<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Partners</title>
    <!-- SweetAlert2 (Alertas Bonitas) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../index.css">
    <!-- Usamos estilos en línea para evitar conflictos de carga -->
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Rajdhani:wght@500;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top, #001f3f, #000);
            margin: 0;
            font-family: 'Outfit', sans-serif;
        }

        .login-card {
            background: rgba(20, 20, 20, 0.9);
            border: 1px solid rgba(0, 198, 255, 0.3);
            padding: 2.5rem;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 0 40px rgba(0, 198, 255, 0.1);
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            color: white;
            text-align: center;
            outline: none;
        }

        input:focus {
            border-color: #00c6ff;
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #00c6ff, #0072ff);
            border: none;
            border-radius: 50px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.3s;
        }

        button:hover {
            transform: scale(1.02);
            box-shadow: 0 0 15px rgba(0, 198, 255, 0.4);
        }

        h2 {
            color: white;
            font-family: 'Rajdhani', sans-serif;
            font-size: 2rem;
            margin-bottom: 5px;
        }

        p {
            color: #00c6ff;
            letter-spacing: 3px;
            font-size: 0.8rem;
            margin-bottom: 2rem;
            text-transform: uppercase;
        }

        .error {
            color: #ff0055;
            margin-top: 15px;
            font-size: 0.9rem;
            display: none;
        }
    </style>
    <!-- Configuración APP Android -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#00c6ff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/png" href="../img/icon-192.png">
</head>

<body>

    <div class="login-card">
        <h2>D'Level</h2>
        <p>Partners</p>

        <form id="login-form">
            <input type="email" id="email" placeholder="Correo" required>
            <input type="password" id="pass" placeholder="Contraseña" required>
            <button type="submit" id="btn-login">INGRESAR</button>

            <!-- SECCIÓN DEMO MEJORADA -->
            <div style="margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <p style="color:#aaa; font-size:0.9rem; margin-bottom:10px">¿Quieres probar antes de unirte?</p>

                <button onclick="enterDemo()" type="button" style="
        background: transparent; 
        border: 1px solid #00c6ff; 
        color: #00c6ff; 
        width: 100%; 
        padding: 10px; 
        border-radius: 50px; 
        cursor: pointer; 
        font-weight: bold; 
        display: flex; align-items: center; justify-content: center; gap: 10px;
        transition: 0.3s;">
                    <span>👁️</span> Acceder como Invitado
                </button>
            </div>
            <div style="margin-top: 20px; font-size: 0.9rem; color: #888;">
                ¿Quieres ser Distribuidor?<br>
                <a href="register.php" style="color: #00c6ff; font-weight: bold; text-decoration: none;">Registrarme</a>
            </div>
        </form>

        <div id="msg" class="error"></div>
    </div>

    <script>

        function enterDemo() {
            const btn = document.querySelector('button[onclick="enterDemo()"]');
            btn.innerHTML = "Accediendo al sistema...";
            btn.style.opacity = "0.7";

            fetch('api.php?action=login_demo', { method: 'POST' })
                .then(r => r.json())
                .then(res => {
                    if (res.success) window.location.href = 'panel.php';
                    else alert("Error: " + res.message);
                });
        }
        // --- AUTO LOGIN (TOKEN MÁGICO) ---
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const token = params.get('magic_token');

            if (token) {
                // Ocultar formulario y mostrar carga
                document.querySelector('.login-card').innerHTML = '<h3 style="color:white">Accediendo como Admin...</h3>';

                fetch('api.php?action=login_with_token', {
                    method: 'POST',
                    body: JSON.stringify({ token: token })
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            window.location.href = 'panel.php';
                        } else {
                            alert("Error de acceso: " + res.message);
                            window.location.href = 'index.php'; // Limpiar URL
                        }
                    });
            }
        });


        // LÓGICA DIRECTA Y AISLADA
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = document.getElementById('btn-login');
            const msg = document.getElementById('msg');

            // Estado de carga
            btn.textContent = "Verificando...";
            btn.disabled = true;
            btn.style.opacity = "0.7";
            msg.style.display = 'none';

            const data = {
                email: document.getElementById('email').value,
                pass: document.getElementById('pass').value
            };

            try {
                const req = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                // Verificar si la respuesta es JSON válido
                const text = await req.text();
                let res;
                try {
                    res = JSON.parse(text);
                } catch (err) {
                    throw new Error("Error del servidor (No JSON): " + text.substring(0, 50));
                }

                if (res.success) {
                    window.location.href = 'panel.php';
                } else {
                    throw new Error(res.message || "Datos incorrectos");
                }

            } catch (error) {
                msg.textContent = error.message;
                msg.style.display = 'block';
                btn.textContent = "INGRESAR";
                btn.disabled = false;
                btn.style.opacity = "1";
            }
        });
    </script>
</body>



</html>