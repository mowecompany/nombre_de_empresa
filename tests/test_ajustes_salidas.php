<?php
/** Verifica los ajustes puntuales de daños, créditos e impresión. */

$inventario = file_get_contents(__DIR__ . '/../Models/Inventario.php');
$vista = file_get_contents(__DIR__ . '/../Views/inventarios.php');
$fallos = 0;

function verificarAjuste(string $nombre, bool $cumple): void
{
    global $fallos;
    echo ($cumple ? 'OK   - ' : 'FALLA- ') . $nombre . PHP_EOL;
    if (!$cumple) {
        $fallos++;
    }
}

verificarAjuste('daños usan referencia secuencial DA', strpos($inventario, "return 'DA-' . str_pad") !== false);
verificarAjuste('referencia de daño se genera dentro de registrarSalida', strpos($inventario, "if (\$tipoSalida === 'dañado'") !== false);
verificarAjuste('referencia antigua DANADO eliminada', strpos($inventario, "'DANADO-' . date") === false);
verificarAjuste('secuencia de daños queda aislada por empresa', strpos($inventario, "empresa_id = :empresa_id") !== false);
verificarAjuste('secuencia de daños usa bloqueo entre cajas', strpos($inventario, 'GET_LOCK(:clave, 10)') !== false);
verificarAjuste('botón dañado comparte btn-nuevo sin color naranja', preg_match('/<button class="btn-nuevo" type="button" onclick="abrirModalProductoDanado\(\)"(?![^>]*background)/', $vista) === 1);
verificarAjuste('salidas conservan la marca esCredito', strpos($vista, 'esCredito: esRegistroCredito(item.es_credito)') !== false);
verificarAjuste('origen de salidas identifica créditos', strpos($vista, "? 'CRÉDITO' : 'INVENTARIO'") !== false);
verificarAjuste('movimientos reutilizan impresión de salidas', strpos($vista, 'await imprimirVentaSalida(claveTemporal, generarPdf);') !== false);
verificarAjuste('impresión mantiene RESUMEN DE VENTAS', strpos($vista, '<h1>RESUMEN DE<br>VENTAS</h1>') !== false);
verificarAjuste('impresión reserva menos ancho para metadatos', strpos($vista, '.meta strong { display: inline-block; width: 48px; }') !== false);

$creditos = file_get_contents(__DIR__ . '/../Controllers/CreditosController.php');
verificarAjuste('pago de crédito marca es_credito', strpos($creditos, "'es_credito' => 1,") !== false);
verificarAjuste('salida guarda la marca es_credito', strpos($inventario, "\$params[':es_credito']") !== false);
verificarAjuste('daños y pérdidas sin método de pago', strpos($inventario, "in_array(\$tipoSalida, ['dañado', 'perdida'], true) ? ''") !== false);
verificarAjuste('movimientos reciben tipo_salida', strpos($inventario, 'AS tipo_salida,') !== false);
verificarAjuste('tabla de salidas oculta tipo de pago en daños', strpos($vista, 'metodoPagoVisible(tipo, grupo.metodoPago)') !== false);
verificarAjuste('tabla de movimientos oculta tipo de pago en daños', strpos($vista, 'metodoPagoVisible(grupo.tipoSalida, grupo.metodoPago)') !== false);
verificarAjuste('movimientos muestran DAÑADO', strpos($vista, 'tipoSinCobro(tipoSalidaMov)') !== false);
verificarAjuste('salidas incluyen la categoría OTROS', strpos($vista, "abrirProductosCategoriaSalida('otros', 'OTROS')") !== false);
verificarAjuste('categoría OTROS filtra sus productos', strpos($vista, "if (grupo === 'otros') return categoria === 'otros';") !== false);
verificarAjuste('edición de kilos usa paso de milésimas', strpos($vista, "const pasoCantidad = esPorKilo ? 0.001 : 1;") !== false);
verificarAjuste('servidor normaliza kilos a tres decimales', strpos($inventario, '$cantidadNueva = $esProductoPorKilo ? round($cantidadNueva, 3) : floor($cantidadNueva);') !== false);

echo PHP_EOL . ($fallos === 0 ? 'TODAS LAS PRUEBAS PASARON' : "{$fallos} PRUEBA(S) FALLARON") . PHP_EOL;
exit($fallos === 0 ? 0 : 1);