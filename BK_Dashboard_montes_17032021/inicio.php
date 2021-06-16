<?php
	session_start();
	$params = parse_ini_file('dist/config.ini');
	if ($params === false) {
		$titulo = '';
	}
	$titulo = $params['title'];
	if (!isset($_SESSION['usuario']) || $_SESSION['usuario']==='') {
		header("Location: /");
	} else {
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<title><?php echo $titulo; ?></title>
		<!-- Tell the browser to be responsive to screen width -->
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- Icon Favicon -->
		<link rel="shortcut icon" href="dist/img/favicon.png">

		<!-- Font Awesome -->
		<link rel="stylesheet" href="plugins/fontawesome/css/all.css">

		<!-- Estilos para el reporte -->
		<!-- daterange picker -->
		<link rel="stylesheet" href="views/plugins/daterangepicker/daterangepicker-bs3.css">
		<!-- Select2 -->
		<link rel="stylesheet" href="views/plugins/select2/select2.min.css">

		<!-- bootstrap-select -->
		<link rel="stylesheet" href="plugins/bootstrap-select/css/bootstrap-select.css">
		<!-- Datepicker Bootstrap -->
		<link rel="stylesheet" href="plugins/bootstrap-datepicker/css/bootstrap-datepicker3.standalone.min.css" class="rel">

		<!-- DataTables -->
		<link rel="stylesheet" href="plugins/datatables/buttons.dataTables.min.css">
		<link rel="stylesheet" href="plugins/datatables/dataTables.bootstrap4.css">
		<link rel="stylesheet" href="plugins/datatables/jquery.dataTables.min.css">
		<link rel="stylesheet" href="plugins/datatables/responsive.dataTables.min.css">

		<!-- Theme style -->
		<link rel="stylesheet" href="dist/css/adminlte.css">

		<style>
			table.dataTable tbody td, thead th, tfoot th {
				font-weight: lighter !important;
			}
			table.dataTable tbody td {
				height: 30px;
				padding: 2px;
			}
			table.dataTable tfoot th, table.dataTable thead th {
				padding: 0px 2px 0px 2px;
				height: 30px;
				vertical-align: middle;
				text-align: center;
			}
			table.dataTable thead td {
				padding: 0px;
				margin: 0px;
				height: 18px;
				vertical-align: middle;
				text-align: center;
				line-height: 0px;
				font-weight: lighter !important;
			}
			table.table-striped tbody tr {
				background-color: #D5D9E2;
			}
			table.table-hover tbody tr:hover {
				background-color: #BFC7D4;
			}
			input[type=number]::-webkit-inner-spin-button,
			input[type=number]::-webkit-outer-spin-button {
				-webkit-appearance: none;
				margin: 0;
			}
			input[type=number] { -moz-appearance:textfield; }
			input { text-transform: uppercase; }

			.bootstrap-select a.dropdown-item:not(.active){
				color: #000000 !important;
			}
			.bootstrap-select a.dropdown-item:not(.active):hover{
				background-color: #007bff;
				color: #FFFFFF !important;
			}
			.mbadge {
				color:#1f2d3d;
				background-color:#ffc107;
				text-align: center;
				padding:.25em .4em;
				font-size:75%;
				font-weight:700;
				line-height: 12px;
				border-radius: .25rem;
				margin-left: 4px;
				margin-right: 4px;
				margin-top: 4px;
			}
			.loader {
				background-image:linear-gradient(#06C90F 0%, #D5D800 100%);
				width:50px;
				height:50px;
				border-radius: 50%;
				margin: 0px;
				padding: 0px;
				-webkit-animation: spin 1s linear infinite;
				animation: spin 1s linear infinite;
				opacity: 1;
				filter:alpha(opacity=100);
			}
			.scroller::-webkit-scrollbar {
				width: 8px;
			}
			.scroller::-webkit-scrollbar-track {
				background: white;
			}
			.scroller::-webkit-scrollbar-thumb {
				background: #7f7f7f;
				border-right: 1px solid white;
			}
			.txtcomp {
				letter-spacing: -0.8px;
				line-height: 1em;
			}
			/* Safari */
			@-webkit-keyframes spin {
				0% { -webkit-transform: rotate(0deg); }
				100% { -webkit-transform: rotate(360deg); }
			}
			@keyframes spin {
				0% { transform: rotate(0deg); }
				100% { transform: rotate(360deg); }
			}
			.bgtransparent{
				position:fixed;
				left:0;
				top:0;
				background-color:#000;
				opacity:0.6;
				filter:alpha(opacity=60);
				z-index: 8886;
				display: none;
				width: 1px;
				height: 1px;
			}
			.bgmodal{
				position:fixed;
				font-family:arial;
				font-size:1em;
				border:0.05em solid black;
				overflow:auto;
				background-color:#FFFFFF;
			}
			.nav-treeview {
				border-radius : 0px 0px 7px 7px;
				background-color: #000 !important;
				border: 1px solid #FFFFFF !important;
				border-top: 0px !important;
				margin: 0px;
			}
			.nav-treeview > .nav-item:hover {
				background-color: #426F80 !important;
			}
			::-webkit-input-placeholder { /* Chrome/Opera/Safari */
				font-style: italic;
				font-size: 80%;
			}
			::-moz-placeholder { /* Firefox 19+ */
				font-style: italic;
				font-size: 80%;
			}
			:-ms-input-placeholder { /* IE 10+ */
				font-style: italic;
				font-size: 80%;
			}
			:-moz-placeholder { /* Firefox 18- */
				font-style: italic;
				font-size: 80%;
			}

			@media (min-width: 5px) and (max-width: 319px) {
				.mopcion {display: block;}
				.titulo {font-size: 90%;}
				.mbadge {font-size: 50%;}
				.imgmain {display: none;}
				.imgmain2 {display: '';}
				.content {margin-top: 55px;}
			}
			@media (min-width: 320px) and (max-width: 424px) {
				.mopcion {display: block;}
				.titulo {font-size: 100%;}
				.mbadge {font-size: 60%;}
				.imgmain {display: none;}
				.imgmain2 {display: '';}
				.content {margin-top: 62px;}
			}
			@media (min-width: 425px) and (max-width: 767px) {
				.mopcion {display: block;}
				.titulo {font-size: 110%;}
				.mbadge {font-size: 60%;}
				.imgmain {display: none;}
				.imgmain2 {display: '';}
				.content {margin-top: 68px;}
			}
			@media (min-width: 425px) and (max-width: 767px) and (orientation:landscape) {
				.mopcion {display: block;}
				.titulo {font-size: 110%;}
				.mbadge {font-size: 60%;}
				.imgmain {display: none;}
				.imgmain2 {display: '';}
				.content {margin-top: 58px;}
			}
			@media (aspect-ratio: 1/1) {
				.content {margin-top: 58px;}
			}
			@media (min-width: 768px) and (max-width: 991px) {
				.mopcion {display: block;}
				.titulo {font-size: 135%;}
				.mbadge {font-size: 70%;}
				.imgmain {display: none;}
				.imgmain2 {display: '';}
				.content {margin-top: 55px;}
			}
			@media (min-width: 992px) {
				.mopcion {display: none;}
				.titulo {font-size: 150%;}
				.mbadge {font-size: 75%;}
				.imgmain {display: block;}
				.imgmain2 {display: none;}
				.content {margin-top: 60px;}
			}
			.modal-backdrop {
				z-index: 8888;
			}
			.swal2-container {
				display: -webkit-box;
				display: flex;
				position: fixed;
				z-index: 300000 !important;
			}
			.custom-control-label {
				margin-bottom: 0;
				cursor: pointer;
			}
			.current-row {
				background-color: #3A5F91 !important;
				font-weight: 600 !important;
				color: #FFFFFF !important;
			}
		</style>
	</head>
	<body class="hold-transition sidebar-mini sidebar-collapse"
		onload="
			<?php if(strpos($_SESSION['modulos'], '[1]')!==false) { ?>
				cargarcontenido('dashboard')
			<?php } else { ?>
				// cargarcontenido('perecederos_resumen')
				$('#fondo').removeClass('d-none')
			<?php } ?>">
		<input type="hidden" tabindex="-1" disabled name="hora_act" id="hora_act" value="">
		<input type="hidden" tabindex="-1" disabled name="fecinibimas" id="fecinibimas" value="<?php echo $_SESSION['fecinibimas'] ?>">
		<input type="hidden" tabindex="-1" disabled name="uinombre" id="uinombre" value="<?php echo $_SESSION['nomusuario'] ?>">
		<input type="hidden" tabindex="-1" disabled name="uilogin" id="uilogin" value="<?php echo $_SESSION['usuario'] ?>">
		<input type="hidden" tabindex="-1" disabled name="codclteindex" id="codclteindex" value="">
		<input type="hidden" tabindex="-1" disabled name="origenui" id="origenui" value="">
		<!-- Navbar -->
		<div class="fixed-top main-header navbar navbar-expand border-bottom navbar-dark bg-dark elevation-2">
			<a class="nav-link mopcion" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
			<img src="dist/img/solologo.png" class="m-0 p-0 bg-transparent imgmain" height="45px">
			<img src="dist/img/solologo.png" class="m-0 p-0 bg-transparent imgmain2" height="30px" data-widget="pushmenu">
			<span id="titulo" class="align-items-center ml-2 titulo">Información de Todas las Tiendas</span>
			<span id="tiempo" class="mbadge ml-auto border elevation-2"></span>
			<a id="btncambiar" class="btn btn-sm btn-outline-warning" style="display: none;" onclick="actualizar('*', 'Todas las Tiendas')">
				Ver Todas las Tiendas
			</a>
		</div>
		<div class="wrapper">
			<!-- Main Sidebar Container -->
			<aside class="main-sidebar sidebar-dark-primary elevation-4" style="overflow: hidden;">
				<!-- Sidebar user panel (optional) -->
				<div class="user-panel m-0 p-0 d-flex align-items-center">
					<div class="image m-1 p-1">
						<img src="dist/img/favicon.png" class="brand-image" alt="Logo">
					</div>
					<div class="info mt-0 w-100 text-center pt-0">
						<span class="badge badge-success w-100 font-weight-normal">
							<?php echo substr($_SESSION['nomusuario'], 0, 30) ?>
						</span>
						<div class="d-flex pt-2">
							<button class="btn btn-outline-warning btn-sm p-0 m-0 pl-1 pr-1 w-100"
								data-toggle="modal" data-target="#CambiarClave">
								Cambiar Clave
							</button>
							<form action="app/DBProcs.php" method="post" class="w-100 m-0 p-0 text-right">
								<input type="hidden" name="idpara" id="idpara" value="<?php echo $_SESSION['url'] ?>">
								<input type="hidden" name="opcion" id="opcion" value="cerrar_sesion">
								<button id="cerrarSesion" class="btn btn-outline-warning btn-sm p-0 m-0 pl-1 pr-1 w-100">
									Cerrar
								</button>
							</form>
						</div>
					</div>
				</div>

				<!-- Sidebar -->
				<div class="sidebar scroller m-0 p-0" style="overflow-x: hidden;">
					<!-- Sidebar Menu -->
					<nav class="m-1">
						<ul class="nav nav-pills nav-sidebar flex-column txtcomp" data-widget="treeview" role="menu" data-accordion="false">
							<?php if(strpos($_SESSION['modulos'], '[1]')!==false) { ?>
								<li class="nav-item">
									<a id="dashboard" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
										<i class="nav-icon fas fa-tachometer-alt"></i>
										<p>Dashboard</p>
									</a>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[2]')!==false) { ?>
								<li class="nav-item">
									<a id="est_clientes" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
										<i class="nav-icon fas fa-user-tag"></i>
										<p>Estadístico de Clientes</p>
									</a>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[17]')!==false) { ?>
								<li class="nav-item">
									<a id="vtas_articulos" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
										<i class="nav-icon fas fa-cubes"></i>
										<p>Ventas por Articulos</p>
									</a>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[18]')!==false) { ?>
								<li class="nav-item">
									<a id="calendario_vtas" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
										<i class="nav-icon far fa-calendar-alt"></i>
										<p>Calendario de Ventas</p>
									</a>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[3]')!==false ||
									 strpos($_SESSION['modulos'], '[4]')!==false ||
									 strpos($_SESSION['modulos'], '[5]')!==false ||
									 strpos($_SESSION['modulos'], '[6]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="vtas_sucursales" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-shopping-basket"></i>
										<p>Venta Sucursales <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[4]')!==false) { ?>
											<li class="nav-item">
												<a id="dia_ventas" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id); ">
													&#9642; <i class="fas fa-calendar-day nav-icon"></i>
													<p>Día X por Semana</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[5]')!==false) { ?>
											<li class="nav-item">
												<a id="sem_ventas" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id); ">
													&#9642; <i class=" fas fa-calendar-week nav-icon"></i>
													<p>4 Semanas</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[6]')!==false) { ?>
											<li class="nav-item">
												<a id="mes_ventas" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id); ">
													&#9642; <i class="fas fa-calendar-alt nav-icon"></i>
													<p>4 Meses</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[8]')!==false ||
									 strpos($_SESSION['modulos'], '[9]')!==false ||
									 strpos($_SESSION['modulos'], '[10]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="vtas_departamentos" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-gifts"></i>
										<p>Venta Departamentos <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[8]')!==false) { ?>
											<li class="nav-item">
												<a id="dia_ventas_dpto" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="fas fa-calendar-day nav-icon"></i>
													<p>Día X por Semana</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[9]')!==false) { ?>
											<li class="nav-item">
												<a id="sem_ventas_dpto" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="fas fa-calendar-week nav-icon"></i>
													<p>4 Semanas</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[10]')!==false) { ?>
											<li class="nav-item">
												<a id="mes_ventas_dpto" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="fas fa-calendar-alt nav-icon"></i>
													<p>4 Meses</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[11]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="vtas_promociones" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-cart-arrow-down"></i>
										<p>Venta Promociones <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[11]')!==false) { ?>
											<li class="nav-item">
												<a id="dia_ventas_promo" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="fas fa-calendar-day nav-icon"></i>
													<p>Día X por Semana</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[12]')!==false ||
									 strpos($_SESSION['modulos'], '[31]')!==false ||
									 strpos($_SESSION['modulos'], '[32]')!==false ||
									 strpos($_SESSION['modulos'], '[34]')!==false ||
									 strpos($_SESSION['modulos'], '[35]')!==false ||
									 strpos($_SESSION['modulos'], '[36]')!==false ||
									 strpos($_SESSION['modulos'], '[51]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="reportes_varios" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-print"></i>
										<p>Reportes Varios <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[12]')!==false) { ?>
											<li class="nav-item">
												<a id="reporte" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642;	<i class="nav-icon fas fa-print"></i>
													<p>Reportes a tu Medida</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[32]')!==false) { ?>
											<li class="nav-item">
												<a id="exportvtasbimas" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642;	<i class="nav-icon fas fa-file-export"></i>
													<p>Ventas Bi+ &#8658; Excel</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[31]')!==false) { ?>
											<li class="nav-item">
												<a id="pedidos_vs_despachos" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-file-alt"></i>
													<p>Pedidos Vs Despachos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[34]')!==false) { ?>
											<li class="nav-item">
												<a id="reporteDIAN" href="javascript:void(0);" class="nav-link menu" onclick="cargarReporteDian()">
													&#9642; <i class="nav-icon fas fa-file-excel"></i>
													<p>Reporte DIAN</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[35]')!==false) { ?>
											<li class="nav-item">
												<a id="reporteDANE" href="javascript:void(0);" class="nav-link menu" onclick="cargarReporteDane()">
													&#9642; <i class="nav-icon fas fa-file-csv"></i>
													<p>Reporte DANE</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[36]')!==false) { ?>
											<li class="nav-item">
												<a id="exportacionesDIAN" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-file-csv"></i>
													<p>Vtas. Administrativas</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[51]')!==false) { ?>
											<li class="nav-item">
												<a id="lista_precios_bimas" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-list-ol"></i>
													<p>Listado de Precios Bi+</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[54]')!==false) { ?>
											<li class="nav-item">
												<a id="reporteNIELSEN" href="javascript:void(0);" class="nav-link menu" onclick="cargarReporteNielsen()">
													&#9642; <i class="nav-icon fas fa-file-excel"></i>
													<p>Reporte NIELSEN</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[13]')!==false ||
									 strpos($_SESSION['modulos'], '[14]')!==false ||
									 strpos($_SESSION['modulos'], '[15]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="mgraficos" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-chart-pie"></i>
										<p>Gráficos Estadísticos <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[13]')!==false) { ?>
											<li class="nav-item">
												<a id="cont_clientes" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-users"></i>
													<p>Conteo de Clientes</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[14]')!==false) { ?>
											<li class="nav-item">
												<a id="graf_vtasacum" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642;	<i class="nav-icon fas fa-chart-line"></i>
													<p>Venta Acumulada</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[15]')!==false) { ?>
											<li class="nav-item">
												<a id="graf_top20prod" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642;	<i class="nav-icon fas fa-chart-bar"></i>
													<p>Top 20 Productos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[20]')!==false) { ?>
											<li class="nav-item">
												<a id="graf_top20tipos" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642;	<i class="nav-icon fab fa-codiepie"></i>
													<p>Top 20 por Tipos</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[22]')!==false ||
									 strpos($_SESSION['modulos'], '[27]')!==false ||
									 strpos($_SESSION['modulos'], '[28]')!==false ||
									 strpos($_SESSION['modulos'], '[30]')!==false ||
									 strpos($_SESSION['modulos'], '[33]')!==false ||
									 strpos($_SESSION['modulos'], '[38]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="logistica" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-clipboard-list"></i>
										<p>Logística <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[27]')!==false) { ?>
											<li class="nav-item">
												<a id="transacciones" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-exchange-alt"></i>
													<p>Procesos de Logística</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[38]')!==false) { ?>
											<li class="nav-item">
												<a id="recepciones" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-truck-loading"></i>
													<p>Util. Recepciones</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[28]')!==false) { ?>
											<li class="nav-item">
												<a id="transp_transf" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="fas fa-truck-moving nav-icon"></i>
													<p>Transporte Transf.</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[22]')!==false) { ?>
											<li class="nav-item">
												<a id="consulta_inv" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-square-root-alt"></i>
													<p>Consultar Agotados</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[30]')!==false) { ?>
											<li class="nav-item">
												<a href="javascript:void(0);" class="nav-link menu" onclick="cargarexportar();">
													&#9642; <i class="nav-icon fas fa-file-excel"></i>
													<p>Agente de Vtas TNS</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[55]')!==false ||
									 strpos($_SESSION['modulos'], '[56]')!==false || $_SESSION['usuario']=='cramirez') { ?>
								<li class="nav-item has-treeview">
									<a id="cltes_admon" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-user-clock"></i>
										<p>Clientes Administración <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[55]')!==false || $_SESSION['usuario']=='cramirez') { ?>
											<li class="nav-item">
												<a id="registro_clientes" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-user-edit"></i>
													<p>Registro de Clientes</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[56]')!==false || $_SESSION['usuario']=='cramirez') { ?>
											<li class="nav-item">
												<a id="vtas_nosync_facele" href="javascript:void(0);" class="nav-link menu"
													onclick="cargarcontenido(this.id);" title="Ventas pendientes por Sincronizar para Facturas electrónicas">
													&#9642; <i class="nav-icon fas fa-store-alt-slash"></i>
													<p>Ventas Pend. x Fact. Elec.</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[56]')!==false || $_SESSION['usuario']=='cramirez') { ?>
											<li class="nav-item">
												<a id="resumen_fac_ele" href="javascript:void(0);" class="nav-link menu"
													onclick="cargarcontenido(this.id);" title="Resumen de Facturas Electrónicas Realizadas">
													&#9642; <i class="nav-icon fas fa-file-medical-alt"></i>
													<p>Fac. Elec. Realizadas</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[23]')!==false ||
									 strpos($_SESSION['modulos'], '[24]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="mconfiguracion" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-newspaper"></i>
										<p>Otras Consultas<i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[23]')!==false) { ?>
											<li class="nav-item">
												<a id="aud_costos" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-tasks"></i>
													<p>Auditoría Costos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[24]')!==false) { ?>
											<li class="nav-item">
												<a id="compara_vtas" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-shapes"></i>
													<p>Tiendas Vs Central</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[25]')!==false ||
									 strpos($_SESSION['modulos'], '[26]')!==false ||
									 strpos($_SESSION['modulos'], '[29]')!==false ) { ?>
								<li class="nav-item has-treeview">
									<a id="msolicitudes" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-edit"></i>
										<p>Solicitudes<i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[25]')!==false) { ?>
											<li class="nav-item">
												<a id="consulta_inv_pedidos" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-file-alt"></i>
													<p>Solicitud de Pedidos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[26]')!==false) { ?>
											<li class="nav-item">
												<a id="consulta_lista_pedidos" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-search"></i>
													<p>Lista de Pedidos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[29]')!==false) { ?>
											<li class="nav-item">
												<a id="monitor_pedidos" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-boxes"></i>
													<p>Monitor de Pedidos</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[41]')!==false ||
									 strpos($_SESSION['modulos'], '[42]')!==false ||
									 strpos($_SESSION['modulos'], '[46]')!==false ||
									 strpos($_SESSION['modulos'], '[47]')!==false ||
									 strpos($_SESSION['modulos'], '[48]')!==false ||
									 strpos($_SESSION['modulos'], '[50]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="msolicitudes" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-carrot"></i>
										<p>Pedidos Perecederos<i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[41]')!==false) { ?>
											<li class="nav-item">
												<a id="perecederos_pedidos" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-file-signature"></i>
													<p>Realizar Pedido</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[42]')!==false) { ?>
											<li class="nav-item">
												<a id="perecederos_consolida" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-compress-arrows-alt"></i>
													<p>Consolidar Pedidos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[46]')!==false) { ?>
											<li class="nav-item">
												<a id="perecederos_gdc" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-clipboard-list"></i>
													<p>Gestión de Compra</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[47]')!==false) { ?>
											<li class="nav-item">
												<a id="perecederos_hdt" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-file-alt"></i>
													<p>Hojas Trabajo Ant.</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[48]')!==false) { ?>
											<li class="nav-item">
												<a id="perecederos_xbuy" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-stopwatch-20"></i>
													<p>Pendientes x Comprar.</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[50]')!==false) { ?>
											<li class="nav-item">
												<a id="perecederos_monitor" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-boxes"></i>
													<p>Monitor Perecederos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[53]')!==false) { ?>
											<li class="nav-item">
												<a id="perecederos_resumen" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-not-equal"></i>
													<p>Resumen Perecederos</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[37]')) { ?>
								<li class="nav-item has-treeview">
									<a id="mconfiguracion" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-globe-americas"></i>
										<p>Info Página WEB <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[37]')!==false) { ?>
											<li class="nav-item">
												<a id="imagenes_web" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-upload"></i>
													<p>Mtto. Imagenes Web</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[39]')!==false ||
									 strpos($_SESSION['modulos'], '[44]')!==false ||
									 strpos($_SESSION['modulos'], '[45]')!==false ||
									 strpos($_SESSION['modulos'], '[49]')!==false ||
									 strpos($_SESSION['modulos'], '[52]')!==false ) { ?>
								<li class="nav-item has-treeview">
									<a id="mconfiguracion" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-receipt"></i>
										<p>Bonos Montes <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[39]')!==false) { ?>
											<li class="nav-item">
												<a id="conf_bonos_pos" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-upload"></i>
													<p>Configuración de Bonos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[44]')!==false) { ?>
											<li class="nav-item">
												<a id="reportes_pma" href="javascript:void(0);" class="nav-link" onclick="cargarReportePMA();">
													&#9642; <i class="nav-icon fas fa-file-excel"></i>
													<p>Reportes PMA</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[45]')!==false) { ?>
											<li class="nav-item">
												<a id="modificar_redimido" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-pencil-alt"></i>
													<p>Mod. Bono Redimido</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[49]')!==false) { ?>
											<li class="nav-item">
												<a id="facturas2PDFong" href="javascript:void(0);" class="nav-link" onclick="facturas2PDFong();">
													&#9642; <i class="nav-icon fas fa-file-pdf"></i>
													<p>Facturas a PDF Ong</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[52]')!==false) { ?>
											<li class="nav-item">
												<a id="movimientos_bonos" href="javascript:void(0);" class="nav-link" onclick="cargarcontenido(this.id);">
													&#9642; <i class="nav-icon fas fa-hand-holding-usd"></i>
													<p>Movimientos Bonos</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
							<?php if(strpos($_SESSION['modulos'], '[7]' )!==false ||
									 strpos($_SESSION['modulos'], '[16]')!==false ||
									 strpos($_SESSION['modulos'], '[19]')!==false ||
									 strpos($_SESSION['modulos'], '[21]')!==false ||
									 strpos($_SESSION['modulos'], '[43]')!==false) { ?>
								<li class="nav-item has-treeview">
									<a id="mconfiguracion" href="javascript:void(0);" class="nav-link">
										<i class="nav-icon fas fa-tools"></i>
										<p>Configuración <i class="right fas fa-angle-left"></i></p>
									</a>
									<ul class="nav nav-treeview">
										<?php if(strpos($_SESSION['modulos'], '[7]')!==false) { ?>
											<li class="nav-item">
												<a id="usuarios" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642;	<i class="nav-icon fas fa-users-cog"></i>
													<p>Usuarios</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[16]')!==false) { ?>
											<li class="nav-item">
												<a id="subir_archivo" href="javascript:void(0);" class="nav-link menu" onclick="lstArchivos();">
													&#9642;	<i class="nav-icon fas fa-upload"></i>
													<p>Subir Archivo .csv</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[19]')!==false) { ?>
											<li class="nav-item">
												<a id="conf_dptos" href="javascript:void(0);" class="nav-link menu" onclick="confDptos();">
													&#9642;	<i class="nav-icon fas fa-warehouse"></i>
													<p>Departamentos</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[21]')!==false) { ?>
											<li class="nav-item">
												<a id="tasa_dolar" href="javascript:void(0);" class="nav-link menu" onclick="confTasas();">
													&#9642;	<i class="nav-icon fas fa-file-invoice-dollar"></i>
													<p>Tasas del Dolar</p>
												</a>
											</li>
										<?php } ?>
										<?php if(strpos($_SESSION['modulos'], '[43]')!==false) { ?>
											<li class="nav-item">
												<a id="articulos_proveedor" href="javascript:void(0);" class="nav-link menu" onclick="cargarcontenido(this.id);">
													&#9642;	<i class="nav-icon fas fa-dolly"></i>
													<p>Artículos X Prov.</p>
												</a>
											</li>
										<?php } ?>
									</ul>
								</li>
							<?php } ?>
						</ul>
					</nav>
					<!-- /.sidebar-menu -->
				</div>
				<!-- /.sidebar -->
			</aside>

			<div class="content-wrapper" id="wp_ppal">
				<!-- Contains page content -->
				<section class="content" id="contenido_ppal">
					<div class="d-flex m-0 p-0 justify-content-center align-items-center" style="height: 90vh;">
						<img src="dist/img/solologo.png" style="width: 90%;">
					</div>
				</section>
				<!-- /.content -->
			</div>
		</div>

		<!-- Modal Cargando-->
		<div class="modal" id="loading" style="z-index: 9999" tabindex="-1" role="dialog" aria-labelledby="ModalLoading" aria-hidden="true">
			<div class="modal-dialog modal-sm modal-dialog-centered" role="document">
				<div class="modal-content align-items-center align-content-center border-0 elevation-0 bg-transparent">
					<div class="loader"></div>
					<button class="btn btn-sm btn-danger m-3 p-1"
						onclick="if(tomar_datos!=='') { tomar_datos.abort(); cargando('hide'); }">
						Cancelar Consulta
					</button>
				</div>
			</div>
		</div>

		<!-- Modal Cargando guardar solicitud de pedidos-->
		<div class="modal" id="loading2" style="z-index: 9999" tabindex="-1" role="dialog" aria-labelledby="ModalLoading" aria-hidden="true">
			<div class="modal-dialog modal-sm modal-dialog-centered" role="document">
				<div class="modal-content align-items-center align-content-center border-0 elevation-0 bg-transparent">
					<div class="loader"></div>
					<div class="bg-warning-gradient p-2 m-2 text-center align-middle rounded border border-dark">
						<b>Procesando, espere...</b>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal Extragrande -->
		<div class="bgtransparent" id="bgtransparent"></div>
		<div class="bgmodal m-0 p-0 elevation-2" style="display: none; overflow: hidden; border-radius: 5px; z-index: 8887" id="bgmodal">
			<div class="modal-header bg-primary m-0 p-0" id="header-modal">
				<h5 class="modal-title font-weight-bold align-middle mr-1 mb-1 mt-2 ml-2" id="bgtitulo"></h5>
				<button type="button" onclick="closeModal()" class="btn btn-danger btn-lg float-right">
					<span class="fas fa-window-close float-right" aria-hidden="true"></span>
				</button>
			</div>
			<div class="modal-body m-0 p-0 w-100 h-100" id="contModal" style="overflow: hidden;">
			</div>
		</div>

		<!-- Modal para datos 1-->
		<div class="modal fade" id="ModalDatos" style="z-index: 9888" tabindex="-1" role="dialog" aria-labelledby="ModalDatosLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
				<div class="modal-content">
					<div class="modal-header bg-primary pb-0 pt-0 pr-0">
						<h5 class="modal-title font-weight-bold" id="tituloModal"></h5>
						<button type="button" data-dismiss="modal" aria-label="Close" class="btn btn-danger btn-lg float-right">
							<span class="fas fa-window-close float-right" aria-hidden="true"></span>
						</button>
					</div>
					<div class="modal-body m-0 p-0" id="contenidoModal">
					</div>
				</div>
			</div>
		</div>

		<!-- Modal para datos 2-->
		<div class="modal fade" id="ModalDatos2" style="z-index: 9888" tabindex="-1" role="dialog" aria-labelledby="ModalDatos2Label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
					<div class="modal-header bg-primary pb-0 pt-0 pr-0">
						<h5 class="modal-title font-weight-bold" id="tituloModal2"></h5>
						<button type="button" data-dismiss="modal" id="btnCloseModal"
							aria-label="Close" class="btn btn-danger btn-lg float-right">
							<span class="fas fa-window-close float-right" aria-hidden="true"></span>
						</button>
					</div>
					<div class="mbadge elevation-2 border border-white" id="subtitulo"></div>
					<div class="modal-body m-0 p-0" id="contenidoModal2">
					</div>
				</div>
			</div>
		</div>

		<!-- Modal para datos 3-->
		<div class="modal fade" id="ModalDatos3" style="z-index: 9888" tabindex="-1" role="dialog" aria-labelledby="ModalDatos3Label" aria-hidden="true">
			<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
				<div class="modal-content">
					<div class="modal-header bg-primary pb-0 pt-0 pr-0">
						<h5 class="modal-title font-weight-bold" id="tituloModal3"></h5>
						<button type="button" data-dismiss="modal" aria-label="Close" class="btn btn-danger btn-lg float-right">
							<span class="fas fa-window-close float-right" aria-hidden="true"></span>
						</button>
					</div>
					<div class="mbadge elevation-2 border border-white mb-2" id="subtitulo3"></div>
					<div class="modal-body m-0 p-0" id="contenidoModal3">
					</div>
				</div>
			</div>
		</div>

		<!-- Modal datos factura-->
		<div class="modal fade" id="ModalDatosFactura" style="z-index: 9888" tabindex="-1" role="dialog" aria-labelledby="ModalDatosFacturaLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
				<div class="modal-content">
					<div class="modal-header bg-primary pb-0 pt-0 pr-0">
						<h5 class="modal-title font-weight-bold">Datos de la Factura</h5>
						<button type="button" data-dismiss="modal" aria-label="Close" class="btn btn-danger btn-lg float-right">
							<span class="fas fa-window-close float-right" aria-hidden="true"></span>
						</button>
					</div>
					<div class="modal-body" id="contenidoModalFactura">
					</div>
				</div>
			</div>
		</div>

		<!-- Modal cambiar clave-->
		<div class="modal fade" id="CambiarClave" style="z-index: 9888" tabindex="-1" role="dialog" aria-labelledby="CambiarClaveLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header bg-primary">
						<h5 class="modal-title font-weight-bold">Cambiar clave de Usuario</h5>
					</div>
					<div class="modal-body">
						<form action="" onsubmit="return false">
							<input id="userName" name="username" autocomplete="username" value="" disabled style="display: none;">
							<div class="input-group">
								<span class="input-group-addon p-1 pl-2 pr-2 bg-info"><i class="fas fa-lock"></i></span>
								<input id="cctclavea" type="password" class="form-control" autocomplete="current-password" placeholder="Clave Anterior" required>
							</div>
							<br>
							<div class="input-group">
								<span class="input-group-addon p-1 pl-2 pr-2 bg-info"><i class="fas fa-lock"></i></span>
								<input id="cctclaven" type="password" class="form-control" autocomplete="new-password" placeholder="Nueva Clave" required>
							</div>
							<br>
							<div class="input-group">
								<span class="input-group-addon p-1 pl-2 pr-2 bg-info"><i class="fas fa-lock"></i></span>
								<input id="cctclavec" type="password" class="form-control" autocomplete="new-password" placeholder="Confirmar Clave Nueva" required>
							</div>
							<br>
							<div class="row">
								<div class="col-6">
									<button class="btn btn-outline-danger btn-block btn-flat" data-dismiss="modal" id="cancelar">
										Cancelar&nbsp;&nbsp;<i class="fas fa-sign-out-alt"></i>
									</button>
								</div>
								<div class="col-6">
									<button class="btn btn-primary btn-block btn-flat d-none" id="aceptar"
											onclick="javascript:cambiarClave( encrypt($('#cctclavea').val()), encrypt($('#cctclaven').val()) )">
										Aceptar&nbsp;&nbsp;<i class="fas fa-check-circle"></i>
									</button>
								</div>
								<!-- /.col -->
							</div>
						</form>
					</div>
					<div class="modal-footer p-1 m-1">
						<span class="badge badge-danger w-100 py-2 elevation-2 border border-light" id="mensaje"></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal subir archivo -->
		<div class="modal fade" id="subirArchivo" style="z-index: 9888;" tabindex="-1" role="dialog" aria-labelledby="subirArchivoLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<!-- Modal content-->
				<div class="modal-content">
					<div class="modal-header bg-primary pt-1 pb-1">
						<h4 class="modal-title">Subir Archivo .csv</h4>
					</div>
					<div class="modal-body">
						<div class="btn-group btn-group-sm btn-group-toggle w-100 font-weight-bold" id="opcionesgr"
							data-toggle="buttons">
							<div class="input-group">
								<label for="farchivos" class="m-0 mt-1 mr-1">Subir Archivo para Actualizar</label>
								<span class="input-group-addon p-1 pl-2 pr-2 bg-info">
									<i class="text-center align-middle fas fa-list" style="width: 20px;"></i>
								</span>
								<select id="farchivos" class="form-control form-control-sm mr-2"
									onchange="
										$('#opcion_sub').html($('#farchivos option:selected').text());
										$('#ejemplo').html($('#farchivos option:selected').attr('text'))">
								</select>
							</div>
						</div>
						<hr>
						<div class="w-100">
							<p class="text-justify">Por favor seleccione el archivo .csv de
								<span id="opcion_sub" class="font-weight-bold text-primary"></span>
							   a subir
							</p>
							<p class="text-justify">Tener en cuenta que los datos del archivo deben estar separados por
								<b>punto y coma (;)</b> y los números
								no deben contener separadores de mil, sólo el separador decimal, bien sea
								<b>coma (,)</b> o <b>punto (.)</b>
							</p>
							<p class="text-justify w-75 ml-auto mr-auto bg-warning p-1 border border-dark elevation-2" id="ejemplo">
							</p>
							<input type='file' id='nom_archivo' class="form-control btn-primary" accept=".csv"
							onchange="this.value!='' ? $('#btnsubir').removeClass('d-none') : $('#btnsubir').addClass('d-none')">
						</div>
						<div class="custom-control custom-checkbox m-0 mt-2 float-right" id="dreemplazar">
							<input type="checkbox" class="custom-control-input" style="cursor: pointer;" id="reemplazar">
							<label class="custom-control-label" for="reemplazar" style="cursor: pointer;">Reemplazar Datos?</label>
						</div>
					</div>
					<div class="modal-footer pt-2 pb-2 align-items-end justify-content-end align-middle">
						<div class="d-none col-12 text-center" id="subiendo">
							Actualizando la información, por favor espere...<img src="dist/img/subiendo.gif" height="50px">
						</div>
						<button class="btn btn-outline-success col-4 d-none" id="btnsubir"
							onclick="subirArchivo();">
							Subir Archivo <i class="fas fa-upload"></i>
						</button>
						<button class="btn btn-outline-danger col-4" class="close" data-dismiss="modal" id="btncerrar"
							onclick="$('#nom_archivo').val('').change(); $('#farchivos').prop('selectedIndex', 0).change();">
							Cancelar <i class="fas fa-times-circle"></i>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal configurar departamento -->
		<div class="modal fade" id="confDpto" style="z-index: 9888;" tabindex="-1" role="dialog" aria-labelledby="confDptoLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<!-- Modal content-->
				<div class="modal-content">
					<div class="modal-header bg-primary pt-1 pb-1">
						<h4 class="modal-title">Departamentos con tipo de productos</h4>
					</div>
					<div class="modal-body p-0" id="contConfDpto">
					</div>
					<div class="modal-footer pt-2 pb-2 align-items-end justify-content-end align-top">
						<div class="text-center col-9 rounded border border-dark elevation-2 d-none" id="msj"></div>
						<button class="btn btn-outline-danger col-3" class="close" data-dismiss="modal" id="btncerrar">
							Cerrar <i class="fas fa-times-circle"></i>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal configurar tasa de dolares -->
		<div class="modal fade" id="confTasa" style="z-index: 9888;" tabindex="-1" role="dialog" aria-labelledby="confTasaLabel" aria-hidden="true">
			<div class="modal-dialog">
				<!-- Modal content-->
				<div class="modal-content">
					<div class="modal-header bg-primary pt-1 pb-1">
						<h4 class="modal-title">Configuración de la tasa del dólar por dia</h4>
					</div>
					<div class="card-header align-middle align-items-center align-content-center justify-content-center">
						<div class="d-flex">
							<label for="fechausd">Fecha</label>
							<input id="fechausd" name="fechausd" type="text" class="ml-2 mr-2 p-0 form-control form-control-sm text-center"
									autocomplete="off" data-inputmask="'alias': 'dd-mm-yyyy'" data-mask placeholder="dd-mm-yyyy">
							<label for="montousd">Monto</label>
							<input type="text" placeholder="99999999 " class="ml-2 mr-2 p-0 form-control form-control-sm text-right"
								id="montousd" onkeydown="soloNumeros()" id="montom" value="">
							<button class="btn btn-sm btn-outline-success col-3 ml-auto mr-auto" id="btnagrega"
								onclick="guardarTasaDolar($('#fechausd').val(), $('#montousd').val())">
								<i class="fas fa-plus"></i>Guardar
							</button>
						</div>
					</div>
					<div class="modal-body p-0" id="divconfTasa">
					</div>
					<div class="modal-footer pt-2 pb-2 align-items-end justify-content-end align-top">
						<div class="text-center col-9 rounded border border-dark elevation-2 d-none" id="msjusd"></div>
						<button class="btn btn-sm btn-outline-danger col-3" class="close" data-dismiss="modal" id="btncierra">
							Cerrar <i class="fas fa-times-circle"></i>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- jQuery -->
		<script src="plugins/jquery/jquery.min.js"></script>
		<!-- jQuery UI 1.12.1 -->
		<script src="plugins/jQueryUI/jquery-ui.min.js"></script>
		<!-- Bootstrap 4 -->
		<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

		<!-- DataTables -->
		<script src="plugins/datatables/jquery.dataTables.min.js"></script>
		<script src="plugins/datatables/dataTables.responsive.min.js"></script>
		<script src="plugins/datatables/dataTables.buttons.min.js"></script>
		<script src="plugins/datatables/jszip.min.js"></script>
		<script src="plugins/datatables/buttons.html5.min.js"></script>
		<script src="plugins/datatables/pdfmake.min.js"></script>
		<script src="plugins/datatables/vfs_fonts.js"></script>

		<!-- bootstrap-select -->
		<script src="plugins/bootstrap-select/js/bootstrap-select.js"></script>
		<script src="plugins/bootstrap-select/js/defaults-es_ES.min.js"></script>
		<!-- Datepicker bootstrap -->
		<script src="plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js"></script>
		<script src="plugins/bootstrap-datepicker/js/bootstrap-datepicker.es.min.js"></script>

		<!-- SweetAlert2@9 -->
		<script src="plugins/sweetalert2/sweetalert2.all.min.js"></script>

		<!-- InputMask -->
		<script src="plugins/input-mask/jquery.inputmask.js"></script>
		<script src="plugins/input-mask/jquery.inputmask.date.extensions.js"></script>
		<!-- moment-with-locals.min.js -->
		<script src="dist/js/moment.min.js"></script>
		<!-- AdminLTE App -->
		<script src="dist/js/adminlte.min.js"></script>
		<!-- JS propias app -->
		<script src="app/js/app.js"></script>
		<!-- Encrypt js -->
		<script src="app/js/encrypt.min.js"></script>

		<!-- Grafico -->
		<script src="plugins/highcharts/highcharts.js"></script>
		<script src="plugins/highcharts/highcharts-3d.js"></script>
		<script src="plugins/highcharts/broken-axis.js"></script>

		<script>
			moment.locale('es')
			moment.updateLocale('es', {
				week: {
					dow: 0
				}
			});
			var ancho = 600;
			var alto = 250;
			var temporizadort = 20000;
			var temporizador1 = '';
			var tiempofalta = temporizadort;
			var ptienda = <?php echo $_SESSION['tienda'] ?>;
			var ptodas = ptienda=='*' ? true : false;
			var pnombretienda = ptienda=='*' ? 'Todas las Tiendas' : <?php echo $_SESSION['sucursal'] ?>;
			var tomar_datos = '';
			var temporizadorp = 0;
			var tempo_hoy = ''; //variable para el temporizador del calendario para actualizar el dia actual
			var tblfAjustarAltoTA = '';

			$('#fechausd').datepicker({
				format: "dd-mm-yyyy",
				todayBtn: "linked",
				language: "es",
				autoclose: true,
				todayHighlight: true,
			});
			$("#fechausd").datepicker().datepicker("setDate", new Date());
			$('[data-mask]').inputmask();

			function mostrarModal(contenidoHTML){
				// agregamos contenido HTML a la ventana modal
				$('#contModal').html(contenidoHTML);

				$('#bgtransparent').css('display', 'block');
				$('#bgmodal').css('display', 'block');

				// redimensionamos para que se ajuste al centro y mas
				setTimeout("$(window).resize()", 100);
			}

			$(window).resize(function(){
				if($('#bgmodal').is(':visible')) {
					// dimensiones de la ventana del explorer
					var wscr = $(window).width();
					var hscr = $(window).height();

					// estableciendo dimensiones de fondo
					$('#bgtransparent').css("width", wscr);
					$('#bgtransparent').css("height", hscr);

					// estableciendo tamaño de la ventana modal
					$('#bgmodal').css("width", (wscr*0.85)+'px');
					$('#bgmodal').css("height", (hscr*0.75)+'px');

					// obtiendo tamaño de la ventana modal
					var wcnt = $('#bgmodal').width();
					var hcnt = $('#bgmodal').height();

					// obtener posicion central
					var mleft = ( wscr - wcnt ) / 2;
					var mtop = ( hscr - hcnt ) / 2;

					// estableciendo ventana modal en el centro
					$('#bgmodal').css("left", mleft+'px');
					$('#bgmodal').css("top", mtop+'px');
					var position = $('#bgmodal').position();
					var ttop = position.top;
					setTimeout('fAjustarAltoTA(tblfAjustarAltoTA)', 150);
					setTimeout('fAjustarAltoTA(tblfAjustarAltoTA)', 150);
				}
				$('.dataTable').DataTable().columns.adjust().draw();
			});

			function closeModal(){
				$('#bgtransparent').css('display', 'none');
				$('#bgmodal').css('display', 'none');
				if( !$("#tiempo").hasClass("d-none") ) {
					temporizador1 = setTimeout("tiemporesta('" + ptienda + "')", 1000);
				}
			}

			// Resolve conflict in jQuery UI tooltip with Bootstrap tooltip
			$.widget.bridge('uibutton', $.ui.button)

			$('#ModalDatos, #subirArchivo, #confDpto, #confTasa').on('show.bs.modal', function () {
				if( !$("#tiempo").hasClass("d-none") ) {
					clearTimeout(temporizador1);
				}
				if(this.id=='confTasa') { $('#montousd').val('') }
			});

			$('#ModalDatos, #subirArchivo, #confDpto, #confTasa').on('hidden.bs.modal', function () {
				if(temporizadorp==1) {
					temporizador1 = setTimeout("tiemporesta('" + ptienda + "')", 1000);
					temporizadorp = 0;
				}
				$('.modal-backdrop').css('zIndex', 8888);
			});

			$('#ModalDatos2').on('hidden.bs.modal', function () {
				if(temporizadorp==1) {
					temporizador1 = setTimeout("tiemporesta('" + ptienda + "')", 1000);
					temporizadorp = 0;
				}
				$('.modal-backdrop').css('zIndex', 8888);
			});

			$('#ModalDatosFactura').on('hidden.bs.modal', function() {
				$('.modal-backdrop').css('zIndex', 8888);
			})

			$('#ModalDatos3').on('shown.bs.modal', function () {
				//cargando('hide');
			})

			$('#CambiarClave').on('shown.bs.modal', function () {
				clearTimeout(temporizador1);
				$('#cctclavea').val('');
				$('#cctclaven').val('');
				$('#cctclavec').val('');
			});

			$('#CambiarClave').on('hidden.bs.modal', function () {
				if(temporizador1!='') {
					temporizador1 = setTimeout("tiemporesta('" + ptienda + "')", 1000);
				}
				$('#cctclavea').val('');
				$('#cctclaven').val('');
				$('#cctclavec').val('');
				$('#mensaje').html('');
				$('#aceptar').addClass('d-none');
				$('#contenido_ppal').focus();
			});

			$('#cctclaven, #cctclavec').on('keyup', function() {
				validar();
			});

			$('#cctclaven, #cctclavec').on('change', function() {
				validar();
			});

			$('#cctclaven, #cctclavec').on('focus', function() {
				validar();
			});

			$('#cctclaven, #cctclavec').on('blur', function() {
				validar();
			});


			$('.modal').modal({backdrop: 'static', keyboard: false, show: false})
			function validar() {
				$('#aceptar').addClass('d-none');
				switch (true) {
					case $('#cctclaven').val()==$('#cctclavec').val() && $('#cctclaven').val()==$('#cctclavea').val():
						$('#mensaje').html('Las claves nuevas deben ser diferentes a la anterior');
						break;
					case $('#cctclaven').val()!=$('#cctclavec').val():
						$('#mensaje').html('Las claves nuevas deben ser iguales');
						break;
					case $('#cctclaven').val()=='':
						$('#mensaje').html('La clave nueva no puede estar vacía');
						break;
					case $('#cctclavec').val()=='':
						$('#mensaje').html('La clave de confirmación no puede estar vacía');
						break;
					default:
						$('#mensaje').html('');
						$('#aceptar').removeClass('d-none');
						break;
				}
			}

			// Configuracion por defecto de las datatables
			$.extend( true, $.fn.dataTable.defaults, {
				language: {
					emptyTable        : "No hay información para mostrar",
					info              : "Mostrando _START_ de _END_ de _TOTAL_",
					infoEmpty         : "No hay información para mostrar",
					infoFiltered      : "(filtrado de _MAX_ entradas totales)",
					search            : "Buscar",
					infoPostFix       : "",
					lengthMenu        : "Mostrar _MENU_ líneas",
					loadingRecords    : "Cargando...",
					processing        : "Procesando...",
					zeroRecords       : "No hay información para mostrar",
					paginate: {
						first         : "Primero",
						last          : "Último",
						next          : "Siguiente",
						previous      : "Anterior"
					},
					aria: {
						sortAscending : ": activar orden ascendente de la columna",
						sortDescending: ": activar orden descendente de la columna"
					}
				},
				destroy: true,
				info: false,
				scrollY: "165px",
				bLengthChange: false,
				processing: true,
				paging: false,
				searching: false,
				ordering: true,
				sScrollX: "100%",
				scrollX: true,
			});

			function sortNumbersIgnoreText(a, b, high) {
				var reg = /[+-]?((\d+(\.\d*)?)|\.\d+)([eE][+-]?[0-9]+)?/;
				a = a.match(reg);
				a = a !== null ? parseFloat(a[0]) : high;
				b = b.match(reg);
				b = b !== null ? parseFloat(b[0]) : high;
				return ((a < b) ? -1 : ((a > b) ? 1 : 0));
			}

			jQuery.extend( jQuery.fn.dataTableExt.oSort, {
				"sort-numbers-ignore-text-asc": function (a, b) {
					return sortNumbersIgnoreText(a, b, Number.POSITIVE_INFINITY);
				},
				"sort-numbers-ignore-text-desc": function (a, b) {
					return sortNumbersIgnoreText(a, b, Number.NEGATIVE_INFINITY) * -1;
				}
			});

			const msg = Swal.mixin({
				customClass: {
					popup: 'p-2 bg-dark border border-warning',
					title: 'text-warning bg-transparent pl-3 pr-3',
					closeButton: 'btn btn-sm btn-danger',
					content: 'bg-white border border-warning rounded p-1',
					confirmButton: 'btn btn-sm btn-success m-1',
					cancelButton: 'btn btn-sm btn-danger m-1',
					input: 'border border-dark text-center',
				},
				onOpen            : function() { setTimeout("$('.swal2-confirm').focus()", 150) },
				buttonsStyling    : false,
				cancelButtonText  : '<i class="fas fa-times-circle"></i> Cancelar ',
				confirmButtonText : '<i class="fas fa-check-circle"></i> Aceptar ',
				showCancelButton  : true,
				allowOutsideClick : false,
			})

			function lstArchivos() {
				var element = $("#farchivos option");
				$.each(element,function(i,v){
					value = v.value;
					$("#farchivos option[value="+value+"]").remove();
				})
				$.ajax({
					data: { opcion: "listaSubirarchivos" },
					type: "POST",
					dataType: "json",
					url: "app/DBProcs.php",
					success : function(data) {
						$.each(data, function() {
							$('#farchivos').append(`<option value="${this.opcion}" text="${this.observacion}">${this.texto}</option>`);
						});
					}
				}).done(function() {
					$("#farchivos").prop('selectedIndex', 0).change();
					setTimeout("$('#subirArchivo').modal('show')", 100);
				});
			}

			function confDptos() {
				$.ajax({
					url: "app/DBProcs.php",
					data: { opcion: "listaConfDptos" },
					type: "POST",
					dataType: "json",
					success : function(data) {
						var contenido = '<table class="table table-hover table-striped w-100 p-0 m-0" id="tblConfDptos">'
							+'<thead>'
							+	'<th width="60%" class="bg-dark">Nombre Departamento</th>'
							+	'<th width="40%" class="bg-dark">Tipo de Productos</th>'
							+'</thead>';
							+'<tbody>'
						$.each(data, function() {
							contenido += '<tr><td>' + this.nomb + '</td><td>'
								+'<select id="ftipos" class="form-control form-control-sm"'
									+' onchange="cambiarTipoDptos(this.value, ' + this.dpto + ')">'
									+'<option value="a"' + (this.tipo=='a' ? 'selected' : '') + '>Alimentos</option>'
									+'<option value="n"' + (this.tipo=='n' ? 'selected' : '') + '>No Alimentos</option>'
									+'<option value="p"' + (this.tipo=='p' ? 'selected' : '') + '>Perecederos</option>'
								+'</select>'
							+'</td></tr>';
						})
						contenido += '</tbody>'
							+'</table>'
							+'<script>'
								+'$("#tblConfDptos").dataTable({ '
									+"scrollY: '40vh', "
									+'scrollCollapse: true, '
								+'});'
							+'</' + 'script>'
						$('#contConfDpto').html(contenido);
						$('#confDpto').modal('show');
						setTimeout("var table = $('#tblConfDptos').DataTable(); $('#contConfDpto').css('display', 'block'); table.columns.adjust().draw();", 150);
					}
				});
			};

			function cambiarTipoDptos(valor, dpto) {
				$.ajax({
					data: { opcion: "guardarTipoDptos", idpara: valor + '¬' + dpto},
					type: "POST",
					dataType: "json",
					url: "app/DBProcs.php",
					success : function(data) {
						result = data.split('¬');
						$('#msj').removeClass('alert-success alert-danger');
						$('#msj').html(result[1]);
						if(result[0] == 1) {
							$('#msj').addClass('alert-success');
							setTimeout("$('#msj').addClass('d-none')", 2000);
						} else {
							$('#msj').addClass('alert-danger');
						}
						$('#msj').removeClass('d-none');
					}
				});
			}

			function confTasas() {
				$.ajax({
					url: "app/DBProcs.php",
					data: { opcion: "listaconfTasas" },
					type: "POST",
					dataType: "json",
					success : function(data) {
						var contenido = '<table class="table table-hover table-striped w-100 p-0 m-0" id="tblconfTasas">'
							+'<thead>'
							+	'<th width="50%" class="bg-dark text-center">Fecha</th>'
							+	'<th width="50%" class="bg-dark text-center">Tasa USD</th>'
							+'</thead>';
							+'<tbody>'
						$.each(data, function() {
							contenido += '<tr class="text-center"><td><span class="d-none">';
							contenido += moment(this.fecha, 'DD-MM-YYYY').format('YYYYMMDD');
							contenido += '</span>' + this.fecha + '</td><td>' + this.tasa +'</td>';
							contenido += '</td></tr>';
						})
						contenido += '</tbody>'
							+'</table>'
							+'<script>'
								+'$("#tblconfTasas").dataTable({ '
									+"scrollY: '250px', "
									+"iDisplayLength: 7, "
									+"oSearch: {'sSearch': '" + ($('#fechausd').val()).substring(3) + "'},"
									+"order: [[ 0, 'desc' ]], "
									+"paging: true,"
									+"searching: true,"
								+'});'
							+'</' + 'script>'
						$('#divconfTasa').html(contenido);
						$('#confTasa').modal('show');
						setTimeout("var table = $('#tblconfTasas').DataTable(); $('#confTasa').css('display', 'block'); table.columns.adjust().draw();", 150);
					}
				});
			};

			function guardarTasaDolar(fecha, valor) {
				$.ajax({
					data: { opcion: "guardarTasaDolar", idpara: valor + '¬' + fecha},
					type: "POST",
					dataType: "json",
					url: "app/DBProcs.php",
					success : function(data) {
						result = data.split('¬');
						$('#msjusd').removeClass('alert-success alert-danger');
						$('#msjusd').html(result[1]);
						if(result[0] == 1) {
							$('#msjusd').addClass('alert-success');
							setTimeout("$('#msjusd').addClass('d-none')", 2000);
							confTasas();
						} else {
							$('#msjusd').addClass('alert-danger');
						}
						$('#msjusd').removeClass('d-none');
					}
				});
			}

			function cargarexportar() {
				$('#tituloModal2').html('Generar Archivos CSV Bi+ a TNS');
				$('#subtitulo').html('Seleccione Fecha y Tienda para generar la Información');
				$('#contenidoModal2').load('app/db_exportBiplus2TNS.html?t=' + moment().format("HH:mm:ss"))
				$('#ModalDatos2').modal('show');
			}

			function cargarReporteDian() {
				$('#tituloModal2').html('Generar Reporte para la DIAN');
				$('#subtitulo').html('Seleccione las Fechas para generar la Información');
				$('#contenidoModal2').load('app/db_reporteDIAN.html?t=' + moment().format("HH:mm:ss"))
				$('#ModalDatos2').modal('show');
			}

			function cargarReporteDane() {
				$('#tituloModal2').html('Generar Reporte para la DANE');
				$('#subtitulo').html('Seleccione la Fecha para generar la Información');
				$('#contenidoModal2').load('app/db_reporteDANE.html?t=' + moment().format("HH:mm:ss"))
				$('#ModalDatos2').modal('show');
			}

			function cargarReportePMA() {
				$('#tituloModal2').html('Exportar Consumos PMA');
				$('#subtitulo').html('Seleccione Fecha y Tienda para generar la Información');
				$('#contenidoModal2').load('app/db_reportes_pma.html?t=' + moment().format("HH:mm:ss"))
				$('#ModalDatos2').modal('show');
			}

			function facturas2PDFong() {
				$('#tituloModal2').html('Exportar Consumos PMA');
				$('#subtitulo').html('Seleccione Fecha y Tienda para generar la Información');
				$('#contenidoModal2').load('app/db_facturas_pdf_ong.html?t=' + moment().format("HH:mm:ss"))
				$('#ModalDatos2').modal('show');
			}

			function cargarReporteNielsen() {
				$('#tituloModal2').html('Generar Reporte para NIELSEN');
				$('#subtitulo').html('Seleccione las Fechas para generar la Información');
				$('#contenidoModal2').load('app/db_reporteNIELSEN.html?t=' + moment().format("HH:mm:ss"))
				$('#ModalDatos2').modal('show');
			}
		</script>
	</body>
</html>
<?php } ?>