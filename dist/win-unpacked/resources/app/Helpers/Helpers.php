<?php
    // Constantes de configuración de moneda para Colombia
    define('SMONEY', 'COP ');       // Símbolo de moneda COP
    define('CURRENCY', 'COP');       // Código ISO de moneda
    define('SPD', ',');              // Separador de decimales (coma)
    define('SPM', '.');              // Separador de miles (punto)
<<<<<<< Updated upstream
    define('NOMBRE_EMPRESA', 'AUTOSERVICIO LA ESTRELLA');
=======
    define('NOMBRE_EMPRESA', 'AUTOSERVICIO MI ESTRELLA');
>>>>>>> Stashed changes
    if(!defined('COSTOENVIO')){
        define('COSTOENVIO', 0);
    }
    if(!defined('METHODENCRIPT')){
        define('METHODENCRIPT', 'AES-128-CTR');
    }
    if(!defined('KEY')){
        define('KEY', 'partner_innovacion');
    }
    if(!defined('WEB_EMPRESA')){
        define('WEB_EMPRESA', 'nombreempresa.ct.ws');
    }
    define('NOMBRE_REMITENTE', 'AUTOSERVICIO');
    define('EMAIL_REMITENTE', 'contacto@empresa.com');
    if(!defined('DESCRIPCION')){
        define('DESCRIPCION', 'Somos una tienda enfocada en ofrecer productos de tecnología con calidad, respaldo y atención personalizada.');
    }
    if(!defined('DIRECCION')){
        define('DIRECCION', 'Colombia');
    }
    if(!defined('TELEMPRESA')){
        define('TELEMPRESA', '+57 300 000 0000');
    }
    if(!defined('EMAIL_EMPRESA')){
        define('EMAIL_EMPRESA', 'mowecompany05@gmail.com');
    }
    if(!defined('FACEBOOK')){
        define('FACEBOOK', '#');
    }
    if(!defined('INSTAGRAM')){
        define('INSTAGRAM', '#');
    }
    
    // Definir ruta raíz absoluta del proyecto
    if (!defined('ROOT_PATH')) {
        define('ROOT_PATH', dirname(__DIR__));
    }
    
    // Definir URL base flexible
    $appBaseUrl = getenv('APP_BASE_URL');
    if ($appBaseUrl === false || trim($appBaseUrl) === '') {
        $httpHost = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
        $basePath = '';

        $documentRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $rootPathNormalized = str_replace('\\', '/', rtrim((string)ROOT_PATH, '/'));
        $documentRootNormalized = str_replace('\\', '/', rtrim($documentRoot, '/'));

        if ($documentRootNormalized !== '' && $rootPathNormalized !== '' && str_starts_with($rootPathNormalized, $documentRootNormalized . '/')) {
            $basePath = '/' . trim(substr($rootPathNormalized, strlen($documentRootNormalized)), '/');
        } elseif ($documentRootNormalized !== '' && $rootPathNormalized !== '' && $rootPathNormalized === $documentRootNormalized) {
            $basePath = '';
        }

        if ($basePath === '' && !empty($_SERVER['SCRIPT_NAME'])) {
            $scriptName = str_replace('\\', '/', trim((string)$_SERVER['SCRIPT_NAME']));
            if ($scriptName !== '') {
                $scriptDir = rtrim(dirname($scriptName), '/');
                if ($scriptDir !== '.' && $scriptDir !== '/') {
                    $basePath = $scriptDir;
                    if (str_ends_with($basePath, '/Views') || str_ends_with($basePath, '/Controllers')) {
                        $basePath = substr($basePath, 0, strrpos($basePath, '/'));
                    }
                }
            }
        }

        if ($httpHost === 'nombreempresa.ct.ws' || $httpHost === 'www.nombreempresa.ct.ws') {
            $appBaseUrl = 'https://nombreempresa.ct.ws' . $basePath;
        } elseif ($httpHost !== '') {
            $scheme = 'http';
            if ((!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443') {
                $scheme = 'https';
            }
            $appBaseUrl = $scheme . '://' . $httpHost . $basePath;
        } else {
            $appBaseUrl = 'http://localhost' . ($basePath !== '' ? $basePath : '/nombre_de_empresa');
        }
    }
    define('BASE_URL', rtrim($appBaseUrl, '/'));

    //Comentamos temporalmente PHPMailer hasta tenerlo instalado
    /*use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    require 'Libraries/phpmailer/Exception.php';
    require 'Libraries/phpmailer/PHPMailer.php';
    require 'Libraries/phpmailer/SMTP.php';*/

    //Retorna la url del proyecto
    function base_url()
    {
        return BASE_URL;
    }

    //Retorna la url base de los assets (carpeta Assets)
    //Se espera que muchos enlaces javascript, CSS e imagenes usen media() y
    //concatenen rutas relativas como "/css/..." o "/images/...".
    //Anteriormente devolvía solo la BASE_URL y la carpeta "Assets" se omitía,
    //lo que generaba URLs como `/nombre_de_empresa/css/main.css` y provocaba
    //404 cuando el directorio real es `/nombre_de_empresa/Assets/css`.
    //Al incluir "/Assets" aquí cualquier llamada existente se arregla de forma
    //centralizada sin necesidad de modificar decenas de vistas.
    function media()
    {
        return BASE_URL . '/Assets';
    }

    //Limpia cadenas de texto
    function strClean($strCadena){
        $string = preg_replace(['/\s+/','/^\s|\s$/'],[' ',''], $strCadena);
        $string = trim($string);
        $string = stripslashes($string);
        $string = str_replace("<script>","",$string);
        $string = str_replace("</script>","",$string);
        $string = str_replace("<script src","",$string);
        $string = str_replace("<script type=","",$string);
        $string = str_replace("SELECT * FROM","",$string);
        $string = str_replace("DELETE FROM","",$string);
        $string = str_replace("INSERT INTO","",$string);
        $string = str_replace("SELECT COUNT(*) FROM","",$string);
        $string = str_replace("DROP TABLE","",$string);
        $string = str_replace("OR '1'='1","",$string);
        $string = str_replace('OR "1"="1"',"",$string);
        $string = str_replace('OR \'1\'=\'1\'',"",$string);
        $string = str_replace("is NULL; --","",$string);
        $string = str_replace("is NULL; --","",$string);
        $string = str_replace("LIKE '","",$string);
        $string = str_replace('LIKE "',"",$string);
        $string = str_replace("LIKE `","",$string);
        $string = str_replace("LIKE ´","",$string);
        $string = str_replace("OR 'a'='a","",$string);
        $string = str_replace('OR "a"="a',"",$string);
        $string = str_replace("OR ´a´=´a","",$string);
        $string = str_replace("OR `a`=`a","",$string);
        $string = str_replace("--","",$string);
        $string = str_replace("^","",$string);
        $string = str_replace("[","",$string);
        $string = str_replace("]","",$string);
        $string = str_replace("==","",$string);
        return $string;
    }

    function normalizarNombreRol($rol): string
    {
        $texto = trim(mb_strtolower((string)$rol, 'UTF-8'));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
        ]);
        $texto = preg_replace('/[^a-z0-9]+/u', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        $texto = trim($texto);
        $texto = str_replace('super administrador', 'superadministrador', $texto);
        $texto = str_replace('administrador', 'administrador', $texto);
        return $texto;
    }

    function slugifyText(string $texto): string
    {
        $texto = trim(mb_strtolower($texto, 'UTF-8'));
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($slug === false) {
            $slug = $texto;
        }
        $slug = preg_replace('/[^a-z0-9\s-]/', '', (string)$slug);
        $slug = preg_replace('/[\s-]+/', '-', (string)$slug);
        return trim((string)$slug, '-');
    }

    function inicializarUtf8(): void
    {
        ini_set('default_charset', 'UTF-8');

        if (function_exists('mb_internal_encoding')) {
            ini_set('mbstring.internal_encoding', 'UTF-8');
            ini_set('mbstring.http_output', 'UTF-8');
            mb_internal_encoding('UTF-8');
            mb_http_output('UTF-8');
            mb_regex_encoding('UTF-8');
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
    }

    function convertirATextoUtf8($texto)
    {
        if (!is_string($texto)) {
            return $texto;
        }

        if ($texto === '') {
            return '';
        }

        if (mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }

        $detected = mb_detect_encoding($texto, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($detected !== false && strtoupper($detected) !== 'UTF-8') {
            $texto = mb_convert_encoding($texto, 'UTF-8', $detected);
        } elseif ($detected === false) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
        }

        return $texto;
    }

    function limpiarTexto($texto)
    {
        if (!is_string($texto)) {
            return $texto;
        }

        $texto = trim((string)$texto);
        $texto = convertirATextoUtf8($texto);
        $texto = corregirMojibake($texto);
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        return $texto;
    }

    function limpiarTextoRecursivo($input)
    {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = limpiarTextoRecursivo($value);
            }
            return $input;
        }

        if (is_object($input)) {
            foreach ($input as $key => $value) {
                $input->{$key} = limpiarTextoRecursivo($value);
            }
            return $input;
        }

        return limpiarTexto($input);
    }

    function jsonResponse($data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }

    function encryptTenantSecret(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }

        $method = defined('METHODENCRIPT') ? (string)METHODENCRIPT : 'AES-128-CTR';
        $ivLength = openssl_cipher_iv_length($method);
        if (!is_int($ivLength) || $ivLength <= 0) {
            return $valor;
        }

        $iv = random_bytes($ivLength);
        $keyMaterial = hash('sha256', (string)(defined('KEY') ? KEY : 'partner_innovacion'), true);
        $ciphertext = openssl_encrypt($valor, $method, $keyMaterial, OPENSSL_RAW_DATA, $iv);
        if (!is_string($ciphertext) || $ciphertext === '') {
            return $valor;
        }

        return 'enc:' . base64_encode($iv . $ciphertext);
    }

    function decryptTenantSecret(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }

        if (stripos($valor, 'enc:') !== 0) {
            return $valor;
        }

        $payload = base64_decode(substr($valor, 4), true);
        if (!is_string($payload) || $payload === '') {
            return '';
        }

        $method = defined('METHODENCRIPT') ? (string)METHODENCRIPT : 'AES-128-CTR';
        $ivLength = openssl_cipher_iv_length($method);
        if (!is_int($ivLength) || $ivLength <= 0 || strlen($payload) <= $ivLength) {
            return '';
        }

        $iv = substr($payload, 0, $ivLength);
        $ciphertext = substr($payload, $ivLength);
        $keyMaterial = hash('sha256', (string)(defined('KEY') ? KEY : 'partner_innovacion'), true);
        $plain = openssl_decrypt($ciphertext, $method, $keyMaterial, OPENSSL_RAW_DATA, $iv);

        return is_string($plain) ? trim($plain) : '';
    }

    function wompiCalcularMontoEnCentavos($monto): int
    {
        if (is_int($monto)) {
            return max(0, $monto * 100);
        }

        if (is_string($monto)) {
            $monto = trim($monto);
            if ($monto === '') {
                return 0;
            }
            $monto = str_replace(['$', ' '], '', $monto);
            $monto = str_replace(',', '.', $monto);
        }

        if (!is_numeric($monto)) {
            return 0;
        }

        return max(0, (int) round(((float) $monto) * 100));
    }

    /**
     * Corrige caracteres rotos (mojibake) causados por codificación incorrecta UTF-8/Latin1
     */
    function corregirMojibake($texto) {
        if (!is_string($texto)) {
            return $texto;
        }
        
        // Mapa de caracteres mojibake comunes a sus equivalentes correctos
        $mapa = [
            'Ã¡' => 'á', 'Ã©' => 'é', 'Ã­' => 'í', 'Ã³' => 'ó', 'Ãº' => 'ú', 'Ã±' => 'ñ', 'Ã¼' => 'ü',
            'Ã' => 'Á', 'Ã‰' => 'É', 'Ã' => 'Í', 'Ã“' => 'Ó', 'Ãš' => 'Ú', 'Ã‘' => 'Ñ', 'Ãœ' => 'Ü',
            'Â¡' => '¡', 'Â¿' => '¿', 'â€”' => '—', 'â€œ' => '"', 'â€' => '"', 'â€˜' => "'", 'â€™' => "'",
            'â€¦' => '…', 'â€¢' => '•', 'âˆ’' => '−', 'Ã§' => 'ç', 'Ã¸' => 'ø', 'Ã¨' => 'è', 'Ã¢' => 'â',
            'Ãª' => 'ê', 'Ã´' => 'ô', 'Ã»' => 'û', 'Ãš' => 'Ú', 'Ã¼' => 'ü',
            'categorÃ­a' => 'categoría', 'CategorÃ­a' => 'Categoría', 'categorÃ­as' => 'categorías',
            'GestiÃ³n' => 'Gestión', 'sesiÃ³n' => 'sesión', 'Ã“rdenes' => 'Órdenes', 'Ã³rdenes' => 'órdenes',
            'descripciÃ³n' => 'descripción', 'DescripciÃ³n' => 'Descripción', 'creaciÃ³n' => 'creación',
            'CreaciÃ³n' => 'Creación', 'actualizaciÃ³n' => 'actualización', 'ActualizaciÃ³n' => 'Actualización',
            'eliminarÃ¡' => 'eliminará', 'eliminarÃ¡' => 'eliminará', 'automÃ¡ticamente' => 'automáticamente',
            'dinÃ¡mico' => 'dinámico', 'cargarÃ¡n' => 'cargarÃ¡n', 'categorÃ­as' => 'categorías',
            'CategorÃ­as' => 'Categorías', 'CÃ³digo' => 'Código', 'cÃ³digo' => 'código',
            'CÃ“DIGO' => 'CÓDIGO', 'cÃ³digo' => 'código', 'CÃ“D. BARRAS' => 'CÓD. BARRAS',
            'DESCRIPCIÃ“N' => 'DESCRIPCIÓN', 'CATEGORÃA' => 'CATEGORÍA', 'FECHA CREACIÃ“N' => 'FECHA CREACIÓN',
            'FECHA ACTUALIZACIÃ“N' => 'FECHA ACTUALIZACIÓN', 'ACCIONES' => 'ACCIONES',
            'NUEVA CATEGORÃA' => 'NUEVA CATEGORÍA', 'GUARDAR CATEGORÃA' => 'GUARDAR CATEGORÍA',
            'ACTUALIZAR CATEGORÃA' => 'ACTUALIZAR CATEGORÍA', 'DETALLES DE LA CATEGORÃA' => 'DETALLES DE LA CATEGORÍA',
            'Ã“RDENES DE TALLER' => 'ÓRDENES DE TALLER', 'Ã“rdenes de Taller' => 'Órdenes de Taller',
            'âœ“ Con Ã³rdenes' => '✓ Con órdenes', 'âˆ’' => '-', 'â€¢' => '•'
        ];
        
        foreach ($mapa as $roto => $correcto) {
            $texto = str_replace($roto, $correcto, $texto);
        }
        
        return $texto;
    }

    function wompiGenerarFirma(string $reference, int $amountInCents, string $currency, string $integrityKey): string
    {
        $reference = trim($reference);
        $currency = trim($currency);
        $integrityKey = trim($integrityKey);

        if ($reference === '' || $amountInCents <= 0 || $currency === '' || $integrityKey === '') {
            return '';
        }

        return hash('sha256', $reference . $amountInCents . $currency . $integrityKey);
    }

    function buildStoreParam(string $texto, $id): string
    {
        $id = (int)$id;
        $slug = slugifyText($texto);
        return $slug !== '' ? $slug . '-' . $id : (string)$id;
    }

    class ActualizacionesHelper
    {
        /**
         * Registra cambios en entidades para auditoría.
         *
         * Implementación mínima que evita errores si el módulo de auditoría no está disponible.
         */
        public static function registrarCambio(string $entidad, string $accion, $antes = null, $despues = null, array $meta = []): void
        {
            // No-op: mantener compatibilidad con los controladores que llaman a esta clase.
        }
    }

    function extractIdFromParam($param): int
    {
        $raw = trim((string)$param);
        if ($raw === '') {
            return 0;
        }

        if (preg_match('/(\d+)$/', $raw, $coincidencia)) {
            return (int)$coincidencia[1];
        }

        return (int)strClean($raw);
    }

    function dbEsSqlite(PDO $db): bool
    {
        try {
            return strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
        } catch (Throwable $e) {
            return false;
        }
    }

    function &dbSchemaCache(PDO $db): array
    {
        static $cache = [];
        $id = spl_object_id($db);
        if (!isset($cache[$id])) {
            $cache[$id] = [];
        }
        return $cache[$id];
    }

    function dbTableExists(PDO $db, string $tabla): bool
    {
        try {
            $cache = &dbSchemaCache($db);
            $cacheKey = 'table:' . strtolower($tabla);
            if (array_key_exists($cacheKey, $cache) && isset($cache[$cacheKey]['exists'])) {
                return (bool)$cache[$cacheKey]['exists'];
            }

            if (dbEsSqlite($db)) {
                $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
                $stmt = $db->prepare('SELECT COUNT(*) FROM sqlite_master WHERE type = "table" AND LOWER(name) = LOWER(:tabla)');
                $stmt->bindValue(':tabla', $tablaSegura, PDO::PARAM_STR);
                $stmt->execute();
                $exists = ((int)$stmt->fetchColumn()) > 0;
                $cache[$cacheKey] = ['exists' => $exists];
                return $exists;
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla");
            $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
            $stmt->execute();
            $exists = ((int)$stmt->fetchColumn()) > 0;
            $cache[$cacheKey] = ['exists' => $exists];
            return $exists;
        } catch (Throwable $e) {
            return false;
        }
    }

    function dbColumnExists(PDO $db, string $tabla, string $columna): bool
    {
        try {
            $info = dbColumnInfo($db, $tabla);
            $key = strtolower(trim($columna));
            return isset($info[$key]);
        } catch (Throwable $e) {
            return false;
        }
    }

    function dbColumnInfo(PDO $db, string $tabla): array
    {
        try {
            $cache = &dbSchemaCache($db);
            $cacheKey = 'columns:' . strtolower($tabla);
            if (array_key_exists($cacheKey, $cache)) {
                return is_array($cache[$cacheKey]) ? $cache[$cacheKey] : [];
            }

            if (dbEsSqlite($db)) {
                $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
                $stmt = $db->prepare("PRAGMA table_info(\"{$tablaSegura}\")");
                $stmt->execute();
                $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($columnas as $col) {
                    $name = strtolower(trim((string)($col['name'] ?? '')));
                    if ($name !== '') {
                        $result[$name] = $col;
                    }
                }
                $cache[$cacheKey] = $result;
                return $result;
            }

            $stmt = $db->prepare("SELECT COLUMN_NAME, IS_NULLABLE, EXTRA, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla");
            $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
            $stmt->execute();
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($columnas as $col) {
                $name = strtolower(trim((string)($col['COLUMN_NAME'] ?? '')));
                if ($name !== '') {
                    $result[$name] = $col;
                }
            }
            $cache[$cacheKey] = $result;
            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    function dbColumnAllowsNull(PDO $db, string $tabla, string $columna): bool
    {
        if (!dbColumnExists($db, $tabla, $columna)) {
            return false;
        }

        $info = dbColumnInfo($db, $tabla);
        $key = strtolower(trim($columna));
        if (!isset($info[$key])) {
            return false;
        }

        if (dbEsSqlite($db)) {
            return !((int)($info[$key]['notnull'] ?? 0) === 1);
        }

        return strtoupper((string)($info[$key]['IS_NULLABLE'] ?? 'NO')) === 'YES';
    }

    function dbColumnIsAutoIncrement(PDO $db, string $tabla, string $columna): bool
    {
        if (!dbColumnExists($db, $tabla, $columna)) {
            return false;
        }

        $info = dbColumnInfo($db, $tabla);
        $key = strtolower(trim($columna));
        if (!isset($info[$key])) {
            return false;
        }

        if (dbEsSqlite($db)) {
            $row = $info[$key];
            $pk = (int)($row['pk'] ?? 0);
            $type = strtoupper((string)($row['type'] ?? ''));
            return $pk === 1 && preg_match('/INT/', $type) === 1;
        }

        return stripos((string)($info[$key]['EXTRA'] ?? ''), 'auto_increment') !== false;
    }

    function getCurrentStoreSlug(): string
    {
        $slug = trim((string)($_SESSION['store_public_slug'] ?? ($_SESSION['empresa_store_slug'] ?? '')));
        return $slug !== '' ? slugifyText($slug) : '';
    }

    function clearPublicStoreContext(): void
    {
        unset($_SESSION['store_public_slug'], $_SESSION['store_public_empresa_id'], $_SESSION['store_public_nombre']);
    }

    function bindPublicStoreContextBySlug(string $slug): bool
    {
        $slug = slugifyText($slug);
        if ($slug === '') {
            clearPublicStoreContext();
            return false;
        }

        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__));
        }

        require_once ROOT_PATH . '/Config/database.php';

        if (!class_exists('Database', false)) {
            return false;
        }

        try {
            $db = Database::connect();
            if (!dbColumnExists($db, 'empresas', 'slug_tienda')) {
                clearPublicStoreContext();
                return false;
            }

            $sql = "SELECT id, nombre, slug_tienda FROM empresas WHERE slug_tienda = :slug LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($empresa) || empty($empresa)) {
                clearPublicStoreContext();
                return false;
            }

            $_SESSION['store_public_empresa_id'] = (int)($empresa['id'] ?? 0);
            $_SESSION['store_public_slug'] = trim((string)($empresa['slug_tienda'] ?? $slug));
            $_SESSION['store_public_nombre'] = trim((string)($empresa['nombre'] ?? ''));
            return (int)$_SESSION['store_public_empresa_id'] > 0;
        } catch (Throwable $e) {
            error_log('No se pudo resolver el contexto publico de tienda: ' . $e->getMessage());
            clearPublicStoreContext();
            return false;
        }
    }

    function currentStoreBaseUrl(?string $slug = null): string
    {
        $base = rtrim(base_url(), '/');
        $storeSlug = $slug === null ? getCurrentStoreSlug() : slugifyText($slug);
        if ($storeSlug === '') {
            return $base;
        }

        return $base . '/empresa/' . rawurlencode($storeSlug);
    }

    function appRoute(string $ruta, string $param = ''): string
    {
        $base = currentStoreBaseUrl();
        $ruta = strtolower(trim($ruta));
        $param = trim($param);

        switch ($ruta) {
            case 'inicio':
            case 'home':
                return $base . '/inicio';
            case 'tiendas':
                return $base . '/tiendas';
            case 'nosotros':
                return $base . '/nosotros';
            case 'contacto':
                return $base . '/contacto';
            case 'tienda':
                return $base . '/tienda';
            case 'tienda_search':
                return $base . '/tienda/search';
            case 'tienda_categoria':
                return $base . '/tienda/categoria/' . rawurlencode($param);
            case 'tienda_producto':
                return $base . '/tienda/producto/' . rawurlencode($param);
            case 'carrito':
                return $base . '/carrito';
            case 'carrito_procesarpago':
                return $base . '/carrito/procesarpago';
            case 'carrito_confirmacion':
                return $base . '/carrito/confirmacion';
            case 'factura_generarfactura':
                return $base . '/factura/generarFactura/' . rawurlencode($param);
            default:
                return $base . '/?ruta=' . rawurlencode($ruta) . ($param !== '' ? '&param=' . rawurlencode($param) : '');
        }
    }

    function imagenPngTieneTransparencia(string $filePath): bool
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return false;
        }

        $contenido = @file_get_contents($filePath);
        if (!is_string($contenido) || strlen($contenido) < 33) {
            return false;
        }

        $firma = "\x89PNG\r\n\x1a\n";
        if (strncmp($contenido, $firma, 8) !== 0) {
            return false;
        }

        $colorType = ord($contenido[25]);
        if ($colorType === 4 || $colorType === 6) {
            return true;
        }

        return strpos($contenido, 'tRNS') !== false;
    }

    function guardarImagenSubidaValidada(string $campoArchivo, array $config = []): ?array
    {
        if (!isset($_FILES[$campoArchivo]) || !is_array($_FILES[$campoArchivo])) {
            return null;
        }

        $archivo = $_FILES[$campoArchivo];
        $label = trim((string)($config['label'] ?? 'La imagen'));
        $error = isset($archivo['error']) ? (int)$archivo['error'] : UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            $mapErrores = [
                UPLOAD_ERR_INI_SIZE => $label . ' supera el limite permitido por el servidor.',
                UPLOAD_ERR_FORM_SIZE => $label . ' supera el tamano maximo permitido por el formulario.',
                UPLOAD_ERR_PARTIAL => $label . ' se subio parcialmente. Intenta nuevamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal para subir ' . strtolower($label) . '.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir ' . strtolower($label) . ' en disco.',
                UPLOAD_ERR_EXTENSION => 'Una extension del servidor bloqueo la subida de ' . strtolower($label) . '.',
            ];
            throw new RuntimeException($mapErrores[$error] ?? ('No se pudo cargar ' . strtolower($label) . '.'));
        }

        $tmpName = trim((string)($archivo['tmp_name'] ?? ''));
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Archivo invalido para ' . strtolower($label) . '.');
        }

        $maxBytes = isset($config['max_bytes']) ? (int)$config['max_bytes'] : (500 * 1024);
        $size = (int)($archivo['size'] ?? 0);
        if ($size <= 0 || $size >= $maxBytes) {
            $maxKb = max(1, (int)floor($maxBytes / 1024));
            throw new RuntimeException($label . ' debe pesar menos de ' . $maxKb . ' KB.');
        }

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw new RuntimeException($label . ' no es un archivo de imagen valido.');
        }

        $mime = strtolower((string)$imageInfo['mime']);
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);

        $allowedMimes = $config['allowed_mimes'] ?? ['image/png' => 'png'];
        if (!is_array($allowedMimes) || empty($allowedMimes) || !isset($allowedMimes[$mime])) {
            $permitidos = implode(', ', array_map(static function ($value) {
                return strtoupper((string)$value);
            }, array_values(is_array($allowedMimes) ? $allowedMimes : [])));
            $permitidos = $permitidos !== '' ? $permitidos : 'PNG';
            throw new RuntimeException($label . ' debe estar en formato ' . $permitidos . '.');
        }

        $exactWidth = isset($config['exact_width']) ? (int)$config['exact_width'] : 0;
        $exactHeight = isset($config['exact_height']) ? (int)$config['exact_height'] : 0;
        if (($exactWidth > 0 && $width !== $exactWidth) || ($exactHeight > 0 && $height !== $exactHeight)) {
            throw new RuntimeException($label . ' debe medir exactamente ' . $exactWidth . ' x ' . $exactHeight . ' px.');
        }

        if (!empty($config['require_transparency'])) {
            if ($mime !== 'image/png' || !imagenPngTieneTransparencia($tmpName)) {
                throw new RuntimeException($label . ' debe ser un PNG con fondo transparente.');
            }
        }

        $candidateDirs = [];
        if (!empty($config['rel_dirs']) && is_array($config['rel_dirs'])) {
            $candidateDirs = array_values($config['rel_dirs']);
        } elseif (!empty($config['rel_dir'])) {
            $candidateDirs = [(string)$config['rel_dir']];
        }

        if (empty($candidateDirs)) {
            throw new RuntimeException('No se configuro el directorio de destino para ' . strtolower($label) . '.');
        }

        $dirRel = '';
        $dirAbs = '';
        foreach ($candidateDirs as $candidateRel) {
            $candidateRel = trim(str_replace('\\', '/', (string)$candidateRel), '/');
            if ($candidateRel === '') {
                continue;
            }

            $candidateAbs = ROOT_PATH . '/' . $candidateRel;
            if (is_dir($candidateAbs) && is_writable($candidateAbs)) {
                $dirRel = $candidateRel;
                $dirAbs = $candidateAbs;
                break;
            }

            if (!is_dir($candidateAbs) && @mkdir($candidateAbs, 0775, true) && is_writable($candidateAbs)) {
                $dirRel = $candidateRel;
                $dirAbs = $candidateAbs;
                break;
            }
        }

        if ($dirAbs === '') {
            throw new RuntimeException('No hay permisos para guardar ' . strtolower($label) . ' en el servidor.');
        }

        $prefix = preg_replace('/[^a-z0-9_\-]/i', '', (string)($config['file_prefix'] ?? 'img'));
        if ($prefix === '') {
            $prefix = 'img';
        }

        $extension = (string)$allowedMimes[$mime];
        $fileName = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destinoAbs = $dirAbs . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $destinoAbs)) {
            throw new RuntimeException('No se pudo guardar ' . strtolower($label) . ' en el servidor.');
        }

        return [
            'relative_path' => $dirRel . '/' . $fileName,
            'file_name' => $fileName,
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'size' => $size,
        ];
    }

    function eliminarArchivoProyectoSiExiste(?string $ruta): void
    {
        $ruta = trim((string)$ruta);
        if ($ruta === '') {
            return;
        }

        if (preg_match('/^(https?:)?\/\//i', $ruta) || str_starts_with($ruta, 'data:')) {
            return;
        }

        $ruta = str_replace('\\', '/', $ruta);
        $ruta = ltrim($ruta, '/');
        if ($ruta === '' || strpos($ruta, '..') !== false) {
            return;
        }

        $rutaAbs = ROOT_PATH . '/' . $ruta;
        if (is_file($rutaAbs)) {
            @unlink($rutaAbs);
        }
    }

    function headerAdmin($data="")
    {
        $view_header = __DIR__ . "/../Views/Template/header_admin.php";
        if(file_exists($view_header)){
            require_once ($view_header);
        }else{
            die("No se encontró el archivo header_admin.php en: " . $view_header);
        }
    }

    function footerAdmin($data="")
    {
        $view_footer = __DIR__ . "/../Views/Template/footer_admin.php";
        if(file_exists($view_footer)){
            require_once ($view_footer);
        }else{
            die("No se encontró el archivo footer_admin.php en: " . $view_footer);
        }
    }

    function headerTienda($data="")
    {
        return;
    }

    function footerTienda($data="")
    {
        return;
    }

    //Muestra información formateada
    function dep($data)
    {
        $format  = print_r('<pre>');
        $format .= print_r($data);
        $format .= print_r('</pre>');
        return $format;
    }

    function getModal(string $nameModal, $data)
    {
        $view_modal = __DIR__ . "/../Views/Template/Modals/{$nameModal}.php";
        if(file_exists($view_modal)){
            require_once $view_modal;
        }else{
            die("No se encontró el archivo {$nameModal}.php en: " . $view_modal);
        }
    }

    // Iniciar saneamiento de codificación global para todos los scripts PHP que carguen este helper.
    inicializarUtf8();

    function getFile(string $url, $data)
    {
        ob_start();
        require_once("Views/{$url}.php");
        $file = ob_get_clean();
        return $file;
    }

    //Envio de correos con SMTP (PHPMailer) o fallback a mail()
    function sendEmail($data,$template)
    {
        $asunto = $data['asunto'];
        $emailDestino = $data['email'];
        $mailBrand = resolveEmailBranding(is_array($data) ? $data : []);
        $empresa = trim((string)($mailBrand['nombre'] ?? NOMBRE_REMITENTE));
        $remitente = trim((string)($mailBrand['correo'] ?? ''));
        if ($remitente === '' || !filter_var($remitente, FILTER_VALIDATE_EMAIL)) {
            $remitente = EMAIL_REMITENTE;
        }
        $smtpHost = 'smtp.gmail.com';
        $smtpUser = 'mowecompany05@gmail.com';
        $smtpPass = 'eytx wogx tyls aymc';
        
        $rutaPlantilla = __DIR__ . "/../Views/Template/Email/" . $template . ".php";
        if (!file_exists($rutaPlantilla)) {
            error_log("Plantilla de correo no encontrada: " . $rutaPlantilla);
            return false;
        }

        $phpMailerDisponible = file_exists(__DIR__ . '/../Libraries/phpmailer/PHPMailer.php');

        $data['mail_brand'] = $mailBrand;
        if (empty($data['empresa_nombre'])) {
            $data['empresa_nombre'] = $empresa;
        }
        
        ob_start();
        require_once($rutaPlantilla);
        $mensaje = ob_get_clean();
        
        // Intentar usar PHPMailer con SMTP (para hosting que bloquea mail())
        if ($phpMailerDisponible) {
            require_once __DIR__ . '/../Libraries/phpmailer/Exception.php';
            require_once __DIR__ . '/../Libraries/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/../Libraries/phpmailer/SMTP.php';

            try {
                $intentos = [
                    [PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS, 587],
                    [PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS, 465],
                ];

                foreach ($intentos as $configSmtp) {
                    [$seguridad, $puerto] = $configSmtp;
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                    $mail->isSMTP();
                    $mail->SMTPAuth = true;
                    $mail->Host = $smtpHost;
                    $mail->Username = $smtpUser;
                    $mail->Password = $smtpPass;
                    $mail->SMTPSecure = $seguridad;
                    $mail->Port = $puerto;
                    $mail->Timeout = 20;
                    $mail->SMTPAutoTLS = true;
                    $mail->SMTPDebug = 0;
                    $mail->CharSet = 'UTF-8';
                    $mail->Encoding = 'base64';

                    // Alineacion de remitente con cuenta autenticada para reducir spam.
                    $mail->setFrom($smtpUser, $empresa);
                    $mail->Sender = $smtpUser;
                    if (strtolower($remitente) !== strtolower($smtpUser)) {
                        $mail->addReplyTo($remitente, $empresa);
                    }
                    $mail->addAddress($emailDestino);

                    $mail->isHTML(true);
                    $mail->Subject = $asunto;
                    $mail->Body = $mensaje;
                    $mail->AltBody = trim((string)preg_replace('/\s+/', ' ', strip_tags($mensaje)));

                    $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
                    $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');

                    try {
                        $mail->send();
                        return true;
                    } catch (Exception $e) {
                        error_log("Fallo envio SMTP {$puerto}: {$mail->ErrorInfo}");
                    }
                }

                error_log('No se pudo enviar correo en ninguno de los puertos SMTP configurados.');
                return false;
            } catch (Exception $e) {
                error_log('Error al preparar PHPMailer: ' . $e->getMessage());
                return false;
            }
        }
        
        // Fallback a mail() solo si hay SMTP configurado de verdad
        $smtpHost = trim((string)ini_get('SMTP'));
        $smtpPort = (int)ini_get('smtp_port');
        if ($smtpHost === '' || strtolower($smtpHost) === 'localhost' || $smtpPort <= 0) {
            error_log('SMTP local no configurado para mail(); se omite envío fallback.');
            return false;
        }

        $de = "MIME-Version: 1.0\r\n";
        $de .= "Content-type: text/html; charset=UTF-8\r\n";
        $de .= "From: {$empresa} <{$remitente}>\r\n";
        
        $send = mail($emailDestino, $asunto, $mensaje, $de);
        return $send;
    }

    function resolveEmailBranding(array $data = []): array
    {
        $empresaId = (int)($data['empresa_id'] ?? ($_SESSION['store_public_empresa_id'] ?? ($_SESSION['empresa_id'] ?? 0)));
        $usaLogoCliente = !empty($data['usar_logo_cliente']);
        $brand = [
            'empresa_id' => $empresaId,
            'nombre' => trim((string)($data['empresa_nombre'] ?? ($_SESSION['empresa_nombre'] ?? NOMBRE_EMPRESA))) ?: NOMBRE_EMPRESA,
            'correo' => trim((string)($data['empresa_correo'] ?? ($_SESSION['empresa_contacto']['correo'] ?? EMAIL_EMPRESA))) ?: EMAIL_EMPRESA,
            'telefono' => trim((string)($data['empresa_telefono'] ?? ($_SESSION['empresa_contacto']['telefono'] ?? TELEMPRESA))) ?: TELEMPRESA,
            'direccion' => trim((string)($data['empresa_direccion'] ?? ($_SESSION['empresa_contacto']['direccion'] ?? DIRECCION))) ?: DIRECCION,
            'url_tienda' => trim((string)($data['empresa_url'] ?? '')),
            'logo' => trim((string)($data['empresa_logo'] ?? ($_SESSION['empresa_logo'] ?? ''))),
            'sobre_nosotros' => trim((string)($data['empresa_descripcion'] ?? ($_SESSION['empresa_contacto']['sobre_nosotros'] ?? (defined('DESCRIPCION') ? DESCRIPCION : '')))),
        ];

        if ($empresaId <= 0) {
            $brand = array_merge($brand, resolveGlobalEmailBranding());
        }

        try {
            if ($empresaId > 0 && class_exists('Database') && method_exists('Database', 'connect')) {
                $db = Database::connect();
                $stmt = $db->prepare("SELECT nombre, direccion, telefono, correo_electronico, email, imagen, sobre_nosotros, slug_tienda FROM empresas WHERE id = :empresa_id LIMIT 1");
                $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                $stmt->execute();
                $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($empresa) && !empty($empresa)) {
                    if (trim((string)($empresa['nombre'] ?? '')) !== '') {
                        $brand['nombre'] = trim((string)$empresa['nombre']);
                    }
                    if (trim((string)($empresa['direccion'] ?? '')) !== '') {
                        $brand['direccion'] = trim((string)$empresa['direccion']);
                    }
                    if (trim((string)($empresa['telefono'] ?? '')) !== '') {
                        $brand['telefono'] = trim((string)$empresa['telefono']);
                    }
                    $correoEmpresa = trim((string)($empresa['correo_electronico'] ?? ($empresa['email'] ?? '')));
                    if ($correoEmpresa !== '') {
                        $brand['correo'] = $correoEmpresa;
                    }
                    if (trim((string)($empresa['imagen'] ?? '')) !== '') {
                        $brand['logo'] = trim((string)$empresa['imagen']);
                    }
                    if (trim((string)($empresa['sobre_nosotros'] ?? '')) !== '') {
                        $brand['sobre_nosotros'] = trim((string)$empresa['sobre_nosotros']);
                    }
                    $slug = trim((string)($empresa['slug_tienda'] ?? ''));
                    if ($slug !== '' && function_exists('currentStoreBaseUrl')) {
                        $brand['url_tienda'] = currentStoreBaseUrl($slug);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('No se pudo resolver branding de correo por empresa: ' . $e->getMessage());
        }

        if (trim((string)$brand['logo']) === '') {
            $brandGlobal = resolveGlobalEmailBranding();
            if (trim((string)($brandGlobal['logo'] ?? '')) !== '') {
                $brand['logo'] = trim((string)$brandGlobal['logo']);
            }
        }

        if ($brand['url_tienda'] === '') {
            $brand['url_tienda'] = rtrim((string)BASE_URL, '/');
        }

        if ($usaLogoCliente) {
            $logoCliente = resolveClientEmailLogo();
            if ($logoCliente !== '') {
                $brand['logo'] = $logoCliente;
            }
        }

        $brand['logo_url'] = resolveEmailBrandImageUrl($brand['logo']);
        $brand['logo_path'] = resolveEmailBrandLocalPath($brand['logo']);
        $brand['logo_cid'] = resolveEmailBrandInlineImage($brand['logo']);

        if ($brand['logo_cid'] !== '') {
            $brand['logo_url'] = $brand['logo_cid'];
        }

        return $brand;
    }

    function resolveClientEmailLogo(): string
    {
        $rutaRelativa = 'Assets/images/Cliente.png';
        $rutaAbsoluta = defined('ROOT_PATH') ? ROOT_PATH . '/' . $rutaRelativa : dirname(__DIR__) . '/' . $rutaRelativa;

        if (is_file($rutaAbsoluta) && is_readable($rutaAbsoluta)) {
            return $rutaRelativa;
        }

        return '';
    }

    function resolveGlobalEmailBranding(): array
    {
        $logoGlobalFallback = '';
        $rutaFavicon = defined('ROOT_PATH') ? ROOT_PATH . '/favicon.ico' : dirname(__DIR__) . '/favicon.ico';

        $brand = [
            'empresa_id' => 0,
            'nombre' => 'Mowe Company',
            'correo' => EMAIL_EMPRESA,
            'telefono' => TELEMPRESA,
            'direccion' => DIRECCION,
            'url_tienda' => rtrim((string)BASE_URL, '/'),
            'logo' => $logoGlobalFallback,
            'sobre_nosotros' => defined('DESCRIPCION') ? (string)DESCRIPCION : '',
        ];

        try {
            $rutaPerfilGlobal = defined('ROOT_PATH') ? ROOT_PATH . '/Config/superadmin_profile.json' : dirname(__DIR__) . '/Config/superadmin_profile.json';
            if (is_file($rutaPerfilGlobal) && is_readable($rutaPerfilGlobal)) {
                $contenido = @file_get_contents($rutaPerfilGlobal);
                $perfil = is_string($contenido) ? json_decode($contenido, true) : null;
                if (is_array($perfil)) {
                    $nombre = trim((string)($perfil['nombre'] ?? ''));
                    if ($nombre !== '' && strtoupper($nombre) !== 'PERFIL GLOBAL') {
                        $brand['nombre'] = $nombre;
                    }

                    $logo = trim((string)($perfil['logo'] ?? ''));
                    if ($logo !== '') {
                        $brand['logo'] = $logo;
                    }

                    $correo = trim((string)($perfil['correo'] ?? ''));
                    if ($correo !== '') {
                        $brand['correo'] = $correo;
                    }

                    $telefono = trim((string)($perfil['telefono'] ?? ''));
                    if ($telefono !== '') {
                        $brand['telefono'] = $telefono;
                    }

                    $direccion = trim((string)($perfil['direccion'] ?? ''));
                    if ($direccion !== '') {
                        $brand['direccion'] = $direccion;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('No se pudo resolver branding global de correo: ' . $e->getMessage());
        }

        if (trim((string)$brand['logo']) === '' && is_file($rutaFavicon) && is_readable($rutaFavicon)) {
            $brand['logo'] = 'favicon.ico';
        }

        return $brand;
    }

    function resolveEmailBrandImageUrl(string $logo): string
    {
        $logo = trim($logo);
        if ($logo === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $logo)) {
            return $logo;
        }

        $logo = ltrim(str_replace('\\', '/', $logo), '/');
        return rtrim((string)resolvePublicEmailBaseUrl(), '/') . '/' . $logo;
    }

    function resolvePublicEmailBaseUrl(): string
    {
        $base = rtrim((string)BASE_URL, '/');
        $host = strtolower((string)(parse_url($base, PHP_URL_HOST) ?? ''));

        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || str_starts_with($host, '192.168.') || str_starts_with($host, '10.') || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) === 1) {
            return 'https://nombreempresa.ct.ws';
        }

        return $base;
    }

    function resolveEmailBrandLocalPath(string $logo): string
    {
        $logo = trim($logo);
        if ($logo === '' || preg_match('/^https?:\/\//i', $logo)) {
            return '';
        }

        $rutaRelativa = ltrim(str_replace('\\', '/', $logo), '/');
        $rutaBase = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
        $rutaArchivo = rtrim($rutaBase, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);

        return (is_file($rutaArchivo) && is_readable($rutaArchivo)) ? $rutaArchivo : '';
    }

    function resolveEmailBrandInlineImage(string $logo): string
    {
        $logo = trim($logo);
        if ($logo === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $logo)) {
            return '';
        }

        $rutaRelativa = ltrim(str_replace('\\', '/', $logo), '/');
        $rutaBase = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
        $rutaArchivo = rtrim($rutaBase, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);

        if (!is_file($rutaArchivo) || !is_readable($rutaArchivo)) {
            return '';
        }

        $contenido = @file_get_contents($rutaArchivo);
        if (!is_string($contenido) || $contenido === '') {
            return '';
        }

        $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($contenido);
    }

    function getPageRout(string $ruta){
        require_once("Libraries/Core/Mysql.php");
        $con = new Mysql();
        $sql = "SELECT * FROM post WHERE ruta = '$ruta' AND status != 0 ";
        $request = $con->select($sql);
        if(!empty($request)){
            $request['portada'] = $request['portada'] != "" ? media()."/images/uploads/".$request['portada'] : "";
        }
        return $request;
    }

    function getCatFooter(){
        require_once ("Models/CategoriasModel.php");
        $objCategoria = new CategoriasModel();
        $request = $objCategoria->getCategoriasFooter();
        return $request;
    }

    function getInfoPage(int $idpagina){
        require_once("Libraries/Core/Mysql.php");
        $con = new Mysql();
        $sql = "SELECT * FROM post WHERE idpost = $idpagina";
        $request = $con->select($sql);
        return $request;
    }

    function viewPage(int $idpagina){
        require_once("Libraries/Core/Mysql.php");
        $con = new Mysql();
        $sql = "SELECT * FROM post WHERE idpost = $idpagina ";
        $request = $con->select($sql);
        if( ($request['status'] == 2 AND isset($_SESSION['permisosMod']) AND $_SESSION['permisosMod']['u'] == true) OR $request['status'] == 1){
            return true;        
        }else{
            return false;
        }
    }

    function Meses(){
        $meses = array("Enero", 
                      "Febrero", 
                      "Marzo", 
                      "Abril", 
                      "Mayo", 
                      "Junio", 
                      "Julio", 
                      "Agosto", 
                      "Septiembre", 
                      "Octubre", 
                      "Noviembre", 
                      "Diciembre");
        return $meses;
    }

    function formatMoney($cantidad) {
        $cantidad = number_format((float)$cantidad,0,'',SPM);
        return $cantidad;
    }

    // ============= PERMISOS Y ROLES CONSOLIDADOS =============
    class PermisosHelper {
        public static function esSuperAdminSesion(): bool {
            if (!empty($_SESSION['superadmin_modo_empresa'])) {
                return false;
            }
            if (self::esSuperAdmin($_SESSION['rol'] ?? '')) {
                return true;
            }
            try {
                $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
                if ($usuarioId <= 0) {
                    return false;
                }
                require_once ROOT_PATH . '/Config/database.php';
                $db = Database::connect();
                $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id = :id LIMIT 1");
                $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
                $stmt->execute();
                $rolDb = (string)($stmt->fetchColumn() ?: '');
                return self::esSuperAdmin($rolDb);
            } catch (Exception $e) {
                error_log('Error en esSuperAdminSesion: ' . $e->getMessage());
                return false;
            }
        }

        public static function tienePermiso($modulo, $accion) {
            if (!isset($_SESSION['rol'])) {
                return false;
            }

            $rol = $_SESSION['rol'];
            $empresaIdSesion = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
            $empresaIdUserData = isset($_SESSION['userData']['empresa_id']) ? (int)$_SESSION['userData']['empresa_id'] : 0;
            $empresaContextoActivo = ($empresaIdSesion > 0 || $empresaIdUserData > 0) && empty($_SESSION['superadmin_modo_empresa']);

            // En contexto de empresa, todos los roles deben ver el menú y las tablas del sistema.
            if ($empresaContextoActivo) {
                return true;
            }

            // SuperAdmin y Admin tienen acceso total
            if (self::esSuperAdmin($rol) || self::esAdministrador($rol)) {
                return true;
            }
            return false;
        }

        private static function esSuperAdmin($rol): bool {
            $rolNormalizado = normalizarNombreRol($rol);
            return $rolNormalizado === 'super administrador' || $rolNormalizado === 'superadministrador';
        }

        private static function esAdministrador($rol): bool {
            $rolNormalizado = normalizarNombreRol($rol);
            return $rolNormalizado === 'administrador';
        }

        public static function verificarRolActivo(): bool {
            if (!isset($_SESSION['rol']) || empty($_SESSION['rol'])) {
                return false;
            }
            try {
                require_once ROOT_PATH . '/Config/database.php';
                $db = Database::connect();
                $rol = trim((string)$_SESSION['rol']);
                $query = "SELECT id, estado FROM roles WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre)) LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':nombre', $rol, PDO::PARAM_STR);
                $stmt->execute();
                $rolData = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$rolData || (int)($rolData['estado'] ?? 0) !== 1) {
                    return false;
                }
                return true;
            } catch (Exception $e) {
                error_log("Error en verificarRolActivo: " . $e->getMessage());
                return false;
            }
        }
    }

    //Más funciones helper según necesites...


/**
 * TenantHelper - Simplificado para sistema single-company
 * Ya no maneja multi-tenant, solo proporciona funciones de compatibilidad
 * Consolidado en Helpers.php desde Helpers/TenantHelper.php
 */
class TenantHelper {
    
    /**
     * Obtiene el ID de empresa de la sesión actual
     * En sistema single-company, siempre retorna 1 o el valor de sesión
     */
    public static function getEmpresaId() {
        if (isset($_SESSION['empresa_id']) && (int)$_SESSION['empresa_id'] > 0) {
            return (int)$_SESSION['empresa_id'];
        }
        return 1; // Default para single-company
    }

    /**
     * Obtiene el ID de usuario actual de la sesión
     */
    public static function getUsuarioId() {
        if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0) {
            return (int)$_SESSION['usuario_id'];
        }
        return 0;
    }

    /**
     * Obtiene el rol del usuario actual
     */
    public static function getRolActual() {
        return $_SESSION['rol'] ?? '';
    }

    /**
     * Verifica si un módulo está habilitado para el tipo de empresa
     * En single-company, todos los módulos activos están habilitados
     */
    public static function moduloHabilitado($moduloNombre) {
        try {
            // Para single-company, verificar que el módulo existe y está activo
            if (!defined('ROOT_PATH')) {
                return true; // Fallback seguro
            }

            require_once ROOT_PATH . '/Config/database.php';
            $db = Database::connect();

            // Buscar el módulo por nombre (case-insensitive)
            $query = "SELECT estado FROM modulos WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre)) LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':nombre', $moduloNombre, PDO::PARAM_STR);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado && (int)($resultado['estado'] ?? 0) === 1;
        } catch (Exception $e) {
            error_log("Error en TenantHelper::moduloHabilitado: " . $e->getMessage());
            return true; // Fallback permisivo
        }
    }

    /**
     * Obtiene el tipo de empresa del usuario actual
     * En single-company, retorna el tipo de la empresa
     */
    public static function getTipoEmpresa() {
        try {
            $empresaId = self::getEmpresaId();
            if ($empresaId <= 0) {
                return null;
            }

            if (!defined('ROOT_PATH')) {
                return null;
            }

            require_once ROOT_PATH . '/Config/database.php';
            $db = Database::connect();

            $query = "SELECT tipo_empresa_id FROM empresas WHERE id = :id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? (int)($resultado['tipo_empresa_id'] ?? 0) : null;
        } catch (Exception $e) {
            error_log("Error en TenantHelper::getTipoEmpresa: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica si el usuario actual es administrador de empresa
     */
    public static function esAdminEmpresa() {
        $rol = self::getRolActual();
        $rolNormalizado = self::normalizarRol($rol);
        return $rolNormalizado === 'administrador';
    }

    /**
     * Verifica si el usuario actual es super administrador
     */
    public static function esSuperAdmin() {
        $rol = self::getRolActual();
        $rolNormalizado = self::normalizarRol($rol);
        return $rolNormalizado === 'superadministrador';
    }

    /**
     * Normaliza un nombre de rol para comparación
     */
    private static function normalizarRol($rol) {
        return normalizarNombreRol($rol);
    }

    /**
     * Obtiene información de la empresa actual
     */
    public static function getEmpresaInfo() {
        try {
            $empresaId = self::getEmpresaId();
            if ($empresaId <= 0) {
                return null;
            }

            if (!defined('ROOT_PATH')) {
                return null;
            }

            require_once ROOT_PATH . '/Config/database.php';
            $db = Database::connect();

            $query = "SELECT * FROM empresas WHERE id = :id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en TenantHelper::getEmpresaInfo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Valida que el usuario tenga acceso a una empresa específica
     * En single-company, solo valida que la empresa exista y esté activa
     */
    public static function usuarioPuedeAccederEmpresa($empresaId) {
        try {
            $empresaId = (int)$empresaId;
            if ($empresaId <= 0) {
                return false;
            }

            if (!defined('ROOT_PATH')) {
                return false;
            }

            require_once ROOT_PATH . '/Config/database.php';
            $db = Database::connect();

            $query = "SELECT estado FROM empresas WHERE id = :id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $empresaId, PDO::PARAM_INT);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado && (int)($resultado['estado'] ?? 0) === 1;
        } catch (Exception $e) {
            error_log("Error en TenantHelper::usuarioPuedeAccederEmpresa: " . $e->getMessage());
            return false;
        }
    }
}
 