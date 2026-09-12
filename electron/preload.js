const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  openExternal: async (url) => {
    return ipcRenderer.invoke('open-external', url);
  },
  checkRemoteServer: async (url) => {
    return ipcRenderer.invoke('check-remote-server', url);
  },
  discoverRemoteServer: async (options) => {
    return ipcRenderer.invoke('discover-remote-server', options);
  },
  getConnectionDiagnostics: async () => {
    return ipcRenderer.invoke('get-connection-diagnostics');
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
