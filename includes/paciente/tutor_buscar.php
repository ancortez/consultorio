<?php
/**
 * Busca personas tipo Tutor ya registradas.
 * Permite reutilizar al mismo tutor en varios expedientes (hermanos).
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$nombres = (string) (paramMayus('nombres') ?? '');
$paterno = (string) (paramMayus('ap_paterno') ?? '');
$materno = (string) (paramMayus('ap_materno') ?? '');

if ($nombres === '' || $paterno === '') {
    Respuesta::validacion([
        'nombres'    => $nombres === '' ? 'Requerido' : null,
        'ap_paterno' => $paterno === '' ? 'Requerido' : null,
    ]);
}

$where  = ['p.id_tipo_persona = 2', 'p.estatus = 1',
           'p.nombres LIKE :n', 'p.ap_paterno LIKE :ap'];
$params = [':n' => '%' . $nombres . '%', ':ap' => '%' . $paterno . '%'];

if ($materno !== '') {
    $where[]       = 'p.ap_materno LIKE :am';
    $params[':am'] = '%' . $materno . '%';
}

Respuesta::ok(Conexion::todos(
    'SELECT p.id_persona, p.rfc,
            CONCAT_WS(" ", p.nombres, p.ap_paterno, p.ap_materno) AS nombre_completo
       FROM persona p
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY p.ap_paterno, p.nombres
      LIMIT 20',
    $params
));
