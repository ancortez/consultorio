<?php
/**
 * Modales del modulo Paciente.
 *
 * Flujo de alta descrito en la composicion, respetado tal cual:
 *   1. Modal de busqueda: nombres, ap_paterno, ap_materno + boton Buscar
 *   2. Si hay coincidencias, se listan y se puede seleccionar una
 *   3. Boton "Agregar nuevo paciente" -> modal de captura con los mismos
 *      datos heredados + Guardar
 *   4. Al guardar se crea registro_paciente + persona y se va a
 *      detalle_paciente
 *
 * MEJORA: el paso 1 es en realidad un control anti-duplicados. Se deja
 * como esta pero se hace explicito en pantalla ("verificamos que no
 * exista ya"), porque de otro modo el usuario no entiende por que el
 * sistema le pide buscar antes de dejarlo capturar.
 */
?>

<!-- ============ Paso 1 y 2: buscar antes de dar de alta ============ -->
<div class="modal fade" id="modalBuscarAlta" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title texto-morado">
          <i class="bi bi-person-plus me-2"></i>Nuevo paciente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-light border d-flex gap-2 py-2 small">
          <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
          <span>Antes de dar de alta verificamos que el paciente no exista ya
                en el sistema. Captura el nombre y presiona <b>Buscar</b>.</span>
        </div>

        <form id="formBuscarAlta" class="row g-2" autocomplete="off">
          <div class="col-4">
            <label class="form-label" for="ba_nombres">Nombres <span class="req">*</span></label>
            <input type="text" class="form-control" id="ba_nombres" name="nombres"
                   maxlength="80" required>
          </div>
          <div class="col-4">
            <label class="form-label" for="ba_paterno">Apellido paterno <span class="req">*</span></label>
            <input type="text" class="form-control" id="ba_paterno" name="ap_paterno"
                   maxlength="60" required>
          </div>
          <div class="col-4">
            <label class="form-label" for="ba_materno">Apellido materno</label>
            <input type="text" class="form-control" id="ba_materno" name="ap_materno"
                   maxlength="60">
          </div>
          <div class="col-12 text-end">
            <button type="submit" class="btn btn-morado btn-sm px-3">
              <i class="bi bi-search me-1"></i> Buscar
            </button>
          </div>
        </form>

        <div id="resultadoBuscarAlta" class="mt-3 d-none">
          <hr>
          <p class="form-label mb-2">Coincidencias encontradas</p>
          <div class="table-responsive border rounded" style="max-height:240px;overflow:auto">
            <table class="table tabla-sistema mb-0">
              <thead><tr>
                <th>Nombre</th><th style="width:110px">Expediente</th>
                <th style="width:120px">Tel&eacute;fono</th><th style="width:110px"></th>
              </tr></thead>
              <tbody id="listaCoincidencias"></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="modal-footer justify-content-between">
        <span class="text-secondary small" id="avisoCoincidencias"></span>
        <div>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-rosa d-none" id="btnAgregarNuevo">
            <i class="bi bi-plus-lg me-1"></i> Agregar nuevo paciente
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============ Paso 3 y 4: captura y guardado ============ -->
<div class="modal fade" id="modalGuardarPaciente" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title texto-morado">
          <i class="bi bi-person-vcard me-2"></i>Datos del nuevo paciente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formGuardarPaciente" autocomplete="off">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-4">
              <label class="form-label" for="gp_nombres">Nombres <span class="req">*</span></label>
              <input type="text" class="form-control" id="gp_nombres" name="nombres"
                     maxlength="80" required>
            </div>
            <div class="col-4">
              <label class="form-label" for="gp_paterno">Apellido paterno <span class="req">*</span></label>
              <input type="text" class="form-control" id="gp_paterno" name="ap_paterno"
                     maxlength="60" required>
            </div>
            <div class="col-4">
              <label class="form-label" for="gp_materno">Apellido materno</label>
              <input type="text" class="form-control" id="gp_materno" name="ap_materno"
                     maxlength="60">
            </div>

            <div class="col-4">
              <label class="form-label" for="gp_nacim">Fecha de nacimiento</label>
              <input type="date" class="form-control" id="gp_nacim" name="fecha_nacim"
                     max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-4">
              <label class="form-label" for="gp_genero">G&eacute;nero</label>
              <select class="form-select" id="gp_genero" name="id_genero">
                <option value="">Seleccionar...</option>
                <?php foreach (Conexion::todos('SELECT * FROM genero ORDER BY id_genero') as $g): ?>
                  <option value="<?= (int) $g['id_genero'] ?>"><?= e($g['nombre_genero']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label" for="gp_tel">Tel&eacute;fono celular</label>
              <input type="tel" class="form-control" id="gp_tel" name="telefono"
                     maxlength="15" placeholder="10 d&iacute;gitos">
            </div>

            <div class="col-12">
              <div class="alert alert-light border small mb-0 d-flex gap-2">
                <i class="bi bi-lightbulb" style="color:var(--morado-600)"></i>
                <span>El domicilio, los dem&aacute;s canales de contacto y los
                      tutores se capturan en la siguiente pantalla.</span>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">
            <i class="bi bi-check-lg me-1"></i> Guardar datos
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
