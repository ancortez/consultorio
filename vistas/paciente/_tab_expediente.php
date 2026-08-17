<?php
/**
 * Pestania Expediente Electronico.
 *
 * Muestra los documentos del paciente: los que faltan por escanear y los
 * que ya estan guardados. El contenido se pinta desde JavaScript porque
 * cambia con cada subida y no vale la pena recargar la pagina entera.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="form-label mb-0">Documentos del expediente</p>
        <p class="text-secondary mb-0" style="font-size:12px">
            Los archivos se guardan cifrados en el almacenamiento del consultorio,
            no en esta computadora.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-morado" id="expRefrescar">
            <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
        </button>
        <?php if (Auth::puede('paciente.editar')): ?>
        <button type="button" class="btn btn-sm btn-rosa" id="expNuevoPendiente">
            <i class="bi bi-plus-lg me-1"></i> Agregar documento
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Aviso si MinIO no responde -->
<div class="aviso-almacen mb-3 d-none" id="expAvisoAlmacen">
    <i class="bi bi-hdd-network"></i>
    <div>
        <b>El almacenamiento de archivos no responde.</b>
        <div id="expAvisoDetalle" class="mt-1"></div>
        <div class="mt-2">
            Puedes seguir usando el resto del sistema; solo no se podr&aacute;n
            subir ni ver documentos hasta que se restablezca.
        </div>
    </div>
</div>

<!-- Resumen de pendientes -->
<div class="aviso-vinculado mb-3 d-none" id="expResumen">
    <i class="bi bi-exclamation-triangle"></i>
    <div>
        <b><span id="expResumenTotal">0</span> documento(s) pendiente(s) de digitalizar.</b><br>
        Imprime, firma, escanea y sube cada uno para completar el expediente.
    </div>
</div>

<div class="row g-3" id="expDocumentos">
    <div class="col-12">
        <div class="cargando">
            <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
            Cargando expediente...
        </div>
    </div>
</div>

<!-- ============ Modal para subir el archivo ============ -->
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

<!-- ============ Modal para agregar un pendiente a mano ============ -->
<div class="modal fade" id="modalNuevoPendiente" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-file-earmark-plus me-2"></i>Agregar documento al expediente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formNuevoPendiente">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Tipo de documento <span class="req">*</span></label>
            <select class="form-select" name="clave" required>
              <option value="">Seleccionar...</option>
              <?php foreach (Conexion::todos(
                  'SELECT clave, nombre_documento FROM tipo_documento
                    WHERE estatus = 1 ORDER BY orden') as $td): ?>
                <option value="<?= e($td['clave']) ?>"><?= e($td['nombre_documento']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Descripci&oacute;n</label>
            <input type="text" class="form-control" name="descripcion" maxlength="200"
                   placeholder="Ejemplo: RADIOGRAFIA PANORAMICA DEL 12/08">
            <div class="form-text">
              Opcional. Ayuda a distinguirlo cuando hay varios del mismo tipo.
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Agregar pendiente</button>
        </div>
      </form>
    </div>
  </div>
</div>
