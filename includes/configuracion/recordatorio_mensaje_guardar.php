<?php
/**
 * El texto del recordatorio que se manda por WhatsApp.
 *
 * Va aparte de las reglas de anticipacion aunque vivan en la misma
 * pantalla: son dos cosas distintas y se cambian en momentos
 * distintos. Los tiempos se definen una vez y casi no se tocan; el
 * texto se ajusta cuando a la doctora no le gusta como suena. Juntar
 * los dos guardados obligaria a reescribir las reglas cada vez que se
 * corrige una coma del mensaje.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.consultorio');

$mensaje = trim((string) param('mensaje', ''));

if ($mensaje === '') {
    Respuesta::validacion(
        ['mensaje' => 'Escribe el mensaje. Si lo dejas vacio, el paciente '
                    . 'recibiria un recordatorio en blanco.']);
}

// Un mensaje de WhatsApp larguisimo no se lee y ademas viaja en la
// direccion del enlace, que tiene su propio limite.
if (mb_strlen($mensaje) > 1000) {
    Respuesta::validacion(
        ['mensaje' => 'El mensaje no puede pasar de 1000 caracteres.']);
}

/*
   Las etiquetas que no existen se avisan, no se dejan pasar.

   Escribir "{telefono}" es facil y el error no se ve al guardar: se
   descubre semanas despues, cuando un paciente contesta preguntando
   que significa esa llave en el mensaje. Vale mas decirlo aqui.
*/
preg_match_all('/\{(\w+)\}/', $mensaje, $encontradas);

$validas      = array_keys(AgendaExtra::etiquetas());
$desconocidas = array_values(array_unique(array_filter(
    $encontradas[0],
    static fn (string $e): bool => !in_array($e, $validas, true)
)));

if ($desconocidas) {
    Respuesta::validacion([
        'mensaje' => 'No reconozco ' . implode(', ', $desconocidas)
                   . '. Usa los botones de abajo para insertar las etiquetas.',
    ]);
}

Conexion::consulta(
    'INSERT INTO parametro (clave, valor, descripcion)
          VALUES (:c, :v, :d)
     ON DUPLICATE KEY UPDATE valor = :v2',
    [':c' => 'recordatorio_mensaje', ':v' => $mensaje,
     ':d' => 'Texto del recordatorio de cita por WhatsApp',
     ':v2' => $mensaje]
);

Bitacora::registrar('configuracion', 'EDITA', 'parametro',
    'recordatorio_mensaje', ['largo' => mb_strlen($mensaje)]);

Respuesta::ok(['mensaje' => $mensaje], 'Mensaje guardado.');
