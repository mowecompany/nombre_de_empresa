<?php
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}

require_once ROOT_PATH . '/Config/Config.php';

if (isset($_GET['conexion_api'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $accion = (string)$_GET['conexion_api'];

    if ($accion === 'config') {
        echo json_encode(['ok' => true, 'config' => read_connection_config()]);
        exit;
    }

    if ($accion === 'red' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $ips = gethostbynamel(gethostname()) ?: [];
        $ips = array_values(array_filter($ips, static fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '127.') && !str_starts_with($ip, '169.254.')));
        echo json_encode(['ok' => true, 'ips' => $ips]);
        exit;
    }

    if ($accion === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $entrada = json_decode(file_get_contents('php://input'), true) ?: [];
        $modo = (string)($entrada['mode'] ?? 'unconfigured');
        $ip = trim((string)($entrada['server_ip'] ?? ''));
        $lanIp = trim((string)($entrada['lan_ip'] ?? ''));
        $puerto = filter_var($entrada['server_port'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($modo, ['unconfigured', 'server', 'client'], true) || !$puerto || $puerto < 1 || $puerto > 65535 || ($modo === 'client' && !filter_var($ip, FILTER_VALIDATE_IP))) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Modo, IP o puerto inválido']);
            exit;
        }
        if ($modo === 'server' && $lanIp !== '' && !filter_var($lanIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'La IP LAN seleccionada no es válida']);
            exit;
        }
        $config = write_connection_config([
            'mode' => $modo,
            'server_ip' => $ip,
            'server_port' => $puerto,
            'lan_ip' => $lanIp
        ]);
        if (!$config) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la configuración']);
            exit;
        }
        echo json_encode(['ok' => true, 'config' => $config]);
        exit;
    }

    if ($accion === 'eliminar') {
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Acción de conexión no válida']);
    exit;
}

$baseUrl = rtrim((string)base_url(), '/');
$applicationPath = defined('APPLICATION_PATH') ? (string)APPLICATION_PATH : '';
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

        <section class="panel">
            <div class="form-grid">
                <div class="field">
                    <label for="conexionModoInput">Modo de instalación</label>
                    <select id="conexionModoInput">
                        <option value="unconfigured">SIN CONFIGURAR</option>
                        <option value="server">SERVIDOR</option>
                        <option value="client">CLIENTE</option>
                    </select>
                </div>
                <div class="field">
                    <label for="conexionIpInput">IP del equipo principal</label>
                    <input id="conexionIpInput" type="text" placeholder="IP DEL SERVIDOR" autocomplete="off">
                </div>
                <div class="field" id="conexionLanField" style="display:none;">
                    <label for="conexionLanInput">IP LAN de este servidor</label>
                    <select id="conexionLanInput"><option value="">SELECCIONA UNA IP LAN</option></select>
                </div>
                <div class="field">
                    <label for="conexionPortInput">Puerto</label>
                    <input id="conexionPortInput" type="number" min="1" max="65535" placeholder="Puerto del servidor" autocomplete="off">
                </div>
            </div>

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
        const baseUrl = <?= json_encode((string)$baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const applicationPath = <?= json_encode($applicationPath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        const modeInput = document.getElementById('conexionModoInput');
        const ipInput = document.getElementById('conexionIpInput');
        const portInput = document.getElementById('conexionPortInput');
        const lanField = document.getElementById('conexionLanField');
        const lanInput = document.getElementById('conexionLanInput');
        const listEl = document.getElementById('conexionLista');
        const countEl = document.getElementById('conexionesCount');
        let conexionEnCurso = false;

        function normalizarPuerto(valor) {
            const raw = String(valor ?? '').trim();
            if (raw === '') return '';
            const numero = Number(raw);
            if (!Number.isInteger(numero) || numero < 1 || numero > 65535) {
                return '';
            }
            return String(numero);
        }

        function normalizarEntrada(item) {
            const ip = String(item?.ip || '')
                .trim()
                .replace(/^https?:\/\//i, '')
                .replace(/\/+$/, '')
                .split('/')[0];

            const port = normalizarPuerto(item?.port);

            if (!ip || !port) {
                return null;
            }

            return {
                ip,
                port,
                fecha: Number(item?.fecha || Date.now()),
                estado: item?.estado === 'conectado' ? 'conectado' : 'no_conectado'
            };
        }

        let conexionesGuardadas = [];

        function obtenerConexionesGuardadas() {
            return conexionesGuardadas;
        }

        async function cargarConexionesGuardadas() {
            const respuesta = await fetch(`${baseUrl}/Views/conexion.php?conexion_api=listar`, { cache: 'no-store' });
            const datos = await respuesta.json();
            conexionesGuardadas = Array.isArray(datos.conexiones)
                ? datos.conexiones.map(normalizarEntrada).filter(Boolean)
                : [];
        }

        async function guardarConexionesGuardadas(entrada) {
            await fetch(`${baseUrl}/Views/conexion.php?conexion_api=guardar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(entrada)
            });
            await cargarConexionesGuardadas();
        }

        function construirUrlConexion(ip, port) {
            return construirUrlConexionConRuta(ip, port, obtenerRutasConexion()[0]);
        }

        function obtenerRutasConexion() {
            const baseUrlObj = new URL(baseUrl);
            const rutas = [baseUrlObj.pathname.replace(/\/$/, ''), applicationPath]
                .map((ruta) => String(ruta || '').trim().replace(/\/$/, ''))
                .filter((ruta, index, lista) => lista.indexOf(ruta) === index);
            return rutas.length > 0 ? rutas : [''];
        }

        function construirUrlConexionConRuta(ip, port, basePath) {
            const baseUrlObj = new URL(baseUrl);
            baseUrlObj.hostname = ip;
            baseUrlObj.port = String(port);
            baseUrlObj.pathname = `${basePath || ''}/Views/login.php`;
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
            const entrada = normalizarEntrada({ ip, port, fecha: Date.now(), estado: 'conectado' });
            if (!entrada) return;

            ipInput.value = entrada.ip;
            portInput.value = entrada.port;

            conectarConComprobacion(entrada.ip, entrada.port);
        }

        async function guardarConexionConectada(entrada) {
            await guardarConexionesGuardadas(entrada);
            renderListaConexiones();
        }

        async function conectarConComprobacion(ip, port) {
            if (conexionEnCurso) return;
            conexionEnCurso = true;
            const boton = document.getElementById('guardarConexionBtn');
            const textoOriginal = boton ? boton.textContent : '';
            if (boton) {
                boton.disabled = true;
                boton.textContent = 'COMPROBANDO SERVIDOR...';
            }

            const controlador = new AbortController();
            const temporizador = window.setTimeout(() => controlador.abort(), 5000);
            try {
                let puertoConectado = port;
                let destino = '';
                if (typeof window.electronAPI?.checkRemoteServer === 'function') {
                    let resultado = { ok: false };
                    for (const ruta of obtenerRutasConexion()) {
                        destino = construirUrlConexionConRuta(ip, puertoConectado, ruta);
                        resultado = await window.electronAPI.checkRemoteServer(destino);
                        if (resultado?.ok) break;
                    }
                    if (!resultado?.ok) {
                        throw new Error(resultado?.error || 'El servidor configurado no responde');
                    }
                } else {
                    await fetch(destino, {
                        method: 'GET',
                        cache: 'no-store',
                        mode: 'no-cors',
                        signal: controlador.signal
                    });
                }
                await guardarConexionConectada(normalizarEntrada({ ip, port: puertoConectado, fecha: Date.now(), estado: 'conectado' }));
                window.location.href = destino || construirUrlConexion(ip, puertoConectado);
            } catch (error) {
                if (typeof Swal !== 'undefined' && Swal.fire) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'COMPUTADOR PRINCIPAL NO DISPONIBLE',
                        text: 'Verifica que el computador principal esté encendido y que el sistema esté iniciado.',
                        confirmButtonText: 'ENTENDIDO',
                        confirmButtonColor: '#2f4a5a'
                    }).then(() => window.location.reload());
                } else {
                    alert('Verifica que el computador principal esté encendido y que el sistema esté iniciado.');
                }
                if (boton) {
                    boton.disabled = false;
                    boton.textContent = textoOriginal;
                }
            } finally {
                window.clearTimeout(temporizador);
                conexionEnCurso = false;
            }
        }

        function eliminarConexion(ip, port) {
            fetch(`${baseUrl}/Views/conexion.php?conexion_api=eliminar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ip, port })
            }).then(cargarConexionesGuardadas).then(renderListaConexiones);
        }

        function guardarYConectar() {
            const ip = String(ipInput.value || '')
                .trim()
                .replace(/^https?:\/\//i, '')
                .replace(/\/+$/, '')
                .split('/')[0];

            const port = normalizarPuerto(portInput.value);

            if (!ip || !port) {
                ipInput.focus();
                return;
            }

            const entrada = normalizarEntrada({ ip, port, fecha: Date.now() });
            if (!entrada) {
                ipInput.focus();
                return;
            }

            renderListaConexiones();
            conectarConComprobacion(entrada.ip, entrada.port);
        }

        function limpiarCampos() {
            ipInput.value = '';
            portInput.value = '';
            ipInput.focus();
        }

        function actualizarCamposModo() {
            const esServidor = modeInput.value === 'server';
            ipInput.disabled = esServidor;
            ipInput.placeholder = esServidor ? 'IP LAN DEL SERVIDOR' : 'IP DEL SERVIDOR';
            lanField.style.display = esServidor ? '' : 'none';
            if (esServidor && lanInput.value) ipInput.value = lanInput.value;
        }

        async function cargarConfiguracionCentral() {
            const respuesta = await fetch(`${baseUrl}/Views/conexion.php?conexion_api=config`, { cache: 'no-store' });
            const datos = await respuesta.json();
            if (!datos?.ok) throw new Error('No se pudo leer la configuración central');
            const config = datos.config || {};
            modeInput.value = config.mode || 'unconfigured';
            ipInput.value = config.server_ip || '';
            portInput.value = config.server_port || 80;
            lanInput.value = config.lan_ip || '';
            actualizarCamposModo();
            if (modeInput.value === 'server') await cargarIpsLan();
        }

        async function cargarIpsLan() {
            const respuesta = typeof window.electronAPI?.getLocalNetworkAddresses === 'function'
                ? await window.electronAPI.getLocalNetworkAddresses().then((ips) => ({ ok: true, ips }))
                : await fetch(`${baseUrl}/Views/conexion.php?conexion_api=red`, { cache: 'no-store' }).then((item) => item.json());
            const ips = Array.isArray(respuesta?.ips) ? respuesta.ips : [];
            const seleccionada = lanInput.value;
            lanInput.innerHTML = '<option value="">SELECCIONA UNA IP LAN</option>';
            ips.forEach((ip) => {
                const option = document.createElement('option');
                option.value = ip;
                option.textContent = ip;
                option.selected = ip === seleccionada;
                lanInput.appendChild(option);
            });
            if (seleccionada && !ips.includes(seleccionada)) {
                const option = document.createElement('option');
                option.value = seleccionada;
                option.textContent = seleccionada;
                option.selected = true;
                lanInput.appendChild(option);
            }
        }

        function construirDestinoConfigurado(ip, port) {
            const base = new URL(baseUrl);
            const ruta = String(applicationPath || base.pathname.replace(/\/$/, '') || '').replace(/\/$/, '');
            base.hostname = ip;
            base.port = String(port);
            base.pathname = `${ruta}/Views/login.php`;
            base.search = '';
            base.hash = '';
            return base.toString();
        }

        async function comprobarDestinoConfigurado(destino) {
            if (typeof window.electronAPI?.checkRemoteServer === 'function') {
                return window.electronAPI.checkRemoteServer(destino);
            }
            const respuesta = await fetch(destino, { method: 'GET', cache: 'no-store', mode: 'no-cors' });
            return { ok: respuesta.ok || respuesta.type === 'opaque' };
        }

        async function guardarYConectar() {
            if (conexionEnCurso) return;
            const modo = modeInput.value;
            const puerto = normalizarPuerto(portInput.value);
            const ip = modo === 'server' ? lanInput.value.trim() : ipInput.value.trim();
            if (!['server', 'client'].includes(modo) || !ip || !puerto) {
                Swal.fire({ icon: 'warning', title: 'CONFIGURACIÓN INCOMPLETA', text: 'Selecciona el modo, la IP del servidor y el puerto.' });
                return;
            }
            conexionEnCurso = true;
            const boton = document.getElementById('guardarConexionBtn');
            if (boton) { boton.disabled = true; boton.textContent = 'GUARDANDO CONFIGURACIÓN...'; }
            try {
                const config = { mode: modo, server_ip: ip, server_port: Number(puerto), lan_ip: modo === 'server' ? ip : '' };
                const guardado = typeof window.electronAPI?.saveConnectionConfig === 'function'
                    ? await window.electronAPI.saveConnectionConfig(config)
                    : await fetch(`${baseUrl}/Views/conexion.php?conexion_api=guardar`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(config) }).then((respuesta) => respuesta.json());
                if (!guardado?.ok) throw new Error(guardado?.error || 'No se pudo guardar la configuración');
                if (modo === 'server') {
                    await Swal.fire({ icon: 'success', title: 'SERVIDOR CONFIGURADO', text: guardado.requiresRestart ? 'Cierra y vuelve a abrir la aplicación para aplicar el modo servidor.' : 'La configuración del servidor fue guardada.' });
                    return;
                }
                if (boton) boton.textContent = 'COMPROBANDO SERVIDOR CONFIGURADO...';
                const destino = construirDestinoConfigurado(ip, puerto);
                const resultado = await comprobarDestinoConfigurado(destino);
                if (!resultado?.ok) throw new Error(resultado?.error || 'El servidor configurado no responde');
                window.location.href = destino;
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'SERVIDOR NO DISPONIBLE', text: error.message || 'No se pudo conectar al servidor configurado.' });
            } finally {
                conexionEnCurso = false;
                if (boton) { boton.disabled = false; boton.textContent = 'GUARDAR Y CONECTAR'; }
            }
        }

        modeInput.addEventListener('change', async () => {
            actualizarCamposModo();
            if (modeInput.value === 'server') await cargarIpsLan();
        });
        lanInput.addEventListener('change', () => { if (modeInput.value === 'server') ipInput.value = lanInput.value; });
        document.getElementById('guardarConexionBtn').addEventListener('click', guardarYConectar);
        document.getElementById('limpiarConexionBtn').addEventListener('click', limpiarCampos);
        document.getElementById('refrescarConexionBtn').addEventListener('click', () => window.location.reload());

        async function cargarDiagnostico() {
            const panel = document.getElementById('diagnosticPanel');
            const content = document.getElementById('diagnosticContent');
            const pathEl = document.getElementById('diagnosticPath');
            if (!panel || !content) return;
            panel.style.display = 'block';
            if (typeof window.electronAPI?.getConnectionDiagnostics !== 'function') {
                content.textContent = 'El diagnóstico detallado solo está disponible en el Portable.';
                return;
            }
            const resultado = await window.electronAPI.getConnectionDiagnostics();
            content.textContent = resultado.content || 'No hay diagnósticos registrados todavía.';
            if (pathEl) pathEl.textContent = `Archivo: ${resultado.path || 'Desktop'}`;
        }

        document.getElementById('verDiagnosticoBtn').addEventListener('click', cargarDiagnostico);
        document.getElementById('cerrarDiagnosticoBtn').addEventListener('click', () => {
            document.getElementById('diagnosticPanel').style.display = 'none';
        });

        cargarConfiguracionCentral().catch((error) => {
            console.error('No se pudo cargar la configuración cliente-servidor:', error);
            modeInput.value = 'unconfigured';
            portInput.value = 80;
            actualizarCamposModo();
        });
    </script>
</body>
</html>
