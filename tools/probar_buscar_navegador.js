/**
 * El buscador de pacientes, corrido en un navegador de mentira.
 *
 * Por que existe
 * --------------
 * probar_orden_pacientes.py prueba la regla del orden con pacientes de
 * verdad, pero del lado del servidor. Lo que no puede probar es la
 * mitad que le toca a la pantalla, que es la que se rompe en silencio:
 *
 *   - el combo de "ordenar por" y el boton del sentido tienen que
 *     MANDARSE al servidor. Un parametro que no viaja deja la pantalla
 *     funcionando y la lista siempre igual, y eso se ve como "no sirve
 *     el filtro".
 *   - cambiar de criterio reinicia el sentido: las fechas de lo mas
 *     nuevo a lo mas viejo, los nombres de la A a la Z. La flecha del
 *     boton tiene que coincidir con lo que se manda, o dice una cosa y
 *     la lista viene de la otra.
 *   - las dos columnas nuevas tienen que pintarse, y las fechas vacias
 *     no pueden salir como "undefined".
 *
 * Asi que se levanta la pantalla DE VERDAD —buscar_paciente.php sin su
 * PHP— con el paciente.js de verdad, y se pican las cosas.
 *
 * Como correrlo
 * -------------
 *     npm install jsdom          (una vez)
 *     node tools/probar_buscar_navegador.js
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

function sinPhp(relativo) {
  return fs.readFileSync(path.join(RAIZ, relativo), 'utf8')
    .replace(/<\?php[\s\S]*?\?>/g, '')
    .replace(/<\?=[\s\S]*?\?>/g, '');
}

/* Los pacientes que contestaria el servidor.

   Los numeros van como cadena porque es lo que devuelve PDO, y la
   ultima consulta en null porque ese paciente nunca ha venido: es el
   caso que dejaba "undefined" en la celda. */
const PACIENTES = [
  {
    id_registro_paciente: '13', num_expediente: 'EXP-0013', id_persona: '21',
    nombre_completo: 'MATEO GUZMAN LOPEZ', edad: '7',
    telefono: '3111234567', correo: 'mama@ejemplo.com', estatus: '1',
    fecha_alta: '2019-03-04', ultima_consulta: '2026-10-02 10:30:00',
    foto: null, domicilio_calle: 'AV. INSURGENTES 120',
    domicilio_resto: 'CENTRO, C.P. 63000, TEPIC, NAYARIT',
  },
  {
    id_registro_paciente: '14', num_expediente: 'EXP-0014', id_persona: '22',
    nombre_completo: 'SOFIA ALVAREZ DIAZ', edad: '5',
    telefono: null, correo: null, estatus: '1',
    fecha_alta: '2026-09-28', ultima_consulta: null,
    foto: null, domicilio_calle: null, domicilio_resto: null,
  },
];

function montar() {
  const dom = new JSDOM(
    `<!DOCTYPE html><body>${sinPhp('vistas/paciente/buscar_paciente.php')}`
    + '</body>',
    { runScripts: 'outside-only' });

  const { window } = dom;
  const doc = window.document;

  const cajas = new Map();
  window.bootstrap = {
    Modal: {
      getOrCreateInstance(el) {
        if (!el) throw new Error('getOrCreateInstance con null');
        if (!cajas.has(el)) cajas.set(el, { show() {}, hide() {} });
        return cajas.get(el);
      },
    },
  };
  window.Swal = { fire: async () => ({ isDismissed: true }) };

  window.eval(fs.readFileSync(path.join(RAIZ, 'assets/js/app.js'), 'utf8')
            + '\n;window.App = App;');
  window.App.alerta = {
    error: () => {}, info: () => {}, toast: () => {},
    confirmar: async () => true, cargando: () => {}, cerrar: () => {},
  };

  // Lo que se pidio al servidor, en orden: es lo que esta prueba viene
  // a mirar.
  window.__pedidos = [];
  window.App.api = (ruta, datos) => {
    if (ruta.includes('paciente/listar')) {
      window.__pedidos.push(datos);
      return Promise.resolve({ datos: PACIENTES });
    }
    return Promise.resolve({ datos: [] });
  };

  window.__rotos = [];
  window.addEventListener('error', (ev) => {
    window.__rotos.push(ev.error ? ev.error.message : String(ev.message));
  });

  window.eval(fs.readFileSync(path.join(RAIZ, 'assets/js/paciente.js'), 'utf8'));

  return {
    window, doc,
    id: (x) => doc.getElementById(x),
    /*
       El arranque lo dispara jsdom, no esta prueba.

       Despachar el DOMContentLoaded a mano arrancaba el modulo DOS
       veces: jsdom tambien lo dispara por su cuenta un instante despues
       de construir el documento, y los escuchas ya estaban puestos. Con
       todo registrado dos veces, un clic en el boton del sentido lo
       invertia y lo volvia a invertir —la pantalla se quedaba igual— y
       el fallo parecia del codigo de la pantalla, que estaba bien.

       Asi que aqui solo se espera. Un setTimeout de 0 corre despues de
       que jsdom termine, que es exactamente lo que hace el navegador de
       verdad: una vez.
    */
    arrancar: () => new Promise((r) => setTimeout(r, 0)),
    esperar: () => new Promise((r) => setTimeout(r, 0)),
    ultimo: () => window.__pedidos[window.__pedidos.length - 1],
    clic: (el) => el.dispatchEvent(
      new window.MouseEvent('click', { bubbles: true, cancelable: true })),
    cambiar: (el) => el.dispatchEvent(
      new window.Event('change', { bubbles: true })),
    flecha: () => doc.getElementById('filtroSentido')
      .querySelector('i').className,
  };
}

async function pruebas() {
  const t = montar();
  await t.arrancar();

  // Un solo arranque: si el modulo se enganchara dos veces, cada clic
  // haria su efecto dos veces y se anularia.
  comprobar('la lista se pidio una sola vez al abrir',
    t.window.__pedidos.length, 1);

  comprobar('el arranque no revienta', t.window.__rotos, []);

  /* -------------------------------------------------------------------
     1. Lo que se manda de entrada
     ------------------------------------------------------------------- */
  comprobar('de entrada ordena por ultima consulta',
    t.ultimo().orden, 'ultima_consulta');
  comprobar('y de lo mas reciente a lo mas viejo', t.ultimo().baja, '1');
  comprobar('la flecha apunta hacia abajo', t.flecha(), 'bi bi-sort-down');

  /* -------------------------------------------------------------------
     2. Las dos columnas nuevas
     ------------------------------------------------------------------- */
  const filas = [...t.id('tablaPacientes').querySelectorAll('tr')];
  comprobar('se pintaron los dos pacientes', filas.length, 2);

  const celdas = [...filas[0].querySelectorAll('td')];
  comprobar('el renglon tiene siete celdas', celdas.length, 7);

  const fechas = [...filas[0].querySelectorAll('.celda-fecha')];
  comprobar('la fecha de alta sale en dia/mes/anio',
    fechas[0].textContent.trim(), '04/03/2019');
  comprobar('y la ultima consulta tambien',
    fechas[1].textContent.trim(), '02/10/2026');

  /* Quien nunca ha venido: la celda no puede decir "undefined".

     Es el caso que se ve en cuanto alguien abre la pantalla —siempre
     hay pacientes recien dados de alta sin consulta— y el que un
     `${p.ultima_consulta}` suelto convierte en la palabra undefined
     escrita en la tabla. */
  const vacias = [...filas[1].querySelectorAll('.celda-fecha')];
  comprobar('sin consultas, la celda lleva un guion',
    vacias[1].textContent.trim(), '-');
  comprobar('y no dice "undefined"',
    /undefined|null/i.test(filas[1].textContent), false);

  /* -------------------------------------------------------------------
     3. Cambiar de criterio reinicia el sentido
     ------------------------------------------------------------------- */
  t.id('filtroOrden').value = 'apellidos';
  t.cambiar(t.id('filtroOrden'));
  await t.esperar();

  comprobar('al pedir apellidos se manda apellidos',
    t.ultimo().orden, 'apellidos');
  comprobar('y arranca de la A a la Z', t.ultimo().baja, '0');
  comprobar('la flecha se da la vuelta', t.flecha(), 'bi bi-sort-up');

  t.id('filtroOrden').value = 'alta';
  t.cambiar(t.id('filtroOrden'));
  await t.esperar();

  comprobar('al volver a una fecha, otra vez de lo mas nuevo',
    t.ultimo().baja, '1');
  comprobar('y la flecha otra vez hacia abajo',
    t.flecha(), 'bi bi-sort-down');

  /* -------------------------------------------------------------------
     4. El boton del sentido
     ------------------------------------------------------------------- */
  t.clic(t.id('filtroSentido'));
  await t.esperar();

  comprobar('picar el boton invierte el sentido', t.ultimo().baja, '0');
  comprobar('sin cambiar el criterio', t.ultimo().orden, 'alta');
  comprobar('y la flecha lo dice', t.flecha(), 'bi bi-sort-up');

  t.clic(t.id('filtroSentido'));
  await t.esperar();
  comprobar('y se puede volver', t.ultimo().baja, '1');

  /* -------------------------------------------------------------------
     5. El filtro de texto no pierde el orden
     ------------------------------------------------------------------- */
  // Lo que se escribe en el buscador no deberia tirar el criterio
  // elegido: quien ordeno por alta y luego escribe un apellido quiere
  // los resultados en ese mismo orden.
  t.id('filtroBusqueda').value = 'GUZMAN';
  t.id('filtroBusqueda').dispatchEvent(
    new t.window.Event('input', { bubbles: true }));
  await new Promise((r) => setTimeout(r, 450));   // el retardo de 350 ms

  comprobar('al buscar texto se conserva el criterio',
    t.ultimo().orden, 'alta');
  comprobar('y el sentido', t.ultimo().baja, '1');
  comprobar('y el texto viaja', t.ultimo().q, 'GUZMAN');

  comprobar('nada revento en el camino', t.window.__rotos, []);
}

pruebas().then(() => {
  console.log();
  if (fallos.length) {
    console.log(`  *** ${fallos.length} fallo(s):`);
    fallos.forEach((f) => console.log('  !!   ' + f));
    process.exit(1);
  }
  console.log('  Todo en orden: el buscador ordena por donde se le pide.');
}).catch((e) => {
  console.log('  !!   la prueba revento: ' + e.stack);
  process.exit(1);
});
