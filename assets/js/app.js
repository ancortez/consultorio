/* =====================================================================
   Utilidades comunes a todo el sistema.
   Se cargan antes que cualquier archivo de modulo.
   ===================================================================== */

const App = (() => {

  const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content ?? '';

  const URL_BASE   = meta('url-base');
  const CSRF_TOKEN = meta('csrf-token');

  /**
   * Llamada al backend. Siempre POST, siempre con token CSRF.
   * Devuelve el objeto {ok, mensaje, datos} o lanza el error ya mostrado.
   */
  async function api(ruta, datos = {}) {
    // Si ya viene un FormData (porque trae archivos), se usa tal cual.
    // Armar uno nuevo perderia los File.
    const cuerpo = datos instanceof FormData ? datos : new FormData();

    if (!(datos instanceof FormData)) {
      Object.entries(datos).forEach(([k, v]) => {
        if (v !== null && v !== undefined) cuerpo.append(k, v);
      });
    }
    cuerpo.append('csrf_token', CSRF_TOKEN);

    let respuesta;
    try {
      respuesta = await fetch(`${URL_BASE}/includes/${ruta}`, {
        method: 'POST',
        body: cuerpo,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
      });
    } catch (e) {
      alerta.error('Sin conexion con el servidor.');
      throw e;
    }

    // Sesion expirada: de vuelta al login.
    if (respuesta.status === 401) {
      await alerta.info('Tu sesion expiro', 'Vuelve a iniciar sesion.');
      window.location.href = `${URL_BASE}/login.php?expiro=1`;
      throw new Error('Sesion expirada');
    }

    let json;
    try {
      json = await respuesta.json();
    } catch (e) {
      alerta.error('El servidor respondio algo inesperado.');
      throw e;
    }

    if (!json.ok) {
      alerta.error(json.mensaje || 'No se pudo completar la operacion.');
      const err = new Error(json.mensaje);
      err.errores = json.errores || {};
      throw err;
    }

    return json;
  }

  /* ---------- SweetAlert2 con la paleta del sistema ---------- */
  const MORADO = '#7b2d8e';
  const ROSA   = '#d96bb0';

  const alerta = {
    exito: (titulo, texto = '') => Swal.fire({
      icon: 'success', title: titulo, text: texto,
      confirmButtonColor: MORADO, timer: 2200, timerProgressBar: true,
    }),
    error: (titulo, texto = '') => Swal.fire({
      icon: 'error', title: titulo, text: texto, confirmButtonColor: MORADO,
    }),
    info: (titulo, texto = '') => Swal.fire({
      icon: 'info', title: titulo, text: texto, confirmButtonColor: MORADO,
    }),
    toast: (titulo, icono = 'success') => Swal.fire({
      toast: true, position: 'top-end', icon: icono, title: titulo,
      showConfirmButton: false, timer: 2200, timerProgressBar: true,
    }),
    async confirmar(titulo, texto = '', textoBoton = 'Si, continuar') {
      const r = await Swal.fire({
        icon: 'question', title: titulo, text: texto,
        showCancelButton: true,
        confirmButtonText: textoBoton, confirmButtonColor: MORADO,
        cancelButtonText: 'Cancelar',  cancelButtonColor: '#6c6577',
        reverseButtons: true,
      });
      return r.isConfirmed;
    },
    /**
     * Pide un texto corto y obligatorio.
     *
     * Se usa para los motivos: cancelar un cargo, cancelar un cobro,
     * bloquear la agenda. En todos esos casos el "por que" es parte
     * del registro, no un adorno, asi que no se acepta vacio.
     *
     * @returns {Promise<string|null>} null si se cancelo.
     */
    async pedirTexto(titulo, texto = '', valor = '') {
      const r = await Swal.fire({
        icon: 'question', title: titulo, text: texto,
        input: 'textarea',
        inputValue: valor,
        inputAttributes: { maxlength: 255, rows: 3 },
        showCancelButton: true,
        confirmButtonText: 'Continuar', confirmButtonColor: MORADO,
        cancelButtonText: 'Cancelar',   cancelButtonColor: '#6c6577',
        reverseButtons: true,
        inputValidator: (v) => (!v || v.trim().length < 4)
          ? 'Escribe el motivo.' : undefined,
      });
      return r.isConfirmed ? r.value.trim().toUpperCase() : null;
    },
    cargando(titulo = 'Procesando...') {
      Swal.fire({
        title: titulo, allowOutsideClick: false, allowEscapeKey: false,
        didOpen: () => Swal.showLoading(),
      });
    },
    cerrar: () => Swal.close(),
  };

  /* ---------- Ayudas ---------- */

  /** Escapa texto antes de meterlo en innerHTML. */
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));

  /** Retrasa la ejecucion: evita golpear el servidor en cada tecla. */
  function debounce(fn, ms = 350) {
    let id;
    return (...args) => {
      clearTimeout(id);
      id = setTimeout(() => fn(...args), ms);
    };
  }

  /** Convierte un <form> en objeto plano. */
  const formADatos = (form) => Object.fromEntries(new FormData(form).entries());

  /** Marca los campos con error devueltos por el backend. */
  function marcarErrores(form, errores = {}) {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    Object.keys(errores).forEach((campo) => {
      const el = form.querySelector(`[name="${campo}"]`);
      if (el) el.classList.add('is-invalid');
    });
  }

  const money = (n) => new Intl.NumberFormat('es-MX', {
    style: 'currency', currency: 'MXN',
  }).format(Number(n) || 0);

  const ir = (ruta) => { window.location.href = `${URL_BASE}/${ruta}`; };

  /** Tamano de archivo legible: 1536 -> "1.5 KB". */
  function tamanoLegible(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / 1024 / 1024).toFixed(1)} MB`;
  }

  /**
   * Abre una ventana aparte, del tamano que se le pida.
   *
   * Se usa en vez de una pestania nueva para que el sistema siga a la
   * vista detras: el usuario no pierde el hilo de donde estaba, y cerrar
   * la ventanita lo regresa a lo que estaba haciendo.
   *
   * Si el navegador bloquea las ventanas emergentes, se cae de regreso a
   * una pestania normal en lugar de no hacer nada.
   */
  function abrirVentana(url, ancho = 900, alto = 700, nombre = '_blank') {
    // Centrada sobre la ventana actual, no sobre la pantalla: con dos
    // monitores, centrar en la pantalla la manda al monitor equivocado.
    const anchoMax = Math.min(ancho, window.screen.availWidth  - 40);
    const altoMax  = Math.min(alto,  window.screen.availHeight - 60);

    const x = window.screenX + Math.max(0, (window.outerWidth  - anchoMax) / 2);
    const y = window.screenY + Math.max(0, (window.outerHeight - altoMax) / 2);

    const ventana = window.open(url, nombre,
      `width=${Math.round(anchoMax)},height=${Math.round(altoMax)},`
      + `left=${Math.round(x)},top=${Math.round(y)},`
      + 'scrollbars=yes,resizable=yes,menubar=no,toolbar=no,location=no');

    if (!ventana || ventana.closed) {
      alerta.info('Ventana bloqueada',
        'El navegador bloqueó la ventana emergente. Permítelas para este '
        + 'sitio, o el documento se abrirá en una pestaña.');
      window.open(url, '_blank');
      return null;
    }

    ventana.focus();
    return ventana;
  }

  /**
   * Muestra una imagen sola, en grande y al centro de la pantalla.
   *
   * No es el album: el album es para recorrer un conjunto —una consulta
   * con sus radiografias— y trae tira de miniaturas, flechas y contador.
   * Para una fotografia suelta todo eso sobra, y abrir una ventana
   * aparte saca a la persona de la ficha para ver una cara.
   *
   * Se construye al vuelo y se destruye al cerrar. Dejarlo en el HTML de
   * cada pantalla seria repetir el mismo bloque en cuatro vistas para
   * algo que se usa de vez en cuando.
   */
  function verImagen(url, titulo = '') {
    const fondo = document.createElement('div');
    fondo.className = 'visor-imagen';
    fondo.innerHTML = `
      <button type="button" class="vi-cerrar" title="Cerrar" aria-label="Cerrar">
        <i class="bi bi-x-lg"></i>
      </button>
      <figure>
        <img src="${url}" alt="">
        ${titulo ? `<figcaption></figcaption>` : ''}
      </figure>`;

    // El titulo va por textContent y no dentro de la plantilla: puede
    // ser el nombre del paciente, y un apellido con comillas o con un
    // signo de menor que rompria el HTML.
    if (titulo) fondo.querySelector('figcaption').textContent = titulo;

    function cerrar() {
      fondo.remove();
      document.removeEventListener('keydown', porTecla);
    }

    function porTecla(ev) { if (ev.key === 'Escape') cerrar(); }

    // Clic en el fondo cierra; clic en la imagen, no. Quien pica la
    // foto para verla mejor no espera que eso la cierre.
    fondo.addEventListener('click', (ev) => {
      if (ev.target.closest('figure') && !ev.target.closest('.vi-cerrar')) return;
      cerrar();
    });

    document.addEventListener('keydown', porTecla);
    document.body.appendChild(fondo);
    fondo.querySelector('.vi-cerrar').focus();
    return fondo;
  }

  /**
   * Solo la fecha, en formato dd/mm/aaaa.
   *
   * No se usa Date() a proposito: un DATE de MySQL ("2026-09-16") lo
   * interpreta como UTC y en nuestro huso se corre un dia hacia atras.
   * Partir el texto no tiene ese problema.
   */
  function fecha(valor) {
    if (!valor) return '';
    const solo = String(valor).substring(0, 10);
    return /^\d{4}-\d{2}-\d{2}$/.test(solo)
      ? solo.split('-').reverse().join('/')
      : String(valor);
  }

  /** Fecha y hora legibles a partir de un DATETIME de MySQL. */
  function fechaHora(valor) {
    if (!valor) return '';
    const d = new Date(String(valor).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? valor
      : d.toLocaleString('es-MX', { day: '2-digit', month: '2-digit',
                                    year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  /* ---------- Mayusculas ---------- */

  /** Campos que NO se convierten: correo, contrasena, fechas, numeros. */
  const exentoDeMayusculas = (el) =>
    ['email', 'password', 'date', 'time', 'number', 'file'].includes(el.type)
    || el.classList.contains('sin-mayusculas');

  /**
   * Convierte a mayusculas conservando la posicion del cursor.
   * Sin esto, escribir en medio de una palabra manda el cursor al final
   * en cada tecla y capturar se vuelve insoportable.
   */
  function aMayusculas(el) {
    if (exentoDeMayusculas(el)) return;
    const arriba = el.value.toUpperCase();
    if (arriba === el.value) return;
    const ini = el.selectionStart;
    const fin = el.selectionEnd;
    el.value = arriba;
    if (ini !== null) {
      try { el.setSelectionRange(ini, fin); } catch (e) { /* inputs sin seleccion */ }
    }
  }

  /** Engancha la conversion a todo el documento, incluso a lo que se
      agregue despues (modales, filas nuevas). */
  function activarMayusculas() {
    document.addEventListener('input', (ev) => {
      const el = ev.target;
      if (el.matches('input[type="text"], input[type="tel"], input[type="search"], input:not([type]), textarea')) {
        aMayusculas(el);
      }
    });
    // Al pegar texto, el evento input llega despues: se resuelve solo.
  }

  /** Sube a mayusculas todos los valores de un objeto de datos. */
  function datosEnMayusculas(datos, exentos = []) {
    const salida = {};
    Object.entries(datos).forEach(([k, v]) => {
      salida[k] = (typeof v === 'string' && !exentos.includes(k))
        ? v.toUpperCase() : v;
    });
    return salida;
  }

  /* ---------- Pestanias ---------- */

  /**
   * Recuerda en que pestania estaba el usuario.
   *
   * Varias pantallas se recargan despues de guardar para releer los datos
   * del servidor. Sin esto, la recarga siempre cae en la primera pestania
   * y el usuario pierde de vista lo que acaba de modificar: guarda un
   * domicilio y aparece en Identificacion, sin saber si se guardo.
   *
   * La pestania viaja en el ancla de la direccion (#sec-domicilio), que
   * sobrevive a la recarga sin necesidad de guardar nada en el navegador.
   * Solo aplica a las pestanias de la pagina, no a las de los modales:
   * esas se reinician a proposito cada vez que se abre el modal.
   */
  function recordarPestania() {
    const enPagina = (btn) => !btn.closest('.modal');

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((btn) => {
      if (!enPagina(btn)) return;
      btn.addEventListener('shown.bs.tab', (ev) => {
        const destino = ev.target.dataset.bsTarget;
        if (destino) {
          // replaceState y no location.hash: cambiar el hash agrega una
          // entrada al historial y el boton Atras se vuelve inservible.
          history.replaceState(null, '', destino);
        }
      });
    });

    activarPestania(location.hash);
  }

  /**
   * Lleva la vista a la parte indicada por el ancla.
   *
   * Entiende dos formas, porque el sistema usa las dos: las pestanias
   * de siempre (#tab-algo) y las secciones plegables de la ficha del
   * paciente (#sec-algo), que desde octubre 2026 reemplazaron a varias
   * pestanias. Si no reconociera la segunda, guardar un telefono
   * recargaria la pagina con la seccion cerrada y el usuario tendria
   * que volver a abrirla para ver si su cambio quedo.
   */
  function activarPestania(destino) {
    if (!destino || destino.length < 2) return false;

    const btn = document.querySelector(
      `[data-bs-toggle="tab"][data-bs-target="${destino}"]`);
    if (btn && !btn.closest('.modal')) {
      bootstrap.Tab.getOrCreateInstance(btn).show();
      return true;
    }

    const seccion = document.querySelector(`details${destino}`);
    if (seccion) {
      seccion.open = true;
      seccion.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return true;
    }

    return false;
  }

  /**
   * Recarga la pagina cayendo en la pestania indicada.
   * Se usa despues de guardar, para que el usuario vuelva a ver
   * exactamente lo que acaba de tocar, ya actualizado.
   */
  function recargarEnPestania(destino, retraso = 700) {
    if (destino) {
      history.replaceState(null, '', destino);
    }
    setTimeout(() => window.location.reload(), retraso);
  }

  /* ---------- Cortinilla de carga ---------- */

  /**
   * Quita la cortinilla que tapa el armado de la vista.
   *
   * Se deja un momento a proposito: sin ella se alcanza a ver como las
   * tablas y los combos se acomodan, y la pantalla parece brincar. Con
   * la cortinilla el cambio de vista se siente de una pieza.
   *
   * Si algo tarda de mas, un respaldo la retira a los 5 segundos: es
   * preferible una pantalla incompleta a una cortinilla eterna.
   */
  function quitarCortinilla(espera = 300) {
    const cortinilla = document.getElementById('cortinilla');
    if (!cortinilla) return;

    const retirar = () => {
      cortinilla.classList.add('oculta');
      // Se saca del DOM al terminar la transicion, no antes: si no,
      // desaparece de golpe en vez de desvanecerse.
      setTimeout(() => cortinilla.remove(), 300);
    };

    setTimeout(retirar, espera);
    setTimeout(retirar, 5000);
  }

  /* ---------- Autocompletado del navegador ---------- */

  /**
   * Apaga el autocompletado en todos los formularios.
   *
   * El navegador ofrece direcciones y nombres de quien captura, no del
   * paciente, y en recepcion eso termina en expedientes con los datos
   * de la persona equivocada. Chrome ignora autocomplete="off" en
   * campos comunes, pero respeta un valor que no reconoce, de ahi
   * "nope": no es un truco sucio, es la forma que funciona.
   */
  function desactivarAutocompletar() {
    document.querySelectorAll('form').forEach((form) => {
      form.setAttribute('autocomplete', 'off');
    });

    const aplicar = (raiz) => {
      raiz.querySelectorAll(
        'input:not([type="checkbox"]):not([type="radio"]):not([type="submit"])'
      ).forEach((el) => {
        if (el.type === 'password') {
          el.setAttribute('autocomplete', 'new-password');
        } else {
          el.setAttribute('autocomplete', 'nope');
        }
        el.setAttribute('spellcheck', 'false');
      });
    };

    aplicar(document);

    // Lo que se agrega despues (filas nuevas, contenido de un modal)
    // tambien queda cubierto.
    new MutationObserver((cambios) => {
      cambios.forEach((c) => {
        c.addedNodes.forEach((nodo) => {
          if (nodo.nodeType === 1) aplicar(nodo);
        });
      });
    }).observe(document.body, { childList: true, subtree: true });
  }

  return {
    api, alerta, esc, debounce, formADatos, marcarErrores, money, ir,
    URL_BASE, aMayusculas, activarMayusculas, datosEnMayusculas,
    recordarPestania, activarPestania, recargarEnPestania,
    quitarCortinilla, desactivarAutocompletar,
    tamanoLegible, fecha, fechaHora, abrirVentana, verImagen,
  };
})();

document.addEventListener('DOMContentLoaded', () => {
  App.activarMayusculas();
  App.recordarPestania();
  App.desactivarAutocompletar();
  App.quitarCortinilla();
});

/* Los modales se escriben dentro de <main>, pero el desenfoque del fondo
   se aplica justamente a <main>: el modal se difuminaria a si mismo.
   Se mueven a <body> al cargar la pagina, antes de que Bootstrap los
   toque. De paso se evita que un modal quede atrapado bajo otro por el
   orden de apilamiento. */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('main .modal').forEach((modal) => {
    document.body.appendChild(modal);
  });
});

/* ---------------------------------------------------------------------
   Modales apilados

   Bootstrap le da el mismo z-index a todos los modales. Cuando uno abre
   otro (el selector de ocupacion sobre el modal del tutor), el segundo
   queda debajo del velo del primero: se ve, pero no se puede tocar.
   Aqui se le sube el z-index al que llega, y a su velo tambien.
   --------------------------------------------------------------------- */
document.addEventListener('show.bs.modal', (ev) => {
  const abiertos = document.querySelectorAll('.modal.show').length;
  if (abiertos === 0) return;

  const salto = abiertos * 20;
  ev.target.style.zIndex = 1055 + salto;
  ev.target.classList.add('modal-encimado');

  // El velo se crea despues de este evento, de ahi el setTimeout.
  setTimeout(() => {
    const velos = document.querySelectorAll('.modal-backdrop');
    const ultimo = velos[velos.length - 1];
    if (ultimo) ultimo.style.zIndex = 1050 + salto;
  }, 0);
});

/* Al cerrar el de encima, Bootstrap quita la clase que permite el scroll
   aunque siga abierto el de abajo. Se repone. */
document.addEventListener('hidden.bs.modal', (ev) => {
  ev.target.style.zIndex = '';
  ev.target.classList.remove('modal-encimado');

  if (document.querySelectorAll('.modal.show').length > 0) {
    document.body.classList.add('modal-open');
  }
});

/* Boton "Nuevo paciente" disponible desde el nav, el menu lateral
   y el tablero: un solo manejador para todos. */
document.addEventListener('click', (ev) => {
  const disparador = ev.target.closest('[data-accion="nuevo-paciente"]');
  if (!disparador) return;
  ev.preventDefault();

  const modal = document.getElementById('modalBuscarAlta');
  if (modal) {
    bootstrap.Modal.getOrCreateInstance(modal).show();
  } else {
    // Estamos en una vista que no tiene el modal cargado: vamos a la busqueda.
    App.ir('index.php?modulo=paciente&vista=buscar_paciente&alta=1');
  }
});
