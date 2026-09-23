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
     * Si el tipo de documento no permite varios y ya existe uno, no se
     * duplica: seria ruido para quien captura.
     *
     * @param int|null $idPlan    plan de tratamiento al que pertenece
     * @param int|null $idConsulta consulta de la que salio el papel
     *
     * Donde queda el documento lo decide su ambito:
     *   PACIENTE -> suelto en el expediente (foto, historial)
     *   PLAN     -> dentro del bloque del plan (consentimiento, hoja)
     *   CONSULTA -> anidado bajo su consulta (receta, radiografia)
     *
     * @return int|null id del documento creado, o null si ya existia
     */
    public static function crearPendiente(
        int     $idExpediente,
        string  $claveTipo,
        ?string $descripcion = null,
        ?string $origenTabla = null,
        ?int    $origenId = null,
        ?int    $idPlan = null,
        ?int    $idConsulta = null
    ): ?int {
        $tipo = Conexion::uno(
            'SELECT id_tipo_documento, permite_varios, ambito
               FROM tipo_documento
              WHERE clave = :c AND estatus = 1',
            [':c' => $claveTipo]
        );

        if ($tipo === null) {
            error_log("[DOCUMENTOS] tipo desconocido: $claveTipo");
            return null;
        }

        // Cada ambito solo acepta el amarre que le corresponde. Asi un
        // error de quien llama no deja la foto del paciente colgando de
        // una consulta ni la receta suelta en el expediente.
        if ($tipo['ambito'] === 'PACIENTE') {
            $idPlan = null;
            $idConsulta = null;
        } elseif ($tipo['ambito'] === 'PLAN') {
            $idConsulta = null;
        } elseif ($tipo['ambito'] === 'CONSULTA') {
            // Una consulta ligada a un plan arrastra ahi sus papeles.
            if ($idConsulta !== null && $idPlan === null) {
                $idPlan = Conexion::valor(
                    'SELECT id_plan_tratamiento FROM consulta WHERE id_consulta = :c',
                    [':c' => $idConsulta]
                );
                $idPlan = $idPlan !== null ? (int) $idPlan : null;
            }
        }

        // Documento unico: existe uno solo, sin importar si ya se
        // digitalizo. Antes solo se revisaban los pendientes, y por eso
        // volver a abrir el consentimiento generaba un faltante nuevo
        // aunque el firmado ya estuviera guardado.
        //
        // "Unico" se mide dentro de su ambito: un consentimiento por
        // plan, una fotografia por paciente.
        if ((int) $tipo['permite_varios'] === 0) {
            $ya = (int) Conexion::valor(
                'SELECT COUNT(*) FROM documento
                  WHERE id_registro_paciente = :r
                    AND id_tipo_documento = :t
                    AND id_plan_tratamiento <=> :pl
                    AND id_consulta <=> :co
                    AND estatus = 1',
                [':r' => $idExpediente, ':t' => $tipo['id_tipo_documento'],
                 ':pl' => $idPlan, ':co' => $idConsulta]
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
                (id_registro_paciente, id_tipo_documento, id_plan_tratamiento,
                 id_consulta, estado, origen_tabla, origen_id, descripcion,
                 id_usuario_solicita)
             VALUES (:r, :t, :pl, :co, ' . self::PENDIENTE . ', :ot, :oi, :d, :u)',
            [':r' => $idExpediente, ':t' => $tipo['id_tipo_documento'],
             ':pl' => $idPlan, ':co' => $idConsulta,
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
     * Crea los pendientes marcados con pendiente_al_alta.
     *
     * Hoy no hay ninguno: se decidio que el expediente no nazca con
     * avisos rojos de papeles que todavia no existen. El pendiente se
     * crea cuando el papel se genera (al guardar la historia, al
     * imprimir el consentimiento, al hacer la receta). El metodo se
     * conserva porque el catalogo sigue teniendo la columna y manianaa
     * puede volver a usarse.
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
                    td.ambito, td.permite_varios,
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

    /** Tipos de documento de un ambito, en el orden en que deben salir. */
    public static function tipos(string $ambito): array
    {
        return Conexion::todos(
            'SELECT clave, nombre_documento, descripcion, icono, formatos,
                    permite_varios
               FROM tipo_documento
              WHERE ambito = :a AND estatus = 1
              ORDER BY orden',
            [':a' => $ambito]
        );
    }

    /**
     * El expediente como lo pide la pantalla: un bloque general arriba y
     * despues un bloque por cada plan de tratamiento, del mas reciente al
     * mas antiguo.
     *
     * Se arma aqui y no en JavaScript porque son cuatro consultas que se
     * cruzan entre si, y repartirlas en cuatro llamadas AJAX solo haria
     * la pantalla mas lenta y mas facil de descuadrar.
     */
    public static function arbol(int $idExpediente): array
    {
        $documentos = self::listar($idExpediente);

        $consultas = Conexion::todos(
            'SELECT c.id_consulta, c.folio, c.id_plan_tratamiento, c.fecha_hora,
                    c.motivo, c.diagnostico, c.cerrada,
                    u.nombre_completo AS dentista,
                    (SELECT COUNT(*) FROM nota_evolucion ne
                      WHERE ne.id_consulta = c.id_consulta AND ne.estatus = 1) AS notas
               FROM consulta c
               JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
              WHERE c.id_registro_paciente = :r AND c.estatus = 1
              ORDER BY c.fecha_hora DESC',
            [':r' => $idExpediente]
        );

        $planes = Conexion::todos(
            'SELECT pt.id_plan_tratamiento, pt.folio, pt.descripcion, pt.fecha,
                    pt.total, pt.diagnostico, pt.id_estado_plan, ep.nombre_estado,
                    u.nombre_completo AS dentista
               FROM plan_tratamiento pt
               JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
               JOIN usuario u ON u.id_usuario = pt.id_usuario_dentista
              WHERE pt.id_registro_paciente = :r
              ORDER BY pt.fecha DESC, pt.id_plan_tratamiento DESC',
            [':r' => $idExpediente]
        );

        // ---- Reparto de documentos en tres niveles ----
        // La consulta manda sobre el plan: un documento con id_consulta
        // se dibuja anidado bajo esa consulta, aunque tambien traiga
        // plan. Asi se ve de que cita salio cada radiografia.
        $porConsulta = [];
        $porPlan     = [];
        $generales   = [];

        $consultaConocida = [];
        foreach ($consultas as $c) {
            $consultaConocida[(int) $c['id_consulta']] = true;
        }

        foreach ($documentos as $d) {
            $cons = $d['id_consulta'];
            $plan = $d['id_plan_tratamiento'];

            if ($cons !== null && isset($consultaConocida[(int) $cons])) {
                $porConsulta[(int) $cons][] = $d;
            } elseif ($plan !== null) {
                $porPlan[(int) $plan][] = $d;
            } else {
                $generales[] = $d;
            }
        }

        /** Cuenta los pendientes de una lista de documentos. */
        $pendientesDe = static fn(array $lista): int => count(array_filter(
            $lista, static fn($d) => (int) $d['estado'] === self::PENDIENTE));

        // Cada consulta se lleva sus documentos colgados.
        $consPorPlan = [];
        $consLibres  = [];
        foreach ($consultas as $c) {
            $id = (int) $c['id_consulta'];
            $c['documentos'] = $porConsulta[$id] ?? [];
            $c['pendientes'] = $pendientesDe($c['documentos']);

            if ($c['id_plan_tratamiento'] === null) {
                $consLibres[] = $c;
            } else {
                $consPorPlan[(int) $c['id_plan_tratamiento']][] = $c;
            }
        }

        $bloques = [];
        foreach ($planes as $p) {
            $id   = (int) $p['id_plan_tratamiento'];
            $docs = $porPlan[$id] ?? [];
            $cons = $consPorPlan[$id] ?? [];

            // Las consultas ya vienen de la mas reciente a la mas
            // antigua; los pendientes del bloque suman los suyos.
            $pend = $pendientesDe($docs);
            foreach ($cons as $c) { $pend += $c['pendientes']; }

            $bloques[] = [
                'plan'       => $p,
                'documentos' => $docs,
                'consultas'  => $cons,
                'pendientes' => $pend,
            ];
        }

        // Un documento puede haber quedado colgado de un plan borrado.
        // Que no se pierda de vista: se manda al bloque general.
        foreach ($porPlan as $id => $docs) {
            $conocido = false;
            foreach ($planes as $p) {
                if ((int) $p['id_plan_tratamiento'] === $id) { $conocido = true; break; }
            }
            if (!$conocido) {
                $generales = array_merge($generales, $docs);
            }
        }

        $pendGenerales = $pendientesDe($generales);
        foreach ($consLibres as $c) { $pendGenerales += $c['pendientes']; }

        return [
            'generales'  => $generales,
            'consultas'  => $consLibres,
            'planes'     => $bloques,
            'tipos_paciente' => self::tipos('PACIENTE'),
            'tipos_plan'     => self::tipos('PLAN'),
            'tipos_consulta' => self::tipos('CONSULTA'),
            'pendientes_generales' => $pendGenerales,
        ];
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
