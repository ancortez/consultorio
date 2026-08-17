<?php
/**
 * Antes de imprimir el consentimiento hay que definir a nombre de quien
 * sale. Si solo hay un tutor con un domicilio y un dentista, el modal ni
 * siquiera se muestra: se imprime directo.
 */
?>
<div class="modal fade" id="modalConsentimiento" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-file-earmark-text me-2"></i>Imprimir consentimiento
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formConsentimiento">
        <div class="modal-body">
          <div class="alert alert-light border py-2 small d-flex gap-2 mb-3">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span>El documento sale prellenado. Al abrirlo se agrega el
                  pendiente de escanearlo firmado.</span>
          </div>

          <div class="mb-3">
            <label class="form-label">Firma como representante legal <span class="req">*</span></label>
            <select class="form-select" name="tutor" id="consTutor" required></select>
            <div class="form-text">
              Por omisi&oacute;n, el tutor marcado como responsable de pago.
            </div>
          </div>

          <div class="mb-3" id="consBloqueDomicilio">
            <label class="form-label">Domicilio que aparece</label>
            <select class="form-select" name="domicilio" id="consDomicilio"></select>
          </div>

          <div>
            <label class="form-label">Doctor/a que atiende <span class="req">*</span></label>
            <select class="form-select" name="dentista" id="consDentista" required></select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">
            <i class="bi bi-printer me-1"></i> Abrir para imprimir
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
