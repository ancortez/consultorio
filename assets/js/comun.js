/* =====================================================================
   Piezas compartidas entre modulos

   Aqui viven los selectores y controles que usan varias pantallas. Antes
   estaban dentro de paciente.js, y por eso el selector de colonia no
   funcionaba en Configuracion > Datos del consultorio: ese modulo no
   carga paciente.js. Lo que sirve a mas de un modulo va en este archivo,
   que se carga siempre.
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  selectorOcupacion();
  selectorColonia();
});

/* ---------------------------------------------------------------------
   Utilidades compartidas
   --------------------------------------------------------------------- */

/** Edad en anios cumplidos a partir de una fecha en formato aaaa-mm-dd. */
function aniosDe(fecha) {
  if (!fecha) return null;
  const nac = new Date(fecha + 'T00:00:00');
  if (Number.isNaN(nac.getTime())) return null;
  const hoy = new Date();
  let a = hoy.getFullYear() - nac.getFullYear();
  const m = hoy.getMonth() - nac.getMonth();
  if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) a--;
  return a;
}

/**
 * Deduce la fecha de nacimiento de un RFC.
 *
 * El RFC de una persona fisica lleva 4 letras y luego la fecha en
 * aammdd: CORA920403357 -> 92 04 03 -> 3 de abril de 1992.
 *
 * El siglo no viene en el RFC, asi que se decide comparando con el anio
 * actual: si los dos digitos son mayores al anio en curso, es del siglo
 * pasado. En 2026, "92" da 1992 y "05" da 2005. Falla solo con personas
 * de mas de cien anios, que no es un caso que se vaya a dar aqui.
 *
 * @returns {string|null} la fecha en aaaa-mm-dd, o null si no se pudo
 */
function fechaDesdeRfc(rfc) {
  if (!rfc) return null;

  const limpio = String(rfc).toUpperCase().replace(/[^A-ZÑ&0-9]/g, '');
  const m = limpio.match(/^[A-ZÑ&]{4}(\d{2})(\d{2})(\d{2})/);
  if (!m) return null;

  const [, aa, mm, dd] = m;
  const mes = Number(mm);
  const dia = Number(dd);
  if (mes < 1 || mes > 12 || dia < 1 || dia > 31) return null;

  const dosDigitosHoy = new Date().getFullYear() % 100;
  const siglo = Number(aa) > dosDigitosHoy ? 1900 : 2000;
  const anio = siglo + Number(aa);

  // Se comprueba que la fecha exista de verdad: un 31 de febrero
  // se convertiria solo en 3 de marzo y pasaria desapercibido.
  const f = new Date(anio, mes - 1, dia);
  if (f.getFullYear() !== anio || f.getMonth() !== mes - 1 || f.getDate() !== dia) {
    return null;
  }
  if (f > new Date()) return null;

  const p = (n) => String(n).padStart(2, '0');
  return `${anio}-${p(mes)}-${p(dia)}`;
}

/**
 * Llena un campo avisando visualmente que lo llenó el sistema.
 * Destello en el campo y una palomita que se desvanece sola.
 */
function llenarConAviso(campo, valor) {
  if (!campo) return;
  campo.value = valor;

  campo.classList.remove('campo-autollenado');
  void campo.offsetWidth;               // reinicia la animacion
  campo.classList.add('campo-autollenado');

  const contenedor = campo.closest('.campo-con-marca') || campo.parentElement;
  contenedor.querySelector('.marca-autollenado')?.remove();

  const palomita = document.createElement('i');
  palomita.className = 'bi bi-check-circle-fill marca-autollenado';
  palomita.title = 'Llenado automáticamente';
  contenedor.appendChild(palomita);

  setTimeout(() => palomita.remove(), 2500);
  campo.dispatchEvent(new Event('change', { bubbles: true }));
}


/* ---------------------------------------------------------------------
   Control de domicilio reutilizable

   La cascada estado -> municipio -> colonia -> resto del formulario se
   usa en la pestania del paciente y en el modal del tutor. En vez de
   copiar la logica, se envuelve aqui y se instancia con los ids de cada
   formulario.
   --------------------------------------------------------------------- */
class ControlDomicilio {
  constructor(cfg) {
    this.form         = document.getElementById(cfg.form);
    this.estado       = document.getElementById(cfg.estado);
    this.municipio    = document.getElementById(cfg.municipio);
    this.colonia      = document.getElementById(cfg.colonia);
    this.coloniaTexto = document.getElementById(cfg.coloniaTexto);
    this.cp           = document.getElementById(cfg.cp);
    this.idCampo      = document.getElementById(cfg.idDomicilio);
    this.aviso        = document.getElementById(cfg.aviso);
    this.btnNuevo     = document.getElementById(cfg.btnNuevo);
    this.btnGuardar   = document.getElementById(cfg.btnGuardar);
    this.selectorDependientes = cfg.dependientes;
    this.alGuardar = cfg.alGuardar || (() => {});

    if (!this.form) return;
    this.enganchar();
  }

  get dependientes() {
    return this.form.querySelectorAll(this.selectorDependientes);
  }

  enganchar() {
    this.estado.addEventListener('change', async () => {
      this.limpiarColonia();
      await this.cargarMunicipios();
    });

    this.municipio.addEventListener('change', () => {
      // Cambiar de municipio invalida la colonia elegida.
      this.limpiarColonia();
      this.actualizarPlaceholderColonia();
    });

    // El selector modal escribe el id y dispara change sobre el visible.
    this.coloniaTexto.addEventListener('change', () => this.actualizarBloqueo());

    /*
       El boton de "capturar nuevo domicilio" ya no existe en ninguna
       pantalla: el expediente acepta uno por persona desde que se
       unifico, y ese boton ofrecia capturar un segundo que el sistema
       no iba a guardar como segundo.

       La escucha se queda con el "?." porque el componente es generico
       y puede volver a hacer falta —una pantalla de proveedores, por
       ejemplo—. Sin el boton, esta linea no hace nada; con el, vuelve a
       funcionar sin tocar nada aqui.
    */
    this.btnNuevo?.addEventListener('click', () => this.limpiar());

    this.form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      await this.guardar();
    });
  }

  limpiarColonia() {
    this.colonia.value = '';
    this.coloniaTexto.value = '';
    this.cp.value = '';
    this.actualizarBloqueo();
  }

  actualizarPlaceholderColonia() {
    this.coloniaTexto.placeholder = this.municipio.value
      ? 'Da clic para buscar la colonia'
      : 'Selecciona el municipio primero';
  }

  async cargarMunicipios(preseleccion = 0) {
    this.municipio.innerHTML = '<option value="">Cargando...</option>';
    const r = await App.api('geo/municipios.php', { id_estado: this.estado.value });
    this.municipio.innerHTML = '<option value="">Selecciona municipio</option>' +
      r.datos.map((m) => `<option value="${m.id_municipio}"
        ${Number(preseleccion) === Number(m.id_municipio) ? 'selected' : ''}>
        ${App.esc(m.nombre_municipio)}</option>`).join('');
    this.actualizarPlaceholderColonia();
    this.actualizarBloqueo();
  }

  /** Bloquea o libera los campos que dependen de la colonia. */
  actualizarBloqueo() {
    const listo = !!this.colonia.value;
    this.dependientes.forEach((el) => {
      el.disabled = !listo;
      el.classList.toggle('campo-bloqueado', !listo && el.tagName === 'INPUT');
    });
    if (this.aviso) this.aviso.classList.toggle('d-none', listo);
  }

  /**
   * Deja el formulario completamente en blanco para capturar de nuevo.
   * Se limpia campo por campo en vez de usar form.reset(): reset()
   * devuelve los valores que traia el HTML al cargar, no los vacia, y
   * ademas no toca los campos deshabilitados.
   */
  limpiar() {
    this.form.querySelectorAll('input[type="text"], input[type="tel"], textarea')
      .forEach((el) => { el.value = ''; });

    this.idCampo.value = '';
    this.colonia.value = '';
    this.coloniaTexto.value = '';
    this.cp.value = '';

    // Lo unico que queda prellenado es el estado por omision.
    this.estado.value = this.estado.dataset.default || this.estado.value;

    this.btnGuardar.dataset.modo = 'nuevo';
    this.btnGuardar.querySelector('span').textContent = 'Guardar domicilio';

    this.cargarMunicipios();
    this.form.dispatchEvent(new CustomEvent('domicilio:limpio'));
  }

  /** Sube un domicilio existente al formulario para modificarlo. */
  async editar(idDomicilio) {
    const r = await App.api('paciente/domicilio_obtener.php', { id_domicilio: idDomicilio });
    const d = r.datos;

    this.idCampo.value = d.id_domicilio;
    ['calle', 'num_ext', 'num_int', 'entre_calle_1', 'entre_calle_2', 'referencia']
      .forEach((c) => {
        const el = this.form.querySelector(`[name="${c}"]`);
        if (el) el.value = d[c] ?? '';
      });

    this.estado.value = d.id_estado ?? this.estado.value;
    await this.cargarMunicipios(d.id_municipio);

    this.colonia.value = d.id_colonia ?? '';
    this.coloniaTexto.value = d.nombre_colonia ?? '';
    this.cp.value = d.codigo_postal ?? '';

    this.btnGuardar.dataset.modo = 'modificar';
    this.btnGuardar.querySelector('span').textContent = 'Modificar domicilio';
    this.actualizarBloqueo();

    this.form.dispatchEvent(new CustomEvent('domicilio:cargado', { detail: d }));
    return d;
  }

  /** Carga inicial respetando lo que ya estaba guardado. */
  async iniciar() {
    await this.cargarMunicipios(this.municipio.dataset.seleccion);
    this.actualizarBloqueo();
  }

  async guardar() {
    const datos = App.formADatos(this.form);
    datos.id_persona = this.form.querySelector('[name="id_persona"]')?.value
      || this.form.dataset.idPersona;
    datos.id_colonia = this.colonia.value;

    // Los campos deshabilitados no viajan en el FormData; se agregan.
    this.dependientes.forEach((el) => {
      if (el.name && datos[el.name] === undefined) datos[el.name] = el.value;
    });

    try {
      await App.api('paciente/domicilio_guardar.php', datos);
      App.alerta.toast(
        this.btnGuardar.dataset.modo === 'nuevo' ? 'Domicilio guardado' : 'Domicilio modificado');
      this.alGuardar();
    } catch (e) {
      App.marcarErrores(this.form, e.errores);
    }
  }
}


/* ---------------------------------------------------------------------
   Selector de ocupacion (modal con filtro)
   --------------------------------------------------------------------- */
function selectorOcupacion() {
  const modal = document.getElementById('modalOcupacion');
  if (!modal) return;

  const bsModal  = bootstrap.Modal.getOrCreateInstance(modal);
  const filtro   = document.getElementById('filtroOcupacion');
  const lista    = document.getElementById('listaOcupaciones');
  const contador = document.getElementById('contadorOcupaciones');
  const bloqueAlta = document.getElementById('altaOcupacion');
  const campoNueva = document.getElementById('nuevaOcupacion');

  let campoTexto = null;
  let campoId    = null;

  async function cargar() {
    lista.innerHTML = `<div class="cargando">
        <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
        Buscando...</div>`;

    const r = await App.api('catalogo/ocupaciones.php', { q: filtro.value.trim() });
    const datos = r.datos;

    contador.textContent = datos.length
      ? `${datos.length} ocupaci${datos.length === 1 ? 'ón' : 'ones'}` : '';

    if (!datos.length) {
      lista.innerHTML = `<div class="p-4 text-center text-secondary small">Sin resultados.</div>`;
      bloqueAlta.classList.remove('d-none');
      campoNueva.value = filtro.value.trim().toUpperCase();
      return;
    }

    bloqueAlta.classList.add('d-none');
    const actual = campoId?.value;
    lista.innerHTML = datos.map((o) => `
      <div class="opcion ${String(o.id_ocupacion) === String(actual) ? 'seleccionada' : ''}"
           data-id="${o.id_ocupacion}" data-nombre="${App.esc(o.nombre_ocupacion)}">
        <span>${App.esc(o.nombre_ocupacion)}</span>
        ${o.clave ? '<span class="badge badge-suave">Frecuente</span>' : ''}
      </div>`).join('');
  }

  document.addEventListener('click', (ev) => {
    const disparador = ev.target.closest('[data-selector="ocupacion"]');
    if (!disparador) return;
    ev.preventDefault();

    campoTexto = disparador;
    campoId    = document.getElementById(disparador.dataset.destino);
    filtro.value = '';
    bsModal.show();
    cargar();
    setTimeout(() => filtro.focus(), 250);
  });

  filtro.addEventListener('input', App.debounce(cargar, 250));
  document.getElementById('limpiarOcupacion').addEventListener('click', () => {
    filtro.value = ''; cargar(); filtro.focus();
  });

  lista.addEventListener('click', (ev) => {
    const opcion = ev.target.closest('.opcion');
    if (!opcion || !campoTexto) return;
    campoTexto.value = opcion.dataset.nombre;
    campoId.value    = opcion.dataset.id;
    campoTexto.dispatchEvent(new Event('change', { bubbles: true }));
    bsModal.hide();
  });

  document.getElementById('guardarOcupacion').addEventListener('click', async () => {
    const nombre = campoNueva.value.trim();
    if (nombre.length < 3) {
      App.alerta.error('Captura al menos 3 letras.');
      return;
    }
    try {
      const r = await App.api('catalogo/ocupacion_guardar.php', { nombre_ocupacion: nombre });
      campoTexto.value = r.datos.nombre_ocupacion;
      campoId.value    = r.datos.id_ocupacion;
      campoTexto.dispatchEvent(new Event('change', { bubbles: true }));
      App.alerta.toast('Ocupación agregada');
      bsModal.hide();
    } catch (e) { /* el mensaje ya se mostro */ }
  });
}


/* ---------------------------------------------------------------------
   Selector de colonia (modal con busqueda en vivo)

   Cualquier campo con data-selector="colonia" lo abre. Necesita saber de
   que municipio buscar, y eso se indica con data-municipio, que apunta
   al id del combo de municipio del mismo formulario.
   --------------------------------------------------------------------- */
function selectorColonia() {
  const modal = document.getElementById('modalColoniaSel');
  if (!modal) return;

  const bsModal   = bootstrap.Modal.getOrCreateInstance(modal);
  const filtro    = document.getElementById('filtroColoniaSel');
  const lista     = document.getElementById('listaColoniaSel');
  const contador  = document.getElementById('contadorColoniaSel');
  const ubicacion = document.querySelector('#coloniaSelUbicacion span');
  const avisoSin  = document.getElementById('avisoSinColonia');

  let campoTexto = null;
  let campoId    = null;
  let campoCp    = null;
  let municipio  = null;
  let todas      = [];      // se cargan una vez y se filtran en memoria

  async function cargar() {
    lista.innerHTML = `<div class="cargando">
        <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
        Cargando colonias...</div>`;

    const r = await App.api('geo/colonias.php', { id_municipio: municipio.value });
    todas = r.datos;
    pintar();
  }

  /** El filtrado es en memoria: son cientos de colonias, no miles, y
      asi la busqueda responde en el mismo instante en que se teclea. */
  function pintar() {
    const q = filtro.value.trim().toUpperCase();
    const datos = q
      ? todas.filter((c) =>
          c.nombre_colonia.includes(q) || c.codigo_postal.includes(q))
      : todas;

    contador.textContent = datos.length
      ? `${datos.length} de ${todas.length} colonias` : '';

    avisoSin.classList.toggle('d-none', datos.length > 0);

    if (!datos.length) {
      lista.innerHTML = `<div class="p-4 text-center text-secondary small">
          Sin resultados para "${App.esc(filtro.value.trim())}".</div>`;
      return;
    }

    const actual = campoId?.value;
    lista.innerHTML = datos.slice(0, 300).map((c) => `
      <div class="opcion ${String(c.id_colonia) === String(actual) ? 'seleccionada' : ''}"
           data-id="${c.id_colonia}"
           data-nombre="${App.esc(c.nombre_colonia)}"
           data-cp="${App.esc(c.codigo_postal)}">
        <div>
          <div class="fw-semibold">${App.esc(c.nombre_colonia)}</div>
          <div class="text-secondary" style="font-size:11px">
            ${App.esc(c.tipo_asenta ?? 'COLONIA')}
          </div>
        </div>
        <span class="badge badge-suave">${App.esc(c.codigo_postal)}</span>
      </div>`).join('');

    if (datos.length > 300) {
      lista.innerHTML += `<div class="p-2 text-center text-secondary" style="font-size:11px">
          Se muestran las primeras 300. Escribe para acotar la búsqueda.</div>`;
    }
  }

  document.addEventListener('click', (ev) => {
    const disparador = ev.target.closest('[data-selector="colonia"]');
    if (!disparador) return;
    ev.preventDefault();

    municipio = document.getElementById(disparador.dataset.municipio);

    if (!municipio || !municipio.value) {
      App.alerta.info('Falta el municipio',
        'Selecciona primero el municipio para poder buscar la colonia.');
      return;
    }

    campoTexto = disparador;
    campoId    = document.getElementById(disparador.dataset.destino);
    campoCp    = disparador.dataset.cpDestino
      ? document.getElementById(disparador.dataset.cpDestino) : null;

    const opcionMun = municipio.selectedOptions[0]?.textContent.trim() ?? '';
    ubicacion.textContent = opcionMun;

    filtro.value = '';
    bsModal.show();
    cargar();
    setTimeout(() => filtro.focus(), 250);
  });

  filtro.addEventListener('input', App.debounce(pintar, 120));
  document.getElementById('limpiarColoniaSel').addEventListener('click', () => {
    filtro.value = ''; pintar(); filtro.focus();
  });

  lista.addEventListener('click', (ev) => {
    const opcion = ev.target.closest('.opcion');
    if (!opcion || !campoTexto) return;

    campoTexto.value = opcion.dataset.nombre;
    campoId.value    = opcion.dataset.id;
    if (campoCp) campoCp.value = opcion.dataset.cp;

    campoTexto.dispatchEvent(new Event('change', { bubbles: true }));
    bsModal.hide();
  });
}


/* =====================================================================
   PASOS

   Un formulario de sesenta campos abruma: quien captura no sabe cuanto
   falta y abandona a la mitad. Partido en pasos se ve el avance y cada
   pantalla cabe sin desplazarse.

   El mismo marcado sirve para dos modos:
     - "pasos"       al capturar por primera vez, uno a la vez
     - "colapsable"  cuando el registro ya existe, para ir a una parte

   La vista solo tiene que envolver cada bloque en
   <section class="paso" data-titulo="..." data-icono="bi-...">
   ===================================================================== */
const Pasos = (() => {

  /**
   * @param {object} cfg
   *   contenedor  id del elemento que envuelve las <section class="paso">
   *   modo        'pasos' | 'colapsable'
   *   alTerminar  se llama al dar el boton final
   *   textoFinal  etiqueta del boton del ultimo paso
   *   validar     (indice) => bool | string   opcional
   */
  function crear(cfg) {
    const raiz = document.getElementById(cfg.contenedor);
    if (!raiz) return null;

    const secciones = [...raiz.querySelectorAll('section.paso')];
    if (!secciones.length) return null;

    let actual = 0;
    let modo = cfg.modo ?? 'pasos';

    /* ---------------- Construccion ---------------- */
    const barra = document.createElement('div');
    barra.className = 'barra-pasos';
    raiz.prepend(barra);

    const pie = document.createElement('div');
    pie.className = 'pie-pasos';
    raiz.append(pie);

    function pintarBarra() {
      if (modo === 'colapsable') {
        barra.innerHTML = '';
        barra.classList.add('d-none');
        return;
      }
      barra.classList.remove('d-none');

      barra.innerHTML = secciones.map((s, i) => `
        <button type="button" class="paso-chip ${
          i === actual ? 'activo' : (i < actual ? 'hecho' : '')
        }" data-ir="${i}">
          <span class="numero">${i < actual ? '<i class="bi bi-check-lg"></i>' : i + 1}</span>
          <span class="texto">${App.esc(s.dataset.titulo ?? 'Paso ' + (i + 1))}</span>
        </button>
        ${i < secciones.length - 1 ? '<span class="union"></span>' : ''}
      `).join('');
    }

    function pintarPie() {
      if (modo === 'colapsable') {
        pie.innerHTML = `
          <div class="text-end w-100">
            <button type="button" class="btn btn-morado" data-accion="terminar">
              <i class="bi bi-check-lg me-1"></i> ${App.esc(cfg.textoFinal ?? 'Guardar')}
            </button>
          </div>`;
        return;
      }

      const ultimo = actual === secciones.length - 1;
      pie.innerHTML = `
        <button type="button" class="btn btn-light ${actual === 0 ? 'invisible' : ''}"
                data-accion="atras">
          <i class="bi bi-chevron-left me-1"></i> Anterior
        </button>
        <span class="contador">Paso ${actual + 1} de ${secciones.length}</span>
        <button type="button" class="btn btn-morado" data-accion="${ultimo ? 'terminar' : 'siguiente'}">
          ${ultimo
            ? `<i class="bi bi-check-lg me-1"></i> ${App.esc(cfg.textoFinal ?? 'Guardar')}`
            : 'Siguiente <i class="bi bi-chevron-right ms-1"></i>'}
        </button>`;
    }

    /**
     * Muestra el paso actual.
     *
     * @param {number} desde  paso que se estaba viendo, para saber si
     *                        el movimiento fue hacia adelante o hacia
     *                        atras y deslizar en esa direccion. Sin
     *                        esto la pantalla cambia de golpe y cuesta
     *                        seguir donde quedo uno.
     */
    function mostrar(desde) {
      if (modo === 'colapsable') {
        secciones.forEach((s) => s.classList.remove('d-none'));
        return;
      }

      secciones.forEach((s, i) => s.classList.toggle('d-none', i !== actual));

      const visible = secciones[actual];
      if (visible && desde !== undefined && desde !== actual) {
        const clase = actual > desde ? 'entra-derecha' : 'entra-izquierda';
        visible.classList.remove('entra-derecha', 'entra-izquierda');
        // Forzar el reflow hace que la animacion vuelva a correr aunque
        // sea la misma clase de la vez pasada.
        void visible.offsetWidth;
        visible.classList.add(clase);
      }

      raiz.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /**
     * Convierte cada seccion en un bloque que se abre y se cierra.
     * Se hace con JavaScript y no con el acordeon de Bootstrap para no
     * tener que duplicar el marcado de las vistas.
     */
    function armarColapsables() {
      secciones.forEach((s, i) => {
        if (s.dataset.colapsableListo) return;
        s.dataset.colapsableListo = '1';

        const cuerpo = document.createElement('div');
        cuerpo.className = 'cuerpo-colapsable';
        while (s.firstChild) cuerpo.appendChild(s.firstChild);

        const cabeza = document.createElement('button');
        cabeza.type = 'button';
        cabeza.className = 'cabeza-colapsable';
        cabeza.innerHTML = `
          <span class="numero">${i + 1}</span>
          <i class="bi ${s.dataset.icono ?? 'bi-card-list'}"></i>
          <span class="flex-grow-1 text-start">${App.esc(s.dataset.titulo ?? '')}</span>
          <i class="bi bi-chevron-down flecha"></i>`;

        // Abrir y cerrar con altura animada. No se puede animar
        // 'height: auto', asi que se mide el contenido, se anima hasta
        // ese numero y al terminar se suelta para que la seccion
        // pueda crecer sola si algo cambia adentro.
        cabeza.addEventListener('click', () => {
          const abierto = s.classList.toggle('abierto');
          cabeza.classList.toggle('abierto', abierto);

          if (abierto) {
            cuerpo.style.height = '0px';
            void cuerpo.offsetHeight;
            cuerpo.style.height = cuerpo.scrollHeight + 'px';
            cuerpo.addEventListener('transitionend', function suelta() {
              cuerpo.style.height = '';
              cuerpo.removeEventListener('transitionend', suelta);
            });
          } else {
            cuerpo.style.height = cuerpo.scrollHeight + 'px';
            void cuerpo.offsetHeight;
            cuerpo.style.height = '0px';
          }
        });

        s.append(cabeza, cuerpo);
        s.classList.add('seccion-colapsable');

        // El primero abierto: da contexto sin obligar a abrir nada.
        if (i === 0) {
          s.classList.add('abierto');
          cabeza.classList.add('abierto');
        }
      });
    }

    function deshacerColapsables() {
      secciones.forEach((s) => {
        if (!s.dataset.colapsableListo) return;
        const cuerpo = s.querySelector('.cuerpo-colapsable');
        s.querySelector('.cabeza-colapsable')?.remove();
        while (cuerpo.firstChild) s.appendChild(cuerpo.firstChild);
        cuerpo.remove();
        s.classList.remove('seccion-colapsable', 'abierto');
        delete s.dataset.colapsableListo;
      });
    }

    /* ---------------- Navegacion ---------------- */
    async function avanzar(destino) {
      if (destino > actual && cfg.validar) {
        // Solo se valida hacia adelante: retroceder a corregir siempre
        // debe estar permitido.
        for (let i = actual; i < destino; i++) {
          const r = await cfg.validar(i, secciones[i]);
          if (r !== true) {
            App.alerta.error('Falta información',
              typeof r === 'string' ? r : 'Completa este paso antes de continuar.');
            return;
          }
        }
      }

      const anterior = actual;
      actual = Math.max(0, Math.min(secciones.length - 1, destino));
      pintarBarra();
      pintarPie();
      mostrar(anterior);
    }

    barra.addEventListener('click', (ev) => {
      const btn = ev.target.closest('[data-ir]');
      if (btn) avanzar(Number(btn.dataset.ir));
    });

    pie.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('[data-accion]');
      if (!btn) return;

      if (btn.dataset.accion === 'atras')     avanzar(actual - 1);
      if (btn.dataset.accion === 'siguiente') avanzar(actual + 1);

      if (btn.dataset.accion === 'terminar') {
        if (modo === 'pasos' && cfg.validar) {
          const r = await cfg.validar(actual, secciones[actual]);
          if (r !== true) {
            App.alerta.error('Falta información',
              typeof r === 'string' ? r : 'Completa este paso.');
            return;
          }
        }
        cfg.alTerminar?.();
      }
    });

    /* ---------------- Arranque ---------------- */
    function aplicarModo(nuevo) {
      modo = nuevo;
      if (modo === 'colapsable') {
        armarColapsables();
      } else {
        deshacerColapsables();
      }
      pintarBarra();
      pintarPie();
      mostrar();
    }

    aplicarModo(modo);

    return {
      aplicarModo,
      ir: avanzar,
      get modo() { return modo; },
      get indice() { return actual; },
      abrirSeccion(i) {
        if (modo === 'colapsable') {
          secciones[i]?.classList.add('abierto');
          secciones[i]?.querySelector('.cabeza-colapsable')?.classList.add('abierto');
          secciones[i]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
          avanzar(i);
        }
      },
    };
  }

  return { crear };
})();

/* =====================================================================
   Planes de tratamiento: la fila de la tabla

   La misma fila se usa en dos lugares (la pestania del paciente y el
   listado de Consultas > Planes de tratamiento). Tenerla escrita dos
   veces garantizaba que tarde o temprano se vieran distintas.
   ===================================================================== */
const Planes = (() => {

  /**
   * @param {object} p       plan devuelto por planes_listar.php
   * @param {number} i       consecutivo visible (no el id)
   * @param {number|string|null} idExpediente
   *        Cuando se pinta dentro del expediente de un paciente se pasa
   *        su id y la columna Paciente se omite: ya se sabe de quien es.
   *        Sin id, la fila trae esa columna.
   */
  function fila(p, i, idExpediente) {
    const conceptos  = Number(p.conceptos) || 0;
    const terminados = Number(p.terminados) || 0;
    const avance = conceptos > 0 ? Math.round((terminados / conceptos) * 100) : 0;

    const expediente = idExpediente ?? p.id_registro_paciente;
    const detalle = `${App.URL_BASE}/index.php?modulo=consultas`
      + `&vista=nuevo_plan_tratamiento&id=${expediente}`
      + `&plan=${p.id_plan_tratamiento}`;

    return `
    <tr>
      <td class="text-secondary">${i + 1}</td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <a href="${detalle}" class="icono-plan" title="Abrir el plan">
            <i class="bi bi-clipboard2-check"></i>
          </a>
          <a href="${detalle}" class="enlace-paciente">${App.esc(p.folio)}</a>
        </div>
      </td>
      <td>${App.esc(App.fecha(p.fecha))}</td>
      <td>
        <div class="plan-diagnostico">${App.esc(p.descripcion || '—')}</div>
      </td>
      ${idExpediente ? '' : `
        <td>
          <a class="enlace-paciente"
             href="${App.URL_BASE}/index.php?modulo=paciente&vista=detalle_paciente&id=${p.id_registro_paciente}">
            ${App.esc(p.paciente ?? '')}
          </a>
          <div class="text-secondary" style="font-size:11px">
            ${App.esc(p.num_expediente ?? '')}
          </div>
        </td>`}
      <td>
        <div class="plan-diagnostico">
          ${p.diagnostico ? App.esc(p.diagnostico.substring(0, 90)) : '—'}
        </div>
        <div class="plan-dentista">${App.esc(p.dentista)}</div>
      </td>
      <td>
        <div style="background:var(--gris-100);border-radius:999px;height:6px;
                    overflow:hidden;margin-bottom:3px">
          <div style="width:${avance}%;height:100%;background:var(--exito)"></div>
        </div>
        <span style="font-size:11px" class="text-secondary">
          ${terminados} de ${conceptos} · ${avance}%
        </span>
      </td>
      <td><span class="badge badge-suave">${App.esc(p.nombre_estado)}</span></td>
      <td class="col-acciones">
        <div class="dropdown">
          <button type="button" class="btn-menu-fila" data-bs-toggle="dropdown"
                  aria-label="Acciones"><i class="bi bi-list fs-5"></i></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="${detalle}">
              <i class="bi bi-pencil me-2"></i>Ver o modificar</a></li>
          </ul>
        </div>
      </td>
    </tr>`;
  }

  return { fila };
})();

/* =====================================================================
   Nueva consulta

   Una consulta puede ser suelta (revision, urgencia, valoracion) o parte
   de un plan de tratamiento en proceso. La diferencia importa: de ella
   depende en que bloque del expediente queda guardada y contra que plan
   se cobra. Preguntarlo al abrir es mas barato que corregirlo despues.
   ===================================================================== */
const NuevaConsulta = (() => {

  /** Ruta relativa, que es lo que espera App.ir(). */
  function ruta(idExpediente, idPlan) {
    return `index.php?modulo=consultas&vista=consulta`
      + `&paciente=${idExpediente}`
      + (idPlan ? `&plan=${idPlan}` : '');
  }

  /**
   * Desde el expediente del paciente: hay que averiguar si tiene planes
   * en proceso antes de poder preguntar nada.
   */
  async function desdePaciente(idExpediente) {
    App.alerta.cargando('Revisando sus planes de tratamiento...');

    let enProceso = [];
    try {
      const r = await App.api('consultas/planes_listar.php', {
        id_registro_paciente: idExpediente,
      });
      // 3 = En proceso. Un plan propuesto todavia no se autoriza, y uno
      // aceptado pero sin arrancar se pone en proceso al abrirlo.
      enProceso = r.datos.filter((p) => Number(p.id_estado_plan) === 3);
    } catch (e) {
      App.alerta.cerrar();
      return;
    }
    App.alerta.cerrar();

    // Sin planes vivos no hay nada que preguntar.
    if (!enProceso.length) {
      App.ir(ruta(idExpediente, null));
      return;
    }

    const opciones = {};
    enProceso.forEach((p) => {
      opciones[p.id_plan_tratamiento] =
        `${p.folio} — ${p.diagnostico
            ? p.diagnostico.substring(0, 45) : 'SIN DIAGNÓSTICO'}`;
    });

    const r = await Swal.fire({
      icon: 'question',
      title: '¿Qué tipo de consulta es?',
      html: `<div style="font-size:14px;line-height:1.6;text-align:left">
               Este paciente tiene <b>${enProceso.length}</b> plan(es) de
               tratamiento en proceso. Si la consulta es parte de uno de
               ellos, elígelo para que quede guardada dentro de su
               expediente y se descuente de su avance.
             </div>`,
      input: 'select',
      inputOptions: opciones,
      inputValue: String(enProceso[0].id_plan_tratamiento),
      showDenyButton: true,
      showCancelButton: true,
      confirmButtonText: 'Ligar al plan elegido',
      confirmButtonColor: '#7b2d8e',
      denyButtonText: 'Es una consulta general',
      denyButtonColor: '#c2568f',
      cancelButtonText: 'Cancelar',
      cancelButtonColor: '#6c6577',
    });

    if (r.isConfirmed) {
      App.ir(ruta(idExpediente, r.value));
    } else if (r.isDenied) {
      App.ir(ruta(idExpediente, null));
    }
  }

  /** Desde el detalle de un plan: el plan ya se sabe, solo se avisa. */
  async function desdePlan(idExpediente, idPlan, folio) {
    const ok = await App.alerta.confirmar(
      'Nueva consulta del plan ' + folio,
      'La consulta quedará vinculada a este plan de tratamiento: se '
      + 'guardará dentro de su bloque en el expediente y contará para '
      + 'su avance.',
      'Sí, crear la consulta');

    if (ok) App.ir(ruta(idExpediente, idPlan));
  }

  return { desdePaciente, desdePlan };
})();

document.addEventListener('DOMContentLoaded', () => {
  // Delegado y por atributo, no por id.
  //
  // Antes buscaba un unico boton por id y leia el expediente del
  // contenedor de pestanias. Las pestanias se fueron, el selector
  // quedo apuntando a nada, y el boton dejo de hacer nada sin dar
  // error. Ademas ahora hay DOS botones de nueva consulta en la misma
  // pantalla —el de arriba y el de la tarjeta— y getElementById solo
  // encuentra el primero.
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-nueva-consulta]');
    if (!b) return;
    NuevaConsulta.desdePaciente(b.dataset.nuevaConsulta);
  });

  // Estado de cuenta: ventana centrada, no pestania nueva. Proporcion
  // de hoja carta, que es como esta armado el documento.
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-estado-cuenta]');
    if (!b) return;
    App.abrirVentana(
      `${App.URL_BASE}/estado_cuenta.php?id=${b.dataset.estadoCuenta}`,
      900, 1020, 'estadocuenta');
  });

  document.getElementById('planNuevaConsulta')?.addEventListener('click', (ev) => {
    const b = ev.currentTarget;
    NuevaConsulta.desdePlan(
      b.dataset.expediente, b.dataset.plan, b.dataset.folio);
  });
});

/* =====================================================================
   Selector de organos dentarios

   Maneja el modal con la arcada dibujada. Se usa desde el plan de
   tratamiento y queda listo para la consulta y el odontograma.

   Una seleccion puede ser:
     - una lista de dientes  -> [16, 26]
     - "no aplica"           -> lista vacia y noAplica = true
     - sin decidir todavia   -> lista vacia y noAplica = false
   Los tres casos son distintos y por eso no basta con la lista vacia.
   ===================================================================== */
const Dientes = (() => {

  let modal      = null;
  let elegidos   = new Set();
  let alElegir   = null;

  /** Texto corto para el boton de la tabla. */
  function etiqueta(lista, noAplica) {
    if (noAplica) return 'No aplica';

    const n = (lista ?? []).length;
    if (n === 0) return 'Elegir...';
    if (n <= 4)  return lista.join(', ');

    // Con muchos dientes el numero dice mas que la lista cortada.
    return `${n} órganos`;
  }

  function caja() { return document.getElementById('modalDientes'); }

  function pintarSeleccion() {
    const noAplica = document.getElementById('dientesNoAplica').checked;

    caja().querySelectorAll('.diente').forEach((b) => {
      b.classList.toggle('elegido', elegidos.has(Number(b.dataset.diente)));
      b.disabled = noAplica;
    });

    const lista = [...elegidos].sort((a, b) => a - b);
    document.getElementById('dientesResumen').textContent = noAplica
      ? 'El procedimiento no va sobre un órgano dentario'
      : (lista.length
          ? `${lista.length} seleccionado(s): ${lista.join(', ')}`
          : 'Sin selección');
  }

  /**
   * Abre el modal.
   * @param {object} cfg {procedimiento, dientes, noAplica, alElegir}
   */
  function pedir(cfg) {
    if (!caja()) return;

    modal = modal ?? bootstrap.Modal.getOrCreateInstance(caja());
    alElegir = cfg.alElegir;

    elegidos = new Set(cfg.dientes ?? []);
    document.getElementById('dientesNoAplica').checked = !!cfg.noAplica;
    document.getElementById('dientesProcedimiento').textContent =
      cfg.procedimiento ?? '—';

    /*
       Con que denticion abrir.

       Es un consultorio pediatrico: abrir siempre en permanente obliga
       a cambiar de pestania en la mayoria de los pacientes, varias
       veces al dia. Se decide asi, en este orden:

         1. Si el renglon YA trae dientes, manda lo que trae. Alguien ya
            eligio y no hay nada que adivinar.
         2. Si no, manda la edad del paciente.

       Los cortes son los de la erupcion dental: hasta los 6 anos la
       boca es temporal; de 6 a 12 es mixta, y ahi se abre en temporal
       porque es donde esta el trabajo de un odontopediatra; de 12 en
       adelante, permanente.

       Siempre se puede cambiar a mano: esto decide donde EMPIEZA, no
       que se puede elegir.
    */
    let temporal;
    if (elegidos.size > 0) {
      temporal = [...elegidos].some((n) => n >= 51);
    } else {
      const edad = Number(cfg.edad);
      temporal = Number.isFinite(edad) && edad > 0 ? edad < 12 : false;
    }

    const pestania = document.querySelector(
      `[data-bs-target="${temporal ? '#dent-temporal' : '#dent-permanente'}"]`);
    if (pestania) bootstrap.Tab.getOrCreateInstance(pestania).show();

    pintarSeleccion();
    modal.show();
  }

  function iniciar() {
    if (!caja()) return;

    caja().addEventListener('click', (ev) => {
      const diente = ev.target.closest('.diente');
      if (diente) {
        const n = Number(diente.dataset.diente);
        if (elegidos.has(n)) elegidos.delete(n); else elegidos.add(n);
        pintarSeleccion();
        return;
      }

      const todos = ev.target.closest('[data-todos]');
      if (todos) {
        caja().querySelectorAll(`.diente.${todos.dataset.todos}`)
          .forEach((b) => elegidos.add(Number(b.dataset.diente)));
        // Elegir dientes contradice "no aplica": se apaga sola.
        document.getElementById('dientesNoAplica').checked = false;
        pintarSeleccion();
        return;
      }

      const ninguno = ev.target.closest('[data-ninguno]');
      if (ninguno) {
        caja().querySelectorAll(`.diente.${ninguno.dataset.ninguno}`)
          .forEach((b) => elegidos.delete(Number(b.dataset.diente)));
        pintarSeleccion();
      }
    });

    document.getElementById('dientesNoAplica')
      .addEventListener('change', (ev) => {
        if (ev.target.checked) elegidos.clear();
        pintarSeleccion();
      });

    document.getElementById('dientesAceptar').addEventListener('click', () => {
      const noAplica = document.getElementById('dientesNoAplica').checked;
      alElegir?.({
        dientes: noAplica ? [] : [...elegidos].sort((a, b) => a - b),
        noAplica,
      });
      modal.hide();
    });
  }

  document.addEventListener('DOMContentLoaded', iniciar);

  return { pedir, etiqueta };
})();

/* =====================================================================
   Visor de album

   Lo usan el panel del plan, el expediente electronico y la consulta.
   Vive aqui por la misma razon que el selector de dientes: son tres
   pantallas y un solo visor, y tenerlo duplicado garantizaba que tarde
   o temprano se vieran distintos.

   Quien lo abre solo pasa la lista de imagenes y un titulo. El visor no
   sabe si vienen de un plan, de una consulta o del expediente.
   ===================================================================== */
const Album = (() => {

  let modal   = null;
  let lista   = [];
  let indice  = 0;

  function caja() { return document.getElementById('modalAlbumVer'); }

  /**
   * @param {object}   cfg
   * @param {string}   cfg.titulo
   * @param {object[]} cfg.imagenes  cada una con id_documento y, si hay,
   *                                 descripcion / nombre_original.
   * @param {number}   [cfg.desde]   en cual abrir. 0 por omision.
   */
  function abrir(cfg) {
    if (!caja() || !cfg.imagenes?.length) return;

    modal = modal ?? bootstrap.Modal.getOrCreateInstance(caja());
    lista = cfg.imagenes;

    document.getElementById('albVerTitulo').textContent =
      `${cfg.titulo ?? 'Álbum'} · ${lista.length} elemento${lista.length > 1 ? 's' : ''}`;

    document.getElementById('albVerTira').innerHTML = lista.map((d, j) => `
      <button type="button" class="mini" data-mini="${j}"
              title="${App.esc(d.descripcion ?? d.nombre_original ?? '')}">
        <img src="${App.URL_BASE}/archivo.php?id=${d.id_documento}" alt="">
      </button>`).join('');

    mostrar(cfg.desde ?? 0);
    modal.show();
  }

  function mostrar(i) {
    if (!lista.length) return;

    /*
       Hacia donde va, para animarla hacia ese lado.

       Se calcula ANTES de normalizar el indice: ahi todavia se sabe si
       pidieron "la siguiente" o "la anterior". Despues de dar la vuelta
       —de la ultima a la primera— la resta de indices dice lo
       contrario de lo que paso.
    */
    const haciaAdelante = i > indice;

    // Da la vuelta en los extremos: llegar a la ultima y seguir lleva a
    // la primera. Comparar la radiografia de hoy con la primera del
    // tratamiento es justo lo que se hace, y con tope habria que
    // recorrer todo de regreso.
    const antes = indice;
    indice = (i + lista.length) % lista.length;
    const d = lista[indice];

    const img = document.getElementById('albVerImagen');

    /*
       La imagen entra deslizandose desde el lado por el que viene.

       No es adorno: con dos radiografias parecidas, cambiar sin
       movimiento deja la duda de si de verdad cambio algo. El
       desplazamiento dice "esta es otra, y esta a la derecha de la
       anterior", que es justo lo que hace falta al comparar.

       Solo si de verdad cambio de imagen: repintar la misma —al abrir,
       o al picar la miniatura que ya esta activa— no deberia moverse.
    */
    if (antes !== indice) {
      img.classList.remove('entra-izq', 'entra-der');
      // Forzar un reflow reinicia la animacion. Sin esto, dos pasos
      // seguidos en la misma direccion solo se animan la primera vez:
      // el navegador ve la misma clase y no vuelve a empezar.
      void img.offsetWidth;
      img.classList.add(haciaAdelante ? 'entra-der' : 'entra-izq');
    }

    img.src =
      `${App.URL_BASE}/archivo.php?id=${d.id_documento}`;
    document.getElementById('albVerDescripcion').textContent =
      d.descripcion || d.nombre_original || d.nombre_documento || '';
    document.getElementById('albVerContador').textContent =
      `${indice + 1} de ${lista.length}`;
    // El enlace conserva su href —para "abrir en otra pestania" del menu
    // del boton derecho, y para que se vea a donde lleva— pero el clic
    // normal abre una ventana a la medida de la imagen. Ver abajo.
    document.getElementById('albVerAbrir').href =
      `${App.URL_BASE}/archivo.php?id=${d.id_documento}`;

    caja().querySelectorAll('.tira-miniaturas .mini').forEach((m, j) => {
      m.classList.toggle('activa', j === indice);
    });
    caja().querySelector('.tira-miniaturas .mini.activa')
      ?.scrollIntoView({ block: 'nearest', inline: 'center' });
  }

  /** Solo imagenes: un PDF no se puede mostrar como miniatura. */
  function soloImagenes(docs) {
    return (docs ?? []).filter(
      (d) => (d.tipo_mime || '').startsWith('image/') && Number(d.estado) !== 0);
  }

  function iniciar() {
    if (!caja()) return;

    caja().addEventListener('click', (ev) => {
      const m = ev.target.closest('[data-mini]');
      if (m) { mostrar(Number(m.dataset.mini)); return; }
      if (ev.target.closest('#albVerAnterior'))  mostrar(indice - 1);
      if (ev.target.closest('#albVerSiguiente')) mostrar(indice + 1);

      /*
         "Abrir en pestania nueva" abre una VENTANA a la medida.

         Una pestania nueva deja la imagen pegada arriba a la izquierda
         y con el navegador entero alrededor: para mirar una radiografia
         eso es peor que el visor que se acaba de dejar. Una ventana del
         tamanio de la imagen, centrada sobre la pantalla, ensenia la
         radiografia y nada mas.

         Se mide la imagen que ya esta cargada en el visor, que es
         gratis: pedir el tamanio al servidor seria un viaje de ida y
         vuelta para algo que ya tenemos delante.
      */
      const abrir = ev.target.closest('#albVerAbrir');
      if (!abrir) return;

      ev.preventDefault();

      const img = document.getElementById('albVerImagen');
      const anchoReal = img.naturalWidth  || 900;
      const altoReal  = img.naturalHeight || 700;

      // Margen para la barra del navegador y un respiro alrededor.
      App.abrirVentana(abrir.href, anchoReal + 40, altoReal + 120,
        `imagen${indice}`);
    });

    // Las flechas del teclado son lo natural en un visor de imagenes.
    caja().addEventListener('keydown', (ev) => {
      if (ev.key === 'ArrowLeft')  mostrar(indice - 1);
      if (ev.key === 'ArrowRight') mostrar(indice + 1);
    });
  }

  document.addEventListener('DOMContentLoaded', iniciar);

  return { abrir, soloImagenes };
})();

/* =====================================================================
   Detalle de una cita
   =====================================================================
   Vive aqui por la misma razon que el album y el visor: son dos
   pantallas —la agenda del dia y la de la semana— y un solo detalle.
   Tenerlo duplicado garantizaba que tarde o temprano una ofreciera un
   cambio de estatus que la otra no, y nadie entenderia por que.

   Quien lo abre pasa la cita y dos cosas que hacer: que ocurre al
   cambiar el estatus y que ocurre al pedir "Modificar". El componente
   no sabe si lo llamaron desde el dia o desde la semana.
   ===================================================================== */
const DetalleCita = (() => {

  /* Que se puede hacer desde cada estatus.

     Estan aqui y no en cada pantalla porque son reglas del negocio, no
     de la vista: una cita cancelada no se "confirma", y una atendida ya
     no cambia. Repetir la tabla en dos sitios es repetir la regla.

     Los numeros son estado_cita: 1 pendiente, 2 confirmada, 3 en sala,
     4 atendida, 5 cancelada, 6 no asistio. */
  const SIGUIENTES = {
    1: [[2, 'Confirmar', 'bi-check2-circle'],
        [3, 'En sala',   'bi-door-open'],
        [6, 'No asistió', 'bi-person-x'],
        [5, 'Cancelar',  'bi-x-circle']],
    2: [[3, 'En sala',    'bi-door-open'],
        [4, 'Atendida',   'bi-clipboard2-check'],
        [6, 'No asistió', 'bi-person-x'],
        [5, 'Cancelar',   'bi-x-circle']],
    3: [[4, 'Atendida',   'bi-clipboard2-check'],
        [6, 'No asistió', 'bi-person-x']],
    // Atendida, cancelada y no asistio no llevan a ningun lado: lo que
    // ya paso, paso. Para corregir un error esta "Modificar".
    4: [], 5: [], 6: [],
  };

  function caja() { return document.getElementById('modalDetalleCita'); }

  let modal = null;
  let actual = null;
  let alCambiar = null;
  let alModificar = null;

  /**
   * @param {object}   cita        tal como la devuelve el listado
   * @param {object}   acciones
   * @param {Function} acciones.alCambiar    (idCita, idEstado) => Promise
   * @param {Function} acciones.alModificar  (cita) => void
   */
  function abrir(cita, acciones = {}) {
    if (!caja() || !cita) return;

    actual      = cita;
    alCambiar   = acciones.alCambiar ?? null;
    alModificar = acciones.alModificar ?? null;

    modal = modal ?? bootstrap.Modal.getOrCreateInstance(caja());
    pintar();
    modal.show();
  }

  function pintar() {
    const c = actual;

    // El listado del dia lo llama nombre_completo y el de la semana
    // paciente. Se aceptan los dos en vez de uniformar las consultas:
    // tocar las dos para esto arriesgaba romper lo que ya funciona.
    const nombre = c.nombre_completo ?? c.paciente ?? '';

    const enlace = document.getElementById('dcNombre');
    enlace.textContent = nombre;
    enlace.href = `${App.URL_BASE}/index.php?modulo=paciente`
                + `&vista=detalle_paciente&id=${c.id_registro_paciente}`;

    document.getElementById('dcSub').textContent = [
      c.num_expediente,
      c.telefono,
      c.edad != null ? `${c.edad} años` : null,
    ].filter(Boolean).join(' · ');

    const chip = document.getElementById('dcEstado');
    chip.textContent = c.nombre_estado ?? '';
    chip.style.background = `${c.color ?? '#999'}1f`;
    chip.style.color = c.color ?? '#999';

    const inicio = String(c.fecha_inicio);
    document.getElementById('dcCuando').textContent =
      `${App.fecha(inicio.substring(0, 10))} · ${inicio.substring(11, 16)}`
      + (c.fecha_fin ? ` a ${String(c.fecha_fin).substring(11, 16)}` : '');

    document.getElementById('dcDentista').textContent =
      (c.dentista ?? '') + (c.nombre_sala ? ` · ${c.nombre_sala}` : '');

    document.getElementById('dcMotivo').textContent =
      c.motivo || c.nombre_procedimiento || 'Sin motivo capturado';

    // La caja de la nota se esconde entera si no hay nota: un titulo
    // "Nota de la cita" sobre un hueco vacio es ruido.
    const hayNota = !!(c.notas && String(c.notas).trim());
    document.getElementById('dcNotaCaja').classList.toggle('d-none', !hayNota);
    document.getElementById('dcNota').textContent = c.notas ?? '';

    pintarAcciones();
  }

  function pintarAcciones() {
    const caja2 = document.getElementById('dcAcciones');
    const puede = SIGUIENTES[Number(actual.id_estado_cita)] ?? [];

    if (!alCambiar || !puede.length) {
      caja2.innerHTML = puede.length ? '' : `
        <div class="dc-cerrada">
          Esta cita ya no cambia de estatus. Usa <b>Modificar</b> si hay
          que corregir algo.
        </div>`;
      return;
    }

    caja2.innerHTML = puede.map(([id, texto, icono]) => `
      <button type="button" class="btn btn-sm ${
        id === 5 ? 'btn-outline-danger' : 'btn-outline-morado'}"
              data-estatus="${id}">
        <i class="bi ${icono} me-1"></i>${texto}
      </button>`).join('');
  }

  function iniciar() {
    if (!caja()) return;

    caja().addEventListener('click', async (ev) => {
      const boton = ev.target.closest('[data-estatus]');
      if (!boton || !alCambiar) return;

      const nuevo = Number(boton.dataset.estatus);

      if (nuevo === 5) {
        const ok = await App.alerta.confirmar(
          '¿Cancelar esta cita?',
          'El espacio queda libre para otro paciente.',
          'Sí, cancelar');
        if (!ok) return;
      }

      // Se desactivan TODOS mientras viaja la peticion. Sin esto, dos
      // clics seguidos mandan dos cambios de estatus y el segundo puede
      // llegar despues del primero y dejar la cita en el estado
      // equivocado.
      caja().querySelectorAll('[data-estatus]')
            .forEach((b) => { b.disabled = true; });

      try {
        await alCambiar(actual.id_cita, nuevo);
        modal.hide();
      } catch (e) {
        caja().querySelectorAll('[data-estatus]')
              .forEach((b) => { b.disabled = false; });
      }
    });

    document.getElementById('dcModificar')?.addEventListener('click', () => {
      if (!alModificar) return;
      const c = actual;
      modal.hide();
      // Se espera a que el modal termine de cerrarse antes de abrir el
      // otro: dos modales de Bootstrap solapandose dejan el fondo
      // oscuro pegado y la pantalla inutilizable.
      caja().addEventListener('hidden.bs.modal',
        () => alModificar(c), { once: true });
    });
  }

  document.addEventListener('DOMContentLoaded', iniciar);

  return { abrir };
})();


/* =====================================================================
   Campos de ficha: dato plano con su lapicito

   El dato se ve como texto. Al picar el lapiz, SOLO ese campo se vuelve
   editable; lo demas sigue siendo texto. Al aceptar se envia el
   formulario de la seccion al endpoint de siempre, asi que la
   validacion y la bitacora no cambian en nada.

   Lo pinta Ficha::campo() en PHP; aqui vive lo que hace al picarlo.
   ===================================================================== */
const CamposFicha = (() => {

  /** El campo que esta abierto ahora mismo, si hay alguno. */
  let abierto = null;

  function editor(campo)  { return campo.querySelector('.cf-editor'); }
  function entrada(campo) { return campo.querySelector('input, select'); }

  function abrir(campo) {
    // Uno a la vez. Dos campos abiertos invitan a editar los dos y
    // despues a dudar de cual se guardo.
    if (abierto && abierto !== campo) cerrar(abierto, false);

    campo.classList.add('editando');
    editor(campo).classList.remove('d-none');

    const el = entrada(campo);
    el.focus();
    if (el.select) el.select();

    abierto = campo;
  }

  /**
   * @param {boolean} guardado  false = se cancelo, hay que devolver el
   *        valor original. Sin esto, cancelar dejaria lo tecleado en el
   *        campo y el siguiente guardado de la seccion lo mandaria.
   */
  function cerrar(campo, guardado) {
    if (!guardado) {
      const el = entrada(campo);
      if (el.dataset.original !== undefined) el.value = el.dataset.original;
    }
    campo.classList.remove('editando');
    editor(campo).classList.add('d-none');
    if (abierto === campo) abierto = null;
  }

  async function aceptar(campo) {
    const el = entrada(campo);

    if (!el.checkValidity()) { el.reportValidity(); return; }

    const form = campo.closest('form');
    if (!form) { cerrar(campo, true); return; }

    // Se manda la seccion COMPLETA, no el campo suelto. Reutiliza el
    // endpoint que ya existia y evita inventar uno generico que
    // escriba cualquier columna por nombre, que es justo el tipo de
    // atajo que termina dejando escribir columnas que no debia.
    const ruta = form.dataset.endpoint;
    if (!ruta) { cerrar(campo, true); return; }

    try {
      await App.api(ruta, App.formADatos(form));

      // El texto plano se actualiza con lo que se acaba de guardar.
      const valor = el.tagName === 'SELECT'
        ? (el.options[el.selectedIndex]?.textContent.trim() ?? '')
        : el.value.trim();

      const vista = campo.querySelector('.cf-valor');
      vista.textContent = valor || 'Sin capturar';
      vista.classList.toggle('vacio', valor === '');

      el.dataset.original = el.value;
      cerrar(campo, true);
      App.alerta.toast('Guardado');
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  }

  function iniciar() {
    document.querySelectorAll('.campo-ficha input, .campo-ficha select')
      .forEach((el) => { el.dataset.original = el.value; });

    document.addEventListener('click', (ev) => {
      const campo = ev.target.closest('.campo-ficha');
      if (!campo) return;

      if (ev.target.closest('.cf-lapiz'))     { abrir(campo); return; }
      if (ev.target.closest('.cf-ok'))        { aceptar(campo); return; }
      if (ev.target.closest('.cf-cancelar'))  { cerrar(campo, false); }
    });

    // Enter guarda, Escape cancela. En un campo suelto es lo que
    // cualquiera intenta antes de buscar el boton.
    document.addEventListener('keydown', (ev) => {
      const campo = ev.target.closest?.('.campo-ficha.editando');
      if (!campo) return;

      if (ev.key === 'Enter' && ev.target.tagName !== 'TEXTAREA') {
        ev.preventDefault();
        aceptar(campo);
      }
      if (ev.key === 'Escape') {
        ev.preventDefault();
        cerrar(campo, false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', iniciar);

  return { abrir };
})();

/* =====================================================================
   Selector de procedimientos del catalogo

   Lo usan el plan de tratamiento y el cargo de la consulta. Quien lo
   abre pasa que hacer con lo elegido; el modal no sabe nada de planes
   ni de cargos.
   ===================================================================== */
const SelectorProcedimiento = (() => {

  let modal      = null;
  let alElegir   = null;
  let lista      = [];

  function caja() { return document.getElementById('modalProcSel'); }

  async function cargar(q = '') {
    const caja2 = document.getElementById('procSelLista');
    caja2.innerHTML = `<div class="cargando">
        <div class="spinner-border spinner-border-sm" style="color:var(--morado-600)"></div>
        Buscando...</div>`;

    const r = await App.api('catalogo/procedimientos.php', { q });
    lista = r.datos;

    caja2.innerHTML = lista.length
      ? lista.map((p) => `
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

  /** @param {function} cb  recibe el procedimiento elegido */
  function pedir(cb) {
    if (!caja()) return;

    modal = modal ?? bootstrap.Modal.getOrCreateInstance(caja());
    alElegir = cb;

    document.getElementById('procSelFiltro').value = '';
    modal.show();
    cargar();
    setTimeout(() => document.getElementById('procSelFiltro').focus(), 250);
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!caja()) return;

    document.getElementById('procSelFiltro').addEventListener('input',
      App.debounce((ev) => cargar(ev.target.value.trim()), 300));

    document.getElementById('procSelLista').addEventListener('click', (ev) => {
      const opcion = ev.target.closest('.opcion[data-id]');
      if (!opcion) return;

      const p = lista.find((x) => String(x.id_procedimiento) === opcion.dataset.id);
      if (!p) return;

      alElegir?.(p);
      modal.hide();
    });
  });

  return { pedir };
})();
