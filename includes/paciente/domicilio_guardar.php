<?php
/**
 * Alta o modificacion del domicilio de una persona.
 *
 * Si viene id_domicilio: modifica el existente.
 * Si no: crea uno nuevo y lo vincula a la persona.
 *
 * MEJORA: como el domicilio ahora es N:M con persona, un mismo domicilio
 * puede compartirse (padre e hijo que viven juntos) sin duplicarlo.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idDomicilio = paramInt('id_domicilio');
$idPersona   = paramInt('id_persona');
$idColonia   = paramInt('id_colonia');
// Domicilio completo en MAYUSCULAS.
$calle       = limpiarEspacios(paramMayus('calle'));
$numExt      = paramMayus('num_ext');
$numInt      = paramMayus('num_int');
$entre1      = limpiarEspacios(paramMayus('entre_calle_1'));
$entre2      = limpiarEspacios(paramMayus('entre_calle_2'));
$referencia  = limpiarEspacios(paramMayus('referencia'));

$errores = [];
if (!$idPersona) { Respuesta::error('Falta el identificador de la persona.'); }

/*
   Un domicilio por persona.

   Antes se podian registrar varios y en la practica eso producia dos
   problemas: nadie sabia cual era el bueno al llamar por telefono, y
   el domicilio "principal" se elegia por el orden de captura y no por
   decision de nadie.

   Un paciente vive en una casa. Si se muda, se CORRIGE la que hay; el
   historial de donde vivio antes no es informacion que este consultorio
   necesite.

   Los domicilios que ya existan de antes no se tocan: el bloqueo es
   para altas nuevas. Si alguien tiene dos capturados, los dos siguen
   ahi y se pueden editar.
*/
if (!$idDomicilio) {
    $yaTiene = (int) Conexion::valor(
        'SELECT COUNT(*) FROM persona_domicilio pd
           JOIN domicilio d ON d.id_domicilio = pd.id_domicilio
          WHERE pd.id_persona = :p AND d.estatus = 1',
        [':p' => $idPersona]
    );
    if ($yaTiene > 0) {
        Respuesta::error(
            'Esta persona ya tiene un domicilio registrado. '
            . 'Modifica el que existe en lugar de agregar otro.');
    }
}
if (!$idColonia) { $errores['id_colonia'] = 'Selecciona la colonia.'; }
if ($calle === null) { $errores['calle'] = 'Captura la calle.'; }
if ($errores) { Respuesta::validacion($errores); }

$pdo = bd();
try {
    $pdo->beginTransaction();

    if ($idDomicilio) {
        // Solo se permite modificar un domicilio ligado a esta persona.
        $propio = (int) Conexion::valor(
            'SELECT COUNT(*) FROM persona_domicilio
              WHERE id_domicilio = :d AND id_persona = :p',
            [':d' => $idDomicilio, ':p' => $idPersona]
        );
        if ($propio === 0) {
            $pdo->rollBack();
            Respuesta::error('El domicilio no pertenece a este paciente.', 403);
        }

        Conexion::consulta(
            'UPDATE domicilio
                SET id_colonia = :col, calle = :c, num_ext = :ne, num_int = :ni,
                    entre_calle_1 = :e1, entre_calle_2 = :e2, referencia = :ref
              WHERE id_domicilio = :id',
            [':col' => $idColonia, ':c' => $calle, ':ne' => $numExt, ':ni' => $numInt,
             ':e1' => $entre1, ':e2' => $entre2, ':ref' => $referencia,
             ':id' => $idDomicilio]
        );
        $accion = 'CAMBIO';
    } else {
        Conexion::consulta(
            'INSERT INTO domicilio
                (id_colonia, calle, num_ext, num_int, entre_calle_1, entre_calle_2, referencia)
             VALUES (:col, :c, :ne, :ni, :e1, :e2, :ref)',
            [':col' => $idColonia, ':c' => $calle, ':ne' => $numExt, ':ni' => $numInt,
             ':e1' => $entre1, ':e2' => $entre2, ':ref' => $referencia]
        );
        $idDomicilio = Conexion::ultimoId();

        // El primer domicilio de la persona queda como principal.
        $existen = (int) Conexion::valor(
            'SELECT COUNT(*) FROM persona_domicilio
              WHERE id_persona = :p AND estatus = 1',
            [':p' => $idPersona]
        );

        Conexion::consulta(
            'INSERT INTO persona_domicilio (id_persona, id_domicilio, es_principal)
             VALUES (:p, :d, :pr)',
            [':p' => $idPersona, ':d' => $idDomicilio, ':pr' => $existen === 0 ? 1 : 0]
        );
        $accion = 'ALTA';
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[DOMICILIO/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar el domicilio.', 500);
}

Bitacora::registrar('paciente', $accion, 'domicilio', (string) $idDomicilio);
Respuesta::ok(['id_domicilio' => $idDomicilio], 'Domicilio guardado.');
