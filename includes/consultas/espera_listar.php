<?php
/**
 * Lista de espera. Con inicio y dentista, solo quienes encajan en ese
 * hueco: es lo que se pregunta cuando se cancela una cita.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$inicio     = trim((string) param('inicio', ''));
$idDentista = paramInt('id_usuario_dentista') ?: null;

if ($inicio !== '' && $idDentista) {
    Respuesta::ok(AgendaExtra::candidatos(
        str_replace('T', ' ', $inicio), $idDentista));
}

Respuesta::ok(AgendaExtra::espera($idDentista));
