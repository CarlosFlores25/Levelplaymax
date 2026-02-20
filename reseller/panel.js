// CSRF token wrapper for all fetch calls (requires token injected by panel.php)
(function () {
  const meta = document.querySelector('meta[name="csrf-token"]');
  const token = (meta && meta.content) ? meta.content : '';
  if (token) {
    const _fetch = window.fetch;
    window.fetch = function (input, init) {
      init = init || {};
      init.headers = init.headers || {};
      if (typeof init.headers === 'object') {
        init.headers['X-CSRF-Token'] = token;
      }
      return _fetch(input, init);
    };
  }
})();

document.addEventListener("DOMContentLoaded", () => {
  console.log("Iniciando Panel Partner Remastered...");
  loadAll();

  // Preloader Logic
  setTimeout(() => {
    const loader = document.getElementById("preloader");
    if (loader) {
      loader.classList.add("hide");
      setTimeout(() => loader.remove(), 1000);
    }
  }, 800);

  setInterval(resellerRealTime, 15000);
});

// WRAPPERS OF SWEETALERT2
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

// GLOBAL VARS
let currentFilter = "all";
let allInventoryData = [];
let myClients = [];
let lastBalance = null;
let maxMsgId = 0;
let isFirstRun = true;

function loadAll() {
  loadDashboard();
  loadTasa();
  loadClients();
}

function showTab(id) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));

  const tab = document.getElementById(id);
  if (tab) tab.classList.add('active');

  // Highlight nav button logic
  document.querySelectorAll(`.nav-btn[onclick="showTab('${id}')"]`).forEach(btn => btn.classList.add('active'));

  const menu = document.getElementById('mobile-menu-overlay');
  if (menu) menu.classList.remove('open');

  switch (id) {
    case 'dashboard': loadDashboard(); break;
    case 'stock': loadStock(); break;
    case 'inventory': loadInventory(); break;
    case 'clients': loadClients(); break;
    case 'monitor': loadMonitor(); break;
    case 'pagos': loadWalletHistory(); break;
    case 'reportes': loadReports(); break;
    case 'feedback': loadFeedback(); break;
    case 'perfil': loadProfile(); break;
    case 'finanzas': loadFinance(); break;
  }
}

// UTILS
function safeEncode(obj) { return btoa(encodeURIComponent(JSON.stringify(obj))); }
function safeDecode(str) { return JSON.parse(decodeURIComponent(atob(str))); }

function getLogo(name) {
  if (!name) return "../img/logo.png";
  const n = name.toLowerCase();
  const path = '../img/';

  if (n.includes('netflix')) return path + 'netflix.png';
  if (n.includes('disney')) return path + 'disney.png';
  if (n.includes('hbo')) return path + 'hbo.png';
  if (n.includes('amazon') || n.includes('primevideo')) return path + 'prime.png';
  if (n.includes('crunchyroll')) return path + 'crunchyroll.png';
  if (n.includes('paramount')) return path + 'paramount.png';
  if (n.includes('vix')) return path + 'vix.png';
  if (n.includes('plex')) return path + 'plex.png';
  if (n.includes('viki')) return path + 'viki.png';

  if (n.includes('canva')) return path + 'canva.png';
  if (n.includes('gemini')) return path + 'gemini.png';
  if (n.includes('gpt')) return path + 'gpt.png';
  if (n.includes('capcut')) return path + 'capcut.png';

  if (n.includes('youtube')) return path + 'youtube.png';
  if (n.includes('spotify')) return path + 'spotify.png';
  if (n.includes('deezer')) return path + 'deezer.png';
  if (n.includes('soundcloud')) return path + 'soundcloud.png';

  if (n.includes('xbox')) return path + 'xbox.png';
  if (n.includes('discord')) return path + 'discord.png';
  if (n.includes('psplus') || n.includes('deluxe')) return path + 'psplus.png';

  return path + 'logo.png';
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
  fetch("api.php?action=get_tasa").then(r => r.json()).then(d => {
    const el = document.getElementById("live-tasa-bs");
    if (el) el.textContent = parseFloat(d.tasa).toFixed(2);
  });
}

// --- REAL TIME ---
function resellerRealTime() {
  fetch("api.php?action=get_dashboard").then((r) => r.json()).then((d) => {
    if (d.error) return;
    const currentBalance = parseFloat(d.saldo);
    if (lastBalance !== null && currentBalance > lastBalance && !isFirstRun) {
      SwalSuccess(`💰 Recarga Recibida: +$${(currentBalance - lastBalance).toFixed(2)}`);
      loadDashboard();
      playNotificationSound();
    }
    lastBalance = currentBalance;
  }).catch(() => { });

  fetch("api.php?action=get_my_notifications").then((r) => r.json()).then((data) => {
    const badge = document.getElementById("notif-badge");
    if (data.length > 0) {
      const ids = data.map((n) => parseInt(n.id));
      const currentMax = Math.max(...ids);
      if (currentMax > maxMsgId) {
        if (!isFirstRun) {
          playNotificationSound();
        }
        if (badge) badge.style.display = "block";
        maxMsgId = currentMax;
      }
    }
    isFirstRun = false;
  }).catch((e) => console.log(e));
}

function playNotificationSound() {
  // const audio = document.getElementById("audio-notif");
  // if (audio) audio.play().catch(() => { });
  // Audio disabled: file missing
}

// --- STOCK ---
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

      if (i.tiene_descuento) {
        precioHtml = `
             <div style="display:flex; flex-direction:column; align-items:center;">
                  <span style="text-decoration:line-through; color:var(--text-muted); font-size:0.8rem;">$${i.precio_original}</span>
                  <div class="stock-price" style="color:var(--secondary); text-shadow:0 0 10px rgba(0,255,136,0.2);">
                      $${i.precio_reseller} <span style="font-size:0.7rem; background:var(--secondary); color:black; padding:2px 4px; border-radius:4px; vertical-align:middle;">-${i.porcentaje_off}%</span>
                  </div>
              </div>`;
      } else {
        precioHtml = `<div class="stock-price">$${i.precio_reseller}</div>`;
      }

      // Lógica según tipo de entrega
      if (i.tipo_entrega === 'manual') {
        statusHtml = `<div style="color:var(--primary); font-size:0.8rem; margin-bottom:10px;">DISPONIBLE</div>`;
        btnHtml = `<button class="buy-btn" onclick="requestManualProduct('${i.plataforma}', '${i.precio_reseller}')">Comprar</button>`;
      } else {
        if (i.disponibles > 0) {
          btnHtml = `<button class="buy-btn" onclick="buyProduct('${i.plataforma}', ${i.precio_reseller})">Comprar</button>`;
        } else {
          statusHtml = `<div style="color:var(--text-muted); font-size:0.8rem; margin-bottom:10px;">AGOTADO</div>`;
          btnHtml = `<button class="buy-btn" disabled style="background:var(--card-bg); color:#555; cursor:not-allowed; box-shadow:none; border:1px solid #333;">Sin Stock</button>`;
          cardStyle = 'opacity: 0.6; filter: grayscale(1);';
        }
      }

      grid.innerHTML += `
              <div class="stock-item" style="${cardStyle}">
                  ${img}
                  <div class="info-col">
                      <div class="stock-name">${i.plataforma}</div>
                      ${precioHtml}
                      ${statusHtml}
                  </div>
                  ${btnHtml}
              </div>`;
    });
  });
}

function buyProduct(plat, price) {
  // Check for email requirement
  const esActivacion = plat.toLowerCase().includes('canva') || plat.toLowerCase().includes('youtube');

  if (esActivacion) {
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
    SwalConfirm(`¿Comprar ${plat} por $${price}?`, () => {
      executePurchase(plat, null);
    });
  }
}

function executePurchase(plat, correoActivar) {
  const data = { plataforma: plat, correo_activar: correoActivar };
  fetch('api.php?action=buy_product', { method: 'POST', body: JSON.stringify(data) })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        SwalSuccess("¡Compra Exitosa!");
        loadAll();
        // Abrir ticket si disponible
        if (res.id_perfil) {
          window.open(`ticket.php?id=${res.id_perfil}`, '_blank');
        }
        // Mostrar referencia de Pedido Web si se creó
        if (typeof res.pedido_id !== 'undefined' && res.pedido_id) {
          Swal.fire({
            title: 'Pedido Web Creado',
            text: `Pedido #${res.pedido_id} generado para revisión por admin`,
            icon: 'info',
            timer: 2500,
            showConfirmButton: false,
            background: '#111', color: '#fff'
          });
        }
      } else {
        SwalError(res.message);
      }
    })
    .catch(e => SwalError("Error de conexión"));
}

function requestManualProduct(plat, price) {
  // En lugar de abrir WhatsApp, tramitar pedido de entrega manual vía API interna
  const data = { plataforma: plat, precio: price, correo_activar: null };
  fetch('api.php?action=buy_product', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data)
  })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        SwalSuccess("Pedido generado. Revisa Pedidos Web en Admin para aprobación.");
        if (typeof res.pedido_id !== 'undefined' && res.pedido_id) {
          console.info('Pedido Web generado:', res.pedido_id);
        }
        if (typeof res.whatsapp_url !== 'undefined' && res.whatsapp_url) {
          let waArea = document.getElementById('wa-notif-area');
          if (!waArea) {
            waArea = document.createElement('div');
            waArea.id = 'wa-notif-area';
            waArea.style.position = 'fixed';
            waArea.style.bottom = '16px';
            waArea.style.right = '16px';
            waArea.style.zIndex = '1000';
            waArea.style.padding = '8px';
            waArea.style.background = '#111';
            waArea.style.borderRadius = '8px';
            waArea.style.boxShadow = '0 2px 8px rgba(0,0,0,.3)';
            document.body.appendChild(waArea);
          }
          waArea.innerHTML = `<a href="${res.whatsapp_url}" target="_blank" class="btn" style="color:#fff; text-decoration:none; background:#25D366; padding:8px 12px; border-radius:6px;">Notificar por WhatsApp</a>`;
        }
      } else {
        SwalError(res.message || "Error al crear pedido");
      }
    })
    .catch(() => SwalError("Error de conexión"));
}

// --- CLIENTS MANAGEMENT ---

let allClientsData = []; // Variable para búsqueda

function loadClients() {
  fetch('api.php?action=list_my_clients')
    .then(r => r.json())
    .then(data => {
      if (!Array.isArray(data)) data = [];
      allClientsData = data; // Guardar en memoria
      renderClients(allClientsData);

      // Actualizar selects en otras pestañas si es necesario
      myClients = data;
    })
    .catch(console.error);
}

function filterClients() {
  const term = document.getElementById('search-clients').value.toLowerCase().trim();

  if (term === '') {
    renderClients(allClientsData);
    return;
  }

  const filtered = allClientsData.filter(c =>
    c.nombre.toLowerCase().includes(term) ||
    c.telefono.includes(term)
  );
  renderClients(filtered);
}

function renderClients(list) {
  const grid = document.getElementById('clients-grid');
  if (!grid) return;

  grid.innerHTML = '';
  if (list.length === 0) {
    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#aaa; padding:2rem;">No hay clientes registrados.</div>';
    return;
  }

  list.forEach(c => {
    const iniciales = c.nombre.substring(0, 2).toUpperCase();
    const safeName = safeEncode(c.nombre);
    const safePhone = safeEncode(c.telefono);
    const safeNote = safeEncode(c.nota_interna || '');

    // Formatear teléfono para WhatsApp (Lógica Venezuela)
    let cleanPhone = c.telefono.replace(/\D/g, '');
    if (cleanPhone.startsWith('04')) cleanPhone = '58' + cleanPhone.substring(1);
    else if (cleanPhone.length === 10) cleanPhone = '58' + cleanPhone;

    // Badge de servicios
    let badgeServicios = '';
    if (c.servicios_activos > 0) {
      badgeServicios = `<span style="background:var(--secondary); color:#000; font-size:0.7rem; padding:2px 6px; border-radius:10px; font-weight:bold;">${c.servicios_activos} Activas</span>`;
    } else {
      badgeServicios = `<span style="background:#444; color:#aaa; font-size:0.7rem; padding:2px 6px; border-radius:10px;">Inactivo</span>`;
    }

    grid.innerHTML += `
            <div class="glass-card" style="padding:0; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between;">
                
                <div style="padding:15px; display:flex; align-items:center; gap:15px;">
                    <div style="width:50px; height:50px; background:linear-gradient(135deg, var(--primary), #000); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:1.2rem; color:#fff; border:1px solid rgba(255,255,255,0.2);">
                        ${iniciales}
                    </div>
                    <div>
                        <div style="font-weight:bold; font-size:1.1rem; color:#fff;">${c.nombre}</div>
                        <div style="color:var(--text-muted); font-size:0.9rem;">${c.telefono}</div>
                        <div style="margin-top:5px;">${badgeServicios}</div>
                    </div>
                </div>

                ${c.nota_interna ? `<div style="padding:0 15px 10px; font-size:0.8rem; color:#aaa; font-style:italic;">"${c.nota_interna}"</div>` : ''}

                <div style="display:flex; border-top:1px solid rgba(255,255,255,0.1);">
                    <a href="https://wa.me/${cleanPhone}" target="_blank" class="icon-btn" style="flex:1; border-radius:0; border-right:1px solid rgba(255,255,255,0.1); color:#25D366; display:flex; justify-content:center; align-items:center; text-decoration:none;">
                         Chat
                    </a>
                    <button class="icon-btn" onclick="openEditClient(${c.id}, '${safeName}', '${safePhone}', '${safeNote}')" style="flex:1; border-radius:0; border-right:1px solid rgba(255,255,255,0.1); color:#fff;">
                        ✏️ Editar
                    </button>
                    <button class="icon-btn" onclick="deleteClient(${c.id}, '${safeName}', ${c.servicios_activos})" style="flex:1; border-radius:0; color:var(--danger);">
                        🗑️ Borrar
                    </button>
                </div>
            </div>`;
  });
}

function deleteClient(id, encName, activeServices) {
  const name = safeDecode(encName);

  let warningMsg = `¿Eliminar a <b>${name}</b>?`;
  if (activeServices > 0) {
    warningMsg += `<br><br><span style="color:var(--warning)">⚠️ ADVERTENCIA: Este cliente tiene <b>${activeServices} cuentas asignadas</b>. Si lo borras, esas cuentas quedarán 'Libres' en tu inventario.</span>`;
  }

  Swal.fire({
    title: '¿Estás seguro?',
    html: warningMsg,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, borrar',
    background: '#111', color: '#fff'
  }).then((result) => {
    if (result.isConfirmed) {
      fetch('api.php?action=delete_client', {
        method: 'POST',
        body: JSON.stringify({ id: id })
      }).then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess("Cliente eliminado");
          loadClients();
          loadInventory(); // Para refrescar los selects
        } else {
          SwalError("Error al eliminar");
        }
      });
    }
  });
}

// Function to handle client form submit
const fc = document.getElementById('form-client');
if (fc) {
  fc.addEventListener('submit', e => {
    e.preventDefault();
    const d = {
      nombre: document.getElementById('cli-name').value,
      telefono: document.getElementById('cli-phone').value,
      nota: document.getElementById('cli-note').value
    };
    fetch('api.php?action=add_my_client', { method: 'POST', body: JSON.stringify(d) })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          document.getElementById('modal-client').style.display = 'none';
          loadClients();
          fc.reset();
          SwalSuccess("Cliente Guardado");
        } else {
          SwalError(res.message);
        }
      });
  });
}

function openEditClient(id, encN, encT, encNote) {
  const name = safeDecode(encN);
  const phone = safeDecode(encT);
  const note = safeDecode(encNote);

  Swal.fire({
    title: 'Editar Cliente',
    html: `
            <input id="swal-input1" class="swal2-input" placeholder="Nombre" value="${name}">
            <input id="swal-input2" class="swal2-input" placeholder="Teléfono" value="${phone}">
            <input id="swal-input3" class="swal2-input" placeholder="Nota" value="${note}">
          `,
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Guardar',
    background: '#111', color: '#fff', confirmButtonColor: '#00c6ff',
    preConfirm: () => {
      return [
        document.getElementById('swal-input1').value,
        document.getElementById('swal-input2').value,
        document.getElementById('swal-input3').value
      ]
    }
  }).then((result) => {
    if (result.isConfirmed) {
      const [newName, newPhone, newNote] = result.value;
      const data = { id: id, nombre: newName, telefono: newPhone, nota: newNote };
      fetch('api.php?action=edit_my_client', { method: 'POST', body: JSON.stringify(data) })
        .then(r => r.json()).then(res => {
          if (res.success) { loadClients(); SwalSuccess("Actualizado"); }
          else SwalError("Error");
        });
    }
  });
}

// --- SISTEMA DE INVENTARIO Y BÚSQUEDA ---

function loadInventory() {
  // 1. Descargamos los datos y los guardamos en la variable global
  fetch("api.php?action=my_inventory").then((r) => r.json()).then((d) => {
    if (!Array.isArray(d)) d = [];

    allInventoryData = d; // Guardamos en memoria
    renderInventory(allInventoryData); // Dibujamos todo
  });
}

function setFilter(type, btn) {
  // 1. Quitar clase active de todos los botones
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  // 2. Activar el botón presionado
  btn.classList.add('active');

  currentFilter = type;
  filterInventory(); // Ejecutar filtro
}

function filterInventory() {
  const term = document.getElementById('search-inventory').value.toLowerCase().trim();
  const hoy = new Date();

  const filtered = allInventoryData.filter(item => {
    // --- 1. DETECTAR SI ES MAESTRA O PERFIL ---
    const nombrePerfil = (item.nombre_perfil || '').toLowerCase();
    const plataforma = (item.plataforma || '').toLowerCase();
    const esMaster = nombrePerfil.includes('completa') ||
      nombrePerfil.includes('cuenta') ||
      plataforma.includes('completa');

    // --- 2. APLICAR FILTRO DE BOTONES ---
    if (currentFilter === 'master' && !esMaster) return false;   // Solo quiero maestras
    if (currentFilter === 'profile' && esMaster) return false;   // Solo quiero perfiles

    if (currentFilter === 'soon') {
      // Lógica de "Por Vencer" (5 días o menos)
      const fechaRef = item.fecha_corte_cliente ? item.fecha_corte_cliente : item.fecha_vencimiento;
      const diff = Math.ceil((new Date(fechaRef) - hoy) / 864e5);
      if (diff > 5 || diff < 0) return false; // Solo mostramos entre 0 y 5 días
    }

    // --- 3. APLICAR BUSCADOR DE TEXTO ---
    if (term === '') return true; // Si no hay texto, pasa

    return plataforma.includes(term) ||
      (item.email_cuenta || '').toLowerCase().includes(term) ||
      nombrePerfil.includes(term) ||
      (item.cliente_final || '').toLowerCase().includes(term);
  });

  renderInventory(filtered);
}

function renderInventory(dataList) {
  const grid = document.getElementById("inventory-grid");
  if (!grid) return;
  grid.innerHTML = "";

  if (dataList.length === 0) {
    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#aaa; padding:2rem;">No se encontraron cuentas.</div>';
    return;
  }

  // --- LÓGICA DE AGRUPACIÓN (IGUAL QUE ANTES) ---
  const singles = [];
  const masters = {};

  dataList.forEach(item => {
    const nombrePerfil = (item.nombre_perfil || '').toLowerCase();
    const plataforma = (item.plataforma || '').toLowerCase();

    // Criterio: Si dice "completa" o "cuenta", es Maestra
    const esCuentaMaestra = nombrePerfil.includes('completa') ||
      nombrePerfil.includes('cuenta') ||
      plataforma.includes('completa');

    if (esCuentaMaestra) {
      const key = item.email_cuenta;
      if (!masters[key]) masters[key] = [];
      masters[key].push(item);
    } else {
      singles.push(item);
    }
  });

  // Renderizar Individuales
  singles.forEach(item => {
    renderResellerSingleCard(item, grid);
  });

  // Renderizar Maestras
  Object.values(masters).forEach(group => {
    renderResellerMasterCard(group, grid);
  });
}

function renderResellerSingleCard(item, container) {
  // Cálculos de fecha
  const hoy = new Date();
  // Prioridad: Fecha corte cliente > Fecha venta + 30 dias
  const fechaBase = item.fecha_corte_cliente ? item.fecha_corte_cliente : item.fecha_vencimiento;
  const vence = new Date(fechaBase);
  const diff = Math.ceil((vence - hoy) / 864e5);

  // Estilos de estado
  let badgeClass = "badge-success", txtEstado = diff + " días";
  if (diff <= 5) { badgeClass = "badge-warning"; }
  if (diff <= 0) { badgeClass = "badge-danger"; txtEstado = "VENCIDO"; }

  // Determinar clase CSS según plataforma para el color del borde
  let platClass = "plat-generic";
  const pName = item.plataforma.toLowerCase();
  if (pName.includes("netflix")) platClass = "plat-netflix";
  else if (pName.includes("disney")) platClass = "plat-disney";
  else if (pName.includes("hbo") || pName.includes("max")) platClass = "plat-max";
  else if (pName.includes("amazon") || pName.includes("prime")) platClass = "plat-prime";
  else if (pName.includes("spotify")) platClass = "plat-spotify";

  // Select de Clientes
  let sel = `<select onchange="assignClient(${item.id},this.value)" style="background:transparent; color:#fff; border:1px solid #444; border-radius:4px; padding:2px; width:100%; font-size:0.8rem;">
               <option value="">-- Asignar Cliente --</option>`;
  if (myClients.length) {
    myClients.forEach(c => {
      sel += `<option value="${c.id}" ${item.cliente_final_id == c.id ? "selected" : ""}>${c.nombre}</option>`;
    });
  }
  sel += "</select>";

  // HTML de Credenciales (Visible si es manual, oculto si no)
  let credsHtml = '';
  // Si viene la contraseña plana (manual) O si quieres mostrar el email siempre
  const passShow = item.password_plain ? item.password_plain : '••••••••';

  // Función de copiado mejorada
  const copyAction = `onclick="copyText('${item.email_cuenta}')"`;
  const copyPass = item.password_plain ? `onclick="copyText('${item.password_plain}')"` : `onclick="viewCreds('${safeEncode(item)}')"`;

  credsHtml = `
        <div class="cred-box">
            <div class="cred-row">
                <span style="color:#aaa;">Usuario:</span>
                <span class="cred-value" ${copyAction} title="Copiar Email">${item.email_cuenta}</span>
            </div>
            <div class="cred-row">
                <span style="color:#aaa;">Pass:</span>
                <span class="cred-value" ${copyPass} title="Copiar/Ver Password">${passShow}</span>
            </div>
            ${item.nombre_perfil ? `
            <div class="cred-row" style="border-top:1px solid rgba(255,255,255,0.1); padding-top:5px; margin-top:5px;">
                <span style="color:var(--primary);">Perfil:</span>
                <span style="font-weight:bold;">${item.nombre_perfil}</span>
                <input type="text" value="${item.pin_perfil || ''}" placeholder="PIN" 
                       onblur="savePD(${item.id})" id="ep-${item.id}"
                       style="width:90px; text-align:center; background:#222; border:none; color:#fff; border-radius:3px;">
            </div>` : ''}
        </div>
    `;

  // HTML FINAL DE LA TARJETA
  container.innerHTML += `
        <div class="inventory-card ${platClass}">
            <div class="card-header-visual">
                <div style="display:flex; align-items:center; gap:10px;">
                    <img src="${getLogo(item.plataforma)}" style="width:24px; height:24px; border-radius:4px;">
                    <span style="font-weight:600; font-size:0.9rem;">${item.plataforma}</span>
                </div>
                <span class="badge ${badgeClass}" style="font-size:0.7rem;">${txtEstado}</span>
            </div>

            <div style="padding:0 15px;">
                <div style="margin-top:10px; margin-bottom:5px;">
                    ${sel}
                </div>
                
                ${credsHtml}

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-size:0.8rem;">
                    <span style="color:#aaa;">Auto-Renovar</span>
                    <label class="switch" style="transform:scale(0.7);">
                        <input type="checkbox" ${item.auto_renovacion == 1 ? "checked" : ""} onchange="toggleAR(${item.id},this)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="card-actions">
                <button onclick="viewCreds('${safeEncode(item)}')" style="border-right:1px solid #333;">👁️ Datos</button>
                <button onclick="renewProduct(${item.id},'${item.plataforma}')" style="border-right:1px solid #333; color:var(--secondary);">↻ Renovar</button>
                <button onclick="reportIssue(${item.id})" style="color:var(--danger);">⚠️ Reportar</button>
            </div>
        </div>`;
}
function releaseAccount(id, isMaster = false) {
  const msg = isMaster
    ? "¿Seguro que deseas liberar esta CUENTA COMPLETA? Se devolverán todos los perfiles asociados al stock."
    : "¿Seguro que deseas liberar (eliminar) esta cuenta de tu inventario? Esta acción no se puede deshacer.";

  SwalConfirm(msg, () => {
    fetch('api.php?action=release_account', {
      method: 'POST',
      body: JSON.stringify({ id, is_master: isMaster })
    })
      .then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess(res.message || "Cuenta liberada correctamente");
          loadInventory();
        } else {
          SwalError(res.message);
        }
      }).catch(e => SwalError("Error de conexión"));
  });
}

function renderResellerMasterCard(group, container) {
  const base = group[0]; // Usamos el primer perfil como referencia de la cuenta
  const totalSlots = group.length;
  const ocupados = group.filter(p => p.cliente_final_id && p.cliente_final_id != '0').length;
  const porcentaje = Math.round((ocupados / totalSlots) * 100);

  // Estado del Switch (IMPORTANTE: convertir 1/0 a checked/nada)
  // Nos aseguramos que lea el valor de auto_renovacion
  const isChecked = (base.auto_renovacion == 1 || base.auto_renovacion == "1") ? "checked" : "";

  // ... (Cálculos de fecha igual que antes) ...
  const hoy = new Date();
  const fechaBase = base.fecha_corte_cliente ? base.fecha_corte_cliente : base.fecha_vencimiento;
  const vence = new Date(fechaBase);
  const diff = Math.ceil((vence - hoy) / 864e5);

  let badgeClass = "badge-success", txtEstado = diff + " días";
  if (diff <= 5) badgeClass = "badge-warning";
  if (diff <= 0) { badgeClass = "badge-danger"; txtEstado = "VENCIDA"; }

  // Estilos de plataforma
  let platClass = "plat-generic";
  const pName = base.plataforma.toLowerCase();
  if (pName.includes("netflix")) platClass = "plat-netflix";
  else if (pName.includes("disney")) platClass = "plat-disney";
  else if (pName.includes("amazon")) platClass = "plat-prime";

  // Generar perfiles... (Tu lógica actual de loop está bien, la omito para abreviar, pégala aquí)
  let profilesHtml = '';
  group.sort((a, b) => a.nombre_perfil.localeCompare(b.nombre_perfil));
  group.forEach(p => {
    let sel = `<select class="compact-select" onchange="assignClient(${p.id},this.value)"><option value="">Libre</option>`;
    if (myClients.length) {
      myClients.forEach(c => { sel += `<option value="${c.id}" ${p.cliente_final_id == c.id ? "selected" : ""}>${c.nombre}</option>`; });
    }
    sel += "</select>";
    profilesHtml += `<div class="profile-row"><div class="profile-name">👤 ${p.nombre_perfil}</div>${sel}<input type="text" class="compact-pin" value="${p.pin_perfil || ''}" placeholder="PIN" onblur="savePD(${p.id})"></div>`;
  });

  container.innerHTML += `
        <div class="inventory-card master-card ${platClass}">
            <div class="card-header-visual" style="background: linear-gradient(90deg, rgba(50,50,50,0.9), transparent);">
                <div style="display:flex; align-items:center; gap:10px;">
                    <img src="${getLogo(base.plataforma)}" style="width:24px; height:24px; border-radius:4px;">
                    <div>
                        <div style="font-weight:700; font-size:0.9rem;">${base.plataforma} <span style="color:var(--secondary); font-size:0.7rem;">MASTER</span></div>
                        <small style="color:#aaa;">${base.email_cuenta}</small>
                    </div>
                </div>
                <span class="badge ${badgeClass}" style="font-size:0.7rem;">${txtEstado}</span>
            </div>

            <!-- BARRA DE OCUPACIÓN -->
            <div class="master-occupancy">
                <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:#aaa;">
                    <span>Ocupación</span><span>${ocupados} / ${totalSlots}</span>
                </div>
                <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: ${porcentaje}%"></div></div>
            </div>

            <!-- SWITCH DE AUTO-RENOVACIÓN (ARREGLADO) -->
            <div style="padding: 0 15px; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <span style="font-size:0.8rem; color:#ddd;">Auto-renovar cuenta:</span>
                <label class="switch" style="transform:scale(0.8);">
                    <!-- IMPORTANTE: Pasamos base.id para que renueve el perfil principal que controla la cuenta -->
                    <input type="checkbox" ${isChecked} onchange="toggleAR(${base.id}, this)">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="profile-list">${profilesHtml}</div>

            <div class="card-actions">
                <button onclick="viewCreds('${safeEncode(base)}')" style="border-right:1px solid #333;">👁️ Datos</button>
                <button onclick="renewProduct(${base.id},'${base.plataforma} MASTER')" style="border-right:1px solid #333; color:var(--secondary);">↻ Renovar</button>
                <button onclick="releaseAccount(${base.id}, true)" style="color:var(--danger);">🗑️ Eliminar</button>
            </div>
        </div>`;
}

// --- ACTIONS (VIEW CREDS, RENEW, ETC) ---
function viewCreds(encItem) {
  const item = safeDecode(encItem);

  Swal.fire({
    title: item.plataforma,
    html: `
        <div style="text-align:left; background:rgba(255,255,255,0.05); padding:15px; border-radius:10px;">
            <p><strong>Email:</strong> <span class="copyable" onclick="copyText('${item.email_cuenta}')">${item.email_cuenta}</span></p>
            <div style="height:1px; background:rgba(255,255,255,0.1); margin:10px 0;"></div>
            <p><strong>Clave:</strong> <span id="pwd-placeholder" style="color:var(--warning)">Cargando...</span></p>
            
            ${item.nombre_perfil && !item.nombre_perfil.includes('Completa') ? `<div style="height:1px; background:rgba(255,255,255,0.1); margin:10px 0;"></div><p><strong>Perfil:</strong> ${item.nombre_perfil}</p><p><strong>PIN:</strong> ${item.pin_perfil || 'N/A'}</p>` : ''}
        </div>
        <p style="margin-top:10px; font-size:0.8rem; color:#aaa;">Haz clic en los datos para copiar.</p>
      `,
    background: '#111', color: '#fff', confirmButtonColor: '#00c6ff',
    didOpen: () => {
      // Fetch password securely
      fetch(`api.php?action=get_credential_secure&id=${item.id}`)
        .then(r => r.json())
        .then(res => {
          const el = document.getElementById('pwd-placeholder');
          if (res.success && el) {
            el.innerHTML = `<span class="copyable" style="color:#fff; cursor:pointer;" onclick="copyText('${res.password}')">${res.password}</span>`;
            // Update onclick to copy the new password
            el.onclick = () => copyText(res.password);
          } else if (el) {
            el.textContent = 'No disponible';
            el.style.color = 'var(--danger)';
          }
        })
        .catch(() => {
          const el = document.getElementById('pwd-placeholder');
          if (el) {
            el.textContent = 'Error';
            el.style.color = 'var(--danger)';
          }
        });
    }
  });
}

function copyText(text) {
  let txtToCopy = text;
  const el = document.getElementById(text);
  if (el) txtToCopy = el.innerText || el.value;

  navigator.clipboard.writeText(txtToCopy).then(() => {
    // Vibrar si es celular
    if (navigator.vibrate) navigator.vibrate(50);

    Swal.fire({
      title: '¡Copiado!',
      text: txtToCopy,
      icon: 'success',
      timer: 1500,
      showConfirmButton: false,
      background: '#111',
      color: '#fff',
      backdrop: `rgba(0,0,0,0.4)`
    });
  });
}
// --- SUBMIT FUNCTIONS (RENEW, REPORT, ETC) STUBS ---
function savePD(id) {
  const pin = document.getElementById(`ep-${id}`).value;
  const fecha = null; // No editamos fecha de corte desde aquí aún
  fetch('api.php?action=update_profile_details', {
    method: 'POST',
    body: JSON.stringify({ id, pin, fecha })
  })
    .then(r => r.json()).then(res => {
      if (res.success) SwalSuccess("PIN Guardado");
      else SwalError(res.message);
    });
}

function toggleAR(id, chk) {
  // 1. Obtenemos el nuevo estado basado en si el checkbox quedó marcado o no
  const newState = chk.checked ? 1 : 0;

  // 2. Enviamos al servidor
  fetch('api.php?action=toggle_auto_renew', {
    method: 'POST',
    body: JSON.stringify({ id: id, state: newState })
  })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showToast("Auto-renovación actualizada");

        // --- FIX CRÍTICO: ACTUALIZAR MEMORIA LOCAL ---
        // Buscamos el item en la lista global y actualizamos su propiedad
        const index = allInventoryData.findIndex(item => item.id == id);
        if (index !== -1) {
          allInventoryData[index].auto_renovacion = newState;
        }

        // Si es una cuenta MAESTRA, es posible que el ID visual sea solo uno del grupo.
        // Para evitar errores visuales, buscamos si hay otros perfiles con la misma cuenta_id 
        // en memoria y los actualizamos también (opcional pero recomendado para consistencia).
        const changedItem = allInventoryData[index];
        if (changedItem && changedItem.cuenta_id) {
          allInventoryData.forEach(p => {
            if (p.cuenta_id === changedItem.cuenta_id) {
              p.auto_renovacion = newState;
            }
          });
        }

      } else {
        // Si falló, regresamos el switch a como estaba
        chk.checked = !chk.checked;
        SwalError("Error al actualizar estado");
      }
    })
    .catch(err => {
      console.error(err);
      chk.checked = !chk.checked; // Revertir visualmente si hay error de red
      SwalError("Error de conexión");
    });
}

function renewProduct(id, name) {
  SwalConfirm(`¿Renovar ${name} por 30 días más?`, () => {
    fetch('api.php?action=renew_my_product', { method: 'POST', body: JSON.stringify({ id }) })
      .then(r => r.json()).then(res => {
        if (res.success) { SwalSuccess("Renovado"); loadInventory(); }
        else SwalError(res.message);
      });
  });
}

function reportIssue(id) {
  Swal.fire({
    title: 'Reportar Falla',
    html: `
      <input type="file" id="swal-input-file" class="swal2-input" accept="image/*">
      <textarea id="swal-input-text" class="swal2-textarea" placeholder="Describe el problema detalladamente..."></textarea>
    `,
    showCancelButton: true,
    confirmButtonText: 'Enviar Reporte',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const mensaje = document.getElementById('swal-input-text').value;
      const file = document.getElementById('swal-input-file').files[0];
      if (!mensaje) {
        Swal.showValidationMessage('Debes escribir una descripción del problema');
        return false;
      }
      return { mensaje: mensaje, file: file };
    }
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('perfil_id', id);
      formData.append('mensaje', result.value.mensaje);
      if (result.value.file) {
        formData.append('imagen', result.value.file);
      }

      // Mostrar cargando
      Swal.fire({ title: 'Enviando...', didOpen: () => Swal.showLoading() });

      fetch('api.php?action=report_issue', {
        method: 'POST',
        body: formData // FormData se encarga de los headers correctos
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) SwalSuccess("Reporte enviado correctamente");
          else SwalError(res.message);
        })
        .catch(e => SwalError("Error de conexión al enviar"));
    }
  });
}

function showToast(msg, icon = 'success') {
  const Toast = Swal.mixin({
    toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, background: '#222', color: '#fff'
  });
  Toast.fire({ icon: icon, title: msg });
}

function loadMonitor() {
  const grid = document.getElementById('monitor-grid');
  const search = document.getElementById('monitor-search') ? document.getElementById('monitor-search').value : '';

  if (!grid) return;
  grid.innerHTML = '<div class="spinner"></div>';

  fetch('api.php?action=monitor_sales', {
    method: 'POST',
    body: JSON.stringify({ search })
  }).then(r => r.json()).then(res => {
    grid.innerHTML = '';

    if (!res.success || !res.data || res.data.length === 0) {
      grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#777; padding:3rem;">No hay clientes activos.</div>';
      return;
    }

    res.data.forEach(item => {
      const hoy = new Date();

      // --- FECHA 1: TU STOCK (Proveedor) ---
      // Usamos la fecha virtual que calculó SQL (Venta + 30 dias)
      const fechaStock = new Date(item.vencimiento_stock_virtual);
      const diffStock = Math.ceil((fechaStock - hoy) / 864e5);

      // --- FECHA 2: TU CLIENTE (Cobranza) ---
      // Si fecha_corte_cliente es NULL (no asignada), usamos hoy como placeholder visual (sin guardar)
      let fechaClienteVal = item.fecha_corte_cliente;
      let fechaClienteObj = fechaClienteVal ? new Date(fechaClienteVal) : new Date();

      // EL COLOR DE LA TARJETA DEPENDE DEL CLIENTE (COBRANZA)
      // Si el cliente no ha pagado, se pone rojo.
      const diffCliente = Math.ceil((fechaClienteObj - hoy) / 864e5);

      let statusClass = 'status-active';
      let statusText = `Cobro: ${diffCliente} días`;
      let icon = '✅';

      if (diffCliente <= 3) { statusClass = 'status-soon'; statusText = 'Cobrar Pronto'; icon = '⚠️'; }
      if (diffCliente <= 0) { statusClass = 'status-expired'; statusText = 'VENCIDO'; icon = '❌'; }
      if (!fechaClienteVal) { statusClass = ''; statusText = 'Sin Fecha'; icon = '📅'; }

      // ALERTA DE STOCK: Si tu cuenta de proveedor vence ANTES que el cliente pague
      let stockAlert = '';
      if (diffStock < diffCliente) {
        stockAlert = `
                <div style="background:rgba(255,100,100,0.15); border:1px solid rgba(255,50,50,0.3); color:#ffcccc; font-size:0.7rem; padding:6px; border-radius:4px; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                    <span>⚠️ <b>Cuidado:</b> Tu cuenta de proveedor vence en ${diffStock} días (antes que el cliente).</span>
                </div>`;
      }

      // Preparar el valor para el input (YYYY-MM-DD)
      const inputDateValue = fechaClienteVal ? fechaClienteVal : '';

      // Construir HTML
      const tieneCliente = item.cliente_nombre && item.cliente_nombre.length > 1;
      let actionArea = '';

      if (tieneCliente) {
        actionArea = `
                    <div class="date-editor">
                        <span style="font-size:0.8rem; color:#aaa;">Paga el:</span>
                        <input type="date" class="date-input-monitor" value="${inputDateValue}" 
                               onchange="updatePaymentDate(${item.id}, this.value)">
                    </div>
                    ${stockAlert}
                    <button class="btn-cobrar" onclick="sendCollectionMessage('${item.cliente_nombre}', '${item.cliente_tel}', '${item.plataforma}', '${inputDateValue}', ${item.id})">
                        <span>📲 COBRAR AHORA</span>
                    </button>
                `;
      } else {
        actionArea = `<div style="text-align:center; color:#555; padding:10px;">Cliente no asignado</div>`;
      }

      grid.innerHTML += `
                <div class="monitor-card ${statusClass}">
                    <div class="monitor-header">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="${getLogo(item.plataforma)}" style="width:28px; height:28px; border-radius:4px;">
                            <div>
                                <div style="font-weight:bold; font-size:0.9rem;">${item.plataforma}</div>
                                <div style="font-size:0.75rem; color:#aaa;">${item.nombre_perfil || 'Master'}</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:bold; font-size:0.85rem;">${statusText}</div>
                            <div style="font-size:0.65rem; color:#888;">Stock vence: ${fechaStock.toLocaleDateString()}</div>
                        </div>
                    </div>

                    <div class="client-info-box">
                        <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
                            <span style="color:#aaa;">Cliente:</span>
                            <span style="color:#fff; font-weight:600;">${tieneCliente ? item.cliente_nombre : '---'}</span>
                        </div>
                        ${actionArea}
                    </div>
                </div>`;
    });
  });
}

// --- FUNCIONES AUXILIARES DE COBRANZA ---

// 1. Actualizar Fecha de Pago en tiempo real
function updatePaymentDate(perfilId, nuevaFecha) {
  fetch('api.php?action=update_profile_details', {
    method: 'POST',
    body: JSON.stringify({ id: perfilId, fecha: nuevaFecha }) // Enviar solo la fecha, el PIN se mantiene
  })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const Toast = Swal.mixin({
          toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, background: '#222', color: '#fff'
        });
        Toast.fire({ icon: 'success', title: 'Fecha de pago actualizada' });
        loadMonitor(); // Recargar para actualizar estados (colores)
      } else {
        SwalError("No se pudo actualizar la fecha");
      }
    });
}

// 2. Enviar Mensaje de Cobro (Lógica Venezuela)
function sendCollectionMessage(nombre, telefono, plataforma, fechaVence, id) {
  if (!telefono || telefono.length < 5) {
    SwalError("El cliente no tiene un número válido registrado.");
    return;
  }

  // Limpiar número (quitar guiones, espacios, paréntesis)
  let cleanPhone = telefono.replace(/\D/g, '');

  if (cleanPhone.startsWith('04')) {
    cleanPhone = '58' + cleanPhone.substring(1);
  }
  // Si no tiene código de país (menos de 10 dígitos es raro), asumimos 58
  else if (cleanPhone.length === 10) {
    cleanPhone = '58' + cleanPhone;
  }

  // Formatear fecha bonita (ej: 12/02/2026)
  const fechaObj = new Date(fechaVence);
  // Ajuste por zona horaria para que no reste un día
  const userTimezoneOffset = fechaObj.getTimezoneOffset() * 60000;
  const fechaBonita = new Date(fechaObj.getTime() + userTimezoneOffset).toLocaleDateString('es-ES');

  // CREAR MENSAJE PERSONALIZADO
  let msg = `Hola *${nombre}*! 👋\n\n`;
  msg += `Paso por aquí para recordarte que tu cuenta de *${plataforma}* `;

  // Detectar si ya venció o va a vencer
  const hoy = new Date();
  const diff = (fechaObj - hoy) / 864e5;

  if (diff < 0) {
    msg += `*venció el ${fechaBonita}*. 🔴\nPor favor realiza el pago para reactivar el servicio.`;
  } else if (diff <= 1) {
    msg += `*vence MAÑANA (${fechaBonita})*. ⚠️\nRecuerda renovar para no perder el acceso.`;
  } else {
    msg += `tiene fecha de corte para el *${fechaBonita}*. 📅`;
  }

  msg += `\n\nQuedo atento a tu comprobante. Gracias!`;

  // Abrir WhatsApp
  const url = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`;
  window.open(url, '_blank');
}

function loadWalletHistory() {
  const tbody = document.getElementById('wallet-history-body');
  if (!tbody) return;
  fetch('api.php?action=get_wallet_history').then(r => r.json()).then(data => {
    tbody.innerHTML = '';
    if (!data || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:10px; color:#555;">Sin movimientos</td></tr>';
      return;
    }
    data.forEach(row => {
      const color = row.monto >= 0 ? '#00ff88' : '#ff0055';
      tbody.innerHTML += `<tr>
                <td style="padding:10px; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.85rem;">${row.fecha}</td>
                <td style="padding:10px; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.85rem;">${row.descripcion}</td>
                <td style="padding:10px; border-bottom:1px solid rgba(255,255,255,0.05); text-align:right; color:${color}; font-weight:bold; font-size:0.85rem;">$${parseFloat(row.monto).toFixed(2)}</td>
             </tr>`;
    });
  });
}

function loadProfile() {
  fetch('api.php?action=get_me').then(r => r.json()).then(d => {
    if (d.nombre) {
      const nameEl = document.getElementById('prof-name');
      if (nameEl) nameEl.value = d.nombre;

      const headEl = document.getElementById('profile-header-name');
      if (headEl) headEl.textContent = d.nombre;

      const mailEl = document.getElementById('prof-email');
      if (mailEl) mailEl.value = d.email || '';

      const avEl = document.getElementById('profile-avatar');
      if (avEl) avEl.textContent = d.nombre.charAt(0);
    }
  });

  // Activity Log
  fetch('api.php?action=get_activity_log').then(r => r.json()).then(logs => {
    const list = document.getElementById('activity-log-list');
    if (!list) return;
    list.innerHTML = '';
    logs.forEach(l => {
      list.innerHTML += `<div style="padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
                <div style="font-size:0.8rem;"><span style="color:var(--primary); font-weight:bold;">[${l.accion}]</span> ${l.descripcion}</div>
                <div style="font-size:0.7rem; color:#555; margin-top:2px;">${l.fecha}</div>
             </div>`;
    });
  });
}

function openNotifications() {
  fetch('api.php?action=get_notifications').then(r => r.json()).then(list => {
    let html = '<div style="max-height:300px; overflow-y:auto; text-align:left;">';
    if (list.length === 0) html += '<p style="color:#777; text-align:center;">No hay notificaciones.</p>';

    list.forEach(n => {
      html += `<div style="background:#222; padding:10px; border-radius:5px; margin-bottom:5px; border:1px solid #333;">
                <div style="font-weight:bold; color:#fff; font-size:0.9rem;">${n.titulo}</div>
                <div style="color:#ccc; font-size:0.85rem; margin-top:2px;">${n.mensaje}</div>
                <div style="font-size:0.7rem; color:#666; text-align:right; margin-top:4px;">${n.fecha}</div>
            </div>`;
    });
    html += '</div>';
    Swal.fire({ title: 'Notificaciones', html: html, showConfirmButton: false, showCloseButton: true, background: '#111', color: '#fff' });
  });
}

function openPaymentModal() {
  const m = document.getElementById('modal-report-payment');
  if (m) m.style.display = 'flex';
}

function getResellerCode(id) {
  fetch(`api.php?action=extract_code_reseller&id=${id}`).then(r => r.json()).then(res => {
    if (res.code) {
      Swal.fire({
        title: 'Código de Acceso',
        text: res.code,
        footer: '<small>Usa este código en MiCuenta.me</small>',
        background: '#111', color: '#fff', confirmButtonColor: '#00c6ff'
      });
    } else {
      SwalError(res.error || 'Error obteniendo código');
    }
  }).catch(() => SwalError('Error de conexión'));
}

function loadFeedback() {
  // Placeholder if you have logic to show sent feedback, currently mostly send only
  const list = document.getElementById('feedback-list');
  if (list) list.innerHTML = '<div class="glass-card" style="text-align:center; color:#555;">Historial no disponible.</div>';
}

// --- FORM LISTENERS ---
const formPay = document.getElementById('form-payment-proof');
if (formPay) {
  formPay.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(formPay);
    // Manually map fields if needed, but FormData should work if Inputs have name attributes
    // Check panel.php: inputs have ID strictly, maybe missed NAME attribute?
    // IMPORTANT: If inputs don't have name attribute, FormData won't generate keys.
    // Let's manually build it to be safe since I didn't verify names in panel.php

    formData.append('monto', document.getElementById('pay-amount-final').value);
    formData.append('metodo', document.getElementById('pay-method').value);
    formData.append('referencia', document.getElementById('pay-ref').value);
    formData.append('nombre_titular', document.getElementById('pay-name').value);
    formData.append('correo_titular', document.getElementById('pay-email').value);
    formData.append('fecha_pago', document.getElementById('pay-date').value);
    formData.append('comprobante', document.getElementById('pay-img').files[0]);

    fetch('api.php?action=submit_recharge_proof', {
      method: 'POST',
      body: formData
    }).then(r => r.json()).then(res => {
      if (res.success) {
        SwalSuccess("Pago reportado. Espera confirmación.");
        document.getElementById('modal-report-payment').style.display = 'none';
        formPay.reset();
      } else {
        SwalError(res.message);
      }
    }).catch(e => SwalError("Error al enviar reporte"));
  });
}

const formFeed = document.getElementById('form-feedback');
if (formFeed) {
  formFeed.addEventListener('submit', (e) => {
    e.preventDefault();
    const tipo = document.getElementById('feed-type').value;
    const msg = document.getElementById('feed-msg').value;

    fetch('api.php?action=send_feedback', {
      method: 'POST',
      body: JSON.stringify({ tipo, mensaje: msg })
    }).then(r => r.json()).then(res => {
      if (res.success) { SwalSuccess("Gracias por tu opinión"); formFeed.reset(); }
      else SwalError(res.message);
    });
  });
}

const formProf = document.getElementById('form-profile');
if (formProf) {
  formProf.addEventListener('submit', (e) => {
    e.preventDefault();
    const d = {
      nombre: document.getElementById('prof-name').value,
      phone: '', // Not in UI
      cedula: '', // Not in UI
      password: document.getElementById('prof-pass').value
    };
    fetch('api.php?action=update_my_profile', { method: 'POST', body: JSON.stringify(d) })
      .then(r => r.json()).then(res => {
        if (res.success) SwalSuccess("Perfil actualizado");
        else SwalError(res.message);
      });
  });
}

function loadFinance() {
    fetch("api.php?action=get_finance_stats").then(r => r.json()).then(d => {
        if (!d.success) return;
        
        financeData = d; // Guardar datos para conversión de moneda
        renderFinanceValues(); // Dibujar los totales (Gasto, Profit, etc)

        // 1. Renderizar Top Productos
        const topList = document.getElementById('top-products-list');
        if (topList && d.top_productos) {
            topList.innerHTML = '';
            // Si está vacío
            if (Object.keys(d.top_productos).length === 0) {
                 topList.innerHTML = '<p style="color:#666; font-size:0.8rem;">Sin ventas aún.</p>';
            } else {
                Object.entries(d.top_productos).forEach(([name, count]) => {
                    topList.innerHTML += `
                    <div class="top-prod-item">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="${getLogo(name)}" style="width:20px; border-radius:3px;">
                            <span style="font-size:0.9rem;">${name}</span>
                        </div>
                        <span style="font-weight:bold; color:#fff;">${count}</span>
                    </div>`;
                });
            }
        }

        // 2. Renderizar Tabla de Precios (AQUÍ ESTÁ EL CAMBIO VISUAL)
        const pGrid = document.getElementById('finance-prices-grid');
        
        if (pGrid && d.tabla_precios) {
            // FORZAMOS LA CLASE CSS NUEVA
            pGrid.className = 'prices-grid'; 
            pGrid.innerHTML = '';

            if (d.tabla_precios.length === 0) {
                pGrid.innerHTML = '<p style="grid-column:1/-1; color:#666;">No hay productos en el catálogo para configurar.</p>';
                return;
            }
            
            d.tabla_precios.forEach(p => {
                const costo = parseFloat(p.costo);
                const venta = parseFloat(p.venta);
                const ganancia = venta - costo;
                
                // Determinar estilo inicial del badge
                let profitClass = 'profit-neutral';
                let profitText = 'Sin Ganancia';
                
                if (ganancia > 0.01) {
                    profitClass = 'profit-positive';
                    profitText = `+$${ganancia.toFixed(2)} Ganancia`;
                } else if (ganancia < -0.01) {
                    profitClass = 'profit-negative';
                    profitText = `-$${Math.abs(ganancia).toFixed(2)} Pérdida`;
                }
                
                // Generamos un ID único seguro para el DOM
                const safeName = p.plataforma.replace(/[^a-zA-Z0-9]/g, '');

                pGrid.innerHTML += `
                <div class="price-card-v2">
                    <!-- Cabecera -->
                    <div class="pc-header">
                        <img src="${getLogo(p.plataforma)}" style="width:24px; height:24px; border-radius:4px;">
                        <span style="font-weight:600; font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.plataforma}</span>
                    </div>

                    <!-- Costo -->
                    <div class="pc-cost">
                        Costo Real: $${costo.toFixed(2)}
                    </div>

                    <!-- Input Venta -->
                    <div class="pc-input-group">
                        <span class="pc-symbol">$</span>
                        <input type="number" step="0.1" 
                               value="${venta}" 
                               class="pc-input"
                               id="input-price-${safeName}"
                               onkeyup="updateProfitCalc(this, ${costo}, '${safeName}')"
                               onchange="saveMyPrice('${p.plataforma}', this.value)">
                    </div>

                    <!-- Badge Ganancia -->
                    <div id="profit-badge-${safeName}" class="profit-badge ${profitClass}">
                        ${profitText}
                    </div>
                </div>`;
            });
        }
    }).catch(console.error);
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

function saveMyPrice(plat, price) {
  fetch('api.php?action=save_my_price', { method: 'POST', body: JSON.stringify({ plataforma: plat, precio: price }) })
    .then(r => r.json()).then(res => { if (res.success) showToast('Precio guardado'); });
}

function editGoal() {
  SwalPrompt("Nueva Meta Mensual ($)").then(r => {
    if (r.isConfirmed && r.value) {
      fetch('api.php?action=update_goal', { method: 'POST', body: JSON.stringify({ meta: r.value }) })
        .then(res => res.json()).then(d => { if (d.success) { SwalSuccess("Meta actualizada"); loadFinance(); } });
    }
  });
}

// assignClient helper
window.assignClient = function (perfilId, clientId) {
  if (!clientId) return;
  fetch('api.php?action=assign_client', {
    method: 'POST',
    body: JSON.stringify({ perfil_id: perfilId, client_id: clientId })
  }).then(r => r.json()).then(res => {
    if (res.success) SwalSuccess("Cliente asignado");
    else SwalError(res.message);
  });
}

function logout() {
  window.location.href = 'index.php?logout=1';
}

// --- OPEN RESELLER MASTER MODAL (FIX) ---
function openResellerMasterModal(encGroup) {
  const group = safeDecode(encGroup);
  if (!group || group.length === 0) return;

  const base = group[0];
  let html = `<div style="text-align:left; max-height:60vh; overflow-y:auto; padding-right:5px;">`;

  group.forEach(p => {
    // Select logic copied from Single Card
    let sel = `<select class="value-box" onchange="assignClient(${p.id},this.value)" style="padding:5px; font-size:0.8rem; margin-bottom:5px;"><option value="">-- Cliente --</option>`;
    if (myClients.length) {
      myClients.forEach(c => {
        sel += `<option value="${c.id}" ${p.cliente_final_id == c.id ? "selected" : ""}>${c.nombre}</option>`;
      });
    }
    sel += "</select>";

    html += `
        <div style="background:rgba(255,255,255,0.05); padding:10px; margin-bottom:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
            <div style="font-weight:bold; color:var(--primary); margin-bottom:5px; font-size:0.9rem;">${p.nombre_perfil}</div>
            
            <div style="margin-bottom:5px;">${sel}</div>
            
            <div style="display:flex; gap:5px;">
                <input type="text" class="value-box" id="ep-master-${p.id}" value="${p.pin_perfil || ''}" placeholder="PIN" style="flex:1; text-align:center;">
                <button onclick="savePDMaster(${p.id})" class="icon-btn success" style="width:90px;">💾</button>
            </div>
        </div>`;
  });
  html += `</div>`;

  Swal.fire({
    title: `${base.plataforma} Master`,
    html: html,
    background: '#111', color: '#fff',
    showConfirmButton: false,
    showCloseButton: true,
    width: '400px'
  });
}

function savePDMaster(id) {
  const pin = document.getElementById(`ep-master-${id}`).value;
  const fecha = null;
  fetch('api.php?action=update_profile_details', {
    method: 'POST',
    body: JSON.stringify({ id, pin, fecha })
  })
    .then(r => r.json()).then(res => {
      if (res.success) showToast("PIN Guardado");
      else SwalError("Error al guardar");
    });
}

// --- MISSING APP LOGIC ---

function openTelegramChannel() {
  fetch('api.php?action=get_telegram_link').then(r => r.json()).then(d => {
    if (d.link && d.link !== '#') window.open(d.link, '_blank');
    else SwalError("El administrador aún no ha configurado el canal de marketing.");
  });
}

function loadReports() {
  const grid = document.getElementById('reports-grid');
  if (!grid) return;
  grid.innerHTML = '<p class="text-muted">Cargando soporte...</p>';

  fetch('api.php?action=get_my_reports').then(r => r.json()).then(data => {
    grid.innerHTML = '';
    if (!data || data.length === 0) {
      grid.innerHTML = '<div class="glass-card" style="text-align:center; padding:2rem; color:#666;">No hay tickets de soporte activos.</div>';
      return;
    }
    data.forEach(r => {
      let badgeStyle = 'badge-warning';
      if (r.estado === 'resuelto') badgeStyle = 'badge-success';
      if (r.estado === 'cerrado') badgeStyle = 'badge-danger';

      grid.innerHTML += `
        <div class="glass-card" style="margin-bottom:15px; border-left:4px solid ${r.estado === 'resuelto' ? 'var(--secondary)' : 'var(--primary)'};">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                <div>
                   <div style="font-weight:bold; color:#fff;">${r.plataforma}</div>
                   <small style="color:#666;">${r.email_cuenta}</small>
                </div>
                <span class="badge ${badgeStyle}">${r.estado.toUpperCase()}</span>
            </div>
            <div style="background:rgba(0,0,0,0.2); padding:10px; border-radius:8px; font-size:0.9rem; margin-top:10px;">
                "${r.mensaje}"
            </div>
            <div style="font-size:0.75rem; color:#444; margin-top:10px; text-align:right;">${r.fecha}</div>
        </div>`;
    });
  });
}

function calculateBs() {
  const usd = document.getElementById('pay-amount-final').value;
  const tasaStr = document.getElementById('live-tasa-bs').textContent;
  const tasa = parseFloat(tasaStr);
  const display = document.getElementById('calc-display');
  const spanBs = document.getElementById('display-bs');

  if (usd > 0 && tasa > 0) {
    display.style.display = 'block';
    spanBs.textContent = (usd * tasa).toLocaleString('es-VE', { minimumFractionDigits: 2 });
  } else {
    display.style.display = 'none';
  }
}

function togglePayFields() {
  const method = document.getElementById('pay-method').value;
  const calc = document.getElementById('calc-display');
  if (method === 'Pago Movil') {
    calculateBs();
  } else {
    calc.style.display = 'none';
  }
}

function sendViaWhatsapp(encItem) {
  const item = safeDecode(encItem);

  // Formato profesional del mensaje
  let msg = `*¡Hola! Aquí tienes tu cuenta de ${item.plataforma}:* 🍿\n\n`;
  msg += `✉️ *Correo:* ${item.email_cuenta}\n`;
  msg += `🔑 *Clave:* ${item.password_plain || 'Consultar'}\n`;

  if (item.nombre_perfil) {
    msg += `👤 *Perfil:* ${item.nombre_perfil}\n`;
    msg += `🔢 *PIN:* ${item.pin_perfil || 'Sin PIN'}\n`;
  }

  msg += `\n📅 *Vence:* ${item.fecha_corte_cliente || item.fecha_vencimiento}\n`;
  msg += `\n_Gracias por tu compra._`;

  // Codificar para URL
  const url = `https://wa.me/?text=${encodeURIComponent(msg)}`;
  window.open(url, '_blank');
}

// --- LOGICA FINANCIERA AVANZADA ---
let financeData = {}; // Guardar datos para conversión
let showInBs = false; // Estado del toggle

// Función para alternar moneda y redibujar
function toggleCurrency() {
  showInBs = !showInBs;
  const btn = document.getElementById('btn-currency');

  if (showInBs) {
    btn.classList.add('active');
    btn.innerHTML = `<span>🇻🇪 BS</span>`; // Solo mostrar BS
  } else {
    btn.classList.remove('active');
    btn.innerHTML = `<span>🇺🇸 USD</span>`;
  }

  renderFinanceValues();
}

function renderFinanceValues() {
  if (!financeData.tasa) return;

  const tasa = parseFloat(financeData.tasa);
  const currency = showInBs ? 'Bs' : '$';
  const multiplier = showInBs ? tasa : 1;

  // Helper para formatear
  const fmt = (val) => {
    let num = parseFloat(val.toString().replace(/,/g, '')) * multiplier;
    return showInBs
      ? num.toLocaleString('es-VE', { minimumFractionDigits: 2 }) + ' Bs'
      : '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2 });
  };

  // Actualizar DOM
  document.getElementById('fin-gasto').textContent = fmt(financeData.gasto_mes);
  document.getElementById('fin-calle').textContent = fmt(financeData.valor_calle);
  document.getElementById('fin-profit').textContent = fmt(financeData.ganancia_estimada);

  // El margen siempre es %
  document.getElementById('fin-margen').textContent = financeData.margen + '%';

  // Meta
  document.getElementById('goal-display').textContent = fmt(financeData.meta);
  document.getElementById('goal-percent').textContent = financeData.progreso_meta + '%';
  document.getElementById('goal-bar').style.width = financeData.progreso_meta + '%';
}