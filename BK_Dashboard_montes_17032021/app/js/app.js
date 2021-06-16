/**
 * apps.js
 * funciones especificas para obtener informacion de la BBDD sobre las ventas
 * al final funciones genericas para todos
 */

/**
 * funcion que carga en el div principal el contenido del dashboard
 */
function cargarcontenido(popcion_menu) {
	$('#tiempo').addClass('d-none');
	clearTimeout(temporizador1);
	clearTimeout(tempo_hoy);
	$('.nav-link').removeClass('active menu-open');
	setTimeout('$(document.body).removeClass("sidebar-open")',100);
	setTimeout('$(document.body).addClass("sidebar-collapse")',100);
	$('#' + popcion_menu).addClass('active');
	if (tomar_datos !== '') {
		tomar_datos.abort();
	}
	$('#titulo' ).html('Información de Todas las Tiendas');
	switch (popcion_menu) {
		case 'dashboard':
			$('#tiempo').removeClass('d-none');
			$('#contenido_ppal').load('app/db_general.html?t=' + moment().format("HH:mm:ss"), function () {
				actualizar(ptienda, pnombretienda);
			});
			break;

		case 'reporte':
			$('#titulo' ).html('Reporte a Medida, Seleccione sus opciones');
			$('#btncambiar' ).css("display", "none");
			$('#contenido_ppal').load('index1.php?t=' + moment().format("HH:mm:ss"));
			break;

		default:
			$('#btncambiar' ).css("display", "none");
			$('#contenido_ppal').load('app/db_'+popcion_menu+'.html?t=' + moment().format("HH:mm:ss"));
			break;
	}
}

function cargando(acc){
	if(acc==='show'){
		$('.modal-backdrop').css('zIndex', 9998);
		$('#loading').modal('show');
	} else {
		$('.modal-backdrop').css('zIndex', 8888);
		$('#loading').modal('hide');
	}
}

function cargando2(acc){
	if(acc==='show'){
		$('.modal-backdrop').css('zIndex', 9998);
		$('#loading2').modal('show');
	} else {
		$('.modal-backdrop').css('zIndex', 8888);
		$('#loading2').modal('hide');
	}
}

/**
 * funcion que actualiza los datos de las tablas
 * @param  {int} ptienda id de la tienda si se selecciona desde ventas por tienda
 * @param  {string} pnomtienda nombre de la tienda para actualizar
 */
function actualizar(ptiendaid, pnomtienda) {
	cargando('show')
	if (tomar_datos !== '') {
		tomar_datos.abort();
	}
	$('#tiempo' ).html('Actualizando, Espere.');
	clearTimeout(temporizador1);
	var vhora = $.ajax({
		data: {
			opcion: "hora_srv",
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			var datos = data.split('¬');
			pnombretienda = pnomtienda;
			ptienda = ptiendaid;
			tomar_datos = $.ajax({
				data: {
					opcion: "actualizar_todo",
					idpara: ptienda,
					hora: datos[1]
				},
				type: "POST",
				dataType: "text",
				url: "app/DBProcs.php",
				success: function (data) {
					var data = JSON.parse(data);
					ventasxtienda(data[0]['ventasxtienda']);
					topxclientes(data[1]['topxclientes']);
					topxtipopagos(data[2]['topxtipopagos']);
					topxproductos(data[3]['topxproductos']);
					topxdepartamento(data[4]['topxdepartamento']);
					$('#hora_act').val(datos[1]);
				},
			}).done(function () {
				$('#tiempo' ).removeClass('d-none');
				tiempofalta = temporizadort;
				temporizador1 = setTimeout("tiemporesta()", 1000);
				cambiartitulo(ptienda, pnombretienda);
				cargando('hide')
			});
		}
	});
}

/**
 * temporizador para actualizar los datos
 */
function tiemporesta() {
	tiempofalta = tiempofalta - 1000;
	if (tiempofalta == 0) {
		actualizar(ptienda, pnombretienda);
		tiempofalta = temporizadort;
	} else {
		$('#tiempo' ).html('Se actualizará en ' + (tiempofalta / 1000) + 's.');
		temporizador1 = setTimeout("tiemporesta()", 1000);
	}
}

/**
 * cambiar titulo de los cuadros para colocar el nombre de la tienda
 * @param  {int} ptienda id de la tienda si se selecciona desde ventas por tienda
 * @param  {string} pnomtienda nombre de la tienda para actualizar
 */
function cambiartitulo(ptienda, pnombretienda) {
	$('#titulo' ).html('Información de &nbsp;<span class="badge badge-warning border elevation-2 p-0">&nbsp;' + pnombretienda + '&nbsp;</span>');
	if (ptienda != '*') {
		$('#ttxclientes').html('Top 20 Clientes <span class="badge badge-light">' + pnombretienda + '</span>');
		$('#ttxtipopagos').html('Top Tipos de Pagos <span class="badge badge-light">' + pnombretienda + '</span>');
		$('#ttxproductos').html('Top 10 Productos más Vendidos <span class="badge badge-light">' + pnombretienda + '</span>'); $('#ttxtipopagos').html('Top Tipos de Pagos Usados <span class="badge badge-light">' + pnombretienda + '</span>');
		$('#ttxdepartamento').html('Ventas por Departamentos <span class="badge badge-light">' + pnombretienda + '</span>');
		if (ptodas) $('#btncambiar' ).css("display", "");
	} else {
		if (ptodas) $('#btncambiar' ).css("display", "none");
		$('#ttxclientes').html('Top 20 de Clientes');
		$('#ttxtipopagos').html('Top Tipos de Pagos');
		$('#ttxproductos').html('Top 10 Productos más Vendidos');
		$('#ttxdepartamento').html('Ventas por Departamentos');
	}
}

/**
 * Permite cambiar la clave del usuario
 * @param  {string} claveant  clave actual del usuario
 * @param  {string} clavenva  clave nueva del usuario
 */
function cambiarClave(claveant, clavenva) {
	tomar_datos = $.ajax({
		data: {
			opcion: "cambiarClave",
			idpara: claveant + encrypt('¬') + clavenva
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			var datos = data.split('¬');
			alert(datos[1]);
			if (datos[0] == '1') $('#CambiarClave').modal('hide');
		}
	});
}

/**
 * Permite subir archivo al servidoro
 * @param  {string} nom_archivo  nombre del archivo .csv para subir
 */
function subirArchivo() {
	$('#btnsubir').addClass('d-none');
	$('#btncerrar').addClass('d-none');
	$('#subiendo').removeClass('d-none');
	var paqueteDeDatos = new FormData();
	paqueteDeDatos.append('archivo', $('#nom_archivo')[0].files[0]);
	paqueteDeDatos.append('opcion', 'subirArchivo');
	paqueteDeDatos.append('idpara', $('#farchivos').val() + '¬' + ($('#reemplazar').is(':checked') ? 1 : 0));
	$.ajax({
		url: "app/DBProcs.php",
		type: 'POST',
		contentType: false,
		dataType: "json",
		data: paqueteDeDatos,
		processData: false,
		cache: false, 
		success: function(data){ // En caso de que todo salga bien.
			var datos = data.split('¬');
			if(datos[0]==1) {
				$('#subirArchivo').modal('hide')
				$('#nom_archivo').val('');
			}
			$('#subiendo').addClass('d-none');
			$('#btnsubir').removeClass('d-none');
			$('#btncerrar').removeClass('d-none');
			alert(datos[1]);
		},
		error: function (){ // Si hay algún error.
			$('#subiendo').addClass('d-none');
			$('#btnsubir').removeClass('d-none');
			$('#btncerrar').removeClass('d-none');
			alert("Algo ha fallado. Intente de Nuevo");
		}
	});
}

/**
 * Permite llenar un array con la informacion de la tabla para poder restituir los valores
 * de las filas que se quiten para los cálculos
 * @param  {string} pDataTable nombre del datatable que se quiere hacer el respaldo de los datos
 */
function arrayAuxiliar(pDataTable) {
	var tabla = $('#' + pDataTable).DataTable();
	dtCols = tabla.columns().count();
	dtRows = tabla.rows().count();
	datosOld = [];
	for (var i = 0; i < dtRows; i++) {
		datosOld[i] = [];
		datosOld[i][0] = i;
	}
	for (var i = 0; i < dtRows; i++) {
		tabla.row(i).every(function (rowIdx, tableLoop, rowLoop) {
			for (var j = 1; j < dtCols; j++) {
				var datos = tabla.row(this).cell(rowIdx, j).data();
				datosOld[rowIdx][j] = datos;
			}
		});
	}
};

/**
 * Coloca en 0 los montos en la fila, localidad, seleccionada para restarlos del total
 * @param  {string} pDataTable nombre del datatable para limpiar los datos de la fila
 * @param  {int} rid id de fila seleccionada
 * @param  {bool} marcado 1 indica si se limpian los valores - 0 indica que se restauran los valores
 */
function limpiarfila(pDataTable, rid, marcado) {
	$('#' + pDataTable + ' tbody').on('click', '.dt-check', function () {
		var tabla = $('#' + pDataTable).DataTable();
		tabla.row(rid).every(function (rowIdx, tableLoop, rowLoop) {
			for (var i = 1; i < dtCols; i++) {
				if (marcado) {
					tabla.row(rid).cell(rowIdx, i).data(datosOld[rowIdx][i]);
				} else {
					tabla.row(rid).cell(rowIdx, i).data(0);
				}
			}
		});
		tabla.draw(false);
	});
}

/**
 * define la tabla para reflejar las ventas de todas las tiendas
 * a la fecha y hora actual
 * @param  {json} datos información para mostrar los datos en la tabla
 */
function ventasxtienda(datos) {
	var cant_totalg = datos['cant_totalg'];
	var totalg_bs = datos['totalg_bs'];
	var costog_bs = datos['costog_bs'];
	var datos = datos['data'];
	$('#ventasxtienda').dataTable({
		data: datos,
		order: [2, 'desc'],
		columns: [
			{ data: "tienda", sClass: "align-middle pl-0 pr-0" },
			{ data: "canfac", sClass: "text-right align-middle" },
			{ data: "total", render: $.fn.dataTable.render.number(",", ".", 0), sClass: "text-right align-middle" },
			{ data: null, sClass: "text-right align-middle",
				render:	function ( data, type, row ) {
							return Math.round( (row.total * 100) / totalg_bs ) + '%';
				}
			},
			{ data: "margen", sClass: "text-right align-middle pr-0" },
		],
		footerCallback: function () {
			margen_gen = ((totalg_bs - costog_bs) / totalg_bs * 100).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
			$(this.api().column(0).footer()).html('Total General');
			$(this.api().column(1).footer()).html(cant_totalg.toFixed(0).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
			$(this.api().column(2).footer()).html(parseInt(totalg_bs).toLocaleString('es-ES'));
			$(this.api().column(4).footer()).html(margen_gen + '%');
		}
	});
}

/**
 * define la tabla para reflejar los 10 clientes con mayores compras
 * a la fecha y hora actual
 * @param  {json} datos información para mostrar los datos en la tabla
 */
function topxclientes(datos) {
	var totalg_bs = datos['totalg_bs'];
	var datos = datos['data'];
	$('#topxclientes').dataTable({
		data: datos,
		order: [1, 'desc'],
		columns: [
			{ data: "cliente", sClass: "align-middle" },
			{ data: "total", render: $.fn.dataTable.render.number(",", ".", 0), sClass: "text-right align-middle" },
			{ data: null, sClass: "text-right align-middle",
				render:	function ( data, type, row ) {
					return Math.round( (row.total * 100) / totalg_bs ) + '%';
				}
			},
			{ data: null, sClass: "align-middle",
				render: function( data, type, row ) {
					var porcentaje = ((row.total * 100) / totalg_bs)
					var tdiv = '<div class="progress">' +
									'<div class="progress-bar progress-bar-striped progress-bar-animated"' +
									' role="progressbar" style="width: ' + porcentaje + '%" aria-valuenow="' +
									porcentaje + '" aria-valuemin="0" aria-valuemax="100"></div></div>';
					return tdiv;
			} }
		],
		footerCallback: function (row, data, start, end, display) {
			$(this.api().column(0).footer()).html('Total General');
			$(this.api().column(1).footer()).html(parseInt(totalg_bs).toLocaleString('es-ES'));
		}
	});
}

/**
 * define la tabla para reflejar los tipos de pago mas utilizados
 * a la fecha y hora actual
 * @param  {json} datos información para mostrar los datos en la tabla
 */
function topxtipopagos(datos) {
	var totalg_bs = datos['totalg_bs'];
	var datos = datos['data'];
	$('#topxtipopagos').dataTable({
		data: datos,
		order: [1, 'desc'],
		columns: [
			{ data: "tipodepago", sClass: "align-middle" },
			{ data: "total", render: $.fn.dataTable.render.number(",", ".", 0), sClass: "text-right align-middle" },
			{ data: null, sClass: "text-right align-middle",
				render:	function ( data, type, row ) {
					return Math.round( (row.total * 100) / totalg_bs ) + '%';
				}
			},
			{ data: null, sClass: "align-middle",
				render: function( data, type, row ) {
					var porcentaje = ((row.total * 100) / totalg_bs)
					var tdiv = '<div class="progress">' +
									'<div class="progress-bar progress-bar-striped progress-bar-animated"' +
									' role="progressbar" style="width: ' + porcentaje + '%" aria-valuenow="' +
									porcentaje + '" aria-valuemin="0" aria-valuemax="100"></div></div>';
					return tdiv;
			} }
		],
		footerCallback: function (row, data, start, end, display) {
			$(this.api().column(0).footer()).html('Total General');
			$(this.api().column(1).footer()).html(parseInt(totalg_bs).toLocaleString('es-ES'));
		}
	});
}

/**
 * define la tabla para reflejar los 10 productos mas vendidos
 * a la fecha y hora actual
 * @param  {json} datos información para mostrar los datos en la tabla
 */
function topxproductos(datos) {
	var cant_totalg = datos['cant_totalg'];
	var totalg_bs = datos['totalg_bs'];
	var costog_bs = datos['costog_bs'];
	var datos = datos['data'];
	$('#topxproductos').dataTable({
		data: datos,
		order: [5, 'desc'],
		columns: [
			{ data: "material", sClass: "align-middle" },
			{ data: "cant_total", sClass: "text-right align-middle", render: $.fn.dataTable.render.number(",", ".", 0) },
			{ data: null, sClass: "text-right align-middle",
				render: function ( data, type, row ) {
					return Math.round( (row.cant_total * 100) / cant_totalg )+'%';
				}
			},
			{ data: "total_bs", sClass: "text-right align-middle", render: $.fn.dataTable.render.number(",", ".", 0) },
			{ data: null, sClass: "text-right align-middle",
				render:	function ( data, type, row ) {
					return Math.round( (row.total_bs * 100) / totalg_bs ) + '%';
				}
			},
			{ data: "margen", sClass: "text-right align-middle" },
		],
		footerCallback: function () {
			margen_gen = ((totalg_bs - costog_bs) / totalg_bs * 100).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
			$(this.api().column(0).footer()).html('Total General');
			$(this.api().column(1).footer()).html(parseInt(cant_totalg).toLocaleString('es-ES'));
			$(this.api().column(3).footer()).html(parseInt(totalg_bs).toLocaleString('es-ES'));
			$(this.api().column(5).footer()).html(margen_gen + '%');
		}
	});
}

/**
 * define la tabla para reflejar los 10 departamentos con mas ventas
 * a la fecha y hora actual
 * @param  {json} datos información para mostrar los datos en la tabla
 */
function topxdepartamento(datos) {
	var cant_totalg = datos['cant_totalg'];
	var totalg_bs = datos['totalg_bs'];
	var costog_bs = datos['costog_bs'];
	var datos = datos['data'];
	$('#topxdepartamento').dataTable({
		data: datos,
		order: [5, 'desc'],
		columns: [
			{ data: "descripcion", sClass: "align-middle" },
			{ data: "cant_total", sClass: "text-right align-middle" },
			{ data: "cantidad", sClass: "text-right align-middle",
				render: function ( data, type, row ) {
					return Math.round( (row.cantidad * 100) / cant_totalg ) + '%';
				}
			},
			{ data: "total_bs", render: $.fn.dataTable.render.number(",", ".", 0), sClass: "text-right align-middle" },
			{ data: null, sClass: "text-right align-middle",
				render:	function ( data, type, row ) {
					return Math.round( (row.total_bs * 100) / totalg_bs ) + '%';
				}
			},
			{ data: "margen", sClass: "text-right align-middle" },
		],
		footerCallback: function (row, data, start, end, display) {
			margen_gen = ((totalg_bs - costog_bs) / totalg_bs * 100).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
			$(this.api().column(0).footer()).html('Total General');
			$(this.api().column(1).footer()).html(cant_totalg.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
			$(this.api().column(3).footer()).html(parseInt(totalg_bs).toLocaleString('es-ES'));
			$(this.api().column(5).footer()).html(margen_gen + '%');
		}
	});
}

/**
 * Se prepara la lista de las facturas para el cliente seleccionado
 * @param { int } ptienda id de la tienda seleccionada
 * @param { string } idcliente id del cliente seleccionado
 */
function listaFacturasxCliente(ptienda, idcliente) {
	cargando('show')
	clearTimeout(temporizador1);
	tomar_datos = $.ajax({
		data: {
			opcion: "listaFacturasxCliente",
			fecha: moment().format('YYYY-MM-DD'),
			hora: moment().format("HH:mm:ss"),
			idpara: ptienda + '¬' + idcliente
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			var datos = data;
			$('#tituloModal').html('Facturas del Cliente ' + datos[0].razon);
			var contenido = '';
			contenido += '<table id="facturas" cellpadding="0" cellspacing="0" class="table table-striped table-hover p-0 m-0 w-100">' +
				'<thead class="bg-dark-gradient">' +
				'<tr>' +
				'<th>Tienda</th>' +
				'<th>Factura</th>' +
				'<th>Caja</th>' +
				'<th>Fecha</th>' +
				'<th>Hora</th>' +
				'<th>Monto</th>' +
				'<th>Margen%</th>' +
				'</tr>' +
				'</thead>' +
				'<tbody>';
			for (i = 0; i < datos.length; i++) {
				contenido +=
					'<tr>' +
						'<td>' + datos[i].tienda + '</td>' +
						'<td>' + datos[i].factura + '</td>' +
						'<td>' + datos[i].caja + '</td>' +
						'<td>' + datos[i].fecha + '</td>' +
						'<td>' + datos[i].hora + '</td>' +
						'<td>' + datos[i].monto + '</td>' +
						'<td>' + datos[i].margen + '</td>' +
					'</tr>';
			}
			contenido += '</tbody></table>'
			contenido += '<script>' +
				'$("#facturas").dataTable({' +
				'scrollY: "300px", ' +
				'order: [5, "desc"],' +
				'columnDefs: [ { ' +
				'targets: [ 5, 6], ' +
				'render: $.fn.dataTable.render.number(",", ".", 2), ' +
				'sClass: "text-right align-middle" ' +
				'} ]' +
				'});' +
				'</script>';
			$('#contenidoModal').html(contenido);
			$('#ModalDatos').modal('show');
			setTimeout("var table = $('#facturas').DataTable(); $('#contenidoModal').css( 'display', 'block' ); table.columns.adjust().draw(); cargando('hide')", 150)
		}
	});
}

/**
 * Se obtienen los datos de la factura enviada por parámetros
 * @param  {int} ptienda id de la tienda a consultar la factura
 * @param  {int} pcaja id de la caja que realizo la venta
 * @param  {string} idcliente id del cliente que realizo la compra
 * @param  {string} pfactura numero de la factura a consultar
 * @param  {string} pfecha fecha de la factura
 */
function datosFactura(ptienda, pcaja, idcliente, pfactura, pfecha) {
	cargando('show')
	tomar_datos = $.ajax({
		data: {
			opcion: "datosFactura",
			fecha: pfecha,
			hora: moment().format("HH:mm:ss"),
			idpara: ptienda + '¬' + pcaja + '¬' + idcliente + '¬' + pfactura
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide')
			var datos = data;
			var tot_cantidad = 0;
			var tot_impuesto = 0;
			var tot_total = 0;
			var tot_subtotal = 0;
			var tot_costo = 0;
			var tot_margen = 0;
			var contenido =
				'<div class="row">' +
					'<table id="cabeceraFactura" cellpadding="0" cellspacing="0"' +
						'class="w-100 ml-1 mr-1">' +
						'<tr style="vertical-align: middle">' +
							'<th width="20%" class="alert-primary"><i class="fas fa-map-marker-alt"></i> Sucursal</th>' +
							'<td width="30%" class="alert-secondary">' + datos[0].nombre + '</td>' +
							'<th rowspan="3" width="15%" class="alert-primary"><i class="fas fa-map-marked-alt"></i> Dirección</th>' +
							'<td rowspan="3" width="35%" class="alert-secondary">' + datos[0].direccion + '</td>' +
						'</tr>' +
						'<tr>' +
							'<th class="alert-primary"><i class="fas fa-file-invoice"></i> Factura #</th>' +
							'<td class="alert-secondary">' + datos[0].factura + '</td>' +
						'</tr>' +
						'<tr style="vertical-align: middle">' +
							'<th class="alert-primary"><i class="fas fa-calendar-alt"></i> Fecha</th>' +
							'<td class="alert-secondary">' + datos[0].fecha + ' (' + datos[0].hora + ')</td>' +
						'</tr>' +
						'<tr style="vertical-align: middle">' +
							'<th class="alert-primary"><i class="fas fa-user"></i> Nombre Cliente</th>' +
							'<td class="alert-secondary">' + datos[0].razon + '</td>' +
							'<th class="alert-primary"><i class="fas fa-phone"></i> Teléfono</th>' +
							'<td class="alert-secondary">' + datos[0].telefono + '</td>' +
						'</tr>' +
						'<tr style="vertical-align: middle">' +
							'<th class="alert-primary"><i class="fas fa-id-badge"></i> Identificación</th>' +
							'<td class="alert-secondary">' + datos[0].cliente + '</td>' +
							'<th class="alert-primary"><i class="fas fa-at"></i> e-mail</th>' +
							'<td class="alert-secondary">' + datos[0].email + '</td>' +
						'</tr>' +
					'</table>' +
				'</div>' +
				'<div class="row">' +
				'<table id="detalleFactura" cellpadding="0" cellspacing="0"' +
				'class="table table-striped table-hover w-100">' +
				'<thead class="bg-primary-gradient">' +
				'<tr style="vertical-align: middle">' +
				'<th width="40%">Articulo</th>' +
				'<th width="15%">Cantidad</th>' +
				'<th width="15%">Precio</th>' +
				'<th width="15%">Impuesto</th>' +
				'<th width="15%">Total</th>' +
				'</tr>' +
				'</thead>' +
				'<tbody>';
			for (i = 0; i < datos.length; i++) {
				tot_cantidad += parseFloat(datos[i].cantidad);
				tot_impuesto += parseFloat(datos[i].impuesto);
				tot_total += parseFloat(datos[i].total);
				tot_subtotal += parseFloat(datos[i].subtotal);
				tot_costo += parseFloat(datos[i].costo);
				contenido +=
					'<tr style="vertical-align: middle; line-height: 1">' +
					'<td width="40%" style="vertical-align: middle">' +
					datos[i].descripcion +
					'</td>' +
					'<td width="15%" style="text-align: right; vertical-align: middle">' +
					parseFloat(datos[i].cantidad).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') +
					'</td>' +
					'<td width="15%" style="text-align: right; vertical-align: middle">' +
					parseFloat(datos[i].precio).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') +
					'</td>' +
					'<td width="15%" style="text-align: right; vertical-align: middle">' +
					parseFloat(datos[i].impuesto).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') +
					'</td>' +
					'<td width="15%" style="text-align: right; vertical-align: middle">' +
					parseFloat(datos[i].total).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') +
					'</td>' +
					'</tr>';
			}
			tot_margen = ((tot_subtotal - tot_costo) / tot_subtotal * 100).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
			contenido += '</tbody>' +
				'<tfoot>' +
				'<tr class="bg-primary-gradient" >' +
				'<th class="text-left">TOTAL GENERAL &nbsp;&nbsp;&nbsp;&nbsp; <span class="badge badge-danger">' +
				'(margen: ' + tot_margen + '%</span> </th>' +
				'<th class="text-right">' + tot_cantidad.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right"></th>' +
				'<th class="text-right">' + tot_impuesto.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right">' + tot_total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'</tr>' +
				'</tfoot>' +
				'</table>' +
				'</div>' +
				'<script>' +
				'$("#detalleFactura").dataTable({ scrollY: "200px" });' +
				'</script>';
			$('#contenidoModalFactura').html(contenido);
			$('.modal-backdrop').css('zIndex', 9890);
			$('#ModalDatosFactura').modal('show');
			setTimeout("var table = $('#detalleFactura').DataTable(); $('#contenidoModalFactura').css( 'display', 'block' ); table.columns.adjust().draw();", 150)
		}
	});
}

/**
 *  Se prepara la lista de los materiales facturados por departamento
 * @param {int} ptienda id de la tienda a consultar
 * @param {int} iddpto id del departamento a consultar
 */
function listaMaterialxDpto(ptienda, iddpto) {
	cargando('show')
	clearTimeout(temporizador1);
	tomar_datos = $.ajax({
		data: {
			opcion: "listaMaterialxDpto",
			idpara: ptienda + '¬' + iddpto,
			hora: $('#hora_act').val(),
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			var tcanfact = 0;
			var tsubtotal = 0;
			var tcosto = 0;
			var datos = data;
			$('#tituloModal').html('Articulos Facturados del Departamento ' + datos[0].dpto);
			var contenido = '';
			if (pnombretienda != '') contenido = '<div class="mbadge elevation-2 border border-white">Tienda ' + pnombretienda + '</div>';
			contenido += '<table id="articulos" cellpadding="0" cellspacing="0" class="table table-striped table-hover p-0 m-0 w-100">' +
				'<thead class="bg-dark-gradient">' +
				'<tr>' +
				'<th>Artículo</th>' +
				'<th>Cantidad</th>' +
				'<th>Subtotal</th>' +
				'<th>Margen%</th>' +
				'</tr>' +
				'</thead>' +
				'<tbody>';
			for (i = 0; i < datos.length; i++) {
				contenido += '<tr>' +
					'<td>' + datos[i].articulo + '</td>' +
					'<td>' + datos[i].cantidad + '</td>' +
					'<td>' + datos[i].subtotal + '</td>' +
					'<td>' + datos[i].margen + '</td>' +
					'</tr>';
				tcanfact += parseFloat(datos[i].cantidad);
				tsubtotal += parseFloat(datos[i].subtotal);
				tcosto += parseFloat(datos[i].costo);
			}
			contenido += '</tbody><tfoot>' +
				'<tr class="bg-dark-gradient" >' +
				'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tcanfact.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tsubtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0 pr-2">' + (((tsubtotal - tcosto) * 100) / tsubtotal).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'</tr>' +
				'</tfoot></table>';
			contenido +=
				'<script>' +
					'$("#articulos").dataTable({' +
						'scrollY: "300px", ' +
						'order: [1, "desc"],' +
						'columnDefs: [ {' +
							'targets: [ 1, 2, 3 ],' +
							'render: $.fn.dataTable.render.number(",", ".", 2),' +
							'sClass: "text-right align-middle"' +
						'} ],' +
					'});' +
				'</script>';
			$('#contenidoModal').html(contenido);
			$('#ModalDatos').modal('show');
			setTimeout("var table = $('#articulos').DataTable(); $('#contenidoModal').css( 'display', 'block' ); table.columns.adjust().draw(); cargando('hide')", 150)
		}
	});
}

/**
 * lista los articulos vendidos por tienda
 * @param {int} pcodigo id del codigo del departamento
 * @param {string} pfecha fecha a consultar
 * @param {string} phora hora a consultar
 * @param {int} pdpto id del departamento a consultar
 * @param {int} ptienda id de la tienda a consultar
 */
function datosVtasDpto(pcodigo, pfecha, phora, pdpto, ptienda) {
	cargando('show')
	clearTimeout(temporizador1);
	tomar_datos = $.ajax({
		data: {
			opcion: "datosVtasDpto",
			fecha: pfecha,
			hora: phora,
			idpara: pcodigo + '¬' + pfecha + '¬' + phora + '¬' + pdpto + '¬' + ptienda
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide')
			var datos = data;
			$('#tituloModal2').html('Ventas del Artículo por Tienda');
			$('#subtitulo').html(datos[0].articulo);
			temporizadorp = 0;
			var contenido = '';
			contenido += '<table id="articulosxtienda" cellpadding="0" cellspacing="0" class="table table-striped table-hover p-0 m-0 w-100">' +
				'<thead class="bg-dark-gradient">' +
				'<tr>' +
				'<th>Tienda</th>' +
				'<th>Cantidad</th>' +
				'<th>Margen%</th>' +
				'</tr>' +
				'</thead>' +
				'<tbody>';
			for (i = 0; i < datos.length; i++) {
				contenido += '<tr>' +
					'<td>' + datos[i].tienda + '</td>' +
					'<td>' + datos[i].cantidad + '</td>' +
					'<td>' + datos[i].margen + '</td>' +
					'</tr>';
			}
			contenido += '</tbody></table>'
			contenido += '<script>' +
				'$("#articulosxtienda").dataTable({' +
				'order: [1, "desc"],' +
				'columnDefs: [ { ' +
				'targets: [ 1, 2], ' +
				'render: $.fn.dataTable.render.number(",", ".", 2), ' +
				'sClass: "text-right align-middle" ' +
				'} ]' +
				'});' +
				'</script>';
			$('#contenidoModal2').html(contenido);
			$('.modal-backdrop').css('zIndex', 9889);
			$('#ModalDatos2').modal('show');
			setTimeout("var table = $('#articulosxtienda').DataTable(); $('#contenidoModal2').css( 'display', 'block' ); table.columns.adjust().draw();", 150)
		}
	});
}

/**
 * permite visualizar el margen de ganancia que produce un departamento en las tiendas
 * @param {int} pcodigo id del departamento
 * @param {string} pfecha fecha a consultar
 * @param {string} phora hora de consulta
 * @param {int} ptienda id de la tienda a consultar
 */
function margenDepartamentos(pcodigo, pfecha, phora, ptienda) {
	cargando('show')
	clearTimeout(temporizador1);
	tomar_datos = $.ajax({
		data: {
			opcion: "margenDepartamentos",
			fecha: pfecha,
			hora: phora,
			idpara: pcodigo + '¬' + ptienda
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide')
			var tcanfact = 0;
			var tsubtotal = 0;
			var tcosto = 0;
			var datos = data;
			clearTimeout(temporizador1);
			temporizadorp = 1;
			$('#tituloModal').html('Margen del Departamento');
			var contenido = '<div class="mbadge elevation-2 border border-white">' + datos[0].dpto + '</div>';
			contenido += '<table id="margenDptosTienda" cellpadding="0" cellspacing="0" ' +
				'class="table table-striped table-hover p-0 m-0 w-100">' +
				'<thead class="bg-dark-gradient">' +
				'<tr>' +
				'<th>Tienda</th>' +
				'<th>#.Fact.</th>' +
				'<th>Subtotal</th>' +
				'<th>Costo</th>' +
				'<th>Margen</th>' +
				'</tr>' +
				'</thead>' +
				'<tbody>';
			for (i = 0; i < datos.length; i++) {
				contenido += '<tr>' +
					'<td>' + datos[i].tienda + '</td>' +
					'<td>' + datos[i].canfact + '</td>' +
					'<td>' + datos[i].subtotal + '</td>' +
					'<td>' + datos[i].costo + '</td>' +
					'<td>' + datos[i].margen + '%</td>' +
					'</tr>';
				tcanfact += parseFloat(datos[i].canfact);
				tsubtotal += parseFloat(datos[i].subtotal);
				tcosto += parseFloat(datos[i].costo);
			}
			contenido += '</tbody><tfoot>' +
				'<tr class="bg-dark-gradient" >' +
				'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tcanfact.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tsubtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tcosto.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0 pr-2">' + (((tsubtotal - tcosto) * 100) / tsubtotal).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'</tr>' +
				'</tfoot></table>';
			contenido += '<script>' +
				'$("#margenDptosTienda").dataTable({ ' +
				"scrollY: '50vh', " +
				'scrollCollapse: true, ' +
				'order: [2, "desc"], ' +
				'columnDefs: [ { ' +
				'targets: [ 1, 2, 3, 4], ' +
				'render: $.fn.dataTable.render.number(",", ".", 2), ' +
				'sClass: "text-right align-middle" ' +
				'} ]' +
				'});' +
				'</script>';
			$('#contenidoModal').html(contenido);
			$('#ModalDatos').modal('show');
			setTimeout("var table = $('#margenDptosTienda').DataTable(); $('#contenidoModal').css( 'display', 'block' ); table.columns.adjust().draw();", 150)
		}
	});
}

/**
 * lista el material vendido diario por departamento
 * @param {int} ptienda id de la tienda a consultar
 * @param {int} iddpto id del departamento a consultar
 * @param {string} pnomtienda nombre de la tienda que se esta consultando
 * @param {string} pfecha fecha a consultar
 * @param {string} phora hora a consultar
 * @param {array} prfechas arreglo de fechas en proceso de consulta
 */
function materialxDptoEV(ptienda, iddpto, pnomtienda, pfecha, phora, prfechas) {
	cargando('show')
	tomar_datos = $.ajax({
		data: {
			opcion: "materialxDptoEV",
			fecha: pfecha,
			hora: phora,
			idpara: ptienda + '¬' + iddpto + '¬' + prfechas
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide')
			var datos = data;
			var dpto = '<span class="mbadge elevation-2 border border-white">' + capitalize(datos[0].dpto) + '</span>';
			dpto += ' de la Tienda ' + '<span class="mbadge elevation-2 border border-white">' + capitalize(pnomtienda) + '</span>';
			$('#bgtitulo').html('Articulos Facturados del Departamento ' + dpto);
			var cabecera = '';
			var contenido = '';
			var carticulo = [];
			var ccantidad = [[], [], [], []];
			var csubtotal = [[], [], [], []];
			var cmargenfc = [[], [], [], []];
			var ccostosfc = [[], [], [], []];
			var tcantidad = [];
			var tsubtotal = [];
			var tcostosfc = [];
			var j = 0;
			var fcol = prfechas.split(',');
			fcol.sort();
			for (i = 0; i < datos.length; i++) {
				if (carticulo.indexOf(datos[i].articulo) < 0) {
					carticulo.push(datos[i].articulo)
				}
			}
			for (k = 0; k < 4; k++) {
				tcantidad[k] = parseFloat(0);
				tsubtotal[k] = parseFloat(0);
				tcostosfc[k] = parseFloat(0);
			}
			for (i = 0; i < carticulo.length; i++) {
				for (k = 0; k < 4; k++) {
					ccantidad[k][i] = '';
					csubtotal[k][i] = '';
					cmargenfc[k][i] = '';
					ccostosfc[k][i] = '';
				}
			}
			for (i = 0; i < datos.length; i++) {
				k = fcol.indexOf(datos[i].fecha);
				j = carticulo.indexOf(datos[i].articulo)
				ccantidad[k][j] = datos[i].cantidad;
				csubtotal[k][j] = datos[i].subtotal;
				cmargenfc[k][j] = datos[i].margen;
				ccostosfc[k][j] = datos[i].costo
				tcantidad[k] += parseFloat(datos[i].cantidad);
				tsubtotal[k] += parseFloat(datos[i].subtotal);
				tcostosfc[k] += parseFloat(datos[i].costo);
			}
			for (i = 0; i < carticulo.length; i++) {
				contenido +=
					'<tr>' +
					'<td class="border border-top-0 border-bottom-0">' + carticulo[i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + ccantidad[0][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + csubtotal[0][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + cmargenfc[0][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + ccantidad[1][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + csubtotal[1][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + cmargenfc[1][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + ccantidad[2][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + csubtotal[2][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + cmargenfc[2][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + ccantidad[3][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + csubtotal[3][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + cmargenfc[3][i] + '</td>' +
					'</tr>';
			}
			contenido += '</tbody>'
			cabecera +=
				'<table id="artvtasdpto" cellpadding="0" cellspacing="0" class="table table-striped table-hover w-100">' +
				'<thead class="bg-dark-gradient">' +
				'<tr>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0"></th>' +
				'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + moment(fcol[0]).format('dddd DD-MM-YYYY') + '</th>' +
				'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + moment(fcol[1]).format('dddd DD-MM-YYYY') + '</th>' +
				'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + moment(fcol[2]).format('dddd DD-MM-YYYY') + '</th>' +
				'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + moment(fcol[3]).format('dddd DD-MM-YYYY') + '</th>' +
				'</tr>' +
				'<tr>' +
				'<th width="28%" class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Artículo</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
				'</tr>' +
				'</thead>';
			totales = '<tfoot>' +
				'<tr class="bg-dark-gradient" >' +
				'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[0].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[0].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[0] == 0) ? '0.00%' : (((tsubtotal[0] - tcostosfc[0]) * 100) / tsubtotal[0]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[1].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[1].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[1] == 0) ? '0.00%' : (((tsubtotal[1] - tcostosfc[1]) * 100) / tsubtotal[1]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[2].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[2].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[2] == 0) ? '0.00%' : (((tsubtotal[2] - tcostosfc[2]) * 100) / tsubtotal[2]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[3].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[3].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[3] == 0) ? '0.00%' : (((tsubtotal[3] - tcostosfc[3]) * 100) / tsubtotal[3]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
				'</tr>' +
				'</tfoot></table>';
			mostrarModal(cabecera + contenido + totales);
			$("#artvtasdpto").dataTable({
				sScrollX: "100%",
				scrollX: true,
				columnDefs: [{
					targets: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
					render: $.fn.dataTable.render.number(",", ".", 2),
					sClass: "text-right align-middle"
				}]
			});
			fAjustarAltoTA('artvtasdpto')
		}
	});
}

/**
 * lista el material vendido semanal por departamento
 * @param {int} ptienda id de la tienda a consultar
 * @param {int} iddpto id del departzamento a consultar
 * @param {string} pnomtienda no,bre de la tienda en consulta
 * @param {string} pfecha fecha a consultar
 * @param {string} phora hora a consultar
 */
function materialxDptoESV(ptienda, iddpto, pnomtienda, pfecha, phora) {
	cargando('show')
	var semanas = [];
	semanas[0] = moment(pfecha).subtract(21, 'd').format("YYYY-MM-DD");
	semanas[1] = moment(pfecha).subtract(14, 'd').format("YYYY-MM-DD");
	semanas[2] = moment(pfecha).subtract(7, 'd').format("YYYY-MM-DD");
	semanas[3] = moment(pfecha).format("YYYY-MM-DD");
	var fcol = [];
	fcol[0] = moment(semanas[0]).week().toString();
	fcol[1] = moment(semanas[1]).week().toString();
	fcol[2] = moment(semanas[2]).week().toString();
	fcol[3] = moment(semanas[3]).week().toString();
	tomar_datos = $.ajax({
		data: {
			opcion: "materialxDptoESV",
			hora: phora,
			idpara: ptienda + '¬' + iddpto + '¬' + semanas[0] + '¬' + moment(semanas[3]).endOf('W').format('YYYY-MM-DD'),
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide')
			var datos = data;
			var dpto = '<span class="mbadge elevation-2 border border-white">' + capitalize(datos[0].dpto) + '</span>';
			dpto += ' de la Tienda ' + '<span class="mbadge elevation-2 border border-white">' + capitalize(pnomtienda) + '</span>';
			$('#bgtitulo').html('Articulos Facturados del Departamento ' + dpto);
			var cabecera = '';
			var contenido = '';
			var carticulo = [];
			var ccantidad = [[], [], [], []];
			var csubtotal = [[], [], [], []];
			var cmargenfc = [[], [], [], []];
			var ccostosfc = [[], [], [], []];
			var tcantidad = [];
			var tsubtotal = [];
			var tcostosfc = [];
			var j = 0;
			for (i = 0; i < datos.length; i++) {
				if (carticulo.indexOf(datos[i].articulo) < 0) {
					carticulo.push(datos[i].articulo);
				}
			}
			for (k = 0; k < 4; k++) {
				tcantidad[k] = parseFloat(0);
				tsubtotal[k] = parseFloat(0);
				tcostosfc[k] = parseFloat(0);
			}
			for (i = 0; i < carticulo.length; i++) {
				for (k = 0; k < 4; k++) {
					ccantidad[k][i] = '';
					csubtotal[k][i] = '';
					cmargenfc[k][i] = '';
					ccostosfc[k][i] = '';
				}
			}
			for (i = 0; i < datos.length; i++) {
				k = fcol.indexOf(datos[i].semana);
				j = carticulo.indexOf(datos[i].articulo)
				ccantidad[k][j] = datos[i].cantidad;
				csubtotal[k][j] = datos[i].subtotal;
				cmargenfc[k][j] = datos[i].margen;
				ccostosfc[k][j] = datos[i].costo
				tcantidad[k] += parseFloat(datos[i].cantidad);
				tsubtotal[k] += parseFloat(datos[i].subtotal);
				tcostosfc[k] += parseFloat(datos[i].costo);
			}
			contenido += '<tbody>'
			for (i = 0; i < carticulo.length; i++) {
				contenido +=
					'<tr>' +
					'<td class="border border-top-0 border-bottom-0">' + carticulo[i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + ccantidad[0][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + csubtotal[0][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + cmargenfc[0][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + ccantidad[1][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + csubtotal[1][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + cmargenfc[1][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + ccantidad[2][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + csubtotal[2][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + cmargenfc[2][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + ccantidad[3][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + csubtotal[3][i] + '</td>' +
					'<td class="border border-top-0 border-bottom-0">' + cmargenfc[3][i] + '</td>' +
					'</tr>';
			}
			contenido += '</tbody>'
			cabecera +=
				'<table id="artvtasdpto2" cellpadding="0" cellspacing="0" class="table table-striped table-hover w-100">' +
				'<thead class="bg-dark-gradient">' +
				'<tr>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0"></th>' +
				'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + moment(semanas[0]).format("DD-MMM") +
				' al ' + moment(semanas[0]).add(6, 'days').format("DD-MMM-YYYY") + '</th>' +
				'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + moment(semanas[1]).format("DD-MMM") +
				' al ' + moment(semanas[1]).add(6, 'days').format("DD-MMM-YYYY") + '</th>' +
				'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + moment(semanas[2]).format("DD-MMM") +
				' al ' + moment(semanas[2]).add(6, 'days').format("DD-MMM-YYYY") + '</th>' +
				'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + moment(semanas[3]).format("DD-MMM") +
				' al ' + moment(semanas[3]).add(6, 'days').format("DD-MMM-YYYY") + '</th>' +
				'</tr>' +
				'<tr>' +
				'<th width="28%" class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Artículo</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
				'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
				'</tr>' +
				'</thead>';
			totales = '<tfoot>' +
				'<tr class="bg-dark-gradient" >' +
				'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[0].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[0].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[0] == 0) ? '0.00%' : (((tsubtotal[0] - tcostosfc[0]) * 100) / tsubtotal[0]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[1].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[1].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[1] == 0) ? '0.00%' : (((tsubtotal[1] - tcostosfc[1]) * 100) / tsubtotal[1]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[2].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[2].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[2] == 0) ? '0.00%' : (((tsubtotal[2] - tcostosfc[2]) * 100) / tsubtotal[2]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[3].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[3].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[3] == 0) ? '0.00%' : (((tsubtotal[3] - tcostosfc[3]) * 100) / tsubtotal[3]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
				'</tr>' +
				'</tfoot></table>';
			mostrarModal(cabecera + contenido + totales);
			$("#artvtasdpto2").dataTable({
				sScrollX: "100%",
				scrollX: true,
				columnDefs: [{
					targets: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
					render: $.fn.dataTable.render.number(",", ".", 2),
					sClass: "text-right align-middle"
				}]
			});
			fAjustarAltoTA('artvtasdpto2')
		}
	});
}

/**
 * lista el material vendido mensual por departamento
 * @param {int} ptienda id de la tienda a consultar
 * @param {int} iddpto id del departamento a consultar
 * @param {string} pnomtienda nombre de la tienda en consulta
 * @param {string} pfecha fecha a consultar
 * @param {string} phora hora a consultar
 * @param {int} pdiaini dia inicial para filtar por unos dias especificos
 * @param {int} pdiafin dia final para filtrar por unos dias especificos
 */
function materialxDptoEMV(ptienda, iddpto, pnomtienda, pfecha, phora, pdiaini, pdiafin) {
	cargando('show')
	var ffecha = pfecha.split('-');
	var ffecha = new Date(ffecha[0], ffecha[1] - 1, ffecha[2]);
	var afechas0 = moment(ffecha).subtract(3, 'M').startOf('M').format('YYYY-MM-DD');
	var afechas1 = moment(ffecha).endOf('M').format('YYYY-MM-DD');
	var fcol = [];
	for (i = 3; i >= 0; i--) {
		fcol[3 - i] = moment(ffecha).subtract(i, 'M').startOf('M').format('YYYY-MM');
	}
	tomar_datos = $.ajax({
		data: {
			opcion: "materialxDptoEMV",
			hora: phora,
			idpara: ptienda + '¬' + iddpto + '¬' + afechas0 + '¬' + afechas1 + '¬' + pdiaini + '¬' + pdiafin
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide')
			var datos = data;
			var dpto = '<span class="mbadge elevation-2 border border-white">' + datos[0].dpto + '</span>';
			dpto += ' de la Tienda ' + '<span class="mbadge elevation-2 border border-white">' + pnomtienda + '</span>';
			$('#bgtitulo').html('Articulos Facturados del Departamento ' + dpto);
			var cabecera = '';
			var contenido = '';
			var carticulo = [];
			var ccantidad = [[], [], [], []];
			var csubtotal = [[], [], [], []];
			var cmargenfc = [[], [], [], []];
			var ccostosfc = [[], [], [], []];
			var tcantidad = [];
			var tsubtotal = [];
			var tcostosfc = [];
			var j = 0;
			for (i = 0; i < datos.length; i++) {
				if (carticulo.indexOf(datos[i].articulo) < 0) {
					carticulo.push(datos[i].articulo);
				}
			}
			for (k = 0; k < 4; k++) {
				tcantidad[k] = parseFloat(0);
				tsubtotal[k] = parseFloat(0);
				tcostosfc[k] = parseFloat(0);
			}
			for (i = 0; i < carticulo.length; i++) {
				for (k = 0; k < 4; k++) {
					ccantidad[k][i] = '';
					csubtotal[k][i] = '';
					cmargenfc[k][i] = '';
					ccostosfc[k][i] = '';
				}
			}
			for (i = 0; i < datos.length; i++) {
				k = fcol.indexOf(datos[i].mes);
				j = carticulo.indexOf(datos[i].articulo)
				ccantidad[k][j] = datos[i].cantidad;
				csubtotal[k][j] = datos[i].subtotal;
				cmargenfc[k][j] = datos[i].margen;
				ccostosfc[k][j] = datos[i].costo
				tcantidad[k] += parseFloat(datos[i].cantidad);
				tsubtotal[k] += parseFloat(datos[i].subtotal);
				tcostosfc[k] += parseFloat(datos[i].costo);
			}
			contenido += '<tbody>'
			for (i = 0; i < carticulo.length; i++) {
				contenido +=
					'<tr>' +
						'<td class="border border-top-0 border-bottom-0">' + carticulo[i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + ccantidad[0][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + csubtotal[0][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + cmargenfc[0][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + ccantidad[1][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + csubtotal[1][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + cmargenfc[1][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + ccantidad[2][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + csubtotal[2][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + cmargenfc[2][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + ccantidad[3][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + csubtotal[3][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + cmargenfc[3][i] + '</td>' +
					'</tr>';
			}
			contenido += '</tbody>'
			cabecera +=
				'<table id="artvtasdpto3" cellpadding="0" cellspacing="0" class="table table-striped table-hover w-100">' +
					'<thead class="bg-dark-gradient">' +
						'<tr>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0"></th>' +
							'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + capitalize(moment(fcol[0]).format('MMMM-YYYY')) + '</th>' +
							'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + capitalize(moment(fcol[1]).format('MMMM-YYYY')) + '</th>' +
							'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + capitalize(moment(fcol[2]).format('MMMM-YYYY')) + '</th>' +
							'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + capitalize(moment(fcol[3]).format('MMMM-YYYY')) + '</th>' +
						'</tr>' +
						'<tr>' +
							'<th width="28%" class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Artículo</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
						'</tr>' +
					'</thead>';
			totales='<tfoot>' +
						'<tr class="bg-dark-gradient" >' +
							'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[0].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[0].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[0] == 0) ? '0.00%' : (((tsubtotal[0] - tcostosfc[0]) * 100) / tsubtotal[0]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[1].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[1].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[1] == 0) ? '0.00%' : (((tsubtotal[1] - tcostosfc[1]) * 100) / tsubtotal[1]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[2].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[2].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[2] == 0) ? '0.00%' : (((tsubtotal[2] - tcostosfc[2]) * 100) / tsubtotal[2]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[3].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[3].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[3] == 0) ? '0.00%' : (((tsubtotal[3] - tcostosfc[3]) * 100) / tsubtotal[3]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
						'</tr>' +
					'</tfoot>' + 
				'</table>';
			mostrarModal(cabecera + contenido + totales);
			$("#artvtasdpto3").dataTable({
				sScrollX: "100%",
				scrollX: true,
				columnDefs: [{
					targets: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
					render: $.fn.dataTable.render.number(",", ".", 2),
					sClass: "text-right align-middle"
				}]
			});
			fAjustarAltoTA('artvtasdpto3')
		}
	});
}

/**
 * lsta los materiales vendidos diarios por departamento, tienda, material y promocion
 * @param {int} ptienda id de la tienda a consultar
 * @param {int} iddpto id del departamento a consultar
 * @param {string} pnomtienda nombre de la tienda a consultar
 * @param {string} pfecha fecha a consultar
 * @param {string} phora hora a consultar
 * @param {array} prfechas arreglo de fechas para consultar
 */
function materialxDptoESVPromo(ptienda, iddpto, pnomtienda, phora, prfechas, pmat, ppromo) {
	cargando('show')
	var fcol = prfechas.split(',');
	tomar_datos = $.ajax({
		data: {
			opcion: "materialxDptoESVPromo",
			hora: phora,
			idpara: ptienda + '¬' + iddpto + '¬' + prfechas + '¬' + pmat + '¬' + ppromo
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide')
			var datos = data;
			var dpto = '<span class="mbadge elevation-2 border border-white">' + capitalize(datos[0].dpto) + '</span>';
			dpto += ' de la Tienda ' + '<span class="mbadge elevation-2 border border-white">' + capitalize(pnomtienda) + '</span>';
			$('#bgtitulo').html('Articulos Facturados del Departamento ' + dpto);
			var cabecera = '';
			var contenido = '';
			var carticulo = [];
			var ccantidad = [[], [], []];
			var csubtotal = [[], [], []];
			var cmargenfc = [[], [], []];
			var ccostosfc = [[], [], []];
			var tcantidad = [];
			var tsubtotal = [];
			var tcostosfc = [];
			var j = 0;
			for (i = 0; i < datos.length; i++) {
				if (carticulo.indexOf(datos[i].articulo) < 0) {
					carticulo.push(datos[i].articulo);
				}
			}
			for (k = 0; k < 3; k++) {
				tcantidad[k] = parseFloat(0);
				tsubtotal[k] = parseFloat(0);
				tcostosfc[k] = parseFloat(0);
			}
			for (i = 0; i < carticulo.length; i++) {
				for (k = 0; k < 3; k++) {
					ccantidad[k][i] = '';
					csubtotal[k][i] = '';
					cmargenfc[k][i] = '';
					ccostosfc[k][i] = '';
				}
			}
			for (i = 0; i < datos.length; i++) {
				if( datos[i].fecha >= convert_fecha(fcol[0]) && datos[i].fecha <= convert_fecha(fcol[1]) ) { k = 0 };
				if( datos[i].fecha >= convert_fecha(fcol[2]) && datos[i].fecha <= convert_fecha(fcol[3]) ) { k = 1 };
				if( datos[i].fecha >= convert_fecha(fcol[4]) && datos[i].fecha <= convert_fecha(fcol[5]) ) { k = 2 };
				j = carticulo.indexOf(datos[i].articulo)
				ccantidad[k][j] = datos[i].cantidad;
				csubtotal[k][j] = datos[i].subtotal;
				cmargenfc[k][j] = datos[i].margen;
				ccostosfc[k][j] = datos[i].costo
				tcantidad[k] += parseFloat(datos[i].cantidad);
				tsubtotal[k] += parseFloat(datos[i].subtotal);
				tcostosfc[k] += parseFloat(datos[i].costo);
			}
			contenido += '<tbody>'
			for (i = 0; i < carticulo.length; i++) {
				contenido +=
					'<tr>' +
						'<td class="border border-top-0 border-bottom-0">' + carticulo[i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + ccantidad[0][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + csubtotal[0][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + cmargenfc[0][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + ccantidad[1][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + csubtotal[1][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + cmargenfc[1][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + ccantidad[2][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + csubtotal[2][i] + '</td>' +
						'<td class="border border-top-0 border-bottom-0">' + cmargenfc[2][i] + '</td>' +
					'</tr>';
			}
			contenido += '</tbody>'
			cabecera +=
				'<table id="artvtasdpto4" cellpadding="0" cellspacing="0" class="table table-striped table-hover w-100">' +
					'<thead class="bg-dark-gradient">' +
						'<tr>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0"></th>' +
							'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + fcol[0] + 
							' al ' + fcol[1] + '</th>' +
							'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + fcol[2] + 
							' al ' + fcol[3] + '</th>' +
							'<th colspan="3" class="text-center border border-top-0 m-0 p-0">' + fcol[4] + 
							' al ' + fcol[5] + '</th>' +
						'</tr>' +
						'<tr>' +
							'<th width="28%" class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Artículo</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Cant</th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Subt<sup><span class="badge badge-danger">x1000</span></sup></th>' +
							'<th class="text-center border border-top-0 ml-0 mr-0 pl-0 pr-0">Marg%</th>' +
						'</tr>' +
					'</thead>';
			totales='<tfoot>' +
						'<tr class="bg-dark-gradient" >' +
							'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[0].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[0].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[0] == 0) ? '0.00%' : (((tsubtotal[0] - tcostosfc[0]) * 100) / tsubtotal[0]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[1].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[1].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[1] == 0) ? '0.00%' : (((tsubtotal[1] - tcostosfc[1]) * 100) / tsubtotal[1]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tcantidad[2].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + tsubtotal[2].toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
							'<th class="text-right border border-bottom-0 mt-0 mb-0 pt-0 pb-0">' + ((tsubtotal[2] == 0) ? '0.00%' : (((tsubtotal[2] - tcostosfc[2]) * 100) / tsubtotal[2]).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')) + '</th>' +
						'</tr>' +
					'</tfoot>' +
				'</table>';
			mostrarModal(cabecera + contenido + totales);
			$("#artvtasdpto4").dataTable({
				sScrollX: "100%",
				scrollX: true,				
				columnDefs: [{
					targets: [1, 2, 3, 4, 5, 6, 7, 8, 9],
					render: $.fn.dataTable.render.number(",", ".", 2),
					sClass: "text-right align-middle",
				}]
			});
			fAjustarAltoTA('artvtasdpto4')
		}
	});
}

/**
 *  Se prepara la lista de los materiales facturados por departamento
 * @param {int} ptienda id de la tienda a consultar
 * @param {int} iddpto id del departamento a consultar
 */
function verDetallevtaArt(ptienda, idart) {
	cargando('show')
	clearTimeout(temporizador1);
	tomar_datos = $.ajax({
		data: {
			opcion: "verDetallevtaArt",
			idpara: ptienda + '¬' + idart,
			hora: $('#hora_act').val(),
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide');
			var tcantidad = 0;
			var tsubtotal = 0;
			var timpuesto = 0;
			var tcosto = 0;
			var totalg = 0;
			var datos = data;
			$('#bgtitulo').html('Ventas del Artículo ' + datos[0].articulo + '(' + idart + ')');
			var contenido = '';
			contenido += '<div id="subartvtasdet" class="mbadge elevation-2 border border-white">Departamento ' + datos[0].dpto + '</div>';
			contenido += '<table id="artvtasdet" cellpadding="0" cellspacing="0" class="table table-striped table-hover p-0 m-0 w-100">' +
				'<thead class="bg-dark-gradient">' +
				'<tr>' +
				'<th>Tienda</th>' +
				'<th>Cantidad</th>' +
				'<th>Costo Promedio</th>' +
				'<th>Costo Venta</th>' +
				'<th>Precio Unidad</th>' +
				'<th>Subtotal</th>' +
				'<th>Impuesto</th>' +
				'<th>Total</th>' +				
				'<th>Margen%</th>' +
				'</tr>' +
				'</thead>' +
				'<tbody>';
			for (i = 0; i < datos.length; i++) {
				contenido += '<tr>' +
					'<td>' + datos[i].tienda + '</td>' +
					'<td>' + datos[i].cantidad + '</td>' +
					'<td>' + datos[i].costo + '</td>' +
					'<td>' + (datos[i].costo*datos[i].cantidad) + '</td>' +
					'<td>' + datos[i].precio + '</td>' +
					'<td>' + datos[i].subtotal + '</td>' +
					'<td>' + datos[i].impuesto + '</td>' +
					'<td>' + parseFloat(datos[i].subtotal+datos[i].impuesto) + '</td>' +
					'<td>' + datos[i].margen + '</td>' +
					'</tr>';
				tcantidad += parseFloat(datos[i].cantidad);
				tsubtotal += parseFloat(datos[i].subtotal);
				timpuesto += parseFloat(datos[i].impuesto);
				tcosto    += parseFloat(datos[i].costo*datos[i].cantidad);
				totalg    += parseFloat(datos[i].subtotal+datos[i].impuesto*1);
			}
			contenido += '</tbody><tfoot>' +
				'<tr class="bg-dark-gradient" >' +
				'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tcantidad.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tcosto.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tsubtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + timpuesto.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + totalg.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0 pr-2">' + (((tsubtotal - tcosto) * 100) / tsubtotal).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'</tr>' +
				'</tfoot></table>';
			mostrarModal(contenido);
			$("#artvtasdet").dataTable({
				sScrollX: "100%",
				scrollX: true,				
				columnDefs: [{
					targets: [1, 2, 3, 4, 5, 6, 7, 8],
					render: $.fn.dataTable.render.number(",", ".", 2),
					sClass: "text-right align-middle",
				}],
			});
			fAjustarAltoTA('artvtasdet');
		}
	});
}

/**
 * lista las facturas generadas por tienda
 * @param {int} ptienda id de la tienda a consultar
 * @param {string} pfecha fecha a consultar
 * @param {string} phora hora a consultar
 * @param {string} pidart articulo que se esta consultando
 * @param {string} pnombretienda nombre de la tienda para consultar el detalle
 */
function detVtasArtxTienda(ptienda, pfecha, phora, pidart, pnombretienda) {
	cargando('show')
	tomar_datos = $.ajax({
		data: {
			opcion: "detVtasArtxTienda",
			idpara: ptienda + '¬' + pfecha + '¬' + phora + '¬' + pidart + '¬' + pnombretienda,
		},
		type: "POST",
		dataType: "json",
		url: "app/DBProcs.php",
		success: function (data) {
			cargando('hide')
			var tcanfact = 0;
			var tsubtotal = 0;
			var tcosto = 0;
			var ttotal = 0;
			clearTimeout(temporizador1);
			temporizadorp = 0;
			$('#tituloModal3').html('Facturas del ' + moment(pfecha).format('DD-MM-YYYY') + ' de ' + data[0].tienda);
			$('#subtitulo3').html( data[0].articulo );
			var contenido = '';
			contenido += '<table id="tdetVtasArtxTienda" cellpadding="0" cellspacing="0" ' +
				'class="table table-striped table-hover p-0 m-0 w-100">' +
				'<thead class="bg-dark-gradient">' +
				'<tr>' +
				'<th>Hora</th>' +
				'<th>Factura</th>' +
				'<th>Caja</th>' +
				'<th>Cliente</th>' +
				'<th>Nombre</th>' +
				'<th>Subtotal</th>' +
				'<th>Total</th>' +
				'<th>Margen</th>' +
				'</tr>' +
				'</thead>' +
				'<tbody>';
			for (i = 0; i < data.length; i++) {
				contenido += '<tr class="txtcomp">' +
					'<td>' + data[i].hora + '</td>' +
					'<td>' + data[i].factura + '</td>' +
					'<td>' + data[i].caja + '</td>' +
					'<td>' + data[i].cliente + '</td>' +
					'<td>' + data[i].razon + '</td>' +
					'<td>' + data[i].subtotal + '</td>' +
					'<td>' + data[i].total + '</td>' +
					'<td>' + data[i].margen + '</td>' +
					'</tr>';
				tcanfact += 1;
				tsubtotal += parseFloat(data[i].subtotal);
				tcosto += parseFloat(data[i].costo);
				ttotal += parseFloat(data[i].total);
			}
			contenido += '</tbody><tfoot>' +
				'<tr class="bg-dark-gradient" >' +
				'<th colspan="3" class="text-center mt-0 mb-0 pt-0 pb-0">Cant. de Facturas: [ ' + tcanfact.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' ]</th>' +
				'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-left mt-0 mb-0 pt-0 pb-0"></th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + tsubtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0">' + ttotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'<th class="text-right mt-0 mb-0 pt-0 pb-0 pr-2">' + (((tsubtotal - tcosto) * 100) / tsubtotal).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</th>' +
				'</tr>' +
				'</tfoot></table>';
			contenido += '<script>' +
				'$("#tdetVtasArtxTienda").dataTable({ ' +
				'scrollY: "300px", ' +
				'columnDefs: [ { ' +
				'targets: [ 5, 6, 7], ' +
				'render: $.fn.dataTable.render.number(",", ".", 2), ' +
				'sClass: "text-right align-middle" ' +
				'} ]' +
				'});' +
				'</' + 'script>';
			$('#contenidoModal3').html(contenido);
			$('#ModalDatos3').modal('show');
			setTimeout("var table = $('#tdetVtasArtxTienda').DataTable(); $('#contenidoModal3').css( 'display', 'block' ); table.columns.adjust().draw();", 150)
		}
	});
}

/**
 * Ajusta el alto de la tabla de de articulos facturados por dpto de estadisticas ventas
 * * se ejecuta despues de cargar los datos en la tabla para hacer los ajustes dinámicos
 * @param  {int} phscr numero de pixel para el alto de la tabla
 */
function fAjustarAltoTA(idtabla) {
	if(idtabla!='') {
		tblfAjustarAltoTA = idtabla;
		var altotabla = ( $('#bgmodal').height() + $('#header-modal').height() ) - $('#'+tblfAjustarAltoTA).parent().offset().top;
		$('#'+idtabla).dataTable({
			scrollY: altotabla + 'px',
			columnDefs: [ { targets: 0, width: "20%" }, ],
			autoWidth: false
		});
	}
}

/**
 * Convierte la cadena en tipo titulo
 * @param  {string} str cadena a convertir cadena de ejemplo
 * @return {string} cadena convertida      Cadena De Ejemplo
 */
function capitalize(str) {
	if (typeof str !== 'string') return ''
	return str.toLowerCase().charAt(0).toUpperCase() + str.slice(1)
	// return str.toLowerCase().replace(/\b(\w)/g, s => s.toUpperCase());
}

/**
 * cambia la hora a formato 12h am pm
 * @param  {time} time 	hora a cambiar el formato
 * @return {string}   	hora ya con el formato de 12h am pm
 */
function to12hour(time) {
	var b = time.split(':');
	return ((b[0] % 12) || 12) + ':' + b[1] + ':' + b[2] + (b[0] > 12 ? ' pm' : ' am');
}

/**
 * funcion solonumeros para limitar los inpuntbox a permitir solo numeros
 */
function soloNumeros(evt) {
	var e = evt || window.event;
	var key = e.keyCode || e.which;
	if (e.char == "'" || e.key == "'" ||
		e.char == "#" || e.key == "#" ||
		e.char == "$" || e.key == "$" ||
		e.char == "%" || e.key == "%" ||
		e.char == "&" || e.key == "&" ||
		e.char == "(" || e.key == "(" ||
		e.char == "." || e.key == "." ||
		e.char == "," || e.key == "," ||
		e.char == ">" || e.key == ">" ||
		e.char == ">" || e.key == ">" ||
		e.char == ":" || e.key == ":")
		key = 0
	if (!e.shiftKey && !e.altKey && !e.ctrlKey &&
		// numbers   
		key >= 48 && key <= 57 ||
		// numbers pad
		key >= 96 && key <= 105 ||
		// Home and End
		key == 110 || key == 190 ||
		key == 35 || key == 36 ||
		// Backspace and Tab and Enter
		key == 8 || key == 9 || key == 13 ||
		// left and right arrows
		key == 37 || key == 39 ||
		// up and down arrows
		key == 38 || key == 40 ||
		// Del and Ins
		key == 46 || key == 116) {
		// input is VALID
	} else {
		// input is INVALID
		e.returnValue = false;
		if (e.preventDefault) e.preventDefault();
	}
};

function convert_fecha(tfecha) {
	tfecha = tfecha.split('-');
	tfecha = new Date(tfecha[2], tfecha[1] - 1, tfecha[0]);
	tfecha = moment(tfecha).format('YYYY-MM-DD')
	return tfecha
}

function dividirValor(valor, i, d=2){
	var numericSymbols = ['k', 'M', 'B', 'T', 'C', 'Q'];
	var ret;
	if(valor >=1000) {
		while (i-- && ret === undefined) {
			multi = Math.pow(1000, i + 1);
			if (valor >= multi && numericSymbols[i] !== null) {
				ret = (valor / multi).toFixed(d).replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' ' + numericSymbols[i];
			}
		}
	}	
	return (ret ? ret : valor);
}