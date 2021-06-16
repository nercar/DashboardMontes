<?php

require_once 'conexionDB.php';

class grupo
{
    //atributos

    //constructor de la clase
    function __construct()
    {

    }

    //función para obtener todos las sucursales
    function listaGrupos(){
        // Conectar a la Base de Datos
        $db=Database::Conectar();
        //Realizar Consulta
        $select="SELECT codigo, descripcion FROM esgrupos";
        $sql=$db->query($select);
        //Sucursales como un Array
        $listaGrupos=$sql->fetchAll();
        return $listaGrupos;

    }

}

?>