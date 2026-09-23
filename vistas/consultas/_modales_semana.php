<?php
/**
 * Modales de la vista de semana: bloqueo de agenda y lista de espera.
 */
$dentistasBloqueo = Agenda::dentistasPermitidos();
?>

<!-- ============ Bloquear horario ============ -->
<div class="modal fade" id="modalBloqueo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-slash-circle me-2"></i>Bloquear horario
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formBloqueo">
        <input type="hidden" name="forzar" id="bqForzar" value="0">

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">&iquest;A qui&eacute;n afecta?</label>
            <select class="form-select" name="id_usuario" id="bqUsuario">
              <?php /* Sin dentista, el bloqueo cierra el consultorio
                       completo: es lo que se quiere para un dia
                       feriado o una capacitacion. */ ?>
              <option value="">Todo el consultorio</option>
              <?php foreach ($dentistasBloqueo as $d): ?>
                <option value="<?= (int) $d['id_usuario'] ?>">
                  Solo <?= e($d['nombre_completo']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Desde <span class="req">*</span></label>
              <input type="datetime-local" class="form-control"
                     name="fecha_inicio" id="bqInicio" required>
            </div>
            <div class="col-6">
              <label class="form-label">Hasta <span class="req">*</span></label>
              <input type="datetime-local" class="form-control"
                     name="fecha_fin" id="bqFin" required>
            </div>
            <div class="col-12">
              <label class="form-label">Motivo <span class="req">*</span></label>
              <input type="text" class="form-control" name="motivo"
                     maxlength="150" required
                     placeholder="VACACIONES, CONGRESO, COMIDA...">
              <div class="form-text">
                Se ve en la agenda en lugar del hueco libre.
              </div>
            </div>
          </div>

          <?php /* Si el rango pisa citas ya agendadas se avisa antes de
                   guardar. A veces es justo lo que se quiere (cerrar y
                   reagendar) y otras es un error de fechas. */ ?>
          <div class="aviso-vinculado mt-3 d-none" id="bqChoques">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
              <b>Hay citas dentro de ese rango.</b>
              <div class="mt-1" id="bqChoquesLista"></div>
              <div class="mt-2" style="font-size:12px">
                Si continúas, el bloqueo se guarda igual y tendr&aacute;s que
                reagendarlas.
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado" id="bqGuardar">Bloquear</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Lista de espera ============ -->
<div class="modal fade" id="modalEspera" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-hourglass-split me-2"></i>Agregar a la lista de espera
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formEspera">
        <input type="hidden" name="id_registro_paciente" id="leIdPaciente">

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Paciente <span class="req">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" id="leBuscar"
                     placeholder="Nombre, expediente o tel&eacute;fono" autocomplete="off">
            </div>
            <div class="lista-seleccion border rounded mt-2 d-none" id="leResultados"></div>
          </div>

          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Con qui&eacute;n</label>
              <select class="form-select" name="id_usuario_dentista">
                <option value="">Cualquiera</option>
                <?php foreach ($dentistasBloqueo as $d): ?>
                  <option value="<?= (int) $d['id_usuario'] ?>">
                    <?= e($d['nombre_completo']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Franja que le acomoda</label>
              <select class="form-select" name="preferencia_horario">
                <option value="3">Cualquiera</option>
                <option value="1">Ma&ntilde;ana</option>
                <option value="2">Tarde</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Motivo</label>
              <input type="text" class="form-control" name="motivo" maxlength="255"
                     placeholder="A QU&Eacute; QUIERE VENIR">
            </div>
            <div class="col-12">
              <label class="form-label">Notas</label>
              <input type="text" class="form-control" name="notas" maxlength="255"
                     placeholder="HORARIOS EN QUE NO PUEDE, OTROS DETALLES">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Agregar</button>
        </div>
      </form>
    </div>
  </div>
</div>
