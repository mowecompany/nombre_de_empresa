<?php
/**
 * Vencimiento
 * Control de fechas de vencimiento por lote de entrada.
 *
 * Reglas principales:
 *  - Una CATEGORIA se marca como perecedera (categorias.requiere_vencimiento = 1).
 *    Todos los productos de esa categoria exigen fecha de vencimiento en cada entrada.
 *  - Cada entrada de inventario guarda su propia fecha (entradas_inventario.fecha_vencimiento),
 *    de modo que un mismo producto puede tener varios lotes con fechas distintas.
 *  - El stock disponible del producto se reparte entre sus lotes por fecha mas proxima primero
 *    (criterio conservador: lo que esta mas cerca de vencer se muestra como existente).
 *  - Un lote vencido se ARCHIVA: se descuenta del inventario y queda registrado en
 *    lotes_vencidos para consulta posterior. Nunca se puede seguir vendiendo.
 */
class Vencimiento
{
    /** Dias de antelacion con los que un lote se considera "proximo a vencer". */
    const DIAS_AVISO = 7;
    /** Dias de antelacion con los que el sistema alerta todos los dias. */
    const DIAS_ALERTA = 5;

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

    private function agregarColumnaSiFalta(string $tabla, string $columna, string $defSqlite, string $defMysql): void
    {
        if ($this->columnaExiste($tabla, $columna)) {
            return;
        }
        try {
            $def = $this->esSqlite() ? $defSqlite : $defMysql;
            $this->db->exec("ALTER TABLE {$tabla} ADD COLUMN {$columna} {$def}");
            unset($this->columnasCache[$tabla . '.' . $columna]);
        } catch (Throwable $e) {
            error_log("Vencimiento: no se pudo agregar {$tabla}.{$columna}: " . $e->getMessage());
        }
    }

    /** Crea/actualiza las columnas y tablas necesarias. Idempotente. */
    public function asegurarEsquema(): void
    {
        if ($this->esquemaListo) {
            return;
        }
        $this->esquemaListo = true;

        $this->agregarColumnaSiFalta('categorias', 'requiere_vencimiento', 'INTEGER NOT NULL DEFAULT 0', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->agregarColumnaSiFalta('entradas_inventario', 'fecha_vencimiento', 'TEXT NULL', 'DATE NULL');
        $this->agregarColumnaSiFalta('entradas_inventario', 'cantidad_vencida', 'REAL NOT NULL DEFAULT 0', 'DECIMAL(14,3) NOT NULL DEFAULT 0');

        try {
            if ($this->esSqlite()) {
                $this->db->exec("CREATE TABLE IF NOT EXISTS lotes_vencidos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entrada_id INTEGER NULL,
                    producto_id INTEGER NOT NULL,
                    producto_nombre TEXT NOT NULL DEFAULT '',
                    producto_codigo TEXT NOT NULL DEFAULT '',
                    categoria_nombre TEXT NOT NULL DEFAULT '',
                    cantidad REAL NOT NULL DEFAULT 0,
                    fecha_vencimiento TEXT NULL,
                    fecha_archivado TEXT NOT NULL,
                    usuario_id INTEGER NULL,
                    empresa_id INTEGER NULL,
                    notas TEXT NULL
                )");
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_lotes_vencidos_producto ON lotes_vencidos (producto_id)");
            } else {
                $this->db->exec("CREATE TABLE IF NOT EXISTS lotes_vencidos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    entrada_id INT NULL,
                    producto_id INT NOT NULL,
                    producto_nombre VARCHAR(200) NOT NULL DEFAULT '',
                    producto_codigo VARCHAR(50) NOT NULL DEFAULT '',
                    categoria_nombre VARCHAR(120) NOT NULL DEFAULT '',
                    cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
                    fecha_vencimiento DATE NULL,
                    fecha_archivado DATETIME NOT NULL,
                    usuario_id INT NULL,
                    empresa_id INT NULL,
                    notas TEXT NULL,
                    INDEX idx_lotes_vencidos_producto (producto_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        } catch (Throwable $e) {
            error_log('Vencimiento: no se pudo crear lotes_vencidos: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Contexto (empresa)                                                  */
    /* ------------------------------------------------------------------ */

    private function empresaId(): int
    {
        foreach (['empresa_id', 'empresaId', 'empresaid'] as $key) {
            if (isset($_SESSION[$key]) && (int)$_SESSION[$key] > 0) {
                return (int)$_SESSION[$key];
            }
            if (isset($_SESSION['userData'][$key]) && (int)$_SESSION['userData'][$key] > 0) {
                return (int)$_SESSION['userData'][$key];
            }
        }
        return 0;
    }

    /* ------------------------------------------------------------------ */
    /* Reglas de obligatoriedad                                            */
    /* ------------------------------------------------------------------ */

    /** ¿La categoría exige fecha de vencimiento a sus productos? */
    public function categoriaRequiere(int $categoriaId): bool
    {
        $this->asegurarEsquema();
        if ($categoriaId <= 0 || !$this->columnaExiste('categorias', 'requiere_vencimiento')) {
            return false;
        }
        try {
            $stmt = $this->db->prepare('SELECT requiere_vencimiento FROM categorias WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $categoriaId]);
            return ((int)$stmt->fetchColumn()) === 1;
        } catch (Throwable $e) {
            error_log('Vencimiento categoriaRequiere: ' . $e->getMessage());
            return false;
        }
    }

    /** Datos de obligatoriedad de un producto (por su categoría). */
    public function productoRequiere(int $productoId): array
    {
        $this->asegurarEsquema();
        $base = ['producto_id' => $productoId, 'requiere' => false, 'categoria' => '', 'categoria_id' => 0];
        if ($productoId <= 0) {
            return $base;
        }
        try {
            $columna = $this->columnaExiste('categorias', 'requiere_vencimiento')
                ? 'COALESCE(c.requiere_vencimiento, 0)'
                : '0';
            $stmt = $this->db->prepare("SELECT p.categoria_id, COALESCE(c.nombre, '') AS categoria, {$columna} AS requiere
                FROM productos p
                LEFT JOIN categorias c ON c.id = p.categoria_id
                WHERE p.id = :id LIMIT 1");
            $stmt->execute([':id' => $productoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $base;
            }
            return [
                'producto_id' => $productoId,
                'requiere' => ((int)$row['requiere']) === 1,
                'categoria' => (string)$row['categoria'],
                'categoria_id' => (int)($row['categoria_id'] ?? 0)
            ];
        } catch (Throwable $e) {
            error_log('Vencimiento productoRequiere: ' . $e->getMessage());
            return $base;
        }
    }

    /** Lista de ids de productos que exigen fecha de vencimiento. */
    public function productosQueRequieren(): array
    {
        $this->asegurarEsquema();
        if (!$this->columnaExiste('categorias', 'requiere_vencimiento')) {
            return [];
        }
        try {
            $sql = 'SELECT p.id FROM productos p INNER JOIN categorias c ON c.id = p.categoria_id WHERE COALESCE(c.requiere_vencimiento, 0) = 1';
            $params = [];
            $empresaId = $this->empresaId();
            if ($empresaId > 0 && $this->columnaExiste('productos', 'empresa_id')) {
                $sql .= ' AND p.empresa_id = :empresa_id';
                $params[':empresa_id'] = $empresaId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
        } catch (Throwable $e) {
            error_log('Vencimiento productosQueRequieren: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Valida la fecha recibida en una entrada de inventario.
     * Devuelve la fecha normalizada (Y-m-d) o null si el producto no la exige.
     * Lanza Exception cuando el producto la exige y la fecha falta o no sirve.
     */
    public function validarFechaEntrada(int $productoId, $fecha): ?string
    {
        $this->asegurarEsquema();
        $info = $this->productoRequiere($productoId);
        $fechaTexto = trim((string)($fecha ?? ''));

        if ($fechaTexto === '') {
            if (!empty($info['requiere'])) {
                throw new Exception('Este producto es perecedero y necesita fecha de vencimiento para registrar la entrada.');
            }
            return null;
        }

        $normalizada = $this->normalizarFecha($fechaTexto);
        if ($normalizada === null) {
            throw new Exception('La fecha de vencimiento no es válida. Usa el formato día/mes/año.');
        }

        if ($normalizada < date('Y-m-d')) {
            throw new Exception('La fecha de vencimiento ya pasó. No se puede ingresar mercancía vencida.');
        }

        return $normalizada;
    }

    private function normalizarFecha(string $fecha): ?string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return null;
        }
        $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formatos as $formato) {
            $dt = DateTime::createFromFormat($formato, $fecha);
            if ($dt instanceof DateTime) {
                $errores = DateTime::getLastErrors();
                $conError = is_array($errores) && ((int)($errores['warning_count'] ?? 0) + (int)($errores['error_count'] ?? 0)) > 0;
                if (!$conError) {
                    return $dt->format('Y-m-d');
                }
            }
        }
        $ts = strtotime($fecha);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /* ------------------------------------------------------------------ */
    /* Lotes activos                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Lotes con fecha de vencimiento y existencias pendientes.
     * El stock del producto se reparte por fecha más próxima primero.
     */
    public function lotes(): array
    {
        $this->asegurarEsquema();
        if (!$this->columnaExiste('entradas_inventario', 'fecha_vencimiento')) {
            return [];
        }

        $empresaId = $this->empresaId();
        $tieneCantidadVencida = $this->columnaExiste('entradas_inventario', 'cantidad_vencida');
        $cantidadVencidaExpr = $tieneCantidadVencida ? 'COALESCE(e.cantidad_vencida, 0)' : '0';
        $tieneProveedor = $this->columnaExiste('entradas_inventario', 'proveedor_id');
        $proveedorSelect = $tieneProveedor ? "COALESCE(pr.nombre, '') AS proveedor" : "'' AS proveedor";
        $proveedorJoin = $tieneProveedor ? 'LEFT JOIN proveedores pr ON pr.id = e.proveedor_id' : '';
        $categoriaRequiereExpr = $this->columnaExiste('categorias', 'requiere_vencimiento')
            ? 'COALESCE(c.requiere_vencimiento, 0)'
            : '0';

        $sql = "SELECT e.id, e.producto_id, e.cantidad, {$cantidadVencidaExpr} AS cantidad_vencida,
                    e.fecha_vencimiento, e.fecha_entrada,
                    COALESCE(p.nombre, '') AS producto,
                    COALESCE(p.codigo, '') AS codigo,
                    COALESCE(p.imagen, '') AS imagen,
                    COALESCE(p.stock, 0) AS stock_producto,
                    COALESCE(c.nombre, '') AS categoria,
                    {$categoriaRequiereExpr} AS categoria_requiere,
                    {$proveedorSelect}
                FROM entradas_inventario e
                INNER JOIN productos p ON p.id = e.producto_id
                LEFT JOIN categorias c ON c.id = p.categoria_id
                {$proveedorJoin}
                WHERE e.fecha_vencimiento IS NOT NULL AND e.fecha_vencimiento <> ''";
        $params = [];
        if ($empresaId > 0 && $this->columnaExiste('entradas_inventario', 'empresa_id')) {
            $sql .= ' AND e.empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }
        $sql .= ' ORDER BY e.producto_id ASC, e.fecha_vencimiento ASC, e.id ASC';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Vencimiento lotes: ' . $e->getMessage());
            return [];
        }

        $hoy = new DateTime(date('Y-m-d'));
        $restantePorProducto = [];
        $resultado = [];

        foreach ($filas as $fila) {
            $productoId = (int)$fila['producto_id'];
            if (!array_key_exists($productoId, $restantePorProducto)) {
                $restantePorProducto[$productoId] = max(0.0, (float)$fila['stock_producto']);
            }

            $pendienteLote = max(0.0, (float)$fila['cantidad'] - (float)$fila['cantidad_vencida']);
            $asignado = min($pendienteLote, $restantePorProducto[$productoId]);
            $restantePorProducto[$productoId] -= $asignado;

            if ($asignado <= 0) {
                continue;
            }

            $fechaVence = $this->normalizarFecha((string)$fila['fecha_vencimiento']);
            if ($fechaVence === null) {
                continue;
            }
            $dias = (int)$hoy->diff(new DateTime($fechaVence))->format('%r%a');

            if ($dias < 0) {
                $estado = 'vencido';
            } elseif ($dias <= self::DIAS_ALERTA) {
                $estado = 'critico';
            } elseif ($dias <= self::DIAS_AVISO) {
                $estado = 'proximo';
            } else {
                $estado = 'vigente';
            }

            $resultado[] = [
                'entrada_id' => (int)$fila['id'],
                'producto_id' => $productoId,
                'producto' => (string)$fila['producto'],
                'codigo' => (string)$fila['codigo'],
                'imagen' => (string)$fila['imagen'],
                'categoria' => (string)$fila['categoria'],
                'proveedor' => (string)$fila['proveedor'],
                'cantidad' => round($asignado, 3),
                'fecha_vencimiento' => $fechaVence,
                'fecha_entrada' => (string)($fila['fecha_entrada'] ?? ''),
                'dias' => $dias,
                'estado' => $estado
            ];
        }

        usort($resultado, function ($a, $b) {
            if ($a['fecha_vencimiento'] === $b['fecha_vencimiento']) {
                return strcmp($a['producto'], $b['producto']);
            }
            return strcmp($a['fecha_vencimiento'], $b['fecha_vencimiento']);
        });

        return $resultado;
    }

    /** Contadores rápidos para avisos en pantalla. */
    public function resumen(): array
    {
        $lotes = $this->lotes();
        $vencidos = 0;
        $criticos = 0;
        $proximos = 0;
        foreach ($lotes as $lote) {
            if ($lote['estado'] === 'vencido') {
                $vencidos++;
            } elseif ($lote['estado'] === 'critico') {
                $criticos++;
            } elseif ($lote['estado'] === 'proximo') {
                $proximos++;
            }
        }
        return [
            'vencidos' => $vencidos,
            'criticos' => $criticos,
            'proximos' => $proximos,
            'por_vencer' => $criticos + $proximos,
            'total' => count($lotes),
            'dias_alerta' => self::DIAS_ALERTA,
            'dias_aviso' => self::DIAS_AVISO
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Archivado de lotes vencidos                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Archiva un lote vencido: lo descuenta del inventario y lo guarda en lotes_vencidos.
     * $inventario se usa para registrar la salida con la lógica normal del sistema.
     */
    public function archivar(int $entradaId, $inventario, array $ctx = []): array
    {
        $this->asegurarEsquema();

        $lote = null;
        foreach ($this->lotes() as $fila) {
            if ($fila['entrada_id'] === $entradaId) {
                $lote = $fila;
                break;
            }
        }

        if ($lote === null) {
            return ['success' => false, 'message' => 'El lote ya no tiene existencias o no existe.'];
        }
        if ($lote['estado'] !== 'vencido') {
            return ['success' => false, 'message' => 'Solo se pueden archivar lotes que ya están vencidos.'];
        }

        $usuarioId = (int)($ctx['usuario_id'] ?? 0);
        $cantidad = (float)$lote['cantidad'];

        $salida = $inventario->registrarSalida([
            'producto_id' => $lote['producto_id'],
            'cantidad' => $cantidad,
            'tipo_salida' => 'vencido',
            'referencia' => 'VENCIDO-' . $entradaId,
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'notas' => 'Producto vencido el ' . $lote['fecha_vencimiento'] . '. Archivado desde Productos a vencer.'
        ]);

        if (empty($salida['success'])) {
            return ['success' => false, 'message' => $salida['message'] ?? 'No se pudo descontar el lote del inventario.'];
        }

        try {
            if ($this->columnaExiste('entradas_inventario', 'cantidad_vencida')) {
                $stmt = $this->db->prepare('UPDATE entradas_inventario SET cantidad_vencida = COALESCE(cantidad_vencida, 0) + :cantidad WHERE id = :id');
                $stmt->execute([':cantidad' => $cantidad, ':id' => $entradaId]);
            }

            $columnas = ['entrada_id', 'producto_id', 'producto_nombre', 'producto_codigo', 'categoria_nombre',
                'cantidad', 'fecha_vencimiento', 'fecha_archivado', 'usuario_id', 'notas'];
            $valores = [':entrada_id', ':producto_id', ':producto_nombre', ':producto_codigo', ':categoria_nombre',
                ':cantidad', ':fecha_vencimiento', ':fecha_archivado', ':usuario_id', ':notas'];
            $params = [
                ':entrada_id' => $entradaId,
                ':producto_id' => $lote['producto_id'],
                ':producto_nombre' => $lote['producto'],
                ':producto_codigo' => $lote['codigo'],
                ':categoria_nombre' => $lote['categoria'],
                ':cantidad' => $cantidad,
                ':fecha_vencimiento' => $lote['fecha_vencimiento'],
                ':fecha_archivado' => date('Y-m-d H:i:s'),
                ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                ':notas' => 'Descontado del inventario por vencimiento.'
            ];

            $empresaId = (int)($ctx['empresa_id'] ?? $this->empresaId());
            if ($empresaId > 0) {
                $columnas[] = 'empresa_id';
                $valores[] = ':empresa_id';
                $params[':empresa_id'] = $empresaId;
            }

            $sql = 'INSERT INTO lotes_vencidos (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable $e) {
            error_log('Vencimiento archivar: ' . $e->getMessage());
            return ['success' => false, 'message' => 'El lote se descontó del inventario pero no se pudo archivar el registro.'];
        }

        return [
            'success' => true,
            'message' => 'Lote archivado y descontado del inventario.',
            'cantidad' => $cantidad,
            'producto' => $lote['producto']
        ];
    }

    /** Archiva de una vez todos los lotes vencidos con existencias. */
    public function archivarTodosVencidos($inventario, array $ctx = []): array
    {
        $archivados = 0;
        $errores = [];
        foreach ($this->lotes() as $lote) {
            if ($lote['estado'] !== 'vencido') {
                continue;
            }
            $res = $this->archivar($lote['entrada_id'], $inventario, $ctx);
            if (!empty($res['success'])) {
                $archivados++;
            } else {
                $errores[] = $lote['producto'] . ': ' . ($res['message'] ?? 'error');
            }
        }
        return [
            'success' => true,
            'archivados' => $archivados,
            'errores' => $errores,
            'message' => $archivados > 0
                ? ($archivados . ' lote(s) vencido(s) archivado(s) y descontado(s) del inventario.')
                : 'No hay lotes vencidos con existencias por archivar.'
        ];
    }

    /** Historial de lotes vencidos archivados. */
    public function archivados(int $limite = 300): array
    {
        $this->asegurarEsquema();
        try {
                $sql = 'SELECT l.*, p.imagen AS producto_imagen
                    FROM lotes_vencidos l
                    LEFT JOIN productos p ON p.id = l.producto_id
                    WHERE 1=1';
            $params = [];
            $empresaId = $this->empresaId();
            if ($empresaId > 0) {
                $sql .= ' AND (empresa_id IS NULL OR empresa_id = :empresa_id)';
                $params[':empresa_id'] = $empresaId;
            }
            $sql .= ' ORDER BY fecha_archivado DESC, id DESC LIMIT ' . max(1, min(1000, $limite));
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Vencimiento archivados: ' . $e->getMessage());
            return [];
        }
    }
}
