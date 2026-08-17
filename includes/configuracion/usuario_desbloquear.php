<?php
/**
 * Quita el bloqueo por intentos fallidos.
 * Pasa seguido: alguien se equivoca cinco veces y hay que dejarlo entrar
 * sin esperar los 15 minutos.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.usuarios');

$id = paramInt('id_usuario');
if (!$id) { Respuesta::error('Falta el identificador del usuario.'); }

Conexion::consulta(
    'UPDATE usuario
        SET intentos_fallidos = 0, bloqueado_hasta = NULL
      WHERE id_usuario = :id',
    [':id' => $id]
);

Bitacora::registrar('configuracion', 'DESBLOQUEO', 'usuario', (string) $id);
Respuesta::ok(null, 'Usuario desbloqueado.');
