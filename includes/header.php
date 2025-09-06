<header class="main-header">
    <div class="container">
        <div class="header-top">
            <a href="/mantastico/index.php" class="logo">⚽ Mantástico</a>
            <form action="/mantastico/catalogo.php" method="GET" class="search-form">
                <input type="text" name="busca" placeholder="Buscar produtos..." required>
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <nav class="main-nav">
                <a href="/mantastico/catalogo.php">Catálogo</a>
                <a href="/mantastico/pages/rastreio.php">Rastrear</a>
                <a href="/mantastico/pages/carrinho.php">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if (!empty($_SESSION['carrinho'])): ?>
                        <span class="cart-count"><?= count($_SESSION['carrinho']) ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
    </div>
    <style>
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            width: 100%;
        }
        .search-form {
            display: flex;
            align-items: center;
            flex: 0 1 400px;
            margin: 0 2rem;
            position: relative;
        }
        .search-form input {
            width: 100%;
            padding: 0.5rem 2.5rem 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .search-form button {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
        }
        .search-form button:hover {
            color: #2c5b2d;
        }
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 50%;
        }
        .main-nav a {
            position: relative;
            display: inline-flex;
            align-items: center;
            margin-left: 1.5rem;
            text-decoration: none;
            color: #333;
        }
        .main-nav a:hover {
            color: #2c5b2d;
        }
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 1rem;
            }
            .search-form {
                margin: 1rem 0;
                flex: 1 1 100%;
            }
            .main-nav {
                width: 100%;
                display: flex;
                justify-content: space-around;
            }
            .main-nav a {
                margin: 0;
            }
        }
    </style>
</header>