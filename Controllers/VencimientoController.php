<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Vencimiento.php';

try {
    $db = Database::connect();

    class VencimientoController {
        private $vencimiento;

        public function __construct($db) {
            $this->vencimiento = new Vencimiento($db);
        }

        // Lotes con fecha de vencimiento paginados por producto (GET)
        public function lotesPorVencerPaginado() {
            try {
                $opciones = [
                    'search' => (string)($_GET['search'] ?? ''),
                    'estado' => (string)($_GET['estado'] ?? ''),
                    'limit' => (int)($_GET['limit'] ?? 50),
                    'offset' => (int)($_GET['offset'] ?? 0)
                ];
                $resultado = $this->vencimiento->lotesPaginado($opciones);
                echo json_encode([
                    'success' => true,
                    'data' => $resultado['data'],
                    'total' => $resultado['total'],
                    'offset' => $resultado['offset'],
                    'limit' => $resultado['limit'],
                    'resumen' => $this->vencimiento->resumen()
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }

        // Contadores para las tarjetas del semáforo (GET)
        public function resumenVencimientos() {
            try {
                echo json_encode(['success' => true, 'resumen' => $this->vencimiento->resumen()]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
    }

    // HANDLING AJAX REQUESTS
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['action'])) {
        $controller = new VencimientoController($db);
        $action = $_GET['action'];

        if (method_exists($controller, $action)) {
            $controller->$action();
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Acción no encontrada'
            ]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
        $controller = new VencimientoController($db);
        $action = $_POST['action'];

        if (method_exists($controller, $action)) {
            $controller->$action();
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Acción no encontrada'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Acción no especificada'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
}
