<?php
/**
 * Modales del paso de diagnostico del plan: oclusion y traumatismos.
 *
 * Los dos existen por la misma razon. Son datos que la mayoria de los
 * pacientes no tiene, y como campos siempre visibles solo servian para
 * que alguien pasara por encima con el tabulador. Fuera del formulario,
 * el caso normal —"no aplica"— no cuesta nada, y el caso que si aplica
 * tiene espacio para capturarse bien.
 */
?>

<!-- ============ Oclusion ============ -->
<div class="modal fade" id="modalOclusion" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-arrows-collapse me-2"></i>Oclusi&oacute;n
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <?php /* Varias a la vez, no una sola.

                 Un paciente puede tener clase II Y mordida cruzada al
                 mismo tiempo; son cosas distintas que conviven. Un
                 selector de una sola opcion obligaria a elegir cual de
                 las dos anotar, y la otra se perderia. */ ?>
        <p class="text-secondary small mb-3">
          Marca lo que aplique. Puede ser m&aacute;s de una.
        </p>

        <div class="lista-opciones" id="oclOpciones">
          <?php foreach (['CLASE II' => 'Clase II',
                          'CLASE III' => 'Clase III',
                          'MORDIDA CRUZADA' => 'Mordida cruzada'] as $valor => $texto): ?>
          <label class="opcion-marcable">
            <input type="checkbox" value="<?= e($valor) ?>">
            <span><?= e($texto) ?></span>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="form-text mt-3">
          Si no marcas nada, queda como <b>No aplica</b>.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-morado" id="oclAceptar">
          <i class="bi bi-check-lg me-1"></i> Aceptar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============ Traumatismo ============ -->
<div class="modal fade" id="modalTraumatismo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-bandaid me-2"></i>Traumatismo dental
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <?php /* Uno a la vez, con SU secuela al lado.

                 Antes eran dos campos sueltos: "traumatismos" y
                 "secuelas". Con dos golpes en la historia del paciente
                 quedaba "CAIDA 4 ANOS Y GOLPE BICICLETA 7 ANOS" en uno
                 y "NECROSIS 51" en el otro, y nadie podia saber de cual
                 de los dos salio la necrosis. */ ?>
        <label class="form-label" for="trmDescripcion">
          &iquest;Qu&eacute; pas&oacute;? <span class="req">*</span>
        </label>
        <input type="text" class="form-control" id="trmDescripcion" maxlength="255"
               placeholder="CA&Iacute;DA A LOS 4 A&Ntilde;OS, FRACTURA DE 51">

        <label class="form-label mt-3" for="trmSecuela">
          Secuela <span class="text-secondary fw-normal">(opcional)</span>
        </label>
        <input type="text" class="form-control" id="trmSecuela" maxlength="255"
               placeholder="NECROSIS PULPAR, DISCROM&Iacute;A...">
        <div class="form-text">
          D&eacute;jalo en blanco si todav&iacute;a no hay secuela. Un golpe
          reciente sin consecuencias no es un dato incompleto.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-morado" id="trmAceptar">
          <i class="bi bi-check-lg me-1"></i> Agregar
        </button>
      </div>
    </div>
  </div>
</div>
