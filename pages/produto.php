<?php
session_start();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    exit("<h1>Erro: Produto não especificado.</h1>");
}

$id_produto = intval($_GET['id']);

$conn = new mysqli("localhost", "root", "", "mantastico");
if ($conn->connect_error) {
    die("Erro na conexão com o banco de dados.");
}

$stmt = $conn->prepare("SELECT id, nome, preco, imagem, categoria, campeonato FROM produtos WHERE id = ?");
$stmt->bind_param("i", $id_produto);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    exit("<h1>Produto não encontrado.</h1>");
}

$produto = $resultado->fetch_assoc();

$nome = htmlspecialchars($produto['nome']);
$preco_base = $produto['preco'];
$categoria = htmlspecialchars($produto['categoria']);
$campeonato = htmlspecialchars($produto['campeonato']);

$imagens = !empty($produto['imagem']) ? explode(',', $produto['imagem']) : [];
$imagem_principal = !empty($imagens) ? trim($imagens[0]) : 'default.jpg';

if (stripos($nome, 'Infantil') !== false) {
    $tamanhos_disponiveis = ['14', '16', '18', '20', '22', '24', '26', '28'];
} else {
    $tamanhos_disponiveis = ['P', 'M', 'G', 'GG', 'XGG'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nome ?> - Mantástico</title>
    <style>
        :root {
            --cor-principal: #1a6b2f;
            --cor-fundo: #f4f4f4;
            --cor-texto: #212529;
            --sombra: 0 2px 10px rgba(0,0,0,0.1);
            --borda: 1px solid #e0e0e0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body { 
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; 
            background-color: var(--cor-fundo); 
            margin: 0; 
            padding: 0; 
            color: var(--cor-texto);
            line-height: 1.6;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 15px; 
            width: 100%;
        }
        .produto-detalhe { 
            display: grid; 
            grid-template-columns: 1fr; /* Uma coluna por padrão para mobile */
            gap: 20px; 
            align-items: flex-start; 
        }
        @media (min-width: 768px) {
            .produto-detalhe { 
                grid-template-columns: 1fr 1fr; /* Duas colunas para desktop */
                gap: 40px; 
            }
        }
        .galeria-produto { 
            flex: 1;
            position: relative;
            width: 100%;
        }
        #imagem-principal { 
            width: 100%; 
            max-width: 100%;
            height: auto; 
            border-radius: 4px; 
            margin-bottom: 15px;
            display: block;
            aspect-ratio: 1/1;
            object-fit: contain;
            background: #f9f9f9;
        }
        .miniaturas-container { display: flex; gap: 10px; flex-wrap: wrap; }
        .miniaturas-container {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
            -webkit-overflow-scrolling: touch;
        }
        .miniatura { 
            width: 60px; 
            height: 60px; 
            min-width: 60px;
            object-fit: cover; 
            border-radius: 4px; 
            cursor: pointer; 
            border: 2px solid transparent;
            background: #f9f9f9;
        }
        .miniatura:hover, .miniatura.active { 
            border-color: var(--cor-principal);
        }
        .info-produto { 
            flex: 1;
            width: 100%;
        }
        .produto-info h1 { font-size: 2.5em; margin-top: 0; }
        .produto-info .preco { 
            font-size: 1.8em; 
            color: var(--cor-principal); 
            font-weight: bold; 
            margin: 15px 0;
        }
        @media (min-width: 768px) {
            .preco {
                font-size: 2em;
            }
        }
        .info-adicional { font-size: 1.1em; color: #555; margin-bottom: 20px; line-height: 1.6; }
        .opcoes-form { display: flex; flex-direction: column; gap: 20px; }
        .form-group { 
            margin-bottom: 20px;
            width: 100%;
        }
        .form-group label { font-weight: bold; margin-bottom: 8px; }
        .opcoes-container { display: flex; gap: 10px; flex-wrap: wrap; }
        .opcoes-container input[type="radio"] { display: none; }
        .opcoes-container label { display: flex; justify-content: center; align-items: center; border: 2px solid #ccc; cursor: pointer; transition: all 0.2s ease; font-weight: bold; padding: 10px 15px; border-radius: 6px; }
        .tamanhos-container label { width: 45px; height: 45px; border-radius: 50%; padding: 0; }
        .opcoes-container input[type="radio"]:checked + label { background-color: #2c5b2d; color: #fff; border-color: #2c5b2d; }
        #campos-personalizacao { display: none; flex-direction: column; gap: 15px; background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
        select, input[type="text"], input[type="number"] { 
            width: 100%; 
            padding: 12px 15px;
            border: var(--borda);
            border-radius: 6px; 
            margin-bottom: 15px;
            font-size: 1em;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-color: #fff;
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
            padding-right: 40px;
        }
        .btn-adicionar { 
            background-color: var(--cor-principal); 
            color: white; 
            border: none; 
            padding: 15px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 1.1em; 
            font-weight: 600; 
            transition: all 0.3s ease;
            width: 100%;
            display: block;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
        }
        .btn-adicionar:hover { 
            background-color: #145023; 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        @media (min-width: 576px) {
            .btn-adicionar {
                max-width: 300px;
                margin: 0 auto;
            }
        }

        /* Ajustes para Mobile */
        @media (max-width: 767px) {
            .produto-info h1 { 
                font-size: 1.8em; 
            }
            .produto-info .preco {
                font-size: 1.5em;
            }
            .opcoes-container label {
                padding: 8px 12px;
                font-size: 0.9em;
            }
        }

        footer { text-align: center; padding: 20px; background: #111; color: white; margin-top: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="produto-detalhe">
            <div class="galeria-produto">
                <img id="imagem-principal" src="../assets/images/<?= htmlspecialchars($imagem_principal) ?>" alt="Imagem principal de <?= $nome ?>">
                <div class="miniaturas-container">
                    <?php foreach ($imagens as $img_nome): ?>
                        <img class="miniatura" src="../assets/images/<?= htmlspecialchars(trim($img_nome)) ?>" alt="Miniatura de <?= $nome ?>">
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="produto-info">
                <h1><?= $nome ?></h1>
                <p class="info-adicional">
                    <strong>Categoria:</strong> <?= $categoria ?> <br>
                    <strong>Campeonato:</strong> <?= $campeonato ?>
                </p>
                <div class="preco" id="preco-produto">R$ <?= number_format($preco_base, 2, ',', '.') ?></div>
                
                <form id="opcoes-produto" class="opcoes-form">
                    <div class="form-group">
                        <label>Tamanho:</label>
                        <div class="opcoes-container tamanhos-container">
                            <?php foreach ($tamanhos_disponiveis as $tamanho): ?>
                                <input type="radio" id="tamanho-<?= strtolower($tamanho) ?>" name="tamanho" value="<?= $tamanho ?>">
                                <label for="tamanho-<?= strtolower($tamanho) ?>"><?= $tamanho ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Personalização:</label>
                        <div class="opcoes-container personalizacao-container">
                            <input type="radio" id="sem-personalizacao" name="personalizacao" value="nao" checked><label for="sem-personalizacao">Sem personalização</label>
                            <input type="radio" id="com-personalizacao" name="personalizacao" value="sim"><label for="com-personalizacao">Com personalização</label>
                        </div>
                    </div>
                    <div id="campos-personalizacao">
                        <div class="form-group">
                            <label for="nome-personalizado">Nome:</label>
                            <input type="text" id="nome-personalizado" name="nome-personalizado" placeholder="Ex: RONALDO" maxlength="12">
                        </div>
                         <div class="form-group">
                            <label for="numero-personalizado">Número (máx. 2 caracteres):</label>
                            <input type="number" id="numero-personalizado" name="numero-personalizado" maxlength="2" placeholder="Ex: 9">
                        </div>
                    </div>
                    <a href="#" id="btn-adicionar-carrinho" class="btn btn-adicionar disabled">Selecione um tamanho</a>
                </form>
            </div>
        </div>
    </div>

    <footer>&copy; <?= date("Y") ?> Mantástico - Todos os direitos reservados.</footer>

    <script>
        // Script da Galeria
        const imagemPrincipal = document.getElementById('imagem-principal');
        const miniaturas = document.querySelectorAll('.miniatura');
        if(miniaturas.length > 0) { miniaturas[0].classList.add('active'); }
        miniaturas.forEach(miniatura => {
            miniatura.addEventListener('click', function() {
                miniaturas.forEach(m => m.classList.remove('active'));
                this.classList.add('active');
                imagemPrincipal.style.opacity = 0;
                setTimeout(() => { imagemPrincipal.src = this.src; imagemPrincipal.style.opacity = 1; }, 200);
            });
        });

        // Script das Opções de Produto
        const precoElemento = document.getElementById('preco-produto');
        const radiosPersonalizacao = document.querySelectorAll('input[name="personalizacao"]');
        const camposPersonalizacao = document.getElementById('campos-personalizacao');
        const inputNome = document.getElementById('nome-personalizado');
        const inputNumero = document.getElementById('numero-personalizado');
        const btnAdicionarCarrinho = document.getElementById('btn-adicionar-carrinho');
        const radiosTamanho = document.querySelectorAll('input[name="tamanho"]');
        const precoBase = <?= $preco_base ?>;
        const custoPersonalizacao = 20;

        // --- NOVA FUNÇÃO PARA PERMITIR APENAS NÚMEROS ---
        inputNumero.addEventListener('input', function() {
            // Remove qualquer caractere que não seja um número
            this.value = this.value.replace(/\D/g, '');
            // Limita a 2 caracteres
            if (this.value.length > 2) {
                this.value = this.value.slice(0, 2);
            }
        });

        function atualizarPrecoEFormulario() {
            const personalizacaoSelecionada = document.querySelector('input[name="personalizacao"]:checked').value;
            let precoFinal = precoBase;
            if (personalizacaoSelecionada === 'sim') {
                precoFinal += custoPersonalizacao;
                camposPersonalizacao.style.display = 'flex';
                inputNome.required = true;
                inputNumero.required = true;
            } else {
                camposPersonalizacao.style.display = 'none';
                inputNome.required = false;
                inputNumero.required = false;
                inputNome.value = '';
                inputNumero.value = '';
            }
            precoElemento.textContent = `R$ ${precoFinal.toFixed(2).replace('.', ',')}`;
            atualizarBotaoCarrinho();
        }

        function atualizarBotaoCarrinho() {
            const tamanhoSelecionado = document.querySelector('input[name="tamanho"]:checked');
            const personalizacaoSelecionada = document.querySelector('input[name="personalizacao"]:checked').value;
            let isTamanhoOk = !!tamanhoSelecionado;
            let isPersonalizacaoOk = true;
            if (personalizacaoSelecionada === 'sim') {
                isPersonalizacaoOk = inputNome.value.trim() !== '' && inputNumero.value.trim() !== '';
            }
            if (isTamanhoOk && isPersonalizacaoOk) {
                btnAdicionarCarrinho.classList.remove('disabled');
                btnAdicionarCarrinho.textContent = 'Adicionar ao Carrinho';
                let url = `carrinho.php?acao=adicionar&id=<?= $id_produto ?>&tamanho=${tamanhoSelecionado.value}`;
                if (personalizacaoSelecionada === 'sim') {
                    url += `&nome_pers=${encodeURIComponent(inputNome.value)}`;
                    url += `&num_pers=${encodeURIComponent(inputNumero.value)}`;
                }
                btnAdicionarCarrinho.href = url;
            } else {
                btnAdicionarCarrinho.classList.add('disabled');
                if (!isTamanhoOk) {
                    btnAdicionarCarrinho.textContent = 'Selecione um tamanho';
                } else {
                    btnAdicionarCarrinho.textContent = 'Preencha a personalização';
                }
                btnAdicionarCarrinho.href = '#';
            }
        }

        radiosPersonalizacao.forEach(radio => radio.addEventListener('change', atualizarPrecoEFormulario));
        radiosTamanho.forEach(radio => radio.addEventListener('change', atualizarBotaoCarrinho));
        inputNome.addEventListener('input', atualizarBotaoCarrinho);
        inputNumero.addEventListener('input', atualizarBotaoCarrinho);

        atualizarPrecoEFormulario();
    </script>
</body>
</html>