/**
 * La agenda del dia, corrida en un navegador de mentira.
 *
 * Por que existe
 * --------------
 * Esta ronda agrega "apartar el dia sin ponerle hora". Del lado de PHP
 * eso ya lo prueba probar_cita_sin_hora.py con numeros. Lo que no se
 * puede probar ahi es la mitad que le toca a la pantalla, y es
 * justamente la mitad fragil:
 *
 *   - un interruptor nuevo que esconde la rejilla de horarios y el
 *     selector de duracion, y que tiene que habilitar el boton de
 *     guardar sin que nadie haya elegido hora;
 *   - el renglon de la agenda, que con una cita apartada tiene que
 *     escribir "Sin hora" y no las 00:00;
 *   - el menu del renglon, que no debe ofrecer "Confirmar" una cita que
 *     todavia no tiene hora que confirmarle al paciente.
 *
 * Esto es pegamento: ids que se buscan, clases que se ponen y se
 * quitan. Y el pegamento falla en silencio. Un getElementById que
 * devuelve null revienta DENTRO del arranque, el navegador lo apunta en
 * la consola y la pagina se dibuja entera con todos los botones
 * muertos. En este proyecto ya paso dos veces y las dos las descubrio
 * Angel, no yo.
 *
 * Asi que se levanta la pantalla DE VERDAD —agenda.php sin su PHP— con
 * el consultas.js de verdad, y se pican las cosas.
 *
 * Como correrlo
 * -------------
 *     npm install jsdom          (una vez)
 *     node tools/probar_agenda_navegador.js
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

/** Un archivo de vista, sin PHP: queda el HTML tal cual se sirve. */
function sinPhp(relativo) {
  return fs.readFileSync(path.join(RAIZ, relativo), 'utf8')
    .replace(/<\?php[\s\S]*?\?>/g, '')
    .replace(/<\?=[\s\S]*?\?>/g, '');
}

/*
   Las citas que contestaria el servidor.

   Los numeros van como CADENA a proposito: es lo que devuelve PDO, y es
   exactamente donde se rompio una vez el boton Modificar de la vista de
   semana. Un "sin_hora" que valga "1" y un codigo que compare con === 1
   contra el numero daria falso y la pantalla escribiria las 00:00.
*/
function citaApartada(extra = {}) {
  return Object.assign({
    id_cita: '41',
    fecha_inicio: '2026-11-27 00:00:00',
    fecha_fin: '2026-11-27 00:00:00',
    sin_hora: '1',
    id_estado_cita: '1',
    nombre_estado: 'Pendiente',
    color: '#c58b00',
    id_registro_paciente: '13',
    id_usuario_dentista: '3',
    id_sala: null,
    nombre_completo: 'MATEO GUZMAN LOPEZ',
    num_expediente: 'EXP-0013',
    telefono: '3111234567',
    edad: '7',
    dentista: 'ADRIANA LOPEZ RUELAS',
    nombre_sala: null,
    motivo: 'CONTINUACION ORTODONCIA',
    notas: null,
  }, extra);
}

function citaNormal(extra = {}) {
  return citaApartada(Object.assign({
    id_cita: '42',
    fecha_inicio: '2026-11-27 10:30:00',
    fecha_fin: '2026-11-27 11:30:00',
    sin_hora: '0',
    nombre_completo: 'SOFIA RAMIREZ DIAZ',
  }, extra));
}

/* =====================================================================
   El teatro
   ===================================================================== */
function montar({ citas = [] } = {}) {
  /* El modal del detalle entra desde el principio.

     agenda.php lo incluye con un require de PHP al final, asi que
     quitando el PHP se queda fuera. Y tiene que estar ANTES de arrancar:
     DetalleCita cuelga sus escuchas en el DOMContentLoaded y si el modal
     no existe todavia se va sin colgar ninguna —que es exactamente la
     falla silenciosa que este arnes viene a cazar—. */
  const dom = new JSDOM(
    `<!DOCTYPE html><body>${sinPhp('vistas/consultas/agenda.php')}`
    + `${sinPhp('vistas/consultas/_modal_detalle_cita.php')}</body>`,
    { runScripts: 'outside-only' });

  const { window } = dom;
  const doc = window.document;

  /*
     El arranque se dispara UNA vez.

     Los modulos se enganchan en el DOMContentLoaded, y jsdom dispara
     ese evento por su cuenta un instante despues de construir el
     documento —cuando los escuchas ya estan puestos—. Despachandolo
     tambien a mano, cada modulo se enganchaba DOS veces: los escuchas
     quedaban duplicados y cualquier cosa que alterne —un interruptor,
     el sentido de un orden— se invertia y se volvia a invertir, con lo
     que la pantalla se quedaba igual. El fallo parecia del codigo que
     se estaba probando, que estaba bien.

     Asi que los escuchas de DOMContentLoaded se interceptan: se guardan
     aqui en vez de registrarse, y arrancar() los corre. El evento que
     dispara jsdom despues no encuentra a nadie.
  */
  const alArrancar = [];
  const registrarDeVerdad = doc.addEventListener.bind(doc);
  doc.addEventListener = (tipo, fn, opciones) => {
    if (tipo === 'DOMContentLoaded') { alArrancar.push(fn); return undefined; }
    return registrarDeVerdad(tipo, fn, opciones);
  };

  const cajas = new Map();
  window.bootstrap = {
    Modal: {
      getOrCreateInstance(el) {
        if (!el) throw new Error('getOrCreateInstance con null');
        if (!cajas.has(el)) {
          cajas.set(el, {
            _visible: false,
            show() { this._visible = true; },
            hide() { this._visible = false; },
          });
        }
        return cajas.get(el);
      },
    },
  };
  window.Swal = { fire: async () => ({ isDismissed: true }) };

  window.eval(fs.readFileSync(path.join(RAIZ, 'assets/js/app.js'), 'utf8')
            + '\n;window.App = App;');
  window.eval(fs.readFileSync(path.join(RAIZ, 'assets/js/comun.js'), 'utf8')
            + '\n;window.DetalleCita = DetalleCita;');

  window.__avisos = [];
  window.App.alerta = {
    error: (...a) => { window.__avisos.push(['error', a.join(' ')]); },
    info: (...a) => { window.__avisos.push(['info', a.join(' ')]); },
    toast: (...a) => { window.__avisos.push(['toast', a.join(' ')]); },
    confirmar: async () => true,
    cargando: () => {}, cerrar: () => {},
  };

  /* La fecha del dia la pone PHP en el value del campo.

     Quitando el PHP el campo queda vacio, y entonces la pantalla se
     comporta como no se comporta nunca de verdad: cargarEspaciosModal
     se va por su rama de "elige dentista y fecha" y la rejilla de
     horarios nunca se dibuja. Ponerla aqui es parte de montar la
     pantalla, no un apanio de la prueba. */
  doc.getElementById('agFecha').value = '2026-11-27';

  // El dentista sale de un foreach de PHP: al quitar el PHP no queda
  // ninguno, asi que se pone uno a mano en los dos selectores.
  ['agDentista', 'citaDentista'].forEach((id) => {
    const o = doc.createElement('option');
    o.value = '3';
    o.textContent = 'ADRIANA LOPEZ RUELAS';
    /* Marcada como seleccionada por omision.

       En un navegador de verdad no hace falta: la primera opcion de un
       select de una sola linea queda elegida sola, y tambien despues de
       un form.reset(). jsdom se queda en selectedIndex -1, asi que el
       select devuelve cadena vacia, cargarEspaciosModal se va por su
       rama de "elige dentista y fecha" y la rejilla no se dibuja nunca.
       Esto es ponerle al doble el comportamiento que el navegador trae
       de fabrica, no tapar un fallo del codigo. */
    o.defaultSelected = true;
    doc.getElementById(id).appendChild(o);
  });

  window.__guardado = null;
  window.App.api = (ruta, datos) => {
    if (ruta.includes('citas_listar')) {
      return Promise.resolve({ datos: citas });
    }
    if (ruta.includes('espacios')) {
      return Promise.resolve({
        datos: {
          espacios: [
            { hora: '09:00', fin: '09:30', libre: true },
            { hora: '09:30', fin: '10:00', libre: false, motivo: 'OCUPADO' },
            { hora: '10:00', fin: '10:30', libre: true },
          ],
        },
      });
    }
    if (ruta.includes('cita_guardar')) {
      window.__guardado = datos;
      return Promise.resolve({
        datos: { id_cita: 99, fecha_inicio: '2026-11-27 00:00:00' },
        mensaje: 'Dia apartado. Falta ponerle hora.',
      });
    }
    return Promise.resolve({ datos: {} });
  };

  window.__rotos = [];
  window.addEventListener('error', (ev) => {
    window.__rotos.push(ev.error ? ev.error.message : String(ev.message));
  });

  window.eval(
    fs.readFileSync(path.join(RAIZ, 'assets/js/consultas.js'), 'utf8'));

  return {
    window, doc,
    $: (s) => doc.querySelector(s),
    id: (x) => doc.getElementById(x),
    arrancar: () => alArrancar.forEach((fn) => fn()),
    // Las promesas del arranque se resuelven en la cola de microtareas:
    // un setTimeout(0) despues de un await alcanza a verlas terminadas.
    esperar: () => new Promise((r) => setTimeout(r, 0)),
    clic(el, queEra) {
      if (!el) {
        fallos.push(`no se encontro ${queEra || 'el elemento'} para picarlo`);
        return false;
      }
      return el.dispatchEvent(
        new window.MouseEvent('click', { bubbles: true, cancelable: true }));
    },
    cambiar(el) {
      el.dispatchEvent(new window.Event('change', { bubbles: true }));
    },
    oculto: (x) => doc.getElementById(x).classList.contains('d-none'),
  };
}

/* =====================================================================
   Las pruebas
   ===================================================================== */
async function pruebas() {

  /* -------------------------------------------------------------------
     1. El renglon de una cita apartada
     ------------------------------------------------------------------- */
  {
    const t = montar({ citas: [citaApartada(), citaNormal()] });
    t.arrancar();
    await t.esperar();

    comprobar('el arranque no revienta', t.window.__rotos, []);

    const filas = [...t.id('agTabla').querySelectorAll('tr[data-cita]')];
    comprobar('se pintaron las dos citas', filas.length, 2);

    const [apartada, normal] = filas;

    comprobar('la apartada dice "Sin hora"',
      /Sin hora/.test(apartada.textContent), true);
    comprobar('la apartada NO escribe las 00:00',
      apartada.textContent.includes('00:00'), false);
    comprobar('el renglon de la apartada va marcado',
      apartada.classList.contains('fila-sin-hora'), true);

    comprobar('la cita normal sigue diciendo su hora',
      /10:30/.test(normal.textContent), true);
    comprobar('la cita normal no va marcada',
      normal.classList.contains('fila-sin-hora'), false);

    /* El menu: confirmar una cita sin hora es prometerle al paciente
       un horario que no existe, asi que la opcion no se ofrece. */
    comprobar('la apartada no ofrece Confirmar',
      apartada.querySelector('[data-accion="cita-estado"][data-estado="2"]'),
      null);
    comprobar('la normal si ofrece Confirmar',
      !!normal.querySelector('[data-accion="cita-estado"][data-estado="2"]'),
      true);
    comprobar('la apartada ofrece ponerle hora',
      /Ponerle hora/.test(
        apartada.querySelector('[data-accion="cita-editar"]').textContent),
      true);
    comprobar('la normal sigue diciendo Reagendar',
      /Reagendar/.test(
        normal.querySelector('[data-accion="cita-editar"]').textContent),
      true);

    // Cancelar si tiene que estar: una cita apartada se cancela igual
    // que cualquier otra.
    comprobar('la apartada si se puede cancelar',
      !!apartada.querySelector('[data-estado="5"]'), true);
  }

  /* -------------------------------------------------------------------
     2. El interruptor del modal
     ------------------------------------------------------------------- */
  {
    const t = montar({ citas: [] });
    t.arrancar();
    await t.esperar();

    t.clic(t.id('btnNuevaCita'), 'el boton de nueva cita');
    await t.esperar();

    comprobar('al abrir, el interruptor esta apagado',
      t.id('citaSinHora').checked, false);
    comprobar('al abrir, la rejilla de horarios se ve',
      t.oculto('citaEspacios'), false);
    comprobar('al abrir, el aviso de dia apartado no se ve',
      t.oculto('citaAvisoSinHora'), true);

    // Sin paciente y sin hora, el boton no se puede picar.
    comprobar('el boton arranca deshabilitado',
      t.id('citaGuardar').disabled, true);

    // Se elige el paciente a mano, como lo deja el buscador.
    t.id('citaPaciente').value = '13';
    t.id('citaPacienteTexto').value = 'MATEO GUZMAN LOPEZ';

    t.id('citaSinHora').checked = true;
    t.cambiar(t.id('citaSinHora'));

    comprobar('encendido: la rejilla de horarios se esconde',
      t.oculto('citaEspacios'), true);
    comprobar('encendido: la duracion se esconde',
      t.oculto('citaCajaDuracion'), true);
    comprobar('encendido: el asterisco de horario se esconde',
      t.oculto('citaHoraRequerida'), true);
    comprobar('encendido: sale el aviso de que solo se aparta el dia',
      t.oculto('citaAvisoSinHora'), false);
    comprobar('encendido: el boton cambia de texto',
      /Apartar el día/.test(t.id('citaGuardar').textContent), true);

    /* Esta es la razon de ser del interruptor: con el encendido se
       puede guardar sin haber elegido ningun horario. Si siguiera
       deshabilitado la funcion no existiria. */
    comprobar('encendido: ya se puede guardar sin elegir hora',
      t.id('citaGuardar').disabled, false);

    // Y al apagarlo se vuelve a pedir la hora.
    t.id('citaSinHora').checked = false;
    t.cambiar(t.id('citaSinHora'));

    comprobar('apagado: la rejilla vuelve', t.oculto('citaEspacios'), false);
    comprobar('apagado: vuelve a hacer falta la hora',
      t.id('citaGuardar').disabled, true);
    comprobar('apagado: el boton vuelve a decir Agendar',
      /Agendar cita/.test(t.id('citaGuardar').textContent), true);
  }

  /* -------------------------------------------------------------------
     3. La hora elegida no se va de contrabando
     ------------------------------------------------------------------- */
  {
    const t = montar({ citas: [] });
    t.arrancar();
    await t.esperar();

    t.clic(t.id('btnNuevaCita'), 'el boton de nueva cita');
    await t.esperar();

    t.id('citaPaciente').value = '13';

    // Se elige las 10:00 de la rejilla...
    const bloque = t.doc.querySelector('#citaEspacios .espacio.libre[data-hora]');
    t.clic(bloque, 'un espacio libre');
    comprobar('al picar un espacio se guarda la hora',
      t.id('citaHora').value, '09:00');

    // ...y despues se cambia de idea.
    t.id('citaSinHora').checked = true;
    t.cambiar(t.id('citaSinHora'));

    /*
       Sin este borrado se mandarian las dos cosas: sin_hora=1 y
       hora=09:00. El servidor se queda con "sin hora" y lo descarta,
       asi que no se corrompe nada —pero la pantalla habria ensenado una
       hora elegida, en morado, y lo guardado seria otra cosa. El
       siguiente que lea este codigo tiene que poder confiar en que lo
       que se ve es lo que se manda.
    */
    comprobar('al encender el interruptor se borra la hora elegida',
      t.id('citaHora').value, '');
    comprobar('y el espacio deja de verse elegido',
      t.doc.querySelector('#citaEspacios .espacio.elegido'), null);

    t.clic(t.id('citaGuardar'), 'el boton de guardar');
    await t.esperar();
    await t.esperar();

    comprobar('se mando sin_hora al servidor',
      t.window.__guardado && t.window.__guardado.sin_hora, '1');
    comprobar('no se mando ninguna hora',
      t.window.__guardado && (t.window.__guardado.hora || ''), '');
    comprobar('el aviso es el que manda el servidor, no uno inventado',
      t.window.__avisos.some(([, texto]) => /Falta ponerle hora/.test(texto)),
      true);
  }

  /* -------------------------------------------------------------------
     4. Abrir una cita apartada para ponerle hora
     ------------------------------------------------------------------- */
  {
    const t = montar({ citas: [citaApartada()] });
    t.arrancar();
    await t.esperar();

    const fila = t.id('agTabla').querySelector('tr[data-cita]');
    t.clic(fila.querySelector('[data-accion="cita-editar"]'),
      'la opcion de ponerle hora');
    await t.esperar();

    /*
       Se entra aqui justamente para ponerle la hora, asi que el
       interruptor arranca APAGADO y la rejilla a la vista. Arrancar
       encendido —"asi estaba guardada"— obligaria a apagarlo a mano
       cada vez, y este es el unico camino por el que se abre una de
       estas citas.
    */
    comprobar('el interruptor arranca apagado',
      t.id('citaSinHora').checked, false);
    comprobar('la rejilla de horarios esta a la vista',
      t.oculto('citaEspacios'), false);
    comprobar('se trajo el paciente que ya tenia',
      t.id('citaPaciente').value, '13');
    comprobar('se trajo el motivo',
      t.doc.querySelector('#formCita [name="motivo"]').value,
      'CONTINUACION ORTODONCIA');

    /*
       La duracion: las dos fechas de una apartada son iguales, asi que
       la resta da cero. Cero no es ninguna de las opciones del
       selector, y asignarlo lo dejaria EN BLANCO: el servidor recibiria
       duracion_min vacia y contestaria "la duracion debe estar entre 15
       y 480 minutos" sobre un campo que no se ve.
    */
    comprobar('la duracion no se quedo en blanco',
      t.id('citaDuracion').value, '30');

    comprobar('nada revento en el camino', t.window.__rotos, []);
  }

  /* -------------------------------------------------------------------
     5. El detalle de la cita
     ------------------------------------------------------------------- */
  {
    const t = montar({ citas: [citaApartada()] });
    t.arrancar();
    await t.esperar();

    t.window.DetalleCita.abrir(citaApartada(), { alCambiar: async () => {} });

    comprobar('el detalle dice que todavia no tiene hora',
      /sin hora/i.test(t.id('dcCuando').textContent), true);
    comprobar('el detalle no escribe "00:00 a 00:00"',
      t.id('dcCuando').textContent.includes('00:00'), false);
    comprobar('el detalle conserva el dia',
      /27\/11\/2026/.test(t.id('dcCuando').textContent), true);

    const acciones = t.id('dcAcciones');
    comprobar('el detalle no ofrece Confirmar',
      acciones.querySelector('[data-estatus="2"]'), null);
    comprobar('el detalle explica por que',
      /Modificar/.test(acciones.textContent), true);
    comprobar('el detalle si ofrece cancelar',
      !!acciones.querySelector('[data-estatus="5"]'), true);

    // Y con una cita normal, Confirmar vuelve y el aviso no aparece.
    t.window.DetalleCita.abrir(citaNormal(), { alCambiar: async () => {} });
    comprobar('una cita con hora si ofrece Confirmar',
      !!t.id('dcAcciones').querySelector('[data-estatus="2"]'), true);
    comprobar('y no le sale el aviso de la hora que falta',
      /antes de confirm/i.test(t.id('dcAcciones').textContent), false);
    comprobar('su "cuando" lleva las dos horas',
      /10:30 a 11:30/.test(t.id('dcCuando').textContent), true);
  }
}

/* ===================================================================== */
pruebas().then(() => {
  console.log();
  if (fallos.length) {
    console.log(`  *** ${fallos.length} fallo(s):`);
    fallos.forEach((f) => console.log('  !!   ' + f));
    process.exit(1);
  }
  console.log('  Todo en orden: la agenda aparta el dia sin inventar una hora.');
}).catch((e) => {
  console.log('  !!   la prueba revento: ' + e.stack);
  process.exit(1);
});
