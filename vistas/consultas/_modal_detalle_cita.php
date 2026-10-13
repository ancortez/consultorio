<?php
/**
 * Detalle de una cita.
 *
 * El mismo archivo lo incluyen la agenda del dia y la de la semana.
 * Antes, para enterarse de algo de una cita habia que abrir el menu de
 * tres puntos de su renglon —y en la vista de semana ni eso: el bloque
 * no respondia al clic—. Lo natural es picar la cita.
 *
 * Que hace y que no:
 *
 *   SI   enseniar quien, cuando, con quien, el motivo y la nota
 *   SI   cambiar el estatus, que es lo que se hace noventa veces al dia
 *   NO   editar los campos aqui dentro
 *
 * Lo ultimo es a proposito. Mover la fecha o la hora obliga a revisar
 * los espacios libres y a avisar de los choques, y eso ya lo hace bien
 * el modal de reagendar. Duplicarlo aqui serian dos formularios que
 * validan distinto sobre la misma tabla. El boton "Modificar" lleva a
 * ese, que es el que sabe.
 */
?>
<div class="modal fade" id="modalDetalleCita" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-calendar-event me-2"></i>Detalle de la cita
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="detalle-cita">

          <div class="dc-cabecera">
            <div>
              <?php /* El nombre es un enlace al expediente: desde el
                       detalle de una cita, lo siguiente que se quiere
                       ver casi siempre es el paciente. */ ?>
              <a class="dc-nombre" id="dcNombre" href="#"></a>
              <div class="dc-sub" id="dcSub"></div>
            </div>
            <span class="chip-estado" id="dcEstado"></span>
          </div>

          <div class="dc-datos">
            <div class="par">
              <span class="et">Cu&aacute;ndo</span>
              <span class="va" id="dcCuando"></span>
            </div>
            <div class="par">
              <span class="et">Con</span>
              <span class="va" id="dcDentista"></span>
            </div>
            <div class="par">
              <span class="et">Motivo</span>
              <span class="va" id="dcMotivo"></span>
            </div>
          </div>

          <?php /* La nota ocupa su propio bloque y no un renglon mas de
                   la rejilla: es texto libre, puede ser largo, y es
                   justo lo que se viene a leer ("¿por que venia este
                   niño?"). En una celda angosta se cortaria. */ ?>
          <div class="dc-nota" id="dcNotaCaja">
            <div class="et"><i class="bi bi-sticky me-1"></i>Nota de la cita</div>
            <div id="dcNota"></div>
          </div>

          <div class="dc-acciones" id="dcAcciones"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-morado" id="dcModificar">
          <i class="bi bi-pencil me-1"></i> Modificar
        </button>
      </div>
    </div>
  </div>
</div>
