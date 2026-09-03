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
      if (pie && historia.capturo) {
        pie.textContent =
          `Última captura: ${App.fechaHora(historia.fecha_modif || historia.fecha_captura)}`
          + ` por ${historia.capturo}`;
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
        if (r) {
          App.abrirVentana(
            `${App.URL_BASE}/historia_imprimir.php?id=${idExpediente}`, 880, 1020, 'historia');
        }
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

    document.getElementById('hcImprimir').addEventListener('click', () => {
      App.abrirVentana(
        `${App.URL_BASE}/historia_imprimir.php?id=${idExpediente}`, 880, 1020, 'historia');
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
