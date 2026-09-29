(function (global) {
    function redondearPrecioVenta(valor) {
        const n = Math.max(0, Number(valor) || 0);
        if (n <= 0) return 0;
        const base = Math.floor(n / 100) * 100;
        const resto = n - base;
        if (resto <= 40) return base;
        return base + 100;
    }

    global.redondearPrecioVenta = redondearPrecioVenta;
})(window);
