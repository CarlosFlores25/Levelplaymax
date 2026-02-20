<?php
// Helpers de validacion y seguridad

// CSRF
function generateCSRFToken()
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token)
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Sanitizacion
function sanitize($data, $type = 'string')
{
    if (is_array($data)) {
        return array_map(function ($item) use ($type) {
            return sanitize($item, $type);
        }, $data);
    }

    switch ($type) {
        case 'email':
            return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var(trim($data), FILTER_SANITIZE_URL);
        default:
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Validacion de entrada
function validate($data, $rules)
{
    $errors = [];

    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        $ruleArray = explode('|', $rule);

        foreach ($ruleArray as $singleRule) {
            if ($singleRule === 'required' && empty($value)) {
                $errors[$field] = "El campo $field es requerido";
                break;
            }

            if (strpos($singleRule, 'min:') === 0 && strlen($value) < (int) substr($singleRule, 4)) {
                $errors[$field] = "El campo $field debe tener al menos " . substr($singleRule, 4) . " caracteres";
            }

            if (strpos($singleRule, 'max:') === 0 && strlen($value) > (int) substr($singleRule, 4)) {
                $errors[$field] = "El campo $field no puede exceder " . substr($singleRule, 4) . " caracteres";
            }

            if ($singleRule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "El campo $field debe ser un email válido";
            }

            if ($singleRule === 'numeric' && !is_numeric($value)) {
                $errors[$field] = "El campo $field debe ser numérico";
            }
        }
    }

    return $errors;
}

// Validacion de archivos
function validateUploadedFile($file, $allowedTypes = null, $maxSize = null)
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No se recibió ningún archivo'];
    }

    $allowedTypes = $allowedTypes ?? ALLOWED_UPLOAD_TYPES;
    $maxSize = $maxSize ?? MAX_UPLOAD_SIZE;

    // Validar tipo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', $allowedTypes)];
    }

    // Validar tamaño
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'El archivo excede el tamaño máximo de ' . ($maxSize / 1024 / 1024) . 'MB'];
    }

    return ['success' => true];
}

// Respuesta JSON
function jsonResponse($success, $data = null, $message = null, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');

    $response = ['success' => $success];
    if ($data !== null)
        $response['data'] = $data;
    if ($message !== null)
        $response['message'] = $message;

    return json_encode($response, JSON_UNESCAPED_UNICODE);
}

// Logs
function safeLog($message, $type = 'ERROR')
{
    $logFile = LOG_DIR . 'system_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user = $_SESSION['admin_user'] ?? 'Guest';

    $logMessage = "[$timestamp] [$ip] [$user] [$type] $message" . PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Escape HTML
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Rate limit
function checkRateLimit($action, $maxRequests = 60, $timeWindow = 60)
{
    $key = 'rate_limit_' . md5($_SERVER['REMOTE_ADDR'] . $action);
    $cacheFile = sys_get_temp_dir() . '/' . $key;

    $requests = [];
    if (file_exists($cacheFile)) {
        $requests = json_decode(file_get_contents($cacheFile), true) ?: [];
    }

    $now = time();
    $requests = array_filter($requests, function ($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });

    if (count($requests) >= $maxRequests) {
        return false;
    }

    $requests[] = $now;
    file_put_contents($cacheFile, json_encode($requests));

    return true;
}
