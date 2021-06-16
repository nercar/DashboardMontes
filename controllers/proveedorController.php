<?php

    include_once '../models/proveedor.php';

    class proveedorController
    {
        public function __construct()
        {
        }

        public function index()
        {
            //echo 'index desde UsuarioController';
            $listaProveedores = proveedor::listaProveedores();
            foreach($listaProveedores as $valores)
            {
                echo '<option value="'.$valores[0].'">'.$valores[1].'</option>';
            }
        }
    }
?>