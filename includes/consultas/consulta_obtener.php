<?php
/**
 * Una consulta con todo lo que cuelga de ella: documentos, recetas y
 * notas de evolucion.
 *
 * Se devuelve todo junto porque la pantalla necesita las cuatro cosas
 * para dibujarse; partirlo en cuatro llamadas solo la haria parpadear.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$idConsulta = paramInt('id_consulta');
if (!$idConsulta) {
    Respuesta::error('Falta la consulta.');
}

$consulta = Conexion::uno(
    'SELECT c.*, vp.nombre_completo AS paciente, vp.num_expediente, vp.edad,
            u.nombre_completo AS dentista,
            pt.folio AS plan_folio, pt.descripcion AS plan_descripcion
       FROM consulta c
       JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
       LEFT JOIN plan_tratamiento pt
              ON pt.id_plan_tratamiento = c.id_plan_tratamiento
      WHERE c.id_consulta = :c AND c.estatus = 1',
    [':c' => $idConsulta]
);

if ($consulta === null) {
    Respuesta::error('La consulta no existe.', 404);
}

$consulta['documentos'] = Conexion::todos(
    'SELECT d.*, td.clave, td.nombre_documento, td.icono, td.formatos,
            us.nombre_completo AS subio
       FROM documento d
       JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
       LEFT JOIN usuario us ON us.id_usuario = d.id_usuario_sube
      WHERE d.id_consulta = :c AND d.estatus = 1
      ORDER BY td.orden, d.id_documento',
    [':c' => $idConsulta]
);

$consulta['recetas'] = Conexion::todos(
    'SELECT r.*, u.nombre_completo AS dentista
       FROM receta r
       JOIN usuario u ON u.id_usuario = r.id_usuario_dentista
      WHERE r.id_consulta = :c AND r.estatus = 1
      ORDER BY r.id_receta DESC',
    [':c' => $idConsulta]
);

$consulta['notas'] = Conexion::todos(
    'SELECT ne.id_nota_evolucion, ne.texto, ne.fecha_registro,
            u.nombre_completo AS autor
       FROM nota_evolucion ne
       JOIN usuario u ON u.id_usuario = ne.id_usuario
      WHERE ne.id_consulta = :c AND ne.estatus = 1
      ORDER BY ne.fecha_registro DESC',
    [':c' => $idConsulta]
);

$consulta['tipos'] = Documentos::tipos('CONSULTA');

Respuesta::ok($consulta);
