<?php
/**
 * Las doce preguntas de la historia clinica que abren otra.
 *
 * "¿Alergico?" no se contesta sola: si la respuesta es si, hace falta
 * saber a que. Doce preguntas son asi, y la regla de cada una —de que
 * respuesta cuelga su complemento— vivia escrita tres veces: en los
 * atributos data-depende del formulario, en el JavaScript que abre la
 * ventanita, y en ninguna parte del lado del servidor.
 *
 * Aqui esta una sola vez, y sirve para lo que el formulario no puede
 * hacer: decidir que se escribe en la hoja IMPRESA cuando el
 * complemento esta vacio.
 *
 * Por que eso importa
 * -------------------
 * La hoja firmada es el documento legal. Un renglon en blanco en una
 * hoja firmada no dice "no aplica": dice "no se pregunto", y seis meses
 * despues nadie puede distinguir una cosa de la otra. Peor aun, un
 * blanco se puede llenar a mano despues de la firma.
 *
 * Asi que cada pregunta trae el texto con el que se imprime su
 * complemento cuando la respuesta no pide ninguno: "NINGUNA", "N/A",
 * "PRIMERA VEZ". Los eligio el consultorio, uno por uno, porque no son
 * intercambiables: a "¿cirugias?" se le contesta NINGUNA y a "¿hasta
 * cuando tomo formula?" se le contesta N/A, que no es lo mismo.
 *
 * Lo que falta por contestar
 * --------------------------
 * Desde el 16 de octubre de 2026 los doce complementos son OPCIONALES.
 * Antes eran obligatorios: un "¿alergico? SI" sin decir a que no dejaba
 * guardar la historia.
 *
 * La doctora pidio quitar esa traba y la razon es de consultorio: el
 * tutor muchas veces no sabe el detalle en ese momento —"si tuvo
 * complicaciones en el parto, pero no me acuerdo de cuales"— y el
 * sistema lo dejaba elegir entre inventar un detalle o cambiar el "si"
 * por un "no". La segunda salida borra un antecedente clinico para
 * poder avanzar, y es la que uno elige con el paciente esperando.
 *
 * Asi que ahora falta un tercer texto: el de "esto si pedia detalle y
 * no se dio". Es SIN_ESPECIFICAR, y no se confunde con los rellenos de
 * arriba: "alergico: SI / NO ESPECIFICA" dice una cosa verdadera y
 * distinta de "alergico: NO / NINGUNA".
 *
 * Lo que no cambia es que el renglon nunca sale en blanco, que es la
 * razon de ser de este archivo. Lo que se GUARDA sigue siendo vacio
 * —para poder distinguir despues lo que no se pregunto de lo que se
 * contesto— y lo que se IMPRIME es siempre un texto.
 */
final class HistoriaClinica
{
    /**
     * Las doce, con su regla y su relleno.
     *
     *   depende  la pregunta de la que cuelga
     *   cuando   la respuesta que SI pide complemento. Un "!" adelante
     *            invierte la condicion, igual que en el formulario.
     *   relleno  lo que se imprime cuando la respuesta no pide nada
     */
    /**
     * Lo que se imprime cuando la respuesta SI pedia detalle y no se
     * dio ninguno.
     *
     * Tiene que decir lo mismo que la constante SIN_ESPECIFICAR del
     * historia.js: la pantalla y la hoja impresa no pueden contestar
     * distinto a la misma pregunta.
     */
    public const SIN_ESPECIFICAR = 'NO ESPECIFICA';

    public const COMPLEMENTOS = [
        'ultima_visita_dentista' => [
            'depende' => 'primera_visita_dentista',
            'cuando'  => '0',
            'relleno' => 'PRIMERA VEZ',
        ],
        'complicaciones_detalle' => [
            'depende' => 'complicaciones_parto',
            'cuando'  => '1',
            'relleno' => 'NINGUNA',
        ],
        'lactancia_hasta' => [
            'depende' => 'lactancia_materna',
            'cuando'  => '1',
            'relleno' => 'N/A',
        ],
        'formula_hasta' => [
            'depende' => 'formula',
            'cuando'  => '1',
            'relleno' => 'N/A',
        ],
        'dieta_porque' => [
            'depende' => 'dieta_completa',
            'cuando'  => '0',
            'relleno' => 'N/A',
        ],
        'alimentos_azucar' => [
            'depende' => 'momentos_azucar',
            'cuando'  => '!NO CONSUME',
            'relleno' => 'N/A',
        ],
        'enfermedad_detalle' => [
            'depende' => 'enfermedad_cronica',
            'cuando'  => '1',
            'relleno' => 'NINGUNA',
        ],
        'discapacidad_detalle' => [
            'depende' => 'discapacidad',
            'cuando'  => '1',
            'relleno' => 'NINGUNA',
        ],
        'alergia_detalle' => [
            'depende' => 'alergia',
            'cuando'  => '1',
            'relleno' => 'NINGUNA',
        ],
        'tratamiento_detalle' => [
            'depende' => 'tratamiento_medico',
            'cuando'  => '1',
            'relleno' => 'NINGUNO',
        ],
        'cirugias_detalle' => [
            'depende' => 'cirugias',
            'cuando'  => '1',
            'relleno' => 'NINGUNA',
        ],
        'otro_higiene_detalle' => [
            'depende' => 'otro_higiene',
            'cuando'  => '1',
            'relleno' => 'NINGUNO',
        ],
    ];

    /**
     * ¿Esta respuesta pide complemento?
     *
     * Mismo criterio que el formulario, incluido el "!" que invierte:
     * "¿que alimentos con azucar?" aparece con cualquier frecuencia
     * MENOS "no consume"... y no aparece si todavia no se contesta
     * nada, porque entonces no hay respuesta de la que colgar.
     */
    public static function pideComplemento(string $cuando, ?string $valor): bool
    {
        $valor = trim((string) $valor);

        if (str_starts_with($cuando, '!')) {
            return $valor !== '' && $valor !== substr($cuando, 1);
        }
        return $valor === $cuando;
    }

    /**
     * Lo que se imprime en el renglon del complemento.
     *
     * Devuelve:
     *   - lo capturado, si hay algo
     *   - el relleno ("NINGUNA", "N/A"...) si la respuesta no pedia nada
     *   - "NO ESPECIFICA" si la respuesta SI pedia detalle y falta
     *   - cadena vacia solo si la pregunta madre no se contesto
     *
     * El tercer caso devolvia cadena vacia hasta octubre de 2026, con
     * el argumento de que rellenarlo convertiria un hueco en una
     * contradiccion. Con "NINGUNA" habria sido cierto —"alergico: SI /
     * NINGUNA" se contradice solo— pero "NO ESPECIFICA" no se
     * contradice con nada: dice exactamente lo que paso.
     *
     * Y deja la hoja mejor que antes: un renglon en blanco en un
     * documento firmado no se lee como "no se especifico", se lee como
     * "no se pregunto", y se puede llenar a mano despues de la firma.
     */
    public static function complementoImpreso(array $hc, string $campo): string
    {
        $regla = self::COMPLEMENTOS[$campo] ?? null;
        if ($regla === null) {
            return trim((string) ($hc[$campo] ?? ''));
        }

        $capturado = trim((string) ($hc[$campo] ?? ''));
        if ($capturado !== '') {
            return $capturado;
        }

        // Sin respuesta en la pregunta madre no hay nada que rellenar:
        // la historia simplemente no se contesto ahi.
        $respuesta = $hc[$regla['depende']] ?? null;
        if ($respuesta === null || trim((string) $respuesta) === '') {
            return '';
        }

        return self::pideComplemento($regla['cuando'], (string) $respuesta)
            ? self::SIN_ESPECIFICAR
            : $regla['relleno'];
    }

    /*
       Aqui vivia complementosQueFaltan().

       Devolvia los complementos que pedian detalle y estaban vacios, y
       servia para no dejar guardar la historia con un "si" a medias.
       Esa regla se quito el 16 de octubre de 2026 —ver la cabecera de
       la clase— y con ella el metodo: dejarlo habria sido un metodo
       publico que nadie llama y que describe una regla que ya no
       existe, o sea una trampa para el que lo encuentre y lo use.

       Lo que marca ahora un complemento sin contestar es
       complementoImpreso(), que devuelve SIN_ESPECIFICAR.
    */
}
