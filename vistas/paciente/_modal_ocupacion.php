<?php
/**
 * Selector de ocupacion.
 *
 * Con casi 80 ocupaciones, un combo normal obliga a desplazarse a ciegas.
 * Este modal trae filtro de busqueda y permite dar de alta una ocupacion
 * nueva sin salirse de la pantalla ni perder lo capturado.
 *
 * Es generico: cualquier campo con data-selector="ocupacion" lo abre y
 * recibe el resultado. Asi sirve igual para el paciente y para el tutor.
 */
?>
<div class="modal fade" id="modalOcupacion" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title texto-morado">
          <i class="bi bi-briefcase me-2"></i>Selecciona la ocupaci&oacute;n
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="input-group mb-3">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="filtroOcupacion"
                 placeholder="Escribe para filtrar" autocomplete="off">
          <button class="btn btn-outline-secondary" type="button" id="limpiarOcupacion"
                  title="Limpiar">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <div class="border rounded lista-seleccion" id="listaOcupaciones">
          <div class="cargando">
            <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
            Cargando...
          </div>
        </div>

        <!-- Alta rapida: aparece cuando el filtro no encuentra nada -->
        <div class="mt-3 d-none" id="altaOcupacion">
          <div class="alert alert-light border py-2 small mb-2 d-flex gap-2">
            <i class="bi bi-lightbulb" style="color:var(--morado-600)"></i>
            <span>No existe esa ocupaci&oacute;n. Puedes agregarla al cat&aacute;logo.</span>
          </div>
          <div class="input-group">
            <input type="text" class="form-control" id="nuevaOcupacion"
                   maxlength="80" placeholder="Nombre de la ocupaci&oacute;n">
            <button class="btn btn-rosa" type="button" id="guardarOcupacion">
              <i class="bi bi-plus-lg me-1"></i> Agregar
            </button>
          </div>
        </div>
      </div>

      <div class="modal-footer justify-content-between">
        <span class="text-secondary small" id="contadorOcupaciones"></span>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>
