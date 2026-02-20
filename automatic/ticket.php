<?php
require '../admin/db.php';
$id = $_GET['id'];
$p = $pdo->query("SELECT p.*, c.plataforma, c.email_cuenta, c.password FROM perfiles p JOIN cuentas c ON p.cuenta_id = c.id WHERE p.id = $id")->fetch();
if (!$p) die("No encontrado");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - <?php echo $p['plataforma']; ?></title>
    <link rel="stylesheet" href="../index.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#000; text-align:center; font-family:'Poppins',sans-serif; }
        .card { background: #111; padding: 2rem; border-radius: 20px; border: 1px solid #333; max-width: 400px; width:90%; box-shadow: 0 0 50px rgba(0,255,136,0.1); }
        .plat { font-size: 1.5rem; font-weight: bold; color: #00ff88; margin-bottom: 20px; text-transform: uppercase; }
        .data-row { background: #222; padding: 10px; margin-bottom: 10px; border-radius: 8px; text-align: left; word-break: break-all;}
        .label { color: #888; font-size: 0.8rem; display: block; }
        .value { color: white; font-family: monospace; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="plat"><?php echo $p['plataforma']; ?></div>
        
        <?php if(!empty($p['correo_a_activar'])): ?>
            <!-- CASO ACTIVACIÓN -->
            <div class="data-row" style="border-left: 3px solid #ffbb00;">
                <span class="label">CUENTA A ACTIVAR</span>
                <span class="value"><?php echo $p['correo_a_activar']; ?></span>
            </div>
            <div class="data-row">
                <span class="label">ESTADO</span>
                <span class="value" style="color: #ffbb00;">⏳ Pendiente de Admin</span>
            </div>
            <p style="color:#aaa; font-size:0.9rem">Te avisaremos cuando esté listo.</p>

        <?php elseif($p['pin_perfil'] == 'LINK'): ?>
            <!-- CASO LINK -->
            <div class="data-row">
                <span class="label">ENLACE DE ACTIVACIÓN</span>
                <a href="<?php echo $p['email_cuenta']; ?>" style="color:#00ff88; font-weight:bold;">CLIC PARA ABRIR</a>
            </div>

        <?php else: ?>
            <!-- CASO CREDENCIALES -->
            <div class="data-row"><span class="label">USUARIO</span><span class="value"><?php echo $p['email_cuenta']; ?></span></div>
            <div class="data-row"><span class="label">CONTRASEÑA</span><span class="value"><?php echo $p['password']; ?></span></div>
            <?php if($p['nombre_perfil']) echo "<div class='data-row'><span class='label'>PERFIL</span><span class='value'>{$p['nombre_perfil']}</span></div>"; ?>
            <?php if($p['pin_perfil'] && $p['pin_perfil']!='N/A') echo "<div class='data-row'><span class='label'>PIN</span><span class='value' style='color:#00ff88'>{$p['pin_perfil']}</span></div>"; ?>
        <?php endif; ?>

        <p style="color:#555; font-size:0.8rem; margin-top:20px;">D'Level Play Max</p>
    </div>
</body>
</html>