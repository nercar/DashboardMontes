<?php

    include_once '../models/subgrupo.php';

    class subgrupoController
    {
        public function __construct()
        {
        }

        public function index()
        {
            //echo 'index desde subgrupoController';
            $listaSubgrupos = subgrupo::listaSubgrupos();
            foreach($listaSubgrupos as $valores)
            {
                echo '<option value="'.$valores[0].'">'.$valores[1].'</option>';
            }
        }
    }
?>