<?php
/**
 * Enrutador Principal del Sistema Clínica Vida Salud
 * Sistemas de Información 1 - UAGRM
 */

require_once __DIR__ . '/configuration/aplicacion.php';
require_once __DIR__ . '/src/core/GuardiaSesion.php';

use App\Nucleo\GuardiaSesion;

GuardiaSesion::iniciarSesionSiEsNecesario();

if (!empty($_SESSION['autenticado']) && !empty($_SESSION['id_usuario'])) {
    header("Location: vistas/panel/index.php");
    exit;
} else {
    header("Location: vistas/autenticacion/login.php");
    exit;
}
