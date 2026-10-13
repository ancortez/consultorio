<?php
/**
 * Agrega un paciente a la lista de espera.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.agenda');

$idExpediente = paramInt('id_registro_paciente');
$idDentista   = paramInt('id_usuario_dentista') ?: null;
$motivo       = limpiarEspacios(paramMayus('motivo'));
$preferencia  = paramInt('preferencia_horario') ?: 3;
$duracion     = paramInt('duracion_min') ?: 30;
$notas        = paramTexto('notas');

if (!$idExpediente) {
    Respuesta::error('Selecciona al paciente.');
}
if (!in_array($preferencia, [1, 2, 3], true)) { $preferencia = 3; }

// El mismo paciente dos veces en la lista solo genera confusion.
$ya = (int) Conexion::valor(
    'SELECT COUNT(*) FROM lista_espera
      WHERE id_registro_paciente = :r AND estado = 1',
    [':r' => $idExpediente]
);
if ($ya > 0) {
    Respuesta::error('Este paciente ya esta en la lista de espera.');
}

Conexion::consulta(
    'INSERT INTO lista_espera
        (id_registro_paciente, id_usuario_dentista, motivo,
         preferencia_horario, duracion_min, notas, id_usuario_registro)
     VALUES (:r, :d, :m, :p, :du, :n, :u)',
    [':r' => $idExpediente, ':d' => $idDentista, ':m' => $motivo,
     ':p' => $preferencia, ':du' => $duracion, ':n' => $notas,
     ':u' => Auth::id()]
);

$id = Conexion::ultimoId();

Bitacora::registrar('consultas', 'ALTA', 'lista_espera', (string) $id,
    ['expediente' => $idExpediente]);

Respuesta::ok(['id_espera' => $id], 'Agregado a la lista de espera.');
