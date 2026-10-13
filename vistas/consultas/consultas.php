<?php
/**
 * Consultas > Lista de consultas.
 *
 * Lo ultimo que se ha atendido, de lo mas reciente a lo mas antiguo.
 * Quien no tiene el permiso consultas.todas ve solo las suyas y ni
 * siquiera se le muestra el filtro de dentista: seria un combo que no
 * cambia nada.
 */
$verTodas = Auth::puede('consultas.todas');

$dentistas = $verTodas
    ? Conexion::todos(
        'SELECT id_usuario, nombre_completo FROM usuario
          WHERE es_dentista = 1 AND estatus = 1 ORDER BY nombre_completo')
    : [];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="titulo-vista">Lista de consultas</h1>
        <p class="subtitulo-vista">
            <?= $verTodas
                ? 'Las &uacute;ltimas consultas del consultorio'
                : 'Tus &uacute;ltimas consultas' ?>
        </p>
    </div>
    <?php if (Auth::puede('consultas.crear')): ?>
    <a class="btn btn-morado"
       href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=buscar_paciente">
        <i class="bi bi-plus-lg me-1"></i> Nueva consulta
    </a>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-<?= $verTodas ? '5' : '8' ?>">
                <label class="form-label" for="cnFiltro">Filtro de b&uacute;squeda</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <?php /* El filtro puede venir en la direccion: la ficha del
                             paciente enlaza aqui con "?buscar=EXP-0004" para
                             que la lista llegue ya acotada a ese paciente.
                             Llegar a una lista de trescientos renglones
                             sabiendo cual quieres es hacer buscar dos
                             veces. */ ?>
                    <input type="text" class="form-control" id="cnFiltro"
                           value="<?= e(limpiarEspacios((string) param('buscar', '')) ?? '') ?>"
                           placeholder="Folio, paciente, expediente, motivo o plan"
                           autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button"
                            id="cnFiltroLimpiar" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <?php if ($verTodas): ?>
            <div class="col-3">
                <label class="form-label" for="cnDentista">Dentista</label>
                <select class="form-select" id="cnDentista">
                    <option value="">Todos</option>
                    <?php foreach ($dentistas as $d): ?>
                        <option value="<?= (int) $d['id_usuario'] ?>">
                            <?= e($d['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-2">
                <label class="form-label" for="cnEstatus">Estatus</label>
                <select class="form-select" id="cnEstatus">
                    <option value="">Todas</option>
                    <option value="0">Abiertas</option>
                    <option value="1">Cerradas</option>
                </select>
            </div>

            <div class="col-2 text-end">
                <span class="text-secondary small" id="cnContador"></span>
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
                <th style="width:130px">Fecha</th>
                <th style="width:22%">Paciente</th>
                <th>Plan de tratamiento</th>
                <th style="width:120px">Estatus</th>
                <th class="col-acciones"></th>
            </tr></thead>
            <tbody id="tablaConsultas">
                <tr class="fila-vacia"><td colspan="7">
                    <div class="spinner-border spinner-border-sm me-2"
                         style="color:var(--morado-600)"></div>Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>
