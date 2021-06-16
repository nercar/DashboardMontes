$(document).ready(function(){
    $('#exportar').click(function(){
        var dataString=$('#form_consulta').serialize();
        
        $.ajax({
            type:'POST',
            url:"controllers/consultaController.php",
            data: dataString,
            dataType:'json'           
        }).done(function(data){
          // console.log(dataString); 
        //   alert(dataString);
        //  console.log(data);
        // alert(data);
            var $a = $("<a>");
            $a.attr("href",data.file);
            $("body").append($a);
            $a.attr("download","reporteConsultaXs.xlsx");
            $a[0].click();
            $a.remove();
        });
    });


  $.ajax({
        type: "GET",
        url: "controllers/cargarController.php",
        async: false,
        data: {funcion: "indexTipoCliente"},
        success: function (respuesta) {
            $('#tipocdiv #tipoc').html(respuesta).fadeIn();
        }
    });

   /* $.ajax({
        type: "GET",
        url: "controllers/cargarController.php",
        async: false,
        data: {funcion: "indexCliente"},
        success: function (respuesta) {
            $('#clientediv #cliente').html(respuesta).fadeIn();
        }
    });*/

   $.ajax({
        type: "GET",
        url: "controllers/cargarController.php",
        async: false,
        data: {funcion: "indexSucursal"},
        success: function (respuesta) {
            $('#sucursaldiv #sucursal').html(respuesta).fadeIn();
        }
    });

    $.ajax({
        type: "GET",
        url: "controllers/cargarController.php",
        async: false,
        data: {funcion: "indexProveedor"},
        success: function (respuesta) {
            $('#proveedordiv #proveedor').html(respuesta).fadeIn();
        }
    });

    $.ajax({
        type: "GET",
        url: "controllers/cargarController.php",
        async: false,
        data: {funcion: "indexDepartamento"},
        success: function (respuesta) {
            $('#departamentodiv #departamento').html(respuesta).fadeIn();
        }
    });

    $.ajax({
        type: "GET",
        url: "controllers/cargarController.php",
        async: false,
        data: {funcion: "indexGrupo"},
        success: function (respuesta) {
            $('#grupodiv #grupo').html(respuesta).fadeIn();
        }
    });

    $.ajax({
        type: "GET",
        url: "controllers/cargarController.php",
        async: false,
        data: {funcion: "indexSubgrupo"},
        success: function (respuesta) {
            $('#subgrupodiv #subgrupo').html(respuesta).fadeIn();
        }
    });

   /* $.ajax({
        type: "GET",
        url: "controllers/cargarController.php",
        async: false,
        data: {funcion: "indexMaterial"},
        success: function (respuesta) {
            $('#materialdiv #material').html(respuesta).fadeIn();
        }
    });*/

    /*$('#agregar1').click(function(){
          var ced=$('#cedula1').val();
          $('#cliente').append('<option class="select2-selection__choice" value='+ced+' selected="selected">'+ced+'</option>');
      });*/

    $("#departamento").change( function() {
        if ($(this).val()!="") {
            $("#grupo").prop("disabled", true);
            $("#subgrupo").prop("disabled", true);
        }
        else
        {
            $("#grupo").prop("disabled", false);
            $("#subgrupo").prop("disabled", false); 
        }
    });

    $("#grupo").change( function() {
        if ($(this).val()!="") {
            $("#departamento").prop("disabled", true);
            $("#subgrupo").prop("disabled", true);
        } 
        else
            {
                $("#departamento").prop("disabled", false);
                $("#subgrupo").prop("disabled", false);
            }

    });

    $("#subgrupo").change( function() {
        if ($(this).val()!="") {
            $("#departamento").prop("disabled", true);
            $("#grupo").prop("disabled", true);
        }
        else
        {
            $("#departamento").prop("disabled", false);
            $("#grupo").prop("disabled", false);
        } 
    });


    $("#radioSalidad").change( function() {

            $("#camposdetallado").prop("disabled",false);
            $("#camposagrupado").prop("disabled",true);
    });
    
    $("#radioSalidaa").change( function() {
            $("#camposdetallado").prop("disabled",true);
            $("#camposagrupado").prop("disabled",false);
    });

    $("#rango_fecha").change( function() {
        if ($(this).val()!="") {
            $("#dia").prop("disabled", true);
            $("#mes").prop("disabled", true);
            $("#year").prop("disabled", true);
        }
        else
        {
            $("#dia").prop("disabled", false);
            $("#mes").prop("disabled", false);
            $("#year").prop("disabled", false);
        } 
    });

    $("#dia").change( function() {
        if ($(this).val()!="") {
            $("#rango_fecha").prop("disabled", true);
        }
        else
        {
            $("#rango_fecha").prop("disabled", false);
        } 
    });

    $("#mes").change( function() {
        if ($(this).val()!="") {
            $("#rango_fecha").prop("disabled", true);
        }
        else
        {
            $("#rango_fecha").prop("disabled", false);
        } 
    });

    $("#year").change( function() {
        if ($(this).val()!="") {
            $("#rango_fecha").prop("disabled", true);
        }
        else
        {
            $("#rango_fecha").prop("disabled", false);
        } 
    });



  });