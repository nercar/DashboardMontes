<?php

// El mensaje
$mensaje = "Línea 1\r\nLínea 2\r\nLínea 3";

// Si cualquier línea es más larga de 70 caracteres, se debería usar wordwrap()
$mensaje = wordwrap($mensaje, 70, "\r\n");

// Enviarlo
$success = mail('nercar@gmail.com', 'Mi título', $mensaje);
if (!$success) {
    $errorMessage = error_get_last()['message'];
} else {
	$errorMessage = 'Correo enviado';
}

echo $errorMessage;

?>