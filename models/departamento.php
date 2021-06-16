<?php

require_once 'conexionDB.php';

class departamento
{
    //atributos

    //constructor de la clase
    function __construct()
    {

    }

    //función para obtener todos las sucursales
    function listaDepartamentos(){
        // Conectar a la Base de Datos
        $db=Database::Conectar();
        //Realizar Consulta
        $select="SELECT codigo, descripcion FROM esdpto";
        $sql=$db->query($select);
        //Sucursales como un Array
        $listaDepartamentos=$sql->fetchAll();
        return $listaDepartamentos;

    }

}

?>