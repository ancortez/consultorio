<?php
/**
 * Recordatorios que toca hacer.
 *
 * Los usa el tablero de inicio y la agenda. Cada renglon trae ya el
 * mensaje armado y el enlace de WhatsApp: recepcion solo revisa y
 * manda.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$idDentista = paramInt('id_usuario_dentista') ?: null;

// Quien no puede ver las citas de todos, ve solo las suyas.
if (!Auth::puede('consultas.agenda') && !Auth::puede('consultas.todas')) {
    $idDentista = (int) Auth::id();
}

$consultorio = (string) (Conexion::valor(
    'SELECT nombre_comercial FROM consultorio WHERE id_consultorio = 1'
) ?? 'el consultorio');

$lista = AgendaExtra::pendientes($idDentista);

foreach ($lista as &$c) {
    $c['mensaje'] = AgendaExtra::mensaje($c, $consultorio);

    // El enlace abre WhatsApp con el mensaje listo. El numero se limpia
    // de espacios y guiones, y se le pone la lada de Mexico si viene a
    // diez digitos, que es como lo captura recepcion.
    $tel = preg_replace('/\D+/', '', (string) ($c['telefono'] ?? ''));
    if (strlen($tel) === 10) { $tel = '52' . $tel; }

    $c['whatsapp'] = $tel !== ''
        ? 'https://wa.me/' . $tel . '?text=' . rawurlencode($c['mensaje'])
        : null;
}
unset($c);

Respuesta::ok([
    'recordatorios' => $lista,
    'reglas'        => AgendaExtra::reglas(),
]);
