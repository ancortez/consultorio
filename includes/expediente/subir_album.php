<?php
/**
 * Sube varios archivos de golpe a la consulta.
 *
 * Las radiografias y las fotografias clinicas nunca vienen de una en
 * una: de una cita salen ocho fotos intraorales. Obligar a crear el
 * pendiente y subir archivo por archivo son dieciseis clics para algo
 * que es un solo gesto.
 *
 * Cada archivo se convierte en su propio documento ya digitalizado: no
 * pasa por el estado pendiente porque el archivo ya esta aqui.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idExpediente = paramInt('id_registro_paciente');
$idConsulta   = paramInt('id_consulta') ?: null;
$idPlan       = paramInt('id_plan_tratamiento') ?: null;
$clave        = (string) param('clave', '');
$descripcion  = limpiarEspacios(paramMayus('descripcion'));

if (!$idExpediente || $clave === '') {
    Respuesta::error('Faltan datos para guardar los archivos.');
}

if (empty($_FILES['archivos']) || !is_array($_FILES['archivos']['name'])) {
    Respuesta::validacion(['archivos' => 'Selecciona al menos un archivo.']);
}

$total = count($_FILES['archivos']['name']);
if ($total > 20) {
    Respuesta::error('Son demasiados archivos de una vez. Sube maximo 20.');
}

$guardados = [];
$fallidos  = [];

for ($i = 0; $i < $total; $i++) {
    if ((int) $_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) {
        $fallidos[] = [
            'nombre' => $_FILES['archivos']['name'][$i],
            'motivo' => 'No se pudo recibir el archivo.',
        ];
        continue;
    }

    // Se rearma como un $_FILES de un solo archivo, que es lo que
    // Documentos::digitalizar sabe recibir.
    $archivo = [
        'name'     => $_FILES['archivos']['name'][$i],
        'type'     => $_FILES['archivos']['type'][$i],
        'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
        'error'    => $_FILES['archivos']['error'][$i],
        'size'     => $_FILES['archivos']['size'][$i],
    ];

    // Cada archivo lleva su propia descripcion numerada. Sin esto, diez
    // radiografias del mismo dia son diez renglones identicos.
    $suDescripcion = $descripcion !== null && $descripcion !== ''
        ? ($total > 1 ? $descripcion . ' (' . ($i + 1) . ')' : $descripcion)
        : mb_strtoupper(mb_substr($archivo['name'], 0, 120), 'UTF-8');

    $idDocumento = Documentos::crearPendiente(
        $idExpediente, $clave, $suDescripcion, null, null, $idPlan, $idConsulta);

    if ($idDocumento === null) {
        $fallidos[] = [
            'nombre' => $archivo['name'],
            'motivo' => 'Ya existe un documento de ese tipo.',
        ];
        continue;
    }

    try {
        Documentos::digitalizar($idDocumento, $archivo);
        $guardados[] = $idDocumento;
    } catch (Throwable $e) {
        // El pendiente vacio no se queda ahi ensuciando el expediente.
        Conexion::consulta(
            'DELETE FROM documento WHERE id_documento = :d AND estado = 0',
            [':d' => $idDocumento]
        );
        $fallidos[] = [
            'nombre' => $archivo['name'],
            'motivo' => $e->getMessage(),
        ];
    }
}

if (!$guardados) {
    Respuesta::error('No se pudo guardar ningun archivo. '
        . ($fallidos[0]['motivo'] ?? ''), 500);
}

Respuesta::ok(
    ['guardados' => count($guardados), 'fallidos' => $fallidos],
    count($fallidos)
        ? count($guardados) . ' archivo(s) guardado(s), ' . count($fallidos) . ' con problema.'
        : count($guardados) . ' archivo(s) guardado(s).'
);
