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
        'SELECT vp.*,
                (SELECT GROUP_CONCAT(a.nombre_antecedente SEPARATOR ", ")
                   FROM expediente_antecedente ea
                   JOIN antecedente a ON a.id_antecedente = ea.id_antecedente
                  WHERE ea.id_registro_paciente = vp.id_registro_paciente
                    AND ea.estatus = 1 AND a.requiere_alerta = 1) AS alertas
           FROM v_paciente vp
          WHERE vp.id_registro_paciente = :r',
        [':r' => $idExpediente])
    : null;

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

$estadosPlan = Conexion::todos('SELECT * FROM estado_plan ORDER BY id_estado_plan');

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
            <?= $idPlan ? 'Modificar plan de tratamiento' : 'Nuevo plan de tratamiento' ?>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-morado <?= $idPlan ? '' : 'd-none' ?>"
                id="planImprimir">
            <i class="bi bi-printer me-1"></i> Imprimir para firma
        </button>
    </div>
</div>

<?php if (!empty($paciente['alertas'])): ?>
<div class="aviso-almacen mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        <b>Alertas cl&iacute;nicas del paciente</b>
        <div class="mt-1"><?= e($paciente['alertas']) ?></div>
        <div class="mt-1" style="font-size:11.5px">
            Verifica antes de planear procedimientos con anestesia.
        </div>
    </div>
</div>
<?php endif; ?>

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
                <div class="col-4">
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
                <div class="col-3">
                    <label class="form-label">Estatus del plan</label>
                    <select class="form-select" name="id_estado_plan">
                        <?php foreach ($estadosPlan as $ep): ?>
                            <option value="<?= (int) $ep['id_estado_plan'] ?>">
                                <?= e($ep['nombre_estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                    <label class="form-label">Diagn&oacute;stico</label>
                    <textarea class="form-control" name="diagnostico" rows="2"></textarea>
                </div>
                <div class="col-4">
                    <label class="form-label">Oclusi&oacute;n</label>
                    <input type="text" class="form-control" name="oclusion" maxlength="150">
                </div>
                <div class="col-4">
                    <label class="form-label">Traumatismos dentales</label>
                    <input type="text" class="form-control" name="traumatismos" maxlength="255">
                </div>
                <div class="col-4">
                    <label class="form-label">Secuelas de traumatismos</label>
                    <input type="text" class="form-control" name="secuelas_traumatismos"
                           maxlength="255">
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
                    <th style="width:130px">Diente</th>
                    <th style="width:70px">Cant.</th>
                    <th style="width:120px">Costo unit.</th>
                    <th style="width:110px">Descuento</th>
                    <th style="width:120px">Importe</th>
                    <th style="width:130px">Fecha</th>
                    <th style="width:130px">Pr&oacute;x. cita</th>
                    <th class="col-acciones"></th>
                </tr></thead>
                <tbody id="planTabla">
                    <tr class="fila-vacia"><td colspan="10">
                        <i class="bi bi-list-check fs-3 d-block mb-2 opacity-50"></i>
                        Agrega los procedimientos del plan.
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    </section>

    <!-- ============ 4. Observaciones ============ -->
    <section class="paso" data-titulo="Observaciones" data-icono="bi-chat-left-text">
    <div class="row g-3">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-chat-left-text me-2"></i>Observaciones</div>
                <div class="card-body">
                    <textarea class="form-control" name="observaciones" rows="4"
                              placeholder="INFORMACION ADICIONAL SOBRE EL TRATAMIENTO"></textarea>
                    <div class="form-text">
                        Lo que no es el procedimiento en s&iacute;: indicaciones,
                        acuerdos con el tutor, condiciones especiales.
                    </div>
                </div>
            </div>
        </div>

    </div>
    </section>

    <!-- ============ 5. Resumen ============ -->
    <section class="paso" data-titulo="Resumen" data-icono="bi-receipt">
    <div class="row g-3">
        <div class="col-7">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-list-check me-2"></i>Procedimientos del plan
                </div>
                <div class="tabla-con-menu">
                    <table class="table tabla-sistema">
                        <thead><tr>
                            <th style="width:40px">#</th>
                            <th>Procedimiento</th>
                            <th style="width:80px">Diente</th>
                            <th style="width:60px">Cant.</th>
                            <th style="width:110px" class="text-end">Importe</th>
                        </tr></thead>
                        <tbody id="planResumen"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-5">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-cash-coin me-2"></i>Totales</div>
                <div class="card-body">
                    <div class="dato-identidad">
                        <i class="bi bi-list-ol"></i>
                        <div class="flex-grow-1 d-flex justify-content-between">
                            <span class="etiqueta">Subtotal</span>
                            <span class="valor" id="planSubtotal">$0.00</span>
                        </div>
                    </div>
                    <div class="dato-identidad">
                        <i class="bi bi-tag"></i>
                        <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                            <span class="etiqueta">Descuento general</span>
                            <div class="input-group input-group-sm" style="width:130px">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control text-end"
                                       name="descuento" id="planDescuento"
                                       value="0.00" step="0.01" min="0">
                            </div>
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

                    <div class="alert alert-light border py-2 small mt-3 mb-0 d-flex gap-2">
                        <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
                        <span>Al guardar se genera el pendiente de escanear
                              el plan firmado por el tutor.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </section>

  </div>
</form>

<!-- ============ Selector de procedimiento ============ -->
<div class="modal fade" id="modalProcSel" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clipboard2-plus me-2"></i>Agregar procedimiento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="input-group mb-3">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="procSelFiltro"
                 placeholder="Nombre o categor&iacute;a" autocomplete="off">
        </div>
        <div class="border rounded lista-seleccion" id="procSelLista">
          <div class="cargando">
            <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
            Cargando...
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Catalogo de dientes para los selectores de cada renglon -->
<template id="tplDientes">
  <option value="">Sin diente</option>
  <optgroup label="Permanentes">
    <?php foreach ($dientes as $d): if ((int) $d['es_temporal'] === 1) continue; ?>
      <option value="<?= (int) $d['id_diente'] ?>"><?= (int) $d['id_diente'] ?></option>
    <?php endforeach; ?>
  </optgroup>
  <optgroup label="Temporales">
    <?php foreach ($dientes as $d): if ((int) $d['es_temporal'] === 0) continue; ?>
      <option value="<?= (int) $d['id_diente'] ?>"><?= (int) $d['id_diente'] ?></option>
    <?php endforeach; ?>
  </optgroup>
</template>
