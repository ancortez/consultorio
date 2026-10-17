<?php
/**
 * Configuracion > Recordatorios de cita.
 *
 * Cuantos avisos se le hacen al paciente y con cuanta anticipacion.
 * El sistema no manda nada solo: arma el mensaje y lo pone enfrente en
 * el tablero para que recepcion lo revise y lo envie.
 */
$reglas = AgendaExtra::reglas();

// Los dos textos guardados, o los de origen si nunca se han tocado.
//
// Son DOS porque una cita con hora y una apartada para el dia no se
// anuncian igual: "le recordamos la cita el jueves a las 10:30" no se
// puede decir de una cita a la que todavia no se le ha puesto hora, y
// dejar la hora en blanco deja al paciente sin saber si tiene que venir.
$mensajeRec = (string) parametro(
    'recordatorio_mensaje', AgendaExtra::PLANTILLA_POR_OMISION);

$mensajeSinHora = (string) parametro(
    'recordatorio_mensaje_sin_hora', AgendaExtra::PLANTILLA_SIN_HORA_POR_OMISION);

$etiquetas  = AgendaExtra::etiquetas();
// El mismo origen que usa el tablero de inicio al armar el mensaje de
// verdad: si la vista previa dijera otro nombre, estaria ensayando algo
// distinto de lo que se manda.
$consultorio = (string) (Conexion::valor(
    'SELECT nombre_comercial FROM consultorio WHERE id_consultorio = 1'
) ?? 'el consultorio');

// La vista previa se arma en el servidor con datos de muestra, y de ahi
// en adelante la rehace el navegador mientras se escribe. Asi se ve algo
// util desde el primer momento, sin esperar a que alguien teclee.
$muestra = [
    'paciente'          => 'MARIA',
    'paciente_completo' => 'MARIA FERNANDA GUTIERREZ LOPEZ',
    'fecha'             => fechaLarga(date('Y-m-d', strtotime('+2 days'))),
    'hora'              => '10:30 am',
    'dentista'          => 'DRA. ADRIANA LOPEZ RUELAS',
    'consultorio'       => $consultorio,
];

/*
   La muestra de la cita SIN HORA cambia dos cosas.

   La fecha se va a seis semanas, que es cuando se aparta un dia sin
   hora de verdad —a dos dias nadie lo hace—, y {hora} dice "por
   confirmar", exactamente lo que sustituye AgendaExtra::mensaje(). Si
   aqui dijera "10:30 am", la vista previa estaria ensayando un mensaje
   que el sistema no manda nunca, y alguien escribiria la etiqueta
   creyendo que funciona.
*/
$muestraSinHora = $muestra;
$muestraSinHora['fecha'] = fechaLarga(date('Y-m-d', strtotime('+42 days')));
$muestraSinHora['hora']  = 'por confirmar';
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Recordatorios de cita</h1>
                <p class="subtitulo-vista">
                    Cu&aacute;ntos avisos y con cu&aacute;nta anticipaci&oacute;n
                </p>
            </div>
            <?php /* El boton de agregar se fue a la cabecera de su
                     propia tarjeta: agrega un renglon a ESA tabla, y
                     arriba del todo parecia que agregaba algo a la
                     pantalla entera. */ ?>
            <div class="d-flex gap-2">
                <button class="btn btn-morado" id="recGuardar">
                    <i class="bi bi-check-lg me-1"></i> Guardar todo
                </button>
            </div>
        </div>

        <div class="alert alert-light border d-flex gap-2 py-2 small">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span>
                El sistema <b>no manda mensajes solo</b>. Cada recordatorio
                aparece en el tablero de inicio con el texto ya armado, y
                recepci&oacute;n lo revisa antes de enviarlo por WhatsApp.
                Mandar solo es f&aacute;cil de programar y dif&iacute;cil de
                arreglar cuando sale mal.
            </span>
        </div>

        <?php /* Mitad y mitad: CUANDO se avisa y QUE se dice.

                 Son las dos preguntas del recordatorio y se contestan
                 juntas: cambiar el texto sin ver a cuanta anticipacion
                 llega lleva a escribir "mañana tiene cita" en un aviso
                 que sale tres dias antes. Lado a lado, la incoherencia
                 salta. */ ?>
        <div class="row g-3">

            <?php /* ---------- Izquierda: los tiempos ---------- */ ?>
            <div class="col-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-clock me-2"></i>Cu&aacute;ndo se avisa</span>
                        <button class="btn btn-sm btn-outline-morado" id="recAgregar">
                            <i class="bi bi-plus-lg me-1"></i> Agregar
                        </button>
                    </div>
                    <div class="tabla-con-menu">
                        <table class="table tabla-sistema mb-0">
                            <thead><tr>
                                <th style="width:48px">#</th>
                                <th>Nombre</th>
                                <th style="width:170px">Anticipaci&oacute;n</th>
                                <th style="width:140px">Equivale a</th>
                                <th class="col-acciones"></th>
                            </tr></thead>
                            <tbody id="tablaRecordatorios"></tbody>
                        </table>
                    </div>

                    <?php /* La linea de tiempo ayuda a ver si los avisos
                             estan bien repartidos o si dos caen casi
                             encima. */ ?>
                    <div class="card-body border-top">
                        <div class="text-secondary small mb-2">
                            <i class="bi bi-clock-history me-1"></i>C&oacute;mo queda
                        </div>
                        <div class="linea-recordatorios" id="lineaRecordatorios"></div>
                    </div>
                </div>
            </div>

            <?php /* ---------- Derecha: el mensaje ---------- */ ?>
            <div class="col-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-whatsapp me-2"></i>Qu&eacute; se le escribe</span>
                        <span class="text-secondary small" id="recContador"></span>
                    </div>
                    <div class="card-body">

                        <?php /* Los botones van ARRIBA del cuadro y no
                                 abajo: se usan mientras se escribe, y
                                 tenerlos despues del texto obliga a
                                 bajar la vista cada vez que hace falta
                                 una etiqueta. */ ?>
                        <?php /* Las dos plantillas, una a la vez.

                                 Apiladas en la misma tarjeta serian dos
                                 cuadros grandes con dos vistas previas,
                                 y la tarjeta mide lo que mide. Con el
                                 selector se edita la que toca y se ve su
                                 propia vista previa, que es como se
                                 revisa un mensaje: leyendolo completo,
                                 no comparandolo con el de al lado. */ ?>
                        <div class="pestanias-mensaje mb-2" id="recQueMensaje">
                            <button type="button" class="chip-mensaje activo"
                                    data-cual="con">
                                <i class="bi bi-clock me-1"></i>Citas con hora
                            </button>
                            <button type="button" class="chip-mensaje"
                                    data-cual="sin">
                                <i class="bi bi-calendar2-week me-1"></i>D&iacute;a
                                apartado sin hora
                            </button>
                        </div>

                        <div class="form-text mb-2" id="recQueMensajeAyuda"></div>

                        <label class="form-label">Mensaje</label>
                        <div class="etiquetas-mensaje mb-2">
                            <?php foreach ($etiquetas as $marca => $queEs): ?>
                            <button type="button" class="chip-etiqueta"
                                    data-etiqueta="<?= e($marca) ?>"
                                    title="<?= e($queEs) ?>">
                                <?= e($marca) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <textarea class="form-control" id="recMensaje" rows="7"
                                  maxlength="1000"
                                  spellcheck="true"><?= e($mensajeRec) ?></textarea>
                        <div class="invalid-feedback" id="recMensajeError"></div>

                        <div class="form-text">
                            Pica una etiqueta para insertarla donde est&eacute;
                            el cursor. Al enviar, cada una se cambia por el
                            dato de esa cita.
                        </div>

                        <?php /* ---------- Vista previa ---------- */ ?>
                        <div class="previa-whatsapp mt-3">
                            <div class="pw-titulo">
                                <i class="bi bi-eye me-1"></i>As&iacute; le llega al paciente
                            </div>
                            <?php /* Sin nl2br: el globo tiene
                                     white-space:pre-wrap, asi que los
                                     saltos ya se respetan solos y un
                                     <br> encima los duplicaria. Ademas
                                     el navegador rehace esto con
                                     textContent en cuanto se teclea: si
                                     PHP metiera <br>, la vista previa
                                     cambiaria de espaciado en la
                                     primera tecla. */ ?>
                            <div class="pw-globo" id="recPrevia"><?=
                                e(AgendaExtra::sustituir($mensajeRec, $muestra))
                            ?></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
/* Las reglas guardadas se pasan al JavaScript tal cual: es una lista
   corta y pedirla por AJAX seria un viaje de mas. */
window.REGLAS_RECORDATORIO = <?= json_encode($reglas, JSON_UNESCAPED_UNICODE) ?>;

/* Los mismos datos de muestra que uso PHP para la primera vista previa.
   Si el navegador usara otros, la vista previa cambiaria de contenido
   en cuanto alguien tocara una tecla, y pareceria que el mensaje se
   descompuso. */
window.MUESTRA_RECORDATORIO = <?= json_encode($muestra, JSON_UNESCAPED_UNICODE) ?>;

/* Las dos plantillas y la muestra de cada una. El navegador cambia de
   una a otra sin pedirle nada al servidor: son dos textos cortos y ya
   estan aqui. */
window.MENSAJES_RECORDATORIO = <?= json_encode([
    'con' => [
        'texto'   => $mensajeRec,
        'muestra' => $muestra,
        'ayuda'   => 'Se manda cuando la cita ya tiene hora.',
    ],
    'sin' => [
        'texto'   => $mensajeSinHora,
        'muestra' => $muestraSinHora,
        'ayuda'   => 'Se manda cuando solo se aparto el dia. No prometa una '
                   . 'hora: la etiqueta {hora} saldra como "por confirmar".',
    ],
], JSON_UNESCAPED_UNICODE) ?>;
</script>
