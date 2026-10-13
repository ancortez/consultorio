<?php
/**
 * Boton "Cerrar" de las ventanas de impresion.
 *
 * Estas pantallas (receta, consentimiento, plan, historia, corte,
 * recibo, estado de cuenta) se abren con window.open en una ventana
 * chica. Antes tenian un enlace "Volver a...", y ahi estaba el
 * problema: al presionarlo, el sistema COMPLETO se cargaba dentro de
 * esa ventanita. El usuario terminaba con dos sesiones del consultorio
 * abiertas, una de ellas apretada en 880 pixeles, sin entender cual era
 * cual ni cual tenia los datos buenos. Y si guardaba en una mientras
 * miraba la otra, lo que veia dejaba de ser cierto.
 *
 * Una ventana que se abrio para imprimir algo solo tiene que saber
 * cerrarse.
 *
 * El respaldo existe porque window.close() no siempre funciona: el
 * navegador solo deja cerrar las ventanas que el propio JavaScript
 * abrio. Si alguien llego aqui pegando la direccion o desde un
 * marcador, cerrar no hace nada, y en ese caso sí conviene llevarlo a
 * donde iba.
 *
 * Uso:
 *     <?php $volverA = URL_BASE . '/index.php?...'; ?>
 *     <?php require __DIR__ . '/includes/_boton_cerrar.php'; ?>
 */
$volverA = $volverA ?? (URL_BASE . '/index.php');
?>
<button type="button" class="secundario" id="btnCerrarVentana">Cerrar</button>
<script>
document.getElementById('btnCerrarVentana').addEventListener('click', () => {
    // window.opener solo existe si esta ventana la abrio otra. Es la
    // forma confiable de saber si "cerrar" va a funcionar.
    const esEmergente = window.opener && !window.opener.closed;

    window.close();

    // Si seguimos aqui, el navegador no dejo cerrar: no era emergente.
    // Entonces si tiene sentido navegar.
    if (!esEmergente) {
        window.location.href = <?= json_encode($volverA, JSON_UNESCAPED_SLASHES) ?>;
    }
});
</script>
