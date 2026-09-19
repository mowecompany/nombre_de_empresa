const { contextBridge, ipcRenderer } = require('electron');
const os = require('os');

contextBridge.exposeInMainWorld('electronAPI', {
  openExternal: async (url) => {
    return ipcRenderer.invoke('open-external', url);
  },
  getDeviceInfo: async () => {
    let equipo = '';
    try {
      equipo = os.hostname();
    } catch (error) {
      equipo = '';
    }
    return {
      equipo,
      sistema: `${os.platform()} ${os.release()}`,
      version: process.env.npm_package_version || '1.0.0'
    };
  },
  getPresenceIdentity: async () => {
    return ipcRenderer.invoke('get-presence-identity');
  },
  checkRemoteServer: async (url) => {
    return ipcRenderer.invoke('check-remote-server', url);
  },
  getConnectionDiagnostics: async () => {
    return ipcRenderer.invoke('get-connection-diagnostics');
  },
  getConnectionConfig: async () => {
    return ipcRenderer.invoke('get-connection-config');
  },
  saveConnectionConfig: async (config) => {
    return ipcRenderer.invoke('save-connection-config', config);
  },
  getLocalNetworkAddresses: async () => {
    return ipcRenderer.invoke('get-local-network-addresses');
  },
  getConnectionRuntime: async () => {
    return ipcRenderer.invoke('get-connection-runtime');
  },
  ensureFirewallRule: async (port) => {
    return ipcRenderer.invoke('ensure-firewall-rule', port);
  },
  stopMainServer: async () => {
    return ipcRenderer.invoke('stop-main-server');
  },
  restartApp: async () => {
    return ipcRenderer.invoke('restart-app');
  },
  saveExportedDatabase: async (filename, data) => {
    return ipcRenderer.invoke('save-exported-database', filename, data);
  },
  printHtml: async (payload = {}) => {
    return ipcRenderer.invoke('print-html', payload);
  },
  saveHtmlPdf: async (payload = {}) => {
    return ipcRenderer.invoke('save-html-pdf', payload);
  },
  onDatabaseExportSaved: (callback) => {
    ipcRenderer.on('database-export-success', (_, payload) => {
      callback?.(payload);
    });
  },
  getAppInfo: () => ({
    name: 'AUTOSERVICIO MI ESTRELLA',
    company: 'AUTOSERVICIO MI ESTRELLA',
    version: process.env.npm_package_version || '1.0.0'
  })
});

// ---------------------------------------------------------------------------
// Báscula ACS-30: único canal hacia el servicio serial del proceso principal.
// La vista solo escucha; nunca abre ni administra el puerto COM.
// ---------------------------------------------------------------------------
const suscribir = (canal, callback) => {
  if (typeof callback !== 'function') return () => {};
  const manejador = (_evento, payload) => callback(payload);
  ipcRenderer.on(canal, manejador);
  return () => ipcRenderer.removeListener(canal, manejador);
};

contextBridge.exposeInMainWorld('basculaAPI', {
  estado: () => ipcRenderer.invoke('bascula:estado'),
  diagnostico: () => ipcRenderer.invoke('bascula:diagnostico'),
  reconectar: () => ipcRenderer.invoke('bascula:comando', 'reconectar'),
  tarar: () => ipcRenderer.invoke('bascula:comando', 'tarar'),
  quitarTara: () => ipcRenderer.invoke('bascula:comando', 'quitar-tara'),
  abrirDiagnostico: () => ipcRenderer.invoke('bascula:comando', 'abrir-diagnostico'),
  onPeso: (callback) => suscribir('bascula:peso', callback),
  onEstado: (callback) => suscribir('bascula:estado-cambio', callback),
  onTrama: (callback) => suscribir('bascula:trama', callback)
});
