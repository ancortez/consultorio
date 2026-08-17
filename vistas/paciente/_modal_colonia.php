<?php
/**
 * Selector de colonia con busqueda en vivo.
 *
 * Solo Tepic tiene 379 colonias. Un combo con esa cantidad obliga a
 * desplazarse a ciegas, y como muchas comparten nombre (AMADO NERVO
 * existe como colonia y como fraccionamiento), el usuario no distingue
 * cual eligio. Aqui se busca escribiendo, se ve el tipo de asentamiento
 * y se ve el codigo postal antes de elegir.
 *
 * Funciona igual que el de ocupacion: cualquier campo con
 * data-selector="colonia" lo abre, y el resultado se escribe en el
 * campo oculto que indique data-destino.
 */
?>
<div class="modal fade" id="modalColoniaSel" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-geo-alt me-2"></i>Selecciona la colonia
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-light border py-2 small d-flex gap-2 mb-3"
             id="coloniaSelUbicacion">
          <i class="bi bi-pin-map" style="color:var(--morado-600)"></i>
          <span></span>
        </div>

        <div class="input-group mb-3">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="filtroColoniaSel"
                 placeholder="Nombre de la colonia o c&oacute;digo postal"
                 autocomplete="off">
          <button class="btn btn-outline-secondary" type="button"
                  id="limpiarColoniaSel" title="Limpiar">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <div class="border rounded lista-seleccion" id="listaColoniaSel">
          <div class="cargando">
            <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
            Cargando...
          </div>
        </div>

        <div class="mt-3 d-none" id="avisoSinColonia">
          <div class="alert alert-light border py-2 small mb-0 d-flex gap-2">
            <i class="bi bi-lightbulb" style="color:var(--morado-600)"></i>
            <span>No aparece esa colonia. Se puede agregar al cat&aacute;logo desde
                  <b>Configuraci&oacute;n &rsaquo; Colonias</b>.</span>
          </div>
        </div>
      </div>

      <div class="modal-footer justify-content-between">
        <span class="text-secondary small" id="contadorColoniaSel"></span>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>
