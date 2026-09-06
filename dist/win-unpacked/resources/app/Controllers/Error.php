<?php 
    class Errors extends Controllers{
        public function __construct()
        {
            parent::__construct();
        }

        public function notFound()
        {
            $data['page_tag'] = "Error 404";
            $data['page_title'] = "Página no encontrada";
            $data['page_name'] = "error";
            $this->views->getView($this,"error", $data);
        }
    }

    $notFound = new Errors();
    $notFound->notFound();
?> 