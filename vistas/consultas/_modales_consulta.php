<?php
/**
 * Modales del detalle de consulta: hallazgo, receta, nota de evolucion
 * y subida de documentos por album.
 */
?>

<!-- ============ Hallazgo ============ -->
<div class="modal fade" id="modalHallazgo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-search-heart me-2"></i>
          <span id="hzTitulo">Nuevo hallazgo</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formHallazgo">
        <input type="hidden" name="id_hallazgo" id="hzId">
        <input type="hidden" name="id_consulta" id="hzConsulta">

        <div class="modal-body">
          <?php /* El tipo va primero y como botones, no como lista.

                   Es la primera decision y solo tiene dos respuestas:
                   lo vi en la boca, o lo vi en la placa. Dos botones
                   grandes se contestan de un toque; un desplegable
                   pide abrir, leer y elegir para lo mismo. */ ?>
          <label class="form-label">&iquest;D&oacute;nde lo viste?</label>
          <div class="grupo-tipo-hallazgo mb-3" id="hzTipo">
            <button type="button" class="opcion-hallazgo activa" data-valor="CLINICO">
              <i class="bi bi-eye"></i>
              <b>Cl&iacute;nico</b>
              <small>En boca, a simple vista</small>
            </button>
            <button type="button" class="opcion-hallazgo" data-valor="RADIOGRAFICO">
              <i class="bi bi-radioactive"></i>
              <b>Radiogr&aacute;fico</b>
              <small>En la placa</small>
            </button>
          </div>
          <input type="hidden" name="tipo" id="hzTipoValor" value="CLINICO">

          <?php /* Ya no dice "(opcional)".

                   Decirlo era correcto y sobraba: el campo llega con su
                   codigo puesto —CLIN_01, RADIO_01— asi que no hay nada
                   que dejar en blanco. Lo que se hace es dejarlo como
                   viene o escribir encima. */ ?>
          <label class="form-label" for="hzDescripcion">Descripci&oacute;n</label>
          <textarea class="form-control" name="descripcion" id="hzDescripcion"
                    rows="3" maxlength="1000"
                    placeholder="CARIES OCLUSAL EN &Oacute;RGANO DENTARIO 46"></textarea>
          <div class="form-text mb-3">
            Uno por hallazgo. Si observaste tres cosas, agrega tres.
            Viene con su c&oacute;digo puesto seg&uacute;n d&oacute;nde lo
            viste; escribe encima si quieres describirlo.
          </div>

          <?php /* Las imagenes son opcionales y se suben aqui mismo.

                   Pueden ser varias: "fractura del 11" se documenta
                   con la foto intraoral y con dos periapicales desde
                   angulos distintos, y las tres son del mismo
                   hallazgo.

                   Obligar a cerrar el modal, abrir el album, subirlas
                   y volver a escribir a que hallazgo pertenecen es
                   justo lo que nadie hace con un paciente enfrente. */ ?>
          <label class="form-label" for="hzArchivos">
            Im&aacute;genes <span class="text-secondary fw-normal">(opcional)</span>
          </label>
          <?php /* El accept lo dice Imagen, no esta linea.

                   Estaba escrito a mano aqui y se quedo atras cuando el
                   nucleo empezo a aceptar HEIC: el selector de archivos
                   atenuaba las fotos del iPhone, no se podian elegir, y
                   parecia que el sistema no las aceptaba. Las aceptaba;
                   nadie podia llegar a el. */ ?>
          <input type="file" class="form-control" name="archivos[]" id="hzArchivos"
                 accept="<?= e(Imagen::aceptaEnPicker()) ?>" multiple>
          <div class="form-text">
            Puedes elegir varias. Se guardan como radiograf&iacute;as o
            como fotograf&iacute;as cl&iacute;nicas seg&uacute;n lo que
            elegiste arriba, y se reducen solas para no ocupar de
            m&aacute;s.
          </div>

          <div id="hzImagenActual" class="mt-3 d-none">
            <span class="et d-block mb-1">
              Ya guardadas <span id="hzImagenCuenta"></span>
            </span>
            <div class="tira-hallazgo" id="hzImagenLista"></div>
            <div class="form-text">
              Lo que subas ahora se <b>agrega</b>; no reemplaza lo que ya hay.
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado" id="hzGuardar">
            <i class="bi bi-check-lg me-1"></i> Guardar hallazgo
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Receta ============ -->
<div class="modal fade" id="modalReceta" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-prescription2 me-2"></i>
          <span id="rxTitulo">Nueva receta</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formReceta">
        <input type="hidden" name="id_receta" id="rxId">
        <input type="hidden" name="id_consulta" id="rxConsulta">

        <div class="modal-body">
          <?php /* Los datos de arriba no se capturan: salen de la
                   consulta. Se muestran para que quien receta vea sobre
                   quien esta recetando, que es de lo que se trata. */ ?>
          <div class="rx-encabezado mb-3">
            <div>
              <span class="et">Paciente</span>
              <span class="va" id="rxPaciente">&mdash;</span>
            </div>
            <div>
              <span class="et">Edad</span>
              <span class="va" id="rxEdad">&mdash;</span>
            </div>
            <div>
              <span class="et">Fecha</span>
              <input type="date" class="form-control form-control-sm"
                     name="fecha" id="rxFecha">
            </div>
          </div>

          <label class="form-label" for="rxIndicaciones">
            Indicaciones <span class="req">*</span>
          </label>
          <textarea class="form-control rx-cuerpo" name="indicaciones"
                    id="rxIndicaciones" rows="12"
                    placeholder="UN RENGL&Oacute;N POR MEDICAMENTO:&#10;&#10;PARACETAMOL 100 MG/ML GOTAS&#10;0.5 ML CADA 8 HORAS POR 3 DIAS&#10;&#10;AMOXICILINA SUSPENSI&Oacute;N 250 MG/5 ML&#10;5 ML CADA 8 HORAS POR 7 DIAS"></textarea>

          <div class="alert alert-light border py-2 small d-flex gap-2 mt-3 mb-0">
            <i class="bi bi-printer" style="color:var(--morado-600)"></i>
            <span>
              Al imprimir sale <b>solo lo que escribiste</b>, acomodado en
              los espacios del recetario que ya tiene el consultorio.
              El membrete y el pie no se imprimen porque ya est&aacute;n en
              el papel.
            </span>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">
            <i class="bi bi-check-lg me-1"></i> Guardar receta
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php /* ============ De que plan es esta consulta ============

         Se pregunta de entrada, antes de escribir nada, y no con un
         combo en la barra de arriba.

         La diferencia no es de forma. El plan decide en que parte del
         expediente caen los papeles de la consulta y empuja el plan a
         "En proceso" al guardarse la primera. Dejado como un combo con
         "Consulta general" preseleccionado, lo que pasa es que nadie lo
         toca: las consultas de un plan de ortodoncia quedan sueltas en
         el bloque general y el plan se queda en "Aceptado" para
         siempre, aunque el paciente lleve seis citas.

         Preguntado de frente tiene respuesta, porque es la primera cosa
         que se sabe de la cita: "vino por su plan" o "vino por otra
         cosa". Y "Consulta general" sigue siendo una respuesta valida,
         no un castigo: esta ahi abajo, con su propio boton.

         Sin boton de cerrar ni escape: no es un aviso, es la pregunta
         que arma el resto de la pantalla. Se contesta una vez y se
         puede cambiar despues con el lapiz del plan. */ ?>
<div class="modal fade" id="modalPlanConsulta" tabindex="-1"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-diagram-3 me-2"></i>&iquest;De qu&eacute; es esta consulta?
        </h5>
      </div>

      <div class="modal-body">
        <p class="text-secondary small mb-3">
          Este paciente tiene tratamiento abierto. Si la cita de hoy es
          de uno de ellos, el motivo y el padecimiento se escriben
          solos y los papeles de hoy quedan guardados dentro de ese
          plan.
        </p>

        <div id="pcPlanes" class="d-grid gap-2"></div>

        <hr class="my-3">

        <button type="button" class="btn btn-light w-100 text-start"
                id="pcGeneral">
          <i class="bi bi-person me-2"></i>
          <b>Consulta general</b>
          <div class="text-secondary" style="font-size:11.5px">
            Urgencia, revisi&oacute;n, limpieza: algo que no es parte de un
            plan.
          </div>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============ Nota de evolucion ============ -->
<div class="modal fade" id="modalNota" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-journal-text me-2"></i>Nota de evoluci&oacute;n
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formNota">
        <input type="hidden" name="id_consulta" id="notaConsulta">
        <div class="modal-body">
          <textarea class="form-control" name="texto" id="notaTexto" rows="6"
                    placeholder="LO QUE SE OBSERV&Oacute;, LO QUE SE ACORD&Oacute; CON EL TUTOR, C&Oacute;MO RESPONDI&Oacute; EL PACIENTE"></textarea>
          <div class="form-text">
            La nota queda firmada con tu nombre y la hora. No se puede
            editar despu&eacute;s: si hay que aclarar algo, se agrega otra nota.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Agregar nota</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Album de documentos ============ -->
<div class="modal fade" id="modalAlbum" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-images me-2"></i>
          <span id="albumTitulo">Agregar documentos</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formAlbum" enctype="multipart/form-data">
        <input type="hidden" name="clave" id="albumClave">
        <input type="hidden" name="id_consulta" id="albumConsulta">

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Descripci&oacute;n</label>
            <input type="text" class="form-control" name="descripcion"
                   id="albumDescripcion" maxlength="200">
            <div class="form-text" id="albumAyuda"></div>
          </div>

          <div class="zona-soltar" id="albumZona">
            <i class="bi bi-file-earmark-arrow-up"></i>
            <div class="fw-semibold">Arrastra los archivos aqu&iacute;</div>
            <div style="font-size:12px">
              o da clic para buscarlos. Puedes elegir varios de una vez.
            </div>
            <input type="file" name="archivos[]" id="albumArchivos" class="d-none" multiple>
          </div>

          <div class="album-previo mt-3" id="albumPrevio"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado" id="albumBoton" disabled>
            <i class="bi bi-cloud-arrow-up me-1"></i>
            <span id="albumBotonTexto">Guardar</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ Cargo de la consulta ============ -->
<div class="modal fade" id="modalCargo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-cash-coin me-2"></i>Cargo al paciente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formCargo">
        <input type="hidden" name="id_consulta" id="cgConsulta">
        <input type="hidden" name="id_registro_paciente" id="cgPaciente">
        <input type="hidden" name="id_procedimiento" id="cgProcedimiento">

        <div class="modal-body">
          <?php /* El catalogo trae el precio sugerido; se puede cambiar
                   por caso, que es lo que pasa a diario. Lo que no se
                   acepta es un importe ya calculado desde el navegador. */ ?>
          <div class="mb-3">
            <label class="form-label">Procedimiento</label>
            <div class="input-group">
              <input type="text" class="form-control" id="cgProcTexto"
                     placeholder="Ninguno del cat&aacute;logo" readonly>
              <button type="button" class="btn btn-outline-morado" id="cgBuscarProc">
                <i class="bi bi-search"></i>
              </button>
              <button type="button" class="btn btn-light" id="cgQuitarProc"
                      title="Quitar">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <div class="form-text">
              Opcional. Si lo eliges, se llenan la descripci&oacute;n y el precio.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Descripci&oacute;n <span class="req">*</span></label>
            <input type="text" class="form-control" name="descripcion"
                   id="cgDescripcion" maxlength="200" required>
          </div>

          <div class="row g-3">
            <div class="col-3">
              <label class="form-label">Cantidad</label>
              <input type="number" class="form-control text-center" name="cantidad"
                     id="cgCantidad" value="1" min="1" max="99">
            </div>
            <div class="col-5">
              <label class="form-label">Precio unitario <span class="req">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control text-end" name="precio_unitario"
                       id="cgPrecio" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-4">
              <label class="form-label">Importe</label>
              <input type="text" class="form-control text-end fw-semibold"
                     id="cgImporte" value="$0.00" readonly>
            </div>
          </div>

          <?php if (Auth::puede('cajas.descuento')): ?>
          <hr>
          <div class="row g-3">
            <div class="col-4">
              <label class="form-label">Descuento</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control text-end" name="descuento"
                       id="cgDescuento" value="0" step="0.01" min="0">
              </div>
            </div>
            <div class="col-8">
              <label class="form-label">Motivo del descuento</label>
              <input type="text" class="form-control" name="motivo_descuento"
                     id="cgMotivoDesc" maxlength="150"
                     placeholder="HERMANO DE PACIENTE, CORTESIA...">
            </div>
          </div>
          <div class="form-text mt-2">
            El descuento queda registrado a tu nombre.
          </div>
          <?php else: ?>
          <input type="hidden" name="descuento" value="0">
          <div class="alert alert-light border py-2 small d-flex gap-2 mt-3 mb-0">
            <i class="bi bi-lock" style="color:var(--gris-600)"></i>
            <span>No tienes permiso para autorizar descuentos.</span>
          </div>
          <?php endif; ?>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-morado">Registrar cargo</button>
        </div>
      </form>
    </div>
  </div>
</div>
