<?php
require_once '../config/database.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-truck me-2"></i>Driv'n Cook
            </a>

            <?php if (isLoggedIn()): ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="../admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Tableau de bord
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../admin/franchises.php">
                                    <i class="fas fa-users me-1"></i>Franchisés
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../admin/trucks.php">
                                    <i class="fas fa-truck me-1"></i>Camions
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../admin/stocks.php">
                                    <i class="fas fa-boxes me-1"></i>Stocks
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../admin/reports.php">
                                    <i class="fas fa-chart-bar me-1"></i>Rapports
                                </a>
                            </li>
                        <?php elseif (isFranchisee()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="../franchisee/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Mon espace
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../franchisee/profile.php">
                                    <i class="fas fa-user me-1"></i>Mon profil
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../franchisee/orders.php">
                                    <i class="fas fa-shopping-cart me-1"></i>Commandes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../franchisee/sales.php">
                                    <i class="fas fa-cash-register me-1"></i>Ventes
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo $_SESSION['user_email'] ?? 'Utilisateur'; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="../logout.php">
                                        <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                                    </a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <main class="container mt-4"><?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>