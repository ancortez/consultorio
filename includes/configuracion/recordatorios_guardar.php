<?php
/**
 * Reglas de recordatorio: cuantos y con cuanta anticipacion.
 *
 * Llegan todas juntas y se reemplazan completas. Es mas simple de
 * razonar que ir comparando cual se agrego y cual se quito, y la lista
 * nunca pasa de un puniado de renglones.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.consultorio');

$reglas = json_decode((string) param('reglas', '[]'), true);
if (!is_array($reglas)) { $reglas = []; }

$limpias = [];
$vistas  = [];

foreach ($reglas as $r) {
    $horas = (int) ($r['horas_antes'] ?? 0);

    // Una anticipacion de cero no es un recordatorio, y mas de un mes
    // antes el paciente ni se acuerda de que agendo.
    if ($horas < 1 || $horas > 720) { continue; }
    if (isset($vistas[$horas]))     { continue; }

    $vistas[$horas] = true;

    $nombre = limpiarEspacios(mayus((string) ($r['nombre'] ?? '')));
    if ($nombre === null || $nombre === '') {
        // Sin nombre se arma uno legible a partir de las horas.
        $nombre = $horas >= 24 && $horas % 24 === 0
            ? intdiv($horas, 24) . ' DIA(S) ANTES'
            : $horas . ' HORA(S) ANTES';
    }

    $limpias[] = ['nombre' => mb_substr($nombre, 0, 60), 'horas_antes' => $horas];
}

if (!$limpias) {
    Respuesta::validacion(['reglas' => 'Deja al menos un recordatorio.']);
}

// Se ordenan del mas anticipado al mas cercano, que es el orden en que
// se hacen y el que espera la pantalla.
usort($limpias, static fn($a, $b) => $b['horas_antes'] <=> $a['horas_antes']);

$pdo = bd();
try {
    $pdo->beginTransaction();

    // Las reglas no se borran: se apagan. Los recordatorios ya hechos
    // apuntan a ellas y borrarlas dejaria el historial sin sentido.
    Conexion::consulta('UPDATE recordatorio_regla SET estatus = 0');

    $orden = 1;
    foreach ($limpias as $r) {
        Conexion::consulta(
            'INSERT INTO recordatorio_regla (nombre, horas_antes, orden, estatus)
                  VALUES (:n, :h, :o, 1)
             ON DUPLICATE KEY UPDATE nombre = :n2, orden = :o2, estatus = 1',
            [':n' => $r['nombre'], ':h' => $r['horas_antes'], ':o' => $orden,
             ':n2' => $r['nombre'], ':o2' => $orden]
        );
        $orden++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[RECORDATORIOS] ' . $e->getMessage());
    Respuesta::error('No se pudieron guardar las reglas.', 500);
}

Bitacora::registrar('configuracion', 'EDITA', 'recordatorio_regla', null,
    ['reglas' => count($limpias)]);

Respuesta::ok(AgendaExtra::reglas(), 'Recordatorios actualizados.');
