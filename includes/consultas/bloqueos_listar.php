<?php
/**
 * Bloqueos de un rango de fechas.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$desde = trim((string) param('desde', date('Y-m-d')));
$hasta = trim((string) param('hasta', date('Y-m-d', strtotime('+60 days'))));
$idDentista = paramInt('id_usuario_dentista') ?: null;

Respuesta::ok(AgendaExtra::bloqueos($desde, $hasta, $idDentista));
