<?php

require_once '../config/database.php';
require_once '../config/language.php';
require_once '../classes/Client.php';

if (isset($_SESSION['client_id'])) {
    header('Location: account.php');
    exit();
}

$client = new Client();
$error = '';


if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $result = $client->authenticate($email, $password);
        
        if ($result['success']) {
            $_SESSION['client_id'] = $result['client']['id'];
            $_SESSION['client_email'] = $result['client']['email'];
            $_SESSION['client_name'] = $result['client']['firstname'] . ' ' . $result['client']['lastname'];
            

            $redirect = $_GET['redirect'] ?? 'account.php';
            header('Location: ' . $redirect);
            exit();
        } else {
            $error = $result['error'];
        }
    } else {
        $error = t('required_fields');
    }
}

$pageTitle = t('login_title');
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Driv'n Cook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
   
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-utensils me-2"></i>Driv'n Cook
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left me-1"></i><?php echo t('back'); ?>
                </a>
                <?php renderLanguageSelector(); ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-container">
                    <div class="card login-card">
                        <div class="login-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <h2><i class="fas fa-user me-2"></i><?php echo t('login_title'); ?></h2>
                            <p class="mb-0"><?php echo t('welcome_subtitle'); ?></p>
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
                                        <i class="fas fa-envelope me-2"></i><?php echo t('email'); ?>
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           placeholder="votre@email.com"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required>
                                    <div class="invalid-feedback">
                                        <?php echo t('required_fields'); ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i><?php echo t('password'); ?>
                                    </label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="••••••••" 
                                           required>
                                    <div class="invalid-feedback">
                                        <?php echo t('required_fields'); ?>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i><?php echo t('sign_in'); ?>
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <p class="text-muted"><?php echo t('no_account'); ?></p>
                                <a href="register.php" class="btn btn-outline-success">
                                    <i class="fas fa-user-plus me-2"></i><?php echo t('create_account'); ?>
                                </a>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <small class="text-muted">
                                    <strong><?php echo t('info'); ?> :</strong><br>
                                    Email: marie.dubois@email.com<br>
                                    <?php echo t('password'); ?>: password
                                </small>
                            </div>
                        </div>
                    </div>
                    
             
                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="text-center text-muted mb-3"><?php echo t('loyalty_card'); ?></h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <i class="fas fa-star text-warning fa-2x mb-2"></i>
                                    <p class="small">1€ = 1 point</p>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-gift text-danger fa-2x mb-2"></i>
                                    <p class="small">100 pts = 5€</p>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-calendar text-info fa-2x mb-2"></i>
                                    <p class="small"><?php echo t('events'); ?></p>
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