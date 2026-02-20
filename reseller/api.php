<?php
// 1. LIMPIEZA DE BUFFER
ob_start();

// Cargar variables de entorno
if (!defined('DB_HOST')) {
    $envFile = __DIR__ . '/../.env';
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

session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// 2. CONEXIÓN SEGURA
try {
    if (!file_exists('db.php'))
        throw new Exception("Falta archivo db.php");
    require 'db.php';
    $pdo->exec("set names utf8mb4");
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

define('ONESIGNAL_APP_ID', $_ENV['ONESIGNAL_APP_ID'] ?? '');
define('ONESIGNAL_API_KEY', $_ENV['ONESIGNAL_API_KEY'] ?? '');


function logResellerActivity($resellerId, $accion, $desc)
{
    global $pdo;
    try {
        // Crear tabla si no existe (Lazy Init)
        $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reseller_id INT NOT NULL,
            accion VARCHAR(50),
            descripcion TEXT,
            ip VARCHAR(45),
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (reseller_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $pdo->prepare("INSERT INTO reseller_logs (reseller_id, accion, descripcion, ip) VALUES (?, ?, ?, ?)");
        $stmt->execute([$resellerId, $accion, $desc, $ip]);
    } catch (Exception $e) {
        // Fallo silencioso para no detener el flujo principal
        error_log("Error guardando log: " . $e->getMessage());
    }
}

function validateImageUpload($fileArr)
{
    if (!isset($fileArr['tmp_name']) || $fileArr['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error en la subida del archivo. Código: " . $fileArr['error']);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fileArr['tmp_name']);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf'
    ];

    if (!array_key_exists($mime, $allowedMimes)) {
        throw new Exception("Formato de archivo no seguro ($mime). Solo JPG, PNG, WEBP o PDF.");
    }

    return $allowedMimes[$mime]; // Retorna la extensión segura
}

function notificarAdmin($titulo, $mensaje)
{
    // Definimos un prefijo para identificar fácil los logs
    $logPrefix = '[NOTIFICAR_ADMIN] ';

    // 1. Validar constantes
    if (!defined('ONESIGNAL_APP_ID') || !defined('ONESIGNAL_API_KEY')) {
        error_log($logPrefix . "Error: Las constantes ONESIGNAL_APP_ID o ONESIGNAL_API_KEY no están definidas.");
        return;
    }

    global $pdo;

    // 2. Validar conexión PDO
    if (!$pdo) {
        error_log($logPrefix . "Error: La variable global \$pdo es nula o no existe.");
        return;
    }

    try {
        // 3. Obtener IDs
        $stmt = $pdo->query("SELECT onesignal_id FROM admin_users WHERE onesignal_id IS NOT NULL AND onesignal_id != ''");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Loguear cuántos IDs se encontraron
        if (empty($ids)) {
            error_log($logPrefix . "Advertencia: No se encontraron IDs de administradores en la tabla admin_users.");
            return;
        } else {
            error_log($logPrefix . "Info: Se enviará notificación a " . count($ids) . " administradores.");
        }

        $content = ["en" => $mensaje];
        $headings = ["en" => $titulo];

        $fields = [
            'app_id' => ONESIGNAL_APP_ID,
            'include_player_ids' => $ids,
            'contents' => $content,
            'headings' => $headings,
            'small_icon' => 'ic_stat_onesignal_default',
            // 'android_channel_id' => 'tu_canal_importante_id' // Descomentar si usas canales
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . ONESIGNAL_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        // 4. Analizar respuesta de cURL
        if ($response === false) {
            error_log($logPrefix . "Error Fatal cURL: " . $curlError);
        } else {
            // Loguear la respuesta de OneSignal para depuración
            // Si el código no es 200, es un error seguro.
            if ($httpCode !== 200) {
                error_log($logPrefix . "Error API OneSignal (HTTP $httpCode): " . $response);
            } else {
                // A veces OneSignal responde 200 pero con errores en el JSON (ej: "All included players are not subscribed")
                $jsonResp = json_decode($response, true);
                if (isset($jsonResp['errors'])) {
                    error_log($logPrefix . "Error Lógico OneSignal: " . json_encode($jsonResp['errors']));
                } else {
                    // Éxito total (puedes comentar esta línea si genera mucho ruido)
                    error_log($logPrefix . "Éxito: Notificación enviada. ID OneSignal: " . ($jsonResp['id'] ?? 'N/A'));
                }
            }
        }

    } catch (Exception $e) {
        // 5. Capturar Excepción real
        error_log($logPrefix . "Excepción PHP: " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine());
    }
}

// Login publico

if ($action == 'login_demo') {
    ob_clean();
    $stmt = $pdo->prepare("SELECT * FROM distribuidores WHERE email = 'demo@levelplaymax.com'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['reseller_id'] = $user['id'];
        $_SESSION['reseller_name'] = $user['nombre'];
        $_SESSION['is_demo'] = true; // MARCA DE SEGURIDAD

        // Restaurar saldo visualmente a 5000 cada vez que entra (Opcional)
        $pdo->query("UPDATE distribuidores SET saldo = 5000 WHERE id = {$user['id']}");

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuario demo no encontrado en BD']);
    }
    exit;
}

if ($action == 'login') {
    ob_clean();
    $input = file_get_contents('php://input');
    $d = json_decode($input, true);

    if (!$d) {
        echo json_encode(['success' => false, 'message' => 'Datos vacíos']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM distribuidores WHERE email = ? AND activo = 1");
    $stmt->execute([$d['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($d['pass'], $user['password'])) {
        $_SESSION['reseller_id'] = $user['id'];
        $_SESSION['reseller_name'] = $user['nombre'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
    }
    exit;
}

if ($action == 'login_with_token') {
    $d = json_decode(file_get_contents('php://input'), true);
    $token = $d['token'];

    if (!$token) {
        echo json_encode(['success' => false]);
        exit;
    }

    // Buscar usuario con ese token
    $stmt = $pdo->prepare("SELECT * FROM distribuidores WHERE login_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // Iniciar sesión
        $_SESSION['reseller_id'] = $user['id'];
        $_SESSION['reseller_name'] = $user['nombre'];

        // BORRAR EL TOKEN (Para que no se pueda reusar)
        $pdo->prepare("UPDATE distribuidores SET login_token = NULL WHERE id = ?")->execute([$user['id']]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Token inválido o expirado']);
    }
    exit;
}


// Registro

if ($action == 'validate_invite_code') {
    $d = json_decode(file_get_contents('php://input'), true);

    // Buscar código activo y no usado
    $stmt = $pdo->prepare("SELECT id FROM codigos_invitacion WHERE codigo = ? AND estado = 'activo'");
    $stmt->execute([$d['codigo']]);

    if ($stmt->fetch()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Código no existe o ya fue usado.']);
    }
    exit;
}

if ($action == 'register_reseller_self') {
    $d = json_decode(file_get_contents('php://input'), true);

    try {
        $pdo->beginTransaction();

        // A. Verificar código DE NUEVO por seguridad (Evitar duplicados simultáneos)
        $stmtCode = $pdo->prepare("SELECT id FROM codigos_invitacion WHERE codigo = ? AND estado = 'activo' FOR UPDATE");
        $stmtCode->execute([$d['codigo']]);
        if (!$stmtCode->fetch())
            throw new Exception("Código de invitación inválido o expirado.");

        // B. Verificar correo
        $stmtMail = $pdo->prepare("SELECT id FROM distribuidores WHERE email = ?");
        $stmtMail->execute([$d['email']]);
        if ($stmtMail->fetch())
            throw new Exception("El correo ya está registrado.");

        // C. Crear Usuario
        $passHash = password_hash($d['pass'], PASSWORD_BCRYPT);
        $sqlInsert = "INSERT INTO distribuidores (nombre, email, password, telefono, cedula, ventas_estimadas, saldo) VALUES (?, ?, ?, ?, ?, ?, 0.00)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([$d['nombre'], $d['email'], $passHash, $d['telefono'], $d['cedula'], $d['ventas']]);

        // D. Quemar Código
        $pdo->prepare("UPDATE codigos_invitacion SET estado = 'usado', usado_por_email = ? WHERE codigo = ?")
            ->execute([$d['email'], $d['codigo']]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Seguridad de sesion
if (!isset($_SESSION['reseller_id'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}
// 🔥 AGREGAR ESTA LÍNEA MÁGICA AQUÍ 🔥
session_write_close();
$myId = $_SESSION['reseller_id'];

// Modo demo
// Si es el usuario demo, prohibimos acciones de escritura
if (isset($_SESSION['is_demo']) && $_SESSION['is_demo'] === true) {
    // Lista negra de acciones
    $prohibidas = [
        'buy_product',
        'add_my_client',
        'edit_my_client',
        'assign_client',
        'renew_my_product',
        'update_profile_details',
        'toggle_auto_renew',
        'update_my_profile',
        'report_issue',
        'send_feedback',
        'return_profile_to_stock',
        'save_my_price',
        'update_goal'
    ];

    if (in_array($action, $prohibidas)) {
        ob_clean();
        // Simulamos un error controlado
        echo json_encode([
            'success' => false,
            'message' => '🚫 MODO DEMO: Esta acción es solo de prueba. Regístrate para realizar compras y cambios reales.'
        ]);
        exit;
    }
}


// Funciones del panel

try {
    // 1. DASHBOARD MEJORADO (CON MÉTRICAS DE NEGOCIO)
    if ($action == 'get_dashboard') {
        ob_clean();

        // A. Datos Básicos
        $stmt = $pdo->prepare("SELECT saldo, nombre FROM distribuidores WHERE id = ?");
        $stmt->execute([$myId]);
        $res = $stmt->fetch();
        $saldo = floatval($res['saldo']);
        $nombre = $res['nombre'];

        // B. Métricas de Inventario
        // Cuentas Activas
        $activas = $pdo->query("SELECT COUNT(*) FROM perfiles WHERE reseller_id = $myId")->fetchColumn();

        // Cuentas por Vencer (Próximos 3 días) - ¡DATO CLAVE PARA COBRAR!
        $sqlPorVencer = "SELECT COUNT(*) FROM perfiles 
                     WHERE reseller_id = $myId 
                     AND DATEDIFF(COALESCE(fecha_corte_cliente, DATE_ADD(fecha_venta_reseller, INTERVAL 30 DAY)), NOW()) BETWEEN 0 AND 3";
        $porVencer = $pdo->query($sqlPorVencer)->fetchColumn();

        // C. Métricas de Ventas (Hoy)
        $hoy = date('Y-m-d');
        $sqlVentasHoy = "SELECT COUNT(*) FROM movimientos_reseller WHERE reseller_id = $myId AND tipo = 'compra' AND DATE(fecha) = '$hoy'";
        $ventasHoy = $pdo->query($sqlVentasHoy)->fetchColumn();

        // D. Tasa del Dólar (Configuración)
        $stmtTasa = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'");
        $tasa = floatval($stmtTasa->fetchColumn()) ?: 0.00;

        echo json_encode([
            'nombre' => $nombre,
            'saldo' => $saldo,
            'activas' => $activas,
            'por_vencer' => $porVencer,
            'ventas_hoy' => $ventasHoy,
            'tasa' => $tasa
        ]);
        exit;
    }
    // 2. STOCK (CATÁLOGO COMPLETO + DISPONIBILIDAD + DESCUENTOS PERSONALIZADOS)
    if ($action == 'get_stock') {
        ob_clean();

        try {
            // A. OBTENER EL DESCUENTO DEL USUARIO ACTUAL
            // Buscamos si este distribuidor tiene un porcentaje especial (Ej: 10.00)
            $stmtUser = $pdo->prepare("SELECT descuento_personal FROM distribuidores WHERE id = ?");
            $stmtUser->execute([$myId]);
            $userDesc = floatval($stmtUser->fetchColumn()) ?: 0;

            // B. CONSULTA DE STOCK (La misma lógica robusta de antes)
            $sql = "
            SELECT 
                cat.nombre as plataforma, 
                cat.precio_reseller, 
                cat.tipo_entrega,
                
                -- CONTAR INVENTARIO DISPONIBLE
                CASE 
                    WHEN cat.tipo_entrega = 'manual' THEN 999 -- Virtualmente ilimitado
                    ELSE
                        COUNT(DISTINCT 
                            CASE 
                                -- Solo contamos si el perfil existe Y está libre
                                WHEN p.id IS NOT NULL AND p.cliente_id IS NULL AND p.reseller_id IS NULL THEN
                                    CASE 
                                        -- Lógica de Cuenta Completa vs Pantalla
                                        WHEN cat.nombre LIKE '%Completa%' OR cat.nombre LIKE '%Cuenta%' 
                                        THEN c.id 
                                        ELSE p.id 
                                    END
                                ELSE NULL 
                            END
                        )
                END as disponibles

            FROM catalogo cat
            -- Unimos con cuentas para ver si hay stock (Cruce flexible de nombres)
            LEFT JOIN cuentas c ON (c.plataforma = cat.nombre OR c.plataforma LIKE CONCAT('%', cat.nombre, '%'))
            LEFT JOIN perfiles p ON c.id = p.cuenta_id
            
            -- SOLO MOSTRAR SI TIENE PRECIO DE RESELLER CONFIGURADO
            WHERE cat.precio_reseller > 0
            
            GROUP BY cat.nombre
            ORDER BY disponibles DESC, cat.nombre ASC
            ";

            $productos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // C. PROCESAR PRECIOS (APLICAR DESCUENTO)
            foreach ($productos as &$prod) {
                $precioBase = floatval($prod['precio_reseller']);

                // Si el usuario tiene descuento mayor a 0%
                if ($userDesc > 0) {
                    $descuentoDinero = $precioBase * ($userDesc / 100);
                    $nuevoPrecio = $precioBase - $descuentoDinero;

                    // Enviamos datos extra para que el JS pinte el precio tachado
                    $prod['precio_original'] = number_format($precioBase, 2); // Precio Tachado ($5.00)
                    $prod['precio_reseller'] = number_format($nuevoPrecio, 2); // Precio Final ($4.50)
                    $prod['tiene_descuento'] = true;
                    $prod['porcentaje_off'] = $userDesc; // Para mostrar la etiqueta "-10%"
                } else {
                    // Sin descuento, todo normal
                    $prod['tiene_descuento'] = false;
                    $prod['precio_reseller'] = number_format($precioBase, 2);
                }
            }

            echo json_encode($productos);

        } catch (Exception $e) {
            echo json_encode([]);
        }
        exit;
    }

    // 4. MIS COMPRAS (INVENTARIO) - ACTUALIZADO Y SEGURO
    if ($action == 'my_inventory') {
        ob_clean();
        // Verifica si la columna existe antes de intentar seleccionarla
        $colExists = $pdo->query("SHOW COLUMNS FROM cuentas LIKE 'password_plain'")->rowCount() > 0;

        $colSelect = $colExists ? "c.password_plain" : "NULL as password_plain";

        $sql = "SELECT p.id, p.cuenta_id, p.nombre_perfil, p.pin_perfil, p.fecha_venta_reseller,
                COALESCE(p.fecha_corte_cliente, DATE_ADD(p.fecha_venta_reseller, INTERVAL 30 DAY)) as fecha_vencimiento,
                c.plataforma, c.email_cuenta, p.auto_renovacion, c.token_micuenta, $colSelect,
                cr.nombre as cliente_final, cr.id as cliente_final_id
                FROM perfiles p
                JOIN cuentas c ON p.cuenta_id = c.id
                LEFT JOIN clientes_reseller cr ON p.cliente_reseller_id = cr.id
                WHERE p.reseller_id = ?
                ORDER BY p.fecha_venta_reseller DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$myId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // 4.5 OBTENER CREDENCIAL SEGURA (ON DEMAND)
    if ($action == 'get_credential_secure') {
        ob_clean();
        $id = filter_var($_GET['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        // Verificar propiedad rigurosamente
        $stmt = $pdo->prepare("SELECT c.password FROM perfiles p JOIN cuentas c ON p.cuenta_id = c.id WHERE p.id = ? AND p.reseller_id = ?");
        $stmt->execute([$id, $myId]);
        $pass = $stmt->fetchColumn();

        if ($pass) {
            echo json_encode(['success' => true, 'password' => $pass]);
            logResellerActivity($myId, 'VIEW_PASS', "Visualizó contraseña de perfil #$id");
        } else {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado o no encontrado']);
        }
        exit;
    }

    // 4.6 OBTENER HISTORIAL DE ACTIVIDAD
    if ($action == 'get_activity_log') {
        ob_clean();
        $stmt = $pdo->prepare("SELECT accion, descripcion, fecha FROM reseller_logs WHERE reseller_id = ? ORDER BY fecha DESC LIMIT 50");
        $stmt->execute([$myId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // 5. MIS CLIENTES (MEJORADO: CON CONTEO DE SERVICIOS)
    if ($action == 'list_my_clients') {
        ob_clean();
        // Consulta inteligente: Trae datos del cliente Y cuenta cuántos perfiles tiene asignados
        $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM perfiles p WHERE p.cliente_reseller_id = c.id) as servicios_activos
            FROM clientes_reseller c 
            WHERE c.reseller_id = ? 
            ORDER BY c.nombre ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$myId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // NUEVO: ELIMINAR CLIENTE
    if ($action == 'delete_client') {
        ob_clean();
        $d = json_decode(file_get_contents('php://input'), true);
        $clientId = $d['id'];

        try {
            $pdo->beginTransaction();

            // 1. Desvincular perfiles (No borramos la cuenta, solo le quitamos el cliente)
            $pdo->prepare("UPDATE perfiles SET cliente_reseller_id = NULL WHERE cliente_reseller_id = ? AND reseller_id = ?")
                ->execute([$clientId, $myId]);

            // 2. Borrar cliente
            $stmt = $pdo->prepare("DELETE FROM clientes_reseller WHERE id = ? AND reseller_id = ?");
            $stmt->execute([$clientId, $myId]);

            $pdo->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'add_my_client') {
        ob_clean();
        $d = json_decode(file_get_contents('php://input'), true);
        // Asegurarse de recibir la nota
        $nota = $d['nota'] ?? '';
        $pdo->prepare("INSERT INTO clientes_reseller (reseller_id, nombre, telefono, nota_interna) VALUES (?,?,?,?)")->execute([$myId, $d['nombre'], $d['telefono'], $nota]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action == 'edit_my_client') {
        ob_clean();
        $d = json_decode(file_get_contents('php://input'), true);
        $nota = $d['nota'] ?? ''; // Recibir nota
        $pdo->prepare("UPDATE clientes_reseller SET nombre = ?, telefono = ?, nota_interna = ? WHERE id = ? AND reseller_id = ?")->execute([$d['nombre'], $d['telefono'], $nota, $d['id'], $myId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 6. ASIGNAR
    // 9. ASIGNAR A CLIENTE FINAL (CORREGIDO)
    if ($action == 'assign_client') {
        ob_clean();
        $d = json_decode(file_get_contents('php://input'), true);

        $perfilId = $d['perfil_id'];
        $clientId = $d['client_id'];

        // CORRECCIÓN CRÍTICA: Convertir vacíos a NULL para SQL
        if ($clientId === '' || $clientId === '0' || $clientId === null) {
            $clientId = NULL;
        }

        try {
            $pdo->beginTransaction();

            // Asegurarnos que el perfil pertenece al reseller antes de tocarlo
            $stmt = $pdo->prepare("UPDATE perfiles SET cliente_reseller_id = ? WHERE id = ? AND reseller_id = ?");
            $res = $stmt->execute([$clientId, $perfilId, $myId]);

            $pdo->commit();

            if ($res) {
                logResellerActivity($myId, 'EDIT_PERFIL', "Editó perfil #$perfilId (PIN/Fecha)");
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se pudo guardar el cambio.']);
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // 7. RENOVAR
    if ($action == 'renew_my_product') {
        ob_clean();
        $d = json_decode(file_get_contents('php://input'), true);
        $perfilId = $d['id'];

        $pdo->beginTransaction();
        $sql = "SELECT p.id, p.fecha_venta_reseller, c.plataforma, cat.precio_reseller FROM perfiles p JOIN cuentas c ON p.cuenta_id = c.id JOIN catalogo cat ON c.plataforma = cat.nombre WHERE p.id = ? AND p.reseller_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$perfilId, $myId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item)
            throw new Exception("Cuenta no encontrada.");
        $saldo = $pdo->query("SELECT saldo FROM distribuidores WHERE id = $myId")->fetchColumn();
        if ($saldo < $item['precio_reseller'])
            throw new Exception("Saldo insuficiente.");

        $hoy = date('Y-m-d H:i:s');
        $vence = date('Y-m-d H:i:s', strtotime($item['fecha_venta_reseller'] . ' + 30 days'));
        $nueva = ($vence < $hoy) ? $hoy : $vence;
        // Recalcular fecha de corte para que no quede arrastrando un valor vencido
        $nuevaCorte = date('Y-m-d', strtotime($nueva . ' + 30 days'));

        // ... después de descontar saldo ...
        $pdo->prepare("UPDATE distribuidores SET saldo = saldo - ? WHERE id = ?")->execute([$item['precio_reseller'], $myId]);
        // REGISTRAR MOVIMIENTO
        $pdo->prepare("INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion) VALUES (?, 'renovacion', ?, ?)")->execute([$myId, $item['precio_reseller'], "Renovación: " . $item['plataforma']]);
        logResellerActivity($myId, 'RENOVACION', "Renovó {$item['plataforma']} (Perfil #$perfilId)");
        // Actualizar fecha de venta y fecha de corte para evitar que sigan marcadas como vencidas
        $pdo->prepare("UPDATE perfiles SET fecha_venta_reseller = ?, fecha_corte_cliente = ? WHERE id = ?")->execute([$nueva, $nuevaCorte, $perfilId]);

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // Reportes
    if ($action == 'get_my_reports') {
        ob_clean(); // Limpiar cualquier error previo
        header('Content-Type: application/json; charset=utf-8');

        try {
            $sql = "SELECT 
                        r.id, 
                        r.mensaje, 
                        r.estado, 
                        r.fecha,
                        COALESCE(c.plataforma, 'Cuenta Eliminada') as plataforma, 
                        COALESCE(c.email_cuenta, '--') as email_cuenta 
                    FROM reportes_fallos r 
                    LEFT JOIN perfiles p ON r.perfil_id = p.id 
                    LEFT JOIN cuentas c ON p.cuenta_id = c.id 
                    WHERE r.reseller_id = ? 
                    ORDER BY r.fecha DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$myId]);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Limpieza de Caracteres Especiales (Vital para evitar JSON Error)
            $cleanData = array_map(function ($row) {
                return array_map(function ($val) {
                    return mb_convert_encoding($val ?? '', 'UTF-8', 'UTF-8');
                }, $row);
            }, $resultados);

            echo json_encode($cleanData);

        } catch (Exception $e) {
            // En caso de error fatal, devolvemos array vacío para que el panel no se congele
            echo json_encode([]);
        }
        exit;
    }

    // Reportar fallo
    if ($action == 'report_issue') {
        ob_clean(); // Limpiar basura previa
        header('Content-Type: application/json');

        try {
            // 1. OBTENER DATOS (Prioridad POST para archivos)
            $perfilId = $_POST['perfil_id'] ?? null;
            $mensaje = $_POST['mensaje'] ?? null;

            // Fallback JSON (Solo si no hay imagen y POST falló)
            if (empty($perfilId)) {
                $input = json_decode(file_get_contents('php://input'), true);
                $perfilId = $input['perfil_id'] ?? null;
                $mensaje = $input['mensaje'] ?? null;
            }

            // 2. VALIDACIÓN
            if (empty($perfilId) || empty($mensaje)) {
                throw new Exception("Datos vacíos. Si subiste una imagen, verifica que no pese más de 5MB.");
            }

            // 3. PROCESAR IMAGEN (Ruta Absoluta)
            $imgPath = null;

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {

                // Chequear errores nativos
                if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Error de subida PHP: " . $_FILES['imagen']['error']);
                }

                // Validar extensión
                $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (!in_array($ext, $allowed)) {
                    throw new Exception("Formato de imagen no permitido ($ext).");
                }

                // Definir ruta
                // __DIR__ es reseller, subimos a la raiz y entramos a uploads
                $baseDir = __DIR__ . '/../uploads/reportes/';

                if (!is_dir($baseDir)) {
                    if (!mkdir($baseDir, 0755, true)) {
                        throw new Exception("No se pudo crear la carpeta de reportes.");
                    }
                }

                $filename = uniqid() . '.' . $ext;
                $targetFile = $baseDir . $filename;

                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetFile)) {
                    // Guardamos la ruta relativa para la base de datos (sin ../)
                    $imgPath = 'uploads/reportes/' . $filename;
                } else {
                    throw new Exception("Error moviendo el archivo al servidor.");
                }
            }

            // 4. GUARDAR EN BD
            $sql = "INSERT INTO reportes_fallos (reseller_id, perfil_id, mensaje, evidencia_img, estado, fecha) VALUES (?, ?, ?, ?, 'pendiente', NOW())";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$myId, $perfilId, $mensaje, $imgPath])) {
                if (function_exists('notificarAdmin')) {
                    notificarAdmin("⚠️ Reporte de Soporte", "Un socio ha reportado una falla en una cuenta.");
                }
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Error SQL al guardar reporte.");
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Feedback
    if ($action == 'send_feedback') {
        ob_clean();

        // 1. Intentar JSON
        $input = json_decode(file_get_contents('php://input'), true);

        // 2. Intentar POST
        $tipo = $input['tipo'] ?? $_POST['tipo'] ?? 'mejora';
        $mensaje = $input['mensaje'] ?? $_POST['mensaje'] ?? null;

        if (empty($mensaje)) {
            echo json_encode(['success' => false, 'message' => 'El mensaje no puede estar vacío']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO feedback_sistema (reseller_id, tipo, mensaje) VALUES (?, ?, ?)");
            $stmt->execute([$myId, $tipo, $mensaje]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // 10. DATOS PERFIL
    if ($action == 'get_me') {
        ob_clean();
        $stmt = $pdo->prepare("SELECT nombre, saldo FROM distribuidores WHERE id = ?");
        $stmt->execute([$myId]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        exit;
    }

    // 11. LOGOUT
    if ($action == 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error Servidor: ' . $e->getMessage()]);
    exit;
}

// CSRF protection for mutating actions (only when logged in)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['reseller_id'])) {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionCsrf = $_SESSION['csrf_token'] ?? '';
    if (!$sessionCsrf || $csrfHeader !== $sessionCsrf) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'CSRF token inválido']);
        exit;
    }
}

// Notificaciones
if ($action == 'get_notifications') {
    ob_clean();
    // Traer mensajes propios O mensajes globales (NULL)
    $sql = "SELECT * FROM notificaciones_reseller 
                WHERE (reseller_id = ? OR reseller_id IS NULL) 
                ORDER BY fecha DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$myId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// 13. LEER NOTIFICACIONES
if ($action == 'get_my_notifications') {
    ob_clean();

    // Trae mensajes privados (ID) o Globales (NULL)
    // Limitamos a los últimos 20 para no saturar
    $sql = "SELECT * FROM notificaciones_reseller 
                WHERE reseller_id = ? OR reseller_id IS NULL 
                ORDER BY fecha DESC LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$myId]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Telegram Marketing

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

// Detalles perfil
if ($action == 'update_profile_details') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);

    $perfilId = $d['id'];
    $pin = $d['pin'] ?? null;
    $fecha = $d['fecha'] ?? null; // YYYY-MM-DD

    try {
        // Build SQL dynamically to avoid overwriting with null if unwanted, 
        // but here we allow updating what comes.
        $sql = "UPDATE perfiles SET pin_perfil = ?";
        $params = [$pin];

        if ($fecha) {
            $sql .= ", fecha_corte_cliente = ?";
            $params[] = $fecha;
        }

        $sql .= " WHERE id = ? AND reseller_id = ?";
        $params[] = $perfilId;
        $params[] = $myId;

        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute($params);

        if ($res) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


// Historial billetera
if ($action == 'get_wallet_history') {
    ob_clean();
    // Traemos los últimos 50 movimientos
    $stmt = $pdo->prepare("SELECT * FROM movimientos_reseller WHERE reseller_id = ? ORDER BY fecha DESC LIMIT 50");
    $stmt->execute([$myId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// OBTENER TASA (Para mostrar al distribuidor)
if ($action == 'get_tasa') {
    ob_clean();
    $stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'");
    $tasa = $stmt->fetchColumn();
    echo json_encode(['tasa' => $tasa ? $tasa : '0.00']);
    exit;
}

// 16. LIBERAR PERFIL (DEVOLVER AL STOCK - SIN REEMBOLSO)
if ($action == 'return_profile_to_stock') {
    $d = json_decode(file_get_contents('php://input'), true);
    $perfilId = $d['id'];

    try {
        // Solo liberar si pertenece a este reseller
        $sql = "UPDATE perfiles 
                    SET reseller_id = NULL, 
                        cliente_reseller_id = NULL, 
                        fecha_venta_reseller = NULL,
                        fecha_corte_cliente = NULL
                    WHERE id = ? AND reseller_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$perfilId, $myId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo liberar (quizás ya no es tuya).']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 13. CAMBIAR ESTADO AUTO-RENOVACIÓN (CORREGIDO)
if ($action == 'toggle_auto_renew') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);
    $id = $d['id'];
    $newState = $d['state']; // Recibimos 1 o 0 desde el JS

    try {
        // Actualizamos al estado específico que mandó el frontend
        $sql = "UPDATE perfiles SET auto_renovacion = ? WHERE id = ? AND reseller_id = ?";
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute([$newState, $id, $myId]);

        if ($res) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action == 'monitor_sales') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $input = file_get_contents('php://input');
    $d = json_decode($input, true);
    $search = $d['search'] ?? '';

    try {
        $sql = "SELECT p.id, c.plataforma, c.email_cuenta, 
                        p.nombre_perfil, p.pin_perfil, 
                        
                        -- FECHA 1: CUANDO COMPRASTE (Tu Stock)
                        p.fecha_venta_reseller,
                        
                        -- CÁLCULO VIRTUAL: Cuándo se te vence el stock (Venta + 30 días)
                        DATE_ADD(p.fecha_venta_reseller, INTERVAL 30 DAY) as vencimiento_stock_virtual,

                        -- FECHA 2: CUANDO PAGA EL CLIENTE (Editable)
                        p.fecha_corte_cliente,
                        
                        cr.nombre as cliente_nombre, 
                        cr.telefono as cliente_tel
                    FROM perfiles p
                    JOIN cuentas c ON p.cuenta_id = c.id
                    LEFT JOIN clientes_reseller cr ON p.cliente_reseller_id = cr.id
                    WHERE p.reseller_id = ?";

        $params = [$myId];

        if (!empty($search)) {
            $sql .= " AND (cr.nombre LIKE ? OR cr.telefono LIKE ? OR c.plataforma LIKE ? OR c.email_cuenta LIKE ?)";
            $term = "%$search%";
            array_push($params, $term, $term, $term, $term);
        }

        $sql .= " ORDER BY p.fecha_corte_cliente ASC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Limpieza UTF-8
        $cleanData = array_map(function ($row) {
            return array_map(function ($val) {
                return mb_convert_encoding($val ?? '', 'UTF-8', 'UTF-8');
            }, $row);
        }, $resultados);

        echo json_encode(['success' => true, 'data' => $cleanData]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Perfil

// LEER DATOS
if ($action == 'get_my_profile') {
    ob_clean();
    $stmt = $pdo->prepare("SELECT nombre, email, telefono, cedula FROM distribuidores WHERE id = ?");
    $stmt->execute([$myId]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

// ACTUALIZAR DATOS
if ($action == 'update_my_profile') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);

    try {
        // 1. Validar campos básicos
        if (empty($d['nombre']))
            throw new Exception("El nombre es obligatorio");

        // 2. Construir SQL dinámico (si envía password o no)
        $sql = "UPDATE distribuidores SET nombre = ?, telefono = ?, cedula = ?";
        $params = [$d['nombre'], $d['telefono'], $d['cedula']];

        // Si escribió algo en la contraseña, la actualizamos
        if (!empty($d['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($d['password'], PASSWORD_BCRYPT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $myId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Actualizar nombre de sesión
        $_SESSION['reseller_name'] = $d['nombre'];

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Finanzas
if ($action == 'get_finance_stats') {
    ob_clean();

    try {
        // 1. Tasa del Dólar (Para conversión en vivo)
        $tasa = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'")->fetchColumn();
        $tasa = floatval($tasa) ?: 0.00;

        // 2. Meta Mensual
        $stmtMeta = $pdo->prepare("SELECT meta_mensual FROM distribuidores WHERE id = ?");
        $stmtMeta->execute([$myId]);
        $meta = floatval($stmtMeta->fetchColumn()) ?: 100.00;

        // 3. GASTO DEL MES ACTUAL (Dinero real que salió de tu saldo)
        $mesActual = date('Y-m');
        $sqlGasto = "SELECT ABS(SUM(monto)) FROM movimientos_reseller 
                     WHERE reseller_id = $myId 
                     AND tipo IN ('compra', 'renovacion') 
                     AND DATE_FORMAT(fecha, '%Y-%m') = '$mesActual'";
        $gastoMes = floatval($pdo->query($sqlGasto)->fetchColumn()) ?: 0.00;

        // 4. VALOR DEL INVENTARIO ACTIVO (Proyección)
        // Traemos todo lo que tienes activo para calcular cuánto vale en la calle
        $sqlInventario = "SELECT c.plataforma FROM perfiles p 
                          JOIN cuentas c ON p.cuenta_id = c.id 
                          WHERE p.reseller_id = ?";
        $stmtInv = $pdo->prepare($sqlInventario);
        $stmtInv->execute([$myId]);
        $misPerfiles = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

        // Precios
        $preciosVenta = $pdo->query("SELECT plataforma, precio_venta FROM reseller_precios WHERE reseller_id = $myId")->fetchAll(PDO::FETCH_KEY_PAIR);
        $preciosCosto = $pdo->query("SELECT nombre, precio_reseller FROM catalogo")->fetchAll(PDO::FETCH_KEY_PAIR);

        $inversionTotal = 0;
        $valorVentaTotal = 0;
        $conteoPlataformas = [];

        foreach ($misPerfiles as $item) {
            $nombre = $item['plataforma'];

            // Conteo para Top Ventas
            if (!isset($conteoPlataformas[$nombre]))
                $conteoPlataformas[$nombre] = 0;
            $conteoPlataformas[$nombre]++;

            // Costos y Ventas
            $costo = isset($preciosCosto[$nombre]) ? floatval($preciosCosto[$nombre]) : 0;
            // Fallback de búsqueda parcial si no hay match exacto
            if ($costo == 0) {
                foreach ($preciosCosto as $k => $v) {
                    if (strpos($nombre, $k) !== false) {
                        $costo = floatval($v);
                        break;
                    }
                }
            }

            $venta = isset($preciosVenta[$nombre]) ? floatval($preciosVenta[$nombre]) : $costo; // Si no configuró precio, asume costo

            $inversionTotal += $costo;
            $valorVentaTotal += $venta;
        }

        $gananciaProyectada = $valorVentaTotal - $inversionTotal;
        $margen = ($inversionTotal > 0) ? round(($gananciaProyectada / $inversionTotal) * 100) : 0;

        // 5. TOP 3 PRODUCTOS
        arsort($conteoPlataformas);
        $top3 = array_slice($conteoPlataformas, 0, 3);

        // 6. Tabla de Precios (Igual que antes)
        $tablaPrecios = [];
        foreach ($preciosCosto as $nombre => $costo) {
            if ($costo <= 0)
                continue;
            $venta = isset($preciosVenta[$nombre]) ? floatval($preciosVenta[$nombre]) : floatval($costo);
            $tablaPrecios[] = ['plataforma' => $nombre, 'costo' => $costo, 'venta' => $venta];
        }
        usort($tablaPrecios, function ($a, $b) {
            return strcmp($a['plataforma'], $b['plataforma']);
        });

        echo json_encode([
            'success' => true,
            'tasa' => $tasa,
            'gasto_mes' => number_format($gastoMes, 2),
            'inversion_total' => number_format($inversionTotal, 2),
            'valor_calle' => number_format($valorVentaTotal, 2),
            'ganancia_estimada' => number_format($gananciaProyectada, 2),
            'margen' => $margen,
            'meta' => $meta,
            'progreso_meta' => ($meta > 0) ? min(100, round(($gananciaProyectada / $meta) * 100)) : 0,
            'top_productos' => $top3,
            'tabla_precios' => $tablaPrecios
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Acción necesaria para guardar (Verificar que exista)
if ($action == 'save_my_price') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);

    // Check insert or update logic... (Lo que ya tenías o versión simple)
    try {
        // Borramos el anterior y ponemos el nuevo (más fácil que hacer IF EXISTS)
        $pdo->prepare("DELETE FROM reseller_precios WHERE reseller_id = ? AND plataforma = ?")->execute([$myId, $d['plataforma']]);
        $pdo->prepare("INSERT INTO reseller_precios (reseller_id, plataforma, precio_venta) VALUES (?, ?, ?)")->execute([$myId, $d['plataforma'], $d['precio']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Acción actualizar meta
if ($action == 'update_goal') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE distribuidores SET meta_mensual = ? WHERE id = ?")->execute([$d['meta'], $myId]);
    echo json_encode(['success' => true]);
    exit;
}

// GUARDAR PRECIO DE VENTA
if ($action == 'save_my_price') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);

    // Verificar si ya existe para hacer update o insert
    $check = $pdo->prepare("SELECT id FROM reseller_precios WHERE reseller_id = ? AND plataforma = ?");
    $check->execute([$myId, $d['plataforma']]);

    if ($check->fetch()) {
        $sql = "UPDATE reseller_precios SET precio_venta = ? WHERE reseller_id = ? AND plataforma = ?";
    } else {
        $sql = "INSERT INTO reseller_precios (precio_venta, reseller_id, plataforma) VALUES (?, ?, ?)";
    }

    $stmt = $pdo->prepare($sql);
    echo json_encode(['success' => $stmt->execute([$d['precio'], $myId, $d['plataforma']])]);
    exit;
}

// ACTUALIZAR META
if ($action == 'update_goal') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE distribuidores SET meta_mensual = ? WHERE id = ?")->execute([$d['meta'], $myId]);
    echo json_encode(['success' => true]);
    exit;
}

// --- TUTORIAL ---
if ($action == 'check_tutorial') {
    ob_clean();
    $visto = $pdo->query("SELECT tutorial_visto FROM distribuidores WHERE id = $myId")->fetchColumn();
    echo json_encode(['visto' => $visto]);
    exit;
}

if ($action == 'finish_tutorial') {
    ob_clean();
    $pdo->query("UPDATE distribuidores SET tutorial_visto = 1 WHERE id = $myId");
    echo json_encode(['success' => true]);
    exit;
}

// Reportar recarga
if ($action == 'submit_recharge_proof') {
    // Limpiar cualquier basura anterior
    while (ob_get_level())
        ob_end_clean();
    header('Content-Type: application/json');

    try {
        // 1. Recibir Datos
        $monto = $_POST['monto'] ?? 0;
        $metodo = $_POST['metodo'] ?? 'Desconocido';

        // --- VALIDACIÓN DE SEGURIDAD NUEVA ---
        if ($monto <= 0) {
            throw new Exception("El monto debe ser mayor a 0.00");
        }

        $referencia = $_POST['referencia'] ?? '---';

        // Datos extra
        $detalles = json_encode([
            'nombre_titular' => $_POST['nombre_titular'] ?? '',
            'correo_titular' => $_POST['correo_titular'] ?? '',
            'fecha_pago' => $_POST['fecha_pago'] ?? ''
        ]);

        // 2. Manejo de Imagen
        $imgPath = null;

        if (isset($_FILES['comprobante'])) {
            try {
                // Validación Segura
                $ext = validateImageUpload($_FILES['comprobante']);

                // Definir ruta absoluta
                $baseDir = dirname(__DIR__) . '/uploads/pagos/';
                if (!is_dir($baseDir)) {
                    // Intento silencioso de crearla
                    @mkdir($baseDir, 0755, true);
                }

                $filename = uniqid() . '_pago.' . $ext;
                $targetFile = $baseDir . $filename;

                if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $targetFile)) {
                    $imgPath = 'uploads/pagos/' . $filename;
                } else {
                    throw new Exception("Error moviendo el archivo.");
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        // 3. Insertar en Base de Datos
        // Asegúrate que la tabla tenga estas columnas
        $stmt = $pdo->prepare("INSERT INTO historial_recargas (reseller_id, monto, metodo, referencia, comprobante_img, detalles_pago, estado, nota) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', 'Solicitud Web')");


        if ($stmt->execute([$myId, $monto, $metodo, $referencia, $imgPath, $detalles])) {

            // ⚠️ CORREO DESACTIVADO TEMPORALMENTE PARA PROBAR SI ERA EL ERROR
            // enviarCorreoHTML(EMAIL_ADMIN, "Pago Reportado", "Monto: $monto");
            notificarAdmin("💰 Nueva Recarga Reportada", "Un distribuidor ha reportado un pago de $$monto por $metodo.");
            echo json_encode(['success' => true, 'message' => 'Reporte enviado con éxito']);

        } else {
            throw new Exception("Error SQL: No se pudo guardar en la base de datos.");
        }

    } catch (Exception $e) {
        // Devolver error legible
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Extracción de código
if ($action == 'extract_code_reseller') {
    ob_clean();
    $perfilId = $_GET['id'];

    // 1. VERIFICAR PROPIEDAD (SEGURIDAD CRÍTICA)
    // Nos aseguramos de que el perfil pertenezca al reseller que está logueado
    $sql = "SELECT c.token_micuenta 
                FROM perfiles p 
                JOIN cuentas c ON p.cuenta_id = c.id 
                WHERE p.id = ? AND p.reseller_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$perfilId, $myId]);
    $cuenta = $stmt->fetch();

    if (!$cuenta || empty($cuenta['token_micuenta'])) {
        echo json_encode(['error' => 'No tienes permiso o la cuenta no tiene token.']);
        exit;
    }

    // 2. EXTRAER (Usando la lógica que ya funciona)
    $parts = explode('/', $cuenta['token_micuenta']);
    if (count($parts) < 2) {
        echo json_encode(['error' => 'Configuración de token inválida.']);
        exit;
    }

    $code = trim($parts[0]);
    $pdv = trim($parts[1]);
    $url_api = 'https://micuenta.me/e/redeem';

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
        echo json_encode(['error' => 'Error proveedor: ' . curl_error($ch)]);
    } else {
        echo $respuesta; // JSON directo del proveedor
    }
    curl_close($ch);
    exit;
}

// Comprar producto
if ($action == 'buy_product') {
    ob_clean();
    $d = json_decode(file_get_contents('php://input'), true);
    $plat = $d['plataforma'];
    $correoCliente = $d['correo_activar'] ?? null;

    try {
        $pdo->beginTransaction();

        // 1. OBTENER DATOS DEL PRODUCTO Y TIPO DE ENTREGA (MOVIDO AL PRINCIPIO)
        $stmtProd = $pdo->prepare("SELECT id, precio_reseller, tipo_entrega FROM catalogo WHERE nombre = ?");
        $stmtProd->execute([$plat]);
        $prodInfo = $stmtProd->fetch(PDO::FETCH_ASSOC);

        if (!$prodInfo)
            throw new Exception("Producto no disponible.");

        $tipoEntrega = $prodInfo['tipo_entrega']; // Definimos la variable aquí para usarla luego
        $productId = $prodInfo['id'];

        // VALIDACIÓN: Si requiere correo (tipo Canva/YouTube), debe venir
        if ($tipoEntrega === 'input_correo' && empty($correoCliente)) {
            throw new Exception("Debes proporcionar el correo a activar.");
        }

        // 2. CALCULAR PRECIO FINAL (CON DESCUENTO)
        $stmtDesc = $pdo->prepare("SELECT descuento_personal FROM distribuidores WHERE id = ?");
        $stmtDesc->execute([$myId]);
        $porcentajeDesc = floatval($stmtDesc->fetchColumn()) ?: 0;

        $precioBase = floatval($prodInfo['precio_reseller']);
        $precioFinal = $precioBase;

        if ($porcentajeDesc > 0) {
            $precioFinal = $precioBase - ($precioBase * ($porcentajeDesc / 100));
        }
        $precioFinal = round($precioFinal, 2);

        // 3. VERIFICAR SALDO
        $saldoActual = $pdo->query("SELECT saldo FROM distribuidores WHERE id = $myId")->fetchColumn();
        if ($saldoActual < $precioFinal) {
            throw new Exception("Saldo insuficiente. Costo: $$precioFinal");
        }

        // --- LÓGICA DE SELECCIÓN DE STOCK ---
        $esCompleta = (stripos($plat, 'Completa') !== false || stripos($plat, 'Cuenta') !== false);
        $perfilParaTicket = 0;
        $reseller_name = $_SESSION['reseller_name'] ?? 'Usuario'; // Corrección variable nombre

        if ($esCompleta) {
            // A. COMPRA DE CUENTA COMPLETA
            $nombreBase = explode(' ', $plat)[0];

            $sqlMaster = "
                SELECT c.id 
                FROM cuentas c
                LEFT JOIN perfiles p ON c.id = p.cuenta_id
                WHERE (c.plataforma = ? OR c.plataforma LIKE ?)
                GROUP BY c.id
                HAVING COUNT(CASE WHEN p.reseller_id IS NOT NULL OR p.cliente_id IS NOT NULL THEN 1 END) = 0
                LIMIT 1 FOR UPDATE
            ";
            $stmtM = $pdo->prepare($sqlMaster);
            $stmtM->execute([$plat, "%$nombreBase%"]);
            $cuentaLibre = $stmtM->fetch();

            if (!$cuentaLibre) {
                // Si es entrega automática/credenciales y no hay stock -> ERROR
                if ($tipoEntrega === 'credenciales' || $tipoEntrega === 'automatica') {
                    throw new Exception("Sin stock automático disponible.");
                }
                $perfilParaTicket = 0; // Se va a pedido manual
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE perfiles SET reseller_id = ?, fecha_venta_reseller = NOW(), correo_a_activar = ? WHERE cuenta_id = ?");
                $stmtUpdate->execute([$myId, $correoCliente, $cuentaLibre['id']]);

                // Corrección: Usar la variable definida arriba
                notificarAdmin("🛒 Venta B2B Exitosa", "El Distribuidor $reseller_name compró: $plat.");
                $perfilParaTicket = $pdo->query("SELECT id FROM perfiles WHERE cuenta_id = {$cuentaLibre['id']} LIMIT 1")->fetchColumn();
            }

        } else {
            // B. COMPRA DE PANTALLA INDIVIDUAL
            $sqlSingle = "
                SELECT p.id 
                FROM perfiles p 
                JOIN cuentas c ON p.cuenta_id=c.id 
                WHERE (c.plataforma = ? OR c.plataforma LIKE ?)
                AND p.cliente_id IS NULL 
                AND p.reseller_id IS NULL 
                LIMIT 1 FOR UPDATE
            ";
            $stmtS = $pdo->prepare($sqlSingle);
            $stmtS->execute([$plat, "%$plat%"]);
            $stk = $stmtS->fetch();

            if (!$stk) {
                if ($tipoEntrega === 'credenciales' || $tipoEntrega === 'automatica') {
                    throw new Exception("Sin stock automático disponible.");
                }
                $perfilParaTicket = 0;
            } else {
                $pdo->prepare("UPDATE perfiles SET reseller_id = ?, fecha_venta_reseller = NOW(), correo_a_activar = ? WHERE id = ?")
                    ->execute([$myId, $correoCliente, $stk['id']]);
                notificarAdmin("🛒 Venta B2B Exitosa", "Distribuidor $reseller_name compró: $plat.");
                $perfilParaTicket = $stk['id'];
            }
        }

        // 4. DESCONTAR SALDO
        $pdo->prepare("UPDATE distribuidores SET saldo = saldo - ? WHERE id = ?")->execute([$precioFinal, $myId]);

        // 5. REGISTRAR MOVIMIENTO
        $descMovimiento = "Compra: $plat";
        if ($porcentajeDesc > 0)
            $descMovimiento .= " (Desc: $porcentajeDesc%)";

        $pdo->prepare("INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion) VALUES (?, 'compra', ?, ?)")
            ->execute([$myId, -$precioFinal, $descMovimiento]);

        $pdo->commit();

        // 6. GENERAR PEDIDO WEB (Si aplica)
        $pedidoId = 0;
        $waUrl = '';

        if ($tipoEntrega === 'manual' || $tipoEntrega === 'input_correo' || $perfilParaTicket == 0) {
            // Datos del Reseller
            $stmtRes = $pdo->prepare("SELECT nombre, email, telefono FROM distribuidores WHERE id = ?");
            $stmtRes->execute([$myId]);
            $ri = $stmtRes->fetch(PDO::FETCH_ASSOC);

            // Tasa
            $stmtTasa = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'");
            $tasaDolar = floatval($stmtTasa->fetchColumn()) ?: 0.0;
            $monto_bs = round($precioFinal * $tasaDolar, 2);

            // Insertar Pedido
            // Nota: Verificamos si la columna existe antes (opcional, pero recomendado en local)
            $stmtPedido = $pdo->prepare(
                "INSERT INTO pedidos (perfil_id, reseller_id, cliente_nombre, cliente_telefono, cliente_email, producto_id, nombre_producto, precio_usd, monto_bs, metodo_pago, comprobante_img, descuento_aplicado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            // Pasamos NULL si perfilParaTicket es 0
            $pidParaSql = ($perfilParaTicket > 0) ? $perfilParaTicket : null;

            $stmtPedido->execute([$pidParaSql, $myId, $ri['nombre'], $ri['telefono'], $ri['email'], $productId, $plat, $precioFinal, $monto_bs, 'Saldo', null, 0.0]);
            $pedidoId = $pdo->lastInsertId();

            // Link WhatsApp
            $adminPhone = '584123368325'; // Puse el numero que vi en el otro archivo, ajusta si es necesario
            $waMessage = "Nuevo pedido manual: Reseller {$ri['nombre']}, Producto: {$plat}, Pedido ID: {$pedidoId}";
            $waUrl = 'https://wa.me/' . $adminPhone . '?text=' . urlencode($waMessage);
        }

        echo json_encode(['success' => true, 'id_perfil' => $perfilParaTicket, 'pedido_id' => $pedidoId, 'whatsapp_url' => $waUrl]);

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Liberar cuenta
if ($action == "release_account") {
    ob_clean();
    $d = json_decode(file_get_contents("php://input"), true);
    $perfilId = $d["id"];
    $isMaster = $d["is_master"] ?? false;

    try {
        if ($isMaster) {
            // Get account ID
            $stmtGet = $pdo->prepare("SELECT cuenta_id FROM perfiles WHERE id = ? AND reseller_id = ?");
            $stmtGet->execute([$perfilId, $myId]);
            $cuentaId = $stmtGet->fetchColumn();

            if (!$cuentaId) {
                throw new Exception("Cuenta no encontrada o no te pertenece.");
            }

            $stmt = $pdo->prepare("UPDATE perfiles 
                                       SET reseller_id = NULL, 
                                           cliente_reseller_id = NULL, 
                                           fecha_venta_reseller = NULL, 
                                           fecha_corte_cliente = NULL,
                                           pin_perfil = NULL,
                                           auto_renovacion = 0
                                       WHERE cuenta_id = ? AND reseller_id = ?");
            $stmt->execute([$cuentaId, $myId]);
            $msg = "Cuenta Completa liberada y devuelta al stock";
        } else {
            $stmt = $pdo->prepare("UPDATE perfiles 
                                       SET reseller_id = NULL, 
                                           cliente_reseller_id = NULL, 
                                           fecha_venta_reseller = NULL, 
                                           fecha_corte_cliente = NULL,
                                           pin_perfil = NULL,
                                           auto_renovacion = 0
                                       WHERE id = ? AND reseller_id = ?");
            $stmt->execute([$perfilId, $myId]);
            $msg = "Perfil liberado y devuelto al stock";
        }

        if ($stmt->rowCount() > 0) {
            logResellerActivity($myId, "LIBERAR_CUENTA", "$msg (Ref ID: $perfilId)");
            echo json_encode(["success" => true, "message" => $msg]);
        } else {
            echo json_encode(["success" => false, "message" => "No se realizaron cambios (quizás ya no es tuya)."]);
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit;
}

