<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Acceso Denegado");
}

require 'db.php';
require '../fpdf/fpdf.php';

class PDF extends FPDF {
    // Cabecera de página
    function Header() {
        // Logo (Asegúrate de que exista o comenta la línea)
        if(file_exists('../img/logo.png')) {
            $this->Image('../img/logo.png', 10, 6, 20); 
        }
        
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(127, 0, 255); // Color Morado D'Level
        $this->Cell(80); // Mover a la derecha
        $this->Cell(30, 10, "D'Level Play Max - Reporte General", 0, 0, 'C');
        $this->Ln(8);
        
        $this->SetFont('Arial', 'I', 10);
        $this->SetTextColor(100);
        $this->Cell(0, 10, 'Generado el: ' . date('d/m/Y H:i A'), 0, 0, 'C');
        $this->Ln(20);
    }

    // Pie de página
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Función para títulos de sección
    function SectionTitle($label) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0);
        $this->Cell(0, 8, utf8_decode($label), 0, 1, 'L', true);
        $this->Ln(4);
    }

    // Función para cabeceras de tabla
    function TableHeader($headers, $widths) {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(127, 0, 255);
        $this->SetTextColor(255);
        for($i=0; $i<count($headers); $i++) {
            $this->Cell($widths[$i], 7, utf8_decode($headers[$i]), 1, 0, 'C', true);
        }
        $this->Ln();
    }
}

// Iniciar PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// 1. VENTAS ACTIVAS (ASIGNACIONES)
$pdf->SectionTitle('1. Ventas Activas y Clientes');
$header = ['Cliente', 'Plataforma', 'Perfil', 'Vence'];
$w = [60, 50, 40, 40]; // Anchos de columna
$pdf->TableHeader($header, $w);

$pdf->SetFont('Arial', '', 8);
$sql = "SELECT c.nombre, p.nombre_perfil, cu.plataforma, p.fecha_corte_cliente 
        FROM perfiles p 
        JOIN clientes c ON p.cliente_id = c.id 
        JOIN cuentas cu ON p.cuenta_id = cu.id 
        WHERE p.cliente_id IS NOT NULL 
        ORDER BY p.fecha_corte_cliente ASC";
$stmt = $pdo->query($sql);

while($row = $stmt->fetch()) {
    $pdf->SetTextColor(0);
    // Marcar en rojo si está vencido
    if($row['fecha_corte_cliente'] < date('Y-m-d')) $pdf->SetTextColor(255, 0, 0);
    
    $pdf->Cell($w[0], 6, utf8_decode(substr($row['nombre'], 0, 35)), 1);
    $pdf->Cell($w[1], 6, utf8_decode(substr($row['plataforma'], 0, 25)), 1);
    $pdf->Cell($w[2], 6, utf8_decode($row['nombre_perfil']), 1);
    $pdf->Cell($w[3], 6, $row['fecha_corte_cliente'], 1);
    $pdf->Ln();
}
$pdf->Ln(10);

// 2. INVENTARIO (STOCK)
$pdf->SectionTitle('2. Inventario Disponible');
$header = ['Plataforma', 'Email Cuenta', 'Pass', 'Disponibles'];
$w = [50, 70, 40, 30];
$pdf->TableHeader($header, $w);

$pdf->SetTextColor(0);
$sql = "SELECT c.plataforma, c.email_cuenta, c.password, COUNT(p.id) as libres
        FROM perfiles p 
        JOIN cuentas c ON p.cuenta_id = c.id 
        WHERE p.cliente_id IS NULL 
        GROUP BY c.id 
        ORDER BY c.plataforma";
$stmt = $pdo->query($sql);

while($row = $stmt->fetch()) {
    $pdf->Cell($w[0], 6, utf8_decode(substr($row['plataforma'], 0, 25)), 1);
    $pdf->Cell($w[1], 6, utf8_decode($row['email_cuenta']), 1);
    $pdf->Cell($w[2], 6, utf8_decode($row['password']), 1);
    $pdf->Cell($w[3], 6, $row['libres'], 1, 0, 'C');
    $pdf->Ln();
}
$pdf->Ln(10);

// 3. LISTA DE CLIENTES
$pdf->AddPage(); // Nueva página para clientes
$pdf->SectionTitle('3. Base de Datos de Clientes');
$header = ['ID', 'Nombre', 'Telefono', 'Email'];
$w = [15, 65, 40, 70];
$pdf->TableHeader($header, $w);

$sql = "SELECT * FROM clientes ORDER BY id DESC";
$stmt = $pdo->query($sql);

while($row = $stmt->fetch()) {
    $pdf->Cell($w[0], 6, $row['id'], 1, 0, 'C');
    $pdf->Cell($w[1], 6, utf8_decode($row['nombre']), 1);
    $pdf->Cell($w[2], 6, $row['telefono'], 1);
    $pdf->Cell($w[3], 6, utf8_decode($row['email']), 1);
    $pdf->Ln();
}

// 4. HISTORIAL DE PEDIDOS (Últimos 50)
$pdf->Ln(10);
$pdf->SectionTitle('4. Últimos Pedidos Web');
$header = ['Fecha', 'Cliente', 'Producto', 'Total ($)', 'Estado'];
$w = [35, 50, 60, 20, 25];
$pdf->TableHeader($header, $w);

$sql = "SELECT fecha_pedido, cliente_nombre, nombre_producto, precio_usd, estado FROM pedidos ORDER BY id DESC LIMIT 50";
$stmt = $pdo->query($sql);

while($row = $stmt->fetch()) {
    $pdf->Cell($w[0], 6, date('d/m/y H:i', strtotime($row['fecha_pedido'])), 1);
    $pdf->Cell($w[1], 6, utf8_decode(substr($row['cliente_nombre'], 0, 25)), 1);
    $pdf->Cell($w[2], 6, utf8_decode(substr($row['nombre_producto'], 0, 30)), 1);
    $pdf->Cell($w[3], 6, $row['precio_usd'], 1, 0, 'R');
    $pdf->Cell($w[4], 6, strtoupper($row['estado']), 1, 0, 'C');
    $pdf->Ln();
}

// Salida
$pdf->Output('D', 'Reporte_DLevel_'.date('Y-m-d').'.pdf');
?>