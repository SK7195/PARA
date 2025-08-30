<?php

require_once 'Database.php';

class Client {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        try {
            
            if ($this->db->exists('clients', 'email = ?', [$data['email']])) {
                return [
                    'success' => false,
                    'error' => 'Cette adresse email est déjà utilisée'
                ];
            }
            
            
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            
          
            if (!isset($data['language'])) {
                $data['language'] = 'fr';
            }
            
            $clientId = $this->db->insert('clients', $data);
            
           
            $this->subscribeToNewsletter($clientId);
            
            return [
                'success' => true,
                'client_id' => $clientId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function authenticate($email, $password) {
        $client = $this->db->selectOne("SELECT * FROM clients WHERE email = ?", [$email]);
        
        if ($client && password_verify($password, $client['password'])) {
          
            unset($client['password']);
            return [
                'success' => true,
                'client' => $client
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Email ou mot de passe incorrect'
        ];
    }

    public function getById($id) {
        $client = $this->db->selectOne("SELECT * FROM clients WHERE id = ?", [$id]);
        
        if ($client) {
            unset($client['password']);
        }
        
        return $client;
    }

    public function update($id, $data) {
        try {

            if (isset($data['email'])) {
                $existing = $this->db->selectOne(
                    "SELECT id FROM clients WHERE email = ? AND id != ?",
                    [$data['email'], $id]
                );
                
                if ($existing) {
                    return [
                        'success' => false,
                        'error' => 'Cette adresse email est déjà utilisée'
                    ];
                }
            }
            
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            } else {
                unset($data['password']);
            }
            
            $rowsAffected = $this->db->update('clients', $data, 'id = :id', ['id' => $id]);
            
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

    public function getAll($limit = null, $offset = 0) {
        $limitClause = $limit ? "LIMIT " . (int)$offset . ", " . (int)$limit : "";
        
        $clients = $this->db->select("
            SELECT id, email, firstname, lastname, phone, address, loyalty_points, language, created_at
            FROM clients 
            ORDER BY created_at DESC 
            {$limitClause}
        ");
        
        return $clients;
    }

    public function count() {
        return $this->db->count('clients');
    }

    public function getOrders($clientId, $limit = null) {
        $limitClause = $limit ? "LIMIT " . (int)$limit : "";
        
        return $this->db->select("
            SELECT co.*, t.license_plate as truck_plate
            FROM client_orders co
            LEFT JOIN trucks t ON co.truck_id = t.id
            WHERE co.client_id = ?
            ORDER BY co.order_date DESC
            {$limitClause}
        ", [$clientId]);
    }

    public function getLoyaltyHistory($clientId, $limit = 20) {
        return $this->db->select("
            SELECT lh.*, co.id as order_number
            FROM loyalty_history lh
            LEFT JOIN client_orders co ON lh.order_id = co.id
            WHERE lh.client_id = ?
            ORDER BY lh.created_at DESC
            LIMIT ?
        ", [$clientId, $limit]);
    }
    
    public function subscribeToNewsletter($clientId) {
        try {
          
            $existing = $this->db->selectOne(
                "SELECT id FROM newsletter_subscribers WHERE client_id = ?",
                [$clientId]
            );
            
            if (!$existing) {
                $this->db->insert('newsletter_subscribers', [
                    'client_id' => $clientId,
                    'subscribed' => true
                ]);
            } else {
                
                $this->db->update(
                    'newsletter_subscribers', 
                    ['subscribed' => true], 
                    'client_id = :client_id', 
                    ['client_id' => $clientId]
                );
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    public function unsubscribeFromNewsletter($clientId) {
        try {
            $this->db->update(
                'newsletter_subscribers', 
                ['subscribed' => false], 
                'client_id = :client_id', 
                ['client_id' => $clientId]
            );
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function isSubscribedToNewsletter($clientId) {
        $subscription = $this->db->selectOne(
            "SELECT subscribed FROM newsletter_subscribers WHERE client_id = ?",
            [$clientId]
        );
        
        return $subscription ? (bool)$subscription['subscribed'] : false;
    }
    
    public function getStats($clientId) {
        $stats = [];
        
        $orderStats = $this->db->selectOne("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_spent,
                COALESCE(AVG(total_amount), 0) as avg_order,
                MAX(order_date) as last_order_date
            FROM client_orders 
            WHERE client_id = ?
        ", [$clientId]);
        
        $client = $this->getById($clientId);
        
        return [
            'total_orders' => (int)$orderStats['total_orders'],
            'total_spent' => (float)$orderStats['total_spent'],
            'avg_order' => (float)$orderStats['avg_order'],
            'last_order_date' => $orderStats['last_order_date'],
            'loyalty_points' => $client ? (int)$client['loyalty_points'] : 0
        ];
    }

    public function search($criteria) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($criteria['email'])) {
            $whereConditions[] = "email LIKE ?";
            $params[] = '%' . $criteria['email'] . '%';
        }
        
        if (!empty($criteria['name'])) {
            $whereConditions[] = "(firstname LIKE ? OR lastname LIKE ?)";
            $params[] = '%' . $criteria['name'] . '%';
            $params[] = '%' . $criteria['name'] . '%';
        }
        
        if (!empty($criteria['language'])) {
            $whereConditions[] = "language = ?";
            $params[] = $criteria['language'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        return $this->db->select("
            SELECT id, email, firstname, lastname, phone, loyalty_points, language, created_at
            FROM clients 
            {$whereClause}
            ORDER BY created_at DESC
        ", $params);
    }
    
    public function delete($id) {
        try {
            $rowsAffected = $this->db->delete('clients', 'id = ?', [$id]);
            
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