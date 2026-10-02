(function () {
    const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[caracter]);

    const formatearFecha = (valor) => {
        const fecha = String(valor || '').slice(0, 10);
        const coincidencia = fecha.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return coincidencia ? `${coincidencia[3]}/${coincidencia[2]}/${coincidencia[1]}` : fecha;
    };

    window.mostrarAlertaVencimientos = async ({ lotes = [], danados = [], fondoClaro = false, alConfirmar = null } = {}) => {
        const lotesEnAlerta = lotes.filter((lote) => lote.estado === 'vencido' || lote.estado === 'critico');
        const filasLotes = lotesEnAlerta.map((lote) => {
            const vencido = lote.estado === 'vencido';
            const estado = vencido ? 'VENCIDO' : `${Number(lote.dias) || 0} DÍA(S)`;
            const color = vencido ? '#b91c1c' : '#b45309';
            return `<tr style="border-bottom:1px solid #e5e7eb;"><td>${escaparHtml(lote.producto)}</td><td>${escaparHtml(lote.codigo)}</td><td style="text-align:center;">${Number(lote.cantidad) || 0}</td><td style="text-align:center;">${escaparHtml(formatearFecha(lote.fecha_vencimiento))}</td><td style="text-align:center;color:${color};font-weight:800;">${estado}</td></tr>`;
        }).join('');

        const danadosVisibles = danados.slice(0, 30);
        const filasDanados = danadosVisibles.map((item) => `<tr style="border-bottom:1px solid #e5e7eb;"><td>${escaparHtml(item.producto_nombre)}</td><td>${escaparHtml(item.codigo)}</td><td style="text-align:center;">${Number(item.cantidad) || 0}</td><td style="text-align:center;color:#b91c1c;font-weight:800;">${escaparHtml(formatearFecha(item.fecha_salida))}</td></tr>`).join('');
        const avisoLimiteDanados = danados.length > danadosVisibles.length
            ? `<p style="margin:8px 0;color:#64748b;">MOSTRANDO ${danadosVisibles.length} DE ${danados.length}. PULSA VER PRODUCTOS PARA ABRIR EL LISTADO COMPLETO.</p>`
            : '';
        const tieneVencidos = lotesEnAlerta.some((lote) => lote.estado === 'vencido');

        const resultado = await Swal.fire({
            icon: tieneVencidos || danados.length ? 'error' : 'warning',
            title: 'ALERTAS DE VENCIDOS Y DAÑADOS',
            html: `<div style="max-height:380px;overflow:auto;text-align:left;"><h3>VENCIDOS Y CRÍTICOS (${lotesEnAlerta.length})</h3><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr><th style="padding:8px;text-align:left;">PRODUCTO</th><th>CÓDIGO</th><th>CANT.</th><th>FECHA</th><th>ESTADO</th></tr></thead><tbody>${filasLotes || '<tr><td colspan="5" style="padding:10px;text-align:center;">NO HAY PRODUCTOS VENCIDOS O CRÍTICOS.</td></tr>'}</tbody></table><h3>DAÑADOS (${danados.length})</h3><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr><th style="padding:8px;text-align:left;">PRODUCTO</th><th>CÓDIGO</th><th>CANT.</th><th>FECHA</th></tr></thead><tbody>${filasDanados || '<tr><td colspan="4" style="padding:10px;text-align:center;">NO HAY PRODUCTOS DAÑADOS.</td></tr>'}</tbody></table>${avisoLimiteDanados}</div>`,
            showCancelButton: true,
            confirmButtonText: 'VER PRODUCTOS',
            cancelButtonText: 'CERRAR',
            confirmButtonColor: '#b45309',
            width: 760,
            backdrop: fondoClaro ? 'rgba(255, 255, 255, 0.97)' : true
        });

        if (resultado.isConfirmed && typeof alConfirmar === 'function') {
            alConfirmar();
        }
        return resultado;
    };
})();
