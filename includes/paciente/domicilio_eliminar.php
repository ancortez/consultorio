<?php
/**
 * Baja logica del domicilio (estatus = 0), no borrado fisico.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idDomicilio = paramInt('id_domicilio');
$idPersona   = paramInt('id_persona');
if (!$idDomicilio || !$idPersona) {
    Respuesta::error('Faltan datos para eliminar el domicilio.');
}

$pdo = bd();
try {
    $pdo->beginTransaction();

    Conexion::consulta(
        'UPDATE persona_domicilio SET estatus = 0
          WHERE id_domicilio = :d AND id_persona = :p',
        [':d' => $idDomicilio, ':p' => $idPersona]
    );

    // Si ningun otro registro lo usa, se marca de baja tambien el domicilio.
    $enUso = (int) Conexion::valor(
        'SELECT COUNT(*) FROM persona_domicilio
          WHERE id_domicilio = :d AND estatus = 1',
        [':d' => $idDomicilio]
    );
    if ($enUso === 0) {
        Conexion::consulta(
            'UPDATE domicilio SET estatus = 0 WHERE id_domicilio = :d',
            [':d' => $idDomicilio]
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[DOMICILIO/ELIMINAR] ' . $e->getMessage());
    Respuesta::error('No se pudo eliminar el domicilio.', 500);
}

Bitacora::registrar('paciente', 'BAJA', 'domicilio', (string) $idDomicilio);
Respuesta::ok(null, 'Domicilio eliminado.');
