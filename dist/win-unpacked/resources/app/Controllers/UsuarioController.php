<?php
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
} else {
    require_once ROOT_PATH . '/Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Usuario.php';
require_once ROOT_PATH . '/Models/Rol.php';

class UsuarioController {
    private $usuarioModel;
    public $db;

    public function __construct($db) {
        $this->db = $db;
        $this->usuarioModel = new Usuario($db);
        $this->asegurarColumnaCodigoUsuario();
        $this->rellenarCodigosUsuariosExistentes();
    }

    private function asegurarColumnaCodigoUsuario(): void {
        if ($this->existeColumna('usuarios', 'codigo')) {
            return;
        }

        try {
            $this->db->exec($this->esSqlite()
                ? 'ALTER TABLE usuarios ADD COLUMN codigo VARCHAR(32)'
                : 'ALTER TABLE usuarios ADD COLUMN codigo VARCHAR(32) NULL');
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate column') === false && stripos($e->getMessage(), 'already exists') === false) {
                error_log('No se pudo crear la columna codigo en usuarios: ' . $e->getMessage());
            }
        }
    }

    private function rellenarCodigosUsuariosExistentes(): void {
        if (!$this->existeColumna('usuarios', 'codigo')) {
            return;
        }

        try {
            $stmt = $this->db->query("SELECT id, rol FROM usuarios WHERE codigo IS NULL OR TRIM(codigo) = '' ORDER BY id ASC");
            $usuariosSinCodigo = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($usuariosSinCodigo)) {
                return;
            }

            $actualizar = $this->db->prepare('UPDATE usuarios SET codigo = :codigo WHERE id = :id');
            foreach ($usuariosSinCodigo as $usuario) {
                $codigo = $this->generarCodigoUsuario((string)($usuario['rol'] ?? 'Usuario'));
                $actualizar->execute([
                    ':codigo' => $codigo,
                    ':id' => (int)$usuario['id'],
                ]);
            }
        } catch (Throwable $e) {
            error_log('No se pudieron completar códigos de usuarios existentes: ' . $e->getMessage());
        }
    }

    private function generarCodigoUsuario(string $rol, string $codigoManual = ''): string {
        $rolNormalizado = preg_replace('/[^a-z0-9]/', '', strtolower($this->normalizarRolTexto($rol)));
        $prefijo = strtoupper(substr($rolNormalizado, 0, 2));
        $prefijo = str_pad($prefijo, 2, 'X');
        $esSuperAdmin = $this->esSuperAdminGlobalSesion();
        $manual = strtoupper(trim($codigoManual));

        if ($esSuperAdmin && $manual !== '') {
            if (!preg_match('/^[A-Z0-9]{2,32}$/', $manual)) {
                throw new Exception('El código solo puede contener letras y números');
            }
            return $manual;
        }

        $maximo = 0;
        $stmt = $this->db->prepare('SELECT codigo FROM usuarios WHERE codigo LIKE :prefijo');
        $stmt->execute([':prefijo' => $prefijo . '%']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $codigoExistente) {
            $codigoExistente = strtoupper(trim((string)$codigoExistente));
            if (preg_match('/^' . preg_quote($prefijo, '/') . '(\d+)$/', $codigoExistente, $coincidencia)) {
                $maximo = max($maximo, (int)$coincidencia[1]);
            }
        }

        return $prefijo . (string)($maximo + 1);
    }

    public function obtenerProximoCodigoUsuario(string $rol): string {
        $rol = trim($rol);
        if ($rol === '') {
            throw new Exception('Debe seleccionar un rol');
        }

        return $this->generarCodigoUsuario($rol);
    }

    private function esSqlite(): bool {
        try {
            return strtolower((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
        } catch (Exception $e) {
            return false;
        }
    }

    private function generarContrasenaTemporal6Digitos(): string {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function esRolAdministrador($rol): bool {
        $texto = trim(mb_strtolower((string)$rol, 'UTF-8'));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        return $texto === 'administrador';
    }

    private function normalizarRolTexto($rol): string {
        $texto = trim(mb_strtolower((string)$rol, 'UTF-8'));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        $texto = preg_replace('/[^a-z0-9]+/u', ' ', $texto);
        $texto = trim(preg_replace('/\s+/u', ' ', (string)$texto));
        return str_replace(' ', '', $texto);
    }

    private function esSuperAdminGlobalSesion(): bool {
        return $this->normalizarRolTexto((string)($_SESSION['rol'] ?? '')) === 'superadministrador'
            && empty($_SESSION['superadmin_modo_empresa']);
    }

    private function obtenerRolesDisponiblesParaSesion(): array {
        $rolModel = new Rol($this->db);
        $roles = $rolModel->obtenerRoles();

        if (!is_array($roles)) {
            return [];
        }

        return array_values(array_filter($roles, static function ($rol) {
            return is_array($rol) && trim((string)($rol['nombre'] ?? '')) !== '';
        }));
    }

    private function obtenerRolPorNombre(string $rolNombre): ?array {
        try {
            $stmt = $this->db->prepare("SELECT id, nombre FROM roles WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre)) LIMIT 1");
            $stmt->bindValue(':nombre', $rolNombre, PDO::PARAM_STR);
            $stmt->execute();
            $rol = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($rol) ? $rol : null;
        } catch (Throwable $e) {
            error_log('Error consultando rol por nombre: ' . $e->getMessage());
            return null;
        }
    }

    private function validarRolDisponibleEnScope(string $rolObjetivo): void {
        $rolObjetivoNorm = $this->normalizarRolTexto($rolObjetivo);
        $rolSesionNorm = $this->normalizarRolTexto((string)($_SESSION['rol'] ?? ''));

        if ($rolSesionNorm === 'superadministrador') {
            $rolExistente = $this->obtenerRolPorNombre($rolObjetivo);
            if ($rolExistente) {
                return;
            }

            throw new Exception('El rol seleccionado no existe en la base de datos. Créelo en el módulo de Roles antes de asignarlo.');
        }

        if ($rolSesionNorm === 'administrador') {
            if (in_array($rolObjetivoNorm, ['superadministrador', 'administrador'], true)) {
                throw new Exception('No tienes permiso para crear o actualizar usuarios con este rol');
            }
            return;
        }

        if (in_array($rolObjetivoNorm, ['superadministrador', 'administrador'], true)) {
            throw new Exception('No tienes permiso para crear o actualizar usuarios con este rol');
        }

        return;
    }

    private function validarRolAsignablePorSesion(string $rolSesion, string $rolObjetivo, bool $esActualizacion = false, int $usuarioObjetivoId = 0): void {
        $rolSesionNorm = $this->normalizarRolTexto($rolSesion);
        $rolObjetivoNorm = $this->normalizarRolTexto($rolObjetivo);
        $usuarioSesionId = (int)($_SESSION['usuario_id'] ?? 0);
        $esActualizacionPropia = $esActualizacion && $usuarioObjetivoId === $usuarioSesionId;

        if ($rolSesionNorm === 'superadministrador') {
            $this->validarRolDisponibleEnScope($rolObjetivo);
            return;
        }

        if ($rolSesionNorm === 'administrador') {
            if (in_array($rolObjetivoNorm, ['superadministrador', 'administrador'], true)) {
                throw new Exception('Solo el Super Administrador puede asignar los roles Administrador o Super Administrador');
            }

            $this->validarRolDisponibleEnScope($rolObjetivo);
            return;
        }

        if (!in_array($rolSesionNorm, ['superadministrador', 'administrador'], true) && in_array($rolObjetivoNorm, ['superadministrador', 'administrador'], true)) {
            throw new Exception('No tienes permiso para crear o actualizar usuarios con este rol');
        }

        $this->validarRolDisponibleEnScope($rolObjetivo);
    }

    private function existeColumna(string $tabla, string $columna): bool {
        return dbColumnExists($this->db, $tabla, $columna);
    }

    private function obtenerColumnaTipoEmpresa(): string {
        if ($this->existeColumna('empresas', 'tipo_empresa_id')) {
            return 'tipo_empresa_id';
        }

        if ($this->existeColumna('empresas', 'id_tipos_empresa')) {
            return 'id_tipos_empresa';
        }

        return '';
    }

    private function existeTabla(string $tabla): bool {
        try {
            return dbTableExists($this->db, $tabla);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function obtenerEmpresaIdDisponibleDesdeDb(): int {
        try {
            $stmt = $this->db->query('SELECT id FROM empresas ORDER BY id ASC LIMIT 1');
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('Error obteniendo empresa disponible para usuario: ' . $e->getMessage());
            return 0;
        }
    }

    private function actualizarTipoEmpresaEmpresa(int $empresaId, int $tipoEmpresaId): void {
        if ($empresaId <= 0 || $tipoEmpresaId <= 0) {
            return;
        }

        $colTipoEmpresa = $this->obtenerColumnaTipoEmpresa();
        if ($colTipoEmpresa === '') {
            return; // Si no existe la columna, no hacer nada
        }

        if (!$this->existeTabla('tipos_empresa')) {
            // Si no existe la tabla tipos_empresa, no validamos en ella ni sincronizamos ese dato.
            error_log('Tabla tipos_empresa no existe. No se sincronizará el tipo de empresa en empresas.');
            return;
        }

        try {
            $stmtValidaTipo = $this->db->prepare("SELECT id FROM tipos_empresa WHERE id = :tipo_empresa_id LIMIT 1");
            $stmtValidaTipo->bindValue(':tipo_empresa_id', $tipoEmpresaId, PDO::PARAM_INT);
            $stmtValidaTipo->execute();
            if ((int)$stmtValidaTipo->fetchColumn() <= 0) {
                error_log("Advertencia: Tipo de empresa {$tipoEmpresaId} no existe. No se sincronizará en empresas.");
                return;
            }
        } catch (Throwable $e) {
            error_log('Error validando tipos_empresa: ' . $e->getMessage());
            return;
        }

        try {
            $updateQuery = $this->esSqlite()
                ? "UPDATE empresas SET {$colTipoEmpresa} = :tipo_empresa_id WHERE id = :empresa_id"
                : "UPDATE empresas SET {$colTipoEmpresa} = :tipo_empresa_id WHERE id = :empresa_id LIMIT 1";
            $stmt = $this->db->prepare($updateQuery);
            $stmt->bindValue(':tipo_empresa_id', $tipoEmpresaId, PDO::PARAM_INT);
            $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            // Registrar el error pero no fallar toda la actualización de usuario
            error_log('Error al actualizar tipo_empresa en empresas: ' . $e->getMessage());
        }
    }

    private function procesarImagenUsuarioSubida(string $campoArchivo): ?string {
        if (!isset($_FILES[$campoArchivo]) || !is_array($_FILES[$campoArchivo])) {
            return null;
        }

        $archivo = $_FILES[$campoArchivo];
        $error = isset($archivo['error']) ? (int)$archivo['error'] : UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new Exception('No se pudo cargar la imagen del usuario.');
        }

        $tmpName = (string)($archivo['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new Exception('Archivo de imagen de usuario invalido.');
        }

        $size = (int)($archivo['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new Exception('La imagen del usuario debe pesar maximo 5MB.');
        }

        $originalName = (string)($archivo['name'] ?? 'imagen');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $permitidas, true)) {
            throw new Exception('Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.');
        }

        $dirRel = 'Assets/images/Usuarios';
        $dirAbs = ROOT_PATH . '/' . $dirRel;
        if (!is_dir($dirAbs) && !mkdir($dirAbs, 0775, true) && !is_dir($dirAbs)) {
            throw new Exception('No se pudo crear la carpeta de imagenes de usuarios.');
        }

        $fileName = 'usuario_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destinoAbs = $dirAbs . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $destinoAbs)) {
            throw new Exception('No se pudo guardar la imagen del usuario.');
        }

        return $dirRel . '/' . $fileName;
    }

    private function procesarImagenEmpresaSubida(string $campoArchivo): ?string {
        $imagenInfo = guardarImagenSubidaValidada($campoArchivo, [
            'label' => 'La imagen de la empresa',
            'max_bytes' => 5 * 1024 * 1024,  // 5MB
            'allowed_mimes' => ['image/png' => 'png'],
            'exact_width' => 500,
            'exact_height' => 500,
            'require_transparency' => true,
            'rel_dirs' => ['Assets/images/Empresas', 'Assets/images/empresas'],
            'file_prefix' => 'empresa',
        ]);

        return is_array($imagenInfo) ? (string)($imagenInfo['relative_path'] ?? '') : null;
    }

    private function esIdEmpresasAutoIncrement(): bool {
        return dbColumnIsAutoIncrement($this->db, 'empresas', 'id');
    }

    private function siguienteIdEmpresa(): int {
        $stmt = $this->db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM empresas");
        return (int)($stmt ? $stmt->fetchColumn() : 1);
    }

    private function asegurarColumnaRequiereConfiguracionEmpresa(): void {
        if ($this->existeColumna('usuarios', 'requiere_configuracion_empresa')) {
            return;
        }

        try {
            $this->db->exec("ALTER TABLE usuarios ADD COLUMN requiere_configuracion_empresa TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Throwable $e) {
            error_log('No se pudo crear columna requiere_configuracion_empresa: ' . $e->getMessage());
        }
    }

    private function marcarPrimerInicioAdministrador(int $usuarioId): void {
        if ($usuarioId <= 0) {
            return;
        }

        try {
            if ($this->existeColumna('usuarios', 'requiere_cambio_contrasena')) {
                $stmt = $this->db->prepare("UPDATE usuarios SET requiere_cambio_contrasena = 1 WHERE id = :id");
                $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
                $stmt->execute();
            }

            $this->asegurarColumnaRequiereConfiguracionEmpresa();
            if ($this->existeColumna('usuarios', 'requiere_configuracion_empresa')) {
                $stmt = $this->db->prepare("UPDATE usuarios SET requiere_configuracion_empresa = 1 WHERE id = :id");
                $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
                $stmt->execute();
            }
        } catch (Throwable $e) {
            error_log('No se pudieron marcar flags de primer inicio de admin: ' . $e->getMessage());
        }
    }

    private function enviarCredencialesUsuario(array $datos): bool {
        if (!function_exists('sendEmail')) {
            return false;
        }

        $correo = strtolower(trim((string)($datos['correo'] ?? '')));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $nombre = trim((string)($datos['nombre'] ?? ''));
        $passwordTemporal = (string)($datos['contrasena'] ?? '');
        $empresaId = (int)($datos['empresa_id'] ?? 0);
        $empresaNombre = trim((string)($datos['empresa_nombre'] ?? ''));

        if ($empresaNombre === '' && function_exists('resolveEmailBranding')) {
            $brand = resolveEmailBranding([
                'empresa_id' => $empresaId,
            ]);
            $empresaNombre = trim((string)($brand['nombre'] ?? ''));
        }

        $rolNormalizado = $this->normalizarRolTexto((string)($datos['rol'] ?? ''));

        $dataEmail = [
            'asunto' => 'Credenciales de acceso - ' . ($empresaNombre !== '' ? $empresaNombre : NOMBRE_EMPRESA),
            'email' => $correo,
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresaNombre,
            'nombreUsuario' => $nombre !== '' ? $nombre : 'Usuario',
            'password' => $passwordTemporal,
            'usar_logo_cliente' => $rolNormalizado === 'cliente',
        ];

        try {
            return (bool)sendEmail($dataEmail, 'email_bienvenida');
        } catch (Throwable $e) {
            error_log('Error enviando credenciales de usuario: ' . $e->getMessage());
            return false;
        }
    }

    private function completarContextoEmpresaCorreo(array $datos): array {
        $empresaId = (int)($datos['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int)($_SESSION['empresa_id'] ?? ($_SESSION['userData']['empresa_id'] ?? 0));
        }

        if ($empresaId > 0) {
            $datos['empresa_id'] = $empresaId;
        }

        $empresaNombre = trim((string)($datos['empresa_nombre'] ?? ''));
        if ($empresaNombre === '' && $empresaId > 0) {
            try {
                $stmt = $this->db->prepare('SELECT nombre FROM empresas WHERE id = :empresa_id LIMIT 1');
                $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                $stmt->execute();
                $empresaNombre = trim((string)($stmt->fetchColumn() ?: ''));
            } catch (Throwable $e) {
                error_log('No se pudo resolver nombre de empresa para correo de usuario: ' . $e->getMessage());
            }
        }

        if ($empresaNombre === '') {
            $empresaNombre = trim((string)($_SESSION['empresa_nombre'] ?? ($_SESSION['userData']['empresa_nombre'] ?? '')));
        }

        if ($empresaNombre !== '') {
            $datos['empresa_nombre'] = $empresaNombre;
        }

        return $datos;
    }

    private function crearEmpresaParaAdministrador(array $datosEmpresa): int {
        $nombre = trim((string)($datosEmpresa['empresa_nombre'] ?? ''));
        $nit = trim((string)($datosEmpresa['empresa_nit'] ?? ''));
        $direccion = trim((string)($datosEmpresa['empresa_direccion'] ?? ''));
        $telefonoUsuario = trim((string)($datosEmpresa['telefono'] ?? ''));
        $correoUsuario = trim((string)($datosEmpresa['correo'] ?? ''));
        $nombreUsuario = trim((string)($datosEmpresa['nombre'] ?? ''));
        $apellidosUsuario = trim((string)($datosEmpresa['apellidos'] ?? ''));
        $telefonoEmpresaFormulario = trim((string)($datosEmpresa['empresa_telefono'] ?? ''));
        $correoEmpresaFormulario = trim((string)($datosEmpresa['empresa_correo_electronico'] ?? ''));

        // Regla solicitada: correo y telefono de empresa usan los mismos datos del usuario.
        $telefono = ($telefonoUsuario !== '') ? $telefonoUsuario : $telefonoEmpresaFormulario;
        $correoEmpresa = ($correoUsuario !== '') ? $correoUsuario : $correoEmpresaFormulario;
        $tipoEmpresaId = isset($datosEmpresa['empresa_tipo_empresa_id'])
            ? (int)$datosEmpresa['empresa_tipo_empresa_id']
            : (isset($datosEmpresa['id_tipos_empresa']) ? (int)$datosEmpresa['id_tipos_empresa'] : 0);
        $estado = isset($datosEmpresa['empresa_estado']) ? (int)$datosEmpresa['empresa_estado'] : 1;
        $estado = ($estado === 0) ? 0 : 1;

        if ($nombre === '') {
            $base = trim($nombreUsuario . ' ' . $apellidosUsuario);
            if ($base === '' && $correoUsuario !== '' && strpos($correoUsuario, '@') !== false) {
                $base = (string)explode('@', $correoUsuario)[0];
            }
            if ($base === '') {
                $base = 'Administrador';
            }
            $nombre = 'Empresa ' . $base;
        }

        if ($tipoEmpresaId <= 0) {
            if ($this->existeTabla('tipos_empresa')) {
                $sqlTipo = "SELECT id FROM tipos_empresa";
                if ($this->existeColumna('tipos_empresa', 'estado')) {
                    $sqlTipo .= " WHERE estado = 1";
                }
                $sqlTipo .= " ORDER BY id ASC LIMIT 1";
                try {
                    $stmtTipoDefault = $this->db->query($sqlTipo);
                    $tipoEmpresaId = (int)($stmtTipoDefault ? $stmtTipoDefault->fetchColumn() : 0);
                } catch (Throwable $e) {
                    error_log('Error consultando tipos_empresa en crearEmpresaParaAdministrador: ' . $e->getMessage());
                    $tipoEmpresaId = 0;
                }
            } else {
                // No requerimos la tabla tipos_empresa para crear empresa/admin en instalaciones locales.
                $tipoEmpresaId = 0;
            }
        }

        if ($correoEmpresa !== '' && !filter_var($correoEmpresa, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo de la empresa no es valido');
        }

        // Asegura nombre unico sin bloquear el alta del administrador.
        $nombreBase = $nombre;
        $intento = 1;
        while (true) {
            $stmtExiste = $this->db->prepare("SELECT id FROM empresas WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
            $stmtExiste->execute([':nombre' => $nombre]);
            if (!$stmtExiste->fetchColumn()) {
                break;
            }

            $intento++;
            $nombre = $nombreBase . ' ' . $intento;

            if ($intento > 100) {
                throw new Exception('No se pudo generar un nombre unico para la empresa del Administrador');
            }
        }

        if ($nit !== '' && $this->existeColumna('empresas', 'nit')) {
            $stmtNit = $this->db->prepare("SELECT id FROM empresas WHERE nit = :nit LIMIT 1");
            $stmtNit->execute([':nit' => $nit]);
            if ($stmtNit->fetchColumn()) {
                throw new Exception('Ya existe una empresa con ese NIT');
            }
        }

        $colTipoEmpresa = $this->existeColumna('empresas', 'tipo_empresa_id') ? 'tipo_empresa_id' : ($this->existeColumna('empresas', 'id_tipos_empresa') ? 'id_tipos_empresa' : '');
        if ($colTipoEmpresa !== '') {
            $columnas[] = $colTipoEmpresa;
            $valores[] = ':tipo_empresa_id';
            $params[':tipo_empresa_id'] = $tipoEmpresaId;
        }

        $usaAutoIncrement = $this->esIdEmpresasAutoIncrement();

        $columnas = ['nombre', 'estado'];
        $valores = [':nombre', ':estado'];
        $params = [
            ':nombre' => $nombre,
            ':estado' => $estado,
        ];

        $idManual = 0;
        if (!$usaAutoIncrement) {
            $idManual = $this->siguienteIdEmpresa();
            $columnas[] = 'id';
            $valores[] = ':id';
            $params[':id'] = $idManual;
        }

        if ($this->existeColumna('empresas', 'nit')) {
            $columnas[] = 'nit';
            $valores[] = ':nit';
            $params[':nit'] = ($nit !== '') ? $nit : null;
        }
        if ($this->existeColumna('empresas', 'direccion')) {
            $columnas[] = 'direccion';
            $valores[] = ':direccion';
            $params[':direccion'] = ($direccion !== '') ? $direccion : null;
        }
        if ($this->existeColumna('empresas', 'telefono')) {
            $columnas[] = 'telefono';
            $valores[] = ':telefono';
            $params[':telefono'] = ($telefono !== '') ? $telefono : null;
        }
        if ($this->existeColumna('empresas', 'correo_electronico')) {
            $columnas[] = 'correo_electronico';
            $valores[] = ':correo_electronico';
            $params[':correo_electronico'] = ($correoEmpresa !== '') ? strtolower($correoEmpresa) : null;
        } elseif ($this->existeColumna('empresas', 'email')) {
            $columnas[] = 'email';
            $valores[] = ':email';
            $params[':email'] = ($correoEmpresa !== '') ? strtolower($correoEmpresa) : null;
        }
        if ($this->existeColumna('empresas', 'fecha_registro')) {
            $columnas[] = 'fecha_registro';
            $valores[] = ':fecha_registro';
            $params[':fecha_registro'] = date('Y-m-d H:i:s');
        }
        if ($this->existeColumna('empresas', 'usuario_creador_id')) {
            $columnas[] = 'usuario_creador_id';
            $valores[] = ':usuario_creador_id';
            $params[':usuario_creador_id'] = (int)($_SESSION['usuario_id'] ?? 0);
        }
        if ($this->existeColumna('empresas', 'imagen')) {
            $imagenEmpresa = trim((string)($datosEmpresa['empresa_imagen'] ?? ''));
            $columnas[] = 'imagen';
            $valores[] = ':imagen';
            $params[':imagen'] = ($imagenEmpresa !== '') ? $imagenEmpresa : null;
        }

        $sql = "INSERT INTO empresas (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $valores) . ")";
        $stmtInsert = $this->db->prepare($sql);
        foreach ($params as $clave => $valor) {
            if (is_int($valor)) {
                $stmtInsert->bindValue($clave, $valor, PDO::PARAM_INT);
            } elseif ($valor === null) {
                $stmtInsert->bindValue($clave, null, PDO::PARAM_NULL);
            } else {
                $stmtInsert->bindValue($clave, $valor, PDO::PARAM_STR);
            }
        }
        $stmtInsert->execute();

        $empresaId = $idManual > 0 ? $idManual : (int)$this->db->lastInsertId();
        if ($empresaId > 0) {
            return $empresaId;
        }

        // Fallback para entornos donde lastInsertId no retorna el ID aunque el INSERT sea exitoso.
        $stmtFind = $this->db->prepare("SELECT id FROM empresas WHERE LOWER(nombre) = LOWER(:nombre) ORDER BY id DESC LIMIT 1");
        $stmtFind->execute([':nombre' => $nombre]);
        $empresaId = (int)($stmtFind->fetchColumn() ?: 0);
        if ($empresaId <= 0) {
            throw new Exception('Empresa insertada sin ID recuperable. Revisa AUTO_INCREMENT de empresas.id');
        }

        return $empresaId;
    }

    public function listarUsuarios() {
        return $this->usuarioModel->obtenerUsuarios();
    }

    /**
     * Elimina todos los usuarios excepto los IDs 1 y 2.
     * Solo puede ejecutarlo el Super Administrador global.
     * Retorna array con success y cantidad eliminada.
     */
    public function reiniciarUsuarios(): array {
        try {
            if (!$this->esSuperAdminGlobalSesion()) {
                throw new Exception('Permisos insuficientes para reiniciar usuarios');
            }

            $this->db->beginTransaction();

            // Guardar backup de la tabla usuarios
            $backupId = $this->guardarBackupUsuarios();

            // Contar cuántos usuarios serán eliminados
            $stmtCount = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE id NOT IN (1,2)');
            $stmtCount->execute();
            $toDelete = (int)$stmtCount->fetchColumn();

            // Ejecutar eliminación segura
            $deleteSql = 'DELETE FROM usuarios WHERE id NOT IN (1,2)';
            $stmtDel = $this->db->prepare($deleteSql);
            $stmtDel->execute();

            // Reiniciar secuencia en sqlite si aplica
            if ($this->esSqlite()) {
                try { $this->db->exec("DELETE FROM sqlite_sequence WHERE name = 'usuarios'"); } catch (Throwable $_) {}
            }

            $this->db->commit();

            return [
                'success' => true,
                'deleted' => $toDelete,
                'backup_id' => $backupId,
                'message' => "Se eliminaron {$toDelete} usuarios (excepto IDs 1 y 2)."
            ];
        } catch (Throwable $e) {
            try { if ($this->db->inTransaction()) $this->db->rollBack(); } catch (Throwable $_) {}
            error_log('Error en reiniciarUsuarios: ' . $e->getMessage());
            return [ 'success' => false, 'message' => $e->getMessage() ];
        }
    }

    private function asegurarTablaBackupUsuarios(): void {
        $sql = "CREATE TABLE IF NOT EXISTS usuarios_backups_reset (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data TEXT NOT NULL,
            creado_en TEXT NOT NULL
        )";
        $this->db->exec($sql);
    }

    private function guardarBackupUsuarios(): int {
        $this->asegurarTablaBackupUsuarios();
        $stmt = $this->db->prepare('SELECT * FROM usuarios');
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmtInsert = $this->db->prepare("INSERT INTO usuarios_backups_reset (data, creado_en) VALUES (:data, :creado_en)");
        $stmtInsert->execute([
            ':data' => json_encode($filas, JSON_UNESCAPED_UNICODE),
            ':creado_en' => date('Y-m-d H:i:s')
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function obtenerUltimoBackupUsuarios(): ?array {
        $this->asegurarTablaBackupUsuarios();
        $stmt = $this->db->prepare('SELECT id, data FROM usuarios_backups_reset ORDER BY id DESC LIMIT 1');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return ['id' => (int)$row['id'], 'data' => (string)$row['data']];
    }

    private function eliminarBackupUsuariosPorId(int $id): void {
        $this->asegurarTablaBackupUsuarios();
        $stmt = $this->db->prepare('DELETE FROM usuarios_backups_reset WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function deshacerUsuarios(): array {
        try {
            if (!$this->esSuperAdminGlobalSesion()) {
                throw new Exception('Permisos insuficientes para deshacer reinicio');
            }

            $backup = $this->obtenerUltimoBackupUsuarios();
            if (!$backup) {
                throw new Exception('No hay backup disponible para restaurar');
            }

            $filas = json_decode($backup['data'] ?? '[]', true);
            if (!is_array($filas)) {
                throw new Exception('Backup inválido');
            }

            $this->db->beginTransaction();

            // Limpiar tabla usuarios actual (mantener IDs 1 y 2 no son eliminados en restauración, pero vamos a restaurar todo)
            $this->db->exec('DELETE FROM usuarios');
            if ($this->esSqlite()) {
                $this->db->exec("DELETE FROM sqlite_sequence WHERE name = 'usuarios'");
            }

            foreach ($filas as $fila) {
                if (!is_array($fila)) continue;
                $cols = array_keys($fila);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = 'INSERT INTO usuarios (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';
                $stmt = $this->db->prepare($sql);
                $params = array_values($fila);
                $stmt->execute($params);
            }

            // Eliminar backup usado
            $this->eliminarBackupUsuariosPorId((int)$backup['id']);
            $this->db->commit();

            return ['success' => true, 'message' => 'Restauración completada a partir del último backup'];
        } catch (Throwable $e) {
            try { if ($this->db->inTransaction()) $this->db->rollBack(); } catch (Throwable $_) {}
            error_log('Error en deshacerUsuarios: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function obtenerUsuarioPorId($id) {
        try {
            if (!$id) {
                throw new Exception('ID de usuario no especificado');
            }

            $usuario = $this->usuarioModel->obtenerUsuarioPorId($id);
            if (!$usuario) {
                throw new Exception('Usuario no encontrado');
            }

            $tipoEmpresaReal = (int)($usuario['id_tipos_empresa'] ?? 0);
            $empresaIdUsuario = isset($usuario['empresa_id']) ? (int)$usuario['empresa_id'] : 0;
            if ($tipoEmpresaReal <= 0 && $empresaIdUsuario > 0) {
                $colTipoEmpresa = 'tipo_empresa_id';
                if (!dbColumnExists($this->db, 'empresas', 'tipo_empresa_id')) {
                    $colTipoEmpresa = 'id_tipos_empresa';
                }

                $stmtTipo = $this->db->prepare("SELECT {$colTipoEmpresa} FROM empresas WHERE id = :empresa_id LIMIT 1");
                $stmtTipo->bindValue(':empresa_id', $empresaIdUsuario, PDO::PARAM_INT);
                $stmtTipo->execute();
                $tipoEmpresaReal = (int)($stmtTipo->fetchColumn() ?: 0);
            }

            if ($tipoEmpresaReal > 0) {
                $usuario['id_tipos_empresa'] = $tipoEmpresaReal;
                $usuario['tipo_empresa_id'] = $tipoEmpresaReal;
            }

            // Remover la contraseña por seguridad
            unset($usuario['contrasena']);
            
            return [
                'success' => true,
                'data' => $usuario
            ];
        } catch (Exception $e) {
            error_log('Error en obtenerUsuarioPorId: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function actualizarUsuario($datos) {
        $abrioTransaccion = false;
        try {
            $rolSesion = trim((string)($_SESSION['rol'] ?? ''));
            $rolSesionNorm = $this->normalizarRolTexto($rolSesion);
            $esSuperAdminActual = ($rolSesionNorm === 'superadministrador');
            $esAdministradorActual = $this->esRolAdministrador($rolSesion);
            if (!isset($datos['id'])) {
                throw new Exception('ID de usuario no especificado');
            }

            $tieneImagenUsuario = $this->existeColumna('usuarios', 'imagen');

            // Verificar si el usuario existe
            $usuarioExistente = $this->obtenerUsuarioPorId($datos['id']);
            if (!$usuarioExistente['success']) {
                throw new Exception($usuarioExistente['message']);
            }

            $rolUsuarioObjetivoNorm = $this->normalizarRolTexto((string)($usuarioExistente['data']['rol'] ?? ''));
            $rolNuevoNorm = $this->normalizarRolTexto((string)($datos['rol'] ?? ''));
            $esUsuarioSuperAdminObjetivo = ($rolUsuarioObjetivoNorm === 'superadministrador');

            if ($esUsuarioSuperAdminObjetivo && $rolNuevoNorm !== 'superadministrador') {
                throw new Exception('No se puede cambiar el rol del usuario Super Administrador');
            }

            if ($tieneImagenUsuario) {
                $imagenActual = trim((string)($usuarioExistente['data']['imagen'] ?? ''));
                $imagenFinal = $imagenActual;
                $limpiarImagen = isset($datos['limpiar_imagen']) && (int)$datos['limpiar_imagen'] === 1;

                if ($limpiarImagen) {
                    $imagenFinal = '';
                }

                $imagenSubida = $this->procesarImagenUsuarioSubida('imagen_archivo');
                if (is_string($imagenSubida) && $imagenSubida !== '') {
                    $imagenFinal = $imagenSubida;
                }

                $datos['imagen'] = $imagenFinal;
            }

            // Validar campos requeridos
            $camposRequeridos = ['nombre', 'apellidos', 'correo', 'telefono', 'documento', 'tipo_documento', 'rol'];
            foreach ($camposRequeridos as $campo) {
                if (!isset($datos[$campo]) || empty($datos[$campo])) {
                    throw new Exception("El campo {$campo} es requerido");
                }
            }

            if (!$esUsuarioSuperAdminObjetivo) {
                $this->validarRolAsignablePorSesion($rolSesion, (string)($datos['rol'] ?? ''), true, (int)($datos['id'] ?? 0));
            }

            $idTipoEmpresa = isset($datos['id_tipos_empresa']) ? intval($datos['id_tipos_empresa']) : 0;

            if ($esSuperAdminActual && $this->esRolAdministrador($datos['rol'] ?? '')) {
                if ($idTipoEmpresa <= 0) {
                    $idTipoEmpresaExistente = (int)($usuarioExistente['data']['id_tipos_empresa'] ?? 0);
                    if ($idTipoEmpresaExistente <= 0) {
                        $idTipoEmpresaExistente = (int)($usuarioExistente['data']['tipo_empresa_id'] ?? 0);
                    }
                    $idTipoEmpresa = $idTipoEmpresaExistente;
                }

                if ($idTipoEmpresa <= 0) {
                    throw new Exception('Debe seleccionar un tipo de empresa para el usuario Administrador');
                }

                $datos['id_tipos_empresa'] = $idTipoEmpresa;
            }

            if ($this->esRolAdministrador($datos['rol'] ?? '')) {
                $empresaObjetivo = 0;
                if ($esSuperAdminActual && isset($datos['empresa_id']) && intval($datos['empresa_id']) > 0) {
                    $empresaObjetivo = intval($datos['empresa_id']);
                } else {
                    $empresaObjetivo = intval($usuarioExistente['data']['empresa_id'] ?? 0);
                }

                if ($empresaObjetivo <= 0) {
                    throw new Exception('No se pudo determinar la empresa del Administrador');
                }
            }

            $empresaObjetivoFinal = (int)($usuarioExistente['data']['empresa_id'] ?? 0);
            if ($esSuperAdminActual && isset($datos['empresa_id']) && (int)$datos['empresa_id'] > 0) {
                $empresaObjetivoFinal = (int)$datos['empresa_id'];
            }

            $debeSincronizarTipoEmpresaEnEmpresa = $esSuperAdminActual
                && $this->esRolAdministrador($datos['rol'] ?? '')
                && $empresaObjetivoFinal > 0
                && $idTipoEmpresa > 0;

            $nombreEmpresaNuevo = trim((string)($datos['empresa_nombre'] ?? ''));
            $empresaSesionId = (int)($_SESSION['empresa_id'] ?? ($_SESSION['userData']['empresa_id'] ?? 0));
            $debeActualizarNombreEmpresa = ($esSuperAdminActual || $esAdministradorActual)
                && $this->esRolAdministrador($datos['rol'] ?? '')
                && $empresaObjetivoFinal > 0
                && $nombreEmpresaNuevo !== ''
                && ($esSuperAdminActual || $empresaObjetivoFinal === $empresaSesionId);

            if ($debeActualizarNombreEmpresa && mb_strlen($nombreEmpresaNuevo, 'UTF-8') > 120) {
                throw new Exception('El nombre de la empresa no puede superar 120 caracteres');
            }

            // Normalizar datos
            $datos['correo'] = trim(strtolower($datos['correo']));
            $datos['telefono'] = trim(strval($datos['telefono']));
            $datos['documento'] = trim(strval($datos['documento']));
            $codigoExistente = trim((string)($usuarioExistente['data']['codigo'] ?? ''));
            $codigoSolicitado = $esSuperAdminActual ? trim((string)($datos['codigo'] ?? '')) : '';
            $datos['codigo'] = $codigoSolicitado !== ''
                ? $this->generarCodigoUsuario((string)$datos['rol'], $codigoSolicitado)
                : ($codigoExistente !== '' ? $codigoExistente : $this->generarCodigoUsuario((string)$datos['rol']));

            if (!ctype_digit($datos['documento'])) {
                throw new Exception('El número de documento solo debe contener dígitos');
            }

            if (strlen($datos['documento']) > 10) {
                throw new Exception('El número de documento no puede superar 10 dígitos');
            }

            error_log("ActualizarUsuario - ID: " . $datos['id']);
            error_log("ActualizarUsuario - Teléfono normalizado: '" . $datos['telefono'] . "'");
            error_log("ActualizarUsuario - Correo normalizado: '" . $datos['correo'] . "'");
            error_log("ActualizarUsuario - Documento normalizado: '" . $datos['documento'] . "'");

            // Validar que el correo no esté duplicado (excluyendo el usuario actual)
            if ($this->usuarioModel->verificarCorreoExistente($datos['correo'], $datos['id'])) {
                throw new Exception('El correo electrónico ya está registrado en el sistema');
            }

            // Validar que el teléfono no esté duplicado (excluyendo el usuario actual)
            if ($this->usuarioModel->verificarTelefonoExistente($datos['telefono'], $datos['id'])) {
                throw new Exception('El número de teléfono ya está registrado en el sistema');
            }

            // Validar que el documento no esté duplicado (excluyendo el usuario actual)
            if ($this->usuarioModel->verificarDocumentoExistente($datos['documento'], $datos['id'])) {
                throw new Exception('El número de documento ya está registrado en el sistema');
            }

            // Si la contraseña está vacía, mantener la existente
            if (empty($datos['contrasena'])) {
                unset($datos['contrasena']);
            }

            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $abrioTransaccion = true;
            }

            $resultado = $this->usuarioModel->actualizarUsuario($datos);
            if (!$resultado) {
                throw new Exception('Error al actualizar el usuario');
            }

            if ($debeSincronizarTipoEmpresaEnEmpresa) {
                $this->actualizarTipoEmpresaEmpresa($empresaObjetivoFinal, $idTipoEmpresa);
            }

            if ($debeActualizarNombreEmpresa) {
                $updateNombreQuery = $this->esSqlite()
                    ? "UPDATE empresas SET nombre = :nombre WHERE id = :empresa_id"
                    : "UPDATE empresas SET nombre = :nombre WHERE id = :empresa_id LIMIT 1";
                $stmtNombreEmpresa = $this->db->prepare($updateNombreQuery);
                $stmtNombreEmpresa->bindValue(':nombre', $nombreEmpresaNuevo, PDO::PARAM_STR);
                $stmtNombreEmpresa->bindValue(':empresa_id', $empresaObjetivoFinal, PDO::PARAM_INT);
                $stmtNombreEmpresa->execute();

                if ((int)($_SESSION['empresa_id'] ?? 0) === $empresaObjetivoFinal) {
                    $_SESSION['empresa_nombre'] = $nombreEmpresaNuevo;
                    if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
                        $_SESSION['userData']['empresa_nombre'] = $nombreEmpresaNuevo;
                    }
                }
            }

            if ($abrioTransaccion && $this->db->inTransaction()) {
                $this->db->commit();
            }

            ActualizacionesHelper::registrarCambio(
                'usuarios',
                'actualizar',
                $usuarioExistente['data'],
                $datos,
                ['usuario_id_actualizado' => (int)$datos['id']]
            );

            return [
                'success' => true,
                'message' => 'Usuario actualizado correctamente'
            ];
        } catch (Exception $e) {
            if ($abrioTransaccion && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error en actualizarUsuario: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function registrarUsuario($datos) {
        try {
            $rolSesion = trim((string)($_SESSION['rol'] ?? ''));
            $esSuperAdminActual = $this->normalizarRolTexto($rolSesion) === 'superadministrador';
            $rolSolicitadoNorm = $this->normalizarRolTexto((string)($datos['rol'] ?? ''));
            $esClienteSolicitado = $rolSolicitadoNorm === 'cliente';

            $esNuevoAdminSolicitado = $this->esRolAdministrador((string)($datos['rol'] ?? ''));
            if (!$esClienteSolicitado && (!isset($datos['contrasena']) || trim((string)$datos['contrasena']) === '')) {
                throw new Exception('La contraseña es requerida para el registro de usuario');
            }

            // Validar datos requeridos
            $camposRequeridos = ['nombre', 'apellidos', 'correo', 'telefono', 'documento', 'tipo_documento', 'rol'];
            foreach ($camposRequeridos as $campo) {
                if (!isset($datos[$campo]) || empty($datos[$campo])) {
                    throw new Exception("El campo {$campo} es requerido");
                }
            }

            $datos['contrasena'] = trim((string)($datos['contrasena'] ?? ''));
            if (!$esClienteSolicitado) {
                $this->validarContrasenaSegura($datos['contrasena'], trim((string)($datos['documento'] ?? '')));
            }

            $idTipoEmpresa = isset($datos['id_tipos_empresa']) ? intval($datos['id_tipos_empresa']) : 0;
            $tieneImagenUsuario = $this->existeColumna('usuarios', 'imagen');

            if ($tieneImagenUsuario) {
                $imagenSubida = $this->procesarImagenUsuarioSubida('imagen_archivo');
                $datos['imagen'] = is_string($imagenSubida) ? $imagenSubida : null;
            }

            if ($esSuperAdminActual && $this->esRolAdministrador($datos['rol'] ?? '')) {
                if ($this->existeColumna('empresas', 'imagen')) {
                    $imagenEmpresaSubida = $this->procesarImagenEmpresaSubida('empresa_imagen_archivo');
                    $datos['empresa_imagen'] = is_string($imagenEmpresaSubida) ? $imagenEmpresaSubida : '';
                }

                // Flujo de alta conjunta: empresa + administrador en una sola transaccion.
                $this->db->beginTransaction();

                $nuevaEmpresaId = $this->crearEmpresaParaAdministrador($datos);
                if ($nuevaEmpresaId <= 0) {
                    throw new Exception('No se pudo crear la empresa para el Administrador');
                }

                if ($idTipoEmpresa <= 0) {
                    $colTipo = $this->existeColumna('empresas', 'tipo_empresa_id') ? 'tipo_empresa_id' : ($this->existeColumna('empresas', 'id_tipos_empresa') ? 'id_tipos_empresa' : '');
                    if ($colTipo !== '') {
                        $stmtTipoEmpresa = $this->db->prepare("SELECT {$colTipo} FROM empresas WHERE id = :id LIMIT 1");
                        $stmtTipoEmpresa->bindValue(':id', $nuevaEmpresaId, PDO::PARAM_INT);
                        $stmtTipoEmpresa->execute();
                        $idTipoEmpresa = (int)($stmtTipoEmpresa->fetchColumn() ?: 0);
                    }
                }

                $datos['empresa_id'] = $nuevaEmpresaId;
                $datos['id_tipos_empresa'] = $idTipoEmpresa > 0 ? $idTipoEmpresa : null;

            } elseif ($this->esRolAdministrador($datos['rol'] ?? '')) {
                $empresaObjetivo = isset($datos['empresa_id']) ? intval($datos['empresa_id']) : 0;
                if ($empresaObjetivo <= 0) {
                    $empresaObjetivo = (int)($_SESSION['empresa_id'] ?? 0);
                }

                if ($empresaObjetivo <= 0) {
                    $empresaObjetivo = $this->obtenerEmpresaIdDisponibleDesdeDb();
                }

                if ($empresaObjetivo > 0) {
                    $datos['empresa_id'] = $empresaObjetivo;

                } else {
                    $datos['empresa_id'] = null;
                }
            }

            // Si quien crea es Administrador, el nuevo usuario queda asociado como su empleado.
            if ($this->esRolAdministrador($rolSesion)) {
                $datos['admin_id'] = (int)($_SESSION['usuario_id'] ?? 0);
            }

            $datos = $this->completarContextoEmpresaCorreo($datos);

            // Normalizar datos
            $datos['correo'] = trim(strtolower($datos['correo']));
            $datos['telefono'] = trim(strval($datos['telefono']));
            $datos['documento'] = trim(strval($datos['documento']));
            $datos['codigo'] = $this->generarCodigoUsuario(
                (string)$datos['rol'],
                ''
            );

            if (!ctype_digit($datos['documento'])) {
                throw new Exception('El número de documento solo debe contener dígitos');
            }

            if (strlen($datos['documento']) > 10) {
                throw new Exception('El número de documento no puede superar 10 dígitos');
            }

            error_log("RegistrarUsuario - Teléfono normalizado: '" . $datos['telefono'] . "'");
            error_log("RegistrarUsuario - Correo normalizado: '" . $datos['correo'] . "'");
            error_log("RegistrarUsuario - Documento normalizado: '" . $datos['documento'] . "'");

            // Validar que el correo no esté duplicado
            if ($this->usuarioModel->verificarCorreoExistente($datos['correo'])) {
                throw new Exception('El correo electrónico ya está registrado en el sistema');
            }

            // Validar que el teléfono no esté duplicado
            if ($this->usuarioModel->verificarTelefonoExistente($datos['telefono'])) {
                throw new Exception('El número de teléfono ya está registrado en el sistema');
            }

            // Validar que el documento no esté duplicado
            if ($this->usuarioModel->verificarDocumentoExistente($datos['documento'])) {
                throw new Exception('El número de documento ya está registrado en el sistema');
            }

            // Validar permisos según el rol del usuario actual
            $rolActual = $_SESSION['rol'] ?? '';
            $rolNuevo = $datos['rol'];
            $this->validarRolAsignablePorSesion((string)$rolActual, (string)$rolNuevo);

            $resultado = $this->usuarioModel->crearUsuario(
                $datos['nombre'],
                $datos['apellidos'],
                $datos['correo'],
                $datos['telefono'],
                $datos['documento'],
                $datos['tipo_documento'],
                $datos['rol'],
                $datos['contrasena'],
                isset($datos['empresa_id']) ? intval($datos['empresa_id']) : null,
                $idTipoEmpresa > 0 ? $idTipoEmpresa : null,
                isset($datos['imagen']) ? trim((string)$datos['imagen']) : null,
                (string)$datos['codigo']
            );

            if (!$resultado) {
                throw new Exception('Error al crear el usuario');
            }

            $esNuevoAdmin = $this->esRolAdministrador($datos['rol'] ?? '');
            $nuevoUsuarioId = is_numeric($resultado) ? (int)$resultado : (int)$this->db->lastInsertId();

            if ($esNuevoAdmin && $nuevoUsuarioId <= 0) {
                $usuarioCreado = $this->usuarioModel->obtenerUsuarioPorCorreo((string)($datos['correo'] ?? ''));
                $nuevoUsuarioId = (int)($usuarioCreado['id'] ?? 0);
            }

            if ($esNuevoAdmin && $nuevoUsuarioId > 0) {
                $this->marcarPrimerInicioAdministrador($nuevoUsuarioId);
            }

            if ($esSuperAdminActual && $this->esRolAdministrador($datos['rol'] ?? '') && $this->db->inTransaction()) {
                $this->db->commit();
            }

            $correoEnviado = $esClienteSolicitado
                ? false
                : $this->enviarCredencialesUsuario($datos);

            $mensajeExito = 'Usuario creado exitosamente';
            if (!$correoEnviado) {
                $mensajeExito = 'Usuario creado exitosamente, pero no se pudo enviar el correo de credenciales';
            }

            return [
                'success' => true,
                'message' => $mensajeExito,
                'correo_enviado' => $correoEnviado,
                'es_administrador' => $esNuevoAdmin,
                'correo_destino' => trim((string)($datos['correo'] ?? '')),
                'notificacion_correo' => $correoEnviado
                    ? 'Credenciales enviadas. Pide al usuario revisar primero la bandeja principal y, si no aparece, spam/no deseado.'
                    : 'No se pudo enviar el correo automaticamente. Comparte la clave temporal manualmente y revisa configuracion SMTP.'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error en registrarUsuario: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function eliminarUsuario($id) {
        try {
            if (!$id) {
                throw new Exception('ID de usuario no especificado');
            }

            $id = (int)$id;
            if (in_array($id, [1, 2], true)) {
                throw new Exception('No se puede eliminar este usuario');
            }

            $resultado = $this->usuarioModel->eliminarUsuario($id);
            if (!$resultado) {
                throw new Exception('Error al eliminar el usuario');
            }

            return [
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ];
        } catch (Exception $e) {
            error_log('Error en eliminarUsuario: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function validarContrasenaSegura(string $contrasena, string $documento = ''): void {
        $contrasena = trim($contrasena);
        if ($contrasena === '') {
            throw new Exception('La contraseña es requerida');
        }

        if (mb_strlen($contrasena, 'UTF-8') !== 6) {
            throw new Exception('La contraseña debe tener exactamente 6 dígitos');
        }

        if (!preg_match('/^\d{6}$/', $contrasena)) {
            throw new Exception('La contraseña solo puede contener números');
        }

        if (preg_match('/\s/', $contrasena)) {
            throw new Exception('La contraseña no debe contener espacios');
        }

        $documento = preg_replace('/\D/', '', $documento) ?? '';
        $valoresDocumento = [];
        if ($documento !== '') {
            $valoresDocumento[] = $documento;
            if (strlen($documento) >= 4) {
                $valoresDocumento[] = substr($documento, 0, 4);
                $valoresDocumento[] = substr($documento, -4);
            }
            if (strlen($documento) >= 6) {
                $valoresDocumento[] = substr($documento, 0, 6);
                $valoresDocumento[] = substr($documento, -6);
            }
        }

        foreach ($valoresDocumento as $valor) {
            if ($valor !== '' && stripos($contrasena, $valor) !== false) {
                throw new Exception('La contraseña no puede contener el número de documento ni sus primeros o últimos dígitos');
            }
        }

        $contrasenaLower = mb_strtolower($contrasena, 'UTF-8');
        $secuenciasNumericas = ['123456', '654321', '012345', '543210', '12345', '54321', '1234', '4321', '123', '321', '12', '21', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999', '0000', '0123', '3210'];
        foreach ($secuenciasNumericas as $secuencia) {
            if ($contrasenaLower === $secuencia || strpos($contrasenaLower, $secuencia) !== false) {
                throw new Exception('La contraseña no puede ser una secuencia o combinación numérica simple');
            }
        }

        $patronesDebiles = [
            '/^(\d{2})(?:\1){2}$/',
            '/^(\d{3})\1$/',
            '/^(\d)(?:\1){5}$/'
        ];
        foreach ($patronesDebiles as $patron) {
            if (preg_match($patron, $contrasenaLower) === 1) {
                throw new Exception('La contraseña no puede ser una secuencia o combinación numérica simple');
            }
        }

        if (preg_match('/(.)\1{2,}/', $contrasenaLower)) {
            throw new Exception('La contraseña no puede tener caracteres repetidos consecutivamente');
        }

        $patronesComunes = ['password', 'contraseña', 'qwerty', 'letmein', 'welcome', 'admin', 'secret', '12345678', '87654321'];
        foreach ($patronesComunes as $patron) {
            if (strpos($contrasenaLower, $patron) !== false) {
                throw new Exception('La contraseña es demasiado común');
            }
        }
    }

    private function verificarContrasenaAlmacenada(string $contrasenaIngresada, string $hashAlmacenado): bool {
        $hashAlmacenado = trim($hashAlmacenado);
        if ($hashAlmacenado === '') {
            return false;
        }

        if (password_verify($contrasenaIngresada, $hashAlmacenado)) {
            return true;
        }

        if (hash_equals($hashAlmacenado, $contrasenaIngresada)) {
            return true;
        }

        if (strlen($hashAlmacenado) === 32 && md5($contrasenaIngresada) === $hashAlmacenado) {
            return true;
        }

        if (strlen($hashAlmacenado) === 64 && hash('sha256', $contrasenaIngresada) === $hashAlmacenado) {
            return true;
        }

        return false;
    }

    public function cambiarContrasena($usuarioId, $contrasenaActual, $contrasenaNueva) {
        try {
            if (!$usuarioId) {
                throw new Exception('ID de usuario no válido');
            }

            $contrasenaActual = trim((string)$contrasenaActual);
            $contrasenaNueva = trim((string)$contrasenaNueva);

            if ($contrasenaNueva === '') {
                throw new Exception('La nueva contraseña es requerida');
            }

            $stmt = $this->db->prepare("SELECT id, contrasena, documento FROM usuarios WHERE id = :id");
            $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                throw new Exception('Usuario no encontrado');
            }

            $this->validarContrasenaSegura($contrasenaNueva, trim((string)($usuario['documento'] ?? '')));

            $esSuperAdminGlobalSesion = $this->esSuperAdminGlobalSesion();
            $esUsuarioActual = ((int)($_SESSION['usuario_id'] ?? 0) === (int)$usuarioId);
            $verificacionActualOk = false;
            if ($contrasenaActual !== '') {
                $verificacionActualOk = $this->verificarContrasenaAlmacenada($contrasenaActual, (string)($usuario['contrasena'] ?? ''));
            } elseif ($esSuperAdminGlobalSesion || $esUsuarioActual) {
                $verificacionActualOk = true;
            }

            if (!$verificacionActualOk) {
                if ($esSuperAdminGlobalSesion || $esUsuarioActual) {
                    error_log('Cambio de contraseña sin verificación de contraseña actual para usuario ' . $usuarioId);
                } else {
                    throw new Exception('La contraseña actual es incorrecta');
                }
            }

            $hashNuevo = password_hash($contrasenaNueva, PASSWORD_BCRYPT);

            $updateStmt = $this->db->prepare("UPDATE usuarios SET contrasena = :contrasena WHERE id = :id");
            $updateStmt->bindValue(':contrasena', $hashNuevo, PDO::PARAM_STR);
            $updateStmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);

            if (!$updateStmt->execute()) {
                throw new Exception('Error al actualizar la contraseña');
            }

            if ($this->existeColumna('usuarios', 'requiere_cambio_contrasena')) {
                $flagStmt = $this->db->prepare("UPDATE usuarios SET requiere_cambio_contrasena = 0 WHERE id = :id");
                $flagStmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
                $flagStmt->execute();
            }

            return [
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente'
            ];
        } catch (Exception $e) {
            error_log('Error en cambiarContrasena: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Obtener conexión
if (!class_exists('Database', false)) {
    require_once __DIR__ . '/../Config/database.php';
}
$db = Database::connect();

// Crear instancia del controlador
$controller = new UsuarioController($db);

$scriptFilename = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
$esEjecutadoDirectamente = $scriptFilename !== '' && realpath($scriptFilename) === __FILE__;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $esEjecutadoDirectamente) {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true);
        if (!is_array($data)) {
            $data = [];
        }
    } else {
        $data = $_POST;
    }

    if (!isset($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
        exit;
    }

    $action = $data['action'];

    try {
        switch ($action) {
            case 'get':
                if (!isset($data['id'])) {
                    throw new Exception('ID de usuario no especificado');
                }
                $resultado = $controller->obtenerUsuarioPorId($data['id']);
                echo json_encode($resultado);
                break;

            case 'update':
                $antes = null;
                if (isset($data['id'])) {
                    $antes = $controller->obtenerUsuarioPorId($data['id']);
                }
                $resultado = $controller->actualizarUsuario($data);
                if (!empty($resultado['success']) && isset($data['id'])) {
                    $despues = $controller->obtenerUsuarioPorId($data['id']);
                    ActualizacionesHelper::registrarCambio(
                        'usuarios',
                        'editar',
                        $antes,
                        $despues,
                        ['entidad' => 'usuario', 'registro_id' => (string)$data['id']]
                    );
                }
                echo json_encode($resultado);
                break;

            case 'crear':
                $resultado = $controller->registrarUsuario($data);
                echo json_encode($resultado);
                break;

            case 'delete':
                if (!isset($data['id'])) {
                    throw new Exception('ID de usuario no especificado');
                }
                $resultado = $controller->eliminarUsuario($data['id']);
                echo json_encode($resultado);
                break;

            case 'cambiar_contrasena':
                $usuarioId = (int)($data['usuario_id'] ?? 0);
                $contrasenaActual = trim((string)($data['contrasena_actual'] ?? $data['contrasenaa_actual'] ?? $data['password_actual'] ?? ''));
                $contrasenaNueva = trim((string)($data['contrasena_nueva'] ?? ''));
                $resultado = $controller->cambiarContrasena($usuarioId, $contrasenaActual, $contrasenaNueva);
                echo json_encode($resultado);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
