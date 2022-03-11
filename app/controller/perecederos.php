<?php
	/**
	* Permite obtener los datos de la base de datos y retornarlos
	* en modo json o array
	*/
	try {
		date_default_timezone_set('America/Bogota');
		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../../dist/config.ini');
		if ($params === false) {
			throw new \Exception("Error reading database configuration file");
		}
		extract($_POST);
		if(isset($sqlcnx)) {
			// connect to the sql server database
			if($params['instance']!='') {
				$conStr = sprintf("sqlsrv:Server=%s\%s;",
					$params['host_sql'],
					$params['instance']);
			} else {
				$conStr = sprintf("sqlsrv:Server=%s,%d;",
					$params['host_sql'],
					$params['port_sql']);
			}
			$connec = new \PDO($conStr, $params['user_sql'], $params['password_sql']);
		} else {
			// connect to the postgresql database
			$conStr = sprintf("pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
						$params['host'],
						$params['port'],
						$params['database'],
						$params['user'],
						$params['password']);
			$connec = new \PDO($conStr);
		}
		$datos = [];
		switch ($opcion) {
			case 'consDispPerecederos':
				$alm_cd = implode(',', $alm_cd);
				$sql = "SELECT
							a.codigo, a.descripcion, COALESCE(a.presentacion, 1) AS empaque,
							COALESCE(gp.DESCRIPCION, '') AS grupo,
							COALESCE(SUM(d.ExistLocal)-(COALESCE(bkt.Cantidad, 0)), 0) AS ExistLocal,
							COALESCE(SUM(d.ExistCedim), 0) AS ExistCedim,
							( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
								WHERE b.escodigo = a.codigo AND b.codigoedi = 1) AS barra,
							(SELECT cantayer FROM BDES.dbo.EST_VENTAS60
								WHERE material = a.codigo AND LOCALIDAD = $idpara) AS rotayer,
							(SELECT cant7dias FROM BDES.dbo.EST_VENTAS60
								WHERE material = a.codigo AND LOCALIDAD = $idpara) AS rot7dia,
							MAX(COALESCE(excl.excluido, 0)) AS excluido
						FROM BDES.dbo.ESARTICULOS a
						LEFT JOIN (SELECT articulo AS Material,
								(CASE WHEN localidad = $idpara THEN
									SUM(cantidad-usada) END) AS ExistLocal,
								(CASE WHEN localidad = $cedim AND almacen IN ($alm_cd) THEN
									SUM(cantidad-usada) END) AS ExistCedim,
								localidad
							FROM BDES.dbo.BIKardexExistencias
							WHERE localidad = $cedim OR localidad = $idpara
							GROUP BY articulo, localidad, almacen) AS d ON d.MATERIAL = a.codigo
						LEFT JOIN BDES.dbo.ESGrupos gp ON gp.codigo = a.grupo
						LEFT JOIN BDES.dbo.DBArtLocExclSol excl
							ON excl.codigo = a.codigo AND excl.localidad = $idpara
						LEFT JOIN BDES.dbo.BIKardexExistencias_Tienda bkt
							ON bkt.ARTICULO = a.codigo AND bkt.localidad = $idpara
						WHERE a.activo = 1 AND a.CPE = $cpe
						GROUP BY gp.DESCRIPCION, a.grupo, a.codigo, a.presentacion,
							a.descripcion, bkt.Cantidad";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$ExistLocalval  =(.000==$row['ExistLocal']) ? 0 : intval($row['ExistLocal']);
					$ExistCedimlval =(.000==$row['ExistCedim']) ? 0 : intval($row['ExistCedim']);
					$empaquelval    =(.000==$row['empaque'])    ? 0 : $row['empaque'];
					$barra = '<span style="display: none;">'.$row['codigo'].'</span>';
					$barra.= '<span title="'.$row['codigo'].'">'.$row['barra'].'</span>';
					$sugerido = intval(($row['rot7dia']/7)*$direpo);
					$checked  = ($row['excluido']*1==1) ? 'checked' : '';
					$excluido = ($row['excluido']*1==1) ? 'excluir' : 'incluir';
					$desmenos = '<span style="display: none">'.$row['descripcion'].'</span>'.
								'<i class="fas fa-minus rounded border border-dark mt-auto mb-auto" '.
								' title="Excluir Artículo de la Lista"'.
								' style="cursor: pointer; color: #dc3545; padding: 1px 2px 1px 2px;'.
								' background-color: #FFF"></i>';
					$desmas =  '<span style="display: none">'.$row['descripcion'].'</span>'.
								'<i class="fas fa-plus rounded border border-dark mt-auto mb-auto" '.
								' title="Incluir Artículo a la Lista"'.
								' style="cursor: pointer; color: #28a745; padding: 1px 2px 1px 2px;'.
								' background-color: #FFF"></i>';
					if($row['excluido']==1) {
						$txt = $desmas;
					} else {
						$txt = $desmenos;
					}
					$datos[] = [
						'material'    => $row['codigo'],
						'ExistLocal'  => $ExistLocalval,
						'ExistCedim'  => $ExistCedimlval,
						'grupo'       => $row['grupo'],
						'excluir'     => $txt,
						'descripcion' => $row['descripcion'],
						'barra'       => $barra,
						'empaque'     => $empaquelval,
						'rotayer'     => '<button tabindex="-1" type="button" title="Ver Rotación" onclick="'.
											'verDetRotaArtLoc('.$row['codigo'].','.$idpara.')" '.
											'class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" '.
											'style="white-space: normal; line-height: 1;">'.
											number_format($row['rotayer']*1, 2).
										'</button>',
						'rot7dia'     => '<button tabindex="-1" type="button" title="Ver Rotación" onclick="'.
											'verDetRotaArtLoc('.$row['codigo'].','.$idpara.')" '.
											'class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" '.
											'style="white-space: normal; line-height: 1;">'.
											number_format($row['rot7dia']*1, 2).
										'</button>',
						'nrot7dia'    => $row['rot7dia']*1,
						'vrotayer'    => number_format($row['rotayer'],2),
						'vrot7dia'    => number_format($row['rot7dia'],2),
						'inppedir'    => '<input type = "number" min = "0" max = "???"' .
											' onblur = "if($(this).val()==0) { $(this).val([]); }' .
											' 	resaltar(this,0); "' .
											' onfocus="resaltar(this,1); $(this).select()" ' .
											' onkeydown="return tabE(this,event);"' .
											' data-material = "' . $row['codigo'] . '"' .
											' data-exilocal = "' . $ExistLocalval . '"' .
											' data-exicedim = "' . $ExistCedimlval . '"' .
											' id="inpund' . $row['codigo'] . '"' .
											' name="pedido[]" value="" class="p-0 m-0 text-center selectSize">',
						'sugerido'    => '<span id="sug'.$row['codigo'].'">'.number_format($sugerido, 0).'</span>',
						'excluido'    => $row['excluido']*1,
						'desmas'      => $desmas,
						'desmenos'    => $desmenos,
					];
				}
				echo json_encode($datos);
				break;
			case 'savePediPerecederos':
				$pedido   = explode(',', $pedido);
				$material = explode(',', $material);
				$exicedim = explode(',', $exicedim);
				$exilocal = explode(',', $exilocal);
				$sql = "INSERT INTO BDES.dbo.BISolicPedido(
							solipedi_fechasoli,localidad,solipedi_usuariosoli,
							solipedi_fechadesp,solipedi_usuariodesp,solipedi_status, centro_dist)
						VALUES (CURRENT_TIMESTAMP, $tienda, '$usidnom', NULL, 0, 0, $cedim)";
				$result = $sql;
				$sql = $connec->query($sql);
				if($sql):
					$sql   ="SELECT IDENT_CURRENT('BDES.dbo.BISolicPedido') as solipedi_id ";
					$sql   = $connec->query($sql);
					$sql   = $sql->fetch();
					$codid = $sql['solipedi_id'];
					$sql   = "INSERT INTO BDES.dbo.BISoliPediDet(
								solipedi_id, solipedidet_codigo, solipedidet_empaque,
								solipedidet_existcedim, solipedidet_existlocal,
								solipedidet_pedido)
							VALUES ";
					for ($i=0; $i < count($material); $i++) :
							$sql .= "(" .$codid. ",".$material[$i].", 1,".
										$exicedim[$i].",".$exilocal[$i].", ".
										$pedido[$i]."),";
					endfor;
					$result = $sql;
					$sql = $connec->query(substr($sql, 0, -1));
					if($sql) {
						$result = '1¬Orden procesada correctamente.¬'.$codid;
					} else {
						$result = '0¬Hubo un error, no se creo la orden' . chr(10) . $result;
					}
				else:
					$result = '0¬Hubo un error, no se creo la orden' . chr(10) . $result;
				endif;
				echo json_encode($result);
				break;
			case 'lstPendPerecederos':
				$tiendas   = [];
				$articulos = [];
				$datos     = [];
				$thtabla   = '';
				$tabla     = '';
				$totexis   = 0;
				$sql = "SELECT codigo, nombre
						FROM BDES.dbo.ESSucursales
						WHERE activa = 1
						AND codigo IN( $idloca )
						ORDER BY rtrim(ltrim(nombre))";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$tiendas[] = $row;
				}
				$sql = "UPDATE BDES.dbo.BISolicPedido SET
						solipedi_status = 6,
						solipedi_fechaespera = CURRENT_TIMESTAMP,
						solipedi_usuarioespera = '$idpara'
						WHERE centro_dist = $cedim AND solipedi_status = 0
						AND localidad IN( $idloca )";
				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
				} else {
					$alm_cd = implode(',', $alm_cd);
					$sql = "SELECT distinct
								det.solipedidet_codigo AS codigo,
								art.descripcion,
								COALESCE(CAST(d.ExistCedim AS NUMERIC(18,0)), 0) AS existcedim
							FROM BDES.dbo.BISolicPedido AS cab
							INNER JOIN BDES.dbo.BISoliPediDet AS det ON det.solipedi_id = cab.solipedi_id
							INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.solipedidet_codigo
							LEFT JOIN
								(SELECT articulo, SUM(cantidad-usada) AS ExistCedim
								FROM BDES.dbo.BIKardexExistencias
								WHERE localidad = $cedim AND almacen IN ($alm_cd)
								GROUP BY articulo) AS d ON d.articulo = det.solipedidet_codigo
							WHERE centro_dist = $cedim AND solipedi_status = 6 AND localidad IN( $idloca )
							ORDER BY art.descripcion";
					$sql = $connec->query($sql);
					if(!$sql) {
						print_r($connec->errorInfo());
					} else {
						while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
							$articulos[] = $row;
						}
						$sql = "SELECT
									'c'+CAST(cab.localidad AS VARCHAR)+CAST(det.solipedidet_codigo AS VARCHAR) AS id,
									det.solipedidet_codigo AS codigo, SUM(det.solipedidet_pedido) AS pedido
								FROM BDES.dbo.BISolicPedido AS cab
								INNER JOIN BDES.dbo.BISoliPediDet AS det ON det.solipedi_id = cab.solipedi_id
								WHERE centro_dist = $cedim AND solipedi_status = 6 AND localidad IN( $idloca )
								GROUP BY cab.localidad, det.solipedidet_codigo";
						$sql = $connec->query($sql);
						if(!$sql) {
							print_r($connec->errorInfo());
						} else {
							while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
								$datos[] = $row;
							}
							$thtabla = '<table ';
							$thtabla.= 'class="table-bordered table-striped table-hover txtcomp table-sticky"';
							$thtabla.= ' id="thtblConsolida">';
							$thtabla.= '<thead class="text-center">';
							$thtabla.= '<tr>';
							$thtabla.= '<th id="th0" class="bg-primary-gradient"><small>CÓDIGO</small></th>';
							$thtabla.= '<th id="th1" class="bg-primary-gradient"><small>DESCRIPCIÓN</small></th>';
							$thtabla.= '<th id="th2" class="bg-primary-gradient"><small>EXIST.</small></th>';
							$thtabla.= '<th id="th3" title="Cantidad para la Compra" class="bg-warning-gradient"><small>PEDIR</small></th>';
							$thtabla.= '<th id="th4" title="Total General UND." class="bg-success-gradient"><small>TOTAL</small></th>';
							$i=5;
							foreach ($tiendas as $tienda) {
								$thtabla.= '<th id="th'.$i.'" title="'.$tienda['nombre'].'"';
								$thtabla.= ' class="bg-secondary-gradient"><small>' .
											substr($tienda['nombre'], 0, 5) . '</small></th>';
								$i++;
							}
							$thtabla.= '</tr></thead></table>';
							$width = intval(64/count($tiendas));
							$tabla = '<table width="100%" style="font-size: 90%" ';
							$tabla.= 'class="table-bordered table-striped table-hover txtcomp table-sticky"';
							$tabla.= ' id="tblConsolida"><tbody>';
							foreach ($articulos as $articulo) {
								$tabla.= '<tr>';
								$tabla.= '<td width="4%">' . $articulo['codigo'] . '</td>';
								$tabla.= '<td width="20%">' . $articulo['descripcion'] . '</td>';
								$tabla.= '<td width="4%" align="right" id="ex'.$articulo['codigo'].'" data-val="'.($articulo['existcedim']*1).'">' . number_format($articulo['existcedim'], 0) . '</td>';
								$tabla.= '<td width="4%" align="right" id="f'.$articulo['codigo'].'">';
								$tabla.= '<input type = "number" value="" ' .
											' onblur="if($(this).val()==0) $(this).val([]); ' .
											'	resaltar(this, 0); ' .
											'	acttotal(this, '.$articulo['codigo'].' ); "' .
											' onkeyup="if(event.keyCode==13) acttotal(this, '.$articulo['codigo'].');"'.
											' onfocus="resaltar(this,1); $(this).select()"' .
											' onkeydown="return tabE(this,event);"' .
											' data-codigo="'.$articulo['codigo'].'"'.
											' id="inp' . $articulo['codigo'] . '"' .
											' class="p-0 m-0 ml-1 mr-1 text-center selectSize">';
								$tabla.= '</td>';
								$tabla.= '<td width="4%" data-total="0" data-val="0"';
								$tabla.= ' align="right" id="t'.$articulo['codigo'].'">0</td>';
								foreach ($tiendas as $tienda) {
									$tabla.= '<td width="'.$width.'%" align="right" data-val="0"';
									$tabla.= ' id="c'.$tienda['codigo'].$articulo['codigo'].'"></td>';
								}
								$tabla.= '</tr>';
								$totexis += $articulo['existcedim'];
							}
							$tabla.= '</tbody></table>';
							$fotabla = '<table style="font-size: 90%" ';
							$fotabla.= 'class="table-bordered table-striped table-hover txtcomp table-sticky"';
							$fotabla.= ' id="fotblConsolida">';
							$fotabla.= '<thead class="text-right">';
							$fotabla.= '<tr>';
							$fotabla.= '<th id="fo0" class="bg-primary-gradient text-center"></th>';
							$fotabla.= '<th id="fo1" colspan="2" class="bg-primary-gradient">TOTAL GENERAL:&emsp;</th>';
							$fotabla.= '<th id="fo2" data-val="0" class="bg-primary-gradient"></th>';
							$fotabla.= '<th id="fo3" data-val="0" class="bg-warning-gradient"></th>';
							$fotabla.= '<th id="fo4" data-val="0" class="bg-success-gradient"></th>';
							$i=5;
							foreach ($tiendas as $tienda) {
								$fotabla.= '<th id="fo'.$i.'" data-val="0"';
								$fotabla.= ' class="bg-secondary-gradient"></th>';
								$i++;
							}
							$fotabla.= '</tr></thead></table>';
						}
					}
				}
				echo json_encode(
					array(
						'thtabla' => $thtabla,
						'tabla'   => $tabla,
						'fotabla' => $fotabla,
						'datos'   => $datos,
						'totexis' => $totexis
					)
				);
				break;
			case 'procPediPerecederos':
				$codigos  = explode(',', $codigos);
				$cantidad = explode(',', $cantidad);
				$existen  = explode(',', $existen);
				$result = [
					'status'  => 0,
					'mensaje' => 'Hubo un error, no se creo la orden',
					'query'   => '',
					'enlace'  => '',
					'archivo' => '',
				];
				$sql = "INSERT INTO BDES.dbo.DBCmpPerecederosC(fecha_registro, usuario_registro)
						VALUES (CURRENT_TIMESTAMP, '$usidnom');";
				$result['query'] = $sql;
				$sql = $connec->query(substr($sql, 0, -1));
				if($sql) {
					$sql   ="SELECT IDENT_CURRENT('BDES.dbo.DBCmpPerecederosC') AS id ";
					$sql   = $connec->query($sql);
					$sql   = $sql->fetch();
					$codid = $sql['id'];
					$sql = "INSERT INTO BDES.dbo.DBCmpPerecederosD(id_cab, codigo, cantidad, existcedim)
							VALUES ";
					for ($i=0; $i < count($codigos); $i++) {
						$sql .= "(" . $codid . "," . $codigos[$i] .", ".$cantidad[$i].", ".$existen[$i]."),";
					}
					$result['query'] = $sql;
					$sql = $connec->query(substr($sql, 0, -1));
					if($sql) {
						$sql = "UPDATE BDES.dbo.BISolicPedido SET
								solipedi_nrodespacho = $codid,
								solipedi_status = 7
								WHERE solipedi_status = 6 AND centro_dist = $cedim
								AND localidad IN( $idloca );
								UPDATE BDES.dbo.BISoliPediDet SET
								solipedidet_nrodespacho = $codid
								WHERE solipedi_id IN(
									SELECT solipedi_id FROM BDES.dbo.BISolicPedido
									WHERE solipedi_nrodespacho = $codid
										AND centro_dist = $cedim
										AND solipedi_status = 7)";
						$result['query'] = $sql;
						$sql = $connec->query($sql);
						if($sql) {
							$sql = "MERGE INTO BDES.dbo.DBKardexCompras AS destino
									USING (SELECT codigo, cantidad
									FROM BDES.dbo.DBCmpPerecederosD WHERE id_cab = $codid) AS reg
									ON destino.codigo = reg.codigo
									WHEN MATCHED THEN
										UPDATE SET cantidad = destino.cantidad + reg.cantidad,
										fecha_modificacion = CURRENT_TIMESTAMP
									WHEN NOT MATCHED THEN
										INSERT(codigo, cantidad, fecha_modificacion)
										VALUES(reg.codigo, reg.cantidad, CURRENT_TIMESTAMP);";
							$result['query'] = $sql;
							$sql = $connec->query($sql);
							if($sql) {
								$result['status'] = '1';
								$result['mensaje'] = 'Orden '.$codid.' procesada correctamente.';
								$result['enlace'] = $codid;
							} else {
								$result['mensaje'] = 'Hubo un error, no se el kardex de compras<br>' . $connec->errorInfo()[2];
							}
						} else {
							$result['mensaje'] = 'Hubo un error, no se actualizaron los pedidos<br>' . $connec->errorInfo()[2];
						}
					} else {
						$result['mensaje'] = 'Hubo un error, no se creo el detalle de la orden<br>' . $result['query'] . '<br>' . $connec->errorInfo()[2];
					}
				} else {
					$result['mensaje'] = 'Hubo un error, no se creo la orden<br>' . $connec->errorInfo()[2];
				}
				echo json_encode($result);
				break;
			case 'artDispoPerecederos':
				$alm_cd = implode(',', $alm_cd);
				$sql="SELECT
						a.codigo,
						( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
						WHERE b.escodigo = a.codigo AND b.codigoedi = 1) AS barra,
						a.descripcion, COALESCE(CAST(d.ExistCedim AS NUMERIC(18,0)), 0) AS existcedim
					FROM BDES.dbo.ESARTICULOS a
					LEFT JOIN
						(SELECT articulo, SUM(cantidad-usada) AS ExistCedim
						FROM BDES.dbo.BIKardexExistencias
						WHERE localidad = $cedim AND almacen IN ($alm_cd)
						GROUP BY articulo) AS d ON d.articulo = a.codigo
					WHERE a.activo = 1 AND a.CPE = $cpe";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'barra'       => $row['barra'],
						'descripcion' => '<button type="button" title="Seleccionar" onclick="' .
											"agregararticulo(".$row['codigo'].", '".$row['descripcion']."', ".$row['existcedim'].")".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold txtcomp">' .
											$row['descripcion'].
										'</button>',
						'existcedim'  => $row['existcedim'] ,
					];
				}
				echo json_encode($datos);
				break;
			case 'genHDTPerecederos':
				$tiendas   = [];
				$articulos = [];
				$pedido    = [];
				$comprar   = [];
				$totales   = [];
				$fdesde    = '';
				$fhasta    = '';
				$result = 0;
				$sql = "SELECT codigo, nombre
						FROM BDES.dbo.ESSucursales
						WHERE activa = 1
						AND codigo IN( SELECT cab.localidad
							FROM BDES.dbo.BISolicPedido AS cab
							WHERE cab.centro_dist = $cedim AND cab.solipedi_status = 7
							AND cab.solipedi_nrodespacho = $nrodes )
						ORDER BY rtrim(ltrim(nombre))";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$tiendas[] = $row;
				}
				$sql = "SELECT DISTINCT det.codigo, art.descripcion, det.existcedim
						FROM BDES.dbo.DBCmpPerecederosD AS det
						INNER JOIN BDES.dbo.BISolicPedido AS cab ON cab.solipedi_nrodespacho = det.id_cab
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.codigo
						WHERE cab.centro_dist = $cedim
						AND cab.solipedi_status = 7
						AND cab.solipedi_nrodespacho = $nrodes
						ORDER BY art.descripcion";
				$sql = $connec->query($sql);
				if(!$sql) {
					$result = 0;
					print_r($connec->errorInfo());
				} else {
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$articulos[] = $row;
					}
					$result = count($articulos)>0 ? 1 : 0;
					$sql = "SELECT 'c'+CAST(cab.localidad AS VARCHAR)+'_'+CAST(det.solipedidet_codigo AS VARCHAR) AS id,
								SUM(det.solipedidet_pedido) AS pedido
							FROM BDES.dbo.BISolicPedido AS cab
							INNER JOIN BDES.dbo.BISoliPediDet AS det ON det.solipedi_id = cab.solipedi_id
							WHERE cab.centro_dist = $cedim AND cab.solipedi_status = 7
								AND cab.solipedi_nrodespacho = $nrodes
							GROUP BY cab.localidad, det.solipedidet_codigo
							ORDER BY det.solipedidet_codigo, cab.localidad";
					$sql = $connec->query($sql);
					if(!$sql) {
						$result = 0;
						print_r($connec->errorInfo());
					} else {
						while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
							$pedido[] = $row;
						}
						$result = count($pedido)>0 ? 1 : 0;
						if($result==1) {
							$sql = "SELECT MIN(cab.solipedi_fechasoli) AS desde, MAX(cab.solipedi_fechasoli) AS hasta
									FROM BDES.dbo.BISolicPedido AS cab
									WHERE cab.centro_dist = $cedim AND cab.solipedi_status = 7
									AND cab.solipedi_nrodespacho = $nrodes";
							$sql = $connec->query($sql);
							$row = $sql->fetch(\PDO::FETCH_ASSOC);
							$fdesde = date('d-m-Y h:i a', strtotime($row['desde']));
							$fhasta = date('d-m-Y h:i a', strtotime($row['hasta']));
						}
						$sql = "SELECT DISTINCT con.codigo, con.cantidad
								FROM BDES.dbo.DBCmpPerecederosD AS con
								INNER JOIN BDES.dbo.BISolicPedido AS cab ON cab.solipedi_nrodespacho = con.id_cab
								WHERE cab.centro_dist = $cedim AND cab.solipedi_status = 7
								AND cab.solipedi_nrodespacho = $nrodes
								ORDER BY con.codigo";
						$sql = $connec->query($sql);
						if(!$sql) {
							$result = 0;
							print_r($connec->errorInfo());
						} else {
							while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
								$comprar[] = $row;
							}
							$result = count($comprar)>0 ? 1 : 0;
							$sql = "SELECT det.solipedidet_codigo AS codigo, SUM(det.solipedidet_pedido) AS pedido
									FROM BDES.dbo.BISolicPedido AS cab
									INNER JOIN BDES.dbo.BISoliPediDet AS det ON det.solipedi_id = cab.solipedi_id
									WHERE cab.centro_dist = $cedim AND cab.solipedi_status = 7
									AND cab.solipedi_nrodespacho = $nrodes
									GROUP BY det.solipedidet_codigo";
							$sql = $connec->query($sql);
							if(!$sql) {
								$result = 0;
								print_r($connec->errorInfo());
							} else {
								while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
									$totales[] = $row;
								}
								if(count($totales)>0) {
									$result = 1;
								} else {
									$result = 0;
									print_r($connec->errorInfo());
								}
							}
						}
					}
				}
				if($result==1) {
					require_once "../../Classes/PHPExcel.php";
					require_once "../../Classes/PHPExcel/Writer/Excel5.php";
					$objPHPExcel = new PHPExcel();
					// Set document properties
					$objPHPExcel->getProperties()
						->setCreator("Dashboard")
						->setLastModifiedBy("Dashboard")
						->setTitle("Hoja de Trabajo Perecederos ".$fecha)
						->setSubject("Hoja de Trabajo Perecederos ".$fecha)
						->setDescription("Hoja de Trabajo Perecederos ".$fecha." generado usando el Dashboard.")
						->setKeywords("Office 2007 openxml php")
						->setCategory("Hoja de Trabajo Perecederos ".$fecha);
					$objPHPExcel->setActiveSheetIndex(0);
					$icorr = $fecha;
					$letra = 70;
					$objPHPExcel->getActiveSheet()->mergeCells('A1:'.chr($letra+count($tiendas)-1).'2');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A1', 'ARTÍCULOS PEDIDOS POR LAS TIENDAS A PERECEDEROS');
					$objPHPExcel->getActiveSheet()->mergeCells('A3:'.chr($letra+count($tiendas)-1).'3');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A3', 'Pedidos Realizados del '.$fdesde.' al '.$fhasta);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:A3')
						->getAlignment()
						->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:A3')
						->getAlignment()
						->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
					$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(16);
					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Logo');
					$objDrawing->setDescription('Logo');
					$objDrawing->setPath('../../dist/img/logo-ppal.png');
					$objDrawing->setCoordinates('A1');
					$objDrawing->setResizeProportional(false);
					$objDrawing->setWidthAndHeight(120,60);
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
					$rowCount = 4;
					$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, 'CÓDIGO');
					$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, 'DESCRIPCIÓN');
					$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, 'EXIST.');
					$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, 'COMPRAR');
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, 'PEDIDOS');
					foreach ($tiendas as $tienda) {
						$objPHPExcel->getActiveSheet()->SetCellValue(chr($letra).$rowCount, substr($tienda['nombre'], 0, 4));
						$letra++;
					}
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.$objPHPExcel->getActiveSheet()
							->getHighestColumn().'4')->getFont()->setBold(true);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.$objPHPExcel->getActiveSheet()
							->getHighestColumn().'4')->getFont()->setSize(13);
					$objPHPExcel->getActiveSheet()
						->getStyle('A3:'.$objPHPExcel->getActiveSheet()
							->getHighestColumn().'3')->getFont()->setSize(11);
					$objPHPExcel->getActiveSheet()
								->getStyle('A4:'.chr($letra-1).'4')->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB('FFD3D3D3');
					$flag = -3;
					$rowCount++;
					foreach ($articulos as $articulo) {
						$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, $articulo['codigo']);
						$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $articulo['descripcion']);
						$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, $articulo['existcedim']);
						$key = array_search($articulo['codigo'], array_column($comprar, 'codigo'));
						$cantcompra = $comprar[$key]['cantidad'];
						$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, $cantcompra);
						$key = array_search($articulo['codigo'], array_column($totales, 'codigo'));
						if($key!==false) {
							$objPHPExcel->getActiveSheet()
								->SetCellValue('E'.($rowCount),
									'=SUM(F'.$rowCount.':'.chr(69+count($tiendas)).$rowCount.')');
							if($totales[$key]['pedido']!=$cantcompra) {
								$objPHPExcel->getActiveSheet()
									->getStyle('E'.($rowCount))
									->getFont()
									->setStrikethrough(true)
									->setItalic(true)
									->getColor()
									->setRGB('6F6F6F');
							} else {
								$objPHPExcel->getActiveSheet()
									->getStyle('E'.($rowCount))
									->getFont()
									->setStrikethrough(false)
									->getColor()
									->setRGB('000000');
							}
						}
						$letra = 70;
						foreach ($tiendas as $tienda) {
							$buscar = 'c'.$tienda['codigo'].'_'.$articulo['codigo'];
							$key = array_search($buscar, array_column($pedido, 'id'));
							if($key!==false) {
								$objPHPExcel->getActiveSheet()
									->SetCellValue(chr($letra).$rowCount, $pedido[$key]['pedido']);
							}
							$letra++;
						}
						if($flag >= 0) {
							$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowCount.':'.chr($letra-1).$rowCount)->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB('FFDCDCDC');
						}
						$flag++;
						if($flag == 3) $flag = -3;
						$rowCount++;
					}
					foreach (range('B', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
						$objPHPExcel
								->getActiveSheet()
								->getColumnDimension($col)
								->setAutoSize(true);
					}
					$objPHPExcel->getActiveSheet()
						->SetCellValue('C'.($rowCount), '=SUM(C5:C'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('D'.($rowCount), '=SUM(D5:D'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('E'.($rowCount), '=SUM(E5:E'.($rowCount-1).')');
					$letra = 70;
					foreach ($tiendas as $tienda) {
						$objPHPExcel->getActiveSheet()
							->SetCellValue(chr($letra).($rowCount),
								'=SUM('.chr($letra).'5:'.chr($letra).($rowCount-1).')');
						$letra++;
					}
					$objPHPExcel->getActiveSheet()
						->getStyle('C'.($rowCount).':'.$objPHPExcel->getActiveSheet()
							->getHighestColumn().($rowCount))->getFont()->setBold(true);
					$objPHPExcel->getActiveSheet()
						->getPageSetup()
						->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
					$objPHPExcel->getActiveSheet()->getPageSetup()->setFitToWidth(1);
					$objPHPExcel->getActiveSheet()->getPageSetup()->setFitToHeight(0);
					$objPHPExcel->getActiveSheet()->getPageMargins()->setTop(0.15);
					$objPHPExcel->getActiveSheet()->getPageMargins()->setRight(0.2);
					$objPHPExcel->getActiveSheet()->getPageMargins()->setLeft(0.15);
					$objPHPExcel->getActiveSheet()->getPageMargins()->setBottom(0.2);
					$objPHPExcel->getActiveSheet()->getPageSetup()->setHorizontalCentered(true);
					$objPHPExcel->getActiveSheet()->getPageSetup()->setVerticalCentered(false);
					$objPHPExcel->getActiveSheet()
						->getPageSetup()
						->setRowsToRepeatAtTopByStartAndEnd(1, 5);
					// Rename worksheet
					$objPHPExcel->getActiveSheet()->setTitle('HDTPerecederos');
					// Set active sheet index to the first sheet, so Excel opens this as the first sheet
					$objPHPExcel->setActiveSheetIndex(0);
					$styleArray = array(
						'allborders' => array(
							'style' => PHPExcel_Style_Border::BORDER_THIN
						)
					);
					$objPHPExcel->getActiveSheet()
						->getStyle('A4:'.chr($letra-1).($rowCount-1))
						->getBorders()
						->applyFromArray($styleArray);
					$objPHPExcel->getActiveSheet()
						->getStyle('A4:'.$objPHPExcel->getActiveSheet()
							->getHighestColumn().'4')->getFont()->setSize(12);
					foreach (range('B4', $objPHPExcel->getActiveSheet()->getHighestDataColumn().'4') as $col) {
						$objPHPExcel
								->getActiveSheet()
								->getColumnDimension($col)
								->setAutoSize(true);
					}
					$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(false);
					$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(11);
					$objPHPExcel->getActiveSheet()->setSelectedCells('A5');
					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setDifferentOddEven(false);
					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setOddHeader('&R&D &T'.chr(10).'(Pág. &P / &N)');
					// Redirect output to a client’s web browser (Excel5)
					header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
					header('Content-Disposition: attachment;filename="HDTPerecederos.xls"');
					header('Cache-Control: max-age=0');
					// If you're serving to IE 9, then the following may be needed
					header('Cache-Control: max-age=1');
					// If you're serving to IE over SSL, then the following may be needed
					header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
					header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
					header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
					header ('Pragma: public'); // HTTP/1.0
					$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
					$objWriter->save('../../tmp/HDTPerecederos_'.$icorr.'.xls');
					echo json_encode(array('enlace'=>'tmp/HDTPerecederos_'.$icorr.'.xls', 'archivo'=>'HDTPerecederos_'.$icorr.'.xls'));
				} else {
					echo json_encode(array('enlace'=>'', 'archivo'=>''));
				}
				break;
			case 'addArtTMPCmp':
				$sql = "SELECT 0 AS id, art.codigo, art.descripcion, 0 AS cantidad,	0 AS pedido,
							COALESCE(uc.costo, 0) AS costo, COALESCE(uc.precio, 0) AS precio1,
							art.impuesto, uc.margen_ref, COALESCE(buc.costo, 0) AS costoliq, COALESCE(buc.precio1, 0) AS precio1liq,
							uc.fecha_ultimamod AS fechadbc, buc.fechahora AS fechaliq,
							COALESCE(CAST(d.ExistCedim AS NUMERIC(18,0)), 0) AS existcedim
						FROM BDES.dbo.ESARTICULOS AS art
						LEFT JOIN BDES.dbo.BIDocumentoSincroUCosto AS buc ON buc.articulo = art.codigo AND buc.sucursal = 6
						LEFT JOIN BDES.dbo.DBCostoArticulos AS uc ON uc.articulo = art.codigo AND uc.sucursal = 6
						LEFT JOIN
							(SELECT articulo, SUM(cantidad-usada) AS ExistCedim
							FROM BDES.dbo.BIKardexExistencias
							WHERE localidad = 6 AND almacen IN (601, 602)
							GROUP BY articulo) AS d ON d.articulo = art.codigo
						WHERE art.codigo = $idpara";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$margen = round(($row['precio1']==0)?0:($row['precio1']-$row['costo'])/$row['precio1']*100, 2);
					$pvpiva = round($row['precio1']*(1+($row['impuesto']/100)), 2);
					$datos[] = [
						'id'          => 0,
						'codigo'      => $row['codigo'],
						'descripcion' => '<span title="'.$row['codigo'].'">'.$row['descripcion'].'</span>',
						'boton'       => '<button id="btn'.$row['codigo'].'" class="btn btn-light elevation-2 '.
											'border-dark pt-0 pb-0 font-weight-bold" '.
											'style="width: 60px;">0</button>',
						'desctxt'     => $row['descripcion'],
						'impuesto'    => $row['impuesto']*1,
						'cntori'      => $row['pedido']*1,
						'cntnva'      => 0,
						'cntver'      => number_format($row['pedido'], 0),
						'cstori'      => round($row['costo']*1, 2),
						'cstnva'      => 0.00,
						'cstver'      => number_format($row['costo'], 2),
						'marori'      => $margen,
						'marnva'      => 0.00,
						'marver'      => number_format($margen, 2),
						'preori'      => round($row['precio1']*1, 2),
						'prenva'      => 0.00,
						'prever'      => number_format($row['precio1'], 2),
						'pvpori'      => $pvpiva,
						'pvpnva'      => 0.00,
						'pvpver'      => number_format($pvpiva, 2),
						'exiori'      => round($row['existcedim']*1, 2),
						'exiver'      => number_format($row['existcedim'], 2),
						'margenr'     => round($row['margen_ref']*1, 2),
						'fecdbc'      => date('d-m-Y h:i a', strtotime($row['fechadbc'])),
						'fecliq'      => date('d-m-Y h:i a', strtotime($row['fechaliq'])),
						'cstliq'      => number_format($row['costoliq'], 2),
						'marliq'      => number_format($margli, 2),
						'preliq'      => number_format($row['precio1liq'], 2),
						'pvpliq'      => number_format($pvpili, 2),
					];
				}
				echo json_encode($datos);
				break;
			case 'saveTMPCmp':
				$result = [
					'status'  => 0,
					'mensaje' => '<b>Hubo un error, no se pudo realizar la modificación.</b>',
					'query'   => '',
				];
				$datos = $dato;
				if($datos['cntnva']>0) {
					$sql = "MERGE INTO BDES.dbo.DBGesCompra_TMP AS destino
							USING (SELECT
								'".$datos['descripcion']."' AS descripcion,
								'".$datos['desctxt']."' AS desctxt,
								".$datos['impuesto']." AS impuesto,
								'".$datos['boton']."'  AS boton,
								".$datos['cntnva']."  AS cntnva,
								".$datos['cntori']."  AS cntori,
								'".$datos['cntver']."' AS cntver,
								".$datos['cstnva']."  AS cstnva,
								".$datos['cstori']."  AS cstori,
								'".$datos['cstver']."' AS cstver,
								".$datos['marnva']."  AS marnva,
								".$datos['marori']."  AS marori,
								'".$datos['marver']."' AS marver,
								".$datos['prenva']."  AS prenva,
								".$datos['preori']."  AS preori,
								'".$datos['prever']."' AS prever,
								".$datos['pvpnva']."  AS pvpnva,
								".$datos['pvpori']."  AS pvpori,
								'".$datos['pvpver']."' AS pvpver,
								".$datos['exiori']."  AS exiori,
								'".$datos['exiver']."' AS exiver,
								".$datos['margenr']." AS margen_ref) AS origen
							ON proveedor = $idprov AND usuario = '$userid'
								AND centro_dist = 6 AND codigo = ".$datos['codigo']."
							WHEN MATCHED THEN
								UPDATE SET
								descripcion = origen.descripcion,
								desctxt     = origen.desctxt,
								impuesto    = origen.impuesto,
								boton       = origen.boton,
								cntnva      = origen.cntnva,
								cntori      = origen.cntori,
								cntver      = origen.cntver,
								cstnva      = origen.cstnva,
								cstori      = origen.cstori,
								cstver      = origen.cstver,
								marnva      = origen.marnva,
								marori      = origen.marori,
								marver      = origen.marver,
								prenva      = origen.prenva,
								preori      = origen.preori,
								prever      = origen.prever,
								pvpnva      = origen.pvpnva,
								pvpori      = origen.pvpori,
								pvpver      = origen.pvpver,
								exiori      = origen.exiori,
								exiver      = origen.exiver,
								margen_ref  = origen.margen_ref
							WHEN NOT MATCHED THEN
								INSERT(proveedor, usuario, centro_dist, codigo, descripcion, desctxt, impuesto, boton,
										cntnva, cntori, cntver, cstnva, cstori, cstver, marnva, marori, marver, prenva,
										preori, prever,	pvpnva, pvpori, pvpver, exiori, exiver, margen_ref)
								VALUES($idprov, '$userid', 6, ".$datos['codigo'].", origen.descripcion,
										origen.desctxt, origen.impuesto, origen.boton, origen.cntnva, origen.cntori,
										origen.cntver, origen.cstnva, origen.cstori, origen.cstver, origen.marnva,
										origen.marori, origen.marver, origen.prenva, origen.preori, origen.prever,
										origen.pvpnva, origen.pvpori, origen.pvpver, origen.exiori, origen.exiver,
										origen.margen_ref);";
					$result['query'] = $sql;
				} else {
					$sql = "DELETE FROM BDES.dbo.DBGesCompra_TMP
							WHERE proveedor = $idprov AND usuario = '$userid'
							AND centro_dist = 6 AND codigo = ".$datos['codigo'];
					$result['query'] = $sql;
				}
				$sql = $connec->query($sql);
				if(!$sql) {
					$result['query'] = '<em>'.$result['query'].'<br>'.
						substr($connec->errorInfo()[2], strrpos($connec->errorInfo()[2], ']')+1).
						'</em>';
				} else {
					$result['status'] = 1;
					$result['mensaje'] = '';
					$result['query'] = '';
				}
				echo json_encode($result);
				break;
			case 'proGESCmp':
				$result = [
					'status'  => 0,
					'mensaje' => 'Hubo un error, no se pudo generar la ODC',
					'query'   => '',
				];
				$idpara = json_decode($idpara, true);
				foreach ($idpara as $datos) {
					if($datos['codigo']!=0) {
						$codigo = $datos['codigo'];
						$cntnva = $datos['cntnva'];
						$cntori = $datos['cntori'];
						$costo  = $datos['cstnva']>0 ? $datos['cstnva'] : $datos['cstori'];
						$precio = $datos['prenva']>0 ? $datos['prenva'] : $datos['preori'];
						$sql = "MERGE INTO BDES.dbo.DBKardexCompras AS destino
								USING (SELECT $codigo AS codigo, $cntnva AS comprado) AS origen
								ON destino.codigo = origen.codigo
								WHEN MATCHED THEN
									UPDATE SET
										comprado = destino.comprado + origen.comprado,
										dif_demas = (CASE WHEN (origen.comprado+destino.comprado+destino.nocomprar)>destino.cantidad THEN
													(origen.comprado+destino.comprado+destino.nocomprar)-destino.cantidad ELSE destino.dif_demas END)
								WHEN NOT MATCHED THEN
									INSERT(codigo, cantidad, comprado, dif_demas)
									VALUES($codigo, ".($cntori==0?0:'origen.comprado').", origen.comprado, ".($cntori==0?'origen.comprado':0).");";
						$result['query'] = substr($sql, 0, 40);
						$sql = $connec->query($sql);
						if(!$sql || $sql->rowCount()==0) {
							$result['query'] = '<em>'.$result['query'].'<br>'.
								substr($connec->errorInfo()[2], strrpos($connec->errorInfo()[2], ']')+1).
								'</em>';
							echo json_encode($result);
							break;
						}
					}
				}
				$sql = "INSERT INTO BDES.dbo.DBODC_cab
							(usuario_crea, fecha_vence, proveedor, centro_dist)
						VALUES ('$userid', '$fechav', $idprov, 6)";
				$result['query'] = substr($sql, 0, 40);
				$sql = $connec->query($sql);
				if(!$sql || $sql->rowCount()==0) {
					$result['query'] = '<em>'.$result['query'].'<br>'.
						substr($connec->errorInfo()[2], strrpos($connec->errorInfo()[2], ']')+1).
						'</em>';
					echo json_encode($result);
					break;
				} else {
					$sql   ="SELECT IDENT_CURRENT('BDES.dbo.DBODC_cab') AS odc_id";
					$sql   = $connec->query($sql);
					$sql   = $sql->fetch();
					$codid = $sql['odc_id'];
					$sql   = "	INSERT INTO BDES.dbo.DBODC_det(id_cab, codigo, cantidad, costo, precio)
								VALUES ";
					foreach ($idpara as $datos) {
						$codigo    = $datos['codigo'];
						$cantidad  = $datos['cntnva'];
						$costo     = $datos['cstnva']>0 ? $datos['cstnva'] : $datos['cstori'];
						$precio    = $datos['prenva']>0 ? $datos['prenva'] : $datos['preori'];
						$sql .= "($codid, $codigo, $cantidad, $costo, $precio),";
					}
					$sql = substr($sql, 0, -1).'; ';
					$result['query'] = $sql;
					$sql = $connec->query($sql);
					if($sql) {
						$sql = "MERGE INTO BDES.dbo.DBCostoArticulos AS destino
								USING (SELECT codigo, costo, precio, '$userid' AS usuario
										FROM BDES.dbo.DBODC_det
										WHERE id_cab = $codid) AS origen
								ON destino.articulo = origen.codigo AND destino.sucursal = 6
								WHEN MATCHED THEN
									UPDATE SET costo = origen.costo, precio = origen.precio,
									sincro = 1, fecha_ultimamod = GETDATE(), usuario = origen.usuario
								WHEN NOT MATCHED THEN
									INSERT(articulo, sucursal, costo, costo_ant, precio, precio_ant, usuario, sincro)
									VALUES(origen.codigo, 6, origen.costo, origen.costo, origen.precio, origen.precio, '$userid', 1);";
						$result['query'] = $sql;
						$sql = $connec->query($sql);
						if($sql) {
							$sql = "INSERT INTO BDES.dbo.DBCostoArticulos_H(
										articulo, sucursal, status, sincro, costo, costo_ant, margen_ref, precio,
										precio_ant, fecha_ultimamod, documento, usuario)
								VALUES ";
							foreach ($idpara as $datos) {
								$codigo = $datos['codigo'];
								$costo  = $datos['cstnva']>0 ? $datos['cstnva'] : $datos['cstori'];
								$precio = $datos['prenva']>0 ? $datos['prenva'] : $datos['preori'];
								$margenr= $datos['margenr'];
								$sql .= "($codigo, 6, 1, 1, $costo, $costo, $margenr, $precio, $precio, GETDATE(), $codid, '$userid'),";
							}
							$sql = substr($sql, 0, -1).'; ';
							$result['query'] = $sql;
							$sql = $connec->query($sql);
							if(!$sql || $sql->rowCount()==0) {
								$result['query'] = '<em>'.$result['query'].'<br>'.
									substr($connec->errorInfo()[2], strrpos($connec->errorInfo()[2], ']')+1).
									'</em>';
								echo json_encode($result);
								break;
							}
						} else {
							$result['query'] = '<em>'.$result['query'].'<br>'.
								substr($connec->errorInfo()[2], strrpos($connec->errorInfo()[2], ']')+1).
								'</em>';
							echo json_encode($result);
							break;
						}
						$sql = "DELETE FROM BDES.dbo.DBGesCompra_TMP
								WHERE proveedor = $idprov
								AND codigo IN(";
						foreach ($idpara as $datos) {
							$sql.= $datos['codigo'].',';
						}
						$sql = substr($sql, 0, -1) . ')';
						$result['query'] = $sql;
						$sql = $connec->query($sql);
						if(!$sql) {
							$result['status'] = $codid;
							$result['mensaje'] = 'Documento [ '.$codid.' ] generado con advertencias<br>'
												.'<span class="bg-danger font-weight-bold border border-dark p-1>'
												.'Los datos temporales no se pudieron eliminar<br>'
												.'Informe a sistemas con el siguiente código: '.$idprov
												.'</span>';
						} else {
							$result['status'] = $codid;
							$result['mensaje'] = 'Documento [ '.$codid.' ] generado correctamente';
							$result['query'] = '';
						}
					}
				}
				echo json_encode($result);
				break;
			case 'delTMPCmp':
				$sql = "DELETE FROM BDES.dbo.DBGesCompra_TMP
						WHERE proveedor = $idprov
						AND usuario = '$userid'";
				$sql = $connec->query($sql);
				if(!$sql || $sql->rowCount()==0) {
					echo '0¬'+$connec->errorInfo()[2];
				} else {
					echo '1¬Realizado';
				}
				break;
			case 'confArtXProvBDES':
				$sql = "SELECT art.codigo, art.descripcion,
							(SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
							WHERE b.escodigo = art.codigo AND b.codigoedi = 1) AS barra,
							COALESCE(grp.DESCRIPCION, '') AS grupo,
							(CASE WHEN axp.proveedor = $idprov THEN 1 ELSE 0 END) AS seleccionado
						FROM BDES.dbo.ESARTICULOS AS art
							LEFT JOIN BDES.dbo.ESGrupos AS grp ON grp.CODIGO = art.Grupo
							LEFT JOIN (SELECT * FROM BDES.dbo.DBProvArticulos
								WHERE proveedor = $idprov AND eliminado = 0) AS axp
								ON axp.articulo = art.codigo
						WHERE art.activo = 1 AND art.departamento = $iddpto
						ORDER BY art.descripcion";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$txt = ($row['seleccionado']=='1') ? '_' : '&nbsp;';
					$txt.= '<input style="cursor: pointer;" id="chk'.$row['codigo'].'" ';
					$txt.= 'type="checkbox" ';
					$txt.= ($row['seleccionado']=='1') ? 'checked ' : '';
					$txt.= 'onclick="marcarart('.$row['codigo'].')">';
					$txt.= '<label for="chk'.$row['codigo'].'" ';
					$txt.= 'style="cursor: pointer;" class="m-0 p-0">&nbsp;Selec.</label>';
					$des = '<span style="cursor: pointer;" onclick="$('."'#chk".$row['codigo']."').click()" .'">';
					$des.= $row['descripcion'].'</span>';
					$datos[] = [
						'codigo'       => '<span style="display: none;">'.$row['barra'].'</span>'.$row['codigo'],
						'descripcion'  => $des,
						'grupo'        => $row['grupo'],
						'seleccionado' => $txt,
						'id'           => $row['codigo'],
					];
				}
				echo json_encode($datos);
				break;
			case 'exclArtLoc':
				$idpara  = explode('¬', $idpara);
				$codigo  = $idpara[0];
				$idlocal = $idpara[1];
				$excluir = $idpara[2];
				$sql = " MERGE INTO BDES.dbo.DBArtLocExclSol AS destino
							USING (SELECT $codigo AS codigo, $idlocal AS localidad, $excluir As excluir) AS reg
							ON destino.codigo = reg.codigo AND destino.localidad = reg.localidad
							WHEN MATCHED THEN
							UPDATE SET excluido = $excluir
							WHEN NOT MATCHED THEN
							INSERT(codigo, localidad, excluido) VALUES(reg.codigo, reg.localidad, reg.excluir);";
				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
					echo 0;
				} else {
					echo 1;
				}
				break;
			case 'addArtXProv':
				$checke = ($checke==1 ? 0 : 1);
				$sql = "MERGE INTO BDES.dbo.DBProvArticulos AS destino
						USING (SELECT $idprov AS proveedor, $idpara AS articulo) AS reg
						ON destino.proveedor = reg.proveedor AND destino.articulo = reg.articulo
						WHEN MATCHED THEN
							UPDATE SET eliminado = $checke
						WHEN NOT MATCHED THEN
								INSERT(proveedor, articulo) VALUES($idprov, $idpara);";
				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
					if($connec->errorInfo()[0]!=23000) {
						echo json_encode(array('res'=>0, 'msj' => $connec->errorInfo()[2]));
					}
				} else {
					echo json_encode(array('res'=>1, 'msj' => 'Atualización realizada correctamente'));
				}
				break;
			case 'lstArtxProv':
				$sql = "SELECT
							gc.proveedor, gc.usuario, gc.centro_dist, gc.codigo, gc.descripcion, gc.desctxt,
							gc.impuesto, gc.boton, gc.cntnva, gc.cntori, gc.cntver, gc.cstnva, gc.cstori,
							gc.cstver, gc.marnva, gc.marori, gc.marver, gc.prenva, gc.preori, gc.prever,
							gc.pvpnva, gc.pvpori, gc.pvpver, gc.exiori, gc.exiver, gc.margen_ref,
							COALESCE(buc.costo, 0) AS costoliq, COALESCE(buc.precio1, 0) AS precio1liq,
							uc.fecha_ultimamod AS fechadbc, buc.fechahora AS fechaliq
						FROM BDES.dbo.DBGesCompra_TMP gc
						LEFT JOIN BDES.dbo.BIDocumentoSincroUCosto AS buc ON buc.articulo = gc.codigo AND buc.sucursal = 6
						INNER JOIN BDES.dbo.DBCostoArticulos AS uc ON uc.articulo = gc.codigo AND uc.sucursal = 6
						WHERE gc.proveedor = $idpara AND gc.centro_dist = 6
						AND gc.usuario = '$userid'";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datostmp = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datostmp[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => $row['descripcion'],
						'boton'       => $row['boton'],
						'desctxt'     => $row['desctxt'],
						'impuesto'    => $row['impuesto']*1,
						'cntori'      => $row['cntori']*1,
						'cntnva'      => $row['cntnva']*1,
						'cntver'      => $row['cntver'],
						'cstori'      => $row['cstori']*1,
						'cstnva'      => $row['cstnva']*1,
						'cstver'      => $row['cstver'],
						'marori'      => $row['marori']*1,
						'marnva'      => $row['marnva']*1,
						'marver'      => $row['marver'],
						'preori'      => $row['preori']*1,
						'prenva'      => $row['prenva']*1,
						'prever'      => $row['prever'],
						'pvpori'      => $row['pvpori']*1,
						'pvpnva'      => $row['pvpnva']*1,
						'pvpver'      => $row['pvpver'],
						'exiori'      => $row['exiori']*1,
						'exiver'      => $row['exiver'],
						'margenr'     => $row['margen_ref']*1,
						'fecdbc'      => date('d-m-Y h:i a', strtotime($row['fechadbc'])),
						'fecliq'      => date('d-m-Y h:i a', strtotime($row['fechaliq'])),
						'cstliq'      => number_format($row['costoliq'], 2),
						'marliq'      => number_format($margli, 2),
						'preliq'      => number_format($row['precio1liq'], 2),
						'pvpliq'      => number_format($pvpili, 2),
					];
				}
				$sql = "SELECT ped.id, ped.codigo, art.descripcion, ped.cantidad AS cantidad, uc.margen_ref,
							(ped.cantidad-ped.comprado-ped.nocomprar+ped.dif_demas) AS pedido,
							COALESCE(uc.costo, 0) AS costo, COALESCE(uc.precio, 0) AS precio1,
							COALESCE(buc.costo, 0) AS costoliq, COALESCE(buc.precio1, 0) AS precio1liq,
							uc.fecha_ultimamod AS fechadbc, buc.fechahora AS fechaliq,
							art.impuesto, COALESCE(CAST(d.ExistCedim AS NUMERIC(18,0)), 0) AS existcedim
						FROM BDES.dbo.DBKardexCompras AS ped
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = ped.codigo
						LEFT JOIN BDES.dbo.DBProvArticulos AS pro ON pro.articulo = ped.codigo AND pro.eliminado = 0
						LEFT JOIN BDES.dbo.DBCostoArticulos AS uc ON uc.articulo = ped.codigo AND uc.sucursal = 6
						LEFT JOIN BDES.dbo.BIDocumentoSincroUCosto AS buc ON buc.articulo = ped.codigo AND buc.sucursal = 6
						LEFT JOIN
							(SELECT articulo, SUM(cantidad-usada) AS ExistCedim
							FROM BDES.dbo.BIKardexExistencias
							WHERE localidad = 6 AND almacen IN (601, 602)
							GROUP BY articulo) AS d ON d.articulo = ped.codigo
						WHERE pro.proveedor = $idpara
						AND ABS(ped.cantidad-ped.comprado-ped.nocomprar+ped.dif_demas) > 0
						ORDER BY art.descripcion";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datosori = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$margen = round(($row['precio1']==0)?0:($row['precio1']-$row['costo'])/$row['precio1']*100, 2);
					$pvpiva = round($row['precio1']*(1+($row['impuesto']/100)), 2);
					$margli = round(($row['precio1liq']==0)?0:($row['precio1liq']-$row['costoliq'])/$row['precio1liq']*100, 2);
					$pvpili = round($row['precio1liq']*(1+($row['impuesto']/100)), 2);
					$datosori[] = [
						'id'          => $row['id'],
						'codigo'      => $row['codigo'],
						'descripcion' => '<span title="'.$row['codigo'].'">'.$row['descripcion'].'</span>',
						'boton'       => '<button id="btn'.$row['codigo'].'" class="btn btn-light elevation-2 '.
											'border-dark pt-0 pb-0 font-weight-bold" '.
											'style="width: 60px;">0</button>',
						'desctxt'     => $row['descripcion'],
						'impuesto'    => $row['impuesto']*1,
						'cntori'      => $row['pedido']*1,
						'cntnva'      => 0,
						'cntver'      => number_format($row['pedido'], 0),
						'cstori'      => round($row['costo']*1, 2),
						'cstnva'      => 0.00,
						'cstver'      => number_format($row['costo'], 2),
						'marori'      => $margen,
						'marnva'      => 0.00,
						'marver'      => number_format($margen, 2),
						'preori'      => round($row['precio1']*1, 2),
						'prenva'      => 0.00,
						'prever'      => number_format($row['precio1'], 2),
						'pvpori'      => $pvpiva,
						'pvpnva'      => 0.00,
						'pvpver'      => number_format($pvpiva, 2),
						'exiori'      => round($row['existcedim']*1, 2),
						'exiver'      => number_format($row['existcedim'], 2),
						'margenr'     => round($row['margen_ref']*1, 2),
						'fecdbc'      => date('d-m-Y h:i a', strtotime($row['fechadbc'])),
						'fecliq'      => date('d-m-Y h:i a', strtotime($row['fechaliq'])),
						'cstliq'      => number_format($row['costoliq'], 2),
						'marliq'      => number_format($margli, 2),
						'preliq'      => number_format($row['precio1liq'], 2),
						'pvpliq'      => number_format($pvpili, 2),
					];
				}
				foreach ($datostmp as $dato) {
					$idx = array_search($dato['codigo'], array_column($datosori, 'codigo'), true);
					if($idx!==false) {
						$datosori[$idx] = $dato;
					} else {
						$datosori[] = $dato;
					}
				}
				echo json_encode($datosori);
				break;
			case 'lstHDTant':
				$sql = "SELECT DISTINCT cab.id, cab.fecha_registro AS fecha
						FROM BDES.dbo.DBCmpPerecederosC cab
						INNER JOIN BDES.dbo.DBCmpPerecederosD AS det ON det.id_cab = cab.id
						INNER JOIN BDES.dbo.BISolicPedido AS ped ON ped.solipedi_nrodespacho = cab.id
						WHERE ped.centro_dist = $cedim AND CAST(fecha_registro AS DATE) BETWEEN '$fdesde' AND '$fhasta'
						ORDER BY fecha_registro DESC";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$datos = [];
				if(!$sql) {
					print_r($connec->errorInfo());
				} else {
					// Se prepara el array para almacenar los datos obtenidos
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = [
							'id'    => $row['id'],
							'fecha' => date('d-m-Y h:i a', strtotime($row['fecha'])),
						];
					}
				}
				echo json_encode($datos);
				break;
			case 'detHDTant':
				$tiendas   = [];
				$articulos = [];
				$datos     = [];
				$thtabla   = '';
				$tabla     = '';
				$totexis   = 0;
				$sql = "SELECT codigo, nombre
						FROM BDES.dbo.ESSucursales
						WHERE activa = 1
						AND codigo NOT IN(6, 11, 14, 99)
						ORDER BY rtrim(ltrim(nombre))";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$tiendas[] = $row;
				}
				$sql = "SELECT det.codigo, art.descripcion, det.cantidad, det.existcedim
						FROM BDES.dbo.DBCmpPerecederosC AS cab
						INNER JOIN BDES.dbo.DBCmpPerecederosD AS det ON det.id_cab = cab.id
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.codigo
						WHERE cab.id = $idpara
						ORDER BY art.descripcion";
				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
				} else {
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$articulos[] = $row;
					}
					$sql = "SELECT
								'c'+CAST(cab.localidad AS VARCHAR)+CAST(det.solipedidet_codigo AS VARCHAR) AS id,
								det.solipedidet_codigo AS codigo, SUM(det.solipedidet_pedido) AS pedido
							FROM BDES.dbo.BISolicPedido AS cab
							INNER JOIN BDES.dbo.BISoliPediDet AS det ON det.solipedi_id = cab.solipedi_id
							WHERE centro_dist = $cedim AND solipedi_status = 7 AND solipedi_nrodespacho = $idpara
							GROUP BY cab.localidad, det.solipedidet_codigo";
					$sql = $connec->query($sql);
					if(!$sql) {
						print_r($connec->errorInfo());
					} else {
						while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
							$datos[] = $row;
						}
						$thtabla = '<table ';
						$thtabla.= 'class="table-bordered table-striped table-hover txtcomp table-sticky"';
						$thtabla.= ' id="thtblConsolida">';
						$thtabla.= '<thead class="text-center">';
						$thtabla.= '<tr>';
						$thtabla.= '<th id="th0" class="bg-primary-gradient"><small>CÓDIGO</small></th>';
						$thtabla.= '<th id="th1" class="bg-primary-gradient"><small>DESCRIPCIÓN</small></th>';
						$thtabla.= '<th id="th2" class="bg-primary-gradient"><small>EXIST.</small></th>';
						$thtabla.= '<th id="th3" title="Cantidad para la Compra" class="bg-warning-gradient"><small>PEDIR</small></th>';
						$thtabla.= '<th id="th4" title="Total General UND." class="bg-success-gradient"><small>TOTAL</small></th>';
						$i=5;
						foreach ($tiendas as $tienda) {
							$thtabla.= '<th id="th'.$i.'" title="'.$tienda['nombre'].'"';
							$thtabla.= ' class="bg-secondary-gradient"><small>' .
										substr($tienda['nombre'], 0, 5) . '</small></th>';
							$i++;
						}
						$thtabla.= '</tr></thead></table>';
						$width = intval(64/count($tiendas));
						$tabla = '<table width="100%" style="font-size: 90%" ';
						$tabla.= 'class="table-bordered table-striped table-hover txtcomp table-sticky"';
						$tabla.= ' id="tblConsolida"><tbody>';
						foreach ($articulos as $articulo) {
							$tabla.= '<tr>';
							$tabla.= '<td width="4%">' . $articulo['codigo'] . '</td>';
							$tabla.= '<td width="20%">' . $articulo['descripcion'] . '</td>';
							$tabla.= '<td width="4%" align="right" id="ex'.$articulo['codigo'].'" data-val="'.($articulo['existcedim']*1).'">' . number_format($articulo['existcedim'], 0) . '</td>';
							$tabla.= '<td width="'.$width.'%" align="right" id="f'.$articulo['codigo'].'" ';
							$tabla.= 'data-val="'.intval($articulo['cantidad']).'">';
							$tabla.=	number_format($articulo['cantidad'], 0);
							$tabla.= '</td>';
							$tabla.= '<td width="'.$width.'%" data-total="0" data-val="0"';
							$tabla.= ' align="right" id="t'.$articulo['codigo'].'">0</td>';
							foreach ($tiendas as $tienda) {
								$tabla.= '<td width="'.$width.'%" align="right" data-val="0"';
								$tabla.= ' id="c'.$tienda['codigo'].$articulo['codigo'].'"></td>';
							}
							$tabla.= '</tr>';
							$totexis += $articulo['existcedim'];
						}
						$tabla.= '</tbody></table>';
						$fotabla = '<table style="font-size: 90%" ';
						$fotabla.= 'class="table-bordered table-striped table-hover txtcomp table-sticky"';
						$fotabla.= ' id="fotblConsolida">';
						$fotabla.= '<thead class="text-right">';
						$fotabla.= '<tr>';
						$fotabla.= '<th id="fo0" class="bg-primary-gradient text-center"></th>';
						$fotabla.= '<th id="fo1" colspan="2" class="bg-primary-gradient">TOTAL GENERAL:&emsp;</th>';
						$fotabla.= '<th id="fo2" data-val="0" class="bg-primary-gradient"></th>';
						$fotabla.= '<th id="fo3" data-val="0" class="bg-warning-gradient"></th>';
						$fotabla.= '<th id="fo4" data-val="0" class="bg-success-gradient"></th>';
						$i=5;
						foreach ($tiendas as $tienda) {
							$fotabla.= '<th id="fo'.$i.'" data-val="0"';
							$fotabla.= ' class="bg-secondary-gradient"></th>';
							$i++;
						}
						$fotabla.= '</tr></thead></table>';
					}
				}
				echo json_encode(
					array(
						'thtabla' => $thtabla,
						'tabla'   => $tabla,
						'fotabla' => $fotabla,
						'datos'   => $datos,
						'totexis' => $totexis
					)
				);
				break;
			case 'lstPenXBuyPerece':
				$alm_cd = implode(',', $alm_cd);
				$sql = "SELECT a.codigo, a.descripcion,
							( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
							WHERE b.escodigo = a.codigo AND b.codigoedi = 1) AS barra,
							(k.cantidad-k.comprado-k.nocomprar+k.dif_demas) AS pendiente,
							COALESCE(CAST(d.ExistCedim AS NUMERIC(18,0)), 0) AS existcedim
						FROM BDES.dbo.DBKardexCompras k
						INNER JOIN BDES.dbo.ESARTICULOS a ON a.codigo = k.codigo
						LEFT JOIN
								(SELECT articulo, SUM(cantidad-usada) AS ExistCedim
								FROM BDES.dbo.BIKardexExistencias
								WHERE localidad = $cedim AND almacen IN ($alm_cd)
								GROUP BY articulo) AS d ON d.articulo = a.codigo
						WHERE a.activo = 1 AND cpe = $cpe AND (k.cantidad-k.comprado-k.nocomprar+k.dif_demas) > 0";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo' => $row['codigo'],
						'barra' => $row['barra'],
						'descripcion' => $row['descripcion'],
						'existcedim' => $row['existcedim'],
						'pendiente' => $row['pendiente'],
						'opcion' => '<button class="btn btn-sm btn-primary elevation-2 border border-dark">No comprar</button>',
					];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'cerrarCompra':
				$idpara = explode('¬', $idpara);
				$nocomprar = ($idpara[0]=='*') ? 'cantidad-comprado+dif_demas' : 'nocomprar + ' . $idpara[1];
				$codigo = ($idpara[0]=='*') ? 'codigo' : $idpara[0];
				$sql = "UPDATE BDES.dbo.DBKardexCompras SET
						nocomprar = $nocomprar WHERE codigo = $codigo;
						DELETE FROM BDES.dbo.DBGesCompra_TMP
						WHERE codigo = $codigo;";
				$sql = $connec->query($sql);
				if(!$sql) {
					echo '0¬'+$connec->errorInfo()[2];
				} else if($sql->rowCount()==0) {
					echo '0¬No se realizaron modificaciones';
				} else {
					if($idpara[0]=='*') {
						echo '1¬('.$sql->rowCount().') Registros Modificados';
					} else {
						echo '1¬Realizado con exito';
					}
				}
				break;
			case 'monitorPerecederos':
				if($tipo==1) {
					// Se prepara los datos para el monitor
					$sql = "SELECT ped.localidad,
								ti.nombre AS sucursal, ped.solipedi_id AS id,
								RIGHT((CAST('00000' AS VARCHAR)+
								CAST(ped.solipedi_id AS VARCHAR)), 5) AS numero,
								ped.solipedi_fechasoli AS fecha
							FROM BDES.dbo.BISolicPedido AS ped
							INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = ped.localidad
							WHERE centro_dist = $cedim AND ped.localidad = $idpara AND solipedi_status != 2
							AND CAST(ped.solipedi_fechasoli AS DATE) = '$fdesde'";
					$sql = $connec->query($sql);
					// Se prepara el array para almacenar los datos obtenidos
					if(!$sql) print_r($connec->errorInfo());
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$txt = '<button type="button" title="Ver Pedido"
									class="btn btn-sm btn-primary w-100"
									style="white-space: normal; line-height: 1;">'.$row['numero'].
								' 👓</button>';
						$datos[]=[
							'id'         => $row['id'],
							'sucursal'   => $row['sucursal'],
							'numero'     => $txt,
							'fechasoli'  => ($row['fecha']!=null?
											date('d-m-Y H:i', strtotime($row['fecha'])):null),
							'fecha'      => '<span style="display: none;">'.
												$row['fecha'].
											'</span>'.
											($row['fecha']!=null?
											date('d-m-Y H:i', strtotime($row['fecha'])):null),
						];
					}
				} else {
					$sql = "SELECT
								cab.id, cab.fecha_crea, cab.fecha_vence, cab.usuario_crea,
								ti.nombre AS cedim, prov.codigo AS codprov,
								prov.descripcion AS nomprov
							FROM
								BDES.dbo.DBODC_cab AS cab
								INNER JOIN BDES.dbo.ESProveedores AS prov ON prov.codigo = cab.proveedor
								INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = cab.centro_dist
							WHERE cab.centro_dist = $cedim AND cab.status = 0 AND CAST(cab.fecha_crea AS DATE) = '$fdesde'";
					$sql = $connec->query($sql);
					// Se prepara el array para almacenar los datos obtenidos
					if(!$sql) print_r($connec->errorInfo());
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$txt = '<button type="button" title="Ver ODC"
									class="btn btn-sm btn-primary w-100"
									style="white-space: normal; line-height: 1;">'.$row['id'].
								' 👓</button>';
						$datos[]=[
							'id'         => $row['id'],
							'sucursal'   => $row['nomprov'],
							'numero'     => $txt,
							'fechasoli'  => ($row['fecha_crea']!=null?
											date('d-m-Y H:i', strtotime($row['fecha_crea'])):null),
							'fecha'      => '<span style="display: none;">'.
												$row['fecha_crea'].
											'</span>'.
											($row['fecha_crea']!=null?
											date('d-m-Y H:i', strtotime($row['fecha_crea'])):null),
						];
					}
				}
				// Se retorna el resultado de la lista
				echo json_encode(array("data"=>$datos));
				break;
			case 'anularPedidoPerecederos':
				$sql = "UPDATE BDES.dbo.BISolicPedido SET
							solipedi_status = 2,
							justificacion = '[ ".date('d-m-Y H:i:s')." ] => ".$justif."',
							solipedi_responsable = '$userid'
						WHERE solipedi_id = $idpara";
				$sql = $connec->query($sql);
				if(!$sql) {
					echo '0¬Error no se pudo Anulador el Documento.<br>'.$connec->errorInfo()[2];
				} else {
					echo '1¬Documento anulado Correctamente';
				}
				break;
			case 'consPedDesPer':
				$fecpedi = $fecha[0];
				$fecpedf = $fecha[1];
				$fecdesi = date('Y-m-d', strtotime($fecha[0].'1 day'));
				$fecdesf = date('Y-m-d', strtotime($fecha[1].'1 day'));
				$sql = "SELECT localidad, ti.nombre AS tienda,
							COALESCE(SUM(KG_PED), 0) AS KG_PED,
							COALESCE(SUM(UNDPED), 0) AS UNDPED,
							COALESCE(SUM(KG_DES), 0) AS KG_DES,
							COALESCE(SUM(UNDDES), 0) AS UNDDES,
							COALESCE(SUM(COSTO), 0) AS COSTO
						FROM
						(
							SELECT localidad, 0 AS COSTO,
								KILOGRAMOS AS KG_PED,
								UNIDADES AS UNDPED,
								0 AS KG_DES, 0 AS UNDDES
							FROM
							(
								SELECT cab.localidad,
									(CASE WHEN art.tipoarticulo != 0 THEN
										cab.solipedidet_pedido END) AS KILOGRAMOS,
									(CASE WHEN art.tipoarticulo  = 0 THEN
										cab.solipedidet_pedido END) AS UNIDADES
								FROM BDES.dbo.vw_soli_pedi_det AS cab
									INNER JOIN BDES.dbo.ESARTICULOS AS art ON
										art.codigo = cab.solipedidet_codigo
								WHERE cab.solipedidet_codigo = ".(($idpara=='')?'cab.solipedidet_codigo':$idpara)."
									AND	cab.centro_dist = $cedim AND cab.solipedi_status = 7
									AND CAST(cab.solipedi_fechasoli AS DATE)
										BETWEEN '$fecpedi' AND '$fecpedf'
							) AS PEDIDOS
							UNION ALL
							SELECT LOCALIDAD, COSTO, 0 AS KG_PED, 0 AS UNDPED,
								KILOGRAMOS AS KG_DES, UNIDADES AS UND_DES
							FROM
							(
								SELECT bkd.LOCALIDAD, (bkd.CANTIDAD*bkd.COSTO) AS COSTO,
									(CASE WHEN art.tipoarticulo != 0 THEN
										bkd.CANTIDAD END) AS KILOGRAMOS,
									(CASE WHEN art.tipoarticulo  = 0 THEN
										bkd.CANTIDAD END) AS UNIDADES
								FROM BDES.dbo.BIKARDEX_DET AS bkd
								INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = bkd.MATERIAL
								WHERE bkd.MATERIAL = ".(($idpara=='')?'bkd.MATERIAL':$idpara)."
									AND	bkd.LOCALIDAD_ORIG = $cedim AND bkd.TIPO = 17
									AND CAST(bkd.FECHA AS DATE) BETWEEN '$fecdesi' AND '$fecdesf'
							) AS KARDEX
						) datos
						INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = datos.localidad
						GROUP BY ti.nombre, datos.localidad
						ORDER BY ti.nombre";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$i = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$idpara = $idpara=='' ? 0 : $idpara;
					$txt = '<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">'.
							'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
							'onclick="limpiarfila('."'tblPedDesPer', ".$i.', $(this).prop('."'checked'".') )" id="c'.$i.'">'.
							'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c'.$i.'"></label>'.
							'<button type="button"
								onclick="detPedDes('."'".
									$row['localidad']."', $idpara, '".ucwords(strtolower($row['tienda']))."', '".
									$fecpedi."', '".$fecpedf."', '".$fecdesi."', '".$fecdesf."')".'"'.
								'class="btn btn-link m-0 p-0 text-left font-weight-bold" '.
								'style="white-space: normal; line-height: 1;">' .
								ucwords(strtolower($row['tienda'])) .
							'</button>' .
						'</div>';
					$totped = $row['KG_PED']+$row['UNDPED'];
					$totdes = $row['KG_DES']+$row['UNDDES'];
					$diftot = $totdes - $totped;
					$pordif = $totped>0?($diftot * 100) / $totped:0;
					$datos[] = [
						'id_loc' => $row['localidad'],
						'tienda' => $txt,
						'kg_ped' => $row['KG_PED']*1,
						'undped' => $row['UNDPED']*1,
						'totped' => $totped,
						'kg_des' => $row['KG_DES']*1,
						'unddes' => $row['UNDDES']*1,
						'totdes' => $totdes,
						'cosdes' => $row['COSTO'] *1,
						'diftot' => $diftot,
						'pordif' => $pordif,
					];
					$i++;
				}
				echo json_encode($datos);
				break;
			case 'detPedDes':
				$sql = "SELECT localidad, ti.nombre AS tienda, idarticulo, articulo,
							COALESCE ( SUM ( KG_PED ), 0 ) AS KG_PED,
							COALESCE ( SUM ( UNDPED ), 0 ) AS UNDPED,
							COALESCE ( SUM ( KG_DES ), 0 ) AS KG_DES,
							COALESCE ( SUM ( UNDDES ), 0 ) AS UNDDES,
							COALESCE ( SUM ( COSTO ), 0 ) AS COSTO
						FROM
							(SELECT localidad, 0 AS COSTO, KILOGRAMOS AS KG_PED, UNIDADES AS UNDPED,
								0 AS KG_DES, 0 AS UNDDES, idarticulo, articulo
							FROM
								(SELECT cab.localidad, art.codigo AS idarticulo, art.descripcion AS articulo,
									( CASE WHEN art.tipoarticulo != 0 THEN cab.solipedidet_pedido END ) AS KILOGRAMOS,
									( CASE WHEN art.tipoarticulo  = 0 THEN cab.solipedidet_pedido END ) AS UNIDADES
								FROM
									BDES.dbo.vw_soli_pedi_det AS cab
									INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = cab.solipedidet_codigo
								WHERE cab.localidad = $id_loc
									AND cab.centro_dist = $cedim
									AND cab.solipedi_status = 7
									AND CAST(cab.solipedi_fechasoli AS DATE) BETWEEN '$fecpei' AND '$fecpef') AS PEDIDOS
							UNION ALL
							SELECT LOCALIDAD, COSTO, 0 AS KG_PED, 0 AS UNDPED, KILOGRAMOS AS KG_DES,
								UNIDADES AS UNDDES, idarticulo, articulo
								FROM
									(SELECT bkd.LOCALIDAD, art.codigo AS idarticulo, art.descripcion AS articulo,
										(bkd.CANTIDAD*bkd.COSTO) AS COSTO,
										(CASE WHEN art.tipoarticulo != 0 THEN bkd.CANTIDAD END) AS KILOGRAMOS,
										(CASE WHEN art.tipoarticulo  = 0 THEN bkd.CANTIDAD END) AS UNIDADES
									FROM
										BDES.dbo.BIKARDEX_DET AS bkd
										INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = bkd.MATERIAL
									WHERE bkd.LOCALIDAD = $id_loc
										AND bkd.LOCALIDAD_ORIG = $cedim
										AND bkd.TIPO = 17
										AND CAST(bkd.FECHA AS DATE) BETWEEN '$fecdei' AND '$fecdef') AS KARDEX
							) datos
						INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = datos.localidad
						GROUP BY ti.nombre, datos.localidad, datos.idarticulo, datos.articulo
						ORDER BY datos.articulo";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$i = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$totped = $row['KG_PED']+$row['UNDPED'];
					$totdes = $row['KG_DES']+$row['UNDDES'];
					$diftot = $totdes - $totped;
					$pordif = $totped>0?($diftot * 100) / $totped:0;
					$datos[] = [
						'id_art' => $row['idarticulo'],
						'nomart' => $row['articulo'],
						'kg_ped' => $row['KG_PED']*1,
						'undped' => $row['UNDPED']*1,
						'totped' => $totped,
						'kg_des' => $row['KG_DES']*1,
						'unddes' => $row['UNDDES']*1,
						'totdes' => $totdes,
						'cosdes' => $row['COSTO'] *1,
						'diftot' => $diftot,
						'pordif' => $pordif,
					];
					$i++;
				}
				echo json_encode($datos);
				break;
			case 'mostrarODC':
				// Se prepara la consulta a la base de datos
				$sql = "SELECT
							cab.id, cab.fecha_crea, cab.fecha_vence, cab.usuario_crea,
							ti.nombre AS cedim, prov.codigo AS codprov,
							prov.descripcion AS nomprov,
							det.codigo, art.descripcion, det.cantidad,
							( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
								WHERE b.escodigo = det.codigo
								AND b.codigoedi = 1) AS barra
						FROM
							BDES.dbo.DBODC_cab AS cab
							INNER JOIN BDES.dbo.DBODC_det AS det ON det.id_cab = cab.id AND det.status = 0
							INNER JOIN BDES.dbo.ESProveedores AS prov ON prov.codigo = cab.proveedor
							INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = cab.centro_dist
							INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.codigo
						WHERE cab.status = 0 AND cab.id = $idpara";
				$sql = $connec->query($sql);
				// Se prepara el array para los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[]=[
						'codigo'      => $row['codigo'],
						'barra'       => $row['barra'],
						'descripcion' => $row['descripcion'],
						'unidad'      => $row['cantidad'],
						'cajas'       => $row['cantidad'],
						'empaque'     => $row['cantidad'],
					];
				}
				echo json_encode($datos);
				break;
			default:
				# code...
				break;
		}
		// Se cierra la conexion
		$connec = null;
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>