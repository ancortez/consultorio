<?php
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$id = paramInt('id_domicilio');
if (!$id) { Respuesta::error('Falta el identificador del domicilio.'); }

$d = Conexion::uno(
    'SELECT d.id_domicilio, d.calle, d.num_ext, d.num_int,
            d.entre_calle_1, d.entre_calle_2, d.referencia,
            d.id_colonia, col.codigo_postal,
            col.nombre_colonia, ta.nombre_tipo_asenta AS tipo_asenta,
            mun.id_municipio, mun.nombre_municipio,
            est.id_estado, est.nombre_estado,
            (SELECT COUNT(*) FROM persona_domicilio pd
              WHERE pd.id_domicilio = d.id_domicilio AND pd.estatus = 1) AS personas
       FROM domicilio d
       LEFT JOIN colonia    col ON col.id_colonia    = d.id_colonia
       LEFT JOIN tipo_asenta ta ON ta.id_tipo_asenta = col.id_tipo_asenta
       LEFT JOIN municipio mun ON mun.id_municipio = col.id_municipio
       LEFT JOIN estado    est ON est.id_estado    = mun.id_estado
      WHERE d.id_domicilio = :id',
    [':id' => $id]
);

if ($d === null) { Respuesta::error('El domicilio no existe.', 404); }
Respuesta::ok($d);
