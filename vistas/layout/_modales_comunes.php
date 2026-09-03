<?php
/**
 * Modales que puede usar cualquier pantalla del sistema.
 *
 * Se cargan siempre porque son baratos (HTML oculto) y porque tenerlos
 * en cada vista llevaba a que faltaran justo donde se necesitaban.
 */
require __DIR__ . '/../paciente/_modal_ocupacion.php';
require __DIR__ . '/../paciente/_modal_colonia.php';
