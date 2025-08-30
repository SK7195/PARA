<?php
require_once '../config/database.php';
require_once '../config/language.php';
require_once '../classes/Menu.php';
require_once '../classes/Client.php';

$currentLang = getCurrentLanguage();
$menu = new Menu();

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php?redirect=cart.php');
    exit();
}

$client = new Client();
$clientInfo = $client->getById($_SESSION['client_id']);
$currentPoints = $clientInfo['loyalty_points'];

$pageTitle = t('cart_title');
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Driv'n Cook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .cart-item {
            transition: all 0.3s ease;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .cart-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .quantity-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 2px solid #28a745;
            background: white;
            color: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quantity-btn:hover {
            background: #28a745;
            color: white;
        }
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        .loyalty-discount {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-utensils me-2"></i>Driv'n Cook
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i><?php echo t('home'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="menu.php">
                            <i class="fas fa-utensils me-1"></i><?php echo t('menu'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="fas fa-calendar me-1"></i><?php echo t('events'); ?>
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="cart.php">
                            <i class="fas fa-shopping-cart me-1"></i><?php echo t('cart'); ?>
                            <span class="badge bg-danger" id="cart-count">0</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo t('my_account'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="account.php">
                                <i class="fas fa-user me-2"></i><?php echo t('profile'); ?>
                            </a></li>
                            <li><a class="dropdown-item" href="orders.php">
                                <i class="fas fa-list me-2"></i><?php echo t('my_orders'); ?>
                            </a></li>
                            <li><a class="dropdown-item" href="loyalty.php">
                                <i class="fas fa-star me-2"></i><?php echo t('loyalty_card'); ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i><?php echo t('logout'); ?>
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <?php renderLanguageSelector(); ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-shopping-cart me-2"></i><?php echo $pageTitle; ?></h1>
                    <button id="clearCart" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-trash me-2"></i>Vider le panier
                    </button>
                </div>

                <div id="cartItems">
                </div>

                <div id="emptyCart" class="card" style="display: none;">
                    <div class="card-body empty-cart">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                        <h4><?php echo t('empty_cart'); ?></h4>
                        <p class="text-muted mb-4">Découvrez nos délicieux plats et ajoutez-les à votre panier</p>
                        <a href="menu.php" class="btn btn-success btn-lg">
                            <i class="fas fa-utensils me-2"></i><?php echo t('continue_shopping'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" id="orderSummary">
                    <div class="card-header">
                        <h5><i class="fas fa-receipt me-2"></i>Résumé de la commande</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sous-total :</span>
                            <strong id="subtotal">0.00 €</strong>
                        </div>
                        
                        <div class="loyalty-discount" id="loyaltyOptions" style="display: none;">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-star text-warning me-2"></i>
                                <strong>Utiliser mes points fidélité</strong>
                            </div>
                            <p class="small mb-2">Vous avez <strong><?php echo $currentPoints; ?> points</strong> disponibles</p>
                            
                            <div id="loyaltyDiscounts"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2" id="discountLine" style="display: none;">
                            <span class="text-success">Réduction fidélité :</span>
                            <strong class="text-success" id="discountAmount">-0.00 €</strong>
                        </div>
                        
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong><?php echo t('total'); ?> :</strong>
                            <strong class="h5 text-success" id="finalTotal">0.00 €</strong>
                        </div>
                        
                        <button id="checkoutBtn" class="btn btn-success btn-lg w-100" disabled>
                            <i class="fas fa-credit-card me-2"></i><?php echo t('checkout'); ?>
                        </button>
                        
                        <a href="menu.php" class="btn btn-outline-primary w-100 mt-2">
                            <i class="fas fa-plus me-2"></i><?php echo t('continue_shopping'); ?>
                        </a>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle me-2"></i>Informations</h6>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted">
                            <p><i class="fas fa-map-marker-alt text-danger me-2"></i>
                            <strong>Retrait en food truck</strong><br>
                            Choisissez votre lieu de retrait lors de la commande</p>
                            
                            <p><i class="fas fa-clock text-info me-2"></i>
                            <strong>Horaires de service</strong><br>
                            Lun-Ven : 11h30-14h30 et 18h00-21h30<br>
                            Sam-Dim : 12h00-22h00</p>
                            
                            <p><i class="fas fa-star text-warning me-2"></i>
                            <strong>Points fidélité</strong><br>
                            Gagnez 1 point par euro dépensé !</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    let selectedDiscountPoints = 0;
    let discountAmount = 0;
    const currentPoints = <?php echo $currentPoints; ?>;

    function updateCartDisplay() {
        const cartItemsContainer = document.getElementById('cartItems');
        const emptyCart = document.getElementById('emptyCart');
        const orderSummary = document.getElementById('orderSummary');
        
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = '';
            emptyCart.style.display = 'block';
            orderSummary.style.display = 'none';
            return;
        }
        
        emptyCart.style.display = 'none';
        orderSummary.style.display = 'block';
        
        let html = '';
        let subtotal = 0;
        
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            
            html += `
                <div class="card cart-item">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-1">${item.name}</h6>
                                <p class="text-muted small mb-0">${item.price.toFixed(2)} € l'unité</p>
                            </div>
                            <div class="col-md-4">
                                <div class="quantity-controls">
                                    <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.quantity - 1})">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="quantity-input" value="${item.quantity}" 
                                           onchange="updateQuantity(${index}, this.value)" min="1">
                                    <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.quantity + 1})">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <div class="fw-bold">${itemTotal.toFixed(2)} €</div>
                                <button class="btn btn-sm btn-outline-danger mt-1" onclick="removeItem(${index})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        cartItemsContainer.innerHTML = html;
        updateOrderSummary(subtotal);
        updateCartCount();
    }
    
    function updateOrderSummary(subtotal) {
        document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' €';
        
        const loyaltyOptions = document.getElementById('loyaltyOptions');
        const loyaltyDiscounts = document.getElementById('loyaltyDiscounts');
        
        if (currentPoints >= 100) {
            loyaltyOptions.style.display = 'block';
            
            let discountHtml = '';
            const maxDiscounts = Math.min(Math.floor(currentPoints / 100), Math.floor(subtotal / 5));
            
            for (let i = 1; i <= maxDiscounts; i++) {
                const points = i * 100;
                const discount = i * 5;
                const checked = selectedDiscountPoints === points ? 'checked' : '';
                
                discountHtml += `
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="loyaltyDiscount" 
                               id="discount${i}" value="${points}" ${checked}
                               onchange="applyLoyaltyDiscount(${points}, ${discount})">
                        <label class="form-check-label small" for="discount${i}">
                            Utiliser ${points} points pour ${discount}€ de réduction
                        </label>
                    </div>
                `;
            }
            
            const noDiscountChecked = selectedDiscountPoints === 0 ? 'checked' : '';
            discountHtml = `
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="loyaltyDiscount" 
                           id="noDiscount" value="0" ${noDiscountChecked}
                           onchange="applyLoyaltyDiscount(0, 0)">
                    <label class="form-check-label small" for="noDiscount">
                        Ne pas utiliser de points
                    </label>
                </div>
            ` + discountHtml;
            
            loyaltyDiscounts.innerHTML = discountHtml;
        } else {
            loyaltyOptions.style.display = 'none';
        }
        
        const finalTotal = subtotal - discountAmount;
        
        if (discountAmount > 0) {
            document.getElementById('discountLine').style.display = 'flex';
            document.getElementById('discountAmount').textContent = '-' + discountAmount.toFixed(2) + ' €';
        } else {
            document.getElementById('discountLine').style.display = 'none';
        }
        
        document.getElementById('finalTotal').textContent = finalTotal.toFixed(2) + ' €';
        
        const checkoutBtn = document.getElementById('checkoutBtn');
        checkoutBtn.disabled = subtotal === 0;
    }
    
    function applyLoyaltyDiscount(points, discount) {
        selectedDiscountPoints = points;
        discountAmount = discount;
        
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        updateOrderSummary(subtotal);
    }
    
    function updateQuantity(index, newQuantity) {
        newQuantity = parseInt(newQuantity);
        
        if (newQuantity < 1) {
            removeItem(index);
            return;
        }
        
        cart[index].quantity = newQuantity;
        saveCart();
        updateCartDisplay();
    }
    
    function removeItem(index) {
        if (confirm('Supprimer cet article du panier ?')) {
            cart.splice(index, 1);
            saveCart();
            updateCartDisplay();
        }
    }
    
    function clearCart() {
        if (confirm('Vider complètement le panier ?')) {
            cart = [];
            selectedDiscountPoints = 0;
            discountAmount = 0;
            saveCart();
            updateCartDisplay();
        }
    }
    
    function updateCartCount() {
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        document.getElementById('cart-count').textContent = count;
    }
    
    function saveCart() {
        localStorage.setItem('cart', JSON.stringify(cart));
    }
    
    function proceedToCheckout() {
        if (cart.length === 0) {
            alert('Votre panier est vide');
            return;
        }
        
        const checkoutData = {
            cart: cart,
            loyaltyPointsUsed: selectedDiscountPoints,
            discountAmount: discountAmount,
            subtotal: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0),
            finalTotal: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) - discountAmount
        };
        
        localStorage.setItem('checkoutData', JSON.stringify(checkoutData));
        window.location.href = 'checkout.php';
    }
    
    document.getElementById('clearCart').addEventListener('click', clearCart);
    document.getElementById('checkoutBtn').addEventListener('click', proceedToCheckout);
    
    document.addEventListener('DOMContentLoaded', function() {
        updateCartDisplay();
        
        setTimeout(() => {
            const cards = document.querySelectorAll('.cart-item');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }, 100);
    });
    </script>
</body>
</html>