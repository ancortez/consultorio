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

<?php /* El expediente se arma como una carpeta: arriba lo que es del
         paciente y despues una pestania por cada plan de tratamiento,
         del mas reciente al mas antiguo. Todo colapsable, para no tener
         cien tarjetas a la vista de golpe. */ ?>
<div class="expediente" id="expDocumentos">
    <div class="cargando">
        <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
        Cargando expediente...
    </div>
</div>

<!-- ============ Modal para subir el archivo ============ -->
<?php /* El modal de subida se movio a vistas/layout: lo necesitan
         tambien la ficha del paciente (foto y pendientes) y no puede
         vivir solo en esta pantalla. */ ?>


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
        <input type="hidden" name="id_plan_tratamiento" id="pendPlan" value="">

        <div class="modal-body">
          <?php /* El combo se arma por ambito para que al agregar un
                   documento dentro de un plan no aparezcan la foto ni el
                   historial, que son del expediente completo. */ ?>
          <div class="alert alert-light border py-2 small d-flex gap-2 mb-3 d-none"
               id="pendContexto">
            <i class="bi bi-list-check" style="color:var(--morado-600)"></i>
            <span>El documento quedar&aacute; dentro del plan
                  <b id="pendContextoFolio"></b>.</span>
          </div>

          <div class="mb-3">
            <label class="form-label">Tipo de documento <span class="req">*</span></label>
            <select class="form-select" name="clave" id="pendTipo" required>
              <option value="">Seleccionar...</option>
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
