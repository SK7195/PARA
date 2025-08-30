<?php
require_once '../config/database.php';
require_once '../config/language.php';
require_once '../classes/Client.php';
require_once '../classes/Menu.php';

$currentLang = getCurrentLanguage();

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit();
}

$client = new Client();
$menu = new Menu();
$pdo = getDBConnection();
$clientId = $_SESSION['client_id'];
$clientInfo = $client->getById($clientId);

$error = $success = '';

if ($_POST && isset($_POST['place_order'])) {
    $pickupLocation = $_POST['pickup_location'] ?? '';
    $pickupTime = $_POST['pickup_time'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? 'card';
    $loyaltyPointsUsed = (int)($_POST['loyalty_points_used'] ?? 0);
    $totalAmount = (float)($_POST['total_amount'] ?? 0);
    $cartData = $_POST['cart_data'] ?? '';
    
    if (!$pickupLocation || !$pickupTime) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } elseif (empty($cartData)) {
        $error = "Votre panier est vide";
    } else {
        try {
            $cartItems = json_decode($cartData, true);
            if (!$cartItems || empty($cartItems)) {
                throw new Exception("Données du panier invalides");
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO client_orders (client_id, total_amount, pickup_time, pickup_location, 
                                         payment_method, loyalty_points_used, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $clientId, 
                $totalAmount, 
                $pickupTime, 
                $pickupLocation, 
                $paymentMethod, 
                $loyaltyPointsUsed
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("
                INSERT INTO client_order_items (order_id, menu_id, quantity, unit_price) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($cartItems as $item) {
                $stmt->execute([
                    $orderId, 
                    $item['id'], 
                    $item['quantity'], 
                    $item['price']
                ]);
            }
            
            if ($loyaltyPointsUsed > 0) {
                $stmt = $pdo->prepare("
                    UPDATE clients SET loyalty_points = loyalty_points - ? WHERE id = ?
                ");
                $stmt->execute([$loyaltyPointsUsed, $clientId]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO loyalty_history (client_id, points_change, reason, order_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $clientId, 
                    -$loyaltyPointsUsed, 
                    'Points utilisés pour commande #' . $orderId, 
                    $orderId
                ]);
            }
            
            $pointsEarned = floor($totalAmount);
            if ($pointsEarned > 0) {
                $stmt = $pdo->prepare("
                    UPDATE clients SET loyalty_points = loyalty_points + ? WHERE id = ?
                ");
                $stmt->execute([$pointsEarned, $clientId]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO loyalty_history (client_id, points_change, reason, order_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $clientId, 
                    $pointsEarned, 
                    'Points gagnés pour commande #' . $orderId, 
                    $orderId
                ]);
            }
            
            $pdo->commit();
            
            header("Location: checkout.php?success=1&order_id=" . $orderId);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Erreur lors du traitement de la commande : " . $e->getMessage();
        }
    }
}

$isSuccess = isset($_GET['success']) && $_GET['success'] == '1';
$orderId = $_GET['order_id'] ?? null;

$trucks = $pdo->query("
    SELECT t.*, f.name as franchisee_name 
    FROM trucks t 
    LEFT JOIN franchisees f ON t.franchisee_id = f.id 
    WHERE t.status = 'assigned'
    ORDER BY t.license_plate
")->fetchAll();

$pageTitle = $isSuccess ? t('order_success') : t('checkout_title');
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
        .checkout-step {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .order-summary-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .order-summary-item:last-child {
            border-bottom: none;
        }
        .success-animation {
            animation: bounceIn 1s ease-out;
        }
        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.3); }
            50% { opacity: 1; transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        .payment-option {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        .payment-option:hover {
            border-color: #28a745;
        }
        .payment-option.selected {
            border-color: #28a745;
            background-color: #f8fff9;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-utensils me-2"></i>Driv'n Cook
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?php echo $isSuccess ? 'orders.php' : 'cart.php'; ?>">
                    <i class="fas fa-arrow-left me-1"></i><?php echo $isSuccess ? 'Mes commandes' : 'Retour au panier'; ?>
                </a>
                <?php renderLanguageSelector(); ?>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <?php if ($isSuccess): ?>
            <div class="text-center success-animation">
                <div class="card">
                    <div class="card-body py-5">
                        <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                        <h1 class="text-success mb-3"><?php echo t('order_success'); ?></h1>
                        <p class="lead mb-4">Votre commande a été enregistrée avec succès</p>
                        
                        <?php if ($orderId): ?>
                            <div class="alert alert-info d-inline-block">
                                <strong><?php echo t('order_number'); ?> : #<?php echo $orderId; ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <h5>Que se passe-t-il maintenant ?</h5>
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                    <p><strong>Confirmation</strong><br>
                                    <small class="text-muted">Votre commande est en cours de traitement</small></p>
                                </div>
                                <div class="col-md-4">
                                    <i class="fas fa-utensils fa-2x text-warning mb-2"></i>
                                    <p><strong>Préparation</strong><br>
                                    <small class="text-muted">Nos chefs préparent vos plats</small></p>
                                </div>
                                <div class="col-md-4">
                                    <i class="fas fa-bell fa-2x text-success mb-2"></i>
                                    <p><strong>Prêt</strong><br>
                                    <small class="text-muted">Récupération au food truck</small></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="orders.php" class="btn btn-success btn-lg me-2">
                                <i class="fas fa-list me-2"></i>Voir mes commandes
                            </a>
                            <a href="menu.php" class="btn btn-outline-success btn-lg">
                                <i class="fas fa-utensils me-2"></i>Nouvelle commande
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <div class="checkout-step">
                <h1><i class="fas fa-credit-card me-2"></i><?php echo t('checkout_title'); ?></h1>
                <p class="mb-0">Finalisez votre commande en quelques clics</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="checkoutForm">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-map-marker-alt me-2"></i><?php echo t('pickup_location'); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($trucks as $truck): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="pickup_location" 
                                                       id="truck<?php echo $truck['id']; ?>" 
                                                       value="<?php echo htmlspecialchars($truck['location'] . ' - ' . $truck['license_plate']); ?>" 
                                                       required>
                                                <label class="form-check-label" for="truck<?php echo $truck['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($truck['location'] ?: 'Food Truck'); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($truck['license_plate']); ?>
                                                        <?php if ($truck['franchisee_name']): ?>
                                                            - <?php echo htmlspecialchars($truck['franchisee_name']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-clock me-2"></i><?php echo t('pickup_time'); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="pickup_date" class="form-label">Date de retrait</label>
                                        <input type="date" class="form-control" id="pickup_date" name="pickup_date" 
                                               min="<?php echo date('Y-m-d'); ?>" 
                                               max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pickup_time_select" class="form-label">Heure de retrait</label>
                                        <select class="form-select" id="pickup_time_select" name="pickup_time_select" required>
                                            <option value="">Choisir une heure</option>
                                            <optgroup label="Déjeuner">
                                                <option value="11:30">11h30</option>
                                                <option value="12:00">12h00</option>
                                                <option value="12:30">12h30</option>
                                                <option value="13:00">13h00</option>
                                                <option value="13:30">13h30</option>
                                                <option value="14:00">14h00</option>
                                                <option value="14:30">14h30</option>
                                            </optgroup>
                                            <optgroup label="Dîner">
                                                <option value="18:00">18h00</option>
                                                <option value="18:30">18h30</option>
                                                <option value="19:00">19h00</option>
                                                <option value="19:30">19h30</option>
                                                <option value="20:00">20h00</option>
                                                <option value="20:30">20h30</option>
                                                <option value="21:00">21h00</option>
                                                <option value="21:30">21h30</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" id="pickup_time" name="pickup_time">
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-credit-card me-2"></i><?php echo t('payment_method'); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="payment-option" data-payment="card">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="payment_card" value="card" checked>
                                        <label class="form-check-label" for="payment_card">
                                            <i class="fas fa-credit-card fa-lg text-primary me-3"></i>
                                            <strong><?php echo t('payment_card'); ?></strong>
                                            <br><small class="text-muted">Paiement sécurisé par carte bancaire</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="payment-option" data-payment="cash">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="payment_cash" value="cash">
                                        <label class="form-check-label" for="payment_cash">
                                            <i class="fas fa-money-bill-wave fa-lg text-success me-3"></i>
                                            <strong><?php echo t('payment_cash'); ?></strong>
                                            <br><small class="text-muted">Paiement en espèces au retrait</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-receipt me-2"></i>Résumé de la commande</h5>
                            </div>
                            <div class="card-body" id="orderSummaryCheckout">
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="cart_data" name="cart_data">
                <input type="hidden" id="loyalty_points_used" name="loyalty_points_used">
                <input type="hidden" id="total_amount" name="total_amount">
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    <?php if (!$isSuccess): ?>
    document.addEventListener('DOMContentLoaded', function() {
  
        const checkoutData = JSON.parse(localStorage.getItem('checkoutData') || '{}');
        
        if (!checkoutData.cart || checkoutData.cart.length === 0) {
            window.location.href = 'cart.php';
            return;
        }

        document.getElementById('cart_data').value = JSON.stringify(checkoutData.cart);
        document.getElementById('loyalty_points_used').value = checkoutData.loyaltyPointsUsed || 0;
        document.getElementById('total_amount').value = checkoutData.finalTotal || 0;

        displayOrderSummary(checkoutData);

        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                document.querySelectorAll('.payment-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
            });
        });
        
        document.getElementById('pickup_date').addEventListener('change', updatePickupTime);
        document.getElementById('pickup_time_select').addEventListener('change', updatePickupTime);
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            const submitBtn = document.createElement('button');
            submitBtn.type = 'submit';
            submitBtn.name = 'place_order';
            submitBtn.className = 'btn btn-success btn-lg w-100 mt-3';
            submitBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>' + <?php echo json_encode(t('place_order')); ?>;
            
            const orderSummary = document.getElementById('orderSummaryCheckout');
            if (!orderSummary.querySelector('button[type="submit"]')) {
                orderSummary.appendChild(submitBtn);
            }
        });
        
        document.querySelector('.payment-option[data-payment="card"]').classList.add('selected');
    });
    
    function displayOrderSummary(checkoutData) {
        const container = document.getElementById('orderSummaryCheckout');
        let html = '';
        
        checkoutData.cart.forEach(item => {
            html += `
                <div class="order-summary-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${item.name}</strong>
                            <br><small class="text-muted">Qté: ${item.quantity}</small>
                        </div>
                        <span>${(item.price * item.quantity).toFixed(2)} €</span>
                    </div>
                </div>
            `;
        });
        

        html += `
            <div class="order-summary-item">
                <div class="d-flex justify-content-between">
                    <span>Sous-total:</span>
                    <strong>${checkoutData.subtotal.toFixed(2)} €</strong>
                </div>
            </div>
        `;
        
        if (checkoutData.discountAmount > 0) {
            html += `
                <div class="order-summary-item">
                    <div class="d-flex justify-content-between text-success">
                        <span>Réduction fidélité:</span>
                        <strong>-${checkoutData.discountAmount.toFixed(2)} €</strong>
                    </div>
                    <small class="text-muted">-${checkoutData.loyaltyPointsUsed} points utilisés</small>
                </div>
            `;
        }
        
        html += `
            <div class="order-summary-item border-top pt-3">
                <div class="d-flex justify-content-between">
                    <strong>Total:</strong>
                    <strong class="h5 text-success">${checkoutData.finalTotal.toFixed(2)} €</strong>
                </div>
            </div>
        `;
        
        const pointsToEarn = Math.floor(checkoutData.finalTotal);
        if (pointsToEarn > 0) {
            html += `
                <div class="alert alert-info mt-3">
                    <small><i class="fas fa-star text-warning me-2"></i>
                    Vous gagnerez <strong>${pointsToEarn} points</strong> avec cette commande !</small>
                </div>
            `;
        }
        
        html += `
            <button type="submit" name="place_order" class="btn btn-success btn-lg w-100 mt-3">
                <i class="fas fa-credit-card me-2"></i><?php echo t('place_order'); ?>
            </button>
        `;
        
        container.innerHTML = html;
    }
    
    function updatePickupTime() {
        const date = document.getElementById('pickup_date').value;
        const time = document.getElementById('pickup_time_select').value;
        
        if (date && time) {
            document.getElementById('pickup_time').value = date + ' ' + time + ':00';
        }
    }
    
    function validateForm() {
        const pickupLocation = document.querySelector('input[name="pickup_location"]:checked');
        const pickupTime = document.getElementById('pickup_time').value;
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
        
        if (!pickupLocation) {
            alert('Veuillez sélectionner un lieu de retrait');
            return false;
        }
        
        if (!pickupTime) {
            alert('Veuillez sélectionner une date et heure de retrait');
            return false;
        }
        
        if (!paymentMethod) {
            alert('Veuillez sélectionner une méthode de paiement');
            return false;
        }
        
        return true;
    }
    <?php endif; ?>
    
    <?php if ($isSuccess): ?>
    localStorage.removeItem('cart');
    localStorage.removeItem('checkoutData');
    
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            const successIcon = document.querySelector('.fa-check-circle');
            if (successIcon) {
                successIcon.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    successIcon.style.transform = 'scale(1)';
                }, 200);
            }
        }, 500);
    });
    <?php endif; ?>
    </script>
</body>
</html>