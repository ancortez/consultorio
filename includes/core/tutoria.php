<?php
/**
 * Quien responde por el paciente: su tutor o el mismo.
 *
 * Nanai nacio para un consultorio de odontopediatria y daba por hecho
 * que todo paciente es un menor con tutor. Atender adultos obliga a
 * poder decir lo contrario, y el caso interesante no es el adulto que
 * llega de nuevo —ese se pregunta al darlo de alta— sino el niño que
 * lleva anios viniendo y un dia cumple dieciocho.
 *
 * Las reglas, enunciadas como las dijo el consultorio:
 *
 *   - Menor de 18: lleva tutor. No se pregunta nada; no hay nada que
 *     decidir, lo dice la ley.
 *
 *   - Cumple 18 y nadie ha dicho nada: se pregunta una vez. Puede
 *     seguir con tutor (vive con sus padres, o hay una discapacidad de
 *     por medio) o pasar a responder por si mismo.
 *
 *   - Marcado con tutor y llega a 24: se vuelve a preguntar. Seis anios
 *     despues la situacion casi siempre cambio, y un expediente que
 *     sigue exigiendo la firma de la mama de alguien de treinta es un
 *     estorbo en el mostrador.
 *
 *   - Ya decidido que responde por si mismo: no se vuelve a preguntar.
 *     Esa decision no caduca.
 *
 * Todo vive aqui y no repartido entre la vista y el guardado porque es
 * UNA regla. Repartida, la pantalla y el endpoint acabarian discrepando
 * sobre si toca preguntar, y el sintoma seria un aviso que sale y no se
 * puede quitar.
 */
final class Tutoria
{
    /** Antes de esta edad la ley decide por nosotros. */
    public const MAYORIA_EDAD = 18;

    /** A esta edad se revisa de nuevo a quien sigue marcado con tutor. */
    public const EDAD_REVISION = 24;

    /**
     * ¿Hay que preguntarle al usuario que hacer con este expediente?
     *
     * @param int|null $edad            null si no hay fecha de nacimiento
     * @param int|null $requiereTutor   NULL sin decidir | 1 si | 0 no
     * @param int|null $decididoEdad    edad a la que se decidio
     */
    public static function hayQuePreguntar(
        ?int $edad, ?int $requiereTutor, ?int $decididoEdad): bool
    {
        // Sin fecha de nacimiento no se puede saber la edad, y preguntar
        // "¿lleva tutor?" sobre alguien de edad desconocida es pedirle
        // al usuario que adivine. Primero que capturen la fecha.
        if ($edad === null) {
            return false;
        }

        // Menor: no hay nada que preguntar.
        if ($edad < self::MAYORIA_EDAD) {
            return false;
        }

        // Mayor de edad y nadie lo ha decidido todavia.
        if ($requiereTutor === null) {
            return true;
        }

        // Ya dijo que responde por si mismo: esa puerta no se vuelve a
        // tocar. Si cambia de situacion, se cambia a mano en su ficha.
        if ((int) $requiereTutor === 0) {
            return false;
        }

        // Sigue con tutor. Se revisa al llegar a la edad de revision, y
        // solo si la decision se tomo ANTES de esa edad: de lo contrario
        // se preguntaria en cada visita a partir de los 24.
        //
        // Un NULL en la edad de la decision se trata como "se decidio
        // hace mucho". Es el caso de los expedientes que ya existian
        // cuando se agrego esta columna: de ellos no sabemos cuando se
        // decidio, y preguntar una vez es mejor que no revisar nunca.
        if ($edad >= self::EDAD_REVISION) {
            return $decididoEdad === null || $decididoEdad < self::EDAD_REVISION;
        }

        return false;
    }

    /**
     * ¿Este expediente lleva tutor, aqui y ahora?
     *
     * Lo usan la ficha —para ensenniar o esconder el bloque— y los
     * documentos impresos —para saber quien firma—. Es deliberadamente
     * distinta de hayQuePreguntar(): mientras nadie conteste, un mayor
     * de edad sigue mostrando a su tutor, porque esconderlo antes de
     * que alguien decida seria borrar de la vista un dato capturado.
     */
    public static function llevaTutor(?int $edad, ?int $requiereTutor): bool
    {
        if ($requiereTutor !== null) {
            return (int) $requiereTutor === 1;
        }

        // Sin decidir: los menores si, y los mayores tambien hasta que
        // alguien conteste. Es lo que habia antes de esta columna y no
        // conviene cambiarlo por omision.
        return true;
    }

    /**
     * Guarda la decision junto con la edad a la que se tomo.
     *
     * Las dos cosas en la misma llamada a proposito: guardar una sin la
     * otra deja el expediente en un estado que la regla no sabe leer
     * —decidido pero sin saber cuando— y eso se traduce en preguntar de
     * mas o de menos.
     */
    public static function decidir(
        int $idExpediente, bool $requiereTutor, ?int $edad): void
    {
        Conexion::consulta(
            'UPDATE registro_paciente
                SET requiere_tutor = :r, tutor_decidido_edad = :e
              WHERE id_registro_paciente = :id',
            [':r' => $requiereTutor ? 1 : 0, ':e' => $edad, ':id' => $idExpediente]
        );

        Bitacora::registrar('paciente', 'CAMBIO', 'registro_paciente',
            (string) $idExpediente,
            ['requiere_tutor' => $requiereTutor ? 1 : 0, 'edad' => $edad]);
    }
}

/**
 * Las alertas clinicas que salen de la historia.
 *
 * Hasta ahora las alertas venian de expediente_antecedente, una tabla
 * que se lleno con los datos de prueba y que nadie captura en el uso
 * real. El resultado: el aviso salia en los pacientes inventados y no
 * salia en los de verdad, que es exactamente al reves de lo que sirve.
 *
 * Las de verdad estan en la historia clinica, en cuatro preguntas que
 * la doctora si contesta:
 *
 *   enfermedad_cronica    diabetes, cardiopatia, epilepsia...
 *   discapacidad          cambia como se maneja la cita entera
 *   alergia               la mas grave: hay anestesia de por medio
 *   tratamiento_medico    puede interactuar con lo que se recete
 *
 * Vive junto a Tutoria y no en su propio archivo porque las dos
 * contestan la misma clase de pregunta —que hay que saber de este
 * paciente antes de atenderlo— y separarlas serian dos archivos de
 * treinta lineas que siempre se leen juntos.
 */
final class AlertasClinicas
{
    /**
     * Las alertas de un expediente, listas para pintar.
     *
     * @return list<array{clave:string, titulo:string, detalle:string}>
     */
    public static function de(int $idExpediente): array
    {
        $h = Conexion::uno(
            // Sin "AND estatus = 1" ni "ORDER BY id_historia": me
            // invente las dos. historia_clinica no tiene columna de
            // estatus —una historia clinica no se da de baja, se
            // corrige— y su llave se llama id_historia_clinica.
            'SELECT enfermedad_cronica, enfermedad_detalle,
                    discapacidad, discapacidad_detalle,
                    alergia, alergia_detalle,
                    tratamiento_medico, tratamiento_detalle
               FROM historia_clinica
              WHERE id_registro_paciente = :r
              ORDER BY id_historia_clinica DESC
              LIMIT 1',
            [':r' => $idExpediente]
        );

        if ($h === null) {
            return [];
        }

        $mapa = [
            ['alergia',            'alergia_detalle',      'Alergias'],
            ['enfermedad_cronica', 'enfermedad_detalle',   'Enfermedad cronica'],
            ['discapacidad',       'discapacidad_detalle', 'Discapacidad'],
            ['tratamiento_medico', 'tratamiento_detalle',  'Tratamiento medico'],
        ];

        $alertas = [];
        foreach ($mapa as [$campo, $detalle, $titulo]) {
            // Solo el SI. Un NULL es "no se pregunto" y un 0 es "no", y
            // ninguno de los dos es una alerta: avisar por ellos
            // ensenniaria a la doctora a ignorar el aviso.
            if ((int) ($h[$campo] ?? 0) !== 1) {
                continue;
            }

            $alertas[] = [
                'clave'   => $campo,
                'titulo'  => $titulo,
                // Sin detalle capturado se avisa igual: que haya una
                // alergia importa aunque nadie haya escrito a que.
                'detalle' => trim((string) ($h[$detalle] ?? '')),
            ];
        }

        // Las alergias primero: es la que puede acabar en urgencia.
        return $alertas;
    }

    /** La discapacidad, si la hay, para la tarjeta del paciente. */
    public static function discapacidad(int $idExpediente): ?string
    {
        foreach (self::de($idExpediente) as $a) {
            if ($a['clave'] === 'discapacidad') {
                return $a['detalle'] !== '' ? $a['detalle'] : 'Sin especificar';
            }
        }
        return null;
    }
}
