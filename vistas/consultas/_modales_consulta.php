<?php
/**
 * Modales del detalle de consulta: receta, nota de evolucion y subida
 * de documentos por album.
 */
?>

<!-- ============ Receta ============ -->
<div class="modal fade" id="modalReceta" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-prescription2 me-2"></i>
          <span id="rxTitulo">Nueva receta</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formReceta">
        <input type="hidden" name="id_receta" id="rxId">
        <input type="hidden" name="id_consulta" id="rxConsulta">

        <div class="modal-body">
          <?php /* Los datos de arriba no se capturan: salen de la
                   consulta. Se muestran para que quien receta vea sobre
                   quien esta recetando, que es de lo que se trata. */ ?>
          <div class="rx-encabezado mb-3">
            <div>
              <span class="et">Paciente</span>
              <span class="va" id="rxPaciente">&mdash;</span>
            </div>
            <div>
              <span class="et">Edad</span>
              <span class="va" id="rxEdad">&mdash;</span>
            </div>
            <div>
              <span class="et">Fecha</span>
              <input type="date" class="form-control form-control-sm"
                     name="fecha" id="rxFecha">
            </div>
          </div>

          <label class="form-label" for="rxIndicaciones">
            Indicaciones <span class="req">*</span>
          </label>
          <textarea class="form-control rx-cuerpo" name="indicaciones"
                    id="rxIndicaciones" rows="12"
                    placeholder="UN RENGL&Oacute;N POR MEDICAMENTO:&#10;&#10;PARACETAMOL 100 MG/ML GOTAS&#10;0.5 ML CADA 8 HORAS POR 3 DIAS&#10;&#10;AMOXICILINA SUSPENSI&Oacute;N 250 MG/5 ML&#10;5 ML CADA 8 HORAS POR 7 DIAS"></textarea>

          <div class="alert alert-light border py-2 small d-flex gap-2 mt-3 mb-0">
            <i class="bi bi-printer" style="color:var(--morado-600)"></i>
            <span>
              Al imprimir sale <b>solo lo que escribiste</b>, acomodado en
              los espacios del recetario que ya tiene el consultorio.
              El membrete y el pie no se imprimen porque ya est&aacute;n en
              el papel.
            </span>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">
            <i class="bi bi-check-lg me-1"></i> Guardar receta
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Nota de evolucion ============ -->
<div class="modal fade" id="modalNota" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-journal-text me-2"></i>Nota de evoluci&oacute;n
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formNota">
        <input type="hidden" name="id_consulta" id="notaConsulta">
        <div class="modal-body">
          <textarea class="form-control" name="texto" id="notaTexto" rows="6"
                    placeholder="LO QUE SE OBSERV&Oacute;, LO QUE SE ACORD&Oacute; CON EL TUTOR, C&Oacute;MO RESPONDI&Oacute; EL PACIENTE"></textarea>
          <div class="form-text">
            La nota queda firmada con tu nombre y la hora. No se puede
            editar despu&eacute;s: si hay que aclarar algo, se agrega otra nota.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Agregar nota</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Album de documentos ============ -->
<div class="modal fade" id="modalAlbum" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-images me-2"></i>
          <span id="albumTitulo">Agregar documentos</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formAlbum" enctype="multipart/form-data">
        <input type="hidden" name="clave" id="albumClave">
        <input type="hidden" name="id_consulta" id="albumConsulta">

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Descripci&oacute;n</label>
            <input type="text" class="form-control" name="descripcion"
                   id="albumDescripcion" maxlength="200">
            <div class="form-text" id="albumAyuda"></div>
          </div>

          <div class="zona-soltar" id="albumZona">
            <i class="bi bi-file-earmark-arrow-up"></i>
            <div class="fw-semibold">Arrastra los archivos aqu&iacute;</div>
            <div style="font-size:12px">
              o da clic para buscarlos. Puedes elegir varios de una vez.
            </div>
            <input type="file" name="archivos[]" id="albumArchivos" class="d-none" multiple>
          </div>

          <div class="album-previo mt-3" id="albumPrevio"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado" id="albumBoton" disabled>
            <i class="bi bi-cloud-arrow-up me-1"></i>
            <span id="albumBotonTexto">Guardar</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Cargo de la consulta ============ -->
<div class="modal fade" id="modalCargo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-cash-coin me-2"></i>Cargo al paciente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formCargo">
        <input type="hidden" name="id_consulta" id="cgConsulta">
        <input type="hidden" name="id_registro_paciente" id="cgPaciente">
        <input type="hidden" name="id_procedimiento" id="cgProcedimiento">

        <div class="modal-body">
          <?php /* El catalogo trae el precio sugerido; se puede cambiar
                   por caso, que es lo que pasa a diario. Lo que no se
                   acepta es un importe ya calculado desde el navegador. */ ?>
          <div class="mb-3">
            <label class="form-label">Procedimiento</label>
            <div class="input-group">
              <input type="text" class="form-control" id="cgProcTexto"
                     placeholder="Ninguno del cat&aacute;logo" readonly>
              <button type="button" class="btn btn-outline-morado" id="cgBuscarProc">
                <i class="bi bi-search"></i>
              </button>
              <button type="button" class="btn btn-light" id="cgQuitarProc"
                      title="Quitar">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <div class="form-text">
              Opcional. Si lo eliges, se llenan la descripci&oacute;n y el precio.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Descripci&oacute;n <span class="req">*</span></label>
            <input type="text" class="form-control" name="descripcion"
                   id="cgDescripcion" maxlength="200" required>
          </div>

          <div class="row g-3">
            <div class="col-3">
              <label class="form-label">Cantidad</label>
              <input type="number" class="form-control text-center" name="cantidad"
                     id="cgCantidad" value="1" min="1" max="99">
            </div>
            <div class="col-5">
              <label class="form-label">Precio unitario <span class="req">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control text-end" name="precio_unitario"
                       id="cgPrecio" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-4">
              <label class="form-label">Importe</label>
              <input type="text" class="form-control text-end fw-semibold"
                     id="cgImporte" value="$0.00" readonly>
            </div>
          </div>

          <?php if (Auth::puede('cajas.descuento')): ?>
          <hr>
          <div class="row g-3">
            <div class="col-4">
              <label class="form-label">Descuento</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control text-end" name="descuento"
                       id="cgDescuento" value="0" step="0.01" min="0">
              </div>
            </div>
            <div class="col-8">
              <label class="form-label">Motivo del descuento</label>
              <input type="text" class="form-control" name="motivo_descuento"
                     id="cgMotivoDesc" maxlength="150"
                     placeholder="HERMANO DE PACIENTE, CORTESIA...">
            </div>
          </div>
          <div class="form-text mt-2">
            El descuento queda registrado a tu nombre.
          </div>
          <?php else: ?>
          <input type="hidden" name="descuento" value="0">
          <div class="alert alert-light border py-2 small d-flex gap-2 mt-3 mb-0">
            <i class="bi bi-lock" style="color:var(--gris-600)"></i>
            <span>No tienes permiso para autorizar descuentos.</span>
          </div>
          <?php endif; ?>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Registrar cargo</button>
        </div>
      </form>
    </div>
  </div>
</div>
