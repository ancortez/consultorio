<?php
/**
 * Configuracion > Colonias.
 *
 * El estado viene preseleccionado en NAYARIT y la lista solo aparece
 * cuando se elige municipio: son mas de 450 colonias entre Tepic y
 * Xalisco, y mostrarlas todas de golpe no le sirve a nadie.
 */
$estados   = Conexion::todos('SELECT * FROM estado ORDER BY nombre_estado');
$estadoDef = (int) parametro('estado_default', (string) ESTADO_DEFAULT);

$tiposAsenta = Conexion::todos(
    'SELECT id_tipo_asenta, nombre_tipo_asenta
       FROM tipo_asenta
      WHERE estatus = 1
      ORDER BY nombre_tipo_asenta'
);

// COLONIA es con diferencia el mas usado, asi que viene preseleccionado.
$tipoDefault = (int) Conexion::valor(
    "SELECT id_tipo_asenta FROM tipo_asenta WHERE nombre_tipo_asenta = 'COLONIA'"
);
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Colonias</h1>
                <p class="subtitulo-vista">
                    Cat&aacute;logo que alimenta la captura de domicilios
                </p>
            </div>
            <button class="btn btn-morado" id="btnNuevaColonia" disabled>
                <i class="bi bi-plus-lg me-1"></i> Nueva colonia
            </button>
        </div>

        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-3">
                        <label class="form-label" for="colEstado">Estado</label>
                        <select class="form-select" id="colEstado">
                            <?php foreach ($estados as $es): ?>
                                <option value="<?= (int) $es['id_estado'] ?>"
                                    <?= (int) $es['id_estado'] === $estadoDef ? 'selected' : '' ?>>
                                    <?= e($es['nombre_estado']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label" for="colMunicipio">
                            Municipio <span class="req">*</span>
                        </label>
                        <select class="form-select" id="colMunicipio">
                            <option value="">Cargando...</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="colFiltro">Buscar colonia</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="colFiltro"
                                   placeholder="Nombre o c&oacute;digo postal"
                                   autocomplete="off" disabled>
                        </div>
                    </div>
                    <div class="col-2 text-end">
                        <span class="text-secondary small" id="colContador"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="tabla-con-menu">
                <table class="table tabla-sistema">
                    <thead><tr>
                        <th style="width:60px">#</th>
                        <th>Colonia</th>
                        <th style="width:170px">Tipo de asentamiento</th>
                        <th style="width:120px">C&oacute;digo postal</th>
                        <th style="width:150px">Municipio</th>
                        <th style="width:130px">Domicilios</th>
                        <th class="col-acciones"></th>
                    </tr></thead>
                    <tbody id="tablaColonias">
                        <tr class="fila-vacia"><td colspan="7">
                            <i class="bi bi-geo-alt fs-3 d-block mb-2 opacity-50"></i>
                            Selecciona un municipio para ver sus colonias.
                        </td></tr>
                    </tbody>
                </table>
            </div>

            <!-- La paginacion se dibuja desde JavaScript -->
            <div class="paginacion d-none" id="colPaginacion">
                <span id="colRango"></span>
                <div class="controles" id="colControles"></div>
            </div>
        </div>
    </div>
</div>

<!-- ============ Modal de colonia ============ -->
<div class="modal fade" id="modalColonia" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title texto-morado">
          <i class="bi bi-geo-alt me-2"></i>
          <span id="tituloModalColonia">Nueva colonia</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formColonia" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="id_colonia" id="coloniaId">
          <input type="hidden" name="id_municipio" id="coloniaMunicipio">

          <div class="alert alert-light border py-2 small d-flex gap-2" id="coloniaUbicacion">
            <i class="bi bi-pin-map" style="color:var(--morado-600)"></i>
            <span></span>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nombre de la colonia <span class="req">*</span></label>
              <input type="text" class="form-control" name="nombre_colonia"
                     maxlength="120" required>
            </div>
            <div class="col-7">
              <label class="form-label">
                Tipo de asentamiento <span class="req">*</span>
              </label>
              <select class="form-select" name="id_tipo_asenta"
                      id="coloniaTipo" data-default="<?= $tipoDefault ?>" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($tiposAsenta as $t): ?>
                  <option value="<?= (int) $t['id_tipo_asenta'] ?>"
                    <?= (int) $t['id_tipo_asenta'] === $tipoDefault ? 'selected' : '' ?>>
                    <?= e($t['nombre_tipo_asenta']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-5">
              <label class="form-label">C&oacute;digo postal <span class="req">*</span></label>
              <input type="text" class="form-control" name="codigo_postal"
                     maxlength="5" inputmode="numeric" required placeholder="63000">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">
            <i class="bi bi-check-lg me-1"></i> Guardar colonia
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
