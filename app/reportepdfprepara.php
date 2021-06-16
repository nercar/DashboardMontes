<?php
	include("barcode.php");

	$code = str_pad($_GET['nrorden'], 5, "0", STR_PAD_LEFT);		
	barcode('../tmp/'.$code.'.png', $code, 40, 'horizontal', 'code128', true);
	$code = '../tmp/'.$code.'.png';

	header("Status: 301 Moved Permanently");
	header("Location: app/reportSoliDespacho.php?nrorden=" . $_GET['nrorden'] . "&code=" . $code);
	exit;
?>