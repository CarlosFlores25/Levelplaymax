// Admin panel JS - robust patch with array normalization and DOM guards
(function () {
  const fns = [
    'checkExpired', 'loadAssignments', 'loadOrders', 'viewStockDetails', 'openRechargeModal',
    'loadClients', 'loadEmailAccounts', 'loadResellerSales', 'loadResellers', 'loadPrices',
    'loadAccounts', 'loadCoupons', 'viewResellerDetails', 'renewProfile', 'openMigrateModal',
    'saveMasterRenewal', 'openRenewMaster', 'loadAllFeedback', 'loadAllReports', 'loadRechargeRequests',
    'loadDashboard', 'loadStock', 'downloadPDF', 'downloadBackup', 'openApproveResellerOrderModal'
  ];
  fns.forEach(n => { if (typeof window[n] !== 'function') window[n] = function () { console.warn('Stub: ' + n); }; });
})();

// Asegurar que showTab esté disponible globalmente desde el principio
ensureShowTabGlobal();

// Normalize API responses to an array
function toArray(x) {
  if (Array.isArray(x)) return x;
  if (x && Array.isArray(x.data)) return x.data;
  if (x && Array.isArray(x.list)) return x.list;
  if (x && Array.isArray(x.items)) return x.items;
  if (x && Array.isArray(x.results)) return x.results;
  if (x && Array.isArray(x.data)) return x.data;
  return [];
}

/**
 * Helper para escapar HTML y prevenir XSS
 */
function escapeHTML(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function getEl(id) { return document.getElementById(id); }

function fetchWithCSRF(url, options = {}) {
  const token = getEl('csrf_token')?.value || '';
  options.headers = { ...(options.headers || {}), 'X-CSRF-TOKEN': token };
  return fetch(url, options);
}

// Small SweetAlert helper wrappers if missing (used across the admin UI)
if (typeof SwalConfirm !== 'function') {
  function SwalConfirm(text, callback) {
    return Swal.fire({ title: text, icon: 'question', showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'No' })
      .then(res => { if (res.isConfirmed && typeof callback === 'function') callback(); });
  }
}
if (typeof SwalError !== 'function') {
  function SwalError(msg) { return Swal.fire('Error', msg || 'Error inesperado', 'error'); }
}
if (typeof SwalSuccess !== 'function') {
  function SwalSuccess(msg) { return Swal.fire('Éxito', msg || 'Operación completada', 'success'); }
}
if (typeof SwalPrompt !== 'function') {
  function SwalPrompt(placeholder) {
    return Swal.fire({ title: placeholder || 'Input', input: 'text', showCancelButton: true });
  }
}

// Safe JSON parsing for fetch responses that may be empty or invalid
function safeParseResponseJSON(response) {
  return response.text().then(txt => {
    if (!txt) return null;
    try {
      return JSON.parse(txt);
    } catch (e) {
      console.error('Invalid JSON response', e, txt);
      return null;
    }
  });
}

function openModal(id) {
  const el = getEl(id);
  if (el) {
    el.style.display = 'flex';
    if (id === 'modal-cuenta' && typeof loadPlatformOptions === 'function') loadPlatformOptions();
  }
}
function closeModal(id) { const el = getEl(id); if (el) el.style.display = 'none'; }

// Ensure showTab is global and aliased
function ensureShowTabGlobal() {
  if (typeof window.showTab !== 'function') {
    window.showTab = function showTab(id) {
      const el = getEl(id); if (!el) return;
      // Usar clases para controlar visibilidad (mantener consistencia con CSS)
      document.querySelectorAll('.view-section').forEach(sec => sec.classList.remove('active'));
      el.classList.add('active');

      // Close sidebar on mobile if open
      if (window.innerWidth <= 1024) {
        document.querySelector('.app-layout')?.classList.remove('sidebar-collapsed');
      }
      try {
        document.querySelectorAll('.nav-item, .tab-btn').forEach(n => n.classList.remove('active'));
        const anchors = Array.from(document.querySelectorAll('.sidebar-nav .nav-item'));
        anchors.forEach(a => {
          const on = a.getAttribute('onclick') || '';
          if (on.includes(`showTab('${id}')`) || on.includes(`showTab(\"${id}\")`)) {
            a.classList.add('active');
          }
        });
      } catch (e) { }
      // Load on demand
      const tabId = id; // use const for clarity
      if (tabId === 'dashboard' && typeof loadDashboard === 'function') loadDashboard();
      if (tabId === 'pedidos' && typeof loadOrders === 'function') loadOrders('pendiente');
      if (tabId === 'stock' && typeof loadStock === 'function') loadStock();
      if (tabId === 'asignaciones' && typeof loadAssignments === 'function') loadAssignments();
      if (tabId === 'clientes' && typeof loadClients === 'function') loadClients();
      if (tabId === 'cuentas' && typeof loadAccounts === 'function') loadAccounts();
      if (tabId === 'precios' && typeof loadCatalog === 'function') loadCatalog(); // Cargar catálogo en sección precios
      if (tabId === 'resellers_admin' && typeof loadResellers === 'function') loadResellers();
      if (tabId === 'inbox' && typeof loadEmailAccounts === 'function') loadEmailAccounts();
      if (tabId === 'catalogo' && typeof loadCatalog === 'function') loadCatalog();
    };
  }
  window.showtab = window.showTab;
}

// 1) Data Loaders (robust against {data:[...]})
function loadStock() {
  fetchWithCSRF('api.php?action=get_stock')
    .then(r => r.json())
    .then(d => {
      const grid = getEl('stock-grid'); if (!grid) return; grid.innerHTML = '';
      const items = toArray(d);
      if (items.length === 0) {
        grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px; grid-column:1/-1;">Sin stock disponible</div>';
        return;
      }
      items.forEach(it => {
        const disponibles = it.disponibles ?? 0;
        const stockClass = disponibles > 5 ? 'has-stock' : disponibles > 0 ? 'low-stock' : 'no-stock';
        const stockLabel = disponibles > 5 ? 'Disponible' : disponibles > 0 ? 'Poco Stock' : 'Agotado';
        const stockColor = disponibles > 5 ? 'var(--success)' : disponibles > 0 ? 'var(--warning)' : 'var(--danger)';

        grid.innerHTML += `
          <div class="stock-item ${stockClass}">
            <div class="stock-name">${it.plataforma || 'Sin nombre'}</div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
              <span class="card-badge" style="background:rgba(${stockColor === 'var(--success)' ? '0,255,136' : stockColor === 'var(--warning)' ? '255,187,0' : '255,0,85'},0.15);color:${stockColor};border:1px solid ${stockColor};">${stockLabel}</span>
              <div class="stock-qty">${disponibles}</div>
            </div>
            <button class="btn-sm primary full-width" style="margin-top:12px;" onclick="viewStockDetails('${it.plataforma}')">Ver Detalles</button>
          </div>`;
      });
    }).catch(e => {
      console.error("Error loading stock:", e);
      const grid = getEl('stock-grid');
      if (grid) grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px; grid-column:1/-1;">Error al cargar stock</div>';
    });
}
function loadResellers() {
  const grid = getEl('resellers-grid'); if (!grid) return; grid.innerHTML = 'Cargando...';
  fetchWithCSRF('api.php?action=get_all_resellers')
    .then(r => r.json())
    .then(d => {
      const items = toArray(d); grid.innerHTML = '';
      if (items.length === 0) { grid.innerHTML = '<p class="text-muted">Sin datos</p>'; return; }
      items.forEach(u => {
        const saldo = u.saldo !== undefined ? parseFloat(u.saldo).toFixed(2) : '0.00';
        const ventas = u.ventas_totales !== undefined ? u.ventas_totales : '0.00';
        const activo = u.activo == 1 || u.activo === '1' ? true : false;
        const statusLabel = activo ? 'Activo' : 'Inactivo';
        const statusColor = activo ? 'var(--success)' : 'var(--danger)';

        grid.innerHTML += `
          <div class="data-card" style="padding:12px;margin:6px;border-radius:8px;background:rgba(255,255,255,0.04);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <strong>${u.nombre || 'Sin nombre'}</strong><br>
                <small class="text-muted">${u.email || ''}</small>
              </div>
              <div style="text-align:right;">
                <div style="font-size:0.9rem; color:var(--text-muted)">Saldo</div>
                <div style="font-weight:800; color:var(--primary);">$${saldo}</div>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:6px">Ventas: $${ventas}</div>
                <div style="margin-top:8px;"><span class="card-badge" style="background:rgba(0,0,0,0.06); border:1px solid ${statusColor}; color:${statusColor}; padding:4px 8px; border-radius:8px; font-size:0.75rem;">${statusLabel}</span></div>
              </div>
            </div>
            <div class="card-actions">
              <button class="btn-sm" onclick="openRechargeResellerModal(${u.id}, '${escapeJS(u.nombre || '')}', ${saldo})">➕ Recargar</button>
              <button class="btn-sm" onclick="openDeductResellerModal(${u.id}, '${escapeJS(u.nombre || '')}', ${saldo})">➖ Ajuste</button>
              <button class="btn-sm" onclick="sendDirectMessageReseller(${u.id}, '${escapeJS(u.nombre || '')}')">✉️ Mensaje</button>
              <button class="btn-sm" onclick="openResellerMovementsModal(${u.id}, '${escapeJS(u.nombre || '')}')">📜 Historial</button>
              <button class="btn-sm" onclick="accessAsReseller(${u.id})">🔐 Entrar</button>
              <button class="btn-sm" onclick="toggleResellerStatus(${u.id})">🔁 Estado</button>
              <button class="btn-sm btn-danger" onclick="deleteReseller(${u.id}, '${escapeJS(u.nombre || '')}')">🗑️ Eliminar</button>
            </div>
          </div>`;
      });
    }).catch(e => {
      console.error("Error loading resellers:", e);
      grid.innerHTML = '<p class="text-muted">Error al cargar resellers</p>';
    });
}

// Helpers for safe JS-injection into template strings
function escapeJS(str) {
  if (!str) return '';
  return String(str).replace(/'/g, "\\'").replace(/\n/g, ' ');
}

// Open modal to recharge reseller (admin)
function openRechargeResellerModal(id, name, currentSaldo) {
  Swal.fire({
    title: `Recargar saldo - ${name}`,
    html:
      `<input id="swal-recharge-amount" type="number" min="0.01" step="0.01" class="swal2-input" placeholder="Monto (USD)">
       <input id="swal-recharge-note" class="swal2-input" placeholder="Nota (opcional)">`,
    focusConfirm: false,
    preConfirm: () => {
      const monto = parseFloat(document.getElementById('swal-recharge-amount').value || 0);
      const nota = document.getElementById('swal-recharge-note').value || '';
      if (!monto || monto <= 0) {
        Swal.showValidationMessage('Ingresa un monto válido');
        return false;
      }
      return { monto, nota };
    },
    showCancelButton: true,
    confirmButtonText: 'Recargar',
  }).then((res) => {
    if (res.isConfirmed && res.value) {
      const payload = { id, monto: res.value.monto, nota: res.value.nota, nombre_reseller: name };
      fetchWithCSRF('api.php?action=recharge_reseller', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(r => r.json()).then(resp => {
        if (resp.success) {
          Swal.fire('Hecho', 'Saldo recargado correctamente', 'success');
          loadResellers();
        } else {
          Swal.fire('Error', resp.message || 'No se pudo recargar', 'error');
        }
      }).catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
    }
  });
}

// Open modal to deduct or adjust reseller balance
function openDeductResellerModal(id, name, currentSaldo) {
  Swal.fire({
    title: `Ajustar saldo - ${name}`,
    html:
      `<input id="swal-deduct-amount" type="number" min="0.01" step="0.01" class="swal2-input" placeholder="Monto a descontar (USD)">
       <input id="swal-deduct-note" class="swal2-input" placeholder="Nota (motivo)">`,
    focusConfirm: false,
    preConfirm: () => {
      const monto = parseFloat(document.getElementById('swal-deduct-amount').value || 0);
      const nota = document.getElementById('swal-deduct-note').value || '';
      if (!monto || monto <= 0) {
        Swal.showValidationMessage('Ingresa un monto válido');
        return false;
      }
      return { monto, nota };
    },
    showCancelButton: true,
    confirmButtonText: 'Descontar',
  }).then((res) => {
    if (res.isConfirmed && res.value) {
      const payload = { id, monto: res.value.monto, nota: res.value.nota };
      fetchWithCSRF('api.php?action=deduct_reseller_balance', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(r => r.json()).then(resp => {
        if (resp.success) {
          Swal.fire('Hecho', 'Saldo ajustado correctamente', 'success');
          loadResellers();
        } else {
          Swal.fire('Error', resp.message || 'No se pudo ajustar', 'error');
        }
      }).catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
    }
  });
}

// Send direct message to reseller (stored as notification)
function sendDirectMessageReseller(id, name) {
  Swal.fire({
    title: `Enviar mensaje a ${name}`,
    input: 'textarea',
    inputPlaceholder: 'Escribe tu mensaje...',
    showCancelButton: true,
    confirmButtonText: 'Enviar'
  }).then(res => {
    if (res.isConfirmed && res.value && res.value.trim().length > 0) {
      const payload = { id, mensaje: res.value.trim() };
      fetchWithCSRF('api.php?action=send_notification', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(r => r.json()).then(resp => {
        if (resp.success) {
          Swal.fire('Enviado', 'Mensaje registrado y enviado', 'success');
        } else {
          Swal.fire('Error', resp.message || 'No se pudo enviar', 'error');
        }
      }).catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
    }
  });
}

// Toggle reseller active/inactive
function toggleResellerStatus(id) {
  Swal.fire({
    title: 'Cambiar estado del distribuidor',
    text: 'Se invertirá el estado (activar/desactivar). ¿Continuar?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, cambiar'
  }).then(result => {
    if (result.isConfirmed) {
      fetchWithCSRF('api.php?action=toggle_reseller_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      }).then(r => r.json()).then(resp => {
        if (resp.success) {
          Swal.fire('Listo', 'Estado cambiado', 'success');
          loadResellers();
        } else {
          Swal.fire('Error', resp.message || 'No se pudo cambiar estado', 'error');
        }
      }).catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
    }
  });
}

// Delete reseller (libera perfiles y borra usuario)
function deleteReseller(id, name) {
  Swal.fire({
    title: `Eliminar ${name}`,
    text: 'Esto liberará perfiles y eliminará al distribuidor. Acción irreversible.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar'
  }).then(result => {
    if (result.isConfirmed) {
      fetchWithCSRF('api.php?action=delete_reseller', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      }).then(r => r.json()).then(resp => {
        if (resp.success) {
          Swal.fire('Eliminado', 'Distribuidor eliminado y perfiles liberados', 'success');
          loadResellers();
        } else {
          Swal.fire('Error', resp.message || 'No se pudo eliminar', 'error');
        }
      }).catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
    }
  });
}

// Access as reseller (magic link)
function accessAsReseller(id) {
  fetchWithCSRF('api.php?action=access_as_reseller', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  }).then(r => r.json()).then(resp => {
    if (resp.success && resp.url) {
      // Abrir en nueva pestaña la URL mágica
      window.open(resp.url, '_blank');
    } else {
      Swal.fire('Error', resp.message || 'No se pudo generar acceso', 'error');
    }
  }).catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}
// Open modal to view reseller movements (historial financiero)
function openResellerMovementsModal(id, name) {
  const modalId = 'modal-reseller-movements';
  const modal = getEl(modalId);
  if (!modal) {
    // If modal not present, create a simple SweetAlert fallback
    Swal.fire('Historial', 'Modal de historial no encontrado en el DOM.', 'info');
    return;
  }
  // Set header
  const titleEl = getEl('reseller-movements-title');
  if (titleEl) titleEl.textContent = `Movimientos - ${name}`;
  const tbody = getEl('reseller-movements-body');
  if (tbody) tbody.innerHTML = '<tr><td colspan="4">Cargando...</td></tr>';
  openModal(modalId);

  fetchWithCSRF(`api.php?action=get_reseller_movements&id=${encodeURIComponent(id)}`)
    .then(r => r.json())
    .then(data => {
      if (!Array.isArray(data)) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="4">Sin movimientos</td></tr>';
        return;
      }
      if (tbody) {
        if (data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="4">No hay movimientos registrados</td></tr>';
          return;
        }
        tbody.innerHTML = '';
        data.forEach(m => {
          const fecha = m.fecha ? (m.fecha.split ? m.fecha.split(' ')[0] : m.fecha) : '';
          const tipo = m.tipo || '';
          const monto = (m.monto !== undefined) ? parseFloat(m.monto).toFixed(2) : '0.00';
          const desc = m.descripcion || '';
          tbody.innerHTML += `<tr>
            <td style="width:120px;">${fecha}</td>
            <td style="width:120px;">${tipo}</td>
            <td style="text-align:right;">$${monto}</td>
            <td>${desc}</td>
          </tr>`;
        });
      }
    }).catch(e => {
      console.error('Error loading reseller movements:', e);
      if (tbody) tbody.innerHTML = '<tr><td colspan="4">Error al cargar movimientos</td></tr>';
    });
}

// Export reseller movements as CSV
function downloadResellerMovementsCSV() {
  const tbody = getEl('reseller-movements-body');
  if (!tbody) return;
  const rows = Array.from(tbody.querySelectorAll('tr'));
  if (rows.length === 0) return;
  const csv = [];
  csv.push(['Fecha', 'Tipo', 'Monto', 'Descripción'].join(','));
  rows.forEach(r => {
    const cols = Array.from(r.querySelectorAll('td')).map(td => `"${(td.textContent || '').replace(/"/g, '""')}"`);
    if (cols.length) csv.push(cols.join(','));
  });
  const csvContent = csv.join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `movimientos_reseller_${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
function loadResellerSales() {
  const grid = getEl('reseller-sales-body'); if (!grid) return; grid.innerHTML = '<tr><td colspan="4">Cargando...</td></tr>';
  fetchWithCSRF('api.php?action=get_all_reseller_sales')
    .then(r => r.json())
    .then(d => {
      const items = toArray(d); grid.innerHTML = '';
      if (items.length === 0) { grid.innerHTML = '<tr><td colspan="4">Sin ventas</td></tr>'; return; }
      items.forEach(s => { grid.innerHTML += `<tr><td>${s.reseller_name || s.reseller || 'N/A'}</td><td>${s.plataforma}</td><td>${s.nombre_perfil || ''}</td><td>${s.fecha_venta_reseller || s.fecha || ''}</td></tr>`; });
    }).catch(e => {
      console.error("Error loading reseller sales:", e);
      grid.innerHTML = '<tr><td colspan="4">Error al cargar ventas de resellers</td></tr>';
    });
}
function loadClients() {
  const grid = getEl('clients-grid'); if (!grid) return; grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="text-muted">Cargando...</p></div>';
  fetchWithCSRF('api.php?action=list_clients')
    .then(r => r.json())
    .then(d => {
      const items = toArray(d); grid.innerHTML = '';
      if (items.length === 0) { grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Sin clientes registrados</div>'; return; }
      items.forEach(c => {
        const iniciales = (c.nombre || '').substring(0, 2).toUpperCase() || 'NA';
        grid.innerHTML += `
          <div class="data-card">
            <div class="card-header">
              <div class="card-title" style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px;height:40px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:1.1rem;">${iniciales}</div>
                <strong>${c.nombre || 'Sin nombre'}</strong>
              </div>
            </div>
            <div class="card-body">
              <div class="data-row">
                <span>📞 Teléfono:</span>
                <strong>${c.telefono || 'N/A'}</strong>
              </div>
              ${c.email ? `<div class="data-row"><span>📧 Email:</span><span>${c.email}</span></div>` : ''}
            </div>
          </div>`;
      });
    }).catch(e => {
      console.error("Error loading clients:", e);
      grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Error al cargar clientes</div>';
    });
}
function loadAccounts() {
  const grid = getEl('accounts-grid'); if (!grid) return; grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="text-muted">Cargando...</p></div>';
  fetchWithCSRF('api.php?action=list_accounts')
    .then(r => r.json())
    .then(d => {
      const items = toArray(d); grid.innerHTML = '';
      if (items.length === 0) { grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Sin cuentas maestras</div>'; return; }
      items.forEach(a => {
        const ocupados = a.ocupados || 0;
        const total = a.total_slots || 0;
        const libres = total - ocupados;
        const porcentaje = total > 0 ? Math.round((ocupados / total) * 100) : 0;
        const badgeColor = porcentaje === 100 ? 'var(--danger)' : porcentaje >= 75 ? 'var(--warning)' : 'var(--success)';

        grid.innerHTML += `
          <div class="data-card" onclick="viewAccountUsers(${a.id}, '${a.email_cuenta}')" style="cursor:pointer;">
            <div class="card-header">
              <div class="card-title">
                <strong>${a.plataforma || 'Sin plataforma'}</strong>
              </div>
              <span class="card-badge" style="background:rgba(${porcentaje === 100 ? '255,0,85' : porcentaje >= 75 ? '255,187,0' : '0,255,136'},0.15);color:${badgeColor};border:1px solid ${badgeColor};">${ocupados}/${total}</span>
            </div>
            <div class="card-body">
              <div class="data-row">
                <span>📧 Email:</span>
                <span style="font-family:monospace;font-size:0.85rem;">${a.email_cuenta || 'N/A'}</span>
              </div>
              <div class="data-row">
                <span>📊 Estado:</span>
                <span><strong>${libres}</strong> libres de <strong>${total}</strong></span>
              </div>
              ${a.fecha_pago_proveedor ? `<div class="data-row"><span>📅 Vence proveedor:</span><span>${a.fecha_pago_proveedor}</span></div>` : ''}
            </div>
          </div>`;
      });
    }).catch(e => {
      console.error("Error loading accounts:", e);
      grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Error al cargar cuentas</div>';
    });
}
function loadPrices() {
  const grid = getEl('catalog-grid'); if (!grid) return; grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="text-muted">Cargando...</p></div>';
  fetchWithCSRF('api.php?action=get_catalogo')
    .then(r => r.json())
    .then(d => {
      const items = toArray(d); grid.innerHTML = '';
      if (items.length === 0) { grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Sin productos en catálogo</div>'; return; }
      items.forEach(p => {
        const categoriaBadge = p.categoria === 'Streaming' ? 'var(--primary)' :
          p.categoria === 'Combo' ? 'var(--secondary)' : 'var(--warning)';
        grid.innerHTML += `
          <div class="data-card">
            <div class="card-header">
              <div class="card-title">
                <strong>${p.nombre || 'Sin nombre'}</strong>
              </div>
              <span class="card-badge" style="background:rgba(${categoriaBadge === 'var(--primary)' ? '0,198,255' : categoriaBadge === 'var(--secondary)' ? '189,0,255' : '255,187,0'},0.15);color:${categoriaBadge};border:1px solid ${categoriaBadge};">${p.categoria || 'General'}</span>
            </div>
            <div class="card-body">
              <div class="data-row">
                <span>💰 Precio Público:</span>
                <strong style="color:var(--primary);font-size:1.2rem;">$${p.precio || '0.00'}</strong>
              </div>
              ${p.precio_reseller ? `<div class="data-row"><span>💼 Precio Reseller:</span><strong style="color:var(--success)">$${p.precio_reseller}</strong></div>` : ''}
              ${p.descripcion ? `<div class="data-row" style="flex-direction:column;align-items:flex-start;gap:4px;"><span style="color:var(--text-muted);font-size:0.85rem;">${p.descripcion}</span></div>` : ''}
            </div>
          </div>`;
      });
    }).catch(e => {
      console.error("Error loading prices:", e);
      grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Error al cargar catálogo</div>';
    });
}

function viewStockDetails(p) {
  const modal = getEl('modal-stock-details'); if (!modal) return; openModal('modal-stock-details'); const container = getEl('stock-detail-container'); if (container) container.innerHTML = 'Cargando...';
  // Cargar simultáneamente clientes y perfiles disponibles
  Promise.all([
    fetchWithCSRF(`api.php?action=get_stock_details&plataforma=${encodeURIComponent(p)}`).then(r => r.json()),
    fetchWithCSRF('api.php?action=list_clients').then(r => r.json())
  ]).then(([profiles, clients]) => {
    // Server may return different shapes:
    // - Array of profile rows (normal)
    // - Object { success:true, data: { email: { password, perfiles: [...] } } }
    // Normalize to an array of profile objects with fields: id, nombre_perfil, pin_perfil, email_cuenta, password
    if (profiles && typeof profiles === 'object' && !Array.isArray(profiles)) {
      if (profiles.success && profiles.data && typeof profiles.data === 'object') {
        const flat = [];
        Object.entries(profiles.data).forEach(([email, info]) => {
          const pw = info.password || '';
          const perfs = Array.isArray(info.perfiles) ? info.perfiles : [];
          perfs.forEach((pf, idx) => {
            flat.push({
              id: pf.id || (`gen_${email}_${idx}`),
              nombre_perfil: pf.nombre || pf.name || '',
              pin_perfil: pf.pin || pf.pin_perfil || '',
              email_cuenta: email,
              password: pw,
              plataforma: pf.plataforma || ''
            });
          });
        });
        profiles = flat;
      } else {
        // If it's an object but not the expected wrapper, try to convert entries where keys look like emails
        const maybeFlat = [];
        Object.entries(profiles).forEach(([k, v]) => {
          if (v && Array.isArray(v.perfiles)) {
            const pw = v.password || '';
            v.perfiles.forEach((pf, idx) => {
              maybeFlat.push({
                id: pf.id || (`gen_${k}_${idx}`),
                nombre_perfil: pf.nombre || pf.name || '',
                pin_perfil: pf.pin || pf.pin_perfil || '',
                email_cuenta: k,
                password: pw,
                plataforma: pf.plataforma || ''
              });
            });
          }
        });
        if (maybeFlat.length) profiles = maybeFlat;
        else profiles = [];
      }
    } else {
      if (!Array.isArray(profiles)) profiles = [];
    }
    if (!Array.isArray(clients)) clients = [];
    if (!container) return;
    if (profiles.length === 0) {
      container.innerHTML = '<div class="text-muted">No hay perfiles disponibles para esta plataforma.</div>';
      return;
    }
    // Normalizar helper in JS (similar to PHP)
    function normalizeJS(s) {
      if (!s) return '';
      s = String(s).replace(/\(.*?\)/g, ' '); // remove parentheses
      s = s.replace(/[^a-zA-Z0-9\s]/g, ' ');
      s = s.replace(/\s+/g, ' ').trim().toLowerCase();
      return s;
    }

    const normRequested = normalizeJS(p);
    const anyExact = profiles.some(pr => normalizeJS(pr.plataforma || '').includes(normRequested));

    // Construir select de clientes
    let clientOptions = '<option value="">-- Selecciona Cliente --</option>';
    clients.forEach(c => { clientOptions += `<option value="${c.id}">${c.nombre} - ${c.telefono || ''}</option>`; });
    let html = `<div style="margin-bottom:12px;"><strong>Vender cuenta - ${p}</strong></div>`;
    if (!anyExact) {
      html += `<div style="margin-bottom:10px; color:var(--warning); font-size:0.9rem;">Mostrando coincidencias similares porque no se encontró una coincidencia exacta para \"${p}\". Revisa las plataformas listadas.</div>`;
    }
    html += '<div style="display:flex; gap:10px; margin-bottom:12px;">';
    html += `<select id="stock-buy-client" class="form-control" style="min-width:260px;">${clientOptions}</select>`;
    html += `<button class="btn-primary" id="btn-auto-assign">✅ Asignar Automático</button>`;
    html += '</div>';

    html += '<div class="cards-grid-modern">';
    profiles.forEach(pr => {
      const pin = pr.pin_perfil || '—';
      const perfilName = pr.nombre_perfil || 'Perfil';
      html += `<div class="data-card reseller-card">
        <div class="card-header"><div class="card-title"><strong>${perfilName}</strong></div><span class="card-badge" style="background:rgba(0,0,0,0.06);">Cuenta: ${pr.email_cuenta || 'N/A'}</span></div>
        <div class="card-body">
          <div class="data-row"><span>🔐 PIN:</span><strong class="text-mono">${pin}</strong></div>
          <div class="data-row"><span>📧 Email:</span><span style="font-family:monospace;">${pr.email_cuenta || ''}</span></div>
        </div>
        <div class="card-actions">
          <button class="btn-sm primary" onclick="sellProfileToClient('${pr.id}')">💸 Vender</button>
        </div>
      </div>`;
    });
    html += '</div>';
    container.innerHTML = html;

    // Auto assign button handler
    const autoBtn = document.getElementById('btn-auto-assign');
    if (autoBtn) {
      autoBtn.addEventListener('click', () => {
        const clientId = document.getElementById('stock-buy-client').value;
        if (!clientId) { Swal.fire('Selecciona cliente', 'Elige un cliente para asignar automáticamente', 'warning'); return; }
        // Call auto_assign_profile with plataforma and client id
        fetchWithCSRF('api.php?action=auto_assign_profile', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ cliente_id: clientId, plataforma: p })
        }).then(r => r.json()).then(res => {
          if (res.success) {
            Swal.fire('Asignado', `Perfil asignado a ${res.data.datos.cliente_nombre}`, 'success');
            closeModal('modal-stock-details');
            loadStock(); loadAssignments(); loadClients();
          } else {
            Swal.fire('Error', res.message || 'No fue posible asignar', 'error');
          }
        }).catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
      });
    }

  }).catch(e => {
    console.error("Error loading stock details:", e);
    if (container) container.innerHTML = '<p class="text-muted">Error al cargar detalles de stock</p>';
  });
}

// Sell a specific profile to a client (with selection modal)
function sellProfileToClient(perfilId) {
  // 1. Cargar lista de clientes para el select
  fetchWithCSRF('api.php?action=list_clients')
    .then(r => r.json())
    .then(clients => {
      // Construir opciones
      let options = {};
      if (Array.isArray(clients)) {
        clients.forEach(c => {
          options[c.id] = c.nombre;
        });
      }

      // 2. Mostrar SweetAlert con selector
      Swal.fire({
        title: 'Vender Perfil',
        text: 'Selecciona el cliente final:',
        input: 'select',
        inputOptions: options,
        inputPlaceholder: 'Selecciona un cliente',
        showCancelButton: true,
        confirmButtonText: 'Vender (Marcar Asignado)',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
          if (!value) {
            return 'Debes seleccionar un cliente';
          }
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const clientId = result.value;
          
          // 3. Confirmar precio (opcional, por ahora directo)
          // Proceder con la asignación
          fetchWithCSRF('api.php?action=assign_profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ perfil_id: perfilId, cliente_id: clientId })
          }).then(r => r.json()).then(resp => {
            if (resp.success) {
              Swal.fire('Vendido', 'Perfil asignado correctamente', 'success');
              
              // Intentar refrescar vistas
              if (typeof loadStock === 'function') loadStock();
              if (typeof loadAssignments === 'function') loadAssignments();
              if (typeof viewAccountUsers === 'function') {
                  // Si estamos dentro del modal de detalles, recargar esa vista
                  // Necesitamos saber el ID de cuenta padre, pero si no lo tenemos a mano, cerramos el modal o recargamos todo
                  // Mejor opción: cerrar modal de detalles para forzar refresh al abrir
                  closeModal('modal-account-view'); 
                  closeModal('modal-stock-details');
              }
              
            } else {
              Swal.fire('Error', resp.message || 'No se pudo asignar', 'error');
            }
          }).catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
      });
    })
    .catch(e => {
      console.error(e);
      Swal.fire('Error', 'No se pudieron cargar los clientes', 'error');
    });
}

function viewSaleDetails(id) {
  const profile = (window.salesMap && window.salesMap[id]) || null;

  if (!profile) {
    SwalError('No se encontraron detalles. Recarga la página.');
    return;
  }

  const email = profile.email_cuenta || 'N/A';
  const password = profile.password || 'N/A';
  const pin = profile.pin_perfil || 'N/A';
  const nombre = profile.nombre_perfil || 'N/A';
  // Use the calculated expiration from the main list (handles reseller vs direct logic correctly)
  const vence = profile.fecha_vencimiento ? (profile.fecha_vencimiento.split ? profile.fecha_vencimiento.split(' ')[0] : profile.fecha_vencimiento) : 'N/A';

  Swal.fire({
    title: 'Detalles de Venta',
    html: `
      <div style="text-align:left; font-size:1.1rem; line-height:1.8; background:rgba(255,255,255,0.03); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.1);">
        <div style="margin-bottom:8px; display:flex; justify-content:space-between;">
          <strong style="color:var(--text-muted);">Platforma:</strong> 
          <span style="color:var(--primary);">${profile.plataforma || 'N/A'}</span>
        </div>
        <hr style="border-color:rgba(255,255,255,0.1); margin:12px 0;">
        <div style="margin-bottom:8px; display:flex; justify-content:space-between;">
          <strong style="color:var(--text-muted);">📧 Email:</strong> 
          <span class="text-mono select-all" style="color:#fff;">${email}</span>
        </div>
        <div style="margin-bottom:8px; display:flex; justify-content:space-between;">
          <strong style="color:var(--text-muted);">🔑 Clave:</strong> 
          <span class="text-mono select-all" style="color:#fff;">${password}</span>
        </div>
        <hr style="border-color:rgba(255,255,255,0.1); margin:12px 0;">
          <div style="margin-bottom:8px; display:flex; justify-content:space-between;">
          <strong style="color:var(--text-muted);">👤 Perfil:</strong> 
          <span class="text-primary">${nombre}</span>
        </div>
        <div style="margin-bottom:8px; display:flex; justify-content:space-between;">
          <strong style="color:var(--text-muted);">🔢 PIN:</strong> 
          <span class="text-mono">${pin}</span>
        </div>
        <div style="margin-bottom:8px; display:flex; justify-content:space-between;">
          <strong style="color:var(--text-muted);">📅 Vence:</strong> 
          <span style="color:${new Date(vence) < new Date() ? 'var(--danger)' : 'var(--success)'}">${vence}</span>
        </div>
      </div>
      <div style="margin-top:20px; font-size:0.9rem; color:var(--text-muted);">
        <i class="ph-copy"></i> Puedes copiar y pegar estos datos al cliente
      </div>
    `,
    confirmButtonText: 'Cerrar',
    showCloseButton: true,
    width: '450px'
  });
}

// Update viewAccountUsers to fetch and show master details
function viewAccountUsers(id, email, isProfileId = false) {
  const emailEl = getEl('acc-detail-email'); if (emailEl) emailEl.textContent = 'Cargando...';
  const accList = getEl('acc-profiles-list'); if (accList) accList.innerHTML = 'Cargando...';
  const modal = getEl('modal-account-view'); if (modal) openModal('modal-account-view');

  // Load account details to get password and real date
  fetchWithCSRF(`api.php?action=get_account_details_full&id=${id}`) // New action for full details
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        if (accList) accList.innerHTML = 'Error cargando datos de la cuenta.';
        return;
      }
      const acc = res.data.cuenta || {};
      const profiles = res.data.perfiles || [];

      // Populate Header
      if (emailEl) emailEl.textContent = acc.email_cuenta || email;

      // Bind Edit Button
      const btnEdit = getEl('btn-edit-master-acc');
      if (btnEdit) {
        // Pass clean values
        const safeEmail = escapeJS(acc.email_cuenta || '');
        const safePass = escapeJS(acc.password || '');
        const safeDate = acc.fecha_pago_proveedor || '';
        btnEdit.onclick = function () {
          openEditMasterAccountModal(acc.id, safeEmail, safePass, safeDate);
        };
      }

      // Populate Profiles List
      if (!accList) return;
      accList.innerHTML = '';
      if (profiles.length === 0) {
        accList.innerHTML = '<p class="text-muted">No hay perfiles.</p>';
      } else {
        profiles.forEach(u => {
          const pid = u.id;
          const pinVal = u.pin_perfil || '';
          const fechaCliente = u.fecha_corte_cliente ? (u.fecha_corte_cliente.split ? u.fecha_corte_cliente.split(' ')[0] : u.fecha_corte_cliente) : '';
          const fechaReseller = u.fecha_venta_reseller ? (u.fecha_venta_reseller.split ? u.fecha_venta_reseller.split(' ')[0] : u.fecha_venta_reseller) : '';

          accList.innerHTML += `<div class="profile-row glass" style="padding:10px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">
                  <div style="flex:1; min-width:150px;">
                    <strong>${u.nombre_perfil || 'Perfil ' + u.slot_numero}</strong><br>
                    <small class="text-muted">Slot: ${u.slot_numero || ''}</small>
                  </div>
                  <div style="width:100px;">
                    <label class="mini-label">PIN</label>
                    <input id="acc-pin-${pid}" class="form-control" value="${pinVal}" placeholder="PIN">
                  </div>
                  <div style="width:130px;">
                    <label class="mini-label">Vence (Cliente)</label>
                    <input id="acc-fecha-c-${pid}" type="date" class="form-control" value="${fechaCliente}">
                  </div>
                  <div style="width:130px;">
                    <label class="mini-label">Vence (Reseller)</label>
                    <input id="acc-fecha-r-${pid}" type="date" class="form-control" value="${fechaReseller}">
                  </div>
                  <div style="width:90px; display:flex; flex-direction:column; gap:6px;">
                    <button class="btn-sm btn-primary" onclick="saveProfileAdmin(${u.id}, document.getElementById('acc-pin-${pid}').value, document.getElementById('acc-fecha-c-${pid}').value, document.getElementById('acc-fecha-r-${pid}').value)">Guardar</button>
                    <button class="btn-sm btn-ghost" style="font-size:0.7rem;" onclick="openMigrateModal(${u.id}, '${escapeJS(u.nombre_perfil || '')}')">Migrar</button>
                  </div>
                </div>`;
        });
      }
    })
    .catch(e => {
      console.error(e);
      if (accList) accList.innerHTML = '<p class="text-danger">Error de conexión</p>';
    });
}

function openEditMasterAccountModal(id, email, pass, date) {
  const modal = getEl('modal-edit-master-account');
  if (!modal) return;
  openModal('modal-edit-master-account');

  getEl('edit-master-id').value = id;
  getEl('edit-master-email').value = email;
  getEl('edit-master-pass').value = pass;
  getEl('edit-master-date').value = date ? date.split(' ')[0] : '';
}

// Master Account Edit Submit Handler
document.addEventListener('DOMContentLoaded', () => {
  const f = getEl('form-edit-master-account');
  if (f) {
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      const id = getEl('edit-master-id').value;
      const email = getEl('edit-master-email').value;
      const password = getEl('edit-master-pass').value;
      const fecha = getEl('edit-master-date').value;

      fetchWithCSRF('api.php?action=update_master_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, email, password, fecha })
      }).then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess('Cuenta actualizada');
          closeModal('modal-edit-master-account');
          // Refresh current view
          viewAccountUsers(id, email);
          loadAccounts(); // Refresh list background
        } else {
          SwalError(res.message || 'Error al actualizar');
        }
      }).catch(() => SwalError('Error de conexión'));
    });
  }
});

function openMigrateModal(oid, email) {
  const idEl = getEl('mig-origen-id'); if (idEl) idEl.value = oid;
  const nameEl = getEl('mig-origen-txt'); if (nameEl) nameEl.textContent = email;
  const destEl = getEl('mig-destino-select'); if (destEl) destEl.innerHTML = '<option>Buscando...</option>';
  const modal = getEl('modal-migrate'); if (modal) openModal('modal-migrate');
  fetch(`api.php?action=get_compatible_accounts&id=${oid}`)
    .then(r => r.json())
    .then(d => {
      if (!destEl) return;
      if (!d || !d.success) { destEl.innerHTML = `<option>${d?.message || 'Sin destinos'}</option>`; return; }
      destEl.innerHTML = '<option value="">-- Selecciona --</option>';
      (d.destinos || []).forEach(dest => {
        destEl.innerHTML += `<option value="${dest.id}">${dest.email_cuenta} (${dest.espacios_libres} libres)</option>`;
      });
    })
    .catch(() => { if (destEl) destEl.innerHTML = '<option>Sin destinos</option>'; });
}

function openRenewMaster(id, email, fechaActual) {
  const idEl = getEl('renew-master-id'); if (idEl) idEl.value = id;
  const nameEl = getEl('renew-master-name'); if (nameEl) nameEl.textContent = email;
  const dateEl = getEl('renew-master-date'); if (dateEl) { const d = new Date(); d.setDate(d.getDate() + 30); dateEl.value = d.toISOString().slice(0, 10); }
  const modal = getEl('modal-renew-master'); if (modal) openModal('modal-renew-master');
}

// Admin actions for profiles/orders
function saveProfileAdmin(id, pin, fecha_cliente, fecha_reseller) {
  fetchWithCSRF('api.php?action=update_profile_pin', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, pin, fecha_cliente, fecha_reseller })
  }).then(r => r.json()).then(res => {
    if (res.success) {
      SwalSuccess('Perfil actualizado');
      loadAccounts(); loadAssignments(); loadResellers();
    } else {
      SwalError(res.message || 'Error al guardar perfil');
    }
  }).catch(() => SwalError('Error de conexión'));
}

function releaseProfileAdmin(id) {
  SwalConfirm('¿Confirmas liberar este perfil?', () => {
    fetchWithCSRF('api.php?action=release_profile', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
    }).then(r => r.json()).then(res => {
      if (res.success) {
        SwalSuccess('Perfil liberado');
        loadAssignments(); loadAccounts(); loadResellers();
      } else SwalError(res.message || 'Error al liberar perfil');
    }).catch(() => SwalError('Error de conexión'));
  });
}

function renewProfileAdmin(id) {
  SwalConfirm('¿Renovar este perfil (30 días)?', () => {
    fetchWithCSRF('api.php?action=renew_profile', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) })
      .then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess('Renovado: ' + (res.nueva_fecha || ''));
          loadAssignments(); loadAccounts();
        } else SwalError(res.message || 'Error al renovar');
      }).catch(() => SwalError('Error de conexión'));
  });
}

function openEditSaleModal(id, nombreEnc, telEnc, emailEnc, fechaVencimiento, type) {
  const modal = getEl('modal-edit-sale');
  if (!modal) return;
  openModal('modal-edit-sale');
  getEl('edit-sale-id').value = id;
  // Set type (reseller or client) to help backend decide which date field to update
  getEl('edit-sale-type').value = type || 'cliente';

  try {
    getEl('edit-sale-nombre').value = decodeURIComponent(nombreEnc || '');
    getEl('edit-sale-telefono').value = decodeURIComponent(telEnc || '');
    getEl('edit-sale-email').value = decodeURIComponent(emailEnc || '');
  } catch (e) {
    getEl('edit-sale-nombre').value = nombreEnc || '';
    getEl('edit-sale-telefono').value = telEnc || '';
    getEl('edit-sale-email').value = emailEnc || '';
  }

  // Set date
  if (fechaVencimiento && fechaVencimiento !== 'null') {
    // Ensure YYYY-MM-DD format
    const iso = fechaVencimiento.split(' ')[0];
    getEl('edit-sale-fecha').value = iso;
  } else {
    getEl('edit-sale-fecha').value = '';
  }
}

// 6) Auto-sell (vigilar DOM)
let currentSellingPlatform = "";
function initAutoSell(plataforma) {
  currentSellingPlatform = plataforma;
  const platName = getEl('sell-plat-name'); if (platName) platName.textContent = plataforma;
  if (typeof renderClientSellList === 'function') renderClientSellList();
  const modal = getEl('modal-sell-select'); if (modal) openModal('modal-sell-select');
}

// 9) Guarded helpers
function loadDashboard() {
  loadFinancials(); // Cargar KPIs financieros
  loadTasa();       // Cargar tasa de dólar
  checkExpired();   // Cargar vencimientos
  loadChartData();  // Cargar datos para el gráfico
}

function loadAll() { loadDashboard(); loadClients(); loadAssignments(); loadSalesStats(); }

function loadAssignments() {
  const grid = getEl('sales-grid'); if (!grid) return; grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="text-muted">Cargando ventas...</p></div>';
  fetchWithCSRF('api.php?action=list_assignments')
    .then(r => r.json())
    .then(d => {
      const items = toArray(d); grid.innerHTML = '';
      window.salesMap = {}; // Reset cache

      if (items.length === 0) { grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Sin ventas activas</div>'; return; }

      items.forEach(a => {
        window.salesMap[a.id] = a; // Cache item

        const nombreEnc = encodeURIComponent(a.cliente_nombre || a.nombre_cliente || '');
        const telEnc = encodeURIComponent(a.cliente_telefono || a.telefono || '');
        const emailEnc = encodeURIComponent(a.cliente_email || a.email || '');
        const estadoEnc = encodeURIComponent(a.estado || a.estado_pedido || 'pendiente');

        let clienteNombre = a.cliente_nombre || a.nombre_cliente || 'Sin asignar';
        const clienteTipo = a.reseller_id ? 'Reseller' : 'Cliente';
        const badgeColor = a.reseller_id ? 'var(--secondary)' : 'var(--primary)';

        // Si es reseller, mostrar a quién pertenece
        if (a.reseller_id && a.reseller_nombre) {
          clienteNombre = `${clienteNombre} <br><small class="text-muted">💼 Partner: ${a.reseller_nombre}</small>`;
        }

        grid.innerHTML += `
          <div class="data-card">
            <div class="card-header">
              <div class="card-title">
                <strong>${a.plataforma || 'Sin plataforma'}</strong>
              </div>
              <span class="card-badge" style="background:rgba(${a.reseller_id ? '189,0,255' : '0,198,255'},0.15);color:${badgeColor};border:1px solid ${badgeColor};">${clienteTipo}</span>
            </div>
            <div class="card-body">
              <div class="data-row">
                <span>👤 Cliente:</span>
                <strong>${clienteNombre}</strong>
              </div>
              <div class="data-row">
                <span>📧 Email:</span>
                <span>${a.email_cuenta || 'N/A'}</span>
              </div>
              <div class="data-row">
                <span>🎭 Perfil:</span>
                <span>${a.nombre_perfil || 'N/A'}</span>
              </div>
              ${a.fecha_vencimiento ? `<div class="data-row"><span>📅 Vence:</span><span>${a.fecha_vencimiento.split(' ')[0]}</span></div>` : ''}
            </div>
            <div class="card-actions">
              <button class="btn-sm primary" onclick="viewSaleDetails(${a.id})">👁️ Ver</button>
              <button class="btn-sm success" onclick="renewProfileAdmin(${a.id})">↻ Renovar</button>
              <button class="btn-sm" onclick="releaseProfileAdmin(${a.id})">↩️ Liberar</button>
              <button class="btn-sm" onclick="openEditSaleModal(${a.id}, '${nombreEnc}', '${telEnc}', '${emailEnc}', '${a.fecha_vencimiento || ''}', '${a.reseller_id ? 'reseller' : 'cliente'}')">✏️ Editar</button>
            </div>
          </div>`;
      });
    }).catch(e => {
      console.error("Error loading assignments:", e);
      grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Error al cargar ventas</div>';
    });
}

function loadOrders(est = 'pendiente', btn) {
  if (btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }
  const grid = getEl('orders-grid'); if (!grid) return; grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="text-muted">Cargando pedidos...</p></div>';
  fetchWithCSRF(`api.php?action=list_orders&estado=${est}`)
    .then(r => r.json())
    .then(d => {
      const items = toArray(d); grid.innerHTML = '';
      if (items.length === 0) { grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Sin pedidos ' + est + 's</div>'; return; }
      items.forEach(o => {
        const estadoBadge = est === 'pendiente' ? 'rgba(255,187,0,0.15);color:var(--warning);border:1px solid var(--warning)' :
          est === 'aprobado' ? 'rgba(0,255,136,0.15);color:var(--success);border:1px solid var(--success)' :
            'rgba(255,0,85,0.15);color:var(--danger);border:1px solid var(--danger)';
        const fechaFormato = o.fecha_pedido ? new Date(o.fecha_pedido).toLocaleDateString('es-ES') : 'N/A';
        grid.innerHTML += `
          <div class="data-card">
            <div class="card-header">
              <div class="card-title">
                <strong>${o.nombre_producto || o.plataforma || 'Sin producto'}</strong>
              </div>
              <span class="card-badge" style="background:${estadoBadge};">${est.toUpperCase()}</span>
            </div>
            <div class="card-body">
              <div class="data-row">
                <span>👤 Cliente:</span>
                <strong>${o.cliente_nombre || 'N/A'}</strong>
              </div>
              <div class="data-row">
                <span>📞 Teléfono:</span>
                <span>${o.cliente_telefono || 'N/A'}</span>
              </div>
              <div class="data-row">
                <span>💰 Monto:</span>
                <strong style="color:var(--success)">$${o.precio_usd || '0.00'}</strong>
              </div>
              <div class="data-row">
                <span>📅 Fecha:</span>
                <span>${fechaFormato}</span>
              </div>
              ${o.metodo_pago ? `<div class="data-row"><span>💳 Método:</span><span>${o.metodo_pago}</span></div>` : ''}
               ${est === 'pendiente' ? `<div class="card-footer mt-3">
                   <button class="btn-primary full-width" onclick="openApproveResellerOrderModal(${o.id})">Aprobar y Entregar Credenciales</button>
                   <button class="btn-sm full-width" style="margin-top:6px" onclick="sendPaymentReminder(${o.id}, '${escapeJS(o.cliente_nombre || '')}', '${escapeJS(o.cliente_telefono || '')}', '${fechaFormato}', '${escapeJS(o.plataforma || o.nombre_producto || '')}')">Cobrar</button>
                 </div>` : ''}
            </div>
          </div>`;
      });
    }).catch(e => {
      console.error("Error loading orders:", e);
      grid.innerHTML = '<div class="text-muted" style="text-align:center; padding:40px;">Error al cargar pedidos</div>';
    });
}

function openApproveResellerOrderModal(pedidoId) {
  const modal = getEl('modal-approve-reseller-order');
  if (!modal) {
    console.error('Modal modal-approve-reseller-order no encontrado');
    return;
  }
  document.getElementById('approve-pedido-id').value = pedidoId;
  document.getElementById('approve-email').value = '';
  document.getElementById('approve-password').value = '';
  openModal('modal-approve-reseller-order');
}

function loadEmailAccounts() {
  const s = getEl('email-selector'); if (!s) return; fetchWithCSRF('api.php?action=list_email_accounts') // Usar fetchWithCSRF aquí también
    .then(r => r.json())
    .then(d => {
      const items = toArray(d); s.innerHTML = '<option value="">-- Seleccionar --</option>';
      items.forEach(a => { s.innerHTML += `<option value="${a.id}">${a.email}</option>`; });
    }).catch(e => {
      console.error("Error loading email accounts:", e);
      s.innerHTML = '<option value="">Error</option>';
    });
}

function loadCoupons() {
  fetchWithCSRF('api.php?action=list_coupons')
    .then(r => r.json())
    .then(d => {
      const tbody = getEl('tabla-cupones-body'); if (!tbody) return; tbody.innerHTML = '';
      const items = toArray(d);
      if (items.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Sin cupones</td></tr>'; return; }
      items.forEach(c => {
        const estadoText = c.activo == 1 ? 'Activo' : 'Inactivo';
        const estadoClass = c.activo == 1 ? 'text-success' : 'text-danger';
        tbody.innerHTML += `<tr>
          <td>${c.codigo}</td>
          <td>${c.descuento}%</td>
          <td>${c.usos_actuales}/${c.usos_max}</td>
          <td class="${estadoClass}">${estadoText}</td>
          <td><button class="btn-sm btn-danger" onclick="deleteCoupon(${c.id})">🗑️</button></td>
        </tr>`;
      });
    })
    .catch(e => {
      console.error("Error loading coupons:", e);
      const tbody = getEl('tabla-cupones-body'); if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Error al cargar cupones</td></tr>';
    });
}

// Create a coupon (admin action)
function saveCoupon() {
  const codeEl = document.getElementById('new-coupon-code');
  const descuentoEl = document.getElementById('new-coupon-descuento');
  const usosEl = document.getElementById('new-coupon-usos');
  const code = codeEl?.value?.trim();
  const descuento = parseFloat(descuentoEl?.value);
  const usos = parseInt(usosEl?.value, 10);
  if (!code || isNaN(descuento) || isNaN(usos)) {
    SwalError('Por favor complete código, descuento y usos.');
    return;
  }
  fetchWithCSRF('api.php?action=save_coupon', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ codigo: code, descuento: descuento, usos: usos })
  }).then(r => r.json()).then(res => {
    if (res && res.success) {
      SwalSuccess('Cupón creado');
      // Limpiar campos
      if (codeEl) codeEl.value = '';
      if (descuentoEl) descuentoEl.value = '';
      if (usosEl) usosEl.value = '';
      loadCoupons();
    } else {
      SwalError(res?.message || 'Error al crear cupón');
    }
  }).catch(() => SwalError('Error de conexión'));
}

// Create an invitation code for resellers (admin action)
function generateInviteCode() {
  fetchWithCSRF('api.php?action=generate_invite_code', {
    method: 'GET'
  }).then(r => r.json()).then(res => {
    if (res && res.success) {
      const code = res.codigo;
      SwalSuccess('Nuevo código: ' + code);
      // Mostrar área de copiar/guardar
      let area = document.getElementById('invite-copy-area');
      const waHref = 'https://wa.me/?text=' + encodeURIComponent('Invitación reseller: usa este código ' + code + ' para registrarte. Regístrate en: https://levelplaymax.com/reseller/register.php?invite=' + code);
      if (!area) {
        const block = document.getElementById('invite-block');
        if (block) {
          block.insertAdjacentHTML('beforeend', `
            <div id="invite-copy-area" style="display:flex; align-items:center; gap:8px; margin-top:6px;">
              <input id="invite-code-display" type="text" value="${code}" readonly class="form-control" style="width:180px;">
              <button class="btn-primary" onclick="copyInviteMessage('${code}')">Copiar mensaje</button>
              <a id="wa-link" href="${waHref}" target="_blank" class="btn-primary" style="text-decoration:none;">WhatsApp</a>
            </div>
          `);
        }
      } else {
        const input = document.getElementById('invite-code-display');
        if (input) input.value = code;
        const wa = document.getElementById('wa-link');
        if (wa) wa.href = waHref;
      }
    } else {
      SwalError(res?.message || 'Error creando código de invitación');
    }
  }).catch(() => SwalError('Error de conexión'));
}

function copyInviteMessage(code) {
  const text = `Invitación reseller: usa este código ${code} para registrarte. Regístrate en: https://levelplaymax.com/reseller/register.php?invite=${code}`;
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      SwalSuccess('Mensaje copiado al portapapeles');
    }).catch(() => SwalError('No se pudo copiar'));
  } else {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      SwalSuccess('Mensaje copiado al portapapeles');
    } catch (e) {
      SwalError('No se pudo copiar');
    } finally {
      document.body.removeChild(ta);
    }
  }
}

// 12) Guard: openMigrateModal, openRenewMaster, viewStockDetails already guarded in their implementations above

// 7) Guards for DOM write points (example utilities)
function safeSetText(id, text) { const el = getEl(id); if (el) el.textContent = text; }

let mainChartInstance = null; // Para controlar la instancia del Chart.js

// 15) Enviar recordatorio de pago por WhatsApp desde el panel (ventas activas)
function sendPaymentReminder(saleId, customerName, customerPhone, dueDateDisplay, platform) {
  const name = customerName || '';
  const phoneRaw = (customerPhone || '').toString().replace(/\D/g, '');
  // Construimos el mensaje base
  const datePart = dueDateDisplay ? (dueDateDisplay) : '';
  const message = `Hola ${name}, recordatorio de pago para ${platform || ''}${datePart ? ' vence el ' + datePart : ''}. Por favor realice el pago para mantener tu suscripción.`;
  if (phoneRaw) {
    const waHref = `https://wa.me/${phoneRaw}?text=${encodeURIComponent(message)}`;
    window.open(waHref, '_blank');
  }
  // Copiar al portapapeles para facilitar pegarlo en otros canales
  if (navigator.clipboard) {
    navigator.clipboard.writeText(message).then(() => {
      SwalSuccess('Recordatorio copiado al portapapeles');
    }).catch(() => {});
  }
}

function loadFinancials() {
  fetchWithCSRF('api.php?action=get_financial_report')
    .then(r => r.json())
    .then(data => {
      if (data) {
        safeSetText('stat-ingresos', `$${data.ingresos || '0.00'}`);
        safeSetText('stat-ganancia', `$${data.ganancia || '0.00'}`);
      }
    })
    .catch(e => console.error("Error loading financials:", e));

  // Cargar clientes totales
  fetchWithCSRF('api.php?action=list_clients')
    .then(r => r.json())
    .then(d => {
      const items = toArray(d);
      safeSetText('stat-clientes', items.length);
    })
    .catch(e => console.error("Error loading total clients for dashboard:", e));

  // Cargar ventas activas totales
  fetchWithCSRF('api.php?action=get_sales_stats')
    .then(r => r.json())
    .then(d => {
      const items = toArray(d);
      const totalVentas = items.reduce((sum, item) => sum + parseInt(item.total || 0), 0);
      safeSetText('stat-ventas', totalVentas);
    })
    .catch(e => console.error("Error loading active sales for dashboard:", e));
}

function loadSalesStats() {
  fetchWithCSRF('api.php?action=get_sales_stats')
    .then(r => r.json())
    .then(d => {
      const grid = getEl('sales-stats-grid'); if (!grid) return; grid.innerHTML = '';
      const items = toArray(d);
      if (items.length === 0) { grid.innerHTML = '<p class="text-center text-muted">Sin estadísticas de ventas.</p>'; return; }

      items.forEach(stat => {
        grid.innerHTML += `<div class="mini-stat-card glass">
          <strong>${stat.plataforma}</strong>
          <span>${stat.total} Asignadas</span>
          <small>Directas: ${stat.directas} | Resellers: ${stat.resellers}</small>
        </div>`;
      });
    })
    .catch(e => {
      console.error("Error loading sales stats:", e);
      const grid = getEl('sales-stats-grid'); if (grid) grid.innerHTML = '<p class="text-center text-muted">Error al cargar estadísticas.</p>';
    });
}

function loadTasa() {
  fetchWithCSRF('api.php?action=get_tasa')
    .then(r => r.json())
    .then(data => {
      if (data && data.tasa) {
        safeSetText('live-rate-display', data.tasa);
      }
    })
    .catch(e => console.error("Error loading tasa:", e));
}

function checkExpired() {
  fetchWithCSRF('api.php?action=check_expired')
    .then(r => r.json())
    .then(d => {
      const list = getEl('expired-list'); if (!list) return; list.innerHTML = '';
      const items = toArray(d);
      if (items.length === 0) { list.innerHTML = '<p class="text-center text-muted mt-2">No hay vencimientos próximos.</p>'; return; }

      items.forEach(item => {
        const icon = item.tipo === 'cliente' ? 'ph-user' : 'ph-key';
        const color = item.tipo === 'cliente' ? 'var(--warning)' : 'var(--danger)';
        const daysLeft = Math.ceil((new Date(item.fecha) - new Date()) / (1000 * 60 * 60 * 24));
        const dateDisplay = new Date(item.fecha).toLocaleDateString();

        list.innerHTML += `<div class="expiration-item">
          <i class="ph ${icon}" style="color:${color}"></i>
          <div class="expiration-info">
            <strong>${item.titulo}</strong>
            <small>${item.detalle} - Vence ${dateDisplay} (${daysLeft} días)</small>
          </div>
        </div>`;
      });
    })
    .catch(e => {
      console.error("Error loading expired items:", e);
      const list = getEl('expired-list'); if (list) list.innerHTML = '<p class="text-center text-muted mt-2">Error al cargar vencimientos.</p>';
    });
}

function loadChartData() {
  fetchWithCSRF('api.php?action=get_chart_data')
    .then(r => r.json())
    .then(data => {
      const canvas = getEl('mainChart');
      if (!canvas || !data || !data.ventas_semana) return;

      const labels = data.ventas_semana.map(v => v.fecha);
      const ventasData = data.ventas_semana.map(v => v.total);

      if (mainChartInstance) {
        mainChartInstance.destroy();
      }

      mainChartInstance = new Chart(canvas, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Ventas Semanales',
            data: ventasData,
            borderColor: '#00c6ff',
            backgroundColor: 'rgba(0, 198, 255, 0.2)',
            tension: 0.3,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(255,255,255,0.1)' },
              ticks: { color: '#fff' }
            },
            x: {
              grid: { display: false },
              ticks: { color: '#fff' }
            }
          },
          plugins: {
            legend: {
              labels: { color: '#fff' }
            }
          }
        }
      });
    })
    .catch(e => console.error("Error loading chart data:", e));
}

// Funciones de la barra superior y otras acciones
function syncDolar() {
  fetchWithCSRF('api.php?action=fetch_dolar_auto')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        SwalSuccess(data.message);
        loadTasa(); // Recargar la tasa para mostrar el nuevo valor
      } else {
        SwalError(data.message || 'Error al sincronizar tasa');
      }
    })
    .catch(e => {
      console.error("Error syncing dolar rate:", e);
      SwalError("Error de conexión al sincronizar tasa");
    });
}

function downloadBackup() {
  SwalConfirm('¿Estás seguro de que quieres descargar una copia de seguridad de la base de datos?', () => {
    window.location.href = 'api.php?action=backup_db';
    SwalSuccess('Copia de seguridad iniciada. Puede tardar unos segundos.');
  });
}

function downloadPDF() {
  SwalConfirm('¿Estás seguro de que quieres generar y descargar el reporte de precios en PDF?', () => {
    window.location.href = 'api.php?action=download_pricelist'; // El action en PHP es download_pricelist
    SwalSuccess('Generando reporte PDF. Puede tardar unos segundos.');
  });
}

function configTelegram() {
  SwalPrompt('Introduce el nuevo enlace del canal de Telegram para marketing:').then(result => {
    if (result.isConfirmed && result.value) {
      fetchWithCSRF('api.php?action=update_telegram_link', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ link: result.value })
      })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            SwalSuccess('Enlace de Telegram actualizado con éxito.');
          } else {
            SwalError(data.message || 'Error al actualizar enlace de Telegram.');
          }
        })
        .catch(e => {
          console.error("Error updating Telegram link:", e);
          SwalError("Error de conexión al actualizar enlace de Telegram.");
        });
    }
  });
}

function logout() {
  SwalConfirm('¿Estás seguro de que quieres cerrar tu sesión?', () => {
    fetchWithCSRF('api.php?action=logout', { method: 'POST' })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          window.location.href = 'login.php';
        } else {
          SwalError(data.message || 'Error al cerrar sesión.');
        }
      })
      .catch(e => {
        console.error("Error logging out:", e);
        SwalError("Error de conexión al cerrar sesión.");
      });
  });
}

// 13) OneSignal registration helper (exposed for compatibility)
function registrarDispositivoAdmin(userId) {
  fetch('api.php?action=register_device', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ player_id: userId })
  }).then(r => r.json()).then(() => { }).catch(() => { });
}

// 14) Autobind ensure showTab on first load if page loaded before script
if (typeof window.showTab !== 'function') {
  window.showTab = function (id) { /* fallback to global showTab defined above in this file if needed */ };
  window.showtab = window.showTab;
}

// Initialize minimal on load
document.addEventListener('DOMContentLoaded', () => {
  if (typeof loadAll === 'function') loadAll(); // Carga inicial del dashboard y otros elementos
  setTimeout(() => { const loader = getEl('preloader'); if (loader) loader.classList.add('hide'); }, 500);
  
  // Handlers para formularios
  const formCliente = getEl('form-cliente');
  if (formCliente) {
    formCliente.addEventListener('submit', (e) => {
      e.preventDefault();
      const nombre = getEl('cli-nombre').value;
      const telefono = getEl('cli-telefono').value;
      const email = getEl('cli-email').value;
      
      fetchWithCSRF('api.php?action=add_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre, telefono, email })
      }).then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess('Cliente agregado');
          closeModal('modal-cliente');
          loadClients();
        } else {
          SwalError(res.message || 'Error al agregar cliente');
        }
      }).catch(() => SwalError('Error de conexión'));
    });
  }

  const formReseller = getEl('form-new-reseller');
  if (formReseller) {
    formReseller.addEventListener('submit', (e) => {
      e.preventDefault();
      const nombre = getEl('res-name').value;
      const email = getEl('res-email').value;
      const pass = getEl('res-pass').value;
      
      fetchWithCSRF('api.php?action=add_reseller', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre, email, pass })
      }).then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess('Distribuidor creado');
          closeModal('modal-new-reseller');
          loadResellers();
        } else {
          SwalError(res.message || 'Error al crear distribuidor');
        }
      }).catch(() => SwalError('Error de conexión'));
    });
  }

  if (window.OneSignal && typeof window.OneSignal.getUserId === 'function') {
    window.OneSignal.getUserId(function (userId) {
      if (userId) {
        console.log('OneSignal User ID:', userId);
        registrarDispositivoAdmin(userId);
      }
    });
  }
  // expose showTab alias if missing
  // Ya no es necesario aquí, se llama al inicio
  // ensureShowTabGlobal();
});

// Exponer funciones críticas en window para que los onclick inline funcionen siempre
(function exposeGlobals() {
  const names = [
    'viewAccountUsers', 'openEditSaleModal', 'renewProfileAdmin', 'releaseProfileAdmin',
    'sellProfileToClient', 'openMigrateModal', 'saveProfileAdmin', 'switchResellerView'
  ];
  names.forEach(n => {
    if (typeof window[n] !== 'function' && typeof eval(n) === 'function') {
      window[n] = eval(n);
    } else if (typeof window[n] !== 'function' && typeof window[n] === 'undefined') {
      try {
        const fn = this[n];
        if (typeof fn === 'function') window[n] = fn;
      } catch (e) { }
    }
  });
})();

// --- NEW RESELLER VIEW LOGIC (Fixing missing functions) ---

// --- RESELLER VIEW LOGIC (Mantenida al final del archivo para consistencia) ---






function solveReport(id) {
  SwalConfirm('¿Marcar como solucionado?', () => {
    fetchWithCSRF('api.php?action=solve_report', { method: 'POST', body: JSON.stringify({ id }) })
      .then(r => r.json()).then(d => {
        if (d.success) { SwalSuccess('Listo'); loadAllReports(); }
      });
  });
}

// --- CATALOGO LOGIC ---

// --- CATALOGO LOGIC ---
function loadCatalog() {
  const container = getEl('catalog-grid'); // Changed to grid container
  if (!container) return;
  container.innerHTML = '<div class="spinner"></div>';

  fetchWithCSRF('api.php?action=get_catalogo')
    .then(r => r.json())
    .then(data => {
      console.log('📋 Productos cargados:', data);
      container.innerHTML = '';
      if (!data || data.length === 0) {
        container.innerHTML = '<p class="text-center text-muted">No hay productos en el catálogo.</p>';
        return;
      }

      data.forEach(p => {
        console.log(`  - ${p.nombre}: tipo_entrega="${p.tipo_entrega}"`);
        container.innerHTML += `
            <div class="stock-item">
                <div class="stock-name">${p.nombre}</div>
                <div class="stock-qty-badge" style="margin-top:5px; font-size:0.8rem; color:#aaa;">${p.categoria || 'General'}</div>
                
                <div style="display:flex; justify-content:space-between; margin-top:10px; font-size:0.9rem;">
                   <span style="color:var(--text-muted)">Costo: $${parseFloat(p.precio).toFixed(2)}</span>
                   <span style="color:var(--success); font-weight:bold;">Venta: $${parseFloat(p.precio_reseller || 0).toFixed(2)}</span>
                </div>

                <div style="display:flex; gap:5px; margin-top:10px;">
                    <button class="btn-sm btn-secondary full-width" onclick="openProductModalById(${p.id})">Editar</button>
                    <button class="btn-sm btn-danger" onclick="deleteProduct(${p.id})" style="width:30%;">🗑</button>
                </div>
            </div>
        `;
      });
    })
    .catch(e => {
      console.error(e);
      container.innerHTML = '<p class="text-center text-danger">Error al cargar catálogo.</p>';
    });
}

function openProductModalById(id) {
  fetchWithCSRF(`api.php?action=get_product&id=${id}`)
    .then(r => r.json())
    .then(p => {
      if (p) openProductModal(p);
      else SwalError('No se encontró el producto');
    }).catch(() => SwalError('Error de conexión'));
}

function openProductModal(prod = null) {
  // Usar el modal correcto: modal-product (no modal-producto)
  const modal = getEl('modal-product');
  if (!modal) {
    console.error('Modal modal-product no encontrado');
    return;
  }

  openModal('modal-product');

  // Obtener referencias a los campos del formulario correcto
  const title = getEl('modal-product-title');
  const idIn = getEl('prod-id');
  const nameIn = getEl('prod-name');
  const catIn = getEl('prod-cat');
  const priceIn = getEl('prod-price');
  const priceResellerIn = getEl('prod-price-reseller');
  const descIn = getEl('prod-desc');
  const typeIn = getEl('prod-type');

  if (prod) {
    // Modo edición: cargar todos los datos del producto
    if (title) title.textContent = 'Editar Producto';
    if (idIn) idIn.value = prod.id || '';
    if (nameIn) nameIn.value = prod.nombre || '';
    if (catIn) catIn.value = prod.categoria || 'streaming';
    if (priceIn) priceIn.value = prod.precio || '';
    if (priceResellerIn) priceResellerIn.value = prod.precio_reseller || '';
    if (descIn) descIn.value = prod.descripcion || '';
    // IMPORTANTE: Cargar el tipo_entrega correctamente
    if (typeIn) typeIn.value = prod.tipo_entrega || 'credenciales';
  } else {
    // Modo nuevo producto: limpiar formulario
    if (title) title.textContent = 'Nuevo Producto';
    const form = getEl('form-product');
    if (form) form.reset();
    if (idIn) idIn.value = '';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const fp = getEl('form-product');
  if (fp) {
    fp.addEventListener('submit', (e) => {
      e.preventDefault();
      const id = getEl('prod-id').value;
      const nombre = getEl('prod-name').value;
      const cat = getEl('prod-cat').value;
      const precio = getEl('prod-price').value;
      const precio_reseller = getEl('prod-price-reseller').value;
      const desc = getEl('prod-desc').value;
      const tipo = getEl('prod-type').value;

      // DEBUG: Verificar qué se está enviando
      const payload = { id, nombre, cat, precio, precio_reseller, desc, tipo_entrega: tipo };
      console.log('📦 Guardando producto:', payload);

      fetchWithCSRF('api.php?action=save_product', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(r => r.json()).then(res => {
        console.log('✅ Respuesta del servidor:', res);
        if (res.success) {
          SwalSuccess('Producto guardado');
          closeModal('modal-product');
          loadCatalog();
        } else {
          SwalError('Error al guardar');
        }
      }).catch(err => {
        console.error('❌ Error al guardar:', err);
        SwalError('Error de conexión');
      });
    });
  }
});

function deleteProduct(id) {
  Swal.fire({
    title: '¿Eliminar producto?',
    text: "No podrás revertir esto",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Sí, eliminar'
  }).then((result) => {
    if (result.isConfirmed) {
      fetchWithCSRF('api.php?action=delete_product', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      }).then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess('Eliminado');
          loadCatalog();
        } else {
          SwalError('Error al eliminar');
        }
      });
    }
  })
}

function deleteAccount(id) {
  Swal.fire({
    title: '¿ELIMINAR CUENTA MAESTRA?',
    text: "Se eliminarán TODOS los perfiles asociados y su historial. Esta acción es IRREVERSIBLE.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: '¡SÍ, ELIMINAR TODO!',
    background: '#1a0000' // Dark red warning bg
  }).then((result) => {
    if (result.isConfirmed) {
      fetchWithCSRF('api.php?action=delete_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      }).then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess('Cuenta eliminada correctamente');
          closeModal('modal-edit-master-account');
          closeModal('modal-account-view'); // Close the details view too
          loadAccounts();
        } else {
          SwalError(res.message || 'Error al eliminar');
        }
      });
    }
  })
}

// --- SMART STOCK LOGIC ---

function loadPlatformOptions() {
  const sel = getEl('cta-plataforma');
  if (!sel) return;

  // Reset but keep loading message
  sel.innerHTML = '<option value="">Cargando...</option>';

  fetchWithCSRF('api.php?action=get_catalogo')
    .then(r => r.json())
    .then(data => {
      sel.innerHTML = '<option value="">Selecciona...</option>';
      if (data && Array.isArray(data)) {
        // Unique names just in case, use Map to keep obj if needed, or just names
        // User said "tal y como estan escritas", so distinct names
        const names = [...new Set(data.map(p => p.nombre))].sort();
        names.forEach(n => {
          const opt = document.createElement('option');
          opt.value = n;
          opt.textContent = n;
          sel.appendChild(opt);
        });
      }
    })
    .catch(() => sel.innerHTML = '<option value="">Error carga</option>');
}

function calculateAccountSlots() {
  const plat = (getEl('cta-plataforma').value || '').toLowerCase();
  const tipo = getEl('cta-tipo').value; // 'pantalla' or 'completa'
  const slotsInput = getEl('cta-slots');
  const feedback = getEl('cta-feedback');
  const btn = getEl('btn-save-account');

  if (!slotsInput) return; // Guard

  if (!plat) {
    slotsInput.value = 0;
    feedback.textContent = '';
    return;
  }

  let slots = 0;
  let msg = '';
  let warn = false;

  if (tipo === 'completa') {
    slots = 1; // Generic slot for the full account
    msg = "Cuenta Completa: Se gestionará como una unidad (Email:Pass). Sin PINs.";
  } else {
    // Pantalla Logic
    if (plat.includes('netflix') || plat.includes('crunchyroll') || plat.includes('hbo') || plat.includes('vix')) {
      slots = 5;
      msg = "Esta plataforma genera 5 perfiles con PIN.";
    } else if (plat.includes('disney')) {
      slots = 7;
      msg = "Disney+ permite 7 perfiles.";
    } else if (plat.includes('paramount') || plat.includes('prime')) {
      slots = 6;
      msg = "Esta plataforma permite 6 perfiles.";
    } else {
      // Other platforms
      slots = 1; // Default
      msg = "⚠ Plataforma no automatizada. Necesita verificación manual.";
      // warn = true; 
    }
  }

  slotsInput.value = slots;
  feedback.textContent = msg;
  feedback.style.color = warn ? 'var(--danger)' : 'var(--warning)';
}

// Handlers are auto-bound by DOMContentLoaded usually, but let's explicity bind this specific one here to be safe if loaded dynamically
// Note: We already added a DOMContentLoaded listener in previous steps, but it might not cover dynamic modal opens if they were not in DOM.
// But modal-cuenta is in DOM. 
// We will add the listener specific to form-cuenta here again just to be sure, or better, keep the code clean.
// I'll add the listener block below.

document.addEventListener('DOMContentLoaded', () => {
  const formCta = getEl('form-cuenta');
  if (formCta) {
    // Remove old listeners? Hard to do without ref. Just add new one. 
    // To avoid duplicates, we can check a flag or just assume this is the main init.
    formCta.addEventListener('submit', (e) => {
      e.preventDefault();
      const plataforma = getEl('cta-plataforma').value;
      const tipo = getEl('cta-tipo').value;
      const email = getEl('cta-email').value;
      const password = getEl('cta-pass').value;
      const fecha = getEl('cta-fecha').value;
      const costo = getEl('cta-costo').value;
      const slots = getEl('cta-slots').value;

      if (!plataforma) return SwalError('Selecciona plataforma');

      const payload = {
        plataforma: plataforma, // Send raw name as in catalog
        email: email,
        password: password,
        fecha_pago: fecha,
        costo: costo,
        slots: slots,
        tipo_venta: tipo
      };

      fetchWithCSRF('api.php?action=add_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(r => r.json()).then(res => {
        if (res.success) {
          SwalSuccess('Cuenta Agregada Correctamente');
          closeModal('modal-cuenta');
          formCta.reset();
          if (typeof loadAccounts === 'function') loadAccounts();
        } else {
          SwalError(res.message || 'Error al agregar');
        }
      });
    });
  }
});



// --- MISSING FUNCTIONS IMPLEMENTATION ---

function loadResellerSales() {
  const container = getEl('reseller-sales-body'); // Check ID in admin.php
  if (!container) return;
  container.innerHTML = '<tr><td colspan="6">Cargando...</td></tr>';

  fetchWithCSRF('api.php?action=get_reseller_sales')
    .then(r => r.json())
    .then(data => {
      if (!data || !data.length) {
        container.innerHTML = '<tr><td colspan="6">Sin ventas registradas</td></tr>';
        return;
      }
      container.innerHTML = '';
      data.forEach(s => {
        container.innerHTML += `
                    <tr>
                        <td>${s.reseller_name}</td>
                        <td>${s.plataforma}</td>
                        <td>${s.nombre_perfil || 'Completa'}</td>
                        <td>${s.pin_perfil || '--'}</td>
                        <td>${s.fecha_venta_reseller}</td>
                        <td>Activo</td> 
                    </tr>
                `;
      });
    })
    .catch(() => container.innerHTML = '<tr><td colspan="6">Error</td></tr>');
}





function filterResellerSales() {
  const input = document.getElementById('search-reseller-sales');
  if (!input) return;
  const filter = input.value.toUpperCase();
  const table = document.getElementById('table-reseller-sales');
  if (!table) return;
  const tr = table.getElementsByTagName('tr');

  for (let i = 0; i < tr.length; i++) {
    // Skip header if in thead, usually table.getElementsByTagName('tr') gets everything.
    // If row is th, skip.
    if (tr[i].getElementsByTagName('th').length > 0) continue;

    const tds = tr[i].getElementsByTagName('td');
    let visible = false;
    // Search all columns
    for (let j = 0; j < tds.length; j++) {
      if (tds[j].textContent.toUpperCase().indexOf(filter) > -1) {
        visible = true;
        break;
      }
    }
    tr[i].style.display = visible ? "" : "none";
  }
}

// --- MASTER ACCOUNT EDIT & ASSOCIATES ---

function openEditMasterAccount(id) {
  const modal = getEl('modal-edit-master-account');
  if (!modal) return;

  // Reset fields
  getEl('edit-master-id').value = id;
  getEl('edit-master-email').value = 'Cargando...';
  getEl('edit-master-pass').value = '';
  getEl('edit-master-date').value = '';
  const list = getEl('assoc-list');
  if (list) list.innerHTML = '<p class="text-muted text-sm">Cargando...</p>';

  openModal('modal-edit-master-account');

  fetchWithCSRF(`api.php?action=get_account_details_full&id=${id}`)
    .then(r => r.json())
    .then(res => {
      console.log('API Response:', res); // DEBUG

      if (!res || !res.success || !res.data) {
        console.warn('Invalid msg', res);
        SwalError('Error cargando datos');
        return;
      }

      const acc = res.data.cuenta || {};
      const perfiles = res.data.perfiles || [];

      try {
        if (getEl('edit-master-email')) getEl('edit-master-email').value = acc.email_cuenta || '';
        if (getEl('edit-master-pass')) getEl('edit-master-pass').value = acc.password || '';
        if (getEl('edit-master-date')) getEl('edit-master-date').value = acc.fecha_pago_proveedor || '';
      } catch (ex) { console.error(ex); }

      // Populate Associates
      const list = getEl('assoc-list');
      if (!list) return;

      if (perfiles.length > 0) {
        let html = '<table class="table-sm" style="width:100%; color:var(--text-primary);">';
        perfiles.forEach(p => {
          let assignedTo = '<span class="text-muted">Libre</span>';
          if (p.cliente_nombre) assignedTo = `👤 ${p.cliente_nombre}`;
          if (p.reseller_nombre) assignedTo = `🤝 Partner: ${p.reseller_nombre}`;

          html += `<tr>
                    <td style="padding:4px;">${p.nombre_perfil || 'Perfil'}</td>
                    <td style="padding:4px;" class="text-right">${assignedTo}</td>
                </tr>`;
        });
        html += '</table>';
        list.innerHTML = html;
      } else {
        list.innerHTML = '<p class="text-muted text-sm">Sin perfiles/asociados (0).</p>';
      }
    })
    .catch(e => {
      console.error(e);
      if (getEl('assoc-list')) getEl('assoc-list').innerHTML = `<p class="text-danger">Error JS: ${e.message}</p>`;
    });
}

// --- SIDEBAR & SEARCH ---

function toggleSidebar() {
  const sb = getEl('main-sidebar');
  if (sb) sb.classList.toggle('active'); // CSS likely uses .active or .show
}

document.addEventListener('DOMContentLoaded', () => {
  // 2. Global Search Binding
  const searchInput = getEl('global-search');
  if (searchInput) {
    searchInput.addEventListener('keyup', (e) => {
      handleGlobalSearch(e.target.value.toLowerCase());
    });
  }
});

function handleGlobalSearch(term) {
  // Determine active tab context
  // We check which view-section is active

  // 1. Clients
  const clientsSection = getEl('clientes');
  if (clientsSection && clientsSection.classList.contains('active')) {
    filterGridOrTable('clients-grid', term); // Assuming clients-grid
  }

  // 2. Master Accounts
  const accSection = getEl('cuentas');
  if (accSection && accSection.classList.contains('active')) {
    filterGridOrTable('accounts-grid', term);
  }

  // 3. Stock
  const stockSection = getEl('stock');
  if (stockSection && stockSection.classList.contains('active')) {
    filterGridOrTable('stock-grid', term);
  }

  // 4. Products (Catalog)
  const catSection = getEl('catalogo');
  if (catSection && catSection.classList.contains('active')) {
    filterGridOrTable('catalog-table', term);
  }

  // 5. Resellers Sales (Delegated already but let's include generic)
  const resSection = getEl('resellers_admin');
  if (resSection && resSection.classList.contains('active')) {
    filterGridOrTable('resellers-grid', term);
    filterGridOrTable('table-reseller-sales', term);
  }
}

function filterGridOrTable(id, term) {
  const el = getEl(id);
  if (!el) return;

  // Is it a Table?
  if (el.tagName === 'TABLE' || el.querySelector('table')) {
    // Basic table filter
    const rows = el.querySelectorAll('tbody tr');
    rows.forEach(r => {
      r.style.display = r.innerText.toLowerCase().includes(term) ? '' : 'none';
    });
  } else {
    // Is it a Grid of Cards?
    const cards = el.querySelectorAll('.data-card, .client-card, .account-card');
    cards.forEach(c => {
      c.style.display = c.innerText.toLowerCase().includes(term) ? 'flex' : 'none';
    });
  }
}

// ========================================
// FUNCIONES PARA GESTIÓN DE RESELLERS
// ========================================

/**
 * Cambiar entre las diferentes vistas de la sección de resellers
 * @param {string} view - Nombre de la vista: 'users', 'sales', 'reports', 'requests'
 */
function switchResellerView(view) {
  // Ocultar todas las vistas de resellers
  const views = ['users', 'sales', 'reports', 'requests'];
  views.forEach(v => {
    const viewEl = getEl(`reseller-view-${v}`);
    if (viewEl) viewEl.style.display = 'none';
  });

  // Remover clase active de todos los pills
  document.querySelectorAll('.tabs-pills .pill').forEach(p => p.classList.remove('active'));

  // Mostrar vista seleccionada
  const targetView = getEl(`reseller-view-${view}`);
  if (targetView) targetView.style.display = 'block';

  // Activar pill correspondiente (buscar el botón que llamó esta función)
  const pills = document.querySelectorAll('.tabs-pills .pill');
  pills.forEach(pill => {
    const onclick = pill.getAttribute('onclick') || '';
    if (onclick.includes(`'${view}'`) || onclick.includes(`"${view}"`)) {
      pill.classList.add('active');
    }
  });

  // Cargar datos según la vista seleccionada
  if (view === 'users') {
    if (typeof loadResellers === 'function') loadResellers();
  } else if (view === 'sales') {
    if (typeof loadResellerSales === 'function') loadResellerSales();
  } else if (view === 'reports') {
    loadAllReports();
  } else if (view === 'requests') {
    loadRechargeRequests();
  }
}

/**
 * Cargar todos los reportes de soporte de resellers
 */
function loadAllReports() {
  const container = getEl('admin-reports-list');
  if (!container) {
    console.warn('Container admin-reports-list not found');
    return;
  }

  container.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="text-muted">Cargando reportes...</p></div>';

  fetchWithCSRF('api.php?action=get_all_reports')
    .then(r => {
      // Verificar si la respuesta es exitosa
      if (!r.ok) {
        throw new Error(`HTTP error! status: ${r.status}`);
      }
      return r.text(); // Primero obtener como texto
    })
    .then(text => {
      console.log('Raw response from get_all_reports:', text); // Debug

      // Intentar parsear el JSON
      if (!text || text.trim() === '') {
        console.warn('Empty response from get_all_reports');
        return { data: [] }; // Retornar estructura vacía
      }

      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('JSON parse error:', e, 'Response:', text);
        throw new Error('Respuesta inválida del servidor');
      }
    })
    .then(d => {
      const items = toArray(d);
      container.innerHTML = '';

      console.log('Reports loaded:', items.length); // Debug

      if (items.length === 0) {
        container.innerHTML = '<div class="text-muted text-center p-4">No hay reportes pendientes</div>';
        return;
      }

      items.forEach(report => {
        const statusColor = report.estado === 'resuelto' ? 'var(--success)' :
          report.estado === 'en_proceso' ? 'var(--warning)' : 'var(--danger)';
        const statusLabel = report.estado === 'resuelto' ? 'Resuelto' :
          report.estado === 'en_proceso' ? 'En Proceso' : 'Pendiente';

        container.innerHTML += `
          <div class="data-card">
            <div class="card-header">
              <div class="card-title"><strong>${escapeHTML(report.asunto || 'Sin asunto')}</strong></div>
              <span class="card-badge" style="background:rgba(${statusColor === 'var(--success)' ? '0,255,136' : statusColor === 'var(--warning)' ? '255,187,0' : '255,0,85'},0.15);color:${statusColor};border:1px solid ${statusColor};">${statusLabel}</span>
            </div>
            <div class="card-body">
              <div class="data-row">
                <span>👤 Reseller:</span>
                <strong>${escapeHTML(report.reseller_nombre || report.nombre_reseller || 'N/A')}</strong>
              </div>
              <div class="data-row">
                <span>📅 Fecha:</span>
                <span>${report.fecha ? (report.fecha.split ? report.fecha.split(' ')[0] : report.fecha) : 'N/A'}</span>
              </div>
              ${report.mensaje ? `
              <div class="data-row" style="flex-direction:column;align-items:flex-start;gap:4px;">
                <span style="color:var(--text-muted);">💬 Mensaje:</span>
                <span style="color:var(--text-main);font-size:0.9rem;line-height:1.5;">${escapeHTML(report.mensaje).substring(0, 150)}${report.mensaje.length > 150 ? '...' : ''}</span>
              </div>` : ''}
            </div>
            ${report.estado !== 'resuelto' ? `
            <div class="card-actions">
              <button class="btn-sm success" onclick="resolveReport(${report.id})">✅ Resolver</button>
              <button class="btn-sm" onclick="viewReportDetails(${report.id})">👁️ Ver Detalles</button>
              ${report.reseller_id ? `<button class="btn-sm" onclick="sendDirectMessageReseller(${report.reseller_id}, '${escapeJS(report.reseller_nombre || '')}')">✉️ Responder</button>` : ''}
            </div>` : ''}
          </div>`;
      });
    })
    .catch(e => {
      console.error('Error loading reports:', e);
      container.innerHTML = `<div class="text-muted text-center p-4">⚠️ Error al cargar reportes: ${e.message}<br><small>Revisa la consola para más detalles</small></div>`;
    });
}

/**
 * Cargar solicitudes de recarga de saldo de resellers
 */
function loadRechargeRequests() {
  const container = getEl('admin-recharge-list');
  if (!container) {
    console.warn('Container admin-recharge-list not found');
    return;
  }

  container.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="text-muted">Cargando solicitudes...</p></div>';

  fetchWithCSRF('api.php?action=get_recharge_requests')
    .then(r => {
      if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
      return r.text();
    })
    .then(text => {
      console.log('Raw response from get_recharge_requests:', text);
      if (!text || text.trim() === '') return { data: [] };
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('JSON parse error:', e, 'Response:', text);
        throw new Error('Respuesta inválida del servidor');
      }
    })
    .then(d => {
      const items = toArray(d);
      container.innerHTML = '';
      console.log('Recharge requests loaded:', items.length);

      if (items.length === 0) {
        container.innerHTML = '<div class="text-muted text-center p-4">No hay solicitudes de recarga pendientes</div>';
        return;
      }

      items.forEach(req => {
        const statusColor = req.estado === 'pendiente' ? 'var(--warning)' :
          req.estado === 'aprobado' ? 'var(--success)' : 'var(--danger)';
        const statusLabel = req.estado === 'pendiente' ? 'Pendiente' :
          req.estado === 'aprobado' ? 'Aprobado' : 'Rechazado';

        container.innerHTML += `
          <div class="data-card">
            <div class="card-header">
              <div class="card-title"><strong>💳 Solicitud #${req.id}</strong></div>
              <span class="card-badge" style="background:rgba(${statusColor === 'var(--warning)' ? '255,187,0' : statusColor === 'var(--success)' ? '0,255,136' : '255,0,85'},0.15);color:${statusColor};border:1px solid ${statusColor};">${statusLabel}</span>
            </div>
            <div class="card-body">
              <div class="data-row">
                <span>👤 Reseller:</span>
                <strong>${escapeHTML(req.reseller_nombre || req.nombre_reseller || 'N/A')}</strong>
              </div>
              <div class="data-row">
                <span>💰 Monto:</span>
                <strong style="color:var(--primary);font-size:1.1rem;">$${parseFloat(req.monto || 0).toFixed(2)}</strong>
              </div>
              <div class="data-row">
                <span>💳 Método:</span>
                <span>${escapeHTML(req.metodo_pago || req.metodo || 'N/A')}</span>
              </div>
              <div class="data-row">
                <span>📅 Fecha:</span>
                <span>${req.fecha ? (req.fecha.split ? req.fecha.split(' ')[0] : req.fecha) : 'N/A'}</span>
              </div>
              ${req.referencia ? `
              <div class="data-row">
                <span>🔖 Referencia:</span>
                <span class="text-mono">${escapeHTML(req.referencia)}</span>
              </div>` : ''}
              ${req.comprobante ? `
              <div class="data-row">
                <span>📎 Comprobante:</span>
                <a href="../${escapeHTML(req.comprobante)}" target="_blank" class="text-primary" style="text-decoration:underline;">Ver imagen</a>
              </div>` : ''}
              ${req.nota ? `
              <div class="data-row" style="flex-direction:column;align-items:flex-start;gap:4px;">
                <span style="color:var(--text-muted);">📝 Nota:</span>
                <span style="color:var(--text-main);font-size:0.85rem;">${escapeHTML(req.nota)}</span>
              </div>` : ''}
            </div>
            ${req.estado === 'pendiente' ? `
            <div class="card-actions">
              <button class="btn-sm success" onclick="approveRecharge(${req.id}, ${req.reseller_id}, ${req.monto}, '${escapeJS(req.reseller_nombre || '')}')">✅ Aprobar</button>
              <button class="btn-sm danger" onclick="rejectRecharge(${req.id})">❌ Rechazar</button>
              ${req.comprobante ? `<button class="btn-sm" onclick="window.open('../${escapeJS(req.comprobante)}', '_blank')">🖼️ Ver Comprobante</button>` : ''}
            </div>` : ''}
          </div>`;
      });
    })
    .catch(e => {
      console.error('Error loading recharge requests:', e);
      container.innerHTML = `<div class="text-muted text-center p-4">⚠️ Error al cargar solicitudes: ${e.message}<br><small>Revisa la consola para más detalles</small></div>`;
    });
}

/**
 * Aprobar una solicitud de recarga
 */
function approveRecharge(requestId, resellerId, monto, resellerName) {
  Swal.fire({
    title: '¿Aprobar recarga?',
    html: `
      <div style="text-align:left;padding:10px;">
        <p><strong>Reseller:</strong> ${escapeHTML(resellerName || 'N/A')}</p>
        <p><strong>Monto:</strong> $${parseFloat(monto).toFixed(2)}</p>
        <p style="margin-top:15px;color:var(--text-muted);font-size:0.9rem;">
          Se acreditará este monto al saldo del reseller.
        </p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: '✅ Sí, aprobar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#00ff88',
    cancelButtonColor: '#ff0055'
  }).then(result => {
    if (result.isConfirmed) {
      fetchWithCSRF('api.php?action=approve_recharge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          request_id: requestId,
          reseller_id: resellerId,
          monto: monto
        })
      })
        .then(r => {
          if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
          return r.text();
        })
        .then(text => {
          if (!text || text.trim() === '') throw new Error('Respuesta vacía del servidor');
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error('JSON parse error in approve_recharge:', e, 'Response:', text);
            throw new Error('Respuesta inválida del servidor');
          }
        })
        .then(res => {
          if (res.success) {
            Swal.fire({
              title: '¡Aprobado!',
              text: 'La recarga ha sido aprobada y el saldo acreditado',
              icon: 'success',
              confirmButtonColor: '#00c6ff'
            });
            loadRechargeRequests();
            // Recargar también la lista de resellers si está visible
            if (typeof loadResellers === 'function') {
              const usersView = getEl('reseller-view-users');
              if (usersView && usersView.style.display !== 'none') {
                loadResellers();
              }
            }
          } else {
            SwalError(res.message || 'Error al aprobar la recarga');
          }
        })
        .catch(err => {
          console.error('Error approving recharge:', err);
          SwalError('Error de conexión al aprobar la recarga');
        });
    }
  });
}

/**
 * Rechazar una solicitud de recarga
 */
function rejectRecharge(requestId) {
  Swal.fire({
    title: '¿Rechazar solicitud?',
    text: 'Esta acción marcará la solicitud como rechazada',
    input: 'textarea',
    inputPlaceholder: 'Motivo del rechazo (opcional)',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: '❌ Sí, rechazar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#ff0055',
    cancelButtonColor: '#888899'
  }).then(result => {
    if (result.isConfirmed) {
      const motivo = result.value || '';
      fetchWithCSRF('api.php?action=reject_recharge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          request_id: requestId,
          motivo: motivo
        })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            Swal.fire({
              title: 'Rechazado',
              text: 'La solicitud ha sido rechazada',
              icon: 'info',
              confirmButtonColor: '#00c6ff'
            });
            loadRechargeRequests();
          } else {
            SwalError(res.message || 'Error al rechazar la solicitud');
          }
        })
        .catch(err => {
          console.error('Error rejecting recharge:', err);
          SwalError('Error de conexión al rechazar la solicitud');
        });
    }
  });
}

/**
 * Resolver un reporte de soporte
 */
function resolveReport(reportId) {
  Swal.fire({
    title: 'Resolver reporte',
    text: '¿Marcar este reporte como resuelto?',
    input: 'textarea',
    inputPlaceholder: 'Nota de resolución (opcional)',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: '✅ Resolver',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#00ff88'
  }).then(result => {
    if (result.isConfirmed) {
      const nota = result.value || '';
      fetchWithCSRF('api.php?action=resolve_report', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          report_id: reportId,
          nota_resolucion: nota
        })
      })
        .then(r => {
          if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
          return r.text();
        })
        .then(text => {
          if (!text || text.trim() === '') throw new Error('Respuesta vacía del servidor');
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error('JSON parse error in resolve_report:', e, 'Response:', text);
            throw new Error('Respuesta inválida del servidor');
          }
        })
        .then(res => {
          if (res.success) {
            SwalSuccess('Reporte marcado como resuelto');
            loadAllReports();
          } else {
            SwalError(res.message || 'Error al resolver el reporte');
          }
        })
        .catch(err => {
          console.error('Error resolving report:', err);
          SwalError('Error de conexión');
        });
    }
  });
}

/**
 * Ver detalles completos de un reporte
 */
function viewReportDetails(reportId) {
  fetchWithCSRF(`api.php?action=get_report_details&id=${reportId}`)
    .then(r => {
      if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
      return r.text();
    })
    .then(text => {
      console.log('Raw response from get_report_details:', text);
      if (!text || text.trim() === '') throw new Error('Respuesta vacía del servidor');
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('JSON parse error in get_report_details:', e, 'Response:', text);
        throw new Error('Respuesta inválida del servidor');
      }
    })
    .then(data => {
      if (!data || !data.id) {
        SwalError('No se encontraron detalles del reporte');
        return;
      }

      Swal.fire({
        title: `Reporte #${data.id}`,
        html: `
          <div style="text-align:left;padding:15px;background:rgba(255,255,255,0.03);border-radius:8px;">
            <div style="margin-bottom:12px;">
              <strong style="color:var(--text-muted);">Asunto:</strong><br>
              <span style="color:var(--text-main);font-size:1.1rem;">${escapeHTML(data.asunto || 'Sin asunto')}</span>
            </div>
            <div style="margin-bottom:12px;">
              <strong style="color:var(--text-muted);">Reseller:</strong><br>
              <span>${escapeHTML(data.reseller_nombre || 'N/A')}</span>
            </div>
            <div style="margin-bottom:12px;">
              <strong style="color:var(--text-muted);">Fecha:</strong><br>
              <span>${data.fecha || 'N/A'}</span>
            </div>
            <div style="margin-bottom:12px;">
              <strong style="color:var(--text-muted);">Estado:</strong><br>
              <span style="color:${data.estado === 'resuelto' ? 'var(--success)' : 'var(--warning)'};">${data.estado || 'Pendiente'}</span>
            </div>
            ${data.mensaje ? `
            <div style="margin-bottom:12px;">
              <strong style="color:var(--text-muted);">Mensaje:</strong><br>
              <div style="background:rgba(0,0,0,0.3);padding:10px;border-radius:6px;margin-top:6px;max-height:200px;overflow-y:auto;">
                <span style="white-space:pre-wrap;line-height:1.6;">${escapeHTML(data.mensaje)}</span>
              </div>
            </div>` : ''}

            ${data.cuenta_afectada ? `
            <div style="margin-top:15px;padding:12px;background:rgba(0,255,136,0.05);border:1px dashed var(--success);border-radius:8px;">
              <strong style="color:var(--success);">Datos de la Cuenta Afectada:</strong>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;font-size:0.9rem;">
                <div><span class="text-muted">Plataforma:</span><br><strong>${escapeHTML(data.cuenta_afectada.plataforma || 'N/A')}</strong></div>
                <div><span class="text-muted">Perfil:</span><br><strong>${escapeHTML(data.cuenta_afectada.perfil || 'N/A')}</strong> ${data.cuenta_afectada.pin ? `(PIN: ${data.cuenta_afectada.pin})` : ''}</div>
                <div style="grid-column:1/-1;"><span class="text-muted">Email:</span><br><code style="user-select:all;color:var(--text-main);">${escapeHTML(data.cuenta_afectada.email || 'N/A')}</code></div>
                <div style="grid-column:1/-1;"><span class="text-muted">Pass:</span><br><code style="user-select:all;color:var(--text-main);">${escapeHTML(data.cuenta_afectada.password || 'N/A')}</code></div>
              </div>
            </div>` : ''}

            ${data.evidencia_img ? `
            <div style="margin-top:15px;">
               <strong style="color:var(--text-muted);">Evidencia:</strong><br>
               <img src="../${data.evidencia_img}" style="max-width:100%;border-radius:6px;margin-top:5px;cursor:pointer;border:1px solid rgba(255,255,255,0.1);" onclick="window.open(this.src, '_blank')">
            </div>` : ''}
            ${data.nota_resolucion ? `
            <div style="margin-top:15px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.1);">
              <strong style="color:var(--success);">✅ Nota de Resolución:</strong><br>
              <span style="color:var(--text-muted);font-size:0.9rem;">${escapeHTML(data.nota_resolucion)}</span>
            </div>` : ''}
          </div>
        `,
        width: '600px',
        confirmButtonText: 'Cerrar',
        confirmButtonColor: '#00c6ff'
      });
    })
    .catch(err => {
      console.error('Error loading report details:', err);
      SwalError('Error al cargar detalles del reporte');
    });
}

// Hacer las funciones globales
window.escapeHTML = escapeHTML;
window.switchResellerView = switchResellerView;
window.loadAllReports = loadAllReports;
window.loadRechargeRequests = loadRechargeRequests;
window.approveRecharge = approveRecharge;
window.rejectRecharge = rejectRecharge;
window.resolveReport = resolveReport;
window.viewReportDetails = viewReportDetails;
