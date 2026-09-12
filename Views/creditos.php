<?php
session_start();
require_once '../Config/Config.php';
require_once '../Helpers/Helpers.php';

if (!isset($_SESSION['rol'])) {
    header('Location: login.php');
    exit;
}

$baseUrl = rtrim((string)base_url(), '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créditos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary:#2f4a5a; --accent:#c47b32; --border:#dbe4ec; --muted:#64748b; --bg:#f4f7f9; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--bg); color:#263238; font-family:Arial,sans-serif; }
        .page { max-width:1400px; margin:0 auto; padding:24px; }
        .page-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
        h1 { margin:0; color:var(--primary); font-size:24px; }
        .header-actions { display:flex; gap:10px; align-items:center; }
        .button { border:0; border-radius:7px; padding:10px 14px; cursor:pointer; font-weight:700; color:#fff; background:var(--primary); text-decoration:none; }
        .button.secondary { background:#64748b; }
        .toolbar { display:flex; gap:12px; align-items:center; margin-bottom:16px; }
        .search { flex:1; padding:12px 14px; border:1px solid var(--border); border-radius:7px; background:#fff; outline:none; }
        .layout { display:block; }
        .panel { background:#fff; border:1px solid var(--border); border-radius:10px; padding:18px; box-shadow:0 5px 18px rgba(47,74,90,.06); }
        #creditosLista { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:14px; }
        .credit-row { width:100%; min-height:150px; display:block; text-align:left; border:1px solid var(--border); border-radius:12px; padding:14px; cursor:pointer; transition:transform .15s ease, box-shadow .15s ease; box-shadow:0 2px 8px rgba(15,23,42,.04); }
        .credit-row.pending { background:linear-gradient(180deg,#fff7f1 0%, #fff 100%); border-color:#f9d8b6; }
        .credit-row.paid { background:linear-gradient(180deg,#eefcf3 0%, #fff 100%); border-color:#b8e4c7; }
        .credit-row:hover, .credit-row.active { transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.08); }
        .row-top, .profile-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
        .client-name-wrap { flex:1; min-width:0; }
        .client-name { display:block; font-size:17px; line-height:1.1; font-weight:900; color:var(--primary); text-transform:uppercase; }
        .client-lastname { display:block; font-size:15px; line-height:1.1; color:#475569; font-weight:900; text-transform:uppercase; margin-top:3px; }
        .client-meta { margin-top:8px; color:#334155; font-size:11px; font-weight:800; text-transform:uppercase; }
        .credit-side { display:flex; flex-direction:column; align-items:flex-end; gap:8px; min-width:92px; }
        .credit-code { display:inline-flex; align-items:center; justify-content:center; padding:6px 10px; border-radius:10px; background:linear-gradient(135deg,#e0f2fe,#dbeafe); border:1px solid #bfdbfe; color:#1d4ed8; font-size:12px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
        .credit-state-chip { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:5px 8px; border-radius:999px; font-size:10px; font-weight:900; text-transform:uppercase; }
        .credit-state-chip.pending { background:#fff1e6; color:#b45309; }
        .credit-state-chip.paid { background:#eafaf1; color:#18794e; }
        .credit-state-chip .status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
        .credit-state-chip.pending .status-dot { background:#ef4444; }
        .credit-state-chip.paid .status-dot { background:#22c55e; }
        .row-footer { display:flex; align-items:flex-end; justify-content:space-between; gap:10px; margin-top:12px; }
        .credit-total-box { display:flex; flex-direction:column; align-items:flex-start; justify-content:flex-end; }
        .credit-total-box strong { font-size:18px; color:#0f172a; }
        .credit-total-box span { font-size:10px; color:#475569; letter-spacing:.08em; text-transform:uppercase; }
        .credit-status-wrap { display:flex; justify-content:flex-end; }
        .credits-total-zero { font-size:11px; font-weight:900; color:#166534; }
        .tags { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
        .tag { display:inline-flex; padding:4px 8px; border-radius:999px; background:#edf2f5; color:#475569; font-size:11px; font-weight:700; text-transform:uppercase; }
        .amount { text-align:right; color:#9a5b1d; font-weight:800; white-space:nowrap; }
        .muted { color:var(--muted); font-size:12px; }
        .profile-empty { padding:48px 16px; text-align:center; color:var(--muted); }
        .profile-title { margin:0; color:var(--primary); font-size:20px; }
        .profile-header { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:10px; }
        .profile-header-left { flex:1; min-width:0; }
        .profile-header-right { display:flex; align-items:center; gap:10px; flex-shrink:0; }
        .profile-code { display:inline-flex; align-items:center; justify-content:center; min-width:110px; padding:10px 14px; border-radius:12px; background:linear-gradient(135deg,#e0f2fe,#dbeafe); border:1px solid #bfdbfe; color:#1d4ed8; font-size:22px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
        .profile-name { margin:0; color:var(--primary); font-size:30px; font-weight:900; text-transform:uppercase; letter-spacing:.02em; }
        .profile-surname { margin-top:6px; color:#334155; font-size:30px; line-height:1.1; font-weight:900; text-transform:uppercase; }
        .profile-document { margin-top:6px; color:#334155; font-size:13px; font-weight:700; text-transform:uppercase; }
        .profile-table { width:100%; border-collapse:collapse; margin-top:18px; font-size:13px; }
        .profile-table th, .profile-table td { padding:9px 6px; border-bottom:1px solid var(--border); vertical-align:middle; text-align:center; }
        .profile-table th { color:var(--muted); text-align:center; font-size:11px; text-transform:uppercase; }
        .profile-table td:nth-child(2), .profile-table th:nth-child(2) { text-align:center; }
        .profile-table td:nth-child(3), .profile-table th:nth-child(3) { text-align:center; }
        .profile-table td:nth-child(4), .profile-table th:nth-child(4) { text-align:center; }
        .product-image { width:42px; height:42px; object-fit:contain; border:1px solid var(--border); border-radius:6px; background:#fff; vertical-align:middle; display:block; margin:0 auto; }
        .product-name-cell { font-size:14px; font-weight:900; color:#1f2937; text-transform:uppercase; text-align:center; }
        .fecha-hora-cell { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; min-width:110px; text-align:center; }
        .fecha-hora-cell .fecha { font-size:11px; font-weight:800; color:#334155; text-transform:uppercase; }
        .fecha-hora-cell .hora { font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; }
        .pay-button { border:0; border-radius:7px; padding:10px 14px; color:#fff; background:#218739; cursor:pointer; font-weight:700; }
        .credit-status-badge { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:999px; background:#eafaf1; color:#18794e; font-size:12px; font-weight:800; }
        .credit-solo-state { display:flex; align-items:center; justify-content:center; min-height:160px; background:#f8fafc; border:1px dashed var(--border); border-radius:12px; color:#334155; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
        .credit-empty-state { background:#f8fafc; border:1px dashed var(--border); border-radius:10px; padding:24px; color:var(--muted); text-align:center; }
        .product-meta-row { display:flex; align-items:center; gap:10px; min-width:0; }
        .product-meta-text { min-width:0; }
        .product-meta-text strong { display:block; color:#1f2937; font-size:12px; }
        .product-meta-text span { display:block; font-size:11px; color:var(--muted); }
        .info-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin:16px 0; }
        .info-box { background:#f8fafc; border:1px solid var(--border); border-radius:9px; padding:12px; }
        .info-box small { display:block; color:var(--muted); margin-bottom:4px; }
        .info-box strong { color:var(--primary); font-size:15px; }
        #creditoPerfil { display:none; position:fixed; inset:5% 5%; z-index:1100; overflow:auto; max-width:1100px; margin:auto; }
        .profile-overlay { display:none; position:fixed; inset:0; z-index:1090; background:rgba(15,23,42,.48); }
        .profile-close { border:0; background:transparent; color:var(--muted); font-size:22px; cursor:pointer; }
        .pagination { display:flex; align-items:center; justify-content:center; gap:12px; margin-top:16px; }
        .pagination button { border:1px solid var(--border); background:#fff; border-radius:6px; padding:8px 12px; cursor:pointer; }
        .pagination button:disabled { opacity:.45; cursor:not-allowed; }
        .empty { padding:28px; text-align:center; color:var(--muted); }
        @media (max-width:1100px) { #creditosLista { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
        @media (max-width:600px) { #creditosLista { grid-template-columns:1fr; } .page { padding:14px; } .page-header { align-items:flex-start; flex-direction:column; } }
    </style>
</head>
<body>
    <main class="page">
        <header class="page-header">
            <h1><i class="fas fa-credit-card"></i> CRÉDITOS</h1>
            
        </header>
        <div class="toolbar">
            <input class="search" id="buscarCredito" type="search" placeholder="BUSCAR CLIENTE, DOCUMENTO, CÓDIGO O REFERENCIA" oninput="renderizarCreditos()">
        </div>
        <section class="layout">
            <div class="panel">
                <div id="creditosLista">Cargando créditos...</div>
                <div id="creditosPaginacion" class="pagination"></div>
            </div>
        </section>
    </main>
    <div id="perfilCreditoOverlay" class="profile-overlay" onclick="cerrarPerfilCredito()"></div>
    <aside class="panel" id="creditoPerfil">
        <div class="profile-empty"><i class="fas fa-hand-pointer"></i><br>Selecciona un crédito para ver el perfil completo del cliente.</div>
    </aside>
    <div id="modalPagoCredito" class="profile-overlay" style="z-index:1200;align-items:center;justify-content:center;" onclick="cerrarModalPagoCredito(event)">
        <div class="panel" style="width:min(420px, calc(100% - 28px));" onclick="event.stopPropagation()">
            <h2 style="margin-top:0;color:var(--primary);">PAGAR CRÉDITO</h2>
            <p class="muted">Selecciona cómo recibió el pago.</p>
            <select id="metodoPagoCredito" class="search"><option value="efectivo">EFECTIVO</option><option value="transferencia">TRANSFERENCIA</option></select>
            <button type="button" class="pay-button" style="margin-top:12px;width:100%;" onclick="confirmarPagoCredito()"><i class="fas fa-check"></i> CONFIRMAR PAGO</button>
        </div>
    </div>
<script>
    const creditosUrl = <?= json_encode($baseUrl . '/Controllers/CreditosController.php'); ?>;
    const baseUrlApp = <?= json_encode($baseUrl); ?>;
    let creditos = [];
    let paginaActual = 1;
    const porPagina = 8;

    const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[caracter]));
    const moneda = valor => new Intl.NumberFormat('es-CO', { style:'currency', currency:'COP', maximumFractionDigits:0 }).format(Number(valor || 0));
    const formatearFechaHoraCredito = (valor) => {
        if (!valor) return { fecha: 'N/D', hora: 'N/D' };
        const raw = String(valor).trim();
        if (!raw) return { fecha: 'N/D', hora: 'N/D' };

        const fechaLocal = new Date(raw.includes('T') ? raw : raw.replace(' ', 'T'));
        if (Number.isNaN(fechaLocal.getTime())) {
            return { fecha: raw.toUpperCase(), hora: 'N/D' };
        }

        const fecha = fechaLocal.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
        const hora = fechaLocal.toLocaleTimeString('es-CO', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });

        return { fecha, hora };
    };
    const nombreCliente = credito => `${credito.nombre || ''} ${credito.apellidos || ''}`.trim() || 'CLIENTE SIN NOMBRE';
    const nombreClienteSplit = (credito) => {
        const nombre = String(credito?.nombre || '').trim();
        const apellido = String(credito?.apellidos || '').trim();
        if (nombre || apellido) {
            return { nombre: nombre.toUpperCase() || 'CLIENTE', apellido: apellido.toUpperCase() };
        }
        const nombreCompleto = nombreCliente(credito).split(/\s+/).filter(Boolean);
        return {
            nombre: (nombreCompleto[0] || 'CLIENTE').toUpperCase(),
            apellido: nombreCompleto.slice(1).join(' ').toUpperCase()
        };
    };
    const precioProducto = item => Number(item.precio_actual || item.precio_unitario || 0);
    const imagenProducto = item => item.producto_imagen ? `<img class="product-image" src="${escapar(`${baseUrlApp}/Assets/images/productos/${item.producto_imagen}`)}" alt="">` : '<span class="product-image" style="display:inline-block;"></span>';
    const agruparProductosCredito = (detalles = []) => {
        const mapa = new Map();
        (detalles || []).forEach(item => {
            const clave = String(item.producto_id ?? `${item.producto_nombre || 'producto'}-${item.producto_codigo || 'sin-codigo'}`);
            const actual = mapa.get(clave) || {
                ...item,
                cantidad: 0,
                producto_nombre: item.producto_nombre || 'PRODUCTO',
                producto_codigo: item.producto_codigo || 'N/D',
                producto_imagen: item.producto_imagen || '',
                precio_actual: Number(item.precio_actual || item.precio_unitario || 0),
                total: 0
            };
            const cantidad = Number(item.cantidad || 0) || 0;
            const precio = Number(item.precio_actual || item.precio_unitario || 0) || 0;
            actual.cantidad = Number(actual.cantidad || 0) + cantidad;
            actual.precio_actual = Math.max(Number(actual.precio_actual || 0), precio);
            actual.total = Number(actual.cantidad || 0) * Number(actual.precio_actual || 0);
            actual.producto_nombre = actual.producto_nombre || item.producto_nombre || 'PRODUCTO';
            actual.producto_codigo = actual.producto_codigo || item.producto_codigo || 'N/D';
            actual.producto_imagen = actual.producto_imagen || item.producto_imagen || '';
            mapa.set(clave, actual);
        });
        return Array.from(mapa.values());
    };
    const formatoEstadoCredito = (credito) => {
        const estado = String(credito?.estado || '').trim().toLowerCase();
        if (estado === 'pagado') return 'CRÉDITO PAGADO';
        if (estado === 'pendiente') return 'CRÉDITO PENDIENTE';
        return 'CRÉDITO';
    };

    async function cargarCreditos() {
        const lista = document.getElementById('creditosLista');
        lista.textContent = 'Cargando créditos...';
        try {
            const respuesta = await fetch(`${creditosUrl}?action=listar`);
            const resultado = await respuesta.json();
            if (!resultado.success) throw new Error(resultado.message || 'No se pudieron cargar los créditos');
            creditos = await Promise.all((resultado.data || []).map(async credito => {
                const ids = (credito.credit_ids || [credito.id]).join(',');
                const detalleRespuesta = await fetch(`${creditosUrl}?action=detalle&ids=${encodeURIComponent(ids)}`);
                const detalleResultado = await detalleRespuesta.json();
                return detalleResultado.success ? detalleResultado.data : credito;
            }));
            creditos.sort((a, b) => {
                const estadoA = String(a?.estado || '').trim().toLowerCase() === 'pendiente' ? 0 : 1;
                const estadoB = String(b?.estado || '').trim().toLowerCase() === 'pendiente' ? 0 : 1;
                if (estadoA !== estadoB) return estadoA - estadoB;
                return new Date(b?.fecha_creacion || 0) - new Date(a?.fecha_creacion || 0);
            });
            paginaActual = 1;
            renderizarCreditos();
        } catch (error) {
            lista.innerHTML = `<div class="empty">${escapar(error.message)}</div>`;
        }
    }

    function renderizarCreditos() {
        const lista = document.getElementById('creditosLista');
        const filtro = String(document.getElementById('buscarCredito').value || '').trim().toLowerCase();
        const filtrados = creditos.filter(credito => `${nombreCliente(credito)} ${credito.documento || ''} ${credito.codigo || ''} ${credito.referencia || ''}`.toLowerCase().includes(filtro));
        const totalPaginas = Math.max(1, Math.ceil(filtrados.length / porPagina));
        paginaActual = Math.min(paginaActual, totalPaginas);
        const inicio = (paginaActual - 1) * porPagina;
        const pagina = filtrados.slice(inicio, inicio + porPagina);
        lista.innerHTML = pagina.length ? pagina.map(credito => {
            const estadoTexto = formatoEstadoCredito(credito);
            const estado = String(credito?.estado || '').trim().toLowerCase();
            const nombreSplit = nombreClienteSplit(credito);
            const creditoId = Number(credito?.id || 0);
            const productos = (credito.detalles || []).slice(0, 2).map(item => `<div style="display:flex;align-items:center;gap:8px;margin-top:9px;font-size:11px;">${imagenProducto(item)}<div class="product-meta-text"><strong>${escapar(item.producto_nombre || 'PRODUCTO')}</strong><span>CÓD: ${escapar(item.producto_codigo || 'N/D')} · REF: ${escapar(item.referencia || 'N/D')}</span></div></div>`).join('');
            const saldoVisible = estado === 'pendiente'
                ? `<div class="amount">SALDO<br>${moneda(credito.saldo)}</div>`
                : `<div class="credit-side"><div class="credit-state-chip paid">PAGADO</div><div class="credits-total-zero">TOTAL: ${moneda(0)}</div></div>`;
            const totalCompacto = estado === 'pendiente' ? moneda(credito.total) : moneda(0);
            const claseEstado = estado === 'pendiente' ? 'pending' : 'paid';
            const badgeEstado = estado === 'pendiente'
                ? '<span class="credit-state-chip pending"><span class="status-dot"></span>PENDIENTE</span>'
                : '<span class="credit-state-chip paid"><span class="status-dot"></span>PAGADO</span>';
            return `<button type="button" class="credit-row ${claseEstado}" data-credito-id="${creditoId}" onclick="mostrarPerfilCredito(${creditoId}, this)"><div class="row-top"><div class="client-name-wrap"><div class="client-name">${escapar(nombreSplit.nombre || 'CLIENTE')}</div><div class="client-lastname">${escapar(nombreSplit.apellido || '')}</div><div class="client-meta">DOC: ${escapar(credito.documento || 'N/D')}</div></div><div class="credit-side"><div class="credit-code">${escapar(String(credito.codigo || 'N/D').toUpperCase())}</div></div></div><div class="row-footer"><div class="credit-total-box"><strong>${totalCompacto}</strong><span>TOTAL</span></div><div class="credit-status-wrap">${badgeEstado}</div></div></button>`;
        }).join('') : '<div class="empty">No hay créditos para mostrar.</div>';
        document.getElementById('creditosPaginacion').innerHTML = filtrados.length > porPagina ? `<button type="button" onclick="cambiarPagina(-1)" ${paginaActual === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button><strong>PÁGINA ${paginaActual} DE ${totalPaginas}</strong><button type="button" onclick="cambiarPagina(1)" ${paginaActual === totalPaginas ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>` : '';
    }

    function cambiarPagina(direccion) {
        paginaActual += direccion;
        renderizarCreditos();
    }

    async function mostrarPerfilCredito(id, elemento) {
        document.querySelectorAll('.credit-row').forEach(row => row.classList.remove('active'));
        if (elemento) elemento.classList.add('active');
        const perfil = document.getElementById('creditoPerfil');
        perfil.innerHTML = '<div class="profile-empty">Cargando perfil...</div>';
        try {
            const creditoId = Number(id || 0);
            const creditoBase = creditos.find(item => Number(item.id || 0) === creditoId);
            const ids = creditoBase && Number(creditoBase.id || 0) > 0
                ? [Number(creditoBase.id)]
                : [creditoId];
            const respuesta = await fetch(`${creditosUrl}?action=detalle&ids=${encodeURIComponent(ids.join(','))}`);
            const resultado = await respuesta.json();
            if (!resultado.success) throw new Error(resultado.message || 'No se pudo cargar el crédito');
            const credito = resultado.data;
            const productosAgrupados = agruparProductosCredito(credito.detalles || []);
            const productos = productosAgrupados.map(item => {
                const precio = Number(item.precio_actual || item.precio_unitario || 0);
                const cantidad = Number(item.cantidad || 0) || 0;
                const total = cantidad * precio;
                const fechaHora = formatearFechaHoraCredito(credito.fecha_creacion || item.fecha_creacion || item.fecha_venta || null);
                return `<tr><td>${imagenProducto(item)}</td><td><div class="fecha-hora-cell"><span class="fecha">${escapar(fechaHora.fecha)}</span><span class="hora">${escapar(fechaHora.hora)}</span></div></td><td class="product-name-cell">${escapar((item.producto_nombre || 'PRODUCTO').toUpperCase())}</td><td>${escapar((item.producto_codigo || 'N/D').toUpperCase())}</td><td>${cantidad}</td><td>${moneda(precio)}</td><td>${moneda(total)}</td></tr>`;
            }).join('');
            const estadoCredito = formatoEstadoCredito(credito);
            const estado = String(credito?.estado || '').trim().toLowerCase();
            const saldoVisible = estado === 'pendiente' ? `<div class="amount">SALDO<br>${moneda(credito.saldo)}</div>` : '<div class="credit-status-badge paid"><i class="fas fa-check-circle"></i> PAGADO</div>';
            const botonPago = estado === 'pendiente' && Number(credito.saldo) > 0 ? `<button type="button" class="pay-button" onclick="abrirModalPagoCredito(${Number(credito.id || 0)})"><i class="fas fa-money-bill-wave"></i> PAGAR CRÉDITO</button>` : '<span class="credit-status-badge paid"><i class="fas fa-check-circle"></i> PAGADO</span>';
            const mostrarProductos = estado === 'pendiente';
            const cuerpoProductos = mostrarProductos ? (productos ? `<table class="profile-table"><thead><tr><th>IMG</th><th>FECHA / HORA</th><th>PRODUCTO</th><th>CÓDIGO</th><th>CANT.</th><th>PRECIO</th><th>TOTAL</th></tr></thead><tbody>${productos}</tbody></table>` : '<div class="credit-empty-state">No hay productos en este crédito.</div>') : '<div class="credit-solo-state">CRÉDITO PAGADO</div>';
            const codigoCredito = (credito.codigo || 'N/D').toUpperCase();
            const nombreSplit = nombreClienteSplit(credito);
            const totalVisible = estado === 'pendiente' ? `<div class="info-box"><small>TOTAL</small><strong>${moneda(credito.total)}</strong></div>` : `<div class="info-box"><small>TOTAL</small><strong>${moneda(0)}</strong></div>`;
            perfil.innerHTML = `
                <div class="profile-header">
                    <div class="profile-header-left">
                        <div class="profile-name">${escapar(nombreSplit.nombre || 'CLIENTE')}</div>
                        <div class="profile-surname">${escapar(nombreSplit.apellido || '')}</div>
                        <div class="profile-document" style="margin-top:8px;">DOCUMENTO: ${escapar(credito.documento || 'N/D')}</div>
                    </div>
                    <div class="profile-header-right">
                        <div class="profile-code">${escapar(codigoCredito)}</div>
                        <button type="button" class="profile-close" onclick="cerrarPerfilCredito()">&times;</button>
                    </div>
                </div>
                <div class="profile-top">
                    <div class="tags">
                        <span class="tag">ORIGEN: CRÉDITO</span>
                        <span class="tag">ESTADO: ${escapar(estadoCredito)}</span>
                    </div>
                    ${saldoVisible}
                </div>
                <div class="info-grid">
                    <div class="info-box"><small>REFERENCIA</small><strong>${escapar(credito.referencia || 'N/D')}</strong></div>
                    ${totalVisible}
                </div>
                <div style="margin:14px 0;">${botonPago}</div>
                ${cuerpoProductos}
            `;
            document.getElementById('perfilCreditoOverlay').style.display = 'block';
            perfil.style.display = 'block';
        } catch (error) {
            perfil.innerHTML = `<div class="profile-empty">${escapar(error.message)}</div>`;
        }
    }

    let creditoPagoId = 0;

    function cerrarPerfilCredito() {
        document.getElementById('creditoPerfil').style.display = 'none';
        document.getElementById('perfilCreditoOverlay').style.display = 'none';
    }

    function abrirModalPagoCredito(id) {
        const creditoId = Number(id || 0);
        const creditoBase = creditos.find(item => Number(item.id || 0) === creditoId);
        creditoPagoId = Number(creditoBase?.id || creditoId || 0);
        document.getElementById('modalPagoCredito').style.display = 'flex';
    }

    function cerrarModalPagoCredito(event) {
        if (!event || event.target === event.currentTarget) document.getElementById('modalPagoCredito').style.display = 'none';
    }

    async function confirmarPagoCredito() {
        const id = Number(creditoPagoId || 0);
        const datos = new FormData();
        datos.append('action', 'pagar');
        const credito = creditos.find(item => Number(item.id || 0) === id);
        const idsPago = Array.isArray(credito?.credit_ids) && credito.credit_ids.length
            ? credito.credit_ids.map(value => Number(value)).filter(value => Number.isFinite(value) && value > 0)
            : (id > 0 ? [id] : []);
        if (!idsPago.length) {
            window.alert('No hay un crédito válido para pagar');
            return;
        }
        datos.append('ids', JSON.stringify(idsPago));
        datos.append('metodo_pago', document.getElementById('metodoPagoCredito').value);
        const respuesta = await fetch(creditosUrl, { method:'POST', body:datos, credentials:'same-origin' });
        let resultado = { success: false, message: 'No se pudo marcar el crédito como pagado' };
        try {
            resultado = await respuesta.json();
        } catch (error) {
            resultado.message = `No se pudo procesar la respuesta del servidor (${respuesta.status || 'error'})`;
        }
        if (!respuesta.ok || !resultado.success) {
            window.alert(resultado.message || 'No se pudo marcar el crédito como pagado');
            return;
        }
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'CRÉDITO PAGADO',
                text: 'El crédito quedó marcado como pagado.',
                timer: 2200,
                showConfirmButton: false,
                timerProgressBar: true
            });
        } else {
            window.alert('CRÉDITO PAGADO: El crédito quedó marcado como pagado.');
        }
        document.getElementById('modalPagoCredito').style.display = 'none';
        await cargarCreditos();
        cerrarPerfilCredito();
    }

    cargarCreditos();
</script>
</body>
</html>
