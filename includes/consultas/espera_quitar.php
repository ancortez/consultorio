<?php
/**
 * Saca a alguien de la lista de espera.
 *
 * No se borra el renglon: se marca. Sirve para saber cuanta gente
 * quedo esperando y nunca se agendo, que es informacion util.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.agenda');

$id     = paramInt('id_espera');
$idCita = paramInt('id_cita') ?: null;

if (!$id) {
    Respuesta::error('Falta el registro.');
}

// 2 = ya se agendo | 0 = se dio de baja sin agendar
$estado = $idCita !== null ? 2 : 0;

Conexion::consulta(
    'UPDATE lista_espera SET estado = :e, id_cita = :c WHERE id_espera = :i',
    [':e' => $estado, ':c' => $idCita, ':i' => $id]
);

Bitacora::registrar('consultas', 'BAJA', 'lista_espera', (string) $id,
    ['cita' => $idCita]);

Respuesta::ok(['id_espera' => $id],
    $idCita ? 'Agendado y quitado de la lista.' : 'Quitado de la lista.');
