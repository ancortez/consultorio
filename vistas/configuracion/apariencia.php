<?php
/**
 * Configuracion > Apariencia.
 *
 * El color se guarda como un parametro y se aplica al recargar. No hay
 * vista previa en vivo a proposito: cambiar los colores con JavaScript
 * y luego guardarlos deja la puerta abierta a que la pantalla se vea de
 * un color y la base diga otro.
 */
$actual = Tema::actual();
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="mb-3">
            <h1 class="titulo-vista">Apariencia</h1>
            <p class="subtitulo-vista">
                Color general del sistema
            </p>
        </div>

        <div class="alert alert-light border d-flex gap-2 py-2 small">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span>
                El color cambia para todos los usuarios, no solo para ti.
                Los documentos que se imprimen (consentimiento, historia
                cl&iacute;nica, plan y recetas) conservan el morado de la
                papeler&iacute;a del consultorio.
            </span>
        </div>

        <div class="row g-3" id="temas">
            <?php foreach (Tema::todos() as $clave => $t): ?>
            <div class="col-4">
                <button type="button"
                        class="tema-tarjeta <?= $clave === $actual ? 'activo' : '' ?>"
                        data-tema="<?= e($clave) ?>">
                    <span class="muestra">
                        <?php foreach (['c900','c700','c500','a400','a300','a100'] as $tono): ?>
                            <span style="background:<?= e($t[$tono]) ?>"></span>
                        <?php endforeach; ?>
                    </span>

                    <span class="cuerpo">
                        <span class="nombre"><?= e($t['nombre']) ?></span>
                        <span class="nota"><?= e($t['nota']) ?></span>
                    </span>

                    <span class="marca">
                        <i class="bi bi-check-lg"></i>
                    </span>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
