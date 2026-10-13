<?php
/**
 * El ciclo de vida de un plan de tratamiento.
 *
 * Un plan pasa por cinco estados y hasta ahora cada pantalla decidia
 * por su cuenta cuando cambiarlo. El resultado era que marcar el avance
 * de un concepto podia SACAR a un plan de Terminado y devolverlo a En
 * proceso: un tratamiento cerrado hace meses volvia a aparecer como
 * activo porque alguien desmarco una casilla.
 *
 * Aqui vive la unica regla, y lo que la hace util es lo que PROHIBE:
 *
 *   Propuesto   se le propuso al paciente, todavia no acepta
 *   Aceptado    firmo la hoja; a partir de aqui se puede cobrar
 *   En proceso  ya se trabajo en el, al menos una consulta
 *   Terminado   FINAL. No se deshace.
 *   Cancelado   FINAL. No se deshace.
 *
 * Por que los finales son finales: de ellos cuelga dinero. Un plan
 * terminado tiene cargos cobrados, y uno cancelado dejo de generarlos.
 * Permitir volver atras significa que la misma consulta podria generar
 * cargos dos veces, o que un plan cobrado vuelva a cobrarse. Si de
 * verdad hubo un error, se hace un plan nuevo: el expediente tiene que
 * poder contar lo que paso, incluido el error.
 *
 * Cancelar, ademas, solo se permite ANTES de aceptar. Despues de la
 * firma hay un compromiso de por medio —y casi siempre dinero— y lo que
 * corresponde no es borrarlo de la vista sino terminarlo con lo que se
 * haya hecho.
 */
final class Planes
{
    public const PROPUESTO  = 1;
    public const ACEPTADO   = 2;
    public const EN_PROCESO = 3;
    public const TERMINADO  = 4;
    public const CANCELADO  = 5;

    /** De estos no se sale. */
    public const FINALES = [self::TERMINADO, self::CANCELADO];

    /** ¿Este plan ya esta cerrado? */
    public static function esFinal(int $estado): bool
    {
        return in_array($estado, self::FINALES, true);
    }

    /**
     * ¿Se puede cancelar?
     *
     * Solo mientras el paciente no haya aceptado. Despues hay firma de
     * por medio y lo que corresponde es terminarlo, no desaparecerlo.
     */
    public static function puedeCancelar(int $estado): bool
    {
        return $estado === self::PROPUESTO;
    }

    /**
     * ¿Se puede dar por terminado?
     *
     * Desde Aceptado o En proceso. Un plan apenas Propuesto no se
     * "termina": no empezo. Y uno ya cerrado no se vuelve a cerrar.
     */
    public static function puedeTerminar(int $estado): bool
    {
        return in_array($estado, [self::ACEPTADO, self::EN_PROCESO], true);
    }

    /**
     * ¿Se puede mover a este estado por el avance automatico?
     *
     * Lo usa el marcado de conceptos, que empuja el plan a En proceso o
     * a Terminado segun lo que quede pendiente. Nunca puede tocar un
     * plan ya cerrado: esa es la comprobacion que faltaba y por la que
     * un plan terminado podia volver a En proceso.
     */
    public static function puedeAvanzarAuto(int $estadoActual): bool
    {
        return !self::esFinal($estadoActual);
    }

    /** El estado de un plan, o null si no existe. */
    public static function estadoDe(int $idPlan): ?int
    {
        $e = Conexion::valor(
            'SELECT id_estado_plan FROM plan_tratamiento
              WHERE id_plan_tratamiento = :p',
            [':p' => $idPlan]
        );
        return $e === null ? null : (int) $e;
    }

    /**
     * Cancela un plan y limpia lo que deja colgando.
     *
     * @throws RuntimeException si el plan no esta en un estado que lo permita
     */
    public static function cancelar(int $idPlan, string $motivo): void
    {
        $estado = self::estadoDe($idPlan);

        if ($estado === null) {
            throw new RuntimeException('Ese plan no existe.');
        }
        if (self::esFinal($estado)) {
            throw new RuntimeException(
                'Ese plan ya esta cerrado y no se puede cambiar.');
        }
        if (!self::puedeCancelar($estado)) {
            throw new RuntimeException(
                'Este plan ya fue aceptado por el paciente. En vez de '
              . 'cancelarlo, dalo por terminado con lo que se haya hecho.');
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException(
                'Escribe por que se cancela. Dentro de seis meses, nadie '
              . 'se va a acordar.');
        }

        Conexion::consulta(
            'UPDATE plan_tratamiento
                SET id_estado_plan = :e,
                    motivo_cancelacion = :m,
                    fecha_cancelacion = NOW()
              WHERE id_plan_tratamiento = :p',
            [':e' => self::CANCELADO, ':m' => mb_substr(mayus($motivo), 0, 255),
             ':p' => $idPlan]
        );

        self::quitarPendientesDelPlan($idPlan);

        Bitacora::registrar('consultas', 'CANCELA', 'plan_tratamiento',
            (string) $idPlan, ['motivo' => $motivo]);
    }

    /**
     * Da el plan por terminado.
     *
     * Se permite aunque queden conceptos sin marcar: la realidad es que
     * muchos tratamientos se cierran con algo pendiente —el paciente
     * dejo de venir, se cambio de ciudad— y obligar a marcar uno por uno
     * lo que no se hizo lleva a marcarlos como hechos, que es peor.
     * Quien cierra se entera de cuantos quedan antes de confirmar.
     */
    public static function terminar(int $idPlan): void
    {
        $estado = self::estadoDe($idPlan);

        if ($estado === null) {
            throw new RuntimeException('Ese plan no existe.');
        }
        if (self::esFinal($estado)) {
            throw new RuntimeException(
                'Ese plan ya esta cerrado y no se puede cambiar.');
        }
        if (!self::puedeTerminar($estado)) {
            throw new RuntimeException(
                'Este plan todavia no lo acepta el paciente. No se puede '
              . 'dar por terminado algo que no empezo.');
        }

        Conexion::consulta(
            'UPDATE plan_tratamiento
                SET id_estado_plan = :e, fecha_terminado = NOW()
              WHERE id_plan_tratamiento = :p',
            [':e' => self::TERMINADO, ':p' => $idPlan]
        );

        Bitacora::registrar('consultas', 'TERMINA', 'plan_tratamiento',
            (string) $idPlan);
    }

    /** Cuantos conceptos quedan sin hacer. */
    public static function conceptosPendientes(int $idPlan): int
    {
        return (int) Conexion::valor(
            'SELECT COUNT(*) FROM plan_tratamiento_detalle
              WHERE id_plan_tratamiento = :p AND estado_avance IN (0, 1)',
            [':p' => $idPlan]
        );
    }

    /**
     * Quita los pendientes de digitalizar de un plan cancelado.
     *
     * El consentimiento y la hoja del plan se piden al crearlo, para que
     * alguien se acuerde de recoger las firmas. Si el plan se cancela,
     * esos papeles ya no van a existir: dejar los pendientes ahi llena
     * el tablero de tareas imposibles, y la gente aprende a ignorar la
     * lista entera.
     *
     * Solo los que siguen SIN escanear. Si alguien ya subio el
     * consentimiento firmado antes de cancelar, ese documento se queda:
     * es un papel que existe y que el expediente tiene que conservar.
     */
    private static function quitarPendientesDelPlan(int $idPlan): void
    {
        Conexion::consulta(
            'UPDATE documento d
               JOIN tipo_documento td
                 ON td.id_tipo_documento = d.id_tipo_documento
                SET d.estatus = 0
              WHERE d.id_plan_tratamiento = :p
                AND d.estado = 0
                AND td.clave IN (:c1, :c2)',
            [':p' => $idPlan,
             ':c1' => 'CONSENTIMIENTO', ':c2' => 'PLAN_TRATAMIENTO']
        );
    }
}
