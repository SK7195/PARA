<?php

require_once 'Database.php';

class ClientOrder {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($clientId, $orderData, $items) {
        try {
            $this->db->beginTransaction();
      
            $validation = $this->validateOrderData($orderData, $items);
            if (!$validation['valid']) {
                throw new Exception(implode(', ', $validation['errors']));
            }

            $totalAmount = $this->calculateTotal($items);

            if (!empty($orderData['loyalty_points_used'])) {
                $loyaltyValidation = $this->validateLoyaltyPoints(
                    $clientId, 
                    $orderData['loyalty_points_used'], 
                    $totalAmount
                );
                if (!$loyaltyValidation['valid']) {
                    throw new Exception($loyaltyValidation['error']);
                }
            }
            
            $orderData['client_id'] = $clientId;
            $orderData['total_amount'] = $totalAmount;
            $orderData['status'] = 'pending';
            
            $orderId = $this->db->insert('client_orders', $orderData);
            
            foreach ($items as $item) {
                $this->db->insert('client_order_items', [
                    'order_id' => $orderId,
                    'menu_id' => $item['menu_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price']
                ]);
            }
            
            if (!empty($orderData['loyalty_points_used'])) {
                $this->updateLoyaltyPoints($clientId, $orderData['loyalty_points_used'], $orderId, 'used');
            }
            

            $pointsEarned = floor($totalAmount);
            if ($pointsEarned > 0) {
                $this->updateLoyaltyPoints($clientId, $pointsEarned, $orderId, 'earned');
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'total_amount' => $totalAmount,
                'points_earned' => $pointsEarned
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getById($orderId, $clientId = null) {
        $whereClause = $clientId ? "WHERE co.id = ? AND co.client_id = ?" : "WHERE co.id = ?";
        $params = $clientId ? [$orderId, $clientId] : [$orderId];
        
        $order = $this->db->selectOne("
            SELECT co.*, 
                   c.firstname, c.lastname, c.email,
                   t.license_plate as truck_plate,
                   COUNT(coi.id) as items_count
            FROM client_orders co
            JOIN clients c ON co.client_id = c.id
            LEFT JOIN trucks t ON co.truck_id = t.id
            LEFT JOIN client_order_items coi ON co.id = coi.order_id
            {$whereClause}
            GROUP BY co.id
        ", $params);
        
        if ($order) {
            $order['items'] = $this->getOrderItems($orderId);
        }
        
        return $order;
    }

    public function getOrderItems($orderId) {
        return $this->db->select("
            SELECT coi.*, 
                   m.name_fr as menu_name_fr,
                   m.name_en as menu_name_en, 
                   m.name_es as menu_name_es,
                   m.category,
                   (coi.quantity * coi.unit_price) as item_total
            FROM client_order_items coi
            JOIN menus m ON coi.menu_id = m.id
            WHERE coi.order_id = ?
            ORDER BY m.category, m.name_fr
        ", [$orderId]);
    }

    public function getClientOrders($clientId, $limit = null, $status = null) {
        $whereConditions = ["co.client_id = ?"];
        $params = [$clientId];
        
        if ($status) {
            $whereConditions[] = "co.status = ?";
            $params[] = $status;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        $limitClause = $limit ? "LIMIT " . (int)$limit : "";
        
        return $this->db->select("
            SELECT co.*, 
                   t.license_plate as truck_plate,
                   COUNT(coi.id) as items_count
            FROM client_orders co
            LEFT JOIN trucks t ON co.truck_id = t.id
            LEFT JOIN client_order_items coi ON co.id = coi.order_id
            {$whereClause}
            GROUP BY co.id
            ORDER BY co.order_date DESC
            {$limitClause}
        ", $params);
    }

    public function getAllOrders($limit = null, $status = null, $dateFrom = null, $dateTo = null) {
        $whereConditions = [];
        $params = [];
        
        if ($status) {
            $whereConditions[] = "co.status = ?";
            $params[] = $status;
        }
        
        if ($dateFrom) {
            $whereConditions[] = "DATE(co.order_date) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereConditions[] = "DATE(co.order_date) <= ?";
            $params[] = $dateTo;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        $limitClause = $limit ? "LIMIT " . (int)$limit : "";
        
        return $this->db->select("
            SELECT co.*, 
                   c.firstname, c.lastname, c.email,
                   t.license_plate as truck_plate,
                   COUNT(coi.id) as items_count
            FROM client_orders co
            JOIN clients c ON co.client_id = c.id
            LEFT JOIN trucks t ON co.truck_id = t.id
            LEFT JOIN client_order_items coi ON co.id = coi.order_id
            {$whereClause}
            GROUP BY co.id
            ORDER BY co.order_date DESC
            {$limitClause}
        ", $params);
    }

    public function updateStatus($orderId, $newStatus) {
        $validStatuses = ['pending', 'confirmed', 'ready', 'completed', 'cancelled'];
        
        if (!in_array($newStatus, $validStatuses)) {
            return [
                'success' => false,
                'error' => 'Statut invalide'
            ];
        }
        
        try {

            if ($newStatus === 'cancelled') {
                $order = $this->getById($orderId);
                if ($order && $order['loyalty_points_used'] > 0) {
                    $this->refundLoyaltyPoints($order['client_id'], $order['loyalty_points_used'], $orderId);
                }
            }
            
            $rowsAffected = $this->db->update('client_orders', 
                ['status' => $newStatus], 
                'id = :id', 
                ['id' => $orderId]
            );
            
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

    public function cancel($orderId, $clientId = null) {
        try {
            $order = $this->getById($orderId, $clientId);
            
            if (!$order) {
                throw new Exception("Commande non trouvée");
            }
            
            if (!in_array($order['status'], ['pending', 'confirmed'])) {
                throw new Exception("Cette commande ne peut plus être annulée");
            }
            
            if ($order['pickup_time']) {
                $pickupTime = strtotime($order['pickup_time']);
                $now = time();
                
                if ($pickupTime - $now < 30 * 60) {
                    throw new Exception("Annulation impossible : moins de 30 minutes avant l'heure de retrait");
                }
            }
            
            return $this->updateStatus($orderId, 'cancelled');
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getStats($clientId = null, $period = 'all') {
        $whereConditions = [];
        $params = [];
        
        if ($clientId) {
            $whereConditions[] = "client_id = ?";
            $params[] = $clientId;
        }
        
        switch ($period) {
            case 'today':
                $whereConditions[] = "DATE(order_date) = CURDATE()";
                break;
            case 'week':
                $whereConditions[] = "order_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $whereConditions[] = "order_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $whereConditions[] = "order_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        return $this->db->selectOne("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as average_order_value,
                COALESCE(SUM(loyalty_points_used), 0) as total_points_used,
                MAX(order_date) as last_order_date
            FROM client_orders
            {$whereClause}
        ", $params);
    }

    public function getPopularItems($limit = 10, $period = 'month') {
        $whereClause = '';
        $params = [];
        
        switch ($period) {
            case 'week':
                $whereClause = "WHERE co.order_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $whereClause = "WHERE co.order_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $whereClause = "WHERE co.order_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
        }
        
        return $this->db->select("
            SELECT 
                m.name_fr as menu_name,
                m.category,
                SUM(coi.quantity) as total_quantity,
                COUNT(DISTINCT co.id) as order_count,
                COALESCE(SUM(coi.quantity * coi.unit_price), 0) as total_revenue
            FROM client_order_items coi
            JOIN client_orders co ON coi.order_id = co.id
            JOIN menus m ON coi.menu_id = m.id
            {$whereClause}
            AND co.status IN ('confirmed', 'ready', 'completed')
            GROUP BY coi.menu_id
            ORDER BY total_quantity DESC
            LIMIT ?
        ", array_merge($params, [$limit]));
    }

    public function getDailySales($dateFrom = null, $dateTo = null) {
        $params = [];
        $whereConditions = ["co.status IN ('confirmed', 'ready', 'completed')"];
        
        if ($dateFrom) {
            $whereConditions[] = "DATE(co.order_date) >= ?";
            $params[] = $dateFrom;
        } else {
            $whereConditions[] = "co.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        
        if ($dateTo) {
            $whereConditions[] = "DATE(co.order_date) <= ?";
            $params[] = $dateTo;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->db->select("
            SELECT 
                DATE(co.order_date) as sale_date,
                COUNT(*) as order_count,
                SUM(co.total_amount) as daily_revenue,
                AVG(co.total_amount) as average_order_value
            FROM client_orders co
            {$whereClause}
            GROUP BY DATE(co.order_date)
            ORDER BY sale_date DESC
        ", $params);
    }

    public function searchOrders($criteria) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($criteria['order_id'])) {
            $whereConditions[] = "co.id = ?";
            $params[] = $criteria['order_id'];
        }
        
        if (!empty($criteria['client_email'])) {
            $whereConditions[] = "c.email LIKE ?";
            $params[] = '%' . $criteria['client_email'] . '%';
        }
        
        if (!empty($criteria['client_name'])) {
            $whereConditions[] = "(c.firstname LIKE ? OR c.lastname LIKE ?)";
            $params[] = '%' . $criteria['client_name'] . '%';
            $params[] = '%' . $criteria['client_name'] . '%';
        }
        
        if (!empty($criteria['status'])) {
            $whereConditions[] = "co.status = ?";
            $params[] = $criteria['status'];
        }
        
        if (!empty($criteria['date_from'])) {
            $whereConditions[] = "DATE(co.order_date) >= ?";
            $params[] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $whereConditions[] = "DATE(co.order_date) <= ?";
            $params[] = $criteria['date_to'];
        }
        
        if (!empty($criteria['min_amount'])) {
            $whereConditions[] = "co.total_amount >= ?";
            $params[] = $criteria['min_amount'];
        }
        
        if (!empty($criteria['max_amount'])) {
            $whereConditions[] = "co.total_amount <= ?";
            $params[] = $criteria['max_amount'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        return $this->db->select("
            SELECT co.*, 
                   c.firstname, c.lastname, c.email,
                   t.license_plate as truck_plate,
                   COUNT(coi.id) as items_count
            FROM client_orders co
            JOIN clients c ON co.client_id = c.id
            LEFT JOIN trucks t ON co.truck_id = t.id
            LEFT JOIN client_order_items coi ON co.id = coi.order_id
            {$whereClause}
            GROUP BY co.id
            ORDER BY co.order_date DESC
        ", $params);
    }

    public function getRevenueByPeriod($period = 'month', $limit = 12) {
        $dateFormat = '';
        $interval = '';
        
        switch ($period) {
            case 'day':
                $dateFormat = '%Y-%m-%d';
                $interval = '30 DAY';
                break;
            case 'week':
                $dateFormat = '%Y-%u';
                $interval = '12 WEEK';
                break;
            case 'month':
                $dateFormat = '%Y-%m';
                $interval = '12 MONTH';
                break;
            case 'year':
                $dateFormat = '%Y';
                $interval = '5 YEAR';
                break;
        }
        
        return $this->db->select("
            SELECT 
                DATE_FORMAT(order_date, '{$dateFormat}') as period,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_order_value
            FROM client_orders
            WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL {$interval})
            AND status IN ('confirmed', 'ready', 'completed')
            GROUP BY period
            ORDER BY period DESC
            LIMIT ?
        ", [$limit]);
    }

    private function calculateTotal($items) {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['unit_price'] * $item['quantity'];
        }
        return $total;
    }

    private function validateOrderData($orderData, $items) {
        $errors = [];
        
        if (empty($items)) {
            $errors[] = 'La commande doit contenir au moins un article';
        }
        
        if (empty($orderData['pickup_location'])) {
            $errors[] = 'Le lieu de retrait est obligatoire';
        }
        
        if (empty($orderData['pickup_time'])) {
            $errors[] = 'L\'heure de retrait est obligatoire';
        } else {
            $pickupTime = strtotime($orderData['pickup_time']);
            if ($pickupTime <= time()) {
                $errors[] = 'L\'heure de retrait doit être dans le futur';
            }
        }
        
        if (!empty($orderData['payment_method'])) {
            $validPaymentMethods = ['card', 'cash', 'loyalty_points'];
            if (!in_array($orderData['payment_method'], $validPaymentMethods)) {
                $errors[] = 'Méthode de paiement invalide';
            }
        }
        
        foreach ($items as $item) {
            if (empty($item['menu_id']) || empty($item['quantity']) || empty($item['unit_price'])) {
                $errors[] = 'Données d\'article invalides';
                break;
            }
            
            if ($item['quantity'] <= 0 || $item['unit_price'] <= 0) {
                $errors[] = 'Quantité et prix doivent être positifs';
                break;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function validateLoyaltyPoints($clientId, $pointsToUse, $orderAmount) {

        $client = $this->db->selectOne("SELECT loyalty_points FROM clients WHERE id = ?", [$clientId]);
        
        if (!$client) {
            return [
                'valid' => false,
                'error' => 'Client non trouvé'
            ];
        }
        
        if ($pointsToUse > $client['loyalty_points']) {
            return [
                'valid' => false,
                'error' => 'Points de fidélité insuffisants'
            ];
        }
        
        if ($pointsToUse % 100 !== 0) {
            return [
                'valid' => false,
                'error' => 'Les points doivent être utilisés par tranches de 100'
            ];
        }
        

        $discountAmount = ($pointsToUse / 100) * 5; 
        if ($discountAmount > $orderAmount) {
            return [
                'valid' => false,
                'error' => 'La réduction ne peut pas dépasser le montant de la commande'
            ];
        }
        
        return [
            'valid' => true
        ];
    }

    private function updateLoyaltyPoints($clientId, $points, $orderId, $type = 'earned') {
        if ($type === 'earned') {
 
            $this->db->execute("
                UPDATE clients 
                SET loyalty_points = loyalty_points + ? 
                WHERE id = ?
            ", [$points, $clientId]);
            
            $this->db->insert('loyalty_history', [
                'client_id' => $clientId,
                'points_change' => $points,
                'reason' => 'Points gagnés pour commande #' . $orderId,
                'order_id' => $orderId
            ]);
        } else {

            $this->db->execute("
                UPDATE clients 
                SET loyalty_points = loyalty_points - ? 
                WHERE id = ?
            ", [$points, $clientId]);
            
            $this->db->insert('loyalty_history', [
                'client_id' => $clientId,
                'points_change' => -$points,
                'reason' => 'Points utilisés pour commande #' . $orderId,
                'order_id' => $orderId
            ]);
        }
    }

    private function refundLoyaltyPoints($clientId, $pointsToRefund, $orderId) {
        $this->db->execute("
            UPDATE clients 
            SET loyalty_points = loyalty_points + ? 
            WHERE id = ?
        ", [$pointsToRefund, $clientId]);
        
        $this->db->insert('loyalty_history', [
            'client_id' => $clientId,
            'points_change' => $pointsToRefund,
            'reason' => 'Remboursement points - commande #' . $orderId . ' annulée',
            'order_id' => $orderId
        ]);
    }

    public function generateInvoice($orderId) {
        $order = $this->getById($orderId);
        
        if (!$order) {
            return [
                'success' => false,
                'error' => 'Commande non trouvée'
            ];
        }
        
        $invoice = [
            'invoice_number' => 'INV-' . str_pad($orderId, 6, '0', STR_PAD_LEFT),
            'order_id' => $orderId,
            'client_name' => $order['firstname'] . ' ' . $order['lastname'],
            'client_email' => $order['email'],
            'order_date' => $order['order_date'],
            'total_amount' => $order['total_amount'],
            'items' => $order['items'],
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        return [
            'success' => true,
            'invoice' => $invoice
        ];
    }

    public function getOrdersByTruck($truckId, $dateFrom = null, $dateTo = null) {
        $whereConditions = ["truck_id = ?"];
        $params = [$truckId];
        
        if ($dateFrom) {
            $whereConditions[] = "DATE(order_date) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereConditions[] = "DATE(order_date) <= ?";
            $params[] = $dateTo;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->db->select("
            SELECT co.*, 
                   c.firstname, c.lastname, c.email,
                   COUNT(coi.id) as items_count
            FROM client_orders co
            JOIN clients c ON co.client_id = c.id
            LEFT JOIN client_order_items coi ON co.id = coi.order_id
            {$whereClause}
            GROUP BY co.id
            ORDER BY co.pickup_time ASC, co.order_date ASC
        ", $params);
    }

    public function getPeakHours($dateFrom = null, $dateTo = null) {
        $whereConditions = ["status IN ('confirmed', 'ready', 'completed')"];
        $params = [];
        
        if ($dateFrom) {
            $whereConditions[] = "DATE(order_date) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereConditions[] = "DATE(order_date) <= ?";
            $params[] = $dateTo;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->db->select("
            SELECT 
                HOUR(pickup_time) as hour_of_day,
                COUNT(*) as order_count,
                AVG(total_amount) as avg_amount
            FROM client_orders
            {$whereClause}
            AND pickup_time IS NOT NULL
            GROUP BY HOUR(pickup_time)
            ORDER BY order_count DESC
        ", $params);
    }

    public function getRepeatCustomers($minOrders = 2) {
        return $this->db->select("
            SELECT 
                c.id,
                c.firstname,
                c.lastname, 
                c.email,
                COUNT(co.id) as order_count,
                SUM(co.total_amount) as total_spent,
                AVG(co.total_amount) as avg_order_value,
                MAX(co.order_date) as last_order_date
            FROM clients c
            JOIN client_orders co ON c.id = co.client_id
            WHERE co.status IN ('confirmed', 'ready', 'completed')
            GROUP BY c.id
            HAVING order_count >= ?
            ORDER BY order_count DESC, total_spent DESC
        ", [$minOrders]);
    }

    public function updatePickupTime($orderId, $newPickupTime, $clientId = null) {
        try {
            $whereClause = $clientId ? "id = ? AND client_id = ?" : "id = ?";
            $params = $clientId ? [$orderId, $clientId] : [$orderId];
            
            // Vérifier que la commande peut encore être modifiée
            $order = $this->db->selectOne("SELECT status, pickup_time FROM client_orders WHERE {$whereClause}", $params);
            
            if (!$order) {
                throw new Exception("Commande non trouvée");
            }
            
            if (!in_array($order['status'], ['pending', 'confirmed'])) {
                throw new Exception("Cette commande ne peut plus être modifiée");
            }
            
            $newPickupTimestamp = strtotime($newPickupTime);
            if ($newPickupTimestamp <= time()) {
                throw new Exception("La nouvelle heure doit être dans le futur");
            }
            
            $rowsAffected = $this->db->update('client_orders', 
                ['pickup_time' => $newPickupTime], 
                $whereClause, 
                array_combine(['id' => $orderId] + ($clientId ? ['client_id' => $clientId] : []), $params)
            );
            
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
}
?>