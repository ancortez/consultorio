/* =====================================================================
   Expediente electronico

   Vive en su propio archivo aunque la pestania este dentro del detalle
   del paciente: es la parte que mas va a crecer y tenerla aparte evita
   que paciente.js se convierta en un archivo de dos mil lineas.

   Se carga desde paciente.js llamando a Expediente.iniciar().
   ===================================================================== */

const Expediente = (() => {

  let idExpediente = null;
  let modalSubir   = null;
  let modalNuevo   = null;
  let archivoElegido = null;

  // Tipos de documento por ambito, para armar el combo de "Agregar".
  let tiposPaciente = [];
  let tiposPlan     = [];

  // Que bloques estaban abiertos antes de recargar. Sin esto, subir un
  // archivo cerraria toda la carpeta y habria que volver a buscar donde
  // se estaba trabajando.
  let abiertos = new Set(['generales']);

  /* -------------------------------------------------------------------
     Carga y pintado
     ------------------------------------------------------------------- */
  async function cargar() {
    const contenedor = document.getElementById('expDocumentos');
    if (!contenedor) return;

    recordarAbiertos();

    contenedor.innerHTML = `<div class="cargando">
        <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
        Cargando expediente...</div>`;

    try {
      const r = await App.api('expediente/listar.php', {
        id_registro_paciente: idExpediente,
      });
      pintar(r.datos);
    } catch (e) {
      contenedor.innerHTML =
        `<div class="alert alert-danger mb-0">No se pudo cargar el expediente.</div>`;
    }
  }

  function recordarAbiertos() {
    const cajas = document.querySelectorAll('#expDocumentos .exp-bloque[data-clave]');
    if (!cajas.length) return;

    abiertos = new Set();
    cajas.forEach((caja) => {
      if (caja.querySelector('.exp-cuerpo')?.classList.contains('abierto')) {
        abiertos.add(caja.dataset.clave);
      }
    });
  }

  function pintar(datos) {
    const { almacen, pendientes } = datos;
    tiposPaciente = datos.tipos_paciente ?? [];
    tiposPlan     = datos.tipos_plan ?? [];

    // Aviso si MinIO no responde. Se dice que hacer, no solo que fallo.
    const aviso = document.getElementById('expAvisoAlmacen');
    aviso.classList.toggle('d-none', almacen.ok);
    if (!almacen.ok) {
      document.getElementById('expAvisoDetalle').textContent = almacen.mensaje;
    }

    // Resumen y resaltado de la pestania
    const resumen = document.getElementById('expResumen');
    resumen.classList.toggle('d-none', pendientes === 0);
    document.getElementById('expResumenTotal').textContent = pendientes;

    const badge = document.getElementById('expBadge');
    const boton = document.getElementById('btnTabExpediente');
    if (badge) {
      badge.textContent = pendientes;
      badge.className = 'badge ms-1 ' + (pendientes > 0 ? 'badge-pendiente' : 'badge-suave');
    }
    if (boton) {
      boton.classList.toggle('con-pendientes', pendientes > 0);
      boton.title = pendientes > 0
        ? `${pendientes} documento(s) sin digitalizar`
        : 'Expediente completo';
    }

    document.getElementById('expDocumentos').innerHTML =
      bloqueGeneral(datos) + datos.planes.map(bloquePlan).join('');
  }

  /* -------------------------------------------------------------------
     Bloque de arriba: lo que es del paciente, no de un tratamiento
     ------------------------------------------------------------------- */
  function bloqueGeneral(datos) {
    const grupos = tiposPaciente.map((t) =>
      grupo(t, datos.generales.filter((d) => d.clave === t.clave), null)).join('');

    const consultas = grupoConsultas(
      datos.consultas,
      'Consultas no ligadas a planes de tratamiento',
      'Revisiones, urgencias y valoraciones sueltas.');

    return bloque({
      clave: 'generales',
      icono: 'bi-person-vcard',
      titulo: 'Documentos generales del paciente',
      subtitulo: 'Identificaci&oacute;n, historial y consultas sueltas',
      pendientes: datos.pendientes_generales,
      cuerpo: grupos + consultas,
      plan: null,
    });
  }

  /* -------------------------------------------------------------------
     Un bloque por plan de tratamiento
     ------------------------------------------------------------------- */
  function bloquePlan(b) {
    const p = b.plan;
    const id = p.id_plan_tratamiento;

    const grupos = tiposPlan.map((t) =>
      grupo(t, b.documentos.filter((d) => d.clave === t.clave), p)).join('');

    // Los que no caen en ningun tipo esperado (un "OTRO" colgado del
    // plan) no se pierden: van al final.
    const claves = new Set(tiposPlan.map((t) => t.clave));
    const sueltos = b.documentos.filter((d) => !claves.has(d.clave));

    // Las consultas van despues del consentimiento y de la hoja del
    // plan, que son los dos papeles que sostienen todo lo demas y
    // tienen que quedar siempre a la vista al abrir el bloque.
    const consultas = grupoConsultas(
      b.consultas,
      'Consultas de este plan',
      'Citas en las que se trabaj&oacute; el tratamiento.');

    return bloque({
      clave: `plan-${id}`,
      icono: 'bi-list-check',
      titulo: `Plan ${App.esc(p.folio)}`,
      subtitulo: `${App.esc(App.fecha(p.fecha))} · ${App.esc(p.nombre_estado)}`
        + (p.diagnostico ? ` · ${App.esc(p.diagnostico.substring(0, 70))}` : ''),
      pendientes: b.pendientes,
      plan: p,
      cuerpo: grupos
        + (sueltos.length
            ? grupo({ clave: 'OTRO', nombre_documento: 'Otros documentos',
                      icono: 'bi-paperclip', permite_varios: 1 }, sueltos, p)
            : '')
        + consultas,
    });
  }

  /** Caja colapsable con su encabezado. */
  function bloque({ clave, icono, titulo, subtitulo, pendientes, cuerpo, plan }) {
    const abierto = abiertos.has(clave);

    return `
    <div class="exp-bloque" data-clave="${clave}">
      <button type="button" class="exp-cabecera ${abierto ? 'abierto' : ''}"
              data-plegar="${clave}">
        <span class="exp-flecha"><i class="bi bi-chevron-right"></i></span>
        <span class="exp-icono"><i class="bi ${icono}"></i></span>
        <span class="exp-titulo">
          <span class="t">${titulo}</span>
          <span class="s">${subtitulo}</span>
        </span>
        ${pendientes > 0
          ? `<span class="badge badge-pendiente">${pendientes} por escanear</span>`
          : `<span class="badge badge-suave">Completo</span>`}
      </button>

      <div class="exp-cuerpo ${abierto ? 'abierto' : ''}">
        ${plan ? barraPlan(plan) : ''}
        ${cuerpo}
      </div>
    </div>`;
  }

  /** Acciones propias del plan: consentimiento y hoja para firma. */
  function barraPlan(p) {
    return `
    <div class="exp-barra-plan">
      <a class="btn btn-sm btn-outline-morado"
         href="${App.URL_BASE}/index.php?modulo=consultas&vista=nuevo_plan_tratamiento&id=${idExpediente}&plan=${p.id_plan_tratamiento}">
        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir el plan
      </a>
      <button type="button" class="btn btn-sm btn-outline-morado"
              data-accion="exp-consentimiento" data-plan="${p.id_plan_tratamiento}">
        <i class="bi bi-file-earmark-check me-1"></i> Consentimiento
      </button>
      <button type="button" class="btn btn-sm btn-outline-morado"
              data-accion="exp-hoja-plan" data-plan="${p.id_plan_tratamiento}">
        <i class="bi bi-printer me-1"></i> Hoja del plan
      </button>
      <span class="ms-auto fw-semibold" style="color:var(--morado-800)">
        ${App.money(p.total)}
      </span>
    </div>`;
  }

  /* -------------------------------------------------------------------
     Un grupo = un tipo de documento con sus archivos
     ------------------------------------------------------------------- */
  function grupo(tipo, docs, plan) {
    const puedeEditar = document.getElementById('expNuevoPendiente') !== null;

    // Un tipo unico que ya tiene su documento no ofrece "agregar otro".
    const unico = Number(tipo.permite_varios) === 0;
    const puedeAgregar = puedeEditar && (!unico || docs.length === 0);

    // Para un tipo unico y vacio no tiene caso pedir primero el
    // pendiente y luego el archivo: son dos pasos para lo mismo. El
    // boton dice "Subir" y abre de una vez el dialogo de archivo.
    const accion = unico ? 'exp-subir-directo' : 'exp-agregar';

    return `
    <div class="exp-grupo">
      <div class="exp-grupo-titulo">
        <i class="bi ${App.esc(tipo.icono || 'bi-file-earmark')}"></i>
        <span>${App.esc(tipo.nombre_documento)}</span>
        ${docs.length ? `<span class="cuenta">${docs.length}</span>` : ''}
        ${puedeAgregar ? `
          <button type="button" class="btn btn-sm btn-link p-0 ms-auto"
                  data-accion="${accion}"
                  data-clave="${App.esc(tipo.clave)}"
                  data-plan="${plan ? plan.id_plan_tratamiento : ''}">
            <i class="bi bi-${unico ? 'cloud-arrow-up' : 'plus-lg'}"></i>
            ${unico ? 'Subir' : 'Agregar'}
          </button>` : ''}
      </div>

      ${docs.length
        ? `<div class="exp-tarjetas">${docs.map(tarjeta).join('')}</div>`
        : `<div class="exp-vacio">Sin documentos de este tipo.</div>`}
    </div>`;
  }

  /**
   * Las consultas, de la mas reciente a la mas antigua, cada una con
   * los documentos que salieron de ella colgando debajo.
   *
   * Anidarlos importa: una radiografia suelta en la lista del plan no
   * dice de que cita salio, y esa es justo la pregunta que se hace
   * quien revisa un expediente.
   */
  function grupoConsultas(lista, titulo, ayuda) {
    return `
    <div class="exp-grupo">
      <div class="exp-grupo-titulo">
        <i class="bi bi-clipboard2-pulse"></i>
        <span>${titulo}</span>
        ${lista.length ? `<span class="cuenta">${lista.length}</span>` : ''}
      </div>

      ${lista.length
        ? `<div class="exp-consultas">${lista.map(consulta).join('')}</div>`
        : `<div class="exp-vacio">${ayuda}</div>`}
    </div>`;
  }

  function consulta(c) {
    const cerrada = Number(c.cerrada) === 1;
    const docs = c.documentos ?? [];

    return `
    <div class="exp-consulta-caja">
      <div class="exp-consulta">
        <div class="fecha">
          <a href="${App.URL_BASE}/index.php?modulo=consultas&vista=consulta&id=${c.id_consulta}"
             class="enlace-paciente">${App.esc(c.folio ?? '')}</a>
          <div>${App.esc(App.fechaHora(c.fecha_hora))}</div>
        </div>
        <div class="flex-grow-1" style="min-width:0">
          <div class="fw-semibold">${App.esc(c.motivo || 'CONSULTA')}</div>
          <div class="text-secondary" style="font-size:11.5px">
            ${App.esc(c.dentista)}
            ${c.diagnostico ? ' · ' + App.esc(c.diagnostico.substring(0, 70)) : ''}
            ${Number(c.notas) > 0 ? ` · ${c.notas} nota(s)` : ''}
          </div>
        </div>
        <span class="chip-estado ${cerrada ? 'badge-suave' : 'badge-pendiente'}">
          ${cerrada ? 'CERRADA' : 'ABIERTA'}
        </span>
      </div>

      ${docs.length ? `
        <div class="exp-consulta-docs">
          ${docs.map(tarjeta).join('')}
        </div>` : ''}
    </div>`;
  }

  function tarjeta(d) {
    const pendiente = Number(d.estado) === 0;
    const esImagen  = (d.tipo_mime || '').startsWith('image/');
    const puedeEditar = document.getElementById('expNuevoPendiente') !== null;

    return `
    <div class="doc-tarjeta ${pendiente ? 'pendiente' : 'listo'}"
         data-documento='${App.esc(JSON.stringify(d))}'>
      <div class="doc-icono">
        <i class="bi ${App.esc(d.icono || 'bi-file-earmark')}"></i>
      </div>

      <div class="flex-grow-1" style="min-width:0">
        <div class="doc-nombre">${App.esc(d.nombre_documento)}</div>

        ${d.descripcion
          ? `<div class="doc-meta">${App.esc(d.descripcion)}</div>` : ''}

        ${pendiente ? `
          <div class="doc-meta mt-1">
            <span class="chip-estado badge-pendiente">PENDIENTE DE DIGITALIZAR</span>
          </div>
          <div class="doc-meta mt-1">
            Solicitado el ${App.esc(App.fechaHora(d.fecha_solicitud))}
            ${d.solicito ? ' por ' + App.esc(d.solicito) : ''}
          </div>
        ` : `
          <div class="doc-meta mt-1" style="word-break:break-all">
            ${App.esc(d.nombre_original || '')}
            · ${App.esc(App.tamanoLegible(d.tamano_bytes))}
          </div>
          <div class="doc-meta">
            Digitalizado el ${App.esc(App.fechaHora(d.fecha_digitalizacion))}
            ${d.subio ? ' por ' + App.esc(d.subio) : ''}
          </div>
        `}

        <div class="mt-2 d-flex gap-2 flex-wrap">
          ${pendiente ? (puedeEditar ? `
            <button type="button" class="btn btn-sm btn-morado" data-accion="exp-subir">
              <i class="bi bi-cloud-arrow-up me-1"></i> Subir escaneo
            </button>` : '') : `
            <button type="button" class="btn btn-sm btn-outline-morado"
                    data-accion="exp-ver">
              <i class="bi bi-eye me-1"></i> ${esImagen ? 'Ver imagen' : 'Ver'}
            </button>
            <a class="btn btn-sm btn-light"
               href="${App.URL_BASE}/archivo.php?id=${d.id_documento}&d=1">
              <i class="bi bi-download me-1"></i> Descargar
            </a>
            ${puedeEditar ? `
            <button type="button" class="btn btn-sm btn-light" data-accion="exp-reemplazar">
              <i class="bi bi-arrow-repeat me-1"></i> Reemplazar
            </button>
            <button type="button" class="btn btn-sm btn-link text-danger"
                    data-accion="exp-quitar">Quitar</button>` : ''}
          `}
        </div>
      </div>
    </div>`;
  }

  /**
   * Abre el documento en una ventana del tamano adecuado.
   *
   * Para imagenes se mide primero el archivo y la ventana se ajusta a
   * sus proporciones: una radiografia apaisada y una credencial vertical
   * no necesitan la misma ventana. Los PDF van a proporcion carta.
   */
  function verDocumento(doc) {
    const url = `${App.URL_BASE}/archivo.php?id=${doc.id_documento}`;
    const esImagen = (doc.tipo_mime || '').startsWith('image/');

    if (!esImagen) {
      // Proporcion de hoja carta, con margen para la barra del visor.
      App.abrirVentana(url, 880, 1000, `doc${doc.id_documento}`);
      return;
    }

    // Se carga la imagen para conocer su tamano real antes de abrir.
    const img = new Image();

    img.onload = () => {
      const maxAncho = window.screen.availWidth  - 120;
      const maxAlto  = window.screen.availHeight - 160;

      let ancho = img.naturalWidth;
      let alto  = img.naturalHeight;

      // Se reduce conservando la proporcion hasta que quepa en pantalla.
      const escala = Math.min(1, maxAncho / ancho, maxAlto / alto);
      ancho = Math.round(ancho * escala);
      alto  = Math.round(alto  * escala);

      // Ventanas muy chicas no dejan ver nada ni permiten cerrarlas bien.
      App.abrirVentana(url, Math.max(ancho + 30, 420), Math.max(alto + 90, 340),
        `doc${doc.id_documento}`);
    };

    img.onerror = () => App.abrirVentana(url, 900, 700, `doc${doc.id_documento}`);
    img.src = url;
  }

  /* -------------------------------------------------------------------
     Subida
     ------------------------------------------------------------------- */
  /**
   * Cambia la foto del circulo sin recargar la pagina.
   *
   * Se le agrega un parametro con la hora al final de la direccion: sin
   * eso el navegador seguiria mostrando la imagen que ya tenia guardada
   * y pareceria que la subida no funciono.
   */
  function refrescarFoto() {
    const img = document.getElementById('fotoPaciente');
    const ini = document.getElementById('inicialesPaciente');
    if (!img) return;

    // Se conserva el ?p= original (id de la persona) y solo se agrega
    // una marca de tiempo para saltarse la copia guardada del navegador.
    const base = img.src.split('&v=')[0];
    img.src = `${base}&v=${Date.now()}`;

    img.onload = () => {
      img.classList.remove('d-none');
      ini?.classList.add('d-none');
      // Un destello para que se note que cambio.
      img.classList.add('campo-autollenado');
      setTimeout(() => img.classList.remove('campo-autollenado'), 1200);
    };

    const boton = document.getElementById('btnCambiarFoto');
    if (boton) {
      boton.innerHTML = '<i class="bi bi-camera me-1"></i> Cambiar fotografía';
    }
  }

  let documentoActual = null;

  function abrirSubida(doc) {
    documentoActual = doc;
    document.getElementById('subirIdDocumento').value = doc.id_documento;
    document.getElementById('subirTitulo').textContent =
      Number(doc.estado) === 0
        ? `Subir ${doc.nombre_documento.toLowerCase()}`
        : `Reemplazar ${doc.nombre_documento.toLowerCase()}`;
    document.getElementById('subirAyuda').textContent =
      `Formatos aceptados: ${(doc.formatos || '').toUpperCase()}. Máximo 20 MB.`;

    document.getElementById('subirArchivo').setAttribute(
      'accept',
      (doc.formatos || '').split(',').map((f) => '.' + f.trim()).join(','));

    limpiarSeleccion();
    modalSubir.show();
  }

  function limpiarSeleccion() {
    archivoElegido = null;
    document.getElementById('subirArchivo').value = '';
    document.getElementById('subirPrevio').classList.add('d-none');
    document.getElementById('subirImagen').classList.add('d-none');
    document.getElementById('subirBoton').disabled = true;
  }

  function elegirArchivo(archivo) {
    if (!archivo) return;

    if (archivo.size > 20 * 1024 * 1024) {
      App.alerta.error('Archivo muy grande',
        'El límite es 20 MB. Vuelve a escanearlo con menor resolución.');
      return;
    }

    archivoElegido = archivo;
    document.getElementById('subirNombre').textContent = archivo.name;
    document.getElementById('subirTamano').textContent =
      '· ' + App.tamanoLegible(archivo.size);
    document.getElementById('subirPrevio').classList.remove('d-none');
    document.getElementById('subirBoton').disabled = false;

    // Vista previa solo de imagenes: ver el escaneo antes de guardarlo
    // evita subir la hoja al revés o en blanco.
    const img = document.getElementById('subirImagen');
    if (archivo.type.startsWith('image/')) {
      img.src = URL.createObjectURL(archivo);
      img.classList.remove('d-none');
      img.onload = () => URL.revokeObjectURL(img.src);
    } else {
      img.classList.add('d-none');
    }
  }

  /* -------------------------------------------------------------------
     Arranque
     ------------------------------------------------------------------- */
  function iniciar(expediente) {
    idExpediente = expediente;

    const panel = document.getElementById('tab-expediente');
    if (!panel) return;

    modalSubir = bootstrap.Modal.getOrCreateInstance(
      document.getElementById('modalSubirDoc'));
    modalNuevo = bootstrap.Modal.getOrCreateInstance(
      document.getElementById('modalNuevoPendiente'));

    // Se carga una sola vez, al abrir la pestania por primera vez: no
    // tiene caso pedir el expediente si el usuario nunca entra ahi.
    let cargado = false;
    document.getElementById('btnTabExpediente')
      ?.addEventListener('shown.bs.tab', () => {
        if (!cargado) { cargado = true; cargar(); }
      });

    // Si la pagina abre directamente en esta pestania (#tab-expediente)
    if (location.hash === '#tab-expediente') {
      cargado = true;
      cargar();
    }

    document.getElementById('expRefrescar').addEventListener('click', cargar);

    /* ---------- Boton "Cambiar fotografia" de la tarjeta ---------- */
    // Esta fuera de la pestania del expediente, asi que necesita buscar
    // el documento de tipo foto por su cuenta.
    /*
     * El boton fallaba porque buscaba un documento de fotografia que
     * ya no se crea al dar de alta al paciente: si no existia, el
     * boton solo mandaba a otra pestania y ahi se acababa. Ahora, si
     * no existe, lo crea y sigue de frente.
     *
     * Ademas el arbol viene agrupado, asi que la foto hay que buscarla
     * dentro de 'generales' y no en una lista plana que ya no existe.
     */
    document.getElementById('btnCambiarFoto')?.addEventListener('click', async () => {
      App.alerta.cargando('Preparando...');
      try {
        let r = await App.api('expediente/listar.php', {
          id_registro_paciente: idExpediente,
        });

        let foto = (r.datos.generales ?? []).find((d) => d.clave === 'FOTO_PACIENTE');

        if (!foto) {
          const nuevo = await App.api('expediente/pendiente_nuevo.php', {
            id_registro_paciente: idExpediente,
            clave: 'FOTO_PACIENTE',
            descripcion: '',
          });

          r = await App.api('expediente/listar.php', {
            id_registro_paciente: idExpediente,
          });
          foto = (r.datos.generales ?? []).find(
            (d) => Number(d.id_documento) === Number(nuevo.datos.id_documento));
        }

        App.alerta.cerrar();

        if (!foto) {
          App.alerta.error('No se pudo preparar la fotografía',
            'Intenta desde la pestaña Expediente electrónico.');
          return;
        }

        abrirSubida(foto);
      } catch (e) {
        App.alerta.cerrar();
      }
    });

    /* ---------- Abrir y cerrar bloques ---------- */
    document.getElementById('expDocumentos').addEventListener('click', (ev) => {
      const cab = ev.target.closest('[data-plegar]');
      if (!cab) return;

      const caja = cab.closest('.exp-bloque');
      cab.classList.toggle('abierto');
      caja.querySelector('.exp-cuerpo').classList.toggle('abierto');
    });

    /* ---------- Acciones de cada tarjeta ---------- */
    document.getElementById('expDocumentos').addEventListener('click', async (ev) => {
      const btn = ev.target.closest('[data-accion]');
      if (!btn) return;

      /* --- Acciones de bloque (no cuelgan de un documento) --- */
      if (btn.dataset.accion === 'exp-agregar') {
        abrirNuevoPendiente(btn.dataset.clave, btn.dataset.plan || '');
        return;
      }

      if (btn.dataset.accion === 'exp-subir-directo') {
        await subirDirecto(btn.dataset.clave, btn.dataset.plan || '');
        return;
      }

      if (btn.dataset.accion === 'exp-consentimiento') {
        abrirDespues(`plan-${btn.dataset.plan}`);
        Consentimiento.pedir(Number(btn.dataset.plan));
        return;
      }

      if (btn.dataset.accion === 'exp-hoja-plan') {
        App.abrirVentana(
          `${App.URL_BASE}/plan_imprimir.php?id=${btn.dataset.plan}`,
          880, 1020, 'plan');
        return;
      }

      const doc = JSON.parse(btn.closest('[data-documento]').dataset.documento);

      if (btn.dataset.accion === 'exp-subir' || btn.dataset.accion === 'exp-reemplazar') {
        abrirSubida(doc);
      }

      if (btn.dataset.accion === 'exp-ver') {
        verDocumento(doc);
      }

      if (btn.dataset.accion === 'exp-quitar') {
        const ok = await App.alerta.confirmar(
          '¿Quitar este archivo?',
          'El documento vuelve a quedar pendiente de digitalizar. '
          + 'El archivo se borra del almacenamiento.',
          'Sí, quitar');
        if (!ok) return;

        try {
          await App.api('expediente/quitar.php', { id_documento: doc.id_documento });
          App.alerta.toast('Archivo eliminado');
          cargar();
        } catch (e) { /* ya se mostro */ }
      }
    });

    /* ---------- Zona de arrastrar y soltar ---------- */
    const zona  = document.getElementById('zonaSoltar');
    const campo = document.getElementById('subirArchivo');

    zona.addEventListener('click', () => campo.click());
    campo.addEventListener('change', () => elegirArchivo(campo.files[0]));

    ['dragenter', 'dragover'].forEach((ev) => {
      zona.addEventListener(ev, (e) => {
        e.preventDefault();
        zona.classList.add('encima');
      });
    });
    ['dragleave', 'drop'].forEach((ev) => {
      zona.addEventListener(ev, (e) => {
        e.preventDefault();
        zona.classList.remove('encima');
      });
    });
    zona.addEventListener('drop', (e) => {
      const archivo = e.dataTransfer.files[0];
      // Se refleja en el input para que viaje en el FormData.
      const dt = new DataTransfer();
      dt.items.add(archivo);
      campo.files = dt.files;
      elegirArchivo(archivo);
    });

    document.getElementById('subirQuitar').addEventListener('click', limpiarSeleccion);

    /* ---------- Guardar ---------- */
    document.getElementById('formSubirDoc').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      if (!archivoElegido) return;

      const datos = new FormData();
      datos.append('id_documento', document.getElementById('subirIdDocumento').value);
      datos.append('archivo', archivoElegido);

      const esFoto = documentoActual?.clave === 'FOTO_PACIENTE';

      App.alerta.cargando(esFoto
        ? 'Guardando y recortando la fotografía...'
        : 'Guardando el documento...');
      try {
        await App.api('expediente/subir.php', datos);
        App.alerta.cerrar();
        modalSubir.hide();
        App.alerta.toast(esFoto ? 'Fotografía guardada' : 'Documento guardado');

        if (esFoto) refrescarFoto();
        cargar();
      } catch (e) {
        App.alerta.cerrar();
      }
    });

    /* ---------- Pendiente a mano ---------- */
    document.getElementById('expNuevoPendiente')?.addEventListener('click',
      () => abrirNuevoPendiente('', ''));

    document.getElementById('formNuevoPendiente')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        await App.api('expediente/pendiente_nuevo.php', {
          ...App.formADatos(ev.target),
          id_registro_paciente: idExpediente,
        });
        modalNuevo.hide();
        App.alerta.toast('Pendiente agregado');

        // El bloque donde se agrego se queda abierto al recargar.
        const plan = document.getElementById('pendPlan').value;
        abrirDespues(plan ? `plan-${plan}` : 'generales');
        cargar();
      } catch (e) { /* ya se mostro */ }
    });
  }

  /**
   * Crea el pendiente de un tipo unico y abre de inmediato el dialogo
   * de subida. Es lo que usan la fotografia del paciente y el
   * historial firmado: el papel ya esta en la mano, no hay nada que
   * "apuntar como faltante" antes de subirlo.
   */
  async function subirDirecto(clave, idPlan) {
    App.alerta.cargando('Preparando...');
    try {
      const r = await App.api('expediente/pendiente_nuevo.php', {
        id_registro_paciente: idExpediente,
        clave,
        id_plan_tratamiento: idPlan || '',
        descripcion: '',
      });
      App.alerta.cerrar();

      abrirDespues(idPlan ? `plan-${idPlan}` : 'generales');

      // Se recarga para que la tarjeta exista, y se abre su subida.
      await cargar();
      const doc = buscarDocumento(r.datos.id_documento);
      if (doc) abrirSubida(doc);
    } catch (e) {
      App.alerta.cerrar();
    }
  }

  /** Busca un documento ya pintado por su id. */
  function buscarDocumento(id) {
    const caja = document.querySelector(
      `#expDocumentos [data-documento*='"id_documento":"${id}"'],`
      + `#expDocumentos [data-documento*='"id_documento":${id}']`);
    return caja ? JSON.parse(caja.dataset.documento) : null;
  }

  /** Marca un bloque para que quede abierto en el siguiente pintado. */
  function abrirDespues(clave) {
    recordarAbiertos();
    abiertos.add(clave);
  }

  /**
   * Abre el modal de "agregar documento" con el contexto puesto.
   *
   * El combo se arma segun donde se pidio: dentro de un plan solo salen
   * los tipos del plan, y en el bloque general solo los del paciente.
   * Asi no se puede colgar una radiografia del expediente entero ni la
   * fotografia de un tratamiento.
   */
  function abrirNuevoPendiente(clave, idPlan) {
    const form = document.getElementById('formNuevoPendiente');
    form.reset();

    const tipos = idPlan ? tiposPlan : tiposPaciente;

    document.getElementById('pendTipo').innerHTML =
      '<option value="">Seleccionar...</option>'
      + tipos.map((t) => `
          <option value="${App.esc(t.clave)}"
                  ${t.clave === clave ? 'selected' : ''}>
            ${App.esc(t.nombre_documento)}
          </option>`).join('');

    document.getElementById('pendPlan').value = idPlan || '';

    const contexto = document.getElementById('pendContexto');
    contexto.classList.toggle('d-none', !idPlan);

    if (idPlan) {
      const cab = document.querySelector(`.exp-bloque[data-clave="plan-${idPlan}"] .exp-titulo .t`);
      document.getElementById('pendContextoFolio').textContent =
        cab ? cab.textContent.trim() : `#${idPlan}`;
    }

    modalNuevo.show();
  }

  return { iniciar, cargar };
})();

/* =====================================================================
   Consentimiento bajo informacion

   El consentimiento pertenece a un plan de tratamiento, no al paciente:
   es el permiso para ESE tratamiento. Por eso se pide desde el bloque
   del plan en el expediente y no desde un boton suelto arriba.

   Antes de abrir el documento hay que saber con que tutor, con que
   domicilio y con que dentista sale impreso. Si no hay nada que elegir,
   el modal estorba y se salta.
   ===================================================================== */
const Consentimiento = (() => {

  let idExpediente = null;
  let idPlan       = null;
  let datos        = null;
  let modal        = null;

  function elementos() {
    return {
      tutor:     document.getElementById('consTutor'),
      domicilio: document.getElementById('consDomicilio'),
      dentista:  document.getElementById('consDentista'),
      bloqueDom: document.getElementById('consBloqueDomicilio'),
    };
  }

  /** Los domicilios dependen del tutor elegido. */
  function pintarDomicilios() {
    const el = elementos();

    const tutor = datos.tutores.find(
      (t) => String(t.id_expediente_tutor) === el.tutor.value);

    const lista = tutor?.domicilios ?? [];

    el.domicilio.innerHTML = lista.length
      ? lista.map((d) => `<option value="${d.id_domicilio}">
           ${App.esc([d.calle_numero, d.colonia,
                      d.codigo_postal ? 'C.P. ' + d.codigo_postal : '',
                      d.nombre_municipio].filter(Boolean).join(', '))}
         </option>`).join('')
      : '<option value="">Este tutor no tiene domicilio registrado</option>';

    // Con un solo domicilio no hay nada que elegir.
    el.bloqueDom.classList.toggle('d-none', lista.length <= 1);
  }

  function abrirDocumento() {
    const el = elementos();

    const url = `${App.URL_BASE}/consentimiento.php`
      + `?id=${idExpediente}`
      + `&plan=${idPlan || 0}`
      + `&tutor=${el.tutor.value || 0}`
      + `&domicilio=${el.domicilio.value || 0}`
      + `&dentista=${el.dentista.value || 0}`;

    // Ancho de hoja carta (21.6 cm = 816 px) mas el margen del visor.
    App.abrirVentana(url, 880, 1020, 'consentimiento');

    // Al volver, el plan ya tiene su pendiente de consentimiento.
    setTimeout(() => Expediente.cargar(), 1500);
  }

  /** Punto de entrada: pide el consentimiento de un plan. */
  async function pedir(plan) {
    idPlan = plan || null;

    App.alerta.cargando('Preparando el documento...');
    try {
      const r = await App.api('paciente/consentimiento_datos.php', {
        id_registro_paciente: idExpediente,
      });
      App.alerta.cerrar();
      datos = r.datos;

      if (!datos.tutores.length) {
        const seguir = await App.alerta.confirmar(
          'Este paciente no tiene tutor registrado',
          'El consentimiento saldra sin nombre ni domicilio del representante '
          + 'legal. \u00bfAun asi quieres imprimirlo?',
          'Si, imprimir en blanco');
        if (!seguir) return;
        abrirDocumento();
        return;
      }

      const el = elementos();

      el.tutor.innerHTML = datos.tutores.map((t) => `
        <option value="${t.id_expediente_tutor}">
          ${App.esc(t.nombre_completo)}
          ${t.nombre_parentesco ? ' \u2014 ' + App.esc(t.nombre_parentesco) : ''}
          ${Number(t.es_responsable_pago) === 1 ? ' (responsable de pago)' : ''}
        </option>`).join('');

      el.dentista.innerHTML = datos.dentistas.length
        ? datos.dentistas.map((d) => `
            <option value="${d.id_usuario}"
              ${String(d.id_usuario) === String(datos.yo_dentista) ? 'selected' : ''}>
              ${App.esc(d.nombre_completo)}
            </option>`).join('')
        : '<option value="">No hay dentistas registrados</option>';

      pintarDomicilios();

      const unTutor     = datos.tutores.length === 1;
      const unDomicilio = (datos.tutores[0].domicilios ?? []).length <= 1;
      const unDentista  = datos.dentistas.length === 1;

      // Sin nada que decidir, no se estorba con un modal de un solo
      // camino: se abre el documento directo.
      if (unTutor && unDomicilio && unDentista) {
        abrirDocumento();
        return;
      }

      modal.show();
    } catch (e) {
      App.alerta.cerrar();
    }
  }

  function iniciar(expediente) {
    idExpediente = expediente;

    const caja = document.getElementById('modalConsentimiento');
    if (!caja) return;

    modal = bootstrap.Modal.getOrCreateInstance(caja);

    document.getElementById('consTutor')
      .addEventListener('change', pintarDomicilios);

    document.getElementById('formConsentimiento')
      .addEventListener('submit', (ev) => {
        ev.preventDefault();
        modal.hide();
        abrirDocumento();
      });
  }

  return { iniciar, pedir };
})();
