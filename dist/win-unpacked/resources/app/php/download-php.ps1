# Descargar e instalar PHP portable para Windows en la carpeta php\
# Ejecutar desde la raíz del proyecto: .\php\download-php.ps1

$phpUrl = 'https://windows.php.net/downloads/releases/archives/php-8.2.15-Win32-vs16-x64.zip'
$downloadFolder = Join-Path $PSScriptRoot 'tmp'
$zipPath = Join-Path $downloadFolder 'php-portable.zip'
$phpFolder = Join-Path $PSScriptRoot 'php'

if (-not (Test-Path $downloadFolder)) {
    New-Item -ItemType Directory -Path $downloadFolder | Out-Null
}

Write-Host "Descargando PHP portable desde: $phpUrl"
Invoke-WebRequest -Uri $phpUrl -OutFile $zipPath -UseBasicParsing

if (-not (Test-Path $zipPath)) {
    Write-Error 'No se pudo descargar el archivo ZIP de PHP.'
    exit 1
}

if (Test-Path $phpFolder) {
    Write-Host "La carpeta php ya existe en: $phpFolder"
} else {
    New-Item -ItemType Directory -Path $phpFolder | Out-Null
}

Write-Host "Extrayendo PHP portable en: $phpFolder"
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::ExtractToDirectory($zipPath, $phpFolder)

# Mover el contenido del subdirectorio extraído al directorio php principal
$extractedRoot = Get-ChildItem -Path $phpFolder | Where-Object { $_.PsIsContainer } | Select-Object -First 1
if ($null -ne $extractedRoot -and $extractedRoot.Name -match '^php-') {
    Write-Host "Reorganizando archivos desde $($extractedRoot.Name)"
    Get-ChildItem -Path $extractedRoot.FullName -Force | Move-Item -Destination $phpFolder -Force
    Remove-Item -Path $extractedRoot.FullName -Recurse -Force
}

Write-Host "PHP portable instalado correctamente en: $phpFolder"
Write-Host "Asegúrate de que exista php.exe en la carpeta $phpFolder"
Write-Host "Para ejecutar Electron, usa: npm run start:dev"
