<?php
declare(strict_types=1);

/**
 * El odontograma: el dibujo de la dentadura y el estado de cada diente.
 *
 * Dos cosas que estaban separadas y tenian que juntarse:
 *
 *   - EL DIBUJO. Las dos arcadas con notacion FDI vivian dentro de
 *     _modal_dientes.php, armadas con unas funciones anonimas. Servia
 *     mientras hubo un solo sitio que las dibujaba. Ahora hay dos —el
 *     selector de organo dentario del plan y la pestania del paciente—
 *     y una copia del dibujo seria dos dentaduras que se separan: la
 *     primera vez que alguien corrija una raiz, lo hara en una sola.
 *
 *   - EL ESTADO. Que tiene cada diente HOY: sano, amalgama, ausente.
 *     Lo pidio la doctora, y el sentido es que la boca del paciente no
 *     se puede reconstruir leyendo cuatro planes de tratamiento y doce
 *     consultas. Es la foto de como esta ahora.
 *
 * Como se guarda
 * --------------
 * En las tablas odontograma y odontograma_detalle, que ya existian en
 * el esquema y no se usaban. Un solo odontograma por paciente, con
 * tipo = 'actual', y un renglon por diente que tenga algo distinto de
 * lo normal.
 *
 * UN SOLO odontograma y no uno por fecha, aunque las tablas lo
 * permitan: lo que se pidio es el estado actual, y una historia de
 * odontogramas por fecha es otra funcion —mas grande— que nadie ha
 * pedido. Los cambios de cada dia ya quedan registrados donde
 * corresponde: en los hallazgos de la consulta y en los procedimientos
 * del plan. Guardar aqui ademas una linea de tiempo seria tener dos
 * versiones de la misma verdad.
 *
 * Los dientes SIN renglon no son un hueco: son los que no tienen nada
 * que decir. Escribir "sano" en los treinta y dos al abrir la pestania
 * llenaria la tabla de datos que nadie capturo, y "sano" afirmado por
 * el sistema no es lo mismo que "sano" dicho por la doctora.
 */
final class Odontograma
{
    /** El tipo con el que se guarda el estado actual del paciente. */
    public const TIPO_ACTUAL = 'actual';

    /*
       Los cuadrantes se acomodan como los ve el dentista frente al
       paciente: la derecha del paciente queda a la izquierda de la
       pantalla. Por eso los numeros de los cuadrantes 1 y 4 van al
       reves.
    */
    public const PERMANENTES = [
        'arriba' => [[18, 17, 16, 15, 14, 13, 12, 11],
                     [21, 22, 23, 24, 25, 26, 27, 28]],
        'abajo'  => [[48, 47, 46, 45, 44, 43, 42, 41],
                     [31, 32, 33, 34, 35, 36, 37, 38]],
    ];

    public const TEMPORALES = [
        'arriba' => [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65]],
        'abajo'  => [[85, 84, 83, 82, 81], [71, 72, 73, 74, 75]],
    ];

    /*
       La forma del diente segun su posicion.

       Antes todos eran el mismo hexagono. Un odontograma se lee por la
       forma —el incisivo es una pala, el molar tiene cuatro cuspides— y
       con treinta y dos hexagonos identicos hay que ir leyendo el
       numero de cada uno para ubicarse, que es justo lo que el dibujo
       deberia ahorrar.

       El ultimo digito de la notacion FDI dice que diente es dentro del
       cuadrante: 1 y 2 incisivos, 3 canino, 4 y 5 premolares, 6 a 8
       molares. En los temporales (51-85) el 4 y el 5 ya son molares,
       porque no hay premolares en la denticion de leche.
    */
    public static function forma(int $n): string
    {
        $pos      = $n % 10;
        $temporal = $n >= 51;

        if ($pos <= 2)  { return 'incisivo'; }
        if ($pos === 3) { return 'canino'; }
        if ($pos <= 5)  { return $temporal ? 'molar' : 'premolar'; }
        return 'molar';
    }

    /*
       Cada forma es un SVG de 40x52: corona arriba, raices abajo.

       Van dibujados a mano y no con una tipografia de iconos porque
       hacen falta DOS zonas pintables —corona y raiz— y un icono es una
       sola silueta. Ademas asi el diente cambia de color sin cambiar de
       forma, que es lo que permite pintarle su estado.
    */
    private const CORONAS = [
        // Pala ancha y plana: el borde incisal es recto.
        'incisivo' => 'M8,4 h24 a3,3 0 0 1 3,3 v14 '
                    . 'c0,7 -4,11 -15,11 s-15,-4 -15,-11 v-14 a3,3 0 0 1 3,-3 z',
        // Punta: la cuspide unica del canino.
        'canino'   => 'M20,2 l14,8 v11 c0,7 -5,11 -14,11 s-14,-4 -14,-11 v-11 z',
        // Dos cuspides.
        'premolar' => 'M9,6 q5,-4 11,0 q6,-4 11,0 v15 '
                    . 'c0,7 -5,11 -11,11 s-11,-4 -11,-11 z',
        // Cuatro cuspides: la corona cuadrada del molar.
        'molar'    => 'M7,6 q4,-4 8,0 q5,-4 10,0 q4,-4 8,0 v15 '
                    . 'c0,7 -6,11 -13,11 s-13,-4 -13,-11 z',
    ];

    public static function svg(string $forma): string
    {
        // Una raiz en los de adelante, dos en los de atras.
        $raices = in_array($forma, ['molar', 'premolar'], true)
            ? '<path class="rz" d="M14,31 l-3,17 q-1,3 2,3 q3,0 3,-3 l1,-17 z"/>'
            . '<path class="rz" d="M26,31 l3,17 q1,3 -2,3 q-3,0 -3,-3 l-1,-17 z"/>'
            : '<path class="rz" d="M16,31 l-2,18 q0,3 4,3 q4,0 4,-3 l-2,-18 z"/>';

        return '<svg viewBox="0 0 40 52" class="dibujo-diente">'
             . $raices
             . '<path class="cr" d="' . self::CORONAS[$forma] . '"/>'
             . '</svg>';
    }

    /** Los nombres largos, para el globo de ayuda. */
    public static function nombres(): array
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }

        $cache = [];
        foreach (Conexion::todos(
                'SELECT id_diente, nombre_diente FROM diente') as $d) {
            $cache[(int) $d['id_diente']] = $d['nombre_diente'];
        }
        return $cache;
    }

    /**
     * Un diente, con su estado pintado si lo tiene.
     *
     * @param array $estado  id_diente => ['nombre_condicion', 'color', ...]
     */
    public static function diente(int $n, string $clase, array $estado = []): string
    {
        $forma   = self::forma($n);
        $nombres = self::nombres();
        $suyo    = $estado[$n] ?? null;

        /*
           El color del estado viaja en una variable CSS, no en el fill.

           Escrito como style="fill:#..." pisaria tambien el color de la
           seleccion, y entonces un diente elegido para un procedimiento
           dejaria de verse elegido en cuanto tuviera estado. Con la
           variable, el CSS decide quien gana: el estado pinta el
           relleno y la seleccion el contorno, asi que las dos cosas se
           ven a la vez. Son dos preguntas distintas —"¿que tiene este
           diente?" y "¿lo estoy eligiendo?"— y las dos hacen falta en
           la misma pantalla.
        */
        $titulo  = $nombres[$n] ?? '';
        $clases  = ['diente', $clase, $forma];
        $atributos = ['data-diente="' . $n . '"'];

        if ($suyo !== null) {
            $titulo   .= ' · ' . $suyo['nombre_condicion'];
            $clases[]  = 'con-estado';
            $atributos[] = 'style="--estado:' . e((string) $suyo['color']) . '"';
            $atributos[] = 'data-condicion="' . (int) $suyo['id_condicion'] . '"';
        }

        // Los atributos se arman en una lista y se unen al final. Antes
        // esto metia el style dentro de la cadena de clases cerrando la
        // comilla a mano —funcionaba y era una trampa para el siguiente
        // que agregara un atributo—.
        return '<button type="button" class="' . implode(' ', $clases) . '" '
             . implode(' ', $atributos)
             . ' title="' . e($titulo) . '">'
             . self::svg($forma)
             . '<span>' . $n . '</span></button>';
    }

    /** Una fila de dientes de un cuadrante. */
    public static function fila(array $nums, string $clase, array $estado = []): string
    {
        $html = '';
        foreach ($nums as $n) {
            $html .= self::diente((int) $n, $clase, $estado);
        }
        return $html;
    }

    /**
     * Una arcada completa: dos renglones con su linea media.
     *
     * @param array $arcada PERMANENTES o TEMPORALES
     */
    public static function arcada(array $arcada, string $clase, array $estado = []): string
    {
        return '<div class="arcada">'
             . '<div class="renglon">'
             .   '<div class="mitad derecha">'
             .     self::fila($arcada['arriba'][0], $clase, $estado)
             .   '</div>'
             .   '<div class="linea-media"></div>'
             .   '<div class="mitad">'
             .     self::fila($arcada['arriba'][1], $clase, $estado)
             .   '</div>'
             . '</div>'
             . '<div class="etiqueta-arcada">'
             .   '<span>Derecha del paciente</span>'
             .   '<span>Izquierda del paciente</span>'
             . '</div>'
             . '<div class="renglon">'
             .   '<div class="mitad derecha">'
             .     self::fila($arcada['abajo'][0], $clase, $estado)
             .   '</div>'
             .   '<div class="linea-media"></div>'
             .   '<div class="mitad">'
             .     self::fila($arcada['abajo'][1], $clase, $estado)
             .   '</div>'
             . '</div>'
             . '</div>';
    }

    // -----------------------------------------------------------------
    //  El estado del paciente
    // -----------------------------------------------------------------

    /** El catalogo de condiciones, con sus colores. */
    public static function condiciones(): array
    {
        return Conexion::todos(
            'SELECT id_condicion, nombre_condicion, color, es_tratamiento
               FROM condicion_dental
              ORDER BY es_tratamiento, id_condicion');
    }

    /**
     * El odontograma "actual" del paciente. Lo crea si no existe.
     *
     * Se crea al PEDIRLO y no al dar de alta el paciente: un expediente
     * nuevo no tiene por que arrastrar un renglon vacio en otra tabla, y
     * asi los pacientes que ya existen funcionan igual que los nuevos
     * sin tener que rellenarlos con una migracion.
     */
    public static function idDelPaciente(int $idExpediente, bool $crear = true): ?int
    {
        $id = Conexion::valor(
            'SELECT id_odontograma FROM odontograma
              WHERE id_registro_paciente = :r AND tipo = :t
              ORDER BY id_odontograma LIMIT 1',
            [':r' => $idExpediente, ':t' => self::TIPO_ACTUAL]
        );

        if ($id !== null) { return (int) $id; }
        if (!$crear)      { return null; }

        /*
           La carrera entre el SELECT y el INSERT se resuelve mirando
           otra vez.

           Dos pestanias abiertas marcando dientes a la vez pueden
           llegar las dos aqui: las dos consultan, las dos no encuentran
           y las dos insertan. La llave unica uq_odo_actual impide que
           el segundo entre —ver la migracion 0017— y lo que hay que
           hacer con ese choque es volver a consultar, no reventar: el
           odontograma que hace falta ya existe, lo acaba de crear la
           otra pestania.

           Sin esto, el aviso al usuario seria un error de servidor en la
           primera marca de dos dientes seguidos.
        */
        try {
            Conexion::consulta(
                'INSERT INTO odontograma (id_registro_paciente, fecha, tipo)
                      VALUES (:r, :f, :t)',
                [':r' => $idExpediente, ':f' => date('Y-m-d'),
                 ':t' => self::TIPO_ACTUAL]
            );

            return Conexion::ultimoId();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $id = Conexion::valor(
                'SELECT id_odontograma FROM odontograma
                  WHERE id_registro_paciente = :r AND tipo = :t
                  ORDER BY id_odontograma LIMIT 1',
                [':r' => $idExpediente, ':t' => self::TIPO_ACTUAL]
            );

            if ($id === null) {
                // La llave se quejo y no hay nada: no es la carrera, es
                // otra cosa, y callarla dejaria un odontograma fantasma.
                throw $e;
            }
            return (int) $id;
        }
    }

    /**
     * El estado de cada diente del paciente.
     *
     * @return array id_diente => ['id_condicion','nombre_condicion','color','nota']
     */
    public static function estadoDelPaciente(int $idExpediente): array
    {
        $id = self::idDelPaciente($idExpediente, false);
        if ($id === null) { return []; }

        $filas = Conexion::todos(
            'SELECT d.id_diente, d.id_condicion, d.nota,
                    c.nombre_condicion, c.color
               FROM odontograma_detalle d
               JOIN condicion_dental c ON c.id_condicion = d.id_condicion
              WHERE d.id_odontograma = :o
              ORDER BY d.id_diente',
            [':o' => $id]
        );

        $estado = [];
        foreach ($filas as $f) {
            $estado[(int) $f['id_diente']] = [
                'id_condicion'     => (int) $f['id_condicion'],
                'nombre_condicion' => $f['nombre_condicion'],
                'color'            => $f['color'] ?? '#999999',
                'nota'             => $f['nota'],
            ];
        }
        return $estado;
    }

    /**
     * Pone una condicion a varios dientes de golpe.
     *
     * Con $idCondicion = null se BORRA el estado de esos dientes, que no
     * es lo mismo que ponerles "sano": borrar deja el diente sin nada
     * dicho, y "sano" es una afirmacion de la doctora. Las dos cosas
     * hacen falta —corregir un error y declarar que esta bien— y
     * confundirlas convertiria el odontograma en algo que no se puede
     * deshacer.
     *
     * @param int[] $dientes notacion FDI
     * @return int cuantos dientes quedaron tocados
     */
    public static function marcar(
        int $idExpediente, array $dientes, ?int $idCondicion, ?string $nota = null
    ): int {
        $dientes = array_values(array_unique(array_map('intval', $dientes)));
        if (!$dientes) { return 0; }

        // Los numeros se comprueban contra el catalogo: lo que llega por
        // POST puede ser cualquier cosa, y un id_diente inventado haria
        // reventar la llave foranea con un error de servidor en vez de
        // un aviso.
        $validos = array_keys(self::nombres());
        $dientes = array_values(array_intersect($dientes, $validos));
        if (!$dientes) { return 0; }

        $idOdo = self::idDelPaciente($idExpediente);

        $pdo = bd();
        $propia = !$pdo->inTransaction();
        if ($propia) { $pdo->beginTransaction(); }

        try {
            /*
               Se borra y se vuelve a insertar, en una transaccion.

               Un UPDATE ... ON DUPLICATE KEY no sirve: la llave unica
               tendria que ser (odontograma, diente, cara) y cara es
               NULL cuando la condicion es de todo el diente. En MySQL
               dos NULL son distintos entre si, asi que una llave unica
               con esa columna no impide el duplicado —y acabariamos con
               el mismo diente dos veces y dos colores para el—.

               Borrar primero lo cierra sin depender de eso. La
               transaccion es lo que impide quedarse a medias: sin ella,
               un error entre el DELETE y el INSERT dejaria el diente
               sin estado y sin aviso.
            */
            Conexion::consulta(
                'DELETE FROM odontograma_detalle
                  WHERE id_odontograma = :o
                    AND id_diente IN (' . implode(',', $dientes) . ')',
                [':o' => $idOdo]
            );

            if ($idCondicion !== null) {
                foreach ($dientes as $n) {
                    Conexion::consulta(
                        'INSERT INTO odontograma_detalle
                            (id_odontograma, id_diente, cara, id_condicion, nota)
                         VALUES (:o, :d, NULL, :c, :n)',
                        [':o' => $idOdo, ':d' => $n,
                         ':c' => $idCondicion, ':n' => $nota]
                    );
                }
            }

            // La fecha del odontograma es la del ultimo cambio: es lo
            // que contesta "¿cuando se reviso esta boca?".
            Conexion::consulta(
                'UPDATE odontograma SET fecha = :f WHERE id_odontograma = :o',
                [':f' => date('Y-m-d'), ':o' => $idOdo]
            );

            if ($propia) { $pdo->commit(); }
        } catch (Throwable $e) {
            if ($propia && $pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }

        return count($dientes);
    }
}
