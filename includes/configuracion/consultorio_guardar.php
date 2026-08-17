<?php
/**
 * Guarda los datos del consultorio.
 *
 * El domicilio usa la misma tabla y la misma logica que el de los
 * pacientes: si el consultorio se muda, se corrige en un solo lugar y
 * el cambio se ve en el pie de pagina, en los tickets y en el
 * consentimiento impreso.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.consultorio');

$nombre      = limpiarEspacios(paramMayus('nombre_comercial'));
$razon       = limpiarEspacios(paramMayus('razon_social'));
$rfc         = paramMayus('rfc');
$telefono    = preg_replace('/[^\d]/', '', (string) param('telefono', ''));
$correo      = mb_strtolower(trim((string) param('correo', '')), 'UTF-8') ?: null;

// Domicilio
$idColonia   = paramInt('id_colonia');
$calle       = limpiarEspacios(paramMayus('calle'));
$numExt      = paramMayus('num_ext');
$numInt      = paramMayus('num_int');
$entre1      = limpiarEspacios(paramMayus('entre_calle_1'));
$entre2      = limpiarEspacios(paramMayus('entre_calle_2'));
$referencia  = limpiarEspacios(paramMayus('referencia'));

$errores = [];
if ($nombre === null || mb_strlen($nombre) < 3) {
    $errores['nombre_comercial'] = 'Captura el nombre del consultorio.';
}
if ($rfc !== null && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
    $errores['rfc'] = 'El RFC no tiene el formato correcto.';
}
if ($correo !== null && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores['correo'] = 'El correo no es valido.';
}
if ($telefono !== '' && strlen($telefono) < 10) {
    $errores['telefono'] = 'El telefono debe tener 10 digitos.';
}
if ($errores) { Respuesta::validacion($errores); }

$pdo = bd();
try {
    $pdo->beginTransaction();

    $actual = Conexion::uno(
        'SELECT id_domicilio FROM consultorio WHERE id_consultorio = 1');

    $idDomicilio = $actual['id_domicilio'] ?? null;

    // El domicilio solo se toca si eligieron colonia: sin ella no hay
    // domicilio que valga la pena guardar.
    if ($idColonia) {
        if ($idDomicilio) {
            Conexion::consulta(
                'UPDATE domicilio
                    SET id_colonia = :col, calle = :c, num_ext = :ne, num_int = :ni,
                        entre_calle_1 = :e1, entre_calle_2 = :e2, referencia = :ref
                  WHERE id_domicilio = :id',
                [':col' => $idColonia, ':c' => $calle, ':ne' => $numExt,
                 ':ni' => $numInt, ':e1' => $entre1, ':e2' => $entre2,
                 ':ref' => $referencia, ':id' => $idDomicilio]
            );
        } else {
            Conexion::consulta(
                'INSERT INTO domicilio
                    (id_colonia, calle, num_ext, num_int,
                     entre_calle_1, entre_calle_2, referencia)
                 VALUES (:col, :c, :ne, :ni, :e1, :e2, :ref)',
                [':col' => $idColonia, ':c' => $calle, ':ne' => $numExt,
                 ':ni' => $numInt, ':e1' => $entre1, ':e2' => $entre2,
                 ':ref' => $referencia]
            );
            $idDomicilio = Conexion::ultimoId();
        }
    }

    Conexion::consulta(
        'UPDATE consultorio
            SET nombre_comercial = :n, razon_social = :rs, rfc = :rfc,
                telefono = :tel, correo = :cor, id_domicilio = :dom
          WHERE id_consultorio = 1',
        [':n' => $nombre, ':rs' => $razon, ':rfc' => $rfc,
         ':tel' => $telefono ?: null, ':cor' => $correo, ':dom' => $idDomicilio]
    );

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[CONSULTORIO/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudieron guardar los datos del consultorio.', 500);
}

Bitacora::registrar('configuracion', 'CAMBIO', 'consultorio', '1',
    ['nombre' => $nombre]);

Respuesta::ok(null, 'Datos del consultorio guardados.');
