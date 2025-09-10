<?php
session_start();
require_once '../config/database.php';

// Inicialização de variáveis
$produtos_db = [];
$total_carrinho = 0;
$custo_personalizacao = 20;
$error_msg = '';
$conn = null;

if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// --- LÓGICA DE MANIPULAÇÃO DO CARRINHO (SESSÃO) ---
// Esta parte do código não precisa de banco de dados, apenas manipula a sessão.
// Por isso, ela pode ser executada antes de qualquer conexão.

// Lógica para adicionar ao carrinho
if (isset($_GET['acao']) && $_GET['acao'] == 'adicionar' && isset($_GET['id'])) {
    $id_produto = intval($_GET['id']);
    
    $tamanho = isset($_GET['tamanho']) ? htmlspecialchars($_GET['tamanho']) : 'Único';
    $nome_pers = isset($_GET['nome_pers']) ? htmlspecialchars($_GET['nome_pers']) : null;
    $num_pers = isset($_GET['num_pers']) ? htmlspecialchars($_GET['num_pers']) : null;

    $cart_item_id = md5($id_produto . $tamanho . $nome_pers . $num_pers);

    if (isset($_SESSION['carrinho'][$cart_item_id])) {
        $_SESSION['carrinho'][$cart_item_id]['qtd']++;
    } else {
        $_SESSION['carrinho'][$cart_item_id] = [
            'id_produto' => $id_produto,
            'qtd' => 1,
            'tamanho' => $tamanho,
            'nome_pers' => $nome_pers,
            'num_pers' => $num_pers
        ];
    }
    
    // Atualiza o contador no localStorage
    echo "<script>
        localStorage.setItem('cartCount', '" . count($_SESSION['carrinho']) . "');
        window.dispatchEvent(new Event('storage'));
    </script>";

    header('Location: carrinho.php');
    exit;
}

// Lógica para remover e alterar quantidade
$cart_item_id = isset($_GET['item_id']) ? $_GET['item_id'] : null;

if (isset($_GET['acao']) && $cart_item_id) {
    if ($_GET['acao'] == 'remover' && isset($_SESSION['carrinho'][$cart_item_id])) {
        unset($_SESSION['carrinho'][$cart_item_id]);
        // Atualiza o contador no localStorage
        echo "<script>
            localStorage.setItem('cartCount', '" . count($_SESSION['carrinho']) . "');
            window.dispatchEvent(new Event('storage'));
        </script>";
        header('Location: carrinho.php');
        exit;
    }
    if ($_GET['acao'] == 'aumentar' && isset($_SESSION['carrinho'][$cart_item_id])) {
        $_SESSION['carrinho'][$cart_item_id]['qtd']++;
        header('Location: carrinho.php');
        exit;
    }
    if ($_GET['acao'] == 'diminuir' && isset($_SESSION['carrinho'][$cart_item_id])) {
        if ($_SESSION['carrinho'][$cart_item_id]['qtd'] > 1) {
            $_SESSION['carrinho'][$cart_item_id]['qtd']--;
        } else {
            unset($_SESSION['carrinho'][$cart_item_id]);
            // Atualiza o contador no localStorage
            echo "<script>
                localStorage.setItem('cartCount', '" . count($_SESSION['carrinho']) . "');
                window.dispatchEvent(new Event('storage'));
            </script>";
        }
        header('Location: carrinho.php');
        exit;
    }
}

// --- LÓGICA PARA BUSCAR DADOS DOS PRODUTOS DO CARRINHO NO BANCO ---
if (!empty($_SESSION['carrinho'])) {
    try {
        $conn = Database::getConnection();

        $ids_produtos = [];
        foreach ($_SESSION['carrinho'] as $item) {
            if (is_array($item) && isset($item['id_produto'])) {
                $ids_produtos[] = $item['id_produto'];
            }
        }
        
        $ids_produtos_unicos = array_unique($ids_produtos);

        if (!empty($ids_produtos_unicos)) {
            // Consulta segura com Prepared Statements para a cláusula IN
            $placeholders = implode(',', array_fill(0, count($ids_produtos_unicos), '?'));
            $types = str_repeat('i', count($ids_produtos_unicos));
            $sql = "SELECT id, nome, preco, imagem FROM produtos WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$ids_produtos_unicos);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            while ($p = $resultado->fetch_assoc()) {
                $produtos_db[$p['id']] = $p;
            }
            $stmt->close();
        }

        // Calcula o valor total do carrinho após ter os preços dos produtos
        foreach ($_SESSION['carrinho'] as $item) {
            if (isset($item['id_produto']) && isset($produtos_db[$item['id_produto']])) {
                $preco_item = $produtos_db[$item['id_produto']]['preco'] + (!empty($item['nome_pers']) ? $custo_personalizacao : 0);
                $total_carrinho += $preco_item * $item['qtd'];
            }
        }
    } catch (Exception $e) {
        $error_msg = "Não foi possível carregar os dados dos produtos. Tente novamente.";
        error_log("Erro em carrinho.php: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seu Carrinho - Mantástico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --cor-principal: #1a6b2f;
            --cor-secundaria: #2c5b2d;
            --cor-erro: #ff6b6b;
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
            padding-bottom: 80px; /* Espaço para o botão flutuante */
        }
        .cart-header { 
            display: flex; 
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px; 
            padding-bottom: 15px;
            border-bottom: var(--borda);
        }
        .cart-header h1 { 
            margin: 0; 
            font-size: 1.8em;
            color: var(--cor-principal);
        }
        .btn { 
            padding: 12px 20px; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: 600; 
            transition: all 0.3s ease;
            text-align: center;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
        .btn-continue { 
            background-color: #e0e0e0; 
            color: #333;
            width: 100%;
        }
        .btn-checkout { 
            background-color: var(--cor-principal); 
            color: white; 
            display: block; 
            width: 100%;
            font-size: 1.1em;
            padding: 15px;
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-checkout:hover {
            background-color: var(--cor-secundaria);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .cart-items { 
            display: block;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .cart-item { 
            display: block;
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: var(--sombra);
            margin-bottom: 15px;
            width: 100%;
            box-sizing: border-box;
            clear: both;
            overflow: hidden;
        }
        .cart-item-content {
            display: flex;
            align-items: flex-start;
            width: 100%;
            gap: 20px;
        }
        .cart-item img { 
            width: 100px; 
            height: 100px; 
            object-fit: cover; 
            border-radius: 6px;
            flex-shrink: 0;
        }
        .item-details { 
            flex: 1;
            min-height: 100px;
        }
        .item-name { 
            font-size: 1.1em; 
            margin: 0 0 8px;
            font-weight: 600;
            color: var(--cor-texto);
            word-break: break-word;
        }
        .item-info {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 8px;
        }
        .item-price { 
            font-weight: bold; 
            color: var(--cor-principal);
            margin: 8px 0;
            font-size: 1.1em;
        }
        .item-actions { 
            clear: both;
            display: flex; 
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: var(--borda);
        }
        .btn-remove, .btn-update { 
            padding: 8px 12px; 
            font-size: 0.9em;
            border-radius: 4px;
        }
        .btn-remove { 
            background-color: #fff; 
            color: var(--cor-erro);
            border: 1px solid var(--cor-erro);
        }
        .quantity-selector { 
            display: flex; 
            align-items: center; 
            gap: 5px;
        }
        .quantity-selector button { 
            background: #f8f9fa; 
            border: var(--borda); 
            width: 32px; 
            height: 32px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            border-radius: 4px;
            font-size: 1.1em;
            color: #555;
        }
        .quantity-selector input { 
            width: 40px; 
            height: 32px;
            text-align: center; 
            border: var(--borda); 
            border-radius: 4px; 
            font-size: 1em;
            -moz-appearance: textfield;
        }
        .quantity-selector input::-webkit-outer-spin-button,
        .quantity-selector input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .cart-summary { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: var(--sombra); 
            margin: 30px auto 0;
            max-width: 800px;
            width: 100%;
            box-sizing: border-box;
        }
        .cart-summary h2 { 
            margin-top: 0; 
            margin-bottom: 20px;
            color: var(--cor-principal);
            font-size: 1.5em;
        }
        .cart-total { 
            font-size: 1.4em; 
            font-weight: bold; 
            text-align: right; 
            margin-top: 20px; 
            padding-top: 20px; 
            border-top: var(--borda);
            color: var(--cor-principal);
        }
        .empty-cart { 
            text-align: center; 
            padding: 40px 20px; 
            background: white;
            border-radius: 8px;
            box-shadow: var(--sombra);
        }
        .empty-cart h2 { 
            color: #555; 
            margin-bottom: 15px;
            font-size: 1.8em;
        }
        .empty-cart p { 
            color: #777; 
            margin-bottom: 25px;
            font-size: 1.1em;
        }
        .empty-cart .btn { 
            display: inline-block;
            background-color: var(--cor-principal);
            color: white;
            padding: 12px 30px;
            font-size: 1.1em;
        }
        .empty-cart .btn:hover {
            background-color: var(--cor-secundaria);
        }
        
        /* Estilos para desktop */
        @media (min-width: 768px) {
            .container {
                padding: 20px 15px 60px;
                max-width: 1000px;
                margin: 0 auto;
                display: block;
            }
            .cart-item-content {
                gap: 25px;
            }
            .cart-item img {
                width: 120px;
                height: 120px;
            }
            .cart-header { 
                display: flex;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                margin: 0 auto 30px;
                width: 100%;
                max-width: 800px;
            }
            .btn-continue { 
                width: auto;
                min-width: 200px;
            }
            .cart-item { 
                margin: 0 auto 20px;
                max-width: 800px;
            }
            .item-actions {
                border-top: var(--borda);
                padding-top: 15px;
                margin-top: 15px;
                justify-content: space-between;
                width: 100%;
                display: flex;
            }
            .cart-summary {
                max-width: 800px;
                margin: 20px auto 0;
                width: 100%;
            }
            .cart-item img {
                width: 120px;
                height: 120px;
                margin-right: 20px;
                object-fit: cover;
            }
            .item-details {
                flex: 1;
                display: flex;
                flex-direction: column;
                width: 100%;
            }
        }
        
        /* Estilos para mobile */
        @media (max-width: 767px) {
            .container {
                padding: 10px 10px 200px;
            }
            .cart-header h1 {
                font-size: 1.6em;
            }
            .cart-item {
                margin: 0 -10px;
                width: calc(100% + 20px);
                border-radius: 0;
                border-bottom: var(--borda);
            }
            .cart-items .cart-item:last-child {
                margin-bottom: 20px; /* Adiciona espaço extra abaixo do último item */
            }
            .cart-summary {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                margin: 0;
                border-radius: 12px 12px 0 0;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
                padding: 15px;
            }
            .cart-summary h2 {
                font-size: 1.3em;
                margin-bottom: 15px;
            }
            .cart-total {
                font-size: 1.3em;
                margin-top: 15px;
                padding-top: 15px;
            }
            .btn-checkout {
                margin-top: 15px;
                padding: 14px;
                font-size: 1.1em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="cart-header">
            <h1>Seu Carrinho</h1>
            <a href="../index.php" class="btn btn-continue">
                <i class="fas fa-arrow-left" style="margin-right: 8px;"></i>Continuar Comprando
            </a>
        </div>

        <?php if ($error_msg): ?>
            <div class="empty-cart">
                <h2 style="color: #c0392b;">Ocorreu um Erro</h2>
                <p><?= htmlspecialchars($error_msg) ?></p>
            </div>
        <?php elseif (empty($_SESSION['carrinho'])): ?>
            <div class="empty-cart">
                <h2>Seu carrinho está vazio.</h2>
                <p>Adicione alguns mantos sagrados à sua coleção!</p>
            </div>
        <?php else: ?>
            <div class="cart-items">
                <?php
                foreach ($_SESSION['carrinho'] as $cart_item_id => $item):
                    $id_produto = $item['id_produto'];
                    if (!isset($produtos_db[$id_produto])) continue;

                    $produto = $produtos_db[$id_produto];
                    $preco_item = $produto['preco'];
                    if (!empty($item['nome_pers'])) {
                        $preco_item += $custo_personalizacao;
                    }
                    $subtotal = $preco_item * $item['qtd'];
                ?>
                <div class="cart-item">
                    <div class="cart-item-content">
                        <?php
                        // Verificar se a imagem contém vírgulas (múltiplas imagens)
                        $imagem_produto = $produto['imagem'];
                        if (strpos($imagem_produto, ',') !== false) {
                            // Se tiver vírgulas, pegar apenas a primeira imagem
                            $imagem_produto = trim(explode(',', $imagem_produto)[0]);
                        }
                        ?>
                        <img src="../assets/images/<?= htmlspecialchars($imagem_produto) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>">
                        <div class="item-details">
                            <h3 class="item-name" title="<?= htmlspecialchars($produto['nome']) ?>">
                                <?= htmlspecialchars($produto['nome']) ?>
                            </h3>
                            <div class="item-info">
                                <?php if (!empty($item['tamanho'])): ?>
                                    <div><i class="fas fa-ruler" style="width: 20px; color: #666;"></i> Tamanho: <?= htmlspecialchars($item['tamanho']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['nome_pers'])): ?>
                                    <div><i class="fas fa-paint-brush" style="width: 20px; color: #666;"></i> 
                                        <?= htmlspecialchars($item['nome_pers']) ?>
                                        <?php if (!empty($item['num_pers'])): ?>
                                            <strong>#<?= htmlspecialchars($item['num_pers']) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-price">R$ <?= number_format($preco_item, 2, ',', '.') ?></div>
                            <div class="item-actions">
                                <a href="?acao=remover&item_id=<?= $cart_item_id ?>" class="btn-remove" 
                                   onclick="return confirm('Tem certeza que deseja remover este item do carrinho?')">
                                    <i class="fas fa-trash"></i> Remover
                                </a>
                                <div class="quantity-selector">
                                    <a href="?acao=diminuir&item_id=<?= $cart_item_id ?>" class="btn-update" title="Diminuir quantidade">
                                        <i class="fas fa-minus"></i>
                                    </a>
                                    <span style="min-width: 30px; text-align: center;"><?= $item['qtd'] ?></span>
                                    <a href="?acao=aumentar&item_id=<?= $cart_item_id ?>" class="btn-update" title="Aumentar quantidade">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($_SESSION['carrinho'])): ?>
        <div class="cart-summary">
            <h2>Resumo do Pedido</h2>
            <div class="cart-total">
                Total: <span>R$ <?= number_format($total_carrinho, 2, ',', '.') ?></span>
            </div>
            <a href="checkout.php" class="btn btn-checkout">
                <i class="fas fa-credit-card" style="margin-right: 8px;"></i>Finalizar Compra
            </a>
        </div>
        <?php endif; ?>
        
        <script>
        // Atualiza o contador do carrinho quando a página carrega
        document.addEventListener('DOMContentLoaded', function() {
            // Suaviza a rolagem para o topo
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Atualiza o contador no localStorage
            const cartCount = <?= count($_SESSION['carrinho']) ?>;
            localStorage.setItem('cartCount', cartCount);
            window.dispatchEvent(new Event('storage'));
            
            // Adiciona confirmação antes de remover itens
            document.querySelectorAll('.btn-remove').forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Tem certeza que deseja remover este item do carrinho?')) {
                        e.preventDefault();
                    }
                });
            });
        });
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
<?php if ($conn) { $conn->close(); } ?>
