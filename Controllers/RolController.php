<?php
session_start();

require_once __DIR__ . '/../Helpers/Helpers.php';
require_once ROOT_PATH . '/Config/database.php';

header('Content-Type: application/json');

try {
    $db = Database::connect();

    $inputData = $_POST;
    if (empty($inputData) && isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        if ($rawBody !== false && trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $inputData = $decoded;
            }
        }
    }

    // Obtener los datos del formulario
    $action = $inputData['action'] ?? $_POST['action'] ?? null;
    
    if ($action === 'crear') {
        // Crear nuevo rol
        $nombre = trim((string)($inputData['nombre'] ?? $_POST['nombre'] ?? ''));
        $descripcion = trim((string)($inputData['descripcion'] ?? $_POST['descripcion'] ?? ''));
        $rolActual = normalizarNombreRol($_SESSION['rol'] ?? '');
        $esSuperAdministrador = str_starts_with($rolActual, 'superadministrador');
        $nombreNormalizado = normalizarNombreRol($nombre);
        
        if (empty($nombre)) {
            echo json_encode([
                'success' => false,
                'message' => 'El nombre del rol es requerido'
            ]);
            exit;
        }
        
        if (!$esSuperAdministrador && in_array($nombreNormalizado, ['administrador', 'superadministrador', 'cliente'], true)) {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes crear un rol con ese nombre desde este perfil'
            ]);
            exit;
        }

        // Verificar que el rol no exista (case-insensitive)
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre))");
        $checkStmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $checkStmt->execute();
        
        if ((int)$checkStmt->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Ya existe un rol con ese nombre'
            ]);
            exit;
        }
        
        $empresaId = isset($_SESSION['empresa_id'])
            ? (int)$_SESSION['empresa_id']
            : (isset($_SESSION['userData']['empresa_id']) ? (int)$_SESSION['userData']['empresa_id'] : 0);

        // El superadministrador global no tiene empresa en sesión, pero roles.empresa_id es obligatorio.
        $empresaValida = false;
        if ($empresaId > 0) {
            $empresaCheck = $db->prepare('SELECT 1 FROM empresas WHERE id = :id LIMIT 1');
            $empresaCheck->execute([':id' => $empresaId]);
            $empresaValida = (bool)$empresaCheck->fetchColumn();
        }

        if (!$empresaValida) {
            $empresaStmt = $db->query('SELECT id FROM empresas ORDER BY id ASC LIMIT 1');
            $empresaId = (int)($empresaStmt->fetchColumn() ?: 0);
        }

        if ($empresaId <= 0) {
            throw new RuntimeException('No existe una empresa válida para asociar el rol');
        }

        // Insertar el nuevo rol
        $insertStmt = $db->prepare("INSERT INTO roles (nombre, descripcion, estado, empresa_id) VALUES (:nombre, :descripcion, 1, :empresa_id)");
        $insertStmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $insertStmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $insertStmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        
        if ($insertStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Rol creado correctamente'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear el rol'
            ]);
        }
        
    } elseif ($action === 'editar') {
        // Editar rol existente
        $rolId = (int)($inputData['id'] ?? $_POST['id'] ?? $_POST['rolId'] ?? 0);
        $nombre = trim((string)($inputData['nombre'] ?? $_POST['nombre'] ?? ''));
        $descripcion = trim((string)($inputData['descripcion'] ?? $_POST['descripcion'] ?? ''));
        $nombreOriginal = trim((string)($inputData['nombre'] ?? $_POST['nombre'] ?? '')); // Por si viene en data-original
        
        if (empty($rolId)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol inválido'
            ]);
            exit;
        }
        
        if (empty($nombre)) {
            echo json_encode([
                'success' => false,
                'message' => 'El nombre del rol es requerido'
            ]);
            exit;
        }
        
        // Obtener el rol actual para comparar nombre
        $currentRolStmt = $db->prepare("SELECT nombre FROM roles WHERE id = :id");
        $currentRolStmt->bindValue(':id', $rolId, PDO::PARAM_INT);
        $currentRolStmt->execute();
        $currentRol = $currentRolStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentRol) {
            echo json_encode([
                'success' => false,
                'message' => 'El rol no existe'
            ]);
            exit;
        }
        
        $rolActual = normalizarNombreRol($_SESSION['rol'] ?? '');
        $nombreNormalizado = normalizarNombreRol($nombre);
        $nombreAnterior = trim($currentRol['nombre']);
        $nombreAnteriorNormalizado = normalizarNombreRol($nombreAnterior);

        if ($rolActual !== 'superadministrador' && in_array($nombreNormalizado, ['administrador', 'superadministrador', 'cliente'], true)) {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes renombrar un rol con ese nombre desde este perfil'
            ]);
            exit;
        }

        if ($rolActual !== 'superadministrador' && in_array($nombreAnteriorNormalizado, ['administrador', 'superadministrador'], true)) {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes modificar este rol protegido'
            ]);
            exit;
        }

        // Solo verificar duplicados si el nombre cambió
        if ($nombre !== $nombreAnterior) {
            // Verificar que el nuevo nombre no exista en otro rol
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre)) AND id != :id");
            $checkStmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $checkStmt->bindValue(':id', $rolId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ((int)$checkStmt->fetchColumn() > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Ya existe otro rol con ese nombre'
                ]);
                exit;
            }
        }
        
        // Actualizar el rol
        $updateStmt = $db->prepare("UPDATE roles SET nombre = :nombre, descripcion = :descripcion WHERE id = :id");
        $updateStmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $updateStmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $updateStmt->bindValue(':id', $rolId, PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            // Validar que se actualizó al menos una fila
            $rowsUpdated = $updateStmt->rowCount();
            if ($rowsUpdated > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Rol actualizado correctamente'
                ]);
            } else {
                error_log("Update roles falló: id=$rolId, nombre=$nombre");
                echo json_encode([
                    'success' => false,
                    'message' => 'No se pudo actualizar el rol (ID no encontrado o sin cambios)'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar el rol: ' . implode(' - ', $updateStmt->errorInfo())
            ]);
        }
        
    } elseif ($action === 'toggle_estado') {
        // Cambiar estado del rol
        $id = (int)($inputData['id'] ?? $_POST['id'] ?? 0);
        $estado = (int)($inputData['estado'] ?? $_POST['estado'] ?? 0);
        
        if (empty($id)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol inválido'
            ]);
            exit;
        }
        
        $rolActual = normalizarNombreRol($_SESSION['rol'] ?? '');
        $checkRolStmt = $db->prepare("SELECT nombre FROM roles WHERE id = :id");
        $checkRolStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $checkRolStmt->execute();
        $rol = $checkRolStmt->fetch(PDO::FETCH_ASSOC);
        $nombreRol = normalizarNombreRol($rol['nombre'] ?? '');

        if ($nombreRol === 'super administrador' || ($nombreRol === 'administrador' && $rolActual !== 'super administrador')) {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes modificar el estado de este rol protegido'
            ]);
            exit;
        }

        // El estado que viene es el NUEVO estado deseado
        $nuevoEstado = $estado ? 1 : 0;
        
        $updateStmt = $db->prepare("UPDATE roles SET estado = :estado WHERE id = :id");
        $updateStmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_INT);
        $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Estado del rol actualizado'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar el estado del rol'
            ]);
        }
        
    } elseif ($action === 'eliminar') {
        // Eliminar rol
        $id = (int)($_POST['id'] ?? 0);
        
        if (empty($id)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol inválido'
            ]);
            exit;
        }
        
        // Verificar que el rol existe
        $checkStmt = $db->prepare("SELECT nombre FROM roles WHERE id = :id");
        $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();
        $rol = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rol) {
            echo json_encode([
                'success' => false,
                'message' => 'El rol no existe'
            ]);
            exit;
        }
        
        // Evitar eliminar roles protegidos salvo que el usuario sea Super Administrador.
        $rolActual = normalizarNombreRol($_SESSION['rol'] ?? '');
        $nombreRol = normalizarNombreRol($rol['nombre'] ?? '');
        $esSuperAdminSesion = $rolActual === 'superadministrador';
        $esRolProtegido = in_array($nombreRol, ['superadministrador', 'administrador'], true);
        $esRolAdmin = $nombreRol === 'administrador';

        if ($esRolProtegido || $id <= 2) {
            $mensaje = 'No se puede eliminar administrador ni superadministrador. Solo se permite eliminar roles a partir del tercer rol.';
            echo json_encode([
                'success' => false,
                'message' => $mensaje
            ]);
            exit;
        }
        
        // Verificar que no hay usuarios con este rol
        $usersStmt = $db->prepare("SELECT COUNT(*) as count FROM usuarios WHERE rol = :rol");
        $usersStmt->bindValue(':rol', $rol['nombre'], PDO::PARAM_STR);
        $usersStmt->execute();
        $userCount = (int)($usersStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        if ($userCount > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar este rol porque hay usuarios asignados a él'
            ]);
            exit;
        }
        
        // Eliminar permisos asociados al rol
        $delPermsStmt = $db->prepare("DELETE FROM permisos_roles WHERE rol_id = :id");
        $delPermsStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $delPermsStmt->execute();
        
        // Eliminar el rol
        $deleteStmt = $db->prepare("DELETE FROM roles WHERE id = :id");
        $deleteStmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        if ($deleteStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Rol eliminado correctamente'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar el rol'
            ]);
        }
        
    } elseif ($action === 'reiniciar') {
        if (!PermisosHelper::esSuperAdminSesion()) {
            echo json_encode([
                'success' => false,
                'message' => 'Permisos insuficientes para reiniciar roles'
            ]);
            exit;
        }

        $db->beginTransaction();
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS roles_backups_reset (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                data_roles TEXT NOT NULL,
                data_permisos TEXT NOT NULL,
                creado_en TEXT NOT NULL
            )");

            $stmtRoles = $db->prepare('SELECT * FROM roles');
            $stmtRoles->execute();
            $rolesData = $stmtRoles->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmtPerms = $db->prepare('SELECT * FROM permisos_roles');
            $stmtPerms->execute();
            $permisosData = $stmtPerms->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmtBackup = $db->prepare('INSERT INTO roles_backups_reset (data_roles, data_permisos, creado_en) VALUES (:data_roles, :data_permisos, :creado_en)');
            $stmtBackup->execute([
                ':data_roles' => json_encode($rolesData, JSON_UNESCAPED_UNICODE),
                ':data_permisos' => json_encode($permisosData, JSON_UNESCAPED_UNICODE),
                ':creado_en' => date('Y-m-d H:i:s')
            ]);
            $backupId = (int)$db->lastInsertId();

            $db->exec('DELETE FROM permisos_roles WHERE rol_id NOT IN (1,2)');
            $db->exec('DELETE FROM roles WHERE id NOT IN (1,2)');

            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                try {
                    $db->exec("DELETE FROM sqlite_sequence WHERE name = 'roles'");
                } catch (Throwable $_) {
                }
            }

            $db->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Reinicio de roles completado correctamente',
                'backup_id' => $backupId
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error reiniciando roles: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error al reiniciar roles: ' . $e->getMessage()
            ]);
        }

    } elseif ($action === 'deshacer') {
        if (!PermisosHelper::esSuperAdminSesion()) {
            echo json_encode([
                'success' => false,
                'message' => 'Permisos insuficientes para deshacer reinicio de roles'
            ]);
            exit;
        }

        try {
            $db->exec("CREATE TABLE IF NOT EXISTS roles_backups_reset (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                data_roles TEXT NOT NULL,
                data_permisos TEXT NOT NULL,
                creado_en TEXT NOT NULL
            )");

            $stmtBackup = $db->prepare('SELECT id, data_roles, data_permisos FROM roles_backups_reset ORDER BY id DESC LIMIT 1');
            $stmtBackup->execute();
            $backup = $stmtBackup->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No hay backup disponible para restaurar'
                ]);
                exit;
            }

            $rolesData = json_decode($backup['data_roles'] ?? '[]', true);
            $permisosData = json_decode($backup['data_permisos'] ?? '[]', true);

            if (!is_array($rolesData) || !is_array($permisosData)) {
                throw new Exception('Backup inválido');
            }

            $db->beginTransaction();
            $db->exec('DELETE FROM permisos_roles');
            $db->exec('DELETE FROM roles');

            $insertRoleStmt = $db->prepare('INSERT INTO roles (' . implode(', ', array_map(function($col) { return $col; }, array_keys($rolesData[0] ?? []))) . ') VALUES (' . implode(', ', array_fill(0, count($rolesData[0] ?? []), '?')) . ')');
            foreach ($rolesData as $rolRow) {
                if (!is_array($rolRow) || empty($rolRow)) continue;
                $insertRoleStmt->execute(array_values($rolRow));
            }

            if (!empty($permisosData)) {
                $insertPermStmt = $db->prepare('INSERT INTO permisos_roles (' . implode(', ', array_map(function($col) { return $col; }, array_keys($permisosData[0] ?? []))) . ') VALUES (' . implode(', ', array_fill(0, count($permisosData[0] ?? []), '?')) . ')');
                foreach ($permisosData as $permRow) {
                    if (!is_array($permRow) || empty($permRow)) continue;
                    $insertPermStmt->execute(array_values($permRow));
                }
            }

            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                try {
                    $db->exec("DELETE FROM sqlite_sequence WHERE name = 'roles'");
                } catch (Throwable $_) {
                }
            }

            $stmtDeleteBackup = $db->prepare('DELETE FROM roles_backups_reset WHERE id = :id');
            $stmtDeleteBackup->execute([':id' => (int)$backup['id']]);

            $db->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Restauración de roles completada correctamente'
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error deshaciendo reinicio de roles: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error al deshacer reinicio: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Acción no válida'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error en RolController: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>
