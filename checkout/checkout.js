document.addEventListener('DOMContentLoaded', function() {
    
    // ==========================================
    // ⚠️ CONFIGURA TUS DATOS DE PAGO AQUÍ
    // ==========================================
    // --- CONFIGURACIÓN DE TUS DATOS DE PAGO ---
    const PAYMENT_DATA = {
        'Pago Movil': {
            datos: `
                <p><strong>Banco:</strong> Venezuela (0102)</p>
                <p><strong>C.I:</strong> 29911214 </p>
                <p><strong>Tel:</strong> 0412-3368325</p>
            `
        },
        'Binance': {
            qr: '../img/binance.jpeg', // O pon la url de tu imagen QR
            datos: '<p>Binance ID: <strong>346-766-184</strong></p><p>Email: carloscruch@gmail.com</p>'
        },
        'Zinli': {
            datos: '<p>Email Zinli: <strong>carloscruch@gmail.com</strong></p>'
        }
    };


    // --- VARIABLES GLOBALES ---
    let currentTasa = 60; 
    let selectedMethod = '';
    let formData = { nombre:'', telefono:'', email:'' };
    
    // VARIABLES DE CUPÓN
    let discountPercent = 0;
    let appliedCouponCode = null;

    // 1. Leer URL para saber qué producto es
    const params = new URLSearchParams(window.location.search);
    const prodId = params.get('id');
    const prodName = params.get('name');
    const prodPriceOriginal = parseFloat(params.get('price'));

    if(!prodId || !prodName) {
        alert("Error: No seleccionaste ningún producto.");
        window.location.href = 'catalogo.html';
        return;
    }

    // 2. Mostrar info inicial
    document.getElementById('summary-prod-name').textContent = prodName;
    updatePriceDisplay(); // Función que pinta el precio

    // 3. Obtener Tasa del Dólar
    fetch('../admin/api.php?action=get_tasa')
        .then(res => res.json())
        .then(data => {
            if(data.tasa) {
                currentTasa = parseFloat(data.tasa);
                const lblTasa = document.getElementById('lbl-tasa');
                if(lblTasa) lblTasa.textContent = currentTasa;
            }
        });

    // --- LÓGICA DE CUPONES ---
    window.applyCoupon = function() {
        const code = document.getElementById('coupon-code').value.trim();
        const msg = document.getElementById('coupon-msg');
        const btn = document.querySelector('#step-1 button.cta-button-main[onclick]'); // Botón aplicar
        
        if(!code) return;

        const data = new FormData();
        data.append('codigo', code);

        // Feedback visual
        msg.textContent = "Verificando...";
        msg.style.color = "#aaa";

        fetch('../admin/api.php?action=validate_coupon', { method: 'POST', body: data })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    discountPercent = parseInt(data.descuento);
                    appliedCouponCode = code;
                    
                    updatePriceDisplay(); // Recalcular totales visuales
                    
                    msg.style.color = '#00ff88';
                    msg.textContent = `✅ ${data.mensaje}`;
                    
                    // Bloquear para no cambiarlo
                    document.getElementById('coupon-code').disabled = true;
                    if(btn) btn.style.display = 'none';
                } else {
                    msg.style.color = '#ff0055';
                    msg.textContent = `❌ ${data.message}`;
                    discountPercent = 0;
                    appliedCouponCode = null;
                    updatePriceDisplay();
                }
            })
            .catch(err => {
                msg.textContent = "Error de conexión";
            });
    };

    function updatePriceDisplay() {
        const precioFinal = calculateFinalPrice();
        const display = document.getElementById('summary-price');

        if(discountPercent > 0) {
            display.innerHTML = `
                <span style="text-decoration:line-through; color:#777; font-size:0.9rem; margin-right:5px">$${prodPriceOriginal.toFixed(2)}</span> 
                <span style="color:#00ff88">$${precioFinal.toFixed(2)}</span>
            `;
        } else {
            display.textContent = `$${prodPriceOriginal.toFixed(2)}`;
        }
    }

    function calculateFinalPrice() {
        const descuento = (prodPriceOriginal * discountPercent) / 100;
        return prodPriceOriginal - descuento;
    }

    // --- NAVEGACIÓN ENTRE PASOS ---

    // Paso 1 -> Paso 2
    document.getElementById('form-datos').addEventListener('submit', (e) => {
        e.preventDefault();
        formData.nombre = document.getElementById('cx-nombre').value;
        formData.telefono = document.getElementById('cx-telefono').value;
        formData.email = document.getElementById('cx-email').value;
        goToStep(2);
    });

    // Paso 2 -> Paso 3 (Selección de Método)
    window.selectPayment = function(method) {
        selectedMethod = method;
        renderPaymentDetails();
        goToStep(3);
    }

    // Mostrar datos de pago en el Paso 3 (Calculando con descuento)
    function renderPaymentDetails() {
        const container = document.getElementById('payment-display');
        const info = PAYMENT_DATA[selectedMethod];
        const finalPrice = calculateFinalPrice();
        
        let html = `<h3 style="color:var(--secondary); margin-bottom:15px">${selectedMethod}</h3>`;

        if (selectedMethod === 'Pago Movil') {
            const montoBs = (finalPrice * currentTasa).toFixed(2);
            html += `
                <div class="bs-amount">Bs. ${montoBs}</div>
                <div style="color:white; font-size:1.1rem; line-height:1.6; border-top:1px solid #333; padding-top:10px">
                    ${info.datos}
                </div>
            `;
        } else {
            if(info.qr) html += `<img src="${info.qr}" class="qr-code" onerror="this.style.display='none'">`;
            html += `<div style="color:white; margin-top:10px">${info.datos}</div>`;
            html += `<div class="bs-amount">$${finalPrice.toFixed(2)}</div>`;
        }

        container.innerHTML = html;
    }

    // Paso 3 -> Paso 4 (Subir Foto y Crear Pedido)
    document.getElementById('file-input').addEventListener('change', function() {
        if(this.files[0]) {
            document.getElementById('file-label').textContent = "📄 " + this.files[0].name;
            document.getElementById('file-label').style.color = "#00ff88";
        }
    });

    document.getElementById('form-comprobante').addEventListener('submit', (e) => {
        e.preventDefault();
        
        const fileInput = document.getElementById('file-input');
        if(fileInput.files.length === 0) { alert("Por favor sube la captura del pago."); return; }

        // Preparar datos finales
        const precioFinal = calculateFinalPrice();
        const descuentoMonto = prodPriceOriginal - precioFinal;
        const montoBs = (precioFinal * currentTasa).toFixed(2);

        const data = new FormData();
        data.append('nombre', formData.nombre);
        data.append('telefono', formData.telefono);
        data.append('email', formData.email);
        data.append('producto_id', prodId);
        data.append('producto_nombre', prodName);
        data.append('precio', precioFinal.toFixed(2)); // Precio con descuento
        data.append('monto_bs', montoBs);
        data.append('metodo', selectedMethod);
        data.append('comprobante', fileInput.files[0]);
        
        // Datos del cupón
        data.append('cupon_codigo', appliedCouponCode || '');
        data.append('descuento_monto', descuentoMonto.toFixed(2));

        // Botón cargando
        const btn = e.target.querySelector('button');
        const originalText = btn.textContent;
        btn.textContent = "Enviando...";
        btn.disabled = true;

        fetch('../admin/api.php?action=create_order', { method: 'POST', body: data })
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    goToStep(4); // Éxito
                } else {
                    alert("Error: " + res.message);
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                alert("Error de conexión.");
                btn.disabled = false;
            });
    });

    window.goToStep = function(step) {
        document.querySelectorAll('.step-card').forEach(el => el.classList.remove('active'));
        document.getElementById(`step-${step}`).classList.add('active');
        window.scrollTo(0,0);
    }
});