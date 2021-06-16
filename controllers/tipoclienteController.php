<?php

    include_once '../models/tipocliente.php';

    class tipoclienteController
    {
        public function __construct()
        {
        }

        public function index()
        {
            //echo 'index desde UsuarioController';
            $listatipoclientes = tipocliente::listatipoclientes();
            foreach($listatipoclientes as $valores)
            {
                echo '<option value="'.$valores[0].'">'.$valores[1].'</option>';
            }
        }
    }
?>