<?php
declare(strict_types=1);

/**
 * Campos de la ficha del paciente: dato plano con su lapicito.
 *
 * El formulario clasico muestra treinta cajas de texto todas iguales y
 * todas vacias de contenido visual. Pero el 95% de las veces nadie
 * entra a esa pantalla a EDITAR: entra a LEER —"¿cual es el telefono?",
 * "¿cuantos anios tiene?"— y las cajas de captura estorban para eso.
 * Un dato plano se lee de un vistazo; una caja de texto hay que
 * mirarla por dentro.
 *
 * Aqui el dato se ve como texto. El lapiz aparece al pasar el raton
 * encima y, al picarlo, SOLO ese campo se vuelve editable. Lo demas
 * sigue siendo texto.
 *
 * Por que uno por uno y no una seccion entera: porque lo que se corrige
 * es un dato suelto —el telefono que cambio, el apellido mal escrito—
 * y abrir doce campos para tocar uno obliga a revisar los once que no
 * querias mover.
 *
 * El guardado reutiliza el formulario y el endpoint que ya existian: al
 * aceptar un campo se envia la seccion completa, como siempre. Nada de
 * esto cambia la validacion ni la bitacora.
 */
final class Ficha
{
    /**
     * Un campo de texto.
     *
     * @param string $nombre     name del input, el que espera el endpoint
     * @param string $etiqueta   lo que lee la persona
     * @param array  $opciones   vacio    = texto libre
     *                           con pares = lista desplegable
     */
    public static function campo(
        string  $nombre,
        string  $etiqueta,
        ?string $valor,
        array   $opciones = []
    ): string {
        $tipo      = $opciones['tipo']      ?? 'text';
        $lista     = $opciones['lista']     ?? null;
        $editable  = $opciones['editable']  ?? true;
        $requerido = $opciones['requerido'] ?? false;
        $maximo    = $opciones['maxlength'] ?? 120;
        $vacio     = $opciones['vacio']     ?? 'Sin capturar';
        $ancho     = $opciones['ancho']     ?? 6;

        // Lo que se MUESTRA no siempre es lo que se GUARDA: en una
        // lista se guarda el id y se ensenia el nombre; en una fecha se
        // guarda ISO y se ensenia dd/mm/aaaa.
        $mostrado = $opciones['mostrado'] ?? $valor;

        $html = '<div class="col-' . (int) $ancho . '">';
        $html .= '<div class="campo-ficha' . ($editable ? '' : ' fijo') . '"'
               . ' data-campo="' . e($nombre) . '">';

        $html .= '<span class="cf-etiqueta">' . $etiqueta
               . ($requerido ? ' <b class="req">*</b>' : '') . '</span>';

        $html .= '<div class="cf-fila">';

        // ---- Lectura ----
        $hayValor = $mostrado !== null && $mostrado !== '';
        $html .= '<span class="cf-valor' . ($hayValor ? '' : ' vacio') . '">'
               . ($hayValor ? e((string) $mostrado) : e($vacio))
               . '</span>';

        if ($editable) {
            $html .= '<button type="button" class="cf-lapiz" title="Modificar '
                   . strip_tags($etiqueta) . '">'
                   . '<i class="bi bi-pencil"></i></button>';
        }
        $html .= '</div>';

        // ---- Edicion ----
        // Se dibuja de una vez y queda escondido. Pedirlo al servidor al
        // picar el lapiz agregaria una espera a algo que tiene que
        // sentirse inmediato.
        if ($editable) {
            $html .= '<div class="cf-editor d-none">';

            if ($lista !== null) {
                $html .= '<select class="form-select form-select-sm" name="'
                       . e($nombre) . '">';
                foreach ($lista as $v => $t) {
                    $sel = (string) $v === (string) $valor ? ' selected' : '';
                    $html .= '<option value="' . e((string) $v) . '"' . $sel . '>'
                           . e((string) $t) . '</option>';
                }
                $html .= '</select>';
            } else {
                $html .= '<input type="' . e($tipo) . '"'
                       . ' class="form-control form-control-sm"'
                       . ' name="' . e($nombre) . '"'
                       . ' value="' . e((string) ($valor ?? '')) . '"'
                       . ' maxlength="' . (int) $maximo . '"'
                       . ($requerido ? ' required' : '')
                       . ($tipo === 'date' ? ' max="' . date('Y-m-d') . '"' : '')
                       . '>';
            }

            $html .= '<div class="cf-botones">'
                   . '<button type="button" class="cf-ok" title="Guardar">'
                   . '<i class="bi bi-check-lg"></i></button>'
                   . '<button type="button" class="cf-cancelar" title="Cancelar">'
                   . '<i class="bi bi-x-lg"></i></button>'
                   . '</div>';
            $html .= '</div>';
        } else {
            // Un campo no editable igual necesita mandar su valor: el
            // endpoint recibe la seccion completa y si falta lo
            // interpretaria como que se borro.
            $html .= '<input type="hidden" name="' . e($nombre) . '"'
                   . ' value="' . e((string) ($valor ?? '')) . '">';
        }

        $html .= '</div></div>';
        return $html;
    }

    /** Titulo de una seccion dentro de la ficha. */
    public static function seccion(string $titulo, string $icono = ''): string
    {
        return '<div class="col-12"><div class="ficha-seccion">'
             . ($icono ? '<i class="bi ' . e($icono) . '"></i>' : '')
             . '<span>' . $titulo . '</span>'
             . '</div></div>';
    }
}
