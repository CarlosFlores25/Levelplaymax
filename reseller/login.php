<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido - D'Level</title>
    <link rel="stylesheet" href="../index.css">
        <!-- Favicon (Logo en la pestaña) -->
    <link rel="icon" type="image/png" href="../img/logo.png">

    <!-- Opcional: Para que se vea bien si guardan la web en iPhone/iPad -->
    <link rel="apple-touch-icon" href="../img/logo.png">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #000; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; text-align: center; background: rgba(20,20,20,0.9); border: 1px solid var(--primary-color); border-radius: 20px; box-shadow: 0 0 50px rgba(127,0,255,0.2); }
        input { width: 100%; margin-bottom: 1rem; background: #111; border: 1px solid #333; padding: 1rem; color: white; border-radius: 8px; }
        .error { color: #ff0055; margin-bottom: 1rem; display: none; }
        
    </style>
</head>
<body>
    <div class="login-card">
        <h2 style="margin-bottom: 1.5rem; color: white;">D'Level <span style="color:var(--secondary-color)">Admin</span></h2>
        <p id="error-msg" class="error">Credenciales incorrectas</p>
        <form id="login-form">
            <input type="text" id="user" placeholder="Usuario" required>
            <input type="password" id="pass" placeholder="Contraseña" required>
            <button type="submit" class="cta-button-main" style="width:100%">Ingresar</button>
        </form>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', e => {
            e.preventDefault();
            const u = document.getElementById('user').value;
            const p = document.getElementById('pass').value;

            fetch('api.php?action=login', {
                method: 'POST',
                body: JSON.stringify({ user: u, pass: p })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.href = 'admin.php';
                } else {
                    document.getElementById('error-msg').style.display = 'block';
                }
            });
        });
    </script>
</body>
</html>