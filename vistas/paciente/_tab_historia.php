<?php
/**
 * Pestania Historia clinica.
 *
 * Sigue el orden de las dos hojas que usa el consultorio en papel, para
 * que quien captura vaya leyendo del formato impreso sin brincar de un
 * lado a otro de la pantalla.
 */
$secciones = Conexion::todos(
    'SELECT seccion, clave, nombre, pide_detalle
       FROM catalogo_clinico WHERE estatus = 1
      ORDER BY seccion, orden'
);

$porSeccion = [];
foreach ($secciones as $sc) {
    $porSeccion[$sc['seccion']][] = $sc;
}

/** Grupo de botones si / no. */
function siNo(string $campo, string $etiqueta = ''): string
{
    $html = $etiqueta !== ''
        ? '<label class="form-label">' . $etiqueta . '</label>' : '';
    return $html . '
      <div class="grupo-sino" data-campo="' . e($campo) . '">
        <button type="button" class="opcion-sino" data-valor="1">S&iacute;</button>
        <button type="button" class="opcion-sino" data-valor="0">No</button>
        <input type="hidden" name="' . e($campo) . '">
      </div>';
}

/** Grupo de opciones numeradas. */
function opciones(string $campo, array $lista, string $etiqueta = ''): string
{
    $html = $etiqueta !== ''
        ? '<label class="form-label">' . $etiqueta . '</label>' : '';
    $html .= '<div class="grupo-sino" data-campo="' . e($campo) . '">';
    foreach ($lista as $valor => $texto) {
        $html .= '<button type="button" class="opcion-sino" data-valor="'
              . (int) $valor . '">' . $texto . '</button>';
    }
    return $html . '<input type="hidden" name="' . e($campo) . '"></div>';
}

/** Bloque de casillas de una seccion del catalogo. */
function casillas(array $lista, string $titulo): string
{
    $html = '<p class="form-label mt-2">' . $titulo . '</p><div class="rejilla-casillas">';
    foreach ($lista as $c) {
        $html .= '
          <label class="casilla-clinica">
            <input type="checkbox" class="marca-clinica" value="' . e($c['clave']) . '"
                   data-detalle="' . (int) $c['pide_detalle'] . '">
            <span>' . e($c['nombre']) . '</span>
          </label>';
        if ((int) $c['pide_detalle'] === 1) {
            $html .= '<input type="text" class="form-control form-control-sm detalle-marca d-none"
                             data-de="' . e($c['clave']) . '" placeholder="Especifique"
                             maxlength="255">';
        }
    }
    return $html . '</div>';
}
?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <p class="form-label mb-0">Historia cl&iacute;nica</p>
        <p class="text-secondary mb-0" style="font-size:12px" id="hcCaptura">
            Sigue el orden de la hoja impresa del consultorio.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-light d-none" id="hcPrellenar">
            <i class="bi bi-magic me-1"></i> Prellenar lo habitual
        </button>
        <button type="button" class="btn btn-outline-morado d-none" id="hcImprimir">
            <i class="bi bi-printer me-1"></i> Imprimir para firma
        </button>
    </div>
</div>

<?php /* Mismo aviso que el del expediente electronico: un pendiente
         que se ve, no uno que hay que ir a buscar. Se esconde solo en
         cuanto la historia existe. */ ?>
<div class="aviso-vinculado mb-3 d-none" id="hcAvisoPendiente">
    <i class="bi bi-clipboard2-x"></i>
    <div>
        <b>Este paciente todav&iacute;a no tiene historia cl&iacute;nica.</b><br>
        Es lo primero que se captura: de aqu&iacute; salen las alertas de
        alergias y de enfermedades que aparecen en cada consulta y en
        cada plan de tratamiento.
        <div class="mt-2">
            <button type="button" class="btn btn-sm btn-morado" id="hcEmpezar">
                <i class="bi bi-pencil-square me-1"></i> Capturarla ahora
            </button>
            <button type="button" class="btn btn-sm btn-light" id="hcPrellenarAviso">
                <i class="bi bi-magic me-1"></i> Prellenar lo habitual y revisar
            </button>
        </div>
    </div>
</div>

<!-- Alertas que ya vienen del expediente -->
<div class="aviso-almacen mb-3 d-none" id="hcAlertas">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        <b>Antecedentes registrados que requieren atenci&oacute;n</b>
        <div class="mt-1" id="hcAlertasLista"></div>
    </div>
</div>

<form id="formHistoria" autocomplete="off">
  <div id="pasosHistoria">

    <!-- ============ 1. Ficha de identificacion ============ -->
    <section class="paso" data-titulo="Identificaci&oacute;n" data-icono="bi-person-badge">
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person-badge me-2"></i>Ficha de identificaci&oacute;n</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-4">
                    <label class="form-label">Le gusta que le llamen</label>
                    <input type="text" class="form-control" name="apodo" maxlength="60">
                </div>
                <div class="col-3">
                    <label class="form-label">Peso (kg)</label>
                    <input type="number" class="form-control" name="peso_kg"
                           step="0.1" min="1" max="200">
                </div>
                <div class="col-5">
                    <label class="form-label">Personaje favorito</label>
                    <input type="text" class="form-control" name="personaje_favorito" maxlength="80">
                </div>

                <div class="col-5">
                    <label class="form-label">Escuela</label>
                    <input type="text" class="form-control" name="escuela" maxlength="120">
                </div>
                <div class="col-3">
                    <label class="form-label">Grado escolar</label>
                    <input type="text" class="form-control" name="grado_escolar" maxlength="40">
                </div>
                <div class="col-4">
                    <label class="form-label">&iquest;Qui&eacute;n nos recomend&oacute;?</label>
                    <input type="text" class="form-control" name="quien_recomendo" maxlength="120">
                </div>

                <div class="col-3">
                    <?= siNo('primera_visita_dentista', '&iquest;Es la primera visita al dentista?') ?>
                </div>
                <?php /* Cuelga del NO, no del SI.

                         Si es la primera visita al dentista, no hay una
                         "ultima vez" que preguntar: la respuesta seria
                         "nunca" en todos los casos. La pregunta tiene
                         sentido justo al reves, cuando el paciente dice
                         que ya ha ido antes. */ ?>
                <div class="col-4"
                     data-depende="primera_visita_dentista" data-cuando="0">
                    <label class="form-label">&iquest;Cu&aacute;ndo fue la &uacute;ltima vez?</label>
                    <input type="text" class="form-control" name="ultima_visita_dentista"
                           maxlength="120" placeholder="HACE 6 MESES, HACE 1 AÑO...">
                </div>
                <div class="col-5">
                    <label class="form-label">&iquest;Cu&aacute;l es el motivo de la consulta?</label>
                    <input type="text" class="form-control" name="motivo_consulta" maxlength="255">
                </div>
            </div>
        </div>
    </div>

    </section>

    <!-- ============ 2. Antecedentes no patologicos ============ -->
    <section class="paso" data-titulo="No patol&oacute;gicos" data-icono="bi-heart">
    <div class="row g-3">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-heart me-2"></i>Antecedentes no patol&oacute;gicos
                </div>
                <div class="card-body">

                    <p class="form-label">Embarazo y nacimiento</p>
                    <div class="row g-3 mb-2">
                        <div class="col-6"><?= siNo('embarazo_normal', 'Embarazo normal') ?></div>
                        <div class="col-6">
                            <?= opciones('tipo_nacimiento', [
                                1 => 'A t&eacute;rmino', 2 => 'Prematuro', 3 => 'Post-t&eacute;rmino',
                            ], 'Nacimiento') ?>
                        </div>
                        <div class="col-6">
                            <?= opciones('tipo_parto', [1 => 'Natural', 2 => 'Ces&aacute;rea'], 'Parto') ?>
                        </div>
                        <div class="col-6"><?= siNo('complicaciones_parto', 'Complicaciones') ?></div>
                        <div class="col-12"
                     data-depende="complicaciones_parto" data-cuando="1">
                            <input type="text" class="form-control form-control-sm"
                                   name="complicaciones_detalle" maxlength="255"
                                   placeholder="Especifique las complicaciones">
                        </div>
                    </div>

                    <hr>

                    <p class="form-label">Alimentaci&oacute;n del reci&eacute;n nacido</p>
                    <div class="row g-3 mb-2">
                        <div class="col-6"><?= siNo('lactancia_materna', 'Lactancia materna') ?></div>
                        <div class="col-6"
                     data-depende="lactancia_materna" data-cuando="1">
                            <label class="form-label">&iquest;Hasta cu&aacute;ndo?</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="lactancia_hasta" maxlength="60">
                        </div>
                        <div class="col-6"><?= siNo('formula', 'F&oacute;rmula') ?></div>
                        <div class="col-6"
                     data-depende="formula" data-cuando="1">
                            <label class="form-label">&iquest;Hasta cu&aacute;ndo?</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="formula_hasta" maxlength="60">
                        </div>
                    </div>

                    <hr>

                    <p class="form-label">Nutrici&oacute;n y dieta</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Comienzo de la alimentaci&oacute;n complementaria</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="inicio_alimentacion_complementaria" maxlength="60"
                                   placeholder="A LOS 6 MESES...">
                        </div>
                        <div class="col-6">
                            <?= opciones('dieta_completa', [1 => 'Completa', 0 => 'Incompleta'], 'Dieta actual') ?>
                        </div>
                        <div class="col-6"
                     data-depende="dieta_completa" data-cuando="0">
                            <label class="form-label">&iquest;Por qu&eacute;?</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="dieta_porque" maxlength="255">
                        </div>
                        <div class="col-5">
                            <label class="form-label">Momentos de az&uacute;car</label>
                            <select class="form-select" name="momentos_azucar">
                                <option value="">Seleccionar...</option>
                                <option value="REGULAR">Regular</option>
                                <option value="DIARIO">Diario</option>
                                <option value="EVENTUALMENTE">Eventualmente</option>
                                <option value="NO CONSUME">No consume</option>
                            </select>
                            <div class="form-text">
                                Con qu&eacute; frecuencia consume az&uacute;car libre o
                                a&ntilde;adida.
                            </div>
                        </div>
                        <div class="col-7"
                     data-depende="momentos_azucar" data-cuando="!NO CONSUME">
                            <label class="form-label">&iquest;Qu&eacute; alimentos o bebidas con az&uacute;car?</label>
                            <textarea class="form-control" name="alimentos_azucar" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    </section>

    <!-- ============ 3. Antecedentes patologicos ============ -->
    <section class="paso" data-titulo="Patol&oacute;gicos" data-icono="bi-clipboard2-pulse">
    <div class="row g-3">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-clipboard2-pulse me-2"></i>Antecedentes patol&oacute;gicos
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <?= opciones('estado_salud', [
                                1 => 'Bueno', 2 => 'Regular', 3 => 'Malo',
                            ], '&iquest;C&oacute;mo considera el estado de salud del paciente?') ?>
                        </div>

                        <div class="col-5">
                            <?= siNo('enfermedad_cronica',
                                '&iquest;Enfermedad cr&oacute;nica o alteraci&oacute;n gen&eacute;tica?') ?>
                        </div>
                        <div class="col-7"
                     data-depende="enfermedad_cronica" data-cuando="1">
                            <label class="form-label">Especifique</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="enfermedad_detalle" maxlength="255">
                        </div>

                        <div class="col-5">
                            <?= siNo('discapacidad', '&iquest;Tiene alguna discapacidad?') ?>
                            <div class="form-text">Visual, auditiva, motora, sensitiva.</div>
                        </div>
                        <div class="col-7"
                     data-depende="discapacidad" data-cuando="1">
                            <label class="form-label">Especifique</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="discapacidad_detalle" maxlength="255">
                        </div>

                        <div class="col-5">
                            <?= siNo('alergia', '&iquest;Es al&eacute;rgico a alg&uacute;n alimento o medicamento?') ?>
                        </div>
                        <div class="col-7"
                     data-depende="alergia" data-cuando="1">
                            <label class="form-label">Especifique</label>
                            <input type="text" class="form-control form-control-sm is-alergia"
                                   name="alergia_detalle" maxlength="255">
                        </div>

                        <div class="col-5">
                            <?= siNo('tratamiento_medico', '&iquest;Est&aacute; bajo tratamiento m&eacute;dico?') ?>
                        </div>
                        <div class="col-7"
                     data-depende="tratamiento_medico" data-cuando="1">
                            <label class="form-label">Especifique</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="tratamiento_detalle" maxlength="255">
                        </div>

                        <div class="col-5">
                            <?= siNo('cirugias', '&iquest;Ha tenido cirug&iacute;as?') ?>
                        </div>
                        <div class="col-7"
                     data-depende="cirugias" data-cuando="1">
                            <label class="form-label">Especifique</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="cirugias_detalle" maxlength="255">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </section>

    <!-- ============ 4. Odontologicos y habitos ============ -->
    <section class="paso" data-titulo="Odontol&oacute;gicos y h&aacute;bitos" data-icono="bi-emoji-smile">
    <div class="row g-3">
        <div class="col-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-emoji-smile me-2"></i>Antecedentes odontol&oacute;gicos
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <?= siNo('experiencia_previa',
                                '&iquest;Experiencia odontol&oacute;gica previa?') ?>
                        </div>
                        <div class="col-6">
                            <?= siNo('recibio_prevencion',
                                '&iquest;Recibi&oacute; informaci&oacute;n de medidas preventivas?') ?>
                        </div>
                        <div class="col-6"><?= siNo('usa_pasta_dental', '&iquest;Usa pasta dental?') ?></div>
                        <div class="col-6">
                            <?php /* En palabras, no un numero.

                                     El tutor no contesta "2": contesta
                                     "en la maniana y en la noche" o
                                     "solo cuando se acuerda". Pidiendo
                                     un entero, quien captura elige uno
                                     y el dato queda mas limpio y menos
                                     cierto. */ ?>
                            <label class="form-label">Cepillados al d&iacute;a</label>
                            <input type="text" class="form-control" name="cepillados_dia"
                                   maxlength="100"
                                   placeholder="EN LA MANIANA Y EN LA NOCHE">
                        </div>
                        <div class="col-5">
                            <?= siNo('otro_higiene', '&iquest;Usa otro elemento de higiene?') ?>
                        </div>
                        <div class="col-7"
                     data-depende="otro_higiene" data-cuando="1">
                            <label class="form-label">Especifique</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="otro_higiene_detalle" maxlength="255"
                                   placeholder="HILO DENTAL, ENJUAGUE...">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ Habitos ============ -->
        <div class="col-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-repeat me-2"></i>H&aacute;bitos</div>
                <div class="card-body">
                    <?= casillas($porSeccion['HABITO'] ?? [], 'Marca los que apliquen') ?>
                </div>
            </div>
        </div>
    </div>

    </section>

    <!-- ============ 5. Examen bucal ============ -->
    <section class="paso" data-titulo="Examen bucal" data-icono="bi-search">
    <div class="card">
        <div class="card-header"><i class="bi bi-search me-2"></i>Examen bucal</div>
        <div class="card-body">

            <?php /* La doctora dejo tres hallazgos y los quiere en
                     texto libre, no en opciones. En la practica lo que
                     escribia era "FRENILLO LINGUAL CORTO" o "AFTA EN
                     CARRILLO DERECHO": descripciones que no caben en
                     una lista cerrada, y que forzadas a una opcion
                     perdian justo el detalle que importaba.

                     Tipo de denticion se queda aunque el resto de
                     "tejidos duros" salio: el odontograma y la hoja
                     impresa lo usan. */ ?>

            <div class="row g-3">
                <?php /* Primero: ¿salio normal?

                         Con solo los tres hallazgos escritos no se podia
                         distinguir un examen NORMAL de uno que no se hizo:
                         los dos dejaban los campos en blanco. En una hoja
                         firmada son dos cosas muy distintas, y la que no se
                         podia afirmar era justamente la buena.

                         Viene en NORMAL porque es lo que pasa en la mayoria
                         de los pacientes, y porque quien captura ya tiene
                         doce preguntas que contestar: lo que se aparta de lo
                         normal es lo que merece un clic. */ ?>
                <div class="col-12">
                    <?= opciones('examen_bucal_normal', [
                        1 => 'Normal', 0 => 'Anormal',
                    ], '&iquest;C&oacute;mo sali&oacute; el examen bucal?') ?>
                    <div class="form-text">
                        En <b>Normal</b> los tres campos de abajo pueden
                        quedarse vac&iacute;os. En <b>Anormal</b>, describe lo
                        que viste.
                    </div>
                </div>

                <div class="col-4">
                    <label class="form-label">Frenillo</label>
                    <input type="text" class="form-control" name="frenillo_detalle"
                           maxlength="255" placeholder="C&Oacute;MO SE VE">
                </div>
                <div class="col-4">
                    <label class="form-label">Lesiones mucosas</label>
                    <input type="text" class="form-control" name="mucosa"
                           maxlength="255" placeholder="QU&Eacute; SE OBSERVA">
                </div>
                <div class="col-4">
                    <label class="form-label">Lengua</label>
                    <input type="text" class="form-control" name="lengua_detalle"
                           maxlength="255" placeholder="C&Oacute;MO SE VE">
                </div>

                <div class="col-12">
                    <?= opciones('tipo_denticion', [
                        1 => 'Temporal', 2 => 'Mixta', 3 => 'Permanente',
                    ], 'Tipo de dentici&oacute;n') ?>
                </div>
            </div>
        </div>
    </div>

    </section>

  </div>
</form>

<?php /* =====================================================================
         La pregunta que cuelga de una respuesta
         =====================================================================
         Doce preguntas de la historia abren otra: "¿alergico? -> ¿a que?".
         Hasta ahora ese segundo campo aparecia en el hueco de al lado,
         y la rejilla se descuadraba: el campo brotaba en medio de la
         fila, empujaba a los vecinos y quien estaba capturando perdia de
         vista donde iba. Con doce de estos, la pantalla se movia sola.

         Ahora la pregunta sale en una ventanita, se contesta, y la
         respuesta se queda escrita junto al selector. La rejilla no se
         mueve nunca.

         Un solo modal para las doce: el titulo, la etiqueta y el campo
         se rellenan al abrirlo. Doce modales serian doce bloques de
         HTML casi iguales, y el primero que alguien tocara dejaria a
         los otros once distintos.
         ===================================================================== */ ?>
<div class="modal fade" id="modalPreguntaExtra" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pxTitulo"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" id="pxEtiqueta" for="pxValor"></label>
        <input type="text" class="form-control" id="pxValor" maxlength="255">
        <div class="form-text" id="pxAyuda"></div>

        <?php /* El aviso de que es opcional, fijo en la ventana.

                 Sin el, dejar el cuadro vacio parece un error: la
                 ventana se abrio sola al contestar "si" y no hay nada
                 que diga que se puede cerrar sin escribir. El tutor que
                 no se acuerda del detalle existe, y antes de este aviso
                 la salida era inventarlo o cambiar el "si" por un "no".

                 Dice tambien que es lo que va a quedar escrito, porque
                 el renglon no se queda en blanco: un blanco en una hoja
                 firmada se lee como "no se pregunto" y se puede llenar
                 a mano despues de la firma. */ ?>
        <div class="aviso-opcional mt-2">
          <i class="bi bi-info-circle"></i>
          <span>Este detalle es <b>opcional</b>. Si lo dejas vac&iacute;o
                quedar&aacute; como <b>NO ESPECIFICA</b> y la respuesta
                anterior se conserva.</span>
        </div>
      </div>
      <div class="modal-footer">
        <?php /* "Cerrar" y ya no "Cancelar".

                 Decia Cancelar porque cerrar sin contestar DESHACIA la
                 respuesta que abrio la ventana: el "si" volvia a como
                 estaba. Eso dejo de pasar al volverse opcional el
                 detalle —el "si" se queda y el complemento sale como NO
                 ESPECIFICA—, asi que el boton ya no cancela nada y
                 llamarlo Cancelar seria mentir sobre lo que hace. */ ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
          Cerrar
        </button>
        <button type="button" class="btn btn-morado" id="pxGuardar">
          <i class="bi bi-check-lg me-1"></i> Guardar
        </button>
      </div>
    </div>
  </div>
</div>
