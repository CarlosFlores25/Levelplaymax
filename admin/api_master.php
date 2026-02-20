<?php
/**
 * API Master Admin (Segura)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$SECRET_KEY = $_ENV['SECRET_KEY_DB'] ?? "Ghost_2025_acceso_total";

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    // Nueva función: OBTENER ESQUEMA AUTOMÁTICO
    if (isset($_POST['accion']) && $_POST['accion'] === 'get_schema') {
        if ($token !== $SECRET_KEY)
            die(json_encode(["error" => "Acero Denegado."]));

        $tablas = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tabla = $row[0];
            $cols_res = $conn->query("DESCRIBE $tabla");
            $cols = [];
            while ($col = $cols_res->fetch_assoc()) {
                $cols[] = $col['Field'];
            }
            $tablas[] = "- $tabla (" . implode(", ", $cols) . ")";
        }
        echo json_encode(["status" => "success", "esquema" => implode("\n", $tablas)]);
        exit;
    }

    $sql = $_POST['sql'] ?? '';

    // Seguridad básica
    if ($token !== $SECRET_KEY) {
        die(json_encode(["error" => "Acceso Denegado."]));
    }

    if (empty($sql)) {
        die(json_encode(["error" => "SQL vacío."]));
    }

    try {
        $result = $conn->query($sql);
        if ($result === TRUE) {
            echo json_encode([
                "status" => "success",
                "mensaje" => "Comando ejecutado. Filas afectadas: " . $conn->affected_rows
            ]);
        } elseif ($result === FALSE) {
            echo json_encode(["status" => "error", "mensaje" => $conn->error]);
        } else {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode([
                "status" => "success",
                "datos" => $data,
                "count" => count($data)
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "mensaje" => "Error en la consulta"]);
    }
}
$conn->close();
?>