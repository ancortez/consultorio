<?php
declare(strict_types=1);

/**
 * Respaldos, desde el propio sistema.
 *
 * Lo pidio Angel: crear, descargar y restaurar sin entrar al servidor
 * por ssh. El motivo real es el que importa: un respaldo que vive en el
 * mismo servidor no protege del caso mas probable —que el servidor se
 * pierda— y bajarlo tiene que ser facil, porque lo que cuesta trabajo
 * no se hace.
 *
 * Que hace esta clase y que NO
 * ----------------------------
 *   SI   listar lo que hay en la carpeta de respaldos
 *   SI   crear un respaldo de la BASE DE DATOS
 *   SI   entregarlo para descargarlo
 *   SI   restaurar la base desde uno de ellos
 *   NO   respaldar los ARCHIVOS del expediente (MinIO)
 *   NO   borrar respaldos
 *
 * Los archivos del expediente no, y no es una omision: pesan gigabytes
 * y viven en el volumen de MinIO, al que este contenedor no tiene
 * acceso —ni debe tenerlo—. Los sigue respaldando respaldar.sh los
 * domingos, en el servidor. Desde aqui se ven, se descargan y se dice
 * claramente que para hacer uno nuevo hay que correr el guion.
 *
 * Y no borra nunca. La rotacion la hace respaldar.sh, que sabe cuantos
 * conservar de cada tipo. Un boton de borrar en una pantalla web, al
 * lado de uno de restaurar, es la clase de vecindad que acaba en un
 * clic equivocado sobre lo unico que quedaba.
 *
 * Por que no usa docker
 * ---------------------
 * La version del servidor hace "docker exec consultorio_db mysqldump".
 * Desde PHP eso pediria el socket de Docker dentro del contenedor, y un
 * contenedor con ese socket puede arrancar otro con el disco del
 * servidor montado: una falla en la aplicacion web dejaria de ser
 * "alguien ve expedientes" y pasaria a ser "alguien es dueño del
 * servidor". Aqui se usa el cliente de MySQL contra la red interna, que
 * es el permiso que la aplicacion ya tiene de todas formas.
 */
final class Respaldos
{
    /** Donde estan, DENTRO del contenedor. Lo pone el compose. */
    public static function carpeta(): string
    {
        return rtrim((string) (getenv('CARPETA_RESPALDOS') ?: '/var/respaldos'), '/');
    }

    /*
       Los nombres que reconoce, y nada mas.

       respaldar.sh los nombra base-FECHA.tar.gz[.enc] y
       archivos-FECHA.tar.gz[.enc]. Esta expresion es la unica puerta:
       lo que llega por la direccion se compara contra ella y lo que no
       encaje no se toca. Sin eso, un nombre como "../../etc/passwd"
       convertiria la descarga de respaldos en una descarga de lo que
       sea del servidor.
    */
    private const PATRON = '/^(base|archivos)-(\d{8}-\d{4})\.(sql|tar\.gz)(\.enc)?$/';

    /** Lo que hace esta clase con la base: volcarla y restaurarla. */
    public const TIPO_BASE     = 'base';
    public const TIPO_ARCHIVOS = 'archivos';

    // -----------------------------------------------------------------
    //  Listar
    // -----------------------------------------------------------------

    /**
     * Los respaldos que hay, del mas nuevo al mas viejo.
     *
     * @return array<int, array{nombre:string,tipo:string,bytes:int,
     *                          fecha:int,cifrado:bool,restaurable:bool}>
     */
    public static function listar(): array
    {
        $carpeta = self::carpeta();
        if (!is_dir($carpeta)) {
            return [];
        }

        $lista = [];

        foreach ((array) scandir($carpeta) as $nombre) {
            if (!is_string($nombre) || !preg_match(self::PATRON, $nombre, $m)) {
                continue;
            }

            $ruta = $carpeta . '/' . $nombre;
            if (!is_file($ruta)) {
                continue;
            }

            $cifrado = str_ends_with($nombre, '.enc');

            $lista[] = [
                'nombre'  => $nombre,
                'tipo'    => $m[1],
                'bytes'   => (int) filesize($ruta),
                'fecha'   => (int) filemtime($ruta),
                'cifrado' => $cifrado,
                /*
                   Restaurable desde aqui: solo la base, y solo sin
                   cifrar.

                   Un respaldo cifrado se puede descargar igual —para
                   eso esta el cifrado, para sacarlo del servidor— pero
                   descifrarlo pide la clave, y una clave de descifrado
                   escrita en un formulario web acaba en el historial
                   del navegador, en los registros del servidor y en la
                   memoria de PHP. Para restaurar uno cifrado esta
                   restaurar.sh, en el servidor, donde la clave ya vive.
                */
                'restaurable' => $m[1] === self::TIPO_BASE && !$cifrado,
                /*
                   ¿Ya se bajo este?

                   Es la pregunta que de verdad se hace Angel cuando
                   entra: no "cuales hay" sino "cuales me falta bajar".
                   Sin la marca, la pantalla ensenia doce archivos
                   iguales y hay que acordarse, lo que significa bajar
                   el mismo tres veces y otro ninguna.

                   La marca es un archivo vacio al lado, no una columna
                   en la base: asi sigue siendo cierta despues de
                   restaurar un respaldo viejo. Guardada en la base, una
                   restauracion devolveria las marcas al estado de hace
                   dos semanas y la pantalla diria que falta bajar lo que
                   ya esta bajado.
                */
                'bajado'  => is_file($ruta . '.bajado'),
            ];
        }

        // Del mas nuevo al mas viejo: lo que se busca casi siempre es
        // el ultimo.
        usort($lista, static fn (array $a, array $b): int
            => $b['fecha'] <=> $a['fecha']);

        return $lista;
    }

    /**
     * La ruta de un respaldo, comprobada.
     *
     * Devuelve null si el nombre no encaja en el patron o el archivo no
     * esta. No lanza: quien llama tiene que poder contestar "ese
     * respaldo no existe" sin tratar una direccion manipulada como un
     * error del sistema.
     */
    public static function ruta(string $nombre): ?string
    {
        if (!preg_match(self::PATRON, $nombre)) {
            return null;
        }

        /*
           basename ADEMAS del patron.

           El patron ya no deja pasar diagonales, asi que esto es
           redundante hoy. Se queda porque es la clase de redundancia
           que vale: el dia que alguien afloje la expresion para
           aceptar otro nombre de archivo, el basename sigue impidiendo
           que la ruta se escape de la carpeta. Una comprobacion sola
           protege hasta que alguien la edita.
        */
        $ruta = self::carpeta() . '/' . basename($nombre);

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * Deja constancia de que un respaldo se bajo.
     *
     * No falla si no puede escribir: la descarga ya salio bien y
     * negarsela a Angel porque no se pudo poner una marca seria cambiar
     * lo importante por lo accesorio.
     */
    public static function marcarBajado(string $nombre): void
    {
        $ruta = self::ruta($nombre);
        if ($ruta === null) {
            return;
        }

        @touch($ruta . '.bajado');
        @chmod($ruta . '.bajado', 0600);
    }

    /**
     * Como va la cosa, en una frase.
     *
     * Es lo que se ensenia arriba de la lista: cuando fue el ultimo
     * respaldo de la base, cuantos quedan sin bajar y si falta el de los
     * archivos. Se calcula aqui y no en la pantalla porque la misma
     * cuenta la quiere el JavaScript despues de crear uno.
     */
    public static function resumen(): array
    {
        $lista = self::listar();

        $bases    = array_values(array_filter(
            $lista, static fn (array $r): bool => $r['tipo'] === self::TIPO_BASE));
        $archivos = array_values(array_filter(
            $lista, static fn (array $r): bool => $r['tipo'] === self::TIPO_ARCHIVOS));

        $sinBajar = count(array_filter(
            $lista, static fn (array $r): bool => !$r['bajado']));

        return [
            'cuantos'        => count($lista),
            'bases'          => count($bases),
            'archivos'       => count($archivos),
            'sin_bajar'      => $sinBajar,
            'ultima_base'    => $bases[0]['fecha'] ?? null,
            'ultimo_archivo' => $archivos[0]['fecha'] ?? null,
            'bytes'          => array_sum(array_column($lista, 'bytes')),
        ];
    }

    // -----------------------------------------------------------------
    //  Crear
    // -----------------------------------------------------------------

    /**
     * Vuelca la base de datos a un archivo nuevo.
     *
     * @return array{nombre:string,bytes:int}
     * @throws RuntimeException con un motivo que se le puede ensenar a
     *         quien esta en la pantalla.
     */
    public static function crearDeLaBase(): array
    {
        $carpeta = self::carpeta();

        if (!is_dir($carpeta)) {
            throw new RuntimeException(
                'No encuentro la carpeta de respaldos. Revisa que este '
                . 'montada en el contenedor.');
        }
        if (!is_writable($carpeta)) {
            throw new RuntimeException(
                'La carpeta de respaldos es de solo lectura para el sistema. '
                . 'Corre infra/produccion/permisos.sh en el servidor.');
        }

        /*
           El espacio libre se mira ANTES.

           Un volcado que se queda a medias por falta de disco deja un
           archivo que parece respaldo y no lo es, y ademas puede llenar
           el disco que MySQL necesita para trabajar: quedarse sin
           sistema por hacer un respaldo es el peor intercambio posible.

           Se pide el doble de lo que ocupa la base, que es de sobra: el
           volcado en texto suele pesar menos que las tablas.
        */
        $libre = (float) (@disk_free_space($carpeta) ?: 0);
        $pesa  = self::pesoDeLaBase();

        if ($libre > 0 && $pesa > 0 && $libre < $pesa * 2) {
            throw new RuntimeException(sprintf(
                'No hay espacio: la base ocupa %s y quedan %s libres. '
                . 'Baja respaldos viejos y borralos del servidor.',
                tamanoLegible((int) $pesa), tamanoLegible((int) $libre)));
        }

        $nombre = 'base-' . date('Ymd-Hi') . '.sql';
        $ruta   = $carpeta . '/' . $nombre;

        // Si ya hay uno de este minuto no se pisa: seria perder el
        // anterior por picar el boton dos veces.
        if (file_exists($ruta)) {
            throw new RuntimeException(
                'Ya se creo un respaldo en este minuto. Espera un momento.');
        }

        self::correrMysqldump($ruta);

        $bytes = (int) filesize($ruta);

        /*
           Un volcado de menos de 10 KB no es un volcado.

           El esquema solo pesa mas que eso. Es la misma comprobacion
           que hace respaldar.sh, y por la misma razon: mas vale fallar
           ruidosamente que guardar un archivo vacio que nadie va a
           abrir hasta el dia que lo necesite.
        */
        if ($bytes < 10240) {
            @unlink($ruta);
            throw new RuntimeException(
                'El respaldo salio vacio (' . $bytes . ' bytes). '
                . 'No se guardo. Revisa el registro del sistema.');
        }

        // Solo el dueño lo lee: trae el expediente clinico completo.
        @chmod($ruta, 0600);

        Bitacora::registrar('configuracion', 'ALTA', 'respaldo', $nombre,
            ['bytes' => $bytes]);

        return ['nombre' => $nombre, 'bytes' => $bytes];
    }

    /** Lo que ocupan los datos en la base, para saber si cabe. */
    private static function pesoDeLaBase(): float
    {
        $v = Conexion::valor(
            'SELECT COALESCE(SUM(data_length + index_length), 0)
               FROM information_schema.tables
              WHERE table_schema = DATABASE()');

        return (float) $v;
    }

    // -----------------------------------------------------------------
    //  Restaurar
    // -----------------------------------------------------------------

    /**
     * Restaura la base desde un respaldo.
     *
     * Antes de tocar nada hace un respaldo de seguridad de lo que hay.
     * Eso no es un adorno: restaurar reemplaza TODOS los expedientes, y
     * si el archivo elegido resulta ser el equivocado —o de hace tres
     * semanas— lo unico que separa un susto de un desastre es poder
     * volver al estado de hace un minuto.
     *
     * @return array{restaurado:string,seguridad:string}
     * @throws RuntimeException
     */
    public static function restaurar(string $nombre): array
    {
        $ruta = self::ruta($nombre);

        if ($ruta === null) {
            throw new RuntimeException('Ese respaldo no existe.');
        }
        if (!str_starts_with($nombre, self::TIPO_BASE . '-')) {
            throw new RuntimeException(
                'Solo se puede restaurar un respaldo de la base de datos.');
        }
        if (str_ends_with($nombre, '.enc')) {
            throw new RuntimeException(
                'Ese respaldo esta cifrado. Para restaurarlo hay que '
                . 'descifrarlo en el servidor con restaurar.sh, donde la '
                . 'clave ya vive.');
        }

        /*
           Se revisa el CONTENIDO antes de reemplazar nada.

           Un archivo puede llamarse base-20261017-0230.sql y estar
           truncado, o ser el volcado de otra base. Restaurarlo sin
           mirar deja el sistema con la mitad de las tablas, y la
           pantalla diria "restaurado" igual.
        */
        self::revisarVolcado($ruta);

        // El respaldo de seguridad primero. Si esto falla, no se
        // restaura: sin red abajo no se salta.
        $seguridad = self::crearDeLaBase();

        self::correrMysqlRestaurando($ruta);

        Bitacora::registrar('configuracion', 'RESTAURA', 'respaldo', $nombre,
            ['seguridad' => $seguridad['nombre']]);

        return [
            'restaurado' => $nombre,
            'seguridad'  => $seguridad['nombre'],
        ];
    }

    /**
     * ¿Esto se parece a un volcado de ESTA base?
     *
     * Se leen los primeros kilobytes y se busca el rastro de mysqldump
     * y alguna tabla que el sistema conozca. No es una validacion
     * completa —no se puede sin cargarlo— pero caza los dos casos
     * reales: el archivo truncado y el volcado de otra cosa.
     */
    private static function revisarVolcado(string $ruta): void
    {
        $tam = (int) filesize($ruta);

        if ($tam < 10240) {
            throw new RuntimeException(
                'Ese respaldo pesa ' . tamanoLegible($tam)
                . ': esta vacio o truncado. No se restaura.');
        }

        $inicio = (string) @file_get_contents($ruta, false, null, 0, 65536);

        if (!str_contains($inicio, 'MySQL dump')
            && !str_contains($inicio, 'CREATE TABLE')) {
            throw new RuntimeException(
                'Ese archivo no parece un volcado de MySQL. No se restaura.');
        }

        /*
           Y el final del archivo, no solo el principio.

           Un volcado cortado a la mitad empieza perfecto: tiene su
           cabecera y sus primeras tablas. Lo que le falta es el final.
           mysqldump cierra siempre con "Dump completed", asi que su
           ausencia es la senial de que el archivo no esta entero.
        */
        $fin = (string) @file_get_contents($ruta, false, null, max(0, $tam - 4096));

        if (!str_contains($fin, 'Dump completed')) {
            throw new RuntimeException(
                'Ese respaldo esta incompleto: le falta el cierre que '
                . 'escribe mysqldump al terminar. No se restaura.');
        }
    }

    // -----------------------------------------------------------------
    //  Los dos programas
    // -----------------------------------------------------------------

    /**
     * La contrasenia NO va en la linea de comandos.
     *
     * Con --password=xxx cualquiera que corra "ps" en el servidor la ve,
     * y queda en los registros del sistema. Se le pasa a mysqldump por
     * un archivo de configuracion temporal, que es la forma que el
     * propio MySQL recomienda.
     */
    private static function archivoDeClave(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'my');
        if ($tmp === false) {
            throw new RuntimeException('No pude preparar la conexion.');
        }

        file_put_contents($tmp, sprintf(
            "[client]\nhost=%s\nport=%s\nuser=%s\npassword=\"%s\"\n",
            getenv('DB_HOST') ?: 'db',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_USER') ?: '',
            str_replace('"', '\\"', (string) getenv('DB_PASS'))
        ));
        chmod($tmp, 0600);

        return $tmp;
    }

    private static function correrMysqldump(string $destino): void
    {
        $claves = self::archivoDeClave();

        try {
            /*
               --single-transaction saca una foto consistente sin
               bloquear las tablas: el consultorio puede seguir
               trabajando durante el respaldo. Sin esto, un cobro a
               medio guardar podria quedar partido en el volcado.
            */
            $r = self::correr([
                'mysqldump',
                '--defaults-extra-file=' . $claves,
                '--single-transaction', '--quick',
                '--routines', '--triggers', '--events',
                '--default-character-set=utf8mb4',
                (string) getenv('DB_NAME'),
            ], $destino);

            if ($r['codigo'] !== 0) {
                @unlink($destino);
                error_log('[RESPALDO] mysqldump: ' . $r['error']);

                throw new RuntimeException(
                    $r['codigo'] === 127
                        ? 'Falta mysqldump en el contenedor. Reconstruye la '
                          . 'imagen: docker compose build app'
                        : 'No se pudo volcar la base. Revisa el registro.');
            }
        } finally {
            @unlink($claves);
        }
    }

    private static function correrMysqlRestaurando(string $origen): void
    {
        $claves = self::archivoDeClave();

        try {
            $r = self::correr([
                'mysql',
                '--defaults-extra-file=' . $claves,
                '--default-character-set=utf8mb4',
                (string) getenv('DB_NAME'),
            ], null, $origen);

            if ($r['codigo'] !== 0) {
                error_log('[RESPALDO] restaurar: ' . $r['error']);

                /*
                   Aqui el aviso tiene que ser el peor de todos.

                   Una restauracion que falla a la mitad deja la base
                   con unas tablas nuevas y otras viejas: el sistema
                   abre y miente. Lo unico util que se puede decir es
                   que hay un respaldo de seguridad de hace un minuto y
                   cual es.
                */
                throw new RuntimeException(
                    'La restauracion FALLO a medio camino. La base puede '
                    . 'estar incompleta: NO sigas usando el sistema. '
                    . 'Restaura el respaldo de seguridad que se acaba de '
                    . 'crear, o avisa a quien lo administra.');
            }
        } finally {
            @unlink($claves);
        }
    }

    /**
     * Corre un programa, sin shell.
     *
     * La forma de arreglo de proc_open NO pasa por un shell, asi que
     * ningun valor —ni el nombre de la base, ni una ruta— puede
     * convertirse en otro comando.
     *
     * El codigo de salida se lee de proc_get_status y no de proc_close:
     * habiendo llamado a proc_get_status, PHP ya recogio al hijo y
     * proc_close devuelve -1 siempre. Eso me costo cuatro diagnosticos
     * equivocados con la conversion de HEIC.
     *
     * @return array{codigo:int,error:string}
     */
    private static function correr(
        array $comando, ?string $salidaA = null, ?string $entradaDe = null
    ): array {
        $canales = [
            0 => $entradaDe !== null ? ['file', $entradaDe, 'r'] : ['pipe', 'r'],
            1 => $salidaA !== null ? ['file', $salidaA, 'w'] : ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proceso = proc_open($comando, $canales, $tubos);

        if (!is_resource($proceso)) {
            return ['codigo' => 127, 'error' => 'no se pudo arrancar'];
        }

        if ($entradaDe === null && isset($tubos[0])) {
            fclose($tubos[0]);
        }

        $salida = isset($tubos[1]) ? (string) stream_get_contents($tubos[1]) : '';
        $error  = (string) stream_get_contents($tubos[2]);

        foreach ($tubos as $t) {
            if (is_resource($t)) { fclose($t); }
        }

        $codigo = -1;
        while (true) {
            $estado = proc_get_status($proceso);
            if (!$estado['running']) {
                $codigo = (int) $estado['exitcode'];
                break;
            }
            usleep(50000);
        }
        proc_close($proceso);

        return ['codigo' => $codigo, 'error' => trim($error . ' ' . $salida)];
    }
}
