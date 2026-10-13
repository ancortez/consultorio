<?php
/**
 * Quita UNA imagen de un hallazgo.
 *
 * Hasta ahora las imagenes de un hallazgo solo se podian mirar: el
 * campo de archivo agrega, y no habia forma de deshacer una foto subida
 * por error —la del paciente equivocado, o una que salio movida— sin
 * irse al expediente a buscarla entre todas las del dia.
 *
 * Aqui SI se borra de verdad, a diferencia de casi todo lo demas del
 * sistema. Una foto equivocada no es un dato clinico que haya que
 * conservar: es basura que estorba, y dejarla "dada de baja pero ahi"
 * ocupa espacio en el almacen sin que nadie la vuelva a ver. Lo que no
 * se borra nunca es el HALLAZGO, que si es la observacion clinica.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idDocumento = paramInt('id_documento');
if (!$idDocumento) { Respuesta::error('Falta la imagen.'); }

/*
   Que de verdad sea la imagen de un hallazgo.

   Sin esta comprobacion, este endpoint borraria CUALQUIER documento del
   expediente con solo mandarle un numero: el consentimiento firmado, la
   credencial del tutor, la hoja del plan. El id viaja desde el
   navegador y el navegador no es de fiar.
*/
$doc = Conexion::uno(
    'SELECT d.id_documento, d.id_hallazgo, d.id_consulta,
            d.nombre_original
       FROM documento d
      WHERE d.id_documento = :d AND d.estatus = 1',
    [':d' => $idDocumento]
);

if ($doc === null) {
    Respuesta::error('Esa imagen no existe o ya se habia quitado.');
}

if (empty($doc['id_hallazgo'])) {
    Respuesta::error('Esa imagen no pertenece a un hallazgo. '
                   . 'Para quitarla, usa el expediente electronico.');
}

Documentos::quitarArchivo($idDocumento);

// quitarArchivo deja el documento como "pendiente de escanear", que es
// lo correcto para un papel que hay que volver a subir. Para la foto de
// un hallazgo no: nadie pidio esa foto, se agrego por gusto. Se da de
// baja el renglon entero para que no quede un pendiente fantasma
// pidiendo una imagen que nadie espera.
Conexion::consulta(
    'UPDATE documento SET estatus = 0 WHERE id_documento = :d',
    [':d' => $idDocumento]
);

Bitacora::registrar('consultas', 'BAJA', 'documento', (string) $idDocumento,
    ['hallazgo' => (int) $doc['id_hallazgo'],
     'archivo'  => $doc['nombre_original']]);

Respuesta::ok(null, 'Imagen quitada.');
