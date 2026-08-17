<?php
/**
 * Modificacion completa de un tutor.
 *
 * Repite la estructura de pestanias del detalle del paciente
 * (identificacion, canales de contacto y domicilio) porque un tutor es
 * una persona con las mismas necesidades: hay que poder llamarle y hay
 * que saber donde vive, sobre todo si es el responsable de pago.
 *
 * Reutiliza los mismos endpoints del paciente (contacto_guardar,
 * domicilio_guardar), solo cambia el id_persona que se les manda.
 */
?>
<div class="modal fade" id="modalTutorEditar" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title texto-morado">
          <i class="bi bi-person-gear me-2"></i>
          Datos del tutor
          <span class="badge badge-suave ms-2" id="teNombre"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="teIdVinculo">
        <input type="hidden" id="teIdPersona">

        <ul class="nav nav-tabs nav-tabs-morado mb-3" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab"
                    data-bs-target="#te-identificacion" type="button">
              <i class="bi bi-person-badge me-1"></i> Identificaci&oacute;n
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab"
                    data-bs-target="#te-contacto" type="button">
              <i class="bi bi-chat-dots me-1"></i> Canales de contacto
              <span class="badge badge-suave ms-1" id="teContadorContactos">0</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab"
                    data-bs-target="#te-domicilio" type="button">
              <i class="bi bi-geo-alt me-1"></i> Domicilio
              <span class="badge badge-suave ms-1" id="teContadorDomicilios">0</span>
            </button>
          </li>
        </ul>

        <div class="tab-content">

          <!-- ---------- Identificacion ---------- -->
          <div class="tab-pane fade show active" id="te-identificacion">
            <form id="teFormIdentificacion" autocomplete="off">
              <div class="row g-3">
                <div class="col-4">
                  <label class="form-label">Nombres <span class="req">*</span></label>
                  <input type="text" class="form-control" name="nombres" maxlength="80" required>
                </div>
                <div class="col-4">
                  <label class="form-label">Apellido paterno <span class="req">*</span></label>
                  <input type="text" class="form-control" name="ap_paterno" maxlength="60" required>
                </div>
                <div class="col-4">
                  <label class="form-label">Apellido materno</label>
                  <input type="text" class="form-control" name="ap_materno" maxlength="60">
                </div>

                <div class="col-3 campo-con-marca">
                  <label class="form-label">Fecha de nacimiento</label>
                  <input type="date" class="form-control" name="fecha_nacim"
                         id="teFechaNacim" max="<?= date('Y-m-d') ?>">
                  <div class="form-text" id="teAvisoRfc"></div>
                </div>
                <div class="col-3">
                  <label class="form-label">G&eacute;nero</label>
                  <select class="form-select" name="id_genero">
                    <option value="">Seleccionar...</option>
                    <?php foreach ($generos as $g): ?>
                      <option value="<?= (int) $g['id_genero'] ?>"><?= e($g['nombre_genero']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-3">
                  <label class="form-label">RFC</label>
                  <input type="text" class="form-control" name="rfc" id="teRfc"
                         maxlength="13" placeholder="XXXX000000XXX">
                </div>
                <div class="col-3">
                  <label class="form-label">Ocupaci&oacute;n</label>
                  <input type="text" readonly class="form-control selector-modal"
                         id="teOcupacionTexto" data-selector="ocupacion"
                         data-destino="teOcupacionId" placeholder="Selecciona...">
                  <input type="hidden" name="id_ocupacion" id="teOcupacionId">
                </div>

                <div class="col-4">
                  <label class="form-label">Parentesco <span class="req">*</span></label>
                  <select class="form-select" name="id_parentesco" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($parentescos as $pa): ?>
                      <option value="<?= (int) $pa['id_parentesco'] ?>">
                        <?= e($pa['nombre_parentesco']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-8 d-flex align-items-end">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1"
                           name="es_responsable_pago" id="teResponsable">
                    <label class="form-check-label" for="teResponsable">
                      Es el responsable de pago de este paciente
                    </label>
                  </div>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-12">
                  <p class="form-label mb-1">
                    <i class="bi bi-person-vcard me-1"></i>Identificaci&oacute;n oficial
                  </p>
                  <p class="text-secondary mb-0" style="font-size:12px">
                    Al guardarla se genera un pendiente para escanear la credencial.
                  </p>
                </div>

                <div class="col-4">
                  <label class="form-label">Tipo de identificaci&oacute;n</label>
                  <select class="form-select" name="id_tipo_identificacion"
                          id="teTipoIdent">
                    <option value="">Sin identificaci&oacute;n</option>
                    <?php foreach ($tiposIdentificacion as $ti): ?>
                      <option value="<?= (int) $ti['id_tipo_identificacion'] ?>"
                              data-vigencia="<?= (int) $ti['tiene_vigencia'] ?>">
                        <?= e($ti['nombre_identificacion']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-4">
                  <label class="form-label">N&uacute;mero</label>
                  <input type="text" class="form-control ident-campo"
                         name="numero_identificacion" id="teNumIdent" maxlength="60">
                </div>
                <div class="col-2">
                  <label class="form-label">Emisi&oacute;n</label>
                  <input type="date" class="form-control ident-campo"
                         name="fecha_emision" id="teEmisionIdent"
                         max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-2">
                  <label class="form-label">Vigencia</label>
                  <input type="date" class="form-control ident-campo"
                         name="fecha_vigencia" id="teVigenciaIdent">
                  <div class="form-text" id="teAvisoVigencia"></div>
                </div>

                <div class="col-12 text-end">
                  <button type="submit" class="btn btn-morado">
                    <i class="bi bi-check-lg me-1"></i> Guardar identificaci&oacute;n
                  </button>
                </div>
              </div>
            </form>
          </div>

          <!-- ---------- Canales de contacto ---------- -->
          <div class="tab-pane fade" id="te-contacto">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <p class="form-label mb-0">Datos de contacto del tutor</p>
              <button type="button" class="btn btn-sm btn-rosa" id="teBtnNuevoContacto"
                      data-accion="nuevo-contacto">
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
                <tbody id="teTablaContactos"></tbody>
              </table>
            </div>
          </div>

          <!-- ---------- Domicilio ---------- -->
          <div class="tab-pane fade" id="te-domicilio">

            <!-- Se muestra solo cuando el domicilio se comparte con el paciente -->
            <div class="aviso-vinculado mb-3 d-none" id="teAvisoVinculado">
              <i class="bi bi-link-45deg"></i>
              <div>
                <b>Este domicilio es el mismo que el del paciente.</b><br>
                Cualquier cambio que hagas aqu&iacute; tambi&eacute;n cambia el
                domicilio del paciente. Si el tutor se mud&oacute; y el paciente no,
                usa <b>Capturar nuevo domicilio</b> en lugar de modificar este.
              </div>
            </div>

            <form id="teFormDomicilio" autocomplete="off">
              <input type="hidden" name="id_domicilio" id="teDomId">

              <div class="row g-3">
                <div class="col-3">
                  <label class="form-label">Estado <span class="req">*</span></label>
                  <select class="form-select" name="id_estado" id="teDomEstado"
                          data-default="<?= $estadoDef ?>" required>
                    <?php foreach ($estados as $es): ?>
                      <option value="<?= (int) $es['id_estado'] ?>"
                        <?= (int) $es['id_estado'] === $estadoDef ? 'selected' : '' ?>>
                        <?= e($es['nombre_estado']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-3">
                  <label class="form-label">Municipio <span class="req">*</span></label>
                  <select class="form-select" name="id_municipio" id="teDomMunicipio" required>
                    <option value="">Cargando...</option>
                  </select>
                </div>
                <div class="col-4">
                  <label class="form-label">Colonia <span class="req">*</span></label>
                  <input type="text" readonly class="form-control selector-modal"
                         id="teDomColoniaTexto"
                         data-selector="colonia"
                         data-destino="teDomColonia"
                         data-municipio="teDomMunicipio"
                         data-cp-destino="teDomCp"
                         placeholder="Selecciona el municipio primero">
                  <input type="hidden" name="id_colonia" id="teDomColonia">
                </div>
                <div class="col-2">
                  <label class="form-label">C&oacute;digo postal</label>
                  <input type="text" class="form-control" id="teDomCp" readonly>
                </div>

                <div class="col-12">
                  <div class="aviso-bloqueo" id="teAvisoDomicilio">
                    <i class="bi bi-info-circle"></i>
                    <span>Selecciona la colonia para capturar el resto del domicilio.</span>
                  </div>
                </div>

                <div class="col-6">
                  <label class="form-label">Calle</label>
                  <input type="text" class="form-control te-campo-domicilio" name="calle" maxlength="150">
                </div>
                <div class="col-3">
                  <label class="form-label">N&uacute;mero exterior</label>
                  <input type="text" class="form-control te-campo-domicilio" name="num_ext" maxlength="20">
                </div>
                <div class="col-3">
                  <label class="form-label">N&uacute;mero interior</label>
                  <input type="text" class="form-control te-campo-domicilio" name="num_int" maxlength="20">
                </div>
                <div class="col-6">
                  <label class="form-label">Entre calle 1</label>
                  <input type="text" class="form-control te-campo-domicilio" name="entre_calle_1" maxlength="150">
                </div>
                <div class="col-6">
                  <label class="form-label">Entre calle 2</label>
                  <input type="text" class="form-control te-campo-domicilio" name="entre_calle_2" maxlength="150">
                </div>
                <div class="col-12">
                  <label class="form-label">Referencia</label>
                  <input type="text" class="form-control te-campo-domicilio" name="referencia"
                         placeholder="Ejemplo: casa de 2 pisos con cancel blanco">
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                  <button type="button" class="btn btn-light" id="teBtnDomicilioNuevo">
                    <i class="bi bi-plus-lg me-1"></i> Capturar nuevo domicilio
                  </button>
                  <button type="submit" class="btn btn-morado te-campo-domicilio"
                          id="teBtnDomicilioGuardar" data-modo="nuevo">
                    <i class="bi bi-check-lg me-1"></i>
                    <span>Guardar domicilio</span>
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
                <tbody id="teTablaDomicilios"></tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
