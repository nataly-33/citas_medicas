<?php
/**
 * Parcial Encabezado HTML y Maquetación Base
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

if (!defined('RUTA_RAIZ')) {
    require_once dirname(__DIR__, 2) . '/configuracion/aplicacion.php';
}
if (!defined('URL_BASE')) {
    define('URL_BASE', '/citas_medicas');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_pagina ?? 'Clínica Vida Salud') ?> - Sistemas 1</title>
    <!-- Cero Emojis: Bootstrap Icons Oficial Vectorial -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/publico/css/tema.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/publico/css/estilos.css">
</head>
<body>
<?php if (!empty($_SESSION['autenticado'])): ?>
<div class="contenedor-aplicacion">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="contenido-principal">
        <?php require_once __DIR__ . '/topbar.php'; ?>
        <main class="cuerpo-contenido">
            <?php require_once __DIR__ . '/alerta.php'; ?>
<?php endif; ?>
