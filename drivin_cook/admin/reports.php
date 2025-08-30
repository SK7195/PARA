<?php
$pageTitle = 'Rapports et Analyses';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();

if (isset($_GET['generate'])) {
    $type = $_GET['generate'];
    $month = $_GET['month'] ?? date('Y-m');
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="rapport_' . $type . '_' . $month . '.pdf"');
    
    $content = generateReportContent($type, $month, $pdo);
    echo $content;
    exit();
}

function generateReportContent($type, $month, $pdo) {
    $report = "RAPPORT DRIV'N COOK - " . strtoupper($type) . "\n";
    $report .= "Période: " . $month . "\n";
    $report .= "Généré le: " . date('d/m/Y H:i') . "\n";
    $report .= str_repeat("=", 50) . "\n\n";
    
    switch ($type) {
        case 'sales':
            $sales = $pdo->query("
                SELECT f.name, f.company_name, 
                       SUM(s.daily_revenue) as total_revenue,
                       SUM(s.commission_due) as total_commission
                FROM sales s
                JOIN franchisees f ON s.franchisee_id = f.id
                WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = '$month'
                GROUP BY f.id
                ORDER BY total_revenue DESC
            ")->fetchAll();
            
            $report .= "RAPPORT DES VENTES\n\n";
            foreach ($sales as $sale) {
                $report .= "Franchisé: " . $sale['name'] . "\n";
                $report .= "Entreprise: " . ($sale['company_name'] ?? 'N/A') . "\n";
                $report .= "CA Total: " . number_format($sale['total_revenue'], 2) . " €\n";
                $report .= "Commission: " . number_format($sale['total_commission'], 2) . " €\n";
                $report .= str_repeat("-", 30) . "\n";
            }
            break;
            
        case 'trucks':
            $trucks = $pdo->query("
                SELECT t.license_plate, t.model, t.status, 
                       f.name as franchisee_name, t.location
                FROM trucks t
                LEFT JOIN franchisees f ON t.franchisee_id = f.id
                ORDER BY t.status, t.license_plate
            ")->fetchAll();
            
            $report .= "RAPPORT DU PARC DE CAMIONS\n\n";
            foreach ($trucks as $truck) {
                $report .= "Plaque: " . $truck['license_plate'] . "\n";
                $report .= "Modèle: " . $truck['model'] . "\n";
                $report .= "Statut: " . $truck['status'] . "\n";
                $report .= "Franchisé: " . ($truck['franchisee_name'] ?? 'Non assigné') . "\n";
                $report .= "Emplacement: " . ($truck['location'] ?? 'N/A') . "\n";
                $report .= str_repeat("-", 30) . "\n";
            }
            break;
    }
    
    return $report;
}

$monthly_sales = $pdo->query("
    SELECT DATE_FORMAT(sale_date, '%Y-%m') as month,
           SUM(daily_revenue) as revenue,
           SUM(commission_due) as commission
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month
")->fetchAll();

$franchisee_performance = $pdo->query("
    SELECT f.name, f.company_name,
           COUNT(DISTINCT s.sale_date) as active_days,
           SUM(s.daily_revenue) as total_revenue,
           AVG(s.daily_revenue) as avg_revenue
    FROM franchisees f
    LEFT JOIN sales s ON f.id = s.franchisee_id 
        AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    WHERE f.status = 'active'
    GROUP BY f.id
    ORDER BY total_revenue DESC
")->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-chart-bar me-2"></i>Rapports et Analyses</h1>
        <div class="btn-group">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-2"></i>Générer rapport
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="?generate=sales&month=<?php echo date('Y-m'); ?>">
                    <i class="fas fa-euro-sign me-2"></i>Rapport des ventes
                </a></li>
                <li><a class="dropdown-item" href="?generate=trucks">
                    <i class="fas fa-truck me-2"></i>Rapport du parc de camions
                </a></li>
            </ul>
        </div>
    </div>

    <div class="row mb-4">
        <?php
        $current_month_revenue = $pdo->query("
            SELECT COALESCE(SUM(daily_revenue), 0) FROM sales 
            WHERE MONTH(sale_date) = MONTH(CURRENT_DATE)
        ")->fetchColumn();
        
        $last_month_revenue = $pdo->query("
            SELECT COALESCE(SUM(daily_revenue), 0) FROM sales 
            WHERE MONTH(sale_date) = MONTH(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
        ")->fetchColumn();
        
        $growth = $last_month_revenue > 0 ? (($current_month_revenue - $last_month_revenue) / $last_month_revenue) * 100 : 0;
        
        $active_franchisees = $pdo->query("
            SELECT COUNT(DISTINCT franchisee_id) FROM sales 
            WHERE MONTH(sale_date) = MONTH(CURRENT_DATE)
        ")->fetchColumn();
        
        $total_commission = $pdo->query("
            SELECT COALESCE(SUM(commission_due), 0) FROM sales 
            WHERE MONTH(sale_date) = MONTH(CURRENT_DATE)
        ")->fetchColumn();
        ?>
        
        <div class="col-md-3">
            <div class="stat-card stat-card-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($current_month_revenue, 0, ',', ' '); ?> €</h3>
                        <p>CA du mois</p>
                        <small class="<?php echo $growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas fa-arrow-<?php echo $growth >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo number_format(abs($growth), 1); ?>% vs mois dernier
                        </small>
                    </div>
                    <i class="fas fa-euro-sign"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card stat-card-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $active_franchisees; ?></h3>
                        <p>Franchisés actifs</p>
                        <small class="text-muted">Ce mois-ci</small>
                    </div>
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card stat-card-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($total_commission, 0, ',', ' '); ?> €</h3>
                        <p>Commissions</p>
                        <small class="text-muted">4% du CA</small>
                    </div>
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($current_month_revenue ? $current_month_revenue / max($active_franchisees, 1) : 0, 0); ?> €</h3>
                        <p>CA moyen</p>
                        <small class="text-muted">Par franchisé</small>
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
                    <h5><i class="fas fa-chart-line me-2"></i>Évolution des ventes (12 derniers mois)</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="100"></canvas>
                    <script>
              
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('salesChart');
                            const data = <?php echo json_encode($monthly_sales); ?>;
                            
                            let chartHtml = '<div class="row text-center">';
                            data.forEach(item => {
                                const height = Math.min((item.revenue / 50000) * 200, 200);
                                chartHtml += `
                                    <div class="col">
                                        <div style="background: linear-gradient(to top, #007bff, #0056b3); 
                                                    height: ${height}px; margin-bottom: 10px; border-radius: 4px;"></div>
                                        <small>${item.month}</small><br>
                                        <small>${Math.round(item.revenue)} €</small>
                                    </div>
                                `;
                            });
                            chartHtml += '</div>';
                            
                            ctx.parentNode.innerHTML = chartHtml;
                        });
                    </script>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-trophy me-2"></i>Top Franchisés (3 mois)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($franchisee_performance)): ?>
                        <p class="text-muted text-center">Aucune donnée disponible</p>
                    <?php else: ?>
                        <?php foreach (array_slice($franchisee_performance, 0, 5) as $index => $perf): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <?php if ($index < 3): ?>
                                        <span class="badge bg-<?php echo ['warning', 'secondary', 'dark'][$index]; ?> rounded-pill">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark rounded-pill"><?php echo $index + 1; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($perf['name']); ?></div>
                                    <small class="text-muted">
                                        <?php echo number_format($perf['total_revenue'], 0); ?> € 
                                        (<?php echo $perf['active_days']; ?> jours actifs)
                                    </small>
                                    <div class="progress mt-1" style="height: 6px;">
                                        <div class="progress-bar bg-primary" 
                                             style="width: <?php echo min(($perf['total_revenue'] / 100000) * 100, 100); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-tools me-2"></i>Actions rapides</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <form method="GET" class="d-flex">
                            <select name="month" class="form-select me-2">
                                <?php for ($i = 0; $i < 12; $i++): 
                                    $month = date('Y-m', strtotime("-$i month"));
                                ?>
                                    <option value="<?php echo $month; ?>" <?php echo $i === 0 ? 'selected' : ''; ?>>
                                        <?php echo date('F Y', strtotime($month . '-01')); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" name="generate" value="sales" class="btn btn-sm btn-primary">
                                <i class="fas fa-download"></i>
                            </button>
                        </form>
                        
                        <a href="../admin/dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                        </a>
                        
                        <a href="../admin/franchises.php" class="btn btn-outline-success">
                            <i class="fas fa-users me-2"></i>Gérer franchisés
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>