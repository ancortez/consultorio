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
  //
  // Al ENTRAR, en cambio, todo arranca cerrado. 'generales' venia
  // abierto por omision y era la pantalla completa de entrada: quien
  // abre el expediente ya sabe que viene a buscar —la credencial, una
  // radiografia, el consentimiento— y lo que necesita es ver los
  // nombres de las carpetas, no el contenido de una de ellas
  // desplazando a las demas fuera de la vista.
  let abiertos = new Set();

  // El ultimo arbol dibujado. Hace falta despues de pintar: cuando
  // alguien abre el album de una consulta hay que encontrar esa
  // consulta y sus documentos, y los manejadores de clic viven fuera de
  // pintar().
  let arbol = null;

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
    arbol = datos;
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

    // El resaltado de la pestania desaparecio con la pestania: el
    // expediente es su propia pantalla desde octubre 2026, y quien
    // avisa de los pendientes es la tarjeta de la ficha.

    const caja = document.getElementById('expDocumentos');
    caja.innerHTML = bloqueGeneral(datos) + datos.planes.map(bloquePlan).join('');

    // Las miniaturas de PDF se arman al asomarse. Va despues de
    // escribir el HTML, porque hasta ahora los renglones no existian.
    observarPdf(caja);
  }

  /**
   * Todas las consultas del expediente, vengan de un plan o sueltas.
   *
   * El arbol las trae repartidas en dos sitios porque asi se dibujan,
   * pero para abrir el album hace falta encontrar una por su id sin
   * importar de donde cuelgue.
   */
  function todasLasConsultas() {
    if (!arbol) return [];
    return [
      ...(arbol.consultas ?? []),
      ...(arbol.planes ?? []).flatMap((b) => b.consultas ?? []),
    ];
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
      // El estatus salio de aqui: ahora es su propia etiqueta de color
      // a la derecha, y repetirlo solo gastaba espacio.
      subtitulo: App.esc(App.fecha(p.fecha))
        + (p.descripcion ? ` · ${App.esc(p.descripcion)}` : '')
        + (p.diagnostico ? ` · ${App.esc(p.diagnostico.substring(0, 60))}` : ''),
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
  /*
     Colores del estatus del plan.

     Son dos cosas distintas en la misma cabecera y conviene que no se
     confundan: a la izquierda, en que va el TRATAMIENTO; a la derecha,
     en que van sus PAPELES. Un plan puede estar terminado y tener el
     consentimiento sin escanear, o estar apenas propuesto con todo en
     orden. Mezclarlos en un solo color borraria esa diferencia.
  */
  const COLOR_ESTADO_PLAN = {
    1: 'plan-propuesto',   // Propuesto  - gris: todavia no pasa nada
    2: 'plan-aceptado',    // Aceptado   - azul: hay firma
    3: 'plan-proceso',     // En proceso - morado: se esta trabajando
    4: 'plan-terminado',   // Terminado  - verde
    5: 'plan-cancelado',   // Cancelado  - rojo apagado
  };

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

        ${plan ? `
          <span class="badge badge-plan ${
            COLOR_ESTADO_PLAN[Number(plan.id_estado_plan)] ?? 'plan-propuesto'}"
                title="Estatus del tratamiento">
            ${App.esc(plan.nombre_estado)}
          </span>` : ''}

        ${pendientes > 0
          ? `<span class="badge badge-pendiente">${pendientes} por escanear</span>`
          : `<span class="badge badge-suave">Completo</span>`}
      </button>

      <div class="exp-cuerpo ${abierto ? 'abierto' : ''}">
        ${plan ? barraPlan(plan) : ''}
        ${cuerpo.trim()
          ? cuerpo
          : `<div class="exp-vacio">
               Todavía no se ha registrado ningún documento aquí.
               Usa <b>Agregar documento</b> para registrar el primero.
             </div>`}
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

    /*
       Un tipo del que no hay NADA registrado no se dibuja.

       Antes se pintaba un grupo por cada tipo del catalogo, con un
       "Sin documentos de este tipo" debajo. Eso llenaba el expediente
       de renglones que no son documentos del paciente sino casillas
       vacias del catalogo, y leerlo obligaba a descartarlas una por
       una para encontrar lo que si existe. Un expediente recien
       abierto se veia lleno y no tenia ni un papel.

       Nada se pierde: lo que todavia no se ha pedido se agrega con el
       boton de arriba, que ofrece la lista completa de tipos. Y los
       que ya se pidieron pero no se han escaneado SI salen, porque son
       pendientes de verdad: alguien tiene que ir por ese papel.
    */
    if (!docs.length) return '';

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

      <div class="exp-tarjetas">${docs.map(tarjeta).join('')}</div>
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
    const pend = docs.filter((d) => Number(d.estado) === 0).length;
    // Solo lo que se puede ver como miniatura. Un PDF en el visor
    // romperia la cuadricula.
    const imagenes = Album.soloImagenes(docs);

    /*
       Colapsada por omision, y eso es a proposito.

       Un plan de seis meses acumula quince consultas con ocho
       radiografias cada una. Abiertas todas, el expediente es un muro
       de cien tarjetas por el que hay que hacer scroll para encontrar
       cualquier cosa. Cerradas, se ve la historia completa del plan en
       una pantalla y se abre la cita que interesa.

       Tampoco abren solas las que tienen pendientes. Abrirlas era util
       cuando el expediente entero se desplegaba al entrar, pero ahora
       que todo arranca cerrado, una consulta abierta en medio de una
       lista cerrada se lee como un error de dibujo. El pendiente ya se
       anuncia en su contador, que es lo que hace falta para saber que
       hay algo ahi.
    */

    return `
    <details class="exp-consulta-caja">
      <summary class="exp-consulta">
        <i class="bi bi-chevron-right flecha"></i>

        <div class="fecha">
          <span class="folio">${App.esc(c.folio ?? '')}</span>
          <div>${App.esc(App.fechaHora(c.fecha_hora))}</div>
        </div>

        <div class="flex-grow-1" style="min-width:0">
          <div class="fw-semibold">${App.esc(c.motivo || 'CONSULTA')}</div>
          <div class="text-secondary" style="font-size:11.5px">
            ${App.esc(c.dentista)}
            ${Number(c.notas) > 0 ? ` · ${c.notas} nota(s)` : ''}
          </div>
        </div>

        <div class="marcas-consulta">
          ${docs.length ? `<span title="Documentos">
            <i class="bi bi-paperclip"></i> ${docs.length}</span>` : ''}
          ${pend ? `<span class="pendiente" title="Pendientes de digitalizar">
            <i class="bi bi-hourglass-split"></i> ${pend}</span>` : ''}
        </div>

        ${imagenes.length ? `
          <button type="button" class="btn-album" data-album-consulta="${c.id_consulta}"
                  title="Ver las ${imagenes.length} imagenes en modo album"
                  onclick="event.stopPropagation()">
            <i class="bi bi-images"></i> ${imagenes.length}
          </button>` : ''}

        <span class="chip-estado ${cerrada ? 'badge-suave' : 'badge-pendiente'}">
          ${cerrada ? 'CERRADA' : 'ABIERTA'}
        </span>

        <a href="${App.URL_BASE}/index.php?modulo=consultas&vista=consulta&id=${c.id_consulta}"
           class="ir-consulta" title="Abrir la consulta"
           onclick="event.stopPropagation()">
          <i class="bi bi-box-arrow-up-right"></i>
        </a>
      </summary>

      <div class="exp-consulta-docs">
        ${docs.length
          ? docs.map(tarjeta).join('')
          : '<div class="exp-vacio">Esta consulta no tiene documentos.</div>'}
      </div>
    </details>`;
  }

  /**
   * El cuadro de la izquierda del renglon.
   *
   * Antes era un icono por tipo de documento, y con doce radiografias
   * salian doce iconos identicos: para saber cual era cual habia que
   * abrirlas una por una. La miniatura contesta eso de un vistazo, que
   * es justo lo que se le pide a una lista.
   *
   * Tres casos:
   *
   *   - imagen      la propia foto, reducida en el servidor. Se pide
   *                 con &mini=1 y no el archivo completo: una
   *                 radiografia de 2400 px por cada renglon hace que
   *                 la pestania tarde en dibujarse y se paga ese ancho
   *                 de banda cada vez que se abre un expediente.
   *   - PDF         la primera pagina, pero SOLO cuando el renglon se
   *                 asoma en pantalla (ver observarPdf). Un expediente
   *                 con quince PDF incrustados de golpe deja el
   *                 navegador de rodillas.
   *   - pendiente   no hay nada que mostrar todavia: su icono.
   */
  function miniatura(d, pendiente, esImagen) {
    if (pendiente) {
      return `<div class="doc-mini vacia">
                <i class="bi ${App.esc(d.icono || 'bi-file-earmark')}"></i>
              </div>`;
    }

    if (esImagen) {
      return `<div class="doc-mini">
                <img loading="lazy" alt=""
                     src="${App.URL_BASE}/archivo.php?id=${d.id_documento}&mini=1">
              </div>`;
    }

    if ((d.tipo_mime || '') === 'application/pdf') {
      return `<div class="doc-mini pdf" data-pdf="${d.id_documento}">
                <i class="bi bi-file-earmark-pdf"></i>
              </div>`;
    }

    return `<div class="doc-mini vacia">
              <i class="bi ${App.esc(d.icono || 'bi-file-earmark')}"></i>
            </div>`;
  }

  /**
   * Las miniaturas de PDF se cargan al asomarse, no todas de golpe.
   *
   * Un <embed> por cada PDF del expediente son quince descargas y
   * quince visores de PDF arrancando a la vez en cuanto se abre la
   * pestania. Con el observador, solo se arma el de los renglones que
   * de verdad se ven, y una vez armado se deja de observar.
   */
  function observarPdf(caja) {
    const cajas = caja.querySelectorAll('.doc-mini.pdf[data-pdf]');
    if (!cajas.length) return;

    // Sin IntersectionObserver —un navegador viejo— se queda el icono.
    // Es peor miniatura, no es una pantalla rota.
    if (typeof IntersectionObserver === 'undefined') return;

    const ojo = new IntersectionObserver((entradas) => {
      entradas.forEach((e) => {
        if (!e.isIntersecting) return;
        const el = e.target;
        ojo.unobserve(el);

        const id = el.dataset.pdf;
        delete el.dataset.pdf;
        // El #toolbar=0 esconde la barra del visor, que en un cuadro de
        // 50 px ocuparia mas que la propia pagina.
        el.innerHTML = `<embed type="application/pdf"
             src="${App.URL_BASE}/archivo.php?id=${id}#toolbar=0&navpanes=0&view=FitH">`;
      });
    }, { rootMargin: '150px' });

    cajas.forEach((c) => ojo.observe(c));
  }

  function tarjeta(d) {
    const pendiente = Number(d.estado) === 0;
    const esImagen  = (d.tipo_mime || '').startsWith('image/');
    const puedeEditar = document.getElementById('expNuevoPendiente') !== null;

    /*
       Eliminar vive detras de los tres puntos.

       Borrar un documento del expediente es irreversible y no es una
       accion frecuente; tenerla del mismo tamanio y al lado de "ver" y
       "descargar" la pone a un dedazo de distancia de las dos cosas
       que si se hacen todo el dia. Un paso mas no estorba cuando la
       accion se usa una vez al mes.

       Un renglon de ancho completo, no un cuadro.

       Antes cada documento era una tarjeta en una rejilla de dos
       columnas. Con un expediente de verdad —doce radiografias, cuatro
       consentimientos, las credenciales— eso se convierte en un mosaico
       de cuadros casi identicos por el que hay que ir leyendo uno por
       uno. En renglon, el ojo baja por una sola columna y la fecha, el
       nombre y el estado quedan siempre en la misma posicion, que es lo
       que permite encontrar algo sin leerlo todo.

       Los botones se reducen a iconos y aparecen al pasar encima: con
       veinte documentos, sesenta botones visibles compiten con el
       contenido.
    */
    return `
    <div class="doc-renglon ${pendiente ? 'pendiente' : 'listo'}"
         data-documento='${App.esc(JSON.stringify(d))}'>

      ${miniatura(d, pendiente, esImagen)}

      <div class="doc-centro">
        <div class="doc-nombre">
          ${App.esc(d.descripcion || d.nombre_documento)}
        </div>
        <div class="doc-meta">
          ${pendiente
            ? `Solicitado el ${App.esc(App.fecha(d.fecha_solicitud))}`
              + (d.solicito ? ' por ' + App.esc(d.solicito) : '')
            : `${App.esc(App.fecha(d.fecha_digitalizacion))}`
              + (d.subio ? ' · ' + App.esc(d.subio) : '')
              + (d.tamano_bytes ? ' · ' + App.esc(App.tamanoLegible(d.tamano_bytes)) : '')}
        </div>
      </div>

      ${/*
           El chip de la derecha ya no repite el tipo de documento.

           Decia "Fotografia del paciente" o "Historial clinico
           firmado"... y a dos centimetros, en el nombre del renglon, ya
           estaba lo mismo. Dos veces el mismo texto en la misma linea
           no informa: ocupa el sitio donde deberia estar lo que hace
           falta saber, que es si el papel ya entro o todavia no.
        */''}
      ${pendiente ? `
        <span class="doc-estado chip-estado badge-pendiente">POR ESCANEAR</span>`
      : `
        <span class="doc-estado escaneado">
          <i class="bi bi-check-circle-fill"></i> Escaneado correctamente
        </span>`}

      <div class="doc-acciones">
        ${pendiente ? (puedeEditar ? `
          <button type="button" class="btn-icono" data-accion="exp-subir"
                  title="Subir escaneo">
            <i class="bi bi-cloud-arrow-up"></i>
          </button>` : '') : `
          <button type="button" class="btn-icono" data-accion="exp-ver"
                  title="${esImagen ? 'Ver imagen' : 'Ver'}">
            <i class="bi bi-eye"></i>
          </button>
          <a class="btn-icono" title="Descargar"
             href="${App.URL_BASE}/archivo.php?id=${d.id_documento}&d=1">
            <i class="bi bi-download"></i>
          </a>
          ${puedeEditar ? `
          <button type="button" class="btn-icono" data-accion="exp-reemplazar"
                  title="Reemplazar">
            <i class="bi bi-arrow-repeat"></i>
          </button>

          <div class="dropdown">
            <button type="button" class="btn-icono" data-bs-toggle="dropdown"
                    title="Más acciones">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <button type="button" class="dropdown-item text-danger"
                        data-accion="exp-quitar">
                  <i class="bi bi-trash me-2"></i> Eliminar documento
                </button>
              </li>
            </ul>
          </div>` : ''}
        `}
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

    // Antes aqui se reescribia el texto del boton de "subir" a
    // "cambiar". Ahora el campo de fotografia se pinta entero desde PHP
    // —con miniatura, descargar y eliminar— asi que lo que corresponde
    // es volver a pedir la pagina, no remendar un boton.
    if (document.querySelector('[data-cambiar-foto]')) {
      setTimeout(() => location.reload(), 900);
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
  /*
     Este modulo sirve a DOS pantallas desde octubre 2026.

     En el expediente electronico hace todo: los bloques, los
     documentos, las subidas. En la ficha del paciente solo hacen falta
     dos cosas —el boton de la fotografia y los pendientes de la
     tarjeta— y el resto del HTML no existe ahi.

     Por eso lo del expediente se engancha solo si su contenedor esta,
     en vez de cortar la funcion entera al principio: cortarla dejaba
     la ficha sin boton de fotografia.
  */
  function iniciar(expediente) {
    idExpediente = expediente;

    const hayExpediente = document.getElementById('expDocumentos') !== null;

    // El modal de subida vive en el layout: esta en las dos pantallas,
    // porque el boton de la fotografia y los pendientes de la tarjeta
    // tambien lo usan.
    const cajaSubir = document.getElementById('modalSubirDoc');
    if (cajaSubir) modalSubir = bootstrap.Modal.getOrCreateInstance(cajaSubir);

    if (hayExpediente) {
      modalNuevo = bootstrap.Modal.getOrCreateInstance(
        document.getElementById('modalNuevoPendiente'));

      /*
         Cuando y como se carga depende de donde este.

         Como pestania del detalle: al abrirla, no antes. Son cuatro
         consultas y la mayoria de las veces quien entra al paciente va
         a otra cosa.

         Como pantalla propia: de entrada, porque quien llego ahi vino
         justamente a verlo.
      */
      const solapa = document.getElementById('btnTabExpediente');
      if (solapa) {
        let cargado = false;
        solapa.addEventListener('shown.bs.tab', () => {
          if (!cargado) { cargado = true; cargar(); }
        });
        if (location.hash === '#tab-expediente') { cargado = true; cargar(); }
      } else {
        cargar();
      }

      document.getElementById('expRefrescar')?.addEventListener('click', cargar);
    }

    /* ---------- Pendientes de la tarjeta de la ficha ---------- */
    // Se sube desde ahi mismo. Mandar a la persona al expediente a
    // buscar el documento es justo el paso que hace que los pendientes
    // se queden pendientes.
    // Delegado en el documento y sin condicion previa: enganchar solo
    // si ya hay un pendiente dibujado dejaria muerto el boton del
    // pendiente que aparezca despues.
    document.addEventListener('click', (ev) => {
      const b = ev.target.closest('[data-subir-doc]');
      if (!b) return;

      const campo = document.getElementById('fichaArchivoPendiente');
      if (!campo) return;

      campo.value = '';
      campo.dataset.documento = b.dataset.subirDoc;
      campo.setAttribute('accept',
        (b.dataset.formatos || '').split(',').map((e) => '.' + e.trim()).join(','));
      campo.click();
    });

    document.getElementById('fichaArchivoPendiente')
      ?.addEventListener('change', async (ev) => {
      const archivo = ev.target.files?.[0];
      if (!archivo) return;

      const cuerpo = new FormData();
      cuerpo.append('id_documento', ev.target.dataset.documento);
      cuerpo.append('archivo', archivo);

      App.alerta.cargando('Guardando el escaneo...');
      try {
        await App.api('expediente/subir.php', cuerpo);
        App.alerta.cerrar();
        // Se recarga la pagina: la tarjeta, el contador y el aviso de
        // pendientes se dibujan en PHP, no en JavaScript.
        location.reload();
      } catch (e) {
        App.alerta.cerrar();
      } finally {
        ev.target.value = '';
      }
    });

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
    // Escucha delegado y no por id: el boton de la foto sale en dos
    // sitios a la vez —el lapiz del circulo y el del campo— y con un id
    // repetido getElementById solo encuentra el primero, dejando al
    // otro muerto sin dar un solo error.
    document.addEventListener('click', async (evFoto) => {
      if (!evFoto.target.closest('[data-cambiar-foto]')) return;
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

    /* ---------- Modo album ---------- */
    // Va antes del plegado porque el boton vive DENTRO del resumen de
    // la consulta: sin esto, abrir el album plegaria el bloque al mismo
    // tiempo y el visor aparecia sobre una lista que se acababa de
    // cerrar.
    document.getElementById('expDocumentos')?.addEventListener('click', (ev) => {
      const b = ev.target.closest('[data-album-consulta]');
      if (!b) return;
      ev.preventDefault();
      ev.stopPropagation();

      const id = Number(b.dataset.albumConsulta);
      const c  = todasLasConsultas().find((x) => Number(x.id_consulta) === id);
      if (!c) return;

      Album.abrir({
        titulo: `Consulta ${c.folio}`,
        imagenes: Album.soloImagenes(c.documentos),
      });
    });

    /* ---------- Abrir y cerrar bloques ---------- */
    document.getElementById('expDocumentos')?.addEventListener('click', (ev) => {
      const cab = ev.target.closest('[data-plegar]');
      if (!cab) return;

      const caja = cab.closest('.exp-bloque');
      cab.classList.toggle('abierto');
      caja.querySelector('.exp-cuerpo').classList.toggle('abierto');
    });

    /* ---------- Acciones de cada tarjeta ---------- */
    document.getElementById('expDocumentos')?.addEventListener('click', async (ev) => {
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
