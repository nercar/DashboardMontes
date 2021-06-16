<?php 
require_once("../Classes/dompdf/autoload.inc.php");

$ngdc = $_GET['ngdc'];

// Se establece la conexion con la BBDD
$params = parse_ini_file('../dist/config.ini');

if ($params === false) {
	throw new \Exception("Error reading database configuration file");
}

// connect to the sql server database
$conStr = sprintf("sqlsrv:Server=%s,%d",
			$params['host_sql'],
			$params['port_sql']);
$connec = new \PDO($conStr, $params['user_sql'], $params['password_sql']);

$tcantidad = 0;
$timpuesto = 0;
$tsubtotal = 0;
$ttotal    = 0;

$sql = "SELECT
			cab.id, cab.fecha_crea, cab.fecha_vence, cab.usuario_crea,
			ti.nombre AS cedim, prov.codigo AS codprov,
			prov.descripcion AS nomprov,
			det.codigo, art.descripcion, det.cantidad, det.costo,
			ROUND((det.cantidad*det.costo)*(art.impuesto/100), 2) AS impuesto,
			ROUND(det.cantidad*det.costo, 2) AS subtotal
		FROM
			BDES.dbo.DBODC_cab AS cab
			INNER JOIN BDES.dbo.DBODC_det AS det ON det.id_cab = cab.id AND det.status = 0
			INNER JOIN BDES.dbo.ESProveedores AS prov ON prov.codigo = cab.proveedor
			INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = cab.centro_dist
			INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.codigo
		WHERE cab.status = 0 AND cab.id = ".$ngdc;

// Se ejecuta la consulta en la BBDD
$sql = $connec->query($sql);
if(!$sql) print_r($connec->errorInfo());

// Se prepara el array para almacenar los datos obtenidos
while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
	$datos[] = $row;
}

$enc = '
<script type="text/php">
	$logo_sup = $pdf->open_object();
	$alto = 50;
	$ancho = 150;
	$img_alto = 30; //Alto de la img
	$img_ancho = 70; // Ancho de la img
	$pdf->image("../dist/img/logo-ppal.png",$img_ancho,$img_alto,$ancho,$alto);
	$pdf->close_object();
	$pdf->add_object($logo_sup,"all");
	$pdf->page_text(490, 50, "Pág. {PAGE_NUM} de {PAGE_COUNT}", $font, 10, array(0, 0, 0));
</script>';

$cabecera =
'<div id="contenido">'. #Inicio contenido
	'<table class="tablacontenido">'.
			'<tr>'.
				'<td width="45%"><span class="titulo_segundario">NRO ÓRDEN DE COMPRA:</span>'.$datos[0]['id'].'</td>'.
				'<td width="55%">
					<span class="titulo_segundario">FECHA:</span>'.date('d-m-Y  H:i', strtotime($datos[0]['fecha_crea'])).
				'     <span class="titulo_segundario">VENCE:</span>'.date('d-m-Y', strtotime($datos[0]['fecha_vence'])).
				'</td>'.
			'</tr>'.
			'<tr>'.
				'<td><span class="titulo_segundario">CEDIM:</span>'.$datos[0]['cedim'].'</td>'.
				'<td><span class="titulo_segundario">USUARIO:</span>'.$datos[0]['usuario_crea'].'</td>'.
			'</tr>'.
			'<tr>'.
				'<td colspan="2"><span class="titulo_segundario">PROVEEDOR:</span>['.$datos[0]['codprov'].'] '.$datos[0]['nomprov'].'</td>'.
			'</tr>'.
	'</table>'.
	'<br>'.
	'<div align="center" with="100%"><span class="titulo">ARTÍCULOS DE LA ÓRDEN DE COMPRA</span></div>' .
'</div>';

$cabdetal = $enc.'<br>'.$cabecera;

$html  = '<html><head>';
$html .= '<meta charset="UTF-8">';
$html .= '<link href="../dist/css/print.css" rel="stylesheet" type="text/css">';
$html .= '
		<style>
			@page { margin: 230px 60px 60px 60px; }
			header { position: fixed; top: -140px; left: 0px; right: 0px; }
			p { page-break-after: never; }
			p:last-child { page-break-after: never; }
		</style>
	</head>';
$html .= '<body><header>' . $cabdetal . '</header><main>';

$i = 1;
$html .= 
	'<div id="contenido">'
		. '<table class="tablacontenido">'
			. '<thead>'
				. '<tr>'
					. '<th width="2%"  align="center"><b>No.</b></th>'
					. '<th width="8%"  align="center"><b>Código</b></th>'
					. '<th width="40%" align="center"><b>Descripción</b></th>'
					. '<th width="10%" align="center"><b>Cantidad</b></th>'
					. '<th width="10%" align="center"><b>Costo</b></th>'
					. '<th width="10%" align="center"><b>Impuesto</b></th>'
					. '<th width="10%" align="center"><b>Subtotal</b></th>'
					. '<th width="10%" align="center"><b>Total</b></th>'
				. '</tr>'
			. '</thead><tbody>';
foreach ($datos as $dato) {
	$html .=
		'<tr>'.
			'<td align="center">' . $i. '</td>'.
			'<td align="center">' . $dato['codigo'] . '</td>'.
			'<td align="left"  >' . $dato['descripcion'] . '</td>'.
			'<td align="right" >' . number_format(($dato['cantidad']*1), 2) . '</td>'.
			'<td align="right" >' . number_format(($dato['costo']*1), 2) . '</td>'.
			'<td align="right" >' . number_format(($dato['impuesto']*1), 2) . '</td>'.
			'<td align="right" >' . number_format(($dato['subtotal']*1), 2) . '</td>'.
			'<td align="right" >' . number_format(($dato['subtotal']*1)+($dato['impuesto']*1), 2) . '</td>'.
		'</tr>';
	$i++;
	$tcantidad += ($dato['cantidad']*1);
	$timpuesto += ($dato['impuesto']*1);
	$tsubtotal += ($dato['subtotal']*1);
	$ttotal    += (($dato['subtotal']*1)+($dato['impuesto']*1));
}		  
$html .= 
	'<tr>'.
		'<td colspan="3" align="right"><b>Total:</b></td>'.
		'<td align="right">' . number_format($tcantidad, 2) . '</td>'.
		'<td></td>'.
		'<td align="right">' . number_format($timpuesto, 2) . '</td>'.
		'<td align="right">' . number_format($tsubtotal, 2) . '</td>'.
		'<td align="right">' . number_format($ttotal   , 2) . '</td>'.
	'</tr>';
$html .= '</tbody></table></div></main></body></html>';

// echo $html;

$dompdf = new Dompdf\Dompdf();
$dompdf->set_paper("letter","portrait"); 
$dompdf->load_html($html);
$dompdf->render();
$dompdf->stream("odc2pdf_".$ngdc.".pdf");
?>