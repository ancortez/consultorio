<?php
/**
 * Visor de album.
 *
 * Lo usan el panel del plan, el expediente electronico y la tarjeta de
 * documentos de la consulta. Vive en el layout porque son tres
 * pantallas y un solo visor; duplicarlo garantizaba que tarde o
 * temprano se vieran distintos.
 *
 * Lo maneja el componente Album de assets/js/comun.js. Quien lo abre
 * solo le pasa una lista de imagenes y un titulo; el visor no sabe de
 * donde vienen.
 */
?>
<!-- ============ Album ============ -->
<div class="modal fade" id="modalAlbumVer" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-images me-2"></i>
          <span id="albVerTitulo">&Aacute;lbum</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <?php /* Dos zonas: la imagen grande arriba y las miniaturas
                 abajo.

                 Mirar radiografias es comparar: "esta de hoy contra la
                 de hace tres meses". Un visor que obligue a cerrar y
                 abrir cada archivo convierte una comparacion de diez
                 segundos en un recorrido de dos minutos. Con las
                 miniaturas siempre a la vista, cambiar de imagen es un
                 clic. */ ?>
        <div class="visor-album">
          <button type="button" class="nav-visor izq" id="albVerAnterior"
                  title="Anterior (flecha izquierda)">
            <i class="bi bi-chevron-left"></i>
          </button>

          <div class="marco">
            <img id="albVerImagen" src="" alt="">
          </div>

          <button type="button" class="nav-visor der" id="albVerSiguiente"
                  title="Siguiente (flecha derecha)">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>

        <div class="pie-visor">
          <span id="albVerDescripcion">&mdash;</span>
          <span class="contador" id="albVerContador">&mdash;</span>
        </div>

        <div class="tira-miniaturas" id="albVerTira"></div>
      </div>

      <div class="modal-footer justify-content-between">
        <a href="#" target="_blank" class="btn btn-light" id="albVerAbrir">
          <i class="bi bi-box-arrow-up-right me-1"></i> Abrir en pesta&ntilde;a nueva
        </a>
        <button type="button" class="btn btn-morado" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

