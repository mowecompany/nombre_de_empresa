<?php
/**
 * Presentacion
 * Motor de presentaciones multiples por producto (UNIDAD, PAQUETE, CAJA, BOLSA...).
 *
 * Reglas principales:
 *  - Cada producto puede activar el manejo de presentaciones (productos.maneja_presentaciones = 1).
 *  - Siempre existe una presentacion base (la mas pequena, factor_base = 1).
 *  - El stock se guarda FISICAMENTE por presentacion (producto_stock_presentacion).
 *  - productos.stock siempre se sincroniza al total en unidades base para no romper reportes.
 *  - Al vender una presentacion pequena sin existencias sueltas, se abre automaticamente
 *    la presentacion inmediatamente superior y se registra como CONVERSION (no como compra).
 */
class Presentacion
{
    private $db;
    private $esquemaListo = false;
    private $columnasCache = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades de driver / esquema                                      */
    /* ------------------------------------------------------------------ */

    private function esSqlite(): bool
    {
        try {
            return strtolower((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function columnaExiste(string $tabla, string $columna): bool
    {
        $key = $tabla . '.' . $columna;
        if (array_key_exists($key, $this->columnasCache)) {
            return $this->columnasCache[$key];
        }
        $existe = false;
        try {
            if ($this->esSqlite()) {
                $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
                $stmt = $this->db->query("PRAGMA table_info(\"{$tablaSegura}\")");
                foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $col) {
                    if (strcasecmp((string)($col['name'] ?? ''), $columna) === 0) {
                        $existe = true;
                        break;
                    }
                }
            } else {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
                $stmt->execute([':t' => $tabla, ':c' => $columna]);
                $existe = ((int)$stmt->fetchColumn()) > 0;
            }
        } catch (Throwable $e) {
            $existe = false;
        }
        $this->columnasCache[$key] = $existe;
        return $existe;
    }

    private function agregarColumnaSiFalta(string $tabla, string $columna, string $definicionSqlite, string $definicionMysql): void
    {
        if ($this->columnaExiste($tabla, $columna)) {
            return;
        }
        try {
            $def = $this->esSqlite() ? $definicionSqlite : $definicionMysql;
            $this->db->exec("ALTER TABLE {$tabla} ADD COLUMN {$columna} {$def}");
            unset($this->columnasCache[$tabla . '.' . $columna]);
        } catch (Throwable $e) {
            error_log("Presentacion: no se pudo agregar {$tabla}.{$columna}: " . $e->getMessage());
        }
    }

    /** Crea/actualiza las tablas y columnas necesarias. Es idempotente. */
    public function asegurarEsquema(): void
    {
        if ($this->esquemaListo) {
            return;
        }
        $this->esquemaListo = true;

        try {
            if ($this->esSqlite()) {
                $this->db->exec("CREATE TABLE IF NOT EXISTS producto_presentaciones (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    producto_id INTEGER NOT NULL,
                    nombre TEXT NOT NULL,
                    factor_padre REAL NOT NULL DEFAULT 1,
                    factor_base REAL NOT NULL DEFAULT 1,
                    precio_venta REAL NOT NULL DEFAULT 0,
                    precio_compra REAL NOT NULL DEFAULT 0,
                    nivel INTEGER NOT NULL DEFAULT 0,
                    es_base INTEGER NOT NULL DEFAULT 0,
                    orden INTEGER NOT NULL DEFAULT 0,
                    estado INTEGER NOT NULL DEFAULT 1,
                    empresa_id INTEGER NULL
                )");
                $this->db->exec("CREATE TABLE IF NOT EXISTS producto_stock_presentacion (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    producto_id INTEGER NOT NULL,
                    presentacion_id INTEGER NOT NULL,
                    cantidad REAL NOT NULL DEFAULT 0
                )");
                $this->db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_stock_presentacion_unico ON producto_stock_presentacion (producto_id, presentacion_id)");
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_presentaciones_producto ON producto_presentaciones (producto_id)");
            } else {
                $this->db->exec("CREATE TABLE IF NOT EXISTS producto_presentaciones (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    producto_id INT NOT NULL,
                    nombre VARCHAR(60) NOT NULL,
                    factor_padre DECIMAL(14,3) NOT NULL DEFAULT 1,
                    factor_base DECIMAL(14,3) NOT NULL DEFAULT 1,
                    precio_venta DECIMAL(14,2) NOT NULL DEFAULT 0,
                    precio_compra DECIMAL(14,2) NOT NULL DEFAULT 0,
                    nivel INT NOT NULL DEFAULT 0,
                    es_base TINYINT(1) NOT NULL DEFAULT 0,
                    orden INT NOT NULL DEFAULT 0,
                    estado TINYINT(1) NOT NULL DEFAULT 1,
                    empresa_id INT NULL,
                    INDEX idx_presentaciones_producto (producto_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $this->db->exec("CREATE TABLE IF NOT EXISTS producto_stock_presentacion (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    producto_id INT NOT NULL,
                    presentacion_id INT NOT NULL,
                    cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
                    UNIQUE KEY idx_stock_presentacion_unico (producto_id, presentacion_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        } catch (Throwable $e) {
            error_log('Presentacion: error creando tablas: ' . $e->getMessage());
        }

        $this->agregarColumnaSiFalta('productos', 'maneja_presentaciones', 'INTEGER NOT NULL DEFAULT 0', 'TINYINT(1) NOT NULL DEFAULT 0');
        foreach (['entradas_inventario', 'salidas_inventario', 'movimientos_inventario'] as $tabla) {
            $this->agregarColumnaSiFalta($tabla, 'presentacion_id', 'INTEGER NULL', 'INT NULL');
            $this->agregarColumnaSiFalta($tabla, 'cantidad_presentacion', 'REAL NULL', 'DECIMAL(14,3) NULL');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    public function manejaPresentaciones(int $productoId): bool
    {
        $this->asegurarEsquema();
        if (!$this->columnaExiste('productos', 'maneja_presentaciones')) {
            return false;
        }
        try {
            $stmt = $this->db->prepare("SELECT maneja_presentaciones FROM productos WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $productoId]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Presentaciones ordenadas de la mas pequena a la mas grande, con su stock fisico. */
    public function listar(int $productoId): array
    {
        $this->asegurarEsquema();
        try {
            $sql = "SELECT p.*, COALESCE(s.cantidad, 0) AS cantidad
                    FROM producto_presentaciones p
                    LEFT JOIN producto_stock_presentacion s
                        ON s.presentacion_id = p.id AND s.producto_id = p.producto_id
                    WHERE p.producto_id = :pid AND p.estado = 1
                    ORDER BY p.factor_base ASC, p.id ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':pid' => $productoId]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('Presentacion listar: ' . $e->getMessage());
            return [];
        }

        return array_map(function ($f) {
            return [
                'id' => (int)$f['id'],
                'producto_id' => (int)$f['producto_id'],
                'nombre' => (string)$f['nombre'],
                'factor_padre' => (float)$f['factor_padre'],
                'factor_base' => (float)$f['factor_base'] > 0 ? (float)$f['factor_base'] : 1.0,
                'precio_venta' => (float)$f['precio_venta'],
                'precio_compra' => (float)$f['precio_compra'],
                'nivel' => (int)$f['nivel'],
                'es_base' => (int)$f['es_base'] === 1,
                'cantidad' => (float)$f['cantidad'],
            ];
        }, $filas);
    }

    public function obtener(int $presentacionId): ?array
    {
        $this->asegurarEsquema();
        try {
            $stmt = $this->db->prepare("SELECT producto_id FROM producto_presentaciones WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $presentacionId]);
            $productoId = (int)$stmt->fetchColumn();
            if ($productoId <= 0) {
                return null;
            }
            foreach ($this->listar($productoId) as $pres) {
                if ($pres['id'] === $presentacionId) {
                    return $pres;
                }
            }
        } catch (Throwable $e) {
            error_log('Presentacion obtener: ' . $e->getMessage());
        }
        return null;
    }

    /** Convierte una cantidad de una presentacion a unidades base. */
    public function aBase(int $presentacionId, float $cantidad): float
    {
        $pres = $this->obtener($presentacionId);
        if (!$pres) {
            return $cantidad;
        }
        return $cantidad * $pres['factor_base'];
    }

    /** Desglose fisico legible: "9 PAQUETES + 27 UNIDADES (297 UNIDADES)". */
    public function resumenStock(int $productoId): array
    {
        $presentaciones = $this->listar($productoId);
        if (!$presentaciones) {
            return ['presentaciones' => [], 'total_base' => 0.0, 'texto' => '', 'base_nombre' => ''];
        }

        $totalBase = 0.0;
        $partes = [];
        foreach (array_reverse($presentaciones) as $pres) {
            $totalBase += $pres['cantidad'] * $pres['factor_base'];
            if ($pres['cantidad'] > 0) {
                $partes[] = $this->formatoCantidad($pres['cantidad']) . ' ' . strtoupper($pres['nombre']);
            }
        }
        $base = $presentaciones[0];
        $texto = $partes ? implode(' + ', $partes) : '0 ' . strtoupper($base['nombre']);
        $texto .= ' (' . $this->formatoCantidad($totalBase) . ' ' . strtoupper($base['nombre']) . ')';

        return [
            'presentaciones' => $presentaciones,
            'total_base' => $totalBase,
            'texto' => $texto,
            'base_nombre' => strtoupper($base['nombre']),
        ];
    }

    private function formatoCantidad(float $valor): string
    {
        if (abs($valor - round($valor)) < 0.001) {
            return (string)(int)round($valor);
        }
        return rtrim(rtrim(number_format($valor, 3, '.', ''), '0'), '.');
    }

    /* ------------------------------------------------------------------ */
    /* Configuracion                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Guarda la configuracion de presentaciones de un producto.
     * $filas viene ordenada de la MAS PEQUENA a la MAS GRANDE:
     *   [ ['id'=>?, 'nombre'=>'UNIDAD', 'factor_padre'=>1, 'precio_venta'=>0, 'precio_compra'=>0], ... ]
     */
    public function guardarConfiguracion(int $productoId, array $filas, bool $maneja, int $empresaId = 0): array
    {
        $this->asegurarEsquema();

        if ($productoId <= 0) {
            return ['success' => false, 'message' => 'Producto no valido'];
        }

        if (!$maneja) {
            try {
                $this->db->prepare("UPDATE productos SET maneja_presentaciones = 0 WHERE id = :id")
                    ->execute([':id' => $productoId]);
                $this->db->prepare("UPDATE producto_presentaciones SET estado = 0 WHERE producto_id = :id")
                    ->execute([':id' => $productoId]);
            } catch (Throwable $e) {
                return ['success' => false, 'message' => 'No se pudo desactivar las presentaciones: ' . $e->getMessage()];
            }
            return ['success' => true, 'message' => 'Presentaciones desactivadas'];
        }

        $limpias = [];
        foreach ($filas as $indice => $fila) {
            $nombre = strtoupper(trim((string)($fila['nombre'] ?? '')));
            if ($nombre === '') {
                continue;
            }
            $factorPadre = $indice === 0 ? 1.0 : (float)str_replace(',', '.', (string)($fila['factor_padre'] ?? 0));
            if ($indice > 0 && $factorPadre < 2) {
                return ['success' => false, 'message' => "La equivalencia de {$nombre} debe ser 2 o mas respecto a la presentacion anterior"];
            }
            $limpias[] = [
                'id' => (int)($fila['id'] ?? 0),
                'nombre' => $nombre,
                'factor_padre' => $factorPadre,
                'precio_venta' => function_exists('redondearPrecioVenta')
                    ? redondearPrecioVenta($fila['precio_venta'] ?? 0)
                    : (float)str_replace(',', '.', (string)($fila['precio_venta'] ?? 0)),
                'precio_compra' => (float)str_replace(',', '.', (string)($fila['precio_compra'] ?? 0)),
            ];
        }

        if (count($limpias) < 1) {
            return ['success' => false, 'message' => 'Debes definir al menos la presentacion base (por ejemplo UNIDAD)'];
        }

        $stockPrevio = 0.0;
        try {
            $stmt = $this->db->prepare("SELECT stock FROM productos WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $productoId]);
            $stockPrevio = (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $stockPrevio = 0.0;
        }

        $existentes = $this->listar($productoId);
        $eraActivo = $this->manejaPresentaciones($productoId) && !empty($existentes);

        try {
            $factorBase = 1.0;
            $conservados = [];
            foreach ($limpias as $indice => $fila) {
                $factorBase = $indice === 0 ? 1.0 : $factorBase * $fila['factor_padre'];
                $params = [
                    ':producto_id' => $productoId,
                    ':nombre' => $fila['nombre'],
                    ':factor_padre' => $indice === 0 ? 1 : $fila['factor_padre'],
                    ':factor_base' => $factorBase,
                    ':precio_venta' => $fila['precio_venta'],
                    ':precio_compra' => $fila['precio_compra'],
                    ':nivel' => $indice,
                    ':es_base' => $indice === 0 ? 1 : 0,
                    ':orden' => $indice,
                ];

                if ($fila['id'] > 0) {
                    $params[':id'] = $fila['id'];
                    $sql = "UPDATE producto_presentaciones SET nombre = :nombre, factor_padre = :factor_padre,
                            factor_base = :factor_base, precio_venta = :precio_venta, precio_compra = :precio_compra,
                            nivel = :nivel, es_base = :es_base, orden = :orden, estado = 1
                            WHERE id = :id AND producto_id = :producto_id";
                    $this->db->prepare($sql)->execute($params);
                    $conservados[] = $fila['id'];
                    continue;
                }

                $columnas = 'producto_id, nombre, factor_padre, factor_base, precio_venta, precio_compra, nivel, es_base, orden, estado';
                $valores = ':producto_id, :nombre, :factor_padre, :factor_base, :precio_venta, :precio_compra, :nivel, :es_base, :orden, 1';
                if ($this->columnaExiste('producto_presentaciones', 'empresa_id')) {
                    $columnas .= ', empresa_id';
                    $valores .= ', :empresa_id';
                    $params[':empresa_id'] = $empresaId > 0 ? $empresaId : null;
                }
                $this->db->prepare("INSERT INTO producto_presentaciones ({$columnas}) VALUES ({$valores})")->execute($params);
                $conservados[] = (int)$this->db->lastInsertId();
            }

            // Las presentaciones eliminadas pasan su stock fisico a unidades base.
            $baseId = $conservados[0] ?? 0;
            foreach ($existentes as $pres) {
                if (in_array($pres['id'], $conservados, true)) {
                    continue;
                }
                if ($pres['cantidad'] > 0 && $baseId > 0) {
                    $this->sumarStock($productoId, $baseId, $pres['cantidad'] * $pres['factor_base']);
                }
                $this->db->prepare("UPDATE producto_presentaciones SET estado = 0 WHERE id = :id")->execute([':id' => $pres['id']]);
                $this->db->prepare("DELETE FROM producto_stock_presentacion WHERE producto_id = :pid AND presentacion_id = :id")
                    ->execute([':pid' => $productoId, ':id' => $pres['id']]);
            }

            $this->db->prepare("UPDATE productos SET maneja_presentaciones = 1 WHERE id = :id")->execute([':id' => $productoId]);

            // Primera activacion: el stock actual del producto pasa a la presentacion base.
            if (!$eraActivo && $baseId > 0 && $stockPrevio > 0) {
                $this->fijarStock($productoId, $baseId, $stockPrevio);
            }

            $this->sincronizarStock($productoId);
        } catch (Throwable $e) {
            error_log('Presentacion guardarConfiguracion: ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo guardar las presentaciones: ' . $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Presentaciones guardadas correctamente', 'presentaciones' => $this->listar($productoId)];
    }

    /* ------------------------------------------------------------------ */
    /* Stock fisico                                                        */
    /* ------------------------------------------------------------------ */

    private function fijarStock(int $productoId, int $presentacionId, float $cantidad): void
    {
        $stmt = $this->db->prepare("SELECT id FROM producto_stock_presentacion WHERE producto_id = :pid AND presentacion_id = :sid LIMIT 1");
        $stmt->execute([':pid' => $productoId, ':sid' => $presentacionId]);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) {
            $this->db->prepare("UPDATE producto_stock_presentacion SET cantidad = :c WHERE id = :id")
                ->execute([':c' => max(0, $cantidad), ':id' => $id]);
            return;
        }
        $this->db->prepare("INSERT INTO producto_stock_presentacion (producto_id, presentacion_id, cantidad) VALUES (:pid, :sid, :c)")
            ->execute([':pid' => $productoId, ':sid' => $presentacionId, ':c' => max(0, $cantidad)]);
    }

    private function sumarStock(int $productoId, int $presentacionId, float $delta): void
    {
        $stmt = $this->db->prepare("SELECT cantidad FROM producto_stock_presentacion WHERE producto_id = :pid AND presentacion_id = :sid LIMIT 1");
        $stmt->execute([':pid' => $productoId, ':sid' => $presentacionId]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC);
        $nuevo = ($actual === false ? 0.0 : (float)$actual['cantidad']) + $delta;
        $this->fijarStock($productoId, $presentacionId, $nuevo);
    }

    /** Recalcula productos.stock como el total en unidades base. */
    public function sincronizarStock(int $productoId): float
    {
        $total = 0.0;
        foreach ($this->listar($productoId) as $pres) {
            $total += $pres['cantidad'] * $pres['factor_base'];
        }
        try {
            $this->db->prepare("UPDATE productos SET stock = :stock WHERE id = :id")
                ->execute([':stock' => $total, ':id' => $productoId]);
        } catch (Throwable $e) {
            error_log('Presentacion sincronizarStock: ' . $e->getMessage());
        }
        return $total;
    }

    /* ------------------------------------------------------------------ */
    /* Movimientos de conversion                                           */
    /* ------------------------------------------------------------------ */

    private function registrarConversion(int $productoId, string $descripcion, float $cantidad, ?int $usuarioId, ?int $empresaId, ?int $presentacionId): void
    {
        try {
            $columnas = ['producto_id', 'tipo_movimiento', 'cantidad', 'stock_anterior', 'stock_nuevo', 'usuario_id', 'descripcion', 'fecha_movimiento'];
            $valores = [':producto_id', ':tipo', ':cantidad', ':stock_anterior', ':stock_nuevo', ':usuario_id', ':descripcion', $this->esSqlite() ? "datetime('now','localtime')" : 'NOW()'];
            $params = [
                ':producto_id' => $productoId,
                ':tipo' => 'ajuste',
                ':cantidad' => $cantidad,
                ':stock_anterior' => 0,
                ':stock_nuevo' => 0,
                ':usuario_id' => $usuarioId ?: null,
                ':descripcion' => $descripcion,
            ];
            if ($this->columnaExiste('movimientos_inventario', 'empresa_id')) {
                $columnas[] = 'empresa_id';
                $valores[] = ':empresa_id';
                $params[':empresa_id'] = $empresaId ?: null;
            }
            if ($this->columnaExiste('movimientos_inventario', 'presentacion_id')) {
                $columnas[] = 'presentacion_id';
                $valores[] = ':presentacion_id';
                $params[':presentacion_id'] = $presentacionId ?: null;
            }

            $sql = "INSERT INTO movimientos_inventario (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $valores) . ")";
            try {
                $this->db->prepare($sql)->execute($params);
            } catch (Throwable $e) {
                // Tablas antiguas de SQLite sin id autoincremental.
                $siguiente = (int)$this->db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM movimientos_inventario")->fetchColumn();
                $columnas[] = 'id';
                $valores[] = ':id';
                $params[':id'] = $siguiente;
                $sql = "INSERT INTO movimientos_inventario (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $valores) . ")";
                $this->db->prepare($sql)->execute($params);
            }
        } catch (Throwable $e) {
            error_log('Presentacion registrarConversion: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Operaciones                                                         */
    /* ------------------------------------------------------------------ */

    /** Entrada fisica de N unidades de una presentacion. Devuelve unidades base agregadas. */
    public function ingresar(int $productoId, int $presentacionId, float $cantidad): float
    {
        $this->asegurarEsquema();
        $presentaciones = $this->listar($productoId);
        $target = $this->buscar($presentaciones, $presentacionId);
        if (!$target) {
            throw new Exception('La presentacion seleccionada no existe');
        }
        if ($cantidad <= 0) {
            throw new Exception('La cantidad debe ser mayor a cero');
        }
        $this->sumarStock($productoId, $presentacionId, $cantidad);
        $this->sincronizarStock($productoId);
        return $cantidad * $target['factor_base'];
    }

    /**
     * Salida fisica de N unidades de una presentacion, abriendo presentaciones
     * superiores automaticamente si hace falta. Devuelve unidades base descontadas.
     */
    public function descontar(int $productoId, int $presentacionId, float $cantidad, array $ctx = []): float
    {
        $this->asegurarEsquema();
        $presentaciones = $this->listar($productoId);
        $target = $this->buscar($presentaciones, $presentacionId);
        if (!$target) {
            throw new Exception('La presentacion seleccionada no existe');
        }
        if ($cantidad <= 0) {
            throw new Exception('La cantidad debe ser mayor a cero');
        }

        $necesarioBase = $cantidad * $target['factor_base'];
        $totalBase = 0.0;
        foreach ($presentaciones as $pres) {
            $totalBase += $pres['cantidad'] * $pres['factor_base'];
        }
        if ($totalBase + 0.0001 < $necesarioBase) {
            throw new Exception('Stock insuficiente. Disponible: ' . $this->formatoCantidad($totalBase) . ' ' . strtoupper($presentaciones[0]['nombre']));
        }

        $indiceTarget = $this->indice($presentaciones, $presentacionId);
        $guardas = 0;

        // Si no hay paquetes físicos, usar unidades base sueltas para completar
        // la venta de la presentación elegida.
        $cantidadTarget = (float)$presentaciones[$indiceTarget]['cantidad'];
        if ($indiceTarget > 0 && $cantidadTarget + 0.0001 < $cantidad) {
            $faltantePresentacion = $cantidad - $cantidadTarget;
            $cantidadBaseNecesaria = $faltantePresentacion * (float)$target['factor_base'];
            $indiceBase = 0;
            if ($presentaciones[$indiceBase]['cantidad'] + 0.0001 >= $cantidadBaseNecesaria) {
                if ($cantidadTarget > 0) {
                    $this->fijarStock($productoId, $presentacionId, 0);
                }
                $this->fijarStock(
                    $productoId,
                    (int)$presentaciones[$indiceBase]['id'],
                    $presentaciones[$indiceBase]['cantidad'] - $cantidadBaseNecesaria
                );
                $this->sincronizarStock($productoId);
                return $necesarioBase;
            }
        }

        while ($presentaciones[$indiceTarget]['cantidad'] + 0.0001 < $cantidad) {
            if (++$guardas > 5000) {
                throw new Exception('No se pudo completar la conversion de presentaciones');
            }
            $indiceOrigen = -1;
            for ($i = $indiceTarget + 1; $i < count($presentaciones); $i++) {
                if ($presentaciones[$i]['cantidad'] >= 1) {
                    $indiceOrigen = $i;
                    break;
                }
            }
            if ($indiceOrigen === -1) {
                throw new Exception('Stock insuficiente para completar la venta con las presentaciones disponibles');
            }
            $this->abrirUno($presentaciones, $indiceOrigen, $ctx, true);
        }

        $nuevo = $presentaciones[$indiceTarget]['cantidad'] - $cantidad;
        $this->fijarStock($productoId, $presentacionId, $nuevo);
        $this->sincronizarStock($productoId);
        return $necesarioBase;
    }

    /**
     * Apertura manual: convierte N unidades de una presentacion en su presentacion
     * inmediatamente inferior (por ejemplo 1 PAQUETE -> 30 UNIDADES).
     */
    public function abrir(int $productoId, int $presentacionId, float $cantidad, array $ctx = []): array
    {
        $this->asegurarEsquema();
        $presentaciones = $this->listar($productoId);
        $indice = $this->indice($presentaciones, $presentacionId);
        if ($indice < 0) {
            return ['success' => false, 'message' => 'La presentacion seleccionada no existe'];
        }
        if ($indice === 0) {
            return ['success' => false, 'message' => 'La presentacion base no se puede abrir'];
        }
        $cantidad = floor($cantidad);
        if ($cantidad < 1) {
            return ['success' => false, 'message' => 'Indica cuantas unidades quieres abrir'];
        }
        if ($presentaciones[$indice]['cantidad'] < $cantidad) {
            return [
                'success' => false,
                'message' => 'Solo tienes ' . $this->formatoCantidad($presentaciones[$indice]['cantidad']) . ' ' . strtoupper($presentaciones[$indice]['nombre']) . ' en existencia',
            ];
        }

        $enTransaccion = false;
        try {
            if ($this->esSqlite()) {
                $this->db->exec('BEGIN IMMEDIATE');
            } else {
                $this->db->beginTransaction();
            }
            $enTransaccion = true;

            for ($i = 0; $i < $cantidad; $i++) {
                $this->abrirUno($presentaciones, $indice, $ctx, false);
            }
            $this->sincronizarStock($productoId);

            if ($this->esSqlite()) {
                $this->db->exec('COMMIT');
            } else {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($enTransaccion) {
                try {
                    if ($this->esSqlite()) {
                        $this->db->exec('ROLLBACK');
                    } else {
                        $this->db->rollBack();
                    }
                } catch (Throwable $ex) {
                }
            }
            error_log('Presentacion abrir: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resumen = $this->resumenStock($productoId);
        return [
            'success' => true,
            'message' => 'Apertura registrada. Existencias: ' . $resumen['texto'],
            'resumen' => $resumen,
        ];
    }

    /** Abre UNA unidad de $presentaciones[$indice] hacia la presentacion inferior. */
    private function abrirUno(array &$presentaciones, int $indice, array $ctx, bool $automatica): void
    {
        if ($indice <= 0) {
            throw new Exception('No hay una presentacion menor para abrir');
        }
        $origen = $presentaciones[$indice];
        $destino = $presentaciones[$indice - 1];
        if ($origen['cantidad'] < 1) {
            throw new Exception('No hay ' . strtoupper($origen['nombre']) . ' disponibles para abrir');
        }
        $equivalencia = $destino['factor_base'] > 0 ? ($origen['factor_base'] / $destino['factor_base']) : 0;
        if ($equivalencia <= 0) {
            throw new Exception('La equivalencia entre presentaciones no es valida');
        }

        $productoId = (int)$origen['producto_id'];
        $presentaciones[$indice]['cantidad'] = $origen['cantidad'] - 1;
        $presentaciones[$indice - 1]['cantidad'] = $destino['cantidad'] + $equivalencia;
        $this->fijarStock($productoId, $origen['id'], $presentaciones[$indice]['cantidad']);
        $this->fijarStock($productoId, $destino['id'], $presentaciones[$indice - 1]['cantidad']);

        $prefijo = $automatica ? 'CONVERSION AUTOMATICA' : 'APERTURA MANUAL';
        $this->registrarConversion(
            $productoId,
            $prefijo . ': 1 ' . strtoupper($origen['nombre']) . ' -> ' . $this->formatoCantidad($equivalencia) . ' ' . strtoupper($destino['nombre']),
            $equivalencia,
            isset($ctx['usuario_id']) ? (int)$ctx['usuario_id'] : null,
            isset($ctx['empresa_id']) ? (int)$ctx['empresa_id'] : null,
            (int)$origen['id']
        );
    }

    private function buscar(array $presentaciones, int $id): ?array
    {
        foreach ($presentaciones as $pres) {
            if ($pres['id'] === $id) {
                return $pres;
            }
        }
        return null;
    }

    private function indice(array $presentaciones, int $id): int
    {
        foreach ($presentaciones as $i => $pres) {
            if ($pres['id'] === $id) {
                return $i;
            }
        }
        return -1;
    }
}
