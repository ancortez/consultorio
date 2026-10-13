<?php
/**
 * Agenda del consultorio.
 *
 * Vista de un dia con los bloques de media hora. Se ve de un golpe que
 * horarios estan libres, que es lo que necesita recepcion cuando tiene
 * al paciente enfrente preguntando "cuando puede ser".
 */
$dentistas = Agenda::dentistasPermitidos();
$estados   = Conexion::todos('SELECT * FROM estado_cita ORDER BY id_estado_cita');
$salas     = Conexion::todos('SELECT * FROM consultorio_sala WHERE estatus = 1');

// Un dentista sin permiso de agenda solo se ve a si mismo.
$soloYo = $usuarioSesion['es_dentista']
    && (int) $usuarioSesion['id_rol'] !== 1
    && !Auth::puede('consultas.agenda');

$fechaInicial = $_GET['fecha'] ?? date('Y-m-d');

// Llegando desde la ficha de un paciente, la cita se abre con el ya
// puesto: el dato lo sabemos y pedirlo de nuevo es hacer repetir
// trabajo a quien esta en recepcion con el tutor enfrente.
$pacientePrevio = paramInt('paciente')
    ? Conexion::uno(
        'SELECT id_registro_paciente, nombre_completo, num_expediente
           FROM v_paciente WHERE id_registro_paciente = :r',
        [':r' => paramInt('paciente')])
    : null;
?>
<?php /* Llegando con "?nueva=1" —el boton de la vista de semana— la
         cita se abre sola. La marca va en el HTML y no en el
         JavaScript leyendo la direccion porque asi el permiso lo
         decide PHP: quien no puede agendar no recibe la marca, y el
         modal no se abre aunque alguien escriba el parametro a mano. */ ?>
<?php if (paramInt('nueva')
         && (Auth::puede('consultas.agenda') || $usuarioSesion['es_dentista'])): ?>
<div id="agAbrirNueva" class="d-none"></div>
<?php endif; ?>

<?php /* Llegando con "?cita=N" —el boton Modificar del detalle, en la
         vista de semana— esa cita se abre para editarla. Se manda solo
         el id; los datos los tiene el listado del dia, que de todas
         formas hay que cargar. */ ?>
<?php if (paramInt('cita')): ?>
<div id="agAbrirCita" class="d-none" data-id="<?= paramInt('cita') ?>"></div>
<?php endif; ?>

<?php if ($pacientePrevio): ?>
<div id="agPacientePrevio" class="d-none"
     data-id="<?= (int) $pacientePrevio['id_registro_paciente'] ?>"
     data-nombre="<?= e($pacientePrevio['nombre_completo']) ?>"></div>
<?php endif; ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="titulo-vista">Agenda</h1>
        <p class="subtitulo-vista" id="agSubtitulo">
            Bloques de <?= Agenda::BLOQUE ?> minutos
            &middot; <?= sprintf('%02d:00 a %02d:00', Agenda::HORA_INICIO, Agenda::HORA_FIN) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-light"
           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=semana">
            <i class="bi bi-calendar3-week me-1"></i> Ver la semana
        </a>
        <?php if (Auth::puede('consultas.agenda') || $usuarioSesion['es_dentista']): ?>
        <button class="btn btn-morado" id="btnNuevaCita">
            <i class="bi bi-calendar-plus me-1"></i> Nueva cita
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">D&iacute;a</label>
                <div class="input-group">
                    <button class="btn btn-outline-secondary" type="button" id="agAyer"
                            title="D&iacute;a anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <input type="date" class="form-control" id="agFecha"
                           value="<?= e($fechaInicial) ?>" style="width:170px">
                    <button class="btn btn-outline-secondary" type="button" id="agManana"
                            title="D&iacute;a siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="btn btn-outline-morado" type="button" id="agHoy">Hoy</button>
                </div>
            </div>

            <div class="col-3">
                <label class="form-label">Dentista</label>
                <select class="form-select" id="agDentista" <?= $soloYo ? 'disabled' : '' ?>>
                    <?php if (!$soloYo): ?><option value="">Todos</option><?php endif; ?>
                    <?php foreach ($dentistas as $d): ?>
                        <?php /* Llegando de la vista de semana se respeta el
                                 dentista que alla estaba filtrado: cambiarlo
                                 por "Todos" a mitad de camino haria que la
                                 agenda se viera distinta de la que se acaba
                                 de dejar. */ ?>
                        <option value="<?= (int) $d['id_usuario'] ?>"
                            <?= $soloYo
                                || paramInt('dentista') === (int) $d['id_usuario']
                                    ? 'selected' : '' ?>>
                            <?= e($d['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-2">
                <label class="form-label">Estatus</label>
                <select class="form-select" id="agEstado">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $es): ?>
                        <option value="<?= (int) $es['id_estado_cita'] ?>">
                            <?= e($es['nombre_estado']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col text-end">
                <span class="text-secondary small" id="agResumen"></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ============ Listado del dia ============ -->
    <div class="col-7">
        <div class="card">
            <div class="card-header" id="agTituloDia">Citas del d&iacute;a</div>
            <div class="tabla-con-menu">
                <table class="table tabla-sistema">
                    <thead><tr>
                        <th style="width:110px">Horario</th>
                        <th>Paciente</th>
                        <th>Motivo</th>
                        <th style="width:150px">Dentista</th>
                        <th style="width:120px">Estatus</th>
                        <th class="col-acciones"></th>
                    </tr></thead>
                    <tbody id="agTabla">
                        <tr class="fila-vacia"><td colspan="6">
                            <div class="spinner-border spinner-border-sm me-2"
                                 style="color:var(--morado-600)"></div>Cargando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ Espacios libres ============ -->
    <div class="col-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock me-2"></i>Espacios del d&iacute;a
            </div>
            <div class="card-body">
                <p class="text-secondary" style="font-size:12px" id="agEspaciosAyuda">
                    Elige un dentista para ver sus espacios libres.
                </p>
                <div class="rejilla-espacios" id="agEspacios"></div>
            </div>
        </div>
    </div>
</div>

<!-- ============ Modal de cita ============ -->
<div class="modal fade" id="modalCita" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-calendar-plus me-2"></i>
          <span id="tituloModalCita">Nueva cita</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formCita" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="id_cita" id="citaId">
          <input type="hidden" name="id_registro_paciente" id="citaPaciente">
          <input type="hidden" name="hora" id="citaHora">

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Paciente <span class="req">*</span></label>
              <input type="text" readonly class="form-control selector-modal"
                     id="citaPacienteTexto" placeholder="Da clic para buscar el paciente">
            </div>

            <div class="col-6">
              <label class="form-label">Dentista <span class="req">*</span></label>
              <select class="form-select" name="id_usuario_dentista"
                      id="citaDentista" required>
                <?php foreach ($dentistas as $d): ?>
                  <option value="<?= (int) $d['id_usuario'] ?>">
                    <?= e($d['nombre_completo']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-3">
              <label class="form-label">Fecha <span class="req">*</span></label>
              <input type="date" class="form-control" name="fecha" id="citaFecha"
                     min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-3">
              <label class="form-label">Duraci&oacute;n</label>
              <select class="form-select" name="duracion_min" id="citaDuracion">
                <option value="30">30 minutos</option>
                <option value="60">1 hora</option>
                <option value="90">1 hora 30</option>
                <option value="120">2 horas</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Horario <span class="req">*</span></label>
              <div class="rejilla-espacios" id="citaEspacios">
                <div class="text-secondary small">
                  Elige dentista y fecha para ver los espacios libres.
                </div>
              </div>
            </div>

            <div class="col-6">
              <label class="form-label">Motivo</label>
              <input type="text" class="form-control" name="motivo" maxlength="255"
                     placeholder="LIMPIEZA, REVISION, CONTINUACION...">
            </div>
            <div class="col-6">
              <label class="form-label">Consultorio</label>
              <select class="form-select" name="id_sala">
                <option value="">Sin asignar</option>
                <?php foreach ($salas as $s): ?>
                  <option value="<?= (int) $s['id_sala'] ?>"><?= e($s['nombre_sala']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Notas</label>
              <textarea class="form-control" name="notas" rows="2"></textarea>
            </div>

            <div class="col-12">
              <div class="alert alert-light border py-2 small mb-0 d-flex gap-2">
                <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
                <span>La cita queda como <b>Pendiente</b> hasta que se confirme
                      con el paciente, desde el tablero de Inicio.</span>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado" id="citaGuardar" disabled>
            <i class="bi bi-check-lg me-1"></i> Agendar cita
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Modal buscador de paciente ============ -->
<div class="modal fade" id="modalBuscaPaciente" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-search me-2"></i>Buscar paciente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="input-group mb-3">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="bpFiltro"
                 placeholder="Nombre, tel&eacute;fono o expediente" autocomplete="off">
        </div>
        <div class="border rounded lista-seleccion" id="bpLista">
          <div class="p-4 text-center text-secondary small">
            Escribe para buscar.
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_modal_detalle_cita.php'; ?>
