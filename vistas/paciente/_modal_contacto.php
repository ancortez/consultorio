<?php
/**
 * Alta y modificacion de un canal de contacto.
 *
 * Sirve igual para el paciente y para el tutor: el JavaScript le pasa el
 * id_persona que corresponda. Antes esto era una fila que se desplegaba
 * dentro de la pestania; como modal queda mas claro que es una captura
 * aparte y no algo que se guarda junto con el resto del formulario.
 */
?>
<div class="modal fade" id="modalContacto" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-chat-dots me-2"></i>
          <span id="tituloModalContacto">Nuevo medio de contacto</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formContacto" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="id_contacto_persona" id="contactoId">
          <input type="hidden" name="id_persona" id="contactoPersona">

          <div class="alert alert-light border py-2 small d-flex gap-2 mb-3"
               id="contactoDe">
            <i class="bi bi-person" style="color:var(--morado-600)"></i>
            <span></span>
          </div>

          <div class="mb-3">
            <label class="form-label" for="contactoMedio">
              Medio de contacto <span class="req">*</span>
            </label>
            <select class="form-select" name="id_medio_contacto" id="contactoMedio" required>
              <?php foreach ($medios as $m): ?>
                <option value="<?= (int) $m['id_medio_contacto'] ?>"
                        data-tipo="<?= e($m['tipo_dato']) ?>"
                        data-desc="<?= e($m['descripcion']) ?>">
                  <?= e($m['nombre_medio_contacto']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="contactoValor">Valor <span class="req">*</span></label>
            <input type="text" class="form-control" name="valor_contacto"
                   id="contactoValor" maxlength="150" required>
            <div class="form-text" id="contactoAyuda"></div>
          </div>

          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1"
                   name="es_principal" id="contactoPrincipal">
            <label class="form-check-label" for="contactoPrincipal">
              Marcar como principal
            </label>
            <div class="form-text">
              El que se muestra en la tarjeta del paciente y en las listas.
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">
            <i class="bi bi-check-lg me-1"></i> Guardar contacto
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
