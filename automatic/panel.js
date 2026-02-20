document.addEventListener("DOMContentLoaded", () => {
  console.log("Iniciando Panel Partner...");
  loadAll();

  // Lógica del Preloader
  setTimeout(() => {
    const loader = document.getElementById("preloader");
    if (loader) {
      loader.classList.add("hide");
      // Opcional: Remover del DOM después de la animación
      setTimeout(() => loader.remove(), 1000);
    }
  }, 500); // Espera 1.5 segundos

  setInterval(resellerRealTime, 15000);
});

// 2. Wrappers de SweetAlert2 (Para reemplazar alert/confirm)
const SwalSuccess = (text) => Swal.fire({
  title: '¡Hecho!',
  text: text,
  icon: 'success',
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  background: '#111',
  color: '#fff'
});

const SwalError = (text) => Swal.fire({
  title: 'Error',
  text: text,
  icon: 'error',
  background: '#111',
  color: '#fff'
});

const SwalConfirm = (text, callback) => Swal.fire({
  title: '¿Estás seguro?',
  text: text,
  icon: 'question',
  showCancelButton: true,
  confirmButtonText: 'Sí, continuar',
  cancelButtonText: 'Cancelar',
  background: '#111',
  color: '#fff',
  confirmButtonColor: '#00ff88',
  cancelButtonColor: '#d33'
}).then((result) => {
  if (result.isConfirmed) callback();
});

const SwalPrompt = (title) => Swal.fire({
  title: title,
  input: 'text',
  inputPlaceholder: 'Escribe aquí...',
  showCancelButton: true,
  background: '#111',
  color: '#fff',
  confirmButtonColor: '#00c6ff'
});

// DATOS DEL TUTORIAL (SOLUCIÓN A TU ERROR)
const tourData = [
  {
    i: '🚀',
    t: 'Bienvenido Socio',
    d: 'D\'Level es tu herramienta todo en uno. Vamos a configurarla en 30 segundos.'
  },
  {
    i: '💳',
    t: '1. Tu Billetera',
    d: 'En la pestaña <strong>"Recargar"</strong> reportas tus pagos. El saldo aparece arriba y es lo que usas para comprar stock.'
  },
  {
    i: '🛒',
    t: '2. Tienda Stock',
    d: 'Aquí está el inventario disponible. Al dar click en <strong>Comprar</strong>, recibes la cuenta al instante (24/7).'
  },
  {
    i: '📦',
    t: '3. Tu Inventario',
    d: 'En <strong>"Mis Cuentas"</strong> tienes el control: 👁️ Ver claves, 🔄 Renovar fechas y 📝 Asignar clientes.'
  },
  {
    i: '⚙️',
    t: '4. Cuentas Completas',
    d: 'Si compras una Cuenta Completa (Ej: 5 pantallas), se agruparán en una <strong>Tarjeta Maestra</strong> azul para gestionarlas juntas.'
  },
  {
    i: '📈',
    t: '5. Tus Finanzas',
    d: 'Usa esta pestaña para fijar tus precios de venta al público. El sistema calculará tu ganancia neta automáticamente.'
  },
  {
    i: '🎨',
    t: '6. Publicidad Gratis',
    d: 'En <strong>Material Ads</strong> puedes descargar imágenes y videos promocionales listos para tus estados de WhatsApp.'
  },
  {
    i: '🛡️',
    t: '7. Soporte',
    d: 'Si algo falla, usa el botón ⚠️ en la cuenta afectada. Responderemos en la pestaña <strong>Reportes</strong>.'
  }
];

// === VARIABLES GLOBALES ===
let myClients = [];
let lastBalance = null;
let maxMsgId = 0; // Antes era lastNotifCount
let isFirstRun = true; // Para no sonar al cargar la página
let monitorTimeout = null;
// === CARGA INICIAL ===
function loadAll() {
  // Carga inicial ultrarrápida
  loadDashboard();
  loadTasa();
  loadClients();
  // Las notificaciones sí las dejamos porque son ligeras
  // El resto espera al clic.
}

function showTab(id) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  const tab = document.getElementById(id);
  if (tab) tab.classList.add('active');

  if (event && event.currentTarget && !event.currentTarget.classList.contains('mobile-more-btn')) {
    event.currentTarget.classList.add('active');
  }
  const menu = document.getElementById('mobile-menu-overlay');
  if (menu) menu.classList.remove('open');

  // SWITCH DE CARGA
  switch (id) {
    case 'dashboard': loadDashboard(); break;
    case 'stock': loadStock(); break;
    case 'inventory': loadInventory(); break; // Aquí dentro ya llama a loadClients si hace falta
    case 'clients': loadClients(); break;
    case 'monitor': loadMonitor(); break;
    case 'pagos': loadWalletHistory(); break;
    case 'reportes': loadReports(); break;
    case 'feedback': loadFeedback(); break;
    case 'perfil': loadProfile(); break;
    case 'finanzas': loadFinance(); break;
  }
}

// ==========================================
// 1. UTILS: CODIFICACIÓN SEGURA
// ==========================================
function safeEncode(obj) {
  return btoa(encodeURIComponent(JSON.stringify(obj)));
}
function safeDecode(str) {
  return JSON.parse(decodeURIComponent(atob(str)));
}

function getLogo(name) {
  if (!name) return "../img/default.png";
  const n = name.toLowerCase();
  const path = '../img/'; // Asegúrate de que esta carpeta exista


  // Streaming
  if (n.includes('netflix')) return path + 'netflix.png';
  if (n.includes('disney')) return path + 'disney.png';
  if (n.includes('hbo')) return path + 'hbo.png';
  if (n.includes('amazon') || n.includes('primevideo')) return path + 'prime.png';
  if (n.includes('crunchyroll')) return path + 'crunchyroll.png';
  if (n.includes('paramount')) return path + 'paramount.png';
  if (n.includes('vix')) return path + 'vix.png';
  if (n.includes('plex')) return path + 'plex.png';
  if (n.includes('viki')) return path + 'viki.png';

  // Estudio 
  if (n.includes('canva')) return path + 'canva.png';
  if (n.includes('gemini')) return path + 'gemini.png';
  if (n.includes('chat gpt')) return path + 'gpt.png';
  if (n.includes('capcut')) return path + 'capcut.png';
  if (n.includes('claude')) return path + 'claude.png';
  if (n.includes('sora')) return path + 'sora.png';
  if (n.includes('midjourney')) return path + 'mid.png';


  // Musica
  if (n.includes('youtube')) return path + 'youtube.png';
  if (n.includes('spotify')) return path + 'spotify.png';
  if (n.includes('deezer')) return path + 'deezer.png';
  if (n.includes('soundcloud')) return path + 'soundcloud.png';
  if (n.includes('qobuz')) return path + 'qobuz.png';

  // Gaming
  if (n.includes('deluxe')) return path + 'psplus.png';
  if (n.includes('xbox')) return path + 'xbox.png';
  if (n.includes('discord')) return path + 'discord.png';

  // Otros
  if (n.includes('one')) return path + 'one.png';
  if (n.includes('windows')) return path + 'windows.png';

  return path + 'default.png';
}


function toggleMobileMenu() {
  document.getElementById("mobile-menu-overlay").classList.toggle("open");
}

function loadDashboard() {
  fetch('api.php?action=get_dashboard').then(r => r.json()).then(d => {
    if (d.error) return;

    // 1. Datos de Usuario
    const nameEl = document.getElementById('dash-user-name');
    if (nameEl && d.nombre) nameEl.textContent = d.nombre;

    // 2. Saldo
    const balEl = document.getElementById('dash-balance');
    if (balEl) balEl.textContent = `$${parseFloat(d.saldo).toFixed(2)}`;

    // 3. Tasa BCV/Monitor (Vital para Vzla)
    const tasaEl = document.getElementById('dash-tasa-display');
    if (tasaEl) tasaEl.textContent = `${parseFloat(d.tasa).toFixed(2)} Bs`;

    // Actualizar también la tasa en la sección de recargas si existe
    const tasaLive = document.getElementById('live-tasa-bs');
    if (tasaLive) tasaLive.textContent = parseFloat(d.tasa).toFixed(2);

    // 4. Estadísticas
    document.getElementById('dash-active').textContent = d.activas;
    document.getElementById('dash-por-vencer').textContent = d.por_vencer;
    document.getElementById('dash-ventas-hoy').textContent = d.ventas_hoy;

    // Alerta visual si hay muchas cuentas por vencer
    const cardUrgent = document.querySelector('.card-urgent');
    if (d.por_vencer > 0) {
      cardUrgent.style.background = 'rgba(255, 165, 0, 0.1)'; // Fondo naranja suave
    }

  }).catch(console.error);
}

function loadTasa() {
  fetch("api.php?action=get_tasa")
    .then((r) => r.json())
    .then((d) => {
      const el = document.getElementById("live-tasa-bs");
      if (el) {
        el.textContent = parseFloat(d.tasa).toFixed(2);
        el.style.textShadow = "0 0 10px rgba(0, 255, 136, 0.4)";
      }
    });
}

function safeLoad(fn) {
  try {
    if (typeof fn === "function") fn();
  } catch (e) {
    console.error(e);
  }
}

// ==========================================
// 🔄 TIEMPO REAL & NOTIFICACIONES (LOGICA DE ID)
// ==========================================

function resellerRealTime() {
  // 1. BALANCE (Dinero)
  fetch("api.php?action=get_dashboard")
    .then((r) => r.json())
    .then((d) => {
      if (d.error) return;
      const currentBalance = parseFloat(d.saldo);

      // Detectar Recarga (Solo si aumentó y no es la primera carga)
      if (lastBalance !== null && currentBalance > lastBalance && !isFirstRun) {
        showToast(`💰 Recarga: +$${(currentBalance - lastBalance).toFixed(2)}`);
        loadDashboard(); // Refrescar vista
        playNotificationSound();
      }

      lastBalance = currentBalance;
    })
    .catch(() => { });

  // --- LOGICA DE NOTIFICACIÓN CORREGIDA ---
  fetch("api.php?action=get_my_notifications")
    .then((r) => r.json())
    .then((data) => {
      const badge = document.getElementById("notif-badge");

      if (data.length > 0) {
        // Buscamos el ID más alto (el mensaje más reciente)
        const ids = data.map((n) => parseInt(n.id));
        const currentMax = Math.max(...ids);

        // Si el ID nuevo es mayor al que conocíamos
        if (currentMax > maxMsgId) {
          // Si NO es la primera vez que carga la página (es decir, llegó en vivo)
          if (!isFirstRun) {
            showToast("🔔 Nuevo Mensaje del Admin", "info"); // Popup
            playNotificationSound(); // Sonido
          }

          // Encendemos el punto rojo siempre que haya novedades sin leer
          // (Para que suene la alerta tienes que tener maxMsgId actualizado,
          // pero si solo entras al panel y hay nuevos, solo mostramos el punto).
          if (badge) badge.style.display = "block";

          maxMsgId = currentMax; // Actualizamos el registro
        }
      }

      isFirstRun = false; // Desactiva bandera de primera carga
    })
    .catch((e) => console.log("Error checking notifs", e));
}

// Función auxiliar para el sonido (para evitar errores de navegador)
function playNotificationSound() {
  const audio = document.getElementById("audio-notif");
  if (audio) {
    // Los navegadores bloquean audio si el usuario no ha hecho click en la pagina primero
    audio
      .play()
      .catch(() => console.log("Sonido bloqueado por falta de interacción."));
  }
}

// ==========================================
// 2. STOCK (TIENDA SHOWCASE)
// ==========================================
function loadStock() {
  const grid = document.getElementById('stock-grid');
  if (!grid) return;

  fetch('api.php?action=get_stock').then(r => r.json()).then(d => {
    grid.innerHTML = '';

    if (!d || d.length === 0) {
      grid.innerHTML = '<p style="color:#aaa;text-align:center;grid-column:1/-1;">No hay productos en el catálogo.</p>';
      return;
    }

    d.forEach(i => {
      const img = `<img src="${getLogo(i.plataforma)}" class="plat-icon-lg" onerror="this.style.display='none'">`;

      let btnHtml = '';
      let statusHtml = '';
      let cardStyle = '';

      let precioHtml = '';

      // Si tiene descuento especial
      if (i.tiene_descuento) {
        precioHtml = `
            <div style="display:flex; flex-direction:column; align-items:center;">
                <span style="text-decoration:line-through; color:#777; font-size:0.8rem;">$${i.precio_original}</span>
                <div class="stock-qty" style="color:#00ff88; text-shadow:0 0 10px rgba(0,255,136,0.3); font-size:2rem;">
                    $${i.precio_reseller} <span style="font-size:0.7rem; background:#00ff88; color:black; padding:2px 4px; border-radius:4px; vertical-align:middle;">-${i.porcentaje_off}%</span>
                </div>
            </div>`;
      } else {
        // Precio normal
        precioHtml = `
            <div class="stock-qty" style="color:${i.disponibles > 0 ? '#00c6ff' : '#aaa'}">
                $${i.precio_reseller}
            </div>`;
      }

      // LÓGICA DE ESTADO
      if (i.disponibles > 0) {
        // HAY STOCK 
        btnHtml = `<button class="buy-btn" onclick="buyProduct('${i.plataforma}', ${i.precio_reseller})">Comprar</button>`;
      } else {
        // AGOTADO / PRÓXIMAMENTE
        statusHtml = `<div class="label-disp" style="color:#777;">AGOTADO</div>`;
        btnHtml = `<button class="buy-btn" disabled style="background:#333; color:#777; cursor:not-allowed; box-shadow:none; border:1px solid #444;">Proximamente...</button>`;
        cardStyle = 'opacity: 0.7; filter: grayscale(0.4);'; // Efecto visual de inactivo
      }

      grid.innerHTML += `
            <div class="stock-item" style="${cardStyle}">
                ${img}
                <div class="stock-name">${i.plataforma}</div>
                <div class="stock-qty" style="color:${i.disponibles > 0 ? '#00c6ff' : '#aaa'}">
                    $${precioHtml} <!-- AQUÍ VA EL NUEVO PRECIO -->
                </div>
                ${statusHtml}
                ${btnHtml}
            </div>`;
    });
  });
}

// COMPRA INTELIGENTE (DETECTA SI PIDE CORREO)
function buyProduct(plat, price) {
  // 1. Consultar si el producto requiere input (hacemos un fetch rápido al catálogo o lo inferimos)
  // Para hacerlo rápido sin otra petición, usaremos la lista de stock que ya tenemos cargada.
  // (Asumimos que loadStock guardó los datos en una variable global o buscamos en el DOM, 
  //  pero mejor hacemos la petición al momento para asegurar).

  // Truco: Si el nombre contiene "Canva" o "YouTube", pedimos correo. 
  // O mejor, pasamos el tipo_entrega desde la API get_stock (Paso 5.1).

  // PASO 5.1: Asegúrate que get_stock en reseller/api.php devuelva 'tipo_entrega'
  // ...

  // Suponiendo que ya sabemos que es Canva:
  const esActivacion = plat.toLowerCase().includes('canva') || plat.toLowerCase().includes('youtube');

  if (esActivacion) {
    // MODAL PARA PEDIR CORREO
    Swal.fire({
      title: 'Activación Requerida',
      text: `Ingresa el correo de tu cliente para activar ${plat}:`,
      input: 'email',
      inputPlaceholder: 'cliente@correo.com',
      showCancelButton: true,
      confirmButtonText: `Pagar $${price} y Activar`,
      background: '#111', color: '#fff', confirmButtonColor: '#00c6ff'
    }).then((result) => {
      if (result.isConfirmed && result.value) {
        executePurchase(plat, result.value);
      }
    });
  } else {
    // COMPRA NORMAL
    SwalConfirm(`¿Comprar ${plat} por $${price}?`, () => {
      executePurchase(plat, null);
    });
  }
}

function executePurchase(plat, correoActivar) {
  const data = {
    plataforma: plat,
    correo_activar: correoActivar // Se envía si existe, si no va null
  };

  fetch('api.php?action=buy_product', {
    method: 'POST',
    body: JSON.stringify(data)
  })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        SwalSuccess("¡Procesado!");
        loadAll();
        window.open(`ticket.php?id=${res.id_perfil}`, '_blank');
      } else {
        SwalError(res.message);
      }
    });
}

// ==========================================
// 5. CLIENTES (CON NOTAS INTERNAS)
// ==========================================
function loadClients() {
  fetch('api.php?action=list_my_clients')
    .then(r => r.json())
    .then(data => {
      myClients = data;
      const grid = document.getElementById('clients-grid');
      if (!grid) return;

      grid.innerHTML = '';

      if (data.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#aaa; padding:2rem;">Sin clientes registrados.</div>';
      } else {
        // AQUÍ SE DEFINE LA VARIABLE 'c'
        data.forEach(c => {
          const iniciales = c.nombre.substring(0, 2).toUpperCase();

          // Lógica de Notas Visual
          const notaHtml = c.nota_interna
            ? `<div style="font-size:0.75rem; color:#f0b90b; margin-top:5px; border-top:1px dashed #333; padding-top:3px; overflow:hidden; text-overflow:ellipsis;">📝 ${c.nota_interna}</div>`
            : '';

          // Codificamos los datos para pasarlos al botón de editar
          // Usamos '' si la nota es null para que no rompa el btoa
          const safeName = safeEncode(c.nombre);
          const safePhone = safeEncode(c.telefono);
          const safeNote = safeEncode(c.nota_interna || '');

          grid.innerHTML += `
                <div class="data-card" style="flex-direction:row; align-items: center; justify-content: space-between;">
                    <div style="display:flex; align-items:center; gap:15px; overflow:hidden;">
                        <div style="width:45px; height:45px; min-width:45px; background:#333; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; border:1px solid #555;">
                            ${iniciales}
                        </div>
                        <div style="overflow:hidden;">
                            <div style="color:white; font-weight:bold; font-size:1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.nombre}</div>
                            <div style="color:#aaa; font-size:0.85rem;">${c.telefono}</div>
                            ${notaHtml}
                        </div>
                    </div>
                    <button class="action-btn" onclick="openEditClient(${c.id}, '${safeName}', '${safePhone}', '${safeNote}')" style="width:40px; height:40px; min-width:40px; justify-content:center;">✏️</button>
                </div>`;
        });
      }

      loadInventory(); // Recargar inventario para actualizar selectores
    })
    .catch(err => console.error("Error clientes", err));
}

// CREAR CLIENTE (Con Nota)
const fc = document.getElementById('form-client');
if (fc) {
  fc.addEventListener('submit', e => {
    e.preventDefault();
    const d = {
      nombre: document.getElementById('cli-name').value,
      telefono: document.getElementById('cli-phone').value,
      nota: document.getElementById('cli-note').value // Nuevo campo
    };
    fetch('api.php?action=add_my_client', { method: 'POST', body: JSON.stringify(d) })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          document.getElementById('modal-client').style.display = 'none';
          loadClients();
          fc.reset();
        } else {
          SwalError(res.message);
        }
      });
  });
}

// ABRIR MODAL EDICIÓN (Con Nota)
function openEditClient(id, encN, encT, encNote) {
  document.getElementById('edit-cli-id').value = id;
  try {
    document.getElementById('edit-cli-name').value = safeDecode(encN);
    document.getElementById('edit-cli-phone').value = safeDecode(encT);
    // Cargar nota si existe
    const note = encNote ? safeDecode(encNote) : '';
    document.getElementById('edit-cli-note').value = note;
  } catch (e) { console.error(e); }

  openModal('modal-edit-client');
}

// GUARDAR EDICIÓN
const fec = document.getElementById('form-edit-client');
if (fec) {
  fec.addEventListener('submit', e => {
    e.preventDefault();
    const data = {
      id: document.getElementById('edit-cli-id').value,
      nombre: document.getElementById('edit-cli-name').value,
      telefono: document.getElementById('edit-cli-phone').value,
      nota: document.getElementById('edit-cli-note').value // Nuevo
    };
    fetch('api.php?action=edit_my_client', { method: 'POST', body: JSON.stringify(data) })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          closeModal('modal-edit-client');
          loadClients();
        } else {
          SwalError("Error al actualizar");
        }
      });
  });
}
// ==========================================
// 6. INVENTARIO Y GESTIÓN
// ==========================================
function loadInventory() {
  fetch("api.php?action=my_inventory")
    .then((r) => r.json())
    .then((d) => {
      const grid = document.getElementById("inventory-grid");
      if (!grid) return;
      grid.innerHTML = "";
      if (d.length === 0) {
        grid.innerHTML =
          '<div style="grid-column:1/-1; text-align:center; color:#aaa; padding:2rem;">No has comprado cuentas.</div>';
        return;
      }

      const grouped = {};
      d.forEach((item) => {
        const k = item.email_cuenta;
        if (!grouped[k]) grouped[k] = [];
        grouped[k].push(item);
      });

      Object.values(grouped).forEach((group) => {
        const item = group[0];
        const np = item.nombre_perfil.toLowerCase();
        const esMaster =
          group.length > 1 || np.includes("cuenta") || np.includes("completa");

        if (esMaster) renderResellerMasterCard(group, grid);
        else renderResellerSingleCard(item, grid);
      });
    });
}

function renderResellerSingleCard(item, container) {
  const hoy = new Date();
  const vence = new Date(item.fecha_vencimiento);
  const diff = Math.ceil((vence - hoy) / 864e5);

  let badgeClass = "badge-success", txt = diff + " días";
  if (diff <= 5) badgeClass = "badge-warning";
  if (diff <= 0) { badgeClass = "badge-danger"; txt = "VENCIDO"; }

  const pin = `<input type="text" class="mini-input" id="ep-${item.id}" value="${item.pin_perfil || ''}" placeholder="PIN">`;
  const fec = `<input type="date" class="mini-input" id="ed-${item.id}" value="${item.fecha_vencimiento ? item.fecha_vencimiento.split(" ")[0] : ""}">`;

  let sel = `<select class="card-select" onchange="assignClient(${item.id},this.value)"><option value="">-- Asignar Cliente --</option>`;
  if (myClients.length) {
    myClients.forEach(c => {
      sel += `<option value="${c.id}" ${item.cliente_final_id == c.id ? "selected" : ""}>${c.nombre}</option>`;
    });
  }
  sel += "</select>";

  const img = `<img src="${getLogo(item.plataforma)}" class="plat-icon-sm">`;
  const chk = item.auto_renovacion == 1 ? "checked" : "";

  // Botón Código: Si existe, lo ponemos. Si no, ponemos un placeholder vacío para mantener el grid alineado
  let btnCode = `<button class="action-btn" disabled style="opacity:0.3">🔒</button>`;
  if (item.token_micuenta && item.token_micuenta.length > 3) {
    btnCode = `<button class="action-btn" onclick="getResellerCode(${item.id})" style="color:#ffbb00;">🔓 COD</button>`;
  }

  container.innerHTML += `
    <div class="data-card inventory-card-clean">
        
        <!-- CABECERA FIJA -->
        <div class="inv-clean-header">
            <div class="inv-clean-title" title="${item.plataforma}">
                ${img} <span>${item.plataforma}</span>
            </div>
            <span class="inv-badge ${badgeClass}">${txt}</span>
        </div>

        <!-- CUERPO -->
        <div class="card-body">
            
            <!-- Fila 1: Selector Cliente -->
            <div class="stable-row">
                <span class="stable-label">CLIENTE</span>
                ${sel}
            </div>

            <!-- Fila 2: PIN y Guardar -->
            <div class="stable-row">
                <span class="stable-label">PIN</span>
                <div style="display:flex; gap:5px; flex:1;">
                    ${pin}
                    <button onclick="savePD(${item.id})" style="background:var(--secondary); border:none; border-radius:4px; width:30px; cursor:pointer; color:black;">💾</button>
                </div>
            </div>

            <!-- Fila 3: Fecha -->
            <div class="stable-row">
                <span class="stable-label">FIN</span>
                ${fec}
            </div>

            <!-- Fila 4: Auto-Renovar (Alineado a la derecha) -->
            <div class="switch-stable-container">
                <span style="font-size:0.7rem; color:#666;">Renovar Auto</span>
                <label class="switch compact" style="margin:0; transform:scale(0.75);">
                    <input type="checkbox" ${chk} onchange="toggleAR(${item.id},this)">
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- BOTONERA INFERIOR (GRID) -->
        <div class="inv-clean-actions">
            <button class="action-btn primary" onclick="viewCreds('${safeEncode(item)}')">👁️ VER</button>
            ${btnCode}
            <button class="action-btn success" onclick="renewProduct(${item.id},'${item.plataforma}')">↻ REN</button>
            <button class="action-btn danger" onclick="reportIssue(${item.id})">⚠️</button>
        </div>
    </div>`;
}

function renderResellerMasterCard(group, container) {
  const base = group[0];
  const totalSlots = group.length;
  const ocupados = group.filter(p => p.cliente_final_id).length;
  const porcentaje = (ocupados / totalSlots) * 100;

  const hoy = new Date();
  const vence = new Date(base.fecha_vencimiento);
  const diff = Math.ceil((vence - hoy) / 864e5);

  let txt = diff + " días", badgeColor = "badge-success";
  if (diff <= 5) badgeColor = "badge-warning";
  if (diff <= 0) { badgeColor = "badge-danger"; txt = "VENCIDA"; }

  const img = `<img src="${getLogo(base.plataforma)}" class="plat-icon-sm">`;
  const safeGroup = safeEncode(group);
  const chk = base.auto_renovacion == 1 ? "checked" : "";

  // Botón Código
  let btnCode = '';
  if (base.token_micuenta && base.token_micuenta.length > 3) {
    btnCode = `<button class="action-btn" onclick="getResellerCode(${base.id})" style="color:#ffbb00; border-left:1px solid #444;">🔓 COD</button>`;
  }

  container.innerHTML += `
    <div class="data-card inventory-card-clean is-master" style="border-left: 3px solid var(--secondary);">
        
        <!-- Header -->
        <div class="inv-clean-header">
            <div class="inv-clean-title" style="color:var(--secondary)" title="${base.plataforma}">
                ${img} <span>${base.plataforma}</span>
            </div>
            <span class="inv-badge ${badgeColor}">${txt}</span>
        </div>

        <!-- Cuerpo -->
        <div class="card-body">
            <!-- Barra Ocupación -->
            <div style="display:flex; justify-content:space-between; font-size:0.7rem; color:#aaa; margin-bottom:2px;">
                <span>Ocupación</span>
                <span style="color:white">${ocupados}/${totalSlots}</span>
            </div>
            <div style="background:#333; height:4px; border-radius:2px; overflow:hidden; margin-bottom:10px;">
                <div style="width: ${porcentaje}%; height:100%; background:var(--secondary);"></div>
            </div>

            <!-- Switch Grande y Visible -->
            <div class="switch-stable-container" style="background:rgba(255,255,255,0.05); padding:0 10px; border-radius:6px; justify-content:space-between; height:35px;">
                <span style="font-size:0.75rem; color:white; font-weight:bold;">Auto-Renovar Saldo</span>
                <label class="switch compact" style="margin:0; transform:scale(0.8);">
                    <input type="checkbox" ${chk} onchange="toggleAR(${base.id},this)">
                    <span class="slider"></span>
                </label>
            </div>

            <!-- Email truncado -->
            <div style="margin-top:10px; padding:5px; background:rgba(0,0,0,0.3); border-radius:4px; font-family:monospace; font-size:0.8rem; color:#aaa; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                ${base.email_cuenta}
            </div>
        </div>

        <!-- Botonera Maestra (Grid ajustado) -->
        <div class="inv-clean-actions" style="grid-template-columns: 1fr auto auto;">
            <button onclick="openResellerMasterModal('${safeGroup}')" class="action-btn" style="background:linear-gradient(90deg, rgba(0,198,255,0.1), rgba(0,114,255,0.1)); color:#00c6ff; font-weight:bold;">
                ⚙️ GESTIONAR
            </button>
            ${btnCode}
            <button onclick="returnProfile(${base.id})" class="action-btn danger" style="width:50px; border-left:1px solid #444;" title="Devolver">
                ↩️
            </button>
        </div>
    </div>`;
}

// B. MODAL DE GESTIÓN (El Centro de Mando)
function openResellerMasterModal(enc) {
  const group = safeDecode(enc);
  const base = group[0]; // Datos generales de la cuenta

  // 1. Llenar Cabecera del Modal
  const headerHtml = `
      <div class="master-header-box">
          <div style="font-size:1.2rem; font-weight:bold; color:white; margin-bottom:5px;">${base.plataforma}</div>
          <div style="font-family:monospace; color:#aaa; font-size:0.9rem;">${base.email_cuenta}</div>
          <div style="font-family:monospace; color:#00c6ff; font-weight:bold; font-size:1.1rem; margin-top:5px;">${base.password}</div>
          
          <button onclick="copyMasterData('${base.email_cuenta}', '${base.password}')" class="copy-master-btn">
              Copiar Correo y Clave
          </button>
      </div>
  `;

  // 2. Generar Grid de Perfiles
  let slotsHtml = '<div class="slots-grid">';

  group.forEach((p, index) => {
    // Estado: Ocupado si tiene cliente asignado
    const isOccupied = (p.cliente_final_id != null);
    const statusClass = isOccupied ? 'st-busy' : 'st-free';
    const statusText = isOccupied ? 'OCUPADO' : 'LIBRE';
    const cardClass = isOccupied ? 'occupied' : '';

    // Selector de Cliente
    let sel = `<select class="slot-input" onchange="assignClient(${p.id}, this.value)" style="margin-bottom:0;">
                    <option value="">-- Libre --</option>`;

    if (myClients.length) {
      myClients.forEach((c) => {
        sel += `<option value="${c.id}" ${p.cliente_final_id == c.id ? "selected" : ""}>${c.nombre}</option>`;
      });
    }
    sel += "</select>";

    // Inputs PIN y Vencimiento
    const pinVal = (p.pin_perfil && p.pin_perfil !== "N/A") ? p.pin_perfil : "";
    const dateVal = p.fecha_vencimiento ? p.fecha_vencimiento.split(" ")[0] : "";

    // Renderizar Tarjeta Individual
    slotsHtml += `
        <div class="profile-slot-card ${cardClass}">
            <!-- Header Slot -->
            <div class="slot-header">
                <span class="slot-number">👤 ${p.nombre_perfil}</span>
                <span class="slot-status ${statusClass}">${statusText}</span>
            </div>

            <!-- Body Slot -->
            <div class="slot-body">
                <div style="margin-bottom:10px;">
                    <label style="font-size:0.7rem; color:#888;">CLIENTE:</label>
                    ${sel}
                </div>

                <div class="slot-input-row">
                    <div style="flex:1">
                        <input type="text" id="ep-${p.id}" value="${pinVal}" class="slot-input" placeholder="PIN">
                    </div>
                    <div style="flex:1">
                        <input type="date" id="ed-${p.id}" value="${dateVal}" class="slot-input">
                    </div>
                </div>
            </div>

            <!-- Botones Acción -->
            <div class="slot-actions">
                <button class="slot-btn" onclick="savePD(${p.id})" title="Guardar Cambios">💾</button>
                <button class="slot-btn" onclick="sendWhatsappProfile(${p.id}, '${base.plataforma}', '${base.email_cuenta}', '${base.password}', '${p.nombre_perfil}')" title="Enviar por WhatsApp" style="color:#00ff88">📲</button>
                <button class="slot-btn" onclick="renewProduct(${p.id}, '${p.nombre_perfil}')" title="Renovar" style="color:#00c6ff">↻</button>
                <button class="slot-btn" onclick="reportIssue(${p.id})" title="Reportar Falla" style="color:#ff0055">⚠️</button>
            </div>
        </div>
      `;
  });

  slotsHtml += '</div>'; // Cerrar grid

  // Inyectar en el modal
  const modalBody = document.getElementById("master-modal-body");
  if (modalBody) {
    modalBody.innerHTML = headerHtml + slotsHtml;
    openModal("modal-reseller-master"); // Asegúrate de que el ID coincida con tu HTML
  } else {
    console.error("No se encontró el contenedor master-modal-body");
  }
}

// FUNCIONES AUXILIARES
function copyMasterData(email, pass) {
  const text = `Correo: ${email}\nClave: ${pass}`;
  navigator.clipboard.writeText(text).then(() => SwalSuccess("Credenciales copiadas"));
}

function copyTextToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => SwalSuccess("Copiado"));
}

function sendWhatsappProfile(id, plat, mail, pass, perfil) {
  // Buscar el PIN actual en el input
  const pin = document.getElementById(`ep-${id}`).value;
  const msg = `*${plat}* 🍿\n\n📧: ${mail}\n🔑: ${pass}\n👤: ${perfil}\n🔒 PIN: ${pin || 'Sin PIN'}\n\n¡Que lo disfrutes!`;

  // Preguntar número si no está asignado, o usar el del cliente
  // (Simplificado: abre wa.me vacío con el texto)
  window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
}

// Acciones de Inventario
function assignClient(pid, cid) {
  if (!cid) cid = null;
  fetch("api.php?action=assign_client", {
    method: "POST",
    body: JSON.stringify({ perfil_id: pid, client_id: cid }),
  });
}
function savePD(id) {
  const p = document.getElementById(`ep-${id}`).value,
    d = document.getElementById(`ed-${id}`).value;
  fetch("api.php?action=update_profile_details", {
    method: "POST",
    body: JSON.stringify({ id, pin: p, fecha: d }),
  })
    .then((r) => r.json())
    .then((res) => {
      if (res.success) {
        loadInventory();
        alert("Guardado");
      } else alert("Error");
    });
}
function toggleAR(id, chk) {
  fetch("api.php?action=toggle_auto_renew", {
    method: "POST",
    body: JSON.stringify({ id }),
  }).catch(() => (chk.checked = !chk.checked));
}
function renewProduct(id, n) {
  if (confirm(`¿Renovar ${n}?`))
    fetch("api.php?action=renew_my_product", {
      method: "POST",
      body: JSON.stringify({ id }),
    })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          alert("Renovado");
          loadInventory();
          loadDashboard();
        } else alert(res.message);
      });
}
function returnProfile(id) {
  if (confirm("¿Devolver al admin?"))
    fetch("api.php?action=return_profile_to_stock", {
      method: "POST",
      body: JSON.stringify({ id }),
    })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) loadInventory();
      });
}

// ==========================================
// 7. MONITOR
// ==========================================

function loadMonitor() {
  const q = document.getElementById("monitor-search")
    ? document.getElementById("monitor-search").value
    : "";
  const g = document.getElementById("monitor-grid");
  if (!g) return;
  if (!q && !g.hasChildNodes())
    g.innerHTML = '<p style="text-align:center;color:#aaa">Cargando...</p>';

  clearTimeout(monitorTimeout);
  monitorTimeout = setTimeout(() => {
    fetch("api.php?action=monitor_sales", {
      method: "POST",
      body: JSON.stringify({ search: q }),
    })
      .then((r) => r.json())
      .then((res) => {
        g.innerHTML = "";
        if (!res.success || !res.data || res.data.length === 0) {
          g.innerHTML =
            '<div style="grid-column:1/-1;text-align:center;padding:2rem;color:#aaa">Sin resultados</div>';
          return;
        }

        res.data.forEach((i) => {
          let ce = "#aaa",
            te = "Sin fecha",
            bg = "rgba(255,255,255,0.1)";
          if (i.fecha_vencimiento) {
            const d = Math.ceil(
              (new Date(i.fecha_vencimiento) - new Date()) / 864e5
            );
            if (d > 5) {
              ce = "#0f8";
              te = d + " días";
              bg = "rgba(0,255,136,0.1)";
            } else if (d >= 0) {
              ce = "#fb0";
              te = d + " días";
              bg = "rgba(255,187,0,0.15)";
            } else {
              ce = "#f05";
              te = "VENCIDO";
              bg = "rgba(255,0,85,0.15)";
            }
          }
          const safe = safeEncode(i);
          const wb = i.cliente_tel
            ? `<button class="action-btn success" onclick="cobrarWhatsapp('${i.cliente_tel}','${i.cliente_nombre}','${i.plataforma}')" style="flex:1">📲 Cobrar</button>`
            : "";

          g.innerHTML += `<div class="data-card"><div class="card-header"><div class="card-title" style="color:white">${i.cliente_nombre || "Sin Asignar"
            }</div><span class="card-badge" style="background:${bg};color:${ce};border:1px solid ${ce}">${te}</span></div><div class="card-body"><div class="data-row"><strong style="color:var(--secondary)">${i.plataforma
            }</strong></div><div class="data-row"><span style="font-size:0.8rem">${i.email_cuenta
            }</span></div><div class="data-row"><span>${i.nombre_perfil}</span>${i.pin_perfil
              ? `<span>PIN:<span style="color:#fff">${i.pin_perfil}</span></span>`
              : ""
            }</div></div><div class="card-actions">${wb}<button class="action-btn primary" onclick="viewCreds('${safe}')" style="width:60px">👁️</button><button class="action-btn danger" onclick="reportIssue(${i.id
            })" style="width:60px">⚠️</button></div></div>`;
        });
      });
  }, 300);
}

function openTelegramChannel() {
  Swal.fire({
    title: 'Solicitando Acceso...',
    text: 'Obteniendo enlace de invitación único.',
    background: '#111', color: '#fff',
    timer: 1500, // Simulamos una pequeña espera para que parezca que procesa
    timerProgressBar: true,
    didOpen: () => Swal.showLoading()
  }).then(() => {
    // Petición real al API
    fetch('api.php?action=get_telegram_link')
      .then(r => r.json())
      .then(d => {
        if (d.success && d.link && d.link !== '#') {
          // Abrir Telegram
          window.open(d.link, '_blank');
        } else {
          Swal.fire({
            title: 'Ups...',
            text: 'El enlace no está configurado aún. Contacta al admin.',
            icon: 'warning',
            background: '#111', color: '#fff'
          });
        }
      })
      .catch(() => {
        Swal.fire('Error', 'No se pudo conectar.', 'error');
      });
  });
}

// ABRIR MODAL Y GUARDAR ID TEMPORALMENTE
function reportIssue(id) {
  // 1. Limpiar campos anteriores
  document.getElementById('form-report').reset();

  // 2. INYECTAR EL ID EN EL INPUT OCULTO
  const hiddenInput = document.getElementById('report-pid');
  if (hiddenInput) {
    hiddenInput.value = id; // <--- ESTO ES CRUCIAL
  } else {
    console.error("No se encontró el input hidden 'report-pid'");
  }

  // 3. Abrir modal
  openModal('modal-report');
}

// ENVIAR EL FORMULARIO
const formRep = document.getElementById('form-report');
if (formRep) {
  formRep.addEventListener('submit', e => {
    e.preventDefault();

    const btn = formRep.querySelector('button');
    btn.textContent = "Enviando..."; btn.disabled = true;

    // Recogemos los datos DIRECTAMENTE de los inputs
    const pid = document.getElementById('report-pid').value;
    const msg = document.getElementById('report-msg').value;

    if (!pid) { alert("Error: No hay ID de perfil seleccionado."); return; }

    const fd = new FormData();
    fd.append('perfil_id', pid);
    fd.append('mensaje', msg);

    const file = document.getElementById('report-img').files[0];
    if (file) fd.append('imagen', file);

    fetch('api.php?action=report_issue', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          SwalSuccess("Reporte enviado correctamente.");
          closeModal('modal-report');
          loadReports();
        } else {
          SwalError(res.message);
        }
        btn.textContent = "Enviar Reporte"; btn.disabled = false;
      })
      .catch(err => {
        console.error(err);
        SwalError("Error de conexión");
        btn.textContent = "Enviar Reporte"; btn.disabled = false;
      });
  });
}
// Utilidades y Modales
// ABRIR NOTIFICACIONES
// ABRIR NOTIFICACIONES (VISUALIZACIÓN MEJORADA)
function openNotifications() {
  openModal('modal-notifications');

  // Apagar badge
  const b = document.getElementById('notif-badge');
  if (b) b.style.display = 'none';

  const c = document.getElementById('notification-list');
  c.innerHTML = '<p style="text-align:center; padding:20px; color:#aaa">Cargando...</p>';

  fetch('api.php?action=get_my_notifications').then(r => r.json()).then(d => {
    c.innerHTML = '';

    if (d.length === 0) {
      c.innerHTML = `<div style="text-align:center; padding:3rem 1rem; color:#666;">
                <div style="font-size:3rem; margin-bottom:10px; opacity:0.5;">📭</div>
                Sin mensajes nuevos.
            </div>`;
      return;
    }

    d.forEach(n => {
      // Estilos dinámicos según tipo (Global o Privado)
      const isGlobal = (n.reseller_id === null);
      const borderColor = isGlobal ? '#00c6ff' : '#e100ff';
      const bgColor = isGlobal ? 'rgba(0, 198, 255, 0.03)' : 'rgba(225, 0, 255, 0.03)';
      const title = isGlobal ? 'ANUNCIO GLOBAL 📢' : 'MENSAJE PRIVADO 💬';
      const badgeClass = isGlobal ? 'info' : 'warning'; // Clases para estilizar el badge si quisieras

      // Formatear fecha
      const dateObj = new Date(n.fecha);
      const dateStr = dateObj.toLocaleDateString() + ' ' + dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

      c.innerHTML += `
            <div style="
                background: ${bgColor}; 
                border-left: 3px solid ${borderColor}; 
                border-radius: 8px; 
                padding: 15px; 
                margin-bottom: 12px; 
                border-top: 1px solid rgba(255,255,255,0.05);
                border-right: 1px solid rgba(255,255,255,0.05);
                border-bottom: 1px solid rgba(255,255,255,0.05);
            ">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                    <strong style="color:${borderColor}; font-size:0.75rem; letter-spacing:1px;">${title}</strong>
                    <small style="color:#777; font-size:0.7rem;">${dateStr}</small>
                </div>
                
                <!-- Aquí aplicamos la clase CSS nueva -->
                <div class="notif-content-box">${n.mensaje}</div>
            </div>`;
    });
  });
}
function showToast(m) {
  console.log(m);
}
function copyText(id) {
  navigator.clipboard.writeText(document.getElementById(id).textContent);
}
function reportRecharge() {
  const a = prompt("Monto:");
  if (a)
    window.open(
      `https://wa.me/584123368325?text=${encodeURIComponent("Pago: " + a)}`
    );
}
function cobrarWhatsapp(t, n, p) {
  window.open(
    `https://wa.me/${t.replace(/\D/g, "")}?text=${encodeURIComponent(
      `Hola ${n}, vence tu ${p}.`
    )}`
  );
}

function viewCreds(b64) {
  const d = safeDecode(b64);
  let copyStr = `PLATAFORMA: ${d.plataforma}\n`;
  let html = `<div style="text-align:center;margin-bottom:20px"><strong style="font-size:1.4rem;color:var(--secondary)">${d.plataforma}</strong></div>`;

  // CASO 1: SOLICITUD DE ACTIVACIÓN (Canva, YouTube Familiar, etc)
  if (d.correo_a_activar) {
    const estado = d.estado_activacion === 'completado' ? '✅ YA ACTIVADO' : '⏳ ESPERANDO ACTIVACIÓN';
    const colorEst = d.estado_activacion === 'completado' ? '#00ff88' : '#ffbb00';

    copyStr += `ESTADO: ${d.estado_activacion === 'completado' ? 'Activo' : 'Pendiente'}\nCORREO REGISTRADO: ${d.correo_a_activar}\n`;

    html += `
            <div style="background:rgba(255,187,0,0.05); padding:15px; border-radius:8px; border-left:3px solid ${colorEst}; margin-bottom:15px;">
                <div style="font-size:0.75rem; color:${colorEst}; font-weight:bold; margin-bottom:5px;">${estado}</div>
                <div style="color:#ddd; font-size:0.9rem;">Solicitaste activar:</div>
                <div style="font-family:monospace; font-size:1.1rem; color:white; word-break:break-all;">${d.correo_a_activar}</div>
            </div>
            <p style="font-size:0.8rem; color:#aaa; text-align:center">El administrador te notificará cuando la invitación sea enviada.</p>
        `;
  }
  // CASO 2: ENLACE (Spotify)
  else if (d.pin_perfil === 'LINK') {
    copyStr += `ENLACE: ${d.email_cuenta}\n`;
    html += `<div style="background:rgba(0,255,136,0.1);padding:15px;border-radius:8px;border-left:3px solid #0f8;margin-bottom:10px;word-break:break-all;color:#0f8">${d.email_cuenta}</div><a href="${d.email_cuenta}" target="_blank" style="display:block;text-align:center;background:#0f8;color:#000;padding:10px;border-radius:20px;text-decoration:none;font-weight:bold">ABRIR LINK</a>`;
  }
  // CASO 3: CUENTA NORMAL (Netflix, Disney...)
  else {
    copyStr += `Usuario: ${d.email_cuenta}\nContraseña: ${d.password}\n`;
    html += `<div style="margin-bottom:10px"><span style="color:#888;font-size:0.8rem">USUARIO</span><div style="background:#111;padding:10px;border-radius:6px;font-family:monospace;color:#fff">${d.email_cuenta}</div></div>`;
    html += `<div style="margin-bottom:10px"><span style="color:#888;font-size:0.8rem">CLAVE</span><div style="background:#111;padding:10px;border-radius:6px;font-family:monospace;color:#00c6ff;font-weight:bold">${d.password}</div></div>`;

    if (d.nombre_perfil) { copyStr += `Perfil: ${d.nombre_perfil}\n`; html += `<div style="margin-top:10px">👤 ${d.nombre_perfil}</div>`; }
    if (d.pin_perfil && d.pin_perfil !== 'N/A') { copyStr += `PIN: ${d.pin_perfil}\n`; html += `<div style="margin-top:5px;color:#0f8">🔒 PIN: ${d.pin_perfil}</div>`; }
  }

  const v = d.fecha_vencimiento ? d.fecha_vencimiento.split(' ')[0] : '---';
  copyStr += `Vence: ${v}`;
  html += `<div style="margin-top:20px;text-align:center;font-size:0.8rem;color:#666">Vence: ${v}</div>`;

  document.getElementById('view-content').innerHTML = html;
  document.getElementById('view-content').setAttribute('data-copy', copyStr);
  openModal('modal-view');
}
function copyData() {
  navigator.clipboard
    .writeText(
      document.getElementById("view-content").getAttribute("data-copy")
    )
    .then(() => alert("Copiado"));
}

function openModal(id) {
  document.getElementById(id).style.display = "flex";
}
function closeModal(id) {
  document.getElementById(id).style.display = "none";
}
function logout() {
  fetch("api.php?action=logout").then(
    () => (window.location.href = "index.php")
  );
}

// Variable global para guardar los movimientos y poder filtrarlos sin recargar
let allMovements = [];

function loadWalletHistory() {
  const tbody = document.getElementById("wallet-history-body");
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#aaa;">Cargando historial...</td></tr>';

  fetch("api.php?action=get_wallet_history")
    .then((r) => r.json())
    .then((data) => {
      allMovements = data; // Guardamos en memoria
      renderWalletTable(data); // Renderizamos
    })
    .catch(err => {
      console.error(err);
      tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:red;">Error cargando datos</td></tr>';
    });
}

// FUNCIÓN DE RENDERIZADO (Dibuja la tabla)
function renderWalletTable(data) {
  const tbody = document.getElementById("wallet-history-body");
  tbody.innerHTML = "";

  if (data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#aaa;">Sin movimientos registrados.</td></tr>';
    return;
  }

  data.forEach((m) => {
    // Configuración visual según tipo
    let icon = "📄";
    let color = "white";
    let signo = "-";
    let typeLabel = "Operación";
    let rowBg = "";

    switch (m.tipo) {
      case 'deposito':
        icon = "💰";
        color = "#00ff88";
        signo = "+";
        typeLabel = "RECARGA APROBADA";
        rowBg = "background:rgba(0,255,136,0.05);";
        break;
      case 'compra':
        icon = "🛒";
        color = "#ffbb00"; // Naranja
        signo = "-";
        typeLabel = "COMPRA STOCK";
        break;
      case 'renovacion':
        icon = "↻";
        color = "#00c6ff"; // Azul
        signo = "-";
        typeLabel = "RENOVACIÓN";
        break;
      case 'ajuste_retiro':
        icon = "👮";
        color = "#ff0055"; // Rojo
        signo = "-";
        typeLabel = "AJUSTE ADMIN";
        break;
      case 'reembolso':
        icon = "↩️";
        color = "#e100ff"; // Morado
        signo = "+";
        typeLabel = "REEMBOLSO";
        break;
    }

    // Formato Fecha
    const dateObj = new Date(m.fecha);
    const fechaStr = dateObj.toLocaleDateString() + ' <br><span style="color:#666; font-size:0.75rem">' + dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '</span>';

    // Render HTML
    tbody.innerHTML += `
            <tr style="${rowBg} border-bottom:1px solid rgba(255,255,255,0.05);">
                <td style="font-size:0.85rem; color:#ddd; line-height:1.2;">${fechaStr}</td>
                <td style="padding:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="font-size:1.2rem;">${icon}</div>
                        <div>
                            <div style="font-size:0.8rem; font-weight:bold; color:${color}; opacity:0.9; letter-spacing:0.5px;">${typeLabel}</div>
                            <div style="font-size:0.9rem; color:#fff;">${m.descripcion}</div>
                        </div>
                    </div>
                </td>
                <td style="color:${color}; font-weight:bold; text-align:right; font-family:monospace; font-size:1.1rem;">
                    ${signo}$${parseFloat(m.monto).toFixed(2)}
                </td>
            </tr>`;
  });
}

// FUNCIÓN DE FILTRADO (Se activa al cambiar el select)
function filterWallet() {
  const tipo = document.getElementById("wallet-filter").value;

  if (tipo === 'all') {
    renderWalletTable(allMovements);
  } else {
    // Filtramos el array en memoria
    const filtered = allMovements.filter(m => m.tipo === tipo);
    renderWalletTable(filtered);
  }
}

// ==========================================
// 12. PERFIL DE USUARIO
// ==========================================

function loadProfile() {
  // Si no existe el formulario en el HTML (porque no han copiado el código PHP aun), salimos
  if (!document.getElementById("form-profile")) return;

  fetch("api.php?action=get_my_profile")
    .then((r) => r.json())
    .then((data) => {
      document.getElementById("prof-email").value = data.email;
      document.getElementById("prof-name").value = data.nombre;
      document.getElementById("prof-phone").value = data.telefono || "";
      document.getElementById("prof-id").value = data.cedula || "";

      // Decoración visual (Avatar con inicial)
      document.getElementById("profile-header-name").textContent = data.nombre;
      document.getElementById("profile-avatar").textContent = data.nombre
        .charAt(0)
        .toUpperCase();
    })
    .catch(console.error);
}

const formProf = document.getElementById("form-profile");
if (formProf) {
  formProf.addEventListener("submit", (e) => {
    e.preventDefault();

    const pass = document.getElementById("prof-pass").value;
    // Validación básica de contraseña
    if (pass.length > 0 && pass.length < 6) {
      return SwalError("La contraseña debe tener al menos 6 caracteres");
    }

    const data = {
      nombre: document.getElementById("prof-name").value,
      telefono: document.getElementById("prof-phone").value,
      cedula: document.getElementById("prof-id").value,
      password: pass, // Se envía vacío si no la cambió
    };

    // Feedback de carga
    const btn = formProf.querySelector("button");
    const txt = btn.textContent;
    btn.textContent = "Guardando...";
    btn.disabled = true;

    fetch("api.php?action=update_my_profile", {
      method: "POST",
      body: JSON.stringify(data),
    })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          SwalSuccess("Perfil actualizado correctamente");
          loadProfile(); // Refrescar visualmente
          document.getElementById("prof-pass").value = ""; // Limpiar campo pass
        } else {
          SwalError(res.message);
        }
        btn.textContent = txt;
        btn.disabled = false;
      })
      .catch(() => {
        btn.textContent = txt;
        btn.disabled = false;
        SwalError("Error de conexión");
      });
  });
}

// ==============================================================
// 4. FINANZAS Y TUTORIAL (NUEVO MÓDULO)
// ==============================================================

// ==========================================
// 4. FINANZAS Y TUTORIAL
// ==========================================

// ==========================================
// 9. FINANZAS Y TUTORIAL
// ==========================================

// FINANZAS (VERSIÓN SEGURA ANTI-CRASH)
function loadFinance() {
  const elGoal = document.getElementById('goal-display');

  // Si no encontramos la sección en el HTML, detenemos la función silenciosamente
  // Esto evita el error rojo en la consola
  if (!elGoal) return;

  fetch('api.php?action=get_finance_stats')
    .then(r => r.json())
    .then(d => {
      // Asignación segura
      elGoal.textContent = `$${d.meta}`;

      const elBar = document.getElementById('goal-bar');
      if (elBar) elBar.style.width = d.progreso_meta + '%';

      const elText = document.getElementById('goal-text');
      if (elText) elText.textContent = d.progreso_meta + '% COMPLETADO';

      const elProfit = document.getElementById('current-profit');
      if (elProfit) elProfit.textContent = `$${d.ganancia}`;

      // Stats
      const elInvest = document.getElementById('fin-invest');
      if (elInvest) elInvest.textContent = `$${d.inversion}`;

      const elIncome = document.getElementById('fin-income');
      if (elIncome) elIncome.textContent = `$${d.ingresos}`;

      const elNet = document.getElementById('fin-profit');
      if (elNet) elNet.textContent = `$${d.ganancia}`;

      // Renderizar Tabla de Precios (NUEVO DISEÑO)
      const pGrid = document.getElementById('finance-prices-grid');

      // Cambiamos la clase del contenedor para usar el grid nuevo
      pGrid.className = 'prices-grid';

      if (pGrid && d.tabla_precios) {
        pGrid.innerHTML = '';

        d.tabla_precios.forEach(p => {
          const costo = parseFloat(p.costo);
          const venta = parseFloat(p.venta);
          const ganancia = venta - costo;

          // Determinar estilo inicial del badge de ganancia
          let profitClass = 'profit-neutral';
          let profitText = '$0.00';

          if (ganancia > 0) {
            profitClass = 'profit-positive';
            profitText = `+$${ganancia.toFixed(2)} Ganancia`;
          } else if (ganancia < 0) {
            profitClass = 'profit-negative';
            profitText = `-$${Math.abs(ganancia).toFixed(2)} Pérdida`;
          }

          pGrid.innerHTML += `
                <div class="price-card-v2">
                    <!-- Cabecera: Logo y Nombre -->
                    <div class="pc-header">
                        <img src="${getLogo(p.plataforma)}" style="width:24px; height:24px; border-radius:4px;">
                        <span style="font-weight:600; font-size:0.85rem; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">${p.plataforma}</span>
                    </div>

                    <!-- Costo Real -->
                    <div class="pc-cost">
                        Costo Real: $${costo.toFixed(2)}
                    </div>

                    <!-- Input de Venta -->
                    <div class="pc-input-group">
                        <span class="pc-symbol">$</span>
                        <input type="number" step="0.1" 
                               value="${venta}" 
                               class="pc-input"
                               id="input-price-${safeEncode(p.plataforma)}"
                               onkeyup="updateProfitCalc(this, ${costo}, '${safeEncode(p.plataforma)}')"
                               onchange="saveMyPrice('${p.plataforma}', this.value)">
                    </div>

                    <!-- Badge de Ganancia Dinámica -->
                    <div id="profit-badge-${safeEncode(p.plataforma)}" class="profit-badge ${profitClass}">
                        ${profitText}
                    </div>
                </div>`;
        });
      }
    })
    .catch(console.error);
}

function updateProfitCalc(input, costo, idElemento) {
    const venta = parseFloat(input.value) || 0;
    const ganancia = venta - costo;
    const badge = document.getElementById(`profit-badge-${idElemento}`);

    // Limpiar clases
    badge.classList.remove('profit-positive', 'profit-neutral', 'profit-negative');

    if (ganancia > 0.01) {
        badge.classList.add('profit-positive');
        badge.textContent = `+$${ganancia.toFixed(2)} Ganancia`;
    } else if (ganancia < -0.01) {
        badge.classList.add('profit-negative');
        badge.textContent = `-$${Math.abs(ganancia).toFixed(2)} Pérdida`;
    } else {
        badge.classList.add('profit-neutral');
        badge.textContent = `Sin Ganancia`;
    }
}

function saveResellerPrice(plat, inputId) {
  const val = document.getElementById(inputId).value;
  fetch("api.php?action=save_my_price", {
    method: "POST",
    body: JSON.stringify({ plataforma: plat, precio: val }),
  }).then(() => {
    loadFinance();
    SwalSuccess("Precio guardado");
  });
}

function editGoal() {
  // AQUÍ ES DONDE DABA EL ERROR. AHORA FUNCIONARÁ.
  SwalPrompt("Define tu meta mensual ($):").then((r) => {
    if (r.isConfirmed && r.value) {
      fetch("api.php?action=update_goal", {
        method: "POST",
        body: JSON.stringify({ meta: r.value }),
      }).then(() => loadFinance());
    }
  });
}

// TUTORIAL
function checkTutorial() {
  fetch("api.php?action=check_tutorial")
    .then((r) => r.json())
    .then((d) => {
      if (d.visto == 0) {
        document.getElementById("tutorial-overlay").style.display = "flex";
        renderStep(0);
      }
    });
}
function renderStep(idx) {
  currentStep = idx;
  const s = tourData[idx];
  document.getElementById("tut-icon").textContent = s.i;
  document.getElementById("tut-title").innerHTML = s.t;
  document.getElementById("tut-desc").innerHTML = s.d;
  document.getElementById("tut-btn-back").style.display =
    idx === 0 ? "none" : "block";
  document.getElementById("tut-btn-next").textContent =
    idx === tourData.length - 1 ? "¡Comenzar!" : "Siguiente";
  document.querySelectorAll(".dot").forEach((d, i) => {
    d.className = i === idx ? "dot active" : "dot";
  });
}
function nextStep() {
  if (currentStep < tourData.length - 1) renderStep(currentStep + 1);
  else {
    fetch("api.php?action=finish_tutorial");
    document.getElementById("tutorial-overlay").style.opacity = "0";
    setTimeout(
      () =>
        (document.getElementById("tutorial-overlay").style.display = "none"),
      500
    );
    SwalSuccess("¡Configuración lista!");
  }
}
function prevStep() {
  if (currentStep > 0) renderStep(currentStep - 1);
}

// ============================================================
// FUNCIONES AUXILIARES FALTANTES (PEGA ESTO AL FINAL DEL ARCHIVO)
// ============================================================

// 1. Notificaciones Flotantes (Toasts) para tiempo real
function showToast(message, type = 'success') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  const icon = type === 'success' ? '✅' : 'ℹ️';
  toast.innerHTML = `<span>${icon}</span> <div>${message}</div>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}


// ==========================================
// 5. CARGAR REPORTES (FALLOS DE CUENTAS)
// ==========================================
function loadReports() {
  const container = document.getElementById('reports-grid'); // Ojo al ID nuevo
  if (!container) return;

  fetch('api.php?action=get_my_reports')
    .then(r => r.json())
    .then(data => {
      container.innerHTML = '';

      if (data.length === 0) {
        container.innerHTML = '<p class="text-muted" style="text-align:center; padding:20px;">No has reportado fallos.</p>';
        return;
      }

      data.forEach(r => {
        // Estilos de estado
        let estadoHtml = '';
        let borderLeft = '#aaa';

        if (r.estado === 'pendiente') {
          estadoHtml = `<span style="background:rgba(255, 187, 0, 0.15); color:#ffbb00; padding:4px 10px; border-radius:10px; font-size:0.8rem; border:1px solid #ffbb00">⏳ Pendiente</span>`;
          borderLeft = '#ffbb00';
        } else {
          estadoHtml = `<span style="background:rgba(0, 255, 136, 0.15); color:#00ff88; padding:4px 10px; border-radius:10px; font-size:0.8rem; border:1px solid #00ff88">✅ Solucionado</span>`;
          borderLeft = '#00ff88';
        }

        // Datos de la cuenta afectada
        const cuentaInfo = r.plataforma
          ? `<strong style="color:white; font-size:1.1rem">${r.plataforma}</strong>`
          : `<span style="color:#666; font-style:italic">Cuenta Eliminada</span>`;

        const emailInfo = r.email_cuenta ? `<div style="font-size:0.85rem; color:#aaa">${r.email_cuenta}</div>` : '';

        // Fecha
        const fecha = new Date(r.fecha).toLocaleString();

        container.innerHTML += `
            <div class="data-card" style="border-left: 4px solid ${borderLeft};">
                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:10px;">
                    <div>
                        ${cuentaInfo}
                        ${emailInfo}
                    </div>
                    ${estadoHtml}
                </div>
                
                <div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; margin-bottom:5px;">
                    <span style="color:#00c6ff; font-size:0.75rem; display:block; margin-bottom:3px;">TU REPORTE:</span>
                    <span style="color:#eee; font-size:0.95rem; line-height:1.4;">${r.mensaje}</span>
                </div>
                
                <div style="text-align:right; font-size:0.75rem; color:#666; margin-top:5px;">
                    Reportado el: ${fecha}
                </div>
            </div>`;
      });
    });
}

// ==========================================
// 7. CARGAR FEEDBACK (BUGS Y MEJORAS)
// ==========================================
function loadFeedback() {
  const container = document.getElementById('feedback-list');
  if (!container) return;

  fetch('api.php?action=get_my_feedback')
    .then(r => r.json())
    .then(data => {
      container.innerHTML = '';
      if (data.length === 0) {
        container.innerHTML = '<p class="text-muted" style="text-align:center; padding:20px;">Buzón vacío.</p>';
        return;
      }

      data.forEach(f => {
        const isBug = f.tipo === 'bug';
        const icon = isBug ? '🐛 BUG' : '✨ IDEA';
        const color = isBug ? '#ff0055' : '#e100ff';
        const fecha = new Date(f.fecha).toLocaleDateString();

        container.innerHTML += `
            <div class="data-card" style="border-left: 4px solid ${color};">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <strong style="color:${color}">${icon}</strong>
                    <small style="color:#888">${fecha}</small>
                </div>
                <div style="color:#ddd; font-size:0.95rem; line-height:1.5; white-space: pre-wrap;">${f.mensaje}</div>
            </div>`;
      });
    });
}

// ==========================================
// MÓDULO DE REPORTE DE PAGOS
// ==========================================
let currentTasaVal = 0; // Para la calculadora

function openPaymentModal() {
  fetch('api.php?action=get_tasa').then(r => r.json()).then(d => {
    currentTasaVal = parseFloat(d.tasa);
    document.getElementById('calc-tasa').textContent = currentTasaVal.toFixed(2);
  });

  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  document.getElementById('pay-date').value = now.toISOString().slice(0, 16);

  // Resetear campos
  document.getElementById('pay-amount-final').value = '';
  document.getElementById('display-bs').textContent = '0.00';
  togglePayFields(); // Ajustar visibilidad inicial

  openModal('modal-report-payment');
}

// 2. MOSTRAR/OCULTAR CALCULADORA (Visual solamente)
function togglePayFields() {
  const method = document.getElementById('pay-method').value;
  const displayInfo = document.getElementById('calc-display');

  // Si es pago móvil, mostramos la conversión a Bs. Si no, solo el input USD normal.
  if (method === 'Pago Movil') {
    displayInfo.style.display = 'block';
    calculateBs(); // Recalcular por si ya había numero
  } else {
    displayInfo.style.display = 'none';
  }
}
// 3. CALCULAR BS EN TIEMPO REAL
function calculateBs() {
  const usd = parseFloat(document.getElementById('pay-amount-final').value) || 0;
  const bs = usd * currentTasaVal;
  document.getElementById('display-bs').textContent = bs.toFixed(2);
}

// EN panel.js

document.getElementById('form-payment-proof')?.addEventListener('submit', e => {
  e.preventDefault();

  const btn = e.target.querySelector('button');
  const txt = btn.textContent;

  // 1. OBTENER EL ELEMENTO INPUT (BUSCAMOS EL NUEVO ID)
  const inputEl = document.getElementById('pay-amount-final');

  // Diagnóstico: Si esto sale null en la consola, no actualizaste el HTML o el ID está mal escrito
  if (!inputEl) {
    alert("Error Crítico: No encuentro el campo de monto 'pay-amount-final'. Borra caché (Ctrl+F5).");
    return;
  }

  const valorTexto = inputEl.value;
  const monto = parseFloat(valorTexto);

  // LOG PARA VER QUÉ ESTAMOS ENVIANDO (Presiona F12 -> Console)
  console.log("Intentando enviar monto:", valorTexto, "Parseado:", monto);

  // 2. VALIDACIÓN ESTRICTA
  if (!valorTexto || isNaN(monto) || monto <= 0) {
    SwalError(`Por favor ingresa un monto válido. (Leí: "${valorTexto}")`);
    return;
  }

  btn.textContent = "Subiendo...";
  btn.disabled = true;

  const fd = new FormData();
  fd.append('metodo', document.getElementById('pay-method').value);
  fd.append('monto', monto); // <--- AQUÍ ENVIAMOS EL NÚMERO YA VALIDADO
  fd.append('nombre_titular', document.getElementById('pay-name').value);
  fd.append('correo_titular', document.getElementById('pay-email').value);
  fd.append('fecha_pago', document.getElementById('pay-date').value);
  fd.append('referencia', document.getElementById('pay-ref').value);
  fd.append('comprobante', document.getElementById('pay-img').files[0]);

  fetch('api.php?action=submit_recharge_proof', { method: 'POST', body: fd })
    .then(r => r.json()).then(res => {
      if (res.success) {
        SwalSuccess("Pago reportado correctamente.");
        closeModal('modal-report-payment');
        e.target.reset();
      } else {
        SwalError(res.message);
      }
      btn.disabled = false; btn.textContent = txt;
    })
    .catch(err => {
      console.error(err);
      SwalError("Error de conexión.");
      btn.disabled = false; btn.textContent = txt;
    });
});

// EXTRACCIÓN DE CÓDIGO (LADO RESELLER)
function getResellerCode(id) {
  Swal.fire({
    title: 'Consultando...',
    text: 'Conectando con el proveedor...',
    background: '#111', color: '#fff',
    didOpen: () => Swal.showLoading()
  });

  fetch(`api.php?action=extract_code_reseller&id=${id}`)
    .then(r => r.json())
    .then(res => {
      // Lógica idéntica al admin
      if (res.success && res.html) {
        Swal.fire({
          title: '📧 Buzón de Entrada',
          html: `
                        <div style="width:100%; height:400px; background:white; border-radius:8px; overflow:hidden;">
                            <iframe id="reseller-visor" style="width:100%; height:100%; border:none;"></iframe>
                        </div>
                    `,
          width: '600px',
          background: '#111', color: '#fff',
          showConfirmButton: true, confirmButtonText: 'Cerrar',
          didOpen: () => {
            const iframe = document.getElementById('reseller-visor');
            const doc = iframe.contentWindow.document;
            doc.open(); doc.write(res.html); doc.close();
          }
        });
      } else if (res.message) {
        Swal.fire({ title: 'Estado', text: res.message, icon: 'info', background: '#111', color: '#fff' });
      } else {
        Swal.fire({ title: 'Error', text: 'Respuesta vacía o error de conexión.', icon: 'error', background: '#111', color: '#fff' });
      }
    })
    .catch(err => {
      console.error(err);
      Swal.fire('Error', 'Fallo técnico al conectar.', 'error');
    });
}

// ABRIR/CERRAR MENÚ INDIVIDUAL
function toggleCardMenu(id) {
  // 1. Cerrar cualquier otro abierto primero
  document.querySelectorAll('.action-dropdown').forEach(d => {
    if (d.id !== `menu-${id}`) d.classList.remove('show');
  });
  document.querySelectorAll('.menu-toggle-btn').forEach(b => b.classList.remove('active'));

  // 2. Abrir el actual
  const menu = document.getElementById(`menu-${id}`);
  const btn = event.currentTarget; // El botón que se presionó

  if (menu.classList.contains('show')) {
    menu.classList.remove('show');
    btn.classList.remove('active');
  } else {
    menu.classList.add('show');
    btn.classList.add('active');
  }

  // Evitar que el clic cierre inmediatamente el menú
  event.stopPropagation();
}

// CERRAR MENÚ AL CLICKEAR AFUERA (UX)
document.addEventListener('click', function (e) {
  if (!e.target.closest('.card-menu-container')) {
    document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('show'));
    document.querySelectorAll('.menu-toggle-btn').forEach(b => b.classList.remove('active'));
  }
});