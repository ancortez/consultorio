<?php
/**
 * Activa o desactiva un usuario. No se borra nunca: sus movimientos en
 * caja y sus consultas tienen que seguir teniendo autor.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.usuarios');

$id      = paramInt('id_usuario');
$estatus = (int) param('estatus', 1);

if (!$id) { Respuesta::error('Falta el identificador del usuario.'); }

if ($id === Auth::id() && $estatus === 0) {
    Respuesta::error('No puedes desactivar tu propia cuenta.');
}

// Debe quedar siempre al menos un administrador activo.
if ($estatus === 0) {
    $admins = (int) Conexion::valor(
        'SELECT COUNT(*) FROM usuario
          WHERE id_rol = 1 AND estatus = 1 AND id_usuario <> :id',
        [':id' => $id]
    );
    $esAdmin = (int) Conexion::valor(
        'SELECT id_rol FROM usuario WHERE id_usuario = :id', [':id' => $id]
    ) === 1;

    if ($esAdmin && $admins === 0) {
        Respuesta::error('Debe quedar al menos un administrador activo.');
    }
}

Conexion::consulta(
    'UPDATE usuario SET estatus = :e WHERE id_usuario = :id',
    [':e' => $estatus, ':id' => $id]
);

Bitacora::registrar('configuracion', $estatus === 1 ? 'ALTA' : 'BAJA',
    'usuario', (string) $id);

Respuesta::ok(null, $estatus === 1 ? 'Usuario activado.' : 'Usuario desactivado.');
