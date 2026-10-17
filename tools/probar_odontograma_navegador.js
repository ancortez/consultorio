/**
 * La pestania del odontograma, corrida en un navegador de mentira.
 *
 * Por que existe
 * --------------
 * probar_odontograma_estado.py prueba la dentadura y las reglas del
 * lado de PHP. Lo que no puede probar es el pegamento, que es la mitad
 * que se rompe en silencio:
 *
 *   - picar dientes los elige, y volver a picarlos los suelta. Es la
 *     unica forma de corregir sin empezar la seleccion de nuevo.
 *   - picar una condicion sin dientes elegidos avisa, no guarda. Sin
 *     esto se mandaria una lista vacia al servidor por cada clic
 *     distraido en la paleta.
 *   - lo que se manda es la lista de los dientes elegidos, no todos.
 *   - al guardar se repinta sin recargar: recargar perderia la pestania
 *     y el desplazamiento, y volver a encontrar el 46 despues de cada
 *     marca es el trabajo que esta pantalla viene a quitar.
 *   - quitar la marca pregunta antes; marcar no.
 *
 * Como correrlo
 * -------------
 *     npm install jsdom          (una vez)
 *     node tools/probar_odontograma_navegador.js
 *
 * Si jsdom no esta instalado se salta sin ruido.
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

/*
   La pestania se arma a mano y no leyendo el .php.

   El archivo de la vista es casi todo PHP: el dibujo lo genera
   Odontograma::arcada() y la paleta sale de un foreach del catalogo, asi
   que quitando el PHP no queda ni un diente. Lo que se levanta aqui es
   el HTML que ESE PHP produce —los mismos ids, las mismas clases y los
   mismos atributos—, que es lo que mira el JavaScript.

   El riesgo de armarlo a mano es que se separe de la vista. Contra eso,
   probar_odontograma_estado.py comprueba los ids y las clases sobre el
   archivo de verdad; aqui se prueba el comportamiento.
*/
function diente(n, estado) {
  const extra = estado
    ? ` con-estado" style="--estado:${estado.color}"`
      + ` data-condicion="${estado.id_condicion}`
    : '';
  const titulo = estado ? `Diente ${n} · ${estado.nombre}` : `Diente ${n}`;

  return `<button type="button" class="diente permanente molar${extra}"
            data-diente="${n}" title="${titulo}">
            <svg class="dibujo-diente"></svg><span>${n}</span>
          </button>`;
}

const CONDICIONES = [
  { id_condicion: 1, nombre_condicion: 'Sano',     color: '#BDC3C7' },
  { id_condicion: 4, nombre_condicion: 'Ausente',  color: '#34495E' },
  { id_condicion: 7, nombre_condicion: 'Amalgama', color: '#5D6D7E' },
];

function montar({ estado = {} } = {}) {
  const dientes = [16, 17, 26, 46]
    .map((n) => diente(n, estado[n] ? {
      color: estado[n].color,
      id_condicion: estado[n].id_condicion,
      nombre: estado[n].nombre_condicion,
    } : null))
    .join('');

  const paleta = CONDICIONES.map((c) => `
    <button type="button" class="chip-condicion elegible"
            data-condicion="${c.id_condicion}">
      <i style="background:${c.color}"></i>${c.nombre_condicion}
    </button>`).join('')
    + `<button type="button" class="chip-condicion elegible quitar"
               data-condicion="0"><i class="vacio"></i>Quitar la marca</button>`;

  const html = `
    <div id="odoPaciente" data-expediente="13" data-puede="1">
      <span id="odoFecha">Sin capturar</span>
      <div class="arcada">${dientes}</div>
      <div class="paleta-odontograma" id="odoPaleta">
        <div class="po-aviso" id="odoAviso"></div>
        <div class="po-botones">${paleta}</div>
      </div>
      <div class="leyenda-odontograma" id="odoLeyenda"></div>
    </div>`;

  const dom = new JSDOM(`<!DOCTYPE html><body>${html}</body>`,
    { runScripts: 'outside-only' });

  const { window } = dom;
  const doc = window.document;

  /* El arranque se dispara UNA vez: jsdom tambien lanza su
     DOMContentLoaded un instante despues de construir el documento, y
     despachandolo a mano los escuchas quedan duplicados —cada clic
     haria su efecto dos veces y se anularia—. */
  const alArrancar = [];
  const registrarDeVerdad = doc.addEventListener.bind(doc);
  doc.addEventListener = (tipo, fn, opciones) => {
    if (tipo === 'DOMContentLoaded') { alArrancar.push(fn); return undefined; }
    return registrarDeVerdad(tipo, fn, opciones);
  };

  window.eval(fs.readFileSync(path.join(RAIZ, 'assets/js/app.js'), 'utf8')
            + '\n;window.App = App;');

  window.__avisos = [];
  window.__confirmado = true;
  window.App.alerta = {
    error: (...a) => { window.__avisos.push(['error', a.join(' ')]); },
    info:  (...a) => { window.__avisos.push(['info', a.join(' ')]); },
    toast: (...a) => { window.__avisos.push(['toast', a.join(' ')]); },
    confirmar: async (...a) => {
      window.__avisos.push(['confirmar', a.join(' ')]);
      return window.__confirmado;
    },
    cargando: () => {}, cerrar: () => {},
  };

  window.__guardados = [];
  window.__respuesta = { estado: {}, condiciones: CONDICIONES,
                         fecha: '2026-10-17' };
  window.App.api = (ruta, datos) => {
    window.__guardados.push({ ruta, datos });
    return Promise.resolve({
      datos: window.__respuesta,
      mensaje: 'Se marcaron los dientes.',
    });
  };

  window.__rotos = [];
  window.addEventListener('error', (ev) => {
    window.__rotos.push(ev.error ? ev.error.message : String(ev.message));
  });

  window.eval(fs.readFileSync(
    path.join(RAIZ, 'assets/js/odontograma.js'), 'utf8'));

  return {
    window, doc,
    id: (x) => doc.getElementById(x),
    arrancar: () => alArrancar.forEach((fn) => fn()),
    esperar: () => new Promise((r) => setTimeout(r, 0)),
    picarDiente: (n) => doc.querySelector(`.diente[data-diente="${n}"]`)
      .dispatchEvent(new window.MouseEvent('click',
        { bubbles: true, cancelable: true })),
    picarCondicion: (id) => doc
      .querySelector(`#odoPaleta [data-condicion="${id}"]`)
      .dispatchEvent(new window.MouseEvent('click',
        { bubbles: true, cancelable: true })),
    elegidos: () => [...doc.querySelectorAll('.diente.elegido')]
      .map((b) => Number(b.dataset.diente)),
    ultimo: () => window.__guardados[window.__guardados.length - 1],
  };
}

async function pruebas() {

  /* -------------------------------------------------------------------
     1. Elegir y soltar dientes
     ------------------------------------------------------------------- */
  {
    const t = montar();
    t.arrancar();

    comprobar('el arranque no revienta', t.window.__rotos, []);
    comprobar('al abrir no hay nada elegido', t.elegidos(), []);
    comprobar('y el aviso pide picar dientes',
      /Pica los dientes/.test(t.id('odoAviso').textContent), true);

    t.picarDiente(16);
    t.picarDiente(17);
    comprobar('se eligen varios dientes', t.elegidos(), [16, 17]);
    comprobar('el aviso cuenta cuantos van',
      /2 diente\(s\) elegido\(s\)/.test(t.id('odoAviso').textContent), true);
    comprobar('y se pone en modo "listo"',
      t.id('odoAviso').classList.contains('listo'), true);

    // Volver a picarlo lo suelta: es la unica forma de corregir sin
    // empezar la seleccion de nuevo.
    t.picarDiente(16);
    comprobar('volver a picar un diente lo suelta', t.elegidos(), [17]);
  }

  /* -------------------------------------------------------------------
     2. Marcar una condicion
     ------------------------------------------------------------------- */
  {
    const t = montar();
    t.arrancar();

    // Sin dientes elegidos no se manda nada.
    t.picarCondicion(7);
    await t.esperar();

    comprobar('picar una condicion sin dientes no guarda',
      t.window.__guardados.length, 0);
    comprobar('y avisa de que primero hay que elegir',
      t.window.__avisos.some(([tipo, texto]) =>
        tipo === 'info' && /elige los dientes/i.test(texto)), true);

    t.picarDiente(16);
    t.picarDiente(26);

    t.window.__respuesta = {
      estado: {
        16: { id_condicion: 7, nombre_condicion: 'Amalgama', color: '#5D6D7E' },
        26: { id_condicion: 7, nombre_condicion: 'Amalgama', color: '#5D6D7E' },
      },
      condiciones: CONDICIONES,
      fecha: '2026-10-17',
    };

    t.picarCondicion(7);
    await t.esperar();
    await t.esperar();

    comprobar('se guardo una vez', t.window.__guardados.length, 1);
    comprobar('al endpoint que toca',
      t.ultimo().ruta, 'paciente/odontograma_guardar.php');
    comprobar('con el paciente',
      t.ultimo().datos.id_registro_paciente, '13');
    comprobar('la condicion elegida', t.ultimo().datos.id_condicion, 7);

    /* Solo los dientes ELEGIDOS, no los cuatro del dibujo. Mandar todos
       pintaria de amalgama una boca entera por un clic. */
    comprobar('y solo los dientes elegidos', t.ultimo().datos.dientes, '16,26');

    // Se repinta sin recargar.
    const marcado = t.doc.querySelector('.diente[data-diente="16"]');
    comprobar('el diente queda con su estado',
      marcado.classList.contains('con-estado'), true);
    comprobar('con su color',
      marcado.style.getPropertyValue('--estado').trim(), '#5D6D7E');
    comprobar('y el globo de ayuda lo dice',
      /Amalgama/.test(marcado.title), true);

    /* La seleccion se suelta: lo que se eligio ya se marco, y dejarla
       puesta haria que la siguiente condicion cayera encima de los
       mismos dientes sin que nadie lo pida. */
    comprobar('la seleccion se suelta al guardar', t.elegidos(), []);

    comprobar('la leyenda ensenia la condicion usada',
      /Amalgama/.test(t.id('odoLeyenda').textContent), true);
    // Y solo esa: con las trece del catalogo la leyenda ocuparia mas
    // que la dentadura y la mayoria no estaria en el dibujo.
    comprobar('y no las que no estan en el dibujo',
      /Ausente/.test(t.id('odoLeyenda').textContent), false);

    comprobar('la fecha se pone al dia',
      /17\/10\/2026/.test(t.id('odoFecha').textContent), true);
    comprobar('nada revento', t.window.__rotos, []);
  }

  /* -------------------------------------------------------------------
     3. Quitar la marca se pregunta
     ------------------------------------------------------------------- */
  {
    const t = montar({
      estado: {
        16: { id_condicion: 7, nombre_condicion: 'Amalgama', color: '#5D6D7E' },
      },
    });
    t.arrancar();

    // La leyenda sale al abrir, leyendo el dibujo: pedir los datos otra
    // vez seria un viaje para enterarnos de lo que ya tenemos enfrente.
    comprobar('la leyenda sale al abrir, sin pedir nada',
      /Amalgama/.test(t.id('odoLeyenda').textContent), true);
    comprobar('y sin viajes al servidor', t.window.__guardados.length, 0);

    t.picarDiente(16);

    // Se contesta que no: no se guarda nada.
    t.window.__confirmado = false;
    t.picarCondicion(0);
    await t.esperar();

    comprobar('quitar la marca pregunta antes',
      t.window.__avisos.some(([tipo]) => tipo === 'confirmar'), true);
    comprobar('y diciendo que no, no se guarda',
      t.window.__guardados.length, 0);
    comprobar('los dientes siguen elegidos', t.elegidos(), [16]);

    // Ahora se acepta.
    t.window.__confirmado = true;
    t.window.__respuesta = { estado: {}, condiciones: CONDICIONES,
                             fecha: '2026-10-17' };
    t.picarCondicion(0);
    await t.esperar();
    await t.esperar();

    comprobar('aceptando, se guarda', t.window.__guardados.length, 1);
    comprobar('con la condicion 0, que significa quitar',
      t.ultimo().datos.id_condicion, 0);

    const limpio = t.doc.querySelector('.diente[data-diente="16"]');
    comprobar('el diente se queda sin estado',
      limpio.classList.contains('con-estado'), false);
    comprobar('sin color', limpio.style.getPropertyValue('--estado'), '');
    comprobar('sin condicion', limpio.dataset.condicion, undefined);
    comprobar('y el globo de ayuda vuelve a lo basico',
      limpio.title, 'Diente 16');
    comprobar('la leyenda lo dice',
      /Todavía no se ha marcado/.test(t.id('odoLeyenda').textContent), true);
  }

  /* -------------------------------------------------------------------
     4. Marcar NO pregunta
     ------------------------------------------------------------------- */
  {
    const t = montar();
    t.arrancar();
    t.picarDiente(46);
    t.picarCondicion(1);
    await t.esperar();
    await t.esperar();

    /* Marcar se corrige volviendo a marcar, asi que preguntar seria
       estorbar en la operacion que se hace veinte veces al dia. */
    comprobar('marcar no pregunta nada',
      t.window.__avisos.some(([tipo]) => tipo === 'confirmar'), false);
    comprobar('y guarda directo', t.window.__guardados.length, 1);
  }

  /* -------------------------------------------------------------------
     5. Sin permiso de editar, solo se mira
     ------------------------------------------------------------------- */
  {
    const t = montar();
    t.id('odoPaciente').dataset.puede = '0';
    t.arrancar();

    t.picarDiente(16);
    comprobar('sin permiso no se puede elegir dientes', t.elegidos(), []);
  }
}

pruebas().then(() => {
  console.log();
  if (fallos.length) {
    console.log(`  *** ${fallos.length} fallo(s):`);
    fallos.forEach((f) => console.log('  !!   ' + f));
    process.exit(1);
  }
  console.log('  Todo en orden: el odontograma se marca de varios en varios.');
}).catch((e) => {
  console.log('  !!   la prueba revento: ' + e.stack);
  process.exit(1);
});
