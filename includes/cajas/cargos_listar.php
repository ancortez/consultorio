<?php
/**
 * Cargos de un paciente, de una consulta o de todo el consultorio.
 *
 * Sin paciente devuelve los pendientes de todos, que es lo que el
 * tablero de inicio le muestra a recepcion: a quien hay que cobrarle.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.ver');

$idExpediente = paramInt('id_registro_paciente');
$idConsulta   = paramInt('id_consulta');
$soloSaldo    = param('con_saldo') !== '0';

if ($idConsulta) {
    Respuesta::ok(Conexion::todos(
        'SELECT vcs.*, c.cantidad, c.precio_unitario, c.descuento,
                c.motivo_descuento, u.nombre_completo AS registro,
                ud.nombre_completo AS autorizo_descuento
           FROM v_cargo_saldo vcs
           JOIN cargo c ON c.id_cargo = vcs.id_cargo
           JOIN usuario u ON u.id_usuario = c.id_usuario
           LEFT JOIN usuario ud ON ud.id_usuario = c.id_usuario_descuento
          WHERE vcs.id_consulta = :c
          ORDER BY vcs.id_cargo',
        [':c' => $idConsulta]
    ));
}

if ($idExpediente) {
    Respuesta::ok(Cargos::pendientes($idExpediente));
}

// Vista de recepcion: quien debe, cuanto y desde cuando.
$where = $soloSaldo ? 'WHERE vps.saldo > 0' : '';

Respuesta::ok(Conexion::todos(
    "SELECT vps.*, vp.nombre_completo AS paciente, vp.num_expediente,
            vp.telefono, vp.id_persona, vp.foto,
            DATEDIFF(CURDATE(), vps.adeudo_mas_antiguo) AS dias
       FROM v_paciente_saldo vps
       JOIN v_paciente vp ON vp.id_registro_paciente = vps.id_registro_paciente
       $where
      ORDER BY vps.adeudo_mas_antiguo, vps.saldo DESC
      LIMIT 100"
));
