<?php

class Inventario {
    private $db;
    private $columnasCache = [];

    private function getDriver(): string {
        try {
            return strtolower((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME));
        } catch (Exception $e) {
            return 'mysql';
        }
    }

    private function esSqlite(): bool {
        return $this->getDriver() === 'sqlite';
    }

    private function dbNow(): string {
        return $this->esSqlite() ? "datetime('now', 'localtime')" : 'NOW()';
    }

    private function normalizarTipoSalidaInventario(?string $tipo): string {
        $tipoNormalizado = strtolower(trim((string)($tipo ?? 'venta')));
        $permitidos = ['venta', 'dañado', 'perdida', 'ajuste'];
        if (in_array($tipoNormalizado, $permitidos, true)) {
            return $tipoNormalizado;
        }

        // El esquema de salidas_inventario no acepta 'credito' en tipo_salida; el método de pago
        // se persiste en la columna metodo_pago cuando corresponde y el tipo sigue siendo una salida válida.
        $aliasCredito = ['venta_credito_pagada', 'venta_credito', 'credito_pagado', 'pagado', 'credito'];
        if (in_array($tipoNormalizado, $aliasCredito, true)) {
            return 'venta';
        }

        return 'venta';
    }

    private function dbCurrentDate(): string {
        return $this->esSqlite() ? "date('now', 'localtime')" : 'CURDATE()';
    }

    private function dbConcat(array $parts): string {
        if ($this->esSqlite()) {
            $escaped = array_map(function ($part) {
                return "({$part})";
            }, $parts);
            return implode(' || ', $escaped);
        }
        return 'CONCAT(' . implode(', ', $parts) . ')';
    }

    private function dbGreatestZero(string $expr): string {
        return $this->esSqlite()
            ? "CASE WHEN {$expr} > 0 THEN {$expr} ELSE 0 END"
            : "GREATEST(0, {$expr})";
    }

    private function dbDateSubDays(int $days): string {
        return $this->esSqlite()
            ? "datetime('now', '-{$days} days', 'localtime')"
            : "DATE_SUB(NOW(), INTERVAL {$days} DAY)";
    }

    private function dbDateFormat(string $expr, string $format): string {
        return $this->esSqlite()
            ? "strftime('{$format}', {$expr})"
            : "DATE_FORMAT({$expr}, '{$format}')";
    }

    private function dbIfNotEmpty(string $expr, string $trueValue, string $falseValue): string {
        return "CASE WHEN TRIM(COALESCE({$expr}, '')) <> '' THEN {$trueValue} ELSE {$falseValue} END";
    }

    private function columnaDescuentoProductos(): string {
        if ($this->columnaExiste('productos', 'descuento_ganacia')) {
            return 'descuento_ganacia';
        }

        if ($this->columnaExiste('productos', 'descuento_porcentaje')) {
            return 'descuento_porcentaje';
        }

        return '';
    }

    private function exprDescuentoProducto(string $alias = 'p'): string {
        $columna = $this->columnaDescuentoProductos();
        if ($columna === '') {
            return '0';
        }

        return "CASE WHEN IFNULL({$alias}.stock, 0) = 0 THEN 0 ELSE IFNULL({$alias}.{$columna}, 0) END";
    }

    private function columnaPorcentajeGananciaProductos(): string {
        if ($this->columnaExiste('productos', 'porcentaje_ganancia')) {
            return 'porcentaje_ganancia';
        }

        if ($this->columnaExiste('productos', 'descuento_ganacia')) {
            return 'descuento_ganacia';
        }

        return '';
    }

    private function exprDescuentoSimple(string $alias = 'p'): string {
        $columna = $this->columnaDescuentoProductos();
        if ($columna === '') {
            return '0';
        }

        return "COALESCE({$alias}.{$columna}, 0)";
    }

    private function salidasInventarioIdEsPkAutoincremental(): bool {
        if (!$this->esSqlite()) {
            return true;
        }

        try {
            $stmt = $this->db->query("PRAGMA table_info('salidas_inventario')");
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columnas as $col) {
                if (strcasecmp($col['name'] ?? '', 'id') === 0) {
                    return isset($col['pk']) && (int)$col['pk'] > 0;
                }
            }
        } catch (Exception $e) {
            error_log('Error verificando PK de salidas_inventario: ' . $e->getMessage());
        }

        return false;
    }

    private function obtenerSiguienteIdSalidasInventario(): int {
        try {
            $stmt = $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM salidas_inventario');
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('Error obteniendo siguiente id para salidas_inventario: ' . $e->getMessage());
            return 1;
        }
    }

    private function entradasInventarioIdEsPkAutoincremental(): bool {
        if (!$this->esSqlite()) {
            return true;
        }

        try {
            $stmt = $this->db->query("PRAGMA table_info('entradas_inventario')");
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columnas as $col) {
                if (strcasecmp($col['name'] ?? '', 'id') === 0) {
                    return isset($col['pk']) && (int)$col['pk'] > 0;
                }
            }
        } catch (Exception $e) {
            error_log('Error verificando PK de entradas_inventario: ' . $e->getMessage());
        }

        return false;
    }

    private function obtenerSiguienteIdEntradasInventario(): int {
        try {
            $stmt = $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM entradas_inventario');
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('Error obteniendo siguiente id para entradas_inventario: ' . $e->getMessage());
            return 1;
        }
    }

    private function movimientosInventarioIdEsPkAutoincremental(): bool {
        if (!$this->esSqlite()) {
            return true;
        }

        try {
            $stmt = $this->db->query("PRAGMA table_info('movimientos_inventario')");
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columnas as $col) {
                if (strcasecmp($col['name'] ?? '', 'id') === 0) {
                    return isset($col['pk']) && (int)$col['pk'] > 0;
                }
            }
        } catch (Exception $e) {
            error_log('Error verificando PK de movimientos_inventario: ' . $e->getMessage());
        }

        return false;
    }

    private function obtenerSiguienteIdMovimientosInventario(): int {
        try {
            $stmt = $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM movimientos_inventario');
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('Error obteniendo siguiente id para movimientos_inventario: ' . $e->getMessage());
            return 1;
        }
    }

    private function obtenerStockReservadoOrdenesTaller(int $productoId, ?int $empresaId = null): int {
        return 0;
    }

    private function resolverProductoInventario(int $productoId, ?int $empresaId = null): ?array {
        if ($productoId <= 0) {
            return null;
        }

        try {
            $sql = 'SELECT id, stock, precio FROM productos WHERE id = :producto_id';
            $params = [':producto_id' => $productoId];

            if ($empresaId !== null && $empresaId > 0 && $this->tablaTieneEmpresaId('productos')) {
                $sql .= ' AND (empresa_id IS NULL OR empresa_id = 0 OR empresa_id = :empresa_id)';
                $params[':empresa_id'] = $empresaId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($producto) && !empty($producto)) {
                return $producto;
            }

            $fallback = $this->db->prepare('SELECT id, stock, precio FROM productos WHERE id = :producto_id LIMIT 1');
            $fallback->execute([':producto_id' => $productoId]);
            $productoFallback = $fallback->fetch(PDO::FETCH_ASSOC);
            return is_array($productoFallback) && !empty($productoFallback) ? $productoFallback : null;
        } catch (Exception $e) {
            error_log('Error resolviendo producto para inventario: ' . $e->getMessage());
            return null;
        }
    }

    private function esSuperAdmin(): bool {
        $rol = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
        $rol = strtr($rol, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);
        return $rol === 'super administrador';
    }

    private function esSuperAdminGlobal(): bool {
        return $this->esSuperAdmin() && empty($_SESSION['superadmin_modo_empresa']);
    }

    private function esAdministradorContexto(): bool {
        $rol = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
        $rol = strtr($rol, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);

        if ($rol === 'administrador') {
            return true;
        }

        return $rol === 'super administrador' && !empty($_SESSION['superadmin_modo_empresa']);
    }

    private function debeFiltrarPorUsuario(): bool {
        // En este modulo el alcance es por empresa para cualquier rol con permiso.
        return false;
    }

    private function resolverEmpresaIdDesdeUsuario(int $usuarioId): int {
        if ($usuarioId <= 0) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare("SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $usuarioId]);
            $empresaId = (int)($stmt->fetchColumn() ?: 0);
            return $empresaId > 0 ? $empresaId : 0;
        } catch (Exception $e) {
            error_log('Error resolviendo empresa por usuario en Inventario: ' . $e->getMessage());
            return 0;
        }
    }

    private function getEmpresaId() {
        $sessionKeys = ['empresa_id', 'empresaId', 'empresaid'];
        foreach ($sessionKeys as $key) {
            if (isset($_SESSION[$key]) && (int)$_SESSION[$key] > 0) {
                return (int)$_SESSION[$key];
            }
        }

        $userDataKeys = ['empresa_id', 'empresaId', 'empresaid'];
        foreach ($userDataKeys as $key) {
            $empresaData = (int)($_SESSION['userData'][$key] ?? 0);
            if ($empresaData > 0) {
                return $empresaData;
            }
        }

        return $this->resolverEmpresaIdDesdeUsuario($this->getUsuarioId());
    }

    private function getUsuarioId(): int {
        if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0) {
            return (int)$_SESSION['usuario_id'];
        }

        $userDataId = (int)($_SESSION['userData']['idusuario'] ?? ($_SESSION['userData']['id'] ?? 0));
        return $userDataId > 0 ? $userDataId : 0;
    }

    private function columnaExiste(string $tabla, string $columna): bool {
        $key = $tabla . '.' . $columna;
        if (array_key_exists($key, $this->columnasCache)) {
            return $this->columnasCache[$key];
        }

        try {
            if ($this->esSqlite()) {
                $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
                $stmt = $this->db->prepare("PRAGMA table_info(\"{$tablaSegura}\")");
                $stmt->execute();
                $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columnas as $col) {
                    if (strcasecmp($col['name'] ?? '', $columna) === 0) {
                        $this->columnasCache[$key] = true;
                        return true;
                    }
                }
                $this->columnasCache[$key] = false;
                return false;
            }

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
            $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
            $stmt->bindValue(':columna', $columna, PDO::PARAM_STR);
            $stmt->execute();
            $existe = ((int)$stmt->fetchColumn()) > 0;
            $this->columnasCache[$key] = $existe;
            return $existe;
        } catch (Exception $e) {
            error_log("Error en columnaExiste Inventario ({$tabla}.{$columna}): " . $e->getMessage());
            $this->columnasCache[$key] = false;
            return false;
        }
    }

    private function asegurarColumnaMetodoPago(): void {
        if ($this->columnaExiste('salidas_inventario', 'metodo_pago')) {
            return;
        }

        try {
            $sql = $this->esSqlite()
                ? "ALTER TABLE salidas_inventario ADD COLUMN metodo_pago TEXT NOT NULL DEFAULT 'efectivo'"
                : "ALTER TABLE salidas_inventario ADD COLUMN metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo'";
            $this->db->exec($sql);
            $this->columnasCache['salidas_inventario.metodo_pago'] = true;
        } catch (Exception $e) {
            error_log('No se pudo agregar metodo_pago a salidas_inventario: ' . $e->getMessage());
        }
    }

    private function asegurarColumnaNotasSalida(): void {
        if ($this->columnaExiste('salidas_inventario', 'notas')) {
            return;
        }

        try {
            $sql = $this->esSqlite()
                ? "ALTER TABLE salidas_inventario ADD COLUMN notas TEXT"
                : "ALTER TABLE salidas_inventario ADD COLUMN notas TEXT NULL";
            $this->db->exec($sql);
            $this->columnasCache['salidas_inventario.notas'] = true;
        } catch (Exception $e) {
            error_log('No se pudo agregar notas a salidas_inventario: ' . $e->getMessage());
        }
    }

    private function tablaTieneEmpresaId(string $tabla): bool {
        return $this->columnaExiste($tabla, 'empresa_id');
    }
    private function tablaTieneUsuarioId(string $tabla): bool {
        return $this->columnaExiste($tabla, 'usuario_id');
    }

    private function construirFiltroEmpresaEstricto(string $tabla, string $alias = ''): string {
        // En este sistema de una sola empresa no aplicamos filtro por empresa en inventario.
        return '';
    }

    private function construirFiltroTenant(string $tabla, string $alias = '', bool $incluirUsuario = true): string {
        $usuarioId = $this->getUsuarioId();
        $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
        $prefijo = $alias !== '' ? ($alias . '.') : '';
        $filtro = $this->construirFiltroEmpresaEstricto($tabla, $alias);

        if ($incluirUsuario && $filtrarPorUsuario && $this->tablaTieneUsuarioId($tabla) && $usuarioId > 0) {
            $filtro .= " AND {$prefijo}usuario_id = {$usuarioId}";
        }

        return $filtro;
    }

    private function exprTotalVenta(string $aliasSalida = 'si', string $aliasProducto = 'p'): string {
        if ($this->columnaExiste('salidas_inventario', 'total_venta')) {
            return "COALESCE({$aliasSalida}.total_venta, {$aliasSalida}.cantidad * {$aliasProducto}.precio)";
        }
        return "({$aliasSalida}.cantidad * {$aliasProducto}.precio)";
    }

    private function exprGananciaUnitaria(string $aliasSalida = 'si', string $aliasProducto = 'p'): string {
        $costoFallback = "COALESCE((SELECT precio_compra FROM entradas_inventario WHERE producto_id = {$aliasSalida}.producto_id ORDER BY fecha_entrada DESC LIMIT 1), 0)";
        $precioVentaExpr = $this->columnaExiste('salidas_inventario', 'precio_venta_unitario')
            ? "COALESCE({$aliasSalida}.precio_venta_unitario, {$aliasProducto}.precio)"
            : "{$aliasProducto}.precio";
        $costoExpr = $this->columnaExiste('salidas_inventario', 'costo_unitario')
            ? "COALESCE({$aliasSalida}.costo_unitario, {$costoFallback})"
            : $costoFallback;

        if ($this->columnaExiste('salidas_inventario', 'total_ganancia')) {
            return "CASE WHEN COALESCE({$aliasSalida}.total_ganancia, 0) <> 0 AND COALESCE({$aliasSalida}.cantidad, 0) > 0 THEN {$aliasSalida}.total_ganancia / {$aliasSalida}.cantidad ELSE ({$precioVentaExpr} - {$costoExpr}) END";
        }

        if ($this->columnaExiste('salidas_inventario', 'ganancia_unitaria')) {
            return "COALESCE({$aliasSalida}.ganancia_unitaria, ({$precioVentaExpr} - {$costoExpr}))";
        }

        if ($this->columnaExiste('salidas_inventario', 'precio_venta_unitario') || $this->columnaExiste('salidas_inventario', 'costo_unitario')) {
            return "({$precioVentaExpr} - {$costoExpr})";
        }

        return "({$aliasProducto}.precio - {$costoFallback})";
    }

    private function exprTotalGanancia(string $aliasSalida = 'si', string $aliasProducto = 'p'): string {
        if ($this->columnaExiste('salidas_inventario', 'total_ganancia')) {
            return "COALESCE({$aliasSalida}.total_ganancia, {$aliasSalida}.cantidad * " . $this->exprGananciaUnitaria($aliasSalida, $aliasProducto) . ")";
        }
        return "({$aliasSalida}.cantidad * " . $this->exprGananciaUnitaria($aliasSalida, $aliasProducto) . ")";
    }

    private function calcularPorcentajeGananciaVenta($venta): float {
        if (is_object($venta) || is_array($venta)) {
            $porcentajeGuardado = isset($venta->porcentaje_ganancia) ? floatval($venta->porcentaje_ganancia) : 0.0;
            if ($porcentajeGuardado > 0) {
                return $porcentajeGuardado;
            }

            $precioVenta = floatval($venta->precio_venta_unitario ?? $venta->precio ?? 0);
            $costo = floatval($venta->costo_unitario ?? 0);
            if ($costo > 0 && $precioVenta > 0) {
                return (($precioVenta - $costo) / $costo) * 100;
            }
        }

        return 0.0;
    }

    private function asegurarTablaBackupReset(): void {
        $sql = "CREATE TABLE IF NOT EXISTS inventario_backups_reset (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tabla TEXT NOT NULL,
            data TEXT NOT NULL,
            creado_en TEXT NOT NULL
        )";
        $this->db->exec($sql);
    }

    private function limpiarNombreTabla(string $tabla): string {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $tabla) ?: 'tabla';
    }

    private function guardarBackupTabla(string $tabla): int {
        $this->asegurarTablaBackupReset();
        $tablaSegura = $this->limpiarNombreTabla($tabla);

        $sql = "SELECT * FROM {$tablaSegura}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtInsert = $this->db->prepare("INSERT INTO inventario_backups_reset (tabla, data, creado_en) VALUES (:tabla, :data, :creado_en)");
        $stmtInsert->execute([
            ':tabla' => $tablaSegura,
            ':data' => json_encode($filas, JSON_UNESCAPED_UNICODE),
            ':creado_en' => date('Y-m-d H:i:s')
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function obtenerUltimoBackupTabla(string $tabla): ?array {
        $this->asegurarTablaBackupReset();
        $tablaSegura = $this->limpiarNombreTabla($tabla);
        $stmt = $this->db->prepare("SELECT id, tabla, data FROM inventario_backups_reset WHERE tabla = :tabla ORDER BY id DESC LIMIT 1");
        $stmt->execute([':tabla' => $tablaSegura]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$backup) {
            return null;
        }

        return [
            'id' => (int)($backup['id'] ?? 0),
            'tabla' => (string)($backup['tabla'] ?? $tablaSegura),
            'data' => (string)($backup['data'] ?? '[]')
        ];
    }

    private function eliminarBackupTablaPorId(int $id): void {
        $this->asegurarTablaBackupReset();
        $stmt = $this->db->prepare("DELETE FROM inventario_backups_reset WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    private function guardarBackupStockProductos(): int {
        $this->asegurarTablaBackupReset();
        $stmt = $this->db->prepare('SELECT id, stock FROM productos');
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtInsert = $this->db->prepare("INSERT INTO inventario_backups_reset (tabla, data, creado_en) VALUES (:tabla, :data, :creado_en)");
        $stmtInsert->execute([
            ':tabla' => 'productos',
            ':data' => json_encode($filas, JSON_UNESCAPED_UNICODE),
            ':creado_en' => date('Y-m-d H:i:s')
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function restaurarStockProductosDesdeBackup(?array $backup): void {
        if (!$backup) {
            return;
        }

        $filas = json_decode((string)($backup['data'] ?? '[]'), true);
        if (!is_array($filas)) {
            return;
        }

        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }

            $productoId = isset($fila['id']) ? (int)$fila['id'] : 0;
            if ($productoId <= 0) {
                continue;
            }

            $stock = isset($fila['stock']) ? (float)$fila['stock'] : 0.0;
            $stmt = $this->db->prepare('UPDATE productos SET stock = :stock WHERE id = :id');
            $stmt->execute([':stock' => $stock, ':id' => $productoId]);
        }
    }

    public function reiniciarInventario(?array $tablas = null): array {
        try {
            $this->db->beginTransaction();

            $tablasAReiniciar = is_array($tablas) && !empty($tablas)
                ? array_values(array_unique($tablas))
                : ['entradas_inventario', 'salidas_inventario', 'movimientos_inventario'];

            $reiniciarProductos = in_array('productos', $tablasAReiniciar, true);
            $reiniciarEntradas = in_array('entradas_inventario', $tablasAReiniciar, true);
            if ($reiniciarProductos || $reiniciarEntradas) {
                $this->guardarBackupStockProductos();
                $this->db->exec('UPDATE productos SET stock = 0');
            }

            foreach ($tablasAReiniciar as $tabla) {
                $tablaSegura = $this->limpiarNombreTabla((string)$tabla);

                if ($tablaSegura === 'productos') {
                    $this->guardarBackupStockProductos();
                    $this->db->exec('UPDATE productos SET stock = 0');
                    continue;
                }

                $this->guardarBackupTabla($tablaSegura);
                $this->db->exec("DELETE FROM {$tablaSegura}");
                if ($this->esSqlite()) {
                    $this->db->exec("DELETE FROM sqlite_sequence WHERE name = '{$tablaSegura}'");
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Inventario reiniciado correctamente',
                'tablas' => $tablasAReiniciar
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error reiniciando inventario: ' . $e->getMessage());
            throw new Exception('No se pudo reiniciar el inventario: ' . $e->getMessage());
        }
    }

    public function deshacerReinicioInventario(string $tabla): array {
        try {
            $this->db->beginTransaction();
            $tablaSegura = $this->limpiarNombreTabla($tabla);
            $backup = $this->obtenerUltimoBackupTabla($tablaSegura);

            if (!$backup) {
                throw new Exception('No hay un reinicio previo para deshacer en esta tabla');
            }

            $filas = json_decode((string)$backup['data'], true);
            if (!is_array($filas)) {
                $filas = [];
            }

            if ($tablaSegura === 'productos') {
                $this->restaurarStockProductosDesdeBackup($backup);
                $this->eliminarBackupTablaPorId((int)$backup['id']);
            } else {
                $this->db->exec("DELETE FROM {$tablaSegura}");
                if ($this->esSqlite()) {
                    $this->db->exec("DELETE FROM sqlite_sequence WHERE name = '{$tablaSegura}'");
                }
            }

            foreach ($filas as $fila) {
                if (!is_array($fila)) {
                    continue;
                }

                $columnas = [];
                $marcadores = [];
                $params = [];
                foreach ($fila as $columna => $valor) {
                    $columnaSegura = $this->limpiarNombreTabla((string)$columna);
                    if ($columnaSegura === '') {
                        continue;
                    }
                    $columnas[] = $columnaSegura;
                    $marcadores[] = '?';
                    $params[] = $valor;
                }

                if (empty($columnas)) {
                    continue;
                }

                $sql = "INSERT INTO {$tablaSegura} (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $marcadores) . ")";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            if ($tablaSegura === 'entradas_inventario') {
                $backupStock = $this->obtenerUltimoBackupTabla('productos');
                if ($backupStock) {
                    $this->restaurarStockProductosDesdeBackup($backupStock);
                    $this->eliminarBackupTablaPorId((int)$backupStock['id']);
                }
            }

            $this->eliminarBackupTablaPorId((int)$backup['id']);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Reinicio deshecho correctamente',
                'tabla' => $tablaSegura
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error deshaciendo reinicio: ' . $e->getMessage());
            throw new Exception('No se pudo deshacer el reinicio: ' . $e->getMessage());
        }
    }
    
    public function __construct($db) {
        $this->db = $db;
        $this->asegurarColumnaMetodoPago();
        $this->asegurarColumnaNotasSalida();
    }
    
    // Obtener conexión a la base de datos
    public function getDb() {
        return $this->db;
    }
    
    // Obtener todos los movimientos de inventario
    public function obtenerMovimientos($filtro = []) {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $movimientosTieneEmpresa = $this->tablaTieneEmpresaId('movimientos_inventario');
            $movimientosTieneUsuario = $this->tablaTieneUsuarioId('movimientos_inventario');
            $salidasTieneEmpresa = $this->tablaTieneEmpresaId('salidas_inventario');
            $salidasTieneUsuario = $this->tablaTieneUsuarioId('salidas_inventario');
            $salidasTieneFechaSalida = $this->columnaExiste('salidas_inventario', 'fecha_salida');
            $salidasTieneTipoSalida = $this->columnaExiste('salidas_inventario', 'tipo_salida');
            $salidasTieneNotas = $this->columnaExiste('salidas_inventario', 'notas');
            $salidasTieneReferencia = $this->columnaExiste('salidas_inventario', 'referencia');
            $salidasTienePrecioVentaUnitario = $this->columnaExiste('salidas_inventario', 'precio_venta_unitario');
            $salidasTieneTotalVenta = $this->columnaExiste('salidas_inventario', 'total_venta');

            if ($movimientosTieneEmpresa && $empresaId <= 0) {
                error_log('obtenerMovimientos: No se pudo resolver empresa_id, intentando fallback sin filtro de empresa');
            }

            $params = [];
            $aplicarFiltroEmpresa = $movimientosTieneEmpresa && $empresaId > 0;


            $sqlMain = "SELECT 
                    m.id,
                    m.tipo_movimiento,
                        p.codigo_barras,
                    m.cantidad,
                    m.stock_anterior,
                    m.stock_nuevo,
                    COALESCE(m.precio_unitario, p.precio, 0) AS precio_unitario,
                    COALESCE(m.total_movimiento, (s.cantidad * COALESCE(m.precio_unitario, p.precio, 0)), 0) AS total_movimiento,
                    m.referencia_id,
                    m.usuario_id,
                    m.descripcion,
                    m.fecha_movimiento,
                    m.empresa_id,
                    COALESCE(s.referencia, 'MOV-' || CAST(m.id AS TEXT)) AS referencia,
                    COALESCE(s.metodo_pago, 'efectivo') AS metodo_pago,
                    " . ($salidasTieneNotas ? 's.notas' : 'NULL') . " AS notas,
                    CASE WHEN EXISTS (SELECT 1 FROM creditos cr WHERE cr.empresa_id = s.empresa_id AND TRIM(COALESCE(cr.referencia, '')) <> '' AND TRIM(cr.referencia) = TRIM(s.referencia) AND LOWER(cr.estado) = 'pagado') THEN 1 ELSE 0 END AS es_credito,
                    p.nombre AS producto_nombre,
                    p.codigo AS codigo,
                    p.imagen AS producto_imagen,
                    u.nombre AS usuario_nombre,
                    u.apellidos AS usuario_apellidos,
                    u.rol AS usuario_rol,
                    c.nombre AS categoria_nombre
                    FROM movimientos_inventario m
                    INNER JOIN productos p ON m.producto_id = p.id
                    INNER JOIN categorias c ON p.categoria_id = c.id
                    LEFT JOIN usuarios u ON m.usuario_id = u.id
                    LEFT JOIN salidas_inventario s ON m.referencia_id = s.id AND m.tipo_movimiento = 'salida'
                    WHERE 1=1";

            $sqlSalidasFallback = "SELECT 
                    NULL AS id,
                    'salida' AS tipo_movimiento,
                    s.cantidad,
                    NULL AS stock_anterior,
                    NULL AS stock_nuevo,
                    " . ($salidasTienePrecioVentaUnitario ? "COALESCE(s.precio_venta_unitario, p.precio, 0)" : "p.precio") . " AS precio_unitario,
                    " . ($salidasTieneTotalVenta ? "COALESCE(s.total_venta, s.cantidad * " . ($salidasTienePrecioVentaUnitario ? "COALESCE(s.precio_venta_unitario, p.precio, 0)" : "p.precio") . ")" : "(s.cantidad * " . ($salidasTienePrecioVentaUnitario ? "COALESCE(s.precio_venta_unitario, p.precio, 0)" : "p.precio") . ")") . " AS total_movimiento,
                    s.id AS referencia_id,
                    s.usuario_id AS usuario_id,
                    COALESCE(s.metodo_pago, 'efectivo') AS metodo_pago,
                    " . ($salidasTieneNotas ? 's.notas' : 'NULL') . " AS notas,
                    CASE WHEN EXISTS (SELECT 1 FROM creditos cr WHERE cr.empresa_id = s.empresa_id AND TRIM(COALESCE(cr.referencia, '')) <> '' AND TRIM(cr.referencia) = TRIM(s.referencia) AND LOWER(cr.estado) = 'pagado') THEN 1 ELSE 0 END AS es_credito,
                    'Salida por ' || " . ($salidasTieneTipoSalida ? "CASE WHEN TRIM(COALESCE(s.tipo_salida, '')) <> '' THEN s.tipo_salida ELSE 'venta' END" : "'venta'") . " AS descripcion,
                    " . ($salidasTieneFechaSalida ? "s.fecha_salida" : $this->dbNow()) . " AS fecha_movimiento,
                    s.empresa_id,
                    " . ($salidasTieneReferencia ? "s.referencia" : "'SAL-' || CAST(s.id AS TEXT)") . " AS referencia,
                    p.nombre AS producto_nombre,
                    p.codigo AS codigo,
                    p.imagen AS producto_imagen,
                    u.nombre AS usuario_nombre,
                    u.apellidos AS usuario_apellidos,
                    u.rol AS usuario_rol,
                    c.nombre AS categoria_nombre
                    FROM salidas_inventario s
                    INNER JOIN productos p ON s.producto_id = p.id
                    INNER JOIN categorias c ON p.categoria_id = c.id
                    LEFT JOIN usuarios u ON s.usuario_id = u.id
                    WHERE NOT EXISTS (
                        SELECT 1 FROM movimientos_inventario m2
                        WHERE m2.referencia_id = s.id AND m2.tipo_movimiento = 'salida'
                    )";

            if ($salidasTieneNotas) {
                $sqlMain .= " AND (m.tipo_movimiento <> 'salida' OR s.notas IS NULL OR LOWER(s.notas) NOT LIKE 'crédito pendiente %')";
                $sqlSalidasFallback .= " AND (s.notas IS NULL OR LOWER(s.notas) NOT LIKE 'crédito pendiente %')";
            }

            if ($aplicarFiltroEmpresa) {
                $sqlMain .= " AND m.empresa_id = :empresa_id";
                $sqlSalidasFallback .= " AND s.empresa_id = :empresa_id";
                $params[':empresa_id'] = $empresaId;
            }
            if ($filtrarPorUsuario && $movimientosTieneUsuario && $usuarioId > 0) {
                $sqlMain .= " AND m.usuario_id = :usuario_id";
                $sqlSalidasFallback .= " AND s.usuario_id = :usuario_id";
                $params[':usuario_id'] = $usuarioId;
            }
            
            if (!empty($filtro['producto_id'])) {
                $sqlMain .= " AND m.producto_id = :producto_id";
                $sqlSalidasFallback .= " AND s.producto_id = :producto_id";
                $params[':producto_id'] = $filtro['producto_id'];
            }
            if (!empty($filtro['tipo'])) {
                $sqlMain .= " AND m.tipo_movimiento = :tipo";
                if (strtolower(trim($filtro['tipo'])) === 'salida') {
                    $sqlSalidasFallback .= " AND 'salida' = :tipo";
                } else {
                    $sqlSalidasFallback .= " AND 1 = 0";
                }
                $params[':tipo'] = $filtro['tipo'];
            }
            if (!empty($filtro['fecha_inicio'])) {
                $sqlMain .= " AND DATE(m.fecha_movimiento) >= :fecha_inicio";
                $sqlSalidasFallback .= " AND DATE(s.fecha_salida) >= :fecha_inicio";
                $params[':fecha_inicio'] = $filtro['fecha_inicio'];
            }
            if (!empty($filtro['fecha_fin'])) {
                $sqlMain .= " AND DATE(m.fecha_movimiento) <= :fecha_fin";
                $sqlSalidasFallback .= " AND DATE(s.fecha_salida) <= :fecha_fin";
                $params[':fecha_fin'] = $filtro['fecha_fin'];
            }

            $query = $this->db->prepare($sqlMain);
            $query->execute($params);
            $movimientos = $query->fetchAll(PDO::FETCH_OBJ);

            if (empty($movimientos) && $aplicarFiltroEmpresa) {
                $sqlMainFallback = str_replace(' AND m.empresa_id = :empresa_id', '', $sqlMain);
                $paramsFallback = $params;
                unset($paramsFallback[':empresa_id']);
                $queryFallback = $this->db->prepare($sqlMainFallback);
                $queryFallback->execute($paramsFallback);
                $movimientos = $queryFallback->fetchAll(PDO::FETCH_OBJ);
            }

            $querySalidas = $this->db->prepare($sqlSalidasFallback);
            $querySalidas->execute($params);
            $salidasSinMovimiento = $querySalidas->fetchAll(PDO::FETCH_OBJ);

            if (empty($salidasSinMovimiento) && $aplicarFiltroEmpresa) {
                $sqlSalidasFallbackFallback = str_replace(' AND s.empresa_id = :empresa_id', '', $sqlSalidasFallback);
                $paramsFallback = $params;
                unset($paramsFallback[':empresa_id']);
                $querySalidasFallback = $this->db->prepare($sqlSalidasFallbackFallback);
                $querySalidasFallback->execute($paramsFallback);
                $salidasSinMovimiento = $querySalidasFallback->fetchAll(PDO::FETCH_OBJ);
            }

            $resultado = array_merge($movimientos, $salidasSinMovimiento);

            usort($resultado, function ($a, $b) {
                $fechaA = strtotime((string)($a->fecha_movimiento ?? ''));
                $fechaB = strtotime((string)($b->fecha_movimiento ?? ''));
                if ($fechaA === $fechaB) {
                    return 0;
                }
                return $fechaA > $fechaB ? -1 : 1;
            });

            return $resultado;
        } catch(PDOException $e) {
            error_log("Error en obtenerMovimientos: " . $e->getMessage());
            return [];
        }
    }
    
    // Obtener entradas de inventario
    public function obtenerEntradas($filtro = []) {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $entradasTieneEmpresa = $this->tablaTieneEmpresaId('entradas_inventario');
            $entradasTieneUsuario = $this->tablaTieneUsuarioId('entradas_inventario');
            $sql = "SELECT e.*, p.codigo as codigo, p.nombre as producto_nombre, p.imagen as producto_imagen, pv.nombre as proveedor_nombre, 
                    u.nombre as usuario_nombre, u.apellidos as usuario_apellidos, u.rol as usuario_rol
                    FROM entradas_inventario e
                    INNER JOIN productos p ON e.producto_id = p.id
                    LEFT JOIN proveedores pv ON e.proveedor_id = pv.id
                    LEFT JOIN usuarios u ON e.usuario_id = u.id
                    WHERE 1=1";

            $params = [];
            $aplicarFiltroEmpresa = $entradasTieneEmpresa && $empresaId > 0;

            if ($aplicarFiltroEmpresa) {
                $sql .= " AND e.empresa_id = :empresa_id";
                $params[':empresa_id'] = $empresaId;
            }
            if ($filtrarPorUsuario && $entradasTieneUsuario && $usuarioId > 0) {
                $sql .= " AND e.usuario_id = :usuario_id";
                $params[':usuario_id'] = $usuarioId;
            }
            
            if (!empty($filtro['producto_id'])) {
                $sql .= " AND e.producto_id = :producto_id";
                $params[':producto_id'] = $filtro['producto_id'];
            }
            if (!empty($filtro['proveedor_id'])) {
                $sql .= " AND e.proveedor_id = :proveedor_id";
                $params[':proveedor_id'] = $filtro['proveedor_id'];
            }
            if (!empty($filtro['fecha_inicio'])) {
                $sql .= " AND DATE(e.fecha_entrada) >= :fecha_inicio";
                $params[':fecha_inicio'] = $filtro['fecha_inicio'];
            }
            if (!empty($filtro['fecha_fin'])) {
                $sql .= " AND DATE(e.fecha_entrada) <= :fecha_fin";
                $params[':fecha_fin'] = $filtro['fecha_fin'];
            }
            
            $sql .= " ORDER BY e.fecha_entrada DESC";
            
            $query = $this->db->prepare($sql);
            $query->execute($params);
            
            $entradas = $query->fetchAll(PDO::FETCH_OBJ);
            
            if (empty($entradas) && $aplicarFiltroEmpresa) {
                $sqlFallback = preg_replace('/ AND e\.empresa_id = :empresa_id/', '', $sql, 1);
                $paramsFallback = $params;
                unset($paramsFallback[':empresa_id']);
                $queryFallback = $this->db->prepare($sqlFallback);
                $queryFallback->execute($paramsFallback);
                return $queryFallback->fetchAll(PDO::FETCH_OBJ);
            }
            
            return $entradas;
        } catch(PDOException $e) {
            error_log("Error en obtenerEntradas: " . $e->getMessage());
            return [];
        }
    }
    
    // Obtener salidas de inventario
    public function obtenerSalidas($filtro = []) {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $salidasTieneEmpresa = $this->tablaTieneEmpresaId('salidas_inventario');
            $salidasTieneUsuario = $this->tablaTieneUsuarioId('salidas_inventario');
            // corregir registros existentes con tipo_salida vacío para que se traten como venta
            if ($salidasTieneEmpresa) {
                if ($empresaId <= 0) {
                    error_log('obtenerSalidas: No se pudo resolver empresa_id, intentando fallback sin filtro de empresa');
                }
                $sqlUpdateTipo = "UPDATE salidas_inventario SET tipo_salida='venta' WHERE (tipo_salida = '' OR tipo_salida IS NULL) AND empresa_id = " . intval($empresaId);
                if ($filtrarPorUsuario && $salidasTieneUsuario && $usuarioId > 0) {
                    $sqlUpdateTipo .= " AND usuario_id = " . intval($usuarioId);
                }
                $this->db->exec($sqlUpdateTipo);
            } else {
                if ($filtrarPorUsuario && $salidasTieneUsuario && $usuarioId > 0) {
                    $this->db->exec("UPDATE salidas_inventario SET tipo_salida='venta' WHERE (tipo_salida = '' OR tipo_salida IS NULL) AND usuario_id = " . intval($usuarioId));
                } else {
                    $this->db->exec("UPDATE salidas_inventario SET tipo_salida='venta' WHERE (tipo_salida = '' OR tipo_salida IS NULL)");
                }
            }

            $sql = "SELECT 
                    CASE WHEN TRIM(COALESCE(s.tipo_salida, '')) <> '' THEN
                        CASE WHEN LOWER(TRIM(s.tipo_salida)) IN ('credito', 'venta_credito_pagada', 'venta_credito', 'credito_pagado', 'pagado') THEN 'venta' ELSE s.tipo_salida END
                    ELSE 'venta' END as tipo_salida,
                    CASE WHEN EXISTS (
                            SELECT 1 FROM creditos cr
                            WHERE cr.empresa_id = s.empresa_id
                              AND TRIM(COALESCE(cr.referencia, '')) <> ''
                              AND TRIM(cr.referencia) = TRIM(s.referencia)
                              AND LOWER(cr.estado) = 'pagado'
                        ) THEN 'credito' ELSE 'venta' END as tipo_salida_efectiva,
                    s.*, p.codigo as codigo, p.nombre as producto_nombre, p.imagen as producto_imagen, 
                    CASE WHEN EXISTS (SELECT 1 FROM creditos cr WHERE cr.empresa_id = s.empresa_id AND TRIM(COALESCE(cr.referencia, '')) <> '' AND TRIM(cr.referencia) = TRIM(s.referencia) AND LOWER(cr.estado) = 'pagado') THEN 1 ELSE 0 END AS es_credito,
                    u.nombre as usuario_nombre, u.apellidos as usuario_apellidos, u.rol as usuario_rol
                    FROM salidas_inventario s
                    INNER JOIN productos p ON s.producto_id = p.id
                    LEFT JOIN usuarios u ON s.usuario_id = u.id
                    WHERE 1=1";

            if ($salidasTieneNotas) {
                $sql .= " AND (s.notas IS NULL OR LOWER(s.notas) NOT LIKE 'crédito pendiente %')";
            }

            $params = [];
            $aplicarFiltroEmpresa = $salidasTieneEmpresa && $empresaId > 0;

            if ($aplicarFiltroEmpresa) {
                $sql .= " AND s.empresa_id = :empresa_id";
                $params[':empresa_id'] = $empresaId;
            }
            if ($filtrarPorUsuario && $salidasTieneUsuario && $usuarioId > 0) {
                $sql .= " AND s.usuario_id = :usuario_id";
                $params[':usuario_id'] = $usuarioId;
            }
            
            if (!empty($filtro['producto_id'])) {
                $sql .= " AND s.producto_id = :producto_id";
                $params[':producto_id'] = $filtro['producto_id'];
            }
            if (!empty($filtro['tipo_salida'])) {
                $sql .= " AND s.tipo_salida = :tipo_salida";
                $params[':tipo_salida'] = $filtro['tipo_salida'];
            }
            if (!empty($filtro['fecha_inicio'])) {
                $sql .= " AND DATE(s.fecha_salida) >= :fecha_inicio";
                $params[':fecha_inicio'] = $filtro['fecha_inicio'];
            }
            if (!empty($filtro['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_salida) <= :fecha_fin";
                $params[':fecha_fin'] = $filtro['fecha_fin'];
            }
            
            $sql .= " ORDER BY s.fecha_salida DESC, s.id DESC";
            
            $query = $this->db->prepare($sql);
            
            try {
                $query->execute($params);
            } catch (Exception $e) {
                error_log('obtenerSalidas execute error: ' . $e->getMessage() . ' | SQL: ' . $sql);
                throw $e;
            }
            
            $salidas = $query->fetchAll(PDO::FETCH_OBJ);
            
            if (empty($salidas) && $aplicarFiltroEmpresa) {
                $sqlFallback = preg_replace('/ AND s\.empresa_id = :empresa_id/', '', $sql, 1);
                $paramsFallback = $params;
                unset($paramsFallback[':empresa_id']);
                $queryFallback = $this->db->prepare($sqlFallback);
                $queryFallback->execute($paramsFallback);
                return $queryFallback->fetchAll(PDO::FETCH_OBJ);
            }
            
            return $salidas;
        } catch(PDOException $e) {
            error_log("Error en obtenerSalidas: " . $e->getMessage());
            return [];
        }
    }
    
    // Registrar entrada de inventario
    public function registrarEntrada($datos) {
        try {
            $empresaId = $this->getEmpresaId();
            $entradasTieneEmpresa = $this->tablaTieneEmpresaId('entradas_inventario');
            $productosTieneEmpresa = $this->tablaTieneEmpresaId('productos');
            $movimientosTieneEmpresa = $this->tablaTieneEmpresaId('movimientos_inventario');
            $tienePorcentajeGanancia = $this->columnaExiste('productos', 'porcentaje_ganancia');
            // Iniciar transacción con bloqueo inmediato para multi-caja (SQLite)
            if ($this->esSqlite()) {
                $this->db->exec('BEGIN IMMEDIATE');
            } else {
                $this->db->beginTransaction();
            }
            
            // Calcular precio de venta basado en costo y porcentaje de ganancia
            $precio_compra = floatval($datos['precio_compra']);
            $porcentaje_ganancia = floatval($datos['porcentaje_ganancia'] ?? 0);
            $precio_venta = $precio_compra + ($precio_compra * ($porcentaje_ganancia / 100));
            
            // Verificar si la columna 'notas' existe en la tabla
            $tiene_notas = $this->columnaExiste('entradas_inventario', 'notas');
            $tiene_fecha_entrada = $this->columnaExiste('entradas_inventario', 'fecha_entrada');

            $columnasEntrada = ['producto_id', 'proveedor_id', 'cantidad', 'precio_compra', 'usuario_id'];
            $placeholdersEntrada = [':producto_id', ':proveedor_id', ':cantidad', ':precio_compra', ':usuario_id'];
            $params = [
                ':producto_id' => $datos['producto_id'],
                ':proveedor_id' => $datos['proveedor_id'] ?? null,
                ':cantidad' => $datos['cantidad'],
                ':precio_compra' => $precio_compra,
                ':usuario_id' => $datos['usuario_id'] ?? null
            ];

            if ($tiene_notas) {
                $columnasEntrada[] = 'notas';
                $placeholdersEntrada[] = ':notas';
                $params[':notas'] = $datos['notas'] ?? null;
            }

            if ($tiene_fecha_entrada) {
                $columnasEntrada[] = 'fecha_entrada';
                $placeholdersEntrada[] = ':fecha_entrada';
                $params[':fecha_entrada'] = gmdate('Y-m-d H:i:s');
            }

            if ($entradasTieneEmpresa) {
                $columnasEntrada[] = 'empresa_id';
                $placeholdersEntrada[] = ':empresa_id';
                $params[':empresa_id'] = $empresaId;
            }

            if ($this->esSqlite() && !$this->entradasInventarioIdEsPkAutoincremental()) {
                $columnasEntrada[] = 'id';
                $placeholdersEntrada[] = ':id';
                $params[':id'] = $this->obtenerSiguienteIdEntradasInventario();
            }

            $sql = "INSERT INTO entradas_inventario (" . implode(', ', $columnasEntrada) . ") VALUES (" . implode(', ', $placeholdersEntrada) . ")";
            $query = $this->db->prepare($sql);
            $query->execute($params);
            
            $entrada_id = $this->db->lastInsertId();
            
            // Obtener stock actual del producto
            $stmt = $this->db->prepare("SELECT stock FROM productos WHERE id = :id");
            $stmt->execute([':id' => $datos['producto_id']]);
            $producto = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$producto) {
                throw new Exception('Producto no encontrado');
            }
            
            $stock_anterior = (float)($producto->stock ?? 0);
            $stock_nuevo = $stock_anterior + $datos['cantidad'];
            
            // Actualizar stock, precio y porcentaje de ganancia del producto
            $sqlUpdate = "UPDATE productos SET stock = :stock, precio = :precio";
            $queryUpdate = $this->db->prepare($sqlUpdate);
            $paramsUpdate = [
                ':stock' => $stock_nuevo,
                ':precio' => $precio_venta,
                ':id' => $datos['producto_id']
            ];
            if ($tienePorcentajeGanancia) {
                $sqlUpdate .= ", porcentaje_ganancia = :porcentaje_ganancia";
                $paramsUpdate[':porcentaje_ganancia'] = $porcentaje_ganancia;
            }
            $sqlUpdate .= " WHERE id = :id";
            $queryUpdate = $this->db->prepare($sqlUpdate);
            $queryUpdate->execute($paramsUpdate);
            
            // Registrar movimiento
            $movimientoRegistrado = $this->registrarMovimiento([
                'producto_id' => $datos['producto_id'],
                'tipo_movimiento' => 'entrada',
                'cantidad' => $datos['cantidad'],
                'stock_anterior' => $stock_anterior,
                'stock_nuevo' => $stock_nuevo,
                'precio_unitario' => $datos['precio_compra'],
                'referencia_id' => $entrada_id,
                'usuario_id' => $datos['usuario_id'] ?? null,
                'descripcion' => 'Entrada de inventario',
                'empresa_id' => $movimientosTieneEmpresa ? $empresaId : null
            ]);
            if (!$movimientoRegistrado) {
                throw new Exception('No se pudo registrar el movimiento de la entrada');
            }
            
            // Finalizar transacción
            if ($this->esSqlite()) {
                $this->db->exec('COMMIT');
            } else {
                $this->db->commit();
            }
            
            return ['success' => true, 'message' => 'Entrada registrada correctamente', 'id' => $entrada_id];
        } catch(PDOException $e) {
            // Revertir transacción
            if ($this->esSqlite()) {
                try { $this->db->exec('ROLLBACK'); } catch (Exception $ex) {}
            } else {
                $this->db->rollBack();
            }
            error_log("Error en registrarEntrada: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al registrar entrada: ' . $e->getMessage()];
        }
    }
    
    // Registrar salida de inventario
    public function registrarSalida($datos) {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = isset($datos['usuario_id']) ? (int)$datos['usuario_id'] : 0;
            $omitirStock = !empty($datos['omitir_stock']);
            $salidasTieneEmpresa = $this->tablaTieneEmpresaId('salidas_inventario');
            $productosTieneEmpresa = $this->tablaTieneEmpresaId('productos');
            $entradasTieneEmpresa = $this->tablaTieneEmpresaId('entradas_inventario');
            $entradasTieneUsuario = $this->tablaTieneUsuarioId('entradas_inventario');
            $movimientosTieneEmpresa = $this->tablaTieneEmpresaId('movimientos_inventario');
            // Iniciar transacción con bloqueo inmediato para multi-caja (SQLite)
            if ($this->esSqlite()) {
                $this->db->exec('BEGIN IMMEDIATE');
            } else {
                $this->db->beginTransaction();
            }
            
            $producto = $this->resolverProductoInventario((int)($datos['producto_id'] ?? 0), $empresaId);
            if (!$producto) {
                throw new Exception('Producto no encontrado');
            }
            
            $stock_actual = (float)($producto['stock'] ?? 0);
            $stock_reservado = $this->obtenerStockReservadoOrdenesTaller((int)$datos['producto_id'], $empresaId);
            $stock_disponible_real = max(0, $stock_actual - $stock_reservado);
            
            if (!$omitirStock && $stock_disponible_real < $datos['cantidad']) {
                throw new Exception('Stock insuficiente. Disponible: ' . $stock_disponible_real);
            }

            // Snapshot de venta para no recalcular historicos con precios/porcentajes actuales.
            // Si el frontend pasa precio_venta (e.g. precio con descuento aplicado), usarlo; sino usar precio del producto.
            $precioVentaPasado = isset($datos['precio_venta']) && $datos['precio_venta'] > 0 ? floatval($datos['precio_venta']) : null;
            $precioVentaUnitario = $precioVentaPasado ?? floatval($producto['precio'] ?? 0);
            $precioVentaUnitario = max($precioVentaUnitario, floatval($producto['precio'] ?? 0));
            $sqlCosto = "SELECT precio_compra FROM entradas_inventario WHERE producto_id = :producto_id";
            $paramsCosto = [':producto_id' => $datos['producto_id']];
            if ($entradasTieneEmpresa) {
                $sqlCosto .= " AND empresa_id = :empresa_id";
                $paramsCosto[':empresa_id'] = $empresaId;
            }
            // NO filtrar por usuario_id para buscar costo: la entrada pudo ser de cualquier usuario de esta empresa
            $sqlCosto .= " ORDER BY fecha_entrada DESC LIMIT 1";
            $stmtCosto = $this->db->prepare($sqlCosto);
            $stmtCosto->execute($paramsCosto);
            $costoUnitario = floatval($stmtCosto->fetchColumn() ?: 0);

            $gananciaUnitaria = $precioVentaUnitario - $costoUnitario;
            $porcentajeGanancia = $costoUnitario > 0 ? (($gananciaUnitaria / $costoUnitario) * 100) : 0;
            $totalVenta = $precioVentaUnitario * floatval($datos['cantidad']);
            $totalGanancia = $gananciaUnitaria * floatval($datos['cantidad']);
            
            // Verificar si la columna 'notas' existe en la tabla
            $tiene_notas = $this->columnaExiste('salidas_inventario', 'notas');

            $tienePrecioVentaUnitario = $this->columnaExiste('salidas_inventario', 'precio_venta_unitario');
            $tieneCostoUnitario = $this->columnaExiste('salidas_inventario', 'costo_unitario');
            $tieneGananciaUnitaria = $this->columnaExiste('salidas_inventario', 'ganancia_unitaria');
            $tienePorcentajeGanancia = $this->columnaExiste('salidas_inventario', 'porcentaje_ganancia');
            $tieneTotalVenta = $this->columnaExiste('salidas_inventario', 'total_venta');
            $tieneTotalGanancia = $this->columnaExiste('salidas_inventario', 'total_ganancia');
            $tieneMetodoPago = $this->columnaExiste('salidas_inventario', 'metodo_pago');
            
            $columnasInsert = ['producto_id', 'cantidad', 'tipo_salida', 'fecha_salida', 'referencia', 'usuario_id'];
            $placeholdersInsert = [':producto_id', ':cantidad', ':tipo_salida', $this->dbNow(), ':referencia', ':usuario_id'];
            $tipoSalida = $this->normalizarTipoSalidaInventario((string)($datos['tipo_salida'] ?? 'venta'));
            $params = [
                ':producto_id' => $datos['producto_id'],
                ':cantidad' => $datos['cantidad'],
                ':tipo_salida' => $tipoSalida,
                ':referencia' => $datos['referencia'] ?? null,
                ':usuario_id' => $datos['usuario_id'] ?? null
            ];

            if ($tiene_notas) {
                $columnasInsert[] = 'notas';
                $placeholdersInsert[] = ':notas';
                $params[':notas'] = $datos['notas'] ?? null;
            }
            if ($tieneMetodoPago) {
                $columnasInsert[] = 'metodo_pago';
                $placeholdersInsert[] = ':metodo_pago';
                $metodoPago = strtolower(trim((string)($datos['metodo_pago'] ?? 'efectivo')));
                $params[':metodo_pago'] = in_array($metodoPago, ['efectivo', 'transferencia'], true) ? $metodoPago : 'efectivo';
            }
            if ($salidasTieneEmpresa) {
                $columnasInsert[] = 'empresa_id';
                $placeholdersInsert[] = ':empresa_id';
                $params[':empresa_id'] = $empresaId;
            }
            if ($tienePrecioVentaUnitario) {
                $columnasInsert[] = 'precio_venta_unitario';
                $placeholdersInsert[] = ':precio_venta_unitario';
                $params[':precio_venta_unitario'] = $precioVentaUnitario;
            }
            if ($tieneCostoUnitario) {
                $columnasInsert[] = 'costo_unitario';
                $placeholdersInsert[] = ':costo_unitario';
                $params[':costo_unitario'] = $costoUnitario;
            }
            if ($tieneGananciaUnitaria) {
                $columnasInsert[] = 'ganancia_unitaria';
                $placeholdersInsert[] = ':ganancia_unitaria';
                $params[':ganancia_unitaria'] = $gananciaUnitaria;
            }
            if ($tienePorcentajeGanancia) {
                $columnasInsert[] = 'porcentaje_ganancia';
                $placeholdersInsert[] = ':porcentaje_ganancia';
                $params[':porcentaje_ganancia'] = $porcentajeGanancia;
            }
            if ($tieneTotalVenta) {
                $columnasInsert[] = 'total_venta';
                $placeholdersInsert[] = ':total_venta';
                $params[':total_venta'] = $totalVenta;
            }
            if ($tieneTotalGanancia) {
                $columnasInsert[] = 'total_ganancia';
                $placeholdersInsert[] = ':total_ganancia';
                $params[':total_ganancia'] = $totalGanancia;
            }

            if ($this->esSqlite() && !$this->salidasInventarioIdEsPkAutoincremental()) {
                $columnasInsert[] = 'id';
                $placeholdersInsert[] = ':id';
                $params[':id'] = $this->obtenerSiguienteIdSalidasInventario();
            }

            $sql = "INSERT INTO salidas_inventario (" . implode(', ', $columnasInsert) . ") VALUES (" . implode(', ', $placeholdersInsert) . ")";
            $query = $this->db->prepare($sql);
            $query->execute($params);
            
            $salida_id = isset($params[':id']) ? $params[':id'] : $this->db->lastInsertId();
            
            // Un crédito ya entregó el producto al cliente; al pagarlo no se debe descontar stock otra vez.
            $stock_nuevo = $omitirStock ? $stock_actual : $stock_actual - $datos['cantidad'];
            if (!$omitirStock) {
                $sqlUpdate = "UPDATE productos SET stock = :stock WHERE id = :id";
                $queryUpdate = $this->db->prepare($sqlUpdate);
                $queryUpdate->execute([':stock' => $stock_nuevo, ':id' => $datos['producto_id']]);
            }
            
            // Registrar movimiento
            $movimientoRegistrado = $omitirStock || $this->registrarMovimiento([
                'producto_id' => $datos['producto_id'],
                'tipo_movimiento' => 'salida',
                'cantidad' => $datos['cantidad'],
                'stock_anterior' => $stock_actual,
                'stock_nuevo' => $stock_nuevo,
                'precio_unitario' => $precioVentaUnitario,
                'referencia_id' => $salida_id,
                'usuario_id' => $datos['usuario_id'] ?? null,
                'descripcion' => 'Salida por ' . ($datos['tipo_salida'] ?? 'venta'),
                'empresa_id' => $movimientosTieneEmpresa ? $empresaId : null
            ]);
            if (!$movimientoRegistrado) {
                throw new Exception('No se pudo registrar el movimiento de la salida');
            }
            
            // Finalizar transacción
            if ($this->esSqlite()) {
                $this->db->exec('COMMIT');
            } else {
                $this->db->commit();
            }
            
            return ['success' => true, 'message' => 'Salida registrada correctamente', 'id' => $salida_id];
        } catch(Exception $e) {
            // Revertir transacción
            if ($this->esSqlite()) {
                try { $this->db->exec('ROLLBACK'); } catch (Exception $ex) {}
            } else {
                $this->db->rollBack();
            }
            error_log("Error en registrarSalida: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Registrar movimiento de inventario
    private function registrarMovimiento($datos) {
        try {
            $total = ($datos['cantidad'] ?? 0) * ($datos['precio_unitario'] ?? 0);
            $movimientosTieneEmpresa = $this->tablaTieneEmpresaId('movimientos_inventario');

            $columnas = [
                'producto_id',
                'tipo_movimiento',
                'cantidad',
                'stock_anterior',
                'stock_nuevo',
                'referencia_id',
                'usuario_id',
                'descripcion',
                'fecha_movimiento'
            ];

            $placeholders = [
                ':producto_id',
                ':tipo_movimiento',
                ':cantidad',
                ':stock_anterior',
                ':stock_nuevo',
                ':referencia_id',
                ':usuario_id',
                ':descripcion',
                $this->dbNow()
            ];

            if ($this->columnaExiste('movimientos_inventario', 'precio_unitario')) {
                $columnas[] = 'precio_unitario';
                $placeholders[] = ':precio_unitario';
            }
            if ($this->columnaExiste('movimientos_inventario', 'total_movimiento')) {
                $columnas[] = 'total_movimiento';
                $placeholders[] = ':total_movimiento';
            }

            if ($movimientosTieneEmpresa) {
                $columnas[] = 'empresa_id';
                $placeholders[] = ':empresa_id';
            }
            if ($this->esSqlite() && !$this->movimientosInventarioIdEsPkAutoincremental()) {
                $columnas[] = 'id';
                $placeholders[] = ':id';
            }

            $sql = "INSERT INTO movimientos_inventario (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $query = $this->db->prepare($sql);

            $params = [
                ':producto_id' => $datos['producto_id'],
                ':tipo_movimiento' => $datos['tipo_movimiento'],
                ':cantidad' => $datos['cantidad'],
                ':stock_anterior' => $datos['stock_anterior'],
                ':stock_nuevo' => $datos['stock_nuevo'],
                ':referencia_id' => $datos['referencia_id'] ?? null,
                ':usuario_id' => $datos['usuario_id'] ?? null,
                ':descripcion' => $datos['descripcion'] ?? null
            ];
            if ($this->columnaExiste('movimientos_inventario', 'precio_unitario')) {
                $params[':precio_unitario'] = $datos['precio_unitario'] ?? null;
            }
            if ($this->columnaExiste('movimientos_inventario', 'total_movimiento')) {
                $params[':total_movimiento'] = $total;
            }

            if ($movimientosTieneEmpresa) {
                $params[':empresa_id'] = $datos['empresa_id'] ?? $this->getEmpresaId();
            }
            if ($this->esSqlite() && !$this->movimientosInventarioIdEsPkAutoincremental()) {
                $params[':id'] = $this->obtenerSiguienteIdMovimientosInventario();
            }

            $query->execute($params);
            return true;
        } catch(PDOException $e) {
            error_log("Error en registrarMovimiento: " . $e->getMessage());
            return false;
        }
    }

    // Obtener resumen del inventario
    public function obtenerResumenInventario(string $mes = null) {
        try {
            if ($mes !== null && !preg_match('/^\d{4}-\d{2}$/', $mes)) {
                $mes = null;
            }

            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $productosTieneEmpresa = $this->tablaTieneEmpresaId('productos');
            $productosTieneUsuario = $this->tablaTieneUsuarioId('productos');
            $salidasTieneEmpresa = $this->tablaTieneEmpresaId('salidas_inventario');
            $salidasTieneUsuario = $this->tablaTieneUsuarioId('salidas_inventario');
            $entradasTieneEmpresa = $this->tablaTieneEmpresaId('entradas_inventario');
            $entradasTieneUsuario = $this->tablaTieneUsuarioId('entradas_inventario');

            $filtroProductos = "";
            if ($productosTieneEmpresa) {
                $filtroProductos .= $this->construirFiltroEmpresaEstricto('productos', 'p');
            }
            if ($filtrarPorUsuario && $productosTieneUsuario && $usuarioId > 0) {
                $filtroProductos .= " AND p.usuario_id = {$usuarioId}";
            }

            $filtroSalidas = "";
            if ($salidasTieneEmpresa) {
                $filtroSalidas .= $this->construirFiltroEmpresaEstricto('salidas_inventario');
            }
            if ($filtrarPorUsuario && $salidasTieneUsuario && $usuarioId > 0) {
                $filtroSalidas .= " AND usuario_id = {$usuarioId}";
            }

            $filtroEntradas = "";
            if ($entradasTieneEmpresa) {
                $filtroEntradas .= $this->construirFiltroEmpresaEstricto('entradas_inventario');
            }
            if ($filtrarPorUsuario && $entradasTieneUsuario && $usuarioId > 0) {
                $filtroEntradas .= " AND usuario_id = {$usuarioId}";
            }

            $tablas_existen = true;
            try {
                $this->db->query("SELECT 1 FROM entradas_inventario LIMIT 1");
                $this->db->query("SELECT 1 FROM salidas_inventario LIMIT 1");
            } catch(PDOException $e) {
                $tablas_existen = false;
                error_log("Tablas de inventario no existen, mostrando solo productos");
            }

            $tieneDescuentoCol = $this->columnaDescuentoProductos() !== '';
            $tieneVentaPorKilo = $this->columnaExiste('productos', 'venta_por_kilo');
            $columnaGanancia = $this->columnaPorcentajeGananciaProductos();
            $tienePorcentajeGananciaCol = $columnaGanancia !== '';
            $exprDescuentoBase = $this->exprDescuentoProducto('p');
            $exprDescuento = "CASE WHEN IFNULL(p.stock, 0) <= 0 THEN 0 ELSE {$exprDescuentoBase} END";
            $exprUltimoPrecioCompra = "IFNULL((SELECT precio_compra FROM entradas_inventario e2 WHERE e2.producto_id = p.id{$filtroEntradas} ORDER BY e2.fecha_entrada DESC LIMIT 1), 0)";
            $exprGananciaBasePct = $tienePorcentajeGananciaCol
                ? "CASE WHEN IFNULL(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN p.{$columnaGanancia} IS NOT NULL THEN COALESCE(p.{$columnaGanancia}, 0) ELSE CASE WHEN {$exprUltimoPrecioCompra} > 0 THEN ROUND(((p.precio - {$exprUltimoPrecioCompra}) * 100.0 / {$exprUltimoPrecioCompra}), 2) ELSE 0 END END END"
                : "CASE WHEN IFNULL(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN {$exprUltimoPrecioCompra} > 0 THEN ROUND(((p.precio - {$exprUltimoPrecioCompra}) * 100.0 / {$exprUltimoPrecioCompra}), 2) ELSE 0 END END";
            $exprGananciaFinalPct = "CASE WHEN IFNULL(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN ({$exprGananciaBasePct} - ({$exprDescuento})) > 0 THEN ({$exprGananciaBasePct} - ({$exprDescuento})) ELSE 0 END END";
            $exprPrecioFinal = $tieneDescuentoCol
                ? "CASE WHEN IFNULL(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN ({$exprDescuento}) > 0 AND {$exprUltimoPrecioCompra} > 0 THEN ROUND({$exprUltimoPrecioCompra} * (1 + ({$exprGananciaFinalPct} / 100)), 2) ELSE CASE WHEN ({$exprDescuento}) > 0 THEN CASE WHEN p.precio * (1 - ({$exprDescuento})/100) > 0 THEN p.precio * (1 - ({$exprDescuento})/100) ELSE 0 END ELSE p.precio END END END"
                : "CASE WHEN IFNULL(p.stock, 0) <= 0 THEN 0 ELSE p.precio END";

            $filtroMesSalidas = '';
            $filtroMesEntradas = '';
            if ($mes !== null) {
                $filtroMesSalidas = ' AND ' . $this->dbMonthEquals('fecha_salida', $mes);
                $filtroMesEntradas = ' AND ' . $this->dbMonthEquals('fecha_entrada', $mes);
            }

            $filtroVentasFecha = $mes !== null
                ? $filtroMesSalidas
                : ' AND fecha_salida >= ' . $this->dbDateSubDays(30);
            $filtroEntradasFecha = $mes !== null
                ? $filtroMesEntradas
                : ' AND fecha_entrada >= ' . $this->dbDateSubDays(30);

            if ($tablas_existen) {
                $sql = "SELECT 
                        p.id, 
                        p.codigo as codigo,
                        p.nombre, 
                        p.imagen,
                        p.color,
                        IFNULL(p.stock, 0) as stock, 
                        IFNULL(p.precio, 0) as precio,
                        " . ($tieneVentaPorKilo ? "COALESCE(p.venta_por_kilo, 0)" : "0") . " as venta_por_kilo,
                        {$exprDescuento} as descuento_porcentaje,
                        {$exprGananciaBasePct} as porcentaje_ganancia,
                        {$exprPrecioFinal} as precio_final,
                        IFNULL(c.nombre, 'SIN CATEGORÍA') as categoria,
                        IFNULL((SELECT SUM(cantidad) FROM salidas_inventario WHERE producto_id = p.id " . $filtroSalidas . $filtroVentasFecha . "), 0) as ventas_30dias,
                        IFNULL((SELECT SUM(cantidad) FROM entradas_inventario WHERE producto_id = p.id " . $filtroEntradas . $filtroEntradasFecha . "), 0) as entradas_30dias
                        FROM productos p
                        LEFT JOIN categorias c ON p.categoria_id = c.id
                        WHERE p.estado = 1" . $filtroProductos . "
                        ORDER BY p.id DESC";
            } else {
                $sql = "SELECT 
                        p.id, 
                        p.codigo as codigo,
                        p.nombre, 
                        p.imagen,
                        p.color,
                        IFNULL(p.stock, 0) as stock, 
                        IFNULL(p.precio, 0) as precio,
                        0 as venta_por_kilo,
                        {$exprDescuento} as descuento_porcentaje,
                        {$exprGananciaBasePct} as porcentaje_ganancia,
                        {$exprPrecioFinal} as precio_final,
                        IFNULL(c.nombre, 'SIN CATEGORÍA') as categoria,
                        0 as ventas_30dias,
                        0 as entradas_30dias
                        FROM productos p
                        LEFT JOIN categorias c ON p.categoria_id = c.id
                        WHERE 1=1" . $filtroProductos . "
                        ORDER BY p.id DESC";
            }

            error_log("SQL para resumen: " . $sql);

            $query = $this->db->prepare($sql);
            $query->execute();

            $resultados = $query->fetchAll(PDO::FETCH_OBJ);
            error_log("Productos encontrados en total: " . count($resultados));

            $con_stock = 0;
            foreach ($resultados as $prod) {
                if ($prod->stock > 0) {
                    $con_stock++;
                }
            }
            error_log("Productos con stock > 0: " . $con_stock);

            return $resultados;
        } catch(PDOException $e) {
            error_log("Error en obtenerResumenInventario: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerMesesInventario() {
        try {
            $meses = [];
            $bases = [
                'entradas_inventario' => [
                    'fecha' => 'fecha_entrada',
                    'filtro' => $this->construirFiltroTenant('entradas_inventario'),
                    'having' => ["SUM(COALESCE(cantidad, 0)) > 0"]
                ],
                'salidas_inventario' => [
                    'fecha' => 'fecha_salida',
                    'filtro' => $this->construirFiltroTenant('salidas_inventario'),
                    'having' => ["SUM(COALESCE(cantidad, 0)) > 0"]
                ]
            ];

            foreach ($bases as $tabla => $config) {
                $fechaCol = $config['fecha'];
                $filtroTabla = $config['filtro'];
                $havingConditions = $config['having'];

                if ($tabla === 'salidas_inventario') {
                    $filtroTabla .= " AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(tipo_salida,'')), ''), 'venta')) IN ('venta', 'venta_credito_pagada')";
                }

                $sql = "SELECT DISTINCT " . $this->dbDateFormat($fechaCol, '%Y-%m') . " AS mes
                        FROM {$tabla}
                        WHERE {$fechaCol} IS NOT NULL
                        " . $filtroTabla . "
                        GROUP BY " . $this->dbDateFormat($fechaCol, '%Y-%m') . "
                        HAVING " . implode(' AND ', $havingConditions) . "
                        ORDER BY mes DESC";

                $query = $this->db->prepare($sql);
                $query->execute();
                $rows = $query->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($rows as $row) {
                    if (!empty($row['mes'])) {
                        $meses[] = $row['mes'];
                    }
                }
            }

            $meses = array_values(array_unique($meses));
            $mesActual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m');
            if (!in_array($mesActual, $meses, true)) {
                array_unshift($meses, $mesActual);
            }
            usort($meses, static function ($a, $b) {
                return strcmp($b, $a);
            });
            $meses = array_values(array_unique($meses));

            return $meses;
        } catch(PDOException $e) {
            error_log("Error en obtenerMesesInventario: " . $e->getMessage());
            return [];
        }
    }

    private function dbMonthEquals(string $column, string $mes): string {
        if ($this->esSqlite()) {
            return "strftime('%Y-%m', {$column}) = '{$mes}'";
        }
        return "DATE_FORMAT({$column}, '%Y-%m') = '{$mes}'";
    }
    
    // Obtener estadísticas del inventario
    public function obtenerEstadisticas() {
        try {
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $productosTieneEmpresa = $this->tablaTieneEmpresaId('productos');
            $productosTieneUsuario = $this->tablaTieneUsuarioId('productos');
            $movimientosTieneEmpresa = $this->tablaTieneEmpresaId('movimientos_inventario');
            $movimientosTieneUsuario = $this->tablaTieneUsuarioId('movimientos_inventario');
            $estadisticas = [];

            $filtroProductos = "";
            if ($productosTieneEmpresa) {
                $filtroProductos .= $this->construirFiltroEmpresaEstricto('productos');
            }
            if ($filtrarPorUsuario && $productosTieneUsuario && $usuarioId > 0) {
                $filtroProductos .= " AND usuario_id = {$usuarioId}";
            }
            
            // Total de productos activos
            $sql = "SELECT COUNT(*) as total FROM productos WHERE estado = 1" . $filtroProductos;
            $query = $this->db->prepare($sql);
            $query->execute();
            $estadisticas['total_productos'] = $query->fetch(PDO::FETCH_OBJ)->total ?? 0;
            
            // Stock total de productos con stock mayor a cero
            $sql = "SELECT COALESCE(SUM(COALESCE(stock, 0)), 0) as total FROM productos WHERE estado = 1 AND COALESCE(stock, 0) > 0" . $filtroProductos;
            $query = $this->db->prepare($sql);
            $query->execute();
            $estadisticas['stock_total'] = $query->fetch(PDO::FETCH_OBJ)->total ?? 0;
            
            // Valor total del inventario solo para productos con stock mayor a cero
            $sql = "SELECT COALESCE(SUM(COALESCE(stock, 0) * COALESCE(precio, 0)), 0) as total FROM productos WHERE estado = 1 AND COALESCE(stock, 0) > 0" . $filtroProductos;
            $query = $this->db->prepare($sql);
            $query->execute();
            $estadisticas['valor_total'] = $query->fetch(PDO::FETCH_OBJ)->total ?? 0;
            
            // BAJO STOCK en dashboard: coincide con reorden (critico/urgente/normal => stock <= 5).
            $sql = "SELECT COUNT(*) as total FROM productos WHERE estado = 1 AND IFNULL(stock, 0) <= 5" . $filtroProductos;
            $query = $this->db->prepare($sql);
            $query->execute();
            $estadisticas['bajo_stock'] = $query->fetch(PDO::FETCH_OBJ)->total ?? 0;
            
            // Movimientos en últimos 30 días (solo si la tabla existe)
            try {
                $sql = "SELECT COUNT(*) as total FROM movimientos_inventario WHERE fecha_movimiento >= " . $this->dbDateSubDays(30);
                if ($movimientosTieneEmpresa) {
                    $sql .= $this->construirFiltroEmpresaEstricto('movimientos_inventario');
                }
                if ($filtrarPorUsuario && $movimientosTieneUsuario && $usuarioId > 0) {
                    $sql .= " AND usuario_id = {$usuarioId}";
                }
                $query = $this->db->prepare($sql);
                $query->execute();
                $estadisticas['movimientos_30dias'] = $query->fetch(PDO::FETCH_OBJ)->total ?? 0;
            } catch(PDOException $e) {
                $estadisticas['movimientos_30dias'] = 0;
            }
            
            return $estadisticas;
        } catch(PDOException $e) {
            error_log("Error en obtenerEstadisticas: " . $e->getMessage());
            return [];
        }
    }
    
    // Obtener desglose del valor del inventario por producto
    public function obtenerValorInventario() {
        try {
            $filtroProductos = $this->construirFiltroTenant('productos', 'p');
            $sql = "SELECT 
                    p.id, 
                    p.codigo as codigo,
                    p.nombre, 
                    p.imagen,
                    IFNULL(p.stock, 0) as stock, 
                    IFNULL(p.precio, 0) as precio,
                    c.nombre as categoria,
                    (IFNULL(p.stock, 0) * IFNULL(p.precio, 0)) as valor_total
                    FROM productos p
                    LEFT JOIN categorias c ON p.categoria_id = c.id
                    WHERE p.estado = 1 AND IFNULL(p.stock, 0) > 0" . $filtroProductos . "
                    ORDER BY p.id DESC";
            
            $query = $this->db->prepare($sql);
            $query->execute();
            $productos = $query->fetchAll(PDO::FETCH_OBJ);
            
            // Calcular totales
            $valor_total = 0;
            foreach ($productos as $prod) {
                $valor_total += floatval($prod->valor_total);
            }
            
            return [
                'productos' => $productos,
                'valor_total' => $valor_total,
                'cantidad_productos' => count($productos)
            ];
        } catch(PDOException $e) {
            error_log("Error en obtenerValorInventario: " . $e->getMessage());
            return ['productos' => [], 'valor_total' => 0, 'cantidad_productos' => 0];
        }
    }
    
    // Obtener ganancia total del inventario
    public function obtenerGananciaTotal() {
        try {
            $filtroSalidas = $this->construirFiltroTenant('salidas_inventario', 'si');
            $filtroProductos = $this->construirFiltroTenant('productos', 'p');
            $exprTotalGanancia = $this->exprTotalGanancia('si', 'p');

            $sql = "SELECT 
                    COALESCE(SUM(" . $exprTotalGanancia . "), 0) as ganancia_total
                    FROM salidas_inventario si
                    INNER JOIN productos p ON si.producto_id = p.id
                    WHERE LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) IN ('venta', 'venta_credito_pagada')" . $filtroSalidas . $filtroProductos;
            
            $query = $this->db->prepare($sql);
            $query->execute();
            $resultado = $query->fetch(PDO::FETCH_OBJ);
            
            return [
                'ganancia_total' => floatval($resultado->ganancia_total ?? 0),
                'ganancia_actual_inventario' => $this->obtenerGananciaInventarioActual(),
                'ganancia_ventas_dia' => $this->obtenerGananciaVentasDelDia()
            ];
        } catch(PDOException $e) {
            error_log("Error en obtenerGananciaTotal: " . $e->getMessage());
            return ['ganancia_total' => 0, 'ganancia_actual_inventario' => 0, 'ganancia_ventas_dia' => 0];
        }
    }

    // Calcular ganancia de las ventas del día (ganancia realizada)
    private function obtenerGananciaVentasDelDia($fecha = null) {
        try {
            $filtroSalidas = $this->construirFiltroTenant('salidas_inventario', 'si');
            $filtroProductos = $this->construirFiltroTenant('productos', 'p');
            $exprTotalGanancia = $this->exprTotalGanancia('si', 'p');
            $sql = "SELECT 
                    COALESCE(SUM(" . $exprTotalGanancia . "), 0) as ganancia
                    FROM salidas_inventario si
                    INNER JOIN productos p ON si.producto_id = p.id
                    WHERE DATE(si.fecha_salida) = " . ($fecha ? ':fecha' : $this->dbCurrentDate()) . "
                    AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) IN ('venta', 'venta_credito_pagada')" . $filtroSalidas . $filtroProductos;

            $query = $this->db->prepare($sql);
            if ($fecha) {
                $query->execute([':fecha' => $fecha]);
            } else {
                $query->execute();
            }
            $row = $query->fetch(PDO::FETCH_OBJ);
            return floatval($row->ganancia ?? 0);
        } catch(PDOException $e) {
            error_log("Error en obtenerGananciaVentasDelDia: " . $e->getMessage());
            return 0;
        }
    }
    
    // Calcular ganancia actual del inventario (stock actual)
    private function obtenerGananciaInventarioActual() {
        try {
            $filtroProductos = $this->construirFiltroTenant('productos', 'p');
            $filtroEntradasSub = $this->construirFiltroTenant('entradas_inventario', '', false);
            // Primero necesitar el costo promedio por producto
            $sql = "SELECT 
                    p.id,
                    p.nombre,
                    p.stock,
                    p.precio,
                    (SELECT AVG(precio_compra) FROM entradas_inventario WHERE producto_id = p.id AND estado = 1" . $filtroEntradasSub . ") as costo_promedio,
                    (p.precio - (SELECT AVG(precio_compra) FROM entradas_inventario WHERE producto_id = p.id AND estado = 1" . $filtroEntradasSub . ")) as ganancia_unitaria
                    FROM productos p
                    WHERE p.estado = 1" . $filtroProductos . " AND EXISTS (
                        SELECT 1 FROM entradas_inventario WHERE producto_id = p.id AND estado = 1" . $filtroEntradasSub . "
                    )";
            
            $query = $this->db->prepare($sql);
            $query->execute();
            $productos = $query->fetchAll(PDO::FETCH_OBJ);
            
            $ganancia_total = 0;
            foreach ($productos as $prod) {
                if (!is_null($prod->ganancia_unitaria)) {
                    $ganancia_total += (floatval($prod->ganancia_unitaria) * intval($prod->stock));
                }
            }
            
            return $ganancia_total;
        } catch(PDOException $e) {
            error_log("Error en obtenerGananciaInventarioActual: " . $e->getMessage());
            return 0;
        }
    }
    
    // Obtener productos que necesitan reorden (bajo stock)
    public function obtenerProductosParaReorden($limite_stock = 5, $soloAgotados = false) {
        try {
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $limite_stock = intval($limite_stock);
            if ($limite_stock <= 0) {
                $limite_stock = 5;
            }

            $filtroProductos = "";
            if ($this->tablaTieneEmpresaId('productos')) {
                $filtroProductos .= $this->construirFiltroEmpresaEstricto('productos', 'p');
            }
            if ($filtrarPorUsuario && $this->tablaTieneUsuarioId('productos') && $usuarioId > 0) {
                $filtroProductos .= " AND p.usuario_id = {$usuarioId}";
            }

            $filtroStock = $soloAgotados ? "IFNULL(p.stock, 0) <= 0" : "IFNULL(p.stock, 0) <= :limite_stock";

            $sql = "SELECT 
                    p.id, 
                    p.codigo, 
                    p.nombre, 
                    IFNULL(p.stock, 0) as stock, 
                    p.precio, 
                    p.imagen, 
                    c.nombre as categoria,
                    CASE
                        WHEN IFNULL(p.stock, 0) <= 0 THEN 'CRITICO'
                        WHEN IFNULL(p.stock, 0) BETWEEN 1 AND 3 THEN 'URGENTE'
                        WHEN IFNULL(p.stock, 0) BETWEEN 4 AND 5 THEN 'NORMAL'
                        ELSE 'NORMAL'
                    END as nivel_urgencia,
                    " . $this->dbGreatestZero(':limite_stock - IFNULL(p.stock, 0)') . " as cantidad_a_pedir
                    FROM productos p
                    LEFT JOIN categorias c ON p.categoria_id = c.id
                    WHERE p.estado = 1 AND " . $filtroStock . $filtroProductos . "
                    ORDER BY p.id DESC";

            $query = $this->db->prepare($sql);
            if (!$soloAgotados) {
                $query->bindValue(':limite_stock', $limite_stock, PDO::PARAM_INT);
            }
            $query->execute();
            $resultado = $query->fetchAll(PDO::FETCH_OBJ);
            
            return $resultado;
        } catch(PDOException $e) {
            error_log("Error en obtenerProductosParaReorden: " . $e->getMessage());
            return [];
        }
    }
    
    // Obtener resumen de ventas del día
    public function obtenerVentasDelDia() {
        try {
            $hoy = date('Y-m-d');
            $filtroSalidas = $this->construirFiltroTenant('salidas_inventario', 'si');
            $filtroProductos = $this->construirFiltroTenant('productos', 'p');
            $exprTotalVenta = $this->exprTotalVenta('si', 'p');
            $exprTotalGanancia = $this->exprTotalGanancia('si', 'p');
            $exprPrecioUnitario = $this->columnaExiste('salidas_inventario', 'precio_venta_unitario')
                ? 'COALESCE(AVG(COALESCE(si.precio_venta_unitario, p.precio)), p.precio)'
                : 'p.precio';
            
            // Total de ventas del día
            $sql = "SELECT 
                COUNT(*) as total_ventas,
                COALESCE(SUM(si.cantidad), 0) as unidades_vendidas,
                COALESCE(SUM(" . $exprTotalVenta . "), 0) as valor_total_ventas,
                COALESCE(SUM(" . $exprTotalGanancia . "), 0) as ganancia_total_ventas
                FROM salidas_inventario si
                INNER JOIN productos p ON si.producto_id = p.id
                WHERE DATE(si.fecha_salida) = " . $this->dbCurrentDate() . "
                AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'" . $filtroSalidas . $filtroProductos;
            
            $query = $this->db->prepare($sql);
            $query->execute();
            $totales = $query->fetch(PDO::FETCH_OBJ);

            if (!$totales) {
                throw new Exception("No se pudieron obtener los totales de ventas");
            }

            $sqlPagosDia = "SELECT
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(si.metodo_pago), ''), 'efectivo')) = 'transferencia' THEN " . $exprTotalVenta . " ELSE 0 END), 0) AS total_transferencia,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(si.metodo_pago), ''), 'efectivo')) <> 'transferencia' THEN " . $exprTotalVenta . " ELSE 0 END), 0) AS total_efectivo
                FROM salidas_inventario si
                INNER JOIN productos p ON si.producto_id = p.id
                WHERE DATE(si.fecha_salida) = " . $this->dbCurrentDate() . "
                AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'" . $filtroSalidas . $filtroProductos;
            $queryPagosDia = $this->db->prepare($sqlPagosDia);
            $queryPagosDia->execute();
            $pagosDia = $queryPagosDia->fetch(PDO::FETCH_OBJ);

            $unidadesDia = floatval($totales->unidades_vendidas ?? 0);
            $ganancia_dia = 0.0;
            $ganancia_promedio_unidad = 0.0;

            if ($unidadesDia > 0) {
                $sqlPorcentaje = "SELECT 
                    si.producto_id,
                    si.cantidad,
                    si.precio_venta_unitario,
                    si.costo_unitario,
                    si.porcentaje_ganancia,
                    p.precio
                    FROM salidas_inventario si
                    INNER JOIN productos p ON si.producto_id = p.id
                    WHERE DATE(si.fecha_salida) = " . $this->dbCurrentDate() . "
                    AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'" . $filtroSalidas . $filtroProductos;

                $queryPorcentaje = $this->db->prepare($sqlPorcentaje);
                $queryPorcentaje->execute();
                $ventasDia = $queryPorcentaje->fetchAll(PDO::FETCH_OBJ) ?: [];

                $porcentajeAcumulado = 0.0;
                $porcentajesPorProducto = [];
                foreach ($ventasDia as $venta) {
                    $cantidad = floatval($venta->cantidad ?? 0);
                    if ($cantidad <= 0) {
                        continue;
                    }

                    $porcentaje = $this->calcularPorcentajeGananciaVenta($venta);
                    $porcentajeAcumulado += $porcentaje * $cantidad;

                    $productoId = (int)($venta->producto_id ?? 0);
                    if ($productoId > 0) {
                        if (!isset($porcentajesPorProducto[$productoId])) {
                            $porcentajesPorProducto[$productoId] = ['cantidad' => 0.0, 'porcentaje_acumulado' => 0.0];
                        }
                        $porcentajesPorProducto[$productoId]['cantidad'] += $cantidad;
                        $porcentajesPorProducto[$productoId]['porcentaje_acumulado'] += $porcentaje * $cantidad;
                    }
                }

                $ganancia_dia = $unidadesDia > 0 ? ($porcentajeAcumulado / $unidadesDia) : 0.0;
                $ganancia_promedio_unidad = $ganancia_dia;
            }
            
            // Productos más vendidos del día
            $sql = "SELECT 
                p.id, p.codigo, p.nombre, p.imagen, COALESCE(c.nombre,'') as categoria,
                SUM(si.cantidad) as cantidad_vendida,
                MAX(si.fecha_salida) as ultima_venta,
                " . $exprPrecioUnitario . " as precio,
                SUM(" . $exprTotalVenta . ") as total_vendido,
                SUM(" . $exprTotalGanancia . ") as total_ganancia
                FROM salidas_inventario si
                INNER JOIN productos p ON si.producto_id = p.id
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE DATE(si.fecha_salida) = " . $this->dbCurrentDate() . " 
                AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'
                " . $filtroSalidas . $filtroProductos . "
                GROUP BY p.id, p.codigo, p.nombre, p.imagen, p.precio, c.nombre
                ORDER BY cantidad_vendida DESC, p.nombre ASC";
            
            $query = $this->db->prepare($sql);
            $query->execute();
            $productos_vendidos = $query->fetchAll(PDO::FETCH_OBJ);
            
            foreach ($productos_vendidos as $prod) {
                $prod->total_ganancia = floatval($prod->total_ganancia ?? 0);
                $productoId = (int)($prod->id ?? 0);
                if ($productoId > 0 && isset($porcentajesPorProducto[$productoId])) {
                    $datosProducto = $porcentajesPorProducto[$productoId];
                    $prod->porcentaje_ganancia = $datosProducto['cantidad'] > 0
                        ? ($datosProducto['porcentaje_acumulado'] / $datosProducto['cantidad'])
                        : 0.0;
                } else {
                    $prod->porcentaje_ganancia = 0.0;
                }
            }
            
            return [
                'fecha' => $hoy,
                'total_ventas' => intval($totales->total_ventas ?? 0),
                'unidades_vendidas' => intval($totales->unidades_vendidas ?? 0),
                'valor_total_ventas' => floatval($totales->valor_total_ventas ?? 0),
                'ganancia_dia' => floatval($ganancia_dia),
                'ganancia_total_dia' => floatval($totales->ganancia_total_ventas ?? 0),
                'ganancia_promedio_unidad_dia' => floatval($ganancia_promedio_unidad),
                'total_efectivo' => floatval($pagosDia->total_efectivo ?? 0),
                'total_transferencia' => floatval($pagosDia->total_transferencia ?? 0),
                'productos_vendidos' => $productos_vendidos
            ];
        } catch(PDOException $e) {
            error_log("PDOException en obtenerVentasDelDia: " . $e->getMessage());
            throw new Exception("Error en base de datos: " . $e->getMessage());
        } catch(Exception $e) {
            error_log("Exception en obtenerVentasDelDia: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Obtener resumen de ventas del mes
    public function obtenerVentasDelMes($periodo = null) {
        try {
            $filtroSalidas = $this->construirFiltroTenant('salidas_inventario', 'si');
            $filtroProductos = $this->construirFiltroTenant('productos', 'p');
            $exprTotalVenta = $this->exprTotalVenta('si', 'p');
            $exprTotalGanancia = $this->exprTotalGanancia('si', 'p');
            $exprPrecioUnitario = $this->columnaExiste('salidas_inventario', 'precio_venta_unitario')
                ? 'COALESCE(AVG(COALESCE(si.precio_venta_unitario, p.precio)), p.precio)'
                : 'p.precio';
            $periodoValido = is_string($periodo) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo);
            if ($periodoValido) {
                $inicioMes = $periodo . '-01';
            } else {
                $anio = date('Y');
                $mes = date('m');
                $inicioMes = "$anio-$mes-01";
            }
            $finMes = date('Y-m-t', strtotime($inicioMes));
            
            // Total de ventas del mes
            $sql = "SELECT 
                COUNT(*) as total_ventas,
                COALESCE(SUM(si.cantidad), 0) as unidades_vendidas,
                COALESCE(SUM(" . $exprTotalVenta . "), 0) as valor_total_ventas,
                COALESCE(SUM(" . $exprTotalGanancia . "), 0) as ganancia_total_ventas
                FROM salidas_inventario si
                INNER JOIN productos p ON si.producto_id = p.id
                WHERE DATE(si.fecha_salida) >= :inicio 
                AND DATE(si.fecha_salida) <= :fin 
                AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'" . $filtroSalidas . $filtroProductos;
            
            $query = $this->db->prepare($sql);
            if (!$query->execute([':inicio' => $inicioMes, ':fin' => $finMes])) {
                $error = $query->errorInfo();
                throw new Exception("Error al consultar totales de ventas del mes: " . ($error[2] ?? 'desconocido'));
            }
            $totales = $query->fetch(PDO::FETCH_OBJ);
            
            if (!$totales) {
                throw new Exception("No se pudieron obtener los totales de ventas");
            }
            
            $sqlPagosMes = "SELECT
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(si.metodo_pago), ''), 'efectivo')) = 'transferencia' THEN " . $exprTotalVenta . " ELSE 0 END), 0) AS total_transferencia,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(si.metodo_pago), ''), 'efectivo')) <> 'transferencia' THEN " . $exprTotalVenta . " ELSE 0 END), 0) AS total_efectivo
                FROM salidas_inventario si
                INNER JOIN productos p ON si.producto_id = p.id
                WHERE DATE(si.fecha_salida) >= :inicio AND DATE(si.fecha_salida) <= :fin
                AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'" . $filtroSalidas . $filtroProductos;
            $queryPagosMes = $this->db->prepare($sqlPagosMes);
            $queryPagosMes->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
            $pagosMes = $queryPagosMes->fetch(PDO::FETCH_OBJ);

            $unidadesMes = floatval($totales->unidades_vendidas ?? 0);
            $ganancia_mes = 0.0;
            $ganancia_promedio_unidad_mes = 0.0;

            if ($unidadesMes > 0) {
                $sqlPorcentajeMes = "SELECT 
                    si.producto_id,
                    si.cantidad,
                    si.precio_venta_unitario,
                    si.costo_unitario,
                    si.porcentaje_ganancia,
                    p.precio
                    FROM salidas_inventario si
                    INNER JOIN productos p ON si.producto_id = p.id
                    WHERE DATE(si.fecha_salida) >= :inicio 
                    AND DATE(si.fecha_salida) <= :fin 
                    AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'" . $filtroSalidas . $filtroProductos;

                $queryPorcentajeMes = $this->db->prepare($sqlPorcentajeMes);
                $queryPorcentajeMes->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
                $ventasMes = $queryPorcentajeMes->fetchAll(PDO::FETCH_OBJ) ?: [];

                $porcentajeAcumuladoMes = 0.0;
                $porcentajesPorProductoMes = [];
                foreach ($ventasMes as $venta) {
                    $cantidad = floatval($venta->cantidad ?? 0);
                    if ($cantidad <= 0) {
                        continue;
                    }

                    $porcentaje = $this->calcularPorcentajeGananciaVenta($venta);
                    $porcentajeAcumuladoMes += $porcentaje * $cantidad;

                    $productoId = (int)($venta->producto_id ?? 0);
                    if ($productoId > 0) {
                        if (!isset($porcentajesPorProductoMes[$productoId])) {
                            $porcentajesPorProductoMes[$productoId] = ['cantidad' => 0.0, 'porcentaje_acumulado' => 0.0];
                        }
                        $porcentajesPorProductoMes[$productoId]['cantidad'] += $cantidad;
                        $porcentajesPorProductoMes[$productoId]['porcentaje_acumulado'] += $porcentaje * $cantidad;
                    }
                }

                $ganancia_mes = $unidadesMes > 0 ? ($porcentajeAcumuladoMes / $unidadesMes) : 0.0;
                $ganancia_promedio_unidad_mes = $ganancia_mes;
            }
            
            // Productos más vendidos del mes
            $sql = "SELECT 
                p.id, p.codigo, p.nombre, p.imagen, COALESCE(c.nombre,'') as categoria,
                SUM(si.cantidad) as cantidad_vendida,
                MAX(si.fecha_salida) as ultima_venta,
                p.precio,
                SUM(" . $exprTotalVenta . ") as total_vendido,
                SUM(" . $exprTotalGanancia . ") as total_ganancia
                FROM salidas_inventario si
                INNER JOIN productos p ON si.producto_id = p.id
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE DATE(si.fecha_salida) >= :inicio 
                AND DATE(si.fecha_salida) <= :fin 
                AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'
                " . $filtroSalidas . $filtroProductos . "
                GROUP BY p.id, p.codigo, p.nombre, p.imagen, p.precio, c.nombre
                ORDER BY cantidad_vendida DESC
                LIMIT 10";
            
            $query = $this->db->prepare($sql);
            if (!$query->execute([':inicio' => $inicioMes, ':fin' => $finMes])) {
                $error = $query->errorInfo();
                throw new Exception("Error al consultar productos vendidos del mes: " . ($error[2] ?? 'desconocido'));
            }
            $productos_vendidos = $query->fetchAll(PDO::FETCH_OBJ) ?: [];
            
            foreach ($productos_vendidos as $prod) {
                $prod->total_ganancia = floatval($prod->total_ganancia ?? 0);
                $productoId = (int)($prod->id ?? 0);
                if ($productoId > 0 && isset($porcentajesPorProductoMes[$productoId])) {
                    $datosProducto = $porcentajesPorProductoMes[$productoId];
                    $prod->porcentaje_ganancia = $datosProducto['cantidad'] > 0
                        ? ($datosProducto['porcentaje_acumulado'] / $datosProducto['cantidad'])
                        : 0.0;
                } else {
                    $prod->porcentaje_ganancia = 0.0;
                }
            }
            
            // Ventas por día del mes
            $sql = "SELECT 
                DATE(si.fecha_salida) as fecha,
                COUNT(*) as ventas_dia,
                COALESCE(SUM(si.cantidad), 0) as unidades_dia,
                COALESCE(SUM(" . $exprTotalGanancia . "), 0) as ganancia_dia,
                COALESCE(SUM(" . $exprTotalVenta . "), 0) as total_dia
                FROM salidas_inventario si
                INNER JOIN productos p ON si.producto_id = p.id
                WHERE DATE(si.fecha_salida) >= :inicio 
                AND DATE(si.fecha_salida) <= :fin 
                AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'" . $filtroSalidas . $filtroProductos . "
                GROUP BY DATE(si.fecha_salida)
                ORDER BY fecha DESC";
            
            $query = $this->db->prepare($sql);
            if (!$query->execute([':inicio' => $inicioMes, ':fin' => $finMes])) {
                $error = $query->errorInfo();
                throw new Exception("Error al consultar ventas por día del mes: " . ($error[2] ?? 'desconocido'));
            }
            $ventas_por_dia = $query->fetchAll(PDO::FETCH_OBJ) ?: [];
            
            // Detalle diario por producto
            $sql = "SELECT 
                DATE(si.fecha_salida) as fecha,
                p.id as producto_id,
                p.codigo,
                p.nombre,
                p.imagen,
                COALESCE(c.nombre,'') as categoria,
                SUM(si.cantidad) as cantidad_vendida,
                MAX(si.fecha_salida) as ultima_venta,
                " . $exprPrecioUnitario . " as precio_unitario,
                SUM(" . $exprTotalGanancia . ") as ganancia_total,
                SUM(" . $exprTotalVenta . ") as total_vendido
                FROM salidas_inventario si
                INNER JOIN productos p ON si.producto_id = p.id
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE DATE(si.fecha_salida) >= :inicio 
                AND DATE(si.fecha_salida) <= :fin 
                AND LOWER(COALESCE(NULLIF(TRIM(IFNULL(si.tipo_salida,'')), ''), 'venta')) = 'venta'
                " . $filtroSalidas . $filtroProductos . "
                GROUP BY DATE(si.fecha_salida), p.id, p.codigo, p.nombre, p.imagen, p.precio, c.nombre
                ORDER BY fecha DESC, cantidad_vendida DESC, p.nombre ASC";
            
            $query = $this->db->prepare($sql);
            if (!$query->execute([':inicio' => $inicioMes, ':fin' => $finMes])) {
                $error = $query->errorInfo();
                throw new Exception("Error al consultar detalle diario del mes: " . ($error[2] ?? 'desconocido'));
            }
            $ventas_detalle = $query->fetchAll(PDO::FETCH_OBJ) ?: [];
            foreach ($ventas_detalle as $detalle) {
                $productoId = (int)($detalle->producto_id ?? 0);
                if ($productoId > 0 && isset($porcentajesPorProductoMes[$productoId])) {
                    $datosProducto = $porcentajesPorProductoMes[$productoId];
                    $detalle->porcentaje_ganancia = $datosProducto['cantidad'] > 0
                        ? ($datosProducto['porcentaje_acumulado'] / $datosProducto['cantidad'])
                        : 0.0;
                } else {
                    $detalle->porcentaje_ganancia = 0.0;
                }
            }
            
            // Formatear nombre del mes
            $meses = [
                'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
                'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
                'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
                'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
            ];
            $nombreMesEng = date('F', strtotime($inicioMes));
            $nombreMesEsp = $meses[$nombreMesEng] ?? $nombreMesEng;
            $mesTexto = $nombreMesEsp . ' ' . date('Y', strtotime($inicioMes));

            return [
                'mes' => $mesTexto,
                'inicio_mes' => $inicioMes,
                'fin_mes' => $finMes,
                'total_ventas' => intval($totales->total_ventas ?? 0),
                'unidades_vendidas' => intval($totales->unidades_vendidas ?? 0),
                'valor_total_ventas' => floatval($totales->valor_total_ventas ?? 0),
                'ganancia_mes' => floatval($totales->ganancia_total_ventas ?? 0),
                'ganancia_total_mes' => floatval($totales->ganancia_total_ventas ?? 0),
                'ganancia_promedio_unidad_mes' => floatval($ganancia_promedio_unidad_mes),
                'total_efectivo' => floatval($pagosMes->total_efectivo ?? 0),
                'total_transferencia' => floatval($pagosMes->total_transferencia ?? 0),
                'productos_vendidos' => $productos_vendidos,
                'ventas_por_dia' => $ventas_por_dia,
                'ventas_detalle' => $ventas_detalle
            ];
        } catch(PDOException $e) {
            error_log("PDOException en obtenerVentasDelMes: " . $e->getMessage());
            throw new Exception("Error en base de datos: " . $e->getMessage());
        } catch(Exception $e) {
            error_log("Exception en obtenerVentasDelMes: " . $e->getMessage());
            throw $e;
        }
    }

    public function obtenerPrimerMesConVentas() {
        try {
            $filtroSalidas = $this->construirFiltroTenant('salidas_inventario');
            $sql = "SELECT " . $this->dbDateFormat('MIN(fecha_salida)', '%Y-%m') . " AS primer_mes
                    FROM salidas_inventario
                    WHERE LOWER(COALESCE(NULLIF(TRIM(IFNULL(tipo_salida,'')), ''), 'venta')) = 'venta'" . $filtroSalidas;

            $query = $this->db->prepare($sql);
            if (!$query->execute()) {
                $error = $query->errorInfo();
                throw new Exception("Error al consultar el primer mes con ventas: " . ($error[2] ?? 'desconocido'));
            }

            $row = $query->fetch(PDO::FETCH_OBJ);
            return !empty($row->primer_mes) ? $row->primer_mes : date('Y-m');
        } catch(PDOException $e) {
            error_log("PDOException en obtenerPrimerMesConVentas: " . $e->getMessage());
            throw new Exception("Error en base de datos: " . $e->getMessage());
        }
    }
    
    // Obtener productos con información de ganancia
    public function obtenerProductosConGanancia() {
        try {
            $filtroProductos = $this->construirFiltroTenant('productos', 'p');
            $filtroEntradas = $this->construirFiltroTenant('entradas_inventario');
            $tieneDescuento = $this->columnaDescuentoProductos() !== '';
            $tieneVentaPorKilo = $this->columnaExiste('productos', 'venta_por_kilo');
            $tienePrecioOriginal = $this->columnaExiste('productos', 'precio_original');
            $columnaGanancia = $this->columnaPorcentajeGananciaProductos();
            $tienePorcentajeGanancia = $columnaGanancia !== '';
            $exprDescuentoBase = $this->exprDescuentoSimple('p');
            $exprDescuento = "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE {$exprDescuentoBase} END";
            $exprPrecioBase = $tienePrecioOriginal
                ? "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN p.precio_original IS NOT NULL AND p.precio_original > 0 THEN p.precio_original ELSE p.precio END END"
                : "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE COALESCE(p.precio, 0) END";
            $exprPrecioFinal = "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE COALESCE(p.precio, 0) END";

            $baseFields = "p.id,
                        p.codigo as codigo_producto,
                        p.codigo_barras,
                        p.nombre,
                        p.descripcion,
                        {$exprPrecioFinal} as precio_venta,
                        {$exprPrecioBase} as precio_original,
                        {$exprDescuento} as descuento_porcentaje,
                        COALESCE(p.stock, 0) as stock,
                        COALESCE(p.precio, 0) as precio,
                        p.imagen,
                        p.estado,
                        c.nombre as categoria_nombre,
                        " . ($tieneVentaPorKilo ? "COALESCE(p.venta_por_kilo, 0)" : "0") . " as venta_por_kilo";

            $tablas_existen = true;
            try {
                $this->db->query("SELECT 1 FROM entradas_inventario LIMIT 1");
            } catch(PDOException $e) {
                $tablas_existen = false;
            }

            if ($tablas_existen) {
                $exprUltimoPrecioCompra = "COALESCE((SELECT precio_compra FROM entradas_inventario e2 WHERE e2.producto_id = p.id{$filtroEntradas} ORDER BY fecha_entrada DESC LIMIT 1), 0)";
                $exprPorcentajeGanancia = $tienePorcentajeGanancia
                    ? "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN p.{$columnaGanancia} IS NOT NULL THEN COALESCE(p.{$columnaGanancia}, 0) ELSE CASE WHEN {$exprUltimoPrecioCompra} > 0 THEN ROUND(((p.precio - {$exprUltimoPrecioCompra}) * 100.0 / {$exprUltimoPrecioCompra}), 2) ELSE 0 END END END"
                    : "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN {$exprUltimoPrecioCompra} > 0 THEN ROUND(((p.precio - {$exprUltimoPrecioCompra}) * 100.0 / {$exprUltimoPrecioCompra}), 2) ELSE 0 END END";
                $sql = "SELECT 
                        {$baseFields},
                        COALESCE((SELECT SUM(precio_compra * cantidad) / NULLIF(SUM(cantidad), 0)
                                FROM entradas_inventario
                                WHERE producto_id = p.id{$filtroEntradas}), 0) as precio_compra_promedio,
                        {$exprUltimoPrecioCompra} as ultimo_precio_compra,
                        CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE ({$exprPrecioFinal} - {$exprUltimoPrecioCompra}) END as ganancia_unitaria,
                        {$exprPorcentajeGanancia} as porcentaje_ganancia
                        FROM productos p
                        LEFT JOIN categorias c ON p.categoria_id = c.id
                        WHERE p.estado = 1{$filtroProductos}
                        ORDER BY p.id DESC";
            } else {
                $sql = "SELECT 
                        {$baseFields},
                        0 as precio_compra_promedio,
                        0 as ultimo_precio_compra,
                        0 as ganancia_unitaria,
                        0 as porcentaje_ganancia
                        FROM productos p
                        LEFT JOIN categorias c ON p.categoria_id = c.id
                        WHERE p.estado = 1{$filtroProductos}
                        ORDER BY p.id DESC";
            }

            try {
                $query = $this->db->prepare($sql);
                $query->execute();
                return $query->fetchAll(PDO::FETCH_OBJ);
            } catch(PDOException $e) {
                error_log("Error en obtenerProductosConGanancia - query complejo: " . $e->getMessage());
                $sql = "SELECT 
                        {$baseFields},
                        0 as precio_compra_promedio,
                        0 as ultimo_precio_compra,
                        0 as ganancia_unitaria,
                        0 as porcentaje_ganancia
                        FROM productos p
                        LEFT JOIN categorias c ON p.categoria_id = c.id
                        WHERE p.estado = 1{$filtroProductos}
                        ORDER BY p.id DESC";
                $query = $this->db->prepare($sql);
                $query->execute();
                return $query->fetchAll(PDO::FETCH_OBJ);
            }
        } catch(PDOException $e) {
            error_log("Error en obtenerProductosConGanancia: " . $e->getMessage());
            return [];
        }
    }
}
?>
