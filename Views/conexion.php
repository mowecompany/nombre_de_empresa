<?php
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}

require_once ROOT_PATH . '/Config/Config.php';

$baseUrl = rtrim((string)base_url(), '/');

/**
 * Direcciones de red detectadas por el propio servidor.
 * Sirven de respaldo cuando la aplicación de escritorio no entrega la lista.
 */
$ipsServidor = [];
if (function_exists('net_get_interfaces')) {
    $interfaces = @net_get_interfaces();
    if (is_array($interfaces)) {
        foreach ($interfaces as $datos) {
            foreach (($datos['unicast'] ?? []) as $unicast) {
                $ip = $unicast['address'] ?? '';
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && strpos($ip, '127.') !== 0
                    && strpos($ip, '169.254.') !== 0) {
                    $ipsServidor[] = $ip;
                }
            }
        }
    }
}
if (!$ipsServidor && stripos(PHP_OS_FAMILY, 'Windows') === 0) {
    $salida = @shell_exec('ipconfig');
    if (is_string($salida) && preg_match_all('/(\d+\.\d+\.\d+\.\d+)/', $salida, $coincidencias)) {
        foreach ($coincidencias[1] as $ip) {
            if (strpos($ip, '127.') !== 0 && strpos($ip, '169.254.') !== 0 && substr($ip, -4) !== '.255' && $ip !== '255.255.255.0') {
                $ipsServidor[] = $ip;
            }
        }
    }
}
$ipsServidor = array_values(array_unique($ipsServidor));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexión</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= base_url(); ?>/Assets/js/presence.js" defer></script>
    <style>
        :root {
            --primary: #2f4a5a;
            --primary-strong: #203864;
            --secondary: #4f8ec8;
            --muted: #64748b;
            --border: #dbe4ec;
            --bg: #f4f7f9;
            --surface: #ffffff;
            --success: #166534;
            --warning: #b45309;
            --danger: #b91c1c;
            --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            --server-deep: #12372A;
            --server-active: #1F7A4D;
            --server-soft: #DDF3E7;
            --server-canvas: #F7FAF8;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: #1f2937;
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px 40px;
        }

        .page-header {
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0;
            color: var(--primary);
            font-size: clamp(1.8rem, 2vw, 2.5rem);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .subtitle {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(120px, 0.6fr);
            gap: 16px;
            align-items: end;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        label {
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #f8fafc;
            padding: 12px 14px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
        }

        input:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(79, 142, 200, 0.15);
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-strong));
            color: #fff;
        }

        .btn.secondary {
            background: #e2e8f0;
            color: #1e293b;
        }

        .list-panel {
            margin-top: 26px;
        }

        .list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .list-header h2 {
            margin: 0;
            color: var(--primary);
            font-size: 18px;
            text-transform: uppercase;
        }

        .count-badge {
            border: 1px solid #c7d2fe;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
        }

        .connection-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .connection-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #f8fafc;
            padding: 14px 16px;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }

        .connection-item.is-latest {
            border-color: #93c5fd;
            background: #eff6ff;
        }

        .connection-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 118px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 8px;
        }

        .connection-status.connected {
            background: #dcfce7;
            color: var(--success);
            border: 1px solid #86efac;
        }

        .connection-status.disconnected {
            background: #e2e8f0;
            color: var(--muted);
            border: 1px solid #cbd5e1;
        }

        .connection-meta {
            min-width: 0;
            flex: 1;
        }

        .connection-flag {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            color: #1d4ed8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 4px;
        }

        .connection-ip {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .connection-port {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .connection-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .refresh-page-btn {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--primary);
            padding: 10px 12px;
            cursor: pointer;
            font-weight: 800;
        }

        .diagnostic-panel {
            display: none;
            margin-top: 18px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #0f172a;
            color: #dbeafe;
            padding: 12px;
        }

        .diagnostic-panel pre {
            max-height: 240px;
            margin: 0;
            overflow: auto;
            white-space: pre-wrap;
            font: 12px/1.5 Consolas, monospace;
        }

        .empty-state {
            border: 1px dashed var(--border);
            border-radius: 12px;
            background: #f8fafc;
            color: var(--muted);
            padding: 24px;
            text-align: center;
            font-size: 14px;
        }

        .tiny-note {
            margin-top: 14px;
            font-size: 12px;
            color: var(--muted);
        }

        .role-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
        }

        .role-card {
            display: flex;
            flex-direction: column;
            gap: 6px;
            text-align: left;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: #f8fafc;
            padding: 16px;
            cursor: pointer;
            font-family: inherit;
            color: #0f172a;
        }

        .role-card strong {
            font-size: 14px;
            color: var(--primary);
            letter-spacing: 0.02em;
        }

        .role-card span {
            font-size: 12px;
            color: var(--muted);
        }

        .role-card.is-active {
            border-color: var(--secondary);
            background: #eff6ff;
        }

        .server-console {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(240px, 0.65fr);
            gap: 18px;
            margin-top: 18px;
            padding: 20px;
            overflow: hidden;
            border: 1px solid #b9d9c6;
            border-radius: 14px;
            background: var(--server-canvas);
        }

        .server-console::before {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 5px;
            background: var(--server-active);
        }

        .server-console.is-stopped::before,
        .server-console.is-attention::before {
            background: var(--warning);
        }

        .server-console-main {
            min-width: 0;
        }

        .server-console-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .server-console-kicker {
            display: block;
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .server-console-title {
            margin: 0;
            color: var(--server-deep);
            font-size: 22px;
        }

        .server-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            padding: 7px 11px;
            border: 1px solid #9dcdb0;
            border-radius: 999px;
            background: var(--server-soft);
            color: var(--success);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .server-status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--server-active);
            box-shadow: 0 0 0 4px rgba(31, 122, 77, 0.12);
        }

        .server-console.is-stopped .server-status,
        .server-console.is-attention .server-status {
            border-color: #f0c58c;
            background: #fff7e8;
            color: var(--warning);
        }

        .server-console.is-stopped .server-status-dot,
        .server-console.is-attention .server-status-dot {
            background: var(--warning);
            box-shadow: 0 0 0 4px rgba(180, 83, 9, 0.12);
        }

        .server-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .server-metric {
            min-width: 0;
            padding: 12px;
            border: 1px solid #d5e6dc;
            border-radius: 9px;
            background: #fff;
        }

        .server-metric-label {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .server-metric-value {
            display: block;
            overflow: hidden;
            color: var(--server-deep);
            font-size: 15px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .server-console-action {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
            padding-left: 18px;
            border-left: 1px solid #d5e6dc;
        }

        .server-power-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            border: 1px solid #e6a9a9;
            border-radius: 9px;
            background: #fff5f5;
            color: var(--danger);
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .server-power-btn.is-start {
            border-color: var(--server-active);
            background: var(--server-deep);
            color: #fff;
        }

        .server-power-btn:disabled {
            cursor: wait;
            opacity: 0.7;
        }

        .server-action-note {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.45;
            text-align: center;
        }

        .ip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        .ip-list .ip-chip {
            border: 1px solid #93c5fd;
            background: #eff6ff;
            border-radius: 999px;
            padding: 7px 12px;
        }

        @media (max-width: 720px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .connection-item {
                flex-direction: column;
                align-items: stretch;
            }

            .actions {
                justify-content: stretch;
            }

            .actions .btn {
                flex: 1;
            }

            .server-console {
                grid-template-columns: 1fr;
            }

            .server-console-action {
                padding-top: 16px;
                padding-left: 0;
                border-top: 1px solid #d5e6dc;
                border-left: 0;
            }

            .server-metrics {
                grid-template-columns: 1fr;
            }
        }
        .device-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
        }

        .device-card {
            border: 1px solid #d5e6dc;
            border-left: 5px solid var(--server-active, #1F7A4D);
            border-radius: 12px;
            background: #f7faf8;
            padding: 14px 16px;
        }

        .device-card.is-idle {
            border-left-color: #d97706;
            background: #fffbeb;
        }

        .device-card.is-self {
            border-left-color: #1d4ed8;
            background: #eff6ff;
        }

        .device-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .device-name {
            font-size: 14px;
            font-weight: 800;
            color: #12372A;
            text-transform: uppercase;
            word-break: break-word;
        }

        .device-tag {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            white-space: nowrap;
        }

        .device-card.is-idle .device-tag {
            background: #fef3c7;
            color: #92400e;
        }

        .device-card.is-self .device-tag {
            background: #dbeafe;
            color: #1e40af;
        }

        .device-rows {
            display: grid;
            gap: 6px;
        }

        .device-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
        }

        .device-row span:first-child {
            color: #5b7a6c;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .device-row span:last-child {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
            word-break: break-word;
        }

        .device-empty {
            padding: 16px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="page-header">
            <div>
                <h1><i class="fas fa-plug"></i> CONEXIÓN</h1>
                <p class="subtitle">Configura el equipo principal y guarda los puertos más usados para conectarte rápidamente.</p>
            </div>
            <button type="button" class="refresh-page-btn" id="refrescarConexionBtn" title="Actualizar conexión" aria-label="Actualizar conexión"><i class="fas fa-sync-alt"></i></button>
        </header>

        <section class="panel" id="rolPanel" style="margin-bottom:20px;">
            <div class="list-header">
                <h2>Rol de este equipo</h2>
                <span id="rolEstadoBadge" class="count-badge">Sin configurar</span>
            </div>
            <div class="role-options">
                <button type="button" class="role-card" id="rolPrincipalBtn" data-rol="server">
                    <strong><i class="fas fa-server"></i> ESTE EQUIPO ES EL PRINCIPAL</strong>
                    <span>Guarda los datos y atiende a las demás cajas por la red Wi-Fi.</span>
                </button>
                <button type="button" class="role-card" id="rolSecundarioBtn" data-rol="client">
                    <strong><i class="fas fa-laptop"></i> ESTE EQUIPO ES SECUNDARIO</strong>
                    <span>Se conecta al equipo principal usando su IP y su puerto.</span>
                </button>
            </div>

            <div class="server-console is-stopped" id="serverConsole" aria-live="polite">
                <div class="server-console-main">
                    <div class="server-console-heading">
                        <div>
                            <span class="server-console-kicker">Centro de control de red</span>
                            <h3 class="server-console-title" id="serverConsoleTitle">Servidor principal</h3>
                        </div>
                        <span class="server-status"><span class="server-status-dot"></span><span id="serverStatusText">Comprobando</span></span>
                    </div>
                    <div class="server-metrics">
                        <div class="server-metric">
                            <span class="server-metric-label">Dirección para cajas</span>
                            <span class="server-metric-value" id="serverMetricIp">Detectando...</span>
                        </div>
                        <div class="server-metric">
                            <span class="server-metric-label">Puerto TCP</span>
                            <span class="server-metric-value" id="serverMetricPort">8000</span>
                        </div>
                        <div class="server-metric">
                            <span class="server-metric-label">Permiso de Windows</span>
                            <span class="server-metric-value" id="serverMetricFirewall">Comprobando</span>
                        </div>
                    </div>
                </div>
                <div class="server-console-action">
                    <button type="button" class="server-power-btn is-start" id="serverPowerBtn">
                        <i class="fas fa-power-off"></i><span id="serverPowerText">ENCENDER SERVIDOR</span>
                    </button>
                    <p class="server-action-note" id="serverActionNote">El puerto de red no está publicado para otras cajas.</p>
                </div>
            </div>

            <div id="bloquePrincipal" style="display:none;margin-top:18px;">
                <div class="form-grid">
                    <div class="field">
                        <label>Direcciones de este equipo en la red</label>
                        <div id="listaIpsLocales" class="ip-list">Detectando...</div>
                    </div>
                    <div class="field">
                        <label for="puertoServidorInput">Puerto del servidor</label>
                        <input id="puertoServidorInput" type="number" min="1" max="65535" value="8000" autocomplete="off">
                    </div>
                </div>
                <div class="actions">
                    <button type="button" class="btn primary" id="activarPrincipalBtn">ACTIVAR EQUIPO PRINCIPAL</button>
                </div>
                <p class="tiny-note" id="notaPrincipal">Las demás cajas deberán escribir una de estas direcciones y este puerto.</p>
            </div>
        </section>

        <section class="panel list-panel" id="panelDispositivos" style="margin-bottom:20px;display:none;">
            <div class="list-header">
                <h2>Dispositivos conectados</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span id="dispositivosCount" class="count-badge">0 en línea</span>
                    <button type="button" class="btn secondary" id="refrescarDispositivosBtn" style="padding:6px 10px;">ACTUALIZAR</button>
                </div>
            </div>
            <div id="dispositivosLista" class="device-list"><div class="device-empty">Buscando dispositivos en la red...</div></div>
            <p class="tiny-note">Se muestran las cajas que entraron a este servidor en los últimos minutos. La lista se actualiza sola cada 10 segundos.</p>
        </section>

        <section class="panel" id="panelSecundario">
            <div class="form-grid">
                <div class="field">
                    <label for="conexionIpInput">IP del equipo principal</label>
                    <input id="conexionIpInput" type="text" placeholder="Ejemplo: 192.168.1.239" autocomplete="off">
                </div>
                <div class="field">
                    <label for="conexionPortInput">Puerto</label>
                    <input id="conexionPortInput" type="number" min="1" max="65535" placeholder="8000" autocomplete="off">
                </div>
            </div>

            <div class="field" style="margin-top:14px;">
                <label for="conexionPathInput">Carpeta en el servidor (solo si el principal usa XAMPP)</label>
                <input id="conexionPathInput" type="text" placeholder="Déjalo vacío si el principal es la aplicación de escritorio" autocomplete="off">
            </div>

            <p class="tiny-note" style="margin-top:12px;">
                ¿Quieres probar sin un segundo computador? Escribe <strong>127.0.0.1</strong> (o la IP Wi-Fi del equipo)
                con el puerto del principal, normalmente <strong>8000</strong>. La portable aparecerá en la lista de
                dispositivos conectados del equipo principal.
            </p>

            <div class="actions">
                <button type="button" class="btn secondary" id="limpiarConexionBtn">LIMPIAR</button>
                <button type="button" class="btn secondary" id="verDiagnosticoBtn">VER DIAGNÓSTICO</button>
                <button type="button" class="btn primary" id="guardarConexionBtn">GUARDAR Y CONECTAR</button>
            </div>
            <div class="diagnostic-panel" id="diagnosticPanel">
                <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:8px;color:#fff;font-weight:800;">
                    <span>DIAGNÓSTICO DE CONEXIÓN</span>
                    <button type="button" class="btn secondary" id="cerrarDiagnosticoBtn" style="padding:5px 9px;">CERRAR</button>
                </div>
                <pre id="diagnosticContent">Cargando...</pre>
                <div id="diagnosticPath" style="margin-top:8px;color:#93c5fd;font-size:11px;"></div>
            </div>
        </section>

        <section class="panel list-panel">
            <div class="list-header">
                <h2>Puertos guardados</h2>
                <span id="conexionesCount" class="count-badge">0 guardados</span>
            </div>
            <div id="conexionLista" class="connection-list"></div>
            <p class="tiny-note">Si haces clic sobre un puerto guardado, la aplicación intentará conectarse automáticamente a ese destino.</p>
        </section>
    </main>

    <script>
        const STORAGE_KEY = 'autoservicioServidorConexiones';
        const LEGACY_STORAGE_KEY = 'autoservicioServidorIp';
        const baseUrl = <?= json_encode((string)$baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        const PATH_STORAGE_KEY = 'autoservicioServidorCarpeta';

        const ipInput = document.getElementById('conexionIpInput');
        const portInput = document.getElementById('conexionPortInput');
        const pathInput = document.getElementById('conexionPathInput');
        const listEl = document.getElementById('conexionLista');
        const countEl = document.getElementById('conexionesCount');

        function normalizarPuerto(valor) {
            const raw = String(valor ?? '').trim();
            if (raw === '') return '8000';
            const numero = Number(raw);
            if (!Number.isInteger(numero) || numero < 1 || numero > 65535) {
                return '8000';
            }
            return String(numero);
        }

        function normalizarEntrada(item) {
            const ip = String(item?.ip || '')
                .trim()
                .replace(/^https?:\/\//i, '')
                .replace(/\/+$/, '')
                .split('/')[0];

            const port = normalizarPuerto(item?.port || '8000');

            if (!ip) {
                return null;
            }

            return {
                ip,
                port,
                fecha: Number(item?.fecha || Date.now()),
                estado: item?.estado === 'conectado' ? 'conectado' : 'no_conectado'
            };
        }

        function obtenerConexionesGuardadas() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                const parsed = raw ? JSON.parse(raw) : [];
                const normalized = Array.isArray(parsed)
                    ? parsed
                        .map((item) => normalizarEntrada(item))
                        .filter(Boolean)
                    : [];

                if (normalized.length > 0) {
                    return normalized.sort((a, b) => b.fecha - a.fecha).slice(0, 8);
                }

                const legacyIp = localStorage.getItem(LEGACY_STORAGE_KEY);
                if (!legacyIp) {
                    return [];
                }

                const legacyEntry = normalizarEntrada({ ip: legacyIp, port: '8000', fecha: Date.now() });
                return legacyEntry ? [legacyEntry] : [];
            } catch (error) {
                console.warn('No se pudieron cargar las conexiones guardadas:', error);
                return [];
            }
        }

        function guardarConexionesGuardadas(conexiones) {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(conexiones.slice(0, 8)));
            } catch (error) {
                console.warn('No se pudo guardar la lista de conexiones:', error);
            }
        }

        function marcarConexionConfirmada(ip, port) {
            const conexiones = obtenerConexionesGuardadas().map((item) => ({
                ...item,
                estado: item.ip === ip && item.port === String(port) ? 'conectado' : 'no_conectado'
            }));
            guardarConexionesGuardadas(conexiones);
            renderListaConexiones();
        }

        function normalizarCarpeta(valor) {
            const limpio = String(valor ?? '')
                .trim()
                .replace(/^https?:\/\/[^/]+/i, '')
                .replace(/^\/+|\/+$/g, '');
            return limpio ? `/${limpio}` : '';
        }

        function obtenerCarpetaServidor() {
            return normalizarCarpeta(localStorage.getItem(PATH_STORAGE_KEY) || '');
        }

        function guardarCarpetaServidor(valor) {
            const carpeta = normalizarCarpeta(valor);
            if (carpeta) {
                localStorage.setItem(PATH_STORAGE_KEY, carpeta);
            } else {
                localStorage.removeItem(PATH_STORAGE_KEY);
            }
            return carpeta;
        }

        function construirUrlConexion(ip, port, recurso = '/Views/login.php') {
            const baseUrlObj = new URL(baseUrl);
            const basePath = obtenerCarpetaServidor();
            baseUrlObj.protocol = 'http:';
            baseUrlObj.hostname = ip;
            baseUrlObj.port = String(port);
            baseUrlObj.pathname = `${basePath}${recurso}`;
            baseUrlObj.search = '';
            baseUrlObj.hash = '';
            return baseUrlObj.toString();
        }

        function renderListaConexiones() {
            const conexiones = obtenerConexionesGuardadas();
            const conectados = conexiones.filter((conexion) => conexion.estado === 'conectado').length;
            countEl.textContent = `${conexiones.length} guardado${conexiones.length === 1 ? '' : 's'} · ${conectados} conectado${conectados === 1 ? '' : 's'}`;

            if (conexiones.length === 0) {
                listEl.innerHTML = '<div class="empty-state">Todavía no tienes puertos guardados.</div>';
                return;
            }

            listEl.innerHTML = conexiones.map((conexion, index) => {
                const conectado = conexion.estado === 'conectado';
                const estadoClass = conectado ? 'connected' : 'disconnected';
                const estadoTexto = conectado ? 'Conectado' : 'No conectado';

                return `
                    <div class="connection-item ${index === 0 ? 'is-latest' : ''}">
                        <div class="connection-meta">
                            <div class="connection-flag">${index === 0 ? 'Último guardado' : 'Puerto guardado'}</div>
                            <div class="connection-ip">${escapeHtml(conexion.ip)}</div>
                            <div class="connection-port">Puerto: ${escapeHtml(conexion.port)}</div>
                            <div class="connection-status ${estadoClass}">${estadoTexto}</div>
                        </div>
                        <div class="connection-actions">
                            <button type="button" class="btn primary" data-ip="${escapeHtml(conexion.ip)}" data-port="${escapeHtml(conexion.port)}">Conectar</button>
                            <button type="button" class="btn danger" data-delete-ip="${escapeHtml(conexion.ip)}" data-delete-port="${escapeHtml(conexion.port)}">Eliminar</button>
                        </div>
                    </div>
                `;
            }).join('');

            listEl.querySelectorAll('[data-ip]').forEach((button) => {
                button.addEventListener('click', () => {
                    conectarServidor(button.dataset.ip, button.dataset.port);
                });
            });
            listEl.querySelectorAll('[data-delete-ip]').forEach((button) => {
                button.addEventListener('click', () => {
                    eliminarConexion(button.dataset.deleteIp, button.dataset.deletePort);
                });
            });
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function conectarServidor(ip, port) {
            const entrada = normalizarEntrada({ ip, port, fecha: Date.now(), estado: 'no_conectado' });
            if (!entrada) return;

            const conexiones = obtenerConexionesGuardadas()
                .filter((item) => !(item.ip === entrada.ip && item.port === entrada.port))
                .map((item) => ({ ...item, estado: 'no_conectado' }));

            const actualizadas = [entrada, ...conexiones].slice(0, 8);
            guardarConexionesGuardadas(actualizadas);

            localStorage.setItem(LEGACY_STORAGE_KEY, entrada.ip);

            ipInput.value = entrada.ip;
            portInput.value = entrada.port;

            renderListaConexiones();
            conectarConComprobacion(entrada.ip, entrada.port);
        }

        async function conectarConComprobacion(ip, port) {
            const boton = document.getElementById('guardarConexionBtn');
            const textoOriginal = boton ? boton.textContent : '';
            if (boton) {
                boton.disabled = true;
                boton.textContent = 'COMPROBANDO SERVIDOR...';
            }

            const controlador = new AbortController();
            const temporizador = window.setTimeout(() => controlador.abort(), 5000);
            let motivo = '';
            try {
                const comprobacion = construirUrlConexion(ip, port, '/health.php');
                if (hayEscritorio()) {
                    const resultado = await desktopCall('checkRemoteServer', comprobacion);
                    if (!resultado?.ok) {
                        motivo = resultado?.error || `El servidor respondió ${resultado?.status || 'sin datos'}`;
                        throw new Error(motivo);
                    }
                } else {
                    await fetch(comprobacion, {
                        method: 'GET',
                        cache: 'no-store',
                        mode: 'no-cors',
                        signal: controlador.signal
                    });
                }
                marcarConexionConfirmada(ip, String(port));
                window.location.href = construirUrlConexion(ip, port, '/Views/login.php');
            } catch (error) {
                motivo = motivo || error?.message || 'No se pudo contactar al equipo principal';
                const detalle = `Destino: ${ip}:${port}\nMotivo: ${motivo}\n\nRevisa que el equipo principal esté encendido, que su aplicación esté abierta y marcada como PRINCIPAL, y que ambos estén en la misma red Wi-Fi.`;
                if (typeof Swal !== 'undefined' && Swal.fire) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'COMPUTADOR PRINCIPAL NO DISPONIBLE',
                        text: detalle,
                        confirmButtonText: 'ENTENDIDO',
                        confirmButtonColor: '#2f4a5a'
                    });
                } else {
                    alert(detalle);
                }
                if (boton) {
                    boton.disabled = false;
                    boton.textContent = textoOriginal;
                }
            } finally {
                window.clearTimeout(temporizador);
            }
        }

        function eliminarConexion(ip, port) {
            const conexiones = obtenerConexionesGuardadas()
                .filter((item) => !(item.ip === ip && item.port === port));
            guardarConexionesGuardadas(conexiones);
            if (localStorage.getItem(LEGACY_STORAGE_KEY) === ip) {
                localStorage.removeItem(LEGACY_STORAGE_KEY);
            }
            renderListaConexiones();
        }

        function guardarYConectar() {
            const ip = String(ipInput.value || '')
                .trim()
                .replace(/^https?:\/\//i, '')
                .replace(/\/+$/, '')
                .split('/')[0];

            const port = normalizarPuerto(portInput.value);

            if (!ip) {
                ipInput.focus();
                return;
            }

            const entrada = normalizarEntrada({ ip, port, fecha: Date.now() });
            if (!entrada) {
                ipInput.focus();
                return;
            }

            const conexiones = obtenerConexionesGuardadas()
                .filter((item) => !(item.ip === entrada.ip && item.port === entrada.port))
                .map((item) => ({ ...item, estado: 'no_conectado' }));

            const actualizadas = [{ ...entrada, estado: 'no_conectado' }, ...conexiones].slice(0, 8);
            guardarConexionesGuardadas(actualizadas);
            localStorage.setItem(LEGACY_STORAGE_KEY, entrada.ip);
            guardarCarpetaServidor(pathInput ? pathInput.value : '');

            renderListaConexiones();
            conectarConComprobacion(entrada.ip, entrada.port);
        }

        function limpiarCampos() {
            ipInput.value = '';
            portInput.value = '8000';
            if (pathInput) pathInput.value = '';
            ipInput.focus();
        }

        document.getElementById('guardarConexionBtn').addEventListener('click', guardarYConectar);
        document.getElementById('limpiarConexionBtn').addEventListener('click', limpiarCampos);
        document.getElementById('refrescarConexionBtn').addEventListener('click', () => window.location.reload());

        async function cargarDiagnostico() {
            const panel = document.getElementById('diagnosticPanel');
            const content = document.getElementById('diagnosticContent');
            const pathEl = document.getElementById('diagnosticPath');
            if (!panel || !content) return;
            panel.style.display = 'block';
            if (!hayEscritorio()) {
                content.textContent = 'El diagnóstico detallado solo está disponible en la aplicación de escritorio.';
                return;
            }

            let resumen = '';
            try {
                const estado = await desktopCall('getConnectionRuntime');
                const direcciones = Array.isArray(estado.addresses) && estado.addresses.length
                    ? estado.addresses.join(', ')
                    : 'ninguna detectada';
                resumen = [
                    `Rol guardado: ${estado.mode === 'server' ? 'PRINCIPAL' : (estado.mode === 'client' ? 'SECUNDARIO' : (estado.mode === 'stopped' ? 'SERVIDOR APAGADO' : 'SIN CONFIGURAR'))}`,
                    `Publicado en la red: ${estado.isServing ? 'SÍ' : 'NO'}`,
                    `Puerto en uso: ${estado.activePort} (configurado: ${estado.configuredPort})`,
                    `Escuchando en: ${estado.bindHost}`,
                    `Direcciones de este equipo: ${direcciones}`,
                    `Firewall: ${estado.firewall?.intentado ? estado.firewall.mensaje : 'sin comprobar (solo se revisa en modo principal)'}`,
                    `Carpeta configurada del servidor: ${obtenerCarpetaServidor() || '(ninguna)'}`,
                    '',
                    '--- Historial de intentos ---',
                    ''
                ].join('\n');
            } catch (error) {
                resumen = `No se pudo leer el estado del equipo: ${error.message}\n\n`;
            }

            try {
                const resultado = await desktopCall('getConnectionDiagnostics');
                content.textContent = `${resumen}${resultado?.content || 'No hay diagnósticos registrados todavía.'}`;
                if (pathEl) pathEl.textContent = `Archivo: ${resultado?.path || 'Desktop'}`;
            } catch (error) {
                content.textContent = `${resumen}No se pudo leer el archivo de diagnóstico: ${error.message}`;
            }
        }

        document.getElementById('verDiagnosticoBtn').addEventListener('click', cargarDiagnostico);
        document.getElementById('cerrarDiagnosticoBtn').addEventListener('click', () => {
            document.getElementById('diagnosticPanel').style.display = 'none';
        });

        const conexiones = obtenerConexionesGuardadas();
        const ultimaConexion = conexiones[0] || null;

        ipInput.value = ultimaConexion ? ultimaConexion.ip : localStorage.getItem(LEGACY_STORAGE_KEY) || '';
        portInput.value = ultimaConexion ? ultimaConexion.port : '8000';
        if (pathInput) pathInput.value = obtenerCarpetaServidor();

        renderListaConexiones();

        // ===== Rol del equipo (principal / secundario) =====
        const rolPrincipalBtn = document.getElementById('rolPrincipalBtn');
        const rolSecundarioBtn = document.getElementById('rolSecundarioBtn');
        const bloquePrincipal = document.getElementById('bloquePrincipal');
        const panelSecundario = document.getElementById('panelSecundario');
        const rolEstadoBadge = document.getElementById('rolEstadoBadge');
        const listaIpsLocales = document.getElementById('listaIpsLocales');
        const puertoServidorInput = document.getElementById('puertoServidorInput');
        const notaPrincipal = document.getElementById('notaPrincipal');
        const activarPrincipalBtn = document.getElementById('activarPrincipalBtn');
        const serverConsole = document.getElementById('serverConsole');
        const serverStatusText = document.getElementById('serverStatusText');
        const serverMetricIp = document.getElementById('serverMetricIp');
        const serverMetricPort = document.getElementById('serverMetricPort');
        const serverMetricFirewall = document.getElementById('serverMetricFirewall');
        const serverPowerBtn = document.getElementById('serverPowerBtn');
        const serverPowerText = document.getElementById('serverPowerText');
        const serverActionNote = document.getElementById('serverActionNote');

        let rolSeleccionado = 'client';
        let rolFijadoPorUsuario = false;
        const IPS_SERVIDOR = <?php echo json_encode($ipsServidor, JSON_UNESCAPED_UNICODE); ?>;

        // ===== Puente con la aplicación de escritorio =====
        const dentroDeMarco = !!(window.parent && window.parent !== window);
        const puentePendientes = new Map();
        let puenteContador = 0;

        window.addEventListener('message', (evento) => {
            const datos = evento.data;
            if (!datos || datos.tipo !== 'electron-bridge-response') return;
            const pendiente = puentePendientes.get(datos.id);
            if (!pendiente) return;
            puentePendientes.delete(datos.id);
            window.clearTimeout(pendiente.timer);
            if (datos.ok) {
                pendiente.resolve(datos.valor);
            } else {
                pendiente.reject(new Error(datos.error || 'La aplicación de escritorio no pudo completar la acción.'));
            }
        });

        function hayEscritorio() {
            return typeof window.electronAPI?.getConnectionRuntime === 'function' || dentroDeMarco;
        }

        function desktopCall(metodo, ...args) {
            if (typeof window.electronAPI?.[metodo] === 'function') {
                return Promise.resolve(window.electronAPI[metodo](...args));
            }
            if (!dentroDeMarco) {
                return Promise.reject(new Error('Esta opción solo está disponible en la aplicación de escritorio.'));
            }
            return new Promise((resolve, reject) => {
                const id = `puente-${++puenteContador}-${Date.now()}`;
                const tiempoEspera = metodo === 'stopMainServer' ? 130000 : 8000;
                const timer = window.setTimeout(() => {
                    puentePendientes.delete(id);
                    reject(new Error('La aplicación de escritorio no respondió a tiempo.'));
                }, tiempoEspera);
                puentePendientes.set(id, { resolve, reject, timer });
                try {
                    window.parent.postMessage({ tipo: 'electron-bridge-request', id, metodo, args }, window.location.origin);
                } catch (error) {
                    puentePendientes.delete(id);
                    window.clearTimeout(timer);
                    reject(new Error('No se pudo hablar con la aplicación de escritorio.'));
                }
            });
        }

        function avisar(icono, titulo, texto) {
            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({ icon: icono, title: titulo, text: texto, confirmButtonText: 'ENTENDIDO', confirmButtonColor: '#2f4a5a' });
            } else {
                alert(`${titulo}\n\n${texto}`);
            }
        }

        function pintarRol(rol) {
            rolSeleccionado = rol === 'server' ? 'server' : 'client';
            rolPrincipalBtn.classList.toggle('is-active', rolSeleccionado === 'server');
            rolSecundarioBtn.classList.toggle('is-active', rolSeleccionado === 'client');
            bloquePrincipal.style.display = rolSeleccionado === 'server' ? 'block' : 'none';
            panelSecundario.style.display = rolSeleccionado === 'server' ? 'none' : 'block';
            if (panelDispositivos) {
                panelDispositivos.style.display = rolSeleccionado === 'server' ? 'block' : 'none';
            }
        }

        let ipsDetectadas = [];

        async function comprobarAccesoPropio(ip, puerto) {
            if (!ip) return;
            try {
                const resultado = await desktopCall('checkRemoteServer', `http://${ip}:${puerto}/health.php`);
                if (resultado?.ok) {
                    notaPrincipal.textContent = `Comprobado: las demás cajas pueden entrar con ${ip}:${puerto}.`;
                } else {
                    notaPrincipal.textContent = resultado?.status === 404
                        ? 'El servidor está activo, pero esta instalación no incluye health.php. Reinstala la versión nueva de la aplicación.'
                        : `Este equipo aún no responde en ${ip}:${puerto}. Usa “Activar equipo principal” para solicitar nuevamente el permiso de red de Windows.`;
                }
            } catch (error) {
                notaPrincipal.textContent = `No se pudo comprobar el acceso por la red: ${error.message}`;
            }
        }

        function mostrarIps(lista) {
            ipsDetectadas = Array.isArray(lista) ? lista.filter(Boolean) : [];
            listaIpsLocales.innerHTML = ipsDetectadas.length
                ? ipsDetectadas.map((ip) => `<span class="ip-chip">${escapeHtml(ip)}</span>`).join('')
                : 'No se detectaron redes. Conecta este equipo al Wi-Fi.';
        }

        function pintarConsolaServidor(estado, visual = 'stopped') {
            const puerto = estado?.isServing
                ? (estado?.activePort || estado?.configuredPort || 8000)
                : (estado?.configuredPort || 8000);
            const ip = (Array.isArray(estado?.addresses) && estado.addresses[0]) || ipsDetectadas[0] || 'Sin red detectada';
            const firewallOk = estado?.firewall?.intentado ? estado.firewall.ok : null;
            serverConsole.classList.toggle('is-stopped', visual === 'stopped');
            serverConsole.classList.toggle('is-attention', visual === 'attention');
            serverMetricIp.textContent = ip;
            serverMetricPort.textContent = String(puerto);
            serverMetricFirewall.textContent = firewallOk === true ? 'Permitido' : (firewallOk === false ? 'Requiere atención' : 'No requerido');

            if (visual === 'active') {
                serverStatusText.textContent = 'Servidor activo';
                serverPowerText.textContent = 'APAGAR SERVIDOR';
                serverPowerBtn.classList.remove('is-start');
                serverPowerBtn.dataset.action = 'stop';
                serverActionNote.textContent = `Las cajas están entrando por ${ip}:${puerto}. Al apagar, perderán la conexión.`;
            } else if (visual === 'attention') {
                serverStatusText.textContent = 'Requiere atención';
                serverPowerText.textContent = 'REINTENTAR ACTIVACIÓN';
                serverPowerBtn.classList.add('is-start');
                serverPowerBtn.dataset.action = 'start';
                serverActionNote.textContent = estado?.firewall?.mensaje || 'Windows debe permitir el acceso de las demás cajas.';
            } else {
                serverStatusText.textContent = 'Servidor apagado';
                serverPowerText.textContent = 'ENCENDER SERVIDOR';
                serverPowerBtn.classList.add('is-start');
                serverPowerBtn.dataset.action = 'start';
                serverActionNote.textContent = `Puerto ${puerto} liberado. Ninguna caja secundaria puede conectarse.`;
            }
        }

        // Lee el estado del equipo probando varias vías: la nueva (getConnectionRuntime),
        // la antigua (getConnectionConfig + getLocalNetworkAddresses) y, como último
        // respaldo, las direcciones detectadas por el propio servidor PHP.
        async function leerEstadoEquipo() {
            try {
                const runtime = await desktopCall('getConnectionRuntime');
                if (runtime && typeof runtime === 'object') return runtime;
            } catch (_) { /* se intenta la vía antigua */ }

            const estado = { addresses: [], mode: null, configuredPort: 8000, isServing: false, parcial: true };

            try {
                const config = await desktopCall('getConnectionConfig');
                if (config && typeof config === 'object') {
                    estado.mode = config.mode || config.config?.mode || null;
                    estado.configuredPort = Number(config.server_port || config.config?.server_port || 8000) || 8000;
                }
            } catch (_) { /* sin configuración legible */ }

            try {
                const direcciones = await desktopCall('getLocalNetworkAddresses');
                const lista = Array.isArray(direcciones) ? direcciones : (direcciones?.addresses || []);
                estado.addresses = lista.map((item) => (typeof item === 'string' ? item : item?.address)).filter(Boolean);
            } catch (_) { /* sin direcciones desde el escritorio */ }

            if (!estado.addresses.length) estado.addresses = IPS_SERVIDOR;
            return estado;
        }

        async function cargarEstadoEquipo(manual = false) {
            if (manual) rolFijadoPorUsuario = true;

            listaIpsLocales.textContent = 'Detectando...';

            let estado = null;
            try {
                estado = await leerEstadoEquipo();
            } catch (error) {
                estado = null;
            }

            if (!estado) {
                mostrarIps(IPS_SERVIDOR);
                rolEstadoBadge.textContent = IPS_SERVIDOR.length
                    ? 'Direcciones detectadas por el servidor'
                    : 'No se pudo leer el estado del equipo';
                return;
            }

            mostrarIps(estado.addresses && estado.addresses.length ? estado.addresses : IPS_SERVIDOR);
            if (!manual || !puertoServidorInput.value) {
                puertoServidorInput.value = String(estado.configuredPort || 8000);
            }

            if (estado.isServing) {
                rolEstadoBadge.textContent = `Principal activo en el puerto ${estado.activePort}`;
                if (!rolFijadoPorUsuario) pintarRol('server');
                if (estado.firewall && estado.firewall.intentado && !estado.firewall.ok) {
                    notaPrincipal.textContent = estado.firewall.mensaje;
                    activarPrincipalBtn.textContent = 'REINTENTAR PERMISO DE RED';
                    activarPrincipalBtn.disabled = false;
                    pintarConsolaServidor(estado, 'attention');
                } else {
                    activarPrincipalBtn.textContent = 'EQUIPO PRINCIPAL ACTIVO';
                    activarPrincipalBtn.disabled = true;
                    pintarConsolaServidor(estado, 'active');
                    comprobarAccesoPropio(ipsDetectadas[0], estado.activePort);
                }
            } else if (estado.mode === 'server') {
                rolEstadoBadge.textContent = 'Principal configurado. Reinicia la aplicación para activarlo.';
                activarPrincipalBtn.textContent = 'ACTIVAR EQUIPO PRINCIPAL';
                activarPrincipalBtn.disabled = false;
                if (!rolFijadoPorUsuario) pintarRol('server');
                pintarConsolaServidor(estado, 'attention');
            } else if (estado.mode === 'client') {
                rolEstadoBadge.textContent = 'Equipo secundario';
                activarPrincipalBtn.textContent = 'ACTIVAR EQUIPO PRINCIPAL';
                activarPrincipalBtn.disabled = false;
                if (!rolFijadoPorUsuario) pintarRol('client');
                pintarConsolaServidor(estado, 'stopped');
            } else if (estado.mode === 'stopped') {
                rolEstadoBadge.textContent = `Servidor apagado · Puerto ${estado.configuredPort || 8000} liberado`;
                activarPrincipalBtn.textContent = 'ENCENDER SERVIDOR';
                activarPrincipalBtn.disabled = false;
                if (!rolFijadoPorUsuario) pintarRol('server');
                pintarConsolaServidor(estado, 'stopped');
            } else {
                rolEstadoBadge.textContent = 'Sin configurar';
                activarPrincipalBtn.textContent = 'ACTIVAR EQUIPO PRINCIPAL';
                activarPrincipalBtn.disabled = false;
                pintarConsolaServidor(estado, 'stopped');
            }
        }

        async function apagarEquipoPrincipal() {
            const puerto = Number(serverMetricPort.textContent) || Number(puertoServidorInput.value) || 8000;
            const confirmacion = typeof Swal !== 'undefined' && Swal.fire
                ? await Swal.fire({
                    icon: 'warning',
                    title: '¿APAGAR EL SERVIDOR?',
                    text: `Las cajas secundarias perderán la conexión y el puerto ${puerto} quedará liberado.`,
                    showCancelButton: true,
                    confirmButtonText: 'SÍ, APAGAR',
                    cancelButtonText: 'CANCELAR',
                    confirmButtonColor: '#b91c1c'
                })
                : { isConfirmed: window.confirm(`Las cajas secundarias perderán la conexión. ¿Apagar el servidor y liberar el puerto ${puerto}?`) };
            if (!confirmacion.isConfirmed) return;

            serverStatusText.textContent = 'Apagando';
            serverPowerBtn.disabled = true;
            serverPowerText.textContent = 'APAGANDO...';
            serverActionNote.textContent = 'Cerrando conexiones y liberando el puerto de red. No cierres la aplicación.';
            try {
                const resultado = await desktopCall('stopMainServer');
                if (!resultado?.ok) throw new Error(resultado?.error || 'No se pudo apagar el servidor.');
                serverStatusText.textContent = 'Servidor apagado';
                serverPowerText.textContent = 'REINICIANDO EN MODO LOCAL...';
                serverActionNote.textContent = resultado.message || `Puerto ${puerto} liberado.`;
            } catch (error) {
                serverPowerBtn.disabled = false;
                serverPowerText.textContent = 'REINTENTAR APAGADO';
                serverActionNote.textContent = error.message || 'No se pudo confirmar el apagado.';
                avisar('error', 'NO SE PUDO APAGAR', error.message || 'El servidor continúa activo.');
                cargarEstadoEquipo(true);
            }
        }

        async function activarEquipoPrincipal() {
            const boton = document.getElementById('activarPrincipalBtn');
            const puerto = Number(puertoServidorInput.value);
            if (!Number.isInteger(puerto) || puerto < 1 || puerto > 65535) {
                puertoServidorInput.focus();
                avisar('warning', 'PUERTO NO VÁLIDO', 'Escribe un puerto entre 1 y 65535. Se recomienda 8000.');
                return;
            }

            if (!hayEscritorio()) {
                avisar('info', 'SOLO EN LA APLICACIÓN DE ESCRITORIO', 'Abre el sistema desde la aplicación instalada para activar el equipo principal.');
                return;
            }

            const textoOriginal = boton ? boton.textContent : '';
            if (boton) {
                boton.disabled = true;
                boton.textContent = 'ACTIVANDO...';
            }

            let resultado = null;
            try {
                resultado = await desktopCall('saveConnectionConfig', {
                    mode: 'server',
                    server_port: puerto,
                    server_ip: '',
                    lan_ip: ''
                });
                if (resultado && resultado.ok === false) {
                    throw new Error(resultado.error || 'No se pudo guardar la configuración.');
                }
            } catch (error) {
                avisar('error', 'NO SE PUDO ACTIVAR', error.message || 'Error desconocido al activar el equipo principal.');
                return;
            } finally {
                if (boton) {
                    boton.disabled = false;
                    boton.textContent = textoOriginal;
                }
            }

            const avisoFirewall = resultado?.firewall && !resultado.firewall.ok
                ? `\n\n${resultado.firewall.mensaje}`
                : '';

            if (resultado?.requiresRestart === false) {
                if (resultado?.firewall && !resultado.firewall.ok) {
                    avisar('warning', 'PERMISO DE RED PENDIENTE', resultado.firewall.mensaje);
                } else {
                    avisar('success', 'EQUIPO PRINCIPAL ACTIVO', `Este equipo ya atiende a las demás cajas en el puerto ${puerto}.`);
                }
                cargarEstadoEquipo(true);
                return;
            }

            if (typeof Swal !== 'undefined' && Swal.fire) {
                const confirmacion = await Swal.fire({
                    icon: 'success',
                    title: 'EQUIPO PRINCIPAL CONFIGURADO',
                    text: `Este equipo atenderá a las demás cajas en el puerto ${puerto}. Se debe reiniciar la aplicación para activarlo.${avisoFirewall}`,
                    showCancelButton: true,
                    confirmButtonText: 'REINICIAR AHORA',
                    cancelButtonText: 'MÁS TARDE',
                    confirmButtonColor: '#2f4a5a'
                });
                if (confirmacion.isConfirmed) {
                    try {
                        await desktopCall('restartApp');
                        return;
                    } catch (error) {
                        avisar('warning', 'REINICIA MANUALMENTE', `Cierra y vuelve a abrir la aplicación para activar el equipo principal. (${error.message})`);
                    }
                }
            }

            cargarEstadoEquipo(true);
        }

        async function marcarEquipoSecundario() {
            pintarRol('client');
            try {
                await desktopCall('saveConnectionConfig', { mode: 'client', server_port: 8000 });
                rolEstadoBadge.textContent = 'Equipo secundario';
            } catch (error) {
                rolEstadoBadge.textContent = 'Equipo secundario (sin guardar)';
            }
        }

        rolPrincipalBtn.addEventListener('click', () => {
            rolFijadoPorUsuario = true;
            pintarRol('server');
            cargarEstadoEquipo(true);
        });
        rolSecundarioBtn.addEventListener('click', () => {
            rolFijadoPorUsuario = true;
            marcarEquipoSecundario();
        });
        document.getElementById('activarPrincipalBtn').addEventListener('click', activarEquipoPrincipal);
        serverPowerBtn.addEventListener('click', () => {
            if (serverPowerBtn.dataset.action === 'stop') {
                apagarEquipoPrincipal();
            } else {
                activarEquipoPrincipal();
            }
        });

        const panelDispositivos = document.getElementById('panelDispositivos');
        const dispositivosLista = document.getElementById('dispositivosLista');
        const dispositivosCount = document.getElementById('dispositivosCount');
        const refrescarDispositivosBtn = document.getElementById('refrescarDispositivosBtn');
        let cajaPropiaId = localStorage.getItem('autoservicioCajaPresenciaId') || '';
        let equipoPropio = '';

        (async () => {
            try {
                const identidad = window.obtenerIdentidadCaja ? await window.obtenerIdentidadCaja() : null;
                if (identidad) {
                    cajaPropiaId = identidad.caja_id || cajaPropiaId;
                    equipoPropio = String(identidad.equipo || '').toUpperCase();
                }
            } catch (error) {
                // Sin identidad solo se pierde la marca "este equipo".
            }
        })();

        function textoActividad(segundos) {
            const valor = Number(segundos) || 0;
            if (valor < 20) return 'Ahora mismo';
            if (valor < 60) return `Hace ${valor} s`;
            const minutos = Math.round(valor / 60);
            return `Hace ${minutos} min`;
        }

        function tarjetaDispositivo(caja) {
            const esPropio = caja.caja_id && caja.caja_id === cajaPropiaId;
            const enLinea = caja.en_linea !== false;
            const clases = ['device-card'];
            if (esPropio) clases.push('is-self');
            else if (!enLinea) clases.push('is-idle');
            const mismoComputador = !esPropio
                && equipoPropio !== ''
                && String(caja.equipo || '').toUpperCase() === equipoPropio;
            const etiqueta = esPropio
                ? 'ESTE EQUIPO'
                : (mismoComputador ? 'MISMO COMPUTADOR' : (enLinea ? 'EN LÍNEA' : 'INACTIVA'));
            const tipoCaja = caja.modo === 'portable'
                ? 'Portable (secundaria)'
                : (caja.modo === 'navegador' ? 'Navegador web' : 'Principal');
            const filas = [
                ['Dirección IP', caja.ip || 'Desconocida'],
                ['Tipo de caja', tipoCaja],
                ['Usuario', caja.usuario_nombre || 'Sin sesión'],
                ['Rol', caja.rol || 'Sin rol'],
                ['Sistema', caja.sistema || 'Desconocido'],
                ['Versión', caja.version || '—'],
                ['Puerto usado', caja.puerto ? String(caja.puerto) : '—'],
                ['Conectada desde', caja.primera_conexion || '—'],
                ['Última actividad', textoActividad(caja.segundos)]
            ];
            return `
                <article class="${clases.join(' ')}">
                    <div class="device-head">
                        <span class="device-name">${escapeHtml(caja.equipo || caja.nombre || 'CAJA')}</span>
                        <span class="device-tag">${escapeHtml(etiqueta)}</span>
                    </div>
                    <div class="device-rows">
                        ${filas.map(([titulo, valor]) => `<div class="device-row"><span>${escapeHtml(titulo)}</span><span>${escapeHtml(String(valor))}</span></div>`).join('')}
                    </div>
                </article>`;
        }

        async function cargarDispositivosConectados(forzar = false) {
            if (!dispositivosLista || rolSeleccionado !== 'server') return;
            if (!forzar && document.visibilityState === 'hidden') return;
            try {
                const respuesta = await fetch(`${baseUrl}/api/v1/index.php?action=presence`, { credentials: 'same-origin', cache: 'no-store' });
                const resultado = await respuesta.json();
                const cajas = Array.isArray(resultado?.data) ? resultado.data : [];
                const enLinea = cajas.filter((caja) => caja.en_linea !== false).length;
                dispositivosCount.textContent = `${enLinea} en línea · ${cajas.length} registradas`;
                dispositivosLista.innerHTML = cajas.length
                    ? cajas.map(tarjetaDispositivo).join('')
                    : '<div class="device-empty">Todavía no hay otras cajas conectadas a este servidor.</div>';
            } catch (error) {
                dispositivosCount.textContent = 'Sin datos';
                dispositivosLista.innerHTML = '<div class="device-empty">No se pudo consultar los dispositivos conectados.</div>';
            }
        }

        refrescarDispositivosBtn?.addEventListener('click', () => cargarDispositivosConectados(true));
        // Deshabilitado para evitar recargas automáticas (igual que el fix de commit e0af774)
        // window.setInterval(() => cargarDispositivosConectados(), 10000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') cargarDispositivosConectados(true);
        });

        cargarEstadoEquipo().finally(() => cargarDispositivosConectados(true));
    </script>
</body>
</html>
