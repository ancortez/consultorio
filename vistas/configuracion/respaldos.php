<?php
/**
 * Configuracion > Respaldos.
 *
 * Crear, descargar y restaurar sin entrar al servidor por ssh.
 *
 * El motivo de que esto exista no es la comodidad: un respaldo que vive
 * en el mismo servidor no protege del caso mas probable —que el servidor
 * se pierda— y bajarlo tiene que ser facil, porque lo que cuesta
 * trabajo no se hace. Antes de esta pantalla, bajar una copia pedia
 * ssh, saber la ruta y teclear un scp. Nadie lo hace cada semana.
 *
 * Solo el administrador entra aqui. El archivo que se descarga trae el
 * expediente clinico completo de todos los pacientes, en texto plano, y
 * restaurar reemplaza TODOS los expedientes por los de otra fecha.
 */
$respaldos = Respaldos::listar();
$resumen   = Respaldos::resumen();

/** Hace cuanto, en palabras. */
$hace = static function (?int $cuando): string {
    if ($cuando === null) {
        return 'nunca';
    }

    $horas = (int) floor((time() - $cuando) / 3600);

    if ($horas < 1)  { return 'hace un momento'; }
    if ($horas < 24) { return "hace $horas h"; }

    $dias = (int) floor($horas / 24);
    return $dias === 1 ? 'ayer' : "hace $dias dias";
};
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col" id="respaldosPantalla">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Respaldos</h1>
                <p class="subtitulo-vista">
                    Crear, descargar y restaurar
                </p>
            </div>
            <button class="btn btn-morado" id="rsCrear">
                <i class="bi bi-plus-lg me-1"></i> Crear respaldo ahora
            </button>
        </div>

        <?php /* ============ Lo primero: la copia de fuera ============

                 Este aviso va ARRIBA de todo y no al pie. Es la unica
                 cosa de esta pantalla que ningun boton resuelve: si el
                 servidor se pierde —el proveedor tiene un problema, una
                 factura sin pagar, un disco que muere— se van el sistema
                 y los respaldos juntos, porque viven en el mismo disco.

                 Lo que lo resuelve es bajar las copias a una
                 computadora, y para eso esta el boton de descargar de
                 cada renglon. */ ?>
        <div class="alert alert-light border d-flex gap-2 py-2 small mb-3">
            <i class="bi bi-exclamation-triangle-fill"
               style="color:var(--alerta)"></i>
            <span>
                Estos respaldos viven en <b>el mismo servidor</b> que el
                sistema. Si el servidor se pierde, se pierden con &eacute;l.
                <b>Baja una copia cada semana</b> a tu computadora y
                gu&aacute;rdala en otro lado.
                <?php if ($resumen['sin_bajar'] > 0): ?>
                <br>
                Ahora mismo hay <b><?= (int) $resumen['sin_bajar'] ?></b>
                respaldo(s) que nunca se han bajado.
                <?php endif; ?>
            </span>
        </div>

        <?php /* ============ Como va la cosa ============ */ ?>
        <div class="row g-3 mb-3">
            <div class="col-4">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary" style="font-size:11.5px">
                            <i class="bi bi-database me-1"></i>Base de datos
                        </div>
                        <div class="fs-5 fw-semibold" id="rsUltimaBase">
                            <?= e($hace($resumen['ultima_base'])) ?>
                        </div>
                        <div class="text-secondary" style="font-size:11.5px">
                            pacientes, consultas, cobros y agenda
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-4">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary" style="font-size:11.5px">
                            <i class="bi bi-images me-1"></i>Archivos del expediente
                        </div>
                        <div class="fs-5 fw-semibold" id="rsUltimoArchivo">
                            <?= e($hace($resumen['ultimo_archivo'])) ?>
                        </div>
                        <div class="text-secondary" style="font-size:11.5px">
                            radiograf&iacute;as, fotos y escaneos
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-4">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary" style="font-size:11.5px">
                            <i class="bi bi-archive me-1"></i>En el servidor
                        </div>
                        <div class="fs-5 fw-semibold" id="rsOcupan">
                            <?= e(tamanoLegible($resumen['bytes'])) ?>
                        </div>
                        <div class="text-secondary" style="font-size:11.5px"
                             id="rsCuantos">
                            <?= (int) $resumen['cuantos'] ?> respaldo(s)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php /* Los dos tipos tienen ritmos distintos y conviene
                 decirlo aqui: si no, la tarjeta de los archivos del
                 expediente parece atrasada todas las semanas. */ ?>
        <div class="alert alert-light border py-2 small mb-3 d-flex gap-2">
            <i class="bi bi-info-circle" style="color:var(--morado-600)"></i>
            <span>
                La <b>base de datos</b> se respalda sola todas las noches y se
                guardan 14 d&iacute;as. Los <b>archivos del expediente</b>
                pesan mucho m&aacute;s, as&iacute; que se respaldan los
                domingos y se guardan 4 copias.
                El bot&oacute;n de arriba crea uno de la <b>base</b>: para
                hacer uno de los archivos hay que correr
                <code>respaldar.sh --completo</code> en el servidor.
            </span>
        </div>

        <?php /* ============ La lista ============ */ ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul me-2"></i>Lo que hay
            </div>
            <div class="tabla-con-menu">
                <table class="table tabla-sistema align-middle">
                    <thead><tr>
                        <th style="width:230px">Respaldo</th>
                        <th style="width:130px">Qu&eacute; trae</th>
                        <th style="width:150px">Cu&aacute;ndo</th>
                        <th style="width:100px">Tama&ntilde;o</th>
                        <th>Estado</th>
                        <th class="col-acciones"></th>
                    </tr></thead>
                    <tbody id="rsTabla">
                        <tr class="fila-vacia"><td colspan="6">
                            <div class="spinner-border spinner-border-sm me-2"
                                 style="color:var(--morado-600)"></div>Cargando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php /* ============ Restaurar ============

         Lo mas peligroso que puede hacer este sistema, y el modal esta
         escrito para que se note. Pide escribir RESTAURAR a mano: una
         confirmacion de "¿seguro?" se contesta que si sin leer, y esto
         no se deshace. */ ?>
<div class="modal fade" id="modalRestaurar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          Restaurar un respaldo
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="formRestaurar">
        <input type="hidden" name="nombre" id="rtNombre">
        <div class="modal-body">
          <div class="aviso-peligro mb-3">
            <b>Esto reemplaza TODOS los expedientes</b> por los que
            hab&iacute;a cuando se hizo el respaldo. Lo que se captur&oacute;
            despu&eacute;s se pierde: consultas, cobros, citas, todo.
          </div>

          <div class="dato-identidad mb-3">
            <i class="bi bi-file-earmark-zip"></i>
            <div>
              <div class="etiqueta">Se va a restaurar</div>
              <div class="valor" id="rtArchivo">&mdash;</div>
              <div class="text-secondary" style="font-size:11.5px"
                   id="rtCuando"></div>
            </div>
          </div>

          <?php /* Que haya respaldo de seguridad se dice ANTES, no
                   despues: es lo que hace que esta decision sea
                   reversible, y saberlo cambia como se toma. */ ?>
          <div class="alert alert-light border py-2 small">
            <i class="bi bi-shield-check me-1" style="color:var(--exito)"></i>
            Antes de tocar nada se guarda un respaldo de lo que hay
            ahora, por si el archivo elegido no era el correcto.
          </div>

          <label class="form-label" for="rtConfirmacion">
            Escribe <b>RESTAURAR</b> para confirmar
          </label>
          <input type="text" class="form-control" name="confirmacion"
                 id="rtConfirmacion" autocomplete="off"
                 placeholder="RESTAURAR">
          <div class="invalid-feedback" id="rtError"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            Cancelar
          </button>
          <button type="submit" class="btn btn-danger" id="rtAceptar">
            <i class="bi bi-arrow-counterclockwise me-1"></i>
            Restaurar la base
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
