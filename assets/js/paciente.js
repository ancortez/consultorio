/* =====================================================================
   Modulo Paciente
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  listadoPacientes();
  altaPaciente();
  gestorContactos();
  detallePaciente();
  tutorEditar();

  /*
     El expediente, la historia y el consentimiento viven en sus propios
     archivos y se arrancan desde aqui.

     El id del paciente viajaba en un atributo del contenedor de
     pestanias. Esas pestanias desaparecieron en octubre 2026, asi que
     ahora viaja en un elemento propio que ponen las tres pantallas:
     ficha, historia clinica y expediente. Cada modulo decide por su
     cuenta si tiene algo que hacer en la pantalla donde esta.
  */
  /* -------------------------------------------------------------------
     Los lapices y los "+" de la ficha

     El lapiz abre la LISTA completa de ese bloque; el "+" va DIRECTO
     al alta. La diferencia no es cosmetica: si no hay nada registrado,
     lo unico que se puede hacer es crear, y mandar a alguien a una
     lista vacia para que ahi pique "agregar" es un clic de mas y una
     pantalla que no dice nada.
     ------------------------------------------------------------------- */
  /* -------------------------------------------------------------------
     Picar una cita de la tarjeta abre su detalle
     -------------------------------------------------------------------
     El mismo modal que usan las dos agendas. Desde la ficha solo se
     MIRA: no se cambia el estatus ni se modifica. Quien esta en la
     ficha del paciente esta repasando su historia, no gobernando la
     agenda, y ofrecer ahi un "marcar atendida" invita a tocar por
     error una cita de hace tres meses.
  */
  document.addEventListener('click', (ev) => {
    const boton = ev.target.closest('[data-cita-ficha]');
    if (!boton) return;

    DetalleCita.abrir(JSON.parse(boton.dataset.citaFicha), {
      alModificar: (c) => {
        // Modificar lleva a la agenda de ese dia, con la cita abierta:
        // es donde viven los espacios libres y el aviso de choques.
        const parametros = new URLSearchParams({
          modulo: 'consultas', vista: 'agenda',
          fecha: String(c.fecha_inicio).substring(0, 10),
          cita:  c.id_cita,
        });
        App.ir(`index.php?${parametros}`);
      },
    });
  });

  /* -------------------------------------------------------------------
     La fotografia: verla en grande y quitarla
     -------------------------------------------------------------------
     Verla en grande se dispara desde dos sitios —el circulo de la
     tarjeta de arriba y la miniatura del campo— porque son los dos
     sitios donde uno la ve y se le ocurre picarla. Un solo escucha
     delegado sirve para ambos.
  */
  document.addEventListener('click', (ev) => {
    const verFoto = ev.target.closest('[data-ver-foto]');
    if (verFoto) {
      // El mismo ?v= que usa el refresco tras subirla: sin el, el
      // navegador puede mostrar en grande la foto vieja que todavia
      // tiene guardada.
      App.verImagen(
        `${App.URL_BASE}/avatar.php?p=${verFoto.dataset.verFoto}&v=${Date.now()}`,
        document.querySelector('.tarjeta-identidad-compacta .nombre')
          ?.textContent.trim() ?? '');
      return;
    }

    const quitarFoto = ev.target.closest('[data-quitar-foto]');
    if (!quitarFoto) return;

    (async () => {
      const ok = await App.alerta.confirmar(
        '¿Eliminar la fotografía?',
        'El expediente se queda sin fotografía hasta que subas otra.',
        'Sí, eliminar');
      if (!ok) return;

      try {
        await App.api('paciente/foto_quitar.php', {
          id_registro_paciente:
            document.getElementById('pacienteContexto')?.dataset.idExpediente,
        });
        // Se recarga en vez de retocar el DOM: la foto sale en el
        // circulo, en el campo y en el menu de acciones, y dejar uno
        // de los tres sin actualizar es peor que esperar un segundo.
        location.reload();
      } catch (e) { /* App.api ya aviso */ }
    })();
  });

  document.addEventListener('click', (ev) => {
    const abrir = ev.target.closest('[data-abrir]');
    if (abrir) {
      const cajas = {
        contactos: 'modalListaContactos',
        tutores:   'modalListaTutores',
        domicilio: 'modalDomicilio',
      };
      const caja = document.getElementById(cajas[abrir.dataset.abrir]);
      if (caja) bootstrap.Modal.getOrCreateInstance(caja).show();
      return;
    }

    const nuevo = ev.target.closest('[data-nuevo]');
    if (!nuevo) return;

    // El "+" dispara el boton de alta que ya vive dentro de cada
    // lista, en vez de duplicar aqui la logica de abrir su modal.
    // Asi hay un solo camino al alta y no dos que se pueden separar.
    const disparos = {
      contacto:  ['modalListaContactos', '[data-accion="nuevo-contacto"]'],
      tutor:     ['modalListaTutores',   '[data-accion="nuevo-tutor"]'],
      domicilio: ['modalDomicilio',      null],
    };

    const [idCaja, selector] = disparos[nuevo.dataset.nuevo] ?? [];
    const caja = document.getElementById(idCaja);
    if (!caja) return;

    if (selector) {
      // El alta abre OTRO modal encima de la lista. Se deja la lista
      // detras a proposito: al cerrar el alta, se vuelve a ella y se
      // ve lo que se acaba de registrar.
      bootstrap.Modal.getOrCreateInstance(caja).show();
      setTimeout(() => caja.querySelector(selector)?.click(), 250);
    } else {
      // El domicilio no tiene lista: su modal ES el formulario.
      bootstrap.Modal.getOrCreateInstance(caja).show();
    }
  });

  const contexto = document.getElementById('pacienteContexto');
  if (contexto) {
    const id = contexto.dataset.idExpediente;
    if (typeof Expediente     !== 'undefined') Expediente.iniciar(id);
    if (typeof Historia       !== 'undefined') Historia.iniciar(id);
    if (typeof Consentimiento !== 'undefined') Consentimiento.iniciar(id);
  }
});

/* ---------------------------------------------------------------------
   Vista buscar_paciente: lista y filtro
   --------------------------------------------------------------------- */
function listadoPacientes() {
  const tabla = document.getElementById('tablaPacientes');
  if (!tabla) return;

  const filtro   = document.getElementById('filtroBusqueda');
  const estatus  = document.getElementById('filtroEstatus');
  const limpiar  = document.getElementById('limpiarFiltro');
  const contador = document.getElementById('contadorResultados');

  async function cargar() {
    tabla.innerHTML = `<tr class="fila-vacia"><td colspan="5">
        <div class="spinner-border spinner-border-sm me-2" style="color:var(--morado-600)"></div>
        Buscando...</td></tr>`;

    try {
      const r = await App.api('paciente/listar.php', {
        q: filtro.value.trim(),
        estatus: estatus.value,
      });
      pintar(r.datos);
    } catch (e) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="5">
          No se pudo cargar la lista.</td></tr>`;
    }
  }

  function pintar(lista) {
    contador.textContent = lista.length
      ? `${lista.length} resultado${lista.length === 1 ? '' : 's'}`
      : '';

    if (!lista.length) {
      tabla.innerHTML = `<tr class="fila-vacia"><td colspan="5">
          <i class="bi bi-search fs-3 d-block mb-2 opacity-50"></i>
          No se encontraron pacientes con ese criterio.</td></tr>`;
      return;
    }

    tabla.innerHTML = lista.map((p) => {
      const detalle = `${App.URL_BASE}/index.php?modulo=paciente`
        + `&vista=detalle_paciente&id=${p.id_registro_paciente}`;

      return `
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <a href="${detalle}" class="avatar-mini" title="Abrir el expediente">
              ${p.foto
                ? `<img src="${App.URL_BASE}/avatar.php?p=${p.id_persona}" alt="">`
                : '<i class="bi bi-person-fill"></i>'}
            </a>
            <div style="min-width:0">
              <a href="${detalle}" class="enlace-paciente">
                ${App.esc(p.nombre_completo)}
              </a>
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(p.num_expediente)}
                ${Number(p.estatus) === 0
                  ? '<span class="badge bg-secondary ms-1">BAJA</span>' : ''}
              </div>
            </div>
          </div>
        </td>
        <td>
          ${p.domicilio_calle || p.domicilio_resto ? `
            <div class="domicilio-calle">${App.esc(p.domicilio_calle || '')}</div>
            <div class="domicilio-resto">${App.esc(p.domicilio_resto || '')}</div>`
            : '<span class="text-secondary opacity-50" style="font-size:12.5px">Sin domicilio</span>'}
        </td>
        <td>${p.telefono ? App.esc(p.telefono) : '<span class="text-secondary opacity-50">-</span>'}</td>
        <td class="text-secondary sin-mayusculas">${p.correo ? App.esc(p.correo) : '-'}</td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones">
              <i class="bi bi-list fs-5"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="${detalle}">
                <i class="bi bi-person-vcard me-2"></i>Datos del paciente</a></li>
              <li><a class="dropdown-item" href="${App.URL_BASE}/index.php?modulo=consultas&vista=consulta&paciente=${p.id_registro_paciente}">
                <i class="bi bi-clipboard2-plus me-2"></i>Nueva consulta</a></li>
              <li><a class="dropdown-item" href="${App.URL_BASE}/index.php?modulo=consultas&vista=nuevo_plan_tratamiento&id=${p.id_registro_paciente}">
                <i class="bi bi-list-check me-2"></i>Nuevo plan de tratamiento</a></li>
            </ul>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  filtro.addEventListener('input', App.debounce(cargar, 350));
  estatus.addEventListener('change', cargar);
  limpiar.addEventListener('click', () => { filtro.value = ''; cargar(); filtro.focus(); });

  cargar();

  if (new URLSearchParams(location.search).get('alta') === '1') {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscarAlta')).show();
  }
}

/* ---------------------------------------------------------------------
   Alta de paciente: buscar -> agregar -> guardar
   --------------------------------------------------------------------- */
function altaPaciente() {
  const modalBuscar = document.getElementById('modalBuscarAlta');
  if (!modalBuscar) return;

  const formBuscar   = document.getElementById('formBuscarAlta');
  const resultado    = document.getElementById('resultadoBuscarAlta');
  const listaCoinc   = document.getElementById('listaCoincidencias');
  const avisoCoinc   = document.getElementById('avisoCoincidencias');
  const btnAgregar   = document.getElementById('btnAgregarNuevo');
  const modalGuardar = document.getElementById('modalGuardarPaciente');
  const formGuardar  = document.getElementById('formGuardarPaciente');

  /* -------------------------------------------------------------------
     ¿Mayor de edad? Entonces hay que preguntar por el tutor
     -------------------------------------------------------------------
     El bloque aparece y desaparece segun la fecha de nacimiento. En un
     menor no se pregunta —lo decide la ley— y ofrecer el interruptor
     invitaria a apagarlo por error, dejando el expediente de un niño
     sin quien firme por el.

     La edad se calcula aqui solo para DECIDIR QUE ENSENIAR. Quien la
     calcula de verdad, para guardarla, es PHP: lo que llega del
     navegador no decide nada que importe.
  */
  const nacimAlta  = document.getElementById('gp_nacim');
  const bloqueTutor = document.getElementById('gpBloqueTutor');

  function ajustarBloqueTutor() {
    if (!nacimAlta || !bloqueTutor) return;

    let esMayor = false;
    if (nacimAlta.value) {
      const nace = new Date(nacimAlta.value + 'T00:00:00');
      const hoy  = new Date();
      let anios = hoy.getFullYear() - nace.getFullYear();
      // Resta uno si todavia no ha cumplido este anio: comparar solo
      // los anios da dieciocho a quien los cumple en diciembre.
      const m = hoy.getMonth() - nace.getMonth();
      if (m < 0 || (m === 0 && hoy.getDate() < nace.getDate())) anios--;
      esMayor = anios >= 18;
    }

    bloqueTutor.classList.toggle('d-none', !esMayor);

    // Al esconderlo se deja en "no responde por su cuenta" para no
    // mandar una respuesta que nadie dio. De todas formas PHP ignora
    // este campo en un menor.
    if (!esMayor) document.getElementById('gpTutorNo').checked = true;
  }

  nacimAlta?.addEventListener('change', ajustarBloqueTutor);
  nacimAlta?.addEventListener('input', ajustarBloqueTutor);

  formBuscar.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const datos = App.formADatos(formBuscar);

    if (!datos.nombres.trim() || !datos.ap_paterno.trim()) {
      App.alerta.error('Captura al menos nombres y apellido paterno.');
      return;
    }

    const r = await App.api('paciente/buscar.php', datos);
    const lista = r.datos;

    resultado.classList.remove('d-none');
    btnAgregar.classList.remove('d-none');

    if (!lista.length) {
      listaCoinc.innerHTML = `<tr class="fila-vacia"><td colspan="4">
          Sin coincidencias. Puedes darlo de alta.</td></tr>`;
      avisoCoinc.textContent = 'No existe un paciente con ese nombre.';
    } else {
      avisoCoinc.textContent =
        `${lista.length} coincidencia${lista.length === 1 ? '' : 's'}. Verifica antes de dar de alta.`;
      listaCoinc.innerHTML = lista.map((p) => `
        <tr>
          <td class="fw-semibold">${App.esc(p.nombre_completo)}</td>
          <td class="text-secondary">${App.esc(p.num_expediente)}</td>
          <td>${p.telefono ? App.esc(p.telefono) : '-'}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-morado"
               href="${App.URL_BASE}/index.php?modulo=paciente&vista=detalle_paciente&id=${p.id_registro_paciente}">
               Seleccionar</a>
          </td>
        </tr>`).join('');
    }
  });

  btnAgregar.addEventListener('click', () => {
    const d = App.formADatos(formBuscar);
    formGuardar.querySelector('[name="nombres"]').value    = d.nombres;
    formGuardar.querySelector('[name="ap_paterno"]').value = d.ap_paterno;
    formGuardar.querySelector('[name="ap_materno"]').value = d.ap_materno;

    bootstrap.Modal.getInstance(modalBuscar).hide();
    bootstrap.Modal.getOrCreateInstance(modalGuardar).show();
  });

  formGuardar.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    App.alerta.cargando('Guardando paciente...');
    try {
      const r = await App.api('paciente/guardar.php', App.formADatos(formGuardar));
      App.alerta.cerrar();

      // Se avisa de los pendientes desde el primer momento, para que
      // quien captura sepa que el expediente todavia no esta completo.
      await Swal.fire({
        icon: 'success',
        title: 'Paciente registrado',
        html: `<div style="font-size:14px;line-height:1.6">
                 Expediente <b>${App.esc(r.datos.num_expediente)}</b>
                 ${Number(r.datos.pendientes) > 0 ? `
                   <div style="background:#fff6e5;border:1px solid #f0d9a8;
                               border-radius:9px;padding:.7rem .9rem;
                               color:#7a5a12;margin-top:.9rem;font-size:12.5px">
                     Quedaron <b>${r.datos.pendientes}</b> documento(s) pendientes
                     de digitalizar. Los ves en la pestaña
                     <b>Expediente electrónico</b>.
                   </div>` : ''}
               </div>`,
        confirmButtonColor: '#7b2d8e',
        confirmButtonText: 'Ir al expediente',
      });

      App.ir(`index.php?modulo=paciente&vista=detalle_paciente&id=${r.datos.id_registro_paciente}`);
    } catch (e) {
      App.alerta.cerrar();
      App.marcarErrores(formGuardar, e.errores);
    }
  });

  modalBuscar.addEventListener('hidden.bs.modal', () => {
    formBuscar.reset();
    resultado.classList.add('d-none');
    btnAgregar.classList.add('d-none');
    avisoCoinc.textContent = '';
  });
}

/* ---------------------------------------------------------------------
   Alta y modificacion de canales de contacto (modal compartido)

   Lo usan el paciente y el tutor. Quien lo abre le dice a que persona
   pertenece y que hacer al terminar.
   --------------------------------------------------------------------- */
const Contactos = {
  alGuardar: null,

  abrir({ idPersona, nombre, contacto = null, alGuardar }) {
    const modal = document.getElementById('modalContacto');
    if (!modal) return;

    this.alGuardar = alGuardar;

    document.getElementById('contactoId').value      = contacto?.id_contacto_persona ?? '';
    document.getElementById('contactoPersona').value = idPersona;
    document.getElementById('contactoValor').value   = contacto?.valor_contacto ?? '';
    document.getElementById('contactoPrincipal').checked =
      Number(contacto?.es_principal ?? 0) === 1;

    if (contacto?.id_medio_contacto) {
      document.getElementById('contactoMedio').value = contacto.id_medio_contacto;
    }

    document.getElementById('tituloModalContacto').textContent =
      contacto ? 'Modificar medio de contacto' : 'Nuevo medio de contacto';
    document.querySelector('#contactoDe span').textContent = nombre ?? '';

    this.ajustarAyuda();
    bootstrap.Modal.getOrCreateInstance(modal).show();
    setTimeout(() => document.getElementById('contactoValor').focus(), 300);
  },

  /** El tipo de dato del medio decide como se comporta el campo. */
  ajustarAyuda() {
    const medio = document.getElementById('contactoMedio');
    const valor = document.getElementById('contactoValor');
    const ayuda = document.getElementById('contactoAyuda');
    const tipo  = medio.selectedOptions[0]?.dataset.tipo ?? 'texto';

    valor.classList.toggle('sin-mayusculas', tipo === 'email');

    if (tipo === 'tel') {
      valor.type = 'tel';
      valor.maxLength = 15;
      valor.placeholder = '3111234567';
      ayuda.textContent = '10 dígitos, sin espacios ni guiones.';
    } else if (tipo === 'email') {
      valor.type = 'email';
      valor.maxLength = 120;
      valor.placeholder = 'nombre@correo.com';
      ayuda.textContent = 'Se guarda en minúsculas.';
    } else {
      valor.type = 'text';
      valor.maxLength = 150;
      valor.placeholder = '';
      ayuda.textContent = medio.selectedOptions[0]?.dataset.desc ?? '';
    }
  },
};

function gestorContactos() {
  const modal = document.getElementById('modalContacto');
  if (!modal) return;

  const form = document.getElementById('formContacto');

  document.getElementById('contactoMedio')
    .addEventListener('change', () => Contactos.ajustarAyuda());

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const datos = App.formADatos(form);
    datos.es_principal = document.getElementById('contactoPrincipal').checked ? 1 : '';

    if (!datos.valor_contacto.trim()) {
      App.alerta.error('Captura el valor del contacto.');
      return;
    }

    try {
      await App.api('paciente/contacto_guardar.php', datos);
      bootstrap.Modal.getInstance(modal).hide();
      App.alerta.toast('Contacto guardado');
      Contactos.alGuardar?.();
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  });
}

/* ---------------------------------------------------------------------
   Vista detalle_paciente
   --------------------------------------------------------------------- */
function detallePaciente() {
  // El id viajaba en el contenedor de pestanias. Esas pestanias
  // desaparecieron en octubre 2026 y la funcion entera se estaba
  // cortando en esta linea: la ficha se dibujaba bien pero no
  // respondia a nada —ni contactos, ni domicilios, ni tutores, ni
  // ocupacion— sin un solo error en la consola.
  const contexto = document.getElementById('pacienteContexto');
  if (!contexto) return;

  const idExpediente = contexto.dataset.idExpediente;
  const idPersona    = contexto.dataset.idPersona;

  /* -------------------------------------------------------------------
     ¿Este paciente ya es mayor y nadie ha dicho si lleva tutor?
     -------------------------------------------------------------------
     Se pregunta UNA vez y la respuesta se guarda. El caso que motiva
     esto es el niño que lleva años viniendo y un dia cumple dieciocho:
     nadie se acuerda de revisar su expediente, y el sistema sigue
     pidiendo la firma de su mama.

     Quien decide si toca preguntar es PHP, no esta linea: la misma
     regla la usan el guardado y los documentos impresos, y calcularla
     aqui por separado acabaria en un aviso que sale y no se puede
     quitar.
  */
  if (contexto.dataset.preguntarTutor) {
    preguntarPorElTutor(idExpediente, contexto.dataset.edad);
  }

  async function preguntarPorElTutor(expediente, edad) {
    const r = await Swal.fire({
      title: `Este paciente ya tiene ${edad} años`,
      html: '¿Sigue necesitando que un tutor firme por él, o ya responde '
          + 'por su cuenta?<br><span class="text-secondary small">'
          + 'Se puede cambiar después en sus datos generales.</span>',
      icon: 'question',
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: 'Responde por su cuenta',
      confirmButtonColor: '#7b2d8e',
      denyButtonText: 'Sigue con tutor',
      denyButtonColor: '#6c6577',
      cancelButtonText: 'Ahora no',
      reverseButtons: true,
      // Que se pueda salir sin contestar es a proposito: quien esta
      // atendiendo a alguien enfrente no tiene por que resolver esto
      // ahora. Vuelve a preguntar la proxima vez.
      allowOutsideClick: true,
    });

    if (r.isDismissed) return;

    try {
      await App.api('paciente/tutor_decidir.php', {
        id_registro_paciente: expediente,
        requiere_tutor: r.isConfirmed ? 0 : 1,
      });
      // Se recarga porque la respuesta cambia la ficha entera: aparece
      // o desaparece el bloque del tutor, y cambia quien firma los
      // documentos. Retocar el DOM a mano dejaria la mitad sin
      // enterarse.
      location.reload();
    } catch (e) { /* App.api ya aviso */ }
  }

  /* ---------- Pestania Identificacion ---------- */
  const formIdent = document.getElementById('formIdentificacion');
  const campoNacim = formIdent?.querySelector('[name="fecha_nacim"]');
  const ocupTexto  = document.getElementById('ocupacionTexto');
  const ocupId     = document.getElementById('ocupacionId');
  const ocupAviso  = document.getElementById('ocupacionAviso');

  async function ocupacionPorEdad() {
    if (!ocupTexto) return;
    const anios = aniosDe(campoNacim?.value);
    if (anios === null) { ocupAviso.textContent = ''; return; }

    if (anios >= 16) {
      ocupAviso.textContent = 'Selecciona la ocupación.';
      return;
    }

    const clave = anios < 3 ? 'NINGUNA' : 'ESTUDIANTE';
    const r = await App.api('catalogo/ocupaciones.php', { q: clave });
    const encontrada = r.datos.find((o) => o.clave === clave);
    if (!encontrada) return;

    ocupTexto.value = encontrada.nombre_ocupacion;
    ocupId.value    = encontrada.id_ocupacion;
    ocupAviso.textContent = anios < 3
      ? 'Asignada automáticamente (menor de 3 años).'
      : 'Asignada automáticamente (de 3 a 15 años).';
  }

  campoNacim?.addEventListener('change', ocupacionPorEdad);
  if (ocupTexto && !ocupId.value) ocupacionPorEdad();
  ocupTexto?.addEventListener('change', () => {
    if (ocupAviso) ocupAviso.textContent = '';
  });

  formIdent?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await App.api('paciente/actualizar_identificacion.php', {
        ...App.formADatos(formIdent),
        id_registro_paciente: idExpediente,
      });
      App.alerta.toast('Datos actualizados');
    } catch (e) {
      App.marcarErrores(formIdent, e.errores);
    }
  });

  /* ---------- Pestania Canales de contacto ---------- */
  const nombrePaciente =
    document.querySelector('.card-identidad .nombre')?.textContent.trim() ?? '';

  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-accion="nuevo-contacto"][data-persona]');
    if (!btn) return;
    ev.preventDefault();
    Contactos.abrir({
      idPersona: btn.dataset.persona,
      nombre: btn.dataset.nombre,
      alGuardar: () => App.recargarEnPestania('#sec-contacto', 500),
    });
  });

  document.getElementById('tablaContactos')?.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const fila = opcion.closest('tr');
    const id   = fila.dataset.id;

    if (opcion.dataset.accion === 'eliminar-contacto') {
      const ok = await App.alerta.confirmar(
        '¿Eliminar este medio de contacto?',
        'El dato deja de aparecer, pero se conserva en el historial.',
        'Sí, eliminar');
      if (!ok) return;

      try {
        await App.api('paciente/contacto_eliminar.php', { id_contacto_persona: id });
        fila.remove();
        App.alerta.toast('Contacto eliminado');
      } catch (e) { /* ya se mostro */ }
    }

    if (opcion.dataset.accion === 'editar-contacto') {
      Contactos.abrir({
        idPersona,
        nombre: nombrePaciente,
        contacto: JSON.parse(fila.dataset.contacto),
        alGuardar: () => App.recargarEnPestania('#sec-contacto', 500),
      });
    }
  });

  /* ---------- Pestania Domicilio ---------- */
  const domicilio = new ControlDomicilio({
    form: 'formDomicilio',
    estado: 'dom_estado',
    municipio: 'dom_municipio',
    colonia: 'dom_colonia',
    coloniaTexto: 'dom_colonia_texto',
    cp: 'dom_cp',
    idDomicilio: 'dom_id',
    aviso: 'avisoDomicilio',
    btnGuardar: 'btnDomicilioGuardar',
    dependientes: '.campo-domicilio',
    // Al recargar se vuelve a la pestania de Domicilio, no a la primera.
    alGuardar: () => App.recargarEnPestania('#sec-domicilio', 900),
  });
  domicilio.iniciar();

  document.getElementById('tablaDomicilios')?.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const fila = opcion.closest('tr');

    if (opcion.dataset.accion === 'eliminar-domicilio') {
      const ok = await App.alerta.confirmar(
        '¿Eliminar este domicilio?',
        'Deja de aparecer en el expediente, pero no se borra de la base.',
        'Sí, eliminar');
      if (!ok) return;
      try {
        await App.api('paciente/domicilio_eliminar.php', {
          id_domicilio: fila.dataset.id, id_persona: idPersona,
        });
        fila.remove();
        App.alerta.toast('Domicilio eliminado');
      } catch (e) { /* ya se mostro */ }
    }

    if (opcion.dataset.accion === 'editar-domicilio') {
      // Se edita desde la lista del tutor, que esta en otra seccion.
      // Antes esto picaba la pestania de domicilio; ahora es una
      // seccion plegable y lo que hay que hacer es abrirla.
      App.activarPestania('#sec-domicilio');
      await domicilio.editar(fila.dataset.id);
    }
  });

  /* ---------- Pestania Tutor: alta ---------- */
  const modalTutor = document.getElementById('modalTutor');

  document.addEventListener('click', (ev) => {
    if (!ev.target.closest('[data-accion="nuevo-tutor"]')) return;
    ev.preventDefault();
    bootstrap.Modal.getOrCreateInstance(modalTutor).show();
  });

  const formBuscarTutor  = document.getElementById('formBuscarTutor');
  const formGuardarTutor = document.getElementById('formGuardarTutor');
  const btnCapturarTutor = document.getElementById('btnCapturarTutor');
  const btnGuardarTutor  = document.getElementById('btnGuardarTutor');
  const resultadoTutor   = document.getElementById('resultadoTutor');
  const listaTutores     = document.getElementById('listaTutores');

  formBuscarTutor?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const r = await App.api('paciente/tutor_buscar.php', App.formADatos(formBuscarTutor));
    resultadoTutor.classList.remove('d-none');

    listaTutores.innerHTML = r.datos.length
      ? r.datos.map((t) => `
          <tr>
            <td class="fw-semibold">${App.esc(t.nombre_completo)}</td>
            <td class="text-secondary">${t.rfc ? App.esc(t.rfc) : '-'}</td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-morado"
                      data-vincular="${t.id_persona}">Vincular</button>
            </td>
          </tr>`).join('')
      : `<tr class="fila-vacia"><td colspan="3">Sin coincidencias.</td></tr>`;
  });

  listaTutores?.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('[data-vincular]');
    if (!btn) return;

    const { value: parentesco } = await Swal.fire({
      title: 'Parentesco con el paciente',
      input: 'select',
      inputOptions: Object.fromEntries(
        [...document.querySelectorAll('#formGuardarTutor [name="id_parentesco"] option')]
          .filter((o) => o.value)
          .map((o) => [o.value, o.textContent.trim()])),
      showCancelButton: true, confirmButtonText: 'Vincular',
      confirmButtonColor: '#7b2d8e', cancelButtonText: 'Cancelar',
    });
    if (!parentesco) return;

    try {
      await App.api('paciente/tutor_vincular.php', {
        id_registro_paciente: idExpediente,
        id_persona: btn.dataset.vincular,
        id_parentesco: parentesco,
      });
      App.alerta.toast('Tutor vinculado');
      App.recargarEnPestania('#sec-tutor', 900);
    } catch (e) { /* ya se mostro */ }
  });

  btnCapturarTutor?.addEventListener('click', () => {
    const d = App.formADatos(formBuscarTutor);
    formGuardarTutor.querySelector('[name="nombres"]').value    = d.nombres;
    formGuardarTutor.querySelector('[name="ap_paterno"]').value = d.ap_paterno;
    formGuardarTutor.querySelector('[name="ap_materno"]').value = d.ap_materno;

    document.getElementById('pasoBuscarTutor').classList.add('d-none');
    formGuardarTutor.classList.remove('d-none');
    btnGuardarTutor.classList.remove('d-none');
  });

  btnGuardarTutor?.addEventListener('click', async () => {
    if (!formGuardarTutor.reportValidity()) return;
    try {
      await App.api('paciente/tutor_guardar.php', {
        ...App.formADatos(formGuardarTutor),
        id_registro_paciente: idExpediente,
      });
      App.alerta.toast('Tutor registrado');
      App.recargarEnPestania('#sec-tutor', 900);
    } catch (e) {
      App.marcarErrores(formGuardarTutor, e.errores);
    }
  });

  document.getElementById('tablaTutores')?.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion="eliminar-tutor"]');
    if (!opcion) return;
    ev.preventDefault();

    const fila = opcion.closest('tr');
    const ok = await App.alerta.confirmar(
      '¿Eliminar este tutor del expediente?',
      'El registro del tutor se conserva, solo deja de estar vinculado.',
      'Sí, eliminar');
    if (!ok) return;

    try {
      await App.api('paciente/tutor_eliminar.php', {
        id_expediente_tutor: fila.dataset.id,
      });
      fila.remove();
      App.alerta.toast('Tutor desvinculado');
    } catch (e) { /* ya se mostro */ }
  });

  modalTutor?.addEventListener('hidden.bs.modal', () => {
    formBuscarTutor.reset();
    formGuardarTutor.reset();
    resultadoTutor.classList.add('d-none');
    formGuardarTutor.classList.add('d-none');
    btnGuardarTutor.classList.add('d-none');
    document.getElementById('pasoBuscarTutor').classList.remove('d-none');
  });
}

/* ---------------------------------------------------------------------
   Modal de modificacion del tutor
   --------------------------------------------------------------------- */
function tutorEditar() {
  const modal = document.getElementById('modalTutorEditar');
  if (!modal) return;

  const bsModal   = bootstrap.Modal.getOrCreateInstance(modal);
  const idVinculo = document.getElementById('teIdVinculo');
  const idPersona = document.getElementById('teIdPersona');
  const formIdent = document.getElementById('teFormIdentificacion');
  const campoRfc  = document.getElementById('teRfc');
  const campoNac  = document.getElementById('teFechaNacim');
  const avisoRfc  = document.getElementById('teAvisoRfc');
  const avisoVinc = document.getElementById('teAvisoVinculado');

  let domicilio  = null;
  let datosTutor = null;
  let yaPregunto = false;   // no insistir con la vinculacion en cada clic

  /* ---------- Fecha de nacimiento a partir del RFC ---------- */
  function intentarFechaDesdeRfc({ forzar = false } = {}) {
    const fecha = fechaDesdeRfc(campoRfc.value);

    if (!fecha) {
      avisoRfc.textContent = '';
      return;
    }

    // Solo se llena si esta vacio, salvo que se pida forzar. No se le
    // pisa al usuario un dato que el mismo capturo.
    if (campoNac.value && !forzar) {
      avisoRfc.textContent = campoNac.value === fecha
        ? 'Coincide con el RFC.'
        : 'Ojo: no coincide con la fecha del RFC (' + fecha.split('-').reverse().join('/') + ').';
      return;
    }

    llenarConAviso(campoNac, fecha);
    avisoRfc.textContent = 'Tomada del RFC.';
    setTimeout(() => { avisoRfc.textContent = ''; }, 4000);
  }

  campoRfc.addEventListener('input', App.debounce(() => intentarFechaDesdeRfc(), 400));
  campoRfc.addEventListener('blur', () => intentarFechaDesdeRfc());

  /* ---------- Identificacion oficial ---------- */
  const tipoIdent     = document.getElementById('teTipoIdent');
  const vigenciaIdent = document.getElementById('teVigenciaIdent');
  const avisoVigencia = document.getElementById('teAvisoVigencia');

  /**
   * Los campos de la credencial solo tienen sentido si se eligio un
   * tipo. Y la vigencia se apaga en las que no caducan, como la cedula
   * profesional: pedirla seria pedir un dato que no existe.
   */
  function ajustarIdentificacion() {
    const hayTipo = !!tipoIdent.value;
    const caduca  = tipoIdent.selectedOptions[0]?.dataset.vigencia === '1';

    document.querySelectorAll('.ident-campo').forEach((el) => {
      el.disabled = !hayTipo;
      el.classList.toggle('campo-bloqueado', !hayTipo);
    });

    if (hayTipo && !caduca) {
      vigenciaIdent.disabled = true;
      vigenciaIdent.value = '';
      vigenciaIdent.classList.add('campo-bloqueado');
      avisoVigencia.textContent = 'Esta identificación no caduca.';
      return;
    }

    avisoVigencia.textContent = '';

    // Si ya vencio, se avisa: una credencial caduca no sirve para
    // firmar un consentimiento.
    if (vigenciaIdent.value) {
      const vence = new Date(vigenciaIdent.value + 'T00:00:00');
      if (vence < new Date()) {
        avisoVigencia.innerHTML =
          '<span style="color:var(--error)">Vencida. Pide una vigente.</span>';
      }
    }
  }

  tipoIdent.addEventListener('change', ajustarIdentificacion);
  vigenciaIdent.addEventListener('change', ajustarIdentificacion);

  /* ---------- Abrir ---------- */
  document.addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion="editar-tutor"]');
    if (!opcion) return;
    ev.preventDefault();

    const fila = opcion.closest('tr');
    App.alerta.cargando('Cargando datos del tutor...');

    try {
      const r = await App.api('paciente/tutor_obtener.php', {
        id_expediente_tutor: fila.dataset.id,
      });
      App.alerta.cerrar();
      yaPregunto = false;
      llenar(r.datos);
      bsModal.show();
    } catch (e) {
      App.alerta.cerrar();
    }
  });

  function llenar(t) {
    datosTutor = t;
    idVinculo.value = t.id_expediente_tutor;
    idPersona.value = t.id_persona;
    document.getElementById('teFormDomicilio').dataset.idPersona = t.id_persona;

    document.getElementById('teNombre').textContent =
      [t.nombres, t.ap_paterno, t.ap_materno].filter(Boolean).join(' ');

    formIdent.querySelector('[name="nombres"]').value       = t.nombres ?? '';
    formIdent.querySelector('[name="ap_paterno"]').value    = t.ap_paterno ?? '';
    formIdent.querySelector('[name="ap_materno"]').value    = t.ap_materno ?? '';
    formIdent.querySelector('[name="fecha_nacim"]').value   = t.fecha_nacim ?? '';
    formIdent.querySelector('[name="id_genero"]').value     = t.id_genero ?? '';
    formIdent.querySelector('[name="rfc"]').value           = t.rfc ?? '';
    formIdent.querySelector('[name="id_parentesco"]').value = t.id_parentesco ?? '';
    formIdent.querySelector('[name="es_responsable_pago"]').checked =
      Number(t.es_responsable_pago) === 1;

    document.getElementById('teOcupacionTexto').value = t.nombre_ocupacion ?? '';
    document.getElementById('teOcupacionId').value    = t.id_ocupacion ?? '';

    // Identificacion oficial
    const ident = t.identificacion;
    document.getElementById('teTipoIdent').value     = ident?.id_tipo_identificacion ?? '';
    document.getElementById('teNumIdent').value      = ident?.numero_identificacion ?? '';
    document.getElementById('teEmisionIdent').value  = ident?.fecha_emision ?? '';
    document.getElementById('teVigenciaIdent').value = ident?.fecha_vigencia ?? '';
    ajustarIdentificacion();

    avisoRfc.textContent = '';

    // Si el tutor traia RFC pero no fecha, se deduce al abrir.
    if (t.rfc && !t.fecha_nacim) {
      setTimeout(() => intentarFechaDesdeRfc(), 400);
    }

    pintarContactos(t.contactos);
    pintarDomicilios(t.domicilios);

    if (domicilio === null) {
      domicilio = new ControlDomicilio({
        form: 'teFormDomicilio',
        estado: 'teDomEstado',
        municipio: 'teDomMunicipio',
        colonia: 'teDomColonia',
        coloniaTexto: 'teDomColoniaTexto',
        cp: 'teDomCp',
        idDomicilio: 'teDomId',
        aviso: 'teAvisoDomicilio',
        btnGuardar: 'teBtnDomicilioGuardar',
        dependientes: '.te-campo-domicilio',
        // Guardar el domicilio del tutor cierra el modal y devuelve a la
        // pestania Tutor, donde la lista ya muestra el domicilio nuevo.
        // Quedarse dentro del modal obligaria a cerrarlo a mano solo para
        // comprobar que el cambio si quedo.
        alGuardar: () => marcarCambioTutor(),
      });

      // Al pedir domicilio nuevo desaparece el aviso de vinculado.
      document.getElementById('teFormDomicilio')
        .addEventListener('domicilio:limpio', () => avisoVinc.classList.add('d-none'));
    }

    if (t.domicilios.length) {
      cargarDomicilioEnFormulario(t.domicilios[0]);
    } else {
      domicilio.limpiar();
      avisoVinc.classList.add('d-none');
    }
  }

  async function cargarDomicilioEnFormulario(dom) {
    await domicilio.editar(dom.id_domicilio);
    // El aviso amarillo solo aparece si de verdad lo comparte el paciente.
    avisoVinc.classList.toggle('d-none',
      Number(dom.compartido_con_paciente ?? 0) !== 1);
  }

  /* ---------- Ofrecer vincular con el domicilio del paciente ---------- */
  document.querySelector('[data-bs-target="#te-domicilio"]')
    ?.addEventListener('shown.bs.tab', () => ofrecerVinculo());

  async function ofrecerVinculo() {
    if (yaPregunto || !datosTutor) return;
    if (datosTutor.domicilios.length > 0) return;      // ya tiene domicilio

    const dp = datosTutor.domicilio_paciente;
    if (!dp) return;                                    // el paciente tampoco tiene

    yaPregunto = true;

    const linea = [
      dp.calle_numero,
      dp.colonia ? `${dp.colonia}` : null,
      dp.codigo_postal ? `C.P. ${dp.codigo_postal}` : null,
      dp.nombre_municipio,
      dp.nombre_estado,
    ].filter(Boolean).join(', ');

    const r = await Swal.fire({
      title: '¿Vincular con el domicilio del paciente?',
      html: `<div style="text-align:left;font-size:14px;line-height:1.6">
               <div style="background:#f7e6f2;border-radius:9px;padding:.7rem .9rem;
                           color:#5f2472;margin-bottom:.8rem">
                 ${App.esc(linea)}
                 ${dp.referencia ? `<div style="font-size:12px;opacity:.8;margin-top:.3rem">
                    ${App.esc(dp.referencia)}</div>` : ''}
               </div>
               <div style="color:#6c6577;font-size:12.5px">
                 Si eliges <b>Sí</b>, ambos comparten el mismo domicilio y
                 cualquier corrección se refleja en los dos.
               </div>
             </div>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, es el mismo',
      confirmButtonColor: '#7b2d8e',
      cancelButtonText: 'No, capturar otro',
      cancelButtonColor: '#6c6577',
      reverseButtons: true,
    });

    if (!r.isConfirmed) {
      domicilio.limpiar();
      return;
    }

    try {
      await App.api('paciente/domicilio_vincular.php', {
        id_persona: idPersona.value,
        id_domicilio: dp.id_domicilio,
      });
      App.alerta.toast('Domicilio vinculado');
      marcarCambioTutor();
    } catch (e) { /* ya se mostro */ }
  }

  /*
     Guardar un dato del tutor YA NO cierra el modal.

     Cerraba y recargaba la pagina entera tras cada guardado, y corregir
     tres cosas del mismo tutor —el telefono, el parentesco y la
     credencial— costaba tres cierres, tres recargas y tres viajes de
     vuelta a abrir el mismo modal. Un formulario que se cierra solo
     cuando guardas castiga justo al que esta capturando con cuidado.

     Lo que se hace ahora: se refresca el contenido del modal con los
     datos recien guardados y se anota que la ficha de atras quedo
     desactualizada. La recarga se hace UNA vez, cuando la persona
     cierra el modal porque ya acabo.
  */
  let hayCambiosTutor = false;

  function marcarCambioTutor() {
    hayCambiosTutor = true;
    // Los datos del propio modal si se refrescan al momento: las
    // tablas de contactos y domicilios tienen que mostrar lo que se
    // acaba de guardar.
    recargar().catch(() => { /* el aviso ya salio */ });
  }

  modal.addEventListener('hidden.bs.modal', () => {
    if (!hayCambiosTutor) return;
    hayCambiosTutor = false;
    // Sin retraso: el modal ya se cerro y no hay nada que esperar.
    App.recargarEnPestania('#sec-tutor', 0);
  });

  async function recargar() {
    const r = await App.api('paciente/tutor_obtener.php', {
      id_expediente_tutor: idVinculo.value,
    });
    datosTutor = r.datos;
    pintarContactos(r.datos.contactos);
    pintarDomicilios(r.datos.domicilios);

    if (r.datos.domicilios.length) {
      await cargarDomicilioEnFormulario(r.datos.domicilios[0]);
    }
  }

  function pintarContactos(lista) {
    const tabla = document.getElementById('teTablaContactos');
    document.getElementById('teContadorContactos').textContent = lista.length;

    tabla.innerHTML = lista.length ? lista.map((c, i) => `
      <tr data-id="${c.id_contacto_persona}"
          data-contacto='${App.esc(JSON.stringify(c))}'>
        <td class="text-secondary">${i + 1}</td>
        <td>${App.esc(c.nombre_medio_contacto)}
          ${Number(c.es_principal) === 1 ? '<span class="badge badge-suave ms-1">Principal</span>' : ''}
        </td>
        <td class="fw-semibold ${c.tipo_dato === 'email' ? 'sin-mayusculas' : ''}">
          ${App.esc(c.valor_contacto)}</td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#" data-accion="te-editar-contacto">
                <i class="bi bi-pencil me-2"></i>Modificar</a></li>
              <li><a class="dropdown-item text-danger" href="#" data-accion="te-eliminar-contacto">
                <i class="bi bi-trash me-2"></i>Eliminar</a></li>
            </ul>
          </div>
        </td>
      </tr>`).join('')
      : `<tr class="fila-vacia"><td colspan="4">Sin canales de contacto registrados.</td></tr>`;
  }

  function pintarDomicilios(lista) {
    const tabla = document.getElementById('teTablaDomicilios');
    document.getElementById('teContadorDomicilios').textContent = lista.length;

    tabla.innerHTML = lista.length ? lista.map((d, i) => `
      <tr data-id="${d.id_domicilio}">
        <td class="text-secondary">${i + 1}</td>
        <td>
          <span class="fw-semibold">${App.esc(d.calle_numero ?? '')}</span>
          ${Number(d.es_principal) === 1 ? '<span class="badge badge-suave ms-1">Principal</span>' : ''}
          ${Number(d.compartido_con_paciente) === 1
            ? '<span class="chip-estado chip-compartido ms-1">Mismo que el paciente</span>' : ''}
          ${d.referencia ? `<div class="text-secondary" style="font-size:11px">${App.esc(d.referencia)}</div>` : ''}
        </td>
        <td>${App.esc(d.colonia ?? '')}
          <span class="text-secondary">· ${App.esc(d.codigo_postal ?? '')}</span></td>
        <td>${App.esc(d.nombre_municipio ?? '')},
          <span class="text-secondary">${App.esc(d.nombre_estado ?? '')}</span></td>
        <td class="col-acciones">
          <div class="dropdown">
            <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                    aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#" data-accion="te-editar-domicilio">
                <i class="bi bi-pencil me-2"></i>Modificar domicilio</a></li>
              <li><a class="dropdown-item text-danger" href="#" data-accion="te-eliminar-domicilio">
                <i class="bi bi-trash me-2"></i>${Number(d.compartido_con_paciente) === 1
                  ? 'Desvincular del tutor' : 'Eliminar domicilio'}</a></li>
            </ul>
          </div>
        </td>
      </tr>`).join('')
      : `<tr class="fila-vacia"><td colspan="5">Sin domicilios registrados.</td></tr>`;
  }

  /* ---------- Guardar identificacion ---------- */
  formIdent.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      // Los campos deshabilitados no viajan en el FormData: se agregan
      // a mano para que el servidor reciba la identificacion completa.
      const datos = App.formADatos(formIdent);
      document.querySelectorAll('.ident-campo').forEach((el) => {
        if (el.name && datos[el.name] === undefined) datos[el.name] = el.value;
      });

      const r = await App.api('paciente/tutor_actualizar.php', {
        ...datos,
        id_expediente_tutor: idVinculo.value,
        id_persona: idPersona.value,
        id_registro_paciente: datosTutor?.id_registro_paciente ?? '',
      });

      if (r.datos?.pendiente_identificacion) {
        App.alerta.toast('Guardado. Falta escanear la identificación', 'info');
      } else {
        App.alerta.toast('Datos del tutor actualizados');
      }

      marcarCambioTutor();
    } catch (e) {
      App.marcarErrores(formIdent, e.errores);
    }
  });

  /* ---------- Contactos del tutor ---------- */
  document.getElementById('teBtnNuevoContacto').addEventListener('click', () => {
    Contactos.abrir({
      idPersona: idPersona.value,
      nombre: document.getElementById('teNombre').textContent,
      alGuardar: () => recargar(),
    });
  });

  document.getElementById('teTablaContactos').addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const fila = opcion.closest('tr');

    if (opcion.dataset.accion === 'te-eliminar-contacto') {
      const ok = await App.alerta.confirmar('¿Eliminar este medio de contacto?', '', 'Sí, eliminar');
      if (!ok) return;
      await App.api('paciente/contacto_eliminar.php', { id_contacto_persona: fila.dataset.id });
      App.alerta.toast('Contacto eliminado');
      recargar();
    }

    if (opcion.dataset.accion === 'te-editar-contacto') {
      Contactos.abrir({
        idPersona: idPersona.value,
        nombre: document.getElementById('teNombre').textContent,
        contacto: JSON.parse(fila.dataset.contacto),
        alGuardar: () => recargar(),
      });
    }
  });

  /* ---------- Domicilios del tutor ---------- */
  document.getElementById('teTablaDomicilios').addEventListener('click', async (ev) => {
    const opcion = ev.target.closest('[data-accion]');
    if (!opcion) return;
    ev.preventDefault();

    const fila = opcion.closest('tr');
    const dom  = datosTutor?.domicilios.find(
      (d) => String(d.id_domicilio) === String(fila.dataset.id));
    const compartido = Number(dom?.compartido_con_paciente ?? 0) === 1;

    if (opcion.dataset.accion === 'te-eliminar-domicilio') {
      const ok = await App.alerta.confirmar(
        compartido ? '¿Desvincular este domicilio del tutor?' : '¿Eliminar este domicilio?',
        compartido
          ? 'El paciente lo conserva; solo deja de estar ligado al tutor.'
          : 'Deja de aparecer, pero no se borra de la base.',
        'Sí, continuar');
      if (!ok) return;

      await App.api('paciente/domicilio_eliminar.php', {
        id_domicilio: fila.dataset.id, id_persona: idPersona.value,
      });
      App.alerta.toast(compartido ? 'Domicilio desvinculado' : 'Domicilio eliminado');
      marcarCambioTutor();
    }

    if (opcion.dataset.accion === 'te-editar-domicilio') {
      if (compartido) {
        const ok = await App.alerta.confirmar(
          'Este domicilio también es del paciente',
          'Los cambios que guardes van a modificar el domicilio del paciente. '
          + 'Si el tutor se mudó y el paciente no, mejor captura uno nuevo.',
          'Entendido, modificar');
        if (!ok) return;
      }
      await cargarDomicilioEnFormulario(dom ?? { id_domicilio: fila.dataset.id });
    }
  });
}

/* ---------------------------------------------------------------------
   Los planes de tratamiento ya no se listan aqui.

   Eran una pestania con su tabla. Octubre 2026: la ficha del paciente
   los muestra en una tarjeta lateral, dibujada en PHP junto con el
   resto de la pagina. Un listado que ya viene en el HTML no necesita
   pedirse por AJAX ni esperar a que alguien abra una pestania.
   --------------------------------------------------------------------- */
