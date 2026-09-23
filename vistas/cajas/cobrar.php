<?php
/**
 * Cajas > Cobrar.
 *
 * El caso normal del consultorio no es "pagar una consulta": es ir
 * abonando a un plan de doce mil pesos. Por eso la pantalla arranca
 * mostrando lo que el paciente debe, no una lista de conceptos que
 * haya que armar desde cero.
 *
 * El flujo es de izquierda a derecha, como se lee:
 *   1. Buscar al paciente
 *   2. Ver lo que debe y elegir a que se aplica
 *   3. Capturar con que paga (puede ser mas de una forma)
 *   4. Cobrar e imprimir
 */
$idExpediente = paramInt('paciente');
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Cobrar</h1>
                <p class="subtitulo-vista" id="cbTurnoTexto">
                    Revisando el turno de caja...
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <label class="form-label mb-0 text-secondary" style="font-size:12px">
                    Caja
                </label>
                <select class="form-select" id="cbCaja" style="width:200px"></select>
            </div>
        </div>

        <?php /* Sin turno abierto no se cobra: un cobro fuera de turno
                 no cae en ningun corte y desaparece de la cuenta del
                 dia. El aviso trae el boton para abrirlo. */ ?>
        <div class="aviso-vinculado mb-3 d-none" id="cbSinTurno">
            <i class="bi bi-lock-fill"></i>
            <div>
                <b>Esta caja no tiene turno abierto.</b><br>
                Un cobro fuera de turno no entra en ning&uacute;n corte.
                <?php if (Auth::puede('cajas.corte')): ?>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-morado" id="cbAbrirTurno">
                        <i class="bi bi-unlock me-1"></i> Abrir el turno
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <!-- ============ Paciente y lo que debe ============ -->
            <div class="col-7">
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <label class="form-label" for="cbBuscar">Paciente</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control" id="cbBuscar"
                                   placeholder="Nombre, expediente o tel&eacute;fono"
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button"
                                    id="cbLimpiar" title="Limpiar">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="lista-seleccion border rounded mt-2 d-none"
                             id="cbResultados"></div>
                    </div>
                </div>

                <div class="card d-none" id="cbCuenta">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-person-vcard me-2"></i>
                              <b id="cbPacienteNombre"></b></span>
                        <a class="btn btn-sm btn-light" id="cbVerEstado" href="#">
                            <i class="bi bi-file-earmark-text me-1"></i> Estado de cuenta
                        </a>
                    </div>

                    <div class="card-body pb-2">
                        <div class="resumen-saldo mb-3" id="cbResumen"></div>

                        <p class="form-label">Pendientes de pago</p>
                        <div class="form-text mb-2">
                            Marca a qu&eacute; se aplica. Si no marcas nada, el abono
                            se reparte solo, empezando por lo m&aacute;s viejo.
                        </div>
                    </div>

                    <div class="tabla-con-menu">
                        <table class="table tabla-sistema mb-0">
                            <thead><tr>
                                <th style="width:40px"></th>
                                <th>Concepto</th>
                                <th style="width:100px">Fecha</th>
                                <th style="width:110px" class="text-end">Debe</th>
                                <th style="width:130px" class="text-end">Abonar</th>
                            </tr></thead>
                            <tbody id="cbCargos"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============ Con que paga ============ -->
            <div class="col-5">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-wallet2 me-2"></i>Formas de pago</span>
                        <button type="button" class="btn btn-sm btn-rosa" id="cbAgregarForma">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>

                    <div class="card-body">
                        <?php /* Pago mixto: quinientos en efectivo y mil con
                                 tarjeta es un solo cobro, no dos. */ ?>
                        <div id="cbFormas"></div>

                        <div class="dato-identidad mt-2"
                             style="border-top:2px solid var(--gris-100)">
                            <i class="bi bi-cash-stack"></i>
                            <div class="flex-grow-1 d-flex justify-content-between">
                                <span class="etiqueta fw-semibold">TOTAL A COBRAR</span>
                                <span class="fs-4 fw-semibold"
                                      style="color:var(--morado-800)" id="cbTotal">$0.00</span>
                            </div>
                        </div>

                        <div class="alert alert-light border py-2 small d-none mt-2 mb-0"
                             id="cbAvisoFavor"></div>

                        <div class="mt-3">
                            <label class="form-label">Observaciones</label>
                            <input type="text" class="form-control form-control-sm"
                                   id="cbObservaciones" maxlength="200">
                        </div>

                        <button type="button" class="btn btn-morado w-100 mt-3"
                                id="cbCobrar" disabled>
                            <i class="bi bi-check-lg me-1"></i> Registrar cobro
                        </button>
                    </div>
                </div>

                <!-- Cobros del turno, por si hay que cancelar uno -->
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-receipt me-2"></i>Cobros de este turno
                    </div>
                    <div class="card-body py-2" id="cbUltimos">
                        <div class="exp-vacio">Todav&iacute;a no hay cobros.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_modales_caja.php'; ?>
