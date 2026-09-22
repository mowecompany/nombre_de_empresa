(function () {
    if (window.EstrellaSkeleton) return;

    const states = new WeakMap();
    const nativeFetch = typeof window.fetch === 'function' ? window.fetch.bind(window) : null;

    const escapeAttribute = (value) => String(value || '').replace(/[^a-zA-Z0-9_-]/g, '');

    function tableMarkup(target, rows) {
        const table = target.closest('table');
        const columns = Math.max(1, table?.querySelectorAll('thead th').length || Number(target.dataset.skeletonColumns) || 5);
        return Array.from({ length: rows }, (_, rowIndex) => {
            const cells = Array.from({ length: columns }, (_, columnIndex) => {
                const shape = columnIndex === 0 && columns > 4
                    ? '<span class="skeleton-circle"></span>'
                    : `<span class="skeleton-line ${(rowIndex + columnIndex) % 3 === 0 ? 'short' : (columnIndex % 2 ? 'medium' : '')}"></span>`;
                return `<td class="skeleton-table-cell">${shape}</td>`;
            }).join('');
            return `<tr class="skeleton-table-row" aria-hidden="true">${cells}</tr>`;
        }).join('');
    }

    function cardMarkup(count) {
        return `<div class="skeleton-card-grid" aria-hidden="true">${Array.from({ length: count }, () => '<div class="skeleton-card-placeholder"><span class="skeleton-line"></span><span class="skeleton-line medium"></span><span class="skeleton-line"></span></div>').join('')}</div>`;
    }

    function panelMarkup() {
        return '<div class="skeleton-panel-placeholder" aria-hidden="true"><span class="skeleton-line short"></span><span class="skeleton-line"></span><span class="skeleton-line medium"></span><span class="skeleton-block"></span></div>';
    }

    function pageMarkup(kind) {
        const login = kind === 'login';
        return `<div class="skeleton-page-shell" role="status" aria-label="Cargando contenido"><span class="skeleton-line skeleton-heading"></span><span class="skeleton-line skeleton-toolbar"></span><div class="skeleton-content">${login ? panelMarkup() : tableMarkupForPage()}</div></div>`;
    }

    function tableMarkupForPage() {
        return `<div class="skeleton-card-grid">${Array.from({ length: 3 }, () => '<div class="skeleton-card-placeholder"><span class="skeleton-line short"></span><span class="skeleton-line"></span><span class="skeleton-line medium"></span></div>').join('')}</div><div class="skeleton-panel-placeholder" style="margin-top:16px"><span class="skeleton-line"></span><span class="skeleton-line medium"></span><span class="skeleton-line"></span><span class="skeleton-line short"></span></div>`;
    }

    function show(target, variant = 'auto', options = {}) {
        if (!target) return;
        const current = states.get(target);
        if (current) {
            if (current.timer) {
                window.clearTimeout(current.timer);
                current.timer = null;
            }
            current.count += 1;
            return;
        }
        const state = { count: 1, html: target.innerHTML, startedAt: Date.now(), timer: null };
        states.set(target, state);
        target.classList.add('skeleton-loading');
        target.setAttribute('aria-busy', 'true');
        target.dataset.skeletonOwned = '1';
        const rows = Math.max(3, Number(options.rows) || 7);
        const resolved = variant === 'auto' ? (target.tagName === 'TBODY' ? 'table' : 'cards') : variant;
        if (resolved === 'table') target.innerHTML = tableMarkup(target, rows);
        else if (resolved === 'page' || resolved === 'login') {
            target.classList.add('skeleton-global-overlay');
            target.innerHTML = pageMarkup(resolved);
        } else if (resolved === 'panel') target.innerHTML = panelMarkup();
        else target.innerHTML = cardMarkup(Math.max(2, Number(options.cards) || 4));
    }

    function hide(target, force = false) {
        if (!target) return;
        const state = states.get(target);
        if (!state) return;
        state.count = force ? 0 : state.count - 1;
        if (state.count > 0) return;
        const finish = () => {
            const stillShowingSkeleton = Boolean(target.querySelector('.skeleton-table-row, .skeleton-card-grid, .skeleton-panel-placeholder, .skeleton-page-shell'));
            if (target.dataset.skeletonOwned === '1' && stillShowingSkeleton) target.innerHTML = state.html;
            target.classList.remove('skeleton-loading', 'skeleton-global-overlay');
            target.removeAttribute('aria-busy');
            delete target.dataset.skeletonOwned;
            states.delete(target);
        };
        const delay = Math.max(0, 120 - (Date.now() - state.startedAt));
        state.timer = window.setTimeout(finish, delay);
    }

    function targetsForRequest(resource, init) {
        const raw = typeof resource === 'string' ? resource : (resource?.url || '');
        const text = `${raw} ${init?.body instanceof FormData ? String(init.body.get('action') || '') : ''}`.toLowerCase();
        const body = document.body;
        if (!body) return [];
        if (body.dataset.silenciarSkeleton === '1') return [];
        const method = String(init?.method || 'GET').toUpperCase();
        if (method !== 'GET') {
            body.dataset.silenciarSkeleton = '1';
            window.setTimeout(() => {
                delete body.dataset.silenciarSkeleton;
            }, 4000);
            return [];
        }
        const result = [];
        const add = (selector, variant, options) => {
            const target = document.querySelector(selector);
            if (target && !result.some(item => item.target === target)) result.push({ target, variant, options });
        };
        if (body.classList.contains('page-codigos')) add('#codes-body', 'table', { rows: 8 });
        else if (document.querySelector('#productos-tbody')) add('#productos-tbody', 'table', { rows: 7 });
        else if (document.querySelector('#categorias-tbody')) add('#categorias-tbody', 'table', { rows: 7 });
        else if (document.querySelector('#cuerpo') && document.querySelector('#tarjetas')) {
            add('#cuerpo', 'table', { rows: 7 }); add('#tarjetas', 'cards', { cards: 4 });
        } else if (document.querySelector('#creditosLista')) add('#creditosLista', 'cards', { cards: 8 });
        else if (body.classList.contains('page-usuarios')) add('#usuarios-tbody', 'table', { rows: 7 });
        else if (body.classList.contains('page-roles')) add('#roles-tbody', 'table', { rows: 7 });
        else if (body.classList.contains('inventario')) {
            if (text.includes('entrada')) add('#entradasTableBody', 'table', { rows: 7 });
            else if (text.includes('salida')) add('#salidasTableBody', 'table', { rows: 7 });
            else if (text.includes('movimiento')) add('#movimientosTableBody', 'table', { rows: 7 });
            else add('#resumenTableBody', 'table', { rows: 7 });
        }
        return result;
    }

    if (nativeFetch) {
        window.fetch = function (resource, init) {
            const headers = init?.headers;
            const silent = headers && (headers['X-Silent-Request'] === '1' || headers['x-silent-request'] === '1');
            const targets = silent ? [] : targetsForRequest(resource, init);
            targets.forEach(item => show(item.target, item.variant, item.options));
            return nativeFetch(resource, init).finally(() => targets.forEach(item => hide(item.target)));
        };
    }

    window.EstrellaSkeleton = { show, hide, pageMarkup, targetsForRequest, escapeAttribute };
})();
