<?php session_start();
if (!isset($_SESSION['reseller_id'])) {
    header("Location: index.php");
    exit;
} ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel Distribuidor</title>
    <!-- SweetAlert2 (Alertas Bonitas) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="../admin/admin.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="../img/logo.png" rel="stylesheet">
    <!-- Configuración APP Android -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#00c6ff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/png" href="../img/logo.png">
</head>

<body>


    <div class="admin-container">
        <!-- SIDEBAR AZUL -->
        <nav class="sidebar">

            <!-- GRUPO 1: VISIBLES SIEMPRE (BARRA INFERIOR) -->
            <div class="nav-group nav-main">
                <button class="nav-btn active" onclick="showTab('dashboard')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Inicio</span>
                </button>

                <button class="nav-btn" onclick="showTab('stock')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span>Tienda</span>
                </button>

                <button class="nav-btn" onclick="showTab('inventory')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                        </path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                    <span>Cuentas</span>
                </button>

                <button class="nav-btn" onclick="showTab('finanzas')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2v20M2 12h20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                    <span>Finanzas</span>
                </button>



                <!-- BOTÓN MENÚ MÓVIL -->
                <button class="nav-btn mobile-more-btn" onclick="toggleMobileMenu()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                    <span>Menú</span>
                </button>
            </div>

            <!-- GRUPO 2: DESPLEGABLE (SECUNDARIOS) -->
            <div class="nav-group nav-extra" id="mobile-menu-overlay">
                <div class="mobile-menu-header">Más Opciones</div>

                <button class="nav-btn" onclick="showTab('clients')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Mis Clientes</span>
                </button>

                <button class="nav-btn" onclick="showTab('monitor')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span>Monitor/Ventas</span>
                </button>

                <button class="nav-btn" onclick="showTab('pagos')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    <span>Recargar</span>
                </button>

                <button class="nav-btn" onclick="showTab('marketing')" style="color:#0088cc;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 2L11 13"></path>
                        <path d="M22 2l-7 20-4-9-9-4 20-7z"></path>
                    </svg>
                    <span>Canal Telegram</span>
                </button>

                <!-- En el Sidebar -->
                <button class="nav-btn" onclick="openNotifications()" style="position: relative;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span>Notificaciones</span>

                    <!-- NUEVO: El punto rojo (oculto por defecto) -->
                    <span id="notif-badge" class="pulse-badge" style="display: none;"></span>
                </button>


                <div class="divider" style="width:100%; height:1px; background:rgba(255,255,255,0.1); margin:10px 0;">
                </div>

                <!-- En el <nav class="sidebar"> ... -->

                <!-- En el sidebar -->
                <button class="nav-btn" onclick="openModal('modal-policies')"
                    style="color:#ffbb00; border:1px solid rgba(255, 187, 0, 0.2);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span>Normas / Reglas</span>
                </button>

                <button class="nav-btn" onclick="openModal('modal-tutorial')"
                    style="color:#00c6ff; border:1px solid rgba(0, 198, 255, 0.3);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span>Tutorial / Ayuda</span>
                </button>


                <button class="nav-btn" onclick="showTab('reportes')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                            d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z">
                        </path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span>Reportar Fallos</span>
                </button>

                <button class="nav-btn" onclick="showTab('feedback')">💡 Mejoras / Bugs</button>

                <button class="nav-btn" onclick="showTab('perfil')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Mi Perfil</span>
                </button>

                <button class="nav-btn" onclick="logout()" style="color:#ff0055;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Cerrar Sesión</span>
                </button>

                <button class="btn-close-menu" onclick="toggleMobileMenu()">▼ Cerrar Menú</button>
            </div>
        </nav>

        <main class="main-content">

            <!-- DASHBOARD -->
            <section id="dashboard" class="tab-content active">
                <!-- BANNER DE BIENVENIDA -->
                <div class="welcome-banner">
                    <div>
                        <div style="font-size:0.9rem; opacity:0.9;">Bienvenido de nuevo,</div>
                        <h1 id="dash-user-name" style="margin:0; font-size:1.8rem;">Distribuidor</h1>
                    </div>
                    <div class="tasa-badge">
                        <span>🇻🇪 Tasa:</span>
                        <strong id="dash-tasa-display">0.00 Bs</strong>
                    </div>
                </div>

                <!-- GRILLA DE ESTADÍSTICAS -->
                <div class="dashboard-grid">
                    <!-- 1. SALDO -->
                    <div class="dash-card card-money" onclick="showTab('pagos')" style="cursor:pointer;">
                        <h3>Saldo Disponible</h3>
                        <div class="value" id="dash-balance">$0.00</div>
                        <small style="color:var(--secondary);">+ Recargar</small>
                    </div>

                    <!-- 2. URGENCIA (POR VENCER) -->
                    <div class="dash-card card-urgent" onclick="showTab('monitor')" style="cursor:pointer;">
                        <h3>Por Vencer (3 días)</h3>
                        <div class="value" id="dash-por-vencer" style="color:var(--warning);">0</div>
                        <small style="color:#aaa;">Ir a cobrar ➔</small>
                    </div>

                    <!-- 3. VENTAS HOY -->
                    <div class="dash-card card-sales">
                        <h3>Ventas Hoy</h3>
                        <div class="value" id="dash-ventas-hoy">0</div>
                        <small style="color:#aaa;">Cuentas nuevas</small>
                    </div>

                    <!-- 4. TOTAL ACTIVAS -->
                    <div class="dash-card">
                        <h3>Total Activas</h3>
                        <div class="value" id="dash-active">0</div>
                        <small style="color:#aaa;">En cartera</small>
                    </div>
                </div>

                <!-- ACCESOS RÁPIDOS -->
                <h3
                    style="margin-bottom:15px; margin-top:30px; border-left:4px solid var(--primary); padding-left:10px;">
                    Accesos Rápidos</h3>
                <div class="quick-actions">
                    <button class="action-btn" onclick="showTab('stock')">
                        <span style="font-size:1.5rem;">🛍️</span> Comprar Cuenta
                    </button>
                    <button class="action-btn" onclick="openPaymentModal()">
                        <span style="font-size:1.5rem;">💳</span> Reportar Pago
                    </button>
                    <button class="action-btn" onclick="showTab('inventory')">
                        <span style="font-size:1.5rem;">📦</span> Mi Inventario
                    </button>
                    <button class="action-btn" onclick="openTelegramChannel()">
                        <span style="font-size:1.5rem;">📢</span> Canal Telegram
                    </button>
                </div>
            </section>

            <!-- TIENDA -->
            <section id="stock" class="tab-content">
                <h1 class="page-title">Comprar Stock</h1>
                <p class="text-muted">Saldo actual: <span id="stock-balance"
                        style="color:#00c6ff; font-weight:bold">...</span></p>
                <div id="stock-grid" class="stock-grid"></div>
            </section>

            <!-- 2. INVENTARIO (VISTA TARJETAS) -->
            <section id="inventory" class="tab-content">
                <div class="header-flex">
                    <h1 class="page-title">Mis Cuentas</h1>
                    <button class="btn-text" onclick="loadInventory()">🔄 Actualizar</button>
                </div>

                <!-- BUSCADOR INVENTARIO -->
                <input type="text" id="search-inventory" placeholder="🔍 Buscar por cliente, plataforma o email..."
                    onkeyup="filterInventory()" style="margin-bottom: 1rem; border-color: var(--secondary);">

                <!-- Aquí se inyectarán las tarjetas -->
                <div id="inventory-grid" class="cards-grid">
                    <p class="text-muted">Cargando...</p>
                </div>
            </section>

            <!-- 3. CLIENTES (VISTA TARJETAS) -->
            <section id="clients" class="tab-content">
                <div class="header-flex">
                    <h1 class="page-title">Mis Clientes</h1>
                    <button class="cta-button-main sm"
                        onclick="document.getElementById('modal-client').style.display='flex'">+ Nuevo</button>
                </div>

                <div id="clients-grid" class="cards-grid">
                    <p class="text-muted">Cargando...</p>
                </div>
            </section>

            <section id="marketing" class="tab-content">
                <div class="header-flex">
                    <h2 class="page-title" style="color:#0088cc;">Comunidad & Material</h2>
                </div>

                <div class="glass-card"
                    style="text-align:center; padding:3rem 1rem; margin-top:2rem; border-top:4px solid #0088cc; max-width:600px; margin-left:auto; margin-right:auto;">

                    <!-- Icono Animado Telegram -->
                    <div style="font-size:5rem; margin-bottom:1rem; text-shadow: 0 0 30px rgba(0, 136, 204, 0.6);">
                        ✈️
                    </div>

                    <h3 style="color:white; font-size:1.5rem; margin-bottom:10px;">Únete a nuestro Canal Oficial</h3>

                    <p style="color:#aaa; font-size:1rem; line-height:1.6; margin-bottom:2rem;">
                        Obtén acceso exclusivo a:<br>
                        📸 Imágenes promocionales sin marca de agua.<br>
                        📹 Videos de marketing para tus estados.<br>
                        📢 Noticias y actualizaciones del servicio.
                    </p>

                    <button onclick="openTelegramChannel()" class="cta-button-main"
                        style="background:#0088cc; color:white; font-size:1.1rem; padding:15px 30px; border-radius:50px; box-shadow: 0 10px 30px rgba(0, 136, 204, 0.3);">
                        SOLICITAR ENLACE DE ACCESO
                    </button>

                    <p style="margin-top:20px; font-size:0.8rem; color:#555;">
                        *Al hacer clic serás redirigido a Telegram.
                    </p>
                </div>
            </section>

            <section id="reportes" class="tab-content">
                <h2 class="page-title">Historial de Fallos</h2>
                <p class="text-muted">Estado de tus reportes enviados a soporte.</p>

                <!-- USAMOS UN DIV GRID, NO UNA TABLA -->
                <div id="reports-grid" class="cards-grid" style="grid-template-columns: 1fr; margin-top:1rem;">
                    <p class="text-muted">Cargando reportes...</p>
                </div>
            </section>

            <!-- 4. SECCIÓN PAGOS Y RECARGAS -->
            <section id="pagos" class="tab-content">
                <div class="header-flex">
                    <h2 class="page-title">Recargar Saldo</h2>
                </div>
                <p class="text-muted" style="margin-bottom: 2rem;">Datos para realizar transferencias.</p>

                <div class="payments-grid">

                    <!-- BINANCE -->
                    <div class="pay-card binance">
                        <div class="pay-header">
                            <img src="../img/binance.png" alt="Binance">
                            <h3>Binance Pay</h3>
                        </div>
                        <div class="pay-body">
                            <p>Binance ID:</p>
                            <div class="copy-row">
                                <span id="binance-data2">carloscruch@gmail.com</span>
                                <span id="binance-data">346-766-184</span>
                                <button class="copy-btn-icon" onclick="copyText('binance-data')"
                                    title="Copiar">📋</button>
                            </div>
                            <small>Recarga mínima: $10.00</small>
                        </div>
                    </div>

                    <!-- ZINLI -->
                    <div class="pay-card zinli">
                        <div class="pay-header">
                            <img src="../img/zinli.png" alt="Zinli">
                            <h3>Zinli</h3>
                        </div>
                        <div class="pay-body">
                            <p>Correo:</p>
                            <div class="copy-row">
                                <span id="zinli-data">carloscruch@gmail.com</span>
                                <button class="copy-btn-icon" onclick="copyText('zinli-data')"
                                    title="Copiar">📋</button>
                            </div>
                            <small>Recarga mínima: $10.00</small>
                        </div>
                    </div>

                    <!-- PAGO MÓVIL -->
                    <div class="pay-card pm">
                        <div class="pay-header">
                            <img src="https://img.icons8.com/fluency/48/iphone-x.png" alt="Pago Móvil">
                            <h3>Pago Móvil</h3>
                        </div>
                        <div class="pay-body">
                            <p>Banco Venezuela (0102)</p>
                            <div class="copy-row" style="margin-bottom:5px">
                                <span id="pm-cedula">V-29911214</span>
                                <button class="copy-btn-icon" onclick="copyText('pm-cedula')">📋</button>
                            </div>
                            <div class="copy-row">
                                <span id="pm-phone">0412-3368325</span>
                                <button class="copy-btn-icon" onclick="copyText('pm-phone')">📋</button>
                            </div>
                            <!-- AQUÍ ESTÁ EL CAMBIO: -->
                            <small style="display: block; margin-top: 15px; font-size: 0.9rem; color:white;">
                                Tasa del día (USDT): <span id="live-tasa-bs"
                                    style="color:#00ff88; font-weight:bold; font-size:1.1rem">Cargando...</span> Bs
                            </small>
                            <small>Recarga mínima: $5.00</small>
                        </div>
                    </div>

                </div>

                <div class="glass-card" style="margin-top: 2rem; text-align: center; padding: 2rem;">
                    <h3>¿Ya realizaste el pago?</h3>
                    <button class="cta-button-main" onclick="openPaymentModal()"
                        style="background: #00ff88; color: black; margin-top: 1rem;">
                        📝 Llenar Formulario de Pago
                    </button>
                </div>

                <!-- HISTORIAL FINANCIERO AVANZADO -->
                <div style="margin-top:3rem; border-top:1px solid rgba(255,255,255,0.1); padding-top:1rem;">
                    <div class="header-flex" style="margin-bottom:15px;">
                        <h3 class="section-subtitle" style="margin:0;">Movimientos de Caja</h3>
                        <!-- Selector de Filtro -->
                        <select id="wallet-filter" onchange="filterWallet()"
                            style="background:#111; color:white; border:1px solid #444; padding:5px; border-radius:5px;">
                            <option value="all">Todo</option>
                            <option value="deposito">💰 Ingresos</option>
                            <option value="compra">🛒 Compras</option>
                            <option value="renovacion">↻ Renovaciones</option>
                        </select>
                    </div>

                    <!-- Tabla Mejorada -->
                    <div class="table-container glass-card" style="max-height: 500px; overflow-y: auto;">
                        <table class="responsive-table" style="width:100%;">
                            <thead>
                                <tr>
                                    <th style="width:120px;">Fecha</th>
                                    <th>Detalle de la Operación</th>
                                    <th style="text-align:right;">Monto</th>
                                </tr>
                            </thead>
                            <tbody id="wallet-history-body">
                                <!-- Se llena con JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>



            <!-- SECCIÓN FEEDBACK -->
            <section id="feedback" class="tab-content">
                <h2 class="page-title">Tu Opinión Importa</h2>
                <p class="text-muted">Ayúdanos a mejorar la plataforma reportando errores o sugiriendo funciones.</p>

                <div class="glass-card" style="padding: 2rem; margin-top: 1rem; border-top: 4px solid #00c6ff;">
                    <form id="form-feedback">
                        <label style="color:white; display:block; margin-bottom:5px;">¿Qué deseas enviar?</label>
                        <select id="feed-type" style="margin-bottom:15px;">
                            <option value="mejora">✨ Sugerencia / Idea</option>
                            <option value="bug">🐛 Reportar un Error (Bug)</option>
                        </select>

                        <label style="color:white; display:block; margin-bottom:5px;">Detalle:</label>
                        <textarea id="feed-msg" rows="4" placeholder="Ej: Sería genial tener un modo oscuro..."
                            style="width:100%; background:rgba(0,0,0,0.3); color:white; border:1px solid #444; padding:15px; border-radius:10px; font-family:inherit;"
                            required></textarea>

                        <button type="submit" class="cta-button-main full-width" style="margin-top:15px;">Enviar
                            Comentario</button>
                    </form>
                </div>

                <h3 class="section-subtitle" style="margin-top:2rem">Tus Aportes Enviados</h3>
                <div id="feedback-list" class="stock-grid" style="grid-template-columns: 1fr;">
                    <!-- Aquí carga el historial -->
                </div>
            </section>

            <!-- 5. MONITOR DE VENTAS -->
            <section id="monitor" class="tab-content">
                <div class="header-flex">
                    <h2 class="page-title">Monitor de Clientes</h2>
                </div>

                <!-- Buscador Grande -->
                <div class="glass-card"
                    style="padding:1rem; margin-bottom:1.5rem; border-left:4px solid var(--secondary)">
                    <input type="text" id="monitor-search" placeholder="🔎 Buscar por nombre, teléfono o plataforma..."
                        onkeyup="loadMonitor()"
                        style="margin:0; width:100%; font-size:1.1rem; border-radius:10px; border:1px solid #555;">
                </div>

                <div id="monitor-grid" class="cards-grid">
                    <p class="text-muted">Cargando...</p>
                </div>
            </section>

            <!-- SECCIÓN PERFIL -->
            <section id="perfil" class="tab-content">
                <div class="header-flex">
                    <h1 class="page-title">Configuración de Cuenta</h1>
                </div>

                <div class="glass-card" style="padding: 2rem; max-width: 600px; margin: 0 auto;">
                    <div style="text-align:center; margin-bottom:2rem">
                        <div style="width:80px; height:80px; background:#00c6ff; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:2rem; font-weight:bold; color:black; box-shadow:0 0 20px rgba(0,198,255,0.4);"
                            id="profile-avatar">
                            U
                        </div>
                        <p style="margin-top:10px; color:white; font-size:1.2rem;" id="profile-header-name">Usuario</p>
                    </div>

                    <form id="form-profile">

                        <label style="color:#aaa; font-size:0.9rem">Correo Electrónico (No modificable)</label>
                        <input type="text" id="prof-email" readonly
                            style="background:rgba(255,255,255,0.05); color:#777; cursor:not-allowed;">

                        <label style="color:#white; font-size:0.9rem">Nombre y Apellido</label>
                        <input type="text" id="prof-name" required>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div>
                                <label style="color:#white; font-size:0.9rem">Teléfono</label>
                                <input type="text" id="prof-phone">
                            </div>
                            <div>
                                <label style="color:#white; font-size:0.9rem">Cédula o Pasaporte</label>
                                <input type="text" id="prof-id">
                            </div>
                        </div>

                        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin:20px 0;">

                        <label style="color:#00ff88; font-size:0.9rem">Nueva Contraseña</label>
                        <input type="password" id="prof-pass" placeholder="Dejar vacío si no se desea cambiar">
                        <small style="color:#aaa; display:block; margin-bottom:15px;">Mínimo 6 caracteres.</small>

                        <button type="submit" class="cta-button-main full-width"
                            style="background:linear-gradient(90deg, #00c6ff, #0072ff);">Guardar Cambios</button>
                    </form>
                </div>
            </section>

            <!-- SECCIÓN FINANZAS (LIMPIA Y CORREGIDA) -->
            <section id="finanzas" class="tab-content">
                <div class="header-flex">
                    <h1 class="page-title">Tus Finanzas</h1>
                    <!-- Botón para actualizar manualmente si se queda pegado -->
                    <button class="btn-text" onclick="loadFinance()">🔄 Recargar Datos</button>
                </div>

                <!-- BARRA DE META -->
                <div class="glass-card"
                    style="margin-bottom:2rem; background:linear-gradient(135deg, rgba(0,255,136,0.05), rgba(0,198,255,0.05)); border:1px solid #00c6ff;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:10px;">
                        <div>
                            <small style="color:#aaa; text-transform:uppercase; letter-spacing:1px;">Meta
                                Mensual</small>
                            <div style="font-size:2rem; font-weight:800; color:white;" id="goal-display">$0.00</div>
                        </div>
                        <button onclick="editGoal()"
                            style="background:none; border:1px solid #aaa; color:#aaa; border-radius:50px; padding:5px 15px; font-size:0.8rem; cursor:pointer;">🎯
                            Editar Meta</button>
                    </div>

                    <!-- Barra -->
                    <div style="background:#333; height:20px; border-radius:10px; overflow:hidden; position:relative;">
                        <div id="goal-bar"
                            style="width:0%; height:100%; background:linear-gradient(90deg, #00c6ff, #00ff88); transition:width 1s ease;">
                        </div>
                        <div id="goal-text"
                            style="position:absolute; width:100%; text-align:center; font-size:0.7rem; color:white; font-weight:bold; top:2px; text-shadow:0 0 5px black;">
                            0%</div>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-top:10px;">
                        <span style="font-size:0.85rem; color:#aaa;">Invertido: <span id="fin-invest"
                                style="color:white">$0.00</span></span>
                        <span style="font-size:0.85rem; color:#aaa;">Ingresos: <span id="fin-income"
                                style="color:white">$0.00</span></span>
                        <span style="font-size:0.9rem;">Ganancia: <strong id="fin-profit"
                                style="color:#00ff88">$0.00</strong></span>
                    </div>
                </div>

                <!-- TÍTULO DE PRECIOS -->
                <div style="margin-top:2rem; border-top:1px solid rgba(255,255,255,0.1); padding-top:1rem;">
                    <h3 class="section-subtitle">Configurar Precios de Venta</h3>
                    <p class="text-muted" style="margin-bottom:1rem">Define tu precio de venta aquí para calcular tu
                        ganancia real.</p>
                </div>

                <!-- AQUÍ SE INYECTAN LAS TARJETAS (Verifica que este ID sea único) -->
                <div id="finance-prices-grid" class="cards-grid">
                    <div style="grid-column: 1/-1; text-align: center; padding: 2rem;">
                        <div
                            style="width: 30px; height: 30px; border: 3px solid #00c6ff; border-top: 3px solid transparent; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;">
                        </div>
                        <p style="color:#aaa; margin-top:10px">Cargando catálogo...</p>
                    </div>
                </div>

            </section>

            <!-- MODAL TUTORIAL ANIMADO (OVERLAY TOTAL) -->
            <div id="tutorial-overlay"
                style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:10000; align-items:center; justify-content:center; flex-direction:column; padding:20px; text-align:center;">
                <div id="tut-content" style="max-width:500px;">
                    <div style="font-size:4rem; margin-bottom:20px;" id="tut-icon">🚀</div>
                    <h2 id="tut-title"
                        style="color:#00c6ff; font-size:2rem; margin-bottom:15px; font-family:'Rajdhani'">Bienvenido
                        Socio</h2>
                    <p id="tut-desc" style="color:#ddd; font-size:1.1rem; line-height:1.6; margin-bottom:30px;">
                        Este es tu nuevo panel de control. Aquí podrás gestionar tu negocio de streaming de forma
                        automatizada.
                    </p>

                    <div style="display:flex; justify-content:center; gap:10px;">
                        <button id="tut-btn-back" class="btn-text" onclick="prevStep()"
                            style="display:none">Anterior</button>
                        <button id="tut-btn-next" class="cta-button-main" onclick="nextStep()"
                            style="width:auto; padding:10px 40px;">Siguiente</button>
                    </div>

                    <div id="tut-dots" style="margin-top:30px; display:flex; gap:8px; justify-content:center;">
                        <span class="dot active"></span><span class="dot"></span><span class="dot"></span><span
                            class="dot"></span><span class="dot"></span>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL CLIENTE -->
    <div id="modal-client" class="modal">
        <div class="modal-content glass-card">
            <span class="close-modal" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>Nuevo Cliente Final</h3>
            <form id="form-client">
                <input type="text" id="cli-name" placeholder="Nombre" required>
                <input type="text" id="cli-phone" placeholder="Teléfono" required>
                <textarea id="cli-note" placeholder="Nota interna (Ej: Paga los días 15)"
                    style="width:100%; background:rgba(0,0,0,0.3); border:1px solid #444; color:white; border-radius:10px; padding:10px;"></textarea>
                <button type="submit" class="cta-button-main full-width">Guardar</button>
            </form>
        </div>
    </div>

    <!-- MODAL REPORTAR PAGO -->
    <div id="modal-report-payment" class="modal">
        <div class="modal-content glass-card" style="max-width: 500px;">
            <span class="close-modal" onclick="closeModal('modal-report-payment')">&times;</span>
            <h2 style="color:#00ff88; margin-bottom:10px;">Reportar Recarga</h2>

            <form id="form-payment-proof">

                <label style="color:#aaa; font-size:0.9rem">Método de Pago:</label>
                <select id="pay-method" onchange="togglePayFields()" style="margin-bottom:15px;">
                    <option value="Binance">Binance Pay (USDT)</option>
                    <option value="Zinli">Zinli (USD)</option>
                    <option value="Pago Movil">Pago Móvil (Bs)</option>
                </select>

                <!-- CAMPO DE MONTO UNIVERSAL (Siempre visible) -->
                <div
                    style="background:rgba(255,255,255,0.05); padding:15px; border-radius:10px; margin-bottom:15px; border:1px solid #444;">
                    <label style="color:#00c6ff; font-weight:bold; font-size:0.9rem;">MONTO RECARGADO (USD):</label>
                    <input type="number" id="pay-amount-final" step="0.01" placeholder="Ej: 10.00" required
                        style="font-size:1.5rem; color:white; text-align:center; font-weight:bold; margin:5px 0;"
                        oninput="calculateBs()"> <!-- Calculamos Bs si aplica -->

                    <!-- CALCULADORA VISUAL (Solo texto informativo) -->
                    <div id="calc-display"
                        style="display:none; text-align:center; margin-top:5px; border-top:1px dashed #444; padding-top:5px;">
                        <small style="color:#aaa">Equivale a:</small>
                        <div style="font-size:1.2rem; color:#00ff88; font-weight:bold;">
                            <span id="display-bs">0.00</span> Bs
                        </div>
                        <small style="color:#666; font-size:0.75rem">Tasa: <span id="calc-tasa">...</span></small>
                    </div>
                </div>

                <!-- Campos Comunes -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div>
                        <label>Nombre Titular:</label>
                        <input type="text" id="pay-name" required>
                    </div>
                    <div>
                        <label>Fecha y Hora:</label>
                        <input type="datetime-local" id="pay-date" required style="color:#aaa;">
                    </div>
                </div>

                <label>Correo / Teléfono del titular:</label>
                <input type="text" id="pay-email" required>

                <label>Número de Referencia (Si es Zinli, solo coloque "Zinli"):</label>
                <input type="text" id="pay-ref" placeholder="Ej: 12345678" required
                    style="font-family:monospace; letter-spacing:1px; color:#00ff88;">

                <label>Comprobante (Captura):</label>
                <input type="file" id="pay-img" accept="image/*" required style="margin-bottom:15px;">

                <button type="submit" class="cta-button-main full-width" style="background:#00ff88; color:black;">
                    Enviar Reporte
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL TUTORIAL / GUÍA MAESTRA -->
    <div id="modal-tutorial" class="modal">
        <div class="modal-content glass-card"
            style="max-width: 600px; height: 90vh; display:flex; flex-direction:column;">

            <!-- Cabecera Fija -->
            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-shrink:0;">
                <div>
                    <h2 style="color:#00c6ff; margin:0;">🎓 Manual de Socio</h2>
                    <small style="color:#aaa">Aprende a usar tu oficina virtual</small>
                </div>
                <span class="close-modal" onclick="closeModal('modal-tutorial')">&times;</span>
            </div>

            <!-- Contenido Scrollable -->
            <div class="tutorial-steps" style="overflow-y: auto; padding-right: 10px;">

                <!-- PASO 1: DINERO -->
                <div class="t-step">
                    <div class="t-icon" style="background:#00ff88; color:black;">1</div>
                    <div class="t-content">
                        <h4>💰 Recarga tu Saldo</h4>
                        <p>Ve a la pestaña <strong>Recargar</strong>. Aceptamos Binance, Zinli y Pago Móvil.</p>
                        <ul style="margin-top:5px; color:#ccc; padding-left:20px; font-size:0.85rem;">
                            <li>Haz la transferencia.</li>
                            <li>Usa el botón <strong>"📲 Notificar"</strong> para enviar el comprobante.</li>
                            <li>El admin acreditará tu saldo para que compres al instante.</li>
                        </ul>
                    </div>
                </div>

                <!-- PASO 2: COMPRA -->
                <div class="t-step">
                    <div class="t-icon" style="background:#00c6ff;">2</div>
                    <div class="t-content">
                        <h4>🛒 Compra en Tienda</h4>
                        <p>En <strong>Tienda Stock</strong> verás las cuentas disponibles en tiempo real. <br>
                            Al dar clic en "Comprar", el sistema <strong>descuenta el saldo y te entrega la cuenta
                                inmediatamente</strong>.</p>
                    </div>
                </div>

                <!-- PASO 3: GESTIÓN (EL NÚCLEO) -->
                <div class="t-step">
                    <div class="t-icon" style="background:#e100ff; color:white;">3</div>
                    <div class="t-content">
                        <h4>📦 Gestión de "Mis Cuentas"</h4>
                        <p>Aquí está tu inventario. Tienes herramientas de control total:</p>
                        <ul style="margin-top:5px; color:#ccc; padding-left:20px; font-size:0.85rem; line-height:1.6;">
                            <li><strong>👁️ Ver:</strong> Copia el correo y contraseña para tu cliente.</li>
                            <li><strong>📝 Asignar:</strong> Usa el selector para indicar qué cliente usa esa cuenta
                                (previamente creado en "Mis Clientes").</li>
                            <li><strong>✏️ Editar:</strong> Si es una cuenta completa, entra a "Gestionar" para cambiar
                                los PINs o Fechas de tus clientes manualmente.</li>
                        </ul>
                    </div>
                </div>

                <!-- PASO 4: RENOVACIÓN INTELIGENTE (IMPORTANTE) -->
                <div class="t-step" style="border: 1px solid #00c6ff; background:rgba(0, 198, 255, 0.05);">
                    <div class="t-icon">4</div>
                    <div class="t-content">
                        <h4 style="color:#00c6ff">🔄 Renovaciones (Sistema Automático)</h4>
                        <p>Las cuentas duran 30 días. El sistema te da <strong>3 días extra</strong> de gracia (Total 33
                            días). ¿Qué pasa después?</p>

                        <div style="margin-top:10px; background:rgba(0,0,0,0.3); padding:10px; border-radius:8px;">
                            <p style="margin-bottom:5px"><strong>El Interruptor (Switch):</strong></p>
                            <ul style="padding-left:20px; margin:0; font-size:0.85rem; color:#ddd;">
                                <li>🟢 <strong>Encendido:</strong> Si tienes saldo, al día 33 se cobra solo y se renueva
                                    la fecha.</li>
                                <li>⚫ <strong>Apagado:</strong> Al día 33, la cuenta se elimina de tu panel y regresa al
                                    admin (sin cobro).</li>
                            </ul>
                        </div>
                        <p style="margin-top:5px; font-size:0.8rem; color:#00ff88">💡 Tip: También puedes usar el botón
                            <strong>"↻ Renovar"</strong> para adelantar el pago manualmente.
                        </p>
                    </div>
                </div>

                <!-- NUEVO PASO: FINANZAS Y GANANCIAS -->
                <div class="t-step" style="border: 1px solid #e100ff; background:rgba(225, 0, 255, 0.05);">
                    <div class="t-icon" style="background:#e100ff; color:white;">5</div>
                    <div class="t-content">
                        <h4 style="color:#e100ff">📈 Control de Ganancias</h4>
                        <p>¡No pierdas dinero! Ve a la pestaña <strong>Finanzas</strong>:</p>
                        <ul style="margin-top:5px; color:#ccc; padding-left:20px; font-size:0.85rem; line-height:1.6;">
                            <li><strong>Precios de Venta:</strong> Configura a cuánto vendes tú. El sistema calculará tu
                                ganancia neta.</li>
                            <li><strong>Meta Mensual:</strong> Fija una meta (Ej: $500) y verás una barra de progreso
                                que se llena con tus ventas.</li>
                            <li><strong>Estadísticas:</strong> Visualiza cuánto has invertido vs. cuánto has ganado
                                real.</li>
                        </ul>
                    </div>
                </div>

                <!-- PASO 5: EXTRAS -->
                <div class="t-step">
                    <div class="t-icon" style="background:#ff0055; color:white;">5</div>
                    <div class="t-content">
                        <h4>📢 Soporte y Marketing</h4>
                        <ul style="margin-top:5px; color:#ccc; padding-left:20px; font-size:0.85rem;">
                            <li><strong>🎨 Publicidad:</strong> Descarga imágenes y videos en "Material Ads" para tus
                                estados desde nuestri canal de telegram.</li>
                            <li><strong>⚠️ Reportar:</strong> Si una cuenta falla, usa el botón de alerta. Verás la
                                solución en la pestaña "Reportes".</li>
                            <li><strong>💡 Feedback:</strong> Envíamos sugerencias o reportes de fallos del sistema en
                                la pestaña "Bugs".</li>
                        </ul>
                    </div>
                </div>

            </div>

            <!-- Pie Fijo -->
            <div style="margin-top:auto; padding-top:15px; border-top:1px solid rgba(255,255,255,0.1);">
                <button class="cta-button-main full-width" onclick="closeModal('modal-tutorial')">¡Entendido, vamos a
                    vender!</button>
            </div>
        </div>
    </div>

    <!-- MODAL VISOR DE CREDENCIALES -->
    <div id="modal-view" class="modal">
        <div class="modal-content glass-card text-center">
            <h2 style="color:#00c6ff">Datos de Acceso</h2>
            <div id="view-content" class="ticket-box" style="text-align:left; font-family:monospace;"></div>
            <button class="cta-button-main full-width" onclick="copyData()">Copiar</button>
            <button class="btn-text" onclick="document.getElementById('modal-view').style.display='none'"
                style="width:100%; margin-top:10px">Cerrar</button>
        </div>
    </div>

    <div id="modal-edit-client" class="modal">
        <div class="modal-content glass-card">
            <span class="close-modal" onclick="closeModal('modal-edit-client')">&times;</span>
            <h3>Editar Cliente</h3>
            <form id="form-edit-client">
                <input type="hidden" id="edit-cli-id">
                <label>Nombre:</label>
                <input type="text" id="edit-cli-name" required>
                <label>Teléfono:</label>
                <input type="text" id="edit-cli-phone" required>

                <!-- CAMPO NUEVO -->
                <label>Nota Interna:</label>
                <textarea id="edit-cli-note" rows="2"
                    style="width:100%; background:rgba(0,0,0,0.3); border:1px solid #444; color:white; border-radius:10px; padding:10px; font-family:inherit;"></textarea>

                <button type="submit" class="cta-button-main full-width">Actualizar Datos</button>
            </form>
        </div>
    </div>

    <!-- MODAL NOTIFICACIONES -->
    <div id="modal-notifications" class="modal">
        <div class="modal-content glass-card"
            style="max-width: 500px; height: 80vh; display: flex; flex-direction: column;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h2 style="color:#e100ff; margin:0;">Mensajes Admin</h2>
                <span class="close-modal" onclick="closeModal('modal-notifications')">&times;</span>
            </div>

            <!-- Aquí se cargan los mensajes -->
            <div id="notification-list" style="flex:1; overflow-y:auto; padding-right:5px;">
                <p class="text-muted" style="text-align:center; margin-top:20px">Cargando...</p>
            </div>

            <button class="btn-text" onclick="closeModal('modal-notifications')"
                style="width:100%; margin-top:1rem; border-top:1px solid #333; padding-top:10px;">Cerrar</button>
        </div>
    </div>

    <!-- MODAL ADMINISTRAR CUENTA COMPLETA -->
    <div id="modal-master-account" class="modal">
        <div class="modal-content glass-card" style="max-width: 800px; width: 95%;">
            <div
                style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px; margin-bottom:15px;">
                <div>
                    <h2 id="master-plat-title" style="color:var(--secondary); margin:0; font-size:1.5rem;">...</h2>
                    <small id="master-email-subtitle"
                        style="color:#aaa; font-family:monospace; font-size:1rem;">...</small>
                </div>
                <span class="close-modal" onclick="closeModal('modal-master-account')">&times;</span>
            </div>

            <div
                style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:white; font-weight:bold;">Contraseña: <span id="master-pass"
                        style="color:#00ff88; font-family:monospace;">...</span></span>
                <button onclick="copyText('master-pass')" class="btn-text" style="padding:5px 10px;">Copiar</button>
            </div>

            <!-- AQUÍ SE LISTARÁN LOS PERFILES -->
            <div id="master-profiles-list" class="stock-grid" style="grid-template-columns: 1fr; gap: 10px;"></div>

            <button class="btn-text" onclick="closeModal('modal-master-account')"
                style="width:100%; margin-top:15px; border-top:1px solid #333; padding-top:10px;">Cerrar</button>
        </div>
    </div>

    <!-- MODAL ADMINISTRAR CUENTA COMPLETA (RESELLER) -->
    <div id="modal-reseller-master" class="modal">
        <!-- Hacemos el modal más ancho para que quepa la grilla -->
        <div class="modal-content glass-card"
            style="max-width: 900px; width: 95%; max-height: 95vh; display:flex; flex-direction:column;">

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h2 style="color:white; margin:0;">Gestionar Cuenta</h2>
                <span class="close-modal" onclick="closeModal('modal-reseller-master')"
                    style="position:static;">&times;</span>
            </div>

            <!-- AQUÍ SE INYECTA TODO EL CONTENIDO DINÁMICO -->
            <div id="master-modal-body" style="overflow-y: auto; padding-right:5px; flex:1;"></div>

            <button class="btn-text" onclick="closeModal('modal-reseller-master')"
                style="width:100%; margin-top:15px;">Cerrar</button>
        </div>
    </div>

    <!-- MODAL POLÍTICAS Y TÉRMINOS -->
    <div id="modal-policies" class="modal">
        <div class="modal-content glass-card" style="max-width: 650px; max-height: 85vh;">

            <!-- Cabecera -->
            <div
                style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:15px; margin-bottom:15px;">
                <h2 style="color:#ffbb00; margin:0;">📜 Términos de Servicio</h2>
                <span class="close-modal" onclick="closeModal('modal-policies')">&times;</span>
            </div>

            <!-- Contenido de Texto -->
            <div class="policy-content">

                <div class="policy-item">
                    <h4>1. Política de Saldo y Pagos</h4>
                    <p>
                        • Todo saldo recargado en la billetera virtual es para <strong>consumo exclusivo de
                            productos</strong>.<br>
                        • <strong>No se realizan reembolsos de dinero</strong> a cuentas bancarias bajo ninguna
                        circunstancia.<br>
                        • Es responsabilidad del distribuidor verificar la Tasa del Día antes de transferir.
                    </p>
                </div>

                <div class="policy-item">
                    <h4>2. Ciclo de Vida y Renovación (Regla de 33 Días)</h4>
                    <p>El sistema es automatizado. Al comprar una cuenta:</p>
                    <ul>
                        <li>Tienes <strong>30 días de servicio</strong> + <strong>3 días de gracia</strong>.</li>
                        <li>Si el interruptor <strong>"Auto-Renovar" está Encendido (ON)</strong>: Al día 33, el sistema
                            descontará saldo y renovará la fecha.</li>
                        <li>Si el interruptor <strong>"Auto-Renovar" está Apagado (OFF)</strong>: Al día 33, la cuenta
                            será <strong>retirada de tu panel y eliminada</strong> sin posibilidad de reclamo.</li>
                        <li><strong style="color:#ff0055">Advertencia:</strong> Si está en ON pero no tienes saldo
                            suficiente, la cuenta también será retirada. Mantén tu billetera recargada.</li>
                    </ul>
                </div>

                <div class="policy-item">
                    <h4>3. Soporte y Garantías</h4>
                    <p>
                        • No se aceptan reportes por chat privado de WhatsApp. Todo fallo debe ser notificado usando el
                        botón <strong>"⚠️ Reportar"</strong> en la tarjeta de la cuenta.<br>
                        • Cambiar la clave o correo maestro de una cuenta compartida anula la garantía
                        inmediatamente.<br>
                        • El tiempo de respuesta estándar es de 0 a 12 horas.
                    </p>
                </div>

                <div class="policy-item">
                    <h4>4. Inventario y Asignación</h4>
                    <p>
                        • El distribuidor es responsable de asignar los nombres de sus clientes en la sección "Mis
                        Cuentas".<br>
                        • D'Level Play Max no se hace responsable por confusión de perfiles si el distribuidor no
                        mantiene su inventario ordenado.
                    </p>
                </div>

            </div>

            <button class="cta-button-main full-width" onclick="closeModal('modal-policies')"
                style="margin-top:20px; background:transparent; border:1px solid #ffbb00; color:#ffbb00;">
                He leído y acepto las normas
            </button>
        </div>
    </div>

    <!-- MODAL REPORTAR FALLO -->
    <div id="modal-report" class="modal">
        <div class="modal-content glass-card">
            <span class="close-modal" onclick="closeModal('modal-report')">&times;</span>
            <h3 style="color:#ff0055">Reportar Falla</h3>

            <form id="form-report">
                <!-- ID DEBE SER 'report-pid' -->
                <input type="hidden" id="report-pid">

                <label>Descripción:</label>
                <!-- ID DEBE SER 'report-msg' -->
                <textarea id="report-msg" rows="3" required placeholder="Detalla el problema..."></textarea>

                <label style="margin-top:10px">Evidencia (Opcional):</label>
                <!-- ID DEBE SER 'report-img' -->
                <input type="file" id="report-img" accept="image/*">

                <button type="submit" class="cta-button-main full-width"
                    style="background:#ff0055; margin-top:15px">Enviar Reporte</button>
            </form>
        </div>
    </div>

    <div id="toast-container"></div>



    <!-- Sonido de Notificación -->
    <audio id="audio-notif" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3"
        preload="auto"></audio>

    <script src="panel.js"></script>
</body>

<?php if (isset($_SESSION['is_demo']) && $_SESSION['is_demo']): ?>
    <!-- BARRA DEMO -->
    <div style="
        position: fixed; top: 0; left: 0; width: 100%; z-index: 99999;
        background: repeating-linear-gradient(45deg, #ffbb00, #ffbb00 10px, #ffd700 10px, #ffd700 20px);
        color: black; font-weight: bold; text-align: center; padding: 5px;
        font-family: sans-serif; font-size: 0.85rem; box-shadow: 0 2px 10px rgba(0,0,0,0.5);
    ">
        🚧 ESTÁS EN MODO INVITADO - Las compras y cambios NO se guardarán.
        <a href="index.php" style="color: black; text-decoration: underline; margin-left: 10px;">Salir</a>
    </div>

    <!-- Ajuste para bajar el contenido y que no lo tape la barra -->
    <style>
        .mobile-top-bar {
            top: 30px !important;
        }

        .sidebar {
            top: 0 !important;
        }

        /* En PC */
        @media (max-width: 768px) {
            .main-content {
                padding-top: 100px !important;
            }

            .sidebar {
                top: auto !important;
            }

            /* En Móvil abajo */
        }
    </style>
<?php endif; ?>

<!-- PRELOADER -->
<div id="preloader">
    <div class="loader-spinner"></div>
    <div class="loader-text">D'LEVEL SYSTEM</div>
</div>

</html>