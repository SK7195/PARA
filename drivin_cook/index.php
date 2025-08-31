<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } elseif (isFranchisee()) {
        header('Location: franchisee/dashboard.php');
    }
    exit();
}

$pageTitle = 'Driv\'n Cook - Accueil';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .access-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .access-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .access-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-white">
                    <h1 class="display-3 fw-bold mb-4">
                        <i class="fas fa-truck me-3"></i>Driv'n Cook
                    </h1>
                    <p class="lead mb-4">
                        La plateforme complète pour la gestion de nos food trucks de qualité 
                        avec des produits frais et locaux dans toute l'Île-de-France.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="client/index.php" class="btn btn-light btn-lg">
                            <i class="fas fa-utensils me-2"></i>Espace Clients
                        </a>
                        <a href="#access" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-cog me-2"></i>Administration
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center text-white">
                    <i class="fas fa-truck fa-8x opacity-75 mb-4"></i>
                    <h3>Des food trucks de qualité</h3>
                    <p>Produits frais • Service rapide • Expérience unique</p>
                </div>
            </div>
        </div>
    </section>

    <section id="access" class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-5">
                        <h2 class="display-5 fw-bold mb-3">Accès aux espaces</h2>
                        <p class="lead text-muted">Choisissez votre espace de connexion</p>
                    </div>

                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <div class="card access-card h-100 text-center">
                                <div class="card-body p-4">
                                    <div class="access-icon text-success">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <h4 class="fw-bold mb-3">Espace Clients</h4>
                                    <p class="text-muted mb-4">
                                        Découvrez nos menus, passez vos commandes, 
                                        participez aux événements et gérez vos points fidélité.
                                    </p>
                                    <ul class="list-unstyled text-start mb-4">
                                        <li><i class="fas fa-check text-success me-2"></i>Commander en ligne</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Programme de fidélité</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Événements exclusifs</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Suivi des commandes</li>
                                    </ul>
                                    <a href="client/index.php" class="btn btn-success btn-lg w-100">
                                        <i class="fas fa-arrow-right me-2"></i>Accéder
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 mb-4">
                            <div class="card access-card h-100 text-center">
                                <div class="card-body p-4">
                                    <div class="access-icon text-primary">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <h4 class="fw-bold mb-3">Espace Franchisés</h4>
                                    <p class="text-muted mb-4">
                                        Gérez votre activité, commandez du stock, 
                                        saisissez vos ventes et suivez vos performances.
                                    </p>
                                    <ul class="list-unstyled text-start mb-4">
                                        <li><i class="fas fa-check text-primary me-2"></i>Gestion des ventes</li>
                                        <li><i class="fas fa-check text-primary me-2"></i>Commandes de stock</li>
                                        <li><i class="fas fa-check text-primary me-2"></i>Suivi des performances</li>
                                        <li><i class="fas fa-check text-primary me-2"></i>Gestion des camions</li>
                                    </ul>
                                    <a href="franchisee/login.php" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                    </a>
                                </div>
                            </div>
                        </div>


                        <div class="col-lg-4 mb-4">
                            <div class="card access-card h-100 text-center">
                                <div class="card-body p-4">
                                    <div class="access-icon text-danger">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <h4 class="fw-bold mb-3">Administration</h4>
                                    <p class="text-muted mb-4">
                                        Supervision complète du réseau, gestion des franchisés, 
                                        stocks et analyses globales.
                                    </p>
                                    <ul class="list-unstyled text-start mb-4">
                                        <li><i class="fas fa-check text-danger me-2"></i>Gestion franchisés</li>
                                        <li><i class="fas fa-check text-danger me-2"></i>Parc de camions</li>
                                        <li><i class="fas fa-check text-danger me-2"></i>Gestion globale stocks</li>
                                        <li><i class="fas fa-check text-danger me-2"></i>Rapports et analyses</li>
                                    </ul>
                                    <a href="admin/login.php" class="btn btn-danger btn-lg w-100">
                                        <i class="fas fa-shield-alt me-2"></i>Accès Admin
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-5">
                        <div class="col-12">
                            <div class="card border-0 bg-white">
                                <div class="card-body text-center p-4">
                                    <h5 class="fw-bold mb-3">Comptes de démonstration</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Client :</strong><br>
                                            <code>marie.dubois@email.com</code><br>
                                            <code>password</code>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Franchisé :</strong><br>
                                            <code>franchisee1@example.com</code><br>
                                            <code>password</code>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Admin :</strong><br>
                                            <code>admin@drivinCook.fr</code><br>
                                            <code>password</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-truck me-2"></i>Driv'n Cook</h5>
                    <p class="text-muted">
                        Des food trucks de qualité avec des produits frais et locaux 
                        dans toute l'Île-de-France.
                    </p>
                </div>
                <div class="col-md-6">
                    <h6>Statistiques du réseau</h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="fw-bold">30+</div>
                            <small class="text-muted">Franchisés</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold">4</div>
                            <small class="text-muted">Entrepôts</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold">100%</div>
                            <small class="text-muted">Local</small>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Driv'n Cook - Système de gestion des franchises</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.access-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>