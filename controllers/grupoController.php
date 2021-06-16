<?php

    include_once '../models/grupo.php';

    class grupoController
    {
        public function __construct()
        {
        }

        public function index()
        {
            //echo 'index desde grupoController';
            $listaGrupos = grupo::listaGrupos();
            foreach($listaGrupos as $valores)
            {
                echo '<option value="'.$valores[0].'">'.$valores[1].'</option>';
            }
        }
    }
?>