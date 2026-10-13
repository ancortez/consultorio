<?php
/**
 * Subir el escaneo de un documento pendiente.
 *
 * Vivia dentro del expediente electronico. Octubre 2026: el boton de
 * "cambiar fotografia" y los pendientes de la tarjeta del paciente
 * tambien lo necesitan, y esas dos cosas estan en otra pantalla. Un
 * modal que solo existe en una pagina es un boton que falla en la
 * otra sin decir por que.
 */
?>
<div class="modal fade" id="modalSubirDoc" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-cloud-arrow-up me-2"></i>
          <span id="subirTitulo">Subir documento</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formSubirDoc" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="id_documento" id="subirIdDocumento">

          <div class="alert alert-light border py-2 small d-flex gap-2 mb-3">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span id="subirAyuda"></span>
          </div>

          <div class="zona-soltar" id="zonaSoltar">
            <i class="bi bi-file-earmark-arrow-up"></i>
            <div class="fw-semibold">Arrastra el archivo aqu&iacute;</div>
            <div style="font-size:12px">o da clic para buscarlo en tu computadora</div>
            <input type="file" name="archivo" id="subirArchivo" class="d-none">
          </div>

          <div class="mt-3 d-none" id="subirPrevio">
            <p class="form-label">Archivo seleccionado</p>
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-file-earmark-check" style="color:var(--exito)"></i>
              <span class="fw-semibold" id="subirNombre"></span>
              <span class="text-secondary" id="subirTamano"></span>
              <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-auto"
                      id="subirQuitar">Quitar</button>
            </div>
            <img class="vista-previa d-none" id="subirImagen" alt="Vista previa">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado" id="subirBoton" disabled>
            <i class="bi bi-cloud-arrow-up me-1"></i> Guardar en el expediente
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
