<?php

require_once 'conexionDB.php';

class sucursal
{
    //atributos

    //constructor de la clase
    function __construct()
    {

    }

    //función para obtener todos las sucursales
    public static function listaSucursales(){
        // Conectar a la Base de Datos
        $db=Database::Conectar();
        //Realizar Consulta
        $select="SELECT id, nombre FROM tiendas";
        $sql=$db->query($select);
        //Sucursales como un Array
        $listaSucursales=$sql->fetchAll();
        return $listaSucursales;

    }

}

?>