<?php
/**
 * Detalle de consulta: captura y expediente de la cita.
 *
 * La pantalla esta partida en dos columnas con papeles distintos:
 *
 *   Izquierda  - lo clinico: motivo, exploracion, diagnostico, plan.
 *                Es lo que se escribe mientras el paciente esta
 *                enfrente, asi que va del lado de la lectura y con los
 *                campos en el orden en que se pregunta.
 *
 *   Derecha    - lo que se genera: recetas, documentos y notas. Son
 *                cosas que se agregan, no que se llenan, y cada una
 *                abre su propio dialogo.
 *
 * Una consulta cerrada bloquea la columna izquierda pero deja viva la
 * derecha: el registro clinico no se corrige, pero se le siguen
 * anexando papeles y notas.
 */
$idConsulta   = paramInt('id');
$idExpediente = paramInt('paciente');
$idPlan       = paramInt('plan');
$idCita       = paramInt('cita');

// Viniendo de una cita, la consulta hereda lo que ya se sabe. Es el
// unico punto donde ese enlace existe: despues nadie va a acordarse de
// que consulta salio de que cita.
$desdeCita = null;
if ($idCita && !$idConsulta) {
    $desdeCita = Conexion::uno(
        'SELECT c.id_cita, c.id_registro_paciente, c.id_usuario_dentista,
                c.fecha_inicio, c.motivo, pr.nombre_procedimiento
           FROM cita c
           LEFT JOIN procedimiento pr ON pr.id_procedimiento = c.id_procedimiento
          WHERE c.id_cita = :c',
        [':c' => $idCita]
    );

    if ($desdeCita !== null) {
        $idExpediente = (int) $desdeCita['id_registro_paciente'];

        // Si ya se capturo la consulta de esa cita, se abre en lugar
        // de crear una segunda.
        $ya = Conexion::valor(
            'SELECT id_consulta FROM consulta
              WHERE id_cita = :c AND estatus = 1 LIMIT 1',
            [':c' => $idCita]
        );
        if ($ya !== null) {
            $idConsulta = (int) $ya;
            $desdeCita  = null;
        }
    }
}

// Abriendo una consulta existente, el paciente y el plan salen de ella.
if ($idConsulta) {
    $base = Conexion::uno(
        'SELECT id_registro_paciente, id_plan_tratamiento
           FROM consulta WHERE id_consulta = :c AND estatus = 1',
        [':c' => $idConsulta]
    );
    if ($base === null) {
        echo '<div class="alert alert-warning">La consulta no existe. '
           . '<a href="' . URL_BASE . '/index.php?modulo=consultas&vista=consultas">'
           . 'Volver a la lista</a></div>';
        return;
    }
    $idExpediente = (int) $base['id_registro_paciente'];
    $idPlan       = (int) ($base['id_plan_tratamiento'] ?? 0);
}

$paciente = $idExpediente
    ? Conexion::uno(
        'SELECT vp.* FROM v_paciente vp
          WHERE vp.id_registro_paciente = :r',
        [':r' => $idExpediente])
    : null;

// Mismo origen que el plan de tratamiento: la historia clinica, no
// expediente_antecedente. Ver el comentario en AlertasClinicas.
$alertasClinicas = $idExpediente ? AlertasClinicas::de($idExpediente) : [];

if ($paciente === null) {
    echo '<div class="alert alert-warning d-flex align-items-center gap-2">'
       . '<i class="bi bi-exclamation-triangle"></i>'
       . 'Selecciona un paciente para registrar la consulta. '
       . '<a href="' . URL_BASE . '/index.php?modulo=paciente&vista=buscar_paciente">'
       . 'Buscar paciente</a></div>';
    return;
}

$dentistas = Conexion::todos(
    'SELECT id_usuario, nombre_completo FROM usuario
      WHERE es_dentista = 1 AND estatus = 1 ORDER BY nombre_completo');

// Planes vivos del paciente, para poder ligar o desligar la consulta.
//
// Viene con la cuenta de consultas que ya lleva cada plan: de ahi sale
// el ordinal del motivo que se propone ("3RA CITA PLAN..."), y se pide
// aqui en una sola consulta en vez de una por plan.
$planes = Conexion::todos(
    'SELECT pt.id_plan_tratamiento, pt.folio, pt.descripcion,
            pt.id_estado_plan, ep.nombre_estado,
            (SELECT COUNT(*) FROM consulta cx
              WHERE cx.id_plan_tratamiento = pt.id_plan_tratamiento
                AND cx.estatus = 1) AS consultas
       FROM plan_tratamiento pt
       JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
      WHERE pt.id_registro_paciente = :r AND pt.id_estado_plan IN (1,2,3)
      ORDER BY pt.fecha DESC',
    [':r' => $idExpediente]
);

/*
   Lo que se propone al elegir un plan.

   El motivo se escribe igual en todas las citas de un mismo plan, y
   escribirlo a mano cada vez termina en expedientes donde la misma
   cosa esta escrita de cinco formas. Se propone ya armado y quien
   captura lo corrige si hace falta: es una propuesta, no un campo
   calculado.

   El ordinal NO se guarda en ninguna columna. Se deduce de las
   consultas que el plan ya tiene, asi que no puede quedar
   desincronizado si una consulta se borra o se cambia de plan.
*/
$propuestaPorPlan = [];
foreach ($planes as $p) {
    $propuestaPorPlan[(int) $p['id_plan_tratamiento']] = [
        'motivo' => motivoDeCitaDePlan(
            (string) $p['folio'], (int) $p['consultas']),
    ];
}

// Una consulta nueva de un paciente con planes vivos pregunta de
// entrada a cual pertenece. Si ya se decidio por la direccion (se
// entro desde el plan) no hay nada que preguntar.
$preguntarPlan = !$idConsulta && $planes && !$idPlan;
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <nav style="font-size:12px" class="mb-1">
            <a href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=consultas"
               class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Lista de consultas
            </a>
        </nav>
        <h1 class="titulo-vista">
            <span id="cnTituloFolio"><?= $idConsulta ? '' : 'Nueva consulta' ?></span>
        </h1>
        <p class="subtitulo-vista">
            <a href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= $idExpediente ?>"
               class="text-decoration-none">
                <?= e($paciente['nombre_completo']) ?>
            </a>
            &middot; <?= e($paciente['num_expediente']) ?>
            <?= $paciente['edad'] !== null ? ' &middot; ' . (int) $paciente['edad'] . ' a&ntilde;os' : '' ?>
        </p>
    </div>

    <div class="d-flex gap-2 align-items-center">
        <span class="chip-estado badge-suave d-none" id="cnEstadoChip"></span>
        <?php /* El Guardar vive en dos sitios, y solo uno se ve.

                 Mientras la consulta NO existe, esta abajo, pegado a
                 "Tratamiento realizado": es el ultimo campo que se
                 escribe y el unico sitio vivo de la pantalla, porque
                 todo lo demas esta difuminado. Tenerlo arriba obligaba
                 a subir la vista para guardar lo que se acababa de
                 teclear abajo.

                 Una vez creada, sube aqui. El trabajo deja de ser
                 "llenar el motivo" y pasa a ser ir y venir entre
                 hallazgos, recetas, cargos y notas; el Guardar ya no
                 pertenece al pie de UNA tarjeta sino a la pantalla
                 entera, y su sitio es junto a Cerrar.

                 Son dos botones distintos y no uno que se mueve: mover
                 un nodo entre dos contenedores con Bootstrap de por
                 medio deja de funcionar en cuanto algo mas cambia. Lo
                 que comparten es data-guardar, y el manejador esta
                 delegado, asi que no hay dos escuchas ni dos ids
                 iguales —que es como nacio el bug del boton que se
                 disparaba doble—. */ ?>
        <?php if (Auth::puede('consultas.crear')): ?>
        <button type="button" class="btn btn-outline-morado" id="cnCerrar" disabled>
            <i class="bi bi-lock me-1"></i> Cerrar consulta
        </button>
        <button type="button" class="btn btn-morado d-none"
                id="cnGuardarArriba" data-guardar>
            <i class="bi bi-check-lg me-1"></i> Guardar
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($desdeCita): ?>
<div class="aviso-vinculado mb-3">
    <i class="bi bi-calendar-check-fill"></i>
    <div>
        <b>Consulta de la cita de las
           <?= e(substr((string) $desdeCita['fecha_inicio'], 11, 5)) ?>.</b>
        Se prellenaron la hora, el dentista y el motivo. Al guardar, la
        consulta queda ligada a esa cita.
    </div>
</div>
<?php endif; ?>

<?php if ($alertasClinicas): ?>
<div class="aviso-almacen mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        <b>Alertas cl&iacute;nicas del paciente</b>
        <div class="mt-1">
            <?php foreach ($alertasClinicas as $al): ?>
            <div>
                <b><?= e($al['titulo']) ?>:</b>
                <?= $al['detalle'] !== ''
                    ? e($al['detalle'])
                    : '<i>sin especificar en la historia</i>' ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="aviso-vinculado mb-3 d-none" id="cnAvisoCerrada">
    <i class="bi bi-lock-fill"></i>
    <div>
        <b>Esta consulta est&aacute; cerrada.</b>
        Los datos cl&iacute;nicos ya no se modifican. Si necesitas aclarar
        o corregir algo, agrega una <b>nota de evoluci&oacute;n</b>: as&iacute;
        queda el dato original y la aclaraci&oacute;n, que es como debe
        verse un expediente.
    </div>
</div>

<div class="row g-3">
    <!-- ============ Columna clinica ============ -->
    <div class="col-8">
        <form id="formConsulta" autocomplete="off">
            <input type="hidden" name="id_consulta" id="cnId" value="<?= $idConsulta ?: '' ?>">
            <input type="hidden" name="id_registro_paciente" value="<?= $idExpediente ?>">
            <input type="hidden" name="id_cita" value="<?= $idCita ?: '' ?>">
            <?php /* Que la pantalla sepa si tiene que preguntar. Lo
                     decide PHP, que es quien sabe si el paciente tiene
                     planes vivos y si se entro desde uno. */ ?>
            <input type="hidden" id="cnPreguntarPlan"
                   value="<?= $preguntarPlan ? '1' : '' ?>">

            <?php /* Cabecera compacta.

                     Estos tres campos se contestan una vez, al abrir la
                     consulta, y despues nadie los vuelve a mirar. Antes
                     ocupaban una tarjeta entera con etiquetas grandes,
                     y empujaban hacia abajo lo que de verdad se usa
                     durante la cita. Ahora van en una barra delgada
                     arriba, con las etiquetas pegadas al campo. */ ?>
            <?php /* Cada campo tiene dos caras: el texto y el control.

                     En una consulta ya guardada se lee el texto; el
                     lapiz descubre el control de ESE campo nada mas.
                     Es a proposito: la fecha, el dentista y el plan son
                     tres datos que se fijan al abrir la consulta y
                     cambiar uno por descuido no se nota —un combo
                     abierto y una rueda de raton bastan para mover la
                     consulta a otro plan y arrastrar sus papeles con
                     ella—. En una consulta nueva no hay nada que leer,
                     asi que se entra directo en modo edicion. */ ?>
            <div class="barra-consulta mb-3" id="cnBarra">
                <label data-campo="fecha">
                    <span>
                        Fecha y hora
                        <button type="button" class="lapiz-campo d-none"
                                data-editar="fecha" title="Cambiar la fecha y hora">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </span>
                    <div class="campo-texto d-none" data-texto="fecha">&mdash;</div>
                    <input type="datetime-local" class="form-control form-control-sm"
                           name="fecha_hora" id="cnFechaHora"
                           value="<?= $desdeCita
                               ? e(str_replace(' ', 'T',
                                   substr((string) $desdeCita['fecha_inicio'], 0, 16)))
                               : date('Y-m-d\TH:i') ?>">
                </label>

                <label data-campo="dentista">
                    <span>
                        Dentista <b class="req">*</b>
                        <button type="button" class="lapiz-campo d-none"
                                data-editar="dentista" title="Cambiar el dentista">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </span>
                    <div class="campo-texto d-none" data-texto="dentista">&mdash;</div>
                    <select class="form-select form-select-sm"
                            name="id_usuario_dentista" required>
                        <?php $dentistaPre = $desdeCita
                            ? (int) $desdeCita['id_usuario_dentista']
                            : (int) $usuarioSesion['id_usuario']; ?>
                        <?php foreach ($dentistas as $d): ?>
                        <option value="<?= (int) $d['id_usuario'] ?>"
                            <?= (int) $d['id_usuario'] === $dentistaPre ? 'selected' : '' ?>>
                            <?= e($d['nombre_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label data-campo="plan">
                    <span>
                        Plan de tratamiento
                        <button type="button" class="lapiz-campo d-none"
                                data-editar="plan" title="Cambiar el plan de tratamiento">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </span>
                    <div class="campo-texto d-none" data-texto="plan">&mdash;</div>
                    <select class="form-select form-select-sm"
                            name="id_plan_tratamiento" id="cnPlan"
                            title="Decide en qué parte del expediente quedan los papeles de esta consulta.">
                        <option value="">Consulta general (sin plan)</option>
                        <?php foreach ($planes as $p): ?>
                        <option value="<?= (int) $p['id_plan_tratamiento'] ?>"
                            data-motivo="<?= e($propuestaPorPlan[(int) $p['id_plan_tratamiento']]['motivo']) ?>"
                            <?= (int) $p['id_plan_tratamiento'] === $idPlan ? 'selected' : '' ?>>
                            <?= e($p['folio']) ?> &mdash; <?= e($p['descripcion'] ?? $p['nombre_estado']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <?php /* El acomodo: a la izquierda lo clinico de hoy, a la
                     derecha lo que se escribe.

                     Arriba a la izquierda el motivo —una linea— y
                     debajo la exploracion, que es donde se trabaja
                     durante la cita. A su lado, a todo el alto, las
                     notas de evolucion.

                     Se fueron dos campos: "padecimiento actual", que en
                     la practica repetia el motivo con otras palabras, y
                     "tratamiento realizado", que decia lo mismo que ya
                     cuentan los hallazgos y las notas. Tres cajas de
                     texto pidiendo variaciones de la misma cosa hacen
                     que se llene una y se dejen dos vacias, y despues
                     nadie sabe en cual estaba lo importante. */ ?>
            <div class="row g-3 mb-3">
                <div class="col-7">
                    <div class="card mb-3">
                        <div class="card-header">
                            <i class="bi bi-chat-left-quote me-2"></i>Motivo de consulta
                        </div>
                        <div class="card-body py-2">
                            <?php /* Prellenado cuando se entro desde un plan.

                                     Lo pone PHP y no el JavaScript porque aqui
                                     el plan ya se sabe al pintar la pantalla:
                                     dejarlo para despues haria aparecer el
                                     texto un instante despues de la vista, y
                                     eso se lee como si el sistema lo hubiera
                                     escrito solo a espaldas de quien captura. */ ?>
                            <?php $pre = $propuestaPorPlan[$idPlan] ?? null; ?>
                            <input type="text" class="form-control form-control-sm"
                                   name="motivo" maxlength="255" required
                                   value="<?= $pre ? e($pre['motivo']) : '' ?>"
                                   placeholder="LO QUE DICE EL TUTOR O EL PACIENTE">

                            <?php /* El Guardar, aqui abajo.

                                     Debajo del unico campo que se escribe y
                                     dentro de la unica tarjeta viva antes del
                                     primer guardado: mientras la consulta no
                                     exista, lo demas esta difuminado y este
                                     boton es lo unico que se puede picar. */ ?>
                            <?php if (Auth::puede('consultas.crear')): ?>
                            <div class="pie-guardar" id="cnPieGuardar">
                                <span class="texto-guardar">
                                    Guarda para poder agregar hallazgos,
                                    recetas, cargos y notas.
                                </span>
                                <button type="button" class="btn btn-morado"
                                        id="cnGuardar" data-guardar>
                                    <i class="bi bi-check-lg me-1"></i> Guardar
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php /* Exploracion: un hallazgo a la vez.

                             Antes era una caja de texto donde terminaban
                             tres observaciones pegadas en un parrafo:
                             "CARIES OCLUSAL EN 46. GINGIVITIS LEVE. MANCHA
                             BLANCA EN 11". Asi no se puede ligar una
                             radiografia a UN hallazgo, ni saber en la
                             siguiente cita cual de los tres se resolvio. */ ?>
                    <div class="card zona-sin-consulta">
                        <div class="card-header d-flex align-items-center
                                    justify-content-between">
                            <span><i class="bi bi-search-heart me-2"></i>Exploraci&oacute;n</span>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-link p-0 d-none"
                                        id="cnAlbumHallazgos"
                                        title="Ver todas las imágenes de la consulta">
                                    <i class="bi bi-images"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-rosa"
                                        id="cnNuevoHallazgo">
                                    <i class="bi bi-plus-lg"></i> Hallazgo
                                </button>
                            </div>
                        </div>
                        <div class="card-body py-2" id="cnHallazgos">
                            <div class="exp-vacio">
                                Sin hallazgos. Agrega uno por cada cosa que
                                observes, en boca o en placa. Con subir la
                                foto basta: la descripci&oacute;n es opcional.
                            </div>
                        </div>
                    </div>
                </div>

                <?php /* Notas de evolucion, a todo el alto de la columna.

                         Estuvieron abajo de las recetas, en la columna
                         angosta de los anexos. Ahi caben dos renglones y
                         son lo que convierte un expediente en una historia
                         que se puede leer: el "mejoro", el "el nino llego
                         asustado", el "la mama pregunto por". Al lado de la
                         exploracion tienen el alto para que se vean varias
                         sin desplazarse. */ ?>
                <div class="col-5">
                    <div class="card borde-destacado h-100 zona-sin-consulta">
                        <div class="card-header d-flex align-items-center
                                    justify-content-between">
                            <span><i class="bi bi-journal-text me-2"></i>Notas de evoluci&oacute;n</span>
                            <button type="button" class="btn btn-sm btn-morado"
                                    id="cnNuevaNota" disabled>
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <div class="card-body py-2" id="cnNotas">
                            <div class="exp-vacio">
                                Sin notas. Sirven para dejar constancia de lo
                                que se observ&oacute; o se acord&oacute; y no
                                cabe en los campos de la izquierda. No generan
                                pendiente de digitalizaci&oacute;n.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>

    <!-- ============ Columna de anexos ============ -->
    <div class="col-4">
        <?php /* Todo lo de esta columna necesita que la consulta ya
                 exista: un documento tiene que colgar de algo.

                 Antes se veia entera y con un aviso de texto arriba
                 explicando que no servia todavia. El aviso se leia una
                 vez y despues se volvia parte del decorado, asi que lo
                 que pasaba era picar un boton, no ver nada, y volver a
                 picarlo. Ahora la zona se difumina y no recibe clics:
                 el estado se ve sin leer nada. */ ?>
        <div class="zona-sin-consulta" id="cnAnexos">
        <?php if (Auth::puede('cajas.cargo')): ?>
        <?php /* Lo que salio en la consulta y hay que cobrar. Se
                 registra aqui, en caliente, porque si se deja para
                 despues nadie se acuerda de que se hizo de mas. */ ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-cash-coin me-2"></i>Pendientes de pago</span>
                <button type="button" class="btn btn-sm btn-rosa" id="cnNuevoCargo" disabled>
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body py-2" id="cnCargos">
                <div class="exp-vacio">Sin cargos.</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ---------- Recetas ---------- -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-prescription2 me-2"></i>Recetas</span>
                <button type="button" class="btn btn-sm btn-rosa" id="cnNuevaReceta" disabled>
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body py-2" id="cnRecetas">
                <div class="exp-vacio">Sin recetas.</div>
            </div>
        </div>

        <?php /* Las notas de evolucion se fueron de aqui.

                 Estuvieron en esta columna, debajo de Recetas, con el
                 argumento de que una nota es un anexo y no un campo del
                 registro. El argumento era bueno y el resultado no: en
                 una columna de este ancho caben dos renglones, y las
                 notas son lo que se LEE en la siguiente cita. Ahora van
                 al lado de la exploracion, a todo el alto. */ ?>

        <?php /* Se fue la tarjeta "Documentos de la consulta".

                 Era una lista de los cuatro tipos de papel que puede
                 traer una consulta, cada uno con su cabecera y su
                 "Sin archivos", y en la practica tres de los cuatro
                 nunca se usaban desde aqui: las radiografias y las
                 fotos entran por su hallazgo —que es lo que les da
                 sentido clinico— y la receta trae su propio pendiente.
                 Ocupaba media columna para decir "Sin archivos" cuatro
                 veces.

                 Pero hacia dos cosas de verdad, y las dos se
                 conservan:

                   1. Adjuntar el escaneo de la receta firmada. Eso
                      bajo al renglon de su receta, que es donde se
                      busca.
                   2. Anexar un estudio externo o una interconsulta
                      —papel que llega de fuera y no es hallazgo ni
                      receta—. Eso es este enlace: un renglon en vez
                      de una tarjeta, porque pasa pocas veces.

                 Lo subido sigue viendose completo en el expediente del
                 paciente, agrupado bajo su consulta. */ ?>
        <div class="pie-anexo">
            <button type="button" class="btn btn-sm btn-link p-0" id="cnOtroDocumento">
                <i class="bi bi-paperclip"></i>
                Anexar un estudio externo o interconsulta
            </button>
            <div class="cuenta-anexos d-none" id="cnCuentaDocumentos"></div>
        </div>
        </div><!-- /cnAnexos -->
    </div>
</div>

<?php /* Campo de archivo escondido.

         Adjuntar el escaneo de un pendiente son dos clics: picar el
         boton y elegir el archivo. Un modal para eso solo agregaria
         pasos a algo que no tiene nada que decidir. */ ?>
<input type="file" id="cnArchivoPendiente" class="d-none">

<?php require __DIR__ . '/_modales_consulta.php'; ?>
<?php require RUTA_VISTAS . '/consultas/_modal_procedimiento.php'; ?>
