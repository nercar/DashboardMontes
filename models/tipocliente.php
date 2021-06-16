<?php

require_once 'conexionDB.php';

class tipocliente
{
    //atributos

    //constructor de la clase
    function __construct()
    {

    }

    //función para obtener todos las sucursales
    public static function listatipoclientes(){
        // Conectar a la Base de Datos
        $db=Database::Conectar();
        //Realizar Consulta
        $select="SELECT tipo, descripcion FROM tipocliente";
        $sql=$db->query($select);
        //Sucursales como un Array
        $listatipoclientes=$sql->fetchAll();
        return $listatipoclientes;

    }

}

?>