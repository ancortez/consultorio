<?php
/**
 * Seccion "Domicilio" de la ficha del paciente.
 *
 * Vivia dentro de una pestania del detalle. Octubre 2026: la pantalla
 * paso a una ficha de tres columnas y cada pestania se volvio una
 * seccion colapsable. El contenido es el mismo; lo que cambio es el
 * envoltorio.
 */
?>
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

                                    <?php /* Un solo domicilio: no hay boton de
                                             "capturar nuevo".

                                             Ofrecia capturar un segundo
                                             domicilio que el sistema ya no
                                             guarda como segundo desde que se
                                             unifico. Lo unico que existe es
                                             modificar el que hay. */ ?>
                                    <div class="col-12 d-flex justify-content-end gap-2">
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
                        
