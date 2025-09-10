// Atualiza o contador do carrinho em todos os elementos com a classe .cart-count
function updateCartCount() {
    // Verifica se existe um carrinho na sessionStorage
    const cartCount = localStorage.getItem('cartCount') || 0;
    
    // Atualiza todos os contadores de carrinho na página
    document.querySelectorAll('.cart-count').forEach(element => {
        element.textContent = cartCount;
        element.style.display = cartCount > 0 ? 'flex' : 'none';
    });
    
    // Atualiza o botão flutuante
    const floatingCart = document.querySelector('.floating-cart .cart-count');
    if (floatingCart) {
        floatingCart.textContent = cartCount;
        floatingCart.style.display = cartCount > 0 ? 'flex' : 'none';
    }
}

// Atualiza o contador quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
    
    // Atualiza o contador quando o carrinho é modificado
    window.addEventListener('storage', function(event) {
        if (event.key === 'cartCount') {
            updateCartCount();
        }
    });
});
