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
