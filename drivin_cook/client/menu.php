<?php
require_once '../config/database.php';
require_once '../config/language.php';
require_once '../classes/Menu.php';

$menu = new Menu();
$currentLang = getCurrentLanguage();

$selectedCategory = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';

if ($searchQuery) {
    $menus = $menu->search($searchQuery, $currentLang, $selectedCategory ?: null);
} elseif ($selectedCategory) {
    $menus = $menu->getByCategory($selectedCategory, $currentLang);
} else {
    $menus = $menu->getAll($currentLang);
}

$categories = $menu->getCategories();
$pageTitle = t('menu_title');
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
                        <a class="nav-link active" href="menu.php">
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

    <div class="container my-5">
        <div class="row">

            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i><?php echo t('filter'); ?></h5>
                    </div>
                    <div class="card-body">

                        <form method="GET" class="mb-3">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="<?php echo t('search'); ?>..." 
                                       value="<?php echo htmlspecialchars($searchQuery); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <?php if ($selectedCategory): ?>
                                <input type="hidden" name="category" value="<?php echo $selectedCategory; ?>">
                            <?php endif; ?>
                        </form>

                        <h6><?php echo t('category_burger'); ?> & Plus</h6>
                        <div class="list-group">
                            <a href="menu.php" class="list-group-item <?php echo !$selectedCategory ? 'active' : ''; ?>">
                                <i class="fas fa-list me-2"></i>Tout voir
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="?category=<?php echo $cat['category']; ?>" 
                                   class="list-group-item <?php echo $selectedCategory === $cat['category'] ? 'active' : ''; ?>">
                                    <i class="fas fa-<?php echo ['burger' => 'hamburger', 'salad' => 'leaf', 'drink' => 'glass-whiskey', 'dessert' => 'ice-cream', 'starter' => 'utensils'][$cat['category']] ?? 'utensils'; ?> me-2"></i>
                                    <?php echo t('category_' . $cat['category']); ?>
                                    <span class="badge bg-secondary float-end"><?php echo $cat['count']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-utensils me-2"></i><?php echo $pageTitle; ?></h1>
                    <span class="text-muted"><?php echo count($menus); ?> plat<?php echo count($menus) > 1 ? 's' : ''; ?></span>
                </div>

                <?php if ($searchQuery || $selectedCategory): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if ($searchQuery): ?>
                            Résultats pour "<?php echo htmlspecialchars($searchQuery); ?>"
                        <?php endif; ?>
                        <?php if ($selectedCategory): ?>
                            Catégorie : <?php echo t('category_' . $selectedCategory); ?>
                        <?php endif; ?>
                        <a href="menu.php" class="btn btn-sm btn-outline-primary ms-2">Effacer</a>
                    </div>
                <?php endif; ?>

                <?php if (empty($menus)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>Aucun plat trouvé</h4>
                        <p class="text-muted">Essayez de modifier vos critères de recherche</p>
                        <a href="menu.php" class="btn btn-primary">Voir tous les plats</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($menus as $menuItem): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
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
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="h4 text-success mb-0">
                                                <?php echo number_format($menuItem['price'], 2); ?> €
                                            </span>
                                            <span class="badge bg-secondary">
                                                <?php echo t('category_' . $menuItem['category']); ?>
                                            </span>
                                        </div>

                                        <?php if ($menuItem['available']): ?>
                                            <?php if (isset($_SESSION['client_id'])): ?>
                                                <button class="btn btn-success add-to-cart" 
                                                        data-id="<?php echo $menuItem['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($menuItem['name']); ?>"
                                                        data-price="<?php echo $menuItem['price']; ?>">
                                                    <i class="fas fa-cart-plus me-2"></i><?php echo t('add_to_cart'); ?>
                                                </button>
                                            <?php else: ?>
                                                <a href="login.php?redirect=menu.php" class="btn btn-primary">
                                                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter pour commander
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" disabled>
                                                <i class="fas fa-times me-2"></i><?php echo t('unavailable'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    
    function updateCartCount() {
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        document.getElementById('cart-count').textContent = count;
    }
    
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const id = parseInt(this.dataset.id);
            const name = this.dataset.name;
            const price = parseFloat(this.dataset.price);
            
            const existingItem = cart.find(item => item.id === id);
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({ id, name, price, quantity: 1 });
            }
            
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartCount();
            
            this.innerHTML = '<i class="fas fa-check me-2"></i>Ajouté !';
            this.classList.remove('btn-success');
            this.classList.add('btn-success');
            
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-cart-plus me-2"></i><?php echo t('add_to_cart'); ?>';
            }, 1500);
        });
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.menu-card');
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
        
        updateCartCount();
    });
    </script>
</body>
</html>