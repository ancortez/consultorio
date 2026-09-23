/* =====================================================================
   Modulo Cajas

   Tres pantallas que comparten catalogos y reglas:
     - cobrar.php      cobro con pago mixto
     - corte.php       turnos, movimientos y cierre
     - pendientes.php  a quien hay que cobrarle

   Los catalogos (cajas, formas de pago, conceptos y permisos) se piden
   una sola vez y se guardan aqui: los usan las tres y pedirlos por
   pantalla seria tres viajes para lo mismo.
   ===================================================================== */

const Cat = {
  cajas: [], formas: [], conceptos: [], permisos: {},

  async cargar() {
    if (this.formas.length) return this;
    const r = await App.api('cajas/catalogos.php', {});
    Object.assign(this, r.datos);
    return this;
  },

  forma(id) {
    return this.formas.find((f) => Number(f.id_forma_pago) === Number(id));
  },
};

document.addEventListener('DOMContentLoaded', async () => {
  const hayCaja = document.getElementById('cajasEstado')
    || document.getElementById('cbCaja')
    || document.getElementById('tablaPendientes');
  if (!hayCaja) return;

  await Cat.cargar();

  pantallaCorte();
  pantallaCobrar();
  pantallaPendientes();
});

/* =====================================================================
   Modales compartidos: abrir turno, cerrar turno, egreso
   ===================================================================== */
const Turno = (() => {

  function modal(id) {
    const caja = document.getElementById(id);
    return caja ? bootstrap.Modal.getOrCreateInstance(caja) : null;
  }

  /** Abre el dialogo para iniciar el turno de una caja. */
  function abrir(idCaja, nombre, alTerminar) {
    const m = modal('modalAbrirTurno');
    if (!m) return;

    document.getElementById('abrirIdCaja').value = idCaja;
    document.getElementById('abrirNombreCaja').textContent = nombre ?? 'la caja';
    document.getElementById('abrirFondo').value = '0.00';

    const form = document.getElementById('formAbrirTurno');
    form.onsubmit = async (ev) => {
      ev.preventDefault();
      try {
        const r = await App.api('cajas/turno_abrir.php', App.formADatos(form));
        m.hide();
        App.alerta.toast('Turno abierto');
        alTerminar?.(r.datos.id_corte_caja);
      } catch (e) {
        App.marcarErrores(form, e.errores);
      }
    };

    m.show();
  }

  /**
   * Cierra el turno.
   *
   * La diferencia se enseña despues de capturar lo contado, no antes:
   * si el sistema dice primero cuanto deberia haber, el conteo se
   * acomoda al numero y el corte deja de servir para nada.
   */
  function cerrar(resumen, alTerminar) {
    const m = modal('modalCerrarTurno');
    if (!m) return;

    document.getElementById('cerrarIdCorte').value = resumen.id_corte_caja;
    document.getElementById('cerrarContado').value = '';
    document.getElementById('cerrarComparativo').classList.add('d-none');

    const esperado = Number(resumen.efectivo_esperado);

    document.getElementById('cerrarContado').oninput = (ev) => {
      const contado = Number(ev.target.value);
      if (ev.target.value === '') {
        document.getElementById('cerrarComparativo').classList.add('d-none');
        return;
      }

      const dif = Math.round((contado - esperado) * 100) / 100;
      document.getElementById('cerrarComparativo').classList.remove('d-none');
      document.getElementById('cerrarEsperado').textContent = App.money(esperado);

      const caja = document.getElementById('cerrarDiferencia');
      caja.textContent = (dif > 0 ? '+' : '') + App.money(dif);
      caja.style.color = Math.abs(dif) < 0.01
        ? 'var(--exito)' : (dif < 0 ? 'var(--error)' : 'var(--alerta)');
    };

    const form = document.getElementById('formCerrarTurno');
    form.onsubmit = async (ev) => {
      ev.preventDefault();
      try {
        const r = await App.api('cajas/turno_cerrar.php', App.formADatos(form));
        m.hide();

        const dif = Number(r.datos.diferencia);
        await Swal.fire({
          icon: Math.abs(dif) < 0.01 ? 'success' : 'warning',
          title: 'Turno cerrado',
          html: `<div style="font-size:14px;line-height:1.8;text-align:left">
                   Esperado: <b>${App.money(r.datos.esperado)}</b><br>
                   Contado: <b>${App.money(r.datos.contado)}</b><br>
                   Diferencia:
                   <b style="color:${Math.abs(dif) < 0.01 ? '#2e9e6b' : '#c0392b'}">
                     ${App.money(dif)}
                   </b>
                 </div>`,
          confirmButtonText: 'Imprimir corte',
          confirmButtonColor: '#7b2d8e',
          showCancelButton: true,
          cancelButtonText: 'Después',
          cancelButtonColor: '#6c6577',
          reverseButtons: true,
        }).then((d) => {
          if (d.isConfirmed) imprimirCorte(resumen.id_corte_caja);
        });

        alTerminar?.();
      } catch (e) {
        App.marcarErrores(form, e.errores);
      }
    };

    m.show();
  }

  /** Salida de dinero del turno. */
  function egreso(idCorte, alTerminar) {
    const m = modal('modalEgreso');
    if (!m) return;

    const form = document.getElementById('formEgreso');
    form.reset();
    document.getElementById('egresoIdCorte').value = idCorte;

    // Solo conceptos de egreso: un "Cobro a paciente" aqui seria un
    // ingreso disfrazado de gasto.
    document.getElementById('egresoConcepto').innerHTML =
      '<option value="">Seleccionar...</option>'
      + Cat.conceptos.filter((c) => c.naturaleza === 'E')
          .map((c) => `<option value="${c.id_concepto}">
                         ${App.esc(c.nombre_concepto)}</option>`).join('');

    document.getElementById('egresoForma').innerHTML =
      Cat.formas.map((f) => `<option value="${f.id_forma_pago}">
                               ${App.esc(f.nombre_forma)}</option>`).join('');

    form.onsubmit = async (ev) => {
      ev.preventDefault();
      try {
        await App.api('cajas/egreso_guardar.php', App.formADatos(form));
        m.hide();
        App.alerta.toast('Salida registrada');
        alTerminar?.();
      } catch (e) {
        App.marcarErrores(form, e.errores);
      }
    };

    m.show();
  }

  return { abrir, cerrar, egreso };
})();

function imprimirCorte(idCorte) {
  App.abrirVentana(`${App.URL_BASE}/corte_imprimir.php?id=${idCorte}`,
    880, 1020, 'corte');
}

/* =====================================================================
   Pantalla: turnos y corte
   ===================================================================== */
function pantallaCorte() {
  const caja = document.getElementById('cajasEstado');
  if (!caja) return;

  let turnoActual = null;

  async function cargar() {
    const r = await App.api('cajas/turno_estado.php', {});
    pintarCajas(r.datos);

    // Si hay una sola caja abierta, se muestra su detalle de una vez:
    // es el caso normal y ahorra un clic cada vez.
    const abiertas = r.datos.filter((c) => c.id_corte_caja !== null);
    if (abiertas.length === 1) verTurno(abiertas[0].id_corte_caja);
    else if (turnoActual) verTurno(turnoActual);
  }

  function pintarCajas(cajas) {
    caja.innerHTML = cajas.map((c) => {
      const abierta = c.id_corte_caja !== null;

      return `
      <div class="col-4">
        <div class="caja-tarjeta ${abierta ? 'abierta' : ''}">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="caja-icono">
              <i class="bi bi-${abierta ? 'unlock' : 'lock'}"></i>
            </span>
            <div class="flex-grow-1">
              <div class="fw-semibold">${App.esc(c.nombre_caja)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${abierta
                  ? 'Abierta por ' + App.esc(c.abrio)
                  : 'Sin turno abierto'}
              </div>
            </div>
          </div>

          ${abierta ? `
            <div class="caja-cifras">
              <div>
                <span class="et">Entradas</span>
                <span class="va">${App.money(c.total_ingresos)}</span>
              </div>
              <div>
                <span class="et">Salidas</span>
                <span class="va">${App.money(c.total_egresos)}</span>
              </div>
              <div>
                <span class="et">Efectivo</span>
                <span class="va fw-semibold">${App.money(c.efectivo_esperado)}</span>
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-morado w-100 mt-2"
                    data-ver-turno="${c.id_corte_caja}">
              Ver movimientos
            </button>`
          : (Cat.permisos.corte ? `
            <button type="button" class="btn btn-sm btn-morado w-100 mt-2"
                    data-abrir-caja="${c.id_caja}"
                    data-nombre="${App.esc(c.nombre_caja)}">
              <i class="bi bi-unlock me-1"></i> Abrir turno
            </button>` : '')}
        </div>
      </div>`;
    }).join('');
  }

  async function verTurno(idCorte) {
    turnoActual = idCorte;

    const r = await App.api('cajas/turno_estado.php', { id_corte_caja: idCorte });
    const t = r.datos;

    document.getElementById('turnoDetalle').classList.remove('d-none');
    document.getElementById('turnoNombre').textContent =
      `${t.nombre_caja} — abierta ${App.fechaHora(t.fecha_apertura)}`;

    // El desglose por forma de pago es lo que hace util el corte: el
    // total del dia no dice cuanto hay que encontrar en el cajon.
    document.getElementById('turnoTotales').innerHTML = `
      <div class="col-3">
        <div class="cifra-corte">
          <span class="et">Fondo inicial</span>
          <span class="va">${App.money(t.monto_inicial)}</span>
        </div>
      </div>
      <div class="col-3">
        <div class="cifra-corte">
          <span class="et">Entradas</span>
          <span class="va" style="color:var(--exito)">${App.money(t.total_ingresos)}</span>
        </div>
      </div>
      <div class="col-3">
        <div class="cifra-corte">
          <span class="et">Salidas</span>
          <span class="va" style="color:var(--error)">${App.money(t.total_egresos)}</span>
        </div>
      </div>
      <div class="col-3">
        <div class="cifra-corte destacada">
          <span class="et">Efectivo en caja</span>
          <span class="va">${App.money(t.efectivo_esperado)}</span>
        </div>
      </div>
      <div class="col-12">
        <div class="desglose-formas">
          ${t.formas.length
            ? t.formas.map((f) => `
                <div class="forma">
                  <span class="nombre">${App.esc(f.nombre_forma)}</span>
                  <span class="monto">${App.money(f.neto)}</span>
                </div>`).join('')
            : '<span class="text-secondary" style="font-size:12px">Sin movimientos.</span>'}
        </div>
      </div>`;

    document.getElementById('turnoMovimientos').innerHTML = t.movimientos.length
      ? t.movimientos.map((m) => `
          <tr>
            <td class="text-secondary">${App.esc(String(m.fecha_hora).substring(11, 16))}</td>
            <td>${App.esc(m.nombre_concepto)}</td>
            <td class="text-secondary" style="font-size:12.5px">
              ${App.esc(m.descripcion ?? '')}
              ${m.paciente ? `<div style="font-size:11px">${App.esc(m.paciente)}</div>` : ''}
            </td>
            <td class="text-secondary">${App.esc(m.nombre_forma)}</td>
            <td class="text-end fw-semibold"
                style="color:${m.naturaleza === 'I' ? 'var(--exito)' : 'var(--error)'}">
              ${m.naturaleza === 'I' ? '' : '−'}${App.money(m.monto)}
            </td>
            <td class="text-secondary" style="font-size:11.5px">${App.esc(m.registro)}</td>
          </tr>`).join('')
      : `<tr class="fila-vacia"><td colspan="6">
           Todavía no hay movimientos en este turno.</td></tr>`;

    // Un turno ya cerrado se puede consultar e imprimir, pero no tocar.
    const abierto = Number(t.abierto) === 1;
    ['btnCerrarTurno', 'btnEgreso'].forEach((id) => {
      const b = document.getElementById(id);
      if (b) b.classList.toggle('d-none', !abierto);
    });

    document.getElementById('btnCerrarTurno')?.setAttribute(
      'data-corte', String(idCorte));
    document.getElementById('btnEgreso')?.setAttribute(
      'data-corte', String(idCorte));

    document.getElementById('btnImprimirCorte').onclick = () => imprimirCorte(idCorte);
    document.getElementById('btnEgreso') &&
      (document.getElementById('btnEgreso').onclick = () => Turno.egreso(idCorte, cargar));
    document.getElementById('btnCerrarTurno') &&
      (document.getElementById('btnCerrarTurno').onclick = () => Turno.cerrar(t, cargar));
  }

  caja.addEventListener('click', (ev) => {
    const abrir = ev.target.closest('[data-abrir-caja]');
    if (abrir) {
      Turno.abrir(abrir.dataset.abrirCaja, abrir.dataset.nombre, cargar);
      return;
    }

    const ver = ev.target.closest('[data-ver-turno]');
    if (ver) verTurno(Number(ver.dataset.verTurno));
  });

  /* ---------- Turnos ya cerrados ---------- */
  (async () => {
    const r = await App.api('cajas/turnos_listar.php', {});
    const cerrados = r.datos.filter((t) => Number(t.abierto) === 0);

    document.getElementById('turnosCerrados').innerHTML = cerrados.length
      ? cerrados.map((t) => {
          const dif = Number(t.diferencia ?? 0);
          return `
          <tr>
            <td class="fw-semibold">${App.esc(t.nombre_caja)}</td>
            <td>
              <div>${App.esc(App.fechaHora(t.fecha_apertura))}</div>
              <div class="text-secondary" style="font-size:11px">${App.esc(t.abrio)}</div>
            </td>
            <td>
              <div>${App.esc(App.fechaHora(t.fecha_cierre))}</div>
              <div class="text-secondary" style="font-size:11px">${App.esc(t.cerro ?? '')}</div>
            </td>
            <td class="text-end">${App.money(t.total_ingresos)}</td>
            <td class="text-end">${App.money(t.total_egresos)}</td>
            <td class="text-end fw-semibold"
                style="color:${Math.abs(dif) < 0.01 ? 'var(--exito)' : 'var(--error)'}">
              ${App.money(dif)}
            </td>
            <td class="col-acciones">
              <button type="button" class="btn-menu-fila"
                      data-ver-turno="${t.id_corte_caja}" title="Ver">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>`;
        }).join('')
      : '<tr class="fila-vacia"><td colspan="7">Sin turnos cerrados.</td></tr>';

    document.getElementById('turnosCerrados').addEventListener('click', (ev) => {
      const ver = ev.target.closest('[data-ver-turno]');
      if (ver) {
        verTurno(Number(ver.dataset.verTurno));
        document.getElementById('turnoDetalle')
          .scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  })();

  cargar();
}

/* =====================================================================
   Pantalla: pendientes de pago
   ===================================================================== */
function pantallaPendientes() {
  const tabla = document.getElementById('tablaPendientes');
  if (!tabla) return;

  (async () => {
    const r = await App.api('cajas/cargos_listar.php', {});
    const lista = r.datos;

    const total = lista.reduce((t, p) => t + Number(p.saldo), 0);
    document.getElementById('pdResumen').innerHTML = lista.length
      ? `${lista.length} paciente(s) · <b style="color:var(--error)">
           ${App.money(total)}</b> por cobrar`
      : '';

    tabla.innerHTML = lista.length
      ? lista.map((p, i) => `
          <tr>
            <td class="text-secondary">${i + 1}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <a href="${App.URL_BASE}/index.php?modulo=paciente&vista=detalle_paciente&id=${p.id_registro_paciente}"
                   class="avatar-mini">
                  ${p.foto
                    ? `<img src="${App.URL_BASE}/avatar.php?p=${p.id_persona}" alt="">`
                    : '<i class="bi bi-person-fill"></i>'}
                </a>
                <div>
                  <a class="enlace-paciente"
                     href="${App.URL_BASE}/index.php?modulo=paciente&vista=detalle_paciente&id=${p.id_registro_paciente}">
                    ${App.esc(p.paciente)}
                  </a>
                  <div class="text-secondary" style="font-size:11px">
                    ${App.esc(p.num_expediente)}
                  </div>
                </div>
              </div>
            </td>
            <td>${p.telefono ? App.esc(p.telefono) : '—'}</td>
            <td>
              <div>${App.esc(App.fecha(p.adeudo_mas_antiguo))}</div>
              ${Number(p.dias) > 30 ? `
                <div style="font-size:11px;color:var(--error)">
                  hace ${p.dias} días</div>` : `
                <div class="text-secondary" style="font-size:11px">
                  hace ${p.dias} día(s)</div>`}
            </td>
            <td class="text-end text-secondary">${App.money(p.cargado)}</td>
            <td class="text-end text-secondary">${App.money(p.pagado)}</td>
            <td class="text-end fw-semibold" style="color:var(--error)">
              ${App.money(p.saldo)}
            </td>
            <td class="col-acciones">
              <a class="btn btn-sm btn-morado"
                 href="${App.URL_BASE}/index.php?modulo=cajas&vista=cobrar&paciente=${p.id_registro_paciente}">
                Cobrar
              </a>
            </td>
          </tr>`).join('')
      : `<tr class="fila-vacia"><td colspan="8">
           <i class="bi bi-check2-circle fs-3 d-block mb-2" style="color:var(--exito)"></i>
           Nadie debe nada. Todo al corriente.</td></tr>`;
  })();
}

/* =====================================================================
   Pantalla: cobrar
   ===================================================================== */
function pantallaCobrar() {
  const selCaja = document.getElementById('cbCaja');
  if (!selCaja) return;

  let turno    = null;   // el turno abierto de la caja elegida
  let cuenta   = null;   // paciente + cargos + planes
  let formas   = [];     // con que esta pagando

  /* -------------------------------------------------------------------
     Turno
     ------------------------------------------------------------------- */
  selCaja.innerHTML = Cat.cajas.map((c) =>
    `<option value="${c.id_caja}">${App.esc(c.nombre_caja)}</option>`).join('');

  async function revisarTurno() {
    const r = await App.api('cajas/turno_estado.php', {});
    const caja = r.datos.find(
      (c) => String(c.id_caja) === selCaja.value);

    turno = caja && caja.id_corte_caja !== null ? caja : null;

    document.getElementById('cbSinTurno').classList.toggle('d-none', turno !== null);
    document.getElementById('cbTurnoTexto').innerHTML = turno
      ? `Turno abierto por <b>${App.esc(turno.abrio)}</b> desde las `
        + `${App.esc(String(turno.fecha_apertura).substring(11, 16))}`
      : 'Sin turno abierto en esta caja';

    actualizarBoton();
    if (turno) cargarUltimos();
  }

  selCaja.addEventListener('change', revisarTurno);

  document.getElementById('cbAbrirTurno')?.addEventListener('click', () => {
    const caja = Cat.cajas.find((c) => String(c.id_caja) === selCaja.value);
    Turno.abrir(selCaja.value, caja?.nombre_caja, revisarTurno);
  });

  /* -------------------------------------------------------------------
     Paciente
     ------------------------------------------------------------------- */
  const buscar    = document.getElementById('cbBuscar');
  const resultados = document.getElementById('cbResultados');

  buscar.addEventListener('input', App.debounce(async () => {
    const q = buscar.value.trim();
    if (q.length < 2) { resultados.classList.add('d-none'); return; }

    const r = await App.api('paciente/listar.php', { q, estatus: '1' });

    resultados.classList.remove('d-none');
    resultados.innerHTML = r.datos.length
      ? r.datos.slice(0, 8).map((p) => `
          <div class="opcion" data-paciente="${p.id_registro_paciente}">
            <div>
              <div class="fw-semibold">${App.esc(p.nombre_completo)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(p.num_expediente)}
                ${p.telefono ? ' · ' + App.esc(p.telefono) : ''}
              </div>
            </div>
          </div>`).join('')
      : '<div class="p-3 text-center text-secondary small">Sin resultados.</div>';
  }, 350));

  document.getElementById('cbLimpiar').addEventListener('click', () => {
    buscar.value = '';
    resultados.classList.add('d-none');
    document.getElementById('cbCuenta').classList.add('d-none');
    cuenta = null;
    actualizarBoton();
  });

  resultados.addEventListener('click', (ev) => {
    const opcion = ev.target.closest('[data-paciente]');
    if (opcion) cargarPaciente(Number(opcion.dataset.paciente));
  });

  async function cargarPaciente(idExpediente) {
    const r = await App.api('cajas/paciente_cuenta.php', {
      id_registro_paciente: idExpediente,
    });
    cuenta = r.datos;

    resultados.classList.add('d-none');
    buscar.value = cuenta.paciente.nombre_completo;

    document.getElementById('cbCuenta').classList.remove('d-none');
    document.getElementById('cbPacienteNombre').textContent =
      cuenta.paciente.nombre_completo;
    document.getElementById('cbVerEstado').href =
      `${App.URL_BASE}/estado_cuenta.php?id=${idExpediente}`;

    pintarResumen();
    pintarCargos();
    actualizarBoton();
  }

  function pintarResumen() {
    const r = cuenta.resumen;
    document.getElementById('cbResumen').innerHTML = `
      <div>
        <span class="et">Cargado</span>
        <span class="va">${App.money(r.cargado ?? 0)}</span>
      </div>
      <div>
        <span class="et">Pagado</span>
        <span class="va" style="color:var(--exito)">${App.money(r.pagado ?? 0)}</span>
      </div>
      <div>
        <span class="et">Debe</span>
        <span class="va fs-5" style="color:${Number(r.saldo) > 0
          ? 'var(--error)' : 'var(--exito)'}">${App.money(r.saldo ?? 0)}</span>
      </div>`;
  }

  function pintarCargos() {
    const tabla = document.getElementById('cbCargos');

    tabla.innerHTML = cuenta.cargos.length
      ? cuenta.cargos.map((c) => `
          <tr data-cargo="${c.id_cargo}">
            <td>
              <input class="form-check-input cb-marca" type="checkbox"
                     data-saldo="${c.saldo}">
            </td>
            <td>
              <div>${App.esc(c.descripcion)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${c.plan_folio ? App.esc(c.plan_folio) : ''}
                ${c.consulta_folio ? ' · ' + App.esc(c.consulta_folio) : ''}
              </div>
            </td>
            <td class="text-secondary">${App.esc(App.fecha(c.fecha))}</td>
            <td class="text-end fw-semibold">${App.money(c.saldo)}</td>
            <td>
              <input type="number" class="form-control form-control-sm text-end cb-abono"
                     step="0.01" min="0" max="${c.saldo}" disabled>
            </td>
          </tr>`).join('')
      : `<tr class="fila-vacia"><td colspan="5">
           Este paciente no debe nada. Puedes cobrarle igual: quedaría
           como saldo a favor.</td></tr>`;
  }

  // Marcar un cargo llena su abono con lo que debe. Es lo que se quiere
  // el 90% de las veces; el resto se corrige a mano.
  document.getElementById('cbCargos').addEventListener('change', (ev) => {
    const marca = ev.target.closest('.cb-marca');
    if (!marca) return;

    const fila  = marca.closest('tr');
    const abono = fila.querySelector('.cb-abono');

    abono.disabled = !marca.checked;
    abono.value = marca.checked ? Number(marca.dataset.saldo).toFixed(2) : '';

    sincronizarDesdeCargos();
  });

  document.getElementById('cbCargos').addEventListener('input', (ev) => {
    if (ev.target.classList.contains('cb-abono')) sincronizarDesdeCargos();
  });

  /**
   * Si se marcaron cargos y todavia no se captura ninguna forma de
   * pago, la primera se llena con la suma. Evita teclear dos veces el
   * mismo numero, que es como se cuelan los errores.
   */
  function sincronizarDesdeCargos() {
    const suma = [...document.querySelectorAll('.cb-abono')]
      .reduce((t, el) => t + (Number(el.value) || 0), 0);

    if (suma > 0 && formas.length === 1 && !formas[0].tocada) {
      formas[0].monto = suma;
      pintarFormas();
    }
    actualizarBoton();
  }

  /* -------------------------------------------------------------------
     Formas de pago
     ------------------------------------------------------------------- */
  function agregarForma() {
    formas.push({
      id_forma_pago: Cat.formas[0]?.id_forma_pago ?? 1,
      monto: 0,
      referencia: '',
      tocada: false,
    });
    pintarFormas();
  }

  function pintarFormas() {
    const caja = document.getElementById('cbFormas');

    caja.innerHTML = formas.map((f, i) => {
      const forma = Cat.forma(f.id_forma_pago);
      const pideReferencia = forma && Number(forma.afecta_efectivo) === 0;

      return `
      <div class="forma-pago" data-i="${i}">
        <select class="form-select form-select-sm cb-forma">
          ${Cat.formas.map((x) => `
            <option value="${x.id_forma_pago}"
              ${Number(x.id_forma_pago) === Number(f.id_forma_pago) ? 'selected' : ''}>
              ${App.esc(x.nombre_forma)}
            </option>`).join('')}
        </select>

        <div class="input-group input-group-sm" style="width:130px">
          <span class="input-group-text">$</span>
          <input type="number" class="form-control text-end cb-monto"
                 value="${f.monto ? Number(f.monto).toFixed(2) : ''}"
                 step="0.01" min="0" placeholder="0.00">
        </div>

        ${formas.length > 1 ? `
          <button type="button" class="btn btn-sm btn-link text-danger p-0"
                  data-quitar-forma="${i}"><i class="bi bi-x-lg"></i></button>`
        : '<span style="width:16px"></span>'}

        ${pideReferencia ? `
          <input type="text" class="form-control form-control-sm cb-referencia"
                 value="${App.esc(f.referencia ?? '')}"
                 placeholder="Referencia o últimos 4 dígitos" maxlength="60">` : ''}
      </div>`;
    }).join('');

    actualizarBoton();
  }

  document.getElementById('cbAgregarForma').addEventListener('click', agregarForma);

  document.getElementById('cbFormas').addEventListener('input', (ev) => {
    const fila = ev.target.closest('[data-i]');
    if (!fila) return;

    const f = formas[Number(fila.dataset.i)];

    if (ev.target.classList.contains('cb-monto')) {
      f.monto = Number(ev.target.value) || 0;
      f.tocada = true;
    }
    if (ev.target.classList.contains('cb-referencia')) {
      f.referencia = ev.target.value;
    }
    actualizarBoton();
  });

  document.getElementById('cbFormas').addEventListener('change', (ev) => {
    const fila = ev.target.closest('[data-i]');
    if (!fila || !ev.target.classList.contains('cb-forma')) return;

    formas[Number(fila.dataset.i)].id_forma_pago = Number(ev.target.value);
    pintarFormas();
  });

  document.getElementById('cbFormas').addEventListener('click', (ev) => {
    const quitar = ev.target.closest('[data-quitar-forma]');
    if (!quitar) return;
    formas.splice(Number(quitar.dataset.quitarForma), 1);
    pintarFormas();
  });

  /* -------------------------------------------------------------------
     Total y aviso de saldo a favor
     ------------------------------------------------------------------- */
  function actualizarBoton() {
    const total = formas.reduce((t, f) => t + (Number(f.monto) || 0), 0);
    document.getElementById('cbTotal').textContent = App.money(total);

    const debe = Number(cuenta?.resumen?.saldo ?? 0);
    const aviso = document.getElementById('cbAvisoFavor');

    // Cobrar de mas no se impide: un anticipo es legitimo. Pero se
    // avisa, porque un anticipo que nadie menciona se olvida y despues
    // nadie entiende por que el saldo no cuadra.
    if (cuenta && total > debe + 0.009) {
      aviso.classList.remove('d-none');
      aviso.innerHTML = `<i class="bi bi-info-circle me-1"
            style="color:var(--morado-600)"></i>
          Se cobra más de lo que debe. Quedarán
          <b>${App.money(total - debe)}</b> a favor del paciente.`;
    } else {
      aviso.classList.add('d-none');
    }

    document.getElementById('cbCobrar').disabled =
      !turno || !cuenta || total <= 0;
  }

  /* -------------------------------------------------------------------
     Cobrar
     ------------------------------------------------------------------- */
  document.getElementById('cbCobrar').addEventListener('click', async () => {
    const aplicaciones = [...document.querySelectorAll('#cbCargos tr[data-cargo]')]
      .map((tr) => ({
        id_cargo: Number(tr.dataset.cargo),
        monto: Number(tr.querySelector('.cb-abono').value) || 0,
      }))
      .filter((a) => a.monto > 0);

    const total = formas.reduce((t, f) => t + (Number(f.monto) || 0), 0);

    const ok = await App.alerta.confirmar(
      `Cobrar ${App.money(total)}`,
      `A ${cuenta.paciente.nombre_completo}. Revisa el monto antes de continuar.`,
      'Sí, cobrar');
    if (!ok) return;

    try {
      const r = await App.api('cajas/cobrar.php', {
        id_registro_paciente: cuenta.paciente.id_registro_paciente,
        id_corte_caja: turno.id_corte_caja,
        formas: JSON.stringify(formas.map(
          (f) => ({ id_forma_pago: f.id_forma_pago, monto: f.monto,
                    referencia: f.referencia }))),
        aplicaciones: JSON.stringify(aplicaciones),
        observaciones: document.getElementById('cbObservaciones').value,
      });

      const d = await Swal.fire({
        icon: 'success',
        title: `Cobro ${r.datos.folio}`,
        html: `<div style="font-size:14px;line-height:1.8">
                 Monto: <b>${App.money(r.datos.monto)}</b><br>
                 Aplicado a cargos: <b>${App.money(r.datos.aplicado)}</b>
                 ${Number(r.datos.a_favor) > 0.009 ? `
                   <div style="background:#fff6e5;border:1px solid #f0d9a8;
                               border-radius:9px;padding:.7rem .9rem;
                               color:#7a5a12;margin-top:.9rem;font-size:12.5px">
                     Quedan <b>${App.money(r.datos.a_favor)}</b> a favor del
                     paciente. Se aplicarán solos al próximo cargo.
                   </div>` : ''}
               </div>`,
        confirmButtonText: 'Imprimir recibo',
        confirmButtonColor: '#7b2d8e',
        showCancelButton: true,
        cancelButtonText: 'Cerrar',
        cancelButtonColor: '#6c6577',
        reverseButtons: true,
      });

      if (d.isConfirmed) {
        App.abrirVentana(`${App.URL_BASE}/recibo_imprimir.php?id=${r.datos.id_pago}`,
          880, 700, 'recibo');
      }

      // Se vuelve a cargar todo: el saldo cambio y las formas ya se usaron.
      formas = [];
      agregarForma();
      document.getElementById('cbObservaciones').value = '';
      cargarPaciente(cuenta.paciente.id_registro_paciente);
      cargarUltimos();
    } catch (e) {
      App.alerta.error('No se pudo cobrar', e.mensaje ?? '');
    }
  });

  /* -------------------------------------------------------------------
     Cobros del turno
     ------------------------------------------------------------------- */
  async function cargarUltimos() {
    if (!turno) return;

    const r = await App.api('cajas/turno_estado.php', {
      id_corte_caja: turno.id_corte_caja,
    });

    // Un cobro puede traer varios movimientos (uno por forma). Se
    // agrupan por folio para no mostrarlo repetido.
    const vistos = new Set();
    const cobros = r.datos.movimientos.filter((m) => {
      if (!m.pago_folio || vistos.has(m.pago_folio)) return false;
      vistos.add(m.pago_folio);
      return true;
    });

    document.getElementById('cbUltimos').innerHTML = cobros.length
      ? cobros.slice(0, 10).map((m) => `
          <div class="anexo">
            <div class="flex-grow-1" style="min-width:0">
              <div class="fw-semibold">${App.esc(m.pago_folio)}</div>
              <div class="text-secondary" style="font-size:11px">
                ${App.esc(String(m.fecha_hora).substring(11, 16))}
                · ${App.esc(m.paciente ?? '')}
              </div>
            </div>
            <div class="d-flex gap-1 align-items-center">
              <button type="button" class="btn btn-sm btn-light"
                      data-recibo="${m.id_pago}" title="Recibo">
                <i class="bi bi-printer"></i>
              </button>
              ${Cat.permisos.cancelar ? `
                <button type="button" class="btn btn-sm btn-link text-danger p-0"
                        data-cancelar="${m.id_pago}" title="Cancelar">
                  <i class="bi bi-x-circle"></i>
                </button>` : ''}
            </div>
          </div>`).join('')
      : '<div class="exp-vacio">Todavía no hay cobros en este turno.</div>';
  }

  document.getElementById('cbUltimos').addEventListener('click', async (ev) => {
    const recibo = ev.target.closest('[data-recibo]');
    if (recibo) {
      App.abrirVentana(`${App.URL_BASE}/recibo_imprimir.php?id=${recibo.dataset.recibo}`,
        880, 700, 'recibo');
      return;
    }

    const cancelar = ev.target.closest('[data-cancelar]');
    if (!cancelar) return;

    const motivo = await App.alerta.pedirTexto(
      '¿Cancelar este cobro?',
      'El cobro no se borra: queda marcado como cancelado y se registra '
      + 'el reverso en la caja. Escribe por qué.');
    if (!motivo) return;

    try {
      await App.api('cajas/cobro_cancelar.php', {
        id_pago: cancelar.dataset.cancelar, motivo,
      });
      App.alerta.toast('Cobro cancelado');
      cargarUltimos();
      if (cuenta) cargarPaciente(cuenta.paciente.id_registro_paciente);
    } catch (e) { /* ya se mostro */ }
  });

  /* ---------- Arranque ---------- */
  agregarForma();
  revisarTurno();

  // Si se llego desde "Cobrar" en la lista de pendientes, el paciente
  // ya viene en la direccion.
  const inicial = new URLSearchParams(location.search).get('paciente');
  if (inicial) cargarPaciente(Number(inicial));
}
