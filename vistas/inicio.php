<?php
/**
 * Tablero de inicio.
 * MEJORA propuesta: la composicion no definia que mostrar en "Inicio".
 * Un tablero con los numeros del dia es lo primero que quiere ver quien
 * abre el sistema en la manana, y es lo que mejor se ve en una demo.
 */

$hoy = date('Y-m-d');

/**
 * Quien ve que citas.
 *
 * El administrador y recepcion ven la agenda completa del consultorio:
 * son quienes coordinan. Un dentista ve solo las suyas, porque su
 * pantalla de inicio le sirve para saber a quien atiende hoy, no para
 * revisar el trabajo de los demas.
 *
 * Se decide por el rol, no por permisos sueltos, porque es una regla de
 * presentacion y no de seguridad: cualquiera con consultas.ver puede
 * entrar a la agenda completa desde el menu.
 */
$soloMisCitas = $usuarioSesion['es_dentista']
    && (int) $usuarioSesion['id_rol'] !== 1
    && !Auth::puede('consultas.agenda');

$sqlCitas = 'SELECT c.id_cita, c.fecha_inicio, c.sin_hora, c.motivo,
            ec.nombre_estado, ec.color,
            vp.nombre_completo, vp.id_registro_paciente, vp.num_expediente,
            u.nombre_completo AS dentista
       FROM cita c
       JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
      WHERE DATE(c.fecha_inicio) = :hoy';

$paramsCitas = [':hoy' => $hoy];

if ($soloMisCitas) {
    $sqlCitas .= ' AND c.id_usuario_dentista = :yo';
    $paramsCitas[':yo'] = Auth::id();
}

// Las sin hora arriba: son las que hay que repartir, y si fueran
// ordenadas por su 00:00 quedarian mezcladas con nada.
$sqlCitas .= ' ORDER BY c.sin_hora DESC, c.fecha_inicio';

$citasHoy = Conexion::todos($sqlCitas, $paramsCitas);

$kpi = [
    'pacientes' => (int) Conexion::valor(
        'SELECT COUNT(*) FROM registro_paciente WHERE estatus = 1'),
    'citas_hoy' => count($citasHoy),
    'consultas_mes' => (int) Conexion::valor(
        'SELECT COUNT(*) FROM consulta
          WHERE YEAR(fecha_hora) = YEAR(CURDATE())
            AND MONTH(fecha_hora) = MONTH(CURDATE())'),
    'ingresos_hoy' => (float) Conexion::valor(
        'SELECT COALESCE(SUM(monto),0) FROM pago
          WHERE DATE(fecha_hora) = :hoy AND estatus = 1', [':hoy' => $hoy]),
];

/**
 * Pendientes de pago.
 *
 * Se agrupa por paciente y no por plan: en el mostrador se cobra a una
 * persona, no a un folio, y un paciente con dos planes abiertos
 * aparecia dos veces pidiendo dos cobros que en realidad son uno.
 *
 * Ordenado por antiguedad del adeudo: entre mas tiempo pasa, mas
 * dificil es recuperarlo.
 */
$porCobrar = Auth::puede('cajas.ver')
    ? Conexion::todos(
        'SELECT vp.nombre_completo, vp.id_registro_paciente, vp.telefono,
                vp.num_expediente, vps.saldo, vps.cargos_abiertos,
                vps.adeudo_mas_antiguo,
                DATEDIFF(CURDATE(), vps.adeudo_mas_antiguo) AS dias
           FROM v_paciente_saldo vps
           JOIN v_paciente vp ON vp.id_registro_paciente = vps.id_registro_paciente
          WHERE vps.saldo > 0
          ORDER BY vps.adeudo_mas_antiguo, vps.saldo DESC
          LIMIT 8')
    : [];

$totalPorCobrar = (float) (Conexion::valor(
    'SELECT COALESCE(SUM(saldo), 0) FROM v_paciente_saldo WHERE saldo > 0') ?? 0);

/**
 * Pacientes con saldo A FAVOR.
 *
 * Es el reverso de los pendientes de pago y se vigila por una razon
 * distinta: el dinero ya entro, pero el paciente todavia no recibio
 * nada a cambio. Mientras siga ahi, el consultorio le debe tratamiento.
 *
 * Y se olvida con facilidad. Un anticipo de hace cuatro meses no le
 * grita a nadie; simplemente esta. Si el paciente deja de venir, ese
 * saldo se convierte en una llamada incomoda o en una devolucion.
 *
 * El calculo no sale de v_paciente_saldo: esa vista suma CARGOS, y el
 * dinero que entro sin encontrar cargo donde caer no aparece en
 * ninguna de sus columnas. Hay que restar lo aplicado a lo cobrado.
 */
$aFavor = Auth::puede('cajas.ver')
    ? Conexion::todos(
        'SELECT vp.id_registro_paciente, vp.nombre_completo,
                vp.num_expediente, vp.telefono,
                SUM(p.monto) - COALESCE((
                    SELECT SUM(pa.monto)
                      FROM pago_aplicacion pa
                      JOIN pago p2 ON p2.id_pago = pa.id_pago
                     WHERE p2.id_registro_paciente = p.id_registro_paciente
                       AND p2.estatus = 1), 0) AS favor,
                MAX(p.fecha_hora) AS ultimo_pago
           FROM pago p
           JOIN v_paciente vp
                ON vp.id_registro_paciente = p.id_registro_paciente
          WHERE p.estatus = 1
          GROUP BY p.id_registro_paciente, vp.id_registro_paciente,
                   vp.nombre_completo, vp.num_expediente, vp.telefono
         HAVING favor > 0.009
          ORDER BY favor DESC
          LIMIT 8')
    : [];

$totalAFavor = 0.0;
foreach ($aFavor as $f) { $totalAFavor += (float) $f['favor']; }

/**
 * Citas por confirmar.
 *
 * Nacen en Pendiente y se quedan asi hasta que alguien le llama al
 * paciente. Sin este tablero ese paso se olvida y el consultorio se
 * entera de que nadie va a llegar hasta que la silla esta vacia.
 *
 * Se listan desde hoy hacia adelante: confirmar una cita de ayer no
 * sirve de nada.
 */
$sqlPorConfirmar = 'SELECT c.id_cita, c.fecha_inicio, c.sin_hora, c.motivo, c.notas,
            vp.nombre_completo, vp.telefono, vp.id_registro_paciente,
            u.nombre_completo AS dentista
       FROM cita c
       JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
      WHERE c.id_estado_cita = 1
        AND c.fecha_inicio >= NOW()';

$paramsPorConfirmar = [];
if ($soloMisCitas) {
    $sqlPorConfirmar .= ' AND c.id_usuario_dentista = :yo';
    $paramsPorConfirmar[':yo'] = Auth::id();
}
// Sin tope apretado: la tarjeta recorta a cinco al pintar, y el modal
// de "Ver todos" necesita la lista entera. Doscientas citas pendientes
// de confirmar serian un problema de consultorio, no de consulta.
$sqlPorConfirmar .= ' ORDER BY c.fecha_inicio LIMIT 200';

$porConfirmar = Conexion::todos($sqlPorConfirmar, $paramsPorConfirmar);

/**
 * Pendientes de digitalizacion, de todo el consultorio.
 *
 * Hasta ahora esto solo se veia paciente por paciente, dentro de su
 * ficha: habia que acordarse de entrar a cada expediente para
 * enterarse de que faltaba un papel. Visto junto se convierte en una
 * tarea que alguien puede despachar en una sentada con el escaner al
 * lado.
 *
 * Los mas viejos primero: un consentimiento solicitado hace tres
 * semanas y nunca escaneado es mas urgente que el de ayer.
 */
$porDigitalizar = Auth::puede('paciente.ver')
    ? Conexion::todos(
        'SELECT d.id_documento, d.fecha_solicitud, d.descripcion,
                td.nombre_documento, td.icono, td.formatos,
                vp.nombre_completo, vp.num_expediente,
                vp.id_registro_paciente
           FROM documento d
           JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
           JOIN v_paciente vp ON vp.id_registro_paciente = d.id_registro_paciente
          WHERE d.estado = 0 AND d.estatus = 1
          ORDER BY d.fecha_solicitud
          LIMIT 200')
    : [];

/**
 * Lista de espera.
 *
 * Quien pidio cita y no habia hueco. Va pegada a la agenda del dia a
 * proposito: el momento util para acordarse de esta gente es cuando se
 * esta mirando el dia y se ve un espacio libre, no en una pantalla
 * aparte a la que hay que decidir entrar.
 */
$enEspera = Auth::puede('consultas.ver')
    ? Conexion::todos(
        // estado 1 = esperando. No hay columna "estatus" ni
        // "prioridad" en esta tabla: el orden es por antiguedad, que es
        // como se atiende una cola.
        'SELECT le.id_espera, le.motivo, le.notas, le.fecha_registro,
                le.preferencia_horario, le.duracion_min,
                le.id_usuario_dentista,
                vp.nombre_completo, vp.telefono, vp.id_registro_paciente,
                u.nombre_completo AS dentista
           FROM lista_espera le
           JOIN v_paciente vp ON vp.id_registro_paciente = le.id_registro_paciente
           LEFT JOIN usuario u ON u.id_usuario = le.id_usuario_dentista
          WHERE le.estado = 1
          ORDER BY le.fecha_registro
          LIMIT 20')
    : [];

/**
 * Recordatorios que toca hacer.
 *
 * Es el trabajo que se olvida: el paciente confirmo hace dos semanas y
 * nadie volvio a llamarle. El tablero los pone enfrente con el mensaje
 * ya armado.
 */
$recordatorios = Auth::puede('consultas.ver')
    ? AgendaExtra::pendientes($soloMisCitas ? (int) Auth::id() : null)
    : [];

$nombreConsultorio = (string) (Conexion::valor(
    'SELECT nombre_comercial FROM consultorio WHERE id_consultorio = 1'
) ?? 'el consultorio');

$cajaAbierta = Conexion::uno(
    'SELECT cc.*, u.nombre_completo AS abrio
       FROM corte_caja cc
       JOIN usuario u ON u.id_usuario = cc.id_usuario_apertura
      WHERE cc.abierto = 1
      ORDER BY cc.fecha_apertura DESC LIMIT 1'
);

$tarjetas = [
    ['titulo' => 'Pacientes activos',  'valor' => number_format($kpi['pacientes']),
     'icono' => 'bi-people-fill',        'color' => 'var(--morado-600)'],
    ['titulo' => 'Citas de hoy',       'valor' => $kpi['citas_hoy'],
     'icono' => 'bi-calendar-check-fill','color' => 'var(--rosa-400)'],
    ['titulo' => 'Consultas del mes',  'valor' => number_format($kpi['consultas_mes']),
     'icono' => 'bi-clipboard2-pulse-fill', 'color' => 'var(--morado-800)'],
    ['titulo' => 'Ingresos de hoy',    'valor' => money($kpi['ingresos_hoy']),
     'icono' => 'bi-cash-stack',        'color' => 'var(--exito)'],
];
?>

<?php
/*
   Entrada escalonada de las tarjetas.

   Cada una aparece en medio segundo y arranca una decima de segundo
   despues que la anterior. El escalon es lo que hace que se lea como
   una pantalla armandose y no como un parpadeo: todas a la vez se
   percibe como un salto, y de una en una con mas pausa se siente
   lento.

   El retraso se cuenta aqui y no con nth-child en la hoja de estilo
   porque el contador de CSS se reinicia en cada fila y en cada
   columna: la cascada salia partida en tres, con las dos columnas de
   la derecha arrancando al mismo tiempo. Contando desde PHP, el orden
   es uno solo y sigue el de lectura.
*/
// Medio segundo de espera antes de la primera. La cortinilla de carga
// se retira a los 300 ms y tarda otros 300 en desvanecerse: sin esta
// espera, las tarjetas entran DETRAS de ella y cuando se levanta ya
// esta todo quieto. La animacion se habria ejecutado sin que nadie la
// viera.
$esperaCortinilla = 0.6;

$escalon = 0;
$entrada = static function () use (&$escalon, $esperaCortinilla): string {
    $retraso = $esperaCortinilla + ($escalon++ * 0.1);
    return 'style="animation-delay:' . number_format($retraso, 1) . 's"';
};
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="titulo-vista">Buen d&iacute;a, <?= e(explode(' ', $usuarioSesion['nombre_completo'])[0]) ?></h1>
        <p class="subtitulo-vista"><?= e(fechaLarga()) ?></p>
    </div>
    <?php if (Auth::puede('paciente.crear')): ?>
    <button class="btn btn-morado" data-accion="nuevo-paciente">
        <i class="bi bi-person-plus me-1"></i> Nuevo paciente
    </button>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($tarjetas as $t): ?>
    <div class="col-3">
        <div class="card h-100 entra" <?= $entrada() ?>>
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:12px;
                            background:<?= $t['color'] ?>;color:#fff;
                            display:flex;align-items:center;justify-content:center;font-size:1.35rem">
                    <i class="bi <?= $t['icono'] ?>"></i>
                </div>
                <div>
                    <div class="text-secondary" style="font-size:12px"><?= e($t['titulo']) ?></div>
                    <div class="fs-4 fw-semibold" style="color:var(--morado-900)"><?= e((string) $t['valor']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <?php /* La agenda se queda con lo que sobre, no con un 8 de 12
             fijo: las tarjetas de al lado miden lo que miden y lo
             demas es para la agenda. Con col-8 y dos columnas de
             tarjetas al lado, la suma se pasaba de doce y la segunda
             columna se iba abajo. */ ?>
    <div class="col">
        <div class="card h-100 entra" <?= $entrada() ?>>
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-calendar3 me-2"></i>Agenda de hoy
                    <span class="badge badge-suave ms-1">
                        <?= $soloMisCitas ? 'Mis citas' : 'Todo el consultorio' ?>
                    </span>
                </span>
                <a class="btn btn-sm btn-outline-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda">Ver agenda completa</a>
            </div>
            <div class="table-responsive">
                <table class="table tabla-sistema">
                    <thead>
                        <tr>
                            <th style="width:90px">Hora</th>
                            <th>Paciente</th>
                            <th>Motivo</th>
                            <th>Dentista</th>
                            <th style="width:120px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$citasHoy): ?>
                        <tr class="fila-vacia">
                            <td colspan="5">
                                <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
                                No hay citas agendadas para hoy.
                            </td>
                        </tr>
                    <?php else: foreach ($citasHoy as $c): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(horaCita($c)) ?></td>
                            <td>
                                <a href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $c['id_registro_paciente'] ?>"
                                   class="text-decoration-none fw-semibold">
                                    <?= e($c['nombre_completo']) ?>
                                </a>
                                <div class="text-secondary" style="font-size:11px"><?= e($c['num_expediente']) ?></div>
                            </td>
                            <td class="text-secondary"><?= e($c['motivo']) ?></td>
                            <td class="text-secondary"><?= e($c['dentista']) ?></td>
                            <td>
                                <span class="chip-estado"
                                      style="background:<?= e($c['color']) ?>1f;color:<?= e($c['color']) ?>">
                                    <?= e($c['nombre_estado']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php /* ============ Lista de espera ============ */ ?>
            <?php /* Pegada a la agenda del dia, y no en una pantalla
                     aparte, porque el momento util para acordarse de
                     esta gente es cuando se esta mirando el dia y se ve
                     un hueco. En otra pantalla hay que decidir entrar, y
                     nadie entra a una lista de espera "por si acaso". */ ?>
            <?php if (Auth::puede('consultas.ver')): ?>
            <div class="card-body border-top py-2">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary small fw-semibold">
                        <i class="bi bi-hourglass-split me-1"></i>Lista de espera
                        <?php if ($enEspera): ?>
                        <span class="badge badge-pendiente ms-1"><?= count($enEspera) ?></span>
                        <?php endif; ?>
                    </span>
                    <a class="btn btn-sm btn-outline-morado"
                       href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda">
                        Abrir la agenda
                    </a>
                </div>

                <?php if (!$enEspera): ?>
                    <div class="exp-vacio">Nadie esperando hueco.</div>
                <?php else: ?>
                <div class="espera-renglones">
                    <?php foreach ($enEspera as $es): ?>
                    <?php
                    $franjas = [1 => 'Mañana', 2 => 'Tarde', 3 => 'Cualquier hora'];
                    ?>
                    <div class="espera-renglon">
                        <div style="min-width:0">
                            <a class="fw-semibold text-decoration-none"
                               style="font-size:13px"
                               href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $es['id_registro_paciente'] ?>">
                                <?= e($es['nombre_completo']) ?>
                            </a>
                            <div class="text-secondary text-truncate" style="font-size:11px">
                                <?php if (!empty($es['telefono'])): ?>
                                    <i class="bi bi-telephone"></i> <?= e($es['telefono']) ?> &middot;
                                <?php endif; ?>
                                <?= e($franjas[(int) $es['preferencia_horario']] ?? 'Cualquier hora') ?>
                                &middot; <?= (int) $es['duracion_min'] ?> min
                                <?php if (!empty($es['dentista'])): ?>
                                    &middot; <?= e($es['dentista']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($es['motivo']) || !empty($es['notas'])): ?>
                            <div class="nota-cita">
                                <i class="bi bi-sticky"></i>
                                <?= e(trim(($es['motivo'] ?? '') . ' ' . ($es['notas'] ?? ''))) ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php /* Lleva a la agenda con el paciente ya
                                 puesto y la cita abierta: el dato lo
                                 sabemos y volver a buscarlo es hacer
                                 repetir trabajo. */ ?>
                        <?php if (Auth::puede('consultas.agenda') || $usuarioSesion['es_dentista']): ?>
                        <a class="btn btn-sm btn-morado"
                           title="Agendarle cita"
                           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda&nueva=1&paciente=<?= (int) $es['id_registro_paciente'] ?>">
                            <i class="bi bi-calendar-plus me-1"></i> Agendar
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php /* Dos columnas de tarjetas, no una.

             Apiladas en una sola columna, las dos ultimas quedaban
             debajo del doblez: para ver los recordatorios del dia
             —que son llamadas que alguien tiene que hacer HOY— habia
             que desplazarse. El dashboard es justo la pantalla que no
             deberia esconder nada: se abre para enterarse de un golpe
             de como viene el dia.

             Lo que se empareja: a la izquierda lo que hay que HACER
             (confirmar citas, llamar); a la derecha lo que hay que
             SABER (la caja, lo que deben, lo que tienen a favor). Son
             dos lecturas distintas y mezclarlas obliga a separarlas
             con la vista. */ ?>
    <div class="col-auto tarjetas-inicio">
      <div class="row g-3">

        <div class="col-6">
        <?php if (Auth::puede('consultas.ver')): ?>
        <div class="card mb-3 entra" <?= $entrada() ?>>
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-telephone me-2"></i>Citas por confirmar
                    <?php /* La clase la usa el JavaScript para bajar el
                             contador al confirmar, aqui y en el modal:
                             son dos y tienen que decir lo mismo. */ ?>
                    <?php if ($porConfirmar): ?>
                        <span class="badge badge-pendiente badge-por-confirmar ms-1"><?= count($porConfirmar) ?></span>
                    <?php endif; ?>
                </span>
                <a class="btn btn-sm btn-outline-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda">Agenda</a>
            </div>
            <?php
            /*
               Cinco a la vista, el resto en "Ver todos".

               La tarjeta cabe en media columna y con doce renglones se
               come la pantalla, que es justo lo que se arreglo esta
               ronda. Cinco es lo que se alcanza a leer de un vistazo;
               quien de verdad va a ponerse a llamar abre la lista
               completa y trabaja desde ahi.

               El renglon se pinta con una funcion y no con dos bloques
               de HTML copiados: la tarjeta y el modal ensenian lo
               mismo, y teniendo dos copias una acabaria mostrando la
               nota y la otra no.
            */
            $filaPorConfirmar = static function (array $pc): void { ?>
                <div class="cita-pendiente" data-cita="<?= (int) $pc['id_cita'] ?>">
                    <div class="cuando">
                        <b><?= e(fecha($pc['fecha_inicio'], 'd/m')) ?></b>
                        <?= e(horaCita($pc)) ?>
                    </div>
                    <div class="flex-grow-1" style="min-width:0">
                        <a class="fw-semibold text-decoration-none d-block text-truncate"
                           style="font-size:13px"
                           href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $pc['id_registro_paciente'] ?>">
                            <?= e($pc['nombre_completo']) ?>
                        </a>
                        <div class="text-secondary text-truncate" style="font-size:11px">
                            <?php if (!empty($pc['telefono'])): ?>
                                <i class="bi bi-telephone"></i> <?= e($pc['telefono']) ?> &middot;
                            <?php endif; ?>
                            <?= e($pc['dentista']) ?>
                        </div>

                        <?php /* La nota es lo que contesta "¿por que
                                 venia este niño?" antes de levantar el
                                 telefono. Sin ella hay que abrir la
                                 agenda para saber de que se le habla. */ ?>
                        <?php if (!empty($pc['notas'])): ?>
                        <div class="nota-cita" title="<?= e($pc['notas']) ?>">
                            <i class="bi bi-sticky"></i> <?= e($pc['notas']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-1">
                        <?php /* Sin hora no hay nada que confirmar: la
                                 llamada al paciente es "lo esperamos a
                                 las diez y media", y esa frase todavia
                                 no existe. En lugar del visto va el
                                 atajo a la agenda de ese dia con la
                                 cita abierta, que es donde se le pone
                                 la hora —lo que de verdad hace falta
                                 antes de llamar—. */ ?>
                        <?php if ((int) ($pc['sin_hora'] ?? 0) === 1): ?>
                        <a class="btn btn-sm btn-outline-morado"
                           title="Ponerle hora"
                           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda&fecha=<?= e(fecha($pc['fecha_inicio'], 'Y-m-d')) ?>&cita=<?= (int) $pc['id_cita'] ?>">
                            <i class="bi bi-clock"></i>
                        </a>
                        <?php else: ?>
                        <button type="button" class="btn btn-sm btn-morado"
                                data-confirmar="<?= (int) $pc['id_cita'] ?>"
                                title="Confirmar cita">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-light text-danger"
                                data-cancelar="<?= (int) $pc['id_cita'] ?>"
                                title="Cancelar cita">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            <?php }; ?>

            <div id="listaPorConfirmar">
                <?php if (!$porConfirmar): ?>
                    <div class="p-4 text-center text-secondary small">
                        <i class="bi bi-check2-circle fs-4 d-block mb-1" style="color:var(--exito)"></i>
                        Todas las citas est&aacute;n confirmadas.
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($porConfirmar, 0, 5) as $pc) {
                        $filaPorConfirmar($pc);
                    } ?>
                <?php endif; ?>
            </div>

            <?php /* El boton solo sale si hay MAS de cinco: con cinco
                     exactos ya se ven todos y el enlace seria ruido. */ ?>
            <?php if (count($porConfirmar) > 5): ?>
            <button type="button" class="ver-mas w-100 border-0 bg-transparent"
                    data-bs-toggle="modal" data-bs-target="#modalPorConfirmar">
                Ver todos (<?= count($porConfirmar) ?>)
                <i class="bi bi-arrow-right"></i>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php /* ============ Recordatorios ============ */ ?>
        <?php if (Auth::puede('consultas.ver')): ?>
        <div class="card entra" <?= $entrada() ?>>
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-whatsapp me-2"></i>Recordatorios de hoy
                    <?php if ($recordatorios): ?>
                        <span class="badge badge-pendiente ms-1"><?= count($recordatorios) ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="list-group list-group-flush" id="listaRecordatorios">
                <?php if (!$recordatorios): ?>
                    <div class="p-4 text-center text-secondary small">
                        <i class="bi bi-check2-circle fs-3 d-block mb-2"
                           style="color:var(--exito)"></i>
                        No hay recordatorios pendientes.
                    </div>
                <?php else: foreach ($recordatorios as $r):
                    $tel = preg_replace('/\D+/', '', (string) ($r['telefono'] ?? ''));
                    if (strlen($tel) === 10) { $tel = '52' . $tel; }
                    $mensaje = AgendaExtra::mensaje($r, $nombreConsultorio);
                ?>
                    <div class="list-group-item recordatorio" data-cita="<?= (int) $r['id_cita'] ?>"
                         data-regla="<?= (int) $r['id_regla'] ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div style="min-width:0">
                                <a class="enlace-paciente" style="font-size:13px"
                                   href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $r['id_registro_paciente'] ?>">
                                    <?= e($r['paciente']) ?>
                                </a>
                                <?php /* Con cuandoCita y no fechaHora: una
                                         cita apartada para el dia esta a
                                         las 00:00, y aqui se leeria
                                         "27/11/2026 00:00" justo al lado
                                         del boton que manda el mensaje al
                                         paciente. */ ?>
                                <div class="text-secondary" style="font-size:11px">
                                    <?= e(cuandoCita($r)) ?>
                                    &middot; <?= e($r['dentista']) ?>
                                </div>
                                <div style="font-size:10.5px">
                                    <span class="chip-estado badge-pendiente">
                                        <?= e($r['regla']) ?>
                                    </span>
                                    <span class="text-secondary">
                                        faltan <?= (int) round($r['horas_faltan']) ?> h
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-1 align-items-end">
                                <?php if ($tel !== ''): ?>
                                <a class="btn btn-sm btn-whatsapp" target="_blank"
                                   rel="noopener"
                                   href="https://wa.me/<?= e($tel) ?>?text=<?= rawurlencode($mensaje) ?>"
                                   data-accion="rec-enviar">
                                    <i class="bi bi-whatsapp"></i> Enviar
                                </a>
                                <?php else: ?>
                                <span class="text-secondary" style="font-size:10.5px">
                                    Sin tel&eacute;fono
                                </span>
                                <?php endif; ?>

                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-light"
                                            data-accion="rec-hecho" title="Ya le avis&eacute;">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light"
                                            data-accion="rec-nocontesto" title="No contest&oacute;">
                                        <i class="bi bi-telephone-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php /* ============ Pendientes de digitalizacion ============ */ ?>
        <?php /* De TODO el consultorio, no de un paciente.

                 Antes esto solo existia dentro de cada ficha, asi que
                 enterarse de que faltaba un papel exigia entrar
                 expediente por expediente. Juntos se vuelven una tarea
                 que alguien despacha en una sentada con el escaner al
                 lado, que es como se hace de verdad. */ ?>
        <?php if (Auth::puede('paciente.ver')): ?>
        <div class="card mt-3 entra" <?= $entrada() ?>>
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-hourglass-split me-2"></i>Pendientes de digitalizar
                    <?php if ($porDigitalizar): ?>
                        <span class="badge badge-pendiente ms-1"><?= count($porDigitalizar) ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <?php
            $filaPendiente = static function (array $d): void { ?>
                <a class="renglon-ficha"
                   href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $d['id_registro_paciente'] ?>#expediente">
                    <i class="bi <?= e($d['icono'] ?: 'bi-file-earmark') ?>"></i>
                    <div style="min-width:0">
                        <div class="rf-titulo"><?= e($d['nombre_documento']) ?></div>
                        <div class="rf-sub"><?= e($d['nombre_completo']) ?>
                            &middot; <?= e($d['num_expediente']) ?></div>
                        <?php /* Desde cuando se pidio: un consentimiento
                                 solicitado hace tres semanas pesa mas que
                                 el de ayer. */ ?>
                        <div class="rf-sub">Solicitado el
                            <?= e(fecha($d['fecha_solicitud'])) ?></div>
                    </div>
                    <i class="bi bi-chevron-right"></i>
                </a>
            <?php }; ?>

            <div class="card-body py-2">
                <?php if (!$porDigitalizar): ?>
                    <div class="exp-vacio">Nada por escanear. Todo al d&iacute;a.</div>
                <?php else: ?>
                    <?php foreach (array_slice($porDigitalizar, 0, 5) as $d) {
                        $filaPendiente($d);
                    } ?>
                    <?php if (count($porDigitalizar) > 5): ?>
                    <button type="button" class="ver-mas w-100 border-0 bg-transparent"
                            data-bs-toggle="modal" data-bs-target="#modalPorDigitalizar">
                        Ver todos (<?= count($porDigitalizar) ?>)
                        <i class="bi bi-arrow-right"></i>
                    </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        </div>

        <div class="col-6">
        <?php if (Auth::puede('cajas.ver')): ?>
        <div class="card mb-3 entra" <?= $entrada() ?>>
            <div class="card-header"><i class="bi bi-cash-coin me-2"></i>Estado de caja</div>
            <div class="card-body">
                <?php if ($cajaAbierta): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="chip-estado" style="background:#e6f5ee;color:var(--exito)">ABIERTA</span>
                        <span class="text-secondary small">
                            desde <?= e(fecha($cajaAbierta['fecha_apertura'], 'H:i')) ?>
                        </span>
                    </div>
                    <div class="dato-identidad">
                        <i class="bi bi-person"></i>
                        <div><div class="etiqueta">Abri&oacute;</div>
                             <div class="valor"><?= e($cajaAbierta['abrio']) ?></div></div>
                    </div>
                    <div class="dato-identidad">
                        <i class="bi bi-wallet2"></i>
                        <div><div class="etiqueta">Fondo inicial</div>
                             <div class="valor"><?= e(money($cajaAbierta['monto_inicial'])) ?></div></div>
                    </div>
                <?php else: ?>
                    <p class="text-secondary small mb-2">No hay una caja abierta.</p>
                    <button class="btn btn-sm btn-morado w-100">Abrir caja</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (Auth::puede('cajas.ver')): ?>
        <div class="card mb-3 entra" <?= $entrada() ?>>
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-cash-coin me-2"></i>Pendientes de pago
                    <?php if ($porCobrar): ?>
                        <span class="badge badge-pendiente ms-1"><?= count($porCobrar) ?></span>
                    <?php endif; ?>
                </span>
                <a class="btn btn-sm btn-outline-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=cajas&vista=pendientes">
                    Ver todos
                </a>
            </div>

            <?php if ($totalPorCobrar > 0): ?>
            <div class="px-3 pt-2">
                <div class="resumen-saldo">
                    <div>
                        <span class="et">Total por cobrar</span>
                        <span class="va fs-5" style="color:var(--error)">
                            <?= e(money($totalPorCobrar)) ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="list-group list-group-flush">
                <?php if (!$porCobrar): ?>
                    <div class="p-4 text-center text-secondary small">
                        <i class="bi bi-check2-circle fs-3 d-block mb-2"
                           style="color:var(--exito)"></i>
                        Nadie debe nada.
                    </div>
                <?php else: foreach ($porCobrar as $p): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div style="min-width:0">
                            <a class="enlace-paciente" style="font-size:13px"
                               href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $p['id_registro_paciente'] ?>">
                                <?= e($p['nombre_completo']) ?>
                            </a>
                            <div class="text-secondary" style="font-size:11px">
                                <?= (int) $p['cargos_abiertos'] ?> concepto(s)
                                <?php /* Un adeudo de mas de un mes se marca:
                                         es el que hay que atender primero. */ ?>
                                <?php if ((int) $p['dias'] > 30): ?>
                                    &middot; <span style="color:var(--error)">
                                        hace <?= (int) $p['dias'] ?> d&iacute;as</span>
                                <?php else: ?>
                                    &middot; hace <?= (int) $p['dias'] ?> d&iacute;a(s)
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold" style="color:var(--error)">
                                <?= e(money($p['saldo'])) ?>
                            </div>
                            <?php if (Auth::puede('cajas.cobrar')): ?>
                            <a class="btn btn-sm btn-morado mt-1"
                               href="<?= URL_BASE ?>/index.php?modulo=cajas&vista=cobrar&paciente=<?= (int) $p['id_registro_paciente'] ?>">
                                Cobrar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php /* ============ Saldo a favor ============ */ ?>
        <?php if (Auth::puede('cajas.ver') && $aFavor): ?>
        <div class="card mb-3 entra" <?= $entrada() ?>>
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-piggy-bank me-2"></i>Con saldo a favor
                    <span class="badge badge-suave ms-1"><?= count($aFavor) ?></span>
                </span>
                <span class="fw-semibold" style="color:var(--exito)">
                    <?= e(money($totalAFavor)) ?>
                </span>
            </div>
            <div class="card-body py-2">
                <?php /* No es una lista de cobro: es una lista de deuda del
                         consultorio hacia el paciente. El dinero ya entro y
                         todavia no recibio nada a cambio. */ ?>
                <div class="exp-vacio mb-2" style="background:#e8f6ef;color:#1c7a52">
                    Ya pagaron y a&uacute;n no se les aplica a ning&uacute;n
                    cargo. Se descuenta solo en su pr&oacute;ximo tratamiento.
                </div>
                <?php foreach ($aFavor as $f): ?>
                <a class="renglon-ficha"
                   href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $f['id_registro_paciente'] ?>">
                    <div style="min-width:0">
                        <div class="rf-titulo"><?= e($f['nombre_completo']) ?></div>
                        <div class="rf-sub">
                            <?= e($f['num_expediente']) ?>
                            <?php if ($f['telefono']): ?>
                                &middot; <?= e($f['telefono']) ?>
                            <?php endif; ?>
                            &middot; &uacute;ltimo pago <?= e(fecha($f['ultimo_pago'])) ?>
                        </div>
                    </div>
                    <span class="fw-semibold" style="color:var(--exito)">
                        <?= e(money($f['favor'])) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        </div>

      </div>
    </div>
</div>

<?php /* =====================================================================
         Las listas completas
         =====================================================================
         Las tarjetas ensenian cinco y estos modales el resto. Se pintan
         con la MISMA funcion que la tarjeta, no con HTML copiado: dos
         copias del renglon acabarian ensenando cosas distintas, que es
         justo lo que acaba de pasar con la nota de la cita.

         Van al final del archivo, fuera de la rejilla: un modal dentro
         de una columna hereda su ancho y sale estrujado contra un lado.
         ===================================================================== */ ?>

<?php if (Auth::puede('consultas.ver') && count($porConfirmar) > 5): ?>
<div class="modal fade" id="modalPorConfirmar" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-telephone me-2"></i>Citas por confirmar
          <span class="badge badge-pendiente badge-por-confirmar ms-1"><?= count($porConfirmar) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" id="listaPorConfirmarTodas">
        <?php foreach ($porConfirmar as $pc) { $filaPorConfirmar($pc); } ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (Auth::puede('paciente.ver') && count($porDigitalizar) > 5): ?>
<div class="modal fade" id="modalPorDigitalizar" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-hourglass-split me-2"></i>Pendientes de digitalizar
          <span class="badge badge-pendiente ms-1"><?= count($porDigitalizar) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-2">
        <?php foreach ($porDigitalizar as $d) { $filaPendiente($d); } ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
