<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Partners - D'Level</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="style.css">
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: radial-gradient(circle at top, #001f3f, #000); margin: 0; font-family: 'Outfit', sans-serif; }
        .reg-card { background: rgba(20,20,20,0.95); border: 1px solid rgba(0, 198, 255, 0.3); padding: 2.5rem; border-radius: 20px; width: 90%; max-width: 500px; box-shadow: 0 0 50px rgba(0, 198, 255, 0.15); }
        .step { display: none; animation: fadeIn 0.5s; }
        .step.active { display: block; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        
        .code-input { letter-spacing: 15px; font-size: 2rem; font-weight: bold; text-align: center; color: #00ff88; text-transform: uppercase; }
        label { display: block; color: #aaa; margin: 10px 0 5px; font-size: 0.9rem; }
    </style>
</head>
<body>

    <div class="reg-card">
        <h2 style="color:white; text-align:center; font-family:'Rajdhani'">Nuevo <span style="color:#00c6ff">Partner</span></h2>
        
        <!-- PASO 1: CÓDIGO -->
        <div id="step-1" class="step active">
            <p style="text-align:center; color:#ccc;">Ingresa tu código de invitación:</p>
            <input type="text" id="invite-code" class="code-input" maxlength="6" placeholder="000000">
            <button onclick="verifyCode()" class="buy-btn" id="btn-verify">Validar Código</button>
            <div style="text-align:center; margin-top:20px;">
                <a href="index.php" style="color:#666; text-decoration:none;">← Volver al Login</a>
            </div>
        </div>

        <!-- PASO 2: DATOS PERSONALES -->
        <div id="step-2" class="step">
            <form id="reg-form">
                <input type="hidden" id="valid-code">
                
                <label>Nombre y Apellido:</label>
                <input type="text" id="reg-name" required>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div>
                        <label>Cédula / ID:</label>
                        <input type="text" id="reg-id" required>
                    </div>
                    <div>
                        <label>Teléfono:</label>
                        <input type="text" id="reg-phone" required>
                    </div>
                </div>

                <label>Estimación de Ventas Mensuales ($):</label>
                <select id="reg-sales" style="background:rgba(255,255,255,0.05); color:white; border:1px solid #444; width:100%; padding:12px; border-radius:10px;">
                    <option value="10-50">Principiante ($10 - $50)</option>
                    <option value="50-200">Intermedio ($50 - $200)</option>
                    <option value="200+">Experto ($200+)</option>
                </select>

                <label>Correo (Usuario):</label>
                <input type="email" id="reg-email" required>

                <label>Contraseña:</label>
                <input type="password" id="reg-pass" required>

                <button type="submit" class="buy-btn" style="background:linear-gradient(90deg, #00c6ff, #0072ff); color:white; margin-top:20px;">Completar Registro</button>
            </form>
        </div>
    </div>

    <script>
        function verifyCode() {
            const code = document.getElementById('invite-code').value;
            const btn = document.getElementById('btn-verify');
            
            if(code.length < 6) return Swal.fire({icon:'error', title:'Error', text:'El código debe tener 6 dígitos', background:'#111', color:'#fff'});

            btn.textContent = "...";
            btn.disabled = true;

            fetch('api.php?action=validate_invite_code', {
                method: 'POST',
                body: JSON.stringify({ codigo: code })
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    // Pasar al siguiente paso
                    document.getElementById('step-1').classList.remove('active');
                    document.getElementById('step-2').classList.add('active');
                    document.getElementById('valid-code').value = code; // Guardar token para el envío final
                    
                    Swal.fire({icon:'success', title:'Código Correcto', text:'Bienvenido, completa tus datos.', background:'#111', color:'#fff', timer:1500, showConfirmButton:false});
                } else {
                    Swal.fire({icon:'error', title:'Inválido', text: res.message, background:'#111', color:'#fff'});
                    btn.textContent = "Validar Código";
                    btn.disabled = false;
                }
            });
        }

        document.getElementById('reg-form').addEventListener('submit', e => {
            e.preventDefault();
            
            const data = {
                codigo: document.getElementById('valid-code').value,
                nombre: document.getElementById('reg-name').value,
                cedula: document.getElementById('reg-id').value,
                telefono: document.getElementById('reg-phone').value,
                ventas: document.getElementById('reg-sales').value,
                email: document.getElementById('reg-email').value,
                pass: document.getElementById('reg-pass').value
            };

            fetch('api.php?action=register_reseller_self', {
                method: 'POST',
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    Swal.fire({
                        icon: 'success', title: '¡Registro Exitoso!', text: 'Tu cuenta ha sido creada. Ingresa ahora.', background: '#111', color: '#fff'
                    }).then(() => {
                        window.location.href = 'index.php'; // Redirigir al login
                    });
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: res.message, background: '#111', color: '#fff'});
                }
            });
        });
    </script>
</body>
</html>