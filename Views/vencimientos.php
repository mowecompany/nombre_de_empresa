<?php
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}

require_once ROOT_PATH . '/Config/Config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$baseUrl = rtrim((string)base_url(), '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos vencidos y dañados - <?= htmlspecialchars((string)NOMBRE_EMPRESA, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --navy: #2f4a5a;
            --ink: #243447;
            --muted: #64748b;
            --line: #e6e9ee;
            --page: #f8f9fa;
            --rojo: #d93025;
            --naranja: #e08b00;
            --verde: #1e8e3e;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 80px 15px 40px;
            background-color: var(--page);
            color: var(--ink);
            font-family: 'Saira Condensed', Arial, sans-serif;
            text-transform: uppercase;
        }

        .venc-shell { width: 100%; max-width: 1600px; margin: 0 auto; }

        .venc-title {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin: 0 0 6px; color: var(--navy); font-size: 2.4rem; font-weight: 600; letter-spacing: 2px;
        }

        .venc-sub { text-align: center; color: var(--muted); font-size: 14px; margin-bottom: 24px; }

        .venc-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 22px; }

        .venc-card {
            background: #fff; border: 1px solid var(--line); border-left: 6px solid var(--navy);
            border-radius: 10px; padding: 14px 16px;
        }
        .venc-card strong { display: block; font-size: 2rem; line-height: 1; margin-bottom: 6px; }
        .venc-card span { font-size: 12px; color: var(--muted); font-weight: 700; }
        .venc-card.rojo { border-left-color: var(--rojo); } .venc-card.rojo strong { color: var(--rojo); }
        .venc-card.naranja { border-left-color: var(--naranja); } .venc-card.naranja strong { color: var(--naranja); }
        .venc-card.verde { border-left-color: var(--verde); } .venc-card.verde strong { color: var(--verde); }

        .venc-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: flex-end; margin-bottom: 16px; }
        .venc-toolbar input, .venc-toolbar select {
            padding: 11px 14px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;
            text-transform: uppercase; font-size: 14px; background: #fff; color: var(--ink);
        }
        .venc-toolbar input { flex: 0 1 300px; min-width: 240px; }

        .venc-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .venc-tabs .btn.activo { background: var(--navy); color: #fff; }

        .btn {
            border: none; border-radius: 8px; padding: 11px 16px; font-family: inherit; font-weight: 700;
            font-size: 13px; cursor: pointer; text-transform: uppercase; color: #fff; background: var(--navy);
        }
        .btn.rojo { background: var(--rojo); }
        .btn.ghost { background: #fff; color: var(--navy); border: 1px solid var(--line); }
        .btn:disabled { opacity: .55; cursor: not-allowed; }

        .venc-table-wrap { background: #fff; border: 1px solid var(--line); border-radius: 12px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th {
            position: sticky; top: 0; background: #fff; z-index: 2; padding: 12px 10px; text-align: left;
            color: var(--muted); font-size: 11px; border-bottom: 2px solid var(--line); white-space: nowrap;
        }
        tbody td { padding: 10px; border-bottom: 1px solid #f1f3f5; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        .prod-img { width: 46px; height: 46px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); background: #fff; }

        .pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; }
        .pill.vencido { background: #fdecea; color: var(--rojo); }
        .pill.critico { background: #fff4e0; color: var(--naranja); }
        .pill.proximo { background: #fff9db; color: #9a7500; }
        .pill.vigente { background: #e8f5e9; color: var(--verde); }

        .vacio { padding: 40px 20px; text-align: center; color: var(--muted); }

        @media (max-width: 720px) {
            body { padding-top: 24px; }
            .venc-title { font-size: 1.6rem; }
            thead th, tbody td { padding: 8px 6px; }
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #2f4a5a;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.18);
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 0.95rem;
            font-weight: 700;
            gap: 8px;
            text-transform: none;
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-save:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(11,94,215,0.18);
        }

        .btn-save:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(11,94,215,0.25);
        }
    </style>
    <link rel="stylesheet" href="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/css/skeletons.css">
    <script src="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/js/skeletons.js"></script>
</head>
<body>
    <div class="venc-shell">
        <h1 class="venc-title"><i class="fas fa-calendar-times"></i> PRODUCTOS VENCIDOS Y DAÑADOS</h1>
        <p class="venc-sub">PRODUCTOS VENCIDOS Y PRODUCTOS DAÑADOS REGISTRADOS EN EL INVENTARIO</p>

        <div class="venc-tabs" role="tablist" aria-label="PRODUCTOS VENCIDOS Y DAÑADOS">
            <button type="button" class="btn ghost activo" id="btnVistaVencidos" onclick="cambiarVista('vencidos')">
                <i class="fas fa-calendar-xmark"></i> PRODUCTOS VENCIDOS
            </button>
            <button type="button" class="btn ghost" id="btnVistaDanados" onclick="cambiarVista('danados')">
                <i class="fas fa-triangle-exclamation"></i> PRODUCTOS DAÑADOS
            </button>
        </div>

        <div class="venc-cards" id="tarjetas"></div>

        <div class="venc-toolbar">
            <button class="btn ghost" id="btnRefrescar"><i class="fas fa-sync"></i> ACTUALIZAR</button>
            <button class="btn ghost" id="btnVerArchivados" type="button" onclick="cambiarVista(vistaActiva === 'archivados' ? 'vencidos' : 'archivados')"><i class="fas fa-box-archive"></i> PRODUCTOS ARCHIVADOS</button>
            <input type="text" id="buscador" placeholder="BUSCAR POR PRODUCTO, CÓDIGO O CATEGORÍA" autocomplete="off" style="border-color:#2f4a5a;border-radius:8px;">
            <select id="vencimientosPorPagina" aria-label="Registros por página" style="width:auto;padding:5px 7px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;background:#fff;color:#2f4a5a;"><option>25</option><option selected>50</option><option>100</option><option>200</option></select>
            <div id="vencimientosPaginacion" style="display:inline-flex;align-items:center;gap:6px;"></div>
        </div>

        <div class="venc-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>IMG</th>
                        <th>CÓDIGO</th>
                        <th>PRODUCTO</th>
                        <th>CATEGORÍA</th>
                        <th>CANTIDAD</th>
                        <th id="encabezadoFecha">VENCE</th>
                        <th id="encabezadoDias">DÍAS</th>
                        <th id="encabezadoProveedor">PROVEEDOR</th>
                        <th>ESTADO</th>
                        <th>ACCIÓN</th>
                    </tr>
                </thead>
                <tbody id="cuerpo">
                    <tr><td colspan="10" class="vacio">CARGANDO LOTES...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const baseUrl = <?= json_encode($baseUrl) ?>;
        const CONTROLADOR = baseUrl + '/Controllers/InventarioController.php';
        let lotes = [];
        let resumen = {};
        let danados = [];
        let archivados = [];
        let vencimientosPagina = 0;
        let vencimientosPorPagina = 50;
        let vistaActiva = new URLSearchParams(window.location.search).get('vista') === 'danados'
            ? 'danados'
            : new URLSearchParams(window.location.search).get('vista') === 'archivados'
                ? 'archivados'
            : 'vencidos';

        function escapar(texto) {
            return String(texto ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function coincideBusquedaVencimientos(valores, consulta) {
            const normalizar = (valor) => String(valor || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, ' ')
                .trim()
                .replace(/\s+/g, ' ');
            const texto = normalizar((valores || []).join(' '));
            const busqueda = normalizar(consulta);
            if (!busqueda) return true;
            const tokens = busqueda.split(' ').filter(Boolean);
            return tokens.every(token => texto.includes(token)) || texto.replace(/\s/g, '').includes(tokens.join(''));
        }

        function imagenUrl(archivo) {
            const imagen = String(archivo || '').trim().replace(/\\/g, '/');
            if (!imagen) return baseUrl + '/favicon.ico';
            if (/^(?:https?:|data:|blob:)/i.test(imagen)) return imagen;
            const nombre = imagen.split('/').pop() || '';
            if (!nombre || nombre.toLowerCase() === 'favicon.ico') return baseUrl + '/favicon.ico';
            return baseUrl + '/Assets/images/productos/' + encodeURIComponent(nombre);
        }

        function fechaBonita(fecha) {
            if (!fecha) return 'N/D';
            const partes = String(fecha).slice(0, 10).split('-');
            return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : fecha;
        }

        function textoDias(dias, estado) {
            if (estado === 'vencido') {
                const pasados = Math.abs(Number(dias) || 0);
                return pasados === 0 ? 'VENCE HOY' : `HACE ${pasados} DÍA(S)`;
            }
            const d = Number(dias) || 0;
            if (d === 0) return 'VENCE HOY';
            return `EN ${d} DÍA(S)`;
        }

        function pintarTarjetas() {
            const contenedor = document.getElementById('tarjetas');
            const vigentes = Math.max(0, (Number(resumen.total) || 0) - (Number(resumen.vencidos) || 0) - (Number(resumen.criticos) || 0) - (Number(resumen.proximos) || 0));
            contenedor.innerHTML = `
                <div class="venc-card rojo"><strong>${Number(resumen.vencidos) || 0}</strong><span>YA VENCIDOS</span></div>
                <div class="venc-card naranja"><strong>${Number(resumen.criticos) || 0}</strong><span>CRÍTICOS (${Number(resumen.dias_alerta) || 5} DÍAS O MENOS)</span></div>
                <div class="venc-card"><strong>${Number(resumen.proximos) || 0}</strong><span>PRÓXIMOS (${Number(resumen.dias_aviso) || 7} DÍAS)</span></div>
                <div class="venc-card verde"><strong>${vigentes}</strong><span>VIGENTES</span></div>
            `;
        }

        function pintarTarjetasDanados() {
            const contenedor = document.getElementById('tarjetas');
            contenedor.innerHTML = `
                <div class="venc-card rojo"><strong>${danados.length}</strong><span>PRODUCTOS DAÑADOS REGISTRADOS</span></div>
            `;
        }

        function pintarTarjetasArchivados() {
            const contenedor = document.getElementById('tarjetas');
            contenedor.innerHTML = `
                <div class="venc-card rojo"><strong>${archivados.length}</strong><span>PRODUCTOS VENCIDOS ARCHIVADOS</span></div>
            `;
        }

        function actualizarVista() {
            const esDanados = vistaActiva === 'danados';
            const esArchivados = vistaActiva === 'archivados';
            document.title = 'Productos vencidos y dañados - <?= htmlspecialchars((string)NOMBRE_EMPRESA, ENT_QUOTES, 'UTF-8') ?>';
            document.getElementById('btnVistaVencidos').classList.toggle('activo', !esDanados);
            document.getElementById('btnVistaDanados').classList.toggle('activo', esDanados);
            const botonArchivados = document.getElementById('btnVerArchivados');
            botonArchivados.style.display = esDanados ? 'none' : '';
            botonArchivados.innerHTML = esArchivados
                ? '<i class="fas fa-calendar-xmark"></i> VER PRODUCTOS VENCIDOS'
                : '<i class="fas fa-box-archive"></i> PRODUCTOS ARCHIVADOS';
            document.getElementById('encabezadoFecha').textContent = esDanados ? 'FECHA' : (esArchivados ? 'ARCHIVADO' : 'VENCE');
            document.getElementById('encabezadoDias').textContent = esDanados ? 'REFERENCIA' : (esArchivados ? 'VENCE' : 'DÍAS');
            document.getElementById('encabezadoProveedor').textContent = esDanados ? 'NOTA' : (esArchivados ? 'NOTA' : 'PROVEEDOR');
            document.getElementById('tarjetas').style.display = '';
            if (esDanados) {
                pintarTarjetasDanados();
                pintarTablaDanados();
            } else if (esArchivados) {
                pintarTarjetasArchivados();
                pintarTablaArchivados();
            } else {
                pintarTarjetas();
                pintarTabla();
            }
        }

        function pintarTablaDanados() {
            const cuerpo = document.getElementById('cuerpo');
            const texto = document.getElementById('buscador').value || '';
            const visibles = danados.filter((item) => {
                if (!texto) return true;
                return coincideBusquedaVencimientos([item.producto_nombre, item.codigo, item.codigo_barras, item.categoria_nombre, item.referencia, item.notas], texto);
            });

            if (!visibles.length) {
                cuerpo.innerHTML = '<tr><td colspan="10" class="vacio">NO HAY PRODUCTOS DAÑADOS PARA MOSTRAR</td></tr>';
                return;
            }

            cuerpo.innerHTML = visibles.map((item) => `
                <tr>
                    <td><img class="prod-img" loading="lazy" src="${imagenUrl(item.producto_imagen)}" alt="${escapar(item.producto_nombre)}" onerror="this.src='${baseUrl}/favicon.ico'"></td>
                    <td>${escapar(item.codigo || 'N/D')}</td>
                    <td><strong>${escapar(item.producto_nombre || 'N/D')}</strong></td>
                    <td>${escapar(item.categoria_nombre || 'N/D')}</td>
                    <td>${Number(item.cantidad) || 0}</td>
                    <td>${fechaBonita(item.fecha_salida)}</td>
                    <td>${escapar(item.referencia || 'N/D')}</td>
                    <td>${escapar(item.notas || 'PRODUCTO DAÑADO')}</td>
                    <td><span class="pill vencido">DAÑADO</span></td>
                    <td><span style="color:#94a3b8;">—</span></td>
                </tr>
            `).join('');
        }

        function pintarTablaArchivados() {
            const cuerpo = document.getElementById('cuerpo');
            const texto = document.getElementById('buscador').value || '';
            const visibles = archivados.filter((item) => {
                if (!texto) return true;
                return coincideBusquedaVencimientos([item.producto_nombre, item.producto_codigo, item.codigo_barras, item.categoria_nombre, item.notas], texto);
            });

            if (!visibles.length) {
                cuerpo.innerHTML = '<tr><td colspan="10" class="vacio">NO HAY PRODUCTOS ARCHIVADOS PARA MOSTRAR</td></tr>';
                return;
            }

            cuerpo.innerHTML = visibles.map((item) => `
                <tr>
                    <td><img class="prod-img" loading="lazy" src="${imagenUrl(item.producto_imagen)}" alt="${escapar(item.producto_nombre)}" onerror="this.src='${baseUrl}/favicon.ico'"></td>
                    <td>${escapar(item.producto_codigo || 'N/D')}</td>
                    <td><strong>${escapar(item.producto_nombre || 'N/D')}</strong></td>
                    <td>${escapar(item.categoria_nombre || 'N/D')}</td>
                    <td>${Number(item.cantidad) || 0}</td>
                    <td>${fechaBonita(item.fecha_archivado)}</td>
                    <td>${fechaBonita(item.fecha_vencimiento)}</td>
                    <td>${escapar(item.notas || 'PRODUCTO VENCIDO ARCHIVADO')}</td>
                    <td><span class="pill vencido">ARCHIVADO</span></td>
                    <td><span style="color:#94a3b8;">—</span></td>
                </tr>
            `).join('');
        }

        function cambiarVista(vista) {
            vistaActiva = ['danados', 'archivados'].includes(vista) ? vista : 'vencidos';
            actualizarVista();
        }

        function pintarTabla() {
            if (vistaActiva === 'danados') return pintarTablaDanados();
            if (vistaActiva === 'archivados') return pintarTablaArchivados();
            const cuerpo = document.getElementById('cuerpo');
            const texto = document.getElementById('buscador').value || '';
            const estadoFiltro = 'todos';

            const visibles = lotes.filter((lote) => {
                if (estadoFiltro !== 'todos' && lote.estado !== estadoFiltro) return false;
                if (!texto) return true;
                return coincideBusquedaVencimientos([lote.producto, lote.codigo, lote.codigo_barras, lote.categoria, lote.proveedor], texto);
            });

            if (!visibles.length) {
                cuerpo.innerHTML = '<tr><td colspan="10" class="vacio">NO HAY LOTES CON FECHA DE VENCIMIENTO PARA MOSTRAR</td></tr>';
                return;
            }

            const totalPaginas = Math.max(1, Math.ceil(visibles.length / vencimientosPorPagina));
            vencimientosPagina = Math.min(vencimientosPagina, totalPaginas - 1);
            const paginaVisible = visibles.slice(vencimientosPagina * vencimientosPorPagina, (vencimientosPagina + 1) * vencimientosPorPagina).slice().reverse();
            document.getElementById('vencimientosPaginacion').innerHTML = `<button type="button" class="btn-save inventory-page-prev" title="Página anterior" aria-label="Página anterior" style="padding:4px 7px;min-height:26px;width:28px;font-size:10px;" ${vencimientosPagina === 0 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button><span style="min-width:90px;text-align:center;color:#667085;font-weight:600;font-size:11px;">PÁGINA ${vencimientosPagina + 1} / ${totalPaginas}</span><button type="button" class="btn-save inventory-page-next" title="Página siguiente" aria-label="Página siguiente" style="padding:4px 7px;min-height:26px;width:28px;font-size:10px;" ${vencimientosPagina >= totalPaginas - 1 ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
            cuerpo.innerHTML = paginaVisible.map((lote) => `
                <tr>
                    <td><img class="prod-img" loading="lazy" src="${imagenUrl(lote.imagen)}" alt="${escapar(lote.producto)}" onerror="this.src='${baseUrl}/favicon.ico'"></td>
                    <td>${escapar(lote.codigo || 'N/D')}</td>
                    <td><strong>${escapar(lote.producto)}</strong></td>
                    <td>${escapar(lote.categoria || 'N/D')}</td>
                    <td>${Number(lote.cantidad) || 0}</td>
                    <td>${fechaBonita(lote.fecha_vencimiento)}</td>
                    <td>${textoDias(lote.dias, lote.estado)}</td>
                    <td>${escapar(lote.proveedor || 'N/D')}</td>
                    <td><span class="pill ${escapar(lote.estado)}">${escapar(lote.estado)}</span></td>
                    <td>${lote.estado === 'vencido'
                        ? `<button class="btn rojo" onclick="archivarLote(${Number(lote.entrada_id)}, '${escapar(lote.producto).replace(/'/g, "\\'")}')"><i class="fas fa-box-archive"></i> ARCHIVAR</button>`
                        : '<span style="color:#94a3b8;">—</span>'}</td>
                </tr>
            `).join('');
        }

        async function cargar() {
            const boton = document.getElementById('btnRefrescar');
            boton.disabled = true;
            window.EstrellaSkeleton?.show(document.getElementById('tarjetas'), 'cards', { cards: 4 });
            window.EstrellaSkeleton?.show(document.getElementById('cuerpo'), 'table', { rows: 7 });
            try {
                const [respuestaLotes, respuestaDanados, respuestaArchivados] = await Promise.all([
                    fetch(CONTROLADOR + '?action=lotesPorVencer', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                    fetch(CONTROLADOR + '?action=obtenerSalidas&tipo_salida=dañado', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                    fetch(CONTROLADOR + '?action=lotesVencidosArchivados', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                ]);
                const datos = await respuestaLotes.json();
                const datosDanados = await respuestaDanados.json();
                const datosArchivados = await respuestaArchivados.json();
                if (!datos.success) throw new Error(datos.message || 'No se pudieron cargar los lotes');
                lotes = Array.isArray(datos.data) ? datos.data : [];
                resumen = datos.resumen || {};
                danados = Array.isArray(datosDanados?.data)
                    ? datosDanados.data.filter(item => String(item.tipo_salida || '').trim().toLowerCase() === 'dañado' && !String(item.referencia || '').toUpperCase().startsWith('VENCIDO-'))
                    : [];
                archivados = Array.isArray(datosArchivados?.data) ? datosArchivados.data : [];
                actualizarVista();
            } catch (error) {
                document.getElementById('cuerpo').innerHTML = `<tr><td colspan="10" class="vacio">${escapar(error.message)}</td></tr>`;
            } finally {
                boton.disabled = false;
                window.EstrellaSkeleton?.hide(document.getElementById('tarjetas'), true);
                window.EstrellaSkeleton?.hide(document.getElementById('cuerpo'), true);
            }
        }

        async function archivarLote(entradaId, nombre) {
            const confirmacion = await Swal.fire({
                icon: 'warning',
                title: '¿ARCHIVAR LOTE VENCIDO?',
                html: `EL LOTE DE <strong>${escapar(nombre)}</strong> SE DESCONTARÁ DEL INVENTARIO Y QUEDARÁ REGISTRADO EN PRODUCTOS VENCIDOS.`,
                showCancelButton: true,
                confirmButtonText: 'SÍ, ARCHIVAR',
                cancelButtonText: 'CANCELAR',
                confirmButtonColor: '#d93025'
            });
            if (!confirmacion.isConfirmed) return;

            const formData = new FormData();
            formData.append('action', 'archivarLoteVencido');
            formData.append('entrada_id', String(entradaId));
            try {
                const respuesta = await fetch(CONTROLADOR, { method: 'POST', body: formData });
                const datos = await respuesta.json();
                if (!datos.success) throw new Error(datos.message || 'No se pudo archivar el lote');
                await Swal.fire({ icon: 'success', title: 'LOTE ARCHIVADO', text: datos.message, timer: 2000, showConfirmButton: false });
                cargar();
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'ERROR', text: error.message });
            }
        }

        async function archivarTodos() {
            const vencidos = lotes.filter((lote) => lote.estado === 'vencido').length;
            if (!vencidos) {
                Swal.fire({ icon: 'info', title: 'SIN LOTES VENCIDOS', text: 'No hay productos vencidos para archivar.' });
                return;
            }
            const confirmacion = await Swal.fire({
                icon: 'warning',
                title: `¿ARCHIVAR ${vencidos} LOTE(S) VENCIDO(S)?`,
                text: 'Todos se descontarán del inventario y quedarán registrados en productos vencidos.',
                showCancelButton: true,
                confirmButtonText: 'SÍ, ARCHIVAR TODOS',
                cancelButtonText: 'CANCELAR',
                confirmButtonColor: '#d93025'
            });
            if (!confirmacion.isConfirmed) return;

            const formData = new FormData();
            formData.append('action', 'archivarTodosVencidos');
            try {
                const respuesta = await fetch(CONTROLADOR, { method: 'POST', body: formData });
                const datos = await respuesta.json();
                if (!datos.success) throw new Error(datos.message || 'No se pudieron archivar los lotes');
                await Swal.fire({ icon: 'success', title: 'LISTO', text: datos.message, timer: 2200, showConfirmButton: false });
                cargar();
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'ERROR', text: error.message });
            }
        }

        document.getElementById('buscador').addEventListener('input', () => { vencimientosPagina = 0; pintarTabla(); });
        document.getElementById('vencimientosPorPagina').addEventListener('change', (event) => {
            const tamanoAnterior = vencimientosPorPagina;
            const indiceLoteAncla = vencimientosPagina * tamanoAnterior;
            vencimientosPorPagina = Number(event.target.value) || 50;
            vencimientosPagina = Math.floor(indiceLoteAncla / vencimientosPorPagina);
            pintarTabla();
        });
        document.getElementById('vencimientosPaginacion').addEventListener('click', (event) => {
            if (event.target.closest('.inventory-page-prev')) vencimientosPagina = Math.max(0, vencimientosPagina - 1);
            if (event.target.closest('.inventory-page-next')) vencimientosPagina += 1;
            pintarTabla();
        });
        document.getElementById('btnRefrescar').addEventListener('click', cargar);
        cargar();
    </script>
</body>
</html>
