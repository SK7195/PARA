<?php
$pageTitle = 'Mon Espace';
require_once '../includes/header.php';
requireLogin();

if (!isFranchisee()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$pdo = getDBConnection();

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

$stats = [];

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(daily_revenue), 0) 
    FROM sales 
    WHERE franchisee_id = ? AND MONTH(sale_date) = MONTH(CURRENT_DATE)
");
$stmt->execute([$franchisee['id']]);
$stats['monthly_revenue'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(daily_revenue), 0) 
    FROM sales 
    WHERE franchisee_id = ?
");
$stmt->execute([$franchisee['id']]);
$stats['total_revenue'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(commission_due), 0) 
    FROM sales 
    WHERE franchisee_id = ? AND MONTH(sale_date) = MONTH(CURRENT_DATE)
");
$stmt->execute([$franchisee['id']]);
$stats['monthly_commission'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM trucks 
    WHERE franchisee_id = ?
");
$stmt->execute([$franchisee['id']]);
$stats['truck_count'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT so.*, w.name as warehouse_name 
    FROM stock_orders so
    JOIN warehouses w ON so.warehouse_id = w.id
    WHERE so.franchisee_id = ?
    ORDER BY so.order_date DESC 
    LIMIT 5
");
$stmt->execute([$franchisee['id']]);
$recent_orders = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * 
    FROM sales 
    WHERE franchisee_id = ?
    ORDER BY sale_date DESC 
    LIMIT 7
");
$stmt->execute([$franchisee['id']]);
$recent_sales = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * 
    FROM trucks 
    WHERE franchisee_id = ?
");
$stmt->execute([$franchisee['id']]);
$trucks = $stmt->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="fas fa-tachometer-alt me-2"></i>Bonjour <?php echo htmlspecialchars($franchisee['name']); ?>
            </h1>
            <?php if ($franchisee['company_name']): ?>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($franchisee['company_name']); ?></p>
            <?php endif; ?>
        </div>
        <div class="text-muted">
            <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y'); ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card stat-card-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['monthly_revenue'], 0, ',', ' '); ?> €</h3>
                        <p>CA ce mois</p>
                    </div>
                    <i class="fas fa-euro-sign"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card stat-card-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> €</h3>
                        <p>CA total</p>
                    </div>
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card stat-card-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['monthly_commission'], 0, ',', ' '); ?> €</h3>
                        <p>Commission due</p>
                        <small>4% du CA</small>
                    </div>
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['truck_count']; ?></h3>
                        <p>Camion<?php echo $stats['truck_count'] > 1 ? 's' : ''; ?></p>
                    </div>
                    <i class="fas fa-truck"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bolt me-2"></i>Actions rapides</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="sales.php" class="btn btn-primary">
                            <i class="fas fa-cash-register me-2"></i>Saisir mes ventes du jour
                        </a>
                        <a href="orders.php" class="btn btn-success">
                            <i class="fas fa-shopping-cart me-2"></i>Commander du stock
                        </a>
                        <a href="profile.php" class="btn btn-info">
                            <i class="fas fa-user me-2"></i>Modifier mon profil
                        </a>
                        <?php if (!empty($trucks)): ?>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal"
                                data-bs-target="#trucksModal">
                                <i class="fas fa-truck me-2"></i>Voir mes camions
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($trucks)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-truck me-2"></i>Mes camions</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($trucks as $truck): ?>
                            <div class="border rounded p-2 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($truck['license_plate']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($truck['model']); ?></small>
                                    </div>
                                    <span class="badge status-<?php echo $truck['status']; ?>">
                                        <?php
                                        $status_labels = [
                                            'available' => 'Disponible',
                                            'assigned' => 'Assigné',
                                            'maintenance' => 'Maintenance',
                                            'broken' => 'En panne'
                                        ];
                                        echo $status_labels[$truck['status']] ?? $truck['status'];
                                        ?>
                                    </span>
                                </div>
                                <?php if ($truck['location']): ?>
                                    <small class="text-muted">
                                        <i
                                            class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($truck['location']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar me-2"></i>Mes ventes récentes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_sales)): ?>
                        <p class="text-muted text-center">Aucune vente enregistrée</p>
                        <div class="text-center">
                            <a href="sales.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-2"></i>Saisir ma première vente
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_sales as $sale): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <div><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></div>
                                    <small class="text-muted">Commission:
                                        <?php echo number_format($sale['commission_due'], 2); ?> €</small>
                                </div>
                                <div class="text-end">
                                    <strong><?php echo number_format($sale['daily_revenue'], 2); ?> €</strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="sales.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-2"></i>Voir tout l'historique
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shopping-cart me-2"></i>Mes commandes récentes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-muted text-center">Aucune commande passée</p>
                        <div class="text-center">
                            <a href="orders.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus me-2"></i>Passer ma première commande
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['warehouse_name']); ?></strong>
                                        <span class="badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                    <div class="text-end">
                                        <strong><?php echo number_format($order['total_amount'], 2); ?> €</strong>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($order['order_date'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="orders.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-eye me-2"></i>Voir toutes mes commandes
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Informations importantes</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-handshake me-2 text-primary"></i>Rappel des engagements</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Droit d'entrée:
                                    <?php echo number_format($franchisee['entry_fee_paid'], 2); ?> € <span
                                        class="badge bg-success">Payé</span></li>
                                <li><i class="fas fa-percentage text-warning me-2"></i>Commission:
                                    <?php echo $franchisee['commission_rate']; ?>% du CA mensuel</li>
                                <li><i class="fas fa-shopping-basket text-info me-2"></i>Achat obligatoire: 80% du stock
                                    chez Driv'n Cook</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-phone me-2 text-primary"></i>Contacts utiles</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-headset me-2"></i>Support technique: 01 23 45 67 89</li>
                                <li><i class="fas fa-truck me-2"></i>Maintenance camions: 01 23 45 67 90</li>
                                <li><i class="fas fa-envelope me-2"></i>Email support: support@drivinCook.fr</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($trucks)): ?>
    <div class="modal fade" id="trucksModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-truck me-2"></i>Détails de mes camions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Plaque</th>
                                    <th>Modèle</th>
                                    <th>Statut</th>
                                    <th>Emplacement</th>
                                    <th>Dernière maintenance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trucks as $truck): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($truck['license_plate']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($truck['model']); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $truck['status']; ?>">
                                                <?php
                                                $status_labels = [
                                                    'available' => 'Disponible',
                                                    'assigned' => 'Assigné',
                                                    'maintenance' => 'Maintenance',
                                                    'broken' => 'En panne'
                                                ];
                                                echo $status_labels[$truck['status']] ?? $truck['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($truck['location'] ?? 'Non défini'); ?></td>
                                        <td>
                                            <?php if ($truck['last_maintenance']): ?>
                                                <?php echo date('d/m/Y', strtotime($truck['last_maintenance'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Jamais</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>