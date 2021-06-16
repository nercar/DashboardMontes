<?php
	// conexion a bbdd principal
	try {
		date_default_timezone_set('America/Bogota');
		// Se capturan las opciones por Post
		extract($_POST);
		// connect to the sql server database
		$connec = new \PDO("sqlsrv:Server=192.168.50.242,1433", 'sa', '');
		$srvvin = '[192.168.50.9].';
		$srvvinope = '[192.168.56.5].';
		// $connec = new \PDO("sqlsrv:Server=localhost,1433", 'sa', '');
		// $srvvin = '';
		// $srvvinope = '';
		$datos = [];
		switch ($opcion) {
			case 'biventasNoSync':
				$sql = "SELECT cli.codigo AS CODIGO, LTRIM(RTRIM(cli.rif)) AS RIF,
							(CASE WHEN cli.tipopersona IS NULL OR cli.tipopersona = 1 THEN LTRIM(RTRIM(cli.rsocial))
								ELSE ( LTRIM(RTRIM(cli.pnombre)) +
									(CASE WHEN LTRIM(RTRIM(cli.snombre)) != '' THEN ' ' + LTRIM(RTRIM(cli.snombre)) ELSE '' END) +
									' ' + LTRIM(RTRIM(cli.papellido)) +
									(CASE WHEN LTRIM(RTRIM(cli.sapellido)) != '' THEN ' ' + LTRIM(RTRIM(cli.sapellido)) ELSE '' END))
							END) AS NOMBRE, nos.ORGANIZACION, LTRIM(RTRIM(org.Organizacion)) AS NOMORGANIZACION,
							nos.LOCALIDAD, LTRIM(RTRIM(suc.Nombre)) AS TIENDA, nos.DOCUMENTO, nos.TIPO, nos.FECHA, nos.CAJA,
							(CASE WHEN nos.ORGANIZACION = 0
								THEN
									(SELECT COALESCE(SUM(TOTAL), 0) FROM ".(($srvvin!='')?$srvvin:'')."BDES.dbo.BIVentas
									WHERE ORGANIZACION = nos.ORGANIZACION AND TIPO IN(7, 8) AND ELIMINADO = 0 AND ESTADO = 1
									AND DOCUMENTO = nos.DOCUMENTO AND CODIGO = nos.CODCLTE)
								ELSE
									(SELECT COALESCE(SUM(TOTAL), 0) FROM ".(($srvvinope!='')?$srvvinope:'')."BDES.dbo.BIVentas
									WHERE ORGANIZACION = nos.ORGANIZACION AND TIPO IN(7, 8) AND ELIMINADO = 0 AND ESTADO = 1
									AND DOCUMENTO = nos.DOCUMENTO AND CODIGO = nos.CODCLTE)
							END) AS TOTAL, nos.OBSERVACIONES, nos.DOC_FAC_ELE, nos.ID_CAB
						FROM
							FAC_ELE.dbo.DBNoSync_BIVentas AS nos
							INNER JOIN FAC_ELE.dbo.ESClientes AS cli ON cli.codigo = nos.CODCLTE
							INNER JOIN ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESOrganizacion_SF AS org ON org.codigo = nos.ORGANIZACION
							INNER JOIN ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESSucursales AS suc ON suc.codigo = nos.LOCALIDAD
						ORDER BY nos.FECHA, nos.CODCLTE, nos.DOCUMENTO";
				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'CODIGO'          => $row['CODIGO'],
						'DOCUMENTO'       => $row['DOCUMENTO'],
						'FECHA'           => date('d-m-Y', strtotime($row['FECHA'])),
						'LOCALIDAD'       => $row['LOCALIDAD'],
						'NOMBRE'          => '<button type="button"
												onclick="$(\'#codclteindex\').val('.$row['CODIGO'].');
													$(\'#origenui\').val(\'dtmodalclte\');
													$(\'#dtmodalclte\').modal(\'show\');
													$(\'#dtmodalclte\').load(\'app/db_registro_clientes.html?t=00:00:00\');"
													class="btn btn-sm btn-outline-primary p-0 pl-1 pr-1" title="Editar Cliente">
												'.$row['NOMBRE'].'
											</button>
											',
						'NOMORGANIZACION' => $row['NOMORGANIZACION'],
						'ORGANIZACION'    => $row['ORGANIZACION'],
						'RIF'             => $row['RIF'],
						'TIENDA'          => $row['TIENDA'],
						'TIPO'            => $row['TIPO']=='7'?'FACT':'NC',
						'TOTAL'           => number_format($row['TOTAL'], 2),
						'CAJA'            => $row['CAJA'],
						'OBSERVACIONES'   => $row['OBSERVACIONES'],
						'DOC_FAC_ELE'     => $row['DOC_FAC_ELE'],
						'ID_CAB'		  => $row['ID_CAB'],
					];
				}
				echo json_encode($datos);
				break;
			case 'encontrarRifClte':
				$sql = "SELECT TOP 1 codigo, rif, rsocial FROM FAC_ELE.dbo.ESClientes WHERE solo_idclte = FAC_ELE.dbo.fn_SoloNumeros('$rifclte')";
				$sql = $connec->query($sql);
				if(!$sql || $sql->rowCount()==0) {
					$datos = [0, 'Error: No existe un cliente con ese RIF'];
				} else {
					$datos = $sql->fetch(\PDO::FETCH_ASSOC);
				}
				echo json_encode($datos);
				break;
			case 'cambiarRifClte':
				$sql = "UPDATE FAC_ELE.dbo.DBNoSync_BIVentas SET CODCLTE = $codclte WHERE ID_CAB = $id_cab;
						UPDATE ".(($srvvin!='')?$srvvin:'')."BDES_POS.dbo.BIVENTAS_INVC SET IDCLIENTE = '$idclte' WHERE ID = $id_cab;";
				$sql = $connec->query($sql);
				if(!$sql || $sql->rowCount()==0) {
					$datos = [0, 'Error: No se pudo actualizar el Cliente<br>' . $connec->errorInfo(2)];
				} else {

					$datos = [1, 'Modificación realizada satisfactoriamente'];
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