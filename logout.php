<?php
require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::cerrarSesion();

header('Location: ' . URL_BASE . '/login.php');
exit;
