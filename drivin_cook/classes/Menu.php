<?php
require_once 'Database.php';

class Menu {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        try {
            $menuId = $this->db->insert('menus', $data);
            
            return [
                'success' => true,
                'menu_id' => $menuId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAll($language = 'fr', $category = null, $availableOnly = true) {
        $whereConditions = [];
        $params = [];
        
        if ($availableOnly) {
            $whereConditions[] = "available = 1";
        }
        
        if ($category) {
            $whereConditions[] = "category = ?";
            $params[] = $category;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $menus = $this->db->select("
            SELECT 
                id,
                name_{$language} as name,
                description_{$language} as description,
                name_fr, name_en, name_es,
                description_fr, description_en, description_es,
                price,
                category,
                image_url,
                available,
                created_at
            FROM menus 
            {$whereClause}
            ORDER BY category, name_{$language}
        ", $params);
        
        return $menus;
    }
 
    public function getById($id, $language = 'fr') {
        return $this->db->selectOne("
            SELECT 
                id,
                name_{$language} as name,
                description_{$language} as description,
                name_fr, name_en, name_es,
                description_fr, description_en, description_es,
                price,
                category,
                image_url,
                available,
                created_at
            FROM menus 
            WHERE id = ?
        ", [$id]);
    }
 
    public function getByCategory($category, $language = 'fr', $availableOnly = true) {
        $whereConditions = ["category = ?"];
        $params = [$category];
        
        if ($availableOnly) {
            $whereConditions[] = "available = 1";
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->db->select("
            SELECT 
                id,
                name_{$language} as name,
                description_{$language} as description,
                price,
                category,
                image_url,
                available
            FROM menus 
            {$whereClause}
            ORDER BY name_{$language}
        ", $params);
    }

    public function getCategories() {
        return $this->db->select("
            SELECT DISTINCT category, COUNT(*) as count
            FROM menus 
            WHERE available = 1
            GROUP BY category
            ORDER BY category
        ");
    }

    public function update($id, $data) {
        try {
            $rowsAffected = $this->db->update('menus', $data, 'id = :id', ['id' => $id]);
            
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
            $rowsAffected = $this->db->delete('menus', 'id = ?', [$id]);
            
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

    public function toggleAvailability($id) {
        try {
            $menu = $this->db->selectOne("SELECT available FROM menus WHERE id = ?", [$id]);
            
            if ($menu) {
                $newStatus = !$menu['available'];
                $this->db->update('menus', ['available' => $newStatus], 'id = :id', ['id' => $id]);
                
                return [
                    'success' => true,
                    'new_status' => $newStatus
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Menu non trouvé'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function search($query, $language = 'fr', $category = null) {
        $whereConditions = [
            "(name_{$language} LIKE ? OR description_{$language} LIKE ?)"
        ];
        $params = ["%{$query}%", "%{$query}%"];
        
        if ($category) {
            $whereConditions[] = "category = ?";
            $params[] = $category;
        }
        
        $whereConditions[] = "available = 1";
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->db->select("
            SELECT 
                id,
                name_{$language} as name,
                description_{$language} as description,
                price,
                category,
                image_url,
                available
            FROM menus 
            {$whereClause}
            ORDER BY name_{$language}
        ", $params);
    }

    public function getPopular($language = 'fr', $limit = 5) {
        return $this->db->select("
            SELECT 
                m.id,
                m.name_{$language} as name,
                m.description_{$language} as description,
                m.price,
                m.category,
                m.image_url,
                COUNT(coi.id) as order_count
            FROM menus m
            LEFT JOIN client_order_items coi ON m.id = coi.menu_id
            WHERE m.available = 1
            GROUP BY m.id
            ORDER BY order_count DESC, m.name_{$language}
            LIMIT ?
        ", [$limit]);
    }

    public function getStats() {
        $stats = [];
        
        $general = $this->db->selectOne("
            SELECT 
                COUNT(*) as total_menus,
                COUNT(CASE WHEN available = 1 THEN 1 END) as available_menus,
                AVG(price) as avg_price,
                MIN(price) as min_price,
                MAX(price) as max_price
            FROM menus
        ");
        
        $categories = $this->db->select("
            SELECT 
                category,
                COUNT(*) as menu_count,
                AVG(price) as avg_price,
                COUNT(CASE WHEN available = 1 THEN 1 END) as available_count
            FROM menus
            GROUP BY category
            ORDER BY category
        ");
        
        return [
            'general' => $general,
            'categories' => $categories
        ];
    }
 
    public function duplicate($id) {
        try {
            $menu = $this->db->selectOne("SELECT * FROM menus WHERE id = ?", [$id]);
            
            if ($menu) {
                unset($menu['id']);
                $menu['name_fr'] .= ' (Copie)';
                $menu['name_en'] .= ' (Copy)';
                $menu['name_es'] .= ' (Copia)';
                $menu['available'] = false;
                
                $newId = $this->db->insert('menus', $menu);
                
                return [
                    'success' => true,
                    'new_id' => $newId
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Menu original non trouvé'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function validate($data) {
        $errors = [];
        
        if (empty($data['name_fr'])) {
            $errors[] = 'Le nom en français est obligatoire';
        }
        
        if (empty($data['name_en'])) {
            $errors[] = 'Le nom en anglais est obligatoire';
        }
        
        if (empty($data['name_es'])) {
            $errors[] = 'Le nom en espagnol est obligatoire';
        }
        
        if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
            $errors[] = 'Le prix doit être un nombre positif';
        }
        
        $validCategories = ['burger', 'salad', 'drink', 'dessert', 'starter'];
        if (empty($data['category']) || !in_array($data['category'], $validCategories)) {
            $errors[] = 'La catégorie doit être une valeur valide';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>