'use strict';

/**
 * Parser de la trama real de la báscula ACS-30 (RS-232 / CH340).
 *
 * No inventa el formato: normaliza lo que llega por el puerto y reconoce los
 * formatos habituales de este tipo de balanza. Todo lo que no se reconoce se
 * devuelve con `reconocido: false` para que quede registrado en el diagnóstico.
 *
 * Formatos contemplados:
 *   "ST,GS,+  1.100kg\r\n"     (estable / bruto)
 *   "US,NT,-  0.250kg\r\n"     (inestable / neto)
 *   "wn+ 1.100kg\r\n"          (variante ACS con prefijo wn/un)
 *   "+0001.100 kg"             (relleno con ceros)
 *   "1100 g"                   (gramos)
 *   "01100\r\n"                (solo dígitos: se asumen 3 decimales)
 */

const UNIDADES = [
  { patron: /\bkgs?\b/i, factor: 1, unidad: 'kg' },
  { patron: /\bg\b|\bgr\b|\bgrs\b/i, factor: 0.001, unidad: 'g' },
  { patron: /\blb\b|\blbs\b/i, factor: 0.45359237, unidad: 'lb' },
  { patron: /\boz\b/i, factor: 0.028349523125, unidad: 'oz' }
];

const MAX_KG = 60; // ACS-30 es de 30 kg; se deja margen y se descarta lo absurdo.

function limpiarTrama(texto) {
  return String(texto == null ? '' : texto)
    .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function detectarUnidad(trama) {
  for (const item of UNIDADES) {
    if (item.patron.test(trama)) return item;
  }
  return null;
}

function detectarEstabilidad(trama) {
  if (/\bST\b/i.test(trama) || /\bwn\b/i.test(trama) || /\bS\b\s*[,:]/i.test(trama)) return true;
  if (/\bUS\b/i.test(trama) || /\bun\b/i.test(trama) || /\bMOV\b/i.test(trama)) return false;
  return null; // La báscula no informa estabilidad en esta trama.
}

function redondear(valor, decimales = 3) {
  const factor = Math.pow(10, decimales);
  return Math.round(valor * factor) / factor;
}

/**
 * @param {string|Buffer} tramaCruda
 * @returns {{reconocido:boolean, peso:number|null, unidad:string|null, estable:boolean|null, inferido:boolean, crudo:string, limpio:string, motivo?:string}}
 */
function parsearTramaAcs30(tramaCruda) {
  const crudo = String(tramaCruda == null ? '' : tramaCruda);
  const limpio = limpiarTrama(crudo);

  const base = {
    reconocido: false,
    peso: null,
    unidad: null,
    estable: detectarEstabilidad(limpio),
    inferido: false,
    crudo,
    limpio
  };

  if (!limpio) {
    return { ...base, motivo: 'Trama vacía' };
  }

  const unidadDetectada = detectarUnidad(limpio);

  // 1) Número explícito con separador decimal o signo.
  const coincidencia = limpio.match(/([-+]?)\s*(\d{1,6}(?:[.,]\d{1,4})?)/);
  if (coincidencia) {
    const signo = coincidencia[1] === '-' ? -1 : 1;
    const crudoNumero = coincidencia[2].replace(',', '.');
    const tieneDecimal = crudoNumero.includes('.');
    let valor = parseFloat(crudoNumero);

    if (Number.isFinite(valor)) {
      let inferido = false;

      // 2) Sin separador decimal y sin unidad clara: la ACS-30 suele enviar
      //    el peso en gramos o con 3 decimales implícitos.
      if (!tieneDecimal && !unidadDetectada && crudoNumero.replace(/^0+/, '').length >= 3) {
        valor = valor / 1000;
        inferido = true;
      }

      const factor = unidadDetectada ? unidadDetectada.factor : 1;
      const kilos = redondear(signo * valor * factor, 3);

      if (Math.abs(kilos) <= MAX_KG) {
        return {
          ...base,
          reconocido: true,
          peso: kilos,
          unidad: 'kg',
          unidadOrigen: unidadDetectada ? unidadDetectada.unidad : (inferido ? 'g?' : 'kg'),
          inferido
        };
      }

      return { ...base, motivo: `Valor fuera de rango (${kilos} kg)` };
    }
  }

  return { ...base, motivo: 'No se encontró un número de peso en la trama' };
}

module.exports = { parsearTramaAcs30, limpiarTrama };
