<?php
/**
 * Vista detalle_paciente.
 * Card de identificacion a la izquierda + card grande con pestanias:
 * Identificacion, Canales de contacto, Domicilio y Tutor.
 */

$idExpediente = paramInt('id');

$pac = $idExpediente
    ? Conexion::uno(
        'SELECT vp.*, rp.fecha_apertura, rp.observaciones,
                p.id_genero, p.curp, p.id_tipo_persona
           FROM v_paciente vp
           JOIN registro_paciente rp ON rp.id_registro_paciente = vp.id_registro_paciente
           JOIN persona p ON p.id_persona = vp.id_persona
          WHERE vp.id_registro_paciente = :id',
        [':id' => $idExpediente])
    : null;

if ($pac === null) {
    echo '<div class="alert alert-warning d-flex align-items-center gap-2">'
       . '<i class="bi bi-exclamation-triangle"></i>'
       . 'No se encontr&oacute; el expediente solicitado. '
       . '<a href="' . URL_BASE . '/index.php?modulo=paciente&vista=buscar_paciente">Volver a la b&uacute;squeda</a>'
       . '</div>';
    return;
}

$idPersona = (int) $pac['id_persona'];

$contactos = Conexion::todos(
    'SELECT cp.*, mc.nombre_medio_contacto, mc.tipo_dato
       FROM contacto_persona cp
       JOIN medio_contacto mc ON mc.id_medio_contacto = cp.id_medio_contacto
      WHERE cp.id_persona = :p AND cp.estatus = 1
      ORDER BY cp.es_principal DESC, cp.id_contacto_persona',
    [':p' => $idPersona]
);

$domicilios = Conexion::todos(
    'SELECT * FROM v_domicilio WHERE id_persona = :p AND estatus = 1
      ORDER BY es_principal DESC, id_domicilio',
    [':p' => $idPersona]
);

$tutores = Conexion::todos(
    'SELECT et.id_expediente_tutor, et.id_parentesco, et.es_responsable_pago,
            p.id_persona, p.nombres, p.ap_paterno, p.ap_materno, p.rfc,
            pa.nombre_parentesco,
            (SELECT CONCAT_WS(", ", vd.calle_numero, vd.colonia, vd.nombre_municipio)
               FROM v_domicilio vd
              WHERE vd.id_persona = p.id_persona AND vd.estatus = 1
              ORDER BY vd.es_principal DESC LIMIT 1) AS domicilio
       FROM expediente_tutor et
       JOIN persona p ON p.id_persona = et.id_persona
       LEFT JOIN parentesco pa ON pa.id_parentesco = et.id_parentesco
      WHERE et.id_registro_paciente = :r AND et.estatus = 1',
    [':r' => $idExpediente]
);

$alertas = Conexion::todos(
    'SELECT a.nombre_antecedente, ea.detalle
       FROM expediente_antecedente ea
       JOIN antecedente a ON a.id_antecedente = ea.id_antecedente
      WHERE ea.id_registro_paciente = :r AND ea.estatus = 1
        AND a.requiere_alerta = 1',
    [':r' => $idExpediente]
);

// Cuantos documentos le faltan por escanear. Decide si la pestania del
// expediente se resalta.
$pendientes = Documentos::cuantosPendientes($idExpediente);

$tiposIdentificacion = Conexion::todos(
    'SELECT * FROM tipo_identificacion WHERE estatus = 1
      ORDER BY nombre_identificacion');

$generos    = Conexion::todos('SELECT * FROM genero ORDER BY id_genero');
$medios     = Conexion::todos('SELECT * FROM medio_contacto ORDER BY id_medio_contacto');
$parentescos = Conexion::todos('SELECT * FROM parentesco ORDER BY id_parentesco');
$estados    = Conexion::todos('SELECT * FROM estado ORDER BY nombre_estado');
$estadoDef  = (int) parametro('estado_default', (string) ESTADO_DEFAULT);

// Si ya hay un domicilio, la parte superior del formulario se prellena
// con el primero encontrado (tal como pide la composicion).
$domPre = $domicilios[0] ?? null;
?>

<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <nav style="font-size:12px" class="mb-1">
                    <a href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=buscar_paciente"
                       class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Buscar paciente
                    </a>
                </nav>
                <h1 class="titulo-vista">Detalle del paciente</h1>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-morado"
                        id="btnConsentimiento">
                    <i class="bi bi-file-earmark-text me-1"></i> Consentimiento
                </button>
                <?php if (Auth::puede('consultas.crear')): ?>
                <a class="btn btn-outline-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=nueva_consulta&id=<?= $idExpediente ?>">
                    <i class="bi bi-clipboard2-plus me-1"></i> Nueva consulta
                </a>
                <?php endif; ?>
                <?php if (Auth::puede('consultas.plan')): ?>
                <a class="btn btn-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=nuevo_plan_tratamiento&id=<?= $idExpediente ?>">
                    <i class="bi bi-list-check me-1"></i> Nuevo plan de tratamiento
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <!-- ============ Card de identificacion ============ -->
            <div class="col-auto" style="width:290px">
                <div class="card card-identidad">
                    <div class="cabecera"></div>
                    <?php /* El circulo muestra la foto recortada si existe, y si no
                             las iniciales. Los dos elementos estan siempre en el
                             HTML: al subir una foto el JavaScript intercambia cual
                             se ve, sin recargar la pagina. */ ?>
                    <img class="foto <?= empty($pac['foto']) ? 'd-none' : '' ?>"
                         id="fotoPaciente"
                         src="<?= URL_BASE ?>/avatar.php?p=<?= $idPersona ?>"
                         alt="Fotograf&iacute;a del paciente">
                    <div class="foto <?= empty($pac['foto']) ? '' : 'd-none' ?>"
                         id="inicialesPaciente"><?= e(iniciales($pac['nombre_completo'])) ?></div>

                    <div class="card-body pt-2">
                        <div class="nombre"><?= e($pac['nombre_completo']) ?></div>
                        <div class="text-center mb-3">
                            <span class="badge badge-suave"><?= e($pac['num_expediente']) ?></span>
                        </div>

                        <div class="dato-identidad">
                            <i class="bi bi-cake2"></i>
                            <div>
                                <div class="etiqueta">Edad</div>
                                <div class="valor">
                                    <?= $pac['edad'] !== null ? (int) $pac['edad'] . ' a&ntilde;os' : 'Sin registrar' ?>
                                </div>
                            </div>
                        </div>
                        <div class="dato-identidad">
                            <i class="bi bi-telephone"></i>
                            <div>
                                <div class="etiqueta">Tel&eacute;fono</div>
                                <div class="valor"><?= e($pac['telefono'] ?: 'Sin registrar') ?></div>
                            </div>
                        </div>
                        <div class="dato-identidad">
                            <i class="bi bi-calendar-check"></i>
                            <div>
                                <div class="etiqueta">&Uacute;ltima consulta</div>
                                <div class="valor">
                                    <?= e(fecha($pac['ultima_consulta']) ?: 'Sin consultas') ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($alertas): ?>
                        <div class="mt-3">
                            <div class="etiqueta text-uppercase fw-semibold mb-2"
                                 style="font-size:11px;color:var(--error)">
                                <i class="bi bi-exclamation-triangle-fill"></i> Alertas cl&iacute;nicas
                            </div>
                            <?php foreach ($alertas as $a): ?>
                                <span class="chip-estado chip-alerta-clinica d-block mb-1"
                                      title="<?= e($a['detalle'] ?? '') ?>">
                                    <?= e($a['nombre_antecedente']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (Auth::puede('paciente.editar')): ?>
                        <button type="button" class="btn btn-outline-morado btn-sm w-100 mt-3"
                                id="btnCambiarFoto">
                            <i class="bi bi-camera me-1"></i>
                            <?= empty($pac['foto']) ? 'Subir fotograf&iacute;a' : 'Cambiar fotograf&iacute;a' ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ============ Card grande con pestanias ============ -->
            <div class="col">
                <div class="card">
                    <div class="card-body pb-0">
                        <ul class="nav nav-tabs nav-tabs-morado" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab"
                                        data-bs-target="#tab-identificacion" type="button">
                                    <i class="bi bi-person-badge me-1"></i> Identificaci&oacute;n
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab"
                                        data-bs-target="#tab-contacto" type="button">
                                    <i class="bi bi-chat-dots me-1"></i> Canales de contacto
                                    <span class="badge badge-suave ms-1"><?= count($contactos) ?></span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab"
                                        data-bs-target="#tab-domicilio" type="button">
                                    <i class="bi bi-geo-alt me-1"></i> Domicilio
                                    <span class="badge badge-suave ms-1"><?= count($domicilios) ?></span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab"
                                        data-bs-target="#tab-tutor" type="button">
                                    <i class="bi bi-people me-1"></i> Tutor
                                    <span class="badge badge-suave ms-1"><?= count($tutores) ?></span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab"
                                        data-bs-target="#tab-planes" type="button">
                                    <i class="bi bi-list-check me-1"></i> Planes de tratamiento
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab"
                                        data-bs-target="#tab-historia" type="button">
                                    <i class="bi bi-clipboard2-pulse me-1"></i> Historia cl&iacute;nica
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link <?= $pendientes > 0 ? 'con-pendientes' : '' ?>"
                                        data-bs-toggle="tab" id="btnTabExpediente"
                                        data-bs-target="#tab-expediente" type="button"
                                        title="<?= $pendientes > 0
                                            ? $pendientes . ' documento(s) sin digitalizar'
                                            : 'Expediente completo' ?>">
                                    <i class="bi bi-folder2-open me-1"></i> Expediente electr&oacute;nico
                                    <span class="badge <?= $pendientes > 0 ? 'badge-pendiente' : 'badge-suave' ?> ms-1"
                                          id="expBadge"><?= $pendientes ?></span>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body">
                    <div class="tab-content"
                         data-id-expediente="<?= $idExpediente ?>"
                         data-id-persona="<?= $idPersona ?>">

                        <!-- ---------- Pestania: Identificacion ---------- -->
                        <div class="tab-pane fade show active" id="tab-identificacion">
                            <form id="formIdentificacion" autocomplete="off">
                                <input type="hidden" name="id_persona" value="<?= $idPersona ?>">
                                <div class="row g-3">
                                    <div class="col-4">
                                        <label class="form-label">Nombres <span class="req">*</span></label>
                                        <input type="text" class="form-control" name="nombres"
                                               value="<?= e($pac['nombres']) ?>" maxlength="80" required>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Apellido paterno <span class="req">*</span></label>
                                        <input type="text" class="form-control" name="ap_paterno"
                                               value="<?= e($pac['ap_paterno']) ?>" maxlength="60" required>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Apellido materno</label>
                                        <input type="text" class="form-control" name="ap_materno"
                                               value="<?= e($pac['ap_materno']) ?>" maxlength="60">
                                    </div>

                                    <div class="col-3">
                                        <label class="form-label">Fecha de nacimiento</label>
                                        <input type="date" class="form-control" name="fecha_nacim"
                                               value="<?= e($pac['fecha_nacim']) ?>" max="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">G&eacute;nero</label>
                                        <select class="form-select" name="id_genero">
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($generos as $g): ?>
                                            <option value="<?= (int) $g['id_genero'] ?>"
                                                <?= (int) $pac['id_genero'] === (int) $g['id_genero'] ? 'selected' : '' ?>>
                                                <?= e($g['nombre_genero']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">RFC</label>
                                        <input type="text" class="form-control text-uppercase" name="rfc"
                                               value="<?= e($pac['rfc']) ?>" maxlength="13">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">Ocupaci&oacute;n</label>
                                        <input type="text" readonly
                                               class="form-control selector-modal"
                                               id="ocupacionTexto"
                                               data-selector="ocupacion"
                                               data-destino="ocupacionId"
                                               placeholder="Selecciona..."
                                               value="<?= e($pac['nombre_ocupacion']) ?>">
                                        <input type="hidden" name="id_ocupacion" id="ocupacionId"
                                               value="<?= e((string) ($pac['id_ocupacion'] ?? '')) ?>">
                                        <div class="form-text" id="ocupacionAviso"></div>
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label">Tutor</label>
                                        <select class="form-select" id="selTutorActivo" disabled>
                                            <?php if (!$tutores): ?>
                                                <option>Sin tutores registrados</option>
                                            <?php else: foreach ($tutores as $t): ?>
                                                <option>
                                                    <?= e(nombreCompleto($t['nombres'], $t['ap_paterno'], $t['ap_materno'])) ?>
                                                    <?= $t['nombre_parentesco'] ? ' (' . e($t['nombre_parentesco']) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <div class="form-text">
                                            Se administran desde la pesta&ntilde;a <b>Tutor</b>.
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Expediente abierto el</label>
                                        <input type="text" class="form-control" readonly
                                               value="<?= e(fecha($pac['fecha_apertura'])) ?>">
                                    </div>

                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-morado">
                                            <i class="bi bi-check-lg me-1"></i> Guardar cambios
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- ---------- Pestania: Canales de contacto ---------- -->
                        <div class="tab-pane fade" id="tab-contacto">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <p class="form-label mb-0">Datos de contacto registrados</p>
                                <button type="button" class="btn btn-sm btn-rosa"
                                        data-accion="nuevo-contacto"
                                        data-persona="<?= $idPersona ?>"
                                        data-nombre="<?= e($pac['nombre_completo']) ?>">
                                    <i class="bi bi-plus-lg me-1"></i> Nuevo medio de contacto
                                </button>
                            </div>

                            <div class="border rounded tabla-con-menu">
                                <table class="table tabla-sistema mb-0">
                                    <thead><tr>
                                        <th style="width:50px">#</th>
                                        <th style="width:220px">Medio de contacto</th>
                                        <th>Valor</th>
                                        <th class="col-acciones"></th>
                                    </tr></thead>
                                    <tbody id="tablaContactos">
                                    <?php if (!$contactos): ?>
                                        <tr class="fila-vacia"><td colspan="4">
                                            Sin canales de contacto registrados.
                                        </td></tr>
                                    <?php else: foreach ($contactos as $i => $c): ?>
                                        <tr data-id="<?= (int) $c['id_contacto_persona'] ?>"
                                            data-contacto='<?= e(json_encode([
                                                "id_contacto_persona" => (int) $c["id_contacto_persona"],
                                                "id_medio_contacto"   => (int) $c["id_medio_contacto"],
                                                "valor_contacto"      => $c["valor_contacto"],
                                                "es_principal"        => (int) $c["es_principal"],
                                            ], JSON_UNESCAPED_UNICODE)) ?>'>
                                            <td class="text-secondary"><?= $i + 1 ?></td>
                                            <td>
                                                <?= e($c['nombre_medio_contacto']) ?>
                                                <?php if ((int) $c['es_principal'] === 1): ?>
                                                    <span class="badge badge-suave ms-1">Principal</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold"><?= e($c['valor_contacto']) ?></td>
                                            <td class="col-acciones">
                                                <div class="dropdown">
                                                    <button type="button" class="btn-menu-fila"
                                                            data-bs-toggle="dropdown"
                                                            data-bs-auto-close="true"
                                                            aria-label="Acciones">
                                                        <i class="bi bi-list fs-5"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="#"
                                                               data-accion="editar-contacto">
                                                            <i class="bi bi-pencil me-2"></i>Modificar</a></li>
                                                        <li><a class="dropdown-item text-danger" href="#"
                                                               data-accion="eliminar-contacto">
                                                            <i class="bi bi-trash me-2"></i>Eliminar</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ---------- Pestania: Domicilio ---------- -->
                        <div class="tab-pane fade" id="tab-domicilio">
                            <form id="formDomicilio" autocomplete="off">
                                <input type="hidden" name="id_persona" value="<?= $idPersona ?>">
                                <input type="hidden" name="id_domicilio" id="dom_id"
                                       value="<?= $domPre ? (int) $domPre['id_domicilio'] : '' ?>">

                                <div class="row g-3">
                                    <div class="col-3">
                                        <label class="form-label">Estado <span class="req">*</span></label>
                                        <select class="form-select" name="id_estado" id="dom_estado"
                                                data-default="<?= $estadoDef ?>" required>
                                            <?php foreach ($estados as $es): ?>
                                            <option value="<?= (int) $es['id_estado'] ?>"
                                                <?= (int) ($domPre['id_estado'] ?? $estadoDef) === (int) $es['id_estado'] ? 'selected' : '' ?>>
                                                <?= e($es['nombre_estado']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">Municipio <span class="req">*</span></label>
                                        <select class="form-select" name="id_municipio" id="dom_municipio"
                                                data-seleccion="<?= (int) ($domPre['id_municipio'] ?? 0) ?>" required>
                                            <option value="">Cargando...</option>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Colonia <span class="req">*</span></label>
                                        <input type="text" readonly
                                               class="form-control selector-modal"
                                               id="dom_colonia_texto"
                                               data-selector="colonia"
                                               data-destino="dom_colonia"
                                               data-municipio="dom_municipio"
                                               data-cp-destino="dom_cp"
                                               placeholder="Selecciona el municipio primero"
                                               value="<?= e(trim(($domPre['colonia'] ?? '') !== ''
                                                     ? $domPre['colonia'] : '')) ?>">
                                        <input type="hidden" name="id_colonia" id="dom_colonia"
                                               value="<?= (int) ($domPre['id_colonia'] ?? 0) ?: '' ?>">
                                    </div>
                                    <div class="col-2">
                                        <label class="form-label">C&oacute;digo postal</label>
                                        <input type="text" class="form-control" id="dom_cp" readonly
                                               value="<?= e($domPre['codigo_postal'] ?? '') ?>">
                                    </div>

                                    <div class="col-12">
                                        <div class="aviso-bloqueo" id="avisoDomicilio">
                                            <i class="bi bi-info-circle"></i>
                                            <span>Selecciona la colonia para poder capturar la calle
                                                  y los dem&aacute;s datos.</span>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label">Calle</label>
                                        <input type="text" class="form-control campo-domicilio" name="calle"
                                               value="<?= e($domPre['calle'] ?? '') ?>" maxlength="150">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">N&uacute;mero exterior</label>
                                        <input type="text" class="form-control campo-domicilio" name="num_ext"
                                               value="<?= e($domPre['num_ext'] ?? '') ?>" maxlength="20">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">N&uacute;mero interior</label>
                                        <input type="text" class="form-control campo-domicilio" name="num_int"
                                               value="<?= e($domPre['num_int'] ?? '') ?>" maxlength="20">
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label">Entre calle 1</label>
                                        <input type="text" class="form-control campo-domicilio" name="entre_calle_1"
                                               value="<?= e($domPre['entre_calle_1'] ?? '') ?>" maxlength="150">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Entre calle 2</label>
                                        <input type="text" class="form-control campo-domicilio" name="entre_calle_2"
                                               value="<?= e($domPre['entre_calle_2'] ?? '') ?>" maxlength="150">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Referencia</label>
                                        <input type="text" class="form-control campo-domicilio" name="referencia"
                                               value="<?= e($domPre['referencia'] ?? '') ?>"
                                               placeholder="Ejemplo: casa de 2 pisos con cancel blanco">
                                    </div>

                                    <div class="col-12 d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-light" id="btnDomicilioNuevo">
                                            <i class="bi bi-plus-lg me-1"></i> Capturar nuevo domicilio
                                        </button>
                                        <button type="submit" class="btn btn-morado campo-domicilio" id="btnDomicilioGuardar"
                                                data-modo="<?= $domPre ? 'modificar' : 'nuevo' ?>">
                                            <i class="bi bi-check-lg me-1"></i>
                                            <span><?= $domPre ? 'Modificar domicilio' : 'Guardar domicilio' ?></span>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <hr class="my-4">

                            <p class="form-label">Domicilios registrados</p>
                            <div class="border rounded tabla-con-menu">
                                <table class="table tabla-sistema mb-0">
                                    <thead><tr>
                                        <th style="width:50px">#</th>
                                        <th>Calle y n&uacute;mero</th>
                                        <th>Colonia y c&oacute;digo postal</th>
                                        <th>Municipio y estado</th>
                                        <th class="col-acciones"></th>
                                    </tr></thead>
                                    <tbody id="tablaDomicilios">
                                    <?php if (!$domicilios): ?>
                                        <tr class="fila-vacia"><td colspan="5">
                                            Sin domicilios registrados.
                                        </td></tr>
                                    <?php else: foreach ($domicilios as $i => $d): ?>
                                        <tr data-id="<?= (int) $d['id_domicilio'] ?>">
                                            <td class="text-secondary"><?= $i + 1 ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= e($d['calle_numero']) ?></span>
                                                <?php if ((int) $d['es_principal'] === 1): ?>
                                                    <span class="badge badge-suave ms-1">Principal</span>
                                                <?php endif; ?>
                                                <?php if (!empty($d['referencia'])): ?>
                                                    <div class="text-secondary" style="font-size:11px">
                                                        <?= e($d['referencia']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e($d['colonia']) ?>
                                                <span class="text-secondary">&middot; <?= e($d['codigo_postal']) ?></span>
                                            </td>
                                            <td><?= e($d['nombre_municipio']) ?>,
                                                <span class="text-secondary"><?= e($d['nombre_estado']) ?></span>
                                            </td>
                                            <td class="col-acciones">
                                                <div class="dropdown">
                                                    <button type="button" class="btn-menu-fila"
                                                            data-bs-toggle="dropdown"
                                                            data-bs-auto-close="true"
                                                            aria-label="Acciones">
                                                        <i class="bi bi-list fs-5"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="#"
                                                               data-accion="editar-domicilio">
                                                            <i class="bi bi-pencil me-2"></i>Modificar domicilio</a></li>
                                                        <li><a class="dropdown-item text-danger" href="#"
                                                               data-accion="eliminar-domicilio">
                                                            <i class="bi bi-trash me-2"></i>Eliminar domicilio</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ---------- Pestania: Tutor ---------- -->
                        <div class="tab-pane fade" id="tab-tutor">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <p class="form-label mb-0">Tutores del paciente</p>
                                <button class="btn btn-sm btn-rosa" data-accion="nuevo-tutor">
                                    <i class="bi bi-person-plus me-1"></i> Nuevo tutor
                                </button>
                            </div>

                            <div class="border rounded tabla-con-menu">
                                <table class="table tabla-sistema mb-0">
                                    <thead><tr>
                                        <th style="width:28%">Nombre completo</th>
                                        <th style="width:140px">Parentesco</th>
                                        <th style="width:140px">RFC</th>
                                        <th>Domicilio</th>
                                        <th class="col-acciones"></th>
                                    </tr></thead>
                                    <tbody id="tablaTutores">
                                    <?php if (!$tutores): ?>
                                        <tr class="fila-vacia"><td colspan="5">
                                            Este paciente no tiene tutores registrados.
                                        </td></tr>
                                    <?php else: foreach ($tutores as $t): ?>
                                        <tr data-id="<?= (int) $t['id_expediente_tutor'] ?>">
                                            <td class="fw-semibold">
                                                <?= e(nombreCompleto($t['nombres'], $t['ap_paterno'], $t['ap_materno'])) ?>
                                                <?php if ((int) $t['es_responsable_pago'] === 1): ?>
                                                    <span class="badge badge-suave ms-1">Responsable de pago</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e($t['nombre_parentesco'] ?? '-') ?></td>
                                            <td class="text-secondary"><?= e($t['rfc'] ?: '-') ?></td>
                                            <td class="text-secondary"><?= e($t['domicilio'] ?: 'Sin domicilio') ?></td>
                                            <td class="col-acciones">
                                                <div class="dropdown">
                                                    <button type="button" class="btn-menu-fila"
                                                            data-bs-toggle="dropdown"
                                                            data-bs-auto-close="true"
                                                            aria-label="Acciones">
                                                        <i class="bi bi-list fs-5"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="#"
                                                               data-accion="editar-tutor"
                                                               data-id-persona="<?= (int) $t['id_persona'] ?>">
                                                            <i class="bi bi-pencil me-2"></i>Modificar datos</a></li>
                                                        <li><a class="dropdown-item text-danger" href="#"
                                                               data-accion="eliminar-tutor">
                                                            <i class="bi bi-person-dash me-2"></i>Eliminar tutor</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ---------- Pestania: Planes de tratamiento ---------- -->
                        <div class="tab-pane fade" id="tab-planes">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <p class="form-label mb-0">Planes de tratamiento</p>
                                    <p class="text-secondary mb-0" style="font-size:12px">
                                        Historial completo con su avance y su saldo.
                                    </p>
                                </div>
                                <?php if (Auth::puede('consultas.plan')): ?>
                                <a class="btn btn-sm btn-rosa"
                                   href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=nuevo_plan_tratamiento&id=<?= $idExpediente ?>">
                                    <i class="bi bi-plus-lg me-1"></i> Nuevo plan
                                </a>
                                <?php endif; ?>
                            </div>

                            <div class="border rounded tabla-con-menu">
                                <table class="table tabla-sistema mb-0">
                                    <thead><tr>
                                        <th style="width:50px">#</th>
                                        <th style="width:110px">Folio</th>
                                        <th style="width:100px">Fecha</th>
                                        <th>Diagn&oacute;stico</th>
                                        <th style="width:130px">Avance</th>
                                        <th style="width:110px">Total</th>
                                        <th style="width:110px">Saldo</th>
                                        <th style="width:110px">Estatus</th>
                                        <th class="col-acciones"></th>
                                    </tr></thead>
                                    <tbody id="tablaPlanes">
                                        <tr class="fila-vacia"><td colspan="9">
                                            <div class="spinner-border spinner-border-sm me-2"
                                                 style="color:var(--morado-600)"></div>Cargando...
                                        </td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ---------- Pestania: Historia clinica ---------- -->
                        <div class="tab-pane fade" id="tab-historia">
                            <?php require __DIR__ . '/_tab_historia.php'; ?>
                        </div>

                        <!-- ---------- Pestania: Expediente electronico ---------- -->
                        <div class="tab-pane fade" id="tab-expediente">
                            <?php require __DIR__ . '/_tab_expediente.php'; ?>
                        </div>

                    </div><!-- /tab-content -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_modales.php'; ?>
<?php require __DIR__ . '/_modal_tutor.php'; ?>
<?php require __DIR__ . '/_modal_contacto.php'; ?>
<?php require __DIR__ . '/_modal_tutor_editar.php'; ?>
<?php require __DIR__ . '/_modal_consentimiento.php'; ?>
