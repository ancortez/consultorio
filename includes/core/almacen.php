<?php
declare(strict_types=1);

/**
 * Almacenamiento de archivos en MinIO.
 *
 * MinIO habla el protocolo S3 de Amazon. Se implementa aqui la firma
 * "AWS Signature Version 4" a mano, con curl, en vez de instalar el SDK
 * de AWS: el SDK arrastra unas cuarenta dependencias por Composer para
 * lo que aqui son tres operaciones (subir, bajar, borrar).
 *
 * Los archivos NUNCA se sirven directamente desde MinIO al navegador.
 * Pasan por PHP, que primero revisa la sesion. Un expediente clinico no
 * puede quedar accesible con solo conocer la URL.
 */
final class Almacen
{
    private const REGION  = 'us-east-1';
    private const SERVICIO = 's3';

    /** Tamano maximo por archivo. */
    public const MAX_BYTES = 20 * 1024 * 1024;   // 20 MB

    /** Tipos que se aceptan, por extension. */
    public const TIPOS = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private static function endpoint(): string
    {
        return rtrim(getenv('MINIO_ENDPOINT') ?: 'http://minio:9000', '/');
    }

    private static function bucket(): string
    {
        return getenv('MINIO_BUCKET') ?: 'consultorio-dental';
    }

    private static function llave(): string
    {
        return getenv('MINIO_LLAVE') ?: 'minioadmin';
    }

    private static function secreto(): string
    {
        return getenv('MINIO_SECRETO') ?: 'minioadmin123';
    }

    // =================================================================
    // Operaciones
    // =================================================================

    /**
     * Sube el contenido de un archivo.
     *
     * @param string $ruta      ruta dentro del bucket, sin diagonal inicial
     * @param string $contenido bytes del archivo
     * @param string $mime      tipo de contenido
     * @throws RuntimeException si MinIO responde con error
     */
    public static function subir(string $ruta, string $contenido, string $mime): void
    {
        $r = self::peticion('PUT', $ruta, $contenido, [
            'Content-Type' => $mime,
        ]);

        if ($r['codigo'] < 200 || $r['codigo'] >= 300) {
            throw new RuntimeException(
                'MinIO rechazo la subida (codigo ' . $r['codigo'] . '): '
                . self::mensajeDeError($r['cuerpo'])
            );
        }
    }

    /** Devuelve el contenido de un archivo, o null si no existe. */
    public static function obtener(string $ruta): ?string
    {
        $r = self::peticion('GET', $ruta);

        if ($r['codigo'] === 404) {
            return null;
        }
        if ($r['codigo'] < 200 || $r['codigo'] >= 300) {
            throw new RuntimeException(
                'MinIO no entrego el archivo (codigo ' . $r['codigo'] . ').'
            );
        }
        return $r['cuerpo'];
    }

    /** true si el archivo existe. */
    public static function existe(string $ruta): bool
    {
        return self::peticion('HEAD', $ruta)['codigo'] === 200;
    }

    /** Borra un archivo. MinIO no se queja si ya no estaba. */
    public static function eliminar(string $ruta): void
    {
        $r = self::peticion('DELETE', $ruta);
        if ($r['codigo'] >= 300 && $r['codigo'] !== 404) {
            throw new RuntimeException(
                'MinIO no pudo borrar el archivo (codigo ' . $r['codigo'] . ').'
            );
        }
    }

    /**
     * Comprueba que MinIO este arriba y el bucket exista.
     * Se usa en la pantalla del expediente para avisar con claridad en
     * lugar de fallar hasta que alguien intenta subir algo.
     *
     * @return array{ok:bool, mensaje:string}
     */
    public static function revisar(): array
    {
        try {
            $r = self::peticion('HEAD', '', null, [], true);

            if ($r['codigo'] === 200) {
                return ['ok' => true, 'mensaje' => 'Almacenamiento disponible.'];
            }
            if ($r['codigo'] === 404) {
                return ['ok' => false, 'mensaje' =>
                    'El bucket "' . self::bucket() . '" no existe en MinIO.'];
            }
            if ($r['codigo'] === 403) {
                return ['ok' => false, 'mensaje' =>
                    'MinIO rechazo las credenciales. Revisa MINIO_LLAVE y MINIO_SECRETO.'];
            }
            if ($r['codigo'] === 0) {
                return ['ok' => false, 'mensaje' =>
                    'No se pudo contactar a MinIO. Levantalo con infra/minio/iniciar-minio.command '
                    . 'y comprueba que exista la red: docker network create sistemas'];
            }
            return ['ok' => false, 'mensaje' => 'MinIO respondio codigo ' . $r['codigo'] . '.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'Error al contactar MinIO: ' . $e->getMessage()];
        }
    }

    // =================================================================
    // Ayudas de nombres
    // =================================================================

    /**
     * Arma la ruta donde vive un documento.
     * Se organiza por expediente para que en la consola de MinIO se
     * pueda ver de un vistazo todo lo de un paciente.
     */
    public static function rutaDocumento(
        int $idExpediente,
        string $claveTipo,
        string $extension
    ): string {
        return sprintf(
            'expedientes/%06d/%s/%s.%s',
            $idExpediente,
            strtolower($claveTipo),
            date('Ymd-His') . '-' . bin2hex(random_bytes(4)),
            strtolower($extension)
        );
    }

    /** Extension en minusculas, sin punto. */
    public static function extension(string $nombre): string
    {
        return strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    }

    /**
     * Valida un archivo recien subido.
     * Devuelve el mensaje de error, o null si todo esta bien.
     */
    public static function validar(array $archivo, string $formatosPermitidos): ?string
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return match ($archivo['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    'El archivo excede el tamano permitido por el servidor.',
                UPLOAD_ERR_PARTIAL   => 'El archivo se subio incompleto. Intenta de nuevo.',
                UPLOAD_ERR_NO_FILE   => 'No se selecciono ningun archivo.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                    'El servidor no pudo guardar el archivo temporal.',
                default => 'No se pudo recibir el archivo.',
            };
        }

        if ($archivo['size'] > self::MAX_BYTES) {
            return 'El archivo pesa mas de ' . (self::MAX_BYTES / 1024 / 1024) . ' MB.';
        }
        if ($archivo['size'] === 0) {
            return 'El archivo esta vacio.';
        }

        $ext = self::extension($archivo['name']);
        $permitidas = array_map('trim', explode(',', strtolower($formatosPermitidos)));

        if (!in_array($ext, $permitidas, true)) {
            return 'Formato no permitido. Se aceptan: ' . strtoupper($formatosPermitidos) . '.';
        }
        if (!isset(self::TIPOS[$ext])) {
            return 'Ese tipo de archivo no esta soportado.';
        }

        // No basta con la extension: se comprueba el contenido real.
        // Renombrar un .exe a .pdf es lo primero que intenta cualquiera.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivo['tmp_name']);

        $esperados = array_values(array_unique(
            array_map(static fn ($e) => self::TIPOS[$e], $permitidas)
        ));
        // Word moderno se reporta como zip, que es lo que es por dentro.
        $esperados[] = 'application/zip';

        if (!in_array($mimeReal, $esperados, true)) {
            return 'El contenido del archivo no corresponde a su extension.';
        }

        return null;
    }

    // =================================================================
    // Firma AWS Signature V4
    // =================================================================

    /**
     * Ejecuta una peticion firmada contra MinIO.
     *
     * @return array{codigo:int, cuerpo:string}
     */
    private static function peticion(
        string  $metodo,
        string  $ruta,
        ?string $cuerpo = null,
        array   $encabezadosExtra = [],
        bool    $soloBucket = false
    ): array {
        $ruta   = ltrim($ruta, '/');
        $host   = parse_url(self::endpoint(), PHP_URL_HOST) ?: 'minio';
        $puerto = parse_url(self::endpoint(), PHP_URL_PORT);
        $hostEncabezado = $puerto ? "$host:$puerto" : $host;

        // Estilo "path": http://minio:9000/bucket/ruta/al/archivo
        $rutaCanonica = '/' . self::bucket() . ($soloBucket ? '/' : '/' . $ruta);
        $rutaCanonica = self::codificarRuta($rutaCanonica);
        $url = self::endpoint() . $rutaCanonica;

        $ahora     = gmdate('Ymd\THis\Z');
        $fecha     = gmdate('Ymd');
        $hashCuerpo = hash('sha256', $cuerpo ?? '');

        $encabezados = array_merge([
            'host'                 => $hostEncabezado,
            'x-amz-content-sha256' => $hashCuerpo,
            'x-amz-date'           => $ahora,
        ], array_change_key_case($encabezadosExtra, CASE_LOWER));

        ksort($encabezados);

        $canonicos = '';
        $firmados  = [];
        foreach ($encabezados as $clave => $valor) {
            $canonicos .= $clave . ':' . trim((string) $valor) . "\n";
            $firmados[] = $clave;
        }
        $listaFirmados = implode(';', $firmados);

        // 1. Peticion canonica
        $peticionCanonica = implode("\n", [
            $metodo,
            $rutaCanonica,
            '',                 // sin parametros de consulta
            $canonicos,
            $listaFirmados,
            $hashCuerpo,
        ]);

        // 2. Cadena por firmar
        $alcance = "$fecha/" . self::REGION . '/' . self::SERVICIO . '/aws4_request';
        $porFirmar = implode("\n", [
            'AWS4-HMAC-SHA256',
            $ahora,
            $alcance,
            hash('sha256', $peticionCanonica),
        ]);

        // 3. Llave derivada: se encadenan cuatro HMAC, cada uno con el
        //    resultado del anterior. Asi la llave secreta nunca viaja.
        $k = hash_hmac('sha256', $fecha, 'AWS4' . self::secreto(), true);
        $k = hash_hmac('sha256', self::REGION, $k, true);
        $k = hash_hmac('sha256', self::SERVICIO, $k, true);
        $k = hash_hmac('sha256', 'aws4_request', $k, true);

        $firma = hash_hmac('sha256', $porFirmar, $k);

        $autorizacion = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            self::llave(), $alcance, $listaFirmados, $firma
        );

        // 4. Enviar
        $cabeceras = ['Authorization: ' . $autorizacion];
        foreach ($encabezados as $clave => $valor) {
            if ($clave === 'host') {
                continue;   // curl la pone sola
            }
            $cabeceras[] = $clave . ': ' . $valor;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_NOBODY         => $metodo === 'HEAD',
        ]);
        if ($cuerpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
        }

        $respuesta = curl_exec($ch);
        $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false && $codigo === 0) {
            error_log('[MINIO] ' . $errorCurl);
        }

        return [
            'codigo' => $codigo,
            'cuerpo' => $respuesta === false ? '' : $respuesta,
        ];
    }

    /**
     * Codifica la ruta como espera S3: cada segmento por separado, sin
     * tocar las diagonales, y sin convertir el espacio en "+".
     */
    private static function codificarRuta(string $ruta): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $ruta)));
    }

    /** Saca el mensaje del XML de error que devuelve MinIO. */
    private static function mensajeDeError(string $xml): string
    {
        if ($xml === '') {
            return 'sin detalle';
        }
        if (preg_match('#<Message>(.*?)</Message>#s', $xml, $m)) {
            return $m[1];
        }
        return substr(strip_tags($xml), 0, 200);
    }
}
