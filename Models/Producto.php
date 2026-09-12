<?php
require_once __DIR__ . '/../Config/Config.php';
if (!function_exists('dbColumnExists')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}

class Producto {
    private $id;
    private $codigo;
    private $codigo_barras;
    private $nombre;
    private $descripcion;
    private $precio;
    private $imagen;
    private $categoria_id;
    private $estado;
    private $stock;
    private $descuento_porcentaje;
    private $porcentaje_ganancia;
    private $color;
    private $db;
    private $table = 'productos';
    
    public function __construct($db) {
        $this->db = $db;
        $this->repararEsquemaProductosSiEsNecesario();
        $this->inicializarColumnasDescuento();
        $this->inicializarColumnasGanancia();
        $this->inicializarColumnaColor();
        $this->inicializarColumnaVentaPorKilo();
    }

    private function esSqlite(): bool {
        try {
            return strtolower((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function esMysql(): bool {
        try {
            return strtolower((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function desactivarRestriccionesForaneas(): void {
        try {
            if ($this->esSqlite()) {
                $this->db->exec('PRAGMA foreign_keys = OFF');
            }
            if ($this->esMysql()) {
                $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
            }
        } catch (Throwable $e) {
            error_log('No se pudo desactivar restricciones foráneas: ' . $e->getMessage());
        }
    }

    private function activarRestriccionesForaneas(): void {
        try {
            if ($this->esSqlite()) {
                $this->db->exec('PRAGMA foreign_keys = ON');
            }
            if ($this->esMysql()) {
                $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        } catch (Throwable $e) {
            error_log('No se pudo activar restricciones foráneas: ' . $e->getMessage());
        }
    }

    private function repararEsquemaProductosSiEsNecesario(): void {
        try {
            if (!$this->esSqlite()) {
                return;
            }

            $stmt = $this->db->query('PRAGMA table_info(productos)');
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $idRow = null;
            foreach ($columnas as $col) {
                if (strtolower(trim((string)($col['name'] ?? ''))) === 'id') {
                    $idRow = $col;
                    break;
                }
            }

            $idEsPkAutoincremental = false;
            if ($idRow !== null) {
                $pk = (int)($idRow['pk'] ?? 0);
                $type = strtoupper((string)($idRow['type'] ?? ''));
                $idEsPkAutoincremental = $pk === 1 && preg_match('/INT/', $type) === 1;
            }

            if ($idEsPkAutoincremental) {
                return;
            }

            $this->db->exec('PRAGMA foreign_keys = OFF');
            $this->db->exec('DROP TABLE IF EXISTS productos_repair');
            $this->db->exec("CREATE TABLE productos_repair (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo TEXT DEFAULT NULL,
                codigo_barras TEXT DEFAULT NULL,
                categoria_id INTEGER DEFAULT NULL,
                nombre TEXT NOT NULL,
                descripcion TEXT DEFAULT NULL,
                precio NUMERIC NOT NULL,
                descuento_porcentaje NUMERIC NOT NULL DEFAULT 0.00,
                precio_original NUMERIC DEFAULT NULL,
                stock INTEGER DEFAULT 0,
                imagen TEXT DEFAULT NULL,
                estado INTEGER DEFAULT 1,
                fecha_creacion timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                empresa_id INTEGER NOT NULL,
                usuario_id INTEGER DEFAULT NULL,
                porcentaje_ganancia NUMERIC NOT NULL DEFAULT 0.00,
                color TEXT DEFAULT NULL
            )");
            $this->db->exec("INSERT INTO productos_repair (id, codigo, codigo_barras, categoria_id, nombre, descripcion, precio, descuento_porcentaje, precio_original, stock, imagen, estado, fecha_creacion, empresa_id, usuario_id, porcentaje_ganancia, color) SELECT id, codigo, codigo_barras, categoria_id, nombre, descripcion, precio, descuento_porcentaje, precio_original, stock, imagen, estado, fecha_creacion, empresa_id, usuario_id, porcentaje_ganancia, color FROM productos");
            $this->db->exec('DROP TABLE productos');
            $this->db->exec('ALTER TABLE productos_repair RENAME TO productos');
            $this->db->exec('PRAGMA foreign_keys = ON');
        } catch (Throwable $e) {
            error_log('No se pudo reparar el esquema de productos: ' . $e->getMessage());
        }
    }

    private function agregarColumnaSiNoExiste(string $tabla, string $columna, string $sql): void {
        try {
            if (!dbColumnExists($this->db, $tabla, $columna)) {
                $this->db->exec($sql);
            }
        } catch (\Throwable $e) {
            error_log('No se pudo agregar columna ' . $columna . ' en ' . $tabla . ': ' . $e->getMessage());
        }
    }

    private function inicializarColumnasGanancia(): void {
        try {
            $driver = strtolower((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME));
            $esSqlite = $driver === 'sqlite';

            $sqlDescuentoGanacia = $esSqlite
                ? 'ALTER TABLE productos ADD COLUMN descuento_ganacia DECIMAL(10,2) NOT NULL DEFAULT 0'
                : 'ALTER TABLE productos ADD COLUMN descuento_ganacia DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER precio';
            $sqlPorcentajeGanancia = $esSqlite
                ? 'ALTER TABLE productos ADD COLUMN porcentaje_ganancia DECIMAL(10,2) NOT NULL DEFAULT 0'
                : 'ALTER TABLE productos ADD COLUMN porcentaje_ganancia DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER descuento_ganacia';

            $this->agregarColumnaSiNoExiste('productos', 'descuento_ganacia', $sqlDescuentoGanacia);
            $this->agregarColumnaSiNoExiste('productos', 'porcentaje_ganancia', $sqlPorcentajeGanancia);
        } catch (\Throwable $e) {
            error_log('No se pudieron inicializar columnas de ganancia en productos: ' . $e->getMessage());
        }
    }

    private function inicializarColumnasDescuento(): void {
        try {
            if (!$this->tieneColumnaDescuentoGanacia() && !$this->tieneColumnaDescuentoPorcentaje()) {
                $this->agregarColumnaSiNoExiste('productos', 'descuento_ganacia', 'ALTER TABLE productos ADD COLUMN descuento_ganacia DECIMAL(5,2) NOT NULL DEFAULT 0');
            }

            if (!$this->tieneColumnaPrecioOriginal()) {
                $this->agregarColumnaSiNoExiste('productos', 'precio_original', 'ALTER TABLE productos ADD COLUMN precio_original DECIMAL(10,2) NULL DEFAULT NULL');
            }
        } catch (\Throwable $e) {
            error_log('No se pudieron inicializar columnas de descuento en productos: ' . $e->getMessage());
        }
    }

    private function inicializarColumnaColor(): void {
        try {
            if ($this->tieneColumnaColor()) {
                $this->asignarColoresFaltantes();
                return;
            }

            $driver = strtolower((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME));
            $esSqlite = $driver === 'sqlite';
            $sql = $esSqlite
                ? 'ALTER TABLE productos ADD COLUMN color VARCHAR(20) NULL DEFAULT NULL'
                : 'ALTER TABLE productos ADD COLUMN color VARCHAR(20) NULL DEFAULT NULL AFTER imagen';

            $this->agregarColumnaSiNoExiste('productos', 'color', $sql);
            $this->asignarColoresFaltantes();
        } catch (\Throwable $e) {
            error_log('No se pudo inicializar columna color en productos: ' . $e->getMessage());
        }
    }

    private function inicializarColumnaVentaPorKilo(): void {
        try {
            $sql = $this->esSqlite()
                ? 'ALTER TABLE productos ADD COLUMN venta_por_kilo INTEGER NOT NULL DEFAULT 0'
                : 'ALTER TABLE productos ADD COLUMN venta_por_kilo TINYINT(1) NOT NULL DEFAULT 0';
            $this->agregarColumnaSiNoExiste('productos', 'venta_por_kilo', $sql);
        } catch (Throwable $e) {
            error_log('No se pudo inicializar columna venta_por_kilo: ' . $e->getMessage());
        }
    }

    private function tieneColumnaColor(): bool {
        try {
            return dbColumnExists($this->db, 'productos', 'color');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function normalizarColorHex(?string $color): ?string {
        $valor = trim((string)($color ?? ''));
        if ($valor === '') {
            return null;
        }

        $valor = strtoupper($valor);
        if (str_starts_with($valor, '#')) {
            $valor = substr($valor, 1);
        }

        if (preg_match('/^[0-9A-F]{6}$/', $valor)) {
            return '#' . $valor;
        }

        if (preg_match('/^[0-9A-F]{3}$/', $valor)) {
            return '#' . $valor[0] . $valor[0] . $valor[1] . $valor[1] . $valor[2] . $valor[2];
        }

        return null;
    }

    private function colorDisponible(string $color, ?int $excludeId = null): bool {
        try {
            $sql = 'SELECT id FROM productos WHERE color = ?';
            $params = [$color];
            if ($excludeId !== null) {
                $sql .= ' AND id != ?';
                $params[] = $excludeId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn() === 0;
        } catch (\Throwable $e) {
            error_log('Error verificando disponibilidad de color: ' . $e->getMessage());
            return false;
        }
    }

    private function generarColorHexUnico(string $seed = 'producto', ?int $excludeId = null): string {
        $baseSeed = trim($seed !== '' ? $seed : 'producto');
        $hash = md5($baseSeed);
        $candidates = [];
        for ($i = 0; $i < 6; $i++) {
            $candidates[] = '#' . substr($hash, $i * 2, 2) . substr($hash, ($i + 1) * 2 % 32, 2) . substr($hash, ($i + 2) * 2 % 32, 2);
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizarColorHex($candidate);
            if ($normalized !== null && $this->colorDisponible($normalized, $excludeId)) {
                return $normalized;
            }
        }

        for ($i = 0; $i < 20; $i++) {
            $candidate = sprintf('#%02X%02X%02X', ($i * 37) % 256, ($i * 53 + 97) % 256, ($i * 71 + 19) % 256);
            if ($this->colorDisponible($candidate, $excludeId)) {
                return $candidate;
            }
        }

        return '#2F4A5A';
    }

    private function asignarColoresFaltantes(): void {
        if (!$this->tieneColumnaColor()) {
            return;
        }

        try {
            $stmt = $this->db->prepare('SELECT id, codigo, nombre FROM productos WHERE color IS NULL OR TRIM(color) = "" ORDER BY id ASC');
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($productos as $producto) {
                $productoId = (int)($producto['id'] ?? 0);
                if ($productoId <= 0) {
                    continue;
                }
                $seed = trim((string)($producto['codigo'] ?? $producto['nombre'] ?? ''));
                $color = $this->generarColorHexUnico($seed !== '' ? $seed : 'producto', $productoId);
                $update = $this->db->prepare('UPDATE productos SET color = ? WHERE id = ?');
                $update->execute([$color, $productoId]);
            }
        } catch (\Throwable $e) {
            error_log('Error asignando colores faltantes: ' . $e->getMessage());
        }
    }

    private function obtenerColorPersistente(array $datos = [], ?int $productoId = null): ?string {
        if (!$this->tieneColumnaColor()) {
            return null;
        }

        if ($productoId !== null && $productoId > 0) {
            $stmt = $this->db->prepare('SELECT color FROM productos WHERE id = ? LIMIT 1');
            $stmt->execute([$productoId]);
            $colorActual = trim((string)$stmt->fetchColumn());
            $colorNormalizado = $this->normalizarColorHex($colorActual);
            if ($colorNormalizado !== null) {
                return $colorNormalizado;
            }
        }

        $colorInput = trim((string)($datos['color'] ?? $datos['color_nombre'] ?? ''));
        $colorNormalizado = $this->normalizarColorHex($colorInput);
        if ($colorNormalizado !== null) {
            return $this->generarColorHexUnico($colorNormalizado, $productoId);
        }

        $seed = trim((string)($datos['codigo'] ?? $datos['nombre'] ?? ''));
        return $this->generarColorHexUnico($seed !== '' ? $seed : 'producto', $productoId);
    }

    private function asegurarColorProducto(array $datos, int $empresaId): ?string {
        return $this->obtenerColorPersistente($datos, isset($datos['id']) ? (int)$datos['id'] : null);
    }

    private function normalizarRolSesion(): string {
        $rol = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
        return strtr($rol, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);
    }

    private function esSuperAdminGlobal(): bool {
        return $this->normalizarRolSesion() === 'super administrador' && empty($_SESSION['superadmin_modo_empresa']);
    }

    private function esAdministradorContexto(): bool {
        $rol = $this->normalizarRolSesion();
        if ($rol === 'administrador') {
            return true;
        }

        return $rol === 'super administrador' && !empty($_SESSION['superadmin_modo_empresa']);
    }

    private function debeFiltrarPorUsuario(): bool {
        // En este modulo el alcance es por empresa para cualquier rol con permiso.
        return false;
    }

    private function getEmpresaId() {
        if ($this->esSuperAdminGlobal()) {
            $usuarioId = $this->getUsuarioId();
            if ($usuarioId > 0) {
                try {
                    $stmt = $this->db->prepare("SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1");
                    $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
                    $stmt->execute();
                    $empresaIdUsuario = (int)($stmt->fetchColumn() ?: 0);
                    if ($empresaIdUsuario > 0) {
                        return $empresaIdUsuario;
                    }
                } catch (\Exception $e) {
                    error_log('Producto getEmpresaId superadmin fallback error: ' . $e->getMessage());
                }
            }
        }

        if (isset($_SESSION['empresa_id']) && (int)$_SESSION['empresa_id'] > 0) {
            return (int)$_SESSION['empresa_id'];
        }

        if (isset($_SESSION['userData']['empresa_id']) && (int)$_SESSION['userData']['empresa_id'] > 0) {
            return (int)$_SESSION['userData']['empresa_id'];
        }

        $usuarioId = $this->getUsuarioId();
        if ($usuarioId > 0) {
            try {
                $stmt = $this->db->prepare("SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1");
                $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
                $stmt->execute();
                $empresaId = (int)($stmt->fetchColumn() ?: 0);
                if ($empresaId > 0) {
                    $_SESSION['empresa_id'] = $empresaId;
                    return $empresaId;
                }
            } catch (\Exception $e) {
                error_log('Producto getEmpresaId fallback error: ' . $e->getMessage());
            }
        }

        // La aplicación usa una sola empresa; el catálogo no depende de un selector de empresa.
        return 1;
    }

    private function getUsuarioId(): int {
        if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0) {
            return (int)$_SESSION['usuario_id'];
        }

        if (isset($_SESSION['userData']['idusuario']) && (int)$_SESSION['userData']['idusuario'] > 0) {
            return (int)$_SESSION['userData']['idusuario'];
        }

        if (isset($_SESSION['userData']['id']) && (int)$_SESSION['userData']['id'] > 0) {
            return (int)$_SESSION['userData']['id'];
        }

        return 0;
    }

    private function getUsuarioOwnerId(): int {
        $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
        if ($adminId > 0) {
            return $adminId;
        }
        return $this->getUsuarioId();
    }

    private function tieneColumnaEmpresaId(): bool {
        try {
            return dbColumnExists($this->db, 'productos', 'empresa_id');
        } catch (Exception $e) {
            error_log("Error verificando columna empresa_id en productos: " . $e->getMessage());
            return false;
        }
    }

    private function tieneColumnaUsuarioIdProductos(): bool {
        try {
            return dbColumnExists($this->db, 'productos', 'usuario_id');
        } catch (Exception $e) {
            error_log("Error verificando columna usuario_id en productos: " . $e->getMessage());
            return false;
        }
    }

    private function tieneColumnaUsuarioIdCategorias(): bool {
        try {
            return dbColumnExists($this->db, 'categorias', 'usuario_id');
        } catch (Exception $e) {
            error_log("Error verificando columna usuario_id en categorias: " . $e->getMessage());
            return false;
        }
    }

    private function tieneColumnaDescuentoPorcentaje(): bool {
        try {
            return dbColumnExists($this->db, 'productos', 'descuento_porcentaje');
        } catch (Exception $e) {
            return false;
        }
    }

    private function tieneColumnaDescuentoGanacia(): bool {
        try {
            return dbColumnExists($this->db, 'productos', 'descuento_ganacia');
        } catch (Exception $e) {
            return false;
        }
    }

    private function columnaDescuentoProducto(): string {
        if ($this->tieneColumnaDescuentoGanacia()) {
            return 'descuento_ganacia';
        }

        if ($this->tieneColumnaDescuentoPorcentaje()) {
            return 'descuento_porcentaje';
        }

        return '';
    }

    private function tieneColumnaPorcentajeGanancia(): bool {
        try {
            return $this->tieneColumnaDescuentoGanacia() || dbColumnExists($this->db, 'productos', 'porcentaje_ganancia');
        } catch (Exception $e) {
            return false;
        }
    }

    private function columnaPorcentajeGananciaProducto(): string {
        if ($this->tieneColumnaPorcentajeGanancia()) {
            return 'porcentaje_ganancia';
        }

        if ($this->tieneColumnaDescuentoGanacia()) {
            return 'descuento_ganacia';
        }

        return '';
    }

    private function tieneColumnaPrecioOriginal(): bool {
        try {
            return dbColumnExists($this->db, 'productos', 'precio_original');
        } catch (Exception $e) {
            return false;
        }
    }

    private function obtenerPrimeraEmpresaDisponible(): int {
        try {
            $stmt = $this->db->prepare("SELECT id FROM empresas WHERE (estado IS NULL OR estado = 1) ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            error_log('Producto obtenerPrimeraEmpresaDisponible error: ' . $e->getMessage());
            return 0;
        }
    }

    // Getters y Setters
    public function getId() { return $this->id; }
    public function getCodigo() { return $this->codigo; }
    public function getCodigoBarras() { return $this->codigo_barras; }
    public function getNombre() { return strtoupper($this->nombre); }
    public function getDescripcion() { return $this->descripcion ? strtoupper($this->descripcion) : ''; }
    public function getPrecio() { return $this->precio; }
    public function getImagen() { return $this->imagen; }
    public function getCategoriaId() { return $this->categoria_id; }
    public function getEstado() { return $this->estado; }
    public function getStock() { return $this->stock; }
    public function getDescuentoPorcentaje() { return $this->descuento_porcentaje; }
    public function getPorcentajeGanancia() { return $this->porcentaje_ganancia; }
    public function getColor() { return $this->color; }

    public function setId($id) { $this->id = $id; }
    public function setCodigo($codigo) { $this->codigo = $codigo; }
    public function setCodigoBarras($codigo_barras) { $this->codigo_barras = $codigo_barras; }
    public function setNombre($nombre) { $this->nombre = $nombre; }
    public function setDescripcion($descripcion) { $this->descripcion = $descripcion; }
    public function setPrecio($precio) { $this->precio = $precio; }
    public function setImagen($imagen) { $this->imagen = $imagen; }
    public function setCategoriaId($categoria_id) { $this->categoria_id = $categoria_id; }
    public function setEstado($estado) { $this->estado = $estado; }
    public function setStock($stock) { $this->stock = $stock; }
    public function setDescuentoPorcentaje($descuento_porcentaje) { $this->descuento_porcentaje = $descuento_porcentaje; }
    public function setPorcentajeGanancia($porcentaje_ganancia) { $this->porcentaje_ganancia = $porcentaje_ganancia; }
    public function setColor($color) { $this->color = $color; }

    private function obtenerPrecioMaximoHistorico(int $productoId, float $precioDeseado): float {
        $precioDeseado = max(0, (float)$precioDeseado);
        try {
            if ($productoId > 0) {
                $stmt = $this->db->prepare("SELECT precio, precio_original FROM productos WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $productoId]);
                $registro = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($registro) {
                    $precioActual = max(0, (float)($registro['precio'] ?? 0));
                    $precioOriginal = max(0, (float)($registro['precio_original'] ?? 0));
                    return max($precioDeseado, $precioActual, $precioOriginal);
                }
            }
        } catch (Throwable $e) {
            error_log('No se pudo calcular precio histórico: ' . $e->getMessage());
        }

        return $precioDeseado;
    }

    private function normalizarTextoParaPrefijo($texto) {
        $texto = trim((string)$texto);
        if ($texto === '') {
            return '';
        }

        $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
        if ($normalized === false) {
            $normalized = $texto;
        }

        $normalized = preg_replace('/[^A-Za-z0-9]/', '', $normalized);
        return strtoupper(trim($normalized));
    }

    private function determinarPrefijosCategorias(array $categorias): array {
        $usedPrefixes = [];
        $asignados = [];

        foreach ($categorias as $categoria) {
            $nombreNormalizado = $this->normalizarTextoParaPrefijo($categoria->nombre);
            $nombreNormalizado = strtoupper($nombreNormalizado);

            if ($nombreNormalizado === '') {
                $prefijo = 'XX';
            } elseif (strlen($nombreNormalizado) < 2) {
                $prefijo = str_pad($nombreNormalizado, 2, 'X');
            } else {
                $prefijo = substr($nombreNormalizado, 0, 2);
                if (in_array($prefijo, $usedPrefixes, true)) {
                    $prefijo = '';
                    $letraInicial = $nombreNormalizado[0];
                    for ($indice = 1; $indice < strlen($nombreNormalizado); $indice++) {
                        $candidate = $letraInicial . $nombreNormalizado[$indice];
                        if (!in_array($candidate, $usedPrefixes, true)) {
                            $prefijo = $candidate;
                            break;
                        }
                    }
                    if ($prefijo === '') {
                        $prefijo = substr($nombreNormalizado, 0, 2);
                    }
                }
            }

            $asignados[(int)$categoria->id] = $prefijo;
            $usedPrefixes[] = $prefijo;
        }

        return $asignados;
    }

    private function obtenerPrefijoCategoria($categoriaId) {
        $empresaId = $this->getEmpresaId();
        $usuarioId = $this->getUsuarioId();
        $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
        $tieneUsuarioCategorias = $this->tieneColumnaUsuarioIdCategorias();

        $sql = "SELECT id, nombre FROM categorias WHERE empresa_id = ?";
        $params = [$empresaId];
        if ($filtrarPorUsuario && $tieneUsuarioCategorias && $usuarioId > 0) {
            $sql .= " AND usuario_id = ?";
            $params[] = $usuarioId;
        }
        $sql .= " ORDER BY id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $categorias = $stmt->fetchAll(PDO::FETCH_OBJ);

        if (!$categorias) {
            return '';
        }

        $prefijosAsignados = $this->determinarPrefijosCategorias($categorias);
        return isset($prefijosAsignados[(int)$categoriaId]) ? $prefijosAsignados[(int)$categoriaId] : '';
    }

    /**
     * Genera un código automático basado en el nombre de la categoría
     * y un número secuencial
     */
    public function generarCodigo() {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioCategorias = $this->tieneColumnaUsuarioIdCategorias();
            $tieneUsuarioProductos = $this->tieneColumnaUsuarioIdProductos();
            // Obtener el nombre de la categoría
            $sql = "SELECT nombre FROM categorias WHERE id = ? AND empresa_id = ?";
            if ($filtrarPorUsuario && $tieneUsuarioCategorias && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $stmt = $this->db->prepare($sql);
            $paramsCategoria = [$this->categoria_id, $empresaId];
            if ($filtrarPorUsuario && $tieneUsuarioCategorias && $usuarioId > 0) {
                $paramsCategoria[] = $usuarioId;
            }
            $stmt->execute($paramsCategoria);
            $categoria = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$categoria) {
                throw new Exception("Categoría no encontrada");
            }
            
            $prefijo = $this->obtenerPrefijoCategoria($this->categoria_id);
            if ($prefijo === '') {
                throw new Exception("Prefijo de categoría inválido");
            }
            
            // Buscar el último número usado para este prefijo
            $sql = "SELECT codigo FROM productos 
                    WHERE codigo LIKE ? 
                    AND empresa_id = ?";
            if ($filtrarPorUsuario && $tieneUsuarioProductos && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $sql .= "
                    ORDER BY LENGTH(codigo) DESC, codigo DESC 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $paramsCodigo = [$prefijo . '%', $empresaId];
            if ($filtrarPorUsuario && $tieneUsuarioProductos && $usuarioId > 0) {
                $paramsCodigo[] = $usuarioId;
            }
            $stmt->execute($paramsCodigo);
            $ultimoCodigo = $stmt->fetch(PDO::FETCH_OBJ);
            
            if ($ultimoCodigo && preg_match('/^' . preg_quote($prefijo, '/') . '(\d+)$/', $ultimoCodigo->codigo, $matches)) {
                $numero = intval($matches[1]) + 1;
            } else {
                $numero = 1;
            }
            
            return $prefijo . $numero;
            
        } catch (Exception $e) {
            error_log("Error en generarCodigo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el próximo código disponible para un prefijo dado
     */
    public function obtenerProximoCodigo($prefijo) {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioProductos = $this->tieneColumnaUsuarioIdProductos();
            
            $prefijo = $this->normalizarTextoParaPrefijo($prefijo);
            if ($prefijo === '') {
                throw new Exception('Prefijo inválido');
            }
            
            // Buscar el último número usado para este prefijo
            $sql = "SELECT codigo FROM productos 
                    WHERE codigo LIKE ? 
                    AND empresa_id = ?";
            if ($filtrarPorUsuario && $tieneUsuarioProductos && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $sql .= "
                    ORDER BY codigo DESC 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $params = [$prefijo . '%', $empresaId];
            if ($filtrarPorUsuario && $tieneUsuarioProductos && $usuarioId > 0) {
                $params[] = $usuarioId;
            }
            $stmt->execute($params);
            $ultimoCodigo = $stmt->fetch(PDO::FETCH_OBJ);
            
            if ($ultimoCodigo && preg_match('/^' . preg_quote($prefijo, '/') . '(\d+)$/', $ultimoCodigo->codigo, $matches)) {
                $numero = intval($matches[1]) + 1;
            } else {
                $numero = 1;
            }
            
            return $prefijo . $numero;
            
        } catch (Exception $e) {
            error_log("Error en obtenerProximoCodigo: " . $e->getMessage());
            return null;
        }
    }

    public function getAll($ordenIdExcluir = 0) {
        try {
            if (!$this->db) {
                return [];
            }

            $empresaId = $this->getEmpresaId();
            if ($empresaId <= 0 && $this->esSuperAdminGlobal()) {
                $empresaId = $this->obtenerPrimeraEmpresaDisponible();
            }
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            $tieneEmpresaId = $this->tieneColumnaEmpresaId();
            $tienePorcentajeGanancia = $this->tieneColumnaPorcentajeGanancia();
            $columnaGanancia = $this->columnaPorcentajeGananciaProducto();
            $subqueryUltimoPrecioCompra = '(SELECT precio_compra FROM entradas_inventario e WHERE e.producto_id = p.id ORDER BY e.fecha_entrada DESC LIMIT 1)';
            $exprPorcentajeGanancia = $tienePorcentajeGanancia && $columnaGanancia !== ''
                ? "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE COALESCE(p.{$columnaGanancia}, 0) END"
                : "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN {$subqueryUltimoPrecioCompra} > 0 AND IFNULL(p.stock, 0) > 0 THEN ROUND(100 * (p.precio - {$subqueryUltimoPrecioCompra}) / {$subqueryUltimoPrecioCompra}, 1) ELSE 0 END END";

            $sql = "SELECT p.*, c.nombre as categoria_nombre,
                    {$subqueryUltimoPrecioCompra} as ultimo_precio_compra,
                    {$exprPorcentajeGanancia} as porcentaje_ganancia,
                    0 as stock_reservado,
                    COALESCE(p.stock, 0) as stock_disponible
                    FROM productos p
                    LEFT JOIN categorias c ON p.categoria_id = c.id
                    WHERE 1=1";
            $params = [];

            if ($tieneEmpresaId && $empresaId > 0) {
                $sql .= " AND (p.empresa_id IS NULL OR p.empresa_id = 0 OR p.empresa_id = ?)";
                $params[] = $empresaId;
            }

            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND p.usuario_id = ?";
                $params[] = $usuarioId;
            }

            $sql .= " ORDER BY p.id DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);
            if (!empty($result)) {
                return $result;
            }

            if ($tieneEmpresaId && $empresaId > 0) {
                $fallbackSql = "SELECT p.*, c.nombre as categoria_nombre,
                                0 as stock_reservado,
                                COALESCE(p.stock, 0) as stock_disponible
                                FROM productos p 
                                LEFT JOIN categorias c ON p.categoria_id = c.id
                                WHERE 1=1";
                $fallbackParams = [];
                if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                    $fallbackSql .= " AND p.usuario_id = ?";
                    $fallbackParams[] = $usuarioId;
                }
                $fallbackSql .= " ORDER BY p.id DESC";
                $fallbackStmt = $this->db->prepare($fallbackSql);
                $fallbackStmt->execute($fallbackParams);
                $fallbackResult = $fallbackStmt->fetchAll(PDO::FETCH_OBJ);
                if (!empty($fallbackResult)) {
                    return $fallbackResult;
                }
            }
            return [];
            
        } catch (Exception $e) {
            error_log("Error en getAll: " . $e->getMessage());
            return [];
        }
    }

    public function save() {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioOwnerId();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            $tienePrecioOriginal = $this->tieneColumnaPrecioOriginal();
            // Generar código automáticamente si no se proporcionó
            if (empty($this->codigo)) {
                $this->codigo = $this->generarCodigo();
            }

            if ($this->precio !== null && $this->precio !== '') {
                $this->precio = $this->obtenerPrecioMaximoHistorico(0, (float)$this->precio);
            }

            if ($tieneUsuarioId) {
                if ($tienePrecioOriginal) {
                    $sql = "INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, precio_original, imagen, categoria_id, estado, stock, empresa_id, usuario_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
                } else {
                    $sql = "INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, imagen, categoria_id, estado, stock, empresa_id, usuario_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
                }
            } else {
                if ($tienePrecioOriginal) {
                    $sql = "INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, precio_original, imagen, categoria_id, estado, stock, empresa_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)";
                } else {
                    $sql = "INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, imagen, categoria_id, estado, stock, empresa_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)";
                }
            }
            $stmt = $this->db->prepare($sql);
            $imagenGuardar = trim((string)$this->getImagen());
            if ($imagenGuardar === '') {
                $imagenGuardar = 'favicon.ico';
            }

            $params = [
                $this->getCodigo(),
                $this->getCodigoBarras(),
                $this->getNombre(),
                $this->getDescripcion(),
                $this->getPrecio(),
            ];
            if ($tienePrecioOriginal) {
                $params[] = $this->getPrecio();
            }
            $params[] = $imagenGuardar;
            $params[] = $this->getCategoriaId();
            $params[] = $this->getStock();
            $params[] = $empresaId;
            if ($tieneUsuarioId) {
                $params[] = $usuarioId > 0 ? $usuarioId : null;
            }
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Error en save: " . $e->getMessage());
            return false;
        }
    }

    public function getAllByCategoria() {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            $sql = "SELECT p.*, c.nombre as categoria FROM productos p "
                 . "INNER JOIN categorias c ON c.id = p.categoria_id "
                 . "WHERE p.categoria_id = ? AND p.empresa_id = ? ";
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= "AND p.usuario_id = ? ";
            }
            $sql .= "ORDER BY p.id DESC";
            $stmt = $this->db->prepare($sql);
            $params = [$this->getCategoriaId(), $empresaId];
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $params[] = $usuarioId;
            }
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            error_log("Error en getAllByCategoria: " . $e->getMessage());
            return null;
        }
    }

    public function getOne() {
        try {
            $empresaId = $this->getEmpresaId();
            if ($empresaId <= 0 && $this->esSuperAdminGlobal()) {
                $empresaId = $this->obtenerPrimeraEmpresaDisponible();
            }
            $this->asignarColoresFaltantes();

            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            $tieneEmpresaId = $this->tieneColumnaEmpresaId();
            $tienePorcentajeGanancia = $this->tieneColumnaPorcentajeGanancia();
            $columnaGanancia = $this->columnaPorcentajeGananciaProducto();
            $subqueryUltimoPrecioCompra = '(SELECT precio_compra FROM entradas_inventario e WHERE e.producto_id = p.id ORDER BY e.fecha_entrada DESC LIMIT 1)';
            $exprPorcentajeGanancia = $tienePorcentajeGanancia && $columnaGanancia !== ''
                ? "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE COALESCE(p.{$columnaGanancia}, 0) END"
                : "CASE WHEN COALESCE(p.stock, 0) <= 0 THEN 0 ELSE CASE WHEN {$subqueryUltimoPrecioCompra} > 0 AND IFNULL(p.stock, 0) > 0 THEN ROUND(100 * (p.precio - {$subqueryUltimoPrecioCompra}) / {$subqueryUltimoPrecioCompra}, 1) ELSE 0 END END";
            // además de los datos básicos, traer el último precio de compra y la ganancia porcentual actual
            $sql = "SELECT p.*, c.nombre as categoria_nombre, 
                    {$subqueryUltimoPrecioCompra} as ultimo_precio_compra,
                    {$exprPorcentajeGanancia} as porcentaje_ganancia
                    FROM productos p 
                    LEFT JOIN categorias c ON c.id = p.categoria_id 
                        WHERE p.id = ?";
            $params = [$this->getId()];

            if ($tieneEmpresaId && $empresaId > 0) {
                $sql .= " AND (p.empresa_id IS NULL OR p.empresa_id = 0 OR p.empresa_id = ?)";
                $params[] = $empresaId;
            }

            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND p.usuario_id = ?";
                $params[] = $usuarioId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$result && $tieneEmpresaId && $empresaId > 0) {
                $fallbackSql = "SELECT p.*, c.nombre as categoria_nombre, 
                    {$subqueryUltimoPrecioCompra} as ultimo_precio_compra,
                    {$exprPorcentajeGanancia} as porcentaje_ganancia
                    FROM productos p 
                    LEFT JOIN categorias c ON c.id = p.categoria_id 
                        WHERE p.id = ?";
                $fallbackParams = [$this->getId()];
                if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                    $fallbackSql .= " AND p.usuario_id = ?";
                    $fallbackParams[] = $usuarioId;
                }
                $fallbackStmt = $this->db->prepare($fallbackSql);
                $fallbackStmt->execute($fallbackParams);
                $result = $fallbackStmt->fetch(PDO::FETCH_OBJ);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error en getOne: " . $e->getMessage());
            return null;
        }
    }

    public function delete() {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            // Primero obtenemos la imagen actual si existe
            $sql = "SELECT imagen FROM productos WHERE id = ? AND empresa_id = ?";
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $stmt = $this->db->prepare($sql);
            $paramsSelect = [$this->getId(), $empresaId];
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $paramsSelect[] = $usuarioId;
            }
            $stmt->execute($paramsSelect);
            $producto = $stmt->fetch(PDO::FETCH_OBJ);
            
            // No eliminar archivo fisico aqui: se conserva para historico de actualizaciones.
            
            // Eliminar el registro de la base de datos
            $sql = "DELETE FROM productos WHERE id = ? AND empresa_id = ?";
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $stmt = $this->db->prepare($sql);
            $paramsDelete = [$this->getId(), $empresaId];
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $paramsDelete[] = $usuarioId;
            }
            $resultado = $stmt->execute($paramsDelete);
            
            if (!$resultado) {
                throw new \Exception('No se pudo eliminar el producto');
            }
            
            return $resultado;
            
        } catch (PDOException $e) {
            $mensaje_error = $e->getMessage();
            
            // Detectar si es un error de llave foránea
            if (strpos($mensaje_error, '1451') !== false || strpos($mensaje_error, 'CONSTRAINT') !== false) {
                throw new \Exception('⚠️ No se puede eliminar este producto porque está siendo usado en el inventario o en órdenes. Primero debes desactivarlo.');
            } else {
                throw new \Exception('Error al eliminar: ' . $mensaje_error);
            }
        }
    }

    private function asegurarTablaBackupProductos(): void {
        $sql = "CREATE TABLE IF NOT EXISTS productos_backups_reset (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data TEXT NOT NULL,
            creado_en TEXT NOT NULL
        )";
        $this->db->exec($sql);
    }

    private function guardarBackupProductos(): int {
        $this->asegurarTablaBackupProductos();
        $sql = 'SELECT * FROM productos';
        $params = [];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtInsert = $this->db->prepare('INSERT INTO productos_backups_reset (data, creado_en) VALUES (:data, :creado_en)');
        $stmtInsert->execute([
            ':data' => json_encode($filas, JSON_UNESCAPED_UNICODE),
            ':creado_en' => date('Y-m-d H:i:s')
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function obtenerUltimoBackupProductos(): ?array {
        $this->asegurarTablaBackupProductos();
        $stmt = $this->db->prepare('SELECT id, data FROM productos_backups_reset ORDER BY id DESC LIMIT 1');
        $stmt->execute();
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$backup) {
            return null;
        }

        return [
            'id' => (int)($backup['id'] ?? 0),
            'data' => (string)($backup['data'] ?? '[]')
        ];
    }

    private function obtenerBackupProductosPorId(int $id): ?array {
        $this->asegurarTablaBackupProductos();
        $stmt = $this->db->prepare('SELECT id, data FROM productos_backups_reset WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$backup) {
            return null;
        }

        return [
            'id' => (int)($backup['id'] ?? 0),
            'data' => (string)($backup['data'] ?? '[]')
        ];
    }

    private function eliminarBackupProductosPorId(int $id): void {
        $this->asegurarTablaBackupProductos();
        $stmt = $this->db->prepare('DELETE FROM productos_backups_reset WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    private function obtenerTablasDependientesProductos(): array {
        $tablas = [];
        try {
            $stmtTablas = $this->db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            $nombres = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);

            foreach ($nombres as $nombreTabla) {
                if (!is_string($nombreTabla) || $nombreTabla === 'productos') {
                    continue;
                }

                $stmtFk = $this->db->query("PRAGMA foreign_key_list({$nombreTabla})");
                $fks = $stmtFk->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $apuntaAProductos = false;
                foreach ($fks as $fk) {
                    if (($fk['table'] ?? '') === 'productos') {
                        $apuntaAProductos = true;
                        break;
                    }
                }

                if (!$apuntaAProductos) {
                    $stmtCols = $this->db->query("PRAGMA table_info({$nombreTabla})");
                    $columnas = $stmtCols->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($columnas as $col) {
                        if (($col['name'] ?? '') === 'producto_id') {
                            $apuntaAProductos = true;
                            break;
                        }
                    }
                }

                if ($apuntaAProductos) {
                    $tablas[] = $nombreTabla;
                }
            }
        } catch (Throwable $e) {
            error_log('Error obteniendo tablas dependientes de productos: ' . $e->getMessage());
        }

        return $tablas;
    }

    private function construirCondicionAlcanceProductos(?int $empresaId = null): array {
        $empresaId = $empresaId ?? $this->getEmpresaId();
        if ($empresaId > 0) {
            return [
                'sql' => ' WHERE (empresa_id IS NULL OR empresa_id = 0 OR empresa_id = ?)',
                'params' => [$empresaId]
            ];
        }

        return [
            'sql' => '',
            'params' => []
        ];
    }

    public function reiniciarProductos(): array {
        try {
            if (!$this->esSuperAdminGlobal()) {
                throw new \Exception('Permisos insuficientes para reiniciar productos');
            }

            $backupId = $this->guardarBackupProductos();
            $_SESSION['productos_reset_backup_id'] = $backupId;
            $dependientes = $this->obtenerTablasDependientesProductos();

            $this->desactivarRestriccionesForaneas();
            $this->db->beginTransaction();

            foreach ($dependientes as $tabla) {
                try {
                    $this->db->exec('DELETE FROM ' . $tabla);
                } catch (\Throwable $e) {
                    error_log('No se pudo limpiar dependencia de reset: ' . $tabla . ' -> ' . $e->getMessage());
                }
            }

            $this->db->exec('DELETE FROM productos');

            if ($this->esSqlite()) {
                try {
                    $this->db->exec("DELETE FROM sqlite_sequence WHERE name = 'productos'");
                } catch (\Throwable $_) {
                }
            }

            $this->db->commit();
            $this->activarRestriccionesForaneas();

            return [
                'success' => true,
                'message' => 'Productos reiniciados correctamente',
                'backup_id' => $backupId,
                'tablas_dependientes' => $dependientes
            ];
        } catch (\Throwable $e) {
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (\Throwable $_) {
            }
            $this->activarRestriccionesForaneas();
            error_log('Error en reiniciarProductos: ' . $e->getMessage());
            return [ 'success' => false, 'message' => $e->getMessage() ];
        }
    }

    public function deshacerProductos(): array {
        try {
            if (!$this->esSuperAdminGlobal()) {
                throw new \Exception('Permisos insuficientes para deshacer reinicio de productos');
            }

            $backupIdSesion = isset($_SESSION['productos_reset_backup_id']) ? (int)$_SESSION['productos_reset_backup_id'] : 0;
            if ($backupIdSesion <= 0) {
                throw new \Exception('No hay un reinicio reciente para deshacer');
            }

            $backup = $this->obtenerBackupProductosPorId($backupIdSesion);
            if (!$backup) {
                throw new \Exception('No hay backup disponible para restaurar este reinicio');
            }

            $filas = json_decode($backup['data'] ?? '[]', true);
            if (!is_array($filas)) {
                throw new \Exception('Backup inválido');
            }

            $dependientes = $this->obtenerTablasDependientesProductos();
            $this->desactivarRestriccionesForaneas();
            $this->db->beginTransaction();

            foreach ($dependientes as $tabla) {
                try {
                    $this->db->exec('DELETE FROM ' . $tabla);
                } catch (\Throwable $e) {
                    error_log('No se pudo limpiar dependencia de deshacer: ' . $tabla . ' -> ' . $e->getMessage());
                }
            }

            $this->db->exec('DELETE FROM productos');

            foreach ($filas as $fila) {
                if (!is_array($fila) || empty($fila)) {
                    continue;
                }

                $filaFiltrada = [];
                $columnInfo = dbColumnInfo($this->db, 'productos');
                foreach ($fila as $col => $valor) {
                    if (!is_string($col)) {
                        continue;
                    }
                    $colLower = strtolower(trim($col));
                    if ($colLower === '' || !isset($columnInfo[$colLower])) {
                        continue;
                    }
                    $filaFiltrada[$colLower] = $valor;
                }

                if (empty($filaFiltrada)) {
                    continue;
                }

                $cols = array_keys($filaFiltrada);
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sqlInsert = 'INSERT INTO productos (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';
                $stmtInsert = $this->db->prepare($sqlInsert);
                $stmtInsert->execute(array_values($filaFiltrada));
            }

            $this->eliminarBackupProductosPorId((int)$backup['id']);
            unset($_SESSION['productos_reset_backup_id']);
            $this->db->commit();
            $this->activarRestriccionesForaneas();

            return ['success' => true, 'message' => 'Restauración completada correctamente'];
        } catch (\Throwable $e) {
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (\Throwable $_) {
            }
            $this->activarRestriccionesForaneas();
            error_log('Error en deshacerProductos: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update() {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            $tieneDescuento = $this->tieneColumnaDescuentoPorcentaje() || $this->tieneColumnaDescuentoGanacia();
            $tieneColumnaColor = $this->tieneColumnaColor();
            $tienePrecioOriginal = $this->tieneColumnaPrecioOriginal();
            $tienePorcentajeGanancia = $this->tieneColumnaPorcentajeGanancia();
            // Validar que tenemos el ID
            if (!$this->id) {
                error_log("Error: ID de producto no establecido");
                return false;
            }

            // Primero obtenemos la imagen actual si existe
            $sql = "SELECT imagen FROM productos WHERE id = ? AND empresa_id = ?";
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $stmt = $this->db->prepare($sql);
            $paramsImagen = [$this->id, $empresaId];
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $paramsImagen[] = $usuarioId;
            }
            $stmt->execute($paramsImagen);
            $imagenAntigua = $stmt->fetchColumn();

            // Construimos la consulta de actualización
            // ahora sí permitimos modificar precio y stock si se proporcionan
            if ($this->precio !== null) {
                $this->precio = $this->obtenerPrecioMaximoHistorico((int)$this->id, (float)$this->precio);
            }
            $sql = "UPDATE productos SET nombre = ?, descripcion = ?";
            $params = [
                $this->nombre,
                $this->descripcion
            ];

            // precio de venta
            if ($this->precio !== null) {
                $sql .= ", precio = ?";
                $params[] = $this->precio;
                if ($tienePrecioOriginal) {
                    $sql .= ", precio_original = CASE WHEN precio_original IS NULL OR precio_original < ? THEN ? ELSE precio_original END";
                    $params[] = $this->precio;
                    $params[] = $this->precio;
                }
            }

            // stock
            if ($this->stock !== null) {
                $sql .= ", stock = ?";
                $params[] = $this->stock;
            }

            // Si hay código de barras (sí se puede actualizar)
            if ($this->codigo_barras !== null) {
                $sql .= ", codigo_barras = ?";
                $params[] = $this->codigo_barras;
            }

            if ($this->codigo !== null && trim((string)$this->codigo) !== '') {
                $sql .= ", codigo = ?";
                $params[] = $this->codigo;
            }

            // Si hay categoría
            if ($this->categoria_id) {
                $sql .= ", categoria_id = ?";
                $params[] = $this->categoria_id;
            }

            // Si se indicó estado explícitamente (puede ser 0)
            if ($this->estado !== null) {
                $sql .= ", estado = ?";
                $params[] = $this->estado ? 1 : 0;
            }

            $columnaDescuento = $this->columnaDescuentoProducto();
            if ($tieneDescuento && $columnaDescuento !== '' && $this->descuento_porcentaje !== null) {
                $descuento = max(0, min(100, (float)$this->descuento_porcentaje));
                if ($tienePrecioOriginal) {
                    // Siempre preservar precio_original cuando hay descuento
                    $sql .= ", precio_original = CASE WHEN precio_original IS NULL THEN precio ELSE precio_original END";
                }
                $sql .= ", {$columnaDescuento} = ?";
                $params[] = $descuento;
            }
            // Asegurar que precio_original siempre está presente si la tabla lo soporta
            elseif ($tienePrecioOriginal && $this->precio !== null) {
                $sql .= ", precio_original = CASE WHEN precio_original IS NULL THEN precio ELSE precio_original END";
            }

            // Guardar porcentaje_ganancia si existe la columna y se proporciona
            if ($tienePorcentajeGanancia && $this->porcentaje_ganancia !== null) {
                $ganancia = max(0, (float)$this->porcentaje_ganancia);
                $columnaGanancia = $this->columnaPorcentajeGananciaProducto();
                if ($columnaGanancia !== '') {
                    $sql .= ", {$columnaGanancia} = ?";
                    $params[] = $ganancia;
                }
            }

            // Si hay una nueva imagen
            if ($this->imagen) {
                $sql .= ", imagen = ?";
                $params[] = $this->imagen;
            }

            if ($tieneColumnaColor && $this->color !== null) {
                $colorNormalizado = $this->normalizarColorHex($this->color);
                $sql .= ", color = ?";
                $params[] = $colorNormalizado !== null ? $colorNormalizado : $this->color;
            }

            $sql .= " WHERE id = ?";
            $params[] = $this->id;
            if ($tieneEmpresaId && $empresaId > 0) {
                $sql .= " AND (empresa_id IS NULL OR empresa_id = 0 OR empresa_id = ?)";
                $params[] = $empresaId;
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
                $params[] = $usuarioId;
            }
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("Error en actualización SQL: " . json_encode($errorInfo));
                return false;
            }

            if ($stmt->rowCount() === 0) {
                error_log("Advertencia: Ninguna fila fue actualizada para el ID: " . $this->id);
            }

            return true;

        } catch (PDOException $e) {
            error_log("Error en update (PDOException): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Error en update (General): " . $e->getMessage());
            return false;
        }
    }

    public function cambiarIdProducto($idActual, $idNuevo) {
        try {
            // Verificar que el nuevo ID no exista ya
            $query = "SELECT COUNT(*) FROM productos WHERE id = ? AND id != ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$idNuevo, $idActual]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El ID '{$idNuevo}' ya existe");
            }
            
            // Iniciar transacción para actualizar en cascada
            $this->db->beginTransaction();
            
            // Desactivar comprobaciones de clave externa temporalmente
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Actualizar el ID en la tabla de productos
            $query = "UPDATE productos SET id = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            
            if (!$stmt->execute([$idNuevo, $idActual])) {
                throw new Exception("Error al actualizar el ID del producto");
            }
            
            // Reactivar comprobaciones de clave externa
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Confirmar la transacción
            $this->db->commit();
            return true;
            
        } catch(Exception $e) {
            try {
                // Asegurar que las claves externas se reactiven en caso de error
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch(Exception $fkException) {
                error_log("Error al reactivar FOREIGN_KEY_CHECKS: " . $fkException->getMessage());
            }
            
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en cambiarIdProducto: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateEstado() {
        try {
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            $sql = "UPDATE productos SET estado = ? WHERE id = ? AND empresa_id = ?";
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $stmt = $this->db->prepare($sql);
            $params = [
                $this->estado ? 1 : 0,
                $this->id,
                $this->getEmpresaId()
            ];
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $params[] = $usuarioId;
            }
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error en updateEstado: " . $e->getMessage());
            return false;
        }
    }

    public function crear($datos) {
        try {
            $empresaId = $this->getEmpresaId();
            if ($empresaId <= 0) {
                $usuarioId = $this->getUsuarioId();
                if ($usuarioId > 0) {
                    try {
                        $stmtEmpresa = $this->db->prepare('SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1');
                        $stmtEmpresa->execute([':id' => $usuarioId]);
                        $empresaId = (int)($stmtEmpresa->fetchColumn() ?: 0);
                        if ($empresaId > 0) {
                            $_SESSION['empresa_id'] = $empresaId;
                        }
                    } catch (Throwable $e) {
                        error_log('Producto crear fallback empresa_id error: ' . $e->getMessage());
                    }
                }
            }
            $usuarioId = $this->getUsuarioOwnerId();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            if ($empresaId <= 0) {
                try {
                    $stmtEmpresa = $this->db->prepare('SELECT id FROM empresas ORDER BY id LIMIT 1');
                    $stmtEmpresa->execute();
                    $empresaId = (int)($stmtEmpresa->fetchColumn() ?: 0);
                    if ($empresaId > 0) {
                        $_SESSION['empresa_id'] = $empresaId;
                    }
                } catch (Throwable $e) {
                    error_log('Producto crear fallback empresa_id default error: ' . $e->getMessage());
                }
            }
            $datos['codigo_barras'] = $this->prepararCodigoBarras($datos['codigo_barras'] ?? '');

            // Generar código automáticamente si no se proporcionó
            if (empty($datos['codigo'])) {
                $this->categoria_id = $datos['categoria_id'];
                $datos['codigo'] = $this->generarCodigo();
            }

            $imagenCrear = trim((string)($datos['imagen'] ?? ''));
            if ($imagenCrear === '') {
                $imagenCrear = 'favicon.ico';
            }

            $colorPersistente = $this->asegurarColorProducto($datos, $empresaId);
            $tieneColumnaColor = $this->tieneColumnaColor();

            if ($tieneUsuarioId) {
                $sql = $tieneColumnaColor
                    ? "INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, imagen, categoria_id, estado, stock, empresa_id, usuario_id, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    : "INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, imagen, categoria_id, estado, stock, empresa_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            } else {
                $sql = $tieneColumnaColor
                    ? "INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, imagen, categoria_id, estado, stock, empresa_id, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    : "INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, imagen, categoria_id, estado, stock, empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            }
            
            $stmt = $this->db->prepare($sql);
            
            $paramsCrear = [
                $datos['codigo'],
                $datos['codigo_barras'] ?? null,
                $datos['nombre'],
                $datos['descripcion'] ?? '',
                $datos['precio'] ?? 0,
                $imagenCrear,
                $datos['categoria_id'],
                $datos['estado'] ?? 1,
                $datos['stock'] ?? 0,
                $empresaId
            ];
            if ($tieneUsuarioId) {
                $paramsCrear[] = $usuarioId > 0 ? $usuarioId : null;
            }
            if ($tieneColumnaColor) {
                $paramsCrear[] = $colorPersistente ?? $this->obtenerColorPersistente($datos, null);
            }

            $resultado = false;
            $intento = 0;
            while ($intento < 2) {
                try {
                    $resultado = $stmt->execute($paramsCrear);
                    break;
                } catch (PDOException $e) {
                    $esDuplicado = ((string)$e->getCode() === '23000') && stripos($e->getMessage(), 'Duplicate entry') !== false;
                    if ($esDuplicado && $intento === 0 && !empty($datos['categoria_id'])) {
                        $this->categoria_id = $datos['categoria_id'];
                        $datos['codigo'] = $this->generarCodigo();
                        $paramsCrear[0] = $datos['codigo'];
                        $intento++;
                        continue;
                    }
                    throw $e;
                }
            }
            
            if ($resultado) {
                return [
                    'success' => true,
                    'codigo' => $datos['codigo']
                ];
            }

            return [
                'success' => false,
                'codigo' => $datos['codigo'] ?? null,
                'message' => 'No se pudo crear el producto. Revise la configuración de la empresa y los datos enviados.'
            ];
        } catch (PDOException $e) {
            $mensaje = 'Error en crear producto: ' . $e->getMessage();
            error_log($mensaje);
            return ['success' => false, 'codigo' => null, 'message' => $mensaje];
        } catch (Exception $e) {
            $mensaje = 'Error en crear producto: ' . $e->getMessage();
            error_log($mensaje);
            return ['success' => false, 'codigo' => null, 'message' => $mensaje];
        }
    }

    public function prepararCodigoBarras($codigo, int $excluirId = 0): string {
        $codigo = trim((string)$codigo);
        // Si el código está vacío, permitir el producto sin código
        if ($codigo === '') {
            return '';
        }

        if (!preg_match('/^\d+$/', $codigo)) {
            throw new InvalidArgumentException('El código de barras solo puede contener dígitos.');
        }
        if (strlen($codigo) < 8 || strlen($codigo) > 14) {
            throw new InvalidArgumentException('El código de barras debe tener entre 8 y 14 dígitos.');
        }
        if ($this->existeCodigoBarras($codigo, $excluirId)) {
            throw new InvalidArgumentException('Este código de barras ya está asociado a un producto.');
        }
        return $codigo;
    }

    public function existeCodigoBarras(string $codigo, int $excluirId = 0): bool {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM productos WHERE codigo_barras = :codigo';
        if ($excluirId > 0) $sql .= ' AND id <> :id';
        $stmt = $this->db->prepare($sql);
        $params = [':codigo' => $codigo];
        if ($excluirId > 0) $params[':id'] = $excluirId;
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function actualizarStock($id, $cantidad) {
        try {
            $empresaId = $this->getEmpresaId();
            $usuarioId = $this->getUsuarioId();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $tieneUsuarioId = $this->tieneColumnaUsuarioIdProductos();
            if ($cantidad >= 0) {
                $sql = "UPDATE productos SET stock = stock + ? WHERE id = ? AND empresa_id = ?";
                if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                    $sql .= " AND usuario_id = ?";
                }
                $stmt = $this->db->prepare($sql);
                $params = [$cantidad, $id, $empresaId];
                if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                    $params[] = $usuarioId;
                }
                return $stmt->execute($params);
            }

            $descuento = abs((int)$cantidad);
            $sql = "UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ? AND empresa_id = ?";
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $stmt = $this->db->prepare($sql);
            $params = [$descuento, $id, $descuento, $empresaId];
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $params[] = $usuarioId;
            }
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error en actualizarStock: " . $e->getMessage());
            return false;
        }
    }
} 