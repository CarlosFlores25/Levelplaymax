<?php
// api_app_chat.php
header('Content-Type: application/json');
$host = "localhost"; $user = "levelpla_Administrador"; $pass = "Cruch2603."; $db = "levelpla_streaming";
$conn = new mysqli($host, $user, $pass, $db);

$accion = $_POST['accion'] ?? '';

// ENVIAR MENSAJE (TEXTO + FOTO OPCIONAL)
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

// CHEQUEAR RESPUESTA
if ($accion == 'check') {
    $last_id = intval($_POST['last_id']);
    // Buscamos si Ghost respondió algo nuevo
    $res = $conn->query("SELECT id, mensaje_bot FROM app_chat WHERE id > $last_id AND estado = 'respondido' ORDER BY id DESC LIMIT 1");
    
    if ($row = $res->fetch_assoc()) {
        echo json_encode(["status" => "nuevo_mensaje", "bot" => $row['mensaje_bot'], "id" => $row['id']]);
    } else {
        echo json_encode(["status" => "esperando"]);
    }
}
$conn->close();
?>