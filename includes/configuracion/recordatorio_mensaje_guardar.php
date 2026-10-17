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

/*
   Son DOS mensajes: el de la cita con hora y el del dia apartado.

   Llegan juntos y se validan con las mismas reglas, porque son el mismo
   tipo de texto. Lo que no se puede es guardar uno y rechazar el otro:
   la pantalla edita los dos en el mismo cuadro y guarda con un solo
   boton, asi que si uno falla no se guarda ninguno y el aviso dice cual
   es. Guardando a medias, la doctora corrige el que fallo y se
   encuentra con que el otro tambien se le fue a medias.

   El de la cita con hora se sigue llamando 'mensaje' —asi lo mandaba la
   pantalla antes de que existiera el segundo— para no romper nada que
   ya lo use.
*/
const CLAVES = [
    'mensaje'          => [
        'clave'       => 'recordatorio_mensaje',
        'descripcion' => 'Texto del recordatorio de cita por WhatsApp',
        'omision'     => AgendaExtra::PLANTILLA_POR_OMISION,
        'que_es'      => 'el de las citas con hora',
    ],
    'mensaje_sin_hora' => [
        'clave'       => 'recordatorio_mensaje_sin_hora',
        'descripcion' => 'Texto del recordatorio de un dia apartado sin hora',
        'omision'     => AgendaExtra::PLANTILLA_SIN_HORA_POR_OMISION,
        'que_es'      => 'el del dia apartado sin hora',
    ],
];

$validas  = array_keys(AgendaExtra::etiquetas());
$errores  = [];
$textos   = [];

foreach (CLAVES as $campo => $cual) {
    /*
       Un campo que no llega se queda como estaba.

       No es lo mismo que llegar vacio: vacio es "lo borre", y eso se
       rechaza. Que no llegue significa que quien llamo al endpoint no
       estaba editando ese mensaje —una pantalla vieja, por ejemplo— y
       pisarlo con la plantilla de origen le borraria a la doctora un
       texto que ella escribio.
    */
    if (param($campo) === null) {
        continue;
    }

    $texto = trim((string) param($campo, ''));

    if ($texto === '') {
        $errores[$campo] = 'Escribe el mensaje (' . $cual['que_es'] . '). '
                         . 'Vacio, el paciente recibiria un recordatorio en blanco.';
        continue;
    }

    // Un mensaje de WhatsApp larguisimo no se lee y ademas viaja en la
    // direccion del enlace, que tiene su propio limite.
    if (mb_strlen($texto) > 1000) {
        $errores[$campo] = 'El mensaje no puede pasar de 1000 caracteres.';
        continue;
    }

    /*
       Las etiquetas que no existen se avisan, no se dejan pasar.

       Escribir "{telefono}" es facil y el error no se ve al guardar: se
       descubre semanas despues, cuando un paciente contesta preguntando
       que significa esa llave en el mensaje. Vale mas decirlo aqui.
    */
    preg_match_all('/\{(\w+)\}/', $texto, $encontradas);

    $desconocidas = array_values(array_unique(array_filter(
        $encontradas[0],
        static fn (string $e): bool => !in_array($e, $validas, true)
    )));

    if ($desconocidas) {
        $errores[$campo] = 'No reconozco ' . implode(', ', $desconocidas)
                         . '. Usa los botones de arriba para insertar las etiquetas.';
        continue;
    }

    $textos[$campo] = $texto;
}

if ($errores) {
    Respuesta::validacion($errores);
}

if (!$textos) {
    Respuesta::error('No llego ningun mensaje que guardar.');
}

foreach ($textos as $campo => $texto) {
    $cual = CLAVES[$campo];

    Conexion::consulta(
        'INSERT INTO parametro (clave, valor, descripcion)
              VALUES (:c, :v, :d)
         ON DUPLICATE KEY UPDATE valor = :v2',
        [':c' => $cual['clave'], ':v' => $texto,
         ':d' => $cual['descripcion'], ':v2' => $texto]
    );

    Bitacora::registrar('configuracion', 'EDITA', 'parametro',
        $cual['clave'], ['largo' => mb_strlen($texto)]);
}

Respuesta::ok($textos,
    count($textos) > 1 ? 'Los dos mensajes quedaron guardados.'
                       : 'Mensaje guardado.');
