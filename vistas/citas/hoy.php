<?php
/**
 * Tablero Diario / Kanban de Citas Médicas del Día
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

require_once dirname(__DIR__, 2) . '/configuration/aplicacion.php';
require_once dirname(__DIR__, 2) . '/src/core/GuardiaSesion.php';
require_once dirname(__DIR__, 2) . '/src/core/Ayudantes.php';
require_once dirname(__DIR__, 2) . '/src/controladores/CitaControlador.php';
require_once dirname(__DIR__, 2) . '/src/controladores/MedicoControlador.php';
require_once dirname(__DIR__, 2) . '/src/controladores/HorarioControlador.php';

use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;
use App\Controladores\CitaControlador;
use App\Controladores\MedicoControlador;
use App\Controladores\HorarioControlador;

GuardiaSesion::verificarAutenticacion();
GuardiaSesion::verificarRol(['Administrador', 'Recepcionista', 'Medico']);

$rolUsuario = $_SESSION['nombre_rol'] ?? '';
$idUsuario = $_SESSION['id_usuario'] ?? 0;
$citaControlador = new CitaControlador();
$medicoControlador = new MedicoControlador();
$horarioControlador = new HorarioControlador();

// ============================================================
// PROCESAMIENTO DE PETICIONES AJAX
// ============================================================
if (isset($_GET['accion']) && $_GET['accion'] === 'disponibilidad') {
    $idMedico = (int)($_GET['id_medico'] ?? 0);
    $fecha = trim($_GET['fecha'] ?? '');
    $excluirIdCita = isset($_GET['excluir_id_cita']) ? (int)$_GET['excluir_id_cita'] : null;
    $resultado = $horarioControlador->consultarDisponibilidad($idMedico, $fecha, $excluirIdCita);
    Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
}

if (isset($_GET['accion']) && $_GET['accion'] === 'detalle') {
    $idCita = (int)($_GET['id_cita'] ?? 0);
    $resDetalle = $citaControlador->obtenerDetalle($idCita);
    Ayudantes::responderJson($resDetalle, $resDetalle['exito'] ? 200 : 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $accion = $datos['accion'] ?? '';

    if ($accion === 'agendar') {
        $datos['id_usuario_registro'] = $idUsuario;
        $resultado = $citaControlador->agendarCita($datos);
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }

    if ($accion === 'reprogramar') {
        $idCita = (int)($datos['id_cita'] ?? 0);
        $fecha = trim($datos['fecha'] ?? '');
        $hIni = trim($datos['hora_inicio'] ?? '');
        $hFin = trim($datos['hora_fin'] ?? '');
        $resultado = $citaControlador->reprogramarCita($idCita, $fecha, $hIni, $hFin);
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }

    if ($accion === 'atender') {
        if ($rolUsuario !== 'Medico' && $rolUsuario !== 'Administrador') {
            Ayudantes::responderJson([
                'exito' => false,
                'mensaje' => 'Operación no permitida: Solo el médico asignado puede registrar la atención clínica.'
            ], 403);
        }
        $idCita = (int)($datos['id_cita'] ?? 0);
        $obs = $datos['observaciones_atencion'] ?? '';
        $res = $citaControlador->marcarAtendida($idCita, $obs);
        Ayudantes::responderJson($res, $res['exito'] ? 200 : 400);
    }

    if ($accion === 'cancelar') {
        $idCita = (int)($datos['id_cita'] ?? 0);
        $motivo = $datos['motivo_cancelacion'] ?? '';
        $res = $citaControlador->cancelarCita($idCita, $motivo);
        Ayudantes::responderJson($res, $res['exito'] ? 200 : 400);
    }

    if ($accion === 'inasistencia') {
        $idCita = (int)($datos['id_cita'] ?? 0);
        $res = $citaControlador->marcarInasistencia($idCita);
        Ayudantes::responderJson($res, $res['exito'] ? 200 : 400);
    }

    if ($accion === 'confirmar_asistencia') {
        $idCita = (int)($datos['id_cita'] ?? 0);
        try {
            $pdo = \App\Nucleo\Conexion::obtenerConexion();
            $stmt = $pdo->prepare("UPDATE citas_medicas SET estado = 'Confirmada', fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':id' => $idCita]);
            Ayudantes::responderJson(['exito' => true, 'mensaje' => 'Asistencia presencial confirmada.']);
        } catch (\Exception $e) {
            Ayudantes::responderJson(['exito' => false, 'mensaje' => 'Error al confirmar asistencia.'], 500);
        }
    }
}

// ============================================================
// FILTROS Y NAVEGACIÓN TIPO CARRUSEL
// ============================================================
$fechaActualHoy = date('Y-m-d');
$fechaSeleccionada = !empty($_GET['fecha']) ? trim($_GET['fecha']) : $fechaActualHoy;

$fechaAnterior = date('Y-m-d', strtotime($fechaSeleccionada . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fechaSeleccionada . ' +1 day'));
$esHoy = ($fechaSeleccionada === $fechaActualHoy);

$filtroIdMedico = (int)($_GET['id_medico'] ?? 0);
$pdo = \App\Nucleo\Conexion::obtenerConexion();

// Si es Médico, forzar su propio ID
if ($rolUsuario === 'Medico') {
    $stmtM = $pdo->prepare("SELECT id FROM medicos WHERE id_usuario = :id_u");
    $stmtM->execute([':id_u' => $idUsuario]);
    $filtroIdMedico = (int)$stmtM->fetchColumn();
}

// Carga de listas completas para modales de agendamiento y filtros
$stmtPac = $pdo->query("SELECT id, codigo, ci, nombres, apellidos FROM pacientes WHERE activo = TRUE ORDER BY apellidos ASC, nombres ASC");
$listaPacientes = $stmtPac->fetchAll();

$stmtMed = $pdo->query("
    SELECT m.id, m.codigo, m.registro_profesional, m.nombres, m.apellidos,
           COALESCE(string_agg(me.id_especialidad::text, ','), '') AS especialidades_ids
    FROM medicos m
    LEFT JOIN medico_especialidad me ON me.id_medico = m.id
    WHERE m.activo = TRUE
    GROUP BY m.id, m.codigo, m.registro_profesional, m.nombres, m.apellidos
    ORDER BY m.apellidos ASC, m.nombres ASC
");
$listaMedicos = $stmtMed->fetchAll();

$stmtEsp = $pdo->query("SELECT id, nombre FROM especialidades WHERE activa = TRUE ORDER BY nombre ASC");
$listaEspecialidades = $stmtEsp->fetchAll();

// Obtener citas del día
$resCitas = $citaControlador->citasPorFecha($fechaSeleccionada, $filtroIdMedico);
$citasDelDia = $resCitas['citas'] ?? [];

// Categorizar citas en las 4 columnas
$colProgramadas = [];
$colConfirmadas = [];
$colAtendidas = [];
$colCanceladas = [];

foreach ($citasDelDia as $c) {
    if ($c['estado'] === 'Programada') {
        $colProgramadas[] = $c;
    } elseif ($c['estado'] === 'Confirmada') {
        $colConfirmadas[] = $c;
    } elseif ($c['estado'] === 'Atendida') {
        $colAtendidas[] = $c;
    } else {
        // 'Inasistencia' o 'Cancelada'
        $colCanceladas[] = $c;
    }
}

$titulo_pagina = 'Tablero Diario de Citas';
$titulo_seccion = 'Tablero Diario de Citas';
$script_modulo = 'citas.js';
require_once dirname(__DIR__) . '/common/encabezado.php';
?>

<!-- Barra Superior con Enlace a Listado General -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--color-primario); display: flex; align-items: center; gap: 0.5rem; margin: 0;">
            <i class="bi bi-calendar2-day"></i>
            <span>Tablero Diario de Atención</span>
        </h2>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="index.php" class="btn btn-secundario">
            <i class="bi bi-table"></i>
            <span>Ver Listado General de Citas</span>
        </a>
        <?php if ($rolUsuario === 'Administrador' || $rolUsuario === 'Recepcionista'): ?>
            <button type="button" class="btn btn-primario" onclick="abrirModalAgendarCita()">
                <i class="bi bi-calendar-plus"></i>
                <span>Programar Cita</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Barra de Navegación de Fecha Tipo Carrusel -->
<div class="barra-carrusel-fecha">
    <div class="carrusel-navegacion">
        <a href="?fecha=<?= $fechaAnterior ?>&id_medico=<?= $filtroIdMedico ?>" class="btn btn-sm btn-secundario" title="Día Anterior">
            <i class="bi bi-chevron-left"></i>
            <span>Día Anterior</span>
        </a>

        <?php if (!$esHoy): ?>
            <a href="?fecha=<?= $fechaActualHoy ?>&id_medico=<?= $filtroIdMedico ?>" class="btn btn-sm btn-acento" title="Ir al día actual">
                <i class="bi bi-calendar-event"></i>
                <span>HOY</span>
            </a>
        <?php endif; ?>

        <div class="carrusel-fecha-actual">
            <i class="bi bi-calendar3"></i>
            <span><?= Ayudantes::formatearFechaBolivia($fechaSeleccionada) ?></span>
            <?php if ($esHoy): ?>
                <span style="font-size: 0.75rem; background-color: var(--color-primario); color: #FFFFFF; padding: 0.1rem 0.4rem; border-radius: 4px;">HOY</span>
            <?php endif; ?>
        </div>

        <a href="?fecha=<?= $fechaSiguiente ?>&id_medico=<?= $filtroIdMedico ?>" class="btn btn-sm btn-secundario" title="Día Siguiente">
            <span>Día Siguiente</span>
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>

    <!-- Filtro Rápido de Fecha y Médico -->
    <form method="GET" action="hoy.php" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        <input type="date" name="fecha" class="control-campo" value="<?= htmlspecialchars($fechaSeleccionada) ?>" style="width: auto; padding: 0.35rem 0.6rem;">

        <?php if ($rolUsuario !== 'Medico'): ?>
            <select name="id_medico" class="control-campo" style="width: auto; padding: 0.35rem 0.6rem;">
                <option value="">-- Todos los Médicos --</option>
                <?php foreach ($listaMedicos as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $filtroIdMedico === (int)$m['id'] ? 'selected' : '' ?>>
                        Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="submit" class="btn btn-sm btn-secundario">
            <i class="bi bi-funnel"></i>
            <span>Aplicar</span>
        </button>
    </form>
</div>

<!-- ============================================================
     TABLERO KANBAN (4 COLUMNAS)
     ============================================================ -->
<div class="tablero-kanban">
    <!-- COLUMNA 1: PROGRAMADAS -->
    <div class="columna-kanban">
        <div class="columna-kanban-cabecera cabecera-programada">
            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <i class="bi bi-clock"></i>
                <span>Programadas</span>
            </div>
            <span class="columna-contador"><?= count($colProgramadas) ?></span>
        </div>

        <div class="columna-kanban-cuerpo">
            <?php if (empty($colProgramadas)): ?>
                <div class="texto-centro texto-muted p-4" style="font-size: 0.8125rem;">
                    Sin citas programadas
                </div>
            <?php else: ?>
                <?php foreach ($colProgramadas as $c): ?>
                    <div class="tarjeta-cita-kanban">
                        <div class="tarjeta-cita-horario">
                            <span><i class="bi bi-clock"></i> <?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></span>
                            <span class="tarjeta-cita-codigo"><?= htmlspecialchars($c['codigo_cita']) ?></span>
                        </div>
                        <div class="tarjeta-cita-paciente">
                            <i class="bi bi-person"></i> <?= htmlspecialchars($c['nombre_paciente']) ?>
                        </div>
                        <div class="tarjeta-cita-medico">
                            <i class="bi bi-person-badge"></i> <?= htmlspecialchars($c['nombre_medico']) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--color-acento); font-weight: 600;">
                            <?= htmlspecialchars($c['nombre_especialidad']) ?>
                        </div>
                        <?php if (!empty($c['motivo_consulta'])): ?>
                            <div class="tarjeta-cita-motivo">
                                <?= htmlspecialchars($c['motivo_consulta']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="tarjeta-cita-acciones" style="display: flex; flex-wrap: nowrap; gap: 0.25rem; justify-content: flex-end; align-items: center;">
                            <button type="button" class="btn btn-sm btn-secundario" title="Ver Detalle" onclick="verDetalleCita(<?= $c['id'] ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($rolUsuario === 'Medico'): ?>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #0284C7; padding: 0.25rem 0.4rem;" title="Ver Ficha Médica" onclick="abrirFichaMedica(<?= $c['id_paciente'] ?>)">
                                    <i class="bi bi-file-earmark-medical"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #6366F1; padding: 0.25rem 0.4rem;" title="Ver Historial Clínico" onclick="abrirHistorialPaciente(<?= $c['id_paciente'] ?>, '<?= htmlspecialchars(addslashes($c['nombre_paciente'])) ?>')">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($rolUsuario === 'Administrador' || $rolUsuario === 'Recepcionista'): ?>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #059669; padding: 0.25rem 0.4rem; white-space: nowrap;" title="Confirmar Asistencia Presencial" onclick="confirmarAsistencia(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>')">
                                    <i class="bi bi-person-check"></i> Pres.
                                </button>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #0284C7; padding: 0.25rem 0.4rem; white-space: nowrap;" title="Reprogramar Horario" onclick="abrirModalReprogramar(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>', <?= $c['id_medico'] ?>, '<?= htmlspecialchars(addslashes($c['nombre_medico'])) ?>', '<?= $c['fecha'] ?>', '<?= substr($c['hora_inicio'], 0, 5) ?>')">
                                    <i class="bi bi-calendar2-range"></i> Reprog.
                                </button>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #D97706; padding: 0.25rem 0.4rem;" title="Marcar Inasistencia" onclick="marcarInasistencia(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>')">
                                    <i class="bi bi-person-x"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: var(--color-peligro); padding: 0.25rem 0.4rem;" title="Cancelar Cita" onclick="abrirModalCancelar(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>', '<?= htmlspecialchars(addslashes($c['nombre_paciente'])) ?>', '<?= htmlspecialchars(addslashes($c['nombre_medico'])) ?>', '<?= $c['fecha'] . ' ' . substr($c['hora_inicio'], 0, 5) ?>')">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- COLUMNA 2: CONFIRMADAS -->
    <div class="columna-kanban">
        <div class="columna-kanban-cabecera cabecera-confirmada">
            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <i class="bi bi-person-check-fill"></i>
                <span>Confirmadas (En Espera)</span>
            </div>
            <span class="columna-contador"><?= count($colConfirmadas) ?></span>
        </div>

        <div class="columna-kanban-cuerpo">
            <?php if (empty($colConfirmadas)): ?>
                <div class="texto-centro texto-muted p-4" style="font-size: 0.8125rem;">
                    Sin pacientes en espera
                </div>
            <?php else: ?>
                <?php foreach ($colConfirmadas as $c): ?>
                    <div class="tarjeta-cita-kanban" style="border-left: 3px solid #059669;">
                        <div class="tarjeta-cita-horario">
                            <span style="color: #065F46;"><i class="bi bi-clock"></i> <?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></span>
                            <span class="tarjeta-cita-codigo"><?= htmlspecialchars($c['codigo_cita']) ?></span>
                        </div>
                        <div class="tarjeta-cita-paciente">
                            <i class="bi bi-person"></i> <?= htmlspecialchars($c['nombre_paciente']) ?>
                        </div>
                        <div class="tarjeta-cita-medico">
                            <i class="bi bi-person-badge"></i> <?= htmlspecialchars($c['nombre_medico']) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--color-acento); font-weight: 600;">
                            <?= htmlspecialchars($c['nombre_especialidad']) ?>
                        </div>
                        <?php if (!empty($c['tipo_sangre']) || !empty($c['alergias'])): ?>
                            <div style="font-size: 0.75rem; background-color: #FEF3C7; padding: 0.25rem 0.5rem; border-radius: 4px; color: #92400E;">
                                <strong>Grupo:</strong> <?= htmlspecialchars($c['tipo_sangre'] ?: 'No registrado') ?> | <strong>Alergias:</strong> <?= htmlspecialchars($c['alergias'] ?: 'Ninguna') ?>
                            </div>
                        <?php endif; ?>

                        <div class="tarjeta-cita-acciones" style="display: flex; flex-wrap: nowrap; gap: 0.25rem; justify-content: flex-end; align-items: center;">
                            <button type="button" class="btn btn-sm btn-secundario" title="Ver Detalle" onclick="verDetalleCita(<?= $c['id'] ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($rolUsuario === 'Medico' || $rolUsuario === 'Administrador'): ?>
                                <!-- Botón Atender: Abre modal con datos clínicos (EXCLUSIVO DEL MÉDICO) -->
                                <button type="button" class="btn btn-sm btn-primario" style="padding: 0.25rem 0.5rem; white-space: nowrap;" title="Registrar Atención Médica" onclick="abrirModalAtenderConDatos(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>', '<?= htmlspecialchars(addslashes($c['nombre_paciente'])) ?>', '<?= htmlspecialchars(addslashes($c['tipo_sangre'] ?? 'ORH+')) ?>', '<?= htmlspecialchars(addslashes($c['alergias'] ?? 'Ninguna conocida')) ?>')">
                                    <i class="bi bi-clipboard2-pulse"></i> Atender
                                </button>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #0284C7; padding: 0.25rem 0.4rem;" title="Ver Ficha Médica" onclick="abrirFichaMedica(<?= $c['id_paciente'] ?>)">
                                    <i class="bi bi-file-earmark-medical"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #6366F1; padding: 0.25rem 0.4rem;" title="Ver Historial Clínico" onclick="abrirHistorialPaciente(<?= $c['id_paciente'] ?>, '<?= htmlspecialchars(addslashes($c['nombre_paciente'])) ?>')">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($rolUsuario === 'Administrador' || $rolUsuario === 'Recepcionista'): ?>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #D97706; padding: 0.25rem 0.4rem;" title="Marcar Inasistencia" onclick="marcarInasistencia(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>')">
                                    <i class="bi bi-person-x"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: var(--color-peligro); padding: 0.25rem 0.4rem;" title="Cancelar Cita" onclick="abrirModalCancelar(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>', '<?= htmlspecialchars(addslashes($c['nombre_paciente'])) ?>', '<?= htmlspecialchars(addslashes($c['nombre_medico'])) ?>', '<?= $c['fecha'] . ' ' . substr($c['hora_inicio'], 0, 5) ?>')">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- COLUMNA 3: ATENDIDAS -->
    <div class="columna-kanban">
        <div class="columna-kanban-cabecera cabecera-atendida">
            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <i class="bi bi-check2-circle"></i>
                <span>Atendidas</span>
            </div>
            <span class="columna-contador"><?= count($colAtendidas) ?></span>
        </div>

        <div class="columna-kanban-cuerpo">
            <?php if (empty($colAtendidas)): ?>
                <div class="texto-centro texto-muted p-4" style="font-size: 0.8125rem;">
                    Sin citas atendidas hoy
                </div>
            <?php else: ?>
                <?php foreach ($colAtendidas as $c): ?>
                    <div class="tarjeta-cita-kanban" style="border-left: 3px solid #16A34A; background-color: #FAFAFA;">
                        <div class="tarjeta-cita-horario">
                            <span style="color: var(--color-primario);"><i class="bi bi-clock"></i> <?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></span>
                            <span class="insignia-estado estado-atendida" style="font-size: 0.6875rem;">Atendida</span>
                        </div>
                        <div class="tarjeta-cita-paciente">
                            <i class="bi bi-person"></i> <?= htmlspecialchars($c['nombre_paciente']) ?>
                        </div>
                        <div class="tarjeta-cita-medico">
                            <i class="bi bi-person-badge"></i> <?= htmlspecialchars($c['nombre_medico']) ?>
                        </div>
                        <?php if (!empty($c['observaciones_atencion'])): ?>
                            <div class="tarjeta-cita-motivo" style="background-color: #ECFDF5; border-color: #A7F3D0;">
                                <strong>Diagnóstico:</strong> <?= htmlspecialchars(mb_strimwidth($c['observaciones_atencion'], 0, 80, '...')) ?>
                            </div>
                        <?php endif; ?>

                        <div class="tarjeta-cita-acciones" style="display: flex; flex-wrap: nowrap; gap: 0.25rem; justify-content: flex-end; align-items: center;">
                            <button type="button" class="btn btn-sm btn-secundario" onclick="verDetalleCita(<?= $c['id'] ?>)">
                                <i class="bi bi-file-medical"></i> Notas
                            </button>
                            <?php if ($rolUsuario === 'Medico' || $rolUsuario === 'Administrador'): ?>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #0284C7; padding: 0.25rem 0.4rem;" title="Ver Ficha Médica" onclick="abrirFichaMedica(<?= $c['id_paciente'] ?>)">
                                    <i class="bi bi-file-earmark-medical"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-secundario" style="color: #6366F1; padding: 0.25rem 0.4rem;" title="Ver Historial Clínico" onclick="abrirHistorialPaciente(<?= $c['id_paciente'] ?>, '<?= htmlspecialchars(addslashes($c['nombre_paciente'])) ?>')">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- COLUMNA 4: INASISTENCIAS / CANCELADAS -->
    <div class="columna-kanban">
        <div class="columna-kanban-cabecera cabecera-cancelada">
            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <i class="bi bi-x-octagon"></i>
                <span>Inasistencias / Canceladas</span>
            </div>
            <span class="columna-contador"><?= count($colCanceladas) ?></span>
        </div>

        <div class="columna-kanban-cuerpo">
            <?php if (empty($colCanceladas)): ?>
                <div class="texto-centro texto-muted p-4" style="font-size: 0.8125rem;">
                    Sin cancelaciones ni inasistencias
                </div>
            <?php else: ?>
                <?php foreach ($colCanceladas as $c): 
                    $esCancelada = ($c['estado'] === 'Cancelada');
                ?>
                    <div class="tarjeta-cita-kanban" style="border-left: 3px solid <?= $esCancelada ? 'var(--color-peligro)' : '#D97706' ?>; opacity: 0.9;">
                        <div class="tarjeta-cita-horario">
                            <span><i class="bi bi-clock"></i> <?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></span>
                            <span class="insignia-estado <?= $esCancelada ? 'estado-cancelada' : 'estado-inasistencia' ?>" style="font-size: 0.6875rem;">
                                <?= htmlspecialchars($c['estado']) ?>
                            </span>
                        </div>
                        <div class="tarjeta-cita-paciente">
                            <i class="bi bi-person"></i> <?= htmlspecialchars($c['nombre_paciente']) ?>
                        </div>
                        <div class="tarjeta-cita-medico">
                            <i class="bi bi-person-badge"></i> <?= htmlspecialchars($c['nombre_medico']) ?>
                        </div>
                        <?php if ($esCancelada && !empty($c['motivo_cancelacion'])): ?>
                            <div class="tarjeta-cita-motivo" style="color: #991B1B; background-color: var(--color-peligro-fondo);">
                                <strong>Motivo:</strong> <?= htmlspecialchars($c['motivo_cancelacion']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="tarjeta-cita-acciones">
                            <button type="button" class="btn btn-sm btn-secundario" onclick="verDetalleCita(<?= $c['id'] ?>)">
                                <i class="bi bi-eye"></i> Detalle
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Inclusión de Modales del Flujo -->
<?php 
require_once __DIR__ . '/modal_agendar.php';
require_once __DIR__ . '/modal_reprogramar.php';
require_once __DIR__ . '/modal_cancelar.php';
require_once __DIR__ . '/modal_atender.php';
require_once __DIR__ . '/modal_detalle.php';
require_once dirname(__DIR__) . '/pacientes/modal_ficha_clinica.php';
require_once dirname(__DIR__) . '/pacientes/modal_historial.php';
require_once dirname(__DIR__) . '/common/pie.php';
?>

<script>
/**
 * Abre modal de atención mostrando alertas de sangre y alergias
 */
function abrirModalAtenderConDatos(idCita, codigoCita, paciente, tipoSangre, alergias) {
    const form = document.getElementById('formAtenderCita');
    if (form) {
        form.reset();
        limpiarErroresFormulario(form);
    }
    ocultarAlertaModal('modalAtenderCita');

    document.getElementById('atender_id_cita').value = idCita;
    document.getElementById('atender_info_cita').textContent = codigoCita + ' - Paciente: ' + paciente;

    const divClinico = document.getElementById('atender_datos_clinicos_paciente');
    const spanSangre = document.getElementById('atender_tipo_sangre');
    const spanAlergias = document.getElementById('atender_alergias');

    if (divClinico && spanSangre && spanAlergias) {
        spanSangre.textContent = tipoSangre || 'No registrado';
        spanAlergias.textContent = alergias || 'Ninguna conocida';
        divClinico.style.display = 'block';
    }

    abrirModal('modalAtenderCita');
}

/**
 * Consulta y despliega el detalle completo en modal (sin diálogo bloqueante)
 */
async function verDetalleCita(idCita) {
    const contenedor = document.getElementById('cuerpoDetalleCita');
    if (contenedor) {
        contenedor.innerHTML = '<div class="texto-centro texto-muted p-4"><i class="bi bi-hourglass-split"></i> Cargando información...</div>';
    }
    abrirModal('modalDetalleCita');

    const respuesta = await peticionAjax('hoy.php?accion=detalle&id_cita=' + idCita, 'GET');

    if (contenedor && respuesta && respuesta.exito && respuesta.detalle) {
        const d = respuesta.detalle;
        document.getElementById('detalle_titulo').textContent = 'Cita Médica ' + d.codigo_cita;

        let badgeClase = 'estado-programada';
        if (d.estado === 'Confirmada') badgeClase = 'estado-confirmada';
        if (d.estado === 'Atendida') badgeClase = 'estado-atendida';
        if (d.estado === 'Cancelada') badgeClase = 'estado-cancelada';
        if (d.estado === 'Inasistencia') badgeClase = 'estado-inasistencia';

        let html = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--color-borde);">
                <div>
                    <span style="font-size: 1.125rem; font-weight: 700; color: var(--color-primario);">${d.codigo_cita}</span>
                    <span class="texto-muted" style="margin-left: 0.5rem; font-size: 0.8125rem;">Fecha: ${d.fecha} (${d.hora_inicio.substring(0, 5)} - ${d.hora_fin.substring(0, 5)})</span>
                </div>
                <span class="insignia-estado ${badgeClase}">${d.estado}</span>
            </div>

            <div class="ficha-clinica-grid" style="margin-bottom: 1rem;">
                <div class="ficha-seccion">
                    <div class="ficha-seccion-titulo"><i class="bi bi-person"></i> Datos del Paciente</div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Nombre Completo:</span><span class="ficha-valor">${d.nombre_paciente}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Cédula (CI):</span><span class="ficha-valor">${d.ci_paciente}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Teléfono:</span><span class="ficha-valor">${d.telefono_paciente || '-'}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Grupo Sanguíneo:</span><span class="badge-tipo-sangre">${d.tipo_sangre || 'No registrado'}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Alergias:</span><span class="ficha-valor" style="color: #B91C1C;">${d.alergias || 'Ninguna conocida'}</span></div>
                </div>

                <div class="ficha-seccion">
                    <div class="ficha-seccion-titulo"><i class="bi bi-person-badge"></i> Atención Médica</div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Médico Asignado:</span><span class="ficha-valor">${d.nombre_medico}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Matrícula Profesional:</span><span class="ficha-valor">${d.matricula_medico}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Especialidad:</span><span class="ficha-valor">${d.nombre_especialidad}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Registrado Por:</span><span class="ficha-valor">${d.registrado_por || 'Sistema'}</span></div>
                </div>
            </div>

            <div class="ficha-seccion" style="margin-bottom: 0.75rem;">
                <div class="ficha-seccion-titulo"><i class="bi bi-chat-left-text"></i> Motivo de Consulta</div>
                <div style="font-size: 0.875rem; color: var(--color-texto);">${d.motivo_consulta || 'Sin motivo detallado'}</div>
            </div>
        `;

        if (d.estado === 'Atendida' && d.observaciones_atencion) {
            html += `
                <div class="ficha-seccion" style="background-color: #ECFDF5; border-color: #86EFAC;">
                    <div class="ficha-seccion-titulo" style="color: #166534;"><i class="bi bi-clipboard2-check"></i> Notas de Atención y Diagnóstico</div>
                    <div style="font-size: 0.875rem; color: #14532D; white-space: pre-wrap;">${d.observaciones_atencion}</div>
                </div>
            `;
        }

        if (d.estado === 'Cancelada' && d.motivo_cancelacion) {
            html += `
                <div class="ficha-seccion" style="background-color: var(--color-peligro-fondo); border-color: var(--color-peligro-borde);">
                    <div class="ficha-seccion-titulo" style="color: #991B1B;"><i class="bi bi-calendar-x"></i> Motivo de Cancelación</div>
                    <div style="font-size: 0.875rem; color: #7F1D1D;">${d.motivo_cancelacion}</div>
                </div>
            `;
        }

        <?php if ($rolUsuario === 'Medico' || $rolUsuario === 'Administrador'): ?>
        html += `
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--color-borde);">
                <button type="button" class="btn btn-sm btn-secundario" style="color: #0284C7;" onclick="abrirFichaMedica(${d.id_paciente})">
                    <i class="bi bi-file-earmark-medical"></i> Ver Ficha Médica
                </button>
                <button type="button" class="btn btn-sm btn-secundario" style="color: #6366F1;" onclick="abrirHistorialPaciente(${d.id_paciente}, '${(d.nombre_paciente || '').replace(/'/g, "\\'")}')">
                    <i class="bi bi-clock-history"></i> Ver Historial de Citas
                </button>
            </div>
        `;
        <?php endif; ?>

        contenedor.innerHTML = html;
    } else {
        if (contenedor) {
            contenedor.innerHTML = '<div class="alerta alerta-peligro"><i class="bi bi-exclamation-triangle-fill"></i> No se pudo cargar el detalle de la cita.</div>';
        }
    }
}
</script>
