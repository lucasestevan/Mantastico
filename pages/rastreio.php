<?php
require_once '../config/database.php';

$pedidos = []; // Agora será um array para guardar vários pedidos
$erro = '';
$busca_realizada = false;
$conn = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['busca'])) {
    $busca_realizada = true;
    try {
        $conn = Database::getConnection();

        // --- LÓGICA DE BUSCA APRIMORADA ---

        // 1. Limpa o valor digitado pelo usuário (remove espaços, pontos, traços)
        $busca_limpa = preg_replace('/[^a-zA-Z0-9-]/', '', $_POST['busca']);
        $busca_limpa = trim($busca_limpa);

        // 2. Prepara a query para ser flexível
        // A função UPPER() torna a busca por código indiferente a maiúsculas/minúsculas
        $sql = "SELECT * FROM pedidos WHERE UPPER(codigo_pedido) = UPPER(?) OR cliente_documento = ? ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $busca_limpa, $busca_limpa);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            // Guarda todos os resultados no array $pedidos de forma mais eficiente
            $pedidos = $resultado->fetch_all(MYSQLI_ASSOC);
        } else {
            $erro = "Nenhum pedido encontrado com os dados informados.";
        }
    } catch (Exception $e) {
        $erro = "Ocorreu um erro ao buscar seu pedido. Tente novamente mais tarde.";
        error_log("Erro em pages/rastreio.php: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastrear Pedido - Mantástico</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 40px auto; }
        .card { background-color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1, h2 { text-align: center; color: #333; }
        .search-form { display: flex; gap: 10px; margin-bottom: 30px; }
        .search-form input { flex-grow: 1; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; }
        .search-form button { background-color: #2c5b2d; color: #fff; border: none; padding: 0 25px; border-radius: 5px; font-weight: bold; cursor: pointer; }
        .error { color: #c0392b; text-align: center; font-weight: bold; }
        .pedido-info p { font-size: 1.1em; line-height: 1.7; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
        .pedido-info p:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0;}
        .pedido-info strong { color: #333; }
        .badge { padding: 5px 10px; border-radius: 5px; color: #fff; font-weight: bold; }
        .bg-success { background-color: #28a745; }
        .bg-warning { background-color: #ffc107; color: #333 !important; }
        .bg-danger { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Rastreie seu Pedido</h1>
            <p style="text-align: center; color: #666;">Digite o código do seu pedido ou seu CPF para ver o status.</p>
            <form class="search-form" method="post">
                <input type="text" name="busca" placeholder="Código do Pedido ou CPF" required>
                <button type="submit">Buscar</button>
            </form>
        </div>
        
        <?php if ($busca_realizada && $erro): ?>
            <div class="card"><p class="error"><?= $erro ?></p></div>
        <?php endif; ?>

        <?php if (!empty($pedidos)): ?>
            <?php foreach ($pedidos as $pedido): ?>
                <div class="card">
                    <h2>Resultado do Pedido #<?= htmlspecialchars($pedido['codigo_pedido']) ?></h2>
                    <div class="pedido-info">
                        <p><strong>Status do Pagamento:</strong> 
                            <?php 
                                $status = htmlspecialchars($pedido['status_pagamento']);
                                $badge_class = 'bg-secondary';
                                if ($status == 'approved') $badge_class = 'bg-success';
                                if ($status == 'pending' || $status == 'in_process') $badge_class = 'bg-warning';
                                if ($status == 'rejected' || $status == 'cancelled') $badge_class = 'bg-danger';
                            ?>
                            <span class="badge <?= $badge_class ?>"><?= ucfirst($status) ?></span>
                        </p>
                        <p><strong>Data do Pedido:</strong> <?= date('d/m/Y H:i', strtotime($pedido['data'])) ?></p>
                        <p><strong>Código de Rastreio:</strong> 
                            <?php if (!empty($pedido['codigo_rastreio'])): ?>
                                <strong><?= htmlspecialchars($pedido['codigo_rastreio']) ?></strong>
                                <a href="https://www2.correios.com.br/sistemas/rastreamento/resultado.cfm?objetos=<?= htmlspecialchars($pedido['codigo_rastreio']) ?>" target="_blank">(Rastrear nos Correios)</a>
                            <?php else: ?>
                                <span class="text-muted">Aguardando envio</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
if ($conn) {
    $conn->close();
}
?>