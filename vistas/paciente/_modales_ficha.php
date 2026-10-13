<?php
/**
 * Los tres modales de la ficha: contacto, tutor y domicilio.
 *
 * En la ficha, cada uno de estos bloques se ve como un RESUMEN de una
 * o dos lineas con un lapiz al final. El lapiz abre el modal que
 * corresponde, y dentro vive la pantalla de administracion que ya
 * existia: el listado completo con sus botones de alta, edicion y
 * baja.
 *
 * Por que resumen afuera y lista adentro: en la ficha lo que se hace
 * el 95% de las veces es MIRAR —"¿cual es el telefono?", "¿quien es la
 * mama?"— y para eso una linea basta. Administrar la lista es lo
 * excepcional, y lo excepcional puede costar un clic.
 *
 * El contenido de cada uno es el mismo archivo de siempre: no se
 * duplico nada, solo cambio el envoltorio.
 */
?>

<!-- ============ Canales de contacto ============ -->
<div class="modal fade" id="modalListaContactos" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-chat-dots me-2"></i>Canales de contacto
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php require __DIR__ . '/_sec_contacto.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-morado" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ Tutores ============ -->
<div class="modal fade" id="modalListaTutores" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-people me-2"></i>Tutores del paciente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php require __DIR__ . '/_sec_tutor.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-morado" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ Domicilio ============ -->
<div class="modal fade" id="modalDomicilio" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-geo-alt me-2"></i>Domicilio del paciente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php require __DIR__ . '/_sec_domicilio.php'; ?>
      </div>
    </div>
  </div>
</div>
