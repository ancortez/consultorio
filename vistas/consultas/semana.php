<?php
/**
 * Agenda: vista de semana.
 *
 * La pregunta diaria del consultorio no es "que hay hoy" sino "cuando
 * la puedo meter". Un dia a la vez no la contesta: hay que ir pasando
 * pantallas hasta dar con un hueco. La rejilla de la semana la
 * contesta de un vistazo, porque los huecos libres son justamente los
 * espacios en blanco.
 *
 * Se puede arrastrar una cita a otro hueco para reagendarla, con
 * confirmacion: arrastrar sin querer es facil.
 */
$dentistas = Agenda::dentistasPermitidos();

$soloYo = $usuarioSesion['es_dentista']
    && (int) $usuarioSesion['id_rol'] !== 1
    && !Auth::puede('consultas.agenda');

$puedeAgendar = Auth::puede('consultas.agenda') || $usuarioSesion['es_dentista'];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="titulo-vista">Agenda de la semana</h1>
        <p class="subtitulo-vista" id="smSubtitulo">Cargando...</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-light"
           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda">
            <i class="bi bi-calendar-day me-1"></i> Ver por d&iacute;a
        </a>
        <?php if (Auth::puede('consultas.agenda')): ?>
        <button class="btn btn-outline-morado" id="smBloquear">
            <i class="bi bi-slash-circle me-1"></i> Bloquear horario
        </button>
        <?php endif; ?>
        <?php if ($puedeAgendar): ?>
        <button class="btn btn-morado" id="smNuevaCita">
            <i class="bi bi-calendar-plus me-1"></i> Nueva cita
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Semana</label>
                <div class="input-group">
                    <button class="btn btn-outline-secondary" type="button" id="smAntes"
                            title="Semana anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <input type="date" class="form-control" id="smLunes" style="width:170px">
                    <button class="btn btn-outline-secondary" type="button" id="smDespues"
                            title="Semana siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="btn btn-outline-morado" type="button" id="smEstaSemana">
                        Esta semana
                    </button>
                </div>
            </div>

            <div class="col-3">
                <label class="form-label">Dentista</label>
                <select class="form-select" id="smDentista" <?= $soloYo ? 'disabled' : '' ?>>
                    <?php foreach ($dentistas as $d): ?>
                        <option value="<?= (int) $d['id_usuario'] ?>">
                            <?= e($d['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col text-end">
                <span class="text-secondary small" id="smResumen"></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-9">
        <div class="card">
            <div class="semana-caja" id="smRejilla">
                <div class="cargando">
                    <div class="spinner-border spinner-border-sm"
                         style="color:var(--morado-600)"></div>
                    Cargando la semana...
                </div>
            </div>
        </div>
    </div>

    <!-- ============ Lista de espera ============ -->
    <div class="col-3">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-hourglass-split me-2"></i>Lista de espera</span>
                <?php if (Auth::puede('consultas.agenda')): ?>
                <button type="button" class="btn btn-sm btn-rosa" id="smAgregarEspera">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body py-2" id="smEspera">
                <div class="exp-vacio">Cargando...</div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-slash-circle me-2"></i>Bloqueos
            </div>
            <div class="card-body py-2" id="smBloqueos">
                <div class="exp-vacio">Sin bloqueos esta semana.</div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_modales_semana.php'; ?>
