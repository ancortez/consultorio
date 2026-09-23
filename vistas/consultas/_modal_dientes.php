<?php
/**
 * Selector de organos dentarios.
 *
 * Se dibuja la arcada completa con notacion FDI y se pican los dientes.
 * Un combo con 52 opciones numeradas obliga a traducir mentalmente
 * "26" a "primer molar superior izquierdo"; el dibujo se lee de un
 * vistazo y es como esta la hoja de papel.
 *
 * Los cuadrantes se acomodan como los ve el dentista frente al
 * paciente: la derecha del paciente queda a la izquierda de la
 * pantalla. Por eso los numeros de los cuadrantes 1 y 4 van al reves.
 */

$permanentes = [
    'arriba' => [
        ['lado' => 'Superior derecho',   'nums' => [18,17,16,15,14,13,12,11]],
        ['lado' => 'Superior izquierdo', 'nums' => [21,22,23,24,25,26,27,28]],
    ],
    'abajo' => [
        ['lado' => 'Inferior derecho',   'nums' => [48,47,46,45,44,43,42,41]],
        ['lado' => 'Inferior izquierdo', 'nums' => [31,32,33,34,35,36,37,38]],
    ],
];

$temporales = [
    'arriba' => [
        ['lado' => 'Superior derecho',   'nums' => [55,54,53,52,51]],
        ['lado' => 'Superior izquierdo', 'nums' => [61,62,63,64,65]],
    ],
    'abajo' => [
        ['lado' => 'Inferior derecho',   'nums' => [85,84,83,82,81]],
        ['lado' => 'Inferior izquierdo', 'nums' => [71,72,73,74,75]],
    ],
];

// Los nombres largos salen como globo de ayuda al pasar el raton.
$nombres = [];
foreach (Conexion::todos('SELECT id_diente, nombre_diente FROM diente') as $d) {
    $nombres[(int) $d['id_diente']] = $d['nombre_diente'];
}

/** Dibuja una fila de dientes de un cuadrante. */
$fila = static function (array $nums, string $clase) use ($nombres): void {
    foreach ($nums as $n) {
        printf(
            '<button type="button" class="diente %s" data-diente="%d" title="%s">'
            . '<i class="bi bi-hexagon-fill"></i><span>%d</span></button>',
            $clase, $n, e($nombres[$n] ?? ''), $n
        );
    }
};
?>
<div class="modal fade" id="modalDientes" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-database-fill-gear me-2"></i>
          &Oacute;rgano dentario
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="text-secondary mb-3" style="font-size:12.5px">
          Procedimiento:
          <b class="text-dark" id="dientesProcedimiento">&mdash;</b>
        </div>

        <ul class="nav nav-tabs nav-tabs-morado mb-3" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab"
                    data-bs-target="#dent-permanente" type="button">
              Denticion permanente
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab"
                    data-bs-target="#dent-temporal" type="button">
              Denticion temporal
            </button>
          </li>
        </ul>

        <div class="tab-content">
          <?php foreach ([['dent-permanente', $permanentes, 'permanente', true],
                          ['dent-temporal',   $temporales,  'temporal',   false]] as
                          [$id, $arcada, $clase, $activa]): ?>
          <div class="tab-pane fade <?= $activa ? 'show active' : '' ?>" id="<?= $id ?>">

            <div class="arcada">
              <div class="renglon">
                <div class="mitad derecha">
                  <?php $fila($arcada['arriba'][0]['nums'], $clase); ?>
                </div>
                <div class="linea-media"></div>
                <div class="mitad">
                  <?php $fila($arcada['arriba'][1]['nums'], $clase); ?>
                </div>
              </div>

              <div class="etiqueta-arcada">
                <span>Derecha del paciente</span>
                <span>Izquierda del paciente</span>
              </div>

              <div class="renglon">
                <div class="mitad derecha">
                  <?php $fila($arcada['abajo'][0]['nums'], $clase); ?>
                </div>
                <div class="linea-media"></div>
                <div class="mitad">
                  <?php $fila($arcada['abajo'][1]['nums'], $clase); ?>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button type="button" class="btn btn-sm btn-outline-morado"
                      data-todos="<?= e($clase) ?>">
                <i class="bi bi-check-all me-1"></i>
                Seleccionar todos los <?= $clase === 'temporal' ? 'temporales' : 'permanentes' ?>
              </button>
              <button type="button" class="btn btn-sm btn-light"
                      data-ninguno="<?= e($clase) ?>">
                Quitar los <?= $clase === 'temporal' ? 'temporales' : 'permanentes' ?>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php /* "No aplica" es distinto de "no seleccione nada": significa
                 que el procedimiento no va sobre un diente (una limpieza,
                 una valoracion). Marcarla apaga toda la seleccion. */ ?>
        <div class="form-check mt-3 p-3 border rounded"
             style="background:var(--lila-50)">
          <input class="form-check-input" type="checkbox" id="dientesNoAplica">
          <label class="form-check-label" for="dientesNoAplica">
            <b>No aplica a un &oacute;rgano dentario</b>
            <div class="text-secondary" style="font-size:11.5px">
              Para procedimientos generales: limpieza, valoraci&oacute;n,
              aplicaci&oacute;n de fl&uacute;or.
            </div>
          </label>
        </div>
      </div>

      <div class="modal-footer justify-content-between">
        <span class="text-secondary" style="font-size:12.5px" id="dientesResumen">
          Sin selecci&oacute;n
        </span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-morado" id="dientesAceptar">
            Aceptar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
