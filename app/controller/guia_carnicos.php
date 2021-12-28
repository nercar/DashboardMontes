<?php
	// conexion a bbdd principal
	try {
		date_default_timezone_set('America/Bogota');
		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../../dist/config.ini');
		if ($params === false) throw new \Exception("Error reading database configuration file");
		// Se capturan las opciones por Post
		extract($_POST);
		// connect to the database
		$strSrvCon = array(
			"Database" => "BDES",
			"UID" => $params['user_sql'],
			"PWD" => $params['password_sql'],
			"CharacterSet" => "UTF-8",
			"ConnectRetryCount" => 5);
		$connecSrv = sqlsrv_connect($params['host_sql'].($params['instance']!=''?'\\'.$params['instance']:''), $strSrvCon);
		$result = [ 'status' => 0, 'query'  => '', 'mensaje' => '' ];
		$datos = [];
		switch ($opcion) {
			case 'listaTiendasBDES':
				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, nombre, servidor, servidorpos
						FROM BDES.dbo.ESSucursales
						WHERE activa = 1 AND codigo NOT IN(3, 6, 14, 19, 99, 100)
						ORDER BY rtrim(ltrim(nombre))";
				// Se ejecuta la consulta en la BBDD
				$sql = sqlsrv_query($connecSrv, $sql);
				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'nombre'      => ucwords(strtolower($row['nombre'])),
						'servidor'    => $row['servidor'],
						'servidorpos' => $row['servidorpos'],
					];
				}
				echo json_encode($datos);
				break;
			case 'listaTransCarnes':
				if($tienda>0) {
					$sql = "SELECT KAR.documento, CONVERT(VARCHAR, KAR.fecha, 120) AS fecha, USR.descripcion AS usuario,
								KAR.ip AS estacion, CONVERT(VARCHAR, KAR.fecha, 23) AS fecexp, KAR.id
							FROM BDES.dbo.BIKARDEX KAR
							LEFT JOIN BDES.dbo.DBGCarnicosCab DBGC ON KAR.ID = DBGC.id_bikardex
							INNER JOIN BDES.dbo.ESUsuarios USR ON USR.codusuario = KAR.USUARIO
							WHERE KAR.FECHA >= '10-06-2021' AND KAR.LOCALIDAD = 14
								AND ESTADO = 1 AND ELIMINADO = 0 AND KAR.localidad_dest = $tienda
								AND KAR.TIPO = 17 AND DBGC.id_bikardex IS NULL";
					$result['query'] = $sql;
					$sql = sqlsrv_query($connecSrv, $sql);
					if($sql===false){
						$result['status'] = 0;
						$result['mensaje'] = sqlsrv_errors()[0]['message'];
						echo json_encode($result);
						break;
					}
					while ($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
						$row['fecha'] = '<span style="display: none">'.$row['fecha'].'</span>'.date('d-m-Y h:i a', strtotime($row['fecha']));
						$datos[] = $row;
					}
				}
				echo json_encode($datos);
				break;
			case 'transfXDoc':
				$sql = "SELECT art.codigo, art.descripcion AS articulo,
							CASE WHEN art.tipoarticulo = 0 THEN det.CANTIDAD ELSE 0 END AS unidad,
							CASE WHEN art.tipoarticulo = 1 THEN det.CANTIDAD ELSE 0 END AS peso,
							art.grupo AS grupo, grp.DESCRIPCION AS especie
						FROM BDES.dbo.BIKARDEX_DET AS det
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.MATERIAL
						INNER JOIN BDES.dbo.ESGrupos AS grp ON grp.CODIGO = art.Grupo
						WHERE det.DOCUMENTO = $nrodoc AND det.ESTADO = 1 AND det.ELIMINADO = 0
							AND det.TIPO = 17 AND det.LOCALIDAD = 14
						ORDER BY especie, art.descripcion";
				$result['query'] = $sql;
				$sql = sqlsrv_query($connecSrv, $sql);
				if($sql===false){
					$result['status'] = 0;
					$result['mensaje'] = sqlsrv_errors()[0]['message'];
					echo json_encode($result);
					break;
				}
				while ($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
					$datos[] = $row;
				}
				echo json_encode($datos);
				break;
			case 'correlativoGuia':
				$sql = "SELECT ValorCorrelativo
						FROM BDES.dbo.ESCorrelativos
						WHERE Correlativo = 'GuiaCarnicos'";
				$sql = sqlsrv_query($connecSrv, $sql);
				if($sql===false){
					print_r(sqlsrv_errors());
					echo 0;
				} else {
					$correlativo = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC);
					$sql = "UPDATE BDES.dbo.ESCorrelativos
							SET ValorCorrelativo = (ValorCorrelativo + 1)
							WHERE Correlativo = 'GuiaCarnicos';";
					$sql = sqlsrv_query($connecSrv, $sql);
					if($sql===false){
						print_r(sqlsrv_errors());
						echo 0;
					} else {
						echo $correlativo['ValorCorrelativo'];
					}
				}
				break;
			case 'generarPdf':
				$sql = "SELECT TOP 1 * FROM BDES.dbo.ESSucursales WHERE codigo = $tienda";
				$sql = sqlsrv_query($connecSrv, $sql);
				$tienda = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC);
				$nomsuc = 'COMERCIALIZADORA MONTES DE COLOMBIA SAS - SUCURSAL '.strtoupper($tienda['Nombre']);
				$dirsuc = strtoupper(substr($tienda['direccion'], 0, stripos($tienda['direccion'], ' - MUNICIPIO')));
				$munsuc = strtoupper(substr($tienda['direccion'], stripos($tienda['direccion'], ' - MUNICIPIO ')+13));
				$noguia = str_pad($noguia, 5, "0", STR_PAD_LEFT);
				$html   =
					'<html>
						<head>
							<meta charset="UTF-8">
							<style>
								@page { margin: 430px 40px 200px 40px; font-size: 11px; font-family: Arial, Helvetica, sans-serif; }
								header { position: fixed; top: -407px; left: 0px; right: 0px; }
								footer { position: fixed; bottom: 5px; left: 0px; right: 0px; }
								p { page-break-after: never; }
								p:last-child { page-break-after: never; }
								table { letter-spacing: -0.3px; }
							</style>
						</head>
						<body>
						<header>
							<table width="100%">
								<tr>
									<td width="40%">
										<img src="../../dist/img/logo-ppal.png" width="200">
									</td>
									<td align="center">
										ALMACENAMIENTO Y/O DISTRIBUCION DE<br>CARNES Y PRODUCTOS CARNICOS COMESTIBLES<br>
										<b>GUIA DE TRANSPORTE Y DESTINO DE CARNES<br>Y PRODUCTOS CARNICOS COMESTIBLES</b><br>
										<small>AVENIDA LIBERTADORES NRO. 3-110 TASAJERO CUCUTA</small>
									</td>
								</tr>
							</table>
							<table width="100%" style="margin-bottom: 5px;">
								<tr>
									<th width="50%" align="center">CODIGO DE INSCRIPCION</td>
									<th width="50%" align="center">GUIA NUMERO</td>
								</tr>
								<tr>
									<td align="center">540019006030415</td>
									<td align="center">540019006030415'.$noguia.'-'.date('y').'</td>
								</tr>
							</table>
							ORIGEN
							<table width="100%" style="border: 1px solid;">
								<tr>
									<td>
										<b>RAZON SOCIAL:</b> COMERCIALIZADORA MONTES DE COLOMBIA SAS  - SUCURSAL LIBERTADORES
									</td>
								</tr>
							</table>
							<table width="100%" style="border: 1px solid;">
								<tr>
									<th align="center">DIRECCIÓN</td>
									<th align="center">MUNICIPIO</td>
									<th align="center">DEPARTAMENTO</td>
								</tr>
								<tr>
									<td align="center">AV. LIBERTADORES NRO 3-110 URB TASAJERO</td>
									<td align="center">CUCUTA</td>
									<td align="center">NORTE DE SANTANDER</td>
								</tr>
							</table>
							<table width="100%" style="border: 1px solid; margin-top: 5px; margin-bottom: 5px;">
								<tr>
									<th align="center">FECHA DE EXPEDICION</td>
									<th align="center">MUNICIPIO</td>
									<th align="center">DEPARTAMENTO</td>
								</tr>
								<tr>
									<td>
										<table width="100%" border="1" cellspacing="0">
											<tr>
												<th align="center">DD</td>
												<th align="center">MM</td>
												<th align="center">AAAA</td>
											</tr>
											<tr>
												<td align="center">'.date('d', strtotime($fecexp)).'</td>
												<td align="center">'.date('m', strtotime($fecexp)).'</td>
												<td align="center">'.date('Y', strtotime($fecexp)).'</td>
											</tr>
										</table>
									</td>
									<td align="center">CUCUTA</td>
									<td align="center">NORTE DE SANTANDER</td>
								</tr>
							</table>
							DESTINO
							<table width="100%" border="1" cellspacing="0">
								<tr>
									<td colspan="4"><b>RAZON SOCIAL: </b>'.$nomsuc.'</td>
								</tr>
								<tr>
									<td colspan="4"><b>DIRECCION: </b>'.$dirsuc.'</td>
								</tr>
								<tr>
									<td width="20%">MUNICIPIO:</td>
									<td>'.$munsuc.'</td>
									<td width="20%"><b>DEPARTAMENTO:<b></td>
									<td>NORTE DE SANTANDER</td>
								</tr>
							</table>
							<table width="100%" style="margin-top: 5px;">
								<tr>
									<td width="20%">ESPECIE</td>
									<td style="border-bottom: 1px solid;">'.$detail[0]['especie'].'</td>
								</tr>
								<tr>
									<td width="20%">TEMPERATURA</td>
									<td style="border-bottom: 1px solid;">'.$temper.' °C</td>
								</tr>
							</table>
							<table width="100%" border="1" cellspacing="0" cellpadding="4" style="line-height: 80%;">
								<tr>
									<th width=" 5%" align="center">No.</td>
									<th width="45%" align="center">PRODUCTO</td>
									<th width="15%" align="center">UNIDAD</td>
									<th width="15%" align="center">PESO KG</td>
									<th width="20%" align="center">LOTE</td>
								</tr>
							</table>
						</header>
						<footer>
							<table width="100%">
								<tr>
									<td width="20%">OBSERVACIONES</td>
									<td style="border-bottom: 1px solid;">'.$observ.'</td>
								</tr>
							</table>
							<table width="100%" style="margin-top: 5px;" border="1" cellspacing="0">
								<tr>
									<td height="22px;" width="35%">PLACA DEL VEHICULO</td>
									<td style="border-bottom: 1px solid;"></td>
								</tr>
								<tr>
									<td height="22px;" width="35%">NOMBRE RESPONSABLE GUIA</td>
									<td style="border-bottom: 1px solid;">JOSE GUILLERMO RINCON GONZALEZ</td>
								</tr>
								<tr>
									<td height="22px;" width="35%">FIRMA</td>
									<td style="border-bottom: 1px solid;"></td>
								</tr>
								<tr>
									<td height="22px;" width="35%">TELEFONO</td>
									<td style="border-bottom: 1px solid;">3132893423</td>
								</tr>
								<tr>
									<td height="22px;" width="35%">HORA DESPACHO</td>
									<td style="border-bottom: 1px solid;">'.date('d-m-Y h:i a').'</td>
								</tr>
								<tr>
									<td height="22px;" width="35%">FECHA Y HORA DE ENTREGA</td>
									<td style="border-bottom: 1px solid;"></td>
								</tr>
							</table>
						</footer>
						<main>
							<table width="100%" border="1" cellspacing="0" cellpadding="4" style="line-height: 80%;">';
				$i = 0;
				foreach($detail as $dato) {
					$i++;
					$html .=
						'<tr>'.
							'<td width=" 5%" align="center">' . ($i) . '</td>'.
							'<td width="45%" align="left"  >' . ($dato['articulo']) . '</td>'.
							'<td width="15%" align="right" >' . ($dato['unidad']*1==0?'':number_format($dato['unidad']*1, 2)) . '</td>'.
							'<td width="15%" align="right" >' . ($dato['peso']*1==0?'':number_format($dato['peso']*1, 2)) . '</td>'.
							'<td width="20%" align="center">' . ($dato['lote']) . '</td>'.
						'</tr>';
				};
				$html .= '</table></main></body></html>';
				require_once("../../Classes/dompdf/autoload.inc.php");
				$dompdf = new Dompdf\Dompdf();
				$dompdf->set_paper("letter","portrait");
				$dompdf->load_html($html);
				$dompdf->render();
				$dompdf->stream("guiacarn_".$noguia.".pdf");
				break;
			case 'guardarRealizadas':
				$sql = "INSERT INTO BDES.dbo.DBGCarnicosCab(id_bikardex) VALUES($kar_id)";
				$sql = sqlsrv_query($connecSrv, $sql);
				if($sql===false){
					print_r(sqlsrv_errors());
					echo 0;
				} else {
					echo 1;
				}
				break;
		}
		$connecSrv = null;
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>