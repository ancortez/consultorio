<?php
/**
 * Datos necesarios para imprimir el consentimiento bajo informacion.
 *
 * Devuelve los tutores del expediente y sus domicilios para que la
 * pantalla pregunte a nombre de quien sale el documento cuando hay mas
 * de una opcion.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el expediente.');
}

$paciente = Conexion::uno(
    'SELECT vp.id_registro_paciente, vp.num_expediente, vp.nombre_completo,
            vp.edad, vp.fecha_nacim
       FROM v_paciente vp
      WHERE vp.id_registro_paciente = :r',
    [':r' => $idExpediente]
);

if ($paciente === null) {
    Respuesta::error('El paciente no existe.', 404);
}

// Tutores, con el responsable de pago primero: es el que por omision
// firma el consentimiento.
$tutores = Conexion::todos(
    'SELECT et.id_expediente_tutor, et.es_responsable_pago,
            p.id_persona,
            CONCAT_WS(" ", p.nombres, p.ap_paterno, p.ap_materno) AS nombre_completo,
            pa.nombre_parentesco
       FROM expediente_tutor et
       JOIN persona p ON p.id_persona = et.id_persona
       LEFT JOIN parentesco pa ON pa.id_parentesco = et.id_parentesco
      WHERE et.id_registro_paciente = :r AND et.estatus = 1
      ORDER BY et.es_responsable_pago DESC, et.id_expediente_tutor',
    [':r' => $idExpediente]
);

// Domicilios de cada tutor, el principal primero.
foreach ($tutores as &$t) {
    $t['domicilios'] = Conexion::todos(
        'SELECT vd.id_domicilio, vd.calle_numero, vd.colonia, vd.codigo_postal,
                vd.nombre_municipio, vd.nombre_estado
           FROM v_domicilio vd
          WHERE vd.id_persona = :p AND vd.estatus = 1
          ORDER BY vd.es_principal DESC, vd.id_domicilio',
        [':p' => $t['id_persona']]
    );
}
unset($t);

// Dentistas activos, para elegir quien firma como profesional.
$dentistas = Conexion::todos(
    'SELECT id_usuario, nombre_completo, cedula_prof
       FROM usuario
      WHERE es_dentista = 1 AND estatus = 1
      ORDER BY nombre_completo'
);

Respuesta::ok([
    'paciente'  => $paciente,
    'tutores'   => $tutores,
    'dentistas' => $dentistas,
    // Quien esta capturando, si resulta ser dentista, viene marcado.
    'yo_dentista' => Auth::usuario()['es_dentista'] ? Auth::id() : null,
]);
