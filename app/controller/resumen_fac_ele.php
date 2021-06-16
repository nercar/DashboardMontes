<?php
	// conexion a bbdd principal
	try {
		date_default_timezone_set('America/Bogota');
		
		$connec = new \PDO("sqlsrv:Server=192.168.50.242,1433", 'sa', '');
		$srvvin = '[192.168.50.9].';
		// $connec = new \PDO("sqlsrv:Server=localhost,1433", 'sa', '');
		// $srvvin = '';
		
		extract($_POST);
		$datos = [];
		switch ($opcion) {
			case 'listaClientesfe':
				$sql = "SELECT codigo, rif,
							(CASE WHEN tipopersona = '2' THEN
								pnombre+(CASE WHEN COALESCE(snombre, '') = '' THEN ', ' ELSE ' '+snombre + ', ' END)+
								papellido+(CASE WHEN COALESCE(sapellido, '') = '' THEN '' ELSE ' '+sapellido END)
							ELSE rsocial
							END) AS nombre
						FROM FAC_ELE.dbo.ESClientes
						WHERE rif LIKE '%$idpara%' OR
							(CASE WHEN tipopersona = '2' THEN
								UPPER(pnombre+COALESCE(snombre, '')+papellido+COALESCE(sapellido, ''))
							ELSE UPPER(rsocial) END) LIKE '%$idpara%'";

				$sql = $connec->query($sql);
				$cnt = $sql->rowCount();

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo' => $row['codigo'],
						'nombre' => $cnt==1 ? $row['nombre'] :
									'<button type="button" title="Seleccionar"
										onclick="seleccion('.$row['codigo'].', '."'".$row['nombre']."')".'"
										class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold"
										style="white-space: normal; line-height: 1;">' . $row['nombre'] .
									'</button>',
						'snombre'=> $row['nombre'],
						'rif'    => $row['rif'],
					];
				}

				echo json_encode($datos);
				break;

			case 'consultarFacturas':
				if($cdclte=='') {
					$codcliente = '1 = 1';
				} else {
					$codcliente = 'cli.codigo = ' . $cdclte;
				}
				$sql = "SELECT linea, cnt_docs, id_fac_ele, existcli, codigo, fecha, llavecomprobante, documento_origen,
							rifcliente, COALESCE(nomcliente, '*** CLIENTE NO EXISTE ***') AS nomcliente, folio, moneda,
							SUM(total) AS total, status, tipo
						FROM
							(SELECT ROW_NUMBER() OVER(ORDER BY biv.ORGANIZACION, biv.TIPO, biv.DOCUMENTO) AS linea,
								COALESCE(cli.codigo, biv.CODIGO) AS codigo, biv.TIPO AS tipo, COALESCE(cli.codigo, 0) AS existcli,
								(SELECT COUNT(CAST(ORGANIZACION AS VARCHAR)+CAST(TIPO AS VARCHAR)+CAST(DOCUMENTO AS VARCHAR))
									FROM FAC_ELE.dbo.BIVentas
									WHERE ORGANIZACION = biv.ORGANIZACION AND TIPO = biv.TIPO AND DOCUMENTO = biv.DOCUMENTO) AS cnt_docs,
								(CASE WHEN biv.ORGANIZACION = 0
									THEN (CASE WHEN biv.TIPO = '7' THEN 'FECM-' WHEN biv.TIPO = '8' THEN 'NCECM-' END)
									ELSE (CASE WHEN biv.TIPO = '7' THEN 'FEOU-' WHEN biv.TIPO = '8' THEN 'NCEOU-' END)
								END) + CAST(biv.DOCUMENTO AS VARCHAR) AS llavecomprobante,
								biv.DOCUMENTO_SERIE AS documento_origen, cli.rif AS rifcliente,
								(CASE WHEN cli.tipopersona = '1' THEN cli.rsocial
								ELSE
									CASE WHEN 
										(cli.pnombre + (CASE WHEN LTRIM(RTRIM(COALESCE(cli.snombre, ''))) = '' THEN ' '
										ELSE ' ' + cli.snombre + ' '  END) +
										cli.papellido +	(CASE WHEN COALESCE(cli.sapellido, '') = '' THEN ''
										ELSE ' ' + cli.sapellido END)) = '' THEN cli.rsocial
									ELSE
										cli.pnombre + (CASE WHEN LTRIM(RTRIM(COALESCE(cli.snombre, ''))) = '' THEN ' '
										ELSE ' ' + cli.snombre + ' '  END) +
										cli.papellido +	(CASE WHEN COALESCE(cli.sapellido, '') = '' THEN ''
										ELSE ' ' + cli.sapellido END)
									END
								END) AS nomcliente, biv.DOCUMENTO AS folio, biv.FECHA AS fecha,
								(CASE WHEN biv.exp = 0 THEN 'COP' ELSE 'USD' END) AS moneda,
								(CAST(ROUND((biv.SUBTOTAL + biv.IMPUESTO - biv.DESCUENTO) /
									(CASE WHEN biv.exp = 0 THEN 1
									ELSE
										(CASE WHEN biv.TIPO = 8 THEN
											(SELECT TASAC FROM FAC_ELE.dbo.BIVentas T
											WHERE T.TIPO = 7 AND T.ESTADO = 1 AND T.ELIMINADO = 0
											AND T.DOCUMENTO =	(SELECT TOP 1 D.DOCUMENTO_REL FROM FAC_ELE.dbo.BIVentasDet D WHERE D.TIPO = 8 AND D.DOCUMENTO = biv.DOCUMENTO))
										ELSE biv.TASAC
										END)
									END), 3, 1)
								AS NUMERIC(20, 2)) + 
								CAST(ROUND((biv.FLETE + biv.SEGURO + biv.OTROSG) /
									(CASE WHEN biv.exp = 0 THEN 1
									ELSE
										(CASE WHEN biv.TIPO = 8 THEN
											(SELECT TASAC FROM FAC_ELE.dbo.BIVentas T
											WHERE T.TIPO = 7 AND T.ESTADO = 1 AND T.ELIMINADO = 0
											AND T.DOCUMENTO = (SELECT TOP 1 D.DOCUMENTO_REL FROM FAC_ELE.dbo.BIVentasDet D WHERE D.TIPO = 8 AND D.DOCUMENTO = biv.DOCUMENTO))
										ELSE biv.TASAC
										END)
									END), 3, 1)
								AS NUMERIC(20, 2))) AS total,
								(CASE WHEN COALESCE(FE.cufe, '') = '' THEN 0 ELSE 1 END) AS status, biv.id_fac_ele
							FROM FAC_ELE.dbo.BIVentas biv
								LEFT JOIN FAC_ELE.dbo.ESClientes cli ON biv.CODIGO = cli.codigo
								LEFT OUTER JOIN FAC_ELE.dbo.factura_electronica AS FE ON
									FE.llavecomprobante =
										(CASE WHEN biv.ORGANIZACION = 0
											THEN (CASE WHEN biv.TIPO = '7' THEN 'FECM-' WHEN biv.TIPO = '8' THEN 'NCECM-' END)
											ELSE (CASE WHEN biv.TIPO = '7' THEN 'FEOU-' WHEN biv.TIPO = '8' THEN 'NCEOU-' END)
										END) + CAST(biv.DOCUMENTO AS VARCHAR)
							WHERE biv.ESTADO = 1 AND biv.ELIMINADO = 0 AND biv.ORGANIZACION = $idpara AND $codcliente
								AND CAST(biv.FECHA AS DATE) BETWEEN '$fdesde' AND '$fhasta') AS DATOS
						GROUP BY linea, cnt_docs, id_fac_ele, codigo, fecha, llavecomprobante, documento_origen,
							rifcliente, nomcliente, folio, moneda, status, tipo, existcli";
				$sql = $connec->query($sql);
				$datos = [];
				if(!$sql) {
					print_r($connec->errorInfo());
				} else {
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = $row;
					}
				}

				echo json_encode($datos);
				break;
			
			case 'eliminarClte':
					$sql = "DELETE FROM FAC_ELE.dbo.BIVentas WHERE id_fac_ele = $idpara;
							DELETE FROM FAC_ELE.dbo.ESClientes WHERE codigo = $cdclte;
							DELETE FROM ".(($srvvin!='')?$srvvin:'')."BDES.dbo.ESClientes WHERE codigo = $cdclte;";

					$sql = $connec->query($sql);
					if($sql) echo 1;
					else {
						print_r($connec->errorInfo());
						echo 0;
					}

					break;
			
			default:
				break;
		}

		$connec = null;

	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>