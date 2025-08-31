<?php
require_once '../config/database.php';
require_once '../classes/ClientOrder.php';
require_once '../classes/Client.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function jsonResponse($success, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Méthode non autorisée', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$requiredFields = ['action'];
if (!$input || !isset($input['action'])) {
    jsonResponse(false, null, 'Action manquante', 400);
}

$action = $input['action'];
$clientOrder = new ClientOrder();
$client = new Client();

try {
    switch ($action) {
        case 'process_payment':
            processPayment($input, $clientOrder, $client);
            break;
            
        case 'simulate_card_payment':
            simulateCardPayment($input);
            break;
            
        case 'validate_loyalty_payment':
            validateLoyaltyPayment($input, $client);
            break;
            
        case 'refund_payment':
            refundPayment($input, $clientOrder);
            break;
            
        case 'get_payment_status':
            getPaymentStatus($input, $clientOrder);
            break;
            
        default:
            jsonResponse(false, null, 'Action non reconnue', 400);
    }
    
} catch (Exception $e) {
    error_log('Payment API Error: ' . $e->getMessage());
    jsonResponse(false, null, 'Erreur interne du serveur', 500);
}

function processPayment($input, $clientOrder, $client) {
    $requiredFields = ['order_id', 'payment_method', 'amount'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            jsonResponse(false, null, "Champ manquant: {$field}", 400);
        }
    }
    
    $orderId = (int)$input['order_id'];
    $paymentMethod = $input['payment_method'];
    $amount = (float)$input['amount'];
    $clientId = $input['client_id'] ?? null;
    
    $order = $clientOrder->getById($orderId, $clientId);
    if (!$order) {
        jsonResponse(false, null, 'Commande non trouvée', 404);
    }
    
    if ($order['status'] !== 'pending') {
        jsonResponse(false, null, 'Cette commande ne peut plus être payée', 400);
    }
    
    if (abs($amount - $order['total_amount']) > 0.01) {
        jsonResponse(false, null, 'Montant incorrect', 400);
    }
    

    switch ($paymentMethod) {
        case 'card':
            $paymentResult = processCardPayment($input, $order);
            break;
            
        case 'cash':
            $paymentResult = processCashPayment($order);
            break;
            
        case 'loyalty_points':
            $paymentResult = processLoyaltyPayment($input, $order, $client);
            break;
            
        default:
            jsonResponse(false, null, 'Méthode de paiement non supportée', 400);
    }
    
    if ($paymentResult['success']) {

        $updateResult = $clientOrder->updateStatus($orderId, 'confirmed');
        
        if ($updateResult['success']) {
            jsonResponse(true, [
                'order_id' => $orderId,
                'payment_id' => $paymentResult['payment_id'],
                'amount_paid' => $amount,
                'payment_method' => $paymentMethod,
                'status' => 'confirmed'
            ], 'Paiement traité avec succès');
        } else {
            jsonResponse(false, null, 'Erreur lors de la confirmation de commande', 500);
        }
    } else {
        jsonResponse(false, $paymentResult, $paymentResult['message'], 400);
    }
}

function processCardPayment($input, $order) {

    $cardNumber = $input['card_number'] ?? '';
    $expiryMonth = $input['expiry_month'] ?? '';
    $expiryYear = $input['expiry_year'] ?? '';
    $cvv = $input['cvv'] ?? '';
    
    if (strlen($cardNumber) < 16 || strlen($cvv) < 3) {
        return [
            'success' => false,
            'message' => 'Données de carte invalides'
        ];
    }
    
    usleep(500000);
    
    $lastDigit = substr($cardNumber, -1);
    
    if ($lastDigit === '1') {
        return [
            'success' => false,
            'message' => 'Carte refusée - Fonds insuffisants',
            'error_code' => 'INSUFFICIENT_FUNDS'
        ];
    } elseif ($lastDigit === '2') {
        return [
            'success' => false,
            'message' => 'Carte expirée',
            'error_code' => 'EXPIRED_CARD'
        ];
    } elseif ($lastDigit === '3') {
        return [
            'success' => false,
            'message' => 'Transaction refusée par la banque',
            'error_code' => 'BANK_DECLINED'
        ];
    }
    
    $paymentId = 'PAY_' . strtoupper(uniqid());
    
    return [
        'success' => true,
        'payment_id' => $paymentId,
        'transaction_id' => 'TXN_' . time(),
        'authorization_code' => 'AUTH_' . rand(100000, 999999),
        'card_last4' => substr($cardNumber, -4),
        'processed_at' => date('Y-m-d H:i:s')
    ];
}

function processCashPayment($order) {

    $paymentId = 'CASH_' . strtoupper(uniqid());
    
    return [
        'success' => true,
        'payment_id' => $paymentId,
        'amount_received' => $order['total_amount'],
        'change_due' => 0.00,
        'processed_at' => date('Y-m-d H:i:s')
    ];
}

function processLoyaltyPayment($input, $order, $client) {
    $clientId = $order['client_id'];
    $pointsUsed = $input['loyalty_points_used'] ?? 0;
    

    $clientInfo = $client->getById($clientId);
    if (!$clientInfo || $clientInfo['loyalty_points'] < $pointsUsed) {
        return [
            'success' => false,
            'message' => 'Points de fidélité insuffisants'
        ];
    }
    
    $discount = ($pointsUsed / 100) * 5;
    
    if ($discount > $order['total_amount']) {
        return [
            'success' => false,
            'message' => 'La réduction dépasse le montant de la commande'
        ];
    }
    
    $result = $client->useLoyaltyPoints(
        $clientId, 
        $pointsUsed, 
        'Points utilisés pour commande #' . $order['id'], 
        $order['id']
    );
    
    if (!$result['success']) {
        return [
            'success' => false,
            'message' => $result['error']
        ];
    }
    
    $paymentId = 'LOYALTY_' . strtoupper(uniqid());
    
    return [
        'success' => true,
        'payment_id' => $paymentId,
        'points_used' => $pointsUsed,
        'discount_applied' => $discount,
        'remaining_amount' => $order['total_amount'] - $discount,
        'processed_at' => date('Y-m-d H:i:s')
    ];
}

function simulateCardPayment($input) {

    $cardNumber = $input['card_number'] ?? '';
    $amount = $input['amount'] ?? 0;
    
    if (empty($cardNumber) || $amount <= 0) {
        jsonResponse(false, null, 'Données manquantes pour la simulation', 400);
    }
    
    $scenarios = [
        '4111111111111111' => ['success' => true, 'message' => 'Paiement réussi'],
        '4111111111111112' => ['success' => false, 'message' => 'Carte refusée'],
        '4111111111111113' => ['success' => false, 'message' => 'Fonds insuffisants'],
        '4111111111111114' => ['success' => false, 'message' => 'Carte expirée']
    ];
    
    $result = $scenarios[$cardNumber] ?? ['success' => true, 'message' => 'Paiement simulé réussi'];
    
    if ($result['success']) {
        $result['payment_id'] = 'SIM_' . strtoupper(uniqid());
        $result['amount'] = $amount;
    }
    
    jsonResponse($result['success'], $result, $result['message']);
}

function validateLoyaltyPayment($input, $client) {
    $clientId = $input['client_id'] ?? null;
    $pointsToUse = $input['points_to_use'] ?? 0;
    $orderAmount = $input['order_amount'] ?? 0;
    
    if (!$clientId || !$pointsToUse || !$orderAmount) {
        jsonResponse(false, null, 'Paramètres manquants', 400);
    }
    
    $clientInfo = $client->getById($clientId);
    if (!$clientInfo) {
        jsonResponse(false, null, 'Client non trouvé', 404);
    }
    
    $availablePoints = $clientInfo['loyalty_points'];
    $maxDiscount = ($availablePoints / 100) * 5;
    $requestedDiscount = ($pointsToUse / 100) * 5;
    
    $validation = [
        'available_points' => $availablePoints,
        'requested_points' => $pointsToUse,
        'max_possible_discount' => $maxDiscount,
        'requested_discount' => $requestedDiscount,
        'order_amount' => $orderAmount,
        'valid' => true,
        'errors' => []
    ];
    
    if ($pointsToUse > $availablePoints) {
        $validation['valid'] = false;
        $validation['errors'][] = 'Points insuffisants';
    }
    
    if ($pointsToUse % 100 !== 0) {
        $validation['valid'] = false;
        $validation['errors'][] = 'Les points doivent être utilisés par multiples de 100';
    }
    
    if ($requestedDiscount > $orderAmount) {
        $validation['valid'] = false;
        $validation['errors'][] = 'La réduction ne peut pas dépasser le montant de la commande';
    }
    
    jsonResponse(true, $validation, $validation['valid'] ? 'Validation réussie' : 'Validation échouée');
}

function refundPayment($input, $clientOrder) {
    $orderId = $input['order_id'] ?? null;
    $refundAmount = $input['refund_amount'] ?? null;
    $reason = $input['reason'] ?? 'Remboursement';
    
    if (!$orderId) {
        jsonResponse(false, null, 'ID de commande manquant', 400);
    }
    
    $order = $clientOrder->getById($orderId);
    if (!$order) {
        jsonResponse(false, null, 'Commande non trouvée', 404);
    }
  
    if (!in_array($order['status'], ['confirmed', 'ready', 'completed'])) {
        jsonResponse(false, null, 'Cette commande ne peut pas être remboursée', 400);
    }
    
    $refundAmount = $refundAmount ?? $order['total_amount'];

    $refundId = 'REFUND_' . strtoupper(uniqid());

    $clientOrder->updateStatus($orderId, 'cancelled');
    
    jsonResponse(true, [
        'refund_id' => $refundId,
        'order_id' => $orderId,
        'refund_amount' => $refundAmount,
        'reason' => $reason,
        'processed_at' => date('Y-m-d H:i:s'),
        'estimated_arrival' => date('Y-m-d', strtotime('+3 business days'))
    ], 'Remboursement traité avec succès');
}

function getPaymentStatus($input, $clientOrder) {
    $orderId = $input['order_id'] ?? null;
    
    if (!$orderId) {
        jsonResponse(false, null, 'ID de commande manquant', 400);
    }
    
    $order = $clientOrder->getById($orderId);
    if (!$order) {
        jsonResponse(false, null, 'Commande non trouvée', 404);
    }
    
    $paymentStatus = [
        'order_id' => $orderId,
        'status' => $order['status'],
        'total_amount' => $order['total_amount'],
        'payment_method' => $order['payment_method'],
        'loyalty_points_used' => $order['loyalty_points_used'],
        'order_date' => $order['order_date']
    ];
    
    switch ($order['status']) {
        case 'pending':
            $paymentStatus['message'] = 'En attente de paiement';
            break;
        case 'confirmed':
            $paymentStatus['message'] = 'Paiement confirmé';
            break;
        case 'cancelled':
            $paymentStatus['message'] = 'Commande annulée';
            break;
        default:
            $paymentStatus['message'] = 'Statut: ' . $order['status'];
    }
    
    jsonResponse(true, $paymentStatus, 'Statut récupéré avec succès');
}
?>