<?php

    include_once '../models/departamento.php';

    class departamentoController
    {
        public function __construct()
        {
        }

        public function index()
        {
            //echo 'index desde UsuarioController';
            $listaDepartamentos = departamento::listaDepartamentos();
            foreach($listaDepartamentos as $valores)
            {
                echo '<option value="'.$valores[0].'">'.$valores[1].'</option>';
            }
        }
    }
?>