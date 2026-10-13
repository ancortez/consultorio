<?php
/**
 * Nota de evolucion.
 *
 * No genera pendiente de digitalizacion: se escribe en el sistema y ahi
 * se queda, no hay papel que escanear. Tampoco se edita ni se borra
 * despues, por lo mismo que una consulta cerrada no se edita: es parte
 * del registro clinico y se le agrega, no se le corrige.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idConsulta = paramInt('id_consulta');
$texto      = paramTexto('texto');

if (!$idConsulta) {
    Respuesta::error('Falta la consulta.');
}
if ($texto === null || mb_strlen($texto) < 5) {
    Respuesta::validacion(['texto' => 'Escribe la nota.']);
}

$existe = (int) Conexion::valor(
    'SELECT COUNT(*) FROM consulta WHERE id_consulta = :c AND estatus = 1',
    [':c' => $idConsulta]
);
if ($existe === 0) {
    Respuesta::error('La consulta no existe.', 404);
}

Conexion::consulta(
    'INSERT INTO nota_evolucion (id_consulta, texto, id_usuario)
          VALUES (:c, :t, :u)',
    [':c' => $idConsulta, ':t' => $texto, ':u' => Auth::id()]
);

$id = Conexion::ultimoId();

Bitacora::registrar('consultas', 'ALTA', 'nota_evolucion', (string) $id,
    ['consulta' => $idConsulta]);

Respuesta::ok(['id_nota_evolucion' => $id], 'Nota agregada.');
