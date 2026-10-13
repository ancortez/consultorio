<?php
/**
 * Lo que debe un paciente: cargos abiertos y planes con saldo.
 *
 * Es lo que la pantalla de cobro necesita para armar el reparto.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.ver');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el paciente.');
}

$paciente = Conexion::uno(
    'SELECT id_registro_paciente, nombre_completo, num_expediente, edad,
            id_persona, foto, telefono
       FROM v_paciente WHERE id_registro_paciente = :r',
    [':r' => $idExpediente]
);
if ($paciente === null) {
    Respuesta::error('El paciente no existe.', 404);
}

Respuesta::ok([
    'paciente' => $paciente,
    'cargos'   => Cargos::pendientes($idExpediente),
    'planes'   => Conexion::todos(
        'SELECT vps.*, pt.descripcion, ep.nombre_estado
           FROM v_plan_saldo vps
           JOIN plan_tratamiento pt
                ON pt.id_plan_tratamiento = vps.id_plan_tratamiento
           JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
          WHERE vps.id_registro_paciente = :r
          ORDER BY pt.fecha DESC',
        [':r' => $idExpediente]),
    // Del mismo sitio que el estado de cuenta impreso. Antes salia de
    // v_paciente_saldo directo, que suma cargos y por lo tanto no sabe
    // del dinero a favor: la cajera no veia que el paciente ya tenia un
    // anticipo y le volvia a cobrar completo.
    'resumen'  => Cargos::estadoDeCuenta($idExpediente)['resumen'],

    /*
       Las fichas que ya se le cobraron.

       Lo pidio el consultorio y la razon es de mostrador: el tutor
       llega diciendo "ya le pague eso" y hasta ahora la unica forma de
       comprobarlo era imprimir el estado de cuenta completo o buscar el
       cobro en el corte del dia en que se hizo. Con la lista aqui
       mismo, el recibo se reimprime en dos clics.

       Van los cancelados tambien, marcados: "no aparece mi pago" se
       contesta mucho mejor con "aqui esta, y se cancelo este dia por
       esto" que con un hueco en la lista.

       Veinte alcanzan. Un paciente de ortodoncia con tres anios de
       mensualidades pasa de treinta, y para eso esta el estado de
       cuenta, que es el documento que si los lista todos.
    */
    'pagos'    => Conexion::todos(
        'SELECT p.id_pago, p.folio, p.monto, p.fecha_hora, p.estatus,
                p.motivo_cancelacion, p.fecha_cancelacion,
                u.nombre_completo AS cobro,
                pt.folio AS plan_folio,
                COALESCE((SELECT SUM(pa.monto) FROM pago_aplicacion pa
                           WHERE pa.aplicada_en_pago = p.id_pago), 0)
                    AS a_favor_aplicado,
                (SELECT GROUP_CONCAT(fp.nombre_forma
                            ORDER BY fp.nombre_forma SEPARATOR ", ")
                   FROM pago_forma pf
                   JOIN forma_pago fp ON fp.id_forma_pago = pf.id_forma_pago
                  WHERE pf.id_pago = p.id_pago) AS formas
           FROM pago p
           JOIN usuario u ON u.id_usuario = p.id_usuario
           LEFT JOIN plan_tratamiento pt
                  ON pt.id_plan_tratamiento = p.id_plan_tratamiento
          WHERE p.id_registro_paciente = :r
          ORDER BY p.fecha_hora DESC, p.id_pago DESC
          LIMIT 20',
        [':r' => $idExpediente]),
]);
