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
$success = '';

if ($_POST) {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    

    if (!$firstname || !$lastname || !$email || !$password) {
        $error = t('required_fields');
    } elseif ($password !== $confirm_password) {
        $error = t('confirm_password') . ' ne correspond pas';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } else {
        $data = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'language' => getCurrentLanguage()
        ];
        
        $result = $client->create($data);
        
        if ($result['success']) {
            $success = t('account_created');

            $_SESSION['client_id'] = $result['client_id'];
            $_SESSION['client_email'] = $email;
            $_SESSION['client_name'] = $firstname . ' ' . $lastname;
            
            header('refresh:2;url=account.php');
        } else {
            $error = $result['error'];
        }
    }
}

$pageTitle = t('register_title');
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
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-header bg-success text-white text-center">
                        <h2><i class="fas fa-user-plus me-2"></i><?php echo t('register_title'); ?></h2>
                        <p class="mb-0">Rejoignez la communauté Driv'n Cook</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <br><small>Redirection automatique...</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$success): ?>
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="firstname" class="form-label">
                                                <?php echo t('firstname'); ?> *
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="firstname" 
                                                   name="firstname" 
                                                   value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="lastname" class="form-label">
                                                <?php echo t('lastname'); ?> *
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="lastname" 
                                                   name="lastname" 
                                                   value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">
                                                <i class="fas fa-envelope me-2"></i><?php echo t('email'); ?> *
                                            </label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email" 
                                                   name="email" 
                                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">
                                                <i class="fas fa-phone me-2"></i><?php echo t('phone'); ?>
                                            </label>
                                            <input type="tel" 
                                                   class="form-control" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">
                                                <i class="fas fa-lock me-2"></i><?php echo t('password'); ?> *
                                            </label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="password" 
                                                   name="password" 
                                                   minlength="6"
                                                   required>
                                            <div class="form-text">Au moins 6 caractères</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">
                                                <?php echo t('confirm_password'); ?> *
                                            </label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="confirm_password" 
                                                   name="confirm_password" 
                                                   minlength="6"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="newsletter" 
                                               name="newsletter" 
                                               checked>
                                        <label class="form-check-label" for="newsletter">
                                            <i class="fas fa-envelope me-2"></i>
                                            S'abonner à la newsletter mensuelle
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-user-plus me-2"></i><?php echo t('create_account'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if (!$success): ?>
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <p class="text-muted"><?php echo t('has_account'); ?></p>
                                <a href="login.php" class="btn btn-outline-success">
                                    <i class="fas fa-sign-in-alt me-2"></i><?php echo t('sign_in'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                

                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="text-center mb-3">🎁 Avantages de votre compte</h6>
                        <div class="row text-center">
                            <div class="col-md-3 mb-2">
                                <i class="fas fa-star text-warning fa-2x mb-2"></i>
                                <p class="small mb-0"><strong>Points fidélité</strong><br>1 € = 1 point</p>
                            </div>
                            <div class="col-md-3 mb-2">
                                <i class="fas fa-history text-info fa-2x mb-2"></i>
                                <p class="small mb-0"><strong>Historique</strong><br>Toutes vos commandes</p>
                            </div>
                            <div class="col-md-3 mb-2">
                                <i class="fas fa-calendar text-success fa-2x mb-2"></i>
                                <p class="small mb-0"><strong>Événements</strong><br>Invitations exclusives</p>
                            </div>
                            <div class="col-md-3 mb-2">
                                <i class="fas fa-envelope text-primary fa-2x mb-2"></i>
                                <p class="small mb-0"><strong>Newsletter</strong><br>Offres spéciales</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirm = this.value;
        
        if (password !== confirm) {
            this.setCustomValidity('Les mots de passe ne correspondent pas');
        } else {
            this.setCustomValidity('');
        }
    });
    </script>
</body>
</html>