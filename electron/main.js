const { app, BrowserWindow, dialog, ipcMain, shell } = require('electron');
const { spawn, spawnSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const net = require('net');
const http = require('http');
const os = require('os');

const APP_NAME = 'AUTOSERVICIO MI ESTRELLA';
const COMPANY_NAME = 'AUTOSERVICIO MI ESTRELLA';
const SERVER_HOST = '127.0.0.1';
const LAN_BIND_HOST = '0.0.0.0';
const LOCAL_CLIENT_PORT = 8000;
const PORTABLE_CLIENT_PORT = 8001;
const STOPPED_SERVER_LOCAL_PORT = 8002;
const DEFAULT_SERVER_PORT = 8000;
const PROJECT_ROOT = path.resolve(__dirname, '..');
const COMPANY_LOGO_ICON_PATH = fs.existsSync(path.join(PROJECT_ROOT, 'logo.ico'))
  ? path.join(PROJECT_ROOT, 'logo.ico')
  : fs.existsSync(path.join(PROJECT_ROOT, 'Assets', 'images', 'Empresas', 'mi_estrella_solo_imprimir.png'))
    ? path.join(PROJECT_ROOT, 'Assets', 'images', 'Empresas', 'mi_estrella_solo_imprimir.png')
    : path.join(PROJECT_ROOT, 'favicon.ico');
const ICON_PATH = COMPANY_LOGO_ICON_PATH;
const FALLBACK_ICON_PATH = path.join(PROJECT_ROOT, 'logo.ico');

function findPhpExecutable(rootDir) {
  if (!fs.existsSync(rootDir)) {
    return null;
  }

  const directExecutable = path.join(rootDir, process.platform === 'win32' ? 'php.exe' : 'php');
  if (fs.existsSync(directExecutable) && fs.statSync(directExecutable).isFile()) {
    return directExecutable;
  }

  const entries = fs.readdirSync(rootDir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(rootDir, entry.name);
    if (entry.isFile() && entry.name.toLowerCase() === (process.platform === 'win32' ? 'php.exe' : 'php')) {
      return fullPath;
    }
    if (entry.isDirectory()) {
      const result = findPhpExecutable(fullPath);
      if (result) {
        return result;
      }
    }
  }

  return null;
}

function isPortableBuild() {
  const executableName = path.basename(process.execPath || '');
  const portableExecutable = String(process.env.PORTABLE_EXECUTABLE_FILE || '').toLowerCase();
  const portableDirectory = String(process.env.PORTABLE_EXECUTABLE_DIR || '').toLowerCase();
  const resourcesPath = String(process.resourcesPath || '').toLowerCase();
  return app.isPackaged && (
    /portable/i.test(executableName)
    || /portable/i.test(portableExecutable)
    || /portable/i.test(portableDirectory)
    || /[\\/]dist[\\/]portable(?:[\\/]|$)/i.test(resourcesPath)
  );
}

// Identidad por versión: la instalada y la portable deben verse como apps distintas
// para Windows (ID), para el bloqueo de instancia única y para su carpeta de datos.
const IS_PORTABLE_BUILD = isPortableBuild();
const APP_ID = IS_PORTABLE_BUILD
  ? 'com.autoservicio.laestrella.desktop.portable'
  : 'com.autoservicio.miestrella.desktop';
const APP_DISPLAY_NAME = IS_PORTABLE_BUILD ? 'AUTOSERVICIO MI ESTRELLA PORTABLE' : APP_NAME;
app.setName(APP_DISPLAY_NAME);

function getAppStartPath() {
  return IS_PORTABLE_BUILD ? '/Views/conexion.php' : '/Views/login.php';
}

function getBundledPhpPath() {
  const phpRoots = [
    path.join(PROJECT_ROOT, 'php'),
    path.join(process.resourcesPath, 'php'),
    path.join(process.resourcesPath, 'app', 'php')
  ];

  for (const phpRoot of phpRoots) {
    const result = findPhpExecutable(phpRoot);
    if (result) {
      return result;
    }
  }

  return null;
}

function getConnectionConfigPath() {
  return path.join(app.getPath('userData'), 'connection-config.json');
}

function readConnectionConfig() {
  const defaults = {
    mode: 'unconfigured',
    server_ip: '',
    server_port: DEFAULT_SERVER_PORT,
    lan_ip: ''
  };
  const configPath = getConnectionConfigPath();
  if (!fs.existsSync(configPath)) return defaults;
  try {
    const parsed = JSON.parse(fs.readFileSync(configPath, 'utf8'));
    const port = Number(parsed.server_port);
    return {
      mode: ['unconfigured', 'server', 'client', 'stopped'].includes(parsed.mode) ? parsed.mode : defaults.mode,
      server_ip: String(parsed.server_ip || '').trim(),
      server_port: Number.isInteger(port) && port >= 1 && port <= 65535 ? port : defaults.server_port,
      lan_ip: String(parsed.lan_ip || '').trim()
    };
  } catch (error) {
    console.warn('No se pudo leer la configuración cliente-servidor:', error.message);
    return defaults;
  }
}

function writeConnectionConfig(config) {
  const port = Number(config.server_port);
  const normalized = {
    mode: ['unconfigured', 'server', 'client', 'stopped'].includes(config.mode) ? config.mode : 'unconfigured',
    server_ip: String(config.server_ip || '').trim(),
    server_port: Number.isInteger(port) && port >= 1 && port <= 65535 ? port : DEFAULT_SERVER_PORT,
    lan_ip: String(config.lan_ip || '').trim()
  };
  const configPath = getConnectionConfigPath();
  fs.mkdirSync(path.dirname(configPath), { recursive: true });
  fs.writeFileSync(configPath, `${JSON.stringify(normalized, null, 2)}\n`, 'utf8');
  connectionConfig = normalized;
  return normalized;
}

let phpProcess = null;
let mainWindow = null;
let activePort = LOCAL_CLIENT_PORT;
let activeBindHost = SERVER_HOST;
let connectionConfig = {
  mode: 'unconfigured',
  server_ip: '',
  server_port: DEFAULT_SERVER_PORT,
  lan_ip: ''
};
// ---------------------------------------------------------------------------
// Báscula ACS-30 (RS-232 -> CH340 -> COM): un único servicio en el proceso
// principal. Ni las vistas ni ningún puente HTTP abren el puerto.
// ---------------------------------------------------------------------------
const { BasculaService } = require('./bascula/BasculaService');

const puertoBasculaForzado = (() => {
  const argumento = process.argv.find((arg) => /^--bascula-puerto=/i.test(arg));
  if (argumento) return argumento.split('=')[1];
  return process.env.BASCULA_PUERTO || null;
})();

const basculaService = new BasculaService({ puertoForzado: puertoBasculaForzado });
let ventanaDiagnosticoBascula = null;

function enviarABascula(canal, payload) {
  const ventanas = [mainWindow, ventanaDiagnosticoBascula].filter((v) => v && !v.isDestroyed());
  for (const ventana of ventanas) {
    try {
      ventana.webContents.send(canal, payload);
      for (const frame of ventana.webContents.mainFrame.framesInSubtree()) {
        if (frame === ventana.webContents.mainFrame) continue;
        ventana.webContents.sendToFrame(frame.frameId, canal, payload);
      }
    } catch (error) {
      // Una ventana cerrándose no debe interrumpir la lectura de la báscula.
    }
  }
}

basculaService.on('peso', (peso) => enviarABascula('bascula:peso', peso));
basculaService.on('estado', (estado) => enviarABascula('bascula:estado-cambio', estado));
basculaService.on('trama', (trama) => enviarABascula('bascula:trama', trama));

function abrirVentanaDiagnosticoBascula() {
  if (ventanaDiagnosticoBascula && !ventanaDiagnosticoBascula.isDestroyed()) {
    ventanaDiagnosticoBascula.focus();
    return ventanaDiagnosticoBascula;
  }
  ventanaDiagnosticoBascula = new BrowserWindow({
    width: 900,
    height: 760,
    title: 'Diagnóstico de báscula ACS-30',
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false
    }
  });
  ventanaDiagnosticoBascula.loadFile(path.join(__dirname, 'bascula', 'diagnostico.html'));
  ventanaDiagnosticoBascula.on('closed', () => {
    ventanaDiagnosticoBascula = null;
  });
  return ventanaDiagnosticoBascula;
}

function locatePhpExecutable() {
  const bundledPhp = getBundledPhpPath();
  if (bundledPhp && fs.existsSync(bundledPhp)) {
    return bundledPhp;
  }

  const result = spawnSync('php', ['-v'], { stdio: 'ignore' });
  if (result.status === 0) {
    return 'php';
  }

  return null;
}

function isPortFree(port, host) {
  return new Promise((resolve) => {
    const server = net.createServer()
      .once('error', (err) => {
        server.close();
        resolve(err.code !== 'EADDRINUSE');
      })
      .once('listening', () => {
        server.close(() => resolve(true));
      })
      .listen(port, host);
  });
}

function waitForServerReady(port, timeout = 15000, bindHost = SERVER_HOST) {
  const probeHost = bindHost === LAN_BIND_HOST ? SERVER_HOST : bindHost;
  const serverUrl = `http://${probeHost}:${port}`;
  const startTime = Date.now();

  return new Promise((resolve, reject) => {
    const check = () => {
      const socket = net.createConnection(port, probeHost);
      socket.on('connect', () => {
        socket.destroy();
        resolve(serverUrl);
      });
      socket.on('error', () => {
        socket.destroy();
        if (Date.now() - startTime > timeout) {
          reject(new Error('El servidor PHP no respondió en el tiempo esperado.'));
        } else {
          setTimeout(check, 250);
        }
      });
    };
    check();
  });
}

function getWritableDatabasePath() {
  const userDataPath = app.getPath('userData');
  const dbDir = path.join(userDataPath, 'database');
  const writableDbPath = path.join(dbDir, 'database.db');
  const seedMarkerPath = path.join(dbDir, '.seed-fingerprint');
  const bundledCandidates = [
    path.join(PROJECT_ROOT, 'database', 'database.db'),
    path.join(process.resourcesPath, 'database', 'database.db')
  ];

  try {
    fs.mkdirSync(dbDir, { recursive: true });
  } catch (error) {
    console.error('No se pudo crear el directorio SQLite en userData:', dbDir, error);
  }

  const sourceDbPath = bundledCandidates.find((candidate) => fs.existsSync(candidate));
  const sourceFingerprint = sourceDbPath
    ? (() => {
        const sourceStats = fs.statSync(sourceDbPath);
        return `${sourceStats.size}:${sourceStats.mtimeMs}`;
      })()
    : null;
  const previousFingerprint = fs.existsSync(seedMarkerPath)
    ? fs.readFileSync(seedMarkerPath, 'utf8').trim()
    : '';
  const mustCopySeed = sourceDbPath && (!fs.existsSync(writableDbPath)
    || (app.isPackaged && previousFingerprint !== sourceFingerprint));

  if (mustCopySeed) {
    try {
      fs.copyFileSync(sourceDbPath, writableDbPath);
      if (sourceFingerprint) {
        fs.writeFileSync(seedMarkerPath, sourceFingerprint, 'utf8');
      }
    } catch (error) {
      console.error('No se pudo copiar la base de datos desde el bundle a userData:', error, {
        sourceDbPath,
        writableDbPath
      });
    }
  } else if (!fs.existsSync(writableDbPath)) {
    try {
      fs.closeSync(fs.openSync(writableDbPath, 'a'));
    } catch (error) {
      console.error('No se pudo crear el archivo SQLite vacío en userData:', error, {
        writableDbPath
      });
    }
  }

  return writableDbPath;
}

function buildPhpEnvironment(port) {
  const sqlitePath = getWritableDatabasePath();
  return {
    ...process.env,
    APP_ENV: app.isPackaged ? 'production' : 'development',
    APP_BASE_URL: `http://${SERVER_HOST}:${port}`,
    APP_PORT: String(port),
    APP_MENU_MODE: isPortableBuild() ? 'portable' : 'full',
    CONNECTION_CONFIG_PATH: getConnectionConfigPath(),
    DB_CONNECTION: process.env.DB_CONNECTION || 'sqlite',
    SQLITE_PATH: sqlitePath,
    DB_CHARSET: 'utf8mb4',
    // Permite que el servidor PHP integrado atienda varias cajas a la vez.
    PHP_CLI_SERVER_WORKERS: process.env.PHP_CLI_SERVER_WORKERS || '6'
  };
}

function isBundledPhpExecutable(phpExecutable) {
  const bundledRoot = app.isPackaged
    ? path.join(process.resourcesPath, 'php')
    : path.join(PROJECT_ROOT, 'php');

  return phpExecutable && phpExecutable.startsWith(bundledRoot);
}

function startPhpServer(port, bindHost) {
  return new Promise((resolve, reject) => {
    const phpExecutable = locatePhpExecutable();
    if (!phpExecutable) {
      reject(new Error('No se detectó PHP en PATH y tampoco se encontró php/php.exe.'));
      return;
    }

    const env = buildPhpEnvironment(port);
    const phpArgs = [];
    if (isBundledPhpExecutable(phpExecutable)) {
      const iniPath = path.join(path.dirname(phpExecutable), 'php.ini-production');
      if (fs.existsSync(iniPath)) {
        phpArgs.push('-c', iniPath);
      }
      phpArgs.push('-d', `extension_dir=${path.join(path.dirname(phpExecutable), 'ext')}`);
    }
    phpArgs.push('-S', `${bindHost}:${port}`, '-t', PROJECT_ROOT);

    phpProcess = spawn(phpExecutable, phpArgs, {
      cwd: PROJECT_ROOT,
      env,
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe']
    });
    const launchedProcess = phpProcess;

    phpProcess.on('error', (error) => {
      reject(new Error(`Error al iniciar PHP: ${error.message}`));
    });

    phpProcess.stderr.on('data', (data) => {
      const message = data.toString();
      if (/address already in use/i.test(message)) {
        reject(new Error(`El puerto ${port} está en uso.`));
      }
    });

    phpProcess.on('exit', (code, signal) => {
      if (phpProcess === launchedProcess) phpProcess = null;
      if (code !== 0 && signal !== 'SIGTERM') {
        console.error(`PHP terminó con código ${code} y señal ${signal}`);
      }
    });

    waitForServerReady(port, 15000, bindHost)
      .then(resolve)
      .catch(reject);
  });
}

function stopPhpServer() {
  if (phpProcess && !phpProcess.killed) {
    phpProcess.kill();
    phpProcess = null;
  }
}

function stopPhpServerAndWait(timeout = 5000) {
  const processToStop = phpProcess;
  phpProcess = null;
  if (!processToStop || processToStop.killed) return Promise.resolve();

  return new Promise((resolve) => {
    let finished = false;
    let forceTimer = null;
    const finish = () => {
      if (finished) return;
      finished = true;
      if (forceTimer) clearTimeout(forceTimer);
      resolve();
    };
    processToStop.once('exit', finish);
    processToStop.kill();
    forceTimer = setTimeout(() => {
      if (process.platform === 'win32' && processToStop.pid) {
        spawnSync('taskkill', ['/PID', String(processToStop.pid), '/T', '/F'], {
          windowsHide: true,
          stdio: 'ignore'
        });
      }
      finish();
    }, timeout);
  });
}

function eliminarReglaFirewall(port) {
  if (process.platform !== 'win32') {
    return { intentado: true, ok: true, mensaje: 'Firewall no aplica en este sistema.' };
  }

  const nombreRegla = `AUTOSERVICIO MI ESTRELLA ${port}`;
  try {
    const argumentosNetsh = [
      'advfirewall', 'firewall', 'delete', 'rule',
      `name=${nombreRegla}`,
      'protocol=TCP',
      `localport=${port}`
    ];
    const argumentosElevados = argumentosNetsh.map((argumento) => (
      String(argumento).startsWith('name=') ? `"${argumento}"` : argumento
    ));
    const argumentosPowerShell = argumentosElevados
      .map((argumento) => `'${String(argumento).replace(/'/g, "''")}'`)
      .join(',');
    const comandoElevado = `$proceso = Start-Process -FilePath 'netsh.exe' -ArgumentList @(${argumentosPowerShell}) -Verb RunAs -WindowStyle Hidden -Wait -PassThru; exit $proceso.ExitCode`;
    const eliminacion = spawnSync('powershell.exe', [
      '-NoProfile',
      '-NonInteractive',
      '-WindowStyle', 'Hidden',
      '-Command', comandoElevado
    ], { windowsHide: true, encoding: 'utf8', timeout: 120000 });

    if (eliminacion.status === 0) {
      return { intentado: true, ok: true, mensaje: `Permiso de red retirado para el puerto ${port}.` };
    }
    return {
      intentado: true,
      ok: false,
      mensaje: eliminacion.error?.code === 'ETIMEDOUT'
        ? 'Windows no respondió a tiempo al retirar el permiso de red.'
        : 'El servidor se apagó, pero Windows no permitió retirar la regla de red.'
    };
  } catch (error) {
    return { intentado: true, ok: false, mensaje: `No se pudo retirar el permiso de red: ${error.message}` };
  }
}

function createMainWindow(serverUrl) {
  const appIconPath = fs.existsSync(ICON_PATH)
    ? ICON_PATH
    : (fs.existsSync(FALLBACK_ICON_PATH) ? FALLBACK_ICON_PATH : undefined);

  mainWindow = new BrowserWindow({
    title: APP_DISPLAY_NAME,
    icon: appIconPath,
    show: false,
    backgroundColor: '#F7F7F7',
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      // El panel carga las vistas dentro de un marco interno (moduleFrame).
      // Sin esto, el puente de escritorio no llega a la pantalla de Conexión.
      nodeIntegrationInSubFrames: true,
      enableRemoteModule: false,
      sandbox: false
    }
  });

  // La báscula la administra exclusivamente el proceso principal (BasculaService).
  // El frontend no abre puertos seriales: Web Serial queda deshabilitado a propósito.
  mainWindow.webContents.session.setPermissionCheckHandler((_webContents, permission) => permission !== 'serial');
  mainWindow.webContents.session.setPermissionRequestHandler((_webContents, permission, callback) => {
    callback(permission !== 'serial');
  });

  // Atajo para abrir el diagnóstico de báscula sin ensuciar la interfaz de ventas.
  mainWindow.webContents.on('before-input-event', (_event, input) => {
    if (input.type === 'keyDown' && input.key === 'F9') {
      abrirVentanaDiagnosticoBascula();
    }
  });

  mainWindow.setMenuBarVisibility(false);

  // Diagnóstico de recargas: deja constancia de cada navegación de la ventana.
  const registrarNavegacion = (evento, detalle = '') => {
    try {
      const linea = `[${new Date().toISOString()}] ${evento} ${detalle}\n`;
      fs.appendFileSync(path.join(app.getPath('userData'), 'navegacion.log'), linea, 'utf8');
    } catch (error) {
      // El log es informativo: nunca debe interrumpir la aplicación.
    }
  };

  mainWindow.webContents.on('did-start-navigation', (_e, url, _isInPlace, isMainFrame) => {
    if (isMainFrame) registrarNavegacion('did-start-navigation', url);
  });
  mainWindow.webContents.on('did-navigate', (_e, url) => registrarNavegacion('did-navigate', url));
  mainWindow.webContents.on('did-fail-load', (_e, code, description, url, isMainFrame) => {
    if (isMainFrame) registrarNavegacion('did-fail-load', `${code} ${description} ${url}`);
  });
  mainWindow.webContents.on('render-process-gone', (_e, details) => {
    registrarNavegacion('render-process-gone', JSON.stringify(details || {}));
  });
  mainWindow.webContents.on('unresponsive', () => registrarNavegacion('unresponsive', ''));

  mainWindow.loadURL(`${serverUrl}${getAppStartPath()}`);

  mainWindow.once('ready-to-show', () => {
    mainWindow.maximize();
    mainWindow.show();
  });

  mainWindow.on('close', () => {
    stopPhpServer();
  });

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    const normalizedUrl = String(url || '').trim().toLowerCase();
    if (
      normalizedUrl === '' ||
      normalizedUrl === 'about:blank' ||
      normalizedUrl.startsWith('data:') ||
      normalizedUrl.startsWith('blob:') ||
      normalizedUrl.startsWith(serverUrl)
    ) {
      return { action: 'allow' };
    }
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.webContents.on('will-navigate', (event, navigationUrl) => {
    const normalizedNav = String(navigationUrl || '').trim().toLowerCase();

    if (
      normalizedNav === '' ||
      normalizedNav === 'about:blank' ||
      normalizedNav.startsWith('data:') ||
      normalizedNav.startsWith('blob:')
    ) {
      return;
    }

    if (/^https?:\/\//i.test(navigationUrl)) {
      return;
    }

    event.preventDefault();
    shell.openExternal(navigationUrl);
  });
}

function resolveLocalClientPort() {
  const argumento = process.argv.find((arg) => /^--puerto-local=/i.test(arg));
  if (argumento) {
    const valor = Number(argumento.split('=')[1]);
    if (Number.isInteger(valor) && valor >= 1 && valor <= 65535) {
      return valor;
    }
  }
  return IS_PORTABLE_BUILD ? PORTABLE_CLIENT_PORT : LOCAL_CLIENT_PORT;
}

// En modo normal (no principal), si el puerto está ocupado se busca el siguiente
// libre en vez de cerrarse: así la instalada y la portable conviven sin choque.
async function encontrarPuertoLibre(puertoBase, host) {
  for (let p = puertoBase; p < puertoBase + 20; p++) {
    if (await isPortFree(p, host)) return p;
  }
  throw new Error(`No hay puertos libres entre ${puertoBase} y ${puertoBase + 19}.`);
}

function resolverPuertoLocalTrasApagado() {
  if (IS_PORTABLE_BUILD) return PORTABLE_CLIENT_PORT;
  return connectionConfig.mode === 'stopped' ? STOPPED_SERVER_LOCAL_PORT : resolveLocalClientPort();
}

let ultimoEstadoFirewall = { intentado: false, ok: false, mensaje: '' };

async function asegurarReglaFirewall(port) {
  if (process.platform !== 'win32') {
    ultimoEstadoFirewall = { intentado: true, ok: true, mensaje: 'Firewall no aplica en este sistema.' };
    return ultimoEstadoFirewall;
  }

  const nombreRegla = `AUTOSERVICIO MI ESTRELLA ${port}`;
  try {
    const existente = spawnSync('netsh', ['advfirewall', 'firewall', 'show', 'rule', `name=${nombreRegla}`], {
      windowsHide: true,
      encoding: 'utf8'
    });
    if (existente.status === 0 && new RegExp(`(^|\\D)${port}(\\D|$)`).test(existente.stdout || '')) {
      ultimoEstadoFirewall = { intentado: true, ok: true, mensaje: `Regla de firewall ya existente para el puerto ${port}.` };
      return ultimoEstadoFirewall;
    }

    const argumentosNetsh = [
      'advfirewall', 'firewall', 'add', 'rule',
      `name=${nombreRegla}`,
      'dir=in',
      'action=allow',
      'protocol=TCP',
      `localport=${port}`,
      'profile=any'
    ];
    const argumentosElevados = argumentosNetsh.map((argumento) => (
      String(argumento).startsWith('name=') ? `"${argumento}"` : argumento
    ));
    const argumentosPowerShell = argumentosElevados
      .map((argumento) => `'${String(argumento).replace(/'/g, "''")}'`)
      .join(',');
    const comandoElevado = `$proceso = Start-Process -FilePath 'netsh.exe' -ArgumentList @(${argumentosPowerShell}) -Verb RunAs -WindowStyle Hidden -Wait -PassThru; exit $proceso.ExitCode`;
    const creacion = spawnSync('powershell.exe', [
      '-NoProfile',
      '-NonInteractive',
      '-WindowStyle', 'Hidden',
      '-Command', comandoElevado
    ], { windowsHide: true, encoding: 'utf8', timeout: 120000 });

    if (creacion.status === 0) {
      ultimoEstadoFirewall = { intentado: true, ok: true, mensaje: `Permiso de red creado para el puerto ${port}.` };
    } else {
      ultimoEstadoFirewall = {
        intentado: true,
        ok: false,
        mensaje: creacion.error?.code === 'ETIMEDOUT'
          ? 'Windows no respondió a tiempo al solicitar el permiso de red. Inténtalo nuevamente.'
          : 'No se concedió el permiso de red de Windows. Pulsa Activar nuevamente y acepta el aviso de administrador.'
      };
    }
  } catch (error) {
    ultimoEstadoFirewall = {
      intentado: true,
      ok: false,
      mensaje: `No se pudo comprobar el firewall: ${error.message}`
    };
  }

  return ultimoEstadoFirewall;
}

function showFatalError(message, detail) {
  dialog.showMessageBoxSync({
    type: 'error',
    title: `${APP_NAME} - Error`,
    message,
    detail,
    buttons: ['Aceptar']
  });
}

app.setAppUserModelId(APP_ID);

// Permite abrir una segunda copia en el mismo equipo para pruebas:
// AUTOSERVICIO.exe --user-data-dir="C:\temp\caja2" --instancia=caja2
const permitirSegundaInstancia = process.argv.some((arg) => /^--(user-data-dir|instancia|instance)\b/i.test(arg));

if (!permitirSegundaInstancia && !app.requestSingleInstanceLock()) {
  app.quit();
  process.exit(0);
}

app.on('second-instance', () => {
  if (mainWindow) {
    if (mainWindow.isMinimized()) {
      mainWindow.restore();
    }
    mainWindow.focus();
  }
});

app.whenReady().then(async () => {
  try {
    connectionConfig = readConnectionConfig();
    const serverMode = connectionConfig.mode === 'server';
    activeBindHost = serverMode ? LAN_BIND_HOST : SERVER_HOST;
    if (serverMode) {
      activePort = connectionConfig.server_port;
      if (!await isPortFree(activePort, activeBindHost)) {
        throw new Error(`El puerto ${activePort} está ocupado para el modo servidor LAN.`);
      }
    } else {
      activePort = await encontrarPuertoLibre(resolverPuertoLocalTrasApagado(), SERVER_HOST);
    }
    if (serverMode) {
      await asegurarReglaFirewall(activePort);
    }
    const serverUrl = `http://${SERVER_HOST}:${activePort}`;
    await startPhpServer(activePort, activeBindHost);
    createMainWindow(serverUrl);
    // La báscula se conecta sola al arrancar y se mantiene abierta toda la sesión.
    basculaService.iniciar();
  } catch (error) {
    showFatalError('No se pudo iniciar la aplicación.', error.message);
    app.quit();
  }
});

app.on('before-quit', () => {
  basculaService.detener().catch(() => {});
  stopPhpServer();
});

app.on('window-all-closed', () => {
  stopPhpServer();
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    const serverUrl = `http://${SERVER_HOST}:${activePort}`;
    createMainWindow(serverUrl);
  }
});

// ---------------------------------------------------------------------------
// Báscula ACS-30: canales IPC (única vía de comunicación con el frontend)
// ---------------------------------------------------------------------------
ipcMain.handle('bascula:estado', async () => basculaService.estado());

ipcMain.handle('bascula:diagnostico', async () => {
  await basculaService.listarPuertos();
  return basculaService.diagnostico();
});

ipcMain.handle('bascula:comando', async (_event, comando) => {
  switch (String(comando || '')) {
    case 'reconectar':
      return basculaService.reconectar();
    case 'tarar':
      return basculaService.tarar();
    case 'quitar-tara':
      return basculaService.quitarTara();
    case 'abrir-diagnostico':
      abrirVentanaDiagnosticoBascula();
      return { abierto: true };
    default:
      throw new Error(`Comando de báscula no reconocido: ${comando}`);
  }
});

ipcMain.handle('open-external', async (_, url) => {
  await shell.openExternal(url);
});

ipcMain.handle('get-connection-config', async () => ({
  ...connectionConfig,
  path: getConnectionConfigPath()
}));

ipcMain.handle('save-connection-config', async (_, config = {}) => {
  const saved = writeConnectionConfig(config);
  let firewall = null;
  if (saved.mode === 'server') {
    firewall = await asegurarReglaFirewall(saved.server_port);
  }
  const modoActivo = activeBindHost === LAN_BIND_HOST ? 'server' : 'client';
  return {
    ok: true,
    config: saved,
    firewall,
    requiresRestart: saved.mode !== modoActivo
      || (saved.mode === 'server' && saved.server_port !== activePort)
  };
});

function listarDireccionesLocales() {
  return Object.values(os.networkInterfaces())
    .flatMap((interfaces) => interfaces || [])
    .filter((item) => item.family === 'IPv4' && !item.internal && !item.address.startsWith('169.254.'))
    .map((item) => item.address);
}

ipcMain.handle('get-local-network-addresses', async () => listarDireccionesLocales());

// Identidad de presencia: cada instalación (instalada o portable) tiene su propio
// identificador estable, incluso cuando las dos corren en el mismo computador.
function getInstallIdPath() {
  return path.join(app.getPath('userData'), 'install-id.txt');
}

function obtenerInstallId() {
  const rutaId = getInstallIdPath();
  try {
    if (fs.existsSync(rutaId)) {
      const guardado = String(fs.readFileSync(rutaId, 'utf8') || '').trim();
      if (guardado) return guardado;
    }
  } catch (error) {
    // Si no se puede leer, se genera uno nuevo.
  }

  const nuevo = `${IS_PORTABLE_BUILD ? 'portable' : 'instalada'}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
  try {
    fs.mkdirSync(path.dirname(rutaId), { recursive: true });
    fs.writeFileSync(rutaId, nuevo, 'utf8');
  } catch (error) {
    // Sin persistencia el id cambia al reiniciar, pero sigue funcionando.
  }
  return nuevo;
}

ipcMain.handle('get-presence-identity', async () => {
  let equipo = '';
  try {
    equipo = os.hostname();
  } catch (error) {
    equipo = '';
  }
  return {
    installId: obtenerInstallId(),
    isPortable: IS_PORTABLE_BUILD,
    modo: IS_PORTABLE_BUILD ? 'portable' : 'principal',
    nombre: IS_PORTABLE_BUILD ? 'CAJA PORTABLE' : 'EQUIPO PRINCIPAL',
    equipo,
    sistema: `${os.platform()} ${os.release()}`,
    version: app.getVersion(),
    localPort: activePort || connectionConfig.server_port || DEFAULT_SERVER_PORT
  };
});

ipcMain.handle('get-connection-runtime', async () => ({
  mode: connectionConfig.mode,
  configuredPort: connectionConfig.server_port,
  activePort,
  bindHost: activeBindHost,
  isServing: Boolean(phpProcess && !phpProcess.killed && activeBindHost === LAN_BIND_HOST),
  processRunning: Boolean(phpProcess && !phpProcess.killed),
  isPortable: isPortableBuild(),
  addresses: listarDireccionesLocales(),
  firewall: ultimoEstadoFirewall,
  configPath: getConnectionConfigPath()
}));

ipcMain.handle('ensure-firewall-rule', async (_, port) => {
  const puerto = Number(port);
  return await asegurarReglaFirewall(Number.isInteger(puerto) && puerto >= 1 && puerto <= 65535 ? puerto : DEFAULT_SERVER_PORT);
});

ipcMain.handle('stop-main-server', async () => {
  const puertoServidor = Number(connectionConfig.server_port) || activePort || DEFAULT_SERVER_PORT;
  if (activeBindHost !== LAN_BIND_HOST || !phpProcess || phpProcess.killed) {
    const saved = writeConnectionConfig({ ...connectionConfig, mode: 'stopped', server_port: puertoServidor });
    const firewall = eliminarReglaFirewall(puertoServidor);
    return {
      ok: true,
      alreadyStopped: true,
      port: puertoServidor,
      firewall,
      config: saved,
      message: firewall.ok
        ? `El servidor ya estaba apagado. El puerto ${puertoServidor} no está publicado en la red.`
        : `El servidor ya estaba apagado. ${firewall.mensaje}`
    };
  }

  const saved = writeConnectionConfig({ ...connectionConfig, mode: 'stopped', server_port: puertoServidor });
  await stopPhpServerAndWait();
  const portReleased = await isPortFree(puertoServidor, SERVER_HOST)
    && await isPortFree(puertoServidor, LAN_BIND_HOST);
  const firewall = eliminarReglaFirewall(puertoServidor);

  if (!portReleased) {
    return {
      ok: false,
      port: puertoServidor,
      firewall,
      config: saved,
      error: `El proceso se cerró, pero el puerto ${puertoServidor} continúa ocupado por otro programa.`
    };
  }

  activeBindHost = SERVER_HOST;
  ultimoEstadoFirewall = firewall.ok
    ? { intentado: false, ok: false, mensaje: `Servidor apagado. Puerto ${puertoServidor} liberado.` }
    : firewall;

  setTimeout(() => {
    app.relaunch();
    app.exit(0);
  }, 700);

  return {
    ok: true,
    port: puertoServidor,
    portReleased: true,
    firewall,
    config: saved,
    restarting: true,
    message: `Servidor apagado. Puerto ${puertoServidor} liberado.`
  };
});

ipcMain.handle('restart-app', async () => {
  await stopPhpServerAndWait();
  app.relaunch();
  app.exit(0);
});

ipcMain.handle('check-remote-server', async (_, rawUrl) => {
  const targetUrl = String(rawUrl || '').trim();
  const logPath = path.join(app.getPath('desktop'), 'autoservicio-connection-diagnostics.txt');
  const writeLog = (message) => {
    try {
      fs.appendFileSync(logPath, `[${new Date().toISOString()}] ${message}\r\n`, 'utf8');
    } catch (error) {
      console.warn('No se pudo escribir diagnóstico de conexión:', error.message);
    }
  };

  writeLog(`Intento de conexión: ${targetUrl || '(vacío)'}`);
  if (!/^https?:\/\//i.test(targetUrl)) {
    writeLog('Resultado: ERROR URL no válida');
    return { ok: false, status: 0, error: 'URL no válida' };
  }

  return new Promise((resolve) => {
    let settled = false;
    const finish = (result) => {
      if (settled) return;
      settled = true;
      writeLog(`Resultado final: ${result.ok ? 'OK' : 'ERROR'}${result.status ? ` HTTP ${result.status}` : ''}${result.error ? ` ${result.error}` : ''}`);
      resolve(result);
    };

    const request = http.get(targetUrl, { headers: { Connection: 'close' } }, (response) => {
      let body = '';
      response.setEncoding('utf8');
      response.on('data', (chunk) => {
        if (body.length < 8192) body += chunk;
      });
      response.once('end', () => {
        let payload = null;
        try { payload = JSON.parse(body); } catch (_) { /* respuesta no JSON */ }
        const statusOk = response.statusCode >= 200 && response.statusCode < 400;
        const identityOk = payload?.ok === true && payload?.app === APP_NAME;
        const result = {
          ok: statusOk && identityOk,
          status: response.statusCode || 0,
          error: statusOk && !identityOk
            ? 'El destino respondió, pero no corresponde a AUTOSERVICIO MI ESTRELLA.'
            : (response.statusCode === 404 ? 'El servidor respondió 404: falta el archivo de comprobación health.php en esa instalación.' : undefined)
        };
        writeLog(`Resultado: HTTP ${result.status} ${result.ok ? 'OK' : 'ERROR'}`);
        finish(result);
      });
    });

    request.setTimeout(5000, () => {
      writeLog('Resultado: TIMEOUT después de 5000 ms');
      request.destroy();
      finish({ ok: false, status: 0, error: 'Tiempo de espera agotado' });
    });
    request.once('error', (error) => {
      writeLog(`Resultado: ERROR ${error.code || ''} ${error.message}`.trim());
      finish({ ok: false, status: 0, error: error.message });
    });

    setTimeout(() => {
      if (!settled) {
        writeLog('Resultado: TIMEOUT global después de 5500 ms');
        request.destroy();
        finish({ ok: false, status: 0, error: 'Tiempo de espera agotado' });
      }
    }, 5500);
  });
});

ipcMain.handle('get-connection-diagnostics', async () => {
  const desktopPath = app.getPath('desktop');
  const logPath = path.join(desktopPath, 'autoservicio-connection-diagnostics.txt');
  try {
    const content = fs.existsSync(logPath) ? fs.readFileSync(logPath, 'utf8') : 'No hay diagnósticos registrados todavía.';
    return { ok: true, path: logPath, content };
  } catch (error) {
    return { ok: false, path: logPath, content: `No se pudo leer el diagnóstico: ${error.message}` };
  }
});

function normalizePdfFileName(value) {
  const raw = String(value || 'documento').trim();
  const normalized = raw
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[<>:"/\\|?*]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  const safe = normalized || 'documento';
  return safe.toLowerCase().endsWith('.pdf') ? safe : `${safe}.pdf`;
}

ipcMain.handle('print-html', async (_, payload = {}) => {
  try {
    const html = String(payload.html || '');
    const title = String(payload.title || 'documento').trim() || 'documento';
    const nombreArchivoPdf = normalizePdfFileName(title);
    const tituloDocumento = nombreArchivoPdf.replace(/\.pdf$/i, '');
    if (!html) {
      return { success: false, error: 'No hay contenido para imprimir.' };
    }

    const printWindow = new BrowserWindow({
      parent: mainWindow || undefined,
      modal: false,
      show: false,
      backgroundColor: '#ffffff',
      title: tituloDocumento,
      webPreferences: {
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: false,
        preload: path.join(__dirname, 'preload.js')
      }
    });

    const htmlBase = payload.preview
      ? html.replace(/window\.onload\s*=\s*function\(\)\s*\{[\s\S]*?window\.print\(\);[\s\S]*?\};/, '')
      : html;
    const htmlParaPdf = payload.silent === false && !payload.pageSize
      ? html.replace('</head>', '<style>@page{size:A4;margin:12mm}html,body{width:100%!important;max-width:none!important;min-height:273mm!important;margin:0!important;padding:0!important;box-sizing:border-box;text-rendering:geometricPrecision}body{font-size:12px!important;color:#000!important;background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;text-shadow:none!important;-webkit-text-stroke:0!important;-webkit-font-smoothing:none!important}img{image-rendering:auto;-webkit-transform:translateZ(0);transform:translateZ(0)}.print-wrapper,.header,footer,table{width:100%!important;max-width:none!important;box-sizing:border-box;color:#000!important}.print-wrapper{padding:0!important}.header h1,.header .info,.empresa,.meta,table,th,td,footer,span{color:#000!important}table{table-layout:fixed;border-collapse:collapse}th,td{padding:8px!important;font-size:12px!important;border:1px solid rgba(0,0,0,.18)!important;background:#fff!important}.logo-empresa img{width:150px!important;height:96px!important;filter:grayscale(100%) brightness(0.62) contrast(1.25)!important}.footer-brand-logo{filter:brightness(0.7) contrast(1.1)!important}</style></head>')
      : htmlBase;
    const htmlVistaPrevia = payload.preview
      ? htmlParaPdf.replace(/window\.onload\s*=\s*function\(\)\s*\{[\s\S]*?window\.print\(\);[\s\S]*?\};/, '')
      : htmlParaPdf;
    const htmlConControles = payload.preview
      ? htmlVistaPrevia.replace('</body>', '<div id="electron-preview-controls" style="position:fixed;right:18px;bottom:18px;z-index:99999;display:flex;gap:10px;padding:10px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 4px 18px rgba(15,23,42,.18);font-family:Arial,sans-serif"><button onclick="window.print()" style="padding:10px 18px;border:0;border-radius:5px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer">IMPRIMIR</button><button onclick="window.electronAPI?.saveHtmlPdf({html:window.__electronPdfHtml ? window.__electronPdfHtml() : document.documentElement.outerHTML,title:document.title})" style="padding:10px 18px;border:0;border-radius:5px;background:#16a34a;color:#fff;font-weight:700;cursor:pointer">GUARDAR PDF</button><button onclick="window.close()" style="padding:10px 18px;border:1px solid #94a3b8;border-radius:5px;background:#fff;color:#1e293b;font-weight:700;cursor:pointer">CERRAR</button></div><style>@media print{#electron-preview-controls{display:none!important}}</style></body>')
      : htmlParaPdf;
    const htmlLocal = payload.appHeaderLayout
      ? htmlConControles.replace('</head>', '<style>.header{position:relative!important;display:block!important;min-height:230px!important;padding-right:3100px!important;box-sizing:border-box!important}.header .info{display:block!important;width:calc(100% - 310px)!important;max-width:none!important;min-width:0!important;padding:0!important}.meta-fecha .valor-fecha,.meta-hora .valor-hora{position:relative!important;left:-35px!important}.logo-empresa{position:absolute!important;top:0!important;right:-80px!important;width:300px!important;display:flex!important;flex-direction:column!important;align-items:center!important;z-index:2!important}.logo-empresa img{display:block!important;width:300px!important;height:230px!important;margin:0!important;object-fit:contain!important}.logo-empresa .empresa{font-size:18px!important;white-space:normal!important;text-align:center!important}</style></head>')
      : htmlConControles;
    await new Promise((resolve, reject) => {
      const handleLoaded = () => {
        printWindow.webContents.removeListener('did-fail-load', handleFailed);
        resolve();
      };
      const handleFailed = (_event, errorCode, errorDescription) => {
        printWindow.webContents.removeListener('did-finish-load', handleLoaded);
        reject(new Error(`No se pudo cargar la vista previa: ${errorDescription || errorCode}`));
      };
      printWindow.webContents.once('did-finish-load', handleLoaded);
      printWindow.webContents.once('did-fail-load', handleFailed);
      printWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(htmlLocal));
    });
    await printWindow.webContents.executeJavaScript(`document.title = ${JSON.stringify(tituloDocumento)}`);
    await new Promise((resolve) => setTimeout(resolve, 100));
    if (payload.preview) {
      printWindow.show();
      printWindow.focus();
      return { success: true, title: tituloDocumento, preview: true };
    }

    const printers = await printWindow.webContents.getPrintersAsync();
    const printer = printers.find((item) => /jaltech|pos-80c|pos80c/i.test(item.name));
    const contentHeightPx = await printWindow.webContents.executeJavaScript(
      'Math.ceil(Math.max(document.body.scrollHeight, document.documentElement.scrollHeight))'
    );
    const paperHeightMicrons = Math.max(50000, Math.ceil(contentHeightPx * 264.583));

    await new Promise((resolve, reject) => {
      printWindow.webContents.print({
        silent: payload.silent === false ? false : Boolean(printer),
        deviceName: payload.silent === false ? undefined : (printer ? printer.name : undefined),
        printBackground: true,
        color: false,
        margins: { marginType: 'none' },
        pageSize: payload.pageSize === 'A4' || payload.silent === false
          ? { width: 210000, height: 297000 }
          : { width: 80000, height: paperHeightMicrons }
      }, (success, failureReason) => {
        if (success) {
          resolve();
          return;
        }
        reject(new Error(failureReason || 'No se pudo enviar el ticket a la impresora.'));
      });
    });

    try {
      printWindow.close();
    } catch (error) {
      console.warn('No se pudo cerrar la ventana temporal de impresión:', error);
    }

    return { success: true, title, printer: printer ? printer.name : null };
  } catch (error) {
    console.error('Error al imprimir documento desde Electron:', error);
    return { success: false, error: error.message || 'No se pudo abrir la impresión.' };
  }
});

ipcMain.handle('save-html-pdf', async (_, payload = {}) => {
  let pdfWindow = null;
  try {
    const html = String(payload.html || '');
    const title = String(payload.title || 'REPORTE DE VENTAS').trim() || 'REPORTE DE VENTAS';
    if (!html) return { success: false, error: 'No hay contenido para guardar.' };

    const result = await dialog.showSaveDialog(mainWindow, {
      title: 'Guardar reporte en PDF',
      defaultPath: normalizePdfFileName(title),
      filters: [{ name: 'Documento PDF', extensions: ['pdf'] }]
    });
    if (result.canceled || !result.filePath) return { success: false, canceled: true };

    pdfWindow = new BrowserWindow({
      webPreferences: { contextIsolation: true, nodeIntegration: false, sandbox: false }
    });
    const pdfHtml = html.replace('</head>', '<meta name="viewport" content="width=device-width, initial-scale=1"><style>@page{size:A4;margin:12mm}html,body{display:block!important;width:100vw!important;max-width:100vw!important;min-width:100vw!important;min-height:273mm!important;margin:0!important;padding:0!important;box-sizing:border-box;text-rendering:geometricPrecision}body{font-size:12px!important;color:#000!important;background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;text-shadow:none!important;-webkit-text-stroke:0!important;-webkit-font-smoothing:none!important}img{image-rendering:auto;-webkit-transform:translateZ(0);transform:translateZ(0)}.print-wrapper,.header,footer,table{display:table!important;width:100%!important;max-width:none!important;min-width:100%!important;box-sizing:border-box;color:#000!important}.print-wrapper{display:block!important;padding:0!important}.header{display:flex!important;margin-bottom:16px!important}.header h1{font-size:22px!important}.header .info{max-width:64%!important}.logo-empresa img{width:150px!important;height:96px!important;filter:grayscale(100%) brightness(0.62) contrast(1.25)!important}.empresa{font-size:20px!important;color:#000!important}.meta{font-size:13px!important;color:#000!important}.subtitulo{font-size:15px!important;color:#000!important}table{table-layout:fixed!important;font-size:12px!important;border-collapse:collapse}th,td{padding:8px!important;font-size:12px!important;color:#000!important;border:1px solid rgba(0,0,0,.18)!important;background:#fff!important}.footer-brand-logo{filter:brightness(0.7) contrast(1.1)!important}#electron-preview-controls{display:none!important}</style></head>');
    await pdfWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(pdfHtml));
    await new Promise((resolve) => setTimeout(resolve, 250));
    const pdf = await pdfWindow.webContents.printToPDF({
      printBackground: true,
      pageSize: { width: 210000, height: 297000 },
      marginsType: 'none',
      preferCSSPageSize: false
    });
    fs.writeFileSync(result.filePath, pdf);
    return { success: true, filePath: result.filePath };
  } catch (error) {
    console.error('Error al guardar reporte en PDF:', error);
    return { success: false, error: error.message || 'No se pudo guardar el PDF.' };
  } finally {
    try { pdfWindow?.close(); } catch (error) { console.warn('No se pudo cerrar la ventana PDF:', error); }
  }
});

ipcMain.handle('save-exported-database', async (_, filename, data) => {
  const result = await dialog.showSaveDialog(mainWindow, {
    title: 'Guardar base de datos del taller de mecánica',
    defaultPath: filename,
    filters: [{ name: 'Base de datos del taller', extensions: ['zip'] }]
  });
  if (result.canceled || !result.filePath) {
    return { saved: false };
  }

  fs.writeFileSync(result.filePath, Buffer.from(data));

  return { saved: true, filePath: result.filePath, filename };
});
