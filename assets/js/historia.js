/* =====================================================================
   Historia clinica

   El formulario tiene sesenta campos. Se carga y se guarda de golpe, sin
   guardado por seccion: la doctora captura la hoja completa de una vez y
   guardar a pedazos solo daria mas oportunidades de perder algo.
   ===================================================================== */

const Historia = (() => {

  let idExpediente = null;
  let cargado = false;
  let pasos = null;
  let existeHistoria = false;

  /* -------------------------------------------------------------------
     Prellenado

     Lo que sale asi en la gran mayoria de los ninios sanos. No es un
     diagnostico: es el punto de partida que la doctora corrige donde
     haga falta, para no picar veinte botones identicos cada vez.

     Por eso el prellenado NO se aplica solo al abrir la pantalla: si
     lo hiciera, una historia sin capturar y una historia "todo normal"
     se verian igual y no habria forma de distinguirlas.
     ------------------------------------------------------------------- */
  const HABITUAL = {
    // Antecedentes no patologicos
    embarazo_normal: 1,
    tipo_parto: 1,          // Natural
    tipo_nacimiento: 1,     // A termino
    complicaciones_parto: 0,
    lactancia_materna: 1,
    formula: 1,
    dieta_completa: 1,

    // Examen bucal: normal, que es lo que pasa en la mayoria de los
    // pacientes. Es tambien el valor por omision de la columna.
    examen_bucal_normal: 1,

    // Antecedentes patologicos
    estado_salud: 1,        // Bueno
    enfermedad_cronica: 0,
    discapacidad: 0,
    alergia: 0,
    tratamiento_medico: 0,
    cirugias: 0,

    // Antecedentes odontologicos y habitos
    experiencia_previa: 0,
    recibio_prevencion: 0,
    usa_pasta_dental: 1,
    otro_higiene: 0,

    // Examen bucal
    tipo_denticion: 2,      // Mixta
  };

  /*
     Campos que no son de si/no pero tambien tienen un valor habitual.

     Van aparte porque se marcan distinto: los de arriba son grupos de
     botones con un campo oculto detras; estos son un <select> o un
     <input> comun, y hay que escribirles el valor directamente.
  */
  const HABITUAL_CAMPOS = {
    momentos_azucar: 'REGULAR',
  };

  /**
   * Marca los valores habituales.
   *
   * @param {boolean} soloVacios  true = respeta lo que ya este marcado.
   *        Es lo que se quiere siempre: prellenar no debe pisar una
   *        respuesta que alguien ya dio.
   */
  function prellenar(soloVacios = true) {
    const form = document.getElementById('formHistoria');
    let puestos = 0;

    Object.entries(HABITUAL).forEach(([campo, valor]) => {
      const oculto = form.querySelector(`input[type="hidden"][name="${campo}"]`);
      if (!oculto) return;
      if (soloVacios && oculto.value !== '') return;

      const grupo = oculto.closest('.grupo-sino');
      if (grupo) { elegir(grupo, valor); puestos++; }
    });

    Object.entries(HABITUAL_CAMPOS).forEach(([campo, valor]) => {
      const el = form.querySelector(`[name="${campo}"]`);
      if (!el) return;
      if (soloVacios && el.value !== '') return;

      el.value = valor;
      /*
         Aqui NO se lanza un 'change'.

         Lo lanzaba, y por eso el prellenado de un paciente nuevo abria
         la ventanita de "¿que alimentos con azucar?": el prellenado
         pone "REGULAR" en momentos de azucar, el change sintetico llega
         al escucha del desplegable, y ese escucha existe justamente
         para saber que alguien eligio. Un evento disparado por codigo
         es indistinguible de uno disparado por una persona, asi que el
         sistema preguntaba por una respuesta que nadie habia dado.

         Es el mismo error que ya me costo dos entregas con los grupos
         de si/no, en otra forma. La regla que salio de aquello vale
         igual aqui: quien pregunta tiene que saber que lo llamo una
         persona, y el prellenado no lo es.

         Las dependencias se recalculan DESPUES, de una vez y sin
         preguntar, en quien llama a prellenar().
      */
      puestos++;
    });

    return puestos;
  }

  /* -------------------------------------------------------------------
     Grupos de si/no y de opciones
     ------------------------------------------------------------------- */

  /** Marca una opcion dentro de su grupo y escribe el valor oculto. */
  /**
   * Marca una opcion dentro de su grupo y escribe el valor oculto.
   *
   * @param {boolean} porPersona true solo si lo acaba de picar alguien.
   *
   * Ese tercer argumento es el corazon del asunto. La primera version
   * colgaba la pregunta extra del evento 'change', y ese evento no
   * distingue quien lo provoco: salta igual cuando alguien pica "Si"
   * que cuando el codigo rellena el formulario con la historia
   * guardada. Resultado: abrir un expediente ya capturado lanzaba las
   * doce ventanas en cadena, el prellenado hacia lo mismo, y escribir
   * en cualquier campo volvia a dispararlas.
   *
   * El clic si distingue. Por eso la pregunta cuelga de aqui y no de
   * un escucha de 'change'.
   */
  function elegir(grupo, valor, porPersona = false) {
    const oculto = grupo.querySelector('input[type="hidden"]');

    grupo.querySelectorAll('.opcion-sino').forEach((b) => {
      b.classList.toggle('activa', b.dataset.valor === String(valor));
    });

    oculto.value = valor === null ? '' : valor;

    // El 'change' se sigue lanzando: otras partes de la pantalla lo
    // escuchan. Lo que ya no hace es abrir ventanas.
    oculto.dispatchEvent(new Event('change', { bubbles: true }));

    /*
       Se repinta, y ya. Nadie pregunta nada.

       Hasta el 17 de octubre de 2026 esto abria la ventanita del
       complemento cuando la respuesta la acababa de dar una persona.
       La doctora pidio que no: contestar doce preguntas de si/no se
       hace de corrido, y una ventana que salta en medio rompe el
       ritmo —hay que leerla, cerrarla y volver a encontrar donde se
       iba—. Peor cuando el complemento es opcional, que lo son los
       doce: la ventana interrumpia para pedir algo que se puede no
       contestar.

       Lo que sale en su lugar es la capsula con NO ESPECIFICA, que se
       pica cuando se quiera escribir el detalle. El dato queda a la
       vista y el camino para llenarlo tambien; lo que se va es la
       interrupcion.

       porPersona se conserva en la firma a proposito: lo mandan los
       dos sitios que llaman a elegir() y lo sigue usando quien
       distingue "lo contesto alguien" de "lo relleno el codigo".
    */
    const caja = document.querySelector(`[data-depende="${oculto.name}"]`);
    if (caja) atender(caja);
  }

  function engancharGrupos() {
    document.querySelectorAll('.grupo-sino').forEach((grupo) => {
      grupo.addEventListener('click', (ev) => {
        const boton = ev.target.closest('.opcion-sino');
        if (!boton) return;

        // Volver a picar la opcion ya marcada la desmarca. Sirve cuando
        // se marco por error: sin esto no habria forma de dejarla en
        // blanco, y "no contestado" no es lo mismo que "no".
        const yaEstaba = boton.classList.contains('activa');
        elegir(grupo, yaEstaba ? null : boton.dataset.valor, true);
      });
    });
  }

  /* -------------------------------------------------------------------
     Casillas con detalle
     ------------------------------------------------------------------- */
  /* -------------------------------------------------------------------
     Campos que aparecen al contestar

     "¿Es la primera visita?" solo necesita "¿cuando fue la ultima vez?"
     si la respuesta es NO. Doce preguntas de la historia son asi, y
     tenerlas siempre a la vista llenaba la pantalla de campos que en la
     mayoria de los pacientes se quedan vacios.

     La regla vive en el HTML: data-depende dice de que pregunta cuelga
     y data-cuando con que respuesta aparece. Un "!" adelante invierte
     la condicion ("aparece con cualquier respuesta MENOS esta").
     ------------------------------------------------------------------- */
  /* -------------------------------------------------------------------
     La pregunta que cuelga de una respuesta
     -------------------------------------------------------------------
     Doce preguntas abren otra: "¿alergico? -> ¿a que?". Antes el campo
     extra brotaba en el hueco de al lado, empujaba a sus vecinos y la
     rejilla se descuadraba a media captura. Ahora sale en una ventanita
     y la respuesta se queda escrita bajo el selector.

     Dos reglas que parecen detalles y no lo son:

       - Solo se abre cuando la respuesta la acaba de dar una PERSONA.
         Al cargar la historia ya guardada se pintan las respuestas, sin
         ventana: si no, abrir un expediente viejo lanzaria doce modales
         en cadena.

       - Cerrar sin contestar DESHACE la respuesta. Marcar "lactancia
         materna: si" y cerrar la ventana deja la pregunta en "no", no
         en un "si" sin el "hasta cuando". Un si a medias es un dato que
         parece capturado y no lo esta.
  */
  /*
     La ventana se busca CADA VEZ, no al cargar el archivo.

     Este modulo se evalua en cuanto entra el script, y la historia
     clinica se inyecta despues: dentro de la ficha del paciente vive en
     una pestania que se carga al abrirla. Guardando la referencia aqui
     arriba, en esa pantalla valdria null para siempre y la ventana no
     abriria nunca —funcionando, eso si, en la pantalla propia de la
     historia, que es donde uno prueba—. Buscarla al momento cuesta
     nada y no depende del orden de carga.
  */
  function cajaPregunta() {
    return document.getElementById('modalPreguntaExtra');
  }

  function modalPregunta() {
    const caja = cajaPregunta();
    return caja ? bootstrap.Modal.getOrCreateInstance(caja) : null;
  }

  /*
     Lo que se ensenia cuando el complemento no se contesto.

     Los doce complementos son OPCIONALES desde el 16 de octubre de
     2026. Antes eran obligatorios: un "¿alergico? SI" sin decir a que
     no dejaba avanzar, y el bloque de la pregunta se marcaba en
     amarillo.

     La doctora pidio quitar esa traba, y tiene razon de consultorio: el
     tutor muchas veces no sabe el detalle en ese momento —"si tuvo
     complicaciones en el parto, pero no me acuerdo de que"— y el
     sistema estaba obligando a elegir entre inventar un detalle o
     contestar "no" a una pregunta cuya respuesta es "si". Las dos
     salidas ensucian el expediente; la segunda, encima, borra un
     antecedente clinico.

     Lo que NO cambia es que el renglon nunca sale en blanco. Un blanco
     en una hoja firmada no dice "no se especifico": dice "no se
     pregunto", y se puede llenar a mano despues de la firma. Asi que lo
     que se guarda sigue siendo vacio —para poder distinguir despues— y
     lo que se ve y se imprime es este texto.

     Tiene que decir lo mismo que HistoriaClinica::SIN_ESPECIFICAR en
     PHP: la pantalla y la hoja impresa no pueden contestar distinto a
     la misma pregunta.
  */
  const SIN_ESPECIFICAR = 'NO ESPECIFICA';

  /*
     Aqui vivia valorPrevio, un Map con lo que valia cada selector antes
     del ultimo cambio.

     Servia para deshacer: si una respuesta abria su pregunta extra y la
     ventana se cerraba sin contestar, el selector volvia a su valor
     anterior. Al volverse opcional el detalle ya no hay nada que
     deshacer, y el Map se quedo escribiendose sin que nadie lo leyera
     —con su escucha de 'focus' incluida, puesta ahi solo para
     alimentarlo—.

     Se quita en vez de dejarlo: un dato que se mantiene al dia y que
     nadie usa es lo que el siguiente va a creer que esta vivo.
  */

  // A que bloque corresponde la ventana que esta abierta.
  //
  // Habia tambien una bandera 'contestada', que alCerrarVentana miraba
  // para saber si deshacer la respuesta. Se fue con el deshacer.
  let preguntaEnCurso = null;

  /**
   * El trozo de pantalla donde va la respuesta de un bloque.
   *
   * Dos formas de preguntar, y antes solo se contemplaba una:
   *
   *   - un grupo de botones Si/No, que guarda su valor en un input
   *     oculto. El bloque es el contenedor del grupo.
   *   - un desplegable, como "momentos de azucar". Vive directo en su
   *     celda de la rejilla.
   *
   * Esto era un fallo con sintoma: pintarRespuesta() exigia un
   * .grupo-sino y se salia sin hacer nada si no lo encontraba, asi que
   * la respuesta de "¿que alimentos con azucar?" se guardaba pero no se
   * veia en ningun lado. El dato estaba; la pantalla decia que no.
   */
  function bloqueDelDisparador(nombre) {
    const control = document.querySelector(`[name="${nombre}"]`);
    if (!control) return null;

    const grupo = control.closest('.grupo-sino');
    return grupo ? grupo.parentElement : control.parentElement;
  }

  /** ¿Esta respuesta pide la pregunta extra? */
  function toca(caja, valor) {
    const cuando = caja.dataset.cuando ?? '1';
    if (cuando.startsWith('!')) {
      // "Que alimentos" aparece con cualquier frecuencia de azucar
      // menos "No consume", y no aparece si todavia no se contesta.
      return valor !== '' && valor !== cuando.slice(1);
    }
    return valor === cuando;
  }

  /** El texto con el que se encabeza la ventana. */
  function etiquetaDe(caja) {
    const propia = caja.querySelector('label');
    if (propia) return propia.innerHTML;

    const campo = caja.querySelector('input, textarea');
    return campo?.placeholder || 'Especifique';
  }

  function abrirPregunta(caja) {
    const campo = caja.querySelector('input, textarea');
    const ventana = modalPregunta();
    if (!campo || !ventana) return;

    preguntaEnCurso = caja;

    const disparador = document.querySelector(
      `[name="${caja.dataset.depende}"]`);
    const grupo = disparador?.closest('.grupo-sino');
    const titulo = grupo?.parentElement
      ?.querySelector('.form-label')?.textContent?.trim();

    document.getElementById('pxTitulo').textContent = titulo || 'Un dato mas';
    document.getElementById('pxEtiqueta').innerHTML = etiquetaDe(caja);
    document.getElementById('pxAyuda').textContent = campo.placeholder || '';

    const valor = document.getElementById('pxValor');
    valor.value = campo.value ?? '';
    valor.maxLength = campo.maxLength > 0 ? campo.maxLength : 255;

    ventana.show();

    const marco = cajaPregunta();
    // El foco al cuadro en cuanto la ventana termina de aparecer: se
    // viene a escribir, no a mirar.
    marco.addEventListener('shown.bs.modal', () => valor.focus(), { once: true });
    marco.addEventListener('hidden.bs.modal', alCerrarVentana, { once: true });
  }

  /**
   * Pinta la respuesta bajo el selector, o la quita.
   *
   * La capsula aparece en cuanto la pregunta PIDE complemento, con o
   * sin texto. Sin texto dice "NO ESPECIFICA" y se ve mas apagada.
   *
   * Que aparezca vacia es lo importante: es el recordatorio de que ahi
   * hay un dato que se puede llenar, y el camino para llenarlo —se pica
   * y se abre la ventana—. Sin capsula, un complemento sin contestar
   * seria invisible y nadie volveria a el; con la capsula, quien
   * pregunta despues "¿a que era alergico?" tiene donde escribirlo.
   */
  function pintarRespuesta(caja) {
    const campo = caja.querySelector('input, textarea');
    const texto = (campo?.value ?? '').trim();

    const bloque = bloqueDelDisparador(caja.dataset.depende);
    if (!bloque) return;

    const control = document.querySelector(`[name="${caja.dataset.depende}"]`);
    const aplica = control !== null && toca(caja, (control.value ?? '').trim());

    let chip = bloque.querySelector('.respuesta-extra');

    // Sin texto Y sin aplicar no hay nada que ensenar: la pregunta ni
    // siquiera pide complemento.
    if (!texto && !aplica) { chip?.remove(); return; }

    if (!chip) {
      chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'respuesta-extra';
      // Picar la respuesta la vuelve a abrir: corregir una errata no
      // deberia obligar a desmarcar y volver a marcar el si.
      chip.addEventListener('click', () => abrirPregunta(caja));
      bloque.appendChild(chip);
    }

    chip.innerHTML = '<i class="bi bi-pencil"></i>';
    chip.append(texto || SIN_ESPECIFICAR);
    chip.classList.toggle('sin-especificar', !texto);
    chip.title = texto
      ? 'Modificar esta respuesta'
      : 'Este detalle es opcional. Picalo para escribirlo.';
  }

  /*
     El boton de guardar de la ventana: escucha delegado sobre el
     documento, por lo mismo que lo anterior. Colgarlo del boton al
     cargar el archivo no funciona cuando la historia llega despues.
  */
  document.addEventListener('click', (ev) => {
    if (!ev.target.closest('#pxGuardar')) return;
    if (!preguntaEnCurso) return;

    const campo = preguntaEnCurso.querySelector('input, textarea');
    const valor = document.getElementById('pxValor').value.trim();

    /*
       Guardar en blanco esta permitido.

       Antes se rechazaba con un aviso, porque guardar en blanco
       equivalia a cancelar y la respuesta de arriba se deshacia. Ya no:
       el detalle es opcional, el "si" se queda y el complemento se
       ensenia como NO ESPECIFICA. Rechazarlo ahora obligaria a inventar
       un detalle para poder cerrar la ventana.
    */
    /*
       A mayusculas con toUpperCase() y no con App.aMayusculas().

       Esa funcion recibe un ELEMENTO, no un texto: mira su .type y su
       .classList para saber si esta exento —un correo o una contrasenia
       no se suben a mayusculas— y le escribe el valor conservando la
       posicion del cursor. Pasandole una cadena, el .classList revienta,
       el manejador muere ahi mismo y el boton Guardar deja de responder
       sin decir nada. Eso es lo que pasaba.

       Aqui no hace falta nada de eso: es un texto suelto que ya salio
       del cuadro.
    */
    campo.value = valor.toUpperCase();
    pintarRespuesta(preguntaEnCurso);
    modalPregunta()?.hide();
  });

  /**
   * Se cerro la ventana sin pasar por Guardar.
   *
   * Hasta octubre de 2026 esto DESHACIA la respuesta: el "si" volvia a
   * como estaba antes y el complemento se limpiaba, porque un "si" sin
   * su detalle era un dato a medias que no se queria guardar.
   *
   * Ahora el detalle es opcional, asi que no hay nada que deshacer: el
   * "si" es una respuesta completa por si sola y el complemento sale
   * como NO ESPECIFICA. Deshacerlo seria lo contrario de lo que se
   * pidio —borrar un antecedente clinico por no saber un detalle—.
   *
   * Lo unico que queda es pintar: la capsula tiene que aparecer con su
   * "NO ESPECIFICA" para que se vea que ahi hay un dato que se puede
   * llenar despues.
   */
  function alCerrarVentana() {
    const caja = preguntaEnCurso;
    preguntaEnCurso = null;
    if (!caja) return;

    pintarRespuesta(caja);
  }

  /**
   * Pone al dia UN bloque: esconde el almacen y pinta la capsula.
   *
   * Tenia un parametro "preguntar" que abria la ventanita cuando el
   * complemento faltaba. Se fue con la apertura automatica: ahora la
   * ventana la abre solo quien pica la capsula.
   */
  function atender(caja) {
    const control = document.querySelector(`[name="${caja.dataset.depende}"]`);
    if (!control) return;

    const valor = (control.value ?? '').trim();
    const campo = caja.querySelector('input, textarea');

    // El bloque nunca se ve en la rejilla: es el almacen del dato. Lo
    // que se ve es la respuesta pintada bajo el selector.
    caja.classList.add('d-none');

    if (!toca(caja, valor)) {
      // Al dejar de aplicar se limpia. Si no, cambiar un "si" por un
      // "no" dejaria guardado el detalle de la respuesta anterior, y el
      // expediente diria dos cosas contradictorias.
      if (campo) campo.value = '';
      pintarRespuesta(caja);
      return;
    }

    pintarRespuesta(caja);
  }

  /** Los doce, sin preguntar nada: al cargar y al prellenar. */
  function aplicarDependencias() {
    document.querySelectorAll('[data-depende]').forEach((caja) => atender(caja));
  }

  function engancharDependencias() {
    /*
       Un solo escucha para todo el formulario, en 'change'.

       Lo importante es el true: significa "esto lo acaba de contestar
       una persona", y es lo que autoriza a abrir la ventana. Las otras
       dos llamadas a aplicarDependencias —al cargar la historia y al
       prellenar— van sin argumento a proposito: abrir un expediente
       viejo no puede lanzar doce modales en cadena.
    */
    /*
       Este escucha YA NO pregunta nada: solo repinta.

       Preguntar desde aqui fue el error. 'change' salta igual cuando
       alguien pica una opcion que cuando el codigo rellena el
       formulario, asi que abrir un expediente ya capturado lanzaba las
       doce ventanas en cadena. Y como salta tambien al salir de
       cualquier campo de texto, escribir el motivo de la consulta y
       pasar al siguiente campo volvia a abrirlas: por eso tampoco se
       podia llegar a guardar.

       Quien pregunta ahora es elegir(), que sabe si lo llamo una
       persona.
    */
    document.getElementById('formHistoria')
      ?.addEventListener('change', () => aplicarDependencias());

    /*
       Los desplegables necesitan su propio escucha.

       "Momentos de azucar" no es un grupo de botones sino un <select>,
       asi que no pasa por elegir() y el 'change' del formulario de
       arriba repinta los doce bloques pero no se entera de cual se
       toco. Con esto, elegir una frecuencia pinta su capsula en el
       momento.

       Preguntaba —abria la ventanita— hasta el 17 de octubre de 2026.
       Ya no: ver el comentario de elegir(). Lo que queda es repintar,
       igual que los grupos de si/no.
    */
    document.querySelectorAll('[data-depende]').forEach((caja) => {
      const nombre = caja.dataset.depende;
      const control = document.querySelector(`[name="${nombre}"]`);
      if (!control || control.tagName !== 'SELECT') return;

      control.addEventListener('change', () => atender(caja));
    });

    aplicarDependencias();
  }

  function engancharCasillas() {
    document.querySelectorAll('.marca-clinica').forEach((casilla) => {
      casilla.addEventListener('change', () => {
        if (casilla.dataset.detalle !== '1') return;

        const detalle = document.querySelector(
          `.detalle-marca[data-de="${casilla.value}"]`);
        if (!detalle) return;

        detalle.classList.toggle('d-none', !casilla.checked);
        if (casilla.checked) detalle.focus();
        else detalle.value = '';
      });
    });
  }

  /* -------------------------------------------------------------------
     Carga
     ------------------------------------------------------------------- */
  async function cargar() {
    const form = document.getElementById('formHistoria');
    if (!form) return;

    try {
      const r = await App.api('historia/obtener.php', {
        id_registro_paciente: idExpediente,
      });
      llenar(r.datos);
    } catch (e) { /* el mensaje ya se mostro */ }
  }

  function llenar({ historia, marcas, claves, antecedentes }) {
    const form = document.getElementById('formHistoria');

    existeHistoria = !!historia;

    // Con historia ya capturada, el recorrido de pasos estorba: lo que
    // se quiere es ir directo a la parte que hay que corregir. Sin
    // historia, los pasos guian la captura de las dos hojas.
    pasos?.aplicarModo(existeHistoria ? 'colapsable' : 'pasos');

    document.getElementById('hcImprimir').classList.toggle('d-none', !existeHistoria);
    document.getElementById('hcPrellenar').classList.toggle('d-none', existeHistoria);
    document.getElementById('hcAvisoPendiente').classList.toggle('d-none', existeHistoria);

    // El aviso de "sin capturar" ya no vive en una solapa: la historia
    // es su propia pantalla desde octubre 2026, y quien avisa es la
    // tarjeta de la ficha del paciente, que lo dibuja en PHP.

    if (historia) {
      Object.entries(historia).forEach(([campo, valor]) => {
        if (valor === null) return;

        const el = form.querySelector(`[name="${campo}"]`);
        if (!el) return;

        if (el.type === 'hidden') {
          // Es un grupo de botones: se marca la opcion correspondiente.
          const grupo = el.closest('.grupo-sino');
          if (grupo) elegir(grupo, valor);
        } else {
          el.value = valor;
        }
      });

      const pie = document.getElementById('hcCaptura');
      if (pie) {
        const cuando = historia.fecha_modif || historia.fecha_captura;
        pie.innerHTML =
          `<i class="bi bi-clock-history me-1"></i>`
          + `Última actualización: <b>${App.esc(App.fechaHora(cuando))}</b>`
          + (historia.capturo ? ` por ${App.esc(historia.capturo)}` : '');
      }
    }

    /*
       Las dependencias, AL FINAL.

       Estaban al principio de esta funcion, con un comentario que decia
       "los valores acaban de llegar del servidor"... y corrian ANTES de
       escribirlos. Asi que se calculaban contra el formulario vacio, y
       despues nadie las rehacia: los que son grupo de botones se
       salvaban de casualidad, porque elegir() atiende su bloque al
       marcar la opcion, pero los demas —los <select> y los campos de
       texto— se quedaban sin pintar.

       Eso es lo que hacia que al abrir una historia ya guardada la
       respuesta de "momentos de azucar" no apareciera por ningun lado,
       aunque estuviera guardada.

       Ya no hace falta cuidar que esto no pregunte: desde el 17 de
       octubre de 2026 nada abre la ventanita sola. Lo unico que hace
       este recorrido es pintar las capsulas.
    */
    aplicarDependencias();

    // Casillas marcadas
    (claves ?? []).forEach((clave) => {
      const casilla = document.querySelector(`.marca-clinica[value="${clave}"]`);
      if (!casilla) return;

      casilla.checked = true;
      casilla.dispatchEvent(new Event('change'));

      const detalle = document.querySelector(`.detalle-marca[data-de="${clave}"]`);
      if (detalle && marcas[clave]) detalle.value = marcas[clave];
    });

    pintarAlertas(antecedentes ?? []);
  }

  /**
   * Los antecedentes que ya estan en el expediente se muestran arriba.
   * Una alergia a la anestesia capturada en otra pantalla tiene que
   * verse aqui: quien llena la historia no siempre es quien la capturo.
   */
  function pintarAlertas(lista) {
    const caja = document.getElementById('hcAlertas');
    if (!caja) return;

    const graves = lista.filter((a) => Number(a.requiere_alerta) === 1);
    caja.classList.toggle('d-none', graves.length === 0);

    if (!graves.length) return;

    document.getElementById('hcAlertasLista').innerHTML = graves.map((a) => `
      <div>· <b>${App.esc(a.nombre_antecedente)}</b>
        <span style="opacity:.8">(${App.esc(a.nombre_tipo)})</span>
        ${a.detalle ? ' — ' + App.esc(a.detalle) : ''}
      </div>`).join('');
  }

  /* -------------------------------------------------------------------
     Guardado
     ------------------------------------------------------------------- */
  async function guardar(ev) {
    ev.preventDefault();
    const form = document.getElementById('formHistoria');

    const datos = App.formADatos(form);
    datos.id_registro_paciente = idExpediente;

    // Las casillas viajan en un solo campo: CLAVE:detalle|CLAVE|...
    // Mandar cincuenta parametros sueltos haria el endpoint ilegible.
    const marcas = [...document.querySelectorAll('.marca-clinica:checked')]
      .map((c) => {
        const detalle = document.querySelector(`.detalle-marca[data-de="${c.value}"]`);
        const texto = detalle?.value.trim();
        return texto ? `${c.value}:${texto}` : c.value;
      });

    datos.marcas = marcas.join('|');

    try {
      await App.api('historia/guardar.php', datos);

      // Ya capturada, la pantalla cambia a bloques colapsables y se
      // habilita la impresion para recoger las firmas.
      const eraNueva = !existeHistoria;
      existeHistoria = true;
      pasos?.aplicarModo('colapsable');
      document.getElementById('hcImprimir').classList.remove('d-none');
      document.getElementById('hcPrellenar')?.classList.add('d-none');

      // El aviso de la pestania se apaga AHORA, no al recargar.
      //
      // La pestania seguia marcada en naranja sobre una historia ya
      // capturada hasta que alguien recargaba la pagina. Un aviso que
      // miente es peor que no tenerlo: ensenia a ignorar los avisos, y
      // el siguiente que sea de verdad tambien se va a ignorar.
      document.getElementById('avisoTabHistoria')?.classList.add('d-none');

      if (eraNueva) {
        const r = await App.alerta.confirmar(
          'Historia clínica guardada',
          '¿Quieres imprimirla ahora para recoger las firmas del tutor y del doctor?',
          'Sí, imprimir');
        if (r) imprimir();
      } else {
        App.alerta.toast('Historia clínica actualizada');
      }

      // La historia clinica es uno de los pendientes de digitalizacion:
      // se refresca el expediente por si se completo.
      if (typeof Expediente !== 'undefined') Expediente.cargar();
    } catch (e) {
      App.marcarErrores(form, e.errores);
    }
  }

  /* -------------------------------------------------------------------
     Impresion

     Antes de imprimir hay que saber quien firma: la hoja lleva el
     nombre y la cedula del doctor que atendio, y no siempre es quien
     capturo la historia en la computadora.
     ------------------------------------------------------------------- */
  async function imprimir() {
    let dentistas = [];
    try {
      const r = await App.api('catalogo/dentistas.php', {});
      dentistas = r.datos;
    } catch (e) { return; }

    const abrir = (id) => App.abrirVentana(
      `${App.URL_BASE}/historia_imprimir.php?id=${idExpediente}`
      + (id ? `&dentista=${id}` : ''),
      880, 1020, 'historia');

    // Con un solo dentista no hay nada que preguntar.
    if (dentistas.length <= 1) {
      abrir(dentistas[0]?.id_usuario);
      return;
    }

    const opciones = {};
    dentistas.forEach((d) => {
      opciones[d.id_usuario] = d.cedula_prof
        ? `${d.nombre_completo} — Ced. ${d.cedula_prof}`
        : d.nombre_completo;
    });

    const r = await Swal.fire({
      icon: 'question',
      title: '¿Quién atiende al paciente?',
      text: 'Su nombre y cédula saldrán impresos en la hoja para firma.',
      input: 'select',
      inputOptions: opciones,
      inputValue: String(dentistas[0].id_usuario),
      showCancelButton: true,
      confirmButtonText: 'Imprimir',
      confirmButtonColor: '#7b2d8e',
      cancelButtonText: 'Cancelar',
      cancelButtonColor: '#6c6577',
    });

    if (r.isConfirmed) abrir(r.value);
  }

  /* -------------------------------------------------------------------
     Arranque
     ------------------------------------------------------------------- */
  function iniciar(expediente) {
    idExpediente = expediente;

    const form = document.getElementById('formHistoria');
    if (!form) return;

    engancharGrupos();
    engancharCasillas();
    engancharDependencias();
    form.addEventListener('submit', guardar);

    pasos = Pasos.crear({
      contenedor: 'pasosHistoria',
      modo: 'pasos',
      textoFinal: 'Guardar historia clínica',
      alTerminar: () => form.requestSubmit(),

      /**
       * Solo se exige lo minimo para que la historia sirva: el motivo de
       * la consulta. Lo demas puede quedar en blanco porque muchas veces
       * el tutor no lo recuerda en ese momento, y bloquear la captura
       * por eso llevaria a inventar datos.
       */
      validar(indice) {
        if (indice === 0) {
          const motivo = form.querySelector('[name="motivo_consulta"]').value.trim();
          if (!motivo) return 'Captura el motivo de la consulta.';
        }

        /*
           Y nada mas.

           Aqui se exigia que ningun complemento quedara a medias: un
           "¿alergico? SI" sin decir a que no dejaba pasar de paso, se
           llevaba la vista hasta la pregunta y abria su ventana.

           Se quito el 16 de octubre de 2026. La doctora lo pidio y la
           razon es de consultorio: el tutor muchas veces no sabe el
           detalle en ese momento, y la traba lo dejaba elegir entre
           inventarlo o cambiar el "si" por un "no". La segunda salida es
           la peor de todas —borra un antecedente clinico para poder
           avanzar— y es la que uno elige con el paciente esperando.

           El dato no se pierde de vista: el complemento vacio se ensenia
           como una capsula que dice NO ESPECIFICA, se pica y se llena
           cuando se sepa, y en la hoja impresa sale ese mismo texto en
           vez de un renglon en blanco.
        */
        return true;
      },
    });

    document.getElementById('hcImprimir').addEventListener('click', imprimir);

    // Los dos botones de prellenado hacen lo mismo: uno vive en el
    // encabezado y el otro dentro del aviso, que es donde la mirada
    // esta cuando la historia no existe.
    ['hcPrellenar', 'hcPrellenarAviso'].forEach((id) => {
      document.getElementById(id)?.addEventListener('click', () => {
        const puestos = prellenar(true);

        if (!puestos) {
          App.alerta.info('Nada que prellenar',
            'Todas las preguntas con valor habitual ya estan contestadas.');
          return;
        }

        aplicarDependencias();
        App.alerta.toast(`${puestos} respuesta(s) prellenadas. Revisa y corrige.`);
        document.getElementById('hcAvisoPendiente').classList.add('d-none');
      });
    });

    document.getElementById('hcEmpezar')?.addEventListener('click', () => {
      document.getElementById('hcAvisoPendiente').classList.add('d-none');
      document.querySelector('[name="motivo_consulta"]')?.focus();
    });

    // Como pestania se carga al abrirla, no antes: son varias
    // consultas y la mayoria de las veces quien entra al paciente va a
    // otra cosa. Como pantalla propia, de entrada.
    const solapa = document.getElementById('btnTabHistoria');
    if (solapa) {
      solapa.addEventListener('shown.bs.tab', () => {
        if (!cargado) { cargado = true; cargar(); }
      });
      if (location.hash === '#tab-historia') { cargado = true; cargar(); }
    } else {
      cargado = true;
      cargar();
    }
  }

  return { iniciar, cargar };
})();
