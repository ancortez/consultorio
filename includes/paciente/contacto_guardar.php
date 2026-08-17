<?php
/**
 * Alta o modificacion de un canal de contacto.
 * Si viene id_contacto_persona modifica; si no, inserta.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idContacto = paramInt('id_contacto_persona');
$idPersona  = paramInt('id_persona');
$idMedio    = paramInt('id_medio_contacto');
$valor      = trim((string) param('valor_contacto', ''));
$principal  = param('es_principal') ? 1 : 0;

if (!$idPersona)   { Respuesta::error('Falta el identificador de la persona.'); }
if ($valor === '') { Respuesta::validacion(['valor_contacto' => 'Captura el valor.']); }

// Validacion segun el tipo de dato del medio de contacto.
if ($idMedio) {
    $tipo = Conexion::valor(
        'SELECT tipo_dato FROM medio_contacto WHERE id_medio_contacto = :m',
        [':m' => $idMedio]
    );

    if ($tipo === 'tel') {
        $soloDigitos = preg_replace('/\D/', '', $valor);
        if (strlen($soloDigitos) < 10) {
            Respuesta::validacion(['valor_contacto' => 'El telefono debe tener 10 digitos.']);
        }
        $valor = $soloDigitos;
    }

    if ($tipo === 'email') {
        // El correo se guarda en minusculas: es lo estandar y evita
        // duplicados del tipo Juan@x.com y juan@x.com.
        $valor = mb_strtolower($valor, 'UTF-8');
        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            Respuesta::validacion(['valor_contacto' => 'El correo no es valido.']);
        }
    }

    // Lo demas (Facebook, contacto de emergencia) va en MAYUSCULAS.
    if ($tipo === 'texto') {
        $valor = mayus($valor);
    }
}

$pdo = bd();
try {
    $pdo->beginTransaction();

    // El primer contacto de la persona queda como principal aunque no
    // lo hayan marcado: alguna forma de localizarla tiene que destacar.
    $existen = (int) Conexion::valor(
        'SELECT COUNT(*) FROM contacto_persona
          WHERE id_persona = :p AND estatus = 1 AND id_contacto_persona <> :id',
        [':p' => $idPersona, ':id' => $idContacto ?? 0]
    );
    if ($existen === 0) {
        $principal = 1;
    }

    if ($idContacto) {
        $sql = 'UPDATE contacto_persona
                   SET valor_contacto = :v, es_principal = :pr';
        $params = [':v' => $valor, ':pr' => $principal,
                   ':id' => $idContacto, ':p' => $idPersona];

        // El medio solo se cambia si lo mandaron.
        if ($idMedio) {
            $sql .= ', id_medio_contacto = :m';
            $params[':m'] = $idMedio;
        }

        $sql .= ' WHERE id_contacto_persona = :id AND id_persona = :p';
        Conexion::consulta($sql, $params);
        $accion = 'CAMBIO';
    } else {
        Conexion::consulta(
            'INSERT INTO contacto_persona
                (id_persona, id_medio_contacto, valor_contacto, es_principal)
             VALUES (:p, :m, :v, :pr)',
            [':p' => $idPersona, ':m' => $idMedio, ':v' => $valor, ':pr' => $principal]
        );
        $idContacto = Conexion::ultimoId();
        $accion = 'ALTA';
    }

    // Solo puede haber un principal por persona.
    if ($principal === 1) {
        Conexion::consulta(
            'UPDATE contacto_persona SET es_principal = 0
              WHERE id_persona = :p AND id_contacto_persona <> :id',
            [':p' => $idPersona, ':id' => $idContacto]
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[CONTACTO/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar el contacto.', 500);
}

Bitacora::registrar('paciente', $accion, 'contacto_persona', (string) $idContacto);
Respuesta::ok(['id_contacto_persona' => $idContacto], 'Contacto guardado.');
