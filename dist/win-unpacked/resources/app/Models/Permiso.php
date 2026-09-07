<?php
require_once __DIR__ . '/../Config/database.php';

class Permiso {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function obtenerPermisos($rolId = null) {
        try {
            if ($rolId !== null) {
                $query = "SELECT * FROM permisos_roles WHERE rol_id = :rol_id ORDER BY modulo ASC";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
            } else {
                $query = "SELECT * FROM permisos_roles ORDER BY rol_id ASC, modulo ASC";
                $stmt = $this->db->prepare($query);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerPermisos: " . $e->getMessage());
            return [];
        }
    }

    public function verificarPermiso($rol, $modulo, $accion = 'ver') {
        try {
            $query = "SELECT * FROM permisos_roles WHERE rol_id = (SELECT id FROM roles WHERE nombre = :rol LIMIT 1) AND modulo = :modulo LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':rol', $rol, PDO::PARAM_STR);
            $stmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$resultado) {
                return false;
            }

            // Verificar el permiso específico
            if (!isset($resultado[$accion])) {
                return false;
            }

            return (int)$resultado[$accion] === 1;
        } catch (PDOException $e) {
            error_log("Error en verificarPermiso: " . $e->getMessage());
            return false;
        }
    }

    public function asignarPermiso($rolId, $modulo, $ver = 0, $crear = 0, $actualizar = 0, $eliminar = 0) {
        try {
            // Verificar si el permiso ya existe
            $checkQuery = "SELECT id FROM permisos_roles WHERE rol_id = :rol_id AND modulo = :modulo LIMIT 1";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
            $checkStmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
            $checkStmt->execute();
            $existe = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                // Actualizar permiso existente
                $query = "UPDATE permisos_roles SET ver = :ver, crear = :crear, actualizar = :actualizar, eliminar = :eliminar WHERE rol_id = :rol_id AND modulo = :modulo";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
                $stmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
                $stmt->bindValue(':ver', $ver, PDO::PARAM_INT);
                $stmt->bindValue(':crear', $crear, PDO::PARAM_INT);
                $stmt->bindValue(':actualizar', $actualizar, PDO::PARAM_INT);
                $stmt->bindValue(':eliminar', $eliminar, PDO::PARAM_INT);
                return $stmt->execute();
            } else {
                // Insertar nuevo permiso
                $query = "INSERT INTO permisos_roles (rol_id, modulo, ver, crear, actualizar, eliminar) VALUES (:rol_id, :modulo, :ver, :crear, :actualizar, :eliminar)";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
                $stmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
                $stmt->bindValue(':ver', $ver, PDO::PARAM_INT);
                $stmt->bindValue(':crear', $crear, PDO::PARAM_INT);
                $stmt->bindValue(':actualizar', $actualizar, PDO::PARAM_INT);
                $stmt->bindValue(':eliminar', $eliminar, PDO::PARAM_INT);
                return $stmt->execute();
            }
        } catch (PDOException $e) {
            error_log("Error en asignarPermiso: " . $e->getMessage());
            return false;
        }
    }

    public function eliminarPermiso($rolId, $modulo = null) {
        try {
            if ($modulo !== null) {
                $query = "DELETE FROM permisos_roles WHERE rol_id = :rol_id AND modulo = :modulo";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
                $stmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
            } else {
                $query = "DELETE FROM permisos_roles WHERE rol_id = :rol_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
            }
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en eliminarPermiso: " . $e->getMessage());
            return false;
        }
    }
}
?>
