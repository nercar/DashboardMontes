<?php 
	require_once("../Classes/dompdf/autoload.inc.php");

	$idp = $_GET['idp'];
	$idl = $_GET['idl'];
	$nc  = $_GET['nc'];
	$nf  = $_GET['nf'];

	$srvvin = $idp;
	$server = explode('\\', $idl);
	$nrocaj = $nc;
	$nrofac = $nf;

	$lineas = 0;

	// connect to the sql server database
	if(count($server)>1) {
		$conStr = sprintf("sqlsrv:Server=%s\%s;", $server[0], $server[1]);
	} else {
		$conStr = sprintf("sqlsrv:Server=%s,%d;", $server[0], '1433');
	}
	
	$connec = new \PDO($conStr, 'sa', '');

	$sqlf = "SELECT DESCRIPCION FROM BDES.dbo.ESMonedas
			WHERE PREDETERMINADA = 1 AND ACTIVA = 1";

	$sqlf = $connec->query($sqlf);
	if(!$sqlf) print_r($connec->errorInfo());
	$row = $sqlf->fetch();
	$moneda = $row['DESCRIPCION'];

	$sqlf = "SELECT DESCRIPCION, CAST(PORCENTAJE AS NUMERIC(5, 2)) AS PORCENTAJE
			FROM BDES.dbo.ESImp ORDER BY PORCENTAJE";

	$sqlf = $connec->query($sqlf);
	if(!$sqlf) print_r($connec->errorInfo());
	$impuestos = [];
	while ($row = $sqlf->fetch(\PDO::FETCH_ASSOC)) {
		if($row['PORCENTAJE']==0) {
			$label = $row['DESCRIPCION'];
		} else {
			$label = 'Iva ('.intval($row['PORCENTAJE']).'%)';
		}
		$impuestos[] = [
			'porcentaje' => $row['PORCENTAJE'],
			'label'      => $label,
			'base'       => 0,
			'impto'      => 0,
		];
	}
	
	$sqlf = "SELECT * FROM BDES_POS.dbo.ESCAJAS_ENCABEZADOFAC
			WHERE CABECERO = 1 AND CAJA = $nrocaj ORDER BY LINEA";

	$sqlf = $connec->query($sqlf);
	if(!$sqlf) print_r($connec->errorInfo());
	$encabezados = [];
	while ($row = $sqlf->fetch(\PDO::FETCH_ASSOC)) {
		$encabezados[] = $row;
	}

	$sqlf = "SELECT * FROM BDES_POS.dbo.ESCAJAS_ENCABEZADOFAC
			WHERE CABECERO = 0 AND CAJA = $nrocaj ORDER BY LINEA";

	$sqlf = $connec->query($sqlf);
	if(!$sqlf) print_r($connec->errorInfo());
	$piedepagina = [];
	while ($row = $sqlf->fetch(\PDO::FETCH_ASSOC)) {
		$piedepagina[] = $row;
	}

	$sqlf = "SELECT CONVERT(VARCHAR, FECHA, 105) AS FecDian,
				CAST(FACDESDE AS VARCHAR) AS IniDian,
				CAST(FACHASTA AS VARCHAR) AS FinDian,
				PREFIJO AS PrfDian, RESOLUCIONDIAN AS ResDian
			FROM BDES_POS.dbo.ESCAJAS_DIAN
			WHERE CAJA = $nrocaj";

	$sqlf = $connec->query($sqlf);
	if(!$sqlf) print_r($connec->errorInfo());
	$datosdian = [];
	while ($row = $sqlf->fetch(\PDO::FETCH_ASSOC)) {
		$datosdian[] = $row;
	}

	$sqlf = "SELECT fpa.descripcion AS DESCRIPCION, FP.NUMERO, FP.MONTO
			FROM BDES_POS.dbo.ESVENTASPOS_FP AS fp
			INNER JOIN BDES.dbo.ESFormasPago AS fpa ON fpa.codigo = fp.DENOMINACION
			WHERE CAJA = $nrocaj AND DOCUMENTO = $nrofac
			ORDER BY LINEA";

	$sqlf = $connec->query($sqlf);
	if(!$sqlf) print_r($connec->errorInfo());
	$formaspago = [];
	while ($row = $sqlf->fetch(\PDO::FETCH_ASSOC)) {
		$formaspago[] = $row;
	}

	$sqlf = "SELECT
				CONVERT(VARCHAR, cab.FECHA, 105) + ' ' +
				CONVERT(VARCHAR, cab.FECHA, 8) AS FECHA,
				cab.CAJA, cab.DOCUMENTO, us.descripcion AS CAJERO,
				cab.RAZON, cab.IDCLIENTE, cab.DIRECCION, cab.LOCALIDAD,
				CAST(PORC AS NUMERIC(5, 2)) AS PORC,
				SUM(det.IMPUESTO) AS IMPUESTO, art.DESCRIPCION, SUM(det.CANTIDAD) AS CANTIDAD, det.PRECIOREAL,
				(CASE WHEN det.PROMODSCTO > 0
				 THEN det.PRECIOREAL - det.PROMODSCTO
				 ELSE 0 END) AS PROMOCION, ROUND((det.PRECIOREAL*(1+(det.PORC/100))), 2) AS PRECIO,
				ROUND((
				(CASE WHEN det.PROMODSCTO > 0
				 THEN det.PRECIOREAL - det.PROMODSCTO
				 ELSE det.PRECIOREAL END) * SUM(det.CANTIDAD)), 2) AS BASE,
				ROUND((
				(CASE WHEN det.PROMODSCTO > 0
				 THEN det.PRECIOREAL - det.PROMODSCTO
				 ELSE det.PRECIOREAL END) * SUM(det.CANTIDAD))+SUM(det.IMPUESTO), 2) AS TOTAL
			FROM BDES_POS.dbo.ESVENTASPOS_DET AS det
			INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.ARTICULO
			INNER JOIN BDES_POS.dbo.ESVENTASPOS AS cab ON cab.DOCUMENTO = det.DOCUMENTO AND cab.CAJA = det.CAJA
			INNER JOIN BDES.dbo.ESUsuarios AS us ON us.codusuario = cab.CAJERO
			WHERE det.DOCUMENTO = $nrofac AND det.CAJA = $nrocaj
			GROUP BY cab.FECHA, cab.CAJA, cab.DOCUMENTO, cab.RAZON, cab.IDCLIENTE, cab.DIRECCION, us.descripcion,
				art.descripcion, det.PORC, det.PRECIOREAL, det.PROMODSCTO, cab.LOCALIDAD
			ORDER BY det.PROMODSCTO, art.DESCRIPCION";

	$sqlf = $connec->query($sqlf);
	if(!$sqlf) print_r($connec->errorInfo());

	$totalfact = 0;
	$base_exen = 0;
	$base_grav = 0;
	$imptoexen = 0;
	$imptograv = 0;
	$base_gral = 0;
	$imptogral = 0;
	$totcambio = 0;

	$datosfac = [];
	while ($row = $sqlf->fetch(\PDO::FETCH_ASSOC)) {
		$datosfac[] = $row;
		$totalfact+= $row['TOTAL'];
		$key = -1;
		for($i=0; $i < count($impuestos); $i++) {
			if($impuestos[$i]['porcentaje']==$row['PORC']) {
				$key = $i;
				break;
			}
		}
		$impuestos[$key]['base']  += $row['BASE'];
		$impuestos[$key]['impto'] += $row['IMPUESTO'];
	}

	$html ='<!doctype html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';

	$html.='</head><body>';
	$html.="<style> 
				body{ font-family: monospace; font-size: 8pt; line-height: 8pt; }
				html { margin: 15px 5px 15px 5px; }
			</style>";

	# Contenido HTML del documento que queremos generar en PDF.
	foreach ($encabezados as $linea) {
		$html.= '<center>'.$linea['TEXTO'].'</center>';
		$lineas++;
	}

	$html.= '<br>';
	$html.= 'Fecha: '.$datosfac[0]['FECHA'].'<br>';
	$html.= 'Caja: '.str_pad($datosfac[0]['CAJA'], 3, '0', STR_PAD_LEFT);
	$html.= str_repeat('&nbsp;', 8).'Factura de Venta: '.$datosfac[0]['DOCUMENTO'].'<br>';
	$html.= 'Cajero: '.$datosfac[0]['CAJERO'].'<br>';
	$html.= str_repeat('-', 42).'<br>';
	$html.= 'Cliente: '.substr($datosfac[0]['RAZON'], 0, 33).'<br>';
	$html.= 'Id: '.$datosfac[0]['IDCLIENTE'].'<br>';
	$html.= 'Dir: '.substr($datosfac[0]['DIRECCION'], 0, 35).'<br>';
	$html.= str_repeat('-', 42).'<br>';
	$html.= '<center>Factura de Venta</center>';
	$html.= str_repeat('&nbsp;', 4).'%'.str_repeat('&nbsp;', 15).'Item<br>';
	$html.= 'Cant.'.str_repeat('&nbsp;', 4).'Precio'.str_repeat('&nbsp;', 7);
	$html.= 'Promo'.str_repeat('&nbsp;', 7).'Total<br>';
	$html.= str_repeat('-', 42).'<br>';

	$lineas+= 13;

	foreach ($datosfac as $dato) {
		if($dato['PORC']>0) {
			$html.= '('.intval($dato['PORC']).'%)'.str_repeat('&nbsp;', 2);
		} else {
			$html.= '(E)'.str_repeat('&nbsp;', 4);
		}
		$Cant   = number_format($dato['CANTIDAD'], 2);
		$Precio = number_format($dato['PRECIO'], 2);
		$Promo  = number_format($dato['PROMOCION'], 2);
		$Total  = number_format($dato['TOTAL'], 2);
		$html  .= substr($dato['DESCRIPCION'], 0, 35).'<br>';
		$html  .= str_repeat('&nbsp;',  6-strlen($Cant)).$Cant.'&nbsp;';
		$html  .= str_repeat('&nbsp;', 10-strlen($Precio)).$Precio.'&nbsp;';
		$html  .= str_repeat('&nbsp;', 10-strlen($Promo)).$Promo.'&nbsp;';
		$html  .= str_repeat('&nbsp;', 13-strlen($Total)).$Total.'<br>';
		$lineas+= 2;
	}
	$html.= str_repeat('-', 42).'<br>';
	$Total = number_format($totalfact, 2);
	$html.= 'Total'.str_repeat('&nbsp;', 37-strlen($Total)).$Total.'<br>';
	$html.= str_repeat('-', 42).'<br>';
	$html.= '<center>** Moneda '.$moneda.' **</center>';

	$lineas+= 4;

	foreach ($formaspago as $pago) {
		$fpago = substr($pago['DESCRIPCION'], 0, 13);
		$fnume = $pago['NUMERO'];  
		$ftota = number_format($pago['MONTO'], 2);
		$html.= $fpago . str_repeat('&nbsp;', 14-strlen($fpago));
		$html.= $fnume . str_repeat('&nbsp;', 15-strlen($fnume));
		$html.= str_repeat('&nbsp;', 13-strlen($ftota)).$ftota.'<br>';
		$lineas++;
	}
	$html.= '<br>'.str_repeat('-', 42).'<br>';

	$html.= 'Tipo'.str_repeat('&nbsp;', 10);
	$html.= str_repeat('&nbsp;', 5).'Base'.str_repeat('&nbsp;', 5);
	$html.= str_repeat('&nbsp;', 10).'Imto<br>';

	$lineas+= 3;

	$tbase  = 0;
	$timpto = 0;
	foreach ($impuestos as $impuesto) {
		$label = $impuesto['label'];
		$base  = number_format($impuesto['base'] , 2);
		$impto = number_format($impuesto['impto'], 2);

		$tbase += $impuesto['base'];
		$timpto+= $impuesto['impto'];

		if($base>0) {
			$html .= $label.str_repeat('&nbsp;', 14-strlen($label));
			$html .= str_repeat('&nbsp;', 13-strlen($base)).$base;
			$html .= str_repeat('&nbsp;', 15-strlen($impto)).$impto;
			$html .= '<br>'; 
			$lineas++;
		}
	}

	$html.= str_repeat('-', 42).'<br>';
	$lineas++;

	$ttotal = $tbase+ $timpto;

	$tbase  = number_format($tbase , 2);
	$timpto = number_format($timpto, 2);

	$html.= 'Totales'.str_repeat('&nbsp;', 14-strlen('Totales'));
	$html.= str_repeat('&nbsp;', 13-strlen($tbase)).$tbase;
	$html.= str_repeat('&nbsp;', 15-strlen($timpto)).$timpto;

	$html.= '<br>';
	$html.= 'Total'.str_repeat('&nbsp;', 37-strlen($Total)).$Total.'<br>';
	$html.= '<br>';

	$lineas+= 3;

	for($i=0; $i < count($piedepagina); $i++) {
		$txt = '<center>'.$piedepagina[$i]['TEXTO'].'</center>';
		for($j=0; $j < count($datosdian); $j++) {
			$bus = '(ResDian)';
			if(strpos($txt, $bus) !== false) {
				$txt = str_replace($bus, $datosdian[$j]['ResDian'], $txt);
			}
			$bus = '(FecDian)';
			if(strpos($txt, $bus) !== false) {
				$txt = str_replace($bus, $datosdian[$j]['FecDian'], $txt);
			}
			$bus = '(IniDian)';
			if(strpos($txt, $bus) !== false) {
				$txt = str_replace($bus, $datosdian[$j]['IniDian'], $txt);
			}
			$bus = '(FinDian)';
			if(strpos($txt, $bus) !== false) {
				$txt = str_replace($bus, $datosdian[$j]['FinDian'], $txt);
			}
			$bus = '(PrfDian)';
			if(strpos($txt, $bus) !== false) {
				$txt = str_replace($bus, $datosdian[$j]['PrfDian'], $txt);
			}
		}
		
		$html.= $txt;
		$lineas++;
	}

	$html.= '</body></html>';

	$len = ($lineas*8)+8;

	$customPaper = array(0,0,210,$len);
	$dompdf = new Dompdf\Dompdf();
	$dompdf->set_paper($customPaper);
	$dompdf->load_html($html, "utf-8");
	$dompdf->render();
	$output = $dompdf->output();
	$filename = str_pad($datosfac[0]['LOCALIDAD'], 2, '0', STR_PAD_LEFT);
	$filename.= str_pad($nrocaj, 3, '0', STR_PAD_LEFT);
	$filename.= $nrofac;
	file_put_contents('../tmp/fact2pdf/fact2pdf_'.$filename.'.pdf', $output);

	echo json_encode(
		array(
			'enlace' => 'tmp/fact2pdf/fact2pdf_'.$filename.'.pdf',
			'archivo'=> 'fact2pdf_'.$filename.'.pdf'
		)
	);
?>