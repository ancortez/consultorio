<?php
declare(strict_types=1);

/**
 * Autenticacion y control de permisos.
 *
 * MEJORAS respecto al login descrito en la composicion:
 *  - Las contrasenas se guardan con password_hash() (bcrypt), nunca en claro.
 *  - El mensaje de error es generico: no revela si el usuario existe o no.
 *    Decir "el usuario no existe" le regala al atacante media credencial.
 *  - Se limitan los intentos fallidos y se bloquea la cuenta temporalmente.
 *  - Se regenera el id de sesion al entrar (evita fijacion de sesion).
 */
final class Auth
{
    private const MSG_GENERICO = 'Usuario o contrasena incorrectos.';

    /**
     * @return array{ok:bool, mensaje:string, usuario?:array}
     */
    public static function intentarLogin(string $usuario, string $contrasena): array
    {
        // El usuario se captura en MAYUSCULAS pero se guarda en
        // minusculas. La comparacion se hace sobre el valor normalizado
        // para que "ADMIN", "Admin" y "admin" sean el mismo usuario.
        $u = Conexion::uno(
            'SELECT u.*, r.nombre_rol
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.nombre_usuario = :u
              LIMIT 1',
            [':u' => mb_strtolower(trim($usuario), 'UTF-8')]
        );

        // Usuario inexistente: se compara igual contra un hash falso para
        // que el tiempo de respuesta no delate la ausencia del usuario.
        if ($u === null) {
            password_verify($contrasena, '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin');
            self::registrarIntento(null, $usuario, 'Usuario inexistente');
            return ['ok' => false, 'mensaje' => self::MSG_GENERICO];
        }

        if ((int) $u['estatus'] !== 1) {
            self::registrarIntento((int) $u['id_usuario'], $usuario, 'Cuenta inactiva');
            return ['ok' => false, 'mensaje' => 'La cuenta esta inactiva. Contacta al administrador.'];
        }

        if (!empty($u['bloqueado_hasta']) && strtotime($u['bloqueado_hasta']) > time()) {
            $min = (int) ceil((strtotime($u['bloqueado_hasta']) - time()) / 60);
            return ['ok' => false, 'mensaje' => "Cuenta bloqueada. Intenta de nuevo en $min minuto(s)."];
        }

        if (!password_verify($contrasena, $u['contrasena'])) {
            self::sumarIntentoFallido($u);
            self::registrarIntento((int) $u['id_usuario'], $usuario, 'Contrasena incorrecta');
            return ['ok' => false, 'mensaje' => self::MSG_GENERICO];
        }

        // Rehash automatico si el costo del algoritmo cambio.
        if (password_needs_rehash($u['contrasena'], PASSWORD_DEFAULT)) {
            Conexion::consulta(
                'UPDATE usuario SET contrasena = :c WHERE id_usuario = :id',
                [':c' => password_hash($contrasena, PASSWORD_DEFAULT), ':id' => $u['id_usuario']]
            );
        }

        Conexion::consulta(
            'UPDATE usuario
                SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW()
              WHERE id_usuario = :id',
            [':id' => $u['id_usuario']]
        );

        Sesion::regenerar();
        Sesion::set('usuario', [
            'id_usuario'      => (int) $u['id_usuario'],
            'nombre_usuario'  => $u['nombre_usuario'],
            'nombre_completo' => $u['nombre_completo'],
            'id_rol'          => (int) $u['id_rol'],
            'nombre_rol'      => $u['nombre_rol'],
            'es_dentista'     => (int) $u['es_dentista'] === 1,
            'requiere_cambio_pass' => (int) $u['requiere_cambio_pass'] === 1,
        ]);
        Sesion::set('permisos', self::cargarPermisos((int) $u['id_rol']));

        Bitacora::registrar('seguridad', 'LOGIN', 'usuario', (string) $u['id_usuario']);

        return ['ok' => true, 'mensaje' => 'Bienvenido.', 'usuario' => Sesion::get('usuario')];
    }

    private static function sumarIntentoFallido(array $u): void
    {
        $intentos = (int) $u['intentos_fallidos'] + 1;
        $max      = (int) parametro('max_intentos_login', (string) MAX_INTENTOS_LOGIN);
        $minutos  = (int) parametro('minutos_bloqueo', (string) MINUTOS_BLOQUEO);

        if ($intentos >= $max) {
            Conexion::consulta(
                'UPDATE usuario
                    SET intentos_fallidos = :i,
                        bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :m MINUTE)
                  WHERE id_usuario = :id',
                [':i' => $intentos, ':m' => $minutos, ':id' => $u['id_usuario']]
            );
        } else {
            Conexion::consulta(
                'UPDATE usuario SET intentos_fallidos = :i WHERE id_usuario = :id',
                [':i' => $intentos, ':id' => $u['id_usuario']]
            );
        }
    }

    private static function registrarIntento(?int $idUsuario, string $usuario, string $motivo): void
    {
        Bitacora::registrar('seguridad', 'LOGIN_FALLIDO', 'usuario',
            $idUsuario !== null ? (string) $idUsuario : null,
            ['usuario_capturado' => $usuario, 'motivo' => $motivo],
            $idUsuario);
    }

    public static function cargarPermisos(int $idRol): array
    {
        $filas = Conexion::todos(
            'SELECT p.clave
               FROM rol_permiso rp
               JOIN permiso p ON p.id_permiso = rp.id_permiso
              WHERE rp.id_rol = :r',
            [':r' => $idRol]
        );
        return array_column($filas, 'clave');
    }

    public static function usuario(): ?array
    {
        return Sesion::get('usuario');
    }

    public static function id(): ?int
    {
        return Sesion::get('usuario')['id_usuario'] ?? null;
    }

    public static function autenticado(): bool
    {
        return Sesion::get('usuario') !== null;
    }

    public static function puede(string $permiso): bool
    {
        $u = self::usuario();
        if ($u === null) {
            return false;
        }
        if ((int) $u['id_rol'] === 1) {   // Administrador
            return true;
        }
        return in_array($permiso, Sesion::get('permisos', []), true);
    }

    /** Corta la ejecucion si no hay sesion iniciada. */
    public static function exigirLogin(): void
    {
        if (self::autenticado()) {
            return;
        }
        if (esAjax()) {
            Respuesta::error('Tu sesion expiro. Vuelve a iniciar sesion.', 401);
        }
        header('Location: ' . URL_BASE . '/login.php?expiro=1');
        exit;
    }

    /** Corta la ejecucion si el usuario no tiene el permiso. */
    public static function exigirPermiso(string $permiso): void
    {
        self::exigirLogin();
        if (self::puede($permiso)) {
            return;
        }
        if (esAjax()) {
            Respuesta::error('No tienes permiso para realizar esta accion.', 403);
        }
        http_response_code(403);
        exit('403 - Acceso denegado.');
    }

    public static function cerrarSesion(): void
    {
        if (self::autenticado()) {
            Bitacora::registrar('seguridad', 'LOGOUT', 'usuario', (string) self::id());
        }
        Sesion::destruir();
    }
}
