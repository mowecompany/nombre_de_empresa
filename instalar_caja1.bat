@echo off
setlocal
set "PROYECTO=C:\xampp\htdocs\nombre_de_empresa"
set "URL=http://localhost/nombre_de_empresa/"
set "DESKTOP=%USERPROFILE%\Desktop\Caja1 - Sistema Local.url"

cls
echo =====================================================
echo       INSTALADOR LOCAL - CAJA 1
echo =====================================================
echo.
echo Este instalador prepara el acceso local para la Caja 1.
echo La Caja 1 es la unica que tiene XAMPP, Apache, PHP y SQLite.
echo.

if not exist "C:\xampp\xampp-control.exe" (
    echo XAMPP no encontrado en C:\xampp\xampp-control.exe
    echo.
    echo Instala XAMPP primero y luego vuelve a ejecutar este archivo.
    echo.
    pause
    exit /b 1
)

if not exist "%PROYECTO%" (
    echo No se encontro el proyecto en: %PROYECTO%
    echo.
    echo Copia la carpeta del proyecto dentro de C:\xampp\htdocs\ y vuelve a ejecutar.
    echo.
    pause
    exit /b 1
)

echo Proyecto encontrado: %PROYECTO%
echo.
echo Creando acceso directo en el Escritorio...
> "%DESKTOP%" (
    echo [InternetShortcut]
    echo URL=%URL%
    echo IconIndex=0
)

echo.
echo Abriendo la app local...
start "" "%URL%"

echo.
echo ================================================
echo Configuracion completada.
echo ================================================
echo.
echo Acceso directo creado en el escritorio:
 echo %DESKTOP%
echo.
echo URL local:
 echo %URL%
echo.
echo.
echo Si Apache no esta activo, abre XAMPP y inicia Apache.
echo.
pause
