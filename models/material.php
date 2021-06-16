<?php

require_once 'conexionDB.php';

class material
{
    //atributos

    //constructor de la clase
    function __construct()
    {

    }

    //función para obtener todos las sucursales
    function listamateriales(){
        // Conectar a la Base de Datos
        $db=Database::Conectar();
        //Realizar Consulta
        $select="SELECT codigo, descripcion FROM esarticulos";
        $sql=$db->query($select);
        //Sucursales como un Array
        $listamateriales=$sql->fetchAll();
        return $listamateriales;

    }

}

?>