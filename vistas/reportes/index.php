<?php
/**
 * Vista de Reportes Administrativos y Métricas Gerenciales (CU13)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

require_once dirname(__DIR__, 2) . '/configuration/aplicacion.php';
require_once dirname(__DIR__, 2) . '/src/core/GuardiaSesion.php';
require_once dirname(__DIR__, 2) . '/src/core/Ayudantes.php';
require_once dirname(__DIR__, 2) . '/src/core/Conexion.php';
require_once dirname(__DIR__, 2) . '/src/controladores/ReporteControlador.php';

use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;
use App\Nucleo\Conexion;
use App\Controladores\ReporteControlador;

GuardiaSesion::verificarAutenticacion();
GuardiaSesion::verificarRol(['Administrador', 'Recepcionista', 'Medico']);

$rolUsuario = $_SESSION['nombre_rol'] ?? '';
$idUsuario = $_SESSION['id_usuario'] ?? 0;
$pdo = Conexion::obtenerConexion();
$reporteControlador = new ReporteControlador();

// ============================================================
// CONFIGURACIÓN DE FILTROS Y TIPO DE REPORTE
// ============================================================
$tipoReporte = $_GET['tipo_reporte'] ?? 'citas'; // 'citas' o 'pacientes'

$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-t');
$filtroIdMedico = (int)($_GET['id_medico'] ?? 0);
$filtroIdEspecialidad = (int)($_GET['id_especialidad'] ?? 0);
$filtroEstado = trim($_GET['estado'] ?? '');
$buscar = trim($_GET['buscar'] ?? '');

// Filtros específicos del Padrón de Pacientes
$filtroSexo = trim($_GET['sexo'] ?? '');
$filtroEstadoPac = trim($_GET['estado_pac'] ?? '');
$filtroSangre = trim($_GET['tipo_sangre'] ?? '');

// Si el usuario es Médico, restringir forzosamente a su ID
if ($rolUsuario === 'Medico') {
    $stmtM = $pdo->prepare("SELECT id FROM medicos WHERE id_usuario = :id_u");
    $stmtM->execute([':id_u' => $idUsuario]);
    $filtroIdMedico = (int)$stmtM->fetchColumn();
}

// ============================================================
// EXPORTACIÓN A CSV NATIVO
// ============================================================
if (isset($_GET['accion']) && $_GET['accion'] === 'exportar_csv') {
    if ($tipoReporte === 'pacientes') {
        $reporteControlador->exportarCsvPacientes($buscar, $filtroSexo, $filtroEstadoPac, $filtroSangre);
    } else {
        $reporteControlador->exportarCsv($fechaInicio, $fechaFin, $filtroIdMedico, $filtroIdEspecialidad, $filtroEstado);
    }
    exit;
}

// ============================================================
// CONSULTA SEGÚN EL TIPO DE REPORTE SELECCIONADO
// ============================================================
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$limite_registros = REGISTROS_POR_PAGINA;
$desplazamiento = ($pagina_actual - 1) * $limite_registros;

if ($tipoReporte === 'pacientes') {
    $todosPacientes = $reporteControlador->obtenerReportePacientes($buscar, $filtroSexo, $filtroEstadoPac, $filtroSangre);
    $total_registros = count($todosPacientes);
    $datosPaginados = array_slice($todosPacientes, $desplazamiento, $limite_registros);

    // Métricas del padrón de pacientes filtrado
    $totalesPadron = [
        'Total'      => $total_registros,
        'Activos'    => 0,
        'Inactivos'  => 0,
        'TotalCitas' => 0
    ];
    foreach ($todosPacientes as $p) {
        if (!empty($p['activo'])) $totalesPadron['Activos']++;
        else $totalesPadron['Inactivos']++;
        $totalesPadron['TotalCitas'] += (int)($p['total_citas'] ?? 0);
    }
} else {
    // Reporte de Citas Médicas
    $todasCitas = $reporteControlador->obtenerDetalleReporte($fechaInicio, $fechaFin, $filtroIdMedico, $filtroIdEspecialidad, $filtroEstado);
    $total_registros = count($todasCitas);
    $datosPaginados = array_slice($todasCitas, $desplazamiento, $limite_registros);

    // Métricas reactivas calculadas directamente sobre el subconjunto de citas filtradas
    $totalesCitas = [
        'Total'                 => $total_registros,
        'Atendida'              => 0,
        'Programada_Confirmada' => 0,
        'Cancelada'             => 0,
        'Inasistencia'          => 0,
    ];
    foreach ($todasCitas as $c) {
        $st = $c['estado'];
        if ($st === 'Atendida') {
            $totalesCitas['Atendida']++;
        } elseif ($st === 'Programada' || $st === 'Confirmada') {
            $totalesCitas['Programada_Confirmada']++;
        } elseif ($st === 'Cancelada') {
            $totalesCitas['Cancelada']++;
        } elseif ($st === 'Inasistencia') {
            $totalesCitas['Inasistencia']++;
        }
    }
}

// Listas para los filtros
$stmtMedicos = $pdo->query("SELECT id, nombres, apellidos FROM medicos WHERE activo = TRUE ORDER BY apellidos ASC");
$listaMedicos = $stmtMedicos->fetchAll();

$stmtEspecialidades = $pdo->query("SELECT id, nombre FROM especialidades WHERE activa = TRUE ORDER BY nombre ASC");
$listaEspecialidades = $stmtEspecialidades->fetchAll();

$titulo_pagina = 'Reportes Administrativos';
$titulo_seccion = 'Reportes y Métricas Administrativas';
$script_modulo = 'reportes.js';
require_once dirname(__DIR__) . '/common/encabezado.php';
?>

<!-- Encabezado Visible Únicamente en Modo Impresión / PDF -->
<div class="encabezado-impresion-reporte">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 16pt; font-weight: 700; color: #1B4332; margin: 0;">Clínica Vida Salud</h1>
            <p style="font-size: 10pt; color: #64748B; margin: 2px 0 0;">
                <?= $tipoReporte === 'pacientes' ? 'Padrón Oficial de Pacientes Registrados' : 'Reporte de Gestión de Citas Médicas' ?>
            </p>
        </div>
        <div style="text-align: right; font-size: 9pt; color: #64748B;">
            <div>Generado el: <?= date('d/m/Y H:i') ?></div>
            <div>Operador: <?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['correo'] ?? 'Sistema') ?></div>
        </div>
    </div>
</div>

<!-- ============================================================
     SELECTOR DE TIPO DE REPORTE Y MÉTRICAS
     ============================================================ -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
    <div style="display: flex; gap: 0.5rem; background-color: #FFFFFF; padding: 0.35rem; border-radius: 6px; border: 1px solid var(--color-borde);">
        <a href="?tipo_reporte=citas" class="btn btn-sm <?= $tipoReporte === 'citas' ? 'btn-primario' : 'btn-secundario' ?>">
            <i class="bi bi-calendar3"></i>
            <span>Reporte de Citas Médicas</span>
        </a>
        <a href="?tipo_reporte=pacientes" class="btn btn-sm <?= $tipoReporte === 'pacientes' ? 'btn-primario' : 'btn-secundario' ?>">
            <i class="bi bi-people"></i>
            <span>Padrón de Pacientes Registrados</span>
        </a>
    </div>

    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-secundario" onclick="window.print()" id="btnExportarPdf" title="Imprimir o Exportar a PDF">
            <i class="bi bi-printer"></i>
            <span>Exportar a PDF</span>
        </button>
        <button type="button" class="btn btn-acento" id="btnExportarCsv" title="Descargar datos en formato CSV para Excel">
            <i class="bi bi-file-earmark-spreadsheet"></i>
            <span>Exportar CSV</span>
        </button>
    </div>
</div>

<?php if ($tipoReporte === 'citas'): ?>
    <!-- Tarjetas de Métricas Compactas de Citas -->
    <div class="tarjetas-metricas-compactas">
        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Total Citas</span>
                <span class="metrica-valor"><?= $totalesCitas['Total'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: #E2E8F0; color: var(--color-primario);">
                <i class="bi bi-calendar3"></i>
            </div>
        </div>

        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Atendidas</span>
                <span class="metrica-valor" style="color: #16A34A;"><?= $totalesCitas['Atendida'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: #D8F3DC; color: #16A34A;">
                <i class="bi bi-clipboard2-check"></i>
            </div>
        </div>

        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Prog. / Conf.</span>
                <span class="metrica-valor" style="color: #0284C7;"><?= $totalesCitas['Programada_Confirmada'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: #E0F2FE; color: #0284C7;">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>

        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Canceladas</span>
                <span class="metrica-valor" style="color: var(--color-peligro);"><?= $totalesCitas['Cancelada'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: var(--color-peligro-fondo); color: var(--color-peligro);">
                <i class="bi bi-calendar-x"></i>
            </div>
        </div>

        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Inasistencias</span>
                <span class="metrica-valor" style="color: #D97706;"><?= $totalesCitas['Inasistencia'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: #FEF3C7; color: #D97706;">
                <i class="bi bi-person-x"></i>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Tarjetas de Métricas Compactas del Padrón de Pacientes -->
    <div class="tarjetas-metricas-compactas">
        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Total Pacientes</span>
                <span class="metrica-valor"><?= $totalesPadron['Total'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: #D8F3DC; color: var(--color-primario);">
                <i class="bi bi-people"></i>
            </div>
        </div>

        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Activos</span>
                <span class="metrica-valor" style="color: #16A34A;"><?= $totalesPadron['Activos'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: #ECFDF5; color: #059669;">
                <i class="bi bi-person-check"></i>
            </div>
        </div>

        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Inactivos</span>
                <span class="metrica-valor" style="color: var(--color-texto-muted);"><?= $totalesPadron['Inactivos'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: #F1F5F9; color: var(--color-texto-muted);">
                <i class="bi bi-person-slash"></i>
            </div>
        </div>

        <div class="tarjeta-metrica-compacta">
            <div class="metrica-info">
                <span class="metrica-titulo">Citas Acumuladas</span>
                <span class="metrica-valor" style="color: #0284C7;"><?= $totalesPadron['TotalCitas'] ?></span>
            </div>
            <div class="metrica-icono" style="background-color: #E0F2FE; color: #0284C7;">
                <i class="bi bi-calendar-event"></i>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Tarjeta Principal con Filtros y Tabla -->
<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <h2 class="tarjeta-titulo">
            <i class="bi bi-<?= $tipoReporte === 'pacientes' ? 'people' : 'calendar3' ?>"></i>
            <span><?= $tipoReporte === 'pacientes' ? 'Padrón Detallado de Pacientes' : 'Detalle de Citas del Período' ?></span>
        </h2>
    </div>

    <div class="tarjeta-cuerpo">
        <?php if ($tipoReporte === 'citas'): ?>
            <!-- Barra de Filtros de Citas -->
            <form method="GET" action="index.php" class="barra-filtros">
                <input type="hidden" name="tipo_reporte" value="citas">

                <div style="min-width: 140px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Desde:</label>
                    <input type="date" name="fecha_inicio" id="filtro_fecha_inicio" class="control-campo" value="<?= htmlspecialchars($fechaInicio) ?>">
                </div>

                <div style="min-width: 140px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Hasta:</label>
                    <input type="date" name="fecha_fin" id="filtro_fecha_fin" class="control-campo" value="<?= htmlspecialchars($fechaFin) ?>">
                </div>

                <?php if ($rolUsuario !== 'Medico'): ?>
                <div style="min-width: 180px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Médico:</label>
                    <select name="id_medico" id="filtro_id_medico" class="control-campo">
                        <option value="">-- Todos los Médicos --</option>
                        <?php foreach ($listaMedicos as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $filtroIdMedico === (int)$m['id'] ? 'selected' : '' ?>>
                                Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="min-width: 170px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Especialidad:</label>
                    <select name="id_especialidad" id="filtro_id_especialidad" class="control-campo">
                        <option value="">-- Todas las Especialidades --</option>
                        <?php foreach ($listaEspecialidades as $esp): ?>
                            <option value="<?= $esp['id'] ?>" <?= $filtroIdEspecialidad === (int)$esp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($esp['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="min-width: 150px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Estado:</label>
                    <select name="estado" id="filtro_estado" class="control-campo">
                        <option value="">-- Todos los Estados --</option>
                        <option value="Programada" <?= $filtroEstado === 'Programada' ? 'selected' : '' ?>>Programada</option>
                        <option value="Confirmada" <?= $filtroEstado === 'Confirmada' ? 'selected' : '' ?>>Confirmada</option>
                        <option value="Atendida" <?= $filtroEstado === 'Atendida' ? 'selected' : '' ?>>Atendida</option>
                        <option value="Cancelada" <?= $filtroEstado === 'Cancelada' ? 'selected' : '' ?>>Cancelada</option>
                        <option value="Inasistencia" <?= $filtroEstado === 'Inasistencia' ? 'selected' : '' ?>>Inasistencia</option>
                    </select>
                </div>

                <div style="align-self: flex-end;">
                    <button type="submit" class="btn btn-secundario">
                        <i class="bi bi-search"></i>
                        <span>Filtrar</span>
                    </button>
                    <a href="?tipo_reporte=citas" class="btn btn-secundario" title="Limpiar Filtros">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>

            <!-- Tabla de Reportes de Citas (15 Filas) -->
            <div class="tabla-responsiva">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Paciente</th>
                            <th>Médico</th>
                            <th>Especialidad</th>
                            <th>Estado</th>
                            <th>Notas / Diagnóstico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($datosPaginados)): ?>
                            <tr>
                                <td colspan="8" class="texto-centro texto-muted" style="padding: 2.5rem;">
                                    <i class="bi bi-info-circle"></i> No se registran citas médicas para los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($datosPaginados as $cr): 
                                $badgeClase = 'estado-programada';
                                if ($cr['estado'] === 'Confirmada') $badgeClase = 'estado-confirmada';
                                if ($cr['estado'] === 'Atendida') $badgeClase = 'estado-atendida';
                                if ($cr['estado'] === 'Cancelada') $badgeClase = 'estado-cancelada';
                                if ($cr['estado'] === 'Inasistencia') $badgeClase = 'estado-inasistencia';

                                $detalle = $cr['observaciones_atencion'] ?: ($cr['motivo_cancelacion'] ? 'Motivo: ' . $cr['motivo_cancelacion'] : ($cr['motivo_consulta'] ?: '-'));
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($cr['codigo_cita']) ?></strong></td>
                                    <td><?= Ayudantes::formatearFechaBolivia($cr['fecha']) ?></td>
                                    <td><?= substr($cr['hora_inicio'], 0, 5) ?> - <?= substr($cr['hora_fin'], 0, 5) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($cr['nombre_paciente'] ?? $cr['paciente_nombre']) ?></div>
                                        <div class="texto-muted" style="font-size: 0.75rem;">CI: <?= htmlspecialchars($cr['ci_paciente']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($cr['nombre_medico'] ?? $cr['medico_nombre']) ?></td>
                                    <td><?= htmlspecialchars($cr['nombre_especialidad'] ?? $cr['especialidad_nombre']) ?></td>
                                    <td>
                                        <span class="insignia-estado <?= $badgeClase ?>">
                                            <?= htmlspecialchars($cr['estado']) ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 280px; font-size: 0.8125rem;">
                                        <?= htmlspecialchars(mb_strimwidth($detalle, 0, 90, '...')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- Barra de Filtros del Padrón de Pacientes -->
            <form method="GET" action="index.php" class="barra-filtros">
                <input type="hidden" name="tipo_reporte" value="pacientes">

                <div style="flex: 2; min-width: 220px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Búsqueda General:</label>
                    <input type="text" name="buscar" id="filtro_buscar_paciente" class="control-campo" 
                           placeholder="Buscar por CI, Código, Nombres, Apellidos o Teléfono..." 
                           value="<?= htmlspecialchars($buscar) ?>">
                </div>

                <div style="flex: 1; min-width: 110px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Sexo:</label>
                    <select name="sexo" id="filtro_sexo" class="control-campo">
                        <option value="">-- Todos --</option>
                        <option value="M" <?= $filtroSexo === 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= $filtroSexo === 'F' ? 'selected' : '' ?>>Femenino</option>
                    </select>
                </div>

                <div style="flex: 1; min-width: 120px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Estado:</label>
                    <select name="estado_pac" id="filtro_estado_pac" class="control-campo">
                        <option value="">-- Todos --</option>
                        <option value="activo" <?= $filtroEstadoPac === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $filtroEstadoPac === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>

                <div style="flex: 1; min-width: 120px;">
                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Tipo Sangre:</label>
                    <select name="tipo_sangre" id="filtro_tipo_sangre" class="control-campo">
                        <option value="">-- Todos --</option>
                        <option value="ORH+" <?= $filtroSangre === 'ORH+' ? 'selected' : '' ?>>ORH+</option>
                        <option value="ORH-" <?= $filtroSangre === 'ORH-' ? 'selected' : '' ?>>ORH-</option>
                        <option value="ARH+" <?= $filtroSangre === 'ARH+' ? 'selected' : '' ?>>ARH+</option>
                        <option value="ARH-" <?= $filtroSangre === 'ARH-' ? 'selected' : '' ?>>ARH-</option>
                        <option value="BRH+" <?= $filtroSangre === 'BRH+' ? 'selected' : '' ?>>BRH+</option>
                        <option value="BRH-" <?= $filtroSangre === 'BRH-' ? 'selected' : '' ?>>BRH-</option>
                        <option value="ABRH+" <?= $filtroSangre === 'ABRH+' ? 'selected' : '' ?>>ABRH+</option>
                        <option value="ABRH-" <?= $filtroSangre === 'ABRH-' ? 'selected' : '' ?>>ABRH-</option>
                    </select>
                </div>

                <div style="align-self: flex-end; display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-secundario">
                        <i class="bi bi-search"></i>
                        <span>Buscar</span>
                    </button>
                    <a href="?tipo_reporte=pacientes" class="btn btn-secundario" title="Limpiar Filtros">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Limpiar</span>
                    </a>
                </div>
            </form>

            <!-- Tabla de Padrón de Pacientes (15 Filas) -->
            <div class="tabla-responsiva">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cédula (CI)</th>
                            <th>Paciente</th>
                            <th>Sexo</th>
                            <th>Nacimiento</th>
                            <th>Teléfono</th>
                            <th>Grupo Sang.</th>
                            <th>Historial Citas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($datosPaginados)): ?>
                            <tr>
                                <td colspan="9" class="texto-centro texto-muted" style="padding: 2.5rem;">
                                    <i class="bi bi-info-circle"></i> No se encontraron pacientes en el padrón con el criterio ingresado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($datosPaginados as $p): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['codigo']) ?></strong></td>
                                    <td><?= htmlspecialchars($p['ci']) ?></td>
                                    <td><?= htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($p['sexo']) ?></td>
                                    <td><?= Ayudantes::formatearFechaBolivia($p['fecha_nacimiento']) ?></td>
                                    <td><?= htmlspecialchars($p['telefono']) ?></td>
                                    <td>
                                        <span class="badge-tipo-sangre" style="font-size: 0.75rem;">
                                            <?= htmlspecialchars($p['tipo_sangre'] ?: 'ORH+') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.75rem;">
                                            <span>Total: <strong><?= $p['total_citas'] ?></strong></span> |
                                            <span style="color: #16A34A;">Atendidas: <?= $p['citas_atendidas'] ?></span> |
                                            <span style="color: var(--color-peligro);">Canceladas: <?= $p['citas_canceladas'] ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="insignia-estado <?= !empty($p['activo']) ? 'estado-activo' : 'estado-inactivo' ?>">
                                            <?= !empty($p['activo']) ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Paginación Compartida de 15 Filas a la Derecha -->
    <?php require dirname(__DIR__) . '/common/paginacion.php'; ?>
</div>

<?php
require_once dirname(__DIR__) . '/common/pie.php';
?>

<script>
document.getElementById('btnExportarCsv')?.addEventListener('click', function () {
    const tipo = '<?= $tipoReporte ?>';
    const params = new URLSearchParams(window.location.search);
    params.set('accion', 'exportar_csv');
    window.location.href = 'index.php?' + params.toString();
});
</script>
