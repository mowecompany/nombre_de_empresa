<?php
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}

require_once ROOT_PATH . '/Config/Config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báscula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?= base_url(); ?>/Assets/js/presence.js" defer></script>
    <style>
        :root {
            --primary: #2f4a5a;
            --primary-strong: #203864;
            --secondary: #4f8ec8;
            --muted: #64748b;
            --border: #dbe4ec;
            --ok: #137333;
            --mal: #c5221f;
            --aviso: #a06000;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 26px 30px 40px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }
        h1 { font-size: 28px; margin: 0 0 6px; display: flex; align-items: center; gap: 12px; }
        .subtitulo { margin: 0 0 22px; color: var(--muted); font-size: 14px; }
        .panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            margin-bottom: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
        }
        .panel h2 {
            margin: 0 0 14px;
            font-size: 13px;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--primary-strong);
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; }
        .dato { background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; }
        .dato b { display: block; font-size: 11px; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
        .dato span { font-size: 15px; word-break: break-all; }
        .peso-caja { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
        .peso-valor { font-size: 62px; font-weight: 800; line-height: 1; }
        .estado-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 14px; border-radius: 999px; font-weight: 700; font-size: 13px;
            background: #e7f4ec; color: var(--ok);
        }
        .estado-chip.mal { background: #fdecea; color: var(--mal); }
        .estado-chip.aviso { background: #fdf3e0; color: var(--aviso); }
        .ok { color: var(--ok); } .mal { color: var(--mal); } .aviso { color: var(--aviso); }
        .aviso-caja {
            margin-top: 14px; padding: 12px 14px; border-radius: 10px;
            background: #fdf3e0; border: 1px solid #f0d9ad; color: #7a4b00;
            font-size: 14px; white-space: pre-line;
        }
        .aviso-caja.error { background: #fdecea; border-color: #f3c2bd; color: #8a1c17; }
        .aviso-caja[hidden] { display: none; }
        .botones { display: flex; flex-wrap: wrap; gap: 10px; }
        button {
            font: inherit; font-weight: 600; cursor: pointer;
            padding: 10px 18px; border-radius: 9px; border: 1px solid var(--border);
            background: #fff; color: var(--primary-strong);
        }
        button:hover { background: #eef2f7; }
        button.principal { background: var(--primary-strong); border-color: var(--primary-strong); color: #fff; }
        button.principal:hover { background: #17284a; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid var(--border); padding: 7px 10px; text-align: left; }
        th { background: #f1f5f9; text-transform: uppercase; font-size: 11px; letter-spacing: .04em; color: var(--muted); }
        pre {
            background: #0f172a; color: #dbeafe; padding: 12px; border-radius: 10px;
            height: 240px; overflow: auto; font-size: 12px; margin: 0;
        }
        .trama { font-family: 'Consolas', monospace; }
    </style>
    <link rel="stylesheet" href="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/css/skeletons.css">
    <script src="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/js/skeletons.js"></script>
</head>
<body>

<h1><i class="fas fa-balance-scale"></i> BÁSCULA</h1>
<p class="subtitulo">Lectura en tiempo real de la balanza ACS-30 conectada por USB (adaptador CH340). Esta pantalla solo muestra lo que envía la aplicación de escritorio; nunca abre el puerto por su cuenta.</p>

<div class="panel">
    <div class="peso-caja">
        <div>
            <b style="display:block;font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;">Peso en vivo</b>
            <div class="peso-valor" id="peso">—</div>
            <div id="pesoDetalle" style="margin-top:8px;color:var(--muted);font-size:13px;">Esperando lectura de la báscula…</div>
        </div>
        <div>
            <span class="estado-chip mal" id="estadoChip"><i class="fas fa-circle"></i> <span id="estadoTexto">Comprobando…</span></span>
        </div>
    </div>
    <div class="aviso-caja" id="avisoCaja" hidden></div>
</div>

<div class="panel">
    <h2>Conexión detectada</h2>
    <div class="grid">
        <div class="dato"><b>Puerto en uso</b><span id="puerto">—</span></div>
        <div class="dato"><b>Adaptador</b><span id="adaptador">—</span></div>
        <div class="dato"><b>Configuración de lectura</b><span id="config">—</span></div>
        <div class="dato"><b>Tara aplicada</b><span id="tara">—</span></div>
        <div class="dato"><b>Proceso principal</b><span id="procesoPrincipal">—</span></div>
        <div class="dato"><b>Propietario de la báscula</b><span id="procesoPropietario">—</span></div>
        <div class="dato"><b>Lector de báscula</b><span id="procesoAuxiliar">—</span></div>
        <div class="dato"><b>Modo de conexión</b><span id="modoBascula">—</span></div>
        <div class="dato"><b>Controlador CH340</b><span id="controladorBascula">—</span></div>
        <div class="dato"><b>Fase actual</b><span id="faseConexion">—</span></div>
    </div>
</div>

<div class="panel">
    <h2>Acciones</h2>
    <div class="botones">
        <button type="button" class="principal" id="btnReconectar"><i class="fas fa-rotate"></i> Reconectar báscula</button>
        <button type="button" id="btnPermisos"><i class="fas fa-shield-halved"></i> Comprobar permisos</button>
        <button type="button" id="btnTarar"><i class="fas fa-scale-balanced"></i> Tarar</button>
        <button type="button" id="btnQuitarTara"><i class="fas fa-eraser"></i> Quitar tara</button>
        <button type="button" id="btnCopiar"><i class="fas fa-copy"></i> Copiar diagnóstico</button>
        <button type="button" id="btnVentana"><i class="fas fa-up-right-from-square"></i> Abrir ventana de diagnóstico</button>
    </div>
    <div class="aviso-caja" id="avisoAccion" hidden></div>
</div>

<div class="panel">
    <h2>Última trama recibida</h2>
    <div class="grid">
        <div class="dato"><b>Texto tal cual llega</b><span class="trama" id="trama">—</span></div>
        <div class="dato"><b>Hexadecimal</b><span class="trama" id="hex">—</span></div>
    </div>
    <p style="margin:12px 0 0;color:var(--muted);font-size:13px;">Si el peso no se interpreta bien, copie esta trama y envíela para ajustar la lectura.</p>
</div>

<div class="panel">
    <h2>Puertos detectados en este equipo</h2>
    <table>
        <thead><tr><th>Puerto</th><th>Fabricante</th><th>VID</th><th>PID</th></tr></thead>
        <tbody id="puertos"><tr><td colspan="4">Sin datos</td></tr></tbody>
    </table>
</div>

<div class="panel">
    <h2>Registro de eventos</h2>
    <pre id="log">Sin registros todavía.</pre>
</div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const api = window.basculaAPI;
    let ultimoDiagnostico = {};
    let primeraCarga = true;
    let paginaActiva = true;
    const cancelaciones = [];

    const mostrarAviso = (elemento, texto, esError) => {
        if (!elemento) return;
        if (!texto) { elemento.hidden = true; elemento.textContent = ''; return; }
        elemento.hidden = false;
        elemento.textContent = texto;
        elemento.className = 'aviso-caja' + (esError ? ' error' : '');
    };

    if (!api) {
        $('estadoTexto').textContent = 'Solo disponible en la aplicación de escritorio';
        mostrarAviso($('avisoCaja'), 'Esta pantalla está abierta en un navegador normal. La báscula solo funciona dentro de la aplicación de escritorio AUTOSERVICIO MI ESTRELLA, que es la que mantiene abierto el puerto de la balanza.', true);
        return;
    }

    function pintarPeso(p) {
        const peso = $('peso');
        const detalle = $('pesoDetalle');
        if (!paginaActiva || !p || !peso || !detalle) return;
        const valor = Number(p.peso || 0);
        peso.textContent = valor.toFixed(3) + ' kg';
        peso.className = 'peso-valor ' + (p.estable === false ? 'aviso' : 'ok');
        const partes = [];
        partes.push(p.estable === false ? 'Lectura aún inestable' : 'Lectura estable');
        if (p.tara) partes.push('tara ' + Number(p.tara).toFixed(3) + ' kg (bruto ' + Number(p.pesoBruto || 0).toFixed(3) + ' kg)');
        if (p.ts) partes.push('actualizado ' + new Date(p.ts).toLocaleTimeString());
        detalle.textContent = partes.join(' · ');
    }

    function pintarTrama(t) {
        const trama = $('trama');
        const hex = $('hex');
        if (!paginaActiva || !t || !trama || !hex) return;
        trama.textContent = JSON.stringify(t.texto);
        hex.textContent = t.hex || '—';
    }

    function pintarEstado(estado) {
        if (!paginaActiva || !estado) return;
        const chip = $('estadoChip');
        const estadoTexto = $('estadoTexto');
        if (!chip || !estadoTexto) return;
        if (estado.disponible === false) {
            chip.className = 'estado-chip mal';
            estadoTexto.textContent = 'Librería de lectura no instalada';
            mostrarAviso($('avisoCaja'), 'Falta instalar la librería serial. En la carpeta ESTRELLA ejecute "npm install" y vuelva a abrir la aplicación.' + (estado.errorLibreria ? '\n\nDetalle: ' + estado.errorLibreria : ''), true);
        } else if (estado.conectado) {
            chip.className = 'estado-chip';
            estadoTexto.textContent = 'Báscula conectada y leyendo';
            mostrarAviso($('avisoCaja'), '', false);
        } else if (estado.ultimoError) {
            chip.className = 'estado-chip mal';
            estadoTexto.textContent = 'Sin conexión con la báscula';
            const ayuda = estado.ultimoError.codigo === 'dispositivo-no-listo'
                ? '\n\nEl código 31 apunta al controlador CH340, no a Acceso denegado. Reconectar puede solicitar autorización para reiniciar únicamente ese dispositivo.'
                : '\n\nPulse "Reconectar báscula". ESTRELLA nunca cerrará otros programas.';
            mostrarAviso($('avisoCaja'), estado.ultimoError.mensaje + ayuda, true);
        } else {
            chip.className = 'estado-chip aviso';
            estadoTexto.textContent = 'Báscula no detectada';
            mostrarAviso($('avisoCaja'), 'No se detecta el adaptador CH340. Revise que la balanza esté encendida y el cable USB conectado; la aplicación reintenta sola cada pocos segundos.', false);
        }

        if (!$('puerto') || !$('adaptador') || !$('config') || !$('tara')) return;
        $('puerto').textContent = estado.puerto || 'sin puerto abierto';
        $('adaptador').textContent = estado.adaptador
            ? ((estado.adaptador.manufacturer || 'adaptador desconocido') + ' — ' + (estado.adaptador.motivo || ''))
            : 'no detectado';
        const c = estado.config || {};
        $('config').textContent = [c.baudRate, c.dataBits, c.parity, c.stopBits].filter((v) => v !== undefined).join(' · ') || '—';
        $('tara').textContent = Number(estado.tara || 0).toFixed(3) + ' kg';
        if ($('procesoPrincipal')) $('procesoPrincipal').textContent = estado.procesoId || '—';
        if ($('procesoPropietario')) $('procesoPropietario').textContent = estado.procesoPropietarioId || '—';
        if ($('procesoAuxiliar')) $('procesoAuxiliar').textContent = estado.procesoAuxiliarId || '—';
        if ($('modoBascula')) $('modoBascula').textContent = estado.modoBascula === 'cliente-compartido' ? 'lector compartido' : (estado.modoBascula || '—');
        if ($('controladorBascula')) {
            const driver = estado.controlador || {};
            $('controladorBascula').textContent = driver.version
                ? ((driver.proveedor || 'Proveedor desconocido') + ' · ' + driver.version + (driver.incompatibleConocido ? ' · versión problemática' : ''))
                : 'no disponible';
        }
        if ($('faseConexion')) $('faseConexion').textContent = String(estado.fase || '—').replace(/-/g, ' ');

        if (estado.ultimaTrama) pintarTrama(estado.ultimaTrama);
        if (estado.ultimoPeso) pintarPeso(estado.ultimoPeso);
    }

    function pintarDiagnostico(d) {
        if (!paginaActiva || !d) return;
        ultimoDiagnostico = d;
        pintarEstado(d);
        const filas = (d.puertos || []).map((p) =>
            '<tr><td>' + (p.path || '') + '</td><td>' + (p.manufacturer || '') + '</td><td>' + (p.vendorId || '') + '</td><td>' + (p.productId || '') + '</td></tr>'
        ).join('');
        if (!$('puertos') || !$('log')) return;
        $('puertos').innerHTML = filas || '<tr><td colspan="4">No se detectaron puertos serie</td></tr>';
        const log = (d.registros || []).map((r) =>
            r.ts + ' [' + r.nivel + '] ' + r.mensaje + (r.extra ? ' :: ' + (typeof r.extra === 'string' ? r.extra : JSON.stringify(r.extra)) : '')
        ).join('\n');
        const pre = $('log');
        pre.textContent = log || 'Sin registros todavía.';
        pre.scrollTop = pre.scrollHeight;
    }

    async function refrescar() {
        if (primeraCarga) {
            window.EstrellaSkeleton?.show($('puertos'), 'table', { rows: 4 });
        }
        try {
            pintarDiagnostico(await api.diagnostico());
        } catch (error) {
            mostrarAviso($('avisoAccion'), 'No se pudo consultar el estado de la báscula: ' + (error && error.message ? error.message : error), true);
        } finally {
            if (primeraCarga) {
                primeraCarga = false;
                window.EstrellaSkeleton?.hide($('puertos'), true);
            }
        }
    }

    cancelaciones.push(api.onPeso(pintarPeso));
    cancelaciones.push(api.onEstado(pintarEstado));
    cancelaciones.push(api.onTrama(pintarTrama));

    $('btnReconectar').addEventListener('click', async () => {
        const boton = $('btnReconectar');
        const textoOriginal = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<i class="fas fa-rotate"></i> Reiniciando el puerto…';
        mostrarAviso($('avisoAccion'), 'Reiniciando el puerto de la báscula…', false);
        try {
            mostrarAviso($('avisoAccion'), 'Cerrando el lector anterior. Si persiste el código 31, Windows pedirá autorización para reiniciar únicamente el CH340…', false);
            const r = await api.reconectar();
            if (r && r.estado) pintarEstado(r.estado);
            if (r && r.ok) {
                mostrarAviso($('avisoAccion'), 'Báscula reconectada en ' + (r.puerto || 'el puerto detectado') + '.', false);
            } else {
                const recuperacion = r && r.estado && r.estado.ultimaRecuperacion ? r.estado.ultimaRecuperacion.mensaje + ' ' : '';
                mostrarAviso($('avisoAccion'), recuperacion + (r && r.error ? r.error : 'No se pudo reconectar con la báscula.') + ' La aplicación sigue intentándolo sola: en cuanto COM3 quede libre, se conecta automáticamente.', true);
            }
        } catch (error) {
            mostrarAviso($('avisoAccion'), 'No se pudo reconectar: ' + (error && error.message ? error.message : error), true);
        } finally {
            boton.disabled = false;
            boton.innerHTML = textoOriginal;
        }
        refrescar();
        setTimeout(refrescar, 1500);
    });
    $('btnPermisos').addEventListener('click', async () => {
        try {
            const r = await api.probarPermisos();
            mostrarAviso($('avisoAccion'), (r.administrador ? 'Modo administrador. ' : 'Modo normal. ') + r.conclusion, false);
            refrescar();
        } catch (error) {
            mostrarAviso($('avisoAccion'), 'No se pudo comprobar el nivel de permisos: ' + error.message, true);
        }
    });
    $('btnTarar').addEventListener('click', async () => { await api.tarar(); refrescar(); });
    $('btnQuitarTara').addEventListener('click', async () => { await api.quitarTara(); refrescar(); });
    $('btnVentana').addEventListener('click', () => api.abrirDiagnostico());
    $('btnCopiar').addEventListener('click', async () => {
        const texto = JSON.stringify(ultimoDiagnostico, null, 2);
        try {
            await navigator.clipboard.writeText(texto);
            mostrarAviso($('avisoAccion'), 'Diagnóstico copiado. Ya puede pegarlo donde lo necesite.', false);
        } catch (error) {
            mostrarAviso($('avisoAccion'), 'No se pudo copiar automáticamente. Seleccione el registro de abajo y cópielo a mano.', true);
        }
    });

    refrescar();
    const intervalo = setInterval(refrescar, 2000);
    window.addEventListener('pagehide', () => {
        paginaActiva = false;
        clearInterval(intervalo);
        cancelaciones.forEach((cancelar) => { try { cancelar(); } catch (_) {} });
    }, { once: true });
})();
</script>
</body>
</html>
