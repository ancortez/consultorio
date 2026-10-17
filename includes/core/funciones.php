<?php
declare(strict_types=1);

/**
 * Escapa texto para imprimirlo en HTML.
 * REGLA DEL PROYECTO: toda variable que se imprima en una vista pasa por e().
 * Esto evita XSS, que es el otro riesgo grande junto con la inyeccion SQL.
 */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Lee un parametro de GET/POST ya limpio de espacios. */
function param(string $clave, $default = null)
{
    $v = $_POST[$clave] ?? $_GET[$clave] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function paramInt(string $clave, ?int $default = null): ?int
{
    $v = param($clave);
    return ($v === null || $v === '') ? $default : (int) $v;
}

/** Formatea un importe en pesos mexicanos. */
function money($monto): string
{
    return '$' . number_format((float) $monto, 2, '.', ',');
}

/** dd/mm/aaaa a partir de un DATE o DATETIME de MySQL. */
function fecha(?string $valor, string $formato = 'd/m/Y'): string
{
    if (empty($valor) || str_starts_with($valor, '0000')) {
        return '';
    }
    $ts = strtotime($valor);
    return $ts ? date($formato, $ts) : '';
}

function fechaHora(?string $valor): string
{
    return fecha($valor, 'd/m/Y H:i');
}

/*
   El "cuando" de una cita, que no siempre tiene hora.

   Una cita apartada para el dia se guarda a las 00:00. Pasada por
   fechaHora() sale "27/11/2026 00:00", y eso es peor que no decir
   nada: parece un horario, y de hecho parece el peor horario posible.
   Alguien que lo lee de pasada entiende que ese nino viene a
   medianoche.

   Estas dos funciones son el unico sitio donde se decide como se
   escribe eso. Son cuatro pantallas las que lo ensenian —inicio dos
   veces, la ficha del paciente dos— y repartir el "if" por las cuatro
   acabaria con una diciendo una cosa y otra diciendo otra.

   El arreglo de la cita se pasa completo, no la fecha sola: la hora no
   se puede juzgar sin saber si la cita la tiene, y eso vive en otra
   columna.
*/
function horaCita(array $cita): string
{
    return (int) ($cita['sin_hora'] ?? 0) === 1
        ? 'Sin hora'
        : fecha($cita['fecha_inicio'] ?? null, 'H:i');
}

function cuandoCita(array $cita): string
{
    $dia = fecha($cita['fecha_inicio'] ?? null);

    return (int) ($cita['sin_hora'] ?? 0) === 1
        ? "$dia · sin hora"
        : trim("$dia " . fecha($cita['fecha_inicio'] ?? null, 'H:i'));
}

/*
   El codigo con el que se nombra un hallazgo: CLIN_01, RADIO_03.

   Lo pidio la doctora asi. El sentido es que la exploracion se dicta
   —"clinico, foto; radiografico, foto"— y escribir una descripcion por
   cada cosa con el paciente en el sillon no se hace: lo que se hacia
   era dejarla en blanco, y entonces el expediente tenia una lista de
   hallazgos sin nombre que nadie podia leer despues. Un codigo corto se
   pone solo, distingue uno de otro y sirve para referirse a el en voz
   alta: "la del CLIN_02".

   El numero sale del MAXIMO que ya exista, no de contar los hallazgos.
   Contando, borrar el CLIN_02 de tres hallazgos haria que el siguiente
   volviera a llamarse CLIN_03 —que ya existe— y dos hallazgos
   distintos del mismo dia tendrian el mismo nombre. Con el maximo eso
   no puede pasar: los numeros no se reciclan.

   Son dos funciones puras —sin base de datos— a proposito: la pantalla
   propone el codigo y el servidor lo pone cuando el campo llega vacio,
   y las dos tienen que dar lo mismo. Asi la regla se puede correr con
   datos y comparar contra la copia del JavaScript.
*/
function prefijoHallazgo(string $tipo): string
{
    return mayus($tipo) === 'RADIOGRAFICO' ? 'RADIO' : 'CLIN';
}

/**
 * @param string[] $descripciones las de los hallazgos que ya existen
 */
function codigoHallazgo(string $prefijo, array $descripciones): string
{
    $maximo = 0;

    foreach ($descripciones as $d) {
        // Ancladas al principio y con el guion bajo: un hallazgo que se
        // llame "DUELE AL MASTICAR CLIN_09" no debe correr la cuenta.
        if (preg_match('/^' . preg_quote($prefijo, '/') . '_(\d+)/',
                mayus(trim((string) $d)), $m)) {
            $maximo = max($maximo, (int) $m[1]);
        }
    }

    // Dos digitos hasta el 99, y de ahi en adelante los que hagan
    // falta: un consultorio no llega a cien hallazgos en una consulta,
    // pero truncar seria crear el duplicado que esto viene a evitar.
    return $prefijo . '_' . str_pad((string) ($maximo + 1), 2, '0', STR_PAD_LEFT);
}

/** Edad en anios a partir de la fecha de nacimiento. */
function edad(?string $fechaNacim): ?int
{
    if (empty($fechaNacim)) {
        return null;
    }
    try {
        return (new DateTime($fechaNacim))->diff(new DateTime('today'))->y;
    } catch (Exception $e) {
        return null;
    }
}

/** Nombre completo a partir de las tres partes. */
function nombreCompleto(?string $nombres, ?string $ap, ?string $am): string
{
    return trim(implode(' ', array_filter([$nombres, $ap, $am])));
}

/** Iniciales para el avatar cuando el paciente no tiene fotografia. */
function iniciales(string $nombreCompleto): string
{
    $partes = preg_split('/\s+/', trim($nombreCompleto)) ?: [];
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return $ini !== '' ? $ini : '?';
}

/** true si la peticion viene por fetch/AJAX. */
function esAjax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/** Valida que la peticion sea POST; corta si no lo es. */
function soloPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        Respuesta::error('Metodo no permitido.', 405);
    }
}

/** Genera el siguiente folio con prefijo y relleno de ceros. */
function siguienteFolio(string $tabla, string $columna, string $prefijo, int $largo = 4): string
{
    $sql = "SELECT MAX(CAST(SUBSTRING_INDEX($columna, '-', -1) AS UNSIGNED))
            FROM `$tabla` WHERE $columna LIKE :p";
    $max = (int) Conexion::valor($sql, [':p' => $prefijo . '-%']);
    return sprintf('%s-%0' . $largo . 'd', $prefijo, $max + 1);
}

/** Lee un parametro configurable de la tabla parametro con respaldo. */
function parametro(string $clave, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Conexion::todos('SELECT clave, valor FROM parametro') as $p) {
            $cache[$p['clave']] = $p['valor'];
        }
    }
    return $cache[$clave] ?? $default;
}

/** Fecha larga en espaniol, sin depender de los locales del sistema. */
function fechaLarga(?string $valor = null): string
{
    $ts = $valor ? strtotime($valor) : time();
    $dias  = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'];
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio',
              'agosto','septiembre','octubre','noviembre','diciembre'];
    return sprintf('%s %d de %s de %d',
        $dias[(int) date('w', $ts)], (int) date('j', $ts),
        $meses[(int) date('n', $ts) - 1], (int) date('Y', $ts));
}

/**
 * Convierte a MAYUSCULAS respetando acentos y la enie.
 *
 * strtoupper() de PHP no es seguro con UTF-8: deja "José" como "JOSé".
 * mb_strtoupper si lo hace bien. Esta es la unica funcion que se debe
 * usar para subir texto en el sistema.
 */
function mayus(?string $texto): ?string
{
    if ($texto === null) {
        return null;
    }
    return mb_strtoupper(trim($texto), 'UTF-8');
}

/**
 * Lee un parametro y lo devuelve ya en mayusculas y sin espacios sobrantes.
 * Devuelve null si queda vacio, para que entre como NULL a la base y no
 * como cadena vacia (que despues obliga a preguntar por las dos cosas).
 */
function paramMayus(string $clave): ?string
{
    $v = mayus((string) param($clave, ''));
    return ($v === null || $v === '') ? null : $v;
}

/**
 * Colapsa espacios repetidos. Util en nombres: "JUAN   PEREZ" -> "JUAN PEREZ".
 */
function limpiarEspacios(?string $texto): ?string
{
    if ($texto === null) {
        return null;
    }
    $t = preg_replace('/\s+/u', ' ', trim($texto));
    return $t === '' ? null : $t;
}

/** Nombre listo para guardar: sin espacios de mas y en mayusculas. */
function nombreLimpio(string $clave): ?string
{
    return limpiarEspacios(mayus((string) param($clave, '')));
}

/**
 * Limpia un texto largo CONSERVANDO los renglones.
 *
 * limpiarEspacios() usa \s+, que incluye el salto de linea. Sirve para
 * un nombre, donde "JUAN   PEREZ" tiene que volverse "JUAN PEREZ".
 *
 * En una receta hace estrago. La doctora escribe:
 *
 *     AMOXICILINA 500 MG
 *     1 CADA 8 HORAS POR 7 DIAS
 *
 *     PARACETAMOL SI HAY DOLOR
 *
 * y al guardarse queda todo en un renglon corrido, ilegible en la hoja
 * impresa. Esta version limpia DENTRO de cada renglon y deja la
 * estructura en pie:
 *
 *   - espacios y tabulaciones repetidos dentro de un renglon -> uno
 *   - espacios al final de cada renglon -> fuera
 *   - tres o mas renglones en blanco seguidos -> dos
 *   - saltos de Windows (\r\n) -> \n
 */
function limpiarTexto(?string $texto): ?string
{
    if ($texto === null) {
        return null;
    }

    $t = str_replace(["\r\n", "\r"], "\n", $texto);

    // \h es "espacio horizontal": espacio y tabulador, nunca el salto.
    $t = preg_replace('/\h+/u', ' ', $t);
    $t = preg_replace('/ *\n */u', "\n", $t);
    $t = preg_replace('/\n{3,}/u', "\n\n", $t);
    $t = trim($t);

    return $t === '' ? null : $t;
}

/**
 * Lee un parametro de texto largo en mayusculas, con los renglones
 * intactos. Es el equivalente de paramMayus() para recetas, notas de
 * evolucion e indicaciones.
 */
function paramTexto(string $clave): ?string
{
    return limpiarTexto(mayus((string) param($clave, '')));
}

/**
 * Decide la ocupacion que corresponde por edad, segun la regla acordada:
 *   menos de 3 anios  -> NINGUNA
 *   de 3 a 15 anios   -> ESTUDIANTE
 *   16 en adelante    -> null (la captura el usuario)
 *
 * Devuelve el id_ocupacion o null.
 */
function ocupacionPorEdad(?string $fechaNacim): ?int
{
    $anios = edad($fechaNacim);
    if ($anios === null) {
        return null;
    }
    if ($anios < 3) {
        $clave = 'NINGUNA';
    } elseif ($anios <= 15) {
        $clave = 'ESTUDIANTE';
    } else {
        return null;
    }
    $id = Conexion::valor(
        'SELECT id_ocupacion FROM ocupacion WHERE clave = :c LIMIT 1',
        [':c' => $clave]
    );
    return $id !== null ? (int) $id : null;
}

/**
 * ¿Es un RFC con forma de RFC?
 *
 * Acepta las dos longitudes, CON homoclave y SIN ella:
 *
 *     COMA861125       13 caracteres, persona fisica sin homoclave
 *     COMA8611257K1    con homoclave
 *
 * Por que las dos. La homoclave la asigna el SAT y aparece en la
 * constancia; un tutor que no pide factura no siempre la tiene a mano,
 * y lo que si recuerda son sus iniciales y su fecha de nacimiento. La
 * regla anterior exigia los tres caracteres finales, asi que ese tutor
 * no podia guardar su RFC en absoluto: o lo inventaba —que es peor que
 * no tenerlo— o se quedaba el campo vacio.
 *
 * Tres o cuatro letras al principio: cuatro para persona fisica
 * (dos del apellido paterno, una del materno, una del nombre) y tres
 * para persona moral. La Ñ y el & son validos en razones sociales.
 *
 * Esto comprueba la FORMA, no que el RFC exista ni que los digitos de
 * la fecha sean una fecha real. Para eso haria falta preguntarle al
 * SAT, y aqui el dato es informativo: va en la factura si algun dia se
 * pide, y una validacion que rechaza un RFC bueno cuesta mas que una
 * que deja pasar uno con una errata.
 */
function rfcConForma(string $rfc): bool
{
    return (bool) preg_match('/^[A-ZÑ&]{3,4}\d{6}([A-Z0-9]{3})?$/u', $rfc);
}

/**
 * El ordinal femenino abreviado, como se escribe a mano en el consultorio:
 * "1RA CITA", "2DA CITA", "3RA CITA".
 *
 * Por que una tabla y no una regla: en espaniol la terminacion no sale
 * de una cuenta. Es RA, DA, RA, TA, TA, TA, MA, VA, NA, MA, y del 11 al
 * 19 vuelve a seguir a la unidad salvo el 11 y el 12, que son VA
 * (undecima, duodecima) y no "1RA"/"2DA". Cualquier regla aritmetica
 * que se invente aqui se equivoca en esos dos, y el 11 y el 12 no son
 * raros: son la cita de un plan largo de ortodoncia.
 *
 * Pasando de 20 se deja de abreviar. Un plan con 21 consultas existe,
 * pero "21RA" no se escribe asi en ningun lado, y escribir mal el
 * expediente por completar una tabla no vale la pena.
 */
function ordinalCorto(int $n): string
{
    $tabla = [
        1 => '1RA',   2 => '2DA',   3 => '3RA',   4 => '4TA',   5 => '5TA',
        6 => '6TA',   7 => '7MA',   8 => '8VA',   9 => '9NA',  10 => '10MA',
       11 => '11VA', 12 => '12VA', 13 => '13RA', 14 => '14TA', 15 => '15TA',
       16 => '16TA', 17 => '17MA', 18 => '18VA', 19 => '19NA', 20 => '20MA',
    ];

    if ($n < 1) {
        return '1RA';
    }
    return $tabla[$n] ?? ('No. ' . $n);
}

/**
 * El motivo que se propone para una consulta de plan de tratamiento.
 *
 * Se arma aqui y no en la pantalla porque el ordinal depende de cuantas
 * consultas lleva el plan, que es un dato de la base. La pantalla lo
 * recibe ya escrito y solo lo copia al campo.
 */
function motivoDeCitaDePlan(string $folioPlan, int $consultasPrevias): string
{
    return ordinalCorto($consultasPrevias + 1)
        . ' CITA PLAN DE TRATAMIENTO ' . mb_strtoupper($folioPlan, 'UTF-8');
}

/**
 * El monto escrito con letra, como lo pide un recibo.
 *
 * No es un adorno: es lo que impide que a un recibo de $500 se le
 * agregue un cero despues de firmado. Por eso los centavos van como
 * fraccion sobre cien y no redondeados.
 */
function montoEnLetra(float $monto): string
{
    $entero   = (int) floor(abs($monto));
    $centavos = (int) round((abs($monto) - $entero) * 100);

    // El redondeo de los centavos puede empujar el entero.
    if ($centavos === 100) { $entero++; $centavos = 0; }

    $texto = $entero === 0 ? 'cero' : numeroEnLetra($entero);

    return mb_strtoupper(
        ($monto < 0 ? 'menos ' : '') . $texto
        . ' pesos ' . sprintf('%02d', $centavos) . '/100 M.N.',
        'UTF-8'
    );
}

/**
 * Un entero en palabras, hasta cientos de millones.
 *
 * Se arma por grupos de tres cifras (millones, miles, unidades), que
 * es como se lee en espaniol y evita una tabla de casos especiales.
 */
function numeroEnLetra(int $n): string
{
    if ($n === 0)   { return 'cero'; }
    if ($n < 0)     { return 'menos ' . numeroEnLetra(-$n); }

    $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis',
                 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece',
                 'catorce', 'quince', 'dieciseis', 'diecisiete', 'dieciocho',
                 'diecinueve', 'veinte'];

    $decenas  = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta',
                 'sesenta', 'setenta', 'ochenta', 'noventa'];

    $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos',
                 'quinientos', 'seiscientos', 'setecientos', 'ochocientos',
                 'novecientos'];

    /** Un grupo de tres cifras: 0 a 999. */
    $tercia = static function (int $x) use ($unidades, $decenas, $centenas): string {
        if ($x === 0)   { return ''; }
        if ($x === 100) { return 'cien'; }

        $partes = [];

        $c = intdiv($x, 100);
        $r = $x % 100;
        if ($c > 0) { $partes[] = $centenas[$c]; }

        if ($r > 0) {
            if ($r <= 20) {
                $partes[] = $unidades[$r];
            } elseif ($r < 30) {
                // Del 21 al 29 va junto: veintiuno, veintidos...
                $partes[] = 'veinti' . $unidades[$r - 20];
            } else {
                $d = intdiv($r, 10);
                $u = $r % 10;
                $partes[] = $u > 0
                    ? $decenas[$d] . ' y ' . $unidades[$u]
                    : $decenas[$d];
            }
        }

        return implode(' ', $partes);
    };

    $millones = intdiv($n, 1000000);
    $miles    = intdiv($n % 1000000, 1000);
    $resto    = $n % 1000;

    $partes = [];

    if ($millones > 0) {
        $partes[] = $millones === 1
            ? 'un millon'
            : $tercia($millones) . ' millones';
    }

    if ($miles > 0) {
        // "un mil" no se dice: es "mil".
        $partes[] = $miles === 1 ? 'mil' : $tercia($miles) . ' mil';
    }

    if ($resto > 0) {
        $partes[] = $tercia($resto);
    }

    return implode(' ', $partes);
}
