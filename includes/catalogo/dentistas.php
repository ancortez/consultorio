<?php
/**
 * Dentistas activos, con su cedula.
 *
 * Lo piden las pantallas que van a imprimir un documento firmado:
 * necesitan saber a nombre de quien sale.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

Respuesta::ok(Conexion::todos(
    'SELECT id_usuario, nombre_completo, cedula_prof
       FROM usuario
      WHERE es_dentista = 1 AND estatus = 1
      ORDER BY nombre_completo'
));
