<?php
/**
 * Modales del modulo Cajas: abrir turno, cerrarlo y registrar salidas.
 */
?>

<!-- ============ Abrir turno ============ -->
<div class="modal fade" id="modalAbrirTurno" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-unlock me-2"></i>Abrir <span id="abrirNombreCaja"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formAbrirTurno">
        <input type="hidden" name="id_caja" id="abrirIdCaja">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Fondo inicial</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" class="form-control text-end" name="monto_inicial"
                     id="abrirFondo" value="0.00" step="0.01" min="0">
            </div>
            <div class="form-text">
              Con cuanto efectivo empiezas el turno. Si no dejas fondo, deja 0.
            </div>
          </div>
          <div>
            <label class="form-label">Observaciones</label>
            <input type="text" class="form-control" name="observaciones" maxlength="200">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Abrir turno</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Cerrar turno ============ -->
<div class="modal fade" id="modalCerrarTurno" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-lock me-2"></i>Cerrar turno
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formCerrarTurno">
        <input type="hidden" name="id_corte_caja" id="cerrarIdCorte">
        <div class="modal-body">
          <?php /* Primero se captura lo contado y despues se ensenia la
                   diferencia. Al reves, el numero esperado condiciona el
                   conteo y el corte deja de servir para nada. */ ?>
          <div class="mb-3">
            <label class="form-label">
              &iquest;Cu&aacute;nto efectivo contaste? <span class="req">*</span>
            </label>
            <div class="input-group input-group-lg">
              <span class="input-group-text">$</span>
              <input type="number" class="form-control text-end" name="monto_contado"
                     id="cerrarContado" step="0.01" min="0" required autofocus>
            </div>
            <div class="form-text">
              Cuenta el cajon antes de ver el n&uacute;mero del sistema.
            </div>
          </div>

          <div class="cierre-comparativo d-none" id="cerrarComparativo">
            <div>
              <span class="et">El sistema esperaba</span>
              <span class="va" id="cerrarEsperado">$0.00</span>
            </div>
            <div>
              <span class="et">Diferencia</span>
              <span class="va" id="cerrarDiferencia">$0.00</span>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" name="observaciones" rows="2"
                      maxlength="200"
                      placeholder="SI HAY DIFERENCIA, EXPLICA A QUE SE DEBI&Oacute;"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Cerrar turno</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Salida de dinero ============ -->
<div class="modal fade" id="modalEgreso" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-dash-circle me-2"></i>Salida de dinero
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formEgreso">
        <input type="hidden" name="id_corte_caja" id="egresoIdCorte">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-7">
              <label class="form-label">Concepto <span class="req">*</span></label>
              <select class="form-select" name="id_concepto" id="egresoConcepto" required>
                <option value="">Seleccionar...</option>
              </select>
            </div>
            <div class="col-5">
              <label class="form-label">Importe <span class="req">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control text-end" name="monto"
                       step="0.01" min="0.01" required>
              </div>
            </div>
            <div class="col-5">
              <label class="form-label">Forma</label>
              <select class="form-select" name="id_forma_pago" id="egresoForma"></select>
            </div>
            <div class="col-7">
              <label class="form-label">Descripci&oacute;n <span class="req">*</span></label>
              <input type="text" class="form-control" name="descripcion"
                     maxlength="200" required
                     placeholder="DE QU&Eacute; FUE EL GASTO">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>
