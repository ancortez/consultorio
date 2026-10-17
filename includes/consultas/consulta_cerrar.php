<?php
/**
 * Cierra una consulta.
 *
 * Cerrar es lo que convierte la captura en un registro clinico: a
 * partir de ahi no se edita. Por eso se exige que tenga lo minimo para
 * valer como tal.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idConsulta = paramInt('id_consulta');
if (!$idConsulta) {
    Respuesta::error('Falta la consulta.');
}

$c = Conexion::uno(
    'SELECT id_consulta, cerrada, motivo
       FROM consulta WHERE id_consulta = :c AND estatus = 1',
    [':c' => $idConsulta]
);

if ($c === null) {
    Respuesta::error('La consulta no existe.', 404);
}
if ((int) $c['cerrada'] === 1) {
    Respuesta::error('Esta consulta ya estaba cerrada.');
}

/*
   Una consulta cerrada tiene que decir algo mas que a que vino.

   Antes se exigia el campo "tratamiento realizado", que ya no existe:
   lo que se hizo quedo repartido entre los hallazgos y las notas de
   evolucion. Asi que la regla se mantiene y cambia de sitio: para
   cerrar hace falta al menos UNA de las dos cosas.

   No es burocracia. Una consulta con solo el motivo —"1RA CITA PLAN DE
   TRATAMIENTO PT-008"— y nada mas no sirve de registro clinico: en la
   siguiente cita no dice que se encontro ni que se hizo, y ante quien
   pregunte dentro de dos anios, tampoco.

   Una sola de las dos, no las dos: una limpieza sin ningun hallazgo se
   cierra con una nota, y una revision que solo encontro cosas no
   necesita ademas un parrafo repitiendolas.
*/
$registro = (int) Conexion::valor(
    'SELECT (SELECT COUNT(*) FROM hallazgo h
              WHERE h.id_consulta = :c1 AND h.estatus = 1)
         + (SELECT COUNT(*) FROM nota_evolucion n
              WHERE n.id_consulta = :c2 AND n.estatus = 1)',
    [':c1' => $idConsulta, ':c2' => $idConsulta]
);

if ($registro === 0) {
    Respuesta::error(
        'Antes de cerrar, deja por escrito que paso en la consulta: '
        . 'agrega al menos un hallazgo en la exploracion o una nota de '
        . 'evolucion. Con solo el motivo, la consulta no sirve como '
        . 'registro clinico.');
}

Conexion::consulta(
    'UPDATE consulta SET cerrada = 1, fecha_cierre = NOW()
      WHERE id_consulta = :c',
    [':c' => $idConsulta]
);

Bitacora::registrar('consultas', 'CIERRA', 'consulta', (string) $idConsulta);

Respuesta::ok(['id_consulta' => $idConsulta], 'Consulta cerrada.');
