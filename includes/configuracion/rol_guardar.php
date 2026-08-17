<?php
/**
 * Alta y modificacion de roles.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.usuarios');

$id     = paramInt('id_rol');
$nombre = limpiarEspacios(paramMayus('nombre_rol'));
$desc   = limpiarEspacios((string) param('descripcion', ''));

if ($nombre === null || mb_strlen($nombre) < 3) {
    Respuesta::validacion(['nombre_rol' => 'Captura al menos 3 letras.']);
}

$repetido = (int) Conexion::valor(
    'SELECT COUNT(*) FROM rol WHERE UPPER(nombre_rol) = :n AND id_rol <> :id',
    [':n' => $nombre, ':id' => $id ?? 0]
);
if ($repetido > 0) {
    Respuesta::validacion(['nombre_rol' => 'Ya existe un rol con ese nombre.']);
}

if ($id) {
    Conexion::consulta(
        'UPDATE rol SET nombre_rol = :n, descripcion = :d WHERE id_rol = :id',
        [':n' => $nombre, ':d' => $desc, ':id' => $id]
    );
    $accion = 'CAMBIO';
} else {
    Conexion::consulta(
        'INSERT INTO rol (nombre_rol, descripcion) VALUES (:n, :d)',
        [':n' => $nombre, ':d' => $desc]
    );
    $id = Conexion::ultimoId();
    $accion = 'ALTA';
}

Bitacora::registrar('configuracion', $accion, 'rol', (string) $id, ['nombre' => $nombre]);
Respuesta::ok(['id_rol' => $id], 'Rol guardado.');
