<?php
// api_acciones.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

// --- 1. CONEXIÓN (Poner datos reales de tu hosting) ---
$host = 'localhost'; // Generalmente se deja 'localhost' en cPanel
$db   = 'levelpla_streaming'; // EL NOMBRE COMPLETO QUE CREASTE EN EL PASO 1 (con prefijo)
$user = 'levelpla_Administrador';     // EL USUARIO QUE CREASTE EN EL PASO 1 (con prefijo)
$pass = 'Cruch2603.'; // LA CONTRASEÑA QUE CREASTE EN EL PASO 1
$charset = 'utf8mb4';
$conn = new mysqli($host, $user, $pass, $db);

// VERIFICAMOS QUE HAYA UNA ORDEN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $accion = $_POST['accion'] ?? '';

    // --- ACCIÓN 1: ACTUALIZAR PRECIO ---
    if ($accion === 'actualizar_precio') {
        $id_producto = $_POST['id'];
        $nuevo_precio = $_POST['precio'];
        
        // Usamos Prepared Statements por seguridad
        $stmt = $conn->prepare("UPDATE catalogo SET precio_reseller = ? WHERE id = ?");
        $stmt->bind_param("di", $nuevo_precio, $id_producto);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "mensaje" => "Precio actualizado a $$nuevo_precio"]);
        } else {
            echo json_encode(["status" => "error", "mensaje" => "Fallo al actualizar"]);
        }
    }

    // --- ACCIÓN 2: SOLUCIONAR REPORTE ---
    elseif ($accion === 'cerrar_reporte') {
        $id_reporte = $_POST['id'];
        
        $stmt = $conn->prepare("UPDATE reportes_fallos SET estado = 'solucionado' WHERE id = ?");
        $stmt->bind_param("i", $id_reporte);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "mensaje" => "Reporte #$id_reporte marcado como solucionado"]);
        }
    }
    
    else {
        echo json_encode(["status" => "error", "mensaje" => "Accion no reconocida"]);
    }
}
$conn->close();
?>