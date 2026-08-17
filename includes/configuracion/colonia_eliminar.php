<?php
/**
 * Borra una colonia, pero solo si ningun domicilio la usa.
 *
 * Aqui si es borrado real, a diferencia de los datos de pacientes: una
 * colonia es catalogo, no historia clinica. Aun asi, si algun domicilio
 * la referencia no se toca, porque dejaria domicilios sin colonia.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.colonias');

$id = paramInt('id_colonia');
if (!$id) { Respuesta::error('Falta el identificador de la colonia.'); }

$enUso = (int) Conexion::valor(
    'SELECT COUNT(*) FROM domicilio WHERE id_colonia = :c', [':c' => $id]
);

if ($enUso > 0) {
    Respuesta::error(
        "No se puede eliminar: hay $enUso domicilio(s) registrados en esta colonia. "
        . 'Puedes corregir el nombre en lugar de borrarla.'
    );
}

$colonia = Conexion::uno(
    'SELECT nombre_colonia, codigo_postal FROM colonia WHERE id_colonia = :c',
    [':c' => $id]
);

Conexion::consulta('DELETE FROM colonia WHERE id_colonia = :c', [':c' => $id]);

Bitacora::registrar('configuracion', 'BAJA', 'colonia', (string) $id, $colonia);
Respuesta::ok(null, 'Colonia eliminada.');
