<?php 
session_start();
require_once 'config/database.php'; 
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mantástico - Camisas de Futebol</title>
  <!-- Font Awesome para ícones -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
  <style>
    :root { --cor-principal: #1a6b2f; --cor-fundo: #f8f9fa; --cor-texto: #212529; }
    body { margin: 0; font-family: 'Roboto', sans-serif; background-color: var(--cor-fundo); color: var(--cor-texto); }
    .container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
    .main-header { background-color: #fff; border-bottom: 1px solid #e9ecef; padding: 15px 0; position: sticky; top: 0; z-index: 1000; }
    .main-header .container { display: flex; justify-content: space-between; align-items: center; }
    .logo { font-size: 2em; font-weight: 900; color: var(--cor-principal); text-decoration: none; }
    .main-nav a { color: var(--cor-texto); text-decoration: none; margin: 0 15px; font-weight: 700; }
    .hero { background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1579952363873-27f3bade9f55?auto=format&fit=crop&w=1350&q=80'); background-size: cover; background-position: center; padding: 100px 0; text-align: center; color: white; }
    .hero h1 { font-size: 3.5em; font-weight: 900; }
    .section-title { text-align: left; font-size: 2em; font-weight: 900; margin-top: 50px; margin-bottom: 20px; border-bottom: 3px solid var(--cor-principal); padding-bottom: 10px; display: inline-block; }
    .vitrine-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; }
    .produto-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; display: flex; flex-direction: column; }
    .produto-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    .produto-imagem-container { position: relative; width: 100%; background-color: #f1f1f1; }
    .produto-imagem-container img { width: 100%; height: auto; display: block; aspect-ratio: 1 / 1; object-fit: cover; }
    .btn-ver-mais-card { position: absolute; top: 10px; right: 10px; z-index: 2; background-color: rgba(26, 107, 47, 0.9); color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 700; text-decoration: none; transition: all 0.2s ease; border: 1px solid rgba(255,255,255,0.5); }
    .btn-ver-mais-card:hover { background-color: var(--cor-principal); transform: scale(1.05); }
    .produto-info { padding: 15px; display: flex; flex-direction: column; flex-grow: 1; text-align: center; }
    .produto-info h3 { margin: 0 0 10px; font-size: 1rem; font-weight: 700; flex-grow: 1; min-height: 40px;}
    .produto-info .preco { margin: 10px 0; font-weight: 900; font-size: 1.4em; }
    .btn-ver-produto { display: block; width: 100%; text-align: center; background-color: #343a40; color: #fff; padding: 10px; text-decoration: none; border-radius: 5px; font-weight: 700; margin-top: auto; transition: background-color 0.2s; }
    .main-footer { background-color: #212529; color: #fff; text-align: center; padding: 40px 20px; margin-top: 60px; }
    @media (max-width: 992px) { .vitrine-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .vitrine-grid { grid-template-columns: repeat(2, 1fr); } }
  </style>
</head>
<body>
<?php
try {
    $conn = Database::getConnection();

    // --- FUNÇÃO REUTILIZÁVEL PARA RENDERIZAR AS VITRINES ---
    // Esta função evita a repetição de código HTML e PHP para cada seção.
    function render_product_showcase($conn, $title, $query, $params = [], $see_more_link = null) {
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            echo '<section>';
            echo '<h2 class="section-title">' . htmlspecialchars($title) . '</h2>';
            echo '<div class="vitrine-grid">';

            $products = $result->fetch_all(MYSQLI_ASSOC);
            $total_products = count($products);

            foreach ($products as $index => $produto) {
                echo '<div class="produto-card">';
                echo '<div class="produto-imagem-container">';

                // Lógica aprimorada para o botão "Ver Mais": aparece no último item se a vitrine estiver cheia (5 produtos).
                if ($see_more_link && $total_products === 5 && $index === ($total_products - 1)) {
                    echo '<a href="' . htmlspecialchars($see_more_link) . '" class="btn-ver-mais-card">Ver Mais</a>';
                }

                $imagens = explode(',', $produto['imagem']);
                $foto_principal = trim($imagens[0]);
                
                echo '<a href="pages/produto.php?id=' . $produto['id'] . '">';
                echo '<img src="assets/images/' . htmlspecialchars($foto_principal) . '" alt="Camisa ' . htmlspecialchars($produto['nome']) . '">';
                echo '</a></div>'; // Fim .produto-imagem-container
                
                echo '<div class="produto-info">';
                echo '<h3>' . htmlspecialchars($produto['nome']) . '</h3>';
                echo '<p class="preco">R$ ' . number_format($produto['preco'], 2, ',', '.') . '</p>';
                echo '<a href="pages/produto.php?id=' . $produto['id'] . '" class="btn-ver-produto">Ver Detalhes</a>';
                echo '</div></div>'; // Fim .produto-info e .produto-card
            }
            echo '</div></section>'; // Fim .vitrine-grid e section
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    die("<div class='container text-center my-5'><h1>Erro ao carregar a página. Tente novamente mais tarde.</h1></div>");
}
?>
  <?php include 'includes/header.php'; ?>

<main>
    <section class="hero">
        <h1>O Manto do Seu Time está Aqui</h1>
    </section>

    <div class="container">
        <section id="lancamentos">
            <?php
                $query_lancamentos = "SELECT * FROM produtos ORDER BY id DESC LIMIT 5";
                render_product_showcase($conn, 'Lançamentos', $query_lancamentos);
            ?>
        </section>

        <section id="brasileirao">
            <?php
                $query_brasileirao = "SELECT * FROM produtos WHERE LOWER(campeonato) = ? ORDER BY id DESC LIMIT 5";
                // O link agora usa 'campeonato_principal' para melhor integração com o catálogo
                render_product_showcase($conn, 'Brasileirão', $query_brasileirao, ['brasileirão'], 'catalogo.php?campeonato_principal=Brasileirão');
            ?>
        </section>
        
        <section id="retro">
            <?php
                $query_retro = "SELECT * FROM produtos WHERE LOWER(categoria) = ? ORDER BY id DESC LIMIT 5";
                // O link agora usa 'categoria_principal' para melhor integração com o catálogo
                render_product_showcase($conn, 'Retrô', $query_retro, ['retro'], 'catalogo.php?categoria_principal=Retro');
            ?>
        </section>
    </div>
</main>

  <?php include 'includes/footer.php'; ?>

<?php $conn->close(); ?>
</body>
</html>