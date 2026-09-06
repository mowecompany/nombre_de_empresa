<?php
session_start();
header('Content-Type: application/json');

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Permiso.php';
require_once ROOT_PATH . '/Helpers/ActualizacionesHelper.php';

function columnaExisteLocal(PDO $db, string $tabla, string $columna): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$tabla} LIKE :columna");
        $stmt->bindValue(':columna', $columna, PDO::PARAM_STR);
        $stmt->execute();
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error en columnaExisteLocal (actualizar_permisos): ' . $e->getMessage());
        return false;
    }
}

function obtenerScopeRolPermisos(PDO $db, int $rolId): array {
    $usaEmpresaEnRoles = columnaExisteLocal($db, 'roles', 'empresa_id');
    $usaUsuarioAdminEnRoles = columnaExisteLocal($db, 'roles', 'usuario_admin_id');

    $columnas = ['id'];
    if ($usaEmpresaEnRoles) {
        $columnas[] = 'empresa_id';
    }
    if ($usaUsuarioAdminEnRoles) {
        $columnas[] = 'usuario_admin_id';
    }

    $stmt = $db->prepare('SELECT ' . implode(', ', $columnas) . ' FROM roles WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $rolId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'empresa_id' => (int)($row['empresa_id'] ?? 0),
        'usuario_admin_id' => (int)($row['usuario_admin_id'] ?? 0),
    ];
}

try {
    // Obtener los datos JSON o FormData
    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos) || empty($datos)) {
        $datos = $_POST;
    }

    if (!is_array($datos)) {
        throw new Exception('No se recibieron datos');
    }

    $rolId = isset($datos['rolId']) ? (int)$datos['rolId'] : (isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0);
    $permisos = $datos['permisos'] ?? [];

    if ($rolId <= 0 || !is_array($permisos)) {
        throw new Exception('Datos de permisos inválidos');
    }

    $db = Database::connect();

    $usaEmpresaEnPermisos = columnaExisteLocal($db, 'permisos_roles', 'empresa_id');
    $usaUsuarioAdminEnPermisos = columnaExisteLocal($db, 'permisos_roles', 'usuario_admin_id');
    $usaUsuarioAdminEnRoles = columnaExisteLocal($db, 'roles', 'usuario_admin_id');
    $usaUsuarioAdmin = $usaUsuarioAdminEnPermisos && $usaUsuarioAdminEnRoles;

    $scopeRol = obtenerScopeRolPermisos($db, $rolId);
    $rolEmpresaId = (int)($scopeRol['empresa_id'] ?? 0);
    $rolUsuarioAdminId = (int)($scopeRol['usuario_admin_id'] ?? 0);

    if ($usaEmpresaEnPermisos && $rolEmpresaId <= 0) {
        $rolEmpresaId = (int)($_SESSION['empresa_id'] ?? 0);
    }

    if ($usaEmpresaEnPermisos && $rolEmpresaId <= 0) {
        throw new Exception('No se pudo determinar la empresa del rol para actualizar permisos');
    }

    $usuarioAdminId = 0;
    if ($usaUsuarioAdmin) {
        $usuarioAdminId = $rolUsuarioAdminId;
        if ($usuarioAdminId <= 0) {
            $rolSesionRaw = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
            $rolSesionRaw = strtr($rolSesionRaw, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
            if ($rolSesionRaw === 'administrador') {
                $usuarioAdminId = (int)($_SESSION['usuario_id'] ?? 0);
            }
        }

        if ($usuarioAdminId <= 0) {
            $usaUsuarioAdmin = false;
        }
    }

    if ($usaUsuarioAdmin && $rolEmpresaId > 0 && $usuarioAdminId > 0) {
        $stmtEliminarScopeInvalido = $db->prepare(
            "DELETE FROM permisos_roles
             WHERE rol_id = :rol_id
               AND (
                    empresa_id <> :empresa_id
                    OR (
                        usuario_admin_id IS NOT NULL
                        AND usuario_admin_id <> 0
                        AND usuario_admin_id <> :usuario_admin_id
                    )
               )"
        );
        $stmtEliminarScopeInvalido->execute([
            ':rol_id' => $rolId,
            ':empresa_id' => $rolEmpresaId,
            ':usuario_admin_id' => $usuarioAdminId,
        ]);

        $stmtEliminarDuplicados = $db->prepare(
            "DELETE pr_legacy
             FROM permisos_roles pr_legacy
             INNER JOIN permisos_roles pr_scope
                ON pr_scope.rol_id = pr_legacy.rol_id
               AND pr_scope.modulo = pr_legacy.modulo
               AND pr_scope.empresa_id = pr_legacy.empresa_id
               AND pr_scope.usuario_admin_id = :usuario_admin_id
             WHERE pr_legacy.rol_id = :rol_id
               AND pr_legacy.empresa_id = :empresa_id
               AND (pr_legacy.usuario_admin_id IS NULL OR pr_legacy.usuario_admin_id = 0)"
        );
        $stmtEliminarDuplicados->execute([
            ':usuario_admin_id' => $usuarioAdminId,
            ':rol_id' => $rolId,
            ':empresa_id' => $rolEmpresaId,
        ]);

        $stmtMigracion = $db->prepare("UPDATE permisos_roles SET usuario_admin_id = :usuario_admin_id WHERE rol_id = :rol_id AND empresa_id = :empresa_id AND (usuario_admin_id IS NULL OR usuario_admin_id = 0)");
        $stmtMigracion->execute([
            ':usuario_admin_id' => $usuarioAdminId,
            ':rol_id' => $rolId,
            ':empresa_id' => $rolEmpresaId,
        ]);
    } elseif ($usaEmpresaEnPermisos && $rolEmpresaId > 0) {
        $stmtEliminarScopeInvalido = $db->prepare(
            "DELETE FROM permisos_roles
             WHERE rol_id = :rol_id
               AND empresa_id <> :empresa_id"
        );
        $stmtEliminarScopeInvalido->execute([
            ':rol_id' => $rolId,
            ':empresa_id' => $rolEmpresaId,
        ]);
    }

    // Iniciar transacción
    $db->beginTransaction();

    // 0) Backfill: asegurar matriz completa de módulos para este rol.
    // Super Admin usa todos los módulos activos; otros, por tipo de empresa cuando aplica.
    $rolSesionNorm = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
    $rolSesionNorm = strtr($rolSesionNorm, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
    $esSuperSesion = ($rolSesionNorm === 'super administrador');

    $tipoEmpresaId = 0;
    if ($rolEmpresaId > 0) {
        try {
            $columnasTipo = [];
            if (columnaExisteLocal($db, 'empresas', 'tipo_empresa_id')) {
                $columnasTipo[] = 'tipo_empresa_id';
            }
            if (columnaExisteLocal($db, 'empresas', 'id_tipos_empresa')) {
                $columnasTipo[] = 'id_tipos_empresa';
            }

            if (!empty($columnasTipo)) {
                $stmtTipoEmp = $db->prepare("SELECT " . implode(', ', $columnasTipo) . " FROM empresas WHERE id = ? LIMIT 1");
                $stmtTipoEmp->execute([$rolEmpresaId]);
                $rowTipoEmp = $stmtTipoEmp->fetch(PDO::FETCH_ASSOC) ?: [];

                $tipoEmpresaPrincipal = (int)($rowTipoEmp['tipo_empresa_id'] ?? 0);
                $tipoEmpresaSecundario = (int)($rowTipoEmp['id_tipos_empresa'] ?? 0);
                $tipoEmpresaId = $tipoEmpresaPrincipal > 0 ? $tipoEmpresaPrincipal : $tipoEmpresaSecundario;
            }
        } catch (Exception $e) {
            $tipoEmpresaId = 0;
        }
    }

    $modulosTieneEstado = columnaExisteLocal($db, 'modulos', 'estado');
    $mteTieneEstado = columnaExisteLocal($db, 'modulos_tipos_empresa', 'estado');
    if ($esSuperSesion) {
        if ($modulosTieneEstado) {
            $stmtModulos = $db->prepare("SELECT nombre FROM modulos WHERE estado = 1 ORDER BY id ASC");
        } else {
            $stmtModulos = $db->prepare("SELECT nombre FROM modulos ORDER BY id ASC");
        }
        $stmtModulos->execute();
    } elseif ($tipoEmpresaId > 0) {
        if ($modulosTieneEstado) {
            $sqlModulosTipo = "SELECT DISTINCT m.nombre FROM modulos m INNER JOIN modulos_tipos_empresa mte ON mte.modulo_id = m.id WHERE m.estado = 1 AND mte.tipo_empresa_id = :tipo_empresa_id";
            if ($mteTieneEstado) {
                $sqlModulosTipo .= " AND (mte.estado = 1 OR mte.estado IS NULL)";
            }
            $sqlModulosTipo .= " ORDER BY m.id ASC";
            $stmtModulos = $db->prepare($sqlModulosTipo);
        } else {
            $sqlModulosTipo = "SELECT DISTINCT m.nombre FROM modulos m INNER JOIN modulos_tipos_empresa mte ON mte.modulo_id = m.id WHERE mte.tipo_empresa_id = :tipo_empresa_id";
            if ($mteTieneEstado) {
                $sqlModulosTipo .= " AND (mte.estado = 1 OR mte.estado IS NULL)";
            }
            $sqlModulosTipo .= " ORDER BY m.id ASC";
            $stmtModulos = $db->prepare($sqlModulosTipo);
        }
        $stmtModulos->execute([':tipo_empresa_id' => $tipoEmpresaId]);
    } else {
        if ($modulosTieneEstado) {
            $stmtModulos = $db->prepare("SELECT nombre FROM modulos WHERE estado = 1 ORDER BY id ASC");
        } else {
            $stmtModulos = $db->prepare("SELECT nombre FROM modulos ORDER BY id ASC");
        }
        $stmtModulos->execute();
    }
    $modulosDisponibles = array_map(static function ($fila) {
        return trim((string)($fila['nombre'] ?? ''));
    }, $stmtModulos->fetchAll(PDO::FETCH_ASSOC));

    $modulosDisponibles = array_values(array_unique(array_filter($modulosDisponibles)));

    foreach ($modulosDisponibles as $nombreModuloBase) {
        if ($nombreModuloBase === '') {
            continue;
        }

        if ($usaEmpresaEnPermisos && $rolEmpresaId > 0) {
            if ($usaUsuarioAdmin && $usuarioAdminId > 0) {
                // Backfill específico del admin
                $stmtExisteBase = $db->prepare("SELECT id FROM permisos_roles WHERE rol_id = ? AND modulo = ? AND empresa_id = ? AND usuario_admin_id = ? LIMIT 1");
                $stmtExisteBase->execute([$rolId, $nombreModuloBase, $rolEmpresaId, $usuarioAdminId]);
                $idBase = (int)($stmtExisteBase->fetchColumn() ?: 0);
                if ($idBase <= 0) {
                    $stmtInsertBase = $db->prepare("INSERT INTO permisos_roles (rol_id, modulo, ver, crear, actualizar, eliminar, empresa_id, usuario_admin_id) VALUES (?, ?, 0, 0, 0, 0, ?, ?)");
                    $stmtInsertBase->execute([$rolId, $nombreModuloBase, $rolEmpresaId, $usuarioAdminId]);
                }
            } else {
                $stmtExisteBase = $db->prepare("SELECT id FROM permisos_roles WHERE rol_id = ? AND modulo = ? AND empresa_id = ? AND (usuario_admin_id IS NULL OR usuario_admin_id = 0) LIMIT 1");
                $stmtExisteBase->execute([$rolId, $nombreModuloBase, $rolEmpresaId]);
                $idBase = (int)($stmtExisteBase->fetchColumn() ?: 0);
                if ($idBase <= 0) {
                    $stmtInsertBase = $db->prepare("INSERT INTO permisos_roles (rol_id, modulo, ver, crear, actualizar, eliminar, empresa_id) VALUES (?, ?, 0, 0, 0, 0, ?)");
                    $stmtInsertBase->execute([$rolId, $nombreModuloBase, $rolEmpresaId]);
                }
            }
        } else {
            $stmtExisteBase = $db->prepare("SELECT id FROM permisos_roles WHERE rol_id = ? AND modulo = ? LIMIT 1");
            $stmtExisteBase->execute([$rolId, $nombreModuloBase]);
            $idBase = (int)($stmtExisteBase->fetchColumn() ?: 0);

            if ($idBase <= 0) {
                $stmtInsertBase = $db->prepare("INSERT INTO permisos_roles (rol_id, modulo, ver, crear, actualizar, eliminar) VALUES (?, ?, 0, 0, 0, 0)");
                $stmtInsertBase->execute([$rolId, $nombreModuloBase]);
            }
        }
    }

    foreach ($permisos as $moduloClave => $permiso) {
        if (!is_array($permiso)) {
            continue;
        }

        $nombreModulo = trim((string)($permiso['modulo'] ?? $moduloClave));
        if ($nombreModulo === '') {
            continue;
        }

        $ver = !empty($permiso['ver']) ? 1 : 0;
        $crear = !empty($permiso['crear']) ? 1 : 0;
        $actualizar = !empty($permiso['actualizar']) ? 1 : 0;
        $eliminar = !empty($permiso['eliminar']) ? 1 : 0;

        if ($usaEmpresaEnPermisos && $rolEmpresaId > 0) {
            if ($usaUsuarioAdmin && $usuarioAdminId > 0) {
                $stmtBuscar = $db->prepare("SELECT id FROM permisos_roles WHERE rol_id = ? AND modulo = ? AND empresa_id = ? AND usuario_admin_id = ? LIMIT 1");
                $stmtBuscar->execute([$rolId, $nombreModulo, $rolEmpresaId, $usuarioAdminId]);
                $idPermiso = (int)($stmtBuscar->fetchColumn() ?: 0);

                if ($idPermiso > 0) {
                    $stmtUpdate = $db->prepare("UPDATE permisos_roles SET ver = ?, crear = ?, actualizar = ?, eliminar = ? WHERE id = ?");
                    $stmtUpdate->execute([$ver, $crear, $actualizar, $eliminar, $idPermiso]);
                } else {
                    $stmtInsert = $db->prepare("INSERT INTO permisos_roles (rol_id, modulo, ver, crear, actualizar, eliminar, empresa_id, usuario_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtInsert->execute([$rolId, $nombreModulo, $ver, $crear, $actualizar, $eliminar, $rolEmpresaId, $usuarioAdminId]);
                }
            } else {
                $stmtBuscar = $db->prepare("SELECT id FROM permisos_roles WHERE rol_id = ? AND modulo = ? AND empresa_id = ? AND (usuario_admin_id IS NULL OR usuario_admin_id = 0) LIMIT 1");
                $stmtBuscar->execute([$rolId, $nombreModulo, $rolEmpresaId]);
                $idPermiso = (int)($stmtBuscar->fetchColumn() ?: 0);

                if ($idPermiso > 0) {
                    $stmtUpdate = $db->prepare("UPDATE permisos_roles SET ver = ?, crear = ?, actualizar = ?, eliminar = ? WHERE id = ?");
                    $stmtUpdate->execute([$ver, $crear, $actualizar, $eliminar, $idPermiso]);
                } else {
                    $stmtInsert = $db->prepare("INSERT INTO permisos_roles (rol_id, modulo, ver, crear, actualizar, eliminar, empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtInsert->execute([$rolId, $nombreModulo, $ver, $crear, $actualizar, $eliminar, $rolEmpresaId]);
                }
            }
        } else {
            $stmtBuscar = $db->prepare("SELECT id FROM permisos_roles WHERE rol_id = ? AND modulo = ? LIMIT 1");
            $stmtBuscar->execute([$rolId, $nombreModulo]);
            $idPermiso = (int)($stmtBuscar->fetchColumn() ?: 0);

            if ($idPermiso > 0) {
                $stmtUpdate = $db->prepare("UPDATE permisos_roles SET ver = ?, crear = ?, actualizar = ?, eliminar = ? WHERE id = ?");
                $stmtUpdate->execute([$ver, $crear, $actualizar, $eliminar, $idPermiso]);
            } else {
                $stmtInsert = $db->prepare("INSERT INTO permisos_roles (rol_id, modulo, ver, crear, actualizar, eliminar) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtInsert->execute([$rolId, $nombreModulo, $ver, $crear, $actualizar, $eliminar]);
            }
        }
    }

    // Confirmar transacción
    $db->commit();

    ActualizacionesHelper::registrarCambio(
        'permisos',
        'actualizar_permisos_rol',
        null,
        ['rol_id' => $rolId, 'permisos' => $permisos],
        ['entidad' => 'rol', 'registro_id' => (string)$rolId]
    );

    echo json_encode(['success' => true, 'message' => 'Permisos actualizados correctamente']);

} catch (Exception $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error al actualizar permisos: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar los permisos: ' . $e->getMessage()]);
}
?> 