<?php
class LoyaltyBridge {
    private $javaPath;
    private $classPath;
    
    public function __construct() {
        // Chemins vers Java (à adapter selon votre installation)
        $this->javaPath = 'java'; // ou chemin complet vers java.exe
        $this->classPath = __DIR__; // Dossier contenant LoyaltyManager.class
    }

    private function executeJavaCommand($action, $params = []) {
        $command = $this->javaPath . ' -cp "' . $this->classPath . '" LoyaltyManager ' . $action;
        
        foreach ($params as $param) {
            $command .= ' "' . addslashes($param) . '"';
        }
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            return trim(implode("\n", $output));
        }
        
        return false;
    }
    
    public function addPoints($clientId, $points, $reason = 'Points ajoutés') {
        $result = $this->executeJavaCommand('addPoints', [$clientId, $points, $reason]);
        return $result === 'SUCCESS';
    }

    public function usePoints($clientId, $points, $reason = 'Points utilisés') {
        $result = $this->executeJavaCommand('usePoints', [$clientId, $points, $reason]);
        return $result === 'SUCCESS';
    }

    public function getCurrentPoints($clientId) {
        $result = $this->executeJavaCommand('getCurrentPoints', [$clientId]);
        return is_numeric($result) ? (int)$result : 0;
    }
    
    public function calculatePointsEarned($amount) {
        $result = $this->executeJavaCommand('calculatePoints', [$amount]);
        return is_numeric($result) ? (int)$result : 0;
    }

    public function processOrderReward($clientId, $orderAmount, $orderId) {
        $result = $this->executeJavaCommand('processOrder', [$clientId, $orderAmount, $orderId]);
        
        if (strpos($result, 'SUCCESS:') === 0) {
            $parts = explode(':', $result);
            return [
                'success' => true,
                'points_earned' => isset($parts[1]) ? (int)$parts[1] : 0,
                'total_points' => isset($parts[2]) ? (int)$parts[2] : 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result
        ];
    }
    
    public function isJavaAvailable() {
        $command = $this->javaPath . ' -version';
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    public function compileJavaFile() {
        $javaFile = $this->classPath . '/LoyaltyManager.java';
        $command = 'javac -cp "." "' . $javaFile . '"';
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }
}

$loyaltyBridge = new LoyaltyBridge();

function addLoyaltyPoints($clientId, $points, $reason = 'Points gagnés') {
    global $loyaltyBridge;
    return $loyaltyBridge->addPoints($clientId, $points, $reason);
}

function useLoyaltyPoints($clientId, $points, $reason = 'Réduction appliquée') {
    global $loyaltyBridge;
    return $loyaltyBridge->usePoints($clientId, $points, $reason);
}

function getLoyaltyPoints($clientId) {
    global $loyaltyBridge;
    return $loyaltyBridge->getCurrentPoints($clientId);
}

function processOrderLoyalty($clientId, $orderAmount, $orderId) {
    global $loyaltyBridge;
    return $loyaltyBridge->processOrderReward($clientId, $orderAmount, $orderId);
}

function calculateLoyaltyDiscount($points) {
    $discountGroups = intval($points / 100);
    return $discountGroups * 5.0;
}
function canUseLoyaltyDiscount($clientId, $pointsNeeded) {
    $currentPoints = getLoyaltyPoints($clientId);
    return $currentPoints >= $pointsNeeded;
}
?>