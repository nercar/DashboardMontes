<?php

    include_once '../models/material.php';

    class materialController
    {
        public function __construct()
        {
        }

        public function index()
        {
            $listaMateriales = material::listaMateriales();
            foreach($listaMateriales as $valores)
            {
                echo '<option value="'.$valores[0].'">'.$valores[0].'</option>';
            }
        }
    }
?>