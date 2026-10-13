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
 * Lo que NO se rellena nunca
 * --------------------------
 * Si la respuesta SI pedia complemento y esta vacio, no se inventa
 * nada. Ahi el blanco es correcto, porque de verdad falta el dato: un
 * "alergico: SI" sin decir a que es un hueco en el expediente, y
 * taparlo con "NINGUNA" lo convertiria en una contradiccion firmada.
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
     *   - cadena vacia si la respuesta SI pedia complemento y falta
     *
     * Ese ultimo caso se deja vacio a proposito. Ver el comentario de
     * la clase: rellenarlo convertiria un hueco en una contradiccion.
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
            ? ''
            : $regla['relleno'];
    }

    /**
     * Las respuestas que estan a medias: piden complemento y no lo
     * tienen.
     *
     * Pasa sobre todo con el prellenado, que contesta varias preguntas
     * de golpe sin poder saber el detalle. Lo usa la pantalla para
     * marcarlas en amarillo y el guardado para no dejar avanzar: un
     * "si" sin su detalle es un dato que PARECE capturado y no lo esta,
     * que es peor que uno vacio.
     *
     * @return string[] los nombres de los campos de complemento
     */
    public static function complementosQueFaltan(array $hc): array
    {
        $faltan = [];

        foreach (self::COMPLEMENTOS as $campo => $regla) {
            $respuesta = $hc[$regla['depende']] ?? null;
            if (!self::pideComplemento($regla['cuando'], $respuesta === null
                    ? null : (string) $respuesta)) {
                continue;
            }
            if (trim((string) ($hc[$campo] ?? '')) === '') {
                $faltan[] = $campo;
            }
        }

        return $faltan;
    }
}
