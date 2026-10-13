<?php
/**
 * Configuracion > Tarifario.
 *
 * Los precios de lista del consultorio.
 *
 * Que diferencia hay con Procedimientos, que es la duda razonable que
 * provoca esta pantalla:
 *
 *   Tarifario       cuanto CUESTA cada cosa. Es la lista de precios.
 *                   Lleva la clave del SAT, que hace falta para
 *                   facturar.
 *
 *   Procedimientos  que se ESCRIBE en el plan de tratamiento. Es el
 *                   vocabulario de la doctora al redactar.
 *
 * Casi siempre coinciden uno a uno —"Resina simple" es las dos cosas—
 * y por eso las listas se parecen tanto. Se mantienen separadas porque
 * no siempre coinciden: hay procedimientos sin precio (una revision de
 * control que no se cobra) y conceptos de cobro que no son un
 * procedimiento (una radiografia que se mando a tomar fuera). Juntar
 * los dos obligaria a inventar precios cero y procedimientos falsos.
 *
 * Cada procedimiento puede APUNTAR a un renglon de aqui para heredar
 * su precio: cambiando el precio aqui, se actualiza alla.
 */
$categorias = Conexion::todos(
    'SELECT * FROM categoria_tratamiento ORDER BY nombre_categoria');
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Tarifario</h1>
                <p class="subtitulo-vista">
                    Precios de lista del consultorio
                </p>
            </div>
            <button class="btn btn-morado" id="btnNuevaTarifa">
                <i class="bi bi-plus-lg me-1"></i> Nuevo precio
            </button>
        </div>

        <?php /* La explicacion va EN la pantalla y no en un manual.

                 Las dos listas se parecen tanto que la pregunta "¿y
                 esto en que se diferencia de Procedimientos?" se la
                 hace cualquiera que entre. Contestarla aqui cuesta
                 cuatro renglones; no contestarla cuesta una llamada. */ ?>
        <div class="alert alert-light border d-flex gap-2 py-2 small">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span>
                Aqu&iacute; vive <b>cu&aacute;nto cuesta</b> cada cosa.
                En <a href="<?= URL_BASE ?>/index.php?modulo=configuracion&vista=procedimientos">Procedimientos
                dentales</a> vive <b>lo que se escribe en el plan</b>.
                Se parecen porque casi siempre son lo mismo, pero no
                siempre: hay procedimientos que no se cobran y cobros que
                no son un procedimiento. Un procedimiento puede
                <b>apuntar</b> a un precio de aqu&iacute; y heredarlo.
            </span>
        </div>

        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-4">
                        <label class="form-label" for="tarFiltro">Buscar</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="tarFiltro"
                                   placeholder="Nombre o clave" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="tarCategoria">Categor&iacute;a</label>
                        <select class="form-select" id="tarCategoria">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int) $c['id_categoria'] ?>">
                                    <?= e($c['nombre_categoria']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-2">
                        <label class="form-label" for="tarEstatus">Estatus</label>
                        <select class="form-select" id="tarEstatus">
                            <option value="1">Activos</option>
                            <option value="0">Inactivos</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-2 text-end">
                        <span class="text-secondary small" id="tarContador"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="tabla-con-menu">
                <table class="table tabla-sistema">
                    <thead><tr>
                        <th style="width:55px">#</th>
                        <th>Tratamiento</th>
                        <th style="width:170px">Categor&iacute;a</th>
                        <th style="width:110px">Precio</th>
                        <th style="width:85px">Duraci&oacute;n</th>
                        <th style="width:110px">Clave SAT</th>
                        <th style="width:120px">Se usa en</th>
                        <th style="width:95px">Estatus</th>
                        <th class="col-acciones"></th>
                    </tr></thead>
                    <tbody id="tablaTarifas">
                        <tr class="fila-vacia"><td colspan="9">
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
<div class="modal fade" id="modalTarifa" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-tag me-2"></i>
          <span id="tituloModalTarifa">Nuevo precio</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formTarifa" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="id_tratamiento" id="tarId">

          <div class="row g-3">
            <div class="col-8">
              <label class="form-label">Nombre <span class="req">*</span></label>
              <input type="text" class="form-control" name="nombre_tratamiento"
                     maxlength="120" required>
            </div>
            <div class="col-4">
              <label class="form-label">Clave</label>
              <input type="text" class="form-control" name="clave" maxlength="20"
                     placeholder="OP-01">
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
            <div class="col-3">
              <label class="form-label">Precio de lista <span class="req">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" name="precio_lista"
                       step="0.01" min="0" value="0.00" required>
              </div>
            </div>
            <div class="col-3">
              <label class="form-label">Duraci&oacute;n (min)</label>
              <input type="number" class="form-control" name="duracion_min"
                     min="5" max="480" step="5" placeholder="30">
            </div>

            <div class="col-6">
              <label class="form-label">Clave del SAT</label>
              <input type="text" class="form-control" name="clave_prod_serv_sat"
                     maxlength="10" placeholder="85121800">
              <?php /* Esta columna es la razon por la que el tarifario
                       existe aparte: el CFDI la exige por concepto, y un
                       procedimiento que no se cobra no tiene ninguna. */ ?>
              <div class="form-text">
                Clave de producto o servicio para la factura.
              </div>
            </div>
            <div class="col-6 d-flex align-items-end">
              <div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1"
                         name="aplica_por_diente" id="tarPorDiente" checked>
                  <label class="form-check-label" for="tarPorDiente">
                    Se cobra por diente
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1"
                         name="estatus" id="tarActivo" checked>
                  <label class="form-check-label" for="tarActivo">Activo</label>
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
            <i class="bi bi-check-lg me-1"></i> Guardar precio
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
