<?php
session_start();
if (!isset($_SESSION['reseller_id'])) {
    header("Location: index.php");
    exit;
}
// CSRF token para API calls desde el Panel
$csrf_token = $_SESSION['csrf_token'] ?? null;
if (!$csrf_token) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel Distribuidor | D'Level</title>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#000000">
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Rajdhani:wght@500;700&display=swap"
        rel="stylesheet">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

    <style>
        /* Preloader Styles */
        #preloader.hide {
            opacity: 0;
            pointer-events: none;
            transition: 0.5s;
        }

        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #050505;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(0, 198, 255, 0.2);
            border-top-color: #00c6ff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>

    <div class="admin-container">
        <!-- SIDEBAR -->
        <nav class="sidebar">
            <div class="logo-area">
                <img src="../img/logo.png" alt="Logo">
                <h3 style="font-family:'Rajdhani'; color:white; margin:0;">D'LEVEL</h3>
            </div>

            <div class="nav-group">
                <button class="nav-btn active" onclick="showTab('dashboard')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Inicio</span>
                </button>

                <button class="nav-btn" onclick="showTab('stock')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span>Tienda</span>
                </button>

                <!-- Inventory (High Priority) -->
                <button class="nav-btn" onclick="showTab('inventory')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path
                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                        </path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                    <span>Cuentas</span>
                </button>

                <!-- Monitor (Moved to Main) -->
                <button class="nav-btn" onclick="showTab('monitor')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="11" cy="11" r="8"></circle>
                        <polygon points="21 21 16.65 16.65"></polygon>
                        <line x1="11" y1="8" x2="11" y2="14"></line>
                        <line x1="8" y1="11" x2="14" y2="11"></line>
                    </svg>
                    <span>Monitor</span>
                </button>
            </div>

            <div class="nav-group nav-extras">
                <button class="nav-btn mobile-more-btn" onclick="toggleMobileMenu()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="12" cy="5" r="1"></circle>
                        <circle cx="12" cy="19" r="1"></circle>
                    </svg>
                    <span>Más</span>
                </button>

                <!-- Hidden Desktop Items (Logic handled via overlay on mobile) -->
                <div id="desktop-extras" style="display:none;"></div>
            </div>
        </nav>

        <!-- MOBILE MENU OVERLAY -->
        <div id="mobile-menu-overlay">
            <!-- Row 1 -->
            <button class="nav-btn" onclick="showTab('pagos')"><svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg><span style="font-size:0.7rem">Recargas</span></button>
            <button class="nav-btn" onclick="showTab('clients')"><svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                </svg><span style="font-size:0.7rem">Clientes</span></button>
            <button class="nav-btn" onclick="showTab('finanzas')"><svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <path d="M12 2v20M2 12h20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg><span style="font-size:0.7rem">Finanzas</span></button>
            <button class="nav-btn" onclick="openNotifications()"><svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg><span style="font-size:0.7rem">Alertas</span></button>

            <!-- Row 2 -->
            <button class="nav-btn" onclick="showTab('marketing')"><svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <path
                        d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z">
                    </path>
                </svg><span style="font-size:0.7rem">Material</span></button>
            <button class="nav-btn" onclick="showTab('perfil')"><svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path
                        d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
                    </path>
                </svg><span style="font-size:0.7rem">Ajustes</span></button>
            <button class="nav-btn" onclick="openModal('modal-tutorial')"><svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                </svg><span style="font-size:0.7rem">Ayuda</span></button>
            <button class="nav-btn" onclick="logout()" style="color:var(--danger)"><svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg><span style="font-size:0.7rem">Salir</span></button>
        </div>

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
                        <span>1$:</span>
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
                    <div class="dash-card card-urgent" onclick="showTab('inventory')" style="cursor:pointer;">
                        <h3>Por Vencer (3 días)</h3>
                        <div class="value" id="dash-por-vencer" style="color:var(--warning);">0</div>
                        <small style="color:#aaa;">Ir a renovar ➔</small>
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

            <!-- STOCK -->
            <section id="stock" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Tienda / Stock</h1>
                </div>
                <div class="content-wrapper">
                    <div id="stock-grid" class="cards-grid">
                        <!-- JS INJECTS HERE -->
                    </div>
                </div>
            </section>

            <!-- INVENTORY -->
            <section id="inventory" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Mis Cuentas</h1>
                    <button class="cta-main" onclick="loadInventory()">Actualizar</button>
                </div>
                <div class="filter-container">
                    <!-- Botón para ver todo -->
                    <button class="filter-btn active" onclick="setFilter('all', this)">Todo</button>

                    <!-- Filtros de Tipo -->
                    <button class="filter-btn" onclick="setFilter('master', this)">👑 Cuentas Completas</button>
                    <button class="filter-btn" onclick="setFilter('profile', this)">👤 Perfiles</button>

                    <!-- Filtro de Urgencia -->
                    <button class="filter-btn" data-type="soon" onclick="setFilter('soon', this)">⚠️ Por Vencer</button>
                </div>

                <!-- (Aquí abajo va tu input de búsqueda existente) -->
                <div class="content-wrapper">
                    <input type="text" id="search-inventory" placeholder="Buscar cuenta..." onkeyup="filterInventory()"
                        style="margin-bottom:20px;">
                    <div id="inventory-grid" class="cards-grid">
                        <!-- JS INJECTS HERE -->
                        <p class="text-muted">Cargando inventario...</p>
                    </div>
                </div>
            </section>

            <section id="clients" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Cartera de Clientes</h1>
                    <button class="cta-main" onclick="document.getElementById('modal-client').style.display='flex'">+
                        Nuevo Cliente</button>
                </div>

                <div class="content-wrapper">
                    <!-- BUSCADOR DE CLIENTES -->
                    <div class="search-box-container" style="margin-bottom: 20px;">
                        <input type="text" id="search-clients" placeholder="🔍 Buscar por nombre o teléfono..."
                            onkeyup="filterClients()"
                            style="width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.3); color: white;">
                    </div>

                    <div id="clients-grid" class="cards-grid">
                        <!-- JS INJECTS HERE -->
                    </div>
                </div>
            </section>

            <section id="finanzas" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Finanzas</h1>
                    <button class="cta-main" onclick="loadFinance()">🔄 Actualizar</button>
                </div>

                <div class="content-wrapper">

                    <!-- CABECERA CON TOGGLE BS/USD -->
                    <div class="finance-header">
                        <h3 style="margin:0; color:var(--text-muted);">Resumen del Mes</h3>
                        <button class="toggle-currency" id="btn-currency" onclick="toggleCurrency()">
                            <span>🇺🇸 USD</span>
                            <span>🇻🇪 BS</span>
                        </button>
                    </div>

                    <!-- TARJETAS PRINCIPALES -->
                    <div class="finance-grid">

                        <!-- 1. GASTO (Cashflow) -->
                        <div class="fin-card" style="border-bottom: 4px solid var(--danger);">
                            <div class="fin-label">Gasto (Compras)</div>
                            <div class="fin-value" id="fin-gasto">$0.00</div>
                            <div class="fin-sub text-muted">Invertido este mes</div>
                        </div>

                        <!-- 2. VALOR DE CALLE (Inventario) -->
                        <div class="fin-card" style="border-bottom: 4px solid var(--primary);">
                            <div class="fin-label">Valor en la Calle</div>
                            <div class="fin-value" id="fin-calle">$0.00</div>
                            <div class="fin-sub text-success">Total a cobrar clientes</div>
                        </div>

                        <!-- 3. PROFIT NETO -->
                        <div class="fin-card" style="border-bottom: 4px solid var(--secondary);">
                            <div class="fin-label">Ganancia Estimada</div>
                            <div class="fin-value" id="fin-profit">$0.00</div>
                            <div class="fin-sub text-success">ROI: <span id="fin-margen">0%</span></div>
                        </div>
                    </div>

                    <!-- SECCIÓN INFERIOR: META Y TOP VENTAS -->
                    <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">

                        <!-- META MENSUAL -->
                        <div class="glass-card">
                            <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                                <h3>🎯 Meta Mensual</h3>
                                <button onclick="editGoal()"
                                    style="background:transparent; border:1px solid #444; color:#fff; padding:2px 10px; border-radius:10px;">Editar</button>
                            </div>
                            <div style="display:flex; align-items:flex-end; gap:10px; margin-bottom:10px;">
                                <span style="font-size:2rem; font-weight:bold; color:#fff;" id="goal-display">$0</span>
                                <span style="color:#666; padding-bottom:5px;">meta</span>
                            </div>
                            <div style="background:#333; height:8px; border-radius:4px; overflow:hidden;">
                                <div id="goal-bar"
                                    style="width:0%; height:100%; background:var(--secondary); transition: width 1s ease;">
                                </div>
                            </div>
                            <small style="display:block; margin-top:5px; text-align:right; color:#aaa;">Progreso: <span
                                    id="goal-percent">0%</span></small>
                        </div>

                        <!-- TOP PRODUCTOS -->
                        <div class="glass-card">
                            <h3>🏆 Top Ventas</h3>
                            <div id="top-products-list">
                                <p style="color:#666; font-size:0.8rem;">Sin datos aún.</p>
                            </div>
                        </div>
                    </div>

                    <!-- CONFIGURADOR DE PRECIOS -->
                    <h3 style="margin-top:30px;">🛒 Mis Precios de Venta</h3>
                    <p class="text-muted" style="font-size:0.8rem; margin-bottom:15px;">Configura a cuánto vendes para
                        calcular tu ganancia real.</p>
                    <div id="finance-prices-grid" class="cards-grid">
                        <!-- JS INJECTS HERE -->
                    </div>
                </div>
            </section>

            <!-- MONITOR -->
            <section id="monitor" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Monitor de Ventas</h1>
                </div>
                <div class="content-wrapper">
                    <input type="text" id="monitor-search" placeholder="Buscar venta..." onkeyup="loadMonitor()"
                        style="margin-bottom:20px;">
                    <div id="monitor-grid" class="cards-grid"></div>
                </div>
            </section>

            <!-- PAGOS/RECARGAS -->
            <section id="pagos" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Recargas</h1>
                </div>
                <div class="content-wrapper">

                    <div class="glass-card" style="text-align:center; padding:3rem; margin-bottom:2rem;">
                        <h2 style="color:var(--secondary)">Reportar Pago</h2>
                        <p class="text-muted">Si ya realizaste tu transferencia, notifícala aquí.</p>
                        <button class="cta-main" onclick="openPaymentModal()"
                            style="margin-top:20px; background:var(--secondary); color:#000;">+ Nuevo Reporte</button>
                    </div>

                    <h3 style="margin-bottom:1rem;">Métodos Aceptados</h3>
                    <div class="cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                        <div class="glass-card">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px;">
                                <img src="../img/binance.png" width="30" alt="Binance">
                                <h4 style="margin:0;">Binance Pay</h4>
                            </div>
                            <div class="value-box" style="display:flex; justify-content:space-between;">
                                <span id="binance-data"
                                    style="font-family:monospace; color:var(--primary);">346-766-184</span>
                                <span onclick="copyText('binance-data')" style="cursor:pointer;">📋</span>
                            </div>
                        </div>

                        <div class="glass-card">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px;">
                                <img src="../img/zinli.png" width="30" alt="Zinli">
                                <h4 style="margin:0;">Zinli</h4>
                            </div>
                            <div class="value-box" style="display:flex; justify-content:space-between;">
                                <span id="zinli-data" style="font-family:monospace;">carloscruch@gmail.com</span>
                                <span onclick="copyText('zinli-data')" style="cursor:pointer;">📋</span>
                            </div>
                        </div>

                        <div class="glass-card">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px;">
                                <span style="font-size:1.5rem;">📱</span>
                                <h4 style="margin:0;">Pago Móvil</h4>
                            </div>
                            <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:5px;">V-29911214 /
                                0412-3368325 (Banco Vzla)</p>
                            <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:5px;">Recarga Minima: 8
                                USDT</p>
                            <div style="margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.1);">
                                Tasa: <span id="live-tasa-bs"
                                    style="color:var(--secondary); font-weight:bold;">Loading...</span> Bs
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:3rem;">
                        <h3>Historial</h3>
                        <div class="glass-card table-container">
                            <table style="width:100%; text-align:left; border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:1px solid rgba(255,255,255,0.1); color:var(--text-muted);">
                                        <th style="padding:10px;">Fecha</th>
                                        <th style="padding:10px;">Detalle</th>
                                        <th style="padding:10px; text-align:right;">Monto</th>
                                    </tr>
                                </thead>
                                <tbody id="wallet-history-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- REPORTES / BUGS -->
            <section id="reportes" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Soporte Técnico</h1>
                </div>
                <div class="content-wrapper">
                    <div id="reports-grid" class="cards-grid" style="grid-template-columns:1fr;"></div>
                </div>
            </section>

            <section id="feedback" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Feedback</h1>
                </div>
                <div class="content-wrapper">
                    <div class="glass-card">
                        <form id="form-feedback">
                            <label style="color:var(--text-muted);">Tipo</label>
                            <select id="feed-type" style="margin-bottom:10px;">
                                <option value="mejora">💡 Idea / Sugerencia</option>
                                <option value="bug">🐛 Error / Bug</option>
                            </select>
                            <textarea id="feed-msg" rows="4" placeholder="Escribe tu mensaje..." required></textarea>
                            <button type="submit" class="cta-main" style="width:100%; margin-top:15px;">Enviar</button>
                        </form>
                    </div>

                    <h3 style="margin-top:2rem;">Mis Mensajes</h3>
                    <div id="feedback-list" class="cards-grid" style="grid-template-columns:1fr;"></div>
                </div>
            </section>

            <!-- PERFIL -->
            <section id="perfil" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Mi Cuenta</h1>
                </div>
                <div class="content-wrapper">
                    <div class="glass-card" style="max-width:600px; margin:0 auto;">
                        <div style="text-align:center; padding:20px;">
                            <div id="profile-avatar"
                                style="width:80px; height:80px; background:var(--primary); color:black; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:2rem; font-weight:bold; margin-bottom:10px;">
                                U</div>
                            <h2 id="profile-header-name">Usuario</h2>
                        </div>

                        <form id="form-profile">
                            <div style="margin-bottom:10px;">
                                <label style="font-size:0.8rem; color:var(--text-muted);">Email</label>
                                <input type="text" id="prof-email" readonly style="opacity:0.5;">
                            </div>
                            <div style="margin-bottom:10px;">
                                <label style="font-size:0.8rem; color:var(--text-muted);">Nombre</label>
                                <input type="text" id="prof-name">
                            </div>
                            <div style="margin-bottom:10px;">
                                <label style="font-size:0.8rem; color:var(--text-muted);">Password (Opcional)</label>
                                <input type="password" id="prof-pass" placeholder="Nueva contraseña">
                            </div>
                            <button type="submit" class="cta-main" style="width:100%; margin-top:20px;">Guardar
                                Cambios</button>
                        </form>

                        <div style="margin-top:30px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.1);">
                            <h3>Activity Log</h3>
                            <div id="activity-log-list"
                                style="max-height:200px; overflow-y:auto; font-size:0.85rem; color:var(--text-muted);">
                            </div>
                        </div>

                        <button onclick="logout()" class="cta-main"
                            style="background:transparent; border:1px solid var(--danger); color:var(--danger); width:100%; margin-top:20px;">Cerrar
                            Sesión</button>
                    </div>
                </div>
            </section>

            <!-- MARKETING -->
            <section id="marketing" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Marketing</h1>
                </div>
                <div class="content-wrapper" style="text-align:center;">
                    <div class="glass-card" style="padding:3rem;">
                        <div style="font-size:4rem;">✈️</div>
                        <h2>Canal de Telegram</h2>
                        <p class="text-muted" style="margin:20px 0;">Únete para recibir material publicitario
                            actualizado.</p>
                        <button onclick="openTelegramChannel()" class="cta-main" style="background:#0088cc;">Abrir
                            Telegram</button>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- MODALS (PRESERVED IDS FOR JS) -->

    <!-- Modal Client -->
    <!-- (Reusing the one in page structure above if possible, but JS expects 'modal-client' with specific inputs) -->
    <!-- I already included #modal-client in the main body structure within the file (hidden by default in CSS/JS)? 
         Wait, typically modals are outside the main flow. I'll put them here at the bottom. -->

    <!-- NOTE: I added #modal-client, #modal-report-payment, #modal-tutorial in the code above? No, I skipped them to put them here cleanly. -->

    <div id="modal-client" class="modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center;">
        <div class="glass-card" style="width:90%; max-width:400px; position:relative;">
            <span onclick="this.parentElement.parentElement.style.display='none'"
                style="position:absolute; top:10px; right:15px; cursor:pointer; font-size:1.5rem;">&times;</span>
            <h3>Nuevo Cliente</h3>
            <form id="form-client">
                <input type="text" id="cli-name" placeholder="Nombre" required style="margin-bottom:10px;">
                <input type="text" id="cli-phone" placeholder="Teléfono" required style="margin-bottom:10px;">
                <textarea id="cli-note" placeholder="Nota Interna" style="margin-bottom:10px;"></textarea>
                <button type="submit" class="cta-main" style="width:100%;">Guardar</button>
            </form>
        </div>
    </div>

    <div id="modal-report-payment" class="modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center;">
        <div class="glass-card"
            style="width:90%; max-width:500px; position:relative; max-height:90vh; overflow-y:auto;">
            <span onclick="document.getElementById('modal-report-payment').style.display='none'"
                style="position:absolute; top:10px; right:15px; cursor:pointer; font-size:1.5rem;">&times;</span>
            <h3 style="color:var(--secondary);">Reportar Pago</h3>
            <form id="form-payment-proof">
                <select id="pay-method" onchange="togglePayFields()"
                    style="margin-bottom:10px; background-color: #0088cc;">
                    <option value="Binance">Binance Pay</option>
                    <option value="Zinli">Zinli</option>
                    <option value="Pago Movil">Pago Móvil</option>
                </select>
                <div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; margin-bottom:10px;">
                    <label style="color:var(--primary); font-size:0.8rem;">MONTO (USD)</label>
                    <input type="number" id="pay-amount-final" step="0.01" placeholder="0.00" oninput="calculateBs()"
                        required style="font-size:1.5rem; font-weight:bold;">
                    <div id="calc-display"
                        style="display:none; text-align:center; font-size:0.9rem; color:var(--secondary);">
                        = <span id="display-bs">0.00</span> Bs
                    </div>
                </div>

                <input type="text" id="pay-name" placeholder="Nombre Titular" required style="margin-bottom:10px;">
                <input type="datetime-local" id="pay-date" required style="margin-bottom:10px;">
                <input type="text" id="pay-email" placeholder="Email / Teléfono" required style="margin-bottom:10px;">
                <input type="text" id="pay-ref" placeholder="Referencia / ID" required style="margin-bottom:10px;">
                <input type="file" id="pay-img" accept="image/*" required style="margin-bottom:15px;">

                <button type="submit" class="cta-main" style="width:100%;">Enviar Reporte</button>
            </form>
        </div>
    </div>

    <!-- Modal Tutorial (Simplified structure) -->
    <!-- Modal Tutorial (Detailed & Styled) -->
    <div id="modal-tutorial" class="modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:3000; align-items:center; justify-content:center;">
        <div class="glass-card"
            style="max-width:600px; width:95%; height:85vh; display:flex; flex-direction:column; padding:0; border-radius:20px; overflow:hidden;">

            <!-- Header -->
            <div
                style="padding:20px; border-bottom:1px solid rgba(255,255,255,0.1); display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.3);">
                <div>
                    <h3 style="margin:0; font-family:'Rajdhani'; font-size:1.5rem; color:var(--primary);">Centro de
                        Ayuda</h3>
                    <small style="color:var(--text-muted);">Guía rápida de operaciones</small>
                </div>
                <span onclick="document.getElementById('modal-tutorial').style.display='none'"
                    style="cursor:pointer; font-size:2rem; line-height:1; color:#fff;">&times;</span>
            </div>

            <!-- Scrollable Content -->
            <div class="tutorial-steps" style="overflow-y:auto; flex:1; padding:20px;">

                <!-- Section 1: Balance -->
                <div style="margin-bottom:25px;">
                    <h4 style="color:#fff; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span
                            style="background:var(--primary); color:#000; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem;">1</span>
                        Recargar Saldo
                    </h4>
                    <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.5;">
                        Para comprar, necesitas saldo en tu billetera. Ve a la pestaña <strong>Recargas</strong> (💳),
                        selecciona tu método de pago (Binance, Zinli, Pagomóvil) y sube el comprobante. Tu saldo se
                        acreditará en minutos.
                    </p>
                </div>

                <!-- Section 2: Buy -->
                <div style="margin-bottom:25px;">
                    <h4 style="color:#fff; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span
                            style="background:var(--secondary); color:#000; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem;">2</span>
                        Comprar Cuentas
                    </h4>
                    <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.5;">
                        Ve a la <strong>Tienda</strong> (🛍️). Toca cualquier producto para ver detalles y stock. Al
                        confirmar la compra, el costo se descuenta de tu saldo y la cuenta se entrega
                        <strong>instantáneamente</strong>.
                    </p>
                </div>

                <!-- Section 3: Inventory -->
                <div style="margin-bottom:25px;">
                    <h4 style="color:#fff; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span
                            style="background:var(--accent); color:#000; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem;">3</span>
                        Entregar a Clientes
                    </h4>
                    <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.5;">
                        Tus compras van a <strong>Mis Cuentas</strong> (📦). Desde allí puedes:
                    <ul style="padding-left:20px; margin-top:5px; color:#bbb;">
                        <li>Ver correo y contraseña (👁️).</li>
                        <li>Copiar credenciales con un toque.</li>
                        <li>Descargar la ficha técnica para enviarla a tu cliente.</li>
                    </ul>
                    </p>
                </div>

                <!-- Section 4: Monitor -->
                <div style="margin-bottom:25px;">
                    <h4 style="color:#fff; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span
                            style="background:#ff0055; color:#fff; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem;">4</span>
                        Monitor de Ventas
                    </h4>
                    <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.5;">
                        Usa el <strong>Monitor</strong> (📈) para ver qué cuentas están por vencer. El sistema te
                        avisará con insignias de colores (Verde: Activa, Naranja: Por vencer, Rojo: Vencida) para que
                        gestiones las renovaciones.
                    </p>
                </div>

                <!-- Section 5: Support -->
                <div style="margin-bottom:15px;">
                    <h4 style="color:#fff; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span
                            style="background:#fff; color:#000; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem;">?</span>
                        Soporte
                    </h4>
                    <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.5;">
                        Si tienes problemas con una cuenta, usa el botón <strong>Reportar Falla</strong> dentro de la
                        tarjeta de la cuenta. También puedes contactar soporte directo vía Telegram.
                    </p>
                </div>

            </div>

            <!-- Footer Action -->
            <div style="padding:15px; border-top:1px solid rgba(255,255,255,0.1); background:rgba(0,0,0,0.3);">
                <button onclick="document.getElementById('modal-tutorial').style.display='none'" class="cta-main"
                    style="width:100%;">Entendido</button>
            </div>
        </div>
    </div>

    <!-- PRELOADER -->
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- Audio (Preserved) -->
    <audio id="audio-notif" src="../img/notif.mp3" preload="auto"></audio>

    <!-- Scripts -->
    <script src="panel.js?v=<?php echo time(); ?>"></script>
    <script>
        // Inline fixes if needed
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    </script>
</body>

</html>