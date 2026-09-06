$ErrorActionPreference = 'Stop'

$Proyecto = 'C:\xampp\htdocs\nombre_de_empresa'
$UrlLocal = 'http://localhost/nombre_de_empresa/'
$DesktopPath = [Environment]::GetFolderPath('Desktop')
$AccesoDirecto = Join-Path $DesktopPath 'Caja1 - Sistema Local.url'

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "   INSTALADOR LOCAL - CAJA 1" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Este instalador prepara la computadora principal." -ForegroundColor Yellow
Write-Host "La Caja 1 es la unica que tiene XAMPP, Apache, PHP y SQLite." -ForegroundColor Yellow
Write-Host ""

if (-not (Test-Path 'C:\xampp\xampp-control.exe')) {
    Write-Host "XAMPP no encontrado en C:\xampp\xampp-control.exe" -ForegroundColor Red
    Write-Host "Instala XAMPP primero y vuelve a ejecutar este script." -ForegroundColor Red
    Read-Host "Presiona Enter para salir"
    exit 1
}

if (-not (Test-Path $Proyecto)) {
    Write-Host "No se encontro el proyecto en: $Proyecto" -ForegroundColor Red
    Write-Host "Copia la carpeta del proyecto dentro de C:\xampp\htdocs\" -ForegroundColor Red
    Read-Host "Presiona Enter para salir"
    exit 1
}

Write-Host "Proyecto encontrado: $Proyecto" -ForegroundColor Green
Write-Host ""
Write-Host "Creando acceso directo en el escritorio..." -ForegroundColor Yellow

@(
    '[InternetShortcut]',
    'URL=' + $UrlLocal,
    'IconIndex=0'
) | Set-Content -Path $AccesoDirecto -Encoding ASCII

Write-Host "Acceso directo creado: $AccesoDirecto" -ForegroundColor Green
Write-Host ""
Write-Host "Abriendo la aplicacion local..." -ForegroundColor Green
Start-Process $UrlLocal

Write-Host ""
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "Instalacion lista para la Caja 1." -ForegroundColor Green
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "URL local: $UrlLocal" -ForegroundColor Cyan
Write-Host "Si Apache no esta activo, abre XAMPP y enciende Apache." -ForegroundColor Yellow
Write-Host ""
Read-Host "Presiona Enter para cerrar"
