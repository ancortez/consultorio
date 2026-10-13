<?php
/**
 * Consultas > Planes de tratamiento.
 *
 * Lista de trabajo: solo los planes vivos (propuestos, aceptados y en
 * proceso). Los terminados y cancelados se consultan desde el expediente
 * del paciente, que es donde tiene sentido buscarlos.
 */
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="titulo-vista">Planes de tratamiento</h1>
        <p class="subtitulo-vista">
            Propuestos, aceptados y en proceso
        </p>
    </div>
    <?php if (Auth::puede('consultas.plan')): ?>
    <a class="btn btn-morado"
       href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=buscar_paciente">
        <i class="bi bi-plus-lg me-1"></i> Nuevo plan
    </a>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-6">
                <label class="form-label" for="planFiltro">Filtro de b&uacute;squeda</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <?php /* El filtro puede venir en la direccion: la ficha del
                             paciente enlaza aqui con "?buscar=EXP-0004" para
                             que la lista llegue ya acotada a ese paciente.
                             Llegar a una lista de trescientos renglones
                             sabiendo cual quieres es hacer buscar dos
                             veces. */ ?>
                    <input type="text" class="form-control" id="planFiltro"
                           value="<?= e(limpiarEspacios((string) param('buscar', '')) ?? '') ?>"
                           placeholder="Folio, paciente, expediente o diagn&oacute;stico"
                           autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button"
                            id="planFiltroLimpiar" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-3">
                <label class="form-label" for="planFiltroEstado">Estatus</label>
                <select class="form-select" id="planFiltroEstado">
                    <option value="">Todos los vigentes</option>
                    <?php foreach (Conexion::todos(
                        'SELECT * FROM estado_plan
                          WHERE id_estado_plan IN (1,2,3)
                          ORDER BY id_estado_plan') as $ep): ?>
                        <option value="<?= (int) $ep['id_estado_plan'] ?>">
                            <?= e($ep['nombre_estado']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-3 text-end">
                <span class="text-secondary small" id="planContador"></span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="tabla-con-menu">
        <table class="table tabla-sistema align-middle mb-0">
            <thead><tr>
                <th style="width:50px">#</th>
                <th style="width:150px">Folio</th>
                <th style="width:100px">Fecha</th>
                <th style="width:20%">Descripci&oacute;n</th>
                <th style="width:18%">Paciente</th>
                <th>Diagn&oacute;stico</th>
                <th style="width:150px">Avance</th>
                <th style="width:120px">Estatus</th>
                <th class="col-acciones"></th>
            </tr></thead>
            <tbody id="tablaPlanesAbiertos">
                <tr class="fila-vacia"><td colspan="9">
                    <div class="spinner-border spinner-border-sm me-2"
                         style="color:var(--morado-600)"></div>Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>
