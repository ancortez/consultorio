<?php
/**
 * Cajas > Turnos y corte.
 *
 * Arriba el estado de cada caja: cual esta abierta, quien la abrio y
 * cuanto lleva. Abajo el detalle del turno elegido, con sus
 * movimientos y el boton de cerrar.
 *
 * Son varias cajas porque asi trabaja el consultorio: recepcion cobra
 * en efectivo y cada doctora tambien cobra desde su consultorio, casi
 * siempre por transferencia. Cada una abre, cierra y corta por su lado.
 */
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Turnos y corte de caja</h1>
                <p class="subtitulo-vista">
                    Cada caja abre y cierra su propio turno
                </p>
            </div>
        </div>

        <!-- ============ Estado de las cajas ============ -->
        <div class="row g-3 mb-3" id="cajasEstado">
            <div class="col-12">
                <div class="cargando">
                    <div class="spinner-border spinner-border-sm"
                         style="color:var(--morado-600)"></div>
                    Cargando cajas...
                </div>
            </div>
        </div>

        <!-- ============ Detalle del turno ============ -->
        <div class="card d-none" id="turnoDetalle">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-receipt-cutoff me-2"></i>
                    Movimientos de <b id="turnoNombre"></b>
                </span>
                <div class="d-flex gap-2">
                    <?php if (Auth::puede('cajas.egreso')): ?>
                    <button type="button" class="btn btn-sm btn-light" id="btnEgreso">
                        <i class="bi bi-dash-circle me-1"></i> Registrar salida
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-morado"
                            id="btnImprimirCorte">
                        <i class="bi bi-printer me-1"></i> Imprimir corte
                    </button>
                    <?php if (Auth::puede('cajas.corte')): ?>
                    <button type="button" class="btn btn-sm btn-morado" id="btnCerrarTurno">
                        <i class="bi bi-lock me-1"></i> Cerrar turno
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body pb-0">
                <div class="row g-3" id="turnoTotales"></div>
            </div>

            <div class="tabla-con-menu">
                <table class="table tabla-sistema mb-0">
                    <thead><tr>
                        <th style="width:90px">Hora</th>
                        <th style="width:170px">Concepto</th>
                        <th>Descripci&oacute;n</th>
                        <th style="width:150px">Forma</th>
                        <th style="width:130px" class="text-end">Importe</th>
                        <th style="width:160px">Registr&oacute;</th>
                    </tr></thead>
                    <tbody id="turnoMovimientos"></tbody>
                </table>
            </div>
        </div>

        <!-- ============ Turnos cerrados ============ -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Turnos cerrados
            </div>
            <div class="tabla-con-menu">
                <table class="table tabla-sistema mb-0">
                    <thead><tr>
                        <th style="width:150px">Caja</th>
                        <th style="width:170px">Apertura</th>
                        <th style="width:170px">Cierre</th>
                        <th class="text-end" style="width:120px">Ingresos</th>
                        <th class="text-end" style="width:120px">Egresos</th>
                        <th class="text-end" style="width:120px">Diferencia</th>
                        <th class="col-acciones"></th>
                    </tr></thead>
                    <tbody id="turnosCerrados">
                        <tr class="fila-vacia"><td colspan="7">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_modales_caja.php'; ?>
