/* =====================================================================
   Tablero de inicio
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  citasPorConfirmar();
});

/**
 * Confirmar o cancelar una cita desde el tablero, sin salir de aqui.
 *
 * La idea es que recepcion pueda ir llamando y palomeando en la misma
 * pantalla. Cada renglon desaparece al resolverse, asi la lista se va
 * vaciando y se ve el avance.
 */
function citasPorConfirmar() {
  const lista = document.getElementById('listaPorConfirmar');
  if (!lista) return;

  lista.addEventListener('click', async (ev) => {
    const confirmar = ev.target.closest('[data-confirmar]');
    const cancelar  = ev.target.closest('[data-cancelar]');
    if (!confirmar && !cancelar) return;

    const boton = confirmar ?? cancelar;
    const fila  = boton.closest('.cita-pendiente');
    const id    = boton.dataset.confirmar ?? boton.dataset.cancelar;
    const nombre = fila.querySelector('a')?.textContent.trim() ?? '';

    if (cancelar) {
      const ok = await App.alerta.confirmar(
        '¿Cancelar esta cita?', nombre, 'Sí, cancelar');
      if (!ok) return;
    }

    boton.disabled = true;

    try {
      await App.api('consultas/cita_estatus.php', {
        id_cita: id,
        id_estado_cita: confirmar ? 2 : 5,
      });

      // Se desvanece el renglon en lugar de recargar: recargar perderia
      // el lugar en la lista si son muchas.
      fila.style.transition = 'opacity .25s, transform .25s';
      fila.style.opacity = '0';
      fila.style.transform = 'translateX(20px)';

      setTimeout(() => {
        fila.remove();
        actualizarContador();
      }, 260);

      App.alerta.toast(confirmar ? 'Cita confirmada' : 'Cita cancelada');
    } catch (e) {
      boton.disabled = false;
    }
  });

  function actualizarContador() {
    const quedan = lista.querySelectorAll('.cita-pendiente').length;
    const badge  = lista.closest('.card').querySelector('.badge-pendiente');

    if (badge) {
      badge.textContent = quedan;
      if (quedan === 0) badge.remove();
    }

    if (quedan === 0) {
      lista.innerHTML = `<div class="p-4 text-center text-secondary small">
          <i class="bi bi-check2-circle fs-4 d-block mb-1" style="color:var(--exito)"></i>
          Todas las citas están confirmadas.
        </div>`;
    }
  }
}

/* ---------------------------------------------------------------------
   Recordatorios del tablero

   Mandar el WhatsApp y registrar que se mando son dos cosas distintas:
   el enlace abre la aplicacion y ahi se pierde el rastro. Por eso al
   pulsar "Enviar" se registra de una vez, y quedan aparte los botones
   de "ya le avise" (por si se hizo por telefono) y "no contesto".
   --------------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
  const lista = document.getElementById('listaRecordatorios');
  if (!lista) return;

  async function registrar(fila, resultado, aviso) {
    try {
      await App.api('consultas/recordatorio_registrar.php', {
        id_cita: fila.dataset.cita,
        id_regla: fila.dataset.regla,
        resultado,
      });

      // La fila se desvanece: ya no es trabajo pendiente y dejarla ahi
      // invita a mandar el mismo recordatorio dos veces.
      fila.classList.add('hecho');
      setTimeout(() => fila.remove(), 400);

      App.alerta.toast(aviso);
    } catch (e) { /* ya se mostro */ }
  }

  lista.addEventListener('click', (ev) => {
    const fila = ev.target.closest('.recordatorio');
    if (!fila) return;

    const accion = ev.target.closest('[data-accion]')?.dataset.accion;

    // El enlace de WhatsApp se deja pasar: abre la app y de paso se
    // registra. Si se bloqueara para registrar primero, el navegador
    // trataria la apertura como emergente y la detendria.
    if (accion === 'rec-enviar') {
      registrar(fila, 1, 'Recordatorio registrado');
      return;
    }

    if (accion === 'rec-hecho') {
      registrar(fila, 1, 'Recordatorio registrado');
    }
    if (accion === 'rec-nocontesto') {
      registrar(fila, 2, 'Anotado: no contestó');
    }
  });
});
