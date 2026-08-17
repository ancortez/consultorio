<?php
/**
 * Historia clinica de un paciente, con sus casillas marcadas.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el expediente.');
}

$historia = Conexion::uno(
    'SELECT hc.*, u.nombre_completo AS capturo
       FROM historia_clinica hc
       LEFT JOIN usuario u ON u.id_usuario = hc.id_usuario_captura
      WHERE hc.id_registro_paciente = :r',
    [':r' => $idExpediente]
);

$marcas = [];
if ($historia !== null) {
    $marcas = Conexion::todos(
        'SELECT cc.clave, hm.detalle
           FROM historia_marca hm
           JOIN catalogo_clinico cc
             ON cc.id_catalogo_clinico = hm.id_catalogo_clinico
          WHERE hm.id_historia_clinica = :h',
        [':h' => $historia['id_historia_clinica']]
    );
}

// Alergias y antecedentes que ya estan en el expediente: se muestran
// como recordatorio para no capturarlos dos veces.
$antecedentes = Conexion::todos(
    'SELECT a.nombre_antecedente, ta.nombre_tipo, ea.detalle, a.requiere_alerta
       FROM expediente_antecedente ea
       JOIN antecedente a ON a.id_antecedente = ea.id_antecedente
       JOIN tipo_antecedente ta ON ta.id_tipo_antecedente = a.id_tipo_antecedente
      WHERE ea.id_registro_paciente = :r AND ea.estatus = 1
      ORDER BY a.requiere_alerta DESC, ta.nombre_tipo',
    [':r' => $idExpediente]
);

Respuesta::ok([
    'historia'     => $historia,
    'marcas'       => array_column($marcas, 'detalle', 'clave'),
    'claves'       => array_column($marcas, 'clave'),
    'antecedentes' => $antecedentes,
]);
