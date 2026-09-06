<?php
session_start();
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/Config.php';
require_once ROOT_PATH . '/Helpers/Helpers.php';

if (!PermisosHelper::esSuperAdminSesion()) {
    header('Location: dashboard.php');
    exit();
}
$baseUrl = rtrim((string)base_url(), '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Base de datos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-family: 'Sora', sans-serif; background: #f4f7f9; color: #263238; }
        .database-panel { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #d9e2e7; border-radius: 14px; padding: 28px; box-shadow: 0 10px 30px rgba(38,50,56,.08); }
        h1 { margin: 0 0 8px; font-size: 24px; text-transform: uppercase; }
        .intro { margin: 0 0 24px; color: #60727c; }
        .actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .action { border: 1px solid #dfe7eb; border-radius: 12px; padding: 22px; background: #fbfcfd; }
        .action h2 { margin: 0 0 8px; font-size: 17px; text-transform: uppercase; }
        .action p { min-height: 42px; margin: 0 0 18px; color: #60727c; font-size: 13px; }
        button, .file-label { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 16px; border: 0; border-radius: 8px; color: #fff; background: #1d6f8f; font-weight: 700; cursor: pointer; text-decoration: none; }
        button:hover, .file-label:hover { filter: brightness(.94); }
        .file-label { background: #d97706; }
        input[type=file] { display: none; }
        #fileName { display: block; margin: 10px 0 14px; color: #60727c; font-size: 12px; overflow-wrap: anywhere; }
        @media (max-width: 650px) { body { padding: 12px; } .database-panel { padding: 20px; } .actions { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <main class="database-panel">
        <h1><i class="fas fa-database"></i> Base de datos</h1>
        <p class="intro">Exporta o importa una copia completa del sistema, incluyendo datos e imágenes.</p>
        <div class="actions">
            <section class="action">
                <h2>EXPORTAR</h2>
                <p>EXPORTAR LA BASE DE DATOS</p>
                <a id="exportDatabase" class="file-label" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/Controllers/BaseDatosController.php?action=exportar"><i class="fas fa-upload"></i> EXPORTAR</a>
            </section>
            <section class="action">
                <h2>IMPORTAR</h2>
                <p>IMPORTAR LA BASE DE DATOS</p>
                <form id="importForm" enctype="multipart/form-data">
                    <label class="file-label" for="databaseFile"><i class="fas fa-download"></i> SELECCIONAR</label>
                    <input id="databaseFile" name="base_datos" type="file" accept=".zip,.db,application/zip,application/octet-stream">
                    <span id="fileName">NINGÚN ARCHIVO SELECCIONADO</span>
                    <button type="submit"><i class="fas fa-file-import"></i> IMPORTAR</button>
                </form>
            </section>
        </div>
    </main>
    <script>
        const baseUrl = <?= json_encode($baseUrl) ?>;
        const exportLink = document.getElementById('exportDatabase');
        const fileInput = document.getElementById('databaseFile');
        const fileName = document.getElementById('fileName');
        exportLink.addEventListener('click', async event => {
            event.preventDefault();
            try {
                const response = await fetch(exportLink.href);
                if (!response.ok) throw new Error(await response.text() || 'No se pudo exportar la base de datos.');
                const blob = await response.blob();
                const contentDisposition = response.headers.get('Content-Disposition') || '';
                const match = contentDisposition.match(/filename="?([^";]+)"?/i);
                const filename = match ? match[1] : `base_datos_AUTOSERVICIO MI ESTRELLA
_${new Date().toISOString().replace(/[:.]/g, '-')}.zip`;
                if (window.electronAPI?.saveExportedDatabase) {
                    const saveResult = await window.electronAPI.saveExportedDatabase(filename, await blob.arrayBuffer());
                    if (!saveResult?.saved) return;
                    await Swal.fire('Exportación completada', 'La base de datos se guardó correctamente.', 'success');
                    return;
                }
                const downloadUrl = URL.createObjectURL(blob);
                const download = document.createElement('a');
                download.href = downloadUrl;
                download.download = filename;
                download.click();
                URL.revokeObjectURL(downloadUrl);
                await Swal.fire('Exportación completada', 'La base de datos se exportó correctamente.', 'success');
            } catch (error) {
                Swal.fire('Error', error.message || 'No se pudo exportar la base de datos.', 'error');
            }
        });
        fileInput.addEventListener('change', () => { fileName.textContent = fileInput.files[0]?.name || 'Ningún archivo seleccionado'; });
        document.getElementById('importForm').addEventListener('submit', async event => {
            event.preventDefault();
            if (!fileInput.files.length) { Swal.fire('Selecciona un archivo', 'Debes elegir un respaldo .zip.', 'warning'); return; }
            const confirmation = await Swal.fire({ title: '¿Importar base de datos?', text: 'La base actual será reemplazada y se guardará un respaldo.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Importar', cancelButtonText: 'Cancelar' });
            if (!confirmation.isConfirmed) return;
            const formData = new FormData(event.target);
            formData.append('action', 'importar');
            try {
                const response = await fetch(`${baseUrl}/Controllers/BaseDatosController.php?action=importar`, { method: 'POST', body: formData });
                const responseText = await response.text();
                let result;
                try { result = JSON.parse(responseText); } catch (parseError) { throw new Error(responseText || 'No se pudo importar la base de datos.'); }
                if (!response.ok || !result.success) throw new Error(result.message || 'No se pudo importar.');
                await Swal.fire('Importación completada', result.message, 'success');
                window.top.location.href = `${baseUrl}/Views/dashboard.php`;
            } catch (error) { Swal.fire('Error', error.message, 'error'); }
        });
    </script>
</body>
</html>
