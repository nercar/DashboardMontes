<?php
require_once("../Classes/dompdf/autoload.inc.php");
require_once("../models/conexionDB.php");

$nropedido=$_GET['nrorden'];
$code=$_GET['code'];
$total_pedidos=0;
$total_bultos=0;
$filas=0;
$pages=1;

$db=Database::ConectarSQLServer();

$sql = "USE BDES;";
$sql = $db->query($sql);

$sql = "SELECT RIGHT((CAST('00000' AS VARCHAR) + CAST(solipedi_id AS VARCHAR)), 5) AS pedido
		FROM BDES.dbo.BISolicPedido WITH (NOLOCK) WHERE solipedi_nrodespacho = '$nropedido'";
$sql = $db->query($sql);
$pedidos = '';
while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
	$pedidos .= $row['pedido'] . ', ';
}
$pedidos = substr($pedidos, 0, -2);

$sql = "SELECT DISTINCT a.solipedi_nrodespacho, a.solipedi_fechaespera, b.Nombre,
		a.solipedi_status, a.solipedi_usuarioespera, b.direccion, a.solipedi_responsable
		FROM BDES.dbo.BISolicPedido AS a WITH (NOLOCK)
		INNER JOIN BDES.dbo.ESSucursales AS b ON a.localidad = b.codigo
		WHERE solipedi_nrodespacho='$nropedido'";

$resulSql =$db->query($sql);
$row = $resulSql->fetch();

$enc = '
<script type="text/php">
	$logo_sup = $pdf->open_object();
	$alto = 50;
	$ancho = 150;
	$img_alto = 30; //Alto de la img
	$img_ancho = 70; // Ancho de la img
	$pdf->image("../dist/img/logo-ppal.png",$img_ancho,$img_alto,$ancho,$alto);
	$pdf->image("' . $code . '",255,40,110,40);
	$pdf->close_object();
	$pdf->add_object($logo_sup,"all");
	$pdf->page_text(490, 50, "Pág. {PAGE_NUM} de {PAGE_COUNT}", $font, 10, array(0, 0, 0));
</script>';

$cabecera =
'<div id="contenido">'. #Inicio contenido
	'<table class="tablacontenido">'.
			'<tr>'.
				'<td width="45%"><span class="titulo_segundario">NRO DESPACHO:'.str_pad($row['solipedi_nrodespacho'], 5, "0", STR_PAD_LEFT).'</span></td>'.
				'<td width="55%"><span class="titulo_segundario">FECHA:'.date('d-m-Y  H:i', strtotime($row['solipedi_fechaespera'])).'</span></td>'.
			'</tr>'.
			'<tr>'.
				'<td><span class="titulo_segundario">TIENDA:</span>'.$row['Nombre'].'</td>'.
				'<td><span class="titulo_segundario">USURAIO:</span>'.$row['solipedi_usuarioespera'].'</td>'.
			'</tr>'.
			'<tr>'.
				'<td><span class="titulo_segundario">DIRECCI&Oacute;N:</span>'.$row['direccion'].'</td>'.
				'<td rowspan="2"><span class="titulo_segundario">PEDIDOS:</span>'.$pedidos.'</td>'.
			'</tr>'.
			'<tr>'.
				'<td><span class="titulo_segundario">RESPONSABLE:</span>'.$row['solipedi_responsable'].'</td>'.
			'</tr>'.
	'</table>'.
	'<br>'.
	'<div align="center" with="100%"><span class="titulo">ARTICULOS A DESPACHAR</span></div>' .
'</div>';

$cabdetal = '' //'<p></p>'
	// . '<br><br><br>'
	. $enc
	. '<br>'
	// . $nrofecha
	. $cabecera;

$html = '<html><head>';
$html .= '<meta charset="UTF-8">';
$html .= '<link href="../dist/css/print.css" rel="stylesheet" type="text/css">';
$html .= '
	<style>
		@page { margin: 250px 60px 60px 60px; }
		header { position: fixed; top: -160px; left: 0px; right: 0px; }
		p { page-break-after: never; }
		p:last-child { page-break-after: never; }
	</style>
</head>';
$html .= '<body><header>' . $cabdetal . '</header><main>';

$sql2 = "SELECT
			d.solipedidet_codigo,
			d.solipedidet_nrodespacho,
			(SELECT TOP 1 descripcion
				FROM BDES.dbo.ESProveedores WITH (NOLOCK) WHERE codigo =
				(SELECT TOP 1 proveedor FROM BDES.dbo.ESArticulosxProv WITH (NOLOCK)
				WHERE ARTICULO = d.solipedidet_codigo)
			) AS proveedor,
			( SELECT TOP 1 a.descripcion FROM BDES.dbo.ESARTICULOS AS a WITH (NOLOCK) WHERE a.codigo= d.solipedidet_codigo ) AS descripcion,
			( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b WITH (NOLOCK) WHERE b.escodigo = d.solipedidet_codigo ORDER BY barra DESC ) AS barra,
			d.solipedidet_empaque AS empaque,
			SUM(d.solipedidet_pedido) AS pedido
		FROM
			BDES.dbo.vw_soli_pedi_det AS d WITH (NOLOCK)
		WHERE d.solipedidet_despachado = 0 AND
			d.solipedidet_nrodespacho IN ( $nropedido )
		GROUP BY
			d.solipedidet_codigo, d.solipedidet_nrodespacho, d.solipedidet_empaque
		ORDER BY
			proveedor, descripcion";

$resulSql2 = $db->query($sql2);
$datos = [];
while ($rowdet = $resulSql2->fetch(\PDO::FETCH_ASSOC)) {
	$datos[] = $rowdet;
}
$i = 1;
$prov = '';
$html .=
	'<div id="contenido">'
		. '<table class="tablacontenido">'
			. '<thead>'
				. '<tr>'
					. '<th width="2%"  align="center"><b>No.</b></th>'
					. '<th width="8%"  align="center"><b>Código</b></th>'
					. '<th width="50%" align="center"><b>Descripción</b></th>'
					. '<th width="16%" align="center"><b>Barra</b></th>'
					. '<th width="8%"  align="center"><b>Empaque</b></th>'
					. '<th width="8%"  align="center"><b>Pedido</b></th>'
					. '<th width="8%"  align="center"><b>C.Bulto</b></th>'
				. '</tr>'
			. '</thead><tbody>';
foreach ($datos as $rowdet) {
	if($prov!=$rowdet['proveedor']) {
		$prov = $rowdet['proveedor'];
		$html .= '<tr><td colspan="7" align="center"><b>'.$rowdet['proveedor'].'</b></td></tr>';
	}
	$empaque = $rowdet['empaque'];
	if($rowdet['empaque']==0) {
		$empaque = 1;
	}
	$html .=
		'<tr>'.
			'<td align="center">' . $i. '</td>'.
			'<td align="center">' . $rowdet['solipedidet_codigo'] . '</td>'.
			'<td align="left"  >' . $rowdet['descripcion'] . '</td>'.
			'<td align="center">' . $rowdet['barra'] . '</td>'.
			'<td align="right" >' . number_format($empaque, 2) . '</td>'.
			'<td align="right" >' . number_format($rowdet['pedido'], 2) . '</td>'.
			'<td align="right" >' . number_format($rowdet['pedido']/$empaque, 2) . '</td>'.
		'</tr>';
	$i++;

	$total_pedidos += $rowdet['pedido'];
	$total_bultos  += ($rowdet['pedido']/$empaque);
}
$html .=
	'<tr>'.
		'<td colspan="5" align="right"><b>Total:</b></td>'.
		'<td align="right">' . number_format($total_pedidos, 2) . '</td>'.
		'<td align="right">' . number_format($total_bultos, 2) . '</td>'.
	'</tr>';
$html .= '</tbody></table></div></main></body></html>';

// echo $html;

$dompdf = new Dompdf\Dompdf();
$dompdf->set_paper("letter","portrait");
$dompdf->load_html($html);
$dompdf->render();
$dompdf->stream("ordendespacho_".$nropedido.".pdf");

unlink($code);

?>