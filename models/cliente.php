<?php

require_once 'conexionDB.php';

class cliente
{
    //atributos

    //constructor de la clase
    function __construct()
    {

    }

    //función para obtener todos las sucursales
    function listaclientes(){
        // Conectar a la Base de Datos
        $db=Database::Conectar();
        //Realizar Consulta
        $select="SELECT rif, razon FROM esclientes";
        $sql=$db->query($select);
        //Sucursales como un Array
        $listaclientes=$sql->fetchAll();
        return $listaclientes;

    }

}

?>