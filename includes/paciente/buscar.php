<?php
/**
 * Busqueda previa al alta: detecta si el paciente ya existe.
 * Se compara solo por nombre y apellidos, que es lo unico que se pide
 * en ese primer modal.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$nombres = (string) (paramMayus('nombres') ?? '');
$paterno = (string) (paramMayus('ap_paterno') ?? '');
$materno = (string) (paramMayus('ap_materno') ?? '');

if ($nombres === '' || $paterno === '') {
    Respuesta::validacion([
        'nombres'    => $nombres === '' ? 'Requerido' : null,
        'ap_paterno' => $paterno === '' ? 'Requerido' : null,
    ]);
}

$where  = ['p.nombres LIKE :n', 'p.ap_paterno LIKE :ap'];
$params = [':n' => '%' . $nombres . '%', ':ap' => '%' . $paterno . '%'];

if ($materno !== '') {
    $where[]        = 'p.ap_materno LIKE :am';
    $params[':am']  = '%' . $materno . '%';
}

$sql = 'SELECT vp.id_registro_paciente, vp.num_expediente, vp.id_persona,
               vp.nombre_completo, vp.edad, vp.telefono
          FROM persona p
          JOIN v_paciente vp ON vp.id_persona = p.id_persona
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY p.ap_paterno, p.ap_materno, p.nombres
         LIMIT 20';

Respuesta::ok(Conexion::todos($sql, $params));
