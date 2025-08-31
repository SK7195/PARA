<?php
// classes/PDFManager.php

require_once 'fpdf/fpdf.php';

class PDFManager extends FPDF {
    private $pdo;
    
    public function __construct($pdo) {
        parent::__construct();
        $this->pdo = $pdo;
    }
    
    // En-tête personnalisé
    function Header() {
        // Logo (si vous en avez un)
        // $this->Image('logo.png', 10, 6, 30);
        
        // Police Arial gras 15
        $this->SetFont('Arial', 'B', 15);
        
        // Décalage à droite
        $this->Cell(80);
        
        // Titre
        $this->Cell(30, 10, 'DRIV\'N COOK - RAPPORT', 0, 0, 'C');
        
        // Saut de ligne
        $this->Ln(20);
    }
    
    // Pied de page
    function Footer() {
        // Positionnement à 1,5 cm du bas
        $this->SetY(-15);
        
        // Police Arial italique 8
        $this->SetFont('Arial', 'I', 8);
        
        // Numéro de page
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        
        // Date de génération
        $this->SetY(-15);
        $this->Cell(0, 10, 'Genere le ' . date('d/m/Y H:i'), 0, 0, 'L');
        
        // Contact
        $this->Cell(0, 10, 'contact@drivinCook.fr - 01 23 45 67 89', 0, 0, 'R');
    }
    
    public function generateSalesReport($month = null) {
        if (!$month) {
            $month = date('Y-m');
        }
        
        $this->AliasNbPages();
        $this->AddPage();
        
        // Titre du rapport
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'RAPPORT DES VENTES', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Periode: ' . date('F Y', strtotime($month . '-01')), 0, 1, 'C');
        $this->Ln(10);
        
        // Récupérer les données
        $salesData = $this->getSalesData($month);
        $this->addSalesTable($salesData);
        
        // Statistiques globales
        $this->addSalesStats($salesData);
        
        return $this->Output('S'); // Retourne le PDF en string
    }
    
    public function generateTrucksReport() {
        $this->AliasNbPages();
        $this->AddPage();
        
        // Titre du rapport
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'RAPPORT DU PARC DE CAMIONS', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Date: ' . date('d/m/Y'), 0, 1, 'C');
        $this->Ln(10);
        
        // Récupérer les données
        $trucksData = $this->getTrucksData();
        $this->addTrucksTable($trucksData);
        
        // Statistiques globales
        $this->addTrucksStats($trucksData);
        
        return $this->Output('S');
    }
    
    public function generateFranchiseesReport($month = null) {
        if (!$month) {
            $month = date('Y-m');
        }
        
        $this->AliasNbPages();
        $this->AddPage();
        
        // Titre du rapport
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'RAPPORT DES FRANCHISES', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Periode: ' . date('F Y', strtotime($month . '-01')), 0, 1, 'C');
        $this->Ln(10);
        
        // Récupérer les données
        $franchiseesData = $this->getFranchiseesData($month);
        $this->addFranchiseesTable($franchiseesData);
        
        return $this->Output('S');
    }
    
    private function getSalesData($month) {
        $stmt = $this->pdo->prepare("
            SELECT f.name, f.company_name, 
                   COUNT(s.id) as sales_days,
                   SUM(s.daily_revenue) as total_revenue,
                   SUM(s.commission_due) as total_commission,
                   AVG(s.daily_revenue) as avg_revenue
            FROM franchisees f
            LEFT JOIN sales s ON f.id = s.franchisee_id 
                AND DATE_FORMAT(s.sale_date, '%Y-%m') = ?
            WHERE f.status = 'active'
            GROUP BY f.id
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$month]);
        return $stmt->fetchAll();
    }
    
    private function getTrucksData() {
        $stmt = $this->pdo->query("
            SELECT t.license_plate, t.model, t.status, t.location, t.last_maintenance,
                   f.name as franchisee_name, f.company_name
            FROM trucks t
            LEFT JOIN franchisees f ON t.franchisee_id = f.id
            ORDER BY t.status, t.license_plate
        ");
        return $stmt->fetchAll();
    }
    
    private function getFranchiseesData($month) {
        $stmt = $this->pdo->prepare("
            SELECT f.name, f.company_name, f.status, f.created_at,
                   f.entry_fee_paid, f.commission_rate,
                   COUNT(t.id) as truck_count,
                   COALESCE(SUM(s.daily_revenue), 0) as monthly_revenue,
                   COALESCE(SUM(s.commission_due), 0) as monthly_commission
            FROM franchisees f
            LEFT JOIN trucks t ON f.id = t.franchisee_id
            LEFT JOIN sales s ON f.id = s.franchisee_id 
                AND DATE_FORMAT(s.sale_date, '%Y-%m') = ?
            GROUP BY f.id
            ORDER BY monthly_revenue DESC
        ");
        $stmt->execute([$month]);
        return $stmt->fetchAll();
    }
    
    private function addSalesTable($data) {
        // En-têtes du tableau
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(200, 220, 255);
        
        $this->Cell(50, 8, 'Franchisé', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Entreprise', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Jours', 1, 0, 'C', true);
        $this->Cell(35, 8, 'CA Total (EUR)', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Commission (EUR)', 1, 1, 'C', true);
        
        // Données
        $this->SetFont('Arial', '', 9);
        foreach ($data as $row) {
            $this->Cell(50, 6, $this->truncateText($row['name'], 20), 1, 0, 'L');
            $this->Cell(40, 6, $this->truncateText($row['company_name'] ?? 'N/A', 15), 1, 0, 'L');
            $this->Cell(20, 6, $row['sales_days'] ?? 0, 1, 0, 'C');
            $this->Cell(35, 6, number_format($row['total_revenue'] ?? 0, 2), 1, 0, 'R');
            $this->Cell(35, 6, number_format($row['total_commission'] ?? 0, 2), 1, 1, 'R');
        }
    }
    
    private function addSalesStats($data) {
        $this->Ln(10);
        
        // Calcul des statistiques
        $totalRevenue = array_sum(array_column($data, 'total_revenue'));
        $totalCommission = array_sum(array_column($data, 'total_commission'));
        $activeFranchisees = count(array_filter($data, fn($d) => ($d['total_revenue'] ?? 0) > 0));
        $avgRevenue = $activeFranchisees > 0 ? $totalRevenue / $activeFranchisees : 0;
        
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'STATISTIQUES GLOBALES', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(70, 8, 'Chiffre d\'affaires total:', 0, 0, 'L');
        $this->Cell(50, 8, number_format($totalRevenue, 2) . ' EUR', 0, 1, 'L');
        
        $this->Cell(70, 8, 'Commission totale due:', 0, 0, 'L');
        $this->Cell(50, 8, number_format($totalCommission, 2) . ' EUR', 0, 1, 'L');
        
        $this->Cell(70, 8, 'Franchisés actifs:', 0, 0, 'L');
        $this->Cell(50, 8, $activeFranchisees . ' / ' . count($data), 0, 1, 'L');
        
        $this->Cell(70, 8, 'CA moyen par franchisé:', 0, 0, 'L');
        $this->Cell(50, 8, number_format($avgRevenue, 2) . ' EUR', 0, 1, 'L');
    }
    
    private function addTrucksTable($data) {
        // En-têtes du tableau
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(200, 220, 255);
        
        $this->Cell(30, 8, 'Plaque', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Modèle', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Statut', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Franchisé', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Emplacement', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Maintenance', 1, 1, 'C', true);
        
        // Données
        $this->SetFont('Arial', '', 8);
        foreach ($data as $row) {
            // Couleur selon le statut
            $fillColor = $this->getTruckStatusColor($row['status']);
            if ($fillColor) {
                $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
                $fill = true;
            } else {
                $fill = false;
            }
            
            $this->Cell(30, 6, $row['license_plate'], 1, 0, 'C', $fill);
            $this->Cell(35, 6, $this->truncateText($row['model'], 15), 1, 0, 'L', $fill);
            $this->Cell(25, 6, ucfirst($row['status']), 1, 0, 'C', $fill);
            $this->Cell(40, 6, $this->truncateText($row['franchisee_name'] ?? 'Non assigné', 18), 1, 0, 'L', $fill);
            $this->Cell(30, 6, $this->truncateText($row['location'] ?? 'N/A', 12), 1, 0, 'L', $fill);
            $this->Cell(30, 6, $row['last_maintenance'] ? date('d/m/Y', strtotime($row['last_maintenance'])) : 'Jamais', 1, 1, 'C', $fill);
        }
    }
    
    private function addTrucksStats($data) {
        $this->Ln(10);
        
        // Calcul des statistiques
        $statusCounts = array_count_values(array_column($data, 'status'));
        $total = count($data);
        $assigned = $statusCounts['assigned'] ?? 0;
        $available = $statusCounts['available'] ?? 0;
        $maintenance = $statusCounts['maintenance'] ?? 0;
        $broken = $statusCounts['broken'] ?? 0;
        
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'STATISTIQUES DU PARC', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(70, 8, 'Total camions:', 0, 0, 'L');
        $this->Cell(50, 8, $total, 0, 1, 'L');
        
        $this->Cell(70, 8, 'Camions assignés:', 0, 0, 'L');
        $this->Cell(50, 8, $assigned . ' (' . round(($assigned/$total)*100, 1) . '%)', 0, 1, 'L');
        
        $this->Cell(70, 8, 'Camions disponibles:', 0, 0, 'L');
        $this->Cell(50, 8, $available . ' (' . round(($available/$total)*100, 1) . '%)', 0, 1, 'L');
        
        $this->Cell(70, 8, 'En maintenance:', 0, 0, 'L');
        $this->Cell(50, 8, $maintenance . ' (' . round(($maintenance/$total)*100, 1) . '%)', 0, 1, 'L');
        
        $this->Cell(70, 8, 'En panne:', 0, 0, 'L');
        $this->Cell(50, 8, $broken . ' (' . round(($broken/$total)*100, 1) . '%)', 0, 1, 'L');
        
        // Maintenance nécessaire
        $needMaintenance = 0;
        foreach ($data as $truck) {
            if (!$truck['last_maintenance'] || 
                strtotime($truck['last_maintenance']) < strtotime('-6 months')) {
                $needMaintenance++;
            }
        }
        
        $this->Cell(70, 8, 'Maintenance nécessaire:', 0, 0, 'L');
        $this->Cell(50, 8, $needMaintenance . ' camions', 0, 1, 'L');
    }
    
    private function addFranchiseesTable($data) {
        // En-têtes du tableau
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(200, 220, 255);
        
        $this->Cell(45, 8, 'Franchisé', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Entreprise', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Statut', 1, 0, 'C', true);
        $this->Cell(15, 8, 'Camions', 1, 0, 'C', true);
        $this->Cell(35, 8, 'CA Mensuel (EUR)', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Commission (EUR)', 1, 1, 'C', true);
        
        // Données
        $this->SetFont('Arial', '', 8);
        foreach ($data as $row) {
            // Couleur selon le statut
            $fillColor = $this->getFranchiseeStatusColor($row['status']);
            if ($fillColor) {
                $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
                $fill = true;
            } else {
                $fill = false;
            }
            
            $this->Cell(45, 6, $this->truncateText($row['name'], 20), 1, 0, 'L', $fill);
            $this->Cell(35, 6, $this->truncateText($row['company_name'] ?? 'N/A', 15), 1, 0, 'L', $fill);
            $this->Cell(20, 6, ucfirst($row['status']), 1, 0, 'C', $fill);
            $this->Cell(15, 6, $row['truck_count'], 1, 0, 'C', $fill);
            $this->Cell(35, 6, number_format($row['monthly_revenue'], 2), 1, 0, 'R', $fill);
            $this->Cell(35, 6, number_format($row['monthly_commission'], 2), 1, 1, 'R', $fill);
        }
    }
    
    private function getTruckStatusColor($status) {
        switch ($status) {
            case 'available':
                return [144, 238, 144]; // Vert clair
            case 'assigned':
                return [173, 216, 230]; // Bleu clair
            case 'maintenance':
                return [255, 255, 224]; // Jaune clair
            case 'broken':
                return [255, 182, 193]; // Rose clair
            default:
                return null;
        }
    }
    
    private function getFranchiseeStatusColor($status) {
        switch ($status) {
            case 'active':
                return [144, 238, 144]; // Vert clair
            case 'inactive':
                return [211, 211, 211]; // Gris clair
            default:
                return null;
        }
    }
    
    private function truncateText($text, $maxLength) {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }
    
    public function generateCompleteReport($month = null) {
        if (!$month) {
            $month = date('Y-m');
        }
        
        $this->AliasNbPages();
        
        // Page 1: Rapport des ventes
        $this->AddPage();
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'RAPPORT COMPLET DRIV\'N COOK', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Periode: ' . date('F Y', strtotime($month . '-01')), 0, 1, 'C');
        $this->Ln(10);
        
        // Section Ventes
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, '1. VENTES', 0, 1, 'L');
        $salesData = $this->getSalesData($month);
        $this->addSalesTable($salesData);
        $this->addSalesStats($salesData);
        
        // Page 2: Rapport des camions
        $this->AddPage();
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, '2. PARC DE CAMIONS', 0, 1, 'L');
        $trucksData = $this->getTrucksData();
        $this->addTrucksTable($trucksData);
        $this->addTrucksStats($trucksData);
        
        // Page 3: Rapport des franchisés
        $this->AddPage();
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, '3. FRANCHISES', 0, 1, 'L');
        $franchiseesData = $this->getFranchiseesData($month);
        $this->addFranchiseesTable($franchiseesData);
        
        return $this->Output('S');
    }
    
    // Méthode pour sauvegarder sur le serveur
    public function saveReport($type, $month = null, $filename = null) {
        if (!$filename) {
            $filename = 'rapport_' . $type . '_' . ($month ?? date('Y-m')) . '_' . date('Y-m-d_H-i-s') . '.pdf';
        }
        
        $reportPath = '../reports/';
        if (!is_dir($reportPath)) {
            mkdir($reportPath, 0755, true);
        }
        
        switch ($type) {
            case 'sales':
                $content = $this->generateSalesReport($month);
                break;
            case 'trucks':
                $content = $this->generateTrucksReport();
                break;
            case 'franchisees':
                $content = $this->generateFranchiseesReport($month);
                break;
            case 'complete':
                $content = $this->generateCompleteReport($month);
                break;
            default:
                throw new Exception('Type de rapport invalide');
        }
        
        $fullPath = $reportPath . $filename;
        file_put_contents($fullPath, $content);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $fullPath,
            'size' => filesize($fullPath)
        ];
    }
}
?>