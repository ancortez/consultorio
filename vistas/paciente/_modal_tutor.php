<?php
/**
 * Alta de tutor. Misma logica que el paciente: primero se busca para no
 * duplicar (un tutor puede ya existir porque es padre de otro paciente),
 * y despues se captura.
 *
 * MEJORA: en el modelo original un tutor solo podia pertenecer a un
 * expediente. Aqui, si el tutor ya existe, simplemente se vincula al
 * expediente actual sin volver a capturarlo. Es el caso de los hermanos.
 */
?>
<div class="modal fade" id="modalTutor" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title texto-morado">
          <i class="bi bi-person-plus me-2"></i>Nuevo tutor
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- Paso 1: buscar -->
        <div id="pasoBuscarTutor">
          <div class="alert alert-light border d-flex gap-2 py-2 small">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span>Si el tutor ya est&aacute; registrado (por ejemplo, es padre de otro
                  paciente), lo vinculamos sin volver a capturarlo.</span>
          </div>

          <form id="formBuscarTutor" class="row g-2" autocomplete="off">
            <div class="col-4">
              <label class="form-label">Nombres <span class="req">*</span></label>
              <input type="text" class="form-control" name="nombres" maxlength="80" required>
            </div>
            <div class="col-4">
              <label class="form-label">Apellido paterno <span class="req">*</span></label>
              <input type="text" class="form-control" name="ap_paterno" maxlength="60" required>
            </div>
            <div class="col-4">
              <label class="form-label">Apellido materno</label>
              <input type="text" class="form-control" name="ap_materno" maxlength="60">
            </div>
            <div class="col-12 text-end">
              <button type="submit" class="btn btn-morado btn-sm px-3">
                <i class="bi bi-search me-1"></i> Buscar
              </button>
            </div>
          </form>

          <div id="resultadoTutor" class="mt-3 d-none">
            <hr>
            <p class="form-label mb-2">Coincidencias encontradas</p>
            <div class="table-responsive border rounded" style="max-height:220px;overflow:auto">
              <table class="table tabla-sistema mb-0">
                <thead><tr><th>Nombre</th><th style="width:130px">RFC</th>
                           <th style="width:110px"></th></tr></thead>
                <tbody id="listaTutores"></tbody>
              </table>
            </div>
            <div class="text-end mt-2">
              <button class="btn btn-rosa btn-sm" id="btnCapturarTutor">
                <i class="bi bi-plus-lg me-1"></i> Capturar tutor nuevo
              </button>
            </div>
          </div>
        </div>

        <!-- Paso 2: capturar -->
        <form id="formGuardarTutor" class="d-none" autocomplete="off">
          <div class="row g-3">
            <div class="col-4">
              <label class="form-label">Nombres <span class="req">*</span></label>
              <input type="text" class="form-control" name="nombres" maxlength="80" required>
            </div>
            <div class="col-4">
              <label class="form-label">Apellido paterno <span class="req">*</span></label>
              <input type="text" class="form-control" name="ap_paterno" maxlength="60" required>
            </div>
            <div class="col-4">
              <label class="form-label">Apellido materno</label>
              <input type="text" class="form-control" name="ap_materno" maxlength="60">
            </div>

            <div class="col-4">
              <label class="form-label">RFC</label>
              <input type="text" class="form-control text-uppercase" name="rfc" maxlength="13">
            </div>
            <div class="col-4">
              <label class="form-label">Parentesco <span class="req">*</span></label>
              <select class="form-select" name="id_parentesco" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($parentescos as $pa): ?>
                  <option value="<?= (int) $pa['id_parentesco'] ?>">
                    <?= e($pa['nombre_parentesco']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label">Tel&eacute;fono celular</label>
              <input type="tel" class="form-control" name="telefono" maxlength="15">
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1"
                       name="es_responsable_pago" id="tutorResponsable" checked>
                <label class="form-check-label" for="tutorResponsable">
                  Es el responsable de pago de este paciente
                </label>
              </div>
            </div>

            <div class="col-12">
              <div class="alert alert-light border small mb-0 d-flex gap-2">
                <i class="bi bi-lightbulb" style="color:var(--morado-600)"></i>
                <span>Al guardar se abrir&aacute; la captura del domicilio del tutor.</span>
              </div>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-morado d-none" id="btnGuardarTutor">
          <i class="bi bi-check-lg me-1"></i> Guardar tutor
        </button>
      </div>
    </div>
  </div>
</div>
