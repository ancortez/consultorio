<?php
/**
 * Reemplaza los permisos de un rol por los que se marcaron.
 * Se hace en una transaccion: borrar y volver a insertar es lo mas
 * simple de razonar, pero si falla a medias el rol se queda sin nada.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.usuarios');

$idRol = paramInt('id_rol');
if (!$idRol) { Respuesta::error('Falta el rol.'); }

// El rol Administrador tiene todo por definicion (Auth::puede lo
// resuelve sin consultar). Editarlo daria una falsa sensacion de control.
if ($idRol === 1) {
    Respuesta::error('El rol Administrador siempre tiene todos los permisos.');
}

$permisos = param('permisos', '');
$ids = array_values(array_filter(
    array_map('intval', explode(',', (string) $permisos)),
    static fn ($v) => $v > 0
));

$pdo = bd();
try {
    $pdo->beginTransaction();

    Conexion::consulta('DELETE FROM rol_permiso WHERE id_rol = :r', [':r' => $idRol]);

    if ($ids) {
        // Solo se aceptan ids que existan de verdad en el catalogo.
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "INSERT INTO rol_permiso (id_rol, id_permiso)
             SELECT ?, id_permiso FROM permiso WHERE id_permiso IN ($marcas)"
        );
        $stmt->execute(array_merge([$idRol], $ids));
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[PERMISOS/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudieron guardar los permisos.', 500);
}

Bitacora::registrar('configuracion', 'CAMBIO', 'rol_permiso', (string) $idRol,
    ['total_permisos' => count($ids)]);

Respuesta::ok(['total' => count($ids)], 'Permisos actualizados.');
