<?php
/**
 * Datos del consultorio, incluido su domicilio ya aplanado.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.consultorio');

$datos = Conexion::uno(
    'SELECT c.*, 
            col.id_colonia, col.nombre_colonia, col.codigo_postal,
            mun.id_municipio, mun.nombre_municipio,
            est.id_estado, est.nombre_estado,
            d.calle, d.num_ext, d.num_int,
            d.entre_calle_1, d.entre_calle_2, d.referencia
       FROM consultorio c
       LEFT JOIN domicilio  d   ON d.id_domicilio   = c.id_domicilio
       LEFT JOIN colonia    col ON col.id_colonia   = d.id_colonia
       LEFT JOIN municipio  mun ON mun.id_municipio = col.id_municipio
       LEFT JOIN estado     est ON est.id_estado    = mun.id_estado
      WHERE c.id_consultorio = 1'
);

Respuesta::ok($datos);
