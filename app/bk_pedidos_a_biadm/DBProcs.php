<?php
	/**
	* Permite obtener los datos de la base de datos y retornarlos
	* en modo json o array
	*/

	try {
		date_default_timezone_set('America/Bogota');
		// Se capturan las opciones por Post
		$opcion = (isset($_POST["opcion"])) ? $_POST["opcion"] : "";
		$fecha  = (isset($_POST["fecha"]) ) ? $_POST["fecha"]  : date("Y-m-d");
		$hora   = (isset($_POST["hora"])  ) ? $_POST["hora"]   : date("H:i:s");

		// id para los filtros en las consultas
		$idpara = (isset($_POST["idpara"])) ? $_POST["idpara"] : '';

		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../dist/config.ini');

		if ($params === false) {
			throw new \Exception("Error reading database configuration file");
		}

		if(isset($_POST["sqlcnx"])) {
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
		$art_excl    = $params['art_excl'];
		$moneda      = $params['moneda'];
		$simbolo     = $params['simbolo'];
		$fecinibimas = $params['fecinibimas'];
		$svrftp      = $params['svrftp'];
		$svrftp2     = $params['svrftp2'];
		$porftp      = $params['porftp'];
		$usrftp      = $params['usrftp'];
		$pasftp      = $params['pasftp'];
		$psvftp      = $params['psvftp'];

		$datos = [];
		switch ($opcion) {
			case 'hora_srv':
				echo json_encode('1¬' . $hora);
				break;

			case 'iniciar_sesion':
				if(empty($_POST['tusuario']) || empty($_POST['tclave'])){
					header("Location: " . $idpara);
					break;
				}

				$sql = "SELECT usuario, nombre, clave, tienda, activo,
						(SELECT nombre FROM tiendas WHERE id = tienda) AS sucursal
						FROM usuarios WHERE LOWER(usuario)=LOWER('" . $_POST['tusuario'] . "')";

				$sql = $connec->query($sql);
				$row = $sql->fetch();

				if($row['clave'] == $_POST['tclave']){
					if(!$row['activo']) {
						session_start();
						session_id($_SESSION['id']);
						session_destroy();
						session_commit();
						session_start();
						$_SESSION['error'] = 2;
						header("Location: " . $idpara);
					} else {
						session_start();
						$_SESSION['id']         = session_id();
						$_SESSION['url']        = $idpara;
						$_SESSION['usuario']    = strtolower($row['usuario']);
						$_SESSION['nomusuario'] = ucwords(strtolower($row['nombre']));
						$_SESSION['tienda']     = ($row['tienda']==0) ? "'*'" : "'" . $row['tienda'] . "'";
						$_SESSION['sucursal']   = "'" . $row['sucursal'] . "'";
						$_SESSION['error']      = 0;

						$sql = "SELECT modulo
								FROM usuario_modulos
								WHERE LOWER(usuario)=LOWER('" . $_POST['tusuario'] . "')
								AND modulo IN(SELECT id FROM modulos WHERE activo)";
						// Se ejecuta la consulta en la BBDD
						$sql = $connec->query($sql);
						$modulos = '';
						while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
							if(strlen($modulos)>0) { $modulos .= ','; }
							$modulos .= '[' . $row['modulo'] . ']';
						}
						$_SESSION['modulos'] = $modulos;
						header("Location: " . $idpara . "inicio.php");
					}
				} else {
					session_start();
					session_id($_SESSION['id']);
					session_destroy();
					session_commit();
					session_start();
					$_SESSION['error'] = 1;
					header("Location: " . $idpara);
				}
				// Indica desde cuando hay información en dashboard
				$_SESSION['fecinibimas'] = $fecinibimas;
				break;

			case 'cerrar_sesion':
				session_start();
				session_id($_SESSION['id']);
				session_destroy();
				session_commit();
				header("Location: " . $_SESSION['url']);
				exit();
				break;

			case 'cambiarClave':
				session_start();
				$params = explode(md5('¬'), $idpara);

				$sql = "SELECT * FROM usuarios
						WHERE LOWER(usuario)=LOWER('" . $_SESSION['usuario'] . "')
						AND clave = '" . $params[0] . "'";

				$sql = $connec->query($sql);
				$row = $sql->fetch();

				if($row){
					$sql = "UPDATE usuarios SET clave = '$params[1]'
							WHERE LOWER(usuario)=LOWER('" . $_SESSION['usuario'] ."')
							AND clave = '$params[0]'";
					$sql = $connec->query($sql);
					$row = $sql->fetch();
					$result = '1¬Se ha actualizado la clave correctamente!!!';
				} else {
					$result = '0¬La clave actual suministrada\nno corresponde al usuario. Verifique!!!';
				}
				echo json_encode($result);
				break;

			case 'subirArchivo':
				// Se extraen los valores de idpara
				$params = explode('¬', $idpara);
				// Se verifica si se envio algun archivo
				$target_path = "../tmp/";
				$archivoreal = basename($_FILES['archivo']['name']);
				$extension = explode('.', $archivoreal);
				$extension = end($extension);
				$result = "0¬Hubo un error, Por favor revise el archivo y trate de nuevo!(" . $archivoreal . ")";
				if($extension == 'csv') {
					$archivoreal = bin2hex(random_bytes(10)) . '.' . $extension;
					$archivotemp = $_FILES['archivo']['tmp_name'];
					$target_path = $target_path . $archivoreal;
					if(move_uploaded_file($archivotemp, $target_path)) {
						$linea = 0;
						$registros = 0;
						$actualiza = 0;
						$tipo = 'i';
						$delimiter = getFileDelimiter($target_path);
						//Abrimos nuestro archivo
						$archivo = fopen($target_path, "r");
						// Se identifica la opcion a subir
						switch ($params[0]) {
							case 'presupuestos':
								// recorremos el archivo
								while(($datos = fgetcsv($archivo, null, $delimiter)) == true) {
									if($linea==0) {
										$linea++;
										continue;
									}
									$fechaf = explode(strpos($datos[2], "/") > 0 ? "/" : "-", $datos[2]);
									$fechaf = $fechaf[2] . '-' . $fechaf[1] . '-' . $fechaf[0];
									$monto  = str_replace(',', '.', $datos[3]);
									if($params[1]=='1') {
										$sql = "SELECT * FROM presupuestos WHERE tienda_id = $datos[0] AND fecha = '$fechaf' AND monto != $monto*1;";
										$sql = $connec->query($sql);
										if($sql->rowCount()>0) {
											$sql = "UPDATE presupuestos SET monto = $monto*1 WHERE tienda_id = $datos[0] AND fecha = '$fechaf';";
											$tipo = 'u';
										} else {
											$sql = "INSERT INTO presupuestos(tienda_id, tienda_nombre, fecha, monto) "
												 . "VALUES (" . $datos[0] . ", '" . $datos[1] . "', '" . $fechaf . "', " . $monto . ");";
											$tipo = 'i';
										}
									} else {
										$sql = "INSERT INTO presupuestos(tienda_id, tienda_nombre, fecha, monto) "
											 . "VALUES (" . $datos[0] . ", '" . $datos[1] . "', '" . $fechaf . "', " . $monto . ");";
										$tipo = 'i';
									}
									$sql = $connec->query($sql);
									if($sql) {
										if($tipo=='i') {
											$registros++;
										} else {
											$actualiza++;
										}
									}
								}
								break;

							case 'promociones':
								// recorremos el archivo
								while(($datos = fgetcsv($archivo, null, $delimiter)) == true) {
									if($linea==0) {
										$linea++;
										continue;
									}
									$fechai = explode(strpos($datos[2], "/") > 0 ? "/" : "-", $datos[2]);
									$fechai = $fechai[2] . '-' . $fechai[1] . '-' . $fechai[0];
									$fechaf = explode(strpos($datos[3], "/") > 0 ? "/" : "-", $datos[3]);
									$fechaf = $fechaf[2] . '-' . $fechaf[1] . '-' . $fechaf[0];
									if($params[1]=='1') {
										$sql = "SELECT * FROM promociones WHERE texto = '$datos[0]';";
										$sql = $connec->query($sql);
										if($sql->rowCount()>0) {
											$sql    = "	UPDATE promociones SET texto = '$datos[0]',
														fecha_ini = '$fechai', fecha_fin = '$fechaf'
														WHERE texto = '$datos[0]';";
											$tipo = 'u';
										} else {
											$sql    = "INSERT INTO promociones(texto, fecha_ini, fecha_fin) "
													. "VALUES ('" . $datos[0] . "', '" . $fechai . "', '" . $fechaf . "');";
											$tipo = 'i';
										}
									} else {
										$sql = "INSERT INTO promociones(texto, fecha_ini, fecha_fin) "
											 . "VALUES ('" . $datos[0] . "', '" . $fechai . "', '" . $fechaf . "');";
										$tipo = 'i';
									}
									$sql = $connec->query($sql);
									if($sql) {
										if($tipo=='i') {
											$registros++;
										} else {
											$actualiza++;
										}
									}
								}
								break;
						}
						//Cerramos el archivo
						fclose($archivo);
						$result = "1¬Se Registraron $registros registros nuevos\nSe Actualizaron $actualiza registros actuales";
						unlink($target_path);
					}
				}
				echo json_encode($result);
				break;

			case 'cedulasid':
				$sql = "SELECT id, descripcion, predeterminado
						FROM BDES.dbo.ESCedulasId
						ORDER BY predeterminado DESC";

				$sql = $connec->query($sql);

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id'             => $row['id'],
						'descripcion'    => $row['descripcion'],
						'predeterminado' => $row['predeterminado'],
					];
				}

				echo json_encode($datos);
				break;

			case 'validarArchivoCsv':
				$target_path = "../tmp/";
				$archivoreal = basename($_FILES['archivo']['name']);
				$extension = explode('.', $archivoreal);
				$extension = end($extension);
				$idempresa = 0;
				$table = "0¬Hubo un error, Por favor revise el archivo y trate de nuevo!(" . $archivoreal . ")";
				if($extension == 'csv') {
					$archivoreal = bin2hex(random_bytes(10)) . '.' . $extension;
					$archivotemp = $_FILES['archivo']['tmp_name'];
					$target_path = $target_path . $archivoreal;
					if(move_uploaded_file($archivotemp, $target_path)) {
						// Indica las filas que hay con el id_archivo en historico
						$row = 0;
						$delimiter = getFileDelimiter($target_path);
						// Abrimos el archivo para validar el id
						$archivo = fopen($target_path, "r");
						$datos = fgetcsv($archivo, null, $delimiter);
						if($datos[1]!='') {
							$sql = "SELECT COUNT(*) AS cuenta FROM BDES_POS.dbo.DBBonos_H WHERE id_archivo = '$datos[1]'";
							$sql = $connec->query($sql);
							if(!$sql) print_r($connec->errorInfo());
							else {
								$row = $sql->fetch();
								$row = $row['cuenta'];
							}
							$table .= '(id_archivo: '.$datos[1].')';
						}

						if($row==0) {
							$table = '1¬<table width="90%" cellpadding="2" cellspacing="2" class="table-bordered">';
							//Abrimos nuestro archivo
							$archivo = fopen($target_path, "r");

							// recorremos el archivo
							while(($datos = fgetcsv($archivo, null, $delimiter)) == true) {
								$table .= '<tr>';
								for($i=0;$i<count($datos);$i++) {
									if($datos[$i]!=='') {
										$table .= '<td>' . utf8_encode($datos[$i]) . '</td>';
									}
								}
								$table .= '</tr>';
							}
							$table.'</table>';
						}

						//Cerramos el archivo
						fclose($archivo);
						unlink($target_path);
					}
				}

				echo $table;
				break;
			case 'subirArchivoBonos':
				// Se verifica si se envio algun archivo
				$target_path = "../tmp/";
				$archivoreal = basename($_FILES['archivo']['name']);
				$extension = explode('.', $archivoreal);
				$extension = end($extension);
				$idempresa = 0;
				$id_archivo = '';
				$result = "0¬Hubo un error, Por favor revise el archivo y trate de nuevo!(" . $archivoreal . ")";
				if($extension == 'csv') {
					$archivoreal = bin2hex(random_bytes(10)) . '.' . $extension;
					$archivotemp = $_FILES['archivo']['tmp_name'];
					$target_path = $target_path . $archivoreal;
					if(move_uploaded_file($archivotemp, $target_path)) {
						$sql = "IF OBJECT_ID(N'BDES_POS.dbo.DBBonosTMP', N'U') IS NULL
								BEGIN
									CREATE TABLE BDES_POS.dbo.DBBonosTMP(
										id_empresa NVARCHAR(15) NOT NULL,
										nom_empresa VARCHAR(255),
										dir_empresa VARCHAR(255),
										tel_empresa VARCHAR(255),
										monto DECIMAL(18, 2),
										consumo INT,
										fecha_vence DATETIME,
										id_beneficiario NVARCHAR(15) NOT NULL,
										nom_beneficiario VARCHAR(255),
										dir_beneficiario VARCHAR(255),
										ema_beneficiario NVARCHAR(50),
										tel_beneficiario NVARCHAR(20));

									ALTER TABLE BDES_POS.dbo.DBBonosTMP
										ADD PRIMARY KEY CLUSTERED ([id_empresa], [id_beneficiario])
										WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF,
											IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON,
											ALLOW_PAGE_LOCKS = ON);
								END;";

						$sql = $connec->query($sql);

						if(!$sql) {
							$result = "0¬Error Creando Tabla Temporal<br>".$connec->errorInfo()[2].'¬0';
							echo json_encode($result);
							break;
						}

						// Se detecta el delimitador de campos del csv
						$delimiter = getFileDelimiter($target_path);
						// Se inicializa la linea para detectar las 2 primeras lineas de datos
						$linea = 0;
						// Filas para insertar en la tabla temporal y en el historico de dbbonos
						$registros = [];
						//Abrimos nuestro archivo
						$archivo = fopen($target_path, "r");

						// recorremos el archivo
						while(($datos = fgetcsv($archivo, null, $delimiter)) == true) {
							if($linea<=1) {
								if($linea==0) {
									$id_archivo = $datos[1];
								}
								$linea++;
								continue;
							}
							$idempresa = $datos[0];
							$fecha = explode(strpos($datos[6], "/") > 0 ? "/" : "-", $datos[6]);
							$fecha = $fecha[2] . '-' . $fecha[1] . '-' . $fecha[0];
							$fecha = "'".$fecha.'T23:59:59.997'."'";
							$registros[] = [
								$datos[0], "'".$datos[1]."'", "'".$datos[2]."'", "'".$datos[3]."'",
								$datos[4], $datos[5], $fecha, "'".$datos[7]."'",
								"'".mb_convert_encoding($datos[8], 'UTF-8', 'ISO-8859-1')."'",
								"'".mb_convert_encoding($datos[9], 'UTF-8', 'ISO-8859-1')."'",
								"'".mb_convert_encoding($datos[10], 'UTF-8', 'ISO-8859-1')."'",
								"'".$datos[11]."'", "'".$id_archivo."'",
								"'".mb_convert_encoding($datos[12], 'UTF-8', 'ISO-8859-1')."'",
								"'".mb_convert_encoding($datos[13], 'UTF-8', 'ISO-8859-1')."'"
							];
						}

						// Cerramos el archivo
						fclose($archivo);
						// Eliminamos el archivo
						unlink($target_path);

						$sql = "TRUNCATE TABLE BDES_POS.dbo.DBBonosTMP;";
						$sql = $connec->query($sql);

						$maxreg = 900;
						$regact = 0;

						$filas = '';
						foreach ($registros as $value) {
							$regact++;
							$filas.= "('".
								$value[0]."',".$value[1].",".$value[2].",".$value[3].",".
								$value[4].",".$value[5].",".$value[6].",".$value[7].",".
								$value[8].",".$value[9].",".$value[10].",".$value[11].",".
								$value[12]."),";

							if($regact==$maxreg) {
								// Se elimina la ultima coma de la cadena
								$filas = substr($filas, 0, -1);

								// Se crea el query para insertar en la tabla temporal de dbbonos
								$sql = "INSERT INTO BDES_POS.dbo.DBBonosTMP(
											id_empresa,
											nom_empresa,
											dir_empresa,
											tel_empresa,
											monto,
											consumo,
											fecha_vence,
											id_beneficiario,
											nom_beneficiario,
											dir_beneficiario,
											ema_beneficiario,
											tel_beneficiario,
											id_archivo)
										VALUES ".$filas;

								$sql = $connec->query($sql);
								if(!$sql) {
									$result = "0¬Error Insertando en Temporal<br>".$connec->errorInfo()[2].'¬0';
									echo json_encode($result);
									break;
								}
								$regact = 0;
								$filas = '';
							}
						}

						if($regact>0) {
							// Se elimina la ultima coma de la cadena
							$filas = substr($filas, 0, -1);
							// Se crea el query para insertar en la tabla temporal de dbbonos
							$sql = "INSERT INTO BDES_POS.dbo.DBBonosTMP(
										id_empresa,
										nom_empresa,
										dir_empresa,
										tel_empresa,
										monto,
										consumo,
										fecha_vence,
										id_beneficiario,
										nom_beneficiario,
										dir_beneficiario,
										ema_beneficiario,
										tel_beneficiario,
										id_archivo)
									VALUES ".$filas;

							$sql = $connec->query($sql);
							if(!$sql) {
								$result = "0¬Error Insertando en Temporal<br>".$connec->errorInfo()[2].'¬0';
								echo json_encode($result);
								break;
							}
							$regact = 0;
							$filas = '';
						}

						$maxreg = 900;
						$regact = 0;

						$filas = '';
						foreach ($registros as $value) {
							$regact++;
							$tipo = ($value[5]==2) ? 2 : 1;
							$mvto = ($value[5]==2) ? 'Nota de Débito' : 'Cargo por subida de archivo';
							$filas.= "('".
								$value[0]."',".$value[7].",GETDATE(),".$value[4].",".$tipo.",'".$_POST['usidnom']."',".
								$value[12].",'".$mvto."',".$value[13].",".$value[14]."),";
							if($regact==$maxreg) {
								// Se elimina la ultima coma de la cadena
								$filas = substr($filas, 0, -1);

								// Se crea el query para insertar en la tabla historica de dbbonos_h
								$sql = "INSERT INTO BDES_POS.dbo.DBBonos_H(
											id_empresa,
											id_beneficiario,
											fecha,
											monto,
											tipo,
											usuario,
											id_archivo,
											movimiento,
											controlclte,
											planillaclte)
										VALUES ".$filas;

								$sql = $connec->query($sql);
								if(!$sql) {
									$result = "0¬Error Insertando en Historico<br>".$connec->errorInfo()[2].'¬0';
									echo json_encode($result);
									break;
								}
								$regact = 0;
								$filas = '';
							}
						}

						if($regact>0) {
							// Se elimina la ultima coma de la cadena
							$filas = substr($filas, 0, -1);

							// Se crea el query para insertar en la tabla temporal de dbbonos
							$sql = "INSERT INTO BDES_POS.dbo.DBBonos_H(
										id_empresa,
										id_beneficiario,
										fecha,
										monto,
										tipo,
										usuario,
										id_archivo,
										movimiento,
										controlclte,
										planillaclte)
									VALUES ".$filas;

							$sql = $connec->query($sql);
							if(!$sql) {
								$result = "0¬Error Insertando en Historico<br>".$connec->errorInfo()[2].'¬0';
								echo json_encode($result);
								break;
							}
							$filas = '';
						}

						$sql = "MERGE BDES_POS.dbo.ESCLIENTESPOS AS destino
								USING
								(SELECT DISTINCT id_beneficiario, nom_beneficiario, dir_beneficiario,
									ema_beneficiario, tel_beneficiario FROM BDES_POS.dbo.DBBonosTMP) AS origen
								ON destino.RIF = origen.id_beneficiario
								WHEN MATCHED THEN
									UPDATE SET
										RAZON = origen.nom_beneficiario,
										DIRECCION = origen.dir_beneficiario,
										EMAIL = origen.ema_beneficiario,
										TELEFONO = origen.tel_beneficiario,
										ACTUALIZO = 0
								WHEN NOT MATCHED THEN
									INSERT(RIF, RAZON, DIRECCION, ACTIVO,
										IDTR, ACTUALIZO, EMAIL, TELEFONO)
									VALUES(origen.id_beneficiario,
										origen.nom_beneficiario,
										origen.dir_beneficiario,
										1, 0, 0,
										origen.ema_beneficiario,
										origen.tel_beneficiario);";

						$sql = $connec->query($sql);
						if(!$sql) {
							$result = "0¬Error Actualizando en Clientes<br>".$connec->errorInfo()[2].'¬0';
							echo json_encode($result);
							break;
						}

						$sql = "MERGE BDES_POS.dbo.DBBonos AS destino
								USING (	SELECT * FROM BDES_POS.dbo.DBBonosTMP ) AS origen
								ON destino.id_empresa = origen.id_empresa
									AND destino.id_beneficiario = origen.id_beneficiario
								WHEN MATCHED THEN
									UPDATE SET
										consumo = origen.consumo,
										fecha_mod = GETDATE(),
										fecha_vence = origen.fecha_vence
								WHEN NOT MATCHED THEN
									INSERT(id_empresa, nom_empresa, dir_empresa, tel_empresa,
										id_beneficiario, consumo, fecha_mod, fecha_vence)
									VALUES(origen.id_empresa, origen.nom_empresa, origen.dir_empresa,
										origen.tel_empresa, origen.id_beneficiario,
										origen.consumo, GETDATE(), origen.fecha_vence);";

						$sql = $connec->exec($sql);

						if($sql===false) {
							$result = "0¬Error al Actualizar los registros<br>".$connec->errorInfo()[2].'¬0';
						} else {
							$result = "1¬Registros afectados ($sql)¬".$idempresa;
						}
					}
				}

				echo json_encode($result);
				break;

			case 'buscarEmpresa':
				$sql = "SELECT se.id_empresa, b.nom_empresa, se.id_beneficiario,
							cl.RAZON AS nom_beneficiario, MAX(se.fecha) AS fecha_mod,
							SUM(se.saldo) AS saldo
						FROM (SELECT id_empresa, id_beneficiario, fecha,
							(CASE WHEN tipo = 1 THEN monto ELSE monto*(-1) END) AS saldo
							FROM BDES_POS.dbo.DBBonos_H WHERE id_empresa = '$idpara') AS se
						INNER JOIN
							(SELECT DISTINCT id_empresa, nom_empresa
							FROM BDES_POS.dbo.DBBonos) AS b ON b.id_empresa = se.id_empresa
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = se.id_beneficiario
						GROUP BY se.id_empresa, b.nom_empresa, se.id_beneficiario, cl.RAZON
						HAVING (SUM(se.saldo)>0)";

				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
					echo json_encode(
						array(
							'id_empresa' =>'',
							'nom_empresa' => '',
							'monto_resta' => 0,
							'beneficiario' => []
						)
					);
				} else {
					$id_empresa  = '';
					$nom_empresa = '';
					$monto_resta = 0;
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$id_empresa  = $row['id_empresa'];
						$nom_empresa = $row['nom_empresa'];
						$monto_resta+= $row['saldo'];
						$nomben      = $row['nom_beneficiario'];
						$datos[] = [
							'id_beneficiario'  => $row['id_beneficiario'],
							'nom_beneficiario' => $nomben,
							'saldo'            => $row['saldo'],
							'fecha_mod'        => '<span style="display: none;">'.$row['fecha_mod'].'</span>'.
												  date('d-m-Y H:i a', strtotime($row['fecha_mod'])),
						];
					}

					echo json_encode(
						array(
							'id_empresa' => $id_empresa,
							'nom_empresa' => $nom_empresa,
							'monto_resta' => number_format($monto_resta, 2),
							'beneficiario' => $datos
						)
					);
				}
				break;

			case 'buscarEdoCta':
				$sql = "SELECT RAZON FROM BDES_POS.dbo.ESCLIENTESPOS WHERE RIF = '$idpara'";
				$sql = $connec->query($sql);
				$row = $sql->fetch();
				$nombeneficiario = '';
				if($row) {
					$nombeneficiario = $row['RAZON'];
					$fecha = explode(',', $fecha);
					$sql = "SELECT SUM(saldo) AS saldoi
								FROM (
									SELECT (CASE WHEN tipo = 1 THEN SUM(monto) ELSE SUM(monto)*(-1) END) AS saldo
									FROM BDES_POS.dbo.DBBonos_H WHERE id_beneficiario = '$idpara'
										AND CAST(fecha AS DATE) < CAST('$fecha[0]' AS DATE)
									GROUP BY tipo, fecha
								) AS saldo";

					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());

					$row = $sql->fetch();
					$saldoi = $row['saldoi']*1;

					$sql = "SELECT  b.id, b.id_beneficiario, b.fecha, b.movimiento,
								COALESCE(ti.nombre, '') AS tienda, b.status,
								COALESCE(b.caja, 0) AS caja,
								COALESCE(b.documento, 0) AS factura,
								(CASE WHEN b.tipo = 1 THEN b.monto ELSE 0 END) AS debe,
								(CASE WHEN b.tipo!= 1 THEN b.monto ELSE 0 END) AS haber
							FROM BDES_POS.dbo.DBBonos_H b
							LEFT JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = b.localidad
							WHERE id_beneficiario = '$idpara'
							AND CAST(b.fecha AS DATE) BETWEEN CAST('$fecha[0]' AS DATE)
								AND CAST('$fecha[1]' AS DATE)
							ORDER BY b.id";

					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());

					$i = 0;
					$datos = [];
					$datos[] = [
						'fila'       => $i,
						'id'         => '',
						'fecha'      => '',
						'movimiento' => 'Saldo Anterior',
						'debe'       => '',
						'haber'      => '',
						'saldo'      => number_format($saldoi, 2),
					];

					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$i++;
						$saldoi += ($row['debe']*1) - ($row['haber']*1);
						$mvto = $row['movimiento'];
						if($row['tienda']!='') {
							$mvto = '<div class="txtcomp w-100">'.$row['movimiento'].'<br>'
								  . '<b>Tienda: </b>'.$row['tienda'].'&emsp;'
								  . '<b>Caja: </b>'.$row['caja'].'&emsp;'
								  . '<b>Factura: </b>'.$row['factura'].'&emsp;'
								  . '</div>';
						}
						$datos[] = [
							'fila'       => $i,
							'id'         => $row['status']==1 ? '' : $row['id'],
							'fecha'      => date('d-m-Y H:i a', strtotime($row['fecha'])),
							'movimiento' => $mvto,
							'debe'       => $row['debe']==0?'':number_format($row['debe'], 2),
							'haber'      => $row['haber']==0?'':number_format($row['haber'], 2),
							'saldo'      => number_format($saldoi, 2),
							'saldo2'     => $saldoi*1,
						];
					}
				}

				echo json_encode(array('nombeneficiario' => $nombeneficiario, 'datos' => $datos));
				break;

			case 'buscarBen':
				$idpara = explode('¬', $idpara);
				$sql = "SELECT cl.RAZON, SUM(saldo) AS saldo, consumo
						FROM (
							SELECT DBH.id_beneficiario, DBB.consumo,
								(CASE WHEN DBH.tipo = 1 THEN SUM(DBH.monto) ELSE SUM(DBH.monto)*(-1) END) AS saldo
							FROM BDES_POS.dbo.DBBonos_H DBH
							INNER JOIN BDES_POS.dbo.DBBonos DBB ON DBB.id_beneficiario = DBH.id_beneficiario
								AND DBB.id_empresa = DBH.id_empresa
								WHERE DBH.id_beneficiario = '$idpara[0]' AND DBH.id_empresa = '$idpara[1]'
							GROUP BY DBH.id_beneficiario, DBH.tipo, DBB.consumo
						) AS ben
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = ben.id_beneficiario
						GROUP BY cl.RAZON, consumo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$row = $sql->fetch();
				if($row) {
					$saldo = $row['saldo']*1;
					$nom_ben = $row['RAZON'];
					$consumo = $row['consumo']*1;
				} else {
					$saldo = 0;
					$nom_ben = '';
					$consumo = 0;
				}

				echo json_encode(
					array(
						'nom_ben' => $nom_ben,
						'saldo' => number_format($saldo, 2),
						'monto' => $saldo,
						'consumo' => $consumo,
					));
				break;

			case 'exportDetconSaldoBonos':
				$sql = "SELECT se.id_empresa, b.nom_empresa, b.dir_empresa, b.tel_empresa,
							se.saldo AS monto, 2 AS consumo,
							CONVERT(VARCHAR(10), GETDATE(), 103) AS fecha_vence,
							se.id_beneficiario, cl.RAZON AS nom_beneficiario, cl.DIRECCION,
							cl.EMAIL, cl.TELEFONO
						FROM (
							SELECT id_empresa, id_beneficiario,
							SUM((CASE WHEN tipo = 1 THEN monto ELSE monto*(-1) END)) AS saldo
							FROM BDES_POS.dbo.DBBonos_H WHERE id_empresa = '$idpara'
							GROUP BY id_empresa, id_beneficiario
							HAVING (SUM((CASE WHEN tipo = 1 THEN monto ELSE monto*(-1) END))>0)) AS se
						INNER JOIN
							(SELECT TOP 1 id_empresa, nom_empresa, dir_empresa, tel_empresa
							FROM BDES_POS.dbo.DBBonos WHERE id_empresa = '$idpara') AS b ON b.id_empresa = se.id_empresa
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = se.id_beneficiario";

				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
					echo json_encode(array('enlace'=>'', 'archivo'=>''));
				} else {
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = $row;
					}
					if(count($datos)>0) {
						require_once "../Classes/PHPExcel.php";
						require_once "../Classes/PHPExcel/Writer/Excel5.php";

						$fecha = date('dmY', strtotime($fecha));

						// Se crea primero el archivo para pagina los montes
						$objPHPExcel = new PHPExcel();
						// Set document properties
						$objPHPExcel->getProperties()->setCreator("Dashboard")
													 ->setLastModifiedBy("Dashboard")
													 ->setTitle("Movimientos para dar de alta id empresa ".$idpara." ".$fecha)
													 ->setSubject("Movimientos para dar de alta id empresa ".$idpara." ".$fecha)
													 ->setDescription("Movimientos para dar de alta id empresa ".$idpara." ".$fecha)
													 ->setKeywords("office 2007 openxml php")
													 ->setCategory("Movimientos para dar de alta id empresa ".$idpara." ".$fecha);

						$objPHPExcel->setActiveSheetIndex(0);
						$rowCount = 1;
						$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, 'id_archivo');
						$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, 'ND'.date('myi'));
						$letra = 65;
						$rowCount++;
						$keys = array_keys($datos[0]);
						foreach ($keys as $key) {
							$objPHPExcel
								->getActiveSheet()
								->SetCellValue(chr($letra).$rowCount, $key);
							$letra++;
						}
						$rowCount++;
						foreach ($datos as $dato) {
							$letra = 65;
							foreach ($dato as $celda) {
								if($letra==69 || $letra==70) {
									$objPHPExcel
										->getActiveSheet()
										->setCellValue(
											chr($letra).$rowCount,
											trim($celda)
										);
								} else {
									$objPHPExcel
										->getActiveSheet()
										->setCellValueExplicit(
											chr($letra).$rowCount,
											trim($celda),
											PHPExcel_Cell_DataType::TYPE_STRING
										);
								}
								$letra++;
							}
							$rowCount++;
						}

						$archivo = 'NDBonos'.$idpara.date('myis');

						// Rename worksheet
						$objPHPExcel->getActiveSheet()->setTitle($archivo);
						// Set active sheet index to the first sheet, so Excel opens this as the first sheet
						$objPHPExcel->setActiveSheetIndex(0);

						// Redirect output to a client’s web browser (Excel5)
						header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
						header('Content-Disposition: attachment;filename="'.$archivo.'.xls"');
						header('Cache-Control: max-age=0');
						// If you're serving to IE 9, then the following may be needed
						header('Cache-Control: max-age=1');

						// If you're serving to IE over SSL, then the following may be needed
						header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
						header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
						header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
						header ('Pragma: public'); // HTTP/1.0

						$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
						$objWriter->save('../tmp/'.$archivo.'.xls');
						echo json_encode(array('enlace'=>'tmp/'.$archivo.'.xls', 'archivo'=>$archivo.'.xls'));
					} else {
						print_r($connec->errorInfo());
						echo json_encode(array('enlace'=>'', 'archivo'=>''));
					}
				}

				break;

			case 'anularMovimientoBonos':
				extract($_POST);
				$sql = "SELECT id, id_empresa, id_beneficiario, fecha, monto, tipo,
							usuario, id_archivo, movimiento, referencia
						FROM BDES_POS.dbo.DBBonos_H WHERE id = $idpara";

				$sql = $connec->query($sql);
				if(!$sql) {
					echo '0¬Error al buscar el movimiento para anular.<br>'.$connec->errorInfo()[2];
				} else {
					$row = $sql->fetch(\PDO::FETCH_OBJ);

					$registro = '';
					foreach($row as $key => $val) {
					    if(is_numeric($val)) {
					    	$registro.= $val.',';
					    } else {
					    	$registro.= "'".$val."',";
					    }
					}

					$registro.="'$justif', '$userid', CURRENT_TIMESTAMP";

					$sql = "INSERT INTO BDES_POS.dbo.DBBonos_H_ANULADO(
								id, id_empresa, id_beneficiario, fecha, monto, tipo,
								usuario, id_archivo, movimiento, referencia, Observacion,
								Anulado_por, fecha_anulado)
							VALUES(".$registro.")";

					$sql = $connec->query($sql);
					if(!$sql) {
						echo '0¬Error no se pudo insertar en Anulados.<br>'.$connec->errorInfo()[2];
					} else {
						$sql = "DELETE FROM BDES_POS.dbo.DBBonos_H WHERE id = $idpara";
						$sql = $connec->query($sql);
						echo '1¬Movimiento anulado Correctamente';
					}
				}
				break;

			case 'actualizar_todo':
				// array de datos para las tablas
				$dt_todos = [];

				// se consultan los datos en la BBDD y se almacena en el array de dt_todos
				dt_ventasxtienda();
				dt_topxclientes();
				dt_topxtipopagos();
				dt_topxproductos();
				dt_topxdepartamento();

				// se retorna el array con los datos para las tablas
				echo json_encode($dt_todos);
				break;

			case 'listaTiendas':
				if($idpara=='') {
					$sql = "SELECT id, nombre FROM tiendas ORDER BY nombre";
				} else {
					// Se extraen los datos de la cadena del parametro para obtener los id's en un array
					$params = explode('¬', $idpara);

					// Se obtiene un listado de las sucursales
					if($params[1]=='*')
						$sql = "SELECT id, nombre FROM tiendas ORDER BY nombre";
					else
						$sql = "SELECT id, nombre FROM tiendas WHERE id = $params[1] ORDER BY nombre";
				}

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'idtienda' => $row['id'],
						'tienda'   => ucwords(strtolower($row['nombre']))
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaModulos':
				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT id, modulo FROM modulos WHERE activo";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'idmodulo' => $row['id'],
						'modulo'   => $row['modulo']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaSubirarchivos':
				$sql = "SELECT texto, opcion, observacion FROM conf_subir_archivos WHERE activo ORDER BY texto";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'texto'  => ucwords(strtolower($row['texto'])),
						'opcion' => $row['opcion'],
						'observacion' => $row['observacion'],
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'lstArticulos':
				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT art.codigo, art.descripcion AS articulo, dpto.descripcion AS dpto
						FROM esarticulos art
						LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE art.activo=B'1'";

				if(is_numeric($idpara)) {
					$sql .= " AND art.codigo = '$idpara'";
				} else {
					$sql .= " AND art.descripcion ILIKE '%$idpara%'";
				}

				$sql .= " GROUP BY dpto.descripcion, art.descripcion, art.codigo";
				$sql .= " ORDER BY dpto.descripcion, art.descripcion";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$cnt = $sql->rowCount();

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'   => $row['codigo'],
						'articulo' => $cnt==1 ? $row['articulo'] :
									  '<button type="button" title="Ver Factura" onclick="' .
										"actualizar_datos('" . $row['codigo'] . "', '" . $row['articulo'] . "')" .
										'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' . $row['articulo'] .
										'</button>',
						'dpto'     => $row['dpto']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaFacturasxCliente':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, codcliente ]
				// $params = [ {10}   , {CC0}      ]
				$params = explode('¬', $idpara);

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT ti.ID, ti.nombre, fp.caja, fp.factura,
							to_char( fp.fecha, 'DD-MM-YYYY' ) AS fecha,
							fp.hora, INITCAP(COALESCE(cl.razon, 'sin registrar')) AS razon,
							fp.cliente, SUM(fp.monto) AS monto,
							(SELECT ROUND(( SUM(subtotal) - SUM(costo)) / SUM(subtotal) * 100, 2 )
								FROM detalle_dia
								WHERE factura = fp.factura AND cliente = fp.cliente AND localidad = ti.ID) AS margen
						FROM
							formaspago_dia fp
							INNER JOIN tiendas ti ON ti.ID = fp.tienda
							LEFT JOIN esclientes cl ON cl.rif = fp.cliente
						WHERE fp.cliente = '$params[1]' AND fp.fecha = '$fecha'";
				if($params[0]!='*') {
					$sql .= " AND fp.tienda = '$params[0]'";
				}
				$sql .= " GROUP BY ti.id, ti.nombre, fp.caja, fp.factura, fp.fecha, fp.hora, cl.razon, fp.cliente";
				$sql .= " ORDER BY fp.hora ";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'idtienda' => $row['id'],
						'tienda'   => ucwords(strtolower($row['nombre'])),
						'caja'     => $row['caja'],
						'factura'  => '<button type="button" title="Ver Factura" onclick="datosFactura(' .
										"'" . $row['id'] . "', '" . $row['caja'] . "', '" . $row['cliente'] .
										"', '" . $row['factura'] . "')" .
										'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' . $row['factura'] .
										'</button>',
						'fecha'    => $row['fecha'],
						'hora'     => $row['hora'],
						'razon'    => $row['razon'],
						'cliente'  => $row['cliente'],
						'monto'    => $row['monto'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'datosFactura':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, pcaja, codcliente, nfactura ]
				// $params = [ {10}   , {52} , {CC0}     , {123456} ]
				$params = explode('¬', $idpara);

				// Se crea el query para obtener el top de productos
				$sql = "SELECT ti.nombre, det.factura, det.caja, det.fecha, det.hora, det.cliente,
							COALESCE(cli.razon, '<em>sin registrar</em>') AS razon, art.descripcion, det.cantidad,
							ROUND(( det.subtotal / det.cantidad ), 2 ) AS precio, cli.direccion, cli.telefono, cli.email,
							det.impuesto, det.total,
							det.subtotal,
							det.costo
						FROM
							detalle_dia det
							RIGHT JOIN tiendas ti ON ti.id = det.localidad
							LEFT JOIN esclientes cli ON cli.rif = det.cliente
							RIGHT JOIN esarticulos art ON art.codigo = det.material
						WHERE
							det.localidad   = '$params[0]'
							AND det.caja    = '$params[1]'
							AND det.cliente = '$params[2]'
							AND det.factura = '$params[3]'
							AND det.fecha   = '$fecha'";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'nombre'      => ucwords(strtolower($row['nombre'])),
						'factura'     => $row['factura'],
						'caja'        => $row['caja'],
						'fecha'       => $row['fecha'],
						'hora'        => $row['hora'],
						'razon'       => $row['razon'],
						'direccion'   => ucwords(strtolower(substr($row['direccion'], 0, 100))),
						'telefono'    => $row['telefono'],
						'email'       => $row['email'],
						'cliente'     => $row['cliente'],
						'descripcion' => $row['descripcion'],
						'cantidad'    => $row['cantidad'],
						'precio'      => $row['precio'],
						'impuesto'    => $row['impuesto'],
						'total'       => $row['total'],
						'subtotal'    => $row['subtotal'],
						'costo'       => $row['costo']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaMaterialxDpto':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, iddpto ]
				// $params = [ {10}   , {18} ]
				$params = explode('¬', $idpara);

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT * FROM (SELECT COALESCE(dpto.descripcion, 'SIN CONFIGURAR') AS dpto, dpto.codigo AS codigodpto,
							det.material AS material, art.descripcion AS articulo,
							SUM(det.cantidad) AS cantidad, SUM(det.subtotal) AS subtotal, SUM(det.costo) AS costo,
							ROUND((SUM(det.subtotal)-SUM(det.costo)) / SUM(det.subtotal) * 100, 2) AS margen
						FROM
							detalle_dia det
							LEFT JOIN esarticulos art ON art.codigo = det.material
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE cantidad != 0
							--AND det.fecha = '$fecha'
							AND det.hora <= '$hora'
							AND art.departamento = '$params[1]'";
				if($params[0]!='*') {
					$sql .= " AND det.localidad = '$params[0]'";
				}

				$sql.= " GROUP BY articulo, dpto, codigodpto, material) articulos WHERE cantidad != 0";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'dpto'     => $row['dpto'],
						'articulo' => '<button type="button" onclick="datosVtasDpto(' .
										"'" . $row['material'] . "', '" . $fecha .
										"', '" . $hora . "', '" . $row['codigodpto'] . "'" . ", '" . $params[0] . "'" .
										')" class="btn btn-sm btn-link m-0 p-0 text-left" style="white-space: normal; line-height: 1;">' .
										$row['articulo'].'</button>',
						'cantidad' => $row['cantidad'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'verDetallevtaArt':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, idart   ]
				// $params = [ 10     , 2025969 ]
				$params = explode('¬', $idpara);

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT ti.nombre AS tienda, det.localidad, COALESCE(dpto.descripcion, 'SIN CONFIGURAR') AS dpto,
							det.material AS material, art.descripcion AS articulo,
							SUM(det.cantidad) AS cantidad, SUM(det.subtotal) AS subtotal, SUM(det.costo) AS costo,
							ROUND((SUM(det.subtotal)-SUM(det.costo)) / SUM(det.subtotal) * 100, 2) AS margen
						FROM
							detalle_dia det
							INNER JOIN tiendas ti ON ti.ID = det.localidad
							LEFT JOIN esarticulos art ON art.codigo = det.material
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE material = '$params[1]'
							--AND det.fecha = '$fecha'
							AND det.hora <= '$hora'";

				if($params[0]!='*') {
					$sql .= " AND det.localidad = '$params[0]'";
				}

				$sql.= " GROUP BY tienda, localidad, articulo, dpto, material";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'tienda'   => '<button type="button" onclick="detVtasArtxTienda(' .
										"'" . $row['localidad'] . "', '" . $fecha . "', '" .
										$hora . "', '" . $params[1] . "', '" . $row['tienda'] . "')" .
										'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold"' .
										' style="white-space: normal; line-height: 1;">' . $row['tienda'] . '</button> ',
						'dpto'     => $row['dpto'],
						'articulo' => $row['articulo'],
						'cantidad' => $row['cantidad']*1,
						'subtotal' => $row['subtotal']*1,
						'costo'    => ($row['costo']/$row['cantidad']),
						'precio'   => ($row['subtotal']/$row['cantidad']),
						'impuesto' => ($row['impuesto']/$row['cantidad']),
						'margen'   => $row['margen']*1,
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'datosVtasDpto':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ pcodigo, pfecha    , phora  , pdpto, ptienda ]
				// $params = [ {10135}, {20190507}, {14:32}, {16} , {10}    ]
				$params = explode('¬', $idpara);

				// Se crea el query para obtener el top de productos
				$sql = "SELECT art.descripcion AS articulo, ti.nombre AS tienda, SUM(det.cantidad) AS cantidad,
						ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) AS margen
						FROM DETALLE_dia AS DET
							INNER JOIN esarticulos AS art ON art.codigo = det.material
							INNER JOIN tiendas AS ti ON ti.id = det.localidad
						WHERE
							det.material = '$params[0]'
							--AND det.fecha = '$params[1]'
							AND det.hora <= '$params[2]'
							AND art.departamento = '$params[3]'";

				if($params[4]!='*') {
					$sql .= " AND det.localidad = '$params[4]'";
				}

				$sql .= " GROUP BY tienda, articulo ORDER BY cantidad DESC";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'articulo' => $row['articulo'],
						'tienda'   => ucwords(strtolower($row['tienda'])),
						'cantidad' => $row['cantidad'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listClientesNRegistra':
				// Se crea el query para obtener el top de productos
				$tabladet = $fecha==date('Y-m-d')?'detalle_dia':'detalle';
				$sql = "SELECT ti.id, ti.nombre AS localidad,
							SUM(cnr) AS clientenr, SUM(cr) AS clienter,
							SUM(subtnr) AS subtnr, SUM(subtr) AS subtr,
							SUM(costonr) AS costonr, SUM(costor) AS costor
							FROM (SELECT localidad, factura, caja,
										SUM(CASE WHEN CHAR_LENGTH(cliente) <  8 THEN (subtotal)/1000 END) AS subtnr,
										SUM(CASE WHEN CHAR_LENGTH(cliente) >= 8 THEN (subtotal)/1000 END) AS subtr,
										SUM(CASE WHEN CHAR_LENGTH(cliente) <  8 THEN (costo)/1000 END) AS costonr,
										SUM(CASE WHEN CHAR_LENGTH(cliente) >= 8 THEN (costo)/1000 END) AS costor,
										MAX(CASE WHEN CHAR_LENGTH(cliente) <  8 AND (subtotal)/1000 > 0 THEN 1 ELSE 0 END) AS cnr,
										MAX(CASE WHEN CHAR_LENGTH(cliente) >= 8 AND (subtotal)/1000 > 0 THEN 1 ELSE 0 END) AS cr
										FROM $tabladet
										WHERE fecha = '$fecha'";
				if($idpara!='*') {
					$sql .= " AND localidad = '$idpara'";
				}

				$sql .= "	GROUP BY localidad, factura, caja) AS det
						INNER JOIN tiendas AS ti ON ti.id = det.localidad
						GROUP BY ti.nombre, ti.id";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				// i para identificar la fila en el datatable al hacer clic en el check
				$i =0;
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$tot_fact = $row['clientenr'] + $row['clienter'];
					$tot_vent = $row['subtnr'] + $row['subtr'];
					$datos[] = [
						'localidad' => '<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
											'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
											'onclick="limpiarfila(' . "'estclientes', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
											'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
											'<button type="button" title="Ver Detalle de Ventas" onclick="listFacturasXHorasCNR(' .
												"'" . $row['id'] . "', '" . $fecha . "', '" . $row['localidad'] . "')" .
												'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
												'style="white-space: normal; line-height: 1;">' . ucwords(strtolower($row['localidad'])) .
											'</button>' .
										'</div>',
						'clientenr' => $row['clientenr']*1,
						'clienter'  => $row['clienter']*1,
						'totfact'   => $tot_fact,
						'pfact_nr'  => round((($row['clientenr'] * 100) / $tot_fact), 2),
						'pfact_r'   => round((($row['clienter'] * 100) / $tot_fact), 2),
						'subtnr'    => $row['subtnr']*1,
						'subtr'     => $row['subtr']*1,
						'totvent'   => $tot_vent,
						'margen_nr' => (is_null($row['subtnr']) || $row['subtnr']==0) ? 0 : (($row['subtnr']-$row['costonr']) / $row['subtnr'])*100,
						'margen_r'  => (is_null($row['subtr']) || $row['subtr']==0) ? 0 : (($row['subtr']-$row['costor']) / $row['subtr'])*100,
						'costo_nr'  => $row['costonr']*1,
						'costo_r'   => $row['costor']*1,
						'fecha'     => date("d-m-Y", strtotime($fecha)),
					];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listFacturasXHorasCNR':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, pnombretienda , pfiltro, pmonto]
				// $params = [ {10}   , 'LIBERTADORES', '>='   , 50000 ]
				$params = explode('¬', $idpara);
				$tabladet = $fecha==date('Y-m-d')?'detalle_dia':'detalle';
				// Se crea el query para obtener el top de productos
				$sql = "SELECT
							EXTRACT('HOUR' FROM hora) AS hora,
							COUNT(factura) AS facturas,
							SUM(subtotal) AS subtotal,
							SUM(total) AS total,
							SUM(costo) AS costo
						FROM
							(SELECT fecha, hora, factura, cliente, caja, localidad,
								(SUM(subtotal)/1000) AS subtotal, (SUM(total)/1000) AS total, (SUM(costo)/1000) AS costo
							FROM $tabladet
							WHERE localidad = '$params[0]' AND fecha = '$fecha'
							GROUP BY fecha, hora, factura, cliente, caja, localidad) AS d
						WHERE subtotal $params[2] $params[3]
						GROUP BY EXTRACT('HOUR' FROM hora)";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'tienda'   => $params[1],
						'localidad'=> $params[0],
						'hora'     => $row['hora'],
						'facturas' => '<button type="button" onclick="listaFacturasxTienda(' .
										"'" . $params[0] . "', '" . $fecha . "', '" . $row['hora'] . "', '" . $params[1] . "')" .
										'" class="btn btn-sm btn-outline-success w-75" style="white-space: normal; line-height: 1;">' .
										number_format($row['facturas']) . '</button> ',
						'canfact'  => $row['facturas'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'total'    => $row['total'],
						'margen'   => $row['subtotal']==0 ? 0 : (($row['subtotal']-$row['costo'])*100)/$row['subtotal']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaFacturasxTienda':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, pfecha      , phora, pnombretienda, pfiltro, pmonto ]
				// $params = [ 10     , '2019-05-28', '11' , 'SEXTA'      , '>='   , 500000 ]
				$params = explode('¬', $idpara);
				$tabladet = $params[1]==date('Y-m-d')?'detalle_dia':'detalle';
				// Se crea el query para obtener el top de productos
				$sql = "SELECT * FROM
							(SELECT fecha, hora, factura, caja, cliente, COALESCE(razon, '<em>sin registrar</em>') AS razon,
								SUM(subtotal) AS subtotal, SUM(costo) AS costo, SUM(total) AS total,
								CAST((CASE WHEN SUM(subtotal)=0 THEN 0
								ELSE ((SUM(subtotal)-SUM(costo))/SUM(subtotal))*100 END) AS NUMERIC(5,2)) AS margen
							FROM $tabladet detalle
								LEFT JOIN esclientes ON esclientes.rif = detalle.cliente
							WHERE fecha='$params[1]' AND
								localidad = $params[0]
							GROUP BY fecha, hora, factura, caja, cliente, razon
							ORDER BY SUM(subtotal) DESC) AS F
						WHERE subtotal $params[4] $params[5]
						AND EXTRACT('HOUR' FROM hora) = $params[2]";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'tienda'   => $params[3],
						'localidad'=> $params[0],
						'fecha'    => $params[1],
						'hora'     => $row['hora'],
						'factura'  => '<button type="button" title="Ver Factura" onclick="datosFactura(' .
										"'" . $params[0] . "', '" . $row['caja'] . "', '" . $row['cliente'] .
										"', '" . $row['factura'] . "', '" . $params[1] . "')" .
										'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' . $row['factura'] .
										'</button>',
						'caja'     => $row['caja'],
						'cliente'  => $row['cliente'],
						'razon'    => $row['razon'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'total'    => $row['total'],
						'margen'   => $row['margen'],
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'detVtasArtxTienda':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, pfecha    , phora, pmat   , pnombretienda ]
				// $params = [ 10     , 2019-05-28, 11:44, 2023794, Pueblo Nuevo  ]
				$params = explode('¬', $idpara);

				// Se crea el query para obtener el nombre del articulo
				$sql = "SELECT descripcion FROM esarticulos WHERE codigo = '$params[3]'";

				$sql = $connec->query($sql);
				$row = $sql->fetch();

				$articulo = $row['descripcion'];

				// Se crea el query para obtener el top de productos
				$sql = "SELECT fecha, hora, factura, caja, cliente, COALESCE(razon, '<em>sin registrar</em>') AS razon,
							SUM(subtotal) AS subtotal, SUM(costo) AS costo, SUM(total) AS total,
							CAST((CASE WHEN SUM(subtotal)=0 THEN 0
							ELSE ((SUM(subtotal)-SUM(costo))/SUM(subtotal))*100 END) AS NUMERIC(5,2)) AS margen
						FROM detalle_dia detalle
							LEFT JOIN esclientes ON esclientes.rif = detalle.cliente
						WHERE fecha='$params[1]'
							AND localidad = '$params[0]'
							AND material = '$params[3]'
							AND hora <= '$params[2]'
						GROUP BY fecha, hora, factura, caja, cliente, razon";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'tienda'   => $params[4],
						'localidad'=> $params[0],
						'fecha'    => $params[1],
						'hora'     => $row['hora'],
						'factura'  => '<button type="button" title="Ver Factura" onclick="datosFactura(' .
										"'" . $params[0] . "', '" . $row['caja'] . "', '" . $row['cliente'] .
										"', '" . $row['factura'] . "', '" . $params[1] . "')" .
										'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' . $row['factura'] .
										'</button>',
						'caja'     => $row['caja'],
						'cliente'  => $row['cliente'],
						'razon'    => $row['razon'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'total'    => $row['total'],
						'margen'   => $row['margen'],
						'articulo' => $articulo,
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listEstadisticasVentas':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, pdpto, afechas             ]
				// $params = [ {*/10} , {16} , '2019-06-05', '2019-05-29', '2019-05-22', '2019-05-15' ]
				$params = explode('¬', $idpara);
				$fechas = explode(',', $params[2]);

				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT id, nombre,
							COALESCE(SUM(canfach), 0) AS canfach, COALESCE(SUM(totalh), 0) AS totalh,
							COALESCE(SUM(cantidadh), 0) AS cantidadh,
							COALESCE(SUM(margenh), 0) AS margenh, COALESCE(SUM(costoh), 0) AS costoh,
							COALESCE(SUM(canfac1), 0) AS canfac1, COALESCE(SUM(total1), 0) AS total1,
							COALESCE(SUM(cantidad1), 0) AS cantidad1,
							COALESCE(SUM(margen1), 0) AS margen1, COALESCE(SUM(costo1), 0) AS costo1,
							COALESCE(SUM(canfac2), 0) AS canfac2, COALESCE(SUM(total2), 0) AS total2,
							COALESCE(SUM(cantidad2), 0) AS cantidad2,
							COALESCE(SUM(margen2), 0) AS margen2, COALESCE(SUM(costo2), 0) AS costo2,
							COALESCE(SUM(canfac3), 0) AS canfac3, COALESCE(SUM(total3), 0) AS total3,
							COALESCE(SUM(cantidad3), 0) AS cantidad3,
							COALESCE(SUM(margen3), 0) AS margen3, COALESCE(SUM(costo3), 0) AS costo3
						FROM (
						SELECT ti.id, ti.nombre,
							(CASE WHEN fecha = '$fechas[3]' THEN COUNT(factura) END) AS canfac3,
							(CASE WHEN fecha = '$fechas[3]' THEN COALESCE(SUM(cantidad), 0) END) AS cantidad3,
							(CASE WHEN fecha = '$fechas[3]' THEN COALESCE(SUM(subtotal), 0) END) AS total3,
							(CASE WHEN fecha = '$fechas[3]' THEN COALESCE(SUM(costo), 0) END) AS costo3,
							(CASE WHEN fecha = '$fechas[3]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
									ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margen3,

							(CASE WHEN fecha = '$fechas[2]' THEN COUNT(factura) END) AS canfac2,
							(CASE WHEN fecha = '$fechas[2]' THEN COALESCE(SUM(cantidad), 0) END) AS cantidad2,
							(CASE WHEN fecha = '$fechas[2]' THEN COALESCE(SUM(subtotal), 0) END) AS total2,
							(CASE WHEN fecha = '$fechas[2]' THEN COALESCE(SUM(costo), 0) END) AS costo2,
							(CASE WHEN fecha = '$fechas[2]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margen2,

							(CASE WHEN fecha = '$fechas[1]' THEN COUNT(factura) END) AS canfac1,
							(CASE WHEN fecha = '$fechas[1]' THEN COALESCE(SUM(cantidad), 0) END) AS cantidad1,
							(CASE WHEN fecha = '$fechas[1]' THEN COALESCE(SUM(subtotal), 0) END) AS total1,
							(CASE WHEN fecha = '$fechas[1]' THEN COALESCE(SUM(costo), 0) END) AS costo1,
							(CASE WHEN fecha = '$fechas[1]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margen1,

							(CASE WHEN fecha = '$fechas[0]' THEN COUNT(factura) END) AS canfach,
							(CASE WHEN fecha = '$fechas[0]' THEN COALESCE(SUM(cantidad), 0) END) AS cantidadh,
							(CASE WHEN fecha = '$fechas[0]' THEN COALESCE(SUM(subtotal), 0) END) AS totalh,
							(CASE WHEN fecha = '$fechas[0]' THEN COALESCE(SUM(costo), 0) END) AS costoh,
							(CASE WHEN fecha = '$fechas[0]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margenh
						FROM
							tiendas AS ti
							LEFT JOIN (
								SELECT fecha, factura, caja, localidad, SUM(subtotal) AS subtotal,
									SUM(costo) AS costo, SUM(cantidad) AS cantidad FROM detalle
								WHERE hora <= '$hora'
								AND fecha BETWEEN CAST('$fechas[3]' AS DATE) AND CAST('$fechas[0]' AS DATE)";

				if($params[1]!='departamento') {
					$sql .= " AND material IN(SELECT codigo FROM esarticulos WHERE departamento = $params[1])";
				}

				$sql .= " GROUP BY factura, caja, localidad, fecha) AS det ON det.localidad = ti.id ";

				if($params[0]!='*') {
					$sql .= " WHERE ti.id = $params[0]";
				}

				$sql .= " GROUP BY ti.ID, ti.nombre, fecha) AS todas GROUP BY todas.id, todas.nombre";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$fecha = date("d-m-Y", strtotime($fecha));
				$i = 0;
				// Se prepara el array para almacenar los datos obtenidos
				$costos = [
					'costoh' => 0,
					'costo1' => 0,
					'costo2' => 0,
					'costo3' => 0,
				];
				$datos  = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($params[1]=='departamento') {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'esttvxtienda', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									ucwords(strtolower($row['nombre'])) .
								'</div>';
					} else {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'esttvxtienda', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									'<button type="button" onclick="materialxDptoEV(' . "'" .
										$row['id'] . "', '" . $params[1] . "', '" .
										ucwords(strtolower($row['nombre'])) . "', '" . $fecha . "', '" .
										$hora . "', '" . $params[2] . "')" . '"' .
										'class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' .
										ucwords(strtolower($row['nombre'])) .
									'</button>' .
								'</div>';
					}
					$datos[] = [
						'tienda'    => $txt,
						'canfac3'   => $row['canfac3'],
						'cantidad3' => $row['cantidad3'],
						'total3'    => $row['total3'],
						'margen3'   => $row['margen3'],
						'canfac2'   => $row['canfac2'],
						'cantidad2' => $row['cantidad2'],
						'total2'    => $row['total2'],
						'margen2'   => $row['margen2'],
						'canfac1'   => $row['canfac1'],
						'cantidad1' => $row['cantidad1'],
						'total1'    => $row['total1'],
						'margen1'   => $row['margen1'],
						'canfach'   => $row['canfach'],
						'cantidadh' => $row['cantidadh'],
						'totalh'    => $row['totalh'],
						'margenh'   => $row['margenh'],
						'fecha'     => $fecha,
						'hora'      => $hora
					];
					$costos['costo3']  = $costos['costo3'] + $row['costo3'];
					$costos['costo2']  = $costos['costo2'] + $row['costo2'];
					$costos['costo1']  = $costos['costo1'] + $row['costo1'];
					$costos['costoh']  = $costos['costoh'] + $row['costoh'];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode(array( 'data' => $datos, 'costos' => $costos ));
				break;

			case 'listEstadisticasSemVentas':
				// Se extraen los datos del parametro a un Array
				$params = explode('¬', $idpara);

				// Se crea el query para obtener los datos de la BBDD
				$sql = "SELECT localidad, nombre,
							SUM(canfac0) AS canfac1, SUM(canfac1) AS canfac2, SUM(canfac2) AS canfac3, SUM(canfac3) AS canfac4,
							SUM(subtotal0) AS subtotal1, SUM(subtotal1) AS subtotal2, SUM(subtotal2) AS subtotal3, SUM(subtotal3) AS subtotal4,
							SUM(costo0) AS costo1, SUM(costo1) AS costo2, SUM(costo2) AS costo3, SUM(costo3) AS costo4
						FROM
							(SELECT localidad, nombre";

				// Se recorre el array de fechas para extraer semana por semana
				foreach ($fecha as $clave => $fechai) {
					// Se crea el query para obtener los totales por tienda
					$sql.=", ";
					$sql.= "(CASE WHEN semana = EXTRACT('WEEK' FROM CAST('$fechai' AS DATE)) THEN canfac END) AS canfac$clave,
							(CASE WHEN semana = EXTRACT('WEEK' FROM CAST('$fechai' AS DATE)) THEN subtotal END) AS subtotal$clave,
							(CASE WHEN semana = EXTRACT('WEEK' FROM CAST('$fechai' AS DATE)) THEN costo END) AS costo$clave";
				}

				$sql.= " FROM (
							SELECT localidad, tiendas.nombre, EXTRACT('WEEK' FROM fecha) AS semana,
							COUNT(DISTINCT factura) AS canfac,
							(SUM(detalle.costo)/1000) AS costo,
							(SUM(subtotal)/1000) AS subtotal
						FROM tiendas
							LEFT OUTER JOIN detalle ON detalle.localidad = tiendas.id
							LEFT OUTER JOIN esarticulos ON esarticulos.codigo = detalle.material
						WHERE
						fecha BETWEEN CAST('$fecha[0]' AS DATE) AND (CAST('$fecha[3]' AS DATE) + CAST('6 days' AS INTERVAL))
						AND hora <= '$hora'";

				if($params[0]!='departamento') {
					$sql .= " AND esarticulos.departamento = '$params[0]'";
				}

				if($params[1]!='*') {
					$sql .= " AND tiendas.id = '$params[1]'";
				}

				$sql.= " GROUP BY localidad, caja, tiendas.nombre, semana) AS todas) AS todas GROUP BY localidad, nombre";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$i = 0;
				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($params[0]=='departamento') {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'estsvxtienda', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									ucwords(strtolower($row['nombre'])) .
								'</div>';
					} else {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'estsvxtienda', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									'<button type="button" onclick="materialxDptoESV(' . "'" .
										$row['localidad'] . "', '" . $params[0] . "', '" .
										ucwords(strtolower($row['nombre'])) . "', '" . $fechai . "', '" . $hora . "')" . '"' .
										'class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' .
										ucwords(strtolower($row['nombre'])) .
									'</button>';
								'</div>';
					}
					$datos[] = [
						'tienda'    => $txt,
						'localidad' => $row['localidad'],
						'canfac1'   => is_null($row['canfac1']) ? 0 : $row['canfac1'],
						'costo1'    => is_null($row['costo1']) ? 0 : $row['costo1'],
						'subtotal1' => (is_null($row['subtotal1']) || $row['subtotal1']==0) ? 0 : $row['subtotal1'],
						'margen1'   => (is_null($row['subtotal1']) || $row['subtotal1']==0) ? 0 : (($row['subtotal1']-$row['costo1'])*100) / $row['subtotal1'],
						'canfac2'   => is_null($row['canfac2']) ? 0 : $row['canfac2'],
						'costo2'    => is_null($row['costo2']) ? 0 : $row['costo2'],
						'subtotal2' => (is_null($row['subtotal2']) || $row['subtotal2']==0) ? 0 : $row['subtotal2'],
						'margen2'   => (is_null($row['subtotal2']) || $row['subtotal2']==0) ? 0 : (($row['subtotal2']-$row['costo2'])*100) / $row['subtotal2'],
						'canfac3'   => is_null($row['canfac3']) ? 0 : $row['canfac3'],
						'costo3'    => is_null($row['costo3']) ? 0 : $row['costo3'],
						'subtotal3' => (is_null($row['subtotal3']) || $row['subtotal3']==0) ? 0 : $row['subtotal3'],
						'margen3'   => (is_null($row['subtotal3']) || $row['subtotal3']==0) ? 0 : (($row['subtotal3']-$row['costo3'])*100) / $row['subtotal3'],
						'canfac4'   => is_null($row['canfac4']) ? 0 : $row['canfac4'],
						'costo4'    => is_null($row['costo4']) ? 0 : $row['costo4'],
						'subtotal4' => (is_null($row['subtotal4']) || $row['subtotal4']==0) ? 0 : $row['subtotal4'],
						'margen4'   => (is_null($row['subtotal4']) || $row['subtotal4']==0) ? 0 : (($row['subtotal4']-$row['costo4'])*100) / $row['subtotal4'],
					];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listEstadisticasMesVentas':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ pdpto, afechas0  , afecha1   , afechas2  , afechas3  , diaini, diafin, ptienda ]
				// $params = [ 16   , 2019-03-01, 2019-04-04, 2019-05-04, 2019-06-30, 1     , 5     , 10      ]
				$params = explode('¬', $idpara);

				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT id, nombre,
							COALESCE(SUM(canfac1), 0) AS canfac1, COALESCE(SUM(total1), 0) AS total1, COALESCE(SUM(costo1), 0) AS costo1,
							(CASE WHEN SUM(total1) = 0 THEN 0 ELSE COALESCE(((SUM(total1)-SUM(costo1))/SUM(total1))*100, 0) END) AS margen1,
							COALESCE(SUM(canfac2), 0) AS canfac2, COALESCE(SUM(total2), 0) AS total2, COALESCE(SUM(costo2), 0) AS costo2,
							(CASE WHEN SUM(total2) = 0 THEN 0 ELSE COALESCE(((SUM(total2)-SUM(costo2))/SUM(total2))*100, 0) END) AS margen2,
							COALESCE(SUM(canfac3), 0) AS canfac3, COALESCE(SUM(total3), 0) AS total3, COALESCE(SUM(costo3), 0) AS costo3,
							(CASE WHEN SUM(total3) = 0 THEN 0 ELSE COALESCE(((SUM(total3)-SUM(costo3))/SUM(total3))*100, 0) END) AS margen3,
							COALESCE(SUM(canfach), 0) AS canfach, COALESCE(SUM(totalh), 0) AS totalh, COALESCE(SUM(costoh), 0) AS costoh,
							(CASE WHEN SUM(totalH) = 0 THEN 0 ELSE COALESCE(((SUM(totalH)-SUM(costoh))/SUM(totalh))*100, 0) END) AS margenh
						FROM (
							SELECT ti.id, ti.nombre,
								(CASE WHEN mes  = EXTRACT(MONTH FROM (CAST('$params[1]' AS DATE))) THEN COUNT(factura) END) AS canfac1,
								(CASE WHEN mes  = EXTRACT(MONTH FROM (CAST('$params[1]' AS DATE))) THEN COALESCE(SUM(subtotal), 0) END) AS total1,
								(CASE WHEN mes  = EXTRACT(MONTH FROM (CAST('$params[1]' AS DATE))) THEN COALESCE(SUM(costo), 0) END) AS costo1,

								(CASE WHEN mes = EXTRACT(MONTH FROM (CAST('$params[2]' AS DATE))) THEN COUNT(factura) END) AS canfac2,
								(CASE WHEN mes = EXTRACT(MONTH FROM (CAST('$params[2]' AS DATE))) THEN COALESCE(SUM(subtotal), 0) END) AS total2,
								(CASE WHEN mes = EXTRACT(MONTH FROM (CAST('$params[2]' AS DATE))) THEN COALESCE(SUM(costo), 0) END) AS costo2,

								(CASE WHEN mes = EXTRACT(MONTH FROM (CAST('$params[3]' AS DATE))) THEN COUNT(factura) END) AS canfac3,
								(CASE WHEN mes = EXTRACT(MONTH FROM (CAST('$params[3]' AS DATE))) THEN COALESCE(SUM(subtotal), 0) END) AS total3,
								(CASE WHEN mes = EXTRACT(MONTH FROM (CAST('$params[3]' AS DATE))) THEN COALESCE(SUM(costo), 0) END) AS costo3,

								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$params[4]' AS DATE)) THEN COUNT(factura) END) AS canfach,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$params[4]' AS DATE)) THEN COALESCE(SUM(subtotal), 0) END) AS totalh,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$params[4]' AS DATE)) THEN COALESCE(SUM(costo), 0) END) AS costoh
							FROM
								tiendas AS ti
								LEFT JOIN (
									SELECT EXTRACT(MONTH FROM fecha) AS mes, factura, caja,
										localidad, (SUM(subtotal)/1000) AS subtotal, (SUM(costo)/1000) AS costo
									FROM detalle
									WHERE fecha BETWEEN CAST('$params[1]' AS DATE) AND CAST('$params[4]' AS DATE)
									AND hora <= '$hora'";
				if($params[5]!='0' && $params[6]!='0') {
					$sql .= " AND EXTRACT('DAY' FROM fecha) BETWEEN $params[5] AND $params[6]";
				}
				if($params[0]!='departamento') {
					$sql .= " AND material IN(SELECT codigo FROM esarticulos WHERE departamento = '$params[0]')";
				}

				$sql .= " GROUP BY factura, caja, localidad, mes) AS det ON det.localidad = ti.id";

				if($params[7]!='*') {
					$sql .= " WHERE ti.id = $params[7]";
				}
				$sql .= " GROUP BY ti.ID, ti.nombre, mes) AS todas GROUP BY todas.id, todas.nombre";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$i = 0;
				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($params[0]=='departamento') {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'estmvxtienda', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									ucwords(strtolower($row['nombre'])) .
								'</div>';
					} else {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'estmvxtienda', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									'<button type="button" onclick="materialxDptoEMV(' . "'" .
										$row['id'] . "', '" . $params[0] . "', '" .
										ucwords(strtolower($row['nombre'])) . "', '" . $params[4] . "', '" . $hora . "', '" . $params[5] . "', '" . $params[6] . "')" . '"' .
										'class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' .
										ucwords(strtolower($row['nombre'])) .
									'</button>';
								'</div>';
					}
					$datos[] = [
						'tienda'  => $txt,
						'canfac3' => $row['canfac3'],
						'total3'  => $row['total3'],
						'costo3'  => $row['costo3'],
						'margen3' => $row['margen3'],
						'canfac2' => $row['canfac2'],
						'total2'  => $row['total2'],
						'costo2'  => $row['costo2'],
						'margen2' => $row['margen2'],
						'canfac1' => $row['canfac1'],
						'total1'  => $row['total1'],
						'costo1'  => $row['costo1'],
						'margen1' => $row['margen1'],
						'canfach' => $row['canfach'],
						'totalh'  => $row['totalh'],
						'costoh'  => $row['costoh'],
						'margenh' => $row['margenh'],
						'fecha'   => $fecha,
						'hora'    => $hora
					];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listEstadisticasVtasDpto':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, pnomtienda, afechas                                               ]
				// $params = [ {*/10} , {SAN LUIS}, '2019-06-05', '2019-05-29', '2019-05-22', '2019-05-15']
				$params = explode('¬', $idpara);
				$fechas = explode(',', $params[2]);

				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT id_dpto, nom_dpto,
							COALESCE(SUM(canfach), 0) AS canfach, COALESCE(SUM(totalh), 0) AS totalh, COALESCE(SUM(costoh), 0) AS costoh,
							(CASE WHEN COALESCE(SUM(totalh), 0) = 0 THEN 0 ELSE ROUND(((SUM(totalh)-SUM(costoh))/SUM(totalh))*100, 2) END) AS margenh,
							COALESCE(SUM(canfac1), 0) AS canfac1, COALESCE(SUM(total1), 0) AS total1, COALESCE(SUM(costo1), 0) AS costo1,
							(CASE WHEN COALESCE(SUM(total1), 0) = 0 THEN 0 ELSE ROUND(((SUM(total1)-SUM(costo1))/SUM(total1))*100, 2) END) AS margen1,
							COALESCE(SUM(canfac2), 0) AS canfac2, COALESCE(SUM(total2), 0) AS total2, COALESCE(SUM(costo2), 0) AS costo2,
							(CASE WHEN COALESCE(SUM(total2), 0) = 0 THEN 0 ELSE ROUND(((SUM(total2)-SUM(costo2))/SUM(total2))*100, 2) END) AS margen2,
							COALESCE(SUM(canfac3), 0) AS canfac3, COALESCE(SUM(total3), 0) AS total3, COALESCE(SUM(costo3), 0) AS costo3,
							(CASE WHEN COALESCE(SUM(total3), 0) = 0 THEN 0 ELSE ROUND(((SUM(total3)-SUM(costo3))/SUM(total3))*100, 2) END) AS margen3
						FROM (
						SELECT det.id_dpto, det.nom_dpto,
							(CASE WHEN fecha = '$fechas[3]' THEN COUNT(factura) END) AS canfac3,
							(CASE WHEN fecha = '$fechas[3]' THEN COALESCE(SUM(subtotal), 0) END) AS total3,
							(CASE WHEN fecha = '$fechas[3]' THEN COALESCE(SUM(costo), 0) END) AS costo3,

							(CASE WHEN fecha = '$fechas[2]' THEN COUNT(factura) END) AS canfac2,
							(CASE WHEN fecha = '$fechas[2]' THEN COALESCE(SUM(subtotal), 0) END) AS total2,
							(CASE WHEN fecha = '$fechas[2]' THEN COALESCE(SUM(costo), 0) END) AS costo2,

							(CASE WHEN fecha = '$fechas[1]' THEN COUNT(factura) END) AS canfac1,
							(CASE WHEN fecha = '$fechas[1]' THEN COALESCE(SUM(subtotal), 0) END) AS total1,
							(CASE WHEN fecha = '$fechas[1]' THEN COALESCE(SUM(costo), 0) END) AS costo1,

							(CASE WHEN fecha = '$fechas[0]' THEN COUNT(factura) END) AS canfach,
							(CASE WHEN fecha = '$fechas[0]' THEN COALESCE(SUM(subtotal), 0) END) AS totalh,
							(CASE WHEN fecha = '$fechas[0]' THEN COALESCE(SUM(costo), 0) END) AS costoh
						FROM
							(SELECT d1.fecha, d1.factura, d1.caja, (d1.subtotal/1000) AS subtotal, (d1.costo/1000) AS costo, COALESCE(e1.departamento, 00) AS id_dpto,
								(CASE WHEN dp1.descripcion ILIKE '%no usar%' THEN 'SIN REGISTRAR'
								WHEN LENGTH(dp1.descripcion)<=3 THEN 'SIN REGISTRAR'
								WHEN dp1.descripcion IS NULL THEN 'SIN REGISTRAR'
								ELSE dp1.descripcion END) AS nom_dpto
							FROM
								detalle d1
								LEFT JOIN esarticulos e1 ON e1.codigo = d1.material
								LEFT JOIN esdpto dp1 ON dp1.codigo = e1.departamento
							WHERE d1.hora <= '$hora'";

				if($params[0]!='localidad') {
					$sql .= " AND d1.localidad = '$params[0]'";
				}

				$sql .= " AND d1.fecha BETWEEN CAST('$fechas[3]' AS DATE) AND CAST('$fechas[0]' AS DATE)) AS det
						 GROUP BY det.id_dpto, det.nom_dpto, det.fecha, det.caja) AS todos GROUP BY todos.id_dpto, todos.nom_dpto";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$fecha = date("d-m-Y", strtotime($fecha));
				$i = 0;
				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($params[0]=='localidad') {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tbl_dia_ventas_dpto', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									ucwords(strtolower($row['nom_dpto'])) .
								'</div>';
					} else {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tbl_dia_ventas_dpto', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									'<button type="button" onclick="materialxDptoEV(' . "'" .
										$params[0] . "', '" . $row['id_dpto'] . "', '" .
										$params[1] . "', '" . $fecha . "', '" .
										$hora . "', '" . $params[2] . "')" . '"' .
										'class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' .
										ucwords(strtolower($row['nom_dpto'])) .
									'</button>' .
								'</div>';
					}
					$datos[] = [
						'dpto'    => $txt,
						'canfac3' => $row['canfac3'],
						'total3'  => $row['total3'],
						'costo3'  => $row['costo3'],
						'margen3' => $row['margen3'],
						'canfac2' => $row['canfac2'],
						'total2'  => $row['total2'],
						'costo2'  => $row['costo2'],
						'margen2' => $row['margen2'],
						'canfac1' => $row['canfac1'],
						'total1'  => $row['total1'],
						'costo1'  => $row['costo1'],
						'margen1' => $row['margen1'],
						'canfach' => $row['canfach'],
						'totalh'  => $row['totalh'],
						'costoh'  => $row['costoh'],
						'margenh' => $row['margenh'],
						'fecha'   => $fecha,
						'hora'    => $hora
					];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listEstadisticasSemVtasDpto':
				// Se crea el query para obtener los datos de la BBDD
				$sql = "SELECT id_dpto, nom_dpto,
							SUM(canfac0) AS canfac1, SUM(canfac1) AS canfac2, SUM(canfac2) AS canfac3, SUM(canfac3) AS canfac4,
							SUM(subtotal0) AS subtotal1, SUM(subtotal1) AS subtotal2, SUM(subtotal2) AS subtotal3, SUM(subtotal3) AS subtotal4,
							SUM(costo0) AS costo1, SUM(costo1) AS costo2, SUM(costo2) AS costo3, SUM(costo3) AS costo4
						FROM
							(SELECT id_dpto, nom_dpto";

				// Se recorre el array de fechas para extraer semana por semana
				foreach ($fecha as $clave => $fechai) {
					// Se crea el query para obtener los totales por tienda
					$sql.=", ";
					$sql.= "(CASE WHEN semana = EXTRACT('WEEK' FROM CAST('$fechai' AS DATE)) THEN COUNT(DISTINCT factura) END) AS canfac$clave,
							(CASE WHEN semana = EXTRACT('WEEK' FROM CAST('$fechai' AS DATE)) THEN SUM(subtotal) END) AS subtotal$clave,
							(CASE WHEN semana = EXTRACT('WEEK' FROM CAST('$fechai' AS DATE)) THEN SUM(costo) END) AS costo$clave";
				}

				$sql.= " FROM
							(SELECT EXTRACT('WEEK' FROM d1.fecha) semana, d1.factura, d1.caja, (d1.subtotal/1000) AS subtotal,
								(d1.costo/1000) AS costo, COALESCE(e1.departamento, 0) AS id_dpto,
								(CASE WHEN dp1.descripcion ILIKE '%no usar%' THEN 'SIN REGISTRAR'
								WHEN LENGTH(dp1.descripcion)<=3 THEN 'SIN REGISTRAR'
								WHEN dp1.descripcion IS NULL THEN 'SIN REGISTRAR'
								ELSE dp1.descripcion END) AS nom_dpto
							FROM
								detalle d1
								LEFT JOIN esarticulos e1 ON e1.codigo = d1.material
								LEFT JOIN esdpto dp1 ON dp1.codigo = e1.departamento
							WHERE
							d1.fecha BETWEEN CAST('$fecha[0]' AS DATE) AND (CAST('$fecha[3]' AS DATE) + CAST('6 days' AS INTERVAL))
							AND d1.hora <= '$hora'";

				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}

				$sql.= " ) AS det GROUP BY det.id_dpto, det.nom_dpto, det.semana, det.caja) AS todos GROUP BY id_dpto, nom_dpto";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$i = 0;
				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($idpara=='localidad') {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tbl_sem_ventas_dpto', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									ucwords(strtolower($row['nom_dpto'])) .
								'</div>';
					} else {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tbl_sem_ventas_dpto', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									'<button type="button" onclick="materialxDptoESV(' . "'" .
										($idpara=='localidad' ? '*' : $idpara) . "', '" . $row['id_dpto'] . "', '" .
										ucwords(strtolower($row['nom_dpto'])) . "', '" . $fechai . "')" . '"' .
										'class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' .
										ucwords(strtolower($row['nom_dpto'])) .
									'</button>';
								'</div>';
					}
					$datos[] = [
						'dpto'      => $txt,
						'localidad' => $idpara=='localidad' ? '*' : $idpara,
						'canfac1'   => is_null($row['canfac1']) ? 0 : $row['canfac1'],
						'costo1'    => is_null($row['costo1']) ? 0 : $row['costo1'],
						'subtotal1' => (is_null($row['subtotal1']) || $row['subtotal1']==0) ? 0 : $row['subtotal1'],
						'margen1'   => (is_null($row['subtotal1']) || $row['subtotal1']==0) ? 0 : (($row['subtotal1']-$row['costo1']) / $row['subtotal1'])*100,
						'canfac2'   => is_null($row['canfac2']) ? 0 : $row['canfac2'],
						'costo2'    => is_null($row['costo2']) ? 0 : $row['costo2'],
						'subtotal2' => (is_null($row['subtotal2']) || $row['subtotal2']==0) ? 0 : $row['subtotal2'],
						'margen2'   => (is_null($row['subtotal2']) || $row['subtotal2']==0) ? 0 : (($row['subtotal2']-$row['costo2']) / $row['subtotal2'])*100,
						'canfac3'   => is_null($row['canfac3']) ? 0 : $row['canfac3'],
						'costo3'    => is_null($row['costo3']) ? 0 : $row['costo3'],
						'subtotal3' => (is_null($row['subtotal3']) || $row['subtotal3']==0) ? 0 : $row['subtotal3'],
						'margen3'   => (is_null($row['subtotal3']) || $row['subtotal3']==0) ? 0 : (($row['subtotal3']-$row['costo3']) / $row['subtotal3'])*100,
						'canfac4'   => is_null($row['canfac4']) ? 0 : $row['canfac4'],
						'costo4'    => is_null($row['costo4']) ? 0 : $row['costo4'],
						'subtotal4' => (is_null($row['subtotal4']) || $row['subtotal4']==0) ? 0 : $row['subtotal4'],
						'margen4'   => (is_null($row['subtotal4']) || $row['subtotal4']==0) ? 0 : (($row['subtotal4']-$row['costo4']) / $row['subtotal4'])*100,
					];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listEstadisticasMesVtasDpto':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda    ,  afechas[0]  afechas[1]  afechas[2]  afechas[3] , diaini, diafin, pnomtienda ]
				// $params = [ 1/localidad, [2019-03-01, 2019-04-04, 2019-05-04, 2019-06-30], 1     , 5     , {SEXTA   } ]
				$params = explode('¬', $idpara);
				$fechas = explode(',', $params[1]);

				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT id_dpto, nom_dpto,
							SUM(canfac1) AS canfac1, SUM(total1) AS total1, SUM(costo1) AS costo1,
							(CASE WHEN SUM(total1) = 0 THEN 0 ELSE ((SUM(total1)-SUM(costo1))/SUM(total1))*100 END) AS margen1,
							SUM(canfac2) AS canfac2, SUM(total2) AS total2, SUM(costo2) AS costo2,
							(CASE WHEN SUM(total2) = 0 THEN 0 ELSE ((SUM(total2)-SUM(costo2))/SUM(total2))*100 END) AS margen2,
							SUM(canfac3) AS canfac3, SUM(total3) AS total3, SUM(costo3) AS costo3,
							(CASE WHEN SUM(total3) = 0 THEN 0 ELSE ((SUM(total3)-SUM(costo3))/SUM(total3))*100 END) AS margen3,
							SUM(canfach) AS canfach, SUM(totalh) AS totalh, SUM(costoh) AS costoh,
							(CASE WHEN SUM(totalH) = 0 THEN 0 ELSE ((SUM(totalH)-SUM(costoh))/SUM(totalh))*100 END) AS margenh
						FROM (
							SELECT id_dpto, nom_dpto,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[0]' AS DATE)) THEN COUNT(DISTINCT canfact) END) AS canfac1,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[0]' AS DATE)) THEN SUM(subtotal) END) AS total1,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[0]' AS DATE)) THEN SUM(costo) END) AS costo1,

								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[1]' AS DATE)) THEN COUNT(DISTINCT canfact) END) AS canfac2,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[1]' AS DATE)) THEN SUM(subtotal) END) AS total2,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[1]' AS DATE)) THEN SUM(costo) END) AS costo2,

								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[2]' AS DATE)) THEN COUNT(DISTINCT canfact) END) AS canfac3,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[2]' AS DATE)) THEN SUM(subtotal) END) AS total3,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[2]' AS DATE)) THEN SUM(costo) END) AS costo3,

								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[3]' AS DATE)) THEN COUNT(DISTINCT canfact) END) AS canfach,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[3]' AS DATE)) THEN SUM(subtotal) END) AS totalh,
								(CASE WHEN mes = EXTRACT(MONTH FROM CAST('$fechas[3]' AS DATE)) THEN SUM(costo) END) AS costoh
							FROM
								(SELECT EXTRACT(MONTH FROM d1.fecha) mes, d1.factura AS canfact, (d1.subtotal/1000) AS subtotal,
									(d1.costo/1000) AS costo, COALESCE(e1.departamento, 0) AS id_dpto, d1.caja AS caja,
									(CASE WHEN dp1.descripcion ILIKE '%no usar%' THEN 'SIN REGISTRAR'
										WHEN LENGTH(dp1.descripcion)<=3 THEN 'SIN REGISTRAR'
										WHEN dp1.descripcion IS NULL THEN 'SIN REGISTRAR'
										ELSE dp1.descripcion END) AS nom_dpto
								FROM
									detalle d1
									LEFT JOIN esarticulos e1 ON e1.codigo = d1.material
									LEFT JOIN esdpto dp1 ON dp1.codigo = e1.departamento
								WHERE d1.fecha BETWEEN CAST('$fechas[0]' AS DATE) AND CAST('$fechas[3]' AS DATE)
								AND d1.hora <= '$hora'";

				if($params[0]!='localidad') {
					$sql .= " AND localidad = $params[0]";
				}

				if($params[2]!='0' && $params[3]!='0') {
					$sql .= " AND EXTRACT('DAY' FROM fecha) BETWEEN $params[2] AND $params[3]";
				}

				$sql .= " ) AS det GROUP BY det.id_dpto, det.nom_dpto, det.mes, det.caja) AS todos GROUP BY id_dpto, nom_dpto";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$i = 0;
				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($params[0]=='localidad') {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tbl_mes_ventas_dpto', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									ucwords(strtolower($row['nom_dpto'])) .
								'</div>';
					} else {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tbl_mes_ventas_dpto', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									'<button type="button" onclick="materialxDptoEMV(' . "'" .
										($params[0]=='localidad' ? '*' : $params[0]) . "', '" . $row['id_dpto'] . "', '" .
										$params[4] . "', '" . $fechas[3] . "', '" . $hora . "', '" . $params[2] . "', '" . $params[3] . "')" . '"' .
										'class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' .
										ucwords(strtolower($row['nom_dpto'])) .
									'</button>';
								'</div>';
					}
					$datos[] = [
						'dpto'  => $txt,
						'canfac3' => is_null($row['canfac3']) ? 0 : $row['canfac3'],
						'total3'  => is_null($row['total3']) ? 0 : $row['total3'],
						'costo3'  => is_null($row['costo3']) ? 0 : $row['costo3'],
						'margen3' => is_null($row['margen3']) ? 0 : $row['margen3'],
						'canfac2' => is_null($row['canfac2']) ? 0 : $row['canfac2'],
						'total2'  => is_null($row['total2']) ? 0 : $row['total2'],
						'costo2'  => is_null($row['costo2']) ? 0 : $row['costo2'],
						'margen2' => is_null($row['margen2']) ? 0 : $row['margen2'],
						'canfac1' => is_null($row['canfac1']) ? 0 : $row['canfac1'],
						'total1'  => is_null($row['total1']) ? 0 : $row['total1'],
						'costo1'  => is_null($row['costo1']) ? 0 : $row['costo1'],
						'margen1' => is_null($row['margen1']) ? 0 : $row['margen1'],
						'canfach' => is_null($row['canfach']) ? 0 : $row['canfach'],
						'totalh'  => is_null($row['totalh']) ? 0 : $row['totalh'],
						'costoh'  => is_null($row['costoh']) ? 0 : $row['costoh'],
						'margenh' => is_null($row['margenh']) ? 0 : $row['margenh'],
						'fecha'   => $fecha,
						'hora'    => $hora
					];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listEstadisticasVtasPromo':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $idpara {0} ptienda id de la tienda en consulta, * si son todas
				// $idpara {1} pmat codigo de material a consultar
				// $idpara {2} ppromo id de la promocion que se desea consultar
				// $idpara {3} pdpto id del departamento a consultar
				$params = explode('¬', $idpara);

				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT id, nombre,
							COALESCE(SUM(canfac2), 0) AS canfac2, COALESCE(SUM(cant2), 0) AS cant2, COALESCE(SUM(total2), 0) AS total2, COALESCE(SUM(costo2), 0) AS costo2,
							COALESCE(CASE WHEN SUM(total2) = 0 THEN 0 ELSE ROUND((SUM(total2) - SUM(costo2)) / SUM(total2) * 100, 2) END, 0) AS margen2,
							COALESCE(SUM(canfac1), 0) AS canfac1, COALESCE(SUM(cant1), 0) AS cant1, COALESCE(SUM(total1), 0) AS total1, COALESCE(SUM(costo1), 0) AS costo1,
							COALESCE(CASE WHEN SUM(total1) = 0 THEN 0 ELSE ROUND((SUM(total1) - SUM(costo1)) / SUM(total1) * 100, 2) END, 0) AS margen1,
							COALESCE(SUM(canfach), 0) AS canfach, COALESCE(SUM(canth), 0) AS canth, COALESCE(SUM(totalh), 0) AS totalh, COALESCE(SUM(costoh), 0) AS costoh,
							COALESCE(CASE WHEN SUM(totalh) = 0 THEN 0 ELSE ROUND((SUM(totalh) - SUM(costoh)) / SUM(totalh) * 100, 2) END, 0) AS margenh
						FROM (
						SELECT ti.id, ti.nombre,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN COUNT(factura) END) AS canfac2,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN COALESCE(SUM(subtotal), 0) END) AS total2,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN COALESCE(SUM(costo), 0) END) AS costo2,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN COALESCE(SUM(cantidad), 0) END) as cant2,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margen2,

							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN COUNT(factura) END) AS canfac1,
							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN COALESCE(SUM(subtotal), 0) END) AS total1,
							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN COALESCE(SUM(costo), 0) END) AS costo1,
							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN COALESCE(SUM(cantidad), 0) END) as cant1,
							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margen1,

							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN COUNT(factura) END) AS canfach,
							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN COALESCE(SUM(subtotal), 0) END) AS totalh,
							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN COALESCE(SUM(costo), 0) END) AS costoh,
							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN COALESCE(SUM(cantidad), 0) END) as canth,
							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margenh
						FROM
							tiendas AS ti
							LEFT JOIN (
								SELECT fecha, factura, caja, localidad, SUM(cantidad) AS cantidad, SUM(subtotal)/1000 AS subtotal, SUM(costo)/1000 AS costo
								FROM detalle
								WHERE hora <= '$hora'
								AND fecha BETWEEN CAST('$fecha[0]' AS DATE) AND CAST('$fecha[5]' AS DATE)";

				if($params[3]!='departamento') {
					$sql .= " AND material IN(SELECT codigo FROM esarticulos WHERE departamento = $params[3])";
				}

				if($params[2]!='promocion') {
					$sql .= " AND promocion = $params[2]";
				}

				if($params[1]!='material') {
					$sql .= " AND material = $params[1]";
				}

				$sql .= " GROUP BY factura, caja, localidad, fecha) AS det ON det.localidad = ti.id ";

				if($params[0]!='*') {
					$sql .= " WHERE ti.id = $params[0]";
				}

				$sql .= " GROUP BY ti.ID, ti.nombre, fecha) AS todas GROUP BY todas.id, todas.nombre";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$fechas = implode(",", $fecha);
				$i = 0;
				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($params[1]=='departamento') {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tabla_datos', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									ucwords(strtolower($row['nombre'])) .
								'</div>';
					} else {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tabla_datos', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									'<button type="button" onclick="materialxDptoESVPromo(' . "'" .
										$row['id'] . "', '" . $params[3] . "', '" . ucwords(strtolower($row['nombre'])) . "', '" .
										$hora . "', '" . $fechas . "', '" . $params[1] . "', '" . $params[2] . "')" . '"' .
										'class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' .
										ucwords(strtolower($row['nombre'])) .
									'</button>' .
								'</div>';
					}
					$datos[] = [
						'tienda'  => $txt,
						'canfac2' => $row['canfac2'],
						'cant2'   => $row['cant2'],
						'total2'  => $row['total2'],
						'costo2'  => $row['costo2'],
						'margen2' => $row['margen2'],
						'canfac1' => $row['canfac1'],
						'cant1'   => $row['cant1'],
						'total1'  => $row['total1'],
						'costo1'  => $row['costo1'],
						'margen1' => $row['margen1'],
						'canfach' => $row['canfach'],
						'canth'   => $row['canth'],
						'totalh'  => $row['totalh'],
						'costoh'  => $row['costoh'],
						'margenh' => $row['margenh'],
						'fecha'   => $fecha[5],
						'hora'    => $hora
					];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listVtasArticulos':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $idpara pmat codigo de material a consultar
				$sql = "SELECT codigo, descripcion FROM esarticulos WHERE codigo = $idpara";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$articulo = $sql->fetch();

				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT id, nombre,
							COALESCE(SUM(canfac2), 0) AS canfac2, COALESCE(SUM(cant2), 0) AS cant2,
							COALESCE(SUM(total2), 0) AS total2, COALESCE(SUM(costo2), 0) AS costo2,
							COALESCE(CASE WHEN SUM(total2) = 0 THEN 0 ELSE
								ROUND((SUM(total2) - SUM(costo2)) / SUM(total2) * 100, 2) END, 0) AS margen2,
							COALESCE(SUM(canfac1), 0) AS canfac1, COALESCE(SUM(cant1), 0) AS cant1,
							COALESCE(SUM(total1), 0) AS total1, COALESCE(SUM(costo1), 0) AS costo1,
							COALESCE(CASE WHEN SUM(total1) = 0 THEN 0 ELSE
								ROUND((SUM(total1) - SUM(costo1)) / SUM(total1) * 100, 2) END, 0) AS margen1,
							COALESCE(SUM(canfach), 0) AS canfach, COALESCE(SUM(canth), 0) AS canth,
							COALESCE(SUM(totalh), 0) AS totalh, COALESCE(SUM(costoh), 0) AS costoh,
							COALESCE(CASE WHEN SUM(totalh) = 0 THEN 0 ELSE
								ROUND((SUM(totalh) - SUM(costoh)) / SUM(totalh) * 100, 2) END, 0) AS margenh
						FROM (
						SELECT ti.id, ti.nombre,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN COUNT(factura) END) AS canfac2,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN COALESCE(SUM(subtotal), 0) END) AS total2,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN COALESCE(SUM(costo), 0) END) AS costo2,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN COALESCE(SUM(cantidad), 0) END) as cant2,
							(CASE WHEN fecha BETWEEN '$fecha[0]' AND '$fecha[1]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margen2,

							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN COUNT(factura) END) AS canfac1,
							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN COALESCE(SUM(subtotal), 0) END) AS total1,
							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN COALESCE(SUM(costo), 0) END) AS costo1,
							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN COALESCE(SUM(cantidad), 0) END) as cant1,
							(CASE WHEN fecha BETWEEN '$fecha[2]' AND '$fecha[3]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margen1,

							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN COUNT(factura) END) AS canfach,
							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN COALESCE(SUM(subtotal), 0) END) AS totalh,
							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN COALESCE(SUM(costo), 0) END) AS costoh,
							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN COALESCE(SUM(cantidad), 0) END) as canth,
							(CASE WHEN fecha BETWEEN '$fecha[4]' AND '$fecha[5]' THEN
								(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
								ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2) END)
							END) AS margenh
						FROM
							tiendas AS ti
							LEFT JOIN (
								SELECT fecha, factura, caja, localidad, SUM(cantidad) AS cantidad,
									SUM(subtotal) AS subtotal, SUM(costo) AS costo
								FROM detalle
								WHERE hora <= '$hora'
								AND fecha BETWEEN CAST('$fecha[0]' AS DATE) AND CAST('$fecha[5]' AS DATE)
								AND material = $idpara";

				$sql .= " GROUP BY factura, caja, localidad, fecha) AS det ON det.localidad = ti.id
						  GROUP BY ti.ID, ti.nombre, fecha) AS todas GROUP BY todas.id, todas.nombre";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$fechas = implode(",", $fecha);
				$i = 0;
				// Se prepara el array para almacenar los datos obtenidos
				$costos = [
					'costoh' => 0,
					'costo1' => 0,
					'costo2' => 0,
				];
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if(($row['canfac2']+$row['canfac1']+$row['canfach'])>0) {
						$txt = 	'<div class="custom-control custom-checkbox my-0 py-0" style="line-height: 1;">' .
									'<input type="checkbox" class="custom-control-input dt-check" checked style="cursor: pointer;" '.
									'onclick="limpiarfila(' . "'tabla_datos', " . $i .', $(this).prop(' . "'checked'" . ') )" id="c' . $i . '">' .
									'<label class="custom-control-label font-weight-normal" style="cursor: pointer;" for="c' . $i . '"></label>' .
									'<button type="button" onclick="listaFacturasxTiendaxArticulo(' . "'" .
										$row['id'] . "', '" . $fechas . "', '" . $hora . "', " . $idpara . ", '" .
										ucwords(strtolower($row['nombre'])) . "')" . '"' .
										'class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' .
										ucwords(strtolower($row['nombre'])) .
									'</button>' .
								'</div>';
					} else {
						$txt = '<div class="text-secondary">' . ucwords(strtolower($row['nombre'])) . '</div>';
					}
					$datos[] = [
						'tienda'  => $txt,
						'canfac2' => $row['canfac2'],
						'cant2'   => $row['cant2'],
						'total2'  => $row['total2'],
						'margen2' => $row['margen2'],
						'canfac1' => $row['canfac1'],
						'cant1'   => $row['cant1'],
						'total1'  => $row['total1'],
						'margen1' => $row['margen1'],
						'canfach' => $row['canfach'],
						'canth'   => $row['canth'],
						'totalh'  => $row['totalh'],
						'margenh' => $row['margenh'],
						'fecha'   => $fecha[5],
						'hora'    => $hora
					];
					$costos['costo2']  = $costos['costo2'] + $row['costo2'];
					$costos['costo1']  = $costos['costo1'] + $row['costo1'];
					$costos['costoh']  = $costos['costoh'] + $row['costoh'];
					$i++;
				}

				// Se retornan los datos obtenidos
				echo json_encode(array( 'data' => $datos, 'costos' => $costos, 'articulo' => $articulo ));
				break;

			case 'listaFacturasxTiendaxArticulo':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, pfecha                    , phora, pmat      ]
				// $params = [ 10     , '2019-05-28,2019-05-27...', '11' , '2023794' ]
				$params = explode('¬', $idpara);
				$fechas = explode(',', $params[1]);

				// Se crea el query para obtener el top de productos
				$sql = "SELECT fecha, factura, caja, cliente, COALESCE(razon, 'Sin Sincronizar') AS razon,
							SUM(subtotal) AS subtotal
						FROM detalle
							LEFT JOIN esclientes ON esclientes.rif = detalle.cliente
						WHERE
							((fecha BETWEEN '$fechas[0]' AND '$fechas[1]') OR
							 (fecha BETWEEN '$fechas[2]' AND '$fechas[3]') OR
							 (fecha BETWEEN '$fechas[4]' AND '$fechas[5]'))
							AND hora <= '$params[2]'
							AND localidad = $params[0]
							AND material = $params[3]
						GROUP BY fecha, hora, factura, caja, cliente, razon
						ORDER BY fecha, hora";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'localidad'=> $params[0],
						'fecha'    => $row['fecha'],
						'factura'  => '<button type="button" title="Ver Factura" onclick="datosFactura(' .
										"'" . $params[0] . "', '" . $row['caja'] . "', '" . $row['cliente'] .
										"', '" . $row['factura'] . "', '" . $row['fecha'] . "')" .
										'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
										'style="white-space: normal; line-height: 1;">' . $row['factura'] .
										'</button>',
						'caja'     => $row['caja'],
						'cliente'  => $row['cliente'],
						'razon'    => $row['razon'],
						'subtotal' => $row['subtotal']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaUsuariosSistema':
				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT us.usuario, us.nombre, us.tienda, COALESCE(ti.nombre, 'TODAS LAS TIENDAS') AS sucursal, us.activo
						FROM usuarios AS us
						LEFT JOIN tiendas AS ti ON ti.id = us.tienda
						WHERE us.usuario != 'admin'";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = array(
						'usuario'  => $row['usuario'],
						'nombre'   => ucwords(strtolower($row['nombre'])),
						'activo'   => $row['activo'],
						'tienda'   => $row['tienda'],
						'sucursal' => $row['sucursal'],
					);
				}

				// Se retornan los datos obtenidos
				echo json_encode(array('data' =>  $datos ));
				break;

			case 'listaConfDptos':
				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT ti.codigodpto AS dpto, ti.tipo_a_p_na AS tipo, esdpto.descripcion AS nomb
						FROM tipo_dpto_apna AS ti
						INNER JOIN esdpto ON esdpto.codigo = ti.codigodpto";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = array(
						'dpto' => $row['dpto'],
						'tipo' => $row['tipo'],
						'nomb' => $row['nomb'],
					);
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'guardarTipoDptos':
				// extraer la informacion de idpara para actualizar los datos
				// idpara[0] = tipo = a = n = p
				// idpara[1] = coadigo del dpto a modificar
				$params = explode('¬', $idpara);
				// Se crea el query para obtener los totales por tienda
				$sql = "UPDATE tipo_dpto_apna SET tipo_a_p_na = '$params[0]' WHERE codigodpto = '$params[1]'";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				if($sql) {
					$result = '1¬Realizado el cambio con Éxito!';
				} else {
					$result = '0¬Hubo un error, no se realizó el cambio';
				}
				// Se retornan los datos obtenidos
				echo json_encode($result);
				break;

			case 'listaconfTasas':
				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT id, to_char(fecha, 'DD-MM-YYYY') AS fecha, tasa FROM tasas_dolar ORDER BY fecha DESC";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = array(
						'id'    => $row['id'],
						'fecha' => $row['fecha'],
						'tasa'  => number_format($row['tasa'], 0)
					);
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'guardarTasaDolar':
				// extraer la informacion de idpara para actualizar los datos
				// idpara[0] = monto del valor del dolar
				// idpara[1] = fecha de referencia
				$params = explode('¬', $idpara);
				// Se crea el query para obtener los totales por tienda
				$sql = "UPDATE tasas_dolar SET fecha = '$params[1]', tasa = '$params[0]' WHERE fecha = '$params[1]'";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$row = $sql->rowCount();
				if($row>0) {
					$result = '1¬Realizado el cambio con Éxito!';
				} else {
					$sql = "INSERT INTO tasas_dolar(fecha, tasa) VALUES('$params[1]', '$params[0]')";
					$sql = $connec->query($sql);
					if($sql) {
						$result = '1¬Realizado el ingreso con Éxito!';
					} else {
						$result = '0¬Hubo un error, no se agregó el cambio';
					}
				}
				// Se retornan los datos obtenidos
				echo json_encode($result);
				break;

			case 'obtener_usuario':
				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT * FROM usuarios WHERE usuario = '$idpara'";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$datos = $sql->fetch();

				$sql = "SELECT modulo FROM usuario_modulos WHERE usuario = '$idpara'";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$modulos = '';
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if(strlen($modulos)>0) { $modulos .= ','; }
					$modulos .= $row['modulo'];
				}

				$datos[] = $modulos;

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'editar_usuario':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ usuario, nombre       , tienda, modulos, clave ]
				// $params = [ admin  , administrador, 10    , {1,2,3}, 12345 ]
				$params = explode(md5('¬'), $idpara);
				$modulos= explode(',', $params[3]);

				$sql = "UPDATE usuarios SET nombre = '$params[1]', tienda = '$params[2]'";
				if(count($params)==5) {
					$sql.= ", clave = '$params[4]'";
				}
				$sql.= " WHERE usuario='$params[0]'";

				$sql = $connec->query($sql);
				$row = $sql->rowCount();
				if($row==0) {
					echo json_encode('0¬No se realizó la modificación.' .chr(10) . 'Por favor verifique la información !!!');
				} else {
					if(count($modulos)>0) {
						guardarModulos($modulos, $params[0], $connec);
					}
					echo json_encode('1¬Modificación realizada con éxito !!!');
				}
				break;

			case 'nuevo_usuario':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ usuario, nombre       , tienda, modulos, clave ]
				// $params = [ admin  , administrador, 10    , {1,2,3}, 12345 ]
				$params = explode(md5('¬'), $idpara);
				$modulos= explode(',', $params[3]);

				$sql = "SELECT * FROM usuarios WHERE usuario='$params[0]'";

				$sql = $connec->query($sql);
				$row = $sql->fetch();

				if($row){
					echo json_encode('0¬El usuario ya existe.' .chr(10) . 'Por favor verifique la información !!!');
					break;
				} else {
					$sql = "INSERT INTO usuarios VALUES('$params[0]', '$params[1]', '$params[4]', '$params[2]')";
				}

				$sql = $connec->query($sql);
				$row = $sql->rowCount();
				if($row==0) {
					echo json_encode('0¬No se realizó la creación del usuario.' .chr(10) . 'Por favor verifique la información !!!');
				} else {
					if(count($modulos)>0) {
						guardarModulos($modulos, $params[0], $connec);
					}
					echo json_encode('1¬Usuario creado con éxito !!!');
				}
				break;

			case 'bloquear_usuario':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ usuario, status ]
				// $params = [ admin  , 1-0    ]
				$params = explode('¬', $idpara);

				// Se crea el query para obtener los totales por tienda
				$sql = "UPDATE usuarios SET activo =";
				$sql.= $params[1]==1 ? 'true' : 'false';
				$sql.= " WHERE usuario = '$params[0]'";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				echo json_encode('');
				break;

			case 'eliminar_usuario':
				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT * FROM usuarios WHERE usuario='$idpara'";

				$sql = $connec->query($sql);
				$row = $sql->fetch();

				if($row){
					$sql = "DELETE FROM usuarios ";
					$sql.= " WHERE usuario = '$idpara'";

					// Se ejecuta la consulta en la BBDD
					$sql = $connec->query($sql);

					$sql = "SELECT * FROM usuarios WHERE usuario='$idpara'";

					$sql = $connec->query($sql);
					$row = $sql->fetch();

					if(!$row){ echo json_encode('1¬Usuario Eliminado correctamente'); }
					else { echo json_encode('0¬El Usuario no se pudo Eliminar'); }
				} else {
					echo json_encode('0¬El Usuario no existe');
				}
				break;

			case 'margenDepartamentos':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ pdpto, ptienda ]
				// $params = [ 16   , 10      ]
				$params = explode('¬', $idpara);

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT
							dpto.descripcion AS dpto,
							ti.nombre AS tienda,
							COUNT(DISTINCT factura) AS canfact,
							SUM(det.subtotal) AS subtotal,
							SUM(det.costo) AS costo,
							ROUND(((SUM(det.subtotal)-SUM(det.costo)) * 100) / SUM(det.subtotal), 2) AS margen
						FROM
							detalle_dia det
							INNER JOIN tiendas ti ON ti.id = det.localidad
							LEFT JOIN esarticulos art ON art.codigo = det.material
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE
							dpto.codigo = $params[0]
							AND fecha = '$fecha'
							AND hora <= '$hora'";
				if($params[1]!='*') {
					$sql .= " AND localidad = $params[1]";
				}

				$sql .= " AND material IN(SELECT codigo FROM esarticulos WHERE departamento = $params[0])
						GROUP BY ti.nombre, dpto.descripcion";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'dpto'     => $row['dpto'],
						'tienda'   => $row['tienda'],
						'canfact'  => $row['canfact'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaDepartamentos':
				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT codigo, descripcion
						FROM esdpto
						WHERE descripcion NOT ILIKE '%no usar%' AND LENGTH(descripcion)>=3
						ORDER BY descripcion";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => ucwords(strtolower($row['descripcion'])),
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaPromociones':
				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT id, texto, to_char(fecha_ini,'DD-MM-YYYY') AS fecha_ini, to_char(fecha_fin,'DD-MM-YYYY') AS fecha_fin
						FROM promociones
						ORDER BY fecha_ini";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id'    => $row['id'],
						'texto' => ucwords(strtolower($row['texto'])) . ' ( ' . $row['fecha_ini'] . ' al ' . $row['fecha_fin'] . ' )',
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'materialxDptoEV':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [  ptienda, iddpto, prfechas ]
				// $params = [ {7}     , {4}   , {2019-05-29,2019-05-22,2019-05-15,2019-05-08} ]
				$params = explode('¬', $idpara);

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT * FROM (SELECT COALESCE(dpto.descripcion, 'SIN CONFIGURAR') AS dpto, dpto.codigo AS codigodpto,
							det.fecha, det.material AS material, art.descripcion AS articulo,
							SUM(det.cantidad) AS cantidad, (SUM(det.subtotal)/1000) AS subtotal,
							(SUM(det.costo)/1000) AS costo,
							(CASE WHEN (SUM(det.subtotal)/1000) = 0 THEN 0
							ELSE ROUND(((SUM(det.subtotal)/1000)-(SUM(det.costo)/1000)) / (SUM(det.subtotal)/1000) * 100, 2) END) AS margen
						FROM
							detalle det
							LEFT JOIN esarticulos art ON art.codigo = det.material
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE cantidad != 0
							AND STRPOS('$params[2]', CAST(det.fecha AS VARCHAR(10))) > 0
							AND det.hora <= '$hora'
							AND art.departamento = $params[1]";
				if($params[0]!='*') {
					$sql .= " AND det.localidad = $params[0]";
				}

				$sql.= " GROUP BY fecha, articulo, dpto, codigodpto, material) articulos WHERE cantidad != 0";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'dpto'     => ucwords(strtolower($row['dpto'])),
						'fecha'    => $row['fecha'],
						'articulo' => $row['articulo'],
						'cantidad' => $row['cantidad'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'materialxDptoESV':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, iddpto, fechaini  , fechafin   ]
				// $params = [ 7      , 4     , 2019-03-01, 2019-06-30 ]
				$params = explode('¬', $idpara);

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT * FROM (
							SELECT COALESCE(dpto.descripcion, 'SIN CONFIGURAR') AS dpto, dpto.codigo AS codigodpto,
								EXTRACT('WEEK' FROM fecha) AS semana, art.descripcion AS articulo,
								SUM(det.cantidad) AS cantidad, (SUM(det.subtotal)/1000) AS subtotal,
								(SUM(det.costo)/1000) AS costo,
								(CASE WHEN (SUM(det.subtotal)/1000) = 0 THEN 0
								ELSE ROUND(((SUM(det.subtotal)/1000)-(SUM(det.costo)/1000)) / (SUM(det.subtotal)/1000) * 100, 2) END) AS margen
							FROM
								detalle det
								LEFT JOIN esarticulos art ON art.codigo = det.material
								LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
							WHERE cantidad != 0
								AND fecha BETWEEN '$params[2]' AND '$params[3]'
								AND hora <= '$hora'
								AND art.departamento = '$params[1]'
								AND det.localidad = '$params[0]'
							GROUP BY semana, articulo, dpto, codigodpto) articulos
						WHERE cantidad != 0";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'dpto'     => ucwords(strtolower($row['dpto'])),
						'semana'   => $row['semana'],
						'articulo' => $row['articulo'],
						'cantidad' => $row['cantidad'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'materialxDptoEMV':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ ptienda, iddpto, afechas0  , afecha1   , diaini, diafin ]
				// $params = [ 7      , 4     , 2019-03-01, 2019-06-30, 1     , 5      ]
				$params = explode('¬', $idpara);

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT * FROM (
							SELECT COALESCE(dpto.descripcion, 'SIN CONFIGURAR') AS dpto, dpto.codigo AS codigodpto,
								to_char(fecha,'YYYY-MM') AS mes, art.descripcion AS articulo,
								SUM(det.cantidad) AS cantidad, (SUM(det.subtotal)/1000) AS subtotal,
								(SUM(det.costo)/1000) AS costo,
								(CASE WHEN (SUM(det.subtotal)/1000) = 0 THEN 0
								ELSE ROUND(((SUM(det.subtotal)/1000)-(SUM(det.costo)/1000)) / (SUM(det.subtotal)/1000) * 100, 2) END) AS margen
							FROM
								detalle det
								LEFT JOIN esarticulos art ON art.codigo = det.material
								LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
							WHERE cantidad != 0
								AND fecha BETWEEN '$params[2]' AND '$params[3]'
								AND hora <= '$hora'
								AND art.departamento = '$params[1]'
								AND det.localidad = '$params[0]'";

				if($params[4]!='0' && $params[5]!='0') {
					$sql .= " AND EXTRACT('DAY' FROM fecha) BETWEEN $params[4] AND $params[5]";
				}

				$sql .= "	GROUP BY mes, articulo, dpto, codigodpto) articulos
						WHERE cantidad != 0";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'dpto'     => ucwords(strtolower($row['dpto'])),
						'mes'      => $row['mes'],
						'articulo' => $row['articulo'],
						'cantidad' => $row['cantidad'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'materialxDptoESVPromo':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [  ptienda, iddpto, prfechas                                     ,  pmat    , ppromo]
				// $params = [ {7}     , {4}   , {2019-05-29,2019-05-22,2019-05-15,2019-05-08}, {1512411}, {1}   ]
				$params = explode('¬', $idpara);
				$fechas = explode(',', $params[2]);

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT * FROM (SELECT COALESCE(dpto.descripcion, 'SIN CONFIGURAR') AS dpto, dpto.codigo AS codigodpto,
							det.fecha, det.material AS material, art.descripcion AS articulo,
							SUM(det.cantidad) AS cantidad, (SUM(det.subtotal)/1000) AS subtotal,
							(SUM(det.costo)/1000) AS costo,
							(CASE WHEN (SUM(det.subtotal)/1000) = 0 THEN 0
							ELSE ROUND(((SUM(det.subtotal)/1000)-(SUM(det.costo)/1000)) / (SUM(det.subtotal)/1000) * 100, 2) END) AS margen
						FROM
							detalle det
							LEFT JOIN esarticulos art ON art.codigo = det.material
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE cantidad != 0
							AND (  (fecha BETWEEN '$fechas[0]' AND '$fechas[1]')
								OR (fecha BETWEEN '$fechas[2]' AND '$fechas[3]')
								OR (fecha BETWEEN '$fechas[4]' AND '$fechas[5]') )
							AND det.hora <= '$hora'";

				if($params[1]!='departamento') {
					$sql .= " AND art.departamento = $params[1]";
				}

				if($params[0]!='*') {
					$sql .= " AND det.localidad = $params[0]";
				}

				if($params[3]!='material') {
					$sql.= " AND det.material = $params[3]";
				}

				if($params[4]!='promocion') {
					$sql.= " AND det.promocion = $params[4]";
				}

				$sql.= " GROUP BY fecha, articulo, dpto, codigodpto, material) articulos WHERE cantidad != 0";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'dpto'     => ucwords(strtolower($row['dpto'])),
						'fecha'    => $row['fecha'],
						'articulo' => '<span title="'.$row['material'].'">'.$row['articulo'].'</span>',
						'cantidad' => $row['cantidad'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'graContClientesBdes':
				// Se divide la fecha en 2 partes, anio y mes
				$fecha = explode('-', $fecha);
				$anio = $fecha[0];
				$mes = $fecha[1];

				$sql = "SELECT COUNT(RIF) AS clientes FROM BDES_POS.dbo.ESCLIENTESPOS";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$row = $sql->fetch();
				$totctes += $row['clientes'];

				$sql = "SELECT COUNT(ESCLIENTESPOS.RIF) AS clientes,
							DAY(ESCLIENTESPOS.fecha) AS dia
						FROM BDES_POS.dbo.ESCLIENTESPOS
							INNER JOIN BDES.dbo.ESSucursales ON localidad = codigo
						WHERE YEAR(ESCLIENTESPOS.fecha) = $anio AND MONTH(ESCLIENTESPOS.fecha) = $mes
						GROUP BY DAY(ESCLIENTESPOS.fecha)
						ORDER BY DAY(ESCLIENTESPOS.fecha)";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'clientes'  => $row['clientes'],
						'dia'       => $row['dia']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode( array( "data" => $datos, "totctes" => number_format($totctes) ) );
				break;

			case 'tabContClientesBdes':
				$sql = "SELECT COUNT(RIF) AS clientes,
							BDES.dbo.ESSucursales.Nombre
						FROM BDES_POS.dbo.ESCLIENTESPOS
							INNER JOIN BDES.dbo.ESSucursales ON localidad = codigo
						WHERE CAST(ESCLIENTESPOS.fecha AS DATE) = '$fecha'
						GROUP BY ESCLIENTESPOS.localidad, BDES.dbo.ESSucursales.Nombre
						ORDER BY ESCLIENTESPOS.localidad";

				$sql = $connec->prepare($sql);
				$sql->execute();
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'clientes'  => $row['clientes'],
						'localidad' => ucwords(strtolower($row['Nombre']))
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode( array( 'data' => $datos, 'fecha' => date('d \d\e M \d\e Y', strtotime($fecha)) ) );
				break;

			case 'tabResClientesBdes':
				// se extraen las fechas del parametro idpara
				$params = explode('¬', $idpara);
				$sql = "SELECT COUNT(RIF) AS clientes,
							RTRIM(YEAR(ESCLIENTESPOS.fecha))+'-'+RIGHT('00' + RTRIM(LTRIM(MONTH(ESCLIENTESPOS.fecha))),2) AS anio_mes
						FROM BDES_POS.dbo.ESCLIENTESPOS
							INNER JOIN BDES.dbo.ESSucursales ON localidad = codigo
						WHERE CAST(ESCLIENTESPOS.fecha AS DATE) BETWEEN '$params[0]' AND '$params[1]'
						GROUP BY YEAR(ESCLIENTESPOS.fecha), MONTH(ESCLIENTESPOS.fecha)
						ORDER BY anio_mes";

				$sql = $connec->prepare($sql);
				$sql->execute();
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'clientes' => number_format($row['clientes']),
						'anio_mes' => $row['anio_mes']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'graVtasAcumuladas':
				// Se divide la fecha en 2 partes, anio y mes
				$sql = "SELECT ti.id AS localidad, ti.nombre AS sucursal,
							COALESCE(
							(SELECT SUM(monto)
								FROM presupuestos
								WHERE tienda_id = ti.id
								AND TO_CHAR(fecha, 'YYYY-MM') = '$fecha'
								AND fecha <= CURRENT_DATE), 0) AS ppto
						FROM tiendas ti
						GROUP BY ti.id, ti.nombre";

				$sql = $connec->query($sql);

				$totgppto = 0;
				$pptos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$totgppto += $row['ppto'];
					$pptos[] = [
						'localidad' => $row['localidad'],
						'sucursal'  => $row['sucursal'],
						'ppto'      => $row['ppto']
					];
				}

				$sql = "SELECT localidad, SUM(det.subtotal) AS vtas,
							(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
							ROUND(((SUM(det.subtotal)-SUM(det.costo))/SUM(det.subtotal))*100, 2) END) AS marg
						FROM detalle AS det
						WHERE TO_CHAR(fecha, 'YYYY-MM') = '$fecha'
						GROUP BY TO_CHAR(fecha, 'YYYY-MM'), localidad";

				$sql = $connec->query($sql);

				$totgvtas = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$totgvtas += $row['vtas'];
					$montoppto = 0;
					$localppto = '';
					for($i=0; $i<count($pptos); $i++) {
						if($pptos[$i]['localidad']==$row['localidad']) {
							$montoppto = $pptos[$i]['ppto'];
							$localppto = $pptos[$i]['sucursal'];
							break;
						}
					}
					$datos[] = [
						'localidad' => $row['localidad'],
						'sucursal'  => $localppto,
						'vtas'      => $row['vtas']*1,
						'ppto'      => $montoppto,
						'marg'      => $row['marg']*1,
						'cumple'    => number_format($montoppto == 0 ? 0 : (($row['vtas'] * 100)/$montoppto))*1
					];
				}

				$cumple = $totgppto > 0 ? (($totgvtas * 100) / $totgppto) : 0;

				// Se retornan los datos obtenidos
				echo json_encode( array(
						"data"     => $datos,
						"totgppto" => number_format($totgppto),
						"totgvtas" => number_format($totgvtas),
						"cumple"   => number_format($cumple, 2)
				) );
				break;

			case 'graTop20Productos':
				// Se prepara la consulta con los parametros
				$sql = "SELECT dpto.codigo AS dpto_id, COALESCE(dpto.descripcion, 'Sin Registrar') AS dpto_nom,
							COALESCE(ROUND(SUM(det.cantidad), 2), 0) AS cant_total,
							COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS subt_total,
							(CASE WHEN SUM(det.subtotal) = 0 THEN 0
							 ELSE ROUND(((SUM(det.subtotal)-SUM(det.costo))/SUM(det.subtotal)*100), 2) END) AS margen
						FROM detalle det
							INNER JOIN esarticulos art ON art.codigo = det.material AND det.material NOT IN($art_excl)
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE TO_CHAR(fecha, 'YYYY-MM') = '$fecha'";

				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}

				$sql .= " GROUP BY dpto.codigo, dpto.descripcion";
				$sql .= " ORDER BY cant_total DESC" ;

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$dptos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$dptos[] = [
						'dpto_id'    => $row['dpto_id'],
						'dpto_nom'   => $row['dpto_nom'],
						'cant_total' => $row['cant_total'],
						'subt_total' => $row['subt_total'],
						'margen'     => $row['margen'] . ' %'
					];
				}

				// Se prepara la consulta para obtener el total general para el top 20
				$sql = "SELECT
							COALESCE(ROUND(SUM(det.cantidad), 2), 0) AS cant_total,
							COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS subt_total
						FROM detalle det
							INNER JOIN esarticulos art ON art.codigo = det.material
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE TO_CHAR(fecha, 'YYYY-MM') = '$fecha'";

				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$sql = $sql->fetch();

				$totCgral = $sql['cant_total'];
				$totSgral = $sql['subt_total'];

				// Se prepara la consulta con los parametros
				$sql = "SELECT det.material, art.descripcion, COALESCE(ROUND(SUM(det.cantidad), 2), 0) AS cant_total,
							COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS subt_total,
							COALESCE(dpto.descripcion, 'Sin Registrar') AS dpto_nom, COALESCE(art.departamento, 0) AS dpto_id,
							(CASE WHEN SUM(det.subtotal) = 0 THEN 0
							 ELSE ROUND(((SUM(det.subtotal)-SUM(det.costo))/SUM(det.subtotal)*100), 2) END) AS margen
						FROM detalle det
							INNER JOIN esarticulos art ON art.codigo = det.material AND det.material NOT IN($art_excl)
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE TO_CHAR(fecha, 'YYYY-MM') = '$fecha'";

				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}

				$sql .= " GROUP BY det.material, art.descripcion, dpto.descripcion, art.departamento";
				$sql .= " ORDER BY cant_total DESC ";
				$sql .= " LIMIT 20";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$totC20 = 0;
				$totS20 = 0;
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'material'   => $row['descripcion'],
						'cant_total' => $row['cant_total'],
						'subt_total' => $row['subt_total'],
						'dpto_id'    => $row['dpto_id'],
						'margen'     => $row['margen'] . ' %'
					];
					$totC20 += $row['cant_total'];
					$totS20 += $row['subt_total'];
				}

				// Se retornan los datos obtenidos
				echo json_encode(
					array(
						'data'     => $datos,
						'dptos'    => $dptos,
						'totCgral' => number_format($totCgral,2),
						'totSgral' => number_format($totSgral,2),
						'totC20'   => number_format($totC20, 2),
						'totS20'   => number_format($totS20, 2),
						'moneda'   => $moneda,
						'simbolo'  => $simbolo
					) );
				break;

			case 'graTop20Tipos':
				// Se prepara la consulta con los parametros
				$sql = "SELECT (CASE ti.tipo_a_p_na WHEN 'p' THEN 0 WHEN 'a' THEN 1 WHEN 'n' THEN 2 END) AS tipodpto,
							(CASE ti.tipo_a_p_na
								WHEN 'p' THEN 'Perecederos'
								WHEN 'a' THEN 'Alimentos'
								WHEN 'n' THEN 'No Alimentos' END) AS nomtipo,
							COALESCE(ROUND(SUM(det.cantidad), 2), 0) AS cant_total,
							COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS subt_total,
							(CASE WHEN SUM(det.subtotal) = 0 THEN 0
							 ELSE ROUND(((SUM(det.subtotal)-SUM(det.costo))/SUM(det.subtotal)*100), 2) END) AS margen
						FROM detalle det
							INNER JOIN esarticulos art ON art.codigo = det.material AND det.material NOT IN($art_excl)
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
							INNER JOIN tipo_dpto_apna ti ON ti.codigodpto = dpto.codigo
						WHERE TO_CHAR(fecha, 'YYYY-MM') = '$fecha'";

				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}

				$sql .= " GROUP BY ti.tipo_a_p_na";
				$sql .= " ORDER BY cant_total DESC" ;

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$dptos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$dptos[] = [
						'tipodpto'   => $row['tipodpto'],
						'nomtipo'    => $row['nomtipo'],
						'cant_total' => $row['cant_total'],
						'subt_total' => $row['subt_total'],
						'margen'     => $row['margen'] . ' %'
					];
				}

				// Se prepara la consulta para obtener el total general para el top 20
				$sql = "SELECT
							COALESCE(ROUND(SUM(det.cantidad), 2), 0) AS cant_total,
							COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS subt_total
						FROM detalle det
							INNER JOIN esarticulos art ON art.codigo = det.material
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
							INNER JOIN tipo_dpto_apna ti ON ti.codigodpto = dpto.codigo
						WHERE TO_CHAR(fecha, 'YYYY-MM') = '$fecha'";

				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				$sql = $sql->fetch();

				$totCgral = $sql['cant_total'];
				$totSgral = $sql['subt_total'];

				// Se prepara la consulta con los parametros
				$sql = "SELECT
							(CASE ti.tipo_a_p_na WHEN 'p' THEN 0 WHEN 'a' THEN 1 WHEN 'n' THEN 2 END) AS tipodpto,
							art.descripcion, COALESCE(ROUND(SUM(det.cantidad), 2), 0) AS cant_total,
							COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS subt_total,
							(CASE WHEN SUM(det.subtotal) = 0 THEN 0
							 ELSE ROUND(((SUM(det.subtotal)-SUM(det.costo))/SUM(det.subtotal)*100), 2) END) AS margen
						FROM detalle det
							INNER JOIN esarticulos art ON art.codigo = det.material AND det.material NOT IN($art_excl)
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
							INNER JOIN tipo_dpto_apna ti ON ti.codigodpto = dpto.codigo
						WHERE TO_CHAR(fecha, 'YYYY-MM') = '$fecha'";

				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}

				$sql .= " GROUP BY ti.tipo_a_p_na, art.descripcion";
				$sql .= " ORDER BY cant_total DESC ";
				$sql .= " LIMIT 20";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$totC20 = 0;
				$totS20 = 0;
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'tipodpto'   => $row['tipodpto'],
						'material'   => $row['descripcion'],
						'cant_total' => $row['cant_total'],
						'subt_total' => $row['subt_total'],
						'margen'     => $row['margen'] . ' %'
					];
					$totC20 += $row['cant_total'];
					$totS20 += $row['subt_total'];
				}

				// Se retornan los datos obtenidos
				echo json_encode(
					array(
						'data'     => $datos,
						'dptos'    => $dptos,
						'totCgral' => number_format($totCgral,2),
						'totSgral' => number_format($totSgral,2),
						'totC20'   => number_format($totC20, 2),
						'totS20'   => number_format($totS20, 2),
						'moneda'   => $moneda,
						'simbolo'  => $simbolo
					) );
				break;

			case 'calendario_vtas':
				$fecha = explode('¬', $fecha);
				$diant = date('Y-m-d', strtotime($fecha[0].'- 1 days'));
				$sql = "SELECT ROUND(
							(CASE WHEN SUM(subtotal) = 0 THEN 0
							ELSE ((SUM(subtotal) - SUM(costo)) / SUM(subtotal)) * 100
							END), 2) AS margen
						FROM vtasacumdia
						WHERE fecha = '$diant'";
				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}
				$sql = $connec->query($sql);
				$datos = $sql->fetch();
				$margen_ant = $datos['margen'];
				// Se prepara la consulta con los parametros
				$sql = "SELECT fecha, SUM(cantfact) AS transacciones,
							SUM(cantidad) AS cantidad, SUM(subtotal) AS subtotal, SUM(costo) AS costo,
							ROUND(
								(CASE WHEN SUM(subtotal) = 0 THEN 0
								ELSE ((SUM(subtotal) - SUM(costo)) / SUM(subtotal)) * 100
								END), 2) AS margen
						FROM vtasacumdia
						WHERE fecha BETWEEN '$fecha[0]' AND '$fecha[1]'";
				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}
				$sql .= " GROUP BY fecha ORDER BY fecha ASC";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) { print_r($connec->errorInfo()); }
				$fechaini = date('Y-m-d', strtotime($fecha[0]));
				$datos= [];
				for($i=1; $i <= date('j', strtotime($fecha[1])); $i++) {
					$datos[] = [
						'fecha'        => $fechaini,
						'canfac'       => 0,
						'cantidad'     => 0,
						'subtotal'     => 0,
						'margen'       => 0,
						'nivel'        => null,
						'simbolo'      => $simbolo,
					];
					$fechaini = date('Y-m-d', strtotime($fechaini."+ 1 days"));
				}
				$row = $sql->fetchAll();
				for($i=0; $i<count($datos); $i++) {
					for($j=0; $j<count($row); $j++) {
						if($datos[$i]['fecha']==$row[$j]['fecha']) {
							$datos[$i]['canfac']   = number_format($row[$j]['transacciones'], 0);
							$datos[$i]['cantidad'] = number_format($row[$j]['cantidad'], 2);
							$datos[$i]['subtotal'] = $row[$j]['subtotal'];
							$datos[$i]['margen']   = number_format($row[$j]['margen'], 2);
							switch (true) {
								case $row[$j]['margen'] > $margen_ant:
									$nivel = 'badge-success';
									break;
									case $row[$j]['margen'] < $margen_ant:
									$nivel = 'badge-danger';
									break;
								default:
									$nivel = 'badge-secondary';
									break;
							}
							$datos[$i]['nivel']   = $nivel;
							$datos[$i]['simbolo'] = $simbolo;
							$margen_ant           = $row[$j]['margen'];
						}
					}
				}
				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'calendario_vtas_hoy':
				// Se obtiene el margen del día anterior
				$sql = "SELECT ROUND(
							(CASE WHEN SUM(subtotal) = 0 THEN 0
							ELSE ((SUM(subtotal) - SUM(costo)) / SUM(subtotal)) * 100
							END), 2) AS margen
						FROM vtasacumdia
						WHERE fecha = (CURRENT_DATE - INTERVAL '1 day')";
				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}
				$sql = $connec->query($sql);
				$datos = $sql->fetch();
				$margen_ant = $datos['margen'];
				// Se prepara la consulta con los parametros
				$sql = "SELECT fecha, SUM(cantfact) AS transacciones,
							SUM(cantidad) AS cantidad, SUM(subtotal) AS subtotal, SUM(costo) AS costo,
							ROUND(
								(CASE WHEN SUM(subtotal) = 0 THEN 0
								ELSE ((SUM(subtotal) - SUM(costo)) / SUM(subtotal)) * 100
								END), 2) AS margen
						FROM vtasacumdia
						WHERE fecha = CURRENT_DATE";
				if($idpara!='localidad') {
					$sql .= " AND localidad = '$idpara'";
				}
				$sql .= ' GROUP BY fecha ORDER BY fecha ASC';
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				$row = $sql->fetch();
				$datos = [];
				$datos['fecha']        = date('j');
				$datos['canfac']       = number_format($row['transacciones'], 0);
				$datos['cantidad']     = number_format($row['cantidad'], 2);
				$datos['subtotal']     = $row['subtotal'];
				$datos['margen']       = number_format($row['margen'], 2);
				switch (true) {
					case $row['margen'] > $margen_ant:
						$nivel = 'badge-success';
						break;
					case $row['margen'] < $margen_ant:
						$nivel = 'badge-danger';
						break;
					default:
						$nivel = 'badge-secondary';
						break;
				}
				$datos['nivel']   = $nivel;
				$datos['simbolo']  = $simbolo;
				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'margenDptosDia':
				// Se extraen los datos de la cadena del parametro para obtener los id's en un array
				// $params = [ pfecha    , ptienda ]
				// $params = [ 2019-10-16, 10      ]

				// Se obtiene un listado de las facturas del cliente
				$sql = "SELECT COALESCE(dpto.descripcion, 'Sin registrar') AS dpto, SUM(det.subtotal) AS subtotal, SUM(det.costo) AS costo,
							(CASE WHEN SUM(det.subtotal) = 0 THEN 0
							ELSE ROUND(((SUM(det.subtotal) - SUM(det.costo)) * 100) / SUM(det.subtotal), 2)
							END) AS margen
						FROM detalle det
							LEFT JOIN esarticulos art ON art.codigo = det.material
							LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
						WHERE fecha = '$fecha'";

				if($idpara!='localidad') {
					$sql .= " AND det.localidad = $idpara";
				}

				$sql .= " GROUP BY dpto.descripcion";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'dpto'     => $row['dpto'],
						'subtotal' => $row['subtotal'],
						'costo'    => $row['costo'],
						'margen'   => $row['margen']
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'comparaTiendaVsCentral':
				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, nombre, servidor, activa FROM BDES.dbo.ESSucursales";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$sucursales= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$sucursales[] = [
						'codigo'   => $row['codigo'],
						'nombre'   => $row['nombre'],
						'servidor' => $row['servidor'],
						'activa'   => $row['activa'],
					];
				}

				$connec = null;

				if(count($sucursales) > 0) {

					$datos = [];
					foreach ($sucursales as $sucursal) {
						// Conectando con SQL de las sucursales
						$connectionInfo = array(
							"Database" => "BDES_POS",
							"UID" => $params['user_sql'],
							"PWD" => $params['password_sql'],
							"ConnectRetryCount" => 3,
						);

						$connec = sqlsrv_connect(strtolower($sucursal['servidor']), $connectionInfo);

						if($connec === false) {
							$datos[] = [
								'tienda'    => '<span class="badge badge-danger m-0">' . $sucursal['nombre'] . ' Sin Conexión</span>',
								'cantfactt' => 0,
								'cantidadt' => 0,
								'costot'    => 0,
								'subtotalt' => 0,
								'margent'   => 0,
								'cantfactd' => 0,
								'cantidadd' => 0,
								'costod'    => 0,
								'subtotald' => 0,
								'margend'   => 0,
								'fecha'     => $fecha,
							];
						} else {
							// Se crea el query para obtener los totales por tienda
							$sql = "SELECT COUNT(DISTINCT CONCAT(DOCUMENTO, '-', caja)) AS transacciones,
										CAST(ROUND(SUM(CANTIDAD), 2) AS DECIMAL(20,2)) AS cantidad,
										CAST(ROUND(SUM(COSTO*CANTIDAD), 2) AS DECIMAL(20,2)) AS costo,
										CAST(ROUND(SUM(SUBTOTAL), 2) AS DECIMAL(20,2)) AS subtotal,
										CAST((CASE WHEN SUM(SUBTOTAL) = 0 THEN 0 ELSE
										ROUND((SUM(SUBTOTAL) - SUM(COSTO*CANTIDAD)) / SUM(SUBTOTAL) * 100, 2) END) AS DECIMAL(20,2)) AS margen
									FROM dbo.ESVENTASPOS_DET
									WHERE CAST(FECHA AS DATE) = CAST('$fecha' AS DATE)";

							// Se ejecuta la consulta en la BBDD
							$sql = sqlsrv_query($connec, $sql);
							if($sql === false) {
								$datos[] = [
									'tienda'    => '<span class="badge badge-danger m-0">' . $sucursal['nombre'] . ' Error SQL</span>',
									'cantfactt' => 0,
									'cantidadt' => 0,
									'costot'    => 0,
									'subtotalt' => 0,
									'margent'   => 0,
									'cantfactd' => 0,
									'cantidadd' => 0,
									'costod'    => 0,
									'subtotald' => 0,
									'margend'   => 0,
									'fecha'     => $fecha,
								];
							} else {
								while($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
									$datos[] = [
										'tienda'    => $sucursal['nombre'],
										'cantfactt' => $row['transacciones'],
										'cantidadt' => $row['cantidad'],
										'costot'    => $row['costo'],
										'subtotalt' => $row['subtotal'],
										'margent'   => $row['margen'],
										'cantfactd' => 0,
										'cantidadd' => 0,
										'costod'    => 0,
										'subtotald' => 0,
										'margend'   => 0,
										'fecha'     => $fecha,
									];
								}
							}
						}
						sqlsrv_close( $connec );
					}

					// connect to the postgresql database
					$conStr = sprintf("pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
								$params['host'],
								$params['port'],
								$params['database'],
								$params['user'],
								$params['password']);

					$connec = new \PDO($conStr);

					foreach ($sucursales as $sucursal) {
						// Se crea el query para obtener los valores del dashboard
						$sql = "SELECT ROUND(COUNT(DISTINCT CONCAT(localidad, '-', factura, '-', caja)), 2) AS transacciones,
									ROUND(SUM(cantidad), 2) AS cantidad,
									ROUND(SUM(costo), 2) AS costo,
									ROUND(SUM(subtotal), 2) AS subtotal,
									(CASE WHEN SUM(subtotal) = 0 THEN 0 ELSE
									ROUND((SUM(subtotal) - SUM(costo)) / SUM(subtotal) * 100, 2) END) AS margen
								FROM detalle
								WHERE localidad = '" . $sucursal['codigo'] . "' AND fecha = '$fecha'";

						// Se ejecuta la consulta en la BBDD
						$sql = $connec->query($sql);

						while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
							foreach ($datos as &$dato) {
								if($dato['tienda'] == $sucursal['nombre']) {
									$dato['cantfactd'] = $row['transacciones'];
									$dato['cantidadd'] = $row['cantidad'];
									$dato['costod']    = $row['costo'];
									$dato['subtotald'] = $row['subtotal'];
									$dato['margend']   = $row['margen'];
								}
							}
						}
					}

					$connec = null;
				}

				// Totales antes de cambiar los datos a span
				$totales['cantfactt'] = 0;
				$totales['cantidadt'] = 0;
				$totales['costot']    = 0;
				$totales['subtotalt'] = 0;
				$totales['cantfactd'] = 0;
				$totales['cantidadd'] = 0;
				$totales['costod']    = 0;
				$totales['subtotald'] = 0;

				foreach ($datos as &$dato) {
					// Totales antes de convertir los campos a span
					$totales['cantfactt'] += $dato['cantfactt'];
					$totales['cantidadt'] += $dato['cantidadt'];
					$totales['costot']    += $dato['costot'];
					$totales['subtotalt'] += $dato['subtotalt'];
					$totales['cantfactd'] += $dato['cantfactd'];
					$totales['cantidadd'] += $dato['cantidadd'];
					$totales['costod']    += $dato['costod'];
					$totales['subtotald'] += $dato['subtotald'];

					$dato['cantfactt'] = number_format($dato['cantfactt'], 2);
					$dato['cantfactd'] = number_format($dato['cantfactd'], 2);
					$dato['cantidadt'] = number_format($dato['cantidadt'], 2);
					$dato['cantidadd'] = number_format($dato['cantidadd'], 2);
					$dato['costot'] = number_format($dato['costot'], 2);
					$dato['costod'] = number_format($dato['costod'], 2);
					$dato['subtotalt'] = number_format($dato['subtotalt'], 2);
					$dato['subtotald'] = number_format($dato['subtotald'], 2);
					$dato['margent'] = number_format($dato['margent'], 2);
					$dato['margend'] = number_format($dato['margend'], 2);

					// Diferencias en transacciones
					if(intval($dato['cantfactt'])<intval($dato['cantfactd'])) {
						$dato['cantfactt'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['cantfactt'] . '</div>';
						$dato['cantfactd'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['cantfactd'] . '</div>';
					}
					if(intval($dato['cantfactd'])<intval($dato['cantfactt'])) {
						$dato['cantfactt'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['cantfactt'] . '</div>';
						$dato['cantfactd'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['cantfactd'] . '</div>';
					}
					// Diferencias en cantidades
					if(intval($dato['cantidadt'])<intval($dato['cantidadd'])) {
						$dato['cantidadt'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['cantidadt'] . '</div>';
						$dato['cantidadd'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['cantidadd'] . '</div>';
					}
					if(intval($dato['cantidadd'])<intval($dato['cantidadt'])) {
						$dato['cantidadt'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['cantidadt'] . '</div>';
						$dato['cantidadd'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['cantidadd'] . '</div>';
					}
					// Diferencias en costo
					if(intval($dato['costot'])<intval($dato['costod'])) {
						$dato['costot'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['costot'] . '</div>';
						$dato['costod'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['costod'] . '</div>';
					}
					if(intval($dato['costod'])<intval($dato['costot'])) {
						$dato['costot'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['costot'] . '</div>';
						$dato['costod'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['costod'] . '</div>';
					}
					// Diferencias en subtotales
					if(intval($dato['subtotalt'])<intval($dato['subtotald'])) {
						$dato['subtotalt'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['subtotalt'] . '</div>';
						$dato['subtotald'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['subtotald'] . '</div>';
					}
					if(intval($dato['subtotald'])<intval($dato['subtotalt'])) {
						$dato['subtotalt'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['subtotalt'] . '</div>';
						$dato['subtotald'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['subtotald'] . '</div>';
					}
					// Diferencias en margens
					if(intval($dato['margent'])<intval($dato['margend'])) {
						$dato['margent'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['margent'] . '</div>';
						$dato['margend'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['margend'] . '</div>';
					}
					if(intval($dato['margend'])<intval($dato['margent'])) {
						$dato['margent'] = '<div class="w-100 m-0 rounded badge-success">' . $dato['margent'] . '</div>';
						$dato['margend'] = '<div class="w-100 m-0 rounded badge-danger">' . $dato['margend'] . '</div>';
					}
				}

				// Se retornan los datos obtenidos
				echo json_encode(array('data' => $datos, 'totales' => $totales));
				break;

			case 'dauditoriaCostos':
				// Se crea query para obtener los cambios realizados a la fecha
				$sql = "SELECT
							B.articulo, A.descripcion, B.sucursal, S.Nombre AS tienda,
							B.costo, B.precio1, B.precioo, B.tipo, B.fechahora
						FROM BDES.dbo.BIDocumentoSincroUCosto AS B
						INNER JOIN BDES.dbo.ESARTICULOS AS A ON A.codigo = B.articulo
						INNER JOIN BDES.dbo.ESSucursales AS S ON S.codigo = B.sucursal
						WHERE CAST(B.fechahora AS DATE) = '$fecha'";

				if($idpara!='*') {
					$sql .= " AND sucursal = '$idpara'";
				}

				$sql .= " ORDER BY fechahora DESC";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se extraen los datos del resultado de la consulta de central
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'       => $row['articulo'],
						'descripcion'  => $row['descripcion'],
						'sucursal'     => $row['sucursal'],
						'tienda'       => $row['tienda'],
						'costoc'       => $row['costo'],
						'costot'       => null,
						'precio1c'     => $row['precio1'],
						'precio1t'     => null,
						'preciooc'     => $row['precioo'],
						'precioot'     => null,
						'tipo'         => $row['tipo'],
						'fechahora'    => $row['fechahora'],
						'observacion'  => null,
					];
				}

				// articulos y sucursales sujetos a auditoria
				$articulos_aud = implode(",", array_column($datos, 'codigo'));
				$sucursalesaud = implode(",", array_column($datos, 'sucursal'));

				if(count($datos) > 0) {
					// Se crea el query para obtener la informacion de conexion de las sucursales
					$sql = "SELECT codigo, nombre, servidor, activa FROM BDES.dbo.ESSucursales WHERE codigo IN($sucursalesaud)";

					// Se ejecuta la consulta en la BBDD
					$sql = $connec->query($sql);

					// Se prepara el array para almacenar los datos obtenidos
					$sucursales= [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$sucursales[] = [
							'codigo'   => $row['codigo'],
							'nombre'   => $row['nombre'],
							'servidor' => $row['servidor'],
							'activa'   => $row['activa'],
						];
					}

					$connec = null;

					if(count($sucursales) > 0) {
						foreach ($sucursales as &$sucursal) {
							// Conectando con SQL de las sucursales
							$connectionInfo = array(
								"Database" => "BDES_POS",
								"UID" => $params['user_sql'],
								"PWD" => $params['password_sql'],
								"ConnectRetryCount" => 3,
							);

							$connec = sqlsrv_connect(strtolower($sucursal['servidor']), $connectionInfo);

							if($connec === false) {
								foreach ($datos as &$dato) {
									if($dato['sucursal']==$sucursal['codigo']) {
										$dato['observacion'] = 'Sin Conexion';
									}
								};
							} else {
								// Se crea el query para obtener los totales por tienda
								$sql = "SELECT codigo, descripcion, costo, precio1, preciooferta
										FROM BDES.dbo.ESARTICULOS
										WHERE codigo IN($articulos_aud)";

								// Se ejecuta la consulta en la BBDD
								$sql = sqlsrv_query($connec, $sql);

								while($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
									$flag = 0;
									foreach ($datos as &$dato) {
										if($dato['codigo']==$row['codigo'] && $dato['sucursal']==$sucursal['codigo']) {
											$dato['costot'] = $row['costo'];
											$dato['precio1t'] = $row['precio1'];
											$dato['precioot'] = $row['preciooferta'];
											$flag = 1;
										}
									}
									if($flag==0) {
										$dato[] = [
											'codigo'       => $row['codigo'],
											'descripcion'  => $row['descripcion'],
											'sucursal'     => $sucursal['codigo'],
											'tienda'       => $sucursal['nombre'],
											'costoc'       => null,
											'costot'       => $row['costo'],
											'precio1c'     => null,
											'precio1t'     => $row['precio1'],
											'preciooc'     => null,
											'precioot'     => $row['preciooferta'],
											'tipo'         => null,
											'fechahora'    => $fecha,
											'observacion'  => 'No Existe en central',
										];
									}
								}
							}
							sqlsrv_close( $connec );
						}
					}
				}

				$datos2 = [];
				foreach ($datos as &$dato) {
					if(intval($dato['costoc'])!=intval($dato['costot'])) {
						$datos2[] = $dato;
					} elseif (intval($dato['precio1c'])!=intval($dato['precio1t'])) {
						$datos2[] = $dato;
					} elseif (intval($dato['preciooc'])!=intval($dato['precioot'])) {
						$datos2[] = $dato;
					}
					if (intval($dato['precio1t'])<intval($dato['costot']) ||
						intval($dato['precio1c'])<intval($dato['costoc'])   ) {
						$datos2[] = $dato;
						# code...
					}
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos2);
				break;

			case 'auditoriaCostos':
				$config = [
					'ashost' => '192.168.125.120',
					'sysnr'  => '00',
					'client' => '300',
					'user'   => 'sapadm',
					'passwd' => 'Multiple2013',
					'trace'  => SapConnection::TRACE_LEVEL_OFF,
				];

				try {
					$connec = new SapConnection($config);

					$funcion = $connec->getFunction('ZGSD_MD_VENTAS');

					$params = [
						'IV_WERKS' => 'GH02'
					];

					print_r( $funcion->invoke($params) );

					$connec->close();
				} catch(SapException $ex) {
					echo 'Exception: ' . $ex->getMessage() . PHP_EOL;
				}
				break;

			case 'listaArticulosBDES':
				// Se extraen los valores del parametro
				// idpara = { $('#fmaterial').val(), $('#fprov').val(), $('#fgrupo').val(), $('#fsubgrupo').val(), $("#select_dptos").val() };
				$params = explode('¬', $idpara);

				// Se crea el query para obtener los totales por tienda
				$sql = "SELECT codigo, descripcion FROM BDES.dbo.ESARTICULOS WHERE activo = 1";

				if($params[0]!='') {
					$sql .= " AND (codigo LIKE '%$params[0]%'";
					$sql .= " OR descripcion LIKE '%$params[0]%')";
				}

				if($params[1]!='') {
					$sql .= " AND codigo IN(SELECT articulo FROM BDES.dbo.ESArticulosxProv WHERE proveedor = '$params[1]')";
				}

				if($params[2]!='') {
					$sql .= " AND grupo = '$params[2]'";
				}

				if($params[3]!='') {
					$sql .= " AND subgrupo = '$params[2]'";
				}

				if($params[4]!='*') {
					$sql .= " AND departamento = '$params[4]'";
				}

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => '<button type="button" title="Seleccionar" onclick="' .
											"seleccion('material', '" .$row['codigo'] . "', '" . $row['descripcion'] . "')".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' .
											ucwords(strtolower($row['descripcion'])) .
										'</button>',
						'nombre'      => $row['descripcion'],
					];
				}

				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;

			case 'listaTiendasBDES':
				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, nombre, servidor, servidorpos
						FROM BDES.dbo.ESSucursales
						WHERE activa = 1
						ORDER BY rtrim(ltrim(nombre))";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'nombre'      => ucwords(strtolower($row['nombre'])),
						'servidor'    => $row['servidor'],
						'servidorpos' => $row['servidorpos'],
					];
				}

				echo json_encode($datos);
				break;

			case 'listaDptosBDES':
				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, descripcion
						FROM BDES.dbo.ESDpto
						WHERE descripcion NOT LIKE '%no usar%' AND LEN(descripcion)>=3
						ORDER BY rtrim(ltrim(descripcion))";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => ucwords(strtolower($row['descripcion'])),
					];
				}
				echo json_encode($datos);
				break;

			case 'listaProvBDES':
				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, rtrim(ltrim(descripcion)) AS descripcion, rif
						FROM BDES.dbo.ESProveedores
						WHERE activo = 1 AND codigo != 0 AND descripcion NOT LIKE '%no usar%'
						AND LEN(descripcion) >= 3";

				// Se chequea si se envio alguna informacion para el filtro
				if($idpara!='') {
					$sql .= " AND (codigo LIKE '%$idpara%'";
					$sql .= " OR descripcion LIKE '%$idpara%'";
					$sql .= " OR rif LIKE '%$idpara%')";
				}

				$sql .= " ORDER BY rtrim(ltrim(descripcion))";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => '<button type="button" title="Seleccionar" onclick="' .
											"seleccion('prov', '" .$row['codigo'] . "', '" . $row['descripcion'] . "')".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' . ucwords(strtolower($row['descripcion'])) .
										'</button>',
						'rif'         => $row['rif'],
						'nombre'      => $row['descripcion']
					];
				}

				echo json_encode($datos);
				break;

			case 'listaGrupBDES':
				// Seextraen los valores de idpara a un array
				// idpara = { $('#fgrupo').val().trim(), $("#select_dptos").val() }
				$params = explode('¬', $idpara);

				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, rtrim(ltrim(descripcion)) AS descripcion
						FROM BDES.dbo.ESGrupos
						WHERE codigo != 0
						AND descripcion NOT LIKE '%no usar%'
						AND LEN( descripcion ) >= 3";

				if($params[0]!='') {
					$sql .= " AND (codigo LIKE '%$params[0]%'";
					$sql .= " OR descripcion LIKE '%$params[0]%')";
				}

				if($params[1]!='*') {
					$sql .= " AND DEPARTAMENTO = '$params[1]'";
				}

				$sql .= " ORDER BY rtrim(ltrim(descripcion))";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => '<button type="button" title="Seleccionar" onclick="' .
											"seleccion('grupo', '" .$row['codigo'] . "', '" . $row['descripcion'] . "')".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' . ucwords(strtolower($row['descripcion'])) .
										'</button>',
						'nombre'      => ucwords(strtolower($row['descripcion'])),
					];
				}

				echo json_encode($datos);
				break;

			case 'listaSubgBDES':
				// Se extraen los valores de idpara para un array
				// idpara = { $('#fsubgrupo').val(), $('#fgrupo').val(), $("#select_dptos").val() }
				$params = explode('¬', $idpara);

				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, descripcion FROM BDES.dbo.ESSubgrupos WHERE 1=1";

				if($params[0]!='') {
					$sql .= " AND (codigo LIKE '%$params[0]'";
					$sql .= " OR descripcion LIKE '%$params[0]')";
				}

				if($params[1]!='') {
					$sql .= " AND GRUPO = '$params[1]'";
				}

				if($params[2]!='*') {
					$sql .= " AND DEPARTAMENTO = '$params[2]'";
				}

				$sql .= " ORDER BY rtrim(ltrim(descripcion))";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => '<button type="button" title="Seleccionar" onclick="' .
											"seleccion('subgrupo', '" .$row['codigo'] . "', '" . $row['descripcion'] . "')".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' . ucwords(strtolower($row['descripcion'])) .
										'</button>',
						'nombre'      => ucwords(strtolower($row['descripcion'])),
					];
				}

				echo json_encode($datos);
				break;

			case 'listaArtBDES':
				$filtro = "1 = 1";
				if($idpara!='') {
					$filtro = " (codigo LIKE '%$idpara%' OR  ".
							" descripcion LIKE '%$idpara%' OR ".
							" barra LIKE '%$idpara%')";
				}
				$sql = "SELECT * FROM
							(SELECT codigo, descripcion,
								(SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
								WHERE b.escodigo = a.codigo AND b.codigoedi = 1) AS barra
							FROM BDES.dbo.ESARTICULOS a
							WHERE activo = 1) AS arts
						WHERE $filtro";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'barra'       => $row['barra'],
						'nombre'      => $row['descripcion'],
						'descripcion' => '<button type="button" title="Seleccionar" onclick="' .
											"seleccion('art', '" .$row['codigo'] . "', '" . $row['descripcion'] . "')".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' .
											ucwords(strtolower($row['descripcion'])) .
										'</button>',
					];
				}

				echo json_encode($datos);
				break;

			case 'listaTransporte':
				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, Descripcion, placa
						FROM BDES.dbo.ESTransporte
						ORDER BY responsable, rtrim(ltrim(Descripcion))";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				// Se prepara el array para almacenar los datos obtenidos
				$camiones = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$camiones[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => ucwords(strtolower($row['Descripcion'])),
						'placa'       => $row['placa']
					];
				}

				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT codigo, descripcion, cedula
						FROM BDES.dbo.ESTransporteConductor
						ORDER BY placa, rtrim(ltrim(descripcion))";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				// Se prepara el array para almacenar los datos obtenidos
				$choferes = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$choferes[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => ucwords(strtolower($row['descripcion'])),
						'cedula'      => $row['cedula']
					];
				}

				echo json_encode(array('camiones' => $camiones, 'choferes' => $choferes));
				break;

			case 'listaTransPen':
				// Se crea el query para obtener la informacion de conexion de las sucursales
				$sql = "SELECT 'T' AS tipo, k.DOCUMENTO AS documento, CAST(k.FECHA AS DATE) AS fecha, us.descripcion AS usuario
						FROM BDES.dbo.BIKARDEX k
						INNER JOIN BDES.dbo.ESUsuarios AS us ON us.codusuario = k.USUARIO
						INNER JOIN BDES.dbo.BISoliPedTran AS pt ON pt.nro_transferencia = k.DOCUMENTO
						WHERE k.TIPO = 17
							AND k.LOCALIDAD = 99
							AND k.TIPOPROC = 1
							AND k.ELIMINADO = 0
							AND k.OBSERVACION != '1'
							AND k.localidad_dest = '$idpara'";

				if($idpara==3) {
					$sql .= "UNION
						SELECT 'F' AS tipo, DOCUMENTO_SERIE, CAST(v.FECHA AS DATE) AS fecha, us.descripcion AS usuario
						FROM BDES.dbo.BIVentas v
						INNER JOIN BDES.dbo.ESUsuarios AS us ON us.codusuario = v.USUARIO
						INNER JOIN BDES.dbo.BISoliPedTran AS pt ON pt.nro_transferencia = v.DOCUMENTO_SERIE
						WHERE v.TIPO = 7
							AND v.LOCALIDAD = 99
							AND v.ELIMINADO = 0
							AND v.DOCUMENTO_NCONTROL != ''
							AND v.DOCUMENTO_NCONTROL NOT LIKE '¬%'
							AND v.CODIGO = 358";
				}

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'tipo'      => $row['tipo'],
						'documento' => $row['documento'],
						'fecha'     => "<span style='display: none'>" . $row['fecha'] . "</span>" . date('d-m-Y', strtotime($row['fecha'])),
						'usuario'   => $row['usuario'],
					];
				}

				echo json_encode(array('data' => $datos));
				break;

			case 'guardaTranspTransf':
				// Extraer los datos desde los parametros
				extract($_POST);
				$idpara = json_decode($idpara);
				$res = '1¬Proceso realizado con éxito.';
				$sql  = "
					DECLARE @idAct AS NUMERIC

					INSERT INTO BDES.dbo.DBCabtransptransf(fecha, codtransp, codcond, prescinto, chof_particular, tran_particular)
					VALUES (CURRENT_TIMESTAMP, $codtra, $codcon, '$presci', '$chopar', '$trapar')

					SELECT @idAct = IDENT_CURRENT('BDES.dbo.DBCabtransptransf')

					INSERT INTO BDES.dbo.DBDettransptransf(id, documento, tipo, locori, tipoproc, eliminado, locdes)
					VALUES ";
				for ($i=0; $i < count($idpara); $i++) {
					$sql .= "(@idAct, " . $idpara[$i] . ", 17, 99, 1, 0, '$locdes'),";
				}
				$sql = substr($sql, 0, -1);
				$idpara = implode(',', $idpara);
				$sql.= "
					UPDATE BDES.dbo.BIKARDEX WITH (READPAST) SET
						OBSERVACION = '1'
					WHERE TIPO = 17 AND LOCALIDAD = 99
						AND TIPOPROC = 1 AND ELIMINADO = 0
						AND OBSERVACION != '1' AND localidad_dest = '$locdes'
						AND DOCUMENTO IN($idpara)
					UPDATE BDES.dbo.BIVentas WITH (READPAST) SET
						DOCUMENTO_NCONTROL = '¬' + DOCUMENTO_NCONTROL
					WHERE DOCUMENTO_SERIE IN($idpara)
						AND LOCALIDAD = 99 AND ELIMINADO = 0
						AND CODIGO = 358
					UPDATE BDES.dbo.BISolicPedido WITH (READPAST) SET
						id_transportado = @idAct
					WHERE solipedi_nrodespacho IN
						(SELECT nro_despacho
						FROM BDES.dbo.BISoliPedTran
						WHERE nro_transferencia IN($idpara))
					UPDATE BDES.dbo.BISoliPedTran WITH (READPAST) SET
						fecha_transporte = CURRENT_TIMESTAMP
						WHERE nro_transferencia IN($idpara)";

				if(!$connec->query($sql)) {
					$res = '0¬No se pudo registrar la informacion.<br>'.$connec->errorInfo()[2];
				}
				echo $res;
				break;

			case 'consultaInv':
				$params = explode('¬', $idpara);
				$fechaini = date("Y-m-d", strtotime($fecha."-" . ($params[0]-1) . " days"));
				$fechafin = $fecha;

				$prov = '';
				$dpto = '';
				$grupo = '';
				$subgrupo = '';
				$material = '';
				$localidad = '';

				if($params[1]!='*') {
					$prov = " WHERE d.MATERIAL IN(SELECT articulo FROM BDES.dbo.ESArticulosxProv WHERE proveedor = $params[1])";
				}
				if($params[2]!='*') {
					$grupo = " AND a.grupo = '$params[2]'";
				}
				if($params[3]!='*') {
					$subgrupo = " AND a.subgrupo = '$params[3]'";
				}
				if($params[4]!='*') {
					$material = " AND d.MATERIAL = '$params[4]'";
				}
				if($params[6]!='*') {
					$dpto = " AND a.departamento = '$params[6]'";
				}
				if($params[7]!='*') {
					$localidad = " AND localidad = '$params[7]'";
				}

				$sql = "SELECT
							d.MATERIAL AS codigo, a.descripcion, s.nombre,
							(SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
							WHERE b.escodigo = d.MATERIAL AND b.codigoedi = 1) AS barra,
							SUM(d.undVen) AS undventas, SUM(d.Existencia) AS existencia,
							(SELECT SUM(e.cantidad-e.usada) FROM BDES.dbo.BIKardexExistencias e
								WHERE e.articulo = d.MATERIAL AND e.localidad = 99
								AND e.almacen IN(5,6)) AS existcedim,
							SUM(d.COSTO) AS costo, SUM(d.SUBTOTAL) AS subtotal, SUM(d.SUBTREAL) AS subtreal
						FROM
							(	SELECT MATERIAL, SUM(CANTIDAD) AS undVen, '0' AS Existencia,
									SUM(CANTIDAD * COSTO) AS COSTO, SUM(CANTIDAD * PRECIO) AS SUBTOTAL,
									SUM(CANTIDAD * PRECIO_REAL) AS SUBTREAL, LOCALIDAD
								FROM BDES_POS.dbo.BIVENTAS_INV
								WHERE CAST(FECHA AS DATE) BETWEEN '$fechaini' AND '$fechafin'
								AND TIPO IN(26, 27) $localidad
								GROUP BY MATERIAL, LOCALIDAD
								UNION
								SELECT ARTICULO, SUM(CANTIDAD), '0', SUM(CANTIDAD * COSTO),
									SUM(SUBTOTAL), SUM(CANTIDAD * PRECIO_ORIGINAL), LOCALIDAD
								FROM BDES.dbo.BIVentasDet
								WHERE CAST(FECHA AS DATE) BETWEEN '$fechaini' AND '$fechafin'
									AND TIPO = 7 AND ELIMINADO = 0 AND ESTADO = 1 $localidad
								GROUP BY ARTICULO, LOCALIDAD
								UNION
								SELECT articulo, '0', SUM(cantidad), '0', '0', '0', localidad
								FROM BDES.dbo.BIKardexMov
								WHERE CAST(fecha AS DATE) <= '$fechafin' AND eliminado = 0 $localidad
								GROUP BY articulo, localidad
							) AS d
						INNER JOIN BDES.dbo.ESARTICULOS a ON a.codigo = d.MATERIAL AND a.activo = 1 $grupo $subgrupo $material $dpto
						INNER JOIN BDES.dbo.ESSucursales s ON s.codigo = d.localidad
						$prov
						GROUP BY d.MATERIAL, a.descripcion, s.nombre
						HAVING $params[5] AND SUM(undVen) <> 0";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'localidad'   => $row['nombre'],
						'codigo'      => $row['codigo'],
						'barra'       => $row['barra'],
						'descripcion' => $row['descripcion'],
						'undventas'   => $row['undventas'],
						'existencia'  => $row['existencia'],
						'existcedim'  => $row['existcedim'],
						'costo'       => $row['costo'],
						'subtotal'    => $row['subtotal'],
						'subtreal'    => $row['subtreal'],
					];
				}

				echo json_encode($datos);
				break;

			case 'listaDocBDES':
				$tipodoc = $_POST['tipodoc'];

				$sql = "SELECT documento, tipo, BIKardex.ESTADO as estado, CAST(FECHA_EMISION AS DATE) AS fecha,
							tercero, descripcion, documento_num AS nrofac
						FROM BDES.dbo.BIKardex
						INNER JOIN BDES.dbo.ESProveedores ON codigo = tercero
						WHERE tipo='$tipodoc'  and eliminado='0' ";

				// Se chequea si se envio alguna informacion para el filtro
				if($idpara!='') {
					$sql .= " AND DOCUMENTO = '" . $idpara . "'";
				}

				$sql .= " ORDER BY DOCUMENTO DESC";

				$sql = $connec->query($sql);

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'documento'   => $row['documento'],
						'nrofac'      => $row['nrofac'],
						'tipo'        => $row['tipo'],
						'estado'      => $row['estado'],
						'fecha'       => $row['fecha'],
						'tercero'     => $row['tercero'],
						'descripcion' => $row['descripcion'],
					];
				}

				echo json_encode($datos);
				break;

			case 'anularrec':
				$sql  = " UPDATE BDES.dbo.BIKardex     SET ELIMINADO = 1 WHERE (TIPO = 14) AND ELIMINADO = 0 AND (ESTADO = 0) AND DOCUMENTO = '" . $idpara . "' ";
				$sql .= " UPDATE BDES.dbo.BIKARDEX_DET SET ELIMINADO = 1 WHERE (TIPO = 14) AND ELIMINADO = 0 AND (ESTADO = 0) AND DOCUMENTO = '" . $idpara . "' ";
				$sql .= " UPDATE BDES.dbo.BIKardexMov  SET ELIMINADO = 1 WHERE (TIPO = 14) AND ELIMINADO = 0 AND (ESTADO = 0) AND DOCUMENTO = '" . $idpara . "' ";

				$sql .= " INSERT INTO BDES.dbo.BICorridaFixInvDet (articulo, almacen, idcorrida, ultid, estado)
							SELECT BDES.dbo.BIKARDEX_DET.MATERIAL as articulo, BDES.dbo.BIKARDEX_DET.ALMACEN as almacen,
							'1' as idcorrida, '0' as ultid, '0' as estado
							FROM BDES.dbo.BIKARDEX_DET
							WHERE (BDES.dbo.BIKARDEX_DET.TIPO = 14) and ELIMINADO = 1 AND DOCUMENTO = '" . $idpara . "'";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				echo true;
				break;

			case 'cerrarrec':
				$sql  = " UPDATE BDES.dbo.BIKardex     SET ESTADO = 1 WHERE (TIPO = 14) AND (ESTADO = 0) AND DOCUMENTO =  '" . $idpara . "' ";
				$sql .= " UPDATE BDES.dbo.BIKARDEX_DET SET ESTADO = 1 WHERE (TIPO = 14) AND (ESTADO = 0) AND DOCUMENTO  = '" . $idpara . "' ";
				$sql .= " UPDATE BDES.dbo.BIKardexMov  SET ESTADO = 1 WHERE (TIPO = 14) AND (ESTADO = 0) AND DOCUMENTO  = '" . $idpara . "' ";

				$sql = $connec->query($sql);

				echo true;
				break;

			case 'bonificacion':
				$sql  = " UPDATE BDES.dbo.BIKARDEX_DET SET TIPO_MOV = 1  WHERE  (TIPO = 14) AND DOCUMENTO = '" . $idpara . "' and TIPO_MOV ='3'";
				$sql .= " UPDATE BDES.dbo.BIKardexMov  SET tipomov = 1   WHERE  (TIPO = 14) AND DOCUMENTO = '" . $idpara . "' and tipomov ='3'";

				$sql = $connec->query($sql);

				echo true;
				break;

			case 'anulardev':
				$sql  = " UPDATE BDES.dbo.BIKardex     SET ELIMINADO = 1 WHERE (TIPO = 30) AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0'";
				$sql .= " UPDATE BDES.dbo.BIKARDEX_DET SET ELIMINADO = 1 WHERE (TIPO = 30) AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0'";
				$sql .= " UPDATE BDES.dbo.BIKardexMov  SET ELIMINADO = 1 WHERE (TIPO = 30) AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0'";

				$sql .= " INSERT INTO BDES.dbo.BICorridaFixInvDet (articulo, almacen, idcorrida, ultid, estado)
							SELECT BDES.dbo.BIKARDEX_DET.MATERIAL as articulo, BDES.dbo.BIKARDEX_DET.ALMACEN as almacen,
							'1' as idcorrida, '0' as ultid, '0' as estado
							FROM BDES.dbo.BIKARDEX_DET
							WHERE (BDES.dbo.BIKARDEX_DET.TIPO = 30) and ELIMINADO = 1 AND DOCUMENTO = '" . $idpara . "'";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				echo true;
				break;

			case 'cerrardev':
				$sql  = " UPDATE BDES.dbo.BIKardex     SET ESTADO = 0 WHERE (TIPO = 30) AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0'";
				$sql .= " UPDATE BDES.dbo.BIKARDEX_DET SET ESTADO = 0 WHERE (TIPO = 30) AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0'";
				$sql .= " UPDATE BDES.dbo.BIKardexMov  SET ESTADO = 0 WHERE (TIPO = 30) AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0'";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				echo true;
				break;

			case 'anulcomp':
				$sql  = " UPDATE BDES.dbo.BIKardex     SET ELIMINADO = 1 WHERE (TIPO = 1) AND ESTADO = 1 AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0'";
				$sql .= " UPDATE BDES.dbo.BIKARDEX_DET SET ELIMINADO = 1 WHERE (TIPO = 1) AND ESTADO = 1 AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0'";

				$sql .= " INSERT INTO BDES.dbo.BICorridaFixInvDet (articulo, almacen, idcorrida, ultid, estado)
							SELECT BDES.dbo.BIKARDEX_DET.MATERIAL as articulo, BDES.dbo.BIKARDEX_DET.ALMACEN as almacen,
							'1' as idcorrida, '0' as ultid, '0' as estado
							FROM BDES.dbo.BIKARDEX_DET
							WHERE (BDES.dbo.BIKARDEX_DET.TIPO = 1) AND ELIMINADO = 1 AND DOCUMENTO = '" . $idpara . "'";

				$sql = $connec->query($sql);

				$sql = "SELECT TOP 1 DOCUMENTO_ORIG FROM BDES.dbo.BIKARDEX_DET WHERE TIPO = 1 AND ELIMINADO = 1 AND DOCUMENTO = '" . $idpara . "'";
				$sql = $connec->query($sql);
				$row = $sql->fetch(\PDO::FETCH_ASSOC);

				$docorig = $row['DOCUMENTO_ORIG'];

				$sql = "UPDATE BDES.dbo.BIKardex  SET ESTADO = 0 WHERE (TIPO = 14) AND (DOCUMENTO = '$docorig') AND ELIMINADO = 0
						UPDATE BDES.dbo.BIKARDEX_DET  SET ESTADO = 0, CANTIDAD_U = 0 WHERE (TIPO = 14) AND (DOCUMENTO = '$docorig')
							AND ELIMINADO = 0
						UPDATE BDES.dbo.BIKardexMov  SET ESTADO = 0 WHERE (TIPO = 14) AND (DOCUMENTO = '$docorig') AND ELIMINADO = 0";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				echo true;
				break;

			case 'fechacomp':
				$fechac = $_POST['fechac'].'T00:00:00.000';
				$sql = "UPDATE BDES.dbo.BIKardex SET FECHA_EMISION = '$fechac', FECHA = '$fechac'
						WHERE TIPO = 1 AND ELIMINADO = 0 AND DOCUMENTO='" . $idpara . "' ";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				echo true;
				break;

			case 'camfact':
				$docpro = $_POST['docpro'];
				$opctip = $_POST['opctip'];
				$sql = "UPDATE BDES.dbo.BIKardex SET documento_num = '$docpro'
						WHERE TIPO = $opctip AND ELIMINADO = 0 AND DOCUMENTO='" . $idpara . "' ";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				echo true;
				break;

			case 'camprov':
				$docpro = $_POST['docpro'];
				$opctip = $_POST['opctip'];

				$sql = "UPDATE BDES.dbo.BIKardex SET tercero = '$docpro'
						WHERE DOCUMENTO='$idpara' AND TIPO = $opctip AND ELIMINADO = 0";

				$sql = $connec->exec($sql);
				if(!$sql) print_r($connec->errorInfo());

				echo true;
				break;

			case 'listaRepBDES':

				$sql = "SELECT documento, tipo, localidad, articulo, almacen, cantidad, costo
						  FROM   BDES.dbo.BIKardexMov
				WHERE  (tipo = 14) AND (estado = 0) AND (eliminado = 0)";

				// Se chequea si se envio alguna informacion para el filtro
				if($idpara!='') {
					$sql .= " AND DOCUMENTO = '" . $idpara . "'";
				}

				$sql .= " ORDER BY DOCUMENTO DESC";


				$sql = $connec->query($sql);

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'documento'   => $row['documento'],
						'tipo'         => $row['tipo'],
						'localidad'    => $row['localidad'],
						'almacen'     => $row['almacen'],
						'articulo'       => $row['articulo'],
						'cantidad' => $row['cantidad'],
						'costo' => $row['costo'],
					];
				}

				echo json_encode(array("data"=>$datos));
				break;

			case 'listaInvBDES':
				$sql = "SELECT  documento, tipo, localidad, material, barra, item, presentacion, cantidad, cantidad_u, cantidad_f, costo, costo_pro,costo_orig, costo_f
						  FROM      BDES.dbo.BIKARDEX_DET
						WHERE   (TIPO = 14) AND (ESTADO = 0) AND (ELIMINADO = 0)";

				// Se chequea si se envio alguna informacion para el filtro
				if($idpara!='') {
					$sql .= " AND DOCUMENTO = '" . $idpara . "'";
				}

				$sql .= " ORDER BY DOCUMENTO DESC";

				$sql = $connec->query($sql);

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'documento'   => $row['documento'],
						'tipo'         => $row['tipo'],
						'localidad'    => $row['localidad'],
						'material'    => $row['material'],
						'barra'     => $row['barra'],
						'item'       => $row['item'],
						'presentacion' => $row['presentacion'],
						'cantidad' => $row['cantidad'],
						'cantidad_u' => $row['cantidad_u'],
						'cantidad_f' => $row['cantidad_f'],
						'costo' => $row['costo'],
						'costo_pro' => $row['costo_pro'],
						'costo_orig' => $row['costo_orig'],
						'costo_f' => $row['costo_f'],
					];
				}

				echo json_encode($datos);
				break;

			case 'cambdoc':

				$farticulo = '';
				$articulo = '';
				$cantidad =  '';
				$costo = '';
				$fcantidad = '';
				$fcosto = '';

				$farticulo = $_POST['farticulo'];
				$fcantidad = $_POST['fcantidad'];
				$fcosto = $_POST['fcosto'];

				$articulo = $_POST['articulo'];
				$cantidad = $_POST['cantidad'];
				$costo = $_POST['costo'];

				if($farticulo!='') {
					$farticulo = "  '$farticulo'";
				} else {
					$farticulo = "  '$articulo'";
				}

				if($fcantidad!='') {
					$fcantidad = " $fcantidad";
				} else {
					$fcantidad = " $cantidad";
				}

				if($fcosto!='') {
					$fcosto = "  $fcosto";
				} else {
					$fcosto = "  $costo";
				}

				$sql = " UPDATE BDES.dbo.BIKardexMov  SET articulo= $farticulo, cantidad= $fcantidad, costo= $fcosto
						WHERE (TIPO = 14) AND DOCUMENTO = '" . $idpara . "' AND ELIMINADO ='0' AND articulo='$articulo' ";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$sql = " UPDATE BDES.dbo.BIKARDEX_DET SET material = $farticulo, barra= $farticulo, cantidad= $fcantidad, cantidad_u= $fcantidad, cantidad_f = $fcantidad,
						costo = $fcosto, costo_pro = $fcosto, costo_orig = $fcosto, costo_f = $fcosto
						WHERE (TIPO = 14) AND DOCUMENTO = $idpara AND ELIMINADO ='0' AND material=$articulo ";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$sql = " INSERT INTO BDES.dbo.BICorridaFixInvDet (articulo, almacen, idcorrida, ultid, estado)
						SELECT BDES.dbo.BIKARDEX_DET.MATERIAL as articulo, BDES.dbo.BIKARDEX_DET.ALMACEN as almacen,
						'1' as idcorrida, '0' as ultid, '0' as estado
						FROM BDES.dbo.BIKARDEX_DET
						WHERE (BDES.dbo.BIKARDEX_DET.TIPO = 14) and ELIMINADO = 0 AND DOCUMENTO = '" . $idpara . "'";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				if($farticulo!=$articulo) {
					$sql = " INSERT INTO BDES.dbo.BICorridaFixInvDet (articulo, almacen, idcorrida, ultid, estado)
							SELECT $articulo as articulo, BDES.dbo.BIKARDEX_DET.ALMACEN as almacen,
							'1' as idcorrida, '0' as ultid, '0' as estado
							FROM BDES.dbo.BIKARDEX_DET
							WHERE (BDES.dbo.BIKARDEX_DET.TIPO = 14) and ELIMINADO = 0 AND DOCUMENTO = '" . $idpara . "'";

					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());
				}

				echo json_encode($datos);
				break;

			case 'consultaInvPedidos':
				$sql="SELECT
						d.MATERIAL, a.descripcion, COALESCE(a.presentacion, 1) AS empaque,
						dp.DESCRIPCION AS departamento,
						COALESCE(SUM(d.ExistLocal), 0) AS ExistLocal,
						COALESCE(SUM(d.ExistCedim), 0) AS ExistCedim,
						COALESCE((SELECT SUM(solipedidet_pedido)
							FROM BDES.dbo.vw_soli_pedi_det
							WHERE solipedi_status <= 1
							AND solipedidet_despachado = 0
							AND solipedidet_codigo = d.material), 0) AS ExistApartado,
						( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
							WHERE b.escodigo = d.MATERIAL AND b.codigoedi = 1) AS barra,
						(SELECT cantayer FROM BDES.dbo.EST_VENTAS60
							WHERE material = d.material AND LOCALIDAD = $idpara) AS rotayer,
						(SELECT cant7dias FROM BDES.dbo.EST_VENTAS60
							WHERE material = d.material AND LOCALIDAD = $idpara) AS rot7dia,
						pr.descripcion AS proveedor
					FROM
						(SELECT articulo AS Material,
							(CASE WHEN localidad=$idpara THEN SUM(cantidad-usada) END) AS ExistLocal,
							(CASE WHEN localidad=99 AND almacen IN(5,6) THEN SUM(cantidad-usada) END) AS ExistCedim,
							localidad
						FROM BDES.dbo.BIKardexExistencias
						WHERE localidad = 99 OR localidad=$idpara
						GROUP BY articulo, localidad, almacen) AS d
					INNER JOIN BDES.dbo.ESARTICULOS a ON a.codigo = d.MATERIAL AND a.activo = 1
					INNER JOIN BDES.dbo.ESDpto dp ON dp.CODIGO = a.departamento
					INNER JOIN BDES.dbo.ESArticulosxProv pxa ON pxa.articulo = d.MATERIAL
					INNER JOIN BDES.dbo.ESProveedores pr ON pr.codigo = pxa.proveedor
					GROUP BY d.MATERIAL, a.descripcion, a.presentacion, a.grupo, a.subgrupo, dp.DESCRIPCION, pr.descripcion
					HAVING (SUM(ExistCedim) > 0)";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {

					$apartado       =(empty($row['ExistApartado'])) ? 0 : $row['ExistApartado'] ;
					$ExistLocalval  =(.000==$row['ExistLocal']) ? 0 : $row['ExistLocal'];
					$ExistCedimlval =(.000==$row['ExistCedim']) ? 0 : $row['ExistCedim'];
					$empaquelval    =(.000==$row['empaque']) ? 0 : $row['empaque'];

					if($empaquelval!=0):
						$cajaCedims = ($ExistCedimlval==0) ? 0 : round($ExistCedimlval/$empaquelval,2);
						$cajaLocal  = ($ExistLocalval==0) ? 0 : round($ExistLocalval/$empaquelval,2);
						$cajaApart  = ($apartado==0) ? 0 : round($apartado/$empaquelval,2);
					else:
						$cajaCedims = 0;
						$cajaLocal  = 0;
						$cajaApart  = 0;
					endif;

					$invDisp    = round($ExistCedimlval - $apartado ,0);
					$cajaDisp   = round($cajaCedims - $cajaApart ,2);
					$barra = '<span title="'.$row['MATERIAL'].'">'.$row['barra'].
							'<span style="display: none;">'.$row['MATERIAL'].'</span>';

					$datos[] = [
						'material'    => $row['MATERIAL'],
						'ExistLocal'  => $ExistLocalval,
						'ExistCedim'  => $ExistCedimlval,
						'departamento'=> $row['departamento'],
						'proveedor'   => $row['proveedor'],
						'descripcion' => $row['descripcion'],
						'barra'       => $barra,
						'empaque'     => $empaquelval,
						'cajaCedims'  => $cajaCedims,
						'cajaLocal'   => $cajaLocal,
						'rotayer'     => '<button type="button" title="Ver Rotación" onclick="' .
											'verDetRotaArtLoc(' .$row['MATERIAL'] . ', ' . $idpara . ')" ' .
											'class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' .
											number_format($row['rotayer']*1, 2).
										'</button>',
						'rot7dia'     => '<button type="button" title="Ver Rotación" onclick="' .
											'verDetRotaArtLoc(' .$row['MATERIAL'] . ', ' . $idpara . ')" ' .
											'class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' .
											number_format($row['rot7dia']*1, 2).
										'</button>',
						'apartado'	  => $apartado,
						'cajaApart'   => $cajaApart,
						'invDisp'	  => $invDisp,
						'cajaDisp'	  => $cajaDisp
					];
				}

				echo json_encode($datos);
				break;

			case 'verDetRotaArtLoc':
				extract($_POST);
				$sql = "SELECT
							est.material, art.descripcion, est.fecha, est.cantayer,
							est.cant7dias, est.cant15dias, est.cant30dias, est.cant45dias,
							est.cant60dias
						FROM BDES.dbo.EST_VENTAS60 AS est
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = est.material
						WHERE est.LOCALIDAD = $idpara AND est.material = $codigo";

				$sql = $connec->query($sql);
				if(!$sql) { print_r($connec->errorInfo()); }

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'material'    => $row['material'],
						'descripcion' => $row['descripcion'],
						'fecha'       => date('d-m-Y', strtotime($row['fecha'])),
						'cantayer'    => number_format($row['cantayer'], 2),
						'cant7dias'   => number_format($row['cant7dias'], 2),
						'cant15dias'  => number_format($row['cant15dias'], 2),
						'cant30dias'  => number_format($row['cant30dias'], 2),
						'cant45dias'  => number_format($row['cant45dias'], 2),
						'cant60dias'  => number_format($row['cant60dias'], 2),
						'dif7dias'    => number_format(($row['cant7dias']-$row['cantayer']), 2),
						'dif15dias'   => number_format(($row['cant15dias']-$row['cant7dias']), 2),
						'dif30dias'   => number_format(($row['cant30dias']-$row['cant15dias']), 2),
						'dif45dias'   => number_format(($row['cant45dias']-$row['cant30dias']), 2),
						'dif60dias'   => number_format(($row['cant60dias']-$row['cant45dias']), 2),
						'prom7dias'   => number_format( ($row['cant7dias']-$row['cantayer']) / 7, 2),
						'prom15dias'  => number_format( ($row['cant15dias']-$row['cant7dias']) / 7, 2),
						'prom30dias'  => number_format( ($row['cant30dias']-$row['cant15dias']) / 15, 2),
						'prom45dias'  => number_format( ($row['cant45dias']-$row['cant30dias']) / 15, 2),
						'prom60dias'  => number_format( ($row['cant60dias']-$row['cant45dias']) / 15, 2),
					];
				}

				echo json_encode($datos);
				break;

			case 'guardarSolicPedi':
				extract($_POST);
				$pedido   = explode(',', $pedido);
				$material = explode(',', $material);
				$exicedim = explode(',', $exicedim);
				$exilocal = explode(',', $exilocal);
				$empaque  = explode(',', $empaque);

				$sql = "INSERT INTO BDES.dbo.BISolicPedido(
							solipedi_fechasoli,localidad,solipedi_usuariosoli,
							solipedi_fechadesp,solipedi_usuariodesp,solipedi_status)
						VALUES (CURRENT_TIMESTAMP, ". $tienda .", '$usidnom', NULL, 0, 0)";
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
							$sql .= "(" .$codid. ",".$material[$i].",".$empaque[$i].",".
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

			case 'consultaListaPedidos':
				$params = explode('¬', $idpara);

				$localidad = $params[0];
				$estado = $params[1];

				$localidad = ($localidad == '*') ? "LIKE '%'" : " = $localidad" ;

				$sql = "SELECT * FROM BDES.dbo.BISolicPedido
				WHERE solipedi_status = $estado AND centro_dist = 99 AND localidad $localidad";

				if($params[2]==1) {
					$sql .= " AND solipedi_id IN (
							SELECT distinct solipedi_id
							FROM BDES.dbo.vw_soli_pedi_det
							WHERE solipedidet_existlocal <= $params[3]
							AND solipedidet_despachado = 0
							AND localidad $localidad)";
				}

				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if ($row['solipedi_fechaespera'] != '') {
						$solipedi_fechasoli   = new DateTime($row['solipedi_fechasoli']);
						$solipedi_fechaespera = new DateTime($row['solipedi_fechaespera']);
						$diasp = $solipedi_fechasoli->diff($solipedi_fechaespera);
						$diasp = $diasp->days;
					}else{
						$diasp = '0';
					}
					if ($row['solipedi_status'] == 2) {
						$solipedi_fechasoli = new DateTime($row['solipedi_fechasoli']);
						$solipedi_fechadesp = new DateTime($row['solipedi_fechadesp']);
						$diase = $solipedi_fechasoli->diff($solipedi_fechadesp);
						$diase = $diase->days;
					}else{
						$diase = '0';
					}

					$location = getDataLocation($row['localidad']);

					$datos[]=[
						'fechaPedido' 	=>  date('d-m-Y H:i', strtotime($row['solipedi_fechasoli'])),
						'localidad'   	=>  $row['localidad'],
						'localidadName'	=>  $location['nombre'],
						'solicitadoPor'	=>  $row['solipedi_usuariosoli'],
						'numero'	  	=>  $row['solipedi_id'],
						'numerodesp'	=>  $row['solipedi_nrodespacho'],
						'estado'	  	=>  $row['solipedi_status'],
						'fechaEspera'	=> ($row['solipedi_fechaespera']!=null?date('d-m-Y H:i', strtotime($row['solipedi_fechaespera'])):null),
						'fechadesp'     => ($row['solipedi_fechadesp']!=null?date('d-m-Y H:i', strtotime($row['solipedi_fechadesp'])):null),
						'usuariodesp'   =>  $row['solipedi_usuariodesp'],
						'diase'         =>  $diase,
						'dias'			=>  $diasp
					];
				}
				// print_r($datos);
				echo json_encode($datos);
				break;

			case 'buscardetpedidos':
				extract($_POST);
				if (!empty($idpara)) {
					$pedidos = implode(',',$idpara);
					$tienda = implode(',', $tienda);
					$sql = "SELECT DISTINCT
								d.solipedidet_codigo,
								CASE WHEN d.solipedidet_empaque = 0 THEN 1 ELSE d.solipedidet_empaque END AS empaque,
								( SELECT TOP 1 a.descripcion
									FROM BDES.dbo.ESARTICULOS AS a
									WHERE a.codigo= d.solipedidet_codigo ) AS descripcion,
								( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
									WHERE b.escodigo = d.solipedidet_codigo
									AND b.codigoedi = 1) AS barra,
								( SELECT TOP 1 SUM(c.solipedidet_pedido)
									FROM BDES.dbo.BISoliPediDet AS c
									WHERE c.solipedidet_codigo= d.solipedidet_codigo AND c.solipedi_id IN ($pedidos)
									GROUP BY solipedidet_codigo ) AS total_pedidos,
								COALESCE(( SELECT SUM(solipedidet_pedido)
									FROM BDES.dbo.vw_soli_pedi_det
									WHERE solipedi_status <= 1 AND centro_dist = 99
									AND solipedidet_despachado = 0
									AND solipedidet_codigo = d.solipedidet_codigo ), 0) AS apartado,
								COALESCE( (SELECT SUM(cantidad-usada)
									FROM BDES.dbo.BIKardexExistencias
									WHERE localidad=99 AND almacen IN(5,6)
									AND articulo = d.solipedidet_codigo), 0) AS ExistCedim
							FROM BDES.dbo.vw_soli_pedi_det AS d WHERE d.solipedi_id IN ($pedidos)
							AND d.solipedidet_despachado = 0";

					if($priori==1) {
						$sql .= " AND d.solipedidet_existlocal <= $exlomi";
					}

					$sql = $connec->query($sql);
					// print_r($sql);
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[]=[
							'codigo'        => $row['solipedidet_codigo'],
							'barra'         => $row['barra'],
							'descripcion'   => $row['descripcion'],
							'apartado'      => $row['apartado'],
							'existcedim'    => $row['ExistCedim']-$row['apartado'],
							'total_pedidos' => $row['total_pedidos'],
							'empaque'       => $row['empaque'],
						];
					}
				}else{
					$datos = '';
				}

				echo json_encode($datos);
				break;

			case 'listaArticulosDispo':
				$idpara = ($idpara!=''?' AND a.codigo = '.$idpara:$idpara);
				// Se prepara la consulta de articulos disponibles en cedim
				$sql = "SELECT a.codigo,
							(SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
							WHERE b.escodigo = a.codigo AND b.codigoedi = 1) AS barra,
							a.descripcion, a.presentacion AS empaque,
							COALESCE(SUM(solipedidet_pedido), 0) AS apartado,
							COALESCE(SUM(d.cantidad - d.usada), 0) AS existcedim
						FROM BDES.dbo.ESARTICULOS a
							INNER JOIN BDES.dbo.BIKardexExistencias d ON
								a.codigo = d.articulo
								AND d.localidad = 99 AND d.almacen IN(5,6)
							LEFT JOIN BDES.dbo.vw_soli_pedi_det p ON
								p.solipedidet_codigo = a.codigo
								AND p.solipedi_status <= 1 AND centro_dist = 99
								AND p.solipedidet_despachado = 0
						WHERE a.activo = 1 $idpara
						GROUP BY a.codigo, a.descripcion, a.presentacion
						HAVING (SUM(d.cantidad - d.usada) > 0)";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se obtienen los registros del resultado de la consulta
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[]=[
						'codigo'      => $row['codigo'],
						'barra'       => $row['barra'],
						'descripcion' => '<button type="button" title="Seleccionar" onclick="' .
											"agregararticulo('" .
												$row['codigo'] . "', '" .
												$row['barra'] . "', '" .
												$row['descripcion'] . "', " .
												$row['apartado'] . ", " .
												$row['empaque'] . ", " .
												$row['existcedim'] . ")".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' .
											ucwords(strtolower($row['descripcion'])) .
										'</button>',
						'apartado'   => $row['apartado'],
						'existcedim' => $row['existcedim']-$row['apartado']
					];
				}

				echo json_encode($datos);
				break;

			case 'listaArticulosDispoCedim':
				// Se prepara la consulta de articulos disponibles en cedim
				$sql = "SELECT a.*, COALESCE(SUM(d.cantidad - d.usada), 0) AS existcedim
						FROM
							(SELECT a.codigo,
								( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
								WHERE b.escodigo = a.codigo AND b.codigoedi = 1) AS barra,
								a.descripcion, a.presentacion AS empaque, COALESCE ( SUM ( solipedidet_pedido ), 0 ) AS apartado
							FROM BDES.dbo.ESARTICULOS a
								LEFT JOIN BDES.dbo.vw_soli_pedi_det p ON p.solipedidet_codigo = a.codigo
									AND p.solipedi_status <= 1 AND centro_dist = 99 AND p.solipedidet_despachado = 0
							WHERE a.activo = 1
							GROUP BY a.codigo, a.descripcion, a.presentacion) AS a
						INNER JOIN BDES.dbo.BIKardexExistencias d ON a.codigo = d.articulo
							AND d.localidad = 99 AND d.almacen IN(5, 6)";

				if($idpara!='') {
					$sql .= "
						WHERE
							a.codigo LIKE '%$idpara%'
							OR a.descripcion LIKE '%$idpara%'
							OR a.barra LIKE '%$idpara%'
						GROUP BY a.codigo, a.descripcion, a.empaque, a.apartado, a.barra
						HAVING (SUM(d.cantidad - d.usada) > 0)";
				}

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se obtienen los registros del resultado de la consulta
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[]=[
						'codigo'      => $row['codigo'],
						'barra'       => $row['barra'],
						'descripcion' => '<button type="button" title="Seleccionar" onclick="' .
											"agregararticulo('" .
												$row['codigo'] . "', '" .
												$row['barra'] . "', '" .
												$row['descripcion'] . "', " .
												$row['apartado'] . ", " .
												$row['empaque'] . ", " .
												$row['existcedim'] . ")".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' .
											ucwords(strtolower($row['descripcion'])) .
										'</button>',
						'apartado'   => $row['apartado'],
						'existcedim' => $row['existcedim']-$row['apartado']
					];
				}

				echo json_encode($datos);
				break;

			case 'procesarPedidosDespacho':
				extract($_POST);
				if (!empty($id_data)) {
					/* BUSCO EL VALOR MAXIMO DEL NRO DE PEDIDO DESPACHADOS*/
					$sql ="SELECT MAX(CONVERT(int, solipedi_nrodespacho)) + 1 as nro_despacho FROM BDES.dbo.BISolicPedido";
					$sql  = $connec->query($sql);
					$sql = $sql->fetch();
					$nrodespacho = (empty($sql['nro_despacho'])) ? 1 : $sql['nro_despacho'];

					/* ACTUALIZO LA TABLA PEDIDOS CON NRO DE DESPACHO, FECHA Y USUARIO*/
					$pedidos = implode(',',$id_data);
					$sql2 = "UPDATE BDES.dbo.BISolicPedido SET
								solipedi_fechaespera = CURRENT_TIMESTAMP,
								solipedi_usuarioespera = '$usidnom',
								solipedi_nrodespacho = $nrodespacho,
								solipedi_status = 1,
								solipedi_responsable = '$nomresp'
							WHERE solipedi_id IN ($pedidos)";
					$sql2 = $connec->query($sql2);

					/* ACTUALIZO LA TABLA DETAPEDIDOS CON NRO DE DESPACHO*/
					$sql3 = "UPDATE BDES.dbo.BISoliPediDet SET
								solipedidet_nrodespacho = $nrodespacho
							WHERE solipedi_id IN ($pedidos)";
					$sql3 = $connec->query($sql3);

					/* ACTUALIZO LA TABLA DETAPEDIDOS CON LA NUEVA CANTIDAD*/
					for ($i=0; $i < count($datacant); $i++):
						$sql4 = "SELECT COUNT(*) FROM BDES.dbo.BISoliPediDet
								WHERE solipedidet_codigo = $datacod[$i]
								AND solipedidet_nrodespacho = $nrodespacho";

						$sql4 = $connec->query($sql4);
						$sql4 = $sql4->fetchColumn();

						if($sql4 > 0) {
							$sql4 = "UPDATE BDES.dbo.BISoliPediDet SET
										solipedidet_pedido_despa =".$datacant[$i]."
									WHERE solipedidet_codigo=".$datacod[$i]."
									AND solipedidet_nrodespacho=".$nrodespacho;
							$sql4 = $connec->query($sql4);
						} else {
							$sql4 = "INSERT INTO BDES.dbo.BISoliPediDet(solipedidet_pedido_despa,
										solipedi_id, solipedidet_codigo, solipedidet_empaque,
										solipedidet_existcedim, solipedidet_existlocal,
										solipedidet_pedido, solipedidet_nrodespacho)
									VALUES (
										$datacant[$i], $pedidos[0], $datacod[$i],
										$dataempaque[$i], $datacedim[$i], 0,
										$datacant[$i] , $nrodespacho
									)";

							$sql4 = $connec->query($sql4);
							if(!$sql4) {
								print_r($connec->errorInfo());
							}
						}
					endfor;

					if(($sql) && ($sql2) && ($sql3) && ($sql4)):
						$result = '1¬Orden procesada correctamente.¬'.$nrodespacho;
					else:
						$result = '0¬Hubo un error, no se creo la orden';
					endif;
				}else{
					$result = '0¬Hubo un error, no se creo la orden, avisar a tecnologia';
				}
				echo json_encode($result);
				break;

			case 'marcarPedidosDespacho':
				extract($_POST);
				if(!empty($id_data)) {
					/* ACTUALIZO LA TABLA PEDIDOS CON NRO DE DESPACHO, FECHA Y USUARIO*/
					$pedidos = implode(',',$id_data);
					$codigos = implode(',',$datacod);
					$sql = "UPDATE BDES.dbo.BISoliPediDet SET solipedidet_despachado = 1
							WHERE solipedi_id IN ($pedidos)
							AND solipedidet_codigo IN($codigos)";

					$sql = $connec->query($sql);

					foreach ($id_data as $pedido) {
						$sql = "SELECT
								(SELECT COUNT(*)
									FROM BISoliPediDet
									WHERE solipedidet_despachado = 1
									AND solipedi_id = $pedido) AS marcados,
								(SELECT COUNT(*)
									FROM BISoliPediDet
									WHERE solipedi_id = $pedido) AS pedidos";

						$sql = $connec->query($sql);
						$row = $sql->fetch(\PDO::FETCH_ASSOC);
						if($row['marcados']==$row['pedidos']) {
							$sql = "UPDATE BDES.dbo.BISolicPedido SET solipedi_status = 4
									WHERE solipedi_id = $pedido;
									UPDATE BDES.dbo.BISoliPediDet SET solipedidet_pedido_dest = $nvoped
									WHERE solipedi_id = $pedido;";
							$sql = $connec->query($sql);
						} else {
							$sql = "UPDATE BDES.dbo.BISolicPedido SET solipedi_status = 0
									WHERE solipedi_id = $pedido";
							$sql = $connec->query($sql);
						}
					}

					$sql = "UPDATE BDES.dbo.BISolicPedido SET solipedi_status = 1
								WHERE solipedi_id = $nvoped;";

					$sql = $connec->query($sql);

					if($sql) {
						$result = '1¬Proceso Realizado Correctamente.';
					} else {
						$result = '0¬Hubo un error, no se pudo marcar.';
					}
				}else{
					$result = '0¬Hubo un error, no se pudo procesar, avisar a tecnologia.';
				}
				echo json_encode($result);
				break;

			case 'cancelarPedidosDespacho':
				extract($_POST);
				if(!empty($id_data)) {
						/* ACTUALIZO LA TABLA PEDIDOS CON NRO DE DESPACHO, FECHA Y USUARIO*/
						$pedidos = implode(',',$id_data);
						$sql = "UPDATE BDES.dbo.BISolicPedido SET
									solipedi_fechadesp   = CURRENT_TIMESTAMP,
									solipedi_usuariodesp = '$usidnom',
									solipedi_status      = 2
								WHERE solipedi_id IN ($pedidos)";
						$sql = $connec->query($sql);

						if($sql) {
							$result = '1¬Orden eliminada correctamente.';
						} else {
							$result = '0¬Hubo un error, no se elimino la orden';
						}
				}else{
					$result = '0¬Hubo un error, no se elimino la orden, avisar a tecnologia';
				}
				echo json_encode($result);
				break;

			case 'procSoliPedi':
				extract($_POST);

				$despachos = '';
				foreach ($idpara as $key => $value) {
					$despachos .= $value['nrodesp'] . ',';
				}

				$despachos = substr($despachos, 0, -1);

				$sql = "SELECT solipedi_id, solipedi_nrodespacho
						FROM BDES.dbo.BISolicPedido
						WHERE solipedi_nrodespacho IN($despachos)";

				$sql2 = $sql;
				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
					$result = '0¬Hubo un error, no se procesaron las ordenes<br>'.
							   $sql2.'<br>'.$connec->errorInfo()[2];
					echo $result;
					break;
				} else {
					// Se prepara el array para almacenar los datos obtenidos
					$pedidos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$pedidos[]=[
							'nro_pedido' => $row['solipedi_id'],
							'nro_despacho' => $row['solipedi_nrodespacho']
						];
					}

					$insert = [];
					$sql = '';
					for($i=0;$i<count($idpara);$i++) {
						$arr = explode(',', $idpara[$i]['nrostran']);
						foreach ($arr as $dato) {
							$nroped = $pedidos[array_search($idpara[$i]['nrodesp'], array_column($pedidos, 'nro_despacho'))]['nro_pedido'];
							$insert[] = [
								'nro_pedido'        => $nroped,
								'nro_despacho'      => $idpara[$i]['nrodesp'],
								'nro_transferencia' => $dato,
							];
							if($idpara[$i]['tipo']==4) {
								$despacho = $idpara[$i]['nrodesp'];
								$sql.= "UPDATE BDES.dbo.BIVentas SET
											DOCUMENTO_NCONTROL =
												(CASE WHEN LEN(DOCUMENTO_NCONTROL) = 0 THEN '$despacho'
												ELSE DOCUMENTO_NCONTROL + ',' + '$despacho' END)
										WHERE TIPO = 7 AND LOCALIDAD = 99 AND CODIGO = 358
											AND ELIMINADO = 0 AND DOCUMENTO_SERIE = '$dato'
											AND DOCUMENTO_NCONTROL NOT LIKE '%$despacho%'; ";
							}
						}
					}

					if(count($insert)>0) {
						$sql.= "INSERT INTO BDES.dbo.BISoliPedTran(nro_despacho, nro_transferencia, nro_pedido)
							VALUES";
						foreach ($insert as $dato) {
							$sql.= '('.$dato['nro_despacho'].','.$dato['nro_transferencia'].','.$dato['nro_pedido'].'),';
						}

						$sql = substr($sql, 0, -1) . '; ';
					}

					// Se extraen los datos del parametro tipo array
					for($i=0;$i<count($idpara);$i++) {
						$despacho = $idpara[$i]['nrodesp'];
						$tipo = $idpara[$i]['tipo'];
						$sql.= "UPDATE BDES.dbo.BISolicPedido SET
									solipedi_fechadesp = CURRENT_TIMESTAMP,
									solipedi_usuariodesp = '$usidnom',
									solipedi_status = 3
								WHERE solipedi_nrodespacho = '$despacho';";
					}

					$sql2 = $sql;
					$sql = $connec->query($sql);
					if(!$sql) {
						print_r($connec->errorInfo());
						$result = '0¬Hubo un error, no se procesaron las ordenes<br>'.
								   $sql2.'<br>'.$connec->errorInfo()[2];
					} else {
						$result = '1¬Ordenes procesadas correctamente.';
					}

					echo $result;
					break;
				}

			case 'consLstPreparacion':
				$idpara = ($idpara == '*') ? "= ped.localidad" : "= $idpara" ;

				$sql = "SELECT DISTINCT suc.Nombre AS localidad, suc.codigo AS local_id,
							RIGHT( ( CAST('00000' AS VARCHAR) + CAST(ped.solipedi_nrodespacho AS VARCHAR) ), 5) AS solipedi_nrodespacho,
							ped.solipedi_fechaespera, DATEDIFF(day, ped.solipedi_fechaespera, CURRENT_TIMESTAMP) AS dias,
							ped.solipedi_responsable, ped.solipedi_nrodespacho AS nrodespacho
						FROM BDES.dbo.BISolicPedido ped
						INNER JOIN BDES.dbo.ESSucursales suc ON suc.codigo = ped.localidad
						WHERE ped.solipedi_status = 1 AND centro_dist = 99 AND ped.localidad $idpara";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[]=[
						'localidad'   =>  $row['localidad'],
						'local_id'    =>  $row['local_id'],
						'iddespacho'  =>  $row['nrodespacho'],
						'numerodesp'  =>  $row['solipedi_nrodespacho'],
						'fechaEspera' => ($row['solipedi_fechaespera']!=null?date('d-m-Y H:i', strtotime($row['solipedi_fechaespera'])):null),
						'responsable' =>  $row['solipedi_responsable'],
						'dias'        =>  $row['dias'],
						'transfer'    =>  ''
					];
				}

				foreach ($datos as &$dato) {
					$sql = "SELECT bk.DOCUMENTO FROM
							BDES.dbo.BIKardex bk WHERE
							bk.PRECINTO LIKE '" . $dato['numerodesp'] . "%'
							AND bk.OBSERVACION != '1' AND bk.TIPO = 17
							AND bk.LOCALIDAD = 99 AND bk.TIPOPROC = 1 AND bk.ELIMINADO = 0
							AND bk.DOCUMENTO NOT IN(
								SELECT nro_transferencia
								FROM BDES.dbo.BISoliPedTran)";

					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());
					$transfer = '';
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$transfer .= $row['DOCUMENTO'] . ',';
					}
					if($transfer!='') {
						$dato['transfer'] = substr($transfer, 0, -1);
					}
				}

				echo json_encode($datos);
				break;

			case 'consLstProcesados':
				$idpara = ($idpara == '*') ? "LIKE '%'" : " = $idpara" ;

				$sql = "SELECT DISTINCT suc.Nombre AS localidad,
							RIGHT( ( CAST('00000' AS VARCHAR) + CAST(ped.solipedi_nrodespacho AS VARCHAR) ), 5) AS solipedi_nrodespacho,
							ped.solipedi_fechasoli, ped.solipedi_fechadesp,
							DATEDIFF(day, ped.solipedi_fechasoli, ped.solipedi_fechadesp) AS dias,
							RIGHT( ( CAST('00000' AS VARCHAR) + CAST(ped.solipedi_id AS VARCHAR) ), 5) AS solipedi_id,
							ped.solipedi_nrodespacho AS nrodespacho
						FROM BDES.dbo.BISolicPedido ped
						INNER JOIN BDES.dbo.ESSucursales suc ON suc.codigo = ped.localidad
						WHERE ped.solipedi_status >= 3 AND centro_dist = 99 AND id_transportado = 0 AND ped.localidad $idpara";

				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$txt = '<button type="button" title="Ver Despacho" onclick="mostrarDespacho('.$row['nrodespacho'].')"
								class="btn btn-link m-0 p-0">'.$row['solipedi_nrodespacho'].
							'</button>';
					$datos[]=[
						'localidad'   => $row['localidad'],
						'numerosoli'  => $row['solipedi_id'],
						'fechasoli'   => ($row['solipedi_fechasoli']!=null?date('d-m-Y H:i', strtotime($row['solipedi_fechasoli'])):null),
						'numerodesp'  => $txt,
						'iddespacho'  => $row['nrodespacho'],
						'fechadesp'   => ($row['solipedi_fechadesp']!=null?date('d-m-Y H:i', strtotime($row['solipedi_fechadesp'])):null),
						'dias'        => $row['dias'],
						'transfer'    => ''
					];
				}

				foreach ($datos as &$dato) {
					$sql = "SELECT nro_transferencia FROM
							BDES.dbo.BISoliPedTran WHERE
							nro_despacho = '" . $dato['iddespacho'] . "'";

					$sql = $connec->query($sql);
					$transfer = '';
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						if($transfer=='') {
							$transfer .= $row['nro_transferencia'] . ', ';
						} else if(strpos($transfer, $row['nro_transferencia'])===false) {
							$transfer .= $row['nro_transferencia'] . ', ';
						}
					}
					if($transfer!='') {
						$dato['transfer'] = substr($transfer, 0, -2);
					}
				}

				foreach ($datos as &$dato) {
					$sql = "SELECT DOCUMENTO_SERIE FROM
							BDES.dbo.BIVentas WHERE
							DOCUMENTO_NCONTROL LIKE '%" . $dato['iddespacho'] . "%'";

					$sql = $connec->query($sql);
					$transfer = '';
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						if($transfer=='') {
							$transfer .= $row['DOCUMENTO_SERIE'] . ', ';
						} else if(strpos($transfer, $row['DOCUMENTO_SERIE'])===false) {
							$transfer .= $row['DOCUMENTO_SERIE'] . ', ';
						}
					}
					if($transfer!='') {
						$dato['transfer'] = substr($transfer, 0, -2);
					}
				}

				echo json_encode($datos);
				break;

			case 'validarTrans':
				// Se prepara la consulta para validar las trasnferencias ingresadas
				$sql = "SELECT COUNT(*) AS totdocs
						FROM BDES.DBO.BIKARDEX
						WHERE TIPO = 17 AND LOCALIDAD = 99 AND ELIMINADO = 0 AND DOCUMENTO = '$idpara'";
				$sql = $connec->query($sql);
				$row = $sql->fetch();
				if($row['totdocs']>0) {
					echo 1;
				} else {
					echo 0;
				}
				break;

			case 'validarNDE':
				// Se prepara la consulta para validar las facturas ingresadas
				$sql = "SELECT COUNT(*) AS totdocs
						FROM BDES.DBO.BIVentas
						WHERE TIPO = 7 AND LOCALIDAD = 99 AND CODIGO = 358
						AND ELIMINADO = 0 AND DOCUMENTO_SERIE = '$idpara'
						AND DOCUMENTO_NCONTROL = ''";
				$sql = $connec->query($sql);
				$row = $sql->fetch();
				if($row['totdocs']>0) {
					echo 1;
				} else {
					echo 0;
				}
				break;

			case 'monitorlistaPedidos':
				// Se prepara los datos para el monitor
				$sql = "SELECT ped.localidad,
							ti.nombre AS sucursal, ped.solipedi_id AS id,
							RIGHT( ( CAST('00000' AS VARCHAR) + CAST(ped.solipedi_id AS VARCHAR) ), 5) AS numero,
							ped.solipedi_fechasoli AS fecha,
							ped.solipedi_responsable AS responsable,
							ped.solipedi_fechaespera AS fecprepara,
							ped.solipedi_fechadesp AS fecprocesa,
							ped.solipedi_nrodespacho AS nrodespacho,
							pt.fecha_transporte AS transportado,
							pt.nro_transferencia AS transferencia
						FROM BDES.dbo.BISolicPedido AS ped
						INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = ped.localidad
						LEFT JOIN BDES.dbo.BISoliPedTran AS pt ON pt.nro_despacho = ped.solipedi_nrodespacho
							AND YEAR(pt.fecha_transporte)>1900
						WHERE ped.solipedi_status NOT IN(2,4) AND centro_dist = 99 AND pt.fecha_transporte IS NULL OR
							CAST(pt.fecha_transporte AS DATE) = CAST(CURRENT_TIMESTAMP AS DATE)";

				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				$succ1 = '<div style="background-color: #0E6707; letter-spacing: -0.8px; display: flex; color: #FFF">&nbsp;';
				$dang1 = '<div style="background-color: #961C1C; letter-spacing: -0.8px; display: flex; color: #FFF">&nbsp;';
				$findi = '</div>';
				$color1= 'uno';
				$color2= 'dos';
				$nroped = '';
				$color = $color1;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$fecprepara = $dang1 . $findi;
					$fecprocesa = $dang1 . $findi;
					$transporte = $dang1 . $findi;
					if($row['fecprepara'] != null) {
						$fecprepara = $succ1 .
								' <span class="text-warning">[' .
								date('d-m-Y H:i', strtotime($row['fecprepara'])) . ']</span>' .
								'<span class="text-white">&nbsp;&nbsp;('.
								'<button type="button" title="Ver Despacho" onclick="mostrarDespacho('.
									$row['nrodespacho'].')"
									class="btn btn-sm btn-link bg-warning m-0 p-0 text-left"
									style="white-space: normal; line-height: 1;">'.str_pad($row['nrodespacho'], 5, "0", STR_PAD_LEFT).
								'</button>)'.
								'</span>&nbsp;&nbsp;' .
								$row['responsable'] .
							$findi;
					}
					if($row['fecprocesa'] != null) {
						$fecprocesa = $succ1 .'<div class="text-center m-0 p-0 w-100">' .
									 date('d-m-Y H:i', strtotime($row['fecprocesa'])) .
									 '</div>' . $findi;
					}
					if($row['transportado'] != null) {
						if($row['localidad']!=3) {
							$txt = '<button type="button" title="Ver Transferencia" onclick="mostrarTransfe('.$row['transferencia'].')"';
						} else {
							$txt = '<button type="button" title="Ver Factura" onclick="mostrarFactura('.$row['transferencia'].')"';
						}
						$txt.='class="btn btn-sm btn-link bg-warning text-warning m-0 p-0 text-left"
									style="white-space: normal; line-height: 1;">'.$row['transferencia'].
								'</button>';
						$transporte = $succ1 .'<div class="text-center m-0 p-0 w-100">'. '(' . $txt .') ' .
							date('d-m-Y H:i', strtotime($row['transportado'])) . '</div>' . $findi;
					}

					$txt = '<button type="button" title="Ver Pedido" onclick="mostrarPedido('.$row['id'].')"
								class="btn btn-link m-0 p-0 text-left"
								style="white-space: normal; line-height: 1;">'.$row['numero'].
							'</button>';
					if($nroped!=$row['numero']) {
						$color = ($color==$color2?$color1:$color2);
						$nroped = $row['numero'];
					}
					$datos[]=[
						'colorfila'  => $color,
						'sucursal'   => '&emsp;'.$row['sucursal'],
						'numero'     => $txt,
						'fecha'      => '<span style="display: none;">'.
											$row['fecha'].
										'</span>'.
										($row['fecha']!=null?date('d-m-Y H:i', strtotime($row['fecha'])):null),
						'fecprepara' => $fecprepara,
						'fecprocesa' => $fecprocesa,
						'transporte' => $transporte,
					];
				}

				// Se retorna el resultado de la lista
				echo json_encode(array("data"=>$datos));
				break;

			case 'mostrarPedido':
				// Se prepara la consulta a la base de datos
				$sql = "SELECT DISTINCT d.solipedi_status,
							d.solipedidet_codigo,
							( CASE WHEN d.solipedidet_empaque = 0 THEN 1
							  ELSE d.solipedidet_empaque END) AS empaque,
							( SELECT TOP 1 a.descripcion
								FROM BDES.dbo.ESARTICULOS AS a
								WHERE a.codigo= d.solipedidet_codigo ) AS descripcion,
							( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
								WHERE b.escodigo = d.solipedidet_codigo
								AND b.codigoedi = 1) AS barra,
							( SELECT TOP 1 SUM ( c.solipedidet_pedido )
								FROM BDES.dbo.BISoliPediDet AS c
								WHERE c.solipedidet_codigo= d.solipedidet_codigo AND c.solipedi_id = $idpara
								GROUP BY solipedidet_codigo ) AS total_pedidos
						FROM BDES.dbo.vw_soli_pedi_det AS d
						WHERE d.solipedi_id = $idpara";

				$sql = $connec->query($sql);

				// Se prepara el array para los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[]=[
						'codigo'      => $row['solipedidet_codigo'],
						'barra'       => $row['barra'],
						'descripcion' => $row['descripcion'],
						'unidad'      => $row['total_pedidos'],
						'cajas'       => round(($row['total_pedidos'] / $row['empaque']), 2),
						'empaque'     => $row['empaque'],
						'anular'      => (strpos('0,6', $row['solipedi_status'])!==false)?1:0,
					];
				}

				echo json_encode($datos);
				break;

			case 'mostrarTransfe':
				// Se prepara la consulta a la base de datos
				$sql = "SELECT bk.BARRA, a.descripcion AS DESCRIPCION, bk.PRESENTACION, bk.CANTIDAD,
							(bk.CANTIDAD/bk.PRESENTACION) AS BULTOS, t.nombre AS LOCALIDAD
						FROM BDES.dbo.BIKARDEX_DET AS bk
						INNER JOIN BDES.dbo.ESARTICULOS AS a ON a.codigo = bk.MATERIAL
						INNER JOIN BDES.dbo.ESSucursales AS t ON t.codigo = bk.DESTINO
						WHERE bk.TIPO = 17 AND bk.LOCALIDAD = 99 AND bk.ELIMINADO = 0 AND bk.DOCUMENTO = $idpara";

				$sql = $connec->query($sql);
				// print_r($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[]=[
						'localidad'    => $row['LOCALIDAD'],
						'barra'        => $row['BARRA'],
						'descripcion'  => $row['DESCRIPCION'],
						'presentacion' => $row['PRESENTACION'],
						'cantidad'     => $row['CANTIDAD'],
						'bultos'       => round($row['BULTOS'], 2),
					];
				}

				echo json_encode($datos);
				break;

			case 'mostrarFactura':
				// Se prepara la consulta a la base de datos
				$sql = "SELECT bv.ARTICULO, a.descripcion AS DESCRIPCION,
							(CASE WHEN a.PRESENTACION=0 THEN 1 ELSE a.PRESENTACION END) AS PRESENTACION,
							bv.CANTIDAD,
							(bv.CANTIDAD/
							(CASE WHEN a.PRESENTACION=0 THEN 1 ELSE a.PRESENTACION END)) AS BULTOS,
							t.nombre AS LOCALIDAD
						FROM BDES.dbo.BIVentasDet AS bv
						INNER JOIN BDES.dbo.ESARTICULOS AS a ON a.codigo = bv.ARTICULO
						INNER JOIN BDES.dbo.ESSucursales AS t ON t.codigo = 3
						WHERE bv.TIPO = 7 AND bv.LOCALIDAD = 99 AND bv.ELIMINADO = 0
							AND bv.DOCUMENTO_SERIE = $idpara";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[]=[
						'localidad'    => $row['LOCALIDAD'],
						'barra'        => $row['ARTICULO'],
						'descripcion'  => $row['DESCRIPCION'],
						'presentacion' => $row['PRESENTACION'],
						'cantidad'     => $row['CANTIDAD'],
						'bultos'       => round($row['BULTOS'], 2),
					];
				}

				echo json_encode($datos);
				break;

			case 'mostrarDespacho':
				// Se prepara la consulta a la base de datos
				$sql = "SELECT RIGHT((CAST('00000' AS VARCHAR) + CAST(solipedi_id AS VARCHAR)), 5) AS pedido
						FROM BDES.dbo.BISolicPedido WHERE solipedi_nrodespacho = $idpara";

				$sql = $connec->query($sql);
				$pedidos = '';
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$pedidos .= $row['pedido'] . ', ';
				}
				$pedidos = substr($pedidos, 0, -1);

				$sql = "SELECT DISTINCT
							a.solipedi_nrodespacho AS nrodespacho,
							a.solipedi_fechaespera AS fecproceso,
							b.Nombre AS localidad,
							a.solipedi_usuarioespera AS usuario,
							b.direccion,
							a.solipedi_responsable AS responsable
						FROM BDES.dbo.BISolicPedido AS a
						INNER JOIN BDES.dbo.ESSucursales AS b ON a.localidad = b.codigo
						WHERE solipedi_nrodespacho = $idpara";

				$sql = $connec->query($sql);
				$row = $sql->fetch();
				$cabecera[] = [
					'nrodespacho' => $row['nrodespacho'],
					'fecproceso'  => $row['fecproceso'],
					'localidad'   => $row['localidad'],
					'usuario'     => $row['usuario'],
					'direccion'   => $row['direccion'],
					'responsable' => $row['responsable'],
					'pedidos'     => $pedidos,
				];

				$sql = "SELECT
							d.solipedidet_codigo,
							d.solipedidet_nrodespacho,
							( SELECT TOP 1 a.descripcion
								FROM BDES.dbo.ESARTICULOS AS a
								WHERE a.codigo= d.solipedidet_codigo
							) AS descripcion,
							( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
								WHERE b.escodigo = d.solipedidet_codigo
								AND b.codigoedi = 1) AS barra,
							d.solipedidet_empaque AS empaque,
							SUM(d.solipedidet_pedido) AS pedido
						FROM
							BDES.dbo.vw_soli_pedi_det AS d
						WHERE d.solipedidet_despachado = 0 AND
							d.solipedidet_nrodespacho = $idpara
						GROUP BY
							d.solipedidet_codigo, d.solipedidet_nrodespacho, d.solipedidet_empaque
						ORDER BY descripcion";

				$sql = $connec->query($sql);
				$detalle = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$empaque = $row['empaque'];
					if($empaque==0) {
						$empaque = 1;
					}
					$detalle[] = [
						'codigo'      => $row['solipedidet_codigo'],
						'nrodespacho' => $row['solipedidet_nrodespacho'],
						'descripcion' => $row['descripcion'],
						'barra'       => $row['barra'],
						'empaque'     => $row['empaque'],
						'pedido'      => $row['pedido'],
						'cant_bulto'  => $row['pedido']/$empaque,
					];
				}

				echo json_encode(array('cabecera' => $cabecera, 'detalle' => $detalle));
				break;

			case 'exportBiplusDIAN':
				// Se prepara el query para obtener los datos
				$fecha = explode('¬', $fecha);
				// Se prepara el query para obtener los datos
				$sql = "SELECT (CASE WHEN SUM(k.Cantidad) < 0 THEN 'DEV' ELSE 'VTA' END) AS TIPODOC,
							k.DOCUMENTO, k.CAJA, k.FECHA, a.codigo, a.descripcion,
							sg.TIPOBIEN, sg.UNDCOMERCIAL,
							SUM(k.Cantidad) AS Cantidad, SUM(k.Subtotal) AS Monto
						FROM (
							SELECT DOCUMENTO, CAJA, CAST(FECHA AS DATE) AS FECHA, MATERIAL,
								SUM(CANTIDAD) AS Cantidad, SUM(SUBTOTAL) AS Subtotal
							FROM BDES_POS.dbo.BIVENTAS_INV
							WHERE CAST(FECHA AS DATE) BETWEEN '$fecha[0]' AND '$fecha[1]'
								AND (TIPO IN (26, 27 )) AND $idpara
							GROUP BY MATERIAL, DOCUMENTO, CAJA, FECHA
							UNION
							SELECT DOCUMENTO, '0', CAST(FECHA AS DATE), ARTICULO, SUM(CANTIDAD),
								SUM(SUBTOTAL)
							FROM BDES.dbo.BIVentasDet
							WHERE CAST(FECHA AS DATE) BETWEEN '$fecha[0]' AND '$fecha[1]'
								AND (TIPO = 7) AND (ELIMINADO = 0) AND (ESTADO = 1) AND $idpara
							GROUP BY ARTICULO, DOCUMENTO, FECHA) AS k
							INNER JOIN BDES.dbo.ESARTICULOS AS a ON a.codigo = k.MATERIAL
							LEFT JOIN BDES.dbo.ESSubgrupos AS sg ON sg.CODIGO = a.subgrupo
							INNER JOIN BDES.dbo.EsArticulo_IMP ON a.codigo = EsArticulo_IMP.codigo
						GROUP BY a.codigo, a.descripcion, k.DOCUMENTO, k.CAJA, k.FECHA,
							sg.TIPOBIEN, sg.UNDCOMERCIAL
						ORDER BY FECHA, DOCUMENTO";

				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$fecha1 = date('d/m/Y', strtotime($fecha[0]));

				require_once "../Classes/PHPExcel.php";
				require_once "../Classes/PHPExcel/Writer/Excel5.php";

				$objPHPExcel = new PHPExcel();

				// Set document properties
				$objPHPExcel->getProperties()->setCreator("Dashboard")
											 ->setLastModifiedBy("Dashboard")
											 ->setTitle("Reporte para DIAN ".$fecha1)
											 ->setSubject("Reporte para DIAN ".$fecha1)
											 ->setDescription("Reporte para DIAN ".$fecha1." generado usando el Dashboard.")
											 ->setKeywords("Office 2007 openxml php")
											 ->setCategory("Reporte para DIAN ".$fecha1);

				$objPHPExcel->setActiveSheetIndex(0);
				$rowCount = 1;
				$icorr = date('dmy', strtotime($fecha[0]));

				$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, 'Tipo');
				$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, 'TipoBien');
				$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, 'UndComercial');
				$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, 'Número');
				$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, 'Caja');
				$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, 'Fecha');
				$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, 'Código');
				$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, 'Descripción del Artículo');
				$objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, 'Cantidad');
				$objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, 'Monto');
				$rowCount++;
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, $row['TIPODOC']);
					$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $row['TIPOBIEN']);
					$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, $row['UNDCOMERCIAL']);
					$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, $row['DOCUMENTO']);
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, $row['CAJA']);
					$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, $row['FECHA']);
					$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, $row['codigo']);
					$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, $row['descripcion']);
					$objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, $row['Cantidad']);
					$objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, $row['Monto']);
					$rowCount++;
				}

				foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
					$objPHPExcel
							->getActiveSheet()
							->getColumnDimension($col)
							->setAutoSize(true);
				}

				// Rename worksheet
				$objPHPExcel->getActiveSheet()->setTitle('ReporteDIAN');
				// Set active sheet index to the first sheet, so Excel opens this as the first sheet
				$objPHPExcel->setActiveSheetIndex(0);

				// Redirect output to a client’s web browser (Excel5)
				header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
				header('Content-Disposition: attachment;filename="ReporteDIAN.xls"');
				header('Cache-Control: max-age=0');
				// If you're serving to IE 9, then the following may be needed
				header('Cache-Control: max-age=1');

				// If you're serving to IE over SSL, then the following may be needed
				header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
				header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
				header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
				header ('Pragma: public'); // HTTP/1.0

				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

				$objWriter->save('../tmp/ReporteDIAN_'.$icorr.'.xls');

				echo json_encode(array('enlace'=>'tmp/ReporteDIAN_'.$icorr.'.xls', 'archivo'=>'ReporteDIAN_'.$icorr.'.xls'));
				break;

			case 'exportBiplusDANE':
				// Se prepara el query para obtener los datos
				$sql = "SELECT CONVERT(VARCHAR, DBDane.COD_BARRAS) AS BARRAS,
							DBDane.NOMBRE_PRODUCTO, DBDane.MARCA, DBDane.CANTIDAD_CONTENIDA,
							f.CANTIDAD, f.PRECIO, f.MONTOV, ESSucursales.NOMBRE
						FROM (SELECT k.LOCALIDAD, a.codigo, a.descripcion, SUM(k.Cantidad) AS cantidad,
								(CASE WHEN SUM(k.Cantidad) = '0' THEN 0
								ELSE SUM(Subtotal)/SUM(k.Cantidad)
								END) AS Precio, SUM ( k.Subtotal ) AS MontoV
							FROM (SELECT LOCALIDAD, MATERIAL, SUM(CANTIDAD) AS Cantidad,
									SUM(CANTIDAD * COSTO) AS SubtCosto, SUM(CANTIDAD * PRECIO) AS Subtotal,
									SUM(CANTIDAD * PRECIO_REAL) AS SubtReal
								FROM BDES_POS.dbo.BIVENTAS_INV
								WHERE CAST(FECHA AS DATE) = '$fecha' AND $idpara AND (TIPO IN (26, 27))
								GROUP BY MATERIAL, LOCALIDAD
								UNION
								SELECT LOCALIDAD, ARTICULO,	SUM(CANTIDAD), SUM(CANTIDAD * COSTO),
									SUM(SUBTOTAL) AS subtotal, SUM(SUBTOTAL) AS SubtReal
								FROM BDES.dbo.BIVentasDet
								WHERE CAST(FECHA AS DATE) = '$fecha' AND $idpara
									AND (TIPO = 7) AND (ELIMINADO = 0) AND (ESTADO = 1)
								GROUP BY ARTICULO, LOCALIDAD) AS k
								INNER JOIN BDES.dbo.ESARTICULOS AS a ON a.codigo = k.MATERIAL
							GROUP BY a.codigo, a.descripcion, k.LOCALIDAD) AS f
							INNER JOIN BDES.dbo.ESCodigos ON f.codigo = ESCodigos.escodigo
							INNER JOIN BDES.dbo.DBDane ON ESCodigos.barra = DBDane.COD_BARRAS
							INNER JOIN BDES.dbo.ESSucursales ON f.LOCALIDAD = ESSucursales.codigo
						WHERE (ESCodigos.descripcion != 'CODIGO MAESTRO')";

				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);

				$fecha1 = date('d/m/Y', strtotime($fecha));

				require_once "../Classes/PHPExcel.php";
				require_once "../Classes/PHPExcel/Writer/Excel5.php";

				$objPHPExcel = new PHPExcel();

				// Set document properties
				$objPHPExcel->getProperties()->setCreator("Dashboard")
											 ->setLastModifiedBy("Dashboard")
											 ->setTitle("Reporte para DANE ".$fecha1)
											 ->setSubject("Reporte para DANE ".$fecha1)
											 ->setDescription("Reporte para DANE ".$fecha1." generado usando el Dashboard.")
											 ->setKeywords("Office 2007 openxml php")
											 ->setCategory("Reporte para DANE ".$fecha1);

				$objPHPExcel->setActiveSheetIndex(0);
				$rowCount = 1;
				$icorr = date('dmy', strtotime($fecha));

				$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, 'BARRAS');
				$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, 'NOMBRE_PRODUCTO');
				$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, 'MARCA');
				$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, 'CANTIDAD_CONTENIDA');
				$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, 'CANTIDAD');
				$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, 'PRECIO');
				$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, 'MONTOV');
				$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, 'NOMBRE');
				$rowCount++;
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$objPHPExcel->getActiveSheet()->setCellValueExplicit('A'.$rowCount, $row['BARRAS'], PHPExcel_Cell_DataType::TYPE_STRING);
					$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $row['NOMBRE_PRODUCTO']);
					$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, $row['MARCA']);
					$objPHPExcel->getActiveSheet()->setCellValueExplicit('D'.$rowCount, $row['CANTIDAD_CONTENIDA'], PHPExcel_Cell_DataType::TYPE_STRING);
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, $row['CANTIDAD']);
					$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, $row['PRECIO']);
					$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, $row['MONTOV']);
					$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, $row['NOMBRE']);
					$rowCount++;
				}

				foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
					$objPHPExcel
							->getActiveSheet()
							->getColumnDimension($col)
							->setAutoSize(true);
				}

				// Rename worksheet
				$objPHPExcel->getActiveSheet()->setTitle('ReporteDANE');
				// Set active sheet index to the first sheet, so Excel opens this as the first sheet
				$objPHPExcel->setActiveSheetIndex(0);

				// Redirect output to a client’s web browser (Excel5)
				header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
				header('Content-Disposition: attachment;filename="ReporteDANE.xls"');
				header('Cache-Control: max-age=0');
				// If you're serving to IE 9, then the following may be needed
				header('Cache-Control: max-age=1');

				// If you're serving to IE over SSL, then the following may be needed
				header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
				header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
				header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
				header ('Pragma: public'); // HTTP/1.0

				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

				$objWriter->save('../tmp/ReporteDANE_'.$icorr.'.xls');

				echo json_encode(array('enlace'=>'tmp/ReporteDANE_'.$icorr.'.xls', 'archivo'=>'ReporteDANE_'.$icorr.'.xls'));
				break;

			case 'exportBiplusNIELSEN':
				ini_set('memory_limit', '-1');
				ini_set('max_execution_time', 300);
				// Se prepara el query para obtener los datos
				$fecha = explode('¬', $fecha);
				// Se prepara el query para obtener los datos
				$sql = "SELECT k.LOCALIDAD, BITiendas_Nielsen.Tienda, d.barra, a.codigo,
							(CASE WHEN BIDATANIELSEN.DescripcionNielsen IS NULL
							THEN a.descripcion ELSE BIDATANIELSEN.DescripcionNielsen END) AS descripcion,
							a.peso, ESDpto.CODIGO AS Cod_Dpto, ESDpto.DESCRIPCION AS Departamento,
							(CASE WHEN ESGrupos.CODIGO IS NULL
							THEN ESDpto.CODIGO ELSE ESGrupos.CODIGO END) AS Cod_Grupo,
							(CASE WHEN ESGrupos.DESCRIPCION IS NULL
							THEN ESDpto.DESCRIPCION ELSE ESGrupos.DESCRIPCION END) AS Grupo,
							(CASE WHEN ESSubgrupos.CODIGO IS NULL
							THEN ESGrupos.CODIGO ELSE ESSubgrupos.CODIGO END) AS Cod_SubGrupo,
							(CASE WHEN ESSubgrupos.DESCRIPCION IS NULL
							THEN ESGrupos.DESCRIPCION ELSE ESSubgrupos.DESCRIPCION END) AS SubGrupo,
							SUM(k.total) AS MontoVT, SUM(k.cantidad) AS Cantidad, len(d.barra) AS d
						FROM
							(SELECT LOCALIDAD, MATERIAL, SUM(CANTIDAD)AS Cantidad, SUM(CANTIDAD * COSTO)AS SubtCosto,
								SUM(SUBTOTAL)AS subtotal, SUM(SUBTOTAL)+ SUM(IMPUESTO)AS total, SUM(IMPUESTO)AS imp
							FROM BDES_POS.dbo.BIVENTAS_INV
							WHERE	CAST(FECHA AS DATE) BETWEEN '$fecha[0]' AND '$fecha[1]'
								AND (TIPO IN(26, 27 ))
							GROUP BY MATERIAL, LOCALIDAD
							UNION ALL
							SELECT LOCALIDAD, ARTICULO, SUM(CANTIDAD)AS cantidad, SUM(CANTIDAD*COSTO)AS SubtCosto,
								SUM(SUBTOTAL)AS subtotal, SUM(SUBTOTAL)+SUM(IMPUESTO)AS total, SUM(IMPUESTO)AS imp
							FROM BDES.dbo.BIVentasDet
							WHERE CAST(FECHA AS DATE) BETWEEN '$fecha[0]' AND '$fecha[1]'
								AND(TIPO = 7) AND(ELIMINADO = 0) AND(ESTADO = 1)
							GROUP BY ARTICULO, LOCALIDAD
							) AS k
							INNER JOIN BDES.dbo.ESARTICULOS AS a ON a.codigo = k.MATERIAL
							INNER JOIN BDES.dbo.BITiendas_Nielsen ON k.LOCALIDAD = BITiendas_Nielsen.Codigo
							INNER JOIN (SELECT escodigo, MAX(barra) AS barra
								FROM BDES.dbo.ESCodigos WHERE codigoedi = '1'
								GROUP BY escodigo) AS d ON a.codigo = d.escodigo
							INNER JOIN BDES.dbo.ESDpto ON a.departamento = ESDpto.CODIGO
							LEFT OUTER JOIN BDES.dbo.BIDATANIELSEN ON d.barra = BIDATANIELSEN.barras
							LEFT OUTER JOIN BDES.dbo.ESSubgrupos ON ESDpto.CODIGO = ESSubgrupos.DEPARTAMENTO
								AND a.Grupo = ESSubgrupos.GRUPO
								AND a.Subgrupo = ESSubgrupos.CODIGO
							LEFT OUTER JOIN BDES.dbo.ESGrupos ON ESDpto.CODIGO = ESGrupos.DEPARTAMENTO
								AND a.Grupo = ESGrupos.CODIGO
						WHERE $idpara
						GROUP BY a.codigo, a.descripcion, k.LOCALIDAD, BITiendas_Nielsen.Tienda, d.barra,
							a.peso, ESDpto.CODIGO, ESDpto.DESCRIPCION, ESGrupos.CODIGO, ESGrupos.DESCRIPCION,
							ESSubgrupos.CODIGO, ESSubgrupos.DESCRIPCION, BIDATANIELSEN.barras,
							BIDATANIELSEN.DescripcionNielsen
						ORDER BY len(d.barra), a.codigo";

				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$fecha1 = date('d/m/Y', strtotime($fecha[0]));

				require_once "../Classes/PHPExcel.php";
				require_once "../Classes/PHPExcel/Writer/Excel5.php";

				$objPHPExcel = new PHPExcel();

				// Set document properties
				$objPHPExcel->getProperties()->setCreator("Dashboard")
											 ->setLastModifiedBy("Dashboard")
											 ->setTitle("Reporte para NIELSEN ".$fecha1)
											 ->setSubject("Reporte para NIELSEN ".$fecha1)
											 ->setDescription("Reporte para NIELSEN ".$fecha1." generado usando el Dashboard.")
											 ->setKeywords("Office 2007 openxml php")
											 ->setCategory("Reporte para NIELSEN ".$fecha1);

				$objPHPExcel->setActiveSheetIndex(0);

				$objPHPExcel->getActiveSheet()
					->getStyle('A1:O2')->getFont()->setBold(true);

				$objPHPExcel->getActiveSheet()->mergeCells('A1:O1');

				$objPHPExcel->getActiveSheet()
					->getStyle('A1:O2')
					->getAlignment()
					->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
				$objPHPExcel->getActiveSheet()
					->getStyle('A1:O2')
					->getAlignment()
					->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

				$objPHPExcel->getActiveSheet()
						->SetCellValue('A1', 'Reporte para NIELSEN del '.
							date('d-m-Y', strtotime($fecha[0])).' al '.
							date('d-m-Y', strtotime($fecha[1])));

				$rowCount = 2;
				$icorr = date('dmy', strtotime($fecha[0]));

				$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, 'LOCALIDAD');
				$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, 'Tienda');
				$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, 'barra');
				$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, 'codigo');
				$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, 'descripcion');
				$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, 'peso');
				$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, 'Cod_Dpto');
				$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, 'Departamento');
				$objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, 'Cod_Grupo');
				$objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, 'Grupo');
				$objPHPExcel->getActiveSheet()->SetCellValue('K'.$rowCount, 'Cod_SubGrupo');
				$objPHPExcel->getActiveSheet()->SetCellValue('L'.$rowCount, 'SubGrupo');
				$objPHPExcel->getActiveSheet()->SetCellValue('M'.$rowCount, 'MontoVT');
				$objPHPExcel->getActiveSheet()->SetCellValue('N'.$rowCount, 'Cantidad');
				$objPHPExcel->getActiveSheet()->SetCellValue('O'.$rowCount, 'LARGO');

				$rowCount++;
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, $row['LOCALIDAD']);
					$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $row['Tienda']);
					$objPHPExcel->getActiveSheet()->setCellValueExplicit('C'.$rowCount, $row['barra'], PHPExcel_Cell_DataType::TYPE_STRING);
					$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, $row['codigo']);
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, $row['descripcion']);
					$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, $row['peso']);
					$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, $row['Cod_Dpto']);
					$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, $row['Departamento']);
					$objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, $row['Cod_Grupo']);
					$objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, $row['Grupo']);
					$objPHPExcel->getActiveSheet()->SetCellValue('K'.$rowCount, $row['Cod_SubGrupo']);
					$objPHPExcel->getActiveSheet()->SetCellValue('L'.$rowCount, $row['SubGrupo']);
					$objPHPExcel->getActiveSheet()->SetCellValue('M'.$rowCount, $row['MontoVT']);
					$objPHPExcel->getActiveSheet()->SetCellValue('N'.$rowCount, $row['Cantidad']);
					$objPHPExcel->getActiveSheet()->SetCellValue('O'.$rowCount, $row['d']);
					$rowCount++;
				}

				$objPHPExcel->getActiveSheet()
					->getStyle('M3:N'.$rowCount)
					->getNumberFormat()
					->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

				$objPHPExcel->getActiveSheet()
					->getStyle('F3:F'.$rowCount)
					->getNumberFormat()
					->setFormatCode('#,##0.000');

				$objPHPExcel->getActiveSheet()->freezePane('A3');

				$rowCount--;

				$objPHPExcel->getActiveSheet()->setAutoFilter('A2:O'.$rowCount);

				foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
					$objPHPExcel
						->getActiveSheet()
						->getColumnDimension($col)
						->setAutoSize(true);
				}

				$objPHPExcel->getActiveSheet()->setSelectedCell('A3');

				// Rename worksheet
				$objPHPExcel->getActiveSheet()->setTitle('ReporteNIELSEN');
				// Set active sheet index to the first sheet, so Excel opens this as the first sheet
				$objPHPExcel->setActiveSheetIndex(0);

				// Redirect output to a client’s web browser (Excel5)
				header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
				header('Content-Disposition: attachment;filename="ReporteNIELSEN.xls"');
				header('Cache-Control: max-age=0');
				// If you're serving to IE 9, then the following may be needed
				header('Cache-Control: max-age=1');

				// If you're serving to IE over SSL, then the following may be needed
				header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
				header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
				header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
				header ('Pragma: public'); // HTTP/1.0

				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

				$objWriter->save('../tmp/ReporteNIELSEN_'.$icorr.'.xls');

				echo json_encode(array('enlace'=>'tmp/ReporteNIELSEN_'.$icorr.'.xls', 'archivo'=>'ReporteNIELSEN_'.$icorr.'.xls'));
				break;

			case 'borrarArchivoTNS':
				// Se borra el archivo TNS generado y descargado
				if(!unlink('../tmp/'.$idpara)){
					echo 'Se presentó un error, Informe a Sistemas';
				} else {
					echo 'El archivo se descargó correctamente';
				}
				break;

			case 'pediVSdespa':
				// Se obtienen los parametros enviados
				extract($_POST);
				$idpara = $idpara=='*' ? 'localidad' : $idpara;

				$sql = "SELECT
							localidad AS sucursal,
							Nombre AS tienda,
							solipedi_fechasoli AS fecha,
							solipedi_fechaespera AS picking,
							solipedi_fechadesp AS despacho,
							solipedi_id AS id,
							RIGHT((CAST('00000' AS VARCHAR)+CAST(solipedi_id AS VARCHAR)), 5) AS nro_pedido,
							SUM(solipedidet_pedido) AS und_pedido,
							SUM(CAST(solipedidet_pedido /
								(CASE WHEN solipedidet_empaque=0 THEN 1
								ELSE COALESCE(solipedidet_empaque, 1)
								END) AS NUMERIC(18,2))) AS bul_pedido,
							solipedi_nrodespacho AS nrodespacho,
							RIGHT((CAST('00000' AS VARCHAR)+CAST(solipedi_nrodespacho AS VARCHAR)), 5) AS nro_despacho,
							SUM(solipedidet_pedido) AS und_despacho,
							SUM(CAST(solipedidet_pedido /
								(CASE WHEN solipedidet_empaque=0 THEN 1
								ELSE COALESCE(solipedidet_empaque, 1)
								END) AS NUMERIC(18,2))) AS bul_despacho
						FROM BDES.dbo.vw_soli_pedi_det
						INNER JOIN BDES.dbo.ESSucursales ON codigo = localidad
						WHERE CAST(solipedi_fechasoli AS DATE) BETWEEN '$fechai' AND '$fechaf'
						AND solipedi_status = 3 AND centro_dist = 99 AND localidad != 3 AND localidad = $idpara
						GROUP BY localidad, Nombre, solipedi_fechasoli, solipedi_id, solipedi_nrodespacho,
							solipedi_fechaespera, solipedi_fechadesp";

				$sql = $connec->query($sql);

				if($sql) {
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$nropedido = '<button type="button" title="Ver Pedido" onclick="mostrarPedido('.$row['id'].','."'".$row['tienda']."'".')"
								class="btn btn-link m-0 p-0 text-left"
								style="white-space: normal; line-height: 1;">'.$row['nro_pedido'].
							'</button>';
						$nrodespacho = '<button type="button" title="Ver Despacho" onclick="mostrarDespacho('.$row['nrodespacho'].','."'".$row['tienda']."'".')"
								class="btn btn-link m-0 p-0 text-left"
								style="white-space: normal; line-height: 1;">'.$row['nro_despacho'].
							'</button>';
						$datos[]=[
							'sucursal'     => $row['sucursal'],
							'tienda'       => $row['tienda'],
							'fecha'        => '<span style="display: none">'.$row['fecha'].'</span>'.date('d-m-Y h:i a', strtotime($row['fecha'])),
							'picking'      => $row['picking'],
							'despacho'     => '<span style="display: none">'.$row['despacho'].'</span>'.date('d-m-Y h:i a', strtotime($row['despacho'])),
							'nro_pedido'   => $nropedido,
							'und_pedido'   => $row['und_pedido'],
							'bul_pedido'   => $row['bul_pedido'],
							'nro_despacho' => $nrodespacho,
							'und_despacho' => $row['und_despacho'],
							'bul_despacho' => $row['bul_despacho'],
						];
					}
				}

				echo json_encode($datos);
				break;

			case 'marcar_articulo_virtuales':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES.dbo.DBArticulosVirtuales SET ";

				if($params[1]==1) {
					$sql .= 'web_montes = ' . $params[2];
				} else {
					$sql .= 'web_externa = ' . $params[2];
				}

				$sql .= 'WHERE codigo = ' . $params[0];

				$sql = $connec->query($sql);

				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r($connec->errorInfo());
				}
				break;

			case 'agregar_art_virtual':
				// Se prepara la consulta para guardar el articulo
				$sql = "INSERT INTO BDES.dbo.DBArticulosVirtuales(codigo, web_externa, web_montes)
						VALUES($idpara, 0, 0)";

				$sql = $connec->query($sql);

				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r($connec->errorInfo());
				}
				break;

			case 'exportArtVirtuales':
				// Se prepara la consulta para buscar los archivos
				$sql = "SELECT av.codigo, art.descripcion, av.web_externa, av.web_montes
						FROM BDES.dbo.DBArticulosVirtuales AS av
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = av.codigo
						WHERE av.eliminado = 0 AND art.activo = 1
						AND $idpara = 1";

				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => $row['descripcion'],
						'web_montes'  => $row['web_montes'],
						'web_externa' => $row['web_externa'],
					];
				}

				if(count($datos)>0) {
					require_once "../Classes/PHPExcel.php";
					require_once "../Classes/PHPExcel/Writer/Excel5.php";

					$fecha = date('dmY', strtotime($fecha));

					// Se crea primero el archivo para pagina los montes
					$objPHPExcel = new PHPExcel();
					// Set document properties
					$objPHPExcel->getProperties()->setCreator("Dashboard")
												 ->setLastModifiedBy("Dashboard")
												 ->setTitle("Artículos para ".$idpara.".com " . $fecha)
												 ->setSubject("Artículos para ".$idpara.".com " . $fecha)
												 ->setDescription("Artículos para ".$idpara.".com generado usando el Dashboard.")
												 ->setKeywords("office 2007 openxml php")
												 ->setCategory("Artículos para ".$idpara.".com " . $fecha);

					$objPHPExcel->setActiveSheetIndex(0);
					$rowCount = 1;
					for ($i = 0; $i < count($datos); $i++) {
						if($datos[$i][$idpara]==1) {
							$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, $datos[$i]['codigo']);
							$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $datos[$i]['descripcion']);
							$rowCount++;
						}
					}

					// Rename worksheet
					$objPHPExcel->getActiveSheet()->setTitle('artVtasVirt'.$idpara.$fecha);
					// Set active sheet index to the first sheet, so Excel opens this as the first sheet
					$objPHPExcel->setActiveSheetIndex(0);

					// Redirect output to a client’s web browser (Excel5)
					header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
					header('Content-Disposition: attachment;filename="artVtasVirt'.$idpara.$fecha.'.xls"');
					header('Cache-Control: max-age=0');
					// If you're serving to IE 9, then the following may be needed
					header('Cache-Control: max-age=1');

					// If you're serving to IE over SSL, then the following may be needed
					header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
					header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
					header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
					header ('Pragma: public'); // HTTP/1.0

					$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
					$objWriter->save('../tmp/artVtasVirt'.$idpara.$fecha.'.xls');
				}

				echo json_encode(array('enlace'=>'tmp/artVtasVirt'.$idpara.$fecha.'.xls', 'archivo'=>'artVtasVirt'.$idpara.$fecha.'.xls'));
				break;

			case 'exportVtasBimas':
				extract($_POST);

				$sql = "SELECT ";

				if($otrosc!='') {
					foreach (explode('¬', $otrosc) as $otrocampo) {
						$sql .= ($otrocampo=='loc') ? 'k.localidad, ti.Nombre AS tienda, ' : '';
						$sql .= ($otrocampo=='dpt') ? 'dpto.DESCRIPCION AS departamento, ' : '';
						$sql .= ($otrocampo=='pro') ? 'pro.descripcion AS proveedor, ' : '';
						$sql .= ($otrocampo=='grp') ? 'g.descripcion AS grupo, ' : '';
						$sql .= ($otrocampo=='sgr') ? 'sg.descripcion AS subgrupo, ' : '';
						$sql .= ($otrocampo=='vta') ? 'k.ultventa AS ultventa, ' : '';
					}
				}

				$sql .= "	a.codigo,
							a.descripcion,
							m.GRUPO AS marca,
							( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
								WHERE b.escodigo = a.codigo
								AND b.codigoedi = 1) AS barra,
							SUM(k.cantidad) AS cantidad,
							SUM(subtcosto) AS monto,
							SUM(subtotal) AS montov
						FROM
							(SELECT ";
				$sql .= (strpos($otrosc, 'loc')!==false) ? 'LOCALIDAD,' : '';
				$sql .= "		MATERIAL,
								SUM(CANTIDAD) AS cantidad,
								SUM(CANTIDAD * COSTO) AS subtcosto,
								SUM(CANTIDAD * PRECIO) AS subtotal";
				$sql .= (strpos($otrosc, 'vta')!==false) ? ', MAX(FECHA) AS ultventa' : '';
				$sql .= "	FROM BDES_POS.dbo.BIVENTAS_INV
							WHERE (CAST (FECHA AS DATE) BETWEEN '$fechai' AND '$fechaf')
								AND (	TIPO IN (26, 27)) AND (LOCALIDAD IN ($idpara))
							GROUP BY ";
				$sql .= (strpos($otrosc, 'loc')!==false) ? 'LOCALIDAD,' : '';
				$sql .= "		MATERIAL
							UNION
							SELECT ";
				$sql .= (strpos($otrosc, 'loc')!==false) ? ' LOCALIDAD,' : '';
				$sql .= "	ARTICULO, SUM(CANTIDAD), SUM(CANTIDAD * COSTO), SUM(SUBTOTAL)";
				$sql .= (strpos($otrosc, 'vta')!==false) ? ', MAX(FECHA)' : '';
				$sql .= "	FROM BDES.dbo.BIVentasDet
							WHERE (CAST (FECHA AS DATE) BETWEEN '$fechai' AND '$fechaf')
								AND (TIPO = 7) AND (ELIMINADO = 0) AND (ESTADO = 1) AND (LOCALIDAD IN ($idpara))
							GROUP BY ";
				$sql .= (strpos($otrosc, 'loc')!==false) ? ' LOCALIDAD,' : '';
				$sql .=	"		ARTICULO) k ";
				$sql .= "	INNER JOIN BDES.dbo.ESArticulos a ON a.codigo= k.material";
				$sql .= (strpos($otrosc, 'loc')!==false) ? ' INNER JOIN BDES.dbo.ESSucursales ti ON ti.codigo = k.localidad' : '';
				$sql .= (strpos($otrosc, 'grp')!==false) ? ' INNER JOIN BDES.dbo.ESGrupos g ON g.codigo = a.grupo' : '';
				$sql .= (strpos($otrosc, 'sgr')!==false) ? ' INNER JOIN BDES.dbo.ESSubgrupos sg ON sg.codigo = a.subgrupo' : '';
				$sql .= "	LEFT JOIN BDES.dbo.ESGruposFichas m ON m.ID = a.marca AND LOWER(m.tipo) = 'marca'
							LEFT JOIN BDES.dbo.ESArticulosxProv pxa ON pxa.articulo = a.codigo";
				$sql .= (strpos($otrosc, 'dpt')!==false) ? ' LEFT JOIN BDES.dbo.ESDpto dpto ON dpto.CODIGO = a.departamento' : '';
				$sql .= (strpos($otrosc, 'pro')!==false) ? ' LEFT JOIN BDES.dbo.ESProveedores pro ON pro.codigo = pxa.proveedor' : '';
				$sql .= " WHERE a.departamento = $iddpto";
				if($idprov!='') {
					$sql .= "	AND pxa.proveedor = $idprov";
				}
				$sql .= " GROUP BY ";
				if($otrosc!='') {
					foreach (explode('¬', $otrosc) as $otrocampo) {
						$sql .= ($otrocampo=='loc') ? 'k.localidad, ti.Nombre,' : '';
						$sql .= ($otrocampo=='dpt') ? 'dpto.DESCRIPCION,' : '';
						$sql .= ($otrocampo=='pro') ? 'pro.descripcion,' : '';
						$sql .= ($otrocampo=='grp') ? 'g.descripcion,' : '';
						$sql .= ($otrocampo=='sgr') ? 'sg.descripcion,' : '';
						$sql .= ($otrocampo=='vta') ? 'k.ultventa,' : '';
					}
				}
				$sql .= "	a.codigo,
							a.descripcion,
							m.GRUPO";

				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'       => $row['codigo'],
						'barra'        => $row['barra'],
						'descripcion'  => $row['descripcion'],
						'marca'        => $row['marca'],
						'cantidad'     => $row['cantidad'],
						'monto'        => $row['monto'],
						'montov'       => $row['montov'],
						'localidad'    => (strpos($otrosc, 'loc')!==false) ? $row['localidad'] : '',
						'tienda'       => (strpos($otrosc, 'loc')!==false) ? $row['tienda'] : '',
						'grupo'        => (strpos($otrosc, 'grp')!==false) ? $row['grupo'] : '',
						'subgrupo'     => (strpos($otrosc, 'sgr')!==false) ? $row['subgrupo'] : '',
						'departamento' => (strpos($otrosc, 'dpt')!==false) ? $row['departamento'] : '',
						'proveedor'    => (strpos($otrosc, 'pro')!==false) ? $row['proveedor'] : '',
						'ultventa'     => (strpos($otrosc, 'vta')!==false) ? date('d-m-Y', strtotime($row['ultventa'])) : '',
					];
				}

				echo json_encode($datos);
				break;

			case 'despachosWebpendientes':
				if($idpara!='') {
					// Se prepara la consulta para los articulos para paginas web
					$sql = "SELECT IDTR, FECHAHORA, IDCLIENTE, RAZON, GRUPOC
							FROM BDES_POS.dbo.ESVENTAS_TMP
							WHERE GRUPOC = 0 AND IDTR = $idpara";

					// Se ejecuta la consulta en la BBDD
					$sql = $connec->query($sql);

					// Se prepara el array para almacenar los datos obtenidos
					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = [
							'nrodoc'    => $row['IDTR'],
							'fecha'     => $row['FECHAHORA'],
							'idcliente' => $row['IDCLIENTE'],
							'nombre'    => $row['RAZON'],
							'activa'    => $row['GRUPOC'],
						];
					}
				} else {
					$datos = [];
				}

				echo json_encode(array('data' => $datos));
				break;

			case 'detalleDespachoweb':
				if($idpara!='') {
					$sql = "SELECT IDTR, ARTICULO, BARRA, IMAI,
								art.descripcion AS NOMBRE,
								ROUND(PRECIO+(PRECIO*PORC/100), 2) AS PRECIO,
								SUM(CANTIDAD) AS CANTIDAD
							FROM BDES_POS.dbo.ESVENTAS_TMP_DET AS det
							INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.ARTICULO
							WHERE IDTR = $idpara
							GROUP BY IDTR, ARTICULO, BARRA, IMAI, art.descripcion, PRECIO, PORC";

					$sql = $connec->query($sql);

					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[]=[
							'nrodoc'   => $row['IDTR'],
							'codigo'   => $row['ARTICULO'],
							'barra'    => $row['BARRA'],
							'imai'     => $row['IMAI'],
							'nombre'   => $row['NOMBRE'],
							'precio'   => $row['PRECIO'],
							'cantidad' => $row['CANTIDAD'],
						];
					}
				} else {
					$datos = [];
				}

				echo json_encode($datos);
				break;

			case 'marcarCabeceraweb':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES_POS.dbo.ESVENTAS_TMP SET GRUPOC = $params[1]
						WHERE IDTR = $params[0]";

				$sql = $connec->query($sql);

				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r($connec->errorInfo());
				}
				break;

			case 'marcarDetalleweb':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES_POS.dbo.ESVENTAS_TMP_DET SET
						IMAI = $params[2]
						WHERE IDTR = $params[0] AND ARTICULO = $params[1]";

				$sql = $connec->query($sql);

				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r($connec->errorInfo());
				}
				break;

			case 'procDctoweb':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES_POS.dbo.ESVENTAS_TMP SET GRUPOC = 2
						WHERE IDTR = $params[0]";

				$sql = $connec->query($sql);

				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r($connec->errorInfo());
				}
				break;

			case 'esclientesBDES':
				$sql = "SELECT codigo, descripcion
						FROM BDES.dbo.ESCLIENTES
						WHERE codigo != 0
						ORDER BY descripcion";

				$sql = $connec->query($sql);

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => strtoupper($row['descripcion']),
					];
				}

				echo json_encode($datos);
				break;

			case 'exportacionesDIAN':
				extract($_POST);
				$sql = "SELECT k.fecha, k.documento, bv.DOCUMENTO_SERIE AS serie,
							bv.CODIGO AS codcliente, bv.DESCRIPCION AS cliente,
							SUM(ABS(k.cantidad)*bvd.PRECIO) AS subtotal,
							SUM(bvd.IMPUESTO) AS impuesto
						FROM BDES.dbo.BIKardexMov AS k
							INNER JOIN BDES.dbo.BIVentasDet AS bvd ON k.documento = bvd.DOCUMENTO_REL
								AND k.tipo = bvd.TIPO_REL AND k.localidad = bvd.LOCALIDAD_REL
								AND k.articulo = bvd.ARTICULO
							INNER JOIN BDES.dbo.BIVentas AS bv ON bvd.DOCUMENTO = bv.DOCUMENTO AND bv.CODIGO = $idpara
								AND bvd.TIPO = bv.TIPO AND bvd.LOCALIDAD = bv.LOCALIDAD
							LEFT OUTER JOIN BDES.dbo.BIMOV_TIPO AS bimov ON bimov.CODIGO = k.tipomov
						WHERE k.periodo BETWEEN '$fechai' AND '$fechaf'
							AND k.tipomov > 0 AND k.eliminado = 0 AND k.almacen IN(4, 5, 6, 12)";
				if($nrodoc>0) {
					$sql.= " AND k.documento = $nrodoc ";
				}

				$sql.= "GROUP BY k.periodo, k.fecha, k.documento, bv.DOCUMENTO_SERIE, bv.CODIGO, bv.DESCRIPCION";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'fecha'      => '<span style="display: none;">'.$row['fecha'].'</span>'.
										date('d-m-Y h:i a', strtotime($row['fecha'])),
						'documento'  => $row['documento'],
						'serie'      => $row['serie'],
						'codcliente' => $row['codcliente'],
						'cliente'    => $row['cliente'],
						'subtotal'   => $row['subtotal'],
						'impuesto'   => $row['impuesto'],
						'total'      => $row['subtotal']+$row['impuesto'],
						'fechad'     => $row['fecha'],
					];
				}

				echo json_encode($datos);
				break;

			case 'detVtasExpDian':
				$sql = "SELECT art.descripcion, exp.*
						FROM
							(SELECT k.articulo, SUM(ABS(k.cantidad)) AS cantidad, SUM(biv.PRECIO) AS precio,
								(SUM(ABS(k.cantidad))*SUM(biv.PRECIO)) AS subtotal, SUM(biv.IMPUESTO) AS impuesto
							FROM BDES.dbo.BIKardexMov AS k
								INNER JOIN BDES.dbo.BIVentasDet AS biv ON k.documento = biv.DOCUMENTO_REL
									AND k.tipo = biv.TIPO_REL AND k.localidad = biv.LOCALIDAD_REL
									AND k.articulo = biv.ARTICULO
								INNER JOIN BDES.dbo.BIVentas ON biv.DOCUMENTO = BIVentas.DOCUMENTO
									AND biv.TIPO = BIVentas.TIPO AND biv.LOCALIDAD = BIVentas.LOCALIDAD
							WHERE k.documento = $idpara
							GROUP BY k.articulo) AS exp
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = exp.articulo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'codigo'      => $row['articulo'],
						'descripcion' => $row['descripcion'],
						'cantidad'    => $row['cantidad'],
						'precio'      => $row['precio'],
						'subtotal'    => $row['subtotal'],
						'impuesto'    => $row['impuesto'],
						'total'       => $row['subtotal']+$row['impuesto'],
					];
				}

				echo json_encode($datos);
				break;

			case 'kardexExpDian':
				extract($_POST);
				$fecha = str_replace(' ', 'T', $fecha);
				$sql = "SELECT articulo, tipo, fecha, DesTercero, costoss, PRECIO,
							(CASE WHEN num = '0' THEN '' ELSE NUM END) AS NumeroDoc,
							(CASE tipo
								WHEN '0'  THEN 'Saldo Inicial'
								WHEN '31' THEN 'Ventas'
								WHEN '14' THEN 'Compras'
								WHEN '13' THEN 'Transferencias'
								WHEN '17' THEN 'Transferencias'
								ELSE Proceso
							END) AS proceso, SUM(cantidad) AS cantidad, SUM(existencia) AS existencia
						FROM
							(SELECT 1 AS orden, articulo, almacen, MAX(fecha) AS fecha, '0' AS documento,
								'0' AS tipo, localidad, SUM(cantidad) AS Cantidad, 0 AS existencia,
								'0' AS tipomov, 'Saldo Inicial' AS Movimiento, '' AS Proceso, 0 AS costo,
								'0' AS CodTERCERO, '0' AS num, '' AS DesTercero, 0 AS costoss,
								'0' AS PRECIO, '0' AS IMPUESTO
							FROM BDES.dbo.BIKardexMov
							WHERE Periodo <= 201712 AND articulo = $idart AND Eliminado = 0 AND almacen = 5
							GROUP BY almacen, localidad, articulo
							UNION
							SELECT 2 AS orden, k.articulo, k.almacen, k.fecha, k.documento, k.tipo, k.localidad,
								k.cantidad, k.Existencia, k.tipomov, COALESCE(bimov.DESCRIPCION, '') AS Movimiento,
								s.SubProceso AS Proceso, k.costo, bk.TERCERO AS CodTercero,
								bk.DOCUMENTO_NUM AS num, prov.descripcion AS DesTercero, bkd.COSTO_F AS costoss,
								bkd.PRECIO, bkd.PORCIMP AS IMPUESTO
							FROM BDES.dbo.ESProveedores AS prov
								RIGHT OUTER JOIN BDES.dbo.BIKARDEX_DET bkd
								INNER JOIN BDES.dbo.BIKardexMov AS k ON bkd.DOCUMENTO_ORIG = k.documento
									AND bkd.TIPO_ORIG = k.tipo AND bkd.LOCALIDAD_ORIG = k.localidad
									AND bkd.ELIMINADO = k.eliminado AND bkd.MATERIAL = k.articulo
								INNER JOIN BDES.dbo.BIKARDEX bk ON bkd.DOCUMENTO = bk.DOCUMENTO
									AND bkd.TIPO = bk.TIPO AND bkd.LOCALIDAD = bk.LOCALIDAD
									AND bkd.ELIMINADO = bk.ELIMINADO
								LEFT OUTER JOIN BDES.dbo.BIMOV_TIPO AS bimov ON k.tipomov = bimov.CODIGO
								LEFT OUTER JOIN BDES.dbo.ESSubProcesosSistema AS s ON s.Codigo = k.tipo
									ON prov.codigo = bk.TERCERO
							WHERE k.periodo BETWEEN 201801 AND $perfin AND k.articulo = $idart
								AND k.tipomov > 0 AND k.eliminado = 0 AND k.almacen = 5 AND k.tipo = 14
							UNION
							SELECT 2 AS orden, k.articulo, k.almacen, k.fecha, k.documento, k.tipo, k.localidad,
								k.cantidad, k.Existencia, k.tipomov, COALESCE(bimov.DESCRIPCION, '') AS Movimiento,
								s.SubProceso AS Proceso, k.costo, biv.CODIGO AS CodTercero,
								biv.DOCUMENTO_SERIE AS num, biv.DESCRIPCION AS DEsTercero,
								bivd.COSTO AS costoss, bivd.PRECIO, bivd.IMPUESTO
							FROM BDES.dbo.BIKardexMov AS k
								INNER JOIN BDES.dbo.BIVentasDet bivd ON k.documento = bivd.DOCUMENTO_REL
									AND k.tipo = bivd.TIPO_REL AND k.localidad = bivd.LOCALIDAD_REL
									AND k.articulo = bivd.ARTICULO
								INNER JOIN BDES.dbo.BIVentas biv ON bivd.DOCUMENTO = biv.DOCUMENTO
									AND bivd.TIPO = biv.TIPO AND bivd.LOCALIDAD = biv.LOCALIDAD
								LEFT OUTER JOIN BDES.dbo.BIMOV_TIPO AS bimov ON bimov.CODIGO = k.tipomov
								LEFT OUTER JOIN BDES.dbo.ESSubProcesosSistema AS s ON s.Codigo = k.tipo
							WHERE k.periodo BETWEEN 201801 AND $perfin AND k.articulo = $idart
								AND k.tipomov > 0 AND k.eliminado = 0 AND k.almacen = 5
							UNION
							SELECT 2 AS orden, k.articulo, k.almacen, k.fecha, k.documento, k.tipo, k.localidad,
								k.cantidad, k.Existencia, k.tipomov, COALESCE(bimov.DESCRIPCION, '') AS Movimiento,
								s.SubProceso AS Proceso, k.costo, '0' AS CodTERCERO, '0' AS num, '' AS DesTercero,
								'0' AS costoss, '0' AS PRECIO, '0' AS IMPUESTO
							FROM BDES.dbo.BIKardexMov AS k
								LEFT OUTER JOIN BDES.dbo.BIMOV_TIPO AS bimov ON k.tipomov = bimov.CODIGO
								LEFT OUTER JOIN BDES.dbo.ESSubProcesosSistema AS s ON s.Codigo = k.tipo
							WHERE k.periodo BETWEEN 201801 AND $perfin AND k.articulo = $idart
								AND k.tipomov > 0 AND k.eliminado = 0 AND k.almacen = 5
								AND k.tipo NOT IN(14, 31)) AS ventas
						WHERE fecha <= '$fecha'
						GROUP BY articulo, tipo, fecha, Proceso, num, DesTercero, costoss, PRECIO
						ORDER BY fecha";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$datos = $sql->fetchAll(\PDO::FETCH_ASSOC);

				$datosr = array_reverse($datos);

				echo json_encode($datosr);
				break;

			case 'subirImagenes':
				$ruta = '../tmp/imgxproc/';
				$result = [];
				$imginv = [];
				foreach ($_FILES as $key) {
					$archivo = explode('.', $key['name']);
					$archivo = $archivo[0];
					if(strlen($archivo)>13 || !is_numeric($archivo)) {
						$imginv[] = [
							'id' => $archivo,
							'tipo' => 2,
							'archivo' => $key['name'],
							'texto' => !is_numeric($archivo) ? 'Nombre Invalido (solo se permiten numeros) ':
										'la longitud del nombre es mayor a 13'
						];
					} else {
						if($key['error'] == UPLOAD_ERR_OK ) { //Si el archivo se paso correctamente Ccontinuamos
							$NombreOriginal = $key['name'];
							$temporal = $key['tmp_name'];
							$Destino = $ruta.$NombreOriginal;
							move_uploaded_file($temporal, $Destino);
						}
						if ($key['error']=='') {
							$result[] = [
								'id' => $archivo,
								'tipo' => 1,
								'archivo' => 'tmp/imgxproc/'.$NombreOriginal,
								'texto' => $NombreOriginal
							];
						}
						if ($key['error']!='') {
							$result[] = [
								'id' => 0,
								'tipo' => 0,
								'archivo' => $NombreOriginal.'->'.$key['error'],
								'texto' => $NombreOriginal
							];
						}
					}
				}

				echo json_encode(['imgval' => $result, 'imginv' => $imginv]);
				break;

			case 'eliminarImagenes':
				$files = glob('../tmp/imgxproc/*'); //obtenemos todos los nombres de los ficheros
				foreach($files as $file){
					if(is_file($file))
					unlink($file); //elimino el fichero
				}
				$files = glob('../tmp/imgproc/*'); //obtenemos todos los nombres de los ficheros
				foreach($files as $file){
					if(is_file($file))
					unlink($file); //elimino el fichero
				}
				echo 1;
				break;

			case 'procesarImagenes':
				// Abrimos la carpeta que nos pasan como parámetro
				$rutao = '../tmp/imgxproc/';
				$rutad = '../tmp/imgproc/';
				$dir = opendir($rutao);
				// Leo todos los ficheros de la carpeta
				while ($elemento = readdir($dir)){
					// Tratamos los elementos . y .. que tienen todas las carpetas
					if ($elemento != "." && $elemento != "..") {
						// Si es una carpeta
						if (!is_dir($rutao . $elemento)) {
							switch (strtolower(substr($elemento, strpos($elemento,'.')))) {
								case '.png':
									$imagen = imagecreatefrompng($rutao . $elemento);
									$bg = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
									imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
									imagealphablending($bg, TRUE);
									imagecopy($bg, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
									$nombreimagen = substr($elemento, 0, strpos($elemento, '.'));
									imagejpeg($bg, $rutad . $nombreimagen . '.jpeg',100);
									ImageDestroy($bg);
									break;
								case '.gif':
									$imagen = imagecreatefromgif($rutao . $elemento);
									$bg = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
									imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
									imagealphablending($bg, TRUE);
									imagecopy($bg, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
									$nombreimagen = substr($elemento, 0, strpos($elemento, '.'));
									imagejpeg($bg, $rutad . $nombreimagen . '.jpeg',100);
									ImageDestroy($bg);
									break;
								case '.jpg':
									$imagen = imagecreatefromjpeg($rutao . $elemento);
									$bg = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
									imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
									imagealphablending($bg, TRUE);
									imagecopy($bg, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
									$nombreimagen = substr($elemento, 0, strpos($elemento, '.'));
									imagejpeg($bg, $rutad . $nombreimagen . '.jpeg',100);
									ImageDestroy($bg);
									break;
								case '.bmp':
									$imagen = imagecreatefrombmp($rutao . $elemento);
									$bg = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
									imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
									imagealphablending($bg, TRUE);
									imagecopy($bg, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
									$nombreimagen = substr($elemento, 0, strpos($elemento, '.'));
									imagejpeg($bg, $rutad . $nombreimagen . '.jpeg',100);
									ImageDestroy($bg);
									break;
								case '.wbmp':
									$imagen = imagecreatefromwbmp($rutao . $elemento);
									$bg = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
									imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
									imagealphablending($bg, TRUE);
									imagecopy($bg, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
									$nombreimagen = substr($elemento, 0, strpos($elemento, '.'));
									imagejpeg($bg, $rutad . $nombreimagen . '.jpeg',100);
									ImageDestroy($bg);
									break;
								case '.wbep':
									$imagen = imagecreatefromwebp($rutao . $elemento);
									$bg = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
									imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
									imagealphablending($bg, TRUE);
									imagecopy($bg, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
									$nombreimagen = substr($elemento, 0, strpos($elemento, '.'));
									imagejpeg($bg, $rutad . $nombreimagen . '.jpeg',100);
									ImageDestroy($bg);
									break;
								default:
									$imagen = true;
									$nombreimagen = substr($elemento, 0, strpos($elemento, '.'));
									copy($rutao . $elemento, $rutad . $elemento);
									break;
							}
							if($imagen) {
								unlink($rutao . $elemento); //elimino el fichero origen
							}
						}
					}
				} // End del while

				$imgconv = [];
				$imagenes = [];
				$dir = opendir($rutad);
				// Leo todos los ficheros de la carpeta
				while ($elemento = readdir($dir)){
					// Tratamos los elementos . y .. que tienen todas las carpetas
					if ($elemento != "." && $elemento != "..") {
						// Si es una carpeta
						if (!is_dir($rutad . $elemento)) {
							$archivo = explode('.', $elemento);
							$archivo = $archivo[0];
							$imgconv[] = $archivo;
							$imagenes[] = [
								'id' => $archivo,
								'archivo' => 'tmp/imgproc/'.$elemento,
								'texto' => $elemento
							];
						}
					}
				} // End del while

				$sql = "TRUNCATE TABLE BDES.dbo.DBArticulosVirtualesBarraFotos_01";

				$sql = $connec->query($sql);
				if(!$sql) {
					echo 0;
					print_r($connec->errorInfo());
				} else {
					$eaninval = [];
					$sql = "INSERT INTO BDES.dbo.DBArticulosVirtualesBarraFotos_01
							VALUES ";

					for ($i=0; $i < count($imgconv); $i++) {
						if(strlen($imgconv[$i])<=13) {
							$sql .= "('" .$imgconv[$i]. "', 1),";
						} else {
							$eaninval[] = $imgconv;
						}
					}

					$result = $sql;
					$sql = $connec->query(substr($sql, 0, -1));
					if(!$sql) {
						echo 0;
						print_r($connec->errorInfo());
					} else {
						echo json_encode($imagenes);
					}
				}
				break;

			case 'subirImagenesFTP':
				// Nos conectamos al servidor FTP
				if($idpara==1) {
					$rutad = '../tmp/imgproc/';
					$dir = opendir($rutad);
					// Leo todos los ficheros de la carpeta
					while ($elemento = readdir($dir)){
						// Tratamos los elementos . y .. que tienen todas las carpetas
						if ($elemento != "." && $elemento != "..") {
							// Si es una carpeta
							if (!is_dir($rutad . $elemento)) {
								$archivo = explode('.', $elemento);
								$archivo = $archivo[0];
								$imagenes[] = [
									'id' => $archivo,
									'archivo' => 'tmp/imgproc/'.$elemento,
									'texto' => $elemento
								];
							}
						}
					} // End del while
					echo json_encode($imagenes);
				} else {
					ini_set('display_errors','Off');
					extract($_POST);
					$id_ftp = ftp_connect($svrftp); //Obtiene un manejador del Servidor FTP
					if(!$id_ftp) { // Si no se pudo conectar o loguear se retorna con error
						$id_ftp = ftp_ssl_connect($svrftp); //Obtiene un manejador del Servidor FTP
						if(!$id_ftp) {
							die;
						}
					}
					if($id_ftp) {
						$conect = ftp_login($id_ftp, $usrftp, $pasftp); //Se loguea al Servidor FTP
						ftp_pasv($id_ftp, $psvftp); //Establece el modo de conexión
						// Obtenemos el listado de archivos a subir
						if($conect) {
							$archivo = '../' . $archivo;
							if(ftp_put($id_ftp, $elemento, $archivo, FTP_BINARY)) {
								echo '1¬Realizado';
							} else {
								echo '0¬'.error_get_last()['message'];
							}
						} else {
							echo '0¬'.error_get_last()['message'];
						}
						ftp_quit($id_ftp); //Cierra la conexion FTP
					} else {
						echo '0¬'.error_get_last()['message'];
					}
					ini_set('display_errors','On');
				}
				break;

			case 'generarCsvWeb':
				$sql = "SELECT cods.escodigo AS referencia_n, avbf.Barra AS ean13,
							UPPER(art.descripcion) AS nombre,
							(CASE WHEN grp.DESCRIPCION IS NULL THEN UPPER(dpto.DESCRIPCION) ELSE
								(UPPER(dpto.DESCRIPCION)+','+UPPER(grp.DESCRIPCION)) END) AS categorias,
							COALESCE(ROUND(uc.precio1, 2, 1), 0) AS preciosiniva,
							(CASE art.impuesto
								WHEN 19 THEN 1
								WHEN 16 THEN 1
								WHEN 5 THEN 2
								WHEN 8 THEN 2
								ELSE 0
							END) AS regla_imp,
							0 AS cantidad, COALESCE(ROUND(uc.costo, 2, 1), 0) AS precio_costo, 5 AS nivel_stock_level,
							0 AS enviamensage_go_mail,
							REPLACE(LOWER(art.descripcion), ' ', ',') AS etiquetas,
							('https://www.".$svrftp2."/site/imgjpg/'+avbf.Barra+'.jpeg') AS URLs,
							1 AS elimine_img_delete
						FROM BDES.dbo.DBArticulosVirtualesBarraFotos_01 AS avbf
							INNER JOIN BDES.dbo.ESCodigos AS cods ON avbf.Barra = cods.barra
							INNER JOIN BDES.dbo.ESARTICULOS AS art ON cods.escodigo = art.codigo
							LEFT JOIN BDES.dbo.BIDocumentoSincroUCosto AS uc ON uc.articulo = cods.escodigo
								AND sucursal = 12
							INNER JOIN BDES.dbo.ESDpto AS dpto ON art.departamento = dpto.CODIGO
							LEFT OUTER JOIN BDES.dbo.ESGrupos AS grp ON art.Grupo = grp.CODIGO";

				$sql = $connec->query($sql);
				if(!$sql) {
					echo 0;
					print_r($connec->errorInfo());
				} else {
					$filecsv = $sql->fetchAll(\PDO::FETCH_ASSOC);
					if(count($filecsv)>0) {
						$arraycab = [
							'Product ID',
							'Active (0/1)',
							'Name *',
							'Categories (x,y,z...)',
							'Price tax excluded',
							'Tax rules ID',
							'Wholesale price',
							'On sale (0/1)',
							'Discount amount',
							'Discount percent',
							'Discount from (yyyy-mm-dd)',
							'Discount to (yyyy-mm-dd)',
							'Reference #',
							'Supplier reference #',
							'Supplier',
							'Manufacturer',
							'EAN13',
							'UPC',
							'Ecotax',
							'Width',
							'Height',
							'Depth',
							'Weight',
							'Delivery time of in-stock products',
							'Delivery time of out-of-stock products with allowed orders',
							'Quantity',
							'Minimal quantity',
							'Low stock level',
							'Send me an email when the quantity is under this level',
							'Visibility',
							'Additional shipping cost',
							'Unity',
							'Unit price',
							'Summary',
							'Description',
							'Tags (x,y,z...)',
							'Meta title',
							'Meta keywords',
							'Meta description',
							'URL rewritten',
							'Text when in stock',
							'Text when backorder allowed',
							'Available for order (0 = No, 1 = Yes)',
							'Product available date',
							'Product creation date',
							'Show price (0 = No, 1 = Yes)',
							'Image URLs (x,y,z...)',
							'Image alt texts (x,y,z...)',
							'Delete existing images (0 = No, 1 = Yes)',
							'Feature(Name:Value:Position)',
							'Available online only (0 = No, 1 = Yes)',
							'Condition',
							'Customizable (0 = No, 1 = Yes)',
							'Uploadable files (0 = No, 1 = Yes)',
							'Text fields (0 = No, 1 = Yes)',
							'Out of stock action',
							'Virtual product',
							'File URL',
							'Number of allowed downloads',
							'Expiration date',
							'Number of days',
							'ID / Name of shop',
							'Advanced stock management',
							'Depends On Stock',
							'Warehouse',
							'Acessories  (x,y,z...)'
						];
						$icorr = date('mdHis');
						$file = '../tmp/imgcsvweb'.$icorr.'.csv';
						$linea = [];
						$fp = fopen($file, 'c');
						fputcsv($fp, $arraycab, ";", '"');
						// for($i=1;$i<=4;$i++) {
							foreach ($filecsv as $value) {
								$linea = [
									'', '',
									$value['nombre'],
									$value['categorias'],
									$value['preciosiniva'],
									$value['regla_imp'],
									$value['precio_costo'],
									'', '', '', '', '',
									$value['referencia_n'],
									'', '', '',
									$value['ean13'],
									'', '', '', '', '', '', '', '',
									$value['cantidad'], '',
									$value['nivel_stock_level'],
									$value['enviamensage_go_mail'],
									'', '', '', '', '', '',
									preg_replace('([^A-Za-z0-9 ])', '', $value['etiquetas']),
									'', '', '', '', '', '', '', '', '', '',
									$value['URLs'], '',
									$value['elimine_img_delete'],
									'', '', '', '', '', '', '', '', '', '', '', '',
									// $i,
									'',
									'', '', '', '',
								];
								fputcsv($fp, $linea, ";", '"');
							}
						// }
						fclose($fp);
						echo json_encode(['enlace'=>substr($file, 3), 'archivo'=>substr($file, 7)]);
					} else {
						echo json_encode(['enlace'=>'none', 'archivo'=>'none']);;
					}
				}
				break;

			case 'borrarArchivoCsvWeb':
				// Se borra el archivo TNS generado y descargado
				if(!unlink('../tmp/'.$idpara)){
					echo 'Se presentó un error, Informe a Sistemas';
				} else {
					echo 'El archivo se eliminó correctamente';
				}
				break;

			case 'consDispPerecederos':
				extract($_POST);
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
						LEFT JOIN	(SELECT articulo AS Material,
								(CASE WHEN localidad = $idpara THEN
									SUM(cantidad-usada) END) AS ExistLocal,
								(CASE WHEN localidad = 6 AND almacen IN (601, 602) THEN
									SUM(cantidad-usada) END) AS ExistCedim,
								localidad
							FROM BDES.dbo.BIKardexExistencias
							WHERE localidad = 6 OR localidad = $idpara
							GROUP BY articulo, localidad, almacen) AS d ON d.MATERIAL = a.codigo
						LEFT JOIN BDES.dbo.ESGrupos gp ON gp.codigo = a.grupo
						LEFT JOIN BDES.dbo.DBArtLocExclSol excl
							ON excl.codigo = a.codigo AND excl.localidad = $idpara
						LEFT JOIN BDES.dbo.BIKardexExistencias_Tienda bkt
							ON bkt.ARTICULO = a.codigo AND bkt.localidad = $idpara
						WHERE a.activo = 1 AND a.CPE = 8
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
				extract($_POST);
				$pedido   = explode(',', $pedido);
				$material = explode(',', $material);
				$exicedim = explode(',', $exicedim);
				$exilocal = explode(',', $exilocal);

				$sql = "INSERT INTO BDES.dbo.BISolicPedido(
							solipedi_fechasoli,localidad,solipedi_usuariosoli,
							solipedi_fechadesp,solipedi_usuariodesp,solipedi_status, centro_dist)
						VALUES (CURRENT_TIMESTAMP, ". $tienda .", '$usidnom', NULL, 0, 0, 6)";
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
				extract($_POST);
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
						WHERE centro_dist = 6 AND solipedi_status = 0
						AND localidad IN( $idloca )";
				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
				} else {
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
								WHERE localidad = 6 AND almacen IN (601, 602)
								GROUP BY articulo) AS d ON d.articulo = det.solipedidet_codigo
							WHERE centro_dist = 6 AND solipedi_status = 6 AND localidad IN( $idloca )
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
								WHERE centro_dist = 6 AND solipedi_status = 6 AND localidad IN( $idloca )
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
				extract($_POST);
				$codigos  = explode(',', $codigos);
				$cantidad = explode(',', $cantidad);
				$existen = explode(',', $existen);
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

				if($sql){
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

					if($sql){
						$sql = "UPDATE BDES.dbo.BISolicPedido SET
								solipedi_nrodespacho = $codid,
								solipedi_status = 7
								WHERE solipedi_status = 6
								AND localidad IN( $idloca );
								UPDATE BDES.dbo.BISoliPediDet SET
								solipedidet_nrodespacho = $codid
								WHERE solipedi_id IN(
									SELECT solipedi_id FROM
									BDES.dbo.BISolicPedido
									WHERE solipedi_nrodespacho = $codid
									AND solipedi_status = 7)";
						$result['query'] = $sql;
						$sql = $connec->query($sql);
						if($sql){
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
							if($sql){
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
				$sql="SELECT
						a.codigo,
						( SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
						WHERE b.escodigo = a.codigo AND b.codigoedi = 1) AS barra,
						a.descripcion, COALESCE(CAST(d.ExistCedim AS NUMERIC(18,0)), 0) AS existcedim
					FROM BDES.dbo.ESARTICULOS a
					LEFT JOIN
						(SELECT articulo, SUM(cantidad-usada) AS ExistCedim
						FROM BDES.dbo.BIKardexExistencias
						WHERE localidad = 6 AND almacen IN (601, 602)
						GROUP BY articulo) AS d ON d.articulo = a.codigo
					WHERE a.activo = 1 AND a.CPE = 8";

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
				$params    = explode('¬', $idpara);
				$codid     = $params[0];
				$idpara    = $params[1]=='null'?'cab.solipedi_usuarioespera':"'".$params[1]."'";
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
							WHERE cab.centro_dist = 6 AND cab.solipedi_status = 7
							AND cab.solipedi_nrodespacho = $codid )
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
						WHERE cab.centro_dist = 6
						AND cab.solipedi_status = 7
						AND cab.solipedi_nrodespacho = $codid
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

					$sql = "SELECT
								'c'+CAST(cab.localidad AS VARCHAR)+'_'+CAST(det.solipedidet_codigo AS VARCHAR) AS id,
								SUM(det.solipedidet_pedido) AS pedido
							FROM BDES.dbo.BISolicPedido AS cab
							INNER JOIN BDES.dbo.BISoliPediDet AS det ON det.solipedi_id = cab.solipedi_id
							WHERE cab.centro_dist = 6 AND cab.solipedi_status = 7
							AND cab.solipedi_nrodespacho = $codid
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
									WHERE cab.centro_dist = 6 AND cab.solipedi_status = 7
									AND cab.solipedi_nrodespacho = $codid";
							$sql = $connec->query($sql);
							$row = $sql->fetch(\PDO::FETCH_ASSOC);
							$fdesde = date('d-m-Y h:i a', strtotime($row['desde']));
							$fhasta = date('d-m-Y h:i a', strtotime($row['hasta']));
						}

						$sql = "SELECT DISTINCT con.codigo, con.cantidad
								FROM BDES.dbo.DBCmpPerecederosD AS con
								INNER JOIN BDES.dbo.BISolicPedido AS cab ON cab.solipedi_nrodespacho = con.id_cab
								WHERE cab.centro_dist = 6 AND cab.solipedi_status = 7
								AND cab.solipedi_nrodespacho = $codid
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
									WHERE cab.centro_dist = 6 AND cab.solipedi_status = 7
									AND cab.solipedi_nrodespacho = $codid
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
					require_once "../Classes/PHPExcel.php";
					require_once "../Classes/PHPExcel/Writer/Excel5.php";

					$objPHPExcel = new PHPExcel();

					// Set document properties
					$objPHPExcel->getProperties()->setCreator("Dashboard")
												 ->setLastModifiedBy("Dashboard")
												 ->setTitle("Hoja de Trabajo Perecederos ".$fecha)
												 ->setSubject("Hoja de Trabajo Perecederos ".$fecha)
												 ->setDescription("Hoja de Trabajo Perecederos ".$fecha." generado usando el Dashboard.")
												 ->setKeywords("Office 2007 openxml php")
												 ->setCategory("Hoja de Trabajo Perecederos ".$fecha);

					$objPHPExcel->setActiveSheetIndex(0);

					$icorr = $fecha;
					$letra = 70;

					$objPHPExcel->getActiveSheet()->mergeCells('A1:'.chr($letra+count($tiendas)-1).'3');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A1', 'ARTÍCULOS PEDIDOS POR LAS TIENDAS A PERECEDEROS');
					$objPHPExcel->getActiveSheet()->mergeCells('A4:'.chr($letra+count($tiendas)-1).'4');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A4', 'Pedidos Realizados del '.$fdesde.' al '.$fhasta);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:A4')
						->getAlignment()
						->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:A4')
						->getAlignment()
						->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Logo');
					$objDrawing->setDescription('Logo');
					$objDrawing->setPath('../dist/img/logo-ppal.png');

					$objDrawing->setCoordinates('A1');
					$objDrawing->setResizeProportional(false);
					$objDrawing->setWidthAndHeight(80,80);
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());

					$rowCount = 5;

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
							->getHighestColumn().'5')->getFont()->setBold(true);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.$objPHPExcel->getActiveSheet()
							->getHighestColumn().'5')->getFont()->setSize(13);

					$objPHPExcel->getActiveSheet()
						->getStyle('A4:'.$objPHPExcel->getActiveSheet()
							->getHighestColumn().'4')->getFont()->setSize(11);

					$objPHPExcel->getActiveSheet()
								->getStyle('A5:'.chr($letra-1).'5')->getFill()
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

					foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
						$objPHPExcel
								->getActiveSheet()
								->getColumnDimension($col)
								->setAutoSize(true);
					}

					$objPHPExcel->getActiveSheet()
						->SetCellValue('C'.($rowCount), '=SUM(C6:C'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('D'.($rowCount), '=SUM(D6:D'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('E'.($rowCount), '=SUM(E6:E'.($rowCount-1).')');

					$letra = 70;
					foreach ($tiendas as $tienda) {
						$objPHPExcel->getActiveSheet()
							->SetCellValue(chr($letra).($rowCount),
								'=SUM('.chr($letra).'6:'.chr($letra).($rowCount-1).')');
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
						->getStyle('A5:'.chr($letra-1).($rowCount-1))
						->getBorders()
						->applyFromArray($styleArray);

					$objPHPExcel->getActiveSheet()
						->getStyle('A5:'.$objPHPExcel->getActiveSheet()
							->getHighestColumn().'5')->getFont()->setSize(12);

					foreach (range('A5', $objPHPExcel->getActiveSheet()->getHighestDataColumn()+'5') as $col) {
						$objPHPExcel
								->getActiveSheet()
								->getColumnDimension($col)
								->setAutoSize(true);
					}

					$objPHPExcel->getActiveSheet()->setSelectedCells('A6');

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

					$objWriter->save('../tmp/HDTPerecederos_'.$icorr.'.xls');

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
				extract($_POST);
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
				extract($_POST);
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
				extract($_POST);
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
				extract($_POST);
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
				extract($_POST);
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
				extract($_POST);
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
				extract($_POST);
				$sql = "SELECT DISTINCT cab.id, cab.fecha_registro AS fecha
						FROM BDES.dbo.DBCmpPerecederosC cab
						INNER JOIN BDES.dbo.DBCmpPerecederosD AS det ON det.id_cab = cab.id
						WHERE CAST(fecha_registro AS DATE) BETWEEN '$fdesde' AND '$fhasta'
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
							WHERE centro_dist = 6 AND solipedi_status = 7 AND solipedi_nrodespacho = $idpara
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
								WHERE localidad = 6 AND almacen IN (601, 602)
								GROUP BY articulo) AS d ON d.articulo = a.codigo
						WHERE a.activo = 1 AND (k.cantidad-k.comprado-k.nocomprar+k.dif_demas) > 0";

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
				extract($_POST);
				if($tipo==1) {
					// Se prepara los datos para el monitor
					$sql = "SELECT ped.localidad,
								ti.nombre AS sucursal, ped.solipedi_id AS id,
								RIGHT((CAST('00000' AS VARCHAR)+
								CAST(ped.solipedi_id AS VARCHAR)), 5) AS numero,
								ped.solipedi_fechasoli AS fecha
							FROM BDES.dbo.BISolicPedido AS ped
							INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = ped.localidad
							WHERE centro_dist = 6 AND ped.localidad = $idpara AND solipedi_status != 2
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
							WHERE cab.status = 0 AND CAST(cab.fecha_crea AS DATE) = '$fdesde'";

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
				extract($_POST);
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
									AND	cab.centro_dist = 6 AND cab.solipedi_status = 7
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
									AND	bkd.LOCALIDAD_ORIG = 6 AND bkd.TIPO = 17
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
				extract($_POST);

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
									AND cab.centro_dist = 6
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
										AND bkd.LOCALIDAD_ORIG = 6
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

			case 'listaCCBonos':
				$sql = "SELECT DISTINCT id_empresa, UPPER(nom_empresa) AS cliente,
						(SELECT SUM((CASE WHEN tipo = 1 THEN monto ELSE monto*(-1) END))
							FROM BDES_POS.dbo.DBBonos_H WHERE id_empresa = a.id_empresa) AS saldo
						FROM BDES_POS.dbo.DBBonos AS a";

				// Se chequea si se envio alguna informacion para el filtro
				if($idpara!='') {
					$sql .= " WHERE (id_empresa LIKE '%$idpara%'";
					$sql .= " OR nom_empresa LIKE '%$idpara%')";
				}

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id_empresa'  => $row['id_empresa'],
						'nom_empresa' => '<button type="button" title="Seleccionar" onclick="' .
											"seleccion('".$row['id_empresa']."','".$row['cliente']."',".$row['saldo'].")".
											'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
											'style="white-space: normal; line-height: 1;">' . ucwords(strtolower($row['cliente'])) .
										'</button>',
						'nombre'      => $row['cliente'],
						'saldo'       => $row['saldo'],
					];
				}

				echo json_encode($datos);
				break;

			case 'listaBenBonos':
				$sql = "SELECT
							DISTINCT id_beneficiario,
							UPPER(LTRIM(RTRIM(cl.RAZON))) AS cliente,
							(SELECT SUM((CASE WHEN tipo = 1 THEN monto ELSE monto*(-1) END))
								FROM BDES_POS.dbo.DBBonos_H
								WHERE id_beneficiario = a.id_beneficiario) AS saldo
						FROM BDES_POS.dbo.DBBonos AS a
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = a.id_beneficiario";

				// Se chequea si se envio alguna informacion para el filtro
				if($idpara!='') {
					$sql .= " WHERE (cl.RIF LIKE '%$idpara%'";
					$sql .= " OR cl.RAZON LIKE '%$idpara%')";
				}

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id_ben'  => $row['id_beneficiario'],
						'nom_ben' => '<button type="button" title="Seleccionar" onclick="' .
									"seleccion('".$row['id_beneficiario']."','".$row['cliente']."',".$row['saldo'].")".
									'" class="btn btn-sm btn-link m-0 p-0 text-left font-weight-bold" ' .
									'style="white-space: normal; line-height: 1;">' . $row['cliente'] .
									'</button>',
						'nombre'  => $row['cliente'],
						'saldo'   => $row['saldo'],
					];
				}

				echo json_encode($datos);
				break;

			case 'reportePMACab':
				extract($_POST);

				try {
					$server = explode('¬', $server);
					$srvvin = $server[1];
					$server = explode('\\', $server[0]);
					// connect to the sql server database
					if(count($server)>1) {
						$conStr = sprintf("sqlsrv:Server=%s\%s;",
							$server[0],
							$server[1]);
					} else {
						$conStr = sprintf("sqlsrv:Server=%s,%d", $server[0], $params['port_sql']);
					}
					$connec = new \PDO($conStr, $params['user_sql'], '');

					$sql = "SELECT ROW_NUMBER() OVER (ORDER BY fpago.caja) AS consecutivo,
								0 AS vrn, 0 AS act, SUBSTRING(vta.IDCLIENTE, 1, 2) AS tipodoc,
								SUBSTRING(vta.IDCLIENTE, 3, 13) AS doc, vta.RAZON AS razon,
								LTRIM(RTRIM(
									COALESCE(PARSENAME(
										REPLACE(REPLACE(LTRIM(RTRIM(vta.RAZON)), '  ', ' '), ' ', '. '), 4), '') + ' ' +
									COALESCE(PARSENAME(
										REPLACE(REPLACE(LTRIM(RTRIM(vta.RAZON)), '  ', ' '), ' ', '. '), 3), ''))) AS nombres,
								LTRIM(RTRIM(
									COALESCE(PARSENAME(
										REPLACE(REPLACE(LTRIM(RTRIM(vta.RAZON)), '  ', ' '), ' ', '. '), 2), '') + ' ' +
									COALESCE(PARSENAME(
										REPLACE(REPLACE(LTRIM(RTRIM(vta.RAZON)), '  ', ' '), ' ', '. '), 1), ''))) AS apellidos,
								vta.DOCUMENTO AS factura, vta.CAJA AS caja, bonos.monto AS vbono, fpago.MONTO AS vredimido,
								vta.FECHA AS fredencion, ti.Nombre AS sucursal
							FROM BDES.dbo.ESSucursales AS ti
							INNER JOIN BDES_POS.dbo.ESVENTASPOS_FP AS fpago
							INNER JOIN BDES_POS.dbo.ESVENTASPOS AS vta ON fpago.CAJA = vta.CAJA
								AND fpago.DOCUMENTO = vta.DOCUMENTO AND fpago.FECHA = vta.FECHA
								ON ti.codigo = vta.LOCALIDAD
							INNER JOIN (SELECT * FROM [$srvvin].BDES_POS.dbo.DBBonos_H
								WHERE id_empresa = '$clteid') AS bonos
								ON bonos.referencia = fpago.NUMERO
							WHERE (fpago.DENOMINACION = 45)
							AND CAST(fpago.FECHA AS DATE) BETWEEN '$fdesde' AND '$fhasta'";

					$sql = $connec->query($sql);
					if(!$sql) {
					 	print_r($connec->errorInfo());
						echo json_encode(array('enlace'=>'', 'archivo'=>''));
					} else {
						if($sql->rowCount()==0) {
							echo json_encode(array('enlace'=>'', 'archivo'=>''));
							break;
						} else {
							require_once "../Classes/PHPExcel.php";
							require_once "../Classes/PHPExcel/Writer/Excel5.php";

							$objPHPExcel = new PHPExcel();

							// Set document properties
							$objPHPExcel->getProperties()
								->setCreator("Dashboard")
								->setLastModifiedBy("Dashboard")
								->setTitle("Cabeceras Consumos PMA Supermercado Los Montes ".$fecha)
								->setSubject("Cabeceras Consumos PMA Supermercado Los Montes ".$fecha)
								->setDescription("Cabeceras Consumos PMA Supermercado Los Montes ".$fecha." generado usando el Dashboard.")
								->setKeywords("Office 2007 openxml php")
								->setCategory("Cabeceras Consumos PMA Supermercado Los Montes ".$fecha);

							$objPHPExcel->setActiveSheetIndex(0);

							$icorr = $fecha;

							$objPHPExcel->getActiveSheet()
								->SetCellValue('A1', 'Reporte de Consumos PMA Cabecera '.$fdesde.' al '.$fhasta);

							$rowCount = 2;

							$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, 'CONSECUTIVO');
							$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, 'VRN');
							$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, 'ACT');
							$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, 'TIPODOC');
							$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, 'DOC');
							$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, 'RAZON');
							$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, 'NOMBRES');
							$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, 'APELLIDOS');
							$objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, 'FACTURA');
							$objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, 'VBONO');
							$objPHPExcel->getActiveSheet()->SetCellValue('K'.$rowCount, 'VREDIMIDO');
							$objPHPExcel->getActiveSheet()->SetCellValue('L'.$rowCount, 'FREDENCION');
							$objPHPExcel->getActiveSheet()->SetCellValue('M'.$rowCount, 'SUCURSAL');
							$objPHPExcel->getActiveSheet()->SetCellValue('N'.$rowCount, 'DIFERENTE');
							$objPHPExcel->getActiveSheet()->SetCellValue('O'.$rowCount, 'CAJA');

							$objPHPExcel->getActiveSheet()
								->getStyle('A1:O2')->getFont()->setBold(true);

							$objPHPExcel->getActiveSheet()->mergeCells('A1:O1');

							$objPHPExcel->getActiveSheet()
								->getStyle('A1:O2')
								->getAlignment()
								->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
							$objPHPExcel->getActiveSheet()
								->getStyle('A1:O2')
								->getAlignment()
								->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

							$rowCount++;
							while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
								$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, $row['consecutivo']);
								$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $row['vrn']);
								$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, $row['act']);
								$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, $row['tipodoc']);
								$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, $row['doc']);
								$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, $row['razon']);
								$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, $row['nombres']);
								$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, $row['apellidos']);
								$objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, $row['factura']);
								$objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, $row['vbono']);
								$objPHPExcel->getActiveSheet()->SetCellValue('K'.$rowCount, $row['vredimido']);
								$objPHPExcel->getActiveSheet()->SetCellValue('L'.$rowCount, date('d-m-Y h:m a', strtotime($row['fredencion'])));
								$objPHPExcel->getActiveSheet()->SetCellValue('M'.$rowCount, $row['sucursal']);
								if($row['vredimido']>$row['vbono']) {
									$objPHPExcel->getActiveSheet()->SetCellValue('N'.$rowCount, '*');
								} else {
									$objPHPExcel->getActiveSheet()->SetCellValue('N'.$rowCount, '');
								}
								$objPHPExcel->getActiveSheet()->SetCellValue('O'.$rowCount, $row['caja']);
								$rowCount++;
							}

							$objPHPExcel->getActiveSheet()
								->getStyle('J3:K'.$rowCount)
								->getNumberFormat()
								->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

							$objPHPExcel->getActiveSheet()->freezePane('A3');

							$rowCount--;

							$objPHPExcel->getActiveSheet()->setAutoFilter('A2:O'.$rowCount);

							foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
								$objPHPExcel
									->getActiveSheet()
									->getColumnDimension($col)
									->setAutoSize(true);
							}

							$objPHPExcel->getActiveSheet()->setSelectedCell('A3');

							// Rename worksheet
							$objPHPExcel->getActiveSheet()->setTitle('ConsumosCabPMASLM');
							// Set active sheet index to the first sheet, so Excel opens this as the first sheet
							$objPHPExcel->setActiveSheetIndex(0);

							$objPHPExcel->getActiveSheet()->setSelectedCells('A3');

							// Redirect output to a client’s web browser (Excel5)
							header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
							header('Content-Disposition: attachment;filename="ConsumosCabPMASLM.xls"');
							header('Cache-Control: max-age=0');
							// If you're serving to IE 9, then the following may be needed
							header('Cache-Control: max-age=1');

							// If you're serving to IE over SSL, then the following may be needed
							header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
							header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
							header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
							header ('Pragma: public'); // HTTP/1.0

							$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

							$objWriter->save('../tmp/ConsumosCabPMASLM_'.$icorr.'.xls');

							echo json_encode(
								array(
									'enlace'  =>'tmp/ConsumosCabPMASLM_'.$icorr.'.xls',
									'archivo' =>'ConsumosCabPMASLM_'.$icorr.'.xls'
								)
							);
						}
					}
				} catch (PDOException $e) {
					echo "Error : " . $e->getMessage() . "<br/>";
					die();
				}
				break;

			case 'reportePMADet':
				extract($_POST);

				try {
					$server = explode('¬', $server);
					$srvvin = $server[1];
					$server = explode('\\', $server[0]);
					// connect to the sql server database
					if(count($server)>1) {
						$conStr = sprintf("sqlsrv:Server=%s\%s;",
							$server[0],
							$server[1]);
					} else {
						$conStr = sprintf("sqlsrv:Server=%s,%d", $server[0], $params['port_sql']);
					}
					$connec = new \PDO($conStr, $params['user_sql'], '');

					$sql = "SELECT ROW_NUMBER() OVER (order by fpago.caja ) AS consecutivo,
								0 AS vrn, 0 AS act, SUBSTRING(vta.IDCLIENTE, 1, 2) AS tipodoc,
								SUBSTRING(vta.IDCLIENTE, 3, 13) AS doc, vta.RAZON AS razon,
								LTRIM(RTRIM(
									COALESCE(PARSENAME(
										REPLACE(REPLACE(LTRIM(RTRIM(vta.RAZON)), '  ', ' '), ' ', '. '), 4), '') + ' ' +
									COALESCE(PARSENAME(
										REPLACE(REPLACE(LTRIM(RTRIM(vta.RAZON)), '  ', ' '), ' ', '. '), 3), ''))) AS nombres,
								LTRIM(RTRIM(
									COALESCE(PARSENAME(
										REPLACE(REPLACE(LTRIM(RTRIM(vta.RAZON)), '  ', ' '), ' ', '. '), 2), '') + ' ' +
									COALESCE(PARSENAME(
										REPLACE(REPLACE(LTRIM(RTRIM(vta.RAZON)), '  ', ' '), ' ', '. '), 1), ''))) AS apellidos,
								vta.DOCUMENTO AS factura, vta.FECHA AS fredencion, vdet.ARTICULO AS codproducto,
								grp.DESCRIPCION1 AS grupoalimentos, art.descripcion AS productos,
								art.descripcion AS complemento, marca.GRUPO AS marca,
								art.peso, (CASE WHEN art.tipoarticulo = 0 THEN 'UD' ELSE 'KG' END) as unidad,
								vdet.precio, vdet.cantidad, vdet.subtotal AS total, ti.nombre AS sucursal
							FROM BDES.dbo.ESGrupos AS grp
							RIGHT OUTER JOIN BDES.dbo.ESARTICULOS AS art ON grp.DEPARTAMENTO = art.departamento
								AND grp.CODIGO = art.Grupo
							LEFT OUTER JOIN (SELECT * FROM BDES.dbo.ESGruposFichas WHERE UPPER(TIPO) = 'MARCA') AS marca
								ON marca.id = art.marca
							INNER JOIN BDES_POS.dbo.ESVENTASPOS_DET AS vdet ON art.codigo = vdet.ARTICULO
							INNER JOIN BDES_POS.dbo.ESVENTASPOS AS vta ON vta.CAJA = vdet.CAJA
								AND vta.DOCUMENTO = vdet.DOCUMENTO
							INNER JOIN BDES_POS.dbo.ESVENTASPOS_FP AS fpago ON fpago.CAJA = vta.CAJA
								AND fpago.DOCUMENTO = vta.DOCUMENTO
							INNER JOIN (SELECT * FROM [$srvvin].BDES_POS.dbo.DBBonos_H
								WHERE id_empresa = '$clteid') AS bonos
								ON bonos.referencia = fpago.NUMERO
							INNER JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = vta.LOCALIDAD
							WHERE (fpago.DENOMINACION = 45)
							AND CAST(fpago.FECHA AS DATE) BETWEEN '$fdesde' AND '$fhasta'";

					$sql = $connec->query($sql);
					if(!$sql) {
					 	print_r($connec->errorInfo());
						echo json_encode(array('enlace'=>'', 'archivo'=>''));
					} else {
						if($sql->rowCount()==0) {
							echo json_encode(array('enlace'=>'', 'archivo'=>''));
							break;
						} else {
							require_once "../Classes/PHPExcel.php";
							require_once "../Classes/PHPExcel/Writer/Excel5.php";

							$objPHPExcel = new PHPExcel();

							// Set document properties
							$objPHPExcel->getProperties()
								->setCreator("Dashboard")
								->setLastModifiedBy("Dashboard")
								->setTitle("Cabeceras Consumos PMA Supermercado Los Montes ".$fecha)
								->setSubject("Cabeceras Consumos PMA Supermercado Los Montes ".$fecha)
								->setDescription("Cabeceras Consumos PMA Supermercado Los Montes ".$fecha." generado usando el Dashboard.")
								->setKeywords("Office 2007 openxml php")
								->setCategory("Cabeceras Consumos PMA Supermercado Los Montes ".$fecha);

							$objPHPExcel->setActiveSheetIndex(0);

							$icorr = $fecha;

							$objPHPExcel->getActiveSheet()
								->SetCellValue('A1', 'Reporte de Consumos PMA Detalle '.$fdesde.' al '.$fhasta);

							$rowCount = 2;

							$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, 'CONSECUTIVO');
							$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, 'VRN');
							$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, 'ACT');
							$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, 'TIPODOC');
							$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, 'DOC');
							$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, 'RAZON');
							$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, 'NOMBRES');
							$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, 'APELLIDOS');
							$objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, 'FACTURA');
							$objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, 'FREDENCION');
							$objPHPExcel->getActiveSheet()->SetCellValue('K'.$rowCount, 'CODPRODUCTO');
							$objPHPExcel->getActiveSheet()->SetCellValue('L'.$rowCount, 'GRUPOALIMENTOS');
							$objPHPExcel->getActiveSheet()->SetCellValue('M'.$rowCount, 'PRODUCTOS');
							$objPHPExcel->getActiveSheet()->SetCellValue('N'.$rowCount, 'COMPLEMENTO');
							$objPHPExcel->getActiveSheet()->SetCellValue('O'.$rowCount, 'MARCA');
							$objPHPExcel->getActiveSheet()->SetCellValue('P'.$rowCount, 'PESO');
							$objPHPExcel->getActiveSheet()->SetCellValue('Q'.$rowCount, 'UNIDAD');
							$objPHPExcel->getActiveSheet()->SetCellValue('R'.$rowCount, 'PRECIO');
							$objPHPExcel->getActiveSheet()->SetCellValue('S'.$rowCount, 'CANTIDAD');
							$objPHPExcel->getActiveSheet()->SetCellValue('T'.$rowCount, 'TOTAL');
							$objPHPExcel->getActiveSheet()->SetCellValue('U'.$rowCount, 'SUCURSAL');

							$objPHPExcel->getActiveSheet()
								->getStyle('A1:U2')->getFont()->setBold(true);

							$objPHPExcel->getActiveSheet()->mergeCells('A1:U1');

							$objPHPExcel->getActiveSheet()
								->getStyle('A1:U2')
								->getAlignment()
								->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
							$objPHPExcel->getActiveSheet()
								->getStyle('A1:U2')
								->getAlignment()
								->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

							$rowCount++;
							while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
								$objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, $row['consecutivo']);
								$objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $row['vrn']);
								$objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, $row['act']);
								$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, $row['tipodoc']);
								$objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, $row['doc']);
								$objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, $row['razon']);
								$objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, $row['nombres']);
								$objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, $row['apellidos']);
								$objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, $row['factura']);
								$objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, date('d-m-Y h:m a', strtotime($row['fredencion'])));
								$objPHPExcel->getActiveSheet()->SetCellValue('K'.$rowCount, $row['codproducto']);
								$objPHPExcel->getActiveSheet()->SetCellValue('L'.$rowCount, $row['grupoalimentos']);
								$objPHPExcel->getActiveSheet()->SetCellValue('M'.$rowCount, $row['productos']);
								$objPHPExcel->getActiveSheet()->SetCellValue('N'.$rowCount, $row['complemento']);
								$objPHPExcel->getActiveSheet()->SetCellValue('O'.$rowCount, $row['marca']);
								$objPHPExcel->getActiveSheet()->SetCellValue('P'.$rowCount, $row['peso']);
								$objPHPExcel->getActiveSheet()->SetCellValue('Q'.$rowCount, $row['unidad']);
								$objPHPExcel->getActiveSheet()->SetCellValue('R'.$rowCount, $row['precio']);
								$objPHPExcel->getActiveSheet()->SetCellValue('S'.$rowCount, $row['cantidad']);
								$objPHPExcel->getActiveSheet()->SetCellValue('T'.$rowCount, $row['total']);
								$objPHPExcel->getActiveSheet()->SetCellValue('U'.$rowCount, $row['sucursal']);
								$rowCount++;
							}

							$objPHPExcel->getActiveSheet()
								->getStyle('P3:P'.$rowCount)
								->getNumberFormat()
								->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

							$objPHPExcel->getActiveSheet()
								->getStyle('R3:T'.$rowCount)
								->getNumberFormat()
								->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

							$objPHPExcel->getActiveSheet()->freezePane('A3');

							$rowCount--;

							$objPHPExcel->getActiveSheet()->setAutoFilter('A2:U'.$rowCount);

							foreach (range('A', $objPHPExcel->getActiveSheet()->getHighestDataColumn()) as $col) {
								$objPHPExcel
									->getActiveSheet()
									->getColumnDimension($col)
									->setAutoSize(true);
							}

							$objPHPExcel->getActiveSheet()->setSelectedCell('A3');

							// Rename worksheet
							$objPHPExcel->getActiveSheet()->setTitle('ConsumosDetPMASLM');
							// Set active sheet index to the first sheet, so Excel opens this as the first sheet
							$objPHPExcel->setActiveSheetIndex(0);

							$objPHPExcel->getActiveSheet()->setSelectedCells('A3');

							// Redirect output to a client’s web browser (Excel5)
							header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
							header('Content-Disposition: attachment;filename="ConsumosDetPMASLM.xls"');
							header('Cache-Control: max-age=0');
							// If you're serving to IE 9, then the following may be needed
							header('Cache-Control: max-age=1');

							// If you're serving to IE over SSL, then the following may be needed
							header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
							header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
							header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
							header ('Pragma: public'); // HTTP/1.0

							$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

							$objWriter->save('../tmp/ConsumosDetPMASLM_'.$icorr.'.xls');

							echo json_encode(
								array(
									'enlace'  =>'tmp/ConsumosDetPMASLM_'.$icorr.'.xls',
									'archivo' =>'ConsumosDetPMASLM_'.$icorr.'.xls'
								)
							);
						}
					}
				} catch (PDOException $e) {
					echo "Error : " . $e->getMessage() . "<br/>";
					die();
				}
				break;

			case 'consFacturaTienda':
				extract($_POST);

				try {
					$server = explode('¬', $server);
					$srvvin = $server[1];
					$server = explode('\\', $server[0]);
					// connect to the sql server database
					if(count($server)>1) {
						$conStr = sprintf("sqlsrv:Server=%s\%s;",
							$server[0],
							$server[1]);
					} else {
						$conStr = sprintf("sqlsrv:Server=%s,%d", $server[0], $params['port_sql']);
					}
					$connec = new \PDO($conStr, $params['user_sql'], '');

					$sql = "SELECT vta.IDCLIENTE, vta.RAZON, fp.ID,
								(vta.SUBTOTAL+vta.IMPUESTO-vta.DESCUENTO) AS MONTO,
								fp.MONTO AS MONTO_PAGO, fp.NUMERO, fp.DENOMINACION,
								fop.descripcion AS FORMAPAGO, fp.BANCO AS IDBANCO,
								ban.descripcion AS BANCO,
								COALESCE(bonos.id_beneficiario, '') AS ID_BENEFICIARIO,
								COALESCE((SELECT RAZON FROM [$srvvin].BDES_POS.dbo.ESCLIENTESPOS
								WHERE RIF = bonos.id_beneficiario), '') AS NOM_CLIENTE,
								bonos.monto AS MONTO_BONO
							FROM BDES_POS.dbo.ESVENTASPOS AS vta
							INNER JOIN BDES_POS.dbo.ESVENTASPOS_FP AS fp
								ON fp.DOCUMENTO = vta.DOCUMENTO AND fp.CAJA = vta.CAJA
							LEFT JOIN BDES.dbo.ESFormasPago AS fop
								ON fop.codigo = fp.DENOMINACION
							LEFT JOIN BDES.dbo.ESBancos AS ban
								ON ban.codigo = fp.BANCO
							LEFT JOIN [$srvvin].BDES_POS.dbo.DBBonos_H AS bonos
								ON id_empresa = '$clteid'
								AND bonos.referencia = fp.NUMERO
								AND bonos.tipo = 0
							WHERE vta.DOCUMENTO = $nrofac AND vta.CAJA = $nrocaj";

					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());

					$datos = [];
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = [
							'idcliente'  => $row['IDCLIENTE'],
							'razon'      => $row['RAZON'],
							'monto'      => number_format($row['MONTO'], 2),
							'monto_pago' => number_format($row['MONTO_PAGO'], 2),
							'referencia' => $row['NUMERO'],
							'fpago'      => $row['DENOMINACION'].'->'.$row['FORMAPAGO'],
							'banco'      => $row['IDBANCO'].'->'.$row['BANCO'],
							'id_bono'    => $row['ID_BENEFICIARIO'],
							'nom_bono'   => $row['NOM_CLIENTE'],
							'mon_bono'   => number_format($row['MONTO_BONO'], 2),
							'denomina'   => $row['DENOMINACION']*1,
							'idbanco'    => $row['IDBANCO']*1,
							'idpago'     => $row['ID'],
							'marcar'     => '<button class="btn btn-sm btn-primary">'.
											'<i class="fas fa-pencil-alt"></i>'.
											'</button>',
						];
					}

					echo json_encode($datos);
				} catch (PDOException $e) {
					echo "Error : " . $e->getMessage() . "<br/>";
					die();
				}
				break;

			case 'busDatosBono':
				$sql = "SELECT id_beneficiario, monto, referencia, cli.RAZON
						FROM BDES_POS.dbo.DBBonos_H AS bonos
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cli
							ON cli.RIF = bonos.id_beneficiario
						WHERE bonos.referencia = '$idpara'
						OR CAST(bonos.id AS VARCHAR) = '$idpara'";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'nombre'     => $row['RAZON'],
						'id_ben'     => $row['id_beneficiario'],
						'monto'      => number_format($row['monto'], 2),
						'referencia' => $row['referencia'],
					];
				}

				echo json_encode($datos);
				break;

			case 'savInfoPayBonoRedimido':
				extract($_POST);

				try {
					$server = explode('¬', $server);
					$srvvin = $server[1];
					$id_loc = $server[2];
					$server = explode('\\', $server[0]);
					// connect to the sql server database
					if(count($server)>1) {
						$conStr = sprintf("sqlsrv:Server=%s\%s;",
							$server[0],
							$server[1]);
					} else {
						$conStr = sprintf("sqlsrv:Server=%s,%d", $server[0], $params['port_sql']);
					}
					$connec = new \PDO($conStr, $params['user_sql'], '');

					$sql = "UPDATE BDES_POS.dbo.ESVENTASPOS_FP SET
								DENOMINACION = 45,
								BANCO = 96,
								NUMERO = '$refbon'
							WHERE ID = $idpago;

							UPDATE BDES_POS.dbo.ESVENTASPOS SET
								IDCLIENTE = '$midben',
								RAZON = '$mnombo'
							WHERE DOCUMENTO = $nrofac AND CAJA = $nrocaj;

							UPDATE BDES_POS.dbo.ESCLIENTESPOS SET
								RAZON = '$mnombo'
							WHERE RIF = '$midben';

							UPDATE [$srvvin].BDES_POS.dbo.DBBonos_H SET
								localidad = $id_loc,
								caja = $nrocaj,
								documento = $nrofac,
								status = 1
							WHERE referencia = '$refbon'
							AND status = 0";
					// echo $sql;
					$sql = $connec->query($sql);
					if(!$sql) {
						echo '0¬Error: '.$connec->errorInfo()[2];
					} else {
						echo '1¬Modificación realizada correctamente';
					}
				} catch (PDOException $e) {
					echo "0¬Error : " . $e->getMessage() . "<br/>";
					die();
				}
				break;

			case 'genFactPDF':
				extract($_POST);

				try {
					$server = explode('¬', $server);
					$srvvin = $server[1];
					$server = explode('\\', $server[0]);
					// connect to the sql server database
					if(count($server)>1) {
						$conStr = sprintf("sqlsrv:Server=%s\%s;",
							$server[0],
							$server[1]);
					} else {
						$conStr = sprintf("sqlsrv:Server=%s,%d", $server[0], $params['port_sql']);
					}
					$connec = new \PDO($conStr, $params['user_sql'], '');

					$sql = "SELECT fpago.DOCUMENTO, fpago.CAJA
							FROM BDES_POS.dbo.ESVENTASPOS_FP AS fpago
							INNER JOIN (SELECT * FROM [$srvvin].BDES_POS.dbo.DBBonos_H
								WHERE id_empresa = '$clteid') AS bonos
								ON bonos.referencia = fpago.NUMERO
							WHERE (fpago.DENOMINACION = 45)
							AND CAST(fpago.FECHA AS DATE) = '$fdesde'";

					$sql = $connec->query($sql);
					if(!$sql) {
					 	print_r($connec->errorInfo());
						echo json_encode(
							array(
								'error' => $connec->errorInfo()[0],
								'mensaje'=>$connec->errorInfo()[2]
							)
						);
					} else {
						$datos = [];
						while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
							$datos[] = $row;
						}
						echo json_encode($datos);
					}
				} catch (PDOException $e) {
					echo "Error : " . $e->getMessage() . "<br/>";
					die();
				}
				break;

			case 'listArtBimas':
				extract($_POST);

				$tiendas   = [];
				$articulos = [];
				$datos     = [];
				$thtabla   = '';
				$tabla     = '';

				$sql = "SELECT codigo, rtrim(ltrim(nombre)) AS nombre
						FROM BDES.dbo.ESSucursales
						WHERE activa = 1
						AND codigo IN ( $idpara )
						ORDER BY rtrim(ltrim(nombre))";

				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);

				// Se prepara el array para almacenar los datos obtenidos
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$tiendas[] = $row;
				}

				$sql = "SELECT ti.codigo AS localidad, a.codigo, a.descripcion,
							(SELECT TOP 1 barra FROM BDES.dbo.ESCodigos AS b
							WHERE b.escodigo = a.codigo AND b.codigoedi = 1) AS barra, ";
				$sql.= (strpos($otrosc, 'dpto')!==false) ? 'dpto.DESCRIPCION AS departamento, ' : '';
				$sql.= (strpos($otrosc, 'grpo')!==false) ? 'g.descripcion AS grupo, ' : '';
				$sql.= (strpos($otrosc, 'sgrp')!==false) ? "COALESCE(sg.descripcion, '') AS subgrupo, " : '';
				$sql.= (strpos($otrosc, 'mark')!==false) ? "COALESCE(m.GRUPO, '') AS marca, " : '';
				$sql.= (strpos($otrosc, 'prov')!==false) ? 'pro.descripcion AS proveedor, ' : '';
				$sql.= (strpos($otrosc, 'exis')!==false) ? 'COALESCE((ex.cantidad-ex.usada), 0) AS existencia, ' : '';
				$sql.= (strpos($otrosc, 'csto')!==false) ? 'cst.costo AS costo, ' : '';
				$sql.= (strpos($otrosc, 'mrgn')!==false) ?
					'( CASE WHEN cst.precio1 = 0 THEN 0
						ELSE (( cst.precio1- cst.costo ) / cst.precio1 ) * 100 END ) AS margen, ' : '';
				$sql.= (strpos($otrosc, 'pvps')!==false) ? 'cst.precio1 AS precio, ' : '';
				$sql.= (strpos($otrosc, 'alic')!==false) ? 'a.impuesto AS alicuota, ' : '';
				$sql.= "	( CASE WHEN cst.precio1 = 0 THEN 0
							ELSE cst.precio1* ( 1+ ( a.impuesto/ 100 )) END ) AS precioiva
						FROM
							BDES.dbo.ESArticulos a
							INNER JOIN BDES.dbo.ESSucursales ti ON ti.codigo IN ( $idpara )
							INNER JOIN BDES.dbo.BIDocumentoSincroUCosto AS cst ON cst.articulo = a.codigo AND cst.sucursal = ti.codigo ";
				$sql.= (strpos($otrosc, 'exis')!==false) ?
							'INNER JOIN BDES.dbo.BiKardexExistencias AS ex ON ex.articulo = a.codigo AND ex.localidad= ti.codigo ' : '';
				$sql.= (strpos($otrosc, 'grpo')!==false) ?
							'LEFT JOIN BDES.dbo.ESGrupos g ON g.codigo = a.grupo ' : '';
				$sql.= (strpos($otrosc, 'sgrp')!==false) ?
							'LEFT JOIN BDES.dbo.ESSubgrupos sg ON sg.codigo = a.subgrupo ' : '';
				$sql.= (strpos($otrosc, 'mark')!==false) ?
							"LEFT JOIN BDES.dbo.ESGruposFichas m ON m.ID = a.marca AND LOWER ( m.tipo ) = 'marca' " : '';
				$sql.= (strpos($otrosc, 'dpto')!==false) ?
							'LEFT JOIN BDES.dbo.ESDpto dpto ON dpto.CODIGO = a.departamento ' : '';
				$sql.= ($idprov!='') ?
							'LEFT JOIN BDES.dbo.ESArticulosxProv pxa ON pxa.articulo = a.codigo
							LEFT JOIN BDES.dbo.ESProveedores pro ON pro.codigo = pxa.proveedor ' : '';
				$sql.= 'WHERE a.activo = 1 ';
				if($idarti!='') {
					$sql.= ' AND a.codigo = '.$idarti;
				} else {
					$sql.= $iddpto=='*'? ' AND a.departamento = a.departamento' : 'AND a.departamento = '.$iddpto;
					$sql.= $idgrpo!='' ? ' AND a.Grupo = '.$idgrpo : '';
					$sql.= $idsgrp!='' ? ' AND a.Subgrupo = '.$idsgrp : '';
					$sql.= $idprov!='' ? ' AND pxa.proveedor = '.$idprov : '';
				}
				$sql.= ' GROUP BY
							ti.codigo,
							a.codigo,
							a.descripcion,
							cst.costo,
							cst.precio1,';
				$sql.= (strpos($otrosc, 'dpto')!==false) ? ' dpto.DESCRIPCION, ' : '';
				$sql.= (strpos($otrosc, 'grpo')!==false) ? ' g.descripcion, ' : '';
				$sql.= (strpos($otrosc, 'sgrp')!==false) ? ' sg.descripcion, ' : '';
				$sql.= (strpos($otrosc, 'mark')!==false) ? ' m.GRUPO, ' : '';
				$sql.= (strpos($otrosc, 'prov')!==false) ? ' pro.descripcion, ' : '';
				$sql.= (strpos($otrosc, 'exis')!==false) ? ' ex.cantidad, ex.usada, ' : '';
				$sql.= '	a.impuesto';

				// Ejecutar connsulta en BBDD
				$sql = $connec->query($sql);

				if(!$sql) print_r($connec->errorInfo());

				// Se prepara el array para almacenar los datos obtenidos
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if(array_search($row['codigo'], array_column($articulos, 'codigo'))===false) {
						$articulos[] = [
							'codigo'       => $row['codigo'],
							'barra'        => $row['barra'],
							'descripcion'  => $row['descripcion'],
							'departamento' => (strpos($otrosc, 'dpto')!==false) ? $row['departamento'] : '',
							'grupo'        => (strpos($otrosc, 'grpo')!==false) ? $row['grupo'] : '',
							'subgrupo'     => (strpos($otrosc, 'sgrp')!==false) ? $row['subgrupo'] : '',
							'marca'        => (strpos($otrosc, 'mark')!==false) ? $row['marca'] : '',
							'proveedor'    => (strpos($otrosc, 'prov')!==false) ? $row['proveedor'] : '',
						];
					}
					$exis = $row['existencia'];
					if(strpos($otrosc, 'exis')!==false) {
						$exis = ($exis==.000) ? 0 : $exis;
					}
					$datos[] = [
						'codigo'       => $row['codigo'],
						'localidad'    => $row['localidad'],
						'precioiva'    => $row['precioiva'],
						'existencia'   => $exis,
						'costo'        => (strpos($otrosc, 'csto')!==false) ? $row['costo'] : '',
						'precio'       => (strpos($otrosc, 'pvps')!==false) ? $row['precio'] : '',
						'margen'       => (strpos($otrosc, 'mrgn')!==false) ? $row['margen'] : '',
						'alicuota'     => (strpos($otrosc, 'alic')!==false) ? $row['alicuota'] : '',
					];
				}

				$infogral = 3;

				if(strpos($otrosc, 'dpto')!==false) $infogral++;
				if(strpos($otrosc, 'grpo')!==false) $infogral++;
				if(strpos($otrosc, 'sgrp')!==false) $infogral++;
				if(strpos($otrosc, 'mark')!==false) $infogral++;
				if(strpos($otrosc, 'prov')!==false) $infogral++;

				$info_loc = 1;

				if(strpos($otrosc, 'exis')!==false) $info_loc++;
				if(strpos($otrosc, 'csto')!==false) $info_loc++;
				if(strpos($otrosc, 'pvps')!==false) $info_loc++;
				if(strpos($otrosc, 'mrgn')!==false) $info_loc++;
				if(strpos($otrosc, 'alic')!==false) $info_loc++;

				$tabla = '<table id="tblLstPrecios"';
				$tabla.= 'class="table table-bordered table-striped table-hover txtcomp w-100 text-nowrap">';
				$tabla.= '<thead class="text-center">';
				$tabla.= '<tr>';
				$tabla.= '<th colspan="'.$infogral.'" class="bg-dark">Listado de Precios Bi+ [ ' . date('d-m-Y h:i a') . ' ]</th>';

				foreach ($tiendas as $tienda) {
					$tabla.= '<th title="'.$tienda['nombre'].'" colspan="'.$info_loc.'"
								class="bg-info-gradient">'.$tienda['nombre'].'</th>';
				}

				$tabla.= '</tr>';

				$tabla.= '<tr>
					<th class="text-center align-middle bg-dark-gradient">Código</th>
					<th class="text-center align-middle bg-dark-gradient">Barra</th>
					<th class="text-center align-middle bg-dark-gradient">Descripcion</th>';

				if(strpos($otrosc, 'dpto')!==false) {
					$tabla.= '<th class="text-center align-middle bg-dark-gradient">Departamento</th>';
				}
				if(strpos($otrosc, 'grpo')!==false) {
					$tabla.= '<th class="text-center align-middle bg-dark-gradient">Grupo</th>';
				}
				if(strpos($otrosc, 'sgrp')!==false) {
					$tabla.= '<th class="text-center align-middle bg-dark-gradient">SubGrupo</th>';
				}
				if(strpos($otrosc, 'mark')!==false) {
					$tabla.= '<th class="text-center align-middle bg-dark-gradient">Marca</th>';
				}
				if(strpos($otrosc, 'prov')!==false) {
					$tabla.= '<th class="text-center align-middle bg-dark-gradient">Proveedor</th>';
				}

				foreach ($tiendas as $tienda) {
					if(strpos($otrosc, 'exis')!==false) {
						$tabla.= '<th class="text-center align-middle bg-info-gradient">Exist.</th>';
					}
					if(strpos($otrosc, 'csto')!==false) {
						$tabla.= '<th class="text-center align-middle bg-info-gradient">Costo.</th>';
					}
					if(strpos($otrosc, 'mrgn')!==false) {
						$tabla.= '<th class="text-center align-middle bg-info-gradient">Margen</th>';
					}
					if(strpos($otrosc, 'pvps')!==false) {
						$tabla.= '<th class="text-center align-middle bg-info-gradient">Precio</th>';
					}
					if(strpos($otrosc, 'alic')!==false) {
						$tabla.= '<th class="text-center align-middle bg-info-gradient">%Alic.</th>';
					}
					$tabla.= '<th class="text-center align-middle bg-info-gradient">Pvp+IVA</th>';
				}
				$tabla.= '</tr></thead>';

				$tabla.= '<tbody>';

				foreach ($articulos as $articulo) {
					$tabla.= '<tr>';
					$tabla.= '<td>' . $articulo['codigo'] . '</td>';
					$tabla.= '<td style="mso-style-parent:style0; mso-number-format:'."'@'".'">' . $articulo['barra'] . '</td>';
					$tabla.= '<td>' . $articulo['descripcion'] . '</td>';

					if(strpos($otrosc, 'dpto')!==false) { $tabla.= '<td>'.$articulo['departamento'].'</td>'; }
					if(strpos($otrosc, 'grpo')!==false) { $tabla.= '<td>'.$articulo['grupo'].'</td>'; }
					if(strpos($otrosc, 'sgrp')!==false) { $tabla.= '<td>'.$articulo['subgrupo'].'</td>'; }
					if(strpos($otrosc, 'mark')!==false) { $tabla.= '<td>'.$articulo['marca'].'</td>'; }
					if(strpos($otrosc, 'prov')!==false) { $tabla.= '<td>'.$articulo['proveedor'].'</td>'; }

					foreach ($tiendas as $tienda) {
						if(strpos($otrosc, 'exis')!==false) {
							$tabla.= '<td align="right" data-val="0" id="exis'.$tienda['codigo'].$articulo['codigo'].'"></td>';
						}
						if(strpos($otrosc, 'csto')!==false) {
							$tabla.= '<td align="right" data-val="0" id="csto'.$tienda['codigo'].$articulo['codigo'].'"></td>';
						}
						if(strpos($otrosc, 'mrgn')!==false) {
							$tabla.= '<td align="right" data-val="0" id="mrgn'.$tienda['codigo'].$articulo['codigo'].'"></td>';
						}
						if(strpos($otrosc, 'pvps')!==false) {
							$tabla.= '<td align="right" data-val="0" id="pvps'.$tienda['codigo'].$articulo['codigo'].'"></td>';
						}
						if(strpos($otrosc, 'alic')!==false) {
							$tabla.= '<td align="right" data-val="0" id="alic'.$tienda['codigo'].$articulo['codigo'].'"></td>';
						}
						$tabla.= '<td class="calcular" align="right" data-val="0" id="prec'.$tienda['codigo'].$articulo['codigo'].'"></td>';
					}
					$tabla.= '</tr>';
				}

				$tabla.= '</tbody></table>';

				echo json_encode(
					array(
						'tabla'   => $tabla,
						'datos'   => $datos,
					)
				);
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

			case 'mvtosBMontesEmpR':
				extract($_POST);
				$sql = "SELECT COUNT(DISTINCT id_beneficiario) AS regs,
							SUM(debe) AS debe, SUM(haber) As haber,
							(SUM(debe)-SUM(haber)) AS saldo
						FROM (
							SELECT id_beneficiario,
							(CASE WHEN tipo = 1 THEN monto END) AS debe,
							(CASE WHEN tipo!= 1 THEN monto END) AS haber
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_empresa = '$idpara'
								AND CAST(fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta'
						) AS saldo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$row    = $sql->fetch(\PDO::FETCH_ASSOC);
				$items  = $row['regs']*1;
				$debeg  = $row['debe']*1;
				$haberg = $row['haber']*1;
				$saldog = $row['saldo']*1;

				$sql = "SELECT TOP 1000
							id_empresa, nom_empresa, id_beneficiario, nom_beneficiario,
							SUM(COALESCE(debe, 0)) as debe, SUM(COALESCE(haber, 0)) as haber,
							SUM(COALESCE(debe, 0))-SUM(COALESCE(haber, 0)) as saldo
						FROM
						(SELECT se.id_empresa, b.nom_empresa, se.id_beneficiario,
							cl.RAZON AS nom_beneficiario,
							(CASE WHEN se.tipo = 1 THEN se.monto END) AS debe,
							(CASE WHEN se.tipo !=1 THEN se.monto END) AS haber
						FROM (SELECT id_empresa, id_beneficiario, tipo, monto
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_empresa = '$idpara'
							AND CAST(fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta') AS se
						INNER JOIN
							(SELECT DISTINCT id_empresa, nom_empresa
							FROM BDES_POS.dbo.DBBonos) AS b ON b.id_empresa = se.id_empresa
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = se.id_beneficiario) AS movimientos
						GROUP BY id_empresa, nom_empresa, id_beneficiario, nom_beneficiario";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$i = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id'               => ++$i,
						'id_beneficiario'  => $row['id_beneficiario'],
						'nom_beneficiario' => $row['nom_beneficiario'],
						'debe'             => $row['debe']*1,
						'haber'            => $row['haber']*1,
						'saldo'            => $row['saldo']*1,
					];
				}

				echo json_encode(
					array(
						'data'   => $datos,
						'items'  => $items,
						'debeg'  => $debeg,
						'haberg' => $haberg,
						'saldog' => $saldog
					)
				);
				break;

			case 'exportResumenEmp':
				extract($_POST);
				$sql = "SELECT
							id_beneficiario, nom_beneficiario,
							SUM(COALESCE(debe, 0)) as debe, SUM(COALESCE(haber, 0)) as haber,
							SUM(COALESCE(debe, 0))-SUM(COALESCE(haber, 0)) as saldo
						FROM
						(SELECT se.id_empresa, b.nom_empresa, se.id_beneficiario,
							cl.RAZON AS nom_beneficiario,
							(CASE WHEN se.tipo = 1 THEN se.monto END) AS debe,
							(CASE WHEN se.tipo !=1 THEN se.monto END) AS haber
						FROM (SELECT id_empresa, id_beneficiario, tipo, monto
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_empresa = '$idpara'
							AND CAST(fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta') AS se
						INNER JOIN
							(SELECT DISTINCT id_empresa, nom_empresa
							FROM BDES_POS.dbo.DBBonos) AS b ON b.id_empresa = se.id_empresa
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = se.id_beneficiario) AS movimientos
						GROUP BY id_empresa, nom_empresa, id_beneficiario, nom_beneficiario";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$i = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id'      => ++$i,
						'id_ben'  => $row['id_beneficiario'],
						'nom_ben' => $row['nom_beneficiario'],
						'debe'    => $row['debe']*1,
						'haber'   => $row['haber']*1,
						'saldo'   => $row['saldo']*1,
					];
				}

				if(count($datos)>0) {
					require_once "../Classes/PHPExcel.php";
					require_once "../Classes/PHPExcel/Writer/Excel5.php";

					$objPHPExcel = new PHPExcel();

					// Set document properties
					$objPHPExcel->getProperties()->setCreator("Dashboard")
												 ->setLastModifiedBy("Dashboard")
												 ->setTitle("mvtosBMontesEmpD ".$fecha)
												 ->setSubject("mvtosBMontesEmpD ".$fecha)
												 ->setDescription("mvtosBMontesEmpD ".$fecha." generado usando el Dashboard.")
												 ->setKeywords("Office 2007 openxml php")
												 ->setCategory("mvtosBMontesEmpD ".$fecha);

					$objPHPExcel->setActiveSheetIndex(0);

					$icorr = $fecha;

					$letra = 64;
					$rowIni= 4;
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Nº');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'ID Beneficiario');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Nombre Beneficiario');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Debe');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Haber');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Saldo');

					$objPHPExcel->getActiveSheet()->mergeCells('A1:'.chr($letra).'1');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A1', 'MOVIMIENTO RESUMIDO DE LOS BONOS MONTES');

					$objPHPExcel->getActiveSheet()->mergeCells('A2:'.chr($letra).'2');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A2', 'CLIENTE A CRÉDITO ['.$idpara.'] '.$nomemp);

					$objPHPExcel->getActiveSheet()->mergeCells('A3:'.chr($letra).'3');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A3', 'DESDE: '.date('d-m-Y', strtotime($fdesde)).' HASTA: '.date('d-m-Y', strtotime($fhasta)));

					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)->getFont()->setBold(true);

					$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowIni.':'.chr($letra).$rowIni)->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB('FF9EB4D3');

					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)
						->getAlignment()
						->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)
						->getAlignment()
						->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

					$idb = '';
					$color = '';
					$rowCount = $rowIni + 1;
					foreach ($datos as $dato) {
						if($idb != $dato['id_ben']) {
							$idb = $dato['id_ben'];
							$color = $color=='FFE6EAED'?'':'FFE6EAED';
						}
						if($color!='') {
							$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowCount.':'.chr($letra).($rowCount))->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB($color);
						}
						$letra = 64;
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['id']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['id_ben']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['nom_ben']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['debe']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['haber']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['saldo']);
						$rowCount++;
					}

					$objPHPExcel->getActiveSheet()->setAutoFilter('A'.$rowIni.':'.chr($letra).$rowIni);

					$styleArray = array(
						'allborders' => array(
							'style' => PHPExcel_Style_Border::BORDER_THIN,
							'color' => array('argb' => 'FFD9D9D9')
						)
					);

					$objPHPExcel->getActiveSheet()
						->getStyle('A'.($rowIni+1).':'.chr($letra).($rowCount-1))
						->getBorders()
						->applyFromArray($styleArray);

					foreach (range('A', chr($letra)) as $col) {
						$objPHPExcel
								->getActiveSheet()
								->getColumnDimension($col)
								->setAutoSize(true);
					}

					$objPHPExcel->getActiveSheet()
						->SetCellValue('D'.($rowCount), '=SUM(D'.($rowIni+1).':D'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('E'.($rowCount), '=SUM(E'.($rowIni+1).':E'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('F'.($rowCount), '=D'.($rowCount).'-E'.($rowCount));

					$objPHPExcel->getActiveSheet()
						->getStyle('D'.($rowCount).':'.chr($letra).($rowCount))->getFont()->setBold(true);

					$objPHPExcel->getActiveSheet()
						->getStyle('D'.($rowIni+1).':'.chr($letra).$rowCount)
						->getNumberFormat()
						->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

					// Rename worksheet
					$objPHPExcel->getActiveSheet()->setTitle('mvtosBMontesEmpD');
					// Set active sheet index to the first sheet, so Excel opens this as the first sheet
					$objPHPExcel->setActiveSheetIndex(0);

					$objPHPExcel->getActiveSheet()->setSelectedCells('A'.($rowIni+1));
					$objPHPExcel->getActiveSheet()->freezePane('A'.($rowIni+1));

					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setDifferentOddEven(false);

					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setOddHeader('&R&D &T'.chr(10).'(Pág. &P / &N)');

					// Redirect output to a client’s web browser (Excel5)
					header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
					header('Content-Disposition: attachment;filename="mvtosBMontesEmpD.xls"');
					header('Cache-Control: max-age=0');
					// If you're serving to IE 9, then the following may be needed
					header('Cache-Control: max-age=1');

					// If you're serving to IE over SSL, then the following may be needed
					header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
					header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
					header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
					header ('Pragma: public'); // HTTP/1.0

					$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

					$objWriter->save('../tmp/mvtosBMontesEmpD_'.$icorr.'.xls');

					echo json_encode(array('enlace'=>'tmp/mvtosBMontesEmpD_'.$icorr.'.xls', 'archivo'=>'mvtosBMontesEmpD_'.$icorr.'.xls'));
				} else {
					echo json_encode(array('enlace'=>'', 'archivo'=>''));
				}

				break;

			case 'mvtosBMontesEmpD':
				extract($_POST);
				$sql = "SELECT COUNT(DISTINCT id_beneficiario) AS regs,
							SUM(debe) AS debe, SUM(haber) As haber,
							(SUM(debe)-SUM(haber)) AS saldo
						FROM (
							SELECT id_beneficiario,
							(CASE WHEN tipo = 1 THEN monto END) AS debe,
							(CASE WHEN tipo!= 1 THEN monto END) AS haber
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_empresa = '$idpara'
								AND CAST(fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta'
						) AS saldo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$row    = $sql->fetch(\PDO::FETCH_ASSOC);
				$items  = $row['regs']*1;
				$debeg  = $row['debe']*1;
				$haberg = $row['haber']*1;
				$saldog = $row['saldo']*1;

				$sql = "SELECT UPPER(id_beneficiario) AS id_beneficiario, saldo AS saldo
						FROM (
							SELECT id_beneficiario,
							SUM((CASE WHEN tipo = 1 THEN monto
							ELSE monto*(-1) END)) AS saldo
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_empresa = '$idpara'
								AND CAST(fecha AS DATE) < '$fdesde'
							GROUP BY id_beneficiario
						) AS saldo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$saldosi = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$saldosi[] = $row;
				}

				$sql = "SELECT TOP 1000 UPPER(b.id_beneficiario) AS id_beneficiario,
							UPPER(LTRIM(RTRIM(cl.RAZON))) AS RAZON, b.fecha, b.movimiento,
							COALESCE(ti.nombre, '') AS tienda,
							COALESCE(b.caja, 0) AS caja,
							COALESCE(b.documento, 0) AS factura,
							(CASE WHEN b.tipo = 1 THEN b.monto ELSE 0 END) AS debe,
							(CASE WHEN b.tipo!= 1 THEN b.monto ELSE 0 END) AS haber
						FROM BDES_POS.dbo.DBBonos_H b
						LEFT JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = b.localidad
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = id_beneficiario
						WHERE id_empresa = '$idpara'
						AND CAST(b.fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta'
						ORDER BY LTRIM(RTRIM(cl.RAZON)), b.id";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$i = 0;
				$ib = '';
				$saldoi = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($ib!=$row['id_beneficiario']) {
						$i = 0;
						$ib = $row['id_beneficiario'];
						$key = array_search($ib, array_column($saldosi, 'id_beneficiario'));
						if($key!==false) {
							$saldoi = $saldosi[$key]['saldo'];
							$datos[] = [
								'id'         => ++$i,
								'id_ben'     => $row['id_beneficiario'],
								'nom_ben'    => $row['RAZON'],
								'fecha'      => '',
								'movimiento' => 'Saldo Anterior al '.date('d-m-Y', strtotime($fdesde)),
								'debe'       => $saldoi>0?$saldoi:0,
								'haber'      => $saldoi<0?$saldoi:0,
								'saldo'      => $saldoi,
							];
						}
						$saldon = $saldoi;
					}
					$saldon += ($row['debe']-$row['haber']);
					$mvto = $row['movimiento'];
					if($row['tienda']!='') {
						$mvto = '<div class="txtcomp w-100">'.$row['movimiento'].'<br>&nbsp;'
							  . '[ <b>Tienda: </b>'.$row['tienda'].'&emsp;'
							  . '<b>Caja: </b>'.$row['caja'].'&emsp;'
							  . '<b>Factura: </b>'.$row['factura'].' ]'
							  . '</div>';
					}
					$datos[] = [
						'id'         => ++$i,
						'id_ben'     => $row['id_beneficiario'],
						'nom_ben'    => $row['RAZON'],
						'fecha'      => date('d-m-Y H:i a', strtotime($row['fecha'])),
						'movimiento' => $mvto,
						'debe'       => $row['debe']*1,
						'haber'      => $row['haber']*1,
						'saldo'      => $saldon,
					];
				}

				echo json_encode(
					array(
						'data'   => $datos,
						'items'  => $items,
						'debeg'  => $debeg,
						'haberg' => $haberg,
						'saldog' => $saldog
					)
				);
				break;

			case 'exportDetalleEmp':
				extract($_POST);

				$sql = "SELECT UPPER(id_beneficiario) AS id_beneficiario, saldo AS saldo
						FROM (
							SELECT id_beneficiario,
							SUM((CASE WHEN tipo = 1 THEN monto
							ELSE monto*(-1) END)) AS saldo
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_empresa = '$idpara'
								AND CAST(fecha AS DATE) < '$fdesde'
							GROUP BY id_beneficiario
						) AS saldo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$saldosi = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$saldosi[] = $row;
				}

				$sql = "SELECT UPPER(b.id_beneficiario) AS id_beneficiario,
							UPPER(LTRIM(RTRIM(cl.RAZON))) AS RAZON, b.fecha, b.movimiento,
							COALESCE(ti.nombre, '') AS tienda,
							COALESCE(b.caja, 0) AS caja,
							COALESCE(b.documento, 0) AS factura,
							(CASE WHEN b.tipo = 1 THEN b.monto ELSE 0 END) AS debe,
							(CASE WHEN b.tipo!= 1 THEN b.monto ELSE 0 END) AS haber
						FROM BDES_POS.dbo.DBBonos_H b
						LEFT JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = b.localidad
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = id_beneficiario
						WHERE id_empresa = '$idpara'
						AND CAST(b.fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta'
						ORDER BY LTRIM(RTRIM(cl.RAZON)), UPPER(b.id_beneficiario), b.id";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$i = 0;
				$ib = '';
				$saldoi = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($ib!=$row['id_beneficiario']) {
						$i = 0;
						$ib = $row['id_beneficiario'];
						$key = array_search($ib, array_column($saldosi, 'id_beneficiario'));
						if($key!==false) {
							$saldoi = $saldosi[$key]['saldo'];
							$datos[] = [
								'id'         => ++$i,
								'id_ben'     => $row['id_beneficiario'],
								'nom_ben'    => $row['RAZON'],
								'fecha'      => '',
								'movimiento' => 'Saldo Anterior al '.date('d-m-Y', strtotime($fdesde)),
								'debe'       => $saldoi>0?$saldoi:0,
								'haber'      => $saldoi<0?$saldoi:0,
								'saldo'      => $saldoi,
							];
						}
						$saldon = $saldoi;
					}
					$saldon += ($row['debe']-$row['haber']);
					$mvto = $row['movimiento'];
					if($row['tienda']!='') {
						$mvto = $row['movimiento'].'&nbsp;'
							  . '[ Tienda: '.$row['tienda'].'&emsp;'
							  . 'Caja: '.$row['caja'].'&emsp;'
							  . 'Factura: '.$row['factura'].' ]';
					}
					$datos[] = [
						'id'         => ++$i,
						'id_ben'     => $row['id_beneficiario'],
						'nom_ben'    => $row['RAZON'],
						'fecha'      => date('d-m-Y H:i a', strtotime($row['fecha'])),
						'movimiento' => $row['movimiento'],
						'tienda'     => $row['tienda'],
						'caja'       => $row['caja']==0?'':$row['caja'],
						'factura'    => $row['factura']==0?'':$row['factura'],
						'debe'       => $row['debe']*1,
						'haber'      => $row['haber']*1,
						'saldo'      => $saldon,
					];
				}

				if(count($datos)>0) {
					require_once "../Classes/PHPExcel.php";
					require_once "../Classes/PHPExcel/Writer/Excel5.php";

					$objPHPExcel = new PHPExcel();

					// Set document properties
					$objPHPExcel->getProperties()->setCreator("Dashboard")
												 ->setLastModifiedBy("Dashboard")
												 ->setTitle("mvtosBMontesEmpD ".$fecha)
												 ->setSubject("mvtosBMontesEmpD ".$fecha)
												 ->setDescription("mvtosBMontesEmpD ".$fecha." generado usando el Dashboard.")
												 ->setKeywords("Office 2007 openxml php")
												 ->setCategory("mvtosBMontesEmpD ".$fecha);

					$objPHPExcel->setActiveSheetIndex(0);

					$icorr = $fecha;

					$letra = 64;
					$rowIni= 4;
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Nº');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'ID Beneficiario');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Nombre Beneficiario');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Fecha');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Movimiento');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Tienda');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Caja');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Factura');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Debe');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Haber');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Saldo');

					$objPHPExcel->getActiveSheet()->mergeCells('A1:'.chr($letra).'1');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A1', 'MOVIMIENTO DETALLADO DE LOS BONOS MONTES');

					$objPHPExcel->getActiveSheet()->mergeCells('A2:'.chr($letra).'2');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A2', 'CLIENTE A CRÉDITO ['.$idpara.'] '.$nomemp);

					$objPHPExcel->getActiveSheet()->mergeCells('A3:'.chr($letra).'3');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A3', 'DESDE: '.date('d-m-Y', strtotime($fdesde)).' HASTA: '.date('d-m-Y', strtotime($fhasta)));

					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)->getFont()->setBold(true);

					$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowIni.':'.chr($letra).$rowIni)->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB('FF9EB4D3');

					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)
						->getAlignment()
						->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)
						->getAlignment()
						->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

					$idb = '';
					$color = '';
					$rowCount = $rowIni+1;
					foreach ($datos as $dato) {
						if($idb != $dato['id_ben']) {
							$idb = $dato['id_ben'];
							$color = $color=='FFE6EAED'?'':'FFE6EAED';
						}
						if($color!='') {
							$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowCount.':'.chr($letra).($rowCount))->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB($color);
						}
						$letra = 64;
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['id']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['id_ben']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['nom_ben']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['fecha']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['movimiento']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['tienda']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['caja']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['factura']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['debe']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['haber']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['saldo']);
						$rowCount++;
					}

					$objPHPExcel->getActiveSheet()->setAutoFilter('A'.$rowIni.':'.chr($letra).$rowIni);

					$styleArray = array(
						'allborders' => array(
							'style' => PHPExcel_Style_Border::BORDER_THIN,
							'color' => array('argb' => 'FFD9D9D9')
						)
					);

					$objPHPExcel->getActiveSheet()
						->getStyle('A'.($rowIni+1).':'.chr($letra).($rowCount-1))
						->getBorders()
						->applyFromArray($styleArray);

					foreach (range('A', chr($letra)) as $col) {
						$objPHPExcel
								->getActiveSheet()
								->getColumnDimension($col)
								->setAutoSize(true);
					}

					$objPHPExcel->getActiveSheet()
						->SetCellValue('I'.($rowCount), '=SUM(I'.($rowIni+1).':I'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('J'.($rowCount), '=SUM(J'.($rowIni+1).':J'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('K'.($rowCount), '=I'.($rowCount).'-J'.($rowCount));

					$objPHPExcel->getActiveSheet()
						->getStyle('I'.($rowCount).':'.chr($letra).($rowCount))->getFont()->setBold(true);

					$objPHPExcel->getActiveSheet()
						->getStyle('I'.($rowIni+1).':'.chr($letra).$rowCount)
						->getNumberFormat()
						->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

					// Rename worksheet
					$objPHPExcel->getActiveSheet()->setTitle('mvtosBMontesEmpD');
					// Set active sheet index to the first sheet, so Excel opens this as the first sheet
					$objPHPExcel->setActiveSheetIndex(0);

					$objPHPExcel->getActiveSheet()->setSelectedCells('A'.($rowIni+1));
					$objPHPExcel->getActiveSheet()->freezePane('A'.($rowIni+1)
				);

					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setDifferentOddEven(false);

					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setOddHeader('&R&D &T'.chr(10).'(Pág. &P / &N)');

					// Redirect output to a client’s web browser (Excel5)
					header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
					header('Content-Disposition: attachment;filename="mvtosBMontesEmpD.xls"');
					header('Cache-Control: max-age=0');
					// If you're serving to IE 9, then the following may be needed
					header('Cache-Control: max-age=1');

					// If you're serving to IE over SSL, then the following may be needed
					header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
					header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
					header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
					header ('Pragma: public'); // HTTP/1.0

					$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

					$objWriter->save('../tmp/mvtosBMontesEmpD_'.$icorr.'.xls');

					echo json_encode(array('enlace'=>'tmp/mvtosBMontesEmpD_'.$icorr.'.xls', 'archivo'=>'mvtosBMontesEmpD_'.$icorr.'.xls'));
				} else {
					echo json_encode(array('enlace'=>'', 'archivo'=>''));
				}

				break;

			case 'mvtosBMontesBenR':
				extract($_POST);
				$sql = "SELECT
							cab.id_empresa, cab.nom_empresa,
							COALESCE(SUM(det.debe), 0) AS debe,
							COALESCE(SUM(det.haber), 0) AS haber,
							COALESCE(SUM(det.debe)-SUM(det.haber), 0) AS saldo
						FROM
							(SELECT id_empresa, id_beneficiario,
								(CASE WHEN tipo = 1 THEN monto END) AS debe,
								(CASE WHEN tipo!= 1 THEN monto END) AS haber
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_beneficiario = '$idpara'
							AND CAST(fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta') det
						LEFT JOIN
							(SELECT id_empresa, nom_empresa, id_beneficiario
							FROM BDES_POS.dbo.DBBonos
							WHERE id_beneficiario = '$idpara') AS cab
							ON cab.id_beneficiario = det.id_beneficiario
							AND cab.id_empresa = det.id_empresa
						GROUP BY cab.id_empresa, cab.nom_empresa";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$i = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id'      => ++$i,
						'id_emp'  => $row['id_empresa'],
						'nom_emp' => $row['nom_empresa'],
						'debe'    => $row['debe']*1,
						'haber'   => $row['haber']*1,
						'saldo'   => $row['saldo']*1,
					];
				}

				echo json_encode($datos);
				break;

			case 'exportResumenBen':
				extract($_POST);
				$sql = "SELECT
							cab.id_empresa, cab.nom_empresa,
							COALESCE(SUM(det.debe), 0) AS debe,
							COALESCE(SUM(det.haber), 0) AS haber,
							COALESCE(SUM(det.debe)-SUM(det.haber), 0) AS saldo
						FROM
							(SELECT id_empresa, id_beneficiario,
								(CASE WHEN tipo = 1 THEN monto END) AS debe,
								(CASE WHEN tipo!= 1 THEN monto END) AS haber
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_beneficiario = '$idpara'
							AND CAST(fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta') det
						LEFT JOIN
							(SELECT id_empresa, nom_empresa, id_beneficiario
							FROM BDES_POS.dbo.DBBonos
							WHERE id_beneficiario = '$idpara') AS cab
							ON cab.id_beneficiario = det.id_beneficiario
							AND cab.id_empresa = det.id_empresa
						GROUP BY cab.id_empresa, cab.nom_empresa";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$i = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'id'      => ++$i,
						'id_emp'  => $row['id_empresa'],
						'nom_emp' => $row['nom_empresa'],
						'debe'    => $row['debe']*1,
						'haber'   => $row['haber']*1,
						'saldo'   => $row['saldo']*1,
					];
				}

				if(count($datos)>0) {
					require_once "../Classes/PHPExcel.php";
					require_once "../Classes/PHPExcel/Writer/Excel5.php";

					$objPHPExcel = new PHPExcel();

					// Set document properties
					$objPHPExcel->getProperties()->setCreator("Dashboard")
												 ->setLastModifiedBy("Dashboard")
												 ->setTitle("mvtosBMontesBenD ".$fecha)
												 ->setSubject("mvtosBMontesBenD ".$fecha)
												 ->setDescription("mvtosBMontesBenD ".$fecha." generado usando el Dashboard.")
												 ->setKeywords("Office 2007 openxml php")
												 ->setCategory("mvtosBMontesBenD ".$fecha);

					$objPHPExcel->setActiveSheetIndex(0);

					$icorr = $fecha;

					$letra = 64;
					$rowIni= 4;
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Nº');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'ID Empresa');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Nombre Empresa');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Debe');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Haber');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Saldo');

					$objPHPExcel->getActiveSheet()->mergeCells('A1:'.chr($letra).'1');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A1', 'MOVIMIENTO RESUMIDO DE LOS BONOS MONTES');

					$objPHPExcel->getActiveSheet()->mergeCells('A2:'.chr($letra).'2');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A2', 'CLIENTE BENEFICIARIO ['.$idpara.'] '.$nomben);

					$objPHPExcel->getActiveSheet()->mergeCells('A3:'.chr($letra).'3');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A3', 'DESDE: '.date('d-m-Y', strtotime($fdesde)).' HASTA: '.date('d-m-Y', strtotime($fhasta)));

					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)->getFont()->setBold(true);

					$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowIni.':'.chr($letra).$rowIni)->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB('FF9EB4D3');

					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)
						->getAlignment()
						->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)
						->getAlignment()
						->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

					$idb = '';
					$color = '';
					$rowCount = $rowIni + 1;
					foreach ($datos as $dato) {
						if($idb != $dato['id_ben']) {
							$idb = $dato['id_ben'];
							$color = $color=='FFE6EAED'?'':'FFE6EAED';
						}
						if($color!='') {
							$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowCount.':'.chr($letra).($rowCount))->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB($color);
						}
						$letra = 64;
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['id']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['id_emp']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['nom_emp']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['debe']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['haber']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['saldo']);
						$rowCount++;
					}

					$objPHPExcel->getActiveSheet()->setAutoFilter('A'.$rowIni.':'.chr($letra).$rowIni);

					$styleArray = array(
						'allborders' => array(
							'style' => PHPExcel_Style_Border::BORDER_THIN,
							'color' => array('argb' => 'FFD9D9D9')
						)
					);

					$objPHPExcel->getActiveSheet()
						->getStyle('A'.($rowIni+1).':'.chr($letra).($rowCount-1))
						->getBorders()
						->applyFromArray($styleArray);

					foreach (range('A', chr($letra)) as $col) {
						$objPHPExcel
								->getActiveSheet()
								->getColumnDimension($col)
								->setAutoSize(true);
					}

					$objPHPExcel->getActiveSheet()
						->SetCellValue('D'.($rowCount), '=SUM(D'.($rowIni+1).':D'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('E'.($rowCount), '=SUM(E'.($rowIni+1).':E'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('F'.($rowCount), '=D'.($rowCount).'-E'.($rowCount));

					$objPHPExcel->getActiveSheet()
						->getStyle('D'.($rowCount).':'.chr($letra).($rowCount))->getFont()->setBold(true);

					$objPHPExcel->getActiveSheet()
						->getStyle('D'.($rowIni+1).':'.chr($letra).$rowCount)
						->getNumberFormat()
						->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

					// Rename worksheet
					$objPHPExcel->getActiveSheet()->setTitle('mvtosBMontesBenD');
					// Set active sheet index to the first sheet, so Excel opens this as the first sheet
					$objPHPExcel->setActiveSheetIndex(0);

					$objPHPExcel->getActiveSheet()->setSelectedCells('A'.($rowIni+1));
					$objPHPExcel->getActiveSheet()->freezePane('A'.($rowIni+1));

					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setDifferentOddEven(false);

					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setOddHeader('&R&D &T'.chr(10).'(Pág. &P / &N)');

					// Redirect output to a client’s web browser (Excel5)
					header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
					header('Content-Disposition: attachment;filename="mvtosBMontesBenD.xls"');
					header('Cache-Control: max-age=0');
					// If you're serving to IE 9, then the following may be needed
					header('Cache-Control: max-age=1');

					// If you're serving to IE over SSL, then the following may be needed
					header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
					header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
					header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
					header ('Pragma: public'); // HTTP/1.0

					$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

					$objWriter->save('../tmp/mvtosBMontesBenD_'.$icorr.'.xls');

					echo json_encode(array('enlace'=>'tmp/mvtosBMontesBenD_'.$icorr.'.xls', 'archivo'=>'mvtosBMontesBenD_'.$icorr.'.xls'));
				} else {
					echo json_encode(array('enlace'=>'', 'archivo'=>''));
				}

				break;

			case 'mvtosBMontesBenD':
				extract($_POST);
				$sql = "SELECT COUNT(DISTINCT id_empresa) AS regs,
							SUM(debe) AS debe, SUM(haber) As haber,
							(SUM(debe)-SUM(haber)) AS saldo
						FROM
							(SELECT id_empresa,
								(CASE WHEN tipo = 1 THEN monto END) AS debe,
								(CASE WHEN tipo!= 1 THEN monto END) AS haber
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_beneficiario = '$idpara'
								AND CAST(fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta'
							) AS saldo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$row    = $sql->fetch(\PDO::FETCH_ASSOC);
				$items  = $row['regs']*1;
				$debeg  = $row['debe']*1;
				$haberg = $row['haber']*1;
				$saldog = $row['saldo']*1;

				$sql = "SELECT
							UPPER(id_empresa) AS id_empresa,
							saldo AS saldo
						FROM
							(SELECT id_empresa,
								SUM((CASE WHEN tipo = 1 THEN monto ELSE monto*(-1) END)) AS saldo
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_beneficiario = '$idpara'
								AND CAST(fecha AS DATE) < '$fdesde'
							GROUP BY id_empresa
							) AS saldo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$saldosi = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$saldosi[] = $row;
				}

				$sql = "SELECT
							UPPER(b.id_empresa) AS id_empresa,
							(SELECT DISTINCT nom_empresa
								FROM BDES_POS.dbo.DBBonos
								WHERE id_empresa = b.id_empresa) AS nom_empresa,
							b.fecha, b.movimiento, COALESCE(ti.nombre, '') AS tienda,
							COALESCE(b.caja, 0) AS caja, COALESCE(b.documento, 0) AS factura,
							(CASE WHEN b.tipo = 1 THEN b.monto ELSE 0 END) AS debe,
							(CASE WHEN b.tipo!= 1 THEN b.monto ELSE 0 END) AS haber
						FROM BDES_POS.dbo.DBBonos_H b
						LEFT JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = b.localidad
						WHERE b.id_beneficiario = '$idpara'
						AND CAST(b.fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta'
						ORDER BY nom_empresa, b.id";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$i = 0;
				$ib = '';
				$saldoi = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($ib!=$row['id_empresa']) {
						$i = 0;
						$ib = $row['id_empresa'];
						$key = array_search($ib, array_column($saldosi, 'id_empresa'));
						if($key!==false) {
							$saldoi = $saldosi[$key]['saldo'];
							$datos[] = [
								'id'         => ++$i,
								'id_ben'     => $row['id_empresa'],
								'nom_ben'    => $row['nom_empresa'],
								'fecha'      => '',
								'movimiento' => 'Saldo Anterior al '.date('d-m-Y', strtotime($fdesde)),
								'debe'       => $saldoi>0?$saldoi:0,
								'haber'      => $saldoi<0?$saldoi:0,
								'saldo'      => $saldoi,
							];
						}
						$saldon = $saldoi;
					}
					$saldon += ($row['debe']-$row['haber']);
					$mvto = $row['movimiento'];
					if($row['tienda']!='') {
						$mvto = '<div class="txtcomp w-100">'.$row['movimiento'].'<br>&nbsp;'
							  . '[ <b>Tienda: </b>'.$row['tienda'].'&emsp;'
							  . '<b>Caja: </b>'.$row['caja'].'&emsp;'
							  . '<b>Factura: </b>'.$row['factura'].' ]'
							  . '</div>';
					}
					$datos[] = [
						'id'         => ++$i,
						'id_ben'     => $row['id_empresa'],
						'nom_ben'    => $row['nom_empresa'],
						'fecha'      => date('d-m-Y H:i a', strtotime($row['fecha'])),
						'movimiento' => $mvto,
						'debe'       => $row['debe']*1,
						'haber'      => $row['haber']*1,
						'saldo'      => $saldon,
					];
				}

				echo json_encode(
					array(
						'data'   => $datos,
						'items'  => $items,
						'debeg'  => $debeg,
						'haberg' => $haberg,
						'saldog' => $saldog
					)
				);
				break;

			case 'exportDetalleBen':
				extract($_POST);

				$sql = "SELECT UPPER(id_empresa) AS id_empresa, saldo AS saldo
						FROM
							(SELECT id_empresa,
								SUM((CASE WHEN tipo = 1 THEN monto ELSE monto*(-1) END)) AS saldo
							FROM BDES_POS.dbo.DBBonos_H
							WHERE id_beneficiario = '$idpara'
								AND CAST(fecha AS DATE) < '$fdesde'
							GROUP BY id_empresa
							) AS saldo";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$saldosi = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$saldosi[] = $row;
				}

				$sql = "SELECT UPPER(b.id_empresa) AS id_empresa,
							(SELECT DISTINCT nom_empresa
								FROM BDES_POS.dbo.DBBonos
								WHERE id_empresa = b.id_empresa) AS nom_empresa,
							b.fecha, b.movimiento, COALESCE(ti.nombre, '') AS tienda,
							COALESCE(b.caja, 0) AS caja, COALESCE(b.documento, 0) AS factura,
							(CASE WHEN b.tipo = 1 THEN b.monto ELSE 0 END) AS debe,
							(CASE WHEN b.tipo!= 1 THEN b.monto ELSE 0 END) AS haber
						FROM BDES_POS.dbo.DBBonos_H b
						LEFT JOIN BDES.dbo.ESSucursales AS ti ON ti.codigo = b.localidad
						WHERE b.id_beneficiario = '$idpara'
						AND CAST(b.fecha AS DATE) BETWEEN '$fdesde' AND '$fhasta'
						ORDER BY nom_empresa, b.id";

				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());

				$i = 0;
				$ib = '';
				$saldoi = 0;
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($ib!=$row['id_empresa']) {
						$i = 0;
						$ib = $row['id_empresa'];
						$key = array_search($ib, array_column($saldosi, 'id_empresa'));
						if($key!==false) {
							$saldoi = $saldosi[$key]['saldo'];
							$datos[] = [
								'id'         => ++$i,
								'id_ben'     => $row['id_empresa'],
								'nom_ben'    => $row['nom_empresa'],
								'fecha'      => '',
								'movimiento' => 'Saldo Anterior al '.date('d-m-Y', strtotime($fdesde)),
								'debe'       => $saldoi>0?$saldoi:0,
								'haber'      => $saldoi<0?$saldoi:0,
								'saldo'      => $saldoi,
							];
						}
						$saldon = $saldoi;
					}
					$saldon += ($row['debe']-$row['haber']);
					$mvto = $row['movimiento'];
					if($row['tienda']!='') {
						$mvto = $row['movimiento'].'&nbsp;'
							  . '[ Tienda: '.$row['tienda'].'&emsp;'
							  . 'Caja: '.$row['caja'].'&emsp;'
							  . 'Factura: '.$row['factura'].' ]';
					}
					$datos[] = [
						'id'         => ++$i,
						'id_ben'     => $row['id_empresa'],
						'nom_ben'    => $row['nom_empresa'],
						'fecha'      => date('d-m-Y H:i a', strtotime($row['fecha'])),
						'movimiento' => $row['movimiento'],
						'tienda'     => $row['tienda'],
						'caja'       => $row['caja']==0?'':$row['caja'],
						'factura'    => $row['factura']==0?'':$row['factura'],
						'debe'       => $row['debe']*1,
						'haber'      => $row['haber']*1,
						'saldo'      => $saldon,
					];
				}

				if(count($datos)>0) {
					require_once "../Classes/PHPExcel.php";
					require_once "../Classes/PHPExcel/Writer/Excel5.php";

					$objPHPExcel = new PHPExcel();

					// Set document properties
					$objPHPExcel->getProperties()->setCreator("Dashboard")
												 ->setLastModifiedBy("Dashboard")
												 ->setTitle("mvtosBMontesBenD ".$fecha)
												 ->setSubject("mvtosBMontesBenD ".$fecha)
												 ->setDescription("mvtosBMontesBenD ".$fecha." generado usando el Dashboard.")
												 ->setKeywords("Office 2007 openxml php")
												 ->setCategory("mvtosBMontesBenD ".$fecha);

					$objPHPExcel->setActiveSheetIndex(0);

					$icorr = $fecha;

					$letra = 64;
					$rowIni= 4;
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Nº');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'ID Beneficiario');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Nombre Beneficiario');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Fecha');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Movimiento');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Tienda');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Caja');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Factura');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Debe');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Haber');
					$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).$rowIni, 'Saldo');

					$objPHPExcel->getActiveSheet()->mergeCells('A1:'.chr($letra).'1');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A1', 'MOVIMIENTO DETALLADO DE LOS BONOS MONTES');

					$objPHPExcel->getActiveSheet()->mergeCells('A2:'.chr($letra).'2');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A2', 'CLIENTE A CRÉDITO ['.$idpara.'] '.$nomben);

					$objPHPExcel->getActiveSheet()->mergeCells('A3:'.chr($letra).'3');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('A3', 'DESDE: '.date('d-m-Y', strtotime($fdesde)).' HASTA: '.date('d-m-Y', strtotime($fhasta)));

					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)->getFont()->setBold(true);

					$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowIni.':'.chr($letra).$rowIni)->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB('FF9EB4D3');

					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)
						->getAlignment()
						->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
					$objPHPExcel->getActiveSheet()
						->getStyle('A1:'.chr($letra).$rowIni)
						->getAlignment()
						->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

					$idb = '';
					$color = '';
					$rowCount = $rowIni+1;
					foreach ($datos as $dato) {
						if($idb != $dato['id_ben']) {
							$idb = $dato['id_ben'];
							$color = $color=='FFE6EAED'?'':'FFE6EAED';
						}
						if($color!='') {
							$objPHPExcel->getActiveSheet()
								->getStyle('A'.$rowCount.':'.chr($letra).($rowCount))->getFill()
								->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
								->getStartColor()->setARGB($color);
						}
						$letra = 64;
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['id']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['id_ben']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['nom_ben']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['fecha']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['movimiento']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['tienda']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['caja']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['factura']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['debe']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['haber']);
						$objPHPExcel->getActiveSheet()->SetCellValue(chr(++$letra).($rowCount), $dato['saldo']);
						$rowCount++;
					}

					$objPHPExcel->getActiveSheet()->setAutoFilter('A'.$rowIni.':'.chr($letra).$rowIni);

					$styleArray = array(
						'allborders' => array(
							'style' => PHPExcel_Style_Border::BORDER_THIN,
							'color' => array('argb' => 'FFD9D9D9')
						)
					);

					$objPHPExcel->getActiveSheet()
						->getStyle('A'.($rowIni+1).':'.chr($letra).($rowCount-1))
						->getBorders()
						->applyFromArray($styleArray);

					foreach (range('A', chr($letra)) as $col) {
						$objPHPExcel
								->getActiveSheet()
								->getColumnDimension($col)
								->setAutoSize(true);
					}

					$objPHPExcel->getActiveSheet()
						->SetCellValue('I'.($rowCount), '=SUM(I'.($rowIni+1).':I'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('J'.($rowCount), '=SUM(J'.($rowIni+1).':J'.($rowCount-1).')');
					$objPHPExcel->getActiveSheet()
						->SetCellValue('K'.($rowCount), '=I'.($rowCount).'-J'.($rowCount));

					$objPHPExcel->getActiveSheet()
						->getStyle('I'.($rowCount).':'.chr($letra).($rowCount))->getFont()->setBold(true);

					$objPHPExcel->getActiveSheet()
						->getStyle('I'.($rowIni+1).':'.chr($letra).$rowCount)
						->getNumberFormat()
						->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

					// Rename worksheet
					$objPHPExcel->getActiveSheet()->setTitle('mvtosBMontesBenD');
					// Set active sheet index to the first sheet, so Excel opens this as the first sheet
					$objPHPExcel->setActiveSheetIndex(0);

					$objPHPExcel->getActiveSheet()->setSelectedCells('A'.($rowIni+1));
					$objPHPExcel->getActiveSheet()->freezePane('A'.($rowIni+1)
				);

					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setDifferentOddEven(false);

					$objPHPExcel->getActiveSheet()
						->getHeaderFooter()->setOddHeader('&R&D &T'.chr(10).'(Pág. &P / &N)');

					// Redirect output to a client’s web browser (Excel5)
					header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
					header('Content-Disposition: attachment;filename="mvtosBMontesBenD.xls"');
					header('Cache-Control: max-age=0');
					// If you're serving to IE 9, then the following may be needed
					header('Cache-Control: max-age=1');

					// If you're serving to IE over SSL, then the following may be needed
					header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
					header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
					header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
					header ('Pragma: public'); // HTTP/1.0

					$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

					$objWriter->save('../tmp/mvtosBMontesBenD_'.$icorr.'.xls');

					echo json_encode(array('enlace'=>'tmp/mvtosBMontesBenD_'.$icorr.'.xls', 'archivo'=>'mvtosBMontesBenD_'.$icorr.'.xls'));
				} else {
					echo json_encode(array('enlace'=>'', 'archivo'=>''));
				}

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

	function dt_ventasxtienda(){
		global $fecha, $hora, $idpara, $connec, $dt_todos;
		// Se crea el query para obtener los totales por tienda
		$sql = "SELECT
					ti.ID, ti.nombre, COUNT(factura) AS canfac, COALESCE(SUM(subtotal), 0) AS total, COALESCE(SUM(costo), 0) AS costo,
					(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
						ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2 )
					END) AS margen
				FROM
					tiendas AS ti
					LEFT JOIN (SELECT factura, caja, localidad, SUM(subtotal) AS subtotal, SUM(costo) AS costo
					FROM detalle_dia
					WHERE fecha = '$fecha' AND hora <= '$hora'
					GROUP BY factura, caja, localidad) AS det ON det.localidad = ti.id ";
		if($idpara!='*') {
			$sql.=  " WHERE ti.id = '$idpara'";
		}
		$sql.= " GROUP BY ti.id, ti.nombre";

		// Se ejecuta la consulta en la BBDD
		$sql = $connec->query($sql);

		$cant_totalg = 0;
		$totalg_bs = 0;
		$costog_bs = 0;
		// Se prepara el array para almacenar los datos obtenidos
		$datos= [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
			$cant_totalg += $row['canfac'];
			$totalg_bs += $row['total'];
			$costog_bs += $row['costo'];
			$datos[] = [
				'tienda'     => '<button type="button" class="btn btn-sm btn-link m-0 p-0" onclick="actualizar(' .
								"'".$row['id']."', '".ucwords(strtolower($row['nombre']))."'".')">'.ucwords(strtolower($row['nombre'])).'</button>',
				'total'      => $row['total'],
				'canfac'     => $row['canfac'],
				'margen'     => $row['margen'] . '%',
			];
		}
		$datos = [ "data" => $datos, "totalg_bs" => $totalg_bs, "cant_totalg" => $cant_totalg, "costog_bs" => $costog_bs ];
		$dt_todos[] = [ 'ventasxtienda' => $datos ];
	}

	function dt_topxclientes(){
		global $fecha, $hora, $idpara, $connec, $dt_todos;
		// Se crea el query para obtener los totales por tienda
		$sql = "SELECT det.cliente, cli.razon,
					COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS total
				FROM detalle_dia det
				LEFT JOIN esclientes cli ON cli.rif = det.cliente
				WHERE det.fecha = '$fecha' AND det.hora <= '$hora'";

		if($idpara!='*') {
			$sql.=  " AND det.localidad = '$idpara'";
		}
		$sql.=  "	GROUP BY det.cliente, razon ORDER BY total DESC LIMIT 20";

		// Se ejecuta la consulta en la BBDD
		$sql = $connec->query($sql);

		$totalg_bs = 0;
		// Se prepara el array para almacenar los datos obtenidos
		$datos= [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
			$totalg_bs += $row['total'];
			if( $row['razon']==null ) {
				$txt = '<span class="badge badge-info font-weight-normal">Cliente Sin Sincronizar</span>';
			} else {
				$txt = ucwords(strtolower($row['razon']));
			}
			$txt = '<button type="button" title="Ver Facturas" onclick="listaFacturasxCliente(' .
					"'" . $idpara . "', '" . $row['cliente'] . "'" .
					')" class="btn btn-sm btn-link m-0 p-0 text-left" style="white-space: normal; line-height: 1;">' .
					$txt . '</button>';
			$datos[] = [
				'cliente'    => $txt,
				'total'      => $row['total'],
			];
		}
		$datos = [ "data" => $datos, "totalg_bs" => $totalg_bs ];
		$dt_todos[] = [ 'topxclientes' => $datos ];
	}

	function dt_topxtipopagos(){
		global $fecha, $hora, $idpara, $connec, $dt_todos;
		// Se crea el query para obtener los totales por tienda
		$sql = "SELECT (CASE WHEN fp.formadepago = 0 THEN 'OTROS' ELSE
						(SELECT efp.descripcion
							FROM esformaspago efp
							WHERE efp.codigo = fp.formadepago AND efp.moneda = 1)
					END) AS tipodepago,
				SUM(monto) AS total
				FROM formaspago_dia fp
				WHERE fecha = '$fecha' AND hora <= '$hora'";

		if($idpara!='*') {
			$sql.=  " AND tienda = '$idpara'";
		}

		$sql.=  " GROUP BY fp.formadepago ORDER BY total DESC";

		// Se ejecuta la consulta en la BBDD
		$sql = $connec->query($sql);

		$totalg_bs = 0;
		// Se prepara el array para almacenar los datos obtenidos
		$datos= [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
			$totalg_bs += $row['total'];
			$datos[] = [
				'tipodepago' => $row['tipodepago'],
				'total'      => $row['total']
			];
		}
		$datos = [ "data" => $datos, "totalg_bs" => $totalg_bs ];
		$dt_todos[] = [ 'topxtipopagos' => $datos ];
	}

	function dt_topxproductos(){
		global $fecha, $hora, $idpara, $connec, $dt_todos, $art_excl;
		// Se obtiene el nombre de la sucursal actual si se a seleccionado una
		if($idpara!='*') {
			$sql = "SELECT nombre FROM tiendas WHERE id = '$idpara'";

			$sql = $connec->query($sql);
			$row = $sql->fetch();

			$pnombretienda = $row['nombre'];
		} else {
			$pnombretienda = '';
		}

		// Se crea el query para obtener el top de productos
		$sql = "SELECT det.material, art.descripcion,
					COALESCE(ROUND(SUM(det.cantidad), 2), 0) AS cant_total,
					COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS total_bs,
					COALESCE(ROUND(SUM(det.costo), 2), 0) AS costo,
					(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
						ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2 )
					END) AS margen
				FROM detalle_dia det
				INNER JOIN esarticulos art ON art.codigo = det.material AND det.material NOT IN($art_excl)
				WHERE det.fecha = '$fecha' AND det.hora <= '$hora'";
		if($idpara!='*') {
			$sql.=  " AND det.localidad = '$idpara'";
		}
		$sql.=  " GROUP BY det.material, art.descripcion ";
		$sql.=  " ORDER BY cant_total DESC ";
		$sql.=  " LIMIT 10 ";

		// Se ejecuta la consulta en la BBDD
		$sql = $connec->query($sql);

		$cant_totalg = 0;
		$totalg_bs = 0;
		$costog_bs = 0;
		// Se prepara el array para almacenar los datos obtenidos
		$datos= [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
			$cant_totalg += $row['cant_total'];
			$totalg_bs += $row['total_bs'];
			$costog_bs += $row['costo'];
			$txt = '<button type="button" onclick="verDetallevtaArt(' .
						 "'" . $idpara . "', '" . $row['material'] . "'" . ')" ' .
						 'class="btn btn-sm btn-link m-0 p-0 text-left" ' .
						 'style="white-space: normal; line-height: 1;" ' .
						 'title="Código Articulo: ' . $row['material'] . '">' .
						 $row['descripcion'] . '</button>';
			$datos[] = [
				'material'   => $txt,
				'cant_total' => $row['cant_total'],
				'total_bs'   => $row['total_bs'],
				'margen' 	 => $row['margen'] . '%',
			];
		}
		$datos = [ "data" => $datos, "totalg_bs" => $totalg_bs, "cant_totalg" => $cant_totalg, "costog_bs" => $costog_bs ];
		$dt_todos[] = [ 'topxproductos' => $datos ];
	}

	function dt_topxdepartamento(){
		global $fecha, $hora, $idpara, $connec, $dt_todos;
		// Se crea el query para obtener el top de productos
		$sql = "SELECT
					COALESCE(dpto.codigo, 0) codigo,
					dpto.descripcion,
					COALESCE(ROUND(SUM(det.cantidad), 2), 0) AS cant_total,
					COALESCE(ROUND(SUM(det.subtotal), 2), 0) AS total_bs,
					COALESCE(ROUND(SUM(det.costo), 2), 0) AS costo,
					(CASE WHEN SUM(det.subtotal) = 0 THEN 0 ELSE
						ROUND((SUM(det.subtotal) - SUM(det.costo)) / SUM(det.subtotal) * 100, 2 )
					END) AS margen
				FROM detalle_dia det
				LEFT JOIN esarticulos art ON art.codigo = det.material
				LEFT JOIN esdpto dpto ON dpto.codigo = art.departamento
				WHERE det.fecha = '$fecha' AND det.hora <= '$hora'";
		if($idpara!='*') {
			$sql.=  " AND det.localidad = '$idpara'";
		}
		$sql.=  " GROUP BY dpto.codigo, dpto.descripcion";

		// Se ejecuta la consulta en la BBDD
		$sql = $connec->query($sql);

		$cant_totalg = 0;
		$totalg_bs = 0;
		$costog_bs = 0;
		// Se prepara el array para almacenar los datos obtenidos
		$datos= [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
			$cant_totalg += $row['cant_total'];
			$totalg_bs += $row['total_bs'];
			$costog_bs += $row['costo'];
			$descripcion = $row['descripcion']=='' ? '<span class="badge badge-danger">SIN CONFIGURAR</span>' : $row['descripcion'];
			$datos[] = [
				'descripcion' => '<button type="button" onclick="listaMaterialxDpto(' .
								 "'" . $idpara . "', '" . $row['codigo'] . "'" . ')" ' .
								 'class="btn btn-sm btn-link m-0 p-0 text-left" ' .
								 'style="white-space: normal; line-height: 1;">' .
								 $descripcion . '</button>',
				'cant_total'  => '<button type="button" onclick="margenDepartamentos(' .
									"'" . $row['codigo'] . "', '" . $fecha . "', '" . $hora . "', '" . $idpara . "')" . '" ' .
									'class="btn btn-sm btn-link m-0 p-0 text-left" style="white-space: normal; line-height: 1;">' .
									number_format($row['cant_total'], 2, '.', ',') . '</button>',
				'cantidad'    => $row['cant_total'],
				'total_bs'    => $row['total_bs'],
				'margen'      => $row['margen'] . '%',
			];
		}
		$datos = [ "data" => $datos, "totalg_bs" => $totalg_bs, "cant_totalg" => $cant_totalg, "costog_bs" => $costog_bs ];
		$dt_todos[] = [ 'topxdepartamento' => $datos ];
	}

	function guardarModulos($amodulos, $nusuario, $sconnec){
		// Array con los IDs de modulos permitidos y Usuario
		$sql = "DELETE FROM usuario_modulos WHERE usuario = '$nusuario'";
		$sql = $sconnec->query($sql);
		for ($i=0; $i < count($amodulos); $i++) {
			$sql = "INSERT INTO usuario_modulos VALUES('$nusuario', $amodulos[$i])";
			$sql = $sconnec->query($sql);
		}
	}

	function getFileDelimiter($file, $checkLines = 2){
		$file = new SplFileObject($file);
		$delimiters = array( ',', '\t', ';', '|', ':' );
		$results = array();
		$i = 0;
		while($file->valid() && $i <= $checkLines){
			$line = $file->fgets();
			foreach ($delimiters as $delimiter){
				$regExp = '/['.$delimiter.']/';
				$fields = preg_split($regExp, $line);
				if(count($fields) > 1){
					if(!empty($results[$delimiter])){
						$results[$delimiter]++;
					} else {
						$results[$delimiter] = 1;
					}
				}
			}
			$i++;
		}
		$results = array_keys($results, max($results)); return $results[0];
	}

	function getDataLocation($location){
		global $fecha, $hora, $idpara, $connec, $dt_todos;
		$sql = "SELECT nombre FROM BDES.dbo.ESSucursales WHERE activa = 1 AND codigo = $location ORDER BY rtrim(ltrim(nombre))";
		// Se ejecuta la consulta en la BBDD
		$sql = $connec->query($sql);
		$row = $sql->fetch(\PDO::FETCH_ASSOC);
		return $row;
	}

	function limpiarImagen($filename) {
		$img = imagecreatefrompng($filename); //or whatever loading function you need
		$remove = imagecolorallocate($img, 255, 255, 255);
		imagecolortransparent($img, $remove);
		imagepng($img, $filename);
	}
?>
