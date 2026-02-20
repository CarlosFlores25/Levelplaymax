<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
$csrf_token = getCSRFToken();
function e($string) { return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin NextGen - D'Level</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../index.css"> <!-- Variables Globales -->
    <link rel="stylesheet" href="admin.css"> <!-- Estilos Específicos -->
    
    <!-- PWA -->
    <link rel="icon" type="image/png" href="../img/logo.png">
    <meta name="theme-color" content="#0a0a12">
    
    <!-- Fonts & Libs -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Iconos Phosphor (Más modernos que SVG inline) -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!--
      Guard defensivo: evita que un WebComponent/Widget externo rompa la página
      al intentar crear Shadow DOM dos veces sobre el mismo host.
      Esto mitiga el error:
      NotSupportedError: Failed to execute 'attachShadow' on 'Element'
    -->
    <script>
      (function () {
        try {
          const original = Element.prototype.attachShadow;
          if (!original || Element.prototype.__dl_shadow_guard__) return;
          Object.defineProperty(Element.prototype, '__dl_shadow_guard__', { value: true });
          Element.prototype.attachShadow = function (init) {
            if (this.shadowRoot) return this.shadowRoot;
            return original.call(this, init);
          };
        } catch (e) {
          // no-op
        }
      })();
    </script>
</head>
<body>
    <input type="hidden" id="csrf_token" value="<?php echo e($csrf_token); ?>">

    <div class="app-layout">
        
        <!-- SIDEBAR -->
        <aside class="sidebar" id="main-sidebar">
            <div class="sidebar-brand">
                <img src="../img/logo.png" alt="Logo" class="brand-logo">
                <div class="brand-text">
                    <span>D'LEVEL</span>
                    <small>ADMIN PANEL</small>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">PRINCIPAL</div>
                <a href="#" class="nav-item active" onclick="showTab('dashboard')">
                    <i class="ph ph-squares-four"></i> <span>Dashboard</span>
                </a>
                <a href="#" class="nav-item" onclick="showTab('asignaciones')">
                    <i class="ph ph-chart-line-up"></i> <span>Ventas Activas</span>
                </a>
                <a href="#" class="nav-item" onclick="showTab('pedidos')">
                    <i class="ph ph-shopping-cart"></i> <span>Pedidos Web</span>
                    <span class="badge pulse">New</span>
                </a>

                <div class="nav-section">GESTIÓN</div>
                <a href="#" class="nav-item" onclick="showTab('stock')">
                    <i class="ph ph-stack"></i> <span>Inventario</span>
                </a>
                <a href="#" class="nav-item" onclick="showTab('cuentas')">
                    <i class="ph ph-key"></i> <span>Cuentas Maestras</span>
                </a>
                <a href="#" class="nav-item" onclick="showTab('catalogo')">
                    <i class="ph ph-tag"></i> <span>Catálogo & Precios</span>
                </a>

                <div class="nav-section">COMUNIDAD</div>
                <a href="#" class="nav-item" onclick="showTab('resellers_admin')">
                    <i class="ph ph-users-three"></i> <span>Partners (Resellers)</span>
                </a>
                <a href="#" class="nav-item" onclick="showTab('clientes')">
                    <i class="ph ph-user"></i> <span>Clientes Finales</span>
                </a>
                <a href="#" class="nav-item" onclick="showTab('inbox')">
                    <i class="ph ph-envelope"></i> <span>Mensajería</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-mini-profile">
                    <div class="avatar">A</div>
                    <div class="info">
                        <span>Admin</span>
                        <small class="status-online">Online</small>
                    </div>
                    <button onclick="logout()" class="btn-logout"><i class="ph ph-sign-out"></i></button>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT WRAPPER -->
        <div class="main-wrapper">
            
            <!-- TOP BAR -->
            <header class="topbar">
                <button class="menu-toggle" onclick="toggleSidebar()"><i class="ph ph-list"></i></button>
                
                <div class="topbar-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" placeholder="Buscar cliente, orden o referencia..." id="global-search">
                </div>

                <div class="topbar-actions">
                    <div class="monitor-rate">
                        <span>TASA:</span>
                        <strong id="live-rate-display" class="text-success">0.00</strong> BS
                        <i class="ph ph-arrows-clockwise" onclick="syncDolar()" style="cursor:pointer"></i>
                    </div>
                    
                    <button class="action-icon" onclick="downloadBackup()" title="Backup DB">
                        <i class="ph ph-database"></i>
                    </button>
                    <button class="action-icon" onclick="downloadPDF()" title="Reporte PDF">
                        <i class="ph ph-file-pdf"></i>
                    </button>
                </div>
            </header>

            <!-- CONTENT AREA -->
            <main class="content-area">
                
                <!-- 1. DASHBOARD -->
                <div id="dashboard" class="view-section active">
                    <div class="section-header">
                        <h1>Visión General</h1>
                        <p class="text-muted">Resumen de actividad del día.</p>
                    </div>

                    <!-- KPI Cards -->
                    <div class="kpi-grid">
                        <div class="kpi-card glass">
                            <div class="icon-box bg-blue"><i class="ph ph-users"></i></div>
                            <div class="kpi-info">
                                <span class="label">Clientes Totales</span>
                                <h2 id="stat-clientes">0</h2>
                            </div>
                        </div>
                        <div class="kpi-card glass">
                            <div class="icon-box bg-green"><i class="ph ph-currency-dollar"></i></div>
                            <div class="kpi-info">
                                <span class="label">Ingresos Mes</span>
                                <h2 id="stat-ingresos">$0.00</h2>
                            </div>
                        </div>
                        <div class="kpi-card glass">
                            <div class="icon-box bg-purple"><i class="ph ph-chart-line-up"></i></div>
                            <div class="kpi-info">
                                <span class="label">Ganancia Neta</span>
                                <h2 id="stat-ganancia">$0.00</h2>
                            </div>
                        </div>
                        <div class="kpi-card glass">
                            <div class="icon-box bg-orange"><i class="ph ph-fire"></i></div>
                            <div class="kpi-info">
                                <span class="label">Ventas Activas</span>
                                <h2 id="stat-ventas">0</h2>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-split">
                        <!-- Main Panel -->
                        <div class="dash-main glass">
                            <div class="card-header-flex">
                                <h3>📊 Rendimiento Financiero</h3>
                                <button onclick="configTelegram()" class="btn-sm btn-ghost">✈️ Configurar Telegram</button>
                            </div>
                            <!-- Aquí iría un gráfico real ChartJS si lo implementamos -->
                            <div class="chart-placeholder">
                                <canvas id="mainChart"></canvas> 
                            </div>
                        </div>

                        <!-- Sidebar Panel (Alertas) -->
                        <div class="dash-side glass">
                            <div class="card-header-flex">
                                <h3>⚠️ Vencimientos</h3>
                                <button onclick="checkExpired()" class="btn-icon"><i class="ph ph-arrows-clockwise"></i></button>
                            </div>
                            <div id="expired-list" class="expiration-list">
                                <p class="text-center text-muted mt-2">Cargando...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. VENTAS (ASIGNACIONES) -->
                <div id="asignaciones" class="view-section">
                    <div class="section-header flex-between">
                        <div>
                            <h1>Ventas Activas</h1>
                            <p class="text-muted">Monitor de cuentas entregadas a clientes y resellers.</p>
                        </div>
                        <button class="btn-primary" onclick="loadAssignments()">🔄 Actualizar</button>
                    </div>

                    <!-- Stats Rápidas -->
                    <div id="sales-stats-grid" class="mini-stats-row"></div>

                    <!-- Buscador y Filtros -->
                    <div class="toolbar glass">
                        <div class="search-box">
                            <i class="ph ph-magnifying-glass"></i>
                            <input type="text" id="search-sales" placeholder="Filtrar por cliente, email o plataforma..." onkeyup="filterSalesCards()">
                        </div>
                    </div>

                    <!-- Grid de Ventas -->
                    <div id="sales-grid" class="cards-grid-modern">
                        <div class="loading-state">
                            <div class="spinner"></div>
                            <p>Cargando datos de ventas...</p>
                        </div>
                    </div>
                </div>

                <!-- 3. STOCK (INVENTARIO) -->
                <div id="stock" class="view-section">
                    <div class="section-header">
                        <h1>Inventario Disponible</h1>
                        <p class="text-muted">Gestión de perfiles y cuentas libres.</p>
                    </div>
                    <div id="stock-grid" class="stock-grid-modern"></div>
                </div>

                <!-- 4. PEDIDOS WEB -->
                <div id="pedidos" class="view-section">
                    <div class="section-header">
                        <h1>Pedidos Web</h1>
                    </div>
                    <div class="tabs-line">
                        <button class="tab-btn active" onclick="loadOrders('pendiente', this)">Pendientes</button>
                        <button class="tab-btn" onclick="loadOrders('aprobado', this)">Historial</button>
                    </div>
                    <div id="orders-grid" class="cards-grid-modern mt-4"></div>
                </div>

                <!-- 5. PARTNERS (RESELLERS) -->
                <div id="resellers_admin" class="view-section">
                    <div class="section-header flex-between">
                        <div>
                            <h1>Partners & Distribuidores</h1>
                            <p class="text-muted">Gestión de revendedores.</p>
                        </div>
                        <div class="action-group">
                            <button class="btn-primary" onclick="openModal('modal-new-reseller')">+ Nuevo Partner</button>
                            <button class="btn-secondary" onclick="generateInviteCode()">🎟️ Invitación</button>
                            <button class="btn-ghost" onclick="openNotifyModal('all', 'Todos')">📢 Notificar Global</button>
                        </div>
                    </div>

                    <div class="tabs-pills mt-4">
                        <button class="pill active" onclick="switchResellerView('users')">👥 Usuarios</button>
                        <button class="pill" onclick="switchResellerView('sales')">📊 Ventas</button>
                        <button class="pill" onclick="switchResellerView('reports')">⚠️ Soporte</button>
                        <button class="pill" onclick="switchResellerView('requests')">💰 Pagos</button>
                    </div>

                    <!-- Vistas Reseller -->
                    <div id="reseller-view-users" class="mt-4" style="display:block;">
                        <div id="resellers-grid" class="stock-grid-modern"></div>
                    </div>
                    
                    <div id="reseller-view-sales" class="mt-4" style="display:none;">
                        <input type="text" id="search-reseller-sales" class="form-control mb-3" placeholder="Filtrar ventas..." onkeyup="filterResellerSales()">
                        <div class="table-responsive glass">
                            <table class="table-modern" id="table-reseller-sales">
                                <thead><tr><th>Distribuidor</th><th>Producto</th><th>Cliente</th><th>Fecha</th></tr></thead>
                                <tbody id="reseller-sales-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="reseller-view-requests" class="mt-4" style="display:none;">
                        <div id="admin-recharge-list" class="cards-grid-modern"></div>
                    </div>
                    
                    <div id="reseller-view-reports" class="mt-4" style="display:none;">
                        <div id="admin-reports-list" class="cards-grid-modern"></div>
                    </div>
                </div>


                <!-- CATALOGO (Sección unificada) -->
                <div id="catalogo" class="view-section">
                    <div class="section-header flex-between">
                        <h1>Catálogo de Productos</h1>
                        <div>
                            <button class="btn-primary" onclick="openProductModal()">+ Producto</button>
                            <button class="btn-ghost" onclick="openCouponModal()">🎟️ Cupones</button>
                        </div>
                    </div>
                    <div id="catalog-grid" class="cards-grid-modern mt-4"></div>
                    
                    <h3 class="mt-5 mb-3">Cupones Activos</h3>
                    <div id="coupon-creator" class="coupon-creator" style="margin:8px 0; padding:8px; border:1px solid var(--glass-border); border-radius:6px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <input id="new-coupon-code" placeholder="Código" class="form-control" style="width:180px;" />
                      <input id="new-coupon-descuento" placeholder="Descuento %" type="number" min="0" max="100" class="form-control" style="width:120px;" />
                      <input id="new-coupon-usos" placeholder="Usos" type="number" min="1" class="form-control" style="width:110px;" />
                      <button class="btn-primary" onclick="saveCoupon()">Crear Cupón</button>
                    </div>
                    <div id="invite-block" class="invite-block" style="margin:12px 0; padding:8px; border:1px solid var(--glass-border); border-radius:6px; display:flex; gap:8px; align-items:center;">
                      <strong>Invitaciones</strong>
                      <button class="btn-primary" onclick="generateInviteCode()">Generar código de invitación</button>
                      <span id="last_invite_code" class="text-muted" style="margin-left:8px;"></span>
                    </div>
                    <div class="table-responsive glass">
                        <table class="table-modern">
                            <thead><tr><th>Código</th><th>Desc %</th><th>Usos</th><th>Estado</th><th>Acción</th></tr></thead>
                            <tbody id="tabla-cupones-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 6. CUENTAS MAESTRAS -->
                <div id="cuentas" class="view-section">
                    <div class="section-header flex-between">
                        <h1>Cuentas Maestras</h1>
                        <div class="action-group">
                            <button class="btn-secondary" onclick="openModal('modal-mass-upload')">📂 Carga Masiva</button>
                            <button class="btn-primary" onclick="openModal('modal-cuenta')">+ Nueva Cuenta</button>
                        </div>
                    </div>
                    <div id="accounts-grid" class="cards-grid-modern mt-4"></div>
                </div>

                <!-- 7. CLIENTES FINALES -->
                <div id="clientes" class="view-section">
                    <div class="section-header flex-between">
                        <h1>Directorio de Clientes</h1>
                        <button class="btn-primary" onclick="openModal('modal-cliente')">+ Nuevo Cliente</button>
                    </div>
                    <div id="clients-grid" class="cards-grid-modern mt-4"></div>
                </div>



                <!-- 9. INBOX -->
                <div id="inbox" class="view-section">
                    <div class="section-header">
                        <h1>Centro de Mensajería</h1>
                    </div>
                    <div class="tabs-line">
                        <button class="tab-btn active" onclick="switchInboxTab('imap')">📧 IMAP</button>
                        <button class="tab-btn" onclick="switchInboxTab('micuenta')">⚡ Extractor</button>
                    </div>

                    <div id="view-imap" class="mt-4 inbox-view">
                        <div class="toolbar glass flex-between">
                            <select id="email-selector" class="form-select" onchange="loadSpecificInbox()">
                                <option value="">-- Cuenta --</option>
                            </select>
                            <div class="action-group">
                                <button class="btn-sm" onclick="loadSpecificInbox()">🔄</button>
                                <button class="btn-sm btn-primary" onclick="openEmailModal()">+ Nueva</button>
                            </div>
                        </div>
                        <div class="table-responsive glass mt-3">
                            <table class="table-modern">
                                <thead><tr><th>Asunto</th><th>De</th><th>Fecha</th><th></th></tr></thead>
                                <tbody id="inbox-list-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="view-micuenta" class="mt-4 inbox-view" style="display:none">
                        <div class="glass p-4">
                            <h3>Extractor de Credenciales</h3>
                            <div class="flex-column gap-3 mt-3">
                                <select id="mc-selector" class="form-control"></select>
                                <button onclick="executeMiCuenta()" class="btn-primary full-width">🔓 Extraer Datos</button>
                                <div id="mc-result-area" class="mt-3 p-3 bg-dark rounded">
                                    <small class="text-muted">Resultado:</small>
                                    <div id="mc-result-text" class="text-mono text-warning">...</div>
                                </div>
                                <div class="flex-between mt-2">
                                    <button class="btn-sm btn-ghost" onclick="openMiCuentaModal()">+ Agregar Token</button>
                                    <button class="btn-sm btn-danger" onclick="deleteMiCuenta()">Borrar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Modales (Mantienen misma estructura interna para compatibilidad JS) -->
    <!-- [Aquí pegaré los modales originales pero con clases CSS limpias] -->
    <!-- Modal Ticket -->
    <div id="modal-ticket" class="modal"><div class="modal-content glass"><h2 class="text-secondary">¡Éxito! 🚀</h2><div id="ticket-content" class="ticket-box"></div><button class="btn-primary full-width" onclick="copyTicket()">Copiar</button><button class="btn-ghost full-width mt-2" onclick="closeModal('modal-ticket')">Cerrar</button></div></div>
    <!-- Modal Cliente -->
    <div id="modal-cliente" class="modal"><div class="modal-content glass"><span class="close-modal" onclick="closeModal('modal-cliente')">&times;</span><h2>Nuevo Cliente</h2><form id="form-cliente"><input type="text" id="cli-nombre" placeholder="Nombre" required class="form-control"><input type="tel" id="cli-telefono" placeholder="Teléfono" required class="form-control"><input type="email" id="cli-email" placeholder="Email" class="form-control"><button type="submit" class="btn-primary full-width mt-3">Guardar</button></form></div></div>
    <!-- (Resto de modales simplificados en estructura, usarán el CSS nuevo) -->
    
    <!-- Incluyo los modales esenciales restantes con estructura standard -->
    <div id="modal-cuenta" class="modal">
        <div class="modal-content glass">
            <span class="close-modal" onclick="closeModal('modal-cuenta')">&times;</span>
            <h2>Agregar Stock (Cuenta Maestra)</h2>
            <form id="form-cuenta">
                <label>Plataforma</label>
                <select name="servicios" id="cta-plataforma" class="form-control mb-2" onchange="calculateAccountSlots()">
                    <option value="">Cargando...</option>
                </select>

                <div class="flex-gap-10 mb-2">
                    <div style="flex:1">
                        <label>Tipo de Venta</label>
                        <select id="cta-tipo" class="form-control" onchange="calculateAccountSlots()">
                            <option value="pantalla">Pantalla (Perfiles)</option>
                            <option value="completa">Cuenta Completa</option>
                        </select>
                    </div>
                    <div style="width:100px">
                        <label>Perfiles</label>
                        <input type="number" id="cta-slots" class="form-control" readonly value="0">
                    </div>
                </div>
                <!-- Feedback visual -->
                <div id="cta-feedback" class="text-sm mb-3" style="min-height:20px; color:var(--warning)"></div>

                <label>Credenciales de Acceso</label>
                <input type="email" id="cta-email" placeholder="Email de la cuenta" class="form-control mb-2" required>
                <div style="position:relative">
                    <input type="text" id="cta-pass" placeholder="Contraseña" class="form-control mb-2" required>
                </div>

                <div class="flex-gap-10">
                    <div style="flex:1">
                        <label>Fecha Pago Prov.</label>
                        <input type="date" id="cta-fecha" class="form-control mb-2" required>
                    </div>
                    <div style="flex:1">
                        <label>Costo ($)</label>
                        <input type="number" id="cta-costo" placeholder="0.00" class="form-control mb-2" step="0.01">
                    </div>
                </div>

                <button type="submit" id="btn-save-account" class="btn-primary full-width mt-2">Guardar Cuenta</button>
            </form>
        </div>
    </div>

    <!-- PRELOADER -->
    <div id="preloader"><div class="spinner"></div></div>
    <div id="toast-container"></div>

    <!-- Scripts -->
    <script>
        // Rellenar select de cuentas (copiado del original para no romper)
        const selectPlat = document.getElementById('cta-plataforma');
        if(selectPlat) {
            const options = `
            <optgroup label="Streaming">
                <option value="Netflix (Pantalla) |5|4">Netflix (Pantalla)</option>
                <option value="Netflix (Cuenta Completa) |5|0">Netflix (Completa)</option>
                <option value="Disney+ Premium (Pantalla) |7|4">Disney+ Premium</option>
                <option value="HBO Max (Pantalla) |5|4">HBO Max</option>
                <option value="Amazon Prime Video (Pantalla)|6|5">Amazon Prime</option>
            </optgroup>
            <optgroup label="Otros"><option value="Spotify (1 Mes)|1|Link">Spotify</option></optgroup>
            `; 
            // Nota: En producción usar el listado completo, aquí resumo por espacio
            selectPlat.innerHTML += options;
        }
    </script>
    <!-- Incluimos modales legacy solo si existen (evita warnings/white-screen en producción) -->
    <?php
      $legacyModals = __DIR__ . '/modals_legacy_include.php';
      if (file_exists($legacyModals)) {
          include $legacyModals;
      }
    ?>
    
    <!-- MODALES RESTANTES (Pegados para funcionalidad) -->
    <div id="modal-producto" class="modal"><div class="modal-content glass"><span class="close-modal" onclick="closeModal('modal-producto')">&times;</span><h2>Producto</h2><form id="form-producto"><input type="hidden" id="prod-id"><input type="text" id="prod-nombre" placeholder="Nombre" class="form-control mb-2" required><input type="number" id="prod-precio" placeholder="Precio" class="form-control mb-2" step="0.01" required><select id="prod-cat" class="form-control mb-2"><option value="Streaming">Streaming</option><option value="Combo">Combo</option></select><select id="prod-tipo" class="form-control mb-2"><option value="credenciales">Automática</option><option value="input_correo">Pedir Correo</option></select><button type="submit" class="btn-primary full-width">Guardar</button></form></div></div>
    
    <div id="modal-new-reseller" class="modal"><div class="modal-content glass"><span class="close-modal" onclick="closeModal('modal-new-reseller')">&times;</span><h2>Nuevo Partner</h2><form id="form-new-reseller"><input type="text" id="res-name" placeholder="Nombre" class="form-control mb-2"><input type="email" id="res-email" placeholder="Email" class="form-control mb-2"><input type="text" id="res-pass" placeholder="Clave" class="form-control mb-2"><button type="submit" class="btn-primary full-width">Crear</button></form></div></div>

    <!-- Modal Detalles de Stock -->
    <div id="modal-stock-details" class="modal">
        <div class="modal-content glass">
            <span class="close-modal" onclick="closeModal('modal-stock-details')">&times;</span>
            <h2>Detalles de Stock</h2>
            <div id="stock-detail-container">
                <p>Cargando detalles...</p>
            </div>
            <button class="btn-ghost full-width mt-3" onclick="closeModal('modal-stock-details')">Cerrar</button>
        </div>
    </div>

    <!-- Modal Movimientos Reseller -->
    <div id="modal-reseller-movements" class="modal">
        <div class="modal-content glass wide">
            <span class="close-modal" onclick="closeModal('modal-reseller-movements')">&times;</span>
            <h2 id="reseller-movements-title">Movimientos</h2>
            <div class="table-responsive mt-3" style="max-height:60vh; overflow:auto;">
                <table class="table-modern" style="width:100%;">
                    <thead>
                        <tr><th>Fecha</th><th>Tipo</th><th style="text-align:right;">Monto</th><th>Descripción</th></tr>
                    </thead>
                    <tbody id="reseller-movements-body">
                        <tr><td colspan="4" class="text-muted">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button class="btn-ghost" onclick="closeModal('modal-reseller-movements')">Cerrar</button>
                <button class="btn-primary" onclick="downloadResellerMovementsCSV()">📥 Exportar CSV</button>
            </div>
        </div>
    </div>

    <!-- Modal Ver Cuenta Maestra -->
    <div id="modal-account-view" class="modal">
        <div class="modal-content glass">
            <span class="close-modal" onclick="closeModal('modal-account-view')">&times;</span>
            <div class="flex-between align-center">
                <h2>Cuenta Maestra</h2>
                <button id="btn-edit-master-acc" class="btn-sm btn-secondary" onclick="">✎ Editar Datos</button>
            </div>
            <h3 id="acc-detail-email" class="text-secondary mt-2 mb-3" style="word-break:break-all;"></h3>
            
            <div id="acc-profiles-list" class="mt-3">
                <p>Cargando perfiles...</p>
            </div>
            <button class="btn-ghost full-width mt-3" onclick="closeModal('modal-account-view')">Cerrar</button>
        </div>
    </div>

    <!-- Modal Editar Cuenta Maestra -->
    <div id="modal-edit-master-account" class="modal">
        <div class="modal-content glass">
            <span class="close-modal" onclick="closeModal('modal-edit-master-account')">&times;</span>
            <h2>Editar Cuenta Maestra</h2>
            <form id="form-edit-master-account">
                <input type="hidden" id="edit-master-id">
                <label>Email de la Cuenta</label>
                <input type="email" id="edit-master-email" class="form-control mb-2" required>
                
                <label>Contraseña</label>
                <div style="position:relative">
                    <input type="text" id="edit-master-pass" class="form-control mb-2" required>
                </div>

                <label>Fecha Pago a Proveedor</label>
                <input type="date" id="edit-master-date" class="form-control mb-2">

                <button type="submit" class="btn-primary full-width mt-3">Guardar Cambios</button>
                
                <h3 class="mt-4 mb-2">Asociados</h3>
                <div id="assoc-list" class="table-responsive" style="max-height: 150px; overflow-y: auto; border: 1px solid var(--glass-border); padding: 5px;">
                    <!-- JS population -->
                    <p class="text-muted text-sm">Cargando...</p>
                </div>

                <hr class="my-3" style="border-top:1px solid rgba(255,255,255,0.1)">
                
                <button type="button" class="btn-danger full-width" onclick="deleteAccount(document.getElementById('edit-master-id').value)">🗑️ Eliminar Cuenta Maestra</button>
            </form>
        </div>
    </div>

    <!-- Modal Producto (Nuevo/Editar) -->
    <div id="modal-product" class="modal">
        <div class="modal-content glass">
            <span class="close-modal" onclick="closeModal('modal-product')">&times;</span>
            <h2 id="modal-product-title">Nuevo Producto</h2>
            <form id="form-product">
                <input type="hidden" id="prod-id">
                
                <label>Nombre del Producto</label>
                <input type="text" id="prod-name" class="form-control mb-2" required>
                
                <label>Categoría</label>
                <select id="prod-cat" class="form-control mb-2">
                    <option value="streaming">Streaming</option>
                    <option value="vpn">VPN</option>
                    <option value="music">Música</option>
                    <option value="other">Otro</option>
                </select>

                <div class="flex-gap-10">
                    <div style="flex:1">
                        <label>Precio Público ($)</label>
                        <input type="number" step="0.01" id="prod-price" class="form-control mb-2" required>
                    </div>
                    <div style="flex:1">
                        <label>Precio Reseller ($)</label>
                        <input type="number" step="0.01" id="prod-price-reseller" class="form-control mb-2" required>
                    </div>
                </div>

                <label>Descripción</label>
                <textarea id="prod-desc" class="form-control mb-2" rows="3"></textarea>

                <label>Tipo de Entrega</label>
                <select id="prod-type" class="form-control mb-2">
                    <option value="credenciales">Credenciales (Email/Pass)</option>
                    <option value="link">Link de Activación</option>
                    <option value="manual">Manual / Ilimitado (Sin Stock)</option>
                </select>

                <button type="submit" class="btn-primary full-width mt-3">Guardar Producto</button>
            </form>
        </div>
    </div>

    <!-- Modal Migrar Perfil -->
    <div id="modal-migrate" class="modal">
        <div class="modal-content glass">
            <span class="close-modal" onclick="closeModal('modal-migrate')">&times;</span>
            <h2>Migrar Perfil</h2>
            <form id="form-migrate">
                <p>Origen: <strong id="mig-origen-txt"></strong></p>
                <input type="hidden" id="mig-origen-id">
                <label for="mig-destino-select">Destino (Cuenta Maestra compatible):</label>
                <select id="mig-destino-select" class="form-control mb-2" required>
                    <option value="">Cargando...</option>
                </select>
                <button type="submit" class="btn-primary full-width mt-3">Migrar</button>
            </form>
            <button class="btn-ghost full-width mt-3" onclick="closeModal('modal-migrate')">Cerrar</button>
        </div>
    </div>

    <!-- Modal Renovar Cuenta Maestra -->
    <div id="modal-renew-master" class="modal">
        <div class="modal-content glass">
            <span class="close-modal" onclick="closeModal('modal-renew-master')">&times;</span>
            <h2>Renovar Cuenta Maestra</h2>
            <form id="form-renew-master">
                <input type="hidden" id="renew-master-id">
                <p>Cuenta: <strong id="renew-master-name"></strong></p>
                <label for="renew-master-date">Nueva Fecha de Pago a Proveedor:</label>
                <input type="date" id="renew-master-date" class="form-control mb-2" required>
                <button type="submit" class="btn-primary full-width mt-3">Renovar</button>
            </form>
            <button class="btn-ghost full-width mt-3" onclick="closeModal('modal-renew-master')">Cerrar</button>
        </div>
    </div>

    <!-- Scripts JS -->
    <script src="admin.js"></script>
    <!-- Modal Editar Venta (Admin) -->
    <div id="modal-edit-sale" class="modal">
      <div class="modal-content glass" style="max-width:600px;">
        <span class="close-modal" onclick="closeModal('modal-edit-sale')">&times;</span>
        <h2>Editar Venta</h2>
        <form id="form-edit-sale">
          <input type="hidden" id="edit-sale-id">
          <!-- Hidden field to track if it's a reseller sale or direct client -->
          <input type="hidden" id="edit-sale-type"> 
          
          <div class="form-group">
            <label>Nombre Cliente / Referencia</label>
            <input type="text" id="edit-sale-nombre" class="form-control">
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="text" id="edit-sale-telefono" class="form-control">
          </div>
          <div class="form-group">
            <label>Email Cliente</label>
            <input type="email" id="edit-sale-email" class="form-control">
          </div>
          <div class="form-group">
             <label for="edit-sale-fecha">Fecha de Vencimiento</label>
             <input type="date" id="edit-sale-fecha" class="form-control" required>
          </div>
          
          <button type="submit" class="btn-primary full-width mt-3">Guardar Cambios</button>
        </form>
        <button class="btn-ghost full-width mt-2" onclick="closeModal('modal-edit-sale')">Cerrar</button>
      </div>
    </div>

    <script>
        // Toggle Sidebar en móvil
        function toggleSidebar() {
            document.querySelector('.app-layout').classList.toggle('sidebar-collapsed');
        }
        // Form submit handler for edit sale (assignments)
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('form-edit-sale');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // Get values
                    const id = document.getElementById('edit-sale-id').value;
                    const nombre = document.getElementById('edit-sale-nombre').value;
                    const telefono = document.getElementById('edit-sale-telefono').value;
                    const email = document.getElementById('edit-sale-email').value;
                    const fecha = document.getElementById('edit-sale-fecha').value;
                    const tipo = document.getElementById('edit-sale-type').value; // 'cliente' or 'reseller' implied/passed

                    fetch('api.php?action=update_assignment', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id, nombre, telefono, email, fecha, tipo })
                    }).then(r => r.json()).then(res => {
                        if (res.success) {
                            SwalSuccess('Venta actualizada');
                            closeModal('modal-edit-sale');
                            loadAssignments(); // Reload the list
                        } else {
                            SwalError(res.message || 'Error al actualizar');
                        }
                    }).catch((e) => {
                        console.error(e);
                        SwalError('Error de conexión');
                    });
                });
            }
        });
    </script>
    <!-- Modal Aprobar Pedido Reseller -->
    <div id="modal-approve-reseller-order" class="modal">
        <div class="modal-content glass">
            <span class="close-modal" onclick="closeModal('modal-approve-reseller-order')">&times;</span>
            <h2>Gestionar Pedido Reseller</h2>
            <p class="text-muted mb-3">Ingresa el correo y la credencial para el pedido seleccionado. Puedes aprobar y entregar credenciales o rechazar el pedido (el saldo será devuelto al reseller).</p>
            <form id="form-approve-reseller-order">
                <input type="hidden" id="approve-pedido-id">
                <div class="form-group">
                    <label>Correo (Email)</label>
                    <input type="email" id="approve-email" class="form-control" placeholder="Ingresa el correo del pedido" required>
                </div>
                <div class="form-group">
                    <label>Credencial (Contraseña)</label>
                    <input type="text" id="approve-password" class="form-control" placeholder="Ingresa la credencial a entregar" required>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn-primary half-width" onclick="submitApproveResellerOrder()">Aprobar y Entregar</button>
                    <button type="button" class="btn-danger half-width" onclick="submitRejectResellerOrder()">Rechazar Pedido</button>
                </div>
            </form>
            <button class="btn-ghost full-width mt-2" onclick="closeModal('modal-approve-reseller-order')">Cancelar</button>
        </div>
    </div>

    <script>
        function submitApproveResellerOrder() {
            const pedidoId = document.getElementById('approve-pedido-id').value;
            const email = document.getElementById('approve-email').value;
            const password = document.getElementById('approve-password').value;
            
            if (!pedidoId || !email || !password) {
                SwalError('Debes completar correo y credencial');
                return;
            }
            
            fetch('api.php?action=approve_reseller_order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pedido_id: pedidoId, email: email, password: password })
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    SwalSuccess('Pedido aprobado y credenciales entregadas');
                    closeModal('modal-approve-reseller-order');
                    loadOrders('pendiente');
                } else {
                    SwalError(res.message || 'Error al aprobar');
                }
            }).catch((e) => {
                console.error(e);
                SwalError('Error de conexión');
            });
        }
        
        function submitRejectResellerOrder() {
            const pedidoId = document.getElementById('approve-pedido-id').value;
            
            if (!pedidoId) {
                SwalError('Pedido no encontrado');
                return;
            }
            
            Swal.fire({
                title: 'Rechazar Pedido',
                text: 'Por favor, indica el motivo del rechazo',
                input: 'textarea',
                inputPlaceholder: 'Motivo del rechazo...',
                showCancelButton: true,
                confirmButtonText: 'Rechazar y Devolver Saldo',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    const motivo = result.value || 'Sin motivo especificado';
                    
                    fetch('api.php?action=reject_reseller_order', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ pedido_id: pedidoId, motivo: motivo })
                    }).then(r => r.json()).then(res => {
                        if (res.success) {
                            SwalSuccess('Pedido rechazado y saldo devuelto');
                            closeModal('modal-approve-reseller-order');
                            loadOrders('pendiente');
                        } else {
                            SwalError(res.message || 'Error al rechazar');
                        }
                    }).catch((e) => {
                        console.error(e);
                        SwalError('Error de conexión');
                    });
                }
            });
        }
        
        // Form submit handler (no se usa, los botones llaman directamente a las funciones)
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('form-approve-reseller-order');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                });
            }
        });
    </script>
</body>
</html>
