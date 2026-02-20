<?php
ob_start();
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require 'db.php';
require 'config.php';
require 'helpers.php';
require 'csrf.php';

// Logs
function writeLog($mensaje, $tipo = 'INFO')
{
    $archivoLog = LOG_DIR . 'system_' . date('Y-m-d') . '.log';
    $fecha = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user = $_SESSION['admin_user'] ?? 'Guest';
    @file_put_contents($archivoLog, "[$fecha] [$ip] [$user] [$tipo] $mensaje" . PHP_EOL, FILE_APPEND);
}

// SMTP
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if (file_exists('PHPMailer/Exception.php')) {
    require 'PHPMailer/Exception.php';
    require 'PHPMailer/PHPMailer.php';
    require 'PHPMailer/SMTP.php';
}

function enviarCorreoHTML($destinatario, $asunto, $mensaje)
{
    // Validar email
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        safeLog("Email inválido: $destinatario", "EMAIL_ERROR");
        return false;
    }

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
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(SMTP_USER, EMAIL_FROM_NAME);
            $mail->addAddress($destinatario);
            $mail->isHTML(true);
            $mail->Subject = sanitize($asunto);
            $mail->Body = "<div style='background:#f4f4f4;padding:20px;font-family:Arial;'><div style='background:#fff;padding:20px;border-radius:8px;border-top:4px solid #7f00ff'>" . sanitize($mensaje) . "</div></div>";
            $mail->send();
            return true;
        } catch (Exception $e) {
            safeLog("SMTP Error: " . $mail->ErrorInfo, "EMAIL_FAIL");
            return false;
        }
    } else {
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: " . EMAIL_FROM_NAME . " <" . SMTP_USER . ">";
        return @mail($destinatario, sanitize($asunto), sanitize($mensaje), $headers);
    }
}

$action = $_GET['action'] ?? '';


// OneSignal ya está definido en config.php

function enviarNotificacionPush($mensaje, $heading = "Nueva Alerta")
{
    // 1. Obtenemos los IDs de los administradores registrados
    global $pdo;
    $ids = $pdo->query("SELECT onesignal_id FROM admin_users WHERE onesignal_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ids))
        return false;

    $content = array(
        "en" => $mensaje
    );

    $headings = array(
        "en" => $heading
    );

    $fields = array(
        'app_id' => ONESIGNAL_APP_ID,
        'include_player_ids' => $ids, // Enviamos a los admins registrados
        'data' => array("foo" => "bar"),
        'contents' => $content,
        'headings' => $headings,
        'small_icon' => 'ic_stat_onesignal_default' // Icono en Android
    );

    $fields = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . ONESIGNAL_API_KEY
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// api.php

if ($action == 'register_device') {
    // 1. Leer datos
    $input = file_get_contents('php://input');
    $d = json_decode($input, true);

    // 2. Log para depurar (Esto guardará en system.log qué está llegando)
    $logMsg = "Intento registro ID. Input: " . $input . " | Sesión ID: " . ($_SESSION['admin_id'] ?? 'NULL');
    writeLog($logMsg, "DEVICE_DEBUG");

    // 3. Validaciones
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'No hay sesión iniciada']);
        exit;
    }

    if (empty($d['player_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID vacío']);
        exit;
    }

    // 4. Guardar en BD
    try {
        $pdo->prepare("UPDATE admin_users SET onesignal_id = ? WHERE id = ?")
            ->execute([$d['player_id'], $_SESSION['admin_id']]);

        writeLog("ID Guardado con éxito: " . $d['player_id'], "DEVICE_SUCCESS");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        writeLog("Error SQL: " . $e->getMessage(), "DEVICE_ERROR");
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Zona publica

if ($action == 'login') {
    $d = json_decode(file_get_contents('php://input'), true);
    $s = $pdo->prepare("SELECT * FROM admin_users WHERE username=?");
    $s->execute([$d['user']]);
    $u = $s->fetch();
    if ($u && password_verify($d['pass'], $u['password_hash'])) {
        $_SESSION['admin_id'] = $u['id'];
        $_SESSION['admin_user'] = $u['username'];
        echo json_encode(['success' => true]);
    } else
        echo json_encode(['success' => false]);
    exit;
}

if ($action == 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}
if ($action == 'get_product') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM catalogo WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

if ($action == 'get_catalogo') {
    echo json_encode($pdo->query("SELECT * FROM catalogo ORDER BY categoria DESC, nombre ASC")->fetchAll());
    exit;
}
if ($action == 'get_stock_details') {
    ob_clean(); // Limpiar cualquier basura previa

    $plataforma = $_GET['plataforma'] ?? '';

    try {
        // Usamos ? para evitar errores con espacios o símbolos en el nombre
        $sql = "SELECT c.email_cuenta, c.password, 
                       p.nombre_perfil, p.pin_perfil, p.slot_numero
                FROM perfiles p
                JOIN cuentas c ON p.cuenta_id = c.id
                WHERE c.plataforma = ? 
                AND p.cliente_id IS NULL 
                AND p.reseller_id IS NULL
                ORDER BY c.id ASC, p.slot_numero ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$plataforma]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar por Cuenta Maestra
        $resultado = [];
        foreach ($filas as $f) {
            $email = $f['email_cuenta'];
            // Inicializar array para este email si no existe
            if (!isset($resultado[$email])) {
                $resultado[$email] = [
                    'password' => $f['password'],
                    'perfiles' => []
                ];
            }
            // Agregar perfil
            $resultado[$email]['perfiles'][] = [
                'nombre' => $f['nombre_perfil'],
                'pin' => $f['pin_perfil']
            ];
        }

        echo json_encode(['success' => true, 'data' => $resultado]);

    } catch (Exception $e) {
        // Enviar error en JSON válido
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
    exit;
}

// Stock inteligente
if ($action == 'get_stock') {
    ob_clean();

    // Query 1: Productos automáticos (desde Perfiles/Cuentas)
    // IMPORTANT: Corregido JOIN que faltaba en la versión anterior que daba error en UNION
    $sqlAuto = "SELECT 
                c.plataforma, 
                COUNT(DISTINCT 
                    CASE 
                        WHEN c.plataforma LIKE '%Completa%' OR c.plataforma LIKE '%Cuenta%' 
                        THEN c.id
                        ELSE p.id
                    END
                ) as disponibles 
            FROM perfiles p 
            JOIN cuentas c ON p.cuenta_id = c.id
            WHERE p.cliente_id IS NULL AND p.reseller_id IS NULL 
            GROUP BY c.plataforma";

    // Query 2: Productos Manuales / Links (desde Catálogo)
    $sqlManual = "SELECT 
                nombre as plataforma, 
                999 as disponibles 
            FROM catalogo 
            WHERE tipo_entrega IN ('manual', 'link')";

    try {
        $finalOutput = [];
        $stockMap = [];

        // 1. Ejecutar Automáticos
        $stmt = $pdo->query($sqlAuto);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stockMap[$row['plataforma']] = intval($row['disponibles']);
        }

        // 2. Ejecutar Manuales
        $stmt2 = $pdo->query($sqlManual);
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $plat = $row['plataforma'];
            // Si ya existe (híbrido?), sumamos, sino asignamos
            if (isset($stockMap[$plat])) {
                $stockMap[$plat] += 999;
            } else {
                $stockMap[$plat] = 999;
            }
        }

        // 3. Convertir Map a Array de Objetos
        foreach ($stockMap as $plat => $qty) {
            $finalOutput[] = ['plataforma' => $plat, 'disponibles' => $qty];
        }

        // Ordenar
        usort($finalOutput, function ($a, $b) {
            return strcmp($a['plataforma'], $b['plataforma']);
        });

        echo json_encode($finalOutput);

    } catch (Exception $e) {
        // Enviar array vacío en vez de error HTML
        error_log("API Error get_stock: " . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}
if ($action == 'get_tasa') {
    echo json_encode(['tasa' => $pdo->query("SELECT valor FROM configuracion WHERE clave='tasa_dolar'")->fetchColumn()]);
    exit;
}

if ($action == 'validate_coupon') {
    try {
        $c = sanitize($_POST['codigo'] ?? '', 'string');
        if (empty($c)) {
            echo jsonResponse(false, null, 'Código de cupón requerido', 400);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM cupones WHERE codigo = ? AND activo = 1");
        $stmt->execute([$c]);
        $r = $stmt->fetch();

        if ($r && $r['usos_actuales'] < $r['usos_max']) {
            echo jsonResponse(true, ['descuento' => $r['descuento']], "Descuento {$r['descuento']}%");
        } else {
            echo jsonResponse(false, null, 'Cupón inválido');
        }
    } catch (Exception $e) {
        safeLog("Error validando cupón: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al validar cupón');
    }
    exit;
}

if ($action == 'track_client') {
    try {
        $id = filter_var($_POST['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        $tel = sanitize($_POST['telefono'] ?? '', 'string');

        if (empty($id) || empty($tel)) {
            echo jsonResponse(false, null, 'Datos incompletos', 400);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND telefono LIKE ?");
        $stmt->execute([$id, "%$tel%"]);
        $cli = $stmt->fetch();

        if (!$cli) {
            echo jsonResponse(false, null, 'Datos incorrectos');
            exit;
        }

        // MOSTRAR SOLO CUENTAS ACTIVAS (NO VENCIDAS O RECIÉN VENCIDAS)
        $stmt2 = $pdo->prepare("SELECT p.nombre_perfil, p.pin_perfil, p.fecha_corte_cliente, c.plataforma, c.email_cuenta, c.password 
                               FROM perfiles p 
                               JOIN cuentas c ON p.cuenta_id = c.id 
                               WHERE p.cliente_id = ? AND p.fecha_corte_cliente >= CURDATE() 
                               ORDER BY p.fecha_corte_cliente ASC");
        $stmt2->execute([$cli['id']]);
        $cu = $stmt2->fetchAll();

        echo jsonResponse(true, ['cliente' => $cli['nombre'], 'cuentas' => $cu]);
    } catch (Exception $e) {
        safeLog("Error track_client: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al buscar cliente');
    }
    exit;
}

if ($action == 'create_order') {
    try {
        // Validar y sanitizar datos
        $errors = validate($_POST, [
            'nombre' => 'required|min:2|max:100',
            'telefono' => 'required|min:10|max:20',
            'email' => 'email',
            'producto_id' => 'required|numeric',
            'producto_nombre' => 'required|min:2|max:200',
            'precio' => 'required|numeric',
            'monto_bs' => 'required|numeric',
            'metodo' => 'required|max:50'
        ]);

        if (!empty($errors)) {
            echo jsonResponse(false, ['errors' => $errors], 'Datos inválidos', 400);
            exit;
        }

        $d = [
            'nombre' => sanitize($_POST['nombre']),
            'telefono' => sanitize($_POST['telefono']),
            'email' => sanitize($_POST['email'] ?? '', 'email'),
            'producto_id' => filter_var($_POST['producto_id'], FILTER_SANITIZE_NUMBER_INT),
            'producto_nombre' => sanitize($_POST['producto_nombre']),
            'precio' => filter_var($_POST['precio'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'monto_bs' => filter_var($_POST['monto_bs'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'metodo' => sanitize($_POST['metodo']),
            'cupon_codigo' => sanitize($_POST['cupon_codigo'] ?? ''),
            'descuento_monto' => filter_var($_POST['descuento_monto'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
        ];

        // Validar archivo subido
        $filePath = null;
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $validation = validateUploadedFile($_FILES['comprobante']);
            if (!$validation['success']) {
                echo jsonResponse(false, null, $validation['message'], 400);
                exit;
            }

            $dir = UPLOAD_DIR . 'pagos/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $file = uniqid() . '_' . basename($_FILES['comprobante']['name']);
            $filePath = $dir . $file;

            if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $filePath)) {
                echo jsonResponse(false, null, 'Error al subir archivo');
                exit;
            }
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, producto_id, nombre_producto, precio_usd, monto_bs, metodo_pago, comprobante_img, cupon_usado, descuento_aplicado) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $d['nombre'],
            $d['telefono'],
            $d['email'],
            $d['producto_id'],
            $d['producto_nombre'],
            $d['precio'],
            $d['monto_bs'],
            $d['metodo'],
            $filePath,
            $d['cupon_codigo'] ?: null,
            $d['descuento_monto']
        ]);
        $oid = $pdo->lastInsertId();

        // Notificaciones
        enviarNotificacionPush("💰 Venta: {$d['producto_nombre']} - Cliente: {$d['nombre']}", "Nueva Venta Web");

        if ($d['cupon_codigo']) {
            $stmt = $pdo->prepare("UPDATE cupones SET usos_actuales = usos_actuales + 1 WHERE codigo = ?");
            $stmt->execute([$d['cupon_codigo']]);

            $stmt = $pdo->prepare("UPDATE cupones SET activo = 0 WHERE codigo = ? AND usos_actuales >= usos_max");
            $stmt->execute([$d['cupon_codigo']]);
        }

        if (EMAIL_ADMIN) {
            enviarCorreoHTML(EMAIL_ADMIN, "Nueva Venta #$oid", "Cliente: {$d['nombre']}<br>Producto: {$d['producto_nombre']}");
        }

        $pdo->commit();
        echo jsonResponse(true, ['order_id' => $oid]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        safeLog("Error create_order: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al crear pedido');
    }
    exit;
}

// Zona privada

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit;
}

// 🔥 AGREGAR ESTA LÍNEA MÁGICA AQUÍ 🔥
session_write_close();

// 27. APROBAR PEDIDO RESELLER Y PROVISIONAR CREDENCIALES
if ($action == 'approve_reseller_order') {
    // Leer input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $pedidoId = isset($input['pedido_id']) ? intval($input['pedido_id']) : 0;
    $password = $input['password'] ?? '';
    $email = $input['email'] ?? '';

    if (!$pedidoId) {
        echo json_encode(['success' => false, 'message' => 'Pedido inválido']);
        exit;
    }
    if (empty($password) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Correo y contraseña requeridos']);
        exit;
    }

    try {
        // Asegurar columnas necesarias
        $colPerfil = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'perfil_id'")->rowCount() > 0;
        if (!$colPerfil) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN perfil_id INT NULL");
        }

        // Obtener el pedido
        $stmtPedido = $pdo->prepare("SELECT p.id, p.nombre_producto, p.reseller_id, p.cliente_email, p.precio_usd 
                                     FROM pedidos p WHERE p.id = ?");
        $stmtPedido->execute([$pedidoId]);
        $pedidoData = $stmtPedido->fetch(PDO::FETCH_ASSOC);

        if (!$pedidoData) {
            throw new Exception('Pedido no encontrado.');
        }

        // INTENTO DE RECUPERACIÓN: Si no tiene reseller_id, buscamos por email
        if (empty($pedidoData['reseller_id'])) {
            $stmtFindRes = $pdo->prepare("SELECT id FROM distribuidores WHERE email = ? LIMIT 1");
            $stmtFindRes->execute([$pedidoData['cliente_email']]);
            $foundResId = $stmtFindRes->fetchColumn();

            if ($foundResId) {
                // Actualizamos el pedido con el ID encontrado
                $pdo->prepare("UPDATE pedidos SET reseller_id = ? WHERE id = ?")->execute([$foundResId, $pedidoId]);
                $pedidoData['reseller_id'] = $foundResId;
            } else {
                throw new Exception('Este pedido no tiene un reseller asociado y no se pudo encontrar por email.');
            }
        }

        // Crear una cuenta maestra nueva para el pedido manual
        $cuentaPlataforma = $pedidoData['nombre_producto'];
        $cuentaEmail = $pedidoData['cliente_email'];
        $hashPassword = password_hash($password, PASSWORD_BCRYPT);

        $pdo->beginTransaction();

        // Insertar la cuenta maestra nueva
        $stmtCuenta = $pdo->prepare("INSERT INTO cuentas (plataforma, email_cuenta, password) VALUES (?, ?, ?)");
        $stmtCuenta->execute([$cuentaPlataforma, $cuentaEmail, $hashPassword]);
        $cuentaId = $pdo->lastInsertId();

        // Crear un perfil asociado a la cuenta
        $stmtPerfil = $pdo->prepare("INSERT INTO perfiles (cuenta_id, nombre_perfil, pin_perfil, reseller_id, fecha_venta_reseller, fecha_corte_cliente) 
                                                      VALUES (?, 'Perfil 1', NULL, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))");
        $stmtPerfil->execute([$cuentaId, $pedidoData['reseller_id']]);
        $perfilId = $pdo->lastInsertId();

        // Asociar el pedido con el perfil recién creado
        $pdo->prepare("UPDATE pedidos SET perfil_id = ? WHERE id = ?")->execute([$perfilId, $pedidoId]);

        // Actualizar el email del pedido si se proporcionó uno diferente
        if (!empty($email)) {
            $pdo->prepare("UPDATE pedidos SET cliente_email = ? WHERE id = ?")->execute([$email, $pedidoId]);
        }

        // Guardar texto plano para entrega
        $plainPasswordForDelivery = $password;
        $colPlainExists = $pdo->query("SHOW COLUMNS FROM cuentas LIKE 'password_plain'")->rowCount() > 0;
        if (!$colPlainExists) {
            $pdo->exec("ALTER TABLE cuentas ADD COLUMN password_plain VARCHAR(255) NULL");
        }
        $pdo->prepare("UPDATE cuentas SET password_plain = ? WHERE id = ?")->execute([$plainPasswordForDelivery, $cuentaId]);

        // Marcar pedido como aprobado
        $pdo->prepare("UPDATE pedidos SET estado = 'aprobado' WHERE id = ?")->execute([$pedidoId]);

        $pdo->commit();

        // Notificar al admin (Blindado para no romper el flujo)
        try {
            if (function_exists('notificarAdmin')) {
                notificarAdmin("🔐 Credenciales Proporcionadas", "Cuenta nueva creada para el pedido {$pedidoId}. Plataforma: {$cuentaPlataforma}");
            }
        } catch (Exception $notifyError) {
            // Ignorar error de notificación
        }

        ob_clean(); // Limpiar buffer antes de enviar JSON final
        echo json_encode([
            'success' => true,
            'perfil_id' => $perfilId,
            'cuenta_id' => $cuentaId,
            'password_plain' => $plainPasswordForDelivery
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// RECHAZAR PEDIDO RESELLER (DEVOLVER SALDO)
if ($action == 'reject_reseller_order') {
    ob_clean();
    $input = json_decode(file_get_contents('php://input'), true);
    $pedidoId = isset($input['pedido_id']) ? intval($input['pedido_id']) : 0;
    $motivo = $input['motivo'] ?? '';

    if (!$pedidoId) {
        echo json_encode(['success' => false, 'message' => 'Pedido inválido']);
        exit;
    }

    try {
        // Asegurar columnas necesarias
        $colMotivo = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'motivo_rechazo'")->rowCount() > 0;
        if (!$colMotivo) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN motivo_rechazo TEXT NULL");
        }

        // Obtener datos del pedido para devolver saldo
        $stmt = $pdo->prepare("SELECT p.id, p.precio_usd, p.reseller_id, ped.perfil_id 
                                     FROM pedidos p 
                                     LEFT JOIN pedidos ped ON p.id = ped.id 
                                     WHERE p.id = ?");
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido || empty($pedido['reseller_id'])) {
            throw new Exception('Pedido no encontrado o no tiene reseller asociado');
        }

        // Devolver saldo al reseller
        $pdo->prepare("UPDATE distribuidores SET saldo = saldo + ? WHERE id = ?")
            ->execute([$pedido['precio_usd'], $pedido['reseller_id']]);

        // Registrar movimiento
        $stmtMov = $pdo->prepare("INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion) 
                                         VALUES (?, 'reembolso', ?, ?)");
        $descMovimiento = "Devolución por pedido rechazado: {$motivo}";
        $stmtMov->execute([$pedido['reseller_id'], $pedido['precio_usd'], $descMovimiento]);

        // Marcar pedido como rechazado
        $pdo->prepare("UPDATE pedidos SET estado = 'rechazado', motivo_rechazo = ? WHERE id = ?")
            ->execute([$motivo, $pedidoId]);

        // Notificar al admin
        if (function_exists('notificarAdmin')) {
            notificarAdmin("❌ Pedido Rechazado", "Pedido {$pedidoId} rechazado. Saldo devuelto al reseller {$pedido['reseller_id']}.");
        }

        echo json_encode(['success' => true, 'message' => 'Pedido rechazado y saldo devuelto']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


if ($action == 'list_accounts') {
    // Aquí mostramos ocupados globales (ya sea por cliente o por reseller)
    // Si reseller_id NO es null, cuenta como ocupado para el admin
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM perfiles WHERE cuenta_id=c.id AND (cliente_id IS NOT NULL OR reseller_id IS NOT NULL)) as ocupados, 
            (SELECT COUNT(*) FROM perfiles WHERE cuenta_id=c.id) as total_slots 
            FROM cuentas c ORDER BY fecha_pago_proveedor ASC";
    echo json_encode($pdo->query($sql)->fetchAll());
    exit;
}

if ($action == 'add_account_with_slots') {
    // 1. LIMPIEZA AGRESIVA DE BUFFER (Para evitar errores de JSON)
    while (ob_get_level())
        ob_end_clean();
    header('Content-Type: application/json'); // Forzar cabecera JSON

    $input = file_get_contents('php://input');
    $d = json_decode($input, true);

    if (!$d) {
        echo json_encode(['success' => false, 'message' => 'No llegaron datos JSON']);
        exit;
    }

    // Validación de formato
    $parts = explode('|', $d['plataforma']);
    if (count($parts) < 3) {
        echo json_encode(['success' => false, 'message' => 'Selecciona un tipo de cuenta válido (Formato incorrecto).']);
        exit;
    }

    $nombrePlataforma = $parts[0];
    $cantidadPerfiles = intval($parts[1]);
    $tipoLogica = $parts[2];
    $costo = isset($d['costo']) ? floatval($d['costo']) : 0.00;

    // Evitar error "Undefined index" usando ??
    $token = $d['token_micuenta'] ?? NULL;

    try {
        $pdo->beginTransaction();

        // Verificar si la columna existe (opcional, pero previene crashes fatales)
        // Insertamos
        $stmt = $pdo->prepare("INSERT INTO cuentas (plataforma, email_cuenta, password, token_micuenta, fecha_pago_proveedor, costo_inversion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombrePlataforma, $d['email'], $d['password'], $token, $d['fecha_pago'], $costo]);

        $cuentaId = $pdo->lastInsertId();

        // Generar Perfiles
        for ($i = 1; $i <= $cantidadPerfiles; $i++) {
            $nombrePerfil = "Perfil $i";
            $pin = NULL;
            if ($tipoLogica === 'Link') {
                $nombrePerfil = "Cupo #$i";
                $pin = "LINK";
            } elseif ($tipoLogica === '0') {
                $nombrePerfil = ($cantidadPerfiles == 1) ? "Cuenta Completa" : "Pantalla #$i";
                $pin = "N/A";
            } elseif ($tipoLogica === '5') {
                $pin = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
            } else {
                $pin = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            }

            $pdo->prepare("INSERT INTO perfiles (cuenta_id, nombre_perfil, pin_perfil, slot_numero) VALUES (?, ?, ?, ?)")
                ->execute([$cuentaId, $nombrePerfil, $pin, $i]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        // Loguear el error real en un archivo para que tú lo veas, pero responder JSON válido
        error_log("Error DB: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
    exit;
}

// Reporte financiero
if ($action == 'get_financial_report') {
    ob_clean();

    // 1. INVERSIÓN TOTAL (CAPITAL PARADO)
    // Sumamos el costo de TODAS las cuentas que has registrado, 
    // sin importar si están vendidas, libres o a medias.
    $sqlInversion = "SELECT SUM(COALESCE(costo_inversion, 0)) FROM cuentas";
    $inversionTotal = $pdo->query($sqlInversion)->fetchColumn() ?: 0;

    // 2. CALCULAR INGRESOS ACTIVOS (CLIENTES WEB)
    // Buscamos cuánto valen las ventas que tienes activas hoy con clientes directos
    $sqlVentasWeb = "
        SELECT 
            SUM(COALESCE(cat.precio, 0) / 
                CASE 
                    WHEN (cat.nombre LIKE '%Completa%' OR cat.nombre LIKE '%Cuenta%') THEN 1 
                    ELSE (SELECT COUNT(id) FROM perfiles WHERE cuenta_id = c.id) 
                END
            )
        FROM perfiles p
        JOIN cuentas c ON p.cuenta_id = c.id
        LEFT JOIN catalogo cat ON c.plataforma = cat.nombre
        WHERE p.cliente_id IS NOT NULL AND p.reseller_id IS NULL
    ";
    // Nota: Simplifiqué la query para sumar directo el valor de venta estimado
    // Si prefieres usar el historial de pedidos aprobados, descomenta la siguiente linea:
    // $ingresosWeb = $pdo->query("SELECT SUM(precio_usd) FROM pedidos WHERE estado='aprobado'")->fetchColumn() ?: 0;

    // Usaremos una aproximación basada en catálogo para inventario activo:
    // (Esta lógica es compleja en SQL puro, volveremos a PHP para precisión como en resellers)

    // --- CÁLCULO PRECISO EN PHP (Mezcla Web + Resellers) ---
    $ingresosTotales = 0;
    $costoDeLoVendido = 0;

    // A. Ventas Directas (Clientes)
    $sqlClientes = "SELECT p.id, c.costo_inversion, cat.precio as precio_venta, c.plataforma,
                    (SELECT COUNT(id) FROM perfiles WHERE cuenta_id = c.id) as slots
                    FROM perfiles p 
                    JOIN cuentas c ON p.cuenta_id = c.id 
                    LEFT JOIN catalogo cat ON c.plataforma = cat.nombre
                    WHERE p.cliente_id IS NOT NULL AND p.reseller_id IS NULL";

    foreach ($pdo->query($sqlClientes) as $row) {
        $slots = intval($row['slots']) ?: 1;
        $costoUnit = floatval($row['costo_inversion']) / $slots;

        $precioVenta = floatval($row['precio_venta']);
        if (strpos(strtolower($row['plataforma']), 'completa') !== false) {
            $precioVenta = $precioVenta / $slots; // Ajuste unitario si el precio es pack
        }

        $ingresosTotales += $precioVenta;
        $costoDeLoVendido += $costoUnit;
    }

    // B. Ventas a Distribuidores (Resellers)
    // Aquí sumamos lo que te han pagado ellos (Historial Recargas) o el valor de su inventario
    // Para ser consistentes con "Ganancia Neta", usaremos el valor de venta al reseller.
    $sqlResellers = "SELECT p.id, c.costo_inversion, cat.precio_reseller as precio_venta, c.plataforma,
                     (SELECT COUNT(id) FROM perfiles WHERE cuenta_id = c.id) as slots
                     FROM perfiles p 
                     JOIN cuentas c ON p.cuenta_id = c.id 
                     LEFT JOIN catalogo cat ON c.plataforma = cat.nombre
                     WHERE p.reseller_id IS NOT NULL";

    foreach ($pdo->query($sqlResellers) as $row) {
        $slots = intval($row['slots']) ?: 1;
        $costoUnit = floatval($row['costo_inversion']) / $slots;

        $precioVenta = floatval($row['precio_venta']);
        if (strpos(strtolower($row['plataforma']), 'completa') !== false) {
            $precioVenta = $precioVenta / $slots;
        }

        $ingresosTotales += $precioVenta;
        $costoDeLoVendido += $costoUnit;
    }

    // 3. GANANCIA (MARGEN)
    // Ganancia = Lo que vendiste - Lo que te costó eso que vendiste
    // No restamos la Inversión Total porque eso incluiría stock parado que aun es un activo.
    $ganancia = $ingresosTotales - $costoDeLoVendido;

    echo json_encode([
        'inversion' => number_format($inversionTotal, 2), // Total gastado en proveedores (Stock + Vendido)
        'ingresos' => number_format($ingresosTotales, 2), // Valor de lo vendido
        'ganancia' => number_format($ganancia, 2)         // Tu margen limpio
    ]);
    exit;
}


if ($action == 'get_all_resellers') {
    ob_clean();

    try {
        $stmt = $pdo->query("SELECT * FROM distribuidores ORDER BY saldo DESC");
        $resellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $listaFinal = [];

        foreach ($resellers as $r) {
            $id = $r['id'];

            // 1. AGRUPAR INVENTARIO POR CUENTA MADRE
            // Esto nos permite analizar cuántos pedazos de una cuenta tiene el reseller
            $sqlInventario = "
                SELECT 
                    c.id as cuenta_id,
                    c.costo_inversion,        -- Cuánto te costó a ti la cuenta entera
                    cat.precio_reseller,      -- A cuánto se la vendiste
                    cat.nombre as nombre_prod,
                    COUNT(p.id) as perfiles_que_tiene,  -- Cuantos perfiles tiene él
                    (SELECT COUNT(id) FROM perfiles WHERE cuenta_id = c.id) as total_perfiles_global
                FROM perfiles p
                JOIN cuentas c ON p.cuenta_id = c.id
                LEFT JOIN catalogo cat ON c.plataforma = cat.nombre
                WHERE p.reseller_id = ?
                GROUP BY c.id
            ";

            $stmtInv = $pdo->prepare($sqlInventario);
            $stmtInv->execute([$id]);
            $inventario = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

            // VARIABLES TOTALES
            $totalVentaBruta = 0;   // Lo que te pagó el reseller
            $totalCostoTuyo = 0;    // Lo que te costó a ti esa parte

            foreach ($inventario as $item) {
                // DATOS BASE
                $costoInversionCuenta = floatval($item['costo_inversion']); // Ej: $15
                $precioVentaCatalogo = floatval($item['precio_reseller']);  // Ej: $4
                $slotsGlobales = intval($item['total_perfiles_global']) ?: 1; // Ej: 5
                $slotsTenidos = intval($item['perfiles_que_tiene']); // Ej: 1 o 5

                // A. CALCULAR TU COSTO REAL POR ESTOS PERFILES
                // (Costo Total Cuenta / Total Perfiles) * Perfiles que él tiene
                $costoProporcional = ($costoInversionCuenta / $slotsGlobales) * $slotsTenidos;
                $totalCostoTuyo += $costoProporcional;

                // B. CALCULAR VENTA REAL (AQUÍ ESTABA EL ERROR)
                $ventaRealItems = 0;

                // Detectar si el precio de catálogo es "Por Cuenta" o "Por Pantalla"
                $nombre = strtolower($item['nombre_prod'] ?? '');
                $esPrecioPack = (strpos($nombre, 'completa') !== false || strpos($nombre, 'cuenta') !== false);

                if ($esPrecioPack) {
                    // Si el precio en catálogo es por el PAQUETE COMPLETO (Ej: $12 la cuenta):
                    // El valor unitario de cada perfil es ($12 / 5).
                    // Multiplicamos por los que él tiene.
                    $precioUnitario = $precioVentaCatalogo / $slotsGlobales;
                    $ventaRealItems = $precioUnitario * $slotsTenidos;
                } else {
                    // Si el precio en catálogo es INDIVIDUAL (Ej: $3 la pantalla):
                    // Simplemente multiplicamos precio * cantidad
                    $ventaRealItems = $precioVentaCatalogo * $slotsTenidos;
                }

                $totalVentaBruta += $ventaRealItems;
            }

            // 2. CALCULAR GANANCIA NETA
            $gananciaNeta = $totalVentaBruta - $totalCostoTuyo;


            // 3. ASIGNAR RANGO AUTOMÁTICO
            $rango = 'bajo';
            if ($gananciaNeta >= 50)
                $rango = 'alto';
            elseif ($gananciaNeta >= 10)
                $rango = 'medio';


            // 4. PREPARAR DATOS
            $r['ventas_totales'] = number_format($totalVentaBruta, 2);
            $r['inversion_activa'] = number_format($gananciaNeta, 2);
            $r['rango'] = $rango;

            $listaFinal[] = $r;
        }

        echo json_encode($listaFinal);

    } catch (Exception $e) {
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    }
    exit;
}

// Soporte tecnico
if ($action == 'get_all_support_reports') {
    ob_clean();

    try {
        $sql = "SELECT 
                    r.id, 
                    r.mensaje, 
                    r.estado, 
                    r.fecha, 
                    r.evidencia_img,
                    d.nombre as reseller_name,
                    d.telefono as reseller_tel,
                    c.plataforma, 
                    c.email_cuenta
                FROM reportes_fallos r
                LEFT JOIN distribuidores d ON r.reseller_id = d.id
                LEFT JOIN perfiles p ON r.perfil_id = p.id
                LEFT JOIN cuentas c ON p.cuenta_id = c.id
                ORDER BY r.fecha DESC 
                LIMIT 50";

        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        // Justo después del INSERT del reporte:
        enviarNotificacionPush("⚠️ Nuevo reporte de fallo: " . $plataforma, "Soporte Técnico");

    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// CAMBIAR ESTADO DE REPORTE (SOLUCIONAR)
if ($action == 'solve_report') {
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE reportes_fallos SET estado = 'solucionado' WHERE id = ?")->execute([$d['id']]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action == 'create_reseller_user' || $action == 'add_reseller') {
    $d = json_decode(file_get_contents('php://input'), true);
    if ($pdo->query("SELECT id FROM distribuidores WHERE email='{$d['email']}'")->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'El correo ya existe']);
        exit;
    }
    $password_to_hash = $d['pass'] ?? $d['password'] ?? '';
    $pass = password_hash($password_to_hash, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO distribuidores (nombre,email,password,saldo) VALUES (?,?,?,0.00)")->execute([$d['nombre'], $d['email'], $pass]);
    echo json_encode(['success' => true]);
    exit;
}
// RECARGAR SALDO (MODIFICADO PARA REGISTRAR MOVIMIENTO VISIBLE)
if ($action == 'recharge_reseller') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);

    try {
        $pdo->beginTransaction();

        // 1. Actualizar Saldo
        $pdo->prepare("UPDATE distribuidores SET saldo = saldo + ? WHERE id = ?")->execute([$d['monto'], $d['id']]);

        // 2. Insertar en Movimientos (ESTO ES LO NUEVO E IMPORTANTE)
        // Tipo 'deposito' para que se vea verde
        $stmt = $pdo->prepare("INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion) VALUES (?, 'deposito', ?, ?)");
        // Justo después de guardar el reporte en la BD:
        enviarNotificacionPush("💸 Pago reportado de: " . $d['nombre_reseller'], "Nuevo Pago Reseller");
        $stmt->execute([$d['id'], $d['monto'], "Recarga Admin: " . $d['nota']]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action == 'update_reseller_price') {
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE catalogo SET precio_reseller = ? WHERE id = ?")->execute([$d['precio'], $d['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// 3. CLIENTES Y VENTAS
if ($action == 'list_clients') {
    echo json_encode($pdo->query("SELECT * FROM clientes ORDER BY id DESC")->fetchAll());
    exit;
}
if ($action == 'add_client') {
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("INSERT INTO clientes (nombre,telefono,email) VALUES (?,?,?)")->execute([$d['nombre'], $d['telefono'], $d['email']]);
    echo json_encode(['success' => true]);
    exit;
}
if ($action == 'update_profile_pin') {
    $d = json_decode(file_get_contents('php://input'), true);
    $id = filter_var($d['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $pin = array_key_exists('pin', $d) ? sanitize($d['pin']) : null;
    $fechaCliente = $d['fecha_cliente'] ?? null;   // YYYY-MM-DD (direct clients)
    $fechaReseller = $d['fecha_reseller'] ?? null; // YYYY-MM-DD (resellers)

    if (empty($id)) {
        echo jsonResponse(false, null, 'ID inválido', 400);
        exit;
    }

    // Construimos el SET dinámicamente para permitir cambios parciales
    $sets = [];
    $params = [];

    if ($pin !== null) {
        $sets[] = "pin_perfil = ?";
        $params[] = $pin;
    }

    if ($fechaCliente !== null) {
        // Permite limpiar la fecha enviando cadena vacía
        $sets[] = "fecha_corte_cliente = ?";
        $params[] = ($fechaCliente === '') ? null : $fechaCliente;
    }

    if ($fechaReseller !== null) {
        $sets[] = "fecha_venta_reseller = ?";
        $params[] = ($fechaReseller === '') ? null : $fechaReseller;
    }

    if (empty($sets)) {
        echo jsonResponse(false, null, 'Nada que actualizar', 400);
        exit;
    }

    $params[] = $id;
    $sql = "UPDATE perfiles SET " . implode(', ', $sets) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo jsonResponse(true);
    exit;
}

// Ventas
if ($action == 'list_assignments') {
    ob_clean();

    // Usamos alias claros para que JS los lea bien
    $sql = "SELECT 
                p.id, 
                p.nombre_perfil, 
                p.pin_perfil, 
                c.plataforma, 
                c.email_cuenta, 
                c.password,
                
                -- Datos Cliente Directo
                cli.nombre as cliente_nombre, 
                cli.telefono as cliente_telefono,
                cli.email as cliente_email,
                
                -- Datos Distribuidor
                dist.nombre as reseller_nombre,
                dist.telefono as reseller_telefono,
                
                -- Fecha Unificada (Lógica Inteligente: Cliente vs Reseller)
                CASE 
                    -- Si es cliente directo del admin, usamos su fecha de corte tal cual
                    WHEN p.cliente_id IS NOT NULL THEN p.fecha_corte_cliente
                    
                    -- Si es Reseller, calculamos desde su fecha de venta/renovación + 33 Días (Regla de Gracia)
                    WHEN p.reseller_id IS NOT NULL THEN DATE_ADD(p.fecha_venta_reseller, INTERVAL 33 DAY)
                    
                    ELSE 'Sin Fecha' 
                END as fecha_vencimiento,

                -- Identificadores
                p.reseller_id,
                p.cliente_id

            FROM perfiles p
            JOIN cuentas c ON p.cuenta_id = c.id
            LEFT JOIN clientes cli ON p.cliente_id = cli.id
            LEFT JOIN distribuidores dist ON p.reseller_id = dist.id
            
            -- Traer solo los que están ocupados
            WHERE p.cliente_id IS NOT NULL OR p.reseller_id IS NOT NULL
            
            ORDER BY fecha_vencimiento ASC";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Limpieza UTF-8 para evitar errores de JSON
    $cleanData = array_map(function ($row) {
        return array_map(function ($val) {
            return mb_convert_encoding($val ?? '', 'UTF-8', 'UTF-8');
        }, $row);
    }, $data);

    echo json_encode($cleanData);
    exit;
}
if ($action == 'get_sales_stats') {
    ob_clean();
    $sql = "SELECT 
                c.plataforma, 
                COUNT(p.id) as total,
                SUM(CASE WHEN p.cliente_id IS NOT NULL THEN 1 ELSE 0 END) as directas,
                SUM(CASE WHEN p.reseller_id IS NOT NULL THEN 1 ELSE 0 END) as resellers
            FROM perfiles p 
            JOIN cuentas c ON p.cuenta_id = c.id 
            WHERE p.cliente_id IS NOT NULL OR p.reseller_id IS NOT NULL 
            GROUP BY c.plataforma 
            ORDER BY total DESC";
    echo json_encode($pdo->query($sql)->fetchAll());
    exit;
}
if ($action == 'renew_profile') {
    $d = json_decode(file_get_contents('php://input'), true);
    $p = $pdo->query("SELECT fecha_corte_cliente FROM perfiles WHERE id={$d['id']}")->fetch();
    $base = ($p['fecha_corte_cliente'] < date('Y-m-d')) ? date('Y-m-d') : $p['fecha_corte_cliente'];
    $new = date('Y-m-d', strtotime($base . " +30 days"));
    $pdo->query("UPDATE perfiles SET fecha_corte_cliente='$new' WHERE id={$d['id']}");
    echo json_encode(['success' => true, 'nueva_fecha' => $new]);
    exit;
}
if ($action == 'release_profile') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($d['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        if (empty($id)) {
            echo jsonResponse(false, null, 'ID inválido', 400);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE perfiles SET cliente_id = NULL, reseller_id = NULL, fecha_corte_cliente = NULL, fecha_venta_reseller = NULL WHERE id = ?");
        $stmt->execute([$id]);
        echo jsonResponse(true);
    } catch (Exception $e) {
        safeLog("Error release_profile: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al liberar perfil');
    }
    exit;
}
// Alertas
if ($action == 'check_expired') {
    ob_clean();

    // Consulta UNION: Combina clientes y cuentas maestras
    // Alias unificados: id, titulo, detalle, fecha, tipo

    $sql = "
    (
        -- 1. CLIENTES (Perfiles)
        SELECT 
            p.id, 
            c.plataforma as titulo, 
            cl.nombre as detalle, 
            cl.telefono as extra_info,
            p.fecha_corte_cliente as fecha, 
            'cliente' as tipo
        FROM perfiles p
        JOIN clientes cl ON p.cliente_id = cl.id
        JOIN cuentas c ON p.cuenta_id = c.id
        WHERE p.fecha_corte_cliente <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) -- Vencidos o próximos (3 días)
    )
    UNION
    (
        -- 2. PROVEEDORES (Cuentas Maestras)
        SELECT 
            id, 
            plataforma as titulo, 
            email_cuenta as detalle, 
            'Proveedor' as extra_info,
            fecha_pago_proveedor as fecha, 
            'master' as tipo
        FROM cuentas
        WHERE fecha_pago_proveedor <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    )
    ORDER BY fecha ASC -- Ordenar todo por fecha (lo más urgente arriba)
    LIMIT 50";

    $stmt = $pdo->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// 4. OTROS
if ($action == 'get_inbox_specific') {
    ob_clean();
    if (!extension_loaded('imap')) {
        echo jsonResponse(false, null, 'No IMAP');
        exit;
    }
    try {
        $id = filter_var($_GET['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        if (empty($id)) {
            echo jsonResponse(false, null, 'ID inválido', 400);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM email_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $acc = $stmt->fetch();

        if (!$acc) {
            echo jsonResponse(false, null, 'Cuenta no encontrada');
            exit;
        }
        $host = $acc['host'] == 'localhost' ? 'mail.' . substr(strrchr($acc['email'], "@"), 1) : $acc['host'];

        // Configurar tiempo de espera corto para evitar error 500 por timeout
        imap_timeout(IMAP_OPENTIMEOUT, 10);

        $inbox = @imap_open("{{$host}:993/imap/ssl/novalidate-cert}INBOX", $acc['email'], $acc['password'], 0, 1);

        if (!$inbox) {
            echo jsonResponse(false, null, 'Error al conectar: ' . imap_last_error());
            exit;
        }

        $msgs = [];
        $tot = imap_num_msg($inbox);

        if ($tot > 0) {
            for ($i = $tot; $i > max($tot - 5, 0); $i--) {
                $h = imap_headerinfo($inbox, $i);
                if (!$h)
                    continue;

                $s = imap_fetchstructure($inbox, $i);
                $pNum = 1;
                $enc = 0;
                if (isset($s->parts)) {
                    foreach ($s->parts as $k => $p) {
                        if ($p->subtype == 'HTML') {
                            $pNum = $k + 1;
                            $enc = $p->encoding;
                            break;
                        }
                        if ($p->subtype == 'PLAIN') {
                            $pNum = $k + 1;
                            $enc = $p->encoding;
                        }
                    }
                } else {
                    $enc = $s->encoding;
                }

                $b = imap_fetchbody($inbox, $i, $pNum);
                if ($enc == 3)
                    $b = base64_decode($b);
                elseif ($enc == 4)
                    $b = quoted_printable_decode($b);

                $msgs[] = [
                    'asunto' => isset($h->subject) ? sanitize(imap_utf8($h->subject)) : '(Sin Asunto)',
                    'de' => isset($h->from[0]) ? sanitize($h->from[0]->mailbox . '@' . $h->from[0]->host, 'email') : 'Desconocido',
                    'fecha' => isset($h->date) ? date('d-m H:i', strtotime($h->date)) : '--',
                    'cuerpo_full' => sanitize(mb_convert_encoding($b, 'UTF-8', 'auto'))
                ];
            }
        }

        imap_close($inbox);
        echo jsonResponse(true, ['correos' => $msgs]);

    } catch (Exception $e) {
        safeLog("Error get_inbox_specific: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al cargar correos');
    }
    exit;
}
if ($action == 'save_email_account') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);

        $errors = validate($d, [
            'email' => 'required|email',
            'password' => 'required|min:3'
        ]);

        if (!empty($errors)) {
            echo jsonResponse(false, ['errors' => $errors], 'Datos inválidos', 400);
            exit;
        }

        $email = sanitize($d['email'], 'email');
        $password = sanitize($d['password']);
        $dom = substr(strrchr($email, "@"), 1);
        $host = 'mail.' . $dom;

        $stmt = $pdo->prepare("INSERT INTO email_accounts (email, password, host) VALUES (?, ?, ?)");
        $stmt->execute([$email, $password, $host]);
        echo jsonResponse(true);
    } catch (Exception $e) {
        safeLog("Error save_email_account: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al guardar cuenta');
    }
    exit;
}
if ($action == 'list_email_accounts') {
    echo json_encode($pdo->query("SELECT id,email FROM email_accounts")->fetchAll());
    exit;
}
if ($action == 'delete_email_account') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($d['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        if (empty($id)) {
            echo jsonResponse(false, null, 'ID inválido', 400);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM email_accounts WHERE id = ?");
        $stmt->execute([$id]);
        echo jsonResponse(true);
    } catch (Exception $e) {
        safeLog("Error delete_email_account: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al eliminar cuenta');
    }
    exit;
}

if ($action == 'list_orders') {
    try {
        $estado = sanitize($_GET['estado'] ?? 'pendiente');
        $allowedStates = ['pendiente', 'aprobado', 'rechazado'];
        if (!in_array($estado, $allowedStates)) {
            $estado = 'pendiente';
        }

        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE estado = ? ORDER BY id DESC");
        $stmt->execute([$estado]);
        echo jsonResponse(true, $stmt->fetchAll());
    } catch (Exception $e) {
        safeLog("Error list_orders: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al cargar pedidos');
    }
    exit;
}
if ($action == 'update_order_status') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($d['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        $estado = sanitize($d['estado'] ?? '');

        $allowedStates = ['pendiente', 'aprobado', 'rechazado'];
        if (!in_array($estado, $allowedStates)) {
            echo jsonResponse(false, null, 'Estado inválido', 400);
            exit;
        }

        if (empty($id)) {
            echo jsonResponse(false, null, 'ID inválido', 400);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        echo jsonResponse(true);
    } catch (Exception $e) {
        safeLog("Error update_order_status: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al actualizar estado');
    }
    exit;
}
// Editar pedido (admin)
if ($action == 'edit_order') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($d['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        $nombre = sanitize($d['nombre'] ?? '');
        $telefono = sanitize($d['telefono'] ?? '');
        $email = sanitize($d['email'] ?? '', 'email');
        $estado = sanitize($d['estado'] ?? '');

        $stmt = $pdo->prepare("UPDATE pedidos SET cliente_nombre = ?, cliente_telefono = ?, cliente_email = ?, estado = ? WHERE id = ?");
        $stmt->execute([$nombre, $telefono, $email, $estado, $id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// APROBACIÓN (FILTRO STOCK RESELLER)
if ($action == 'approve_order_automated') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $oid = filter_var($d['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        if (empty($oid)) {
            echo jsonResponse(false, null, 'ID de pedido inválido', 400);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
        $stmt->execute([$oid]);
        $ped = $stmt->fetch();

        if (!$ped) {
            throw new Exception("Pedido no encontrado");
        }

        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE telefono = ?");
        $stmt->execute([$ped['cliente_telefono']]);
        $cli = $stmt->fetch();

        if ($cli) {
            $cid = $cli['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)");
            $stmt->execute([
                sanitize($ped['cliente_nombre']),
                sanitize($ped['cliente_telefono']),
                sanitize($ped['cliente_email'] ?? '', 'email')
            ]);
            $cid = $pdo->lastInsertId();
        }

        $reqs = [];
        $combos = ['NetMAX' => ['Netflix', 'HBO Max']];
        foreach ($combos as $k => $v) {
            if (stripos($ped['nombre_producto'], $k) !== false) {
                $reqs = $v;
                break;
            }
        }
        if (!$reqs) {
            $reqs[] = explode(' ', $ped['nombre_producto'])[0];
        }

        $ents = [];
        $vence = date('Y-m-d', strtotime("+30 days"));
        foreach ($reqs as $r) {
            // FILTRO IMPORTANTE: AND p.reseller_id IS NULL
            $stk = $pdo->prepare("SELECT p.id, p.nombre_perfil, p.pin_perfil, c.email_cuenta, c.password, c.plataforma 
                                  FROM perfiles p 
                                  JOIN cuentas c ON p.cuenta_id = c.id 
                                  WHERE c.plataforma LIKE ? AND p.cliente_id IS NULL AND p.reseller_id IS NULL 
                                  LIMIT 1");
            $stk->execute(["%$r%"]);
            $p = $stk->fetch();
            if (!$p) {
                throw new Exception("Sin Stock de $r");
            }

            $stmt = $pdo->prepare("UPDATE perfiles SET cliente_id = ?, fecha_corte_cliente = ? WHERE id = ?");
            $stmt->execute([$cid, $vence, $p['id']]);

            $ents[] = [
                'plataforma' => $p['plataforma'],
                'email' => $p['email_cuenta'],
                'pass' => $p['password'],
                'perfil' => $p['nombre_perfil'],
                'pin' => $p['pin_perfil'],
                'vence' => $vence
            ];
        }

        $stmt = $pdo->prepare("UPDATE pedidos SET estado = 'aprobado' WHERE id = ?");
        $stmt->execute([$oid]);

        $pdo->commit();

        if (!empty($ped['cliente_email'])) {
            enviarCorreoHTML($ped['cliente_email'], "Pedido Listo", "Tus cuentas están activas.");
        }

        echo jsonResponse(true, [
            'cliente_id' => $cid,
            'cliente_nombre' => $ped['cliente_nombre'],
            'cliente_telefono' => $ped['cliente_telefono'],
            'cuentas' => $ents
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        safeLog("Error approve_order_automated: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, $e->getMessage());
    }
    exit;
}

if ($action == 'auto_assign_profile') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $cid = filter_var($d['cliente_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        $plat = sanitize($d['plataforma'] ?? '');

        if (empty($cid) || empty($plat)) {
            echo jsonResponse(false, null, 'Datos incompletos', 400);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$cid]);
        $cli = $stmt->fetch();

        if (!$cli) {
            throw new Exception("Cliente no existe");
        }

        // FILTRO IMPORTANTE: AND p.reseller_id IS NULL
        $stk = $pdo->prepare("SELECT p.id, p.nombre_perfil, p.pin_perfil, c.email_cuenta, c.password, c.plataforma 
                              FROM perfiles p 
                              JOIN cuentas c ON p.cuenta_id = c.id 
                              WHERE c.plataforma LIKE ? AND p.cliente_id IS NULL AND p.reseller_id IS NULL 
                              LIMIT 1");
        $stk->execute(["%$plat%"]);
        $p = $stk->fetch();

        if (!$p) {
            throw new Exception("Sin stock");
        }

        $vence = date('Y-m-d', strtotime("+30 days"));
        $stmt = $pdo->prepare("UPDATE perfiles SET cliente_id = ?, fecha_corte_cliente = ? WHERE id = ?");
        $stmt->execute([$cid, $vence, $p['id']]);

        echo jsonResponse(true, [
            'datos' => [
                'plataforma' => $p['plataforma'],
                'email' => $p['email_cuenta'],
                'pass' => $p['password'],
                'perfil' => $p['nombre_perfil'],
                'pin' => $p['pin_perfil'],
                'vence' => $vence,
                'cliente_nombre' => $cli['nombre'],
                'cliente_telefono' => $cli['telefono']
            ]
        ]);
    } catch (Exception $e) {
        safeLog("Error auto_assign_profile: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, $e->getMessage());
    }
    exit;
}

// GUARDAR PRODUCTO (CON TIPO DE ENTREGA)
if ($action == 'save_product') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);

    // DEBUG: Log de datos recibidos
    error_log("🔍 save_product - Datos recibidos: " . json_encode($d));

    // Default a credenciales si no viene definido
    $tipo = $d['tipo_entrega'] ?? 'credenciales';
    $precioReseller = $d['precio_reseller'] ?? 0;

    error_log("🔍 save_product - tipo_entrega extraído: " . $tipo);

    try {
        if (isset($d['id']) && $d['id'] != '') {
            $stmt = $pdo->prepare("UPDATE catalogo SET nombre=?, precio=?, precio_reseller=?, descripcion=?, categoria=?, tipo_entrega=? WHERE id=?");
            $params = [$d['nombre'], $d['precio'], $precioReseller, $d['desc'], $d['cat'], $tipo, $d['id']];
            error_log("🔍 save_product - UPDATE params: " . json_encode($params));
            $res = $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("INSERT INTO catalogo (nombre, precio, precio_reseller, descripcion, categoria, tipo_entrega) VALUES (?, ?, ?, ?, ?, ?)");
            $params = [$d['nombre'], $d['precio'], $precioReseller, $d['desc'], $d['cat'], $tipo];
            error_log("🔍 save_product - INSERT params: " . json_encode($params));
            $res = $stmt->execute($params);
        }
        error_log("🔍 save_product - Resultado: " . ($res ? 'SUCCESS' : 'FAILED'));
        echo json_encode(['success' => $res]);
    } catch (Exception $e) {
        // Auto-fix Schema: Add column if missing
        if (strpos($e->getMessage(), "Unknown column") !== false) {
            $pdo->exec("ALTER TABLE catalogo ADD COLUMN tipo_entrega VARCHAR(50) DEFAULT 'credenciales'");
            // Retry operation
            if (isset($d['id']) && $d['id'] != '') {
                $stmt = $pdo->prepare("UPDATE catalogo SET nombre=?, precio=?, precio_reseller=?, descripcion=?, categoria=?, tipo_entrega=? WHERE id=?");
                $res = $stmt->execute([$d['nombre'], $d['precio'], $precioReseller, $d['desc'], $d['cat'], $tipo, $d['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO catalogo (nombre, precio, precio_reseller, descripcion, categoria, tipo_entrega) VALUES (?, ?, ?, ?, ?, ?)");
                $res = $stmt->execute([$d['nombre'], $d['precio'], $precioReseller, $d['desc'], $d['cat'], $tipo]);
            }
            echo json_encode(['success' => $res]);
        } else {
            throw $e;
        }
    }
    exit;
}

if ($action == 'delete_account') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $id = $d['id'] ?? 0;
        if (!$id)
            throw new Exception("ID no válido");

        $pdo->beginTransaction();
        // Delete profiles first (or set null depending on need, but usually delete for full cleanup)
        $pdo->prepare("DELETE FROM perfiles WHERE cuenta_id = ?")->execute([$id]);
        // Delete account
        $pdo->prepare("DELETE FROM cuentas WHERE id = ?")->execute([$id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}



if ($action == 'add_account') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);

        $email = sanitize($d['email'] ?? '', 'email');
        $pass = sanitize($d['password'] ?? '');
        $plat = sanitize($d['plataforma'] ?? '');
        $fecha = $d['fecha_pago'] ?? date('Y-m-d');
        $costo = floatval($d['costo'] ?? 0);
        $slots = intval($d['slots'] ?? 1);
        $tipo = $d['tipo_venta'] ?? 'pantalla'; // 'pantalla' or 'completa'

        if (empty($email) || empty($pass) || empty($plat))
            throw new Exception("Datos incompletos");

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO cuentas (email_cuenta, password, plataforma, fecha_pago_proveedor, costo_inversion) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$email, $pass, $plat, $fecha, $costo]);
        $cuentaId = $pdo->lastInsertId();

        // Create profiles
        // If 'completa', maybe just 1 profile named 'Cuenta Completa'
        if ($tipo === 'completa') {
            $pdo->prepare("INSERT INTO perfiles (cuenta_id, nombre_perfil, pin_perfil, slot_numero) VALUES (?, ?, ?, ?)")
                ->execute([$cuentaId, "Cuenta Completa", "N/A", 1]); // No PIN, slot 1
        } else {
            // Pantalla: Create 1..N profiles
            for ($i = 1; $i <= $slots; $i++) {
                $pin = 'N/A'; // Usamos N/A por defecto en lugar de null para evitar errores si la columna no acepta NULL
                $nombre = "Perfil $i";
                // Agregamos slot_numero al insert, igual que en add_account_with_slots
                $pdo->prepare("INSERT INTO perfiles (cuenta_id, nombre_perfil, pin_perfil, slot_numero) VALUES (?, ?, ?, ?)")
                    ->execute([$cuentaId, $nombre, $pin, $i]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) { // Capturar Throwable para errores fatales de PHP 7+
        if ($pdo->inTransaction())
            $pdo->rollBack();
        error_log("Add Account Error: " . $e->getMessage()); // Log al error log de PHP
        echo json_encode(['success' => false, 'message' => 'Error Interno: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'get_account_details_full') {
    // Get account info + profiles with assigned clients/resellers
    $id = $_GET['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'No ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM cuentas WHERE id = ?");
        $stmt->execute([$id]);
        $acc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($acc) {
            $stmtP = $pdo->prepare("
                SELECT p.*, 
                       c.nombre as cliente_nombre, 
                       d.nombre as reseller_nombre
                FROM perfiles p 
                LEFT JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN distribuidores d ON p.reseller_id = d.id
                WHERE p.cuenta_id = ?
            ");
            $stmtP->execute([$id]);
            $perfiles = $stmtP->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'cuenta' => $acc,
                    'perfiles' => $perfiles
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cuenta no encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action == 'delete_product') {
    $pdo->query("DELETE FROM catalogo WHERE id=" . json_decode(file_get_contents('php://input'), true)['id']);
    echo json_encode(['success' => true]);
    exit;
}



if ($action == 'get_reseller_sales') {
    // List global sales by resellers
    try {
        $sql = "SELECT p.*, d.nombre as reseller_name, c.plataforma 
                FROM perfiles p 
                JOIN distribuidores d ON p.reseller_id = d.id 
                JOIN cuentas c ON p.cuenta_id = c.id
                WHERE p.reseller_id IS NOT NULL 
                ORDER BY p.fecha_venta_reseller DESC LIMIT 500";
        echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}


if ($action == 'update_tasa') {
    $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_dolar'")->execute([json_decode(file_get_contents('php://input'), true)['tasa']]);
    echo json_encode(['success' => true]);
    exit;
}
if ($action == 'backup_db') {
    $tables = [];
    $r = $pdo->query("SHOW TABLES");
    while ($row = $r->fetch(PDO::FETCH_NUM))
        $tables[] = $row[0];
    $sql = "";
    foreach ($tables as $t) {
        $r2 = $pdo->query("SELECT * FROM $t");
        $num = $r2->columnCount();
        $sql .= "DROP TABLE IF EXISTS $t;";
        $row2 = $pdo->query("SHOW CREATE TABLE $t")->fetch(PDO::FETCH_NUM);
        $sql .= "\n\n" . $row2[1] . ";\n\n";
        while ($row = $r2->fetch(PDO::FETCH_NUM)) {
            $sql .= "INSERT INTO $t VALUES(";
            for ($j = 0; $j < $num; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                if (isset($row[$j]))
                    $sql .= '"' . $row[$j] . '"';
                else
                    $sql .= '""';
                if ($j < ($num - 1))
                    $sql .= ',';
            }
            $sql .= ");\n";
        }
        $sql .= "\n\n\n";
    }
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"backup.sql\"");
    echo $sql;
    exit;
}
if ($action == 'list_coupons') {
    echo json_encode($pdo->query("SELECT * FROM cupones ORDER BY id DESC")->fetchAll());
    exit;
}
if ($action == 'delete_coupon') {
    $pdo->query("DELETE FROM cupones WHERE id=" . json_decode(file_get_contents('php://input'), true)['id']);
    echo json_encode(['success' => true]);
    exit;
}
if ($action == 'save_coupon') {
    $d = json_decode(file_get_contents('php://input'), true);
    try {
        $pdo->prepare("INSERT INTO cupones (codigo, descuento, usos_max) VALUES (?,?,?)")->execute([strtoupper($d['codigo']), $d['descuento'], $d['usos']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Código duplicado']);
    }
    exit;
}

// Gestion avanzada de distribuidores

// A. VER DETALLES COMPLETOS (INVENTARIO Y CLIENTES DEL RESELLER)
if ($action == 'get_reseller_details') {
    $rid = $_GET['id'];

    // 1. Sus cuentas compradas
    $cuentas = $pdo->query("
        SELECT p.nombre_perfil, p.pin_perfil, p.fecha_venta_reseller, 
               c.plataforma, c.email_cuenta, c.password
        FROM perfiles p
        JOIN cuentas c ON p.cuenta_id = c.id
        WHERE p.reseller_id = $rid
        ORDER BY p.fecha_venta_reseller DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Sus clientes registrados
    $clientes = $pdo->query("SELECT * FROM clientes_reseller WHERE reseller_id = $rid")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'cuentas' => $cuentas, 'clientes' => $clientes]);
    exit;
}

// MOVIMIENTOS DEL RESELLER (HISTORIAL FINANCIERO)
if ($action == 'get_reseller_movements') {
    $rid = intval($_GET['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT id, tipo, monto, descripcion, fecha FROM movimientos_reseller WHERE reseller_id = ? ORDER BY fecha DESC LIMIT 500");
        $stmt->execute([$rid]);
        $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($movs);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// OBTENER DETALLES DE STOCK (PERFILES DISPONIBLES)
if ($action == 'get_stock_details') {
    $plat = $_GET['plataforma'] ?? '';
    try {
        // Intento 1: búsqueda directa por plataforma (LIKE)
        $stmt = $pdo->prepare("SELECT p.id, p.nombre_perfil, p.pin_perfil, c.email_cuenta, c.plataforma, c.id as cuenta_id
                               FROM perfiles p
                               JOIN cuentas c ON p.cuenta_id = c.id
                               WHERE c.plataforma LIKE ? AND p.cliente_id IS NULL AND p.reseller_id IS NULL
                               ORDER BY p.id ASC
                               LIMIT 500");
        $stmt->execute(["%$plat%"]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalizar función en PHP para comparar sin depender del formato exacto
        $normalize = function ($s) {
            $s = (string) $s;
            $s = preg_replace('/\s*\(.*?\)\s*/u', ' ', $s); // quitar paréntesis y su contenido
            $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s); // quitar símbolos raros
            $s = preg_replace('/\s+/u', ' ', $s); // compactar espacios
            $s = trim(mb_strtolower($s, 'UTF-8'));
            return $s;
        };

        $normPlat = $normalize($plat);

        // Filtrar rows en PHP comparando la versión normalizada
        $filtered = [];
        foreach ($rows as $r) {
            $rplat = $r['plataforma'] ?? $r['plataforma'] ?? '';
            if ($normPlat === '' || mb_strpos($normalize($rplat), $normPlat) !== false) {
                $filtered[] = $r;
            }
        }

        // Si no encontramos con la búsqueda directa, intentar por tokens o devolver algunos disponibles
        if (empty($filtered) && !empty($normPlat)) {
            // intentar tokens
            $tokens = preg_split('/\s+/', $normPlat);
            foreach ($tokens as $t) {
                if (strlen($t) < 2)
                    continue;
                foreach ($rows as $r) {
                    if (mb_strpos($normalize($r['plataforma'] ?? ''), $t) !== false) {
                        $filtered[] = $r;
                        break;
                    }
                }
                if (!empty($filtered))
                    break;
            }
        }

        // última opción: devolver algunos perfiles disponibles si no hay match (para no mostrar vacío)
        if (empty($filtered)) {
            $stmtAny = $pdo->prepare("SELECT p.id, p.nombre_perfil, p.pin_perfil, c.email_cuenta, c.plataforma, c.id as cuenta_id
                                      FROM perfiles p
                                      JOIN cuentas c ON p.cuenta_id = c.id
                                      WHERE p.cliente_id IS NULL AND p.reseller_id IS NULL
                                      ORDER BY p.id ASC
                                      LIMIT 200");
            $stmtAny->execute();
            $filtered = $stmtAny->fetchAll(PDO::FETCH_ASSOC);
        }

        // Escribir log diagnóstico para depuración (no incluir contraseñas)
        try {
            $count = is_array($filtered) ? count($filtered) : 0;
            $samples = [];
            if ($count > 0) {
                foreach (array_slice($filtered, 0, 5) as $s) {
                    $samples[] = ($s['plataforma'] ?? '') . ' | ' . ($s['email_cuenta'] ?? '');
                }
            }
            $msg = "get_stock_details requested platform=\"{$plat}\" normalized=\"{$normPlat}\" results={$count} samples=" . implode('; ', $samples);
            if (function_exists('safeLog'))
                safeLog($msg, 'INFO');
        } catch (Exception $e) {
            // no-op
        }

        echo json_encode($filtered);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// OBTENER USUARIOS (PERFILES) DE UNA CUENTA MAESTRA
if ($action == 'get_account_users') {
    // Accept either account id (`id`) or profile id (`profile_id`)
    $accountId = 0;
    if (!empty($_GET['profile_id'])) {
        $pid = intval($_GET['profile_id']);
        if ($pid > 0) {
            $r = $pdo->prepare("SELECT cuenta_id FROM perfiles WHERE id = ? LIMIT 1");
            $r->execute([$pid]);
            $tmp = $r->fetch(PDO::FETCH_ASSOC);
            $accountId = intval($tmp['cuenta_id'] ?? 0);
        }
    }
    if (empty($accountId)) {
        $accountId = intval($_GET['id'] ?? 0);
    }
    if ($accountId <= 0) {
        echo json_encode([]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT p.id, p.nombre_perfil, p.pin_perfil, p.slot_numero, p.fecha_corte_cliente, c.email_cuenta, c.password
                               FROM perfiles p
                               JOIN cuentas c ON p.cuenta_id = c.id
                               WHERE c.id = ? 
                               ORDER BY p.slot_numero ASC, p.id ASC");
        $stmt->execute([$accountId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    } catch (Exception $e) {
        safeLog("Error get_account_users: " . $e->getMessage(), "ERROR");
        echo json_encode([]);
    }
    exit;
}

if ($action == 'update_assignment') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($d['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        $newName = sanitize($d['nombre'] ?? '');
        $newTel = sanitize($d['telefono'] ?? '');
        $newEmail = sanitize($d['email'] ?? '', 'email');
        $newDate = $d['fecha'] ?? null;
        $type = $d['type'] ?? 'cliente';

        if (empty($id)) {
            echo jsonResponse(false, null, 'ID inválido', 400);
            exit;
        }

        $pdo->beginTransaction();

        // 1. Update Profile (Date)
        if ($newDate) {
            if ($type === 'reseller') {
                // For resellers, the logic usually is date_sale + 33 days. 
                // If admin wants to force a specific expiration date, we need to adjust the sale date 
                // such that sale_date + 33 = new_expiration.
                // So: sale_date = new_expiration - 33 days.
                $calculatedSaleDate = date('Y-m-d', strtotime($newDate . ' -33 days'));
                $stmt = $pdo->prepare("UPDATE perfiles SET fecha_venta_reseller = ? WHERE id = ?");
                $stmt->execute([$calculatedSaleDate, $id]);
            } else {
                // Direct client: just update the cut-off date
                $stmt = $pdo->prepare("UPDATE perfiles SET fecha_corte_cliente = ? WHERE id = ?");
                $stmt->execute([$newDate, $id]);
            }
        }

        // 2. Update Client Info (if linked)
        // Ensure we find the client_id attached to this profile
        $stmt = $pdo->prepare("SELECT client_id FROM perfiles WHERE id = ?"); // Correction: it is cliente_id in schema check
        // Double check schema col name: previous reads showed 'cliente_id'
        $stmt = $pdo->prepare("SELECT cliente_id FROM perfiles WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row && !empty($row['cliente_id'])) {
            $cid = $row['cliente_id'];
            $stmt = $pdo->prepare("UPDATE clientes SET nombre = ?, telefono = ?, email = ? WHERE id = ?");
            $stmt->execute([$newName, $newTel, $newEmail, $cid]);
        }

        $pdo->commit();
        echo jsonResponse(true);

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        safeLog("Error update_assignment: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al actualizar venta');
    }
    exit;
}


// ASIGNAR UN PERFIL A UN CLIENTE REGISTRADO (VENTA MANUAL)
if ($action == 'assign_profile') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $perfil_id = intval($d['perfil_id'] ?? 0);
        $cliente_id = intval($d['cliente_id'] ?? 0);

        if (empty($perfil_id) || empty($cliente_id)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }

        // Verificar cliente existe
        $stmt = $pdo->prepare("SELECT id, nombre, telefono FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cli = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cli) {
            echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
            exit;
        }

        // Verificar perfil disponible
        $stmt = $pdo->prepare("SELECT id FROM perfiles WHERE id = ? AND cliente_id IS NULL AND reseller_id IS NULL");
        $stmt->execute([$perfil_id]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Perfil no disponible']);
            exit;
        }

        // Asignar perfil al cliente y fijar fecha de corte a +30 días
        $vence = date('Y-m-d', strtotime("+30 days"));
        $upd = $pdo->prepare("UPDATE perfiles SET cliente_id = ?, fecha_corte_cliente = ? WHERE id = ?");
        $upd->execute([$cliente_id, $vence, $perfil_id]);

        // Devolver datos del perfil asignado
        $stmt = $pdo->prepare("SELECT p.id, p.nombre_perfil, p.pin_perfil, c.email_cuenta, c.password, c.plataforma FROM perfiles p JOIN cuentas c ON p.cuenta_id = c.id WHERE p.id = ?");
        $stmt->execute([$perfil_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => ['perfil' => $res, 'vence' => $vence, 'cliente' => $cli]]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// B. DESCONTAR SALDO
if ($action == 'deduct_reseller_balance') {
    $d = json_decode(file_get_contents('php://input'), true);
    try {
        $pdo->beginTransaction();
        // Restar saldo
        $pdo->prepare("UPDATE distribuidores SET saldo = saldo - ? WHERE id = ?")->execute([$d['monto'], $d['id']]);

        $pdo->prepare("INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion) VALUES (?, 'ajuste_retiro', ?, ?)")
            ->execute([$d['id'], $d['monto'], "Ajuste Admin: " . $d['nota']]);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// C. ELIMINAR DISTRIBUIDOR
if ($action == 'delete_reseller') {
    $d = json_decode(file_get_contents('php://input'), true);
    // OJO: Al borrar, la BD hará cascade en notificaciones y clientes, 
    // pero los PERFILES comprados quedarán con reseller_id apuntando a nada o NULL.
    // Vamos a liberar los perfiles para que vuelvan a tu stock (Opcional, si prefieres que se borren, cambia la lógica)

    $pdo->prepare("UPDATE perfiles SET reseller_id = NULL, cliente_reseller_id = NULL, fecha_venta_reseller = NULL WHERE reseller_id = ?")->execute([$d['id']]);

    // Ahora borramos al usuario
    $pdo->prepare("DELETE FROM distribuidores WHERE id = ?")->execute([$d['id']]);

    echo json_encode(['success' => true]);
    exit;
}

// D. ENVIAR NOTIFICACIÓN
if ($action == 'send_notification') {
    $d = json_decode(file_get_contents('php://input'), true);
    $target = ($d['id'] === 'all') ? NULL : $d['id'];

    $stmt = $pdo->prepare("INSERT INTO notificaciones_reseller (reseller_id, mensaje) VALUES (?, ?)");
    $stmt->execute([$target, $d['mensaje']]);

    echo json_encode(['success' => true]);
    exit;
}

// --- GESTIÓN DE TELEGRAM MARKETING ---

// 1. ADMIN: GUARDAR ENLACE
if ($action == 'update_telegram_link') {
    // Solo admin puede guardar
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }

    $d = json_decode(file_get_contents('php://input'), true);

    $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('link_telegram_marketing', ?) ON DUPLICATE KEY UPDATE valor = ?");
    $stmt->execute([$d['link'], $d['link']]);

    echo json_encode(['success' => true]);
    exit;
}

// 2. ADMIN Y RESELLER: OBTENER ENLACE
if ($action == 'get_telegram_link') {
    // Cualquiera logueado puede pedir el link
    ob_clean();
    $link = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'link_telegram_marketing'")->fetchColumn();

    echo json_encode(['success' => true, 'link' => $link ? $link : '#']);
    exit;
}

// Feedback y sugerencias

// ENVIAR FEEDBACK
if ($action == 'send_feedback') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);

    if (empty($d['mensaje'])) {
        echo json_encode(['success' => false, 'message' => 'El mensaje no puede estar vacío']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO feedback_sistema (reseller_id, tipo, mensaje) VALUES (?, ?, ?)");
        $stmt->execute([$myId, $d['tipo'], $d['mensaje']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// VER MIS SUGERENCIAS (HISTORIAL)
if ($action == 'get_my_feedback') {
    ob_clean();
    $stmt = $pdo->prepare("SELECT * FROM feedback_sistema WHERE reseller_id = ? ORDER BY fecha DESC");
    $stmt->execute([$myId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// 12.1 UPDATE MASTER ACCOUNT
if ($action == 'update_master_account') {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        if (!isset($d['id']))
            throw new Exception("ID Requerido");

        $sql = "UPDATE cuentas SET email_cuenta = ?, password = ?, fecha_pago_proveedor = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $d['email'],
            $d['password'],
            empty($d['fecha']) ? null : $d['fecha'],
            $d['id']
        ]);

        echo jsonResponse(true);
    } catch (Exception $e) {
        safeLog("Error update_master_account: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al actualizar');
    }
    exit;
}

// 12.2 LISTAR CUENTAS MAESTRAS
if ($action == 'list_accounts') {
    $stmt = $pdo->query("SELECT c.*, 
						(SELECT COUNT(*) FROM perfiles WHERE cuenta_id = c.id) as total_slots,
						(SELECT COUNT(*) FROM perfiles WHERE cuenta_id = c.id AND (cliente_id IS NOT NULL OR reseller_id IS NOT NULL)) as ocupados
						FROM cuentas c ORDER BY c.id DESC");
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// 12.3 GET ACCOUNT DETAILS FULL (Master + Profiles)
if ($action == 'get_account_details_full') {
    try {
        $id = $_GET['id'];
        // Get Master
        $stmtC = $pdo->prepare("SELECT * FROM cuentas WHERE id = ?");
        $stmtC->execute([$id]);
        $cuenta = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$cuenta) {
            echo jsonResponse(false, null, 'Cuenta no encontrada');
            exit;
        }

        // Get Profiles
        $stmtP = $pdo->prepare("SELECT p.* FROM perfiles p WHERE p.cuenta_id = ? ORDER BY p.slot_numero ASC");
        $stmtP->execute([$id]);
        $perfiles = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        echo jsonResponse(true, ['cuenta' => $cuenta, 'perfiles' => $perfiles]);

    } catch (Exception $e) {
        safeLog("Error get_account_details_full: " . $e->getMessage(), "ERROR");
        echo jsonResponse(false, null, 'Error al cargar detalles');
    }
    exit;
}

// Feedback de distribuidores
if ($action == 'get_all_feedback') {
    ob_clean(); // Limpiar errores previos

    try {
        $sql = "SELECT 
                    f.id, 
                    f.tipo, 
                    f.mensaje, 
                    f.fecha,
                    COALESCE(d.nombre, 'Usuario Eliminado') as autor,
                    d.email
                FROM feedback_sistema f
                LEFT JOIN distribuidores d ON f.reseller_id = d.id
                ORDER BY f.fecha DESC 
                LIMIT 50";

        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($data);

    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// Monitor de ventas B2B
if ($action == 'get_all_reseller_sales') {
    ob_clean(); // Limpiar errores previos

    // Usamos IFNULL para que no falle si la fecha está vacía
    $sql = "SELECT d.nombre as reseller, 
                   c.plataforma, 
                   c.email_cuenta,
                   p.nombre_perfil, 
                   p.pin_perfil,
                   cr.nombre as cliente_final,
                   IFNULL(p.fecha_venta_reseller, 'Sin fecha') as fecha_venta_reseller
            FROM perfiles p
            JOIN distribuidores d ON p.reseller_id = d.id
            JOIN cuentas c ON p.cuenta_id = c.id
            LEFT JOIN clientes_reseller cr ON p.cliente_reseller_id = cr.id
            WHERE p.reseller_id IS NOT NULL
            ORDER BY p.fecha_venta_reseller DESC 
            LIMIT 100";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Corrección de caracteres UTF-8 (Para evitar JSON vacío)
    $dataFixed = array_map(function ($row) {
        return array_map(function ($val) {
            return mb_convert_encoding($val, 'UTF-8', 'UTF-8');
        }, $row);
    }, $data);

    echo json_encode($dataFixed);
    exit;
}

// --- 19. VER DETALLES DE CUENTA MAESTRA (QUIÉN LA USA) ---
if ($action == 'get_account_details_admin') {
    $id = $_GET['id'];

    $sql = "SELECT p.slot_numero, p.nombre_perfil, p.pin_perfil, p.fecha_corte_cliente,
                   c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                   d.nombre as reseller_nombre
            FROM perfiles p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN distribuidores d ON p.reseller_id = d.id
            WHERE p.cuenta_id = ?
            ORDER BY p.slot_numero ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// 18. ACCESO MÁGICO A RESELLER
if ($action == 'access_as_reseller') {
    $d = json_decode(file_get_contents('php://input'), true);
    $id = $d['id'];

    // 1. Generar un token aleatorio único
    $token = bin2hex(random_bytes(32));

    // 2. Guardarlo en la base de datos para ese usuario
    $stmt = $pdo->prepare("UPDATE distribuidores SET login_token = ? WHERE id = ?");
    $stmt->execute([$token, $id]);

    // 3. Devolver la URL mágica
    // Asumiendo que la carpeta se llama "reseller"
    $url = "../reseller/index.php?magic_token=" . $token;

    echo json_encode(['success' => true, 'url' => $url]);
    exit;
}

// B. ACTIVAR / DESACTIVAR DISTRIBUIDOR (BANEO)
if ($action == 'toggle_reseller_status') {
    $d = json_decode(file_get_contents('php://input'), true);
    // Invertimos el estado: Si es 1 pasa a 0, si es 0 pasa a 1
    $sql = "UPDATE distribuidores SET activo = NOT activo WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$d['id']])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar estado']);
    }
    exit;
}

// Estadisticas graficos
if ($action == 'get_chart_data') {
    // 1. Ventas últimos 7 días (Agrupado por fecha)
    // Usamos DATE() para ignorar la hora
    $sqlVentas = "SELECT DATE(fecha_pedido) as fecha, COUNT(*) as total 
                  FROM pedidos 
                  WHERE estado = 'aprobado' 
                  AND fecha_pedido >= DATE(NOW()) - INTERVAL 7 DAY
                  GROUP BY DATE(fecha_pedido)
                  ORDER BY fecha ASC";
    $ventas = $pdo->query($sqlVentas)->fetchAll(PDO::FETCH_ASSOC);

    // 2. Productos más vendidos (Top 5)
    $sqlTop = "SELECT nombre_producto, COUNT(*) as total
               FROM pedidos
               WHERE estado = 'aprobado'
               GROUP BY nombre_producto
               ORDER BY total DESC
               LIMIT 5";
    $top = $pdo->query($sqlTop)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ventas_semana' => $ventas, 'top_productos' => $top]);
    exit;
}

// Tasa automatica
if ($action == 'fetch_dolar_auto') {
    ob_clean();

    // 1. Consultar API Externa
    $ch = curl_init("https://ve.dolarapi.com/v1/dolares/paralelo");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Error conectando a API Dolar.']);
        exit;
    }
    curl_close($ch);

    // 2. Procesar Respuesta
    $data = json_decode($response, true);
    $extra = 5.00;

    // La API devuelve un objeto directo, buscamos "promedio"
    if (isset($data['promedio']) && $data['promedio'] > 0) {
        $tasa = $data['promedio'] + $extra;

        // 3. Guardar en BD
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_dolar'");
        if ($stmt->execute([$tasa])) {
            echo json_encode([
                'success' => true,
                'tasa' => $tasa,
                'message' => "Tasa actualizada: Bs. $tasa"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar en BD.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'La API no devolvió un precio válido.']);
    }
    exit;
}

// Finanzas distribuidores
if ($action == 'get_reseller_financials') {
    ob_clean();

    // 1. OBTENER TODOS LOS PERFILES QUE ESTÁN EN PODER DE DISTRIBUIDORES
    // Buscamos perfil por perfil para ser exactos matemáticamente
    $sql = "
        SELECT 
            p.id,
            c.plataforma,
            COALESCE(c.costo_inversion, 0) as costo_padre,
            COALESCE(cat.precio_reseller, 0) as precio_venta_catalogo,
            -- Contamos cuántos perfiles tiene la cuenta madre para dividir costos/precios
            (SELECT COUNT(id) FROM perfiles WHERE cuenta_id = c.id) as total_slots
        FROM perfiles p
        JOIN cuentas c ON p.cuenta_id = c.id
        -- Unimos con catálogo para saber a cuánto se lo vendiste
        LEFT JOIN catalogo cat ON c.plataforma = cat.nombre
        WHERE p.reseller_id IS NOT NULL
    ";

    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $montoInversion = 0; // Costo para ti
    $ingresosBrutos = 0; // Venta al distribuidor
    $totalCuentas = count($items);

    foreach ($items as $row) {
        // A. CÁLCULO DE TU INVERSIÓN (COSTO)
        // Costo de la cuenta padre / cantidad de perfiles = Costo unitario de este perfil
        $slots = intval($row['total_slots']) ?: 1; // Evitar división por cero
        $costoUnitario = floatval($row['costo_padre']) / $slots;

        $montoInversion += $costoUnitario;

        // B. CÁLCULO DE INGRESOS BRUTOS (VENTA)
        // Aquí detectamos si se vendió como "Cuenta Completa" o "Pantalla"
        $precioCatalogo = floatval($row['precio_venta_catalogo']);
        $nombrePlat = strtolower($row['plataforma']);
        $ventaUnitaria = 0;

        // Si el producto en catálogo dice "Completa" o "Cuenta", el precio es por el Lote.
        // Debemos dividirlo entre los slots para saber cuánto vale este perfil individual.
        if (strpos($nombrePlat, 'completa') !== false || strpos($nombrePlat, 'cuenta') !== false) {
            $ventaUnitaria = $precioCatalogo / $slots;
        } else {
            // Si es pantalla suelta, el precio del catálogo es directo
            $ventaUnitaria = $precioCatalogo;
        }

        $ingresosBrutos += $ventaUnitaria;
    }

    // 3. GANANCIA NETA
    $ganancia = $ingresosBrutos - $montoInversion;

    echo json_encode([
        // Enviamos los datos etiquetados como los pediste
        'costos_operativos' => number_format($montoInversion, 2), // Tu Inversión
        'ventas_plataforma' => number_format($ingresosBrutos, 2), // Ingresos Brutos (Venta a Resellers)
        'ganancia_neta' => number_format($ganancia, 2),           // Ganancia Real

        // El ingreso real de dinero (Caja) se mantiene aparte solo como referencia
        'ingresos_reales' => number_format($ingresosBrutos, 2)
    ]);
    exit;
}


if ($action == 'generate_invite_code') {
    // Generar 6 dígitos aleatorios
    $codigo = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

    // Guardar en BD
    $stmt = $pdo->prepare("INSERT INTO codigos_invitacion (codigo) VALUES (?)");

    try {
        $stmt->execute([$codigo]);
        echo json_encode(['success' => true, 'codigo' => $codigo]);
    } catch (Exception $e) {
        // Si por una remota casualidad el código se repite, intentamos de nuevo (recursividad simple)
        // O mandamos error para que le des click otra vez.
        echo json_encode(['success' => false, 'message' => 'Error generando código, intenta de nuevo.']);
    }
    exit;
}

// --- 23. RENOVAR FECHA DE PAGO A PROVEEDOR (CUENTA MAESTRA) ---
if ($action == 'update_master_date') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);

    // Solo actualizamos la fecha de pago
    $stmt = $pdo->prepare("UPDATE cuentas SET fecha_pago_proveedor = ? WHERE id = ?");

    if ($stmt->execute([$d['nueva_fecha'], $d['id']])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar DB']);
    }
    exit;
}

// Lista de precios PDF
if ($action == 'download_pricelist') {
    // 1. Limpieza agresiva del buffer para evitar corrupción del PDF
    while (ob_get_level())
        ob_end_clean();

    // 2. Detección inteligente de la librería FPDF
    // Intenta buscar en la carpeta actual o una atrás
    $fpdfPath = 'fpdf/fpdf.php';
    if (!file_exists($fpdfPath)) {
        $fpdfPath = '../fpdf/fpdf.php';
    }

    if (!file_exists($fpdfPath)) {
        die("Error Crítico: No se encuentra la librería FPDF en '$fpdfPath'. Verifica que subiste la carpeta 'fpdf'.");
    }

    require($fpdfPath);

    class PDF_Catalogo extends FPDF
    {
        function Header()
        {
            // 3. Detección inteligente del Logo
            $logoPath = 'img/logo.png';
            if (!file_exists($logoPath))
                $logoPath = '../img/logo.png';

            if (file_exists($logoPath)) {
                $this->Image($logoPath, 10, 8, 25);
            }

            // TÍTULO
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(127, 0, 255);
            $this->Cell(0, 10, utf8_decode("LISTA DE PRECIOS OFICIAL"), 0, 1, 'C');

            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(100);
            $this->Cell(0, 5, "Actualizado al: " . date('d/m/Y'), 0, 1, 'C');
            $this->Ln(10);

            // ENCABEZADOS
            $this->SetFillColor(30, 30, 30);
            $this->SetTextColor(255);
            $this->SetFont('Arial', 'B', 10);

            $this->Cell(70, 10, 'PLATAFORMA', 0, 0, 'L', true);
            $this->Cell(80, 10, 'DETALLES / PLAN', 0, 0, 'L', true);
            $this->Cell(40, 10, 'COSTO (USD)', 0, 1, 'C', true);
            $this->Ln();
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(150);
            $this->Cell(0, 10, utf8_decode('D\'Level Play Max - Partners'), 0, 0, 'C');
        }
    }

    // --- GENERACIÓN ---
    // Usamos global $pdo porque estamos dentro de una función si el include es complejo
    global $pdo;

    $pdf = new PDF_Catalogo();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    $sql = "SELECT nombre, precio_reseller, descripcion FROM catalogo WHERE precio_reseller > 0 ORDER BY nombre ASC";
    $stmt = $pdo->query($sql);

    $fill = false;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($fill) {
            $pdf->SetFillColor(245, 245, 255);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->SetTextColor(0);
        $nombre = utf8_decode($row['nombre']);
        $desc = utf8_decode(substr($row['descripcion'], 0, 55));

        $pdf->Cell(70, 8, $nombre, 0, 0, 'L', true);

        $pdf->SetTextColor(100);
        $pdf->Cell(80, 8, $desc, 0, 0, 'L', true);

        $pdf->SetTextColor(0, 150, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(40, 8, '$ ' . number_format($row['precio_reseller'], 2), 0, 1, 'C', true);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln();
        $fill = !$fill;
    }

    $pdf->Output('I', 'Lista_Precios.pdf');
    exit;
}

// OBTENER SOLICITUDES UNIFICADAS (PAGOS + ACTIVACIONES)
if ($action == 'get_pending_recharges') {
    ob_clean();

    // 1. Recargas de dinero
    $sqlPagos = "SELECT h.*, d.nombre as reseller_name, 'pago' as tipo_solicitud 
                 FROM historial_recargas h 
                 JOIN distribuidores d ON h.reseller_id = d.id 
                 WHERE h.estado = 'pendiente' ORDER BY h.fecha DESC";
    $pagos = $pdo->query($sqlPagos)->fetchAll(PDO::FETCH_ASSOC);


    // 2. Activaciones pendientes (Canva, etc.)
    // Buscamos perfiles que tengan un correo_a_activar pero estado_activacion sea pendiente
    $sqlAct = "SELECT p.id, p.correo_a_activar as referencia, c.plataforma as metodo, 
                      d.nombre as reseller_name, 'activacion' as tipo_solicitud, p.fecha_venta_reseller as fecha
               FROM perfiles p
               JOIN distribuidores d ON p.reseller_id = d.id
               JOIN cuentas c ON p.cuenta_id = c.id
               WHERE p.correo_a_activar IS NOT NULL AND p.estado_activacion = 'pendiente'";
    $activaciones = $pdo->query($sqlAct)->fetchAll(PDO::FETCH_ASSOC);

    // Unimos los dos arrays
    $resultado = array_merge($pagos, $activaciones);

    echo json_encode($resultado);
    exit;
}

// NUEVO: MARCAR ACTIVACIÓN COMO LISTA
if ($action == 'mark_activation_done') {
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE perfiles SET estado_activacion = 'completado' WHERE id = ?")->execute([$d['id']]);

    // Opcional: Notificar al reseller
    // enviarAlerta(...)
    echo json_encode(['success' => true]);
    exit;
}

// APROBAR RECARGA (SUMAR SALDO CORREGIDO)
if ($action == 'approve_recharge_request') {
    ob_clean(); // Limpieza importante
    $d = json_decode(file_get_contents('php://input'), true);
    $reqId = $d['id'];

    // Tomamos el monto que enviaste desde el admin (si existe), si no, el de la BD
    $montoAprobado = isset($d['monto_real']) ? floatval($d['monto_real']) : null;

    try {
        $pdo->beginTransaction();

        // 1. Obtener datos de la solicitud
        $req = $pdo->query("SELECT * FROM historial_recargas WHERE id = $reqId")->fetch();

        if (!$req)
            throw new Exception("Solicitud no encontrada");
        if ($req['estado'] != 'pendiente')
            throw new Exception("Esta solicitud ya fue procesada");

        // Si no enviaste monto manual, usamos el de la solicitud
        if ($montoAprobado === null) {
            $montoAprobado = floatval($req['monto']);
        }

        // 2. Sumar saldo al distribuidor (USANDO EL MONTO VERIFICADO)
        $pdo->prepare("UPDATE distribuidores SET saldo = saldo + ? WHERE id = ?")
            ->execute([$montoAprobado, $req['reseller_id']]);

        // 3. Actualizar la solicitud (Marcamos aprobada y actualizamos el monto por si venía en 0)
        $pdo->prepare("UPDATE historial_recargas SET estado = 'aprobado', monto = ? WHERE id = ?")
            ->execute([$montoAprobado, $reqId]);

        // 4. Registrar movimiento en historial financiero del reseller
        $pdo->prepare("INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion) VALUES (?, 'deposito', ?, ?)")
            ->execute([$req['reseller_id'], $montoAprobado, "Recarga Aprobada #$reqId ({$req['metodo']})"]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// RECHAZAR RECARGA
if ($action == 'reject_recharge_request') {
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE historial_recargas SET estado = 'rechazado' WHERE id = ?")->execute([$d['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// --- GESTIÓN DE MICUENTA.ME ---

// 1. LISTAR TOKENS GUARDADOS
if ($action == 'list_micuenta_tokens') {
    echo json_encode($pdo->query("SELECT * FROM micuenta_tokens ORDER BY id DESC")->fetchAll());
    exit;
}

// 2. GUARDAR NUEVO TOKEN
if ($action == 'save_micuenta_token') {
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("INSERT INTO micuenta_tokens (alias, code, pdv) VALUES (?,?,?)")
        ->execute([$d['alias'], $d['code'], $d['pdv']]);
    echo json_encode(['success' => true]);
    exit;
}

// 3. BORRAR TOKEN
if ($action == 'delete_micuenta_token') {
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("DELETE FROM micuenta_tokens WHERE id = ?")->execute([$d['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// 4. EJECUTAR EXTRACCIÓN (La lógica que ya probaste)
if ($action == 'extract_micuenta') {
    ob_clean();
    $id = $_GET['id'];

    // Buscamos los datos en la BD
    $stmt = $pdo->prepare("SELECT code, pdv FROM micuenta_tokens WHERE id = ?");
    $stmt->execute([$id]);
    $token = $stmt->fetch();

    if (!$token) {
        echo json_encode(['error' => 'Token no encontrado']);
        exit;
    }

    $url_api = 'https://micuenta.me/e/redeem';
    $data_json = json_encode(['code' => $token['code'], 'pdv' => $token['pdv']]);

    $ch = curl_init($url_api);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Referer: https://micuenta.me/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]);

    $respuesta = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['error' => 'Error Curl: ' . curl_error($ch)]);
    } else {
        echo $respuesta; // Devolvemos el JSON puro del proveedor
    }
    curl_close($ch);
    exit;
}

if ($action == 'extract_credentials_from_master') {
    ob_clean();
    $id = $_GET['id'];

    // 1. Buscamos el token en la tabla CUENTAS
    $stmt = $pdo->prepare("SELECT token_micuenta FROM cuentas WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row || empty($row['token_micuenta'])) {
        echo json_encode(['error' => 'Esta cuenta no tiene Token configurado.']);
        exit;
    }

    // El formato guardado debe ser: CODE/PDV (Ej: 1788890/fxnet11nov)
    $parts = explode('/', $row['token_micuenta']);
    if (count($parts) < 2) {
        echo json_encode(['error' => 'Formato de token inválido. Debe ser CODE/PDV']);
        exit;
    }

    $code = trim($parts[0]);
    $pdv = trim($parts[1]);
    $url_api = 'https://micuenta.me/e/redeem';

    // 2. Ejecutar petición al proveedor
    $ch = curl_init($url_api);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['code' => $code, 'pdv' => $pdv]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Referer: https://micuenta.me/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]);

    $respuesta = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['error' => 'Error conexión: ' . curl_error($ch)]);
    } else {
        echo $respuesta; // Devuelve el JSON del proveedor directo al JS
    }
    curl_close($ch);
    exit;
}

// ACTUALIZAR DESCUENTO DE UN DISTRIBUIDOR
if ($action == 'update_reseller_discount_percent') {
    $d = json_decode(file_get_contents('php://input'), true);

    // Validar que sea lógico (entre 0 y 100%)
    if (!is_numeric($d['porcentaje']) || $d['porcentaje'] < 0 || $d['porcentaje'] > 100) {
        echo json_encode(['success' => false, 'message' => 'Porcentaje inválido']);
        exit;
    }

    $pdo->prepare("UPDATE distribuidores SET descuento_personal = ? WHERE id = ?")
        ->execute([$d['porcentaje'], $d['id']]);

    echo json_encode(['success' => true]);
    exit;
}

// Reportes y recargas

/**
 * Obtener todos los reportes de soporte de resellers
 * Tabla: reportes_fallos
 */
if ($action == 'get_all_reports') {
    ob_clean();

    try {
        $stmt = $pdo->query("
            SELECT 
                r.id,
                'Reporte de Fallo' as asunto, -- Campo asunto no existe, usamos texto fijo o derivado
                r.mensaje,
                r.fecha,
                r.estado,
                r.reseller_id,
                r.evidencia_img,
                r.perfil_id,
                d.nombre as reseller_nombre,
                p.nombre_perfil,
                c.plataforma
            FROM reportes_fallos r
            LEFT JOIN distribuidores d ON r.reseller_id = d.id
            LEFT JOIN perfiles p ON r.perfil_id = p.id
            LEFT JOIN cuentas c ON p.cuenta_id = c.id
            ORDER BY 
                CASE r.estado 
                    WHEN 'pendiente' THEN 1
                    WHEN 'solucionado' THEN 3
                    ELSE 2
                END,
                r.fecha DESC
        ");

        $reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalizar datos para el frontend
        $data = array_map(function ($r) {
            // Ajustar ruta de imagen: bajar un nivel si no empieza con ../
            $evidencia = $r['evidencia_img'];
            if ($evidencia && strpos($evidencia, '../') !== 0) {
                $evidencia = '../' . $evidencia;
            }

            return [
                'id' => $r['id'],
                'asunto' => $r['plataforma'] ? "Fallo en {$r['plataforma']} ({$r['nombre_perfil']})" : "Reporte General",
                'mensaje' => $r['mensaje'],
                'fecha' => $r['fecha'],
                // Mapear estado exacto de la BD al frontend
                'estado' => $r['estado'] === 'solucionado' ? 'resuelto' : ($r['estado'] === 'pendiente' ? 'pendiente' : 'en_proceso'),
                'reseller_id' => $r['reseller_id'],
                'reseller_nombre' => $r['reseller_nombre'],
                'evidencia' => $evidencia
            ];
        }, $reportes);

        echo json_encode(['success' => true, 'data' => $data]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al cargar reportes: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Obtener solicitudes de recarga de saldo
 * Tabla: historial_recargas
 */
if ($action == 'get_recharge_requests') {
    ob_clean();

    try {
        $stmt = $pdo->query("
            SELECT 
                hr.id,
                hr.reseller_id,
                hr.monto,
                hr.metodo,
                hr.referencia,
                hr.comprobante_img,
                hr.nota,
                hr.fecha,
                hr.estado,
                hr.detalles_pago,
                d.nombre as reseller_nombre
            FROM historial_recargas hr
            LEFT JOIN distribuidores d ON hr.reseller_id = d.id
            WHERE hr.estado IN ('pendiente', 'aprobado', 'rechazado') -- Traemos todos para historial
            ORDER BY 
                CASE hr.estado 
                    WHEN 'pendiente' THEN 1
                    WHEN 'aprobado' THEN 2
                    WHEN 'rechazado' THEN 3
                END,
                hr.fecha DESC
        ");

        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalizar datos para frontend
        $data = array_map(function ($r) {
            return [
                'id' => $r['id'],
                'reseller_id' => $r['reseller_id'],
                'reseller_nombre' => $r['reseller_nombre'],
                'monto' => $r['monto'],
                'metodo_pago' => $r['metodo'] ?: 'N/A',
                'referencia' => $r['referencia'],
                'comprobante' => $r['comprobante_img'],
                'nota' => $r['nota'],
                'fecha' => $r['fecha'],
                'estado' => $r['estado'] == 'pendiente' ? 'pendiente' : ($r['estado'] == 'aprobado' ? 'aprobado' : 'rechazado')
            ];
        }, $solicitudes);

        echo json_encode(['success' => true, 'data' => $data]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al cargar solicitudes: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Aprobar una solicitud de recarga
 */
if ($action == 'approve_recharge') {
    ob_clean();

    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($data['request_id'] ?? 0);
    $reseller_id = intval($data['reseller_id'] ?? 0);
    $monto = floatval($data['monto'] ?? 0);

    if (!$request_id || !$reseller_id || $monto <= 0) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Verificar estado actual para no aprobar doble
        $stmtCheck = $pdo->prepare("SELECT estado FROM historial_recargas WHERE id = ?");
        $stmtCheck->execute([$request_id]);
        $current = $stmtCheck->fetch();

        if ($current && $current['estado'] == 'aprobado') {
            throw new Exception("Esta solicitud ya fue aprobada anteriormente");
        }

        // 2. Actualizar estado de la solicitud en historial_recargas
        $stmt = $pdo->prepare("UPDATE historial_recargas SET estado = 'aprobado' WHERE id = ?");
        $stmt->execute([$request_id]);

        // 3. Acreditar saldo al reseller
        $stmt = $pdo->prepare("UPDATE distribuidores SET saldo = saldo + ? WHERE id = ?");
        $stmt->execute([$monto, $reseller_id]);

        // 4. Registrar movimiento (esto ya lo estábamos haciendo bien)
        $stmt = $pdo->prepare("
            INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion, fecha)
            VALUES (?, 'deposito', ?, ?, NOW())
        ");
        $stmt->execute([$reseller_id, $monto, "Recarga Aprobada #$request_id"]);

        $pdo->commit();

        // Notificación push
        enviarNotificacionPush("💰 Recarga aprobada: $" . number_format($monto, 2), "Recarga Exitosa");

        echo json_encode(['success' => true, 'message' => 'Recarga aprobada correctamente']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Rechazar una solicitud de recarga
 */
if ($action == 'reject_recharge') {
    ob_clean();

    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($data['request_id'] ?? 0);
    $motivo = $data['motivo'] ?? '';

    if (!$request_id) {
        echo json_encode(['success' => false, 'message' => 'ID de solicitud inválido']);
        exit;
    }

    try {
        // En historial_recargas a veces usamos la nota para guardar el motivo
        // O simplemente cambiamos el estado
        $stmt = $pdo->prepare("
            UPDATE historial_recargas 
            SET estado = 'rechazado', nota = CONCAT(nota, ' [Rechazado: ', ?, ']')
            WHERE id = ?
        ");
        $stmt->execute([$motivo, $request_id]);

        echo json_encode(['success' => true, 'message' => 'Solicitud rechazada']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al rechazar: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Resolver un reporte de soporte
 */
if ($action == 'resolve_report') {
    ob_clean();

    $data = json_decode(file_get_contents('php://input'), true);
    $report_id = intval($data['report_id'] ?? 0);
    // En reportes_fallos no hay campo 'nota_resolucion', así que solo cambiamos estado
    // Opcionalmente podríamos agregarlo a 'mensaje'

    if (!$report_id) {
        echo json_encode(['success' => false, 'message' => 'ID de reporte inválido']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE reportes_fallos 
            SET estado = 'solucionado' 
            WHERE id = ?
        ");
        $stmt->execute([$report_id]);

        echo json_encode(['success' => true, 'message' => 'Reporte marcado como solucionado']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Obtener detalles de un reporte específico
 */
if ($action == 'get_report_details') {
    ob_clean();

    $id = intval($_GET['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                r.mensaje,
                r.fecha,
                r.estado,
                r.evidencia_img,
                r.reseller_id,
                d.nombre as reseller_nombre,
                d.email as reseller_email,
                d.telefono as reseller_telefono,
                p.nombre_perfil,
                p.pin_perfil,
                c.plataforma,
                c.email_cuenta,
                c.password as password_cuenta
            FROM reportes_fallos r
            LEFT JOIN distribuidores d ON r.reseller_id = d.id
            LEFT JOIN perfiles p ON r.perfil_id = p.id
            LEFT JOIN cuentas c ON p.cuenta_id = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($r) {
            // Normalizar
            $reporte = [
                'id' => $r['id'],
                'asunto' => $r['plataforma'] ? "Fallo en {$r['plataforma']} ({$r['nombre_perfil']})" : "Reporte General",
                'mensaje' => $r['mensaje'],
                'fecha' => $r['fecha'],
                'estado' => $r['estado'] == 'solucionado' ? 'resuelto' : 'pendiente',
                'reseller_nombre' => $r['reseller_nombre'],
                'reseller_email' => $r['reseller_email'],
                'reseller_telefono' => $r['reseller_telefono'],
                'nota_resolucion' => null, // No soportado en tabla actual
                // Datos extra útiles para soporte
                'cuenta_afectada' => [
                    'plataforma' => $r['plataforma'],
                    'email' => $r['email_cuenta'],
                    'password' => $r['password_cuenta'],
                    'perfil' => $r['nombre_perfil'],
                    'pin' => $r['pin_perfil']
                ],
                'evidencia' => $r['evidencia_img']
            ];
            echo json_encode($reporte);
        } else {
            echo json_encode(['success' => false, 'message' => 'Reporte no encontrado']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

?>