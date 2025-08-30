<?php
$pageTitle = 'Mon Profil';
require_once '../includes/header.php';
requireLogin();

if (!isFranchisee()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$pdo = getDBConnection();
$error = $success = '';

$stmt = $pdo->prepare("
    SELECT f.*, u.email 
    FROM franchisees f 
    JOIN users u ON f.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$franchisee = $stmt->fetch();

if (!$franchisee) {
    $_SESSION['error'] = 'Franchisé non trouvé';
    header('Location: ../logout.php');
    exit();
}

if ($_POST && isset($_POST['update_profile'])) {
    $name = $_POST['name'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    if ($name) {
        $stmt = $pdo->prepare("UPDATE franchisees SET name = ?, company_name = ?, phone = ?, address = ? WHERE id = ?");
        if ($stmt->execute([$name, $company_name, $phone, $address, $franchisee['id']])) {
            $success = 'Profil mis à jour avec succès';
          
            $stmt = $pdo->prepare("
                SELECT f.*, u.email 
                FROM franchisees f 
                JOIN users u ON f.user_id = u.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $franchisee = $stmt->fetch();
        } else {
            $error = 'Erreur lors de la mise à jour';
        }
    } else {
        $error = 'Le nom est obligatoire';
    }
}


if ($_POST && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($current_password && $new_password && $confirm_password) {
   
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_password = $stmt->fetchColumn();
        
        if (password_verify($current_password, $user_password)) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                        $success = 'Mot de passe modifié avec succès';
                    } else {
                        $error = 'Erreur lors de la modification du mot de passe';
                    }
                } else {
                    $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères';
                }
            } else {
                $error = 'Les nouveaux mots de passe ne correspondent pas';
            }
        } else {
            $error = 'Mot de passe actuel incorrect';
        }
    } else {
        $error = 'Tous les champs mot de passe sont obligatoires';
    }
}

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(daily_revenue), 0) as total_revenue,
        COALESCE(AVG(daily_revenue), 0) as avg_revenue,
        MAX(sale_date) as last_sale_date
    FROM sales 
    WHERE franchisee_id = ?
");
$stmt->execute([$franchisee['id']]);
$sales_stats = $stmt->fetch();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-user me-2"></i>Mon Profil</h1>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-edit me-2"></i>Informations personnelles</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom complet *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($franchisee['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($franchisee['email']); ?>" readonly>
                                    <div class="form-text">L'email ne peut pas être modifié</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">Nom de l'entreprise</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" 
                                           value="<?php echo htmlspecialchars($franchisee['company_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Téléphone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($franchisee['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Adresse</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($franchisee['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Mettre à jour le profil
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-lock me-2"></i>Changer le mot de passe</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel *</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
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
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Informations de compte</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Statut du compte</label>
                        <div>
                            <span class="badge status-<?php echo $franchisee['status']; ?> fs-6">
                                <?php echo ucfirst($franchisee['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted">Date d'inscription</label>
                        <div><?php echo date('d/m/Y', strtotime($franchisee['created_at'])); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted">Droit d'entrée</label>
                        <div>
                            <strong><?php echo number_format($franchisee['entry_fee_paid'], 2); ?> €</strong>
                            <span class="badge bg-success ms-2">Payé</span>
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label text-muted">Taux de commission</label>
                        <div><strong><?php echo $franchisee['commission_rate']; ?>%</strong> du chiffre d'affaires</div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar me-2"></i>Mes statistiques</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total des ventes</span>
                            <strong><?php echo $sales_stats['total_sales']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">CA total</span>
                            <strong><?php echo number_format($sales_stats['total_revenue'], 2); ?> €</strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">CA moyen/jour</span>
                            <strong><?php echo number_format($sales_stats['avg_revenue'], 2); ?> €</strong>
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Dernière vente</span>
                            <strong>
                                <?php if ($sales_stats['last_sale_date']): ?>
                                    <?php echo date('d/m/Y', strtotime($sales_stats['last_sale_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Jamais</span>
                                <?php endif; ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-question-circle me-2"></i>Aide et support</h5>
                </div>
                <div class="card-body">
                    <p class="small">Besoin d'aide ? Contactez notre équipe support :</p>
                    <div class="d-grid gap-2">
                        <a href="tel:0123456789" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-phone me-2"></i>01 23 45 67 89
                        </a>
                        <a href="mailto:support@drivinCook.fr" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-envelope me-2"></i>support@drivinCook.fr
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirm = this.value;
    
    if (password !== confirm) {
        this.setCustomValidity('Les mots de passe ne correspondent pas');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>