const { app, BrowserWindow, dialog, ipcMain, shell } = require('electron');
const { spawn, spawnSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const net = require('net');
const http = require('http');

const APP_NAME = 'AUTOSERVICIO MI ESTRELLA';
const COMPANY_NAME = 'AUTOSERVICIO MI ESTRELLA';
const SERVER_HOST = '127.0.0.1';
const DEFAULT_PORT = 8000;
const MAX_PORT = 8010;
const APP_START_PATH = '/Views/login.php';
const PROJECT_ROOT = path.resolve(__dirname, '..');
const ICON_PATH = path.join(PROJECT_ROOT, 'logo.ico');
const FALLBACK_ICON_PATH = path.join(PROJECT_ROOT, 'electron', 'build', 'app_icon.ico');

function findPhpExecutable(rootDir) {
  if (!fs.existsSync(rootDir)) {
    return null;
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

let phpProcess = null;
let mainWindow = null;
let activePort = DEFAULT_PORT;
let basculaSerial = null;
let basculaBridgeServer = null;
const basculaBridgeClients = new Set();
let ultimaTramaBalanza = null;
let temporizadorReconectarBalanza = null;
let intentosConexionBalanza = 0;
let reconexionBalanzaEnCurso = false;
let operacionBalanzaEnCurso = Promise.resolve();
let ultimoListadoPuertosBalanza = '';
let puertosDetectadosBalanza = [];

async function actualizarPuertosDetectadosBalanza() {
  const puertos = await SerialPort.list().catch(() => []);
  puertosDetectadosBalanza = puertos.map(item => ({
    path: item.path,
    manufacturer: item.manufacturer || '',
    vendorId: item.vendorId || '',
    productId: item.productId || ''
  }));
  const listadoActual = JSON.stringify(puertosDetectadosBalanza);
  if (listadoActual !== ultimoListadoPuertosBalanza) {
    ultimoListadoPuertosBalanza = listadoActual;
    console.info('[BASCULA][ELECTRON] puertos USB detectados:', puertosDetectadosBalanza);
    publicarEstadoBalanza();
  }
  return puertos;
}

function encolarOperacionBalanza(operacion) {
  const turno = operacionBalanzaEnCurso.then(operacion, operacion);
  operacionBalanzaEnCurso = turno.catch(() => {});
  return turno;
}

function obtenerConfiguracionesSerialBalanza(configuracionBase = {}) {
  const baudRates = Array.from(new Set([
    Number(configuracionBase.baudRate) || 9600,
    9600,
    4800,
    2400,
    19200,
    115200
  ]));
  const dataBits = Array.from(new Set([Number(configuracionBase.dataBits) || 8, 8]));
  const parities = Array.from(new Set([String(configuracionBase.parity || 'none').toLowerCase(), 'none', 'even', 'odd']));
  const stopBits = Array.from(new Set([Number(configuracionBase.stopBits) || 1, 1, 2]));

  const configuraciones = [];
  for (const baudRate of baudRates) {
    for (const dataBit of dataBits) {
      for (const parity of parities) {
        for (const stopBit of stopBits) {
          configuraciones.push({ baudRate, dataBits: dataBit, parity, stopBits: stopBit });
        }
      }
    }
  }

  return configuraciones.filter((config, index, arr) => arr.findIndex(item => (
    item.baudRate === config.baudRate &&
    item.dataBits === config.dataBits &&
    item.parity === config.parity &&
    item.stopBits === config.stopBits
  )) === index);
}

async function abrirPuertoBalanzaConFallback(pathName, configuracionInicial = {}) {
  const intentos = obtenerConfiguracionesSerialBalanza(configuracionInicial);
  let ultimoError = null;

  for (const configuracion of intentos) {
    const puertoIntento = new SerialPort({
      path: pathName,
      baudRate: Number(configuracion.baudRate),
      dataBits: Number(configuracion.dataBits),
      stopBits: Number(configuracion.stopBits),
      parity: String(configuracion.parity || 'none'),
      autoOpen: false
    });

    try {
      await new Promise((resolve, reject) => {
        const manejarError = (error) => {
          puertoIntento.removeListener('error', manejarError);
          reject(error);
        };
        puertoIntento.once('error', manejarError);
        puertoIntento.open((error) => {
          puertoIntento.removeListener('error', manejarError);
          if (error) reject(error); else resolve();
        });
      });
      return puertoIntento;
    } catch (error) {
      ultimoError = error;
      try { if (puertoIntento.isOpen) puertoIntento.close(); } catch (closeError) {}
      try { puertoIntento.removeAllListeners(); } catch (listenerError) {}
    }
  }

  throw ultimoError || new Error(`No se pudo abrir el puerto ${pathName} con ninguna configuración serial válida.`);
}

function publicarTramaBalanza(data) {
  const bytes = Buffer.from(data);
  ultimaTramaBalanza = {
    data: bytes.toString('base64'),
    timestamp: Date.now()
  };
  for (const client of basculaBridgeClients) {
    client.write(`event: raw\ndata: ${ultimaTramaBalanza.data}\n\n`);
  }
  if (!mainWindow?.isDestroyed()) {
    const payload = { data: ultimaTramaBalanza.data };
    mainWindow.webContents.send('bascula-datos-raw', payload);
    for (const frame of mainWindow.webContents.mainFrame.framesInSubtree()) {
      if (frame === mainWindow.webContents.mainFrame) continue;
      try {
        mainWindow.webContents.sendToFrame(frame.frameId, 'bascula-datos-raw', payload);
      } catch (error) {
        console.warn('[BASCULA][ELECTRON] no se pudo enviar RAW al frame:', error?.message || error);
      }
    }
  }
}

function publicarEstadoBalanza() {
  const estado = JSON.stringify({ conectado: Boolean(basculaSerial?.isOpen), path: basculaSerial?.path || null });
  for (const client of basculaBridgeClients) client.write(`event: estado\ndata: ${estado}\n\n`);
}

async function cerrarPuertoBalanzaSiExiste() {
  if (!basculaSerial) return;
  try {
    if (basculaSerial.isOpen) {
      await new Promise((resolve, reject) => {
        basculaSerial.close((error) => {
          if (error) reject(error); else resolve();
        });
      });
    }
  } catch (error) {
    console.warn('[BASCULA][ELECTRON] cierre forzado del puerto fallido:', error?.message || error);
  } finally {
    basculaSerial = null;
  }
}

function iniciarPuenteLocalBalanza() {
  basculaBridgeServer = http.createServer((request, response) => {
    response.setHeader('Access-Control-Allow-Origin', '*');
    response.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    if (request.method === 'OPTIONS') {
      response.writeHead(204);
      response.end();
      return;
    }
    if (request.url === '/bascula/status') {
      response.setHeader('Content-Type', 'application/json');
      response.end(JSON.stringify({ conectado: Boolean(basculaSerial?.isOpen), path: basculaSerial?.path || null, puertos: puertosDetectadosBalanza, ultimaTrama: ultimaTramaBalanza }));
      return;
    }
    if (request.url === '/bascula/stream') {
      response.writeHead(200, { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache', Connection: 'keep-alive' });
      response.write(`event: estado\ndata: ${JSON.stringify({ conectado: Boolean(basculaSerial?.isOpen), path: basculaSerial?.path || null, puertos: puertosDetectadosBalanza })}\n\n`);
      if (ultimaTramaBalanza) response.write(`event: raw\ndata: ${ultimaTramaBalanza.data}\n\n`);
      basculaBridgeClients.add(response);
      request.on('close', () => basculaBridgeClients.delete(response));
      return;
    }
    response.writeHead(404);
    response.end();
  });
  basculaBridgeServer.on('error', error => console.error('Puente local de báscula:', error.message));
  basculaBridgeServer.listen(8765, '127.0.0.1');
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

function isPortFree(port) {
  return new Promise((resolve) => {
    const server = net.createServer()
      .once('error', (err) => {
        server.close();
        resolve(err.code !== 'EADDRINUSE');
      })
      .once('listening', () => {
        server.close(() => resolve(true));
      })
      .listen(port, SERVER_HOST);
  });
}

async function findAvailablePort() {
  for (let port = DEFAULT_PORT; port <= MAX_PORT; port += 1) {
    if (await isPortFree(port)) {
      return port;
    }
  }
  throw new Error(`No hay puertos libres entre ${DEFAULT_PORT} y ${MAX_PORT}.`);
}

function waitForServerReady(port, timeout = 15000) {
  const serverUrl = `http://${SERVER_HOST}:${port}`;
  const startTime = Date.now();

  return new Promise((resolve, reject) => {
    const check = () => {
      const socket = net.createConnection(port, SERVER_HOST);
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
    ? crypto.createHash('sha256').update(fs.readFileSync(sourceDbPath)).digest('hex')
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
    DB_CONNECTION: process.env.DB_CONNECTION || 'sqlite',
    SQLITE_PATH: sqlitePath,
    DB_CHARSET: 'utf8mb4'
  };
}

function isBundledPhpExecutable(phpExecutable) {
  const bundledRoot = app.isPackaged
    ? path.join(process.resourcesPath, 'php')
    : path.join(PROJECT_ROOT, 'php');

  return phpExecutable && phpExecutable.startsWith(bundledRoot);
}

function startPhpServer(port) {
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
    phpArgs.push('-S', `${SERVER_HOST}:${port}`, '-t', PROJECT_ROOT);

    phpProcess = spawn(phpExecutable, phpArgs, {
      cwd: PROJECT_ROOT,
      env,
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe']
    });

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
      if (code !== 0 && signal !== 'SIGTERM') {
        console.error(`PHP terminó con código ${code} y señal ${signal}`);
      }
    });

    waitForServerReady(port)
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

function createMainWindow(serverUrl) {
  mainWindow = new BrowserWindow({
    title: APP_NAME,
    icon: fs.existsSync(ICON_PATH)
      ? ICON_PATH
      : (fs.existsSync(FALLBACK_ICON_PATH) ? FALLBACK_ICON_PATH : undefined),
    show: false,
    backgroundColor: '#F7F7F7',
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      enableRemoteModule: false,
      sandbox: false
    }
  });

  // Permitir que la vista autenticada solicite y use una báscula serial USB.
  mainWindow.webContents.session.setPermissionCheckHandler((_webContents, permission) => {
    return permission === 'serial';
  });
  mainWindow.webContents.session.setPermissionRequestHandler((_webContents, permission, callback) => {
    callback(permission === 'serial');
  });
  mainWindow.webContents.session.on('select-serial-port', (event, portList, _webContents, callback) => {
    event.preventDefault();
    const primerPuerto = Array.isArray(portList) ? portList[0] : null;
    callback(primerPuerto ? primerPuerto.portId : '');
  });

  mainWindow.setMenuBarVisibility(false);
  mainWindow.loadURL(`${serverUrl}${APP_START_PATH}`);

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
      normalizedNav.startsWith('blob:') ||
      navigationUrl.startsWith(serverUrl)
    ) {
      return;
    }
    event.preventDefault();
    shell.openExternal(navigationUrl);
  });
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

app.setAppUserModelId('com.autoservicio.laestrella.desktop');

if (!app.requestSingleInstanceLock()) {
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
    activePort = await findAvailablePort();
    const serverUrl = `http://${SERVER_HOST}:${activePort}`;
    await startPhpServer(activePort);
    createMainWindow(serverUrl);
  } catch (error) {
    showFatalError('No se pudo iniciar la aplicación.', error.message);
    app.quit();
  }
});

app.on('before-quit', () => {
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

ipcMain.handle('bascula-listar-puertos', async () => {
  const puertos = await SerialPort.list();
  console.info('[BASCULA][ELECTRON] puertos detectados:', puertos.map((puerto) => ({ path: puerto.path, manufacturer: puerto.manufacturer || '' })));
  return puertos.map((puerto) => ({
    path: puerto.path,
    manufacturer: puerto.manufacturer || '',
    serialNumber: puerto.serialNumber || '',
    vendorId: puerto.vendorId || '',
    productId: puerto.productId || ''
  }));
});

ipcMain.handle('bascula-conectar', async (event, options = {}) => {
  return encolarOperacionBalanza(async () => {
    console.info('[BASCULA][ELECTRON] solicitud de conexión:', options);
    await cerrarPuertoBalanzaSiExiste();

    const pathName = String(options.path || '').trim();
    const baudRate = Number(options.baudRate);
    const dataBits = Number(options.dataBits);
    const stopBits = Number(options.stopBits);
    const parity = String(options.parity || 'none');
    if (!pathName) throw new Error('Puerto no disponible');
    const puertos = await SerialPort.list();
    puertosDetectadosBalanza = puertos.map(item => ({ path: item.path, manufacturer: item.manufacturer || '', vendorId: item.vendorId || '', productId: item.productId || '' }));
    const puertoSeleccionado = puertos.find((puerto) => puerto.path === pathName);
    if (!puertoSeleccionado || !/CH340|USB-SERIAL|wch\.cn/i.test(`${puertoSeleccionado.path} ${puertoSeleccionado.manufacturer}`)) {
      throw new Error('Seleccione el puerto USB-SERIAL CH340 de la ACS-30, no COM1.');
    }
    if (!Number.isInteger(baudRate) || baudRate <= 0) throw new Error('Debe indicar el baud rate real de la ACS-30.');
    if (![5, 6, 7, 8].includes(dataBits) || ![1, 2].includes(stopBits) || !['none', 'even', 'odd', 'mark', 'space'].includes(parity)) {
      throw new Error('Parámetros seriales no válidos.');
    }

    try {
      basculaSerial = await abrirPuertoBalanzaConFallback(pathName, { baudRate, dataBits, parity, stopBits });
      basculaSerial.on('data', (data) => {
        publicarTramaBalanza(data);
      });
      const puertoAbierto = basculaSerial;
      puertoAbierto.on('close', () => {
        if (basculaSerial !== puertoAbierto) return;
        basculaSerial = null;
        intentosConexionBalanza = 0;
        publicarEstadoBalanza();
        if (!mainWindow?.isDestroyed()) mainWindow.webContents.send('bascula-estado', { estado: 'desconectada' });
      });
      puertoAbierto.on('error', (error) => {
        if (!mainWindow?.isDestroyed()) mainWindow.webContents.send('bascula-error', { mensaje: error.message });
        if (puertoAbierto.isOpen) {
          puertoAbierto.close(() => {
            if (basculaSerial !== puertoAbierto) return;
            basculaSerial = null;
            intentosConexionBalanza = 0;
            publicarEstadoBalanza();
          });
        }
      });

      console.info('[BASCULA][ELECTRON] puerto abierto correctamente:', pathName);
      return { path: pathName };
    } catch (error) {
      console.error('[BASCULA][ELECTRON] error al abrir puerto:', { path: pathName, message: error.message, stack: error.stack });
      basculaSerial = null;
      const mensaje = String(error?.message || '');
      if (/Unknown error code 31|Access is denied|The port is already open|COM3/i.test(mensaje)) {
        throw new Error(`El puerto ${pathName} está ocupado por otra aplicación, por otro programa del sistema o la báscula no responde. Revisa si COM3 está abierto en otra app y ciérrala antes de intentar de nuevo.`);
      }
      throw error;
    }
  });
});

async function conectarBasculaAutomatica() {
  if (basculaSerial?.isOpen) return;
  if (basculaSerial && !basculaSerial.isOpen) basculaSerial = null;
  if (reconexionBalanzaEnCurso) return;
  reconexionBalanzaEnCurso = true;
  intentosConexionBalanza += 1;
  try {
    const puertos = await actualizarPuertosDetectadosBalanza();
    const puerto = puertos.find(item => String(item.vendorId).toLowerCase() === '1a86' && String(item.productId).toLowerCase() === '7523')
      || puertos.find(item => /USB-SERIAL|CH340|wch\.cn/i.test(`${item.path} ${item.manufacturer}`))
      || puertos.find(item => /COM3/i.test(item.path));
    if (!puerto) {
      intentosConexionBalanza = 0;
      return;
    }

    basculaSerial = await abrirPuertoBalanzaConFallback(puerto.path, { baudRate: 9600, dataBits: 8, parity: 'none', stopBits: 1 });
    basculaSerial.on('data', publicarTramaBalanza);
    const puertoAbierto = basculaSerial;
    basculaSerial.on('close', () => {
      if (basculaSerial !== puertoAbierto) return;
      basculaSerial = null;
      intentosConexionBalanza = 0;
      publicarEstadoBalanza();
    });
    basculaSerial.on('error', error => {
      console.error('Báscula automática:', error.message);
      if (puertoAbierto.isOpen) {
        puertoAbierto.close(() => {
          if (basculaSerial !== puertoAbierto) return;
          basculaSerial = null;
          intentosConexionBalanza = 0;
          publicarEstadoBalanza();
        });
      }
    });
    publicarEstadoBalanza();
    intentosConexionBalanza = 0;
    console.log(`Báscula conectada automáticamente en ${puerto.path}`);
  } catch (error) {
    basculaSerial = null;
    console.error('No se pudo conectar automáticamente la báscula:', error.message);
  } finally {
    reconexionBalanzaEnCurso = false;
  }
}

ipcMain.handle('bascula-desconectar', async () => {
  return encolarOperacionBalanza(async () => {
    await cerrarPuertoBalanzaSiExiste();
    publicarEstadoBalanza();
  });
});

ipcMain.handle('bascula-probar', async () => ({
  conectado: Boolean(basculaSerial?.isOpen),
  path: basculaSerial?.path || null
}));

ipcMain.handle('open-external', async (_, url) => {
  await shell.openExternal(url);
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
      ? html.replace('</head>', '<style>@page{size:A4;margin:12mm}html,body{width:100%!important;max-width:none!important;min-height:273mm!important;margin:0!important;padding:0!important;box-sizing:border-box}body{font-size:12px!important}.print-wrapper,.header,footer,table{width:100%!important;max-width:none!important;box-sizing:border-box}.print-wrapper{padding:0!important}.header h1{font-size:22px!important}.header .info{max-width:64%!important}.logo-empresa img{width:150px!important;height:110px!important}.meta{font-size:13px!important}table{table-layout:fixed}th,td{padding:8px!important;font-size:12px!important}</style></head>')
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
    await printWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(htmlLocal));
    await printWindow.webContents.executeJavaScript(`document.title = ${JSON.stringify(tituloDocumento)}`);
    await new Promise((resolve) => setTimeout(resolve, 250));
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
    const pdfHtml = html.replace('</head>', '<meta name="viewport" content="width=device-width, initial-scale=1"><style>@page{size:A4;margin:12mm}html,body{display:block!important;width:100vw!important;max-width:100vw!important;min-width:100vw!important;min-height:273mm!important;margin:0!important;padding:0!important;box-sizing:border-box}body{font-size:12px!important}.print-wrapper,.header,footer,table{display:table!important;width:100%!important;max-width:none!important;min-width:100%!important;box-sizing:border-box}.print-wrapper{display:block!important;padding:0!important}.header{display:flex!important;margin-bottom:16px!important}.header h1{font-size:22px!important}.header .info{max-width:64%!important}.logo-empresa img{width:150px!important;height:110px!important}.empresa{font-size:20px!important}.meta{font-size:13px!important}.subtitulo{font-size:15px!important}table{table-layout:fixed!important;font-size:12px!important}th,td{padding:8px!important;font-size:12px!important}#electron-preview-controls{display:none!important}</style></head>');
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
