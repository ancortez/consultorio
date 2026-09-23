<?php
/**
 * Lista de consultas.
 *
 * Por omision cada quien ve las suyas. Con el permiso consultas.todas
 * (recepcion y administracion) se ven las de todo el consultorio: son
 * quienes necesitan saber que paso en el dia, no solo en su sillon.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$q            = trim((string) param('q', ''));
$idExpediente = paramInt('id_registro_paciente');
$idDentista   = paramInt('id_usuario_dentista');
$limite       = min(200, max(10, paramInt('limite') ?: 50));

$where  = ['c.estatus = 1'];
$params = [];

// Quien no puede ver las de todos queda amarrado a las suyas, sin
// importar lo que mande el navegador.
if (!Auth::puede('consultas.todas')) {
    $idDentista = (int) Auth::id();
}

if ($idDentista) {
    $where[]         = 'c.id_usuario_dentista = :d';
    $params[':d']    = $idDentista;
}

if ($idExpediente) {
    $where[]      = 'c.id_registro_paciente = :r';
    $params[':r'] = $idExpediente;
}

if ($q !== '') {
    // Cada LIKE con su propio parametro: con EMULATE_PREPARES en false
    // MySQL no acepta el mismo nombre repetido.
    $where[] = '(c.folio LIKE :q1 OR vp.nombre_completo LIKE :q2
                 OR c.motivo LIKE :q3 OR vp.num_expediente LIKE :q4
                 OR pt.folio LIKE :q5)';
    $comodin = '%' . $q . '%';
    foreach (['q1','q2','q3','q4','q5'] as $n) {
        $params[':' . $n] = $comodin;
    }
}

$lista = Conexion::todos(
    'SELECT c.id_consulta, c.folio, c.fecha_hora, c.motivo, c.diagnostico,
            c.cerrada, c.id_registro_paciente, c.id_plan_tratamiento,
            vp.nombre_completo AS paciente, vp.num_expediente, vp.edad,
            u.nombre_completo AS dentista,
            pt.folio AS plan_folio, pt.descripcion AS plan_descripcion,
            (SELECT COUNT(*) FROM documento d
              WHERE d.id_consulta = c.id_consulta AND d.estatus = 1) AS documentos,
            (SELECT COUNT(*) FROM documento d
              WHERE d.id_consulta = c.id_consulta AND d.estatus = 1
                AND d.estado = 0) AS pendientes,
            (SELECT COUNT(*) FROM nota_evolucion ne
              WHERE ne.id_consulta = c.id_consulta AND ne.estatus = 1) AS notas
       FROM consulta c
       JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
       LEFT JOIN plan_tratamiento pt
              ON pt.id_plan_tratamiento = c.id_plan_tratamiento
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY c.fecha_hora DESC, c.id_consulta DESC
      LIMIT ' . $limite,
    $params
);

Respuesta::ok([
    'consultas' => $lista,
    'todas'     => Auth::puede('consultas.todas'),
]);
