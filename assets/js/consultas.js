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
        // El estatus viaja al servidor. Filtrarlo aqui solo podia
        // QUITAR de lo que ya habia llegado, y los cancelados nunca
        // llegaban: la consulta traia unicamente los vigentes.
        estado: estado.value,
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
    const lista = todos;

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
  estado.addEventListener('change', cargar);
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
      <tr data-cita='${App.esc(JSON.stringify(c))}'
          class="${Number(c.sin_hora) === 1 ? 'fila-sin-hora' : ''}">
        <td>
          ${Number(c.sin_hora) === 1 ? `
            <span class="chip-sin-hora">
              <i class="bi bi-clock-history"></i> Sin hora
            </span>
            <div class="text-secondary" style="font-size:11px">
              Falta ponerle hora</div>`
          : `
            <div class="fw-semibold">${App.esc(c.fecha_inicio.substring(11, 16))}</div>
            <div class="text-secondary" style="font-size:11px">
              a ${App.esc(c.fecha_fin.substring(11, 16))}</div>`}
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
        <td class="text-secondary">
          ${App.esc(c.motivo ?? '-')}
          ${c.notas ? `<div class="nota-cita" title="${App.esc(c.notas)}">
                         <i class="bi bi-sticky"></i> ${App.esc(c.notas)}</div>` : ''}
        </td>
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
              ${Number(c.id_estado_cita) === 1 && Number(c.sin_hora) !== 1 ? `
              <li><a class="dropdown-item" href="#" data-accion="cita-estado" data-estado="2">
                <i class="bi bi-check2-circle me-2"></i>Confirmar</a></li>` : ''}
              ${[1, 2].includes(Number(c.id_estado_cita)) ? `
              <li><a class="dropdown-item" href="#" data-accion="cita-estado" data-estado="3">
                <i class="bi bi-door-open me-2"></i>Marcar en sala</a></li>` : ''}
              ${[2, 3].includes(Number(c.id_estado_cita)) ? `
              <li><a class="dropdown-item" href="#" data-accion="cita-estado" data-estado="4">
                <i class="bi bi-clipboard2-check me-2"></i>Marcar atendida</a></li>` : ''}
              <li><a class="dropdown-item" href="#" data-accion="cita-editar">
                ${Number(c.sin_hora) === 1
                  ? '<i class="bi bi-clock me-2"></i>Ponerle hora'
                  : '<i class="bi bi-calendar-event me-2"></i>Reagendar'}</a></li>
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

    /* El interruptor se pone ANTES de pedir los espacios.

       Abriendo una cita que esta sin hora, lo que se viene a hacer es
       justamente ponersela: el interruptor arranca apagado para que la
       rejilla de horarios este a la vista. Si arrancara encendido —"asi
       estaba guardada"— habria que apagarlo a mano cada vez, que es el
       unico camino por el que se entra a esta pantalla con una de
       estas citas. */
    document.getElementById('citaSinHora').checked = false;
    aplicarSinHora();

    document.getElementById('tituloModalCita').textContent =
      cita ? 'Modificar cita' : 'Nueva cita';

    document.getElementById('citaFecha').value = cita
      ? cita.fecha_inicio.substring(0, 10)
      : fecha.value;

    if (cita) {
      document.getElementById('citaDentista').value = cita.id_usuario_dentista;
      formCita.querySelector('[name="motivo"]').value = cita.motivo ?? '';
      formCita.querySelector('[name="id_sala"]').value = cita.id_sala ?? '';

      /* La nota TAMBIEN se recarga.

         Este era el bug de "la nota no se guarda". Si se guardaba: lo
         que pasaba es que al reagendar, el formulario se limpia con
         reset() y la nota no se volvia a poner, asi que al dar Guardar
         se mandaba vacia y pisaba la que ya estaba. El dato se perdia
         en la EDICION, no en el alta, que es por lo que parecia que
         nunca se habia guardado. */
      formCita.querySelector('[name="notas"]').value = cita.notas ?? '';

      /* La duracion sale de la diferencia entre las dos fechas, pero
         una cita sin hora las tiene iguales: daria cero, que no es
         ninguna de las opciones del selector y lo dejaria en blanco.
         Para esas vale el valor de arranque, media hora, que es lo que
         se va a elegir en cuanto se le ponga hora. */
      if (Number(cita.sin_hora) !== 1) {
        const min = (new Date(cita.fecha_fin.replace(' ', 'T'))
                   - new Date(cita.fecha_inicio.replace(' ', 'T'))) / 60000;
        document.getElementById('citaDuracion').value = String(min);
      }
    } else if (dentista.value) {
      document.getElementById('citaDentista').value = dentista.value;
    }

    modalCita.show();
    cargarEspaciosModal(horaPreferida
      ?? (Number(cita?.sin_hora) === 1
            ? null : cita?.fecha_inicio?.substring(11, 16)));
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

  /*
     El interruptor de "sin hora", en un solo sitio.

     Lo que cambia no es solo que se vea o no la rejilla: cambia que se
     pide y que no. Con el encendido no hay horario que elegir ni
     duracion que apartar, porque la cita no ocupa tiempo, y el boton
     deja de decir "Agendar cita" —no se esta agendando nada— para
     decir que lo que se aparta es el dia.

     Y la hora que hubiera quedado elegida se borra. Sin esto, alguien
     que elige las 10:30, se arrepiente y enciende el interruptor
     mandaria las dos cosas: el servidor se queda con "sin hora" y la
     pantalla habria ensenado otra cosa.
  */
  function aplicarSinHora() {
    const sin = document.getElementById('citaSinHora').checked;

    document.getElementById('citaEspacios').classList.toggle('d-none', sin);
    document.getElementById('citaCajaDuracion').classList.toggle('d-none', sin);
    document.getElementById('citaHoraRequerida').classList.toggle('d-none', sin);
    document.getElementById('citaAvisoSinHora').classList.toggle('d-none', !sin);

    if (sin) {
      document.getElementById('citaHora').value = '';
      document.querySelectorAll('#citaEspacios .espacio')
        .forEach((e) => e.classList.remove('elegido'));
    }

    document.getElementById('citaGuardar').innerHTML = sin
      ? '<i class="bi bi-calendar2-check me-1"></i> Apartar el día'
      : '<i class="bi bi-check-lg me-1"></i> Agendar cita';

    revisarListo();
  }

  function revisarListo() {
    const sin = document.getElementById('citaSinHora').checked;

    document.getElementById('citaGuardar').disabled =
      !document.getElementById('citaPaciente').value
      || (!sin && !document.getElementById('citaHora').value);
  }

  document.getElementById('citaSinHora')
    .addEventListener('change', aplicarSinHora);

  document.getElementById('citaEspacios').addEventListener('click', (ev) => {
    const bloque = ev.target.closest('.espacio.libre[data-hora]');
    if (bloque) elegirEspacio(bloque);
  });

  ['citaFecha', 'citaDentista', 'citaDuracion'].forEach((id) => {
    document.getElementById(id).addEventListener('change', () => {
      // Con el dia apartado no hay espacios que buscar: el viaje al
      // servidor no serviria para nada y la rejilla esta escondida.
      if (document.getElementById('citaSinHora').checked) return;
      cargarEspaciosModal();
    });
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
      /* El texto lo manda el servidor.

         Apartar un dia y agendar una hora no son la misma cosa y no se
         avisan igual: uno dice que falta ponerle hora, el otro que
         falta confirmar. Repetir el aviso aqui era tener dos verdades,
         y la de la pantalla decia "Cita agendada" tambien cuando no se
         habia agendado ninguna hora. */
      App.alerta.toast(r.mensaje ?? 'Cita guardada', 'info');

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

      await cambiarEstatus(cita, nuevo);
    }
  });

  /*
     El cambio de estatus, en un solo sitio.

     Lo llaman el menu de tres puntos del renglon y el detalle de la
     cita. Antes vivia suelto dentro del manejador del menu, y al
     agregar el detalle habria quedado duplicado: dos copias que
     avisan distinto, o que una ofrece abrir la consulta al poner en
     sala y la otra no.
  */
  async function cambiarEstatus(cita, nuevo) {
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

  /*
     Picar el renglon abre el detalle.

     Va DESPUES del manejador del menu y comprueba que el clic no venga
     de un enlace ni del menu: dentro del renglon hay un enlace al
     expediente y un desplegable de acciones, y si el renglon entero se
     tragara el clic, ninguno de los dos funcionaria.
  */
  tabla.addEventListener('click', (ev) => {
    if (ev.target.closest('a, button, .dropdown')) return;

    const fila = ev.target.closest('tr[data-cita]');
    if (!fila) return;

    DetalleCita.abrir(JSON.parse(fila.dataset.cita), {
      alCambiar: async (idCita, idEstado) => {
        const c = JSON.parse(fila.dataset.cita);
        await cambiarEstatus(c, idEstado);
      },
      alModificar: (c) => abrirCita(c),
    });
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

  /*
     Llegando desde la ficha del paciente con "?paciente=4", la cita se
     abre con ese paciente ya puesto.

     Sin esto, el boton de la ficha dejaba a la recepcionista en la
     agenda teniendo que buscar de nuevo al paciente que acababa de
     tener abierto. El dato ya lo sabemos: pedirlo otra vez es hacerle
     repetir trabajo al sistema.
  */
  // Llegando con "?nueva=1" —desde la vista de semana— el modal se abre
  // solo. Sin esto el boton de alla dejaba a la persona en la agenda
  // del dia correcto pero teniendo que picar "Nueva cita" otra vez.
  if (document.getElementById('agAbrirNueva')) {
    abrirCita();
  }

  /* Y con "?cita=N", esa cita se abre para modificarla.

     Hay que esperar a que el listado llegue: la cita se busca en lo
     que devolvio el servidor, no se pide aparte. cargar() ya se
     disparo arriba, asi que aqui solo se encadena a esa misma promesa
     en vez de pedir el dia dos veces. */
  const pedida = document.getElementById('agAbrirCita');
  if (pedida?.dataset.id) {
    cargar().then(() => {
      // Se compara el dato, no el texto del JSON. Un selector del tipo
      // [data-cita*='"id_cita":5'] parece mas corto pero depende de
      // como quede serializado, y PDO devuelve los enteros como
      // CADENA: el atributo dice "id_cita":"5" con comillas, asi que
      // ese selector no casaria nunca y el boton Modificar de la
      // semana no haria nada.
      const buscada = Number(pedida.dataset.id);
      for (const fila of tabla.querySelectorAll('tr[data-cita]')) {
        const c = JSON.parse(fila.dataset.cita);
        if (Number(c.id_cita) === buscada) { abrirCita(c); return; }
      }
      App.alerta.info('No encontré esa cita',
        'Puede que se haya movido a otro día o la hayan cancelado.');
    });
  }

  const pre = document.getElementById('agPacientePrevio');
  if (pre?.dataset.id) {
    abrirCita();
    document.getElementById('citaPaciente').value = pre.dataset.id;
    document.getElementById('citaPacienteTexto').value = pre.dataset.nombre;
    // El boton de guardar se habilita mirando los campos llenos.
    document.getElementById('citaPaciente')
      .dispatchEvent(new Event('change', { bubbles: true }));
  }
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
      // Para decidir con que denticion abrir cuando el renglon todavia
      // no trae dientes elegidos.
      edad: document.querySelector('[data-edad-paciente]')?.dataset.edadPaciente,
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

  /**
   * Abre la hoja del plan y, si se pide, tambien el consentimiento.
   *
   * Los dos se firman en la misma sentada, asi que al CREAR un plan se
   * abren juntos: imprimir uno, recordar que falta el otro y volver a
   * buscarlo es como se traspapela el consentimiento.
   *
   * Van en ventanas distintas y no en una sola con dos paginas porque
   * son dos documentos: uno se queda en el expediente y el otro lo
   * firma el tutor. Con nombres de ventana distintos, el navegador no
   * reusa la misma y de verdad salen los dos.
   */
  function imprimirDelPlan(idPlan) {
    if (!idPlan) return;
    App.abrirVentana(`${App.URL_BASE}/plan_imprimir.php?id=${idPlan}`,
      880, 1020, 'plan');
  }

  function imprimirConsentimiento(idPlan, idExpediente) {
    App.abrirVentana(
      `${App.URL_BASE}/consentimiento.php?id=${idExpediente}&plan=${idPlan}`,
      880, 1020, 'consentimiento');
  }

  /**
   * Plan guardado: un boton por cada papel, y cada uno marcado hasta
   * que se abre.
   *
   * Lo de antes era un solo boton, "Imprimir los dos", que abria dos
   * ventanas con 400 ms de diferencia. No funciono: el navegador tomo
   * la segunda por una emergente no pedida y la bloqueo sin avisar. Y
   * como el pendiente del consentimiento lo creaba esa misma ventana al
   * abrirse, se perdieron las dos cosas de golpe —el papel y el aviso
   * de que faltaba—.
   *
   * El pendiente ya se crea al guardar, en el servidor (ver
   * plan_guardar.php). Y aqui las ventanas se abren DE UNA EN UNA,
   * cada una por un clic: una ventana pedida con un clic no la bloquea
   * nadie. El truco de los 400 ms era pelear contra el navegador
   * cuando lo que estaba mal era pedirle dos cosas con un solo gesto.
   *
   * La advertencia dice "pendiente de imprimir" y se quita al picar.
   * Significa "ya lo abriste", no "ya salio de la impresora": eso no
   * se puede saber desde aqui, y la etiqueta lo dice asi.
   */
  async function avisarYOfrecerImpresiones(datos) {
    const idPlan = datos.id_plan_tratamiento;
    const idExpediente = idExpedienteDelPlan();

    const papeles = [
      { clave: 'plan', titulo: 'Hoja del plan de tratamiento',
        pie: 'La firma el tutor para aceptar el presupuesto.',
        abrir: () => imprimirDelPlan(idPlan) },
      { clave: 'consentimiento', titulo: 'Consentimiento bajo información',
        pie: 'Autoriza los procedimientos. Va firmado al expediente.',
        abrir: () => imprimirConsentimiento(idPlan, idExpediente) },
    ];

    const fila = (p) => `
      <button type="button" class="papel-plan" data-papel="${p.clave}">
        <i class="bi bi-file-earmark-text icono"></i>
        <span class="texto">
          <b>${p.titulo}</b>
          <small>${p.pie}</small>
        </span>
        <span class="marca">
          <i class="bi bi-exclamation-triangle-fill"></i>
          Pendiente de imprimir
        </span>
      </button>`;

    await Swal.fire({
      icon: 'success',
      title: 'Plan guardado',
      html: `<div class="guardado-plan">
               <div class="total">Total: <b>${App.money(datos.total)}</b>
                 ${datos.folio ? `<span class="folio">${App.esc(datos.folio)}</span>` : ''}
               </div>
               <p class="intro">
                 Imprime los dos papeles, recoge las firmas y s&uacute;belos
                 escaneados desde el <b>Expediente electr&oacute;nico</b>.
                 Los papeles por escanear ya quedaron registrados.
               </p>
               ${/*
                   Y los pendientes de PAGO, dichos aparte.

                   El parrafo de arriba decia "los pendientes ya quedaron
                   registrados" y hablaba de los papeles por escanear. Con
                   los cargos naciendo al guardar el plan, "pendiente"
                   significa dos cosas distintas en la misma pantalla, y
                   la que importa en el mostrador es esta. Se dice con el
                   numero: asi se nota si quedaron los que se capturaron.
                 */''}
               ${Number(datos.cargos?.creados) > 0 ? `
                 <p class="intro">
                   <i class="bi bi-cash-coin me-1"></i>
                   Se registraron <b>${Number(datos.cargos.creados)}</b>
                   concepto(s) como <b>pendientes de pago</b> del paciente.
                 </p>` : ''}
               ${Number(datos.cargos?.intocables) > 0 ? `
                 <p class="intro">
                   <i class="bi bi-lock me-1"></i>
                   ${Number(datos.cargos.intocables)} concepto(s) no se
                   modificaron porque ya tienen pagos aplicados.
                 </p>` : ''}
               ${papeles.map(fila).join('')}
             </div>`,
      showConfirmButton: true,
      confirmButtonText: 'Listo, ir al expediente',
      confirmButtonColor: '#7b2d8e',
      allowOutsideClick: false,
      width: 560,
      didOpen: (caja) => {
        caja.querySelectorAll('[data-papel]').forEach((boton) => {
          boton.addEventListener('click', () => {
            papeles.find((p) => p.clave === boton.dataset.papel)?.abrir();
            // Se marca como visto, no se desactiva: reimprimir es
            // legitimo —la impresora se atasca, sale torcido— y un
            // boton apagado obligaria a salirse y volver a entrar.
            boton.classList.add('impreso');
            boton.querySelector('.marca').innerHTML =
              '<i class="bi bi-check-circle-fill"></i> Enviado a imprimir';
          });
        });
      },
    });
  }

  /** El expediente al que pertenece el plan que se esta capturando. */
  function idExpedienteDelPlan() {
    return document.querySelector('[name="id_registro_paciente"]')?.value ?? '';
  }

  document.getElementById('planImprimir')?.addEventListener('click', () => {
    imprimirDelPlan(document.getElementById('planId').value);
  });

  document.getElementById('planConsentimiento')?.addEventListener('click', (ev) => {
    const b = ev.currentTarget;
    App.abrirVentana(
      `${App.URL_BASE}/consentimiento.php?id=${b.dataset.expediente}`
      + `&plan=${b.dataset.plan}`,
      880, 1020, 'consentimiento');
  });

  /* -------------------------------------------------------------------
     Cerrar el plan: terminarlo o cancelarlo
     -------------------------------------------------------------------
     Las dos cosas que no se deshacen. La regla de cuando se puede vive
     en PHP; aqui solo se pregunta bien antes de hacerlo.
  */
  document.getElementById('planTerminar')?.addEventListener('click', async (ev) => {
    const b = ev.currentTarget;
    const faltan = Number(b.dataset.pendientes || 0);

    const ok = await App.alerta.confirmar(
      '¿Dar por terminado este plan?',
      faltan > 0
        // El numero delante, no escondido: cerrar un plan con cuatro
        // conceptos sin hacer puede ser lo correcto —el paciente dejo
        // de venir— o puede ser un descuido, y quien decide tiene que
        // saber cual de los dos es.
        ? `Quedan ${faltan} concepto(s) sin realizar. Se cerrará de todas `
          + 'formas y ya no podrá volver a otro estatus.'
        : 'Ya no podrá volver a otro estatus.',
      'Sí, terminarlo');
    if (!ok) return;

    try {
      await App.api('consultas/plan_terminar.php', {
        id_plan_tratamiento: b.dataset.plan,
      });
      location.reload();
    } catch (e) { /* App.api ya aviso */ }
  });

  document.getElementById('planCancelar')?.addEventListener('click', async (ev) => {
    const b = ev.currentTarget;

    const { value: motivo, isConfirmed } = await Swal.fire({
      icon: 'warning',
      title: '¿Cancelar este plan?',
      html: 'Se quitarán sus pendientes de digitalización y ya no podrá '
          + 'volver a otro estatus.<br><span class="text-secondary small">'
          + 'Si el paciente dejó dinero a cuenta, se queda a su favor.</span>',
      input: 'textarea',
      inputLabel: '¿Por qué se cancela?',
      inputPlaceholder: 'El paciente se cambió de ciudad, prefirió otra opción...',
      inputAttributes: { maxlength: 255 },
      // Se exige aqui y tambien en el servidor. Aqui para no hacer un
      // viaje en balde; alla porque es donde de verdad importa.
      inputValidator: (v) => (!v || !v.trim())
        ? 'Escribe el motivo: dentro de seis meses nadie se va a acordar.'
        : undefined,
      showCancelButton: true,
      confirmButtonText: 'Sí, cancelar el plan',
      confirmButtonColor: '#c0392b',
      cancelButtonText: 'No',
      cancelButtonColor: '#6c6577',
      reverseButtons: true,
    });

    if (!isConfirmed) return;

    try {
      await App.api('consultas/plan_cancelar.php', {
        id_plan_tratamiento: b.dataset.plan,
        motivo,
      });
      location.reload();
    } catch (e) { /* App.api ya aviso */ }
  });

  /* -------------------------------------------------------------------
     Oclusion y traumatismos

     Los dos son datos que la mayoria de los pacientes no tiene. Como
     campos siempre visibles solo servian para que alguien pasara por
     encima con el tabulador; fuera del formulario, el caso normal
     —"no aplica"— no cuesta ni un clic.
     ------------------------------------------------------------------- */
  let oclusion = [];      // ['CLASE II', 'MORDIDA CRUZADA']
  let traumatismos = [];  // [{descripcion, secuela}]

  const modalOclusion = document.getElementById('modalOclusion')
    ? bootstrap.Modal.getOrCreateInstance(document.getElementById('modalOclusion'))
    : null;
  const modalTrauma = document.getElementById('modalTraumatismo')
    ? bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTraumatismo'))
    : null;

  function pintarOclusion() {
    const texto = document.getElementById('plOclusionTexto');
    const campo = document.getElementById('plOclusion');
    if (!texto || !campo) return;

    const vacio = oclusion.length === 0;
    texto.textContent = vacio ? 'No aplica' : oclusion.join(' · ');
    texto.classList.toggle('vacio', vacio);

    // Lo que viaja al servidor es una cadena separada por comas. No se
    // manda "NO APLICA" como texto: vacio ya significa eso, y guardar
    // la frase haria imposible distinguir "no tiene" de "no se
    // pregunto".
    campo.value = oclusion.join(', ');
  }

  document.getElementById('plOclusionAbrir')?.addEventListener('click', (ev) => {
    ev.preventDefault();
    document.querySelectorAll('#oclOpciones input[type="checkbox"]')
      .forEach((c) => { c.checked = oclusion.includes(c.value); });
    modalOclusion?.show();
  });

  document.getElementById('oclAceptar')?.addEventListener('click', () => {
    oclusion = [...document.querySelectorAll('#oclOpciones input:checked')]
      .map((c) => c.value);
    pintarOclusion();
    modalOclusion?.hide();
  });

  function pintarTraumatismos() {
    const texto = document.getElementById('plTraumaTexto');
    const lista = document.getElementById('plTraumaLista');
    const campo = document.getElementById('plTraumaJson');
    if (!texto || !lista || !campo) return;

    const vacio = traumatismos.length === 0;
    texto.textContent = vacio
      ? 'No aplica'
      : `${traumatismos.length} registrado${traumatismos.length > 1 ? 's' : ''}`;
    texto.classList.toggle('vacio', vacio);

    lista.innerHTML = traumatismos.map((t, i) => `
      <div class="traumatismo">
        <div style="min-width:0">
          <div class="trm-desc">${App.esc(t.descripcion)}</div>
          ${t.secuela ? `<div class="trm-secuela">
            <i class="bi bi-arrow-return-right"></i> ${App.esc(t.secuela)}
          </div>` : ''}
        </div>
        <button type="button" class="btn btn-sm btn-link p-0 text-danger"
                data-quitar-trm="${i}" title="Quitar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>`).join('');

    campo.value = JSON.stringify(traumatismos);
  }

  document.getElementById('plTraumaAbrir')?.addEventListener('click', (ev) => {
    ev.preventDefault();
    document.getElementById('trmDescripcion').value = '';
    document.getElementById('trmSecuela').value = '';
    modalTrauma?.show();
  });

  document.getElementById('trmAceptar')?.addEventListener('click', () => {
    const d = document.getElementById('trmDescripcion');
    const descripcion = d.value.trim().toUpperCase();

    if (descripcion.length < 3) {
      d.classList.add('is-invalid');
      d.focus();
      return;
    }
    d.classList.remove('is-invalid');

    traumatismos.push({
      descripcion,
      secuela: document.getElementById('trmSecuela').value.trim().toUpperCase(),
    });
    pintarTraumatismos();
    modalTrauma?.hide();
  });

  document.getElementById('plTraumaLista')?.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-quitar-trm]');
    if (!b) return;
    traumatismos.splice(Number(b.dataset.quitarTrm), 1);
    pintarTraumatismos();
  });

  pintarOclusion();
  pintarTraumatismos();

  /* -------------------------------------------------------------------
     Panel del plan: consultas, album y notas

     Un plan no es un documento que se firma y se archiva: se ejecuta
     durante meses, cita por cita. Antes, para ver que habia pasado con
     el, habia que salirse al expediente, buscar las consultas, entrar a
     cada una y volver. Estas tres tarjetas contestan eso sin moverse.
     ------------------------------------------------------------------- */
  const modalNotaVer = document.getElementById('modalNotaVer')
    ? bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNotaVer'))
    : null;

  let panel = { consultas: [], album: [], notas: [] };

  async function cargarPanel() {
    if (!idPlan || !document.getElementById('ppConsultas')) return;

    try {
      const r = await App.api('consultas/plan_panel.php', {
        id_plan_tratamiento: idPlan,
      });
      panel = r.datos;
      pintarPanel();
    } catch (e) { /* ya se mostro */ }
  }

  function pintarPanel() {
    pintarPanelConsultas();
    pintarPanelAlbum();
    pintarPanelNotas();
  }

  function pintarPanelConsultas() {
    const caja = document.getElementById('ppConsultas');
    document.getElementById('ppConsultasCuenta').textContent = panel.consultas.length;

    caja.innerHTML = panel.consultas.length
      ? panel.consultas.map((c) => `
          <a class="pp-consulta"
             href="${App.URL_BASE}/index.php?modulo=consultas&vista=consulta&id=${c.id_consulta}">
            <div style="min-width:0">
              <div class="pp-folio">
                ${App.esc(c.folio)}
                ${Number(c.cerrada) === 1
                  ? '<span class="chip-estado badge-suave">CERRADA</span>'
                  : '<span class="chip-estado badge-pendiente">ABIERTA</span>'}
              </div>
              <div class="pp-fecha">${App.esc(App.fechaHora(c.fecha_hora))}</div>
              ${c.motivo ? `<div class="pp-motivo">${App.esc(c.motivo)}</div>` : ''}
              <div class="pp-marcas">
                ${Number(c.hallazgos) ? `<span><i class="bi bi-search-heart"></i> ${c.hallazgos}</span>` : ''}
                ${Number(c.imagenes)  ? `<span><i class="bi bi-image"></i> ${c.imagenes}</span>` : ''}
                ${Number(c.notas)     ? `<span><i class="bi bi-journal-text"></i> ${c.notas}</span>` : ''}
              </div>
            </div>
            <i class="bi bi-chevron-right"></i>
          </a>`).join('')
      : `<div class="exp-vacio">
           Sin consultas todavía. Usa <b>Nueva consulta</b> arriba.
         </div>`;
  }

  function pintarPanelAlbum() {
    const caja = document.getElementById('ppAlbum');
    const total = panel.album.reduce((t, g) => t + g.elementos.length, 0);
    document.getElementById('ppAlbumCuenta').textContent = total;

    caja.innerHTML = total
      ? panel.album.map((g, i) => `
          <button type="button" class="pp-album" data-album-grupo="${i}">
            <img src="${App.URL_BASE}/archivo.php?id=${g.elementos[0].id_documento}" alt="">
            <div style="min-width:0">
              <div class="pp-folio">${App.esc(g.folio)}</div>
              <div class="pp-fecha">
                ${g.fecha ? App.esc(App.fecha(g.fecha)) : ''}
              </div>
              <div class="pp-cuenta-elementos">
                ${g.elementos.length} elemento${g.elementos.length > 1 ? 's' : ''}
              </div>
            </div>
          </button>`).join('')
      : `<div class="exp-vacio">
           Sin imágenes. Se agregan desde la consulta.
         </div>`;
  }

  function pintarPanelNotas() {
    const caja = document.getElementById('ppNotas');
    document.getElementById('ppNotasCuenta').textContent = panel.notas.length;

    caja.innerHTML = panel.notas.length
      ? panel.notas.map((n, i) => `
          <button type="button" class="pp-nota" data-nota="${i}">
            <div class="pp-fecha">
              ${App.esc(App.fechaHora(n.fecha_nota))} · ${App.esc(n.consulta_folio)}
              ${n.fecha_modificacion
                ? '<span class="marca-corregida">'
                  + '<i class="bi bi-pencil-fill"></i> corregida</span>' : ''}
            </div>
            <div class="pp-nota-texto">${App.esc(n.texto)}</div>
          </button>`).join('')
      : `<div class="exp-vacio">
           Sin notas. Se escriben dentro de cada consulta.
         </div>`;
  }

  /* ---- Abrir el album ---- */
  // El visor vive en comun.js: lo usan tambien el expediente y la
  // consulta. Aqui solo se le dice que lista mostrar.
  document.getElementById('ppAlbum')?.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-album-grupo]');
    if (!b) return;

    const g = panel.album[Number(b.dataset.albumGrupo)];
    Album.abrir({ titulo: g.folio, imagenes: g.elementos });
  });

  /* ---- Detalle de una nota ---- */
  document.getElementById('ppNotas')?.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-nota]');
    if (!b) return;

    const n = panel.notas[Number(b.dataset.nota)];
    document.getElementById('notaVerConsulta').textContent = n.consulta_folio;
    // La fecha de la NOTA, que es la que dice a que dia se refiere.
    // Con la de captura, una nota del viernes escrita el martes se
    // leeria como del martes.
    document.getElementById('notaVerFecha').textContent =
      App.fechaHora(n.fecha_nota)
      + (n.fecha_modificacion
          ? ` · corregida el ${App.fechaHora(n.fecha_modificacion)}`
            + (n.corrigio ? ` por ${n.corrigio}` : '')
          : '');
    document.getElementById('notaVerAutor').textContent = n.autor;
    document.getElementById('notaVerTexto').textContent = n.texto;
    document.getElementById('notaVerIrConsulta').href =
      `${App.URL_BASE}/index.php?modulo=consultas&vista=consulta&id=${n.id_consulta}`;

    modalNotaVer?.show();
  });

  cargarPanel();

  /* -------------------------------------------------------------------
     Carga de un plan existente
     ------------------------------------------------------------------- */
  if (idPlan) {
    (async () => {
      const r = await App.api('consultas/plan_obtener.php', {
        id_plan_tratamiento: idPlan,
      });
      const p = r.datos;

      ['descripcion', 'diagnostico', 'fecha'].forEach((c) => {
        const el = form.querySelector(`[name="${c}"]`);
        if (el && p[c] != null) el.value = p[c];
      });

      form.querySelector('[name="id_usuario_dentista"]').value = p.id_usuario_dentista;
      descuento.value = Number(p.descuento).toFixed(2);

      // El estatus ya no es un campo: se muestra tal cual viene.
      const chipEstado = document.querySelector('#plEstatus .chip-estado');
      if (chipEstado && p.nombre_estado) chipEstado.textContent = p.nombre_estado;

      oclusion = (p.oclusion ?? '').split(',')
        .map((x) => x.trim()).filter(Boolean);
      pintarOclusion();

      traumatismos = (p.traumatismos_lista ?? []).map((t) => ({
        descripcion: t.descripcion, secuela: t.secuela ?? '',
      }));
      pintarTraumatismos();

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

      await avisarYOfrecerImpresiones(r.datos);

      App.ir(`index.php?modulo=paciente&vista=detalle_paciente`
        + `&id=${form.querySelector('[name="id_registro_paciente"]').value}`);
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
                     href="${App.URL_BASE}/index.php?modulo=paciente&vista=expediente&id=${c.id_registro_paciente}">
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
    // Solo el motivo: padecimiento_actual y plan_indicaciones salieron
    // de la pantalla. Sus columnas siguen en la base con lo capturado
    // antes, pero esta pantalla ya no las escribe ni las lee.
    ['motivo'].forEach((c) => {
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
    barraALectura();

    pintarHallazgos();
    pintarRecetas();
    pintarNotas();
    cuentaDeDocumentos();
    cargarCargos();
  }

  /* -------------------------------------------------------------------
     La barra de arriba: fecha, dentista y plan

     En una consulta guardada se leen como texto. El lapiz descubre uno
     solo, y descubrir uno vuelve a tapar los otros dos: si quedan los
     tres abiertos se pierde la razon de haberlos cerrado.
     ------------------------------------------------------------------- */
  const barra = document.getElementById('cnBarra');

  /** El texto que se ve cuando el campo esta cerrado. */
  function textoDelCampo(campo) {
    if (campo === 'fecha') {
      return datos ? App.fechaHora(datos.fecha_hora) : '';
    }
    if (campo === 'dentista') {
      const sel = form.querySelector('[name="id_usuario_dentista"]');
      return sel.selectedOptions[0]?.textContent.trim() ?? '';
    }
    // El plan: el folio y su descripcion, o la ausencia dicha como tal.
    const sel = document.getElementById('cnPlan');
    return sel.value ? sel.selectedOptions[0].textContent.trim() : '';
  }

  function barraALectura() {
    if (!barra) return;

    barra.querySelectorAll('[data-campo]').forEach((caja) => {
      const campo  = caja.dataset.campo;
      const texto  = caja.querySelector('[data-texto]');
      const lapiz  = caja.querySelector('.lapiz-campo');
      const valor  = textoDelCampo(campo);

      texto.textContent = valor || 'Consulta general (sin plan)';
      texto.classList.toggle('vacio', valor === '');
      texto.classList.remove('d-none');

      // Una consulta cerrada no se edita: el lapiz solo estorbaria.
      const cerrada = Number(datos?.cerrada) === 1;
      lapiz.classList.toggle('d-none', cerrada);

      controlDe(caja).classList.add('d-none');
    });
  }

  /** El control de captura de una caja de la barra. */
  function controlDe(caja) {
    return caja.querySelector('input:not([type="hidden"]), select');
  }

  function abrirCampo(campo) {
    // Primero se cierran todos; asi solo hay uno abierto a la vez.
    barraALectura();

    const caja = barra.querySelector(`[data-campo="${campo}"]`);
    if (!caja) return;

    caja.querySelector('[data-texto]').classList.add('d-none');
    const control = controlDe(caja);
    control.classList.remove('d-none');
    control.focus();
  }

  barra?.addEventListener('click', (ev) => {
    const lapiz = ev.target.closest('[data-editar]');
    if (!lapiz) return;
    ev.preventDefault();
    abrirCampo(lapiz.dataset.editar);
  });

  /**
   * Una consulta cerrada bloquea lo clinico y deja viva la columna de
   * anexos: el registro no se corrige, pero se le siguen agregando
   * papeles y notas.
   */
  function bloquear(cerrada) {
    form.querySelectorAll('input, textarea, select')
      .forEach((el) => { if (el.type !== 'hidden') el.disabled = cerrada; });

    // Los DOS botones de Guardar. Apagar solo uno dejaba el otro vivo
    // sobre una consulta cerrada, y el servidor contestaba con un error
    // que parecia un fallo del sistema y no una regla.
    document.querySelectorAll('[data-guardar]')
      .forEach((b) => { b.disabled = cerrada; });

    if (btnCerrar) btnCerrar.disabled = cerrada;
    document.getElementById('cnAvisoCerrada').classList.toggle('d-none', !cerrada);
  }

  /**
   * Lo que no sirve hasta que la consulta exista.
   *
   * Un hallazgo, una receta, un cargo o una nota tienen que colgar de
   * una consulta guardada. Antes se veian iguales que si funcionaran,
   * con un aviso de texto arriba que se leia una vez y despues era
   * decorado: lo que pasaba era picar, no ver nada, y volver a picar.
   *
   * Se difuminan y se marcan `inert`. Las dos cosas, no una: el
   * difuminado es lo que se ve y el `inert` es lo que de verdad apaga
   * los clics, el tabulador y el lector de pantalla. El `disabled` de
   * los botones se mantiene porque es la unica de las tres que viaja
   * en el HTML y vale antes de que corra este archivo.
   */
  function habilitarAnexos(si) {
    ['cnNuevaReceta', 'cnNuevaNota', 'cnNuevoCargo', 'cnNuevoHallazgo',
     'cnOtroDocumento'].forEach((id) => {
      const b = document.getElementById(id);
      if (b) b.disabled = !si;
    });

    document.querySelectorAll('.zona-sin-consulta').forEach((z) => {
      z.classList.toggle('difuminada', !si);
      z.inert = !si;
    });

    /*
       El Guardar cambia de sitio, no de estado.

       Mientras la consulta no existe vive al pie del motivo, que es el
       ultimo campo que se escribe y el unico sitio vivo de la pantalla.
       Una vez creada sube a la cabecera, junto a Cerrar: a partir de
       ahi el trabajo es ir y venir entre hallazgos, recetas y notas, y
       el boton ya no pertenece al pie de UNA tarjeta sino a la
       pantalla entera.

       Se ve uno solo de los dos. Los dos a la vez no serian dos
       atajos: serian la duda de si hacen lo mismo.
    */
    document.getElementById('cnPieGuardar')?.classList.toggle('d-none', si);
    document.getElementById('cnGuardarArriba')?.classList.toggle('d-none', !si);

    // Sin permiso de capturar no existen ni Guardar ni Cerrar. Se
    // comprueba porque esta pantalla si se abre en modo lectura.
    const guardar = document.getElementById('cnGuardar');
    if (btnCerrar && guardar && !guardar.disabled) btnCerrar.disabled = !si;
  }

  /* -------------------------------------------------------------------
     Guardado
     ------------------------------------------------------------------- */
  /*
     Un solo manejador para los dos botones de Guardar.

     Hay uno en la cabecera y uno al pie, y solo se ve el que toca
     —abajo mientras la consulta no existe, arriba una vez creada—. Lo
     que los une es data-guardar y una escucha delegada en el documento.

     Delegada y no una por boton a proposito: dos escuchas sobre el
     mismo gesto es como nacio el bug del boton que se disparaba dos
     veces, y dos elementos con el mismo id es como nacio el de la foto
     del paciente. Aqui no hay ni lo uno ni lo otro.
  */
  document.addEventListener('click', async (ev) => {
    if (!ev.target.closest('[data-guardar]')) return;
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
  /**
   * El documento de una receta: el pendiente de escanearla firmada.
   *
   * La receta lo apunta en su columna id_documento desde que se crea,
   * asi que no hay que adivinar cual de los papeles de la consulta es
   * el suyo —y una consulta con tres recetas tiene tres pendientes
   * iguales de nombre—.
   */
  function documentoDeReceta(receta) {
    if (!receta.id_documento) return null;
    return (datos.documentos ?? []).find(
      (d) => Number(d.id_documento) === Number(receta.id_documento)) ?? null;
  }

  function pintarRecetas() {
    const caja = document.getElementById('cnRecetas');

    caja.innerHTML = datos.recetas.length
      ? datos.recetas.map((r) => {
          /*
             El escaneo, en el renglon de su receta.

             Vivia en la tarjeta "Documentos de la consulta", en la que
             habia que reconocer cual de los pendientes correspondia a
             cual receta leyendo folios. Aqui no hay nada que
             reconocer: el papel que hay que subir es el de esta
             receta, y esta pegado a ella.
          */
          const doc = documentoDeReceta(r);
          const papel = doc === null ? '' : (Number(doc.estado) === 0 ? `
            <button type="button" class="anexo-chip pendiente"
                    data-rx-escanear="${doc.id_documento}"
                    data-formatos="pdf,jpg,jpeg,png"
                    title="Adjuntar la receta firmada">
              <i class="bi bi-cloud-arrow-up"></i> Adjuntar firmada
            </button>` : `
            <a class="anexo-chip" target="_blank"
               href="${App.URL_BASE}/archivo.php?id=${doc.id_documento}"
               title="${App.esc(doc.nombre_original ?? '')}">
              <i class="bi bi-eye"></i> Ver la firmada
            </a>`);

          return `
          <div class="anexo">
            <div class="flex-grow-1" style="min-width:0">
              <div class="fw-semibold">${App.esc(r.folio)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(App.fecha(r.fecha))} · ${App.esc(r.dentista)}
              </div>
              <div class="anexo-texto">${App.esc(r.indicaciones)}</div>
              ${papel ? `<div class="anexo-archivos mt-1">${papel}</div>` : ''}
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
          </div>`;
        }).join('')
      : '<div class="exp-vacio">Sin recetas.</div>';
  }

  document.getElementById('cnNuevaReceta')?.addEventListener('click', () => abrirReceta(null));

  document.getElementById('cnRecetas').addEventListener('click', (ev) => {
    const escanear = ev.target.closest('[data-rx-escanear]');
    if (escanear) {
      pedirArchivo(escanear.dataset.rxEscanear, escanear.dataset.formatos);
      return;
    }

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
     Hallazgos

     Uno por observacion. La diferencia con la caja de texto que habia
     antes no es cosmetica: un hallazgo puede traer su propia imagen, y
     en la siguiente cita se puede ver cual sigue ahi y cual no.
     ------------------------------------------------------------------- */
  const modalHallazgo = document.getElementById('modalHallazgo')
    ? bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHallazgo'))
    : null;

  /**
   * Las imagenes de TODOS los hallazgos de la consulta, en el orden en
   * que se dictaron.
   *
   * El album se abre con el conjunto completo, no con las de un
   * hallazgo suelto: al revisar una cita uno quiere pasar de una
   * imagen a la siguiente, y partirlas por hallazgo obliga a cerrar y
   * volver a abrir en cada grupo.
   */
  function imagenesDeLaConsulta() {
    return (datos.hallazgos ?? []).flatMap((h) => Album.soloImagenes(h.imagenes));
  }

  function pintarHallazgos() {
    const caja = document.getElementById('cnHallazgos');
    if (!caja) return;

    const lista = datos.hallazgos ?? [];
    const cerrada = Number(datos.cerrada) === 1;

    // El boton de album en la cabecera solo aparece si hay que ver algo.
    const todas = imagenesDeLaConsulta();
    document.getElementById('cnAlbumHallazgos')
      ?.classList.toggle('d-none', todas.length === 0);

    caja.innerHTML = lista.length
      ? lista.map((h) => {
          const esRx = h.tipo === 'RADIOGRAFICO';
          return `
          <div class="hallazgo" data-hallazgo="${h.id_hallazgo}">
            <span class="hz-tipo ${esRx ? 'rx' : 'cl'}"
                  title="${esRx ? 'Visto en placa' : 'Visto en boca'}">
              <i class="bi ${esRx ? 'bi-radioactive' : 'bi-eye'}"></i>
              ${esRx ? 'Radiográfico' : 'Clínico'}
            </span>

            <div class="hz-cuerpo">
              ${h.descripcion
                ? `<div class="hz-texto">${App.esc(h.descripcion)}</div>`
                : `<div class="hz-texto sin-texto">Sin descripci\u00f3n</div>`}
              <div class="hz-pie">
                ${App.esc(App.fechaHora(h.fecha_registro))} · ${App.esc(h.autor)}
              </div>
            </div>

            ${(() => {
              const imgs = Album.soloImagenes(h.imagenes);
              if (!imgs.length) return '';

              // La primera como miniatura y, si hay mas, un contador
              // encima. Dibujar las seis en un renglon de lista
              // empujaria el texto del hallazgo fuera de la pantalla,
              // y el texto es lo que se lee.
              return `
                <button type="button" class="hz-imagen"
                        data-album-hz="${h.id_hallazgo}"
                        title="Ver ${imgs.length} imagen${imgs.length > 1 ? 'es' : ''}">
                  <img src="${App.URL_BASE}/archivo.php?id=${imgs[0].id_documento}" alt="">
                  ${imgs.length > 1
                    ? `<span class="cuantas">${imgs.length}</span>` : ''}
                </button>`;
            })()}

            ${cerrada ? '' : `
              <div class="hz-acciones">
                <button type="button" class="btn btn-sm btn-link p-0"
                        data-editar-hz title="Editar">
                  <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-link p-0 text-danger"
                        data-quitar-hz title="Quitar">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>`}
          </div>`;
        }).join('')
      : `<div class="exp-vacio">
           Sin hallazgos. Agrega uno por cada cosa que observes, en boca
           o en placa.
         </div>`;
  }

  /*
     El codigo con el que se nombra un hallazgo: CLIN_01, RADIO_03.

     Es la misma regla que codigoHallazgo() en funciones.php, y tiene
     que dar lo mismo: aqui se PROPONE al abrir el modal y alla se PONE
     cuando el campo llega vacio. Si las dos contaran distinto, lo que
     se ve al capturar y lo que queda guardado no coincidirian.

     El numero sale del MAXIMO que ya exista, no de contar los
     hallazgos: contando, borrar el CLIN_02 de tres haria que el
     siguiente se llamara CLIN_03 otra vez, y dos hallazgos del mismo
     dia tendrian el mismo nombre.
  */
  const PREFIJO_HALLAZGO = { RADIOGRAFICO: 'RADIO', CLINICO: 'CLIN' };

  function prefijoHallazgo(tipo) {
    return PREFIJO_HALLAZGO[String(tipo).toUpperCase()] ?? 'CLIN';
  }

  function codigoHallazgo(prefijo) {
    let maximo = 0;

    (datos.hallazgos ?? []).forEach((h) => {
      // Anclado al principio: un hallazgo que se llame "DUELE AL
      // MASTICAR CLIN_09" no debe correr la cuenta.
      const m = String(h.descripcion ?? '').trim().toUpperCase()
        .match(new RegExp(`^${prefijo}_(\\d+)`));
      if (m) maximo = Math.max(maximo, Number(m[1]));
    });

    return `${prefijo}_${String(maximo + 1).padStart(2, '0')}`;
  }

  /** ¿Este texto es un codigo que propusimos nosotros? */
  function esCodigoDeHallazgo(texto) {
    return /^(CLIN|RADIO)_\d+$/.test(String(texto ?? '').trim().toUpperCase());
  }

  /** Deja el modal listo. Sin hallazgo = alta; con hallazgo = edicion. */
  function abrirHallazgo(h = null) {
    document.getElementById('hzId').value = h ? h.id_hallazgo : '';
    document.getElementById('hzConsulta').value = idConsulta();
    document.getElementById('hzTitulo').textContent =
      h ? 'Editar hallazgo' : 'Nuevo hallazgo';
    document.getElementById('hzDescripcion').value = h ? (h.descripcion ?? '') : '';
    document.getElementById('hzArchivos').value = '';

    elegirTipoHallazgo(h ? h.tipo : 'CLINICO');

    // Las imagenes que ya tiene se muestran, no se tocan. El campo de
    // archivo AGREGA: dejarlo vacio no significa "borralas".
    const caja  = document.getElementById('hzImagenActual');
    const imgs  = h ? Album.soloImagenes(h.imagenes) : [];

    caja.classList.toggle('d-none', imgs.length === 0);
    if (imgs.length) {
      document.getElementById('hzImagenCuenta').textContent = `(${imgs.length})`;
      /*
         Cada miniatura con su cruz para quitarla.

         Hasta ahora las imagenes guardadas solo se podian mirar: el
         campo de archivo AGREGA, y no habia forma de deshacer una foto
         subida por error —la del paciente equivocado, o una que salio
         movida— sin irse al expediente a buscarla entre todas.

         La cruz aparece al pasar por encima: con ocho miniaturas, ocho
         cruces siempre visibles compiten con las fotos, que es lo que
         se viene a ver.
      */
      document.getElementById('hzImagenLista').innerHTML = imgs.map((d) => `
        <div class="mini-hz">
          <a href="${App.URL_BASE}/archivo.php?id=${d.id_documento}" target="_blank"
             title="${App.esc(d.descripcion ?? '')}">
            <img src="${App.URL_BASE}/archivo.php?id=${d.id_documento}" alt="">
          </a>
          <button type="button" class="quitar-mini"
                  data-quitar-imagen="${d.id_documento}"
                  title="Quitar esta imagen">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>`).join('');
    }

    modalHallazgo?.show();
  }

  function elegirTipoHallazgo(valor) {
    document.getElementById('hzTipoValor').value = valor;
    document.querySelectorAll('#hzTipo .opcion-hallazgo').forEach((b) => {
      b.classList.toggle('activa', b.dataset.valor === valor);
    });

    /*
       El codigo solo se propone en el ALTA.

       Un hallazgo guardado ya se llama como se llama, y su nombre viajo
       al expediente como el nombre de sus imagenes. Renumerarlo al
       abrirlo —o al cambiarle el tipo— dejaria el renglon del
       expediente diciendo CLIN_02 y el hallazgo diciendo RADIO_01: dos
       nombres para la misma cosa, que es peor que un nombre feo. Para
       renombrarlo esta el campo, que se puede escribir.

       La marca es hzId, que abrirHallazgo() llena ANTES de llamar aqui.
    */
    if (document.getElementById('hzId')?.value) return;

    /*
       Y no pisa lo que alguien escribio.

       Se reescribe si el campo esta vacio o si lo que tiene es un
       codigo nuestro: eso significa que nadie lo toco y que cambiar de
       tipo tiene que cambiarlo. Con una descripcion de verdad se queda,
       porque picar el tipo por error no puede borrar lo que se acabo de
       dictar.

       Es la misma regla que la propuesta del motivo de la consulta, y
       por la misma razon: una propuesta que borra lo escrito deja de
       ser una propuesta.
    */
    const campo = document.getElementById('hzDescripcion');
    const texto = (campo?.value ?? '').trim();

    if (campo && (texto === '' || esCodigoDeHallazgo(texto))) {
      campo.value = codigoHallazgo(prefijoHallazgo(valor));
    }
  }

  document.getElementById('hzImagenLista')?.addEventListener('click', async (ev) => {
    const boton = ev.target.closest('[data-quitar-imagen]');
    if (!boton) return;

    ev.preventDefault();      // la miniatura es un enlace: no abrirla

    const ok = await App.alerta.confirmar(
      '¿Quitar esta imagen?',
      'Se borra del expediente. Las demás imágenes del hallazgo se quedan.',
      'Sí, quitarla');
    if (!ok) return;

    boton.disabled = true;

    try {
      await App.api('consultas/hallazgo_imagen_quitar.php', {
        id_documento: boton.dataset.quitarImagen,
      });

      // Se quita del DOM y de los datos en memoria: si no, al cerrar y
      // volver a abrir el hallazgo reaparecería hasta recargar.
      boton.closest('.mini-hz')?.remove();
      quitarDeLosDatos(Number(boton.dataset.quitarImagen));

      const quedan = document.querySelectorAll('#hzImagenLista .mini-hz').length;
      document.getElementById('hzImagenCuenta').textContent = `(${quedan})`;
      document.getElementById('hzImagenActual')
        .classList.toggle('d-none', quedan === 0);

      App.alerta.toast('Imagen quitada');
    } catch (e) {
      boton.disabled = false;
    }
  });

  /** Borra la imagen de los datos que ya tiene cargados la pantalla. */
  function quitarDeLosDatos(idDocumento) {
    (datos.hallazgos ?? []).forEach((h) => {
      h.imagenes = (h.imagenes ?? [])
        .filter((d) => Number(d.id_documento) !== idDocumento);
    });
  }

  document.getElementById('hzTipo')?.addEventListener('click', (ev) => {
    const b = ev.target.closest('.opcion-hallazgo');
    if (b) elegirTipoHallazgo(b.dataset.valor);
  });

  document.getElementById('cnAlbumHallazgos')?.addEventListener('click', () => {
    Album.abrir({
      titulo: `Consulta ${datos.folio}`,
      imagenes: imagenesDeLaConsulta(),
    });
  });

  document.getElementById('cnNuevoHallazgo')?.addEventListener('click', () => {
    if (!idConsulta()) {
      App.alerta.info('Guarda la consulta primero',
        'Un hallazgo tiene que colgar de una consulta que ya exista.');
      return;
    }
    abrirHallazgo();
  });

  document.getElementById('cnHallazgos')?.addEventListener('click', async (ev) => {
    const fila = ev.target.closest('[data-hallazgo]');
    if (!fila) return;
    const h = (datos.hallazgos ?? [])
      .find((x) => String(x.id_hallazgo) === fila.dataset.hallazgo);
    if (!h) return;

    if (ev.target.closest('[data-album-hz]')) {
      // Se abre con TODAS las de la consulta, pero empezando en la
      // primera de este hallazgo: se ve lo que se pico y de ahi se
      // puede seguir al resto sin cerrar.
      const todas = imagenesDeLaConsulta();
      const suya  = Album.soloImagenes(h.imagenes)[0];
      const desde = todas.findIndex(
        (d) => String(d.id_documento) === String(suya?.id_documento));

      Album.abrir({
        titulo: `Consulta ${datos.folio}`,
        imagenes: todas,
        desde: Math.max(0, desde),
      });
      return;
    }

    if (ev.target.closest('[data-editar-hz]')) { abrirHallazgo(h); return; }

    if (ev.target.closest('[data-quitar-hz]')) {
      const ok = await App.alerta.confirmar(
        '¿Quitar este hallazgo?',
        'Deja de verse en la consulta. La imagen, si tiene, se queda en el expediente.',
        'Sí, quitar');
      if (!ok) return;

      try {
        await App.api('consultas/hallazgo_quitar.php', { id_hallazgo: h.id_hallazgo });
        App.alerta.toast('Hallazgo quitado');
        cargar();
      } catch (e) { /* ya se mostro */ }
    }
  });

  document.getElementById('formHallazgo')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();

    const boton = document.getElementById('hzGuardar');
    if (boton.dataset.enviando === '1') return;

    // Va como FormData porque puede traer un archivo: armar un objeto
    // plano perderia el File.
    const cuerpo = new FormData(ev.target);

    boton.dataset.enviando = '1';
    boton.disabled = true;

    try {
      const r = await App.api('consultas/hallazgo_guardar.php', cuerpo);
      modalHallazgo?.hide();
      App.alerta.toast(r.mensaje);
      cargar();
    } catch (e) {
      App.marcarErrores(ev.target, e.errores);
    } finally {
      boton.dataset.enviando = '0';
      boton.disabled = false;
    }
  });

  /* -------------------------------------------------------------------
     Notas de evolucion
     ------------------------------------------------------------------- */
  function pintarNotas() {
    const caja = document.getElementById('cnNotas');

    caja.innerHTML = datos.notas.length
      ? datos.notas.map((n, i) => `
          <div class="anexo">
            <div style="min-width:0">
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(App.fechaHora(n.fecha_nota))} · ${App.esc(n.autor)}
                ${/*
                     Si se corrigio, se dice. Una nota cambiada en
                     silencio es peor que una nota con un dedazo: el
                     expediente diria algo distinto de lo que se firmo y
                     nada lo indicaria. Lo que NO se ensenia es el texto
                     viejo —no se guarda— y eso se nota aqui a
                     proposito: lo que queda es quien y cuando.
                  */''}
                ${n.fecha_modificacion ? `
                  <span class="marca-corregida"
                        title="Corregida el ${App.esc(App.fechaHora(n.fecha_modificacion))}${
                          n.corrigio ? ' por ' + App.esc(n.corrigio) : ''}">
                    <i class="bi bi-pencil-fill"></i> corregida
                  </span>` : ''}
              </div>
              <div class="anexo-texto">${App.esc(n.texto)}</div>
            </div>
            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none"
                    data-editar-nota="${i}" title="Corregir esta nota">
              <i class="bi bi-pencil"></i>
            </button>
          </div>`).join('')
      : `<div class="exp-vacio">
           Sin notas. No generan pendiente de digitalización.
         </div>`;
  }

  /**
   * Deja el modal listo. Sin nota = alta; con nota = correccion.
   *
   * El mismo modal para las dos cosas, como en los hallazgos: son los
   * mismos dos campos y un segundo modal seria una copia que se separa
   * en cuanto alguien toque uno.
   */
  function abrirNota(n = null) {
    document.getElementById('notaId').value = n ? n.id_nota_evolucion : '';
    document.getElementById('notaConsulta').value = idConsulta();
    document.getElementById('notaTexto').value = n ? (n.texto ?? '') : '';

    document.getElementById('notaTitulo').textContent =
      n ? 'Corregir la nota' : 'Nota de evolución';
    document.getElementById('notaGuardar').textContent =
      n ? 'Guardar la corrección' : 'Agregar nota';

    /*
       El campo datetime-local quiere "2026-10-17T14:30", sin segundos
       y con T en medio. Lo que llega de MySQL es "2026-10-17 14:30:00":
       asignado tal cual, el navegador lo rechaza en silencio y el campo
       aparece VACIO —con lo que corregir el texto de una nota le
       cambiaria la fecha sin que nadie lo pida—.
    */
    document.getElementById('notaFecha').value = n
      ? String(n.fecha_nota ?? '').replace(' ', 'T').substring(0, 16)
      : ahoraParaCampo();
  }

  /** La fecha y hora de ahora, en el formato del campo. */
  function ahoraParaCampo() {
    const d = new Date();
    // A mano y no con toISOString(): ese convierte a UTC, y en Nayarit
    // eso son seis horas menos. Una nota escrita a las 9 de la mañana
    // saldria fechada a las 3 de la madrugada.
    const dos = (x) => String(x).padStart(2, '0');
    return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`
         + `T${dos(d.getHours())}:${dos(d.getMinutes())}`;
  }

  document.getElementById('cnNuevaNota')?.addEventListener('click', () => {
    abrirNota();
    modalNota.show();
  });

  document.getElementById('cnNotas')?.addEventListener('click', (ev) => {
    const boton = ev.target.closest('[data-editar-nota]');
    if (!boton) return;

    abrirNota(datos.notas[Number(boton.dataset.editarNota)]);
    modalNota.show();
  });

  document.getElementById('formNota')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      const r = await App.api('consultas/nota_guardar.php',
        App.formADatos(ev.target));
      modalNota.hide();
      // El texto lo manda el servidor: "agregada" y "corregida" no son
      // lo mismo, y aqui habia uno fijo que decia siempre "agregada".
      App.alerta.toast(r.mensaje ?? 'Nota guardada');
      cargar();
    } catch (e) {
      App.marcarErrores(ev.target, e.errores);
    }
  });

  /* -------------------------------------------------------------------
     Documentos de la consulta

     Se fue la tarjeta que los listaba por tipo. Eran cuatro grupos
     —receta, radiografia, fotografia, otro— y tres de los cuatro no se
     usaban desde aqui: las imagenes entran por su hallazgo, que es lo
     que les da sentido clinico, y la receta trae su propio pendiente.
     Media columna para decir "Sin archivos" cuatro veces.

     De lo que hacia queda lo que de verdad se usaba:

       - el escaneo de la receta, ahora pegado a su receta
       - anexar un estudio externo, en un enlace al pie
       - la cuenta de lo subido, para que no parezca que se perdio

     Lo demas se ve completo en el expediente del paciente, agrupado
     bajo su consulta, que es donde se busca un papel de hace un anio.
     ------------------------------------------------------------------- */

  /** Lo que ya tiene archivo, sin contar las imagenes de hallazgos. */
  function documentosAnexados() {
    return (datos?.documentos ?? []).filter(
      (d) => Number(d.estado) === 1 && !d.id_hallazgo);
  }

  function cuentaDeDocumentos() {
    const caja = document.getElementById('cnCuentaDocumentos');
    if (!caja) return;

    const n = documentosAnexados().length;
    caja.classList.toggle('d-none', n === 0);
    caja.textContent = n === 1
      ? '1 archivo anexado'
      : `${n} archivos anexados`;
  }

  /* ---- Adjuntar el escaneo de un pendiente, sin salir de aqui ---- */
  // Se usa un campo de archivo oculto en vez de un modal: son dos
  // clics en lugar de cuatro, y lo unico que hay que decidir es cual
  // archivo. Un modal para eso solo agrega pasos.
  function pedirArchivo(idDocumento, formatos) {
    const campo = document.getElementById('cnArchivoPendiente');
    campo.value = '';
    campo.dataset.documento = idDocumento;
    campo.setAttribute('accept',
      (formatos || '').split(',').map((e) => '.' + e.trim()).join(','));
    campo.click();
  }

  /* ---- Anexar un estudio externo o una interconsulta ---- */
  // Papel que llega de fuera y no es hallazgo ni receta: el estudio de
  // un laboratorio, la nota de otro medico. Pasa pocas veces, asi que
  // es un renglon y no una tarjeta, pero tiene que poder hacerse aqui:
  // llega en la mano del tutor, durante la cita.
  document.getElementById('cnOtroDocumento')?.addEventListener('click', () => {
    const tipo = tipos.find((t) => t.clave === 'OTRO_CONSULTA');
    if (!tipo) {
      App.alerta.error('No se puede anexar',
        'Falta el tipo de documento OTRO_CONSULTA en el catálogo.');
      return;
    }
    abrirAlbumDeTipo(tipo);
  });

  document.getElementById('cnArchivoPendiente')?.addEventListener('change', async (ev) => {
    const archivo = ev.target.files?.[0];
    if (!archivo) return;

    const cuerpo = new FormData();
    cuerpo.append('id_documento', ev.target.dataset.documento);
    cuerpo.append('archivo', archivo);

    App.alerta.cargando('Guardando el escaneo...');
    try {
      await App.api('expediente/subir.php', cuerpo);
      App.alerta.cerrar();
      App.alerta.toast('Escaneo guardado');
      cargar();
    } catch (e) {
      App.alerta.cerrar();
    } finally {
      ev.target.value = '';
    }
  });

  function abrirAlbumDeTipo(tipo) {
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
  }

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

  /* -------------------------------------------------------------------
     ¿De que plan es esta consulta?

     Se pregunta al abrir una consulta nueva, cuando el paciente tiene
     tratamiento abierto. No es un adorno de bienvenida: el plan decide
     en que parte del expediente caen los papeles de hoy y es lo que
     empuja el plan a "En proceso" al guardarse su primera consulta.

     Como combo con "Consulta general" preseleccionado, lo que pasaba es
     que nadie lo tocaba: las consultas del tratamiento quedaban sueltas
     en el bloque general y el plan se quedaba en "Aceptado" aunque el
     paciente llevara seis citas.
     ------------------------------------------------------------------- */
  const selPlan = document.getElementById('cnPlan');
  const campoMotivo = form.querySelector('[name="motivo"]');

  /** Los motivos que propone cada plan, para saber si un texto es mio. */
  function propuestas() {
    return [...(selPlan?.options ?? [])]
      .filter((o) => o.value)
      .map((o) => o.dataset.motivo ?? '');
  }

  /**
   * Escribe la propuesta del plan elegido.
   *
   * Respeta lo escrito a mano: solo pisa un campo que este vacio o que
   * tenga TEXTUALMENTE la propuesta de otro plan. Si el usuario corrigio
   * el motivo y luego cambia de plan, su correccion se queda. Una
   * propuesta que borra lo que alguien tecleo deja de ser una propuesta.
   */
  function proponerDelPlan() {
    const opcion = selPlan?.selectedOptions?.[0];
    if (!opcion || !opcion.value) return;

    // Solo el motivo. El padecimiento salio de la pantalla, y con el
    // su propuesta: dejar aqui un querySelector de un campo que ya no
    // existe habria dado null y reventado en el primer cambio de plan.
    const mias   = propuestas();
    const actual = campoMotivo.value.trim();

    if (actual === '' || mias.includes(actual)) {
      campoMotivo.value = opcion.dataset.motivo ?? '';
    }
  }

  // Cambiar el plan a mano tambien reescribe la propuesta, pero solo
  // mientras la consulta no exista. En una guardada, cambiar el plan es
  // una correccion de donde va el papeleo y no tiene por que tocar lo
  // que ya dice el expediente.
  selPlan?.addEventListener('change', () => {
    if (!idConsulta()) proponerDelPlan();
  });

  const cajaPlan = document.getElementById('modalPlanConsulta');
  const modalPlan = cajaPlan
    ? bootstrap.Modal.getOrCreateInstance(cajaPlan) : null;

  function preguntarDePlan() {
    if (!modalPlan || !selPlan) return;
    if (document.getElementById('cnPreguntarPlan')?.value !== '1') return;

    const opciones = [...selPlan.options].filter((o) => o.value);
    if (!opciones.length) return;

    document.getElementById('pcPlanes').innerHTML = opciones.map((o) => `
      <button type="button" class="btn btn-outline-morado text-start"
              data-plan="${App.esc(o.value)}">
        <b>${App.esc(o.textContent.trim())}</b>
        <div class="text-secondary" style="font-size:11.5px">
          Se propondrá como motivo: ${App.esc(o.dataset.motivo ?? '')}
        </div>
      </button>`).join('');

    modalPlan.show();
  }

  document.getElementById('pcPlanes')?.addEventListener('click', (ev) => {
    const boton = ev.target.closest('[data-plan]');
    if (!boton) return;

    selPlan.value = boton.dataset.plan;
    proponerDelPlan();
    modalPlan.hide();
    campoMotivo.focus();
  });

  document.getElementById('pcGeneral')?.addEventListener('click', () => {
    selPlan.value = '';
    modalPlan.hide();
    campoMotivo.focus();
  });

  cargar();

  // La pregunta se abre AL FINAL, despues de colgar los manejadores de
  // sus dos botones. El orden es carga, no estilo: ese modal no tiene
  // aspa ni escape, asi que si se abriera antes de que existan sus
  // escuchas, la pantalla quedaria tapada sin salida. Abriendolo aqui,
  // si se ve es porque se puede contestar.
  preguntarDePlan();
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

  /*
     "Nueva cita" en la vista de semana.

     No hacia nada: el boton nunca tuvo escucha, y aunque lo hubiera
     tenido, en esta pantalla no existe el modal de la cita —vive en la
     agenda del dia, junto con el buscador de paciente y el formulario.

     En vez de duplicar los tres aqui, el boton lleva a la agenda del
     dia con el modal ya abierto. Una cita se agenda SOBRE un dia
     concreto: hay que elegir la hora y ver que mas hay a esa hora, y
     eso es justo lo que la vista de semana no muestra. Se manda el
     lunes que se esta viendo para no caer en otra semana, y el
     dentista filtrado para no volver a elegirlo.
  */
  document.getElementById('smNuevaCita')?.addEventListener('click', () => {
    const parametros = new URLSearchParams({
      modulo: 'consultas',
      vista:  'agenda',
      fecha:  campoLunes.value || new Date().toISOString().substring(0, 10),
      nueva:  '1',
    });
    if (selDentista?.value) parametros.set('dentista', selDentista.value);
    App.ir(`index.php?${parametros}`);
  });


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
      `${datos.citas} cita(s) esta semana`
      + (datos.sin_hora_total
          ? ` · <b style="color:var(--alerta)">${datos.sin_hora_total} `
            + `sin hora</b>` : '');

    let html = '<table class="semana"><thead><tr><th class="hora"></th>';

    dias.forEach((d) => {
      html += `<th class="${d.hoy ? 'hoy' : ''}">
                 <div class="nombre">${App.esc(d.nombre)}</div>
                 <div class="dia">${d.dia}</div>
               </th>`;
    });
    html += '</tr></thead><tbody>';

    /*
       La banda de las citas sin hora.

       Va en el primer renglon del cuerpo, arriba de todas las horas,
       porque es lo que hay que repartir ANTES de ponerse a mirar
       huecos. En la rejilla no caben: su hora es las 00:00 y el
       consultorio abre a las nueve, asi que no habria renglon donde
       ponerlas.

       El renglon entero se omite cuando la semana no tiene ninguna. Una
       banda vacia con el rotulo "Sin hora" seria un renglon de tabla
       que no dice nada y que corre la rejilla hacia abajo todos los
       dias del anio para el caso raro.
    */
    if (datos.sin_hora_total) {
      html += '<tr class="renglon-sin-hora">'
            + '<td class="hora">Sin<br>hora</td>';

      datos.sin_hora.forEach((delDia) => {
        html += '<td class="celda-sin-hora">'
          + delDia.map((ci) => `
              <div class="pildora-sin-hora" data-cita="${ci.id_cita}"
                   title="${App.esc(ci.paciente)} · falta ponerle hora"
                   style="--color:${App.esc(ci.color ?? '#9333a8')}">
                <i class="bi bi-clock-history"></i>
                <span>${App.esc(ci.paciente)}</span>
              </div>`).join('')
          + '</td>';
      });

      html += '</tr>';
    }

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
    // Solo los bloques de la rejilla, no las pildoras de la banda: a
    // una cita sin hora no se le puede "conservar la duracion" al
    // soltarla porque no tiene ninguna, y lo que hay que hacer con
    // ella es abrirla y ponerle hora.
    const bloque = ev.target.closest('.cita-bloque[data-cita]');
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

  /*
     Picar un bloque de la semana abre su detalle.

     Hasta ahora el bloque solo servia para arrastrarlo a otra hora: se
     veia el nombre y poco mas, y para saber el motivo o el telefono
     habia que irse a la agenda del dia a buscarlo. Ahora el bloque se
     pica.

     Arrastrar y picar conviven sin estorbarse: el navegador dispara
     'click' solo cuando el raton se solto donde se apreto, asi que
     terminar un arrastre no abre el detalle.
  */
  caja.addEventListener('click', (ev) => {
    const bloque = ev.target.closest('[data-cita]');
    if (!bloque) return;

    const cita = buscarCita(Number(bloque.dataset.cita));
    if (!cita) return;

    DetalleCita.abrir(cita, {
      alCambiar: async (idCita, idEstado) => {
        await App.api('consultas/cita_estatus.php', {
          id_cita: idCita, id_estado_cita: idEstado,
        });
        App.alerta.toast('Cita actualizada');
        cargar();
      },
      /*
         Modificar lleva a la agenda del dia con esa cita abierta.

         El formulario de reagendar necesita los espacios libres del
         dia para avisar de los choques, y eso la vista de semana no lo
         tiene cargado. Traerlo aqui serian dos formularios validando
         distinto sobre la misma tabla; llevar a quien sabe es mas
         barato y no se desincroniza.
      */
      alModificar: (c) => {
        const parametros = new URLSearchParams({
          modulo: 'consultas',
          vista:  'agenda',
          fecha:  String(c.fecha_inicio).substring(0, 10),
          cita:   c.id_cita,
        });
        App.ir(`index.php?${parametros}`);
      },
    });
  });

  function buscarCita(id) {
    // Tambien en la banda de las sin hora: el detalle se abre picandolas
    // igual que cualquier otra, y sin esto el clic no encontraria nada y
    // no pasaria nada en pantalla.
    for (const delDia of (datos.sin_hora ?? [])) {
      const hallada = delDia.find((c) => Number(c.id_cita) === id);
      if (hallada) return hallada;
    }

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
