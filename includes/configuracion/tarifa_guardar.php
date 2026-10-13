<?php
/**
 * Alta y modificacion de precios del tarifario.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.tarifas');

$id          = paramInt('id_tratamiento');
$idCategoria = paramInt('id_categoria');
$clave       = paramMayus('clave');
$nombre      = limpiarEspacios(paramMayus('nombre_tratamiento'));
$descripcion = limpiarEspacios(paramMayus('descripcion'));
$precio      = (float) str_replace(',', '', (string) param('precio_lista', '0'));
$duracion    = paramInt('duracion_min');
$porDiente   = param('aplica_por_diente') ? 1 : 0;
$claveSat    = paramMayus('clave_prod_serv_sat');
$estatus     = param('estatus') !== null ? (int) param('estatus') : 1;

$errores = [];

if ($nombre === null || mb_strlen($nombre) < 3) {
    $errores['nombre_tratamiento'] = 'Captura el nombre del tratamiento.';
}
if (!$idCategoria) {
    $errores['id_categoria'] = 'Selecciona la categoria.';
}
if ($precio < 0) {
    $errores['precio_lista'] = 'El precio no puede ser negativo.';
}
if ($duracion !== null && ($duracion < 5 || $duracion > 480)) {
    $errores['duracion_min'] = 'La duracion debe estar entre 5 y 480 minutos.';
}

// La clave del SAT es numerica y de ocho digitos. Una mal escrita no
// se nota hasta que el CFDI lo rechaza el proveedor de timbrado, que
// es semanas despues y con el paciente ya cobrado.
if ($claveSat !== null && $claveSat !== ''
        && !preg_match('/^\d{8}$/', $claveSat)) {
    $errores['clave_prod_serv_sat'] =
        'La clave del SAT son 8 digitos. Dejala vacia si no la tienes.';
}

// A diferencia de procedimiento, la tabla tratamiento NO tiene indice
// unico sobre el nombre: la revision es solo de aqui. Aun asi conviene
// avisar, porque dos renglones con el mismo nombre y distinto precio
// es una trampa para quien elige del combo.
$repetido = (int) Conexion::valor(
    'SELECT COUNT(*) FROM tratamiento
      WHERE nombre_tratamiento = :n AND id_tratamiento <> :id',
    [':n' => $nombre, ':id' => $id ?? 0]
);
if ($repetido > 0) {
    $errores['nombre_tratamiento'] = 'Ya existe un precio con ese nombre.';
}

if ($errores) { Respuesta::validacion($errores); }

$datos = [
    ':cat' => $idCategoria,
    ':cl'  => $clave,
    ':n'   => $nombre,
    ':d'   => $descripcion,
    ':p'   => $precio,
    ':dur' => $duracion,
    ':pd'  => $porDiente,
    ':sat' => $claveSat ?: null,
    ':est' => $estatus,
];

if ($id) {
    Conexion::consulta(
        'UPDATE tratamiento
            SET id_categoria = :cat, clave = :cl, nombre_tratamiento = :n,
                descripcion = :d, precio_lista = :p, duracion_min = :dur,
                aplica_por_diente = :pd, clave_prod_serv_sat = :sat,
                estatus = :est
          WHERE id_tratamiento = :id',
        array_merge($datos, [':id' => $id])
    );
    $accion = 'CAMBIO';
} else {
    Conexion::consulta(
        'INSERT INTO tratamiento
            (id_categoria, clave, nombre_tratamiento, descripcion,
             precio_lista, duracion_min, aplica_por_diente,
             clave_prod_serv_sat, estatus)
         VALUES (:cat, :cl, :n, :d, :p, :dur, :pd, :sat, :est)',
        $datos
    );
    $id = Conexion::ultimoId();
    $accion = 'ALTA';
}

/*
   Cambiar el precio aqui NO reescribe los planes ya hechos.

   Es a proposito y es importante: plan_tratamiento_detalle guarda el
   importe con el que se pacto cada renglon. Un plan firmado en marzo
   se cobra a precio de marzo, aunque la lista haya subido. Reescribir
   hacia atras cambiaria lo que el paciente acepto por escrito.

   Lo que si se actualiza es el costo sugerido de los procedimientos
   que cuelgan de este precio: ese es el valor que se PROPONE al armar
   el siguiente plan, y para eso estan ligados.
*/
if ($accion === 'CAMBIO') {
    Conexion::consulta(
        'UPDATE procedimiento SET costo_sugerido = :p
          WHERE id_tratamiento = :id',
        [':p' => $precio, ':id' => $id]
    );
}

Bitacora::registrar('configuracion', $accion, 'tratamiento', (string) $id,
    ['nombre' => $nombre, 'precio' => $precio]);

Respuesta::ok(['id_tratamiento' => $id], 'Precio guardado.');
