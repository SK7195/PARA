<?php

require_once '../config/database.php';
require_once '../config/language.php';
require_once '../classes/Menu.php';

$menu = new Menu();
$currentLang = getCurrentLanguage();


$popularMenus = $menu->getPopular($currentLang, 4);
$categories = $menu->getCategories();

$pageTitle = t('welcome_title');
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
                        <a class="nav-link active" href="index.php">
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

    <section class="hero-section py-5" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
        <div class="container text-white text-center">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4"><?php echo t('welcome_title'); ?></h1>
                    <p class="lead mb-4"><?php echo t('welcome_subtitle'); ?></p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="menu.php" class="btn btn-light btn-lg">
                            <i class="fas fa-utensils me-2"></i><?php echo t('our_menu'); ?>
                        </a>
                        <a href="#locations" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-map-marker-alt me-2"></i><?php echo t('find_truck'); ?>
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-truck fa-8x opacity-75"></i>
                </div>
            </div>
        </div>
    </section>

    <main class="container my-5">

        <section class="mb-5">
            <h2 class="text-center mb-4">
                <i class="fas fa-star text-warning me-2"></i>
                <?php echo t('menu_title'); ?>
            </h2>
            
            <?php if (!empty($popularMenus)): ?>
                <div class="row">
                    <?php foreach ($popularMenus as $menuItem): ?>
                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="card h-100 menu-card">
                                <?php if ($menuItem['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($menuItem['image_url']); ?>" 
                                         class="card-img-top" style="height: 200px; object-fit: cover;"
                                         alt="<?php echo htmlspecialchars($menuItem['name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <i class="fas fa-utensils fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($menuItem['name']); ?></h5>
                                    <p class="card-text text-muted flex-grow-1">
                                        <?php echo htmlspecialchars($menuItem['description']); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h5 text-success mb-0">
                                            <?php echo number_format($menuItem['price'], 2); ?> €
                                        </span>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($menuItem['category']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="menu.php" class="btn btn-success btn-lg">
                        <i class="fas fa-utensils me-2"></i>
                        <?php echo t('our_menu'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <section class="mb-5">
            <h3 class="text-center mb-4"><?php echo t('category_' . 'burger'); ?> & Plus</h3>
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <div class="category-card p-4 bg-light rounded">
                        <i class="fas fa-hamburger fa-3x text-warning mb-3"></i>
                        <h5><?php echo t('category_burger'); ?></h5>
                        <p class="text-muted">Des burgers artisanaux</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="category-card p-4 bg-light rounded">
                        <i class="fas fa-leaf fa-3x text-success mb-3"></i>
                        <h5><?php echo t('category_salad'); ?></h5>
                        <p class="text-muted">Fraîches et locales</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="category-card p-4 bg-light rounded">
                        <i class="fas fa-glass-whiskey fa-3x text-info mb-3"></i>
                        <h5><?php echo t('category_drink'); ?></h5>
                        <p class="text-muted">Rafraîchissantes</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="category-card p-4 bg-light rounded">
                        <i class="fas fa-ice-cream fa-3x text-danger mb-3"></i>
                        <h5><?php echo t('category_dessert'); ?></h5>
                        <p class="text-muted">Délicieux desserts</p>
                    </div>
                </div>
            </div>
        </section>


        <section class="mb-5" id="events">
            <div class="row">
                <div class="col-lg-6">
                    <h3 class="mb-4">
                        <i class="fas fa-calendar text-primary me-2"></i>
                        <?php echo t('events'); ?>
                    </h3>
                    <p class="text-muted mb-4">
                        Rejoignez-nous pour des événements uniques : dégustations, ateliers culinaires et soirées conviviales !
                    </p>
                    <a href="events.php" class="btn btn-primary">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo t('join_events'); ?>
                    </a>
                </div>
                <div class="col-lg-6">
                    <div class="bg-light p-4 rounded">
                        <h5><i class="fas fa-star text-warning me-2"></i>Avantages fidélité</h5>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>1 € dépensé = 1 point gagné</li>
                            <li><i class="fas fa-check text-success me-2"></i>100 points = 5 € de réduction</li>
                            <li><i class="fas fa-check text-success me-2"></i>Invitations aux événements exclusifs</li>
                            <li><i class="fas fa-check text-success me-2"></i>Newsletter mensuelle</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section id="locations" class="mb-5">
            <h3 class="text-center mb-4">
                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                <?php echo t('find_truck'); ?>
            </h3>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-map-marker-alt fa-2x text-danger mb-3"></i>
                            <h5>Place de la République</h5>
                            <p class="text-muted">Lun-Ven : 11h30-14h30</p>
                            <small class="text-success">Ouvert maintenant</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-map-marker-alt fa-2x text-danger mb-3"></i>
                            <h5>Gare du Nord</h5>
                            <p class="text-muted">Lun-Sam : 12h-15h</p>
                            <small class="text-warning">Ferme à 15h</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-map-marker-alt fa-2x text-danger mb-3"></i>
                            <h5>Châtelet Les Halles</h5>
                            <p class="text-muted">Mar-Dim : 11h-16h</p>
                            <small class="text-success">Ouvert maintenant</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><i class="fas fa-utensils me-2"></i>Driv'n Cook</h5>
                    <p class="text-muted">Des food trucks de qualité avec des produits frais et locaux dans toute l'Île-de-France.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h6><?php echo t('contact_us'); ?></h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone me-2"></i>01 23 45 67 89</li>
                        <li><i class="fas fa-envelope me-2"></i>contact@drivinCook.fr</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i>Paris, Île-de-France</li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h6><?php echo t('newsletter'); ?></h6>
                    <p class="small text-muted">Recevez nos dernières actualités et offres spéciales</p>
                    <?php if (!isset($_SESSION['client_id'])): ?>
                        <a href="register.php" class="btn btn-success btn-sm">
                            <i class="fas fa-user-plus me-2"></i><?php echo t('subscribe'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Driv'n Cook - <?php echo t('copyright'); ?></p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.menu-card, .category-card');
        
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
  
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    });
    </script>
</body>
</html>