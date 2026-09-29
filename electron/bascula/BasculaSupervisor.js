'use strict';

const { EventEmitter } = require('events');
const { fork } = require('child_process');
const path = require('path');

const TIEMPO_RESPUESTA_MS = 5000;
const TIEMPO_SALIDA_MS = 4000;
const TIEMPO_RECONEXION_MS = 20000;
const INTENTOS_ERROR_31_ANTES_RECUPERAR = 3;
const ESPERA_REENUMERACION_MS = 5000;

class BasculaSupervisor extends EventEmitter {
  constructor(opciones = {}) {
    super();
    this.puertoForzado = opciones.puertoForzado || null;
    this.procesoId = opciones.procesoId || process.pid;
    this.recuperarDispositivo = typeof opciones.recuperarDispositivo === 'function'
      ? opciones.recuperarDispositivo
      : null;
    this.worker = null;
    this.workerListo = false;
    this.detenido = true;
    this.reiniciando = null;
    this.secuencia = 0;
    this.intentosError31 = 0;
    this.recuperacionAutomaticaIntentada = false;
    this.temporizadorRecuperacion = null;
    this.pendientes = new Map();
    this.ultimoEstado = {
      procesoId: this.procesoId,
      procesoAuxiliarId: null,
      fase: 'detenido',
      disponible: true,
      conectado: false,
      puerto: null,
      adaptador: null,
      config: { baudRate: 9600, dataBits: 8, parity: 'none', stopBits: 1 },
      tara: 0,
      ultimoError: null,
      ultimaRecuperacion: null,
      ultimoPeso: null,
      ultimaTrama: null
    };
    this.ultimoDiagnostico = { ...this.ultimoEstado, puertos: [], registros: [] };
  }

  iniciar() {
    if (!this.detenido && this.worker) return;
    this.detenido = false;
    this.crearWorker();
  }

  crearWorker() {
    if (this.worker || this.detenido) return;
    this.actualizarFase('iniciando-lector');
    const workerPath = path.join(__dirname, 'BasculaWorker.js');
    const worker = fork(workerPath, [], {
      env: {
        ...process.env,
        ELECTRON_RUN_AS_NODE: '1',
        BASCULA_PUERTO: this.puertoForzado || ''
      },
      stdio: ['ignore', 'ignore', 'ignore', 'ipc'],
      windowsHide: true
    });
    this.worker = worker;
    this.workerListo = false;
    this.ultimoEstado = { ...this.ultimoEstado, procesoAuxiliarId: worker.pid || null };

    worker.on('message', (mensaje) => this.procesarMensaje(worker, mensaje));
    worker.once('error', (error) => {
      if (this.worker !== worker) return;
      this.ultimoEstado = {
        ...this.ultimoEstado,
        fase: 'error-lector',
        conectado: false,
        ultimoError: { codigo: 'lector-no-iniciado', mensaje: 'No se pudo iniciar el lector de la báscula.', detalle: error.message }
      };
      this.emit('estado', this.estado());
    });
    worker.once('exit', (codigo, senal) => this.alSalirWorker(worker, codigo, senal));
  }

  procesarMensaje(worker, mensaje = {}) {
    if (this.worker !== worker) return;
    if (mensaje.tipo === 'worker:listo') {
      this.workerListo = true;
      this.actualizarFase('detectando-bascula');
      return;
    }
    if (mensaje.tipo === 'evento:peso') {
      this.emit('peso', mensaje.payload);
      return;
    }
    if (mensaje.tipo === 'evento:trama') {
      this.emit('trama', mensaje.payload);
      return;
    }
    if (mensaje.tipo === 'evento:estado') {
      const estadoWorker = mensaje.payload || {};
      const fase = estadoWorker.conectado
        ? 'conectado'
        : (estadoWorker.ultimoError ? 'reintentando' : 'detectando-bascula');
      const recuperacionAnterior = this.ultimoEstado.ultimaRecuperacion || null;
      const recuperacion = estadoWorker.conectado && recuperacionAnterior && !recuperacionAnterior.ok
        ? {
            ...recuperacionAnterior,
            ok: true,
            pidNuevo: worker.pid || null,
            completadaEn: new Date().toISOString(),
            mensaje: `Windows liberó ${estadoWorker.puerto || 'el puerto'} y la báscula volvió a conectarse automáticamente.`
          }
        : recuperacionAnterior;
      this.ultimoEstado = {
        ...estadoWorker,
        procesoId: this.procesoId,
        procesoAuxiliarId: worker.pid || null,
        fase,
        ultimaRecuperacion: recuperacion
      };
      this.emit('estado', this.estado());
      if (estadoWorker.conectado) {
        this.intentosError31 = 0;
      } else if (estadoWorker.ultimoError?.codigo === 'dispositivo-no-listo') {
        this.intentosError31 += 1;
        this.programarRecuperacionAutomatica();
      } else {
        this.intentosError31 = 0;
      }
      return;
    }
    if (mensaje.tipo === 'respuesta') {
      const pendiente = this.pendientes.get(mensaje.payload?.id);
      if (!pendiente) return;
      this.pendientes.delete(mensaje.payload.id);
      clearTimeout(pendiente.timer);
      if (mensaje.payload.ok) pendiente.resolve(mensaje.payload.resultado);
      else pendiente.reject(new Error(mensaje.payload.error || 'El lector no respondió correctamente.'));
      return;
    }
    if (mensaje.tipo === 'worker:error') {
      this.ultimoEstado = {
        ...this.ultimoEstado,
        fase: 'error-lector',
        conectado: false,
        ultimoError: { codigo: 'error-lector', mensaje: 'El lector de la báscula se detuvo inesperadamente.', detalle: mensaje.payload?.mensaje || '' }
      };
      this.emit('estado', this.estado());
    }
  }

  alSalirWorker(worker, codigo, senal) {
    if (this.worker !== worker) return;
    this.worker = null;
    this.workerListo = false;
    for (const pendiente of this.pendientes.values()) {
      clearTimeout(pendiente.timer);
      pendiente.reject(new Error('El lector de la báscula terminó antes de responder.'));
    }
    this.pendientes.clear();
    this.ultimoEstado = {
      ...this.ultimoEstado,
      procesoAuxiliarId: null,
      conectado: false,
      puerto: null,
      fase: this.detenido ? 'detenido' : 'reiniciando-lector'
    };
    this.emit('estado', this.estado());
    if (!this.detenido && !this.reiniciando) {
      setTimeout(() => this.crearWorker(), 750);
    }
  }

  actualizarFase(fase) {
    this.ultimoEstado = { ...this.ultimoEstado, fase };
    this.emit('estado', this.estado());
  }

  enviar(comando, timeout = TIEMPO_RESPUESTA_MS) {
    const worker = this.worker;
    if (!worker || !worker.connected) return Promise.reject(new Error('El lector de la báscula no está disponible.'));
    const id = ++this.secuencia;
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        this.pendientes.delete(id);
        reject(new Error(`El lector no respondió al comando ${comando}.`));
      }, timeout);
      this.pendientes.set(id, { resolve, reject, timer });
      worker.send({ id, comando }, (error) => {
        if (!error) return;
        const pendiente = this.pendientes.get(id);
        if (!pendiente) return;
        this.pendientes.delete(id);
        clearTimeout(timer);
        reject(error);
      });
    });
  }

  async terminarWorker() {
    const worker = this.worker;
    if (!worker) return { cerrado: true, pid: null, forzado: false };
    const pid = worker.pid || null;
    this.actualizarFase('cerrando-lector');
    let forzado = false;
    const salida = new Promise((resolve) => worker.once('exit', () => resolve(true)));
    await this.enviar('cerrar', 1800).catch(() => {});
    const salio = await Promise.race([
      salida,
      new Promise((resolve) => setTimeout(() => resolve(false), TIEMPO_SALIDA_MS))
    ]);
    if (!salio && worker.exitCode === null) {
      forzado = true;
      worker.kill('SIGKILL');
      await Promise.race([salida, new Promise((resolve) => setTimeout(resolve, 1000))]);
    }
    if (this.worker === worker) this.worker = null;
    return { cerrado: worker.exitCode !== null || worker.killed, pid, forzado };
  }

  async detener() {
    this.detenido = true;
    if (this.temporizadorRecuperacion) clearTimeout(this.temporizadorRecuperacion);
    this.temporizadorRecuperacion = null;
    const resultado = await this.terminarWorker();
    this.ultimoEstado = { ...this.ultimoEstado, fase: 'detenido', procesoAuxiliarId: null, conectado: false, puerto: null };
    this.emit('estado', this.estado());
    return resultado;
  }

  programarRecuperacionAutomatica() {
    if (this.detenido || this.reiniciando || this.recuperacionAutomaticaIntentada
      || this.temporizadorRecuperacion || !this.recuperarDispositivo
      || this.intentosError31 < INTENTOS_ERROR_31_ANTES_RECUPERAR) return;
    this.recuperacionAutomaticaIntentada = true;
    this.registrar('warn', 'El código 31 persistió; se iniciará una recuperación única del CH340.', { intentos: this.intentosError31 });
    this.temporizadorRecuperacion = setTimeout(() => {
      this.temporizadorRecuperacion = null;
      this.reconectar({ forzarRecuperacion: true, automatico: true }).catch((error) => {
        this.registrar('error', 'La recuperación automática del CH340 no pudo completarse.', error.message);
      });
    }, 500);
  }

  async esperarIdentidadControlador(timeout = 6000) {
    if (this.ultimoEstado.controlador?.instanciaId) return this.ultimoEstado.controlador;
    return new Promise((resolve) => {
      let terminado = false;
      const finalizar = () => {
        if (terminado) return;
        terminado = true;
        clearTimeout(timer);
        this.removeListener('estado', alCambiar);
        resolve(this.ultimoEstado.controlador || null);
      };
      const alCambiar = (estado) => {
        if (estado?.controlador?.instanciaId) finalizar();
      };
      const timer = setTimeout(finalizar, timeout);
      this.on('estado', alCambiar);
    });
  }

  async reconectar(opciones = {}) {
    if (this.reiniciando) return this.reiniciando;
    // Marcar el reinicio antes del primer await evita que el evento exit del
    // lector programe otro proceso en paralelo.
    this.reiniciando = Promise.resolve();
    const operacion = (async () => {
      this.detenido = false;
      const controladorAntesDelCierre = await this.esperarIdentidadControlador();
      const anterior = await this.terminarWorker();
      let recuperacionDispositivo = null;
      if (!opciones.forzarRecuperacion) {
        this.actualizarFase('esperando-liberacion');
        await new Promise((resolve) => setTimeout(resolve, 1200));
        this.crearWorker();
        await this.esperarConexion(TIEMPO_RECONEXION_MS);
      }
      if ((opciones.forzarRecuperacion || !this.ultimoEstado.conectado)
        && this.ultimoEstado.ultimoError?.codigo === 'dispositivo-no-listo'
        && this.recuperarDispositivo) {
        await this.terminarWorker();
        this.actualizarFase('reiniciando-dispositivo-ch340');
        recuperacionDispositivo = await this.recuperarDispositivo(controladorAntesDelCierre || this.ultimoEstado.controlador || {});
        this.actualizarFase('esperando-com3');
        if (recuperacionDispositivo?.ok) {
          await new Promise((resolve) => setTimeout(resolve, ESPERA_REENUMERACION_MS));
        }
        this.crearWorker();
        await this.esperarLecturaValida(TIEMPO_RECONEXION_MS);
      } else if (!this.worker) {
        this.crearWorker();
      }
      const ok = Boolean(this.ultimoEstado.conectado && this.ultimoEstado.ultimoPeso);
      const recuperacion = {
        ok,
        pidAnterior: anterior.pid,
        cierreForzado: anterior.forzado,
        pidNuevo: this.worker?.pid || null,
        reinicioDispositivo: recuperacionDispositivo,
        automatica: Boolean(opciones.automatico),
        mensaje: ok
          ? (recuperacionDispositivo
              ? 'Windows reinició el CH340, COM3 volvió a abrirse y se recibió una lectura válida.'
              : 'El lector anterior terminó, COM3 volvió a abrirse y se recibió una lectura válida.')
          : (recuperacionDispositivo?.cancelado
              ? 'La autorización de Windows fue cancelada; ESTRELLA seguirá intentando automáticamente.'
              : 'El lector fue reemplazado, pero Windows todavía rechazó la apertura o no llegó una lectura válida de COM3.')
      };
      this.ultimoEstado = { ...this.ultimoEstado, ultimaRecuperacion: recuperacion };
      this.emit('estado', this.estado());
      return {
        ok,
        puerto: ok ? this.ultimoEstado.puerto : null,
        intentos: null,
        error: ok ? null : (this.ultimoEstado.ultimoError?.mensaje || 'No se pudo abrir COM3.'),
        estado: this.estado()
      };
    })();
    this.reiniciando = operacion;
    try {
      return await operacion;
    } finally {
      this.reiniciando = null;
    }
  }

  esperarConexion(timeout = TIEMPO_RECONEXION_MS) {
    if (this.ultimoEstado.conectado) return Promise.resolve(true);
    return new Promise((resolve) => {
      let terminado = false;
      const finalizar = (ok) => {
        if (terminado) return;
        terminado = true;
        clearTimeout(timer);
        this.removeListener('estado', alCambiarEstado);
        resolve(ok);
      };
      const alCambiarEstado = (estado) => {
        if (estado?.conectado) finalizar(true);
        else if (estado?.ultimoError?.codigo === 'libreria-no-disponible') finalizar(false);
      };
      const timer = setTimeout(() => finalizar(Boolean(this.ultimoEstado.conectado)), timeout);
      this.on('estado', alCambiarEstado);
    });
  }

  esperarLecturaValida(timeout = TIEMPO_RECONEXION_MS) {
    if (this.ultimoEstado.conectado && this.ultimoEstado.ultimoPeso) return Promise.resolve(true);
    return new Promise((resolve) => {
      let terminado = false;
      const finalizar = (ok) => {
        if (terminado) return;
        terminado = true;
        clearTimeout(timer);
        this.removeListener('estado', alCambiarEstado);
        this.removeListener('peso', alRecibirPeso);
        resolve(ok);
      };
      const alCambiarEstado = (estado) => {
        if (estado?.ultimoError?.codigo === 'libreria-no-disponible') finalizar(false);
      };
      const alRecibirPeso = () => finalizar(true);
      const timer = setTimeout(() => finalizar(false), timeout);
      this.on('estado', alCambiarEstado);
      this.on('peso', alRecibirPeso);
    });
  }

  async listarPuertos() {
    const diagnostico = await this.diagnostico();
    return diagnostico.puertos || [];
  }

  async diagnostico() {
    try {
      const diagnosticoWorker = await this.enviar('diagnostico');
      this.ultimoDiagnostico = {
        ...diagnosticoWorker,
        procesoId: this.procesoId,
        procesoAuxiliarId: this.worker?.pid || null,
        fase: this.ultimoEstado.fase,
        ultimaRecuperacion: this.ultimoEstado.ultimaRecuperacion || null
      };
    } catch (_) {
      this.ultimoDiagnostico = { ...this.ultimoDiagnostico, ...this.estado() };
    }
    return this.ultimoDiagnostico;
  }

  estado() {
    return { ...this.ultimoEstado };
  }

  tarar() {
    return this.enviar('tarar');
  }

  quitarTara() {
    return this.enviar('quitar-tara');
  }

  registrar(nivel, mensaje, extra = null) {
    const entrada = { ts: new Date().toISOString(), nivel, mensaje, extra: extra || undefined };
    const registros = [...(this.ultimoDiagnostico.registros || []), entrada].slice(-80);
    this.ultimoDiagnostico = { ...this.ultimoDiagnostico, registros };
  }
}

module.exports = { BasculaSupervisor };
