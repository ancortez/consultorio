<?php
/**
 * Detalle de consulta: captura y expediente de la cita.
 *
 * La pantalla esta partida en dos columnas con papeles distintos:
 *
 *   Izquierda  - lo clinico: motivo, exploracion, diagnostico, plan.
 *                Es lo que se escribe mientras el paciente esta
 *                enfrente, asi que va del lado de la lectura y con los
 *                campos en el orden en que se pregunta.
 *
 *   Derecha    - lo que se genera: recetas, documentos y notas. Son
 *                cosas que se agregan, no que se llenan, y cada una
 *                abre su propio dialogo.
 *
 * Una consulta cerrada bloquea la columna izquierda pero deja viva la
 * derecha: el registro clinico no se corrige, pero se le siguen
 * anexando papeles y notas.
 */
$idConsulta   = paramInt('id');
$idExpediente = paramInt('paciente');
$idPlan       = paramInt('plan');
$idCita       = paramInt('cita');

// Viniendo de una cita, la consulta hereda lo que ya se sabe. Es el
// unico punto donde ese enlace existe: despues nadie va a acordarse de
// que consulta salio de que cita.
$desdeCita = null;
if ($idCita && !$idConsulta) {
    $desdeCita = Conexion::uno(
        'SELECT c.id_cita, c.id_registro_paciente, c.id_usuario_dentista,
                c.fecha_inicio, c.motivo, pr.nombre_procedimiento
           FROM cita c
           LEFT JOIN procedimiento pr ON pr.id_procedimiento = c.id_procedimiento
          WHERE c.id_cita = :c',
        [':c' => $idCita]
    );

    if ($desdeCita !== null) {
        $idExpediente = (int) $desdeCita['id_registro_paciente'];

        // Si ya se capturo la consulta de esa cita, se abre en lugar
        // de crear una segunda.
        $ya = Conexion::valor(
            'SELECT id_consulta FROM consulta
              WHERE id_cita = :c AND estatus = 1 LIMIT 1',
            [':c' => $idCita]
        );
        if ($ya !== null) {
            $idConsulta = (int) $ya;
            $desdeCita  = null;
        }
    }
}

// Abriendo una consulta existente, el paciente y el plan salen de ella.
if ($idConsulta) {
    $base = Conexion::uno(
        'SELECT id_registro_paciente, id_plan_tratamiento
           FROM consulta WHERE id_consulta = :c AND estatus = 1',
        [':c' => $idConsulta]
    );
    if ($base === null) {
        echo '<div class="alert alert-warning">La consulta no existe. '
           . '<a href="' . URL_BASE . '/index.php?modulo=consultas&vista=consultas">'
           . 'Volver a la lista</a></div>';
        return;
    }
    $idExpediente = (int) $base['id_registro_paciente'];
    $idPlan       = (int) ($base['id_plan_tratamiento'] ?? 0);
}

$paciente = $idExpediente
    ? Conexion::uno(
        'SELECT vp.*,
                (SELECT GROUP_CONCAT(a.nombre_antecedente SEPARATOR ", ")
                   FROM expediente_antecedente ea
                   JOIN antecedente a ON a.id_antecedente = ea.id_antecedente
                  WHERE ea.id_registro_paciente = vp.id_registro_paciente
                    AND ea.estatus = 1 AND a.requiere_alerta = 1) AS alertas
           FROM v_paciente vp
          WHERE vp.id_registro_paciente = :r',
        [':r' => $idExpediente])
    : null;

if ($paciente === null) {
    echo '<div class="alert alert-warning d-flex align-items-center gap-2">'
       . '<i class="bi bi-exclamation-triangle"></i>'
       . 'Selecciona un paciente para registrar la consulta. '
       . '<a href="' . URL_BASE . '/index.php?modulo=paciente&vista=buscar_paciente">'
       . 'Buscar paciente</a></div>';
    return;
}

$dentistas = Conexion::todos(
    'SELECT id_usuario, nombre_completo FROM usuario
      WHERE es_dentista = 1 AND estatus = 1 ORDER BY nombre_completo');

// Planes vivos del paciente, para poder ligar o desligar la consulta.
$planes = Conexion::todos(
    'SELECT pt.id_plan_tratamiento, pt.folio, pt.descripcion, ep.nombre_estado
       FROM plan_tratamiento pt
       JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
      WHERE pt.id_registro_paciente = :r AND pt.id_estado_plan IN (1,2,3)
      ORDER BY pt.fecha DESC',
    [':r' => $idExpediente]
);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <nav style="font-size:12px" class="mb-1">
            <a href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=consultas"
               class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Lista de consultas
            </a>
        </nav>
        <h1 class="titulo-vista">
            <span id="cnTituloFolio"><?= $idConsulta ? '' : 'Nueva consulta' ?></span>
        </h1>
        <p class="subtitulo-vista">
            <a href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= $idExpediente ?>"
               class="text-decoration-none">
                <?= e($paciente['nombre_completo']) ?>
            </a>
            &middot; <?= e($paciente['num_expediente']) ?>
            <?= $paciente['edad'] !== null ? ' &middot; ' . (int) $paciente['edad'] . ' a&ntilde;os' : '' ?>
        </p>
    </div>

    <div class="d-flex gap-2 align-items-center">
        <span class="chip-estado badge-suave d-none" id="cnEstadoChip"></span>
        <?php if (Auth::puede('consultas.crear')): ?>
        <button type="button" class="btn btn-outline-morado" id="cnCerrar" disabled>
            <i class="bi bi-lock me-1"></i> Cerrar consulta
        </button>
        <button type="button" class="btn btn-morado" id="cnGuardar">
            <i class="bi bi-check-lg me-1"></i> Guardar
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($desdeCita): ?>
<div class="aviso-vinculado mb-3">
    <i class="bi bi-calendar-check-fill"></i>
    <div>
        <b>Consulta de la cita de las
           <?= e(substr((string) $desdeCita['fecha_inicio'], 11, 5)) ?>.</b>
        Se prellenaron la hora, el dentista y el motivo. Al guardar, la
        consulta queda ligada a esa cita.
    </div>
</div>
<?php endif; ?>

<?php if (!empty($paciente['alertas'])): ?>
<div class="aviso-almacen mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        <b>Alertas cl&iacute;nicas del paciente</b>
        <div class="mt-1"><?= e($paciente['alertas']) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="aviso-vinculado mb-3 d-none" id="cnAvisoCerrada">
    <i class="bi bi-lock-fill"></i>
    <div>
        <b>Esta consulta est&aacute; cerrada.</b>
        Los datos cl&iacute;nicos ya no se modifican. Si necesitas aclarar
        o corregir algo, agrega una <b>nota de evoluci&oacute;n</b>: as&iacute;
        queda el dato original y la aclaraci&oacute;n, que es como debe
        verse un expediente.
    </div>
</div>

<div class="row g-3">
    <!-- ============ Columna clinica ============ -->
    <div class="col-8">
        <form id="formConsulta" autocomplete="off">
            <input type="hidden" name="id_consulta" id="cnId" value="<?= $idConsulta ?: '' ?>">
            <input type="hidden" name="id_registro_paciente" value="<?= $idExpediente ?>">
            <input type="hidden" name="id_cita" value="<?= $idCita ?: '' ?>">

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label">Fecha y hora</label>
                            <input type="datetime-local" class="form-control"
                                   name="fecha_hora" id="cnFechaHora"
                                   value="<?= $desdeCita
                                       ? e(str_replace(' ', 'T',
                                           substr((string) $desdeCita['fecha_inicio'], 0, 16)))
                                       : date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Dentista <span class="req">*</span></label>
                            <select class="form-select" name="id_usuario_dentista" required>
                                <?php $dentistaPre = $desdeCita
                                    ? (int) $desdeCita['id_usuario_dentista']
                                    : (int) $usuarioSesion['id_usuario']; ?>
                                <?php foreach ($dentistas as $d): ?>
                                <option value="<?= (int) $d['id_usuario'] ?>"
                                    <?= (int) $d['id_usuario'] === $dentistaPre ? 'selected' : '' ?>>
                                    <?= e($d['nombre_completo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Plan de tratamiento</label>
                            <select class="form-select" name="id_plan_tratamiento" id="cnPlan">
                                <option value="">Consulta general (sin plan)</option>
                                <?php foreach ($planes as $p): ?>
                                <option value="<?= (int) $p['id_plan_tratamiento'] ?>"
                                    <?= (int) $p['id_plan_tratamiento'] === $idPlan ? 'selected' : '' ?>>
                                    <?= e($p['folio']) ?> &mdash; <?= e($p['descripcion'] ?? $p['nombre_estado']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Decide en qu&eacute; parte del expediente quedan
                                los papeles de esta consulta.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-chat-left-quote me-2"></i>Motivo de la consulta
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            &iquest;A qu&eacute; vino? <span class="req">*</span>
                        </label>
                        <input type="text" class="form-control" name="motivo"
                               maxlength="255" required
                               placeholder="LO QUE DICE EL TUTOR O EL PACIENTE">
                    </div>
                    <div>
                        <label class="form-label">Padecimiento actual</label>
                        <textarea class="form-control" name="padecimiento_actual"
                                  rows="3"
                                  placeholder="DESDE CU&Aacute;NDO, C&Oacute;MO EMPEZ&Oacute;, QU&Eacute; LO MEJORA O LO EMPEORA"></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-search-heart me-2"></i>Exploraci&oacute;n
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label class="form-label">Presi&oacute;n arterial</label>
                            <input type="text" class="form-control" name="presion_arterial"
                                   maxlength="15" placeholder="110/70">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Frecuencia card&iacute;aca</label>
                            <div class="input-group">
                                <input type="number" class="form-control"
                                       name="frecuencia_cardiaca" min="20" max="250">
                                <span class="input-group-text">lpm</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Temperatura</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="temperatura"
                                       step="0.1" min="30" max="45">
                                <span class="input-group-text">&deg;C</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Hallazgos</label>
                        <textarea class="form-control" name="exploracion" rows="4"
                                  placeholder="LO QUE SE OBSERVA EN BOCA Y EN LA EXPLORACI&Oacute;N GENERAL"></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="bi bi-clipboard2-pulse me-2"></i>Diagn&oacute;stico e indicaciones
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Diagn&oacute;stico</label>
                        <textarea class="form-control" name="diagnostico" rows="2"></textarea>
                        <div class="form-text">
                            Se pide para poder cerrar la consulta.
                        </div>
                    </div>
                    <div>
                        <label class="form-label">
                            Tratamiento realizado e indicaciones
                        </label>
                        <textarea class="form-control" name="plan_indicaciones" rows="4"
                                  placeholder="QU&Eacute; SE HIZO HOY Y QU&Eacute; DEBE HACER EL PACIENTE EN CASA"></textarea>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ============ Columna de anexos ============ -->
    <div class="col-4">
        <?php /* Todo lo de esta columna necesita que la consulta ya
                 exista: un documento tiene que colgar de algo. Mientras
                 no se guarda, se ve pero avisa. */ ?>
        <div class="alert alert-light border py-2 small d-flex gap-2" id="cnAvisoGuardar">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span>Guarda la consulta para poder agregarle recetas,
                  documentos y notas.</span>
        </div>

        <?php if (Auth::puede('cajas.cargo')): ?>
        <?php /* Lo que salio en la consulta y hay que cobrar. Se
                 registra aqui, en caliente, porque si se deja para
                 despues nadie se acuerda de que se hizo de mas. */ ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-cash-coin me-2"></i>Pendientes de pago</span>
                <button type="button" class="btn btn-sm btn-rosa" id="cnNuevoCargo" disabled>
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body py-2" id="cnCargos">
                <div class="exp-vacio">Sin cargos.</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ---------- Recetas ---------- -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-prescription2 me-2"></i>Recetas</span>
                <button type="button" class="btn btn-sm btn-rosa" id="cnNuevaReceta" disabled>
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body py-2" id="cnRecetas">
                <div class="exp-vacio">Sin recetas.</div>
            </div>
        </div>

        <!-- ---------- Documentos ---------- -->
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-folder2-open me-2"></i>Documentos de la consulta
            </div>
            <div class="card-body py-2" id="cnDocumentos">
                <div class="exp-vacio">Sin documentos.</div>
            </div>
        </div>

        <!-- ---------- Notas de evolucion ---------- -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-journal-text me-2"></i>Notas de evoluci&oacute;n</span>
                <button type="button" class="btn btn-sm btn-rosa" id="cnNuevaNota" disabled>
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body py-2" id="cnNotas">
                <div class="exp-vacio">
                    Sin notas. Sirven para dejar constancia de lo que se
                    observ&oacute; o se acord&oacute; y no cabe en los campos de
                    arriba. No generan pendiente de digitalizaci&oacute;n.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_modales_consulta.php'; ?>
<?php require RUTA_VISTAS . '/consultas/_modal_procedimiento.php'; ?>
