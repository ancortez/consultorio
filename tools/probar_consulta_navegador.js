/**
 * El detalle de la consulta, corrido en un navegador de mentira.
 *
 * Por que existe
 * --------------
 * Esta ronda movio de sitio media pantalla: se quito la tarjeta de
 * documentos, el Guardar bajo al pie del motivo, la barra de arriba
 * paso a leerse como texto y se agrego un modal que pregunta de que
 * plan es la consulta.
 *
 * Nada de eso es una cuenta que se pueda probar en Python. Es
 * pegamento: elementos que se buscan por id, manejadores que se cuelgan
 * de un contenedor y clases que se ponen y se quitan. Y el pegamento
 * falla de la peor manera posible —en silencio—: un
 * getElementById('cnDocumentos') que ahora devuelve null revienta DENTRO
 * del arranque, el navegador lo apunta en la consola y sigue como si
 * nada, asi que la pantalla se dibuja entera y ningun boton responde.
 * Eso ya le paso a Angel dos veces, y mirando el codigo no se ve.
 *
 * Asi que se levanta la pantalla DE VERDAD —la vista y los modales,
 * leidos de sus archivos y sin el PHP— con el consultas.js de verdad, y
 * se pican las cosas.
 *
 * Como correrlo
 * -------------
 *     npm install jsdom          (una vez)
 *     node tools/probar_consulta_navegador.js
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

/* =====================================================================
   El teatro
   ===================================================================== */
function montar({ idConsulta = '', preguntarPlan = false } = {}) {
  const vista   = sinPhp('vistas/consultas/consulta.php');
  const modales = sinPhp('vistas/consultas/_modales_consulta.php');
  const modalPr = sinPhp('vistas/consultas/_modal_procedimiento.php');

  const dom = new JSDOM(
    `<!DOCTYPE html><body>${vista}${modales}${modalPr}</body>`,
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

  // Bootstrap de mentira: abrir, cerrar y poder preguntar si esta abierto.
  const cajas = new Map();
  window.bootstrap = {
    Modal: {
      getOrCreateInstance(el) {
        if (!el) throw new Error('getOrCreateInstance con null');
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
  window.Album = { abrir: () => {}, soloImagenes: (l) => (l ?? []) };
  window.Odontograma = { crear: () => ({}) };

  window.eval(fs.readFileSync(path.join(RAIZ, 'assets/js/app.js'), 'utf8')
            + '\n;window.App = App;');

  window.__errores = [];
  window.App.alerta = {
    error: (...a) => { window.__errores.push(a.join(' ')); },
    info: () => {}, toast: () => {}, confirmar: async () => true,
    cargando: () => {}, cerrar: () => {},
  };

  /*
     Los planes salen de un foreach de PHP, asi que al quitar el PHP
     queda la plantilla de UN option vacio. Se rellena a mano con dos
     planes, que es lo que hace falta para probar que la propuesta de
     uno no pisa la del otro.
  */
  const sel = doc.getElementById('cnPlan');
  const molde = sel.querySelector('option:not([value=""])');
  if (molde) molde.remove();

  [['7', 'PT-008 — ORTODONCIA INTERCEPTIVA',
    '1RA CITA PLAN DE TRATAMIENTO PT-008', 'ORTODONCIA INTERCEPTIVA'],
   ['9', 'PT-012 — RESINAS EN CUATRO ORGANOS',
    '3RA CITA PLAN DE TRATAMIENTO PT-012', 'RESINAS EN CUATRO ORGANOS'],
  ].forEach(([valor, texto, motivo]) => {
    const o = doc.createElement('option');
    o.value = valor;
    o.textContent = texto;
    // Solo data-motivo: data-padecimiento se quito de la vista, y
    // ponerlo aqui haria que el arnes trabajara con una pantalla que ya
    // no existe —justo lo contrario de para lo que sirve—.
    o.dataset.motivo = motivo;
    sel.appendChild(o);
  });

  doc.getElementById('cnId').value = idConsulta;
  doc.getElementById('cnPreguntarPlan').value = preguntarPlan ? '1' : '';

  // El dentista tambien viene de un foreach: se pone uno.
  const selD = doc.querySelector('[name="id_usuario_dentista"]');
  const o = doc.createElement('option');
  o.value = '3';
  o.textContent = 'ADRIANA LOPEZ RUELAS';
  selD.appendChild(o);

  window.__rotos = [];
  window.addEventListener('error', (ev) => {
    window.__rotos.push(ev.error ? ev.error.message : String(ev.message));
  });

  // El consultas.js DE VERDAD. Es lo unico que hace util a este arnes:
  // un doble escrito por mi no probaria el archivo que se sirve.
  window.eval(
    fs.readFileSync(path.join(RAIZ, 'assets/js/consultas.js'), 'utf8'));

  // La consulta se pide al servidor. Se contesta a mano para no
  // depender de nada, o se deja colgada cuando es nueva.
  window.__respuesta = null;
  window.App.api = (ruta) => {
    if (ruta.includes('consulta_obtener')) {
      return window.__respuesta
        ? Promise.resolve(window.__respuesta)
        : new Promise(() => {});
    }
    return new Promise(() => {});
  };

  return {
    window, doc,
    $: (s) => doc.querySelector(s),
    arrancar: () => alArrancar.forEach((fn) => fn()),
    clic(el, queEra) {
      if (!el) {
        fallos.push(`no se encontro ${queEra || 'el elemento'} para picarlo`);
        return false;
      }
      return el.dispatchEvent(
        new window.MouseEvent('click', { bubbles: true, cancelable: true }));
    },
    abierto: (id) => cajas.get(doc.getElementById(id))?._visible === true,
  };
}

/** La consulta que contestaria el servidor. */
function consultaGuardada(extra = {}) {
  return {
    datos: Object.assign({
      id_consulta: 55,
      id_registro_paciente: 13,
      folio: 'CN-0055',
      fecha_hora: '2026-10-13 10:30:00',
      id_usuario_dentista: 3,
      id_plan_tratamiento: 7,
      motivo: '1RA CITA PLAN DE TRATAMIENTO PT-008',
      cerrada: 0,
      paciente: 'JUAN PEREZ LOPEZ',
      edad: 9,
      hallazgos: [],
      recetas: [],
      notas: [],
      documentos: [],
      tipos: [
        { clave: 'OTRO_CONSULTA', nombre_documento: 'Otro documento',
          icono: 'bi-paperclip', formatos: 'pdf,jpg' },
        { clave: 'RECETA', nombre_documento: 'Receta',
          icono: 'bi-prescription2', formatos: 'pdf,jpg' },
      ],
    }, extra),
  };
}

/* =====================================================================
   1. Una consulta NUEVA de un paciente con planes
   ===================================================================== */
console.log('Consulta nueva');
{
  const t = montar({ preguntarPlan: true });
  const { $, doc, clic, abierto, window } = t;
  t.arrancar();

  // Lo primero: la pregunta. Antes era un combo con "Consulta general"
  // preseleccionado y nadie lo tocaba.
  comprobar('al abrir, pregunta de que plan es',
    abierto('modalPlanConsulta'), true);
  comprobar('y ofrece los dos planes vivos',
    doc.querySelectorAll('#pcPlanes [data-plan]').length, 2);

  /* Lo que no sirve todavia esta difuminado y apagado, no solo apagado.

     Son TRES: la exploracion, las notas de evolucion y la columna de
     anexos. Fueron dos durante unos dias, cuando las notas vivian
     dentro de los anexos; el 16 de octubre de 2026 salieron de ahi a su
     propia columna, a todo el alto, y volvieron a ser una zona por su
     cuenta.

     Lo que importa no es el numero sino que ninguna quede DENTRO de
     otra: el filtro y la opacidad se multiplican, y una zona anidada se
     ve mas apagada que sus vecinas —parece estropeada, no deshabilitada—. */
  const zonas = [...doc.querySelectorAll('.zona-sin-consulta')];
  comprobar('hay tres zonas inservibles antes de guardar', zonas.length, 3);
  comprobar('y ninguna esta anidada dentro de otra',
    zonas.some((z) => z.parentElement?.closest('.zona-sin-consulta')), false);
  comprobar('todas se ven difuminadas',
    zonas.every((z) => z.classList.contains('difuminada')), true);
  comprobar('y ninguna recibe clics ni tabulador',
    zonas.every((z) => z.inert === true), true);
  comprobar('el boton de hallazgo esta apagado',
    doc.getElementById('cnNuevoHallazgo').disabled, true);
  comprobar('y el de anexar un estudio tambien',
    doc.getElementById('cnOtroDocumento').disabled, true);
  /* Las notas salieron de los anexos: ahora son la columna derecha.

     Se comprueban las dos mitades del cambio —que esten fuera de los
     anexos y que haya UNA sola— porque la mudanza se hizo copiando la
     tarjeta, y una copia que se quede atras deja dos cajas de texto
     distintas guardando el mismo campo. */
  comprobar('las notas ya no viven dentro de los anexos',
    !!doc.getElementById('cnNotas').closest('#cnAnexos'), false);
  comprobar('y hay una sola caja de notas',
    doc.querySelectorAll('#cnNotas').length, 1);

  // El Guardar, abajo: pegado al ultimo campo que se escribe, y el
  // unico sitio vivo de la pantalla.
  comprobar('el Guardar de abajo se ve',
    doc.getElementById('cnPieGuardar').classList.contains('d-none'), false);
  comprobar('y el de arriba no',
    doc.getElementById('cnGuardarArriba').classList.contains('d-none'), true);

  // La barra se captura: en una consulta nueva no hay nada que leer.
  comprobar('la barra entra en modo captura, sin lapices',
    [...doc.querySelectorAll('.lapiz-campo')]
      .every((l) => l.classList.contains('d-none')), true);
  comprobar('y con los controles a la vista',
    doc.getElementById('cnFechaHora').classList.contains('d-none'), false);

  // Elegir un plan propone el motivo. (El padecimiento actual se quito
  // de la pantalla el 16 de octubre de 2026, y con el su propuesta.)
  clic(doc.querySelector('#pcPlanes [data-plan="7"]'), 'el primer plan');
  comprobar('elegir el plan cierra la pregunta',
    abierto('modalPlanConsulta'), false);
  comprobar('y lo deja seleccionado', doc.getElementById('cnPlan').value, '7');
  comprobar('propone el motivo con el ordinal y el folio',
    $('[name="motivo"]').value, '1RA CITA PLAN DE TRATAMIENTO PT-008');
  /*
     Y los dos campos que se quitaron no vuelven por la puerta de atras.

     proponerDelPlan() tenia un querySelector del campo de padecimiento.
     Quitando el campo de la vista, ese querySelector devuelve null y la
     asignacion revienta DENTRO del manejador del cambio de plan: la
     pantalla se dibuja entera y elegir un plan deja de hacer nada. Es
     la misma falla silenciosa que ya aparecio dos veces en este
     proyecto, asi que se comprueba que ni los campos ni su propuesta
     siguen ahi.
  */
  comprobar('el padecimiento actual ya no esta en la pantalla',
    $('[name="padecimiento_actual"]'), null);
  comprobar('el tratamiento realizado tampoco',
    $('[name="plan_indicaciones"]'), null);
  comprobar('elegir el plan no revento nada', window.__rotos, []);

  // Cambiar de plan reescribe la propuesta: lo que hay es mio.
  doc.getElementById('cnPlan').value = '9';
  doc.getElementById('cnPlan').dispatchEvent(
    new window.Event('change', { bubbles: true }));
  comprobar('cambiar de plan reescribe la propuesta',
    $('[name="motivo"]').value, '3RA CITA PLAN DE TRATAMIENTO PT-012');

  // Pero no pisa lo que una persona escribio. Este es el caso que
  // convierte una propuesta en un estorbo.
  $('[name="motivo"]').value = 'SE CAYO EN LA ESCUELA';
  doc.getElementById('cnPlan').value = '7';
  doc.getElementById('cnPlan').dispatchEvent(
    new window.Event('change', { bubbles: true }));
  comprobar('pero NO pisa lo que se escribio a mano',
    $('[name="motivo"]').value, 'SE CAYO EN LA ESCUELA');

  comprobar('nada revento en el arranque', window.__rotos, []);
}

/* =====================================================================
   2. "Consulta general" sigue siendo una respuesta
   ===================================================================== */
console.log();
console.log('Consulta general');
{
  const t = montar({ preguntarPlan: true });
  const { $, doc, clic, abierto } = t;
  t.arrancar();

  clic(doc.getElementById('pcGeneral'), 'el boton de consulta general');
  comprobar('contestar "general" cierra la pregunta',
    abierto('modalPlanConsulta'), false);
  comprobar('y deja el plan vacio', doc.getElementById('cnPlan').value, '');
  comprobar('sin proponer ningun motivo', $('[name="motivo"]').value, '');
}

/* =====================================================================
   3. Sin planes vivos no se pregunta nada
   ===================================================================== */
console.log();
console.log('Paciente sin tratamiento abierto');
{
  const t = montar({ preguntarPlan: false });
  const { abierto } = t;
  t.arrancar();
  comprobar('no se pregunta de que plan es', abierto('modalPlanConsulta'), false);
}

/* =====================================================================
   4. Una consulta YA GUARDADA
   ===================================================================== */
console.log();
console.log('Consulta guardada');
(async () => {
  const t = montar({ idConsulta: '55' });
  const { $, doc, clic, window } = t;
  window.__respuesta = consultaGuardada();
  t.arrancar();

  // cargar() es asincrono: se le da el turno.
  await new Promise((r) => setTimeout(r, 0));

  comprobar('la barra pasa a leerse como texto',
    [...doc.querySelectorAll('.campo-texto')]
      .every((c) => !c.classList.contains('d-none')), true);
  comprobar('y los controles se esconden',
    doc.getElementById('cnFechaHora').classList.contains('d-none'), true);
  comprobar('el plan se lee con su folio',
    doc.querySelector('[data-texto="plan"]').textContent.includes('PT-008'), true);
  comprobar('y el dentista con su nombre',
    doc.querySelector('[data-texto="dentista"]').textContent.trim(),
    'ADRIANA LOPEZ RUELAS');
  comprobar('los tres lapices estan a la vista',
    [...doc.querySelectorAll('.lapiz-campo')]
      .filter((l) => !l.classList.contains('d-none')).length, 3);

  // El lapiz abre UNO. Si quedaran los tres abiertos se pierde la razon
  // de haberlos cerrado: un combo abierto y una rueda de raton bastan
  // para mover la consulta a otro plan y llevarse sus papeles.
  clic(doc.querySelector('[data-editar="plan"]'), 'el lapiz del plan');
  comprobar('el lapiz abre su campo',
    doc.getElementById('cnPlan').classList.contains('d-none'), false);
  comprobar('y los otros dos siguen cerrados',
    doc.getElementById('cnFechaHora').classList.contains('d-none'), true);

  clic(doc.querySelector('[data-editar="fecha"]'), 'el lapiz de la fecha');
  comprobar('abrir otro cierra el primero',
    doc.getElementById('cnPlan').classList.contains('d-none'), true);
  comprobar('y abre el suyo',
    doc.getElementById('cnFechaHora').classList.contains('d-none'), false);

  // Ya guardada, cambiar el plan NO reescribe el motivo: eso seria
  // corregir el expediente por mover un papel de carpeta.
  doc.getElementById('cnPlan').value = '9';
  doc.getElementById('cnPlan').dispatchEvent(
    new window.Event('change', { bubbles: true }));
  comprobar('en una consulta guardada el motivo no se reescribe',
    $('[name="motivo"]').value, '1RA CITA PLAN DE TRATAMIENTO PT-008');

  // Y el resto de la pantalla ya sirve.
  const zonas = [...doc.querySelectorAll('.zona-sin-consulta')];
  comprobar('las zonas dejan de estar difuminadas',
    zonas.some((z) => z.classList.contains('difuminada')), false);
  comprobar('y dejan de estar apagadas',
    zonas.some((z) => z.inert === true), false);
  comprobar('se puede anexar un estudio externo',
    doc.getElementById('cnOtroDocumento').disabled, false);

  /*
     El Guardar se mudo arriba.

     Una vez creada la consulta el trabajo deja de ser "llenar el
     motivo" y pasa a ser ir y venir entre hallazgos, recetas, cargos y
     notas: el boton ya no pertenece al pie de UNA tarjeta sino a la
     pantalla entera, y su sitio es junto a Cerrar. Se comprueba que se
     vea UNO de los dos y no los dos: dos Guardar a la vez no son dos
     atajos, son la duda de si hacen lo mismo.
  */
  comprobar('el Guardar sube a la cabecera',
    doc.getElementById('cnGuardarArriba').classList.contains('d-none'), false);
  comprobar('y el de abajo desaparece',
    doc.getElementById('cnPieGuardar').classList.contains('d-none'), true);
  comprobar('se ve exactamente un Guardar',
    [...doc.querySelectorAll('[data-guardar]')]
      .filter((b) => !b.closest('.d-none') && !b.classList.contains('d-none'))
      .length, 1);

  // Y el de arriba guarda de verdad: es un boton distinto, no el mismo
  // movido, asi que si la escucha no estuviera delegada quedaria mudo.
  let pidioGuardar = false;
  const apiAntes = window.App.api;
  window.App.api = (ruta, datos) => {
    if (ruta.includes('consulta_guardar')) {
      pidioGuardar = true;
      return Promise.resolve({ datos: { id_consulta: 55, folio: 'CN-0055' } });
    }
    return apiAntes(ruta, datos);
  };
  clic(doc.getElementById('cnGuardarArriba'), 'el Guardar de arriba');
  await new Promise((r) => setTimeout(r, 0));
  comprobar('el Guardar de arriba si guarda', pidioGuardar, true);
  window.App.api = apiAntes;

  comprobar('nada revento al pintar la consulta', window.__rotos, []);

  /* -----------------------------------------------------------------
     5. La receta firmada se sube desde su propio renglon
     ----------------------------------------------------------------- */
  console.log();
  console.log('La receta y su escaneo');

  const t2 = montar({ idConsulta: '55' });
  t2.window.__respuesta = consultaGuardada({
    recetas: [{ id_receta: 4, folio: 'RX-0004', fecha: '2026-10-13',
                dentista: 'ADRIANA LOPEZ RUELAS',
                indicaciones: 'PARACETAMOL 160 MG',
                id_documento: 88 }],
    documentos: [{ id_documento: 88, clave: 'RECETA', estado: 0,
                   descripcion: 'RECETA RX-0004', nombre_original: null }],
  });
  t2.arrancar();
  await new Promise((r) => setTimeout(r, 0));

  const chip = t2.doc.querySelector('#cnRecetas [data-rx-escanear]');
  comprobar('el pendiente sale pegado a su receta', !!chip, true);
  comprobar('y apunta al documento de esa receta',
    chip?.dataset.rxEscanear, '88');

  // Picarlo tiene que abrir el campo de archivo, no quedarse mudo.
  const campo = t2.doc.getElementById('cnArchivoPendiente');
  let abrio = false;
  campo.click = () => { abrio = true; };
  t2.clic(chip, 'el chip de adjuntar la receta firmada');
  comprobar('picarlo pide el archivo', abrio, true);
  comprobar('con el documento correcto apuntado',
    campo.dataset.documento, '88');

  // Y una vez subida, se ve en vez de volver a pedirla.
  const t3 = montar({ idConsulta: '55' });
  t3.window.__respuesta = consultaGuardada({
    recetas: [{ id_receta: 4, folio: 'RX-0004', fecha: '2026-10-13',
                dentista: 'ADRIANA LOPEZ RUELAS',
                indicaciones: 'PARACETAMOL 160 MG',
                id_documento: 88 }],
    documentos: [{ id_documento: 88, clave: 'RECETA', estado: 1,
                   descripcion: 'RECETA RX-0004',
                   nombre_original: 'receta.jpg' }],
  });
  t3.arrancar();
  await new Promise((r) => setTimeout(r, 0));

  comprobar('ya subida, deja de pedirse',
    !!t3.doc.querySelector('#cnRecetas [data-rx-escanear]'), false);
  comprobar('y se puede ver',
    !!t3.doc.querySelector('#cnRecetas a[href*="archivo.php?id=88"]'), true);
  comprobar('y se cuenta como archivo anexado',
    t3.doc.getElementById('cnCuentaDocumentos').textContent.trim(),
    '1 archivo anexado');
  comprobar('nada revento con recetas en pantalla', t3.window.__rotos, []);

  console.log();
  if (fallos.length) {
    console.log(`*** ${fallos.length} PROBLEMA(S) ***`);
    fallos.forEach((f) => console.log('  !!   ' + f));
    process.exit(1);
  }
  console.log('*** TODO EN ORDEN ***');
})();
