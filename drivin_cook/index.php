<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: franchisee/dashboard.php');
    }
    exit();
}

$error = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_type'] = $user['type'];

            if ($user['type'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: franchisee/dashboard.php');
            }
            exit();
        } else {
            $error = 'Email ou mot de passe incorrect';
        }
    } else {
        $error = 'Veuillez remplir tous les champs';
    }
}

$pageTitle = 'Connexion';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle . ' - ' . SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-container">
                    <div class="card login-card">
                        <div class="login-header">
                            <h2><i class="fas fa-truck me-2"></i>Driv'n Cook</h2>
                            <p class="mb-0">Système de gestion des franchises</p>
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
                                        <i class="fas fa-envelope me-2"></i>Email
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        placeholder="votre@email.com"
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">
                                        Veuillez saisir un email valide.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Mot de passe
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password"
                                        placeholder="••••••••" required>
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
                                <h6 class="text-muted">Comptes de test :</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Admin</small>
                                        <code>admin@drivinCook.fr</code><br>
                                        <code>password</code>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Franchisé</small>
                                        <code>franchisee1@example.com</code><br>
                                        <code>password</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <div class="row text-muted">
                            <div class="col-4">
                                <i class="fas fa-truck fa-2x mb-2"></i>
                                <p><small>30+ Franchisés</small></p>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
                                <p><small>4 Entrepôts</small></p>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-leaf fa-2x mb-2"></i>
                                <p><small>Produits Locaux</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>