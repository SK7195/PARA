<?php
$pageTitle = 'Tableau de bord';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();

$stats = [
    'franchisees' => $pdo->query("SELECT COUNT(*) FROM franchisees WHERE status = 'active'")->fetchColumn(),
    'trucks' => $pdo->query("SELECT COUNT(*) FROM trucks")->fetchColumn(),
    'available_trucks' => $pdo->query("SELECT COUNT(*) FROM trucks WHERE status = 'available'")->fetchColumn(),
    'total_revenue' => $pdo->query("SELECT COALESCE(SUM(daily_revenue), 0) FROM sales WHERE MONTH(sale_date) = MONTH(CURRENT_DATE)")->fetchColumn()
];

$recent_franchisees = $pdo->query("
    SELECT f.*, u.email 
    FROM franchisees f 
    JOIN users u ON f.user_id = u.id 
    ORDER BY f.created_at DESC 
    LIMIT 5
")->fetchAll();

$monthly_commissions = $pdo->query("
    SELECT COALESCE(SUM(commission_due), 0) as total_commission
    FROM sales 
    WHERE MONTH(sale_date) = MONTH(CURRENT_DATE)
")->fetchColumn();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-tachometer-alt me-2"></i>Tableau de bord</h1>
        <div class="text-muted">
            <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y'); ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card stat-card-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['franchisees']; ?></h3>
                        <p>Franchisés actifs</p>
                    </div>
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card stat-card-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['available_trucks']; ?>/<?php echo $stats['trucks']; ?></h3>
                        <p>Camions disponibles</p>
                    </div>
                    <i class="fas fa-truck"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card stat-card-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> €</h3>
                        <p>CA du mois</p>
                    </div>
                    <i class="fas fa-euro-sign"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($monthly_commissions, 0, ',', ' '); ?> €</h3>
                        <p>Commissions du mois</p>
                    </div>
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users me-2"></i>Franchisés récents</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_franchisees)): ?>
                        <p class="text-muted text-center">Aucun franchisé enregistré</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Entreprise</th>
                                        <th>Email</th>
                                        <th>Statut</th>
                                        <th>Date d'inscription</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_franchisees as $franchisee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($franchisee['name']); ?></td>
                                            <td><?php echo htmlspecialchars($franchisee['company_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($franchisee['email']); ?></td>
                                            <td>
                                                <span class="badge status-<?php echo $franchisee['status']; ?>">
                                                    <?php echo ucfirst($franchisee['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($franchisee['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bolt me-2"></i>Actions rapides</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="franchises.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Nouveau franchisé
                        </a>
                        <a href="trucks.php?action=add" class="btn btn-success">
                            <i class="fas fa-truck me-2"></i>Nouveau camion
                        </a>
                        <a href="reports.php" class="btn btn-info">
                            <i class="fas fa-chart-bar me-2"></i>Générer rapport
                        </a>
                        <a href="stocks.php" class="btn btn-warning">
                            <i class="fas fa-boxes me-2"></i>Gérer stocks
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Alertes</h5>
                </div>
                <div class="card-body">
                    <?php
                    $broken_trucks = $pdo->query("SELECT COUNT(*) FROM trucks WHERE status = 'broken'")->fetchColumn();
                    $maintenance_trucks = $pdo->query("SELECT COUNT(*) FROM trucks WHERE status = 'maintenance'")->fetchColumn();
                    ?>

                    <?php if ($broken_trucks > 0): ?>
                        <div class="alert alert-danger py-2">
                            <small><i class="fas fa-exclamation-circle me-2"></i><?php echo $broken_trucks; ?> camion(s) en
                                panne</small>
                        </div>
                    <?php endif; ?>

                    <?php if ($maintenance_trucks > 0): ?>
                        <div class="alert alert-warning py-2">
                            <small><i class="fas fa-wrench me-2"></i><?php echo $maintenance_trucks; ?> camion(s) en
                                maintenance</small>
                        </div>
                    <?php endif; ?>

                    <?php if ($broken_trucks == 0 && $maintenance_trucks == 0): ?>
                        <div class="alert alert-success py-2">
                            <small><i class="fas fa-check me-2"></i>Tous les systèmes fonctionnent</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>