<?php
require_once '../config/database.php';
requireAdmin(); 

$pdo = getDBConnection();

$type = $_GET['type'] ?? 'sales';
$format = $_GET['format'] ?? 'pdf';
$month = $_GET['month'] ?? date('Y-m');
$franchisee_id = $_GET['franchisee_id'] ?? null;

if ($format === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="rapport_' . $type . '_' . $month . '.pdf"');
} else {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_' . $type . '_' . $month . '.txt"');
}
function generateReportContent($type, $month, $franchisee_id, $pdo) {
    $report = "";
    $report .= "╔══════════════════════════════════════════════════════════════════╗\n";
    $report .= "║                        DRIV'N COOK                               ║\n";
    $report .= "║                 RAPPORT " . strtoupper($type) . "                                    ║\n";
    $report .= "╚══════════════════════════════════════════════════════════════════╝\n\n";
    
    $report .= "Période: " . date('F Y', strtotime($month . '-01')) . "\n";
    $report .= "Généré le: " . date('d/m/Y à H:i:s') . "\n";
    $report .= "Par: " . ($_SESSION['user_email'] ?? 'Administrateur') . "\n";
    $report .= str_repeat("=", 70) . "\n\n";
    
    switch ($type) {
        case 'sales':
            $report .= generateSalesReport($month, $franchisee_id, $pdo);
            break;
            
        case 'trucks':
            $report .= generateTrucksReport($pdo);
            break;
            
        case 'stocks':
            $report .= generateStocksReport($pdo);
            break;
            
        case 'franchisees':
            $report .= generateFranchiseesReport($month, $pdo);
            break;
            
        case 'commissions':
            $report .= generateCommissionsReport($month, $pdo);
            break;
            
        default:
            $report .= "Type de rapport non reconnu.\n";
    }
    
    $report .= "\n" . str_repeat("=", 70) . "\n";
    $report .= "Fin du rapport - Driv'n Cook © " . date('Y') . "\n";
    
    return $report;
}

function generateSalesReport($month, $franchisee_id, $pdo) {
    $report = "RAPPORT DES VENTES\n\n";

    $whereClause = "WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ?";
    $params = [$month];
    
    if ($franchisee_id) {
        $whereClause .= " AND s.franchisee_id = ?";
        $params[] = $franchisee_id;
 
        $stmt = $pdo->prepare("SELECT name, company_name FROM franchisees WHERE id = ?");
        $stmt->execute([$franchisee_id]);
        $franchisee = $stmt->fetch();
        
        if ($franchisee) {
            $report .= "Franchisé: " . $franchisee['name'];
            if ($franchisee['company_name']) {
                $report .= " - " . $franchisee['company_name'];
            }
            $report .= "\n\n";
        }
    }

    $stmt = $pdo->prepare("
        SELECT f.name, f.company_name,
               COUNT(s.id) as nb_ventes,
               SUM(s.daily_revenue) as total_revenue,
               AVG(s.daily_revenue) as avg_revenue,
               SUM(s.commission_due) as total_commission,
               MIN(s.sale_date) as first_sale,
               MAX(s.sale_date) as last_sale
        FROM sales s
        JOIN franchisees f ON s.franchisee_id = f.id
        {$whereClause}
        GROUP BY f.id
        ORDER BY total_revenue DESC
    ");
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
    
    if (empty($sales)) {
        $report .= "Aucune vente enregistrée pour cette période.\n";
        return $report;
    }
    $total_revenue = array_sum(array_column($sales, 'total_revenue'));
    $total_commission = array_sum(array_column($sales, 'total_commission'));
    $total_sales = array_sum(array_column($sales, 'nb_ventes'));
    
    $report .= "RÉSUMÉ EXÉCUTIF:\n";
    $report .= "- Chiffre d'affaires total: " . number_format($total_revenue, 2, ',', ' ') . " €\n";
    $report .= "- Commissions totales: " . number_format($total_commission, 2, ',', ' ') . " €\n";
    $report .= "- Nombre de ventes: " . $total_sales . "\n";
    $report .= "- Nombre de franchisés actifs: " . count($sales) . "\n";
    $report .= "- CA moyen par franchisé: " . number_format($total_revenue / count($sales), 2, ',', ' ') . " €\n\n";
    $report .= "DÉTAIL PAR FRANCHISÉ:\n";
    $report .= str_repeat("-", 70) . "\n";
    
    foreach ($sales as $sale) {
        $report .= "\n" . strtoupper($sale['name']);
        if ($sale['company_name']) {
            $report .= " - " . $sale['company_name'];
        }
        $report .= "\n";
        
        $report .= "  • Nombre de ventes: " . $sale['nb_ventes'] . "\n";
        $report .= "  • CA total: " . number_format($sale['total_revenue'], 2, ',', ' ') . " €\n";
        $report .= "  • CA moyen/jour: " . number_format($sale['avg_revenue'], 2, ',', ' ') . " €\n";
        $report .= "  • Commission due: " . number_format($sale['total_commission'], 2, ',', ' ') . " €\n";
        $report .= "  • Première vente: " . date('d/m/Y', strtotime($sale['first_sale'])) . "\n";
        $report .= "  • Dernière vente: " . date('d/m/Y', strtotime($sale['last_sale'])) . "\n";

        $performance = ($sale['total_revenue'] / $total_revenue) * 100;
        $report .= "  • Part du CA total: " . number_format($performance, 1) . "%\n";
        
        $report .= str_repeat("-", 50) . "\n";
    }
    
    return $report;
}

function generateTrucksReport($pdo) {
    $report = "RAPPORT DU PARC DE CAMIONS\n\n";

    $stats = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM trucks
        GROUP BY status
    ")->fetchAll();
    
    $total = 0;
    $report .= "ÉTAT DU PARC:\n";
    foreach ($stats as $stat) {
        $total += $stat['count'];
        $status_fr = [
            'available' => 'Disponibles',
            'assigned' => 'Assignés',
            'maintenance' => 'En maintenance',
            'broken' => 'En panne'
        ][$stat['status']] ?? $stat['status'];
        
        $report .= "- " . $status_fr . ": " . $stat['count'] . "\n";
    }
    $report .= "- TOTAL: " . $total . " camions\n\n";

    $trucks = $pdo->query("
        SELECT t.*, f.name as franchisee_name, f.company_name,
               DATEDIFF(CURRENT_DATE, COALESCE(t.last_maintenance, t.created_at)) as days_since_maintenance
        FROM trucks t
        LEFT JOIN franchisees f ON t.franchisee_id = f.id
        ORDER BY t.status, t.license_plate
    ")->fetchAll();
    
    $report .= "INVENTAIRE DÉTAILLÉ:\n";
    $report .= str_repeat("-", 70) . "\n";
    
    $current_status = '';
    foreach ($trucks as $truck) {
        if ($current_status !== $truck['status']) {
            $current_status = $truck['status'];
            $status_fr = [
                'available' => 'CAMIONS DISPONIBLES',
                'assigned' => 'CAMIONS ASSIGNÉS',
                'maintenance' => 'CAMIONS EN MAINTENANCE',
                'broken' => 'CAMIONS EN PANNE'
            ][$current_status] ?? strtoupper($current_status);
            
            $report .= "\n" . $status_fr . ":\n";
        }
        
        $report .= "\n  Plaque: " . $truck['license_plate'] . "\n";
        $report .= "  Modèle: " . $truck['model'] . "\n";
        
        if ($truck['franchisee_name']) {
            $report .= "  Franchisé: " . $truck['franchisee_name'];
            if ($truck['company_name']) {
                $report .= " (" . $truck['company_name'] . ")";
            }
            $report .= "\n";
        }
        
        if ($truck['location']) {
            $report .= "  Emplacement: " . $truck['location'] . "\n";
        }
        
        if ($truck['last_maintenance']) {
            $report .= "  Dernière maintenance: " . date('d/m/Y', strtotime($truck['last_maintenance']));
            $report .= " (" . $truck['days_since_maintenance'] . " jours)\n";
        } else {
            $report .= "  Maintenance: Jamais (" . $truck['days_since_maintenance'] . " jours depuis création)\n";
        }
        if ($truck['days_since_maintenance'] > 90) {
            $report .= "  ⚠️  ATTENTION: Maintenance recommandée\n";
        }
        
        $report .= str_repeat(".", 50) . "\n";
    }
    
    return $report;
}
function generateStocksReport($pdo) {
    $report = "RAPPORT DES STOCKS\n\n";

    $warehouses = $pdo->query("
        SELECT w.name, w.manager_name,
               COUNT(p.id) as product_count,
               SUM(p.stock_quantity) as total_items,
               SUM(p.stock_quantity * p.price) as total_value,
               COUNT(CASE WHEN p.stock_quantity < 10 THEN 1 END) as low_stock_items
        FROM warehouses w
        LEFT JOIN products p ON w.id = p.warehouse_id
        GROUP BY w.id
        ORDER BY w.name
    ")->fetchAll();
    
    $report .= "ÉTAT DES ENTREPÔTS:\n";
    $report .= str_repeat("-", 70) . "\n";
    
    $total_value = 0;
    $total_items = 0;
    
    foreach ($warehouses as $warehouse) {
        $report .= "\n" . strtoupper($warehouse['name']) . "\n";
        $report .= "Manager: " . $warehouse['manager_name'] . "\n";
        $report .= "Produits référencés: " . $warehouse['product_count'] . "\n";
        $report .= "Articles en stock: " . number_format($warehouse['total_items'], 0, ',', ' ') . "\n";
        $report .= "Valeur du stock: " . number_format($warehouse['total_value'], 2, ',', ' ') . " €\n";
        
        if ($warehouse['low_stock_items'] > 0) {
            $report .= "⚠️  Produits en stock faible: " . $warehouse['low_stock_items'] . "\n";
        }
        
        $total_value += $warehouse['total_value'];
        $total_items += $warehouse['total_items'];
        
        $report .= str_repeat("-", 50) . "\n";
    }
    
    $report .= "\nTOTAL GÉNÉRAL:\n";
    $report .= "Valeur totale des stocks: " . number_format($total_value, 2, ',', ' ') . " €\n";
    $report .= "Articles totaux: " . number_format($total_items, 0, ',', ' ') . "\n\n";

    $low_stock = $pdo->query("
        SELECT p.name, p.stock_quantity, p.price, w.name as warehouse_name
        FROM products p
        JOIN warehouses w ON p.warehouse_id = w.id
        WHERE p.stock_quantity < 10
        ORDER BY p.stock_quantity ASC, w.name
    ")->fetchAll();
    
    if (!empty($low_stock)) {
        $report .= "ALERTES STOCK FAIBLE:\n";
        $report .= str_repeat("-", 70) . "\n";
        
        foreach ($low_stock as $product) {
            $report .= "• " . $product['name'] . " (" . $product['warehouse_name'] . ")\n";
            $report .= "  Stock restant: " . $product['stock_quantity'] . " unités\n";
            $report .= "  Prix unitaire: " . number_format($product['price'], 2) . " €\n\n";
        }
    }
    
    return $report;
}
function generateFranchiseesReport($month, $pdo) {
    $report = "RAPPORT DES FRANCHISÉS\n\n";
    
    $franchisees = $pdo->query("
        SELECT f.*, u.email,
               COUNT(DISTINCT t.id) as truck_count,
               COUNT(DISTINCT s.id) as sales_count,
               COALESCE(SUM(s.daily_revenue), 0) as total_revenue
        FROM franchisees f
        JOIN users u ON f.user_id = u.id
        LEFT JOIN trucks t ON f.id = t.franchisee_id
        LEFT JOIN sales s ON f.id = s.franchisee_id 
            AND DATE_FORMAT(s.sale_date, '%Y-%m') = '{$month}'
        GROUP BY f.id
        ORDER BY f.status, total_revenue DESC
    ")->fetchAll();
    
    $active_count = 0;
    $inactive_count = 0;
    
    foreach ($franchisees as $franchisee) {
        if ($franchisee['status'] === 'active') {
            $active_count++;
        } else {
            $inactive_count++;
        }
    }
    
    $report .= "RÉSUMÉ:\n";
    $report .= "- Franchisés actifs: " . $active_count . "\n";
    $report .= "- Franchisés inactifs: " . $inactive_count . "\n";
    $report .= "- Total: " . count($franchisees) . "\n\n";
    
    $report .= "LISTE DÉTAILLÉE:\n";
    $report .= str_repeat("-", 70) . "\n";
    
    $current_status = '';
    foreach ($franchisees as $franchisee) {
        if ($current_status !== $franchisee['status']) {
            $current_status = $franchisee['status'];
            $status_fr = $current_status === 'active' ? 'FRANCHISÉS ACTIFS' : 'FRANCHISÉS INACTIFS';
            $report .= "\n" . $status_fr . ":\n";
        }
        
        $report .= "\n" . strtoupper($franchisee['name']) . "\n";
        if ($franchisee['company_name']) {
            $report .= "Entreprise: " . $franchisee['company_name'] . "\n";
        }
        $report .= "Email: " . $franchisee['email'] . "\n";
        $report .= "Téléphone: " . ($franchisee['phone'] ?: 'Non renseigné') . "\n";
        $report .= "Inscrit le: " . date('d/m/Y', strtotime($franchisee['created_at'])) . "\n";
        $report .= "Camions assignés: " . $franchisee['truck_count'] . "\n";
        $report .= "Ventes ce mois: " . $franchisee['sales_count'] . "\n";
        $report .= "CA ce mois: " . number_format($franchisee['total_revenue'], 2, ',', ' ') . " €\n";
        
        if ($franchisee['address']) {
            $report .= "Adresse: " . str_replace("\n", " ", $franchisee['address']) . "\n";
        }
        
        $report .= str_repeat(".", 50) . "\n";
    }
    
    return $report;
}

function generateCommissionsReport($month, $pdo) {
    $report = "RAPPORT DES COMMISSIONS\n\n";
    
    $commissions = $pdo->prepare("
        SELECT f.name, f.company_name, f.commission_rate,
               SUM(s.daily_revenue) as total_revenue,
               SUM(s.commission_due) as total_commission,
               COUNT(s.id) as nb_ventes
        FROM sales s
        JOIN franchisees f ON s.franchisee_id = f.id
        WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ?
        GROUP BY f.id
        ORDER BY total_commission DESC
    ");
    $commissions->execute([$month]);
    $commissions = $commissions->fetchAll();
    
    if (empty($commissions)) {
        $report .= "Aucune commission calculée pour cette période.\n";
        return $report;
    }
    
    $total_revenue = array_sum(array_column($commissions, 'total_revenue'));
    $total_commission = array_sum(array_column($commissions, 'total_commission'));
    
    $report .= "RÉSUMÉ DES COMMISSIONS:\n";
    $report .= "- CA total franchisés: " . number_format($total_revenue, 2, ',', ' ') . " €\n";
    $report .= "- Commissions totales dues: " . number_format($total_commission, 2, ',', ' ') . " €\n";
    $report .= "- Taux moyen: " . number_format(($total_commission / $total_revenue) * 100, 2) . "%\n\n";
    
    $report .= "DÉTAIL PAR FRANCHISÉ:\n";
    $report .= str_repeat("-", 70) . "\n";
    
    foreach ($commissions as $commission) {
        $report .= "\n" . strtoupper($commission['name']);
        if ($commission['company_name']) {
            $report .= " - " . $commission['company_name'];
        }
        $report .= "\n";
        
        $report .= "Taux de commission: " . $commission['commission_rate'] . "%\n";
        $report .= "Nombre de ventes: " . $commission['nb_ventes'] . "\n";
        $report .= "CA réalisé: " . number_format($commission['total_revenue'], 2, ',', ' ') . " €\n";
        $report .= "Commission due: " . number_format($commission['total_commission'], 2, ',', ' ') . " €\n";
        
        $report .= str_repeat("-", 50) . "\n";
    }
    
    return $report;
}

echo generateReportContent($type, $month, $franchisee_id, $pdo);
?>