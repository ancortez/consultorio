<?php
/**
 * Pone una condicion a varios dientes del paciente.
 *
 * Un solo endpoint para marcar y para quitar, porque es la misma
 * operacion: "estos dientes pasan a tener esto". Con id_condicion = 0
 * se quita la marca, que NO es lo mismo que ponerles "sano" —quitar
 * deja el diente sin nada dicho, y "sano" es una afirmacion de la
 * doctora—. Dos endpoints para eso serian dos sitios donde escribir la
 * misma transaccion.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idExpediente = paramInt('id_registro_paciente');
$idCondicion  = paramInt('id_condicion');
$nota         = paramTexto('nota');

// La lista llega como "16,17,26": es lo que arma la pantalla y lo que
// ya se usa para los dientes de un procedimiento del plan.
$dientes = array_filter(array_map(
    'intval', explode(',', (string) param('dientes', ''))));

if (!$idExpediente) {
    Respuesta::error('Falta el paciente.');
}
if (!$dientes) {
    Respuesta::validacion(['dientes' => 'Elige al menos un diente.']);
}

// El expediente tiene que existir: un id inventado crearia un
// odontograma colgando de nada y reventaria la llave foranea con un
// error de servidor en vez de un aviso.
$existe = Conexion::valor(
    'SELECT 1 FROM registro_paciente WHERE id_registro_paciente = :r',
    [':r' => $idExpediente]
);
if ($existe === null) {
    Respuesta::error('El paciente no existe.', 404);
}

/*
   El 0 significa "quitar". Cualquier otro valor tiene que estar en el
   catalogo: con un id que no existe, el INSERT revienta por la llave
   foranea y la pantalla recibe un error 500 que no explica nada.
*/
if ($idCondicion) {
    $valida = Conexion::valor(
        'SELECT 1 FROM condicion_dental WHERE id_condicion = :c',
        [':c' => $idCondicion]
    );
    if ($valida === null) {
        Respuesta::validacion(['id_condicion' => 'Esa condicion no existe.']);
    }
}

try {
    $cuantos = Odontograma::marcar(
        $idExpediente, $dientes, $idCondicion ?: null, $nota);
} catch (Throwable $e) {
    error_log('[ODONTOGRAMA/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar el odontograma.', 500);
}

Bitacora::registrar('paciente', 'CAMBIO', 'odontograma',
    (string) $idExpediente,
    ['dientes' => $cuantos, 'condicion' => $idCondicion]);

Respuesta::ok(
    [
        'estado'      => Odontograma::estadoDelPaciente($idExpediente),
        'condiciones' => Odontograma::condiciones(),
        'fecha'       => date('Y-m-d'),
    ],
    $idCondicion
        ? "Se marcaron $cuantos diente(s)."
        : "Se quito la marca de $cuantos diente(s)."
);
