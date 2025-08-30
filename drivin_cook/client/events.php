<?php
require_once '../config/database.php';
require_once '../config/language.php';

$currentLang = getCurrentLanguage();
$pdo = getDBConnection();
$error = $success = '';

if ($_POST && isset($_POST['register_event'])) {
    if (!isset($_SESSION['client_id'])) {
        header('Location: login.php?redirect=events.php');
        exit();
    }
    
    $eventId = (int)$_POST['event_id'];
    $clientId = $_SESSION['client_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'upcoming'");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if (!$event) {
            $error = "Événement non trouvé";
        } elseif ($event['current_participants'] >= $event['max_participants']) {
            $error = "Événement complet";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM event_participants WHERE event_id = ? AND client_id = ?");
            $stmt->execute([$eventId, $clientId]);
            
            if ($stmt->fetch()) {
                $error = "Vous êtes déjà inscrit à cet événement";
            } else {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO event_participants (event_id, client_id) VALUES (?, ?)");
                $stmt->execute([$eventId, $clientId]);
                
                $stmt = $pdo->prepare("UPDATE events SET current_participants = current_participants + 1 WHERE id = ?");
                $stmt->execute([$eventId]);
                
                $pdo->commit();
                $success = t('event_registered');
            }
        }
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Erreur lors de l'inscription : " . $e->getMessage();
    }
}

$events = $pdo->query("
    SELECT e.*, 
           (e.max_participants - e.current_participants) as places_remaining
    FROM events e 
    WHERE e.event_date >= CURDATE() 
    AND e.status = 'upcoming'
    ORDER BY e.event_date ASC
")->fetchAll();

$userEvents = [];
if (isset($_SESSION['client_id'])) {
    $stmt = $pdo->prepare("
        SELECT e.id 
        FROM events e 
        JOIN event_participants ep ON e.id = ep.event_id 
        WHERE ep.client_id = ?
    ");
    $stmt->execute([$_SESSION['client_id']]);
    $userEvents = array_column($stmt->fetchAll(), 'id');
}

$pageTitle = t('events_title');
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
        .event-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .event-date {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .event-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .event-full {
            opacity: 0.7;
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
                        <a class="nav-link active" href="events.php">
                            <i class="fas fa-calendar me-1"></i><?php echo t('events'); ?>
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['client_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">
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
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i><?php echo t('login'); ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">
                                <i class="fas fa-user-plus me-1"></i><?php echo t('register'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <?php renderLanguageSelector(); ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="py-5" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
        <div class="container text-white text-center">
            <h1 class="display-4 fw-bold mb-3"><?php echo t('events_title'); ?></h1>
            <p class="lead">Rejoignez-nous pour des expériences culinaires uniques et conviviales</p>
        </div>
    </section>

    <div class="container my-5">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4"><?php echo t('upcoming_events'); ?></h2>

                <?php if (empty($events)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                            <h4><?php echo t('no_events'); ?></h4>
                            <p class="text-muted">Revenez bientôt pour découvrir nos prochains événements !</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <?php 
                        $isRegistered = in_array($event['id'], $userEvents);
                        $isFull = $event['places_remaining'] <= 0;
                        ?>
                        <div class="card event-card mb-4 <?php echo $isFull ? 'event-full' : ''; ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="event-date">
                                            <div class="h4 mb-1">
                                                <?php echo date('d', strtotime($event['event_date'])); ?>
                                            </div>
                                            <div>
                                                <?php echo date('M Y', strtotime($event['event_date'])); ?>
                                            </div>
                                            <?php if ($event['event_time']): ?>
                                                <div class="mt-2">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('H:i', strtotime($event['event_time'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h4 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h4>
                                        <p class="card-text text-muted"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                        
                                        <div class="mb-2">
                                            <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                            <strong><?php echo htmlspecialchars($event['location']); ?></strong>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <i class="fas fa-users text-info me-2"></i>
                                            <?php echo $event['current_participants']; ?>/<?php echo $event['max_participants']; ?> participants
                                            
                                            <?php if ($event['places_remaining'] <= 5 && $event['places_remaining'] > 0): ?>
                                                <span class="badge bg-warning text-dark ms-2">
                                                    Plus que <?php echo $event['places_remaining']; ?> place<?php echo $event['places_remaining'] > 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3 text-center">
                                        <?php if ($event['price'] > 0): ?>
                                            <div class="event-price mb-3">
                                                <?php echo number_format($event['price'], 2); ?> €
                                            </div>
                                        <?php else: ?>
                                            <div class="event-price mb-3 text-success">
                                                Gratuit
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($isRegistered): ?>
                                            <button class="btn btn-success btn-lg" disabled>
                                                <i class="fas fa-check me-2"></i><?php echo t('registered'); ?>
                                            </button>
                                        <?php elseif ($isFull): ?>
                                            <button class="btn btn-secondary btn-lg" disabled>
                                                <i class="fas fa-times me-2"></i><?php echo t('event_full'); ?>
                                            </button>
                                        <?php elseif (!isset($_SESSION['client_id'])): ?>
                                            <a href="login.php?redirect=events.php" class="btn btn-primary btn-lg">
                                                <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                            </a>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" name="register_event" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-calendar-plus me-2"></i><?php echo t('register_event'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>À propos de nos événements</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Participez à des expériences culinaires uniques organisées par Driv'n Cook !</p>
                        
                        <div class="mb-3">
                            <h6><i class="fas fa-utensils text-success me-2"></i>Dégustations</h6>
                            <p class="small text-muted">Découvrez nos nouveaux plats en avant-première</p>
                        </div>
                        
                        <div class="mb-3">
                            <h6><i class="fas fa-graduation-cap text-info me-2"></i>Ateliers</h6>
                            <p class="small text-muted">Apprenez les secrets de nos chefs</p>
                        </div>
                        
                        <div class="mb-0">
                            <h6><i class="fas fa-music text-warning me-2"></i>Soirées</h6>
                            <p class="small text-muted">Moments conviviaux avec musique et bonne nourriture</p>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['client_id']) && !empty($userEvents)): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar-check me-2"></i>Mes inscriptions</h5>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Vous êtes inscrit à <?php echo count($userEvents); ?> événement<?php echo count($userEvents) > 1 ? 's' : ''; ?></p>
                            
                            <div class="alert alert-info py-2">
                                <small><i class="fas fa-bell me-2"></i>
                                Nous vous enverrons un rappel par email 24h avant chaque événement</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-star me-2"></i>Avantages fidélité</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">En tant que membre fidèle :</p>
                        <ul class="small">
                            <li>Accès prioritaire aux événements</li>
                            <li>Tarifs préférentiels</li>
                            <li>Invitations aux événements exclusifs</li>
                        </ul>
                        
                        <?php if (!isset($_SESSION['client_id'])): ?>
                            <a href="register.php" class="btn btn-sm btn-success w-100">
                                <i class="fas fa-user-plus me-2"></i>Rejoindre le programme
                            </a>
                        <?php else: ?>
                            <a href="loyalty.php" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fas fa-star me-2"></i>Voir mes points
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const eventCards = document.querySelectorAll('.event-card');
        eventCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateX(-30px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateX(0)';
            }, index * 200);
        });

        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        document.getElementById('cart-count').textContent = count;

        const registerForms = document.querySelectorAll('form[method="POST"]');
        registerForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const eventTitle = this.closest('.event-card').querySelector('.card-title').textContent;
                if (!confirm(`Confirmer votre inscription à l'événement "${eventTitle}" ?`)) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
</body>
</html>