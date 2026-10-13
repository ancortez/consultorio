<?php
/**
 * Quita la fotografia del paciente.
 *
 * Dos cosas distintas que hay que dejar en el mismo estado:
 *
 *   - el documento FOTO_PACIENTE del expediente, que vuelve a quedar
 *     pendiente (solicitado pero sin escanear), y
 *   - persona.foto, que es el recorte cuadrado que se ve en la tarjeta.
 *
 * Documentos::quitarArchivo ya hace las dos cuando el documento existe.
 * Pero puede no existir: hay fotos que entraron antes de que la foto
 * fuera un documento del expediente, y para esas lo unico que hay que
 * limpiar es el recorte. Sin ese segundo camino, el boton de eliminar
 * no haria nada justo en los expedientes mas viejos.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) { Respuesta::error('Falta el expediente.'); }

$idDocumento = (int) Conexion::valor(
    'SELECT d.id_documento
       FROM documento d
       JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
      WHERE d.id_registro_paciente = :r
        AND td.clave = :clave
        AND d.estatus = 1
        AND d.estado = 1
      ORDER BY d.id_documento DESC
      LIMIT 1',
    [':r' => $idExpediente, ':clave' => 'FOTO_PACIENTE']
);

if ($idDocumento > 0) {
    Documentos::quitarArchivo($idDocumento);
} else {
    // Sin documento: solo el recorte de la tarjeta.
    $persona = Conexion::uno(
        'SELECT p.id_persona, p.foto
           FROM registro_paciente rp
           JOIN persona p ON p.id_persona = rp.id_persona
          WHERE rp.id_registro_paciente = :r',
        [':r' => $idExpediente]
    );

    if ($persona === null) { Respuesta::error('No se encontro el expediente.'); }

    if (!empty($persona['foto'])) {
        try {
            Almacen::eliminar($persona['foto']);
        } catch (Throwable $e) {
            // Que el archivo ya no este en el almacen no es razon para
            // dejar al paciente con una foto que no se puede mostrar.
            error_log('[FOTO] ' . $e->getMessage());
        }
        Conexion::consulta(
            'UPDATE persona SET foto = NULL WHERE id_persona = :p',
            [':p' => (int) $persona['id_persona']]
        );
    }
}

Bitacora::registrar('paciente', 'BAJA', 'fotografia', (string) $idExpediente);
Respuesta::ok(null, 'Fotografia eliminada.');
