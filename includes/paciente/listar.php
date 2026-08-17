<?php
/**
 * Lista de pacientes para la vista buscar_paciente.
 *
 * Sin filtro devuelve los ultimos N pacientes atendidos (ordenados por
 * fecha de ultima consulta, no por fecha de alta: "atendidos" significa
 * que estuvieron aqui, no que se registraron).
 *
 * Con filtro busca por nombre, apellidos, telefono, correo y expediente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$q       = trim((string) param('q', ''));
$estatus = param('estatus', '1');
$limite  = (int) parametro('pacientes_lista_inicio', (string) PACIENTES_INICIO);

$where  = [];
$params = [];

if ($estatus === '0' || $estatus === '1') {
    $where[]           = 'vp.estatus = :estatus';
    $params[':estatus'] = (int) $estatus;
}

if ($q !== '') {
    // Se busca en el nombre completo concatenado para que "juan perez"
    // encuentre a Juan Perez aunque nombre y apellido esten en columnas
    // distintas.
    //
    // OJO: cada LIKE lleva su propio parametro (:q1, :q2...). Con
    // EMULATE_PREPARES en false, MySQL no acepta que el mismo nombre de
    // parametro aparezca dos veces en la consulta: truena con
    // "Invalid parameter number". Repetir el valor es la forma correcta.
    $where[] = '(vp.nombre_completo LIKE :q1
                 OR vp.num_expediente LIKE :q2
                 OR vp.telefono       LIKE :q3
                 OR vp.correo         LIKE :q4)';
    $comodin = '%' . $q . '%';
    $params[':q1'] = $comodin;
    $params[':q2'] = $comodin;
    $params[':q3'] = $comodin;
    $params[':q4'] = $comodin;
}

$sql = 'SELECT vp.id_registro_paciente, vp.num_expediente, vp.id_persona,
               vp.nombre_completo, vp.edad, vp.telefono, vp.correo,
               vp.estatus, vp.ultima_consulta,
               (SELECT CONCAT_WS(", ", vd.calle_numero, vd.colonia,
                                 vd.codigo_postal, vd.nombre_municipio,
                                 vd.nombre_estado)
                  FROM v_domicilio vd
                 WHERE vd.id_persona = vp.id_persona AND vd.estatus = 1
                 ORDER BY vd.es_principal DESC, vd.id_domicilio
                 LIMIT 1) AS domicilio
          FROM v_paciente vp';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

// Los que tienen consulta reciente primero; los que nunca han venido al final.
$sql .= ' ORDER BY vp.ultima_consulta IS NULL, vp.ultima_consulta DESC,
                   vp.id_registro_paciente DESC';

// Con filtro se permite ver mas resultados; sin filtro se respeta el tope.
$sql .= ' LIMIT ' . ($q !== '' ? 100 : $limite);

Respuesta::ok(Conexion::todos($sql, $params));
