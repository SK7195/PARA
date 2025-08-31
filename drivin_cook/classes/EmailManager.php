<?php
// classes/EmailManager.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailManager {
    private $mailer;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initMailer();
    }
    
    private function initMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Configuration SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->CharSet = EMAIL_CHARSET;
            
            // Configuration d'envoi
            $this->mailer->setFrom(FROM_EMAIL, FROM_NAME);
            $this->mailer->addReplyTo(REPLY_TO, FROM_NAME);
            
            // Debug si activé
            if (EMAIL_DEBUG) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
        } catch (Exception $e) {
            error_log("Erreur configuration PHPMailer: " . $e->getMessage());
        }
    }
    
    public function sendNewsletter($type, $eventId = null) {
        try {
            // Récupérer les abonnés
            $subscribers = $this->getActiveSubscribers();
            
            if (empty($subscribers)) {
                return [
                    'success' => false,
                    'message' => 'Aucun abonné trouvé',
                    'data' => ['sent_count' => 0]
                ];
            }
            
            // Préparer les données selon le type
            $data = $this->prepareNewsletterData($type, $eventId);
            
            $sentCount = 0;
            $errors = [];
            
            foreach ($subscribers as $subscriber) {
                // Personnaliser le contenu pour chaque abonné
                $personalizedData = $this->personalizeData($data, $subscriber);
                $template = getEmailTemplate($type, $personalizedData);
                
                if ($this->sendEmailToSubscriber($subscriber, $template)) {
                    $sentCount++;
                    $this->logNewsletterSent($subscriber['id'], $type, $eventId);
                } else {
                    $errors[] = "Échec envoi pour " . $subscriber['email'];
                }
            }
            
            return [
                'success' => true,
                'message' => "Newsletter envoyée à $sentCount abonné(s)",
                'data' => [
                    'sent_count' => $sentCount,
                    'total_subscribers' => count($subscribers),
                    'errors' => $errors
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Erreur envoi newsletter: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    private function getActiveSubscribers() {
        $stmt = $this->pdo->query("
            SELECT c.id, c.email, c.firstname, c.lastname, c.language
            FROM clients c
            JOIN newsletter_subscribers ns ON c.id = ns.client_id
            WHERE ns.subscribed = 1
            ORDER BY c.firstname
        ");
        return $stmt->fetchAll();
    }
    
    private function prepareNewsletterData($type, $eventId = null) {
        switch ($type) {
            case 'newsletter_monthly':
                return $this->getMonthlyNewsletterData();
                
            case 'newsletter_event':
                return $this->getEventNewsletterData($eventId);
                
            default:
                return [];
        }
    }
    
    private function getMonthlyNewsletterData() {
        $currentMonth = date('Y-m');
        
        // Nouveaux menus du mois
        $stmt = $this->pdo->query("
            SELECT name_fr as name, description_fr as description, price, category
            FROM menus 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
            AND available = 1
            LIMIT 5
        ");
        $newMenus = $stmt->fetchAll();
        
        // Événements à venir
        $stmt = $this->pdo->query("
            SELECT title, event_date, event_time, location, price, description
            FROM events 
            WHERE event_date >= CURDATE()
            AND status = 'upcoming'
            ORDER BY event_date
            LIMIT 3
        ");
        $upcomingEvents = $stmt->fetchAll();
        
        return [
            'new_menus' => $newMenus,
            'upcoming_events' => $upcomingEvents,
            'discount_offer' => '10% de réduction avec le code NEWSLETTER10',
            'month_name' => date('F Y')
        ];
    }
    
    private function getEventNewsletterData($eventId) {
        if (!$eventId) {
            throw new Exception('ID événement manquant');
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if (!$event) {
            throw new Exception('Événement non trouvé');
        }
        
        return [
            'event' => $event
        ];
    }
    
    private function personalizeData($data, $subscriber) {
        $data['firstname'] = $subscriber['firstname'];
        $data['lastname'] = $subscriber['lastname'];
        $data['language'] = $subscriber['language'];
        
        return $data;
    }
    
    private function sendEmailToSubscriber($subscriber, $template) {
        try {
            // Reset des destinataires
            $this->mailer->clearAddresses();
            $this->mailer->clearAllRecipients();
            
            // Ajouter le destinataire
            $this->mailer->addAddress($subscriber['email'], $subscriber['firstname'] . ' ' . $subscriber['lastname']);
            
            // Configurer le message
            $this->mailer->Subject = $template['subject'];
            $this->mailer->isHTML(true);
            $this->mailer->Body = $template['html'];
            $this->mailer->AltBody = $template['text'];
            
            // Envoyer
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Erreur envoi email à {$subscriber['email']}: " . $e->getMessage());
            return false;
        }
    }
    
    private function logNewsletterSent($clientId, $type, $eventId = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO newsletter_history (client_id, type, event_id, sent_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE sent_at = NOW()
            ");
            $stmt->execute([$clientId, $type, $eventId]);
        } catch (Exception $e) {
            // Créer la table si elle n'existe pas
            $this->createNewsletterHistoryTable();
            // Réessayer
            $stmt = $this->pdo->prepare("
                INSERT INTO newsletter_history (client_id, type, event_id, sent_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$clientId, $type, $eventId]);
        }
    }
    
    private function createNewsletterHistoryTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS newsletter_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                type ENUM('newsletter_monthly', 'newsletter_event') NOT NULL,
                event_id INT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                UNIQUE KEY unique_client_newsletter (client_id, type, event_id, DATE(sent_at))
            )
        ");
    }
    
    public function sendCustomEmail($to, $subject, $htmlBody, $textBody = null) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAllRecipients();
            
            // Supporter plusieurs destinataires
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $this->mailer->addAddress($name);
                    } else {
                        $this->mailer->addAddress($email, $name);
                    }
                }
            } else {
                $this->mailer->addAddress($to);
            }
            
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlBody;
            
            if ($textBody) {
                $this->mailer->AltBody = $textBody;
            }
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Erreur envoi email personnalisé: " . $e->getMessage());
            return false;
        }
    }
    
    public function testEmailConnection() {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(FROM_EMAIL, 'Test');
            $this->mailer->Subject = 'Test de connexion SMTP - Driv\'n Cook';
            $this->mailer->Body = '<h1>Test réussi !</h1><p>La configuration SMTP fonctionne correctement.</p>';
            $this->mailer->AltBody = 'Test réussi ! La configuration SMTP fonctionne correctement.';
            
            $result = $this->mailer->send();
            
            return [
                'success' => $result,
                'message' => $result ? 'Test réussi' : 'Test échoué'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }
    
    public function getNewsletterStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(DISTINCT nh.client_id) as total_recipients,
                    COUNT(*) as total_sent,
                    COUNT(CASE WHEN nh.type = 'newsletter_monthly' THEN 1 END) as monthly_sent,
                    COUNT(CASE WHEN nh.type = 'newsletter_event' THEN 1 END) as event_sent,
                    MAX(nh.sent_at) as last_sent
                FROM newsletter_history nh
                WHERE nh.sent_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            return [
                'total_recipients' => 0,
                'total_sent' => 0,
                'monthly_sent' => 0,
                'event_sent' => 0,
                'last_sent' => null
            ];
        }
    }
}
?>