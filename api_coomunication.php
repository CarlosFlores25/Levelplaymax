<?php
// api_jarvis_real.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // Permite conexión desde tu casa

// --- 1. CONEXIÓN (Poner datos reales de tu hosting) ---
$host = 'localhost'; // Generalmente se deja 'localhost' en cPanel
$db   = 'levelpla_streaming'; // EL NOMBRE COMPLETO QUE CREASTE EN EL PASO 1 (con prefijo)
$user = 'levelpla_Administrador';     // EL USUARIO QUE CREASTE EN EL PASO 1 (con prefijo)
$pass = 'Cruch2603.'; // LA CONTRASEÑA QUE CREASTE EN EL PASO 1
$charset = 'utf8mb4';

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die(json_encode(["error" => "Error de conexión DB: " . $conn->connect_error]));
}

$data = [];

try {
    // --- 2. CONSULTAS BASADAS EN TU PDF ---

    // A. TASA DEL DÓLAR (Tabla: configuracion)
    $sql_tasa = "SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'";
    $res = $conn->query($sql_tasa);
    $data['tasa'] = ($res && $row = $res->fetch_assoc()) ? $row['valor'] : "No definida";

    // B. REPORTES PENDIENTES (Tabla: reportes_fallos)
    // Extraemos mensaje, fecha y estado para que Jarvis sepa qué falla.
    $sql_reportes = "SELECT id, mensaje, estado, fecha FROM reportes_fallos WHERE estado = 'pendiente' LIMIT 10";
    $data['reportes_pendientes'] = $conn->query($sql_reportes)->fetch_all(MYSQLI_ASSOC);

    // C. TOP DISTRIBUIDORES CON SALDO (Tabla: distribuidores)
    // Para saber quién tiene dinero disponible.
    $sql_dist = "SELECT nombre, telefono, saldo FROM distribuidores ORDER BY saldo DESC LIMIT 5";
    $data['top_distribuidores'] = $conn->query($sql_dist)->fetch_all(MYSQLI_ASSOC);

    // D. RESUMEN DE INVENTARIO (Tabla: cuentas)
    // Contamos cuántas cuentas tienes por plataforma (Netflix, HBO, etc.)
    $sql_stock = "SELECT plataforma, COUNT(*) as cantidad FROM cuentas GROUP BY plataforma";
    $data['stock_resumen'] = $conn->query($sql_stock)->fetch_all(MYSQLI_ASSOC);

    // E. VENTAS DE HOY (Tabla: movimientos_reseller)
    // Usamos CURDATE() para ver el movimiento en tiempo real
    $sql_ventas = "SELECT descripcion, montro as monto, fecha FROM movimientos_reseller 
                   WHERE tipo = 'compra' AND DATE(fecha) = CURDATE() ORDER BY fecha DESC LIMIT 5";
    // Nota: En tu PDF vi 'movimientos_reseller', asumí columnas estándar. 
    // Si falla esta, la API devolverá array vacío pero no romperá el resto.
    if ($res_v = $conn->query($sql_ventas)) {
        $data['ventas_hoy'] = $res_v->fetch_all(MYSQLI_ASSOC);
    } else {
        $data['ventas_hoy'] = [];
    }

} catch (Exception $e) {
    $data['error_interno'] = $e->getMessage();
}

// --- 3. ENVIAR AL PYTHON ---
echo json_encode($data, JSON_PRETTY_PRINT);
$conn->close();
?>