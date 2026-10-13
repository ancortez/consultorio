<?php
/**
 * La fotografia del paciente, como un campo mas de los datos generales.
 *
 * Hasta ahora la foto solo vivia en el circulo de la tarjeta de arriba,
 * y ahi lo unico que se podia hacer era reemplazarla. Pero una foto es
 * un documento del expediente igual que la credencial o el
 * consentimiento: hay que poder verla, bajarla y quitarla.
 *
 * Dos caras, como el resto de la ficha:
 *
 *   - con foto:  la miniatura (que se abre al darle clic) y los tres
 *                botones de siempre; eliminar va detras de los tres
 *                puntos, porque borrar no se pone al lado de ver
 *   - sin foto:  un boton de camara que lleva directo a tomarla
 *
 * El id_documento se busca aqui y no en el navegador: la tarjeta de
 * arriba lo pedia por AJAX al dar clic, y si el documento no existia
 * todavia lo creaba sobre la marcha. Teniendolo ya resuelto al pintar
 * la pagina, el boton sabe desde el principio que tiene delante.
 */

// El documento de tipo FOTO_PACIENTE, si es que ya se creo. Puede
// existir como pendiente (solicitado, sin archivo) o ya digitalizado.
$docFoto = Conexion::uno(
    'SELECT d.id_documento, d.estado, d.tipo_mime, d.nombre_original,
            td.formatos
       FROM documento d
       JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
      WHERE d.id_registro_paciente = :r
        AND td.clave = :clave
        AND d.estatus = 1
      ORDER BY d.estado DESC, d.id_documento DESC
      LIMIT 1',
    [':r' => $idExpediente, ':clave' => 'FOTO_PACIENTE']
);

// Lo que decide que cara se pinta es la foto recortada de la persona,
// no el documento: es la que se ve en el circulo y la que se descarga.
// Un documento solicitado pero sin subir todavia es, para esta ficha,
// un paciente sin fotografia.
$tieneFoto = !empty($pac['foto']);
$puedeEditarFoto = Auth::puede('paciente.editar');
?>

<div class="campo-ficha" data-campo="fotografia">
    <span class="cf-etiqueta">Fotograf&iacute;a</span>

    <?php if ($tieneFoto): ?>
    <div class="cf-fila campo-foto">
        <?php /* La miniatura es el boton de ver: es lo que uno intenta
                 picar, y un boton de lupa al lado seria repetir el
                 mismo gesto en dos sitios. */ ?>
        <img class="foto-miniatura"
             src="<?= URL_BASE ?>/avatar.php?p=<?= $idPersona ?>"
             alt="Fotograf&iacute;a del paciente"
             data-ver-foto="<?= $idPersona ?>"
             title="Ver la fotograf&iacute;a">

        <div class="foto-acciones">
            <button type="button" class="btn-icono" data-ver-foto="<?= $idPersona ?>"
                    title="Visualizar">
                <i class="bi bi-eye"></i>
            </button>

            <?php /* Descargar sale del documento original, no del
                     recorte: el recorte es un cuadro chico hecho para
                     el circulo, y quien baja la foto la quiere
                     completa. Si todavia no hay documento —porque la
                     foto entro por el camino viejo— se baja el
                     recorte, que es lo unico que hay. */ ?>
            <a class="btn-icono" title="Descargar"
               download
               href="<?= $docFoto && (int) $docFoto['estado'] === 1
                        ? URL_BASE . '/archivo.php?id=' . (int) $docFoto['id_documento'] . '&d=1'
                        : URL_BASE . '/avatar.php?p=' . $idPersona ?>">
                <i class="bi bi-download"></i>
            </a>

            <?php if ($puedeEditarFoto): ?>
            <button type="button" class="btn-icono" data-cambiar-foto="1"
                    title="Reemplazar">
                <i class="bi bi-arrow-repeat"></i>
            </button>

            <div class="dropdown">
                <button type="button" class="btn-icono" data-bs-toggle="dropdown"
                        title="M&aacute;s acciones">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                                data-quitar-foto="<?= $docFoto
                                    ? (int) $docFoto['id_documento'] : 0 ?>">
                            <i class="bi bi-trash me-2"></i> Eliminar fotograf&iacute;a
                        </button>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <div class="cf-fila campo-foto">
        <?php if ($puedeEditarFoto): ?>
        <button type="button" class="boton-camara" data-cambiar-foto="1">
            <i class="bi bi-camera"></i>
            <span>Agregar fotograf&iacute;a</span>
        </button>
        <?php else: ?>
        <span class="cf-valor vacio">Sin fotograf&iacute;a</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
