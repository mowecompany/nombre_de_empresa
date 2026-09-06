<?php 
    require_once("Libraries/Core/Autoload.php");
    require_once("Libraries/Core/Views.php");
    require_once("Libraries/Core/Controllers.php");
    require_once("Libraries/Core/Conexion.php");
    require_once("Libraries/Core/Mysql.php");
    
    $controllerFile = "Controllers/".$controller.".php";
    if(file_exists($controllerFile))
    {
        require_once($controllerFile);
        $controller = new $controller();
        if(method_exists($controller, $method))
        {
            $controller->{$method}($params);
        }else{
            require_once("Controllers/Error.php");
        }
    }else{
        require_once("Controllers/Error.php");
    }
?> 