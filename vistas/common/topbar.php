<?php
/**
 * Barra Superior (Topbar) con Perfil de Usuario y Cierre de Sesión
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

$nombreMostrar = !empty($_SESSION['nombres']) ? trim($_SESSION['nombres'] . ' ' . ($_SESSION['apellidos'] ?? '')) : ($_SESSION['correo'] ?? 'Operador');
$rolUsuario = $_SESSION['nombre_rol'] ?? 'Invitado';
$urlBase = defined('URL_BASE') ? URL_BASE : '/citas_medicas';
?>
<header class="barra-superior">
    <div class="barra-superior-titulo">
        <i class="bi bi-hospital"></i>
        <span><?= htmlspecialchars($titulo_seccion ?? 'Clínica Vida Salud') ?></span>
    </div>

    <div class="barra-superior-perfil">
        <div class="perfil-usuario-info">
            <div class="perfil-usuario-nombre"><?= htmlspecialchars($nombreMostrar) ?></div>
            <div class="perfil-usuario-rol"><?= htmlspecialchars($rolUsuario) ?></div>
        </div>

        <a href="<?= $urlBase ?>/vistas/autenticacion/login.php?accion=cerrar_sesion" class="btn btn-sm btn-secundario" title="Cerrar Sesión Segura">
            <i class="bi bi-box-arrow-right"></i>
            <span>Salir</span>
        </a>
    </div>
</header>
