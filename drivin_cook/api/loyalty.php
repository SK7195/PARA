<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest();
} else {
    jsonResponse(false, 'Méthode non autorisée');
}

function handleGetRequest() {
    $action = $_GET['action'] ?? '';
    $clientId = $_GET['client_id'] ?? null;
    
    if (!$clientId) {
        jsonResponse(false, 'ID client requis');
    }
    
    try {
        $pdo = getDBConnection();
        
        switch ($action) {
            case 'balance':
                getClientBalance($pdo, $clientId);
                break;
                
            case 'history':
                $limit = $_GET['limit'] ?? 20;
                getLoyaltyHistory($pdo, $clientId, $limit);
                break;
                
            case 'stats':
                getLoyaltyStats($pdo, $clientId);
                break;
                
            default:
                jsonResponse(false, 'Action requise (balance, history, stats)');
        }
        
    } catch (Exception $e) {
        error_log('Loyalty API GET Error: ' . $e->getMessage());
        jsonResponse(false, 'Erreur interne');
    }
}

function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if (!$action) {
        jsonResponse(false, 'Action requise');
    }
    
    try {
        $pdo = getDBConnection();
        
        switch ($action) {
            case 'add_points':
                addPoints($pdo, $input);
                break;
                
            case 'use_points':
                usePoints($pdo, $input);
                break;
                
            case 'calculate_discount':
                calculateDiscount($input);
                break;
                
            case 'process_order':
                processOrderPoints($pdo, $input);
                break;
                
            default:
                jsonResponse(false, 'Action non reconnue');
        }
        
    } catch (Exception $e) {
        error_log('Loyalty API POST Error: ' . $e->getMessage());
        jsonResponse(false, 'Erreur interne');
    }
}

function getClientBalance($pdo, $clientId) {
    $stmt = $pdo->prepare("SELECT loyalty_points FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $points = $stmt->fetchColumn();
    
    if ($points === false) {
        jsonResponse(false, 'Client non trouvé');
    }
    
    $availableDiscounts = floor($points / 100) * 5;
    $pointsForNextDiscount = (100 - ($points % 100)) % 100;
    
    jsonResponse(true, 'Solde récupéré', [
        'points' => (int)$points,
        'available_discount' => $availableDiscounts,
        'points_for_next_discount' => $pointsForNextDiscount
    ]);
}

function getLoyaltyHistory($pdo, $clientId, $limit) {
    $stmt = $pdo->prepare("
        SELECT lh.*, co.id as order_number
        FROM loyalty_history lh
        LEFT JOIN client_orders co ON lh.order_id = co.id
        WHERE lh.client_id = ?
        ORDER BY lh.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$clientId, (int)$limit]);
    $history = $stmt->fetchAll();
    
    jsonResponse(true, 'Historique récupéré', $history);
}

function getLoyaltyStats($pdo, $clientId) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN points_change > 0 THEN points_change ELSE 0 END) as total_earned,
            SUM(CASE WHEN points_change < 0 THEN ABS(points_change) ELSE 0 END) as total_used,
            COUNT(*) as total_transactions,
            MIN(created_at) as first_transaction
        FROM loyalty_history
        WHERE client_id = ?
    ");
    $stmt->execute([$clientId]);
    $stats = $stmt->fetch();
    
    $client = $pdo->prepare("SELECT loyalty_points FROM clients WHERE id = ?");
    $client->execute([$clientId]);
    $currentPoints = $client->fetchColumn();
    
    jsonResponse(true, 'Statistiques récupérées', [
        'current_points' => (int)$currentPoints,
        'total_earned' => (int)$stats['total_earned'],
        'total_used' => (int)$stats['total_used'],
        'total_transactions' => (int)$stats['total_transactions'],
        'member_since' => $stats['first_transaction'],
        'total_saved' => round(($stats['total_used'] / 100) * 5, 2)
    ]);
}

function addPoints($pdo, $data) {
    $clientId = $data['client_id'] ?? null;
    $points = $data['points'] ?? 0;
    $reason = $data['reason'] ?? 'Points ajoutés';
    $orderId = $data['order_id'] ?? null;
    
    if (!$clientId || $points <= 0) {
        jsonResponse(false, 'Données invalides');
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE clients SET loyalty_points = loyalty_points + ? WHERE id = ?");
        $stmt->execute([$points, $clientId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Client non trouvé');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO loyalty_history (client_id, points_change, reason, order_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$clientId, $points, $reason, $orderId]);
        
        $stmt = $pdo->prepare("SELECT loyalty_points FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $newBalance = $stmt->fetchColumn();
        
        $pdo->commit();
        
        jsonResponse(true, "Points ajoutés avec succès", [
            'points_added' => $points,
            'new_balance' => (int)$newBalance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        jsonResponse(false, 'Erreur lors de l\'ajout des points: ' . $e->getMessage());
    }
}

function usePoints($pdo, $data) {
    $clientId = $data['client_id'] ?? null;
    $points = $data['points'] ?? 0;
    $reason = $data['reason'] ?? 'Points utilisés';
    $orderId = $data['order_id'] ?? null;
    
    if (!$clientId || $points <= 0) {
        jsonResponse(false, 'Données invalides');
    }
    
    if ($points % 100 !== 0) {
        jsonResponse(false, 'Les points doivent être utilisés par tranches de 100');
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT loyalty_points FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $currentPoints = $stmt->fetchColumn();
        
        if ($currentPoints === false) {
            throw new Exception('Client non trouvé');
        }
        
        if ($currentPoints < $points) {
            throw new Exception('Points insuffisants');
        }
        
        $stmt = $pdo->prepare("UPDATE clients SET loyalty_points = loyalty_points - ? WHERE id = ?");
        $stmt->execute([$points, $clientId]);
        
        $stmt = $pdo->prepare("
            INSERT INTO loyalty_history (client_id, points_change, reason, order_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$clientId, -$points, $reason, $orderId]);
        
        $newBalance = $currentPoints - $points;
        $discount = ($points / 100) * 5;
        
        $pdo->commit();
        
        jsonResponse(true, "Points utilisés avec succès", [
            'points_used' => $points,
            'discount_applied' => $discount,
            'new_balance' => $newBalance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        jsonResponse(false, 'Erreur: ' . $e->getMessage());
    }
}

function calculateDiscount($data) {
    $points = $data['points'] ?? 0;
    
    if ($points < 100) {
        jsonResponse(true, 'Calcul effectué', [
            'points' => $points,
            'discount' => 0,
            'message' => 'Minimum 100 points requis pour une réduction'
        ]);
    }
    
    $discountGroups = floor($points / 100);
    $discount = $discountGroups * 5;
    $usablePoints = $discountGroups * 100;
    
    jsonResponse(true, 'Calcul effectué', [
        'total_points' => $points,
        'usable_points' => $usablePoints,
        'discount' => $discount,
        'remaining_points' => $points - $usablePoints
    ]);
}

function processOrderPoints($pdo, $data) {
    $clientId = $data['client_id'] ?? null;
    $orderAmount = $data['order_amount'] ?? 0;
    $orderId = $data['order_id'] ?? null;
    
    if (!$clientId || $orderAmount <= 0) {
        jsonResponse(false, 'Données invalides');
    }
    
    $pointsEarned = floor($orderAmount);
    
    if ($pointsEarned <= 0) {
        jsonResponse(true, 'Aucun point à attribuer', [
            'points_earned' => 0,
            'order_amount' => $orderAmount
        ]);
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE clients SET loyalty_points = loyalty_points + ? WHERE id = ?");
        $stmt->execute([$pointsEarned, $clientId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Client non trouvé');
        }
        
        $reason = $orderId ? "Points gagnés pour commande #{$orderId}" : "Points gagnés pour achat de {$orderAmount}€";
        $stmt = $pdo->prepare("
            INSERT INTO loyalty_history (client_id, points_change, reason, order_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$clientId, $pointsEarned, $reason, $orderId]);
        
        $stmt = $pdo->prepare("SELECT loyalty_points FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $newBalance = $stmt->fetchColumn();
        
        $pdo->commit();
        
        jsonResponse(true, "Points attribués pour la commande", [
            'order_amount' => $orderAmount,
            'points_earned' => $pointsEarned,
            'new_balance' => (int)$newBalance,
            'next_discount_in' => 100 - ($newBalance % 100)
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        jsonResponse(false, 'Erreur lors du traitement: ' . $e->getMessage());
    }
}
?>