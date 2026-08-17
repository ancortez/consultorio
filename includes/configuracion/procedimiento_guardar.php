<?php
/**
 * Alta y modificacion de procedimientos dentales.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.procedimientos');

$id          = paramInt('id_procedimiento');
$idCategoria = paramInt('id_categoria');
$idTratamiento = paramInt('id_tratamiento');
$clave       = paramMayus('clave');
$nombre      = limpiarEspacios(paramMayus('nombre_procedimiento'));
$descripcion = limpiarEspacios(paramMayus('descripcion'));
$costo       = (float) str_replace(',', '', (string) param('costo_sugerido', '0'));
$duracion    = paramInt('duracion_min');
$porDiente   = param('aplica_por_diente') ? 1 : 0;
$anestesia   = param('requiere_anestesia') ? 1 : 0;
$estatus     = param('estatus') !== null ? (int) param('estatus') : 1;

$errores = [];
if ($nombre === null || mb_strlen($nombre) < 3) {
    $errores['nombre_procedimiento'] = 'Captura el nombre del procedimiento.';
}
if (!$idCategoria) {
    $errores['id_categoria'] = 'Selecciona la categoria.';
}
if ($costo < 0) {
    $errores['costo_sugerido'] = 'El costo no puede ser negativo.';
}
if ($duracion !== null && ($duracion < 5 || $duracion > 480)) {
    $errores['duracion_min'] = 'La duracion debe estar entre 5 y 480 minutos.';
}

$repetido = (int) Conexion::valor(
    'SELECT COUNT(*) FROM procedimiento
      WHERE nombre_procedimiento = :n AND id_procedimiento <> :id',
    [':n' => $nombre, ':id' => $id ?? 0]
);
if ($repetido > 0) {
    $errores['nombre_procedimiento'] = 'Ya existe un procedimiento con ese nombre.';
}

if ($errores) { Respuesta::validacion($errores); }

$datos = [
    ':cat'  => $idCategoria,
    ':trat' => $idTratamiento ?: null,
    ':cl'   => $clave,
    ':n'    => $nombre,
    ':d'    => $descripcion,
    ':c'    => $costo,
    ':dur'  => $duracion,
    ':pd'   => $porDiente,
    ':an'   => $anestesia,
    ':est'  => $estatus,
];

if ($id) {
    Conexion::consulta(
        'UPDATE procedimiento
            SET id_categoria = :cat, id_tratamiento = :trat, clave = :cl,
                nombre_procedimiento = :n, descripcion = :d,
                costo_sugerido = :c, duracion_min = :dur,
                aplica_por_diente = :pd, requiere_anestesia = :an,
                estatus = :est
          WHERE id_procedimiento = :id',
        array_merge($datos, [':id' => $id])
    );
    $accion = 'CAMBIO';
} else {
    Conexion::consulta(
        'INSERT INTO procedimiento
            (id_categoria, id_tratamiento, clave, nombre_procedimiento,
             descripcion, costo_sugerido, duracion_min,
             aplica_por_diente, requiere_anestesia, estatus)
         VALUES (:cat, :trat, :cl, :n, :d, :c, :dur, :pd, :an, :est)',
        $datos
    );
    $id = Conexion::ultimoId();
    $accion = 'ALTA';
}

Bitacora::registrar('configuracion', $accion, 'procedimiento', (string) $id,
    ['nombre' => $nombre, 'costo' => $costo]);

Respuesta::ok(['id_procedimiento' => $id], 'Procedimiento guardado.');
