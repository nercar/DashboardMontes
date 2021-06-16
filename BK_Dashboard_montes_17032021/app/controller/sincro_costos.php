<?php
	// conexion a bbdd principal
	try {
		date_default_timezone_set('America/Bogota');
		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../../dist/config.ini');
		if ($params === false) {
			throw new \Exception("Error reading database configuration file");
		}
		// Se capturan las opciones por Post
		extract($_POST);
		// connect to the sql server database
		$srvvin = '[192.168.50.9].';
		$srvvinope = '[192.168.56.5].';
		$strSrvCon = array(
			"Database" => "BDES",
			"UID" => $params['user_sql'],
			"PWD" => $params['password_sql'],
			"ConnectRetryCount" => 5);
		$strLocCon = array(
			"Database" => "BDES_POS",
			"UID" => 'sa',
			"PWD" => '',
			"ConnectRetryCount" => 5);
		$connecSrv = sqlsrv_connect($params['host_sql'], $strSrvCon);
		$datos = [];
		switch ($opcion) {
			case 'porSincronizar':
				$sql = "SELECT uc.articulo, art.descripcion, uc.margen_ref, uc.costo,
							CAST((CASE WHEN uc.precio = 0 THEN 0 ELSE
								(uc.precio-uc.costo)/uc.precio*100
							END) AS NUMERIC(7,2)) AS margen, uc.precio, art.impuesto,
							CAST((CASE WHEN art.impuesto = 0 THEN uc.precio ELSE
								uc.precio*(1+(art.impuesto/100))
							END) AS NUMERIC(21,2)) AS pvpiva, sincro
						FROM BDES.dbo.DBCostoArticulos AS uc
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = uc.articulo
						WHERE uc.sincro IN(1, 2) AND uc.sucursal = 6";
				$sql = sqlsrv_query($connecSrv, $sql);
				$datos = [];
				while ($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['articulo'],
						'descripcion' => '<span class="btn btn-link p-0 pl-1 pr-1" title="Validar Costos">'.utf8_decode($row['descripcion']).'</span>',
						'descriptxt'  => utf8_decode($row['descripcion']),
						'margen_ref'  => $row['margen_ref'],
						'costo'       => $row['costo'],
						'margen'      => $row['margen'],
						'precio'      => $row['precio'],
						'impuesto'    => $row['impuesto'],
						'pvpiva'      => $row['pvpiva'],
						'sincro'      => $row['sincro']==1?
										'<div class="note-warning">Por Revisar</div>':
										'<div class="note-info">Por Sincronizar</div>',
					];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'costosTiendas':
				$sql = "SELECT ti.codigo, ti.Nombre AS tienda, uc.sincro,
							COALESCE(sol.cantidad, 0) AS cantidad,
							COALESCE(CAST(uc.costo AS NUMERIC(21,2)) ,0) AS costo,
							CAST((CASE WHEN uc.precio = 0 THEN 0 ELSE
								COALESCE((uc.precio-uc.costo)/uc.precio*100, 0)
							END) AS NUMERIC(6,2)) AS margen,
							CAST(COALESCE(uc.margen_ref, 0) AS NUMERIC(6,2)) AS margen_ref,
							CAST(COALESCE(uc.precio, 0) AS NUMERIC(21,2)) AS precio,
							CAST(COALESCE(uc.impuesto, 0) AS NUMERIC(6,2)) AS impuesto,
							CAST((CASE WHEN uc.impuesto = 0 THEN uc.precio ELSE
								COALESCE(uc.precio*(1+(uc.impuesto/100)), 0)
							END) AS NUMERIC(21,2)) AS pvpiva
						FROM BDES.dbo.ESSucursales AS ti
						LEFT JOIN
							(SELECT uc1.sucursal, uc1.costo, uc1.precio, art.impuesto, uc1.margen_ref, uc1.sincro
							FROM BDES.dbo.DBCostoArticulos uc1
							INNER JOIN BDES.dbo.ESARTICULOS art ON art.codigo = uc1.articulo
							WHERE uc1.articulo = $codigo) AS uc ON ti.codigo = uc.sucursal
						LEFT JOIN
							(SELECT localidad, SUM(det.solipedidet_pedido) AS cantidad
							FROM BDES.dbo.BISolicPedido cab
							INNER JOIN BDES.dbo.BISoliPediDet det ON det.solipedi_id = cab.solipedi_id
							WHERE cab.solipedi_status = 7 AND det.solipedidet_codigo = $codigo
							AND CAST(cab.solipedi_fechasoli AS DATE) =
									(SELECT MAX(CAST(cab.solipedi_fechasoli AS DATE))
									FROM BDES.dbo.BISolicPedido cab
									INNER JOIN BDES.dbo.BISoliPediDet det ON det.solipedi_id = cab.solipedi_id
									WHERE cab.solipedi_status = 7 AND det.solipedidet_codigo = $codigo)
							GROUP BY localidad) AS sol ON sol.localidad = ti.codigo
						WHERE ti.codigo != 6";
				$sql = sqlsrv_query($connecSrv, $sql);
				$i = 0;
				while ($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
					$datos[] = [
						'codigo'     => $row['codigo'],
						'margen_refn'=> $row['margen_ref'],
						'costo'      => $row['costo'],
						'margenn'    => $row['margen'],
						'precion'    => $row['precio'],
						'impuesto'   => $row['impuesto'],
						'pvpivan'    => $row['pvpiva'],
						'rowid'      => $i,
						'tienda'     => '<span style="display: none;">'.$row['tienda'].'</span>'.
										'<div class="custom-control custom-checkbox text-nowrap">' .
										'<input type="checkbox" class="custom-control-input dt-check m-0" '.($row['sincro']==2?'checked ':'').
										'onclick="cambiarMarca(this.checked, '.$i.');" id="c'.$i.'">' .
										'<label class="custom-control-label pt-1 font-weight-normal" for="c'.$i.'">'.
										ucwords(strtolower($row['tienda'])).
										($row['cantidad']*1>0?' <i class="far fa-dot-circle fa-xs"></i>':'').'</label>'.
										'</div>',
						'margen_ref' => '<input type="text" placeholder="9,999.99" '.($row['sincro']==2?'':'disabled').
										' onblur = "resaltar(this,0); "'.
										' onfocus = "resaltar(this,1); $(this).select()" '.
										' onkeydown = "return tabE(this,event);"'.
										' onkeypress = "if(this.value.length==7) return false;" '.
										' onchange = "calcMontos(\'margen\', '.$i.', '.$row['impuesto'].');" '.
										' id="inp_margen_ref'.$i.'"' .
										' value="'.$row['margen_ref'].'" class="w-100 inpNum inpPor">',
						'margen'     => '<input type="text" placeholder="9,999.99" '.($row['sincro']==2?'':'disabled').
										' onblur = "resaltar(this,0); "'.
										' onfocus = "resaltar(this,1); $(this).select()" '.
										' onkeydown = "return tabE(this,event);"'.
										' onkeypress = "if(this.value.length==7) return false;" '.
										' onchange = "calcMontos(\'margen\', '.$i.', '.$row['impuesto'].');" '.
										' id="inp_margen'.$i.'"' .
										' value="'.$row['margen'].'" class="w-100 inpNum inpPor">',
						'precio'     => '<span id="inp_precio'.$i.'" data-val="'.$row['precio'].'">'.number_format($row['precio'], 2).'</span>',
						'pvpiva'     => '<input type="text" placeholder="999,999,999.99" '.($row['sincro']==2?'':'disabled').
										' onblur = "resaltar(this,0); "'.
										' onfocus = "resaltar(this,1); $(this).select()" '.
										' onkeydown = "return tabE(this,event);"'.
										' onchange = "calcMontos(\'pvpiva\', '.$i.', '.$row['impuesto'].');" '.
										' id="inp_pvpiva'.$i.'"' .
										' value="'.$row['pvpiva'].'" class="w-100 inpNum">',
					];
					$i++;
				}
				echo json_encode($datos);
				break;
			case 'actualizarCosto':
				$result = [
					'status'  => 0,
					'mensaje' => 'Hubo un error, no se pudo actualizar la información',
					'query'   => '',
				];
				if(count($tienda)>0) {
					$sql = "UPDATE BDES.dbo.DBCostoArticulos SET
							sincro = 1, fecha_ultimamod = GETDATE(), usuario = '$userid'
							WHERE sincro = 2 AND articulo = ".$tienda[0]['articulo'].";
							MERGE INTO BDES.dbo.DBCostoArticulos AS destino USING (VALUES";
					foreach ($tienda as $sucursal) {
						$sql.= "(".$sucursal['articulo'].", ";
						$sql.= $sucursal['sucursal'].", ";
						$sql.= $sucursal['costo'].", ";
						$sql.= $sucursal['margenrf'].", ";
						$sql.= $sucursal['precio'].", '$userid'),";
					}
					$sql = substr($sql, 0, -1);
					$sql.= ") AS origen(articulo, sucursal, costo, margenrf, precio, usuario)
							ON destino.articulo = origen.articulo AND destino.sucursal = origen.sucursal
							WHEN MATCHED THEN
								UPDATE SET costo = origen.costo, precio = origen.precio, margen_ref = origen.margenrf,
								sincro = 2, fecha_ultimamod = GETDATE(), usuario = origen.usuario
							WHEN NOT MATCHED THEN
								INSERT(articulo, sucursal, costo, margen_ref, precio, sincro, usuario)
								VALUES(origen.articulo, origen.sucursal, origen.costo, origen.margenrf, origen.precio, 2, '$userid');
							UPDATE BDES.dbo.DBCostoArticulos SET sincro = 2 WHERE sucursal = 6 AND articulo =".$tienda[0]['articulo'];
					$sql.= "INSERT INTO BDES.dbo.DBCostoArticulos_H(articulo, sucursal, status, sincro, costo, margen_ref, precio, fecha_ultimamod, usuario)
							VALUES ";
					foreach ($tienda as $sucursal) {
						$sql.= "(".$sucursal['articulo'].", ";
						$sql.= $sucursal['sucursal'].", 1, 2, ";
						$sql.= $sucursal['costo'].", ";
						$sql.= $sucursal['margenrf'].", ";
						$sql.= $sucursal['precio'].", GETDATE(), '$userid'),";
					}
					$sql = substr($sql, 0, -1);
					$result['query'] = $sql;
					$sql = sqlsrv_query($connecSrv, $sql);
					if(!$sql) {
						$result['mensaje'] .= substr(utf8_encode(sqlsrv_errors()[0]['message']), 54);
					} else {
						$result = [
							'status'  => 1,
							'mensaje' => 'Información actualizada correctamente',
							'query'   => $result['query'],
						];
					}
				} else {
					$sql = "UPDATE BDES.dbo.DBCostoArticulos SET
							sincro = 1, fecha_ultimamod = GETDATE(), usuario = '$userid'
							WHERE sincro = 2 AND articulo = $codigo";
					$sql = sqlsrv_query($connecSrv, $sql);
					if(!$sql) {
						$result['mensaje'] .= substr(utf8_encode(sqlsrv_errors()[0]['message']), 54);
					} else {
						$result = [
							'status'  => 1,
							'mensaje' => 'Información actualizada correctamente',
							'query'   => $result['query'],
						];
					}
				}
				echo json_encode($result);
				break;
			case 'listarPorSincronizar':
				$sql = "SELECT
							suc.codigo AS localidad,
							suc.Nombre AS sucursal,
							uc.articulo AS codigo,
							art.descripcion AS articulo,
							CAST(uc.costo AS NUMERIC(21,2)) AS costo,
							CAST((CASE WHEN uc.precio = 0 THEN 0 ELSE
								COALESCE((uc.precio-uc.costo)/uc.precio*100, 0)
							END) AS NUMERIC(6,2)) AS margen,
							CAST(COALESCE(uc.precio, 0) AS NUMERIC(21,2)) AS precio,
							CAST((CASE WHEN art.impuesto = 0 THEN uc.precio ELSE
								COALESCE(uc.precio*(1+(art.impuesto/100)), 0)
							END) AS NUMERIC(21,2)) AS pvpiva
						FROM BDES.dbo.DBCostoArticulos AS uc
						INNER JOIN BDES.dbo.ESSucursales AS suc ON suc.codigo = uc.sucursal
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = uc.articulo
						WHERE uc.sincro = 2 AND uc.sucursal != 6";
				$sql = sqlsrv_query($connecSrv, $sql);
				$datos = [];
				while ($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
					$datos[] = $row;
				}
				echo json_encode($datos);
				break;
			case 'sincronizarCostos':
				$result = [];
				$tiendas = [];
				$error = 0;
				if($connecSrv) {
					// Se crea el query para obtener la informacion de conexion de las sucursales
					$sql = "SELECT codigo, nombre, COALESCE(servidor, '') AS servidor
							FROM BDES.dbo.ESSucursales
							WHERE activa = 1
							AND codigo IN(SELECT DISTINCT sucursal FROM BDES.dbo.DBCostoArticulos WHERE sincro = 2)
							ORDER BY codigo";
					// Se ejecuta la consulta en la BBDD
					$sql = sqlsrv_query($connecSrv, $sql);
					// Se prepara el array para almacenar los datos obtenidos
					while($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
						$tiendas[] = $row;
					}
				}
				foreach ($tiendas as $tienda) {
					$ret = [
						'codigo'      => $tienda['codigo'],
						'nombre'      => $tienda['nombre'],
						'servidor'    => $tienda['servidor'],
						'exitosos'    => null,
						'observacion' => null,
						'inicia'      => null,
						'finaliza'    => null,
					];
					$ret['inicia'] = date('Y-m-d H:i:s');
					// Conectando con SQL de las sucursales
					$sql = "SELECT
								art.codigo, art.descripcion, art.descripcion_corta, art.departamento, art.Grupo, art.Subgrupo,
								art.marca, art.modelo, art.costoant, uc.costo, 0 AS costoempaque, uc.precio AS precio1,
								uc.precio AS precio2, uc.precio AS precio3, art.preciooferta, art.descripresenta, art.peso,
								art.volumen, art.presentacion, art.presenta_ven, art.impuesto, art.activo, art.tipoarticulo,
								CONVERT(VARCHAR, art.fechainicio, 121) AS fechainicio,
								CONVERT(VARCHAR, art.fechafinal, 121) AS fechafinal,
								CONVERT(VARCHAR, art.horainicio, 121) AS horainicio,
								CONVERT(VARCHAR, art.horafinal, 121) AS horafinal,
								CONVERT(VARCHAR, art.fechacreacion, 121) AS fechacreacion,
								CONVERT(VARCHAR, GETDATE(), 121) AS fechamodificacion, art.numerodecimales, 0 AS usuario, art.precioregulado,
								art.teclado, art.sundecop, art.multiplicar, art.nivelvender, art.manejalote, art.mpps, art.cpe,
								'0' AS tipocambio, '0' AS actualizo, 'DB_50.8_".$tienda['codigo']."' AS aplicacion, art.talla, art.color
							FROM BDES.dbo.DBCostoArticulos AS uc
							INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = uc.articulo
							WHERE uc.sincro = 2 AND uc.sucursal = ".$tienda['codigo'];
					// Se ejecuta la consulta en la BBDD
					$sql = sqlsrv_query($connecSrv, $sql);
					if(!$sql) {
						$ret['observacion'] = substr(utf8_encode(sqlsrv_errors()[0]['message']), 54);
						$error = 1;
					} else {
						// Se prepara el array para almacenar los datos obtenidos
						$articulos = [];
						while($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
							$articulos[] = $row;
						}
						if(count($articulos)>0) {
							$connecLoc = sqlsrv_connect($tienda['servidor'], $strLocCon);
							if($connecLoc === false) {
								$ret['observacion'] = substr(utf8_encode(sqlsrv_errors()[0]['message']), 54);
								$error = 1;
							} else {
								$sql = "INSERT INTO BDES_POS.dbo.ESARTICULOS_CAMBIOS(
											codigo, descripcion, descripcion_corta, departamento, Grupo, Subgrupo, marca,
											modelo, costoant, costo, costoempaque, precio1, precio2, precio3, preciooferta,
											descripresenta, peso, volumen, presentacion, presenta_ven, impuesto, activo,
											tipoarticulo, fechainicio, fechafinal, horainicio, horafinal, fechacreacion,
											fechamodificacion, numerodecimales, usuario, precioregulado, teclado, sundecop,
											multiplicar, nivelvender, manejalote, mpps, cpe, tipocambio, actualizo,
											aplicacion, talla, color)
										SELECT * FROM (VALUES";
								foreach ($articulos as $articulo) {
									$sql.= "("
											.$articulo['codigo'].", '".$articulo['descripcion']."', '".$articulo['descripcion_corta']."', ".$articulo['departamento'].", "
											.$articulo['Grupo'].", ".$articulo['Subgrupo'].", '".$articulo['marca']."', '".$articulo['modelo']."', ".$articulo['costoant'].", "
											.$articulo['costo'].", ".$articulo['costoempaque'].", ".$articulo['precio1'].", ".$articulo['precio2'].", "
											.$articulo['precio3'].", ".$articulo['preciooferta'].", '".$articulo['descripresenta']."', ".$articulo['peso'].", "
											.$articulo['volumen'].", ".$articulo['presentacion'].", ".$articulo['presenta_ven'].", ".$articulo['impuesto'].", '"
											.$articulo['activo']."', ".$articulo['tipoarticulo'].", "
											."'".str_replace(" ", "T", $articulo['fechainicio'])."', "
											."'".str_replace(" ", "T", $articulo['fechafinal'])."', "
											."'".str_replace(" ", "T", $articulo['horainicio'])."', "
											."'".str_replace(" ", "T", $articulo['horafinal'])."', "
											."'".str_replace(" ", "T", $articulo['fechacreacion'])."', "
											."'".str_replace(" ", "T", $articulo['fechamodificacion'])."', "
											.$articulo['numerodecimales'].", ".$articulo['usuario'].", '".$articulo['precioregulado']."', '".$articulo['teclado']."', '"
											.$articulo['sundecop']."', '".$articulo['multiplicar']."', ".$articulo['nivelvender'].", '".$articulo['manejalote']."', '"
											.$articulo['mpps']."', '".$articulo['cpe']."', ".$articulo['tipocambio'].", '".$articulo['actualizo']."', '".$articulo['aplicacion']."', "
											.$articulo['talla'].", ".$articulo['color']
										."),";
								}
								$sql = substr($sql, 0, -1) . ')
										AS tmp (codigo, descripcion, descripcion_corta, departamento, Grupo, Subgrupo, marca,
												modelo, costoant, costo, costoempaque, precio1, precio2, precio3, preciooferta,
												descripresenta, peso, volumen, presentacion, presenta_ven, impuesto, activo,
												tipoarticulo, fechainicio, fechafinal, horainicio, horafinal, fechacreacion,
												fechamodificacion, numerodecimales, usuario, precioregulado, teclado, sundecop,
												multiplicar, nivelvender, manejalote, mpps, cpe, tipocambio, actualizo,
												aplicacion, talla, color);';
								$sql = sqlsrv_query($connecLoc, $sql);
								if($sql) {
									$rows = sqlsrv_rows_affected($sql);
									$ret['exitosos'] = 'RegAct: ' . $rows;
									if($rows>0) {
										$sql = "UPDATE BDES.dbo.DBCostoArticulos SET
													sincro = 3, fecha_ultimamod = GETDATE(), usuario = '$userid'
												WHERE sincro = 2 AND sucursal = ".$tienda['codigo'].";
												INSERT INTO BDES.dbo.DBCostoArticulos_H(
													articulo, sucursal, status, sincro, costo, margen_ref, precio, fecha_ultimamod, usuario)
												SELECT articulo, sucursal, status, sincro, costo, margen_ref, precio, GETDATE() AS fecha_ultimamod, '$userid' AS usuario
												FROM BDES.dbo.DBCostoArticulos WHERE sincro = 3 AND sucursal = ".$tienda['codigo'].";
												UPDATE BDES.dbo.DBCostoArticulos SET sincro = 4 WHERE sincro = 3 AND sucursal = ".$tienda['codigo'];
										$sql = sqlsrv_query($connecSrv, $sql);
										if(!$sql) {
											$ret['exitosos'].= $rows . ' ('. $tienda['nombre'] . ') No se actualizaron en el Servidor principal';
											$ret['observacion'] = substr(utf8_encode(sqlsrv_errors()[0]['message']), 54);
											$error = 1;
										}
									} else {
										$ret['exitosos'] = 'RegAct: 0';
										$ret['observacion'] = 'No se realizaron modificaciones para '. $tienda['nombre'];
										$error = 1;
									}
								} else {
									$ret['exitosos'] = 'RegAct: 0';
									$ret['observacion'] = substr(utf8_encode(sqlsrv_errors()[0]['message']), 54);
									$error = 1;
								}
							}
							$connecLoc = null;
						} else {
							$ret['observacion'] = 'No hay informacion para actualizar';
						}
						$ret['finaliza'] = date('Y-m-d H:i:s');
						$result[] = $ret;
					}
				}
				if($error==0) {
					$sql = "UPDATE BDES.dbo.DBCostoArticulos SET
								sincro = 3, fecha_ultimamod = GETDATE(), usuario = '$userid'
							WHERE sincro = 2 AND sucursal = 6;
							INSERT INTO BDES.dbo.DBCostoArticulos_H(
								articulo, sucursal, status, sincro, costo, margen_ref, precio, fecha_ultimamod, usuario)
							SELECT articulo, sucursal, status, sincro, costo, margen_ref, precio, GETDATE() AS fecha_ultimamod, '$userid' AS usuario
							FROM BDES.dbo.DBCostoArticulos WHERE sincro = 3 AND sucursal = ".$tienda['codigo'].";
							UPDATE BDES.dbo.DBCostoArticulos SET sincro = 4 WHERE sincro = 3 AND sucursal = 6";
					$sql = sqlsrv_query($connecSrv, $sql);
					if(!$sql) {
						$result[] = [
							'codigo'      => '6',
							'nombre'      => 'FRUVER',
							'servidor'    => null,
							'exitosos'    => 'RegAct: 0',
							'observacion' => substr(utf8_encode(sqlsrv_errors()[0]['message']), 54),
							'inicia'      => null,
							'finaliza'    => null,
						];
					}
				}
				echo json_encode($result);
				break;
		}
		$connecSrv = null;
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>