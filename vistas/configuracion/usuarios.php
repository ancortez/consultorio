<?php
/**
 * Configuracion > Usuarios y roles.
 *
 * Dos pestanias: la lista de usuarios y la matriz de permisos por rol.
 * Los permisos se administran desde aqui, no desde el codigo: cuando la
 * clienta decida que recepcion tambien puede cancelar pagos, es una
 * casilla, no una version nueva del sistema.
 */
$roles = Conexion::todos('SELECT * FROM rol WHERE estatus = 1 ORDER BY id_rol');
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Usuarios y roles</h1>
                <p class="subtitulo-vista">Qui&eacute;n entra al sistema y qu&eacute; puede hacer</p>
            </div>
            <button class="btn btn-morado" id="btnNuevoUsuario">
                <i class="bi bi-person-plus me-1"></i> Nuevo usuario
            </button>
        </div>

        <div class="card">
            <div class="card-body pb-0">
                <ul class="nav nav-tabs nav-tabs-morado" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab"
                                data-bs-target="#tab-usuarios" type="button">
                            <i class="bi bi-people me-1"></i> Usuarios
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab"
                                data-bs-target="#tab-permisos" type="button">
                            <i class="bi bi-shield-check me-1"></i> Permisos por rol
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
            <div class="tab-content">

                <!-- ---------- Usuarios ---------- -->
                <div class="tab-pane fade show active" id="tab-usuarios">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-5">
                            <label class="form-label" for="filtroUsuario">Buscar</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="filtroUsuario"
                                       placeholder="Nombre o usuario" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-3">
                            <label class="form-label" for="filtroEstatusUsuario">Estatus</label>
                            <select class="form-select" id="filtroEstatusUsuario">
                                <option value="1">Activos</option>
                                <option value="0">Inactivos</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                    </div>

                    <div class="border rounded tabla-con-menu">
                        <table class="table tabla-sistema mb-0">
                            <thead><tr>
                                <th>Usuario</th>
                                <th style="width:150px">Rol</th>
                                <th style="width:220px">Correo</th>
                                <th style="width:160px">&Uacute;ltimo acceso</th>
                                <th style="width:120px">Estatus</th>
                                <th class="col-acciones"></th>
                            </tr></thead>
                            <tbody id="tablaUsuarios">
                                <tr class="fila-vacia"><td colspan="6">
                                    <div class="spinner-border spinner-border-sm me-2"
                                         style="color:var(--morado-600)"></div>
                                    Cargando...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ---------- Permisos ---------- -->
                <div class="tab-pane fade" id="tab-permisos">
                    <div class="row g-3">
                        <div class="col-4">
                            <p class="form-label">Roles</p>
                            <div class="border rounded" id="listaRoles">
                                <div class="cargando">Cargando...</div>
                            </div>
                            <button class="btn btn-sm btn-outline-morado w-100 mt-2" id="btnNuevoRol">
                                <i class="bi bi-plus-lg me-1"></i> Nuevo rol
                            </button>
                        </div>

                        <div class="col-8">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <p class="form-label mb-0" id="tituloPermisos">
                                    Selecciona un rol
                                </p>
                                <button class="btn btn-sm btn-morado d-none" id="btnGuardarPermisos">
                                    <i class="bi bi-check-lg me-1"></i> Guardar permisos
                                </button>
                            </div>
                            <div id="matrizPermisos">
                                <div class="card">
                                    <div class="card-body text-center py-5 text-secondary">
                                        <i class="bi bi-shield-lock fs-1 d-block mb-2 opacity-25"></i>
                                        Elige un rol de la izquierda para ver y ajustar sus permisos.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ Modal de usuario ============ -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title texto-morado">
          <i class="bi bi-person-badge me-2"></i>
          <span id="tituloModalUsuario">Nuevo usuario</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formUsuario" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="id_usuario" id="usuarioId">

          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Nombre completo <span class="req">*</span></label>
              <input type="text" class="form-control" name="nombre_completo"
                     maxlength="120" required>
            </div>
            <div class="col-6">
              <label class="form-label">Rol <span class="req">*</span></label>
              <select class="form-select" name="id_rol" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int) $r['id_rol'] ?>"><?= e($r['nombre_rol']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6">
              <label class="form-label">Nombre de usuario <span class="req">*</span></label>
              <input type="text" class="form-control sin-mayusculas" name="nombre_usuario"
                     maxlength="40" required placeholder="ejemplo: dra.lopez">
              <div class="form-text">Letras min&uacute;sculas, n&uacute;meros, punto o gui&oacute;n.</div>
            </div>
            <div class="col-6">
              <label class="form-label">Correo electr&oacute;nico</label>
              <input type="email" class="form-control sin-mayusculas" name="correo" maxlength="120">
            </div>

            <div class="col-6">
              <label class="form-label">C&eacute;dula profesional</label>
              <input type="text" class="form-control" name="cedula_prof" maxlength="30">
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check me-4">
                <input class="form-check-input" type="checkbox" value="1"
                       name="es_dentista" id="usuarioEsDentista">
                <label class="form-check-label" for="usuarioEsDentista">
                  Atiende pacientes
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1"
                       name="estatus" id="usuarioActivo" checked>
                <label class="form-check-label" for="usuarioActivo">Activo</label>
              </div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12">
              <div class="alert alert-light border py-2 small mb-0 d-flex gap-2"
                   id="avisoContrasena">
                <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
                <span>Al guardar, el usuario deber&aacute; cambiar su contrase&ntilde;a
                      la primera vez que entre.</span>
              </div>
            </div>

            <div class="col-6">
              <label class="form-label">
                Contrase&ntilde;a <span class="req" id="reqPass">*</span>
              </label>
              <input type="password" class="form-control" name="contrasena" maxlength="72">
              <div class="form-text">M&iacute;nimo 8 caracteres, con letras y n&uacute;meros.</div>
            </div>
            <div class="col-6">
              <label class="form-label">Confirmar contrase&ntilde;a</label>
              <input type="password" class="form-control" name="contrasena_confirmar" maxlength="72">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">
            <i class="bi bi-check-lg me-1"></i> Guardar usuario
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Modal de rol ============ -->
<div class="modal fade" id="modalRol" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title texto-morado">
          <i class="bi bi-shield-plus me-2"></i>
          <span id="tituloModalRol">Nuevo rol</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formRol" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="id_rol" id="rolId">
          <div class="mb-3">
            <label class="form-label">Nombre del rol <span class="req">*</span></label>
            <input type="text" class="form-control" name="nombre_rol" maxlength="40" required>
          </div>
          <div>
            <label class="form-label">Descripci&oacute;n</label>
            <textarea class="form-control" name="descripcion" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Guardar rol</button>
        </div>
      </form>
    </div>
  </div>
</div>
