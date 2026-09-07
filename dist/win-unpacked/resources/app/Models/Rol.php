<?php
require_once __DIR__ . '/../Config/database.php';

class Rol {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function obtenerRoles() {
        try {
            $query = "SELECT id, nombre, descripcion, estado FROM roles ORDER BY id DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerRoles: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerRolPorId($id) {
        try {
            $query = "SELECT id, nombre, descripcion, estado FROM roles WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerRolPorId: " . $e->getMessage());
            return null;
        }
    }

    public function obtenerRolPorNombre($nombre) {
        try {
            $query = "SELECT id, nombre, descripcion, estado FROM roles WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre)) LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerRolPorNombre: " . $e->getMessage());
            return null;
        }
    }

    public function crearRol($nombre, $descripcion = '', $estado = 1, $empresaId = 0) {
        try {
            $query = "INSERT INTO roles (nombre, descripcion, estado, empresa_id) VALUES (:nombre, :descripcion, :estado, :empresa_id)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':estado', $estado, PDO::PARAM_INT);
            $stmt->bindValue(':empresa_id', (int)$empresaId, PDO::PARAM_INT);
            $stmt->execute();
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error en crearRol: " . $e->getMessage());
            return false;
        }
    }

    public function actualizarRol($id, $nombre, $descripcion = '', $estado = 1) {
        try {
            $query = "UPDATE roles SET nombre = :nombre, descripcion = :descripcion, estado = :estado WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':estado', $estado, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en actualizarRol: " . $e->getMessage());
            return false;
        }
    }

    public function eliminarRol($id) {
        try {
            $query = "DELETE FROM roles WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en eliminarRol: " . $e->getMessage());
            return false;
        }
    }
}
?>
