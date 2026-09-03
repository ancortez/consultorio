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

    function mostrar() {
      if (modo === 'colapsable') {
        secciones.forEach((s) => s.classList.remove('d-none'));
        return;
      }
      secciones.forEach((s, i) => s.classList.toggle('d-none', i !== actual));
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

        cabeza.addEventListener('click', () => {
          const abierto = s.classList.toggle('abierto');
          cabeza.classList.toggle('abierto', abierto);
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

      actual = Math.max(0, Math.min(secciones.length - 1, destino));
      pintarBarra();
      pintarPie();
      mostrar();
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
