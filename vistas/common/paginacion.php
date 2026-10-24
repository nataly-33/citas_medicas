<?php
/**
 * Componente Reutilizable de Paginación Estándar de 15 Filas
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 * 
 * Variables esperadas:
 * - $total_registros (int)
 * - $pagina_actual (int)
 * - $limite_registros (int, default 15)
 */

$totalRegistros = isset($total_registros) ? (int)$total_registros : 0;
$limite = isset($limite_registros) ? (int)$limite_registros : (defined('REGISTROS_POR_PAGINA') ? REGISTROS_POR_PAGINA : 15);
$paginaActual = isset($pagina_actual) ? max(1, (int)$pagina_actual) : 1;
$totalPaginas = max(1, (int)ceil($totalRegistros / $limite));

// Cálculo de rangos visibles
$inicio = $totalRegistros === 0 ? 0 : (($paginaActual - 1) * $limite) + 1;
$fin = min($totalRegistros, $paginaActual * $limite);

// Función para construir enlace conservando filtros de búsqueda
if (!function_exists('construirUrlPagina')) {
    function construirUrlPagina(int $numPagina): string {
        $parametros = $_GET;
        $parametros['pagina'] = $numPagina;
        return '?' . http_build_query($parametros);
    }
}
?>
<div class="paginacion-contenedor">
    <div class="paginacion-info">
        Mostrando <strong><?= $inicio ?></strong> a <strong><?= $fin ?></strong> de <strong><?= $totalRegistros ?></strong> registros
    </div>

    <div class="paginacion-controles">
        <?php if ($paginaActual > 1): ?>
            <a href="<?= construirUrlPagina($paginaActual - 1) ?>" class="paginacion-boton" title="Página Anterior">
                <i class="bi bi-chevron-left"></i> Anterior
            </a>
        <?php else: ?>
            <span class="paginacion-boton deshabilitado">
                <i class="bi bi-chevron-left"></i> Anterior
            </span>
        <?php endif; ?>

        <span class="paginacion-boton activo">
            Página <?= $paginaActual ?> de <?= $totalPaginas ?>
        </span>

        <?php if ($paginaActual < $totalPaginas): ?>
            <a href="<?= construirUrlPagina($paginaActual + 1) ?>" class="paginacion-boton" title="Página Siguiente">
                Siguiente <i class="bi bi-chevron-right"></i>
            </a>
        <?php else: ?>
            <span class="paginacion-boton deshabilitado">
                Siguiente <i class="bi bi-chevron-right"></i>
            </span>
        <?php endif; ?>
    </div>
</div>
