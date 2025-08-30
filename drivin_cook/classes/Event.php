<?php
require_once 'Database.php';

class Event {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        try {
            $validation = $this->validate($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => implode(', ', $validation['errors'])
                ];
            }
            
            $eventId = $this->db->insert('events', $data);
            
            return [
                'success' => true,
                'event_id' => $eventId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAll($status = null, $upcomingOnly = false) {
        $whereConditions = [];
        $params = [];
        
        if ($status) {
            $whereConditions[] = "status = ?";
            $params[] = $status;
        }
        
        if ($upcomingOnly) {
            $whereConditions[] = "event_date >= CURDATE()";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        return $this->db->select("
            SELECT e.*, 
                   (e.max_participants - e.current_participants) as places_remaining,
                   CASE 
                       WHEN e.event_date < CURDATE() THEN 'past'
                       WHEN e.event_date = CURDATE() THEN 'today'
                       ELSE 'future'
                   END as time_status
            FROM events e
            {$whereClause}
            ORDER BY e.event_date ASC, e.event_time ASC
        ", $params);
    }

    public function getById($id) {
        return $this->db->selectOne("
            SELECT e.*, 
                   (e.max_participants - e.current_participants) as places_remaining
            FROM events e 
            WHERE e.id = ?
        ", [$id]);
    }

    public function getUpcoming($limit = null) {
        $limitClause = $limit ? "LIMIT " . (int)$limit : "";
        
        return $this->db->select("
            SELECT e.*, 
                   (e.max_participants - e.current_participants) as places_remaining
            FROM events e 
            WHERE e.event_date >= CURDATE() 
            AND e.status = 'upcoming'
            ORDER BY e.event_date ASC, e.event_time ASC
            {$limitClause}
        ");
    }

    public function registerClient($eventId, $clientId) {
        try {
            $this->db->beginTransaction();
            
            $event = $this->getById($eventId);
            if (!$event) {
                throw new Exception("Événement non trouvé");
            }
            
            if ($event['status'] !== 'upcoming') {
                throw new Exception("Cet événement n'est plus ouvert aux inscriptions");
            }
            
            if ($event['places_remaining'] <= 0) {
                throw new Exception("Événement complet");
            }
            
            $existing = $this->db->selectOne("
                SELECT id FROM event_participants 
                WHERE event_id = ? AND client_id = ?
            ", [$eventId, $clientId]);
            
            if ($existing) {
                throw new Exception("Vous êtes déjà inscrit à cet événement");
            }
            
            $this->db->insert('event_participants', [
                'event_id' => $eventId,
                'client_id' => $clientId
            ]);
            
            $this->db->execute("
                UPDATE events 
                SET current_participants = current_participants + 1 
                WHERE id = ?
            ", [$eventId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Inscription réussie'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function unregisterClient($eventId, $clientId) {
        try {
            $this->db->beginTransaction();
            
            $participation = $this->db->selectOne("
                SELECT id FROM event_participants 
                WHERE event_id = ? AND client_id = ?
            ", [$eventId, $clientId]);
            
            if (!$participation) {
                throw new Exception("Vous n'êtes pas inscrit à cet événement");
            }
            
            $event = $this->getById($eventId);
            if (!$event) {
                throw new Exception("Événement non trouvé");
            }
            
            $eventDateTime = strtotime($event['event_date'] . ' ' . ($event['event_time'] ?: '00:00:00'));
            $now = time();
            $timeUntilEvent = $eventDateTime - $now;
            
            if ($timeUntilEvent < 24 * 3600) {
                throw new Exception("Désinscription impossible : moins de 24h avant l'événement");
            }
            
            $this->db->delete('event_participants', 'id = ?', [$participation['id']]);
            
            $this->db->execute("
                UPDATE events 
                SET current_participants = current_participants - 1 
                WHERE id = ?
            ", [$eventId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Désinscription réussie'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getClientEvents($clientId, $status = null) {
        $whereClause = $status ? "AND e.status = '{$status}'" : "";
        
        return $this->db->select("
            SELECT e.*, ep.registration_date,
                   (e.max_participants - e.current_participants) as places_remaining
            FROM events e
            JOIN event_participants ep ON e.id = ep.event_id
            WHERE ep.client_id = ? {$whereClause}
            ORDER BY e.event_date ASC
        ", [$clientId]);
    }

    public function getEventParticipants($eventId) {
        return $this->db->select("
            SELECT c.id, c.firstname, c.lastname, c.email, 
                   ep.registration_date
            FROM event_participants ep
            JOIN clients c ON ep.client_id = c.id
            WHERE ep.event_id = ?
            ORDER BY ep.registration_date ASC
        ", [$eventId]);
    }

    public function isClientRegistered($eventId, $clientId) {
        $participation = $this->db->selectOne("
            SELECT id FROM event_participants 
            WHERE event_id = ? AND client_id = ?
        ", [$eventId, $clientId]);
        
        return !empty($participation);
    }

    public function update($id, $data) {
        try {

            if (isset($data['max_participants'])) {
                $event = $this->getById($id);
                if ($event && $data['max_participants'] < $event['current_participants']) {
                    return [
                        'success' => false,
                        'error' => 'Le nombre maximum de participants ne peut pas être inférieur au nombre actuel d\'inscrits'
                    ];
                }
            }
            
            $rowsAffected = $this->db->update('events', $data, 'id = :id', ['id' => $id]);
            
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
            
            $participantCount = $this->db->count('event_participants', 'event_id = ?', [$id]);
            
            if ($participantCount > 0) {
                throw new Exception("Impossible de supprimer un événement avec des participants inscrits");
            }
            
            $rowsAffected = $this->db->delete('events', 'id = ?', [$id]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'rows_affected' => $rowsAffected
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function updateStatus($id, $status) {
        $validStatuses = ['upcoming', 'active', 'completed', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'error' => 'Statut invalide'
            ];
        }
        
        return $this->update($id, ['status' => $status]);
    }

    public function getStats() {
        $stats = [];
        
        $general = $this->db->selectOne("
            SELECT 
                COUNT(*) as total_events,
                COUNT(CASE WHEN status = 'upcoming' THEN 1 END) as upcoming_events,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_events,
                SUM(current_participants) as total_participants,
                AVG(current_participants) as avg_participants
            FROM events
        ");
        
        $monthlyEvents = $this->db->select("
            SELECT 
                DATE_FORMAT(event_date, '%Y-%m') as month,
                COUNT(*) as event_count,
                SUM(current_participants) as participants_count
            FROM events
            WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month
        ");
        
        return [
            'general' => $general,
            'monthly' => $monthlyEvents
        ];
    }

    public function getPopular($limit = 5) {
        return $this->db->select("
            SELECT e.*, 
                   (e.current_participants / e.max_participants * 100) as fill_percentage
            FROM events e
            WHERE e.status IN ('upcoming', 'completed')
            AND e.max_participants > 0
            ORDER BY fill_percentage DESC, e.current_participants DESC
            LIMIT ?
        ", [$limit]);
    }

    public function sendReminders($hoursBeforeEvent = 24) {

        $events = $this->db->select("
            SELECT DISTINCT e.*, 
                   COUNT(ep.client_id) as registered_count
            FROM events e
            JOIN event_participants ep ON e.id = ep.event_id
            WHERE e.status = 'upcoming'
            AND e.event_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            GROUP BY e.id
        ");
        
        $remindersSent = 0;
        
        foreach ($events as $event) {
            $participants = $this->getEventParticipants($event['id']);
            
            foreach ($participants as $participant) {

                $remindersSent++;
            }
        }
        
        return [
            'success' => true,
            'events_processed' => count($events),
            'reminders_sent' => $remindersSent
        ];
    }

    public function search($criteria) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($criteria['title'])) {
            $whereConditions[] = "title LIKE ?";
            $params[] = '%' . $criteria['title'] . '%';
        }
        
        if (!empty($criteria['location'])) {
            $whereConditions[] = "location LIKE ?";
            $params[] = '%' . $criteria['location'] . '%';
        }
        
        if (!empty($criteria['date_from'])) {
            $whereConditions[] = "event_date >= ?";
            $params[] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $whereConditions[] = "event_date <= ?";
            $params[] = $criteria['date_to'];
        }
        
        if (isset($criteria['price_max'])) {
            $whereConditions[] = "price <= ?";
            $params[] = $criteria['price_max'];
        }
        
        if (isset($criteria['has_places']) && $criteria['has_places']) {
            $whereConditions[] = "current_participants < max_participants";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        return $this->db->select("
            SELECT e.*, 
                   (e.max_participants - e.current_participants) as places_remaining
            FROM events e
            {$whereClause}
            ORDER BY e.event_date ASC
        ", $params);
    }

    private function validate($data) {
        $errors = [];
        
        if (empty($data['title'])) {
            $errors[] = 'Le titre est obligatoire';
        }
        
        if (empty($data['event_date'])) {
            $errors[] = 'La date est obligatoire';
        } elseif (strtotime($data['event_date']) < strtotime('today')) {
            $errors[] = 'La date ne peut pas être dans le passé';
        }
        
        if (empty($data['location'])) {
            $errors[] = 'Le lieu est obligatoire';
        }
        
        if (!isset($data['max_participants']) || $data['max_participants'] <= 0) {
            $errors[] = 'Le nombre maximum de participants doit être supérieur à 0';
        }
        
        if (isset($data['price']) && $data['price'] < 0) {
            $errors[] = 'Le prix ne peut pas être négatif';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function autoUpdateStatuses() {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        try {
            $this->db->beginTransaction();
            
            $this->db->execute("
                UPDATE events 
                SET status = 'completed' 
                WHERE status = 'upcoming' 
                AND (
                    event_date < ? 
                    OR (event_date = ? AND event_time < TIME(?))
                )
            ", [$today, $today, $now]);
            
            $this->db->execute("
                UPDATE events 
                SET status = 'active' 
                WHERE status = 'upcoming' 
                AND event_date = ?
                AND event_time <= TIME(?)
                AND DATE_ADD(CONCAT(event_date, ' ', event_time), INTERVAL 4 HOUR) > ?
            ", [$today, $now, $now]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Statuts mis à jour automatiquement'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>