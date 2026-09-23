/* =====================================================================
   Modulo Consultas: agenda y citas
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  moduloAgenda();
  moduloPlan();
  listadoPlanes();
  listadoConsultas();
  detalleConsulta();
  vistaSemana();
});

/* ---------------------------------------------------------------------
   Consultas > Planes de tratamiento

   Lista de trabajo del consultorio: los planes que siguen vivos. El
   filtro por estatus se hace aqui y no en la base porque la lista ya
   viene acotada a los tres estados vigentes y nunca sera larga.
   --------------------------------------------------------------------- */
function listadoPlanes() {
  const tabla = document.getElementById('tablaPlanesAbiertos');
  if (!tabla) return;

  const filtro   = document.getElementById('planFiltro');
  const estado   = document.getElementById('planFiltroEstado');
  const limpiar  = document.getElementById('planFiltroLimpiar');
  const contador = document.getElementById('planContador');

  let todos = [];

  async function cargar() {
    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="9">
        <div class="spinner-border spinner-border-sm me-2"
             style="color:var(--morado-600)"></div>Buscando...</td></tr>`;

    try {
      const r = await App.api('consultas/planes_listar.php', {
        abiertos: 1,
        q: filtro.value.trim(),
      });
      todos = r.datos;
      pintar();
    } catch (e) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="9">
          No se pudo cargar la lista.</td></tr>`;
    }
  }

  function pintar() {
    const lista = estado.value
      ? todos.filter((p) => String(p.id_estado_plan) === estado.value)
      : todos;

    contador.textContent = lista.length
      ? `${lista.length} plan${lista.length === 1 ? '' : 'es'}`
      : '';

    tabla.innerHTML = lista.length
      ? lista.map((p, i) => Planes.fila(p, i, null)).join('')
      : `<tr class="fila-vacia"><td colspan="9">
           <i class="bi bi-list-check fs-3 d-block mb-2 opacity-50"></i>
           No hay planes de tratamiento con ese criterio.</td></tr>`;
  }

  filtro.addEventListener('input', App.debounce(cargar, 350));
  estado.addEventListener('change', pintar);
  limpiar.addEventListener('click', () => { filtro.value = ''; cargar(); filtro.focus(); });

  cargar();
}

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

      // 3 = En sala. El paciente ya esta enfrente: es el momento de
      // abrir su consulta, no diez minutos despues cuando alguien se
      // acuerde y vuelva a teclear los mismos datos.
      if (nuevo === 3) await ofrecerConsulta(cita);

      // 5 = Cancelada. El hueco quedo libre: si alguien lo estaba
      // esperando, este es el unico momento en que sirve saberlo.
      if (nuevo === 5) await ofrecerListaEspera(cita);
    }
  });

  /** Al poner al paciente en sala, se ofrece abrir su consulta. */
  async function ofrecerConsulta(cita) {
    const ok = await App.alerta.confirmar(
      `${cita.nombre_completo} está en sala`,
      '¿Abres su consulta ahora? Queda ligada a esta cita y con sus datos '
      + 'ya puestos.',
      'Sí, abrir la consulta');
    if (!ok) return;

    App.ir(`index.php?modulo=consultas&vista=consulta`
      + `&paciente=${cita.id_registro_paciente}&cita=${cita.id_cita}`);
  }

  /**
   * Al cancelar, se revisa quien estaba esperando ese hueco.
   *
   * Es la funcion que mas dinero recupera de todas: una cita cancelada
   * que se vuelve a llenar el mismo dia es una silla que no se quedo
   * vacia.
   */
  async function ofrecerListaEspera(cita) {
    let candidatos = [];
    try {
      const r = await App.api('consultas/espera_listar.php', {
        inicio: cita.fecha_inicio,
        id_usuario_dentista: cita.id_usuario_dentista,
      });
      candidatos = r.datos;
    } catch (e) { return; }

    if (!candidatos.length) return;

    await Swal.fire({
      icon: 'info',
      title: 'Alguien estaba esperando este horario',
      html: `<div style="text-align:left;font-size:13px;line-height:1.7">
               Se liberó el
               <b>${App.esc(App.fechaHora(cita.fecha_inicio))}</b>.
               Están esperando:
               <div style="margin-top:.6rem">
                 ${candidatos.slice(0, 5).map((c) => `
                   <div style="border:1px solid #e6e0ea;border-radius:8px;
                               padding:.4rem .6rem;margin-bottom:.35rem">
                     <b>${App.esc(c.paciente)}</b>
                     ${c.telefono ? `<div style="font-size:11px;color:#6c6577">
                          ${App.esc(c.telefono)}</div>` : ''}
                     ${c.motivo ? `<div style="font-size:11px;color:#6c6577">
                          ${App.esc(c.motivo)}</div>` : ''}
                   </div>`).join('')}
               </div>
             </div>`,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#7b2d8e',
    });
  }

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

  // Los renglones viven en memoria y se pintan de nuevo en cada cambio.
  // Es mas simple que ir parchando celdas sueltas del HTML.
  let renglones = [];
  let pasos = null;

  /* -------------------------------------------------------------------
     Pintado
     ------------------------------------------------------------------- */
  function pintar() {
    if (!renglones.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="8">
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
          <input type="text" class="form-control campo-nota campo-nota-chica mt-1"
                 placeholder="Nota" value="${App.esc(r.notas ?? '')}"
                 maxlength="255">
        </td>
        <td>
          ${r.aplica_por_diente
            ? `<button type="button" class="btn-dientes ${r.no_aplica ? 'vacio' : ''}"
                       data-dientes="${i}">
                 <i class="bi bi-database-fill-gear"></i>
                 <span>${Dientes.etiqueta(r.dientes, r.no_aplica)}</span>
               </button>`
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
        <td class="col-acciones">
          <button type="button" class="btn-menu-fila text-danger" data-quitar="${i}"
                  title="Quitar"><i class="bi bi-x-lg"></i></button>
        </td>
      </tr>`).join('');

    totalizar();
  }

  /** Tabla de solo lectura del ultimo paso, para revisar antes de guardar. */
  function pintarResumen() {
    const caja = document.getElementById('planResumen');
    if (!caja) return;

    caja.innerHTML = renglones.length
      ? renglones.map((r, i) => `
          <tr>
            <td class="text-secondary">${i + 1}</td>
            <td>
              <div class="fw-semibold">${App.esc(r.nombre_procedimiento)}</div>
              ${r.notas ? `<div class="text-secondary" style="font-size:11px">
                             ${App.esc(r.notas)}</div>` : ''}
            </td>
            <td class="text-secondary" style="font-size:11.5px">
              ${Dientes.etiqueta(r.dientes, r.no_aplica)}
            </td>
            <td class="text-center">${r.cantidad}</td>
            <td class="text-end fw-semibold">${App.money(r.importe)}</td>
          </tr>`).join('')
      : `<tr class="fila-vacia"><td colspan="5">Sin procedimientos.</td></tr>`;
  }

  function totalizar() {
    const subtotal = renglones.reduce((s, r) => s + Number(r.importe), 0);
    const desc = Number(descuento.value) || 0;
    const total = Math.max(0, subtotal - desc);

    document.getElementById('planSubtotal').textContent = App.money(subtotal);
    document.getElementById('planTotal').textContent = App.money(total);
    pintarResumen();
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

    if (['campo-cant', 'campo-precio', 'campo-desc'].some((c) => el.classList.contains(c))) {
      recalcular(i);
      // Solo se actualiza la celda del importe y el total: repintar toda
      // la tabla haria perder el foco en cada tecla.
      fila.children[6].textContent = App.money(r.importe);
      totalizar();
    }
  });

  tabla.addEventListener('click', (ev) => {
    const quitar = ev.target.closest('[data-quitar]');
    if (quitar) {
      renglones.splice(Number(quitar.dataset.quitar), 1);
      pintar();
      return;
    }

    const abrir = ev.target.closest('[data-dientes]');
    if (!abrir) return;

    const i = Number(abrir.dataset.dientes);
    const r = renglones[i];

    Dientes.pedir({
      procedimiento: r.nombre_procedimiento,
      dientes: r.dientes,
      noAplica: r.no_aplica,
      alElegir({ dientes, noAplica }) {
        r.dientes = dientes;
        r.no_aplica = noAplica;
        // id_diente conserva el primero para el odontograma y los
        // reportes, que lo consultan por llave foranea.
        r.id_diente = dientes.length ? dientes[0] : null;
        pintar();
      },
    });
  });

  descuento.addEventListener('input', totalizar);

  /* -------------------------------------------------------------------
     Selector de procedimientos

     El modal es compartido con el cargo de la consulta: vive en
     SelectorProcedimiento y aqui solo se dice que hacer con lo elegido.
     ------------------------------------------------------------------- */
  document.getElementById('planAgregar').addEventListener('click', () => {
    SelectorProcedimiento.pedir((p) => {
      renglones.push({
        id_procedimiento: p.id_procedimiento,
        nombre_procedimiento: p.nombre_procedimiento,
        aplica_por_diente: Number(p.aplica_por_diente) === 1,
        requiere_anestesia: Number(p.requiere_anestesia) === 1,
        dientes: [],
        no_aplica: false,
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
    });
  });

  /* -------------------------------------------------------------------
     Pasos
     ------------------------------------------------------------------- */
  const idPlan = document.getElementById('planId').value;

  pasos = Pasos.crear({
    contenedor: 'pasosPlan',
    // Un plan que ya existe se abre para corregir una parte, no para
    // recorrerlo de nuevo desde el principio.
    modo: idPlan ? 'colapsable' : 'pasos',
    textoFinal: 'Guardar plan',
    alTerminar: () => form.requestSubmit(),

    validar(indice) {
      if (indice === 2 && !renglones.length) {
        return 'Agrega al menos un procedimiento antes de continuar.';
      }
      return true;
    },
  });

  document.getElementById('planImprimir')?.addEventListener('click', () => {
    const id = document.getElementById('planId').value;
    if (!id) return;
    App.abrirVentana(`${App.URL_BASE}/plan_imprimir.php?id=${id}`, 880, 1020, 'plan');
  });

  /* -------------------------------------------------------------------
     Carga de un plan existente
     ------------------------------------------------------------------- */
  if (idPlan) {
    (async () => {
      const r = await App.api('consultas/plan_obtener.php', {
        id_plan_tratamiento: idPlan,
      });
      const p = r.datos;

      ['descripcion', 'diagnostico', 'oclusion', 'traumatismos',
       'secuelas_traumatismos', 'observaciones', 'fecha'].forEach((c) => {
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
        // 'dientes' es la lista real; id_diente solo conserva el primero.
        dientes: (d.dientes ?? '').split(',').filter(Boolean).map(Number),
        no_aplica: Number(d.aplica_por_diente) === 1 && !d.dientes,
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

      // Se deja el id para que el boton de imprimir sepa cual es.
      document.getElementById('planId').value = r.datos.id_plan_tratamiento;
      document.getElementById('planImprimir').classList.remove('d-none');
      pasos?.aplicarModo('colapsable');

      const decision = await Swal.fire({
        icon: 'success',
        title: 'Plan guardado',
        html: `<div style="font-size:14px;line-height:1.6">
                 Total: <b>${App.money(r.datos.total)}</b>
                 ${r.datos.pendiente ? `
                   <div style="background:#fff6e5;border:1px solid #f0d9a8;
                               border-radius:9px;padding:.7rem .9rem;
                               color:#7a5a12;margin-top:.9rem;font-size:12.5px">
                     Imprímelo, recoge la firma del tutor y súbelo escaneado
                     desde el <b>Expediente electrónico</b>.
                   </div>` : ''}
               </div>`,
        showCancelButton: true,
        confirmButtonText: 'Imprimir ahora',
        confirmButtonColor: '#7b2d8e',
        cancelButtonText: 'Ir al expediente',
        cancelButtonColor: '#6c6577',
        reverseButtons: true,
      });

      if (decision.isConfirmed) {
        App.abrirVentana(
          `${App.URL_BASE}/plan_imprimir.php?id=${r.datos.id_plan_tratamiento}`,
          880, 1020, 'plan');
        return;
      }

      App.ir(`index.php?modulo=paciente&vista=detalle_paciente`
        + `&id=${form.querySelector('[name="id_registro_paciente"]').value}#tab-planes`);
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });
}

/* =====================================================================
   Consultas > Lista de consultas
   ===================================================================== */
function listadoConsultas() {
  const tabla = document.getElementById('tablaConsultas');
  if (!tabla) return;

  const filtro   = document.getElementById('cnFiltro');
  const dentista = document.getElementById('cnDentista');
  const estatus  = document.getElementById('cnEstatus');
  const limpiar  = document.getElementById('cnFiltroLimpiar');
  const contador = document.getElementById('cnContador');

  let todas = [];

  async function cargar() {
    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="7">
        <div class="spinner-border spinner-border-sm me-2"
             style="color:var(--morado-600)"></div>Buscando...</td></tr>`;

    try {
      const r = await App.api('consultas/consultas_listar.php', {
        q: filtro.value.trim(),
        id_usuario_dentista: dentista ? dentista.value : '',
      });
      todas = r.datos.consultas;
      pintar();
    } catch (e) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="7">
          No se pudo cargar la lista.</td></tr>`;
    }
  }

  function pintar() {
    // El estatus se filtra aqui: la lista ya vino acotada y volver al
    // servidor por un si/no seria un viaje de mas.
    const lista = estatus.value === ''
      ? todas
      : todas.filter((c) => String(c.cerrada) === estatus.value);

    contador.textContent = lista.length
      ? `${lista.length} consulta${lista.length === 1 ? '' : 's'}`
      : '';

    if (!lista.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="7">
          <i class="bi bi-clipboard2-pulse fs-3 d-block mb-2 opacity-50"></i>
          No hay consultas con ese criterio.</td></tr>`;
      return;
    }

    tabla.innerHTML = lista.map((c, i) => {
      const detalle = `${App.URL_BASE}/index.php?modulo=consultas`
        + `&vista=consulta&id=${c.id_consulta}`;
      const cerrada = Number(c.cerrada) === 1;

      return `
      <tr>
        <td class="text-secondary">${i + 1}</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <a href="${detalle}" class="icono-plan" title="Abrir la consulta">
              <i class="bi bi-clipboard2-pulse"></i>
            </a>
            <a href="${detalle}" class="enlace-paciente">${App.esc(c.folio)}</a>
          </div>
        </td>
        <td>
          <div>${App.esc(App.fechaHora(c.fecha_hora))}</div>
          <div class="text-secondary" style="font-size:11px">
            ${App.esc(c.dentista)}
          </div>
        </td>
        <td>
          <a class="enlace-paciente"
             href="${App.URL_BASE}/index.php?modulo=paciente&vista=detalle_paciente&id=${c.id_registro_paciente}">
            ${App.esc(c.paciente)}
          </a>
          <div class="text-secondary" style="font-size:11px">
            ${App.esc(c.num_expediente)}
            ${c.motivo ? ' · ' + App.esc(c.motivo.substring(0, 40)) : ''}
          </div>
        </td>
        <td>
          ${c.plan_folio ? `
            <a class="enlace-paciente"
               href="${App.URL_BASE}/index.php?modulo=consultas&vista=nuevo_plan_tratamiento&id=${c.id_registro_paciente}&plan=${c.id_plan_tratamiento}">
              ${App.esc(c.plan_folio)}
            </a>
            <div class="text-secondary" style="font-size:11px">
              ${App.esc(c.plan_descripcion ?? '')}
            </div>`
          : '<span class="text-secondary opacity-50" style="font-size:12.5px">Consulta general</span>'}
        </td>
        <td>
          <span class="chip-estado ${cerrada ? 'badge-suave' : 'badge-pendiente'}">
            ${cerrada ? 'CERRADA' : 'ABIERTA'}
          </span>
          ${Number(c.pendientes) > 0
            ? `<div style="font-size:10.5px;color:var(--alerta)">
                 ${c.pendientes} por escanear</div>` : ''}
        </td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="${detalle}">
                <i class="bi bi-eye me-2"></i>${cerrada ? 'Ver' : 'Ver o modificar'}</a></li>
              <li><a class="dropdown-item"
                     href="${App.URL_BASE}/index.php?modulo=paciente&vista=detalle_paciente&id=${c.id_registro_paciente}#tab-expediente">
                <i class="bi bi-folder2-open me-2"></i>Expediente del paciente</a></li>
            </ul>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  filtro.addEventListener('input', App.debounce(cargar, 350));
  dentista?.addEventListener('change', cargar);
  estatus.addEventListener('change', pintar);
  limpiar.addEventListener('click', () => { filtro.value = ''; cargar(); filtro.focus(); });

  cargar();
}

/* =====================================================================
   Consultas > Detalle de consulta
   ===================================================================== */
function detalleConsulta() {
  const form = document.getElementById('formConsulta');
  if (!form) return;

  const campoId = document.getElementById('cnId');
  const btnCerrar = document.getElementById('cnCerrar');

  const modalRx    = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalReceta'));
  const modalNota  = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalNota'));
  const modalAlbum = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalAlbum'));

  let datos    = null;   // la consulta ya cargada
  let tipos    = [];     // tipos de documento de ambito CONSULTA
  let archivos = [];     // seleccion actual del album

  const idConsulta = () => Number(campoId.value) || 0;

  /* -------------------------------------------------------------------
     Carga
     ------------------------------------------------------------------- */
  async function cargar() {
    if (!idConsulta()) { habilitarAnexos(false); return; }

    try {
      const r = await App.api('consultas/consulta_obtener.php', {
        id_consulta: idConsulta(),
      });
      datos = r.datos;
      tipos = datos.tipos ?? [];
      llenar();
    } catch (e) { /* ya se mostro */ }
  }

  function llenar() {
    ['motivo', 'padecimiento_actual', 'exploracion', 'diagnostico',
     'plan_indicaciones', 'presion_arterial', 'frecuencia_cardiaca',
     'temperatura'].forEach((c) => {
      const el = form.querySelector(`[name="${c}"]`);
      if (el && datos[c] != null) el.value = datos[c];
    });

    form.querySelector('[name="id_usuario_dentista"]').value = datos.id_usuario_dentista;
    document.getElementById('cnPlan').value = datos.id_plan_tratamiento ?? '';

    // El datetime-local quiere "2026-09-19T10:30", no el DATETIME de MySQL.
    document.getElementById('cnFechaHora').value =
      String(datos.fecha_hora).replace(' ', 'T').substring(0, 16);

    document.getElementById('cnTituloFolio').textContent = `Consulta ${datos.folio}`;

    const cerrada = Number(datos.cerrada) === 1;
    const chip = document.getElementById('cnEstadoChip');
    chip.classList.remove('d-none', 'badge-suave', 'badge-pendiente');
    chip.classList.add(cerrada ? 'badge-suave' : 'badge-pendiente');
    chip.textContent = cerrada ? 'CERRADA' : 'ABIERTA';

    bloquear(cerrada);
    habilitarAnexos(true);

    pintarRecetas();
    pintarDocumentos();
    pintarNotas();
    cargarCargos();
  }

  /**
   * Una consulta cerrada bloquea lo clinico y deja viva la columna de
   * anexos: el registro no se corrige, pero se le siguen agregando
   * papeles y notas.
   */
  function bloquear(cerrada) {
    form.querySelectorAll('input, textarea, select')
      .forEach((el) => { if (el.type !== 'hidden') el.disabled = cerrada; });

    document.getElementById('cnGuardar').disabled = cerrada;
    btnCerrar.disabled = cerrada;
    document.getElementById('cnAvisoCerrada').classList.toggle('d-none', !cerrada);
  }

  function habilitarAnexos(si) {
    ['cnNuevaReceta', 'cnNuevaNota', 'cnNuevoCargo'].forEach((id) => {
      const b = document.getElementById(id);
      if (b) b.disabled = !si;
    });
    if (!document.getElementById('cnGuardar').disabled) btnCerrar.disabled = !si;
    document.getElementById('cnAvisoGuardar').classList.toggle('d-none', si);
  }

  /* -------------------------------------------------------------------
     Guardado
     ------------------------------------------------------------------- */
  document.getElementById('cnGuardar')?.addEventListener('click', async () => {
    if (!form.reportValidity()) return;

    try {
      const r = await App.api('consultas/consulta_guardar.php',
        App.formADatos(form));

      const eraNueva = !idConsulta();
      campoId.value = r.datos.id_consulta;

      App.alerta.toast(eraNueva ? 'Consulta registrada' : 'Consulta actualizada');

      // Con la consulta ya creada se desbloquean los anexos y, si era
      // nueva, la direccion cambia para que recargar no la duplique.
      if (eraNueva) {
        history.replaceState(null, '',
          `${App.URL_BASE}/index.php?modulo=consultas&vista=consulta&id=${r.datos.id_consulta}`);
      }
      cargar();
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });

  btnCerrar?.addEventListener('click', async () => {
    const ok = await App.alerta.confirmar(
      '¿Cerrar la consulta?',
      'Una vez cerrada ya no se podrán modificar los datos clínicos. '
      + 'Podrás seguir agregando notas de evolución y documentos.',
      'Sí, cerrar');
    if (!ok) return;

    try {
      await App.api('consultas/consulta_cerrar.php', { id_consulta: idConsulta() });
      App.alerta.toast('Consulta cerrada');
      cargar();
    } catch (e) { /* ya se mostro */ }
  });

  /* -------------------------------------------------------------------
     Pendientes de pago

     Lo que salio en la consulta sin estar planeado. Se registra aqui,
     con el paciente enfrente, porque si se deja para la salida nadie
     se acuerda de que se hizo de mas.
     ------------------------------------------------------------------- */
  const modalCargo = document.getElementById('modalCargo')
    ? bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCargo'))
    : null;

  let cargos = [];

  async function cargarCargos() {
    if (!document.getElementById('cnCargos') || !idConsulta()) return;

    try {
      const r = await App.api('cajas/cargos_listar.php', { id_consulta: idConsulta() });
      cargos = r.datos;
      pintarCargos();
    } catch (e) { /* ya se mostro */ }
  }

  function pintarCargos() {
    const caja = document.getElementById('cnCargos');
    if (!caja) return;

    if (!cargos.length) {
      caja.innerHTML = `<div class="exp-vacio">
          Sin cargos. Agrega aquí lo que se hizo y no venía en el plan.
        </div>`;
      return;
    }

    const total  = cargos.reduce((t, c) => t + Number(c.importe), 0);
    const debido = cargos.reduce((t, c) => t + Number(c.saldo), 0);

    caja.innerHTML = cargos.map((c) => {
      const pagado = Number(c.saldo) <= 0;
      return `
      <div class="anexo">
        <div class="flex-grow-1" style="min-width:0">
          <div class="fw-semibold">${App.esc(c.descripcion)}</div>
          <div class="text-secondary" style="font-size:11px">
            ${c.cantidad > 1 ? c.cantidad + ' × ' + App.money(c.precio_unitario) : ''}
            ${Number(c.descuento) > 0
              ? ` · desc. ${App.money(c.descuento)}`
                + (c.autorizo_descuento ? ` (${App.esc(c.autorizo_descuento)})` : '')
              : ''}
          </div>
        </div>
        <div class="text-end">
          <div class="fw-semibold">${App.money(c.importe)}</div>
          <span class="chip-estado ${pagado ? 'badge-suave' : 'badge-pendiente'}">
            ${pagado ? 'PAGADO' : 'DEBE ' + App.money(c.saldo)}
          </span>
        </div>
        ${!pagado && Number(c.aplicado) === 0 ? `
          <button type="button" class="btn btn-sm btn-link text-danger p-0"
                  data-cargo-quitar="${c.id_cargo}" title="Cancelar">
            <i class="bi bi-x-lg"></i>
          </button>` : ''}
      </div>`;
    }).join('')
    + `<div class="d-flex justify-content-between pt-2 mt-1"
            style="border-top:2px solid var(--gris-100)">
         <span class="text-secondary">Total ${App.money(total)}</span>
         <span class="fw-semibold" style="color:${debido > 0 ? 'var(--error)' : 'var(--exito)'}">
           ${debido > 0 ? 'Debe ' + App.money(debido) : 'Liquidado'}
         </span>
       </div>`;
  }

  document.getElementById('cnNuevoCargo')?.addEventListener('click', () => {
    const form = document.getElementById('formCargo');
    form.reset();
    document.getElementById('cgConsulta').value = idConsulta();
    document.getElementById('cgPaciente').value = datos.id_registro_paciente;
    document.getElementById('cgProcedimiento').value = '';
    document.getElementById('cgProcTexto').value = '';
    document.getElementById('cgImporte').value = App.money(0);
    modalCargo.show();
  });

  document.getElementById('cgBuscarProc')?.addEventListener('click', () => {
    SelectorProcedimiento.pedir((p) => {
      document.getElementById('cgProcedimiento').value = p.id_procedimiento;
      document.getElementById('cgProcTexto').value = p.nombre_procedimiento;
      document.getElementById('cgDescripcion').value =
        p.nombre_procedimiento.toUpperCase();
      document.getElementById('cgPrecio').value = Number(p.costo_sugerido).toFixed(2);
      recalcularCargo();
    });
  });

  document.getElementById('cgQuitarProc')?.addEventListener('click', () => {
    document.getElementById('cgProcedimiento').value = '';
    document.getElementById('cgProcTexto').value = '';
  });

  function recalcularCargo() {
    const cant   = Number(document.getElementById('cgCantidad').value) || 1;
    const precio = Number(document.getElementById('cgPrecio').value) || 0;
    const desc   = Number(document.getElementById('cgDescuento')?.value) || 0;
    document.getElementById('cgImporte').value =
      App.money(Math.max(0, precio * cant - desc));
  }

  ['cgCantidad', 'cgPrecio', 'cgDescuento'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', recalcularCargo);
  });

  document.getElementById('formCargo')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await App.api('cajas/cargo_guardar.php', App.formADatos(ev.target));
      modalCargo.hide();
      App.alerta.toast('Cargo registrado');
      cargarCargos();
    } catch (e) {
      App.marcarErrores(ev.target, e.errores);
    }
  });

  document.getElementById('cnCargos')?.addEventListener('click', async (ev) => {
    const boton = ev.target.closest('[data-cargo-quitar]');
    if (!boton) return;

    const motivo = await App.alerta.pedirTexto(
      '¿Cancelar este cargo?',
      'Escribe por qué se cancela. Queda registrado, no se borra.');
    if (!motivo) return;

    try {
      await App.api('cajas/cargo_cancelar.php', {
        id_cargo: boton.dataset.cargoQuitar,
        motivo,
      });
      App.alerta.toast('Cargo cancelado');
      cargarCargos();
    } catch (e) { /* ya se mostro */ }
  });

  /* -------------------------------------------------------------------
     Recetas
     ------------------------------------------------------------------- */
  function pintarRecetas() {
    const caja = document.getElementById('cnRecetas');

    caja.innerHTML = datos.recetas.length
      ? datos.recetas.map((r) => `
          <div class="anexo">
            <div class="flex-grow-1" style="min-width:0">
              <div class="fw-semibold">${App.esc(r.folio)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(App.fecha(r.fecha))} · ${App.esc(r.dentista)}
              </div>
              <div class="anexo-texto">${App.esc(r.indicaciones)}</div>
            </div>
            <div class="d-flex flex-column gap-1">
              <button type="button" class="btn btn-sm btn-outline-morado"
                      data-rx-imprimir="${r.id_receta}" title="Imprimir">
                <i class="bi bi-printer"></i>
              </button>
              <button type="button" class="btn btn-sm btn-light"
                      data-rx-editar="${r.id_receta}" title="Modificar">
                <i class="bi bi-pencil"></i>
              </button>
            </div>
          </div>`).join('')
      : '<div class="exp-vacio">Sin recetas.</div>';
  }

  document.getElementById('cnNuevaReceta')?.addEventListener('click', () => abrirReceta(null));

  document.getElementById('cnRecetas').addEventListener('click', (ev) => {
    const imprimir = ev.target.closest('[data-rx-imprimir]');
    if (imprimir) {
      App.abrirVentana(
        `${App.URL_BASE}/receta_imprimir.php?id=${imprimir.dataset.rxImprimir}`,
        860, 620, 'receta');
      return;
    }

    const editar = ev.target.closest('[data-rx-editar]');
    if (editar) {
      abrirReceta(datos.recetas.find(
        (r) => String(r.id_receta) === editar.dataset.rxEditar));
    }
  });

  function abrirReceta(receta) {
    document.getElementById('rxConsulta').value = idConsulta();
    document.getElementById('rxId').value = receta ? receta.id_receta : '';
    document.getElementById('rxTitulo').textContent =
      receta ? `Receta ${receta.folio}` : 'Nueva receta';

    // Prellenado: paciente, edad y fecha salen de la consulta.
    document.getElementById('rxPaciente').textContent = datos.paciente;
    document.getElementById('rxEdad').textContent =
      datos.edad != null ? `${datos.edad} años` : '—';
    document.getElementById('rxFecha').value =
      receta ? receta.fecha : String(datos.fecha_hora).substring(0, 10);
    document.getElementById('rxIndicaciones').value =
      receta ? receta.indicaciones : '';

    modalRx.show();
  }

  document.getElementById('formReceta')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      const r = await App.api('consultas/receta_guardar.php',
        App.formADatos(ev.target));
      modalRx.hide();
      await cargar();

      const imprimir = await App.alerta.confirmar(
        `Receta ${r.datos.folio} guardada`,
        '¿La imprimes ahora? Sale solo el texto, para calzarlo en el recetario.',
        'Sí, imprimir');
      if (imprimir) {
        App.abrirVentana(
          `${App.URL_BASE}/receta_imprimir.php?id=${r.datos.id_receta}`,
          860, 620, 'receta');
      }
    } catch (e) {
      App.marcarErrores(ev.target, e.errores);
    }
  });

  /* -------------------------------------------------------------------
     Notas de evolucion
     ------------------------------------------------------------------- */
  function pintarNotas() {
    const caja = document.getElementById('cnNotas');

    caja.innerHTML = datos.notas.length
      ? datos.notas.map((n) => `
          <div class="anexo">
            <div style="min-width:0">
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(App.fechaHora(n.fecha_registro))} · ${App.esc(n.autor)}
              </div>
              <div class="anexo-texto">${App.esc(n.texto)}</div>
            </div>
          </div>`).join('')
      : `<div class="exp-vacio">
           Sin notas. No generan pendiente de digitalización.
         </div>`;
  }

  document.getElementById('cnNuevaNota')?.addEventListener('click', () => {
    document.getElementById('notaConsulta').value = idConsulta();
    document.getElementById('notaTexto').value = '';
    modalNota.show();
  });

  document.getElementById('formNota')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await App.api('consultas/nota_guardar.php', App.formADatos(ev.target));
      modalNota.hide();
      App.alerta.toast('Nota agregada');
      cargar();
    } catch (e) {
      App.marcarErrores(ev.target, e.errores);
    }
  });

  /* -------------------------------------------------------------------
     Documentos
     ------------------------------------------------------------------- */
  function pintarDocumentos() {
    const caja = document.getElementById('cnDocumentos');

    caja.innerHTML = tipos.map((t) => {
      const suyos = datos.documentos.filter((d) => d.clave === t.clave);

      return `
      <div class="exp-grupo">
        <div class="exp-grupo-titulo">
          <i class="bi ${App.esc(t.icono)}"></i>
          <span>${App.esc(t.nombre_documento)}</span>
          ${suyos.length ? `<span class="cuenta">${suyos.length}</span>` : ''}
          <button type="button" class="btn btn-sm btn-link p-0 ms-auto"
                  data-album="${App.esc(t.clave)}">
            <i class="bi bi-plus-lg"></i> Agregar
          </button>
        </div>
        ${suyos.length ? `
          <div class="anexo-archivos">
            ${suyos.map((d) => Number(d.estado) === 0 ? `
              <span class="anexo-chip pendiente" title="${App.esc(d.descripcion ?? '')}">
                <i class="bi bi-hourglass-split"></i> Por escanear
              </span>` : `
              <a class="anexo-chip" href="${App.URL_BASE}/archivo.php?id=${d.id_documento}"
                 target="_blank" title="${App.esc(d.nombre_original ?? '')}">
                <i class="bi bi-eye"></i> ${App.esc(d.descripcion ?? 'Ver')}
              </a>`).join('')}
          </div>` : '<div class="exp-vacio">Sin archivos.</div>'}
      </div>`;
    }).join('');
  }

  document.getElementById('cnDocumentos').addEventListener('click', (ev) => {
    const boton = ev.target.closest('[data-album]');
    if (!boton) return;

    const tipo = tipos.find((t) => t.clave === boton.dataset.album);
    if (!tipo) return;

    document.getElementById('albumClave').value = tipo.clave;
    document.getElementById('albumConsulta').value = idConsulta();
    document.getElementById('albumTitulo').textContent = tipo.nombre_documento;
    document.getElementById('albumDescripcion').value = '';
    document.getElementById('albumAyuda').textContent =
      `Formatos: ${(tipo.formatos || '').toUpperCase()}. Máximo 20 MB por archivo.`;
    document.getElementById('albumArchivos').setAttribute('accept',
      (tipo.formatos || '').split(',').map((f) => '.' + f.trim()).join(','));

    limpiarAlbum();
    modalAlbum.show();
  });

  function limpiarAlbum() {
    archivos = [];
    document.getElementById('albumArchivos').value = '';
    document.getElementById('albumPrevio').innerHTML = '';
    document.getElementById('albumBoton').disabled = true;
    document.getElementById('albumBotonTexto').textContent = 'Guardar';
  }

  function elegirArchivos(lista) {
    // Se revisa el tamanio aqui para no mandar 60 MB y esperar el error
    // del servidor.
    const grandes = [...lista].filter((a) => a.size > 20 * 1024 * 1024);
    if (grandes.length) {
      App.alerta.error('Hay archivos muy grandes',
        `${grandes.length} archivo(s) pasan de 20 MB. Vuelve a escanearlos `
        + 'con menor resolución.');
      return;
    }

    archivos = [...lista];
    document.getElementById('albumBoton').disabled = archivos.length === 0;
    document.getElementById('albumBotonTexto').textContent =
      `Guardar ${archivos.length} archivo${archivos.length === 1 ? '' : 's'}`;

    document.getElementById('albumPrevio').innerHTML = archivos.map((a) => `
      <div class="album-item">
        ${a.type.startsWith('image/')
          ? `<img src="${URL.createObjectURL(a)}" alt="">`
          : '<i class="bi bi-file-earmark-pdf"></i>'}
        <span>${App.esc(a.name)}</span>
      </div>`).join('');
  }

  const zona  = document.getElementById('albumZona');
  const campo = document.getElementById('albumArchivos');

  zona.addEventListener('click', () => campo.click());
  campo.addEventListener('change', () => elegirArchivos(campo.files));

  ['dragenter', 'dragover'].forEach((e) => {
    zona.addEventListener(e, (ev) => { ev.preventDefault(); zona.classList.add('encima'); });
  });
  ['dragleave', 'drop'].forEach((e) => {
    zona.addEventListener(e, (ev) => { ev.preventDefault(); zona.classList.remove('encima'); });
  });
  zona.addEventListener('drop', (ev) => {
    const dt = new DataTransfer();
    [...ev.dataTransfer.files].forEach((f) => dt.items.add(f));
    campo.files = dt.files;
    elegirArchivos(dt.files);
  });

  document.getElementById('formAlbum')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (!archivos.length) return;

    const cuerpo = new FormData();
    cuerpo.append('clave', document.getElementById('albumClave').value);
    cuerpo.append('id_consulta', idConsulta());
    cuerpo.append('id_registro_paciente', datos.id_registro_paciente);
    cuerpo.append('descripcion', document.getElementById('albumDescripcion').value);
    archivos.forEach((a) => cuerpo.append('archivos[]', a));

    App.alerta.cargando(`Guardando ${archivos.length} archivo(s)...`);
    try {
      const r = await App.api('expediente/subir_album.php', cuerpo);
      App.alerta.cerrar();
      modalAlbum.hide();
      App.alerta.toast(r.mensaje);
      cargar();
    } catch (e) {
      App.alerta.cerrar();
    }
  });

  cargar();
}

/* =====================================================================
   Agenda: vista de semana
   ===================================================================== */
function vistaSemana() {
  const caja = document.getElementById('smRejilla');
  if (!caja) return;

  const selDentista = document.getElementById('smDentista');
  const campoLunes  = document.getElementById('smLunes');

  let datos = null;

  /** El lunes de la semana a la que pertenece una fecha. */
  function lunesDe(iso) {
    const d = new Date(iso + 'T00:00:00');
    // getDay() da 0 para domingo; se corre para que la semana empiece
    // en lunes, que es como trabaja el consultorio.
    const desplazar = (d.getDay() + 6) % 7;
    d.setDate(d.getDate() - desplazar);
    return d.toISOString().substring(0, 10);
  }

  campoLunes.value = lunesDe(new Date().toISOString().substring(0, 10));

  async function cargar() {
    caja.innerHTML = `<div class="cargando">
        <div class="spinner-border spinner-border-sm"
             style="color:var(--morado-600)"></div>Cargando la semana...</div>`;

    try {
      const r = await App.api('consultas/semana.php', {
        id_usuario_dentista: selDentista.value,
        lunes: campoLunes.value,
      });
      datos = r.datos;
      campoLunes.value = datos.lunes;
      pintar();
      pintarBloqueos();
    } catch (e) {
      caja.innerHTML = '<div class="alert alert-danger m-3">No se pudo cargar la semana.</div>';
    }
  }

  function pintar() {
    const dias = datos.dias;

    document.getElementById('smSubtitulo').textContent =
      `${App.fecha(datos.lunes)} al ${App.fecha(datos.sabado)}`;
    document.getElementById('smResumen').innerHTML =
      `${datos.citas} cita(s) esta semana`;

    let html = '<table class="semana"><thead><tr><th class="hora"></th>';

    dias.forEach((d) => {
      html += `<th class="${d.hoy ? 'hoy' : ''}">
                 <div class="nombre">${App.esc(d.nombre)}</div>
                 <div class="dia">${d.dia}</div>
               </th>`;
    });
    html += '</tr></thead><tbody>';

    // Las celdas que una cita larga ya ocupo se saltan: la tarjeta se
    // estira con rowspan y no debe haber celda debajo.
    const saltar = {};

    datos.rejilla.forEach((fila, f) => {
      html += `<tr><td class="hora">${App.esc(fila.hora)}</td>`;

      fila.celdas.forEach((celda, c) => {
        if (saltar[`${f}-${c}`]) return;

        if (celda.tipo === 'cita') {
          const ci = celda.cita;
          for (let k = 1; k < celda.largo; k++) saltar[`${f + k}-${c}`] = true;

          html += `
          <td rowspan="${celda.largo}" class="con-cita">
            <div class="cita-bloque" draggable="true"
                 data-cita="${ci.id_cita}"
                 style="--color:${App.esc(ci.color ?? '#9333a8')}">
              <div class="hora">${App.esc(String(ci.fecha_inicio).substring(11, 16))}</div>
              <div class="paciente">${App.esc(ci.paciente)}</div>
              <div class="motivo">
                ${App.esc(ci.nombre_procedimiento ?? ci.motivo ?? '')}
              </div>
              <span class="estado">${App.esc(ci.nombre_estado)}</span>
            </div>
          </td>`;
          return;
        }

        if (celda.tipo === 'bloqueo') {
          html += `<td class="bloqueado" title="${App.esc(celda.motivo)}">
                     <span>${App.esc(celda.motivo)}</span></td>`;
          return;
        }

        if (celda.tipo === 'cerrado') { html += '<td class="cerrado"></td>'; return; }
        if (celda.tipo === 'sigue')   { return; }
        if (celda.tipo === 'paso')    { html += '<td class="paso"></td>'; return; }

        html += `<td class="libre" data-fecha="${celda.fecha}"
                     data-hora="${celda.hora}"></td>`;
      });

      html += '</tr>';
    });

    caja.innerHTML = html + '</tbody></table>';
  }

  function pintarBloqueos() {
    const lista = document.getElementById('smBloqueos');

    lista.innerHTML = datos.bloqueos.length
      ? datos.bloqueos.map((b) => `
          <div class="anexo">
            <div class="flex-grow-1" style="min-width:0">
              <div class="fw-semibold">${App.esc(b.motivo)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(App.fechaHora(b.fecha_inicio))}
                a ${App.esc(App.fechaHora(b.fecha_fin))}
              </div>
              <div class="text-secondary" style="font-size:11px">
                ${b.dentista ? App.esc(b.dentista) : 'Todo el consultorio'}
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-link text-danger p-0"
                    data-quitar-bloqueo="${b.id_bloqueo}" title="Quitar">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>`).join('')
      : '<div class="exp-vacio">Sin bloqueos esta semana.</div>';
  }

  document.getElementById('smBloqueos').addEventListener('click', async (ev) => {
    const boton = ev.target.closest('[data-quitar-bloqueo]');
    if (!boton) return;

    const ok = await App.alerta.confirmar('¿Quitar este bloqueo?',
      'Los horarios volverán a quedar disponibles.', 'Sí, quitar');
    if (!ok) return;

    await App.api('consultas/bloqueo_quitar.php', {
      id_bloqueo: boton.dataset.quitarBloqueo,
    });
    App.alerta.toast('Bloqueo quitado');
    cargar();
  });

  /* -------------------------------------------------------------------
     Reagendar arrastrando

     Se pide confirmacion siempre: arrastrar sin querer es facil, y
     mover una cita sin avisar es de las cosas que mas molestan a un
     paciente.
     ------------------------------------------------------------------- */
  let arrastrando = null;

  caja.addEventListener('dragstart', (ev) => {
    const bloque = ev.target.closest('[data-cita]');
    if (!bloque) return;

    arrastrando = Number(bloque.dataset.cita);
    bloque.classList.add('arrastrando');
    ev.dataTransfer.effectAllowed = 'move';
  });

  caja.addEventListener('dragend', (ev) => {
    ev.target.closest('[data-cita]')?.classList.remove('arrastrando');
    caja.querySelectorAll('.libre.encima')
      .forEach((c) => c.classList.remove('encima'));
    arrastrando = null;
  });

  caja.addEventListener('dragover', (ev) => {
    const celda = ev.target.closest('td.libre');
    if (!celda || !arrastrando) return;
    ev.preventDefault();
    celda.classList.add('encima');
  });

  caja.addEventListener('dragleave', (ev) => {
    ev.target.closest('td.libre')?.classList.remove('encima');
  });

  caja.addEventListener('drop', async (ev) => {
    const celda = ev.target.closest('td.libre');
    if (!celda || !arrastrando) return;

    ev.preventDefault();
    celda.classList.remove('encima');

    const cita = buscarCita(arrastrando);
    const id = arrastrando;
    arrastrando = null;

    const ok = await App.alerta.confirmar(
      'Reagendar la cita',
      `${cita?.paciente ?? 'La cita'} pasaría al `
      + `${App.fecha(celda.dataset.fecha)} a las ${celda.dataset.hora}. `
      + 'La cita vuelve a quedar pendiente de confirmar y hay que avisarle.',
      'Sí, reagendar');
    if (!ok) return;

    // La duracion se conserva: mover una cita no debe acortarla.
    const minutos = cita
      ? Math.round((new Date(String(cita.fecha_fin).replace(' ', 'T'))
          - new Date(String(cita.fecha_inicio).replace(' ', 'T'))) / 60000)
      : 30;

    try {
      await App.api('consultas/cita_guardar.php', {
        id_cita: id,
        id_registro_paciente: cita.id_registro_paciente,
        id_usuario_dentista: selDentista.value,
        fecha: celda.dataset.fecha,
        hora: celda.dataset.hora,
        duracion_min: minutos,
        motivo: cita.motivo ?? '',
      });
      App.alerta.toast('Cita reagendada');
      cargar();
    } catch (e) {
      App.alerta.error('No se pudo reagendar', e.mensaje ?? '');
      cargar();
    }
  });

  function buscarCita(id) {
    for (const fila of datos.rejilla) {
      for (const celda of fila.celdas) {
        if (celda.tipo === 'cita' && Number(celda.cita.id_cita) === id) {
          return celda.cita;
        }
      }
    }
    return null;
  }

  /* -------------------------------------------------------------------
     Navegacion
     ------------------------------------------------------------------- */
  function correr(semanas) {
    const d = new Date(campoLunes.value + 'T00:00:00');
    d.setDate(d.getDate() + semanas * 7);
    campoLunes.value = d.toISOString().substring(0, 10);
    cargar();
  }

  document.getElementById('smAntes').addEventListener('click', () => correr(-1));
  document.getElementById('smDespues').addEventListener('click', () => correr(1));
  document.getElementById('smEstaSemana').addEventListener('click', () => {
    campoLunes.value = lunesDe(new Date().toISOString().substring(0, 10));
    cargar();
  });
  campoLunes.addEventListener('change', cargar);
  selDentista.addEventListener('change', cargar);

  /* -------------------------------------------------------------------
     Bloqueos y lista de espera
     ------------------------------------------------------------------- */
  bloqueoYEspera(cargar, () => selDentista.value);

  cargar();
}

/* =====================================================================
   Bloqueos y lista de espera

   Lo usan la vista de semana y podria usarlo la del dia. Recibe que
   hacer al terminar y de donde sacar el dentista elegido, para no
   saber nada de la pantalla que lo llama.
   ===================================================================== */
function bloqueoYEspera(alCambiar, dentistaActual) {
  const cajaBloqueo = document.getElementById('modalBloqueo');
  if (!cajaBloqueo) return;

  const modalBloqueo = bootstrap.Modal.getOrCreateInstance(cajaBloqueo);
  const modalEspera  = bootstrap.Modal.getOrCreateInstance(
    document.getElementById('modalEspera'));

  /* ---------- Bloquear ---------- */
  document.getElementById('smBloquear')?.addEventListener('click', () => {
    const form = document.getElementById('formBloqueo');
    form.reset();
    document.getElementById('bqForzar').value = '0';
    document.getElementById('bqChoques').classList.add('d-none');
    document.getElementById('bqGuardar').textContent = 'Bloquear';

    // Se propone desde hoy a las nueve hasta hoy a las dos: el caso
    // mas comun es cerrar una maniana.
    const hoy = new Date().toISOString().substring(0, 10);
    document.getElementById('bqInicio').value = `${hoy}T09:00`;
    document.getElementById('bqFin').value    = `${hoy}T14:00`;

    if (dentistaActual?.()) {
      document.getElementById('bqUsuario').value = dentistaActual();
    }

    modalBloqueo.show();
  });

  document.getElementById('formBloqueo')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const form = ev.target;

    try {
      const r = await App.api('consultas/bloqueo_guardar.php', App.formADatos(form));

      // Primera pasada: hay citas dentro y todavia no se guardo nada.
      if (r.datos.confirmar) {
        document.getElementById('bqChoques').classList.remove('d-none');
        document.getElementById('bqChoquesLista').innerHTML =
          r.datos.citas.map((c) => `
            <div>· ${App.esc(App.fechaHora(c.fecha_inicio))} —
                 <b>${App.esc(c.paciente)}</b></div>`).join('');

        document.getElementById('bqForzar').value = '1';
        document.getElementById('bqGuardar').textContent = 'Bloquear de todas formas';
        return;
      }

      modalBloqueo.hide();
      App.alerta.toast(r.mensaje);
      alCambiar?.();
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });

  /* ---------- Lista de espera ---------- */
  async function cargarEspera() {
    const caja = document.getElementById('smEspera');
    if (!caja) return;

    const r = await App.api('consultas/espera_listar.php', {});

    caja.innerHTML = r.datos.length
      ? r.datos.map((e) => `
          <div class="anexo">
            <div class="flex-grow-1" style="min-width:0">
              <div class="fw-semibold">${App.esc(e.paciente)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${e.telefono ? App.esc(e.telefono) + ' · ' : ''}
                ${['', 'Mañana', 'Tarde', 'Cualquier horario'][e.preferencia_horario]}
              </div>
              ${e.motivo ? `<div class="anexo-texto">${App.esc(e.motivo)}</div>` : ''}
              <div class="text-secondary" style="font-size:10.5px">
                Desde ${App.esc(App.fecha(e.fecha_registro))}
                ${e.dentista ? ' · ' + App.esc(e.dentista) : ''}
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-link text-danger p-0"
                    data-quitar-espera="${e.id_espera}" title="Quitar">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>`).join('')
      : `<div class="exp-vacio">
           Nadie esperando. Agrega aquí a quien quiera "lo que salga antes":
           cuando se cancele una cita, el sistema dirá a quién le sirve.
         </div>`;
  }

  document.getElementById('smAgregarEspera')?.addEventListener('click', () => {
    const form = document.getElementById('formEspera');
    form.reset();
    document.getElementById('leIdPaciente').value = '';
    document.getElementById('leResultados').classList.add('d-none');
    modalEspera.show();
  });

  document.getElementById('leBuscar')?.addEventListener('input',
    App.debounce(async (ev) => {
      const q = ev.target.value.trim();
      const caja = document.getElementById('leResultados');

      if (q.length < 2) { caja.classList.add('d-none'); return; }

      const r = await App.api('paciente/listar.php', { q, estatus: '1' });
      caja.classList.remove('d-none');
      caja.innerHTML = r.datos.length
        ? r.datos.slice(0, 6).map((p) => `
            <div class="opcion" data-paciente="${p.id_registro_paciente}">
              <div>
                <div class="fw-semibold">${App.esc(p.nombre_completo)}</div>
                <div class="text-secondary" style="font-size:11px">
                  ${App.esc(p.num_expediente)}
                  ${p.telefono ? ' · ' + App.esc(p.telefono) : ''}
                </div>
              </div>
            </div>`).join('')
        : '<div class="p-3 text-center text-secondary small">Sin resultados.</div>';
    }, 350));

  document.getElementById('leResultados')?.addEventListener('click', (ev) => {
    const opcion = ev.target.closest('[data-paciente]');
    if (!opcion) return;

    document.getElementById('leIdPaciente').value = opcion.dataset.paciente;
    document.getElementById('leBuscar').value =
      opcion.querySelector('.fw-semibold').textContent.trim();
    document.getElementById('leResultados').classList.add('d-none');
  });

  document.getElementById('formEspera')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();

    if (!document.getElementById('leIdPaciente').value) {
      App.alerta.error('Elige al paciente de la lista.');
      return;
    }

    try {
      await App.api('consultas/espera_guardar.php', App.formADatos(ev.target));
      modalEspera.hide();
      App.alerta.toast('Agregado a la lista de espera');
      cargarEspera();
    } catch (e) {
      App.alerta.error('No se pudo agregar', e.mensaje ?? '');
    }
  });

  document.getElementById('smEspera')?.addEventListener('click', async (ev) => {
    const boton = ev.target.closest('[data-quitar-espera]');
    if (!boton) return;

    const ok = await App.alerta.confirmar('¿Quitar de la lista de espera?',
      'Queda registrado que estuvo esperando y no se agendó.', 'Sí, quitar');
    if (!ok) return;

    await App.api('consultas/espera_quitar.php', {
      id_espera: boton.dataset.quitarEspera,
    });
    App.alerta.toast('Quitado de la lista');
    cargarEspera();
  });

  cargarEspera();
}
