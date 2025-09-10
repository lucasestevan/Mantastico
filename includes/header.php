<div class="sticky-header">
<header class="main-header">
    <div class="container">
        <div class="header-top">
            <a href="/mantastico/index.php" class="logo">⚽ Mantástico</a>
            <form action="/mantastico/catalogo.php" method="GET" class="search-form">
                <input type="text" name="busca" placeholder="Buscar produtos..." required>
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <button class="mobile-menu-toggle" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-content">
                <nav class="main-nav">
                    <a href="/mantastico/catalogo.php">Catálogo</a>
                    <a href="/mantastico/pages/rastreio.php">Rastrear</a>
                    <a href="/mantastico/pages/carrinho.php" class="cart-link desktop-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <?php 
                        $cart_count = !empty($_SESSION['carrinho']) ? count($_SESSION['carrinho']) : 0;
                        if ($cart_count > 0): ?>
                            <span class="cart-count"><?= $cart_count ?></span>
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
            position: relative;
        }
        
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            /* width: 100%; */ /* Removido para corrigir o layout do desktop */
            justify-content: flex-end; /* Alinhado para a direita */
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
        .cart-link {
            position: relative;
            display: inline-block;
            color: #333;
            text-decoration: none;
            padding: 0.5rem 1rem;
        }
        .cart-count {
            position: absolute;
            top: -5px;
            right: 0;
            background-color: #ff6b6b;
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
        @media (max-width: 992px) {
            .header-top {
                flex-wrap: wrap;
                justify-content: flex-start; /* Alinha itens à esquerda */
                align-items: center;
                gap: 1rem; /* Espaço entre o menu e o logo */
            }
            .logo {
                order: 2;
                margin: 0; /* Remove margens extras */
            }
            .mobile-menu-toggle {
                display: block;
                order: 1;
            }
            
            .header-content {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                padding: 1rem;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 1000;
                flex-direction: column;
                gap: 1rem;
            }
            
            .header-content.active {
                display: flex;
            }
            
            .search-form {
                order: 3;
                margin: 0.5rem 0;
                width: 100%;
            }
            
            .main-nav {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .main-nav a {
                margin: 0;
                padding: 0.5rem 0;
                border-bottom: 1px solid #eee;
            }
            
            .desktop-cart {
                display: none !important;
            }
            .main-nav a {
                margin: 0;
            }
        }
        
        /* Botão flutuante para mobile */
        .floating-cart {
            display: none;
            position: fixed;
            bottom: 90px; /* Aumentado para não sobrepor o botão de filtro */
            right: 20px;
            background-color: var(--cor-principal);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 1000;
            text-decoration: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            font-size: 1.5rem;
            transition: transform 0.2s;
        }

        .floating-cart:hover {
            transform: scale(1.1);
        }
        
        .floating-cart .cart-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .floating-cart {
                display: flex;
            }
        }
    </style>
    
    <!-- Botão flutuante do carrinho para mobile -->
    <?php if (basename($_SERVER['PHP_SELF']) != 'carrinho.php'): ?>
    <a href="/mantastico/pages/carrinho.php" class="floating-cart">
        <i class="fas fa-shopping-cart"></i>
        <?php if ($cart_count > 0): ?>
            <span class="cart-count"><?= $cart_count ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>
    
    <script>
    // Toggle do menu mobile
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.querySelector('.mobile-menu-toggle');
        const headerContent = document.querySelector('.header-content');
        
        if (menuToggle && headerContent) {
            menuToggle.addEventListener('click', function() {
                headerContent.classList.toggle('active');
            });
            
            // Fechar menu ao clicar em um link
            const navLinks = headerContent.querySelectorAll('a');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    headerContent.classList.remove('active');
                });
            });
        }
    });
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Script do carrinho -->
    <script>
        // Inicializa o contador do carrinho se não existir
        if (!localStorage.getItem('cartCount')) {
            localStorage.setItem('cartCount', '<?= $cart_count ?>');
        }
        
        // Atualiza o contador do carrinho
        function updateCartCount() {
            const cartCount = localStorage.getItem('cartCount') || 0;
            document.querySelectorAll('.cart-count').forEach(element => {
                if (element) {
                    element.textContent = cartCount;
                    element.style.display = cartCount > 0 ? 'flex' : 'none';
                }
            });
        }
        
        // Atualiza o contador quando a página carrega
        document.addEventListener('DOMContentLoaded', updateCartCount);
    </script>
</header>
</div>