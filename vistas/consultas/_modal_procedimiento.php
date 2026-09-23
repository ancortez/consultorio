<?php
/**
 * Selector de procedimientos del catalogo.
 *
 * Lo usan el plan de tratamiento y el cargo de la consulta. Vive
 * aparte porque son dos pantallas y una sola lista: tenerlo duplicado
 * garantizaba que tarde o temprano se vieran distintas.
 *
 * Quien lo abre decide que hacer con lo elegido, escuchando el evento
 * 'procedimiento:elegido' del propio modal.
 */
?>
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

