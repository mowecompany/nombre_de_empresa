<?php 
class HomeModel extends Mysql {
    private ?bool $usaEmpresaEnCatalogo = null;
    private ?bool $usaUsuarioEnCatalogo = null;
    private ?bool $usaDescuentoPorcentaje = null;
    private ?bool $usaPrecioOriginal = null;

    public function __construct() {
        parent::__construct();
    }

    private function obtenerUsuarioIdSesion(): int {
        if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0) {
            return (int)$_SESSION['usuario_id'];
        }

        if (isset($_SESSION['userData']['idusuario']) && (int)$_SESSION['userData']['idusuario'] > 0) {
            return (int)$_SESSION['userData']['idusuario'];
        }

        if (isset($_SESSION['userData']['id']) && (int)$_SESSION['userData']['id'] > 0) {
            return (int)$_SESSION['userData']['id'];
        }

        return 0;
    }

    private function getEmpresaIdSesion(): int {
        if (isset($_SESSION['store_public_empresa_id']) && (int)$_SESSION['store_public_empresa_id'] > 0) {
            return (int)$_SESSION['store_public_empresa_id'];
        }

        $empresaIdUsuario = 0;

        try {
            $usuarioId = $this->obtenerUsuarioIdSesion();
            if ($usuarioId > 0 && $this->existeColumna('usuarios', 'empresa_id')) {
                $sql = "SELECT empresa_id, admin_id FROM usuarios WHERE id = {$usuarioId} LIMIT 1";
                $row = $this->select($sql);
                $empresaIdUsuario = (int)($row['empresa_id'] ?? 0);
                if ($empresaIdUsuario <= 0) {
                    $adminId = (int)($row['admin_id'] ?? ($_SESSION['admin_id'] ?? ($_SESSION['userData']['admin_id'] ?? 0)));
                    if ($adminId > 0) {
                        $sqlAdmin = "SELECT empresa_id FROM usuarios WHERE id = {$adminId} LIMIT 1";
                        $rowAdmin = $this->select($sqlAdmin);
                        $empresaIdUsuario = (int)($rowAdmin['empresa_id'] ?? 0);
                    }
                }
            }
        } catch (Throwable $e) {
        }

        if ($empresaIdUsuario > 0) {
            $_SESSION['empresa_id'] = $empresaIdUsuario;
            if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
                $_SESSION['userData']['empresa_id'] = $empresaIdUsuario;
            }
            return $empresaIdUsuario;
        }

        if (isset($_SESSION['empresa_id']) && (int)$_SESSION['empresa_id'] > 0) {
            return (int)$_SESSION['empresa_id'];
        }

        if (isset($_SESSION['userData']['empresa_id']) && (int)$_SESSION['userData']['empresa_id'] > 0) {
            $_SESSION['empresa_id'] = (int)$_SESSION['userData']['empresa_id'];
            return (int)$_SESSION['userData']['empresa_id'];
        }

        return 0;
    }

    private function existeColumna(string $tabla, string $columna): bool {
        try {
            return dbColumnExists(Database::connect(), strClean($tabla), strClean($columna));
        } catch (Throwable $e) {
            return false;
        }
    }

    private function usaFiltroEmpresa(): bool {
        if ($this->usaEmpresaEnCatalogo !== null) {
            return $this->usaEmpresaEnCatalogo;
        }

        $this->usaEmpresaEnCatalogo = $this->existeColumna('productos', 'empresa_id') && $this->existeColumna('categorias', 'empresa_id');
        return $this->usaEmpresaEnCatalogo;
    }

    private function usaFiltroUsuario(): bool {
        return false;
    }

    private function usaDescuentoPorcentaje(): bool {
        if ($this->usaDescuentoPorcentaje !== null) {
            return $this->usaDescuentoPorcentaje;
        }

        $this->usaDescuentoPorcentaje = $this->existeColumna('productos', 'descuento_porcentaje');
        return $this->usaDescuentoPorcentaje;
    }

    private function usaPrecioOriginal(): bool {
        if ($this->usaPrecioOriginal !== null) {
            return $this->usaPrecioOriginal;
        }

        $this->usaPrecioOriginal = $this->existeColumna('productos', 'precio_original');
        return $this->usaPrecioOriginal;
    }

    private function camposPrecioCatalogo(): array {
        $usaDescuento = $this->usaDescuentoPorcentaje();
        $usaOriginal = $this->usaPrecioOriginal();

        if ($usaDescuento) {
            $baseOriginal = $usaOriginal
                ? "CASE WHEN p.precio_original IS NOT NULL AND p.precio_original > 0 THEN p.precio_original ELSE p.precio END"
                : "p.precio";

            $precioFinal = "CASE WHEN COALESCE(p.descuento_porcentaje, 0) > 0
                               THEN ROUND(({$baseOriginal}) * (1 - (COALESCE(p.descuento_porcentaje, 0) / 100)), 2)
                               ELSE p.precio END";

            return [
                "{$precioFinal} AS precio",
                "{$baseOriginal} AS precio_original",
                "{$precioFinal} AS precio_final",
                "COALESCE(p.descuento_porcentaje, 0) AS descuento_porcentaje",
            ];
        }

        return [
            "p.precio AS precio",
            "p.precio AS precio_original",
            "p.precio AS precio_final",
            "0 AS descuento_porcentaje",
        ];
    }

    public function getCategorias() {
        $empresaId = $this->getEmpresaIdSesion();
        $usuarioId = $this->obtenerUsuarioIdSesion();
        $filtroEmpresa = ($this->usaFiltroEmpresa() && $empresaId > 0) ? " AND empresa_id = {$empresaId}" : "";
        $filtroUsuario = ($this->usaFiltroUsuario() && $usuarioId > 0) ? " AND usuario_id = {$usuarioId}" : "";

        if ($this->usaFiltroEmpresa() && $empresaId <= 0) {
            return [];
        }

        $sql = "SELECT id as idcategoria, nombre, descripcion, imagen as portada
                FROM categorias WHERE estado = 1{$filtroEmpresa}{$filtroUsuario} ORDER BY id DESC LIMIT 6";
        $result = $this->select_all($sql);
        foreach ($result as &$row) {
            $ruta = ROOT_PATH . '/Assets/images/categorias/' . $row['portada'];
            if (empty($row['portada']) || !is_file($ruta)) {
                $row['portada'] = 'favicon.ico';
            }
        }
        return $result;
    }

    public function getProductos() {
        $empresaId = $this->getEmpresaIdSesion();
        $usuarioId = $this->obtenerUsuarioIdSesion();
        $filtroEmpresa = ($this->usaFiltroEmpresa() && $empresaId > 0) ? " AND p.empresa_id = {$empresaId} AND c.empresa_id = {$empresaId}" : "";
        $filtroUsuario = ($this->usaFiltroUsuario() && $usuarioId > 0) ? " AND p.usuario_id = {$usuarioId} AND c.usuario_id = {$usuarioId}" : "";

        if ($this->usaFiltroEmpresa() && $empresaId <= 0) {
            return [];
        }

        $camposPrecio = $this->camposPrecioCatalogo();

        $sql = "SELECT p.id as idproducto, 
                       p.nombre, 
                       p.descripcion, 
                       " . implode(', ', $camposPrecio) . ",
                       p.imagen as url_image,
                       c.nombre as categoria
                FROM productos p 
                INNER JOIN categorias c ON p.categoria_id = c.id
                WHERE p.estado = 1{$filtroEmpresa}{$filtroUsuario}
                ORDER BY p.id DESC LIMIT ".CANTPORDHOME;
        $result = $this->select_all($sql);
        foreach ($result as &$row) {
            $ruta = ROOT_PATH . '/Assets/images/productos/' . $row['url_image'];
            if (empty($row['url_image']) || !is_file($ruta)) {
                $row['url_image'] = 'favicon.ico';
            }
        }
        return $result;
    }

    public function getProductosDestacados() {
        $empresaId = $this->getEmpresaIdSesion();
        $usuarioId = $this->obtenerUsuarioIdSesion();
        $filtroEmpresa = ($this->usaFiltroEmpresa() && $empresaId > 0) ? " AND p.empresa_id = {$empresaId} AND c.empresa_id = {$empresaId}" : "";
        $filtroUsuario = ($this->usaFiltroUsuario() && $usuarioId > 0) ? " AND p.usuario_id = {$usuarioId} AND c.usuario_id = {$usuarioId}" : "";

        if ($this->usaFiltroEmpresa() && $empresaId <= 0) {
            return [];
        }

        $camposPrecio = $this->camposPrecioCatalogo();

        $sql = "SELECT p.id as idproducto, 
                       p.nombre, 
                       p.descripcion, 
                       " . implode(', ', $camposPrecio) . ",
                       p.imagen as url_image,
                       p.stock,
                       c.nombre as categoria
                FROM productos p 
                INNER JOIN categorias c ON p.categoria_id = c.id
                WHERE p.estado = 1{$filtroEmpresa}{$filtroUsuario}
            ORDER BY p.id DESC";
        $result = $this->select_all($sql);
        foreach ($result as &$row) {
            $ruta = ROOT_PATH . '/Assets/images/productos/' . $row['url_image'];
            if (empty($row['url_image']) || !is_file($ruta)) {
                $row['url_image'] = 'favicon.ico';
            }
        }
        return $result;
    }

    public function getProductosCategoria($idcategoria){
        $empresaId = $this->getEmpresaIdSesion();
        $usuarioId = $this->obtenerUsuarioIdSesion();
        $idcategoria = intval($idcategoria);
        $filtroEmpresa = ($this->usaFiltroEmpresa() && $empresaId > 0) ? " AND p.empresa_id = {$empresaId} AND c.empresa_id = {$empresaId}" : "";
        $filtroUsuario = ($this->usaFiltroUsuario() && $usuarioId > 0) ? " AND p.usuario_id = {$usuarioId} AND c.usuario_id = {$usuarioId}" : "";

        if ($this->usaFiltroEmpresa() && $empresaId <= 0) {
            return [];
        }

        $camposPrecio = $this->camposPrecioCatalogo();

        $sql = "SELECT p.id as idproducto,
                       p.nombre,
                       p.descripcion,
                       p.categoria_id,
                       c.nombre as categoria,
                       " . implode(', ', $camposPrecio) . ",
                       p.stock,
                       p.imagen as url_image
                FROM productos p 
                INNER JOIN categorias c ON p.categoria_id = c.id
                WHERE p.estado = 1 
                AND p.categoria_id = {$idcategoria}{$filtroEmpresa}{$filtroUsuario}";
        $result = $this->select_all($sql);
        foreach ($result as &$row) {
            $ruta = ROOT_PATH . '/Assets/images/productos/' . $row['url_image'];
            if (empty($row['url_image']) || !is_file($ruta)) {
                $row['url_image'] = 'favicon.ico';
            }
        }
        return $result;
    }
}
?> 