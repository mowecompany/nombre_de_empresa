'use strict';

const { BasculaService } = require('./BasculaService');

const puertoForzado = process.env.BASCULA_PUERTO || null;
const service = new BasculaService({ puertoForzado, procesoId: process.pid });
let terminando = false;

function enviar(tipo, payload) {
  if (!process.connected) return;
  try {
    process.send({ tipo, payload });
  } catch (_) {
    // El proceso principal ya cerró el canal.
  }
}

service.on('peso', (payload) => enviar('evento:peso', payload));
service.on('estado', (payload) => enviar('evento:estado', payload));
service.on('trama', (payload) => enviar('evento:trama', payload));

async function responder(id, tarea) {
  try {
    enviar('respuesta', { id, ok: true, resultado: await tarea() });
  } catch (error) {
    enviar('respuesta', { id, ok: false, error: error?.message || String(error) });
  }
}

async function cerrarProceso(id = null) {
  if (terminando) return;
  terminando = true;
  await service.detener().catch(() => {});
  if (id !== null) enviar('respuesta', { id, ok: true, resultado: { cerrado: true } });
  enviar('worker:detenido', { pid: process.pid });
  setImmediate(() => process.exit(0));
}

process.on('message', (mensaje = {}) => {
  const id = mensaje.id;
  switch (mensaje.comando) {
    case 'estado':
      responder(id, async () => service.estado());
      break;
    case 'diagnostico':
      responder(id, async () => {
        await service.listarPuertos();
        return service.diagnostico();
      });
      break;
    case 'tarar':
      responder(id, async () => service.tarar());
      break;
    case 'quitar-tara':
      responder(id, async () => service.quitarTara());
      break;
    case 'cerrar':
      cerrarProceso(id);
      break;
    default:
      responder(id, async () => { throw new Error(`Comando del lector no reconocido: ${mensaje.comando}`); });
  }
});

process.on('disconnect', () => cerrarProceso());
process.on('SIGTERM', () => cerrarProceso());
process.on('SIGINT', () => cerrarProceso());
process.on('uncaughtException', (error) => {
  enviar('worker:error', { mensaje: error?.message || String(error) });
  cerrarProceso();
});

enviar('worker:listo', { pid: process.pid });
service.iniciar();
