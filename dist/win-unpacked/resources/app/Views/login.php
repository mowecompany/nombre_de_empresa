<?php
// cSpell:disable
session_start();
ob_start(); // Iniciar buffer de salida para permitir redirecciones

// Cargar configuración global (ROOT_PATH, BASE_URL, etc.)
require_once __DIR__ . '/../Helpers/Helpers.php';
require_once ROOT_PATH . '/Config/database.php';

// Si llega desde el enlace del correo de bienvenida, forzar pantalla de login limpia.
if (isset($_GET['nuevo']) && $_GET['nuevo'] == '1') {
    if (isset($_SESSION['usuario_id'])) {
        session_unset();
        session_destroy();
        session_start();
    }

    $_SESSION['mensaje'] = 'INGRESA CON TU CORREO Y CONTRASEÑA TEMPORAL. LUEGO CAMBIA LA CONTRASEÑA Y VUELVE A INICIAR SESIÓN.';
    $_SESSION['tipo'] = 'success';
    $_SESSION['titulo'] = 'BIENVENIDO';
}

// Generar token CSRF único
if (empty($_SESSION['login_token'])) {
    $_SESSION['login_token'] = bin2hex(random_bytes(32));
}

$loginLogoPath = '/logo.ico';
$loginDefaultLogo = rtrim((string)base_url(), '/') . $loginLogoPath;
$loginLoadingLogo = $loginDefaultLogo;
if (!is_file(ROOT_PATH . $loginLogoPath)) {
    $loginLoadingLogo = rtrim((string)base_url(), '/') . $loginLogoPath;
}
$loginFavicon = $loginLoadingLogo;
$loginCompanyName = isset($_SESSION['empresa_nombre']) && trim((string)$_SESSION['empresa_nombre']) !== ''
    ? trim((string)$_SESSION['empresa_nombre'])
    : (defined('NOMBRE_EMPRESA') && trim((string)NOMBRE_EMPRESA) !== '' ? (string)NOMBRE_EMPRESA : 'AUTOSERVICIO MI ESTRELLA');
$correoPrefillLogin = trim((string)($_GET['correo'] ?? $_GET['email'] ?? ''));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INICIAR SESIÓN - <?= htmlspecialchars($loginCompanyName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($loginFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($loginFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="<?= base_url(); ?>/Assets/js/main.js"></script>
    <script>
        const base_url = <?= json_encode(base_url(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <style>
        :root {
            --bg:#f4f6f8;
            --card:#ffffff;
            --text:#242629;
            --muted:#6b7280;
            --accent:#2f4a5a;
            --input-border:#e6e9ee;
            --focus:rgba(47,74,90,0.12);
        }

        * {box-sizing:border-box;margin:0;padding:0}
        body{
            text-transform:uppercase; /* todo en mayúsculas */
             font-family: 'Segoe UI', Tahoma, sans-serif;
             background:var(--bg);
             min-height:100vh;
             display:flex;
             align-items:center;
             justify-content:center;
             flex-direction:column;
             gap:14px;
             padding:32px;
             color:var(--text);
         }

        .mowee-signature {
            position: fixed;
            left: calc(50% - 12px);
            bottom: 14px;
            transform: translateX(-50%);
            z-index: 1;
            color: var(--accent);
            font-weight: 700;
            text-transform: uppercase !important;
            opacity: 0.95;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .mowee-signature img {
           
            height: 16px;
            object-fit: contain;
            display: inline-block;
            
        }

        .mowee-signature > span:first-child {
            transform: translateX(-10px);
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
            gap: 3px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #divLoading img {
            margin-left: 2px;
            margin-right: 2px;
            position: relative;
            z-index: 1;
            width: 88px;
            height: 88px;
            object-fit: contain;
            filter: drop-shadow(0 10px 16px rgba(17, 78, 151, 0.25));
            animation: loadingLogoSpin 0.95s linear infinite;
        }

        @keyframes loadingLogoSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .bg-hero{
            position:fixed;inset:0;background:linear-gradient(180deg, rgba(47,74,90,0.03), transparent 40%);pointer-events:none;z-index:0;
        }
        /* Contenedor un poco más grande */
        .login-container{
            width:100%;
            max-width:520px; /* control de ancho sin forzar altura */
            background:var(--card);
            border-radius:14px;
            padding:34px 28px;
            box-shadow:0 12px 36px rgba(20,30,40,0.06);
            z-index:1;
            position:relative;
            display:flex;
            flex-direction:column;
            align-items:center; /* centra contenido */
            gap:18px;
        }
        .header-row{display:flex;flex-direction:column;align-items:center;gap:8px;margin:0;width:100%}
        /* Logo y títulos más grandes y en mayúsculas */
        .login-logo{display:flex;align-items:center;justify-content:center;width:100%;height:120px;}
        .login-logo-text{font-size:28px;color:var(--accent);font-weight:700;letter-spacing:0.18em;text-transform:uppercase;}
        h2{font-size:20px;color:var(--text);margin:0;font-weight:700;text-transform:uppercase}
        .lead{font-size:13px;color:var(--muted);margin-top:6px;text-transform:uppercase;letter-spacing:0.6px}
        .input-group{position:relative;margin-top:16px}
        .input-group .icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:15px}
        #empresaDestinoInfo,#empresaDestinoError{margin-top:-6px;margin-bottom:4px;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:600;letter-spacing:.5px;align-items:center;gap:8px;background:rgba(39,174,96,.08);border:1px solid rgba(39,174,96,.2)}
        #empresaDestinoError{background:rgba(231,76,60,.07);border-color:rgba(231,76,60,.2)}
        /* Inputs más grandes y texto en mayúsculas (placeholders también) */
        input,select{
            width:100%;
            padding:14px 16px 14px 44px;
            border:1px solid var(--input-border);
            border-radius:8px;
            background:#fff;
            color:var(--text);
            outline:none;
            font-size:16px;
            text-transform:uppercase;
        }
        input::placeholder{ text-transform:uppercase; opacity:0.7 }
        input:focus,select:focus{box-shadow:0 6px 18px var(--focus);border-color:var(--accent)}
        .toggle-password{position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--muted)}
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear{
            display:none;
        }
        /* formulario ocupa su contenido natural y no estira desproporcionadamente */
        form#loginForm{display:flex;flex-direction:column;gap:14px;margin-top:6px;width:100%;max-width:460px}
        .actions{margin-top:6px;display:flex;flex-direction:column;gap:10px;width:100%}
        .btn-primary{
            background:var(--accent);
            color:#fff;
            padding:14px;
            border-radius:8px;
            border:none;
            cursor:pointer;
            font-weight:700;
            font-size:16px;
            text-transform:uppercase;
        }
        .meta{display:flex;justify-content:space-between;align-items:center;margin-top:8px;font-size:13px;color:var(--muted);text-transform:uppercase}
        .forgot-password a{color:var(--accent);text-decoration:none}
        
        #loginForm{display:flex;flex-direction:column;gap:14px;margin-top:6px;width:100%;max-width:460px}
        
        /* Modal de cambio de contraseña y completar datos */
        .modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(20, 30, 40, 0.55);z-index:9999;align-items:center;justify-content:center}
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
        .modal-overlay.active{display:flex}
        .modal-box{
            background:var(--card);
            border:1px solid #dce3ea;
            border-radius:12px;
            padding:26px 22px;
            max-width:520px;
            width:92%;
            box-shadow:0 12px 28px rgba(20,30,40,0.18);
        }
        .modal-header{text-align:left;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #e6e9ee}
        .modal-header .modal-icon{font-size:18px;margin-right:8px;color:var(--accent);display:inline-block;vertical-align:middle}
        .modal-header h3{display:inline-block;font-size:20px;color:var(--text);margin:0 0 6px 0;text-transform:uppercase;letter-spacing:0.6px;font-weight:700;vertical-align:middle}
        .modal-header p{font-size:12px;color:var(--muted);margin:2px 0 0;text-transform:uppercase;letter-spacing:0.3px}
        .modal-form-group{margin-bottom:16px}
        .modal-form-group .input-group{margin-top:0}
        .modal-form-group select{width:100%;padding:14px 16px 14px 44px;border:1px solid var(--input-border);border-radius:8px;background:#fff;color:var(--text);outline:none;font-size:16px;text-transform:uppercase}
        .modal-form-group select:focus{box-shadow:0 6px 18px var(--focus);border-color:var(--accent)}
        .password-strength{margin-top:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding:4px 8px;border-radius:4px;display:inline-block}
        .password-strength.weak{color:#fff;background:#d32f2f}
        .password-strength.medium{color:#fff;background:#ff9800}
        .password-strength.strong{color:#fff;background:#4caf50}
        .modal-note{background:var(--bg);border-left:3px solid var(--accent);padding:12px 14px;margin-top:20px;border-radius:8px;font-size:12px;color:var(--muted);line-height:1.5}
        .modal-note strong{color:var(--accent);text-transform:uppercase}
        
        @media(max-width:480px){
            .login-container{width:95%;padding:20px}
            .login-logo{font-size:48px}
            h2{font-size:18px}
            .modal-box{width:95%;padding:28px 20px}
            .modal-header h3{font-size:19px}
            .modal-header .modal-icon{font-size:17px}
            .modal-note{font-size:11px;padding:10px}
        }

        #bloqueContainer {
            animation: slideDown 0.4s ease-out;
        }

        .company-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 0 auto 10px;
            padding: 0 10px;
            max-width: 640px;
            text-align: center;
        }

        .company-info img {
            width: 78px;
            height: 78px;
            object-fit: contain;
            border-radius: 18px;
            border: 1px solid rgba(47, 74, 90, 0.12);
            background: #fff;
            display: block;
        }

        .company-info .company-meta {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .company-info .company-meta strong {
            display: block;
            font-size: 15px;
            color: var(--text);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .block-progress-wrapper {
            margin: 14px auto 8px;
            max-width: 340px;
            text-align: left;
        }

        .btn-otra-cuenta {
            width: 100%;
            max-width: 340px;
            margin: 12px auto 0;
            border: none;
            border-radius: 8px;
            padding: 12px 14px;
            background: var(--accent);
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.06em;
            cursor: pointer;
            text-transform: uppercase;
            box-shadow: 0 10px 20px rgba(47, 74, 90, 0.18);
        }

        .btn-otra-cuenta:hover {
            filter: brightness(1.05);
        }

        .block-progress-wrapper .progress-container {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .block-progress-wrapper .progress-bar {
            height: 100%;
            transition: width 0.4s ease, background 0.4s ease;
            background: #d32f2f;
        }

        .block-progress-wrapper .progress-bar.medium {
            background: #ff9800;
        }

        .block-progress-wrapper .progress-bar.good {
            background: #4caf50;
        }

        .block-progress-wrapper .progress-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            font-size: 12px;
            color: #4b5563;
            letter-spacing: 0.03em;
        }

        #bloqueContainer {
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #temporizador {
            letter-spacing: 2px;
        }
     </style>
</head>
<body>
    <div id="divLoading">
        <div>
            <img id="loginLoadingLogoImg" src="<?= htmlspecialchars($loginLoadingLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Loading">
        </div>
    </div>
    <div class="bg-hero" aria-hidden="true"></div>

    <div class="login-container" role="main" aria-labelledby="loginTitle">
        <div class="header-row">
            <div class="company-info">
                <img class="company-logo" src="<?= htmlspecialchars($loginDefaultLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo de la empresa">
                <div class="company-meta">
                    <strong><?= htmlspecialchars($loginCompanyName, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            </div>
            <div>
                <h2 id="loginTitle">BIENVENIDO DE NUEVO</h2>
                <p class="lead">INGRESA TUS CREDENCIALES PARA ACCEDER A TU CUENTA</p>
            </div>
        </div>
        
        <!-- Contenedor para mostrar bloqueo -->
        <div id="bloqueContainer" style="display: none; width: 100%; text-align: center; margin-bottom: 20px;">
            <div id="bloqueBox" style="background: #fff3cd; border: 2px solid #ff9800; border-radius: 8px; padding: 20px;">
                <i class="fas fa-lock" id="bloqueIcon" style="font-size: 48px; color: #ff9800; margin-bottom: 15px; display: block;"></i>
                <h3 id="bloqueTitle" style="color: #ff9800; margin: 10px 0; text-transform: uppercase;">CUENTA BLOQUEADA</h3>
                <p id="razonBloqueo" style="color: #666; font-size: 14px; margin: 10px 0;"></p>
                <div id="temporizador" style="font-size: 28px; font-weight: bold; color: #ff9800; margin: 15px 0; font-family: monospace;"></div>
                <div class="block-progress-wrapper">
                    <div class="progress-container">
                        <div class="progress-bar" id="pageProgressBar" style="width: 0%"></div>
                    </div>
                    <div class="progress-meta">
                        <span id="pageProgressStatus">BLOQUEO ACTIVO</span>
                        <span class="progress-percent" id="pageProgressPercent">0%</span>
                    </div>
                </div>
                <p id="bloqueAdvice" style="color: #666; font-size: 12px; margin-top: 10px;">POR FAVOR, ESPERA ANTES DE INTENTAR DE NUEVO</p>
                <button type="button" id="btnOtraCuenta" class="btn-otra-cuenta">INICIAR SESIÓN CON OTRA CUENTA</button>
            </div>
        </div>
        
        <form method="POST" action="login.php" id="loginForm" autocomplete="off" novalidate>
            <!-- Token CSRF para prevenir resubmisiones -->
            <input type="hidden" name="login_token" value="<?php echo htmlspecialchars($_SESSION['login_token']); ?>">
            
            <div class="input-group">
                <i class="fas fa-envelope icon" aria-hidden="true"></i>
                <input type="email" id="correo" name="correo" placeholder="CORREO ELECTRÓNICO" autocomplete="off" required aria-required="true" value="<?= htmlspecialchars($correoPrefillLogin, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="input-group">
                <i class="fas fa-lock icon" aria-hidden="true"></i>
                <input type="password" id="contrasena" name="contrasena" placeholder="CONTRASEÑA" autocomplete="off" required aria-required="true" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" title="INGRESA EXACTAMENTE 6 DÍGITOS NUMÉRICOS">
                <i class="fas fa-eye toggle-password" title="MOSTRAR CONTRASEÑA" role="button" aria-label="MOSTRAR CONTRASEÑA"></i>
            </div>

            <div class="meta">
                <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" id="remember" name="remember" value="1"> RECORDARME</label>
            </div>

            <div class="actions">
                <button type="submit" class="btn-primary"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> INGRESAR</button>
            </div>
        </form>

        <?php
        if (isset($_SESSION['mensaje'])) {
            $msg = strtoupper($_SESSION['mensaje']);
            $tipo = $_SESSION['tipo'] ?? 'info';
            $titulo = strtoupper($_SESSION['titulo'] ?? 'AVISO');
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: '" . $tipo . "',
                        title: '" . $titulo . "',
                        text: '" . $msg . "',
                        confirmButtonColor: '#2f4a5a'
                    });
                });
            </script>";
            unset($_SESSION['mensaje'], $_SESSION['tipo'], $_SESSION['titulo']);
        }
        ?>


        <?php
        // ============================================================================
        // PROCESAR LOGIN
        // ============================================================================
        // Procesar solo si es POST DIRECTO con token válido
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && 
            !empty($_POST['correo']) && 
            !empty($_POST['contrasena']) &&
            !empty($_POST['login_token']) &&
            $_POST['login_token'] === $_SESSION['login_token']) {

            require_once __DIR__ . '/../Models/Usuario.php';
            $correoLogin = strtolower(trim((string)$_POST['correo']));
            $contrasenaLogin = trim((string)$_POST['contrasena']);

            // Validación de contraseña del lado del servidor: solo números y exactamente 6 dígitos.
            if (!preg_match('/^[0-9]{6}$/', $contrasenaLogin)) {
                unset($_SESSION['login_token']);
                $mensaje = urlencode('LA CONTRASEÑA DEBE SER NUMÉRICA Y TENER EXACTAMENTE 6 DÍGITOS');
                header("Location: login.php?error=1&msg={$mensaje}");
                exit();
            }

            // Invalidar el token inmediatamente para evitar resubmisiones
            unset($_SESSION['login_token']);

            $empresaDestinoInput = trim((string)($_POST['empresa_destino'] ?? ''));
            $empresaDestino = ctype_digit($empresaDestinoInput) ? (int)$empresaDestinoInput : 0;
            
            include_once __DIR__ . '/../Controllers/LoginController.php';
            $loginController = new LoginController();
            $resultado = $loginController->iniciarSesion(
                $_POST['correo'],
                $_POST['contrasena'],
                $empresaDestino > 0 ? $empresaDestino : null
            );
            
            if (!$resultado['success']) {
                // Redirigir a GET con los datos de error para evitar recargas duplicadas
                if (isset($resultado['bloqueado']) && $resultado['bloqueado']) {
                    $razon = urlencode(strtoupper($resultado['razon'] ?? 'CUENTA BLOQUEADA'));
                    $minutos = $resultado['minutos_restantes'] ?? 10;
                    $duracion = $resultado['duracion_bloqueo'] ?? 10;
                    $fecha_bloqueo = urlencode($resultado['fecha_bloqueo'] ?? '');
                    $correo_bloqueado = urlencode($_POST['correo'] ?? '');
                    header("Location: login.php?bloqueado=1&razon=$razon&minutos=$minutos&duracion=$duracion&fecha=$fecha_bloqueo&correo=$correo_bloqueado");
                    exit();
                } else {
                    // Error sin bloqueo
                    $intentos = $resultado['intentos'] ?? 0;
                    $mensaje = urlencode(strtoupper($resultado['message'] ?? 'CREDENCIALES INVÁLIDAS'));
                    header("Location: login.php?error=1&intentos=$intentos&msg=$mensaje");
                    exit();
                }
            } else {
                // Login exitoso
                if (isset($resultado['requiere_cambio_contrasena']) && $resultado['requiere_cambio_contrasena']) {
                    // Requiere cambio de contraseña - redirigir a modal
                    header('Location: login.php?cambiar_pass=1');
                    exit();
                } elseif (isset($resultado['requiere_configuracion_empresa']) && $resultado['requiere_configuracion_empresa']) {
                    // Requiere configuración inicial de empresa (solo primera vez de admin)
                    header('Location: login.php?config_empresa=1');
                    exit();
                } elseif (isset($resultado['requiere_completar_datos']) && $resultado['requiere_completar_datos']) {
                    // Requiere completar datos personales
                    header('Location: login.php?completar_datos=1');
                    exit();
                } else {
                    // Login normal
                    header('Location: dashboard.php');
                    exit();
                }
            }
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Token inválido o faltan campos - mostrar error pero NO incrementar intentos
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'warning',
                        title: 'SESIÓN EXPIRADA',
                        html: `
                            <p style=\"margin: 15px 0; color: #666; font-size: 15px;\">
                                TU SESIÓN HA EXPIRADO POR SEGURIDAD.
                            </p>
                            <div style=\"background: #fff7ed; 
                                        padding: 12px; border-radius: 10px; margin-top: 15px; 
                                        border-left: 4px solid #ff9800;\">
                                <strong>NOTA:</strong> POR FAVOR, INICIA SESIÓN NUEVAMENTE.
                            </div>
                        `,
                        confirmButtonText: 'ENTENDIDO',
                        confirmButtonColor: '#2f4a5a',
                        customClass: {
                            popup: 'animated-popup'
                        },
                        didClose: function() {
                            window.location.href = 'login.php';
                        }
                    });
                });
            </script>";
        }
        
        // Limpiar bloqueos si viene del email
        if (isset($_GET['nuevo']) && $_GET['nuevo'] == 1) {
            echo "<script>
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, 'login.php');
                }
            </script>";
        } else {
            // Mostrar bloqueo si viene desde GET (parámetro bloqueado)
            if (isset($_GET['bloqueado']) && $_GET['bloqueado'] == 1) {
                $razon = strtoupper(htmlspecialchars($_GET['razon'] ?? 'CUENTA BLOQUEADA'));
                $minutos = $_GET['minutos'] ?? 10;
                $duracion_total = $_GET['duracion'] ?? 10; // Duración total del bloqueo en minutos
                $fecha_bloqueo = urldecode($_GET['fecha'] ?? '');
                $correo_usuario = htmlspecialchars($_GET['correo'] ?? '');
                $fecha_bloqueo_iso = '';
                try {
                    date_default_timezone_set('America/Bogota');
                    if (trim($fecha_bloqueo) !== '') {
                        $fecha_bloqueo_iso = (new DateTime($fecha_bloqueo, new DateTimeZone(date_default_timezone_get())))->format(DateTime::ATOM);
                    }
                } catch (Exception $e) {
                    $fecha_bloqueo_iso = '';
                }
                ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Estilos personalizados para la alerta profesional
                    const style = document.createElement('style');
                    style.textContent = `
                        .swal2-container {
                            backdrop-filter: blur(3px);
                        }
                        .block-alert {
                            text-align: left;
                            margin-top: 4px;
                        }
                        .block-reason {
                            display: inline-block;
                            margin: 2px 0 12px;
                            padding: 7px 12px;
                            border-radius: 999px;
                            border: 1px solid #f3c08a;
                            background: #fff7ed;
                            color: #9a3412;
                            font-size: 12px;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: .3px;
                        }
                        .progress-container {
                            width: 100%;
                            height: 10px;
                            background: #e5e7eb;
                            border-radius: 999px;
                            overflow: hidden;
                            margin: 12px 0 8px 0;
                            position: relative;
                        }
                        .progress-meta {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            margin-bottom: 10px;
                            font-size: 12px;
                            color: #6b7280;
                        }
                        .progress-percent {
                            color: #111827;
                            font-weight: 700;
                        }
                        .progress-bar {
                            height: 100%;
                            transition: all 0.3s ease;
                            background: #d32f2f;
                        }
                        .progress-bar.medium {
                            background: #ff9800;
                        }
                        .progress-bar.good {
                            background: #4caf50;
                        }
                        .timer-display {
                            font-size: 34px;
                            font-weight: 700;
                            color: #1f2937;
                            margin: 8px 0 6px;
                            font-family: 'Courier New', monospace;
                            letter-spacing: 1px;
                        }
                        .block-message {
                            color: #4b5563;
                            font-size: 13px;
                            line-height: 1.5;
                            margin: 8px 0 0;
                        }
                        .unlock-message {
                            font-size: 16px;
                            color: #4caf50;
                            font-weight: 700;
                        }
                    `;
                    document.head.appendChild(style);
                    
                    // Mostrar bloque visual en la página
                    document.getElementById('bloqueContainer').style.display = 'block';
                    document.getElementById('razonBloqueo').textContent = <?= json_encode($razon, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                    document.getElementById('loginForm').style.display = 'none';

                    const btnOtraCuenta = document.getElementById('btnOtraCuenta');
                    if (btnOtraCuenta) {
                        btnOtraCuenta.addEventListener('click', function () {
                            if (window.Swal) {
                                Swal.close();
                            }
                            const bloqueContainer = document.getElementById('bloqueContainer');
                            const loginForm = document.getElementById('loginForm');
                            const correoInput = document.getElementById('correo');
                            const passwordInput = document.getElementById('contrasena');

                            if (bloqueContainer) {
                                bloqueContainer.style.display = 'none';
                            }
                            if (loginForm) {
                                loginForm.style.display = 'flex';
                            }
                            if (correoInput) {
                                correoInput.value = '';
                            }
                            if (passwordInput) {
                                passwordInput.value = '';
                            }
                            if (window.history.replaceState) {
                                window.history.replaceState(null, null, 'login.php');
                            }
                        });
                    }

                    const razonBloqueo = <?= json_encode($razon, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                    const fechaBloqueoIso = <?= json_encode($fecha_bloqueo_iso, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                    const duracionTotal = Number(<?= json_encode((int)$duracion_total, JSON_NUMERIC_CHECK); ?>) * 60;
                    const correoUsuario = <?= json_encode($correo_usuario, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                    const resetUrl = base_url + '/Controllers/LoginController.php?action=resetear_bloqueo';

                    let fechaBloqueo = null;
                    if (fechaBloqueoIso && fechaBloqueoIso !== '') {
                        fechaBloqueo = new Date(fechaBloqueoIso);
                    }

                    const diferenciaInicial = fechaBloqueo ? fechaBloqueo.getTime() - Date.now() : -1;
                    if (!fechaBloqueo || Number.isNaN(fechaBloqueo.getTime()) || diferenciaInicial <= 0) {
                        console.error('Fecha de bloqueo inválida o expirado:', fechaBloqueoIso);
                        if (window.history.replaceState) {
                            window.history.replaceState(null, null, 'login.php');
                        }
                        window.location.href = 'login.php';
                        return;
                    }

                    const swalHtml = `
                        <div class="block-alert">
                            <span class="block-reason">${razonBloqueo}</span>
                            <div class="progress-meta">
                                <span>TIEMPO RESTANTE</span>
                                <span class="progress-percent" id="progressPercent">0%</span>
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar" id="swalProgressBar" style="width: 0%"></div>
                            </div>
                            <div class="timer-display" id="swalTimer">--:--:--</div>
                            <p class="block-message">La cuenta se mantendrá bloqueada durante el periodo indicado.</p>
                        </div>
                    `;

                    Swal.fire({
                        icon: 'warning',
                        title: 'CUENTA BLOQUEADA',
                        html: swalHtml,
                        confirmButtonText: 'ENTENDIDO',
                        confirmButtonColor: '#2f4a5a',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showCloseButton: false,
                        didOpen: () => {
                            actualizarDisplay();
                        }
                    });

                    function actualizarDisplay() {
                        if (!fechaBloqueo || Number.isNaN(fechaBloqueo.getTime())) {
                            console.error('Fecha de bloqueo inválida:', fechaBloqueoIso);
                            return;
                        }
                        
                        const ahora = new Date();
                        const diferencia = Math.max(0, fechaBloqueo.getTime() - ahora.getTime());
                        const segundosRestantes = Math.floor(diferencia / 1000);
                        
                        // Log para debugging (solo primera vez)
                        if (!window.primeraActualizacion) {
                            const tiempoTranscurridoInicial = Math.max(0, duracionTotal - Math.floor(diferencia / 1000));
                            const porcentajeInicial = Math.round((tiempoTranscurridoInicial / duracionTotal) * 100);
                            
                            console.log('=== INICIO TEMPORIZADOR ===');
                            console.log('Fecha actual:', ahora.toLocaleString());
                            console.log('  Timestamp actual:', ahora.getTime());
                            console.log('Fecha de bloqueo (fin):', fechaBloqueo.toLocaleString());
                            console.log('  Timestamp bloqueo:', fechaBloqueo.getTime());
                            console.log('Diferencia (ms):', diferencia);
                            console.log('Diferencia (minutos):', Math.round(diferencia / 1000 / 60 * 10) / 10);
                            console.log('Duración total (seg):', duracionTotal);
                            console.log('Segundos restantes:', segundosRestantes);
                            console.log('Tiempo transcurrido:', tiempoTranscurridoInicial, 'seg');
                            console.log('📊 PROGRESO INICIAL:', porcentajeInicial + '%');
                            console.log('============================');
                            window.primeraActualizacion = true;
                        }
                        
                        if (diferencia <= 0) {
                            if (correoUsuario) {
                                // Hacer llamada AJAX para resetear intentos
                                fetch(resetUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'correo=' + encodeURIComponent(correoUsuario)
                                })
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Intentos reseteados:', data);
                                })
                                .catch(error => {
                                    console.error('Error al resetear intentos:', error);
                                });
                            }
                            
                            // Desbloquear
                            Swal.fire({
                                icon: 'success',
                                title: '✅ CUENTA DESBLOQUEADA',
                                html: '<p class=\"unlock-message\">¡PUEDES INTENTAR INICIAR SESIÓN NUEVAMENTE!</p>',
                                confirmButtonText: 'ENTENDIDO',
                                confirmButtonColor: '#2f4a5a',
                                allowOutsideClick: false
                            }).then(() => {
                                window.location.href = 'login.php';
                            });
                            return;
                        }
                        
                        // Calcular tiempo restante
                        const horas = Math.floor(diferencia / (1000 * 60 * 60));
                        const minutos = Math.floor((diferencia % (1000 * 60 * 60)) / (1000 * 60));
                        const segundos = Math.floor((diferencia % (1000 * 60)) / 1000);
                        
                        // Calcular progreso basado en el tiempo RESTANTE
                        // duracionTotal = tiempo total del bloqueo (ej: 600 seg para 10 min)
                        // segundosRestantes = tiempo que falta para desbloquear (disminuye)
                        // tiempoTranscurrido = tiempo que ya pasó desde el inicio del bloqueo
                        const tiempoRestante = segundosRestantes;
                        const tiempoTranscurrido = Math.max(0, duracionTotal - tiempoRestante);
                        const porcentajeRestante = Math.min(100, Math.max(0, (tiempoRestante / duracionTotal) * 100));
                        const porcentajeTranscurrido = Math.min(100, Math.max(0, (tiempoTranscurrido / duracionTotal) * 100));
                        const estadoProgreso = porcentajeTranscurrido >= 75 ? 'CASI DESBLOQUEADO' : porcentajeTranscurrido >= 50 ? 'ATENCIÓN' : 'BLOQUEO ACTIVO';
                        const claseProgreso = porcentajeTranscurrido >= 75 ? 'good' : porcentajeTranscurrido >= 50 ? 'medium' : '';
                        const colorEscala = claseProgreso === 'good' ? '#4caf50' : claseProgreso === 'medium' ? '#ff9800' : '#d32f2f';
                        
                        // Actualizar temporizador en la página principal
                        const tempElement = document.getElementById('temporizador');
                        if (tempElement) {
                            tempElement.textContent = 
                                String(horas).padStart(2, '0') + ':' + 
                                String(minutos).padStart(2, '0') + ':' + 
                                String(segundos).padStart(2, '0');
                            tempElement.style.color = colorEscala;
                        }
                        
                        // Actualizar temporizador en la alerta SweetAlert
                        const swalTimer = document.getElementById('swalTimer');
                        if (swalTimer) {
                            swalTimer.textContent = 
                                String(horas).padStart(2, '0') + ':' + 
                                String(minutos).padStart(2, '0') + ':' + 
                                String(segundos).padStart(2, '0');
                            swalTimer.style.color = colorEscala;
                        }
                        
                        // Log del progreso para verificar
                        const porcentajeRedondeado = Math.round(porcentajeTranscurrido);
                        if (porcentajeRedondeado % 5 === 0 && !window['logged_' + porcentajeRedondeado]) {
                            console.log('⏱️ Progreso:', porcentajeRedondeado + '%', '| Transcurrido:', tiempoTranscurrido + 's', '| Restante:', tiempoRestante + 's', '| Total:', duracionTotal + 's');
                            window['logged_' + porcentajeRedondeado] = true;
                        }
                        
                        // Actualizar barra de progreso
                        const progressBar = document.getElementById('swalProgressBar');
                        const progressPercent = document.getElementById('progressPercent');
                        const pageProgressBar = document.getElementById('pageProgressBar');
                        const pageProgressPercent = document.getElementById('pageProgressPercent');
                        const pageProgressStatus = document.getElementById('pageProgressStatus');
                        const bloqueTitle = document.getElementById('bloqueTitle');
                        const bloqueIcon = document.getElementById('bloqueIcon');
                        const bloqueBox = document.getElementById('bloqueBox');
                        const bloqueAdvice = document.getElementById('bloqueAdvice');
                        const swalBlockReason = document.querySelector('.swal2-container .block-reason');
                        
                        if (progressBar && progressPercent) {
                            progressBar.style.width = porcentajeTranscurrido + '%';
                            progressPercent.textContent = Math.round(porcentajeTranscurrido) + '%';
                            progressBar.classList.remove('medium', 'good');
                            if (claseProgreso === 'good') {
                                progressBar.classList.add('good');
                            } else if (claseProgreso === 'medium') {
                                progressBar.classList.add('medium');
                            }
                        }
                        
                        if (pageProgressBar && pageProgressPercent && pageProgressStatus) {
                            pageProgressBar.style.width = porcentajeTranscurrido + '%';
                            pageProgressPercent.textContent = Math.round(porcentajeTranscurrido) + '%';
                            pageProgressStatus.textContent = estadoProgreso;
                            pageProgressBar.classList.remove('medium', 'good');
                            if (claseProgreso === 'good') {
                                pageProgressBar.classList.add('good');
                            } else if (claseProgreso === 'medium') {
                                pageProgressBar.classList.add('medium');
                            }
                            pageProgressStatus.style.color = colorEscala;
                        }

                        if (bloqueTitle) {
                            bloqueTitle.style.color = colorEscala;
                        }
                        if (bloqueIcon) {
                            bloqueIcon.style.color = colorEscala;
                        }
                        if (bloqueBox) {
                            bloqueBox.style.borderColor = colorEscala;
                            bloqueBox.style.backgroundColor = claseProgreso === 'good' ? '#e8f5e9' : claseProgreso === 'medium' ? '#fff4e5' : '#fdecea';
                        }
                        if (swalBlockReason) {
                            swalBlockReason.style.borderColor = colorEscala;
                            swalBlockReason.style.color = colorEscala;
                            swalBlockReason.style.backgroundColor = claseProgreso === 'good' ? '#e8f5e9' : claseProgreso === 'medium' ? '#fff4e5' : '#fdecea';
                        }
                        if (bloqueAdvice) {
                            bloqueAdvice.style.color = colorEscala;
                        }
                        
                        // Continuar actualizando
                        setTimeout(actualizarDisplay, 1000);
                    }
                });
                </script>
            <?php
        }
        
        // Mostrar error si viene desde GET (parámetro error)
        if (isset($_GET['error']) && $_GET['error'] == 1) {
            $intentos = $_GET['intentos'] ?? 0;
            $mensaje = strtoupper(htmlspecialchars($_GET['msg'] ?? 'CREDENCIALES INVÁLIDAS'));
            
            // Calcular el color del indicador según los intentos
            $colorIndicador = '#4caf50'; // Verde por defecto
            $nivelRiesgo = 'BAJO';
            if ($intentos >= 4) {
                $colorIndicador = '#d32f2f'; // Rojo
                $nivelRiesgo = 'ALTO';
            } elseif ($intentos >= 2) {
                $colorIndicador = '#ff9800'; // Naranja
                $nivelRiesgo = 'MEDIO';
            }
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const style = document.createElement('style');
                    style.textContent = `
                        .credentials-alert {
                            text-align: left;
                            margin-top: 6px;
                        }
                        .credentials-alert .alert-message {
                            margin: 0 0 14px 0;
                            color: #4a4a4a;
                            font-size: 14px;
                            line-height: 1.55;
                        }
                        .credentials-alert .alert-status {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 10px;
                            padding: 12px 14px;
                            border: 1px solid #e8ebef;
                            border-left: 4px solid $colorIndicador;
                            border-radius: 10px;
                            background: #fafbfd;
                            margin-bottom: 14px;
                        }
                        .credentials-alert .status-label {
                            font-size: 12px;
                            letter-spacing: 0.4px;
                            text-transform: uppercase;
                            color: #6f7782;
                            margin-bottom: 2px;
                        }
                        .credentials-alert .status-value {
                            font-size: 15px;
                            font-weight: 700;
                            color: #1f2933;
                        }
                        .credentials-alert .risk-badge {
                            font-size: 12px;
                            font-weight: 700;
                            text-transform: uppercase;
                            color: $colorIndicador;
                            background: {$colorIndicador}1A;
                            border: 1px solid {$colorIndicador}4D;
                            border-radius: 999px;
                            padding: 5px 10px;
                        }
                        .attempt-indicator {
                            display: flex;
                            gap: 6px;
                            margin: 12px 0 0;
                        }
                        .attempt-dot {
                            flex: 1;
                            height: 8px;
                            border-radius: 999px;
                            background: #e5e7eb;
                            transition: all 0.3s ease;
                        }
                        .attempt-dot.active {
                            background: $colorIndicador;
                            box-shadow: 0 0 0 1px {$colorIndicador}33;
                        }
                        .warning-message {
                            background: #fff;
                            padding: 12px 14px;
                            border-radius: 10px;
                            border-left: 4px solid $colorIndicador;
                            border: 1px solid #eceff3;
                            font-size: 13px;
                            color: #4b5563;
                        }
                    `;
                    document.head.appendChild(style);
                    
                    // Generar indicadores de intentos
                    let dotsHTML = '';
                    for (let i = 1; i <= 5; i++) {
                        const activeClass = i <= $intentos ? 'active' : '';
                        dotsHTML += `<div class=\"attempt-dot \${activeClass}\"></div>`;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'CREDENCIALES INVÁLIDAS',
                        html: `
                            <div class=\"credentials-alert\">
                                <p class=\"alert-message\">$mensaje</p>
                                <div class=\"alert-status\">
                                    <div>
                                        <div class=\"status-label\">INTENTOS DE ACCESO</div>
                                        <div class=\"status-value\">INTENTO $intentos DE 5</div>
                                    </div>
                                    <span class=\"risk-badge\">RIESGO $nivelRiesgo</span>
                                </div>
                                <div class=\"attempt-indicator\">\${dotsHTML}</div>
                            </div>
                            <div class=\"warning-message\">
                                <strong>CONSEJO DE SEGURIDAD:</strong><br>
                                VERIFICA QUE ESTÉS USANDO EL CORREO Y CONTRASEÑA CORRECTOS.
                            </div>
                        `,
                        confirmButtonText: 'ENTENDIDO',
                        confirmButtonColor: '#2f4a5a',
                        customClass: {
                            popup: 'animated-popup'
                        },
                        didClose: function() {
                            // Limpiar URL después de cerrar alert
                            window.history.replaceState({}, document.title, 'login.php');
                        }
                    });
                });
            </script>";
        }

        }
        ?>
    </div>

    <div class="mowee-signature"><span>DEVELOPED BY  </span><img src="<?= htmlspecialchars(rtrim((string)base_url(), '/') . '/favicon.ico', ENT_QUOTES, 'UTF-8'); ?>" alt="M"><span>OWE COMPANY</span></div>
    
    <!-- Modal de Cambio de Contraseña Obligatorio -->
    <div id="modalCambioPass" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <span class="modal-icon"><i class="fas fa-lock"></i></span>
                <h3>CAMBIO DE CONTRASEÑA REQUERIDO</h3>
                <p>Por seguridad, debes cambiar tu contraseña temporal</p>
            </div>
            <form id="formCambioPassword" method="POST" action="<?php echo BASE_URL; ?>/Controllers/procesar_cambio_contrasena.php">
                <input type="hidden" name="accion" value="cambio_obligatorio">
                <input type="hidden" name="usuario_id" value="<?php echo $_SESSION['usuario_id'] ?? ''; ?>">
                
                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" id="password_actual" name="password_actual" placeholder="CONTRASEÑA ACTUAL (6 DIGITOS)" required autocomplete="off" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" title="INGRESA EXACTAMENTE 6 DIGITOS">
                        <i class="fas fa-eye toggle-password" data-target="password_actual"></i>
                    </div>
                </div>
                
                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-key icon"></i>
                        <input type="password" id="password_nueva" name="password_nueva" placeholder="NUEVA CONTRASEÑA (6 DIGITOS)" required autocomplete="new-password" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" title="INGRESA EXACTAMENTE 6 DIGITOS">
                        <i class="fas fa-eye toggle-password" data-target="password_nueva"></i>
                    </div>
                    <div id="passwordStrength" class="password-strength" style="display:none;"></div>
                </div>
                
                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-check-circle icon"></i>
                        <input type="password" id="password_confirmar" name="password_confirmar" placeholder="CONFIRMAR NUEVA CONTRASEÑA" required autocomplete="new-password" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" title="INGRESA EXACTAMENTE 6 DIGITOS">
                        <i class="fas fa-eye toggle-password" data-target="password_confirmar"></i>
                    </div>
                </div>
                
                <div class="modal-note">
                    <strong>IMPORTANTE:</strong> Después de cambiar tu contraseña, la sesión se cerrará automáticamente. Deberás iniciar sesión nuevamente con tu nueva contraseña.
                </div>
                
                <div class="actions" style="margin-top:20px;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> CAMBIAR CONTRASEÑA
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de Completar Datos Personales -->
    <div id="modalCompletarDatos" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <span class="modal-icon"><i class="fas fa-id-card"></i></span>
                <h3>COMPLETA TUS DATOS PERSONALES</h3>
                <p>Por favor, proporciona la siguiente información para completar tu perfil</p>
            </div>
            <form id="formCompletarDatos" method="POST" action="<?php echo BASE_URL; ?>/Controllers/procesar_cambio_contrasena.php">
                <input type="hidden" name="accion" value="completar_datos">
                <input type="hidden" name="usuario_id" value="<?php echo $_SESSION['usuario_id'] ?? ''; ?>">
                
                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-id-card icon"></i>
                        <select id="tipo_documento" name="tipo_documento" required style="padding-left:44px;">
                            <option value="">SELECCIONA TIPO DE DOCUMENTO</option>
                            <option value="Cédula de Ciudadanía">CÉDULA DE CIUDADANÍA</option>
                            <option value="Cédula de Extranjería">CÉDULA DE EXTRANJERÍA</option>
                            <option value="Pasaporte">PASAPORTE</option>
                            <option value="NIT">NIT</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-user icon"></i>
                        <input type="text" id="apellidos" name="apellidos" placeholder="APELLIDOS COMPLETOS" required autocomplete="off">
                    </div>
                </div>
                
                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-hashtag icon"></i>
                        <input type="text" id="documento" name="documento" placeholder="NÚMERO DE DOCUMENTO" required autocomplete="off" pattern="[0-9]{1,10}" title="SOLO NÚMEROS. MÁXIMO 10 DÍGITOS" maxlength="10" inputmode="numeric">
                    </div>
                </div>
                
                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-phone icon"></i>
                        <input type="tel" id="telefono" name="telefono" placeholder="NÚMERO DE TELÉFONO" required autocomplete="off" pattern="[0-9]{10}" title="SOLO NÚMEROS. 10 DÍGITOS" maxlength="10" inputmode="numeric">
                    </div>
                </div>
                
                <div class="modal-note">
                    <strong>IMPORTANTE:</strong> Estos datos son necesarios para verificar tu identidad y mejorar tu experiencia en la plataforma.
                </div>
                
                <div class="actions" style="margin-top:20px;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> GUARDAR DATOS
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Configuracion Inicial de Empresa -->
    <div id="modalConfigEmpresa" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <span class="modal-icon"><i class="fas fa-building"></i></span>
                <h3>CONFIGURACION INICIAL DE EMPRESA</h3>
                <p>Completa estos datos solo una vez para personalizar tu empresa</p>
            </div>
            <form id="formConfigEmpresa" method="POST" action="<?php echo BASE_URL; ?>/Controllers/procesar_cambio_contrasena.php" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="completar_config_empresa">
                <input type="hidden" name="usuario_id" value="<?php echo $_SESSION['usuario_id'] ?? ''; ?>">
                <input type="hidden" name="empresa_id" value="<?php echo $_SESSION['empresa_id'] ?? ''; ?>">

                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-building icon"></i>
                        <input type="text" id="empresa_nombre" name="empresa_nombre" placeholder="NOMBRE DE LA EMPRESA" required autocomplete="off" maxlength="120">
                    </div>
                </div>

                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-file-invoice icon"></i>
                        <input type="text" id="empresa_nit" name="empresa_nit" placeholder="NIT DE LA EMPRESA" required autocomplete="off" maxlength="40">
                    </div>
                </div>

                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-map-marker-alt icon"></i>
                        <input type="text" id="empresa_direccion" name="empresa_direccion" placeholder="DIRECCION DE LA EMPRESA" required autocomplete="off" maxlength="180">
                    </div>
                </div>

                <div class="modal-form-group">
                    <div class="input-group">
                        <i class="fas fa-image icon"></i>
                        <input type="file" id="empresa_imagen_archivo" name="empresa_imagen_archivo" accept="image/png" autocomplete="off">
                    </div>
                </div>

                <div class="modal-note">
                    <strong>Nota:</strong> El correo y telefono de la empresa se guardan automaticamente con los datos del usuario administrador.
                </div>

                <div class="actions" style="margin-top:20px;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> GUARDAR CONFIGURACION
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mostrar/ocultar contraseña
        document.addEventListener('click', function(e){
            if(e.target && e.target.classList.contains('toggle-password')){
                const target = e.target.getAttribute('data-target');
                const pwd = target ? document.getElementById(target) : document.getElementById('contrasena');
                if(!pwd) return;
                if(pwd.type === 'password'){ 
                    pwd.type = 'text'; 
                    e.target.classList.remove('fa-eye'); 
                    e.target.classList.add('fa-eye-slash'); 
                } else { 
                    pwd.type = 'password'; 
                    e.target.classList.remove('fa-eye-slash'); 
                    e.target.classList.add('fa-eye'); 
                }
            }
        });

        const logoDefaultLogin = <?php echo json_encode($loginLoadingLogo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        let logoLookupTimer = null;
        const logoLookupCache = new Map();
        const logoLookupRequests = new Map();
        const superadminCheckCache = new Map();
        const superadminCheckRequests = new Map();
        const rememberEmailCookieName = 'remember_email';
        const rememberEmailStorageKey = 'remember_email';

        const setCookie = (name, value, maxAgeSeconds) => {
            document.cookie = `${name}=${encodeURIComponent(value || '')};path=/;max-age=${maxAgeSeconds}`;
        };

        const getCookie = (name) => {
            return document.cookie.split('; ').reduce((acc, cookie) => {
                const [key, val] = cookie.split('=');
                if (key === name) {
                    return decodeURIComponent(val || '');
                }
                return acc;
            }, '');
        };

        const setRememberEmail = (email, remember) => {
            if (remember && email) {
                setCookie(rememberEmailCookieName, email, 30 * 24 * 60 * 60);
                try {
                    localStorage.setItem(rememberEmailStorageKey, email);
                } catch (_e) {}
            } else {
                setCookie(rememberEmailCookieName, '', 0);
                try {
                    localStorage.removeItem(rememberEmailStorageKey);
                } catch (_e) {}
            }
        };

        const getRememberEmail = () => {
            const cookieEmail = getCookie(rememberEmailCookieName);
            if (cookieEmail) {
                return cookieEmail;
            }
            try {
                return localStorage.getItem(rememberEmailStorageKey) || '';
            } catch (_e) {
                return '';
            }
        };

        const applyRememberEmail = () => {
            const correoInput = document.getElementById('correo');
            const rememberCheckbox = document.getElementById('remember');
            const remembered = getRememberEmail();
            if (correoInput && rememberCheckbox && remembered) {
                correoInput.value = remembered;
                rememberCheckbox.checked = true;
            }
        };

        const setEmpresaCampo = (visible) => {
            const grupo = document.getElementById('grupoEmpresaDestino');
            const input = document.getElementById('empresa_destino');
            if (!grupo) return;
            if (visible) {
                grupo.style.display = '';
            } else {
                grupo.style.display = 'none';
                if (input) input.value = '';
                ocultarInfoEmpresaDestino();
            }
        };

        const ocultarInfoEmpresaDestino = () => {
            const info = document.getElementById('empresaDestinoInfo');
            const err = document.getElementById('empresaDestinoError');
            if (info) info.style.display = 'none';
            if (err) err.style.display = 'none';
        };

        let empresaDestinoTimer = null;
        const empresaDestinoCache = new Map();

        const buscarEmpresaPorId = async (empresaId, correo) => {
            const id = parseInt(empresaId, 10);
            if (!id || id <= 0) { ocultarInfoEmpresaDestino(); return; }

            const cacheKey = `${id}:${correo}`;
            if (empresaDestinoCache.has(cacheKey)) {
                mostrarResultadoEmpresa(empresaDestinoCache.get(cacheKey));
                return;
            }

            try {
                const params = new URLSearchParams({ action: 'get_empresa_info', empresa_id: id, correo });
                const res = await fetch(`${base_url}/Controllers/LoginController.php?${params}`, {
                    method: 'GET', credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) { ocultarInfoEmpresaDestino(); return; }
                const data = await res.json();
                empresaDestinoCache.set(cacheKey, data);
                mostrarResultadoEmpresa(data);
            } catch (e) { ocultarInfoEmpresaDestino(); }
        };

        const mostrarResultadoEmpresa = (data) => {
            const info = document.getElementById('empresaDestinoInfo');
            const err = document.getElementById('empresaDestinoError');
            const nombre = document.getElementById('empresaDestinoNombre');
            if (!info || !err) return;
            if (data && data.found) {
                if (nombre) nombre.textContent = data.empresa_nombre.toUpperCase() + ' (ID: ' + data.empresa_id + ')';
                info.style.display = 'flex';
                err.style.display = 'none';
                if (data.logo_url) setLoginLoaderLogo(data.logo_url);
            } else {
                info.style.display = 'none';
                err.style.display = 'flex';
            }
        };

        const checkIsSuperAdmin = async (correo) => {
            const correoNormalizado = String(correo || '').trim().toLowerCase();
            const formatoValido = correoNormalizado && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correoNormalizado);

            if (!formatoValido) {
                setEmpresaCampo(false);
                return;
            }

            // Mostrar el campo de forma inmediata (optimista) mientras llega la respuesta AJAX
            setEmpresaCampo(true);

            if (superadminCheckCache.has(correoNormalizado)) {
                setEmpresaCampo(superadminCheckCache.get(correoNormalizado));
                return;
            }

            if (superadminCheckRequests.has(correoNormalizado)) {
                const result = await superadminCheckRequests.get(correoNormalizado);
                setEmpresaCampo(result);
                return;
            }

            const pendingRequest = fetch(
                `${base_url}/Controllers/LoginController.php?action=check_superadmin&correo=${encodeURIComponent(correoNormalizado)}`,
                { method: 'GET', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            )
                .then(async (res) => {
                    if (!res.ok) return false;
                    const data = await res.json();
                    return data?.is_superadmin === true;
                })
                .catch(() => false)
                .finally(() => { superadminCheckRequests.delete(correoNormalizado); });

            superadminCheckRequests.set(correoNormalizado, pendingRequest);
            const esSuperAdmin = await pendingRequest;
            superadminCheckCache.set(correoNormalizado, esSuperAdmin);
            // Ocultar solo si el AJAX confirma que NO es superadmin
            setEmpresaCampo(esSuperAdmin);
        };

        const setLoginLoaderLogo = (src) => {
            const logoFinal = String(src || '').trim() || logoDefaultLogin;
            const logoLoaderImg = document.getElementById('loginLoadingLogoImg');
            const companyLogoImg = document.querySelector('.company-logo');
            if (logoLoaderImg && logoLoaderImg.src !== logoFinal) {
                logoLoaderImg.src = logoFinal;
            }
            if (companyLogoImg && companyLogoImg.src !== logoFinal) {
                companyLogoImg.src = logoFinal;
            }
        };

        const fetchCompanyLogoByEmail = async (correo) => {
            const correoNormalizado = String(correo || '').trim().toLowerCase();
            if (!correoNormalizado || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correoNormalizado)) {
                setLoginLoaderLogo(logoDefaultLogin);
                return logoDefaultLogin;
            }

            if (logoLookupCache.has(correoNormalizado)) {
                const logoCacheado = logoLookupCache.get(correoNormalizado) || logoDefaultLogin;
                setLoginLoaderLogo(logoCacheado);
                return logoCacheado;
            }

            if (logoLookupRequests.has(correoNormalizado)) {
                const logoPendiente = await logoLookupRequests.get(correoNormalizado);
                setLoginLoaderLogo(logoPendiente);
                return logoPendiente;
            }

            try {
                const params = new URLSearchParams({ action: 'logo_por_correo', correo: correoNormalizado });
                const pendingRequest = fetch(`${base_url}/Controllers/LoginController.php?${params.toString()}`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(async (response) => {
                        if (!response.ok) {
                            return logoDefaultLogin;
                        }

                        const data = await response.json();
                        return String(data?.logo_url || '').trim() || logoDefaultLogin;
                    })
                    .catch(() => logoDefaultLogin)
                    .finally(() => {
                        logoLookupRequests.delete(correoNormalizado);
                    });

                logoLookupRequests.set(correoNormalizado, pendingRequest);
                const logoResuelto = await pendingRequest;
                logoLookupCache.set(correoNormalizado, logoResuelto);
                setLoginLoaderLogo(logoResuelto);
                return logoResuelto;
            } catch (error) {
                setLoginLoaderLogo(logoDefaultLogin);
                return logoDefaultLogin;
            }
        };

        // Prefill desde cookie y gestionar comportamiento de login
        document.addEventListener('DOMContentLoaded', function(){
            const correoLoginInput = document.getElementById('correo');

            const scheduleCompanyLogoLookup = (correo) => {
                if (logoLookupTimer) {
                    clearTimeout(logoLookupTimer);
                }

                logoLookupTimer = setTimeout(() => {
                    fetchCompanyLogoByEmail(correo);
                }, 260);
            };

            if (correoLoginInput) {
                correoLoginInput.addEventListener('input', function() {
                    scheduleCompanyLogoLookup(this.value);
                    checkIsSuperAdmin(this.value);
                    // Si el campo empresa_destino tiene valor, re-buscar con nuevo correo
                    const edInput = document.getElementById('empresa_destino');
                    if (edInput && edInput.value) buscarEmpresaPorId(edInput.value, this.value.trim().toLowerCase());
                });

                const rememberCheckbox = document.getElementById('remember');
                if (rememberCheckbox) {
                    rememberCheckbox.addEventListener('change', function() {
                        setRememberEmail(correoLoginInput.value.trim(), this.checked);
                    });
                }

                correoLoginInput.addEventListener('change', function() {
                    scheduleCompanyLogoLookup(this.value);
                    checkIsSuperAdmin(this.value);
                });

                correoLoginInput.addEventListener('blur', function() {
                    fetchCompanyLogoByEmail(this.value);
                    checkIsSuperAdmin(this.value);
                });

                // Disparar inmediatamente si el campo ya tiene valor (autocompletar del navegador)
                const dispararSiPreCargado = () => {
                    if (correoLoginInput.value.trim()) {
                        scheduleCompanyLogoLookup(correoLoginInput.value);
                        checkIsSuperAdmin(correoLoginInput.value);
                    }
                };
                // Intentar varias veces para capturar el autocompletado del navegador
                setTimeout(dispararSiPreCargado, 100);
                setTimeout(dispararSiPreCargado, 500);
                setTimeout(dispararSiPreCargado, 1200);
            }

            // Listener para campo empresa_destino
            const empresaDestinoInput = document.getElementById('empresa_destino');
            if (empresaDestinoInput) {
                empresaDestinoInput.addEventListener('input', function() {
                    clearTimeout(empresaDestinoTimer);
                    const correoVal = correoLoginInput ? correoLoginInput.value.trim().toLowerCase() : '';
                    const idVal = this.value.trim();
                    if (!idVal) { ocultarInfoEmpresaDestino(); return; }
                    empresaDestinoTimer = setTimeout(() => buscarEmpresaPorId(idVal, correoVal), 400);
                });
                empresaDestinoInput.addEventListener('blur', function() {
                    const correoVal = correoLoginInput ? correoLoginInput.value.trim().toLowerCase() : '';
                    if (this.value.trim()) buscarEmpresaPorId(this.value.trim(), correoVal);
                });
            }

            // Verificar si debe mostrar modal de cambio de contraseña
            const urlParams = new URLSearchParams(window.location.search);
            // Verificar si debe mostrar modal de cambio de contraseña
            if(urlParams.get('cambiar_pass') === '1'){
                document.getElementById('modalCambioPass').classList.add('active');
            }
            
            // Verificar si debe mostrar modal de completar datos
            if(urlParams.get('completar_datos') === '1'){
                document.getElementById('modalCompletarDatos').classList.add('active');
            }

            // Verificar si debe mostrar modal de configuracion inicial de empresa
            if(urlParams.get('config_empresa') === '1'){
                document.getElementById('modalConfigEmpresa').classList.add('active');
            }
            
            const prefilledLoginEmail = <?php echo json_encode($correoPrefillLogin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            // Cookie de recordar correo
            const correoInput = document.getElementById('correo');
            const rememberCheckbox = document.getElementById('remember');
            if (prefilledLoginEmail) {
                correoInput.value = prefilledLoginEmail;
                fetchCompanyLogoByEmail(prefilledLoginEmail);
                if (rememberCheckbox) {
                    rememberCheckbox.checked = true;
                    setRememberEmail(prefilledLoginEmail, true);
                }
            } else {
                applyRememberEmail();
                const remembered = getRememberEmail();
                if (remembered) {
                    fetchCompanyLogoByEmail(remembered);
                }
            }
            
            // Validación de fortaleza de contraseña
            const passwordNueva = document.getElementById('password_nueva');
            if(passwordNueva){
                passwordNueva.addEventListener('input', function(){
                    const strength = document.getElementById('passwordStrength');
                    const password = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                    if (this.value !== password) {
                        this.value = password;
                    }
                    
                    if(password.length === 0){
                        strength.style.display = 'none';
                        return;
                    }
                    
                    strength.style.display = 'inline-block';

                    strength.classList.remove('weak', 'medium', 'strong');
                    if(password.length < 6){
                        strength.textContent = 'DIFICULTAD: EN PROCESO (' + password.length + '/6)';
                        strength.classList.add(password.length >= 4 ? 'medium' : 'weak');
                    } else {
                        strength.textContent = 'DIFICULTAD: COMPLETA (6/6)';
                        strength.classList.add('strong');
                    }
                });
            }

            const passwordActual = document.getElementById('password_actual');
            if (passwordActual) {
                passwordActual.addEventListener('input', function() {
                    const limpio = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                    if (this.value !== limpio) {
                        this.value = limpio;
                    }
                });
            }

            const passwordConfirmar = document.getElementById('password_confirmar');
            if (passwordConfirmar) {
                passwordConfirmar.addEventListener('input', function() {
                    const limpio = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                    if (this.value !== limpio) {
                        this.value = limpio;
                    }
                });
            }

            const documentoInput = document.getElementById('documento');
            if (documentoInput) {
                documentoInput.addEventListener('input', function() {
                    const limpio = this.value.replace(/[^0-9]/g, '').slice(0, 10);
                    if (this.value !== limpio) {
                        this.value = limpio;
                    }
                });
            }

            const telefonoInput = document.getElementById('telefono');
            if (telefonoInput) {
                telefonoInput.addEventListener('input', function() {
                    const limpio = this.value.replace(/[^0-9]/g, '').slice(0, 10);
                    if (this.value !== limpio) {
                        this.value = limpio;
                    }
                });
            }
        });

        const contrasenaInput = document.getElementById('contrasena');
        if (contrasenaInput) {
            const sanitizarContrasena = function() {
                const limpio = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                if (this.value !== limpio) {
                    this.value = limpio;
                }
            };
            contrasenaInput.addEventListener('input', sanitizarContrasena);
            contrasenaInput.addEventListener('paste', function(evt) {
                const clipboardData = (evt.clipboardData || window.clipboardData);
                const pasted = clipboardData ? clipboardData.getData('text') : '';
                if (!/^[0-9]*$/.test(pasted)) {
                    evt.preventDefault();
                    const limpio = pasted.replace(/[^0-9]/g, '').slice(0, 6);
                    const current = this.value || '';
                    this.value = (current + limpio).slice(0, 6);
                }
            });
        }

        document.getElementById('loginForm').addEventListener('submit', async function(evt){
            if (this.dataset.submitting === '1') {
                return;
            }

            const correo = document.getElementById('correo').value.trim();
            const pass = document.getElementById('contrasena').value.trim();
            if(!correo || !pass){
                evt.preventDefault();
                Swal.fire({icon:'warning',title:'CAMPOS REQUERIDOS',text:'COMPLETA CORREO Y CONTRASEÑA',confirmButtonColor:'#2f4a5a'});
                return;
            }

            if(!/^[0-9]{6}$/.test(pass)){
                evt.preventDefault();
                Swal.fire({icon:'warning',title:'CONTRASEÑA INVÁLIDA',text:'LA CONTRASEÑA DEBE SER NUMÉRICA Y TENER EXACTAMENTE 6 DÍGITOS',confirmButtonColor:'#2f4a5a'});
                return;
            }

            evt.preventDefault();
            // antes de enviar: crear o borrar cookie según checkbox
            const remember = document.getElementById('remember').checked;
            setRememberEmail(correo, remember);

            const logoLoaderImg = document.getElementById('loginLoadingLogoImg');
            const logoDetectado = await fetchCompanyLogoByEmail(correo);
            if (logoLoaderImg) {
                logoLoaderImg.setAttribute('src', logoDetectado || <?php echo json_encode($loginLoadingLogo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>);
            }

            if (typeof window.showLoading === 'function') {
                window.showLoading();
            }

            this.dataset.submitting = '1';
            HTMLFormElement.prototype.submit.call(this);
        });
        
        // Validación formulario de cambio de contraseña
        const formCambioPass = document.getElementById('formCambioPassword');
        if(formCambioPass){
            formCambioPass.addEventListener('submit', function(evt){
                const passActual = document.getElementById('password_actual').value;
                const passNueva = document.getElementById('password_nueva').value;
                const passConfirmar = document.getElementById('password_confirmar').value;
                
                if(!passActual || !passNueva || !passConfirmar){
                    evt.preventDefault();
                    Swal.fire({icon:'warning',title:'CAMPOS REQUERIDOS',text:'COMPLETA TODOS LOS CAMPOS',confirmButtonColor:'#2f4a5a'});
                    return;
                }
                
                if(!/^[0-9]{6}$/.test(passNueva)){
                    evt.preventDefault();
                    Swal.fire({icon:'warning',title:'CONTRASEÑA INVÁLIDA',text:'LA CONTRASEÑA DEBE TENER EXACTAMENTE 6 DIGITOS',confirmButtonColor:'#2f4a5a'});
                    return;
                }

                if(!/^[0-9]{6}$/.test(passConfirmar)){
                    evt.preventDefault();
                    Swal.fire({icon:'warning',title:'CONFIRMACIÓN INVÁLIDA',text:'LA CONFIRMACIÓN DEBE TENER EXACTAMENTE 6 DIGITOS',confirmButtonColor:'#2f4a5a'});
                    return;
                }
                
                if(passNueva !== passConfirmar){
                    evt.preventDefault();
                    Swal.fire({icon:'warning',title:'CONTRASEÑAS NO COINCIDEN',text:'LAS CONTRASEÑAS NUEVAS NO SON IGUALES',confirmButtonColor:'#2f4a5a'});
                    return;
                }
            });
        }
        
        // Validación formulario de completar datos
        const formCompletarDatos = document.getElementById('formCompletarDatos');
        if(formCompletarDatos){
            formCompletarDatos.addEventListener('submit', function(evt){
                if (this.dataset.submitting === '1') {
                    return;
                }

                const apellidos = document.getElementById('apellidos').value.trim();
                const tipoDoc = document.getElementById('tipo_documento').value;
                const documento = document.getElementById('documento').value.replace(/[^0-9]/g, '').slice(0, 10);
                const telefono = document.getElementById('telefono').value.replace(/[^0-9]/g, '').slice(0, 10);

                document.getElementById('documento').value = documento;
                document.getElementById('telefono').value = telefono;
                
                if(!apellidos || !tipoDoc || !documento || !telefono){
                    evt.preventDefault();
                    Swal.fire({icon:'warning',title:'CAMPOS REQUERIDOS',text:'COMPLETA TODOS LOS CAMPOS',confirmButtonColor:'#2f4a5a'});
                    return;
                }
                
                if(!/^[0-9]{1,10}$/.test(documento)){
                    evt.preventDefault();
                    Swal.fire({icon:'warning',title:'DOCUMENTO INVÁLIDO',text:'EL DOCUMENTO SOLO DEBE CONTENER NÚMEROS Y TENER MÁXIMO 10 DÍGITOS',confirmButtonColor:'#2f4a5a'});
                    return;
                }
                
                if(!/^[0-9]{10}$/.test(telefono)){
                    evt.preventDefault();
                    Swal.fire({icon:'warning',title:'TELÉFONO INVÁLIDO',text:'EL TELÉFONO DEBE TENER 10 DÍGITOS',confirmButtonColor:'#2f4a5a'});
                    return;
                }

                this.dataset.submitting = '1';
                if (typeof window.showLoading === 'function') {
                    window.showLoading();
                }
            });
        }

        // Validacion formulario de configuracion inicial de empresa
        const formConfigEmpresa = document.getElementById('formConfigEmpresa');
        if(formConfigEmpresa){
            formConfigEmpresa.addEventListener('submit', function(evt){
                const nombreEmpresa = document.getElementById('empresa_nombre').value.trim();
                const nitEmpresa = document.getElementById('empresa_nit').value.trim();
                const direccionEmpresa = document.getElementById('empresa_direccion').value.trim();

                if(!nombreEmpresa || !nitEmpresa || !direccionEmpresa){
                    evt.preventDefault();
                    Swal.fire({icon:'warning',title:'CAMPOS REQUERIDOS',text:'COMPLETA NOMBRE, NIT Y DIRECCION',confirmButtonColor:'#2f4a5a'});
                    return;
                }
            });
        }
     </script>
</body>
</html>
