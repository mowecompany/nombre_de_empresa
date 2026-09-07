<?php
    require_once ROOT_PATH . '/Models/StoreCompanyProfile.php';

    class Home extends Controllers
    {
        private $storeCompanyProfileModel;

        public function __construct()
        {
            parent::__construct();
            $this->storeCompanyProfileModel = new StoreCompanyProfile();
        }

        private function anexarPerfilEmpresa(array $data): array
        {
            $data['store_company'] = $this->storeCompanyProfileModel->obtenerPerfilPublico();
            return $data;
        }

        public function home()
        {
            $data['page_tag'] = NOMBRE_EMPRESA;
            $data['page_title'] = "Inicio";
            $data['page_name'] = "home";
            $data['categorias'] = [];
            $data['productos'] = [];

            try {
                $data['categorias'] = $this->model->getCategorias();
                $data['productos'] = $this->model->getProductosDestacados();
            } catch (Throwable $e) {
                error_log('HOME_CONTROLLER_ERROR: ' . $e->getMessage());
                $data['store_error'] = 'No fue posible cargar los datos de tienda en este momento.';
            }
            $data = $this->anexarPerfilEmpresa($data);
            $this->views->getView($this, "home", $data);
        }

        public function nosotros()
        {
            $data['page_tag'] = NOMBRE_EMPRESA . " - Nosotros";
            $data['page_title'] = "Sobre Nosotros";
            $data['page_name'] = "nosotros";
            $data = $this->anexarPerfilEmpresa($data);
            $this->views->getView($this, "nosotros", $data);
        }

        public function contacto()
        {
            $data['page_tag'] = NOMBRE_EMPRESA . " - Contacto";
            $data['page_title'] = "Contacto";
            $data['page_name'] = "contacto";
            $data = $this->anexarPerfilEmpresa($data);
            $this->views->getView($this, "contacto", $data);
        }
    }
?> 