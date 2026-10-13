<?php
/**
 * Modal del panel derecho del plan: detalle de una nota de evolucion.
 *
 * El visor de album vivia aqui y se movio a vistas/layout: lo usan tres
 * pantallas (plan, expediente y consulta) y tenerlo en una sola
 * obligaba a duplicarlo en las otras.
 */
?>

<!-- ============ Nota de evolucion (solo lectura) ============ -->
<div class="modal fade" id="modalNotaVer" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-journal-text me-2"></i>Nota de evoluci&oacute;n
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="rx-encabezado mb-3">
          <div>
            <span class="et">Consulta</span>
            <span class="va" id="notaVerConsulta">&mdash;</span>
          </div>
          <div>
            <span class="et">Fecha</span>
            <span class="va" id="notaVerFecha">&mdash;</span>
          </div>
          <div>
            <span class="et">Escribi&oacute;</span>
            <span class="va" id="notaVerAutor">&mdash;</span>
          </div>
        </div>

        <?php /* Solo lectura, a proposito. Una nota de evolucion no se
                 corrige: si hay algo que aclarar, se escribe otra. Eso
                 es lo que hace que el expediente sirva como registro y
                 no como borrador. */ ?>
        <div class="nota-completa" id="notaVerTexto"></div>
      </div>

      <div class="modal-footer justify-content-between">
        <a href="#" class="btn btn-light" id="notaVerIrConsulta">
          <i class="bi bi-arrow-right-short me-1"></i> Ir a la consulta
        </a>
        <button type="button" class="btn btn-morado" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
