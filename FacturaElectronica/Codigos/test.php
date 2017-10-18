<?php
# Ejemplo de Uso de Interface COM con Web Services AFIP (PyAfipWs) para PHP
# WSFEv1 2.5 (factura electr�nica mercado interno sin detalle -r�gimen general-)
# RG2485 RG2485/08 RG2757/10 RG2904/10 RG3067/11 RG3571/13 RG3668/14 RG3749/15
# 2015 (C) Mariano Reingart <reingart@gmail.com> licencia AGPLv3+
#
# Documentaci�n:
#  * http://www.sistemasagiles.com.ar/trac/wiki/ProyectoWSFEv1
#  * http://www.sistemasagiles.com.ar/trac/wiki/ManualPyAfipWs
#
# Instalaci�n: agregar en el php.ini las siguientes lineas (sin #)
# [COM_DOT_NET] 
# extension=ext\php_com_dotnet.dll 

$HOMO = true;   # homologaci�n (testing / pruebas) o producci�n
$CACHE = "";    # directorio para archivos temporales (usar por defecto)

try {
	error_reporting(E_ALL);
	# Crear objeto interface Web Service Autenticaci�n y Autorizaci�n
	$WSAA = new COM("WSAA") or die("Unable to instantiate application object");
	# Generar un Ticket de Requerimiento de Acceso (TRA)
	$tra = $WSAA->CreateTRA() ;
	
	# Especificar la ubicacion de los archivos certificado y clave privada
	$path = getcwd()  . "\\";
	# Certificado: certificado es el firmado por la AFIP
	# ClavePrivada: la clave privada usada para crear el certificado
	$Certificado = "reingart.crt"; // certificado de prueba
	$ClavePrivada = "reingart.key"; // clave privada de prueba
	# Generar el mensaje firmado (CMS) ;
	$cms = $WSAA->SignTRA($tra, $path . $Certificado, $path . $ClavePrivada);

    # iniciar la conexi�n al webservice de autenticaci�n
    if ($HOMO)
        $wsdl = "https://wsaahomo.afip.gov.ar/ws/services/LoginCms";
    else
        $wsdl = "https://wsaa.afip.gov.ar/ws/services/LoginCms"; # producci�n
	$ok = $WSAA->Conectar($CACHE, $wsdl);
	
	# Llamar al web service para autenticar
	$ta = $WSAA->LoginCMS($cms);
	
	echo "Token de Acceso: $WSAA->Token \n";
	echo "Sing de Acceso: $WSAA->Sign \n";

	
	# Crear objeto interface Web Service de Factura Electr�nica v1 (version 2.5)
	$WSFEv1 = new COM('WSFEv1');
	# Setear tocken y sing de autorizaci�n (pasos previos) Y CUIT del emisor
	$WSFEv1->Token = $WSAA->Token;
	$WSFEv1->Sign = $WSAA->Sign; 
	$WSFEv1->Cuit = "20267565393";
	
	# Conectar al Servicio Web de Facturaci�n: homologaci�n testing o producci�n
	if ($HOMO)
    	$wsdl = "https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL";	
	else
    	$wsdl = "https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL"; 
	$ok = $WSFEv1->Conectar($CACHE, $wsdl); // pruebas
	#$ok = WSFE.Conectar() ' producci�n # producci�n
	
	# Llamo a un servicio nulo, para obtener el estado del servidor (opcional)
	$WSFEv1->Dummy();
	echo "appserver status $WSFEv1->AppServerStatus \n";
	echo "dbserver status $WSFEv1->DbServerStatus \n";
	echo "authserver status $WSFEv1->AuthServerStatus \n";
		
	# Recupero �ltimo n�mero de comprobante para un punto venta/tipo (opcional)
	$tipo_cbte = 1; $punto_vta = 1;
	$ult = $WSFEv1->CompUltimoAutorizado($tipo_cbte, $punto_vta);
	
	# Establezco los valores de la factura o lote a autorizar:
	$fecha = date("Ymd");
	echo "Fecha $fecha \n";
	$concepto = 1;                  # 1: productos, 2: servicios, 3: ambos
	$tipo_doc = 80;                 # 80: CUIT, 96: DNI, 99: Consumidor Final
	$nro_doc = "23111111113";       # 0 para Consumidor Final (<$1000)
	$cbt_desde = $ult + 1; 
	$cbt_hasta = $ult + 1;
    $imp_total = "179.25";          # total del comprobante
    $imp_tot_conc = "2.00";         # subtotal de conceptos no gravados
    $imp_neto = "150.00";           # subtotal neto sujeto a IVA
    $imp_iva = "26.25";             # subtotal impuesto IVA liquidado
    $imp_trib = "1.00";             # subtotal otros impuestos
    $imp_op_ex = "0.00";            # subtotal de operaciones exentas
    $fecha_cbte = $fecha;
    $fecha_venc_pago = "";          # solo servicios
    # Fechas del per�odo del servicio facturado (solo si concepto = 1?)
    $fecha_serv_desde = "";
    $fecha_serv_hasta = "";
    $moneda_id = "PES";             # no utilizar DOL u otra moneda 
    $moneda_ctz = "1.000";          # (deshabilitado por AFIP)
	
	# Inicializo la factura interna con los datos de la cabecera
	$ok = $WSFEv1->CrearFactura($concepto, $tipo_doc, $nro_doc, 
	    $tipo_cbte, $punto_vta, $cbt_desde, $cbt_hasta, 
	    $imp_total, $imp_tot_conc, $imp_neto, $imp_iva, $imp_trib, $imp_op_ex,
	    $fecha_cbte, $fecha_venc_pago, $fecha_serv_desde, $fecha_serv_hasta,
        $moneda_id, $moneda_ctz);
        
    # Agrego los comprobantes asociados (solo para notas de cr�dito y d�bito):
    if (false) {
        $tipo = 19;
        $pto_vta = 2;
        $nro = 1234;
        $ok = $WSFEv1->AgregarCmpAsoc($tipo, $pto_vta, $nro);
    }
        
    # Agrego impuestos varios
    $tributo_id = 99;
    $ds = "Impuesto Municipal Matanza'";
    $base_imp = "100.00";
    $alic = "0.10";
    $importe = "0.10";
    $ok = $WSFEv1->AgregarTributo($tributo_id, $ds, $base_imp, $alic, $importe);

    # Agrego impuestos varios
    $tributo_id = 4;
    $ds = "Impuestos internos";
    $base_imp = "100.00";
    $alic = "0.40";
    $importe = "0.40";
    $ok = $WSFEv1->AgregarTributo($tributo_id, $ds, $base_imp, $alic, $importe);

    # Agrego impuestos varios
    $tributo_id = 1;
    $ds = "Impuesto nacional";
    $base_imp = "50.00";
    $alic = "1.00";
    $importe = "0.50";
    $ok = $WSFEv1->AgregarTributo($tributo_id, $ds, $base_imp, $alic, $importe);

    # Agrego tasas de IVA
    $iva_id = 5;             # 21%
    $base_imp = "100.00";
    $importe = "21.00";
    $ok = $WSFEv1->AgregarIva($iva_id, $base_imp, $importe);
    
    # Agrego tasas de IVA 
    $iva_id = 4;            # 10.5%  
    $base_imp = "50.00";
    $importe = "5.25";
    $ok = $WSFEv1->AgregarIva($iva_id, $base_imp, $importe);
    
    # Agrego datos opcionales  RG 3668 Impuesto al Valor Agregado - Art.12 
    # ("presunci�n no vinculaci�n la actividad gravada", F.8001):
    if ($tipo_cbte == 1) {  # solo para facturas A
        # IVA Excepciones (01: Locador/Prestador, 02: Conferencias, 03: RG 74, 04: Bienes de cambio, 05: Ropa de trabajo, 06: Intermediario).
        $ok = $WSFEv1->AgregarOpcional(5, "02");
        # Firmante Doc Tipo (80: CUIT, 96: DNI, etc.)
        $ok = $WSFEv1->AgregarOpcional(61, "80");
        # Firmante Doc Nro:
        $ok = $WSFEv1->AgregarOpcional(62, "20267565393");
        # Car�cter del Firmante (01: Titular, 02: Director/Presidente, 03: Apoderado, 04: Empleado)
        $ok = $WSFEv1->AgregarOpcional(7, "01");
    }
    # proximamente m�s valores opcionales para RG 3749/2015
    
    # Habilito reprocesamiento autom�tico (predeterminado):
    $WSFEv1->Reprocesar = true;
        
	# Llamo al WebService de Autorizaci�n para obtener el CAE
	$cae = $WSFEv1->CAESolicitar();
	
	echo "Resultado=$WSFEv1->Resultado \n";
	echo "Nro CBTE=$WSFEv1->CbteNro \n";
	echo "CAE=$cae \n";
	echo "Vencimiento $WSFEv1->Vencimiento"; # Fecha de vto. de la autorizaci�n
	echo "Tipo Emision=$WSFEv1->EmisionTipo\n";	
	echo "Reproceso=$WSFEv1->Reproceso\n";
	echo "Errores=$WSFEv1->ErrMsg\n";
	
	# Verifico que no haya rechazo o advertencia al generar el CAE
	if ($cae=="") {
		echo "La p�gina esta caida o la respuesta es inv�lida\n";
	} elseif ($cae=="NULL" || $WSFEv1->Resultado!="A") {
		echo "No se asign� CAE (Rechazado). Motivos: $WSFEv1->Motivo \n";
	} elseif ($WSFEv1->Obs!="") {
		echo "Se asign� CAE pero con advertencias. Motivos: $WSFEv1->Obs \n";
	} 

} catch (Exception $e) {
	echo 'Excepci�n: ',  $e->getMessage(), "\n";
	if (isset($WSAA)) {
	    echo "WSAA.Excepcion: $WSAA->Excepcion \n";
	    echo "WSAA.Traceback: $WSAA->Traceback \n";
	}
	if (isset($WSFEv1)) {
	    echo "WSFEv1.Excepcion: $WSFEv1->Excepcion \n";
	    echo "WSFEv1.Traceback: $WSFEv1->Traceback \n";
	}
}
if (isset($WSFEv1)) {
    # almacenar la respuesta para depuraci�n / testing
    # (guardar en un directorio no descargable al subir a un servidor web)
    file_put_contents("request.xml", $WSFEv1->XmlRequest);
    file_put_contents("response.xml", $WSFEv1->XmlResponse);
}

?>