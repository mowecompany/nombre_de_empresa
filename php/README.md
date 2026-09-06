# PHP portable para empaquetado

Para ejecutar la aplicación como escritorio sin depender de XAMPP, puedes usar un runtime PHP portable.

## Instrucciones
1. Ejecuta el script de descarga desde la raíz del proyecto:
   ```powershell
   .\php\download-php.ps1
   ```
2. El script descargará PHP portable y lo extraerá dentro de la carpeta `php/`.
3. Verifica que exista `php/php.exe` después de ejecutar el script.

## Uso con Electron
- El contenedor Electron ya está configurado para buscar `php/php.exe` primero.
- Si deseas forzar otra ruta, ajusta `PHP_EXECUTABLE` o `PHP_PATH` en el entorno.
- Electron arranca el servidor PHP con:
  ```text
  php -S 127.0.0.1:8000 -t <raíz-del-proyecto>
  ```

Si `php` ya está en PATH, la aplicación también funcionará sin necesidad de copiar `php/php.exe`.
