<?php
/**
 * El odontograma del paciente: el estado de cada diente y el catalogo.
 *
 * La pestania ya llega dibujada desde PHP, asi que esto no hace falta
 * para verla. Existe para volver a pintarla despues de guardar sin
 * recargar la pagina, y para que cualquier otra pantalla pueda pedir el
 * estado sin repetir la consulta.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$idExpediente = paramInt('id_registro_paciente');

if (!$idExpediente) {
    Respuesta::error('Falta el paciente.');
}

Respuesta::ok([
    'estado'      => Odontograma::estadoDelPaciente($idExpediente),
    'condiciones' => Odontograma::condiciones(),
]);
