<?php
/**
 * Lo que rodea a un plan: sus consultas, sus imagenes y sus notas.
 *
 * Son tres cosas que viven en tablas distintas pero que en la pantalla
 * son una sola idea: "¿que ha pasado con este plan?". Se devuelven
 * juntas porque la columna derecha del plan las dibuja de un tiron, y
 * partirlo en tres llamadas solo la haria parpadear tres veces.
 *
 * Solo tiene sentido en un plan guardado: un plan que no existe no
 * tiene consultas colgando.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$idPlan = paramInt('id_plan_tratamiento');
if (!$idPlan) {
    Respuesta::error('Falta el plan.');
}

// ---------------------------------------------------------------------
// Consultas del plan
// ---------------------------------------------------------------------
// Cada una trae de una vez cuantas imagenes y cuantas notas le cuelgan.
// Son subconsultas sobre indices, y sacarlas aqui evita tener que
// recorrer despues las otras dos listas para contar.
$consultas = Conexion::todos(
    'SELECT c.id_consulta, c.folio, c.fecha_hora, c.motivo, c.cerrada,
            u.nombre_completo AS dentista,
            (SELECT COUNT(*) FROM documento d
              WHERE d.id_consulta = c.id_consulta AND d.estatus = 1
                AND d.estado = 1) AS imagenes,
            (SELECT COUNT(*) FROM nota_evolucion ne
              WHERE ne.id_consulta = c.id_consulta AND ne.estatus = 1) AS notas,
            (SELECT COUNT(*) FROM hallazgo h
              WHERE h.id_consulta = c.id_consulta AND h.estatus = 1) AS hallazgos
       FROM consulta c
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
      WHERE c.id_plan_tratamiento = :p AND c.estatus = 1
      ORDER BY c.fecha_hora DESC',
    [':p' => $idPlan]
);

// ---------------------------------------------------------------------
// Album: fotos y radiografias, agrupadas por consulta
// ---------------------------------------------------------------------
// Solo lo que es imagen. Un PDF o un Word en el album no se puede
// mostrar como miniatura y romperia la cuadricula.
$archivos = Conexion::todos(
    "SELECT d.id_documento, d.descripcion, d.nombre_original, d.tipo_mime,
            d.fecha_digitalizacion, d.id_consulta,
            td.clave, td.nombre_documento,
            c.folio AS consulta_folio, c.fecha_hora AS consulta_fecha
       FROM documento d
       JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
       LEFT JOIN consulta c ON c.id_consulta = d.id_consulta
      WHERE d.id_plan_tratamiento = :p
        AND d.estatus = 1 AND d.estado = 1
        AND td.clave IN ('RADIOGRAFIA', 'FOTOGRAFIA')
        AND d.tipo_mime LIKE 'image/%'
      ORDER BY c.fecha_hora DESC, d.id_documento",
    [':p' => $idPlan]
);

// Se agrupa por consulta para que el album muestre "Consulta CN-0012,
// 8 elementos" y no una pila suelta de imagenes sin contexto. Lo que no
// cuelga de ninguna consulta (se subio al plan directo) cae en un grupo
// aparte, no se pierde.
$album = [];
foreach ($archivos as $a) {
    $clave = $a['id_consulta'] !== null ? (int) $a['id_consulta'] : 0;

    if (!isset($album[$clave])) {
        $album[$clave] = [
            'id_consulta' => $a['id_consulta'] !== null ? (int) $a['id_consulta'] : null,
            'folio'       => $a['consulta_folio'] ?? 'Del plan',
            'fecha'       => $a['consulta_fecha'],
            'elementos'   => [],
        ];
    }
    $album[$clave]['elementos'][] = $a;
}

// ---------------------------------------------------------------------
// Notas de evolucion de todas las consultas del plan
// ---------------------------------------------------------------------
$notas = Conexion::todos(
    'SELECT ne.id_nota_evolucion, ne.texto, ne.fecha_registro,
            c.id_consulta, c.folio AS consulta_folio,
            u.nombre_completo AS autor
       FROM nota_evolucion ne
       JOIN consulta c ON c.id_consulta = ne.id_consulta
       JOIN usuario u ON u.id_usuario = ne.id_usuario
      WHERE c.id_plan_tratamiento = :p
        AND ne.estatus = 1 AND c.estatus = 1
      ORDER BY ne.fecha_registro DESC',
    [':p' => $idPlan]
);

Respuesta::ok([
    'consultas' => $consultas,
    // array_values porque las llaves son ids de consulta y en JSON eso
    // llegaria como objeto en vez de lista.
    'album'     => array_values($album),
    'notas'     => $notas,
]);
