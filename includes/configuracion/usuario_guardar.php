<?php
/**
 * Alta y modificacion de usuarios.
 *
 * La contrasena solo se toca al dar de alta o cuando explicitamente se
 * manda una nueva. Al editar, dejarla vacia conserva la que tenia: es lo
 * que espera quien solo queria corregir un nombre mal escrito.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.usuarios');

$id         = paramInt('id_usuario');
$usuario    = mb_strtolower(trim((string) param('nombre_usuario', '')), 'UTF-8');
$nombre     = nombreLimpio('nombre_completo');
$correo     = mb_strtolower(trim((string) param('correo', '')), 'UTF-8') ?: null;
$idRol      = paramInt('id_rol');
$cedula     = paramMayus('cedula_prof');
$esDentista = param('es_dentista') ? 1 : 0;
$estatus    = param('estatus') !== null ? (int) param('estatus') : 1;
$pass       = (string) param('contrasena', '');
$passConf   = (string) param('contrasena_confirmar', '');

$errores = [];

if ($usuario === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $usuario)) {
    $errores['nombre_usuario'] =
        'De 3 a 40 caracteres: letras, numeros, punto, guion o guion bajo.';
}
if ($nombre === null) {
    $errores['nombre_completo'] = 'Captura el nombre completo.';
}
if (!$idRol) {
    $errores['id_rol'] = 'Selecciona el rol.';
}
if ($correo !== null && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores['correo'] = 'El correo no es valido.';
}

// La contrasena es obligatoria al dar de alta, opcional al modificar.
if (!$id || $pass !== '') {
    if (mb_strlen($pass) < 8) {
        $errores['contrasena'] = 'Minimo 8 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $errores['contrasena'] = 'Debe combinar letras y numeros.';
    } elseif ($pass !== $passConf) {
        $errores['contrasena_confirmar'] = 'Las contrasenas no coinciden.';
    }
}

// El nombre de usuario no se puede repetir.
$repetido = Conexion::valor(
    'SELECT COUNT(*) FROM usuario WHERE nombre_usuario = :u AND id_usuario <> :id',
    [':u' => $usuario, ':id' => $id ?? 0]
);
if ((int) $repetido > 0) {
    $errores['nombre_usuario'] = 'Ese nombre de usuario ya existe.';
}

if ($errores) {
    Respuesta::validacion($errores);
}

// Nadie puede quitarse a si mismo el acceso ni bajarse de rol: es la
// forma mas comun de quedarse fuera del sistema sin poder volver a entrar.
if ($id && $id === Auth::id()) {
    if ($estatus === 0) {
        Respuesta::error('No puedes desactivar tu propia cuenta.');
    }
    if ($idRol !== (int) Auth::usuario()['id_rol']) {
        Respuesta::error('No puedes cambiar tu propio rol.');
    }
}

try {
    if ($id) {
        $sql = 'UPDATE usuario
                   SET nombre_usuario = :u, nombre_completo = :n, correo = :c,
                       id_rol = :r, cedula_prof = :ced, es_dentista = :dent,
                       estatus = :est';
        $params = [':u' => $usuario, ':n' => $nombre, ':c' => $correo,
                   ':r' => $idRol, ':ced' => $cedula, ':dent' => $esDentista,
                   ':est' => $estatus, ':id' => $id];

        if ($pass !== '') {
            $sql .= ', contrasena = :pass, requiere_cambio_pass = 1,
                       intentos_fallidos = 0, bloqueado_hasta = NULL';
            $params[':pass'] = password_hash($pass, PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id_usuario = :id';
        Conexion::consulta($sql, $params);
        $accion = 'CAMBIO';
    } else {
        Conexion::consulta(
            'INSERT INTO usuario
                (nombre_usuario, nombre_completo, correo, id_rol, cedula_prof,
                 es_dentista, estatus, contrasena, requiere_cambio_pass)
             VALUES (:u, :n, :c, :r, :ced, :dent, :est, :pass, 1)',
            [':u' => $usuario, ':n' => $nombre, ':c' => $correo, ':r' => $idRol,
             ':ced' => $cedula, ':dent' => $esDentista, ':est' => $estatus,
             ':pass' => password_hash($pass, PASSWORD_DEFAULT)]
        );
        $id = Conexion::ultimoId();
        $accion = 'ALTA';
    }
} catch (Throwable $e) {
    error_log('[USUARIO/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar el usuario.', 500);
}

// La bitacora nunca guarda la contrasena, solo si se cambio.
Bitacora::registrar('configuracion', $accion, 'usuario', (string) $id, [
    'nombre_usuario'    => $usuario,
    'rol'               => $idRol,
    'cambio_contrasena' => $pass !== '',
]);

Respuesta::ok(['id_usuario' => $id], 'Usuario guardado.');
