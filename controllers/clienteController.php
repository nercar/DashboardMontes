<?php

    include_once '../models/cliente.php';

    class clienteController
    {
        public function __construct()
        {
        }

        public function index()
        {
            $listaclientes = cliente::listaclientes();
            foreach($listaclientes as $valores)
            {
                echo '<option value="'.$valores[0].'">'.$valores[0].'</option>';
            }
        }
    }
?>