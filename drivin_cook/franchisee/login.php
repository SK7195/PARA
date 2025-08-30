<?php
require_once '../config/database.php';

if (isLoggedIn()) {
    if (isFranchisee()) {
        header('Location: dashboard.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit();
}

$error = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND type = 'franchisee'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_type'] = $user['type'];

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Email ou mot de passe incorrect, ou vous n\'êtes pas franchisé';
        }
    } else {
        $error = 'Veuillez remplir tous les champs';
    }
}

$pageTitle = 'Connexion Franchisé';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle . ' - ' . SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-container">
                    <div class="card login-card">
                        <div class="login-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <h2><i class="fas fa-user-tie me-2"></i>Espace Franchisé</h2>
                            <p class="mb-0">Driv'n Cook - Mon espace personnel</p>
                        </div>
                        <div class="login-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email franchisé
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           placeholder="votre@email.com"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required>
                                    <div class="invalid-feedback">
                                        Veuillez saisir un email valide.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Mot de passe
                                    </label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="••••••••" 
                                           required>
                                    <div class="invalid-feedback">
                                        Veuillez saisir votre mot de passe.
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Accéder à mon espace
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <h6 class="text-muted mb-3">Franchisés Driv'n Cook</h6>
                                <div class="alert alert-success py-2">
                                    <small>
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Compte test :</strong><br>
                                        Email: franchisee1@example.com<br>
                                        Mot de passe: password
                                    </small>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
                                    </a>
                                    <a href="../admin/login.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-shield-alt me-2"></i>Administration
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-handshake me-2"></i>Vos services franchisé</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                                    <p class="small mb-0">Commandes de stock</p>
                                </div>
                                <div class="col-6 mb-3">
                                    <i class="fas fa-cash-register fa-2x text-success mb-2"></i>
                                    <p class="small mb-0">Saisie des ventes</p>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-truck fa-2x text-warning mb-2"></i>
                                    <p class="small mb-0">Gestion camions</p>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                                    <p class="small mb-0">Suivi performances</p>
                                </div>
                            </div>

                            <div class="mt-3 pt-3 border-top">
                                <h6 class="text-muted mb-2"><i class="fas fa-info-circle me-2"></i>Rappel de vos engagements</h6>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-euro-sign text-success me-2"></i>Commission : 4% du CA mensuel</li>
                                    <li><i class="fas fa-shopping-basket text-warning me-2"></i>Achat obligatoire : 80% chez Driv'n Cook</li>
                                    <li><i class="fas fa-handshake text-info me-2"></i>Droit d'entrée : 50 000 €</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Besoin d'aide ?</h6>
                            <div class="row">
                                <div class="col-6">
                                    <i class="fas fa-phone text-primary"></i>
                                    <small class="d-block">01 23 45 67 89</small>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-envelope text-primary"></i>
                                    <small class="d-block">support@drivinCook.fr</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>