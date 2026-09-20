# Electron Desktop Shell

Esta integración permite ejecutar el proyecto PHP dentro de una aplicación de escritorio Windows.

## Estructura
- `electron/main.js`: arranca el servidor PHP embebido y crea la ventana Electron.
- `electron/preload.js`: expone APIs seguras mínimas al renderer.
- `package.json`: scripts y configuración de `electron-builder`.
- `Config/.env`: configuración de entorno para Electron y SQLite.

## Requisitos
- Windows 10 o 11.
- PHP disponible en PATH o un PHP portable en `php/php.exe`.
- Archivo de icono `logo.ico` colocado en la raíz del proyecto.
- Node.js y npm instalados.

## Uso
1. Coloca `logo.ico` en la raíz del proyecto.
2. En la raíz del proyecto, instala dependencias:
   ```bash
   npm install
   ```
3. Inicia en modo desarrollo:
   ```bash
   npm run start:dev
   ```

## Compilar e instalar
1. Empaqueta la app:
   ```bash
   npm run build
   ```
2. El instalador generado queda en `dist/LaserTechManagerSetup.exe`.
3. Ejecuta el instalador para crear accesos directos en el escritorio y el menú de inicio.

## Modo offline
- El instalador incluye la aplicación web y usa SQLite portátil.
- El servidor PHP se inicia automáticamente internamente.
- La app carga `http://127.0.0.1:8000` en la ventana Electron.

## PHP portable (recomendado)
- Antes de construir el instalador, coloca un runtime PHP portable en `php/`.
- Puedes usar `php/download-php.ps1` para descargar e instalar automáticamente PHP portable en la carpeta `php/`.

## Cambiar el icono
- Reemplaza `logo.ico` en la raíz del proyecto.
- El instalador y la ventana de la app usarán ese icono.

## Cambiar nombre de la aplicación
- `LaserTech Manager` y `LaserTech Solutions` están definidos en `electron/main.js`.
- También se pueden ajustar en `package.json` dentro de `productName` y `build.win.legalTrademarks`.

## Cambiar SQLite
- La configuración SQLite se carga desde `Config/.env`.
- Si necesitas usar MySQL, cambia `DB_CONNECTION=mysql` en `Config/.env` y ajusta los valores relacionados.

## Notas importantes
- No modifica la arquitectura MVC ni las carpetas `Assets`, `Controllers`, `Models`, `Views`.
- El servidor PHP arranca y se cierra junto con la aplicación.
- Si el puerto `8000` está ocupado, Electron buscará un puerto libre entre 8000 y 8010.

## Báscula ACS-30 (lectura de peso real)

### Regla principal
El puerto COM lo abre **solo** un lector auxiliar propio de ESTRELLA (`electron/bascula/BasculaWorker.js`).
`BasculaCoordinator.js` elige una única copia propietaria en todo el equipo. Si están abiertas la
instalada y la portable, la segunda recibe el peso desde la propietaria y nunca compite por COM3.
El proceso principal lo supervisa y puede reiniciarlo para que Windows libere todos sus manejadores.
La pantalla de ventas nunca abre el puerto: únicamente escucha `window.basculaAPI.onPeso()`.
Un puerto serial admite una sola aplicación a la vez; por eso cualquier apertura desde el
navegador (Web Serial) provoca `Failed to execute 'open' on 'SerialPort'` al recargar la página.

### Qué eliminar si vienes de una versión anterior
- Todo uso de `navigator.serial` (`requestPort`, `getPorts`, `port.open`) en `Views/inventarios.php`.
- El puente HTTP/SSE local `127.0.0.1:8765` (`/bascula/status`, `/bascula/stream`).
- Los mensajes `liberar-bascula-antes-de-navegar` en `Views/dashboard.php`.
- Los handlers antiguos `bascula-conectar` / `bascula-desconectar` / `bascula-probar` en `main.js`.

### Archivos vigentes
- `electron/bascula/BasculaService.js`: detección del adaptador CH340 (VID `1a86`, respaldo `COM3`,
  `COM1` nunca), apertura 9600-8-N-1, lectura continua, reconexión automática y tara.
- `electron/bascula/BasculaWorker.js`: proceso aislado que posee el puerto y muere completamente al reconectar.
- `electron/bascula/BasculaSupervisor.js`: reinicia el lector y conserva la API usada por las pantallas.
- `electron/bascula/BasculaCoordinator.js`: comparte un único lector entre todas las copias locales.
- `electron/bascula/parserAcs30.js`: interpretación de la trama.
- `electron/bascula/diagnostico.html`: ventana de diagnóstico (tecla **F9**).
- `electron/preload.js`: expone `window.basculaAPI`.

### Instalación
`serialport` es un módulo nativo: `npm install` ejecuta `postinstall` con `electron-rebuild`
para recompilarlo contra Electron 26. Sin ese paso el puerto no abre.

### Comprobación
1. `npm install` y `npm start` en la carpeta `ESTRELLA`.
2. Pulsa **F9**: debe verse el adaptador CH340, el puerto, la trama cruda y el peso.
3. Recarga la pantalla de ventas y cambia de módulo varias veces: el peso debe seguir llegando.

### Errores frecuentes
- *Acceso denegado / puerto ocupado*: el software de la balanza o un monitor serial ajeno tiene COM3.
  Ciérralo y pulsa Reintentar. Las copias de ESTRELLA comparten un solo lector y no deben competir.
- *SetCommState / código 31*: Windows ve el CH340 pero el dispositivo o su controlador aún no responde.
  ESTRELLA reintenta durante 20 segundos y, al pulsar Reconectar, puede solicitar UAC para reiniciar
  exclusivamente `VID_1A86&PID_7523` mediante `pnputil`. La aplicación completa no queda elevada.
  La versión WCH `3.9.2024.9` está marcada como problemática; pruebe `3.7.2022.01` o `3.5.2019.1`.
- *Prueba de permisos*: el botón **Comprobar permisos** registra si la ejecución actual está elevada.
  Si el código 31 también ocurre elevada, queda descartada la falta de privilegios. Un bloqueo real se
  reporta como `Acceso denegado`/`puerto ocupado`, que es un error distinto.
- *La librería serial no está instalada*: falta `npm install` en `ESTRELLA`.
- *Llega trama pero no peso*: copia la trama desde el diagnóstico para ajustar el parser.

### Prueba de cierre y recuperación de COM3
1. Abra ESTRELLA y confirme en F9 que la báscula está conectada.
2. Cierre la ventana con la X y confirme en el Administrador de tareas que ESTRELLA desaparece.
3. Abra ESTRELLA otra vez: debe detectar el CH340 y abrir COM3 automáticamente.
4. Cierre sesión y vuelva a iniciarla: COM3 debe seguir abierto por la misma aplicación, sin desconectarse.
5. Pulse **Reconectar báscula** sin desconectar el USB: ESTRELLA debe cerrar únicamente su lector auxiliar, iniciar uno limpio y recuperar COM3.
6. Si un monitor serial ajeno ocupa COM3, ESTRELLA debe informar el bloqueo sin cerrar ese programa.
7. Abra instalada y portable al mismo tiempo: ambas deben mostrar el mismo PID propietario y el mismo peso.
8. Para comparar permisos, ejecute una vez normalmente y otra con **Ejecutar como administrador**, pulse
   **Comprobar permisos** y repita la apertura sin desconectar el USB. Los resultados quedan guardados en
   `bascula-recuperacion.jsonl` dentro de la carpeta de datos de la aplicación.

### Integraciones universales
Productos comerciales como PV-COM usan patrones conocidos: agente local, puerto serial y salida como
teclado/COM virtual. ESTRELLA mantiene una implementación propia más segura: lector auxiliar exclusivo e
IPC de Electron, sin copiar software propietario, sin depender del foco del teclado y sin exponer COM al navegador.
