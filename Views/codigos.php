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
            justify-content: space-between;
            gap: 16px;
            padding: 0 0 16px;
            border-bottom: 1px solid var(--line);
        }

        .count { color: var(--muted); font-size: 14px; font-weight: 700; }

        .search-box {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            width: min(100%, 330px);
            padding: 10px 13px;
            border: 1px solid #c9dce9;
            border-radius: 10px;
            background: #fbfdff;
            color: var(--muted);
        }

        .search-box input { order: 1; width: 100%; border: 0; outline: 0; color: var(--ink); font: inherit; text-transform: uppercase; }
        .search-box input::placeholder { text-transform: uppercase; }
        .search-box i { order: 2; color: #2f4a5a; }

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
</head>
<body>
    <main class="codes-shell">
        <header class="codes-header">
            <div>
                <h1 class="catalog-title"><i class="fas fa-layer-group"></i> CATÁLOGO DE PRODUCTOS</h1>
                <p class="code-title"><i class="fas fa-barcode"></i> CÓDIGO</p>
                <p class="codes-subtitle">Consulta rápidamente el código asignado a cada producto.</p>
            </div>
        </header>

        <section class="codes-panel" aria-labelledby="codes-title">
            <div class="toolbar">
                <span id="codes-count" class="count">CARGANDO PRODUCTOS...</span>
                <label class="search-box" for="codes-search">
                    <i class="fas fa-search"></i>
                    <input id="codes-search" type="search" placeholder="Buscar por nombre o código" autocomplete="off">
                </label>
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
                        <tr><td colspan="5" class="empty-state"><i class="fas fa-spinner fa-spin"></i>Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const baseUrl = <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const codesBody = document.getElementById('codes-body');
        const codesCount = document.getElementById('codes-count');
        const codesSearch = document.getElementById('codes-search');
        const codesTableWrap = document.querySelector('.table-wrap');
        const catalogScrollStorageKey = 'catalogo-productos-scroll-position';
        let products = [];

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
            const query = codesSearch.value.trim().toLowerCase();
            const visible = products.filter(product => {
                const name = String(product.nombre || '').toLowerCase();
                const code = String(product.codigo || '').toLowerCase();
                return !query || name.includes(query) || code.includes(query);
            });

            codesCount.textContent = `${visible.length} PRODUCTO${visible.length === 1 ? '' : 'S'}`;
            if (!visible.length) {
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
                rows.push(`<tr class="category-row"><td colspan="5"><span class="category-heading"><img class="category-image" src="${categoryImageUrl(categoryImage)}" alt="${escapeHtml(category)}" onerror="this.src='${baseUrl}/favicon.ico'"><i class="fas fa-tag category-icon"></i><span>${escapeHtml(category)}</span></span></td></tr>`);
                grouped[category].sort((first, second) => categorySort.compare(String(first.nombre || ''), String(second.nombre || ''))).forEach(product => {
                    const productIndex = products.indexOf(product);
                    rows.push(`
                        <tr>
                            <td><img class="product-image" src="${imageUrl(product.imagen)}" alt="${escapeHtml(product.nombre || 'Producto')}" onerror="this.src='${baseUrl}/favicon.ico'"></td>
                            <td class="code-value">${escapeHtml(product.codigo || 'SIN CÓDIGO')}</td>
                            <td class="product-name">${escapeHtml(product.nombre || 'Sin nombre')}</td>
                            <td class="product-price">${formatPrice(product.precio)}</td>
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
            const price = Number(value);
            if (!Number.isFinite(price)) return 'COP $0';
            return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(price);
        }

        function printProduct(product) {
            if (!product) return;
            const name = escapeHtml(product.nombre || 'Sin nombre');
            const code = escapeHtml(product.codigo || 'SIN CÓDIGO');
            const ventaPorKilo = [1, '1', true, 'true', 'si', 'sí'].includes(product.venta_por_kilo);
            const price = escapeHtml(formatPrice(product.precio));
            const priceUnit = ventaPorKilo ? 'X/KG' : 'C/U';
            const title = `Etiqueta ${product.codigo || product.nombre || 'producto'}`;
            const html = `<!doctype html><html lang="es"><head><meta charset="UTF-8"><title>${escapeHtml(title)}</title><style>
                @page{size:58mm auto;margin:0}*{box-sizing:border-box}html,body{margin:0 auto;width:58mm;height:auto;min-height:0;background:#fff}body{font-family:Arial,sans-serif;color:#17212b}.label{position:relative;width:58mm;height:auto;min-height:0;margin:0 auto;padding:1mm 0 0;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:1.5mm;text-align:center}.label-name{width:58mm;margin:14mm 0 0;padding:0 1mm;font-size:22px;font-weight:900;line-height:1.08;text-align:center;text-transform:uppercase}.label-price{width:58mm;margin:1mm 0 0;color:#167348;font-size:36px;font-weight:900;line-height:1.05;text-align:center}.label-unit{width:58mm;margin:0;color:#167348;font-size:16px;font-weight:900;line-height:1;text-align:center}.label-code{position:absolute;top:4mm;right:1.5mm;max-width:48mm;font-size:21px;font-weight:800;letter-spacing:.5px;text-align:right}.label-footer{width:58mm;margin-top:1mm;padding:4px 0 0;display:flex;justify-content:center;align-items:center;gap:3px;flex-wrap:nowrap;background:#fff;color:#0b1f3a;font-size:8px;font-weight:700;line-height:1;text-align:center;white-space:nowrap;border-top:1px solid #d1d5db}.label-footer span{display:inline-flex;align-items:center;justify-content:center;gap:3px;line-height:1;white-space:nowrap}.label-footer img{width:12px;height:12px;object-fit:contain;display:inline-flex;vertical-align:middle}.label-footer-wordmark{display:inline-flex;align-items:center;justify-content:center;gap:3px;font-weight:800;letter-spacing:.02em}@media print{html,body,.label{height:auto;min-height:0}}
            </style></head><body><main class="label"><div class="label-code">${code}</div><div class="label-name">${name}</div><div class="label-price">${price}</div><div class="label-unit">${priceUnit}</div><footer class="label-footer"><span>&copy; ${new Date().getFullYear()}</span><span class="label-footer-wordmark"><img src="${baseUrl}/favicon.ico" alt="Favicon"><span>OWE COMPANY</span></span><span>TODOS LOS DERECHOS RESERVADOS</span></footer></main><script>window.onload=function(){window.print();};<\/script></body></html>`;

            if (typeof window.electronAPI?.printHtml === 'function') {
                window.electronAPI.printHtml({ html, title, preview: true });
                return;
            }

            const printWindow = window.open('', '_blank', 'toolbar=0,menubar=0,scrollbars=1,resizable=1,width=520,height=460');
            if (!printWindow) {
                window.alert('Permite las ventanas emergentes para imprimir la etiqueta.');
                return;
            }
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
        }

        async function loadProducts() {
            try {
                const [productsResponse, categoriesResponse] = await Promise.all([
                    fetch(`${baseUrl}/Controllers/ProductoController.php?action=getAll`),
                    fetch(`${baseUrl}/Controllers/CategoriaController.php?action=getAll`)
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
                codesCount.textContent = 'ERROR AL CARGAR';
                codesBody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-triangle-exclamation"></i>No fue posible cargar los productos.</td></tr>';
            }
        }

        let categoryImages = {};
        codesSearch.addEventListener('input', renderProducts);
        codesTableWrap?.addEventListener('scroll', saveCatalogScrollPosition, { passive: true });
        window.addEventListener('scroll', saveCatalogScrollPosition, { passive: true });
        window.addEventListener('beforeunload', saveCatalogScrollPosition);
        codesBody.addEventListener('click', event => {
            const button = event.target.closest('.print-product-button');
            if (!button) return;
            printProduct(products[Number(button.dataset.productIndex)]);
        });
        loadProducts();
    </script>
</body>
</html>