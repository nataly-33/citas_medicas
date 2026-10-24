<?php
/**
 * Barra Lateral de Navegación Reactiva según Rol en Sesión
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

$rolUsuario = $_SESSION['nombre_rol'] ?? 'Recepcionista';
$rutaActual = $_SERVER['PHP_SELF'] ?? '';
$urlBase = defined('URL_BASE') ? URL_BASE : '/citas_medicas';

function esRutaActiva(string $patron, string $rutaActual): string {
    // Si buscamos citas pero la ruta actual es citas/hoy, diferenciar
    if ($patron === 'vistas/citas/index' && strpos($rutaActual, 'vistas/citas/hoy') !== false) {
        return '';
    }
    return strpos($rutaActual, $patron) !== false ? 'activo' : '';
}
?>
<aside class="barra-lateral">
    <div class="barra-lateral-cabecera">
        <i class="bi bi-hospital logo-icono"></i>
        <div class="logo-texto">Vida Salud</div>
    </div>

    <nav class="barra-lateral-menu">
        <a href="<?= $urlBase ?>/vistas/panel/index.php" class="menu-enlace <?= esRutaActiva('vistas/panel', $rutaActual) ?>">
            <i class="bi bi-grid-1x2"></i>
            <span>Panel de Control</span>
        </a>

        <?php if ($rolUsuario === 'Administrador'): ?>
            <div class="menu-seccion-titulo">Configuración del Sistema</div>
            <a href="<?= $urlBase ?>/vistas/usuarios/index.php" class="menu-enlace <?= esRutaActiva('vistas/usuarios', $rutaActual) ?>">
                <i class="bi bi-shield-lock"></i>
                <span>Gestión de Usuarios</span>
            </a>
            <a href="<?= $urlBase ?>/vistas/medicos/index.php" class="menu-enlace <?= esRutaActiva('vistas/medicos', $rutaActual) ?>">
                <i class="bi bi-person-badge"></i>
                <span>Médicos y Especialidades</span>
            </a>

            <div class="menu-seccion-titulo">Supervisión</div>
            <a href="<?= $urlBase ?>/vistas/reportes/index.php" class="menu-enlace <?= esRutaActiva('vistas/reportes', $rutaActual) ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Reportes Generales</span>
            </a>
        <?php endif; ?>

        <?php if ($rolUsuario === 'Recepcionista'): ?>
            <div class="menu-seccion-titulo">Gestión de Citas</div>
            <a href="<?= $urlBase ?>/vistas/citas/index.php" class="menu-enlace <?= esRutaActiva('vistas/citas/index', $rutaActual) ?>">
                <i class="bi bi-calendar3"></i>
                <span>Citas Médicas</span>
            </a>
            <a href="<?= $urlBase ?>/vistas/citas/hoy.php" class="menu-enlace <?= esRutaActiva('vistas/citas/hoy', $rutaActual) ?>">
                <i class="bi bi-calendar2-day"></i>
                <span>Citas de Hoy (Tablero)</span>
            </a>

            <div class="menu-seccion-titulo">Atención Clínica</div>
            <a href="<?= $urlBase ?>/vistas/pacientes/index.php" class="menu-enlace <?= esRutaActiva('vistas/pacientes', $rutaActual) ?>">
                <i class="bi bi-people"></i>
                <span>Pacientes</span>
            </a>
            <a href="<?= $urlBase ?>/vistas/reportes/index.php" class="menu-enlace <?= esRutaActiva('vistas/reportes', $rutaActual) ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Reportes</span>
            </a>
        <?php endif; ?>

        <?php if ($rolUsuario === 'Medico'): ?>
            <div class="menu-seccion-titulo">Consultorio Médico</div>
            <a href="<?= $urlBase ?>/vistas/citas/hoy.php" class="menu-enlace <?= esRutaActiva('vistas/citas/hoy', $rutaActual) ?>">
                <i class="bi bi-calendar2-day"></i>
                <span>Mi Agenda de Hoy</span>
            </a>
            <a href="<?= $urlBase ?>/vistas/citas/index.php" class="menu-enlace <?= esRutaActiva('vistas/citas/index', $rutaActual) ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Mis Citas</span>
            </a>
            <a href="<?= $urlBase ?>/vistas/pacientes/index.php" class="menu-enlace <?= esRutaActiva('vistas/pacientes', $rutaActual) ?>">
                <i class="bi bi-people"></i>
                <span>Buscar Pacientes</span>
            </a>
            <a href="<?= $urlBase ?>/vistas/reportes/index.php" class="menu-enlace <?= esRutaActiva('vistas/reportes', $rutaActual) ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Reportes</span>
            </a>
        <?php endif; ?>

        <?php if ($rolUsuario === 'Paciente'): ?>
            <div class="menu-seccion-titulo">Portal del Paciente</div>
            <a href="<?= $urlBase ?>/vistas/perfil/index.php" class="menu-enlace <?= esRutaActiva('vistas/perfil', $rutaActual) ?>">
                <i class="bi bi-person-vcard"></i>
                <span>Mi Perfil y Ficha Médica</span>
            </a>
            <a href="<?= $urlBase ?>/vistas/citas/index.php" class="menu-enlace <?= esRutaActiva('vistas/citas/index', $rutaActual) ?>">
                <i class="bi bi-calendar3"></i>
                <span>Mis Citas Médicas</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="barra-lateral-pie">
        <div>Sistemas 1 - UAGRM</div>
        <div style="font-size: 0.6875rem; color: #64748B;">Semestre 2-2026</div>
    </div>
</aside>
