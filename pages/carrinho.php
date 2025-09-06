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

    header('Location: carrinho.php');
    exit;
}

// Lógica para remover e alterar quantidade
$cart_item_id = isset($_GET['item_id']) ? $_GET['item_id'] : null;

if (isset($_GET['acao']) && $cart_item_id) {
    if ($_GET['acao'] == 'remover' && isset($_SESSION['carrinho'][$cart_item_id])) {
        unset($_SESSION['carrinho'][$cart_item_id]);
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
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f4f4; margin: 0; color: #333; }
        .container { max-width: 1100px; margin: 30px auto; padding: 20px; }
        .cart-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .cart-header h1 { margin: 0; font-size: 2em; }
        .btn { text-decoration: none; padding: 10px 20px; border-radius: 5px; transition: background-color 0.3s; }
        .btn-continue { background-color: #e0e0e0; color: #333; }
        .btn-checkout { background-color: #2c5b2d; color: #fff; font-size: 1.2em; font-weight: bold; }
        .cart-item { display: flex; align-items: center; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .cart-item img { width: 100px; height: 100px; object-fit: cover; border-radius: 5px; margin-right: 20px; }
        .item-details { flex-grow: 1; }
        .item-details h3 { margin: 0 0 10px; font-size: 1.2em; }
        .item-options { font-size: 0.9em; color: #666; }
        .item-options span { font-weight: bold; }
        .personalizacao-info { color: #2c5b2d; font-weight: bold; }
        .item-quantity { display: flex; align-items: center; }
        .item-quantity a { font-size: 1.5em; color: #333; text-decoration: none; padding: 0 10px; }
        .item-quantity span { font-size: 1.2em; padding: 0 15px; }
        .item-subtotal { font-size: 1.3em; font-weight: bold; min-width: 120px; text-align: right; }
        .item-remove a { color: #c0392b; text-decoration: none; font-weight: bold; margin-left: 30px; }
        .cart-summary { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        .total-price { font-size: 2em; font-weight: bold; }
        .empty-cart { text-align: center; padding: 50px; background-color: #fff; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="cart-header">
            <h1>Seu Carrinho</h1>
            <a href="../index.php" class="btn btn-continue">Continuar Comprando</a>
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
            <div class="cart-items-list">
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
                        <h3><?= htmlspecialchars($produto['nome']) ?></h3>
                        <p class="item-options">Tamanho: <span><?= htmlspecialchars($item['tamanho']) ?></span></p>
                        <?php if (!empty($item['nome_pers'])): ?>
                            <p class="personalizacao-info">
                                Personalização: "<?= htmlspecialchars($item['nome_pers']) ?>", Nº <?= htmlspecialchars($item['num_pers']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="item-quantity">
                        <a href="?acao=diminuir&item_id=<?= $cart_item_id ?>">-</a>
                        <span><?= $item['qtd'] ?></span>
                        <a href="?acao=aumentar&item_id=<?= $cart_item_id ?>">+</a>
                    </div>
                    <div class="item-subtotal">R$ <?= number_format($subtotal, 2, ',', '.') ?></div>
                    <div class="item-remove">
                        <a href="?acao=remover&item_id=<?= $cart_item_id ?>" onclick="return confirm('Deseja remover este item?')">Remover</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <span class="total-price">Total: R$ <?= number_format($total_carrinho, 2, ',', '.') ?></span>
                <a href="checkout.php" class="btn btn-checkout">Finalizar Compra</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php if ($conn) { $conn->close(); } ?>
