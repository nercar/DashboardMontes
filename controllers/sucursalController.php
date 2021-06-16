<?php

    include_once '../models/sucursal.php';

    class sucursalController
    {
        public function __construct()
        {
        }

        public function index()
        {
            //echo 'index desde UsuarioController';
            $listaSucursales = sucursal::listaSucursales();
            foreach($listaSucursales as $valores)
            {
                echo '<option value="'.$valores[0].'">'.$valores[1].'</option>';
            }
        }
    }
?>
