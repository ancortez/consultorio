<?php
/**
 * Quita un hallazgo de la consulta.
 *
 * No se borra: cambia de estatus. Un hallazgo es parte del registro
 * clinico de un dia, y el registro clinico no se borra ni cuando quien
 * lo escribio se arrepiente. Si se capturo por error, deja de verse;
 * si alguien necesita saber que estuvo ahi, sigue estando.
 *
 * La imagen tampoco se borra. Queda en el expediente como un documento
 * mas: el archivo existe, se subio, y perderlo por deshacer un renglon
 * de texto seria desproporcionado.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idHallazgo = paramInt('id_hallazgo');
if (!$idHallazgo) {
    Respuesta::error('Falta el hallazgo.');
}

$h = Conexion::uno(
    'SELECT h.id_hallazgo, h.id_consulta, c.cerrada
       FROM hallazgo h
       JOIN consulta c ON c.id_consulta = h.id_consulta
      WHERE h.id_hallazgo = :h AND h.estatus = 1',
    [':h' => $idHallazgo]
);

if ($h === null) {
    Respuesta::error('El hallazgo no existe.', 404);
}
if ((int) $h['cerrada'] === 1) {
    Respuesta::error(
        'Esta consulta ya esta cerrada y sus hallazgos no se modifican.');
}

Conexion::consulta(
    'UPDATE hallazgo SET estatus = 0 WHERE id_hallazgo = :h',
    [':h' => $idHallazgo]
);

Bitacora::registrar('consultas', 'BAJA', 'hallazgo', (string) $idHallazgo,
    ['consulta' => (int) $h['id_consulta']]);

Respuesta::ok([], 'Hallazgo quitado.');
