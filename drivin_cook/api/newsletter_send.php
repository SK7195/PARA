<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

function jsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée');
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = getDBConnection();
    
    $action = $input['action'] ?? 'send_monthly';
    
    switch ($action) {
        case 'send_monthly':
            $result = sendMonthlyNewsletter($pdo);
            jsonResponse($result['success'], $result['message'], $result['data']);
            break;
            
        case 'send_event':
            $eventId = $input['event_id'] ?? null;
            if (!$eventId) {
                jsonResponse(false, 'ID événement manquant');
            }
            $result = sendEventNewsletter($pdo, $eventId);
            jsonResponse($result['success'], $result['message'], $result['data']);
            break;
            
        default:
            jsonResponse(false, 'Action non reconnue');
    }
    
} catch (Exception $e) {
    error_log('Newsletter API Error: ' . $e->getMessage());
    jsonResponse(false, 'Erreur interne du serveur');
}

function sendMonthlyNewsletter($pdo) {
    try {

        $stmt = $pdo->query("
            SELECT c.id, c.email, c.firstname, c.lastname, c.language
            FROM clients c
            JOIN newsletter_subscribers ns ON c.id = ns.client_id
            WHERE ns.subscribed = 1
        ");
        $subscribers = $stmt->fetchAll();
        
        if (empty($subscribers)) {
            return [
                'success' => true,
                'message' => 'Aucun abonné trouvé',
                'data' => ['sent_count' => 0]
            ];
        }
        
        $monthlyData = getMonthlyData($pdo);
        
        $sentCount = 0;
        $errors = [];
        
        foreach ($subscribers as $subscriber) {
            $emailContent = generateNewsletterContent($subscriber, $monthlyData);
            
            $emailSent = sendEmailSimulation(
                $subscriber['email'],
                'Newsletter Driv\'n Cook - ' . date('F Y'),
                $emailContent
            );
            
            if ($emailSent) {
                $sentCount++;
                logNewsletterSent($pdo, $subscriber['id'], 'monthly');
            } else {
                $errors[] = "Échec envoi pour " . $subscriber['email'];
            }
        }
        
        return [
            'success' => true,
            'message' => "Newsletter envoyée à {$sentCount} abonnés",
            'data' => [
                'sent_count' => $sentCount,
                'total_subscribers' => count($subscribers),
                'errors' => $errors
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage(),
            'data' => null
        ];
    }
}

function sendEventNewsletter($pdo, $eventId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if (!$event) {
            return [
                'success' => false,
                'message' => 'Événement non trouvé',
                'data' => null
            ];
        }
        
        $stmt = $pdo->query("
            SELECT c.id, c.email, c.firstname, c.language
            FROM clients c
            JOIN newsletter_subscribers ns ON c.id = ns.client_id
            WHERE ns.subscribed = 1
        ");
        $subscribers = $stmt->fetchAll();
        
        $sentCount = 0;
        
        foreach ($subscribers as $subscriber) {
            $emailContent = generateEventEmailContent($subscriber, $event);
            
            if (sendEmailSimulation(
                $subscriber['email'],
                'Nouvel événement Driv\'n Cook !',
                $emailContent
            )) {
                $sentCount++;
                logNewsletterSent($pdo, $subscriber['id'], 'event', $eventId);
            }
        }
        
        return [
            'success' => true,
            'message' => "Notification événement envoyée à {$sentCount} abonnés",
            'data' => ['sent_count' => $sentCount, 'event_title' => $event['title']]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage(),
            'data' => null
        ];
    }
}

function getMonthlyData($pdo) {
    $currentMonth = date('Y-m');

    $newMenus = $pdo->query("
        SELECT name_fr, name_en, name_es, price, category
        FROM menus 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$currentMonth}'
        AND available = 1
        LIMIT 3
    ")->fetchAll();
    
    $upcomingEvents = $pdo->query("
        SELECT title, event_date, location, price
        FROM events 
        WHERE event_date >= CURDATE()
        AND status = 'upcoming'
        ORDER BY event_date
        LIMIT 2
    ")->fetchAll();
    
    return [
        'new_menus' => $newMenus,
        'upcoming_events' => $upcomingEvents,
        'month_name' => date('F Y'),
        'discount_offer' => '10% de réduction avec le code NEWSLETTER10'
    ];
}

function generateNewsletterContent($subscriber, $data) {
    $lang = $subscriber['language'] ?? 'fr';
    
    $content = "
    <h2>Newsletter Driv'n Cook - {$data['month_name']}</h2>
    
    <p>Bonjour {$subscriber['firstname']},</p>
    
    <h3>🍔 Nouvelles saveurs ce mois-ci :</h3>
    ";
    
    foreach ($data['new_menus'] as $menu) {
        $name = $menu["name_{$lang}"] ?? $menu['name_fr'];
        $content .= "<p>• {$name} - {$menu['price']}€</p>";
    }
    
    if (!empty($data['upcoming_events'])) {
        $content .= "<h3>📅 Événements à venir :</h3>";
        foreach ($data['upcoming_events'] as $event) {
            $date = date('d/m/Y', strtotime($event['event_date']));
            $content .= "<p>• {$event['title']} - {$date} à {$event['location']}</p>";
        }
    }
    
    $content .= "
    <h3>🎁 Offre spéciale :</h3>
    <p>{$data['discount_offer']}</p>
    
    <p>À bientôt dans nos food trucks !</p>
    <p>L'équipe Driv'n Cook</p>
    ";
    
    return $content;
}

function generateEventEmailContent($subscriber, $event) {
    $eventDate = date('d/m/Y', strtotime($event['event_date']));
    
    return "
    <h2>Nouvel événement Driv'n Cook !</h2>
    
    <p>Bonjour {$subscriber['firstname']},</p>
    
    <h3>🎉 {$event['title']}</h3>
    <p>{$event['description']}</p>
    
    <p><strong>📅 Date :</strong> {$eventDate}</p>
    <p><strong>📍 Lieu :</strong> {$event['location']}</p>
    <p><strong>💰 Prix :</strong> " . ($event['price'] > 0 ? $event['price'] . "€" : "Gratuit") . "</p>
    
    <p>Places limitées ! Inscrivez-vous dès maintenant.</p>
    
    <p>À bientôt,<br>L'équipe Driv'n Cook</p>
    ";
}

function sendEmailSimulation($to, $subject, $content) {
    
    error_log("Email simulé envoyé à: {$to}");
    error_log("Sujet: {$subject}");
    
    return rand(1, 100) <= 95;
}

function logNewsletterSent($pdo, $clientId, $type, $eventId = null) {
    $stmt = $pdo->prepare("
        INSERT INTO newsletter_history (client_id, type, event_id, sent_at) 
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE sent_at = NOW()
    ");
    
    try {
        $stmt->execute([$clientId, $type, $eventId]);
    } catch (Exception $e) {
        createNewsletterHistoryTable($pdo);
    }
}

function createNewsletterHistoryTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS newsletter_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            type ENUM('monthly', 'event') NOT NULL,
            event_id INT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        )
    ");
}
?>