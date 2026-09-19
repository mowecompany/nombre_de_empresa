/**
 * Utilidades compartidas de paginación.
 * - El índice interno 0 corresponde al número de página visible más alto.
 * - El último bloque interno (página visible 1) se ancla al final para
 *   mostrar siempre la cantidad seleccionada cuando hay registros suficientes.
 */
(function () {
    'use strict';

    function totalPaginas(total, porPagina) {
        const tamano = Math.max(1, Number(porPagina) || 1);
        return Math.max(1, Math.ceil((Number(total) || 0) / tamano));
    }

    function inicioBloque(total, pagina, porPagina) {
        const registros = Math.max(0, Number(total) || 0);
        const tamano = Math.max(1, Number(porPagina) || 1);
        const paginas = totalPaginas(registros, tamano);
        const indice = Math.min(Math.max(0, Number(pagina) || 0), paginas - 1);
        if (indice === paginas - 1 && registros > tamano) {
            return Math.max(0, registros - tamano);
        }
        return indice * tamano;
    }

    function paginaAlCambiarTamano(total, paginaActual, tamanoAnterior, nuevoTamano) {
        const registros = Math.max(0, Number(total) || 0);
        const anterior = Math.max(1, Number(tamanoAnterior) || 1);
        const nuevo = Math.max(1, Number(nuevoTamano) || 1);
        const paginasAnterior = totalPaginas(registros, anterior);
        const indiceAnterior = Math.min(Math.max(0, Number(paginaActual) || 0), paginasAnterior - 1);
        const visibleAnterior = paginasAnterior - indiceAnterior;
        const paginasNuevo = totalPaginas(registros, nuevo);
        const visibleNueva = Math.min(Math.max(1, visibleAnterior), paginasNuevo);
        return paginasNuevo - visibleNueva;
    }

    /**
     * Devuelve una copia de la lista ordenada del registro más reciente al más
     * antiguo, igual que llegan los datos de Inventario (ORDER BY ... DESC).
     * Usa el primer campo disponible de `campos` (numérico o fecha); si ninguno
     * sirve, invierte el orden de llegada.
     */
    function recientesPrimero(lista, campos) {
        const datos = Array.isArray(lista) ? lista.slice() : [];
        if (datos.length < 2) return datos;
        const claves = Array.isArray(campos) && campos.length ? campos : ['id'];
        const clave = claves.find((campo) => datos.some((item) => item && item[campo] !== undefined && item[campo] !== null && item[campo] !== ''));
        if (!clave) return datos.reverse();

        const valor = (item) => {
            const bruto = item ? item[clave] : null;
            if (bruto === undefined || bruto === null || bruto === '') return null;
            const numero = Number(bruto);
            if (!Number.isNaN(numero) && /^-?\d+(\.\d+)?$/.test(String(bruto).trim())) return numero;
            const fecha = Date.parse(String(bruto));
            return Number.isNaN(fecha) ? null : fecha;
        };

        return datos
            .map((item, indice) => ({ item, indice, orden: valor(item) }))
            .sort((a, b) => {
                if (a.orden === null && b.orden === null) return b.indice - a.indice;
                if (a.orden === null) return 1;
                if (b.orden === null) return -1;
                if (a.orden === b.orden) return b.indice - a.indice;
                return b.orden - a.orden;
            })
            .map((entrada) => entrada.item);
    }

    window.EstrellaPaginacion = { totalPaginas, inicioBloque, paginaAlCambiarTamano, recientesPrimero };
})();
