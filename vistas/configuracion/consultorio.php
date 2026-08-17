<?php
/**
 * Configuracion > Datos del consultorio.
 *
 * Estos datos salen impresos en el consentimiento y en el pie de pagina
 * del sistema, asi que conviene que el consultorio los pueda corregir
 * solo, sin depender de que alguien toque la base.
 */
$estados   = Conexion::todos('SELECT * FROM estado ORDER BY nombre_estado');
$estadoDef = (int) parametro('estado_default', (string) ESTADO_DEFAULT);
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Datos del consultorio</h1>
                <p class="subtitulo-vista">
                    Aparecen en el pie del sistema y en los documentos impresos
                </p>
            </div>
            <button type="submit" form="formConsultorio" class="btn btn-morado">
                <i class="bi bi-check-lg me-1"></i> Guardar cambios
            </button>
        </div>

        <form id="formConsultorio" autocomplete="off">
            <div class="row g-3">

                <!-- ============ Identidad ============ -->
                <div class="col-7">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="bi bi-shop me-2"></i>Identidad
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">
                                        Nombre comercial <span class="req">*</span>
                                    </label>
                                    <input type="text" class="form-control"
                                           name="nombre_comercial" maxlength="120" required>
                                    <div class="form-text">
                                        Como aparece en la papeler&iacute;a del consultorio.
                                    </div>
                                </div>
                                <div class="col-8">
                                    <label class="form-label">Raz&oacute;n social</label>
                                    <input type="text" class="form-control"
                                           name="razon_social" maxlength="150">
                                    <div class="form-text">Para facturaci&oacute;n.</div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">RFC</label>
                                    <input type="text" class="form-control" name="rfc" maxlength="13">
                                </div>
                                <div class="col-5">
                                    <label class="form-label">Tel&eacute;fono</label>
                                    <input type="tel" class="form-control" name="telefono"
                                           maxlength="20" placeholder="3113003760">
                                </div>
                                <div class="col-7">
                                    <label class="form-label">Correo electr&oacute;nico</label>
                                    <input type="email" class="form-control sin-mayusculas"
                                           name="correo" maxlength="120">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ Vista previa ============ -->
                <div class="col-5">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="bi bi-eye me-2"></i>As&iacute; se ver&aacute;
                        </div>
                        <div class="card-body">
                            <p class="text-secondary" style="font-size:12px">
                                Encabezado de los documentos impresos:
                            </p>
                            <div style="background:#8b5a96;border-radius:10px;padding:.9rem 1rem;
                                        display:flex;align-items:center;justify-content:space-between;
                                        gap:.8rem">
                                <div style="background:#fff;border-radius:8px;padding:.4rem .7rem">
                                    <div style="font-weight:700;color:#6b4574;font-size:13px"
                                         id="vistaNombre">Consultorio Dental</div>
                                    <div style="font-size:8px;letter-spacing:.08em;color:#8b5a96;
                                                text-transform:uppercase">
                                        Odontopediatr&iacute;a y ortopedia maxilar
                                    </div>
                                </div>
                                <div style="color:#fff;font-size:10px;font-weight:700;
                                            text-transform:uppercase">Documento</div>
                            </div>

                            <div class="mt-2" style="font-size:10.5px;color:var(--gris-600);
                                        display:flex;justify-content:space-between;gap:.5rem">
                                <span id="vistaDomicilio">Domicilio por definir</span>
                                <span id="vistaTelefono"></span>
                            </div>

                            <hr>

                            <p class="text-secondary" style="font-size:12px">Pie del sistema:</p>
                            <div style="background:var(--morado-900);color:rgba(255,255,255,.85);
                                        border-radius:8px;padding:.45rem .8rem;font-size:11px;
                                        display:flex;justify-content:space-between;gap:1rem">
                                <span id="vistaPie">Domicilio por definir</span>
                                <span>v<?= e(APP_VERSION) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ Domicilio ============ -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-geo-alt me-2"></i>Domicilio
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-3">
                                    <label class="form-label">Estado <span class="req">*</span></label>
                                    <select class="form-select" name="id_estado" id="cnsEstado"
                                            data-default="<?= $estadoDef ?>" required>
                                        <?php foreach ($estados as $es): ?>
                                            <option value="<?= (int) $es['id_estado'] ?>"
                                                <?= (int) $es['id_estado'] === $estadoDef ? 'selected' : '' ?>>
                                                <?= e($es['nombre_estado']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <label class="form-label">Municipio <span class="req">*</span></label>
                                    <select class="form-select" name="id_municipio" id="cnsMunicipio" required>
                                        <option value="">Cargando...</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Colonia <span class="req">*</span></label>
                                    <input type="text" readonly class="form-control selector-modal"
                                           id="cnsColoniaTexto"
                                           data-selector="colonia"
                                           data-destino="cnsColonia"
                                           data-municipio="cnsMunicipio"
                                           data-cp-destino="cnsCp"
                                           placeholder="Selecciona el municipio primero">
                                    <input type="hidden" name="id_colonia" id="cnsColonia">
                                </div>
                                <div class="col-2">
                                    <label class="form-label">C&oacute;digo postal</label>
                                    <input type="text" class="form-control" id="cnsCp" readonly>
                                </div>

                                <div class="col-12">
                                    <div class="aviso-bloqueo" id="cnsAvisoDomicilio">
                                        <i class="bi bi-info-circle"></i>
                                        <span>Selecciona la colonia para capturar el resto del domicilio.</span>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label">Calle</label>
                                    <input type="text" class="form-control cns-campo-domicilio"
                                           name="calle" maxlength="150">
                                </div>
                                <div class="col-3">
                                    <label class="form-label">N&uacute;mero exterior</label>
                                    <input type="text" class="form-control cns-campo-domicilio"
                                           name="num_ext" maxlength="20">
                                </div>
                                <div class="col-3">
                                    <label class="form-label">N&uacute;mero interior</label>
                                    <input type="text" class="form-control cns-campo-domicilio"
                                           name="num_int" maxlength="20">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Entre calle 1</label>
                                    <input type="text" class="form-control cns-campo-domicilio"
                                           name="entre_calle_1" maxlength="150">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Entre calle 2</label>
                                    <input type="text" class="form-control cns-campo-domicilio"
                                           name="entre_calle_2" maxlength="150">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Referencia</label>
                                    <input type="text" class="form-control cns-campo-domicilio"
                                           name="referencia" maxlength="255"
                                           placeholder="FRENTE AL PARQUE, EDIFICIO BLANCO...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mt-3">
                <button type="submit" class="btn btn-morado">
                    <i class="bi bi-check-lg me-1"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php require RUTA_VISTAS . '/paciente/_modal_colonia.php'; ?>
