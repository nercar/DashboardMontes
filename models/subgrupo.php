<?php

require_once 'conexionDB.php';

class subgrupo
{
    //atributos

    //constructor de la clase
    function __construct()
    {

    }

    //función para obtener todos las sucursales
    function listaSubgrupos(){
        // Conectar a la Base de Datos
        $db=Database::Conectar();
        //Realizar Consulta
        $select="SELECT codigo, descripcion FROM essubgrupos";
        $sql=$db->query($select);
        //Sucursales como un Array
        $listaSubgrupos=$sql->fetchAll();
        return $listaSubgrupos;

    }

}

?>