<?php
declare(strict_types=1);

/**
 * Expediente electronico: pendientes de digitalizacion y documentos.
 *
 * La idea es que el sistema sepa solo lo que falta escanear, en vez de
 * depender de que alguien lo anote. Cada vez que ocurre algo que genera
 * papel (se registra un paciente, se imprime un consentimiento, se
 * captura la identificacion del tutor), se crea aqui un pendiente.
 */
final class Documentos
{
    public const PENDIENTE    = 0;
    public const DIGITALIZADO = 1;
    public const NO_APLICA    = 2;

    /**
     * Crea un pendiente de digitalizacion.
     *
     * Si el tipo de documento no permite varios y ya existe uno
     * pendiente, no se duplica: seria ruido para quien captura.
     *
     * @return int|null id del documento creado, o null si ya existia
     */
    public static function crearPendiente(
        int     $idExpediente,
        string  $claveTipo,
        ?string $descripcion = null,
        ?string $origenTabla = null,
        ?int    $origenId = null
    ): ?int {
        $tipo = Conexion::uno(
            'SELECT id_tipo_documento, permite_varios
               FROM tipo_documento
              WHERE clave = :c AND estatus = 1',
            [':c' => $claveTipo]
        );

        if ($tipo === null) {
            error_log("[DOCUMENTOS] tipo desconocido: $claveTipo");
            return null;
        }

        // Documento unico: existe uno solo por expediente, sin importar
        // si ya se digitalizo. Antes solo se revisaban los pendientes, y
        // por eso volver a abrir el consentimiento generaba un faltante
        // nuevo aunque el firmado ya estuviera guardado.
        if ((int) $tipo['permite_varios'] === 0) {
            $ya = (int) Conexion::valor(
                'SELECT COUNT(*) FROM documento
                  WHERE id_registro_paciente = :r
                    AND id_tipo_documento = :t
                    AND estatus = 1',
                [':r' => $idExpediente, ':t' => $tipo['id_tipo_documento']]
            );
            if ($ya > 0) {
                return null;
            }
        }

        // Con permite_varios, el mismo origen no genera dos pendientes:
        // reimprimir un consentimiento no debe apilar faltantes.
        if ($origenTabla !== null && $origenId !== null) {
            $mismoOrigen = (int) Conexion::valor(
                'SELECT COUNT(*) FROM documento
                  WHERE id_registro_paciente = :r
                    AND id_tipo_documento = :t
                    AND origen_tabla = :ot AND origen_id = :oi
                    AND estado = ' . self::PENDIENTE . '
                    AND estatus = 1',
                [':r' => $idExpediente, ':t' => $tipo['id_tipo_documento'],
                 ':ot' => $origenTabla, ':oi' => $origenId]
            );
            if ($mismoOrigen > 0) {
                return null;
            }
        }

        Conexion::consulta(
            'INSERT INTO documento
                (id_registro_paciente, id_tipo_documento, estado,
                 origen_tabla, origen_id, descripcion, id_usuario_solicita)
             VALUES (:r, :t, ' . self::PENDIENTE . ', :ot, :oi, :d, :u)',
            [':r' => $idExpediente, ':t' => $tipo['id_tipo_documento'],
             ':ot' => $origenTabla, ':oi' => $origenId,
             ':d' => $descripcion, ':u' => Auth::id()]
        );

        $id = Conexion::ultimoId();

        Bitacora::registrar('expediente', 'PENDIENTE', 'documento', (string) $id, [
            'tipo'       => $claveTipo,
            'expediente' => $idExpediente,
        ]);

        return $id;
    }

    /**
     * Crea de un golpe los pendientes que le tocan a un paciente recien
     * registrado (los tipos marcados con pendiente_al_alta).
     */
    public static function pendientesDeAlta(int $idExpediente): int
    {
        $tipos = Conexion::todos(
            'SELECT clave FROM tipo_documento
              WHERE pendiente_al_alta = 1 AND estatus = 1
              ORDER BY orden'
        );

        $creados = 0;
        foreach ($tipos as $t) {
            if (self::crearPendiente($idExpediente, $t['clave']) !== null) {
                $creados++;
            }
        }
        return $creados;
    }

    /** Cuantos documentos le faltan por digitalizar a un expediente. */
    public static function cuantosPendientes(int $idExpediente): int
    {
        return (int) Conexion::valor(
            'SELECT COUNT(*) FROM documento
              WHERE id_registro_paciente = :r
                AND estado = ' . self::PENDIENTE . ' AND estatus = 1',
            [':r' => $idExpediente]
        );
    }

    /** Documentos del expediente, pendientes primero. */
    public static function listar(int $idExpediente): array
    {
        return Conexion::todos(
            'SELECT d.*,
                    td.clave, td.nombre_documento, td.icono, td.formatos,
                    us.nombre_completo AS subio,
                    usol.nombre_completo AS solicito
               FROM documento d
               JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
               LEFT JOIN usuario us   ON us.id_usuario = d.id_usuario_sube
               LEFT JOIN usuario usol ON usol.id_usuario = d.id_usuario_solicita
              WHERE d.id_registro_paciente = :r AND d.estatus = 1
              ORDER BY d.estado, td.orden, d.fecha_solicitud DESC',
            [':r' => $idExpediente]
        );
    }

    /**
     * Guarda el archivo de un documento y lo marca como digitalizado.
     *
     * @throws RuntimeException si MinIO falla
     */
    public static function digitalizar(int $idDocumento, array $archivo): array
    {
        $doc = Conexion::uno(
            'SELECT d.*, td.clave, td.formatos
               FROM documento d
               JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
              WHERE d.id_documento = :id AND d.estatus = 1',
            [':id' => $idDocumento]
        );

        if ($doc === null) {
            throw new RuntimeException('El documento no existe.');
        }

        $error = Almacen::validar($archivo, $doc['formatos']);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        $contenido = file_get_contents($archivo['tmp_name']);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }

        $ext  = Almacen::extension($archivo['name']);
        $ruta = Almacen::rutaDocumento(
            (int) $doc['id_registro_paciente'], $doc['clave'], $ext);
        $mime = Almacen::TIPOS[$ext];

        Almacen::subir($ruta, $contenido, $mime);

        // La fotografia del paciente ademas se recorta a un cuadrado
        // centrado en la cara, que es lo que se muestra en el circulo
        // de la tarjeta. El original completo se conserva aparte.
        if ($doc['clave'] === 'FOTO_PACIENTE') {
            self::generarAvatar(
                (int) $doc['id_registro_paciente'], $contenido);
        }

        // Si ya habia un archivo (se esta reemplazando), se borra el
        // anterior para no dejar basura ocupando espacio.
        $anterior = $doc['ruta_archivo'];

        Conexion::consulta(
            'UPDATE documento
                SET estado = ' . self::DIGITALIZADO . ',
                    ruta_archivo = :ruta,
                    nombre_original = :nom,
                    tipo_mime = :mime,
                    tamano_bytes = :tam,
                    hash_sha256 = :hash,
                    id_usuario_sube = :u,
                    fecha_digitalizacion = NOW()
              WHERE id_documento = :id',
            [':ruta' => $ruta,
             ':nom'  => mb_substr($archivo['name'], 0, 200),
             ':mime' => $mime,
             ':tam'  => $archivo['size'],
             ':hash' => hash('sha256', $contenido),
             ':u'    => Auth::id(),
             ':id'   => $idDocumento]
        );

        if (!empty($anterior) && $anterior !== $ruta) {
            try {
                Almacen::eliminar($anterior);
            } catch (Throwable $e) {
                // Que no se borre el viejo no invalida la subida nueva.
                error_log('[DOCUMENTOS] no se pudo borrar ' . $anterior);
            }
        }

        Bitacora::registrar('expediente', 'DIGITALIZA', 'documento',
            (string) $idDocumento, ['ruta' => $ruta, 'tamano' => $archivo['size']]);

        return ['id_documento' => $idDocumento, 'ruta' => $ruta];
    }

    /**
     * Genera el recorte cuadrado de la foto y lo deja listo para el
     * circulo de la tarjeta del paciente.
     *
     * Si algo falla no se interrumpe la subida: la foto original ya
     * quedo guardada y el avatar es un extra.
     */
    private static function generarAvatar(int $idExpediente, string $contenido): void
    {
        try {
            $recorte = Imagen::recorteRetrato($contenido);

            $ruta = sprintf('expedientes/%06d/avatar/%s.jpg',
                $idExpediente, date('Ymd-His'));

            Almacen::subir($ruta, $recorte['contenido'], $recorte['mime']);

            // El avatar anterior se borra para no acumular versiones.
            $persona = Conexion::uno(
                'SELECT p.id_persona, p.foto
                   FROM registro_paciente rp
                   JOIN persona p ON p.id_persona = rp.id_persona
                  WHERE rp.id_registro_paciente = :r',
                [':r' => $idExpediente]
            );

            if ($persona === null) {
                return;
            }

            Conexion::consulta(
                'UPDATE persona SET foto = :f WHERE id_persona = :p',
                [':f' => $ruta, ':p' => $persona['id_persona']]
            );

            if (!empty($persona['foto']) && $persona['foto'] !== $ruta) {
                try {
                    Almacen::eliminar($persona['foto']);
                } catch (Throwable $e) {
                    error_log('[DOCUMENTOS] avatar viejo: ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            error_log('[DOCUMENTOS] no se pudo recortar la foto: ' . $e->getMessage());
        }
    }

    /**
     * Quita el archivo y devuelve el documento a pendiente.
     * No se borra el renglon: el pendiente debe seguir a la vista.
     */
    public static function quitarArchivo(int $idDocumento): void
    {
        $doc = Conexion::uno(
            'SELECT d.ruta_archivo, d.id_registro_paciente, td.clave
               FROM documento d
               JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
              WHERE d.id_documento = :id',
            [':id' => $idDocumento]
        );

        if ($doc === null) {
            throw new RuntimeException('El documento no existe.');
        }

        // Quitar la foto tambien deja al paciente sin avatar: la tarjeta
        // vuelve a mostrar sus iniciales.
        if ($doc['clave'] === 'FOTO_PACIENTE') {
            $persona = Conexion::uno(
                'SELECT p.id_persona, p.foto
                   FROM registro_paciente rp
                   JOIN persona p ON p.id_persona = rp.id_persona
                  WHERE rp.id_registro_paciente = :r',
                [':r' => $doc['id_registro_paciente']]
            );
            if ($persona !== null && !empty($persona['foto'])) {
                try {
                    Almacen::eliminar($persona['foto']);
                } catch (Throwable $e) { /* no impide continuar */ }
                Conexion::consulta(
                    'UPDATE persona SET foto = NULL WHERE id_persona = :p',
                    [':p' => $persona['id_persona']]
                );
            }
        }

        if (!empty($doc['ruta_archivo'])) {
            try {
                Almacen::eliminar($doc['ruta_archivo']);
            } catch (Throwable $e) {
                error_log('[DOCUMENTOS] ' . $e->getMessage());
            }
        }

        Conexion::consulta(
            'UPDATE documento
                SET estado = ' . self::PENDIENTE . ',
                    ruta_archivo = NULL, nombre_original = NULL,
                    tipo_mime = NULL, tamano_bytes = NULL, hash_sha256 = NULL,
                    id_usuario_sube = NULL, fecha_digitalizacion = NULL
              WHERE id_documento = :id',
            [':id' => $idDocumento]
        );

        Bitacora::registrar('expediente', 'BAJA', 'documento', (string) $idDocumento);
    }
}
