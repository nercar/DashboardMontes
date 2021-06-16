<?php

require_once 'conexionDB.php';

class proveedor
{
    //atributos

    //constructor de la clase
    function __construct()
    {

    }

    //función para obtener todos las sucursales
    function listaProveedores(){
        // Conectar a la Base de Datos
        $db=Database::Conectar();
        //Realizar Consulta
        $select="SELECT codigo, descripcion FROM esproveedores";
        $sql=$db->query($select);
        //Sucursales como un Array
        $listaProveedores=$sql->fetchAll();
        return $listaProveedores;

    }

}

?>