<?php
    class Views
    {
        function getView($controller,$view,$data="")
        {
            $controller = get_class($controller);
            if($controller == "Errors"){
                $view = "Views/Errors/error.php";
            }else if($controller == "Home"){
                $view = "Views/".$view.".php";
            }else{
                $view = "Views/".$controller."/".$view.".php";
            }
            require_once ($view);
        }
    }
?> 