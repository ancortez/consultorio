<?php
/**
 * Vincula a una persona con un domicilio que ya existe.
 *
 * Caso tipico: la madre vive con el paciente. En lugar de capturar dos
 * veces la misma calle, se apunta al mismo registro de domicilio. Como
 * la relacion persona-domicilio es N:M, esto es solo un renglon mas en
 * persona_domicilio, y cualquier correccion se ve en los dos de
 * inmediato porque es el mismo domicilio, no una copia.
 *
 * El precio de esto es que modificarlo afecta a ambos. Por eso la
 * pantalla lo avisa en amarillo antes de dejar editar.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idPersona   = paramInt('id_persona');
$idDomicilio = paramInt('id_domicilio');

if (!$idPersona || !$idDomicilio) {
    Respuesta::error('Faltan datos para vincular el domicilio.');
}

// El domicilio tiene que existir y estar activo.
$existe = (int) Conexion::valor(
    'SELECT COUNT(*) FROM domicilio WHERE id_domicilio = :d AND estatus = 1',
    [':d' => $idDomicilio]
);
if ($existe === 0) {
    Respuesta::error('El domicilio no existe o esta dado de baja.', 404);
}

$yaVinculado = Conexion::uno(
    'SELECT id_persona_domicilio, estatus FROM persona_domicilio
      WHERE id_persona = :p AND id_domicilio = :d',
    [':p' => $idPersona, ':d' => $idDomicilio]
);

$pdo = bd();
try {
    $pdo->beginTransaction();

    if ($yaVinculado !== null) {
        // Estaba dado de baja: se reactiva en lugar de duplicar.
        Conexion::consulta(
            'UPDATE persona_domicilio SET estatus = 1
              WHERE id_persona_domicilio = :id',
            [':id' => $yaVinculado['id_persona_domicilio']]
        );
    } else {
        // Si es su primer domicilio, queda como principal.
        $tiene = (int) Conexion::valor(
            'SELECT COUNT(*) FROM persona_domicilio
              WHERE id_persona = :p AND estatus = 1',
            [':p' => $idPersona]
        );

        Conexion::consulta(
            'INSERT INTO persona_domicilio (id_persona, id_domicilio, es_principal)
             VALUES (:p, :d, :pr)',
            [':p' => $idPersona, ':d' => $idDomicilio, ':pr' => $tiene === 0 ? 1 : 0]
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[DOMICILIO/VINCULAR] ' . $e->getMessage());
    Respuesta::error('No se pudo vincular el domicilio.', 500);
}

Bitacora::registrar('paciente', 'VINCULO', 'persona_domicilio', (string) $idDomicilio,
    ['id_persona' => $idPersona]);

Respuesta::ok(['id_domicilio' => $idDomicilio], 'Domicilio vinculado.');
