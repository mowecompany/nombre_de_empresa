<?php
// Cargar configuración si no está ya cargada
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}

require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Usuario.php';

class LoginController {
    private $db;
    private $model;

    private function normalizarLogoUrl(string $ruta): string {
        $ruta = trim($ruta);
        if ($ruta === '') {
            return '';
        }

        $ruta = str_replace('\\', '/', $ruta);

        if (preg_match('/^(https?:)?\/\//i', $ruta) === 1 || str_starts_with($ruta, 'data:')) {
            return $ruta;
        }

        if (str_starts_with($ruta, '/')) {
            return $ruta;
        }

        if (stripos($ruta, 'Assets/') === 0) {
            return rtrim((string)base_url(), '/') . '/' . ltrim($ruta, '/');
        }

        $posAssets = stripos($ruta, '/Assets/');
        if ($posAssets !== false) {
            $relativa = substr($ruta, $posAssets + 1);
            return rtrim((string)base_url(), '/') . '/' . ltrim((string)$relativa, '/');
        }

        if (strpos($ruta, '/') === false) {
            return rtrim((string)base_url(), '/') . '/Assets/images/Empresas/' . ltrim($ruta, '/');
        }

        return rtrim((string)base_url(), '/') . '/' . ltrim($ruta, '/');
    }

    private function logoDefectoLogin(): string {
        $rutaPerfil = ROOT_PATH . '/Config/superadmin_profile.json';
        if (is_file($rutaPerfil) && is_readable($rutaPerfil)) {
            $contenido = @file_get_contents($rutaPerfil);
            $json = is_string($contenido) ? json_decode($contenido, true) : null;
            $logo = trim((string)($json['logo'] ?? ''));
            if ($logo !== '') {
                return $this->normalizarLogoUrl($logo);
            }
        }

        return rtrim((string)base_url(), '/') . '/Assets/images/Empresas/empresa.png';
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
            return dbTableExists($this->db, $tabla);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function existeColumna(string $tabla, string $columna): bool {
        return dbColumnExists($this->db, $tabla, $columna);
    }

    private function esSuperAdministradorRol(string $rol): bool {
        $rolNormalizado = normalizarNombreRol($rol);
        return $rolNormalizado === 'superadministrador' || $rolNormalizado === 'super administrador';
    }

    private function esAdministradorRol(string $rol): bool {
        $rolNormalizado = normalizarNombreRol($rol);
        return $rolNormalizado === 'administrador';
    }

    private function requiereConfiguracionInicialEmpresa(array $usuario): bool {
        try {
            if (!$this->esAdministradorRol((string)($usuario['rol'] ?? ''))) {
                return false;
            }

            if (!$this->existeColumna('usuarios', 'requiere_configuracion_empresa')) {
                return false;
            }

            return (int)($usuario['requiere_configuracion_empresa'] ?? 0) === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function asegurarRolesBaseEmpresa(int $empresaId): void {
        // Desactivado intencionalmente: no se permite crear roles automaticamente.
        // Los roles deben ser creados manualmente por usuarios autorizados.
        return;
    }

    private function cargarColoresEmpresaSesion(int $empresaId): void {
        $defaults = [
            // Identidad visual base
            'color_principal'        => '#2F4A5A',
            'color_secundario'       => '#2F4A5A',
            'color_menu_lateral'     => '#2F4A5A',
            'color_botones'          => '#2F4A5A',
            'color_fondo'            => '#F4F6F8',
            'color_texto'            => '#2F4A5A',
            'color_bordes'           => '#D8DFE5',
            // Navegación
            'color_navbar'           => '#2F4A5A',
            'color_iconos_menu'      => '#FFFFFF',
            'color_hover_menu'       => '#3D6175',
            // Contenido
            'color_titulos'          => '#2F4A5A',
            'color_links'            => '#2F4A5A',
            // Tablas
            'color_fondo_tabla'      => '#FFFFFF',
            'color_encabezado_tabla' => '#2F4A5A',
            'color_filas_alternas'   => '#F4F6F8',
            // Botones específicos
            'color_btn_crear'        => '#2F4A5A',
            'color_btn_editar'       => '#2F4A5A',
            'color_btn_eliminar'     => '#DC3545',
            // Formularios
            'color_focus_inputs'     => '#2F4A5A',
        ];

        $normalizar = static function ($valor, $defecto) {
            $valor = strtoupper(trim((string)$valor));
            return preg_match('/^#[0-9A-F]{6}$/', $valor) === 1 ? $valor : strtoupper((string)$defecto);
        };

        $_SESSION['colores_empresa'] = $defaults;

        if ($empresaId <= 0) {
            return;
        }

        try {
            if (!dbTableExists($this->db, 'colores_empresa')) {
                return;
            }

            $columnasExistentes = array_keys(dbColumnInfo($this->db, 'colores_empresa'));
            $columnasSolicitadas = array_intersect(array_keys($defaults), $columnasExistentes);
            if (empty($columnasSolicitadas)) {
                return;
            }

            $selectCols = implode(', ', array_map(static fn($c) => "`$c`", $columnasSolicitadas));
            $stmt = $this->db->prepare("SELECT {$selectCols} FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1");
            $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                return;
            }

            foreach ($defaults as $clave => $defecto) {
                $_SESSION['colores_empresa'][$clave] = $normalizar($fila[$clave] ?? $defecto, $defecto);
            }
        } catch (Throwable $e) {
            error_log('Error cargando colores de empresa en sesion: ' . $e->getMessage());
        }
    }

    private function cargarLogoEmpresaSesion(int $empresaId): void {
        $_SESSION['empresa_logo'] = rtrim((string)base_url(), '/') . '/Assets/images/Empresas/empresa_2_20260729_195515_4ce5c2e8.png';

        if ($empresaId <= 0 || !$this->existeColumna('empresas', 'imagen')) {
            return;
        }

        try {
            $stmt = $this->db->prepare('SELECT imagen FROM empresas WHERE id = :empresa_id LIMIT 1');
            $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();
            $ruta = trim((string)$stmt->fetchColumn());

            if ($ruta === '') {
                return;
            }

            $_SESSION['empresa_logo'] = $this->normalizarLogoUrl($ruta);
        } catch (Throwable $e) {
            // Si falla la carga de logo, se mantiene el fallback por defecto en los headers.
        }
    }

    public function __construct() {
        date_default_timezone_set('America/Bogota');
        $database = new Database();
        $this->db = $database->connect();
        $this->model = new Usuario($this->db);
    }

    public function iniciarSesion($correo, $contrasena, $empresaIdObjetivo = null) {
        // Normalizar el correo ANTES de cualquier operación
        $correo = strtolower(trim($correo));
        $empresaIdObjetivo = is_numeric($empresaIdObjetivo) ? (int)$empresaIdObjetivo : 0;
        
        // Log para debugging
        $this->debugLogin("=== INICIO DE SESION ===");
        $this->debugLogin("Correo recibido (normalizado): '" . $correo . "'");
        $this->debugLogin("Longitud de contraseña: " . strlen($contrasena));
        
        // Primero obtener el usuario por correo (si existe)
        $usuarioPorCorreo = $this->model->obtenerUsuarioPorCorreo($correo);
        
        if ($usuarioPorCorreo) {
            $this->debugLogin("✓ Usuario encontrado por correo - ID: " . $usuarioPorCorreo['id'] . ", Rol: " . $usuarioPorCorreo['rol']);
        } else {
            $this->debugLogin("✗ Usuario NO encontrado por correo: '" . $correo . "'");
        }
        
        // Si el usuario existe, verificar si está bloqueado
        if ($usuarioPorCorreo) {
            $bloqueo = $this->model->verificarSiBloqueadoPorId($usuarioPorCorreo['id']);
            if ($bloqueo['bloqueado']) {
                $fecha_bloqueo = new \DateTime($bloqueo['bloqueado_hasta']);
                $ahora = new \DateTime();
                $diferencia_segundos = $fecha_bloqueo->getTimestamp() - $ahora->getTimestamp();
                $minutos_restantes = ceil($diferencia_segundos / 60);
                $segundos_restantes = $diferencia_segundos % 60;
                
                // Obtener intentos para determinar razón del bloqueo y duración
                $intentos = $this->model->obtenerIntentosfallidosPorId($usuarioPorCorreo['id']);
                $razon = ($intentos >= 5) ? '5 intentos fallidos (esta se bloqueó por 24 horas)' : '3 intentos fallidos (esta se bloqueó por 10 minutos)';
                $duracion_bloqueo = ($intentos >= 5) ? 1440 : 10; // en minutos
                
                return [
                    'success' => false, 
                    'bloqueado' => true,
                    'razon' => $razon,
                    'intentos' => $intentos,
                    'minutos_restantes' => $minutos_restantes,
                    'segundos_restantes' => $segundos_restantes,
                    'duracion_bloqueo' => $duracion_bloqueo,
                    'fecha_bloqueo' => $fecha_bloqueo->format('Y-m-d H:i:s'),
                    'message' => "Cuenta bloqueada por: $razon. Tiempo restante: $minutos_restantes min $segundos_restantes seg"
                ];
            }
        }

        $usuario = $this->model->verificarCredenciales($correo, $contrasena);
        
        $this->debugLogin("Resultado de verificarCredenciales: " . ($usuario ? "EXITO" : "FALLO"));
        
        if ($usuario) {
            $this->debugLogin("Login exitoso para usuario ID: " . $usuario['id']);
            // Credenciales correctas - resetear intentos fallidos y actualizar última actividad
            $this->model->resetearIntentosfallidosPorId($usuario['id']);
            $this->model->actualizarUltimaActividad($usuario['id']);
            
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['rol_id'] = isset($usuario['rol_id']) ? (int)$usuario['rol_id'] : 0;
            $_SESSION['correo'] = $usuario['correo'] ?? $correo;
            $_SESSION['documento'] = $usuario['documento'] ?? '';
            $_SESSION['nombre'] = trim(((string)($usuario['nombre'] ?? '')) . ' ' . ((string)($usuario['apellidos'] ?? '')));
            $_SESSION['alerta_stock_login_token'] = bin2hex(random_bytes(16));

            // Limpiar cualquier residuo de otro login y fijar datos del usuario actual.
            unset($_SESSION['userData']);
            $_SESSION['userData'] = [
                'id' => (int)($usuario['id'] ?? 0),
                'nombres' => (string)($usuario['nombre'] ?? ''),
                'apellidos' => (string)($usuario['apellidos'] ?? ''),
                'email' => (string)($usuario['correo'] ?? $correo),
                'correo' => (string)($usuario['correo'] ?? $correo),
                'telefono' => (string)($usuario['telefono'] ?? ''),
                'nombrerol' => (string)($usuario['rol'] ?? ''),
                'rol_id' => isset($usuario['rol_id']) ? (int)$usuario['rol_id'] : 0,
                'documento' => (string)($usuario['documento'] ?? ''),
            ];

            $adminIdSesion = isset($usuario['admin_id']) ? (int)$usuario['admin_id'] : 0;
            if ($this->esAdministradorRol((string)($usuario['rol'] ?? ''))) {
                $adminIdSesion = (int)($usuario['id'] ?? 0);
            }
            if ($adminIdSesion > 0) {
                $_SESSION['admin_id'] = $adminIdSesion;
                $_SESSION['userData']['admin_id'] = $adminIdSesion;
            } else {
                unset($_SESSION['admin_id']);
            }

            $esSuperAdmin = $this->esSuperAdministradorRol((string)($usuario['rol'] ?? ''));

            $contextoEmpresa = null;

            if ($esSuperAdmin && $empresaIdObjetivo > 0) {
                $contextoEmpresa = $this->model->obtenerContextoEmpresaPorEmpresaId($empresaIdObjetivo);
                if (!$contextoEmpresa) {
                    unset($_SESSION['usuario_id'], $_SESSION['rol'], $_SESSION['correo'], $_SESSION['documento']);
                    return [
                        'success' => false,
                        'bloqueado' => false,
                        'message' => 'La empresa indicada no existe. Verifica el ID e intenta nuevamente.'
                    ];
                }
            } elseif ($esSuperAdmin) {
                // Super administrador en perfil global: NO heredar empresa de usuario ni de fallback.
                $contextoEmpresa = [
                    'empresa_id' => 0,
                    'tipo_empresa_id' => 0,
                    'empresa_nombre' => 'PERFIL GLOBAL'
                ];
            }

            if (!$contextoEmpresa) {
                $contextoEmpresa = $this->model->obtenerContextoEmpresaPorUsuarioId($usuario['id']);
            }

            if (!$contextoEmpresa) {
                $adminIdRelacionado = (int)($usuario['admin_id'] ?? 0);
                if ($adminIdRelacionado > 0) {
                    $contextoEmpresa = $this->model->obtenerContextoEmpresaPorUsuarioId($adminIdRelacionado);
                }
            }

            if (!$contextoEmpresa) {
                $empresaPublicaSesion = (int)($_SESSION['store_public_empresa_id'] ?? 0);
                if ($empresaPublicaSesion > 0) {
                    $contextoEmpresa = $this->model->obtenerContextoEmpresaPorEmpresaId($empresaPublicaSesion);
                }
            }

            if (!$contextoEmpresa) {
                if ($esSuperAdmin) {
                    // Excepcion: super admin puede entrar sin estar vinculado a una empresa.
                    $contextoEmpresa = $this->model->obtenerPrimerContextoEmpresa();
                    if (!$contextoEmpresa) {
                        $contextoEmpresa = [
                            'empresa_id' => 1,
                            'tipo_empresa_id' => 1,
                            'empresa_nombre' => 'Sistema Global'
                        ];
                    }
                } elseif ($this->esAdministradorRol((string)($usuario['rol'] ?? ''))) {
                    // Recuperacion para administradores que quedaron sin empresa_id por ajustes previos.
                    $contextoEmpresa = $this->model->recuperarContextoEmpresaParaAdministrador((int)$usuario['id']);

                    if (!$contextoEmpresa) {
                        unset($_SESSION['usuario_id'], $_SESSION['rol'], $_SESSION['correo'], $_SESSION['documento']);
                        return [
                            'success' => false,
                            'bloqueado' => false,
                            'message' => 'No se pudo recuperar la empresa asociada del administrador. Contacta al super administrador.'
                        ];
                    }
                } elseif (trim(mb_strtolower((string)($usuario['rol'] ?? ''), 'UTF-8')) === 'cliente') {
                    $contextoEmpresa = [
                        'empresa_id' => 0,
                        'tipo_empresa_id' => 0,
                        'empresa_nombre' => 'PERFIL GLOBAL'
                    ];
                } else {
                    unset($_SESSION['usuario_id'], $_SESSION['rol'], $_SESSION['correo'], $_SESSION['documento']);
                    return [
                        'success' => false,
                        'bloqueado' => false,
                        'message' => 'Tu usuario no tiene una empresa asociada. Contacta al administrador.'
                    ];
                }
            }

            // Aplicar contexto de empresa a la sesión
            $_SESSION['empresa_id'] = (int)($contextoEmpresa['empresa_id'] ?? 0);
            $_SESSION['tipo_empresa_id'] = (int)($contextoEmpresa['tipo_empresa_id'] ?? 0);
            $_SESSION['empresa_nombre'] = (string)($contextoEmpresa['empresa_nombre'] ?? '');
            $_SESSION['userData']['empresa_id'] = (int)($_SESSION['empresa_id'] ?? 0);
            $_SESSION['userData']['tipo_empresa_id'] = (int)($_SESSION['tipo_empresa_id'] ?? 0);
            $_SESSION['userData']['empresa_nombre'] = (string)($_SESSION['empresa_nombre'] ?? '');
            $this->cargarColoresEmpresaSesion((int)($_SESSION['empresa_id'] ?? 0));
            $this->cargarLogoEmpresaSesion((int)($_SESSION['empresa_id'] ?? 0));

            if (!$esSuperAdmin) {
                // Roles base se gestionan manualmente, no se crean automaticamente al login.
                unset($_SESSION['puede_gestionar_roles'], $_SESSION['acceso_total']);
            }
            
            // Establecer permisos para Super Administrador
            if ($esSuperAdmin) {
                $_SESSION['puede_gestionar_roles'] = true;
                $_SESSION['acceso_total'] = true;
                // Indicar si el superadmin inicio sesion en el contexto de una empresa especifica
                $_SESSION['superadmin_modo_empresa'] = ($empresaIdObjetivo > 0);
            } else {
                $_SESSION['superadmin_modo_empresa'] = false;
            }
            
            // Verificar si requiere cambio de contraseña (PRIMER LOGIN)
            $requiere_cambio = $usuario['requiere_cambio_contrasena'] ?? 0;
            if ($requiere_cambio == 1) {
                return [
                    'success' => true, 
                    'requiere_cambio_contrasena' => true,
                    'message' => 'Inicio de sesión exitoso - Se requiere cambio de contraseña'
                ];
            }

            if ($this->requiereConfiguracionInicialEmpresa($usuario)) {
                return [
                    'success' => true,
                    'requiere_configuracion_empresa' => true,
                    'message' => 'Inicio de sesión exitoso - Se requiere configuración inicial de empresa'
                ];
            }
            
            // Verificar si necesita completar datos (SEGUNDO LOGIN - solo si apellidos está vacío)
            $apellidos = trim($usuario['apellidos'] ?? '');
            
            if (empty($apellidos)) {
                return [
                    'success' => true,
                    'requiere_completar_datos' => true,
                    'message' => 'Inicio de sesión exitoso - Se requiere completar datos personales'
                ];
            }
            
            return ['success' => true, 'message' => 'Inicio de sesión exitoso'];
        }
        
        // Credenciales incorrectas
        $this->debugLogin("Credenciales incorrectas - iniciando lógica de intentos fallidos");
        // Si el usuario existe, incrementar intentos fallidos
        if ($usuarioPorCorreo) {
            $this->debugLogin("Incrementando intentos fallidos para usuario ID: " . $usuarioPorCorreo['id']);
            
            // Obtener intentos ANTES de incrementar para validar
            $intentos_previos = $this->model->obtenerIntentosfallidosPorId($usuarioPorCorreo['id']);
            
            // Si ya está en 5 o más intentos, verificar si el bloqueo de 24h sigue activo
            if ($intentos_previos >= 5) {
                $this->debugLogin("Usuario tiene 5+ intentos fallidos, verificando estado de bloqueo de 24 horas");
                $bloqueo_actual = $this->model->verificarSiBloqueadoPorId($usuarioPorCorreo['id']);

                if ($bloqueo_actual['bloqueado']) {
                    $this->debugLogin("Usuario sigue bloqueado con 24 horas activas");
                    return [
                        'success' => false,
                        'bloqueado' => true,
                        'razon' => '5 intentos fallidos (esta se bloqueó por 24 horas)',
                        'intentos' => 5,
                        'minutos_restantes' => 1440,
                        'duracion_bloqueo' => 1440,
                        'fecha_bloqueo' => $bloqueo_actual['bloqueado_hasta'],
                        'message' => 'Has agotado todos tus intentos. Tu cuenta ha sido bloqueada por 24 horas.'
                    ];
                }

                // El bloqueo de 24 horas expiró: limpiar solo el bloqueo y permitir que
                // la secuencia de intentos vuelva a evaluarse sin borrar el conteo previo.
                $this->debugLogin("Bloqueo de 24 horas expirado para usuario ID: " . $usuarioPorCorreo['id'] . ". Se conserva el conteo y se limpia solo el bloqueo activo.");
                $this->model->limpiarBloqueoPorId($usuarioPorCorreo['id']);
                $intentos_previos = $this->model->obtenerIntentosfallidosPorId($usuarioPorCorreo['id']);
            }
            
            // Incrementar intentos solo si es menor a 5
            $this->model->incrementarIntentosfallidosPorId($usuarioPorCorreo['id']);
            $intentos_actuales = $this->model->obtenerIntentosfallidosPorId($usuarioPorCorreo['id']);
            $this->debugLogin("Intentos actuales después de incrementar: " . $intentos_actuales);
            
            // Lógica de bloqueo PROGRESIVO
            if ($intentos_actuales == 3) {
                // PRIMER bloqueo: después del 3er intento fallido (10 minutos)
                $this->model->bloquearUsuarioPorId($usuarioPorCorreo['id'], 10);
                
                // Obtener la fecha REAL desde la base de datos después de guardarla
                $bloqueo_real = $this->model->verificarSiBloqueadoPorId($usuarioPorCorreo['id']);
                $fecha_bloqueo_bd = $bloqueo_real['bloqueado_hasta'];
                
                $ahora = new \DateTime();
                $fecha_bloqueo_obj = new \DateTime($fecha_bloqueo_bd);
                $diferencia = $fecha_bloqueo_obj->getTimestamp() - $ahora->getTimestamp();
                $minutos_diff = round($diferencia / 60);
                
                $this->debugLogin("=== BLOQUEO DE 10 MINUTOS ===");
                $this->debugLogin("Zona horaria PHP: " . date_default_timezone_get());
                $this->debugLogin("Fecha actual (servidor): " . $ahora->format('Y-m-d H:i:s'));
                $this->debugLogin("Fecha de desbloqueo (BD): " . $fecha_bloqueo_bd);
                $this->debugLogin("Diferencia calculada: " . $minutos_diff . " minutos");
                
                $intentos_restantes = 2; // Permite 2 intentos más (4 y 5)
                return [
                    'success' => false, 
                    'bloqueado' => true,
                    'razon' => '3 intentos fallidos (esta se bloqueó por 10 minutos)',
                    'intentos' => $intentos_actuales,
                    'intentos_restantes' => $intentos_restantes,
                    'minutos_restantes' => 10,
                    'duracion_bloqueo' => 10,
                    'fecha_bloqueo' => $fecha_bloqueo_bd,
                    'message' => "Demasiados intentos fallidos. Tu cuenta ha sido bloqueada por 10 minutos. Después podrás hacer $intentos_restantes intentos más."
                ];
            } elseif ($intentos_actuales == 5) {
                // SEGUNDO bloqueo: después del 5to intento fallido (24 horas)
                $this->model->bloquearUsuarioPorId($usuarioPorCorreo['id'], 1440); // 1440 minutos = 24 horas
                
                // Obtener la fecha REAL desde la base de datos después de guardarla
                $bloqueo_real = $this->model->verificarSiBloqueadoPorId($usuarioPorCorreo['id']);
                $fecha_bloqueo_bd = $bloqueo_real['bloqueado_hasta'];
                
                $this->debugLogin("=== BLOQUEO DE 24 HORAS ===");
                $this->debugLogin("Fecha actual (servidor): " . (new \DateTime())->format('Y-m-d H:i:s'));
                $this->debugLogin("Fecha de desbloqueo (BD): " . $fecha_bloqueo_bd);
                
                return [
                    'success' => false, 
                    'bloqueado' => true,
                    'razon' => '5 intentos fallidos (esta se bloqueó por 24 horas)',
                    'intentos' => $intentos_actuales,
                    'minutos_restantes' => 1440,
                    'duracion_bloqueo' => 1440,
                    'fecha_bloqueo' => $fecha_bloqueo_bd,
                    'message' => 'Has agotado todos tus intentos. Tu cuenta ha sido bloqueada por 24 horas.'
                ];
            }
            
            // Intentos 4 (sin bloqueo todavía, solo error)
            if ($intentos_actuales == 4) {
                $intentos_restantes = 1;
                return [
                    'success' => false, 
                    'bloqueado' => false,
                    'intentos' => $intentos_actuales,
                    'intentos_restantes' => $intentos_restantes,
                    'message' => 'El correo o la contraseña no son correctos.'
                ];
            }
            
            // Intentos 1 y 2 (sin bloqueo)
            $intentos_restantes = 3 - $intentos_actuales;
            return [
                'success' => false, 
                'bloqueado' => false,
                'intentos' => $intentos_actuales,
                'intentos_restantes' => $intentos_restantes,
                'message' => 'El correo o la contraseña no son correctos.'
            ];
        }
        
        // Si no existe el usuario, devolver mensaje genérico
        return [
            'success' => false, 
            'bloqueado' => false,
            'message' => 'El correo o la contraseña no son correctos.'
        ];
    }

    public function resetearBloqueo() {
        header('Content-Type: application/json');
        
        // Solo permitir POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            return;
        }

        // Obtener el correo del usuario
        $correo = $_POST['correo'] ?? null;

        if (!$correo) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Correo no proporcionado']);
            return;
        }

        try {
            // Obtener el usuario por correo
            $usuario = $this->model->obtenerUsuarioPorCorreo($correo);
            
            if (!$usuario) {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                return;
            }
            
            // Verificar si el usuario ya no está bloqueado
            $bloqueo = $this->model->verificarSiBloqueadoPorId($usuario['id']);
            
            if (!$bloqueo['bloqueado']) {
                // El bloqueo de 10 minutos expiró: limpiar solo la fecha de desbloqueo
                // y conservar el conteo de intentos fallidos para que la secuencia sea:
                // 3 intentos fallidos -> 10 minutos; 4.º fallo -> 1 intento restante;
                // 5.º fallo -> bloqueo de 24 horas.
                $this->model->limpiarBloqueoPorId($usuario['id']);
                error_log("Bloqueo expirado para usuario ID: " . $usuario['id'] . ". Se elimina solo la fecha de bloqueo activo y se conserva el conteo de intentos.");

                echo json_encode([
                    'success' => true,
                    'message' => 'Bloqueo expirado correctamente'
                ]);
            } else {
                // Todavía está bloqueado
                echo json_encode([
                    'success' => false, 
                    'message' => 'El usuario todavía está bloqueado'
                ]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error en resetearBloqueo: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Error al procesar la solicitud'
            ]);
        }
    }

    public function obtenerInfoEmpresaPorId() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['found' => false]);
            return;
        }

        $correo = isset($_GET['correo']) ? strtolower(trim((string)$_GET['correo'])) : '';
        $empresaId = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : 0;

        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false || $empresaId <= 0) {
            echo json_encode(['found' => false]);
            return;
        }

        try {
            $usuario = $this->model->obtenerUsuarioPorCorreo($correo);
            if (!$usuario || !$this->esSuperAdministradorRol((string)($usuario['rol'] ?? ''))) {
                echo json_encode(['found' => false]);
                return;
            }

            $contexto = $this->model->obtenerContextoEmpresaPorEmpresaId($empresaId);
            if (!$contexto) {
                echo json_encode(['found' => false]);
                return;
            }

            $logo = $this->logoDefectoLogin();
            if ($this->existeColumna('empresas', 'imagen')) {
                $stmt = $this->db->prepare('SELECT imagen FROM empresas WHERE id = :id LIMIT 1');
                $stmt->bindValue(':id', $empresaId, PDO::PARAM_INT);
                $stmt->execute();
                $imgRaw = trim((string)($stmt->fetchColumn() ?: ''));
                if ($imgRaw !== '') {
                    $logo = $this->normalizarLogoUrl($imgRaw);
                }
            }

            echo json_encode([
                'found' => true,
                'empresa_id' => (int)$contexto['empresa_id'],
                'empresa_nombre' => (string)$contexto['empresa_nombre'],
                'logo_url' => $logo,
            ]);
        } catch (Throwable $e) {
            echo json_encode(['found' => false]);
        }
    }

    public function verificarEsSuperAdmin() {
        header('Content-Type: application/json');

        // Solo permitir GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['is_superadmin' => false]);
            return;
        }

        $correo = isset($_GET['correo']) ? strtolower(trim((string)$_GET['correo'])) : '';
        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            echo json_encode(['is_superadmin' => false]);
            return;
        }

        try {
            $usuario = $this->model->obtenerUsuarioPorCorreo($correo);
            if (!$usuario || (int)($usuario['id'] ?? 0) <= 0) {
                echo json_encode(['is_superadmin' => false]);
                return;
            }

            $esSuperAdmin = $this->esSuperAdministradorRol((string)($usuario['rol'] ?? ''));
            echo json_encode(['is_superadmin' => $esSuperAdmin]);
        } catch (Throwable $e) {
            echo json_encode(['is_superadmin' => false]);
        }
    }

    public function obtenerLogoEmpresaPorCorreo() {
        header('Content-Type: application/json');

        $correo = isset($_REQUEST['correo']) ? strtolower(trim((string)$_REQUEST['correo'])) : '';
        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            echo json_encode([
                'success' => true,
                'logo_url' => $this->logoDefectoLogin(),
                'empresa' => ''
            ]);
            return;
        }

        try {
            $usuario = $this->model->obtenerUsuarioPorCorreo($correo);
            if (!$usuario || (int)($usuario['id'] ?? 0) <= 0) {
                echo json_encode([
                    'success' => true,
                    'logo_url' => $this->logoDefectoLogin(),
                    'empresa' => ''
                ]);
                return;
            }

            $contextoEmpresa = $this->model->obtenerContextoEmpresaPorUsuarioId((int)$usuario['id']);
            if (!$contextoEmpresa && $this->esAdministradorRol((string)($usuario['rol'] ?? ''))) {
                $contextoEmpresa = $this->model->recuperarContextoEmpresaParaAdministrador((int)$usuario['id']);
            }
            $empresaId = (int)($contextoEmpresa['empresa_id'] ?? 0);

            if ($empresaId <= 0 || !$this->existeColumna('empresas', 'imagen')) {
                echo json_encode([
                    'success' => true,
                    'logo_url' => $this->logoDefectoLogin(),
                    'empresa' => (string)($contextoEmpresa['empresa_nombre'] ?? '')
                ]);
                return;
            }

            $stmt = $this->db->prepare('SELECT nombre, imagen FROM empresas WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $logo = $this->normalizarLogoUrl((string)($empresa['imagen'] ?? ''));
            if ($logo === '') {
                $logo = $this->logoDefectoLogin();
            }

            echo json_encode([
                'success' => true,
                'logo_url' => $logo,
                'empresa' => (string)($empresa['nombre'] ?? ($contextoEmpresa['empresa_nombre'] ?? ''))
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'logo_url' => $this->logoDefectoLogin(),
                'empresa' => '',
                'message' => 'No se pudo resolver el logo de la empresa.'
            ]);
        }
    }
}

// Manejo de las acciones
if (isset($_GET['action'])) {
    session_start();
    $controller = new LoginController();
    $action = $_GET['action'];
    
    switch($action) {
        case 'logo_por_correo':
            $controller->obtenerLogoEmpresaPorCorreo();
            break;
        case 'check_superadmin':
            $controller->verificarEsSuperAdmin();
            break;
        case 'get_empresa_info':
            $controller->obtenerInfoEmpresaPorId();
            break;
        case 'resetear_bloqueo':
            $controller->resetearBloqueo();
            break;
        // ... otros casos ...
    }
}
?> 