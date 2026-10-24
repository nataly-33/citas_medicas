<?php
/**
 * Configuración General de la Aplicación
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

date_default_timezone_set('America/La_Paz');

define('RUTA_RAIZ', dirname(__DIR__));

if (!defined('URL_BASE')) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($uri, '/citas_medicas') === 0 || strpos($script, '/citas_medicas') === 0) {
        define('URL_BASE', '/citas_medicas');
    } else {
        define('URL_BASE', '');
    }
}

define('REGISTROS_POR_PAGINA', 15);

define('DURACION_CITA_MINUTOS', 30);

// Autocarga PSR-4 de clases bajo el namespace App\ compatible con Linux y Windows
spl_autoload_register(function (string $clase): void {
    $prefijo = 'App\\';
    $directorioBase = RUTA_RAIZ . '/src/';

    $longitudPrefijo = strlen($prefijo);
    if (strncmp($prefijo, $clase, $longitudPrefijo) !== 0) {
        return;
    }

    $claseRelativa = substr($clase, $longitudPrefijo);
    $partes = explode('\\', $claseRelativa);
    $nombreArchivo = array_pop($partes) . '.php';
    
    // Mapeo canónico a carpetas físicas en minúscula de src/
    $subdirectorio = strtolower(implode('/', $partes));
    if ($subdirectorio === 'nucleo') {
        $subdirectorio = 'core';
    }

    $archivoMapeado = $directorioBase . ($subdirectorio ? $subdirectorio . '/' : '') . $nombreArchivo;
    if (file_exists($archivoMapeado)) {
        require_once $archivoMapeado;
        return;
    }

    $archivoDirecto = $directorioBase . str_replace('\\', '/', $claseRelativa) . '.php';
    if (file_exists($archivoDirecto)) {
        require_once $archivoDirecto;
    }
});
