<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

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

if ($conn->connect_error) {
    die(json_encode(["error" => "Error de conexión DB"]));
}

$data = [];

try {
    // Tasa Dolar
    $res = $conn->query("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'");
    $data['tasa'] = ($res && $row = $res->fetch_assoc()) ? $row['valor'] : "No definida";

    // Reportes pendientes
    $data['reportes_pendientes'] = $conn->query("SELECT id, mensaje, estado, fecha FROM reportes_fallos WHERE estado = 'pendiente' LIMIT 10")->fetch_all(MYSQLI_ASSOC);

    // Top distribuidores
    $data['top_distribuidores'] = $conn->query("SELECT nombre, telefono, saldo FROM distribuidores ORDER BY saldo DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

    // Stock resumen
    $data['stock_resumen'] = $conn->query("SELECT plataforma, COUNT(*) as cantidad FROM cuentas GROUP BY plataforma")->fetch_all(MYSQLI_ASSOC);

    // Ventas hoy
    $res_v = $conn->query("SELECT descripcion, montro as monto, fecha FROM movimientos_reseller WHERE tipo = 'compra' AND DATE(fecha) = CURDATE() ORDER BY fecha DESC LIMIT 5");
    $data['ventas_hoy'] = $res_v ? $res_v->fetch_all(MYSQLI_ASSOC) : [];

} catch (Exception $e) {
    $data['error_interno'] = $e->getMessage();
}

echo json_encode($data, JSON_PRETTY_PRINT);
$conn->close();

?>