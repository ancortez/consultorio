<?php
/**
 * Configuracion > Procedimientos dentales.
 *
 * Es la lista de lo que la doctora escribe en el plan de tratamiento.
 * Cada procedimiento puede apuntar a un tratamiento del tarifario para
 * heredar su precio, pero tambien puede tener costo propio o ninguno.
 */
$categorias = Conexion::todos(
    'SELECT * FROM categoria_tratamiento ORDER BY nombre_categoria');

$tratamientos = Conexion::todos(
    'SELECT id_tratamiento, clave, nombre_tratamiento, precio_lista
       FROM tratamiento WHERE estatus = 1
      ORDER BY nombre_tratamiento');
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Procedimientos dentales</h1>
                <p class="subtitulo-vista">
                    Lo que se puede registrar en un plan de tratamiento
                </p>
            </div>
            <button class="btn btn-morado" id="btnNuevoProcedimiento">
                <i class="bi bi-plus-lg me-1"></i> Nuevo procedimiento
            </button>
        </div>

        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-4">
                        <label class="form-label" for="procFiltro">Buscar</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="procFiltro"
                                   placeholder="Nombre o clave" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="procCategoria">Categor&iacute;a</label>
                        <select class="form-select" id="procCategoria">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int) $c['id_categoria'] ?>">
                                    <?= e($c['nombre_categoria']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-2">
                        <label class="form-label" for="procEstatus">Estatus</label>
                        <select class="form-select" id="procEstatus">
                            <option value="1">Activos</option>
                            <option value="0">Inactivos</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-2 text-end">
                        <span class="text-secondary small" id="procContador"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="tabla-con-menu">
                <table class="table tabla-sistema">
                    <thead><tr>
                        <th style="width:55px">#</th>
                        <th>Procedimiento</th>
                        <th style="width:180px">Categor&iacute;a</th>
                        <th style="width:110px">Costo</th>
                        <th style="width:90px">Duraci&oacute;n</th>
                        <th style="width:130px">Caracter&iacute;sticas</th>
                        <th style="width:100px">Estatus</th>
                        <th class="col-acciones"></th>
                    </tr></thead>
                    <tbody id="tablaProcedimientos">
                        <tr class="fila-vacia"><td colspan="8">
                            <div class="spinner-border spinner-border-sm me-2"
                                 style="color:var(--morado-600)"></div>Cargando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============ Modal ============ -->
<div class="modal fade" id="modalProcedimiento" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-clipboard2-plus me-2"></i>
          <span id="tituloModalProc">Nuevo procedimiento</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formProcedimiento" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="id_procedimiento" id="procId">

          <div class="row g-3">
            <div class="col-8">
              <label class="form-label">Nombre <span class="req">*</span></label>
              <input type="text" class="form-control" name="nombre_procedimiento"
                     maxlength="120" required>
            </div>
            <div class="col-4">
              <label class="form-label">Clave</label>
              <input type="text" class="form-control" name="clave" maxlength="20"
                     placeholder="P-RES">
              <div class="form-text">Opcional, para identificarlo r&aacute;pido.</div>
            </div>

            <div class="col-6">
              <label class="form-label">Categor&iacute;a <span class="req">*</span></label>
              <select class="form-select" name="id_categoria" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($categorias as $c): ?>
                  <option value="<?= (int) $c['id_categoria'] ?>">
                    <?= e($c['nombre_categoria']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Tratamiento del tarifario</label>
              <select class="form-select" name="id_tratamiento" id="procTratamiento">
                <option value="">Sin ligar (costo propio)</option>
                <?php foreach ($tratamientos as $t): ?>
                  <option value="<?= (int) $t['id_tratamiento'] ?>"
                          data-precio="<?= (float) $t['precio_lista'] ?>">
                    <?= e($t['nombre_tratamiento']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Al elegirlo se copia su precio.</div>
            </div>

            <div class="col-4">
              <label class="form-label">Costo sugerido</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" name="costo_sugerido"
                       id="procCosto" step="0.01" min="0" value="0.00">
              </div>
            </div>
            <div class="col-4">
              <label class="form-label">Duraci&oacute;n (minutos)</label>
              <input type="number" class="form-control" name="duracion_min"
                     min="5" max="480" step="5" placeholder="30">
              <div class="form-text">Sirve para calcular el espacio en la agenda.</div>
            </div>
            <div class="col-4 d-flex align-items-center">
              <div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1"
                         name="aplica_por_diente" id="procPorDiente" checked>
                  <label class="form-check-label" for="procPorDiente">
                    Se registra por diente
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1"
                         name="requiere_anestesia" id="procAnestesia">
                  <label class="form-check-label" for="procAnestesia">
                    Requiere anestesia
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1"
                         name="estatus" id="procActivo" checked>
                  <label class="form-check-label" for="procActivo">Activo</label>
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">Descripci&oacute;n</label>
              <textarea class="form-control" name="descripcion" rows="2"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">
            <i class="bi bi-check-lg me-1"></i> Guardar procedimiento
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
