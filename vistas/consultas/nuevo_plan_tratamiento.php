<?php
/**
 * Captura de un plan de tratamiento.
 *
 * Sigue la hoja impresa: arriba el diagnostico y abajo el desglose de
 * procedimientos con fecha, costo y proxima cita.
 */
$idExpediente = paramInt('id');
$idPlan       = paramInt('plan');

$paciente = $idExpediente
    ? Conexion::uno(
        'SELECT vp.* FROM v_paciente vp
          WHERE vp.id_registro_paciente = :r',
        [':r' => $idExpediente])
    : null;

/*
   Las alertas salen de la HISTORIA CLINICA, no de expediente_antecedente.

   Esa tabla se lleno con los datos de prueba y nadie la captura en el
   uso real: el aviso salia en los pacientes inventados y no salia en
   los de verdad. Justo al reves de para lo que sirve.

   Las que importan —alergia, enfermedad cronica, discapacidad,
   tratamiento medico— son cuatro preguntas que la doctora si contesta
   en la historia, y de ahi se leen ahora.
*/
$alertasClinicas = $idExpediente ? AlertasClinicas::de($idExpediente) : [];

if ($paciente === null) {
    echo '<div class="alert alert-warning d-flex align-items-center gap-2">'
       . '<i class="bi bi-exclamation-triangle"></i>'
       . 'Selecciona un paciente para capturar su plan de tratamiento. '
       . '<a href="' . URL_BASE . '/index.php?modulo=paciente&vista=buscar_paciente">'
       . 'Buscar paciente</a></div>';
    return;
}

$dentistas = Conexion::todos(
    'SELECT id_usuario, nombre_completo FROM usuario
      WHERE es_dentista = 1 AND estatus = 1 ORDER BY nombre_completo');


// El folio se necesita en el encabezado y en el aviso de nueva consulta.
// Se lee aqui y no por AJAX porque el titulo no puede aparecer vacio y
// llenarse un segundo despues.
$datosPlan = $idPlan
    ? Conexion::uno(
        'SELECT folio, id_estado_plan, motivo_cancelacion,
                fecha_cancelacion, fecha_terminado
           FROM plan_tratamiento
          WHERE id_plan_tratamiento = :p AND id_registro_paciente = :r',
        [':p' => $idPlan, ':r' => $idExpediente])
    : null;

$folioPlan   = (string) ($datosPlan['folio'] ?? '');
$estadoPlan  = $datosPlan !== null ? (int) $datosPlan['id_estado_plan'] : 0;

/*
   Que se puede hacer con este plan.

   Lo decide Planes, no esta vista. La misma pregunta se la hacen la
   pantalla —para ensenar o esconder el boton— y el endpoint que lo
   ejecuta: calculada por separado, acabarian discrepando y el sintoma
   seria un boton que sale y al picarlo dice que no se puede.
*/
$planCerrado  = $estadoPlan > 0 && Planes::esFinal($estadoPlan);
$puedeCancelar = $estadoPlan > 0 && Planes::puedeCancelar($estadoPlan);
$puedeTerminar = $estadoPlan > 0 && Planes::puedeTerminar($estadoPlan);
$conceptosSinHacer = $idPlan ? Planes::conceptosPendientes($idPlan) : 0;

// Dientes agrupados para el selector: temporales y permanentes.
$dientes = Conexion::todos(
    'SELECT id_diente, nombre_diente, es_temporal FROM diente ORDER BY id_diente');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <nav style="font-size:12px" class="mb-1">
            <a href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= $idExpediente ?>"
               class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> <?= e($paciente['nombre_completo']) ?>
            </a>
        </nav>
        <h1 class="titulo-vista">
            <?= $idPlan
                ? 'Plan de tratamiento ' . e($folioPlan)
                : 'Nuevo plan de tratamiento' ?>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <?php /* Solo tiene sentido en un plan ya guardado: una consulta no
                 puede colgar de algo que todavia no existe. */ ?>
        <?php if ($idPlan && Auth::puede('consultas.crear')): ?>
        <button type="button" class="btn btn-outline-morado" id="planNuevaConsulta"
                data-plan="<?= $idPlan ?>"
                data-expediente="<?= $idExpediente ?>"
                data-folio="<?= e($folioPlan) ?>">
            <i class="bi bi-clipboard2-plus me-1"></i> Nueva consulta
        </button>
        <?php endif; ?>
        <?php /* El consentimiento, por separado.

                 Al CREAR el plan se imprimen los dos de golpe —ver
                 planImprimir— porque se firman juntos. Pero al volver a
                 un plan ya hecho, lo que suele faltar es solo uno de
                 los dos: se traspapelo el consentimiento, o hubo que
                 rehacer la hoja. Tener los dos botones evita imprimir
                 de mas. */ ?>
        <?php if ($idPlan && !$planCerrado): ?>
        <button type="button" class="btn btn-outline-morado" id="planConsentimiento"
                data-plan="<?= $idPlan ?>" data-expediente="<?= $idExpediente ?>">
            <i class="bi bi-file-earmark-check me-1"></i> Imprimir consentimiento
        </button>
        <?php endif; ?>

        <button type="button" class="btn btn-outline-morado <?= $idPlan ? '' : 'd-none' ?>"
                id="planImprimir">
            <i class="bi bi-printer me-1"></i> Imprimir para firma
        </button>

        <?php /* Cerrar el plan: terminarlo o cancelarlo.

                 Van detras de los tres puntos porque son las dos cosas
                 que no se deshacen, y no se ponen al lado de lo que se
                 usa a diario. */ ?>
        <?php if (($puedeCancelar || $puedeTerminar) && Auth::puede('consultas.plan')): ?>
        <div class="dropdown">
            <button type="button" class="btn btn-light" data-bs-toggle="dropdown"
                    title="Cerrar este plan">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($puedeTerminar): ?>
                <li>
                    <button type="button" class="dropdown-item" id="planTerminar"
                            data-plan="<?= $idPlan ?>"
                            data-pendientes="<?= $conceptosSinHacer ?>">
                        <i class="bi bi-check2-all me-2"></i> Dar por terminado
                    </button>
                </li>
                <?php endif; ?>
                <?php if ($puedeCancelar): ?>
                <li>
                    <button type="button" class="dropdown-item text-danger"
                            id="planCancelar" data-plan="<?= $idPlan ?>">
                        <i class="bi bi-x-circle me-2"></i> Cancelar el plan
                    </button>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php /* Un plan cerrado lo dice, y dice por que.

         Sin este aviso, quien abre un plan cancelado ve una pantalla
         normal con los botones apagados y no entiende que pasa. El
         motivo es lo que contesta "¿por que este paciente no siguio?"
         dentro de seis meses. */ ?>
<?php if ($planCerrado): ?>
<div class="aviso-plan-cerrado <?= $estadoPlan === Planes::CANCELADO
        ? 'cancelado' : 'terminado' ?> mb-3">
    <i class="bi bi-<?= $estadoPlan === Planes::CANCELADO
        ? 'x-circle-fill' : 'check-circle-fill' ?>"></i>
    <div>
        <b>Este plan est&aacute;
            <?= $estadoPlan === Planes::CANCELADO ? 'cancelado' : 'terminado' ?>
            <?php
            $cuando = $estadoPlan === Planes::CANCELADO
                ? $datosPlan['fecha_cancelacion'] : $datosPlan['fecha_terminado'];
            ?>
            <?= $cuando ? ' desde el ' . e(fecha($cuando)) : '' ?>.
        </b>
        <?php if ($estadoPlan === Planes::CANCELADO && !empty($datosPlan['motivo_cancelacion'])): ?>
        <div class="mt-1"><?= e($datosPlan['motivo_cancelacion']) ?></div>
        <?php endif; ?>
        <div class="mt-1" style="font-size:11.5px">
            Ya no cambia de estatus. Si hace falta retomar el tratamiento,
            se captura un plan nuevo: as&iacute; el expediente conserva lo
            que pas&oacute;.
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($alertasClinicas): ?>
<div class="aviso-almacen mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        <b>Alertas cl&iacute;nicas del paciente</b>
        <?php /* Cada una en su renglon, con su detalle. Antes salian
                 todas pegadas en una linea separada por comas, y
                 "ALERGIA, DIABETES" no dice a que es alergico, que es
                 justo el dato que hace falta antes de anestesiar. */ ?>
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
        <div class="mt-1" style="font-size:11.5px">
            Verifica antes de planear procedimientos con anestesia.
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="<?= $idPlan ? 'col-8' : 'col-12' ?>">

<form id="formPlan" autocomplete="off">
    <input type="hidden" name="id_plan_tratamiento" id="planId" value="<?= $idPlan ?: '' ?>">
    <input type="hidden" name="id_registro_paciente" value="<?= $idExpediente ?>">
    <input type="hidden" name="renglones" id="planRenglones">

  <div id="pasosPlan">

    <!-- ============ 1. Generales ============ -->
    <section class="paso" data-titulo="Generales" data-icono="bi-info-circle">
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="planDescripcion">
                        Descripci&oacute;n del plan de tratamiento
                    </label>
                    <input type="text" class="form-control campo-titular"
                           name="descripcion" id="planDescripcion" maxlength="150"
                           value="PLAN DE TRATAMIENTO <?= strtoupper(fecha(date('Y-m-d'))) ?>">
                    <div class="form-text">
                        Con esto se reconoce el plan en las listas y en el
                        expediente. Puedes cambiarlo por algo m&aacute;s claro,
                        como ORTOPEDIA ETAPA 1.
                    </div>
                </div>

                <div class="col-4" data-edad-paciente="<?= $paciente['edad'] !== null
                         ? (int) $paciente['edad'] : '' ?>">
                    <label class="form-label">Paciente</label>
                    <input type="text" class="form-control" readonly
                           value="<?= e($paciente['nombre_completo']) ?>">
                    <div class="form-text">
                        <?= e($paciente['num_expediente']) ?>
                        <?= $paciente['edad'] !== null ? ' · ' . (int) $paciente['edad'] . ' años' : '' ?>
                    </div>
                </div>
                <div class="col-3">
                    <label class="form-label">Dentista <span class="req">*</span></label>
                    <select class="form-select" name="id_usuario_dentista" required>
                        <?php foreach ($dentistas as $d): ?>
                            <option value="<?= (int) $d['id_usuario'] ?>"
                                <?= (int) $d['id_usuario'] === (int) $usuarioSesion['id_usuario']
                                    ? 'selected' : '' ?>>
                                <?= e($d['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-2">
                    <label class="form-label">Fecha</label>
                    <input type="date" class="form-control" name="fecha"
                           value="<?= date('Y-m-d') ?>">
                </div>
                <?php /* El estatus ya no se elige a mano.

                         Un plan que se acaba de capturar es, por
                         definicion, un plan propuesto: todavia no se lo
                         han enseniado al tutor. Dejarlo como campo
                         invitaba a marcarlo "Aceptado" antes de que
                         nadie firmara nada, y entonces el estatus
                         dejaba de significar algo.

                         Lo mueven los hechos: escanear la hoja firmada
                         lo pasa a Aceptado, y registrar una consulta
                         suya lo pasa a En proceso.

                         Aqui decia "Pasa a Aceptado al escanear la hoja
                         firmada", y era una promesa que el sistema no
                         siempre cumple. Si se agenda y se registra la
                         primera consulta del plan antes de escanear la
                         hoja —que pasa, el papel se queda en el
                         mostrador—, el plan salta directo a En proceso
                         y ya no vuelve atras: los estados solo avanzan.
                         Asi que ese plan nunca estuvo "Aceptado", y
                         quien leyera el aviso se quedaria esperando un
                         cambio que no va a llegar.

                         Lo que se dice ahora es lo unico seguro: que
                         ahora es una propuesta y que lo mueve lo que
                         ocurra, sin prometer a cual de los dos. */ ?>
                <div class="col-3">
                    <label class="form-label">Estatus</label>
                    <div class="form-control-plaintext d-flex align-items-center gap-2"
                         id="plEstatus">
                        <span class="chip-estado badge-suave">Propuesto</span>
                    </div>
                    <div class="form-text">
                        Avanza solo: con la hoja firmada escaneada o con
                        su primera consulta.
                    </div>
                </div>
            </div>
        </div>
    </div>

    </section>

    <!-- ============ 2. Diagnostico ============ -->
    <section class="paso" data-titulo="Diagn&oacute;stico" data-icono="bi-clipboard2-pulse">
    <div class="card">
        <div class="card-header"><i class="bi bi-clipboard2-pulse me-2"></i>Diagn&oacute;stico</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">
                        Diagn&oacute;stico <span class="req">*</span>
                    </label>
                    <textarea class="form-control" name="diagnostico" rows="3"
                              placeholder="EL DIAGN&Oacute;STICO QUE SUSTENTA ESTE PLAN"></textarea>
                    <div class="form-text">
                        Es lo &uacute;nico obligatorio de este paso. Sin
                        diagn&oacute;stico, el plan no se puede justificar.
                    </div>
                </div>

                <?php /* Oclusion y traumatismos son opcionales y casi
                         siempre van vacios. Ocupaban dos campos de
                         texto siempre visibles que la mayoria de las
                         veces se quedaban en blanco, y aun asi habia
                         que pasar por ellos con el tabulador.

                         Ahora son dos renglones chicos que dicen "No
                         aplica" hasta que alguien decide lo contrario.
                         El caso normal no pide ni un clic. */ ?>
                <div class="col-6">
                    <label class="form-label">Oclusi&oacute;n</label>
                    <div class="caja-opcional" id="plOclusionCaja">
                        <span class="valores" id="plOclusionTexto">No aplica</span>
                        <a href="#" class="enlace-agregar" id="plOclusionAbrir">
                            <i class="bi bi-pencil"></i> Elegir
                        </a>
                    </div>
                    <input type="hidden" name="oclusion" id="plOclusion">
                </div>

                <div class="col-6">
                    <label class="form-label">Traumatismos dentales</label>
                    <div class="caja-opcional" id="plTraumaCaja">
                        <span class="valores" id="plTraumaTexto">No aplica</span>
                        <a href="#" class="enlace-agregar" id="plTraumaAbrir">
                            <i class="bi bi-plus-lg"></i> Agregar
                        </a>
                    </div>
                    <div id="plTraumaLista" class="lista-traumatismos"></div>
                    <input type="hidden" name="traumatismos_json" id="plTraumaJson" value="[]">
                </div>
            </div>
        </div>
    </div>

    </section>

    <!-- ============ 3. Procedimientos ============ -->
    <section class="paso" data-titulo="Procedimientos" data-icono="bi-list-check">
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-list-check me-2"></i>Procedimientos</span>
            <button type="button" class="btn btn-sm btn-rosa" id="planAgregar">
                <i class="bi bi-plus-lg me-1"></i> Agregar procedimiento
            </button>
        </div>
        <div class="tabla-con-menu">
            <table class="table tabla-sistema">
                <thead><tr>
                    <th style="width:45px">#</th>
                    <th>Procedimiento</th>
                    <th style="width:170px">&Oacute;rgano dentario</th>
                    <?php /* 70px no alcanzaban: el campo numerico de
                             Bootstrap trae sus flechitas de subir y
                             bajar, y entre eso y el relleno interno el
                             numero quedaba cortado. */ ?>
                    <th style="width:95px">Cant.</th>
                    <th style="width:120px">Costo unit.</th>
                    <th style="width:110px">Descuento</th>
                    <th style="width:120px">Importe</th>
                    <th class="col-acciones"></th>
                </tr></thead>
                <tbody id="planTabla">
                    <tr class="fila-vacia"><td colspan="8">
                        <i class="bi bi-list-check fs-3 d-block mb-2 opacity-50"></i>
                        Agrega los procedimientos del plan.
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php /* Observaciones y Resumen se quitaron del recorrido.

             Observaciones era una caja de texto que repetia lo que ya
             se dice en el diagnostico o en las indicaciones de la
             consulta, y que en la practica quedaba vacia.

             Resumen mostraba de nuevo, en solo lectura, la misma tabla
             que acababa de capturarse dos pasos antes. Un paso entero
             para volver a ver lo mismo. Lo unico suyo que hacia falta
             —el descuento y el total— vive ahora aqui, al pie de los
             procedimientos, que es donde se mira mientras se arma el
             plan y no tres clics despues.

             La columna observaciones sigue en la base con lo capturado
             antes del cambio. */ ?>

    <div class="row g-3 mt-1">
        <div class="col-7">
            <div class="alert alert-light border py-2 small mb-0 d-flex gap-2 h-100
                        align-items-center">
                <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
                <span>Al guardar se genera el pendiente de escanear
                      el plan firmado por el tutor.</span>
            </div>
        </div>

        <div class="col-5">
            <div class="card">
                <div class="card-body py-2">
                    <div class="dato-identidad">
                        <i class="bi bi-calculator"></i>
                        <div class="flex-grow-1 d-flex justify-content-between">
                            <span class="etiqueta">Subtotal</span>
                            <span class="fw-semibold" id="planSubtotal">$0.00</span>
                        </div>
                    </div>
                    <div class="dato-identidad">
                        <i class="bi bi-tag"></i>
                        <div class="flex-grow-1 d-flex justify-content-between
                                    align-items-center gap-2">
                            <span class="etiqueta">Descuento</span>
                            <input type="number" class="form-control form-control-sm
                                   text-end" style="max-width:120px"
                                   name="descuento" id="planDescuento"
                                   value="0.00" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="dato-identidad" style="border-top:2px solid var(--gris-100)">
                        <i class="bi bi-receipt"></i>
                        <div class="flex-grow-1 d-flex justify-content-between">
                            <span class="etiqueta fw-semibold">TOTAL</span>
                            <span class="fs-5 fw-semibold" style="color:var(--morado-800)"
                                  id="planTotal">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </section>

  </div>
</form>

  </div>

<?php if ($idPlan): ?>
  <?php /* El entorno del plan.

           Un plan de tratamiento no es un documento que se firma y se
           archiva: es algo que se ejecuta durante meses, cita por cita.
           Hasta ahora, para ver que habia pasado con el, habia que
           salirse al expediente, buscar las consultas, entrar a cada
           una y volver.

           Las tres tarjetas contestan lo que uno se pregunta estando
           aqui: que citas ha habido, como se ve la boca ahora, y que
           se ha ido anotando. */ ?>
  <div class="col-4">

    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clipboard2-pulse me-2"></i>Consultas del plan</span>
        <span class="cuenta" id="ppConsultasCuenta">0</span>
      </div>
      <div class="card-body py-2" id="ppConsultas">
        <div class="exp-vacio">Sin consultas todav&iacute;a.</div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-images me-2"></i>Fotos y radiograf&iacute;as</span>
        <span class="cuenta" id="ppAlbumCuenta">0</span>
      </div>
      <div class="card-body py-2" id="ppAlbum">
        <div class="exp-vacio">Sin im&aacute;genes.</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-journal-text me-2"></i>Notas de evoluci&oacute;n</span>
        <span class="cuenta" id="ppNotasCuenta">0</span>
      </div>
      <div class="card-body py-2" id="ppNotas">
        <div class="exp-vacio">Sin notas.</div>
      </div>
    </div>

  </div>
<?php endif; ?>
</div>

<?php require RUTA_VISTAS . '/consultas/_modal_procedimiento.php'; ?>

<?php /* El selector de dientes pinta el estado actual de ESTE
         paciente: se elige donde va el procedimiento mirando como esta
         la boca, no una rejilla de numeros iguales. */ ?>
<?php $idExpedienteOdo = $idExpediente; ?>
<?php require RUTA_VISTAS . '/consultas/_modal_dientes.php'; ?>

<?php require RUTA_VISTAS . '/consultas/_modales_diagnostico.php'; ?>

<?php if ($idPlan): ?>
<?php require RUTA_VISTAS . '/consultas/_modales_panel_plan.php'; ?>
<?php endif; ?>
