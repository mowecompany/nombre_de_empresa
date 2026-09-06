<?php
// cSpell:disable
header('Content-Type: application/json');
error_reporting(0);
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Categoria.php';

try {
    $db = Database::connect();
    
    $categoria = new Categoria($db);

    // Manejar peticiones GET
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'getAll') {
            // Obtener todas las categorías
            $result = $categoria->getAll();
            if (is_array($result)) {
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
            } else {
                throw new Exception('Error al obtener categorías');
            }
        } elseif (isset($_GET['action']) && $_GET['action'] === 'getOne' && isset($_GET['id'])) {
            $categoria->setId($_GET['id']);
            $result = $categoria->getOne();
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
            } else {
                throw new Exception('Categoría no encontrada');
            }
        } else {
            throw new Exception('Acción no válida o ID no proporcionado');
        }
        exit;
    }

    // Manejar peticiones POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['action'])) {
            throw new Exception('Acción no especificada');
        }

        $action = $_POST['action'];
        
        switch($action) {
            case 'editar':
                // Permiso: Administradores pueden actualizar categorías
                
                if (!isset($_POST['id']) || !isset($_POST['nombre'])) {
                    throw new Exception('Faltan datos requeridos');
                }

                try {
                    $categoriaAntes = null;
                    $rutaImagenNueva = null;
                    $imagenAnterior = '';
                    // Verificar que solo Super Administrador puede cambiar el ID
                    $idOriginal = $_POST['id_original'] ?? $_POST['id'];
                    $idNuevo = $_POST['id'] ?? $_POST['id'];
                    $rolActual = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
                    $rolActual = strtr($rolActual, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);

                    if ($idOriginal !== $idNuevo && $rolActual !== 'super administrador') {
                        throw new Exception('Solo Super Administrador puede cambiar el ID de la categoría');
                    }

                    // Si el ID cambió, hacerlo primero
                    if ($idOriginal !== $idNuevo) {
                        try {
                            $categoria->cambiarIdCategoria($idOriginal, $idNuevo);
                        } catch(Exception $e) {
                            throw new Exception('Error al cambiar el ID: ' . $e->getMessage());
                        }
                    }

                    // Actualizar la categoría (nombre, descripción, imagen, etc)
                    $categoria->setId($idNuevo);
                    $categoriaAntes = $categoria->getOne();
                    $imagenAnterior = trim((string)($categoriaAntes->imagen ?? ''));
                    $categoria->setNombre($_POST['nombre']);
                    $categoria->setDescripcion($_POST['descripcion'] ?? '');

                    // Manejar la imagen si se subió una nueva
                    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                        $imagenInfo = guardarImagenSubidaValidada('imagen', [
                            'label' => 'La imagen de la categoría',
                            'max_bytes' => 500 * 1024,
                            'allowed_mimes' => ['image/png' => 'png'],
                            'rel_dir' => 'Assets/images/categorias',
                            'file_prefix' => 'categoria',
                        ]);
                        $rutaImagenNueva = (string)($imagenInfo['relative_path'] ?? '');
                        $categoria->setImagen((string)($imagenInfo['file_name'] ?? ''));
                    }

                    if ($categoria->update()) {
                        $categoria->setId($idNuevo);
                        $categoriaDespues = $categoria->getOne();
                        $imagenNueva = trim((string)($categoriaDespues->imagen ?? ''));
                        if ($rutaImagenNueva !== null && $imagenAnterior !== '' && $imagenAnterior !== $imagenNueva) {
                            eliminarArchivoProyectoSiExiste('Assets/images/categorias/' . $imagenAnterior);
                        }
                        ActualizacionesHelper::registrarCambio(
                            'categorias',
                            'editar',
                            $categoriaAntes,
                            $categoriaDespues,
                            ['entidad' => 'categoria', 'registro_id' => (string)$idNuevo]
                        );
                        echo json_encode([
                            'success' => true,
                            'message' => 'Categoría actualizada correctamente'
                        ]);
                    } else {
                        if ($rutaImagenNueva !== null) {
                            eliminarArchivoProyectoSiExiste($rutaImagenNueva);
                        }
                        throw new Exception('No se pudo actualizar la categoría. Verifique los datos e intente nuevamente.');
                    }
                } catch (Exception $e) {
                    if (!empty($rutaImagenNueva)) {
                        eliminarArchivoProyectoSiExiste($rutaImagenNueva);
                    }
                    error_log("Error en actualización: " . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar: ' . $e->getMessage()
                    ]);
                }
                break;

            case 'crear':
                // Permiso: Administradores pueden crear categorías
                
                if (!isset($_POST['nombre'])) {
                    throw new Exception('El nombre es requerido');
                }

                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $descripcion = trim((string)($_POST['descripcion'] ?? ''));
                if ($nombre === '') {
                    throw new Exception('El nombre es requerido');
                }
                
                $imagen = null;
                $rutaImagenNueva = null;
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                    try {
                        $imagenInfo = guardarImagenSubidaValidada('imagen', [
                            'label' => 'La imagen de la categoría',
                            'max_bytes' => 500 * 1024,
                            'allowed_mimes' => ['image/png' => 'png'],
                            'rel_dir' => 'Assets/images/categorias',
                            'file_prefix' => 'categoria',
                        ]);
                        $imagen = (string)($imagenInfo['file_name'] ?? '');
                        $rutaImagenNueva = (string)($imagenInfo['relative_path'] ?? '');
                    } catch (Exception $e) {
                        error_log("Error al procesar imagen en crear categoria: " . $e->getMessage());
                        throw new Exception('Error al procesar la imagen: ' . $e->getMessage());
                    }
                } else if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== 0 && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
                    error_log("Error de carga de archivo: " . $_FILES['imagen']['error']);
                    throw new Exception('Error al cargar la imagen: código ' . $_FILES['imagen']['error']);
                }
                
                $empresaIdPost = null;
                if (isset($_POST['empresa_id']) && is_numeric($_POST['empresa_id'])) {
                    $empresaIdPost = (int)$_POST['empresa_id'];
                }
                if ($empresaIdPost !== null && $empresaIdPost > 0) {
                    $categoria->setEmpresaId($empresaIdPost);
                } elseif (isset($_SESSION['empresa_id']) && (int)$_SESSION['empresa_id'] > 0) {
                    $categoria->setEmpresaId((int)$_SESSION['empresa_id']);
                }

                error_log("Creando categoria con datos: " . json_encode([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'imagen' => $imagen,
                    'estado' => 1
                ]));
                $resultado = $categoria->crear([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'imagen' => $imagen,
                    'estado' => 1
                ]);
                
                error_log("Resultado de crear categoria: " . ($resultado ? "true" : "false"));
                
                if ($resultado) {
                    $idCreado = (int)$db->lastInsertId();
                    $categoriaCreada = null;
                    if ($idCreado > 0) {
                        $categoria->setId($idCreado);
                        $categoriaCreada = $categoria->getOne();
                    }

                    $datosAfter = $categoriaCreada ? [
                        'id' => $categoriaCreada->id ?? $idCreado,
                        'nombre' => $categoriaCreada->nombre ?? $nombre,
                        'descripcion' => $categoriaCreada->descripcion ?? $descripcion,
                        'imagen' => $categoriaCreada->imagen ?? $imagen,
                        'estado' => $categoriaCreada->estado ?? 1,
                    ] : [
                        'id' => $idCreado > 0 ? $idCreado : null,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'imagen' => $imagen,
                        'estado' => 1,
                    ];

                    ActualizacionesHelper::registrarCambio(
                        'categorias',
                        'crear',
                        null,
                        $datosAfter,
                        ['entidad' => 'categoria', 'registro_id' => (string)($idCreado > 0 ? $idCreado : '')]
                    );

                    echo json_encode([
                        'success' => true,
                        'message' => 'Categoría creada exitosamente'
                    ]);
                } else {
                    if ($rutaImagenNueva !== null) {
                        eliminarArchivoProyectoSiExiste($rutaImagenNueva);
                    }
                    $errorDetalle = trim((string)$categoria->getLastError());
                    if ($errorDetalle === '') {
                        $errorDetalle = 'Error al crear la categoría. Por favor, verifique los datos e intente nuevamente.';
                    }
                    error_log("Error: crear() retornó false. Nombre: $nombre, Descripción: $descripcion. Detalle: $errorDetalle");
                    throw new Exception($errorDetalle);
                }
                break;

            case 'cambiarEstado':
                // Permiso: Administradores pueden cambiar estado de categorías
                
                if (!isset($_POST['id']) || !isset($_POST['estado'])) {
                    throw new Exception('Faltan datos requeridos');
                }

                try {
                    $categoria->setId($_POST['id']);
                    $categoriaAntes = $categoria->getOne();
                    $categoria->setEstado($_POST['estado']);
                    
                    if ($categoria->updateEstado()) {
                        $categoriaDespues = $categoria->getOne();
                        ActualizacionesHelper::registrarCambio(
                            'categorias',
                            'cambiar_estado',
                            $categoriaAntes,
                            $categoriaDespues,
                            ['entidad' => 'categoria', 'registro_id' => (string)$_POST['id']]
                        );
                        echo json_encode([
                            'success' => true,
                            'message' => 'Estado actualizado correctamente'
                        ]);
                    } else {
                        throw new Exception('Error al actualizar el estado');
                    }
                } catch (Exception $e) {
                    error_log("Error al cambiar estado: " . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar el estado: ' . $e->getMessage()
                    ]);
                }
                break;

            case 'eliminar':
                // Permiso: Administradores pueden eliminar categorías
                
                if (!isset($_POST['id'])) {
                    throw new Exception('ID no proporcionado');
                }

                try {
                    $categoria->setId($_POST['id']);
                    $categoriaAntes = $categoria->getOne();

                    if ($categoria->delete()) {
                        $idEliminado = (string)$_POST['id'];
                        $datosBefore = $categoriaAntes ? [
                            'id' => $categoriaAntes->id ?? $idEliminado,
                            'nombre' => $categoriaAntes->nombre ?? null,
                            'descripcion' => $categoriaAntes->descripcion ?? null,
                            'imagen' => $categoriaAntes->imagen ?? null,
                            'estado' => $categoriaAntes->estado ?? null,
                        ] : ['id' => $idEliminado];

                        ActualizacionesHelper::registrarCambio(
                            'categorias',
                            'eliminar',
                            $datosBefore,
                            ['id' => $idEliminado, 'eliminado' => true],
                            ['entidad' => 'categoria', 'registro_id' => $idEliminado]
                        );

                        echo json_encode([
                            'success' => true,
                            'message' => 'Categoría eliminada correctamente'
                        ]);
                    } else {
                        $error = trim((string)$categoria->getLastError());
                        if ($error === '') {
                            $error = 'Error al eliminar la categoría';
                        }
                        throw new Exception($error);
                    }
                } catch (Exception $e) {
                    error_log("Error al eliminar categoría: " . $e->getMessage());
                    $message = $e->getMessage();
                    if ($message === 'Error al eliminar la categoría') {
                        echo json_encode([
                            'success' => false,
                            'message' => $message
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => $message
                        ]);
                    }
                }
                break;

            case 'reiniciar':
                if (!PermisosHelper::esSuperAdminSesion()) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Permisos insuficientes para reiniciar categorías'
                    ]);
                    exit;
                }

                try {
                    $resultado = $categoria->reiniciarCategorias();
                    echo json_encode($resultado);
                } catch (Exception $e) {
                    error_log('Error en acción reiniciar categorías: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al reiniciar categorías: ' . $e->getMessage()
                    ]);
                }
                exit;

            case 'deshacer':
                if (!PermisosHelper::esSuperAdminSesion()) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Permisos insuficientes para deshacer reinicio de categorías'
                    ]);
                    exit;
                }

                try {
                    $resultado = $categoria->deshacerCategorias();
                    echo json_encode($resultado);
                } catch (Exception $e) {
                    error_log('Error en acción deshacer categorías: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al restaurar categorías: ' . $e->getMessage()
                    ]);
                }
                exit;

            default:
                throw new Exception('Acción no válida');
        }
    }
} catch (Exception $e) {
    error_log("Error en CategoriaController: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 