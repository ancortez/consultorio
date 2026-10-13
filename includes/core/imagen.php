<?php
declare(strict_types=1);

/**
 * Tratamiento de las imagenes que entran al expediente.
 *
 * Hace dos cosas distintas:
 *
 *   1. optimizar()      reduce lo que se sube. Una camara de celular
 *                       entrega 4000x3000 y 5 MB; un sensor de rayos X
 *                       puede soltar un BMP de 30 MB sin comprimir.
 *                       Guardar eso tal cual llena el disco, hace lentos
 *                       los respaldos y no mejora nada: ninguna pantalla
 *                       muestra esa resolucion.
 *
 *   2. recorteRetrato() genera el circulo de la tarjeta del paciente.
 *
 * No hay reconocimiento facial: eso pediria una libreria pesada para un
 * caso que se resuelve con geometria. En un retrato, la cara casi siempre
 * queda en el tercio superior central de la imagen, asi que se recorta un
 * cuadrado centrado horizontalmente y desplazado hacia arriba.
 *
 * El encuadre resultante se puede corregir a mano desde la pantalla si
 * la foto viene rara, pero para el 90% de los casos sale bien sola.
 */
final class Imagen
{
    /** Lado del recorte final, en pixeles. */
    public const LADO = 400;

    /**
     * Cuanto se reduce cada tipo de imagen.
     *
     * Una radiografia y una foto clinica no valen lo mismo. La foto se
     * mira; la radiografia se AMPLIA para buscar una linea de fractura
     * o una caries interproximal. Por eso la radiografia conserva mas
     * resolucion y menos compresion, aunque pese el triple.
     *
     * Las medidas son del lado largo. Una imagen que ya viene mas chica
     * NO se agranda: estirarla solo inventa pixeles y la empeora.
     */
    private const PERFILES = [
        'RADIOGRAFIA' => ['lado' => 2400, 'calidad' => 90],
        '_'           => ['lado' => 1600, 'calidad' => 82],
    ];

    /** Extensiones que son imagen y por lo tanto se pueden optimizar. */
    private const IMAGENES = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'heic', 'heif'];

    /**
     * Las de iPhone, que GD no sabe leer.
     *
     * Desde iOS 11 la camara guarda en HEIC. Al subir desde el navegador
     * del telefono, iOS suele convertir a JPEG por su cuenta, asi que
     * esto no se nota; pero una foto que llega por AirDrop, exportada
     * desde Fotos en la Mac o arrastrada desde una carpeta, llega en
     * HEIC tal cual. Y entonces no se podia subir.
     */
    private const DE_IPHONE = ['heic', 'heif'];

    /**
     * El accept de un <input type="file"> que pida imagenes.
     *
     * Existe porque la lista estaba escrita a mano en el modal del
     * hallazgo —".jpg,.jpeg,.png,.bmp,.webp"— y se quedo atras cuando
     * aqui se agrego HEIC. El resultado no fue un error: el selector de
     * archivos simplemente ATENUABA las fotos del iPhone, asi que no se
     * podian elegir y parecia que el sistema no las aceptaba. El
     * servidor si las aceptaba; nadie podia llegar a el.
     *
     * Dos listas del mismo dato en dos archivos distintos se separan
     * siempre. Esta sale de IMAGENES, que es la que manda.
     *
     * Van las extensiones Y los mime: macOS filtra por tipo y no
     * siempre reconoce .heic por el nombre, asi que con solo las
     * extensiones las fotos seguirian atenuadas en la Mac de la
     * doctora, que es de donde vienen.
     */
    public static function aceptaEnPicker(): string
    {
        $partes = [];
        foreach (self::IMAGENES as $ext) {
            $partes[] = '.' . $ext;
        }
        foreach (self::IMAGENES as $ext) {
            $mime = Almacen::TIPOS[$ext] ?? null;
            if ($mime !== null && !in_array($mime, $partes, true)) {
                $partes[] = $mime;
            }
        }
        return implode(',', $partes);
    }

    /**
     * Deja una imagen lista para guardar: girada, reducida y comprimida.
     *
     * Devuelve tambien la extension y el mime finales, porque pueden
     * cambiar: un BMP entra como .bmp y sale como .jpg.
     *
     * Si el archivo no es una imagen (un PDF, un Word) o no se puede
     * leer, se devuelve intacto. Nunca se pierde el archivo por no
     * poder optimizarlo: vale mas un expediente pesado que uno con
     * huecos.
     *
     * @return array{contenido:string, ext:string, mime:string, optimizada:bool}
     */
    public static function optimizar(
        string $contenido,
        string $ext,
        string $clave = '_'
    ): array {
        $ext    = strtolower($ext);
        $tal_cual = [
            'contenido'  => $contenido,
            'ext'        => $ext,
            'mime'       => Almacen::TIPOS[$ext] ?? 'application/octet-stream',
            'optimizada' => false,
        ];

        if (!in_array($ext, self::IMAGENES, true)
            || !function_exists('imagecreatefromstring')) {
            return $tal_cual;
        }

        /*
           Las de iPhone se pasan antes a JPEG.

           GD no abre HEIC, asi que sin este paso imagecreatefromstring
           devuelve false. Y aqui la regla general —"vale mas un
           expediente pesado que uno con huecos"— se invierte: si la
           conversion falla NO se guarda el archivo, se rechaza.

           Por que, con nombre y fecha. Octubre de 2026: se acepto el
           HEIC, la conversion fallaba en silencio, el archivo se
           guardaba tal cual, y el expediente quedo con fotos de
           hallazgos que no se ven en ningun lado. El album salia
           vacio, no habia miniaturas, y al descargar una venia un
           .heic que el navegador no abre. Nadie se habria enterado
           hasta necesitar esa radiografia.

           Un archivo guardado que nadie puede abrir es PEOR que un
           hueco: el hueco se ve, y mientras la foto siga en el
           telefono se puede volver a subir. Un .heic archivado parece
           un expediente completo y no lo es.

           El motivo viaja en la excepcion. El que lo lee es quien
           sube: la unica forma de saber si falta heif-convert o si
           falta su complemento para decodificar es lo que diga el
           propio programa.
        */
        if (in_array($ext, self::DE_IPHONE, true)) {
            $porQue = null;
            $convertido = self::deHeicAJpeg($contenido, $porQue);

            if ($convertido === null) {
                error_log('[IMAGEN] HEIC rechazado: ' . ($porQue ?? 'sin detalle'));
                /*
                   Primero lo que puede hacer quien esta leyendo.

                   La version anterior empezaba explicando el problema y
                   acababa con "avisa para revisar el servidor". Eso le
                   sirve a quien mantiene el sistema, no a la doctora con
                   un paciente enfrente: ella no puede revisar ningun
                   servidor, y lo unico que saca del mensaje es que no
                   puede guardar su foto.

                   Lo primero es el ajuste del telefono, porque arregla
                   el problema de raiz y para siempre: con "Mas
                   compatible", el iPhone toma JPEG y esto deja de pasar
                   en todos lados, no solo aqui.
                */
                throw new InvalidArgumentException(
                    'Esta foto viene en formato de iPhone (.' . $ext . ') y '
                    . 'este equipo no la puede convertir, asi que no se '
                    . 'guardo: archivada tal cual no se podria ver despues. '
                    . 'Dos salidas: exportala como JPEG antes de subirla, o '
                    . 'mejor aun, en el iPhone entra a Ajustes > Camara > '
                    . 'Formatos y elige "Mas compatible" para que tome las '
                    . 'fotos en JPEG de aqui en adelante.'
                    . ($porQue !== null && APP_ENV !== 'produccion'
                        ? ' (Para quien da soporte: ' . $porQue . ')' : ''));
            }

            $contenido = $convertido;
            $ext = 'jpg';
        }

        $original = @imagecreatefromstring($contenido);
        if ($original === false) {
            return $tal_cual;
        }

        try {
            $perfil = self::PERFILES[$clave] ?? self::PERFILES['_'];

            $imagen = self::enderezar($original, $contenido);
            $ancho  = imagesx($imagen);
            $alto   = imagesy($imagen);
            $largo  = max($ancho, $alto);

            // Una imagen ya chica no se agranda.
            $escala = $largo > $perfil['lado'] ? $perfil['lado'] / $largo : 1.0;
            $nuevoAncho = max(1, (int) round($ancho * $escala));
            $nuevoAlto  = max(1, (int) round($alto  * $escala));

            $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

            // Fondo blanco: un PNG transparente se veria negro en JPEG.
            $blanco = imagecolorallocate($destino, 255, 255, 255);
            imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, $blanco);

            imagecopyresampled($destino, $imagen,
                0, 0, 0, 0,
                $nuevoAncho, $nuevoAlto, $ancho, $alto);

            ob_start();
            imagejpeg($destino, null, $perfil['calidad']);
            $salida = (string) ob_get_clean();

            imagedestroy($destino);
            if ($imagen !== $original) {
                imagedestroy($imagen);
            }

            // Si el resultado pesa MAS que el original, algo no valio la
            // pena: pasa con capturas chicas en PNG. Se deja el original,
            // salvo que fuera BMP, que hay que convertir de todos modos
            // porque varios navegadores no lo muestran.
            if (strlen($salida) >= strlen($contenido) && $ext !== 'bmp') {
                return $tal_cual;
            }

            return [
                'contenido'  => $salida,
                'ext'        => 'jpg',
                'mime'       => 'image/jpeg',
                'optimizada' => true,
            ];
        } catch (Throwable $e) {
            error_log('[IMAGEN] no se pudo optimizar: ' . $e->getMessage());
            return $tal_cual;
        } finally {
            if (is_object($original)) {
                @imagedestroy($original);
            }
        }
    }

    /**
     * Gira la imagen segun lo que diga su EXIF.
     *
     * Los celulares no rotan el archivo al tomar la foto: lo guardan
     * como salio del sensor y anotan "esto va girado 90 grados" en los
     * metadatos. El visor del telefono obedece esa nota; GD no. Al
     * reducir la imagen la nota se pierde, y una foto intraoral vertical
     * terminaria guardada de lado para siempre.
     *
     * Si la extension exif no esta instalada, se devuelve tal cual: una
     * foto derecha sin girar es mejor que ninguna foto.
     *
     * @param \GdImage $imagen
     * @return \GdImage
     */
    /**
     * Pasa una foto de iPhone a JPEG, o devuelve null si no se puede.
     *
     * Lo hace heif-convert, de libheif. No hay forma de hacerlo dentro
     * de PHP en esta imagen: GD no conoce HEIC y la extension imagick
     * con el delegado de heif pesa mucho mas que un ejecutable que hace
     * una sola cosa.
     *
     * Tres cuidados, que son los tres accidentes de llamar a un
     * programa de fuera:
     *
     *   - los archivos temporales se borran SIEMPRE, incluso si algo
     *     revienta a la mitad: una carpeta temporal que crece sin que
     *     nadie la mire acaba llenando el disco del servidor
     *   - hay limite de tiempo: un archivo corrupto puede dejar al
     *     conversor dando vueltas, y con el a Apache esperando
     *   - la ruta la ponemos nosotros, nunca el nombre que trae el
     *     archivo subido: ese nombre lo elige quien sube
     */
    /**
     * Una foto HEIC pasada a JPEG con heif-convert.
     *
     * @param  string|null $porQue  se llena con el motivo si falla
     * @return string|null          el JPEG, o null
     *
     * El $porQue no es un adorno. La version anterior abria los tubos
     * de salida del programa, los ponia en no bloqueante... y los
     * cerraba sin leerlos nunca. Asi que cuando heif-convert fallaba,
     * lo UNICO que podia explicar por que —su propio mensaje de error—
     * se tiraba a la basura. Lo que quedaba era "no se pudo" y a
     * adivinar: ¿falta el programa? ¿falta el complemento que
     * decodifica? ¿la foto esta corrupta? Cada respuesta pide un
     * arreglo distinto.
     *
     * Y habia un segundo problema en lo mismo: un tubo que nadie lee se
     * llena, y el programa que escribe en el se queda esperando. Con
     * suficiente texto de error, heif-convert se habria colgado hasta
     * agotar los treinta segundos, y el motivo que llegaria seria
     * "tardo demasiado": otra pista falsa.
     */
    /**
     * Los nombres que puede tener el conversor de HEIC.
     *
     * libheif renombro su programa: lo que durante anios se llamo
     * heif-convert pasa a llamarse heif-dec en las versiones recientes,
     * y en las nuevas de Debian ya solo esta el nombre nuevo. El paquete
     * se instala igual, la imagen se construye igual, y el programa que
     * se invoca no existe.
     */
    private const PROGRAMAS_HEIC = ['heif-dec', 'heif-convert'];

    /**
     * Donde esta el conversor, o null.
     *
     * Se busca a mano por el PATH en vez de preguntarle a `which` o
     * usar `command -v`: eso exigiria pasar por una shell, y una shell
     * en medio es una pieza mas que puede faltar o comportarse distinto.
     * Aqui solo se mira si hay un archivo ejecutable con ese nombre.
     */
    private static function programaDeHeic(): ?string
    {
        static $encontrado = false;   // false = sin buscar todavia
        if ($encontrado !== false) {
            return $encontrado;
        }

        $rutas = array_filter(explode(':', (string) getenv('PATH')));
        if (!$rutas) {
            $rutas = ['/usr/bin', '/usr/local/bin', '/bin'];
        }

        foreach (self::PROGRAMAS_HEIC as $nombre) {
            foreach ($rutas as $dir) {
                $ruta = rtrim($dir, '/') . '/' . $nombre;
                if (@is_executable($ruta)) {
                    return $encontrado = $ruta;
                }
            }
        }

        return $encontrado = null;
    }

    /** El aviso de que no hay con que convertir, con los nombres que se buscaron. */
    private static function noHayPrograma(): string
    {
        return 'no encuentro el conversor de HEIC (busque '
             . implode(' y ', self::PROGRAMAS_HEIC)
             . '). Reconstruye la imagen de PHP: docker compose build app';
    }

    private static function deHeicAJpeg(string $contenido, ?string &$porQue = null): ?string
    {
        if (!function_exists('proc_open')) {
            $porQue = 'proc_open esta deshabilitado en este PHP';
            return null;
        }

        $entrada = tempnam(sys_get_temp_dir(), 'heic_');
        if ($entrada === false) {
            $porQue = 'no se pudo crear el archivo temporal';
            return null;
        }
        $salida = $entrada . '.jpg';

        try {
            if (@file_put_contents($entrada, $contenido) === false) {
                $porQue = 'no se pudo escribir el archivo temporal';
                return null;
            }

            $programa = self::programaDeHeic();
            if ($programa === null) {
                $porQue = self::noHayPrograma();
                return null;
            }

            $proceso = @proc_open(
                [$programa, '-q', '92', $entrada, $salida],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $tubos
            );

            if (!is_resource($proceso)) {
                $porQue = 'no se pudo arrancar ' . basename($programa);
                return null;
            }

            // Se LEEN los dos tubos mientras el programa corre. Ver el
            // comentario de arriba: sin esto se pierde el motivo y, con
            // bastante texto, el programa se cuelga.
            foreach ($tubos as $t) { stream_set_blocking($t, false); }
            $dicho = [1 => '', 2 => ''];

            /*
               El codigo de salida se toma de proc_get_status, NO de
               proc_close.

               Esto es una trampa de PHP y me costo una entrega entera.
               En cuanto proc_get_status() ve que el proceso termino, PHP
               lo recoge (hace el waitpid) y se guarda su codigo de
               salida. proc_close() ya no tiene a quien preguntarle, asi
               que devuelve -1 SIEMPRE que antes se haya llamado a
               proc_get_status.

               Y este bucle llama a proc_get_status en cada vuelta,
               porque es como sabe cuando parar. Asi que proc_close
               devolvia -1 incluso cuando heif-convert habia convertido
               la foto perfectamente. El mensaje que le llegaba a la
               doctora era:

                   "heif-convert salio con codigo -1 y sin decir nada"

               Y ese "sin decir nada" era la pista: un programa que
               falla se queja. El que no dice nada es el que funciono.
               Yo lo lei como "fallo de forma misteriosa" y me fui a
               buscar paquetes que faltaban en el servidor.

               El codigo se guarda en el momento exacto en que se
               detecta el final, que es la unica vez que es de fiar.
            */
            $codigo = null;

            // Treinta segundos de sobra para una foto de telefono. Si no
            // acabo, algo va mal y es mejor cortar que dejar a Apache
            // esperando a un proceso colgado.
            $limite = microtime(true) + 30;
            while (microtime(true) < $limite) {
                foreach ($tubos as $i => $t) {
                    $trozo = @fread($t, 8192);
                    if (is_string($trozo) && $trozo !== '') {
                        // Un tope: un programa en bucle podria escribir
                        // sin parar y llenar la memoria de PHP.
                        if (strlen($dicho[$i]) < 8192) { $dicho[$i] .= $trozo; }
                    }
                }

                $estado = proc_get_status($proceso);
                if (!$estado['running']) {
                    $codigo = (int) $estado['exitcode'];
                    break;
                }
                usleep(20000);
            }

            if ($codigo === null) {
                proc_terminate($proceso, 9);
                foreach ($tubos as $t) { @fclose($t); }
                proc_close($proceso);
                $porQue = 'heif-convert tardo mas de 30 segundos';
                return null;
            }

            // Lo que quedara en los tubos despues de terminar.
            foreach ($tubos as $i => $t) {
                $resto = @stream_get_contents($t);
                if (is_string($resto) && strlen($dicho[$i]) < 8192) {
                    $dicho[$i] .= $resto;
                }
                fclose($t);
            }

            // Se cierra por higiene; lo que devuelva da igual, y por lo
            // dicho arriba sera -1.
            proc_close($proceso);

            $mensaje = trim($dicho[2] !== '' ? $dicho[2] : $dicho[1]);

            /*
               El 127 se traduce.

               Es el codigo con el que sale un proceso cuando no se pudo
               ejecutar el programa. Y aqui llega de verdad: proc_open
               con un arreglo no pasa por una shell, asi que PHP bifurca
               y el hijo intenta el exec; si el programa no esta, el
               padre recibe un proceso valido y un 127. Por eso la rama
               de "no esta instalado" de arriba —la del is_resource—
               nunca saltaba: con la forma de arreglo, proc_open casi
               siempre tiene exito aunque el programa no exista.

               Sin esta traduccion, lo que se lee es "salio con codigo
               127 y sin decir nada", que suena a fallo misterioso del
               conversor. Y no hay ningun conversor: ese es el punto.
            */
            if ($codigo === 127) {
                $porQue = self::noHayPrograma();
                return null;
            }

            if ($codigo !== 0) {
                $porQue = basename((string) $programa) . " salio con codigo $codigo"
                        . ($mensaje !== '' ? ": $mensaje" : ' y sin decir nada');
                return null;
            }
            if (!is_file($salida)) {
                $porQue = basename((string) $programa) . ' dijo que todo bien y no dejo el archivo'
                        . ($mensaje !== '' ? ": $mensaje" : '');
                return null;
            }

            $jpeg = @file_get_contents($salida);
            if ($jpeg === false || $jpeg === '') {
                $porQue = 'el JPEG convertido salio vacio';
                return null;
            }

            /*
               Y se comprueba que de verdad sea una imagen.

               No sobra: lo que decide si esta foto entra al expediente
               es el codigo de salida de otro programa, y acabo de
               aprender por las malas lo poco de fiar que puede ser ese
               dato. Mirar el archivo es mirar el hecho, no el informe
               de alguien sobre el hecho. Un JPEG a medias —se lleno el
               disco a mitad de la escritura— pasaria las dos
               comprobaciones de arriba y llegaria al expediente roto.
            */
            if (@getimagesizefromstring($jpeg) === false) {
                $porQue = 'lo que devolvio heif-convert no es una imagen valida';
                return null;
            }

            return $jpeg;
        } finally {
            // Pase lo que pase: nada de archivos sueltos en /tmp.
            @unlink($entrada);
            @unlink($salida);
        }
    }

    /**
     * Una miniatura cuadrada para la lista del expediente.
     *
     * Devuelve un JPEG de $lado px de ancho maximo, o null si el
     * contenido no es una imagen que GD sepa abrir —un PDF, por
     * ejemplo—. Quien llama decide que hacer con el null; aqui no se
     * inventa nada.
     *
     * Es aparte de optimizar() porque hace otra cosa: optimizar()
     * prepara el archivo que se va a GUARDAR y conserva todo el detalle
     * que una radiografia necesita para ampliarse; esto genera algo
     * para mirar de lejos en un renglon de lista. Mezclarlas obligaria
     * a un perfil "mini" dentro de la tabla de perfiles de guardado, y
     * un dia alguien archivaria una radiografia de 160 px.
     */
    public static function miniatura(string $contenido, int $lado = 160): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $original = @imagecreatefromstring($contenido);
        if ($original === false) {
            return null;
        }

        try {
            // Se endereza igual que al guardar: una foto de telefono
            // tomada en vertical sale acostada si se ignora el EXIF, y
            // en una miniatura chica eso es lo unico que se ve.
            $imagen = self::enderezar($original, $contenido);

            $ancho = imagesx($imagen);
            $alto  = imagesy($imagen);
            $largo = max($ancho, $alto);
            if ($largo <= 0) {
                return null;
            }

            // Nunca se agranda: estirar una imagen ya chica solo
            // inventa pixeles y pesa mas.
            $escala = $largo > $lado ? $lado / $largo : 1.0;
            $nAncho = max(1, (int) round($ancho * $escala));
            $nAlto  = max(1, (int) round($alto  * $escala));

            $destino = imagecreatetruecolor($nAncho, $nAlto);
            $blanco  = imagecolorallocate($destino, 255, 255, 255);
            imagefilledrectangle($destino, 0, 0, $nAncho, $nAlto, $blanco);
            imagecopyresampled($destino, $imagen, 0, 0, 0, 0,
                $nAncho, $nAlto, $ancho, $alto);

            ob_start();
            imagejpeg($destino, null, 72);
            $jpeg = (string) ob_get_clean();

            imagedestroy($destino);
            if ($imagen !== $original) { imagedestroy($imagen); }

            return $jpeg !== '' ? $jpeg : null;
        } finally {
            if (is_resource($original) || $original instanceof GdImage) {
                @imagedestroy($original);
            }
        }
    }

    /**
     * ¿Se puede convertir una foto de iPhone en esta instalacion?
     *
     * Devuelve null si todo bien, o el motivo. Lo usa el guion de
     * actualizacion para avisar ANTES de que alguien suba una foto y
     * se lleve el rechazo con el paciente enfrente.
     *
     * @param string|null $comoSeSupo con que seguridad se contesta
     *
     * Ese segundo dato importa. No basta con que el programa exista:
     * en algunas versiones de Debian el decodificador de HEVC va en un
     * paquete aparte, asi que el programa corre y falla en cada foto.
     * Asi que, cuando se puede, se hace la prueba COMPLETA: se fabrica
     * un HEIC al momento con heif-enc y se le pide al conversor que lo
     * lea. Eso contesta la pregunta de verdad.
     *
     * Y cuando no se puede —falta el codificador, por ejemplo— el
     * veredicto se queda en "el programa esta" y $comoSeSupo lo dice.
     * Un diagnostico que contesta "convierte bien" habiendo comprobado
     * solo que hay un archivo ejecutable esta afirmando mas de lo que
     * sabe, y eso es justo lo que me hizo perder tres intentos en este
     * mismo fallo.
     */
    public static function porQueNoConvierteHeic(?string &$comoSeSupo = null): ?string
    {
        $comoSeSupo = 'no se pudo comprobar';

        if (!function_exists('proc_open')) {
            return 'proc_open esta deshabilitado en este PHP';
        }

        /*
           Se mira si el archivo esta, no si proc_open "tuvo exito".

           La version anterior arrancaba el programa con --help y daba
           por buena la instalacion si proc_open devolvia un recurso. No
           sirve de nada: con la forma de arreglo, proc_open casi siempre
           devuelve un recurso aunque el programa no exista —el fallo
           ocurre en el hijo, despues—. Asi que esta comprobacion decia
           "ok" en una instalacion donde no habia conversor, que es
           exactamente la que tenia Angel.
        */
        $programa = self::programaDeHeic();
        if ($programa === null) {
            return self::noHayPrograma();
        }

        /*
           La prueba de verdad: se fabrica un HEIC y se lee.

           El mismo paquete que trae el conversor trae el codificador
           (heif-enc), asi que se puede hacer el viaje completo sin
           depender de que alguien deje una foto de muestra en el
           repositorio: se dibuja una imagen de 8x8 con GD, se codifica a
           HEIC, y se le pide al conversor que la devuelva a JPEG.

           Esto es lo que distingue "el programa esta" de "convierte".
           Son dos cosas distintas y la diferencia no es teorica: en
           algunas versiones de Debian el decodificador de HEVC va en un
           paquete aparte, y entonces el programa esta y no convierte
           nada.
        */
        $comoSeSupo = 'solo se comprobo que el programa esta';

        $heic = self::heicDePrueba();
        if ($heic === null) {
            // Sin codificador no se puede hacer la prueba completa. Se
            // dice asi en vez de dar por buena la instalacion: el
            // veredicto se queda en lo que de verdad se comprobo.
            return null;
        }

        $porQue = null;
        $jpeg = self::deHeicAJpeg($heic, $porQue);

        if ($jpeg === null) {
            $comoSeSupo = 'probado con un HEIC hecho al momento';
            return $porQue;
        }

        $comoSeSupo = 'probado de verdad: se creo un HEIC y se convirtio';
        return null;
    }

    /**
     * Un HEIC chiquito, hecho al momento, para probar la conversion.
     *
     * Devuelve null si no hay con que fabricarlo; eso no quiere decir
     * que la conversion falle, solo que no se puede comprobar aqui.
     */
    private static function heicDePrueba(): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $codificador = null;
        foreach (array_filter(explode(':', (string) getenv('PATH'))) as $dir) {
            $ruta = rtrim($dir, '/') . '/heif-enc';
            if (@is_executable($ruta)) { $codificador = $ruta; break; }
        }
        if ($codificador === null) {
            return null;
        }

        $jpg  = tempnam(sys_get_temp_dir(), 'prueba_');
        if ($jpg === false) { return null; }
        $heic = $jpg . '.heic';

        try {
            $im = imagecreatetruecolor(8, 8);
            imagefilledrectangle($im, 0, 0, 8, 8,
                imagecolorallocate($im, 120, 60, 140));
            imagejpeg($im, $jpg, 80);
            imagedestroy($im);

            $p = @proc_open([$codificador, '-q', '50', $jpg, '-o', $heic],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
            if (!is_resource($p)) { return null; }

            foreach ($tubos as $t) { @stream_get_contents($t); fclose($t); }
            proc_close($p);

            if (!is_file($heic)) { return null; }
            $datos = @file_get_contents($heic);
            return ($datos === false || $datos === '') ? null : $datos;
        } finally {
            @unlink($jpg);
            @unlink($heic);
        }
    }

    private static function enderezar($imagen, string $contenido)
    {
        if (!function_exists('exif_read_data')) {
            return $imagen;
        }

        try {
            $datos = @exif_read_data('data://image/jpeg;base64,'
                . base64_encode($contenido));
        } catch (Throwable $e) {
            return $imagen;
        }

        $orientacion = (int) ($datos['Orientation'] ?? 0);
        $grados = match ($orientacion) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($grados === 0) {
            return $imagen;
        }

        $girada = @imagerotate($imagen, (float) $grados, 0);
        return $girada === false ? $imagen : $girada;
    }

    /**
     * Que tan arriba se coloca el recorte dentro de la foto.
     * 0 = pegado arriba, 0.5 = centrado vertical.
     * 0.18 deja la cara centrada en un retrato de medio cuerpo.
     */
    private const SESGO_VERTICAL = 0.18;

    /**
     * Genera un cuadrado recortado a la altura de la cara.
     *
     * @param string $contenido bytes de la imagen original
     * @return array{contenido:string, mime:string, ancho:int, alto:int}
     * @throws RuntimeException si la imagen no se puede leer
     */
    public static function recorteRetrato(string $contenido, int $lado = self::LADO): array
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('La extension GD no esta disponible en PHP.');
        }

        $original = @imagecreatefromstring($contenido);
        if ($original === false) {
            throw new RuntimeException('El archivo no es una imagen valida.');
        }

        try {
            $ancho = imagesx($original);
            $alto  = imagesy($original);

            // El lado del cuadrado es el menor de los dos: asi el recorte
            // siempre cabe dentro de la foto sin bordes vacios.
            $corte = min($ancho, $alto);

            // Centrado horizontal.
            $x = (int) (($ancho - $corte) / 2);

            // Desplazado hacia arriba. Si la foto es apaisada no hay nada
            // que subir verticalmente y el desplazamiento queda en cero.
            $sobra = $alto - $corte;
            $y = (int) ($sobra * self::SESGO_VERTICAL);

            $destino = imagecreatetruecolor($lado, $lado);

            // Fondo blanco por si la original trae transparencia: un PNG
            // con fondo transparente se veria negro sin esto.
            $blanco = imagecolorallocate($destino, 255, 255, 255);
            imagefilledrectangle($destino, 0, 0, $lado, $lado, $blanco);

            imagecopyresampled(
                $destino, $original,
                0, 0,          // destino
                $x, $y,        // origen
                $lado, $lado,  // tamano destino
                $corte, $corte // tamano origen
            );

            ob_start();
            imagejpeg($destino, null, 88);
            $recorte = (string) ob_get_clean();

            imagedestroy($destino);

            return [
                'contenido' => $recorte,
                'mime'      => 'image/jpeg',
                'ancho'     => $lado,
                'alto'      => $lado,
            ];
        } finally {
            imagedestroy($original);
        }
    }

    /** Dimensiones de una imagen en memoria, o null si no se puede leer. */
    public static function medidas(string $contenido): ?array
    {
        $info = @getimagesizefromstring($contenido);
        if ($info === false) {
            return null;
        }
        return ['ancho' => $info[0], 'alto' => $info[1], 'mime' => $info['mime']];
    }
}
