<?php 
require_once("../Classes/dompdf/autoload.inc.php");

$np = $_GET['np'];

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

// Se prepara la consulta a la base de datos
$sql = "SELECT DISTINCT
			d.solipedi_id, d.solipedi_fechasoli, d.solipedi_usuariosoli,
			ti.nombre AS tienda,
			d.solipedidet_codigo, d.solipedidet_empaque AS empaque,
			( SELECT TOP 1 a.descripcion
				FROM BDES.dbo.ESARTICULOS AS a
				WHERE a.codigo= d.solipedidet_codigo ) AS descripcion,
			( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
				WHERE b.escodigo = d.solipedidet_codigo
				AND b.codigoedi = 1) AS barra,
			( SELECT TOP 1 SUM ( c.solipedidet_pedido )
				FROM BDES.dbo.BISoliPediDet AS c
				WHERE c.solipedidet_codigo= d.solipedidet_codigo AND c.solipedi_id = $np
				GROUP BY solipedidet_codigo ) AS total_pedidos
		FROM BDES.dbo.vw_soli_pedi_det AS d
		INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = d.localidad
		WHERE d.solipedi_id = $np";

$sql = $connec->query($sql);

// Se prepara el array para los datos obtenidos
$datos = [];
while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
	$datos[]=[
		'numero'      => str_pad($row['solipedi_id'], 5, "0", STR_PAD_LEFT),
		'fecha'       => date('d-m-Y  H:i', strtotime($row['solipedi_fechasoli'])),
		'tienda'      => $row['tienda'],
		'usuario'     => $row['solipedi_usuariosoli'],
		'codigo'      => $row['solipedidet_codigo'],
		'barra'       => $row['barra'],
		'descripcion' => $row['descripcion'],
		'unidad'      => $row['total_pedidos'],
		'cajas'       => round(($row['total_pedidos'] / $row['empaque']), 2),
		'empaque'     => $row['empaque'],
	];
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
				'<td width="45%"><span class="titulo_segundario">NRO PEDIDO:</span>'.
					$datos[0]['numero'].
				'</td>'.
				'<td width="55%">
					<span class="titulo_segundario">FECHA:</span>'.
					$datos[0]['fecha'].
				'</td>'.
			'</tr>'.
			'<tr>'.
				'<td><span class="titulo_segundario">TIENDA:</span>'.$datos[0]['tienda'].'</td>'.
				'<td><span class="titulo_segundario">USUARIO:</span>'.$datos[0]['usuario'].'</td>'.
			'</tr>'.
	'</table>'.
	'<br>'.
	'<div align="center" with="100%"><span class="titulo">ARTÍCULOS DEL PEDIDO</span></div>'.
'</div>';

$cabdetal = $enc.'<br>'.$cabecera;

$html  = '<html><head>';
$html .= '<meta charset="UTF-8">';
$html .= '<link href="../dist/css/print.css" rel="stylesheet" type="text/css">';
$html .= '
		<style>
			@page { margin: 200px 60px 60px 60px; }
			header { position: fixed; top: -110px; left: 0px; right: 0px; }
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
					. '<th width="5%"  align="center"><b>No.</b></th>'
					. '<th width="10%" align="center"><b>Código</b></th>'
					. '<th width="15%" align="center"><b>Barra</b></th>'
					. '<th width="50%" align="center"><b>Descripción</b></th>'
					. '<th width="10%" align="center"><b>Cantidad</b></th>'
					. '<th width="10%" align="center"><b>Despacho</b></th>'
				. '</tr>'
			. '</thead><tbody>';
foreach ($datos as $dato) {
	$html .=
		'<tr>'.
			'<td align="center">' . $i. '</td>'.
			'<td align="center">' . $dato['codigo'] . '</td>'.
			'<td align="center">' . $dato['barra'] . '</td>'.
			'<td align="left"  >' . $dato['descripcion'] . '</td>'.
			'<td align="right" >' . number_format(($dato['unidad']*1), 2) . '</td>'.
			'<td></td>'.
		'</tr>';
	$i++;
	$tcantidad += ($dato['unidad']*1);
}		  
$html .= 
	'<tr>'.
		'<td colspan="4" align="right"><b>Total:</b></td>'.
		'<td align="right">' . number_format($tcantidad, 2) . '</td>'.
		'<td></td>'.
	'</tr>';
$html .= '</tbody></table></div></main></body></html>';

// echo $html;

$dompdf = new Dompdf\Dompdf();
$dompdf->set_paper("letter","portrait"); 
$dompdf->load_html($html);
$dompdf->render();
$dompdf->stream("pedido2pdf_".$datos[0]['numero'].".pdf");
?>