<?php
include 'includes/header.php';
require_once '../config/database.php';

$resultado = null;
$error_msg = '';
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

try {
    $conn = Database::getConnection();

    $sql = "SELECT id, codigo_pedido, total, email_cliente, cliente_documento, status_pagamento, data FROM pedidos";
    $params = [];
    $types = '';

    if (!empty($busca)) {
        $busca_param = "%" . $busca . "%";
        // Se o termo de busca for puramente numérico, também pesquisamos pelo ID do pedido.
        if (ctype_digit($busca)) {
            $sql .= " WHERE id = ? OR codigo_pedido LIKE ? OR email_cliente LIKE ? OR cliente_documento LIKE ? OR status_pagamento LIKE ?";
            $params = [$busca, $busca_param, $busca_param, $busca_param, $busca_param];
            $types = 'issss';
        } else {
            $sql .= " WHERE codigo_pedido LIKE ? OR email_cliente LIKE ? OR cliente_documento LIKE ? OR status_pagamento LIKE ?";
            $params = [$busca_param, $busca_param, $busca_param, $busca_param];
            $types = 'ssss';
        }
    }

    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();

} catch (Exception $e) {
    $error_msg = "Ocorreu um erro no sistema: " . htmlspecialchars($e->getMessage());
    error_log("Erro em admin/pedidos.php: " . $e->getMessage());
}
?>

<h2>Gerenciar Pedidos</h2>

<?php if ($error_msg): ?>
    <div class="alert alert-danger"><?= $error_msg ?></div>
<?php endif; ?>

<div class="card bg-light my-4">
    <div class="card-body">
        <form method="get" action="pedidos.php" class="d-flex">
            <input type="text" name="busca" class="form-control me-2" placeholder="Pesquisar por Cód., e-mail, CPF..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn btn-info">Buscar</button>
            <?php if (!empty($busca)): ?>
                <a href="pedidos.php" class="btn btn-link ms-2">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Cód. Pedido</th>
                <th>Data</th>
                <th>Cliente (E-mail)</th>
                <th>Documento (CPF)</th>
                <th>Total</th>
                <th>Status Pagamento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($pedido = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($pedido['codigo_pedido']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($pedido['data'])) ?></td>
                        <td><?= htmlspecialchars($pedido['email_cliente']) ?></td>
                        <td><?= htmlspecialchars($pedido['cliente_documento']) ?></td>
                        <td>R$ <?= number_format($pedido['total'], 2, ',', '.') ?></td>
                        <td>
                            <?php 
                                $status = htmlspecialchars($pedido['status_pagamento']);
                                $badge_class = 'bg-secondary';
                                if ($status == 'approved') $badge_class = 'bg-success';
                                if ($status == 'pending' || $status == 'in_process') $badge_class = 'bg-warning text-dark';
                                if ($status == 'rejected' || $status == 'cancelled') $badge_class = 'bg-danger';
                            ?>
                            <span class="badge <?= $badge_class ?>"><?= ucfirst($status) ?></span>
                        </td>
                        <td>
                            <a href="pedido_detalhes.php?id=<?= $pedido['id'] ?>" class="btn btn-sm btn-primary">Ver Detalhes</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">Nenhum pedido encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include 'includes/footer.php'; 
?>