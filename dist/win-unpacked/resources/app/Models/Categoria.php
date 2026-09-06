<?php
require_once __DIR__ . '/../Config/Config.php';

class Categoria {
    private $db;
    private $id;
    private $nombre;
    private $descripcion;
    private $imagen;
    private $estado;
    private $empresa_id;
    private $fecha_creacion;
    private $fecha_actualizacion;
    private $columnaFechaActualizacion = null;
    private $tieneUsuarioId = null;
    private $lastError = '';

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

    private function getEmpresaIdSesion(): int {
        if (isset($_SESSION['empresa_id']) && (int)$_SESSION['empresa_id'] > 0) {
            return (int)$_SESSION['empresa_id'];
        }

        if (isset($_SESSION['userData']['empresa_id']) && (int)$_SESSION['userData']['empresa_id'] > 0) {
            $_SESSION['empresa_id'] = (int)$_SESSION['userData']['empresa_id'];
            return (int)$_SESSION['userData']['empresa_id'];
        }

        $empresaIdUsuario = 0;

        try {
            $usuarioId = $this->getUsuarioIdSesion();
            if ($usuarioId > 0 && $this->existeColumna('usuarios', 'empresa_id')) {
                $stmt = $this->db->prepare("SELECT empresa_id, admin_id FROM usuarios WHERE id = :id LIMIT 1");
                $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $empresaIdUsuario = (int)($row['empresa_id'] ?? 0);

                if ($empresaIdUsuario <= 0) {
                    $adminId = (int)($row['admin_id'] ?? ($_SESSION['admin_id'] ?? ($_SESSION['userData']['admin_id'] ?? 0)));
                    if ($adminId > 0) {
                        $stmtAdmin = $this->db->prepare("SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1");
                        $stmtAdmin->bindValue(':id', $adminId, PDO::PARAM_INT);
                        $stmtAdmin->execute();
                        $empresaIdUsuario = (int)$stmtAdmin->fetchColumn();
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error al resolver empresa_id de sesión en Categoria: ' . $e->getMessage());
        }

        if ($empresaIdUsuario > 0) {
            $_SESSION['empresa_id'] = $empresaIdUsuario;
            if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
                $_SESSION['userData']['empresa_id'] = $empresaIdUsuario;
            }
            return $empresaIdUsuario;
        }

        // La aplicación usa una sola empresa; no bloquear el catálogo si la sesión no trae el contexto.
        return 1;
    }

    private function getUsuarioIdSesion(): int {
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

    private function getUsuarioOwnerIdSesion(): int {
        $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
        if ($adminId > 0) {
            return $adminId;
        }
        return $this->getUsuarioIdSesion();
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
            error_log('No se pudo desactivar restricciones foráneas en categorías: ' . $e->getMessage());
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
            error_log('No se pudo activar restricciones foráneas en categorías: ' . $e->getMessage());
        }
    }

    private function asegurarTablaBackupCategorias(): void {
        $sql = "CREATE TABLE IF NOT EXISTS categorias_backups_reset (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data TEXT NOT NULL,
            creado_en TEXT NOT NULL
        )";
        $this->db->exec($sql);
    }

    private function guardarBackupCategorias(): int {
        $this->asegurarTablaBackupCategorias();
        $empresaId = $this->getEmpresaIdSesion();
        $sql = 'SELECT * FROM categorias';
        $params = [];
        if ($empresaId > 0) {
            $sql .= ' WHERE empresa_id = ?';
            $params[] = $empresaId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtInsert = $this->db->prepare('INSERT INTO categorias_backups_reset (data, creado_en) VALUES (:data, :creado_en)');
        $stmtInsert->execute([
            ':data' => json_encode($filas, JSON_UNESCAPED_UNICODE),
            ':creado_en' => date('Y-m-d H:i:s')
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function obtenerUltimoBackupCategorias(): ?array {
        $this->asegurarTablaBackupCategorias();
        $stmt = $this->db->prepare('SELECT id, data FROM categorias_backups_reset ORDER BY id DESC LIMIT 1');
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

    private function eliminarBackupCategoriasPorId(int $id): void {
        $this->asegurarTablaBackupCategorias();
        $stmt = $this->db->prepare('DELETE FROM categorias_backups_reset WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function setEmpresaId($empresaId) {
        $this->empresa_id = $empresaId;
    }

    private function tieneColumnaUsuarioId(): bool {
        if ($this->tieneUsuarioId !== null) {
            return $this->tieneUsuarioId;
        }

        try {
            $this->tieneUsuarioId = dbColumnExists($this->db, 'categorias', 'usuario_id');
            return $this->tieneUsuarioId;
        } catch (Exception $e) {
            error_log("Error verificando columna usuario_id en categorias: " . $e->getMessage());
            $this->tieneUsuarioId = false;
            return false;
        }
    }

    public function __construct($db) {
        $this->db = $db;
        $this->inicializarColumnaFechaActualizacion();
    }

    public function reiniciarCategorias(): array {
        try {
            if (!$this->esSuperAdminGlobal()) {
                throw new Exception('Permisos insuficientes para reiniciar categorías');
            }

            $empresaId = $this->getEmpresaIdSesion();
            $backupId = $this->guardarBackupCategorias();

            $this->desactivarRestriccionesForaneas();
            $this->db->beginTransaction();

            $whereEmpresa = '';
            $params = [];
            if ($empresaId > 0) {
                $whereEmpresa = ' WHERE empresa_id = ?';
                $params = [$empresaId];
            }

            $sqlDeleteCategorias = 'DELETE FROM categorias';
            $paramsDelete = [];
            if ($empresaId > 0) {
                $sqlDeleteCategorias .= ' WHERE empresa_id = ?';
                $paramsDelete[] = $empresaId;
            }
            $stmtCategorias = $this->db->prepare($sqlDeleteCategorias);
            $stmtCategorias->execute($paramsDelete);

            if ($this->esSqlite()) {
                try {
                    $this->db->exec("DELETE FROM sqlite_sequence WHERE name = 'categorias'");
                } catch (Throwable $_) {
                }
            }

            $this->db->commit();
            $this->activarRestriccionesForaneas();

            return [
                'success' => true,
                'message' => 'Categorías reiniciadas correctamente',
                'backup_id' => $backupId
            ];
        } catch (Throwable $e) {
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (Throwable $_) {
            }
            $this->activarRestriccionesForaneas();
            error_log('Error en reiniciarCategorias: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deshacerCategorias(): array {
        try {
            if (!$this->esSuperAdminGlobal()) {
                throw new Exception('Permisos insuficientes para deshacer reinicio de categorías');
            }

            $backup = $this->obtenerUltimoBackupCategorias();
            if (!$backup) {
                throw new Exception('No hay backup disponible para restaurar');
            }

            $filas = json_decode($backup['data'] ?? '[]', true);
            if (!is_array($filas)) {
                throw new Exception('Backup inválido');
            }

            $empresaId = $this->getEmpresaIdSesion();
            $this->desactivarRestriccionesForaneas();
            $this->db->beginTransaction();

            $sqlDelete = 'DELETE FROM categorias';
            $paramsDelete = [];
            if ($empresaId > 0) {
                $sqlDelete .= ' WHERE empresa_id = ?';
                $paramsDelete[] = $empresaId;
            }
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->execute($paramsDelete);

            foreach ($filas as $fila) {
                if (!is_array($fila) || empty($fila)) {
                    continue;
                }
                $cols = array_keys($fila);
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sqlInsert = 'INSERT INTO categorias (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';
                $stmtInsert = $this->db->prepare($sqlInsert);
                $stmtInsert->execute(array_values($fila));
            }

            $this->activarRestriccionesForaneas();
            $this->eliminarBackupCategoriasPorId((int)$backup['id']);
            $this->db->commit();

            return ['success' => true, 'message' => 'Restauración completada correctamente'];
        } catch (Throwable $e) {
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (Throwable $_) {
            }
            $this->activarRestriccionesForaneas();
            error_log('Error en deshacerCategorias: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function inicializarColumnaFechaActualizacion() {
        try {
            if (!$this->db) {
                return;
            }

            if ($this->existeColumna('fecha_actualizacion')) {
                $this->columnaFechaActualizacion = 'fecha_actualizacion';
                return;
            }

            if ($this->existeColumna('fecha_actulizacion')) {
                $this->columnaFechaActualizacion = 'fecha_actulizacion';
                return;
            }

            $this->db->exec("ALTER TABLE categorias ADD COLUMN IF NOT EXISTS fecha_actualizacion DATETIME NULL DEFAULT NULL AFTER fecha_creacion");
            $this->columnaFechaActualizacion = 'fecha_actualizacion';
        } catch (Exception $e) {
            // Si otro proceso ya creó la columna, evitar ruido de "Duplicate column" y continuar.
            if ($this->existeColumna('fecha_actualizacion')) {
                $this->columnaFechaActualizacion = 'fecha_actualizacion';
                return;
            }
            if ($this->existeColumna('fecha_actulizacion')) {
                $this->columnaFechaActualizacion = 'fecha_actulizacion';
                return;
            }

            error_log("Error inicializando columna fecha_actualizacion: " . $e->getMessage());
            $this->columnaFechaActualizacion = null;
        }
    }

    private function existeColumna($nombreColumna) {
        try {
            return dbColumnExists($this->db, 'categorias', (string)$nombreColumna);
        } catch (Exception $e) {
            error_log("Error verificando columna {$nombreColumna}: " . $e->getMessage());
            return false;
        }
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getNombre() {
        return $this->nombre;
    }

    public function setNombre($nombre) {
        $this->nombre = $nombre;
    }

    public function getDescripcion() {
        return $this->descripcion;
    }

    public function setDescripcion($descripcion) {
        $this->descripcion = $descripcion;
    }

    public function getImagen() {
        return $this->imagen;
    }

    public function setImagen($imagen) {
        $this->imagen = $imagen;
    }

    public function getEstado() {
        return $this->estado;
    }

    public function setEstado($estado) {
        $this->estado = $estado;
    }

    public function getFechaCreacion() {
        return $this->fecha_creacion;
    }

    public function setFechaActualizacion($fecha_actualizacion) {
        $this->fecha_actualizacion = $fecha_actualizacion;
    }

    public function getFechaActualizacion() {
        return $this->fecha_actualizacion;
    }

    public function getAll() {
        try {
            if (!$this->db) {
                return null;
            }
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $usuarioId = $this->getUsuarioIdSesion();
            $tieneUsuarioId = $this->tieneColumnaUsuarioId();
            $empresaId = $this->getEmpresaIdSesion();
            
            // Si es super admin global, permitir acceso a TODOS los datos
            $esSuperAdminGlobal = $this->esSuperAdminGlobal();
            if (!$esSuperAdminGlobal && $empresaId <= 0) {
                return [];
            }
            
            $fechaActualizacionExpr = $this->columnaFechaActualizacion ? $this->columnaFechaActualizacion : "NULL";
            
            // Si es super admin global, no filtrar por empresa_id
            if ($esSuperAdminGlobal) {
                $sql = "SELECT *, {$fechaActualizacionExpr} AS fecha_actualizacion FROM categorias WHERE 1=1";
                if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                    $sql .= " AND usuario_id = :usuario_id";
                }
                $sql .= " ORDER BY id DESC";
            } else {
                $sql = "SELECT *, {$fechaActualizacionExpr} AS fecha_actualizacion FROM categorias WHERE empresa_id = :empresa_id";
                if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                    $sql .= " AND usuario_id = :usuario_id";
                }
                $sql .= " ORDER BY id DESC";
            }
            
            $stmt = $this->db->prepare($sql);
            
            if (!$esSuperAdminGlobal) {
                $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            }
            
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            error_log("Error en getAll: " . $e->getMessage());
            return null;
        }
    }

    public function getOne() {
        try {
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $usuarioId = $this->getUsuarioIdSesion();
            $tieneUsuarioId = $this->tieneColumnaUsuarioId();
            $empresaId = $this->getEmpresaIdSesion();
            $esSuperAdminGlobal = $this->esSuperAdminGlobal();
            if (!$esSuperAdminGlobal && $empresaId <= 0) {
                return null;
            }
            $fechaActualizacionExpr = $this->columnaFechaActualizacion ? $this->columnaFechaActualizacion : "NULL";
            $sql = "SELECT *, {$fechaActualizacionExpr} AS fecha_actualizacion FROM categorias WHERE id = ?";
            if (!$esSuperAdminGlobal) {
                $sql .= " AND empresa_id = ?";
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $stmt = $this->db->prepare($sql);
            $params = [$this->getId()];
            if (!$esSuperAdminGlobal) {
                $params[] = $empresaId;
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $params[] = $usuarioId;
            }
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            error_log("Error en getOne: " . $e->getMessage());
            return null;
        }
    }

    public function save() {
        try {
            $empresaId = $this->empresa_id ?? $this->getEmpresaIdSesion();
            $esSuperAdminGlobal = $this->esSuperAdminGlobal();
            if ($empresaId <= 0 && !$esSuperAdminGlobal) {
                error_log("Error en save categoria: empresa_id no resuelto en sesion.");
                return false;
            }
            $usuarioId = $this->getUsuarioOwnerIdSesion();
            $tieneUsuarioId = $this->tieneColumnaUsuarioId();
            $tieneEmpresaId = $empresaId > 0;

            if ($tieneUsuarioId) {
                if ($tieneEmpresaId) {
                    $sql = "INSERT INTO categorias (nombre, descripcion, imagen, estado, empresa_id, usuario_id) 
                            VALUES (?, ?, ?, 1, ?, ?)";
                } else {
                    $sql = "INSERT INTO categorias (nombre, descripcion, imagen, estado, usuario_id) 
                            VALUES (?, ?, ?, 1, ?)";
                }
            } else {
                if ($tieneEmpresaId) {
                    $sql = "INSERT INTO categorias (nombre, descripcion, imagen, estado, empresa_id) 
                            VALUES (?, ?, ?, 1, ?)";
                } else {
                    $sql = "INSERT INTO categorias (nombre, descripcion, imagen, estado) 
                            VALUES (?, ?, ?, 1)";
                }
            }

            $stmt = $this->db->prepare($sql);
            $params = [
                $this->getNombre(),
                $this->getDescripcion(),
                $this->getImagen(),
            ];
            if ($tieneEmpresaId) {
                $params[] = $empresaId;
            }
            if ($tieneUsuarioId) {
                $params[] = $usuarioId > 0 ? $usuarioId : null;
            }
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Error en save: " . $e->getMessage());
            return false;
        }
    }

    public function delete() {
        $this->lastError = '';
        try {
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $empresaId = $this->getEmpresaIdSesion();
            $esSuperAdminGlobal = $this->esSuperAdminGlobal();
            if (!$esSuperAdminGlobal && $empresaId <= 0) {
                $this->lastError = 'Empresa no válida en sesión.';
                return false;
            }
            if (!$this->id) {
                $this->lastError = 'ID de categoría no establecido.';
                return false;
            }
            $usuarioId = $this->getUsuarioIdSesion();
            $tieneUsuarioId = $this->tieneColumnaUsuarioId();

            if ($this->tieneProductosAsociados()) {
                $this->lastError = 'No se puede eliminar la categoría porque tiene productos asociados. Elimina o cambia la categoría de los productos relacionados primero.';
                error_log("Error en delete categoria: " . $this->lastError);
                return false;
            }

            // Primero obtenemos la información de la categoría
            $sql = "SELECT imagen FROM categorias WHERE id = :id";
            if (!$esSuperAdminGlobal) {
                $sql .= " AND empresa_id = :empresa_id";
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = :usuario_id";
            }
            $stmt = $this->db->prepare($sql);
            $paramsSelect = [':id' => $this->id];
            if (!$esSuperAdminGlobal) {
                $paramsSelect[':empresa_id'] = $empresaId;
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $paramsSelect[':usuario_id'] = $usuarioId;
            }
            $stmt->execute($paramsSelect);
            $categoria = $stmt->fetch(PDO::FETCH_OBJ);

            // No eliminar archivo físico aquí: se conserva para histórico de actualizaciones.
            
            // Eliminar el registro de la base de datos
            $sql = "DELETE FROM categorias WHERE id = :id";
            if (!$esSuperAdminGlobal) {
                $sql .= " AND empresa_id = :empresa_id";
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = :usuario_id";
            }
            $stmt = $this->db->prepare($sql);
            $paramsDelete = [':id' => $this->id];
            if (!$esSuperAdminGlobal) {
                $paramsDelete[':empresa_id'] = $empresaId;
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $paramsDelete[':usuario_id'] = $usuarioId;
            }
            $resultado = $stmt->execute($paramsDelete);

            if ($resultado) {
                if ($stmt->rowCount() === 0) {
                    $this->lastError = 'La categoría no existe o no pertenece a esta empresa.';
                    return false;
                }
                return true;
            }

            $errorInfo = $stmt->errorInfo();
            if (is_array($errorInfo) && isset($errorInfo[2]) && trim((string)$errorInfo[2]) !== '') {
                $this->lastError = 'Error de base de datos al eliminar la categoría: ' . $errorInfo[2];
            } else {
                $this->lastError = 'Error al eliminar la categoría';
            }
            error_log('Error en delete categoria: ' . $this->lastError);
            return false;

        } catch (PDOException $e) {
            $this->lastError = 'Error al eliminar la categoría: ' . $e->getMessage();
            error_log("Error en delete: " . $e->getMessage());
            return false;
        }
    }

    public function tieneProductosAsociados(): bool {
        try {
            $usuarioId = $this->getUsuarioIdSesion();
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $sql = "SELECT COUNT(*) FROM productos WHERE categoria_id = :categoria_id";
            if ($filtrarPorUsuario && $usuarioId > 0) {
                $sql .= " AND usuario_id = :usuario_id";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':categoria_id', $this->id, PDO::PARAM_INT);
            if ($filtrarPorUsuario && $usuarioId > 0) {
                $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return ((int)$stmt->fetchColumn() > 0);
        } catch (PDOException $e) {
            error_log("Error verificando productos asociados: " . $e->getMessage());
            $this->lastError = 'Error al verificar productos asociados: ' . $e->getMessage();
            return false;
        }
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function update() {
        try {
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $empresaId = $this->getEmpresaIdSesion();
            $esSuperAdminGlobal = $this->esSuperAdminGlobal();
            if (!$esSuperAdminGlobal && $empresaId <= 0) {
                return false;
            }
            $usuarioId = $this->getUsuarioIdSesion();
            $tieneUsuarioId = $this->tieneColumnaUsuarioId();
            // Validar que tenemos el ID
            if (!$this->id) {
                error_log("Error: ID de categoría no establecido");
                return false;
            }

            // Primero obtenemos la imagen actual si existe
            $sql = "SELECT imagen FROM categorias WHERE id = ?";
            if (!$esSuperAdminGlobal) {
                $sql .= " AND empresa_id = ?";
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = ?";
            }
            $stmt = $this->db->prepare($sql);
            $paramsImagen = [$this->id];
            if (!$esSuperAdminGlobal) {
                $paramsImagen[] = $empresaId;
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $paramsImagen[] = $usuarioId;
            }
            $stmt->execute($paramsImagen);
            $imagenAntigua = $stmt->fetchColumn();

            // Construimos la consulta de actualización
            $sql = "UPDATE categorias SET nombre = ?, descripcion = ?";
            if ($this->columnaFechaActualizacion) {
                $sql .= ", {$this->columnaFechaActualizacion} = datetime('now')";
            }
            $params = [
                $this->nombre,
                $this->descripcion,
            ];

            // Si hay una nueva imagen
            if ($this->imagen) {
                $sql .= ", imagen = ?";
                $params[] = $this->imagen;
            }

            $sql .= " WHERE id = ?";
            $params[] = $this->id;
            if (!$esSuperAdminGlobal) {
                $sql .= " AND empresa_id = ?";
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

    public function updateEstado() {
        try {
            $filtrarPorUsuario = $this->debeFiltrarPorUsuario();
            $empresaId = $this->getEmpresaIdSesion();
            $esSuperAdminGlobal = $this->esSuperAdminGlobal();
            if (!$esSuperAdminGlobal && $empresaId <= 0) {
                return false;
            }
            $usuarioId = $this->getUsuarioIdSesion();
            $tieneUsuarioId = $this->tieneColumnaUsuarioId();
            $sql = "UPDATE categorias SET estado = :estado";
            if ($this->columnaFechaActualizacion) {
                $sql .= ", {$this->columnaFechaActualizacion} = datetime('now')";
            }
            $sql .= " WHERE id = :id";
            if (!$esSuperAdminGlobal) {
                $sql .= " AND empresa_id = :empresa_id";
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $sql .= " AND usuario_id = :usuario_id";
            }
            $stmt = $this->db->prepare($sql);
            $params = [
                ':estado' => $this->estado ? 1 : 0,
                ':id' => $this->id,
            ];
            if (!$esSuperAdminGlobal) {
                $params[':empresa_id'] = $empresaId;
            }
            if ($filtrarPorUsuario && $tieneUsuarioId && $usuarioId > 0) {
                $params[':usuario_id'] = $usuarioId;
            }
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error en updateEstado: " . $e->getMessage());
            return false;
        }
    }

    public function cambiarIdCategoria($idActual, $idNuevo) {
        try {
            // Verificar que el nuevo ID no exista ya
            $query = "SELECT COUNT(*) FROM categorias WHERE id = ? AND id != ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$idNuevo, $idActual]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El ID '{$idNuevo}' ya existe");
            }
            
            // Iniciar transacción para actualizar en cascada
            $this->db->beginTransaction();
            
            // Desactivar comprobaciones de clave externa temporalmente
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Actualizar referencias en la tabla productos
            try {
                $query = "UPDATE productos SET categoria_id = ? WHERE categoria_id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$idNuevo, $idActual]);
            } catch(PDOException $e) {
                error_log("Nota: No se pudo actualizar productos: " . $e->getMessage());
            }
            
            // Ahora actualizar el ID en la tabla de categorias
            $query = "UPDATE categorias SET id = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            
            if (!$stmt->execute([$idNuevo, $idActual])) {
                throw new Exception("Error al actualizar el ID de la categoría");
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
            error_log("Error en cambiarIdCategoria: " . $e->getMessage());
            throw $e;
        }
    }

    private function resolverEmpresaIdParaCreacion(): ?int {
        if ($this->empresa_id !== null && $this->empresa_id > 0) {
            return (int)$this->empresa_id;
        }

        $empresaId = $this->getEmpresaIdSesion();
        if ($empresaId > 0) {
            return $empresaId;
        }

        $usuarioId = $this->getUsuarioIdSesion();
        if ($usuarioId > 0) {
            try {
                $stmt = $this->db->prepare("SELECT empresa_id, admin_id FROM usuarios WHERE id = :id LIMIT 1");
                $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $empresaId = (int)($row['empresa_id'] ?? 0);

                if ($empresaId <= 0) {
                    $adminId = (int)($row['admin_id'] ?? 0);
                    if ($adminId > 0) {
                        $stmtAdmin = $this->db->prepare("SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1");
                        $stmtAdmin->bindValue(':id', $adminId, PDO::PARAM_INT);
                        $stmtAdmin->execute();
                        $empresaId = (int)$stmtAdmin->fetchColumn();
                    }
                }
            } catch (Exception $e) {
                error_log('Error al resolver empresa_id desde usuario en categoria: ' . $e->getMessage());
            }
        }

        if ($empresaId > 0) {
            return $empresaId;
        }

        try {
            $stmt = $this->db->query("SELECT id FROM empresas WHERE estado = 1 ORDER BY id LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['id']) && (int)$row['id'] > 0) {
                return (int)$row['id'];
            }
        } catch (Exception $e) {
            error_log('Error al resolver empresa_id por fallback en categoria: ' . $e->getMessage());
        }

        return null;
    }

    public function crear($datos) {
        try {
            $empresaId = $this->resolverEmpresaIdParaCreacion();
            $esSuperAdminGlobal = $this->esSuperAdminGlobal();
            $esAdministrador = $this->esAdministradorContexto();
            $usuarioId = $this->getUsuarioOwnerIdSesion();
            $tieneUsuarioId = $this->tieneColumnaUsuarioId();
            
            if ($empresaId === null || $empresaId <= 0) {
                $this->lastError = 'No se pudo determinar una empresa válida para crear la categoría.';
                error_log($this->lastError);
                return false;
            }
            
            $tieneEmpresaId = true;
            $sql = "INSERT INTO categorias (nombre, descripcion, imagen, estado, fecha_creacion, empresa_id";
            
            if ($tieneUsuarioId) {
                $sql .= ", usuario_id";
            }
            
            $sql .= ") VALUES (:nombre, :descripcion, :imagen, :estado, datetime('now'), :empresa_id";
            
            if ($tieneUsuarioId) {
                $sql .= ", :usuario_id";
            }
            
            $sql .= ")";
            $stmt = $this->db->prepare($sql);
            
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':descripcion', $datos['descripcion']);
            $stmt->bindParam(':imagen', $datos['imagen']);
            $stmt->bindParam(':estado', $datos['estado']);
            $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            if ($tieneUsuarioId) {
                if ($usuarioId > 0) {
                    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':usuario_id', null, PDO::PARAM_NULL);
                }
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en crear categoría: " . $e->getMessage());
            error_log("Error code: " . $e->getCode());
            error_log("SQL: " . $sql);
            error_log("Statement error info: " . json_encode($stmt->errorInfo() ?? "No error info available"));
            return false;
        }
    }
} 