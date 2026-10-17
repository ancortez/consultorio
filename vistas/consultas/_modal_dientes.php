<?php
/**
 * Selector de organos dentarios.
 *
 * Se dibuja la arcada completa con notacion FDI y se pican los dientes.
 * Un combo con 52 opciones numeradas obliga a traducir mentalmente
 * "26" a "primer molar superior izquierdo"; el dibujo se lee de un
 * vistazo y es como esta la hoja de papel.
 *
 * El dibujo YA NO se arma aqui: vive en Odontograma, en el nucleo.
 * Hasta el 17 de octubre de 2026 estas eran unas funciones anonimas en
 * este archivo, y servia mientras este fuera el unico sitio que
 * dibujaba una dentadura. Ahora hay dos —este selector y la pestania de
 * odontograma del paciente— y una copia seria dos dentaduras que se
 * separan: la primera vez que alguien corrija una raiz, lo hara en una
 * sola.
 *
 * Y el selector ENSENIA el estado de cada diente: sano, amalgama,
 * ausente, con su color. Lo pidio la doctora y es lo que convierte
 * este modal en algo util para decidir: se elige donde va el
 * procedimiento mirando como esta la boca, no una rejilla de numeros
 * iguales. El estado pinta el relleno y la seleccion el contorno, asi
 * que las dos cosas se ven a la vez.
 */

/*
   De quien es el odontograma que se pinta.

   Lo pone la pantalla que incluye este modal: la del plan sabe de que
   paciente es. Sin expediente el modal sigue funcionando —se puede
   elegir dientes— pero sin colores, que es lo que pasaba antes.
*/
$idExpedienteOdo = $idExpedienteOdo ?? null;

$estadoDientes = $idExpedienteOdo
    ? Odontograma::estadoDelPaciente((int) $idExpedienteOdo)
    : [];

$condicionesOdo = $estadoDientes ? Odontograma::condiciones() : [];
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
          <?php foreach ([['dent-permanente', Odontograma::PERMANENTES, 'permanente', true],
                          ['dent-temporal',   Odontograma::TEMPORALES,  'temporal',   false]] as
                          [$id, $arcada, $clase, $activa]): ?>
          <div class="tab-pane fade <?= $activa ? 'show active' : '' ?>" id="<?= $id ?>">

            <?= Odontograma::arcada($arcada, $clase, $estadoDientes) ?>

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

        <?php /* La leyenda de colores, solo si hay algo pintado.

                 Sin ella los colores son adivinanza: un diente gris
                 oscuro puede ser ausente o amalgama y el globo de ayuda
                 obliga a pasar el raton por cada uno. Y solo salen las
                 condiciones que el paciente TIENE: una leyenda con las
                 trece ocuparia mas que la dentadura y la mayoria no
                 estaria en el dibujo. */ ?>
        <?php if ($estadoDientes): ?>
        <?php
            $usadas = array_column($estadoDientes, 'id_condicion');
            $leyenda = array_values(array_filter(
                $condicionesOdo,
                static fn (array $c): bool
                    => in_array((int) $c['id_condicion'], $usadas, true)));
        ?>
        <div class="leyenda-odontograma mt-3">
          <span class="text-secondary">Estado actual:</span>
          <?php foreach ($leyenda as $c): ?>
          <span class="chip-condicion">
            <i style="background:<?= e((string) $c['color']) ?>"></i>
            <?= e($c['nombre_condicion']) ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

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
