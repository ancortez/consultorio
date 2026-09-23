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
    labios: 1,              // Competentes
    frenillos: 1,           // Normales
    lengua: 1,              // Normal
    tipo_denticion: 2,      // Mixta
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

    return puestos;
  }

  /* -------------------------------------------------------------------
     Grupos de si/no y de opciones
     ------------------------------------------------------------------- */

  /** Marca una opcion dentro de su grupo y escribe el valor oculto. */
  function elegir(grupo, valor) {
    const oculto = grupo.querySelector('input[type="hidden"]');

    grupo.querySelectorAll('.opcion-sino').forEach((b) => {
      b.classList.toggle('activa', b.dataset.valor === String(valor));
    });

    oculto.value = valor === null ? '' : valor;
    oculto.dispatchEvent(new Event('change', { bubbles: true }));
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
        elegir(grupo, yaEstaba ? null : boton.dataset.valor);
      });
    });
  }

  /* -------------------------------------------------------------------
     Casillas con detalle
     ------------------------------------------------------------------- */
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

    // La pestania tambien avisa, para que se note sin entrar.
    const solapa = document.querySelector('[data-bs-target="#tab-historia"]');
    solapa?.classList.toggle('con-pendientes', !existeHistoria);
    if (solapa) {
      solapa.title = existeHistoria
        ? 'Historia clinica capturada'
        : 'Falta capturar la historia clinica';
    }

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

        App.alerta.toast(`${puestos} respuesta(s) prellenadas. Revisa y corrige.`);
        document.getElementById('hcAvisoPendiente').classList.add('d-none');
      });
    });

    document.getElementById('hcEmpezar')?.addEventListener('click', () => {
      document.getElementById('hcAvisoPendiente').classList.add('d-none');
      document.querySelector('[name="motivo_consulta"]')?.focus();
    });

    // Se carga al abrir la pestania, no antes: son varias consultas y
    // la mayoria de las veces el usuario entra a otra cosa.
    document.querySelector('[data-bs-target="#tab-historia"]')
      ?.addEventListener('shown.bs.tab', () => {
        if (!cargado) { cargado = true; cargar(); }
      });

    if (location.hash === '#tab-historia') {
      cargado = true;
      cargar();
    }
  }

  return { iniciar, cargar };
})();
