<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'drivin_cook');
define('DB_USER', 'root');
define('DB_PASS', 'root');

define('SITE_NAME', 'Driv\'n Cook');
define('SITE_URL', 'http://localhost/drivin_cook');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isFranchisee() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'franchisee';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}
?>