<?php
    include_once 'sucursalController.php';
    include_once 'proveedorController.php';
    include_once 'tipoclienteController.php';
    include_once 'clienteController.php';
    include_once 'departamentoController.php';
    include_once 'grupoController.php';
    include_once 'subgrupoController.php';
    include_once 'materialController.php';


    if(isset($_GET['funcion']) && !empty($_GET['funcion']))
    {
        $funcion = $_GET['funcion'];

        switch ($funcion){
            case 'indexTipoCliente' :
                $tipocliente = new tipoclienteController();
                $tipocliente->index();
                break;
            case 'indexCliente' :
                $cliente = new clienteController();
                $cliente->index();
                break;
            case 'indexSucursal' :
                $sucursales = new sucursalController();
                $sucursales->index();
                break;
            case 'indexProveedor' :
                $proveedores = new proveedorController();
                $proveedores->index();
                break;
            case 'indexDepartamento' :
                $departamentos = new departamentoController();
                $departamentos->index();
                break;
            case 'indexGrupo' :
                $grupos = new grupoController();
                $grupos->index();
                break;
            case 'indexSubgrupo' :
                $subgrupos = new subgrupoController();
                $subgrupos->index();
                break;
            case 'indexMaterial' :
                $materiales = new materialController();
                $materiales->index();
                break;
        }
    }
?>