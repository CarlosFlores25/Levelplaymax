<?php
/**
 * Sistema de Protección CSRF
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Obtener token CSRF actual
 */
function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Validar token CSRF
 */
function validateCSRF($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Requiere token CSRF válido para acciones POST/PUT/DELETE
 */
function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return true; // GET no requiere CSRF
    }
    
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!validateCSRF($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Token CSRF inválido o faltante'
        ]);
        exit;
    }
    
    return true;
}
