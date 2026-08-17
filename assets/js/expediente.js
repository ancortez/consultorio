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

  /* -------------------------------------------------------------------
     Carga y pintado
     ------------------------------------------------------------------- */
  async function cargar() {
    const contenedor = document.getElementById('expDocumentos');
    if (!contenedor) return;

    contenedor.innerHTML = `<div class="col-12"><div class="cargando">
        <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
        Cargando expediente...</div></div>`;

    try {
      const r = await App.api('expediente/listar.php', {
        id_registro_paciente: idExpediente,
      });
      pintar(r.datos);
    } catch (e) {
      contenedor.innerHTML = `<div class="col-12">
          <div class="alert alert-danger mb-0">No se pudo cargar el expediente.</div>
        </div>`;
    }
  }

  function pintar({ documentos, pendientes, almacen }) {
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

    const contenedor = document.getElementById('expDocumentos');

    if (!documentos.length) {
      contenedor.innerHTML = `<div class="col-12">
          <div class="card"><div class="card-body text-center py-5 text-secondary">
            <i class="bi bi-folder2-open fs-1 d-block mb-2 opacity-25"></i>
            Este expediente todavía no tiene documentos.
          </div></div>
        </div>`;
      return;
    }

    contenedor.innerHTML = documentos.map(tarjeta).join('');
  }

  function tarjeta(d) {
    const pendiente = Number(d.estado) === 0;
    const esImagen  = (d.tipo_mime || '').startsWith('image/');
    const puedeEditar = document.getElementById('expNuevoPendiente') !== null;

    return `
    <div class="col-6">
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
    document.getElementById('btnCambiarFoto')?.addEventListener('click', async () => {
      App.alerta.cargando('Preparando...');
      try {
        const r = await App.api('expediente/listar.php', {
          id_registro_paciente: idExpediente,
        });
        App.alerta.cerrar();

        const foto = r.datos.documentos.find((d) => d.clave === 'FOTO_PACIENTE');

        if (!foto) {
          App.alerta.info('Sin documento de fotografía',
            'Agrega el documento desde la pestaña Expediente electrónico.');
          return;
        }

        abrirSubida(foto);
      } catch (e) {
        App.alerta.cerrar();
      }
    });

    /* ---------- Acciones de cada tarjeta ---------- */
    document.getElementById('expDocumentos').addEventListener('click', async (ev) => {
      const btn = ev.target.closest('[data-accion]');
      if (!btn) return;

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
    document.getElementById('expNuevoPendiente')?.addEventListener('click', () => {
      document.getElementById('formNuevoPendiente').reset();
      modalNuevo.show();
    });

    document.getElementById('formNuevoPendiente')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        await App.api('expediente/pendiente_nuevo.php', {
          ...App.formADatos(ev.target),
          id_registro_paciente: idExpediente,
        });
        modalNuevo.hide();
        App.alerta.toast('Pendiente agregado');
        cargar();
      } catch (e) { /* ya se mostro */ }
    });

    iniciarConsentimiento(idExpediente);
  }

  /* -------------------------------------------------------------------
     Consentimiento bajo informacion
     ------------------------------------------------------------------- */
  function iniciarConsentimiento(expediente) {
    const boton = document.getElementById('btnConsentimiento');
    if (!boton) return;

    const modal  = bootstrap.Modal.getOrCreateInstance(
      document.getElementById('modalConsentimiento'));
    const selTutor     = document.getElementById('consTutor');
    const selDomicilio = document.getElementById('consDomicilio');
    const selDentista  = document.getElementById('consDentista');
    const bloqueDom    = document.getElementById('consBloqueDomicilio');

    let datos = null;

    /** Los domicilios dependen del tutor elegido. */
    function pintarDomicilios() {
      const tutor = datos.tutores.find(
        (t) => String(t.id_expediente_tutor) === selTutor.value);

      const lista = tutor?.domicilios ?? [];

      selDomicilio.innerHTML = lista.length
        ? lista.map((d) => `<option value="${d.id_domicilio}">
             ${App.esc([d.calle_numero, d.colonia, d.codigo_postal ? 'C.P. ' + d.codigo_postal : '',
                        d.nombre_municipio].filter(Boolean).join(', '))}
           </option>`).join('')
        : '<option value="">Este tutor no tiene domicilio registrado</option>';

      // Con un solo domicilio no hay nada que elegir.
      bloqueDom.classList.toggle('d-none', lista.length <= 1);
    }

    function abrirDocumento() {
      const url = `${App.URL_BASE}/consentimiento.php`
        + `?id=${expediente}`
        + `&tutor=${selTutor.value || 0}`
        + `&domicilio=${selDomicilio.value || 0}`
        + `&dentista=${selDentista.value || 0}`;

      // Ancho de hoja carta (21.6 cm ≈ 816 px) mas el margen del visor.
      // Alto suficiente para ver la hoja completa sin tapar el sistema.
      App.abrirVentana(url, 880, 1020, 'consentimiento');

      // Al volver, el expediente ya tiene un pendiente mas.
      setTimeout(() => cargar(), 1500);
    }

    boton.addEventListener('click', async () => {
      App.alerta.cargando('Preparando el documento...');
      try {
        const r = await App.api('paciente/consentimiento_datos.php', {
          id_registro_paciente: expediente,
        });
        App.alerta.cerrar();
        datos = r.datos;

        if (!datos.tutores.length) {
          const seguir = await App.alerta.confirmar(
            'Este paciente no tiene tutor registrado',
            'El consentimiento saldrá sin nombre ni domicilio del representante '
            + 'legal. ¿Aun así quieres imprimirlo?',
            'Sí, imprimir en blanco');
          if (!seguir) return;
          abrirDocumento();
          return;
        }

        selTutor.innerHTML = datos.tutores.map((t) => `
          <option value="${t.id_expediente_tutor}">
            ${App.esc(t.nombre_completo)}
            ${t.nombre_parentesco ? ' — ' + App.esc(t.nombre_parentesco) : ''}
            ${Number(t.es_responsable_pago) === 1 ? ' (responsable de pago)' : ''}
          </option>`).join('');

        selDentista.innerHTML = datos.dentistas.length
          ? datos.dentistas.map((d) => `
              <option value="${d.id_usuario}"
                ${String(d.id_usuario) === String(datos.yo_dentista) ? 'selected' : ''}>
                ${App.esc(d.nombre_completo)}
              </option>`).join('')
          : '<option value="">No hay dentistas registrados</option>';

        pintarDomicilios();

        const unTutor    = datos.tutores.length === 1;
        const unDomicilio = (datos.tutores[0].domicilios ?? []).length <= 1;
        const unDentista = datos.dentistas.length === 1;

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
    });

    selTutor.addEventListener('change', pintarDomicilios);

    document.getElementById('formConsentimiento').addEventListener('submit', (ev) => {
      ev.preventDefault();
      modal.hide();
      abrirDocumento();
    });
  }

  return { iniciar, cargar, iniciarConsentimiento };
})();
