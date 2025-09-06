<?php
include 'includes/header.php';
require_once '../config/database.php';

$success_msg = '';
$error_msg = '';
$res = null;
$total_produtos = 0;
$total_paginas = 1;
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;

try {
    $conn = Database::getConnection();

    // Lógica para remover o produto com transação para garantir consistência
    if (isset($_GET['remover']) && filter_var($_GET['remover'], FILTER_VALIDATE_INT)) {
        $id = intval($_GET['remover']);
        
        $conn->begin_transaction();

        // 1. Apaga as imagens do servidor
        $stmt_select = $conn->prepare("SELECT imagem FROM produtos WHERE id = ?");
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $res_select = $stmt_select->get_result();
        if ($produto = $res_select->fetch_assoc()) {
            if (!empty($produto['imagem'])) {
                $imagens = explode(',', $produto['imagem']);
                foreach ($imagens as $img) {
                    $caminho_arquivo = '../assets/images/' . trim($img);
                    if (file_exists($caminho_arquivo) && is_file($caminho_arquivo)) {
                        if (!unlink($caminho_arquivo)) {
                            // Se não conseguir apagar uma imagem, desfaz a transação
                            $conn->rollback();
                            throw new Exception("Falha ao apagar o arquivo de imagem: " . htmlspecialchars($caminho_arquivo));
                        }
                    }
                }
            }
        }
        $stmt_select->close();

        // 2. Deleta o produto do banco
        $stmt_delete = $conn->prepare("DELETE FROM produtos WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();
        
        if ($stmt_delete->affected_rows > 0) {
            $conn->commit();
            $success_msg = "Produto removido com sucesso.";
        } else {
            $conn->rollback();
            $error_msg = "Erro: Produto não encontrado ou já removido.";
        }
        $stmt_delete->close();
    }

    // Lógica de Paginação e Busca com Prepared Statements
    $produtos_por_pagina = 15;
    $offset = ($pagina_atual - 1) * $produtos_por_pagina;
    
    $base_sql = " FROM produtos";
    $where_sql = '';
    $params = [];
    $types = '';

    if (!empty($busca)) {
        $where_sql = " WHERE nome LIKE ? OR categoria LIKE ? OR campeonato LIKE ?";
        $busca_param = "%" . $busca . "%";
        $params = [$busca_param, $busca_param, $busca_param];
        $types = 'sss';
    }

    // Query para contar o total de resultados
    $sql_total = "SELECT COUNT(*) as total" . $base_sql . $where_sql;
    $stmt_total = $conn->prepare($sql_total);
    if (!empty($params)) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $total_produtos = $stmt_total->get_result()->fetch_assoc()['total'];
    $total_paginas = ceil($total_produtos / $produtos_por_pagina);
    if ($total_paginas < 1) $total_paginas = 1;
    $stmt_total->close();

    // Query para buscar os produtos da página atual
    $sql_data = "SELECT * " . $base_sql . $where_sql . " ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt_data = $conn->prepare($sql_data);
    
    $params_data = array_merge($params, [$produtos_por_pagina, $offset]);
    $types_data = $types . 'ii';
    
    $stmt_data->bind_param($types_data, ...$params_data);
    
    $stmt_data->execute();
    $res = $stmt_data->get_result();

} catch (Exception $e) {
    $error_msg = "Ocorreu um erro no sistema: " . htmlspecialchars($e->getMessage());
    error_log($e->getMessage()); // Loga o erro para o administrador
}

?>

<h2>Gerenciar Produtos</h2>
<?php if ($success_msg) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
<?php if ($error_msg) echo "<div class='alert alert-danger'>$error_msg</div>"; ?>

<div class="card bg-light my-4">
    <div class="card-body">
        <form method="get" action="produtos.php" class="d-flex">
            <input type="text" name="busca" class="form-control me-2" placeholder="Pesquisar por nome, categoria ou campeonato..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn btn-info">Buscar</button>
            <?php if (!empty($busca)): ?>
                <a href="produtos.php" class="btn btn-link ms-2">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<p><strong>Exibindo <?= $res ? $res->num_rows : 0 ?> de <?= $total_produtos ?> produtos.</strong></p>

<table class="table table-bordered table-hover align-middle">
  <thead class="table-dark">
    <tr>
      <th style="width: 5%;">ID</th>
      <th style="width: 10%;">Imagem</th>
      <th style="width: 35%;">Nome do Produto</th>
      <th style="width: 15%;">Categoria</th>
      <th style="width: 15%;">Campeonato</th>
      <th style="width: 10%;">Preço</th>
      <th style="width: 15%;">Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($res && $res->num_rows > 0): ?>
        <?php while ($p = $res->fetch_assoc()): ?>
          <tr>
            <td><?= $p['id'] ?></td>
            <td>
                <?php
                    $imagens = explode(',', $p['imagem']);
                    $imagem_principal = trim($imagens[0]);
                ?>
                <img src="../assets/images/<?= htmlspecialchars($imagem_principal) ?>" width="60" class="img-thumbnail">
            </td>
            <td><?= htmlspecialchars($p['nome']) ?></td>
            <td><span class="badge bg-primary"><?= htmlspecialchars($p['categoria']) ?></span></td>
            <td><?= htmlspecialchars($p['campeonato']) ?></td>
            <td>R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
            <td>
                <a href="editar_produto.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                <a href="produtos.php?remover=<?= $p['id'] ?>&pagina=<?= $pagina_atual ?>&busca=<?= urlencode($busca) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja remover este produto e todas as suas imagens? Esta ação não pode ser desfeita.')">Remover</a>
            </td>
          </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="7" class="text-center">Nenhum produto encontrado.</td>
        </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php if ($total_paginas > 1): ?>
<nav aria-label="Navegação de página">
  <ul class="pagination justify-content-center">
    <li class="page-item <?= ($pagina_atual <= 1) ? 'disabled' : '' ?>">
      <a class="page-link" href="?pagina=<?= $pagina_atual - 1 ?>&busca=<?= urlencode($busca) ?>">Anterior</a>
    </li>

    <?php 
        $range = 2; // Quantos números de página mostrar antes e depois da página atual
        for ($i = 1; $i <= $total_paginas; $i++): 
            // Mostra o número da página se for:
            // 1. A primeira página
            // 2. A última página
            // 3. Uma página dentro do 'range' da página atual
            if ($i == 1 || $i == $total_paginas || ($i >= $pagina_atual - $range && $i <= $pagina_atual + $range)):
    ?>
        <li class="page-item <?= ($i == $pagina_atual) ? 'active' : '' ?>">
            <a class="page-link" href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>"><?= $i ?></a>
        </li>
    <?php 
            // Adiciona as reticências '...' se houver um buraco entre os números
            elseif ($i == $pagina_atual - ($range + 1) || $i == $pagina_atual + ($range + 1)):
    ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
    <?php 
            endif;
        endfor; 
    ?>

    <li class="page-item <?= ($pagina_atual >= $total_paginas) ? 'disabled' : '' ?>">
      <a class="page-link" href="?pagina=<?= $pagina_atual + 1 ?>&busca=<?= urlencode($busca) ?>">Próximo</a>
    </li>
  </ul>
</nav>
<?php endif; ?>

<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include 'includes/footer.php'; 
?>