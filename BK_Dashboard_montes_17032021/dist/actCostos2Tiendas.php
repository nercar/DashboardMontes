<?php
	$userid = 'JOB crontab 50.8';
	date_default_timezone_set('America/Bogota');
	// Se establece la conexion con la BBDD
	$params = parse_ini_file('config.ini');
	if ($params === false) {
		throw new \Exception("Error reading database configuration file");
	}
	// Se capturan las opciones por Post
	extract($_POST);
	// cadena de conexion a los servidores
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
							$ret['observacion'] = 'No se realizaron modificaciones para '.$tienda['nombre'];
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
	if($error==0 && count($tiendas)>0) {
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
	foreach ($result as $resultado) {
		echo implode(', ', $resultado)."\r";
	}
?>