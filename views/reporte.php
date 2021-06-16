<form id="form_consulta" method='POST'>
	<div class="container-fluid pt-3">
		<div class="row" >
			<div class="col-md-12">
				<div class="card">
					<div class="card-header">
						<h5 class="card-title text-primary">Fecha o Mes</h5>
					</div>
					<div class="card-body">
						<div class="form-group row">
							<label class="col-sm-2 col-form-label">Rango de Fecha</label>
							<div class="col-sm-6">
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text">
											<i class="fa fa-calendar"></i>
										</span>
									</div>
									<input type="text" class="form-control float-right" id="rango_fecha" name="rango_fecha">
								</div>
								<!-- /.input group -->
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-2 col-form-label">Día</label>
							<div class="col-sm-6">
								<select id="dia" name="dia[]" class="form-control select2"
									multiple="multiple" data-placeholder="Seleccione el(los) dia(s)"
									style="width: 100%;">
									<option value="0">Domingo</option>
									<option value="1">Lunes</option>
									<option value="2">Martes</option>
									<option value="3">Miercoles</option>
									<option value="4">Jueves</option>
									<option value="5">Viernes</option>
									<option value="6">Sabado</option>
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-2 col-form-label">Mes</label>
							<div class="col-sm-6">
								<select id="mes" name="mes[]" class="form-control select2"
									multiple="multiple" data-placeholder="Seleccione el(los) Mes(es)"
									style="width: 100%;">
									<option value="01">Enero</option>
									<option value="02">Febrero</option>
									<option value="03">Marzo</option>
									<option value="04">Abril</option>
									<option value="05">Mayo</option>
									<option value="06">Junio</option>
									<option value="07">Julio</option>
									<option value="08">Agosto</option>
									<option value="09">Septiembre</option>
									<option value="10">Octubre</option>
									<option value="11">Noviembre</option>
									<option value="12">Diciembre</option>
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-2 col-form-label">Año</label>
							<div class="col-sm-6">
								<select id="year" name="year[]" class="form-control select2"
									multiple="multiple" data-placeholder="Seleccione el(los) Año(s)"
									style="width: 100%;">
									<?php  $year = date("Y"); for($i=2019;$i<=$year;$i++) { echo "<option value='".$i."'>".$i."</option>"; } ?>
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-2 col-form-label">Rango de Horas</label>
							<div class="col-sm-3">
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text"><i class="fa fa-clock"></i>&nbsp;Desde:</span>
									</div>
									<input id="hora1" name="hora1" class="form-control"
										type="number" min="0" max="23" placeholder="Hora Desde 0 a 23"
										onblur="if(this.value > $('#hora2').val() && $('#hora2').val() != '') this.value = $('#hora2').val()">
								</div>
								<!-- /.input group -->
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text"><i class="fa fa-clock"></i>&nbsp;Hasta:</span>
									</div>
									<input id="hora2" name="hora2" class="form-control"
										type="number" min="0" max="23" placeholder="Hora Hasta 0 a 23"
										onblur="if(this.value < $('#hora1').val() && $('#hora1').val() != '') this.value = $('#hora1').val()">
								</div>
								<!-- /.input group -->
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-6">
				<div class="card">
					<div class="card-header">
						<h5 class="card-title text-primary">Filtros</h5>
					</div>
					<div class="card-body">
						<div class="form-group row">
							<label class="col-sm-4 col-form-label">Cliente</label>
							<div class="col-sm-8">
								<input type='text' id='cedula' placeholder="Agregar Cedula(s)">
								<input type='button' id='agregar' onclick="add_li()" value='Agregar'>
								<ul  id="listaCedulas" name="listaCedulas"></ul>
								<select id="cliente" name="cliente[]" class="form-control select2-hidden-accessible"
									multiple="multiple" data-placeholder="Agregar el(los) Cliente(s)"
									style="width: 100%;" size='8'>
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-4 col-form-label">Sucursal</label>
							<div class="col-sm-8" id="sucursaldiv">
								<select id="sucursal" name="sucursal[]" class="form-control select2"
									multiple="multiple" data-placeholder="Todas las sucursales"
									style="width: 100%;">
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-4 col-form-label">Proveedor</label>
							<div class="col-sm-8" id="proveedordiv">
								<select id="proveedor" name="proveedor[]" class="form-control select2"
									multiple="multiple" data-placeholder="Todos los Proveedores"
									style="width: 100%;">
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-4 col-form-label">Departamento</label>
							<div class="col-sm-8" id="departamentodiv">
								<select id="departamento" name="departamento[]" class="form-control select2" multiple="multiple" data-placeholder="Todos los Departamentos"
									style="width: 100%;">
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-4 col-form-label">Grupo</label>
							<div class="col-sm-8" id="grupodiv">
								<select id="grupo" name="grupo[]" class="form-control select2"
									multiple="multiple" data-placeholder="Todos los Grupos"
									style="width: 100%;">
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-4 col-form-label">Sub Grupo</label>
							<div class="col-sm-8" id="subgrupodiv">
								<select id="subgrupo" name="subgrupo[]" class="form-control select2"
									multiple="multiple" data-placeholder="Todos los Subgrupos"
									style="width: 100%;">
								</select>
							</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-4 col-form-label">Material</label>
							<div class="col-sm-8">
								<input type='text' id='articulo' placeholder="Agregar Codigo(s) Material(es)">
								<input type='button' id='agregar_articulos' onclick="add_li_art()" value='Agregar'>
								<ul  id="listaArticulos" name="listaArticulos"></ul>
								<select id="articulos" name="articulos[]" class="form-control select2-hidden-accessible"
									multiple="multiple" data-placeholder="Agregar el(los) Material(es)"
									style="width: 100%;" size='8'>
								</select>
							</div>
						</div>
					</div>
				</div>

				<div class="card">
					<fieldset id="cardcant" name="cardcant" class="card-body">
						<div class="card-header">
							<h5 class="card-title text-primary">Cantidad o Valor</h5>
						</div>
						<div class="card-body">
							<div class="form-group row">
								<label class="col-sm-4 col-form-label">Cantidad</label>
								<div class="col-sm-4">
									<input id="cantidad1" name="cantidad1" class="form-control" type="text" placeholder="Mayor o Igual a">
								</div>
								<div class="col-sm-4">
									<input id="cantidad2" name="cantidad2" class="form-control" type="text" placeholder="Menor o Igual a">
								</div>
							</div>
							<div class="form-group row">
								<label class="col-sm-4 col-form-label">Valor</label>
								<div class="col-sm-4">
									<input id="valor1" name="valor1" class="form-control" type="text" placeholder="Mayor o Igual a">
								</div>
								<div class="col-sm-4">
									<input id="valor2" name="valor2" class="form-control" type="text" placeholder="Menor o Igual a">
								</div>
							</div>
						</div>
					</fieldset>
				</div><!-- /.card -->
			</div>
			
			<!-- /.col-md-6 -->
			<div class="col-md-6">
				<div class="card">
					<div class="card-header text-primary">
						<div class="form-check">
							<input class="form-check-input" type="radio" name="radioSalida" id="radioSalidad" value="detallado">
							<label class="form-check-label" for="radioSalidad" style="cursor: pointer;"><h5 class="m-0">Salida del dato detallado</h5></label>
						</div>
					</div>

					<fieldset id="camposdetallado" name="camposdetallado" class="card-body" disabled="disabled">
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input"  id="check_det_sucursal"  name="check_det_sucursal">
									<label class="form-check-label" for="check_det_sucursal">Sucursal</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_cantidad" name="check_det_cantidad">
									<label class="form-check-label" for="check_det_cantidad">Cantidad</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_tipoc" name="check_det_tipoc" disabled="disabled">
									<label class="form-check-label" for="check_det_tipoc">Tipo de Cliente</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_cliente" name="check_det_cliente">
									<label class="form-check-label" for="check_det_cliente">Cliente</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_subtotal" name="check_det_subtotal">
									<label class="form-check-label" for="check_det_subtotal">Sub Total</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_proveedor" name="check_det_proveedor" disabled="disabled">
									<label class="form-check-label" for="check_det_proveedor">Proveedor</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_fecha" name="check_det_fecha">
									<label class="form-check-label" for="check_det_fecha">Fecha</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_impuesto" name="check_det_impuesto">
									<label class="form-check-label" for="check_det_impuesto">Impuesto</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_linea" name="check_det_linea" disabled="disabled">
									<label class="form-check-label" for="check_det_linea">L&iacutenea</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_hora" name="check_det_hora">
									<label class="form-check-label" for="check_det_hora">Hora</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_total" name="check_det_total">
									<label class="form-check-label" for="check_det_total">Total</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_departamento" name="check_det_departamento">
									<label class="form-check-label" for="check_det_departamento">Departamento</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_factura" name="check_det_factura">
									<label class="form-check-label" for="check_det_factura">Factura</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_precior" name="check_det_precior" disabled="disabled">
									<label class="form-check-label" for="check_det_precior">Precio Real</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_grupo" name="check_det_grupo">
									<label class="form-check-label" for="check_det_grupo">Grupo</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_caja" name="check_det_caja">
									<label class="form-check-label" for="check_det_caja">Caja</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_promocion" name="check_det_promocion" disabled="disabled">
									<label class="form-check-label" for="check_det_promocion">Promoci&oacuten</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_subgrupo" name="check_det_subgrupo">
									<label class="form-check-label" for="check_det_subgrupo">Sub Grupo</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_material" name="check_det_material">
									<label class="form-check-label" for="check_det_material">Material</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_formap" name="check_det_formap" disabled="disabled">
									<label class="form-check-label" for="check_det_formap">Forma de Pago</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_det_marca" name="check_det_marca" disabled="disabled">
									<label class="form-check-label" for="check_det_marca">Marca</label>
								</div>
							</div>
						</div>
					</fieldset>
				</div>

				<div class="card">
					<div class="card-header text-primary">
						<div class="form-check">
							<input class="form-check-input" type="radio" name="radioSalida" id="radioSalidaa" value="agrupado">
							<label class="form-check-label" for="radioSalidaa" style="cursor: pointer;"><h5 class="m-0">Salida del dato agrupado</h5></label>
						</div>
					</div>
					<fieldset id="camposagrupado" name="camposagrupado" class="card-body" disabled="disabled">
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_sucursal" name="check_agrup_sucursal">
									<label class="form-check-label" for="check_agrup_sucursal">Sucursal</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_cantidad" name="check_agrup_cantidad">
									<label class="form-check-label" for="check_agrup_cantidad">Cantidad</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_tipoc" name="check_agrup_tipoc">
									<label class="form-check-label" for="check_agrup_tipoc">Tipo de Cliente</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_cliente" name="check_agrup_cliente">
									<label class="form-check-label" for="check_agrup_cliente">Cliente</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_subtotal" name="check_agrup_subtotal">
									<label class="form-check-label" for="check_agrup_subtotal">Sub Total</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_proveedor" name="check_agrup_proveedor" disabled="disabled">
									<label class="form-check-label" for="check_agrup_proveedor">Proveedor</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_fecha" name="check_agrup_fecha">
									<label class="form-check-label" for="check_agrup_fecha">Fecha</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_impuesto" name="check_agrup_impuesto">
									<label class="form-check-label" for="check_agrup_impuesto">Impuesto</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_linea" name="check_agrup_linea"  disabled="disabled">
									<label class="form-check-label" for="check_agrup_linea">L&iacutenea</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_hora" name="check_agrup_hora">
									<label class="form-check-label" for="check_agrup_hora">Hora</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_total" name="check_agrup_total">
									<label class="form-check-label" for="check_agrup_total">Total</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_departamento" name="check_agrup_departamento" >
									<label class="form-check-label" for="check_agrup_departamento">Departamento</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_factura" name="check_agrup_factura">
									<label class="form-check-label" for="check_agrup_factura">Factura</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_precior" name="check_agrup_precior"  disabled="disabled">
									<label class="form-check-label" for="check_agrup_precior">Precio Real</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_grupo" name="check_agrup_grupo">
									<label class="form-check-label" for="check_agrup_grupo">Grupo</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_caja" name="check_agrup_caja">
									<label class="form-check-label" for="check_agrup_caja">Caja</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_promocion" name="check_agrup_promocion"  disabled="disabled">
									<label class="form-check-label" for="check_agrup_promocion">Promoci&oacuten</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_subgrupo" name="check_agrup_subgrupo">
									<label class="form-check-label" for="check_agrup_subgrupo">Sub Grupo</label>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_material" name="check_agrup_material">
									<label class="form-check-label" for="check_agrup_material">Material</label>
								</div>
							</div>
							<div class="col-md-4 col-sm-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_formap" name="check_agrup_formap"  disabled="disabled">
									<label class="form-check-label" for="check_agrup_formap">Forma de Pago</label>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="check_agrup_marca" name="check_agrup_marca"  disabled="disabled">
									<label class="form-check-label" for="check_agrup_marca">Marca</label>
								</div>
							</div>
						</div>
					</fieldset>
				</div>

				<div class="card card-primary card-outline">
					<div class="card-body text-center">
						<a href="#" id="exportar" name="exportar" class="btn btn-primary">Exportar</a>
					</div>
				</div>
			</div>
			<!-- /.col-md-6 -->
		</div>
		<!-- /.row -->
	</div><!-- /.container-fluid -->
</form>
<!-- Scripts para reportes -->
<!-- Select2 -->
<script src="views/plugins/select2/select2.full.min.js"></script>
<script src="views/dist/js/reporte.js"></script>
<script src="views/plugins/daterangepicker/daterangepicker.js"></script>

<script>
	$(function () {
		//Initialize Select2 Elements
		$('.select2').select2()
		//Date range picker

		$('#rango_fecha').daterangepicker({
			"format": 'DD-MM-YYYY',
			"separator": " / ",
			"locale": {
				"applyLabel": "Aplicar",
				"cancelLabel": "Cancelar",
				"fromLabel": "Desde",
				"toLabel": "Hasta",
				"customRangeLabel": "Custom",
				"daysOfWeek": [
					"Do",
					"Lu",
					"Ma",
					"Mi",
					"Ju",
					"Vi",
					"Sa"],
				"monthNames": [
					"Enero",
					"Febrero",
					"Marzo",
					"Abril",
					"Mayo",
					"Junio",
					"Julio",
					"Agosto",
					"Septiembre",
					"Octubre",
					"Noviembre",
					"Diciembre"],
				"firstDay": 1
			}
		})
	})

	function add_li()
	{
		var cedulaLi=document.getElementById("cedula").value;
		if(cedulaLi.length>0)
		{
			if(find_li(cedulaLi))
			{
				var li=document.createElement('li');
				li.id=cedulaLi;
				li.innerHTML=cedulaLi+"<span class='badge badge-primary badge-pill' onclick='eliminar(this)'>X</span>";
				// li.innerHTML=cedulaLi+"<span onclick='eliminar(this)'>X</span>";
				document.getElementById("listaCedulas").appendChild(li);
				//$("#clientes2").val($("#listaCedulas").html());
				$('#cedula').val('');
				//$("#listaCedulas").addClass("list-group-item d-flex justify-content-between align-items-center");
				//$("#listaCedulas").addClass("list-group-item justify-content-between align-items-center");
				$('#cliente').append('<option class="select2-selection__choice" value='+cedulaLi+' selected="selected">'+cedulaLi+'</option>');
			}
		}
		return false;
	}

	/** * Funcion que busca si existe ya el <li> dentrol del <ul> Devuelve true si no existe.*/
	function find_li(contenido)
	{
		var el = document.getElementById("listaCedulas").getElementsByTagName("li");
		for (var i=0; i<el.length; i++)
		{
			if(el[i].innerHTML==contenido)
				return false;
		}
		return true;
	}

	/*** Funcion para eliminar los elementos Tiene que recibir el elemento pulsado */
	function eliminar(elemento)
	{
		var id=elemento.parentNode.getAttribute("id");
		node=document.getElementById(id);
		node.parentNode.removeChild(node);
		//$("#cliente option[value='option1']").remove();
		$("#cliente option[value="+id+"]").remove();
	}

	function add_li_art()
	{
		var articuloLi=document.getElementById("articulo").value;
		if(articuloLi.length>0)
		{
			if(find_li_art(articuloLi))
			{
				var li=document.createElement('li');
				li.id=articuloLi;
				li.innerHTML=articuloLi+"<span class='badge badge-primary badge-pill' onclick='eliminarArt(this)'>X</span>";
			 // li.innerHTML=cedulaLi+"<span onclick='eliminar(this)'>X</span>";
			 document.getElementById("listaArticulos").appendChild(li);
				//$("#clientes2").val($("#listaCedulas").html());
				$('#articulo').val('');
				//$("#listaCedulas").addClass("list-group-item d-flex justify-content-between align-items-center");
				//$("#listaCedulas").addClass("list-group-item justify-content-between align-items-center");
				$('#articulos').append('<option class="select2-selection__choice" value='+articuloLi+' selected="selected">'+articuloLi+'</option>');
			}
		}
		return false;
	}

	function find_li_art(contenido)
	{
		var el = document.getElementById("listaArticulos").getElementsByTagName("li");
		for (var i=0; i<el.length; i++)
		{
			if(el[i].innerHTML==contenido)
				return false;
		}
		return true;
	}

	function eliminarArt(elemento)
	{
		var id=elemento.parentNode.getAttribute("id");
		node=document.getElementById(id);
		node.parentNode.removeChild(node);
		//$("#cliente option[value='option1']").remove();
		$("#articulos option[value="+id+"]").remove();
	}
</script>