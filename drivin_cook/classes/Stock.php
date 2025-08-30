<?php
require_once 'Database.php';

class Stock {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
 
    public function createProduct($data) {
        try {
            $productId = $this->db->insert('products', $data);
            
            return [
                'success' => true,
                'product_id' => $productId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAllProducts($warehouseId = null) {
        $whereClause = $warehouseId ? 'WHERE p.warehouse_id = ?' : '';
        $params = $warehouseId ? [$warehouseId] : [];
        
        $sql = "
            SELECT p.*, w.name as warehouse_name
            FROM products p
            JOIN warehouses w ON p.warehouse_id = w.id
            {$whereClause}
            ORDER BY w.name, p.category, p.name
        ";
        
        return $this->db->select($sql, $params);
    }

    public function getProductById($id) {
        $sql = "
            SELECT p.*, w.name as warehouse_name
            FROM products p
            JOIN warehouses w ON p.warehouse_id = w.id
            WHERE p.id = ?
        ";
        
        return $this->db->selectOne($sql, [$id]);
    }

    public function updateProduct($id, $data) {
        try {
            $rowsAffected = $this->db->update('products', $data, 'id = :id', ['id' => $id]);
            
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

    public function deleteProduct($id) {
        try {
            $rowsAffected = $this->db->delete('products', 'id = ?', [$id]);
            
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

    public function updateStock($productId, $newQuantity) {
        if ($newQuantity < 0) {
            return [
                'success' => false,
                'error' => 'La quantité ne peut pas être négative'
            ];
        }
        
        return $this->updateProduct($productId, ['stock_quantity' => $newQuantity]);
    }

    public function createOrder($franchiseeId, $warehouseId, $items) {
        try {
            $this->db->beginTransaction();
            
            $totalAmount = 0;
            $validItems = [];
            
            foreach ($items as $item) {
                $product = $this->getProductById($item['product_id']);
                
                if (!$product) {
                    throw new Exception("Produit ID {$item['product_id']} non trouvé");
                }
                
                if ($product['warehouse_id'] != $warehouseId) {
                    throw new Exception("Le produit {$product['name']} n'appartient pas à cet entrepôt");
                }
                
                if ($product['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Stock insuffisant pour {$product['name']} (disponible: {$product['stock_quantity']})");
                }
                
                $itemTotal = $product['price'] * $item['quantity'];
                $totalAmount += $itemTotal;
                
                $validItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $product['price'],
                    'total' => $itemTotal
                ];
            }
            
            if (empty($validItems)) {
                throw new Exception("Aucun article valide dans la commande");
            }
           
            $orderId = $this->db->insert('stock_orders', [
                'franchisee_id' => $franchiseeId,
                'warehouse_id' => $warehouseId,
                'total_amount' => $totalAmount,
                'status' => 'pending'
            ]);
    
            foreach ($validItems as $item) {
                $this->db->insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price']
                ]);

                $this->db->execute(
                    "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'total_amount' => $totalAmount,
                'items_count' => count($validItems)
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAllOrders($franchiseeId = null, $limit = null) {
        $whereClause = $franchiseeId ? 'WHERE so.franchisee_id = ?' : '';
        $params = $franchiseeId ? [$franchiseeId] : [];
        $limitClause = $limit ? "LIMIT " . (int)$limit : "";
        
        $sql = "
            SELECT so.*, 
                   f.name as franchisee_name, 
                   f.company_name,
                   w.name as warehouse_name,
                   COUNT(oi.id) as items_count
            FROM stock_orders so
            JOIN franchisees f ON so.franchisee_id = f.id
            JOIN warehouses w ON so.warehouse_id = w.id
            LEFT JOIN order_items oi ON so.id = oi.order_id
            {$whereClause}
            GROUP BY so.id
            ORDER BY so.order_date DESC
            {$limitClause}
        ";
        
        return $this->db->select($sql, $params);
    }

    public function getOrderById($orderId) {
        $sql = "
            SELECT so.*, 
                   f.name as franchisee_name, 
                   f.company_name,
                   w.name as warehouse_name
            FROM stock_orders so
            JOIN franchisees f ON so.franchisee_id = f.id
            JOIN warehouses w ON so.warehouse_id = w.id
            WHERE so.id = ?
        ";
        
        $order = $this->db->selectOne($sql, [$orderId]);
        
        if ($order) {
        
            $sql = "
                SELECT oi.*, p.name as product_name, p.category
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY p.category, p.name
            ";
            
            $order['items'] = $this->db->select($sql, [$orderId]);
        }
        
        return $order;
    }

    public function updateOrderStatus($orderId, $status) {
        $validStatuses = ['pending', 'delivered', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'error' => 'Statut invalide'
            ];
        }
        
        try {
            $this->db->update('stock_orders', ['status' => $status], 'id = :id', ['id' => $orderId]);
            
            return [
                'success' => true,
                'message' => 'Statut mis à jour'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cancelOrder($orderId) {
        try {
            $this->db->beginTransaction();
 
            $order = $this->getOrderById($orderId);
            if (!$order) {
                throw new Exception("Commande non trouvée");
            }
            
            if ($order['status'] !== 'pending') {
                throw new Exception("Seules les commandes en attente peuvent être annulées");
            }
 
            foreach ($order['items'] as $item) {
                $this->db->execute(
                    "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }

            $this->db->update('stock_orders', ['status' => 'cancelled'], 'id = :id', ['id' => $orderId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Commande annulée et stocks remis à jour'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
 
    public function getAllWarehouses() {
        return $this->db->select("
            SELECT * FROM warehouses 
            ORDER BY name
        ");
    }

    public function getWarehouseStats($warehouseId) {
        $sql = "
            SELECT 
                COUNT(p.id) as product_count,
                SUM(p.stock_quantity) as total_items,
                SUM(p.stock_quantity * p.price) as total_value,
                COUNT(CASE WHEN p.stock_quantity < 10 THEN 1 END) as low_stock_items
            FROM products p
            WHERE p.warehouse_id = ?
        ";
        
        return $this->db->selectOne($sql, [$warehouseId]);
    }

    public function getLowStockProducts($threshold = 10, $warehouseId = null) {
        $whereClause = "WHERE p.stock_quantity < ?";
        $params = [$threshold];
        
        if ($warehouseId) {
            $whereClause .= " AND p.warehouse_id = ?";
            $params[] = $warehouseId;
        }
        
        $sql = "
            SELECT p.*, w.name as warehouse_name
            FROM products p
            JOIN warehouses w ON p.warehouse_id = w.id
            {$whereClause}
            ORDER BY p.stock_quantity ASC, w.name, p.name
        ";
        
        return $this->db->select($sql, $params);
    }

    public function checkComplianceRule($franchiseeId, $months = 3) {
        $sql = "
            SELECT 
                COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_purchases,
                COALESCE(SUM(CASE WHEN p.warehouse_id IN (1,2,3,4) THEN oi.quantity * oi.unit_price ELSE 0 END), 0) as driv_purchases
            FROM stock_orders so
            JOIN order_items oi ON so.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE so.franchisee_id = ?
            AND so.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL ? MONTH)
        ";
        
        $result = $this->db->selectOne($sql, [$franchiseeId, $months]);
        
        if ($result && $result['total_purchases'] > 0) {
            $percentage = ($result['driv_purchases'] / $result['total_purchases']) * 100;
            
            return [
                'compliant' => $percentage >= 80,
                'percentage' => round($percentage, 2),
                'total_purchases' => $result['total_purchases'],
                'driv_purchases' => $result['driv_purchases'],
                'external_purchases' => $result['total_purchases'] - $result['driv_purchases']
            ];
        }
        
        return [
            'compliant' => true,
            'percentage' => 0,
            'total_purchases' => 0,
            'driv_purchases' => 0,
            'external_purchases' => 0,
            'message' => 'Aucun achat dans la période'
        ];
    }

    public function getGlobalStats() {
        $stats = [];

        $sql = "
            SELECT w.name, w.manager_name,
                   COUNT(p.id) as product_count,
                   SUM(p.stock_quantity) as total_items,
                   SUM(p.stock_quantity * p.price) as total_value
            FROM warehouses w
            LEFT JOIN products p ON w.id = p.warehouse_id
            GROUP BY w.id
            ORDER BY w.name
        ";
        $stats['warehouses'] = $this->db->select($sql);
 
        $stats['recent_orders'] = $this->getAllOrders(null, 10);
        
        $stats['low_stock'] = $this->getLowStockProducts();
        
        return $stats;
    }

    public function searchProducts($criteria) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($criteria['name'])) {
            $whereConditions[] = "p.name LIKE ?";
            $params[] = '%' . $criteria['name'] . '%';
        }
        
        if (!empty($criteria['category'])) {
            $whereConditions[] = "p.category = ?";
            $params[] = $criteria['category'];
        }
        
        if (!empty($criteria['warehouse_id'])) {
            $whereConditions[] = "p.warehouse_id = ?";
            $params[] = $criteria['warehouse_id'];
        }
        
        if (isset($criteria['low_stock']) && $criteria['low_stock']) {
            $whereConditions[] = "p.stock_quantity < 10";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "
            SELECT p.*, w.name as warehouse_name
            FROM products p
            JOIN warehouses w ON p.warehouse_id = w.id
            {$whereClause}
            ORDER BY w.name, p.category, p.name
        ";
        
        return $this->db->select($sql, $params);
    }

    public function generateWarehouseReport($warehouseId) {
        $warehouse = $this->db->selectOne("SELECT * FROM warehouses WHERE id = ?", [$warehouseId]);
        $products = $this->getAllProducts($warehouseId);
        $stats = $this->getWarehouseStats($warehouseId);
        
        return [
            'warehouse' => $warehouse,
            'products' => $products,
            'stats' => $stats,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    public function getFranchiseePurchases($franchiseeId, $period = 'month') {
        $dateFilter = '';
        switch ($period) {
            case 'month':
                $dateFilter = "AND MONTH(so.order_date) = MONTH(CURRENT_DATE) AND YEAR(so.order_date) = YEAR(CURRENT_DATE)";
                break;
            case '3months':
                $dateFilter = "AND so.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
                break;
            case 'year':
                $dateFilter = "AND YEAR(so.order_date) = YEAR(CURRENT_DATE)";
                break;
        }
        
        $sql = "
            SELECT 
                SUM(oi.quantity * oi.unit_price) as total_amount,
                COUNT(DISTINCT so.id) as order_count,
                COUNT(oi.id) as item_count
            FROM stock_orders so
            JOIN order_items oi ON so.id = oi.order_id
            WHERE so.franchisee_id = ? {$dateFilter}
        ";
        
        return $this->db->selectOne($sql, [$franchiseeId]);
    }
 
    public function addStock($productId, $quantity, $reason = 'Réapprovisionnement') {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'error' => 'La quantité doit être positive'
            ];
        }
        
        try {
            $this->db->execute(
                "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                [$quantity, $productId]
            );
            
            return [
                'success' => true,
                'message' => "Stock augmenté de {$quantity} unités"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function removeStock($productId, $quantity, $reason = 'Sortie de stock') {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'error' => 'La quantité doit être positive'
            ];
        }
        
        try {

            $product = $this->getProductById($productId);
            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Produit non trouvé'
                ];
            }
            
            if ($product['stock_quantity'] < $quantity) {
                return [
                    'success' => false,
                    'error' => 'Stock insuffisant'
                ];
            }
            
            $this->db->execute(
                "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                [$quantity, $productId]
            );
            
            return [
                'success' => true,
                'message' => "Stock réduit de {$quantity} unités"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>