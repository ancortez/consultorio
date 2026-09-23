<?php
/**
 * Cajas > Pendientes de pago.
 *
 * A quien hay que cobrarle. Ordenado por antiguedad del adeudo, que es
 * el orden en que conviene atenderlo: entre mas tiempo pasa, mas
 * dificil es recuperarlo.
 */
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Pendientes de pago</h1>
                <p class="subtitulo-vista">
                    Pacientes con saldo, del adeudo m&aacute;s viejo al m&aacute;s reciente
                </p>
            </div>
            <span class="text-secondary" id="pdResumen"></span>
        </div>

        <div class="card">
            <div class="tabla-con-menu">
                <table class="table tabla-sistema align-middle mb-0">
                    <thead><tr>
                        <th style="width:50px">#</th>
                        <th>Paciente</th>
                        <th style="width:130px">Tel&eacute;fono</th>
                        <th style="width:150px">Desde</th>
                        <th style="width:110px" class="text-end">Cargado</th>
                        <th style="width:110px" class="text-end">Pagado</th>
                        <th style="width:120px" class="text-end">Debe</th>
                        <th class="col-acciones"></th>
                    </tr></thead>
                    <tbody id="tablaPendientes">
                        <tr class="fila-vacia"><td colspan="8">
                            <div class="spinner-border spinner-border-sm me-2"
                                 style="color:var(--morado-600)"></div>Cargando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
