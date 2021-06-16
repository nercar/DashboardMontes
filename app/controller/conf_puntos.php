<?php
	// conexion a bbdd principal
	try {
		date_default_timezone_set('America/Bogota');
		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../../dist/config.ini');
		if ($params === false) throw new \Exception("Error reading database configuration file");
		// Se capturan las opciones por Post
		extract($_POST);
		// connect to the postgresql database
		$conStr = sprintf("pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
			$params['host'],
			$params['port'],
			$params['database'],
			$params['user'],
			$params['password']);
		$connec = new \PDO($conStr);
		$result = [ 'status' => 0, 'query'  => '', 'mensaje' => '' ];
		$datos = [];
		switch ($opcion) {
			case 'puntoCanje':
				$sql = "SELECT puntos, valor FROM puntos_valor_canje WHERE activo = 1 ORDER BY fecha DESC LIMIT 1";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = $sql->fetch(\PDO::FETCH_ASSOC);			
				echo json_encode($datos);
				break;
			case 'guardarPuntoCanje':
				$sql = "UPDATE puntos_valor_canje SET activo = 0 WHERE activo = 1";
				$sql = $connec->query($sql);
				$sql = "INSERT INTO puntos_valor_canje(fecha, puntos, valor, activo)
						VALUES(CURRENT_TIMESTAMP, 1, $valor, 1);";
				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
					echo 0;
				} else {
					echo 1;
				}
				break;
			case 'lstArticulos':
				$sql = "SELECT codigo, TRIM(descripcion) AS descripcion
						FROM esarticulos
						WHERE activo = B'1' AND
						(lower(descripcion) LIKE lower('%$idpara%')
						OR CAST(codigo AS VARCHAR) = '$idpara')";
				$sql =  $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$txt = '<button type="button" title="Agregar Artículo" onclick="' .
								" agregarArt('" . $row['codigo'] . "', '" . $row['descripcion'] . "')" .
								'" class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
								' style="white-space: normal; line-height: 1;">' . ucwords($row['descripcion']) .
							'</button>';
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => $txt,
					];
				}
				echo json_encode($datos);
				break;
			case 'listaArtPuntos':
				$sql = "SELECT p.codigo, TRIM(a.descripcion) AS descripcion, p.fecha_desde, p.fecha_hasta, p.cantidad_venta, p.puntos, p.activo,
							CAST(p.puntos/p.cantidad_venta AS FLOAT) AS factor,
							(SELECT valor FROM puntos_valor_canje WHERE activo = 1 ORDER BY fecha DESC LIMIT 1) AS valor_canje
						FROM puntos_articulos p
						INNER JOIN esarticulos a ON a.codigo = p.codigo";
				$sql =  $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$fecha ='<span style="display: none">'.$row['fecha_hasta'].'</span>
							<div class="input-group input-daterange date gfechas d-flex mr-auto ml-auto align-items-center">
								<input type="text" id="fechai'.$row['codigo'].'"'.($row['activo']==1?"":" disabled ").'
									class="form-control form-control-sm rounded" style="height: 25px;"
									autocomplete="off" data-inputmask=\'alias\': \'dd-mm-yyyy\'" data-mask placeholder="dd-mm-yyyy"
									onblur="if(this.value==[]) $(this).datepicker(\'setDate\', moment().format(\'DD-MM-YYYY\'));"
									value="'.date('d-m-Y', strtotime($row['fecha_desde'])).'">
								<input type="text" id="fechaf'.$row['codigo'].'"'.($row['activo']==1?"":" disabled ").'
									class="form-control form-control-sm rounded" style="height: 25px;"
									autocomplete="off" data-inputmask=\'alias\': \'dd-mm-yyyy\'" data-mask placeholder="dd-mm-yyyy"
									onblur="if(this.value==[]) $(this).datepicker(\'setDate\', moment().format(\'DD-MM-YYYY\'));"
									value="'.date('d-m-Y', strtotime($row['fecha_hasta'])).'">
							</div>';
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => '<span title="'.$row['codigo'].'">'.$row['descripcion'].'</span>',
						'descriptxt'  => $row['descripcion'],
						'fecha'       => $fecha,
						'cantidad'    => $row['cantidad_venta'],
						'puntos'      => $row['puntos'],
						'factor'      => $row['factor'],
						'activo'      => $row['activo'],
						'valor_canje' => $row['valor_canje'],
					];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'agregarArt':
				$sql = "INSERT INTO puntos_articulos(codigo, fecha_desde, fecha_hasta, cantidad_venta, puntos, activo)
						VALUES($codigo, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, 1, 1)";
				$sql =  $connec->query($sql);
				if(!$sql) {
					$result['status'] = 0;
					$result['query'] = $connec->errorInfo()[2];
					$result['mensaje'] = 'Error al agregar el Art, por favor intente de nuevo';
				} else {
					$result['status'] = 1;
					$result['query'] = null;
					$result['mensaje'] = 'Agregado con éxito';
				}
				echo json_encode($result);
				break;
			case 'inactivarArt':
				$sql = "UPDATE puntos_articulos SET activo = $marcar WHERE codigo = $codigo";
				$sql = $connec->query($sql);
				if(!$sql) {
					$result['status'] = 0;
					$result['query'] = $connec->errorInfo()[2];
					$result['mensaje'] = 'Error al actualizar el Art, por favor intente de nuevo';
				} else {
					$result['status'] = 1;
					$result['query'] = null;
					$result['mensaje'] = 'Actualizado con éxito';
				}
				echo json_encode($result);
				break;
			case 'eliminarArt':
				$sql = "DELETE FROM puntos_articulos WHERE codigo = $codigo";
				$sql = $connec->query($sql);
				if(!$sql) {
					$result['status'] = 0;
					$result['query'] = $connec->errorInfo()[2];
					$result['mensaje'] = 'Error al actualizar el Art, por favor intente de nuevo';
				} else {
					$result['status'] = 1;
					$result['query'] = null;
					$result['mensaje'] = 'Actualizado con éxito';
				}
				echo json_encode($result);
				break;
			case 'guardarArt':
				$fdesde = $fdesde . 'T' . date("H:i:s.v");
				$fhasta = $fhasta . 'T' . date("H:i:s.v");
				$sql = "UPDATE puntos_articulos SET
						fecha_desde = '$fdesde', fecha_hasta = '$fhasta',
						cantidad_venta = $cant, puntos = $puntos
						WHERE codigo = $codigo";
				$sql = $connec->query($sql);
				if(!$sql) {
					$result['status'] = 0;
					$result['query'] = $connec->errorInfo()[2];
					$result['mensaje'] = 'Error al actualizar el Art, por favor intente de nuevo';
				} else {
					$result['status'] = 1;
					$result['query'] = null;
					$result['mensaje'] = 'Actualizado con éxito';
				}
				echo json_encode($result);
				break;
			case 'listaDptoPuntos':
				$sql = "SELECT a.codigo, TRIM(a.descripcion) AS descripcion,
							COALESCE(p.fecha_desde, CURRENT_DATE-1) AS fecha_desde,
							COALESCE(p.fecha_hasta, CURRENT_DATE-1) AS fecha_hasta,
							COALESCE(p.tipo, 1) AS tipo, COALESCE(p.monto, 0) AS monto,
							COALESCE(p.puntos, 0) AS puntos, COALESCE(p.activo, 0) AS activo,
							(CASE WHEN p.monto = 0 THEN 0 ELSE CAST(p.puntos/p.monto AS FLOAT) END) AS factor,
							(SELECT valor FROM puntos_valor_canje WHERE activo = 1
							ORDER BY fecha DESC LIMIT 1) AS valor_canje
						FROM esdpto a
						LEFT JOIN puntos_departamentos p ON p.codigo = a.codigo";
				$sql =  $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$fecha ='<span style="display: none">'.$row['fecha_hasta'].'</span>
							<div class="input-group input-daterange date gfechas d-flex mr-auto ml-auto align-items-center">
								<input type="text" id="fechai'.$row['codigo'].'"'.($row['activo']==1?"":" disabled ").'
									class="form-control form-control-sm rounded" style="height: 25px;"
									autocomplete="off" data-inputmask="\'alias\': \'dd-mm-yyyy\'" data-mask placeholder="dd-mm-yyyy"
									onblur="if(this.value==[]) $(this).datepicker(\'setDate\', moment().format(\'DD-MM-YYYY\'));"
									value="'.date('d-m-Y', strtotime($row['fecha_desde'])).'">
								<input type="text" id="fechaf'.$row['codigo'].'"'.($row['activo']==1?"":" disabled ").'
									class="form-control form-control-sm rounded" style="height: 25px;"
									autocomplete="off" data-inputmask="\'alias\': \'dd-mm-yyyy\'" data-mask placeholder="dd-mm-yyyy"
									onblur="if(this.value==[]) $(this).datepicker(\'setDate\', moment().format(\'DD-MM-YYYY\'));"
									value="'.date('d-m-Y', strtotime($row['fecha_hasta'])).'">
							</div>';
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => '<span title="'.$row['codigo'].'">'.$row['descripcion'].'</span>',
						'descriptxt'  => $row['descripcion'],
						'fecha'       => $fecha,
						'tipo'        => $row['tipo'],
						'monto'       => $row['monto'],
						'puntos'      => $row['puntos'],
						'factor'      => $row['factor'],
						'activo'      => $row['activo'],
						'valor_canje' => $row['valor_canje'],
					];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'guardarDpto':
				$fdesde = $fdesde . 'T' . date("H:i:s.v");
				$fhasta = $fhasta . 'T' . date("H:i:s.v");
				$sql = "INSERT INTO puntos_departamentos(codigo, fecha_desde, fecha_hasta, tipo, monto, puntos, activo)
						VALUES($codigo, '$fdesde', '$fhasta', $tipo, $monto, $puntos, 1)
						ON CONFLICT ON CONSTRAINT puntos_departamentos_pkey DO UPDATE
						SET fecha_desde = '$fdesde', fecha_hasta = '$fhasta',
							tipo = $tipo, monto = $monto, puntos = $puntos;";
				$sql =  $connec->query($sql);
				if(!$sql) {
					$result['status'] = 0;
					$result['query'] = $connec->errorInfo()[2];
					$result['mensaje'] = 'Error al agregar el Art, por favor intente de nuevo';
				} else {
					$result['status'] = 1;
					$result['query'] = null;
					$result['mensaje'] = 'Agregado con éxito';
				}
				echo json_encode($result);
				break;
			case 'inactivarDpto':
				$fdesde = $fdesde . 'T' . date("H:i:s.v");
				$fhasta = $fhasta . 'T' . date("H:i:s.v");
				$sql = "SELECT * FROM puntos_departamentos WHERE codigo = $codigo";
				$sql = $connec->query($sql);
				if($sql->rowCount()>0) {
					$sql = "UPDATE puntos_departamentos SET activo = $marcar WHERE codigo = $codigo";
				} else {
					$sql = "INSERT INTO puntos_departamentos(codigo, fecha_desde, fecha_hasta, tipo, monto, puntos, activo)
							VALUES($codigo, '$fdesde', '$fhasta', 1, $monto, $puntos, $marcar)";
				}
				$sql = $connec->query($sql);
				if(!$sql) {
					$result['status'] = 0;
					$result['query'] = $connec->errorInfo()[2];
					$result['mensaje'] = 'Error al actualizar el Art, por favor intente de nuevo';
				} else {
					$result['status'] = 1;
					$result['query'] = null;
					$result['mensaje'] = 'Actualizado con éxito';
				}
				echo json_encode($result);
				break;
		}
		$connec = null;
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>