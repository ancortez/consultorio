/* =====================================================================
   Modulo Consultas: agenda y citas
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  moduloAgenda();
  moduloPlan();
});

function moduloAgenda() {
  const tabla = document.getElementById('agTabla');
  if (!tabla) return;

  const fecha    = document.getElementById('agFecha');
  const dentista = document.getElementById('agDentista');
  const estado   = document.getElementById('agEstado');
  const resumen  = document.getElementById('agResumen');
  const espacios = document.getElementById('agEspacios');

  const modalCita = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalCita'));
  const modalBusca = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalBuscaPaciente'));
  const formCita  = document.getElementById('formCita');

  /* -------------------------------------------------------------------
     Listado del dia
     ------------------------------------------------------------------- */
  async function cargar() {
    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="6">
        <div class="spinner-border spinner-border-sm me-2"
             style="color:var(--morado-600)"></div>Cargando...</td></tr>`;

    document.getElementById('agTituloDia').innerHTML =
      `<i class="bi bi-calendar3 me-2"></i>${diaLargo(fecha.value)}`;

    const r = await App.api('consultas/citas_listar.php', {
      desde: fecha.value,
      hasta: fecha.value,
      id_usuario_dentista: dentista.value,
      id_estado_cita: estado.value,
    });

    const pendientes = r.datos.filter((c) => Number(c.id_estado_cita) === 1).length;
    resumen.innerHTML = r.datos.length
      ? `${r.datos.length} cita(s)`
        + (pendientes ? ` · <b style="color:var(--alerta)">${pendientes} por confirmar</b>` : '')
      : '';

    if (!r.datos.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="6">
          <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
          No hay citas para este día.</td></tr>`;
      cargarEspacios();
      return;
    }

    tabla.innerHTML = r.datos.map((c) => `
      <tr data-cita='${App.esc(JSON.stringify(c))}'>
        <td>
          <div class="fw-semibold">${App.esc(c.fecha_inicio.substring(11, 16))}</div>
          <div class="text-secondary" style="font-size:11px">
            a ${App.esc(c.fecha_fin.substring(11, 16))}</div>
        </td>
        <td>
          <a class="fw-semibold text-decoration-none"
             href="${App.URL_BASE}/index.php?modulo=paciente&vista=detalle_paciente&id=${c.id_registro_paciente}">
            ${App.esc(c.nombre_completo)}
          </a>
          <div class="text-secondary" style="font-size:11px">
            ${App.esc(c.num_expediente)}
            ${c.telefono ? ' · ' + App.esc(c.telefono) : ''}
            ${c.edad != null ? ' · ' + c.edad + ' años' : ''}
          </div>
        </td>
        <td class="text-secondary">${App.esc(c.motivo ?? '-')}</td>
        <td class="text-secondary" style="font-size:12.5px">
          ${App.esc(c.dentista)}
          ${c.nombre_sala ? `<div style="font-size:11px">${App.esc(c.nombre_sala)}</div>` : ''}
        </td>
        <td>
          <span class="chip-estado"
                style="background:${App.esc(c.color)}1f;color:${App.esc(c.color)}">
            ${App.esc(c.nombre_estado)}
          </span>
        </td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              ${Number(c.id_estado_cita) === 1 ? `
              <li><a class="dropdown-item" href="#" data-accion="cita-estado" data-estado="2">
                <i class="bi bi-check2-circle me-2"></i>Confirmar</a></li>` : ''}
              ${[1, 2].includes(Number(c.id_estado_cita)) ? `
              <li><a class="dropdown-item" href="#" data-accion="cita-estado" data-estado="3">
                <i class="bi bi-door-open me-2"></i>Marcar en sala</a></li>` : ''}
              ${[2, 3].includes(Number(c.id_estado_cita)) ? `
              <li><a class="dropdown-item" href="#" data-accion="cita-estado" data-estado="4">
                <i class="bi bi-clipboard2-check me-2"></i>Marcar atendida</a></li>` : ''}
              <li><a class="dropdown-item" href="#" data-accion="cita-editar">
                <i class="bi bi-calendar-event me-2"></i>Reagendar</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="#" data-accion="cita-estado" data-estado="6">
                <i class="bi bi-person-x me-2"></i>No asistió</a></li>
              <li><a class="dropdown-item text-danger" href="#"
                     data-accion="cita-estado" data-estado="5">
                <i class="bi bi-x-circle me-2"></i>Cancelar cita</a></li>
            </ul>
          </div>
        </td>
      </tr>`).join('');

    cargarEspacios();
  }

  /** Fecha en palabras: "Lunes 17 de agosto de 2026". */
  function diaLargo(iso) {
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('es-MX', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    }).replace(/^\w/, (c) => c.toUpperCase());
  }

  /* -------------------------------------------------------------------
     Espacios libres del panel lateral
     ------------------------------------------------------------------- */
  async function cargarEspacios() {
    const ayuda = document.getElementById('agEspaciosAyuda');

    if (!dentista.value) {
      espacios.innerHTML = '';
      ayuda.textContent = 'Elige un dentista para ver sus espacios libres.';
      return;
    }

    const r = await App.api('consultas/espacios.php', {
      fecha: fecha.value,
      id_usuario_dentista: dentista.value,
    });

    if (!r.datos.espacios.length) {
      espacios.innerHTML = '';
      ayuda.textContent = 'El consultorio no abre ese día.';
      return;
    }

    const libres = r.datos.espacios.filter((e) => e.libre).length;
    ayuda.innerHTML = `<b>${libres}</b> de ${r.datos.espacios.length} espacios libres.`;

    espacios.innerHTML = r.datos.espacios.map((e) => `
      <div class="espacio ${e.libre ? 'libre' : 'ocupado'}"
           title="${e.libre ? 'Libre' : App.esc(e.motivo)}"
           ${e.libre ? `data-hora="${e.hora}"` : ''}>
        <span class="hora">${e.hora}</span>
        ${e.libre ? '' : `<span class="quien">${App.esc(e.motivo)}</span>`}
      </div>`).join('');
  }

  // Dar clic en un espacio libre abre la cita ya con esa hora puesta.
  espacios.addEventListener('click', (ev) => {
    const bloque = ev.target.closest('.espacio.libre[data-hora]');
    if (!bloque) return;
    abrirCita(null, bloque.dataset.hora);
  });

  /* -------------------------------------------------------------------
     Modal de cita
     ------------------------------------------------------------------- */
  function abrirCita(cita = null, horaPreferida = null) {
    formCita.reset();
    document.getElementById('citaId').value = cita?.id_cita ?? '';
    document.getElementById('citaPaciente').value = cita?.id_registro_paciente ?? '';
    document.getElementById('citaPacienteTexto').value = cita?.nombre_completo ?? '';
    document.getElementById('citaHora').value = '';
    document.getElementById('citaGuardar').disabled = true;

    document.getElementById('tituloModalCita').textContent =
      cita ? 'Reagendar cita' : 'Nueva cita';

    document.getElementById('citaFecha').value = cita
      ? cita.fecha_inicio.substring(0, 10)
      : fecha.value;

    if (cita) {
      document.getElementById('citaDentista').value = cita.id_usuario_dentista;
      formCita.querySelector('[name="motivo"]').value = cita.motivo ?? '';
      formCita.querySelector('[name="id_sala"]').value = cita.id_sala ?? '';

      const min = (new Date(cita.fecha_fin.replace(' ', 'T'))
                 - new Date(cita.fecha_inicio.replace(' ', 'T'))) / 60000;
      document.getElementById('citaDuracion').value = String(min);
    } else if (dentista.value) {
      document.getElementById('citaDentista').value = dentista.value;
    }

    modalCita.show();
    cargarEspaciosModal(horaPreferida ?? cita?.fecha_inicio?.substring(11, 16));
  }

  async function cargarEspaciosModal(horaPreferida = null) {
    const caja = document.getElementById('citaEspacios');
    const f    = document.getElementById('citaFecha').value;
    const d    = document.getElementById('citaDentista').value;
    const dur  = document.getElementById('citaDuracion').value;
    const idCita = document.getElementById('citaId').value;

    if (!f || !d) {
      caja.innerHTML = '<div class="text-secondary small">'
        + 'Elige dentista y fecha para ver los espacios libres.</div>';
      return;
    }

    caja.innerHTML = '<div class="text-secondary small">Buscando espacios...</div>';

    const r = await App.api('consultas/espacios.php', {
      fecha: f, id_usuario_dentista: d, duracion_min: dur, id_cita: idCita,
    });

    const libres = r.datos.espacios.filter((e) => e.libre);

    if (!libres.length) {
      caja.innerHTML = `<div class="aviso-vinculado">
          <i class="bi bi-calendar-x"></i>
          <div>No hay espacios libres ese día con esa duración.
               Prueba otra fecha o una cita más corta.</div>
        </div>`;
      document.getElementById('citaHora').value = '';
      revisarListo();
      return;
    }

    caja.innerHTML = r.datos.espacios.map((e) => `
      <div class="espacio ${e.libre ? 'libre elegible' : 'ocupado'}"
           ${e.libre ? `data-hora="${e.hora}"` : ''}
           title="${e.libre ? `${e.hora} a ${e.fin}` : App.esc(e.motivo)}">
        <span class="hora">${e.hora}</span>
        ${e.libre ? '' : `<span class="quien">${App.esc(e.motivo)}</span>`}
      </div>`).join('');

    // Si venia una hora sugerida y sigue libre, se deja marcada.
    if (horaPreferida) {
      const b = caja.querySelector(`.espacio.libre[data-hora="${horaPreferida}"]`);
      if (b) elegirEspacio(b);
    }
    revisarListo();
  }

  function elegirEspacio(bloque) {
    document.querySelectorAll('#citaEspacios .espacio').forEach(
      (e) => e.classList.remove('elegido'));
    bloque.classList.add('elegido');
    document.getElementById('citaHora').value = bloque.dataset.hora;
    revisarListo();
  }

  function revisarListo() {
    document.getElementById('citaGuardar').disabled =
      !document.getElementById('citaHora').value
      || !document.getElementById('citaPaciente').value;
  }

  document.getElementById('citaEspacios').addEventListener('click', (ev) => {
    const bloque = ev.target.closest('.espacio.libre[data-hora]');
    if (bloque) elegirEspacio(bloque);
  });

  ['citaFecha', 'citaDentista', 'citaDuracion'].forEach((id) => {
    document.getElementById(id).addEventListener('change', () => cargarEspaciosModal());
  });

  /* -------------------------------------------------------------------
     Buscador de paciente
     ------------------------------------------------------------------- */
  document.getElementById('citaPacienteTexto').addEventListener('click', () => {
    document.getElementById('bpFiltro').value = '';
    document.getElementById('bpLista').innerHTML =
      '<div class="p-4 text-center text-secondary small">Escribe para buscar.</div>';
    modalBusca.show();
    setTimeout(() => document.getElementById('bpFiltro').focus(), 250);
  });

  document.getElementById('bpFiltro').addEventListener('input', App.debounce(async (ev) => {
    const q = ev.target.value.trim();
    const lista = document.getElementById('bpLista');

    if (q.length < 2) {
      lista.innerHTML = '<div class="p-4 text-center text-secondary small">'
        + 'Escribe al menos dos letras.</div>';
      return;
    }

    const r = await App.api('paciente/listar.php', { q, estatus: '1' });

    lista.innerHTML = r.datos.length
      ? r.datos.map((p) => `
          <div class="opcion" data-id="${p.id_registro_paciente}"
               data-nombre="${App.esc(p.nombre_completo)}">
            <div>
              <div class="fw-semibold">${App.esc(p.nombre_completo)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(p.num_expediente)}
                ${p.telefono ? ' · ' + App.esc(p.telefono) : ''}
              </div>
            </div>
            <i class="bi bi-chevron-right"></i>
          </div>`).join('')
      : '<div class="p-4 text-center text-secondary small">Sin resultados.</div>';
  }, 350));

  document.getElementById('bpLista').addEventListener('click', (ev) => {
    const opcion = ev.target.closest('.opcion[data-id]');
    if (!opcion) return;

    document.getElementById('citaPaciente').value = opcion.dataset.id;
    document.getElementById('citaPacienteTexto').value = opcion.dataset.nombre;
    modalBusca.hide();
    revisarListo();
  });

  /* -------------------------------------------------------------------
     Guardar y acciones
     ------------------------------------------------------------------- */
  formCita.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      const r = await App.api('consultas/cita_guardar.php', App.formADatos(formCita));
      modalCita.hide();
      App.alerta.toast('Cita agendada. Falta confirmarla', 'info');

      // Si la cita quedo en otro dia, se salta a ese dia.
      const dia = r.datos.fecha_inicio.substring(0, 10);
      if (dia !== fecha.value) fecha.value = dia;
      cargar();
    } catch (e) {
      App.marcarErrores(formCita, e.errores);
    }
  });

  tabla.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const cita = JSON.parse(opcion.closest('tr').dataset.cita);

    if (opcion.dataset.accion === 'cita-editar') {
      abrirCita(cita);
      return;
    }

    if (opcion.dataset.accion === 'cita-estado') {
      const nuevo = Number(opcion.dataset.estado);

      // Cancelar y "no asistió" se confirman: son los que no tienen
      // vuelta atrás fácil y los que más se pican por error.
      if ([5, 6].includes(nuevo)) {
        const ok = await App.alerta.confirmar(
          nuevo === 5 ? '¿Cancelar esta cita?' : '¿Marcar que no asistió?',
          `${cita.nombre_completo} · ${cita.fecha_inicio.substring(11, 16)}`,
          'Sí, continuar');
        if (!ok) return;
      }

      await App.api('consultas/cita_estatus.php', {
        id_cita: cita.id_cita, id_estado_cita: nuevo,
      });
      App.alerta.toast('Cita actualizada');
      cargar();
    }
  });

  /* -------------------------------------------------------------------
     Navegacion de dias
     ------------------------------------------------------------------- */
  const mover = (dias) => {
    const d = new Date(fecha.value + 'T00:00:00');
    d.setDate(d.getDate() + dias);
    fecha.value = d.toISOString().substring(0, 10);
    cargar();
  };

  document.getElementById('agAyer').addEventListener('click', () => mover(-1));
  document.getElementById('agManana').addEventListener('click', () => mover(1));
  document.getElementById('agHoy').addEventListener('click', () => {
    fecha.value = new Date().toISOString().substring(0, 10);
    cargar();
  });

  fecha.addEventListener('change', cargar);
  dentista.addEventListener('change', cargar);
  estado.addEventListener('change', cargar);

  document.getElementById('btnNuevaCita')?.addEventListener('click', () => abrirCita());

  cargar();
}


/* ---------------------------------------------------------------------
   Plan de tratamiento
   --------------------------------------------------------------------- */
function moduloPlan() {
  const form = document.getElementById('formPlan');
  if (!form) return;

  const tabla     = document.getElementById('planTabla');
  const descuento = document.getElementById('planDescuento');
  const modal     = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalProcSel'));
  const tplDientes = document.getElementById('tplDientes').innerHTML;

  // Los renglones viven en memoria y se pintan de nuevo en cada cambio.
  // Es mas simple que ir parchando celdas sueltas del HTML.
  let renglones = [];
  let procedimientos = [];

  /* -------------------------------------------------------------------
     Pintado
     ------------------------------------------------------------------- */
  function pintar() {
    if (!renglones.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="10">
          <i class="bi bi-list-check fs-3 d-block mb-2 opacity-50"></i>
          Agrega los procedimientos del plan.</td></tr>`;
      totalizar();
      return;
    }

    tabla.innerHTML = renglones.map((r, i) => `
      <tr data-i="${i}">
        <td class="text-secondary">${i + 1}</td>
        <td>
          <div class="fw-semibold">${App.esc(r.nombre_procedimiento)}</div>
          ${r.requiere_anestesia
            ? '<span class="chip-estado chip-alerta-clinica">ANESTESIA</span>' : ''}
          <input type="text" class="form-control form-control-sm mt-1 campo-nota"
                 placeholder="Nota del procedimiento" value="${App.esc(r.notas ?? '')}"
                 maxlength="255">
        </td>
        <td>
          ${r.aplica_por_diente
            ? `<select class="form-select form-select-sm campo-diente">${tplDientes}</select>`
            : '<span class="text-secondary">—</span>'}
        </td>
        <td>
          <input type="number" class="form-control form-control-sm text-center campo-cant"
                 value="${r.cantidad}" min="1" max="32">
        </td>
        <td>
          <input type="number" class="form-control form-control-sm text-end campo-precio"
                 value="${Number(r.precio_unitario).toFixed(2)}" step="0.01" min="0">
        </td>
        <td>
          <input type="number" class="form-control form-control-sm text-end campo-desc"
                 value="${Number(r.descuento).toFixed(2)}" step="0.01" min="0">
        </td>
        <td class="fw-semibold text-end">${App.money(r.importe)}</td>
        <td>
          <input type="date" class="form-control form-control-sm campo-fecha"
                 value="${r.fecha_programada ?? ''}">
        </td>
        <td>
          <input type="date" class="form-control form-control-sm campo-prox"
                 value="${r.proxima_cita ?? ''}">
        </td>
        <td class="col-acciones">
          <button type="button" class="btn-menu-fila text-danger" data-quitar="${i}"
                  title="Quitar"><i class="bi bi-x-lg"></i></button>
        </td>
      </tr>`).join('');

    // Los selectores de diente se marcan despues de pintar: el HTML de
    // las opciones viene de una plantilla y no trae el valor elegido.
    renglones.forEach((r, i) => {
      const sel = tabla.querySelector(`tr[data-i="${i}"] .campo-diente`);
      if (sel && r.id_diente) sel.value = r.id_diente;
    });

    totalizar();
  }

  function totalizar() {
    const subtotal = renglones.reduce((s, r) => s + Number(r.importe), 0);
    const desc = Number(descuento.value) || 0;
    const total = Math.max(0, subtotal - desc);

    document.getElementById('planSubtotal').textContent = App.money(subtotal);
    document.getElementById('planTotal').textContent = App.money(total);
  }

  /** Recalcula el importe de un renglon con sus valores actuales. */
  function recalcular(i) {
    const r = renglones[i];
    r.importe = Math.max(0,
      Number(r.precio_unitario) * Number(r.cantidad) - Number(r.descuento));
  }

  /* -------------------------------------------------------------------
     Edicion en la tabla
     ------------------------------------------------------------------- */
  tabla.addEventListener('input', (ev) => {
    const fila = ev.target.closest('tr[data-i]');
    if (!fila) return;

    const i = Number(fila.dataset.i);
    const r = renglones[i];
    const el = ev.target;

    if (el.classList.contains('campo-cant'))   r.cantidad = Math.max(1, Number(el.value) || 1);
    if (el.classList.contains('campo-precio')) r.precio_unitario = Number(el.value) || 0;
    if (el.classList.contains('campo-desc'))   r.descuento = Number(el.value) || 0;
    if (el.classList.contains('campo-nota'))   r.notas = el.value;
    if (el.classList.contains('campo-fecha'))  r.fecha_programada = el.value;
    if (el.classList.contains('campo-prox'))   r.proxima_cita = el.value;

    if (['campo-cant', 'campo-precio', 'campo-desc'].some((c) => el.classList.contains(c))) {
      recalcular(i);
      // Solo se actualiza la celda del importe y el total: repintar toda
      // la tabla haria perder el foco en cada tecla.
      fila.children[6].textContent = App.money(r.importe);
      totalizar();
    }
  });

  tabla.addEventListener('change', (ev) => {
    const fila = ev.target.closest('tr[data-i]');
    if (!fila || !ev.target.classList.contains('campo-diente')) return;
    renglones[Number(fila.dataset.i)].id_diente = ev.target.value || null;
  });

  tabla.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-quitar]');
    if (!btn) return;
    renglones.splice(Number(btn.dataset.quitar), 1);
    pintar();
  });

  descuento.addEventListener('input', totalizar);

  /* -------------------------------------------------------------------
     Selector de procedimientos
     ------------------------------------------------------------------- */
  async function cargarProcedimientos(q = '') {
    const lista = document.getElementById('procSelLista');
    lista.innerHTML = `<div class="cargando">
        <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
        Buscando...</div>`;

    const r = await App.api('catalogo/procedimientos.php', { q });
    procedimientos = r.datos;

    lista.innerHTML = procedimientos.length
      ? procedimientos.map((p) => `
          <div class="opcion" data-id="${p.id_procedimiento}">
            <div>
              <div class="fw-semibold">${App.esc(p.nombre_procedimiento)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(p.nombre_categoria ?? '')}
                ${p.duracion_min ? ' · ' + p.duracion_min + ' min' : ''}
                ${Number(p.requiere_anestesia) === 1 ? ' · Requiere anestesia' : ''}
              </div>
            </div>
            <span class="badge badge-suave">${App.money(p.costo_sugerido)}</span>
          </div>`).join('')
      : '<div class="p-4 text-center text-secondary small">Sin resultados.</div>';
  }

  document.getElementById('planAgregar').addEventListener('click', () => {
    document.getElementById('procSelFiltro').value = '';
    modal.show();
    cargarProcedimientos();
    setTimeout(() => document.getElementById('procSelFiltro').focus(), 250);
  });

  document.getElementById('procSelFiltro').addEventListener('input',
    App.debounce((ev) => cargarProcedimientos(ev.target.value.trim()), 300));

  document.getElementById('procSelLista').addEventListener('click', (ev) => {
    const opcion = ev.target.closest('.opcion[data-id]');
    if (!opcion) return;

    const p = procedimientos.find(
      (x) => String(x.id_procedimiento) === opcion.dataset.id);
    if (!p) return;

    renglones.push({
      id_procedimiento: p.id_procedimiento,
      nombre_procedimiento: p.nombre_procedimiento,
      aplica_por_diente: Number(p.aplica_por_diente) === 1,
      requiere_anestesia: Number(p.requiere_anestesia) === 1,
      id_diente: null,
      cantidad: 1,
      precio_unitario: Number(p.costo_sugerido),
      descuento: 0,
      importe: Number(p.costo_sugerido),
      fecha_programada: '',
      proxima_cita: '',
      notas: '',
    });

    pintar();
    modal.hide();
  });

  /* -------------------------------------------------------------------
     Carga de un plan existente
     ------------------------------------------------------------------- */
  const idPlan = document.getElementById('planId').value;
  if (idPlan) {
    (async () => {
      const r = await App.api('consultas/plan_obtener.php', {
        id_plan_tratamiento: idPlan,
      });
      const p = r.datos;

      ['diagnostico', 'oclusion', 'traumatismos', 'secuelas_traumatismos',
       'observaciones', 'fecha'].forEach((c) => {
        const el = form.querySelector(`[name="${c}"]`);
        if (el && p[c] != null) el.value = p[c];
      });

      form.querySelector('[name="id_usuario_dentista"]').value = p.id_usuario_dentista;
      form.querySelector('[name="id_estado_plan"]').value = p.id_estado_plan;
      descuento.value = Number(p.descuento).toFixed(2);

      renglones = p.renglones.map((d) => ({
        id_procedimiento: d.id_procedimiento,
        nombre_procedimiento: d.nombre_procedimiento ?? 'PROCEDIMIENTO',
        aplica_por_diente: Number(d.aplica_por_diente) === 1,
        requiere_anestesia: Number(d.requiere_anestesia) === 1,
        id_diente: d.id_diente,
        cantidad: Number(d.cantidad),
        precio_unitario: Number(d.precio_unitario),
        descuento: Number(d.descuento),
        importe: Number(d.importe),
        fecha_programada: d.fecha_programada ?? '',
        proxima_cita: d.proxima_cita ?? '',
        notas: d.notas ?? '',
      }));

      pintar();
    })();
  }

  /* -------------------------------------------------------------------
     Guardar
     ------------------------------------------------------------------- */
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();

    if (!renglones.length) {
      App.alerta.error('Plan vacío', 'Agrega al menos un procedimiento.');
      return;
    }

    document.getElementById('planRenglones').value = JSON.stringify(renglones);

    try {
      const r = await App.api('consultas/plan_guardar.php', App.formADatos(form));

      await Swal.fire({
        icon: 'success',
        title: 'Plan guardado',
        html: `<div style="font-size:14px;line-height:1.6">
                 Total: <b>${App.money(r.datos.total)}</b>
                 ${r.datos.pendiente ? `
                   <div style="background:#fff6e5;border:1px solid #f0d9a8;
                               border-radius:9px;padding:.7rem .9rem;
                               color:#7a5a12;margin-top:.9rem;font-size:12.5px">
                     Imprime el plan, recoge la firma del tutor y súbelo
                     escaneado desde el <b>Expediente electrónico</b>.
                   </div>` : ''}
               </div>`,
        confirmButtonColor: '#7b2d8e',
        confirmButtonText: 'Ir al expediente',
      });

      App.ir(`index.php?modulo=paciente&vista=detalle_paciente`
        + `&id=${form.querySelector('[name="id_registro_paciente"]').value}#tab-planes`);
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });
}
