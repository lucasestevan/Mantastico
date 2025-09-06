<?php
include 'includes/header.php';
require_once '../config/database.php';

$pedido = null;
$produtos_info = [];
$msg_rastreio = '';
$error_msg = '';

try {
    $conn = Database::getConnection();

    $id_pedido = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
    if ($id_pedido === false) {
        throw new Exception("ID do pedido inválido ou não fornecido.");
    }

    // Lógica para salvar o código de rastreio
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['codigo_rastreio'])) {
        $codigo_rastreio = trim($_POST['codigo_rastreio']);
        
        $stmt_update = $conn->prepare("UPDATE pedidos SET codigo_rastreio = ? WHERE id = ?");
        $stmt_update->bind_param("si", $codigo_rastreio, $id_pedido);
        if ($stmt_update->execute()) {
            $msg_rastreio = "<div class='alert alert-success mt-3'>Código de rastreio salvo com sucesso!</div>";
        } else {
            throw new Exception("Erro ao salvar o código de rastreio.");
        }
        $stmt_update->close();
    }

    // Busca os dados do pedido
    $stmt_pedido = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt_pedido->bind_param("i", $id_pedido);
    $stmt_pedido->execute();
    $pedido = $stmt_pedido->get_result()->fetch_assoc();
    $stmt_pedido->close();

    if (!$pedido) {
        throw new Exception("Pedido com ID " . htmlspecialchars($id_pedido) . " não encontrado.");
    }

    // Busca as informações dos produtos contidos no pedido de forma segura
    $produtos_no_pedido = json_decode($pedido['produtos'], true);
    $ids_produtos = !empty($produtos_no_pedido) ? array_column(array_filter($produtos_no_pedido, 'is_array'), 'id_produto') : [];

    if (!empty($ids_produtos)) {
        $placeholders = implode(',', array_fill(0, count($ids_produtos), '?'));
        $types = str_repeat('i', count($ids_produtos));
        $sql_produtos = "SELECT id, nome, imagem, preco FROM produtos WHERE id IN ($placeholders)";
        $stmt_produtos = $conn->prepare($sql_produtos);
        $stmt_produtos->bind_param($types, ...$ids_produtos);
        $stmt_produtos->execute();
        $res_produtos = $stmt_produtos->get_result();
        while ($p_info = $res_produtos->fetch_assoc()) {
            $produtos_info[$p_info['id']] = $p_info;
        }
        $stmt_produtos->close();
    }
} catch (Exception $e) {
    $error_msg = "<div class='alert alert-danger mt-3'>" . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("Erro em admin/pedido_detalhes.php: " . $e->getMessage());
}
?>

<?php if ($error_msg): ?>
    <h2>Erro ao Carregar Pedido</h2>
    <a href="pedidos.php" class="btn btn-secondary mb-4">Voltar para a lista de pedidos</a>
    <?= $error_msg ?>
<?php elseif ($pedido): ?>

<h2>Detalhes do Pedido #<?= htmlspecialchars($pedido['codigo_pedido']) ?></h2>
<a href="pedidos.php" class="btn btn-secondary mb-4">Voltar para a lista de pedidos</a>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header"><strong>Informações do Cliente</strong></div>
            <div class="card-body">
                <p><strong>Nome:</strong> <?= htmlspecialchars($pedido['nome_cliente']) ?></p>
                <p><strong>Endereço:</strong> <?= htmlspecialchars($pedido['endereco_cliente']) ?></p>
                <p><strong>WhatsApp:</strong> <?= htmlspecialchars($pedido['cliente_whatsapp']) ?></p>
                <p><strong>E-mail:</strong> <?= htmlspecialchars($pedido['email_cliente']) ?></p>
                <p><strong>Documento (CPF):</strong> <?= htmlspecialchars($pedido['cliente_documento']) ?></p>
            </div>
        </div>
        <div class="card">
             <div class="card-header"><strong>Código de Rastreio</strong></div>
             <div class="card-body">
                <form method="post">
                    <div class="input-group">
                        <input type="text" name="codigo_rastreio" class="form-control" placeholder="Insira o código de rastreio" value="<?= htmlspecialchars($pedido['codigo_rastreio'] ?? '') ?>">
                        <button class="btn btn-success" type="submit">Salvar</button>
                    </div>
                </form>
                <?= $msg_rastreio ?>
             </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header"><strong>Informações do Pagamento</strong></div>
            <div class="card-body">
                <p><strong>Status:</strong> <span class="badge bg-info"><?= htmlspecialchars($pedido['status_pagamento']) ?></span></p>
                <p><strong>ID da Transação (Mercado Pago):</strong> <?= htmlspecialchars($pedido['id_pagamento_externo']) ?></p>
                <p><strong>Valor:</strong> 
                    <strong class="text-success">R$ <?= number_format($pedido['total'], 2, ',', '.') ?></strong> 
                    em 
                    <strong><?= htmlspecialchars($pedido['parcelas'] ?? 1) ?>x</strong>
                </p>
            </div>
        </div>
    </div>
</div>

<h4 class="mt-4">Itens do Pedido</h4>
<table class="table table-bordered mt-3">
    <thead class="table-dark">
        <tr>
            <th>Produto</th>
            <th>Detalhes</th>
            <th>Preço Unit.</th>
            <th>Qtd</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php if(!empty($produtos_no_pedido)): ?>
            <?php foreach ($produtos_no_pedido as $item): ?>
                <?php 
                    if (!is_array($item) || !isset($item['id_produto']) || !isset($produtos_info[$item['id_produto']])) continue;
                    
                    $produto = $produtos_info[$item['id_produto']];
                    $preco_item = $produto['preco'];
                    if (!empty($item['nome_pers'])) { $preco_item += 20; }
                    $subtotal = $preco_item * $item['qtd'];
                ?>
                    <tr>
                        <td>
                            <?php
                            // Verificar se a imagem contém vírgulas (múltiplas imagens)
                            $imagem_produto = $produto['imagem'];
                            if (strpos($imagem_produto, ',') !== false) {
                                // Se tiver vírgulas, pegar apenas a primeira imagem
                                $imagem_produto = trim(explode(',', $imagem_produto)[0]);
                            }
                            ?>
                            <img src="../assets/images/<?= htmlspecialchars($imagem_produto) ?>" width="50" class="me-2">
                            <?= htmlspecialchars($produto['nome']) ?>
                        </td>
                        <td>
                            <strong>Tamanho:</strong> <?= htmlspecialchars($item['tamanho']) ?><br>
                            <?php if (!empty($item['nome_pers'])): ?>
                                <strong class="text-success">Personalização:</strong><br>
                                &nbsp;&nbsp;Nome: <?= htmlspecialchars($item['nome_pers']) ?><br>
                                &nbsp;&nbsp;Número: <?= htmlspecialchars($item['num_pers']) ?>
                            <?php else: ?>
                                <span class="text-muted">Sem personalização</span>
                            <?php endif; ?>
                        </td>
                        <td>R$ <?= number_format($preco_item, 2, ',', '.') ?></td>
                        <td><?= $item['qtd'] ?></td>
                        <td>R$ <?= number_format($subtotal, 2, ',', '.') ?></td>
                    </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5" class="text-center">Não foi possível carregar os itens deste pedido.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php endif; ?>

<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include 'includes/footer.php'; 
?>