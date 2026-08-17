<?php
declare(strict_types=1);

/**
 * Recorte de la fotografia del paciente.
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
