<?php
header('Content-Type: application/json');

if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$conn = new mysqli($_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', $_ENV['DB_NAME'] ?? 'levelpla_streaming');
$conn->set_charset("utf8mb4");

$accion = $_POST['accion'] ?? '';

// Enviar
if ($accion == 'enviar') {
    $msg = $conn->real_escape_string($_POST['mensaje']);
    $img = isset($_POST['imagen']) ? $conn->real_escape_string($_POST['imagen']) : NULL;
    $sql = "INSERT INTO app_chat (mensaje_usuario, imagen_b64, estado) VALUES ('$msg', '$img', 'pendiente')";
    if ($conn->query($sql)) {
        echo json_encode(["status" => "success", "id" => $conn->insert_id]);
    } else {
        echo json_encode(["status" => "error", "error" => $conn->error]);
    }
}

// Chequear
if ($accion == 'check') {
    $last_id = intval($_POST['last_id']);
    $res = $conn->query("SELECT id, mensaje_bot FROM app_chat WHERE id > $last_id AND estado = 'respondido' ORDER BY id DESC LIMIT 1");
    if ($row = $res->fetch_assoc()) {
        echo json_encode(["status" => "nuevo_mensaje", "bot" => $row['mensaje_bot'], "id" => $row['id']]);
    } else {
        echo json_encode(["status" => "esperando"]);
    }
}

$conn->close();

?>