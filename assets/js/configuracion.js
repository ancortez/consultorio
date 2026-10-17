/* =====================================================================
   Modulo Configuracion: usuarios, roles, permisos y colonias
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  moduloUsuarios();
  moduloPermisos();
  moduloColonias();
  moduloProcedimientos();
  moduloTarifas();
  moduloConsultorio();
});

/* ---------------------------------------------------------------------
   Usuarios
   --------------------------------------------------------------------- */
function moduloUsuarios() {
  const tabla = document.getElementById('tablaUsuarios');
  if (!tabla) return;

  const filtro  = document.getElementById('filtroUsuario');
  const estatus = document.getElementById('filtroEstatusUsuario');
  const modal   = document.getElementById('modalUsuario');
  const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
  const form    = document.getElementById('formUsuario');

  async function cargar() {
    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="6">
        <div class="spinner-border spinner-border-sm me-2"
             style="color:var(--morado-600)"></div>Cargando...</td></tr>`;
    try {
      const r = await App.api('configuracion/usuarios_listar.php', {
        q: filtro.value.trim(), estatus: estatus.value,
      });
      pintar(r.datos);
    } catch (e) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="6">
          No se pudo cargar la lista.</td></tr>`;
    }
  }

  function pintar(lista) {
    if (!lista.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="6">
          Sin usuarios con ese criterio.</td></tr>`;
      return;
    }

    tabla.innerHTML = lista.map((u) => {
      const bloqueado = Number(u.bloqueado) === 1;
      const activo    = Number(u.estatus) === 1;

      return `
      <tr data-usuario='${App.esc(JSON.stringify(u))}'>
        <td>
          <div class="fw-semibold">${App.esc(u.nombre_completo)}</div>
          <div class="text-secondary sin-mayusculas" style="font-size:11px">
            ${App.esc(u.nombre_usuario)}
            ${Number(u.es_dentista) === 1
              ? '<span class="badge badge-suave ms-1">Atiende pacientes</span>' : ''}
          </div>
        </td>
        <td><span class="badge badge-suave">${App.esc(u.nombre_rol)}</span></td>
        <td class="text-secondary sin-mayusculas" style="font-size:12.5px">
          ${u.correo ? App.esc(u.correo) : '-'}</td>
        <td class="text-secondary" style="font-size:12.5px">
          ${u.ultimo_acceso ? App.esc(formatoFecha(u.ultimo_acceso)) : 'Nunca'}</td>
        <td>
          ${activo
            ? '<span class="chip-estado" style="background:#e6f5ee;color:var(--exito)">ACTIVO</span>'
            : '<span class="chip-estado" style="background:#eee;color:#777">INACTIVO</span>'}
          ${bloqueado
            ? '<div><span class="chip-estado chip-alerta-clinica mt-1">BLOQUEADO</span></div>' : ''}
        </td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#" data-accion="editar-usuario">
                <i class="bi bi-pencil me-2"></i>Modificar datos</a></li>
              <li><a class="dropdown-item" href="#" data-accion="pass-usuario">
                <i class="bi bi-key me-2"></i>Cambiar contraseña</a></li>
              ${bloqueado ? `
              <li><a class="dropdown-item" href="#" data-accion="desbloquear-usuario">
                <i class="bi bi-unlock me-2"></i>Desbloquear acceso</a></li>` : ''}
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item ${activo ? 'text-danger' : ''}" href="#"
                     data-accion="estatus-usuario" data-estatus="${activo ? 0 : 1}">
                <i class="bi bi-${activo ? 'person-dash' : 'person-check'} me-2"></i>
                ${activo ? 'Desactivar' : 'Activar'}</a></li>
            </ul>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  const formatoFecha = (f) => {
    const d = new Date(f.replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? f
      : d.toLocaleString('es-MX', { day: '2-digit', month: '2-digit',
                                    year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  /* ---------- Abrir en blanco ---------- */
  document.getElementById('btnNuevoUsuario').addEventListener('click', () => {
    form.reset();
    document.getElementById('usuarioId').value = '';
    document.getElementById('tituloModalUsuario').textContent = 'Nuevo usuario';
    document.getElementById('reqPass').classList.remove('d-none');
    document.getElementById('usuarioActivo').checked = true;
    document.getElementById('avisoContrasena').querySelector('span').textContent =
      'Al guardar, el usuario deberá cambiar su contraseña la primera vez que entre.';
    bsModal.show();
  });

  /* ---------- Acciones por renglon ---------- */
  tabla.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const fila = opcion.closest('tr');
    const u = JSON.parse(fila.dataset.usuario);

    if (opcion.dataset.accion === 'editar-usuario') {
      form.reset();
      document.getElementById('usuarioId').value = u.id_usuario;
      document.getElementById('tituloModalUsuario').textContent = 'Modificar usuario';
      document.getElementById('reqPass').classList.add('d-none');
      form.querySelector('[name="nombre_completo"]').value = u.nombre_completo;
      form.querySelector('[name="nombre_usuario"]').value  = u.nombre_usuario;
      form.querySelector('[name="correo"]').value          = u.correo ?? '';
      form.querySelector('[name="id_rol"]').value          = u.id_rol;
      form.querySelector('[name="cedula_prof"]').value     = u.cedula_prof ?? '';
      document.getElementById('usuarioEsDentista').checked = Number(u.es_dentista) === 1;
      document.getElementById('usuarioActivo').checked     = Number(u.estatus) === 1;
      document.getElementById('avisoContrasena').querySelector('span').textContent =
        'Deja la contraseña vacía para conservar la actual.';
      bsModal.show();
    }

    if (opcion.dataset.accion === 'pass-usuario') {
      const { value: datos } = await Swal.fire({
        title: 'Nueva contraseña',
        html: `<p class="text-secondary small mb-3">${App.esc(u.nombre_completo)}</p>
               <input id="sw1" type="password" class="swal2-input" placeholder="Contraseña">
               <input id="sw2" type="password" class="swal2-input" placeholder="Confirmar">`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Cambiar',
        confirmButtonColor: '#7b2d8e',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
          const a = document.getElementById('sw1').value;
          const b = document.getElementById('sw2').value;
          if (a.length < 8) { Swal.showValidationMessage('Mínimo 8 caracteres.'); return false; }
          if (!/[A-Za-z]/.test(a) || !/\d/.test(a)) {
            Swal.showValidationMessage('Debe combinar letras y números.'); return false;
          }
          if (a !== b) { Swal.showValidationMessage('No coinciden.'); return false; }
          return { a, b };
        },
      });
      if (!datos) return;

      try {
        await App.api('configuracion/usuario_guardar.php', {
          id_usuario: u.id_usuario,
          nombre_usuario: u.nombre_usuario,
          nombre_completo: u.nombre_completo,
          correo: u.correo ?? '',
          id_rol: u.id_rol,
          cedula_prof: u.cedula_prof ?? '',
          es_dentista: Number(u.es_dentista) === 1 ? 1 : '',
          estatus: u.estatus,
          contrasena: datos.a,
          contrasena_confirmar: datos.b,
        });
        App.alerta.toast('Contraseña actualizada');
        cargar();
      } catch (e) { /* ya se mostro */ }
    }

    if (opcion.dataset.accion === 'desbloquear-usuario') {
      await App.api('configuracion/usuario_desbloquear.php', { id_usuario: u.id_usuario });
      App.alerta.toast('Usuario desbloqueado');
      cargar();
    }

    if (opcion.dataset.accion === 'estatus-usuario') {
      const nuevo = opcion.dataset.estatus;
      const ok = await App.alerta.confirmar(
        Number(nuevo) === 1 ? '¿Activar este usuario?' : '¿Desactivar este usuario?',
        Number(nuevo) === 1
          ? 'Podrá volver a entrar al sistema.'
          : 'Dejará de poder entrar, pero se conserva todo lo que registró.',
        'Sí, continuar');
      if (!ok) return;

      try {
        await App.api('configuracion/usuario_estatus.php', {
          id_usuario: u.id_usuario, estatus: nuevo,
        });
        App.alerta.toast('Estatus actualizado');
        cargar();
      } catch (e) { /* ya se mostro */ }
    }
  });

  /* ---------- Guardar ---------- */
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const datos = App.formADatos(form);
    // Las casillas sin marcar no viajan en el FormData: se explicitan.
    datos.es_dentista = document.getElementById('usuarioEsDentista').checked ? 1 : '';
    datos.estatus     = document.getElementById('usuarioActivo').checked ? 1 : 0;

    try {
      await App.api('configuracion/usuario_guardar.php', datos);
      bsModal.hide();
      App.alerta.toast('Usuario guardado');
      cargar();
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });

  filtro.addEventListener('input', App.debounce(cargar, 350));
  estatus.addEventListener('change', cargar);
  cargar();
}

/* ---------------------------------------------------------------------
   Roles y permisos
   --------------------------------------------------------------------- */
function moduloPermisos() {
  const lista = document.getElementById('listaRoles');
  if (!lista) return;

  const matriz   = document.getElementById('matrizPermisos');
  const titulo   = document.getElementById('tituloPermisos');
  const btnGuard = document.getElementById('btnGuardarPermisos');
  const modalRol = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRol'));
  const formRol  = document.getElementById('formRol');

  let rolActual = null;

  async function cargarRoles() {
    const r = await App.api('configuracion/roles_listar.php');
    lista.innerHTML = r.datos.map((rol) => `
      <div class="opcion ${rolActual === rol.id_rol ? 'seleccionada' : ''}"
           data-rol="${rol.id_rol}" style="cursor:pointer">
        <div>
          <div class="fw-semibold">${App.esc(rol.nombre_rol)}</div>
          <div class="text-secondary" style="font-size:11px">
            ${rol.usuarios} usuario(s) · ${rol.permisos} permiso(s)
          </div>
        </div>
        <i class="bi bi-chevron-right"></i>
      </div>`).join('');
    lista.classList.add('lista-seleccion');
  }

  async function cargarPermisos(idRol) {
    rolActual = Number(idRol);
    matriz.innerHTML = `<div class="cargando">
        <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
        Cargando permisos...</div>`;

    const r = await App.api('configuracion/permisos_listar.php', { id_rol: idRol });
    const { rol, es_admin: esAdmin, por_modulo: porModulo } = r.datos;

    titulo.textContent = `Permisos de ${rol.nombre_rol}`;
    btnGuard.classList.toggle('d-none', esAdmin);

    if (esAdmin) {
      matriz.innerHTML = `
        <div class="card"><div class="card-body text-center py-5">
          <i class="bi bi-shield-fill-check fs-1 d-block mb-2" style="color:var(--morado-500)"></i>
          <p class="fw-semibold mb-1" style="color:var(--morado-800)">Acceso total</p>
          <p class="text-secondary small mb-0" style="max-width:420px;margin:0 auto">
            El rol Administrador tiene todos los permisos por definición, incluidas
            las citas y la configuración. No se edita para que nadie pueda dejar
            al sistema sin administrador por accidente.
          </p>
        </div></div>`;
      cargarRoles();
      return;
    }

    matriz.innerHTML = Object.entries(porModulo).map(([modulo, datos]) => `
      <div class="card mb-2">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
          <span><i class="bi ${App.esc(datos.icono || 'bi-folder')} me-2"></i>${App.esc(modulo)}</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" data-modulo="${App.esc(modulo)}"
                   ${datos.permisos.every((p) => Number(p.asignado) === 1) ? 'checked' : ''}>
          </div>
        </div>
        <div class="card-body py-2">
          <div class="row g-2">
            ${datos.permisos.map((p) => `
              <div class="col-6">
                <div class="form-check">
                  <input class="form-check-input permiso" type="checkbox"
                         value="${p.id_permiso}" id="perm${p.id_permiso}"
                         data-grupo="${App.esc(modulo)}"
                         ${Number(p.asignado) === 1 ? 'checked' : ''}>
                  <label class="form-check-label" for="perm${p.id_permiso}">
                    ${App.esc(p.nombre)}
                    <div class="text-secondary sin-mayusculas" style="font-size:10.5px">
                      ${App.esc(p.clave)}</div>
                  </label>
                </div>
              </div>`).join('')}
          </div>
        </div>
      </div>`).join('');

    cargarRoles();
  }

  lista.addEventListener('click', (ev) => {
    const opcion = ev.target.closest('[data-rol]');
    if (opcion) cargarPermisos(opcion.dataset.rol);
  });

  // Interruptor de modulo: marca o desmarca todo el bloque
  matriz.addEventListener('change', (ev) => {
    const maestro = ev.target.closest('[data-modulo]');
    if (!maestro) return;
    matriz.querySelectorAll(`.permiso[data-grupo="${maestro.dataset.modulo}"]`)
      .forEach((c) => { c.checked = maestro.checked; });
  });

  btnGuard.addEventListener('click', async () => {
    const ids = [...matriz.querySelectorAll('.permiso:checked')].map((c) => c.value);
    try {
      await App.api('configuracion/permisos_guardar.php', {
        id_rol: rolActual, permisos: ids.join(','),
      });
      App.alerta.toast('Permisos actualizados');
      cargarRoles();
    } catch (e) { /* ya se mostro */ }
  });

  document.getElementById('btnNuevoRol').addEventListener('click', () => {
    formRol.reset();
    document.getElementById('rolId').value = '';
    document.getElementById('tituloModalRol').textContent = 'Nuevo rol';
    modalRol.show();
  });

  formRol.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await App.api('configuracion/rol_guardar.php', App.formADatos(formRol));
      modalRol.hide();
      App.alerta.toast('Rol guardado');
      cargarRoles();
    } catch (e) {
      App.marcarErrores(formRol, e.errores);
    }
  });

  cargarRoles();
}

/* ---------------------------------------------------------------------
   Colonias
   --------------------------------------------------------------------- */
function moduloColonias() {
  const tabla = document.getElementById('tablaColonias');
  if (!tabla) return;

  const estado    = document.getElementById('colEstado');
  const municipio = document.getElementById('colMunicipio');
  const filtro    = document.getElementById('colFiltro');
  const contador  = document.getElementById('colContador');
  const btnNueva  = document.getElementById('btnNuevaColonia');
  const modal     = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalColonia'));
  const form      = document.getElementById('formColonia');
  const barra     = document.getElementById('colPaginacion');
  const rango     = document.getElementById('colRango');
  const controles = document.getElementById('colControles');

  // Tepic solo tiene casi 400 colonias: se traen todas de una vez y se
  // pagina en el navegador. Asi cambiar de pagina es instantaneo y no
  // hay que ir al servidor por cada una.
  const POR_PAGINA = 50;
  let todas   = [];
  let pagina  = 1;

  async function cargarMunicipios() {
    municipio.innerHTML = '<option value="">Cargando...</option>';
    const r = await App.api('geo/municipios.php', { id_estado: estado.value });
    municipio.innerHTML = '<option value="">Selecciona municipio</option>' +
      r.datos.map((m) => `<option value="${m.id_municipio}">
        ${App.esc(m.nombre_municipio)}</option>`).join('');
    vaciar();
  }

  function vaciar() {
    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="7">
        <i class="bi bi-geo-alt fs-3 d-block mb-2 opacity-50"></i>
        Selecciona un municipio para ver sus colonias.</td></tr>`;
    contador.textContent = '';
    filtro.disabled  = true;
    btnNueva.disabled = true;
    barra.classList.add('d-none');
    todas = [];
  }

  async function cargar() {
    if (!municipio.value) { vaciar(); return; }

    filtro.disabled  = false;
    btnNueva.disabled = false;

    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="7">
        <div class="spinner-border spinner-border-sm me-2"
             style="color:var(--morado-600)"></div>Cargando...</td></tr>`;

    const r = await App.api('configuracion/colonias_listar.php', {
      id_municipio: municipio.value, q: filtro.value.trim(),
    });

    todas  = r.datos;
    pagina = 1;
    pintar();
  }

  function pintar() {
    contador.textContent = todas.length
      ? `${todas.length} colonia${todas.length === 1 ? '' : 's'}` : '';

    if (!todas.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="7">
          Sin colonias con ese criterio.</td></tr>`;
      barra.classList.add('d-none');
      return;
    }

    const paginas = Math.ceil(todas.length / POR_PAGINA);
    if (pagina > paginas) pagina = paginas;

    const desde = (pagina - 1) * POR_PAGINA;
    const hasta = Math.min(desde + POR_PAGINA, todas.length);

    // El consecutivo sigue la paginacion: en la pagina 2 empieza en 51,
    // no vuelve a 1. Asi el numero identifica al renglon dentro de todo
    // el resultado y no solo dentro de la pagina que se ve.
    tabla.innerHTML = todas.slice(desde, hasta).map((c, i) => `
      <tr data-colonia='${App.esc(JSON.stringify(c))}'>
        <td class="text-secondary">${desde + i + 1}</td>
        <td class="fw-semibold">${App.esc(c.nombre_colonia)}</td>
        <td class="text-secondary">${App.esc(c.tipo_asenta ?? '-')}</td>
        <td><span class="badge badge-suave">${App.esc(c.codigo_postal)}</span></td>
        <td>${App.esc(c.nombre_municipio ?? '-')}</td>
        <td class="text-secondary">
          ${Number(c.domicilios) > 0
            ? `${c.domicilios} registrado(s)`
            : '<span class="opacity-50">Ninguno</span>'}
        </td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#" data-accion="editar-colonia">
                <i class="bi bi-pencil me-2"></i>Modificar</a></li>
              <li><a class="dropdown-item text-danger" href="#" data-accion="eliminar-colonia">
                <i class="bi bi-trash me-2"></i>Eliminar</a></li>
            </ul>
          </div>
        </td>
      </tr>`).join('');

    rango.textContent =
      `Mostrando ${desde + 1} a ${hasta} de ${todas.length}`;
    barra.classList.remove('d-none');
    pintarControles(paginas);
  }

  /**
   * Botones de pagina. Con muchas paginas no se pintan todas: se
   * muestran la primera, la ultima, la actual y sus vecinas, y el resto
   * se resume con puntos suspensivos.
   */
  function pintarControles(paginas) {
    if (paginas <= 1) {
      controles.innerHTML = '';
      return;
    }

    const numeros = [];
    for (let i = 1; i <= paginas; i++) {
      if (i === 1 || i === paginas || Math.abs(i - pagina) <= 1) {
        numeros.push(i);
      } else if (numeros[numeros.length - 1] !== '...') {
        numeros.push('...');
      }
    }

    controles.innerHTML = `
      <button type="button" class="pagina" data-ir="${pagina - 1}"
              ${pagina === 1 ? 'disabled' : ''} title="Anterior">
        <i class="bi bi-chevron-left"></i>
      </button>
      ${numeros.map((n) => n === '...'
        ? '<span class="puntos">…</span>'
        : `<button type="button" class="pagina ${n === pagina ? 'activa' : ''}"
                   data-ir="${n}">${n}</button>`).join('')}
      <button type="button" class="pagina" data-ir="${pagina + 1}"
              ${pagina === paginas ? 'disabled' : ''} title="Siguiente">
        <i class="bi bi-chevron-right"></i>
      </button>`;
  }

  controles.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-ir]');
    if (!btn || btn.disabled) return;
    pagina = Number(btn.dataset.ir);
    pintar();
    tabla.closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  function textoUbicacion() {
    const est = estado.selectedOptions[0]?.textContent.trim() ?? '';
    const mun = municipio.selectedOptions[0]?.textContent.trim() ?? '';
    return `${mun}, ${est}`;
  }

  btnNueva.addEventListener('click', () => {
    form.reset();
    document.getElementById('coloniaId').value = '';
    document.getElementById('coloniaMunicipio').value = municipio.value;
    document.getElementById('tituloModalColonia').textContent = 'Nueva colonia';
    document.querySelector('#coloniaUbicacion span').textContent = textoUbicacion();

    const combo = document.getElementById('coloniaTipo');
    combo.value = combo.dataset.default || '';
    modal.show();
  });

  tabla.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const c = JSON.parse(opcion.closest('tr').dataset.colonia);

    if (opcion.dataset.accion === 'editar-colonia') {
      form.reset();
      document.getElementById('coloniaId').value = c.id_colonia;
      document.getElementById('coloniaMunicipio').value = c.id_municipio;
      document.getElementById('tituloModalColonia').textContent = 'Modificar colonia';
      document.querySelector('#coloniaUbicacion span').textContent = textoUbicacion();
      form.querySelector('[name="nombre_colonia"]').value = c.nombre_colonia;
      form.querySelector('[name="codigo_postal"]').value  = c.codigo_postal;

      const combo = document.getElementById('coloniaTipo');
      combo.value = c.id_tipo_asenta ?? combo.dataset.default ?? '';
      modal.show();
    }

    if (opcion.dataset.accion === 'eliminar-colonia') {
      if (Number(c.domicilios) > 0) {
        App.alerta.error(
          'No se puede eliminar',
          `Hay ${c.domicilios} domicilio(s) registrados en esta colonia. ` +
          'Si el nombre está mal, corrígelo en lugar de borrarla.');
        return;
      }
      const ok = await App.alerta.confirmar(
        '¿Eliminar esta colonia?',
        `${c.nombre_colonia} · CP ${c.codigo_postal}`,
        'Sí, eliminar');
      if (!ok) return;

      try {
        await App.api('configuracion/colonia_eliminar.php', { id_colonia: c.id_colonia });
        App.alerta.toast('Colonia eliminada');
        cargar();
      } catch (e) { /* ya se mostro */ }
    }
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await App.api('configuracion/colonia_guardar.php', App.formADatos(form));
      modal.hide();
      App.alerta.toast('Colonia guardada');
      cargar();
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });

  estado.addEventListener('change', cargarMunicipios);
  municipio.addEventListener('change', cargar);
  filtro.addEventListener('input', App.debounce(cargar, 350));

  cargarMunicipios();
}


/* ---------------------------------------------------------------------
   Procedimientos dentales
   --------------------------------------------------------------------- */
function moduloProcedimientos() {
  const tabla = document.getElementById('tablaProcedimientos');
  if (!tabla) return;

  const filtro    = document.getElementById('procFiltro');
  const categoria = document.getElementById('procCategoria');
  const estatus   = document.getElementById('procEstatus');
  const contador  = document.getElementById('procContador');
  const modal     = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalProcedimiento'));
  const form      = document.getElementById('formProcedimiento');

  async function cargar() {
    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="8">
        <div class="spinner-border spinner-border-sm me-2"
             style="color:var(--morado-600)"></div>Cargando...</td></tr>`;

    const r = await App.api('configuracion/procedimientos_listar.php', {
      q: filtro.value.trim(),
      id_categoria: categoria.value,
      estatus: estatus.value,
    });

    contador.textContent = r.datos.length
      ? `${r.datos.length} procedimiento${r.datos.length === 1 ? '' : 's'}` : '';

    if (!r.datos.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="8">
          Sin procedimientos con ese criterio.</td></tr>`;
      return;
    }

    tabla.innerHTML = r.datos.map((p, i) => {
      const activo = Number(p.estatus) === 1;
      return `
      <tr data-proc='${App.esc(JSON.stringify(p))}'>
        <td class="text-secondary">${i + 1}</td>
        <td>
          <div class="fw-semibold">${App.esc(p.nombre_procedimiento)}</div>
          ${p.clave ? `<div class="text-secondary" style="font-size:11px">
                         ${App.esc(p.clave)}</div>` : ''}
          ${Number(p.usos) > 0 ? `<div class="text-secondary" style="font-size:11px">
                         Usado en ${p.usos} plan(es)</div>` : ''}
        </td>
        <td class="text-secondary">${App.esc(p.nombre_categoria ?? '-')}</td>
        <td class="fw-semibold">${App.esc(App.money(p.costo_sugerido))}</td>
        <td class="text-secondary">${p.duracion_min ? p.duracion_min + ' min' : '-'}</td>
        <td>
          ${Number(p.aplica_por_diente) === 1
            ? '<span class="chip-estado badge-suave">POR DIENTE</span> ' : ''}
          ${Number(p.requiere_anestesia) === 1
            ? '<span class="chip-estado chip-alerta-clinica">ANESTESIA</span>' : ''}
        </td>
        <td>
          ${activo
            ? '<span class="chip-estado" style="background:#e6f5ee;color:var(--exito)">ACTIVO</span>'
            : '<span class="chip-estado" style="background:#eee;color:#777">INACTIVO</span>'}
        </td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#" data-accion="editar-proc">
                <i class="bi bi-pencil me-2"></i>Modificar</a></li>
              <li><a class="dropdown-item ${activo ? 'text-danger' : ''}" href="#"
                     data-accion="estatus-proc" data-estatus="${activo ? 0 : 1}">
                <i class="bi bi-${activo ? 'slash-circle' : 'check-circle'} me-2"></i>
                ${activo ? 'Desactivar' : 'Activar'}</a></li>
            </ul>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  /* Al ligar un tratamiento, el precio se copia solo: tener dos precios
     distintos para lo mismo es la forma mas rapida de cobrar mal. */
  document.getElementById('procTratamiento').addEventListener('change', (ev) => {
    const precio = ev.target.selectedOptions[0]?.dataset.precio;
    if (precio) document.getElementById('procCosto').value = Number(precio).toFixed(2);
  });

  document.getElementById('btnNuevoProcedimiento').addEventListener('click', () => {
    form.reset();
    document.getElementById('procId').value = '';
    document.getElementById('tituloModalProc').textContent = 'Nuevo procedimiento';
    document.getElementById('procPorDiente').checked = true;
    document.getElementById('procActivo').checked = true;
    document.getElementById('procCosto').value = '0.00';
    modal.show();
  });

  tabla.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const p = JSON.parse(opcion.closest('tr').dataset.proc);

    if (opcion.dataset.accion === 'editar-proc') {
      form.reset();
      document.getElementById('procId').value = p.id_procedimiento;
      document.getElementById('tituloModalProc').textContent = 'Modificar procedimiento';
      form.querySelector('[name="nombre_procedimiento"]').value = p.nombre_procedimiento;
      form.querySelector('[name="clave"]').value        = p.clave ?? '';
      form.querySelector('[name="id_categoria"]').value = p.id_categoria ?? '';
      form.querySelector('[name="id_tratamiento"]').value = p.id_tratamiento ?? '';
      form.querySelector('[name="costo_sugerido"]').value = Number(p.costo_sugerido).toFixed(2);
      form.querySelector('[name="duracion_min"]').value = p.duracion_min ?? '';
      form.querySelector('[name="descripcion"]').value  = p.descripcion ?? '';
      document.getElementById('procPorDiente').checked = Number(p.aplica_por_diente) === 1;
      document.getElementById('procAnestesia').checked = Number(p.requiere_anestesia) === 1;
      document.getElementById('procActivo').checked    = Number(p.estatus) === 1;
      modal.show();
    }

    if (opcion.dataset.accion === 'estatus-proc') {
      const nuevo = opcion.dataset.estatus;
      if (Number(nuevo) === 0 && Number(p.usos) > 0) {
        const ok = await App.alerta.confirmar(
          '¿Desactivar este procedimiento?',
          `Está usado en ${p.usos} plan(es). Los planes viejos lo conservan, `
          + 'pero ya no se podrá elegir en planes nuevos.',
          'Sí, desactivar');
        if (!ok) return;
      }

      await App.api('configuracion/procedimiento_estatus.php', {
        id_procedimiento: p.id_procedimiento, estatus: nuevo,
      });
      App.alerta.toast('Estatus actualizado');
      cargar();
    }
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const datos = App.formADatos(form);
    datos.aplica_por_diente  = document.getElementById('procPorDiente').checked ? 1 : '';
    datos.requiere_anestesia = document.getElementById('procAnestesia').checked ? 1 : '';
    datos.estatus            = document.getElementById('procActivo').checked ? 1 : 0;

    try {
      await App.api('configuracion/procedimiento_guardar.php', datos);
      modal.hide();
      App.alerta.toast('Procedimiento guardado');
      cargar();
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });

  filtro.addEventListener('input', App.debounce(cargar, 350));
  categoria.addEventListener('change', cargar);
  estatus.addEventListener('change', cargar);
  cargar();
}

/* ---------------------------------------------------------------------
   Tarifario
   ---------------------------------------------------------------------
   Casi igual que Procedimientos, y a proposito: son dos catalogos
   hermanos y quien sabe usar uno sabe usar el otro sin volver a
   aprender nada. Lo que cambia es lo que cambia de verdad: aqui hay
   clave del SAT y una columna que dice cuantos procedimientos cuelgan
   de cada precio.
   --------------------------------------------------------------------- */
function moduloTarifas() {
  const tabla = document.getElementById('tablaTarifas');
  if (!tabla) return;

  const filtro    = document.getElementById('tarFiltro');
  const categoria = document.getElementById('tarCategoria');
  const estatus   = document.getElementById('tarEstatus');
  const contador  = document.getElementById('tarContador');
  const modal     = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalTarifa'));
  const form      = document.getElementById('formTarifa');

  async function cargar() {
    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="9">
        <div class="spinner-border spinner-border-sm me-2"
             style="color:var(--morado-600)"></div>Cargando...</td></tr>`;

    const r = await App.api('configuracion/tarifas_listar.php', {
      q: filtro.value.trim(),
      id_categoria: categoria.value,
      estatus: estatus.value,
    });

    contador.textContent = r.datos.length
      ? `${r.datos.length} precio${r.datos.length === 1 ? '' : 's'}` : '';

    if (!r.datos.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="9">
          Sin precios con ese criterio.</td></tr>`;
      return;
    }

    tabla.innerHTML = r.datos.map((t, i) => {
      const activo = Number(t.estatus) === 1;
      const usos   = Number(t.usos);
      return `
      <tr data-tarifa='${App.esc(JSON.stringify(t))}'>
        <td class="text-secondary">${i + 1}</td>
        <td>
          <div class="fw-semibold">${App.esc(t.nombre_tratamiento)}</div>
          ${t.clave ? `<div class="text-secondary" style="font-size:11px">
                         ${App.esc(t.clave)}</div>` : ''}
        </td>
        <td class="text-secondary">${App.esc(t.nombre_categoria ?? '-')}</td>
        <td class="fw-semibold">${App.esc(App.money(t.precio_lista))}</td>
        <td class="text-secondary">${t.duracion_min ? t.duracion_min + ' min' : '-'}</td>
        <td class="text-secondary" style="font-size:11px">
          ${t.clave_prod_serv_sat
            ? App.esc(t.clave_prod_serv_sat)
            : '<span title="Hace falta para facturar">Sin clave</span>'}
        </td>
        <td class="text-secondary" style="font-size:11px">
          ${usos > 0
            ? `${usos} procedimiento${usos === 1 ? '' : 's'}`
            : '<span title="Ningun procedimiento hereda este precio">Nadie</span>'}
        </td>
        <td>
          ${activo
            ? '<span class="chip-estado" style="background:#e6f5ee;color:var(--exito)">ACTIVO</span>'
            : '<span class="chip-estado" style="background:#eee;color:#777">INACTIVO</span>'}
        </td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#" data-accion="editar-tarifa">
                <i class="bi bi-pencil me-2"></i>Modificar</a></li>
              <li><a class="dropdown-item ${activo ? 'text-danger' : ''}" href="#"
                     data-accion="estatus-tarifa" data-estatus="${activo ? 0 : 1}">
                <i class="bi bi-${activo ? 'slash-circle' : 'check-circle'} me-2"></i>
                ${activo ? 'Desactivar' : 'Activar'}</a></li>
            </ul>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  document.getElementById('btnNuevaTarifa').addEventListener('click', () => {
    form.reset();
    document.getElementById('tarId').value = '';
    document.getElementById('tituloModalTarifa').textContent = 'Nuevo precio';
    document.getElementById('tarPorDiente').checked = true;
    document.getElementById('tarActivo').checked = true;
    form.querySelector('[name="precio_lista"]').value = '0.00';
    modal.show();
  });

  tabla.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const t = JSON.parse(opcion.closest('tr').dataset.tarifa);

    if (opcion.dataset.accion === 'editar-tarifa') {
      form.reset();
      document.getElementById('tarId').value = t.id_tratamiento;
      document.getElementById('tituloModalTarifa').textContent = 'Modificar precio';
      form.querySelector('[name="nombre_tratamiento"]').value = t.nombre_tratamiento;
      form.querySelector('[name="clave"]').value        = t.clave ?? '';
      form.querySelector('[name="id_categoria"]').value = t.id_categoria ?? '';
      form.querySelector('[name="precio_lista"]').value = Number(t.precio_lista).toFixed(2);
      form.querySelector('[name="duracion_min"]').value = t.duracion_min ?? '';
      form.querySelector('[name="clave_prod_serv_sat"]').value = t.clave_prod_serv_sat ?? '';
      form.querySelector('[name="descripcion"]').value  = t.descripcion ?? '';
      document.getElementById('tarPorDiente').checked = Number(t.aplica_por_diente) === 1;
      document.getElementById('tarActivo').checked    = Number(t.estatus) === 1;
      modal.show();
    }

    if (opcion.dataset.accion === 'estatus-tarifa') {
      const nuevo = opcion.dataset.estatus;

      /* Si hay procedimientos colgando, se dice CUANTOS antes de
         desactivar. Sin el aviso, la doctora apaga un precio y semanas
         despues descubre que media docena de procedimientos proponen
         cero pesos al armar un plan. */
      if (Number(nuevo) === 0 && Number(t.usos) > 0) {
        const ok = await App.alerta.confirmar(
          '¿Desactivar este precio?',
          `Hay ${t.usos} procedimiento(s) que heredan su costo de aquí. `
          + 'Los planes ya hechos conservan lo que se pactó, pero al armar '
          + 'uno nuevo esos procedimientos ya no propondrán precio.',
          'Sí, desactivar');
        if (!ok) return;
      }

      await App.api('configuracion/tarifa_estatus.php', {
        id_tratamiento: t.id_tratamiento, estatus: nuevo,
      });
      App.alerta.toast('Estatus actualizado');
      cargar();
    }
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const datos = App.formADatos(form);
    datos.aplica_por_diente = document.getElementById('tarPorDiente').checked ? 1 : '';
    datos.estatus           = document.getElementById('tarActivo').checked ? 1 : 0;

    try {
      await App.api('configuracion/tarifa_guardar.php', datos);
      modal.hide();
      App.alerta.toast('Precio guardado');
      cargar();
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });

  filtro.addEventListener('input', App.debounce(cargar, 350));
  categoria.addEventListener('change', cargar);
  estatus.addEventListener('change', cargar);
  cargar();
}

/* ---------------------------------------------------------------------
   Datos del consultorio
   --------------------------------------------------------------------- */
function moduloConsultorio() {
  const form = document.getElementById('formConsultorio');
  if (!form) return;

  const estado    = document.getElementById('cnsEstado');
  const municipio = document.getElementById('cnsMunicipio');
  const colonia   = document.getElementById('cnsColonia');
  const coloniaTx = document.getElementById('cnsColoniaTexto');
  const cp        = document.getElementById('cnsCp');
  const aviso     = document.getElementById('cnsAvisoDomicilio');

  /* ---------- Cascada del domicilio ---------- */
  async function cargarMunicipios(preseleccion = 0) {
    municipio.innerHTML = '<option value="">Cargando...</option>';
    const r = await App.api('geo/municipios.php', { id_estado: estado.value });
    municipio.innerHTML = '<option value="">Selecciona municipio</option>' +
      r.datos.map((m) => `<option value="${m.id_municipio}"
        ${Number(preseleccion) === Number(m.id_municipio) ? 'selected' : ''}>
        ${App.esc(m.nombre_municipio)}</option>`).join('');

    coloniaTx.placeholder = municipio.value
      ? 'Da clic para buscar la colonia'
      : 'Selecciona el municipio primero';
    actualizarBloqueo();
  }

  function actualizarBloqueo() {
    const listo = !!colonia.value;
    form.querySelectorAll('.cns-campo-domicilio').forEach((el) => {
      el.disabled = !listo;
      el.classList.toggle('campo-bloqueado', !listo);
    });
    aviso.classList.toggle('d-none', listo);
  }

  estado.addEventListener('change', () => {
    colonia.value = ''; coloniaTx.value = ''; cp.value = '';
    cargarMunicipios();
  });
  municipio.addEventListener('change', () => {
    colonia.value = ''; coloniaTx.value = ''; cp.value = '';
    coloniaTx.placeholder = municipio.value
      ? 'Da clic para buscar la colonia'
      : 'Selecciona el municipio primero';
    actualizarBloqueo();
  });
  coloniaTx.addEventListener('change', () => { actualizarBloqueo(); vistaPrevia(); });

  /* ---------- Vista previa en vivo ---------- */
  function vistaPrevia() {
    const d = App.formADatos(form);

    document.getElementById('vistaNombre').textContent =
      d.nombre_comercial || 'Consultorio Dental';
    document.getElementById('vistaTelefono').textContent =
      d.telefono ? 'TEL. ' + d.telefono : '';

    const linea = [
      [d.calle, d.num_ext].filter(Boolean).join(' '),
      coloniaTx.value,
      cp.value ? 'C.P. ' + cp.value : '',
      municipio.selectedOptions[0]?.value ? municipio.selectedOptions[0].textContent.trim() : '',
      estado.selectedOptions[0]?.textContent.trim(),
    ].filter(Boolean).join(', ');

    const texto = linea || 'Domicilio por definir';
    document.getElementById('vistaDomicilio').textContent = texto;
    document.getElementById('vistaPie').textContent = texto;
  }

  form.addEventListener('input', App.debounce(vistaPrevia, 200));
  municipio.addEventListener('change', vistaPrevia);
  estado.addEventListener('change', vistaPrevia);

  /* ---------- Carga ---------- */
  (async () => {
    const r = await App.api('configuracion/consultorio_obtener.php');
    const d = r.datos ?? {};

    ['nombre_comercial', 'razon_social', 'rfc', 'telefono', 'correo',
     'calle', 'num_ext', 'num_int', 'entre_calle_1', 'entre_calle_2', 'referencia']
      .forEach((c) => {
        const el = form.querySelector(`[name="${c}"]`);
        if (el && d[c] != null) el.value = d[c];
      });

    if (d.id_estado) estado.value = d.id_estado;
    await cargarMunicipios(d.id_municipio);

    colonia.value   = d.id_colonia ?? '';
    coloniaTx.value = d.nombre_colonia ?? '';
    cp.value        = d.codigo_postal ?? '';

    actualizarBloqueo();
    vistaPrevia();
  })();

  /* ---------- Guardar ---------- */
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();

    // Los campos deshabilitados no viajan en el FormData.
    const datos = App.formADatos(form);
    form.querySelectorAll('.cns-campo-domicilio').forEach((el) => {
      if (el.name && datos[el.name] === undefined) datos[el.name] = el.value;
    });
    datos.id_colonia = colonia.value;

    try {
      await App.api('configuracion/consultorio_guardar.php', datos);
      App.alerta.toast('Datos guardados');
      // El pie de pagina los lee al cargar, asi que se recarga.
      setTimeout(() => location.reload(), 900);
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });
}

/* ---------------------------------------------------------------------
   Configuracion > Apariencia

   Al elegir un color se guarda y se recarga. Se recarga a proposito: el
   color vive en un bloque <style> que imprime el servidor, y pintarlo
   con JavaScript ademas dejaria dos fuentes de verdad.
   --------------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
  const caja = document.getElementById('temas');
  if (!caja) return;

  caja.addEventListener('click', async (ev) => {
    const boton = ev.target.closest('[data-tema]');
    if (!boton || boton.classList.contains('activo')) return;

    const nombre = boton.querySelector('.nombre').textContent.trim();

    const ok = await App.alerta.confirmar(
      'Cambiar el color del sistema',
      `El sistema se verá en «${nombre}» para todos los usuarios. `
      + 'La pantalla se recargará para aplicarlo.',
      'Sí, cambiar');
    if (!ok) return;

    try {
      await App.api('configuracion/tema_guardar.php', { tema: boton.dataset.tema });
      location.reload();
    } catch (e) { /* ya se mostro */ }
  });
});

/* ---------------------------------------------------------------------
   Configuracion > Recordatorios de cita
   --------------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
  const tabla = document.getElementById('tablaRecordatorios');
  if (!tabla) return;

  let reglas = (window.REGLAS_RECORDATORIO ?? []).map((r) => ({
    nombre: r.nombre,
    horas_antes: Number(r.horas_antes),
  }));

  /** "48 horas" se lee mejor como "2 dias". */
  function enPalabras(horas) {
    if (horas % 24 === 0 && horas >= 24) {
      const d = horas / 24;
      return `${d} día${d === 1 ? '' : 's'} antes`;
    }
    return `${horas} hora${horas === 1 ? '' : 's'} antes`;
  }

  function pintar() {
    // Del mas anticipado al mas cercano: es el orden en que ocurren.
    reglas.sort((a, b) => b.horas_antes - a.horas_antes);

    tabla.innerHTML = reglas.length
      ? reglas.map((r, i) => `
          <tr data-i="${i}">
            <td class="text-secondary">${i + 1}</td>
            <td>
              <input type="text" class="form-control form-control-sm rec-nombre"
                     value="${App.esc(r.nombre)}" maxlength="60">
            </td>
            <td>
              <div class="input-group input-group-sm">
                <input type="number" class="form-control text-end rec-horas"
                       value="${r.horas_antes}" min="1" max="720">
                <span class="input-group-text">horas antes</span>
              </div>
            </td>
            <td class="text-secondary">${enPalabras(r.horas_antes)}</td>
            <td class="col-acciones">
              <button type="button" class="btn-menu-fila text-danger"
                      data-quitar="${i}" title="Quitar">
                <i class="bi bi-x-lg"></i>
              </button>
            </td>
          </tr>`).join('')
      : `<tr class="fila-vacia"><td colspan="5">
           Sin recordatorios. Agrega al menos uno.</td></tr>`;

    pintarLinea();
  }

  /**
   * Linea de tiempo del aviso hasta la cita.
   *
   * Sirve para ver de un golpe si los recordatorios estan repartidos o
   * si dos caen casi encima, que es el error tipico al configurarlos.
   */
  function pintarLinea() {
    const caja = document.getElementById('lineaRecordatorios');
    if (!reglas.length) {
      caja.innerHTML = '<span class="text-secondary small">Sin recordatorios.</span>';
      return;
    }

    const maximo = Math.max(...reglas.map((r) => r.horas_antes));

    caja.innerHTML = `
      <div class="linea">
        ${reglas.map((r) => {
          // Se coloca por proporcion: el primero a la izquierda y la
          // cita al final, a la derecha.
          const pos = 100 - (r.horas_antes / maximo) * 92;
          return `
            <div class="marca" style="left:${pos}%">
              <span class="punto"></span>
              <span class="texto">${App.esc(enPalabras(r.horas_antes))}</span>
            </div>`;
        }).join('')}
        <div class="marca cita" style="left:100%">
          <span class="punto"></span>
          <span class="texto">La cita</span>
        </div>
      </div>`;
  }

  tabla.addEventListener('input', (ev) => {
    const fila = ev.target.closest('tr[data-i]');
    if (!fila) return;

    const r = reglas[Number(fila.dataset.i)];
    if (ev.target.classList.contains('rec-nombre')) r.nombre = ev.target.value;
    if (ev.target.classList.contains('rec-horas')) {
      r.horas_antes = Math.max(1, Math.min(720, Number(ev.target.value) || 1));
      pintarLinea();
      fila.children[3].textContent = enPalabras(r.horas_antes);
    }
  });

  tabla.addEventListener('click', (ev) => {
    const boton = ev.target.closest('[data-quitar]');
    if (!boton) return;
    reglas.splice(Number(boton.dataset.quitar), 1);
    pintar();
  });

  document.getElementById('recAgregar').addEventListener('click', () => {
    // Se propone la mitad del aviso mas cercano: casi siempre es por
    // ahi por donde se quiere meter uno nuevo.
    const menor = reglas.length ? Math.min(...reglas.map((r) => r.horas_antes)) : 48;
    const horas = Math.max(1, Math.round(menor / 2));

    if (reglas.some((r) => r.horas_antes === horas)) {
      App.alerta.error('Ya existe un recordatorio con esa anticipación.');
      return;
    }

    reglas.push({ nombre: '', horas_antes: horas });
    pintar();
  });

  document.getElementById('recGuardar').addEventListener('click', async () => {
    const horas = reglas.map((r) => r.horas_antes);
    if (new Set(horas).size !== horas.length) {
      App.alerta.error('Hay dos recordatorios con la misma anticipación.',
        'Cada uno tiene que caer en un momento distinto.');
      return;
    }

    try {
      const r = await App.api('configuracion/recordatorios_guardar.php', {
        reglas: JSON.stringify(reglas),
      });
      reglas = r.datos.map((x) => ({
        nombre: x.nombre, horas_antes: Number(x.horas_antes),
      }));
      pintar();
      await guardarMensaje();
      App.alerta.toast('Recordatorios guardados');
    } catch (e) { /* ya se mostro */ }
  });

  /* -------------------------------------------------------------------
     El texto del recordatorio
     -------------------------------------------------------------------
     Tres cosas: insertar etiquetas donde esta el cursor, rehacer la
     vista previa mientras se escribe, y guardar.
  */
  const cuadro   = document.getElementById('recMensaje');
  const previa   = document.getElementById('recPrevia');
  const contador = document.getElementById('recContador');

  /*
     Dos plantillas, un solo cuadro.

     La de las citas con hora y la del dia apartado sin hora. Se editan
     en el mismo cuadro porque apiladas serian dos cajas grandes con dos
     vistas previas y la tarjeta no da para tanto; lo que cambia al
     picar el selector es QUE texto se esta editando y con que datos de
     muestra se ensaya.

     El texto en curso se guarda en este objeto al salir de cada
     pestania. Sin eso, cambiar de pestania y volver perderia lo que se
     acababa de escribir —y se perderia en silencio, que es lo peor—.
  */
  const MENSAJES = window.MENSAJES_RECORDATORIO ?? {
    con: { texto: cuadro?.value ?? '',
           muestra: window.MUESTRA_RECORDATORIO ?? {}, ayuda: '' },
  };

  let cual = 'con';

  const muestraActual = () => MENSAJES[cual]?.muestra ?? {};

  /* Misma regla que AgendaExtra::sustituir en PHP: UNA sola pasada.
     Si aqui se hiciera etiqueta por etiqueta, la vista previa mostraria
     un mensaje distinto del que se manda de verdad, y ese es justo el
     sitio donde no puede haber diferencia: la vista previa existe para
     confiar en ella. */
  function sustituir(plantilla) {
    const muestra = muestraActual();
    return plantilla.replace(/\{(\w+)\}/g,
      (entera, nombre) => muestra[nombre] ?? entera);
  }

  function refrescarPrevia() {
    if (!cuadro) return;
    // Lo que se ve en el cuadro es siempre el texto de la pestania en
    // curso: se guarda en cada tecla para que cambiar de pestania no
    // pueda perderlo.
    if (MENSAJES[cual]) MENSAJES[cual].texto = cuadro.value;

    previa.textContent = sustituir(cuadro.value);
    contador.textContent = `${cuadro.value.length} / 1000`;
  }

  /** Pasa a la otra plantilla sin perder lo escrito en esta. */
  function elegirMensaje(nuevo) {
    if (!cuadro || !MENSAJES[nuevo]) return;

    if (MENSAJES[cual]) MENSAJES[cual].texto = cuadro.value;
    cual = nuevo;

    cuadro.value = MENSAJES[cual].texto ?? '';
    cuadro.classList.remove('is-invalid');

    document.querySelectorAll('#recQueMensaje [data-cual]').forEach((b) => {
      b.classList.toggle('activo', b.dataset.cual === cual);
    });
    const ayuda = document.getElementById('recQueMensajeAyuda');
    if (ayuda) ayuda.textContent = MENSAJES[cual].ayuda ?? '';

    refrescarPrevia();
  }

  document.getElementById('recQueMensaje')
    ?.addEventListener('click', (ev) => {
      const boton = ev.target.closest('[data-cual]');
      if (boton) elegirMensaje(boton.dataset.cual);
    });

  /* Inserta la etiqueta DONDE ESTA EL CURSOR, no al final.

     Al final seria mas facil de programar y obligaria a cortar y pegar
     a mano cada vez: se escribe "Le recordamos la cita de" y lo que
     sigue es el nombre, ahi mismo. */
  document.querySelectorAll('[data-etiqueta]').forEach((chip) => {
    chip.addEventListener('click', () => {
      const etiqueta = chip.dataset.etiqueta;
      const desde = cuadro.selectionStart ?? cuadro.value.length;
      const hasta = cuadro.selectionEnd   ?? cuadro.value.length;

      cuadro.value = cuadro.value.slice(0, desde) + etiqueta
                   + cuadro.value.slice(hasta);

      // El cursor queda DESPUES de lo insertado y el foco vuelve al
      // cuadro: se sigue escribiendo sin tener que volver a picar.
      cuadro.focus();
      cuadro.setSelectionRange(desde + etiqueta.length, desde + etiqueta.length);
      refrescarPrevia();
    });
  });

  cuadro?.addEventListener('input', refrescarPrevia);

  async function guardarMensaje() {
    if (!cuadro) return;

    // Lo que esta en el cuadro ahora mismo es de la pestania en curso.
    if (MENSAJES[cual]) MENSAJES[cual].texto = cuadro.value;

    cuadro.classList.remove('is-invalid');
    try {
      // Los dos de un viaje: se editan juntos y se guardan juntos. El
      // servidor rechaza los dos si uno falla, asi que mandarlos por
      // separado podria dejar uno guardado y el otro no.
      await App.api('configuracion/recordatorio_mensaje_guardar.php', {
        mensaje: MENSAJES.con?.texto ?? cuadro.value,
        mensaje_sin_hora: MENSAJES.sin?.texto ?? '',
      });
    } catch (e) {
      /*
         El aviso va pegado al cuadro, y la pestania del error se abre.

         El error puede ser del mensaje que NO se esta viendo: se guardan
         los dos con un boton. Sin cambiar de pestania, el cuadro se
         marcaria en rojo con un texto que esta bien y el aviso hablaria
         de otro, que es como se pierde media hora.
      */
      const deQuien = e.errores?.mensaje_sin_hora ? 'sin'
        : (e.errores?.mensaje ? 'con' : null);

      if (deQuien) {
        if (deQuien !== cual) elegirMensaje(deQuien);
        cuadro.classList.add('is-invalid');
        document.getElementById('recMensajeError').textContent =
          e.errores[deQuien === 'sin' ? 'mensaje_sin_hora' : 'mensaje'];
        cuadro.focus();
      }
      throw e;
    }
  }

  elegirMensaje('con');
  pintar();
});

/* =====================================================================
   Configuracion > Respaldos
   =====================================================================
   Crear, descargar y restaurar.

   La pantalla ya llega dibujada desde PHP; esto la rehace despues de
   crear o restaurar, sin recargar. Recargar perderia el aviso de lo que
   acaba de pasar, que en esta pantalla es justo lo que hay que leer.
   ===================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  const pantalla = document.getElementById('respaldosPantalla');
  if (!pantalla) return;

  const tabla = document.getElementById('rsTabla');

  const modalRestaurar = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalRestaurar'));

  let respaldos = [];

  /**
   * La fecha de un respaldo, desde su sello de tiempo.
   *
   * No se pasa por App.fechaHora: esa espera el texto que manda MySQL y
   * aqui lo que llega es un sello de tiempo de Unix. Convertirlo con
   * toISOString para darsela lo pasaria a UTC, y en Nayarit eso son
   * seis horas menos: el respaldo de las 2:30 de la madrugada se
   * leeria como de las 8:30 de la noche del dia anterior.
   */
  function cuando(marca) {
    return new Date(Number(marca) * 1000).toLocaleString('es-MX', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  }

  /** Hace cuanto, en palabras. El mismo criterio que usa PHP al pintar. */
  function hace(marca) {
    const horas = Math.floor((Date.now() / 1000 - Number(marca)) / 3600);
    if (horas < 1) return 'hace un momento';
    if (horas < 24) return `hace ${horas} h`;
    const dias = Math.floor(horas / 24);
    return dias === 1 ? 'ayer' : `hace ${dias} días`;
  }

  function pintar(lista) {
    respaldos = lista ?? [];

    if (!respaldos.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="6">
          <i class="bi bi-shield-exclamation fs-3 d-block mb-2"
             style="color:var(--alerta)"></i>
          No hay ningún respaldo todavía.
          <div class="text-secondary small mt-1">
            Pica "Crear respaldo ahora" para hacer el primero.
          </div></td></tr>`;
      return;
    }

    tabla.innerHTML = respaldos.map((r, i) => `
      <tr>
        <td>
          <div class="fw-semibold" style="font-size:12.5px">
            ${App.esc(r.nombre)}
          </div>
        </td>
        <td>
          ${r.tipo === 'base'
            ? '<span class="chip-estado" style="background:#f0e9f7;color:var(--morado-700)">'
              + '<i class="bi bi-database me-1"></i>Base</span>'
            : '<span class="chip-estado" style="background:#e8f4f1;color:#13795f">'
              + '<i class="bi bi-images me-1"></i>Archivos</span>'}
        </td>
        <td class="text-secondary" style="font-size:12.5px">
          ${App.esc(cuando(r.fecha))}
          <div style="font-size:11px">${App.esc(hace(r.fecha))}</div>
        </td>
        <td class="text-secondary" style="font-size:12.5px">
          ${App.esc(App.tamanoLegible(r.bytes))}
        </td>
        <td style="font-size:11.5px">
          ${r.bajado
            ? '<span class="doc-estado escaneado">'
              + '<i class="bi bi-check-circle-fill"></i> Ya lo bajaste</span>'
            : '<span class="chip-sin-hora">'
              + '<i class="bi bi-cloud-arrow-down"></i> Sin bajar</span>'}
          ${r.cifrado
            ? '<div class="text-secondary mt-1">'
              + '<i class="bi bi-lock-fill"></i> cifrado</div>' : ''}
        </td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item"
                     href="${App.URL_BASE}/respaldo_bajar.php?nombre=${encodeURIComponent(r.nombre)}">
                <i class="bi bi-download me-2"></i>Descargar</a></li>
              ${r.restaurable ? `
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="#"
                     data-restaurar="${i}">
                <i class="bi bi-arrow-counterclockwise me-2"></i>Restaurar</a></li>`
              : `
              <li><hr class="dropdown-divider"></li>
              <li><span class="dropdown-item-text text-secondary"
                        style="font-size:11.5px">
                ${r.tipo === 'base'
                  ? 'Cifrado: se restaura en el servidor'
                  : 'Los archivos se restauran en el servidor'}
              </span></li>`}
            </ul>
          </div>
        </td>
      </tr>`).join('');
  }

  /** Pone al dia las tres tarjetas de arriba. */
  function pintarResumen(resumen) {
    if (!resumen) return;

    const poner = (id, texto) => {
      const el = document.getElementById(id);
      if (el) el.textContent = texto;
    };

    poner('rsUltimaBase',
      resumen.ultima_base ? hace(resumen.ultima_base) : 'nunca');
    poner('rsUltimoArchivo',
      resumen.ultimo_archivo ? hace(resumen.ultimo_archivo) : 'nunca');
    poner('rsOcupan', App.tamanoLegible(resumen.bytes));
    poner('rsCuantos', `${resumen.cuantos} respaldo(s)`);
  }

  async function cargar() {
    try {
      const r = await App.api('configuracion/respaldos_listar.php', {});
      pintar(r.datos.respaldos);
      pintarResumen(r.datos.resumen);
    } catch (e) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="6">
          No se pudo leer la carpeta de respaldos.</td></tr>`;
    }
  }

  /* -------------------------------------------------------------------
     Crear
     ------------------------------------------------------------------- */
  document.getElementById('rsCrear')?.addEventListener('click', async (ev) => {
    const boton = ev.currentTarget;

    /*
       El boton se apaga y lo dice.

       Un volcado de la base tarda segundos y no da ninguna señal: sin
       esto, lo que se hace es picar otra vez. Dos volcados a la vez no
       corrompen nada —el segundo falla porque ya hay uno de ese
       minuto— pero el aviso de error sobre algo que si funciono
       confunde mas que la espera.
    */
    const textoAntes = boton.innerHTML;
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1">'
                    + '</span> Creando...';

    try {
      const r = await App.api('configuracion/respaldo_crear.php', {});
      pintar(r.datos.respaldos);
      pintarResumen(r.datos.resumen);
      App.alerta.exito('Respaldo creado', r.mensaje ?? '');
    } catch (e) {
      /* ya se mostro */
    } finally {
      boton.disabled = false;
      boton.innerHTML = textoAntes;
    }
  });

  /* -------------------------------------------------------------------
     Restaurar
     ------------------------------------------------------------------- */
  tabla.addEventListener('click', (ev) => {
    const opcion = ev.target.closest('[data-restaurar]');
    if (!opcion) return;
    ev.preventDefault();

    const r = respaldos[Number(opcion.dataset.restaurar)];
    if (!r) return;

    document.getElementById('rtNombre').value = r.nombre;
    document.getElementById('rtArchivo').textContent = r.nombre;
    document.getElementById('rtCuando').textContent =
      `${hace(r.fecha)} · ${App.tamanoLegible(r.bytes)}`;

    // El campo se limpia cada vez: dejandolo escrito de la vez
    // anterior, restaurar seria un clic sin leer nada.
    const campo = document.getElementById('rtConfirmacion');
    campo.value = '';
    campo.classList.remove('is-invalid');

    modalRestaurar.show();
    setTimeout(() => campo.focus(), 250);
  });

  document.getElementById('formRestaurar')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();

    const boton = document.getElementById('rtAceptar');
    const campo = document.getElementById('rtConfirmacion');
    campo.classList.remove('is-invalid');

    const textoAntes = boton.innerHTML;
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1">'
                    + '</span> Restaurando...';

    try {
      const r = await App.api('configuracion/respaldo_restaurar.php',
        App.formADatos(ev.target));

      modalRestaurar.hide();
      pintar(r.datos.respaldos);
      pintarResumen(r.datos.resumen);

      /*
         Esto NO se avisa con un toast.

         Lo que acaba de pasar es que todos los expedientes son otros.
         Un aviso que se va solo a los tres segundos no alcanza para
         eso, y ademas hay algo que hay que leer: el nombre del respaldo
         de seguridad, que es la unica forma de volver atras.
      */
      await Swal.fire({
        icon: 'success',
        title: 'Base restaurada',
        html: `<div style="text-align:left;font-size:14px">
                 <p>Se restauró <b>${App.esc(r.datos.restaurado)}</b>.</p>
                 <p>Antes se guardó
                    <b>${App.esc(r.datos.seguridad)}</b>
                    con lo que había hasta hace un momento. Si éste no era
                    el respaldo correcto, restaura ése.</p>
                 <p class="text-secondary" style="font-size:12.5px">
                   Cierra sesión y vuelve a entrar: lo que tengas abierto
                   en otra pestaña es de la base anterior.
                 </p>
               </div>`,
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#7b2d8e',
        allowOutsideClick: false,
      });
    } catch (e) {
      if (e.errores?.confirmacion) {
        campo.classList.add('is-invalid');
        document.getElementById('rtError').textContent = e.errores.confirmacion;
        campo.focus();
      }
    } finally {
      boton.disabled = false;
      boton.innerHTML = textoAntes;
    }
  });

  cargar();
});
