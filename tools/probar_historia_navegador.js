/**
 * La historia clinica, corrida en un navegador de mentira.
 *
 * Por que existe
 * --------------
 * Los demas arneses portan el algoritmo a Python y lo corren aparte.
 * Eso sirve para una cuenta —un saldo, una regla de edad— pero no para
 * esto: aqui el error no estaba en la logica sino en el pegamento.
 * Tres entregas seguidas fallaron en esta pantalla:
 *
 *   1. la ventana se abria sola doce veces (colgaba del evento 'change')
 *   2. no se podia guardar (mismo origen)
 *   3. no se podia MODIFICAR una respuesta ya contestada
 *
 * La tercera es la que obligo a escribir esto. La causa era que yo
 * llamaba a App.aMayusculas(texto) cuando esa funcion recibe un
 * ELEMENTO: mira su .classList para saber si esta exento, y con una
 * cadena revienta. El manejador del boton moria ahi, en silencio.
 *
 * Y mi arnes anterior no lo vio porque yo mismo habia escrito un
 * App.aMayusculas de mentira que SI aceptaba cadenas. Un doble mas
 * capaz que el original no prueba el original: prueba el doble. Por eso
 * aqui se carga el app.js de verdad.
 *
 * Como correrlo
 * -------------
 *     npm install jsdom          (una vez)
 *     node tools/probar_historia_navegador.js
 *
 * Si jsdom no esta instalado se salta sin ruido: no vale la pena
 * detener todo el barrido por una dependencia que solo sirve aqui.
 */
'use strict';

const fs = require('fs');
const path = require('path');

let JSDOM;
try {
  ({ JSDOM } = require('jsdom'));
} catch (e) {
  console.log('  ~    jsdom no esta instalado: esta prueba se salta.');
  console.log('       Para habilitarla:  npm install jsdom');
  process.exit(0);
}

const RAIZ = process.env.RAIZ || path.join(__dirname, '..');
const fallos = [];

function comprobar(nombre, obtenido, esperado) {
  const a = JSON.stringify(obtenido);
  const b = JSON.stringify(esperado);
  if (a !== b) {
    fallos.push(`${nombre}\n      esperaba: ${b}\n      obtuvo:   ${a}`);
  } else {
    console.log('  ok   ' + nombre);
  }
}

/* =====================================================================
   El teatro: una pagina con lo justo, pero con el JavaScript de verdad
   ===================================================================== */
function montar() {
  // El modal sale del archivo real: si alguien le cambia un id, esta
  // prueba se entera.
  let parcial = fs.readFileSync(
    path.join(RAIZ, 'vistas/paciente/_tab_historia.php'), 'utf8');
  parcial = parcial.replace(/<\?php[\s\S]*?\?>/g, '').replace(/<\?=[\s\S]*?\?>/g, '');

  const i = parcial.indexOf('<div class="modal fade" id="modalPreguntaExtra"');
  if (i < 0) {
    fallos.push('no se encontro el modal de la pregunta extra en la vista');
    return null;
  }

  /*
     Una pregunta de cada forma, que son las tres que existen:
       - alergia            aparece con "si"
       - primera_visita     aparece con "no"
       - momentos_azucar    aparece con todas menos una
  */
  const dom = new JSDOM(`<!DOCTYPE html><body>
    <form id="formHistoria">
      <section class="paso">
        <div class="col-6">
          <label class="form-label">Alergico</label>
          <div class="grupo-sino" data-campo="alergia">
            <button type="button" class="opcion-sino" data-valor="1">Si</button>
            <button type="button" class="opcion-sino" data-valor="0">No</button>
            <input type="hidden" name="alergia">
          </div>
        </div>
        <div class="col-12" data-depende="alergia" data-cuando="1">
          <input type="text" name="alergia_detalle" maxlength="255"
                 placeholder="Especifique">
        </div>

        <div class="col-6">
          <label class="form-label">Primera visita</label>
          <div class="grupo-sino" data-campo="primera_visita_dentista">
            <button type="button" class="opcion-sino" data-valor="1">Si</button>
            <button type="button" class="opcion-sino" data-valor="0">No</button>
            <input type="hidden" name="primera_visita_dentista">
          </div>
        </div>
        <div class="col-12" data-depende="primera_visita_dentista" data-cuando="0">
          <label class="form-label">¿Cuando fue la ultima vez?</label>
          <input type="text" name="ultima_visita_dentista" maxlength="120">
        </div>
      </section>
    </form>
    ${parcial.substring(i)}
    <div id="pasosHistoria"></div>
    <button id="hcImprimir"></button>
    <button id="hcPrellenar"></button>
    <button id="hcPrellenarAviso"></button>
    <div id="hcAvisoPendiente"></div>
    <div id="hcCaptura"></div>
  </body>`, { runScripts: 'outside-only' });

  const { window } = dom;
  window.__errores = [];

  // Bootstrap de mentira: lo justo para abrir, cerrar y avisar.
  const cajas = new Map();
  window.bootstrap = {
    Modal: {
      getOrCreateInstance(el) {
        if (!cajas.has(el)) {
          cajas.set(el, {
            _visible: false,
            show() {
              this._visible = true;
              el.dispatchEvent(new window.Event('shown.bs.modal'));
            },
            hide() {
              this._visible = false;
              el.dispatchEvent(new window.Event('hidden.bs.modal'));
            },
          });
        }
        return cajas.get(el);
      },
    },
  };
  window.Swal = { fire: async () => ({ isDismissed: true }) };
  window.Pasos = { crear: () => ({ aplicarModo: () => {} }) };

  // El app.js DE VERDAD. Ver el comentario de arriba sobre por que no
  // un doble.
  /*
     El arranque se dispara UNA vez.

     Los modulos se enganchan en el DOMContentLoaded, y jsdom dispara
     ese evento por su cuenta un instante despues de construir el
     documento —cuando los escuchas ya estan puestos—. Despachandolo
     tambien a mano, cada modulo se engancharia DOS veces: los escuchas
     quedarian duplicados y cualquier cosa que alterne se invertiria y
     se volveria a invertir, dejando la pantalla igual. El fallo
     parecería del codigo que se esta probando, que estaria bien.

     Asi que los escuchas se interceptan: se guardan y se corren una
     vez. El evento que dispara jsdom despues no encuentra a nadie.
  */
  const alArrancar = [];
  const registrarDeVerdad = window.document.addEventListener
    .bind(window.document);
  window.document.addEventListener = (tipo, fn, opciones) => {
    if (tipo === 'DOMContentLoaded') { alArrancar.push(fn); return undefined; }
    return registrarDeVerdad(tipo, fn, opciones);
  };

  window.eval(fs.readFileSync(path.join(RAIZ, 'assets/js/app.js'), 'utf8')
            + '\n;window.App = App;');

  // Lo unico que se reemplaza son los avisos, para poder leerlos.
  window.App.alerta = {
    error: (...a) => { window.__errores.push(a.join(' ')); },
    info: () => {}, toast: () => {}, confirmar: async () => true,
    cargando: () => {}, cerrar: () => {},
  };

  window.eval(fs.readFileSync(path.join(RAIZ, 'assets/js/historia.js'), 'utf8')
            + '\n;window.Historia = Historia;');

  alArrancar.forEach((fn) => fn());

  // cargar() pide datos al servidor: se deja colgada.
  window.App.api = () => new Promise(() => {});
  window.Historia.iniciar(1);

  /*
     Los errores que revientan DENTRO de un manejador no detienen la
     pagina: el navegador los apunta en la consola y sigue como si nada.
     Eso es justo lo que hacia que el boton Guardar pareciera ignorar el
     clic. Aqui se recogen para poder decirlo con todas sus letras en
     vez de que la prueba falle "porque si".
  */
  window.__rotos = [];
  window.addEventListener('error', (ev) => {
    window.__rotos.push(ev.error ? ev.error.message : String(ev.message));
  });

  return {
    window,
    $: (s) => window.document.querySelector(s),
    // Picar algo que no existe es un fallo con nombre, no un golpe seco.
    clic(el, queEra) {
      if (!el) {
        fallos.push(`no se encontro ${queEra || 'el elemento'} para picarlo`);
        return false;
      }
      return el.dispatchEvent(
        new window.MouseEvent('click', { bubbles: true, cancelable: true }));
    },
    abierta: () => cajas.get(
      window.document.getElementById('modalPreguntaExtra'))?._visible === true,
  };
}

const t = montar();
if (t === null) {
  console.log('*** 1 PROBLEMA(S) ***');
  fallos.forEach((f) => console.log('  !!   ' + f));
  process.exit(1);
}

const { $, clic, abierta, window } = t;

/* =====================================================================
   1. Contestar que SI no abre ninguna ventana
   =====================================================================
   Abria la ventanita del complemento hasta el 17 de octubre de 2026. La
   doctora pidio que no: las doce preguntas de si/no se contestan de
   corrido, y una ventana que salta en medio obliga a leerla, cerrarla y
   volver a encontrar donde se iba. Mas todavia siendo el complemento
   opcional: interrumpia para pedir algo que se puede no contestar.

   Lo que sale es la capsula, que es el dato a la vista y el camino para
   llenarlo cuando se quiera.
   ===================================================================== */
clic($('[data-campo="alergia"] .opcion-sino[data-valor="1"]'), 'el boton Si de alergia');
comprobar('picar "Si" NO abre ninguna ventana', abierta(), false);
comprobar('la respuesta queda marcada', $('[name="alergia"]').value, '1');
comprobar('y sale la capsula diciendo NO ESPECIFICA',
  ($('.respuesta-extra')?.textContent ?? '').includes('NO ESPECIFICA'), true);
comprobar('marcada como no especificada',
  $('.respuesta-extra')?.classList.contains('sin-especificar'), true);
comprobar('el detalle sigue vacio en el formulario',
  $('[name="alergia_detalle"]')?.value, '');
comprobar('y no hubo ningun aviso de error', window.__errores, []);

/* =====================================================================
   2. La capsula es la que abre la ventana
   ===================================================================== */
clic($('.respuesta-extra'), 'la capsula de la respuesta');
comprobar('picar la capsula abre la ventana', abierta(), true);
comprobar('y llega vacia, que es lo que hay guardado',
  $('#pxValor').value, '');

$('#pxValor').value = 'penicilina';
clic($('#pxGuardar'), 'el boton Guardar');

comprobar('guardar cierra la ventana', abierta(), false);
comprobar('el dato queda escrito, en mayusculas',
  $('[name="alergia_detalle"]')?.value, 'PENICILINA');
comprobar('la capsula deja de estar sin especificar',
  $('.respuesta-extra')?.classList.contains('sin-especificar'), false);

/* =====================================================================
   2b. MODIFICAR una respuesta ya contestada
   ===================================================================== */
// Esta es la que fallaba una vez: la ventana salia y el boton no
// respondia.
clic($('.respuesta-extra'), 'la etiqueta de la respuesta');
comprobar('picar la etiqueta reabre la ventana', abierta(), true);
comprobar('y llega con el texto que ya habia', $('#pxValor').value, 'PENICILINA');

$('#pxValor').value = 'penicilina y sulfas';
clic($('#pxGuardar'), 'el boton Guardar');

comprobar('MODIFICAR: guardar cierra la ventana', abierta(), false);
comprobar('MODIFICAR: el dato queda actualizado',
  $('[name="alergia_detalle"]').value, 'PENICILINA Y SULFAS');
comprobar('MODIFICAR: la respuesta sigue siendo "si"',
  $('[name="alergia"]').value, '1');
comprobar('MODIFICAR: la etiqueta muestra lo nuevo',
  ($('.respuesta-extra')?.textContent ?? '').includes('PENICILINA Y SULFAS'), true);

/* =====================================================================
   3. Guardar en blanco se permite: el detalle es opcional
   =====================================================================
   Hasta el 16 de octubre de 2026 esto se rechazaba con un aviso. Ahora
   se acepta, porque el tutor muchas veces no sabe el detalle y la
   alternativa era inventarlo o cambiar el "si" por un "no" —que borra
   un antecedente clinico para poder avanzar—.

   Lo que no puede pasar es que el dato quede invisible: la capsula se
   queda, diciendo NO ESPECIFICA, y se pica para escribirlo cuando se
   sepa.
   ===================================================================== */
window.__errores.length = 0;
clic($('.respuesta-extra'), 'la etiqueta de la respuesta');
$('#pxValor').value = '   ';
clic($('#pxGuardar'), 'el boton Guardar');

comprobar('guardar en blanco cierra la ventana', abierta(), false);
comprobar('y no regaznia', window.__errores.length, 0);
comprobar('el detalle queda vacio en el formulario',
  $('[name="alergia_detalle"]').value, '');
comprobar('la respuesta de arriba se conserva',
  $('[name="alergia"]').value, '1');
comprobar('la capsula sigue ahi, diciendo NO ESPECIFICA',
  ($('.respuesta-extra')?.textContent ?? '').includes('NO ESPECIFICA'), true);
comprobar('y se marca como no especificada',
  $('.respuesta-extra')?.classList.contains('sin-especificar'), true);

// Se vuelve a llenar para lo que sigue.
clic($('.respuesta-extra'), 'la capsula sin especificar');
comprobar('la capsula vacia tambien se puede picar', abierta(), true);
$('#pxValor').value = 'PENICILINA Y SULFAS';
clic($('#pxGuardar'), 'el boton Guardar');
comprobar('y al escribirlo deja de estar sin especificar',
  $('.respuesta-extra')?.classList.contains('sin-especificar'), false);

/* =====================================================================
   4. Cerrar sin contestar CONSERVA la respuesta
   =====================================================================
   Esto deshacia el "si" hasta octubre de 2026. Era coherente mientras
   el detalle era obligatorio; con el detalle opcional, deshacerlo es
   perder el antecedente por no saber una precision.
   ===================================================================== */
clic($('[data-campo="primera_visita_dentista"] .opcion-sino[data-valor="0"]'), 'el boton No de primera visita');
comprobar('"no es la primera visita" tampoco abre ventana', abierta(), false);

// Se abre a mano, que es lo que hace quien pica la capsula, para poder
// probar lo que pasa al cerrarla sin contestar.
clic($('[data-campo="primera_visita_dentista"]').parentElement
       .querySelector('.respuesta-extra'),
     'la capsula de primera visita');
comprobar('su capsula abre la ventana', abierta(), true);

clic($('#modalPreguntaExtra [data-bs-dismiss="modal"]'), 'el boton Cerrar');
// El Bootstrap de mentira no cierra solo con data-bs-dismiss: se le
// pide a mano, que es lo que hace el de verdad.
window.bootstrap.Modal.getOrCreateInstance(
  window.document.getElementById('modalPreguntaExtra')).hide();

comprobar('cerrar sin contestar conserva la respuesta',
  $('[name="primera_visita_dentista"]').value, '0');
comprobar('el detalle se queda vacio',
  $('[name="ultima_visita_dentista"]').value, '');

// Y la capsula aparece en la pregunta de primera visita, que es la que
// se acaba de contestar sin detalle.
const capsulas = [...window.document.querySelectorAll('.respuesta-extra')]
  .filter((c) => c.textContent.includes('NO ESPECIFICA'));
comprobar('y sale su capsula de NO ESPECIFICA', capsulas.length >= 1, true);

/* =====================================================================
   5. Cambiar "si" por "no" limpia el detalle
   ===================================================================== */
clic($('[data-campo="alergia"] .opcion-sino[data-valor="0"]'), 'el boton No de alergia');
comprobar('al pasar a "no" se borra el detalle',
  $('[name="alergia_detalle"]').value, '');
/* La capsula se busca DENTRO del bloque de alergia, no en toda la
   pantalla.

   Un selector global fallaba desde que las capsulas aparecen tambien
   vacias: la de "primera visita", que quedo sin detalle en el paso
   anterior, es la que encontraba primero y la comprobacion se quejaba
   de una capsula que es correcta y de otra pregunta. */
const bloqueAlergia = $('[data-campo="alergia"]')?.parentElement;
comprobar('y desaparece la etiqueta de alergia',
  !!bloqueAlergia?.querySelector('.respuesta-extra'), false);
comprobar('sin abrir ninguna ventana', abierta(), false);

// Un error dentro de un manejador no detiene nada, pero deja el boton
// muerto. Si hubo alguno, se dice: es la pista mas util de todas.
if (window.__rotos.length) {
  fallos.push('algo revento dentro de un manejador (en el navegador el '
            + 'boton se queda mudo): ' + window.__rotos.join(' | '));
}

console.log();
if (fallos.length) {
  console.log(`*** ${fallos.length} PROBLEMA(S) ***`);
  fallos.forEach((f) => console.log('  !!   ' + f));
  process.exit(1);
}
console.log('*** TODO EN ORDEN ***');
