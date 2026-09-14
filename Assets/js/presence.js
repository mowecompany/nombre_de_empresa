/**
 * Presencia de cajas.
 *
 * Cada aplicación (instalada o portable) avisa periódicamente al servidor al que
 * está conectada. La identidad la informa la propia aplicación de escritorio, no
 * el servidor que sirve la página: así, dos aplicaciones en el mismo computador
 * cuentan como dos cajas distintas.
 */
(function () {
  if (window.__presenciaCajaIniciada) return;
  window.__presenciaCajaIniciada = true;

  const scriptActual = document.currentScript;
  const baseUrl = (() => {
    try {
      const src = new URL(scriptActual.src, window.location.href);
      return src.href.replace(/\/Assets\/js\/presence\.js.*$/i, '');
    } catch (error) {
      return '';
    }
  })();

  const STORAGE_KEY = 'autoservicioCajaPresenciaId';
  let identidad = null;

  function idNavegador() {
    let id = '';
    try {
      id = localStorage.getItem(STORAGE_KEY) || '';
      if (!id) {
        id = `navegador-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
        localStorage.setItem(STORAGE_KEY, id);
      }
    } catch (error) {
      id = `navegador-${Date.now().toString(36)}`;
    }
    return id;
  }

  async function obtenerIdentidad() {
    if (identidad) return identidad;

    let info = null;
    try {
      if (window.electronAPI && window.electronAPI.getPresenceIdentity) {
        info = await window.electronAPI.getPresenceIdentity();
      }
    } catch (error) {
      info = null;
    }

    if (info && info.installId) {
      identidad = {
        caja_id: String(info.installId),
        install_id: String(info.installId),
        modo: info.modo === 'portable' ? 'portable' : 'principal',
        nombre: String(info.nombre || 'CAJA'),
        equipo: String(info.equipo || '').trim() || 'EQUIPO SIN NOMBRE',
        sistema: String(info.sistema || '').trim() || (navigator.platform || 'Desconocido'),
        version: String(info.version || '').trim(),
        puerto: Number(info.localPort || window.location.port || 80)
      };
    } else {
      const id = idNavegador();
      identidad = {
        caja_id: id,
        install_id: id,
        modo: 'navegador',
        nombre: 'NAVEGADOR',
        equipo: 'NAVEGADOR WEB',
        sistema: navigator.platform || 'Desconocido',
        version: '',
        puerto: Number(window.location.port || 80)
      };
    }

    // Guardar el id también en el navegador permite que la pantalla de conexión
    // reconozca cuál de las cajas listadas es la de este equipo.
    try {
      localStorage.setItem(STORAGE_KEY, identidad.caja_id);
    } catch (error) {
      // Sin almacenamiento local solo se pierde la marca "este equipo".
    }

    return identidad;
  }

  async function registrarPresencia() {
    if (document.visibilityState === 'hidden') return;
    try {
      const datos = await obtenerIdentidad();
      await fetch(`${baseUrl}/api/v1/index.php?action=presence`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
        body: JSON.stringify(datos)
      });
    } catch (error) {
      // Un fallo de presencia nunca debe afectar la pantalla.
    }
  }

  window.registrarPresenciaCaja = registrarPresencia;
  window.obtenerIdentidadCaja = obtenerIdentidad;

  registrarPresencia();
  // Deshabilitado para evitar recargas automáticas (igual que el fix de commit e0af774)
  // window.setInterval(registrarPresencia, 15000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') registrarPresencia();
  });
})();
