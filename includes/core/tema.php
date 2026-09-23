<?php
declare(strict_types=1);

/**
 * Color general del sistema.
 *
 * Toda la hoja de estilo esta escrita sobre variables CSS
 * (--morado-700, --rosa-400...). Cambiar el color del sistema es
 * reescribir esas variables, no tocar los 40 archivos que las usan.
 *
 * Por eso aqui no se guardan hojas de estilo alternas: se guarda la
 * paleta, y el layout la imprime como un bloque :root que pisa al de
 * estilo.css. Un tema nuevo son doce lineas en este arreglo.
 *
 * Los nombres de las variables conservan la palabra "morado" aunque el
 * tema sea verde. Renombrarlas obligaria a tocar toda la hoja de
 * estilo cada vez, y lo que importa es el papel que cumple cada tono
 * (el 700 es el color fuerte de la marca), no como se llama.
 */
final class Tema
{
    public const CLAVE_PARAMETRO = 'tema_color';
    public const POR_DEFECTO     = 'morado';

    /**
     * Cada tema define nueve tonos que van de lo mas oscuro a lo mas
     * claro, mas un acento. La escala tiene que ser pareja: si el 100
     * no es claro de verdad, los badges quedan ilegibles.
     */
    private const TEMAS = [
        'morado' => [
            'nombre' => 'Morado y rosa',
            'nota'   => 'El color original del consultorio',
            'c900' => '#4a1d5c', 'c800' => '#5f2472', 'c700' => '#7b2d8e',
            'c600' => '#9333a8', 'c500' => '#a94bbd', 'c400' => '#c46fd4',
            'a400' => '#d96bb0', 'a300' => '#e89ac9', 'a100' => '#f7e6f2',
            'f50'  => '#faf5fc',
        ],
        'menta' => [
            'nombre' => 'Menta y agua',
            'nota'   => 'Verde suave, descansado a la vista',
            'c900' => '#0f4339', 'c800' => '#155e4e', 'c700' => '#1c7a63',
            'c600' => '#239478', 'c500' => '#39ae90', 'c400' => '#63c7ab',
            'a400' => '#43b0c2', 'a300' => '#8ad3de', 'a100' => '#e2f5f1',
            'f50'  => '#f4fbf9',
        ],
        'cielo' => [
            'nombre' => 'Cielo y turquesa',
            'nota'   => 'Azul claro, el mas neutro de todos',
            'c900' => '#123a63', 'c800' => '#17507f', 'c700' => '#1c689e',
            'c600' => '#2280bb', 'c500' => '#3f9cd2', 'c400' => '#6bb8e2',
            'a400' => '#3fbccc', 'a300' => '#8ad9e3', 'a100' => '#e3f2fa',
            'f50'  => '#f5fafd',
        ],
        'durazno' => [
            'nombre' => 'Durazno y coral',
            'nota'   => 'Calido, buena opcion para ninos',
            'c900' => '#6b2b1c', 'c800' => '#8c3a23', 'c700' => '#b04b2c',
            'c600' => '#cc5f38', 'c500' => '#e07a52', 'c400' => '#ee9b78',
            'a400' => '#e8746b', 'a300' => '#f4a9a2', 'a100' => '#fdeae4',
            'f50'  => '#fef8f5',
        ],
        'ciruela' => [
            'nombre' => 'Ciruela y vino',
            'nota'   => 'Mas serio y con mas contraste',
            'c900' => '#3d1028', 'c800' => '#571a39', 'c700' => '#75244d',
            'c600' => '#933061', 'c500' => '#b04a7c', 'c400' => '#c8749b',
            'a400' => '#c75b6e', 'a300' => '#e09aa8', 'a100' => '#f9e6ec',
            'f50'  => '#fdf6f8',
        ],
        'bosque' => [
            'nombre' => 'Bosque y olivo',
            'nota'   => 'Verde profundo, poco brillo',
            'c900' => '#1d3320', 'c800' => '#2a482d', 'c700' => '#3a6140',
            'c600' => '#4c7c53', 'c500' => '#659a6d', 'c400' => '#8bb891',
            'a400' => '#8a9e4b', 'a300' => '#c0cf8f', 'a100' => '#eaf2e6',
            'f50'  => '#f7faf5',
        ],
    ];

    /** @return array<string,array<string,string>> */
    public static function todos(): array
    {
        return self::TEMAS;
    }

    public static function existe(string $clave): bool
    {
        return isset(self::TEMAS[$clave]);
    }

    /** Tema activo, cayendo al de fabrica si el guardado ya no existe. */
    public static function actual(): string
    {
        $clave = (string) parametro(self::CLAVE_PARAMETRO, self::POR_DEFECTO);
        return self::existe($clave) ? $clave : self::POR_DEFECTO;
    }

    /**
     * Bloque <style> con la paleta del tema activo.
     *
     * Se imprime en el head, despues de estilo.css, para que gane por
     * orden sin necesidad de !important. El tema de fabrica no imprime
     * nada: ya esta en la hoja.
     */
    public static function estilos(?string $clave = null): string
    {
        $clave = $clave ?? self::actual();

        if ($clave === self::POR_DEFECTO || !self::existe($clave)) {
            return '';
        }

        $t = self::TEMAS[$clave];

        return sprintf(
            '<style id="tema-color">:root{'
            . '--morado-900:%s;--morado-800:%s;--morado-700:%s;'
            . '--morado-600:%s;--morado-500:%s;--morado-400:%s;'
            . '--rosa-400:%s;--rosa-300:%s;--rosa-100:%s;--lila-50:%s;'
            . '--sombra:0 2px 10px %s1f;--sombra-alta:0 6px 24px %s2e;'
            . '}</style>',
            $t['c900'], $t['c800'], $t['c700'],
            $t['c600'], $t['c500'], $t['c400'],
            $t['a400'], $t['a300'], $t['a100'], $t['f50'],
            $t['c900'], $t['c900']
        );
    }
}
