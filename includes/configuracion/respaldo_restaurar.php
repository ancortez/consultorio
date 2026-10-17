<?php
/**
 * Restaura la base desde un respaldo.
 *
 * Lo mas peligroso que puede hacer este sistema: reemplaza TODOS los
 * expedientes por los de otra fecha. Por eso pide tres cosas:
 *
 *   1. el permiso config.respaldos, que solo tiene el administrador
 *   2. la palabra RESTAURAR escrita a mano
 *   3. un respaldo de seguridad previo, que lo hace Respaldos::restaurar
 *
 * La palabra escrita no es burocracia: es la diferencia entre un clic
 * distraido y una decision. Un boton con confirmacion de "¿seguro?" se
 * contesta que si sin leer, y esto no se deshace.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.respaldos');

$nombre  = (string) param('nombre', '');
$palabra = mayus((string) param('confirmacion', ''));

if ($nombre === '') {
    Respuesta::error('Falta el respaldo que se va a restaurar.');
}

if ($palabra !== 'RESTAURAR') {
    Respuesta::validacion([
        'confirmacion' => 'Escribe RESTAURAR para confirmar. Esto reemplaza '
                        . 'todos los expedientes por los de ese respaldo.',
    ]);
}

try {
    $hecho = Respaldos::restaurar($nombre);
} catch (Throwable $e) {
    error_log('[RESPALDO/RESTAURAR] ' . $e->getMessage());
    Respuesta::error($e->getMessage());
}

Respuesta::ok(
    [
        'restaurado' => $hecho['restaurado'],
        'seguridad'  => $hecho['seguridad'],
        'respaldos'  => Respaldos::listar(),
        'resumen'    => Respaldos::resumen(),
    ],
    'Base restaurada desde ' . $hecho['restaurado']
    . '. Antes se guardo ' . $hecho['seguridad'] . ' por si acaso.'
);
