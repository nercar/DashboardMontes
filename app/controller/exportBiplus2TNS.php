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
		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', 300);
		switch ($opcion) {
			case 'exportVtasBiplus2TNS':
				// Se valida el parametro de localidad
				if($idpara=='*') {
					$localidad = 'v.localidad!=3';
				} else {
					$localidad = 'v.localidad=' . $idpara;
				}
				/**
				 *
				 * SELECT localidad, 403470 AS codigo, 0, 0, subtotal AS base, 0 AS impuesto FROM (
				 *			SELECT localidad, sum(montoservicio) AS subtotal FROM
				 *			(SELECT distinct(CAST(documento AS VARCHAR)+CAST(localidad AS VARCHAR)+CAST(caja AS VARCHAR)) AS documento, localidad, montoservicio
				 *			FROM BDES_POS.dbo.BIVENTAS_INVC_FP v WHERE CAST(fecha AS DATE) = '$fecha' AND $localidad) AS v
				 *			GROUP BY localidad ) AS j
				 *		UNION ALL
				 *		SELECT localidad, 100000 AS codigo, tipo, porc, sum(base)-
				 *			CASE WHEN tipo = 0 then
				 *				(SELECT sum(montoservicio) AS subtotal FROM
				 *					(SELECT distinct(CAST(documento AS VARCHAR)+CAST(localidad AS VARCHAR)+CAST(caja AS VARCHAR)) AS documento, montoservicio
				 *				FROM BDES_POS.dbo.BIVENTAS_INVC_FP v WHERE CAST(fecha AS DATE) = '$fecha' AND v.localidad = j.localidad) AS v)
				 *			ELSE 0 END AS base, sum(impuesto) AS impuesto FROM (
				 *			SELECT localidad, subgrupo, tipo, CAST(porc AS NUMERIC) AS porc, sum(subtotal) AS base, sum(impuesto) AS impuesto
				 *			FROM (SELECT v.localidad, coalesce(a.subgrupo,0) AS subgrupo, a.codigo,
				 *			v.tipo - 26 AS tipo, CASE WHEN v.impuesto = 0 THEN 0 ELSE (v.impuesto * 100 )/ v.subtotal END AS porc, v.subtotal, v.impuesto
				 *			FROM BDES_POS.dbo.BIVENTAS_INV v LEFT JOIN bdes.dbo.esarticulos a ON
				 *			a.codigo=v.material WHERE CAST(v.fecha AS DATE) = '$fecha' AND $localidad AND v.material NOT IN('2005404','2005405','2005406')) AS tb
				 *			GROUP BY tipo, CAST(porc AS NUMERIC), subgrupo, tb.localidad ) AS j
				 *		WHERE porc ='0'
				 *		GROUP BY tipo, porc, localidad
				 *
				 */
				$sql = "SELECT localidad, 403471 AS codigo, tipo, porc,  sum(base) AS base,sum(impuesto) AS impuesto FROM (
							SELECT localidad, subgrupo, tipo, CAST(porc AS NUMERIC) AS porc, sum(subtotal) AS base, sum(impuesto) AS impuesto
							FROM (SELECT v.localidad, coalesce(a.subgrupo,0) AS subgrupo,a.codigo,
							v.tipo - 26 AS tipo, CASE WHEN v.impuesto = 0 THEN 0 ELSE (v.impuesto * 100 )/ v.subtotal END AS porc, v.subtotal, v.impuesto
							FROM BDES_POS.dbo.BIVENTAS_INV v LEFT JOIN bdes.dbo.esarticulos a ON
							a.codigo=v.material WHERE CAST(v.fecha AS DATE) = '$fecha' AND $localidad AND v.material IN('2005404','2005405','2005406')) AS tb
							GROUP BY tipo, CAST(porc AS NUMERIC), subgrupo, tb.localidad ) AS j
						WHERE porc ='0'
						GROUP BY tipo, porc, localidad
						UNION ALL
						SELECT v.LOCALIDAD, 403470 AS codigo, 0 AS tipo, 0 AS porc, SUM(D.CANTIDAD*COALESCE(A.CPE, 0)) AS subtotal, 0 AS impuesto
						FROM BDES_POS.dbo.BIVENTAS_INVC v
						INNER JOIN BDES_POS.dbo.BIVENTAS_INV D ON D.LOCALIDAD = v.LOCALIDAD AND D.DOCUMENTO = v.DOCUMENTO AND v.CAJA = D.CAJA
						INNER JOIN BDES.DBO.ESARTICULOS A ON A.CODIGO = D.MATERIAL
						WHERE a.tipoarticulo = 7 AND CAST(v.FECHA AS DATE) = '$fecha' AND $localidad
						GROUP BY v.LOCALIDAD
						UNION ALL
						SELECT localidad, 100000 AS codigo, tipo, porc, sum(base)-
							CASE WHEN tipo = 0 then
								COALESCE((
								SELECT SUM(D.CANTIDAD*COALESCE(A.CPE, 0))
								FROM BDES_POS.dbo.BIVENTAS_INVC v
								INNER JOIN BDES_POS.dbo.BIVENTAS_INV D ON D.LOCALIDAD = v.LOCALIDAD AND D.DOCUMENTO = v.DOCUMENTO AND v.CAJA = D.CAJA
								INNER JOIN BDES.DBO.ESARTICULOS A ON A.CODIGO = D.MATERIAL
								WHERE a.tipoarticulo = 7 AND CAST(v.FECHA AS DATE) = '$fecha' AND v.localidad = j.localidad
								GROUP BY v.LOCALIDAD
								), 0)
							ELSE 0 END AS base, sum(impuesto) AS impuesto
						FROM (
							SELECT localidad, subgrupo, tipo, CAST(porc AS NUMERIC) AS porc, sum(subtotal) AS base, sum(impuesto) AS impuesto
							FROM (SELECT v.localidad, coalesce(a.subgrupo,0) AS subgrupo, a.codigo,
							v.tipo - 26 AS tipo, CASE WHEN v.impuesto = 0 THEN 0 ELSE (v.impuesto * 100 )/ v.subtotal END AS porc, v.subtotal, v.impuesto
							FROM BDES_POS.dbo.BIVENTAS_INV v LEFT JOIN bdes.dbo.esarticulos a ON
							a.codigo=v.material WHERE CAST(v.fecha AS DATE) = '$fecha' AND $localidad AND v.material NOT IN('2005404','2005405','2005406')) AS tb
							GROUP BY tipo, CAST(porc AS NUMERIC), subgrupo, tb.localidad ) AS j
						WHERE porc ='0'
						GROUP BY tipo, porc, localidad
						UNION ALL
						SELECT localidad, 100005 AS codigo, tipo, porc,sum(base) AS base, sum(impuesto) AS impuesto FROM (
							SELECT localidad, subgrupo, tipo, CAST(porc AS NUMERIC) AS porc, sum(subtotal) AS base, sum(impuesto) AS impuesto
							FROM (SELECT v.localidad, coalesce(a.subgrupo,0) AS subgrupo, a.codigo,
							v.tipo - 26 AS tipo, CASE WHEN v.impuesto = 0 THEN 0 ELSE (v.impuesto * 100 )/ v.subtotal END AS porc, v.subtotal, v.impuesto
							FROM BDES_POS.dbo.BIVENTAS_INV v LEFT JOIN bdes.dbo.esarticulos a ON
							a.codigo=v.material WHERE CAST(v.fecha AS DATE) = '$fecha' AND $localidad) AS tb
							GROUP BY tipo, CAST(porc AS NUMERIC), subgrupo, tb.localidad ) AS j
						WHERE subgrupo NOT IN ('45','264','265','266','267','268','269','270','271','272','273','274','275','276') AND porc ='5'
						GROUP BY tipo, porc, localidad
						UNION ALL
						SELECT localidad, 100019 AS codigo, tipo, porc, sum(base) AS base, sum(impuesto) AS impuesto FROM (
							SELECT localidad, subgrupo, tipo, CAST(porc AS NUMERIC) AS porc, sum(subtotal) AS base, sum(impuesto) AS impuesto
							FROM (SELECT v.localidad, coalesce(a.subgrupo,0) AS subgrupo,a.codigo,
							v.tipo - 26 AS tipo, CASE WHEN v.impuesto = 0 THEN 0 ELSE (v.impuesto * 100 )/ v.subtotal END AS porc, v.subtotal, v.impuesto
							FROM BDES_POS.dbo.BIVENTAS_INV v LEFT JOIN bdes.dbo.esarticulos a ON
							a.codigo=v.material WHERE CAST(v.fecha AS DATE) = '$fecha' AND $localidad) AS tb
							GROUP BY tipo, CAST(porc AS NUMERIC), subgrupo, tb.localidad ) AS j
						WHERE subgrupo NOT IN ('261','262','263','3') AND porc ='19'
						GROUP BY tipo, porc, localidad
						UNION ALL
						SELECT localidad, 100023 AS codigo,tipo,porc,sum(base) AS base,sum(impuesto) AS impuesto FROM (
							SELECT localidad, subgrupo, tipo, CAST(porc AS NUMERIC) AS porc,sum(subtotal) AS base, sum(impuesto) AS impuesto
							FROM (SELECT v.localidad, coalesce(a.subgrupo,0) AS subgrupo,a.codigo,
							v.tipo - 26 AS tipo, CASE WHEN v.impuesto = 0 THEN 0 ELSE (v.impuesto * 100 )/ v.subtotal END AS porc,v.subtotal,v.impuesto
							FROM BDES_POS.dbo.BIVENTAS_INV v LEFT JOIN bdes.dbo.esarticulos a ON
							a.codigo=v.material WHERE CAST(v.fecha AS DATE) = '$fecha' AND $localidad) AS tb
							GROUP BY tipo, CAST(porc AS NUMERIC), subgrupo, tb.localidad ) AS j
						WHERE subgrupo IN('261','262','263') AND porc ='19'
						GROUP BY tipo, porc, localidad
						UNION ALL
						SELECT localidad, 100025 AS codigo, tipo, porc, sum(base) AS base, sum(impuesto) AS impuesto FROM (
							SELECT localidad, subgrupo, tipo, CAST(porc AS NUMERIC) AS porc,sum(subtotal) AS base, sum(impuesto) AS impuesto
							FROM (SELECT v.localidad, coalesce(a.subgrupo,0) AS subgrupo,a.codigo,
							v.tipo - 26 AS tipo, CASE WHEN v.impuesto = 0 THEN 0 ELSE (v.impuesto * 100 )/ v.subtotal END AS porc, v.subtotal, v.impuesto
							FROM BDES_POS.dbo.BIVENTAS_INV v LEFT JOIN bdes.dbo.esarticulos a ON
							a.codigo=v.material WHERE CAST(v.fecha AS DATE) = '$fecha' AND $localidad) AS tb
							GROUP BY tipo, CAST(porc AS NUMERIC), subgrupo, tb.localidad ) AS j
						WHERE subgrupo IN('45','264','265','266','267','268','269','270','271','272','273','274','275','276') AND porc ='5'
						GROUP BY tipo, porc, localidad
						UNION ALL
						SELECT localidad, 100024 AS codigo, tipo, porc, sum(base) AS base, sum(impuesto) AS impuesto FROM (
							SELECT localidad, subgrupo, tipo, CAST(porc AS NUMERIC) AS porc,sum(subtotal) AS base, sum(impuesto) AS impuesto
							FROM (SELECT v.localidad, coalesce(a.subgrupo,0) AS subgrupo,a.codigo,
							v.tipo - 26 AS tipo, CASE WHEN v.impuesto = 0 THEN 0 ELSE (v.impuesto * 100 )/ v.subtotal END AS porc, v.subtotal, v.impuesto
							FROM BDES_POS.dbo.BIVENTAS_INV v LEFT JOIN bdes.dbo.esarticulos a ON
							a.codigo=v.material WHERE CAST(v.fecha AS DATE) = '$fecha' AND $localidad) AS tb
							GROUP BY tipo, CAST(porc AS NUMERIC), subgrupo, tb.localidad ) AS j
						WHERE subgrupo IN('3') AND porc ='19'
						GROUP BY tipo, porc, localidad
						ORDER BY localidad, tipo, codigo";
				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);
				if($sql) {
					// Se prepara el array para almacenar los datos obtenidos
					$loact = '';
					$local = [];
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						if($locact!=$row['localidad']) {
							$local[] = [ 'localidad' => $row['localidad'] ];
							$locact = $row['localidad'];
						}
						$datos[] = [
							'localidad'=> $row['localidad'],
							'codigo'   => $row['codigo'],
							'tipo'     => $row['tipo'],
							'porc'     => $row['porc'],
							'base'     => abs($row['base']),
							'impuesto' => abs($row['impuesto']),
						];
					}
					$fecha1 = date('d/m/Y', strtotime($fecha));
					require_once "../../Classes/PHPExcel.php";
					require_once "../../Classes/PHPExcel/Writer/Excel5.php";
					$objPHPExcel = new PHPExcel();
					// Set document properties
					$objPHPExcel->getProperties()
						->setCreator("Dashboard")
						->setLastModifiedBy("Dashboard")
						->setTitle("Ventas BI+ para TNS ".$fecha1)
						->setSubject("Ventas BI+ para TNS ".$fecha1)
						->setDescription("Ventas BI+ para TNS ".$fecha1." generado usando el Dashboard.")
						->setKeywords("office 2007 openxml php")
						->setCategory("Ventas BI+ para TNS ".$fecha1);
					$objPHPExcel->setActiveSheetIndex(0);
					$rowCount = 1;
					$icorr = date('dmy', strtotime($fecha));
					$fecha = date('d/m/Y', strtotime($fecha));
					for ($l=0; $l < count($local); $l++) {
						$tienda      = str_pad($local[$l]['localidad'], 2, "0", STR_PAD_LEFT);
						$correlativo = $icorr.$tienda;
						$tbasev      = 0;
						$tivav       = 0;
						$tbased      = 0;
						$tivad       = 0;
						$detvtas     = [];
						$detdevo     = [];
						for ($i = 0; $i < count($datos); $i++) {
							if($datos[$i]['localidad']==$local[$l]['localidad']) {
								$codigo   = $datos[$i]['codigo'];
								$base     = number_format( ( $datos[$i]['base']*1     ), 2, '.', '' );
								$impuesto = number_format( ( $datos[$i]['impuesto']*1 ), 2, '.', '' );
								$porc     = number_format( ( $datos[$i]['porc']*1     ), 2, '.', '' );
								$total    = number_format( ( $base + $impuesto        ), 2, '.', '' );
								if($datos[$i]['tipo']==0) {
									$detvtas[]=['3','FV',$codigo,'00','D','1',$base,$impuesto,$base,$base,$total,$porc,$total];
									// if($codigo!='403470') {
										$tbasev += $base;
										$tivav  += $impuesto;
									// }
								} else {
									$detdevo[]=['3','DV',$codigo,'00','D','1',$base,$impuesto,$base,$base,$total,$porc,$total];
									// if($codigo!='403470') {
										$tbased += $base;
										$tivad  += $impuesto;
									// }
								}
							}
						}
						$tbasev = number_format( $tbasev, 2, '.', '' );
						$tivav  = number_format( $tivav, 2, '.', ''  );
						$tbased = number_format( $tbased, 2, '.', '' );
						$tivad  = number_format( $tivad, 2, '.', ''  );
						$totalv = number_format( ( $tbasev + $tivav ), 2, '.', '' );
						$totald = number_format( ( $tbased + $tivad ), 2, '.', '' );
						$cabvtas = array(
									'1','FV','00',$correlativo,$fecha,'00','00','00','MU','00',$fecha,
									'','','','','','1','0','0','0','0','0',$tienda,'00','00',
									'','0','0',$tbasev,$tivav,'0','0','0','0',$totalv,
									'',$totalv,'0','ADMIN','0','0','0',$correlativo);
						$pievtas = array('5','FV',$fecha,'',$correlativo,$totalv,'0','0','0','00');
						$cabdevo = array(
									'1','DV','00',$correlativo,$fecha,'00','00','00','MU','00',$fecha,
									'','','','','','1','0','0','0','0','0',$tienda,'00','00',
									'','0','0',$tbased,$tivad,'0','0','0','0',$totald,
									'',$totald,'0','ADMIN','0','0','0',$correlativo);
						$piedevo = array('5','DV',$fecha,'',$correlativo,$totald,'0','0','0','00');
						if($totalv > 0) {
							$let1 = '';
							$let2 = 65;
							for ($i=0; $i < count($cabvtas) ; $i++) {
								$objPHPExcel->getActiveSheet()->SetCellValue($let1.chr($let2).$rowCount, $cabvtas[$i]);
								$let2++;
								if($let2==91) {
									$let2 = 65;
									$let1 = 'A';
								}
							}
							for ($i=0; $i < count($detvtas); $i++) {
								$rowCount++;
								$let1 = '';
								$let2 = 65;
								for ($j=0; $j < count($detvtas[$i]); $j++) {
									$objPHPExcel->getActiveSheet()->SetCellValue($let1.chr($let2).$rowCount, $detvtas[$i][$j]);
									$let2++;
									if($let2==91) {
										$let2 = 65;
										$let1 = 'A';
									}
								}
							}
							$rowCount++;
							$let1 = '';
							$let2 = 65;
							for ($i=0; $i < count($pievtas); $i++) {
								$objPHPExcel->getActiveSheet()->SetCellValue($let1.chr($let2).$rowCount, $pievtas[$i]);
								$let2++;
								if($let2==91) {
									$let2 = 65;
									$let1 = 'A';
								}
							}
						}
						if($totald > 0) {
							$rowCount++;
							$let1 = '';
							$let2 = 65;
							for ($i=0; $i < count($cabdevo) ; $i++) {
								$objPHPExcel->getActiveSheet()->SetCellValue($let1.chr($let2).$rowCount, $cabdevo[$i]);
								$let2++;
								if($let2==91) {
									$let2 = 65;
									$let1 = 'A';
								}
							}
							for ($i=0; $i < count($detdevo); $i++) {
								$rowCount++;
								$let1 = '';
								$let2 = 65;
								for ($j=0; $j < count($detdevo[$i]); $j++) {
									$objPHPExcel->getActiveSheet()->SetCellValue($let1.chr($let2).$rowCount, $detdevo[$i][$j]);
									$let2++;
									if($let2==91) {
										$let2 = 65;
										$let1 = 'A';
									}
								}
							}
							$rowCount++;
							$let1 = '';
							$let2 = 65;
							for ($i=0; $i < count($piedevo); $i++) {
								$objPHPExcel->getActiveSheet()->SetCellValue($let1.chr($let2).$rowCount, $piedevo[$i]);
								$let2++;
								if($let2==91) {
									$let2 = 65;
									$let1 = 'A';
								}
							}
						}
						$rowCount++;
					}
					// Rename worksheet
					$objPHPExcel->getActiveSheet()->setTitle('VtasBimas2TNS');
					// Set active sheet index to the first sheet, so Excel opens this as the first sheet
					$objPHPExcel->setActiveSheetIndex(0);
					// Redirect output to a client’s web browser (Excel5)
					header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
					header('Content-Disposition: attachment;filename="VtasBimas2TNS.xls"');
					header('Cache-Control: max-age=0');
					// If you're serving to IE 9, then the following may be needed
					header('Cache-Control: max-age=1');
					// If you're serving to IE over SSL, then the following may be needed
					header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
					header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
					header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
					header ('Pragma: public'); // HTTP/1.0
					$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
					if($idpara=='*') {
						$ext = 'tt';
					} else {
						$ext = str_pad($idpara, 2, "0", STR_PAD_LEFT);
					}
					$objWriter->save('../../tmp/TNS_VE'.$icorr.$ext.'.xls');
					echo json_encode(array(
							'conteo' => count($datos),
							'enlace'=>'tmp/TNS_VE'.$icorr.$ext.'.xls',
							'archivo'=>'TNS_VE'.$icorr.$ext.'.xls'));
				} else {
					print_r($connec->errorInfo());
					echo json_encode(array(
							'conteo' => count($datos),
							'enlace'=>null,
							'archivo'=>null));
				}
				break;
			case 'exportCostBiplus2TNS':
				// Se valida el parametro de localidad
				if($idpara=='*') {
					$localidad = 'v.localidad!=3';
				} else {
					$localidad = 'v.localidad=' . $idpara;
				}
				// Se prepara el query para obtener los datos
				$sql = "SELECT localidad, tipo, SUM(costo) AS costo
						FROM
							(SELECT localidad, SUM(Costo) AS Costo, tipo
								FROM (SELECT v.localidad, SUM(v.COSTO * v.CANTIDAD) AS Costo, v.Tipo - 26 AS tipo
									FROM BDES_POS.dbo.BIVENTAS_INV v
									WHERE CAST(v.FECHA AS DATE) = '$fecha'
									AND $localidad
									GROUP BY v.tipo, v.localidad) AS tb
							GROUP BY tb.tipo,tb.localidad) AS j
						GROUP BY tipo, localidad";
				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				$loact = '';
				$local = [];
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($locact!=$row['localidad']) {
						$local[] = [ 'localidad' => $row['localidad'] ];
						$locact = $row['localidad'];
					}
					$datos[] = [
						'localidad'=> $row['localidad'],
						'tipo'     => $row['tipo'],
						'costo'    => abs($row['costo']),
					];
				}
				$fecha1 = date('d/m/Y', strtotime($fecha));
				$icorr  = date('md', strtotime($fecha));
				$fecha  = date('d/m/Y', strtotime($fecha));
				if($idpara=='*') {
					$ext = 'TT';
				} else {
					$ext = str_pad($idpara, 2, "0", STR_PAD_LEFT);
				}
				$file = '../../tmp/TNS_CV'.$ext.$icorr.'.csv';
				$fp = fopen($file, 'c');
				for ($l=0; $l < count($local); $l++) {
					$tienda      = str_pad($local[$l]['localidad'], 2, "0", STR_PAD_LEFT);
					$detvtas     = '';
					$detdevo     = '';
					for ($i = 0; $i < count($datos); $i++) {
						if($datos[$i]['localidad']==$local[$l]['localidad']) {
							$monto = number_format( $datos[$i]['costo'], 2, '.', '' );
							if($datos[$i]['tipo']==0) {
								$correlav = 'V'.$tienda.$icorr.'9';
								$detvtas1 = array('613505.01',$monto,'D','00','0','0','FV',$correlav,$tienda);
								$detvtas2 = array('143505.01',$monto,'C','00','0','0','FV',$correlav,$tienda);
							} else {
								$correlad = 'D'.$tienda.$icorr.'9';
								$detdevo1 = array('143505.01',$monto,'D','00','0','0','DV',$correlad,$tienda);
								$detdevo2 = array('613505.01',$monto,'C','00','0','0','DV',$correlad,$tienda);
							}
						}
					}
					$cabvtas = array('*CC','CC',$correlav,$fecha,'VEN_Comprobante_Costos');
					$cabdevo = array('*CC','CC',$correlad,$fecha,'DEV_Comprobante_Costos');
					if($detvtas1!='') {
						fputcsv($fp, $cabvtas);
						fputcsv($fp, $detvtas1);
						fputcsv($fp, $detvtas2);
					}
					if($detdevo1!='') {
						fputcsv($fp, $cabdevo);
						fputcsv($fp, $detdevo1);
						fputcsv($fp, $detdevo2);
					}
				}
				fclose($fp);
				echo json_encode(array('enlace'=>'tmp/TNS_CV'.$ext.$icorr.'.csv', 'archivo'=>'TNS_CV'.$ext.$icorr.'.csv'));
				break;
		}
		$connec = null;
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>