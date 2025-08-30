<?php
$pageTitle = 'Mes Ventes';
require_once '../includes/header.php';
requireLogin();

if (!isFranchisee()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$pdo = getDBConnection();
$error = $success = '';

$stmt = $pdo->prepare("SELECT id, commission_rate FROM franchisees WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$franchisee_data = $stmt->fetch();

if (!$franchisee_data) {
    $_SESSION['error'] = 'Franchisé non trouvé';
    header('Location: ../logout.php');
    exit();
}

$franchisee_id = $franchisee_data['id'];
$commission_rate = $franchisee_data['commission_rate'];

if ($_POST) {
    $sale_date = $_POST['sale_date'] ?? '';
    $daily_revenue = $_POST['daily_revenue'] ?? 0;
    
    if ($sale_date && $daily_revenue > 0) {
        
        $stmt = $pdo->prepare("SELECT id FROM sales WHERE franchisee_id = ? AND sale_date = ?");
        $stmt->execute([$franchisee_id, $sale_date]);
        $existing_sale = $stmt->fetchColumn();
        
        $commission_due = ($daily_revenue * $commission_rate) / 100;
        
        if ($existing_sale) {
  
            $stmt = $pdo->prepare("UPDATE sales SET daily_revenue = ?, commission_due = ? WHERE id = ?");
            if ($stmt->execute([$daily_revenue, $commission_due, $existing_sale])) {
                $success = 'Vente du ' . date('d/m/Y', strtotime($sale_date)) . ' mise à jour';
            } else {
                $error = 'Erreur lors de la mise à jour';
            }
        } else {

            $stmt = $pdo->prepare("INSERT INTO sales (franchisee_id, sale_date, daily_revenue, commission_due) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$franchisee_id, $sale_date, $daily_revenue, $commission_due])) {
                $success = 'Vente enregistrée avec succès';
            } else {
                $error = 'Erreur lors de l\'enregistrement';
            }
        }
    } else {
        $error = 'Date et chiffre d\'affaires obligatoires';
    }
}

if (isset($_GET['delete'])) {
    $sale_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ? AND franchisee_id = ?");
    if ($stmt->execute([$sale_id, $franchisee_id])) {
        $_SESSION['success'] = 'Vente supprimée avec succès';
    } else {
        $_SESSION['error'] = 'Erreur lors de la suppression';
    }
    header('Location: sales.php');
    exit();
}

$current_month = date('Y-m');
$filter_month = $_GET['month'] ?? $current_month;

$stmt = $pdo->prepare("
    SELECT * FROM sales 
    WHERE franchisee_id = ? AND DATE_FORMAT(sale_date, '%Y-%m') = ?
    ORDER BY sale_date DESC
");
$stmt->execute([$franchisee_id, $filter_month]);
$monthly_sales = $stmt->fetchAll();

$stats = [
    'monthly_revenue' => array_sum(array_column($monthly_sales, 'daily_revenue')),
    'monthly_commission' => array_sum(array_column($monthly_sales, 'commission_due')),
    'sales_days' => count($monthly_sales),
    'avg_daily_revenue' => count($monthly_sales) > 0 ? array_sum(array_column($monthly_sales, 'daily_revenue')) / count($monthly_sales) : 0
];

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM sales WHERE franchisee_id = ? AND sale_date = ?");
$stmt->execute([$franchisee_id, $today]);
$today_sale = $stmt->fetch();

$monthly_evolution = $pdo->prepare("
    SELECT DATE_FORMAT(sale_date, '%Y-%m') as month,
           SUM(daily_revenue) as revenue,
           SUM(commission_due) as commission,
           COUNT(*) as days
    FROM sales
    WHERE franchisee_id = ? 
    AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month
");
$monthly_evolution->execute([$franchisee_id]);
$evolution_data = $monthly_evolution->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-cash-register me-2"></i>Mes Ventes</h1>
        <div class="d-flex align-items-center">
          
            <form method="GET" class="me-3">
                <select name="month" class="form-select" onchange="this.form.submit()">
                    <?php for ($i = 0; $i < 12; $i++): 
                        $month_value = date('Y-m', strtotime("-$i month"));
                        $month_label = date('F Y', strtotime($month_value . '-01'));
                    ?>
                        <option value="<?php echo $month_value; ?>" <?php echo $month_value === $filter_month ? 'selected' : ''; ?>>
                            <?php echo $month_label; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </form>
            
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                <i class="fas fa-plus me-2"></i>Nouvelle vente
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card stat-card-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['monthly_revenue'], 0, ',', ' '); ?> €</h3>
                        <p>CA du mois</p>
                    </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addSaleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-cash-register me-2"></i>
                    <span id="modalTitle">Enregistrer une vente</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="sale_date" class="form-label">Date de la vente *</label>
                        <input type="date" class="form-control" id="sale_date" name="sale_date" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                        <div class="form-text">Vous ne pouvez pas enregistrer de ventes futures</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="daily_revenue" class="form-label">Chiffre d'affaires de la journée (€) *</label>
                        <input type="number" class="form-control" id="daily_revenue" name="daily_revenue" 
                               step="0.01" min="0" required data-revenue="main">
                        <div class="form-text">Saisissez le montant total des ventes de la journée</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commission_preview" class="form-label">Commission à verser (<?php echo $commission_rate; ?>%)</label>
                        <input type="text" class="form-control bg-light" id="commission_preview" 
                               data-commission-for="main" readonly>
                        <div class="form-text">Commission calculée automatiquement</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-2"></i>
                            La commission de <?php echo $commission_rate; ?>% sera automatiquement calculée et enregistrée.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

function setTodayDate() {
    document.getElementById('sale_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('modalTitle').textContent = 'Enregistrer la vente d\'aujourd\'hui';
}

function editSale(date, revenue) {
    document.getElementById('sale_date').value = date;
    document.getElementById('daily_revenue').value = revenue;
    document.getElementById('modalTitle').textContent = 'Modifier la vente du ' + formatDate(date);
    
    const commission = (revenue * <?php echo $commission_rate; ?>) / 100;
    document.getElementById('commission_preview').value = commission.toFixed(2) + ' €';
    
    const modal = new bootstrap.Modal(document.getElementById('addSaleModal'));
    modal.show();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR');
}

document.getElementById('daily_revenue').addEventListener('input', function() {
    const revenue = parseFloat(this.value) || 0;
    const commission = (revenue * <?php echo $commission_rate; ?>) / 100;
    document.getElementById('commission_preview').value = commission.toFixed(2) + ' €';
});

document.getElementById('addSaleModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modalTitle').textContent = 'Enregistrer une vente';
    document.querySelector('#addSaleModal form').reset();
    document.getElementById('commission_preview').value = '';
});

document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('sale_date').value) {
        setTodayDate();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
                    <i class="fas fa-euro-sign"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card stat-card-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['monthly_commission'], 2); ?> €</h3>
                        <p>Commission due</p>
                        <small><?php echo $commission_rate; ?>% du CA</small>
                    </div>
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card stat-card-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['sales_days']; ?></h3>
                        <p>Jours de vente</p>
                    </div>
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['avg_daily_revenue'], 0); ?> €</h3>
                        <p>CA moyen/jour</p>
                    </div>
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Ventes de <?php echo date('F Y', strtotime($filter_month . '-01')); ?> (<?php echo count($monthly_sales); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($monthly_sales)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-cash-register fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Aucune vente enregistrée pour ce mois</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                                <i class="fas fa-plus me-2"></i>Enregistrer ma première vente
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Chiffre d'affaires</th>
                                        <th>Commission</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_sales as $sale): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></strong>
                                                <br><small class="text-muted"><?php echo date('l', strtotime($sale['sale_date'])); ?></small>
                                            </td>
                                            <td><strong><?php echo number_format($sale['daily_revenue'], 2); ?> €</strong></td>
                                            <td><?php echo number_format($sale['commission_due'], 2); ?> €</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="editSale('<?php echo $sale['sale_date']; ?>', <?php echo $sale['daily_revenue']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete=<?php echo $sale['id']; ?>" 
                                                   class="btn btn-sm btn-danger btn-delete" 
                                                   data-name="la vente du <?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
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
                    <h5><i class="fas fa-calendar-day me-2"></i>Aujourd'hui</h5>
                </div>
                <div class="card-body">
                    <?php if ($today_sale): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Vente enregistrée</h6>
                            <p class="mb-1">CA : <strong><?php echo number_format($today_sale['daily_revenue'], 2); ?> €</strong></p>
                            <p class="mb-0">Commission : <strong><?php echo number_format($today_sale['commission_due'], 2); ?> €</strong></p>
                        </div>
                        <button type="button" class="btn btn-warning btn-sm" 
                                onclick="editSale('<?php echo $today; ?>', <?php echo $today_sale['daily_revenue']; ?>)">
                            <i class="fas fa-edit me-2"></i>Modifier
                        </button>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p class="mb-0">Vous n'avez pas encore enregistré votre vente d'aujourd'hui.</p>
                        </div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal" 
                                onclick="setTodayDate()">
                            <i class="fas fa-plus me-2"></i>Enregistrer maintenant
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>Évolution (6 mois)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($evolution_data)): ?>
                        <div class="row text-center">
                            <?php 
                            $recent_months = array_slice($evolution_data, -6);
                            foreach ($recent_months as $month): 
                            ?>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted"><?php echo date('M Y', strtotime($month['month'] . '-01')); ?></small>
                                        <div><strong><?php echo number_format($month['revenue'], 0); ?> €</strong></div>
                                        <small class="text-muted"><?php echo $month['days']; ?> jour<?php echo $month['days'] > 1 ? 's' : ''; ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Pas assez de données</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-lightbulb me-2"></i>Conseils</h5>
                </div>
                <div class="card-body">
                    <div class="small">
                        <p><i class="fas fa-check text-success me-2"></i>Enregistrez vos ventes quotidiennement</p>
                        <p><i class="fas fa-chart-bar text-info me-2"></i>Analysez vos performances mensuelles</p>
                        <p><i class="fas fa-percentage text-warning me-2"></i>Commission automatiquement calculée à <?php echo $commission_rate; ?>%</p>
                        <p class="mb-0"><i class="fas fa-phone text-primary me-2"></i>Support : 01 23 45 67 89</p>
                    </div>
                </div>
            </div>