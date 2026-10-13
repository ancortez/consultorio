<?php
/**
 * Seccion "Canales de contacto" de la ficha del paciente.
 *
 * Vivia dentro de una pestania del detalle. Octubre 2026: la pantalla
 * paso a una ficha de tres columnas y cada pestania se volvio una
 * seccion colapsable. El contenido es el mismo; lo que cambio es el
 * envoltorio.
 */
?>
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
                        
