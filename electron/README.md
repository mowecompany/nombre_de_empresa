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
El puerto COM lo abre **solo** el proceso principal de Electron (`electron/bascula/BasculaService.js`).
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
- *El puerto está ocupado por otro programa*: hay otra copia de ESTRELLA, el software de la balanza
  o un monitor serial abierto. Ciérralo y pulsa Reintentar.
- *La librería serial no está instalada*: falta `npm install` en `ESTRELLA`.
- *Llega trama pero no peso*: copia la trama desde el diagnóstico para ajustar el parser.
