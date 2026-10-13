<?php
/**
 * Alta y edicion de un hallazgo de la consulta.
 *
 * Un hallazgo es UNA observacion: "caries oclusal en 46", "gingivitis
 * leve en sector anterior". Antes todas iban juntas en una caja de
 * texto, y entonces no habia forma de colgarle una radiografia a una
 * sola de ellas ni de saber, en la siguiente cita, cual se resolvio.
 *
 * La imagen es opcional y se sube aqui mismo. Hacerlo en dos pasos
 * —guardar el hallazgo, cerrar, abrir el album, subir, volver a
 * explicar a cual pertenece— es exactamente lo que nadie hace con un
 * paciente sentado enfrente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idHallazgo  = paramInt('id_hallazgo');
$idConsulta  = paramInt('id_consulta');
$tipo        = mayus((string) param('tipo', 'CLINICO'));
$descripcion = paramTexto('descripcion');

const TIPOS_HALLAZGO = ['CLINICO', 'RADIOGRAFICO'];

if (!$idConsulta) {
    Respuesta::error('Falta la consulta.');
}
if (!in_array($tipo, TIPOS_HALLAZGO, true)) {
    Respuesta::validacion(['tipo' => 'Elige si es clinico o radiografico.']);
}
if ($descripcion === null || mb_strlen($descripcion) < 3) {
    Respuesta::validacion(['descripcion' => 'Escribe que observaste.']);
}

$consulta = Conexion::uno(
    'SELECT id_consulta, id_registro_paciente, id_plan_tratamiento, cerrada
       FROM consulta WHERE id_consulta = :c AND estatus = 1',
    [':c' => $idConsulta]
);
if ($consulta === null) {
    Respuesta::error('La consulta no existe.', 404);
}

// Una consulta cerrada no se edita. Lo que se observo ese dia quedo
// registrado; si hay algo que aclarar, va como nota de evolucion, que
// es la forma correcta de corregir un expediente: se agrega, no se
// sobrescribe.
if ((int) $consulta['cerrada'] === 1) {
    Respuesta::error(
        'Esta consulta ya esta cerrada. Para agregar algo, usa una nota '
        . 'de evolucion.');
}

// ---------------------------------------------------------------------
// Guardado del hallazgo
// ---------------------------------------------------------------------
// El hallazgo se guarda ANTES que las imagenes, al reves que antes.
//
// Tiene que ser asi: cada imagen necesita el id del hallazgo para
// colgarse de el. Cuando era una sola imagen se podia crear primero y
// coser el enlace despues; con varias, ese remiendo se multiplica y
// basta con que una falle para dejar documentos apuntando a nada.
if ($idHallazgo) {
    $ya = Conexion::uno(
        'SELECT id_hallazgo, id_documento FROM hallazgo
          WHERE id_hallazgo = :h AND id_consulta = :c AND estatus = 1',
        [':h' => $idHallazgo, ':c' => $idConsulta]
    );
    if ($ya === null) {
        Respuesta::error('El hallazgo no existe.', 404);
    }

    // Las imagenes que ya tenia se quedan. Editar el texto de un
    // hallazgo no debe borrarle las radiografias; lo que se suba ahora
    // se agrega a lo que hay.
    Conexion::consulta(
        'UPDATE hallazgo SET tipo = :t, descripcion = :d
          WHERE id_hallazgo = :h',
        [':t' => $tipo, ':d' => $descripcion, ':h' => $idHallazgo]
    );
    $accion = 'CAMBIO';
} else {
    // El orden sale del ultimo que haya: los hallazgos se leen en el
    // orden en que se dictaron, que es el orden de la exploracion.
    $orden = 1 + (int) Conexion::valor(
        'SELECT COALESCE(MAX(orden), 0) FROM hallazgo
          WHERE id_consulta = :c AND estatus = 1',
        [':c' => $idConsulta]
    );

    Conexion::consulta(
        'INSERT INTO hallazgo
            (id_consulta, tipo, descripcion, orden, id_usuario)
         VALUES (:c, :t, :d, :o, :u)',
        [':c' => $idConsulta, ':t' => $tipo, ':d' => $descripcion,
         ':o' => $orden, ':u' => Auth::id()]
    );
    $idHallazgo = Conexion::ultimoId();
    $accion = 'ALTA';
}

// ---------------------------------------------------------------------
// Las imagenes
// ---------------------------------------------------------------------
// Un hallazgo puede traer varias: "fractura del 11" se documenta con la
// foto intraoral y con dos periapicales desde angulos distintos.
//
// El tipo de hallazgo decide donde se archivan: lo que se vio en la
// placa es una radiografia, lo que se vio en boca es una fotografia
// clinica. Asi el album y el expediente los separan solos, sin que
// nadie tenga que elegirlo dos veces.
$guardadas = 0;
$fallidas  = [];
// Los motivos como claves: dos fotos que fallan por lo mismo dan un
// solo renglon. Pegarlos a cada nombre llenaba media pantalla con el
// mismo parrafo repetido.
$motivos   = [];

if (!empty($_FILES['archivos']['name']) && is_array($_FILES['archivos']['name'])) {

    $clave = $tipo === 'RADIOGRAFICO' ? 'RADIOGRAFIA' : 'FOTOGRAFIA';
    $total = count($_FILES['archivos']['name']);

    if ($total > 10) {
        Respuesta::validacion(
            ['archivos' => 'Son demasiadas imagenes para un hallazgo. '
                         . 'Sube maximo 10.']);
    }

    for ($i = 0; $i < $total; $i++) {
        if ((int) $_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $archivo = [
            'name'     => $_FILES['archivos']['name'][$i],
            'type'     => $_FILES['archivos']['type'][$i],
            'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
            'error'    => $_FILES['archivos']['error'][$i],
            'size'     => $_FILES['archivos']['size'][$i],
        ];

        // La descripcion sale del hallazgo, numerada cuando son
        // varias: asi en el expediente el archivo se llama como lo que
        // muestra y no "IMG_4471.JPG".
        $nombre = mb_substr($descripcion, 0, 110)
                . ($total > 1 ? ' (' . ($i + 1) . ')' : '');

        $idDocumento = Documentos::crearPendiente(
            (int) $consulta['id_registro_paciente'], $clave, $nombre,
            null, null,
            $consulta['id_plan_tratamiento'] !== null
                ? (int) $consulta['id_plan_tratamiento'] : null,
            $idConsulta
        );

        if ($idDocumento === null) {
            $fallidas[] = $archivo['name'];
            continue;
        }

        try {
            Documentos::digitalizar($idDocumento, $archivo);
            Conexion::consulta(
                'UPDATE documento SET id_hallazgo = :h WHERE id_documento = :d',
                [':h' => $idHallazgo, ':d' => $idDocumento]);
            $guardadas++;
        } catch (Throwable $e) {
            // El pendiente vacio no se queda ensuciando el expediente.
            // Y el hallazgo NO se deshace: el texto ya vale por si
            // solo, y perderlo por una imagen que fallo seria
            // desproporcionado.
            Conexion::consulta(
                'DELETE FROM documento WHERE id_documento = :d AND estado = 0',
                [':d' => $idDocumento]);
            /*
               El MOTIVO viaja, no solo el nombre del archivo.

               Antes solo iba al log y la pantalla decia "no se
               pudieron guardar: IMG_4821.HEIC". Con eso, lo unico que
               sabe quien esta capturando es que algo fallo, y el unico
               que puede leer el log soy yo. Eso convirtio un paquete
               que faltaba en el servidor en semanas de fotos que no se
               veian: el sistema sabia exactamente que pasaba y no lo
               decia.
            */
            $fallidas[] = $archivo['name'];
            $motivos[$e->getMessage()] = true;
            error_log('[HALLAZGO] imagen no guardada: ' . $e->getMessage());
        }
    }
}

Bitacora::registrar('consultas', $accion, 'hallazgo', (string) $idHallazgo,
    ['consulta' => $idConsulta, 'tipo' => $tipo, 'imagenes' => $guardadas]);

// Si una imagen fallo se dice, pero el hallazgo ya quedo: callarlo
// dejaria a la doctora creyendo que subio tres radiografias cuando
// solo entraron dos.
$mensaje = $accion === 'ALTA' ? 'Hallazgo agregado' : 'Hallazgo actualizado';
if ($guardadas > 0) {
    $mensaje .= " con $guardadas imagen" . ($guardadas > 1 ? 'es' : '');
}
/*
   Los nombres juntos y el motivo UNA vez.

   Antes el motivo se pegaba a cada nombre, y subiendo tres fotos que
   fallan por lo mismo salia el mismo parrafo tres veces: un muro de
   texto que tapaba media pantalla y que no se lee. Angel me mando la
   foto; el mensaje era correcto y aun asi era inservible.

   Si los motivos son distintos se dicen todos, que para eso estan.
*/
if ($fallidas) {
    $mensaje .= '. No se pudieron guardar: ' . implode(', ', $fallidas);
    // Puede no haber motivo: una imagen rechazada por repetida no lanza
    // excepcion. Sin esta comprobacion el mensaje acabaria en un punto
    // suelto.
    if ($motivos) {
        $mensaje .= '. ' . implode(' ', array_keys($motivos));
    }
}

Respuesta::ok(
    ['id_hallazgo' => $idHallazgo, 'imagenes' => $guardadas,
     'fallidas' => $fallidas],
    $mensaje . '.');
