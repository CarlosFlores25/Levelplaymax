<?php
date_default_timezone_set('America/Caracas');

$cronKey = "12345";
$emailAdmin = "carloscruch2@gmail.com";

if (($_GET['key'] ?? '') !== $cronKey) {
    http_response_code(403);
    die("Acceso Denegado.");
}

ini_set('display_errors', 0);
ini_set('max_execution_time', 600);

require '../admin/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists('PHPMailer/Exception.php')) {
    require 'PHPMailer/Exception.php';
    require 'PHPMailer/PHPMailer.php';
    require 'PHPMailer/SMTP.php';
}

function enviarCorreo($destinatario, $asunto, $htmlBody)
{
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL))
        return false;

    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->CharSet = 'UTF-8';
            $mail->setFrom(SMTP_USER, EMAIL_FROM_NAME);
            $mail->addAddress($destinatario);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $htmlBody;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error SMTP: " . $mail->ErrorInfo);
            return false;
        }
    }
    return false;
}

$logRenovadas = [];
$logRevocadas = [];
$hoySql = date('Y-m-d H:i:s');
$cuentasMaestrasProcesadas = [];

$sqlRes = "
    SELECT 
        p.id as perfil_id, 
        p.cuenta_id, 
        p.reseller_id, 
        p.auto_renovacion, 
        d.saldo, 
        d.email as email_reseller, 
        d.nombre as nombre_reseller,
        c.plataforma, 
        c.email_cuenta,
        cat.precio_reseller,
        cat.nombre as nombre_producto_catalogo
    FROM perfiles p 
    JOIN distribuidores d ON p.reseller_id = d.id 
    JOIN cuentas c ON p.cuenta_id = c.id 
    LEFT JOIN catalogo cat ON c.plataforma = cat.nombre 
    WHERE p.reseller_id IS NOT NULL 
    AND p.fecha_venta_reseller <= DATE_SUB(NOW(), INTERVAL 30 DAY)
";

$items = $pdo->query($sqlRes)->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as $row) {
    $perfilId = $row['perfil_id'];
    $cuentaId = $row['cuenta_id'];
    $resellerId = $row['reseller_id'];
    $precio = floatval($row['precio_reseller']);
    $plataforma = $row['plataforma'];
    $emailCuenta = $row['email_cuenta'];
    $nombreReseller = $row['nombre_reseller'];

    $esMaster = (stripos($row['nombre_producto_catalogo'], 'Completa') !== false ||
        stripos($row['nombre_producto_catalogo'], 'Cuenta') !== false ||
        stripos($plataforma, 'Completa') !== false);

    if ($esMaster && in_array($cuentaId, $cuentasMaestrasProcesadas))
        continue;
    if ($esMaster)
        $cuentasMaestrasProcesadas[] = $cuentaId;

    if ($row['auto_renovacion'] == 1 && $row['saldo'] >= $precio && $precio > 0) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE distribuidores SET saldo = saldo - ? WHERE id = ?")->execute([$precio, $resellerId]);
            $desc = $esMaster ? "Renovación Auto: $plataforma (MASTER)" : "Renovación Auto: $plataforma";
            $pdo->prepare("INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion) VALUES (?, 'renovacion', ?, ?)")->execute([$resellerId, $precio, $desc]);

            if ($esMaster) {
                $pdo->prepare("UPDATE perfiles SET fecha_venta_reseller = ? WHERE cuenta_id = ? AND reseller_id = ?")->execute([$hoySql, $cuentaId, $resellerId]);
            } else {
                $pdo->prepare("UPDATE perfiles SET fecha_venta_reseller = ? WHERE id = ?")->execute([$hoySql, $perfilId]);
            }
            $pdo->commit();

            $logRenovadas[] = [
                'reseller' => $nombreReseller,
                'cuenta' => "$plataforma ($emailCuenta)",
                'precio' => "$$precio"
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    } else {
        try {
            if ($esMaster) {
                $pdo->prepare("UPDATE perfiles SET reseller_id = NULL, cliente_reseller_id = NULL, fecha_venta_reseller = NULL, fecha_corte_cliente = NULL, auto_renovacion = 0 WHERE cuenta_id = ? AND reseller_id = ?")->execute([$cuentaId, $resellerId]);
            } else {
                $pdo->prepare("UPDATE perfiles SET reseller_id = NULL, cliente_reseller_id = NULL, fecha_venta_reseller = NULL, fecha_corte_cliente = NULL, auto_renovacion = 0 WHERE id = ?")->execute([$perfilId]);
            }

            $motivo = ($row['auto_renovacion'] == 0) ? "Auto-Renovación Desactivada" : "Saldo Insuficiente";
            $logRevocadas[] = [
                'reseller' => $nombreReseller,
                'cuenta' => "$plataforma ($emailCuenta)",
                'motivo' => $motivo
            ];

            $msgReseller = "<p>Hola $nombreReseller,</p><p>Tu cuenta <strong>$plataforma</strong> ha sido retirada.</p><p>Motivo: $motivo</p>";
            enviarCorreo($row['email_reseller'], "Cuenta Revocada", $msgReseller);
        } catch (Exception $e) {
        }
    }
}

if (count($logRenovadas) > 0 || count($logRevocadas) > 0) {
    $htmlReporte = "<h2>Reporte Diario</h2>";
    $htmlReporte .= "<p>Ejecutado: " . date('d/m/Y H:i A') . "</p>";

    if (count($logRenovadas) > 0) {
        $htmlReporte .= "<h3 style='color:green;'>Renovadas (" . count($logRenovadas) . ")</h3>";
        $htmlReporte .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
        $htmlReporte .= "<tr><th>Reseller</th><th>Cuenta</th><th>Monto</th></tr>";
        foreach ($logRenovadas as $ren) {
            $htmlReporte .= "<tr><td>{$ren['reseller']}</td><td>{$ren['cuenta']}</td><td>{$ren['precio']}</td></tr>";
        }
        $htmlReporte .= "</table><br>";
    }

    if (count($logRevocadas) > 0) {
        $htmlReporte .= "<h3 style='color:red;'>Revocadas (" . count($logRevocadas) . ")</h3>";
        $htmlReporte .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
        $htmlReporte .= "<tr><th>Reseller</th><th>Cuenta</th><th>Motivo</th></tr>";
        foreach ($logRevocadas as $rev) {
            $htmlReporte .= "<tr><td>{$rev['reseller']}</td><td>{$rev['cuenta']}</td><td>{$rev['motivo']}</td></tr>";
        }
        $htmlReporte .= "</table>";
    }

    if (!enviarCorreo($emailAdmin, "Reporte Operaciones: " . count($logRenovadas) . " Renovadas", $htmlReporte)) {
        file_put_contents('log_reporte_' . date('Y-m-d') . '.html', $htmlReporte);
    }
}

$pdo->query("DELETE FROM notificaciones_reseller WHERE fecha < DATE_SUB(NOW(), INTERVAL 30 DAY)");
?>