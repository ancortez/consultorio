<?php
/**
 * Nota de evolucion: alta y correccion.
 *
 * No genera pendiente de digitalizacion: se escribe en el sistema y ahi
 * se queda, no hay papel que escanear.
 *
 * SI se puede corregir, desde el 17 de octubre de 2026. Antes no, a
 * proposito —"es parte del registro clinico y se le agrega, no se le
 * corrige"— y el argumento resolvia la mitad del problema: un dedazo
 * obligaba a escribir otra nota diciendo "donde dice X, lease Y", y una
 * nota escrita el martes que habla del viernes anterior no tenia forma
 * de decirlo.
 *
 * Lo que hace que corregir no sea borrar el rastro son las dos fechas:
 *
 *   fecha_nota          a que dia se refiere. Se corrige.
 *   fecha_registro      cuando se teclo. No se toca nunca.
 *
 * mas fecha_modificacion y quien la hizo. Asi la nota puede decir "del
 * 10 de octubre" y el sistema sigue sabiendo que se teclo el 13 y se
 * corrigio el 17: las tres cosas son ciertas a la vez.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idNota     = paramInt('id_nota_evolucion');
$idConsulta = paramInt('id_consulta');
$texto      = paramTexto('texto');
$fecha      = trim((string) param('fecha', ''));

if ($texto === null || mb_strlen($texto) < 5) {
    Respuesta::validacion(['texto' => 'Escribe la nota.']);
}

/*
   La fecha: opcional al dar de alta, obligatoria al corregir.

   Al alta se puede no mandar y entonces es hoy, que es el caso de
   siempre. Al corregir, la pantalla la manda siempre —el campo viene
   con la que tenia—, asi que si llega vacia es que alguien la borro y
   hay que decirlo en vez de poner la fecha de hoy sin avisar: eso
   cambiaria el dia al que se refiere la nota por un descuido.
*/
$fechaNota = null;

if ($fecha !== '') {
    $ts = strtotime($fecha);
    if ($ts === false) {
        Respuesta::validacion(['fecha' => 'La fecha no es valida.']);
    }

    // Una nota del futuro no es un error de dedo que se pueda dejar
    // pasar: el expediente quedaria diciendo que algo se observo un dia
    // que todavia no ha llegado. Se dan dos dias de margen por si la
    // hora del servidor va atrasada.
    if ($ts > strtotime('+2 days')) {
        Respuesta::validacion(
            ['fecha' => 'La fecha no puede ser del futuro.']);
    }

    // Si viene solo el dia, se le pone la hora actual: una nota a las
    // 00:00 se ordena antes que todo lo de ese dia, y no es que se haya
    // escrito a medianoche.
    $fechaNota = strlen($fecha) <= 10
        ? date('Y-m-d', $ts) . ' ' . date('H:i:s')
        : date('Y-m-d H:i:s', $ts);
} elseif ($idNota) {
    Respuesta::validacion(['fecha' => 'Pon la fecha de la nota.']);
}

// ---------------------------------------------------------------------
//  Correccion
// ---------------------------------------------------------------------
if ($idNota) {
    $nota = Conexion::uno(
        'SELECT ne.id_nota_evolucion, ne.id_consulta, ne.id_usuario
           FROM nota_evolucion ne
          WHERE ne.id_nota_evolucion = :n AND ne.estatus = 1',
        [':n' => $idNota]
    );

    if ($nota === null) {
        Respuesta::error('La nota no existe.', 404);
    }

    /*
       Una consulta CERRADA no impide corregir su nota.

       Es lo contrario de lo que pasa con los hallazgos, y a proposito:
       las notas de evolucion son justamente el camino para agregar algo
       a una consulta cerrada. Si cerrar la consulta congelara tambien
       sus notas, corregir un dedazo seria imposible a partir del dia
       siguiente —que es cuando se nota el dedazo—.

       Lo que protege el registro no es prohibir la correccion: es que
       quede constancia de que se hizo, y de eso se encargan
       fecha_modificacion y id_usuario_modifica.
    */
    Conexion::consulta(
        'UPDATE nota_evolucion
            SET texto = :t, fecha_nota = :f,
                fecha_modificacion = NOW(), id_usuario_modifica = :u
          WHERE id_nota_evolucion = :n',
        [':t' => $texto, ':f' => $fechaNota, ':u' => Auth::id(), ':n' => $idNota]
    );

    Bitacora::registrar('consultas', 'CAMBIO', 'nota_evolucion',
        (string) $idNota,
        ['consulta' => (int) $nota['id_consulta'], 'fecha' => $fechaNota]);

    Respuesta::ok(['id_nota_evolucion' => $idNota], 'Nota corregida.');
}

// ---------------------------------------------------------------------
//  Alta
// ---------------------------------------------------------------------
if (!$idConsulta) {
    Respuesta::error('Falta la consulta.');
}

$existe = (int) Conexion::valor(
    'SELECT COUNT(*) FROM consulta WHERE id_consulta = :c AND estatus = 1',
    [':c' => $idConsulta]
);
if ($existe === 0) {
    Respuesta::error('La consulta no existe.', 404);
}

Conexion::consulta(
    'INSERT INTO nota_evolucion (id_consulta, texto, fecha_nota, id_usuario)
          VALUES (:c, :t, COALESCE(:f, NOW()), :u)',
    [':c' => $idConsulta, ':t' => $texto, ':f' => $fechaNota,
     ':u' => Auth::id()]
);

$id = Conexion::ultimoId();

Bitacora::registrar('consultas', 'ALTA', 'nota_evolucion', (string) $id,
    ['consulta' => $idConsulta]);

Respuesta::ok(['id_nota_evolucion' => $id], 'Nota agregada.');
