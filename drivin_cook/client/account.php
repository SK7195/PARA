<?php
require_once '../config/database.php';
require_once '../config/language.php';
require_once '../classes/Client.php';

$currentLang = getCurrentLanguage();

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php?redirect=account.php');
    exit();
}

$client = new Client();
$clientId = $_SESSION['client_id'];
$error = $success = '';

if ($_POST && isset($_POST['update_profile'])) {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $language = $_POST['language'] ?? 'fr';
    
    if (!$firstname || !$lastname) {
        $error = t('required_fields');
    } else {
        $data = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'phone' => $phone,
            'address' => $address,
            'language' => $language
        ];
        
        $result = $client->update($clientId, $data);
        
        if ($result['success']) {
            $success = t('profile_updated');
            
            $_SESSION['client_name'] = $firstname . ' ' . $lastname;
            
            if ($language !== getCurrentLanguage()) {
                setLanguage($language);
                
                header('refresh:1;url=account.php');
            }
        } else {
            $error = $result['error'];
        }
    }
}

if ($_POST && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!$currentPassword || !$newPassword || !$confirmPassword) {
        $error = 'Tous les champs sont obligatoires';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Les nouveaux mots de passe ne correspondent pas';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT password FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $storedPassword = $stmt->fetchColumn();
        
        if (!password_verify($currentPassword, $storedPassword)) {
            $error = 'Mot de passe actuel incorrect';
        } else {
            $data = ['password' => $newPassword];
            $result = $client->update($clientId, $data);
            
            if ($result['success']) {
                $success = 'Mot de passe modifié avec succès';
            } else {
                $error = $result['error'];
            }
        }
    }
}

if ($_POST && isset($_POST['toggle_newsletter'])) {
    $subscribe = isset($_POST['newsletter_subscribe']);
    
    if ($subscribe) {
        $client->subscribeToNewsletter($clientId);
        $success = 'Abonné à la newsletter avec succès';
    } else {
        $client->unsubscribeFromNewsletter($clientId);
        $success = 'Désabonné de la newsletter';
    }
}

$clientInfo = $client->getById($clientId);
$clientStats = $client->getStats($clientId);
$isSubscribed = $client->isSubscribedToNewsletter($clientId);

if (!$clientInfo) {
    header('Location: logout.php');
    exit();
}

$pageTitle = t('account_title');
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
        .profile-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 20px;
        }
        .account-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        .account-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        .quick-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
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
                            <li><a class="dropdown-item active" href="account.php">
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

        <div class="profile-header text-center">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h1>Bonjour, <?php echo htmlspecialchars($clientInfo['firstname']); ?> !</h1>
            <p class="mb-0">Membre depuis le <?php echo date('d/m/Y', strtotime($clientInfo['created_at'])); ?></p>
        </div>

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
            <div class="col-12 mb-4">
                <div class="quick-stats">
                    <h5 class="text-center mb-4"><i class="fas fa-chart-bar me-2"></i>Mes statistiques</h5>
                    <div class="row">
                        <div class="col-md-3 stat-item">
                            <div class="stat-number"><?php echo $clientStats['total_orders']; ?></div>
                            <small class="text-muted">Commandes</small>
                        </div>
                        <div class="col-md-3 stat-item">
                            <div class="stat-number"><?php echo number_format($clientStats['total_spent'], 0); ?> €</div>
                            <small class="text-muted">Dépensé</small>
                        </div>
                        <div class="col-md-3 stat-item">
                            <div class="stat-number"><?php echo $clientInfo['loyalty_points']; ?></div>
                            <small class="text-muted">Points fidélité</small>
                        </div>
                        <div class="col-md-3 stat-item">
                            <div class="stat-number"><?php echo number_format($clientStats['avg_order'], 0); ?> €</div>
                            <small class="text-muted">Panier moyen</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">

            <div class="col-lg-8">
                <div class="account-section">
                    <div class="section-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h4 class="mb-3">Informations personnelles</h4>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="firstname" class="form-label"><?php echo t('firstname'); ?> *</label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" 
                                           value="<?php echo htmlspecialchars($clientInfo['firstname']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="lastname" class="form-label"><?php echo t('lastname'); ?> *</label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" 
                                           value="<?php echo htmlspecialchars($clientInfo['lastname']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label"><?php echo t('email'); ?></label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($clientInfo['email']); ?>" disabled>
                                    <div class="form-text">L'email ne peut pas être modifié</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label"><?php echo t('phone'); ?></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($clientInfo['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="language" class="form-label"><?php echo t('language'); ?></label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="fr" <?php echo $clientInfo['language'] === 'fr' ? 'selected' : ''; ?>>🇫🇷 Français</option>
                                        <option value="en" <?php echo $clientInfo['language'] === 'en' ? 'selected' : ''; ?>>🇬🇧 English</option>
                                        <option value="es" <?php echo $clientInfo['language'] === 'es' ? 'selected' : ''; ?>>🇪🇸 Español</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label"><?php echo t('address'); ?></label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($clientInfo['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-success">
                            <i class="fas fa-save me-2"></i><?php echo t('save_changes'); ?>
                        </button>
                    </form>
                </div>

                <div class="account-section">
                    <div class="section-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h4 class="mb-3">Changer mon mot de passe</h4>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Mot de passe actuel *</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe *</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6" required>
                                    <div class="form-text">Au moins 6 caractères</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="6" required>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">

                <div class="account-section">
                    <div class="section-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h5 class="mb-3"><?php echo t('newsletter'); ?></h5>
                    
                    <form method="POST">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="newsletter_subscribe" 
                                   name="newsletter_subscribe" <?php echo $isSubscribed ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="newsletter_subscribe">
                                Recevoir la newsletter mensuelle
                            </label>
                        </div>
                        <small class="text-muted d-block mb-3">
                            Découvrez nos nouveautés, offres spéciales et événements exclusifs
                        </small>
                        <button type="submit" name="toggle_newsletter" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-envelope me-2"></i>Mettre à jour
                        </button>
                    </form>
                </div>

                <div class="account-section">
                    <div class="section-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h5 class="mb-3">Actions rapides</h5>
                    
                    <div class="d-grid gap-2">
                        <a href="orders.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i><?php echo t('my_orders'); ?>
                        </a>
                        <a href="loyalty.php" class="btn btn-outline-warning">
                            <i class="fas fa-star me-2"></i><?php echo t('loyalty_card'); ?>
                        </a>
                        <a href="events.php" class="btn btn-outline-info">
                            <i class="fas fa-calendar me-2"></i><?php echo t('events'); ?>
                        </a>
                        <a href="menu.php" class="btn btn-outline-success">
                            <i class="fas fa-utensils me-2"></i>Commander
                        </a>
                    </div>
                </div>

                <div class="account-section">
                    <div class="section-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h5 class="mb-3">Mon compte</h5>
                    
                    <div class="small text-muted">
                        <p><strong>Membre depuis :</strong><br>
                        <?php echo date('d/m/Y', strtotime($clientInfo['created_at'])); ?></p>
                        
                        <?php if ($clientStats['last_order_date']): ?>
                            <p><strong>Dernière commande :</strong><br>
                            <?php echo date('d/m/Y', strtotime($clientStats['last_order_date'])); ?></p>
                        <?php endif; ?>
                        
                        <p><strong>Langue préférée :</strong><br>
                        <?php 
                        $langNames = ['fr' => 'Français', 'en' => 'English', 'es' => 'Español'];
                        echo $langNames[$clientInfo['language']] ?? $clientInfo['language'];
                        ?></p>
                        
                        <div class="mt-3 pt-3 border-top">
                            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100">
                                <i class="fas fa-sign-out-alt me-2"></i><?php echo t('logout'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
 
        const sections = document.querySelectorAll('.account-section');
        sections.forEach((section, index) => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';
            setTimeout(() => {
                section.style.transition = 'all 0.6s ease';
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            }, index * 150);
        });

        const confirmPassword = document.getElementById('confirm_password');
        const newPassword = document.getElementById('new_password');
        
        if (confirmPassword && newPassword) {
            confirmPassword.addEventListener('input', function() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Les mots de passe ne correspondent pas');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
        }

        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        document.getElementById('cart-count').textContent = count;

        const avatar = document.querySelector('.profile-avatar');
        if (avatar) {
            avatar.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1) rotate(5deg)';
                this.style.transition = 'all 0.3s ease';
            });
            
            avatar.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
            });
        }

        const languageSelect = document.getElementById('language');
        if (languageSelect) {
            const originalLang = languageSelect.value;
            languageSelect.addEventListener('change', function() {
                if (this.value !== originalLang) {
                    const langNames = {fr: 'Français', en: 'English', es: 'Español'};
                    if (confirm(`Changer la langue vers ${langNames[this.value]} ? La page sera rechargée.`)) {

                    } else {
                        this.value = originalLang;
                    }
                }
            });
        }
    });
    </script>
</body>
</html>