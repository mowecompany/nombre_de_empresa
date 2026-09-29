'use strict';

const { EventEmitter } = require('events');
const net = require('net');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { BasculaSupervisor } = require('./BasculaSupervisor');

const CANAL_WINDOWS = '\\\\.\\pipe\\autoservicio-mi-estrella-bascula-v1';
const CANAL_UNIX = path.join(os.tmpdir(), 'autoservicio-mi-estrella-bascula-v1.sock');
const TIEMPO_RPC_MS = 25000;
const RETRASO_ELECCION_MS = 900;

/**
 * Garantiza un solo propietario de la báscula entre la versión instalada,
 * portable y copias de prueba. Las copias cliente reciben los mismos eventos
 * y envían sus comandos al propietario mediante un canal exclusivamente local.
 */
class BasculaCoordinator extends EventEmitter {
  constructor(opciones = {}) {
    super();
    this.opciones = opciones;
    this.procesoId = opciones.procesoId || process.pid;
    this.canal = opciones.canal || (process.platform === 'win32' ? CANAL_WINDOWS : CANAL_UNIX);
    this.supervisor = null;
    this.servidor = null;
    this.cliente = null;
    this.clientes = new Set();
    this.bufferCliente = '';
    this.secuencia = 0;
    this.pendientes = new Map();
    this.iniciado = false;
    this.detenido = true;
    this.eleccionEnCurso = false;
    this.reintento = null;
    this.modo = 'iniciando';
    this.propietarioId = null;
    this.registrosCoordinacion = [];
    this.ultimoEstado = {
      procesoId: this.procesoId,
      procesoPropietarioId: null,
      procesoAuxiliarId: null,
      modoBascula: 'iniciando',
      fase: 'coordinando',
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
    if (this.iniciado && !this.detenido) return;
    this.iniciado = true;
    this.detenido = false;
    this.elegirPropietario();
  }

  async elegirPropietario() {
    if (this.detenido || this.eleccionEnCurso || this.servidor || this.cliente) return;
    this.eleccionEnCurso = true;
    const servidor = net.createServer((socket) => this.aceptarCliente(socket));
    let resuelto = false;

    servidor.once('listening', () => {
      if (resuelto || this.detenido) return;
      resuelto = true;
      this.eleccionEnCurso = false;
      this.servidor = servidor;
      this.convertirseEnPropietario();
    });
    servidor.once('error', (error) => {
      if (resuelto) return;
      resuelto = true;
      this.eleccionEnCurso = false;
      try { servidor.close(); } catch (_) {}
      if (error.code === 'EADDRINUSE') this.conectarConPropietario();
      else this.programarEleccion();
    });
    servidor.listen(this.canal);
  }

  convertirseEnPropietario() {
    if (this.detenido) return;
    this.modo = 'propietaria';
    this.propietarioId = this.procesoId;
    this.registrarCoordinacion('info', `Esta copia tomó el control exclusivo de la báscula (PID ${this.procesoId}).`);
    const supervisor = new BasculaSupervisor({
      puertoForzado: this.opciones.puertoForzado || null,
      procesoId: this.procesoId,
      recuperarDispositivo: this.opciones.recuperarDispositivo
    });
    this.supervisor = supervisor;
    supervisor.on('peso', (payload) => this.publicar('peso', payload));
    supervisor.on('trama', (payload) => this.publicar('trama', payload));
    supervisor.on('estado', (payload) => {
      this.ultimoEstado = this.decorar(payload);
      this.publicar('estado', this.ultimoEstado);
    });
    supervisor.iniciar();
    this.ultimoEstado = this.decorar(supervisor.estado());
    this.publicar('estado', this.ultimoEstado);
  }

  conectarConPropietario() {
    if (this.detenido || this.cliente) return;
    const socket = net.createConnection(this.canal);
    this.cliente = socket;
    this.bufferCliente = '';
    socket.setEncoding('utf8');
    socket.on('connect', () => {
      this.modo = 'cliente-compartido';
      this.registrarCoordinacion('info', 'Esta copia usará el lector compartido de otra copia de ESTRELLA.');
      this.enviarSocket(socket, { tipo: 'hola', procesoId: this.procesoId });
    });
    socket.on('data', (data) => this.procesarDatosCliente(data));
    socket.once('error', () => {});
    socket.once('close', () => {
      if (this.cliente !== socket) return;
      this.cliente = null;
      this.propietarioId = null;
      this.modo = 'iniciando';
      this.registrarCoordinacion('warn', 'La copia propietaria terminó; esta copia intentará tomar el control de la báscula.');
      this.rechazarPendientes('La copia propietaria de la báscula dejó de responder.');
      this.ultimoEstado = this.decorar({ ...this.ultimoEstado, conectado: false, puerto: null, fase: 'tomando-control' });
      this.emit('estado', this.estado());
      this.programarEleccion();
    });
  }

  programarEleccion() {
    if (this.detenido || this.reintento) return;
    this.reintento = setTimeout(() => {
      this.reintento = null;
      if (process.platform !== 'win32' && !this.cliente && !this.servidor) {
        try { fs.unlinkSync(this.canal); } catch (_) {}
      }
      this.elegirPropietario();
    }, RETRASO_ELECCION_MS);
  }

  aceptarCliente(socket) {
    this.clientes.add(socket);
    let buffer = '';
    socket.setEncoding('utf8');
    socket.on('data', (data) => {
      buffer += data;
      const lineas = buffer.split('\n');
      buffer = lineas.pop() || '';
      for (const linea of lineas) {
        if (!linea.trim()) continue;
        try { this.procesarSolicitud(socket, JSON.parse(linea)); } catch (_) {}
      }
    });
    socket.on('error', () => {});
    socket.on('close', () => this.clientes.delete(socket));
    this.enviarSocket(socket, { tipo: 'estado', payload: this.estadoPropietario() });
  }

  registrarCoordinacion(nivel, mensaje) {
    this.registrosCoordinacion.push({ ts: new Date().toISOString(), nivel, mensaje });
    if (this.registrosCoordinacion.length > 40) this.registrosCoordinacion.shift();
  }

  async procesarSolicitud(socket, mensaje = {}) {
    if (mensaje.tipo === 'hola') {
      this.enviarSocket(socket, { tipo: 'estado', payload: this.estadoPropietario() });
      return;
    }
    if (mensaje.tipo !== 'solicitud' || !mensaje.id) return;
    try {
      const resultado = await this.ejecutarLocal(mensaje.comando);
      this.enviarSocket(socket, { tipo: 'respuesta', id: mensaje.id, ok: true, resultado });
    } catch (error) {
      this.enviarSocket(socket, { tipo: 'respuesta', id: mensaje.id, ok: false, error: error?.message || String(error) });
    }
  }

  procesarDatosCliente(data) {
    this.bufferCliente += data;
    const lineas = this.bufferCliente.split('\n');
    this.bufferCliente = lineas.pop() || '';
    for (const linea of lineas) {
      if (!linea.trim()) continue;
      let mensaje;
      try { mensaje = JSON.parse(linea); } catch (_) { continue; }
      if (mensaje.tipo === 'estado') {
        this.propietarioId = mensaje.payload?.procesoPropietarioId || mensaje.payload?.procesoId || null;
        this.ultimoEstado = this.decorar(mensaje.payload || {});
        this.emit('estado', this.estado());
      } else if (mensaje.tipo === 'peso' || mensaje.tipo === 'trama') {
        this.emit(mensaje.tipo, mensaje.payload);
      } else if (mensaje.tipo === 'respuesta') {
        const pendiente = this.pendientes.get(mensaje.id);
        if (!pendiente) continue;
        this.pendientes.delete(mensaje.id);
        clearTimeout(pendiente.timer);
        if (mensaje.ok) pendiente.resolve(this.decorarResultado(mensaje.resultado));
        else pendiente.reject(new Error(mensaje.error || 'La copia propietaria no respondió.'));
      } else if (mensaje.tipo === 'diagnostico') {
        this.ultimoDiagnostico = this.decorar(mensaje.payload || {});
      }
    }
  }

  publicar(tipo, payload) {
    if (tipo === 'estado') this.ultimoEstado = this.decorar(payload || {});
    this.emit(tipo, tipo === 'estado' ? this.estado() : payload);
    const mensaje = { tipo, payload: tipo === 'estado' ? this.estadoPropietario() : payload };
    for (const socket of this.clientes) this.enviarSocket(socket, mensaje);
  }

  enviarSocket(socket, mensaje) {
    if (!socket || socket.destroyed) return;
    try { socket.write(`${JSON.stringify(mensaje)}\n`); } catch (_) {}
  }

  decorar(estado = {}) {
    const propietario = this.modo === 'propietaria'
      ? this.procesoId
      : (estado.procesoPropietarioId || estado.procesoId || this.propietarioId);
    return {
      ...estado,
      procesoId: this.procesoId,
      procesoPropietarioId: propietario || null,
      modoBascula: this.modo
    };
  }

  estadoPropietario() {
    const base = this.supervisor ? this.supervisor.estado() : this.ultimoEstado;
    return { ...base, procesoPropietarioId: this.procesoId, modoBascula: 'propietaria' };
  }

  estado() {
    return this.decorar(this.ultimoEstado);
  }

  decorarResultado(resultado) {
    if (!resultado || typeof resultado !== 'object') return resultado;
    return resultado.estado ? { ...resultado, estado: this.decorar(resultado.estado) } : resultado;
  }

  solicitar(comando) {
    const socket = this.cliente;
    if (!socket || socket.destroyed || !socket.writable) {
      return Promise.reject(new Error('Esperando que una copia de ESTRELLA tome el control de la báscula.'));
    }
    const id = `${this.procesoId}-${++this.secuencia}`;
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        this.pendientes.delete(id);
        reject(new Error('La copia propietaria no respondió a tiempo.'));
      }, TIEMPO_RPC_MS);
      this.pendientes.set(id, { resolve, reject, timer });
      this.enviarSocket(socket, { tipo: 'solicitud', id, comando });
    });
  }

  rechazarPendientes(mensaje) {
    for (const pendiente of this.pendientes.values()) {
      clearTimeout(pendiente.timer);
      pendiente.reject(new Error(mensaje));
    }
    this.pendientes.clear();
  }

  async ejecutarLocal(comando) {
    if (!this.supervisor) throw new Error('Esta copia todavía no controla la báscula.');
    if (comando === 'estado') return this.estadoPropietario();
    if (comando === 'diagnostico') return this.decorar(await this.supervisor.diagnostico());
    if (comando === 'reconectar') return this.decorarResultado(await this.supervisor.reconectar());
    if (comando === 'tarar') return this.supervisor.tarar();
    if (comando === 'quitar-tara') return this.supervisor.quitarTara();
    throw new Error(`Comando de báscula no reconocido: ${comando}`);
  }

  async diagnostico() {
    const diagnostico = this.supervisor
      ? await this.supervisor.diagnostico()
      : await this.solicitar('diagnostico');
    const base = this.decorar(diagnostico || {});
    this.ultimoDiagnostico = {
      ...base,
      registros: [...this.registrosCoordinacion, ...(base.registros || [])]
        .sort((a, b) => String(a.ts || '').localeCompare(String(b.ts || '')))
        .slice(-80)
    };
    return this.ultimoDiagnostico;
  }

  async reconectar() {
    const resultado = this.supervisor
      ? await this.supervisor.reconectar()
      : await this.solicitar('reconectar');
    return this.decorarResultado(resultado);
  }
  tarar() { return this.supervisor ? this.supervisor.tarar() : this.solicitar('tarar'); }
  quitarTara() { return this.supervisor ? this.supervisor.quitarTara() : this.solicitar('quitar-tara'); }

  registrar(nivel, mensaje, extra = null) {
    if (this.supervisor) this.supervisor.registrar(nivel, mensaje, extra);
  }

  async detener() {
    this.detenido = true;
    if (this.reintento) clearTimeout(this.reintento);
    this.reintento = null;
    this.rechazarPendientes('ESTRELLA se está cerrando.');
    if (this.cliente) {
      this.cliente.destroy();
      this.cliente = null;
    }
    const supervisor = this.supervisor;
    this.supervisor = null;
    const cierreSupervisor = supervisor ? await supervisor.detener() : null;
    for (const socket of this.clientes) socket.destroy();
    this.clientes.clear();
    if (this.servidor) {
      const servidor = this.servidor;
      this.servidor = null;
      await new Promise((resolve) => servidor.close(() => resolve()));
    }
    if (process.platform !== 'win32') {
      try { fs.unlinkSync(this.canal); } catch (_) {}
    }
    this.modo = 'detenida';
    this.ultimoEstado = this.decorar({ ...this.ultimoEstado, fase: 'detenido', conectado: false, puerto: null });
    return { cerrado: true, propietario: Boolean(supervisor), lector: cierreSupervisor };
  }
}

module.exports = { BasculaCoordinator, CANAL_WINDOWS };