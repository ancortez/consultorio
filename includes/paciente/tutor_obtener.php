<?php
/**
 * Devuelve todos los datos de un tutor para llenar el modal de
 * modificacion: identificacion, contactos y domicilios.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$idVinculo = paramInt('id_expediente_tutor');
if (!$idVinculo) {
    Respuesta::error('Falta el identificador del tutor.');
}

$tutor = Conexion::uno(
    'SELECT et.id_expediente_tutor, et.id_registro_paciente,
            et.id_parentesco, et.es_responsable_pago,
            p.id_persona, p.nombres, p.ap_paterno, p.ap_materno,
            p.fecha_nacim, p.id_genero, p.rfc, p.curp,
            p.id_ocupacion, o.nombre_ocupacion
       FROM expediente_tutor et
       JOIN persona p ON p.id_persona = et.id_persona
       LEFT JOIN ocupacion o ON o.id_ocupacion = p.id_ocupacion
      WHERE et.id_expediente_tutor = :id',
    [':id' => $idVinculo]
);

if ($tutor === null) {
    Respuesta::error('El tutor no existe.', 404);
}

// Identificacion oficial vigente (la ultima capturada).
$tutor['identificacion'] = Conexion::uno(
    'SELECT pi.*, ti.nombre_identificacion, ti.tiene_vigencia
       FROM persona_identificacion pi
       JOIN tipo_identificacion ti
         ON ti.id_tipo_identificacion = pi.id_tipo_identificacion
      WHERE pi.id_persona = :p AND pi.estatus = 1
      ORDER BY pi.id_persona_identificacion DESC
      LIMIT 1',
    [':p' => $tutor['id_persona']]
);

$tutor['contactos'] = Conexion::todos(
    'SELECT cp.id_contacto_persona, cp.id_medio_contacto, cp.valor_contacto,
            cp.es_principal, mc.nombre_medio_contacto, mc.tipo_dato
       FROM contacto_persona cp
       JOIN medio_contacto mc ON mc.id_medio_contacto = cp.id_medio_contacto
      WHERE cp.id_persona = :p AND cp.estatus = 1
      ORDER BY cp.es_principal DESC, cp.id_contacto_persona',
    [':p' => $tutor['id_persona']]
);

// Cada domicilio trae cuantas personas lo comparten. Con eso la pantalla
// sabe si debe advertir que un cambio afecta tambien al paciente.
$tutor['domicilios'] = Conexion::todos(
    'SELECT vd.*,
            (SELECT COUNT(*) FROM persona_domicilio pd
              WHERE pd.id_domicilio = vd.id_domicilio AND pd.estatus = 1) AS personas,
            EXISTS(SELECT 1 FROM persona_domicilio pd2
                     JOIN registro_paciente rp2 ON rp2.id_persona = pd2.id_persona
                    WHERE pd2.id_domicilio = vd.id_domicilio
                      AND pd2.estatus = 1
                      AND rp2.id_registro_paciente = :r) AS compartido_con_paciente
       FROM v_domicilio vd
      WHERE vd.id_persona = :p AND vd.estatus = 1
      ORDER BY vd.es_principal DESC, vd.id_domicilio',
    [':p' => $tutor['id_persona'], ':r' => $tutor['id_registro_paciente']]
);

// Domicilio principal del paciente, para ofrecer vincularlo cuando el
// tutor todavia no tiene ninguno.
$tutor['domicilio_paciente'] = Conexion::uno(
    'SELECT vd.id_domicilio, vd.calle_numero, vd.colonia, vd.codigo_postal,
            vd.nombre_municipio, vd.nombre_estado, vd.referencia
       FROM registro_paciente rp
       JOIN v_domicilio vd ON vd.id_persona = rp.id_persona
      WHERE rp.id_registro_paciente = :r AND vd.estatus = 1
      ORDER BY vd.es_principal DESC, vd.id_domicilio
      LIMIT 1',
    [':r' => $tutor['id_registro_paciente']]
);

Respuesta::ok($tutor);
