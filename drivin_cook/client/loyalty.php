<?php
require_once '../config/database.php';
require_once '../config/language.php';
require_once '../classes/Client.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php?redirect=loyalty.php');
    exit();
}

$client = new Client();
$currentLang = getCurrentLanguage();
$clientId = $_SESSION['client_id'];

$clientInfo = $client->getById($clientId);
$loyaltyHistory = $client->getLoyaltyHistory($clientId, 20);


$currentPoints = $clientInfo['loyalty_points'];
$availableDiscounts = floor($currentPoints / 100) * 5;
$pointsForNextDiscount = (100 - ($currentPoints % 100)) % 100;

$pageTitle = t('loyalty_title');
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
        .loyalty-card {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            border-radius: 15px;
            color: #333;
            position: relative;
            overflow: hidden;
        }
        .loyalty-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: rotate(45deg);
        }
        .points-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto;
        }
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring-circle {
            stroke: #ddd;
            fill: transparent;
            stroke-width: 8;
        }
        .progress-ring-progress {
            stroke: #28a745;
            fill: transparent;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.5s;
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
                            <li><a class="dropdown-item" href="orders.php">
                                <i class="fas fa-list me-2"></i><?php echo t('my_orders'); ?>
                            </a></li>
                            <li><a class="dropdown-item active" href="loyalty.php">
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
                <div class="card loyalty-card">
                    <div class="card-body text-center p-4">
                        <h4 class="mb-3">
                            <i class="fas fa-star me-2"></i><?php echo t('loyalty_card'); ?>
                        </h4>
                        
                        <div class="points-circle mb-3">
                            <?php echo $currentPoints; ?>
                        </div>
                        
                        <h6 class="mb-3">Points disponibles</h6>
                        
                        <?php if ($availableDiscounts > 0): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-gift me-2"></i>
                                <strong><?php echo number_format($availableDiscounts, 2); ?> € de réduction disponible !</strong>
                            </div>
                        <?php else: ?>
                            <p class="mb-3">
                                Plus que <strong><?php echo $pointsForNextDiscount; ?> points</strong> 
                                pour une réduction de 5€
                            </p>
                        <?php endif; ?>
                        

                        <div class="progress mb-3" style="height: 20px;">
                            <?php 
                            $progressPercent = (($currentPoints % 100) / 100) * 100;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $progressPercent; ?>%">
                                <?php echo $currentPoints % 100; ?>/100
                            </div>
                        </div>
                        
                        <small>Prochaine réduction dans <?php echo 100 - ($currentPoints % 100); ?> points</small>
                    </div>
                </div>


                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Comment ça marche ?</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-euro-sign text-success fa-lg me-3"></i>
                                <div>
                                    <strong><?php echo t('loyalty_rule'); ?></strong>
                                    <br><small class="text-muted">Gagnez des points à chaque commande</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-gift text-warning fa-lg me-3"></i>
                                <div>
                                    <strong><?php echo t('redemption_rule'); ?></strong>
                                    <br><small class="text-muted">Utilisez vos points lors du paiement</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-0">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-calendar text-info fa-lg me-3"></i>
                                <div>
                                    <strong>Invitations aux événements</strong>
                                    <br><small class="text-muted">Accès prioritaire aux dégustations</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="col-lg-8">
                <h1 class="mb-4">
                    <i class="fas fa-history me-2"></i><?php echo t('loyalty_history'); ?>
                </h1>

                <?php if (empty($loyaltyHistory)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-star fa-4x text-muted mb-4"></i>
                            <h4>Aucun historique</h4>
                            <p class="text-muted mb-4">Vous n'avez pas encore d'historique de points fidélité</p>
                            <a href="menu.php" class="btn btn-success">
                                <i class="fas fa-utensils me-2"></i>Passer ma première commande
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Points</th>
                                            <th><?php echo t('reason'); ?></th>
                                            <th>Commande</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loyaltyHistory as $history): ?>
                                            <tr>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($history['created_at'])); ?>
                                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($history['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($history['points_change'] > 0): ?>
                                                        <span class="text-success fw-bold">
                                                            <i class="fas fa-plus me-1"></i>+<?php echo $history['points_change']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-danger fw-bold">
                                                            <i class="fas fa-minus me-1"></i><?php echo $history['points_change']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($history['reason']); ?>
                                                    <?php if ($history['points_change'] > 0): ?>
                                                        <i class="fas fa-arrow-up text-success ms-2"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-arrow-down text-danger ms-2"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($history['order_number']): ?>
                                                        <a href="orders.php" class="text-decoration-none">
                                                            #<?php echo $history['order_number']; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <?php
                        $totalEarned = array_sum(array_filter(array_column($loyaltyHistory, 'points_change'), fn($p) => $p > 0));
                        $totalUsed = abs(array_sum(array_filter(array_column($loyaltyHistory, 'points_change'), fn($p) => $p < 0)));
                        ?>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-star fa-2x text-warning mb-3"></i>
                                    <h4><?php echo $totalEarned; ?></h4>
                                    <p class="text-muted mb-0"><?php echo t('points_earned'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-gift fa-2x text-danger mb-3"></i>
                                    <h4><?php echo $totalUsed; ?></h4>
                                    <p class="text-muted mb-0"><?php echo t('points_used'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-piggy-bank fa-2x text-success mb-3"></i>
                                    <h4><?php echo number_format($totalUsed * 0.05, 2); ?> €</h4>
                                    <p class="text-muted mb-0">Économisé</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        const progressBar = document.querySelector('.progress-bar');
        if (progressBar) {
            const width = progressBar.style.width;
            progressBar.style.width = '0%';
            setTimeout(() => {
                progressBar.style.transition = 'width 1.5s ease';
                progressBar.style.width = width;
            }, 500);
        }

        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        document.getElementById('cart-count').textContent = count;

        const loyaltyCard = document.querySelector('.loyalty-card');
        loyaltyCard.addEventListener('mouseover', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
            this.style.transition = 'all 0.3s ease';
        });
        
        loyaltyCard.addEventListener('mouseout', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    </script>
</body>
</html>