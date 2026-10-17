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

/*
   El orden: una lista cerrada, no el texto que llegue.

   Lo que manda la pantalla es una CLAVE y aqui se traduce a SQL. Pegar
   el parametro en el ORDER BY seria una inyeccion de las de libro: el
   ORDER BY no admite marcadores de posicion, asi que no hay forma de
   mandarlo como dato y la unica defensa es que no se use nunca lo que
   venga de fuera.

   Los apellidos aparte de los nombres porque son dos busquedas
   distintas y las dos se hacen: "a ver los Guzman" ordena por apellido,
   y "¿como se llamaba el Mateo?" ordena por nombre. El nombre_completo
   empieza por los nombres, asi que ordenar por el es ordenar por
   nombre: para los apellidos hace falta su propia opcion.

   El desempate siempre acaba en el id, que es unico. Sin el, dos altas
   del mismo dia pueden salir en un orden distinto en cada consulta
   —MySQL no promete nada— y la lista "baila" al recargar sin que nada
   haya cambiado.
*/
const ORDENES = [
    'ultima_consulta' => 'vp.ultima_consulta',
    'alta'            => 'vp.fecha_alta',
    'nombres'         => 'vp.nombres',
    'apellidos'       => 'vp.ap_paterno',
];

$orden = (string) param('orden', 'ultima_consulta');
if (!isset(ORDENES[$orden])) {
    $orden = 'ultima_consulta';
}

// Las fechas arrancan de lo mas nuevo a lo mas viejo y los nombres de la
// A a la Z: es lo que uno espera de cada cosa sin tener que pedirlo.
$esFecha = in_array($orden, ['ultima_consulta', 'alta'], true);
$baja    = param('baja', $esFecha ? '1' : '0') === '1';

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

// El domicilio viaja partido en dos: la calle con su numero va en el
// primer renglon y con letra un poco mas grande, y la colonia y el resto
// en el segundo. Armarlo como una sola cadena obligaria al JavaScript a
// adivinar donde cortar.
$sql = 'SELECT vp.id_registro_paciente, vp.num_expediente, vp.id_persona,
               vp.nombre_completo, vp.edad, vp.telefono, vp.correo,
               vp.estatus, vp.fecha_alta, vp.ultima_consulta, vp.foto,
               (SELECT vd.calle_numero
                  FROM v_domicilio vd
                 WHERE vd.id_persona = vp.id_persona AND vd.estatus = 1
                 ORDER BY vd.es_principal DESC, vd.id_domicilio
                 LIMIT 1) AS domicilio_calle,
               (SELECT CONCAT_WS(", ", vd.colonia,
                                 CONCAT("C.P. ", vd.codigo_postal),
                                 vd.nombre_municipio, vd.nombre_estado)
                  FROM v_domicilio vd
                 WHERE vd.id_persona = vp.id_persona AND vd.estatus = 1
                 ORDER BY vd.es_principal DESC, vd.id_domicilio
                 LIMIT 1) AS domicilio_resto
          FROM v_paciente vp';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

/*
   Los vacios SIEMPRE al final, ordene como ordene.

   "IS NULL" primero en el ORDER BY: en MySQL un NULL es mas chico que
   cualquier valor, asi que ordenando de la Z a la A los pacientes que
   nunca han venido se irian a la cabeza de la lista. Un paciente sin
   consultas no es "el mas reciente" ni "el mas antiguo": es el que no
   tiene ese dato, y su sitio es abajo en los dos sentidos.

   Solo pasa con las fechas —ultima_consulta puede ser NULL y
   fecha_apertura no—, pero la expresion se deja igual para las cuatro:
   una sola forma de armar el ORDER BY se lee de un golpe, y sobre una
   columna NOT NULL "IS NULL" vale 0 en todos los renglones y no cambia
   nada.
*/
$columna = ORDENES[$orden];
$sentido = $baja ? 'DESC' : 'ASC';

$sql .= " ORDER BY $columna IS NULL, $columna $sentido,
                   vp.id_registro_paciente DESC";

// Con filtro se permite ver mas resultados; sin filtro se respeta el tope.
$sql .= ' LIMIT ' . ($q !== '' ? 100 : $limite);

Respuesta::ok(Conexion::todos($sql, $params));
