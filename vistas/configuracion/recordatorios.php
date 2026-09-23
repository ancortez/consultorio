<?php
/**
 * Configuracion > Recordatorios de cita.
 *
 * Cuantos avisos se le hacen al paciente y con cuanta anticipacion.
 * El sistema no manda nada solo: arma el mensaje y lo pone enfrente en
 * el tablero para que recepcion lo revise y lo envie.
 */
$reglas = AgendaExtra::reglas();
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Recordatorios de cita</h1>
                <p class="subtitulo-vista">
                    Cu&aacute;ntos avisos y con cu&aacute;nta anticipaci&oacute;n
                </p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-morado" id="recAgregar">
                    <i class="bi bi-plus-lg me-1"></i> Agregar recordatorio
                </button>
                <button class="btn btn-morado" id="recGuardar">
                    <i class="bi bi-check-lg me-1"></i> Guardar
                </button>
            </div>
        </div>

        <div class="alert alert-light border d-flex gap-2 py-2 small">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span>
                El sistema <b>no manda mensajes solo</b>. Cada recordatorio
                aparece en el tablero de inicio con el texto ya armado, y
                recepci&oacute;n lo revisa antes de enviarlo por WhatsApp.
                Mandar solo es f&aacute;cil de programar y dif&iacute;cil de
                arreglar cuando sale mal.
            </span>
        </div>

        <div class="card">
            <div class="tabla-con-menu">
                <table class="table tabla-sistema mb-0">
                    <thead><tr>
                        <th style="width:60px">#</th>
                        <th>Nombre</th>
                        <th style="width:220px">Anticipaci&oacute;n</th>
                        <th style="width:180px">Equivale a</th>
                        <th class="col-acciones"></th>
                    </tr></thead>
                    <tbody id="tablaRecordatorios"></tbody>
                </table>
            </div>
        </div>

        <?php /* La linea de tiempo ayuda a ver si los avisos estan bien
                 repartidos o si dos caen casi encima. */ ?>
        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>C&oacute;mo queda
            </div>
            <div class="card-body">
                <div class="linea-recordatorios" id="lineaRecordatorios"></div>
            </div>
        </div>
    </div>
</div>

<script>
/* Las reglas guardadas se pasan al JavaScript tal cual: es una lista
   corta y pedirla por AJAX seria un viaje de mas. */
window.REGLAS_RECORDATORIO = <?= json_encode($reglas, JSON_UNESCAPED_UNICODE) ?>;
</script>
