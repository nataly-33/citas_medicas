<?php
/**
 * Vista Principal de Gestión de Citas Médicas (CU07, CU08, CU09, CU10, CU11)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

require_once dirname(__DIR__, 2) . '/configuracion/aplicacion.php';
require_once dirname(__DIR__, 2) . '/src/core/GuardiaSesion.php';
require_once dirname(__DIR__, 2) . '/src/core/Ayudantes.php';
require_once dirname(__DIR__, 2) . '/src/core/Conexion.php';
require_once dirname(__DIR__, 2) . '/src/controladores/CitaControlador.php';

use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;
use App\Nucleo\Conexion;
use App\Controladores\CitaControlador;

GuardiaSesion::verificarAutenticacion();
GuardiaSesion::verificarRol(['Administrador', 'Recepcionista', 'Medico', 'Paciente']);

$rolUsuario = $_SESSION['nombre_rol'] ?? '';
$idUsuario = $_SESSION['id_usuario'] ?? 0;
$pdo = Conexion::obtenerConexion();
$citaControlador = new CitaControlador();

// ============================================================
// PROCESAMIENTO DE PETICIONES AJAX Y ACCIONES
// ============================================================

// Detalle completo de cita para modal
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle') {
    $idCita = (int)($_GET['id_cita'] ?? 0);
    $resDetalle = $citaControlador->obtenerDetalle($idCita);
    Ayudantes::responderJson($resDetalle, $resDetalle['exito'] ? 200 : 404);
}

// Consulta Dinámica de Turnos Disponibles
if (isset($_GET['accion']) && $_GET['accion'] === 'disponibilidad') {
    $idMedico = (int)($_GET['id_medico'] ?? 0);
    $fecha = trim($_GET['fecha'] ?? '');

    if (!$idMedico || empty($fecha)) {
        Ayudantes::responderJson(['exito' => false, 'turnos' => []]);
    }

    $hoy = date('Y-m-d');
    if ($fecha < $hoy) {
        Ayudantes::responderJson(['exito' => false, 'mensaje' => 'La fecha no puede ser anterior a la fecha actual.', 'turnos' => []], 400);
    }

    $diaSemana = Ayudantes::obtenerDiaSemana($fecha);

    try {
        $stmtH = $pdo->prepare("
            SELECT hora_inicio, hora_fin, duracion_minutos 
            FROM horarios_atencion 
            WHERE id_medico = :id_medico AND dia_semana = :dia AND activo = TRUE
        ");
        $stmtH->execute([':id_medico' => $idMedico, ':dia' => $diaSemana]);
        $jornada = $stmtH->fetch();

        if (!$jornada) {
            Ayudantes::responderJson(['exito' => true, 'turnos' => []]);
        }

        $duracion = (int)($jornada['duracion_minutos'] ?? 30);
        $horaInicioSeg = strtotime($jornada['hora_inicio']);
        $horaFinSeg = strtotime($jornada['hora_fin']);

        $stmtC = $pdo->prepare("
            SELECT hora_inicio, hora_fin 
            FROM citas_medicas 
            WHERE id_medico = :id_medico AND fecha = :fecha AND estado <> 'Cancelada'
        ");
        $stmtC->execute([':id_medico' => $idMedico, ':fecha' => $fecha]);
        $citasOcupadas = $stmtC->fetchAll();

        $turnosDisponibles = [];
        $actual = $horaInicioSeg;

        while ($actual + ($duracion * 60) <= $horaFinSeg) {
            $hIni = date('H:i:s', $actual);
            $hFin = date('H:i:s', $actual + ($duracion * 60));

            $ocupado = false;
            foreach ($citasOcupadas as $ocup) {
                if ($hIni < $ocup['hora_fin'] && $hFin > $ocup['hora_inicio']) {
                    $ocupado = true;
                    break;
                }
            }

            if (!$ocupado) {
                $turnosDisponibles[] = [
                    'hora_inicio' => $hIni,
                    'hora_fin'    => $hFin
                ];
            }

            $actual += ($duracion * 60);
        }

        Ayudantes::responderJson(['exito' => true, 'turnos' => $turnosDisponibles]);
    } catch (\Exception $e) {
        Ayudantes::responderJson(['exito' => false, 'mensaje' => 'Error al calcular disponibilidad: ' . $e->getMessage()], 500);
    }
}

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $accion = $datos['accion'] ?? '';

    if ($accion === 'agendar') {
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

    if ($accion === 'cancelar') {
        $idCita = (int)($datos['id_cita'] ?? 0);
        $motivo = trim($datos['motivo_cancelacion'] ?? '');
        $resultado = $citaControlador->cancelarCita($idCita, $motivo);
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }

    if ($accion === 'atender') {
        if ($rolUsuario !== 'Medico' && $rolUsuario !== 'Administrador') {
            Ayudantes::responderJson([
                'exito' => false,
                'mensaje' => 'Operación no permitida: Solo el profesional médico asignado puede registrar la atención clínica.'
            ], 403);
        }
        $idCita = (int)($datos['id_cita'] ?? 0);
        $obs = trim($datos['observaciones_atencion'] ?? '');
        $resultado = $citaControlador->marcarAtendida($idCita, $obs);
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }

    if ($accion === 'confirmar_asistencia') {
        $idCita = (int)($datos['id_cita'] ?? 0);
        try {
            $stmtConf = $pdo->prepare("UPDATE citas_medicas SET estado = 'Confirmada', fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = :id");
            $stmtConf->execute([':id' => $idCita]);
            Ayudantes::responderJson(['exito' => true, 'mensaje' => 'Asistencia presencial confirmada.']);
        } catch (\Exception $e) {
            Ayudantes::responderJson(['exito' => false, 'mensaje' => 'Error al confirmar asistencia.'], 500);
        }
    }

    if ($accion === 'inasistencia') {
        $idCita = (int)($datos['id_cita'] ?? 0);
        $resultado = $citaControlador->marcarInasistencia($idCita);
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }
}

// ============================================================
// CARGA DE LISTAS PARA MODALES Y FILTROS
// ============================================================
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

// ============================================================
// FILTROS AVANZADOS Y PAGINACIÓN DE 15 FILAS
// ============================================================
$fechaDesde = trim($_GET['fecha_desde'] ?? '');
$fechaHasta = trim($_GET['fecha_hasta'] ?? '');
$filtroIdMedico = (int)($_GET['id_medico'] ?? 0);
$filtroEstado = trim($_GET['estado'] ?? '');
$buscar = trim($_GET['buscar'] ?? '');
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$limite_registros = REGISTROS_POR_PAGINA;
$desplazamiento = ($pagina_actual - 1) * $limite_registros;

$whereClause = " WHERE 1=1 ";
$params = [];

if ($rolUsuario === 'Medico') {
    $whereClause .= " AND m.id_usuario = :id_usuario_med ";
    $params[':id_usuario_med'] = $idUsuario;
}

if ($rolUsuario === 'Paciente') {
    $whereClause .= " AND p.id_usuario = :id_usuario_pac ";
    $params[':id_usuario_pac'] = $idUsuario;
}

if (!empty($fechaDesde) && !empty($fechaHasta)) {
    $whereClause .= " AND c.fecha BETWEEN :f_desde AND :f_hasta ";
    $params[':f_desde'] = $fechaDesde;
    $params[':f_hasta'] = $fechaHasta;
} elseif (!empty($fechaDesde)) {
    $whereClause .= " AND c.fecha >= :f_desde ";
    $params[':f_desde'] = $fechaDesde;
} elseif (!empty($fechaHasta)) {
    $whereClause .= " AND c.fecha <= :f_hasta ";
    $params[':f_hasta'] = $fechaHasta;
}

if ($filtroIdMedico > 0) {
    $whereClause .= " AND c.id_medico = :id_medico ";
    $params[':id_medico'] = $filtroIdMedico;
}

if (!empty($filtroEstado)) {
    $whereClause .= " AND c.estado = :estado ";
    $params[':estado'] = $filtroEstado;
}

if (!empty($buscar)) {
    $whereClause .= " AND (c.codigo_cita ILIKE :buscar OR p.nombres ILIKE :buscar OR p.apellidos ILIKE :buscar OR p.ci ILIKE :buscar) ";
    $params[':buscar'] = "%{$buscar}%";
}

$stmtTotal = $pdo->prepare("
    SELECT COUNT(*) 
    FROM citas_medicas c
    JOIN pacientes p ON p.id = c.id_paciente
    JOIN medicos m ON m.id = c.id_medico
    JOIN especialidades e ON e.id = c.id_especialidad
    {$whereClause}
");
$stmtTotal->execute($params);
$total_registros = (int)$stmtTotal->fetchColumn();

// Ordenamiento con prioridad de estado y cronológico: Fecha DESC, Hora ASC
$sqlCitas = "
    SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta,
           c.motivo_cancelacion, c.observaciones_atencion, c.id_medico,
           p.id AS id_paciente, p.codigo AS codigo_paciente, p.ci AS ci_paciente, 
           (p.nombres || ' ' || p.apellidos) AS paciente_nombre, p.telefono AS paciente_telefono,
           p.tipo_sangre, p.alergias,
           (m.nombres || ' ' || m.apellidos) AS medico_nombre, m.registro_profesional AS medico_matricula,
           e.nombre AS especialidad_nombre
    FROM citas_medicas c
    JOIN pacientes p ON p.id = c.id_paciente
    JOIN medicos m ON m.id = c.id_medico
    JOIN especialidades e ON e.id = c.id_especialidad
    {$whereClause}
    ORDER BY c.fecha DESC, c.hora_inicio ASC
    LIMIT {$limite_registros} OFFSET {$desplazamiento}
";
$stmtCitas = $pdo->prepare($sqlCitas);
$stmtCitas->execute($params);
$citas = $stmtCitas->fetchAll();

$titulo_pagina = 'Gestión de Citas Médicas';
$titulo_seccion = 'Gestión de Citas Médicas';
$script_modulo = 'citas.js';
require_once dirname(__DIR__) . '/parciales/encabezado.php';
?>

<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <h2 class="tarjeta-titulo">
            <i class="bi bi-calendar3"></i>
            <span>Listado de Citas Médicas</span>
        </h2>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <!-- Botón Destacado al Tablero Diario -->
            <a href="hoy.php" class="btn btn-acento" title="Ver citas del día actual en tablero">
                <i class="bi bi-calendar2-day"></i>
                <span>Ver Citas de Hoy (Tablero)</span>
            </a>

            <?php if ($rolUsuario === 'Administrador' || $rolUsuario === 'Recepcionista'): ?>
                <button type="button" class="btn btn-primario" onclick="abrirModalAgendarCita()">
                    <i class="bi bi-calendar-plus"></i>
                    <span>Programar Cita</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="tarjeta-cuerpo">
        <!-- Barra de Filtros con Rango de Fechas -->
        <form method="GET" action="index.php" class="barra-filtros">
            <div style="min-width: 140px;">
                <label class="etiqueta-campo" style="font-size: 0.75rem;">Desde:</label>
                <input type="date" name="fecha_desde" class="control-campo" value="<?= htmlspecialchars($fechaDesde) ?>">
            </div>

            <div style="min-width: 140px;">
                <label class="etiqueta-campo" style="font-size: 0.75rem;">Hasta:</label>
                <input type="date" name="fecha_hasta" class="control-campo" value="<?= htmlspecialchars($fechaHasta) ?>">
            </div>

            <?php if ($rolUsuario !== 'Medico'): ?>
            <div style="min-width: 170px;">
                <label class="etiqueta-campo" style="font-size: 0.75rem;">Médico:</label>
                <select name="id_medico" class="control-campo">
                    <option value="">-- Todos los Médicos --</option>
                    <?php foreach ($listaMedicos as $med): ?>
                        <option value="<?= $med['id'] ?>" <?= $filtroIdMedico === (int)$med['id'] ? 'selected' : '' ?>>
                            Dr(a). <?= htmlspecialchars($med['nombres'] . ' ' . $med['apellidos']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div style="min-width: 150px;">
                <label class="etiqueta-campo" style="font-size: 0.75rem;">Estado:</label>
                <select name="estado" class="control-campo">
                    <option value="">-- Todos los Estados --</option>
                    <option value="Programada" <?= $filtroEstado === 'Programada' ? 'selected' : '' ?>>Programada</option>
                    <option value="Confirmada" <?= $filtroEstado === 'Confirmada' ? 'selected' : '' ?>>Confirmada</option>
                    <option value="Atendida" <?= $filtroEstado === 'Atendida' ? 'selected' : '' ?>>Atendida</option>
                    <option value="Cancelada" <?= $filtroEstado === 'Cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    <option value="Inasistencia" <?= $filtroEstado === 'Inasistencia' ? 'selected' : '' ?>>Inasistencia</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 160px;">
                <label class="etiqueta-campo" style="font-size: 0.75rem;">Buscar:</label>
                <input type="text" name="buscar" class="control-campo" 
                       placeholder="Código, paciente, CI..." 
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>

            <div style="align-self: flex-end;">
                <button type="submit" class="btn btn-secundario">
                    <i class="bi bi-search"></i>
                    <span>Buscar</span>
                </button>

                <?php if (!empty($fechaDesde) || !empty($fechaHasta) || $filtroIdMedico > 0 || !empty($filtroEstado) || !empty($buscar)): ?>
                    <a href="index.php" class="btn btn-secundario" title="Limpiar Filtros">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Tabla de Citas Médicas (15 Filas) -->
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
                        <th class="texto-centro">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($citas)): ?>
                        <tr>
                            <td colspan="8" class="texto-centro texto-muted" style="padding: 2.5rem;">
                                <i class="bi bi-info-circle"></i> No se encontraron citas médicas con los criterios especificados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($citas as $c): 
                            $badgeClase = 'estado-programada';
                            if ($c['estado'] === 'Confirmada') $badgeClase = 'estado-confirmada';
                            if ($c['estado'] === 'Atendida') $badgeClase = 'estado-atendida';
                            if ($c['estado'] === 'Cancelada') $badgeClase = 'estado-cancelada';
                            if ($c['estado'] === 'Inasistencia') $badgeClase = 'estado-inasistencia';
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['codigo_cita']) ?></strong></td>
                                <td><?= Ayudantes::formatearFechaBolivia($c['fecha']) ?></td>
                                <td><?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($c['paciente_nombre']) ?></div>
                                    <div class="texto-muted" style="font-size: 0.75rem;">CI: <?= htmlspecialchars($c['ci_paciente']) ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($c['medico_nombre']) ?></div>
                                    <div class="texto-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($c['medico_matricula']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($c['especialidad_nombre']) ?></td>
                                <td>
                                    <span class="insignia-estado <?= $badgeClase ?>">
                                        <?= htmlspecialchars($c['estado']) ?>
                                    </span>
                                </td>
                                <td class="texto-centro">
                                    <div class="acciones-fila" style="justify-content: center;">
                                        <!-- Botón de Ojo: Ver Detalle Completo en Modal (NUNCA usa alert) -->
                                        <button type="button" class="btn-accion" title="Ver Detalle de Cita" onclick="verDetalleCita(<?= $c['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if ($rolUsuario === 'Medico' || $rolUsuario === 'Administrador'): ?>
                                            <button type="button" class="btn-accion" style="color: #0284C7;" title="Ver Ficha Médica" onclick="abrirFichaMedica(<?= $c['id_paciente'] ?>)">
                                                <i class="bi bi-file-earmark-medical"></i>
                                            </button>
                                            <button type="button" class="btn-accion btn-accion-historial" title="Ver Historial Clínico" onclick="abrirHistorialPaciente(<?= $c['id_paciente'] ?>, '<?= htmlspecialchars(addslashes($c['paciente_nombre'])) ?>')">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($c['estado'] === 'Programada'): ?>
                                            <?php if ($rolUsuario === 'Administrador' || $rolUsuario === 'Recepcionista'): ?>
                                                <!-- Confirmar Asistencia Presencial -->
                                                <button type="button" class="btn-accion" style="color: #059669;" 
                                                        title="Confirmar Asistencia Presencial" 
                                                        onclick="confirmarAsistencia(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>')">
                                                    <i class="bi bi-person-check"></i>
                                                </button>

                                                <!-- Reprogramar Cita (Solo en estado Programada) -->
                                                <button type="button" class="btn-accion btn-accion-editar" 
                                                        title="Reprogramar Horario de Cita" 
                                                        onclick="abrirModalReprogramar(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>', <?= $c['id_medico'] ?>, '<?= htmlspecialchars(addslashes($c['medico_nombre'])) ?>', '<?= $c['fecha'] ?>', '<?= substr($c['hora_inicio'], 0, 5) ?>')">
                                                    <i class="bi bi-calendar2-range"></i>
                                                </button>

                                                <!-- Registrar Inasistencia -->
                                                <button type="button" class="btn-accion" style="color: #D97706;" 
                                                        title="Registrar Inasistencia" 
                                                        onclick="marcarInasistencia(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>')">
                                                    <i class="bi bi-person-x"></i>
                                                </button>

                                                <!-- Cancelar Cita -->
                                                <button type="button" class="btn-accion btn-accion-eliminar" 
                                                        title="Cancelar Cita" 
                                                        onclick="abrirModalCancelar(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>', '<?= htmlspecialchars(addslashes($c['paciente_nombre'])) ?>', '<?= htmlspecialchars(addslashes($c['medico_nombre'])) ?>', '<?= $c['fecha'] . ' ' . substr($c['hora_inicio'], 0, 5) ?>')">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($c['estado'] === 'Confirmada'): ?>
                                            <?php if ($rolUsuario === 'Medico' || $rolUsuario === 'Administrador'): ?>
                                                <!-- Atender Cita (EXCLUSIVO DEL MÉDICO) -->
                                                <button type="button" class="btn-accion btn-accion-atender" 
                                                        title="Registrar Atención Médica" 
                                                        onclick="abrirModalAtender(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>', '<?= htmlspecialchars(addslashes($c['paciente_nombre'])) ?>')">
                                                    <i class="bi bi-clipboard2-check"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($rolUsuario === 'Administrador' || $rolUsuario === 'Recepcionista'): ?>
                                                <!-- Registrar Inasistencia si confirmó pero no entró / se retiró -->
                                                <button type="button" class="btn-accion" style="color: #D97706;" 
                                                        title="Registrar Inasistencia" 
                                                        onclick="marcarInasistencia(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>')">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                                <!-- Cancelar Cita -->
                                                <button type="button" class="btn-accion btn-accion-eliminar" 
                                                        title="Cancelar Cita" 
                                                        onclick="abrirModalCancelar(<?= $c['id'] ?>, '<?= $c['codigo_cita'] ?>', '<?= htmlspecialchars(addslashes($c['paciente_nombre'])) ?>', '<?= htmlspecialchars(addslashes($c['medico_nombre'])) ?>', '<?= $c['fecha'] . ' ' . substr($c['hora_inicio'], 0, 5) ?>')">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación Compartida de 15 Filas a la Derecha -->
    <?php require dirname(__DIR__) . '/parciales/paginacion.php'; ?>
</div>

<!-- Inclusión de Modales del Flujo de Citas -->
<?php 
require_once __DIR__ . '/modal_agendar.php';
require_once __DIR__ . '/modal_reprogramar.php';
require_once __DIR__ . '/modal_cancelar.php';
require_once __DIR__ . '/modal_atender.php';
require_once __DIR__ . '/modal_detalle.php';
require_once dirname(__DIR__) . '/pacientes/modal_ficha_clinica.php';
require_once dirname(__DIR__) . '/pacientes/modal_historial.php';
require_once dirname(__DIR__) . '/parciales/pie.php';
?>

<script>
/**
 * Consulta y despliega el detalle completo en modal (sin diálogo bloqueante)
 */
async function verDetalleCita(idCita) {
    const contenedor = document.getElementById('cuerpoDetalleCita');
    if (contenedor) {
        contenedor.innerHTML = '<div class="texto-centro texto-muted p-4"><i class="bi bi-hourglass-split"></i> Cargando información...</div>';
    }
    abrirModal('modalDetalleCita');

    const respuesta = await peticionAjax('index.php?accion=detalle&id_cita=' + idCita, 'GET');

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

        contenedor.innerHTML = html;
    } else {
        if (contenedor) {
            contenedor.innerHTML = '<div class="alerta alerta-peligro"><i class="bi bi-exclamation-triangle-fill"></i> No se pudo cargar el detalle de la cita.</div>';
        }
    }
}
</script>
