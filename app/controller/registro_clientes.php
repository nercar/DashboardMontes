<?php
	// conexion a bbdd principal
	try {
		date_default_timezone_set('America/Bogota');
		// Se capturan las opciones por Post
		$opcion = (isset($_POST["opcion"])) ? $_POST["opcion"] : "";
		// id para los filtros en las consultas
		$idpara = (isset($_POST["idpara"])) ? $_POST["idpara"] : '';
		// connect to the sql server database
		$connec = new \PDO("sqlsrv:Server=192.168.50.242,1433", 'sa', '');
		$srvvin = '[192.168.50.9].';
		$srvvinope = '[192.168.56.5].';
		// $connec = new \PDO("sqlsrv:Server=localhost,1433", 'sa', '');
		// $srvvin = '';
		// $srvvinope = '';

		$datos = [];
		switch ($opcion) {
			case 'valoresIniciales':
				// Tipos de ID Fiscales
				$sql = "SELECT codigo, LTRIM(RTRIM(significado)) AS significado,
							LTRIM(RTRIM(abreviatura)) AS abreviatura
						FROM FAC_ELE.dbo.DBIDFiscal
						ORDER BY prioridad, significado";

				$sql = $connec->query($sql);
				$idfiscal = $sql->fetchAll(\PDO::FETCH_ASSOC);

				// Tipos de personas
				$sql = "SELECT codigo, LTRIM(RTRIM(tipo)) AS tipo
						FROM FAC_ELE.dbo.DBTipoPersona";

				$sql = $connec->query($sql);
				$tipopersona = $sql->fetchAll(\PDO::FETCH_ASSOC);

				// Tipos de Grupos de Clientes
				$sql = "SELECT ID, LTRIM(RTRIM(GRUPO)) AS GRUPO
						FROM FAC_ELE.dbo.ESGruposFichas
						WHERE UPPER(tipo) = 'CLI'";

				$sql = $connec->query($sql);
				$grpCltes = $sql->fetchAll(\PDO::FETCH_ASSOC);

				// Tipos de Tributos Receptor
				$sql = "SELECT LTRIM(RTRIM(id)) AS id, LTRIM(RTRIM(nombre)) AS nombre,
							LTRIM(RTRIM(descripcion)) AS descripcion
						FROM FAC_ELE.dbo.DBTributoReceptor
						ORDER BY LTRIM(RTRIM(id))";

				$sql = $connec->query($sql);
				$tributoreceptor = $sql->fetchAll(\PDO::FETCH_ASSOC);

				// Tipos de Obligaciones Fiscales
				$sql = "SELECT LTRIM(RTRIM(codigo)) AS codigo,
							LTRIM(RTRIM(significado)) AS significado
						FROM FAC_ELE.dbo.DBObligFiscal";

				$sql = $connec->query($sql);
				$obligfiscal = $sql->fetchAll(\PDO::FETCH_ASSOC);

				// Tipos de Regimen
				$sql = "SELECT codigo, LTRIM(RTRIM(significado)) AS significado
						FROM FAC_ELE.dbo.DBTipoRegimen";

				$sql = $connec->query($sql);
				$tiporegimen = $sql->fetchAll(\PDO::FETCH_ASSOC);

				// Lista de Paises
				$sql = "SELECT codigo, LTRIM(RTRIM(nombre)) AS nombre, LTRIM(RTRIM(alfa2)) AS alfa2
						FROM FAC_ELE.dbo.DBPaises
						ORDER BY prioridad, LTRIM(RTRIM(nombre))";

				$sql = $connec->query($sql);
				$dbPaises = $sql->fetchAll(\PDO::FETCH_ASSOC);

				// Lista de Departamentos Colombia
				$sql = "SELECT codigo, LTRIM(RTRIM(nombre)) AS nombre, LTRIM(RTRIM(codigo_iso)) AS codigo_iso
						FROM FAC_ELE.dbo.DBDepartamentos
						ORDER BY LTRIM(RTRIM(nombre)) ASC";

				$sql = $connec->query($sql);
				$dbDepartamentos = $sql->fetchAll(\PDO::FETCH_ASSOC);
				
				$datos = [
					'idfiscal'        => $idfiscal,
					'tipopersona'     => $tipopersona,
					'grpCltes'        => $grpCltes,
					'tributoreceptor' => $tributoreceptor,
					'obligfiscal'     => $obligfiscal,
					'tiporegimen'     => $tiporegimen,
					'dbPaises'        => $dbPaises,	
					'dbDepartamentos' => $dbDepartamentos,
				];

				echo json_encode($datos);
				break;
			case 'lstClientesFacEle':
				$sql = "SELECT codigo, rif, LTRIM(RTRIM(rsocial)) AS rsocial, telefono, email, activo
						FROM FAC_ELE.dbo.ESClientes
						WHERE codigo > 0 AND
						(rif LIKE '%$idpara%' OR rsocial LIKE '%$idpara%' OR email LIKE '%$idpara%')";

				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'   => $row['codigo'],
						'rif'      => $row['rif'],
						'rsocial'  => $row['rsocial'],
						'telefono' => $row['telefono'],
						'email'    => $row['email'],
						'opciones' =>
							'<div class="btn-group-sm text-center">'.
								'<button type="button" onclick="bloquearCliente('.$row['codigo'].')" '.
								(($row['activo']==1) ?
									'class="btn btn-success btn-sm p-0" style="width: 30px" title="Bloquear Cliente">'.
									'<i class="fas fa-lock"></i>':
									'class="btn btn-danger btn-sm p-0" style="width: 30px" title="Activar Cliente">'.
									'<i class="fas fa-lock-open"></i>').
								'</button>'.
								'&nbsp;&nbsp;'.
								'<button type="button" onclick="editarCliente('.$row['codigo'].')"'.
									'class="btn btn-primary btn-sm p-0" style="width: 30px" title="Editar Cliente">'.
									'<i class="fas fa-user-edit"></i>'.
								'</button>'.
							'</div>'
					];
				}

				echo json_encode($datos);
				break;
				
			case 'dbDepartamentos':
				$sql = "SELECT codigo, LTRIM(RTRIM(nombre)) AS nombre, LTRIM(RTRIM(codigo_iso)) AS codigo_iso
						FROM FAC_ELE.dbo.DBDepartamentos
						ORDER BY LTRIM(RTRIM(nombre)) ASC";

				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}

				echo json_encode($datos);
				break;

			case 'dbMunicipios':
				$sql = "SELECT LTRIM(RTRIM(codigo_mpio)) AS codigo_mpio, LTRIM(RTRIM(nombre)) AS nombre, id
						FROM FAC_ELE.dbo.DBMunicipios
						WHERE codigo_dpto = $idpara
						ORDER BY LTRIM(RTRIM(nombre)) ASC";

				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}

				echo json_encode($datos);
				break;

			case 'dbCodigosPostales':
				extract($_POST);
				$sql = "SELECT LTRIM(RTRIM(codigo_postal)) AS codigo_postal,
							LTRIM(RTRIM(tipo)) AS tipo
						FROM FAC_ELE.dbo.DBCodigosPostales
						WHERE codigo_dpto = $cddpto AND codigo_mpio = '$cdmpio'
						ORDER BY codigo_postal ASC";

				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}

				echo json_encode($datos);
				break;

			case 'buscarCliente':
				$sql = "SELECT codigo, descripcion, rif
						FROM FAC_ELE.dbo.ESClientes
						WHERE rif LIKE '%$idpara%' OR descripcion LIKE '%$idpara%'";

				$sql = $connec->query($sql);
				$cnt = $sql->rowCount();

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo' => $row['codigo'],
						'nombre' => $cnt==1 ? $row['descripcion'] :
									'<button type="button" title="Seleccionar" onclick="' .
									"$('#BuscarCliente').modal('hide'); consultarClte('" . $row['codigo'] . "')" .
									'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
									'style="white-space: normal; line-height: 1;">' . $row['descripcion'] .
									'</button>',
						'rif'    => $row['rif'],
					];
				}

				echo json_encode($datos);
				break;

			case 'consultarClte':
				$sql = "SELECT
							codigo, LTRIM(RTRIM(descripcion)) AS descripcion, LTRIM(RTRIM(rsocial)) AS rsocial,
							COALESCE(nifsinindentificador, REPLACE(rif, '-','')) AS rif,
							LTRIM(RTRIM(direccion)) AS direccion, LTRIM(RTRIM(telefono)) AS telefono,
							LTRIM(RTRIM(contacto)) AS contacto, LTRIM(RTRIM(observacion)) AS observacion,
							LTRIM(RTRIM(codigoespecial)) AS codigoespecial, LTRIM(RTRIM(codigoproveedor)) AS codigoproveedor,
							grupo, pais, estado, ciudad, LTRIM(RTRIM(email)) AS email, activo, precio,
							(CASE WHEN YEAR(fechanac) = 1900 THEN NULL ELSE fechanac END) AS fechanac,
							diascredito, CAST(limite AS int) AS limite, validorif, tipoc,
							COALESCE(LTRIM(RTRIM(pnombre)), '') AS pnombre,
							COALESCE(LTRIM(RTRIM(snombre)), '') AS snombre,
							COALESCE(LTRIM(RTRIM(papellido)), '') AS papellido,
							COALESCE(LTRIM(RTRIM(sapellido)), '') AS sapellido,
							nifsinindentificador, digitoverificacion,
							LTRIM(RTRIM(telefonomovil)) AS telefonomovil,
							COALESCE(LTRIM(RTRIM(codigopostal)), '') AS codigopostal,
							LTRIM(RTRIM(emailcontacto)) AS emailcontacto, COALESCE(tipopersona, 1) AS tipopersona,
							identificadorfiscal, obligacionesfiscales, tiporegimen, COALESCE(tributoreceptor, '') AS tributoreceptor,
							LTRIM(RTRIM(telefonocontacto)) AS telefonocontacto, COALESCE(LTRIM(RTRIM(municipio)), '') AS municipio,
							LTRIM(RTRIM(otro_departamento)) AS otro_departamento, LTRIM(RTRIM(otro_ciudad)) AS otro_ciudad,
							LTRIM(RTRIM(solo_idclte)) AS solo_idclte
						FROM FAC_ELE.dbo.ESClientes
						WHERE codigo = '$idpara'";

				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}

				echo json_encode($datos);
				break;

			case 'guardarClte':
				$result = [
					'status'  => 0,
					'mensaje' => 'Hubo un error, no se pudo guardar la información',
					'query'   => '',
				];
				extract($_POST);

				if($tipopersona==2) {
					$nombrecompleto = $primernombre   . ( ($segundonombre  !='') ? ' '.$segundonombre   : '' ) . ' ';
					$nombrecompleto.= $primerapellido . ( ($segundoapellido!='') ? ' '.$segundoapellido : '' );
				} else {
					$nombrecompleto = $rsocial;
				}

				$rifcompleto = $txtidfiscal . $idclte . $codverif;

				$codverif = $codverif == '' ? 'null' : $codverif;

				$sql = "DECLARE @codclte INT
						UPDATE ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESCorrelativos
						SET ValorCorrelativo = (ValorCorrelativo + 1)
						WHERE Correlativo = 'Cliente';

						SELECT @codclte = ValorCorrelativo
						FROM ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESCorrelativos
						WHERE Correlativo = 'Cliente';

						INSERT INTO ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESClientes(
							codigo, descripcion, rsocial, rif, nit, direccion, telefono, contacto, observacion,
							codigoespecial, codigoproveedor, grupo, pais, estado, ciudad, email, activo, precio,
							fechanac, diascredito, limite, validorif, tipoc)
						VALUES (
							@codclte, '$nombrecompleto', '$nombrecompleto', '$rifcompleto', '$rifcompleto', '$direccion', '$telclte',
							'$nomcontacto', '$observacion', 54, 169, $grpCltes, $pais, $dptos, $ciudad, '$emailclte', $activo, 3,
							CAST('$fechanac' AS DATE), $diascredito, $limite, 1, $otrosclte);

						INSERT INTO FAC_ELE.dbo.ESClientes(
							codigo, descripcion, rsocial, rif, nit, direccion, telefono, contacto, observacion,
							codigoespecial, codigoproveedor, grupo, pais, estado, ciudad, email, activo, precio,
							fechanac, diascredito, limite, validorif, tipoc, pnombre, snombre, papellido, sapellido,
							nifsinindentificador, digitoverificacion, telefonomovil, codigopostal, emailcontacto,
							tipopersona, identificadorfiscal, obligacionesfiscales, tiporegimen, tributoreceptor,
							telefonocontacto, municipio, otro_departamento, otro_ciudad, solo_idclte)
						VALUES (
							@codclte, '$nombrecompleto', '$nombrecompleto', '$rifcompleto', '$rifcompleto', '$direccion', '$telclte',
							'$nomcontacto', '$observacion', 54, 169, $grpCltes, $pais, $dptos, $ciudad, '$emailclte', $activo, 3,
							CAST('$fechanac' AS DATE), $diascredito, $limite, 1, $otrosclte,
							'$primernombre', '$segundonombre', '$primerapellido', '$segundoapellido',
							'$txtidfiscal$idclte', $codverif, '$celclte', '$postales', '$emailcontacto',
							$tipopersona, $idfiscal, '$obligacion', $tiporegimen, '$tributo', '$telcontacto', '$municipio',
							'$otro_dpto', '$otro_ciudad', $idclte);";

				$result['query'] = substr($sql, 0, 40);
				$sql = $connec->query($sql);
				if(!$sql || $sql->rowCount()==0) {
					$result['query'] = '<em>'.$result['query'].'<br>'.
						substr($connec->errorInfo()[2], strrpos($connec->errorInfo()[2], ']')+1).
						'</em>';
					echo json_encode($result);
					break;
				} else {
					$result['status'] = 1;
					$result['mensaje'] = 'Información del cliente<br>[ '.$rifcompleto.' - ' .$nombrecompleto. ' ]<br>guardada correctamente';
					$result['query'] = '';
				}

				echo json_encode($result);
				break;

			case 'actualizaClte':
				$result = [
					'status'  => 0,
					'mensaje' => 'Hubo un error, no se pudo guardar la información',
					'query'   => '',
				];
				extract($_POST);

				if($tipopersona==2) {
					$nombrecompleto = $primernombre   . ( ($segundonombre  !='') ? ' '.$segundonombre   : '' ) . ' ';
					$nombrecompleto.= $primerapellido . ( ($segundoapellido!='') ? ' '.$segundoapellido : '' );
				} else {
					$nombrecompleto = $rsocial;
				}

				$rifcompleto = $txtidfiscal . $idclte . $codverif;

				$codverif = $codverif == '' ? 'null' : $codverif;

				$sql = "UPDATE ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESClientes SET
							rif = '$rifcompleto', nit = '$rifcompleto',
							descripcion = '$nombrecompleto', rsocial = '$nombrecompleto',
							direccion = '$direccion', telefono = '$telclte', contacto = '$nomcontacto',
							observacion = '$observacion', grupo = $grpCltes, pais = $pais, estado = $dptos,
							ciudad = $ciudad, email = '$emailclte', activo = $activo,
							fechanac = CAST('$fechanac' AS DATE), diascredito = $diascredito, limite = $limite,
							tipoc = $otrosclte
						WHERE codigo = $codigoclte;

						UPDATE FAC_ELE.dbo.ESClientes SET
							rif = '$rifcompleto', nit = '$rifcompleto',
							descripcion = '$nombrecompleto', rsocial = '$nombrecompleto',
							direccion = '$direccion', telefono = '$telclte', contacto = '$nomcontacto',
							observacion = '$observacion', grupo = $grpCltes, pais = $pais, estado = $dptos,
							ciudad = $ciudad, email = '$emailclte', activo = $activo,
							fechanac = CAST('$fechanac' AS DATE), diascredito = $diascredito, limite = $limite,
							tipoc = $otrosclte,
							pnombre = '$primernombre', snombre = '$segundonombre', papellido = '$primerapellido',
							sapellido = '$segundoapellido', nifsinindentificador = '$txtidfiscal$idclte',
							digitoverificacion = $codverif, telefonomovil = '$celclte',
							codigopostal = '$postales', emailcontacto = '$emailcontacto',
							tipopersona = $tipopersona, identificadorfiscal = $idfiscal,
							obligacionesfiscales = '$obligacion', tiporegimen = $tiporegimen,
							tributoreceptor = '$tributo', telefonocontacto = '$telcontacto',
							municipio = '$municipio', otro_departamento = '$otro_dpto',
							otro_ciudad = '$otro_ciudad', solo_idclte = $idclte
						WHERE codigo = $codigoclte";

				$result['query'] = substr($sql, 0, 40);
				$sql = $connec->query($sql);
				if(!$sql || $sql->rowCount()==0) {
					$result['query'] = '<em>'.$result['query'].'<br>'.
						substr($connec->errorInfo()[2], strrpos($connec->errorInfo()[2], ']')+1).
						'</em>';
					echo json_encode($result);
					break;
				} else {
					$result['status'] = 1;
					$result['mensaje'] = 'Información del cliente<br>[ '.$rifcompleto.' - ' .$nombrecompleto. ' ]<br>actualizada correctamente';
					$result['query'] = '';
				}

				echo json_encode($result);
				break;

			case 'bloquearCliente':
				$result = [
					'status'  => 0,
					'mensaje' => 'Hubo un error, no se pudo guardar la información',
					'query'   => '',
				];
				$sql = "UPDATE ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESClientes SET
							activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END
						WHERE codigo = $idpara;

						UPDATE FAC_ELE.dbo.ESClientes SET
							activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END
						WHERE codigo = $idpara";

				$sql = $connec->query($sql);
				if($sql) {
					$result['status'] = 1;
					$result['mensaje'] = '';
					$result['query'] = '';
				} else {
					$result['query'] = '<em>'.$result['query'].'<br>'.
						substr($connec->errorInfo()[2], strrpos($connec->errorInfo()[2], ']')+1).
						'</em>';
				}

				echo json_encode($result);
				break;

			case 'biventasNoSync':
				$sql = "SELECT cli.codigo AS CODIGO, LTRIM(RTRIM(cli.rif)) AS RIF,
							(CASE WHEN cli.tipopersona IS NULL OR cli.tipopersona = 1 THEN LTRIM(RTRIM(cli.rsocial))
								ELSE ( LTRIM(RTRIM(cli.pnombre)) +
									(CASE WHEN LTRIM(RTRIM(cli.snombre)) != '' THEN ' ' + LTRIM(RTRIM(cli.snombre)) ELSE '' END) +
									LTRIM(RTRIM(cli.papellido)) +
									(CASE WHEN LTRIM(RTRIM(cli.sapellido)) != '' THEN ' ' + LTRIM(RTRIM(cli.sapellido)) ELSE '' END))
							END) AS NOMBRE, nos.ORGANIZACION, LTRIM(RTRIM(org.Organizacion)) AS NOMORGANIZACION,
							nos.LOCALIDAD, LTRIM(RTRIM(suc.Nombre)) AS TIENDA, nos.DOCUMENTO, nos.TIPO, nos.FECHA,
							(CASE WHEN nos.ORGANIZACION = 0
								THEN
									(SELECT COALESCE(SUM(TOTAL), 0) FROM ".(($srvvin!='')?$srvvin:'')."BDES.dbo.BIVentas
									WHERE ORGANIZACION = nos.ORGANIZACION AND TIPO IN(7, 8) AND ELIMINADO = 0 AND ESTADO = 1
									AND DOCUMENTO = nos.DOCUMENTO AND CODIGO = nos.CODCLTE)
								ELSE
									(SELECT COALESCE(SUM(TOTAL), 0) FROM ".(($srvvinope!='')?$srvvinope:'')."BDES.dbo.BIVentas
									WHERE ORGANIZACION = nos.ORGANIZACION AND TIPO IN(7, 8) AND ELIMINADO = 0 AND ESTADO = 1
									AND DOCUMENTO = nos.DOCUMENTO AND CODIGO = nos.CODCLTE)
							END) AS TOTAL
						FROM
							FAC_ELE.dbo.DBNoSync_BIVentas AS nos
							INNER JOIN FAC_ELE.dbo.ESClientes AS cli ON cli.codigo = nos.CODCLTE
							INNER JOIN ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESOrganizacion_SF AS org ON org.codigo = nos.ORGANIZACION
							INNER JOIN ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESSucursales AS suc ON suc.codigo = nos.LOCALIDAD
						ORDER BY nos.CODCLTE, nos.DOCUMENTO, nos.FECHA";

				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'CODIGO'          => $row['CODIGO'],
						'DOCUMENTO'       => $row['DOCUMENTO'],
						'FECHA'           => date('d-m-Y', strtotime($row['FECHA'])),
						'LOCALIDAD'       => $row['LOCALIDAD'],
						'NOMBRE'          => '<div class="m-0">
												<button type="button"
													onclick="$('."'#codclteindex').val(".$row['CODIGO'].'); cargarcontenido('."'registro_clientes'".')"
													class="btn btn-link p-0" style="width: 30px" title="Editar Cliente">
													'.$row['NOMBRE'].'
												</button>
											</div>',
						'NOMORGANIZACION' => $row['NOMORGANIZACION'],
						'ORGANIZACION'    => $row['ORGANIZACION'],
						'RIF'             => $row['RIF'],
						'TIENDA'          => $row['TIENDA'],
						'TIPO'            => $row['TIPO']=='7'?'FACT':'NC',
						'TOTAL'           => number_format($row['TOTAL'], 2),
					];
				}

				echo json_encode($datos);
				break;
		}

		$connec = null;

	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>