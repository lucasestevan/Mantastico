<?php
session_start();
require_once 'config/database.php';

// Função auxiliar para renderizar a grade de produtos
function render_product_grid($result) {
    if ($result && $result->num_rows > 0) {
        echo '<div class="produtos-grid">';
        while ($produto = $result->fetch_assoc()) {
            $imagens = explode(',', $produto['imagem']);
            $foto_principal = trim($imagens[0]);
            echo '<div class="produto-card"> <a href="pages/produto.php?id='. $produto['id'] .'" class="produto-imagem-container"> <img src="assets/images/'. htmlspecialchars($foto_principal) .'" alt="Camisa '. htmlspecialchars($produto['nome']) .'"> </a> <div class="produto-info"> <h3>'. htmlspecialchars($produto['nome']) .'</h3> <p class="preco">R$ '. number_format($produto['preco'], 2, ',', '.') .'</p> <a href="pages/produto.php?id='. $produto['id'] .'" class="btn-ver-produto">Ver Detalhes</a> </div> </div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-center">Nenhum produto encontrado com os filtros selecionados.</p>';
    }
}

    // Inicialização de variáveis
    $conn = null;
    $error_msg = '';
    $resultado_catalogo = null;
    $categorias_disponiveis = null;
    $campeonatos_disponiveis = null;
    $total_produtos = 0;
    $total_paginas = 1;
    $pagina_atual = 1;
    $primary_filter_type = null;
    $primary_filter_value = '';
    $termo_busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    
try {
    $conn = Database::getConnection();

    // --- LÓGICA DE FILTRO E TÍTULO ---
    $where_clauses = [];
    $param_types = '';
    $param_values = [];

    // Removida condição de status pois a coluna não existe mais

    // Identifica o filtro principal vindo da index
    if (isset($_GET['categoria_principal'])) {
        $primary_filter_type = 'categoria';
        $primary_filter_value = $_GET['categoria_principal'];
    } elseif (isset($_GET['campeonato_principal'])) {
        $primary_filter_type = 'campeonato';
        $primary_filter_value = $_GET['campeonato_principal'];
    }

    // Constrói a query com base em TODOS os filtros
    if (!empty($termo_busca)) {
        $where_clauses[] = "LOWER(nome) LIKE LOWER(?)";
        $param_types .= 's';
        $termo_like = "%{$termo_busca}%";
        $param_values[] = $termo_like;
    }

    if ($primary_filter_type) {
        $where_clauses[] = "LOWER($primary_filter_type) = LOWER(?)";
        $param_types .= 's';
        $param_values[] = $primary_filter_value;
    }

    if (isset($_GET['categoria'])) {
        $values = (array)$_GET['categoria'];
        $placeholder_group = implode(',', array_fill(0, count($values), '?'));
        $where_clauses[] = "categoria IN ($placeholder_group)";
        foreach($values as $value){ $param_types .= 's'; $param_values[] = $value; }
    }
    if (isset($_GET['campeonato'])) {
        $values = (array)$_GET['campeonato'];
        $placeholder_group = implode(',', array_fill(0, count($values), '?'));
        $where_clauses[] = "campeonato IN ($placeholder_group)";
        foreach($values as $value){ $param_types .= 's'; $param_values[] = $value; }
    }

    $condicao_sql = "";
    if (!empty($where_clauses)) {
        $condicao_sql = " WHERE " . implode(' AND ', $where_clauses);
    }

    // --- LÓGICA DE PAGINAÇÃO ---
    $produtos_por_pagina = 15;
    $pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina_atual < 1) $pagina_atual = 1;
    $offset = ($pagina_atual - 1) * $produtos_por_pagina;

    // Busca o total de produtos para a paginação
    $sql_total = "SELECT COUNT(*) as total FROM produtos" . $condicao_sql;
    $stmt_total = $conn->prepare($sql_total);
    if (!empty($param_values)) { $stmt_total->bind_param($param_types, ...$param_values); }
    $stmt_total->execute();
    $total_produtos = $stmt_total->get_result()->fetch_assoc()['total'];
    $total_paginas = ceil($total_produtos / $produtos_por_pagina);
    if ($total_paginas < 1) $total_paginas = 1;
    $stmt_total->close();

    // Busca os produtos da página atual
    $sql_catalogo = "SELECT id, nome, preco, imagem, categoria, campeonato FROM produtos" . $condicao_sql . " ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt_catalogo = $conn->prepare($sql_catalogo);
    $final_param_types = $param_types . 'ii';
    $final_param_values = array_merge($param_values, [$produtos_por_pagina, $offset]);
    $stmt_catalogo->bind_param($final_param_types, ...$final_param_values);
    $stmt_catalogo->execute();
    $resultado_catalogo = $stmt_catalogo->get_result();
    $stmt_catalogo->close();

    // Busca filtros disponíveis para a sidebar (estas queries são seguras pois não usam input do usuário)
    $categorias_disponiveis = $conn->query("SELECT DISTINCT categoria FROM produtos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria ASC");
    $campeonatos_disponiveis = $conn->query("SELECT DISTINCT campeonato FROM produtos WHERE campeonato IS NOT NULL AND campeonato != '' ORDER BY campeonato ASC");

} catch (Exception $e) {
    // Em ambiente de desenvolvimento, mostra o erro real
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $error_msg = "Erro: " . $e->getMessage();
    } else {
        // Em produção, mostra mensagem genérica
        $error_msg = "Ocorreu um erro ao carregar o catálogo. Por favor, tente novamente mais tarde.";
    }
    error_log("Erro em catalogo.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $primary_filter_value ? htmlspecialchars(ucfirst($primary_filter_value)) : 'Catálogo' ?> - Mantástico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --cor-principal: #1a6b2f; --cor-fundo: #f8f9fa; --cor-texto: #212529; }
        body { margin: 0; font-family: 'Roboto', sans-serif; background-color: var(--cor-fundo); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
        .main-header { background-color: #fff; border-bottom: 1px solid #e9ecef; padding: 15px 0; }
        .main-header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 2em; font-weight: 900; color: var(--cor-principal); text-decoration: none; }
        .main-nav a { color: var(--cor-texto); text-decoration: none; margin: 0 15px; font-weight: 700; }
        .main-footer { background-color: #212529; color: #fff; text-align: center; padding: 40px 20px; margin-top: 60px; }
        .catalogo-container { display: flex; gap: 30px; margin-top: 40px; }
        .sidebar { width: 250px; flex-shrink: 0; }
        .main-content { flex-grow: 1; }
        .filtro-widget { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .filtro-widget h4 { font-weight: 900; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .filtro-widget .form-check { margin-bottom: 10px; }
        .filtro-widget .form-check-label { font-weight: 700; }
        .produtos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .produto-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s; display: flex; flex-direction: column; }
        .produto-card:hover { transform: translateY(-5px); }
        .produto-imagem-container img { width: 100%; height: auto; display: block; aspect-ratio: 1 / 1; object-fit: cover; }
        .produto-info { padding: 15px; display: flex; flex-direction: column; flex-grow: 1; text-align: center; }
        .produto-info h3 { margin: 0 0 10px; font-size: 1rem; font-weight: 700; flex-grow: 1; min-height: 40px;}
        .produto-info .preco { margin: 10px 0; font-weight: 900; font-size: 1.4em; }
        .btn-ver-produto { display: block; width: 100%; text-align: center; background-color: var(--cor-principal); color: #fff; padding: 10px; text-decoration: none; border-radius: 5px; font-weight: 700; transition: background-color 0.2s; margin-top: auto; }
        .paginacao { margin-top: 40px; }
        .pagination .page-link { color: var(--cor-principal); }
        .pagination .page-item.active .page-link { background-color: var(--cor-principal); border-color: var(--cor-principal); }

        /* Estilos para o header fixo e filtros mobile */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 1020;
            background-color: #fff;
        }

        @media (max-width: 992px) {
            .sticky-header {
                position: fixed;
                top: 0;
                width: 100%;
                background-color: #fff; /* Ensure it has a background */
                box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Optional: add a subtle shadow */
            }
            .main-content {
                padding-top: 140px; /* Adjust based on header height */
            }
            .catalogo-container {
                margin-top: 0; /* Remove top margin as header is fixed */
            }
        }

        .mobile-filter-trigger {
            display: none; /* Oculto no desktop */
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: var(--cor-principal);
            color: #fff;
            text-align: center;
            padding: 10px;
            font-size: 1.2em;
            font-weight: 700;
            border: none;
            border-radius: 25px 25px 0 0;
            z-index: 1000;
            cursor: pointer;
        }

        @media (max-width: 767px) {
            .produtos-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            .produto-info h3 {
                font-size: 0.9rem;
                min-height: 36px;
            }
            .produto-info .preco {
                font-size: 1.2em;
            }
        }

        .filter-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1030; /* Acima do conteúdo, abaixo do painel */
        }

        .filter-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 80vh;
            background-color: #fff;
            transform: translateY(100%);
            transition: transform 0.3s ease-in-out;
            z-index: 1040;
            padding: 20px;
            overflow-y: auto;
            border-radius: 15px 15px 0 0;
        }

        .filter-panel.active {
            transform: translateY(0);
        }

        @media (max-width: 992px) {
            .catalogo-container {
                flex-direction: column;
            }
            .sidebar {
                display: none; /* Oculta a sidebar original */
            }
            .mobile-filter-trigger {
                display: block; /* Mostra o botão no mobile */
            }
        }
    </style>
</head>
<body>
    <body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="container catalogo-container">
        <?php if ($error_msg): ?>
            <div class="alert alert-danger w-100 text-center">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php else: ?>

        <aside class="sidebar">
            <form method="get" id="form-filtros-desktop">
                <?php if ($primary_filter_type): ?>
                    <input type="hidden" name="<?= $primary_filter_type ?>_principal" value="<?= htmlspecialchars($primary_filter_value) ?>">
                <?php endif; ?>
                
                <div class="filtro-widget">
                    <?php if (!empty($termo_busca)): ?>
                        <input type="hidden" name="busca" value="<?= htmlspecialchars($termo_busca) ?>">
                    <?php endif; ?>
                    <h4>Filtros</h4>
                    <?php if ($categorias_disponiveis && $categorias_disponiveis->num_rows > 0): ?><h5>Categorias</h5><?php endif; ?>
                    <?php while($cat = $categorias_disponiveis->fetch_assoc()): 
                        $cat_nome = $cat['categoria'];
                        $is_primary = ($primary_filter_type === 'categoria' && strtolower($primary_filter_value) === strtolower($cat_nome));
                        $is_checked = isset($_GET['categoria']) && in_array($cat_nome, (array)$_GET['categoria']);
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="categoria[]" value="<?= htmlspecialchars($cat_nome) ?>" id="cat-desktop-<?= htmlspecialchars(md5($cat_nome)) ?>" 
                            <?php if($is_checked || $is_primary) echo 'checked'; ?>
                            <?php if($is_primary) echo 'disabled'; ?>
                        >
                        <label class="form-check-label" for="cat-desktop-<?= htmlspecialchars(md5($cat_nome)) ?>"><?= htmlspecialchars($cat_nome) ?></label>
                    </div>
                    <?php endwhile; ?>
                    <?php if ($categorias_disponiveis && $categorias_disponiveis->num_rows > 0 && $campeonatos_disponiveis && $campeonatos_disponiveis->num_rows > 0): ?><hr><?php endif; ?>
                    <?php if ($campeonatos_disponiveis && $campeonatos_disponiveis->num_rows > 0): ?><h5>Campeonatos</h5><?php endif; ?>
                     <?php mysqli_data_seek($campeonatos_disponiveis, 0); while($camp = $campeonatos_disponiveis->fetch_assoc()): 
                        $camp_nome = $camp['campeonato'];
                        $is_primary = ($primary_filter_type === 'campeonato' && strtolower($primary_filter_value) === strtolower($camp_nome));
                        $is_checked = isset($_GET['campeonato']) && in_array($camp_nome, (array)$_GET['campeonato']);
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="campeonato[]" value="<?= htmlspecialchars($camp_nome) ?>" id="camp-mobile-<?= htmlspecialchars(md5($camp_nome)) ?>"
                           <?php if($is_checked || $is_primary) echo 'checked'; ?>
                           <?php if($is_primary) echo 'disabled'; ?>
                        >
                        <label class="form-check-label" for="camp-mobile-<?= htmlspecialchars(md5($camp_nome)) ?>"><?= htmlspecialchars($camp_nome) ?></label>
                    </div>
                    <?php endwhile; ?>
                </div>
                <button type="submit" class="btn btn-success w-100 mt-3">Aplicar Filtros</button>
            </form>
        </aside>

        <section class="main-content">
            <h1 style="font-weight: 900; margin-bottom: 30px;">
                <?php if (!empty($termo_busca)): ?>
                    Resultados para "<?= htmlspecialchars($termo_busca) ?>"
                <?php else: ?>
                    <?= $primary_filter_value ? htmlspecialchars(ucfirst($primary_filter_value)) : 'Catálogo Completo' ?>
                <?php endif; ?>
            </h1>
            <p>Mostrando <?= $resultado_catalogo ? $resultado_catalogo->num_rows : 0 ?> de <?= $total_produtos ?> produtos</p>
            
            <?php render_product_grid($resultado_catalogo); ?>

            <?php if ($total_paginas > 1): ?>
            <nav class="paginacao">
              <ul class="pagination justify-content-center">
                <li class="page-item <?= ($pagina_atual <= 1) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>">Anterior</a>
                </li>
                <?php 
                    $range = 2;
                    for ($i = 1; $i <= $total_paginas; $i++): 
                        if ($i == 1 || $i == $total_paginas || ($i >= $pagina_atual - $range && $i <= $pagina_atual + $range)):
                ?>
                    <li class="page-item <?= ($i == $pagina_atual) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php 
                        elseif ($i == $pagina_atual - ($range + 1) || $i == $pagina_atual + ($range + 1)):
                ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php 
                        endif;
                    endfor; 
                ?>
                <li class="page-item <?= ($pagina_atual >= $total_paginas) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>">Próximo</a>
                </li>
              </ul>
            </nav>
            <?php endif; ?>
        </section>
        <?php endif; // Fim do else do $error_msg ?>
    </main>

    <div class="filter-overlay"></div>
    <button class="mobile-filter-trigger">Filtros</button>

    <div class="filter-panel">
        <form method="get" id="form-filtros-mobile">
            <?php if ($primary_filter_type): ?>
                <input type="hidden" name="<?= $primary_filter_type ?>_principal" value="<?= htmlspecialchars($primary_filter_value) ?>">
            <?php endif; ?>
            
            <div class="filtro-widget">
                <?php if (!empty($termo_busca)): ?>
                    <input type="hidden" name="busca" value="<?= htmlspecialchars($termo_busca) ?>">
                <?php endif; ?>
                <h4>Filtros</h4>
                <?php if ($categorias_disponiveis && $categorias_disponiveis->num_rows > 0): ?><h5>Categorias</h5><?php endif; ?>
                <?php mysqli_data_seek($categorias_disponiveis, 0); while($cat = $categorias_disponiveis->fetch_assoc()): 
                    $cat_nome = $cat['categoria'];
                    $is_primary = ($primary_filter_type === 'categoria' && strtolower($primary_filter_value) === strtolower($cat_nome));
                    $is_checked = isset($_GET['categoria']) && in_array($cat_nome, (array)$_GET['categoria']);
                ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="categoria[]" value="<?= htmlspecialchars($cat_nome) ?>" id="cat-mobile-<?= htmlspecialchars(md5($cat_nome)) ?>" 
                        <?php if($is_checked || $is_primary) echo 'checked'; ?>
                        <?php if($is_primary) echo 'disabled'; ?>
                    >
                    <label class="form-check-label" for="cat-mobile-<?= htmlspecialchars(md5($cat_nome)) ?>"><?= htmlspecialchars($cat_nome) ?></label>
                </div>
                <?php endwhile; ?>
                <?php if ($categorias_disponiveis && $categorias_disponiveis->num_rows > 0 && $campeonatos_disponiveis && $campeonatos_disponiveis->num_rows > 0): ?><hr><?php endif; ?>
                <?php if ($campeonatos_disponiveis && $campeonatos_disponiveis->num_rows > 0): ?><h5>Campeonatos</h5><?php endif; ?>
                 <?php mysqli_data_seek($campeonatos_disponiveis, 0); while($camp = $campeonatos_disponiveis->fetch_assoc()): 
                    $camp_nome = $camp['campeonato'];
                    $is_primary = ($primary_filter_type === 'campeonato' && strtolower($primary_filter_value) === strtolower($camp_nome));
                    $is_checked = isset($_GET['campeonato']) && in_array($camp_nome, (array)$_GET['campeonato']);
                ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="campeonato[]" value="<?= htmlspecialchars($camp_nome) ?>" id="camp-mobile-<?= htmlspecialchars(md5($camp_nome)) ?>"
                       <?php if($is_checked || $is_primary) echo 'checked'; ?>
                       <?php if($is_primary) echo 'disabled'; ?>
                    >
                    <label class="form-check-label" for="camp-mobile-<?= htmlspecialchars(md5($camp_nome)) ?>"><?= htmlspecialchars($camp_nome) ?></label>
                </div>
                <?php endwhile; ?>
            </div>
            <button type="submit" class="btn btn-success w-100 mt-3">Aplicar Filtros</button>
        </form>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterTrigger = document.querySelector('.mobile-filter-trigger');
            const filterPanel = document.querySelector('.filter-panel');
            const filterOverlay = document.querySelector('.filter-overlay');

            function toggleFilterPanel() {
                filterPanel.classList.toggle('active');
                filterOverlay.style.display = filterPanel.classList.contains('active') ? 'block' : 'none';
                document.body.style.overflow = filterPanel.classList.contains('active') ? 'hidden' : '';
            }

            if (filterTrigger) {
                filterTrigger.addEventListener('click', toggleFilterPanel);
            }
            if (filterOverlay) {
                filterOverlay.addEventListener('click', toggleFilterPanel);
            }
        });
    </script>
