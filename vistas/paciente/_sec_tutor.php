<?php
/**
 * Seccion "Tutor" de la ficha del paciente.
 *
 * Vivia dentro de una pestania del detalle. Octubre 2026: la pantalla
 * paso a una ficha de tres columnas y cada pestania se volvio una
 * seccion colapsable. El contenido es el mismo; lo que cambio es el
 * envoltorio.
 */
?>
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
                        
