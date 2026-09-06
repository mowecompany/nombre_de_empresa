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

        .table-wrap { width: 100%; overflow-x: auto; overflow-y: auto; max-height: calc(100vh - 300px); }
        table { width: 100%; min-width: 1200px; border-collapse: separate; border-spacing: 0; margin: 0; box-sizing: border-box; text-transform: uppercase; table-layout: fixed; border-radius: 8px; font-family: var(--font-saira); }
        thead { position: sticky; top: 0; z-index: 5; }
        th, td { padding: 14px 10px; vertical-align: middle; text-align: center; border-bottom: 1px solid #e6e9ee; font-size: .95rem; line-height: 1.4; word-break: break-word; white-space: normal; height: auto; max-height: 100px; overflow: hidden; }
        th { background: white; color: #2f4a5a; font-weight: 600; text-transform: uppercase; border-bottom: 2px solid #2f4a5a; letter-spacing: .5px; box-shadow: 0 2px 4px rgba(47, 74, 90, .08); }
        th i, td i { margin-right: 5px; vertical-align: middle; }
        th:nth-child(1), td:nth-child(1) { width: 18%; }
        th:nth-child(2), td:nth-child(2) { width: 20%; }
        th:nth-child(3), td:nth-child(3) { width: 62%; }
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
                        </tr>
                    </thead>
                    <tbody id="codes-body">
                        <tr><td colspan="3" class="empty-state"><i class="fas fa-spinner fa-spin"></i>Cargando...</td></tr>
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
        let products = [];

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
                codesBody.innerHTML = '<tr><td colspan="3" class="empty-state"><i class="fas fa-box-open"></i>No hay productos que coincidan.</td></tr>';
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
                rows.push(`<tr class="category-row"><td colspan="3"><span class="category-heading"><img class="category-image" src="${categoryImageUrl(categoryImage)}" alt="${escapeHtml(category)}" onerror="this.src='${baseUrl}/favicon.ico'"><i class="fas fa-tag category-icon"></i><span>${escapeHtml(category)}</span></span></td></tr>`);
                grouped[category].sort((first, second) => categorySort.compare(String(first.nombre || ''), String(second.nombre || ''))).forEach(product => {
                    rows.push(`
                        <tr>
                            <td><img class="product-image" src="${imageUrl(product.imagen)}" alt="${escapeHtml(product.nombre || 'Producto')}" onerror="this.src='${baseUrl}/favicon.ico'"></td>
                            <td class="code-value">${escapeHtml(product.codigo || 'SIN CÓDIGO')}</td>
                            <td class="product-name">${escapeHtml(product.nombre || 'Sin nombre')}</td>
                        </tr>
                    `);
                });
            });

            codesBody.innerHTML = rows.join('');
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
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
            } catch (error) {
                codesCount.textContent = 'ERROR AL CARGAR';
                codesBody.innerHTML = '<tr><td colspan="3" class="empty-state"><i class="fas fa-triangle-exclamation"></i>No fue posible cargar los productos.</td></tr>';
            }
        }

        let categoryImages = {};
        codesSearch.addEventListener('input', renderProducts);
        loadProducts();
    </script>
</body>
</html>