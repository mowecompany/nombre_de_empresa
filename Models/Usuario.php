<?php
class Usuario {
    private $conn;
    private $table = "usuarios";
    private $encryption_key = 'clave_secreta_segura_2026'; //     cd c:\xampp\htdocs\nombre_de_empresaPOR UNA CLAVE MÁS SEGURA EN PRODUCCIÓN
    private ?string $columnaEmpresaUsuario = null;

    public $id;
    public $nombre;
    public $apellidos;
    public $correo;
    public $telefono;
    public $contrasena;
    public $rol;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function debugLoginEnabled(): bool {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }

        $raw = getenv('APP_DEBUG_LOGIN');
        if ($raw === false || $raw === null || $raw === '') {
            $enabled = false;
            return $enabled;
        }

        $val = strtolower(trim((string)$raw));
        $enabled = in_array($val, ['1', 'true', 'on', 'yes', 'si'], true);
        return $enabled;
    }

    private function debugLogin(string $message): void {
        if ($this->debugLoginEnabled()) {
            error_log($message);
        }
    }

    private function existeTabla(string $tabla): bool {
        try {
            return dbTableExists($this->conn, $tabla);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function esSuperAdmin(): bool {
        if (!empty($_SESSION['superadmin_modo_empresa'])) {
            return false;
        }

        $rol = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
        $rol = strtr($rol, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);
        return $rol === 'super administrador';
    }

    private function esSqlite(): bool {
        try {
            return strtolower((string)$this->conn->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function dbConcat(array $parts): string {
        if ($this->esSqlite()) {
            return '(' . implode(' || ', $parts) . ')';
        }
        return 'CONCAT(' . implode(', ', $parts) . ')';
    }

    private function getEmpresaIdSesion() {
        if ($this->esSuperAdmin()) {
            return 0;
        }

        $empresaIdSesion = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
        if ($empresaIdSesion > 0) {
            return $empresaIdSesion;
        }

        $empresaIdUserData = (int)($_SESSION['userData']['empresa_id'] ?? 0);
        if ($empresaIdUserData > 0) {
            return $empresaIdUserData;
        }

        $usuarioIdSesion = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
        if ($usuarioIdSesion > 0 && $this->existeColumna($this->table, $this->getColumnaEmpresaUsuario())) {
            try {
                $colEmpresa = $this->getColumnaEmpresaUsuario();
                $stmt = $this->conn->prepare("SELECT {$colEmpresa} FROM {$this->table} WHERE id = :id LIMIT 1");
                $stmt->bindValue(':id', $usuarioIdSesion, PDO::PARAM_INT);
                $stmt->execute();
                $empresaDesdeUsuario = (int)($stmt->fetchColumn() ?: 0);
                if ($empresaDesdeUsuario > 0) {
                    return $empresaDesdeUsuario;
                }
            } catch (Throwable $e) {
                error_log('Error resolviendo empresa de sesion en Usuario::getEmpresaIdSesion: ' . $e->getMessage());
            }
        }

        return 0;
    }

    private function getEmpresaIdSesionNullable(): int {
        return $this->getEmpresaIdSesion();
    }

    private function getAdminIdSesion(): ?int {
        $usuarioIdSesion = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
        if ($usuarioIdSesion <= 0) {
            return null;
        }

        $rolSesion = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
        $rolSesion = strtr($rolSesion, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);

        if ($rolSesion === 'administrador') {
            return $usuarioIdSesion;
        }

        return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
    }

    private function existeColumna(string $tabla, string $columna): bool {
        try {
            return dbColumnExists($this->conn, $tabla, $columna);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function obtenerCorreoPlanoPorUsuarioId(int $usuarioId): ?string {
        try {
            $stmt = $this->conn->prepare("SELECT correo FROM {$this->table} WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();
            $correoGuardado = $stmt->fetchColumn();
            if ($correoGuardado === false || $correoGuardado === null) {
                return null;
            }

            $correoDesencriptado = $this->desencriptar((string)$correoGuardado);
            if ($correoDesencriptado !== false && $correoDesencriptado !== null && trim((string)$correoDesencriptado) !== '') {
                return strtolower(trim((string)$correoDesencriptado));
            }

            return strtolower(trim((string)$correoGuardado));
        } catch (Throwable $e) {
            error_log('Error resolviendo correo plano por usuario en Usuario::obtenerCorreoPlanoPorUsuarioId: ' . $e->getMessage());
            return null;
        }
    }

    private function buscarUsuarioPorCorreoEnUsuarios(string $correo): ?array {
        $correo = strtolower(trim($correo));
        if ($correo === '') {
            return null;
        }

        $query = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        while ($usuario = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $correoDesencriptado = $this->desencriptar($usuario['correo']);

            if ($correoDesencriptado === null || $correoDesencriptado === false) {
                if (strtolower(trim((string)$usuario['correo'])) === $correo) {
                    $usuario['correo'] = strtolower(trim((string)$usuario['correo']));
                    $usuario['documento'] = $this->desencriptar($usuario['documento']) ?? $usuario['documento'];
                    return $usuario;
                }
                continue;
            }

            if (strtolower(trim((string)$correoDesencriptado)) === $correo) {
                $usuario['correo'] = strtolower(trim((string)$correoDesencriptado));
                $usuario['documento'] = $this->desencriptar($usuario['documento']);
                return $usuario;
            }
        }

        return null;
    }

    private function obtenerClienteLegacyPorCorreo(string $correo): ?array {
        $correo = strtolower(trim($correo));
        if ($correo === '' || !$this->existeTabla('clientes') || !$this->existeColumna('clientes', 'correo_electronico')) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare('SELECT * FROM clientes WHERE LOWER(TRIM(correo_electronico)) = :correo ORDER BY id DESC LIMIT 1');
            $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
            $stmt->execute();
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($cliente) ? $cliente : null;
        } catch (Throwable $e) {
            error_log('Error obteniendo cliente legacy por correo en Usuario::obtenerClienteLegacyPorCorreo: ' . $e->getMessage());
            return null;
        }
    }

    private function sincronizarUsuarioDesdeClienteLegacyPorCorreo(string $correo, ?string $passwordHash = null, bool $permitirPasswordTemporal = false): ?array {
        $correo = strtolower(trim($correo));
        if ($correo === '') {
            return null;
        }

        $cliente = $this->obtenerClienteLegacyPorCorreo($correo);
        if (!$cliente) {
            return null;
        }

        $usuarioExistente = $this->buscarUsuarioPorCorreoEnUsuarios($correo);
        $hashCliente = trim((string)($cliente['contrasena'] ?? ''));
        $hashFinal = trim((string)($passwordHash ?? ''));
        if ($hashFinal === '') {
            $hashFinal = $hashCliente;
        }
        if ($hashFinal === '' && $permitirPasswordTemporal) {
            $hashFinal = password_hash(str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), PASSWORD_BCRYPT);
        }

        $nombre = trim((string)($cliente['nombre'] ?? ($usuarioExistente['nombre'] ?? 'Cliente')));
        $apellidos = trim((string)($cliente['apellidos'] ?? ($usuarioExistente['apellidos'] ?? '')));
        $telefono = trim((string)($cliente['telefono'] ?? ($usuarioExistente['telefono'] ?? '')));
        $documento = trim((string)($cliente['documento'] ?? ($usuarioExistente['documento'] ?? '')));
        $tipoDocumento = trim((string)($cliente['tipo_documento'] ?? ($usuarioExistente['tipo_documento'] ?? 'Cédula de Ciudadanía')));
        $estado = isset($cliente['estado']) ? (int)$cliente['estado'] : (int)($usuarioExistente['estado'] ?? 1);
        $requiereCambio = isset($cliente['requiere_cambio_contrasena'])
            ? (int)$cliente['requiere_cambio_contrasena']
            : (int)($usuarioExistente['requiere_cambio_contrasena'] ?? 1);

        $colEmpresa = $this->getColumnaEmpresaUsuario();
        $empresaId = (int)($usuarioExistente[$colEmpresa] ?? 0);
        if ($empresaId <= 0) {
            $empresaPublica = (int)($_SESSION['store_public_empresa_id'] ?? 0);
            if ($empresaPublica > 0 && $this->empresaExistePorId($empresaPublica)) {
                $empresaId = $empresaPublica;
            }
        }

        try {
            if ($usuarioExistente) {
                $campos = [
                    'nombre = :nombre',
                    'apellidos = :apellidos',
                    'telefono = :telefono',
                    'documento = :documento',
                    'tipo_documento = :tipo_documento',
                    'rol = :rol',
                    'estado = :estado'
                ];
                if ($hashFinal !== '') {
                    $campos[] = 'contrasena = :contrasena';
                }
                if ($this->existeColumna($this->table, 'requiere_cambio_contrasena')) {
                    $campos[] = 'requiere_cambio_contrasena = :requiere_cambio_contrasena';
                }
                if ($empresaId > 0 && $this->empresaExistePorId($empresaId)) {
                    $campos[] = "{$colEmpresa} = :empresa_id";
                }

                $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $campos) . ' WHERE id = :id';
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(':id', (int)$usuarioExistente['id'], PDO::PARAM_INT);
                $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
                $stmt->bindValue(':apellidos', $apellidos, PDO::PARAM_STR);
                $stmt->bindValue(':telefono', $telefono !== '' ? $telefono : null, $telefono !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':documento', $this->encriptar($documento), PDO::PARAM_STR);
                $stmt->bindValue(':tipo_documento', $tipoDocumento, PDO::PARAM_STR);
                $stmt->bindValue(':rol', 'Cliente', PDO::PARAM_STR);
                $stmt->bindValue(':estado', $estado, PDO::PARAM_INT);
                if ($hashFinal !== '') {
                    $stmt->bindValue(':contrasena', $hashFinal, PDO::PARAM_STR);
                }
                if ($this->existeColumna($this->table, 'requiere_cambio_contrasena')) {
                    $stmt->bindValue(':requiere_cambio_contrasena', $requiereCambio, PDO::PARAM_INT);
                }
                if ($empresaId > 0 && $this->empresaExistePorId($empresaId)) {
                    $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                }
                $stmt->execute();
                $usuarioId = (int)$usuarioExistente['id'];
            } else {
                if ($hashFinal === '') {
                    return null;
                }

                $columnas = ['nombre', 'apellidos', 'correo', 'telefono', 'documento', 'tipo_documento', 'rol', 'contrasena', 'estado'];
                $valores = [':nombre', ':apellidos', ':correo', ':telefono', ':documento', ':tipo_documento', ':rol', ':contrasena', ':estado'];
                if ($this->existeColumna($this->table, $colEmpresa) && ($empresaId > 0 || $this->columnaPermiteNull($this->table, $colEmpresa))) {
                    $columnas[] = $colEmpresa;
                    $valores[] = ':empresa_id';
                }
                if ($this->existeColumna($this->table, 'requiere_cambio_contrasena')) {
                    $columnas[] = 'requiere_cambio_contrasena';
                    $valores[] = ':requiere_cambio_contrasena';
                }

                $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ')';
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
                $stmt->bindValue(':apellidos', $apellidos, PDO::PARAM_STR);
                $stmt->bindValue(':correo', $this->encriptar($correo), PDO::PARAM_STR);
                $stmt->bindValue(':telefono', $telefono !== '' ? $telefono : null, $telefono !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':documento', $this->encriptar($documento), PDO::PARAM_STR);
                $stmt->bindValue(':tipo_documento', $tipoDocumento, PDO::PARAM_STR);
                $stmt->bindValue(':rol', 'Cliente', PDO::PARAM_STR);
                $stmt->bindValue(':contrasena', $hashFinal, PDO::PARAM_STR);
                $stmt->bindValue(':estado', $estado, PDO::PARAM_INT);
                if ($this->existeColumna($this->table, $colEmpresa) && ($empresaId > 0 || $this->columnaPermiteNull($this->table, $colEmpresa))) {
                    if ($empresaId > 0 && $this->empresaExistePorId($empresaId)) {
                        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue(':empresa_id', null, PDO::PARAM_NULL);
                    }
                }
                if ($this->existeColumna($this->table, 'requiere_cambio_contrasena')) {
                    $stmt->bindValue(':requiere_cambio_contrasena', $requiereCambio, PDO::PARAM_INT);
                }
                $stmt->execute();
                $usuarioId = (int)$this->conn->lastInsertId();
            }

            $this->sincronizarClienteRelacionado($usuarioId, [
                'rol' => 'Cliente',
                'estado' => $estado,
                'contrasena' => $hashFinal !== '' ? $hashFinal : $hashCliente,
                'requiere_cambio_contrasena' => $requiereCambio,
            ], $correo);

            return $this->buscarUsuarioPorCorreoEnUsuarios($correo);
        } catch (Throwable $e) {
            error_log('Error sincronizando usuario desde cliente legacy en Usuario::sincronizarUsuarioDesdeClienteLegacyPorCorreo: ' . $e->getMessage());
            return null;
        }
    }

    public function asegurarUsuarioClientePorCorreo(string $correo, bool $permitirPasswordTemporal = false): ?array {
        return $this->buscarUsuarioPorCorreoEnUsuarios($correo);
    }

    private function sincronizarClienteRelacionado(int $usuarioId, array $campos, ?string $correoReferencia = null): void {
        try {
            if (!$this->existeColumna('clientes', 'id')) {
                return;
            }

            $clienteId = 0;
            if ($correoReferencia !== null && $correoReferencia !== '') {
                $stmt = $this->conn->query('SELECT id, correo_electronico FROM clientes ORDER BY id DESC');
                while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $correoCliente = strtolower(trim((string)$this->desencriptar($fila['correo_electronico'] ?? '')));
                    if ($correoCliente === '') {
                        $correoCliente = strtolower(trim((string)($fila['correo_electronico'] ?? '')));
                    }
                    if ($correoCliente === strtolower(trim($correoReferencia))) {
                        $clienteId = (int)($fila['id'] ?? 0);
                        break;
                    }
                }
            }

            if ($clienteId <= 0) {
                return;
            }

            $actualizaciones = [];
            $parametros = [':id' => $clienteId];
            foreach ($campos as $columna => $valor) {
                if (!$this->existeColumna('clientes', $columna)) {
                    continue;
                }

                if ($columna === 'correo_electronico' && $valor !== null && $valor !== '') {
                    $valor = $this->encriptar(strtolower(trim((string)$valor)));
                }
                if ($columna === 'documento' && $valor !== null && $valor !== '') {
                    $valor = $this->encriptar(trim((string)$valor));
                }

                $marcador = ':' . $columna;
                $actualizaciones[] = $columna . ' = ' . $marcador;
                $parametros[$marcador] = $valor;
            }

            if (empty($actualizaciones)) {
                return;
            }

            $sql = 'UPDATE clientes SET ' . implode(', ', $actualizaciones) . ' WHERE id = :id';
            $stmt = $this->conn->prepare($sql);
            foreach ($parametros as $marcador => $valor) {
                if ($valor === null) {
                    $stmt->bindValue($marcador, null, PDO::PARAM_NULL);
                } elseif (is_int($valor)) {
                    $stmt->bindValue($marcador, $valor, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($marcador, $valor, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('Error sincronizando cliente relacionado en Usuario::sincronizarClienteRelacionado: ' . $e->getMessage());
        }
    }

    private function columnaPermiteNull(string $tabla, string $columna): bool {
        try {
            return dbColumnAllowsNull($this->conn, $tabla, $columna);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function empresaExistePorId(int $empresaId): bool {
        if ($empresaId <= 0) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM empresas WHERE id = :id");
            $stmt->bindValue(':id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function obtenerEmpresaIdDisponible(): int {
        try {
            $stmt = $this->conn->query("SELECT id FROM empresas ORDER BY id ASC LIMIT 1");
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function obtenerSiguienteIdUsuario(): int {
        try {
            $stmt = $this->conn->query('SELECT MAX(id) FROM usuarios');
            $maxId = $stmt->fetchColumn();
            return ((int)($maxId ?: 0)) + 1;
        } catch (Throwable $e) {
            return 1;
        }
    }

    private function getColumnaEmpresaUsuario(): string {
        if ($this->columnaEmpresaUsuario !== null) {
            return $this->columnaEmpresaUsuario;
        }

        if ($this->existeColumna($this->table, 'empresa_id')) {
            $this->columnaEmpresaUsuario = 'empresa_id';
        } elseif ($this->existeColumna($this->table, 'id_tipos_empresa')) {
            $this->columnaEmpresaUsuario = 'id_tipos_empresa';
        } else {
            $this->columnaEmpresaUsuario = 'empresa_id';
        }

        return $this->columnaEmpresaUsuario;
    }
    
    private function esRolAdministrador($rol): bool {
        $texto = trim(mb_strtolower((string)$rol, 'UTF-8'));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);
        return $texto === 'administrador';
    }

    private function normalizarRol(string $rol): string {
        $texto = trim(mb_strtolower($rol, 'UTF-8'));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);
        $texto = preg_replace('/[^a-z0-9]+/u', '', $texto);
        return (string)$texto;
    }

    private function esRolCliente($rol): bool {
        return $this->normalizarRol((string)$rol) === 'cliente';
    }

    private function resolverEmpresaIdParaRegistro(array $data, bool $esSuperAdmin): int {
        $empresaIdSesion = $this->getEmpresaIdSesion();
        if ($empresaIdSesion > 0 && $this->empresaExistePorId($empresaIdSesion)) {
            return $empresaIdSesion;
        }

        $empresaIdEntrada = isset($data['empresa_id']) ? (int)$data['empresa_id'] : 0;
        if (($esSuperAdmin || $this->esRolCliente($data['rol'] ?? '')) && $empresaIdEntrada > 0 && $this->empresaExistePorId($empresaIdEntrada)) {
            return $empresaIdEntrada;
        }

        $empresaIdPublica = (int)($_SESSION['store_public_empresa_id'] ?? 0);
        if ($this->esRolCliente($data['rol'] ?? '') && $empresaIdPublica > 0 && $this->empresaExistePorId($empresaIdPublica)) {
            return $empresaIdPublica;
        }

        if ($this->esRolCliente($data['rol'] ?? '')) {
            $contexto = $this->obtenerPrimerContextoEmpresa();
            $empresaFallback = (int)($contexto['empresa_id'] ?? 0);
            if ($empresaFallback > 0 && $this->empresaExistePorId($empresaFallback)) {
                return $empresaFallback;
            }
        }

        return 0;
    }

    private function sincronizarAdministradoresHistoricosPorEmpresaCreadora(int $creadorId): void {
        if ($creadorId <= 0) {
            return;
        }

        if (!$this->existeColumna($this->table, 'admin_id') || !$this->existeColumna('empresas', 'usuario_creador_id')) {
            return;
        }

        $colEmpresa = $this->getColumnaEmpresaUsuario();
        if (!$this->existeColumna($this->table, $colEmpresa) || !$this->existeColumna('empresas', 'id')) {
            return;
        }

        try {
            $sql = "UPDATE {$this->table} u
                    INNER JOIN empresas e ON e.id = u.{$colEmpresa}
                    SET u.admin_id = :creador_id
                    WHERE LOWER(u.rol) = 'administrador'
                      AND e.usuario_creador_id = :creador_id_match
                      AND u.id <> :creador_id_self
                      AND (u.admin_id IS NULL OR u.admin_id = u.id)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':creador_id', $creadorId, PDO::PARAM_INT);
            $stmt->bindValue(':creador_id_match', $creadorId, PDO::PARAM_INT);
            $stmt->bindValue(':creador_id_self', $creadorId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('Error sincronizando administradores historicos por empresa creadora: ' . $e->getMessage());
        }
    }

    public function existeAdministradorEnEmpresa(int $empresaId, ?int $excluirUsuarioId = null): bool {
        try {
            if ($empresaId <= 0) {
                return false;
            }

            $colEmpresa = $this->getColumnaEmpresaUsuario();
            $sql = "SELECT COUNT(*)
                    FROM {$this->table}
                    WHERE {$colEmpresa} = :empresa_id
                      AND LOWER(rol) = 'administrador'";

            if ($excluirUsuarioId !== null && $excluirUsuarioId > 0) {
                $sql .= " AND id <> :excluir_usuario_id";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            if ($excluirUsuarioId !== null && $excluirUsuarioId > 0) {
                $stmt->bindValue(':excluir_usuario_id', $excluirUsuarioId, PDO::PARAM_INT);
            }
            $stmt->execute();

            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('Error en existeAdministradorEnEmpresa: ' . $e->getMessage());
            return false;
        }
    }

    public function obtenerContextoEmpresaPorUsuarioId($usuarioId) {
        try {
            $colEmpresa = $this->getColumnaEmpresaUsuario();
            $tieneTipoEmpresaId = $this->existeColumna('empresas', 'tipo_empresa_id');
            $tieneIdTiposEmpresa = $this->existeColumna('empresas', 'id_tipos_empresa');
            if ($tieneTipoEmpresaId && $tieneIdTiposEmpresa) {
                $exprTipoEmpresa = "COALESCE(NULLIF(e.tipo_empresa_id, 0), NULLIF(e.id_tipos_empresa, 0), 1)";
            } elseif ($tieneIdTiposEmpresa) {
                $exprTipoEmpresa = "e.id_tipos_empresa";
            } elseif ($tieneTipoEmpresaId) {
                $exprTipoEmpresa = "e.tipo_empresa_id";
            } else {
                $exprTipoEmpresa = "1";
            }

            $query = "SELECT u.{$colEmpresa} AS empresa_id, {$exprTipoEmpresa} AS tipo_empresa_id, e.nombre AS empresa_nombre
                      FROM usuarios u
                      INNER JOIN empresas e ON e.id = u.{$colEmpresa}
                      WHERE u.id = :usuario_id
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':usuario_id' => $usuarioId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error en obtenerContextoEmpresaPorUsuarioId: " . $e->getMessage());
            return null;
        }
    }

    public function obtenerContextoEmpresaPorEmpresaId(int $empresaId): ?array {
        try {
            if ($empresaId <= 0) {
                return null;
            }

            $tieneTipoEmpresaId = $this->existeColumna('empresas', 'tipo_empresa_id');
            $tieneIdTiposEmpresa = $this->existeColumna('empresas', 'id_tipos_empresa');
            if ($tieneTipoEmpresaId && $tieneIdTiposEmpresa) {
                $exprTipoEmpresa = "COALESCE(NULLIF(e.tipo_empresa_id, 0), NULLIF(e.id_tipos_empresa, 0), 1)";
            } elseif ($tieneIdTiposEmpresa) {
                $exprTipoEmpresa = "e.id_tipos_empresa";
            } elseif ($tieneTipoEmpresaId) {
                $exprTipoEmpresa = "e.tipo_empresa_id";
            } else {
                $exprTipoEmpresa = "1";
            }

            $query = "SELECT e.id AS empresa_id, {$exprTipoEmpresa} AS tipo_empresa_id, e.nombre AS empresa_nombre
                      FROM empresas e
                      WHERE e.id = :empresa_id
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return [
                'empresa_id' => (int)($row['empresa_id'] ?? 0),
                'tipo_empresa_id' => (int)($row['tipo_empresa_id'] ?? 1),
                'empresa_nombre' => (string)($row['empresa_nombre'] ?? 'Empresa'),
            ];
        } catch (PDOException $e) {
            error_log("Error en obtenerContextoEmpresaPorEmpresaId: " . $e->getMessage());
            return null;
        }
    }

    public function obtenerPrimerContextoEmpresa() {
        try {
            $colEmpresa = $this->getColumnaEmpresaUsuario();
            $tieneTipoEmpresaId = $this->existeColumna('empresas', 'tipo_empresa_id');
            $tieneIdTiposEmpresa = $this->existeColumna('empresas', 'id_tipos_empresa');
            if ($tieneTipoEmpresaId && $tieneIdTiposEmpresa) {
                $exprTipoEmpresa = "COALESCE(NULLIF(e.tipo_empresa_id, 0), NULLIF(e.id_tipos_empresa, 0), 1)";
            } elseif ($tieneIdTiposEmpresa) {
                $exprTipoEmpresa = "e.id_tipos_empresa";
            } elseif ($tieneTipoEmpresaId) {
                $exprTipoEmpresa = "e.tipo_empresa_id";
            } else {
                $exprTipoEmpresa = "1";
            }

            $query = "SELECT e.id AS empresa_id, {$exprTipoEmpresa} AS tipo_empresa_id, e.nombre AS empresa_nombre,
                             COUNT(u.id) AS total_usuarios
                      FROM empresas e
                      LEFT JOIN usuarios u ON u.{$colEmpresa} = e.id
                      GROUP BY e.id, e.nombre
                      ORDER BY total_usuarios DESC, e.id ASC
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return [
                'empresa_id' => (int)($row['empresa_id'] ?? 0),
                'tipo_empresa_id' => (int)($row['tipo_empresa_id'] ?? 1),
                'empresa_nombre' => (string)($row['empresa_nombre'] ?? 'Empresa')
            ];
        } catch (PDOException $e) {
            error_log("Error en obtenerPrimerContextoEmpresa: " . $e->getMessage());
            return null;
        }
    }

    public function recuperarContextoEmpresaParaAdministrador(int $usuarioId): ?array {
        try {
            if ($usuarioId <= 0) {
                return null;
            }

            $colEmpresa = $this->getColumnaEmpresaUsuario();
            $tieneIdTiposUsuario = $this->existeColumna($this->table, 'id_tipos_empresa');

            $cols = ["u.{$colEmpresa} AS empresa_id"];
            if ($tieneIdTiposUsuario) {
                $cols[] = "u.id_tipos_empresa";
            }

            $queryUsuario = "SELECT " . implode(', ', $cols) . " FROM {$this->table} u WHERE u.id = :usuario_id LIMIT 1";
            $stmtUsuario = $this->conn->prepare($queryUsuario);
            $stmtUsuario->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmtUsuario->execute();
            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                return null;
            }

            $empresaActual = (int)($usuario['empresa_id'] ?? 0);
            if ($empresaActual > 0) {
                return $this->obtenerContextoEmpresaPorUsuarioId($usuarioId);
            }

            $tipoUsuario = $tieneIdTiposUsuario ? (int)($usuario['id_tipos_empresa'] ?? 0) : 0;

            $contexto = null;
            if ($tipoUsuario > 0) {
                $tieneTipoEmpresaId = $this->existeColumna('empresas', 'tipo_empresa_id');
                $tieneIdTiposEmpresa = $this->existeColumna('empresas', 'id_tipos_empresa');

                $colTipoEmpresa = '';
                if ($tieneTipoEmpresaId) {
                    $colTipoEmpresa = 'tipo_empresa_id';
                } elseif ($tieneIdTiposEmpresa) {
                    $colTipoEmpresa = 'id_tipos_empresa';
                }

                if ($colTipoEmpresa !== '') {
                    $sql = "SELECT id AS empresa_id, {$colTipoEmpresa} AS tipo_empresa_id, nombre AS empresa_nombre
                            FROM empresas
                            WHERE {$colTipoEmpresa} = :tipo_empresa_id
                            ORDER BY id ASC
                            LIMIT 1";
                    $stmtEmpresaTipo = $this->conn->prepare($sql);
                    $stmtEmpresaTipo->bindValue(':tipo_empresa_id', $tipoUsuario, PDO::PARAM_INT);
                    $stmtEmpresaTipo->execute();
                    $contexto = $stmtEmpresaTipo->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }

            if (!$contexto) {
                $contexto = $this->obtenerPrimerContextoEmpresa();
            }

            if (!$contexto || (int)($contexto['empresa_id'] ?? 0) <= 0) {
                return null;
            }

            $stmtFix = $this->conn->prepare("UPDATE {$this->table} SET {$colEmpresa} = :empresa_id WHERE id = :usuario_id AND ({$colEmpresa} IS NULL OR {$colEmpresa} = 0)");
            $stmtFix->bindValue(':empresa_id', (int)$contexto['empresa_id'], PDO::PARAM_INT);
            $stmtFix->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmtFix->execute();

            return [
                'empresa_id' => (int)($contexto['empresa_id'] ?? 0),
                'tipo_empresa_id' => (int)($contexto['tipo_empresa_id'] ?? 1),
                'empresa_nombre' => (string)($contexto['empresa_nombre'] ?? 'Empresa'),
            ];
        } catch (PDOException $e) {
            error_log("Error en recuperarContextoEmpresaParaAdministrador: " . $e->getMessage());
            return null;
        }
    }

    // Métodos de encriptación y desencriptación
    private function opensslAvailable(): bool {
        return function_exists('openssl_cipher_iv_length')
            && function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt')
            && function_exists('openssl_random_pseudo_bytes');
    }

    private function encriptar($texto) {
        if (!$this->opensslAvailable()) {
            error_log('OpenSSL no está disponible. Usando texto sin encriptar para guardar datos.');
            return $texto;
        }

        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($texto, 'aes-256-cbc', hash('sha256', $this->encryption_key, true), 0, $iv);
        // Concatenar IV + texto encriptado y codificar en base64
        return base64_encode($iv . $encrypted);
    }

    private function desencriptar($texto_cifrado) {
        try {
            if ($texto_cifrado === null || $texto_cifrado === '') {
                return $texto_cifrado;
            }

            if (!$this->opensslAvailable()) {
                error_log('OpenSSL no está disponible. Devolviendo el valor original sin desencriptar.');
                return $texto_cifrado;
            }

            $iv_length = openssl_cipher_iv_length('aes-256-cbc');
            $data = base64_decode($texto_cifrado, true);

            if ($data === false || strlen($data) <= $iv_length) {
                return $texto_cifrado;
            }

            // Extraer el IV (primeros 16 bytes)
            $iv = substr($data, 0, $iv_length);
            // El resto es el texto encriptado
            $encrypted = substr($data, $iv_length);
            
            if (strlen($iv) != $iv_length) {
                return $texto_cifrado;
            }

            $desencriptado = openssl_decrypt($encrypted, 'aes-256-cbc', hash('sha256', $this->encryption_key, true), 0, $iv);
            return $desencriptado === false ? $texto_cifrado : $desencriptado;
        } catch (\Exception $e) {
            error_log("Error en desencriptación: " . $e->getMessage());
            return $texto_cifrado;
        }
    }

    public function obtenerCorreoDesencriptado($correo_encriptado) {
        return $this->desencriptar($correo_encriptado);
    }

    public function obtenerDocumentoDesencriptado($documento_encriptado) {
        return $this->desencriptar($documento_encriptado);
    }

    public function registrarUsuario($data) {
        try {
            $colEmpresa = $this->getColumnaEmpresaUsuario();
            $esSuperAdmin = $this->esSuperAdmin();
            $usarAdminId = $this->existeColumna($this->table, 'admin_id');
            $usuarioSesionId = (int)($_SESSION['usuario_id'] ?? 0);
            $tieneCodigo = $this->existeColumna($this->table, 'codigo');
            $columnas = "nombre, apellidos, correo, telefono, documento, tipo_documento, contrasena, rol, estado, {$colEmpresa}";
            $valores = ":nombre, :apellidos, :correo, :telefono, :documento, :tipo_documento, :contrasena, :rol, :estado, :empresa_id";
            if ($tieneCodigo) {
                $columnas .= ', codigo';
                $valores .= ', :codigo';
            }
            if ($usarAdminId) {
                $columnas .= ", admin_id";
                $valores .= ", :admin_id";
            }

            $query = "INSERT INTO " . $this->table . " ({$columnas}) VALUES ({$valores})";
            
            $stmt = $this->conn->prepare($query);
            
            // Hashear la contraseña
            $contrasenaHash = password_hash($data['contrasena'], PASSWORD_DEFAULT);

            // Encriptar campos sensibles
            $correoEncriptado = $this->encriptar(strtolower(trim($data['correo'])));
            
            // Asegúrate de que el documento se trate como string
            $documento = strval($data['documento']); // Convertir a string explícitamente
            $documentoEncriptado = $this->encriptar($documento);
            
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':apellidos', $data['apellidos']);
            $stmt->bindParam(':correo', $correoEncriptado);
            $stmt->bindParam(':telefono', $data['telefono']);
            $stmt->bindParam(':documento', $documentoEncriptado, PDO::PARAM_STR); // Especificar que es string
            $stmt->bindParam(':tipo_documento', $data['tipo_documento'], PDO::PARAM_STR); // Agregar tipo de documento
            $stmt->bindParam(':contrasena', $contrasenaHash);
            $stmt->bindParam(':rol', $data['rol']);
            $stmt->bindParam(':estado', $data['estado']);
            if ($tieneCodigo) {
                $codigo = trim((string)($data['codigo'] ?? ''));
                $stmt->bindValue(':codigo', $codigo !== '' ? $codigo : null, $codigo !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }
            $empresaIdValida = $this->resolverEmpresaIdParaRegistro($data, $esSuperAdmin);

            if ($empresaIdValida <= 0) {
                return [
                    'success' => false,
                    'message' => 'No existe una empresa valida en la sesion para asociar el usuario.'
                ];
            }

            if ($empresaIdValida > 0) {
                $stmt->bindValue(':empresa_id', $empresaIdValida, PDO::PARAM_INT);
            } else {
                if ($this->columnaPermiteNull($this->table, $colEmpresa)) {
                    $stmt->bindValue(':empresa_id', null, PDO::PARAM_NULL);
                } else {
                    return [
                        'success' => false,
                        'message' => 'No existe una empresa valida para asociar el usuario. Crea primero una empresa en el sistema.'
                    ];
                }
            }
            if ($usarAdminId) {
                $adminId = isset($data['admin_id']) ? (int)$data['admin_id'] : $usuarioSesionId;
                if ($adminId > 0) {
                    $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':admin_id', null, PDO::PARAM_NULL);
                }
            }
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Usuario registrado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al registrar el usuario'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
        }
    }

    public function obtenerUsuarios() {
        try {
            $usuarioSesionId = (int)($_SESSION['usuario_id'] ?? 0);
            if ($usuarioSesionId <= 0) {
                return [];
            }

            $colEmpresa = $this->getColumnaEmpresaUsuario();
            $usuarioTieneImagen = $this->existeColumna($this->table, 'imagen');
            $usuarioTieneCodigo = $this->existeColumna($this->table, 'codigo');
            $empresaTieneImagen = $this->existeColumna('empresas', 'imagen');
            
            $selectImagen = $usuarioTieneImagen ? ", u.imagen" : ", NULL AS imagen";
            $selectImagenEmpresa = $empresaTieneImagen ? ", e.imagen AS empresa_imagen" : ", NULL AS empresa_imagen";
            $selectCodigo = $this->existeColumna($this->table, 'codigo') ? ", u.codigo" : ", NULL AS codigo";
            
            $query = "SELECT u.id, u.nombre, u.apellidos, u.correo, u.telefono, u.tipo_documento, u.documento, u.rol, u.estado{$selectCodigo},
                        COALESCE(e.nombre, 'N/A') AS empresa_nombre,
                        e.tipo_empresa_id AS tipo_empresa_nombre,
                        NULL AS admin_id, 'N/A' AS administrador_nombre
                        {$selectImagen}
                        {$selectImagenEmpresa}
                     FROM " . $this->table . " u
                     LEFT JOIN empresas e ON e.id = u.{$colEmpresa}
                     ORDER BY u.id DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($usuarios as &$usuario) {
                if (isset($usuario['documento'])) {
                    $usuario['documento'] = $this->desencriptar($usuario['documento']);
                }
            }
            unset($usuario);
            return $usuarios;
        } catch(PDOException $e) {
            error_log("Error en obtenerUsuarios: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerUsuarioPorId($id) {
        $esSuperAdmin = $this->esSuperAdmin();
        $empresaIdSesion = $this->getEmpresaIdSesionNullable();
        $forzarFiltroEmpresa = !$esSuperAdmin && $empresaIdSesion > 0;
        $usuarioSesionId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

        if ($usuarioSesionId <= 0) {
            return null;
        }

        $superadminModoEmpresa = !empty($_SESSION['superadmin_modo_empresa']);
        $rolSesionNormalizado = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
        $rolSesionNormalizado = strtr($rolSesionNormalizado, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);
        $esSuperAdminSesion = ($rolSesionNormalizado === 'super administrador');
        $esSuperAdminGlobal = $esSuperAdminSesion && empty($_SESSION['superadmin_modo_empresa']);
        $colEmpresa = $this->getColumnaEmpresaUsuario();
        $usuarioTieneIdTiposEmpresa = $this->existeColumna($this->table, 'id_tipos_empresa');
        $usuarioTieneAdminId = $this->existeColumna($this->table, 'admin_id');
        $empresaTieneImagen = $this->existeColumna('empresas', 'imagen');
        $tieneTipoEmpresaId = $this->existeColumna('empresas', 'tipo_empresa_id');
        $tieneIdTiposEmpresa = $this->existeColumna('empresas', 'id_tipos_empresa');

        if ($tieneTipoEmpresaId && $tieneIdTiposEmpresa) {
            $exprTipoEmpresa = "COALESCE(NULLIF(e.tipo_empresa_id, 0), NULLIF(e.id_tipos_empresa, 0)" . ($usuarioTieneIdTiposEmpresa ? ", NULLIF(u.id_tipos_empresa, 0)" : "") . ", 1)";
        } elseif ($tieneIdTiposEmpresa) {
            $exprTipoEmpresa = $usuarioTieneIdTiposEmpresa
                ? "COALESCE(NULLIF(e.id_tipos_empresa, 0), NULLIF(u.id_tipos_empresa, 0), 1)"
                : "e.id_tipos_empresa";
        } elseif ($tieneTipoEmpresaId) {
            $exprTipoEmpresa = $usuarioTieneIdTiposEmpresa
                ? "COALESCE(NULLIF(e.tipo_empresa_id, 0), NULLIF(u.id_tipos_empresa, 0), 1)"
                : "e.tipo_empresa_id";
        } else {
            $exprTipoEmpresa = $usuarioTieneIdTiposEmpresa ? "COALESCE(NULLIF(u.id_tipos_empresa, 0), 1)" : "1";
        }

        $selectAdmin = $usuarioTieneAdminId
            ? ", u.admin_id, COALESCE(" . $this->dbConcat(['ua.nombre', "' '", 'ua.apellidos']) . ", 'N/A') AS administrador_nombre"
            : ", NULL AS admin_id, 'N/A' AS administrador_nombre";

        $joinAdmin = $usuarioTieneAdminId
            ? " LEFT JOIN " . $this->table . " ua ON ua.id = u.admin_id"
            : "";

        $selectImagenEmpresa = $empresaTieneImagen ? ", e.imagen AS empresa_imagen" : ", NULL AS empresa_imagen";

        // Seleccionar tipo_empresa_nombre directamente del valor de tipo de empresa
        $selectTipoEmpresa = ", {$exprTipoEmpresa} AS tipo_empresa_nombre";

        $query = "SELECT u.*, COALESCE(e.nombre, 'N/A') AS empresa_nombre{$selectTipoEmpresa}
                  {$selectImagenEmpresa}
                  {$selectAdmin}
                  FROM " . $this->table . " u
                  LEFT JOIN empresas e ON e.id = u.{$colEmpresa}
                  {$joinAdmin}
                  WHERE u.id = :id";
        if ((!$esSuperAdmin || $forzarFiltroEmpresa) && ((int)$id !== $usuarioSesionId)) {
            $query .= " AND LOWER(TRIM(u.rol)) <> 'cliente'";
        }
        $permitirUsuarioSesionSinFiltroEmpresa = $superadminModoEmpresa
            && $esSuperAdminSesion
            && ((int)$id === $usuarioSesionId);

        if ((!$esSuperAdmin || $forzarFiltroEmpresa) && !$permitirUsuarioSesionSinFiltroEmpresa) {
            $query .= " AND u.{$colEmpresa} = :empresa_id";
        }
        if ($esSuperAdmin && !$forzarFiltroEmpresa && !$permitirUsuarioSesionSinFiltroEmpresa && !$esSuperAdminGlobal) {
            if ($usuarioTieneAdminId) {
                $query .= " AND (u.id = :usuario_id_sesion_self OR u.admin_id = :usuario_id_sesion_admin OR (u.admin_id IS NULL AND LOWER(u.rol) <> 'super administrador'))";
            } else {
                $query .= " AND u.id = :usuario_id_sesion";
            }
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        if ((!$esSuperAdmin || $forzarFiltroEmpresa) && !$permitirUsuarioSesionSinFiltroEmpresa) {
            $stmt->bindValue(':empresa_id', $forzarFiltroEmpresa ? $empresaIdSesion : $this->getEmpresaIdSesion(), PDO::PARAM_INT);
        }
        if ($esSuperAdmin && !$forzarFiltroEmpresa && !$permitirUsuarioSesionSinFiltroEmpresa && !$esSuperAdminGlobal) {
            if ($usuarioTieneAdminId) {
                $stmt->bindValue(':usuario_id_sesion_self', $usuarioSesionId, PDO::PARAM_INT);
                $stmt->bindValue(':usuario_id_sesion_admin', $usuarioSesionId, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':usuario_id_sesion', $usuarioSesionId, PDO::PARAM_INT);
            }
        }
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Desencriptar correo y documento cuando se obtiene para el perfil
        if ($usuario) {
            $usuario['correo'] = $this->desencriptar($usuario['correo']);
            $usuario['documento'] = $this->desencriptar($usuario['documento']);
        }
        
        return $usuario;
    }

    public function actualizarUsuario($datos) {
        try {
            $esSuperAdmin = $this->esSuperAdmin();
            $esSuperAdminGlobal = $esSuperAdmin && empty($_SESSION['superadmin_modo_empresa']);
            $empresaIdSesion = $this->getEmpresaIdSesionNullable();
            $forzarFiltroEmpresa = !$esSuperAdmin && $empresaIdSesion > 0;
            $usuarioSesionId = (int)($_SESSION['usuario_id'] ?? 0);
            $permitirAutoActualizacionSinEmpresa = !$esSuperAdmin
                && !$forzarFiltroEmpresa
                && $usuarioSesionId > 0
                && (int)($datos['id'] ?? 0) === $usuarioSesionId;

            if ($usuarioSesionId <= 0) {
                return false;
            }

            $colEmpresa = $this->getColumnaEmpresaUsuario();
            $usuarioTieneAdminId = $this->existeColumna($this->table, 'admin_id');
            $usuarioTieneImagen = $this->existeColumna($this->table, 'imagen');
            $usuarioTieneCodigo = $this->existeColumna($this->table, 'codigo');
            // Encriptar correo y documento
            $correo_encriptado = $this->encriptar($datos['correo']);
            $documento_encriptado = $this->encriptar($datos['documento']);
            
            $sql = "UPDATE usuarios SET 
                    nombre = :nombre,
                    apellidos = :apellidos,
                    correo = :correo,
                    telefono = :telefono,
                    documento = :documento,
                    tipo_documento = :tipo_documento,
                    rol = :rol";

            if ($usuarioTieneCodigo && array_key_exists('codigo', $datos)) {
                $sql .= ", codigo = :codigo";
            }

            $rolObjetivoEsAdmin = $this->esRolAdministrador($datos['rol'] ?? '');
            $actualizarEmpresa = $esSuperAdmin
                && isset($datos['empresa_id'])
                && intval($datos['empresa_id']) > 0
                && $rolObjetivoEsAdmin;

            $actualizarTipoEmpresa = $esSuperAdmin
                && isset($datos['id_tipos_empresa'])
                && intval($datos['id_tipos_empresa']) > 0
                && $this->existeColumna($this->table, 'id_tipos_empresa');

            if ($actualizarEmpresa) {
                $sql .= ", {$colEmpresa} = :empresa_id_nueva";
            }
            if ($actualizarTipoEmpresa) {
                $sql .= ", id_tipos_empresa = :id_tipos_empresa_nuevo";
            }

            // Si se proporciona una nueva contraseña, actualizarla
            if (!empty($datos['contrasena'])) {
                $sql .= ", contrasena = :contrasena";
            }

            if ($usuarioTieneImagen && array_key_exists('imagen', $datos)) {
                $sql .= ", imagen = :imagen";
            }

            $sql .= " WHERE id = :id";
            if ((!$esSuperAdmin || $forzarFiltroEmpresa) && !$permitirAutoActualizacionSinEmpresa) {
                $sql .= " AND {$colEmpresa} = :empresa_id";
            }
            if ($esSuperAdmin && !$forzarFiltroEmpresa && !$esSuperAdminGlobal) {
                if ($usuarioTieneAdminId) {
                    $sql .= " AND (id = :usuario_id_sesion_self OR admin_id = :usuario_id_sesion_admin OR (admin_id IS NULL AND LOWER(rol) <> 'super administrador'))";
                } else {
                    $sql .= " AND id = :usuario_id_sesion";
                }
            }

            $stmt = $this->conn->prepare($sql);
            
            // Vincular parámetros
            $stmt->bindParam(':id', $datos['id']);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':apellidos', $datos['apellidos']);
            $stmt->bindParam(':correo', $correo_encriptado);
            $stmt->bindParam(':telefono', $datos['telefono']);
            $stmt->bindParam(':documento', $documento_encriptado);
            $stmt->bindParam(':tipo_documento', $datos['tipo_documento']);
            $stmt->bindParam(':rol', $datos['rol']);
            if ($usuarioTieneCodigo && array_key_exists('codigo', $datos)) {
                $stmt->bindValue(':codigo', trim((string)$datos['codigo']), PDO::PARAM_STR);
            }
            if ((!$esSuperAdmin || $forzarFiltroEmpresa) && !$permitirAutoActualizacionSinEmpresa) {
                $stmt->bindValue(':empresa_id', $forzarFiltroEmpresa ? $empresaIdSesion : $this->getEmpresaIdSesion(), PDO::PARAM_INT);
            }
            if ($esSuperAdmin && !$forzarFiltroEmpresa && !$esSuperAdminGlobal) {
                if ($usuarioTieneAdminId) {
                    $stmt->bindValue(':usuario_id_sesion_self', $usuarioSesionId, PDO::PARAM_INT);
                    $stmt->bindValue(':usuario_id_sesion_admin', $usuarioSesionId, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':usuario_id_sesion', $usuarioSesionId, PDO::PARAM_INT);
                }
            }
            if ($actualizarEmpresa) {
                $empresaIdNueva = intval($datos['empresa_id']);
                $stmt->bindParam(':empresa_id_nueva', $empresaIdNueva, PDO::PARAM_INT);
            }
            if ($actualizarTipoEmpresa) {
                $idTipoEmpresaNuevo = intval($datos['id_tipos_empresa']);
                $stmt->bindParam(':id_tipos_empresa_nuevo', $idTipoEmpresaNuevo, PDO::PARAM_INT);
            }

            // Si hay contraseña, vincularla
            if (!empty($datos['contrasena'])) {
                $contrasenaHash = password_hash($datos['contrasena'], PASSWORD_DEFAULT);
                $stmt->bindParam(':contrasena', $contrasenaHash);
            }

            if ($usuarioTieneImagen && array_key_exists('imagen', $datos)) {
                $imagen = trim((string)($datos['imagen'] ?? ''));
                if ($imagen === '') {
                    $stmt->bindValue(':imagen', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':imagen', substr($imagen, 0, 255), PDO::PARAM_STR);
                }
            }

            $ok = $stmt->execute();
            if ($ok) {
                $this->sincronizarClienteRelacionado((int)$datos['id'], [
                    'apellidos' => (string)$datos['apellidos'],
                    'telefono' => (string)$datos['telefono'],
                    'documento' => (string)$datos['documento'],
                    'tipo_documento' => (string)$datos['tipo_documento'],
                ], (string)$datos['correo']);
            }

            return $ok;
        } catch (PDOException $e) {
            error_log('Error en actualizarUsuario: ' . $e->getMessage());
            return false;
        }
    }

    public function eliminarUsuario($id) {
        $esSuperAdmin = $this->esSuperAdmin();
        $empresaIdSesion = $this->getEmpresaIdSesionNullable();
        $forzarFiltroEmpresa = !$esSuperAdmin && $empresaIdSesion > 0;
        $usuarioSesionId = (int)($_SESSION['usuario_id'] ?? 0);

        if ($usuarioSesionId <= 0) {
            return false;
        }

        $colEmpresa = $this->getColumnaEmpresaUsuario();
        $usuarioTieneAdminId = $this->existeColumna($this->table, 'admin_id');
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        if (!$esSuperAdmin || $forzarFiltroEmpresa) {
            $query .= " AND {$colEmpresa} = :empresa_id";
        }
        // Si es Super Administrador global (sin modo empresa), permitir eliminación de cualquier usuario
        // excepto las protecciones aplicadas en el controlador (IDs 1 y 2).
        if ($esSuperAdmin && !$forzarFiltroEmpresa) {
            // No añadir filtros adicionales: el Super Administrador puede eliminar por id.
        } else {
            // Para usuarios normales o en modo empresa, aplicar filtro por empresa
            if ($forzarFiltroEmpresa) {
                $query .= " AND {$colEmpresa} = :empresa_id";
            }
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        if ($forzarFiltroEmpresa) {
            $stmt->bindValue(':empresa_id', $empresaIdSesion, PDO::PARAM_INT);
        }
        return $stmt->execute();
    }

    public function verificarCredenciales($correo, $contrasena) {
        // Normalizar correo de entrada (minúsculas y sin espacios)
        $correo = strtolower(trim($correo));

        $this->debugLogin("--- verificarCredenciales: Comprobando '" . $correo . "' ---");
        
        // Obtener todos los usuarios (necesario porque correo está encriptado)
        $query = "SELECT * FROM " . $this->table . " WHERE estado = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        while ($usuario = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $correo_desencriptado = $this->desencriptar($usuario['correo']);
            
            // Si la desencriptación falla, probablemente es plaintext (usuario antiguo)
            if ($correo_desencriptado === null || $correo_desencriptado === false) {
                // Intentar comparar directamente en plaintext
                $correo_usuario = strtolower(trim($usuario['correo']));
                if ($correo_usuario === $correo && password_verify($contrasena, $usuario['contrasena'])) {
                    $usuario['correo'] = $correo_usuario;
                    $usuario['documento'] = $this->desencriptar($usuario['documento']) ?? $usuario['documento'];
                    $this->debugLogin("✓ Login exitoso con correo plaintext: " . $correo . " (ID: " . $usuario['id'] . ")");
                    return $usuario;
                }
                continue;
            }
            
            // Normalizar correo desencriptado (minúsculas y sin espacios)
            $correo_desencriptado = strtolower(trim($correo_desencriptado));
            
            // Comparar correo normalizado y verificar contraseña
            if ($correo_desencriptado === $correo && password_verify($contrasena, $usuario['contrasena'])) {
                // Desencriptar datos antes de devolver
                $usuario['correo'] = $correo_desencriptado;
                $usuario['documento'] = $this->desencriptar($usuario['documento']);
                $this->debugLogin("✓ Login exitoso con correo encriptado: " . $correo . " (ID: " . $usuario['id'] . ")");
                return $usuario; // Retorna el usuario si las credenciales son correctas
            }
        }

        $this->debugLogin("✗ Login fallido para correo: " . $correo);
        return false; // Retorna falso si las credenciales son incorrectas
    }

    public function obtenerUsuarioPorCorreo($correo) {
        // Normalizar correo de entrada (minúsculas y sin espacios)
        $correo = strtolower(trim($correo));

        $this->debugLogin("--- obtenerUsuarioPorCorreo: Buscando '" . $correo . "' ---");
        $usuario = $this->buscarUsuarioPorCorreoEnUsuarios($correo);
        if ($usuario) {
            return $usuario;
        }

        $this->debugLogin("✗ obtenerUsuarioPorCorreo: Correo '" . $correo . "' NO encontrado.");
        return null; // Retorna null si no existe
    }

    public function actualizarTokenRecuperacion($usuario_id, $token, $fecha_expiracion, $tipo = 'contrasena') {
        $esCorreo = ($tipo === 'correo');
        $campoToken = $esCorreo ? 'token_recuperacion_correo' : 'token_recuperacion_contrasena';
        $campoExpira = $esCorreo ? 'fecha_expiracion_token_correo' : 'fecha_expiracion_token_contrasena';

        $query = "UPDATE " . $this->table . " SET {$campoToken} = :token, {$campoExpira} = :fecha_expiracion WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $ok = $stmt->execute([
            ':token' => $token,
            ':fecha_expiracion' => $fecha_expiracion,
            ':id' => $usuario_id
        ]);

        if ($ok) {
            $this->sincronizarClienteRelacionado((int)$usuario_id, [
                $campoToken => $token,
                $campoExpira => $fecha_expiracion,
            ], $this->obtenerCorreoPlanoPorUsuarioId((int)$usuario_id));
        }
    }

    public function obtenerTokenRecuperacion($usuario_id, $tipo = 'contrasena') {
        $esCorreo = ($tipo === 'correo');
        $campoToken = $esCorreo ? 'token_recuperacion_correo' : 'token_recuperacion_contrasena';

        $query = "SELECT {$campoToken} FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $usuario_id);
        $stmt->execute();
        return $stmt->fetchColumn(); // Retorna el token si se encuentra
    }

    public function verificarCorreo($correo) {
        // Esta función intenta buscar en plaintext, pero es mejor usar obtenerUsuarioPorCorreo
        // que maneja tanto correos encriptados como plaintext (usuarios antiguos)
        $usuario = $this->obtenerUsuarioPorCorreo($correo);
        return $usuario !== null;
    }

    public function verificarCorreoExistente($correo, $idExcluir = null) {
        try {
            // Normalizar el correo (eliminar espacios y convertir a minúsculas)
            $correo = trim(strtolower($correo));

            $this->debugLogin("Verificando correo: '" . $correo . "' (idExcluir: " . ($idExcluir ?? 'null') . ")");
            
            // Obtener todos los usuarios
            $query = "SELECT id, correo FROM " . $this->table;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            while ($usuario = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Desencriptar correo para comparar
                $correo_desencriptado = trim(strtolower($this->desencriptar($usuario['correo'])));
                
                // Si coincide y no está excluido por ID
                if ($correo_desencriptado === $correo && (!$idExcluir || $usuario['id'] != $idExcluir)) {
                    $this->debugLogin("Correo encontrado duplicado - ID: " . $usuario['id']);
                    return true;
                }
            }
            
            $this->debugLogin("Correo no encontrado duplicado");
            return false;
        } catch (PDOException $e) {
            error_log("Error en verificarCorreoExistente: " . $e->getMessage());
            return false;
        }
    }

    public function verificarTelefonoExistente($telefono, $idExcluir = null) {
        try {
            $colEmpresa = $this->getColumnaEmpresaUsuario();
            // Normalizar el teléfono (eliminar espacios y convertir a string)
            $telefono = trim(strval($telefono));
            
            error_log("Verificando teléfono: '" . $telefono . "' (idExcluir: " . ($idExcluir ?? 'null') . ")");
            
            if ($idExcluir) {
                $query = "SELECT id, telefono FROM " . $this->table . " WHERE TRIM(telefono) = :telefono AND id != :id AND {$colEmpresa} = :empresa_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':telefono', $telefono, PDO::PARAM_STR);
                $stmt->bindParam(':id', $idExcluir, PDO::PARAM_INT);
            } else {
                $query = "SELECT id, telefono FROM " . $this->table . " WHERE TRIM(telefono) = :telefono AND {$colEmpresa} = :empresa_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':telefono', $telefono, PDO::PARAM_STR);
            }
            $stmt->bindValue(':empresa_id', $this->getEmpresaIdSesion(), PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado) {
                error_log("Teléfono encontrado duplicado - ID: " . $resultado['id'] . ", Teléfono: '" . $resultado['telefono'] . "'");
            } else {
                error_log("Teléfono no encontrado duplicado");
            }
            
            return $resultado !== false;
        } catch (PDOException $e) {
            error_log("Error en verificarTelefonoExistente: " . $e->getMessage());
            return false;
        }
    }

    public function verificarDocumentoExistente($documento, $idExcluir = null) {
        try {
            // Normalizar el documento (eliminar espacios y convertir a string)
            $documento = trim(strval($documento));
            
            error_log("Verificando documento: '" . $documento . "' (idExcluir: " . ($idExcluir ?? 'null') . ")");
            
            // Obtener todos los usuarios
            $query = "SELECT id, documento FROM " . $this->table;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            while ($usuario = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Desencriptar documento para comparar
                $documento_desencriptado = trim($this->desencriptar($usuario['documento']));
                
                // Si coincide y no está excluido por ID
                if ($documento_desencriptado === $documento && (!$idExcluir || $usuario['id'] != $idExcluir)) {
                    error_log("Documento encontrado duplicado - ID: " . $usuario['id']);
                    return true;
                }
            }
            
            error_log("Documento no encontrado duplicado");
            return false;
        } catch (PDOException $e) {
            error_log("Error en verificarDocumentoExistente: " . $e->getMessage());
            return false;
        }
    }

    public function verificarToken($token, $tipo = 'contrasena', $correo = null) {
        $esCorreo = ($tipo === 'correo');
        $campoToken = $esCorreo ? 'token_recuperacion_correo' : 'token_recuperacion_contrasena';
        $campoExpira = $esCorreo ? 'fecha_expiracion_token_correo' : 'fecha_expiracion_token_contrasena';

        $ahora = dbEsSqlite($this->conn) ? "datetime('now')" : "NOW()";
        $query = "SELECT * FROM " . $this->table . " WHERE {$campoToken} = :token AND {$campoExpira} > " . $ahora;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            return false;
        }

        if ($correo !== null && trim($correo) !== '') {
            $correoEntrada = strtolower(trim($correo));
            $correoUsuario = strtolower(trim($this->desencriptar($usuario['correo'])));

            if ($correoUsuario !== $correoEntrada) {
                return false;
            }
        }

        return $usuario;
    }

    public function actualizarContrasena($usuario_id, $nueva_contrasena) {
        $setAdicional = '';
        if ($this->existeColumna($this->table, 'requiere_cambio_contrasena')) {
            $setAdicional .= ", requiere_cambio_contrasena = 0";
        }

        $query = "UPDATE " . $this->table . " 
                  SET contrasena = :contrasena,
                      token_recuperacion_contrasena = NULL,
                      fecha_expiracion_token_contrasena = NULL" . $setAdicional . "
                  WHERE id = :id";
                  
        $stmt = $this->conn->prepare($query);
        $ok = $stmt->execute([
            ':contrasena' => $nueva_contrasena,
            ':id' => $usuario_id
        ]);

        if ($ok) {
            $this->sincronizarClienteRelacionado((int)$usuario_id, [
                'contrasena' => $nueva_contrasena,
                'token_recuperacion_contrasena' => null,
                'fecha_expiracion_token_contrasena' => null,
                'requiere_cambio_contrasena' => 0,
            ], $this->obtenerCorreoPlanoPorUsuarioId((int)$usuario_id));
        }

        return $ok;
    }

    public function actualizarCorreo($usuario_id, $nuevo_correo) {
        try {
            $nuevo_correo = strtolower(trim($nuevo_correo));
            $correoAnterior = $this->obtenerCorreoPlanoPorUsuarioId((int)$usuario_id);

            if (!filter_var($nuevo_correo, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'FORMATO DE CORREO NO VÁLIDO'
                ];
            }

            if ($this->verificarCorreoExistente($nuevo_correo, $usuario_id)) {
                return [
                    'success' => false,
                    'message' => 'EL CORREO YA ESTÁ REGISTRADO'
                ];
            }

            $correo_encriptado = $this->encriptar($nuevo_correo);
            $query = "UPDATE " . $this->table . "
                      SET correo = :correo,
                          token_recuperacion_correo = NULL,
                          fecha_expiracion_token_correo = NULL
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $ok = $stmt->execute([
                ':correo' => $correo_encriptado,
                ':id' => $usuario_id
            ]);

            if (!$ok) {
                return [
                    'success' => false,
                    'message' => 'NO FUE POSIBLE ACTUALIZAR EL CORREO'
                ];
            }

            $this->sincronizarClienteRelacionado((int)$usuario_id, [
                'correo_electronico' => $nuevo_correo,
                'token_recuperacion_correo' => null,
                'fecha_expiracion_token_correo' => null,
            ], $correoAnterior);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Error en actualizarCorreo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ERROR DE BASE DE DATOS AL ACTUALIZAR CORREO'
            ];
        }
    }

    public function guardarTokenRecuperacion($id, $token, $tipo = 'contrasena') {
        $esCorreo = ($tipo === 'correo');
        $campoToken = $esCorreo ? 'token_recuperacion_correo' : 'token_recuperacion_contrasena';
        $campoExpira = $esCorreo ? 'fecha_expiracion_token_correo' : 'fecha_expiracion_token_contrasena';

        if (dbEsSqlite($this->conn)) {
            $futura = "datetime('now', '+30 minutes')";
        } else {
            $futura = "NOW() + INTERVAL 30 MINUTE";
        }
        $query = "UPDATE " . $this->table . " SET {$campoToken} = :token, {$campoExpira} = " . $futura . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':id', $id);
        $ok = $stmt->execute();

        if ($ok) {
            $this->sincronizarClienteRelacionado((int)$id, [
                $campoToken => $token,
                $campoExpira => date('Y-m-d H:i:s', time() + (30 * 60)),
            ], $this->obtenerCorreoPlanoPorUsuarioId((int)$id));
        }

        return $ok;
    }

    public function cambiarEstado($id, $estado) {
        $query = "UPDATE " . $this->table . " SET estado = :estado WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':id', $id);
        $ok = $stmt->execute();

        if ($ok) {
            $this->sincronizarClienteRelacionado((int)$id, [
                'estado' => (int)$estado,
            ], $this->obtenerCorreoPlanoPorUsuarioId((int)$id));
        }

        return $ok;
    }

    public function crearUsuario($nombre, $apellidos, $correo, $telefono, $documento, $tipo_documento, $rol, $contrasena, $empresaId = null, $idTiposEmpresa = null, $imagen = null, $codigo = null) {
        // Encriptar correo y documento
        $correo_encriptado = $this->encriptar($correo);
        $documento_encriptado = $this->encriptar($documento);
        $rolNormalizado = trim(mb_strtolower((string)$rol, 'UTF-8'));
        $rolNormalizado = strtr($rolNormalizado, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);
        $esCliente = ($rolNormalizado === 'cliente');

        $colEmpresa = $this->getColumnaEmpresaUsuario();
        $usarAdminId = $this->existeColumna($this->table, 'admin_id');
        $usuarioSesionId = (int)($_SESSION['usuario_id'] ?? 0);
        $usarTipoEmpresa = ($idTiposEmpresa !== null && $this->existeColumna($this->table, 'id_tipos_empresa'));
        $usarImagen = $this->existeColumna($this->table, 'imagen');
        $usarCodigo = $this->existeColumna($this->table, 'codigo');
        $siguienteId = $this->obtenerSiguienteIdUsuario();
        $columnas = "id, nombre, apellidos, correo, telefono, documento, tipo_documento, rol, contrasena, estado, {$colEmpresa}";
        $valores = ":id, :nombre, :apellidos, :correo, :telefono, :documento, :tipo_documento, :rol, :contrasena, 1, :empresa_id";
        if ($usarAdminId) {
            $columnas .= ", admin_id";
            $valores .= ", :admin_id";
        }
        if ($usarTipoEmpresa) {
            $columnas .= ", id_tipos_empresa";
            $valores .= ", :id_tipos_empresa";
        }
        if ($usarImagen) {
            $columnas .= ", imagen";
            $valores .= ", :imagen";
        }
        if ($usarCodigo) {
            $columnas .= ", codigo";
            $valores .= ", :codigo";
        }
        
        $sql = "INSERT INTO usuarios ({$columnas}) VALUES ({$valores})";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $siguienteId, PDO::PARAM_INT);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':apellidos', $apellidos);
        $stmt->bindParam(':correo', $correo_encriptado);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':documento', $documento_encriptado);
        $stmt->bindParam(':tipo_documento', $tipo_documento);
        $stmt->bindParam(':rol', $rol);
        $empresaIdSesion = $this->getEmpresaIdSesion();
        if ($empresaIdSesion > 0 && !$this->esSuperAdmin()) {
            $empresaIdFinal = $empresaIdSesion;
        } elseif (intval($empresaId) > 0) {
            $empresaIdFinal = intval($empresaId);
        } else {
            $empresaIdFinal = $empresaIdSesion;
        }

        if ($empresaIdFinal <= 0) {
            $empresaIdFallback = $this->obtenerEmpresaIdDisponible();
            if ($empresaIdFallback > 0) {
                $empresaIdFinal = $empresaIdFallback;
                $stmt->bindParam(':empresa_id', $empresaIdFinal, PDO::PARAM_INT);
            } elseif ($esCliente && $this->columnaPermiteNull($this->table, $colEmpresa)) {
                $stmt->bindValue(':empresa_id', null, PDO::PARAM_NULL);
            } elseif ($this->columnaPermiteNull($this->table, $colEmpresa)) {
                $stmt->bindValue(':empresa_id', null, PDO::PARAM_NULL);
            } else {
                throw new RuntimeException('No existe una empresa valida para crear el usuario.');
            }
        } elseif (!$this->empresaExistePorId($empresaIdFinal)) {
            $empresaIdFallback = $this->obtenerEmpresaIdDisponible();
            if ($empresaIdFallback > 0) {
                $empresaIdFinal = $empresaIdFallback;
                $stmt->bindParam(':empresa_id', $empresaIdFinal, PDO::PARAM_INT);
            } elseif ($this->columnaPermiteNull($this->table, $colEmpresa)) {
                $stmt->bindValue(':empresa_id', null, PDO::PARAM_NULL);
            } else {
                throw new RuntimeException('No existe una empresa valida para crear el usuario.');
            }
        } else {
            $stmt->bindParam(':empresa_id', $empresaIdFinal, PDO::PARAM_INT);
        }
        if ($usarTipoEmpresa) {
            $idTiposEmpresaFinal = (int)$idTiposEmpresa;
            $stmt->bindParam(':id_tipos_empresa', $idTiposEmpresaFinal, PDO::PARAM_INT);
        }
        if ($usarImagen) {
            $imagenValor = trim((string)($imagen ?? ''));
            if ($imagenValor === '') {
                $stmt->bindValue(':imagen', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':imagen', substr($imagenValor, 0, 255), PDO::PARAM_STR);
            }
        }
        if ($usarCodigo) {
            $stmt->bindValue(':codigo', trim((string)$codigo), PDO::PARAM_STR);
        }
        if ($usarAdminId) {
            $adminIdFinal = $usuarioSesionId > 0 ? $usuarioSesionId : ($this->getAdminIdSesion() ?? 0);
            if ($adminIdFinal && $adminIdFinal > 0) {
                $stmt->bindValue(':admin_id', (int)$adminIdFinal, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':admin_id', null, PDO::PARAM_NULL);
            }
        }
        $hashedPassword = $esCliente && trim((string)$contrasena) === ''
            ? ''
            : password_hash($contrasena, PASSWORD_BCRYPT);
        $stmt->bindParam(':contrasena', $hashedPassword);
        $ok = $stmt->execute();

        return $ok;
    }

    // Métodos para gestionar intentos fallidos y bloqueos (por ID)
    public function incrementarIntentosfallidosPorId($usuario_id) {
        try {
            $query = "UPDATE " . $this->table . " SET intentos_fallidos = intentos_fallidos + 1 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $usuario_id);
            $ok = $stmt->execute();
            if ($ok) {
                $this->sincronizarClienteRelacionado((int)$usuario_id, [
                    'intentos_fallidos' => (int)$this->obtenerIntentosfallidosPorId($usuario_id),
                ], $this->obtenerCorreoPlanoPorUsuarioId((int)$usuario_id));
            }
            return $ok;
        } catch(PDOException $e) {
            error_log("Error en incrementarIntentosfallidosPorId: " . $e->getMessage());
            return false;
        }
    }

    public function resetearIntentosfallidosPorId($usuario_id) {
        try {
            $query = "UPDATE " . $this->table . " SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $usuario_id);
            $ok = $stmt->execute();
            if ($ok) {
                $this->sincronizarClienteRelacionado((int)$usuario_id, [
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                ], $this->obtenerCorreoPlanoPorUsuarioId((int)$usuario_id));
            }
            return $ok;
        } catch(PDOException $e) {
            error_log("Error en resetearIntentosfallidosPorId: " . $e->getMessage());
            return false;
        }
    }

    public function limpiarBloqueoPorId($usuario_id) {
        try {
            $query = "UPDATE " . $this->table . " SET bloqueado_hasta = NULL WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $usuario_id);
            $ok = $stmt->execute();
            if ($ok) {
                $this->sincronizarClienteRelacionado((int)$usuario_id, [
                    'bloqueado_hasta' => null,
                ], $this->obtenerCorreoPlanoPorUsuarioId((int)$usuario_id));
            }
            return $ok;
        } catch(PDOException $e) {
            error_log("Error en limpiarBloqueoPorId: " . $e->getMessage());
            return false;
        }
    }

    public function actualizarUltimaActividad($usuario_id) {
        try {
            // Verificar si el campo ultima_actividad existe
            $checkField = dbColumnExists($this->conn, $this->table, 'ultima_actividad');
            if ($checkField) {
                $ahora = dbEsSqlite($this->conn) ? "datetime('now')" : "NOW()";
                $query = "UPDATE " . $this->table . " SET ultima_actividad = " . $ahora . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $usuario_id);
                $ok = $stmt->execute();
                if ($ok) {
                    $this->sincronizarClienteRelacionado((int)$usuario_id, [
                        'ultima_actividad' => date('Y-m-d H:i:s'),
                    ], $this->obtenerCorreoPlanoPorUsuarioId((int)$usuario_id));
                }
                return $ok;
            }
            return true; // Si no existe el campo, retornar true para no romper el flujo
        } catch(PDOException $e) {
            error_log("Error en actualizarUltimaActividad: " . $e->getMessage());
            return false;
        }
    }

    public function bloquearUsuarioPorId($usuario_id, $minutos) {
        try {
            date_default_timezone_set('America/Bogota');
            $fechaBloqueo = new \DateTime('now', new \DateTimeZone(date_default_timezone_get()));
            $fechaBloqueo->modify("+$minutos minutes");
            $fecha_bloqueo = $fechaBloqueo->format('Y-m-d H:i:s');
            error_log("bloquearUsuarioPorId - Fecha calculada para bloqueo: " . $fecha_bloqueo . " (+" . $minutos . " minutos)");
            $query = "UPDATE " . $this->table . " SET bloqueado_hasta = :bloqueado_hasta WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':bloqueado_hasta', $fecha_bloqueo);
            $stmt->bindParam(':id', $usuario_id);
            $ok = $stmt->execute();
            if ($ok) {
                $this->sincronizarClienteRelacionado((int)$usuario_id, [
                    'bloqueado_hasta' => $fecha_bloqueo,
                ], $this->obtenerCorreoPlanoPorUsuarioId((int)$usuario_id));
            }
            return $ok;
        } catch(PDOException $e) {
            error_log("Error en bloquearUsuarioPorId: " . $e->getMessage());
            return false;
        }
    }

    public function verificarSiBloqueadoPorId($usuario_id) {
        try {
            $query = "SELECT bloqueado_hasta FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $usuario_id);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado && !empty($resultado['bloqueado_hasta'])) {
                $zona = new \DateTimeZone(date_default_timezone_get());
                $fechaBloqueo = \DateTime::createFromFormat('Y-m-d H:i:s', $resultado['bloqueado_hasta'], $zona);
                if (!$fechaBloqueo) {
                    $fechaBloqueo = new \DateTime($resultado['bloqueado_hasta'], $zona);
                }
                $ahora = new \DateTime('now', $zona);

                if ($fechaBloqueo > $ahora) {
                    return [
                        'bloqueado' => true,
                        'bloqueado_hasta' => $resultado['bloqueado_hasta']
                    ];
                }
            }
            return ['bloqueado' => false];
        } catch(PDOException $e) {
            error_log("Error en verificarSiBloqueadoPorId: " . $e->getMessage());
            return ['bloqueado' => false];
        }
    }

    public function obtenerIntentosfallidosPorId($usuario_id) {
        try {
            $query = "SELECT intentos_fallidos FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $usuario_id);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['intentos_fallidos'] ?? 0;
        } catch(PDOException $e) {
            error_log("Error en obtenerIntentosfallidosPorId: " . $e->getMessage());
            return 0;
        }
    }

    // Métodos antiguos para mantener compatibilidad (aunque no son recomendados)
    public function incrementarIntentosFallidos($correo) {
        $usuario = $this->obtenerUsuarioPorCorreo($correo);
        if ($usuario) {
            return $this->incrementarIntentosfallidosPorId($usuario['id']);
        }
        return false;
    }

    public function resetearIntentosFallidos($correo) {
        $usuario = $this->obtenerUsuarioPorCorreo($correo);
        if ($usuario) {
            return $this->resetearIntentosfallidosPorId($usuario['id']);
        }
        return false;
    }

    public function bloquearUsuario($correo, $minutos) {
        $usuario = $this->obtenerUsuarioPorCorreo($correo);
        if ($usuario) {
            return $this->bloquearUsuarioPorId($usuario['id'], $minutos);
        }
        return false;
    }

    public function verificarSiBloqueado($correo) {
        $usuario = $this->obtenerUsuarioPorCorreo($correo);
        if ($usuario) {
            return $this->verificarSiBloqueadoPorId($usuario['id']);
        }
        return ['bloqueado' => false];
    }

    public function obtenerIntentosFallidos($correo) {
        $usuario = $this->obtenerUsuarioPorCorreo($correo);
        if ($usuario) {
            return $this->obtenerIntentosfallidosPorId($usuario['id']);
        }
        return 0;
    }
}
?>