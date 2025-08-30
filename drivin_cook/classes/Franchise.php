<?php
require_once 'Database.php';

class Franchise {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($userData, $franchiseData) {
        try {
            $this->db->beginTransaction();

            $userId = $this->db->insert('users', [
                'email' => $userData['email'],
                'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
                'type' => 'franchisee'
            ]);
 
            $franchiseData['user_id'] = $userId;
            $franchiseId = $this->db->insert('franchisees', $franchiseData);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'franchise_id' => $franchiseId,
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
 
    public function getAll($includeStats = false) {
        $sql = "
            SELECT f.*, u.email,
                   " . ($includeStats ? "
                   COUNT(DISTINCT t.id) as truck_count,
                   COALESCE(SUM(s.daily_revenue), 0) as total_revenue,
                   COUNT(DISTINCT s.id) as total_sales
                   " : "1 as dummy") . "
            FROM franchisees f
            JOIN users u ON f.user_id = u.id
            " . ($includeStats ? "
            LEFT JOIN trucks t ON f.id = t.franchisee_id
            LEFT JOIN sales s ON f.id = s.franchisee_id
            " : "") . "
            GROUP BY f.id
            ORDER BY f.created_at DESC
        ";
        
        return $this->db->select($sql);
    }
    
    public function getById($id) {
        $sql = "
            SELECT f.*, u.email 
            FROM franchisees f 
            JOIN users u ON f.user_id = u.id 
            WHERE f.id = ?
        ";
        
        return $this->db->selectOne($sql, [$id]);
    }

    public function getByUserId($userId) {
        $sql = "
            SELECT f.*, u.email 
            FROM franchisees f 
            JOIN users u ON f.user_id = u.id 
            WHERE f.user_id = ?
        ";
        
        return $this->db->selectOne($sql, [$userId]);
    }

    public function update($id, $data) {
        try {
            $rowsAffected = $this->db->update('franchisees', $data, 'id = :id', ['id' => $id]);
            
            return [
                'success' => true,
                'rows_affected' => $rowsAffected
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function delete($id) {
        try {
            $this->db->beginTransaction();

            $franchise = $this->getById($id);
            if (!$franchise) {
                throw new Exception("Franchisé non trouvé");
            }

            $this->db->delete('franchisees', 'id = ?', [$id]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Franchisé supprimé avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getStats($franchiseId, $period = 'month') {
        $dateFilter = '';
        switch ($period) {
            case 'month':
                $dateFilter = "AND MONTH(s.sale_date) = MONTH(CURRENT_DATE) AND YEAR(s.sale_date) = YEAR(CURRENT_DATE)";
                break;
            case 'year':
                $dateFilter = "AND YEAR(s.sale_date) = YEAR(CURRENT_DATE)";
                break;
            case 'all':
            default:
                $dateFilter = '';
                break;
        }
        
        $sql = "
            SELECT 
                COUNT(s.id) as total_sales,
                COALESCE(SUM(s.daily_revenue), 0) as total_revenue,
                COALESCE(SUM(s.commission_due), 0) as total_commission,
                COALESCE(AVG(s.daily_revenue), 0) as avg_daily_revenue,
                MAX(s.sale_date) as last_sale_date
            FROM sales s
            WHERE s.franchisee_id = ? {$dateFilter}
        ";
        
        return $this->db->selectOne($sql, [$franchiseId]);
    }
    
    public function getTrucks($franchiseId) {
        $sql = "
            SELECT * 
            FROM trucks 
            WHERE franchisee_id = ? 
            ORDER BY created_at DESC
        ";
        
        return $this->db->select($sql, [$franchiseId]);
    }
    
    public function getOrders($franchiseId, $limit = null) {
        $limitClause = $limit ? "LIMIT " . (int)$limit : "";
        
        $sql = "
            SELECT so.*, w.name as warehouse_name
            FROM stock_orders so
            JOIN warehouses w ON so.warehouse_id = w.id
            WHERE so.franchisee_id = ?
            ORDER BY so.order_date DESC
            {$limitClause}
        ";
        
        return $this->db->select($sql, [$franchiseId]);
    }
    
    public function getSales($franchiseId, $limit = null, $month = null) {
        $params = [$franchiseId];
        $monthFilter = '';
        
        if ($month) {
            $monthFilter = "AND DATE_FORMAT(sale_date, '%Y-%m') = ?";
            $params[] = $month;
        }
        
        $limitClause = $limit ? "LIMIT " . (int)$limit : "";
        
        $sql = "
            SELECT * 
            FROM sales 
            WHERE franchisee_id = ? {$monthFilter}
            ORDER BY sale_date DESC
            {$limitClause}
        ";
        
        return $this->db->select($sql, $params);
    }
    
    public function checkComplianceRule($franchiseId) {
        $sql = "
            SELECT 
                COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_purchases,
                COALESCE(SUM(CASE WHEN p.warehouse_id IN (1,2,3,4) THEN oi.quantity * oi.unit_price ELSE 0 END), 0) as driv_purchases
            FROM stock_orders so
            JOIN order_items oi ON so.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE so.franchisee_id = ?
            AND so.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
        ";
        
        $result = $this->db->selectOne($sql, [$franchiseId]);
        
        if ($result && $result['total_purchases'] > 0) {
            $compliance_percentage = ($result['driv_purchases'] / $result['total_purchases']) * 100;
            return [
                'compliant' => $compliance_percentage >= 80,
                'percentage' => $compliance_percentage,
                'total_purchases' => $result['total_purchases'],
                'driv_purchases' => $result['driv_purchases']
            ];
        }
        
        return [
            'compliant' => true,
            'percentage' => 0,
            'total_purchases' => 0,
            'driv_purchases' => 0,
            'message' => 'Aucun achat récent'
        ];
    }
    
    public function getActive() {
        return $this->db->select("
            SELECT f.*, u.email 
            FROM franchisees f 
            JOIN users u ON f.user_id = u.id 
            WHERE f.status = 'active' 
            ORDER BY f.name
        ");
    }
    
    public function changeStatus($id, $status) {
        $validStatuses = ['active', 'inactive'];
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'error' => 'Statut invalide'
            ];
        }
        
        return $this->update($id, ['status' => $status]);
    }
}
?>