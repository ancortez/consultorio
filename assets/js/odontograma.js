/* =====================================================================
   Odontograma del paciente
   =====================================================================
   El estado de cada diente HOY: sano, amalgama, ausente. Se pican uno o
   varios dientes y se les pone su condicion con un clic.

   De varios en varios porque es como se dicta: "del 14 al 17,
   amalgama". Uno por uno serian cuatro guardados para lo mismo, con el
   paciente en el sillon.

   El dibujo lo pinta PHP (Odontograma, en el nucleo) y es el mismo que
   usa el selector de organo dentario del plan. Aqui solo vive lo que
   pasa al picar.
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  const caja = document.getElementById('odoPaciente');
  if (!caja) return;

  const idExpediente = caja.dataset.expediente;
  const puedeEditar  = caja.dataset.puede === '1';

  const aviso   = document.getElementById('odoAviso');
  const leyenda = document.getElementById('odoLeyenda');

  /* -------------------------------------------------------------------
     Elegir dientes
     ------------------------------------------------------------------- */
  /*
     La seleccion se lee del DOM, no se guarda en una variable.

     Tenerla aparte obliga a mantener dos cosas al dia —la clase del
     diente y la lista— y en cuanto la pantalla se repinta despues de
     guardar, la lista queda hablando de botones que ya no existen. El
     DOM es la unica copia que no se puede desincronizar de si misma.
  */
  const elegidos = () => [...caja.querySelectorAll('.diente.elegido')]
    .map((b) => Number(b.dataset.diente));

  function refrescarAviso() {
    if (!aviso) return;

    const cuantos = elegidos().length;
    aviso.textContent = cuantos === 0
      ? 'Pica los dientes que quieras marcar.'
      : `${cuantos} diente(s) elegido(s): pica la condición que les toca.`;
    aviso.classList.toggle('listo', cuantos > 0);
  }

  caja.addEventListener('click', (ev) => {
    const diente = ev.target.closest('.diente');
    if (!diente) return;

    ev.preventDefault();
    if (!puedeEditar) return;

    // Volver a picarlo lo desmarca: es la unica forma de corregir sin
    // tener que empezar la seleccion de nuevo.
    diente.classList.toggle('elegido');
    refrescarAviso();
  });

  /* -------------------------------------------------------------------
     Marcar la condicion
     ------------------------------------------------------------------- */
  document.getElementById('odoPaleta')?.addEventListener('click', async (ev) => {
    const boton = ev.target.closest('[data-condicion]');
    if (!boton) return;

    const dientes = elegidos();

    if (!dientes.length) {
      App.alerta.info('Primero elige los dientes',
        'Pica en el dibujo los órganos dentarios que quieras marcar. '
        + 'Puedes elegir varios.');
      return;
    }

    const idCondicion = Number(boton.dataset.condicion);

    /*
       Quitar la marca se confirma; marcar no.

       Marcar se corrige volviendo a marcar, asi que preguntar seria
       estorbar en la operacion que se hace veinte veces. Quitar borra
       lo que alguien capturo y no deja rastro de que era, asi que ahi
       la pregunta vale.
    */
    if (idCondicion === 0) {
      const ok = await App.alerta.confirmar(
        `¿Quitar la marca de ${dientes.length} diente(s)?`,
        'Quedarán sin nada registrado. No es lo mismo que marcarlos '
        + 'como sanos: "sano" dice que se revisaron y están bien.',
        'Sí, quitarla');
      if (!ok) return;
    }

    // Los botones se apagan mientras va el viaje: picar dos condiciones
    // seguidas mandaria dos guardados sobre los mismos dientes y el
    // segundo pisaria al primero sin que nadie sepa cual gano.
    const botones = [...document.querySelectorAll('#odoPaleta [data-condicion]')];
    botones.forEach((b) => { b.disabled = true; });

    try {
      const r = await App.api('paciente/odontograma_guardar.php', {
        id_registro_paciente: idExpediente,
        id_condicion: idCondicion,
        dientes: dientes.join(','),
      });

      pintarEstado(r.datos.estado, r.datos.condiciones);

      const campoFecha = document.getElementById('odoFecha');
      if (campoFecha) {
        campoFecha.textContent = `Actualizado el ${App.fecha(r.datos.fecha)}`;
      }

      App.alerta.toast(r.mensaje ?? 'Odontograma actualizado');
    } catch (e) {
      /* ya se mostro */
    } finally {
      botones.forEach((b) => { b.disabled = false; });
    }
  });

  /* -------------------------------------------------------------------
     Pintar
     ------------------------------------------------------------------- */
  /**
   * Pone el color de cada diente y rehace la leyenda.
   *
   * Se repinta en vez de recargar la pagina: recargar perderia la
   * pestania en la que esta el usuario y el desplazamiento, y volver a
   * encontrar el 46 despues de cada marca es justo el trabajo que esta
   * pantalla viene a quitar.
   */
  function pintarEstado(estado, condiciones) {
    estado = estado ?? {};

    caja.querySelectorAll('.diente').forEach((boton) => {
      const suyo = estado[boton.dataset.diente];

      // La seleccion se suelta al guardar: lo que se eligio ya se
      // marco, y dejarla puesta hace que la siguiente condicion caiga
      // encima de los mismos dientes sin que nadie lo pida.
      boton.classList.remove('elegido');

      if (!suyo) {
        boton.classList.remove('con-estado');
        boton.style.removeProperty('--estado');
        boton.removeAttribute('data-condicion');
        boton.title = boton.title.split(' · ')[0];
        return;
      }

      boton.classList.add('con-estado');
      boton.style.setProperty('--estado', suyo.color);
      boton.dataset.condicion = suyo.id_condicion;
      boton.title = `${boton.title.split(' · ')[0]} · ${suyo.nombre_condicion}`;
    });

    refrescarAviso();
    pintarLeyenda(estado, condiciones);
  }

  /* Solo las condiciones que el paciente TIENE.

     Con las trece del catalogo la leyenda ocuparia mas que la dentadura
     y la mayoria no estaria en el dibujo: una leyenda que nombra cosas
     que no se ven no ayuda a leer, estorba. */
  function pintarLeyenda(estado, condiciones) {
    if (!leyenda) return;

    const usadas = new Set(Object.values(estado ?? {})
      .map((e) => Number(e.id_condicion)));

    const filas = (condiciones ?? [])
      .filter((c) => usadas.has(Number(c.id_condicion)));

    leyenda.innerHTML = filas.length
      ? '<span class="text-secondary">Estado actual:</span>'
        + filas.map((c) => `
            <span class="chip-condicion">
              <i style="background:${App.esc(c.color ?? '#999')}"></i>
              ${App.esc(c.nombre_condicion)}
            </span>`).join('')
      : '<span class="text-secondary">Todavía no se ha marcado ningún '
        + 'órgano dentario.</span>';
  }

  /* -------------------------------------------------------------------
     Arranque
     ------------------------------------------------------------------- */
  /*
     La leyenda se arma al abrir leyendo el DOM, no pidiendo los datos.

     El dibujo ya viene pintado desde PHP: los colores y las condiciones
     estan en los atributos de cada diente. Pedirlos otra vez al
     servidor seria un viaje para enterarnos de lo que ya tenemos
     enfrente, y la pestania tardaria en verse completa.
  */
  const delDibujo = {};
  caja.querySelectorAll('.diente.con-estado').forEach((b) => {
    delDibujo[b.dataset.diente] = {
      id_condicion: Number(b.dataset.condicion),
      nombre_condicion: (b.title.split(' · ')[1] ?? '').trim(),
      color: b.style.getPropertyValue('--estado').trim() || '#999',
    };
  });

  const condicionesDelDibujo = Object.values(delDibujo)
    .filter((c, i, lista) =>
      lista.findIndex((o) => o.id_condicion === c.id_condicion) === i);

  pintarLeyenda(delDibujo, condicionesDelDibujo);
  refrescarAviso();
});
