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
    <title>Códigos - <?= htmlspecialchars((string)NOMBRE_EMPRESA, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #203864;
            --secondary-blue: #3591CA;
            --navy: #2f4a5a;
            --blue: #3591ca;
            --ink: #243447;
            --muted: #64748b;
            --line: #e6e9ee;
            --surface: #ffffff;
            --page: #f8f9fa;
            --accent: #2f4a5a;
            --font-saira: 'Saira Condensed', sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            overflow: auto;
            padding: 80px 15px 0;
            background-color: var(--page);
            color: var(--ink);
            font-family: var(--font-saira);
            text-transform: uppercase;
        }

        .codes-shell { width: 100%; max-width: 1600px; margin: 0 auto; }

        .codes-header {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100px;
            margin-bottom: 2rem;
        }

        .catalog-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0 0 7px;
            color: #2f4a5a;
            font-size: 2.5rem;
            font-weight: 600;
            letter-spacing: 2px;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .catalog-title i { color: #2f4a5a; font-size: .8em; }

        .code-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            color: #2f4a5a;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .code-title i { color: #2f4a5a; font-size: 16px; }

        .codes-subtitle { margin: 9px 0 0; color: #64748b; font-size: 14px; }

        .codes-panel {
            overflow: hidden;
            border: 1px solid rgba(32, 56, 100, .08);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: 0 4px 12px rgba(47, 74, 90, .08), 0 0 0 1px rgba(47, 74, 90, .04);
            padding: 20px;
            margin: 60px auto;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            padding: 0 0 16px;
            border-bottom: 1px solid var(--line);
        }

        .count { color: var(--muted); font-size: 14px; font-weight: 700; }

        .search-box {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex: 0 1 300px;
            width: min(100%, 300px);
            min-height: 42px;
            padding: 0;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            color: var(--muted);
        }

        .search-box input { order: 1; width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; padding: 11px 12px; color: var(--ink); font: inherit; text-transform: uppercase; }
        .search-box input::placeholder { text-transform: uppercase; }
        .search-box i { order: 2; display: flex; align-items: center; justify-content: center; width: 42px; min-width: 42px; height: 100%; border-left: 1px solid #e6e9ee; background: #f8fafc; color: #667085; }

        .btn-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-blue);
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

        .table-wrap { width: 100%; overflow-x: auto; overflow-y: visible; max-height: none; }
        table { width: 100%; min-width: 1200px; border-collapse: separate; border-spacing: 0; margin: 0; box-sizing: border-box; text-transform: uppercase; table-layout: fixed; border-radius: 8px; font-family: var(--font-saira); }
        thead { position: sticky; top: 0; z-index: 5; }
        th, td { padding: 14px 10px; vertical-align: middle; text-align: center; border-bottom: 1px solid #e6e9ee; font-size: .95rem; line-height: 1.4; word-break: break-word; white-space: normal; height: auto; max-height: 100px; overflow: hidden; }
        th { background: white; color: #2f4a5a; font-weight: 600; text-transform: uppercase; border-bottom: 2px solid #2f4a5a; letter-spacing: .5px; box-shadow: 0 2px 4px rgba(47, 74, 90, .08); }
        th i, td i { margin-right: 5px; vertical-align: middle; }
        th:nth-child(1), td:nth-child(1) { width: 18%; }
        th:nth-child(2), td:nth-child(2) { width: 16%; }
        th:nth-child(3), td:nth-child(3) { width: 35%; }
        th:nth-child(4), td:nth-child(4) { width: 15%; }
        th:nth-child(5), td:nth-child(5) { width: 16%; }
        tbody tr:last-child td { border-bottom: 0; }
        tr:hover { background-color: rgba(47, 74, 90, .04); transition: background-color .2s ease; }

        .category-row td {
            padding: 8px 10px;
            border-bottom: 1px solid #cfe0eb;
            background: linear-gradient(90deg, #e7f2f8, #f5f9fc);
            color: #2f4a5a;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
            text-align: left;
        }

        .category-heading { display: inline-flex; align-items: center; justify-content: flex-start; gap: 11px; }

        .category-image {
            width: 96px;
            height: 52px;
            object-fit: cover;
            border: 2px solid #fff;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 3px 8px rgba(32, 56, 100, .12);
        }

        .category-icon { color: #2f4a5a; }

        .product-image {
            width: 80px;
            height: 80px;
            display: block;
            margin: 0 auto;
            object-fit: contain;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #f8fafc;
        }

        .code-value { color: #2f4a5a; font-size: .95rem; font-weight: 800; letter-spacing: .02em; text-align: center; }
        .product-name { font-size: 1rem; font-weight: 700; text-align: center; }
        .product-price { color: #1f7a52; font-size: 1rem; font-weight: 800; text-align: center; white-space: nowrap; }
        .presentation-switcher { display:flex; flex-wrap:wrap; justify-content:center; gap:6px; margin-top:8px; }
        .presentation-option { min-width:78px; min-height:32px; padding:6px 10px; border:2px solid #bfdbfe; border-radius:6px; background:#eff6ff; color:#1d4ed8; cursor:pointer; font-size:11px; font-weight:800; text-transform:uppercase; transition:background .15s ease, border-color .15s ease, box-shadow .15s ease; }
        .presentation-option:hover { border-color:#2563eb; background:#dbeafe; }
        .presentation-option.is-selected { border-color:#1e3a8a; background:#2563eb; color:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.22); }
        .print-product-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 40px;
            min-width: 42px;
            margin: 0 auto;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: #2f4a5a;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            transition: background-color .2s ease, transform .2s ease;
        }
        .print-product-button:hover { background: #203864; transform: translateY(-1px); }
        .print-product-button:focus-visible { outline: 3px solid rgba(53, 145, 202, .35); outline-offset: 2px; }
        .empty-state { padding: 54px 20px; color: var(--muted); text-align: center; }
        .empty-state i { display: block; margin-bottom: 12px; color: #b7c7d5; font-size: 32px; }

        @media (max-width: 680px) {
            body { padding: 20px 14px; }
            .codes-header, .toolbar { align-items: stretch; flex-direction: column; }
            .search-box { width: 100%; justify-content: center; }
            th, td { padding: 13px 15px; }
        }
    </style>
    <link rel="stylesheet" href="<?= base_url() ?>/Assets/css/responsive.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/css/skeletons.css">
    <script src="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/js/skeletons.js"></script>
    <script src="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/js/redondeo-precio-venta.js"></script>
</head>
<body class="page-codigos">
    <main class="codes-shell">
        <header class="codes-header">
            <div>
                <h1 class="catalog-title"><i class="fas fa-layer-group"></i> CATÁLOGO DE PRODUCTOS</h1>
                <p class="code-title"><i class="fas fa-barcode"></i> CÓDIGO</p>
                <p class="codes-subtitle">Consulta rápidamente el código asignado a cada producto.</p>
            </div>
        </header>

        <section class="codes-panel" aria-labelledby="codes-title">
            <div class="toolbar" style="align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;">
                <input type="search" id="codes-search" placeholder="BUSCAR CÓDIGO..." style="width:min(100%,260px);padding:6px 9px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;">
                <select id="codes-page-size" aria-label="Registros por página" style="width:auto;padding:5px 7px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;background:#fff;color:#2f4a5a;"><option>25</option><option selected>50</option><option>100</option><option>200</option></select>
                <div id="codes-pagination" style="display:flex;gap:6px;"></div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Código</th>
                            <th>Nombre del producto</th>
                            <th>Precio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="codes-body">
                        <tr><td colspan="5" class="empty-state">CARGANDO PRODUCTOS...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const baseUrl = <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const codesBody = document.getElementById('codes-body');
        const codesSearch = document.getElementById('codes-search');
        const codesTableWrap = document.querySelector('.table-wrap');
        const catalogScrollStorageKey = 'catalogo-productos-scroll-position';
        const phpSessionId = (new URLSearchParams(window.location.search)).get('PHPSESSID') || '';
        let products = [];
        let codesPage = 0;
        let codesPageSize = 50;

        function preservarSesionEnUrl(url) {
            if (!phpSessionId) return url;
            const destino = new URL(url, window.location.href);
            if (destino.origin === window.location.origin && destino.pathname.includes('/Controllers/')) {
                destino.searchParams.set('PHPSESSID', phpSessionId);
            }
            return destino.toString();
        }

        function saveCatalogScrollPosition() {
            if (!codesTableWrap) return;
            sessionStorage.setItem(catalogScrollStorageKey, JSON.stringify({
                top: window.scrollY,
                left: codesTableWrap.scrollLeft
            }));
        }

        function restoreCatalogScrollPosition() {
            if (!codesTableWrap) return;
            try {
                const savedPosition = JSON.parse(sessionStorage.getItem(catalogScrollStorageKey) || 'null');
                if (!savedPosition) return;
                requestAnimationFrame(() => {
                    window.scrollTo(0, Number(savedPosition.top) || 0);
                    codesTableWrap.scrollLeft = Number(savedPosition.left) || 0;
                });
            } catch (error) {
                sessionStorage.removeItem(catalogScrollStorageKey);
            }
        }

        function normalizarBusquedaProducto(valor) {
            return String(valor || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, ' ')
                .trim()
                .replace(/\s+/g, ' ');
        }

        function coincideBusquedaProducto(valor, consulta) {
            const texto = normalizarBusquedaProducto(valor);
            const busqueda = normalizarBusquedaProducto(consulta);
            if (!busqueda) return true;
            const tokens = busqueda.split(' ').filter(Boolean);
            return tokens.every(token => texto.includes(token)) || texto.replace(/\s/g, '').includes(tokens.join(''));
        }

        function imageUrl(image) {
            const value = String(image || '').trim();
            if (!value || value === 'favicon.ico') return `${baseUrl}/favicon.ico`;
            const fileName = value.split('/').pop();
            return `${baseUrl}/Assets/images/productos/${encodeURIComponent(fileName)}`;
        }

        function categoryImageUrl(image) {
            const value = String(image || '').trim();
            if (!value || value === 'favicon.ico') return `${baseUrl}/favicon.ico`;
            const fileName = value.split('/').pop();
            return `${baseUrl}/Assets/images/categorias/${encodeURIComponent(fileName)}`;
        }

        function renderProducts() {
            const query = codesSearch.value;
            const visibleAll = products.filter(product => {
                const textoProducto = `${product.id || ''} ${product.nombre || ''} ${product.codigo || ''} ${product.codigo_barras || ''} ${product.categoria_nombre || ''}`;
                return coincideBusquedaProducto(textoProducto, query);
            });
            const totalPaginas = Math.max(1, Math.ceil(visibleAll.length / codesPageSize));
            codesPage = Math.min(codesPage, totalPaginas - 1);
            const visible = visibleAll.slice(codesPage * codesPageSize, (codesPage + 1) * codesPageSize);

            // Generar paginador dinámico
            const paginationDiv = document.getElementById('codes-pagination');
            if (paginationDiv) {
                paginationDiv.innerHTML = `
                    <button type="button" class="btn-save inventory-page-prev" title="Página anterior" aria-label="Página anterior" style="padding:4px 7px;min-height:26px;width:28px;font-size:10px;" ${codesPage === 0 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>
                    <span style="min-width:90px;text-align:center;color:#667085;font-weight:600;font-size:11px;">PÁGINA ${totalPaginas - codesPage} / ${totalPaginas}</span>
                    <button type="button" class="btn-save inventory-page-next" title="Página siguiente" aria-label="Página siguiente" style="padding:4px 7px;min-height:26px;width:28px;font-size:10px;" ${(codesPage + 1) * codesPageSize >= visibleAll.length ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>
                `;
            }
            if (!visibleAll.length) {
                codesBody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-box-open"></i>No hay productos que coincidan.</td></tr>';
                return;
            }

            const grouped = visible.reduce((groups, product) => {
                const category = String(product.categoria_nombre || 'SIN CATEGORÍA').trim() || 'SIN CATEGORÍA';
                if (!groups[category]) groups[category] = [];
                groups[category].push(product);
                return groups;
            }, {});
            const categorySort = new Intl.Collator('es', { sensitivity: 'base', numeric: true });
            const rows = [];

            Object.keys(grouped).sort(categorySort.compare).forEach(category => {
                const categoryKey = category.toLocaleLowerCase('es');
                const categoryImage = categoryImages[categoryKey] || '';
                rows.push(`<tr class="category-row"><td colspan="5"><span class="category-heading"><img class="category-image" loading="lazy" decoding="async" src="${categoryImageUrl(categoryImage)}" alt="${escapeHtml(category)}" onerror="this.src='${baseUrl}/favicon.ico'"><i class="fas fa-tag category-icon"></i><span>${escapeHtml(category)}</span></span></td></tr>`);
                grouped[category].sort((first, second) => categorySort.compare(String(first.nombre || ''), String(second.nombre || ''))).forEach(product => {
                    const productIndex = products.indexOf(product);
                    const presentaciones = Array.isArray(product.presentaciones) ? product.presentaciones : [];
                    const selectedId = Number(product.selectedPresentationId || presentaciones[0]?.id || 0);
                    const selected = presentaciones.find(presentation => Number(presentation.id) === selectedId) || null;
                    const precioVisible = selected ? selected.precio_venta : product.precio;
                    const presentationButtons = presentaciones.length > 1
                        ? `<div class="presentation-switcher" role="group" aria-label="Presentaciones de ${escapeHtml(product.nombre || 'producto')}">${presentaciones.map(presentation => `<button type="button" class="presentation-option${Number(presentation.id) === selectedId ? ' is-selected' : ''}" data-product-index="${productIndex}" data-presentation-id="${Number(presentation.id)}" aria-pressed="${Number(presentation.id) === selectedId ? 'true' : 'false'}">${escapeHtml(String(presentation.nombre || '').toUpperCase())}${Number(presentation.id) === selectedId ? ' ✓' : ''}</button>`).join('')}</div>`
                        : '';
                    rows.push(`
                        <tr>
                            <td><img class="product-image" loading="lazy" decoding="async" src="${imageUrl(product.imagen)}" alt="${escapeHtml(product.nombre || 'Producto')}" onerror="this.src='${baseUrl}/favicon.ico'"></td>
                            <td class="code-value">${escapeHtml(product.codigo || 'SIN CÓDIGO')}</td>
                            <td class="product-name">${escapeHtml(product.nombre || 'Sin nombre')}${presentationButtons}</td>
                            <td class="product-price">${formatPrice(precioVisible)}</td>
                            <td><button type="button" class="print-product-button" data-product-index="${productIndex}" title="Imprimir etiqueta" aria-label="Imprimir etiqueta"><i class="fas fa-print"></i></button></td>
                        </tr>
                    `);
                });
            });

            codesBody.innerHTML = rows.join('');
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
        }

        function formatPrice(value) {
            const raw = Number(value);
            if (!Number.isFinite(raw)) return 'COP $0';
            const price = typeof redondearPrecioVenta === 'function' ? redondearPrecioVenta(raw) : raw;
            return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(price);
        }

        function printHtmlInPage(html) {
            const printFrame = document.createElement('iframe');
            printFrame.setAttribute('title', 'Vista de impresión');
            printFrame.style.position = 'fixed';
            printFrame.style.right = '0';
            printFrame.style.bottom = '0';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = '0';
            printFrame.style.visibility = 'hidden';
            document.body.appendChild(printFrame);
            printFrame.onload = () => {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
                setTimeout(() => printFrame.remove(), 1000);
            };
            const frameDocument = printFrame.contentDocument || printFrame.contentWindow.document;
            frameDocument.open();
            frameDocument.write(html);
            frameDocument.close();
        }

        function printProduct(product) {
            if (!product) return;
            const presentaciones = Array.isArray(product.presentaciones) ? product.presentaciones : [];
            const selectedId = Number(product.selectedPresentationId || presentaciones[0]?.id || 0);
            const selected = presentaciones.find(presentation => Number(presentation.id) === selectedId) || null;
            const name = escapeHtml(selected ? `${product.nombre || 'Sin nombre'} · ${selected.nombre}` : (product.nombre || 'Sin nombre'));
            const code = escapeHtml(String(product.codigo || 'SIN CÓDIGO').trim());
            const ventaPorKilo = [1, '1', true, 'true', 'si', 'sí'].includes(product.venta_por_kilo);
            const price = escapeHtml(formatPrice(selected ? selected.precio_venta : product.precio));
            const nombrePresentacion = String(selected?.nombre || '').trim().toUpperCase();
            const esUnidad = ['UNIDAD', 'UNIDADES', 'UND', 'U'].includes(nombrePresentacion);
            const esPaquete = ['PAQUETE', 'PAQUETES', 'PK', 'P'].includes(nombrePresentacion);
            const priceUnit = selected ? (esUnidad ? 'C/U' : esPaquete ? 'X/PAQUETE' : nombrePresentacion) : (ventaPorKilo ? 'X/KG' : 'C/U');
            const title = `Etiqueta ${product.codigo || product.nombre || 'producto'}`;
            const html = `<!doctype html><html lang="es"><head><meta charset="UTF-8"><title>${escapeHtml(title)}</title><style>
                @page{size:58mm auto;margin:0}*{box-sizing:border-box}html,body{margin:0 auto;width:58mm;height:auto;min-height:0;background:#fff}body{font-family:Arial,sans-serif;color:#000;text-shadow:none;-webkit-text-stroke:0}.label{position:relative;width:58mm;height:auto;min-height:0;margin:0 auto;padding:1mm 0 0;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:1.5mm;text-align:center;color:#000}.label-top{display:flex;align-items:center;justify-content:space-between;width:58mm;padding:0 1.5mm}.label-company{width:35mm;font-family:Arial,sans-serif;font-size:11px;font-style:normal;font-weight:800;line-height:1.1;letter-spacing:0;text-align:center;white-space:normal;color:#000}.label-name{width:52mm;margin:8mm 0 0;padding:0;font-size:clamp(12px,3.8vw,23px);font-weight:900;line-height:1.08;text-align:center;text-transform:uppercase;color:#000;letter-spacing:.2px;overflow-wrap:anywhere;word-break:break-word}.label-price{width:58mm;margin:2mm 0 0;padding:0;color:#000;font-size:34px;font-weight:900;line-height:1;text-align:center}.label-unit{width:58mm;margin:0;color:#000;font-size:15px;font-weight:900;line-height:1;text-align:center}.label-code{max-width:20mm;font-family:Arial,sans-serif;font-size:18px;font-style:normal;font-weight:800;letter-spacing:.5px;text-align:center;white-space:nowrap;color:#000}footer{width:100%;margin-top:1mm;background:#fff;color:#000;padding:6px 0 0;display:flex;justify-content:center;align-items:center;gap:5px;flex-wrap:wrap;font-size:9px;font-weight:800;text-align:center;box-sizing:border-box;border-top:1px solid #000}footer span{display:inline-flex;align-items:center;justify-content:center;gap:4px;line-height:1;color:#000;font-weight:800}footer .footer-wordmark{display:inline-flex;align-items:center;justify-content:center;gap:4px;color:#000;font-weight:900}footer .footer-brand-logo{width:28px;height:28px;object-fit:contain;display:inline-flex;vertical-align:middle;filter:brightness(0) contrast(1.5)}@media print{html,body,.label{height:auto;min-height:0}}
            </style></head><body><main class="label"><div class="label-top"><div class="label-company">AUTOSERVICIO<br>MI ESTRELLA</div><div class="label-code">${code}</div></div><div class="label-name">${name}</div><div class="label-price">${price}</div><div class="label-unit">${priceUnit}</div><footer><span>&copy; ${new Date().getFullYear()}</span><span class="footer-wordmark"><img src="${baseUrl + '/favicon.ico'}" alt="Favicon" class="footer-brand-logo"><span>OWE COMPANY</span></span><span>TODOS LOS DERECHOS RESERVADOS</span></footer></main><script>window.onload=function(){window.print();};<\/script></body></html>`;

            if (typeof window.electronAPI?.printHtml === 'function') {
                Promise.resolve(window.electronAPI.printHtml({ html, title, preview: true }))
                    .catch(() => printHtmlInPage(html));
                return;
            }

            printHtmlInPage(html);
        }

        async function loadProducts() {
            window.EstrellaSkeleton?.show(codesBody, 'table', { rows: 8 });
            try {
                const [productsResponse, categoriesResponse] = await Promise.all([
                    fetch(preservarSesionEnUrl(`${baseUrl}/Controllers/ProductoController.php?action=getAll`)),
                    fetch(preservarSesionEnUrl(`${baseUrl}/Controllers/CategoriaController.php?action=getAll`))
                ]);
                const productsData = await productsResponse.json();
                const categoriesData = await categoriesResponse.json();
                if (!productsData.success || !Array.isArray(productsData.data)) throw new Error('Respuesta inválida');
                products = productsData.data;
                categoryImages = {};
                if (categoriesData.success && Array.isArray(categoriesData.data)) {
                    categoriesData.data.forEach(category => {
                        const categoryName = String(category.nombre || '').trim().toLocaleLowerCase('es');
                        if (categoryName) categoryImages[categoryName] = category.imagen || '';
                    });
                }
                renderProducts();
                restoreCatalogScrollPosition();
            } catch (error) {
                codesBody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-triangle-exclamation"></i>No fue posible cargar los productos.</td></tr>';
            } finally {
                window.EstrellaSkeleton?.hide(codesBody, true);
            }
        }

        codesSearch.addEventListener('input', () => {
            codesPage = 0;
            renderProducts();
        });
        document.getElementById('codes-page-size').addEventListener('change', (event) => {
            const tamanoAnterior = codesPageSize;
            const indiceCodeAncla = codesPage * tamanoAnterior;
            codesPageSize = Number(event.target.value) || 50;
            codesPage = Math.floor(indiceCodeAncla / codesPageSize);
            renderProducts();
        });
        // Event listeners para paginador dinámico
        document.addEventListener('click', (e) => {
            if (e.target.closest('.inventory-page-prev')) {
                if (codesPage === 0) return;
                codesPage -= 1;
                renderProducts();
            }
            if (e.target.closest('.inventory-page-next')) {
                const total = Math.ceil(products.filter(product => coincideBusquedaProducto(`${product.id || ''} ${product.nombre || ''} ${product.codigo || ''} ${product.codigo_barras || ''} ${product.categoria_nombre || ''}`, codesSearch.value)).length / codesPageSize);
                if (codesPage >= total - 1) return;
                codesPage += 1;
                renderProducts();
            }
        });
        codesTableWrap?.addEventListener('scroll', saveCatalogScrollPosition, { passive: true });
        window.addEventListener('scroll', saveCatalogScrollPosition, { passive: true });
        window.addEventListener('beforeunload', saveCatalogScrollPosition);
        codesBody.addEventListener('click', event => {
            const presentationButton = event.target.closest('.presentation-option');
            if (presentationButton) {
                const product = products[Number(presentationButton.dataset.productIndex)];
                if (product) {
                    product.selectedPresentationId = Number(presentationButton.dataset.presentationId || 0);
                    renderProducts();
                }
                return;
            }
            const button = event.target.closest('.print-product-button');
            if (!button) return;
            printProduct(products[Number(button.dataset.productIndex)]);
        });
        loadProducts();
    </script>
</body>
</html>