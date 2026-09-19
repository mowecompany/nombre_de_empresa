$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$root = Split-Path -Parent $PSScriptRoot
$sourcePath = Join-Path $root 'Assets\images\Empresas\empresa_1_20260901_185734_fe042179.png'
$outputDir = Join-Path $root 'tmp\windows-icon-sizes'

if (-not (Test-Path $sourcePath)) {
    throw "No existe el logo fuente: $sourcePath"
}

New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
$sizes = @(16, 32, 48, 64, 128, 256)
$source = [System.Drawing.Bitmap]::new($sourcePath)

try {
    foreach ($size in $sizes) {
        $targetPath = Join-Path $outputDir "$size.png"
        $bitmap = [System.Drawing.Bitmap]::new($size, $size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
        try {
            $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
            try {
                $graphics.Clear([System.Drawing.Color]::Transparent)
                $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
                $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
                $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
                $graphics.DrawImage($source, 0, 0, $size, $size)
            } finally {
                $graphics.Dispose()
            }
            $bitmap.Save($targetPath, [System.Drawing.Imaging.ImageFormat]::Png)
        } finally {
            $bitmap.Dispose()
        }
    }
} finally {
    $source.Dispose()
}

Push-Location $root
try {
    & node '.\scripts\build-win-icon.js'
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo construir logo.ico."
    }
} finally {
    Pop-Location
}
