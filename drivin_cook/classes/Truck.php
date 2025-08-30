<?php
require_once 'Database.php';

class Truck {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($data) {
        try {
         
            if ($this->db->exists('trucks', 'license_plate = ?', [$data['license_plate']])) {
                return [
                    'success' => false,
                    'error' => 'Cette plaque d\'immatriculation existe déjà'
                ];
            }
            
            $truckId = $this->db->insert('trucks', $data);
            
            return [
                'success' => true,
                'truck_id' => $truckId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAll($includeDetails = false) {
        $sql = "
            SELECT t.*, 
                   f.name as franchisee_name, 
                   f.company_name
                   " . ($includeDetails ? ",
                   DATEDIFF(CURRENT_DATE, t.last_maintenance) as days_since_maintenance
                   " : "") . "
            FROM trucks t
            LEFT JOIN franchisees f ON t.franchisee_id = f.id
            ORDER BY t.created_at DESC
        ";
        
        return $this->db->select($sql);
    }

    public function getById($id) {
        $sql = "
            SELECT t.*, 
                   f.name as franchisee_name, 
                   f.company_name
            FROM trucks t
            LEFT JOIN franchisees f ON t.franchisee_id = f.id
            WHERE t.id = ?
        ";
        
        return $this->db->selectOne($sql, [$id]);
    }
    public function getByFranchisee($franchiseeId) {
        return $this->db->select("
            SELECT * 
            FROM trucks 
            WHERE franchisee_id = ? 
            ORDER BY created_at DESC
        ", [$franchiseeId]);
    }

    public function getAvailable() {
        return $this->db->select("
            SELECT * 
            FROM trucks 
            WHERE status = 'available' 
            ORDER BY license_plate
        ");
    }

    public function update($id, $data) {
        try {

            if (isset($data['license_plate'])) {
                $existing = $this->db->selectOne(
                    "SELECT id FROM trucks WHERE license_plate = ? AND id != ?",
                    [$data['license_plate'], $id]
                );
                
                if ($existing) {
                    return [
                        'success' => false,
                        'error' => 'Cette plaque d\'immatriculation existe déjà'
                    ];
                }
            }
            
            $rowsAffected = $this->db->update('trucks', $data, 'id = :id', ['id' => $id]);
            
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
            $rowsAffected = $this->db->delete('trucks', 'id = ?', [$id]);
            
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

    public function assignToFranchisee($truckId, $franchiseeId) {
        try {
            $this->db->beginTransaction();
            
            $truck = $this->getById($truckId);
            if (!$truck) {
                throw new Exception("Camion non trouvé");
            }
            
            if ($truck['status'] !== 'available') {
                throw new Exception("Ce camion n'est pas disponible");
            }
            
            $this->db->update('trucks', [
                'franchisee_id' => $franchiseeId,
                'status' => 'assigned'
            ], 'id = :id', ['id' => $truckId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Camion assigné avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function unassign($truckId) {
        try {
            $this->db->update('trucks', [
                'franchisee_id' => null,
                'status' => 'available'
            ], 'id = :id', ['id' => $truckId]);
            
            return [
                'success' => true,
                'message' => 'Camion libéré avec succès'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function changeStatus($id, $status) {
        $validStatuses = ['available', 'assigned', 'maintenance', 'broken'];
        
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'error' => 'Statut invalide'
            ];
        }
        
        return $this->update($id, ['status' => $status]);
    }

    public function scheduleMaintenance($id, $maintenanceDate = null) {
        $data = [
            'status' => 'maintenance'
        ];
        
        if ($maintenanceDate) {
            $data['last_maintenance'] = $maintenanceDate;
        }
        
        return $this->update($id, $data);
    }

    public function completeMaintenance($id) {
        return $this->update($id, [
            'status' => 'available',
            'last_maintenance' => date('Y-m-d')
        ]);
    }

    public function getStats() {
        $sql = "
            SELECT 
                status,
                COUNT(*) as count
            FROM trucks
            GROUP BY status
        ";
        
        $results = $this->db->select($sql);
        
        $stats = [
            'total' => 0,
            'available' => 0,
            'assigned' => 0,
            'maintenance' => 0,
            'broken' => 0
        ];
        
        foreach ($results as $result) {
            $stats[$result['status']] = (int)$result['count'];
            $stats['total'] += (int)$result['count'];
        }
        
        return $stats;
    }

    public function getMaintenanceNeeded($daysSinceLastMaintenance = 90) {
        $sql = "
            SELECT t.*, f.name as franchisee_name,
                   DATEDIFF(CURRENT_DATE, COALESCE(t.last_maintenance, t.created_at)) as days_since_maintenance
            FROM trucks t
            LEFT JOIN franchisees f ON t.franchisee_id = f.id
            WHERE DATEDIFF(CURRENT_DATE, COALESCE(t.last_maintenance, t.created_at)) > ?
            AND t.status NOT IN ('maintenance', 'broken')
            ORDER BY days_since_maintenance DESC
        ";
        
        return $this->db->select($sql, [$daysSinceLastMaintenance]);
    }
    
    public function getHistory($truckId) {

        return $this->getById($truckId);
    }
    
    public function search($criteria) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($criteria['license_plate'])) {
            $whereConditions[] = "t.license_plate LIKE ?";
            $params[] = '%' . $criteria['license_plate'] . '%';
        }
        
        if (!empty($criteria['status'])) {
            $whereConditions[] = "t.status = ?";
            $params[] = $criteria['status'];
        }
        
        if (!empty($criteria['franchisee_id'])) {
            $whereConditions[] = "t.franchisee_id = ?";
            $params[] = $criteria['franchisee_id'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "
            SELECT t.*, f.name as franchisee_name, f.company_name
            FROM trucks t
            LEFT JOIN franchisees f ON t.franchisee_id = f.id
            {$whereClause}
            ORDER BY t.created_at DESC
        ";
        
        return $this->db->select($sql, $params);
    }
}
?>