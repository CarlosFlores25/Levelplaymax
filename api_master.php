<?php
header('Content-Type: application/json');

if (!defined('DB_HOST')) {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0)
                continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }
}

$SECRET_KEY = $_ENV['SECRET_KEY_DB'] ?? "Ghost_2025_acceso_total";

$conn = new mysqli($_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', $_ENV['DB_NAME'] ?? 'levelpla_streaming');
$conn->set_charset($_ENV['DB_CHARSET'] ?? 'utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (isset($_POST['accion']) && $_POST['accion'] === 'get_schema') {
        if ($token !== $SECRET_KEY)
            die(json_encode(["error" => "Denegado"]));

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

    if ($token !== $SECRET_KEY) {
        die(json_encode(["error" => "Denegado"]));
    }

    if (empty($sql)) {
        die(json_encode(["error" => "SQL vacio"]));
    }

    try {
        $result = $conn->query($sql);
        if ($result === TRUE) {
            echo json_encode([
                "status" => "success",
                "mensaje" => "Ejecutado. Filas: " . $conn->affected_rows
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
        echo json_encode(["status" => "error", "mensaje" => "Error consulta"]);
    }
}
$conn->close();
?>