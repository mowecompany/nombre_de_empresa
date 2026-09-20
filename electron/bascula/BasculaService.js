'use strict';

const { EventEmitter } = require('events');
const { parsearTramaAcs30 } = require('./parserAcs30');

let SerialPort = null;
let ReadlineParser = null;
let errorCargaSerial = null;

try {
  ({ SerialPort } = require('serialport'));
  ({ ReadlineParser } = require('@serialport/parser-readline'));
} catch (error) {
  errorCargaSerial = error;
}

const CONFIG_POR_DEFECTO = {
  baudRate: 9600,
  dataBits: 8,
  parity: 'none',
  stopBits: 1
};

const PUERTO_RESPALDO = 'COM3';
const INTERVALO_BUSQUEDA_MS = 3000;
const MAX_REGISTROS = 200;

/**
 * Único responsable de la comunicación serial con la báscula ACS-30.
 * Vive en el proceso principal de Electron: recargar o cambiar de página en
 * el frontend no afecta al puerto.
 */
class BasculaService extends EventEmitter {
  constructor(opciones = {}) {
    super();
    this.config = { ...CONFIG_POR_DEFECTO, ...(opciones.config || {}) };
    this.puertoForzado = opciones.puertoForzado || null;
    this.procesoId = opciones.procesoId || process.pid;
    this.port = null;
    this.parser = null;
    this.abriendo = false;
    this.reiniciando = false;
    this.detenido = true;
    this.temporizador = null;
    this.puertosDetectados = [];
    this.adaptadorDetectado = null;
    this.ultimaTrama = null;
    this.ultimoPeso = null;
    this.registros = [];
    this.ultimoError = null;
    this.ultimaRecuperacion = null;
    this.tara = 0;
    this.disponible = Boolean(SerialPort);
    this.errorLibreria = errorCargaSerial ? (errorCargaSerial.message || String(errorCargaSerial)) : null;
  }

  registrar(nivel, mensaje, extra = null) {
    const entrada = {
      ts: new Date().toISOString(),
      nivel,
      mensaje,
      extra: extra || undefined
    };
    this.registros.push(entrada);
    if (this.registros.length > MAX_REGISTROS) this.registros.shift();
    const etiqueta = `[BASCULA][${nivel.toUpperCase()}]`;
    if (nivel === 'error') console.error(etiqueta, mensaje, extra || '');
    else console.info(etiqueta, mensaje, extra || '');
    this.emit('registro', entrada);
  }

  iniciar() {
    if (!this.disponible) {
      this.registrar('error', 'La librería serialport no está disponible. Ejecute npm install dentro de ESTRELLA.', this.errorLibreria);
      this.emitirEstado();
      return;
    }
    if (!this.detenido) return;
    this.detenido = false;
    this.registrar('info', 'Servicio de báscula iniciado');
    this.bucle();
    this.temporizador = setInterval(() => this.bucle(), INTERVALO_BUSQUEDA_MS);
  }

  async detener() {
    this.detenido = true;
    this.reiniciando = false;
    this.abriendo = false;
    if (this.temporizador) {
      clearInterval(this.temporizador);
      this.temporizador = null;
    }
    await this.forzarCierre('servicio detenido');
    this.abriendo = false;
    this.reiniciando = false;
    this.emitirEstado();
  }

  async bucle() {
    if (this.detenido || this.abriendo || this.estaConectada()) return;
    try {
      await this.conectar();
    } catch (error) {
      // conectar() ya registra el error; el intervalo reintenta.
    }
  }

  estaConectada() {
    return Boolean(this.port && this.port.isOpen);
  }

  async listarPuertos() {
    if (!this.disponible) return [];
    const puertos = await SerialPort.list().catch((error) => {
      this.registrar('error', 'No se pudieron listar los puertos', error.message);
      return [];
    });
    this.puertosDetectados = puertos.map((item) => ({
      path: item.path,
      manufacturer: item.manufacturer || '',
      vendorId: (item.vendorId || '').toLowerCase(),
      productId: (item.productId || '').toLowerCase(),
      serialNumber: item.serialNumber || ''
    }));
    return this.puertosDetectados;
  }

  /** Selecciona el adaptador CH340. COM1 nunca se usa. */
  elegirPuerto(puertos) {
    const candidatos = puertos.filter((p) => !/^COM1$/i.test(p.path));

    if (this.puertoForzado) {
      const forzado = candidatos.find((p) => p.path.toUpperCase() === String(this.puertoForzado).toUpperCase());
      if (forzado) return { ...forzado, motivo: 'puerto forzado por el usuario' };
    }

    const porVid = candidatos.find((p) => p.vendorId === '1a86');
    if (porVid) return { ...porVid, motivo: 'VID 1a86 (CH340)' };

    const porNombre = candidatos.find((p) => /CH340|USB-SERIAL|wch\.cn|USB2\.0-Ser/i.test(`${p.path} ${p.manufacturer}`));
    if (porNombre) return { ...porNombre, motivo: 'descripción del adaptador' };

    const respaldo = candidatos.find((p) => p.path.toUpperCase() === PUERTO_RESPALDO);
    if (respaldo) return { ...respaldo, motivo: 'respaldo COM3' };

    return null;
  }

  async conectar() {
    if (!this.disponible) throw new Error('serialport no disponible');
    if (this.abriendo) return;
    if (this.estaConectada()) return;

    this.abriendo = true;
    try {
      const puertos = await this.listarPuertos();
      const elegido = this.elegirPuerto(puertos);
      if (!elegido) {
        if (this.adaptadorDetectado) {
          this.adaptadorDetectado = null;
          this.registrar('warn', 'Adaptador CH340 no detectado');
          this.emitirEstado();
        }
        return;
      }

      this.adaptadorDetectado = elegido;
      this.registrar('info', `Abriendo ${elegido.path} (${elegido.motivo})`, this.config);

      const port = new SerialPort({
        path: elegido.path,
        baudRate: Number(this.config.baudRate),
        dataBits: Number(this.config.dataBits),
        stopBits: Number(this.config.stopBits),
        parity: String(this.config.parity || 'none'),
        autoOpen: false
      });

      await new Promise((resolve, reject) => {
        const alFallar = (error) => {
          port.removeListener('error', alFallar);
          reject(error);
        };
        port.once('error', alFallar);
        port.open((error) => {
          port.removeListener('error', alFallar);
          if (error) reject(error);
          else resolve();
        });
      });

      // Si el cierre de ESTRELLA comenzó mientras Windows abría el COM,
      // no publicar el manejador: cerrarlo antes de que el proceso termine.
      if (this.detenido) {
        await new Promise((resolve) => port.close(() => resolve()));
        this.registrar('info', `Apertura de ${elegido.path} cancelada por cierre de la aplicación`);
        return;
      }

      this.port = port;
      this.parser = port.pipe(new ReadlineParser({ delimiter: '\n', includeDelimiter: false }));
      this.parser.on('data', (linea) => this.procesarTrama(linea));
      // Algunas balanzas envían sin salto de línea: el buffer crudo es el respaldo.
      port.on('data', (buffer) => {
        this.ultimaTrama = {
          texto: buffer.toString('latin1'),
          hex: buffer.toString('hex'),
          ts: Date.now()
        };
      });

      port.on('close', () => {
        if (this.port !== port) return;
        this.port = null;
        this.parser = null;
        this.registrar('warn', 'Puerto cerrado / báscula desconectada');
        this.emitirEstado();
      });

      port.on('error', (error) => {
        this.registrar('error', 'Error en el puerto serial', error.message);
        if (port.isOpen) {
          port.close(() => {});
        } else if (this.port === port) {
          this.port = null;
          this.parser = null;
          this.emitirEstado();
        }
      });

      this.ultimoError = null;
      this.registrar('info', `Puerto abierto correctamente en ${elegido.path}`);
      this.emitirEstado();
    } catch (error) {
      this.port = null;
      this.parser = null;
      const mensaje = String(error?.message || error);
      if (/Access denied|Unknown error code 31|already open|Resource busy/i.test(mensaje)) {
        this.ultimoError = {
          codigo: 'puerto-ocupado',
          mensaje: 'El puerto de la báscula está ocupado por otro programa (otra copia de ESTRELLA, el software de la balanza o un monitor serial). Ciérrelo y pulse Reintentar.',
          detalle: mensaje
        };
        this.registrar('error', this.ultimoError.mensaje, mensaje);
      } else {
        this.ultimoError = {
          codigo: 'apertura-fallida',
          mensaje: 'No se pudo abrir el puerto de la báscula. Revise el cable USB del adaptador CH340 y pulse Reintentar.',
          detalle: mensaje
        };
        this.registrar('error', this.ultimoError.mensaje, mensaje);
      }
      this.emitirEstado();
      throw error;
    } finally {
      this.abriendo = false;
    }
  }

  async cerrarPuerto(motivo = '') {
    const port = this.port;
    this.port = null;
    this.parser = null;
    if (!port) return;
    try {
      if (port.isOpen) {
        await Promise.race([
          new Promise((resolve) => port.close(() => resolve())),
          this.esperar(2000)
        ]);
        if (port.isOpen && typeof port.destroy === 'function') port.destroy();
      }
    } catch (error) {
      this.registrar('warn', 'Cierre de puerto con incidencia', error.message);
    }
    this.registrar('info', `Puerto liberado ${motivo ? `(${motivo})` : ''}`.trim());
    this.emitirEstado();
  }

esperar(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /** Cierra el puerto pase lo que pase y suelta el manejador. */
  async forzarCierre(motivo = 'reinicio solicitado') {
    const port = this.port;
    this.port = null;
    const parser = this.parser;
    this.parser = null;
    if (!port) {
      this.registrar('info', `No había un puerto propio que liberar (${motivo})`);
      return { cerrado: true, teniaPuerto: false };
    }
    try {
      if (parser && typeof parser.removeAllListeners === 'function') parser.removeAllListeners();
      port.removeAllListeners('data');
      port.removeAllListeners('close');
      port.removeAllListeners('error');
      port.on('error', () => {});
      if (port.isOpen) {
        await Promise.race([
          new Promise((resolve) => port.close(() => resolve())),
          this.esperar(1500)
        ]);
      }
      if (port.isOpen && typeof port.destroy === 'function') {
        port.destroy();
      }
      if (port.isOpen) throw new Error('Windows no confirmó el cierre del puerto dentro del tiempo esperado.');
    } catch (error) {
      this.registrar('warn', 'Cierre forzado con incidencia', error.message);
      return { cerrado: false, teniaPuerto: true, error: error.message };
    }
    this.registrar('info', `Puerto liberado (${motivo})`);
    return { cerrado: true, teniaPuerto: true };
  }

  /** Espera a que Windows libere realmente el COM antes de reabrirlo. */
  async esperarPuertoLibre(ruta, msMax = 3000) {
    const limite = Date.now() + msMax;
    while (Date.now() < limite) {
      await this.esperar(250);
      const puertos = await this.listarPuertos();
      if (!ruta) return true;
      const sigue = puertos.some((p) => p.path.toUpperCase() === String(ruta).toUpperCase());
      if (sigue) return true;
    }
    return false;
  }

  /**
   * Reinicio completo del puerto: cierra a la fuerza, espera la liberación real
   * del sistema, vuelve a escanear y reintenta abrir varias veces.
   */
  async reiniciarPuerto() {
    if (!this.disponible) {
      return {
        ok: false,
        puerto: null,
        intentos: 0,
        error: 'La librería serial no está instalada. Ejecute npm install dentro de ESTRELLA.',
        estado: this.estado()
      };
    }

    if (this.reiniciando) {
      return { ok: this.estaConectada(), puerto: this.port ? this.port.path : null, intentos: 0, error: 'Ya hay un reinicio en curso.', estado: this.estado() };
    }
    this.reiniciando = true;

    // 1. Detener el ciclo automático para que no compita por el puerto.
    if (this.temporizador) {
      clearInterval(this.temporizador);
      this.temporizador = null;
    }
    this.detenido = true;
    // 2. Liberar una bandera de apertura que haya quedado trabada.
    this.abriendo = false;

    const rutaAnterior = this.port ? this.port.path : (this.adaptadorDetectado ? this.adaptadorDetectado.path : null);
    this.registrar('info', `Reinicio de puerto solicitado${rutaAnterior ? ` (${rutaAnterior})` : ''}`);

    let intentos = 0;
    let ultimoFallo = null;

    try {
      // 3. Cierre forzado.
      await this.forzarCierre('reinicio solicitado');
      // 4. Esperar a que el sistema lo libere de verdad.
      await this.esperarPuertoLibre(rutaAnterior, 3000);
      // 5. Empezar de cero: sin error viejo ni adaptador cacheado.
      this.ultimoError = null;
      this.adaptadorDetectado = null;
      this.emitirEstado();

      // 6 y 7. Reescanear y reintentar con esperas crecientes.
      for (let i = 1; i <= 5; i += 1) {
        intentos = i;
        try {
          await this.conectar();
          if (this.estaConectada()) {
            this.registrar('info', `Báscula reconectada en ${this.port.path} (intento ${i})`);
            break;
          }
          ultimoFallo = 'No se detecta el adaptador de la báscula.';
          this.registrar('warn', `Intento ${i}: adaptador no detectado`);
        } catch (error) {
          ultimoFallo = this.ultimoError?.mensaje || String(error?.message || error);
          this.registrar('warn', `Intento ${i} fallido`, ultimoFallo);
        }
        if (i < 5) await this.esperar(300 * i);
      }
    } finally {
      this.reiniciando = false;
      this.abriendo = false;
      // 8. El ciclo automático queda siempre activo: si aparece después, entra sola.
      this.detenido = true;
      this.iniciar();
    }

    const ok = this.estaConectada();
    return {
      ok,
      puerto: ok ? this.port.path : null,
      intentos,
      error: ok ? null : (ultimoFallo || 'No se pudo reconectar con la báscula.'),
      estado: this.estado()
    };
  }

  async reconectar() {
    return this.reiniciarPuerto();
  }

  registrarRecuperacion(resultado) {
    this.ultimaRecuperacion = resultado || null;
    if (resultado?.mensaje) {
      this.registrar(resultado.ok === false ? 'warn' : 'info', resultado.mensaje, resultado.pids || null);
    }
    this.emitirEstado();
  }

  procesarTrama(tramaCruda) {
    const texto = String(tramaCruda).replace(/\r/g, '');
    this.ultimaTrama = { texto, hex: Buffer.from(texto, 'latin1').toString('hex'), ts: Date.now() };
    const resultado = parsearTramaAcs30(texto);
    this.emit('trama', { ...this.ultimaTrama, resultado });

    if (!resultado.reconocido) {
      this.registrar('warn', 'Trama no reconocida', texto);
      return;
    }

    const pesoNeto = Math.round((resultado.peso - this.tara) * 1000) / 1000;
    this.ultimoPeso = {
      peso: pesoNeto,
      pesoBruto: resultado.peso,
      tara: this.tara,
      unidad: 'kg',
      estable: resultado.estable,
      inferido: resultado.inferido,
      ts: Date.now()
    };
    this.emit('peso', this.ultimoPeso);
  }

  tarar() {
    const bruto = this.ultimoPeso ? this.ultimoPeso.pesoBruto : 0;
    this.tara = bruto;
    this.registrar('info', `Tara aplicada: ${this.tara} kg`);
    if (this.ultimoPeso) {
      this.ultimoPeso = { ...this.ultimoPeso, peso: 0, tara: this.tara, ts: Date.now() };
      this.emit('peso', this.ultimoPeso);
    }
    return { tara: this.tara };
  }

  quitarTara() {
    this.tara = 0;
    this.registrar('info', 'Tara eliminada');
    return { tara: 0 };
  }

  estado() {
    return {
      procesoId: this.procesoId,
      disponible: this.disponible,
      errorLibreria: this.errorLibreria,
      conectado: this.estaConectada(),
      puerto: this.port ? this.port.path : null,
      adaptador: this.adaptadorDetectado,
      config: this.config,
      tara: this.tara,
      ultimoError: this.estaConectada() ? null : this.ultimoError,
      ultimaRecuperacion: this.ultimaRecuperacion,
      ultimoPeso: this.ultimoPeso,
      ultimaTrama: this.ultimaTrama
    };
  }

  diagnostico() {
    return {
      ...this.estado(),
      puertos: this.puertosDetectados,
      registros: this.registros.slice(-80)
    };
  }

  emitirEstado() {
    this.emit('estado', this.estado());
  }
}

module.exports = { BasculaService, CONFIG_POR_DEFECTO };
