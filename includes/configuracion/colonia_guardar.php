<?php
/**
 * Alta y modificacion de colonias.
 * Todo en MAYUSCULAS, igual que el resto de los datos de domicilio.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.colonias');

$id          = paramInt('id_colonia');
$idMunicipio = paramInt('id_municipio');
$nombre      = limpiarEspacios(paramMayus('nombre_colonia'));
$cp          = preg_replace('/\D/', '', (string) param('codigo_postal', ''));
$idTipo      = paramInt('id_tipo_asenta');

$errores = [];
if (!$idMunicipio)  { $errores['id_municipio']   = 'Selecciona el municipio.'; }
if (!$idTipo)       { $errores['id_tipo_asenta'] = 'Selecciona el tipo de asentamiento.'; }
if ($nombre === null || mb_strlen($nombre) < 2) {
    $errores['nombre_colonia'] = 'Captura el nombre de la colonia.';
}
if (strlen($cp) !== 5) {
    $errores['codigo_postal'] = 'El codigo postal debe tener 5 digitos.';
}
if ($errores) { Respuesta::validacion($errores); }

// No se permite la misma colonia dos veces en el mismo municipio y CP.
$repetido = (int) Conexion::valor(
    'SELECT COUNT(*) FROM colonia
      WHERE nombre_colonia = :n AND id_municipio = :m
        AND codigo_postal = :cp AND id_colonia <> :id',
    [':n' => $nombre, ':m' => $idMunicipio, ':cp' => $cp, ':id' => $id ?? 0]
);
if ($repetido > 0) {
    Respuesta::validacion(['nombre_colonia' => 'Esa colonia ya existe con ese codigo postal.']);
}

if ($id) {
    Conexion::consulta(
        'UPDATE colonia
            SET id_municipio = :m, nombre_colonia = :n,
                codigo_postal = :cp, id_tipo_asenta = :t
          WHERE id_colonia = :id',
        [':m' => $idMunicipio, ':n' => $nombre, ':cp' => $cp,
         ':t' => $idTipo, ':id' => $id]
    );
    $accion = 'CAMBIO';
} else {
    Conexion::consulta(
        'INSERT INTO colonia (id_municipio, nombre_colonia, codigo_postal, id_tipo_asenta)
         VALUES (:m, :n, :cp, :t)',
        [':m' => $idMunicipio, ':n' => $nombre, ':cp' => $cp, ':t' => $idTipo]
    );
    $id = Conexion::ultimoId();
    $accion = 'ALTA';
}

Bitacora::registrar('configuracion', $accion, 'colonia', (string) $id,
    ['nombre' => $nombre, 'cp' => $cp]);

Respuesta::ok(['id_colonia' => $id], 'Colonia guardada.');
