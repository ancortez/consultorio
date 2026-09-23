<?php
/**
 * Guarda el color general del sistema.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.consultorio');

$clave = (string) param('tema', '');

if (!Tema::existe($clave)) {
    Respuesta::error('Ese color no existe.');
}

// El parametro puede no existir todavia: la primera vez se crea.
Conexion::consulta(
    'INSERT INTO parametro (clave, valor, descripcion)
          VALUES (:c, :v, :d)
     ON DUPLICATE KEY UPDATE valor = :v2',
    [':c' => Tema::CLAVE_PARAMETRO, ':v' => $clave,
     ':d' => 'Color general del sistema', ':v2' => $clave]
);

Bitacora::registrar('configuracion', 'EDITA', 'parametro',
    Tema::CLAVE_PARAMETRO, ['tema' => $clave]);

Respuesta::ok(['tema' => $clave], 'Color actualizado.');
