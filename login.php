<?php
require_once __DIR__ . '/includes/core/bootstrap.php';

// Si ya hay sesion, al sistema.
if (Auth::autenticado()) {
    header('Location: ' . URL_BASE . '/index.php');
    exit;
}

$error   = null;
$usuario = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $usuario = (string) param('usuario', '');
    $pass    = (string) param('contrasena', '');

    if (!Csrf::valido($_POST['csrf_token'] ?? null)) {
        $error = 'La sesion del formulario expiro. Intenta de nuevo.';
    } elseif ($usuario === '' || $pass === '') {
        $error = 'Captura tu usuario y contrasena.';
    } else {
        $r = Auth::intentarLogin($usuario, $pass);
        if ($r['ok']) {
            header('Location: ' . URL_BASE . '/index.php');
            exit;
        }
        $error = $r['mensaje'];
    }
}

$avisoSesion = Sesion::flash('aviso_sesion');
if (isset($_GET['expiro']) && $avisoSesion === null) {
    $avisoSesion = 'Tu sesion expiro. Vuelve a iniciar sesion.';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso &middot; <?= e(APP_NOMBRE) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= URL_BASE ?>/assets/css/estilo.css">
</head>
<body class="pantalla-login">

<div class="caja-login">
    <div class="logo"><i class="bi bi-heart-pulse"></i></div>
    <h1 class="h5 text-center mb-1 texto-morado fw-semibold">Consultorio Dental</h1>
    <p class="text-center text-secondary small mb-4">Acceso al sistema</p>

    <?php if ($avisoSesion): ?>
        <div class="alert alert-warning py-2 small"><?= e($avisoSesion) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
        <?= Csrf::campo() ?>

        <div class="mb-3">
            <label class="form-label" for="usuario">Usuario</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control sin-mayusculas" id="usuario"
                       name="usuario" value="<?= e($usuario) ?>" maxlength="40"
                       required autofocus autocomplete="off" spellcheck="false">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="contrasena">Contrase&ntilde;a</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" id="contrasena"
                       name="contrasena" maxlength="72" required>
                <button class="btn btn-outline-secondary" type="button" id="verPass"
                        tabindex="-1" title="Mostrar contrasena">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <div class="alert alert-warning py-2 small mb-3 d-none" id="avisoMayus"
             style="align-items:center; gap:.5rem">
            <i class="bi bi-capslock-fill"></i>
            <span>Tienes activado <b>Bloq May&uacute;s</b>.</span>
        </div>

        <button type="submit" class="btn btn-morado w-100 py-2 fw-semibold">
            Entrar
        </button>
    </form>

    <?php if (APP_ENV !== 'produccion'): ?>
    <div class="mt-4 pt-3 border-top">
        <p class="text-secondary small mb-1 fw-semibold">Usuarios de demostraci&oacute;n</p>
        <p class="text-secondary small mb-0" style="line-height:1.7">
            <code>admin</code> &middot; <code>dra.adriana</code> &middot; <code>recepcion</code><br>
            Contrase&ntilde;a para todos: <code>Admin123!</code>
        </p>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Aviso de Bloq Mayus.
   La contrasena si distingue mayusculas de minusculas, asi que tener la
   tecla activada sin darse cuenta es la causa mas comun de "no me deja
   entrar". Se avisa en cuanto se detecta. */
const avisoMayus = document.getElementById('avisoMayus');

function revisarBloqMayus(ev) {
    const activo = ev.getModifierState && ev.getModifierState('CapsLock');
    avisoMayus.classList.toggle('d-none', !activo);
    // display:flex se pone aqui y no con una clase de Bootstrap: d-flex
    // le gana a d-none por orden en la hoja y el aviso no se ocultaria.
    avisoMayus.style.display = activo ? 'flex' : '';
}

document.querySelectorAll('#usuario, #contrasena').forEach((campo) => {
    campo.addEventListener('keyup', revisarBloqMayus);
    campo.addEventListener('keydown', revisarBloqMayus);
    campo.addEventListener('focus', revisarBloqMayus);
});

document.getElementById('verPass').addEventListener('click', function () {
    const campo = document.getElementById('contrasena');
    const icono = this.querySelector('i');
    const visible = campo.type === 'text';
    campo.type = visible ? 'password' : 'text';
    icono.className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
});
</script>
</body>
</html>
