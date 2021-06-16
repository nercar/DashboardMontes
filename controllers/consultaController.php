<?php

	require_once __DIR__ . '/../vendor/autoload.php';
	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
	use PhpOffice\PhpSpreadsheet\IOFactory;
	require_once '../models/conexionDB.php';

	//--------------------------Construccion del SELECT-----------------------------------------------------/

	$sqlcampos ="SELECT ";
	$sqlwhere="WHERE ";
	$sqlgroupby="GROUP BY ";
	if ($_POST["valor1"]!="" || $_POST["valor2"]!="")
	{
		$sqltablas=" FROM formaspago ";
		$sqltablas.="LEFT JOIN detalle ON (formaspago.factura=detalle.factura AND formaspago.tienda=detalle.localidad AND formaspago.caja=detalle.caja) ";
	}
	else 
		$sqltablas=" FROM detalle ";

	$Letra='A';
	$tab_art=true;

	//------------------------------------------------------------------------
	//----------CONDICIONES WHERE---------------------------------------------

	if (@$_POST["rango_fecha"] || @$_POST["dia"] || @$_POST["mes"] || @$_POST["year"] || @$_POST["tipoc"] || @$_POST["cliente"] || @$_POST["sucursal"] || @$_POST["proveedor"] || @$_POST["departamento"] || @$_POST["grupo"] || @$_POST["subgrupo"] || @$_POST["articulos"] || @$_POST["cantidad1"] || @$_POST["cantidad2"] || @$_POST["valor1"] || @$_POST["valor2"])
	{
		if ($_POST["rango_fecha"]) {
			//Obtener el rango de fecha y darle el formato que esta en la BD*//*
			$fechas = explode(" / ", $_POST['rango_fecha']);
			$fecha1 = $fechas[0];
			//$fecha1 = str_replace('/', '-', $fecha1);
			$fecha1= date('Y-m-d', strtotime($fecha1));
			$fecha2 = $fechas[1];
			//$fecha2 = str_replace('/', '-', $fecha2);
			$fecha2= date('Y-m-d', strtotime($fecha2));                
			$sqlwhere .= "(FECHA>='".$fecha1."' AND FECHA<='".$fecha2."')";
			$sqlwhere .= " AND ";
		}

		if ($_POST["dia"]!="") {
			$Dias = $_POST["dia"];
			$sqlwhere .= "(";
			foreach ($Dias as $Dia) {
				$sqlwhere .= " extract(dow from detalle.fecha)='" . $Dia . "' OR";
			}
			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
		}

		if ($_POST["mes"]!="" && $_POST["year"]!="") {
			$Meses = $_POST["mes"];
			$Years = $_POST["year"];
			$sqlwhere .= "(";
			foreach ($Years as $Year) {
				foreach ($Meses as $Mes){
					$sqlwhere .= " TO_CHAR(detalle.fecha,'YYYY-MM')='" . $Year . "-" . $Mes ."' OR";
				}
			}
			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
		}
		else
		{
			if ($_POST["mes"]!="") {
				$Meses = $_POST["mes"];
				$sqlwhere .= "(";
				foreach ($Meses as $Mes) {
					$sqlwhere .= " TO_CHAR(detalle.fecha,'MM')='" . $Mes ."' OR";
				}
				$sqlwhere .= ")";
				$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
				$sqlwhere .= " AND ";    
			}

			if ($_POST["year"]!="") {
				$Years = $_POST["year"];
				$sqlwhere .= "(";
				foreach ($Years as $Year) {
					$sqlwhere .= " TO_CHAR(detalle.fecha,'YYYY')='" . $Year . "' OR";
				}
				$sqlwhere .= ")";
				$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
				$sqlwhere .= " AND ";   
			}
		}
		if ($_POST["cliente"]!="") { 
			$Clientes = $_POST["cliente"];
			$sqlwhere .= "(";
			foreach ($Clientes as $Cliente) {
				$sqlwhere .= " detalle.cliente='" . $Cliente . "' OR";
				$sqlwhere .= " detalle.cliente=upper('" . $Cliente . "') OR";
			}
			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
		}

		/*if ($_POST["tipoc"]) {

		$TiposC = $_POST["tipoc"];
		$sqlwhere .= "(";
		foreach ($TiposC as $Tipoc) {
		$sqlwhere .= " esclientes.tipo='" . $Tipoc . "' OR";
		}
		$sqlwhere .= ")";
		$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
		$sqlwhere .= " AND ";
		}*/

		if ($_POST["sucursal"]!="")
		{
			$Sucursales = $_POST["sucursal"];
			$sqlwhere .= "(";
			foreach ($Sucursales as $Sucursal) {
				$sqlwhere .= " detalle.localidad='" . $Sucursal . "' OR";
			}
			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
			//$sqltablas.="LEFT JOIN tiendas ON (detalle.tienda=tiendas.id) ";
		}

		if ($_POST["proveedor"]!="")
		{
			$Proveedores = $_POST["proveedor"];
			$sqlwhere .= "(";
			foreach ($Proveedores as $Proveedor) {
				$sqlwhere .= " esarticulosxprov.proveedor='" . $Proveedor . "' OR";
			}
			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
			$sqltablas.="LEFT JOIN esarticulosxprov ON (detalle.material=esarticulosxprov.articulo) ";
		} 

		if ($_POST["departamento"]!="")
		{                
			$Departamentos = $_POST["departamento"];
			$sqlwhere .= "(";
			foreach ($Departamentos as $Departamento) {
				$sqlwhere .= " esarticulos.departamento='" . $Departamento . "' OR";
			}

			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
			if ($tab_art)
			{
				$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
				$tab_art=false;
			}

		}

		if ($_POST["grupo"]!="")
		{                
			$Grupos = $_POST["grupo"];
			$sqlwhere .= "(";
			foreach ($Grupos as $Grupo) {
				$sqlwhere .= " esarticulos.grupo='" . $Grupo . "' OR";
			}

			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
			if ($tab_art)
			{
				$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
				$tab_art=false;
			}
		}

		if ($_POST["subgrupo"]!="")
		{                
			$Subgrupos = $_POST["subgrupo"];
			$sqlwhere .= "(";
			foreach ($Subgrupos as $Subgrupo) {
				$sqlwhere .= " esarticulos.subgrupo='" . $Subgrupo . "' OR";
			}

			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
			if ($tab_art)
			{
				$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
				$tab_art=false;
			}
		}

		if ($_POST["articulos"]!="")
		{ 
			$Articulos = $_POST["articulos"];
			$sqlwhere .= "(";
			foreach ($Articulos as $Articulo) {
				$sqlwhere .= " detalle.material='" . $Articulo . "' OR";
			}
			$sqlwhere .= ")";
			$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
		}


		if ($_POST["cantidad1"]!="")
		{ 
			if ($_POST["cantidad2"]!="") { 
				//Between
				$Cantidad1 = $_POST["cantidad1"];
				$Cantidad2 = $_POST["cantidad2"];
				$sqlwhere .= "(";
				//$sqlwhere .= " detalle.cantidad='" . $Cantidad . "' )";
				$sqlwhere .= " detalle.cantidad BETWEEN '" . $Cantidad1 . "' AND '". $Cantidad2 . "' )";
				//$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
				$sqlwhere .= " AND ";
			}
			else
			{
				//Mayor a Cantidad 1
				$Cantidad1 = $_POST["cantidad1"];
				$sqlwhere .= "(";
				//$sqlwhere .= " detalle.cantidad='" . $Cantidad . "' )";
				$sqlwhere .= " detalle.cantidad >= '" . $Cantidad1 . "' )";
				//$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
				$sqlwhere .= " AND ";
			}
		}
		else
		{
		if ($_POST["cantidad2"]!="") { 
			//Menor a Cantidad2
			$Cantidad2 = $_POST["cantidad2"];
			$sqlwhere .= "(";
			//$sqlwhere .= " detalle.cantidad='" . $Cantidad . "' )";
			$sqlwhere .= " detalle.cantidad <= '" . $Cantidad2 . "' )";
			//$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
			$sqlwhere .= " AND ";
		}
		}

		if ($_POST["valor1"]!="") { 
			if ($_POST["valor2"]!="") { 
				//Between
				$Valor1 = $_POST["valor1"];
				$Valor2 = $_POST["valor2"];
				$sqlwhere .= "(";
				//$sqlwhere .= " detalle.cantidad='" . $Cantidad . "' )";
				$sqlwhere .= " formaspago.monto BETWEEN '" . $Valor1 . "' AND '". $Valor2 . "' )";
				//$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
				$sqlwhere .= " AND ";
			} else {
				//Mayor a Valor1
				$Valor1 = $_POST["valor1"];
				$sqlwhere .= "(";
				$sqlwhere .= " formaspago.monto >= '" . $Valor1 . "' )";
				$sqlwhere .= " AND ";
			}
		} else {
			if ($_POST["valor2"]!="") { 
				//Menor a Valor2
				$Valor2 = $_POST["valor2"];
				$sqlwhere .= "(";
				//$sqlwhere .= " detalle.cantidad='" . $Cantidad . "' )";
				$sqlwhere .= " formaspago.monto <= '" . $Valor2 . "' )";
				//$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
				$sqlwhere .= " AND ";
			}
		}

		if ($_POST["hora1"]!="") { 
			if ($_POST["hora2"]!="") { 
				//Between
				$Hora1 = $_POST["hora1"];
				$Hora2 = $_POST["hora2"];
				$sqlwhere .= "(";
				//$sqlwhere .= " detalle.cantidad='" . $Cantidad . "' )";
				$sqlwhere .= " date_part('hour', detalle.hora) >= '" . $Hora1 . "' OR date_part('hour', detalle.hora) <='". $Hora2 . "' )";
				//$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
				$sqlwhere .= " AND ";
			} else {
				//Mayor a hora1
				$Hora1 = $_POST["hora1"];
				$sqlwhere .= "(";
				$sqlwhere .= " date_part('hour', detalle.hora) >= '" . $Hora1 . "' )";
				$sqlwhere .= " AND ";
			}
		} else {
			if ($_POST["hora2"]!="") { 
				//Menor a hora2
				$Hora2 = $_POST["hora2"];
				$sqlwhere .= "(";
				//$sqlwhere .= " detalle.cantidad='" . $Cantidad . "' )";
				$sqlwhere .= " date_part('hour', detalle.hora) <= '" . $Hora2 . "' )";
				//$sqlwhere = str_replace (" OR)", ")", $sqlwhere);
				$sqlwhere .= " AND ";
			}
		}

		$sqlwhere=chop($sqlwhere,"AND ");

	}


	//-------------------------------------------------------------------------
	// Check para campos a mostrar, agrupados o detallados

	if($_POST["radioSalida"]=='agrupado')
	{
		if($_POST["check_agrup_fecha"]=='on')
		{
			//$camposExcel[$Letra++]="tienda";
			//$camposExcel = array( $Letra++ => array('facturas00' =>'TIENDAS');
			if ($_POST["dia"] || $_POST["mes"] || $_POST["year"])
			{
				if ($_POST["dia"])
				{
					$camposExcel[$Letra++]["dia"] = "Dia"; 

					$sqlcampos.="case extract(dow from detalle.fecha) when 1 then 'Lunes' when 2 then 'Martes' when 3 then 'Miercoles' when 4 then 'Jueves' when 5 then 'Viernes' when 6 then 'Sabado' else 'Domingo' end as dia, ";
					//$sqlcampos.="extract(dow from detalle.fecha) as dia , "; 
					//$sqlcampos.="extract(dow from detalle.fecha) as dia , ";
					$sqlgroupby.="dia, ";
				}
				if ($_POST["mes"] && $_POST["year"])
				{
					$camposExcel[$Letra++]["mes_annio"] = "Año-Mes";   
					$sqlcampos.="TO_CHAR(detalle.fecha,'YYYY-MM') as mes_annio , ";                       
					$sqlgroupby.="mes_annio, ";
				}                        
				else
				{
					if($_POST["mes"])
					{
						$camposExcel[$Letra++]["mes"] = "Mes";   
						$sqlcampos.="TO_CHAR(detalle.fecha,'MM') as mes , ";
						$sqlgroupby.="mes, ";
					}     
					if($_POST["year"])
					{
						$camposExcel[$Letra++]["annio"] = "Año";   
						$sqlcampos.="TO_CHAR(detalle.fecha,'YYYY') as annio , ";
						$sqlgroupby.="annio, ";
					}
				}           
			}
			else
			{
				$camposExcel[$Letra++]["fecha"] = "Fecha";   
				$sqlcampos.="detalle.fecha AS fecha , ";
				$sqlgroupby.="detalle.fecha, ";
			}

		}

		if($_POST["check_agrup_hora"]=='on')
		{
			$camposExcel[$Letra++]["horas"] = "Hora";   
			$sqlcampos.="date_part('hour',detalle.hora) as horas , ";
			$sqlgroupby.="horas, ";
		}

		if($_POST["check_agrup_sucursal"]=='on')
		{
			//$camposExcel[$Letra++]="tienda";
			//$camposExcel = array( $Letra++ => array('facturas00' =>'TIENDAS');
			$camposExcel[$Letra++]["tienda"] = "Tienda";   
			$sqlgroupby.="tiendas.nombre, ";
			$sqlcampos.="tiendas.nombre AS tienda , ";
			$sqltablas.="LEFT JOIN tiendas ON (detalle.localidad=tiendas.id) ";
		}


		if (@$_POST["check_agrup_factura"]=='on')
		{

			if (@$_POST["check_agrup_tipoc"]=='on')
			{
				// $camposExcel[$Letra++]="facturas00";
				//$camposExcel = array( $Letra++ => array('facturas00' =>"Cantidad de Facturas Clientes ACONTADO");
				$camposExcel[$Ledetalletra++]["facturas00"] = "Clientes ACONTADO Cantidad Facturas";  
				$sqlcampos.="count(distinct detalle.factura) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)=0) as facturas00, ";
				//$camposExcel[$Letra++]="facturasm0";
				//$camposExcel = array( $Letra++ => array('facturasm0' =>"Cantidad de Facturas Clientes REGISTRADOS");
					$camposExcel[$Letra++]["facturasm0"] = "Cliente REGISTRADOS Cantidad Facturas";
				$sqlcampos.="count(distinct detalle.factura) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)>0) as facturasM0, ";
			}
			else
			{
				$sqlcampos.="COUNT(DISTINCT CONCAT(detalle.localidad, '-', detalle.factura, '-', detalle.caja)) as Facturas , ";
				//$camposExcel[$Letra++]="facturas";
				//$camposExcel = array( $Letra++ => array('facturas' =>"Cantidad de Facturas");
				$camposExcel[$Letra++]["facturas"] = "Cantidad Facturas";
			}

		}

		if($_POST["check_agrup_cliente"]=='on')
		{
			//$camposExcel[$Letra++]="tienda";
			//$camposExcel = array( $Letra++ => array('facturas00' =>'TIENDAS');
			$camposExcel[$Letra++]["rifc"] = "Cliente";
			$camposExcel[$Letra++]["nombrec"] = "Nombre";
			$camposExcel[$Letra++]["telefonoc"] = "Telefono";
			$camposExcel[$Letra++]["emailc"] = "Email";   
			$sqlcampos.="detalle.cliente AS rifc , esclientes.razon as nombrec, esclientes.telefono as telefonoc, esclientes.email as emailc, ";
			$sqltablas.="LEFT JOIN esclientes ON (detalle.cliente=esclientes.rif) ";
			$sqlgroupby.="rifc, nombrec, telefonoc, emailc, ";
		}

		if($_POST["check_agrup_caja"]=='on')
		{
			//$camposExcel[$Letra++]="tienda";
			//$camposExcel = array( $Letra++ => array('facturas00' =>'TIENDAS');
			$camposExcel[$Letra++]["caja"] = "Caja";   
			$sqlcampos.="detalle.caja AS caja , ";
			$sqlgroupby.="detalle.caja, ";
		}


		if($_POST["check_agrup_material"]=='on')
		{
			//$camposExcel[$Letra++]="tienda";
			//$camposExcel = array( $Letra++ => array('facturas00' =>'TIENDAS');
			$camposExcel[$Letra++]["materialcod"] = "Codigo Material";
			$camposExcel[$Letra++]["materialn"] = "Material";   
			//$sqlcampos.="detalle.material AS materialn , ";
			//$sqlgroupby.="detalle.material, ";
			$sqlcampos.="esarticulos.codigo AS materialcod, esarticulos.descripcion AS materialn , ";
			$sqlgroupby.="esarticulos.codigo, esarticulos.descripcion, ";
			if ($tab_art)
			{
				$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
				$tab_art=false;
			}
		}

		if($_POST["check_agrup_departamento"]=='on')
		{
			//$camposExcel[$Letra++]="tienda";
			//$camposExcel = array( $Letra++ => array('facturas00' =>'TIENDAS');
			$camposExcel[$Letra++]["departamento"] = "Departamento";   
			//$sqlcampos.="esarticulos.departamento AS departamento , ";
			$sqlcampos.="esdpto.descripcion AS departamento , ";
			$sqlgroupby.="esdpto.descripcion, ";
			if ($tab_art)
			{
				$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
				$tab_art=false;
			}
			$sqltablas.="LEFT JOIN esdpto ON (esarticulos.departamento=esdpto.codigo) ";

		}

		if($_POST["check_agrup_grupo"]=='on')
		{
			//$camposExcel[$Letra++]="tienda";
			//$camposExcel = array( $Letra++ => array('facturas00' =>'TIENDAS');
			$camposExcel[$Letra++]["grupo"] = "Grupo";   
			$sqlcampos.="esgrupos.descripcion AS grupo , ";
			$sqlgroupby.="esgrupos.descripcion, ";
			if ($tab_art)
			{
				$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
				$tab_art=false;
			}
			$sqltablas.="LEFT JOIN esgrupos ON (esarticulos.grupo=esgrupos.codigo) ";
		}

		if($_POST["check_agrup_subgrupo"]=='on')
		{
			//$camposExcel[$Letra++]="tienda";
			//$camposExcel = array( $Letra++ => array('facturas00' =>'TIENDAS');
			$camposExcel[$Letra++]["subgrupo"] = "SubGrupo";   
			$sqlcampos.="essubgrupos.descripcion AS subgrupo , ";
			$sqlgroupby.="essubgrupos.descripcion, ";
			if ($tab_art)
			{
				$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
				$tab_art=false;
			}
			$sqltablas.="LEFT JOIN essubgrupos ON (esarticulos.subgrupo=essubgrupos.codigo) ";
		}

		if (@$_POST["check_agrup_cantidad"]=='on')
		{
			if (@$_POST["check_agrup_tipoc"]=='on')
			{
				// $camposExcel[$Letra++]="unidades00";
				//$camposExcel = array( $Letra++ => array('unidades00' =>"Cantidad de Unidades Clientes ACONTADO");
				$camposExcel[$Letra++]["unidades00"] = "Cliente ACONTADO Cantidad Unidades";   
				$sqlcampos.="sum(detalle.cantidad) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)=0) as unidades00, "; 
				//$camposExcel[$Letra++]="unidadesm0";
				//$camposExcel = array( $Letra++ => array('facturasm0' =>"Cantidad de Unidades Clientes REGISTRADOS");
				$camposExcel[$Letra++]["unidadesm0"] = "Clientes REGISTRADOS Cantidad Unidades"; 
				$sqlcampos.="sum(detalle.cantidad) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)>0) as unidadesM0, "; 
			}
			else
			{
				//$camposExcel[$Letra++]="unidades";
				//$camposExcel = array( $Letra++ => array('unidades' =>"Cantidad de Unidades");
				$camposExcel[$Letra++]["unidades"] = "Cantidad Unidades"; 
				$sqlcampos.="SUM(detalle.cantidad) as unidades, ";
			}                   
		}

		if (@$_POST["check_agrup_subtotal"]=='on' || @$_POST["check_agrup_total"]=='on' || @$_POST["check_agrup_impuesto"]=='on')
		{
			if (@$_POST["check_agrup_tipoc"]=='on')
			{
				////Costo
				$camposExcel[$Letra++]["costo00"] = "Clientes ACONTADO Costo"; 
				$sqlcampos.="sum(detalle.costo) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)=0) as costo00, ";
				$camposExcel[$Letra++]["costom0"] = "Clientes REGISTRADOS Costo";
				$sqlcampos.="sum(detalle.costo) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)>0) as costoM0, ";
			}
			else
			{
				$camposExcel[$Letra++]["costo"] = "Costo";   
				$sqlcampos.="SUM(detalle.costo) as costo, ";
			} 

		}

		if (@$_POST["check_agrup_subtotal"]=='on')
		{
			if (@$_POST["check_agrup_tipoc"]=='on')
			{
				//$camposExcel[$Letra++]="subtotal00";
				// $camposExcel = array( $Letra++ => array('subtotal00' =>"Subtotal de Clientes ACONTADO");
				$camposExcel[$Letra++]["subtotal00"] = "Clientes ACONTADO Subtotal"; 
				$sqlcampos.="sum(detalle.subtotal) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)=0) as subtotal00, ";
				//$camposExcel[$Letra++]="subtotalm0";
				//$camposExcel = array( $Letra++ => array('subtotalm0' =>"Subtotal Clientes REGISTRADOS");
				$camposExcel[$Letra++]["subtotalm0"] = "Clientes REGISTRADOS Subtotal";
				$sqlcampos.="sum(detalle.subtotal) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)>0) as subtotalM0, ";
			}
			else
			{
				// $camposExcel[$Letra++]="subtotal";
				//$camposExcel = array( $Letra++ => array('subtotal' =>"Subtotal");
				$camposExcel[$Letra++]["subtotal"] = "Subtotal";   
				$sqlcampos.="SUM(detalle.subtotal) as subtotal, ";
			}    
		}

		if (@$_POST["check_agrup_impuesto"]=='on')
		{
			if (@$_POST["check_agrup_tipoc"]=='on')
			{
				//$camposExcel[$Letra++]="subtotal00";
				// $camposExcel = array( $Letra++ => array('subtotal00' =>"Subtotal de Clientes ACONTADO");
				$camposExcel[$Letra++]["impuesto00"] = "Clientes ACONTADO Impuesto"; 
				$sqlcampos.="sum(detalle.impuesto) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)=0) as impuesto00, ";
				//$camposExcel[$Letra++]="subtotalm0";
				//$camposExcel = array( $Letra++ => array('subtotalm0' =>"Subtotal Clientes REGISTRADOS");
				$camposExcel[$Letra++]["impuestom0"] = "Clientes REGISTRADOS Impuesto";
				$sqlcampos.="sum(detalle.impuesto) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)>0) as impuestoM0, ";
			}
			else
			{
				// $camposExcel[$Letra++]="subtotal";
				//$camposExcel = array( $Letra++ => array('subtotal' =>"Subtotal");
				$camposExcel[$Letra++]["impuesto"] = "Impuesto";   
				$sqlcampos.="SUM(detalle.impuesto) as impuesto, ";
			}    
		}

		if (@$_POST["check_agrup_total"]=='on')
		{
			if (@$_POST["check_agrup_tipoc"]=='on')
			{
				//$camposExcel[$Letra++]="subtotal00";
				// $camposExcel = array( $Letra++ => array('subtotal00' =>"Subtotal de Clientes ACONTADO");
				$camposExcel[$Letra++]["total00"] = "Clientes ACONTADO Total"; 
				$sqlcampos.="sum(detalle.total) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)=0) as total00, ";
				//$camposExcel[$Letra++]="subtotalm0";
				//$camposExcel = array( $Letra++ => array('subtotalm0' =>"Subtotal Clientes REGISTRADOS");
				$camposExcel[$Letra++]["totalm0"] = "Clientes REGISTRADOS Total";
				$sqlcampos.="sum(detalle.total) filter (where cast((regexp_replace(detalle.cliente, '[^0-9]', '','g')) as float)>0) as totalM0, ";
			}
			else
			{
				// $camposExcel[$Letra++]="subtotal";
				//$camposExcel = array( $Letra++ => array('subtotal' =>"Subtotal");
				$camposExcel[$Letra++]["total"] = "Total";   
				$sqlcampos.="SUM(detalle.total) as total, ";
			}    
		}
	}
	else
	{
		if($_POST["radioSalida"]=='detallado')
		{
			if($_POST["check_det_fecha"]=='on')
			{
				if ($_POST["dia"] || $_POST["mes"] || $_POST["year"])
				{
					if ($_POST["dia"])
					{
						$camposExcel[$Letra++]["dia"] = "Dia"; 

						$sqlcampos.="case extract(dow from detalle.fecha) when 1 then 'Lunes' when 2 then 'Martes' when 3 then 'Miercoles' when 4 then 'Jueves' when 5 then 'Viernes' when 6 then 'Sabado' else 'Domingo' end as dia, ";
						//$sqlcampos.="extract(dow from detalle.fecha) as dia , "; 
						//$sqlcampos.="extract(dow from detalle.fecha) as dia , ";
						//$sqlgroupby.="dia, ";
					}
					if ($_POST["mes"] && $_POST["year"])
					{
						$camposExcel[$Letra++]["mes_annio"] = "Año-Mes";   
						$sqlcampos.="TO_CHAR(detalle.fecha,'YYYY-MM') as mes_annio , ";                       
						//$sqlgroupby.="mes_annio, ";
					}                        
					else
					{
						if($_POST["mes"])
						{
							$camposExcel[$Letra++]["mes"] = "Mes";   
							$sqlcampos.="TO_CHAR(detalle.fecha,'MM') as mes , ";
						//$sqlgroupby.="mes, ";
						}     
						if($_POST["year"])
						{
							$camposExcel[$Letra++]["annio"] = "Año";   
							$sqlcampos.="TO_CHAR(detalle.fecha,'YYYY') as annio , ";
						//$sqlgroupby.="annio, ";
						}
					}           
				}
				else
				{
					$camposExcel[$Letra++]["fecha"] = "Fecha";   
					$sqlcampos.="detalle.fecha AS fecha , ";
					// $sqlgroupby.="detalle.fecha, ";
				}

			}

			if($_POST["check_det_hora"]=='on')
			{
				$camposExcel[$Letra++]["horas"] = "Hora";   
				$sqlcampos.="date_part('hour',detalle.hora) as horas , ";
			}

			if($_POST["check_det_sucursal"]=='on')
			{
				$camposExcel[$Letra++]["tienda"] = "Tienda";   
				//$sqlgroupby.="tiendas.nombre, ";
				$sqlcampos.="tiendas.nombre AS tienda , ";
				$sqltablas.="LEFT JOIN tiendas ON (detalle.localidad=tiendas.id) ";
			}


			if (@$_POST["check_det_factura"]=='on')
			{
				$sqlcampos.="detalle.factura as facturas , ";
				$camposExcel[$Letra++]["facturas"] = "Factura";    
			}

			if($_POST["check_det_caja"]=='on')
			{
				$camposExcel[$Letra++]["caja"] = "Caja";   
				$sqlcampos.="detalle.caja AS caja , ";
			}

			if($_POST["check_det_cliente"]=='on')
			{
				$camposExcel[$Letra++]["rifc"] = "Cliente";
				$camposExcel[$Letra++]["nombrec"] = "Nombre";
				$camposExcel[$Letra++]["telefonoc"] = "Telefono";
				$camposExcel[$Letra++]["emailc"] = "Email";   
				$sqlcampos.="detalle.cliente AS rifc , esclientes.razon as nombrec, esclientes.telefono as telefonoc, esclientes.email as emailc, ";
				$sqltablas.="LEFT JOIN esclientes ON (detalle.cliente=esclientes.rif) ";
			}

			if($_POST["check_det_material"]=='on')
			{

				$camposExcel[$Letra++]["materialcod"] = "Codigo Material";
				$camposExcel[$Letra++]["materialn"] = "Material";   
				$sqlcampos.="esarticulos.codigo AS materialcod, esarticulos.descripcion AS materialn , ";
				if ($tab_art)
				{
					$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
					$tab_art=false;
				}
			}

			if($_POST["check_det_departamento"]=='on')
			{
				$camposExcel[$Letra++]["departamento"] = "Departamento";   
				$sqlcampos.="esdpto.descripcion AS departamento , ";
				if ($tab_art)
				{
					$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
					$tab_art=false;
				}
				$sqltablas.="LEFT JOIN esdpto ON (esarticulos.departamento=esdpto.codigo) ";

			}

			if($_POST["check_det_grupo"]=='on')
			{
				$camposExcel[$Letra++]["grupo"] = "Grupo";   
				$sqlcampos.="esgrupos.descripcion AS grupo , ";
				if ($tab_art)
				{
					$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
					$tab_art=false;
				}
				$sqltablas.="LEFT JOIN esgrupos ON (esarticulos.grupo=esgrupos.codigo) ";
			}

			if($_POST["check_det_subgrupo"]=='on')
			{
				$camposExcel[$Letra++]["subgrupo"] = "SubGrupo";   
				$sqlcampos.="essubgrupos.descripcion AS subgrupo , ";
				if ($tab_art)
				{
					$sqltablas.="LEFT JOIN esarticulos ON (detalle.material=esarticulos.codigo) ";
					$tab_art=false;
				}
				$sqltablas.="LEFT JOIN essubgrupos ON (esarticulos.subgrupo=essubgrupos.codigo) ";
			}

			if (@$_POST["check_det_cantidad"]=='on')
			{

				$camposExcel[$Letra++]["unidades"] = "Cantidad Unidades"; 
				$sqlcampos.="detalle.cantidad as unidades, ";                 
			}

			if (@$_POST["check_det_subtotal"]=='on' || @$_POST["check_det_total"]=='on' || @$_POST["check_det_impuesto"]=='on')
			{
				$camposExcel[$Letra++]["costo"] = "Costo";   
				$sqlcampos.="detalle.costo as costo, ";
			}

			if (@$_POST["check_det_subtotal"]=='on')
			{
				$camposExcel[$Letra++]["subtotal"] = "Subtotal";   
				$sqlcampos.="detalle.subtotal as subtotal, ";
			}

			if (@$_POST["check_det_impuesto"]=='on')
			{
				$camposExcel[$Letra++]["impuesto"] = "Impuesto";   
				$sqlcampos.="detalle.impuesto as impuesto, ";
			}

			if (@$_POST["check_det_total"]=='on')
			{
				$camposExcel[$Letra++]["total"] = "Total";   
				$sqlcampos.="detalle.total as total, "; 
			}


		}
	}

	/*$sql.="detalle.fecha AS fecha, tipocliente.descripcion AS tipoc, detalle.cedula AS cc_rif, "; 
	$sql.="esclientes.razon AS nombrecliente, tiendas.nombre AS tienda,  esproveedores.descripcion AS proveedor, "; 
	//$sql.="CASE WHEN esproveedores.descripcion IS NOT NULL THEN esproveedores.descripcion ELSE 'SIN PROVEEDOR' END AS proveedor ";
	$sql.="detalle.material as articulo ";*/

	$sqlcampos=chop($sqlcampos,", ");
	$sqlgroupby=chop($sqlgroupby,", ");

	if ($sqlcampos=='SELECT')
		$sqlcampos.=" * ";
	if ($sqlwhere=="WHERE ")
		$sqlwhere=" ";
	if ($sqlgroupby=='GROUP BY') 
		$sqlgroupby=" ";

	$sql=$sqlcampos.$sqltablas.$sqlwhere.$sqlgroupby;


	//var_dump($sql);
	
	//--------------------------Fin Construccion del SELECT-----------------------------------------------------
	//---------------------------Conectar a la BD y realizar consulta-------------------------------------------
	//---------------------------Conectar a la BD y realizar consulta-------------------------------------------
	$db=Database::Conectar();
	ini_set('memory_limit', '-1');
	ini_set('max_execution_time', 300);

	$sqlresult=$db->query($sql);
	//---------------------------Fin Conectar a la BD y realizar consulta-------------------------------------------

	$documento = new Spreadsheet();
	$documento
		->getProperties()
		->setCreator("Dashboard, Reportes a tu Medida")
		->setLastModifiedBy('Dashboard') // última vez modificado por
		->setTitle('Reportes a tu Medida')
		->setSubject('Reporte')
		->setDescription('Este documento fue generado Garzón Empresas')
		->setKeywords('Reportes a tu Medida de Dashboard Garzón Empresas')
		->setCategory('Cat: Reportes a tu Medida');

	$hoja = $documento->getActiveSheet();
	$hoja->setTitle("Reporte a tu medida");

	//$camposExcel = array( $Letra++ => array('facturas00' =>"Cantidad de Facturas Clientes ACONTADO");

	//$hoja->setCellValue('A1', $sql);
	foreach ($camposExcel as $key => $value) {
		$hoja->setCellValue($key.'1', $value);
	}  
	$fila = 2;
	// $hoja->setCellValue('A1', $sql);

	while($rows = $sqlresult->fetch(PDO::FETCH_ASSOC)){ 
		foreach($camposExcel as $lcolum => $campos) {
			foreach($campos as $ncolum => $valor){
				$valtmp = (substr($rows[$ncolum], 0, 1)=='=') ? substr_replace($rows[$ncolum], ' =', 0, 1) : $rows[$ncolum];
				if(substr($valtmp, 0, 1)=='=') {
					$valtmp = substr_replace($valtmp, '.=', 0, 1);
				}
				$hoja->setCellValue($lcolum.$fila, $valtmp);
				// var_dump($lcolum, $ncolum, $rows[$ncolum] );
			}
		}
		$fila++; //Sumamos 1 para pasar a la siguiente fila
	}

	foreach($camposExcel as $lcolum => $campos){ 
		$hoja->getStyle($lcolum.'2')->getAlignment()->setWrapText(true);  
		$hoja->getColumnDimension($lcolum)->setWidth(25);
		foreach($campos as $ncolum => $valor){
			$hoja->setCellValue($lcolum.'1', $valor);
			// var_dump($lcolum.'1', $valor);
		}
	} 

	$hoja->getRowDimension(1)->setRowHeight(30);

	$nom_file="post_get";
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="' . $nom_file . '"');
	header('Cache-Control: max-age=0');
	$writer = IOFactory::createWriter($documento, 'Xlsx');
	$writer = new Xlsx($documento);
	ob_start();
	$writer->save("php://output");
	$xlsData = ob_get_contents();
	var_dump($xlsData);
	ob_end_clean();
	$response =  array(
		'op' => 'ok',
		'file' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,".base64_encode($xlsData)
	);
	die(json_encode($response));
?>
