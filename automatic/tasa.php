<?php
// ============================================================
// CRON JOB RÁPIDO: ACTUALIZAR TASA DÓLAR
// Ejecutar cada 30 min o 1 hora
// ============================================================

// 1. SEGURIDAD
$cronKey = 'Cruch2603.'; // <--- TU CLAVE
$inputKey = $_GET['key'] ?? ($argv[1] ?? null);

if ($inputKey !== $cronKey) {
    http_response_code(403);
    die("Acceso Denegado.");
}

require '../admin/db.php';

// 2. CONSULTAR API
try {
    $ch = curl_init("https://ve.dolarapi.com/v1/dolares/paralelo");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    
    if(curl_errno($ch)) throw new Exception(curl_error($ch));
    curl_close($ch);
    
    $data = json_decode($res, true);
    $extra = 5.00;
    
    if (isset($data['promedio']) && $data['promedio'] > 0) {
        $tasa = $data['promedio'] + $extra ;
        
        // Actualizar BD
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_dolar'");
        $stmt->execute([$tasa]);
        
        echo "✅ Tasa Actualizada: Bs. " . number_format($tasa, 2) . " (Hora: " . date('H:i:s') . ")";
    } else {
        echo "⚠️ API respondió pero sin precio válido.";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>