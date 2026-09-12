<?php
// cSpell:disable
session_start();

date_default_timezone_set('America/Bogota');

// Cargar configuracion global
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}

require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Permiso.php';
require_once ROOT_PATH . '/Models/Usuario.php';
require_once ROOT_PATH . '/Helpers/Helpers.php';

// Verificar si el usuario esta logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar si el rol esta activo

$userData = $_SESSION['userData'] ?? [];
$usuarioId = $_SESSION['usuario_id'] ?? null;
$alertaStockLoginToken = (string)($_SESSION['alerta_stock_login_token'] ?? '');
$nombreCompleto = 'Usuario';
$correo = '';
$telefono = '';
$rolNombre = $_SESSION['rol'] ?? '';
$rolSesionInicial = normalizarNombreRol($rolNombre);
$esClienteSesionDashboard = ($rolSesionInicial === 'cliente');
$appMenuMode = defined('APP_MENU_MODE') ? strtolower((string)APP_MENU_MODE) : 'full';
$modoMenuPortable = $appMenuMode === 'portable';
$esSuperAdminRolSesion = in_array($rolSesionInicial, ['superadministrador', 'super administrador'], true);
$esSuperAdminGlobalSesion = $esSuperAdminRolSesion
    && PermisosHelper::esSuperAdminSesion()
    && empty($_SESSION['superadmin_modo_empresa']);
$esSuperAdminModoEmpresa = $esSuperAdminRolSesion && !empty($_SESSION['superadmin_modo_empresa']);
$esSuperAdminSinRestriccionColores = $esSuperAdminGlobalSesion || $esSuperAdminModoEmpresa;
$puedeGestionEmpresaPerfil = $esSuperAdminGlobalSesion || $esSuperAdminModoEmpresa || (preg_match('/\badministrador\b/u', $rolSesionInicial) === 1);

$textoMayusDashboard = static function (string $texto): string {
    $valor = (string)$texto;
    return function_exists('mb_strtoupper') ? mb_strtoupper($valor, 'UTF-8') : strtoupper($valor);
};

$empresaId = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
$tipoEmpresaId = isset($_SESSION['tipo_empresa_id']) ? (int)$_SESSION['tipo_empresa_id'] : 0;
$cargaCompletaDashboard = !empty($_GET['load_full']) || !empty($_POST['action']);
$debeCargarDatosFinancierosInicial = $cargaCompletaDashboard && $empresaId > 0 && !$esSuperAdminGlobalSesion && !$esSuperAdminModoEmpresa && !$esClienteSesionDashboard;
$debeConciliarIntentosDashboard = $cargaCompletaDashboard && $empresaId > 0 && !$esSuperAdminGlobalSesion && !$esSuperAdminModoEmpresa && !$esClienteSesionDashboard;
$puedeGestionEmpresaFinanzas = false;
$empresaNombre = isset($_SESSION['empresa_nombre']) && trim((string)$_SESSION['empresa_nombre']) !== ''
    ? trim((string)$_SESSION['empresa_nombre'])
    : 'Empresa no definida';
$tipoEmpresaNombre = 'Tipo no definido';
$documento = '';
$nombrePerfilVisual = '';
$rolPerfilVisual = '';
$empresaImagenUrl = '';
$empresaRedesSocialesDef = [
    'facebook_url' => 'Facebook',
    'instagram_url' => 'Instagram',
    'whatsapp_url' => 'WhatsApp',
    'linkedin_url' => 'LinkedIn',
    'youtube_url' => 'YouTube',
    'tiktok_url' => 'TikTok',
];
$empresaRedesSociales = [];
foreach (array_keys($empresaRedesSocialesDef) as $empresaRedSocialColumna) {
    $empresaRedesSociales[$empresaRedSocialColumna] = trim((string)($_SESSION['empresa_redes_sociales'][$empresaRedSocialColumna] ?? ''));
}
$empresaDireccion = 'Colombia';
$empresaTelefonoContacto = '';
$empresaCorreoContacto = '';
$empresaSobreNosotros = '';
$empresaStoreSlug = '';
$empresaCuentaBancaria = [
    'banco_nombre' => '',
    'tipo_cuenta' => 'ahorros',
    'titular_cuenta' => '',
    'numero_cuenta' => '',
    'documento_titular' => '',
    'correo_pagos' => '',
    'telefono_pagos' => '',
];
$empresaCredencialesWompi = [
    'public_key' => '',
    'private_key' => '',
    'integrity_secret' => '',
    'events_secret' => '',
    'has_private_key' => false,
    'has_integrity_secret' => false,
    'has_events_secret' => false,
];
$rutaPerfilSuperAdminDashboard = ROOT_PATH . '/Config/superadmin_profile.json';
$cargarPerfilSuperAdminDashboard = static function (string $rutaPerfil): array {
    $perfil = [
        'nombre' => 'PERFIL GLOBAL',
        'logo' => '',
        'direccion' => 'Colombia',
        'telefono' => '',
        'correo' => '',
        'sobre_nosotros' => '',
        'redes_sociales' => [],
        'colores' => [],
    ];

    if (!is_file($rutaPerfil) || !is_readable($rutaPerfil)) {
        return $perfil;
    }

    $contenido = @file_get_contents($rutaPerfil);
    if (!is_string($contenido) || trim($contenido) === '') {
        return $perfil;
    }

    $json = json_decode($contenido, true);
    if (!is_array($json)) {
        return $perfil;
    }

    $nombre = trim((string)($json['nombre'] ?? ''));
    $logo = trim((string)($json['logo'] ?? ''));
    $direccion = trim((string)($json['direccion'] ?? ''));
    $telefonoPerfil = trim((string)($json['telefono'] ?? ''));
    $correoPerfil = trim((string)($json['correo'] ?? ''));
    $sobreNosotrosPerfil = trim((string)($json['sobre_nosotros'] ?? ''));
    $redesPerfil = isset($json['redes_sociales']) && is_array($json['redes_sociales']) ? $json['redes_sociales'] : [];
    $coloresPerfil = isset($json['colores']) && is_array($json['colores']) ? $json['colores'] : [];

    if ($nombre !== '') {
        $perfil['nombre'] = $nombre;
    }
    if ($logo !== '') {
        $perfil['logo'] = $logo;
    }
    if ($direccion !== '') {
        $perfil['direccion'] = $direccion;
    }
    if ($telefonoPerfil !== '') {
        $perfil['telefono'] = $telefonoPerfil;
    }
    if ($correoPerfil !== '') {
        $perfil['correo'] = $correoPerfil;
    }
    if ($sobreNosotrosPerfil !== '') {
        $perfil['sobre_nosotros'] = $sobreNosotrosPerfil;
    }
    if (!empty($redesPerfil)) {
        $perfil['redes_sociales'] = $redesPerfil;
    }
    if (!empty($coloresPerfil)) {
        $perfil['colores'] = $coloresPerfil;
    }

    return $perfil;
};

if (!$esSuperAdminGlobalSesion && ($empresaId <= 0 || strcasecmp($empresaNombre, 'PERFIL GLOBAL') === 0)) {
    try {
        $dbDashboardSesion = Database::connect();
        $usuarioModelDashboardSesion = new Usuario($dbDashboardSesion);
        $contextoEmpresaDashboard = null;
        $empresaSesionRecuperada = (int)($_SESSION['userData']['empresa_id'] ?? 0);

        if ($empresaSesionRecuperada > 0) {
            $contextoEmpresaDashboard = $usuarioModelDashboardSesion->obtenerContextoEmpresaPorEmpresaId($empresaSesionRecuperada);
        }

        if (!$contextoEmpresaDashboard && $usuarioId) {
            $contextoEmpresaDashboard = $usuarioModelDashboardSesion->obtenerContextoEmpresaPorUsuarioId((int)$usuarioId);
        }

        if (!$contextoEmpresaDashboard) {
            $adminIdDashboard = (int)($_SESSION['admin_id'] ?? ($_SESSION['userData']['admin_id'] ?? 0));
            if ($adminIdDashboard > 0) {
                $contextoEmpresaDashboard = $usuarioModelDashboardSesion->obtenerContextoEmpresaPorUsuarioId($adminIdDashboard);
            }

        
        }

        if (!$contextoEmpresaDashboard && $rolSesionInicial === 'administrador' && $usuarioId) {
            $contextoEmpresaDashboard = $usuarioModelDashboardSesion->recuperarContextoEmpresaParaAdministrador((int)$usuarioId);
        }

        if ($contextoEmpresaDashboard && (int)($contextoEmpresaDashboard['empresa_id'] ?? 0) > 0) {
            $_SESSION['empresa_id'] = (int)$contextoEmpresaDashboard['empresa_id'];
            $_SESSION['tipo_empresa_id'] = (int)($contextoEmpresaDashboard['tipo_empresa_id'] ?? 0);
            $_SESSION['empresa_nombre'] = (string)($contextoEmpresaDashboard['empresa_nombre'] ?? 'Empresa');
            $_SESSION['userData']['empresa_id'] = (int)$contextoEmpresaDashboard['empresa_id'];
            $_SESSION['userData']['tipo_empresa_id'] = (int)($contextoEmpresaDashboard['tipo_empresa_id'] ?? 0);
            $_SESSION['userData']['empresa_nombre'] = (string)($contextoEmpresaDashboard['empresa_nombre'] ?? 'Empresa');

            $empresaId = (int)$contextoEmpresaDashboard['empresa_id'];
            $tipoEmpresaId = (int)($contextoEmpresaDashboard['tipo_empresa_id'] ?? 0);
            $empresaNombre = (string)($contextoEmpresaDashboard['empresa_nombre'] ?? 'Empresa');
        }
    } catch (Throwable $e) {
        error_log('DASHBOARD_CONTEXTO_EMPRESA_ERROR: ' . $e->getMessage());
    }
}

$empresaIdFinanzasContexto = $empresaId;
$tipoEmpresaIdFinanzasContexto = $tipoEmpresaId;
$empresaNombreFinanzasContexto = $empresaNombre;

if ($esSuperAdminGlobalSesion && $empresaIdFinanzasContexto <= 0) {
    try {
        $dbDashboardFinanzas = Database::connect();
        $stmtEmpresaFinanzas = $dbDashboardFinanzas->query("SELECT id, nombre FROM empresas ORDER BY id ASC LIMIT 1");
        $empresaFinanzasRow = $stmtEmpresaFinanzas ? $stmtEmpresaFinanzas->fetch(PDO::FETCH_ASSOC) : null;
        if ($empresaFinanzasRow) {
            $empresaIdFinanzasContexto = (int)($empresaFinanzasRow['id'] ?? 0);
            $empresaNombreFinanzasContexto = trim((string)($empresaFinanzasRow['nombre'] ?? 'Empresa')) ?: 'Empresa';
        }
    } catch (Throwable $e) {
        error_log('DASHBOARD_CONTEXTO_FINANZAS_ERROR: ' . $e->getMessage());
    }
}

if ($esSuperAdminGlobalSesion) {
    $perfilSuperAdmin = $cargarPerfilSuperAdminDashboard($rutaPerfilSuperAdminDashboard);
    $empresaId = 0;
    $tipoEmpresaId = 0;
    $empresaNombre = (string)($perfilSuperAdmin['nombre'] ?? 'PERFIL GLOBAL');
    $tipoEmpresaNombre = 'ACCESO GLOBAL';
    $_SESSION['empresa_id'] = 0;
    $_SESSION['tipo_empresa_id'] = 0;
    $_SESSION['empresa_nombre'] = $empresaNombre;
    $_SESSION['empresa_logo'] = '';
    $_SESSION['superadmin_logo'] = (string)($perfilSuperAdmin['logo'] ?? '');
    $_SESSION['empresa_contacto'] = [
        'direccion' => trim((string)($perfilSuperAdmin['direccion'] ?? 'Colombia')) ?: 'Colombia',
        'telefono' => trim((string)($perfilSuperAdmin['telefono'] ?? '')),
        'correo' => trim((string)($perfilSuperAdmin['correo'] ?? '')),
        'pais' => 'Colombia',
        'sobre_nosotros' => trim((string)($perfilSuperAdmin['sobre_nosotros'] ?? '')),
    ];
    $_SESSION['empresa_redes_sociales'] = isset($perfilSuperAdmin['redes_sociales']) && is_array($perfilSuperAdmin['redes_sociales'])
        ? $perfilSuperAdmin['redes_sociales']
        : [];
    if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
        $_SESSION['userData']['empresa_id'] = 0;
        $_SESSION['userData']['tipo_empresa_id'] = 0;
        $_SESSION['userData']['empresa_nombre'] = $empresaNombre;
    }
}

$puedeGestionEmpresaFinanzas = $empresaIdFinanzasContexto > 0
    && ($esSuperAdminGlobalSesion || $esSuperAdminModoEmpresa || $rolSesionInicial === 'administrador');

$empresaImagenSesionRaw = $esSuperAdminGlobalSesion
    ? trim((string)($_SESSION['superadmin_logo'] ?? ''))
    : trim((string)($_SESSION['empresa_logo'] ?? ''));
$dashboardLoadingLogo = rtrim((string)base_url(), '/') . '/Assets/images/partner_innovacion_logo.png';
$intentosColoresInfo = ['usados' => 0, 'tope' => 3, 'disponible' => false, 'sin_restriccion' => $esSuperAdminSinRestriccionColores];
$paqueteIntentosColoresDashboard = 3;
$montoIntentosColoresDashboard = 40000.0;

$coloresEmpresaDefault = [
    // Identidad visual base
    'color_principal'        => '#2F4A5A',
    'color_secundario'       => '#2F4A5A',
    'color_menu_lateral'     => '#2F4A5A',
    'color_botones'          => '#2F4A5A',
    'color_fondo'            => '#F4F6F8',
    'color_texto'            => '#2F4A5A',
    'color_bordes'           => '#DFF3DE',
    // Navegación
    'color_navbar'           => '#2F4A5A',
    'color_iconos_menu'      => '#FFFFFF',
    'color_hover_menu'       => '#3D6175',
    // Contenido
    'color_titulos'          => '#2F4A5A',
    'color_links'            => '#2F4A5A',
    // Tablas
    'color_fondo_tabla'      => '#FFFFFF',
    'color_texto_tabla'      => '#2F4A5A',
    'color_encabezado_tabla' => '#2F4A5A',
    'color_filas_alternas'   => '#F4F6F8',
    // Botones específicos
    'color_btn_crear'        => '#2F4A5A',
    'color_btn_editar'       => '#2F4A5A',
    'color_btn_eliminar'     => '#DC3545',
    // Formularios
    'color_focus_inputs'     => '#2F4A5A',
];

$normalizarColoresPerfilSuperAdminDashboard = static function (array $perfil) use ($coloresEmpresaDefault): array {
    $coloresPerfil = isset($perfil['colores']) && is_array($perfil['colores']) ? $perfil['colores'] : [];
    $colores = $coloresEmpresaDefault;
    foreach ($colores as $clave => $defecto) {
        $valor = strtoupper(trim((string)($coloresPerfil[$clave] ?? $defecto)));
        $colores[$clave] = (preg_match('/^#[0-9A-F]{6}$/', $valor) === 1)
            ? $valor
            : strtoupper((string)$defecto);
    }
    return $colores;
};

$asegurarTablaColoresSuperAdminGlobalDashboard = static function (PDO $conexion): void {
    $sql = "CREATE TABLE IF NOT EXISTS superadmin_colores_globales (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        usuario_id INT(11) NOT NULL,
        colores_json LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_superadmin_colores_globales_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conexion->exec($sql);
};

$guardarColoresSuperAdminGlobalDbDashboard = static function (PDO $conexion, int $usuarioIdGuardar, array $coloresGuardar) use ($asegurarTablaColoresSuperAdminGlobalDashboard): void {
    if ($usuarioIdGuardar <= 0) {
        throw new Exception('No se detecto usuario superadministrador para persistir colores globales.');
    }

    $asegurarTablaColoresSuperAdminGlobalDashboard($conexion);
    $jsonColores = json_encode($coloresGuardar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($jsonColores) || $jsonColores === '') {
        throw new Exception('No se pudo serializar la configuracion de colores globales.');
    }

    $stmt = $conexion->prepare(
        "INSERT INTO superadmin_colores_globales (usuario_id, colores_json, created_at, updated_at)
         VALUES (:usuario_id, :colores_json, NOW(), NOW())
         ON DUPLICATE KEY UPDATE colores_json = VALUES(colores_json), updated_at = NOW()"
    );
    $stmt->bindValue(':usuario_id', $usuarioIdGuardar, PDO::PARAM_INT);
    $stmt->bindValue(':colores_json', $jsonColores, PDO::PARAM_STR);
    $stmt->execute();
};

$cargarColoresSuperAdminGlobalDbDashboard = static function (PDO $conexion, int $usuarioIdBuscar) use ($asegurarTablaColoresSuperAdminGlobalDashboard, $normalizarColoresPerfilSuperAdminDashboard): ?array {
    if ($usuarioIdBuscar <= 0) {
        return null;
    }

    try {
        $asegurarTablaColoresSuperAdminGlobalDashboard($conexion);
        $stmt = $conexion->prepare("SELECT colores_json FROM superadmin_colores_globales WHERE usuario_id = :usuario_id LIMIT 1");
        $stmt->bindValue(':usuario_id', $usuarioIdBuscar, PDO::PARAM_INT);
        $stmt->execute();
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $normalizarColoresPerfilSuperAdminDashboard(['colores' => $decoded]);
    } catch (Throwable $e) {
        error_log('Error al cargar colores globales del superadmin desde BD: ' . $e->getMessage());
        return null;
    }
};

$normalizarUrlImagenEmpresaDashboard = static function ($ruta): string {
    $valor = trim((string)$ruta);
    if ($valor === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $valor) || str_starts_with($valor, 'data:')) {
        return $valor;
    }

    // Convert absolute-root paths and relative paths to full base_url() paths
    // to work consistently both in web and Electron/embedded server builds.
    if (str_starts_with($valor, '/')) {
        return rtrim((string)base_url(), '/') . $valor;
    }

    return rtrim((string)base_url(), '/') . '/' . ltrim($valor, '/');
};

$perfilSuperAdminBaseDashboard = $cargarPerfilSuperAdminDashboard($rutaPerfilSuperAdminDashboard);
$logoGlobalDashboard = $normalizarUrlImagenEmpresaDashboard((string)($perfilSuperAdminBaseDashboard['logo'] ?? ''));

if ($empresaImagenSesionRaw !== '') {
    $empresaImagenUrl = $normalizarUrlImagenEmpresaDashboard($empresaImagenSesionRaw);
}

if ($logoGlobalDashboard !== '') {
    $dashboardLoadingLogo = $logoGlobalDashboard;
}

if ($empresaImagenUrl !== '') {
    $dashboardLoadingLogo = $empresaImagenUrl;
}

$dashboardFavicon = rtrim((string)base_url(), '/') . '/favicon.ico';
$dashboardFaviconExt = 'ico';
$dashboardFaviconType = 'image/x-icon';
$dashboardFaviconVer = abs(crc32((string)($_SESSION['empresa_id'] ?? '0') . '|' . (string)($_SESSION['superadmin_logo'] ?? '') . '|' . $dashboardFavicon));
$dashboardFaviconUrl = $dashboardFavicon . '?v=' . $dashboardFaviconVer;
$dashboardHeaderLogoUrl = $empresaImagenUrl;
$dashboardHeaderBrandName = $empresaNombre;

if ($esClienteSesionDashboard) {
    $perfilSuperAdminClienteDashboard = $cargarPerfilSuperAdminDashboard($rutaPerfilSuperAdminDashboard);
    $logoSuperAdminCliente = $normalizarUrlImagenEmpresaDashboard((string)($perfilSuperAdminClienteDashboard['logo'] ?? ''));
    $nombreSuperAdminCliente = trim((string)($perfilSuperAdminClienteDashboard['nombre'] ?? ''));

    if ($logoSuperAdminCliente !== '') {
        $dashboardHeaderLogoUrl = $logoSuperAdminCliente;
    }

    if ($nombreSuperAdminCliente !== '') {
        $dashboardHeaderBrandName = $nombreSuperAdminCliente;
    }
}

$dashboardFooterLogoUrl = $logoGlobalDashboard !== ''
    ? $logoGlobalDashboard
    : ($dashboardLoadingLogo !== '' ? $dashboardLoadingLogo : $dashboardHeaderLogoUrl);

$procesarLogoEmpresaDashboard = static function (string $campoArchivo, int $empresaId): ?string {
    $imagenInfo = guardarImagenSubidaValidada($campoArchivo, [
        'label' => 'El logo de la empresa',
        'max_bytes' => 5 * 1024 * 1024,  // 5MB
        'allowed_mimes' => ['image/png' => 'png'],
        'exact_width' => 500,
        'exact_height' => 500,
        'require_transparency' => true,
        'rel_dirs' => ['Assets/images/Empresas', 'Assets/images/empresas'],
        'file_prefix' => 'empresa_' . max(1, (int)$empresaId),
    ]);

    return is_array($imagenInfo) ? (string)($imagenInfo['relative_path'] ?? '') : null;
};

$procesarLogoPerfilSuperAdminDashboard = static function (string $campoArchivo): ?string {
    $imagenInfo = guardarImagenSubidaValidada($campoArchivo, [
        'label' => 'La imagen del perfil global',
        'max_bytes' => 5 * 1024 * 1024,  // 5MB
        'allowed_mimes' => ['image/png' => 'png'],
        'exact_width' => 500,
        'exact_height' => 500,
        'require_transparency' => true,
        'rel_dirs' => ['Assets/images/Empresas', 'Assets/images/empresas'],
        'file_prefix' => 'superadmin_global',
    ]);

    return is_array($imagenInfo) ? (string)($imagenInfo['relative_path'] ?? '') : null;
};

$normalizarColorHexDashboard = static function ($valor, $defecto) {
    $valor = strtoupper(trim((string)$valor));
    if (preg_match('/^#[0-9A-F]{6}$/', $valor) === 1) {
        return $valor;
    }
    return strtoupper((string)$defecto);
};

$cacheEsquemaDashboard = ['tablas' => [], 'columnas' => []];
$tablaExisteDashboard = static function (PDO $conexion, string $tabla) use (&$cacheEsquemaDashboard): bool {
    $cacheKey = strtolower(trim($tabla));
    if (array_key_exists($cacheKey, $cacheEsquemaDashboard['tablas'])) {
        return (bool)$cacheEsquemaDashboard['tablas'][$cacheKey];
    }

    try {
        if (strtolower((string)$conexion->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
            $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
            $stmt = $conexion->prepare('SELECT COUNT(*) FROM sqlite_master WHERE type = "table" AND name = :tabla');
            $stmt->bindValue(':tabla', $tablaSegura, PDO::PARAM_STR);
            $stmt->execute();
            $existe = ((int)$stmt->fetchColumn() > 0);
            $cacheEsquemaDashboard['tablas'][$cacheKey] = $existe;
            return $existe;
        }

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla");
        $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
        $stmt->execute();
        $existe = ((int)$stmt->fetchColumn() > 0);
        $cacheEsquemaDashboard['tablas'][$cacheKey] = $existe;
        return $existe;
    } catch (Throwable $e) {
        $cacheEsquemaDashboard['tablas'][$cacheKey] = false;
        return false;
    }
};

$tablaColoresEmpresaExiste = static function (PDO $conexion): bool {
    try {
        return dbTableExists($conexion, 'colores_empresa');
    } catch (Throwable $e) {
        return false;
    }
};

$columnaExisteEnTablaDashboard = static function (PDO $conexion, string $tabla, string $columna) use (&$cacheEsquemaDashboard): bool {
    $cacheKey = strtolower(trim($tabla)) . ':' . strtolower(trim($columna));
    if (array_key_exists($cacheKey, $cacheEsquemaDashboard['columnas'])) {
        return (bool)$cacheEsquemaDashboard['columnas'][$cacheKey];
    }

    try {
        if (strtolower((string)$conexion->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
            $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
            $stmt = $conexion->prepare("PRAGMA table_info(\"{$tablaSegura}\")");
            $stmt->execute();
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columnas as $col) {
                if (strcasecmp(trim((string)($col['name'] ?? '')), $columna) === 0) {
                    $cacheEsquemaDashboard['columnas'][$cacheKey] = true;
                    return true;
                }
            }
            $cacheEsquemaDashboard['columnas'][$cacheKey] = false;
            return false;
        }

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
        $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
        $stmt->bindValue(':columna', $columna, PDO::PARAM_STR);
        $stmt->execute();
        $existe = ((int)$stmt->fetchColumn()) > 0;
        $cacheEsquemaDashboard['columnas'][$cacheKey] = $existe;
        return $existe;
    } catch (Throwable $e) {
        $cacheEsquemaDashboard['columnas'][$cacheKey] = false;
        return false;
    }
};

$leerConfigPasarelaDashboard = static function (string $key, string $default = ''): string {
    static $envCache = null;

    if (defined($key)) {
        return trim((string)constant($key));
    }

    if ($envCache === null) {
        $envCache = [];
        $envPath = ROOT_PATH . '/Config/.env';
        if (is_file($envPath) && is_readable($envPath)) {
            $lineas = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lineas)) {
                foreach ($lineas as $linea) {
                    $linea = trim((string)$linea);
                    if ($linea === '' || str_starts_with($linea, '#') || str_starts_with($linea, ';')) {
                        continue;
                    }
                    $posEq = strpos($linea, '=');
                    if ($posEq === false) {
                        continue;
                    }
                    $k = trim(substr($linea, 0, $posEq));
                    $v = trim(substr($linea, $posEq + 1));
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    if ($k !== '') {
                        $envCache[$k] = $v;
                    }
                }
            }
        }
    }

    return trim((string)($envCache[$key] ?? $default));
};

$asegurarTablaPagosIntentosColoresDashboard = static function (PDO $conexion): void {
    $sql = "CREATE TABLE IF NOT EXISTS pagos_intentos_colores (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        empresa_id INT(11) NOT NULL,
        usuario_id INT(11) NOT NULL,
        proveedor VARCHAR(50) NOT NULL DEFAULT 'wompi',
        estado ENUM('pendiente','aprobado','rechazado','cancelado','error') NOT NULL DEFAULT 'pendiente',
        monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        moneda VARCHAR(10) NOT NULL DEFAULT 'COP',
        referencia_externa VARCHAR(120) NOT NULL,
        cantidad_intentos INT(11) NOT NULL DEFAULT 3,
        respuesta_gateway LONGTEXT NULL,
        fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        fecha_aprobacion DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_pagos_intentos_colores_ref (referencia_externa),
        KEY idx_pagos_intentos_colores_empresa (empresa_id),
        KEY idx_pagos_intentos_colores_usuario (usuario_id),
        KEY idx_pagos_intentos_colores_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conexion->exec($sql);
};

$aplicarPagoAprobadoPendienteIntentosDashboard = static function (PDO $conexion, int $empresaId, int $usuarioId) use (
    $tablaColoresEmpresaExiste,
    $columnaExisteEnTablaDashboard,
    $paqueteIntentosColoresDashboard,
    $coloresEmpresaDefault
): bool {
    if ($empresaId <= 0 || $usuarioId <= 0) {
        return false;
    }

    if (! $tablaExisteDashboard($conexion, 'pagos_intentos_colores')) {
        return false;
    }

    if (!$tablaColoresEmpresaExiste($conexion)) {
        return false;
    }

    $tieneIntentos = $columnaExisteEnTablaDashboard($conexion, 'colores_empresa', 'intentos_usados')
                  && $columnaExisteEnTablaDashboard($conexion, 'colores_empresa', 'tope_intentos');
    if (!$tieneIntentos) {
        return false;
    }

    $stmtPago = $conexion->prepare(
        "SELECT id
         FROM pagos_intentos_colores
         WHERE empresa_id = :empresa_id
           AND usuario_id = :usuario_id
           AND estado = 'aprobado'
           AND (fecha_aprobacion IS NULL OR fecha_aprobacion = '0000-00-00 00:00:00')
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmtPago->execute([
        ':empresa_id' => $empresaId,
        ':usuario_id' => $usuarioId,
    ]);
    $pagoPendiente = $stmtPago->fetch(PDO::FETCH_ASSOC);
    if (!$pagoPendiente) {
        return false;
    }

    $conexion->beginTransaction();
    try {
        $stmtColor = $conexion->prepare("SELECT id FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1");
        $stmtColor->execute([':empresa_id' => $empresaId]);
        $colorId = (int)($stmtColor->fetchColumn() ?: 0);

        if ($colorId <= 0) {
            $columnasInsert = ['empresa_id', 'usuario_id', 'created_at', 'updated_at'];
            $valoresInsert = [':empresa_id', ':usuario_id', 'NOW()', 'NOW()'];
            $paramsInsert = [
                ':empresa_id' => $empresaId,
                ':usuario_id' => $usuarioId,
            ];

            foreach ($coloresEmpresaDefault as $clave => $valor) {
                if ($columnaExisteEnTablaDashboard($conexion, 'colores_empresa', $clave)) {
                    $columnasInsert[] = $clave;
                    $valoresInsert[] = ':' . $clave;
                    $paramsInsert[':' . $clave] = $valor;
                }
            }

            if ($columnaExisteEnTablaDashboard($conexion, 'colores_empresa', 'intentos_usados')) {
                $columnasInsert[] = 'intentos_usados';
                $valoresInsert[] = ':intentos_usados';
                $paramsInsert[':intentos_usados'] = 0;
            }
            if ($columnaExisteEnTablaDashboard($conexion, 'colores_empresa', 'tope_intentos')) {
                $columnasInsert[] = 'tope_intentos';
                $valoresInsert[] = ':tope_intentos';
                $paramsInsert[':tope_intentos'] = $paqueteIntentosColoresDashboard;
            }

            $sqlInsert = "INSERT INTO colores_empresa (" . implode(', ', $columnasInsert) . ") VALUES (" . implode(', ', $valoresInsert) . ")";
            $stmtInsert = $conexion->prepare($sqlInsert);
            $stmtInsert->execute($paramsInsert);
        } else {
            $setParts = ['usuario_id = :usuario_id', 'updated_at = NOW()'];
            $paramsUpdate = [
                ':id' => $colorId,
                ':usuario_id' => $usuarioId,
            ];

            if ($columnaExisteEnTablaDashboard($conexion, 'colores_empresa', 'intentos_usados')) {
                $setParts[] = 'intentos_usados = 0';
            }
            if ($columnaExisteEnTablaDashboard($conexion, 'colores_empresa', 'tope_intentos')) {
                $setParts[] = 'tope_intentos = :tope_intentos';
                $paramsUpdate[':tope_intentos'] = $paqueteIntentosColoresDashboard;
            }

            $stmtUpdate = $conexion->prepare("UPDATE colores_empresa SET " . implode(', ', $setParts) . " WHERE id = :id");
            $stmtUpdate->execute($paramsUpdate);
        }

        $stmtMarcar = $conexion->prepare(
            "UPDATE pagos_intentos_colores
             SET fecha_aprobacion = NOW(),
                 fecha_actualizacion = NOW()
             WHERE id = :id"
        );
        $stmtMarcar->execute([':id' => (int)$pagoPendiente['id']]);

        $conexion->commit();
        return true;
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $e;
    }
};

$cargarColoresEmpresa = static function (PDO $conexion, int $empresaId) use ($coloresEmpresaDefault, $normalizarColorHexDashboard, $tablaColoresEmpresaExiste, $columnaExisteEnTablaDashboard): array {
    $colores = $coloresEmpresaDefault;
    if ($empresaId <= 0 || !$tablaColoresEmpresaExiste($conexion)) {
        return $colores;
    }

    try {
        // Columnas siempre presentes desde la migración inicial
        $columnasBase = ['color_principal', 'color_secundario', 'color_menu_lateral', 'color_botones', 'color_fondo'];
        // Columnas extendidas: se añaden sólo si existen en la BD
        $columnasExtendidas = array_keys(array_diff_key($coloresEmpresaDefault, array_flip($columnasBase)));

        $columnasACargar = $columnasBase;
        foreach ($columnasExtendidas as $columna) {
            if ($columnaExisteEnTablaDashboard($conexion, 'colores_empresa', $columna)) {
                $columnasACargar[] = $columna;
            }
        }

        $sqlSelect = 'SELECT ' . implode(', ', $columnasACargar) . ' FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1';
        $stmt = $conexion->prepare($sqlSelect);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return $colores;
        }

        foreach ($colores as $clave => $defecto) {
            if (array_key_exists($clave, $fila)) {
                $colores[$clave] = $normalizarColorHexDashboard($fila[$clave], $defecto);
            }
        }
        // Fallbacks para columnas base que aún no existen
        if (!in_array('color_texto', $columnasACargar, true)) {
            $colores['color_texto'] = $colores['color_principal'];
        }
        if (!in_array('color_bordes', $columnasACargar, true)) {
            $colores['color_bordes'] = $coloresEmpresaDefault['color_bordes'];
        }
    } catch (Throwable $e) {
        error_log('Error al cargar colores_empresa en dashboard: ' . $e->getMessage());
    }

    return $colores;
};

// ── Acción: restaurar colores por defecto ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restaurar_colores_empresa') {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        if ($esSuperAdminGlobalSesion && !$esSuperAdminModoEmpresa) {
            $dbGlobalColores = Database::connect();
            $guardarColoresSuperAdminGlobalDbDashboard($dbGlobalColores, (int)$usuarioId, $coloresEmpresaDefault);

            $perfilActual = $cargarPerfilSuperAdminDashboard($rutaPerfilSuperAdminDashboard);
            $perfilActual['colores'] = $coloresEmpresaDefault;
            $perfilActual['updated_at'] = date('c');

            $jsonPerfil = json_encode($perfilActual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (is_string($jsonPerfil) && $jsonPerfil !== '') {
                @file_put_contents($rutaPerfilSuperAdminDashboard, $jsonPerfil, LOCK_EX);
            }

            $_SESSION['colores_empresa'] = $coloresEmpresaDefault;
            echo json_encode(['success' => true, 'message' => 'Colores restaurados a los valores por defecto.', 'colores' => $coloresEmpresaDefault]);
            exit;
        }

        if ($empresaId <= 0 || !$usuarioId) {
            throw new Exception('No se detecto empresa o usuario en sesion.');
        }
        $dbColores = Database::connect();
        if ($tablaColoresEmpresaExiste($dbColores)) {
            $stmtExiste = $dbColores->prepare("SELECT id FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1");
            $stmtExiste->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmtExiste->execute();
            $idColorEmpresa = (int)($stmtExiste->fetchColumn() ?: 0);

            $coloresRestaurar = $coloresEmpresaDefault;
            // Construir SET dinámico para cada columna de color que exista
            $setParts = ['usuario_id = :usuario_id', 'updated_at = NOW()'];
            foreach ($coloresRestaurar as $clave => $valor) {
                if ($columnaExisteEnTablaDashboard($dbColores, 'colores_empresa', $clave)) {
                    $setParts[] = "$clave = :$clave";
                }
            }
            if ($idColorEmpresa > 0) {
                $stmtR = $dbColores->prepare("UPDATE colores_empresa SET " . implode(', ', $setParts) . " WHERE id = :id");
                $stmtR->bindValue(':id', $idColorEmpresa, PDO::PARAM_INT);
            } else {
                $stmtR = $dbColores->prepare("INSERT INTO colores_empresa (empresa_id, usuario_id, " . implode(', ', array_keys($coloresRestaurar)) . ", created_at, updated_at) VALUES (:empresa_id, :usuario_id, " . implode(', ', array_map(fn($k) => ":$k", array_keys($coloresRestaurar))) . ", NOW(), NOW())");
                $stmtR->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            }
            $stmtR->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            foreach ($coloresRestaurar as $clave => $valor) {
                if ($columnaExisteEnTablaDashboard($dbColores, 'colores_empresa', $clave)) {
                    $stmtR->bindValue(":$clave", $valor, PDO::PARAM_STR);
                }
            }
            $stmtR->execute();
        }
        $_SESSION['colores_empresa'] = $coloresEmpresaDefault;
        echo json_encode(['success' => true, 'message' => 'Colores restaurados a los valores por defecto.', 'colores' => $coloresEmpresaDefault]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Acción: reset gratis deshabilitado; ahora requiere pasarela ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_intentos_colores') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'El reinicio directo de intentos fue deshabilitado. Debes pagar para reactivar el paquete de 3 intentos.',
    ]);
    exit;
}

// ── Acción: crear checkout real para 3 intentos por Wompi ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear_checkout_intentos_colores') {
    header('Content-Type: application/json; charset=UTF-8');
    @ini_set('display_errors', '0');
    if (ob_get_level() === 0) {
        ob_start();
    }

    try {
        if ($empresaId <= 0 || !$usuarioId) {
            throw new Exception('No se detecto empresa o usuario en sesion.');
        }

        $dbColores = Database::connect();
        if (!$tablaColoresEmpresaExiste($dbColores)) {
            throw new Exception('La tabla colores_empresa no existe.');
        }

        $tieneIntentos = $columnaExisteEnTablaDashboard($dbColores, 'colores_empresa', 'intentos_usados')
                      && $columnaExisteEnTablaDashboard($dbColores, 'colores_empresa', 'tope_intentos');
        if (!$tieneIntentos) {
            throw new Exception('El sistema de intentos aun no esta disponible. Ejecuta la migracion 20260311_add_colores_avanzados_intentos.sql');
        }

        $stmtI = $dbColores->prepare("SELECT id, intentos_usados, tope_intentos FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1");
        $stmtI->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmtI->execute();
        $filaI = $stmtI->fetch(PDO::FETCH_ASSOC);
        $intentosUsados = $filaI ? max(0, min($paqueteIntentosColoresDashboard, (int)($filaI['intentos_usados'] ?? 0))) : 0;

        if ($intentosUsados < $paqueteIntentosColoresDashboard) {
            throw new Exception('Aun tienes intentos disponibles. Solo puedes pagar cuando consumas el paquete de 3.');
        }

        $wompiPublicKey = $leerConfigPasarelaDashboard('WOMPI_PUBLIC_KEY');
        $wompiIntegrity = $leerConfigPasarelaDashboard('WOMPI_INTEGRITY_SECRET');
        if ($wompiPublicKey === '' || $wompiIntegrity === '') {
            throw new Exception('La pasarela Wompi no esta configurada correctamente.');
        }

        $asegurarTablaPagosIntentosColoresDashboard($dbColores);

        $reference = 'CLR-' . max(1, $empresaId) . '-' . max(1, (int)$usuarioId) . '-' . time() . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $amountInCents = wompiCalcularMontoEnCentavos($montoIntentosColoresDashboard);
        $signature = wompiGenerarFirma($reference, $amountInCents, 'COP', $wompiIntegrity);
        if ($signature === '') {
            throw new Exception('No fue posible generar la firma de integridad para Wompi. Verifica la referencia, el monto y la clave de integridad.');
        }

        $stmtPago = $dbColores->prepare("INSERT INTO pagos_intentos_colores (empresa_id, usuario_id, proveedor, estado, monto, moneda, referencia_externa, cantidad_intentos, respuesta_gateway) VALUES (:empresa_id, :usuario_id, 'wompi', 'pendiente', :monto, 'COP', :referencia, :cantidad, :respuesta)");
        $stmtPago->execute([
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
            ':monto' => $montoIntentosColoresDashboard,
            ':referencia' => $reference,
            ':cantidad' => $paqueteIntentosColoresDashboard,
            ':respuesta' => json_encode([
                'contexto' => 'colores_empresa',
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        if (ob_get_length() !== false && ob_get_length() > 0) {
            @ob_clean();
        }
        echo json_encode([
            'success' => true,
            'checkout' => [
                'publicKey' => $wompiPublicKey,
                'currency' => 'COP',
                'amountInCents' => $amountInCents,
                'reference' => $reference,
                'signature' => $signature,
                'redirectUrl' => rtrim(base_url(), '/') . '/Views/dashboard.php?wompi_intentos=1&ref=' . rawurlencode($reference),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        if (ob_get_length() !== false && ob_get_length() > 0) {
            @ob_clean();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── Acción: consultar estado del pago de intentos ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'consultar_pago_intentos_colores') {
    header('Content-Type: application/json; charset=UTF-8');
    @ini_set('display_errors', '0');
    if (ob_get_level() === 0) {
        ob_start();
    }

    try {
        if ($empresaId <= 0 || !$usuarioId) {
            throw new Exception('No se detecto empresa o usuario en sesion.');
        }

        $reference = trim((string)($_POST['reference'] ?? ''));
        if ($reference === '') {
            throw new Exception('Referencia de pago no valida.');
        }

        $dbColores = Database::connect();
        $asegurarTablaPagosIntentosColoresDashboard($dbColores);

        $stmtPago = $dbColores->prepare("SELECT id, estado, cantidad_intentos, fecha_aprobacion FROM pagos_intentos_colores WHERE referencia_externa = :referencia AND empresa_id = :empresa_id AND usuario_id = :usuario_id ORDER BY id DESC LIMIT 1");
        $stmtPago->execute([
            ':referencia' => $reference,
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
        ]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);
        if (!$pago) {
            throw new Exception('No se encontro el pago solicitado.');
        }

        $estadoPago = strtolower(trim((string)($pago['estado'] ?? 'pendiente')));
        // Compatibilidad ante estados escritos manualmente con variaciones.
        if (in_array($estadoPago, ['approved', 'aprovado', 'pagado'], true)) {
            $estadoPago = 'aprobado';
        }

        if ($estadoPago === 'aprobado' && $tablaColoresEmpresaExiste($dbColores)) {
            $stmtResetIntentos = $dbColores->prepare(
                "UPDATE colores_empresa
                 SET intentos_usados = 0,
                     tope_intentos = :tope,
                     usuario_id = :usuario_id,
                     updated_at = NOW()
                 WHERE empresa_id = :empresa_id
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $stmtResetIntentos->execute([
                ':tope' => $paqueteIntentosColoresDashboard,
                ':usuario_id' => $usuarioId,
                ':empresa_id' => $empresaId,
            ]);

            if (empty($pago['fecha_aprobacion'])) {
                $stmtMarcarAprobacion = $dbColores->prepare(
                    "UPDATE pagos_intentos_colores
                     SET fecha_aprobacion = NOW(),
                         fecha_actualizacion = NOW()
                     WHERE id = :id"
                );
                $stmtMarcarAprobacion->execute([':id' => (int)($pago['id'] ?? 0)]);
            }
        }

        $usados = 0;
        $tope = $paqueteIntentosColoresDashboard;
        if ($tablaColoresEmpresaExiste($dbColores)) {
            $stmtIntentos = $dbColores->prepare("SELECT intentos_usados, tope_intentos FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1");
            $stmtIntentos->execute([':empresa_id' => $empresaId]);
            $filaIntentos = $stmtIntentos->fetch(PDO::FETCH_ASSOC);
            if ($filaIntentos) {
                $usados = max(0, min($paqueteIntentosColoresDashboard, (int)($filaIntentos['intentos_usados'] ?? 0)));
            }
        }

        if (ob_get_length() !== false && ob_get_length() > 0) {
            @ob_clean();
        }
        echo json_encode([
            'success' => true,
            'estado' => $estadoPago,
            'intentos_usados' => $usados,
            'tope_intentos' => $tope,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        if (ob_get_length() !== false && ob_get_length() > 0) {
            @ob_clean();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}



// ── Acción: guardar colores de empresa ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_datos_empresa') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        if ($esSuperAdminGlobalSesion) {
            if (!$usuarioId) {
                throw new Exception('No se detecto usuario en sesion.');
            }

            $nombrePerfilNuevo = trim((string)($_POST['empresa_nombre'] ?? ''));
            if ($nombrePerfilNuevo === '') {
                throw new Exception('El nombre del perfil es obligatorio.');
            }
            if (mb_strlen($nombrePerfilNuevo, 'UTF-8') > 120) {
                throw new Exception('El nombre del perfil no puede superar 120 caracteres.');
            }

            $perfilActual = $cargarPerfilSuperAdminDashboard($rutaPerfilSuperAdminDashboard);
            $logoPerfilAnterior = trim((string)($perfilActual['logo'] ?? ''));
            $logoPerfilRuta = trim((string)($perfilActual['logo'] ?? ''));
            $hayArchivoLogoGlobal = isset($_FILES['logo_empresa_archivo'])
                && is_array($_FILES['logo_empresa_archivo'])
                && (int)($_FILES['logo_empresa_archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

            if ($hayArchivoLogoGlobal) {
                $logoProcesado = $procesarLogoPerfilSuperAdminDashboard('logo_empresa_archivo');
                if (is_string($logoProcesado) && $logoProcesado !== '') {
                    $logoPerfilRuta = $logoProcesado;
                }
            }

            $telefonoPerfilNuevo = trim((string)($_POST['empresa_telefono'] ?? ''));
            $direccionPerfilNueva = trim((string)($_POST['empresa_direccion'] ?? 'Colombia')) ?: 'Colombia';
            $correoPerfilNuevo = trim((string)($_POST['empresa_correo'] ?? ($correo ?: ($perfilActual['correo'] ?? ''))));
            $sobreNosotrosPerfilNuevo = trim((string)($_POST['sobre_nosotros'] ?? ($perfilActual['sobre_nosotros'] ?? '')));
            $redesPerfilNuevo = [];
            foreach ($empresaRedesSocialesDef as $empresaRedSocialColumna => $empresaRedSocialLabel) {
                $empresaRedSocialValor = trim((string)($_POST[$empresaRedSocialColumna] ?? ''));
                if ($empresaRedSocialValor !== '' && filter_var($empresaRedSocialValor, FILTER_VALIDATE_URL) === false) {
                    throw new Exception('La URL de ' . $empresaRedSocialLabel . ' no es valida.');
                }
                $redesPerfilNuevo[$empresaRedSocialColumna] = $empresaRedSocialValor;
            }

            $payloadPerfil = [
                'nombre' => $nombrePerfilNuevo,
                'logo' => $logoPerfilRuta,
                'direccion' => $direccionPerfilNueva,
                'telefono' => $telefonoPerfilNuevo,
                'correo' => $correoPerfilNuevo,
                'sobre_nosotros' => $sobreNosotrosPerfilNuevo,
                'redes_sociales' => $redesPerfilNuevo,
                'updated_at' => date('c'),
            ];

            $jsonPerfil = json_encode($payloadPerfil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (!is_string($jsonPerfil) || @file_put_contents($rutaPerfilSuperAdminDashboard, $jsonPerfil, LOCK_EX) === false) {
                throw new Exception('No se pudo guardar la configuracion del perfil global.');
            }

            $_SESSION['empresa_nombre'] = $nombrePerfilNuevo;
            $_SESSION['empresa_logo'] = '';
            $_SESSION['superadmin_logo'] = $logoPerfilRuta;
            $_SESSION['empresa_contacto'] = [
                'direccion' => $direccionPerfilNueva,
                'telefono' => $telefonoPerfilNuevo,
                'correo' => $correoPerfilNuevo,
                'pais' => 'Colombia',
                'sobre_nosotros' => $sobreNosotrosPerfilNuevo,
            ];
            $_SESSION['empresa_redes_sociales'] = $redesPerfilNuevo;
            if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
                $_SESSION['userData']['empresa_nombre'] = $nombrePerfilNuevo;
            }

            if ($hayArchivoLogoGlobal && $logoPerfilAnterior !== '' && $logoPerfilAnterior !== $logoPerfilRuta) {
                eliminarArchivoProyectoSiExiste($logoPerfilAnterior);
            }

            // Registrar la actualizacion del perfil global en el log de actualizaciones
            try {
                $auditModelSA = new Actualizacion(Database::connect());
                $auditModelSA->registrar([
                    'empresa_id'        => null,
                    'actor_usuario_id'  => (int)($_SESSION['usuario_id'] ?? 0),
                    'actor_nombre'      => trim((string)($_SESSION['userData']['nombres'] ?? 'Super Administrador')),
                    'actor_rol'         => (string)($_SESSION['rol'] ?? 'super administrador'),
                    'modulo'            => 'perfil',
                    'accion'            => 'actualizar',
                    'entidad'           => 'perfil_superadmin',
                    'registro_id'       => (string)($_SESSION['usuario_id'] ?? 0),
                    'antes_json'        => json_encode($perfilActual, JSON_UNESCAPED_UNICODE),
                    'despues_json'      => json_encode($payloadPerfil, JSON_UNESCAPED_UNICODE),
                    'detalle'           => 'Actualizacion de perfil del superadministrador global.',
                    'ip_origen'         => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
                ]);
            } catch (Throwable $auditErrSA) {
                error_log('No se pudo registrar actualizacion del perfil superadmin: ' . $auditErrSA->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => 'Perfil global actualizado correctamente.',
                'empresa_nombre' => $nombrePerfilNuevo,
                'logo_empresa_url' => $normalizarUrlImagenEmpresaDashboard($logoPerfilRuta),
            ]);
            exit;
        }

        if ($empresaId <= 0 && $usuarioId) {
            $dbCtx = Database::connect();
            $stmtCtx = $dbCtx->prepare("SELECT empresa_id FROM usuarios WHERE id = :usuario_id LIMIT 1");
            $stmtCtx->bindValue(':usuario_id', (int)$usuarioId, PDO::PARAM_INT);
            $stmtCtx->execute();
            $empresaFallback = (int)($stmtCtx->fetchColumn() ?: 0);

            if ($empresaFallback > 0) {
                $empresaId = $empresaFallback;
                $_SESSION['empresa_id'] = $empresaFallback;
            }
        }

        if (!$puedeGestionEmpresaPerfil) {
            throw new Exception('No tienes permisos para editar datos de empresa.');
        }

        if ($empresaId <= 0 || !$usuarioId) {
            throw new Exception('No se detecto empresa o usuario en sesion.');
        }

        $nombreEmpresaNuevo = trim((string)($_POST['empresa_nombre'] ?? ''));
        if ($nombreEmpresaNuevo === '') {
            throw new Exception('El nombre de la empresa es obligatorio.');
        }

        if (mb_strlen($nombreEmpresaNuevo, 'UTF-8') > 120) {
            throw new Exception('El nombre de la empresa no puede superar 120 caracteres.');
        }

        $empresaRedesPayload = [];
        foreach ($empresaRedesSocialesDef as $empresaRedSocialColumna => $empresaRedSocialLabel) {
            $empresaRedSocialValor = trim((string)($_POST[$empresaRedSocialColumna] ?? ''));
            if ($empresaRedSocialValor !== '' && filter_var($empresaRedSocialValor, FILTER_VALIDATE_URL) === false) {
                throw new Exception('La URL de ' . $empresaRedSocialLabel . ' no es valida.');
            }
            $empresaRedesPayload[$empresaRedSocialColumna] = $empresaRedSocialValor;
        }

        $empresaTelefonoNuevo = trim((string)($_POST['empresa_telefono'] ?? ''));
        $empresaDireccionNueva = trim((string)($_POST['empresa_direccion'] ?? ''));

        $dbEmpresa = Database::connect();
        $empresaColumnasSelect = ['id', 'nombre'];
        if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'imagen')) {
            $empresaColumnasSelect[] = 'imagen';
        }
        foreach (array_keys($empresaRedesSocialesDef) as $empresaRedSocialColumna) {
            if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', $empresaRedSocialColumna)) {
                $empresaColumnasSelect[] = $empresaRedSocialColumna;
            }
        }
        foreach (['direccion', 'telefono', 'correo_electronico', 'email', 'sobre_nosotros'] as $empresaDatoColumna) {
            if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', $empresaDatoColumna)) {
                $empresaColumnasSelect[] = $empresaDatoColumna;
            }
        }

        $stmtEmpresa = $dbEmpresa->prepare("SELECT " . implode(', ', $empresaColumnasSelect) . " FROM empresas WHERE id = :empresa_id LIMIT 1");
        $stmtEmpresa->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmtEmpresa->execute();
        $empresaActual = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);
        if (!$empresaActual) {
            throw new Exception('No se encontro la empresa asociada a la sesion.');
        }

        $empresaSobreNosotrosNuevo = trim((string)($_POST['sobre_nosotros'] ?? ($empresaActual['sobre_nosotros'] ?? '')));
        $empresaCorreoNuevo = trim((string)($empresaActual['correo_electronico'] ?? ($empresaActual['email'] ?? '')));
        if ($empresaDireccionNueva === '') {
            $empresaDireccionNueva = trim((string)($empresaActual['direccion'] ?? 'Colombia'));
        }
        if ($empresaDireccionNueva === '') {
            $empresaDireccionNueva = 'Colombia';
        }

        $hayArchivoLogo = isset($_FILES['logo_empresa_archivo'])
            && is_array($_FILES['logo_empresa_archivo'])
            && (int)($_FILES['logo_empresa_archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        $logoEmpresaRutaGuardar = null;
        $logoEmpresaUrlRespuesta = '';
        if ($hayArchivoLogo) {
            if (!$columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'imagen')) {
                throw new Exception('La columna empresas.imagen no existe. Ejecuta la migracion de imagenes de empresa.');
            }

            $logoEmpresaRutaGuardar = $procesarLogoEmpresaDashboard('logo_empresa_archivo', $empresaId);
            if ($logoEmpresaRutaGuardar !== null) {
                $logoEmpresaUrlRespuesta = $normalizarUrlImagenEmpresaDashboard($logoEmpresaRutaGuardar);
            }
        }

        $empresaCamposUpdate = ['nombre = :nombre'];
        if ($logoEmpresaRutaGuardar !== null) {
            $empresaCamposUpdate[] = 'imagen = :imagen';
        }

        $empresaRedesGuardadas = [];
        foreach ($empresaRedesPayload as $empresaRedSocialColumna => $empresaRedSocialValor) {
            if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', $empresaRedSocialColumna)) {
                $empresaCamposUpdate[] = $empresaRedSocialColumna . ' = :' . $empresaRedSocialColumna;
                $empresaRedesGuardadas[$empresaRedSocialColumna] = $empresaRedSocialValor;
            }
        }

        if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'telefono')) {
            $empresaCamposUpdate[] = 'telefono = :empresa_telefono';
        }
        if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'direccion')) {
            $empresaCamposUpdate[] = 'direccion = :empresa_direccion';
        }
        if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'sobre_nosotros')) {
            $empresaCamposUpdate[] = 'sobre_nosotros = :sobre_nosotros';
        }
        $slugEmpresaNuevo = '';
        if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'slug_tienda')) {
            $slugEmpresaNuevo = slugifyText($nombreEmpresaNuevo);
            if ($slugEmpresaNuevo === '') {
                throw new Exception('No fue posible generar la URL de la empresa a partir del nombre.');
            }

            $slugEmpresaBase = $slugEmpresaNuevo;
            $slugIntentoEmpresa = 1;
            while (true) {
                $stmtSlugDuplicadoEmpresa = $dbEmpresa->prepare('SELECT id FROM empresas WHERE slug_tienda = :slug AND id <> :empresa_id LIMIT 1');
                $stmtSlugDuplicadoEmpresa->bindValue(':slug', $slugEmpresaNuevo, PDO::PARAM_STR);
                $stmtSlugDuplicadoEmpresa->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                $stmtSlugDuplicadoEmpresa->execute();
                if (!$stmtSlugDuplicadoEmpresa->fetch(PDO::FETCH_ASSOC)) {
                    break;
                }

                $slugIntentoEmpresa++;
                $slugEmpresaNuevo = $slugEmpresaBase . '-' . $slugIntentoEmpresa;
            }

            $empresaCamposUpdate[] = 'slug_tienda = :slug_tienda';
        }

        $updateEmpresaQuery = strtolower((string)$dbEmpresa->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite'
            ? "UPDATE empresas SET " . implode(', ', $empresaCamposUpdate) . " WHERE id = :empresa_id"
            : "UPDATE empresas SET " . implode(', ', $empresaCamposUpdate) . " WHERE id = :empresa_id LIMIT 1";
        $stmtUpdateEmpresa = $dbEmpresa->prepare($updateEmpresaQuery);
        if ($logoEmpresaRutaGuardar !== null) {
            $stmtUpdateEmpresa->bindValue(':imagen', $logoEmpresaRutaGuardar, PDO::PARAM_STR);
        }
        foreach ($empresaRedesGuardadas as $empresaRedSocialColumna => $empresaRedSocialValor) {
            $stmtUpdateEmpresa->bindValue(':' . $empresaRedSocialColumna, $empresaRedSocialValor, PDO::PARAM_STR);
        }
        if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'telefono')) {
            $stmtUpdateEmpresa->bindValue(':empresa_telefono', $empresaTelefonoNuevo, PDO::PARAM_STR);
        }
        if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'direccion')) {
            $stmtUpdateEmpresa->bindValue(':empresa_direccion', $empresaDireccionNueva, PDO::PARAM_STR);
        }
        if ($slugEmpresaNuevo !== '') {
            $stmtUpdateEmpresa->bindValue(':slug_tienda', $slugEmpresaNuevo, PDO::PARAM_STR);
        }
        if ($columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'sobre_nosotros')) {
            $stmtUpdateEmpresa->bindValue(':sobre_nosotros', $empresaSobreNosotrosNuevo, PDO::PARAM_STR);
        }

        $stmtUpdateEmpresa->bindValue(':nombre', $nombreEmpresaNuevo, PDO::PARAM_STR);
        $stmtUpdateEmpresa->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmtUpdateEmpresa->execute();

        $_SESSION['empresa_nombre'] = $nombreEmpresaNuevo;
        if ($slugEmpresaNuevo !== '') {
            $_SESSION['empresa_store_slug'] = $slugEmpresaNuevo;
        }
        if ($logoEmpresaRutaGuardar !== null) {
            $_SESSION['empresa_logo'] = $logoEmpresaRutaGuardar;
        }
        $_SESSION['empresa_redes_sociales'] = array_merge($empresaRedesSociales, $empresaRedesGuardadas);
        $_SESSION['empresa_contacto'] = [
            'direccion' => $empresaDireccionNueva,
            'telefono' => $empresaTelefonoNuevo,
            'correo' => $empresaCorreoNuevo,
            'pais' => 'Colombia',
            'sobre_nosotros' => $empresaSobreNosotrosNuevo,
        ];

        // Leer NUEVAMENTE de BD después del UPDATE para obtener los valores que se guardaron
        $stmtVerificar = $dbEmpresa->prepare("SELECT " . implode(', ', $empresaColumnasSelect) . " FROM empresas WHERE id = :empresa_id LIMIT 1");
        $stmtVerificar->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmtVerificar->execute();
        $empresaVerificada = $stmtVerificar->fetch(PDO::FETCH_ASSOC);
        
        $logoAnterior = trim((string)($empresaActual['imagen'] ?? ''));
        $logoNuevo = trim((string)($empresaVerificada['imagen'] ?? ''));
        $nombreAnterior = trim((string)($empresaActual['nombre'] ?? ''));
        $nombreNuevo = trim((string)($empresaVerificada['nombre'] ?? ''));
        $redesAntes = [];
        $redesDespues = [];
        foreach (array_keys($empresaRedesSocialesDef) as $empresaRedSocialColumna) {
            $redesAntes[$empresaRedSocialColumna] = trim((string)($empresaActual[$empresaRedSocialColumna] ?? ''));
            $redesDespues[$empresaRedSocialColumna] = trim((string)($empresaVerificada[$empresaRedSocialColumna] ?? ''));
        }
        $contactoAntes = [
            'direccion' => trim((string)($empresaActual['direccion'] ?? '')),
            'telefono' => trim((string)($empresaActual['telefono'] ?? '')),
            'correo' => trim((string)($empresaActual['correo_electronico'] ?? ($empresaActual['email'] ?? ''))),
            'sobre_nosotros' => trim((string)($empresaActual['sobre_nosotros'] ?? '')),
        ];
        $contactoDespues = [
            'direccion' => trim((string)($empresaVerificada['direccion'] ?? '')),
            'telefono' => trim((string)($empresaVerificada['telefono'] ?? '')),
            'correo' => trim((string)($empresaVerificada['correo_electronico'] ?? ($empresaVerificada['email'] ?? ''))),
            'sobre_nosotros' => trim((string)($empresaVerificada['sobre_nosotros'] ?? '')),
        ];
        $_SESSION['empresa_redes_sociales'] = $redesDespues;
        $tipoEmpresaSesion = trim((string)($tipoEmpresaNombre ?? ($_SESSION['tipo_empresa_id'] ?? '')));

        if ($hayArchivoLogo && $logoAnterior !== '' && $logoAnterior !== $logoNuevo) {
            eliminarArchivoProyectoSiExiste($logoAnterior);
        }

        // DEBUG: Registrar valores para auditar el flujo
        $debugInfo = [
            'hayArchivoLogo' => (bool)$hayArchivoLogo,
            'logoEmpresaRutaGuardar' => $logoEmpresaRutaGuardar,
            'logoAnterior' => $logoAnterior,
            'logoNuevo (de BD)' => $logoNuevo,
            'nombreAnterior' => $nombreAnterior,
            'nombreNuevo (de BD)' => $nombreNuevo,
        ];
        error_log('DEBUG SAVE EMPRESA: ' . json_encode($debugInfo, JSON_UNESCAPED_UNICODE));

        // Registro robusto en auditoria (SIEMPRE registra imagen y nombre)
        try {
            $auditModel = new Actualizacion(Database::connect());
            
            $datosBefore = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => $nombreAnterior,
                'tipo_empresa' => $tipoEmpresaSesion,
                'empresa_imagen' => $logoAnterior,
                'contacto' => $contactoAntes,
                'redes_sociales' => $redesAntes,
            ];
            $datosAfter = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => $nombreNuevo,
                'tipo_empresa' => $tipoEmpresaSesion,
                'empresa_imagen' => $logoNuevo,
                'contacto' => $contactoDespues,
                'redes_sociales' => $redesDespues,
            ];
            $beforeJson = json_encode($datosBefore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $afterJson = json_encode($datosAfter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            // DEBUG DETALLADO
            error_log('=== AUDIT SAVE DETAILED ===');
            error_log('BEFORE: ' . $beforeJson);
            error_log('AFTER: ' . $afterJson);
            error_log('logoAnterior: "' . $logoAnterior . '"');
            error_log('logoNuevo: "' . $logoNuevo . '"');
            error_log('nombreAnterior: "' . $nombreAnterior . '"');
            error_log('nombreNuevo: "' . $nombreNuevo . '"');
            error_log('========================');
            
            $auditModel->registrar([
                'empresa_id' => $empresaId,
                'actor_usuario_id' => (int)$usuarioId,
                'actor_nombre' => trim((string)($nombrePerfilVisual ?? $nombreCompleto ?? ($_SESSION['correo'] ?? 'Usuario'))),
                'actor_rol' => (string)($rolPerfilVisual ?? $rolNombre ?? ($_SESSION['rol'] ?? '')),
                'modulo' => 'empresa',
                'accion' => 'actualizar_datos_empresa',
                'entidad' => 'empresa',
                'registro_id' => (string)$empresaId,
                'antes_json' => $beforeJson,
                'despues_json' => $afterJson,
                'detalle' => 'Actualizacion de nombre/logo de empresa desde panel de datos.',
                'ip_origen' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $auditError) {
            error_log('No se pudo registrar actualizacion de datos empresa: ' . $auditError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Datos de empresa guardados correctamente.',
            'empresa_nombre' => $nombreEmpresaNuevo,
            'empresa_slug_tienda' => $slugEmpresaNuevo,
            'empresa_store_url' => $slugEmpresaNuevo !== '' ? currentStoreBaseUrl($slugEmpresaNuevo) : '',
            'logo_empresa_url' => $logoEmpresaUrlRespuesta,
            'debug' => [
                'logoAnterior' => $logoAnterior,
                'logoNuevo' => $logoNuevo,
                'nombreAnterior' => $nombreAnterior,
                'nombreNuevo' => $nombreNuevo,
                'cambioImagen' => ($logoAnterior !== $logoNuevo),
            ]
        ]);
        exit;
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_datos_bancarios_empresa') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        if (!$puedeGestionEmpresaFinanzas) {
            throw new Exception('Los datos bancarios solo se pueden configurar dentro de una empresa.');
        }

        $empresaIdObjetivoFinanzas = $empresaIdFinanzasContexto > 0 ? $empresaIdFinanzasContexto : $empresaId;
        $empresaNombreObjetivoFinanzas = trim((string)($empresaNombreFinanzasContexto ?: $empresaNombre));
        if ($empresaIdObjetivoFinanzas <= 0) {
            throw new Exception('No se detectó una empresa para guardar los datos bancarios.');
        }

        $dbEmpresa = Database::connect();
        if (!$tablaExisteDashboard($dbEmpresa, 'empresa_cuentas_bancarias')) {
            throw new Exception('La tabla empresa_cuentas_bancarias no existe. Ejecuta la migración correspondiente.');
        }
        if (!$columnaExisteEnTablaDashboard($dbEmpresa, 'empresas', 'slug_tienda')) {
            throw new Exception('La columna empresas.slug_tienda no existe. Ejecuta la migración correspondiente.');
        }

        $slugTiendaNuevo = slugifyText($empresaNombreObjetivoFinanzas);
        if ($slugTiendaNuevo === '') {
            throw new Exception('Debes indicar una URL válida para la tienda.');
        }

        $slugBaseEmpresa = $slugTiendaNuevo;
        $slugIntentoEmpresa = 1;
        while (true) {
            $stmtSlugDuplicado = $dbEmpresa->prepare('SELECT id FROM empresas WHERE slug_tienda = :slug AND id <> :empresa_id LIMIT 1');
            $stmtSlugDuplicado->bindValue(':slug', $slugTiendaNuevo, PDO::PARAM_STR);
            $stmtSlugDuplicado->bindValue(':empresa_id', $empresaIdObjetivoFinanzas, PDO::PARAM_INT);
            $stmtSlugDuplicado->execute();
            if (!$stmtSlugDuplicado->fetch(PDO::FETCH_ASSOC)) {
                break;
            }

            $slugIntentoEmpresa++;
            $slugTiendaNuevo = $slugBaseEmpresa . '-' . $slugIntentoEmpresa;
        }

        $tipoCuentaNuevo = trim((string)($_POST['tipo_cuenta'] ?? 'ahorros'));
        $tiposCuentaValidos = ['ahorros', 'corriente'];
        if (!in_array($tipoCuentaNuevo, $tiposCuentaValidos, true)) {
            throw new Exception('El tipo de cuenta seleccionado no es válido.');
        }

        $payloadCuentaBancaria = [
            'banco_nombre' => trim((string)($_POST['banco_nombre'] ?? '')),
            'tipo_cuenta' => $tipoCuentaNuevo,
            'titular_cuenta' => trim((string)($_POST['titular_cuenta'] ?? '')),
            'numero_cuenta' => trim((string)($_POST['numero_cuenta'] ?? '')),
            'documento_titular' => trim((string)($_POST['documento_titular'] ?? '')),
            'correo_pagos' => trim((string)($_POST['correo_pagos'] ?? '')),
            'telefono_pagos' => trim((string)($_POST['telefono_pagos'] ?? '')),
        ];

        if ($payloadCuentaBancaria['correo_pagos'] !== '' && filter_var($payloadCuentaBancaria['correo_pagos'], FILTER_VALIDATE_EMAIL) === false) {
            throw new Exception('El correo de pagos no es válido.');
        }

        $dbEmpresa->beginTransaction();

        $slugUpdateQuery = strtolower((string)$dbEmpresa->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite'
            ? 'UPDATE empresas SET slug_tienda = :slug_tienda WHERE id = :empresa_id'
            : 'UPDATE empresas SET slug_tienda = :slug_tienda WHERE id = :empresa_id LIMIT 1';
        $stmtSlug = $dbEmpresa->prepare($slugUpdateQuery);
        $stmtSlug->bindValue(':slug_tienda', $slugTiendaNuevo, PDO::PARAM_STR);
        $stmtSlug->bindValue(':empresa_id', $empresaIdObjetivoFinanzas, PDO::PARAM_INT);
        $stmtSlug->execute();

        $stmtCuentaActual = $dbEmpresa->prepare('SELECT id FROM empresa_cuentas_bancarias WHERE empresa_id = :empresa_id LIMIT 1');
    $stmtCuentaActual->bindValue(':empresa_id', $empresaIdObjetivoFinanzas, PDO::PARAM_INT);
        $stmtCuentaActual->execute();
        $cuentaActualId = (int)($stmtCuentaActual->fetchColumn() ?: 0);

        if ($cuentaActualId > 0) {
            $stmtCuenta = $dbEmpresa->prepare('UPDATE empresa_cuentas_bancarias
                SET banco_nombre = :banco_nombre,
                    tipo_cuenta = :tipo_cuenta,
                    titular_cuenta = :titular_cuenta,
                    numero_cuenta = :numero_cuenta,
                    documento_titular = :documento_titular,
                    correo_pagos = :correo_pagos,
                    telefono_pagos = :telefono_pagos,
                    estado = 1,
                    updated_at = NOW()
                WHERE id = :id LIMIT 1');
            $stmtCuenta->bindValue(':id', $cuentaActualId, PDO::PARAM_INT);
        } else {
            $stmtCuenta = $dbEmpresa->prepare('INSERT INTO empresa_cuentas_bancarias
                (empresa_id, banco_nombre, tipo_cuenta, titular_cuenta, numero_cuenta, documento_titular, correo_pagos, telefono_pagos, estado, created_at, updated_at)
                VALUES
                (:empresa_id, :banco_nombre, :tipo_cuenta, :titular_cuenta, :numero_cuenta, :documento_titular, :correo_pagos, :telefono_pagos, 1, NOW(), NOW())');
            $stmtCuenta->bindValue(':empresa_id', $empresaIdObjetivoFinanzas, PDO::PARAM_INT);
        }

        foreach ($payloadCuentaBancaria as $campoCuenta => $valorCuenta) {
            $stmtCuenta->bindValue(':' . $campoCuenta, $valorCuenta, PDO::PARAM_STR);
        }
        $stmtCuenta->execute();
        $dbEmpresa->commit();

        $_SESSION['empresa_store_slug'] = $slugTiendaNuevo;

        echo json_encode([
            'success' => true,
            'message' => 'Datos bancarios guardados correctamente.',
            'empresa_slug_tienda' => $slugTiendaNuevo,
            'empresa_store_url' => currentStoreBaseUrl($slugTiendaNuevo),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        if (isset($dbEmpresa) && $dbEmpresa instanceof PDO && $dbEmpresa->inTransaction()) {
            $dbEmpresa->rollBack();
        }

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_credenciales_wompi_empresa') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        if (!$puedeGestionEmpresaFinanzas) {
            throw new Exception('Las credenciales Wompi solo se pueden configurar dentro de una empresa.');
        }

        $empresaIdObjetivoFinanzas = $empresaIdFinanzasContexto > 0 ? $empresaIdFinanzasContexto : $empresaId;
        if ($empresaIdObjetivoFinanzas <= 0) {
            throw new Exception('No se detectó una empresa para guardar las credenciales Wompi.');
        }

        $dbEmpresa = Database::connect();
        if (!$tablaExisteDashboard($dbEmpresa, 'empresa_pasarelas_pago')) {
            throw new Exception('La tabla empresa_pasarelas_pago no existe. Ejecuta la migración correspondiente.');
        }

        $payloadWompi = [
            'public_key' => trim((string)($_POST['wompi_public_key'] ?? '')),
            'private_key' => trim((string)($_POST['wompi_private_key'] ?? '')),
            'integrity_secret' => trim((string)($_POST['wompi_integrity_secret'] ?? '')),
            'events_secret' => trim((string)($_POST['wompi_events_secret'] ?? '')),
        ];

        $dbEmpresa->beginTransaction();

        $stmtPasarelaActual = $dbEmpresa->prepare('SELECT id, public_key, private_key, integrity_secret, events_secret FROM empresa_pasarelas_pago WHERE empresa_id = :empresa_id AND pasarela = :pasarela LIMIT 1');
        $stmtPasarelaActual->bindValue(':empresa_id', $empresaIdObjetivoFinanzas, PDO::PARAM_INT);
        $stmtPasarelaActual->bindValue(':pasarela', 'wompi', PDO::PARAM_STR);
        $stmtPasarelaActual->execute();
        $pasarelaActual = $stmtPasarelaActual->fetch(PDO::FETCH_ASSOC) ?: [];
        $pasarelaActualId = (int)($pasarelaActual['id'] ?? 0);

        if ($payloadWompi['public_key'] === '') {
            $payloadWompi['public_key'] = trim((string)($pasarelaActual['public_key'] ?? ''));
        }
        if ($payloadWompi['public_key'] === '') {
            throw new Exception('Debes registrar la Public Key de Wompi.');
        }

        if ($payloadWompi['private_key'] === '') {
            $payloadWompi['private_key'] = trim((string)($pasarelaActual['private_key'] ?? ''));
        } else {
            $payloadWompi['private_key'] = encryptTenantSecret($payloadWompi['private_key']);
        }

        if ($payloadWompi['integrity_secret'] === '') {
            $payloadWompi['integrity_secret'] = trim((string)($pasarelaActual['integrity_secret'] ?? ''));
        } else {
            $payloadWompi['integrity_secret'] = encryptTenantSecret($payloadWompi['integrity_secret']);
        }

        if ($payloadWompi['events_secret'] === '') {
            $payloadWompi['events_secret'] = trim((string)($pasarelaActual['events_secret'] ?? ''));
        } else {
            $payloadWompi['events_secret'] = encryptTenantSecret($payloadWompi['events_secret']);
        }

        if ($payloadWompi['private_key'] === '' || $payloadWompi['integrity_secret'] === '') {
            throw new Exception('Debes completar al menos la llave privada y la clave de integridad de Wompi.');
        }

        if ($pasarelaActualId > 0) {
            $stmtPasarela = $dbEmpresa->prepare('UPDATE empresa_pasarelas_pago
                SET public_key = :public_key,
                    private_key = :private_key,
                    integrity_secret = :integrity_secret,
                    events_secret = :events_secret,
                    estado = 1,
                    updated_at = NOW()
                WHERE id = :id LIMIT 1');
            $stmtPasarela->bindValue(':id', $pasarelaActualId, PDO::PARAM_INT);
        } else {
            $stmtPasarela = $dbEmpresa->prepare('INSERT INTO empresa_pasarelas_pago
                (empresa_id, pasarela, public_key, private_key, integrity_secret, events_secret, estado, created_at, updated_at)
                VALUES
                (:empresa_id, :pasarela, :public_key, :private_key, :integrity_secret, :events_secret, 1, NOW(), NOW())');
            $stmtPasarela->bindValue(':empresa_id', $empresaIdObjetivoFinanzas, PDO::PARAM_INT);
            $stmtPasarela->bindValue(':pasarela', 'wompi', PDO::PARAM_STR);
        }

        foreach ($payloadWompi as $campoWompi => $valorWompi) {
            $stmtPasarela->bindValue(':' . $campoWompi, $valorWompi, PDO::PARAM_STR);
        }
        $stmtPasarela->execute();
        $dbEmpresa->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Credenciales Wompi guardadas correctamente.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        if (isset($dbEmpresa) && $dbEmpresa instanceof PDO && $dbEmpresa->inTransaction()) {
            $dbEmpresa->rollBack();
        }

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_colores_empresa') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $coloresGuardar = [];
        foreach ($coloresEmpresaDefault as $clave => $defecto) {
            if (isset($_POST[$clave])) {
                $coloresGuardar[$clave] = $normalizarColorHexDashboard($_POST[$clave], $defecto);
            } else {
                $coloresGuardar[$clave] = $defecto;
            }
        }

        if ($esSuperAdminGlobalSesion && !$esSuperAdminModoEmpresa) {
            if (!$usuarioId) {
                throw new Exception('No se detecto usuario en sesion.');
            }

            $dbGlobalColores = Database::connect();
            $guardarColoresSuperAdminGlobalDbDashboard($dbGlobalColores, (int)$usuarioId, $coloresGuardar);

            $perfilActual = $cargarPerfilSuperAdminDashboard($rutaPerfilSuperAdminDashboard);
            $perfilActual['colores'] = $coloresGuardar;
            $perfilActual['updated_at'] = date('c');

            $jsonPerfil = json_encode($perfilActual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (is_string($jsonPerfil) && $jsonPerfil !== '') {
                @file_put_contents($rutaPerfilSuperAdminDashboard, $jsonPerfil, LOCK_EX);
            }

            $_SESSION['colores_empresa'] = $coloresGuardar;
            echo json_encode([
                'success' => true,
                'message' => 'Colores de perfil global guardados correctamente.',
                'colores' => $coloresGuardar,
                'logo_empresa_url' => '',
                'intentos_usados' => null,
                'tope_intentos' => null,
                'intentos_restantes' => null,
                'sin_restriccion' => true,
            ]);
            exit;
        }

        if ($empresaId <= 0 && $usuarioId) {
            $dbCtx = Database::connect();
            $stmtCtx = $dbCtx->prepare("SELECT empresa_id FROM usuarios WHERE id = :usuario_id LIMIT 1");
            $stmtCtx->bindValue(':usuario_id', (int)$usuarioId, PDO::PARAM_INT);
            $stmtCtx->execute();
            $empresaFallback = (int)($stmtCtx->fetchColumn() ?: 0);

            if ($empresaFallback <= 0) {
                $rolSesionCtx = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
                $rolSesionCtx = strtr($rolSesionCtx, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
                $esSuperAdminCtx = ($rolSesionCtx === 'super administrador');
                if ($esSuperAdminCtx) {
                    $stmtEmpresa = $dbCtx->query("SELECT id, nombre FROM empresas ORDER BY id ASC LIMIT 1");
                    $empresaRow = $stmtEmpresa ? $stmtEmpresa->fetch(PDO::FETCH_ASSOC) : null;
                    if ($empresaRow) {
                        $empresaFallback = (int)($empresaRow['id'] ?? 0);
                        $_SESSION['empresa_nombre'] = trim((string)($empresaRow['nombre'] ?? 'Empresa'));
                    }
                }
            }

            if ($empresaFallback > 0) {
                $empresaId = $empresaFallback;
                $_SESSION['empresa_id'] = $empresaFallback;
            }
        }

        if (!$puedeGestionEmpresaPerfil) {
            throw new Exception('No tienes permisos para editar colores de empresa.');
        }

        if ($empresaId <= 0 || !$usuarioId) {
            throw new Exception('No se detecto empresa o usuario en sesion.');
        }

        $dbColores = Database::connect();
        if (!$tablaColoresEmpresaExiste($dbColores)) {
            throw new Exception('La tabla colores_empresa no existe. Ejecuta la migracion correspondiente.');
        }

        $sinRestriccionIntentosColores = $esSuperAdminSinRestriccionColores;

        // ── Verificar sistema de intentos ─────────────────────
        $tieneIntentos = $columnaExisteEnTablaDashboard($dbColores, 'colores_empresa', 'intentos_usados')
                      && $columnaExisteEnTablaDashboard($dbColores, 'colores_empresa', 'tope_intentos');
        $stmtIntExiste = $dbColores->prepare("SELECT id, intentos_usados, tope_intentos FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1");
        $stmtIntExiste->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmtIntExiste->execute();
        $filaIntentos = $stmtIntExiste->fetch(PDO::FETCH_ASSOC);
        $intentosUsados  = 0;
        $topeIntentos    = 3;
        $idColorEmpresa  = 0;
        if ($filaIntentos) {
            $idColorEmpresa = (int)($filaIntentos['id'] ?? 0);
            if ($tieneIntentos) {
                $intentosUsados = max(0, min($paqueteIntentosColoresDashboard, (int)($filaIntentos['intentos_usados'] ?? 0)));
                $topeIntentos   = $paqueteIntentosColoresDashboard;
            }
        }

        if (!$sinRestriccionIntentosColores && $tieneIntentos && $intentosUsados >= $topeIntentos) {
            $reactivadoPorPago = $aplicarPagoAprobadoPendienteIntentosDashboard($dbColores, $empresaId, (int)$usuarioId);
            if ($reactivadoPorPago) {
                $stmtIntExiste->execute();
                $filaIntentos = $stmtIntExiste->fetch(PDO::FETCH_ASSOC);
                if ($filaIntentos) {
                    $idColorEmpresa = (int)($filaIntentos['id'] ?? 0);
                    $intentosUsados = max(0, min($paqueteIntentosColoresDashboard, (int)($filaIntentos['intentos_usados'] ?? 0)));
                    $topeIntentos   = $paqueteIntentosColoresDashboard;
                }
            }
        }

        if (!$sinRestriccionIntentosColores && $tieneIntentos && $intentosUsados >= $topeIntentos) {
            echo json_encode([
                'success'            => false,
                'limite_intentos'    => true,
                'message'            => 'Has alcanzado el limite de ' . $topeIntentos . ' cambios de colores. Debes pagar para reactivar 3 intentos.',
                'intentos_usados'    => $intentosUsados,
                'tope_intentos'      => $topeIntentos,
                'intentos_restantes' => 0,
            ]);
            exit;
        }


        $hayArchivoLogo = isset($_FILES['logo_empresa_archivo'])
            && is_array($_FILES['logo_empresa_archivo'])
            && (int)($_FILES['logo_empresa_archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        $logoAnteriorColores = '';
        if ($hayArchivoLogo) {
            $stmtLogoActual = $dbColores->prepare("SELECT imagen FROM empresas WHERE id = :empresa_id LIMIT 1");
            $stmtLogoActual->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmtLogoActual->execute();
            $logoAnteriorColores = trim((string)($stmtLogoActual->fetchColumn() ?: ''));
        }

        $logoEmpresaRutaGuardar = null;
        $logoEmpresaUrlRespuesta = '';
        if ($hayArchivoLogo) {
            if (!$columnaExisteEnTablaDashboard($dbColores, 'empresas', 'imagen')) {
                throw new Exception('La columna empresas.imagen no existe. Ejecuta la migracion de imagenes de empresa.');
            }

            $logoEmpresaRutaGuardar = $procesarLogoEmpresaDashboard('logo_empresa_archivo', $empresaId);
            if ($logoEmpresaRutaGuardar !== null) {
                $logoEmpresaUrlRespuesta = $normalizarUrlImagenEmpresaDashboard($logoEmpresaRutaGuardar);
            }
        }

        // Excluir columnas que no existen en la BD
        $coloresAGuardar = [];
        foreach ($coloresGuardar as $clave => $valor) {
            $columnasBase = ['color_principal', 'color_secundario', 'color_menu_lateral', 'color_botones', 'color_fondo'];
            if (in_array($clave, $columnasBase, true) || $columnaExisteEnTablaDashboard($dbColores, 'colores_empresa', $clave)) {
                $coloresAGuardar[$clave] = $valor;
            }
        }

        $dbColores->beginTransaction();

        if ($idColorEmpresa > 0) {
            // UPDATE dinámico
            $setParts = ['usuario_id = :usuario_id', 'updated_at = NOW()'];
            foreach ($coloresAGuardar as $clave => $valor) {
                $setParts[] = "$clave = :$clave";
            }
            if ($tieneIntentos && !$sinRestriccionIntentosColores) {
                $setParts[] = 'intentos_usados = intentos_usados + 1';
                $setParts[] = 'tope_intentos = :tope_intentos';
            }
            $stmtUpdate = $dbColores->prepare("UPDATE colores_empresa SET " . implode(', ', $setParts) . " WHERE id = :id");
            $stmtUpdate->bindValue(':id', $idColorEmpresa, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            if ($tieneIntentos && !$sinRestriccionIntentosColores) {
                $stmtUpdate->bindValue(':tope_intentos', $paqueteIntentosColoresDashboard, PDO::PARAM_INT);
            }
            foreach ($coloresAGuardar as $clave => $valor) {
                $stmtUpdate->bindValue(":$clave", $valor, PDO::PARAM_STR);
            }
            $stmtUpdate->execute();
        } else {
            // INSERT dinámico
            $colsInsert = array_merge(['empresa_id', 'usuario_id'], array_keys($coloresAGuardar));
            $valsInsert = array_merge([':empresa_id', ':usuario_id'], array_map(fn($k) => ":$k", array_keys($coloresAGuardar)));
            if ($tieneIntentos && !$sinRestriccionIntentosColores) {
                $colsInsert[] = 'tope_intentos';
                $valsInsert[] = ':tope_intentos';
            }
            $sqlInsert = "INSERT INTO colores_empresa (" . implode(', ', $colsInsert) . ", created_at, updated_at) VALUES (" . implode(', ', $valsInsert) . ", NOW(), NOW())";
            $stmtInsert = $dbColores->prepare($sqlInsert);
            $stmtInsert->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmtInsert->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            if ($tieneIntentos && !$sinRestriccionIntentosColores) {
                $stmtInsert->bindValue(':tope_intentos', $paqueteIntentosColoresDashboard, PDO::PARAM_INT);
            }
            foreach ($coloresAGuardar as $clave => $valor) {
                $stmtInsert->bindValue(":$clave", $valor, PDO::PARAM_STR);
            }
            $stmtInsert->execute();
            if ($tieneIntentos && !$sinRestriccionIntentosColores) {
                $newId = (int)$dbColores->lastInsertId();
                $dbColores->prepare("UPDATE colores_empresa SET intentos_usados = 1 WHERE id = :id")->execute([':id' => $newId]);
            }
        }

        if ($logoEmpresaRutaGuardar !== null) {
            $logoUpdateQuery = strtolower((string)$dbColores->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite'
                ? "UPDATE empresas SET imagen = :imagen WHERE id = :empresa_id"
                : "UPDATE empresas SET imagen = :imagen WHERE id = :empresa_id LIMIT 1";
            $stmtEmpresaLogo = $dbColores->prepare($logoUpdateQuery);
            $stmtEmpresaLogo->bindValue(':imagen', $logoEmpresaRutaGuardar, PDO::PARAM_STR);
            $stmtEmpresaLogo->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmtEmpresaLogo->execute();
        }

        $dbColores->commit();
        if ($logoEmpresaRutaGuardar !== null && $logoAnteriorColores !== '' && $logoAnteriorColores !== $logoEmpresaRutaGuardar) {
            eliminarArchivoProyectoSiExiste($logoAnteriorColores);
        }
        $_SESSION['colores_empresa'] = $coloresGuardar;

        // Calcular intentos restantes actualizados
        $intentosUsadosNuevos = $sinRestriccionIntentosColores
            ? 0
            : min($paqueteIntentosColoresDashboard, $intentosUsados + 1);
        $intentosRestantes = $sinRestriccionIntentosColores
            ? null
            : max(0, $paqueteIntentosColoresDashboard - $intentosUsadosNuevos);

        echo json_encode([
            'success'            => true,
            'message'            => 'Colores de empresa guardados correctamente.',
            'colores'            => $coloresGuardar,
            'logo_empresa_url'   => $logoEmpresaUrlRespuesta,
            'intentos_usados'    => ($tieneIntentos && !$sinRestriccionIntentosColores) ? $intentosUsadosNuevos : null,
            'tope_intentos'      => ($tieneIntentos && !$sinRestriccionIntentosColores) ? $paqueteIntentosColoresDashboard : null,
            'intentos_restantes' => ($tieneIntentos && !$sinRestriccionIntentosColores) ? $intentosRestantes : null,
            'sin_restriccion'    => $sinRestriccionIntentosColores,
        ]);
        exit;
    } catch (Throwable $e) {
        if (isset($dbColores) && $dbColores instanceof PDO && $dbColores->inTransaction()) {
            $dbColores->rollBack();
        }

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
        exit;
    }
}

$coloresEmpresa = $coloresEmpresaDefault;

if ($esSuperAdminGlobalSesion && !$esSuperAdminModoEmpresa) {
    $coloresSuperAdminDb = null;
    try {
        $dbColoresSuperAdmin = Database::connect();
        $coloresSuperAdminDb = $cargarColoresSuperAdminGlobalDbDashboard($dbColoresSuperAdmin, (int)$usuarioId);
    } catch (Throwable $e) {
        error_log('Error al inicializar colores globales del superadmin desde BD: ' . $e->getMessage());
    }

    if (is_array($coloresSuperAdminDb)) {
        $coloresEmpresa = $coloresSuperAdminDb;
    } else {
        $perfilSuperAdminColores = $cargarPerfilSuperAdminDashboard($rutaPerfilSuperAdminDashboard);
        $coloresEmpresa = $normalizarColoresPerfilSuperAdminDashboard($perfilSuperAdminColores);
    }
    $_SESSION['colores_empresa'] = $coloresEmpresa;
}

if (isset($_SESSION['colores_empresa']) && is_array($_SESSION['colores_empresa'])) {
    foreach ($coloresEmpresa as $clave => $defecto) {
        $coloresEmpresa[$clave] = $normalizarColorHexDashboard($_SESSION['colores_empresa'][$clave] ?? $defecto, $defecto);
    }
}

try {
    if ($usuarioId) {
        $db = Database::connect();
        $db->exec("SET time_zone = '-05:00'");
        $clienteCorreoSesionDashboard = strtolower(trim((string)($_SESSION['correo'] ?? ($userData['correo'] ?? ($userData['email'] ?? '')))));

        if ($empresaId > 0) {
            $coloresEmpresa = $cargarColoresEmpresa($db, $empresaId);
            $_SESSION['colores_empresa'] = $coloresEmpresa;

            if ($debeConciliarIntentosDashboard) {
                try {
                    if (!isset($_SESSION['dashboard_intentos_conciliados']) || !is_array($_SESSION['dashboard_intentos_conciliados'])) {
                        $_SESSION['dashboard_intentos_conciliados'] = [];
                    }
                    $aplicarPagoAprobadoPendienteIntentosDashboard($db, $empresaId, (int)$usuarioId);
                    $_SESSION['dashboard_intentos_conciliados'][$empresaId] = true;
                } catch (Throwable $e) {
                    // Si falla la conciliacion automatica, no bloquea el render del dashboard.
                }
            }

            // Cargar datos de intentos para mostrar en la UI
            try {
                $tieneIntentosCol = $columnaExisteEnTablaDashboard($db, 'colores_empresa', 'intentos_usados')
                                 && $columnaExisteEnTablaDashboard($db, 'colores_empresa', 'tope_intentos');
                if ($esSuperAdminSinRestriccionColores) {
                    $intentosColoresInfo = ['usados' => 0, 'tope' => $paqueteIntentosColoresDashboard, 'disponible' => false, 'sin_restriccion' => true];
                } elseif ($tieneIntentosCol && $tablaColoresEmpresaExiste($db)) {
                    $stmtI = $db->prepare("SELECT intentos_usados, tope_intentos FROM colores_empresa WHERE empresa_id = :eid ORDER BY id DESC LIMIT 1");
                    $stmtI->bindValue(':eid', $empresaId, PDO::PARAM_INT);
                    $stmtI->execute();
                    $filaI = $stmtI->fetch(PDO::FETCH_ASSOC);
                    $intentosColoresInfo = $filaI
                        ? ['usados' => max(0, min($paqueteIntentosColoresDashboard, (int)$filaI['intentos_usados'])), 'tope' => $paqueteIntentosColoresDashboard, 'disponible' => true, 'sin_restriccion' => false]
                        : ['usados' => 0, 'tope' => $paqueteIntentosColoresDashboard, 'disponible' => true, 'sin_restriccion' => false];
                } else {
                    $intentosColoresInfo = ['usados' => 0, 'tope' => $paqueteIntentosColoresDashboard, 'disponible' => false, 'sin_restriccion' => false];
                }
            } catch (Throwable $e) {
                $intentosColoresInfo = ['usados' => 0, 'tope' => $paqueteIntentosColoresDashboard, 'disponible' => false, 'sin_restriccion' => $esSuperAdminSinRestriccionColores];
            }
        }

        $usuarioModel = new Usuario($db);
        $usuarioDb = $usuarioModel->obtenerUsuarioPorId($usuarioId);

        if (!empty($usuarioDb)) {
            $nombreCompleto = trim(($usuarioDb['nombre'] ?? '') . ' ' . ($usuarioDb['apellidos'] ?? ''));
            $nombreCompleto = $nombreCompleto !== '' ? $nombreCompleto : $nombreCompleto;
            $correo = $usuarioDb['correo'] ?? '';
            $telefono = $usuarioDb['telefono'] ?? '';
            $rolNombre = $usuarioDb['rol'] ?? $rolNombre;
            $documento = $usuarioDb['documento'] ?? '';
        }

        if ($empresaId > 0) {
            $empresaColumnasPerfil = [];
            if ($columnaExisteEnTablaDashboard($db, 'empresas', 'imagen')) {
                $empresaColumnasPerfil[] = 'imagen';
            }

            foreach (['direccion', 'telefono', 'correo_electronico', 'email', 'sobre_nosotros'] as $empresaDatoColumna) {
                if ($columnaExisteEnTablaDashboard($db, 'empresas', $empresaDatoColumna)) {
                    $empresaColumnasPerfil[] = $empresaDatoColumna;
                }
            }

            if ($columnaExisteEnTablaDashboard($db, 'empresas', 'slug_tienda')) {
                $empresaColumnasPerfil[] = 'slug_tienda';
            }

            foreach (array_keys($empresaRedesSocialesDef) as $empresaRedSocialColumna) {
                if ($columnaExisteEnTablaDashboard($db, 'empresas', $empresaRedSocialColumna)) {
                    $empresaColumnasPerfil[] = $empresaRedSocialColumna;
                }
            }

            if (!empty($empresaColumnasPerfil)) {
                $stmtImagenEmpresa = $db->prepare("SELECT " . implode(', ', $empresaColumnasPerfil) . " FROM empresas WHERE id = :empresa_id LIMIT 1");
                $stmtImagenEmpresa->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                $stmtImagenEmpresa->execute();
                $empresaPerfilDb = $stmtImagenEmpresa->fetch(PDO::FETCH_ASSOC) ?: [];
                $empresaImagenRaw = trim((string)($empresaPerfilDb['imagen'] ?? ''));
                if ($empresaImagenRaw !== '') {
                    $empresaImagenUrl = $normalizarUrlImagenEmpresaDashboard($empresaImagenRaw);
                }

                $empresaDireccion = trim((string)($empresaPerfilDb['direccion'] ?? $empresaDireccion));
                $empresaTelefonoContacto = trim((string)($empresaPerfilDb['telefono'] ?? $empresaTelefonoContacto));
                $empresaCorreoContacto = trim((string)($empresaPerfilDb['correo_electronico'] ?? ($empresaPerfilDb['email'] ?? $empresaCorreoContacto)));
                $empresaSobreNosotros = trim((string)($empresaPerfilDb['sobre_nosotros'] ?? $empresaSobreNosotros));
                $empresaStoreSlug = trim((string)($empresaPerfilDb['slug_tienda'] ?? $empresaStoreSlug));

                foreach (array_keys($empresaRedesSocialesDef) as $empresaRedSocialColumna) {
                    $empresaRedesSociales[$empresaRedSocialColumna] = trim((string)($empresaPerfilDb[$empresaRedSocialColumna] ?? $empresaRedesSociales[$empresaRedSocialColumna] ?? ''));
                }
                $_SESSION['empresa_redes_sociales'] = $empresaRedesSociales;
            }

            if ($debeCargarDatosFinancierosInicial) {
                if ($tablaExisteDashboard($db, 'empresa_cuentas_bancarias')) {
                    $cuentaColumnas = ['empresa_id'];
                    foreach (array_keys($empresaCuentaBancaria) as $cuentaColumna) {
                        if ($columnaExisteEnTablaDashboard($db, 'empresa_cuentas_bancarias', $cuentaColumna)) {
                            $cuentaColumnas[] = $cuentaColumna;
                        }
                    }

                    if (!empty($cuentaColumnas)) {
                        $stmtCuentaEmpresa = $db->prepare('SELECT ' . implode(', ', $cuentaColumnas) . ' FROM empresa_cuentas_bancarias WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1');
                        $stmtCuentaEmpresa->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                        $stmtCuentaEmpresa->execute();
                        $cuentaEmpresaDb = $stmtCuentaEmpresa->fetch(PDO::FETCH_ASSOC) ?: [];

                        foreach (array_keys($empresaCuentaBancaria) as $cuentaCampo) {
                            if (array_key_exists($cuentaCampo, $cuentaEmpresaDb)) {
                                $empresaCuentaBancaria[$cuentaCampo] = trim((string)$cuentaEmpresaDb[$cuentaCampo]);
                            }
                        }
                    }
                }

                if ($tablaExisteDashboard($db, 'empresa_pasarelas_pago')) {
                    $pasarelaColumnas = ['empresa_id', 'pasarela'];
                    foreach (['public_key', 'private_key', 'integrity_secret', 'events_secret'] as $pasarelaColumna) {
                        if ($columnaExisteEnTablaDashboard($db, 'empresa_pasarelas_pago', $pasarelaColumna)) {
                            $pasarelaColumnas[] = $pasarelaColumna;
                        }
                    }

                    if (!empty($pasarelaColumnas)) {
                        $stmtPasarelaEmpresa = $db->prepare('SELECT ' . implode(', ', $pasarelaColumnas) . ' FROM empresa_pasarelas_pago WHERE empresa_id = :empresa_id AND pasarela = :pasarela ORDER BY id DESC LIMIT 1');
                        $stmtPasarelaEmpresa->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                        $stmtPasarelaEmpresa->bindValue(':pasarela', 'wompi', PDO::PARAM_STR);
                        $stmtPasarelaEmpresa->execute();
                        $pasarelaEmpresaDb = $stmtPasarelaEmpresa->fetch(PDO::FETCH_ASSOC) ?: [];

                        $empresaCredencialesWompi['public_key'] = trim((string)($pasarelaEmpresaDb['public_key'] ?? ''));
                        $empresaCredencialesWompi['has_private_key'] = trim((string)($pasarelaEmpresaDb['private_key'] ?? '')) !== '';
                        $empresaCredencialesWompi['has_integrity_secret'] = trim((string)($pasarelaEmpresaDb['integrity_secret'] ?? '')) !== '';
                        $empresaCredencialesWompi['has_events_secret'] = trim((string)($pasarelaEmpresaDb['events_secret'] ?? '')) !== '';
                    }
                }
            }
        }

        if ($empresaIdFinanzasContexto > 0 && $empresaIdFinanzasContexto !== $empresaId) {
            if ($columnaExisteEnTablaDashboard($db, 'empresas', 'slug_tienda')) {
                $stmtSlugFinanzas = $db->prepare('SELECT slug_tienda FROM empresas WHERE id = :empresa_id LIMIT 1');
                $stmtSlugFinanzas->bindValue(':empresa_id', $empresaIdFinanzasContexto, PDO::PARAM_INT);
                $stmtSlugFinanzas->execute();
                $empresaStoreSlug = trim((string)($stmtSlugFinanzas->fetchColumn() ?: $empresaStoreSlug));
            }

            if ($tablaExisteDashboard($db, 'empresa_cuentas_bancarias')) {
                $cuentaColumnasFinanzas = ['empresa_id'];
                foreach (array_keys($empresaCuentaBancaria) as $cuentaColumnaFinanzas) {
                    if ($columnaExisteEnTablaDashboard($db, 'empresa_cuentas_bancarias', $cuentaColumnaFinanzas)) {
                        $cuentaColumnasFinanzas[] = $cuentaColumnaFinanzas;
                    }
                }

                if (!empty($cuentaColumnasFinanzas)) {
                    $stmtCuentaFinanzas = $db->prepare('SELECT ' . implode(', ', $cuentaColumnasFinanzas) . ' FROM empresa_cuentas_bancarias WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1');
                    $stmtCuentaFinanzas->bindValue(':empresa_id', $empresaIdFinanzasContexto, PDO::PARAM_INT);
                    $stmtCuentaFinanzas->execute();
                    $cuentaFinanzasDb = $stmtCuentaFinanzas->fetch(PDO::FETCH_ASSOC) ?: [];

                    foreach (array_keys($empresaCuentaBancaria) as $cuentaCampoFinanzas) {
                        if (array_key_exists($cuentaCampoFinanzas, $cuentaFinanzasDb)) {
                            $empresaCuentaBancaria[$cuentaCampoFinanzas] = trim((string)$cuentaFinanzasDb[$cuentaCampoFinanzas]);
                        }
                    }
                }
            }

            if ($tablaExisteDashboard($db, 'empresa_pasarelas_pago')) {
                $pasarelaColumnasFinanzas = ['empresa_id', 'pasarela'];
                foreach (['public_key', 'private_key', 'integrity_secret', 'events_secret'] as $pasarelaColumnaFinanzas) {
                    if ($columnaExisteEnTablaDashboard($db, 'empresa_pasarelas_pago', $pasarelaColumnaFinanzas)) {
                        $pasarelaColumnasFinanzas[] = $pasarelaColumnaFinanzas;
                    }
                }

                if (!empty($pasarelaColumnasFinanzas)) {
                    $stmtPasarelaFinanzas = $db->prepare('SELECT ' . implode(', ', $pasarelaColumnasFinanzas) . ' FROM empresa_pasarelas_pago WHERE empresa_id = :empresa_id AND pasarela = :pasarela ORDER BY id DESC LIMIT 1');
                    $stmtPasarelaFinanzas->bindValue(':empresa_id', $empresaIdFinanzasContexto, PDO::PARAM_INT);
                    $stmtPasarelaFinanzas->bindValue(':pasarela', 'wompi', PDO::PARAM_STR);
                    $stmtPasarelaFinanzas->execute();
                    $pasarelaFinanzasDb = $stmtPasarelaFinanzas->fetch(PDO::FETCH_ASSOC) ?: [];

                    $empresaCredencialesWompi['public_key'] = trim((string)($pasarelaFinanzasDb['public_key'] ?? $empresaCredencialesWompi['public_key']));
                    $empresaCredencialesWompi['has_private_key'] = trim((string)($pasarelaFinanzasDb['private_key'] ?? '')) !== '';
                    $empresaCredencialesWompi['has_integrity_secret'] = trim((string)($pasarelaFinanzasDb['integrity_secret'] ?? '')) !== '';
                    $empresaCredencialesWompi['has_events_secret'] = trim((string)($pasarelaFinanzasDb['events_secret'] ?? '')) !== '';
                }
            }
        }

        $superAdminModoEmpresa = !empty($_SESSION['superadmin_modo_empresa']);
        if ($empresaId > 0 && (!$esSuperAdminGlobalSesion || $superAdminModoEmpresa)) {
            $colTipoEmpresa = $columnaExisteEnTablaDashboard($db, 'empresas', 'tipo_empresa_id') ? 'tipo_empresa_id' : 'id_tipos_empresa';

            $sqlTipoEmpresa = "SELECT te.nombre
                               FROM empresas e
                               LEFT JOIN tipos_empresa te ON te.id = e.{$colTipoEmpresa}
                               WHERE e.id = :empresa_id
                               LIMIT 1";
            $stmtTipoEmpresa = $db->prepare($sqlTipoEmpresa);
            $stmtTipoEmpresa->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            $stmtTipoEmpresa->execute();
            $tipoEmpresaNombreDb = trim((string)($stmtTipoEmpresa->fetchColumn() ?: ''));
            if ($tipoEmpresaNombreDb !== '') {
                $tipoEmpresaNombre = $tipoEmpresaNombreDb;
            }

        } elseif ($esSuperAdminGlobalSesion) {
            $tipoEmpresaNombre = 'ACCESO GLOBAL';
            $perfilSuperAdminVista = $cargarPerfilSuperAdminDashboard($rutaPerfilSuperAdminDashboard);
            $empresaDireccion = trim((string)($perfilSuperAdminVista['direccion'] ?? $empresaDireccion));
            $empresaTelefonoContacto = trim((string)($perfilSuperAdminVista['telefono'] ?? $empresaTelefonoContacto));
            $empresaCorreoContacto = trim((string)($perfilSuperAdminVista['correo'] ?? $empresaCorreoContacto));
            $empresaSobreNosotros = trim((string)($perfilSuperAdminVista['sobre_nosotros'] ?? ''));
            if (!empty($perfilSuperAdminVista['redes_sociales']) && is_array($perfilSuperAdminVista['redes_sociales'])) {
                foreach (array_keys($empresaRedesSocialesDef) as $empresaRedSocialColumna) {
                    $empresaRedesSociales[$empresaRedSocialColumna] = trim((string)($perfilSuperAdminVista['redes_sociales'][$empresaRedSocialColumna] ?? $empresaRedesSociales[$empresaRedSocialColumna] ?? ''));
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error al cargar datos del usuario: " . $e->getMessage());
}

if ($nombreCompleto === 'Usuario') {
    $nombreCompleto = trim(($userData['nombres'] ?? '') . ' ' . ($userData['apellidos'] ?? '')) ?: 'Usuario';
    $correo = $correo ?: ($userData['email'] ?? ($userData['correo'] ?? ''));
    $telefono = $telefono ?: ($userData['telefono'] ?? '');
    $rolNombre = $rolNombre ?: ($userData['nombrerol'] ?? '');
}

$documento = $documento ?: ($_SESSION['documento'] ?? '');
$empresaTelefonoContacto = $empresaTelefonoContacto !== '' ? $empresaTelefonoContacto : ($telefono ?: '');
$empresaCorreoContacto = $empresaCorreoContacto !== '' ? $empresaCorreoContacto : ($correo ?: '');
$empresaDireccion = $empresaDireccion !== '' ? $empresaDireccion : 'Colombia';
$empresaSobreNosotros = DESCRIPCION;
if ($empresaId > 0) {

}
$scriptNameDashboard = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDirDashboard = rtrim(str_replace('\\', '/', dirname($scriptNameDashboard)), '/');
$baseWebDashboard = preg_match('#/Views$#i', $scriptDirDashboard) === 1
    ? substr($scriptDirDashboard, 0, -6)
    : $scriptDirDashboard;
$diagnosticoEndpoint = ($baseWebDashboard === '' ? '' : $baseWebDashboard) . '/diagnostico_carrito_pago.php';

$rolNombreLimpio = trim((string)$rolNombre);
$rolNombreNormalizado = function_exists('mb_strtolower')
    ? mb_strtolower($rolNombreLimpio, 'UTF-8')
    : strtolower($rolNombreLimpio);
$rolNombreNormalizado = preg_replace('/\s+/u', ' ', $rolNombreNormalizado);
$_superAdminReal = $esSuperAdminRolSesion || (preg_match('/\bsuper\b.*\badministrador\b/u', $rolNombreNormalizado) === 1);
// Si el superadmin inicio sesion en modo empresa, se muestra como administrador de esa empresa
$esSuperAdmin = $_superAdminReal && empty($_SESSION['superadmin_modo_empresa']);
$esAdministrador = !$esSuperAdmin && ((preg_match('/\badministrador\b/u', $rolNombreNormalizado) === 1) || (!empty($_SESSION['superadmin_modo_empresa']) && $_superAdminReal));
$esSuperAdminGlobalSinEmpresa = $esSuperAdmin && $empresaId <= 0;
$ocultarSuperAdminEnResumen = $esAdministrador;

if ($esSuperAdminGlobalSinEmpresa) {
    $empresaNombre = 'PERFIL GLOBAL';
    $tipoEmpresaNombre = 'ACCESO GLOBAL';
}

$nombrePerfilVisual = $nombreCompleto;
$rolPerfilVisual = $rolNombre;

function esRolSuperAdminDashboard($rolNombre) {
    $rolLimpio = trim((string)$rolNombre);
    $rolNormalizado = function_exists('mb_strtolower')
        ? mb_strtolower($rolLimpio, 'UTF-8')
        : strtolower($rolLimpio);
    $rolNormalizado = preg_replace('/\s+/u', ' ', $rolNormalizado);

    return preg_match('/\bsuper\b.*\badministrador\b/u', $rolNormalizado) === 1;
}

function tailFileLines(string $filePath, int $lineCount = 1200): array {
    if (!is_file($filePath) || !is_readable($filePath)) {
        return [];
    }

    try {
        $file = new SplFileObject($filePath, 'r');
    } catch (Throwable $e) {
        return [];
    }

    $file->seek(PHP_INT_MAX);
    $lastLine = $file->key();
    $startLine = max(0, $lastLine - $lineCount + 1);
    $result = [];

    for ($file->seek($startLine); !$file->eof(); $file->next()) {
        $result[] = rtrim((string)$file->current(), "\r\n");
    }

    return $result;
}

// Actualizar ultima actividad del usuario actual (si el campo existe)
try {
    $db = Database::connect();
    // Verificar si el campo ultima_actividad existe
    $checkField = $db->query("SHOW COLUMNS FROM usuarios LIKE 'ultima_actividad'");
    if ($checkField->rowCount() > 0) {
        $queryUpdate = "UPDATE usuarios SET ultima_actividad = NOW() WHERE id = :usuario_id";
        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->execute([':usuario_id' => $usuarioId]);
    }
} catch (Exception $e) {
    error_log("Error al actualizar ultima actividad: " . $e->getMessage());
}

// Obtener usuarios activos en tiempo real (en linea) - Solo para Admin y Super Admin
$usuariosEnLinea = [];
$totalEnLinea = 0;
$estadisticasUsuarios = [];
$usuariosPorRol = []; // Usuarios en linea agrupados por rol
$mostrarUsuariosEnLinea = ($esAdministrador || $esSuperAdmin);
$errorUsuariosEnLinea = null;
$tieneUltimaActividad = false;
$intervaloEnLinea = 2; // Minutos de actividad reciente para considerar en linea
$mostrarPanelErrores = $_superAdminReal;
$usarDiagnosticoAjax = false;
$erroresSistema = [];
$resumenErroresSistema = [
    'total' => 0,
    'fatal' => 0,
    'warning' => 0,
    'notice' => 0,
    'otros' => 0
];
$fuenteErroresSistema = 'Sin fuente disponible';
$errorLecturaErroresSistema = null;
$ultimoErrorSistema = null;
$erroresPorArchivo = [];
$diagnosticoLocalPayload = [
    'success' => true,
    'eventos' => [
        'scope' => 'active',
        'scope_label' => 'activos',
        'ventana_minutos' => 90,
        'total' => 0,
        'resumen' => [
            'FATAL' => 0,
            'WARNING' => 0,
            'NOTICE' => 0,
            'INFO' => 0,
        ],
        'lista' => [],
    ],
    'resultados' => [
        ['check' => 'Sesion super admin', 'status' => 'OK', 'detail' => 'Autorizado para ver diagnostico local.'],
    ],
    'log_path' => 'No encontrada',
];

function extraerArchivoErrorDashboard($linea) {
    if (preg_match('/\bin\s+([^\s]+\.php)\s+on\s+line\s+(\d+)/i', $linea, $m)) {
        return $m[1] . ':L' . $m[2];
    }

    if (preg_match('/referer:\s+https?:\/\/[^\s]+\/(Views\/[^\s]+\.php)/i', $linea, $m)) {
        return $m[1];
    }

    if (preg_match('/(Controllers\/[^\s]+\.php|Models\/[^\s]+\.php|Views\/[^\s]+\.php)/i', $linea, $m)) {
        return $m[1];
    }

    return 'Archivo no identificado';
}

function obtenerTimestampLogDashboard($linea) {
    if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4}\s+\d{2}:\d{2}:\d{2})\]/', $linea, $mFechaPhp)) {
        $ts = strtotime($mFechaPhp[1]);
        return $ts !== false ? $ts : null;
    }

    if (preg_match('/\[(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s+([A-Za-z]{3})\s+(\d{1,2})\s+(\d{2}:\d{2}:\d{2})(?:\.\d+)?\s+(\d{4})\]/', $linea, $mFechaApache)) {
        $texto = $mFechaApache[3] . ' ' . $mFechaApache[2] . ' ' . $mFechaApache[5] . ' ' . $mFechaApache[4];
        $ts = strtotime($texto);
        return $ts !== false ? $ts : null;
    }

    return null;
}

if ($mostrarUsuariosEnLinea) {
    try {
        $db = Database::connect();
        
        // Verificar si el campo ultima_actividad existe
        $checkField = $db->query("SHOW COLUMNS FROM usuarios LIKE 'ultima_actividad'");
        $tieneUltimaActividad = ($checkField->rowCount() > 0);
        
        if ($tieneUltimaActividad) {
            // Obtener SOLO usuarios con actividad en los ultimos 15 minutos (EN LINEA)
            if ($esSuperAdminGlobalSinEmpresa) {
                $query = "SELECT nombre, apellidos, correo, rol, ultima_actividad,
                          TIMESTAMPDIFF(MINUTE, ultima_actividad, NOW()) as minutos_inactivo
                          FROM usuarios
                          WHERE estado = 1
                          AND id = :usuario_id
                          AND ultima_actividad IS NOT NULL
                          AND ultima_actividad <= NOW()
                          AND ultima_actividad >= DATE_SUB(NOW(), INTERVAL {$intervaloEnLinea} MINUTE)
                          ORDER BY ultima_actividad DESC";
            } elseif ($esSuperAdmin) {
                $query = "SELECT nombre, apellidos, correo, rol, ultima_actividad,
                          TIMESTAMPDIFF(MINUTE, ultima_actividad, NOW()) as minutos_inactivo
                          FROM usuarios 
                          WHERE estado = 1 
                          AND ultima_actividad IS NOT NULL
                          AND ultima_actividad <= NOW()
                          AND ultima_actividad >= DATE_SUB(NOW(), INTERVAL {$intervaloEnLinea} MINUTE)
                          ORDER BY ultima_actividad DESC";
            } else {
                $query = "SELECT nombre, apellidos, correo, rol, ultima_actividad,
                          TIMESTAMPDIFF(MINUTE, ultima_actividad, NOW()) as minutos_inactivo
                          FROM usuarios 
                          WHERE estado = 1 
                          AND empresa_id = :empresa_id
                          AND ultima_actividad IS NOT NULL
                          AND ultima_actividad <= NOW()
                          AND ultima_actividad >= DATE_SUB(NOW(), INTERVAL {$intervaloEnLinea} MINUTE)
                          ORDER BY ultima_actividad DESC";
            }
            
            $stmt = $db->prepare($query);
            if ($esSuperAdminGlobalSinEmpresa) {
                $stmt->bindValue(':usuario_id', (int)$usuarioId, PDO::PARAM_INT);
            } elseif (!$esSuperAdmin) {
                $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($resultados as $usuario) {
                if ($ocultarSuperAdminEnResumen && esRolSuperAdminDashboard($usuario['rol'] ?? '')) {
                    continue;
                }

                $horaActividad = strtotime($usuario['ultima_actividad']);
                $tiempoEnLinea = time() - $horaActividad;
                $minutosEnLinea = floor($tiempoEnLinea / 60);
                
                $tiempoTexto = '';
                if ($minutosEnLinea < 1) {
                    $tiempoTexto = 'Ahora mismo';
                } elseif ($minutosEnLinea == 1) {
                    $tiempoTexto = 'Hace 1 min';
                } else {
                    $tiempoTexto = 'Hace ' . $minutosEnLinea . ' min';
                }
                
                $datosUsuario = [
                    'nombre' => trim($usuario['nombre'] . ' ' . $usuario['apellidos']),
                    'rol' => $usuario['rol'],
                    'email' => $usuario['correo'],
                    'hora_inicio' => date('H:i', $horaActividad),
                    'tiempo_en_linea' => $tiempoTexto,
                    'ultima_iso' => date('c', $horaActividad),
                    'minutos' => $minutosEnLinea
                ];
                
                $usuariosEnLinea[] = $datosUsuario;
                
                // Agrupar por rol
                if (!isset($usuariosPorRol[$usuario['rol']])) {
                    $usuariosPorRol[$usuario['rol']] = [];
                }
                $usuariosPorRol[$usuario['rol']][] = $datosUsuario;
            }
            $totalEnLinea = count($usuariosEnLinea);
        } else {
            // Si NO existe el campo, indicar error
            $errorUsuariosEnLinea = "Campo 'ultima_actividad' no encontrado en la base de datos";
        }
        
        // Obtener estadisticas de usuarios por rol (total registrados)
        $query = $esSuperAdminGlobalSinEmpresa
            ? "SELECT rol, COUNT(*) as cantidad FROM usuarios WHERE estado = 1 AND id = :usuario_id GROUP BY rol ORDER BY cantidad DESC"
            : ($esSuperAdmin
                ? "SELECT rol, COUNT(*) as cantidad FROM usuarios WHERE estado = 1 GROUP BY rol ORDER BY cantidad DESC"
                : "SELECT rol, COUNT(*) as cantidad FROM usuarios WHERE estado = 1 AND empresa_id = :empresa_id GROUP BY rol ORDER BY cantidad DESC");
        $stmt = $db->prepare($query);
        if ($esSuperAdminGlobalSinEmpresa) {
            $stmt->bindValue(':usuario_id', (int)$usuarioId, PDO::PARAM_INT);
        } elseif (!$esSuperAdmin) {
            $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $estadisticasUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($ocultarSuperAdminEnResumen) {
            $estadisticasUsuarios = array_values(array_filter($estadisticasUsuarios, function ($item) {
                return !esRolSuperAdminDashboard($item['rol'] ?? '');
            }));
        }
        
    } catch (Exception $e) {
        error_log("Error al obtener usuarios en linea: " . $e->getMessage());
        $errorUsuariosEnLinea = $e->getMessage();
        // Si hay error, al menos obtener las estadisticas basicas
        try {
            $db = Database::connect();
            $query = $esSuperAdminGlobalSinEmpresa
                ? "SELECT rol, COUNT(*) as cantidad FROM usuarios WHERE estado = 1 AND id = :usuario_id GROUP BY rol ORDER BY cantidad DESC"
                : ($esSuperAdmin
                    ? "SELECT rol, COUNT(*) as cantidad FROM usuarios WHERE estado = 1 GROUP BY rol ORDER BY cantidad DESC"
                    : "SELECT rol, COUNT(*) as cantidad FROM usuarios WHERE estado = 1 AND empresa_id = :empresa_id GROUP BY rol ORDER BY cantidad DESC");
            $stmt = $db->prepare($query);
            if ($esSuperAdminGlobalSinEmpresa) {
                $stmt->bindValue(':usuario_id', (int)$usuarioId, PDO::PARAM_INT);
            } elseif (!$esSuperAdmin) {
                $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $estadisticasUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($ocultarSuperAdminEnResumen) {
                $estadisticasUsuarios = array_values(array_filter($estadisticasUsuarios, function ($item) {
                    return !esRolSuperAdminDashboard($item['rol'] ?? '');
                }));
            }
        } catch (Exception $e2) {
            error_log("Error al obtener estadisticas: " . $e2->getMessage());
        }
    }
}

// Colores para cada rol (tonos suaves y elegantes)
$coloresRoles = [
    'Super Administrador' => '#c1666b',  // Rosa suave
    'Administrador' => '#4a7c8c',        // Azul grisaceo suave
    'Gerente' => '#6ba8c8',              // Azul cielo suave
    'Empleado' => '#63a375',             // Verde suave
    'Cliente' => '#d89a5a',              // Naranja suave
    'Invitado' => '#8e9aaf'              // Gris azulado suave
];

if ($mostrarPanelErrores && !$usarDiagnosticoAjax) {
    try {
        $rutasCandidatas = [];
        $rutaPhpIni = ini_get('error_log');

        if (!empty($rutaPhpIni)) {
            $rutasCandidatas[] = $rutaPhpIni;
        }

        $rutasCandidatas[] = ROOT_PATH . '/error_log';
        $rutasCandidatas[] = 'C:/xampp/php/logs/php_error_log';
        $rutasCandidatas[] = 'C:/xampp/apache/logs/error.log';

        $rutaSeleccionada = null;
        foreach ($rutasCandidatas as $ruta) {
            if (!empty($ruta) && is_file($ruta) && is_readable($ruta)) {
                $rutaSeleccionada = $ruta;
                break;
            }
        }

        if ($rutaSeleccionada !== null) {
            $fuenteErroresSistema = $rutaSeleccionada;
            $lineas = tailFileLines($rutaSeleccionada, 1200);
            // Solo eventos activos recientes que puedan estar afectando ahora.
            $limiteReciente = strtotime('-90 minutes');

            if (!empty($lineas)) {
                $lineasRecientes = array_reverse($lineas);

                foreach ($lineasRecientes as $linea) {
                    $tsLinea = obtenerTimestampLogDashboard($linea);
                    if ($tsLinea !== null && $tsLinea < $limiteReciente) {
                        continue;
                    }

                    $lineaNormalizada = function_exists('mb_strtolower')
                        ? mb_strtolower((string)$linea, 'UTF-8')
                        : strtolower((string)$linea);

                    $lineaNormalizada = strtr($lineaNormalizada, [
                        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                    ]);

                    $esErrorFuerte = preg_match('/(fatal error|parse error|warning|exception|uncaught|error:|sqlstate|pdoexception|cannot|undefined|failed|invalid)/i', $linea) === 1;
                    $esNotice = preg_match('/(notice|deprecated)/i', $linea) === 1;

                    $esFatalCritico = preg_match('/(fatal error|parse error|uncaught|exception|pdoexception)/i', $linea) === 1;

                    // Excluir ruido funcional del modulo de colores/pagos que no afecta todo el sistema.
                    $esRuidoModuloColores = preg_match('/(colores_empresa|intentos_colores|pago[s]?_intentos_colores|wompi|checkout_intentos|reactivar.*intentos|paquete de 3 intentos)/i', $lineaNormalizada) === 1;
                    if ($esRuidoModuloColores && !$esFatalCritico) {
                        continue;
                    }

                    $esNoticeInformativo = $esNotice && preg_match('/(login exitoso|resultado de verificarcredenciales|verificarcredenciales: comprobando|obtenerusuarioporcorreo: buscando|usuario encontrado por correo|login fallido para correo|verificando correo:|credenciales incorrectas - iniciando|incrementando intentos fallidos|intentos actuales despu|obteniendo resumen de inventario|sql para resumen|productos encontrados|productos con stock|resumen obtenido|roles obtenidos|contenido de \$usuarios|tipo de \$usuarios)/i', $lineaNormalizada) === 1;

                    if ($esNoticeInformativo) {
                        continue;
                    }

                    if (!$esErrorFuerte) {
                        continue;
                    }

                    $tipo = 'OTRO';
                    if (preg_match('/fatal error|parse error|uncaught|exception|pdoexception/i', $linea)) {
                        $tipo = 'FATAL';
                        $resumenErroresSistema['fatal']++;
                    } elseif (preg_match('/warning/i', $linea)) {
                        $tipo = 'WARNING';
                        $resumenErroresSistema['warning']++;
                    } elseif (preg_match('/notice|deprecated/i', $linea)) {
                        // Los notice/deprecated relevantes se elevan como warning operativo.
                        $tipo = 'WARNING';
                        $resumenErroresSistema['warning']++;
                    } else {
                        $resumenErroresSistema['otros']++;
                    }

                    $fecha = '';
                    if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4}\s+\d{2}:\d{2}:\d{2})\]/', $linea, $mFechaPhp)) {
                        $fecha = $mFechaPhp[1];
                    } elseif (preg_match('/\[(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s+[A-Za-z]{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}(?:\.\d+)?\s+\d{4}\]/', $linea, $mFechaApache)) {
                        $fecha = trim($mFechaApache[0], '[]');
                    }

                    $erroresSistema[] = [
                        'fecha' => $fecha,
                        'tipo' => $tipo,
                        'detalle' => trim($linea),
                        'archivo' => extraerArchivoErrorDashboard($linea)
                    ];

                    if (count($erroresSistema) >= 60) {
                        break;
                    }
                }
            } else {
                $errorLecturaErroresSistema = 'No se pudo leer el archivo de errores.';
            }
        } else {
            $errorLecturaErroresSistema = 'No se encontro un log legible (php.ini / proyecto / XAMPP).';
        }
    } catch (Exception $e) {
        $errorLecturaErroresSistema = $e->getMessage();
        error_log('Error al cargar panel de errores del sistema: ' . $e->getMessage());
    }

    $resumenErroresSistema['total'] = count($erroresSistema);
    if (!empty($erroresSistema)) {
        $ultimoErrorSistema = $erroresSistema[0];

        foreach ($erroresSistema as $err) {
            $archivoClave = $err['archivo'] ?? 'Archivo no identificado';
            if (!isset($erroresPorArchivo[$archivoClave])) {
                $erroresPorArchivo[$archivoClave] = [
                    'archivo' => $archivoClave,
                    'cantidad' => 0,
                    'items' => []
                ];
            }

            $erroresPorArchivo[$archivoClave]['cantidad']++;
            if (count($erroresPorArchivo[$archivoClave]['items']) < 4) {
                $erroresPorArchivo[$archivoClave]['items'][] = [
                    'tipo' => $err['tipo'] ?? 'OTRO',
                    'fecha' => $err['fecha'] ?? '',
                    'detalle' => $err['detalle'] ?? ''
                ];
            }
        }

        usort($erroresPorArchivo, function($a, $b) {
            return ($b['cantidad'] ?? 0) <=> ($a['cantidad'] ?? 0);
        });

        if (count($erroresPorArchivo) > 12) {
            $erroresPorArchivo = array_slice($erroresPorArchivo, 0, 12);
        }
    }

    $eventosListaLocal = [];
    foreach ($erroresSistema as $errItem) {
        $sev = strtoupper((string)($errItem['tipo'] ?? 'WARNING'));
        if ($sev !== 'FATAL' && $sev !== 'WARNING') {
            $sev = 'WARNING';
        }
        $eventosListaLocal[] = [
            'severity' => $sev,
            'file' => (string)($errItem['archivo'] ?? 'Archivo no identificado'),
            'line' => (string)($errItem['detalle'] ?? ''),
        ];
    }

    $resultadosLocal = [
        ['check' => 'Sesion super admin', 'status' => 'OK', 'detail' => 'Autorizado para ver diagnostico local.'],
        ['check' => 'Ruta de log', 'status' => empty($errorLecturaErroresSistema) ? 'OK' : 'WARN', 'detail' => $fuenteErroresSistema],
        ['check' => 'Lectura de log', 'status' => empty($errorLecturaErroresSistema) ? 'OK' : 'WARN', 'detail' => empty($errorLecturaErroresSistema) ? 'Eventos activos en ultimos 90 minutos.' : (string)$errorLecturaErroresSistema],
        ['check' => 'Eventos detectados', 'status' => count($eventosListaLocal) > 0 ? 'WARN' : 'OK', 'detail' => 'Eventos en ventana activa: ' . count($eventosListaLocal)],
    ];

    $diagnosticoLocalPayload = [
        'success' => true,
        'eventos' => [
            'scope' => 'active',
            'scope_label' => 'activos',
            'ventana_minutos' => 90,
            'total' => count($eventosListaLocal),
            'resumen' => [
                'FATAL' => (int)($resumenErroresSistema['fatal'] ?? 0),
                'WARNING' => (int)($resumenErroresSistema['warning'] ?? 0),
                'NOTICE' => 0,
                'INFO' => 0,
            ],
            'lista' => $eventosListaLocal,
        ],
        'resultados' => $resultadosLocal,
        'log_path' => $fuenteErroresSistema,
    ];
}

if ($mostrarPanelErrores && $usarDiagnosticoAjax) {
    $erroresSistema = [];
    $erroresPorArchivo = [];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= NOMBRE_EMPRESA ?></title>
    <link rel="icon" type="<?= $dashboardFaviconType; ?>" sizes="16x16" href="<?= htmlspecialchars($dashboardFaviconUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" type="<?= $dashboardFaviconType; ?>" sizes="16x16" href="<?= htmlspecialchars($dashboardFaviconUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="<?= $dashboardFaviconType; ?>" sizes="32x32" href="<?= htmlspecialchars($dashboardFaviconUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8'); ?>/Assets/js/main.js"></script>
    <style>
        :root {
            --company-primary: <?= htmlspecialchars($coloresEmpresa['color_principal']); ?>;
            --company-secondary: <?= htmlspecialchars($coloresEmpresa['color_secundario']); ?>;
            --company-sidebar: <?= htmlspecialchars($coloresEmpresa['color_menu_lateral']); ?>;
            --company-tertiary: <?= htmlspecialchars($coloresEmpresa['color_menu_lateral']); ?>;
            --company-button: <?= htmlspecialchars($coloresEmpresa['color_botones']); ?>;
            --company-background: <?= htmlspecialchars($coloresEmpresa['color_fondo']); ?>;
            --company-text: <?= htmlspecialchars($coloresEmpresa['color_texto']); ?>;
            --company-border: <?= htmlspecialchars($coloresEmpresa['color_bordes']); ?>;
            --primary-color: var(--company-primary);
            --primary-dark: var(--company-sidebar);
            --primary-light: var(--company-secondary);
            --error-color: #dc3545;
            --secondary-blue: var(--company-secondary);
            --white: #FFFFFF;
            --black: #000000;
            --topbar-height: 72px;
            --topbar-gap: 18px;
            --footer-height: 44px;
            --sidebar-width: 220px;
            --sidebar-collapsed-width: 80px;
            --hover-transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            --font-saira: 'Saira Condensed', sans-serif;
        }

        body {
            font-family: 'Sora', sans-serif;
            margin: 0;
            padding: 0;
            background:
                radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--company-primary) 10%, transparent), transparent 45%),
                radial-gradient(circle at 80% 10%, color-mix(in srgb, var(--company-secondary) 12%, transparent), transparent 40%),
                linear-gradient(180deg, var(--company-background) 0%, #ffffff 50%, var(--company-background) 100%);
            min-height: 100vh;
            color: var(--company-text);
            overflow: hidden;
            padding-top: calc(var(--topbar-height) + var(--topbar-gap));
        }

        #divLoading {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            background: radial-gradient(circle at 25% 20%, rgba(255, 255, 255, 0.95), rgba(238, 243, 251, 0.92) 40%, rgba(226, 235, 246, 0.9) 100%);
            backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity 0.22s ease;
        }

        #divLoading.is-active {
            opacity: 1;
        }

        #divLoading > div {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #divLoading img {
            position: relative;
            z-index: 1;
            width: 88px;
            height: 88px;
            object-fit: contain;
            filter: drop-shadow(0 10px 16px rgba(17, 78, 151, 0.25));
            animation: loadingLogoSpin 0.95s linear infinite;
        }

        @keyframes loadingLogoSpin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        body,
        body p,
        body span,
        body label,
        body li,
        body td,
        body th,
        body h1,
        body h2,
        body h3,
        body h4,
        body h5,
        body h6,
        body a,
        body input,
        body select,
        body textarea,
        body option {
            color: var(--company-text);
        }

        .topbar,
        .menu,
        .profile-dropdown,
        .info-card,
        .content-hero {
            border-color: var(--company-secondary) !important;
        }

        .menu-title,
        .profile-toggle,
        .logout-item,
        .hero-badge,
        .btn-logout,
        .content-hero,
        .stat-icon-small,
        .activity-icon {
            background: linear-gradient(135deg, var(--company-primary), var(--company-sidebar)) !important;
        }

        .menu-item:hover,
        .menu-item.active {
            background: var(--company-secondary) !important;
            color: var(--company-text) !important;
            border-color: var(--company-secondary) !important;
        }

        .topbar-time,
        .topbar-time-user,
        .info-card h3,
        .activity-text,
        .stat-number {
            color: var(--company-text) !important;
        }

        header {
            background: transparent;
            color: var(--company-text);
            padding: 20px 0;
            text-align: center;
            box-shadow: none;
        }

        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            min-height: 64px;
            background: linear-gradient(135deg, #ffffff 0%, #f7f9fb 100%);
            border-bottom: 2px solid var(--company-border);
            box-shadow: 0 8px 24px color-mix(in srgb, var(--company-primary) 10%, transparent);
            backdrop-filter: blur(12px);
            margin-bottom: 0;
        }

        .topbar-inner {
            max-width: 100%;
            margin: 0;
            padding: 8px 16px 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 64px;
            box-sizing: border-box;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 2;
        }

        .sidebar-toggle {
            display: none;
        }

        .topbar-time {
            font-size: 14px;
            color: var(--company-text);
            font-weight: 600;
            display: flex;
            align-items: stretch;
            gap: 12px;
            line-height: 1.15;
        }

        .topbar-company-media {
            width: 54px;
            min-width: 54px;
            height: 54px;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--company-border) 70%, #ffffff);
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(244,248,251,.92));
            box-shadow: 0 8px 16px color-mix(in srgb, var(--company-primary) 10%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex: 0 0 54px;
        }

        .topbar-company-media img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 6px;
            display: block;
        }

        .topbar-company-media span {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 900;
            color: var(--company-primary);
        }

        .topbar-time-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 2px;
            min-height: 54px;
        }

        .topbar-time-main {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .topbar-time-user {
            font-size: 12px;
            color: var(--company-text);
            font-weight: 700;
            letter-spacing: .2px;
            max-width: 62vw;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .topbar-user-role {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            font-weight: 700;
            opacity: 0.72;
        }

        .topbar-user-name {
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topbar-user-company {
            font-size: 9px;
            font-weight: 700;
            opacity: 0.8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 768px) {
            .topbar-company-media {
                width: 58px;
                min-width: 58px;
                height: 58px;
                border-radius: 16px;
            }

            .topbar-time-text {
                min-height: 58px;
            }
        }

        .profile-wrapper {
            position: relative;
            margin-left: auto;
            padding-left: 12px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .reload-app-btn {
            min-height: 34px;
            min-width: 34px;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--company-border) 70%, #ffffff);
            background: #ffffff;
            color: var(--company-primary);
            padding: 0 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .reload-app-btn:hover {
            background: var(--company-secondary);
            color: var(--company-text);
            transform: translateY(-1px);
        }

        .errors-toggle {
            min-height: 38px;
            border-radius: 999px;
            border: 1px solid #f1d0d4;
            background: #fff7f8;
            color: #7a1f2b;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .errors-toggle.ok {
            border-color: #b8dfc2;
            background: #eef9f1;
            color: #1f6b35;
        }

        .errors-dropdown {
            position: absolute;
            right: 54px;
            top: calc(100% + 8px);
            width: min(1300px, 96vw);
            max-height: 85vh;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid var(--company-border);
            border-radius: 14px;
            box-shadow: 0 12px 30px color-mix(in srgb, var(--company-primary) 16%, transparent);
            padding: 16px;
            display: none;
            z-index: 20;
        }

        .errors-dropdown.active {
            display: block;
        }

        .errors-file-block {
            border: 1px solid var(--company-border);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
            background: color-mix(in srgb, var(--company-primary) 3%, #ffffff);
        }

        .errors-file-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            color: var(--company-text);
        }

        .errors-file-name {
            max-width: 78%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .errors-file-count {
            background: var(--company-primary);
            color: var(--company-text);
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
        }

        .errors-item {
            font-size: 11px;
            color: var(--company-text);
            border-top: 1px dashed var(--company-border);
            padding-top: 6px;
            margin-top: 6px;
            line-height: 1.35;
        }

        .errors-item:first-of-type {
            border-top: 0;
            padding-top: 0;
            margin-top: 0;
        }

        .error-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .3px;
            border: 1px solid transparent;
        }

        .error-pill.fatal { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .error-pill.warning { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .error-pill.notice { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
        .error-pill.info { background: var(--company-border); color: #374151; border-color: #d1d5db; }
        .error-pill.ok { background: #dcfce7; color: #166534; border-color: #86efac; }
        .error-pill.error { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .error-pill.warn { background: #fef3c7; color: #92400e; border-color: #fcd34d; }

        .errors-copy-btn {
            border: 1px solid var(--company-border);
            background: #fff;
            color: var(--company-text);
            font-size: 11px;
            font-weight: 700;
            border-radius: 8px;
            padding: 5px 9px;
            cursor: pointer;
            transition: all .2s ease;
        }

        .errors-copy-btn:hover {
            background: var(--company-primary);
            color: var(--company-text);
        }

        .errors-close-btn {
            position: sticky;
            top: 0;
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 1px solid var(--company-border);
            background: #fff;
            color: var(--company-text);
            border-radius: 999px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            z-index: 1;
        }

        .errors-close-btn:hover {
            background: var(--company-primary);
            color: var(--company-text);
        }

        .error-nav-status {
            max-width: 420px;
            font-size: 11px;
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid #f1d0d4;
            background: #fff7f8;
            color: #7a1f2b;
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.25;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--company-primary) 8%, transparent);
        }

        .error-nav-status.ok {
            border-color: #b8dfc2;
            background: #eef9f1;
            color: #1f6b35;
        }

        .error-nav-status .file {
            font-weight: 700;
        }

        .profile-toggle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid transparent;
            background: linear-gradient(135deg, var(--company-primary), var(--company-tertiary));
            color: var(--company-text);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--company-primary) 15%, transparent);
        }

        .profile-toggle::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--company-secondary), var(--company-primary));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .profile-toggle:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--company-primary) 30%, transparent);
        }

        .profile-toggle:hover::before {
            opacity: 1;
        }

        .profile-toggle i {
            font-size: 18px;
            position: relative;
            z-index: 1;
        }

        .profile-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 320px;
            background: #ffffff;
            border: 1px solid var(--company-border);
            border-radius: 14px;
            box-shadow: 0 12px 30px color-mix(in srgb, var(--company-primary) 16%, transparent);
            padding: 20px;
            display: none;
        }

        .profile-dropdown.active {
            display: block;
        }

        .profile-card {
            position: relative;
        }

        .profile-dropdown-close {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            border: 1px solid var(--company-border);
            border-radius: 8px;
            background: #fff;
            color: var(--company-text);
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background .2s ease, color .2s ease, border-color .2s ease;
        }

        .profile-dropdown-close:hover {
            background: color-mix(in srgb, var(--company-primary) 10%, #fff);
            border-color: var(--company-primary);
        }

        .profile-hero {
            display: flex;
            gap: 14px;
            align-items: center;
            padding: 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 14%, transparent), color-mix(in srgb, var(--company-primary) 3%, transparent));
            border: 1px solid var(--company-border);
        }

        .profile-hero .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--company-primary);
            color: var(--company-text);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .profile-hero .avatar img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .profile-hero .name {
            font-size: 14px;
            font-weight: 700;
            color: var(--company-text);
            text-transform: uppercase;
        }

        .profile-hero .role {
            font-size: 12px;
            color: var(--company-sidebar);
            margin-top: 2px;
            text-transform: uppercase;
        }

        .detail-grid {
            display: grid;
            gap: 12px;
            margin-top: 12px;
        }

        .detail-grid.collapsed {
            display: none;
        }

        .profile-data-btn {
            width: 100%;
            margin-top: 12px;
            border: 1px solid var(--company-secondary);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .4px;
            color: var(--company-text);
            background: color-mix(in srgb, var(--company-primary) 7%, #ffffff);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .profile-data-btn:hover {
            background: color-mix(in srgb, var(--company-primary) 10%, #ffffff);
        }

        .profile-data-btn i:last-child {
            transition: transform .2s ease;
        }

        .profile-data-btn.expanded i:last-child {
            transform: rotate(180deg);
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid var(--company-border);
            border-radius: 10px;
            background: #fff;
        }

        .detail-item i {
            color: var(--company-text);
            width: 16px;
            text-align: center;
        }

        .detail-item .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--company-text);
        }

        .detail-item .value {
            font-size: 13px;
            font-weight: 600;
            color: var(--company-text);
        }

        .logout-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            background: var(--company-button);
            color: var(--company-text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: background 0.2s ease, transform 0.2s ease;
            margin-top: 16px;
        }

        .logout-item:hover {
            background: var(--company-tertiary);
            transform: translateY(-1px);
        }

        .profile-action-btn {
            width: 100%;
            margin-top: 12px;
            border: 1px solid var(--company-secondary);
            border-radius: 10px;
            padding: 10px 12px;
            min-height: 40px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .4px;
            color: var(--company-text);
            background: var(--company-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            box-sizing: border-box;
        }

        .profile-action-btn:hover {
            background: var(--company-sidebar);
        }

        .colors-form {
            display: grid;
            gap: 14px;
        }

        .company-advanced-sections {
            display: grid;
            gap: 12px;
        }

        .company-colors-controls .company-controls-head,
        .company-colors-controls .colors-help {
            display: none;
        }

        .color-field {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            align-items: center;
            gap: 10px;
            border: 1px solid color-mix(in srgb, var(--company-border) 78%, #d7e0e8);
            border-radius: 12px;
            padding: 10px 12px;
            background: #ffffff;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .color-field:hover {
            border-color: color-mix(in srgb, var(--company-primary) 24%, #d7e0e8);
            box-shadow: 0 8px 20px color-mix(in srgb, var(--company-primary) 10%, transparent);
        }

        .color-field label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            color: var(--company-text);
            text-transform: uppercase;
        }

        .color-field input[type="color"] {
            width: 38px;
            height: 38px;
            border: 1px solid color-mix(in srgb, var(--company-border) 72%, #d7e0e8);
            border-radius: 10px;
            background: transparent;
            cursor: pointer;
        }

        .color-hex {
            min-width: 96px;
            text-align: right;
            font-size: 11px;
            font-weight: 700;
            color: var(--company-text);
            border: 1px solid color-mix(in srgb, var(--company-border) 75%, #d7e0e8);
            border-radius: 999px;
            padding: 5px 10px;
            background: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 7px;
        }

        .color-hex::before {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: var(--hex-color, #ffffff);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
        }

        .pastel-presets {
            border: 1px solid var(--company-border);
            border-radius: 14px;
            background: #ffffff;
            padding: 12px;
            display: grid;
            gap: 10px;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--company-primary) 8%, transparent);
        }

        .recent-presets-head {
            margin-top: 2px;
            font-size: 10px;
            font-weight: 700;
            color: var(--company-text);
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .recent-presets-grid {
            display: grid;
            grid-template-columns: repeat(10, minmax(0, 1fr));
            gap: 4px;
        }

        .recent-presets-grid .preset-color-btn {
            width: 100%;
            aspect-ratio: auto;
            min-height: 18px;
            height: 18px;
            border-radius: 999px;
            border-width: 1px;
        }

        .preset-color-btn {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 10px;
            border: 2px solid transparent;
            cursor: pointer;
            padding: 0;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }

        .preset-color-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px color-mix(in srgb, var(--company-primary) 22%, transparent);
            border-color: color-mix(in srgb, var(--company-primary) 35%, #ffffff);
        }

        .preset-color-btn.is-selected {
            border-color: #ffffff;
            box-shadow: 0 0 0 2px var(--company-primary), 0 8px 16px color-mix(in srgb, var(--company-primary) 26%, transparent);
            transform: translateY(-1px) scale(1.04);
        }

        .color-field.is-selected {
            border-color: var(--company-primary);
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--company-primary) 22%, transparent);
            background: color-mix(in srgb, var(--company-primary) 6%, #ffffff);
        }

        .color-field.is-selected label {
            color: var(--company-primary);
        }

        @media (max-width: 1100px) {
            .recent-presets-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .recent-presets-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .save-colors-btn {
            border: 0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .5px;
            color: var(--company-text);
            background: var(--company-button);
            cursor: pointer;
            position: sticky;
            bottom: 0;
            z-index: 2;
            box-shadow: 0 8px 18px color-mix(in srgb, var(--company-primary) 16%, transparent);
        }

        .color-status {
            min-height: 16px;
            font-size: 11px;
            font-weight: 700;
            color: #1f6b35;
        }

        .color-status.error {
            color: #b3261e;
        }

        /* ===== TABS DEL PREVIEW ===== */

        .preview-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--company-border);
            background: linear-gradient(180deg, #ffffff, color-mix(in srgb, var(--company-background) 32%, #ffffff));
            flex-wrap: wrap;
        }

        .preview-tabs {
            display: inline-flex;
            gap: 5px;
            background: color-mix(in srgb, var(--company-primary) 8%, #eef3f7);
            border-radius: 12px;
            padding: 4px;
        }

        .preview-tab {
            border: 0;
            border-radius: 10px;
            background: transparent;
            padding: 7px 13px;
            font-size: 11px;
            font-weight: 700;
            color: var(--company-text);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background .15s ease, color .15s ease;
        }

        .preview-tab.active {
            background: #fff;
            box-shadow: 0 8px 16px color-mix(in srgb, var(--company-primary) 14%, transparent);
            color: var(--company-primary);
        }

        .preview-expand-btn {
            border: 1px solid color-mix(in srgb, var(--company-primary) 16%, var(--company-border));
            background: #ffffff;
            color: var(--company-primary);
            border-radius: 10px;
            padding: 7px 12px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all .15s ease;
        }

        .preview-expand-btn:hover {
            background: color-mix(in srgb, var(--company-primary) 10%, #ffffff);
        }

        body.preview-fullscreen-open {
            overflow: hidden;
        }

        #companyColorsPreview.preview-fullscreen {
            position: fixed;
            inset: 8px;
            z-index: 10060;
            margin: 0;
            width: auto;
            max-width: none;
            height: calc(100vh - 16px);
            max-height: none;
            border-radius: 16px;
            box-shadow: 0 26px 64px rgba(15, 23, 42, 0.34);
            overflow: hidden;
        }

        #companyColorsPreview.preview-fullscreen .company-preview-canvas {
            height: 100%;
            max-height: none;
            overflow: auto;
        }

        #companyColorsPreview.preview-fullscreen .company-preview-stage {
            min-height: 100%;
        }

        .preview-changed-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #fffbeb;
            border-bottom: 1px solid #fde68a;
            font-size: 11px;
            font-weight: 700;
            color: #92400e;
        }

        .preview-tab-hint {
            font-weight: 400;
            opacity: .7;
        }

        .preview-live-panel {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 14px;
            padding: 14px;
            border-bottom: 1px solid var(--company-border);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 9%, #ffffff), #ffffff 42%),
                linear-gradient(180deg, #ffffff, color-mix(in srgb, var(--company-background) 40%, #ffffff));
        }

        .preview-live-main {
            display: grid;
            gap: 10px;
            min-width: 0;
        }

        .preview-live-label-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .preview-live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            border: 1px solid var(--company-border);
            padding: 4px 10px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: var(--company-text);
        }

        .preview-live-title {
            margin: 0;
            font-size: 18px;
            line-height: 1.1;
            color: var(--company-title-color, var(--company-text));
        }

        .preview-live-copy {
            margin: 0;
            font-size: 12px;
            line-height: 1.55;
            color: var(--company-text);
            max-width: 60ch;
        }

        .preview-live-meta {
            display: flex;
            align-items: stretch;
            gap: 10px;
            flex-wrap: wrap;
        }

        .preview-live-chip {
            min-width: 110px;
            border-radius: 14px;
            border: 1px solid var(--company-border);
            background: #ffffff;
            padding: 10px 12px;
            box-shadow: 0 8px 18px color-mix(in srgb, var(--company-primary) 8%, transparent);
        }

        .preview-live-chip-label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            opacity: .7;
            margin-bottom: 4px;
        }

        .preview-live-chip-value {
            font-size: 13px;
            font-weight: 800;
            color: var(--company-text);
            word-break: break-word;
        }


        .preview-live-note {
            font-size: 11px;
            line-height: 1.45;
            color: var(--company-text);
            opacity: .84;
        }

        /* ===== CAMPO MODIFICADO ===== */

        .color-field.is-modified {
            border-color: #f59e0b;
            background: linear-gradient(90deg, #fffbeb, #ffffff);
        }

        .color-field.is-modified label::after {
            content: ' •';
            color: #f59e0b;
            font-weight: 900;
        }

        /* ===== BOTÓN CANCELAR ===== */

        .btn-cancelar-cambios {
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            background: #fffbeb;
            color: #92400e;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-cancelar-cambios:hover {
            background: #fde68a;
        }

        /* ===== SECCIONES DE COLOR ===== */

        .color-section {
            border: 1px solid var(--company-border);
            border-radius: 11px;
            background: #fff;
            overflow: hidden;
        }

        .color-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            background: linear-gradient(90deg, color-mix(in srgb, var(--company-primary) 10%, #fff), #fff);
            font-size: 11px;
            font-weight: 700;
            color: var(--company-text);
            text-transform: uppercase;
            letter-spacing: .4px;
            cursor: pointer;
            user-select: none;
            border-bottom: 1px solid var(--company-border);
        }

        .color-section-title .section-arrow {
            margin-left: auto;
            font-size: 10px;
            transition: transform .2s ease;
            opacity: .7;
        }

        .color-section.collapsed .section-arrow {
            transform: rotate(-90deg);
        }

        .color-section.collapsed .color-section-body {
            display: none;
        }

        .color-section .color-field {
            border-radius: 0;
            border: 0;
            border-bottom: 1px solid #f0f3f6;
        }

        .color-section .color-field:last-child {
            border-bottom: 0;
        }

        /* ===== BADGE DE INTENTOS ===== */

        .intentos-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--company-border);
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 8%, #fff), #fff);
            font-size: 12px;
            font-weight: 700;
            color: var(--company-text);
            flex-wrap: wrap;
        }

        .intentos-badge.bloqueado {
            background: linear-gradient(135deg, #fff0f0, #fff);
            border-color: #f8c1c1;
        }

        .intentos-count {
            font-size: 20px;
            font-weight: 900;
            color: var(--company-primary);
            min-width: 22px;
            text-align: center;
            line-height: 1;
        }

        .intentos-badge.bloqueado .intentos-count {
            color: #b3261e;
        }

        .intentos-total {
            font-size: 12px;
            color: var(--company-text);
            opacity: .65;
        }

        .intentos-sin-limite {
            margin-left: auto;
            font-size: 11px;
            color: var(--company-text);
            opacity: .6;
        }

        .btn-pagar-intentos {
            margin-left: auto;
            border: 0;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .3px;
            background: var(--company-button);
            color: var(--company-text);
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-pagar-intentos:hover {
            opacity: .85;
        }

        /* ===== FILA DE ACCIONES (RESTAURAR / GUARDAR) ===== */

        .colors-action-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
            flex-wrap: wrap;
            position: sticky;
            bottom: 0;
            z-index: 2;
            background: #fff;
            padding-top: 10px;
            border-top: 1px solid var(--company-border);
            margin-top: 4px;
        }

        .btn-restaurar-colores {
            border: 1px solid var(--company-border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            background: #fff;
            color: var(--company-text);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-restaurar-colores:hover {
            background: color-mix(in srgb, var(--company-primary) 8%, #fff);
        }

        .save-colors-btn {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .save-colors-btn.bloqueado {
            background: linear-gradient(135deg, #c65b4e, #a43f34);
            color: #fff;
            box-shadow: 0 8px 18px rgba(164, 63, 52, .25);
        }

        .save-colors-btn.bloqueado:hover {
            filter: brightness(1.03);
        }

        /* ===== HIGHLIGHT DE SECCIÓN EN PREVIEW ===== */

        #companyColorsPreview[data-highlight-target] .company-preview-header,
        #companyColorsPreview[data-highlight-target] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target] .company-preview-topbar,
        #companyColorsPreview[data-highlight-target] .company-preview-hero,
        #companyColorsPreview[data-highlight-target] .company-preview-summary,
        #companyColorsPreview[data-highlight-target] .company-preview-table,
        #companyColorsPreview[data-highlight-target] .company-preview-button,
        #companyColorsPreview[data-highlight-target] .company-preview-action-btn,
        #companyColorsPreview[data-highlight-target] .company-preview-topbar-btn,
        #companyColorsPreview[data-highlight-target] .company-preview-menu-item,
        #companyColorsPreview[data-highlight-target] .company-preview-hero-link,
        #companyColorsPreview[data-highlight-target] .company-preview-card,
        #companyColorsPreview[data-highlight-target] .company-preview-card-value,
        #companyColorsPreview[data-highlight-target] .company-preview-card-note,
        #companyColorsPreview[data-highlight-target] .company-preview-table-head,
        #companyColorsPreview[data-highlight-target] .company-preview-table-row {
            transition: opacity .18s ease, filter .18s ease, transform .18s ease, box-shadow .18s ease, outline-color .18s ease;
        }

        #companyColorsPreview[data-highlight-target] .company-preview-logo-target,
        #companyColorsPreview[data-highlight-target] .company-preview-logo-target img,
        #companyColorsPreview[data-highlight-target] .company-preview-logo-card,
        #companyColorsPreview[data-highlight-target] .company-preview-brand-icon {
            opacity: 1;
            filter: none;
        }

        #companyColorsPreview[data-highlight-target="branding"] .company-preview-header,
        #companyColorsPreview[data-highlight-target="branding"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="branding"] .company-preview-brand,
        #companyColorsPreview[data-highlight-target="branding"] .company-preview-logo-card {
            opacity: 1;
            filter: none;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--company-primary) 28%, transparent);
            border-radius: 10px;
        }

        #companyColorsPreview[data-highlight-target="sidebar"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="sidebar"] .company-preview-menu-item {
            opacity: 1;
            filter: none;
            outline: 3px solid #f59e0b;
            outline-offset: -2px;
            border-radius: 6px;
        }

        #companyColorsPreview[data-highlight-target="menu-active"] .company-preview-menu-item.active {
            opacity: 1;
            filter: none;
            outline: 3px solid #f59e0b;
            outline-offset: 2px;
        }

        #companyColorsPreview[data-highlight-target="accents"] .company-preview-menu-item.active,
        #companyColorsPreview[data-highlight-target="accents"] .company-preview-chip,
        #companyColorsPreview[data-highlight-target="accents"] .company-preview-chip.muted,
        #companyColorsPreview[data-highlight-target="accents"] .company-preview-status,
        #companyColorsPreview[data-highlight-target="accents"] .company-preview-action-btn.alt {
            opacity: 1;
            filter: none;
            outline: 3px solid #f59e0b;
            outline-offset: 2px;
            border-radius: 8px;
        }

        #companyColorsPreview[data-highlight-target="navbar"] .company-preview-topbar {
            opacity: 1;
            filter: none;
            outline: 3px solid #f59e0b;
            outline-offset: -2px;
            border-radius: 10px;
        }

        #companyColorsPreview[data-highlight-target="content"] .company-preview-hero-title,
        #companyColorsPreview[data-highlight-target="content"] .company-preview-card-value,
        #companyColorsPreview[data-highlight-target="content"] .company-preview-hero-link,
        #companyColorsPreview[data-highlight-target="content"] .company-preview-card-note {
            opacity: 1;
            filter: none;
            text-shadow: none;
        }

        #companyColorsPreview[data-highlight-target="table-body"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-header"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-zebra"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-text"] .company-preview-table {
            opacity: 1;
            filter: none;
        }

        #companyColorsPreview[data-highlight-target="table-body"] .company-preview-table {
            outline: 3px solid #f59e0b;
            outline-offset: -3px;
            border-radius: 4px;
        }

        #companyColorsPreview[data-highlight-target="table-header"] .company-preview-table-head {
            opacity: 1;
            filter: none;
            outline: 3px solid #f59e0b;
            outline-offset: -2px;
        }

        #companyColorsPreview[data-highlight-target="table-zebra"] .company-preview-table-row:nth-child(even) {
            opacity: 1;
            filter: none;
            outline: 2px solid #f59e0b;
            outline-offset: -2px;
        }

        #companyColorsPreview[data-highlight-target="table-text"] .company-preview-table-row {
            opacity: 1;
            filter: none;
            outline: 2px solid #f59e0b;
            outline-offset: -2px;
        }

        #companyColorsPreview[data-highlight-target="buttons"] .company-preview-button,
        #companyColorsPreview[data-highlight-target="buttons"] .company-preview-action-btn,
        #companyColorsPreview[data-highlight-target="buttons"] .company-preview-topbar-btn {
            opacity: 1;
            filter: none;
            outline: 3px solid #f59e0b;
            outline-offset: 2px;
            border-radius: 6px;
            transform: translateY(-1px);
        }

        #companyColorsPreview[data-highlight-target="forms"] .company-preview-form-strip,
        #companyColorsPreview[data-highlight-target="forms"] .company-preview-input,
        #companyColorsPreview[data-highlight-target="forms"] .company-preview-select {
            opacity: 1;
            filter: none;
            outline: 3px solid #f59e0b;
            outline-offset: -2px;
        }

        #companyColorsPreview[data-highlight-target="surface"] .company-preview-main,
        #companyColorsPreview[data-highlight-target="surface"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="surface"] .company-preview-summary {
            opacity: 1;
            filter: none;
            outline: 3px solid #f59e0b;
            outline-offset: -3px;
            border-radius: 8px;
        }

        #companyColorsPreview[data-highlight-target="text"] .company-preview-main,
        #companyColorsPreview[data-highlight-target="text"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="text"] .company-preview-card {
            opacity: 1;
            filter: none;
        }

        #companyColorsPreview[data-highlight-target="borders"] .company-preview-card,
        #companyColorsPreview[data-highlight-target="borders"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="borders"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="borders"] .company-preview-topbar {
            opacity: 1;
            filter: none;
            outline: 2px dashed #f59e0b;
            outline-offset: -2px;
        }

        /* ===== MODAL MIS DATOS ===== */

        .mis-datos-modal {
            position: fixed;
            inset: 0;
            background: rgba(13, 23, 34, 0.55);
            z-index: 470;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .mis-datos-modal.active {
            display: flex;
        }

        .mis-datos-dialog {
            width: min(680px, 96vw);
            background: #fff;
            border-radius: 18px;
            border: 1px solid var(--company-border);
            box-shadow: 0 18px 48px rgba(12, 24, 35, 0.35);
            overflow: hidden;
            max-height: 90vh;
            overflow-y: auto;
        }

        .mis-datos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: linear-gradient(135deg, var(--company-primary), var(--company-sidebar));
            color: var(--company-text);
        }

        .mis-datos-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mis-datos-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.18);
            color: var(--company-text);
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .mis-datos-body {
            padding: 20px;
            display: grid;
            gap: 16px;
        }

        .mis-datos-avatar {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            padding: 18px 20px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 12%, #fff), #fff);
            border-radius: 14px;
            border: 1px solid var(--company-border);
            align-items: center;
        }

        .mis-datos-avatar-circle {
            width: 62px;
            height: 62px;
            min-width: 62px;
            border-radius: 50%;
            background: var(--company-primary);
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px color-mix(in srgb, var(--company-primary) 35%, transparent);
            overflow: hidden;
            border: 2px solid color-mix(in srgb, var(--company-primary) 25%, #fff);
        }

        .mis-datos-avatar-circle img {
            width: 56%;
            height: 56%;
            object-fit: contain;
        }

        .mis-datos-avatar-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
        }

        .mis-datos-name {
            font-size: 15px;
            font-weight: 800;
            color: var(--company-primary);
            line-height: 1.1;
            text-transform: uppercase;
        }

        .mis-datos-role {
            font-size: 11px;
            font-weight: 600;
            color: var(--company-sidebar);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .mis-datos-nombre-editrow {
            display: flex;
            gap: 0;
            align-items: stretch;
        }

        .mis-datos-nombre-editrow .mis-datos-nombre-input {
            flex: 1;
            border-radius: 10px 0 0 10px !important;
        }

        .mis-datos-edit-btn {
            width: 42px;
            flex-shrink: 0;
            padding: 0;
            border: 1px solid var(--company-border);
            border-left: none;
            border-radius: 0 10px 10px 0;
            background: color-mix(in srgb, var(--company-primary) 8%, #fff);
            color: var(--company-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-size: 14px;
            transition: background .2s, color .2s;
        }

        .mis-datos-edit-btn:hover,
        .mis-datos-edit-btn.activo {
            background: var(--company-primary);
            color: #fff;
        }

        .mis-datos-nombre-input.editando {
            border-color: var(--company-primary) !important;
            background: #fff !important;
            cursor: text !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--company-primary) 15%, transparent);
        }

        .mis-datos-admin-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: color-mix(in srgb, var(--company-primary) 8%, #fff);
            border: 1px solid var(--company-border);
            border-left: 4px solid var(--company-primary);
            border-radius: 10px;
        }

        .mis-datos-admin-badge i {
            color: var(--company-primary);
            font-size: 18px;
            flex-shrink: 0;
        }

        .mis-datos-admin-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 700;
            color: var(--company-text);
            opacity: 0.65;
        }

        .mis-datos-admin-nombre {
            font-size: 14px;
            font-weight: 700;
            color: var(--company-primary);
            text-transform: uppercase;
        }

        .mis-datos-grid {
            display: grid;
            gap: 8px;
        }

        .mis-datos-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid var(--company-border);
            border-radius: 10px;
            background: #fff;
        }

        .mis-datos-item i {
            color: var(--company-primary);
            width: 16px;
            text-align: center;
            flex-shrink: 0;
        }

        .mis-datos-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--company-text);
            opacity: .65;
            font-weight: 700;
        }

        .mis-datos-value {
            font-size: 13px;
            font-weight: 600;
            color: var(--company-text);
            word-break: break-all;
            text-transform: uppercase;
        }

        .mis-datos-form {
            display: grid;
            gap: 12px;
        }

        .mis-datos-readonly-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .mis-datos-field {
            display: grid;
            gap: 4px;
        }

        .mis-datos-field label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--company-text);
            opacity: .78;
            font-weight: 700;
        }

        .mis-datos-field input[type="text"],
        .mis-datos-field input[type="file"] {
            width: 100%;
            border: 1px solid var(--company-border);
            border-radius: 10px;
            padding: 7px 9px;
            font-size: 11px;
            color: var(--company-text);
            background: #fff;
            transition: border-color .2s ease, box-shadow .2s ease;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.2;
            box-sizing: border-box;
            text-transform: uppercase;
        }

        .mis-datos-field input[readonly] {
            background: color-mix(in srgb, var(--company-primary) 6%, #ffffff);
            cursor: not-allowed;
            font-size: 11px !important;
            padding: 7px 9px !important;
        }

        .mis-datos-field input[type="text"]:focus {
            outline: none;
            border-color: var(--company-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--company-primary) 18%, transparent);
        }

        .input-with-edit {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-with-edit input {
            flex: 1;
        }

        .mis-datos-edit-btn {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            color: var(--company-secondary);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .mis-datos-edit-btn:hover {
            background: color-mix(in srgb, var(--company-secondary) 20%, transparent);
        }

        .mis-datos-readonly-hint {
            margin: 0;
            padding: 8px 10px;
            border-radius: 9px;
            border: 1px solid var(--company-border);
            background: color-mix(in srgb, var(--company-primary) 6%, #ffffff);
            color: var(--company-text);
            font-size: 11px;
            font-weight: 600;
            line-height: 1.35;
        }

        .mis-datos-logo-upload {
            display: grid;
            grid-template-columns: 50px 1fr;
            gap: 12px;
            align-items: center;
            padding: 12px;
            border: 1px dashed var(--company-border);
            border-radius: 10px;
            background: color-mix(in srgb, var(--company-primary) 5%, #ffffff);
        }

        .mis-datos-logo-preview {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            border: 1px solid var(--company-border);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #fff;
            color: var(--company-primary);
            font-size: 20px;
            flex-shrink: 0;
        }

        .mis-datos-logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .mis-datos-logo-controls small {
            display: block;
            margin-top: 4px;
            color: var(--company-text);
            opacity: .72;
            font-size: 10px;
            line-height: 1.3;
        }

        .mis-datos-logo-controls input[type="file"] {
            padding: 5px 8px !important;
            font-size: 11px !important;
            height: 28px;
            line-height: 1;
        }

        .mis-datos-actions {
            margin-top: 2px;
            position: relative;
        }

        .mis-datos-danger-zone {
            margin-top: 12px;
            padding: 14px;
            border: 1px solid rgba(179, 30, 30, 0.18);
            border-radius: 12px;
            background: linear-gradient(180deg, rgba(255, 245, 245, 0.96), rgba(255, 252, 252, 0.98));
            display: grid;
            gap: 10px;
            justify-items: center;
            text-align: center;
        }

        .mis-datos-danger-copy {
            display: grid;
            gap: 4px;
            color: #7a1f1f;
            font-size: 12px;
            line-height: 1.45;
            justify-items: center;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .mis-datos-delete {
            width: auto;
            min-width: 0;
            border: 1px solid #b3261e;
            border-radius: 10px;
            padding: 11px 12px;
            background: #b3261e;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            cursor: pointer;
            transition: transform .16s ease, filter .16s ease;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .mis-datos-delete:hover:not(:disabled) {
            filter: brightness(0.95);
            transform: translateY(-1px);
        }

        .mis-datos-delete:disabled {
            opacity: .7;
            cursor: not-allowed;
        }

        .mis-datos-save {
            width: 100%;
            border: 1px solid var(--company-primary);
            border-radius: 10px;
            padding: 11px 12px;
            background: var(--company-primary);
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            cursor: pointer;
            transition: transform .16s ease, filter .16s ease;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .mis-datos-save:hover:not(:disabled) {
            filter: brightness(0.96);
            transform: translateY(-1px);
        }

        .mis-datos-save:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .mis-datos-loading {
            display: none;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .mis-datos-save.cargando .mis-datos-loading {
            display: block;
        }

        .mis-datos-save.cargando i {
            display: none;
        }

        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .mis-datos-status {
            min-height: 18px;
            font-size: 12px;
            color: var(--company-primary);
            font-weight: 600;
        }

        .mis-datos-quick-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .mis-datos-quick-btn {
            width: 100%;
            border: 1px solid var(--company-primary);
            border-radius: 10px;
            padding: 11px 12px;
            background: #ffffff;
            color: var(--company-primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.35px;
            text-transform: uppercase;
            cursor: pointer;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            transition: background .16s ease, color .16s ease, transform .16s ease;
        }

        .mis-datos-quick-btn:hover {
            background: color-mix(in srgb, var(--company-primary) 10%, #ffffff);
            transform: translateY(-1px);
        }

        .mis-datos-help-note {
            margin: 0;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--company-border);
            background: color-mix(in srgb, var(--company-primary) 6%, #ffffff);
            color: var(--company-text);
            font-size: 11px;
            font-weight: 600;
            line-height: 1.45;
        }

        .mis-datos-status.error {
            color: #c62828;
        }

        @media (max-width: 640px) {
            .mis-datos-dialog {
                width: 98vw;
            }
        }

        .company-colors-modal {
            position: fixed;
            inset: 0;
            background: rgba(13, 23, 34, 0.55);
            z-index: 300;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .24s ease, visibility .24s ease, backdrop-filter .24s ease;
            backdrop-filter: blur(0px);
        }

        .company-colors-modal.active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            backdrop-filter: blur(4px);
        }

        body.modal-company-colors-open {
            overflow: hidden;
        }

        .company-ui-toast-container {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 9800;
            display: grid;
            gap: 10px;
            pointer-events: none;
            width: min(360px, 92vw);
        }

        .company-ui-toast {
            border-radius: 12px;
            border: 1px solid var(--company-border);
            background: #ffffff;
            box-shadow: 0 12px 28px color-mix(in srgb, var(--company-primary) 20%, transparent);
            padding: 12px 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--company-text);
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity .2s ease, transform .2s ease;
        }

        .company-ui-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .company-ui-toast.success {
            border-color: #b8dfc2;
            background: #eef9f1;
            color: #1f6b35;
        }

        .company-ui-toast.warning {
            border-color: #fcd34d;
            background: #fef3c7;
            color: #92400e;
        }

        .company-ui-toast.error {
            border-color: #fecaca;
            background: #fee2e2;
            color: #991b1b;
        }

        .company-ui-dialog-overlay {
            position: fixed;
            inset: 0;
            background: rgba(13, 23, 34, 0.6);
            z-index: 9801;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .company-ui-dialog-overlay.active {
            display: flex;
        }

        .company-ui-dialog {
            width: min(460px, 96vw);
            border-radius: 16px;
            border: 1px solid var(--company-border);
            background: #ffffff;
            box-shadow: 0 20px 50px rgba(12, 24, 35, 0.35);
            overflow: hidden;
        }

        .company-ui-dialog-header {
            padding: 14px 16px;
            background: linear-gradient(135deg, var(--company-primary), var(--company-sidebar));
            color: var(--company-text);
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .company-ui-dialog-body {
            padding: 16px;
            font-size: 13px;
            line-height: 1.4;
            color: var(--company-text);
        }

        .company-ui-dialog-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 0 16px 16px;
        }

        .company-ui-btn {
            border-radius: 10px;
            border: 1px solid var(--company-border);
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            color: var(--company-text);
            background: #fff;
        }

        .company-ui-btn.primary {
            background: var(--company-button);
            border-color: var(--company-button);
            color: var(--company-text);
        }

        .company-colors-dialog {
            width: min(1180px, 96vw);
            max-height: 90vh;
            background: #f6f9fc;
            border-radius: 16px;
            border: 1px solid var(--company-border);
            box-shadow: 0 18px 44px rgba(12, 24, 35, 0.3);
            overflow: hidden;
            display: grid;
            grid-template-rows: auto 1fr;
            transform: translateY(20px) scale(0.985);
            opacity: 0;
            transition: transform .26s ease, opacity .24s ease;
        }

        .company-colors-modal.active .company-colors-dialog {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .company-colors-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 14px;
            background: linear-gradient(135deg, var(--company-primary), var(--company-sidebar));
            color: var(--company-text);
        }

        .company-colors-header-copy {
            display: grid;
            gap: 2px;
        }

        .company-colors-header h3 {
            margin: 0;
            font-size: 15px;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .company-colors-header p {
            margin: 0;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .2px;
            opacity: .88;
        }

        .company-colors-close {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.18);
            color: var(--company-text);
            font-size: 18px;
            cursor: pointer;
        }

        .company-colors-body {
            display: grid;
            grid-template-columns: minmax(260px, 300px) minmax(0, 1fr);
            gap: 12px;
            padding: 12px;
            min-height: 0;
        }

        .company-colors-controls {
            border: 1px solid var(--company-border);
            border-radius: 12px;
            padding: 12px;
            background: linear-gradient(180deg, #fbfdff 0%, #ffffff 100%);
            overflow: auto;
            min-width: 0;
            box-shadow: 0 10px 20px color-mix(in srgb, var(--company-primary) 8%, transparent);
        }

        .company-controls-head {
            border: 1px solid var(--company-border);
            border-radius: 12px;
            background: #ffffff;
            padding: 10px 12px;
            display: grid;
            gap: 4px;
        }

        .company-controls-head-title {
            margin: 0;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            color: var(--company-title-color, var(--company-text));
        }

        .company-controls-head-copy {
            margin: 0;
            font-size: 11px;
            line-height: 1.45;
            color: var(--company-text);
            opacity: .88;
        }

        .colors-help {
            margin: 0 0 8px 0;
            padding: 8px 10px;
            border-radius: 9px;
            border: 1px solid var(--company-border);
            background: #ffffff;
            font-size: 11px;
            color: var(--company-text);
            font-weight: 600;
            line-height: 1.35;
        }

        .company-logo-sample {
            margin: 0 0 10px 0;
            border-radius: 12px;
            border: 1px solid var(--company-border);
            background: linear-gradient(180deg, #ffffff 0%, color-mix(in srgb, var(--company-background) 55%, #ffffff) 100%);
            padding: 14px;
            display: grid;
            gap: 12px;
            min-width: 0;
            overflow: hidden;
        }

        .company-logo-sample-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--company-text);
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .company-logo-sample-media {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            height: clamp(130px, 24vh, 180px);
            border-radius: 12px;
            border: 1px solid var(--company-border);
            background:
                linear-gradient(45deg, color-mix(in srgb, var(--company-border) 35%, #ffffff) 25%, transparent 25%, transparent 75%, color-mix(in srgb, var(--company-border) 35%, #ffffff) 75%),
                linear-gradient(45deg, color-mix(in srgb, var(--company-border) 35%, #ffffff) 25%, transparent 25%, transparent 75%, color-mix(in srgb, var(--company-border) 35%, #ffffff) 75%),
                #ffffff;
            background-size: 18px 18px;
            background-position: 0 0, 9px 9px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--company-text);
            font-size: 56px;
            padding: 14px;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .company-logo-sample-media img {
            max-width: 100%;
            max-height: 100%;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
            image-rendering: auto;
            filter: drop-shadow(0 3px 10px color-mix(in srgb, var(--company-primary) 18%, transparent));
        }

        @media (max-width: 768px) {
            .company-logo-sample-media {
                width: 100%;
                min-width: 0;
                height: clamp(180px, 38vh, 230px);
            }
        }

        .company-logo-sample-note {
            font-size: 11px;
            color: var(--company-text);
            line-height: 1.3;
        }

        .company-colors-preview {
            border: 1px solid var(--company-border);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            min-height: 420px;
            display: grid;
            grid-template-rows: auto auto auto minmax(0, 1fr);
            box-shadow: 0 10px 20px color-mix(in srgb, var(--company-primary) 10%, transparent);
            --company-primary: #2F4A5A;
            --company-secondary: #2F4A5A;
            --company-sidebar: #2F4A5A;
            --company-button: #2F4A5A;
            --company-background: #F5F5F5;
            --company-text: #8B8B8B;
            --company-border: #DFF3DE;
        }

        .company-preview-canvas {
            background: linear-gradient(180deg, #f2f6fa 0%, #eaf0f5 100%);
            padding: 10px;
            overflow: auto;
            min-height: 0;
        }

        .company-preview-stage {
            display: grid;
            grid-template-columns: 220px 1fr;
            grid-template-rows: auto 1fr;
            min-height: 360px;
            background: var(--company-background);
            border: 1px solid var(--company-border);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 8px 16px rgba(20, 34, 48, 0.14);
        }

        .company-preview-header {
            grid-column: 1 / -1;
            background: var(--company-navbar, var(--company-primary));
            color: var(--contrast-navbar, var(--company-text));
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .company-preview-brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .company-preview-brand-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.45);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            overflow: hidden;
        }

        .company-preview-brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .company-preview-brand-icon.fallback {
            font-size: 12px;
        }

        .company-preview-header-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .company-preview-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ffffff;
            opacity: .9;
        }

        .company-preview-chip-pill {
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 4px 10px;
            font-size: 10px;
        }

        .company-preview-sidebar {
            background: var(--company-sidebar);
            color: var(--contrast-sidebar, var(--company-text));
            padding: 14px;
            display: grid;
            align-content: start;
            gap: 8px;
            font-weight: 700;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .company-preview-menu-title {
            font-size: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.14);
            margin-bottom: 6px;
            text-align: center;
        }

        .company-preview-menu-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border-radius: 9px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 9px 10px;
            font-size: 11px;
            background: rgba(255, 255, 255, 0.08);
            cursor: pointer;
        }

        .company-preview-menu-item span:first-child {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .company-preview-menu-icon {
            color: var(--company-icon-color, var(--contrast-sidebar, var(--company-text)));
            width: 14px;
            text-align: center;
            font-size: 12px;
        }

        .company-preview-menu-item span:last-child {
            color: var(--company-icon-color, var(--contrast-sidebar, var(--company-text)));
            opacity: .95;
        }

        .company-preview-menu-item.active {
            background: var(--company-secondary);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .company-preview-main {
            padding: 10px;
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .company-preview-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 10px;
            border-radius: 16px;
            border: 1px solid var(--company-border);
            background:
                radial-gradient(circle at top right, color-mix(in srgb, var(--company-secondary) 18%, transparent), transparent 34%),
                linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 10%, #ffffff), #ffffff 48%, color-mix(in srgb, var(--company-background) 75%, #ffffff));
            padding: 12px;
            overflow: hidden;
        }

        .company-preview-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--company-primary) 10%, #ffffff);
            border: 1px solid var(--company-border);
            padding: 5px 10px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            color: var(--company-text);
            width: fit-content;
        }

        .company-preview-hero-copy {
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .company-preview-hero-title {
            margin: 0;
            font-size: 18px;
            line-height: 1.05;
            font-weight: 900;
            color: var(--company-title-color, var(--company-text));
        }

        .company-preview-hero-text {
            margin: 0;
            font-size: 11px;
            line-height: 1.45;
            color: var(--company-text);
            max-width: 52ch;
        }

        .company-preview-inline-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .company-preview-hero-link {
            font-size: 12px;
            font-weight: 800;
            color: var(--company-link-color, var(--company-primary));
            text-decoration: none;
        }

        .company-preview-hero-art {
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .company-preview-logo-card {
            border-radius: 16px;
            border: 1px solid var(--company-border);
            background: rgba(255, 255, 255, 0.86);
            padding: 12px;
            display: grid;
            grid-template-columns: 54px 1fr;
            gap: 12px;
            align-items: center;
        }

        .company-preview-logo-card .company-preview-brand-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: color-mix(in srgb, var(--company-primary) 16%, #ffffff);
            border-color: color-mix(in srgb, var(--company-primary) 24%, #ffffff);
        }

        .company-preview-logo-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            color: var(--company-text);
        }

        .company-preview-logo-subtitle {
            font-size: 11px;
            line-height: 1.5;
            color: var(--company-text);
            opacity: .8;
            margin-top: 2px;
        }

        .company-preview-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .company-preview-stat {
            border-radius: 14px;
            border: 1px solid var(--company-border);
            background: rgba(255, 255, 255, 0.9);
            padding: 12px;
            display: grid;
            gap: 4px;
        }

        .company-preview-stat-label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            opacity: .72;
        }

        .company-preview-stat-value {
            font-size: 18px;
            font-weight: 900;
            line-height: 1;
            color: var(--company-title-color, var(--company-text));
        }

        .company-preview-stat-note {
            font-size: 11px;
            color: var(--company-text);
            opacity: .8;
        }

        .company-preview-topbar {
            background: var(--company-navbar, #ffffff);
            color: var(--contrast-navbar, var(--company-text));
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid color-mix(in srgb, var(--company-navbar, #ffffff) 65%, var(--company-border));
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .company-preview-topbar-main {
            display: grid;
            gap: 6px;
            min-width: 0;
        }

        .company-preview-topbar-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .company-preview-selection-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            max-width: 100%;
            border-radius: 999px;
            padding: 4px 10px;
            border: 1px solid color-mix(in srgb, var(--contrast-navbar, #ffffff) 35%, transparent);
            background: color-mix(in srgb, var(--company-navbar, #ffffff) 55%, #ffffff);
            color: var(--contrast-navbar, var(--company-text));
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .2px;
            text-transform: none;
        }

        .company-preview-selection-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 260px;
        }

        .company-preview-selection-hex {
            font-family: Consolas, 'Courier New', monospace;
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--contrast-navbar, #ffffff) 28%, transparent);
            background: color-mix(in srgb, var(--company-navbar, #ffffff) 48%, #ffffff);
        }

        .company-preview-topbar-btn {
            border: 1px solid var(--company-button);
            border-radius: 999px;
            background: var(--company-button);
            color: var(--company-text);
            font-size: 10px;
            padding: 5px 10px;
        }

        .company-preview-form-strip {
            border: 1px solid var(--company-border);
            border-radius: 12px;
            background: color-mix(in srgb, var(--company-background) 88%, #ffffff);
            padding: 8px;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 8px;
        }

        .company-preview-form-field {
            display: grid;
            gap: 6px;
        }

        .company-preview-form-field label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .35px;
            color: var(--company-text);
            opacity: .82;
        }

        .company-preview-input,
        .company-preview-select {
            border: 1px solid color-mix(in srgb, var(--company-border) 80%, #d1d5db);
            border-radius: 10px;
            background: #ffffff;
            color: var(--company-text);
            padding: 8px 10px;
            font-size: 12px;
            outline: none;
            box-shadow: 0 0 0 0 color-mix(in srgb, var(--company-focus) 30%, transparent);
            transition: box-shadow .18s ease, border-color .18s ease;
        }

        .company-preview-input.is-focus,
        .company-preview-select.is-focus {
            border-color: var(--company-focus);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--company-focus) 26%, transparent);
        }

        .company-preview-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .company-preview-card {
            border: 1px solid var(--company-border);
            border-radius: 12px;
            background: #fff;
            padding: 10px;
            display: grid;
            gap: 6px;
            min-height: 82px;
        }

        .company-preview-card-label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            opacity: .7;
            color: var(--company-text);
        }

        .company-preview-card-value {
            font-size: 18px;
            font-weight: 900;
            color: var(--company-title-color, var(--company-text));
        }

        .company-preview-card-note {
            font-size: 11px;
            color: var(--company-text);
            opacity: .82;
            line-height: 1.45;
        }

        .company-preview-chip {
            width: 70%;
            height: 9px;
            border-radius: 999px;
            background: var(--company-secondary);
        }

        .company-preview-chip.muted {
            width: 48%;
            opacity: .4;
            background: var(--company-secondary);
        }

        .company-preview-table {
            border: 1px solid var(--company-border);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .company-preview-table-head {
            background: var(--company-table-header, var(--company-primary));
            color: var(--contrast-table-header, var(--company-text));
            display: grid;
            grid-template-columns: 0.8fr 1.8fr 1fr 1fr;
            gap: 8px;
            padding: 8px 10px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .company-preview-table-row {
            display: grid;
            grid-template-columns: 0.8fr 1.8fr 1fr 1fr;
            gap: 8px;
            padding: 8px 10px;
            align-items: center;
            border-top: 1px solid var(--company-border);
            font-size: 10px;
            color: var(--company-table-text, var(--company-text));
            background: var(--company-table-bg, #fff);
        }

        .color-section-body {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .company-preview-table-row:nth-child(even) {
            background: var(--company-table-row-alt, var(--company-background, #f4f6f8));
            color: var(--company-table-text, var(--company-text));
        }

        .company-preview-status {
            border-radius: 999px;
            background: var(--company-secondary);
            color: var(--company-text);
            padding: 3px 8px;
            width: fit-content;
            font-weight: 700;
        }

        .company-preview-actions {
            display: inline-flex;
            gap: 5px;
        }

        .company-preview-action-btn {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            border: 0;
            background: var(--company-btn-editar, var(--company-button));
            color: var(--contrast-btn-editar, var(--company-text));
        }

        .company-preview-action-btn.alt {
            background: var(--company-btn-eliminar, var(--company-secondary));
            color: var(--contrast-btn-eliminar, var(--company-text));
        }

        .company-preview-button {
            border: 0;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            color: var(--contrast-btn-crear, var(--company-text));
            padding: 9px 13px;
            width: fit-content;
            background: var(--company-btn-crear, var(--company-button));
        }

        @media (max-width: 1100px) {
            .company-colors-body {
                grid-template-columns: 1fr;
            }

            .company-colors-dialog {
                width: min(1100px, 98vw);
            }

            .company-preview-stage {
                grid-template-columns: 1fr;
            }

            .company-preview-sidebar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .preview-live-panel,
            .company-preview-hero {
                grid-template-columns: 1fr;
            }

            .color-section-body {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .company-preview-summary,
            .company-preview-stat-grid {
                grid-template-columns: 1fr;
            }

            .company-preview-topbar,
            .preview-live-meta {
                flex-direction: column;
                align-items: stretch;
            }

            .company-preview-sidebar {
                grid-template-columns: 1fr;
            }
        }

        .preview-top {
            background: var(--company-primary);
            color: var(--company-text);
            font-size: 11px;
            font-weight: 700;
            padding: 8px 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .preview-body {
            display: grid;
            grid-template-columns: 34% 1fr;
            min-height: 92px;
            background: var(--company-background);
        }

        .preview-side {
            background: var(--company-sidebar);
            color: var(--company-text);
            font-size: 10px;
            font-weight: 700;
            padding: 8px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .preview-main {
            padding: 10px;
            display: grid;
            gap: 8px;
            align-content: start;
        }

        .preview-chip {
            height: 8px;
            border-radius: 999px;
            background: var(--company-secondary);
        }

        .preview-button {
            border: 0;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 700;
            color: var(--company-text);
            padding: 8px 10px;
            width: fit-content;
            background: var(--company-button);
        }

        /* ===== PREVIEW ENFOCADO POR COMPONENTE ===== */

        #companyColorsPreview[data-highlight-target] .company-preview-header,
        #companyColorsPreview[data-highlight-target] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target] .company-preview-main > * {
            display: none;
        }

        #companyColorsPreview[data-highlight-target] .company-preview-stage {
            grid-template-columns: 1fr;
            grid-template-rows: auto;
            min-height: 280px;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        #companyColorsPreview[data-highlight-target] .company-preview-main {
            padding: 0;
            display: block;
            background: transparent;
        }

        #companyColorsPreview[data-highlight-target="branding"] .company-preview-header,
        #companyColorsPreview[data-highlight-target="sidebar"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="menu-icons"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="menu-active"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="navbar"] .company-preview-topbar,
        #companyColorsPreview[data-highlight-target="content"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="table-body"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-header"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-zebra"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-text"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="buttons"] .company-preview-summary,
        #companyColorsPreview[data-highlight-target="forms"] .company-preview-form-strip,
        #companyColorsPreview[data-highlight-target="surface"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="text"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="borders"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="accents"] .company-preview-summary {
            display: block;
        }

        #companyColorsPreview[data-highlight-target="branding"] .company-preview-header,
        #companyColorsPreview[data-highlight-target="navbar"] .company-preview-topbar {
            display: flex;
        }

        #companyColorsPreview[data-highlight-target="sidebar"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="menu-icons"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="menu-active"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="content"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="buttons"] .company-preview-summary,
        #companyColorsPreview[data-highlight-target="forms"] .company-preview-form-strip,
        #companyColorsPreview[data-highlight-target="surface"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="text"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="accents"] .company-preview-summary {
            display: grid;
        }

        #companyColorsPreview[data-highlight-target="branding"] .company-preview-header,
        #companyColorsPreview[data-highlight-target="sidebar"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="menu-icons"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="menu-active"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="navbar"] .company-preview-topbar,
        #companyColorsPreview[data-highlight-target="content"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="table-body"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-header"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-zebra"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="table-text"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="buttons"] .company-preview-summary,
        #companyColorsPreview[data-highlight-target="forms"] .company-preview-form-strip,
        #companyColorsPreview[data-highlight-target="surface"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="text"] .company-preview-hero,
        #companyColorsPreview[data-highlight-target="borders"] .company-preview-table,
        #companyColorsPreview[data-highlight-target="accents"] .company-preview-summary {
            border: 1px solid color-mix(in srgb, var(--company-primary) 24%, var(--company-border));
            border-radius: 12px;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--company-primary) 18%, transparent);
            background: #ffffff;
            padding: 12px;
        }

        #companyColorsPreview[data-highlight-target="menu-active"] .company-preview-menu-item.active,
        #companyColorsPreview[data-highlight-target="table-header"] .company-preview-table-head,
        #companyColorsPreview[data-highlight-target="table-zebra"] .company-preview-table-row:nth-child(even),
        #companyColorsPreview[data-highlight-target="table-text"] .company-preview-table-row,
        #companyColorsPreview[data-highlight-target="forms"] .company-preview-input.is-focus,
        #companyColorsPreview[data-highlight-target="forms"] .company-preview-select.is-focus {
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--company-primary) 35%, transparent);
            border-radius: 8px;
        }

        #companyColorsPreview[data-highlight-target="buttons"] .company-preview-summary {
            grid-template-columns: 1fr;
        }

        #companyColorsPreview[data-highlight-target="sidebar"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="menu-icons"] .company-preview-sidebar,
        #companyColorsPreview[data-highlight-target="menu-active"] .company-preview-sidebar {
            gap: 7px;
        }

        #companyColorsPreview[data-highlight-target="menu-icons"] .company-preview-menu-icon {
            transform: scale(1.15);
            filter: drop-shadow(0 0 4px color-mix(in srgb, var(--company-icon-color) 38%, transparent));
        }

        #companyColorsPreview[data-highlight-target="branding"] .company-preview-header {
            min-height: 66px;
        }

        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 2px 16px 8px 16px;
            min-height: calc(100vh - var(--topbar-height) - var(--topbar-gap) - var(--footer-height) + 20px);
            box-sizing: border-box;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
            gap: 12px;
            align-items: stretch;
            min-height: 0;
        }

        .menu-column {
            position: relative;
            min-width: 0;
            min-height: 0;
            height: 100%;
            padding: 16px 14px 12px;
            border: 1px solid var(--company-border);
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--company-primary) 10%, transparent);
            overflow-y: auto;
            overflow-x: hidden;
        }

        h2 {
            color: var(--company-text);
            text-align: center;
            margin: 30px 0;
            padding: 15px;
            background-color: transparent;
            border-radius: 10px;
            box-shadow: none;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Menu principal */
        .menu {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            background: #ffffff;
            padding: 18px;
            border-radius: 16px;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--company-primary) 10%, transparent);
            border: 1px solid var(--company-border);
            gap: 10px;
            margin: 0;
            height: 100%;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            z-index: 1;
            transition: padding 0.25s ease;
        }

        .sidebar-toggle-nav {
            width: 36px;
            height: 36px;
            border: 1px solid var(--company-border);
            border-radius: 10px;
            background: #ffffff;
            color: var(--company-text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 10;
        }

        .sidebar-toggle-nav:hover {
            background: var(--company-primary);
            color: var(--company-text);
            box-shadow: 0 6px 14px color-mix(in srgb, var(--company-primary) 20%, transparent);
        }

        .sidebar-toggle-nav:focus {
            outline: none;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--company-secondary) 28%, transparent);
        }

        .content-area {
            background: #ffffff;
            padding: 18px;
            min-height: 85vh;
            align-self: stretch;
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            box-sizing: border-box;
            border: 1px solid var(--company-border);
            border-radius: 14px;
            transition: padding 0.25s ease;
            overscroll-behavior: contain;
        }

        body.cliente-dashboard .container {
            padding: 2px 18px 8px;
        }

        body.cliente-dashboard .dashboard-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        body.cliente-dashboard .content-area {
            padding: 8px;
            height: calc(100vh - var(--topbar-height) - var(--topbar-gap) - var(--footer-height) + 8px);
            border-radius: 20px;
        }

        body.cliente-dashboard .workspace-module {
            border-radius: 18px;
            box-shadow: none;
        }

        body.sidebar-collapsed .dashboard-grid {
            grid-template-columns: var(--sidebar-collapsed-width) minmax(0, 1fr);
        }

        body.sidebar-collapsed .menu-column {
            padding: 12px 8px 10px 8px;
            padding-top: 52px;
        }

        body.sidebar-collapsed .sidebar-toggle-nav {
            right: 50%;
            transform: translateX(50%);
        }

        body.sidebar-collapsed .menu {
            padding: 4px 2px;
            gap: 6px;
        }

        body.sidebar-collapsed .menu-title {
            font-size: 0;
            padding: 8px;
            margin-bottom: 6px;
            min-height: 34px;
        }

        body.sidebar-collapsed .menu-title::after {
            display: none;
        }

        body.sidebar-collapsed .menu-title::before {
            content: 'MENU';
            font-size: 10px;
            letter-spacing: 0.8px;
            color: var(--company-text);
            font-weight: 700;
        }

        body.sidebar-collapsed .menu-item {
            justify-content: center;
            min-height: 34px;
            padding: 8px 6px;
            gap: 0;
        }

        body.sidebar-collapsed .menu-item-text,
        body.sidebar-collapsed .menu-item-label {
            display: none;
        }

        .content-area.module-open {
            padding: 12px;
            overflow: hidden;
        }

        @media (max-width: 1440px) and (min-width: 1024px) {
            .container {
                height: auto;
                min-height: calc(100vh - var(--topbar-height) - var(--topbar-gap) - var(--footer-height) + 20px);
                padding: 8px 12px 10px;
            }

            .dashboard-grid {
                grid-template-columns: 190px minmax(0, 1fr);
                gap: 10px;
            }

            .menu-column {
                padding: 12px 10px 10px;
            }

            .content-area {
                height: auto;
                min-height: 70vh;
                padding: 14px;
            }

            .content-hero {
                padding: 14px 16px;
                gap: 14px;
                flex-wrap: wrap;
            }

            .hero-title {
                font-size: 28px;
            }

            .hero-subtitle {
                font-size: 12px;
            }

            .card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .info-card {
                min-height: 250px;
            }

            .chart-body {
                min-height: 280px;
            }

            .chart-canvas-wrapper canvas {
                max-height: 280px;
            }

            .dashboard-month-filter {
                min-width: 150px;
            }
        }

        @media (max-width: 1366px) {
            .container {
                padding: 8px 10px 10px;
            }

            .topbar-inner {
                padding: 8px 12px;
            }

            .topbar-left {
                gap: 8px;
            }

            .topbar-time {
                font-size: 13px;
            }

            .content-hero {
                padding: 12px 14px;
            }

            .hero-title {
                font-size: 24px;
            }

            .hero-subtitle {
                font-size: 11px;
            }

            .card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .info-card {
                min-height: 220px;
                padding: 12px;
            }

            .chart-actions {
                gap: 6px;
            }

            .chart-toggle-btn {
                width: 32px;
                height: 32px;
            }
        }

        @media (max-width: 1024px) {
            .container {
                padding: 8px 10px 10px;
            }

            .dashboard-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .menu-column {
                height: auto;
                max-height: none;
            }

            .content-area {
                min-height: 60vh;
                max-height: none;
            }

            .content-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .card-grid {
                grid-template-columns: 1fr;
            }

            .info-card {
                min-height: 200px;
            }

            .topbar-inner {
                padding: 8px 10px;
            }

            .topbar-left {
                gap: 6px;
            }

            .topbar-time {
                font-size: 12px;
            }

            .topbar-company-media {
                width: 48px;
                min-width: 48px;
                height: 48px;
            }
        }

        .workspace-home {
            display: block;
            height: calc(100vh - var(--topbar-height) - var(--topbar-gap) - var(--footer-height) - 20px);
            max-height: none;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0 8px 48px 0;
            box-sizing: border-box;
        }

        .workspace-home::-webkit-scrollbar {
            width: 8px;
        }

        .workspace-home::-webkit-scrollbar-track {
            background: transparent;
        }

        .workspace-home::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }

        .workspace-home::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }

        .workspace-home.hidden {
            display: none;
        }

        .workspace-module {
            display: none;
            height: 100%;
            border: 1px solid var(--company-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--company-primary) 10%, transparent);
            background: #ffffff;
        }

        .workspace-module.active {
            display: block;
        }

        .module-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: #ffffff;
            display: block;
        }

        .content-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 16px 20px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--company-primary) 0%, var(--company-sidebar) 100%);
            box-shadow: 0 12px 28px color-mix(in srgb, var(--company-primary) 28%, transparent);
            margin-bottom: 14px;
            position: relative;
            overflow: hidden;
            border: 1px solid color-mix(in srgb, #ffffff 24%, var(--company-border));
        }

        .workspace-home,
        .workspace-home * {
            text-transform: uppercase !important;
        }

        .content-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1), transparent 60%);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-20px, 20px) scale(1.05); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            
            font-size: 35px;
            font-weight: 800;
            margin: 2px 0 4px 0;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-shadow: 0 3px 10px rgba(0,0,0,0.25);
        }

        .hero-subtitle {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.95);
            margin: 0;
            font-weight: 600;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.22);
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.35);
            position: relative;
            z-index: 1;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 0;
            align-items: stretch;
            grid-auto-rows: 1fr;
        }

        .info-card {
            border: 1px solid var(--company-border);
            border-radius: 16px;
            padding: 16px;
            background: #ffffff;
            box-shadow: 0 8px 20px color-mix(in srgb, var(--company-primary) 8%, transparent);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            min-height: 320px;
            max-height: 380px;
           
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .info-card > *:not(h3):not(.chart-body) {

            max-height: 320px;
        }

        .chart-actions,
        .chart-list-wrapper {
            overflow: visible;
        }

        .info-card > *:not(h3)::-webkit-scrollbar {
            width: 4px;
        }

        .info-card > *:not(h3)::-webkit-scrollbar-track {
            background: transparent;
        }

        .info-card > *:not(h3)::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--company-primary) 20%, transparent);
            border-radius: 10px;
        }

        .info-card > *:not(h3)::-webkit-scrollbar-thumb:hover {
            background: color-mix(in srgb, var(--company-primary) 40%, transparent);
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--company-primary), var(--company-tertiary));
            transform: scaleY(0);
            transform-origin: top;
            transition: transform 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 32px color-mix(in srgb, var(--company-primary) 15%, transparent);
        }

        .info-card:hover::before {
            transform: scaleY(1);
        }

        .info-card h3 {
            font-size: 11px;
            margin: 0 0 12px 0;
            color: var(--company-text);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--company-border);
        }

        .chart-toggle-group {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .chart-toggle-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid color-mix(in srgb, var(--company-primary) 26%, transparent);
            background: #ffffff;
            color: var(--company-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chart-toggle-btn:hover {
            background: var(--company-primary);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .dashboard-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 12px;
        }

        .dashboard-filter-label {
            font-size: 0.92rem;
            font-weight: 700;
            color: rgb(253, 253, 253);
            letter-spacing: 0.02em;
        }

        .dashboard-month-filter {
            min-width: 180px;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff !important;
            color: #0f172a;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .dashboard-month-filter:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        .chart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            gap: 8px;
            flex-wrap: wrap;
            overflow: visible;
            min-height: 34px;
        }

        .chart-selection-center {
          
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 0;
            min-height: 34px;
            position: relative;
            flex-direction: column;
        }

        .chart-selection-badge {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 28px;
            max-width: 220px;
            padding: 5px 10px;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: rgba(255, 255, 255, 0.98);
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 2px 8px rgba(2, 6, 23, 0.08);
            position: relative;
        }

        .chart-selection-badge.is-visible {
            display: inline-flex;
            border-color: #111827;
            box-shadow: inset 0 0 0 1px rgba(17, 24, 39, 0.16);
        }

        .chart-list-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }


        .chart-selection-badge .badge-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .chart-selection-badge .badge-text {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chart-movimientos-header-controls,
        .chart-movimientos-header-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .chart-movimientos-header-wrap {
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--company-border);
        }

        .chart-movimientos-filter-inline {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .chart-selection-badge .badge-value {
            margin-left: 4px;
            color: #334155;
            font-weight: 700;
        }

        .chart-selection-badge.is-visible .badge-text {
            color: #f7c948;
            font-weight: 800;
        }

        .chart-body {
            display: flex;
            flex-direction: column;
            gap: 0;
            align-items: stretch;
            justify-content: flex-start;
            position: relative;
            overflow: hidden;
            flex: 1 1 auto;
            min-height: 0;
            max-height: 220px;
        }

        .chart-body.chart-body-bar-mode {
            max-height: 400px;
        }

        .chart-inline-list-panel-selected {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            color: #0f172a;
            font-weight: 700;
        }

        .chart-inline-list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .chart-inline-list-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-close-panel-btn {
            border: none;
            background: transparent;
            color: #334155;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .chart-close-panel-btn:hover {
            background: rgba(15, 23, 42, 0.08);
            color: #111827;
        }

        /* per-item clear button removed by UX */

        .chart-canvas-wrapper {
            flex: 1 1 auto;
            min-width: 0;
            min-height: 0;
            width: 100%;
            max-width: 100%;
            margin: 0;
            overflow: hidden;
            max-height: 220px;
            padding-right: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            scrollbar-width: thin;
            scrollbar-color: color-mix(in srgb, var(--company-primary) 25%, transparent) transparent;
        }

        .chart-canvas-wrapper.chart-wrapper-fixed-height {
            height: 220px;
            max-height: 220px;
        }

        .chart-canvas-wrapper::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .chart-canvas-wrapper::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--company-primary) 25%, transparent);
            border-radius: 999px;
        }

        .chart-canvas-wrapper canvas {
            width: 100% !important;
            height: auto !important;
            display: block;
            min-height: 0;
            max-width: 100%;
            max-height: 220px;
            object-fit: contain;
            margin: 0 auto;
        }

        .chart-canvas-wrapper.chart-circle-mode {
            max-height: 200px;
            align-items: center;
            justify-content: center;
        }

        .chart-canvas-wrapper.chart-circle-mode canvas {
            width: 200px !important;
            height: 200px !important;
            max-height: 200px;
        }

        .chart-canvas-wrapper.chart-bar-mode {
            height: 400px;
            max-height: 400px;
            overflow-y: auto;
            align-items: flex-start;
            justify-content: flex-start;
        }

        .chart-canvas-wrapper.chart-bar-mode canvas {
            max-height: none;
        }

        .chart-list-panel-wrapper {
            position: absolute;
            top: 8px;
            right: 0;
            width: min(320px, 100%);
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-end;
            justify-content: flex-start;
            z-index: 30;
            pointer-events: none;
            overflow: visible;
        }

        .chart-list-panel-wrapper > .chart-inline-list-panel {
            pointer-events: auto;
            width: 100%;
            min-width: 240px;
            margin-top: 6px;
        }

        .chart-list-btn {
            background: #2f4a5a;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 9px 14px;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
            font-size: 13px;
            font-weight: 600;
            width: 100%;
            min-height: 36px;
        }

        .chart-inline-list-panel {
            position: relative;
            top: 0;
            right: 0;
            width: min(320px, 100%);
            max-height: min(80vh, 420px);
            box-sizing: border-box;
            overflow-y: auto;
            overflow-x: hidden;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(203, 213, 225, 0.95);
            border-radius: 16px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18);
            padding: 16px 16px 14px;
            display: none;
            z-index: 30;
            pointer-events: auto;
        }

        .chart-inline-list-panel.active {
            display: block;
        }

        .chart-inline-list-panel[aria-hidden="true"] {
            display: none;
        }

        .chart-inline-list-title {
            font-size: 10px;
            font-weight: 800;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 10px;
        }

        .chart-inline-list-group {
            display: grid;
            margin-bottom: 12px;
            row-gap: 8px;
        }

        .chart-inline-list-group-title {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .chart-inline-list-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            padding: 10px 12px;
            min-height: 42px;
            border: 1px solid transparent;
            border-radius: 12px;
            font-size: 11px;
            color: #374151;
            cursor: pointer;
            transition: background-color 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
            overflow: hidden;
            background: transparent;
            width: 100%;
            box-sizing: border-box;
        }

        .chart-inline-list-item:hover {
            background: rgba(47, 74, 90, 0.05);
            border-color: rgba(47, 74, 90, 0.18);
        }

        .chart-inline-list-item.is-selected {
            background: rgba(255, 255, 255, 0.98);
            border-color: rgba(17, 24, 39, 0.12);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            transform: none;
        }

        .chart-inline-list-item-main {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            overflow: hidden;
        }

        .product-color-chip {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            border: 1px solid rgba(15, 23, 42, 0.12);
            flex: 0 0 auto;
        }

        .product-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            word-break: break-word;
        }

        .product-value {
            font-size: 11px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: right;
        }

        .chart-list-btn:hover {
            background: #1f3447;
            transform: translateY(-1px);
        }

        .chart-list-modal {
            position: fixed;
            inset: 0;
            background: rgba(18, 24, 39, 0.72);
            display: none;
            justify-content: center;
            align-items: center;
            padding: 18px;
            z-index: 1200;
        }

        .chart-list-modal.active {
            display: flex;
        }

        body.modal-open {
            overflow: hidden;
        }

        .modal-chart-list {
            max-width: 700px;
            width: 100%;
            max-height: 86vh;
            overflow: hidden;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 24px 68px rgba(0, 0, 0, 0.18);
        }

        .chart-list-body {
            padding: 18px;
            overflow-y: auto;
            max-height: calc(86vh - 86px);
            background: #f8fafc;
        }

        .chart-list-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #e6ebf3;
            margin-bottom: 10px;
        }

        .product-color-chip {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }

        .product-label {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        .product-value {
            font-size: 13px;
            color: #4b5563;
            white-space: nowrap;
        }

        .chart-list-empty {
            padding: 24px;
            text-align: center;
            color: #475569;
            font-size: 14px;
        }

        .modal-chart-list .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 18px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-chart-list .modal-header h2 {
            margin: 0;
            font-size: 17px;
            letter-spacing: 0.2px;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-chart-list .close-btn {
            background: transparent;
            border: none;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
            color: #374151;
        }

        .modal-chart-list .close-btn:hover {
            color: #111827;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 11px;
            border-radius: 12px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 10%, transparent), color-mix(in srgb, var(--company-primary) 5%, transparent));
            border: 1px solid color-mix(in srgb, var(--company-primary) 16%, transparent);
            font-size: 11px;
            font-weight: 600;
            color: var(--company-text);
            transition: all 0.2s ease;
            cursor: default;
            margin-bottom: 6px;
            width: 100%;
            box-sizing: border-box;
        }

        .chip:hover {
            background: linear-gradient(135deg, var(--company-primary), var(--company-tertiary));
            color: var(--company-text);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px color-mix(in srgb, var(--company-primary) 20%, transparent);
        }

        .chart-toggle-btn {
            background: #2f4a5a;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 8px;
            margin: 0 2px;
            cursor: pointer;
            font-size: 14px;
        }
        .chart-toggle-btn:hover {
            background: #1a2d4f;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .stats-card {
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 5%, transparent), color-mix(in srgb, var(--company-secondary) 6%, transparent));
        }

        .stat-display {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 9px 10px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid var(--company-border);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateX(4px);
            box-shadow: 0 6px 16px color-mix(in srgb, var(--company-primary) 10%, transparent);
        }

        .stat-icon-small {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--company-primary), var(--company-tertiary));
            color: var(--company-text);
            border-radius: 10px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stat-info {
            flex: 1;
        }

        .stat-label {
            font-size: 11px;
            color: var(--company-text);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }

        .stat-number {
            font-size: 18px;
            font-weight: 700;
            color: var(--company-text);
        }

        .activity-card {
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-secondary) 6%, transparent), color-mix(in srgb, var(--company-primary) 5%, transparent));
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 10px;
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid var(--company-border);
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px color-mix(in srgb, var(--company-primary) 10%, transparent);
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--company-primary), var(--company-tertiary));
            color: var(--company-text);
            border-radius: 8px;
            font-size: 16px;
            flex-shrink: 0;
        }

        .activity-icon.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--company-text);
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 11px;
            color: var(--company-text);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-area {
            animation: fadeInUp 0.6s ease;
        }

        .card-grid .info-card {
            animation: fadeInUp 0.6s ease;
        }

        .card-grid .info-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .card-grid .info-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .card-grid .info-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .card-grid .info-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .menu-title {
            animation: fadeInUp 0.5s ease;
        }

        .menu-column .menu-item {
            animation: fadeInUp 0.5s ease;
        }

        .menu-column .menu-item:nth-child(2) { animation-delay: 0.05s; }
        .menu-column .menu-item:nth-child(3) { animation-delay: 0.1s; }
        .menu-column .menu-item:nth-child(4) { animation-delay: 0.15s; }
        .menu-column .menu-item:nth-child(5) { animation-delay: 0.2s; }
        .menu-column .menu-item:nth-child(6) { animation-delay: 0.25s; }
        .menu-column .menu-item:nth-child(7) { animation-delay: 0.3s; }
        .menu-column .menu-item:nth-child(8) { animation-delay: 0.35s; }
        .menu-column .menu-item:nth-child(9) { animation-delay: 0.4s; }
        .menu-column .menu-item:nth-child(10) { animation-delay: 0.45s; }

        .chip i {
            transition: transform 0.2s ease;
        }

        .chip:hover i {
            transform: scale(1.2);
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 1;
                transform: scale(1);
            }
            50% { 
                opacity: 0.7;
                transform: scale(1.1);
            }
        }

        .pulse-icon {
            animation: pulse 2s ease-in-out infinite;
        }

        .status-online {
            color: #28a745;
            filter: drop-shadow(0 0 4px rgba(40, 167, 69, 0.6));
        }

        .rol-filter-btn {
            transition: all 0.2s ease;
        }

        .rol-filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            opacity: 0.9;
        }

        .rol-filter-btn.active {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transform: translateY(-1px);
            font-weight: 700;
        }

        .rol-filter-btn .badge-count {
            background: rgba(255, 255, 255, 0.3);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            margin-left: 2px;
        }

        .rol-filter-btn.active .badge-count {
            background: rgba(255, 255, 255, 0.5);
        }

        .rol-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }

        .rol-modal-overlay.active {
            display: flex;
        }

        .rol-modal-card {
            background: #ffffff;
            width: min(820px, 94vw);
            max-height: 88vh;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.35);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .rol-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: color-mix(in srgb, var(--company-primary) 5%, #ffffff);
            border-bottom: 1px solid var(--company-border);
        }

        .rol-modal-header h3 {
            margin: 0;
            font-size: 15px;
            color: var(--company-text);
        }

        .rol-modal-close {
            background: transparent;
            border: none;
            font-size: 22px;
            color: var(--company-text);
            cursor: pointer;
        }

        .rol-modal-body {
            padding: 16px 18px 18px;
            overflow-y: auto;
        }

        .rol-modal-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .rol-modal-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: color-mix(in srgb, var(--company-primary) 5%, #ffffff);
        }

        .rol-modal-name {
            font-weight: 700;
            font-size: 13px;
            color: var(--company-text);
        }

        .rol-modal-role {
            font-size: 10px;
            font-weight: 600;
            margin-top: 2px;
        }

        .rol-modal-meta {
            text-align: right;
            font-size: 10px;
            color: var(--company-text);
        }

        .rol-modal-duration {
            color: #63a375;
            font-weight: 600;
        }

        body {
            padding-bottom: 60px;
        }

        .menu-column::-webkit-scrollbar {
            width: 6px;
        }

        .menu-column::-webkit-scrollbar-track {
            background: transparent;
        }

        .menu-column::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--company-primary) 30%, transparent);
            border-radius: 10px;
        }

        .menu-column::-webkit-scrollbar-thumb:hover {
            background: color-mix(in srgb, var(--company-primary) 50%, transparent);
        }

        .content-area {
            overflow: hidden;
        }

        .welcome-title {
            font-size: 20px;
            color: var(--company-text);
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .welcome-text {
            font-size: 14px;
            color: var(--company-text);
            margin: 0;
        }

        .profile-panel {
            display: none;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--company-border);
            margin-bottom: 16px;
        }

        .profile-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--company-primary) 12%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--company-text);
            font-size: 22px;
            font-weight: 700;
        }

        .profile-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--company-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-role {
            font-size: 13px;
            color: var(--company-text);
            margin-top: 4px;
        }

        .profile-time {
            background: color-mix(in srgb, var(--company-primary) 4%, #ffffff);
            border: 1px solid var(--company-border);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            margin-bottom: 16px;
        }

        .profile-time .time {
            font-size: 18px;
            font-weight: 700;
            color: var(--company-text);
        }

        .profile-time .date {
            font-size: 13px;
            color: var(--company-text);
            margin-top: 4px;
        }

        .profile-details {
            display: grid;
            gap: 12px;
            margin-bottom: 18px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid var(--company-border);
            border-radius: 10px;
            background: #fff;
        }

        .detail-item i {
            color: var(--company-text);
            width: 18px;
            text-align: center;
        }

        .detail-label {
            font-size: 11px;
            color: var(--company-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--company-text);
        }

        /* Eliminar la tarjeta de estadisticas redundante */
        .stats-cards {
            display: none;
        }

        footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: var(--footer-height);
            padding: 0 18px;
            box-sizing: border-box;
            background: linear-gradient(135deg, var(--company-primary), var(--company-tertiary));
            color: var(--company-text);
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 -4px 16px color-mix(in srgb, var(--company-primary) 20%, transparent);
            z-index: 120;
            pointer-events: none;
        }

        .footer-brand-logo {
            width: 16px;
            height: 16px;
            object-fit: contain;
            flex: 0 0 16px;
            display: block;
            border-radius: 4px;
        }

        .footer-wordmark {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            line-height: 1;
        }

        footer p {
            margin: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            max-width: calc(100vw - 40px);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            line-height: 1;
            white-space: nowrap;
        }

        /* Actualizar el responsive */
        @media (max-width: 1024px) {
            :root {
                --topbar-gap: 14px;
                --footer-height: 42px;
                --sidebar-width: 260px;
            }

            .container {
                padding: 32px 12px 8px 22px;
            }

            .dashboard-grid {
                gap: 10px;
            }

            .menu-column {
                padding: 16px 12px;
                overflow-y: auto;
            }

            .content-area {
                padding: 18px;
                overflow-y: auto;
                width: calc(100% - 36px);
                margin: 0 auto;
            }

            .card-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .workspace-module {
                height: calc(100vh - 71px - 110px);
            }

            .chart-actions {
                gap: 6px;
                margin-bottom: 6px;
                min-height: 32px;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
            }

            .chart-toggle-group {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-bottom: 0;
            }

            .chart-toggle-btn {
                width: 30px;
                height: 30px;
                min-width: 30px;
            }

            .chart-list-wrapper {
                margin-left: auto;
                display: flex;
                align-items: center;
            }

            .chart-list-btn {
                padding: 6px 10px;
                font-size: 11px;
                min-height: 30px;
                width: auto;
                min-width: 72px;
                white-space: nowrap;
            }

            .chart-selection-center {
                min-height: 28px;
                flex: 1 1 auto;
                display: flex;
                justify-content: center;
                align-items: center;
                width: 100%;
            }

            .chart-list-panel-wrapper {
                position: relative;
                top: auto;
                right: auto;
                width: 100%;
                align-items: stretch;
                margin-top: 8px;
                margin-bottom: 6px;
                order: -1;
                pointer-events: none;
            }

            .chart-list-panel-wrapper > .chart-inline-list-panel {
                pointer-events: auto;
                width: 100%;
                min-width: auto;
                margin-top: 0;
            }

            .chart-inline-list-panel {
                max-height: 260px;
                padding: 10px;
            }

            .chart-inline-list-item {
                padding: 8px 10px;
                min-height: 36px;
                font-size: 10px;
            }
        }

        @media (max-width: 768px) {
            :root {
                --topbar-gap: 10px;
                --footer-height: 40px;
            }

            .sidebar-toggle,
            .sidebar-toggle-nav {
                display: none;
            }

            .topbar-left {
                min-width: 0;
            }

            .container {
                padding: 26px 10px 8px 14px;
                height: auto;
            }

            .dashboard-grid {
                display: block;
                height: auto;
                overflow: auto;
            }

            .menu-column {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
                left: 0;
                border-right: none;
                border-bottom: 2px solid var(--company-border);
                padding: 12px;
                overflow: visible;
            }

            .menu {
                overflow: visible;
            }

            .content-area {
                padding: 16px;
                width: 100%;
                height: auto;
                margin-left: 0;
                overflow: visible;
                border-radius: 12px;
            }

            .content-area.module-open {
                padding: 10px;
                overflow: hidden;
            }

            .topbar-inner {
                padding: 12px 20px;
            }

            .card-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            footer {
                margin-left: 0;
            }

            footer p {
                gap: 6px;
                font-size: 12px;
                max-width: calc(100vw - 24px);
            }

            .chart-actions {
                gap: 8px;
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-start;
                margin-bottom: 8px;
                min-height: auto;
                flex-wrap: wrap;
                overflow: visible;
                padding-bottom: 0;
            }

            .chart-toggle-group {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                justify-content: flex-start;
                min-width: 0;
                margin-bottom: 0;
            }

            .chart-toggle-btn {
                width: 32px;
                height: 32px;
                flex: 0 0 auto;
            }

            .chart-list-wrapper {
                display: flex;
                justify-content: flex-end;
                width: 100%;
                margin-left: 0;
                min-width: 0;
            }

            .chart-list-btn {
                padding: 6px 10px;
                font-size: 11px;
                min-height: 30px;
                width: auto;
                min-width: 72px;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .chart-selection-center {
                flex: 0 1 auto;
                min-height: 24px;
                display: flex;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 0;
            }

            .chart-list-panel-wrapper {
                position: relative;
                top: auto;
                right: auto;
                width: 100%;
                align-items: stretch;
                margin-top: 6px;
                margin-bottom: 6px;
                order: -1;
                pointer-events: none;
            }

            .chart-list-panel-wrapper > .chart-inline-list-panel {
                pointer-events: auto;
                width: 100%;
                min-width: auto;
                margin-top: 0;
            }

            .chart-inline-list-panel {
                max-height: 260px;
                padding: 10px;
            }

            .chart-inline-list-item {
                padding: 7px 9px;
                min-height: 34px;
                font-size: 10px;
            }
        }

        @media (max-width: 480px) {
            .content-area {
                padding: 16px;
                margin-left: 0;
            }

            .content-hero {
                padding: 24px 20px;
                flex-direction: column;
                text-align: center;
            }

            .card-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .info-card {
                min-height: auto;
            }

            .menu-item {
                font-size: 12px;
                padding: 12px 14px;
            }

            .menu-column {
                padding: 16px;
                position: relative;
                width: 100%;
            }

            .chip {
                padding: 8px 12px;
                font-size: 12px;
            }

            .stat-item {
                padding: 12px;
                gap: 10px;
            }

            .stat-icon-small {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }

            footer {
                margin-left: 0;
                padding: 6px 10px;
                height: auto;
                min-height: var(--footer-height);
            }

            footer p {
                white-space: normal;
                flex-wrap: wrap;
                text-align: center;
                row-gap: 4px;
                font-size: 11px;
                line-height: 1.2;
                max-width: calc(100vw - 20px);
            }

            .footer-wordmark {
                gap: 4px;
            }
        }

        /* Estilos actualizados para el carrusel */
        .equipos-slider {
            background: transparent;
            padding: 30px;
            margin: 20px auto 40px;
            border-radius: 20px;
            box-shadow: none;
            overflow: hidden;
            max-width: 800px;
            width: 90%;
            border: 2px solid var(--company-tertiary);
            position: relative;
        }

        /* Decoracion de esquinas */
        .equipos-slider:before,
        .equipos-slider:after {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            border: 3px solid var(--company-tertiary);
            opacity: 0.1;
        }

        .equipos-slider:before {
            top: -10px;
            left: -10px;
            border-right: none;
            border-bottom: none;
            border-radius: 20px 0 0 0;
        }

        .equipos-slider:after {
            bottom: -10px;
            right: -10px;
            border-left: none;
            border-top: none;
            border-radius: 0 0 20px 0;
        }

        .equipos-titulo {
            color: var(--company-text);
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8em;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }

        .equipos-titulo:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--company-secondary), transparent);
            border-radius: 2px;
        }

        .equipo-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            opacity: 0;
            transform: translateX(100%);
            position: absolute;
            left: 0;
            right: 0;
            height: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--company-primary) 8%, transparent);
            transition: all 1.2s cubic-bezier(0.42, 0, 0.58, 1);
            will-change: transform, opacity;
            margin: 0 auto;
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--company-border);
        }

        .equipo-logo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 15px;
            border: 4px solid var(--company-tertiary);
            padding: 5px;
            background: white;
            object-fit: cover;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--company-primary) 20%, transparent);
            transition: all 0.3s ease;
        }

        .equipo-card:hover .equipo-logo {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 8px 18px color-mix(in srgb, var(--company-primary) 30%, transparent);
        }

        .equipo-nombre {
            color: var(--company-text);
            font-weight: 800;
            margin: 15px 0;
            font-size: 22px;
            text-align: center;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .equipo-stats {
            font-size: 0.9em;
            color: var(--company-text);
        }

        .mensaje-info, .mensaje-error {
            width: 100%;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            border: 2px solid;
            background: transparent;
            font-weight: 600;
        }

        .mensaje-info {
            border-color: var(--company-primary);
            color: var(--company-text);
        }

        .mensaje-error {
            border-color: #dc3545;
            color: #dc3545;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .equipos-slider {
                padding: 20px;
                margin: 15px auto 50px;
            }

            .equipos-container {
                min-height: 300px;
            }

            .equipo-card {
                height: 280px;
                padding: 20px;
            }

            .equipo-logo {
                width: 150px;
                height: 150px;
            }

            .equipo-nombre {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .equipos-slider {
                width: 95%;
                padding: 15px;
            }

            .equipo-logo {
                width: 130px;
                height: 130px;
            }
        }

        /* Estilos para el modal de torneos */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: color-mix(in srgb, var(--company-sidebar) 50%, transparent);
            backdrop-filter: blur(4px);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            width: 80%;
            max-width: 500px;
            border-radius: 14px;
            box-shadow: 0 12px 36px color-mix(in srgb, var(--company-primary) 15%, transparent);
            animation: slideIn 0.4s ease;
            text-align: center;
            border: 1px solid var(--company-border);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-close {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            font-size: 20px;
            color: var(--company-text);
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background-color: var(--company-button);
            color: white;
            transform: rotate(90deg);
        }

        .modal-content h2 i {
            margin-right: 10px;
            color: var(--company-text);
        }

        .modal-content h2 {
            font-size: 1.8em;
            margin-bottom: 30px;
            color: var(--company-text);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .torneo-selector {
            margin: 30px 0;
            position: relative;
        }

        .torneo-selector::after {
            content: '';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid var(--company-secondary);
            pointer-events: none;
        }

        .torneo-selector select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--company-border);
            border-radius: 8px;
            font-size: 16px;
            appearance: none;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--company-text);
            font-weight: 600;
            box-shadow: 0 2px 4px color-mix(in srgb, var(--company-primary) 5%, transparent);
            text-transform: uppercase;
        }

        .torneo-selector select:hover {
            border-color: var(--company-secondary);
            box-shadow: 0 4px 8px color-mix(in srgb, var(--company-primary) 15%, transparent);
        }

        .torneo-selector select:focus {
            outline: none;
            border-color: var(--company-secondary);
            box-shadow: 0 6px 18px color-mix(in srgb, var(--company-primary) 20%, transparent);
        }

        .torneo-selector select option {
            padding: 12px;
            font-size: 15px;
            background-color: white;
            color: var(--company-text);
        }

        .torneo-selector select option:hover {
            background-color: var(--company-button);
            color: white;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 90%;
                margin: 20% auto;
                padding: 15px;
            }

            .modal-content h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            .torneo-selector select {
                font-size: 14px;
                padding: 10px;
            }
        }

        /* Animaciones del carrusel */
        .equipo-card {
            position: absolute;
            left: 0;
            right: 0;
            opacity: 0;
            transform: translateX(100%);
            transition: all 1.2s cubic-bezier(0.42, 0, 0.58, 1);
        }

        .equipo-card.entering {
            opacity: 0;
            transform: translateX(100%);
        }

        .equipo-card.active {
            opacity: 1;
            transform: translateX(0);
        }

        .equipo-card.leaving {
            opacity: 0;
            transform: translateX(-100%);
        }

        .equipos-container {
            position: relative;
            min-height: 350px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .btn-logout {
            color: var(--company-text);
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: transparent;
            padding: 10px 20px;
            border-radius: 8px;
            border: 2px solid var(--company-tertiary);
            transition: all 0.3s ease;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 100%;
            justify-content: center;
        }

        .btn-logout:hover {
            background-color: var(--company-button);
            color: white;
            text-decoration: none;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--company-primary) 30%, transparent);
            transform: translateY(-2px);
        }

        .btn-logout i {
            font-size: 16px;
        }

        /* ===== ACABADO VISUAL DEL DASHBOARD ===== */

        body {
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--company-primary) 9%, transparent), transparent 32%),
                radial-gradient(circle at top right, color-mix(in srgb, var(--company-secondary) 10%, transparent), transparent 28%),
                linear-gradient(180deg, color-mix(in srgb, var(--company-background) 82%, #ffffff), #ffffff 52%, color-mix(in srgb, var(--company-background) 70%, #ffffff));
        }

        .menu-column {
            background: rgba(255, 255, 255, 0.82);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            padding: 8px 7px 6px;
        }

        .menu {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            padding: 4px 2px !important;
            gap: 6px !important;
        }

        .menu-title {
            background: #ffffff !important;
            color: var(--company-title-color, var(--company-text)) !important;
            border: 1px solid color-mix(in srgb, var(--company-primary) 12%, var(--company-border)) !important;
            border-radius: 12px !important;
            box-shadow: 0 6px 14px color-mix(in srgb, var(--company-primary) 8%, transparent) !important;
            min-height: 34px;
            padding: 6px 7px 6px 11px !important;
            font-size: 11.5px !important;
            line-height: 1;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 6px;
            box-sizing: border-box;
            cursor: pointer;
        }

        .menu-title-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .menu-title::after,
        .menu-item::before,
        .content-hero::before,
        .profile-toggle::before,
        .equipos-slider::before,
        .equipos-slider::after {
            display: none !important;
        }

        .menu-item,
        .menu-item-store {
            background: #ffffff !important;
            color: var(--company-text) !important;
            border: 1px solid color-mix(in srgb, var(--company-primary) 12%, var(--company-border)) !important;
            border-radius: 11px !important;
            min-height: 34px;
            padding: 6px 10px !important;
            box-shadow: 0 3px 8px color-mix(in srgb, var(--company-primary) 5%, transparent) !important;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease !important;
            gap: 8px;
            font-size: 11.5px !important;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .menu-item i,
        .menu-item-store i {
            color: var(--company-primary);
            width: 16px;
            text-align: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .menu-item-text,
        .menu-item-label {
            font-size: 11.5px !important;
            line-height: 1.2;
            letter-spacing: .15px;
        }

        .menu-item:hover,
        .menu-item.active,
        .menu-item-store:hover {
            background: color-mix(in srgb, var(--company-primary) 10%, #ffffff) !important;
            color: var(--company-text) !important;
            border-color: color-mix(in srgb, var(--company-primary) 30%, var(--company-border)) !important;
            box-shadow: 0 6px 14px color-mix(in srgb, var(--company-primary) 10%, transparent) !important;
            transform: translateX(2px);
        }

        .sidebar-toggle-nav {
            position: static !important;
            top: auto !important;
            right: auto !important;
            width: 24px !important;
            height: 24px !important;
            border-radius: 8px;
            box-shadow: 0 3px 8px color-mix(in srgb, var(--company-primary) 8%, transparent);
            flex-shrink: 0;
            font-size: 11px;
        }

        body.sidebar-collapsed .menu-column {
            padding: 8px 5px 6px 5px;
        }

        body.sidebar-collapsed .sidebar-toggle-nav {
            width: 24px !important;
            height: 24px !important;
            border-radius: 8px;
        }

        body.sidebar-collapsed .menu {
            padding: 4px 2px !important;
        }

        body.sidebar-collapsed .menu-title {
            min-height: 32px;
            padding: 5px !important;
            justify-content: center !important;
            font-size: 11px !important;
            margin-bottom: 3px;
        }

        body.sidebar-collapsed .menu-title-text {
            display: none !important;
        }

        body.sidebar-collapsed .menu-title::before {
            display: none !important;
            content: none !important;
        }

        body.sidebar-collapsed .menu-item,
        body.sidebar-collapsed .menu-item-store {
            justify-content: center !important;
            min-height: 32px !important;
            padding: 5px !important;
            gap: 0 !important;
        }

        body.sidebar-collapsed .menu-item i,
        body.sidebar-collapsed .menu-item-store i {
            font-size: 14px;
            width: auto;
        }

        .topbar {
            background: rgba(255, 255, 255, 0.84);
            -webkit-backdrop-filter: blur(14px);
            backdrop-filter: blur(14px);
        }

        .profile-toggle,
        .logout-item,
        .profile-action-btn,
        .btn-logout {
            background: #ffffff !important;
            color: var(--company-text) !important;
            border: 1px solid color-mix(in srgb, var(--company-primary) 14%, var(--company-border)) !important;
            box-shadow: 0 8px 18px color-mix(in srgb, var(--company-primary) 9%, transparent) !important;
        }

        .content-area,
        .workspace-module,
        .info-card,
        .errors-file-block,
        .rol-modal-item {
            background: rgba(255, 255, 255, 0.92) !important;
            box-shadow: 0 12px 28px color-mix(in srgb, var(--company-primary) 8%, transparent) !important;
        }

        .content-hero {
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 92%, #ffffff), color-mix(in srgb, var(--company-sidebar) 88%, #ffffff)) !important;
            border-radius: 22px !important;
        }

        .company-colors-dialog {
            width: min(1580px, 98vw);
            max-height: 95vh;
            background: linear-gradient(180deg, #f8fbfe 0%, #eef4f8 100%);
            border-radius: 24px;
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.28);
        }

        .company-colors-header {
            background: #ffffff !important;
            color: var(--company-text) !important;
            border-bottom: 1px solid color-mix(in srgb, var(--company-primary) 10%, var(--company-border));
            padding: 18px 20px;
        }

        .company-colors-header h3 {
            font-size: 18px;
            color: var(--company-title-color, var(--company-text));
        }

        .company-colors-header p {
            font-size: 12px;
            opacity: .78;
        }

        .company-colors-close {
            border-color: color-mix(in srgb, var(--company-primary) 14%, var(--company-border));
            background: #ffffff;
            color: var(--company-primary);
        }

        .company-colors-body {
            grid-template-columns: minmax(600px, 0.8fr) minmax(720px, 1.2fr);
            gap: 6px;
            padding: 14px;
        }

        .company-colors-controls {
            padding: 10px;
        }

        .colors-form {
            gap: 14px;
        }

        .company-colors-preview {
            min-height: 500px;
            max-width: none;
            justify-self: stretch;
            width: 100%;
        }

        .company-colors-controls,
        .company-colors-preview {
            border-radius: 20px;
            border: 1px solid color-mix(in srgb, var(--company-primary) 10%, var(--company-border));
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 16px 34px color-mix(in srgb, var(--company-primary) 10%, transparent);
        }

        .company-controls-head,
        .colors-help,
        .intentos-badge,
        .company-logo-sample,
        .pastel-presets {
            border-radius: 16px;
            border-color: color-mix(in srgb, var(--company-primary) 10%, var(--company-border));
            box-shadow: 0 10px 20px color-mix(in srgb, var(--company-primary) 6%, transparent);
        }

        .company-logo-sample,
        .pastel-presets {
            padding: 10px;
        }

        .pastel-presets {
            gap: 10px;
        }

        .company-logo-sample {
            gap: 6px;
        }

        .company-logo-sample-media {
            height: clamp(80px, 12vh, 104px);
            font-size: 34px;
            padding: 8px;
        }

        .company-advanced-sections {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 164px;
            gap: 4px;
            align-items: start;
        }

        .company-sections-stack {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .color-palette-library {
            display: grid;
            gap: 4px;
            align-self: start;
            position: sticky;
            top: 0;
        }

        .preset-palettes-wrap {
            display: grid;
            gap: 6px;
        }

        .preset-palettes-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 3px;
        }

        .preset-palette-card {
            border: 0;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(4, 20, 18, 0.08) 0%, rgba(4, 20, 18, 0.02) 100%);
            padding: 2px 2px 1px;
            display: grid;
            gap: 2px;
            text-align: left;
            min-width: 0;
        }

        .preset-palette-meta {
            display: none;
        }

        .preset-palette-name {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            color: var(--company-text);
        }

        .preset-palette-swatches {
            display: grid;
            gap: 0;
            padding: 1px 0 2px;
        }

        .preset-palette-swatch-btn {
            appearance: none;
            width: 100%;
            aspect-ratio: auto;
            height: 34px;
            min-height: 34px;
            border: 2px solid rgba(255, 255, 255, 0.7);
            border-radius: 999px;
            margin-top: -15px;
            cursor: pointer;
            padding: 5px 10px 0;
            box-shadow: 0 9px 18px rgba(15, 23, 42, 0.16);
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            text-align: left;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .12px;
            line-height: 1;
            white-space: nowrap;
            max-width: none;
        }

        .preset-palette-swatch-btn:first-child {
            margin-top: 0;
        }

        .preset-palette-swatch-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.2);
        }

        .preset-palette-swatch-btn.is-selected {
            border-color: var(--company-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--company-primary) 22%, transparent), 0 18px 32px rgba(15, 23, 42, 0.22);
        }

        .company-controls-head {
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 8%, #ffffff), #ffffff);
            padding: 12px;
        }

        .colors-help {
            padding: 10px 12px;
            background: color-mix(in srgb, var(--company-primary) 5%, #ffffff);
        }

        .color-section {
            border-radius: 12px;
            border: 1px solid #d6dde5;
            box-shadow: none;
            background: #ffffff;
        }

        .color-section-title {
            background: #f5f8fb;
            padding: 10px 12px;
            font-size: 12px;
            letter-spacing: .2px;
            border-bottom: 1px solid #e1e8ef;
        }

        .color-section-body {
            display: grid;
            grid-template-columns: 1fr;
            padding: 8px;
            gap: 8px;
            background: #ffffff;
        }

        @media (max-width: 1320px) {
            .company-colors-dialog {
                width: min(1360px, 98vw);
            }

            .company-colors-body {
                grid-template-columns: minmax(520px, 0.82fr) minmax(540px, 1.18fr);
                gap: 6px;
            }
        }

        @media (max-width: 1180px) {
            .company-colors-body {
                grid-template-columns: minmax(0, 1fr);
            }

            .company-colors-preview {
                max-width: none;
                justify-self: stretch;
            }

            .company-advanced-sections {
                grid-template-columns: minmax(0, 1fr);
            }

            .preset-palettes-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .company-sections-stack .color-section .color-field {
            display: grid;
            grid-template-columns: minmax(186px, 1.42fr) minmax(122px, 0.92fr) 36px;
            align-items: center;
            gap: 8px;
            padding: 7px 9px;
            border: 1px solid #dce3ea;
            border-radius: 10px;
            margin: 0;
            background: #fbfdff;
        }

        .company-sections-stack .color-section-title {
            padding: 8px 10px;
            font-size: 11px;
        }

        .company-sections-stack .color-section-body {
            padding: 6px;
            gap: 6px;
        }

        .company-sections-stack .color-section .color-field label {
            font-size: 11px;
            line-height: 1.2;
        }

        .company-sections-stack .color-section .color-field .color-hex {
            font-size: 11px;
            min-height: 28px;
            display: inline-flex;
            align-items: center;
        }

        .color-section .color-field label {
            font-size: 12px;
            letter-spacing: 0;
            text-transform: none;
            font-weight: 600;
        }

        .color-section .color-field .color-hex {
            order: 2;
            text-align: center;
            justify-content: center;
            min-width: 0;
            width: 100%;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
            border-color: #d7dde3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .color-section .color-field .color-hex::before {
            display: none;
        }

        .company-sections-stack .color-section .color-field input[type="color"] {
            order: 3;
            width: 36px;
            height: 36px;
            border-radius: 8px;
        }

        .color-field:hover,
        .color-field.is-selected {
            transform: none;
            box-shadow: none;
        }

        .color-section .color-field:hover {
            border-color: #c8d4df;
            background: #f6fbff;
        }

        .color-section .color-field.is-selected {
            border-color: color-mix(in srgb, var(--company-primary) 40%, #9fb2c4);
            background: color-mix(in srgb, var(--company-primary) 6%, #ffffff);
        }

        .preview-toolbar,
        .preview-live-panel,
        .company-preview-canvas {
            background-image: none !important;
        }

        .preview-live-panel {
            background: linear-gradient(135deg, color-mix(in srgb, var(--company-primary) 8%, #ffffff), #ffffff 46%, color-mix(in srgb, var(--company-secondary) 8%, #ffffff));
            padding: 16px;
        }


        .colors-action-row {
            background: linear-gradient(180deg, rgba(255,255,255,0), #ffffff 28%);
            border-top: 1px solid color-mix(in srgb, var(--company-primary) 8%, var(--company-border));
            padding-top: 12px;
        }

        .btn-restaurar-colores,
        .btn-cancelar-cambios,
        .save-colors-btn,
        .btn-pagar-intentos {
            border-radius: 14px;
            min-height: 42px;
            box-shadow: 0 10px 22px color-mix(in srgb, var(--company-primary) 10%, transparent);
        }

        .save-colors-btn,
        .btn-pagar-intentos {
            background: linear-gradient(135deg, var(--company-primary), var(--company-sidebar)) !important;
            color: #ffffff !important;
        }

        @media (max-width: 768px) {
            .company-colors-dialog {
                width: min(100vw, 100%);
                max-height: 96vh;
                border-radius: 18px;
            }

            .company-colors-body {
                padding: 12px;
                gap: 12px;
            }

            .company-colors-controls,
            .company-colors-preview {
                border-radius: 16px;
            }

            .preset-palettes-grid {
                grid-template-columns: 1fr;
            }
        }

        footer {
            background: rgba(20, 40, 58, 0.94) !important;
            backdrop-filter: blur(8px);
            color: #fff !important;
        }

        footer p,
        footer span,
        footer .footer-wordmark,
        footer .footer-wordmark span {
            color: #fff !important;
        }

        @media print {
            html, body {
                background: #fff;
                color: #000;
                margin: 0;
                padding: 0;
                min-height: 100%;
            }

            body {
                margin-bottom: 100px;
            }

            footer {
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 8px 18px !important;
                background: rgba(20, 40, 58, 0.94) !important;
                color: #fff !important;
                border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
                box-shadow: none !important;
                z-index: 9999 !important;
                page-break-inside: avoid !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            footer p,
            footer span,
            footer .footer-wordmark,
            footer .footer-wordmark span {
                color: #fff !important;
            }

            footer .footer-brand-logo {
                display: inline-block !important;
            }

            .company-ui-toast-container,
            .company-ui-dialog-overlay,
            .topbar,
            .company-ui-dialog,
            .mis-datos-modal,
            .company-ui-dialog-overlay {
                display: none !important;
            }

            .container,
            .dashboard-grid,
            .workspace-home,
            .workspace-module {
                page-break-inside: avoid !important;
            }

            @page {
                margin: 20mm 10mm 25mm 10mm;
            }
        }

    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="<?= $esClienteSesionDashboard ? 'cliente-dashboard' : ''; ?>">
    <div id="divLoading">
        <div>
            <img src="<?= htmlspecialchars($empresaImagenUrl !== '' ? $empresaImagenUrl : $dashboardLoadingLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Loading">
        </div>
    </div>
    <div class="topbar">
        <div class="topbar-inner">
            <div class="topbar-left">
                <div class="topbar-time">
                    <div class="topbar-company-media">
                        <?php if ($dashboardHeaderLogoUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($dashboardHeaderLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo cabecera" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <span style="display:none;"><?= htmlspecialchars($textoMayusDashboard(mb_substr((string)($dashboardHeaderBrandName ?: 'E'), 0, 1, 'UTF-8'))); ?></span>
                        <?php else: ?>
                            <span><?= htmlspecialchars($textoMayusDashboard(mb_substr((string)($dashboardHeaderBrandName ?: 'E'), 0, 1, 'UTF-8'))); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="topbar-time-text">
                        <div class="topbar-time-main">
                            <i class="far fa-clock"></i>
                            <span id="currentTime">--:--</span>
                            <span id="currentDate">--/--/----</span>
                        </div>
                        <div class="topbar-time-user">
                            <span class="topbar-user-role"><?php echo htmlspecialchars($textoMayusDashboard((string)($rolPerfilVisual ?: 'ROL'))); ?></span>
                            <span class="topbar-user-name"><?php echo htmlspecialchars($textoMayusDashboard((string)$nombrePerfilVisual)); ?></span>
                            <?php if (!$esClienteSesionDashboard): ?>
                                <span class="topbar-user-company"><?php echo htmlspecialchars($textoMayusDashboard((string)($esSuperAdminGlobalSinEmpresa ? 'PERFIL GLOBAL (SIN EMPRESA)' : ($empresaNombre ?: 'Empresa no definida')))); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="profile-wrapper">
                <div class="topbar-actions">
                    <button type="button" class="reload-app-btn" onclick="window.location.reload()" title="Recargar aplicación">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <?php if ($mostrarPanelErrores): ?>
                        <button type="button" class="errors-toggle ok" id="errorsToggle" aria-label="Errores del sistema">
                            <i id="errorsToggleIcon" class="fas fa-check-circle"></i>
                            <span>Errores</span>
                            <strong id="errorsToggleCount">(0)</strong>
                        </button>
                        <div class="errors-dropdown" id="errorsDropdown">
                            <button type="button" class="errors-close-btn" id="errorsDropdownClose" aria-label="Cerrar errores">&times;</button>
                            <div id="errorsDropdownContent">
                                <div class="errors-file-block">
                                    <div class="errors-file-head">
                                        <span class="errors-file-name">Panel de diagnostico listo</span>
                                    </div>
                                    <div style="font-size:11px;color: var(--company-text);">Abre este panel para ver el estado mas reciente.</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="button" class="profile-toggle" id="profileToggle" aria-label="Perfil">
                        <i class="fas fa-user"></i>
                    </button>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-card">
                        <button type="button" class="profile-dropdown-close" id="profileDropdownClose" aria-label="Cerrar perfil">&times;</button>
                        <div class="profile-hero">
                            <div class="avatar">
                                <?php if ($dashboardHeaderLogoUrl !== ''): ?>
                                    <img src="<?= htmlspecialchars($dashboardHeaderLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars(strtoupper(substr($dashboardHeaderBrandName ?: ($nombrePerfilVisual ?: 'U'), 0, 1))); ?></span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars(strtoupper(substr($dashboardHeaderBrandName ?: ($nombrePerfilVisual ?: 'U'), 0, 1))); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="name"><?php echo htmlspecialchars($puedeGestionEmpresaPerfil ? ($empresaNombre ?: 'Empresa no definida') : $nombrePerfilVisual); ?></div>
                                <div class="role"><?php echo htmlspecialchars($puedeGestionEmpresaPerfil ? ($nombreCompleto ?: ($rolPerfilVisual ?: 'Administrador')) : ($rolPerfilVisual ?: 'Rol')); ?></div>
                            </div>
                        </div>
                        <button type="button" class="profile-action-btn" id="openMisDatosModal">
                            <i class="fas <?php echo $puedeGestionEmpresaPerfil ? 'fa-building' : 'fa-user'; ?>"></i> <?php echo $puedeGestionEmpresaPerfil ? 'DATOS DE EMPRESA' : 'MIS DATOS'; ?>
                        </button>
                    </div>
                    <a href="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8'); ?>/Controllers/cerrar_sesion.php" class="logout-item">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesion
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-grid">
            <?php if (!$esClienteSesionDashboard): ?>
            <div class="menu-column">
                <!-- Primero el menu de botones -->
                <div class="menu">
                    <div class="menu-title" id="menuHome">
                        <span class="menu-title-text">Menu principal</span>
                        <button type="button" class="sidebar-toggle-nav" id="sidebarToggle" aria-label="Ocultar o mostrar navegacion">
                            <i id="sidebarToggleIcon" class="fas fa-bars"></i>
                        </button>
                    </div>

                    <?php
                        $superAdminPuedeOperarEmpresa = !$esSuperAdminGlobalSesion || $esSuperAdminModoEmpresa;
                        $normalizarNombreModuloMenu = function (string $modulo): string {
                            $texto = function_exists('mb_strtolower') ? mb_strtolower(trim($modulo), 'UTF-8') : strtolower(trim($modulo));
                            $texto = strtr($texto, [
                                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
                                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
                            ]);
                            $texto = str_replace(['_', '-', '.'], ' ', $texto);
                            $texto = preg_replace('/\s+/u', ' ', $texto);
                            return trim((string)$texto);
                        };

                        $modulosSesionNormalizados = [];
                        $modulosSesionNombres = (array)($_SESSION['modulos'] ?? []);
                        foreach ($modulosSesionNombres as $nombreModuloSesion) {
                            $normalizado = $normalizarNombreModuloMenu((string)$nombreModuloSesion);
                            if ($normalizado !== '') {
                                $modulosSesionNormalizados[$normalizado] = true;
                            }
                        }

                        $empresaContextoActivo = (!empty($_SESSION['empresa_id']) || !empty($_SESSION['userData']['empresa_id'])) && empty($_SESSION['superadmin_modo_empresa']);
                        $mostrarUsuarios = !$modoMenuPortable && ($esSuperAdmin || $empresaContextoActivo || (
                            TenantHelper::moduloHabilitado('usuarios') &&
                            PermisosHelper::tienePermiso('usuarios', 'ver')
                        ));
                        $mostrarRoles = !$modoMenuPortable && ($esSuperAdmin || $empresaContextoActivo || (
                            TenantHelper::moduloHabilitado('roles') &&
                            PermisosHelper::tienePermiso('roles', 'ver')
                        ));
                        $mostrarCategorias = $modoMenuPortable || $esSuperAdmin || $empresaContextoActivo || (
                            TenantHelper::moduloHabilitado('categorias') &&
                            PermisosHelper::tienePermiso('categorias', 'ver')
                        );
                        $mostrarProductos = $modoMenuPortable || $esSuperAdmin || $empresaContextoActivo || (
                            TenantHelper::moduloHabilitado('productos') &&
                            PermisosHelper::tienePermiso('productos', 'ver')
                        );
                        $mostrarInventario = $modoMenuPortable || $esSuperAdmin || $empresaContextoActivo || (
                            (TenantHelper::moduloHabilitado('inventario') || TenantHelper::moduloHabilitado('inventarios')) &&
                            (PermisosHelper::tienePermiso('inventario', 'ver') || PermisosHelper::tienePermiso('inventarios', 'ver'))
                        );
                        $mostrarCreditos = $modoMenuPortable || $esSuperAdmin || $empresaContextoActivo || (
                            (TenantHelper::moduloHabilitado('creditos') || TenantHelper::moduloHabilitado('mis creditos')) &&
                            (PermisosHelper::tienePermiso('creditos', 'ver') || PermisosHelper::tienePermiso('mis creditos', 'ver'))
                        );
                        $mostrarPedidos = $esSuperAdmin || $empresaContextoActivo || (
                            TenantHelper::moduloHabilitado('pedidos') &&
                            PermisosHelper::tienePermiso('pedidos', 'ver')
                        );
                        $mostrarTiendas = $esSuperAdmin || $empresaContextoActivo || (
                            TenantHelper::moduloHabilitado('tiendas') &&
                            PermisosHelper::tienePermiso('tiendas', 'ver')
                        );
                        $mostrarActualizaciones = ($_superAdminReal ?? false);
                    ?>

                    <?php if ($mostrarUsuarios): ?>
                        <a href="usuarios.php" class="menu-item">
                            <i class="fas fa-users"></i>
                            <span class="menu-item-text">USUARIOS</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($mostrarRoles): ?>
                        <a href="roles.php" class="menu-item">
                            <i class="fas fa-user-shield"></i>
                            <span class="menu-item-text">ROLES</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($mostrarCategorias): ?>

                        <a href="categorias.php" class="menu-item">
                            <i class="fas fa-tags"></i>
                            <span class="menu-item-text">CATEGORIAS</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($mostrarProductos): ?>
                        <a href="productos.php" class="menu-item">
                            <i class="fas fa-box"></i>
                            <span class="menu-item-text">PRODUCTOS</span>
                        </a>
                        <a href="codigos.php" class="menu-item">
                            <i class="fas fa-barcode"></i>
                            <span class="menu-item-text">CÓDIGO</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($mostrarInventario): ?>
                         <a href="inventarios.php?accion=entrada" class="menu-item menu-item-subaction">
                            <i class="fas fa-arrow-down"></i>
                            <span class="menu-item-text">ENTRADA</span>
                        </a>
                        <a href="inventarios.php?accion=salida" class="menu-item menu-item-subaction">
                            <i class="fas fa-arrow-up"></i>
                            <span class="menu-item-text">SALIDA</span>
                        </a>
                        <a href="inventarios.php" class="menu-item">
                            <i class="fas fa-boxes"></i>
                            <span class="menu-item-text">INVENTARIO</span>
                        </a>
                       
                    <?php endif; ?>

                    <?php if ($mostrarCreditos): ?>
                        <a href="creditos.php" class="menu-item">
                            <i class="fas fa-credit-card"></i>
                            <span class="menu-item-text">CRÉDITOS</span>
                        </a>
                    <?php endif; ?>

                    <a href="conexion.php" class="menu-item">
                        <i class="fas fa-plug"></i>
                        <span class="menu-item-text">CONEXIÓN</span>
                    </a>

                    <?php if ($esSuperAdminGlobalSesion && !$modoMenuPortable): ?>
                        <a href="base_datos.php" class="menu-item">
                            <i class="fas fa-database"></i>
                            <span class="menu-item-text">BASE DE DATOS</span>
                        </a>
                    <?php endif; ?>


                </div>
            </div>
            <?php endif; ?>

            <section class="content-area<?= $esClienteSesionDashboard ? ' module-open' : ''; ?>">
                <?php if (!$esClienteSesionDashboard): ?>
                <div class="workspace-home" id="workspaceHome">
                <div class="content-hero">
                    <div class="hero-content">
                        <h2 class="hero-title"><i class="fas fa-chart-bar"></i>   Panel Principal</h2>
                        <p class="hero-subtitle">Acceso rapido a las funciones clave del sistema</p>
                        <div class="dashboard-filters">
                            <label for="dashboardMesFiltro" class="dashboard-filter-label">FILTRAR POR MES</label>
                            <select id="dashboardMesFiltro" class="dashboard-month-filter" onchange="refrescarDashboard()">
                            </select>
                            
                        </div>
                    </div>
                    
                </div>

                <div class="card-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-user"></i> SESIÓN ACTIVA</h3>
                        <div class="chip"><i class="fas fa-user"></i> <?php echo htmlspecialchars($nombreCompleto); ?></div>
                        <div class="chip"><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($rolNombre); ?></div>
                        <div class="chip"><i class="fas fa-key"></i> Acceso: <?php echo htmlspecialchars($rolNombre); ?></div>
                    </div>
                    <?php if ($mostrarCreditos): ?>
                    <div class="info-card" style="cursor:pointer;" onclick="abrirCreditosDashboard()" title="Ver créditos registrados">
                        <h3><i class="fas fa-credit-card"></i> CRÉDITOS</h3>
                        <div class="chip"><i class="fas fa-users"></i> CLIENTES: <strong id="creditosClientesTotal">0</strong></div>
                        <div class="chip"><i class="fas fa-hand-holding-usd"></i> SALDO: <strong id="creditosSaldoTotal">$0</strong></div>
                        <div id="creditosDashboardResumen" style="margin-top:10px;font-size:12px;color:#64748b;">Cargando créditos...</div>
                    </div>
                    <?php endif; ?>
                    <div class="info-card">
                        <h3><i class="fas fa-chart-bar"></i> VENTAS POR MES</h3>
                        <div class="chart-actions">
                            <div class="chart-toggle-group">
                                <button type="button" onclick="setChartType('ventasChart','bar')" class="chart-toggle-btn" title="Ver barras"><i class="fas fa-minus"></i></button>
                                <button type="button" onclick="setChartType('ventasChart','pie')" class="chart-toggle-btn" title="Ver circular"><i class="fas fa-circle"></i></button>
                            </div>
                            <div class="chart-selection-center">
                                <div id="selectionBadge-ventasChart" class="chart-selection-badge" aria-live="polite"></div>
                            </div>
                            <div class="chart-list-wrapper">
                                <button type="button" class="chart-list-btn" data-chart-list-target="ventasChart" onclick="toggleChartListPanel('ventasChart')">LISTA</button>
                            </div>
                        </div>
                        <div class="chart-body">
                            <div class="chart-canvas-wrapper">
                                <canvas id="ventasChart" width="300" height="180"></canvas>
                            </div>
                            <div class="chart-list-panel-wrapper">
                                <div id="chartListPanel-ventasChart" class="chart-inline-list-panel" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>
                    <div class="info-card">
                        <h3><i class="fas fa-arrow-down"></i> PRODUCTOS QUE ENTRAN</h3>
                        <div class="chart-actions">
                            <div class="chart-toggle-group">
                                <button type="button" onclick="setChartType('entradasChart','bar')" class="chart-toggle-btn" title="Ver barras"><i class="fas fa-minus"></i></button>
                                <button type="button" onclick="setChartType('entradasChart','pie')" class="chart-toggle-btn" title="Ver circular"><i class="fas fa-circle"></i></button>
                            </div>
                            <div class="chart-selection-center">
                                <div id="selectionBadge-entradasChart" class="chart-selection-badge" aria-live="polite"></div>
                            </div>
                            <div class="chart-list-wrapper">
                                <button type="button" class="chart-list-btn" data-chart-list-target="entradasChart" onclick="toggleChartListPanel('entradasChart')">LISTA</button>
                            </div>
                        </div>
                        <div class="chart-body">
                            <div class="chart-canvas-wrapper">
                                <canvas id="entradasChart" width="300" height="180"></canvas>
                            </div>
                            <div class="chart-list-panel-wrapper">
                                <div id="chartListPanel-entradasChart" class="chart-inline-list-panel" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>
                    <div class="info-card">
                        <h3><i class="fas fa-arrow-up"></i> PRODUCTOS QUE SALEN</h3>
                        <div class="chart-actions">
                            <div class="chart-toggle-group">
                                <button type="button" onclick="setChartType('salidasChart','bar')" class="chart-toggle-btn" title="Ver barras"><i class="fas fa-minus"></i></button>
                                <button type="button" onclick="setChartType('salidasChart','pie')" class="chart-toggle-btn" title="Ver circular"><i class="fas fa-circle"></i></button>
                            </div>
                            <div class="chart-selection-center">
                                <div id="selectionBadge-salidasChart" class="chart-selection-badge" aria-live="polite"></div>
                            </div>
                            <div class="chart-list-wrapper">
                                <button type="button" class="chart-list-btn" data-chart-list-target="salidasChart" onclick="toggleChartListPanel('salidasChart')">LISTA</button>
                            </div>
                        </div>
                        <div class="chart-body">
                            <div class="chart-canvas-wrapper">
                                <canvas id="salidasChart" width="300" height="180"></canvas>
                            </div>
                            <div class="chart-list-panel-wrapper">
                                <div id="chartListPanel-salidasChart" class="chart-inline-list-panel" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>
                    <div class="info-card">
                        <h3><i class="fas fa-warehouse"></i> STOCK TOTAL</h3>
                        <div class="chart-actions">
                            <div class="chart-toggle-group">
                                <button type="button" onclick="setChartType('stockChart','bar')" class="chart-toggle-btn" title="Ver barras"><i class="fas fa-minus"></i></button>
                                <button type="button" onclick="setChartType('stockChart','pie')" class="chart-toggle-btn" title="Ver circular"><i class="fas fa-circle"></i></button>
                            </div>
                            <div class="chart-selection-center">
                                <div id="selectionBadge-stockChart" class="chart-selection-badge" aria-live="polite"></div>
                            </div>
                            <div class="chart-list-wrapper">
                                <button type="button" class="chart-list-btn" data-chart-list-target="stockChart" onclick="toggleChartListPanel('stockChart')">LISTA</button>
                            </div>
                        </div>
                        <div class="chart-body">
                            <div class="chart-canvas-wrapper">
                                <canvas id="stockChart" width="300" height="180"></canvas>
                            </div>
                            <div class="chart-list-panel-wrapper">
                                <div id="chartListPanel-stockChart" class="chart-inline-list-panel" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
                <?php else: ?>
                <div class="workspace-home hidden" id="workspaceHome"></div>
                <?php endif; ?>

                <div class="workspace-module<?= $esClienteSesionDashboard ? ' active' : ''; ?>" id="workspaceModule">
                    <iframe id="moduleFrame" class="module-frame" src="" loading="lazy" aria-hidden="true"></iframe>
                </div>
            </section>

        </div>
    </div>

    <?php if ($mostrarCreditos): ?>
    <div id="creditosDashboardModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:1050px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <h2 style="margin:0;"><i class="fas fa-credit-card"></i> CRÉDITOS POR CLIENTE</h2>
                <button type="button" class="close-btn" onclick="cerrarCreditosDashboard()" title="Cerrar créditos"><i class="fas fa-times"></i></button>
            </div>
            <div id="creditosDashboardLista" style="margin-top:18px;max-height:65vh;overflow:auto;">Cargando créditos...</div>
        </div>
    </div>
    <?php endif; ?>

    <div id="conexionDashboardModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:520px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;">
                <h2 style="margin:0;"><i class="fas fa-plug"></i> CONEXIÓN</h2>
                <button type="button" class="close-btn" onclick="cerrarConexionDashboard()" title="Cerrar conexión"><i class="fas fa-times"></i></button>
            </div>
            <p style="margin:0 0 14px;color:#475569;font-size:14px;">Ingrese la IP y el puerto del equipo principal para conectarse a la misma instalación.</p>

            <div style="display:grid;grid-template-columns:1.4fr 0.8fr;gap:10px;">
                <div>
                    <label for="conexionDashboardIpInput" style="display:block;margin-bottom:8px;font-weight:700;color:#0f172a;">IP del equipo principal</label>
                    <input id="conexionDashboardIpInput" type="text" placeholder="Ejemplo: 192.168.1.239" autocomplete="off" style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box;">
                </div>
                <div>
                    <label for="conexionDashboardPortInput" style="display:block;margin-bottom:8px;font-weight:700;color:#0f172a;">Puerto</label>
                    <input id="conexionDashboardPortInput" type="number" min="1" max="65535" placeholder="8000" autocomplete="off" style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-top:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;">
                    <strong style="font-size:13px;color:#0f172a;">PUERTOS GUARDADOS</strong>
                </div>
                <div id="conexionDashboardLista" style="display:flex;flex-direction:column;gap:8px;max-height:190px;overflow:auto;padding-right:4px;"></div>
            </div>

            <div style="margin-top:18px;">
                <strong style="font-size:13px;color:#0f172a;">CAJAS ACTIVAS</strong>
                <div id="cajasActivasLista" style="display:flex;flex-direction:column;gap:8px;max-height:160px;overflow:auto;padding:8px 0;"></div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;">
                <button type="button" class="btn btn-secondary" onclick="cerrarConexionDashboard()">CANCELAR</button>
                <button type="button" class="btn btn-primary" onclick="guardarConexionDashboard()">GUARDAR Y CONECTAR</button>
            </div>
        </div>
    </div>

    <!-- Modal: Datos de Empresa -->
    <div class="mis-datos-modal" id="misDatosModal" aria-hidden="true">
        <div class="mis-datos-dialog">
            <div class="mis-datos-header">
                <h3>
                    <i class="fas <?php echo $puedeGestionEmpresaPerfil ? 'fa-building' : 'fa-user'; ?>"></i>
                    <?php echo $puedeGestionEmpresaPerfil ? 'Datos de Empresa' : 'Mis Datos'; ?>
                </h3>
                <button type="button" class="mis-datos-close" id="closeMisDatosModal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="mis-datos-body">
                <div class="mis-datos-avatar">
                    <div class="mis-datos-avatar-circle" id="empresaAvatarInicial">
                        <?php if ($empresaImagenUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($empresaImagenUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo empresa" style="width:100%;height:100%;object-fit:contain;border-radius:inherit;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($textoMayusDashboard(mb_substr($puedeGestionEmpresaPerfil ? ($empresaNombre ?: 'E') : ($nombrePerfilVisual ?: 'U'), 0, 1))); ?></span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($textoMayusDashboard(mb_substr($puedeGestionEmpresaPerfil ? ($empresaNombre ?: 'E') : ($nombrePerfilVisual ?: 'U'), 0, 1))); ?>
                        <?php endif; ?>
                    </div>
                    <div class="mis-datos-avatar-info">
                        <div class="mis-datos-name" id="empresaNombreVisual"><?php echo htmlspecialchars($puedeGestionEmpresaPerfil ? ($empresaNombre ?: 'N/A') : ($nombrePerfilVisual ?: 'N/A')); ?></div>
                        <div class="mis-datos-role"><?php echo htmlspecialchars($puedeGestionEmpresaPerfil ? ($nombreCompleto ?: ($rolPerfilVisual ?: 'Administrador')) : ($rolPerfilVisual ?: 'Rol')); ?></div>
                    </div>
                </div>

                <div class="mis-datos-admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <div>
                        <div class="mis-datos-admin-label"><?php echo htmlspecialchars($puedeGestionEmpresaPerfil ? ($rolPerfilVisual ?: 'Administrador') : 'Cuenta de cliente'); ?></div>
                        <div class="mis-datos-admin-nombre"><?php echo htmlspecialchars($puedeGestionEmpresaPerfil ? ($nombrePerfilVisual ?: 'N/A') : ($correo ?: 'Sin correo')); ?></div>
                    </div>
                </div>

                <form id="empresaDatosForm" class="mis-datos-form" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="guardar_datos_empresa">

                    <div class="mis-datos-readonly-grid">
                        <div class="mis-datos-field">
                            <label for="empresaNombreInput"><?php echo $puedeGestionEmpresaPerfil ? 'Nombre de empresa' : 'Nombre completo'; ?></label>
                            <div class="input-with-edit">
                                <input type="text" id="empresaNombreInput" name="empresa_nombre" maxlength="120"
                                       value="<?php echo htmlspecialchars($puedeGestionEmpresaPerfil ? ($empresaNombre ?: '') : ($nombrePerfilVisual ?: '')); ?>" readonly
                                       class="mis-datos-nombre-input">
                                <?php if ($puedeGestionEmpresaPerfil): ?>
                                    <button type="button" class="mis-datos-edit-btn" id="editNombreEmpresaBtn" title="Editar nombre de la empresa">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <?php if (!$puedeGestionEmpresaPerfil): ?>
                        <div class="mis-datos-readonly-grid">
                            <div class="mis-datos-field">
                                <label for="misDatosCorreoInput">Correo</label>
                                <input type="text" id="misDatosCorreoInput" value="<?php echo htmlspecialchars($correo ?: 'N/A'); ?>" readonly>
                            </div>
                            <div class="mis-datos-field">
                                <label for="misDatosTelefonoInput">Telefono</label>
                                <input type="text" id="misDatosTelefonoInput" value="<?php echo htmlspecialchars($telefono ?: 'N/A'); ?>" readonly>
                            </div>
                        </div>
                        <div class="mis-datos-field">
                            <label for="misDatosDocumentoInput">Documento</label>
                            <input type="text" id="misDatosDocumentoInput" value="<?php echo htmlspecialchars((string)($documento ?: 'N/A')); ?>" readonly>
                        </div>
                    <?php endif; ?>

                    <?php if ($puedeGestionEmpresaPerfil): ?>
                        <div class="mis-datos-readonly-grid">
                            <div class="mis-datos-field">
                                <label>Usuario conectado</label>
                                <input type="text" value="<?php echo htmlspecialchars($nombreCompleto ?: 'N/A'); ?>" readonly>
                            </div>
                            <div class="mis-datos-field">
                                <label>Correo de usuario</label>
                                <input type="text" value="<?php echo htmlspecialchars($correo ?: 'N/A'); ?>" readonly>
                            </div>
                            <div class="mis-datos-field">
                                <label>Teléfono de usuario</label>
                                <input type="text" value="<?php echo htmlspecialchars($telefono ?: 'N/A'); ?>" readonly>
                            </div>
                        </div>
                        <div class="mis-datos-field">
                            <label>Logo de empresa</label>
                            <div class="mis-datos-logo-upload">
                                <div class="mis-datos-logo-preview" id="empresaLogoPreviewWrap">
                                    <?php if ($empresaImagenUrl !== ''): ?>
                                        <img id="empresaLogoPreview" src="<?= htmlspecialchars($empresaImagenUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo de empresa">
                                    <?php else: ?>
                                        <i class="fas fa-building" id="empresaLogoPlaceholder"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="mis-datos-logo-controls">
                                    <input type="file" id="empresaLogoInput" name="logo_empresa_archivo" accept="image/png">
                                    <small>Formatos: JPG, PNG, WEBP o GIF. Tamaño máximo: 5MB.</small>
                                </div>
                            </div>
                        </div>
                        <div class="mis-datos-readonly-grid">
                            <div class="mis-datos-field">
                                <label for="empresaTelefonoInput">Teléfono de empresa</label>
                                <div class="input-with-edit">
                                    <input type="text" id="empresaTelefonoInput" name="empresa_telefono" maxlength="50" value="<?= htmlspecialchars($empresaTelefonoContacto, ENT_QUOTES, 'UTF-8'); ?>" placeholder="3001234567" readonly>
                                    <button type="button" class="mis-datos-edit-btn" onclick="habilitarEdicion('empresaTelefonoInput')" title="Editar teléfono">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mis-datos-field">
                                <label for="empresaCorreoInput">Correo de empresa</label>
                                <div class="input-with-edit">
                                    <input type="email" id="empresaCorreoInput" name="empresa_correo" maxlength="150" value="<?= htmlspecialchars($empresaCorreoContacto, ENT_QUOTES, 'UTF-8'); ?>" placeholder="empresa@email.com" readonly>
                                    <button type="button" class="mis-datos-edit-btn" onclick="habilitarEdicion('empresaCorreoInput')" title="Editar correo">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mis-datos-readonly-grid">
                            <div class="mis-datos-field">
                                <label for="empresaDireccionInput">Ubicación</label>
                                <div class="input-with-edit">
                                    <input type="text" id="empresaDireccionInput" name="empresa_direccion" maxlength="200" value="<?= htmlspecialchars($empresaDireccion, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Carrera, calle, barrio, ciudad" readonly>
                                    <button type="button" class="mis-datos-edit-btn" onclick="habilitarEdicion('empresaDireccionInput')" title="Editar ubicación">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mis-datos-actions">
                            <button type="submit" class="mis-datos-save" id="guardarEmpresaBtn">
                                <i class="fas fa-save"></i> Guardar Datos de Empresa
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="mis-datos-status" id="empresaDatosStatus" aria-live="polite"></div>
                </form>
            </div>
        </div>
    </div>



   
    </div>

    <div class="company-ui-toast-container" id="companyUiToastContainer" aria-live="polite" aria-atomic="true"></div>

    <div class="company-ui-dialog-overlay" id="companyUiDialog" aria-hidden="true">
        <div class="company-ui-dialog" role="dialog" aria-modal="true" aria-labelledby="companyUiDialogTitle">
            <div class="company-ui-dialog-header" id="companyUiDialogTitle">Confirmacion</div>
            <div class="company-ui-dialog-body" id="companyUiDialogMessage"></div>
            <div class="company-ui-dialog-actions">
                <button type="button" class="company-ui-btn" id="companyUiDialogCancel">Cancelar</button>
                <button type="button" class="company-ui-btn primary" id="companyUiDialogAccept">Aceptar</button>
            </div>
        </div>
    </div>

    <footer style="color: #fff;">
        <p>
            <span>&copy; <?php echo date('Y'); ?></span>
            <span class="footer-wordmark">
                <img src="<?= htmlspecialchars(rtrim((string)base_url(), '/') . '/favicon.ico', ENT_QUOTES, 'UTF-8'); ?>" alt="Favicon" class="footer-brand-logo" onerror="this.style.display='none';">
                <span>OWE COMPANY</span>
            </span>
            <span>TODOS LOS DERECHOS RESERVADOS</span>
        </p>
    </footer>

    <script>
        function updateDateTime() {
            const now = new Date();
            const time = now.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Bogota' });
            const date = now.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'America/Bogota' });
            const timeEl = document.getElementById('currentTime');
            const dateEl = document.getElementById('currentDate');
            if (timeEl) timeEl.textContent = time;
            if (dateEl) dateEl.textContent = date;
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateDateTime();
            setInterval(updateDateTime, 1000);

            const toggle = document.getElementById('profileToggle');
            const dropdown = document.getElementById('profileDropdown');
            const profileDropdownClose = document.getElementById('profileDropdownClose');
            const errorsToggle = document.getElementById('errorsToggle');
            const errorsToggleIcon = document.getElementById('errorsToggleIcon');
            const errorsToggleCount = document.getElementById('errorsToggleCount');
            const errorsDropdown = document.getElementById('errorsDropdown');
            const errorsDropdownContent = document.getElementById('errorsDropdownContent');
            const errorsDropdownClose = document.getElementById('errorsDropdownClose');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
            const menuHome = document.getElementById('menuHome');
            const homePanel = document.getElementById('workspaceHome');
            const modulePanel = document.getElementById('workspaceModule');
            const moduleFrame = document.getElementById('moduleFrame');
            const contentArea = document.querySelector('.content-area');
            const menuLinks = Array.from(document.querySelectorAll('.menu .menu-item[href*=".php"]'));
            const toggleProfileDataBtn = null; // reemplazado por modal
            const profileDataGrid = null;      // reemplazado por modal
            const companyColorsForm = document.getElementById('companyColorsForm');
            const companyColorsStatus = document.getElementById('companyColorsStatus');
            const colorInputs = Array.from(document.querySelectorAll('#companyColorsForm input[type="color"]'));
            const colorFields = Array.from(document.querySelectorAll('#companyColorsForm .color-field'));
            const companyAdvancedSections = document.getElementById('companyAdvancedSections');
            const recentPresetsGrid = document.getElementById('recentPresetsGrid');
            const presetPalettesGrid = document.getElementById('presetPalettesGrid');
            const companyColorsPreview = document.getElementById('companyColorsPreview');
            const previewMenuItems = Array.from(document.querySelectorAll('.company-preview-menu-item'));
            const companyLogoSampleMedia = document.getElementById('companyLogoSampleMedia');
            const companyPreviewBrandIcon = document.getElementById('companyPreviewBrandIcon');
            const companyPreviewLogoTargets = () => Array.from(document.querySelectorAll('#companyColorsPreview .company-preview-logo-target'));
            const previewLiveTitle = document.getElementById('previewLiveTitle');
            const previewLiveCopy = document.getElementById('previewLiveCopy');
            const previewLiveHex = document.getElementById('previewLiveHex');
            const previewLiveImpact = document.getElementById('previewLiveImpact');
            const previewLiveScope = document.getElementById('previewLiveScope');
            const previewLiveNote = document.getElementById('previewLiveNote');
            const previewFullscreenToggle = document.getElementById('previewFullscreenToggle');
            let activeColorInput = document.getElementById('color_principal') || colorInputs[0] || null;
            const recentColorsStorageKey = 'dashboard_company_recent_pastel_colors';
            const maxRecentColors = 12;
            const presetPaletteDefinitions = [
                { key: 'ember-red', name: 'Ember Red', colors: ['#5A1F14', '#551C24', '#C23C29', '#F53960', '#FFCDD2'] },
                { key: 'amber-gold', name: 'Amber Gold', colors: ['#7D6608', '#B88908', '#F6D90F', '#F7E097', '#FEE63F'] },
                { key: 'ocean-blue', name: 'Ocean Blue', colors: ['#154660', '#2F6F9F', '#3B83C1', '#9FC3F1', '#D6EEF9'] },
                { key: 'indigo-night', name: 'Indigo Night', colors: ['#154360', '#375FBD', '#5235C1', '#9BB4F5', '#D6EAF8'] },
                { key: 'steel-blue', name: 'Steel Blue', colors: ['#0B1F3A', '#125A6F', '#184676', '#A9C8F6', '#D4E6F1'] },
                { key: 'deep-forest', name: 'Deep Forest', colors: ['#051F20', '#173831', '#235347', '#8CB79B', '#DBF0DD'] },
                { key: 'forest-mint', name: 'Forest Mint', colors: ['#145A32', '#145A49', '#3E8B72', '#A9D9C8', '#D5F5E3'] },
                { key: 'violet-royal', name: 'Violet Royal', colors: ['#4A235A', '#5E2463', '#8E5AA0', '#D7BDE2', '#F5EEF8'] },
                { key: 'copper-sand', name: 'Copper Sand', colors: ['#6E2C00', '#AA4A00', '#C97E85', '#F4C38B', '#FDEBD0'] },
                { key: 'sunset-peach', name: 'Sunset Peach', colors: ['#6E2C00', '#ED7622', '#F9A031', '#F8C9A1', '#FDEBD0'] },
                { key: 'berry-pop', name: 'Berry Pop', colors: ['#78281F', '#A01457', '#E8307A', '#F5B7D2', '#FDE2E4'] },
                { key: 'jade-aqua', name: 'Jade Aqua', colors: ['#0E6251', '#17A098', '#1ABC9C', '#A8E6E0', '#D1F2EB'] },
                { key: 'slate-sky', name: 'Slate Sky', colors: ['#1C1C1C', '#434669', '#6C8BC4', '#C8DAF5', '#FAF6F0'] },
                { key: 'lavender-mist', name: 'Lavender Mist', colors: ['#3E2723', '#6F6C91', '#B6B5D8', '#E6E4F5', '#F8F8FB'] },
                { key: 'mocha-cream', name: 'Mocha Cream', colors: ['#3D2226', '#A96942', '#D9A679', '#F3D5BF', '#EFEBE9'] },
                { key: 'orchid-glow', name: 'Orchid Glow', colors: ['#4B1E3F', '#7B1C8A', '#C153A4', '#E8B1D4', '#F5EEF8'] },
            ];
            const uiToastContainer = document.getElementById('companyUiToastContainer');
            const uiDialog = document.getElementById('companyUiDialog');
            const uiDialogTitle = document.getElementById('companyUiDialogTitle');
            const uiDialogMessage = document.getElementById('companyUiDialogMessage');
            const uiDialogAccept = document.getElementById('companyUiDialogAccept');
            const uiDialogCancel = document.getElementById('companyUiDialogCancel');

            const companyTheme = <?= json_encode($coloresEmpresa, JSON_UNESCAPED_UNICODE); ?>;
            const companyThemeLiveStorageKey = <?= json_encode('company_theme_live_' . (int)$empresaId, JSON_UNESCAPED_UNICODE); ?>;
            const ES_CLIENTE_DASHBOARD = <?= $esClienteSesionDashboard ? 'true' : 'false'; ?>;
            const CLIENTE_DASHBOARD_VIEW = '';
            const diagnosticoEndpoint = <?= json_encode($diagnosticoEndpoint, JSON_UNESCAPED_UNICODE); ?>;
            const usarDiagnosticoAjax = <?= $usarDiagnosticoAjax ? 'true' : 'false'; ?>;
            const diagnosticoLocalPayload = <?= json_encode($diagnosticoLocalPayload, JSON_UNESCAPED_UNICODE); ?>;
            const previewSectionLabels = {
                base: 'Base visual',
                nav: 'Navegacion',
                content: 'Contenido',
                tables: 'Tablas',
                buttons: 'Botones',
                forms: 'Formularios'
            };
            const previewFieldFocusTargets = {
                color_principal: 'branding',
                color_secundario: 'accents',
                color_menu_lateral: 'sidebar',
                color_navbar: 'navbar',
                color_iconos_menu: 'menu-icons',
                color_hover_menu: 'menu-active',
                color_titulos: 'content',
                color_links: 'content',
                color_fondo_tabla: 'table-body',
                color_texto_tabla: 'table-text',
                color_encabezado_tabla: 'table-header',
                color_filas_alternas: 'table-zebra',
                color_botones: 'buttons',
                color_btn_crear: 'buttons',
                color_btn_editar: 'buttons',
                color_btn_eliminar: 'buttons',
                color_fondo: 'surface',
                color_texto: 'text',
                color_bordes: 'borders',
                color_focus_inputs: 'forms'
            };
            const previewFieldThemeKeys = {
                color_principal: ['color_principal'],
                color_secundario: ['color_secundario'],
                color_menu_lateral: ['color_menu_lateral'],
                color_navbar: ['color_navbar'],
                color_iconos_menu: ['color_iconos_menu'],
                color_hover_menu: ['color_hover_menu'],
                color_titulos: ['color_titulos'],
                color_links: ['color_links'],
                color_fondo_tabla: ['color_fondo_tabla'],
                color_texto_tabla: ['color_texto_tabla'],
                color_encabezado_tabla: ['color_encabezado_tabla'],
                color_filas_alternas: ['color_filas_alternas'],
                color_botones: ['color_botones'],
                color_btn_crear: ['color_btn_crear'],
                color_btn_editar: ['color_btn_editar'],
                color_btn_eliminar: ['color_btn_eliminar'],
                color_fondo: ['color_fondo'],
                color_texto: ['color_texto'],
                color_bordes: ['color_bordes'],
                color_focus_inputs: ['color_focus_inputs']
            };
            const previewFieldDescriptions = {
                color_principal: {
                    impact: 'Cabecera, acentos visuales y componentes principales',
                    note: 'Define la personalidad general del sistema y el primer golpe visual de la marca.',
                    copy: 'Este color se usa como base para elementos destacados y ayuda a que la interfaz se sienta coherente con el logo.'
                },
                color_secundario: {
                    impact: 'Estados activos, chips y refuerzos visuales',
                    note: 'Sirve como tono de apoyo para no saturar toda la interfaz con un solo color.',
                    copy: 'Usalo para complementar el principal y dar profundidad a tarjetas, pills y bloques de apoyo.'
                },
                color_menu_lateral: {
                    impact: 'Sidebar, jerarquia de navegacion y contraste del menu',
                    note: 'Si el logo es oscuro, conviene que este tono mantenga buen contraste con iconos y texto.',
                    copy: 'Aqui ves inmediatamente si el menu lateral queda elegante, legible y alineado con tu marca.'
                },
                color_botones: {
                    impact: 'CTA, busqueda y acciones generales',
                    note: 'Busca un color que invite a hacer clic pero no opaque el contenido.',
                    copy: 'Cada boton de accion se actualiza en el preview para que revises prioridad visual y legibilidad.'
                },
                color_fondo: {
                    impact: 'Superficie general, descanso visual y limpieza de pantalla',
                    note: 'Fondos demasiado saturados cansan; aqui puedes medir si el modulo respira bien.',
                    copy: 'El fondo cambia en vivo para que sepas si el sistema se siente ligero, premium o muy cargado.'
                },
                color_texto: {
                    impact: 'Lectura global y claridad de la interfaz',
                    note: 'Debe mantener contraste suficiente sobre fondo, tablas y tarjetas.',
                    copy: 'En el borrador se actualizan textos, notas y etiquetas para revisar lectura real, no solo un cuadro de color.'
                },
                color_bordes: {
                    impact: 'Separadores, tarjetas y estructura de los bloques',
                    note: 'Un buen borde ordena el diseño sin robar protagonismo.',
                    copy: 'Prueba aqui si las tarjetas y tablas quedan definidas o demasiado planas.'
                },
                color_navbar: {
                    impact: 'Barra superior del modulo, cabecera y zona de contexto',
                    note: 'Aqui se define el tono de la barra superior que acompaña filtros y busqueda.',
                    copy: 'Cambia este color para validar legibilidad de titulos, chips y controles de cabecera.'
                },
                color_iconos_menu: {
                    impact: 'Iconos, indicadores y referencias visuales del menu lateral',
                    note: 'Debe contrastar bien con el fondo del menu para mantener lectura inmediata.',
                    copy: 'Aqui se actualizan iconos y numeraciones del menu para validar legibilidad real en navegacion.'
                },
                color_hover_menu: {
                    impact: 'Estado hover/activo en navegacion lateral',
                    note: 'Define como se resalta el modulo seleccionado en el menu.',
                    copy: 'Aqui verificas si el item activo del menu destaca sin romper la armonia de la paleta.'
                },
                color_titulos: {
                    impact: 'Jerarquia de encabezados y bloques de contenido',
                    note: 'Titulos bien contrastados mejoran lectura y escaneo de informacion.',
                    copy: 'En el borrador veras como quedan encabezados y nombres de bloques al instante.'
                },
                color_links: {
                    impact: 'Enlaces de accion y rutas visuales secundarias',
                    note: 'Los links deben verse clickeables sin competir con botones principales.',
                    copy: 'Revisa aqui la visibilidad de enlaces como guia visual y accesos secundarios.'
                },
                color_fondo_tabla: {
                    impact: 'Superficie base de filas y celdas de tabla',
                    note: 'Afecta directamente lectura de datos, IDs y nombres de modulo.',
                    copy: 'Este color cambia el fondo de la tabla para validar contraste real de texto y estado.'
                },
                color_texto_tabla: {
                    impact: 'Color de letras en filas de tabla',
                    note: 'Controla la legibilidad de IDs, nombres y estados en el cuerpo de la tabla.',
                    copy: 'Usa este color para definir el texto de las filas sin alterar fondo ni encabezado.'
                },
                color_encabezado_tabla: {
                    impact: 'Cabecera de tabla (columnas y titulos de datos)',
                    note: 'Debe tener contraste alto para que los encabezados se lean rapido.',
                    copy: 'Prueba este tono para confirmar que el encabezado resalte frente a las filas.'
                },
                color_filas_alternas: {
                    impact: 'Zebra de filas para escaneo visual de registros',
                    note: 'Una alternancia suave facilita leer listados extensos.',
                    copy: 'Aqui visualizas el efecto de filas alternas y su balance con fondo y texto.'
                },
                color_btn_crear: {
                    impact: 'Boton de crear/agregar en contexto de gestion',
                    note: 'Debe transmitir accion positiva y mantener legibilidad del texto.',
                    copy: 'Revisa en vivo como se percibe el boton de alta frente al resto de acciones.'
                },
                color_btn_editar: {
                    impact: 'Boton de edicion y mantenimiento de registros',
                    note: 'Debe diferenciarse del crear y del eliminar sin perder coherencia.',
                    copy: 'Este ajuste muestra el peso visual del boton editar dentro de tarjetas y tabla.'
                },
                color_btn_eliminar: {
                    impact: 'Boton de eliminacion y acciones destructivas',
                    note: 'Conviene un tono de alerta claro para evitar confusiones operativas.',
                    copy: 'En el preview validas rapidamente si eliminar se distingue como accion critica.'
                },
                color_focus_inputs: {
                    impact: 'Anillo de foco en inputs, selects y campos interactivos',
                    note: 'Ayuda a la navegacion y accesibilidad al editar formularios.',
                    copy: 'Ajusta este color para mejorar visibilidad del foco sin ruido visual excesivo.'
                }
            };

            const showToast = (message, type = 'info', timeout = 2600) => {
                if (!uiToastContainer) return;
                const toast = document.createElement('div');
                toast.className = `company-ui-toast ${type}`;
                toast.textContent = message;
                uiToastContainer.appendChild(toast);
                requestAnimationFrame(() => toast.classList.add('show'));
                window.setTimeout(() => {
                    toast.classList.remove('show');
                    window.setTimeout(() => toast.remove(), 200);
                }, timeout);
            };

            window.escapeHtml = function escapeHtml(text) {
                return String(text || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const renderPresetPalettes = () => {
                if (!presetPalettesGrid) return;

                presetPalettesGrid.innerHTML = presetPaletteDefinitions.map((palette) => `
                    <div class="preset-palette-card" data-palette-key="${escapeHtml(palette.key)}">
                        <span class="preset-palette-meta">
                            <span class="preset-palette-name">${escapeHtml(palette.name)}</span>
                        </span>
                        <span class="preset-palette-swatches">
                            ${palette.colors.map((color) => `
                                <button type="button" class="preset-color-btn preset-palette-swatch-btn" data-color="${escapeHtml(color)}" style="background:${color};color:${getContrastColor(color)};" title="Aplicar color ${escapeHtml(color)}" aria-label="Aplicar color ${escapeHtml(color)}">${escapeHtml(color)}</button>
                            `).join('')}
                        </span>
                    </div>
                `).join('');

                presetPalettesGrid.querySelectorAll('.preset-palette-swatch-btn').forEach((button) => {
                    button.addEventListener('click', () => {
                        const color = normalizarHex(button.dataset.color || '', '#FCCDC4');
                        applyColorToActiveInput(color);
                    });
                });

                syncPresetSelection();
            };

            const showConfirmDialog = ({
                title = 'Confirmacion',
                message = '',
                acceptText = 'Aceptar',
                cancelText = 'Cancelar',
            } = {}) => {
                if (typeof Swal !== 'undefined') {
                    return Swal.fire({
                        icon: 'question',
                        title,
                        text: message,
                        showCancelButton: true,
                        confirmButtonText: acceptText,
                        cancelButtonText: cancelText,
                    }).then((r) => r.isConfirmed === true);
                }

                if (!uiDialog || !uiDialogTitle || !uiDialogMessage || !uiDialogAccept || !uiDialogCancel) {
                    return Promise.resolve(window.confirm(message));
                }

                return new Promise((resolve) => {
                    uiDialogTitle.textContent = title;
                    uiDialogMessage.textContent = message;
                    uiDialogAccept.textContent = acceptText;
                    uiDialogCancel.textContent = cancelText;
                    uiDialog.classList.add('active');
                    uiDialog.setAttribute('aria-hidden', 'false');

                    const cleanup = () => {
                        uiDialog.classList.remove('active');
                        uiDialog.setAttribute('aria-hidden', 'true');
                        uiDialogAccept.removeEventListener('click', onAccept);
                        uiDialogCancel.removeEventListener('click', onCancel);
                        uiDialog.removeEventListener('click', onOverlay);
                        document.removeEventListener('keydown', onEsc);
                    };

                    const onAccept = () => {
                        cleanup();
                        resolve(true);
                    };

                    const onCancel = () => {
                        cleanup();
                        resolve(false);
                    };

                    const onOverlay = (event) => {
                        if (event.target === uiDialog) onCancel();
                    };

                    const onEsc = (event) => {
                        if (event.key === 'Escape') onCancel();
                    };

                    uiDialogAccept.addEventListener('click', onAccept);
                    uiDialogCancel.addEventListener('click', onCancel);
                    uiDialog.addEventListener('click', onOverlay);
                    document.addEventListener('keydown', onEsc);
                });
            };

            const showAlertSafe = ({ icon = 'info', title = 'Informacion', text = '', confirmButtonText = 'Entendido' } = {}) => {
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    return Swal.fire({ icon, title, text, confirmButtonText });
                }
                window.alert([title, text].filter(Boolean).join('\n\n'));
                return Promise.resolve({ isConfirmed: true });
            };

            const showLoadingSafe = ({ title = 'Cargando', text = 'Espera un momento...' } = {}) => {
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    Swal.fire({
                        title,
                        text,
                        allowOutsideClick: false,
                        didOpen: () => {
                            if (typeof Swal.showLoading === 'function') Swal.showLoading();
                        },
                    });
                    return;
                }
                if (companyColorsStatus) {
                    companyColorsStatus.classList.remove('error');
                    companyColorsStatus.textContent = text;
                }
            };

            const closeLoadingSafe = () => {
                if (typeof Swal !== 'undefined' && typeof Swal.close === 'function') {
                    Swal.close();
                }
            };

            const parseJsonFromResponse = async (response) => {
                const raw = await response.text();
                const plain = String(raw || '').trim();

                try {
                    return JSON.parse(plain);
                } catch {
                    const ini = plain.indexOf('{');
                    const fin = plain.lastIndexOf('}');
                    if (ini >= 0 && fin > ini) {
                        return JSON.parse(plain.slice(ini, fin + 1));
                    }
                    throw new Error('La respuesta del servidor no fue JSON valido.');
                }
            };

            const normalizarHex = (color, fallback) => {
                const raw = String(color || '').trim().toUpperCase();
                return /^#[0-9A-F]{6}$/.test(raw) ? raw : fallback;
            };

            const snapshotThemeFromInputs = () => {
                const defaults = <?= json_encode($coloresEmpresaDefault, JSON_UNESCAPED_UNICODE); ?>;

                const g = (id, fallback) => normalizarHex(document.getElementById(id)?.value, defaults[id] || fallback);

                return {
                    color_principal:        g('color_principal',        '#2F4A5A'),
                    color_secundario:       g('color_secundario',       '#2F4A5A'),
                    color_menu_lateral:     g('color_menu_lateral',     '#2F4A5A'),
                    color_botones:          g('color_botones',          '#2F4A5A'),
                    color_fondo:            g('color_fondo',            '#F4F6F8'),
                    color_texto:            g('color_texto',            '#2F4A5A'),
                    color_bordes:           g('color_bordes',           '#DFF3DE'),
                    color_navbar:           g('color_navbar',           '#2F4A5A'),
                    color_iconos_menu:      g('color_iconos_menu',      '#FFFFFF'),
                    color_hover_menu:       g('color_hover_menu',       '#3D6175'),
                    color_titulos:          g('color_titulos',          '#2F4A5A'),
                    color_links:            g('color_links',            '#2F4A5A'),
                    color_fondo_tabla:      g('color_fondo_tabla',      '#FFFFFF'),
                    color_texto_tabla:      g('color_texto_tabla',      '#2F4A5A'),
                    color_encabezado_tabla: g('color_encabezado_tabla',  '#2F4A5A'),
                    color_filas_alternas:   g('color_filas_alternas',   '#F4F6F8'),
                    color_btn_crear:        g('color_btn_crear',        '#2F4A5A'),
                    color_btn_editar:       g('color_btn_editar',       '#2F4A5A'),
                    color_btn_eliminar:     g('color_btn_eliminar',     '#DC3545'),
                    color_focus_inputs:     g('color_focus_inputs',     '#2F4A5A'),
                };
            };

            const syncColorHexLabels = () => {
                colorInputs.forEach((input) => {
                    const target = document.querySelector(`[data-color-hex="${input.id}"]`);
                    if (target) {
                        const hexValue = String(input.value || '').toUpperCase();
                        target.textContent = hexValue;
                        target.style.setProperty('--hex-color', hexValue);
                    }
                });
            };

            const renderPreviewFocus = (input) => {
                const currentInput = input || activeColorInput || colorInputs[0] || null;
                if (!currentInput) return;

                const labelText = document.querySelector(`label[for="${currentInput.id}"]`)?.textContent?.trim() || 'Color activo';
                const meta = previewFieldDescriptions[currentInput.id] || {};
                const sectionLabel = previewSectionLabels[currentInput.dataset.section || 'base'] || 'Base visual';
                const selectedHex = String(currentInput.value || '').toUpperCase();

                if (previewLiveTitle) previewLiveTitle.textContent = labelText;
                if (previewLiveHex) previewLiveHex.textContent = selectedHex;
                if (previewLiveImpact) previewLiveImpact.textContent = meta.impact || 'Vista inmediata sobre el modulo, acciones y jerarquia visual';
                if (previewLiveScope) previewLiveScope.textContent = sectionLabel;
                if (previewLiveCopy) previewLiveCopy.textContent = meta.copy || 'El borrador reacciona en tiempo real para que veas como se comporta el cambio antes de guardar.';
                if (previewLiveNote) previewLiveNote.textContent = meta.note || 'La vista se enfoca automaticamente en el componente activo para revisar el impacto exacto.';

                document.querySelectorAll('.company-preview-selection-indicator').forEach((indicator) => {
                    const nameEl = indicator.querySelector('.company-preview-selection-name');
                    const hexEl = indicator.querySelector('.company-preview-selection-hex');
                    if (nameEl) nameEl.textContent = `Editando: ${labelText}`;
                    if (hexEl) hexEl.textContent = selectedHex;
                    indicator.setAttribute('title', `${sectionLabel} · ${meta.impact || 'Vista previa en tiempo real'}`);
                });
            };

            const setActiveColorInput = (input) => {
                if (!input) return;
                activeColorInput = input;

                colorFields.forEach((field) => field.classList.remove('is-selected'));
                const parentField = input.closest('.color-field');
                if (parentField) {
                    parentField.classList.add('is-selected');
                }

                handleSectionHighlight(input);
                renderPreviewFocus(input);
                applyThemeToPreview();
                syncPresetSelection();
            };

            const syncPresetSelection = () => {
                const current = normalizarHex(activeColorInput?.value || '', '');
                const allPresetButtons = Array.from(document.querySelectorAll('.preset-color-btn'));
                allPresetButtons.forEach((button) => {
                    const btnColor = normalizarHex(button.dataset.color || '', '');
                    const isSelected = current !== '' && btnColor === current;
                    button.classList.toggle('is-selected', isSelected);
                    button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                });
            };

            const getRecentColors = () => {
                try {
                    const raw = localStorage.getItem(recentColorsStorageKey);
                    const parsed = raw ? JSON.parse(raw) : [];
                    if (!Array.isArray(parsed)) return [];
                    return parsed
                        .map((value) => normalizarHex(value, ''))
                        .filter((value) => /^#[0-9A-F]{6}$/.test(value));
                } catch (error) {
                    return [];
                }
            };

            const saveRecentColors = (colors) => {
                try {
                    localStorage.setItem(recentColorsStorageKey, JSON.stringify(colors.slice(0, maxRecentColors)));
                } catch (error) {
                    // Sin almacenamiento, la funcionalidad principal sigue activa.
                }
            };

            const applyColorToActiveInput = (color) => {
                const targetInput = activeColorInput || colorInputs[0] || null;
                if (!targetInput) return;

                targetInput.value = color;
                setActiveColorInput(targetInput);
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                syncPresetSelection();
            };

            const renderRecentColors = () => {
                if (!recentPresetsGrid) return;
                const recentColors = getRecentColors();

                if (recentColors.length === 0) {
                    recentPresetsGrid.innerHTML = '<span style="grid-column:1 / -1; font-size:10px; color:var(--company-text); opacity:.75;">Aun no hay colores recientes.</span>';
                    return;
                }

                recentPresetsGrid.innerHTML = recentColors.map((color) => (
                    `<button type="button" class="preset-color-btn" data-color="${color}" style="background:${color};" title="${color}" aria-label="Color reciente ${color}"></button>`
                )).join('');

                recentPresetsGrid.querySelectorAll('.preset-color-btn').forEach((button) => {
                    button.addEventListener('click', () => {
                        const color = normalizarHex(button.dataset.color || '', '#FCCDC4');
                        applyColorToActiveInput(color);
                    });
                });

                syncPresetSelection();
            };

            const rememberRecentColor = (color) => {
                const normalized = normalizarHex(color, '');
                if (!/^#[0-9A-F]{6}$/.test(normalized)) return;

                const current = getRecentColors().filter((item) => item !== normalized);
                current.unshift(normalized);
                saveRecentColors(current);
                renderRecentColors();
            };

            // ===== AUTO-CONTRASTE =====
            const getContrastColor = (hexBg) => {
                const hex = String(hexBg || '#FFFFFF').replace('#', '');
                if (!/^[0-9A-Fa-f]{6}$/.test(hex)) return '#1A1A2E';
                const r = parseInt(hex.substring(0, 2), 16);
                const g = parseInt(hex.substring(2, 4), 16);
                const b = parseInt(hex.substring(4, 6), 16);
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.55 ? '#1A1A2E' : '#FFFFFF';
            };

            const applyContrastVars = (el, theme) => {
                el.style.setProperty('--contrast-navbar',        getContrastColor(theme.color_navbar || theme.color_principal));
                el.style.setProperty('--contrast-sidebar',       getContrastColor(theme.color_menu_lateral));
                el.style.setProperty('--contrast-table-header',  getContrastColor(theme.color_encabezado_tabla || theme.color_principal));
                el.style.setProperty('--contrast-table-bg',      getContrastColor(theme.color_fondo_tabla || '#FFFFFF'));
                el.style.setProperty('--contrast-table-row-alt', getContrastColor(theme.color_filas_alternas || theme.color_fondo || '#F4F6F8'));
                el.style.setProperty('--contrast-btn-crear',     getContrastColor(theme.color_btn_crear || theme.color_botones));
                el.style.setProperty('--contrast-btn-editar',    getContrastColor(theme.color_btn_editar || theme.color_botones));
                el.style.setProperty('--contrast-btn-eliminar',  getContrastColor(theme.color_btn_eliminar || '#DC3545'));
            };

            const applyThemeToContainer = (el, theme) => {
                if (!el || !theme) return;
                el.style.setProperty('--company-primary',        theme.color_principal);
                el.style.setProperty('--company-secondary',      theme.color_secundario);
                el.style.setProperty('--company-sidebar',        theme.color_menu_lateral);
                el.style.setProperty('--company-button',         theme.color_botones);
                el.style.setProperty('--company-background',     theme.color_fondo);
                el.style.setProperty('--company-text',           theme.color_texto || '#000000');
                el.style.setProperty('--company-border',         theme.color_bordes || '#DFF3DE');
                el.style.setProperty('--company-navbar',         theme.color_navbar || theme.color_principal);
                el.style.setProperty('--company-icon-color',     theme.color_iconos_menu || '#FFFFFF');
                el.style.setProperty('--company-hover-menu',     theme.color_hover_menu || theme.color_menu_lateral);
                el.style.setProperty('--company-title-color',    theme.color_titulos || theme.color_texto || theme.color_principal);
                el.style.setProperty('--company-link-color',     theme.color_links || theme.color_principal);
                el.style.setProperty('--company-table-bg',       theme.color_fondo_tabla || '#FFFFFF');
                el.style.setProperty('--company-table-text',     theme.color_texto_tabla || theme.color_texto || '#000000');
                el.style.setProperty('--company-table-header',   theme.color_encabezado_tabla || theme.color_principal);
                el.style.setProperty('--company-table-row-alt',  theme.color_filas_alternas || theme.color_fondo);
                el.style.setProperty('--company-btn-crear',      theme.color_btn_crear || theme.color_botones);
                el.style.setProperty('--company-btn-editar',     theme.color_btn_editar || theme.color_botones);
                el.style.setProperty('--company-btn-eliminar',   theme.color_btn_eliminar || '#DC3545');
                el.style.setProperty('--company-focus',          theme.color_focus_inputs || theme.color_principal);
                applyContrastVars(el, theme);
            };

            const resolvePreviewTheme = () => {
                if (previewTabMode === 'guardado' && savedTheme) {
                    return { ...savedTheme };
                }

                const liveTheme = snapshotThemeFromInputs();
                const focusInput = activeColorInput || colorInputs[0] || null;
                if (!focusInput) {
                    return liveTheme;
                }

                const baseTheme = savedTheme ? { ...savedTheme } : { ...liveTheme };
                const focusKeys = previewFieldThemeKeys[focusInput.id] || [focusInput.id];
                focusKeys.forEach((key) => {
                    if (Object.prototype.hasOwnProperty.call(liveTheme, key)) {
                        baseTheme[key] = liveTheme[key];
                    }
                });
                return baseTheme;
            };

            const applyThemeToPreview = (theme = null) => {
                if (!companyColorsPreview) return;
                const effectiveTheme = theme || resolvePreviewTheme();
                if (!effectiveTheme) return;
                applyThemeToContainer(companyColorsPreview, effectiveTheme);
                renderPreviewFocus(activeColorInput);
            };

            const applyThemeToDocument = (theme) => {
                if (!theme) return;
                const root = document.documentElement;
                applyThemeToContainer(root, theme);
                root.style.setProperty('--company-tertiary',   theme.color_menu_lateral);
                root.style.setProperty('--primary-color',      theme.color_principal);
                root.style.setProperty('--primary-dark',       theme.color_menu_lateral);
                root.style.setProperty('--primary-light',      theme.color_secundario);
                root.style.setProperty('--secondary-blue',     theme.color_secundario);
            };

            const broadcastCompanyTheme = (theme) => {
                if (!theme) return;
                const payload = {
                    at: Date.now(),
                    theme,
                };
                try {
                    localStorage.setItem(companyThemeLiveStorageKey, JSON.stringify(payload));
                } catch (error) {
                    // Si el almacenamiento local falla, el tema local ya fue aplicado.
                }
            };

            const renderLogoFallback = () => '<i class="fas fa-building"></i>';

            window.setLogoFallbackIcon = (imgElement) => {
                if (!imgElement) return;
                const wrapper = imgElement.closest('.company-logo-sample-media') || imgElement.parentElement;
                if (!wrapper) return;
                wrapper.classList.add('fallback');
                wrapper.innerHTML = '<i class="fas fa-building"></i>';
            };

            const setPreviewFullscreen = (enabled) => {
                if (!companyColorsPreview || !previewFullscreenToggle) return;
                const isEnabled = Boolean(enabled);
                companyColorsPreview.classList.toggle('preview-fullscreen', isEnabled);
                document.body.classList.toggle('preview-fullscreen-open', isEnabled);
                previewFullscreenToggle.setAttribute('aria-pressed', isEnabled ? 'true' : 'false');
                previewFullscreenToggle.innerHTML = isEnabled
                    ? '<i class="fas fa-compress-arrows-alt"></i> Cerrar vista completa'
                    : '<i class="fas fa-expand-arrows-alt"></i> Vista completa';
            };

            function refreshFullPreviewIfOpen() {
                if (!companyColorsPreview || !companyColorsPreview.classList.contains('preview-fullscreen')) return;
                applyThemeToPreview();
            }

            const setCompanyLogoPreview = (url) => {
                const finalUrl = String(url || '').trim();

                if (companyLogoSampleMedia) {
                    companyLogoSampleMedia.innerHTML = finalUrl
                        ? `<img id="companyLogoSampleImage" src="${finalUrl}" alt="Logo empresa" onerror="window.setLogoFallbackIcon(this)">`
                        : renderLogoFallback();
                }

                companyPreviewLogoTargets().forEach((target) => {
                    target.classList.remove('fallback');
                    target.innerHTML = finalUrl
                        ? `<img src="${finalUrl}" alt="Logo empresa" onerror="window.setLogoFallbackIcon(this)">`
                        : renderLogoFallback();
                });

                refreshFullPreviewIfOpen();
            };

            // ===== SAVED THEME & TRACKING =====
            let savedTheme = null;
            let previewTabMode = 'nuevo';

            const trackChangedFields = () => {
                if (!savedTheme) return 0;
                const live = snapshotThemeFromInputs();
                let count = 0;
                Object.keys(live).forEach((key) => {
                    const input = document.getElementById(key);
                    if (!input) return;
                    const field = input.closest('.color-field');
                    const changed = live[key] !== savedTheme[key];
                    if (field) field.classList.toggle('is-modified', changed);
                    if (changed) count++;
                });
                const bar = document.getElementById('previewChangedBar');
                const txt = document.getElementById('previewChangedText');
                if (bar) bar.style.display = count > 0 ? 'flex' : 'none';
                if (txt) txt.textContent = `${count} color${count !== 1 ? 'es' : ''} modificado${count !== 1 ? 's' : ''}`;
                const btnCancelar = document.getElementById('btnCancelarCambios');
                if (btnCancelar) btnCancelar.style.display = count > 0 ? '' : 'none';
                return count;
            };

            // ===== MODAL DATOS DE EMPRESA =====
            const misDatosModal         = document.getElementById('misDatosModal');
            const openMisDatosModalBtn  = document.getElementById('openMisDatosModal');
            const closeMisDatosModalBtn = document.getElementById('closeMisDatosModal');
            const empresaSlugTiendaInput = document.getElementById('empresaSlugTiendaInput');
            const empresaStoreUrlPreview = document.getElementById('empresaStoreUrlPreview');
            const empresaDatosForm       = document.getElementById('empresaDatosForm');
            const empresaCuentaBancariaForm = document.getElementById('empresaCuentaBancariaForm');
            const empresaCredencialesWompiForm = document.getElementById('empresaCredencialesWompiForm');
            const empresaNombreInput     = document.getElementById('empresaNombreInput');
            const empresaLogoInput       = document.getElementById('empresaLogoInput');
            const empresaDatosStatus     = document.getElementById('empresaDatosStatus');
            const guardarEmpresaBtn      = document.getElementById('guardarEmpresaBtn');
            const cuentaBancariaStatus   = document.getElementById('cuentaBancariaStatus');
            const guardarCuentaBancariaBtn = document.getElementById('guardarCuentaBancariaBtn');
            const credencialesWompiStatus = document.getElementById('credencialesWompiStatus');
            const guardarCredencialesWompiBtn = document.getElementById('guardarCredencialesWompiBtn');
            const empresaNombreVisual    = document.getElementById('empresaNombreVisual');
            const empresaAvatarInicial   = document.getElementById('empresaAvatarInicial');
            const empresaLogoPreviewWrap = document.getElementById('empresaLogoPreviewWrap');
            const puedeGestionEmpresaPerfilJs = <?= $puedeGestionEmpresaPerfil ? 'true' : 'false'; ?>;
            const puedeGestionEmpresaFinanzasJs = <?= $puedeGestionEmpresaFinanzas ? 'true' : 'false'; ?>;

            const empresaNombreInicial = <?= json_encode((string)($puedeGestionEmpresaPerfil ? ($empresaNombre ?: '') : ($nombrePerfilVisual ?: '')), JSON_UNESCAPED_UNICODE); ?>;
            const dashboardBaseUrl = <?= json_encode(rtrim(base_url(), '/'), JSON_UNESCAPED_UNICODE); ?>;
            const empresaContextoIdJs = <?= (int)$empresaId; ?>;
            const empresaContextoFinanzasIdJs = <?= (int)$empresaIdFinanzasContexto; ?>;
            let empresaLogoActual = <?= json_encode((string)($puedeGestionEmpresaPerfil ? $empresaImagenUrl : ''), JSON_UNESCAPED_UNICODE); ?>;
            let empresaLogoPreviewObjectUrl = null;
            const dashboardPanelTarget = <?= json_encode((string)($_GET['panel'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;

            const primeraLetraEmpresa = (texto) => {
                const limpio = String(texto || '').trim();
                if (limpio === '') return 'E';
                return limpio.substring(0, 1).toUpperCase();
            };

            const setEmpresaDatosStatus = (mensaje, esError = false) => {
                if (!empresaDatosStatus) return;
                empresaDatosStatus.classList.toggle('error', Boolean(esError));
                empresaDatosStatus.textContent = String(mensaje || '');
            };

            const setCuentaBancariaStatus = (mensaje, esError = false) => {
                if (!cuentaBancariaStatus) return;
                cuentaBancariaStatus.classList.toggle('error', Boolean(esError));
                cuentaBancariaStatus.textContent = String(mensaje || '');
            };

            const setCredencialesWompiStatus = (mensaje, esError = false) => {
                if (!credencialesWompiStatus) return;
                credencialesWompiStatus.classList.toggle('error', Boolean(esError));
                credencialesWompiStatus.textContent = String(mensaje || '');
            };


            const normalizarSlugEmpresa = (valor) => String(valor || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/[\s-]+/g, '-');

            const actualizarVistaUrlEmpresa = () => {
                if (!empresaStoreUrlPreview) return;
                const fuenteSlug = empresaNombreInput && String(empresaNombreInput.value || '').trim() !== ''
                    ? empresaNombreInput.value
                    : (empresaSlugTiendaInput ? empresaSlugTiendaInput.value : '');
                const slug = normalizarSlugEmpresa(fuenteSlug);
                if (empresaSlugTiendaInput && empresaSlugTiendaInput.value !== slug) {
                    empresaSlugTiendaInput.value = slug;
                }
                empresaStoreUrlPreview.value = slug ? `${dashboardBaseUrl}/empresa/${encodeURIComponent(slug)}` : dashboardBaseUrl;
            };

            const limpiarEmpresaLogoObjectUrl = () => {
                if (!empresaLogoPreviewObjectUrl) return;
                URL.revokeObjectURL(empresaLogoPreviewObjectUrl);
                empresaLogoPreviewObjectUrl = null;
            };

            const pintarLogoEmpresaEnModal = (logoUrl = '') => {
                if (!empresaLogoPreviewWrap) return;
                const finalUrl = String(logoUrl || '').trim();
                if (finalUrl !== '') {
                    empresaLogoPreviewWrap.innerHTML = `<img id="empresaLogoPreview" src="${finalUrl}" alt="Logo de empresa" onerror="window.setLogoFallbackIcon(this)">`;
                    return;
                }
                empresaLogoPreviewWrap.innerHTML = '<i class="fas fa-building" id="empresaLogoPlaceholder"></i>';
            };

            const sincronizarNombreEmpresaVisual = (nombre) => {
                const finalNombre = String(nombre || '').trim() || 'N/A';
                if (empresaNombreVisual) {
                    empresaNombreVisual.textContent = finalNombre;
                }
                // Solo actualizar la letra si no hay logo en el circulo
                if (empresaAvatarInicial && !empresaAvatarInicial.querySelector('img')) {
                    empresaAvatarInicial.textContent = primeraLetraEmpresa(finalNombre);
                }
            };

            const actualizarAvatarCirculo = (logoUrl) => {
                if (!empresaAvatarInicial) return;
                const url = String(logoUrl || '').trim();
                if (url !== '') {
                    empresaAvatarInicial.innerHTML = `<img src="${url}" alt="Logo empresa" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none';">`;
                } else if (!empresaAvatarInicial.querySelector('img')) {
                    empresaAvatarInicial.textContent = primeraLetraEmpresa(
                        empresaNombreVisual ? empresaNombreVisual.textContent : 'E'
                    );
                }
            };

            sincronizarNombreEmpresaVisual(empresaNombreInicial);
            pintarLogoEmpresaEnModal(empresaLogoActual);

            const openMisDatosModal = () => {
                if (!misDatosModal) return;
                if (dropdown) dropdown.classList.remove('active');
                setEmpresaDatosStatus('');
                misDatosModal.classList.add('active');
                misDatosModal.setAttribute('aria-hidden', 'false');
            };

            // Exponer funcion global para apertura directa desde el boton.
            window.openMisDatosModalFromProfile = openMisDatosModal;

            const closeMisDatosModal = () => {
                if (!misDatosModal) return;
                if (empresaLogoInput) empresaLogoInput.value = '';
                limpiarEmpresaLogoObjectUrl();
                pintarLogoEmpresaEnModal(empresaLogoActual);
                setEmpresaDatosStatus('');
                // Restablecer edicion del nombre al cerrar
                if (empresaNombreInput) {
                    empresaNombreInput.readOnly = true;
                    empresaNombreInput.classList.remove('editando');
                }
                const _cerrBtn = document.getElementById('editNombreEmpresaBtn');
                if (_cerrBtn) {
                    _cerrBtn.classList.remove('activo');
                    _cerrBtn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
                    _cerrBtn.title = 'Editar nombre';
                }
                misDatosModal.classList.remove('active');
                misDatosModal.setAttribute('aria-hidden', 'true');
            };

            if (empresaLogoInput) {
                empresaLogoInput.addEventListener('change', () => {
                    const archivo = empresaLogoInput.files && empresaLogoInput.files[0] ? empresaLogoInput.files[0] : null;
                    if (!archivo) {
                        limpiarEmpresaLogoObjectUrl();
                        pintarLogoEmpresaEnModal(empresaLogoActual);
                        return;
                    }

                    if (archivo.size > (5 * 1024 * 1024)) {
                        empresaLogoInput.value = '';
                        limpiarEmpresaLogoObjectUrl();
                        pintarLogoEmpresaEnModal(empresaLogoActual);
                        setEmpresaDatosStatus('El logo supera el tamano maximo permitido de 5MB.', true);
                        return;
                    }

                    limpiarEmpresaLogoObjectUrl();
                    empresaLogoPreviewObjectUrl = URL.createObjectURL(archivo);
                    pintarLogoEmpresaEnModal(empresaLogoPreviewObjectUrl);
                    setEmpresaDatosStatus('');
                });
            }

            if (empresaDatosForm) {
                empresaDatosForm.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    if (!puedeGestionEmpresaPerfilJs) {
                        return;
                    }

                    const nombreNormalizado = String((empresaNombreInput && empresaNombreInput.value) || '').trim();
                    if (nombreNormalizado === '') {
                        setEmpresaDatosStatus('Debes ingresar el nombre de la empresa.', true);
                        if (empresaNombreInput) empresaNombreInput.focus();
                        return;
                    }

                    if (guardarEmpresaBtn) {
                        guardarEmpresaBtn.disabled = true;
                        guardarEmpresaBtn.classList.add('cargando');
                    }
                    setEmpresaDatosStatus('');

                    try {
                        const payload = new FormData(empresaDatosForm);
                        payload.set('action', 'guardar_datos_empresa');
                        payload.set('empresa_nombre', nombreNormalizado);

                        const response = await fetch('dashboard.php', {
                            method: 'POST',
                            body: payload,
                            credentials: 'same-origin'
                        });
                        const result = await parseJsonFromResponse(response);

                        if (!response.ok || !result?.success) {
                            throw new Error(result?.message || 'No fue posible guardar los datos de la empresa.');
                        }

                        if (empresaNombreInput) empresaNombreInput.value = result.empresa_nombre || nombreNormalizado;
                        sincronizarNombreEmpresaVisual(result.empresa_nombre || nombreNormalizado);
                        if (empresaSlugTiendaInput && result.empresa_slug_tienda) {
                            empresaSlugTiendaInput.value = result.empresa_slug_tienda;
                        }
                        if (empresaStoreUrlPreview && result.empresa_store_url) {
                            empresaStoreUrlPreview.value = result.empresa_store_url;
                        }

                        if (result.logo_empresa_url) {
                            empresaLogoActual = result.logo_empresa_url;
                            pintarLogoEmpresaEnModal(empresaLogoActual);
                            actualizarAvatarCirculo(empresaLogoActual);
                            setCompanyLogoPreview(empresaLogoActual);
                        }

                        if (empresaLogoInput) empresaLogoInput.value = '';
                        limpiarEmpresaLogoObjectUrl();
                        // Restablecer modo lectura al guardar exitosamente
                        if (empresaNombreInput) {
                            empresaNombreInput.readOnly = true;
                            empresaNombreInput.classList.remove('editando');
                        }
                        const _saveBtn = document.getElementById('editNombreEmpresaBtn');
                        if (_saveBtn) {
                            _saveBtn.classList.remove('activo');
                            _saveBtn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
                        }
                        
                        // Show success alert
                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: result.message || 'Datos guardados correctamente.',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                        }
                    } catch (error) {
                        setEmpresaDatosStatus('', true);
                        
                        // Show error alert
                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error?.message || 'No fue posible guardar los datos de empresa.',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    } finally {
                        if (guardarEmpresaBtn) {
                            guardarEmpresaBtn.disabled = false;
                            guardarEmpresaBtn.classList.remove('cargando');
                        }
                    }
                });
            }


            if (empresaSlugTiendaInput) {
                empresaSlugTiendaInput.addEventListener('input', actualizarVistaUrlEmpresa);
                empresaSlugTiendaInput.addEventListener('blur', actualizarVistaUrlEmpresa);
                actualizarVistaUrlEmpresa();
            }

            if (empresaNombreInput) {
                empresaNombreInput.addEventListener('input', actualizarVistaUrlEmpresa);
                empresaNombreInput.addEventListener('blur', actualizarVistaUrlEmpresa);
            }

            if (empresaCuentaBancariaForm) {
                empresaCuentaBancariaForm.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    if (!puedeGestionEmpresaFinanzasJs) {
                        return;
                    }

                    actualizarVistaUrlEmpresa();
                    const slugNormalizado = normalizarSlugEmpresa((empresaNombreInput && empresaNombreInput.value) || (empresaSlugTiendaInput && empresaSlugTiendaInput.value) || '');
                    if (!slugNormalizado) {
                        setCuentaBancariaStatus('No fue posible generar la URL de la empresa.', true);
                        return;
                    }

                    if (guardarCuentaBancariaBtn) {
                        guardarCuentaBancariaBtn.disabled = true;
                        guardarCuentaBancariaBtn.classList.add('cargando');
                    }
                    setCuentaBancariaStatus('');

                    try {
                        const payload = new FormData(empresaCuentaBancariaForm);
                        payload.set('action', 'guardar_datos_bancarios_empresa');
                        payload.set('empresa_slug_tienda', slugNormalizado);

                        const response = await fetch('dashboard.php', {
                            method: 'POST',
                            body: payload,
                            credentials: 'same-origin'
                        });
                        const result = await parseJsonFromResponse(response);

                        if (!response.ok || !result?.success) {
                            throw new Error(result?.message || 'No fue posible guardar los datos bancarios.');
                        }

                        if (empresaSlugTiendaInput) {
                            empresaSlugTiendaInput.value = result.empresa_slug_tienda || slugNormalizado;
                        }
                        if (empresaStoreUrlPreview) {
                            empresaStoreUrlPreview.value = result.empresa_store_url || `${dashboardBaseUrl}/empresa/${encodeURIComponent(slugNormalizado)}`;
                        }

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: result.message || 'Datos bancarios guardados correctamente.',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                        }
                    } catch (error) {
                        setCuentaBancariaStatus(error?.message || 'No fue posible guardar los datos bancarios.', true);
                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error?.message || 'No fue posible guardar los datos bancarios.',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    } finally {
                        if (guardarCuentaBancariaBtn) {
                            guardarCuentaBancariaBtn.disabled = false;
                            guardarCuentaBancariaBtn.classList.remove('cargando');
                        }
                    }
                });
            }

            if (empresaCredencialesWompiForm) {
                empresaCredencialesWompiForm.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    if (!puedeGestionEmpresaFinanzasJs) {
                        return;
                    }

                    if (guardarCredencialesWompiBtn) {
                        guardarCredencialesWompiBtn.disabled = true;
                        guardarCredencialesWompiBtn.classList.add('cargando');
                    }
                    setCredencialesWompiStatus('');

                    try {
                        const payload = new FormData(empresaCredencialesWompiForm);
                        payload.set('action', 'guardar_credenciales_wompi_empresa');

                        const response = await fetch('dashboard.php', {
                            method: 'POST',
                            body: payload,
                            credentials: 'same-origin'
                        });
                        const result = await parseJsonFromResponse(response);

                        if (!response.ok || !result?.success) {
                            throw new Error(result?.message || 'No fue posible guardar las credenciales Wompi.');
                        }

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: result.message || 'Credenciales Wompi guardadas correctamente.',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                        }
                    } catch (error) {
                        setCredencialesWompiStatus(error?.message || 'No fue posible guardar las credenciales Wompi.', true);
                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error?.message || 'No fue posible guardar las credenciales Wompi.',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    } finally {
                        if (guardarCredencialesWompiBtn) {
                            guardarCredencialesWompiBtn.disabled = false;
                            guardarCredencialesWompiBtn.classList.remove('cargando');
                        }
                    }
                });
            }

            // Boton lapiz: activar/desactivar edicion del nombre de empresa
            const editNombreEmpresaBtn = document.getElementById('editNombreEmpresaBtn');
            if (editNombreEmpresaBtn && empresaNombreInput) {
                editNombreEmpresaBtn.addEventListener('click', () => {
                    const editando = !empresaNombreInput.readOnly;
                    if (editando) {
                        // Cancelar edicion
                        empresaNombreInput.value = empresaNombreInput.getAttribute('data-original') || empresaNombreInput.value;
                        empresaNombreInput.readOnly = true;
                        empresaNombreInput.classList.remove('editando');
                        editNombreEmpresaBtn.classList.remove('activo');
                        editNombreEmpresaBtn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
                        editNombreEmpresaBtn.title = 'Editar nombre';
                    } else {
                        // Activar edicion
                        empresaNombreInput.setAttribute('data-original', empresaNombreInput.value);
                        empresaNombreInput.readOnly = false;
                        empresaNombreInput.classList.add('editando');
                        editNombreEmpresaBtn.classList.add('activo');
                        editNombreEmpresaBtn.innerHTML = '<i class="fas fa-times"></i>';
                        editNombreEmpresaBtn.title = 'Cancelar edicion';
                        empresaNombreInput.focus();
                        empresaNombreInput.select();
                    }
                });
            }

            // Función para habilitar edición de campos
            window.habilitarEdicion = function(id) {
                const input = document.getElementById(id);
                if (input) {
                    input.readOnly = false;
                    input.focus();
                    input.select();
                }
            };

            if (openMisDatosModalBtn) {
                openMisDatosModalBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    openMisDatosModal();
                });
            }

            // Fallback por delegacion para botones renderizados dinamicamente
            document.addEventListener('click', (event) => {
                const misDatosTrigger = event.target.closest('#openMisDatosModal');
                if (misDatosTrigger) {
                    event.preventDefault();
                    event.stopPropagation();
                    openMisDatosModal();
                    return;
                }
            });

            if (closeMisDatosModalBtn) {
                closeMisDatosModalBtn.addEventListener('click', () => closeMisDatosModal());
            }

            if (misDatosModal) {
                misDatosModal.addEventListener('click', (event) => {
                    if (event.target === misDatosModal) closeMisDatosModal();
                });
            }

            // collapseProfileData ya no hace nada (accordion eliminado) pero se mantiene por compatibilidad
            const collapseProfileData = () => {};


            const modulePreviewProfiles = {
                Dashboard: {
                    topbar: 'Resumen general con indicadores y accesos rapidos',
                    topbarBtn: 'Filtrar',
                    heroTitle: 'Asi se visualiza tu Dashboard con la paleta activa',
                    heroText: 'Valida rapidamente contraste de tarjetas, acentos y botones de accion del tablero principal.',
                    primaryAction: 'Crear widget',
                    linkText: 'Ver metricas',
                    cards: [
                        { label: 'Panel principal', value: '87%', note: 'Resumen visual de indicadores clave y estado operativo.', button: 'Ver detalle' },
                        { label: 'Interaccion', value: 'Widgets', note: 'Comprueba foco visual en accesos rapidos y tarjetas.', button: 'Gestionar' },
                        { label: 'Acciones', value: '3 atajos', note: 'Evalua jerarquia de crear, editar y acciones secundarias.', button: 'Actualizar' },
                    ],
                    rows: ['Inventario Principal', 'Roles y Permisos', 'Empresas y Sedes'],
                },
                Empresas: {
                    topbar: 'Cabecera de empresas con filtros por sede y estado',
                    topbarBtn: 'Buscar empresa',
                    heroTitle: 'Vista de Tiendas con el estilo de tu marca',
                    heroText: 'Revisa legibilidad en listados, prioridad de botones y contraste de datos administrativos.',
                    primaryAction: 'Crear empresa',
                    linkText: 'Ver estructura',
                    cards: [
                        { label: 'Panel principal', value: 'Empresas', note: 'Muestra tarjetas de sedes, estado y datos comerciales.', button: 'Ver detalle' },
                        { label: 'Interaccion', value: 'Sedes', note: 'Verifica hover y activo en acciones de sedes y sucursales.', button: 'Gestionar' },
                        { label: 'Acciones', value: 'Registro', note: 'Valida colores en alta de empresa, edicion y eliminacion.', button: 'Actualizar' },
                    ],
                    rows: ['Empresa Norte', 'Sede Centro', 'Sucursal Industrial'],
                },
                Usuarios: {
                    topbar: 'Control de usuarios con busqueda y estado de acceso',
                    topbarBtn: 'Buscar usuario',
                    heroTitle: 'Asi se ve el modulo de Usuarios en vivo',
                    heroText: 'Comprueba contraste en formularios, tablas y acciones de alta, edicion y bloqueo.',
                    primaryAction: 'Crear usuario',
                    linkText: 'Ver permisos',
                    cards: [
                        { label: 'Panel principal', value: 'Usuarios', note: 'Visualiza tipografia y contraste en datos personales.', button: 'Ver detalle' },
                        { label: 'Interaccion', value: 'Permisos', note: 'Revisa feedback visual en cambios de rol y estado.', button: 'Gestionar' },
                        { label: 'Acciones', value: '3 botones', note: 'Comprueba botones de crear, editar y suspender usuario.', button: 'Actualizar' },
                    ],
                    rows: ['Carlos Gomez', 'Laura Castro', 'Julian Herrera'],
                },
                Roles: {
                    topbar: 'Configuracion de roles y permisos por modulo',
                    topbarBtn: 'Buscar rol',
                    heroTitle: 'Vista de Roles y permisos con tu diseno',
                    heroText: 'Valida claridad visual en matrices de permisos, etiquetas y estados de activacion.',
                    primaryAction: 'Crear rol',
                    linkText: 'Ver matriz',
                    cards: [
                        { label: 'Panel principal', value: 'Roles', note: 'Muestra jerarquia de perfiles administrativos y operativos.', button: 'Ver detalle' },
                        { label: 'Interaccion', value: 'Permisos', note: 'Revisa contraste de toggles y estados activo/inactivo.', button: 'Gestionar' },
                        { label: 'Acciones', value: 'Edicion', note: 'Comprueba experiencia visual al editar permisos masivos.', button: 'Actualizar' },
                    ],
                    rows: ['Administrador', 'Supervisor', 'Operador'],
                },
                Inventario: {
                    topbar: 'Cabecera de inventario con filtros de stock y categoria',
                    topbarBtn: 'Buscar producto',
                    heroTitle: 'Borrador de Inventario en tiempo real',
                    heroText: 'Mide contraste en tablas de productos, alertas de stock y botones operativos.',
                    primaryAction: 'Crear producto',
                    linkText: 'Ver movimientos',
                    cards: [
                        { label: 'Panel principal', value: 'Stock', note: 'Resalta niveles de inventario y estados de reposicion.', button: 'Ver detalle' },
                        { label: 'Interaccion', value: 'Lotes', note: 'Evalua navegacion entre filtros, categorias y variantes.', button: 'Gestionar' },
                        { label: 'Acciones', value: '3 botones', note: 'Valida editar, eliminar y ajustes de existencia.', button: 'Actualizar' },
                    ],
                    rows: ['Aceite 20W50', 'Filtro de aire', 'Pastillas de freno'],
                },
                Pedidos: {
                    topbar: 'Seguimiento de pedidos y estado de entrega',
                    topbarBtn: 'Buscar pedido',
                    heroTitle: 'Preview del modulo de Pedidos en tiempo real',
                    heroText: 'Comprueba legibilidad en estados, prioridad de alertas y botones de gestion.',
                    primaryAction: 'Crear pedido',
                    linkText: 'Ver trazabilidad',
                    cards: [
                        { label: 'Panel principal', value: 'Pedidos', note: 'Muestra prioridad de estados pendientes y entregados.', button: 'Ver detalle' },
                        { label: 'Interaccion', value: 'Despachos', note: 'Revisa feedback visual de seguimiento y actualizacion.', button: 'Gestionar' },
                        { label: 'Acciones', value: 'Confirmacion', note: 'Valida contraste en confirmar, editar y cancelar.', button: 'Actualizar' },
                    ],
                    rows: ['Pedido #1204', 'Pedido #1216', 'Pedido #1222'],
                },
            };

            const applyModulePreviewContext = (menuItem) => {
                const stage = menuItem?.closest('.company-preview-stage');
                if (!stage) return;

                const moduleName = (menuItem.querySelector('span')?.textContent || '').trim();
                const profile = modulePreviewProfiles[moduleName] || modulePreviewProfiles.Dashboard;

                const topbarTextEl = stage.querySelector('.company-preview-topbar .company-preview-topbar-title');
                const topbarBtnEl = stage.querySelector('.company-preview-topbar-btn');
                const heroTitleEl = stage.querySelector('.company-preview-hero-title');
                const heroTextEl = stage.querySelector('.company-preview-hero-text');
                const heroPrimaryBtn = stage.querySelector('.company-preview-inline-actions .company-preview-button');
                const heroLinkEl = stage.querySelector('.company-preview-hero-link');

                if (topbarTextEl) topbarTextEl.textContent = profile.topbar;
                if (topbarBtnEl) topbarBtnEl.textContent = profile.topbarBtn;
                if (heroTitleEl) heroTitleEl.textContent = profile.heroTitle;
                if (heroTextEl) heroTextEl.textContent = profile.heroText;
                if (heroPrimaryBtn) heroPrimaryBtn.textContent = profile.primaryAction;
                if (heroLinkEl) heroLinkEl.textContent = profile.linkText;

                const cards = Array.from(stage.querySelectorAll('.company-preview-summary .company-preview-card'));
                cards.forEach((card, idx) => {
                    const cardData = profile.cards[idx];
                    if (!cardData) return;
                    const labelEl = card.querySelector('.company-preview-card-label');
                    const valueEl = card.querySelector('.company-preview-card-value');
                    const noteEl = card.querySelector('.company-preview-card-note');
                    const btnEl = card.querySelector('.company-preview-button');
                    if (labelEl) labelEl.textContent = cardData.label;
                    if (valueEl) valueEl.textContent = cardData.value;
                    if (noteEl) noteEl.textContent = cardData.note;
                    if (btnEl) btnEl.textContent = cardData.button;
                });

                const rows = Array.from(stage.querySelectorAll('.company-preview-table .company-preview-table-row'));
                rows.forEach((row, idx) => {
                    const moduleCell = row.querySelector('span:nth-child(2)');
                    if (moduleCell && profile.rows[idx]) moduleCell.textContent = profile.rows[idx];
                });
            };

            document.addEventListener('click', (event) => {
                const clickedMenuItem = event.target.closest('.company-preview-menu-item');
                if (!clickedMenuItem) return;
                const menuContainer = clickedMenuItem.closest('.company-preview-sidebar');
                if (!menuContainer) return;

                menuContainer.querySelectorAll('.company-preview-menu-item').forEach((node) => {
                    node.classList.remove('active');
                });
                clickedMenuItem.classList.add('active');
                applyModulePreviewContext(clickedMenuItem);
            });

            const initialActiveMenuItem = document.querySelector('#companyColorsPreview .company-preview-menu-item.active')
                || document.querySelector('#companyColorsPreview .company-preview-menu-item');
            if (initialActiveMenuItem) {
                applyModulePreviewContext(initialActiveMenuItem);
            }

            colorInputs.forEach((input) => {
                input.addEventListener('focus', () => {
                    setActiveColorInput(input);
                });

                input.addEventListener('click', () => {
                    setActiveColorInput(input);
                });

                input.addEventListener('input', () => {
                    syncColorHexLabels();
                    Object.assign(companyTheme, snapshotThemeFromInputs());
                    previewTabMode = 'nuevo';
                    document.querySelectorAll('.preview-tab').forEach((t) => t.classList.toggle('active', t.dataset.tab === 'nuevo'));
                    applyThemeToPreview();
                    syncPresetSelection();
                    trackChangedFields();
                    refreshFullPreviewIfOpen();
                });

                input.addEventListener('change', () => {
                    rememberRecentColor(input.value);
                });
            });

            if (activeColorInput) {
                setActiveColorInput(activeColorInput);
            }

            renderRecentColors();
            renderPresetPalettes();
            syncPresetSelection();

            syncColorHexLabels();
            applyThemeToPreview();
            applyThemeToDocument(companyTheme);
            broadcastCompanyTheme(companyTheme);

            // ===== HELPERS DE INTENTOS Y SECCIONES =====

            const intentosBadge  = document.getElementById('intentosBadge');
            const intentosCount  = document.getElementById('intentosCount');
            const intentosRemainingHint = document.getElementById('intentosRemainingHint');
            let   intentosData   = <?= json_encode($intentosColoresInfo, JSON_UNESCAPED_UNICODE); ?>;

            const isIntentosControlActivo = () => {
                if (intentosData?.sin_restriccion) return false;
                const raw = intentosData?.disponible;
                return raw === true || raw === 1 || raw === '1' || raw === 'true';
            };

            const updateIntentosUI = (usados, tope) => {
                if (intentosData?.sin_restriccion) {
                    if (intentosCount) intentosCount.innerHTML = '&infin;';
                    const totalEl = intentosBadge?.querySelector('.intentos-total');
                    if (totalEl) totalEl.textContent = '';
                    if (intentosRemainingHint) intentosRemainingHint.textContent = 'Super administrador sin restricciones';
                    if (intentosBadge) {
                        intentosBadge.classList.remove('bloqueado');
                        const icon = intentosBadge.querySelector('i.fas');
                        if (icon) icon.className = 'fas fa-infinity';
                    }
                    const btnPagar = document.getElementById('btnPagarIntentos');
                    if (btnPagar) btnPagar.remove();
                    const btnGuardar = document.getElementById('btnGuardarColores');
                    if (btnGuardar) {
                        btnGuardar.disabled = false;
                        btnGuardar.classList.remove('bloqueado');
                        btnGuardar.setAttribute('aria-disabled', 'false');
                        btnGuardar.title = '';
                    }
                    return;
                }

                const topeNum = Number(tope);
                const usadosNum = Number(usados);
                intentosData.tope = Number.isFinite(topeNum) && topeNum > 0 ? Math.floor(topeNum) : 3;
                intentosData.usados = Number.isFinite(usadosNum)
                    ? Math.max(0, Math.min(intentosData.tope, Math.floor(usadosNum)))
                    : 0;
                const restantes     = Math.max(0, intentosData.tope - intentosData.usados);
                const bloqueado     = isIntentosControlActivo() && intentosData.usados >= intentosData.tope;

                if (intentosCount) intentosCount.textContent = String(restantes);
                if (intentosRemainingHint) {
                    intentosRemainingHint.textContent = isIntentosControlActivo()
                        ? `Usados: ${intentosData.usados}`
                        : 'Control de intentos no activo';
                }
                if (intentosBadge) {
                    intentosBadge.classList.toggle('bloqueado', bloqueado);
                    const icon = intentosBadge.querySelector('i.fas');
                    if (icon) icon.className = bloqueado ? 'fas fa-lock' : 'fas fa-lock-open';
                }

                const btnPagar = document.getElementById('btnPagarIntentos');
                const btnGuardar = document.getElementById('btnGuardarColores');
                if (bloqueado) {
                    if (!btnPagar) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn-pagar-intentos';
                        btn.id = 'btnPagarIntentos';
                        btn.innerHTML = '<i class="fas fa-credit-card"></i> Pagar 3 intentos ($40.000)';
                        intentosBadge?.appendChild(btn);
                        btn.addEventListener('click', handlePagarIntentos);
                    }
                    if (btnGuardar) {
                        btnGuardar.disabled = false;
                        btnGuardar.classList.add('bloqueado');
                        btnGuardar.setAttribute('aria-disabled', 'true');
                        btnGuardar.title = 'Debes realizar el pago para volver a guardar cambios.';
                    }
                } else {
                    if (btnPagar) btnPagar.remove();
                    if (btnGuardar) {
                        btnGuardar.disabled = false;
                        btnGuardar.classList.remove('bloqueado');
                        btnGuardar.setAttribute('aria-disabled', 'false');
                        btnGuardar.title = '';
                    }
                }
            };

            const promptPagoIntentos = async () => {
                const msgBloqueo = 'Ya usaste tus 3 cambios disponibles. Para seguir personalizando colores, activa un nuevo paquete de 3 intentos.';
                if (companyColorsStatus) {
                    companyColorsStatus.classList.add('error');
                    companyColorsStatus.textContent = msgBloqueo;
                }

                const confirmarPago = await showConfirmDialog({
                    title: 'Necesitas activar nuevos intentos',
                    message: 'Tus 3 intentos ya fueron usados. ¿Deseas ir al pago para habilitar 3 intentos adicionales por $40.000 COP?',
                    acceptText: 'Si, ir a pagar',
                    cancelText: 'Ahora no',
                });

                if (confirmarPago) {
                    handlePagarIntentos();
                }
            };

            const btnGuardarColoresEl = document.getElementById('btnGuardarColores');
            if (btnGuardarColoresEl) {
                btnGuardarColoresEl.addEventListener('click', (event) => {
                    if (isIntentosControlActivo() && intentosData.usados >= intentosData.tope) {
                        event.preventDefault();
                        event.stopPropagation();
                        promptPagoIntentos();
                    }
                });
            }

            // Secciones colapsibles
            document.querySelectorAll('.color-section-title[data-toggle]').forEach((title) => {
                title.addEventListener('click', () => {
                    const sectionId = title.dataset.toggle;
                    const section = document.getElementById(sectionId);
                    if (!section) return;
                    const willExpand = section.classList.contains('collapsed');
                    section.classList.toggle('collapsed');
                    if (willExpand) {
                        const firstInput = section.querySelector('input[type="color"]');
                        if (firstInput) {
                            setActiveColorInput(firstInput);
                        }
                    }
                });
            });

            // Resaltar sección en preview según data-section del input activo
            function handleSectionHighlight(input) {
                if (!companyColorsPreview || !input) return;
                const section = input.dataset.section || '';
                const focusTarget = previewFieldFocusTargets[input.id] || 'branding';
                companyColorsPreview.setAttribute('data-highlight-section', section);
                companyColorsPreview.setAttribute('data-highlight-target', focusTarget);
            }

            function clearSectionHighlight() {
                if (!companyColorsPreview) return;
                companyColorsPreview.removeAttribute('data-highlight-section');
                companyColorsPreview.removeAttribute('data-highlight-target');
            }

            // ===== EVENTO SUBMIT DEL FORMULARIO =====

            if (companyColorsForm) {
                companyColorsForm.addEventListener('submit', (event) => {
                    event.preventDefault();

                    if (isIntentosControlActivo() && intentosData.usados >= intentosData.tope) {
                        promptPagoIntentos();
                        return;
                    }

                    if (companyColorsStatus) {
                        companyColorsStatus.classList.remove('error');
                        companyColorsStatus.textContent = 'Guardando colores...';
                    }

                    const data    = snapshotThemeFromInputs();
                    const payload = new FormData(companyColorsForm);
                    payload.set('action', 'guardar_colores_empresa');

                    Object.entries(data).forEach(([key, val]) => payload.set(key, val));

                    fetch('dashboard.php', {
                        method: 'POST',
                        body: payload,
                        credentials: 'same-origin'
                    })
                    .then(async (response) => {
                        const result = await parseJsonFromResponse(response);
                        return { response, result };
                    })
                    .then(({ response, result }) => {
                        if (!response.ok) {
                            throw new Error(result?.message || 'No se pudo guardar la configuracion.');
                        }

                        if (!result || !result.success) {
                            if (result?.limite_intentos) {
                                if (typeof result.intentos_usados !== 'undefined' && typeof result.tope_intentos !== 'undefined') {
                                    updateIntentosUI(result.intentos_usados, result.tope_intentos);
                                }
                                promptPagoIntentos();
                            }
                            throw new Error(result?.message || 'No se pudo guardar la configuracion.');
                        }

                        if (typeof result.sin_restriccion !== 'undefined') {
                            intentosData.sin_restriccion = Boolean(result.sin_restriccion);
                        }

                        if (result.colores) Object.assign(companyTheme, result.colores);
                        if (result.logo_empresa_url) setCompanyLogoPreview(result.logo_empresa_url);

                        applyThemeToPreview();
                        applyThemeToDocument(companyTheme);
                        broadcastCompanyTheme(companyTheme);
                        refreshFullPreviewIfOpen();

                        // Sincronizar savedTheme con lo guardado
                        savedTheme = { ...companyTheme };
                        previewTabMode = 'nuevo';
                        document.querySelectorAll('.preview-tab').forEach((t) => t.classList.toggle('active', t.dataset.tab === 'nuevo'));
                        trackChangedFields();

                        if (typeof result.intentos_usados !== 'undefined' && typeof result.tope_intentos !== 'undefined') {
                            updateIntentosUI(result.intentos_usados, result.tope_intentos);
                        }

                        if (companyColorsStatus) {
                            companyColorsStatus.classList.remove('error');
                            const usadosTexto = intentosData?.sin_restriccion
                                ? ' (sin restricciones)'
                                : isIntentosControlActivo()
                                ? ` (${intentosData.usados}/${intentosData.tope} usados)`
                                : '';
                            companyColorsStatus.textContent = `Colores guardados correctamente.${usadosTexto}`;
                        }

                        const restantesActuales = isIntentosControlActivo()
                            ? Math.max(0, intentosData.tope - intentosData.usados)
                            : null;

                        showAlertSafe({
                            icon: 'success',
                            title: 'Colores guardados',
                            text: restantesActuales > 0
                                ? `Guardado correcto. Uso actual: ${intentosData.usados}/${intentosData.tope}.`
                                : 'Guardado correcto. Ya consumiste los 3 intentos y ahora debes pagar para volver a editar.',
                            confirmButtonText: 'Entendido'
                        });
                    })
                    .catch((error) => {
                        if (companyColorsStatus) {
                            companyColorsStatus.classList.add('error');
                            companyColorsStatus.textContent = error.message || 'Error al guardar colores.';
                        }
                    });
                });
            }

            // ===== BOTÓN RESTAURAR DEFAULTS =====

            const btnRestaurar = document.getElementById('btnRestaurarColores');
            if (btnRestaurar) {
                btnRestaurar.addEventListener('click', async () => {
                    const confirmed = await showConfirmDialog({
                        title: 'Restaurar colores',
                        message: '¿Restaurar todos los colores a los valores predeterminados? Esto no consume intentos.',
                        acceptText: 'Si, restaurar',
                        cancelText: 'Cancelar',
                    });
                    if (!confirmed) return;

                    const payload = new FormData();
                    payload.set('action', 'restaurar_colores_empresa');

                    fetch('dashboard.php', {
                        method: 'POST',
                        body: payload,
                        credentials: 'same-origin'
                    })
                    .then((r) => r.json())
                    .then((result) => {
                        if (!result?.success) throw new Error(result?.message || 'Error al restaurar.');

                        if (result.colores) {
                            Object.assign(companyTheme, result.colores);
                            // Actualizar inputs
                            Object.entries(result.colores).forEach(([key, val]) => {
                                const input = document.getElementById(key);
                                if (input && input.type === 'color') {
                                    input.value = val;
                                }
                            });
                            syncColorHexLabels();
                        }

                        applyThemeToPreview();
                        applyThemeToDocument(companyTheme);
                        broadcastCompanyTheme(companyTheme);
                        refreshFullPreviewIfOpen();

                        if (companyColorsStatus) {
                            companyColorsStatus.classList.remove('error');
                            companyColorsStatus.textContent = 'Colores restaurados a los valores predeterminados.';
                        }
                    })
                    .catch((err) => {
                        if (companyColorsStatus) {
                            companyColorsStatus.classList.add('error');
                            companyColorsStatus.textContent = err.message || 'Error al restaurar.';
                        }
                    });
                });
            }

            // ===== BOTÓN PAGAR / ADQUIRIR MÁS INTENTOS =====

            const asegurarWidgetWompiDashboard = async () => {
                if (typeof window.WidgetCheckout === 'function') {
                    return;
                }

                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://checkout.wompi.co/widget.js?v=' + Date.now();
                    script.async = true;
                    script.onload = () => resolve(true);
                    script.onerror = () => reject(new Error('No fue posible cargar el SDK de Wompi.'));
                    document.head.appendChild(script);
                });

                if (typeof window.WidgetCheckout !== 'function') {
                    throw new Error('Wompi no quedo disponible en el navegador.');
                }
            };

            const handlePagarIntentos = async () => {
                const confirmed = await showConfirmDialog({
                    title: 'Activar 3 nuevos intentos',
                    message: 'Se abrira la pasarela de pago para activar 3 intentos de personalizacion por $40.000 COP.',
                    acceptText: 'Continuar con el pago',
                    cancelText: 'Cancelar',
                });
                if (!confirmed) return;

                try {
                    showLoadingSafe({
                        title: 'Conectando con la pasarela',
                        text: 'Espera un momento...',
                    });

                    const payload = new FormData();
                    payload.set('action', 'crear_checkout_intentos_colores');

                    const response = await fetch('dashboard.php', {
                        method: 'POST',
                        body: payload,
                        credentials: 'same-origin'
                    });
                    const result = await parseJsonFromResponse(response);
                    if (!response.ok || !result?.success) {
                        throw new Error(result?.message || 'No fue posible iniciar el pago de intentos.');
                    }

                    await asegurarWidgetWompiDashboard();

                    const checkoutData = result.checkout || {};
                    const checkout = new WidgetCheckout({
                        currency: checkoutData.currency || 'COP',
                        amountInCents: Number(checkoutData.amountInCents || 0),
                        reference: String(checkoutData.reference || ''),
                        publicKey: String(checkoutData.publicKey || ''),
                        public_key: String(checkoutData.publicKey || ''),
                        signature: {
                            integrity: String(checkoutData.signature || '')
                        },
                        redirectUrl: String(checkoutData.redirectUrl || window.location.href)
                    });

                    closeLoadingSafe();
                    checkout.open(function (widgetResult) {
                        if (widgetResult?.transaction?.id) {
                            showAlertSafe({
                                icon: 'info',
                                title: 'Transaccion creada',
                                text: 'Completa el pago en Wompi. Cuando se apruebe, tus intentos volveran a 0/3.',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    });
                } catch (err) {
                    if (companyColorsStatus) {
                        companyColorsStatus.classList.add('error');
                        companyColorsStatus.textContent = err.message || 'No fue posible abrir la pasarela.';
                    }
                    closeLoadingSafe();
                    showAlertSafe({
                        icon: 'error',
                        title: 'Error de pago',
                        text: err?.message || 'No fue posible abrir la pasarela.',
                        confirmButtonText: 'Entendido'
                    });
                }
            };

            const btnPagarEl = document.getElementById('btnPagarIntentos');
            if (btnPagarEl) btnPagarEl.addEventListener('click', handlePagarIntentos);

            // Inicializar estado de intentos UI
            updateIntentosUI(intentosData.usados, intentosData.tope);

            const revisarRetornoPagoIntentos = async () => {
                const paymentParams = new URLSearchParams(window.location.search);
                const fromWompiIntentos = paymentParams.get('wompi_intentos');
                const reference = paymentParams.get('ref');
                if (fromWompiIntentos !== '1' || !reference) {
                    return;
                }

                try {
                    const payload = new FormData();
                    payload.set('action', 'consultar_pago_intentos_colores');
                    payload.set('reference', reference);

                    const response = await fetch('dashboard.php', {
                        method: 'POST',
                        body: payload,
                        credentials: 'same-origin'
                    });
                    const result = await parseJsonFromResponse(response);
                    if (!response.ok || !result?.success) {
                        throw new Error(result?.message || 'No se pudo verificar el pago de intentos.');
                    }

                    if (typeof result.intentos_usados !== 'undefined' && typeof result.tope_intentos !== 'undefined') {
                        updateIntentosUI(result.intentos_usados, result.tope_intentos);
                    }

                    if (String(result.estado || '').toLowerCase() === 'aprobado') {
                        showAlertSafe({
                            icon: 'success',
                            title: 'Pago aprobado',
                            text: 'Tus intentos fueron reactivados. El contador vuelve a 0/3.',
                            confirmButtonText: 'Entendido'
                        });
                    } else if (String(result.estado || '').toLowerCase() === 'pendiente') {
                        showAlertSafe({
                            icon: 'info',
                            title: 'Pago pendiente',
                            text: 'Wompi todavia esta confirmando el pago. Recarga en unos segundos si no ves el reset de intentos.',
                            confirmButtonText: 'Entendido'
                        });
                    } else {
                        showAlertSafe({
                            icon: 'warning',
                            title: 'Pago no aprobado',
                            text: 'El pago no fue aprobado y los intentos siguen bloqueados.',
                            confirmButtonText: 'Entendido'
                        });
                    }
                } catch (error) {
                    showAlertSafe({
                        icon: 'warning',
                        title: 'Verificacion pendiente',
                        text: error?.message || 'No se pudo consultar el estado del pago.',
                        confirmButtonText: 'Entendido'
                    });
                } finally {
                    const cleanUrl = new URL(window.location.href);
                    cleanUrl.searchParams.delete('wompi_intentos');
                    cleanUrl.searchParams.delete('ref');
                    window.history.replaceState({}, document.title, cleanUrl.toString());
                }
            };

            revisarRetornoPagoIntentos();

            // ===== BOTÓN CANCELAR CAMBIOS =====
            document.getElementById('btnCancelarCambios')?.addEventListener('click', () => {
                if (!savedTheme) return;
                Object.assign(companyTheme, savedTheme);
                Object.entries(savedTheme).forEach(([key, val]) => {
                    const input = document.getElementById(key);
                    if (input && input.type === 'color') input.value = val;
                });
                syncColorHexLabels();
                previewTabMode = 'nuevo';
                document.querySelectorAll('.preview-tab').forEach((t) => t.classList.toggle('active', t.dataset.tab === 'nuevo'));
                applyThemeToPreview();
                refreshFullPreviewIfOpen();
                document.querySelectorAll('#companyColorsForm .color-field').forEach((f) => f.classList.remove('is-modified'));
                const bar = document.getElementById('previewChangedBar');
                if (bar) bar.style.display = 'none';
                const btnCancelar = document.getElementById('btnCancelarCambios');
                if (btnCancelar) btnCancelar.style.display = 'none';
            });

            const updateSidebarIcon = () => {
                if (!sidebarToggleIcon) return;
                const collapsed = document.body.classList.contains('sidebar-collapsed');
                sidebarToggleIcon.className = collapsed ? 'fas fa-chevron-right' : 'fas fa-bars';
            };

            const storedCollapsed = localStorage.getItem('dashboardSidebarCollapsed');
            if (storedCollapsed === '1' && window.innerWidth > 768) {
                document.body.classList.add('sidebar-collapsed');
            }
            updateSidebarIcon();

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', (evt) => {
                    evt.stopPropagation();
                    document.body.classList.toggle('sidebar-collapsed');
                    const collapsed = document.body.classList.contains('sidebar-collapsed');
                    localStorage.setItem('dashboardSidebarCollapsed', collapsed ? '1' : '0');
                    updateSidebarIcon();
                });
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth <= 768) {
                    document.body.classList.remove('sidebar-collapsed');
                } else if (localStorage.getItem('dashboardSidebarCollapsed') === '1') {
                    document.body.classList.add('sidebar-collapsed');
                }
                updateSidebarIcon();
            });

            const showHome = () => {
                if (homePanel) homePanel.classList.remove('hidden');
                if (modulePanel) modulePanel.classList.remove('active');
                if (contentArea) contentArea.classList.remove('module-open');
                menuLinks.forEach((link) => link.classList.remove('active'));
                history.replaceState(null, '', 'dashboard.php');
            };

            // Se desactiva la recarga forzada para evitar que el dashboard se refresque solo.

            let moduleLoadTimer = null;

            if (moduleFrame) {
                moduleFrame.addEventListener('load', () => {
                    if (moduleLoadTimer) {
                        clearTimeout(moduleLoadTimer);
                        moduleLoadTimer = null;
                    }
                    if (typeof window.hideLoading === 'function') {
                        window.hideLoading();
                    }
                });

                moduleFrame.addEventListener('error', () => {
                    if (moduleLoadTimer) {
                        clearTimeout(moduleLoadTimer);
                        moduleLoadTimer = null;
                    }
                    if (typeof window.hideLoading === 'function') {
                        window.hideLoading();
                    }
                });
            }

            const liberarBasculaAntesDeNavegar = () => new Promise((resolve, reject) => {
                const vistaActual = moduleFrame?.getAttribute('src') || '';
                if (!/\/Views\/(inventarios|bascula)\.php/i.test(vistaActual) || !moduleFrame.contentWindow) {
                    resolve();
                    return;
                }
                const manejarRespuesta = (evento) => {
                    if (evento.source !== moduleFrame.contentWindow) return;
                    if (evento.data?.tipo === 'bascula-liberada-para-navegar') {
                        window.removeEventListener('message', manejarRespuesta);
                        resolve();
                    } else if (evento.data?.tipo === 'bascula-liberacion-error') {
                        window.removeEventListener('message', manejarRespuesta);
                        reject(new Error(evento.data.mensaje || 'No se pudo liberar la báscula.'));
                    }
                };
                window.addEventListener('message', manejarRespuesta);
                moduleFrame.contentWindow.postMessage({ tipo: 'liberar-bascula-antes-de-navegar' }, window.location.origin);
            });

            const openModule = async (link) => {
                if (!link || !moduleFrame || !modulePanel || !homePanel) return;
                const href = link.getAttribute('href');
                if (!href) return;

                try {
                    await liberarBasculaAntesDeNavegar();
                } catch (error) {
                    if (typeof window.hideLoading === 'function') window.hideLoading();
                    console.error('[BASCULA] no se pudo liberar antes de navegar', error);
                    return;
                }

                if (typeof window.showLoading === 'function') {
                    window.showLoading();
                }

                if (moduleLoadTimer) {
                    clearTimeout(moduleLoadTimer);
                }
                moduleLoadTimer = window.setTimeout(() => {
                    if (typeof window.hideLoading === 'function') {
                        window.hideLoading();
                    }
                }, 6000);

                const moduleUrl = new URL(href, window.location.href);
                // Pasar la cookie de sesión al iframe para que pueda acceder a la sesión
                const sessionCookie = document.cookie.split(';').find(cookie => cookie.trim().startsWith('PHPSESSID='));
                if (sessionCookie) {
                    const sessionId = sessionCookie.split('=')[1];
                    moduleUrl.searchParams.set('PHPSESSID', sessionId);
                }
                moduleFrame.src = moduleUrl.href;
                homePanel.classList.add('hidden');
                modulePanel.classList.add('active');
                if (contentArea) contentArea.classList.add('module-open');
                menuLinks.forEach((item) => item.classList.remove('active'));
                link.classList.add('active');
                history.replaceState(null, '', `dashboard.php?vista=${encodeURIComponent(href)}`);
            };

            menuLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    openModule(link);
                });
            });

            if (menuHome) {
                menuHome.addEventListener('click', async () => {
                    try {
                        await liberarBasculaAntesDeNavegar();
                    } catch (error) {
                        console.error('[BASCULA] no se pudo liberar al volver al inicio', error);
                        return;
                    }
                    if (moduleLoadTimer) {
                        clearTimeout(moduleLoadTimer);
                        moduleLoadTimer = null;
                    }
                    if (moduleFrame) moduleFrame.src = '';
                    if (typeof window.hideLoading === 'function') {
                        window.hideLoading();
                    }
                    showHome();
                });
            }

            const params = new URLSearchParams(window.location.search);
            const vista = params.get('vista');
            if (ES_CLIENTE_DASHBOARD) {
                if (homePanel) homePanel.classList.add('hidden');
                if (modulePanel) modulePanel.classList.add('active');
                if (contentArea) contentArea.classList.add('module-open');
                if (moduleFrame && moduleFrame.getAttribute('src') !== CLIENTE_DASHBOARD_VIEW) {
                    moduleFrame.src = CLIENTE_DASHBOARD_VIEW;
                }
                history.replaceState(null, '', `dashboard.php?vista=${encodeURIComponent(CLIENTE_DASHBOARD_VIEW)}`);
            } else if (vista) {
                const selected = menuLinks.find((link) => link.getAttribute('href') === vista);
                if (selected) {
                    openModule(selected);
                } else {
                    showHome();
                }
            } else {
                showHome();
            }

            if (toggle && dropdown) {
                toggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    if (errorsDropdown) {
                        errorsDropdown.classList.remove('active');
                    }
                    const willOpen = !dropdown.classList.contains('active');
                    dropdown.classList.toggle('active');
                    if (willOpen) {
                        collapseProfileData();
                    }
                });

                dropdown.addEventListener('click', (event) => {
                    event.stopPropagation();
                });

                if (profileDropdownClose) {
                    profileDropdownClose.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        dropdown.classList.remove('active');
                    });
                }

                if (errorsToggle && errorsDropdown) {
                    errorsToggle.addEventListener('click', (event) => {
                        event.stopPropagation();
                        dropdown.classList.remove('active');
                        errorsDropdown.classList.toggle('active');
                    });
                }

                if (errorsDropdown) {
                    errorsDropdown.addEventListener('click', (event) => {
                        event.stopPropagation();
                    });
                }

                if (errorsDropdownClose && errorsDropdown) {
                    errorsDropdownClose.addEventListener('click', (event) => {
                        event.stopPropagation();
                        errorsDropdown.classList.remove('active');
                    });
                }

                document.addEventListener('click', () => {
                    dropdown.classList.remove('active');
                    if (errorsDropdown) {
                        errorsDropdown.classList.remove('active');
                    }
                });
            }

            const getSeverityClass = (value) => {
                const sev = String(value || '').toUpperCase();
                if (sev === 'FATAL') return 'fatal';
                if (sev === 'WARNING' || sev === 'WARN') return 'warning';
                if (sev === 'NOTICE') return 'notice';
                return 'info';
            };

            const getStatusClass = (value) => {
                const status = String(value || '').toUpperCase();
                if (status === 'OK') return 'ok';
                if (status === 'WARN' || status === 'WARNING') return 'warn';
                return 'error';
            };

            const copiarTextoPortapapeles = async (text) => {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                        return true;
                    }

                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-9999px';
                    document.body.appendChild(textarea);
                    textarea.focus();
                    textarea.select();
                    const ok = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    return ok;
                } catch {
                    return false;
                }
            };

            const renderErrorSummary = (data) => {
                if (!errorsToggle || !errorsToggleIcon || !errorsToggleCount || !errorsDropdownContent) {
                    return;
                }

                const resumen = (data && data.eventos && data.eventos.resumen) ? data.eventos.resumen : {};
                const eventos = (data && data.eventos && Array.isArray(data.eventos.lista)) ? data.eventos.lista : [];
                const resultados = Array.isArray(data?.resultados) ? data.resultados : [];
                const logPath = data?.log_path || 'No encontrada';
                const totalEventos = Number(data?.eventos?.total ?? (Array.isArray(eventos) ? eventos.length : 0));
                const ventanaMin = Number(data?.eventos?.ventana_minutos || 0);
                const scopeLabel = String(data?.eventos?.scope_label || `ultimos ${ventanaMin} min`);

                errorsToggleCount.textContent = `(${totalEventos})`;

                if (totalEventos > 0) {
                    errorsToggle.classList.remove('ok');
                    errorsToggleIcon.className = 'fas fa-exclamation-triangle';
                } else {
                    errorsToggle.classList.add('ok');
                    errorsToggleIcon.className = 'fas fa-check-circle';
                }

                const resultadosHtml = resultados.slice(0, 40).map((r) => {
                    const st = escapeHtml(String(r.status || 'OK').toUpperCase());
                    const stClass = getStatusClass(st);
                    const chk = escapeHtml(r.check || '');
                    const det = escapeHtml(r.detail || '');
                    return `<tr><td style="padding:6px;border-bottom:1px solid var(--company-border);">${chk}</td><td style="padding:6px;border-bottom:1px solid var(--company-border);"><span class="error-pill ${stClass}">${st}</span></td><td style="padding:6px;border-bottom:1px solid var(--company-border);">${det}</td></tr>`;
                }).join('');

                const eventosHtml = eventos.slice(0, 80).map((ev) => {
                    const sev = escapeHtml(String(ev.severity || 'INFO').toUpperCase());
                    const sevClass = getSeverityClass(sev);
                    const file = escapeHtml(ev.file || 'Archivo no identificado');
                    const line = escapeHtml(ev.line || '');
                    return `
                        <tr>
                            <td style="padding:6px;border-bottom:1px solid var(--company-border);white-space:nowrap;"><span class="error-pill ${sevClass}">${sev}</span></td>
                            <td style="padding:6px;border-bottom:1px solid var(--company-border);">${file}</td>
                            <td style="padding:6px;border-bottom:1px solid var(--company-border);"><div style="max-width:360px;white-space:normal;word-break:break-word;">${line}</div></td>
                        </tr>
                    `;
                }).join('');

                errorsDropdownContent.innerHTML = `
                    <div class="errors-file-block">
                        <div class="errors-file-head">
                            <span class="errors-file-name">Errores del sistema (${escapeHtml(scopeLabel)})</span>
                            <button type="button" class="errors-copy-btn" id="btnCopyDiag">Copiar diagnostico</button>
                        </div>
                        <div style="font-size:12px;line-height:1.6;color: var(--company-text);">
                            <span class="error-pill info">TOTAL: ${totalEventos}</span>
                            <span class="error-pill fatal">FATAL: ${Number(resumen.FATAL || 0)}</span>
                            <span class="error-pill warning">WARNING: ${Number(resumen.WARNING || 0)}</span>
                            <span class="error-pill notice">NOTICE: ${Number(resumen.NOTICE || 0)}</span>
                            <span class="error-pill info">INFO: ${Number(resumen.INFO || 0)}</span>
                        </div>
                    </div>

                    <div class="errors-file-block">
                        <div class="errors-file-head"><span class="errors-file-name">Resultados</span></div>
                        <div style="overflow:auto;max-height:320px;">
                            <table style="width:100%;border-collapse:collapse;font-size:11px;">
                                <thead>
                                    <tr>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid var(--company-border);">Check</th>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid var(--company-border);">Estado</th>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid var(--company-border);">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${resultadosHtml || '<tr><td colspan="3" style="padding:8px;">Sin resultados.</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="errors-file-block">
                        <div class="errors-file-head"><span class="errors-file-name">Detalle de eventos</span></div>
                        <div style="font-size:11px;color: var(--company-text);margin-bottom:6px;">Ruta detectada: ${escapeHtml(logPath)}</div>
                        <div style="overflow:auto;max-height:420px;">
                            <table style="width:100%;border-collapse:collapse;font-size:11px;">
                                <thead>
                                    <tr>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid var(--company-border);">Tipo</th>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid var(--company-border);">Archivo</th>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid var(--company-border);">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${eventosHtml || '<tr><td colspan="3" style="padding:8px;">No hay eventos para mostrar.</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;

                const btnCopyDiag = document.getElementById('btnCopyDiag');
                if (btnCopyDiag) {
                    btnCopyDiag.addEventListener('click', async (event) => {
                        event.stopPropagation();
                        const text = [
                            `Errores del sistema (${scopeLabel})`,
                            `TOTAL: ${totalEventos} | FATAL: ${Number(resumen.FATAL || 0)} | WARNING: ${Number(resumen.WARNING || 0)} | NOTICE: ${Number(resumen.NOTICE || 0)} | INFO: ${Number(resumen.INFO || 0)}`,
                            '',
                            'Resultados',
                            ...resultados.map(r => `- [${String(r.status || 'OK').toUpperCase()}] ${r.check || ''}: ${r.detail || ''}`),
                            '',
                            `Detalle de eventos`,
                            `Ruta detectada: ${logPath}`,
                            ...eventos.map(ev => `- [${String(ev.severity || 'INFO').toUpperCase()}] ${ev.file || 'Archivo no identificado'} :: ${ev.line || ''}`)
                        ].join('\n');

                        const copied = await copiarTextoPortapapeles(text);
                        if (copied) {
                            btnCopyDiag.textContent = 'Copiado';
                            setTimeout(() => {
                                btnCopyDiag.textContent = 'Copiar diagnostico';
                            }, 1400);
                        } else {
                            btnCopyDiag.textContent = 'No se pudo copiar';
                            setTimeout(() => {
                                btnCopyDiag.textContent = 'Copiar diagnostico';
                            }, 1400);
                        }
                    });
                }
            };

            const renderErrorSummaryUnavailable = (reason = '') => {
                if (!errorsToggle || !errorsToggleIcon || !errorsToggleCount || !errorsDropdownContent) {
                    return;
                }

                errorsToggle.classList.remove('ok');
                errorsToggleIcon.className = 'fas fa-exclamation-triangle';
                errorsToggleCount.textContent = '(0)';

                const reasonSafe = escapeHtml(String(reason || 'No se recibio respuesta JSON valida.'));

                errorsDropdownContent.innerHTML = `
                    <div class="errors-file-block">
                        <div class="errors-file-head">
                            <span class="errors-file-name">Diagnostico temporalmente no disponible</span>
                        </div>
                        <div style="font-size:11px;color: var(--company-text);line-height:1.45;">
                            ${reasonSafe}<br>
                            Endpoint: ${escapeHtml(String(diagnosticoEndpoint || 'No definido'))}
                        </div>
                    </div>
                `;
            };

            const cargarDiagnosticoErrores = () => {
                if (!errorsToggle) {
                    return;
                }

                if (!usarDiagnosticoAjax) {
                    renderErrorSummary(diagnosticoLocalPayload || {
                        success: true,
                        eventos: {
                            scope: 'active',
                            scope_label: 'activos',
                            ventana_minutos: 90,
                            total: 0,
                            resumen: { FATAL: 0, WARNING: 0, NOTICE: 0, INFO: 0 },
                            lista: []
                        },
                        resultados: [],
                        log_path: 'No encontrada'
                    });
                    return;
                }

                const controller = new AbortController();
                const timeoutId = window.setTimeout(() => controller.abort(), 12000);

                fetch(`${diagnosticoEndpoint}?scope=active&minutes=90&include_info=0&include_notice=0&max_list=250`, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    cache: 'no-store',
                    signal: controller.signal
                })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status} en diagnostico`);
                    }
                    return response.text();
                })
                .then((rawText) => {
                    const text = String(rawText || '').trim();
                    if (text === '') {
                        throw new Error('Respuesta vacia');
                    }

                    try {
                        return JSON.parse(text);
                    } catch {
                        // Si hay ruido antes/despues del JSON (warnings/notices), intenta recuperar el bloque JSON.
                        const start = text.indexOf('{');
                        const end = text.lastIndexOf('}');
                        if (start >= 0 && end > start) {
                            return JSON.parse(text.slice(start, end + 1));
                        }
                        throw new Error('Respuesta no parseable como JSON');
                    }
                })
                .then((data) => renderErrorSummary(data))
                .catch((err) => {
                    renderErrorSummaryUnavailable(err && err.message ? err.message : 'Fallo desconocido al cargar diagnostico.');
                })
                .finally(() => {
                    window.clearTimeout(timeoutId);
                });
            };

            cargarDiagnosticoErrores();
            if (usarDiagnosticoAjax) {
                setInterval(cargarDiagnosticoErrores, 20000);
            }

            const rolModal = document.getElementById('rolModal');
            const rolModalTitle = document.getElementById('rolModalTitle');
            const rolModalList = document.getElementById('rolModalList');
            const rolModalClose = document.getElementById('rolModalClose');
            const rolButtons = document.querySelectorAll('.rol-modal-btn');
            const usuariosEnLinea = Array.from(document.querySelectorAll('.usuario-en-linea'));
            const openRolModal = (rol, color) => {
                if (!rolModal || !rolModalTitle || !rolModalList) return;

                rolModalTitle.textContent = rol;
                const usuariosRol = usuariosEnLinea.filter((usuario) => usuario.dataset.rol === rol);

                if (usuariosRol.length === 0) {
                    rolModalList.innerHTML = '<div class="chip" style="background: color-mix(in srgb, var(--company-secondary) 18%, #ffffff); color: var(--company-secondary); border: 1px solid color-mix(in srgb, var(--company-secondary) 35%, #ffffff); padding: 10px;">No hay usuarios en linea para este rol</div>';
                } else {
                    rolModalList.innerHTML = usuariosRol.map((usuario) => {
                        const itemColor = usuario.dataset.color || color || '#8e9aaf';
                        return `
                            <div class="rol-modal-item" style="border-color: ${itemColor}66; background: linear-gradient(135deg, ${itemColor}25, ${itemColor}15);">
                                <div>
                                    <div class="rol-modal-name">${usuario.dataset.nombre || ''}</div>
                                    <div class="rol-modal-role" style="color: var(--company-text);"><i class="fas fa-shield-alt"></i> ${rol}</div>
                                </div>
                                <div class="rol-modal-meta">
                                    <div><i class="fas fa-sign-in-alt"></i> ${usuario.dataset.hora || ''}</div>
                                    <div class="rol-modal-duration"><i class="fas fa-hourglass-half"></i> ${usuario.dataset.tiempo || ''}</div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }

                rolModal.classList.add('active');
            };

            const closeRolModal = () => {
                if (!rolModal) return;
                rolModal.classList.remove('active');
            };

            rolButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    openRolModal(button.dataset.rol, button.dataset.color);
                });
            });

            if (rolModalClose) {
                rolModalClose.addEventListener('click', closeRolModal);
            }

            if (rolModal) {
                rolModal.addEventListener('click', (event) => {
                    if (event.target === rolModal) {
                        closeRolModal();
                    }
                });
            }

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeRolModal();
                    if (errorsDropdown) {
                        errorsDropdown.classList.remove('active');
                    }
                }
            });

        });
    </script>

    <script>
        // Gráficas del Dashboard
        window.escapeHtml = window.escapeHtml || ((value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;'));
        const escapeHtml = window.escapeHtml;
        const chartTypes = {
            ventasChart: 'pie',
            entradasChart: 'pie',
            salidasChart: 'pie',
            movimientosChart: 'pie',
            stockChart: 'pie'
        };
        const charts = [];
        const chartProductSummaries = {};
        let movimientosTipo = 'entradas';
        let lastResumenProductos = null;
        let lastVentasPorMes = null;
        const productColorPalette = ['#264653', '#2a9d8f', '#e9c46a', '#f4a261', '#e76f51', '#6a4c93', '#1b3b5f', '#3b3f5c', '#4f6d7a', '#7d3c98'];

        const formatQuantityColombia = (valor) => {
            const numero = Number(valor) || 0;
            return numero.toLocaleString('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        };

        const getQuantityUnit = (product) => {
            const value = product?.venta_por_kilo ?? product?.ventaPorKilo ?? product?.unidad;
            const unidadNormalizada = String(value ?? '').trim().toLowerCase();
            const esProductoPorKilo = ['1', 'true', 'si', 'sí', 'kg', 'kilo', 'kilogramo', 'kilogramos'].includes(unidadNormalizada);
            return esProductoPorKilo ? 'KG' : 'UN';
        };

        const formatQuantityWithUnit = (valor, product) => `${formatQuantityColombia(valor)} ${getQuantityUnit(product)}`;

        const formatSalesCurrency = (valor) => '$' + (Number(valor) || 0).toLocaleString('es-CO', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });

        const formatAxisQuantityColombia = (valor) => {
            const numero = Number(valor) || 0;
            return numero.toLocaleString('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        };

        const getChartInstance = (canvas) => {
            if (!canvas) return null;
            return typeof Chart.getChart === 'function'
                ? Chart.getChart(canvas)
                : charts.find((entry) => entry && entry.canvas && entry.canvas.id === canvas.id);
        };

        const destroyChartInstance = (chartId) => {
            const chartCanvas = document.getElementById(chartId);
            if (!chartCanvas) return null;
            const existingChart = getChartInstance(chartCanvas);
            if (!existingChart) return null;
            const existingIndex = charts.findIndex((entry) => entry && entry.canvas && entry.canvas.id === chartId);
            if (existingIndex >= 0) {
                charts.splice(existingIndex, 1);
            }
            try {
                existingChart.destroy();
            } catch (e) {
                console.warn('Error destroying chart', e);
            }
            return existingChart;
        };

        const recreateChart = (chartId) => {
            const chartCanvas = document.getElementById(chartId);
            if (!chartCanvas) return;

            const products = Array.isArray(chartProductSummaries[chartId]) ? chartProductSummaries[chartId] : [];
            const labels = products.map((product) => String(product.nombre || '').trim());
            const values = products.map((product) => Number(product.valor ?? product.ventas_30dias ?? product.entradas_30dias ?? 0));
            const productColors = products.map((product, index) => String(product.color || product.color_hex || product.color_producto || productColorPalette[index % productColorPalette.length] || '#3b82f6'));
            const isBarChart = chartTypes[chartId] === 'bar';
            const ctx = chartCanvas.getContext('2d');
            const existingChart = getChartInstance(chartCanvas);
            if (existingChart) {
                destroyChartInstance(chartId);
            }

            const wrapper = chartCanvas.parentElement;
            const wrapperWidth = wrapper?.clientWidth || chartCanvas.width || 300;
            if (isBarChart) {
                chartCanvas.width = wrapperWidth;
                chartCanvas.style.width = '100%';
                const canvasHeight = Math.max(400, labels.length * 42 + 60);
                chartCanvas.height = canvasHeight;
                chartCanvas.style.height = `${canvasHeight}px`;
            } else {
                const pieSize = Math.min(Math.max(wrapperWidth * 0.68, 200), 240);
                chartCanvas.width = pieSize;
                chartCanvas.height = pieSize;
                chartCanvas.style.width = '100%';
                chartCanvas.style.height = `${pieSize}px`;
            }
            if (wrapper) {
                wrapper.parentElement?.classList.toggle('chart-body-bar-mode', isBarChart);
                wrapper.classList.toggle('chart-wrapper-fixed-height', isBarChart && labels.length > 8);
                wrapper.classList.toggle('chart-bar-mode', isBarChart);
                wrapper.classList.toggle('chart-circle-mode', !isBarChart);
            }

            const chartLabels = isBarChart && labels.length > 8 ? labels.slice(0, 8) : labels;
            const chartData = isBarChart && values.length > 8 ? values.slice(0, 8) : values;
            const chartColors = isBarChart && productColors.length > 8 ? productColors.slice(0, 8) : productColors;
            const chartLabelsFull = labels;
            const chartDataFull = values;
            const chartColorsFull = productColors;

            const dataset = {
                label: chartValueLabels[chartId] || '',
                data: chartDataFull,    
                backgroundColor: chartColorsFull,
                borderColor: chartColorsFull,
                borderWidth: chartLabelsFull.map(() => 1),
                borderRadius: 6,
                minBarLength: 0,
                maxBarThickness: isBarChart ? 20 : 56,
                barThickness: isBarChart ? 16 : 42,
                categoryPercentage: isBarChart ? 0.9 : 0.8,
                barPercentage: isBarChart ? 0.95 : 0.9
            };

            const barMaxValue = Math.max(...chartDataFull.map((x) => Number(x) || 0));
            const smallStepScale = barMaxValue > 0 && barMaxValue <= 10;
            const xTickOptions = {
                color: '#666',
                autoSkip: false,
                maxRotation: 0,
                minRotation: 0,
                font: { size: 8 },
                precision: 0,
                stepSize: smallStepScale ? 1 : undefined,
                callback: (value) => {
                    if (!Number.isInteger(value)) return '';
                    return value;
                }
            };

            const chart = new Chart(ctx, {
                type: chartTypes[chartId],
                data: {
                    labels: chartLabelsFull,
                    datasets: [dataset]
                },
                plugins: [],
                options: {
                    responsive: false,
                    maintainAspectRatio: false,
                        devicePixelRatio: isBarChart ? Math.max(window.devicePixelRatio || 1, 1.5) : undefined,
                    animation: { duration: 260 },
                    interaction: { mode: 'nearest', intersect: false },
                    indexAxis: isBarChart ? 'y' : 'x',
                    layout: {
                        padding: { top: 10, bottom: 10, left: 8, right: 8 }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            callbacks: {
                                label: (context) => {
                                    const parsedValue = context.parsed?.x ?? context.parsed?.y ?? context.parsed;
                                    const product = chartProductSummaries[chartId]?.[context.dataIndex];
                                    const valueLabel = chartId === 'ventasChart'
                                        ? formatSalesCurrency(parsedValue)
                                        : formatQuantityWithUnit(parsedValue, product);
                                    return `${context.label}: ${valueLabel}`;
                                }
                            }
                        }
                    },
                    scales: isBarChart ? {
                        x: {
                            beginAtZero: true,
                            min: 0,
                            suggestedMax: smallStepScale ? barMaxValue + 1 : undefined,
                            ticks: xTickOptions,
                            grid: { color: 'rgba(148, 163, 184, 0.16)' }
                        },
                        y: {
                            ticks: {
                                color: '#666',
                                autoSkip: false,
                                maxRotation: 0,
                                minRotation: 0,
                                font: { size: 12, weight: '600' }
                            },
                            grid: { display: false }
                        }
                    } : {
                        x: { display: false, grid: { display: false }, ticks: { display: false } },
                        y: { display: false, beginAtZero: true, ticks: { display: false }, grid: { display: false } }
                    },
                    onClick: (event, elements) => {
                        if (!elements || !elements.length) return;
                        const activeElement = elements[0];
                        const selectedLabel = labels[activeElement.index];
                        if (selectedLabel) {
                            highlightChartProduct(chartId, selectedLabel);
                        }
                    }
                }
            });

            charts.push(chart);
            applySelectionStateToChart(chartId, chart, false);

            try {
                const panelEl = document.getElementById(`chartListPanel-${chartId}`);
                if (panelEl) {
                    if (isBarChart) {
                        panelEl.classList.add('scrollable');
                    } else {
                        panelEl.classList.remove('scrollable');
                    }
                }
            } catch (e) {
                console.warn('No se pudo ajustar scroll panel:', e);
            }
        };

        const getSelectedChartIndex = (chartId, chart = null) => {
            const selectedName = String(chartSelectionState[chartId] || '').trim().toUpperCase();
            if (!selectedName) {
                return -1;
            }
            const targetChart = chart || charts.find((entry) => entry && entry.canvas && entry.canvas.id === chartId);
            if (!targetChart || !Array.isArray(targetChart.data?.labels)) {
                return -1;
            }
            return targetChart.data.labels.findIndex((label) => String(label || '').trim().toUpperCase() === selectedName);
        };


        const updateSelectionBadge = (chartId, productName = '', color = '', value = 0) => {
            const badge = document.getElementById(`selectionBadge-${chartId}`);
            if (!badge) {
                return;
            }
            const normalizedName = String(productName || '').trim();
            badge.classList.toggle('is-visible', Boolean(normalizedName));
            if (!normalizedName) {
                badge.innerHTML = '';
                return;
            }
            const resolvedColor = String(color || '').trim() || '#3b82f6';
            const resolvedValue = Number(value || 0);
            const selectedProduct = (chartProductSummaries[chartId] || []).find((product) => String(product.nombre || '').trim().toUpperCase() === normalizedName);
            const badgeValue = chartId === 'ventasChart'
                ? formatSalesCurrency(resolvedValue)
                : formatQuantityWithUnit(resolvedValue, selectedProduct);
            badge.innerHTML = `<span class="badge-dot" style="background:${escapeHtml(resolvedColor)}"></span><span class="badge-text" style="color:${escapeHtml(resolvedColor)}">${escapeHtml(normalizedName)}</span><span class="badge-value" style="color:${escapeHtml(resolvedColor)}">${escapeHtml(badgeValue)}</span>`;
        };

        const getColorForKey = (key, fallbackColor = null) => {
            if (fallbackColor && /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(fallbackColor)) {
                return fallbackColor;
            }
            const text = String(key || 'default');
            let hash = 0;
            for (let i = 0; i < text.length; i++) {
                hash = ((hash << 5) - hash) + text.charCodeAt(i);
                hash |= 0;
            }
            const index = Math.abs(hash) % productColorPalette.length;
            return productColorPalette[index];
        };

        const buildSelectionAwareColors = (chartId, labels, baseColors) => {
            const selectedName = String(chartSelectionState[chartId] || '').trim().toUpperCase();
            return labels.map((label, index) => {
                const normalizedLabel = String(label || '').trim().toUpperCase();
                const baseColor = baseColors[index] || '#3b82f6';
                if (selectedName && normalizedLabel === selectedName) {
                    return baseColor;
                }
                return baseColor;
            });
        };

        function formatMesLabel(mes) {
            if (!mes) return '';
            const parts = String(mes).split('-');
            if (parts.length !== 2) return mes;
            const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            const m = Number(parts[1]) - 1;
            const anio = String(parts[0] || '').slice(-2);
            return `${meses[m] || parts[1]} ${anio}`;
        }

        const chartListTitles = {
            ventasChart: 'Productos más vendidos',
            entradasChart: 'Productos que entran',
            salidasChart: 'Productos que salen',
            movimientosChart: 'Productos con movimiento',
            stockChart: 'Productos con más stock'
        };

        const chartValueLabels = {
            ventasChart: 'Ventas',
            entradasChart: 'Entradas',
            salidasChart: 'Salidas',
            movimientosChart: 'Movimientos',
            stockChart: 'Stock'
        };

        const renderChartListPanel = (chartId) => {
            const panel = document.getElementById(`chartListPanel-${chartId}`);
            if (!panel) return;
            const items = Array.isArray(chartProductSummaries[chartId]) ? chartProductSummaries[chartId] : [];
            const title = (chartListTitles[chartId] || 'LISTADO DE PRODUCTOS').toUpperCase();
            const normalizedItems = Array.isArray(items)
                ? items
                    .filter((item) => String(item?.nombre || '').trim() !== '')
                    .map((item) => ({
                        ...item,
                        nombre: String(item?.nombre || 'SIN NOMBRE').trim(),
                        valor: Number(item?.valor ?? item?.ventas_30dias ?? item?.entradas_30dias ?? 0),
                        venta_por_kilo: item?.venta_por_kilo ?? 0,
                        categoria: String(item?.categoria || item?.categoria_nombre || item?.category || item?.grupo || 'SIN CATEGORÍA').trim()
                    }))
                : [];

            const groupItemsByCategory = (collection) => {
                const groups = new Map();
                collection.forEach((item) => {
                    const categoryKey = String(item.categoria || 'SIN CATEGORÍA').trim();
                    const categoryLabel = categoryKey === '' ? 'SIN CATEGORÍA' : categoryKey;
                    if (!groups.has(categoryLabel)) {
                        groups.set(categoryLabel, []);
                    }
                    groups.get(categoryLabel).push(item);
                });
                return Array.from(groups.entries()).map(([category, categoryItems]) => ({ category, categoryItems }));
            };

            const selectedProduct = String(chartSelectionState[chartId] || '').trim().toUpperCase();
            const clearButtonHtml = '';
            const closeButtonHtml = `<button type="button" class="chart-close-panel-btn" onclick="closeChartListPanel('${chartId}')">✕</button>`;

            if (normalizedItems.length === 0) {
                panel.innerHTML = `
                    <div class="chart-inline-list-header">
                        <div class="chart-inline-list-title">${title}</div>
                        <div class="chart-inline-list-controls">${clearButtonHtml}${closeButtonHtml}</div>
                    </div>
                    <div class="chart-list-empty">NO HAY DATOS DISPONIBLES PARA ESTE LISTADO.</div>
                `;
            } else {
                const groupedItems = groupItemsByCategory(normalizedItems)
                    .map(({ category, categoryItems }) => ({
                        category,
                        categoryItems: categoryItems.sort((a, b) => Number(b.valor) - Number(a.valor))
                    }))
                    .sort((a, b) => a.category.localeCompare(b.category, 'es', { sensitivity: 'base' }));
                panel.innerHTML = `
                    <div class="chart-inline-list-header">
                        <div class="chart-inline-list-title">${title}</div>
                        <div class="chart-inline-list-controls">${clearButtonHtml}${closeButtonHtml}</div>
                    </div>
                    ${groupedItems.map(({ category, categoryItems }) => `
                        <div class="chart-inline-list-group">
                            <div class="chart-inline-list-group-title">${escapeHtml(category)}</div>
                            ${categoryItems.map((item) => `
                                <div class="chart-inline-list-item" data-chart-product="${escapeHtml(item.nombre)}" data-chart-id="${escapeHtml(chartId)}" data-chart-value="${escapeHtml(String(item.valor))}">
                                    <div class="chart-inline-list-item-main">
                                        <span class="product-color-chip" style="background:${escapeHtml(item.color || '#d1d5db')};"></span>
                                        <span class="product-label">${escapeHtml(item.nombre)}</span>
                                    </div>
                                    <span class="product-value">${chartId === 'ventasChart' ? formatSalesCurrency(item.valor) : formatQuantityWithUnit(item.valor, item)}</span>
                                </div>
                            `).join('')}
                        </div>
                    `).join('')}
                `;

                panel.querySelectorAll('.chart-inline-list-item').forEach((item) => {
                    const productName = String(item.getAttribute('data-chart-product') || item.dataset.chartProduct || '').trim();
                    const isSelected = Boolean(selectedProduct && productName && productName.toUpperCase() === selectedProduct);
                    item.classList.toggle('is-selected', isSelected);
                    item.style.cursor = 'pointer';
                    item.setAttribute('role', 'button');
                    item.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        if (!productName) return;
                        chartSelectionState[chartId] = String(productName || '').trim().toUpperCase();
                        highlightChartProduct(chartId, productName);
                        closeChartListPanel(chartId);
                    });
                });
            }
        };

        const chartSelectionState = {};

        const normalizeAngle = (angle) => {
            const twopi = Math.PI * 2;
            let normalized = angle % twopi;
            if (normalized < 0) {
                normalized += twopi;
            }
            return normalized;
        };

        const getCurrentChartRotation = (chart) => {
            const options = chart?.options || chart?.config?.options;
            return Number(options?.rotation ?? -0.5 * Math.PI);
        };

        const getSelectionRotationForChart = (chartId, chart = null) => {
            const selectedName = String(chartSelectionState[chartId] || '').trim().toUpperCase();
            const targetChart = chart || charts.find((entry) => entry && entry.canvas && entry.canvas.id === chartId);
            if (!targetChart || !Array.isArray(targetChart.data?.labels) || targetChart.data.labels.length === 0) {
                return -0.5 * Math.PI;
            }
            const labels = targetChart.data.labels;
            const index = labels.findIndex((label) => String(label || '').trim().toUpperCase() === selectedName);
            if (index < 0) {
                return -0.5 * Math.PI;
            }
            const meta = targetChart.getDatasetMeta?.(0);
            const point = meta?.data?.[index];
            if (point && typeof point.startAngle === 'number' && typeof point.circumference === 'number') {
                const currentRotation = normalizeAngle(getCurrentChartRotation(targetChart));
                const pointMidAngle = normalizeAngle(point.startAngle + (point.circumference / 2));
                const desiredTopAngle = normalizeAngle(-0.5 * Math.PI);
                let delta = normalizeAngle(desiredTopAngle - pointMidAngle);
                if (delta > Math.PI) {
                    delta -= Math.PI * 2;
                }
                return currentRotation + delta;
            }
            const anglePerSlice = (Math.PI * 2) / labels.length;
            return (-0.5 * Math.PI) - ((index + 0.5) * anglePerSlice);
        };

        const animateChartRotation = (chart, startRotation, endRotation, duration = 1200) => {
            if (!chart) {
                return;
            }
            const options = chart.config?.options || chart.options;
            if (!options) {
                return;
            }
            const startTime = performance.now();
            const tick = (timestamp) => {
                const progress = Math.min(1, (timestamp - startTime) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);
                const currentRotation = startRotation + (endRotation - startRotation) * eased;
                options.rotation = currentRotation;
                if (chart.config?.options) {
                    chart.config.options.rotation = currentRotation;
                }
                if (chart.options) {
                    chart.options.rotation = currentRotation;
                }
                chart.draw();
                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    options.rotation = endRotation;
                    if (chart.config?.options) {
                        chart.config.options.rotation = endRotation;
                    }
                    if (chart.options) {
                        chart.options.rotation = endRotation;
                    }
                    chart.draw();
                }
            };
            requestAnimationFrame(tick);
        };

        const applySelectionStateToChart = (chartId, chart = null, shouldAnimate = false) => {
            const selectedName = String(chartSelectionState[chartId] || '').trim().toUpperCase();
            const targetChart = chart || charts.find((entry) => entry && entry.canvas && entry.canvas.id === chartId);
            if (!targetChart || !Array.isArray(targetChart.data?.labels)) {
                return false;
            }

            const labels = targetChart.data.labels;
            const baseColors = Array.isArray(targetChart.data.datasets?.[0]?.backgroundColor)
                ? targetChart.data.datasets[0].backgroundColor
                : labels.map((_, index) => productColorPalette[index % productColorPalette.length]);
            const selectionColors = buildSelectionAwareColors(chartId, labels, baseColors);
            if (targetChart.data.datasets?.[0]) {
                const selectedFillColor = '#ffffff';
                targetChart.data.datasets[0].backgroundColor = labels.map((label) => {
                    const normalizedLabel = String(label || '').trim().toUpperCase();
                    return selectedName && normalizedLabel === selectedName ? baseColors[labels.indexOf(label)] || '#3b82f6' : (baseColors[labels.indexOf(label)] || '#3b82f6');
                });
                targetChart.data.datasets[0].borderAlign = 'inner';
                targetChart.data.datasets[0].borderColor = labels.map((label) => {
                    const normalizedLabel = String(label || '').trim().toUpperCase();
                    return selectedName && normalizedLabel === selectedName ? '#ffd54a' : 'rgba(255,255,255,0.0)';
                });
                targetChart.data.datasets[0].borderWidth = labels.map((label) => {
                    const normalizedLabel = String(label || '').trim().toUpperCase();
                    return selectedName && normalizedLabel === selectedName ? 4.2 : 0;
                });
                targetChart.data.datasets[0].hoverBorderColor = labels.map((label) => {
                    const normalizedLabel = String(label || '').trim().toUpperCase();
                    return selectedName && normalizedLabel === selectedName ? '#ffd54a' : 'rgba(255,255,255,0.0)';
                });
                targetChart.data.datasets[0].hoverBorderWidth = labels.map((label) => {
                    const normalizedLabel = String(label || '').trim().toUpperCase();
                    return selectedName && normalizedLabel === selectedName ? 4.2 : 0;
                });
                targetChart.data.datasets[0].shadowColor = labels.map((label) => {
                    const normalizedLabel = String(label || '').trim().toUpperCase();
                    return selectedName && normalizedLabel === selectedName ? 'rgba(255, 213, 74, 0.45)' : 'rgba(0,0,0,0)';
                });
                targetChart.data.datasets[0].offset = labels.map((label) => {
                    const normalizedLabel = String(label || '').trim().toUpperCase();
                    return selectedName && normalizedLabel === selectedName ? 0 : 0;
                });
            }
            try {
                targetChart.update('none');
            } catch (e) {
                targetChart.draw();
            }
            const productsForChart = chartProductSummaries[chartId] || [];
            const found = productsForChart.find((p) => String(p.nombre || '').toUpperCase() === selectedName);
            if (selectedName) {
                const chartLabel = found ? String(found.nombre || selectedName) : String(selectedName);
                updateSelectionBadge(chartId, chartLabel, found ? found.color : '', found ? Number(found.valor || 0) : 0);
                hideSelectionTooltip(chartId);
            } else {
                updateSelectionBadge(chartId, '', '', 0);
                hideSelectionTooltip(chartId);
            }
            return Boolean(selectedName);
        };

        const highlightChartProduct = (chartId, productName) => {
            const normalizedName = String(productName || '').trim().toUpperCase();
            if (!normalizedName) {
                return;
            }

            chartSelectionState[chartId] = normalizedName;
            const chart = charts.find((entry) => entry && entry.canvas && entry.canvas.id === chartId);
            applySelectionStateToChart(chartId, chart, false);
            if (chart && chartTypes[chartId] === 'bar') {
                const selectedIndex = chart.data.labels.findIndex((label) => String(label || '').trim().toUpperCase() === normalizedName);
                const wrapper = chart.canvas?.parentElement;
                const point = selectedIndex >= 0 ? chart.getDatasetMeta(0)?.data?.[selectedIndex] : null;
                if (wrapper && point && Number.isFinite(Number(point.y))) {
                    wrapper.scrollTo({
                        top: Math.max(0, Number(point.y) - (wrapper.clientHeight / 2)),
                        behavior: 'smooth'
                    });
                }
            }
            document.querySelectorAll(`.chart-inline-list-item[data-chart-id="${chartId}"]`).forEach((item) => {
                item.classList.toggle('is-selected', String(item.dataset.chartProduct || '').toUpperCase() === normalizedName);
            });
            try {
                const productsForChart = chartProductSummaries[chartId] || [];
                const found = productsForChart.find((p) => String(p.nombre || '').toUpperCase() === normalizedName);
                updateSelectionBadge(chartId, found ? found.nombre : productName, found ? found.color : '', found ? Number(found.valor || 0) : 0);
            } catch (e) { console.warn(e); }
            hideSelectionTooltip(chartId);
            // cerrar paneles de lista al seleccionar
            try { closeChartListPanel(chartId); } catch (e) { }
        };

        // Tooltip overlay for selection (persistent until user clears)
        function showSelectionTooltip(chartId, productName, value, chart = null) {
            try {
                const canvas = document.getElementById(chartId);
                if (!canvas) return;
                let container = document.getElementById(`chartSelectionTooltipContainer-${chartId}`);
                if (!container) {
                    container = document.createElement('div');
                    container.id = `chartSelectionTooltipContainer-${chartId}`;
                    container.className = 'chart-selection-tooltip-container';
                    const canvasParent = canvas.parentElement || document.body;
                    if (getComputedStyle(canvasParent).position === 'static') canvasParent.style.position = 'relative';
                    canvasParent.appendChild(container);
                }
                container.style.position = 'absolute';
                container.style.left = '0';
                container.style.top = '0';
                container.style.width = '100%';
                container.style.height = '100%';
                container.style.pointerEvents = 'none';
                container.style.zIndex = '1000';
                let tooltip = container.querySelector('.chart-selection-tooltip');
                if (!tooltip) {
                    tooltip = document.createElement('div');
                    tooltip.className = 'chart-selection-tooltip';
                    container.appendChild(tooltip);
                }
                const lbl = escapeHtml(String(productName || ''));
                const tooltipProduct = (chartProductSummaries[chartId] || []).find((product) => String(product.nombre || '').trim().toUpperCase() === String(productName || '').trim().toUpperCase());
                const tooltipValue = chartId === 'ventasChart' ? formatSalesCurrency(value) : formatQuantityWithUnit(value, tooltipProduct);
                tooltip.innerHTML = `<div class="tooltip-label">${lbl}</div><div class="tooltip-value">${tooltipValue}</div><button class="tooltip-clear-btn" aria-label="Quitar selección">✕</button>`;
                tooltip.style.position = 'absolute';
                tooltip.style.display = 'inline-flex';
                tooltip.style.zIndex = '1001';
                tooltip.style.pointerEvents = 'auto';
                const btn = tooltip.querySelector('.tooltip-clear-btn');
                if (btn) {
                    btn.addEventListener('click', () => {
                        try { clearChartSelection(chartId); } catch (e) {}
                        try { hideSelectionTooltip(chartId); } catch (e) {}
                    });
                }
                positionSelectionOverlay(chartId, chart);
            } catch (e) {
                console.warn('showSelectionTooltip error', e);
            }
        }

        function hideSelectionTooltip(chartId) {
            try {
                const container = document.getElementById(`chartSelectionTooltipContainer-${chartId}`);
                if (container) {
                    const tooltip = container.querySelector('.chart-selection-tooltip');
                    if (tooltip) {
                        tooltip.style.display = 'none';
                    }
                }
            } catch (e) {}
        }

        const closeAllChartSelectionTooltips = () => {
            document.querySelectorAll('.chart-selection-tooltip-container').forEach((container) => {
                if (container) {
                    const tooltip = container.querySelector('.chart-selection-tooltip');
                    if (tooltip) {
                        tooltip.style.display = 'none';
                    }
                }
            });
        };

        const reapplyPersistedChartSelection = () => {
            Object.keys(chartSelectionState).forEach((chartId) => {
                const selectedName = String(chartSelectionState[chartId] || '').trim().toUpperCase();
                if (!selectedName) {
                    return;
                }
                const chart = charts.find((entry) => entry && entry.canvas && entry.canvas.id === chartId);
                if (!chart) {
                    return;
                }
                applySelectionStateToChart(chartId, chart, false);
                const productsForChart = chartProductSummaries[chartId] || [];
                const found = productsForChart.find((p) => String(p.nombre || '').toUpperCase() === selectedName);
                if (found) {
                    updateSelectionBadge(chartId, found.nombre, found.color, Number(found.valor || 0));
                } else {
                    updateSelectionBadge(chartId, '', '', 0);
                }
                document.querySelectorAll(`.chart-inline-list-item[data-chart-id="${chartId}"]`).forEach((item) => {
                    item.classList.toggle('is-selected', String(item.dataset.chartProduct || '').toUpperCase() === selectedName);
                });
            });
        };

        const closeChartListPanels = () => {
            document.querySelectorAll('.chart-inline-list-panel').forEach((panel) => {
                panel.innerHTML = '';
                panel.classList.remove('active');
                panel.setAttribute('aria-hidden', 'true');
            });
            document.querySelectorAll('.chart-list-btn').forEach((button) => {
                button.setAttribute('aria-expanded', 'false');
            });
        };

        window.toggleChartListPanel = (chartId) => {
            const panel = document.getElementById(`chartListPanel-${chartId}`);
            const button = document.querySelector(`[data-chart-list-target="${chartId}"]`);
            if (!panel) return;
            const isOpen = panel.classList.contains('active');
            closeChartListPanels();
            if (!isOpen) {
                renderChartListPanel(chartId);
                // abrir panel solo cuando el usuario hizo click en LISTA
                panel.classList.add('active');
                panel.setAttribute('aria-hidden', 'false');
                if (button) {
                    button.setAttribute('aria-expanded', 'true');
                }
            }
        };

        window.closeChartListPanel = (chartId) => {
            const panel = document.getElementById(`chartListPanel-${chartId}`);
            if (!panel) return;
            panel.classList.remove('active');
            panel.setAttribute('aria-hidden', 'true');
            panel.innerHTML = '';
            const button = document.querySelector(`[data-chart-list-target="${chartId}"]`);
            if (button) {
                button.setAttribute('aria-expanded', 'false');
            }
        };

        // No cerrar los paneles al hacer clic fuera; el cierre debe ser explícito con el botón X.
        // document.addEventListener('click', (event) => {
        //     if (!event.target.closest('.chart-inline-list-panel') && !event.target.closest('.chart-list-wrapper')) {
        //         closeChartListPanels();
        //     }
        // });

        document.addEventListener('click', (event) => {
            const chartCanvas = event.target.closest('canvas');
            if (!chartCanvas) return;
            const chartId = chartCanvas.id;
            const chart = charts.find((entry) => entry && entry.canvas && entry.canvas.id === chartId);
            if (!chart) return;
            const activePoints = chart.getElementsAtEventForMode(event, 'nearest', { intersect: false }, true);
            if (!activePoints || activePoints.length === 0) return;
            const point = activePoints[0];
            const selectedLabel = chart.data.labels[point.index];
            if (!selectedLabel) return;
            highlightChartProduct(chartId, selectedLabel);
        });

        closeChartListPanels();

        // Establecer tipo de gráfica explícito
        function setChartType(chartId, type) {
            chartTypes[chartId] = type;
            updateChart(chartId);
        }

        // Alternar tipo de gráfica si se necesita compatibilidad
        function toggleChart(chartId) {
            const siguienteTipo = chartTypes[chartId] === 'pie' ? 'bar' : 'pie';
            setChartType(chartId, siguienteTipo);
        }

        // Actualizar gráfica específica
        function updateChart(chartId) {
            const chartCanvas = document.getElementById(chartId);
            const chart = chartCanvas && typeof Chart.getChart === 'function'
                ? Chart.getChart(chartCanvas)
                : charts.find(c => c.canvas.id === chartId);
            if (!chart) {
                return;
            }

            const isBarChart = chartTypes[chartId] === 'bar';
            const currentChartType = chart.config.type;
            if (currentChartType !== chartTypes[chartId]) {
                destroyChartInstance(chartId);
                recreateChart(chartId);
                return;
            }
            chart.config.type = chartTypes[chartId];
            chart.config.options.indexAxis = isBarChart ? 'y' : 'x';
            chart.config.options.maintainAspectRatio = false;
            chart.config.options.animation = {
                duration: 260
            };
            chart.config.options.plugins.tooltip.displayColors = false;
            chart.config.options.plugins.tooltip.callbacks.label = (context) => {
                const parsedValue = context.parsed?.x ?? context.parsed?.y ?? context.parsed;
                                    return `${context.label}: ${formatQuantityColombia(parsedValue)}`;
            };

            if (isBarChart) {
                chart.config.data.datasets[0].barThickness = 16;
                chart.config.data.datasets[0].maxBarThickness = 20;
                chart.config.data.datasets[0].borderRadius = 6;
                chart.config.data.datasets[0].minBarLength = 0;
                const barMaxValue = Math.max(...(chart.config.data.datasets[0].data || []).map((x) => Number(x) || 0));
                const isTinyRange = barMaxValue > 0 && barMaxValue <= 10;
                const barStep = isTinyRange ? 1 : 5;
                const xTickOptions = {
                    color: '#666',
                    autoSkip: false,
                    maxRotation: 0,
                    minRotation: 0,
                    font: { size: 8 },
                    precision: 0,
                    stepSize: barMaxValue > 0 ? barStep : undefined,
                    callback: (value) => {
                        if (!Number.isInteger(value)) return '';
                        return chartId === 'ventasChart' ? formatSalesCurrency(value) : formatAxisQuantityColombia(value);
                    }
                };
                chart.config.options.scales = {
                    x: {
                        beginAtZero: true,
                        min: 0,
                        suggestedMax: barMaxValue > 0 ? Math.ceil((barMaxValue + 1) / barStep) * barStep : undefined,
                        ticks: xTickOptions,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.16)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#666',
                            autoSkip: false,
                            maxRotation: 0,
                            minRotation: 0,
                            font: { size: 12, weight: '600' }
                        },
                        grid: {
                            display: false
                        }
                    }
                };
                destroyChartInstance(chartId);
                recreateChart(chartId);
                return;
            } else {
                chart.config.options.scales = {
                    x: {
                        display: false,
                        grid: {
                            display: false
                        },
                        ticks: {
                            display: false
                        }
                    },
                    y: {
                        display: false,
                        beginAtZero: true,
                        ticks: {
                            display: false
                        },
                        grid: {
                            display: false
                        }
                    }
                };
            }

            chart.update();
            applySelectionStateToChart(chartId, chart, false);
        }

        // Inicializar gráficas
        // Función para refrescar los datos del dashboard desde módulos externos
        window.refrescarDashboard = function() {
            const ventasCanvas = document.getElementById('ventasChart');
            if (!ventasCanvas) {
                console.log('Dashboard: gráficos no encontrados');
                return;
            }

            console.log('Dashboard: refrescando datos...');

            const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#F67019', '#1E8FBE', '#8E44AD'];
            const topCount = 6;

            const syncSelectionStateWithLabels = (chartId, labels) => {
                const selectedName = String(chartSelectionState[chartId] || '').trim().toUpperCase();
                if (!selectedName) {
                    return;
                }
                const exists = Array.isArray(labels) && labels.some((label) => String(label || '').trim().toUpperCase() === selectedName);
                if (!exists) {
                    delete chartSelectionState[chartId];
                    updateSelectionBadge(chartId, '', '', 0);
                    hideSelectionTooltip(chartId);
                }
            };

            const buildChartConfig = (ctx, chartId, labels, data, colors = null) => {
                // Destruir gráfico anterior si existe
                const chartTarget = ctx && ctx.canvas ? ctx.canvas : ctx;
                const existingChart = typeof Chart.getChart === 'function'
                    ? Chart.getChart(chartTarget)
                    : (Chart.helpers && Chart.helpers.get ? Chart.helpers.get(ctx).chart : null);
                if (existingChart) {
                    existingChart.destroy();
                }

                const isBarChart = chartTypes[chartId] === 'bar';
                const canvasWidth = ctx?.canvas?.parentElement?.clientWidth || ctx?.canvas?.width || 300;
                if (ctx && ctx.canvas) {
                    if (isBarChart) {
                        ctx.canvas.width = canvasWidth;
                        ctx.canvas.style.width = '100%';
                        const canvasHeight = Math.max(400, labels.length * 42 + 60);
                        ctx.canvas.height = canvasHeight;
                        ctx.canvas.style.height = `${canvasHeight}px`;
                    } else {
                        const pieSize = Math.min(Math.max(canvasWidth * 0.68, 200), 240);
                        ctx.canvas.width = pieSize;
                        ctx.canvas.height = pieSize;
                        ctx.canvas.style.width = '100%';
                        ctx.canvas.style.height = `${pieSize}px`;
                    }
                }

                const backgroundColors = Array.isArray(colors) && colors.length === data.length
                    ? colors
                    : labels.map((_, index) => productColorPalette[index % productColorPalette.length]);

                const wrapper = ctx?.canvas?.parentElement;
                const chartLabels = isBarChart && labels.length > 8 ? labels.slice(0, 8) : labels;
                const chartData = isBarChart && data.length > 8 ? data.slice(0, 8) : data;
                const chartColors = isBarChart && backgroundColors.length > 8 ? backgroundColors.slice(0, 8) : backgroundColors;
                if (wrapper) {
                    wrapper.parentElement?.classList.toggle('chart-body-bar-mode', isBarChart);
                    wrapper.classList.toggle('chart-wrapper-fixed-height', isBarChart && labels.length > 8);
                    wrapper.classList.toggle('chart-bar-mode', isBarChart);
                    wrapper.classList.toggle('chart-circle-mode', !isBarChart);
                }
                const chartLabelsFull = labels;
                const chartDataFull = data;
                const chartColorsFull = backgroundColors;

                const dataset = {
                    label: chartValueLabels[chartId] || '',
                    data: chartDataFull,
                    backgroundColor: chartColorsFull,
                    borderColor: chartColorsFull,
                    borderWidth: chartLabelsFull.map(() => 1),
                    borderRadius: 6,
                    minBarLength: 0,
                    maxBarThickness: isBarChart ? 20 : 56,
                    barThickness: isBarChart ? 16 : 42,
                    categoryPercentage: isBarChart ? 0.9 : 0.8,
                    barPercentage: isBarChart ? 0.95 : 0.9
                };

                const chart = new Chart(ctx, {
                    type: chartTypes[chartId],
                    data: {
                        labels: chartLabelsFull,
                        datasets: [dataset]
                    },
                    plugins: [],
                    options: {
                        responsive: false,
                        maintainAspectRatio: false,
                        devicePixelRatio: isBarChart ? Math.max(window.devicePixelRatio || 1, 1.5) : undefined,
                        animation: {
                            duration: 260
                        },
                        interaction: {
                            mode: 'nearest',
                            intersect: false
                        },
                        indexAxis: isBarChart ? 'y' : 'x',
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10,
                                left: 8,
                                right: 8
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                displayColors: false,
                                callbacks: {
                                    label: (context) => {
                                        const parsedValue = context.parsed?.x ?? context.parsed?.y ?? context.parsed;
                                        const product = chartProductSummaries[chartId]?.[context.dataIndex];
                                        const valueLabel = chartId === 'ventasChart'
                                            ? formatSalesCurrency(parsedValue)
                                            : formatQuantityWithUnit(parsedValue, product);
                                        return `${context.label}: ${valueLabel}`;
                                    }
                                }
                            }
                        },
                        scales: isBarChart ? (() => {
                            const barMaxValue = data.length > 0 ? Math.max(...data.map((x) => Number(x) || 0)) : 0;
                            const isTinyRange = barMaxValue > 0 && barMaxValue <= 10;
                            const barStep = isTinyRange ? 1 : 5;
                            const xTickOptions = {
                                color: '#666',
                                autoSkip: false,
                                maxRotation: 0,
                                minRotation: 0,
                                font: {
                                    size: 8
                                },
                                precision: 0,
                                stepSize: barMaxValue > 0 ? barStep : undefined,
                                callback: (value) => {
                                    if (!Number.isInteger(value)) return '';
                                    return chartId === 'ventasChart' ? formatSalesCurrency(value) : formatAxisQuantityColombia(value);
                                }
                            };
                            return {
                                x: {
                                    beginAtZero: true,
                                    min: 0,
                                    suggestedMax: barMaxValue > 0 ? Math.ceil((barMaxValue + 1) / barStep) * barStep : undefined,
                                    ticks: xTickOptions,
                                    grid: {
                                        color: 'rgba(148, 163, 184, 0.16)'
                                    }
                                },
                                y: {
                                    ticks: {
                                        color: '#666',
                                        autoSkip: false,
                                        maxRotation: 0,
                                        minRotation: 0,
                                        font: {
                                            size: 11,
                                            weight: '600'
                                        }
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            };
                        })() : {
                            x: {
                                display: false,
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    display: false
                                }
                            },
                            y: {
                                display: false,
                                beginAtZero: true,
                                ticks: {
                                    display: false
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        onClick: (event, elements) => {
                            if (!elements || !elements.length) {
                                return;
                            }
                            const activeElement = elements[0];
                            const selectedLabel = labels[activeElement.index];
                            if (selectedLabel) {
                                highlightChartProduct(chartId, selectedLabel);
                            }
                        }
                    }
                });
                const existingIndex = charts.findIndex((c) => c && c.canvas && c.canvas.id === chartId);
                if (existingIndex >= 0) {
                    charts[existingIndex] = chart;
                } else {
                    charts.push(chart);
                }
                syncSelectionStateWithLabels(chartId, labels);
                applySelectionStateToChart(chartId, chart, false);

                // ensure overlay container exists for selection tooltip
                const containerId = `chartSelectionTooltipContainer-${chartId}`;
                if (!document.getElementById(containerId)) {
                    const container = document.createElement('div');
                    container.id = containerId;
                    container.className = 'chart-selection-tooltip-container';
                    const canvasParent = ctx.canvas.parentElement || document.body;
                    canvasParent.style.position = canvasParent.style.position || 'relative';
                    canvasParent.appendChild(container);
                }
                // Ajustar panel de lista: permitir scroll solo para gráficos de barras
                try {
                    const panelEl = document.getElementById(`chartListPanel-${chartId}`);
                    if (panelEl) {
                        if (isBarChart) {
                            panelEl.classList.add('scrollable');
                        } else {
                            panelEl.classList.remove('scrollable');
                        }
                    }
                } catch (e) {
                    console.warn('No se pudo ajustar scroll panel:', e);
                }
            };

        // badge removed; selection handled by chart tooltip overlay

            function buildChartSeries(productos, labelKey, valueKey, includeZeroValues = false) {
                const products = productos
                    .map((item) => ({
                        nombre: String(item[labelKey] || 'SIN NOMBRE').toUpperCase(),
                        valor: Number(item[valueKey] || 0),
                        venta_por_kilo: item.venta_por_kilo ?? 0,
                        categoria: String(item.categoria || item.categoria_nombre || item.category || item.grupo || 'SIN CATEGORÍA').toUpperCase(),
                        color: getColorForKey(item[labelKey], item.color || item.color_hex || item.color_producto)
                    }))
                    .filter((product) => includeZeroValues || Number(product.valor) > 0);

                return {
                    labels: products.map((product) => product.nombre),
                    values: products.map((product) => product.valor),
                    products
                };
            }

            function buildSummary(productos) {
                const ventasOrdenadas = [...productos]
                    .filter((item) => Number(item.ventas_30dias || 0) > 0)
                    .sort((a, b) => Number(b.ventas_30dias || 0) - Number(a.ventas_30dias || 0));
                const entradasOrdenadas = [...productos]
                    .filter((item) => Number(item.entradas_30dias || 0) > 0)
                    .sort((a, b) => Number(b.entradas_30dias || 0) - Number(a.entradas_30dias || 0));
                const stockOrdenado = [...productos]
                    .sort((a, b) => Number(b.stock || 0) - Number(a.stock || 0));
                const movimientosOrdenados = [...productos]
                    .map((item) => {
                        const ventas = Number(item.ventas_30dias || 0);
                        const entradas = Number(item.entradas_30dias || 0);
                        let valor = ventas + entradas;
                        if (movimientosTipo === 'entradas') valor = entradas;
                        if (movimientosTipo === 'salidas') valor = ventas;
                        return {
                            ...item,
                            movimientos_30dias: valor
                        };
                    })
                    .filter((item) => Number(item.movimientos_30dias || 0) > 0)
                    .sort((a, b) => Number(b.movimientos_30dias) - Number(a.movimientos_30dias));

                let ventas;
                const hasMonthlySalesContext = lastVentasPorMes && (
                    Number.isFinite(Number(lastVentasPorMes.ventas)) ||
                    Number.isFinite(Number(lastVentasPorMes.ventas_mes_anterior)) ||
                    String(lastVentasPorMes.mes || '').trim() !== ''
                );

                if (hasMonthlySalesContext) {
                    const mesActual = String(lastVentasPorMes.mes || '').trim();
                    const ventasVal = Number(lastVentasPorMes.ventas || 0);
                    const mesLabel = formatMesLabel(mesActual) || 'Mes actual';

                    if (mesActual) {
                        ventas = {
                            labels: [mesLabel],
                            values: [ventasVal],
                            products: [{ nombre: mesLabel, valor: ventasVal, color: productColorPalette[0] }]
                        };
                    } else {
                        ventas = buildChartSeries(ventasOrdenadas, 'nombre', 'ventas_30dias');
                    }
                } else {
                    ventas = buildChartSeries(ventasOrdenadas, 'nombre', 'ventas_30dias');
                }
                const entradas = buildChartSeries(entradasOrdenadas, 'nombre', 'entradas_30dias');
                const salidas = buildChartSeries(ventasOrdenadas, 'nombre', 'ventas_30dias');
                const movimientos = buildChartSeries(movimientosOrdenados, 'nombre', 'movimientos_30dias');
                const stock = buildChartSeries(stockOrdenado, 'nombre', 'stock', true);

                chartProductSummaries.ventasChart = ventas.products;
                chartProductSummaries.entradasChart = entradas.products;
                chartProductSummaries.salidasChart = salidas.products;
                chartProductSummaries.stockChart = stock.products;

                // Guardar último resumen para permitir re-render sin re-fetch
                lastResumenProductos = productos;

                const ventasCtx = document.getElementById('ventasChart').getContext('2d');
                const entradasCtx = document.getElementById('entradasChart').getContext('2d');
                const salidasCtx = document.getElementById('salidasChart').getContext('2d');
                const stockCtx = document.getElementById('stockChart').getContext('2d');

                buildChartConfig(ventasCtx, 'ventasChart', ventas.labels, ventas.values, ventas.products.map((product) => product.color));
                buildChartConfig(entradasCtx, 'entradasChart', entradas.labels, entradas.values, entradas.products.map((product) => product.color));
                buildChartConfig(salidasCtx, 'salidasChart', salidas.labels, salidas.values, salidas.products.map((product) => product.color));
                buildChartConfig(stockCtx, 'stockChart', stock.labels, stock.values, stock.products.map((product) => product.color));
                reapplyPersistedChartSelection();
            }

            function obtenerFechaBogota() {
                const formatter = new Intl.DateTimeFormat('en-CA', {
                    timeZone: 'America/Bogota',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
                const parts = formatter.formatToParts(new Date());
                const map = {};
                parts.forEach((part) => {
                    if (part.type !== 'literal') {
                        map[part.type] = part.value;
                    }
                });
                return new Date(`${map.year}-${map.month}-${map.day}T${map.hour}:${map.minute}:${map.second}`);
            }

            const selector = document.getElementById('dashboardMesFiltro');
            const currentDate = obtenerFechaBogota();
            const currentMonthValue = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`;
            let mesSeleccionado = '';
            if (selector && selector.value) {
                mesSeleccionado = selector.value;
            }
            if (!mesSeleccionado && selector) {
                mesSeleccionado = currentMonthValue;
                selector.value = currentMonthValue;
            }
            const query = mesSeleccionado ? `&mes=${encodeURIComponent(mesSeleccionado)}` : '';
            console.log('Dashboard: mesSeleccionado=', mesSeleccionado, ' query=', query);
            fetch('<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8'); ?>/Controllers/InventarioController.php?action=obtenerResumen' + query)
                .then((response) => {
                    if (!response.ok) throw new Error('Error de red al cargar resumen de inventario');
                    return response.json();
                })
                .then((data) => {
                    console.log('Dashboard: respuesta backend requested_mes=', data?.requested_mes, 'items=', Array.isArray(data?.data) ? data.data.length : 0);
                    const productos = Array.isArray(data?.data) ? data.data : [];
                    // Ventas por mes se mostrará en el panel de 'VENTAS POR MES' (chart)
                    try {
                        const ventasInfo = data?.ventas_por_mes;
                        lastVentasPorMes = ventasInfo || null;
                    } catch (e) {
                        console.warn('No se pudo actualizar ventas por mes card', e);
                    }
                    if (productos.length === 0) {
                        lastResumenProductos = [{ nombre: 'Sin datos', ventas_30dias: 0, entradas_30dias: 0, stock: 0 }];
                        return buildSummary(lastResumenProductos);
                    }
                    lastResumenProductos = productos;
                    buildSummary(productos);
                    console.log('Dashboard: datos refrescados');
                })
                .catch((error) => {
                    console.error('Error refrescando datos de inventario:', error);
                    buildSummary([{ nombre: 'Sin datos', ventas_30dias: 0, entradas_30dias: 0, stock: 0 }]);
                });
        };

        const DASHBOARD_REFRESH_KEY = 'refreshDashboard';
        const DASHBOARD_FORCE_REFRESH_EVENT = 'refreshDashboard';

        function dispararRefreshDashboard() {
            try {
                localStorage.setItem(DASHBOARD_REFRESH_KEY, String(Date.now()));
            } catch (e) {
                console.warn('No se pudo guardar refresh del dashboard:', e);
            }
            try {
                window.dispatchEvent(new Event(DASHBOARD_FORCE_REFRESH_EVENT));
            } catch (e) {
                console.warn('No se pudo disparar evento local de dashboard:', e);
            }
        }

        window.addEventListener('storage', (event) => {
            if (event.key === DASHBOARD_REFRESH_KEY || event.key === 'refreshInventario') {
                try {
                    inicializarDashboardMesFiltro().finally(() => refrescarDashboard());
                } catch (e) {
                    console.warn('No se pudo refrescar dashboard desde storage:', e);
                }
            }
        });

        window.addEventListener('message', (event) => {
            if (!event || !event.data) return;
            if (event.data.action === 'printHtml' && event.data.payload) {
                const apiElectronImpresion = window.electronAPI?.printHtml;
                if (typeof apiElectronImpresion === 'function') {
                    apiElectronImpresion(event.data.payload);
                }
                return;
            }
            if (event.data.action === 'refreshDashboard' || event.data.action === 'refreshInventario') {
                try {
                    inicializarDashboardMesFiltro().finally(() => refrescarDashboard());
                } catch (e) {
                    console.warn('No se pudo refrescar dashboard desde postMessage:', e);
                }
            }
        });

        window.addEventListener(DASHBOARD_FORCE_REFRESH_EVENT, () => {
            try {
                inicializarDashboardMesFiltro().finally(() => refrescarDashboard());
            } catch (e) {
                console.warn('No se pudo refrescar dashboard al recibir evento:', e);
            }
        });

        document.addEventListener('DOMContentLoaded', async function() {
            const ventasCanvas = document.getElementById('ventasChart');
            if (!ventasCanvas) {
                return;
            }
            await inicializarDashboardMesFiltro();
            // Llamar a la función de refresco una vez inicializado el selector
            refrescarDashboard();
        });

        async function inicializarDashboardMesFiltro() {
            const selector = document.getElementById('dashboardMesFiltro');
            if (!selector) return;

            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            function obtenerFechaBogota() {
                const formatter = new Intl.DateTimeFormat('en-CA', {
                    timeZone: 'America/Bogota',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
                const parts = formatter.formatToParts(new Date());
                const map = {};
                parts.forEach((part) => {
                    if (part.type !== 'literal') {
                        map[part.type] = part.value;
                    }
                });
                return new Date(`${map.year}-${map.month}-${map.day}T${map.hour}:${map.minute}:${map.second}`);
            }
            const currentDate = obtenerFechaBogota();
            const currentMonthValue = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`;
            const currentMonthLabel = `${meses[currentDate.getMonth()]} ${currentDate.getFullYear()}`;

            selector.innerHTML = '';
            try {
                const response = await fetch('<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8'); ?>/Controllers/InventarioController.php?action=obtenerMesesInventario');
                if (!response.ok) {
                    throw new Error('Error al cargar meses de inventario');
                }
                const data = await response.json();
                        if (data.success && Array.isArray(data.data)) {
                        const mesesExistentes = new Set(data.data);
                        const labelMap = {};
                        data.data
                            .filter((mes) => String(mes) <= currentMonthValue)
                            .forEach((mes) => {
                            const [anio, mesNumero] = String(mes).split('-');
                            const index = Number(mesNumero) - 1;
                            const nombreMes = meses[index] || mes;
                            const option = document.createElement('option');
                            option.value = mes;
                            option.textContent = `${nombreMes} ${anio}`;
                            selector.appendChild(option);
                            labelMap[mes] = option.textContent;
                        });

                        if (!selector.value && selector.options.length > 0) {
                            const hasCurrent = Array.from(selector.options).some(o => o.value === currentMonthValue);
                            if (hasCurrent) {
                                selector.value = currentMonthValue;
                            } else {
                                selector.value = selector.options[0].value;
                            }
                        }

                        // Log en cambio de selección y reset de selección global cuando se cambia mes
                        selector.addEventListener('change', function() {
                            const val2 = selector.value || '';
                            console.log('Dashboard: selector changed ->', val2, labelMap[val2] || '');
                            try {
                                closeChartListPanels();
                            } catch (e) {
                                console.warn('Error cerrando paneles al cambiar mes', e);
                            }
                            // refrescar datos para el nuevo mes
                            try { refrescarDashboard(); } catch (e) { console.warn(e); }
                        });
                    }

                    // Actualizar render de movimientos según filtro sin necesidad de re-fetch
                    const actualizarMovimientosPorFiltro = () => {
                        // Si hay resumen en memoria, re-renderizamos usando el flujo estándar
                        // llamando a refrescarDashboard para evitar problemas de scope.
                        try {
                            refrescarDashboard();
                        } catch (e) {
                            console.warn('No se pudo refrescar al cambiar filtro de movimientos', e);
                        }
                    };

                    // Manejar cambio del selector de tipo de movimientos
                    const movimientosFiltroEl = document.getElementById('movimientosTipoFiltro');
                    if (movimientosFiltroEl) {
                        movimientosFiltroEl.addEventListener('change', function(e) {
                            movimientosTipo = String(e.target.value || 'both');
                            console.log('Dashboard: movimientosTipo cambiado a', movimientosTipo);
                            actualizarMovimientosPorFiltro();
                        });
                    }
            } catch (error) {
                console.warn('No se pudo cargar meses de inventario:', error);
            }
            // Estilo blanco y selección por defecto: sólo seleccionar mes actual si existe
            selector.style.background = '#ffffff';
            selector.style.color = '#0f172a';
            const hasCurrent = Array.from(selector.options).some(o => o.value === currentMonthValue);
            if (hasCurrent) {
                selector.value = currentMonthValue;
            } else {
                const option = document.createElement('option');
                option.value = currentMonthValue;
                option.textContent = currentMonthLabel;
                selector.appendChild(option);
                selector.value = currentMonthValue;
            }
        }

        function clearChartSelection(chartId) {
            delete chartSelectionState[chartId];
            const chart = charts.find((entry) => entry && entry.canvas && entry.canvas.id === chartId);
            if (chart) {
                if (chart.data.datasets?.[0]) {
                    chart.data.datasets[0].backgroundColor = chart.data.labels.map((_, index) => {
                        const baseColor = chart.data.datasets[0].backgroundColor[index] || productColorPalette[index % productColorPalette.length];
                        return typeof baseColor === 'string' ? baseColor : productColorPalette[index % productColorPalette.length];
                    });
                    chart.data.datasets[0].borderWidth = chart.data.labels.map(() => 1);
                }
                const chartOptions = chart.config?.options || chart.options;
                if (chartOptions) {
                    chartOptions.rotation = -0.5 * Math.PI;
                    if (chart.config?.options) {
                        chart.config.options.rotation = -0.5 * Math.PI;
                    }
                    if (chart.options) {
                        chart.options.rotation = -0.5 * Math.PI;
                    }
                }
                if (chart.tooltip && typeof chart.tooltip.setActiveElements === 'function') {
                    chart.tooltip.setActiveElements([], { x: 0, y: 0 });
                }
                chart.update();
            }
            renderChartListPanel(chartId);
            try { updateSelectionBadge(chartId, '', '', 0); } catch (e) {}
            try { hideSelectionTooltip(chartId); } catch (e) {}
        }

        // Función para verificar alertas de inventario
        let ultimaAlertaInventario = '';
        const alertCheckInterval = 60000;
        const alertaStockLoginToken = <?= json_encode($alertaStockLoginToken) ?>;
        const baseAlertaStock = '<?= htmlspecialchars(rtrim((string)base_url(), '/'), ENT_QUOTES, 'UTF-8'); ?>';

        function construirTablaAlertaStock(productos, color, mostrarStock) {
            if (!productos.length) return '';
            const filas = productos.map(producto => {
                const nombreImagen = String(producto.imagen || '').trim();
                const imagen = nombreImagen && nombreImagen !== 'favicon.ico'
                    ? `${baseAlertaStock}/Assets/images/productos/${encodeURI(nombreImagen.replace(/\\/g, '/'))}`
                    : `${baseAlertaStock}/favicon.ico`;
                const stock = `<td style="width:62px;padding:6px 7px;text-align:center;color:${color};font-weight:800;font-size:13px;vertical-align:middle;">${producto.stock}</td>`;
                return `<tr style="border-bottom:1px solid #f0f2f4;text-transform:uppercase;"><td style="width:62px;padding:6px 5px;"><img src="${imagen}" alt="" style="width:46px;height:46px;object-fit:contain;border-radius:7px;border:1px solid #e5e7eb;background:#fff;" onerror="this.src='${baseAlertaStock}/favicon.ico'"></td><td style="width:125px;padding:6px 7px;text-align:left;color:#263238;font-weight:700;font-size:13px;vertical-align:middle;word-break:break-word;">${producto.codigo}</td><td style="padding:6px 7px;text-align:left;color:#53636d;font-size:13px;vertical-align:middle;overflow-wrap:anywhere;">${producto.nombre}</td>${stock}</tr>`;
            }).join('');
            return `<section style="margin:0 0 12px;padding:12px;border:1px solid ${color}55;border-left:5px solid ${color};border-radius:10px;background:#fff;text-transform:uppercase;"><div style="font-size:14px;font-weight:800;color:${color};text-align:left;margin-bottom:7px;">${mostrarStock ? 'URGENTES' : 'CRÍTICOS'}</div><table style="width:100%;table-layout:fixed;border-collapse:collapse;font-size:13px;"><thead><tr style="border-bottom:2px solid #e5e7eb;color:#7a8790;font-size:11px;"><th style="width:62px;padding:5px;text-align:left;position:sticky;top:0;z-index:2;background:#fff;">IMG</th><th style="width:125px;padding:5px 7px;text-align:left;position:sticky;top:0;z-index:2;background:#fff;">CÓDIGO</th><th style="padding:5px 7px;text-align:left;position:sticky;top:0;z-index:2;background:#fff;">NOMBRE</th><th style="width:62px;padding:5px 7px;text-align:center;position:sticky;top:0;z-index:2;background:#fff;">STOCK</th></tr></thead><tbody>${filas}</tbody></table></section>`;
        }

        async function verificarProductosStockCero(motivo = 'horario') {
            const ahora = new Date();
            const horaActual = `${String(ahora.getHours()).padStart(2, '0')}:${String(ahora.getMinutes()).padStart(2, '0')}`;
            const claveHorario = `${ahora.toISOString().slice(0, 10)}-${horaActual}`;
            const esInicioSesion = motivo === 'login';
            const esHorario = horaActual === '11:30' || horaActual === '16:30';
            const claveMostrada = sessionStorage.getItem('alertaStockMostrada') || '';
            if ((!esInicioSesion && !esHorario) || (esInicioSesion && claveMostrada === alertaStockLoginToken) || (!esInicioSesion && claveMostrada === claveHorario)) return;
            if (ultimaAlertaInventario === motivo + claveHorario) return;
            ultimaAlertaInventario = motivo + claveHorario;
            try {
                const response = await fetch(`${baseAlertaStock}/Controllers/InventarioController.php?action=obtenerProductosCriticosYUrgentes`);
                const resultado = await response.json();
                if (!resultado.success || resultado.count_total <= 0) return;
                const contenido = construirTablaAlertaStock(resultado.criticos || [], '#d33', false) + construirTablaAlertaStock(resultado.urgentes || [], '#ef8b00', true);
                sessionStorage.setItem('alertaStockMostrada', esInicioSesion ? alertaStockLoginToken : claveHorario);
                Swal.fire({ title: 'PRODUCTOS CRÍTICOS SIN STOCK', html: `<div style="max-height:380px;overflow-y:auto;padding:2px 4px;">${contenido}</div>`, icon: resultado.count_criticos > 0 ? 'error' : 'warning', showCloseButton: true, confirmButtonText: 'Entendido', confirmButtonColor: resultado.count_criticos > 0 ? '#d33' : '#ef8b00', allowOutsideClick: true, allowEscapeKey: true, width: 680 });
            } catch (error) {
                console.error('Error verificando stock de productos:', error);
            }
        }

        const baseCreditosDashboard = '<?= htmlspecialchars(rtrim((string)base_url(), '/'), ENT_QUOTES, 'UTF-8'); ?>';
        const cajaPresenciaStorageKey = 'autoservicioCajaPresenciaId';
        const cajaPresenciaId = (() => {
            let id = localStorage.getItem(cajaPresenciaStorageKey);
            if (!id) {
                id = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
                localStorage.setItem(cajaPresenciaStorageKey, id);
            }
            return id;
        })();

        async function registrarPresenciaCaja() {
            try {
                await fetch(`${baseCreditosDashboard}/api/v1/index.php?action=presence`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        caja_id: cajaPresenciaId,
                        nombre: window.electronAPI ? 'APP/PORTABLE' : 'NAVEGADOR',
                        modo: <?= json_encode($modoMenuPortable ? 'portable' : 'principal'); ?>,
                        puerto: Number(location.port || 80)
                    })
                });
            } catch (error) {
                console.warn('No se pudo registrar la presencia de la caja:', error);
            }
        }

        async function cargarCajasActivas() {
            const lista = document.getElementById('cajasActivasLista');
            if (!lista) return;
            try {
                const respuesta = await fetch(`${baseCreditosDashboard}/api/v1/index.php?action=presence`, { credentials: 'same-origin', cache: 'no-store' });
                const resultado = await respuesta.json();
                const cajas = Array.isArray(resultado.data) ? resultado.data : [];
                lista.innerHTML = cajas.length
                    ? cajas.map(caja => `<div style="padding:8px 10px;border:1px solid #dbe4ec;border-radius:8px;background:#f8fafc;font-size:12px;"><strong>${window.escapeHtml ? window.escapeHtml(caja.nombre) : caja.nombre}</strong><br><span style="color:#64748b;">${window.escapeHtml ? window.escapeHtml(caja.ip) : caja.ip} · ${caja.modo === 'portable' ? 'PORTABLE' : 'PRINCIPAL'} · ACTIVA</span></div>`).join('')
                    : '<div style="padding:8px 10px;color:#64748b;font-size:12px;">No hay cajas activas.</div>';
            } catch (error) {
                lista.innerHTML = '<div style="padding:8px 10px;color:#b91c1c;font-size:12px;">No se pudo consultar las cajas activas.</div>';
            }
        }

        registrarPresenciaCaja();
        window.setInterval(registrarPresenciaCaja, 15000);
        window.setInterval(cargarCajasActivas, 15000);
        const conexionDashboardStorageKey = 'autoservicioServidorConexiones';
        const conexionDashboardLegacyStorageKey = 'autoservicioServidorIp';

        function normalizarPuertoConexion(valor) {
            const rawPuerto = String(valor ?? '').trim();
            if (rawPuerto === '') return '8000';
            const numero = Number(rawPuerto);
            if (!Number.isInteger(numero) || numero < 1 || numero > 65535) {
                return '8000';
            }
            return String(numero);
        }

        function normalizarEntradaConexion(item) {
            const ip = String(item?.ip || '').trim().replace(/^https?:\/\//i, '').replace(/\/+$/, '').split('/')[0];
            const port = normalizarPuertoConexion(item?.port || '8000');
            if (!ip) return null;
            return {
                ip,
                port,
                fecha: Number(item?.fecha || Date.now())
            };
        }

        function obtenerConexionesGuardadas() {
            try {
                const raw = localStorage.getItem(conexionDashboardStorageKey);
                const conexiones = raw ? JSON.parse(raw) : [];
                const listaNormalizada = Array.isArray(conexiones)
                    ? conexiones
                        .map((item) => normalizarEntradaConexion(item))
                        .filter(Boolean)
                    : [];

                if (listaNormalizada.length > 0) {
                    return listaNormalizada
                        .sort((a, b) => b.fecha - a.fecha)
                        .slice(0, 8);
                }

                const ipLegacy = localStorage.getItem(conexionDashboardLegacyStorageKey);
                if (!ipLegacy) {
                    return [];
                }

                const entradaLegacy = normalizarEntradaConexion({ ip: ipLegacy, port: '8000', fecha: Date.now() });
                return entradaLegacy ? [entradaLegacy] : [];
            } catch (error) {
                console.warn('No se pudieron cargar las conexiones guardadas:', error);
                return [];
            }
        }

        function guardarConexionesGuardadas(conexiones) {
            try {
                localStorage.setItem(conexionDashboardStorageKey, JSON.stringify(conexiones.slice(0, 8)));
            } catch (error) {
                console.warn('No se pudo guardar la lista de conexiones:', error);
            }
        }

        function renderConexionDashboardLista() {
            const lista = document.getElementById('conexionDashboardLista');
            if (!lista) return;

            const conexiones = obtenerConexionesGuardadas();
            if (conexiones.length === 0) {
                lista.innerHTML = '<div style="padding:10px 12px;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;font-size:13px;">No hay puertos guardados todavía.</div>';
                return;
            }

            lista.innerHTML = conexiones.map((conexion, index) => {
                const esUltimo = index === 0;
                return `
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid ${esUltimo ? '#2563eb' : '#dbeafe'};border-radius:8px;background:${esUltimo ? '#eff6ff' : '#f8fafc'};">
                        <div style="min-width:0;">
                            <div style="font-size:12px;color:#64748b;">${esUltimo ? 'ÚLTIMO GUARDADO' : 'PUERTO GUARDADO'}</div>
                            <div style="font-size:14px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${window.escapeHtml ? window.escapeHtml(conexion.ip) : conexion.ip}</div>
                            <div style="font-size:12px;color:#475569;">Puerto: ${window.escapeHtml ? window.escapeHtml(conexion.port) : conexion.port}</div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                            <button type="button" class="btn btn-primary" onclick="conectarServidorGuardado('${window.escapeHtml ? window.escapeHtml(conexion.ip) : conexion.ip}', '${window.escapeHtml ? window.escapeHtml(conexion.port) : conexion.port}')" style="padding:8px 12px;font-size:12px;white-space:nowrap;">Conectar</button>
                            <button type="button" class="btn btn-secondary" onclick="eliminarConexionGuardada('${window.escapeHtml ? window.escapeHtml(conexion.ip) : conexion.ip}', '${window.escapeHtml ? window.escapeHtml(conexion.port) : conexion.port}')" style="padding:8px 12px;font-size:12px;white-space:nowrap;color:#991b1b;">Eliminar</button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function construirUrlConexion(ip, port) {
            const currentUrl = new URL(window.location.href);
            const basePath = Number(port) === 80 || Number(port) === 443
                ? '/nombre_de_empresa'
                : currentUrl.pathname.split('/Views/')[0].replace(/\/$/, '');
            return `${currentUrl.protocol}//${ip}:${port}${basePath}/Views/login.php`;
        }

        function conectarServidorGuardado(ip, port) {
            const entrada = normalizarEntradaConexion({ ip, port, fecha: Date.now() });
            if (!entrada) return;

            const conexiones = obtenerConexionesGuardadas()
                .filter((item) => !(item.ip === entrada.ip && item.port === entrada.port));

            const actualizadas = [entrada, ...conexiones].slice(0, 8);
            guardarConexionesGuardadas(actualizadas);

            const ipInput = document.getElementById('conexionDashboardIpInput');
            const portInput = document.getElementById('conexionDashboardPortInput');
            if (ipInput) ipInput.value = entrada.ip;
            if (portInput) portInput.value = entrada.port;

            cerrarConexionDashboard();
            window.location.href = construirUrlConexion(entrada.ip, entrada.port);
        }

        function eliminarConexionGuardada(ip, port) {
            const conexiones = obtenerConexionesGuardadas()
                .filter((item) => !(item.ip === ip && item.port === port));
            guardarConexionesGuardadas(conexiones);
            if (localStorage.getItem(conexionDashboardLegacyStorageKey) === ip) {
                localStorage.removeItem(conexionDashboardLegacyStorageKey);
            }
            renderConexionDashboardLista();
        }

        function abrirConexionDashboard() {
            const modal = document.getElementById('conexionDashboardModal');
            const ipInput = document.getElementById('conexionDashboardIpInput');
            const portInput = document.getElementById('conexionDashboardPortInput');
            if (!modal || !ipInput || !portInput) return;

            const conexiones = obtenerConexionesGuardadas();
            const ultimaConexion = conexiones[0] || null;
            ipInput.value = ultimaConexion ? ultimaConexion.ip : (localStorage.getItem(conexionDashboardLegacyStorageKey) || '');
            portInput.value = ultimaConexion ? ultimaConexion.port : '8000';
            renderConexionDashboardLista();
            cargarCajasActivas();
            modal.style.display = 'block';
        }

        function cerrarConexionDashboard() {
            const modal = document.getElementById('conexionDashboardModal');
            if (modal) modal.style.display = 'none';
        }

        function guardarConexionDashboard() {
            const ipInput = document.getElementById('conexionDashboardIpInput');
            const portInput = document.getElementById('conexionDashboardPortInput');
            if (!ipInput || !portInput) return;

            const ip = String(ipInput.value || '').trim().replace(/^https?:\/\//i, '').replace(/\/+$/, '').split('/')[0];
            const port = normalizarPuertoConexion(portInput.value);

            if (!ip) {
                ipInput.focus();
                return;
            }

            const entrada = normalizarEntradaConexion({ ip, port, fecha: Date.now() });
            if (!entrada) {
                ipInput.focus();
                return;
            }

            const conexiones = obtenerConexionesGuardadas()
                .filter((item) => !(item.ip === entrada.ip && item.port === entrada.port));

            const actualizadas = [entrada, ...conexiones].slice(0, 8);
            guardarConexionesGuardadas(actualizadas);

            try {
                localStorage.setItem(conexionDashboardLegacyStorageKey, entrada.ip);
            } catch (error) {
                console.warn('No se pudo guardar la IP principal:', error);
            }

            cerrarConexionDashboard();
            window.location.href = construirUrlConexion(entrada.ip, entrada.port);
        }

        function escaparHtmlCredito(valor) {
            return String(valor ?? '').replace(/[&<>'"]/g, caracter => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[caracter]));
        }

        function formatoMonedaCredito(valor) {
            return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor || 0));
        }

        async function mostrarDetalleCreditoDashboard(id) {
            const response = await fetch(`${baseCreditosDashboard}/Controllers/CreditosController.php?action=detalle&id=${encodeURIComponent(id)}`);
            const resultado = await response.json();
            if (!resultado.success) throw new Error(resultado.message || 'No se pudo cargar el crédito');
            const credito = resultado.data;
            const detalles = (credito.detalles || []).map(item => `<tr><td>${escaparHtmlCredito(item.producto_nombre || item.producto_codigo || 'Producto')}</td><td>${item.cantidad}</td><td>${formatoMonedaCredito(item.total)}</td></tr>`).join('');
            const abonos = (credito.abonos || []).map(item => `<li>${escaparHtmlCredito(item.fecha_abono)}: ${formatoMonedaCredito(item.monto)} (${escaparHtmlCredito(item.metodo_pago)})</li>`).join('') || '<li>Sin abonos registrados</li>';
            Swal.fire({
                title: `CRÉDITO ${escaparHtmlCredito(credito.referencia)}`,
                html: `<div style="text-align:left;font-size:13px;"><p><strong>CLIENTE:</strong> ${escaparHtmlCredito(`${credito.nombre || ''} ${credito.apellidos || ''}`.trim())}</p><p><strong>DOCUMENTO:</strong> ${escaparHtmlCredito(credito.documento || 'N/D')} &nbsp; <strong>CÓDIGO:</strong> ${escaparHtmlCredito(credito.codigo || 'N/D')}</p><p><strong>TOTAL:</strong> ${formatoMonedaCredito(credito.total)} &nbsp; <strong>SALDO:</strong> ${formatoMonedaCredito(credito.saldo)}</p><h4>PRODUCTOS</h4><table style="width:100%;border-collapse:collapse;"><thead><tr><th style="text-align:left;">Producto</th><th>Cant.</th><th>Total</th></tr></thead><tbody>${detalles}</tbody></table><h4>ABONOS</h4><ul>${abonos}</ul></div>`,
                confirmButtonText: 'CERRAR',
                width: 680
            });
        }

        async function cargarCreditosDashboard() {
            const resumen = document.getElementById('creditosDashboardResumen');
            try {
                const response = await fetch(`${baseCreditosDashboard}/Controllers/CreditosController.php?action=listar`);
                const resultado = await response.json();
                if (!resultado.success) throw new Error(resultado.message || 'No se pudieron cargar los créditos');
                const creditos = resultado.data || [];
                const saldoTotal = creditos.reduce((total, credito) => total + Number(credito.saldo || 0), 0);
                const clientes = new Set(creditos.map(credito => String(credito.cliente_id || '')));
                document.getElementById('creditosClientesTotal').textContent = clientes.size;
                document.getElementById('creditosSaldoTotal').textContent = formatoMonedaCredito(saldoTotal);
                resumen.innerHTML = creditos.length
                    ? creditos.slice(0, 3).map(credito => `<button type="button" onclick="event.stopPropagation();mostrarDetalleCreditoDashboard(${Number(credito.id)})" style="display:block;width:100%;margin:4px 0;padding:5px 0;border:0;background:transparent;text-align:left;color:#334155;cursor:pointer;">${escaparHtmlCredito(`${credito.nombre || ''} ${credito.apellidos || ''}`.trim())} - ${formatoMonedaCredito(credito.saldo)}</button>`).join('')
                    : 'No hay créditos registrados.';
            } catch (error) {
                if (resumen) resumen.textContent = 'No se pudieron cargar los créditos.';
                console.error(error);
            }
        }

        function cerrarCreditosDashboard() {
            const modal = document.getElementById('creditosDashboardModal');
            if (modal) modal.style.display = 'none';
        }

        let creditosDashboardDetalles = [];
        let creditosDashboardPagina = 1;
        const creditosDashboardPorPagina = 5;

        function renderizarPaginaCreditosDashboard() {
            const lista = document.getElementById('creditosDashboardLista');
            if (!lista) return;
            const totalPaginas = Math.max(1, Math.ceil(creditosDashboardDetalles.length / creditosDashboardPorPagina));
            creditosDashboardPagina = Math.min(Math.max(1, creditosDashboardPagina), totalPaginas);
            const inicio = (creditosDashboardPagina - 1) * creditosDashboardPorPagina;
            const pagina = creditosDashboardDetalles.slice(inicio, inicio + creditosDashboardPorPagina);
            const fichas = pagina.map(credito => {
                const nombreCliente = `${credito.nombre || ''} ${credito.apellidos || ''}`.trim() || 'CLIENTE SIN NOMBRE';
                const productos = (credito.detalles || []).map(item => `<tr><td style="padding:8px;border-bottom:1px solid #e2e8f0;">${escaparHtmlCredito(item.producto_nombre || 'Producto')}<br><small>CÓD: ${escaparHtmlCredito(item.producto_codigo || 'N/D')}</small></td><td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:center;">${item.cantidad}</td><td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;">${formatoMonedaCredito(item.precio_unitario)}</td><td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;">${formatoMonedaCredito(item.total)}</td></tr>`).join('');
                return `<article style="margin-bottom:16px;padding:16px;border:1px solid #dbe4ec;border-radius:10px;background:#fff;text-align:left;"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;"><div><strong style="font-size:16px;color:#263238;">${escaparHtmlCredito(nombreCliente)}</strong><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:7px;"><span class="chip">DOC: ${escaparHtmlCredito(credito.documento || 'N/D')}</span><span class="chip">CÓD: ${escaparHtmlCredito(credito.codigo || 'N/D')}</span><span class="chip">REF: ${escaparHtmlCredito(credito.referencia || 'N/D')}</span></div></div><div style="text-align:right;"><strong style="display:block;color:#b45309;">SALDO: ${formatoMonedaCredito(credito.saldo)}</strong><small>TOTAL: ${formatoMonedaCredito(credito.total)} | ${escaparHtmlCredito(credito.estado || 'pendiente')}</small></div></div><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="color:#64748b;"><th style="padding:7px;text-align:left;">PRODUCTO</th><th style="padding:7px;">CANT.</th><th style="padding:7px;text-align:right;">PRECIO</th><th style="padding:7px;text-align:right;">TOTAL</th></tr></thead><tbody>${productos || '<tr><td colspan="4" style="padding:12px;text-align:center;">Sin productos</td></tr>'}</tbody></table></article>`;
            }).join('');
            lista.innerHTML = fichas || '<p style="padding:20px;text-align:center;color:#64748b;">No hay créditos registrados.</p>';
            if (creditosDashboardDetalles.length > creditosDashboardPorPagina) {
                lista.innerHTML += `<div style="display:flex;align-items:center;justify-content:center;gap:12px;padding:12px 0;"><button type="button" class="chart-list-btn" onclick="cambiarPaginaCreditosDashboard(-1)" ${creditosDashboardPagina === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button><strong>PÁGINA ${creditosDashboardPagina} DE ${totalPaginas}</strong><button type="button" class="chart-list-btn" onclick="cambiarPaginaCreditosDashboard(1)" ${creditosDashboardPagina === totalPaginas ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button></div>`;
            }
        }

        function cambiarPaginaCreditosDashboard(direccion) {
            creditosDashboardPagina += direccion;
            renderizarPaginaCreditosDashboard();
        }

        async function abrirCreditosDashboard() {
            const modal = document.getElementById('creditosDashboardModal');
            const lista = document.getElementById('creditosDashboardLista');
            if (!modal || !lista) return;
            modal.style.display = 'block';
            lista.innerHTML = 'Cargando créditos...';

            try {
                const response = await fetch(`${baseCreditosDashboard}/Controllers/CreditosController.php?action=listar`);
                const resultado = await response.json();
                if (!resultado.success) throw new Error(resultado.message || 'No se pudieron cargar los créditos');
                const detalles = await Promise.all((resultado.data || []).map(async credito => {
                    const detalleResponse = await fetch(`${baseCreditosDashboard}/Controllers/CreditosController.php?action=detalle&id=${encodeURIComponent(credito.id)}`);
                    const detalleResultado = await detalleResponse.json();
                    return detalleResultado.success ? detalleResultado.data : { ...credito, detalles: [], abonos: [] };
                }));

                creditosDashboardDetalles = detalles;
                creditosDashboardPagina = 1;
                renderizarPaginaCreditosDashboard();
            } catch (error) {
                lista.innerHTML = `<p style="padding:20px;text-align:center;color:#b91c1c;">${escaparHtmlCredito(error.message || 'No se pudieron cargar los créditos')}</p>`;
            }
        }

        setInterval(() => verificarProductosStockCero('horario'), alertCheckInterval);
        verificarProductosStockCero('login');
        <?php if ($mostrarCreditos): ?>
        cargarCreditosDashboard();
        <?php endif; ?>
    </script>

</body>

</html>




