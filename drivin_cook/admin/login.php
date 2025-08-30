<?php
require_once '../config/database.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: dashboard.php');
    } else {
        header('Location: ../franchisee/dashboard.php');
    }
    exit();
}

$error = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND type = 'admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_type'] = $user['type'];

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Email ou mot de passe incorrect, ou vous n\'êtes pas administrateur';
        }
    } else {
        $error = 'Veuillez remplir tous les champs';
    }
}

$pageTitle = 'Connexion Administrateur';
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
                        <div class="login-header">
                            <h2><i class="fas fa-shield-alt me-2"></i>Administration</h2>
                            <p class="mb-0">Driv'n Cook - Espace Administrateur</p>
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
                                        <i class="fas fa-envelope me-2"></i>Email administrateur
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           placeholder="admin@drivinCook.fr"
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
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <h6 class="text-muted mb-3">Accès administrateur uniquement</h6>
                                <div class="alert alert-info py-2">
                                    <small>
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Compte test :</strong><br>
                                        Email: admin@drivinCook.fr<br>
                                        Mot de passe: password
                                    </small>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
                                    </a>
                                    <a href="../franchisee/login.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-user me-2"></i>Espace Franchisé
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header bg-dark text-white">
                            <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Fonctionnalités administrateur</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                    <p class="small mb-0">Gestion des franchisés</p>
                                </div>
                                <div class="col-6 mb-3">
                                    <i class="fas fa-truck fa-2x text-success mb-2"></i>
                                    <p class="small mb-0">Parc de camions</p>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-boxes fa-2x text-warning mb-2"></i>
                                    <p class="small mb-0">Gestion des stocks</p>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-chart-bar fa-2x text-info mb-2"></i>
                                    <p class="small mb-0">Rapports et analyses</p>
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