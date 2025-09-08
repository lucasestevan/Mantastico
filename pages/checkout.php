<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

if (empty($_SESSION['carrinho'])) {
    header('Location: ../index.php');
    exit;
}

// Inicialização de variáveis
$total = 0;
$error_msg = '';
$conn = null;
$custo_personalizacao = 20;

// Obter a chave pública do Mercado Pago
$mercadoPagoPublicKey = $_ENV['MERCADO_PAGO_PUBLIC_KEY'] ?? '';

// Verificar se a chave pública está configurada
if (empty($mercadoPagoPublicKey)) {
    die('Erro: Chave pública do Mercado Pago não configurada.');
}

try {
    $conn = Database::getConnection();

    $ids_produtos = [];
    foreach ($_SESSION['carrinho'] as $item) {
        if (is_array($item) && isset($item['id_produto'])) {
            $ids_produtos[] = $item['id_produto'];
        }
    }
    
    $produtos_db = [];
    $ids_produtos_unicos = array_unique($ids_produtos);

    if (!empty($ids_produtos_unicos)) {
        $placeholders = implode(',', array_fill(0, count($ids_produtos_unicos), '?'));
        $types = str_repeat('i', count($ids_produtos_unicos));
        $sql = "SELECT id, preco FROM produtos WHERE id IN ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$ids_produtos_unicos);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        while ($p = $resultado->fetch_assoc()) {
            $produtos_db[$p['id']] = $p;
        }
        $stmt->close();
    }

    foreach ($_SESSION['carrinho'] as $item) {
        if (is_array($item) && isset($item['id_produto']) && isset($produtos_db[$item['id_produto']])) {
            $preco_item = $produtos_db[$item['id_produto']]['preco'] + (!empty($item['nome_pers']) ? $custo_personalizacao : 0);
            $total += $preco_item * $item['qtd'];
        }
    }
} catch (Exception $e) {
    $error_msg = "Ocorreu um erro ao calcular o total do seu pedido. Por favor, volte ao carrinho e tente novamente.";
    error_log("Erro em finalizar.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - Mantástico</title>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f4f4; padding: 20px 0; }
        .checkout-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); width: 100%; max-width: 600px; margin: auto;}
        h2, h3 { text-align: center; margin-bottom: 20px; }
        .total-valor { text-align: center; font-size: 1.2em; font-weight: bold; margin-bottom: 30px; }
        #form-checkout { display: flex; flex-direction: column; gap: 15px; }
        .form-group { display: flex; flex-direction: column; position: relative; }
        .form-group label { margin-bottom: 5px; font-weight: bold; }
        .form-group input { padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; }
        .form-group input:read-only { background-color: #e9ecef; cursor: not-allowed; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1 1 0; }
        hr { border: 1px solid #eee; margin: 30px 0; }
        #cep-loading { font-size: 0.8em; color: #009ee3; position: absolute; right: 10px; bottom: 10px; display: none; }
        
        #payment-section, #payment-separator, .spinner { 
            display: none;
        }
        
        #customer-info-section {
            display: block;
        }
        
        .spinner {
            text-align: center;
            padding: 20px;
        }
        
        /* Estilo para campos somente leitura */
        input[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }

        /* Estilos para seleção do método de pagamento */
        .payment-method-selector {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }

        .payment-option {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: #2c5b2d;
            background-color: #f8fff8;
        }

        .payment-option input[type="radio"] {
            display: none;
        }

        .payment-option label {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 0;
            cursor: pointer;
        }

        .payment-icon {
            font-size: 24px;
            width: 40px;
            text-align: center;
        }

        .payment-title {
            font-weight: bold;
            font-size: 18px;
            color: #333;
        }

        .payment-description {
            color: #666;
            font-size: 14px;
        }

        .payment-option.selected {
            border-color: #2c5b2d;
            background-color: #f8fff8;
            box-shadow: 0 2px 8px rgba(44, 91, 45, 0.1);
        }

        /* Formulários de pagamento */
        .payment-form {
            display: none;
            margin-top: 20px;
        }

        .payment-form.active {
            display: block;
        }

        .pix-info {
            margin-bottom: 15px;
            color: #666;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2c5b2d;
        }
        
        #cardPaymentBrick_container,
        #pixPaymentBrick_container {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #fff;
        }
        
        .payment-method-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #2c5b2d;
        }

        #btn-goto-payment { background-color: #2c5b2d; color: #fff; font-size: 1.2em; font-weight: bold; padding: 15px; border-radius: 5px; border: none; cursor: pointer; margin-top: 15px; transition: all 0.3s ease; width: 100%; }
        #btn-goto-payment:hover { background-color: #1e421f; transform: translateY(-2px); }
        #payment-section { margin-top: 30px; }
        #payment-section h3 { color: #2c5b2d; }
        .payment-option { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; cursor: pointer; transition: all 0.3s ease; }
        .payment-option:hover { border-color: #2c5b2d; box-shadow: 0 2px 8px rgba(44, 91, 45, 0.1); }
        .payment-option.selected { border-color: #2c5b2d; background-color: #f8fff8; }
    </style>
</head>
<body>
    <div class="checkout-container">
        <?php if ($error_msg): ?>
            <h2>Ocorreu um Erro</h2>
            <div class="alert alert-danger" style="color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem;"><?= htmlspecialchars($error_msg) ?></div>
            <a href="carrinho.php" class="btn-back">Voltar ao Carrinho</a>
            <style>.btn-back { display: block; text-align: center; margin-top: 20px; text-decoration: none; background-color: #6c757d; color: white; padding: 10px; border-radius: 5px; }</style>
        <?php else: ?>

            <h2>Finalizar Compra</h2>
            <p class="total-valor">Total a Pagar: R$ <?= number_format($total, 2, ',', '.') ?></p>
            
        <form id="form-checkout">
            <div id="customer-info-section">
                <h3>Seus Dados e Endereço de Entrega</h3>
                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required placeholder="seuemail@exemplo.com">
                </div>
                <div class="form-group">
                    <label for="whatsapp">WhatsApp (com DDD)</label>
                    <input type="tel" id="whatsapp" name="whatsapp" required placeholder="Ex: 11987654321">
                </div>
                <div class="form-group">
                    <label for="docNumber">CPF</label>
                    <input type="text" id="docNumber" name="docNumber" required placeholder="Seu número de CPF (apenas números)">
                </div>
                <div class="form-group">
                    <label for="cep">CEP</label>
                    <input type="text" id="cep" name="cep" required placeholder="Digite um CEP válido para continuar">
                    <span id="cep-loading">Buscando...</span>
                </div>
                 <div class="form-row">
                     <div class="form-group" style="flex-grow: 3;">
                        <label for="rua">Rua / Logradouro</label>
                        <input type="text" id="rua" name="rua" required readonly>
                    </div>
                     <div class="form-group" style="flex-grow: 1;">
                        <label for="numero">Número</label>
                        <input type="text" id="numero" name="numero" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex-grow: 2;">
                        <label for="bairro">Bairro</label>
                        <input type="text" id="bairro" name="bairro" required readonly>
                    </div>
                    <div class="form-group" style="flex-grow: 2;">
                        <label for="cidade">Cidade</label>
                        <input type="text" id="cidade" name="cidade" required readonly>
                    </div>
                    <div class="form-group" style="flex-grow: 0; min-width: 80px;">
                        <label for="estado">UF</label>
                        <input type="text" id="estado" name="estado" maxlength="2" required readonly>
                    </div>
                </div>
                <button type="button" id="btn-goto-payment">Ir para o Pagamento</button>
            </div>
            
            <hr id="payment-separator">
            
            <div id="payment-section">
                <h3>Escolha o Método de Pagamento</h3>
                
                <!-- O container do Payment Brick substitui a seleção manual e os formulários separados. -->
                <!-- Ele gerenciará a exibição de Cartão de Crédito, PIX, etc. -->
                <div id="paymentBrick_container"></div>
                
            </div>
        </form>
        <div id="spinner" class="spinner"></div>

        <?php endif; ?>
    </div>

    <script>
        // --- Elementos do DOM ---
        const customerInfoSection = document.getElementById('customer-info-section');
        const paymentSection = document.getElementById('payment-section');
        const paymentSeparator = document.getElementById('payment-separator');
        const btnGoToPayment = document.getElementById('btn-goto-payment');
        const cepInput = document.getElementById('cep');
        const ruaInput = document.getElementById('rua');
        const bairroInput = document.getElementById('bairro');
        const cidadeInput = document.getElementById('cidade');
        const estadoInput = document.getElementById('estado');
        const numeroInput = document.getElementById('numero');
        const cepLoading = document.getElementById('cep-loading');
        
        
        // Verifica se todos os elementos foram encontrados
        [customerInfoSection, paymentSection, paymentSeparator, btnGoToPayment].forEach(element => {
            if (!element) {
                console.error('Elemento não encontrado:', element);
            }
        });
        
        const allInfoFields = [
            document.getElementById('nome'),
            document.getElementById('email'),
            document.getElementById('whatsapp'),
            document.getElementById('docNumber'),
            cepInput,
            ruaInput,
            numeroInput,
            bairroInput,
            cidadeInput,
            estadoInput
        ];
        
        // Verifica se todos os campos do formulário foram encontrados
        allInfoFields.forEach(field => {
            if (!field) {
                console.error('Campo do formulário não encontrado:', field);
            }
        });

        // --- LÓGICA DE BUSCA DE CEP ---
        async function buscarCEP(cep) {
            cep = cep.replace(/\D/g, '');
            if (cep.length !== 8) return;
            
            // Mostra o loading
            cepLoading.style.display = 'block';
            
            try {
                const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                const data = await response.json();
                
                if (data.erro) {
                    throw new Error('CEP não encontrado');
                }
                
                console.log('Dados do CEP:', data);
                
                // Preenche os campos com os dados do CEP
                if (ruaInput) ruaInput.value = data.logradouro || '';
                if (bairroInput) bairroInput.value = data.bairro || '';
                if (cidadeInput) cidadeInput.value = data.localidade || '';
                if (estadoInput) estadoInput.value = data.uf || '';
                
                // Foca no campo de número após preencher o CEP
                if (document.getElementById('numero')) {
                    document.getElementById('numero').focus();
                }
                
                return true;
            } catch (error) {
                console.error('Erro ao buscar CEP:', error);
                
                // Limpa os campos de endereço
                if (ruaInput) ruaInput.value = '';
                if (bairroInput) bairroInput.value = '';
                if (cidadeInput) cidadeInput.value = '';
                if (estadoInput) estadoInput.value = '';
                
                if (error.message === 'CEP não encontrado') {
                    alert('CEP não encontrado. Por favor, verifique o número e tente novamente.');
                } else {
                    alert('Não foi possível buscar o CEP. Verifique sua conexão e tente novamente.');
                }
                
                // Foca de volta no campo de CEP
                cepInput.focus();
                return false;
            } finally {
                cepLoading.style.display = 'none';
            }
        }

        // Formata o CEP enquanto digita
        cepInput.addEventListener('input', function(e) {
            console.log('Evento input do CEP disparado');
            let value = e.target.value.replace(/\D/g, '');
            console.log('Valor do CEP após remover não-números:', value);
            
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
                console.log('Valor do CEP após formatação:', value);
            }
            e.target.value = value;
            
            // Busca automaticamente quando tiver 8 dígitos (sem contar o hífen)
            if (value.replace(/\D/g, '').length === 8) {
                console.log('Buscando CEP:', value);
                buscarCEP(value);
            } else {
                console.log('CEP incompleto:', value);
            }
        });
        
        // Busca quando o campo perde o foco (caso o usuário não tenha digitado o CEP completo)
        cepInput.addEventListener('blur', () => {
            console.log('Evento blur do CEP disparado');
            const cep = cepInput.value.replace(/\D/g, '');
            console.log('CEP no blur:', cep);
            if (cep.length === 8) {
                console.log('Buscando CEP no blur:', cep);
                buscarCEP(cep);
            }
        });

        // --- LÓGICA DO BOTÃO "IR PARA O PAGAMENTO" ---
        btnGoToPayment.addEventListener('click', (event) => {
            event.preventDefault();
            console.log('Botão clicado'); // Debug

            let allFieldsValid = true;
            let emptyFields = [];
            
            allInfoFields.forEach(field => {
                console.log('Verificando campo:', field.id, 'valor:', field.value); // Debug
                if (field.value.trim() === '') {
                    allFieldsValid = false;
                    emptyFields.push(field.id);
                }
            });

            console.log('Campos válidos:', allFieldsValid); // Debug
            console.log('Campos vazios:', emptyFields); // Debug

            if (allFieldsValid) {
                console.log('Mostrando seção de pagamento'); // Debug
                
                // Oculta a seção de informações do cliente
                customerInfoSection.style.display = 'none';
                
                // Mostra a seção de pagamento e o separador
                paymentSection.style.display = 'block';
                paymentSeparator.style.display = 'block';
                
                // Renderiza o brick somente na primeira vez que a seção é exibida
                if (!window.brickRendered) {
                    // Mostra um spinner enquanto o brick carrega
                    document.getElementById('paymentBrick_container').innerHTML = '<p style="text-align:center;">Carregando opções de pagamento...</p>';
                    // Adiciona um pequeno delay para garantir que o container está visível no DOM antes de renderizar o brick.
                    setTimeout(() => {
                        renderPaymentBrick(bricksBuilder);
                        window.brickRendered = true;
                    }, 50); // 50ms é um delay seguro e imperceptível.
                }

                // Rola a tela para a seção de pagamento
                setTimeout(() => {
                    paymentSection.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            } else {
                // Mostra mensagem de erro mais amigável
                const camposFaltantes = emptyFields.map(id => {
                    const label = document.querySelector(`label[for="${id}"]`);
                    return label ? label.textContent : id;
                });
                
                alert('Por favor, preencha todos os campos obrigatórios:\n\n' + camposFaltantes.join('\n'));
            }
        });

        // --- SCRIPT DO MERCADO PAGO ---
        const mp = new MercadoPago('<?= $mercadoPagoPublicKey ?>', {
            locale: 'pt-BR'
        });
        const bricksBuilder = mp.bricks();
        window.brickRendered = false; // Flag para controlar a renderização do brick

        const renderPaymentBrick = async (builder) => {
            // Prepara os dados do pagador a partir do formulário
            const nomeCompleto = document.getElementById('nome').value.trim();
            const nomes = nomeCompleto.split(' ');
            const primeiroNome = nomes.shift() || '';
            const sobrenome = nomes.join(' ') || '';

            const settings = {
                initialization: {
                    amount: <?= $total ?>,
                    payer: {
                        entityType: 'individual',
                        email: document.getElementById('email').value,
                        firstName: primeiroNome,
                        lastName: sobrenome,
                        identification: {
                            type: 'CPF',
                            number: document.getElementById('docNumber').value.replace(/\D/g, ''),
                        },
                        address: {
                            zipCode: document.getElementById('cep').value.replace(/\D/g, ''),
                            streetName: document.getElementById('rua').value,
                            streetNumber: document.getElementById('numero').value,
                            neighborhood: document.getElementById('bairro').value,
                            city: document.getElementById('cidade').value,
                            federalUnit: document.getElementById('estado').value
                        }
                    },
                },
                customization: {
                    paymentMethods: {
                        ticket: 'all',
                        creditCard: 'all',
                        debitCard: 'all',
                        bankTransfer: ['pix']
                    },
                    visual: {
                        style: {
                            theme: 'default' // ou 'dark', 'bootstrap'
                        }
                    }
                },
                callbacks: {
                    onReady: () => {
                        console.log('Mercado Pago Payment Brick está pronto. O componente deve ser visível agora.');
                    },
                    onSubmit: ({ selectedPaymentMethod, formData }) => {
                        console.log('Método selecionado:', selectedPaymentMethod);
                        console.log('Dados do formulário:', formData);
                        document.getElementById('spinner').style.display = 'flex';
                        document.getElementById('spinner').innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';

                        // Adiciona dados do cliente e endereço que não são coletados pelo Brick
                        const additionalData = {
                            nome_cliente: document.getElementById('nome').value,
                            cliente_whatsapp: document.getElementById('whatsapp').value,
                            cliente_documento: document.getElementById('docNumber').value,
                            endereco: {
                                rua: ruaInput.value,
                                numero: numeroInput.value,
                                bairro: bairroInput.value,
                                cidade: cidadeInput.value,
                                estado: estadoInput.value,
                                cep: cepInput.value
                            }
                        };

                        // Garante que o e-mail do pagador seja o e-mail preenchido no formulário
                        if (!formData.payer) formData.payer = {};
                        formData.payer.email = document.getElementById('email').value;

                        // O 'payment_method_id' (bandeira do cartão) já vem correto no formData.
                        // Nós só precisamos garantir que os nossos dados adicionais sejam incluídos.
                        const finalData = {
                            ...formData,
                            ...additionalData
                        };

                        // Corrige o nome do método de pagamento para o backend APENAS se for PIX.
                        if (selectedPaymentMethod === 'bank_transfer') {
                            finalData.payment_method_id = 'pix';
                        }

                        console.log('Dados enviados:', finalData);
                        return new Promise((resolve, reject) => {
                            fetch("processar_pagamento.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify(finalData)
                            })
                            .then(async response => {
                                let data;
                                try {
                                    if (!response.ok) {
                                        const errorText = await response.text();
                                        throw new Error(`Erro no servidor: ${response.status} - ${errorText}`);
                                    }
                                    data = await response.json();
                                } catch (e) {
                                    throw new Error('Erro ao processar resposta do servidor. ' + e.message);
                                }
                                document.getElementById('spinner').style.display = 'none';
                                
                                // Se é um pagamento PIX, redireciona para a página do QR Code
                                if (data.payment_type === 'pix' && data.redirect_url) {
                                    console.log('Redirecionando para página do PIX');
                                    window.location.href = data.redirect_url;
                                    return resolve();
                                }
                                
                                // Para outros métodos, se temos um pedido_id, consideramos como sucesso
                                if (data.pedido_id) {
                                    console.log('Redirecionando para status do pedido:', data.pedido_id);
                                    window.location.href = `status_pedido.php?id=${data.pedido_id}`;
                                    return resolve();
                                }
                                
                                // Se não temos pedido_id mas temos erro
                                if (data.error) {
                                    let mensagemErro = data.message || 'Ocorreu um erro ao processar seu pagamento.';
                                    console.error('Erro de processamento:', data);
                                    alert(mensagemErro);
                                    return reject();
                                }
                                
                                // Se chegou aqui, temos uma resposta inesperada
                                console.error('Resposta inesperada:', data);
                                throw new Error('Resposta inesperada do servidor');
                            })
                            .catch(error => {
                                document.getElementById('spinner').style.display = 'none';
                                console.error('Erro na Promise:', error);
                                alert("Ocorreu um erro: " + error.message);
                                reject();
                            });
                        });
                    },
                    onError: (error) => {
                        console.error('Brick error:', error);
                        alert('Dados de pagamento inválidos. Verifique as informações e tente novamente.');
                        // Mensagem mais específica pode ser útil aqui
                        document.getElementById('paymentBrick_container').innerHTML = '<p style="color:red; text-align:center;">Ocorreu um erro ao carregar as opções de pagamento. Por favor, recarregue a página e tente novamente.</p>';
                    },
                },
            };
            // Renderiza o brick de Pagamento (que inclui Cartão e PIX)
            window.paymentBrickController = await builder.create('payment', 'paymentBrick_container', settings);
        };
    </script>
</body>
<?php if ($conn) { $conn->close(); } ?>
</html>