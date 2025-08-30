<?php
require_once '../config/database.php';
require_once '../config/language.php';
require_once '../classes/Client.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php?redirect=orders.php');
    exit();
}

$client = new Client();
$currentLang = getCurrentLanguage();
$clientId = $_SESSION['client_id'];


$orders = $client->getOrders($clientId);
$clientStats = $client->getStats($clientId);

$pageTitle = t('orders_title');
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
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart me-1"></i><?php echo t('cart'); ?>
                            <span class="badge bg-danger" id="cart-count">0</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo t('my_account'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="account.php">
                                <i class="fas fa-user me-2"></i><?php echo t('profile'); ?>
                            </a></li>
                            <li><a class="dropdown-item active" href="orders.php">
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

            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Mes statistiques</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="stat-card stat-card-info p-3">
                                    <h4><?php echo $clientStats['total_orders']; ?></h4>
                                    <p class="mb-0">Commandes</p>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stat-card stat-card-success p-3">
                                    <h4><?php echo number_format($clientStats['total_spent'], 2); ?> €</h4>
                                    <p class="mb-0">Dépensé</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card stat-card-warning p-3">
                                    <h4><?php echo number_format($clientStats['avg_order'], 2); ?> €</h4>
                                    <p class="mb-0">Panier moyen</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card p-3">
                                    <h4><?php echo $clientStats['loyalty_points']; ?></h4>
                                    <p class="mb-0">Points fidélité</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($clientStats['last_order_date']): ?>
                            <div class="mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-2"></i>
                                    Dernière commande : <?php echo date('d/m/Y', strtotime($clientStats['last_order_date'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body text-center">
                        <h6><i class="fas fa-utensils me-2"></i>Envie de commander ?</h6>
                        <a href="menu.php" class="btn btn-success">
                            <i class="fas fa-utensils me-2"></i>Découvrir notre menu
                        </a>
                    </div>
                </div>
            </div>


            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-list me-2"></i><?php echo $pageTitle; ?></h1>
                    <span class="text-muted"><?php echo count($orders); ?> commande<?php echo count($orders) > 1 ? 's' : ''; ?></span>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-shopping-basket fa-4x text-muted mb-4"></i>
                            <h4>Aucune commande</h4>
                            <p class="text-muted mb-4">Vous n'avez pas encore passé de commande chez Driv'n Cook</p>
                            <a href="menu.php" class="btn btn-success btn-lg">
                                <i class="fas fa-utensils me-2"></i>Découvrir notre menu
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <strong>Commande #<?php echo $order['id']; ?></strong>
                                        <br><small class="text-muted"><?php echo date('d/m/Y à H:i', strtotime($order['order_date'])); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge status-<?php echo $order['status']; ?> fs-6">
                                            <?php 
                                            $statusLabels = [
                                                'pending' => t('status_pending'),
                                                'confirmed' => t('status_confirmed'),
                                                'ready' => t('status_ready'),
                                                'completed' => t('status_completed'),
                                                'cancelled' => t('status_cancelled')
                                            ];
                                            echo $statusLabels[$order['status']] ?? $order['status'];
                                            ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong><?php echo number_format($order['total_amount'], 2); ?> €</strong>
                                        <?php if ($order['loyalty_points_used'] > 0): ?>
                                            <br><small class="text-warning">
                                                <i class="fas fa-star me-1"></i>
                                                -<?php echo $order['loyalty_points_used']; ?> points utilisés
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#order-<?php echo $order['id']; ?>">
                                            <i class="fas fa-eye me-1"></i><?php echo t('view_details'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="collapse" id="order-<?php echo $order['id']; ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-info-circle me-2"></i>Détails de la commande</h6>
                                            <?php if ($order['pickup_location']): ?>
                                                <p><strong>Lieu de retrait :</strong> <?php echo htmlspecialchars($order['pickup_location']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($order['pickup_time']): ?>
                                                <p><strong>Heure de retrait :</strong> <?php echo date('d/m/Y à H:i', strtotime($order['pickup_time'])); ?></p>
                                            <?php endif; ?>
                                            <?php if ($order['truck_plate']): ?>
                                                <p><strong>Food Truck :</strong> <?php echo htmlspecialchars($order['truck_plate']); ?></p>
                                            <?php endif; ?>
                                            <p><strong>Paiement :</strong> 
                                                <?php 
                                                $paymentMethods = [
                                                    'card' => 'Carte bancaire',
                                                    'cash' => 'Espèces',
                                                    'loyalty_points' => 'Points fidélité'
                                                ];
                                                echo $paymentMethods[$order['payment_method']] ?? $order['payment_method'];
                                                ?>
                                            </p>
                                        </div>
                                        
                                        <div class="col-md-6">

                                            <h6><i class="fas fa-utensils me-2"></i>Articles commandés</h6>
                                            <div class="alert alert-info">
                                                <small><i class="fas fa-info-circle me-2"></i>
                                                Montant total : <strong><?php echo number_format($order['total_amount'], 2); ?> €</strong></small>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="mt-3 pt-3 border-top">
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-clock me-2"></i>Votre commande est en cours de préparation
                                            </div>
                                        <?php elseif ($order['status'] === 'confirmed'): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-check me-2"></i>Commande confirmée - Préparation en cours
                                            </div>
                                        <?php elseif ($order['status'] === 'ready'): ?>
                                            <div class="alert alert-success">
                                                <i class="fas fa-bell me-2"></i>Votre commande est prête ! Vous pouvez venir la récupérer
                                            </div>
                                        <?php elseif ($order['status'] === 'completed'): ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle me-2"></i>Commande terminée
                                                </span>
                                                <button class="btn btn-sm btn-primary">
                                                    <i class="fas fa-redo me-1"></i>Commander à nouveau
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>


                    <?php if (count($orders) > 10): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
  
        const cards = document.querySelectorAll('.card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.opacity = '1';
                }
            });
        });
        
        cards.forEach(card => {
            card.style.transform = 'translateY(20px)';
            card.style.opacity = '0';
            card.style.transition = 'all 0.5s ease';
            observer.observe(card);
        });

        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        document.getElementById('cart-count').textContent = count;
    });
    </script>
</body>
</html>