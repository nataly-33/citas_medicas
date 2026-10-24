<?php
/**
 * Panel de Control Principal / Dashboard
 * Adaptado dinámicamente según el rol: Administrador, Recepcionista, Médico y Paciente
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

require_once dirname(__DIR__, 2) . '/configuracion/aplicacion.php';
require_once dirname(__DIR__, 2) . '/src/core/GuardiaSesion.php';
require_once dirname(__DIR__, 2) . '/src/core/Ayudantes.php';
require_once dirname(__DIR__, 2) . '/src/core/Conexion.php';
require_once dirname(__DIR__, 2) . '/src/controladores/CitaControlador.php';
require_once dirname(__DIR__, 2) . '/src/controladores/HorarioControlador.php';

use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;
use App\Nucleo\Conexion;
use App\Controladores\CitaControlador;
use App\Controladores\HorarioControlador;

GuardiaSesion::verificarAutenticacion();

$rolUsuario = $_SESSION['nombre_rol'] ?? '';
$idUsuario  = (int)($_SESSION['id_usuario'] ?? 0);
$pdo        = Conexion::obtenerConexion();
$hoy        = date('Y-m-d');

// ============================================================
// PROCESAMIENTO DE PETICIONES AJAX
// ============================================================
if (isset($_GET['accion']) && $_GET['accion'] === 'disponibilidad') {
    $horarioControlador = new HorarioControlador();
    $idMedico = (int)($_GET['id_medico'] ?? 0);
    $fecha = trim($_GET['fecha'] ?? '');
    $resultado = $horarioControlador->consultarDisponibilidad($idMedico, $fecha);
    Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
}

if (isset($_GET['accion']) && $_GET['accion'] === 'detalle') {
    $citaControlador = new CitaControlador();
    $idCita = (int)($_GET['id_cita'] ?? 0);
    $resDetalle = $citaControlador->obtenerDetalle($idCita);
    Ayudantes::responderJson($resDetalle, $resDetalle['exito'] ? 200 : 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $accion = $datos['accion'] ?? '';

    if ($accion === 'agendar') {
        $citaControlador = new CitaControlador();
        $datos['id_usuario_registro'] = $idUsuario;

        // Si es rol Paciente, forzar que la cita sea para sí mismo por seguridad
        if ($rolUsuario === 'Paciente') {
            $idPac = $_SESSION['id_paciente'] ?? null;
            if (!$idPac) {
                $stmtP = $pdo->prepare("SELECT id FROM pacientes WHERE id_usuario = :u LIMIT 1");
                $stmtP->execute([':u' => $idUsuario]);
                $idPac = (int)$stmtP->fetchColumn();
                $_SESSION['id_paciente'] = $idPac;
            }
            $datos['id_paciente'] = $idPac;
        }

        $resultado = $citaControlador->agendarCita($datos);
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }
}

// ============================================================
// MÉTRICAS Y DATOS SEGÚN EL ROL DEL USUARIO
// ============================================================
$metricas = [];
$citasProximas = [];

try {
    if ($rolUsuario === 'Administrador') {
        $totalUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = TRUE")->fetchColumn();
        $totalMedicos = (int)$pdo->query("SELECT COUNT(*) FROM medicos WHERE activo = TRUE")->fetchColumn();
        $totalEspecialidades = (int)$pdo->query("SELECT COUNT(*) FROM especialidades WHERE activa = TRUE")->fetchColumn();
        $totalPacientes = (int)$pdo->query("SELECT COUNT(*) FROM pacientes WHERE activo = TRUE")->fetchColumn();

        $metricas = [
            ['titulo' => 'Usuarios Activos', 'valor' => $totalUsuarios, 'icono' => 'bi-shield-lock', 'color' => '#002855', 'fondo' => '#E2E8F0'],
            ['titulo' => 'Plantel Médico', 'valor' => $totalMedicos, 'icono' => 'bi-person-badge', 'color' => '#1B4332', 'fondo' => '#E8F5E9'],
            ['titulo' => 'Especialidades', 'valor' => $totalEspecialidades, 'icono' => 'bi-mortarboard', 'color' => '#0284C7', 'fondo' => '#E0F2FE'],
            ['titulo' => 'Pacientes Registrados', 'valor' => $totalPacientes, 'icono' => 'bi-people', 'color' => '#2D6A4F', 'fondo' => '#F4F9F5'],
        ];

    } elseif ($rolUsuario === 'Medico') {
        $idMedicoSesion = $_SESSION['id_medico'] ?? null;
        if (!$idMedicoSesion) {
            $stmtM = $pdo->prepare("SELECT id FROM medicos WHERE id_usuario = :u LIMIT 1");
            $stmtM->execute([':u' => $idUsuario]);
            $idMedicoSesion = (int)$stmtM->fetchColumn();
            $_SESSION['id_medico'] = $idMedicoSesion;
        }

        // 1. Citas de hoy del médico
        $stmtMedHoy = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE id_medico = :m AND fecha = :hoy AND estado <> 'Cancelada'");
        $stmtMedHoy->execute([':m' => $idMedicoSesion, ':hoy' => $hoy]);
        $citasHoyMed = (int)$stmtMedHoy->fetchColumn();

        // 2. Pacientes en espera hoy (Confirmadas)
        $stmtMedConf = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE id_medico = :m AND fecha = :hoy AND estado = 'Confirmada'");
        $stmtMedConf->execute([':m' => $idMedicoSesion, ':hoy' => $hoy]);
        $pacientesEnEspera = (int)$stmtMedConf->fetchColumn();

        // 3. Consultas atendidas por el médico
        $stmtMedAten = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE id_medico = :m AND estado = 'Atendida'");
        $stmtMedAten->execute([':m' => $idMedicoSesion]);
        $consultasAtendidas = (int)$stmtMedAten->fetchColumn();

        // 4. Pacientes distintos atendidos
        $stmtMedPac = $pdo->prepare("SELECT COUNT(DISTINCT id_paciente) FROM citas_medicas WHERE id_medico = :m");
        $stmtMedPac->execute([':m' => $idMedicoSesion]);
        $pacientesAtendidos = (int)$stmtMedPac->fetchColumn();

        $metricas = [
            ['titulo' => 'Citas para Hoy', 'valor' => $citasHoyMed, 'icono' => 'bi-calendar2-day', 'color' => '#A81124', 'fondo' => '#FEE2E2'],
            ['titulo' => 'En Espera (Hoy)', 'valor' => $pacientesEnEspera, 'icono' => 'bi-person-check', 'color' => '#059669', 'fondo' => '#D1FAE5'],
            ['titulo' => 'Consultas Atendidas', 'valor' => $consultasAtendidas, 'icono' => 'bi-clipboard2-pulse', 'color' => '#0284C7', 'fondo' => '#E0F2FE'],
            ['titulo' => 'Pacientes Atendidos', 'valor' => $pacientesAtendidos, 'icono' => 'bi-people', 'color' => '#4338CA', 'fondo' => '#EEF2FF'],
        ];

        // Próximas citas del médico
        $sqlMed = "
            SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta,
                   p.id AS id_paciente, p.codigo AS codigo_paciente, (p.nombres || ' ' || p.apellidos) AS paciente_nombre, p.telefono AS paciente_telefono,
                   (m.nombres || ' ' || m.apellidos) AS medico_nombre, e.nombre AS especialidad_nombre
            FROM citas_medicas c
            JOIN pacientes p ON p.id = c.id_paciente
            JOIN medicos m ON m.id = c.id_medico
            JOIN especialidades e ON e.id = c.id_especialidad
            WHERE c.id_medico = :id_medico AND c.fecha >= :hoy
            ORDER BY c.fecha ASC, c.hora_inicio ASC
            LIMIT 10
        ";
        $stmtLista = $pdo->prepare($sqlMed);
        $stmtLista->execute([':id_medico' => $idMedicoSesion, ':hoy' => $hoy]);
        $citasProximas = $stmtLista->fetchAll();

    } elseif ($rolUsuario === 'Paciente') {
        $idPacienteSesion = $_SESSION['id_paciente'] ?? null;
        if (!$idPacienteSesion) {
            $stmtP = $pdo->prepare("SELECT id FROM pacientes WHERE id_usuario = :u LIMIT 1");
            $stmtP->execute([':u' => $idUsuario]);
            $idPacienteSesion = (int)$stmtP->fetchColumn();
            $_SESSION['id_paciente'] = $idPacienteSesion;
        }

        // 1. Próximas citas programadas
        $stmtPacProg = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE id_paciente = :p AND estado = 'Programada' AND fecha >= :hoy");
        $stmtPacProg->execute([':p' => $idPacienteSesion, ':hoy' => $hoy]);
        $misCitasProgramadas = (int)$stmtPacProg->fetchColumn();

        // 2. Citas confirmadas / en espera
        $stmtPacConf = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE id_paciente = :p AND estado = 'Confirmada' AND fecha >= :hoy");
        $stmtPacConf->execute([':p' => $idPacienteSesion, ':hoy' => $hoy]);
        $misCitasConfirmadas = (int)$stmtPacConf->fetchColumn();

        // 3. Consultas atendidas completadas
        $stmtPacAten = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE id_paciente = :p AND estado = 'Atendida'");
        $stmtPacAten->execute([':p' => $idPacienteSesion]);
        $misCitasAtendidas = (int)$stmtPacAten->fetchColumn();

        // 4. Total historial de citas
        $stmtPacTot = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE id_paciente = :p");
        $stmtPacTot->execute([':p' => $idPacienteSesion]);
        $misCitasTotal = (int)$stmtPacTot->fetchColumn();

        $metricas = [
            ['titulo' => 'Mis Citas Programadas', 'valor' => $misCitasProgramadas, 'icono' => 'bi-clock-history', 'color' => '#0284C7', 'fondo' => '#E0F2FE'],
            ['titulo' => 'En Espera Hoy', 'valor' => $misCitasConfirmadas, 'icono' => 'bi-person-check', 'color' => '#059669', 'fondo' => '#D1FAE5'],
            ['titulo' => 'Consultas Atendidas', 'valor' => $misCitasAtendidas, 'icono' => 'bi-clipboard2-check', 'color' => '#16A34A', 'fondo' => '#DCFCE7'],
            ['titulo' => 'Historial de Citas', 'valor' => $misCitasTotal, 'icono' => 'bi-folder2-open', 'color' => '#4338CA', 'fondo' => '#EEF2FF'],
        ];

        // Próximas citas del paciente
        $sqlPac = "
            SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta,
                   p.id AS id_paciente, p.codigo AS codigo_paciente, (p.nombres || ' ' || p.apellidos) AS paciente_nombre, p.telefono AS paciente_telefono,
                   (m.nombres || ' ' || m.apellidos) AS medico_nombre, e.nombre AS especialidad_nombre
            FROM citas_medicas c
            JOIN pacientes p ON p.id = c.id_paciente
            JOIN medicos m ON m.id = c.id_medico
            JOIN especialidades e ON e.id = c.id_especialidad
            WHERE c.id_paciente = :id_paciente AND c.fecha >= :hoy
            ORDER BY c.fecha ASC, c.hora_inicio ASC
            LIMIT 10
        ";
        $stmtLista = $pdo->prepare($sqlPac);
        $stmtLista->execute([':id_paciente' => $idPacienteSesion, ':hoy' => $hoy]);
        $citasProximas = $stmtLista->fetchAll();

    } else {
        // Recepcionista (Operador general)
        $totalCitasHoy = (int)$pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE fecha = :hoy")->execute([':hoy' => $hoy]) ? $pdo->query("SELECT COUNT(*) FROM citas_medicas WHERE fecha = '{$hoy}'")->fetchColumn() : 0;
        $citasProgramadas = (int)$pdo->query("SELECT COUNT(*) FROM citas_medicas WHERE estado = 'Programada'")->fetchColumn();
        $citasAtendidas = (int)$pdo->query("SELECT COUNT(*) FROM citas_medicas WHERE estado = 'Atendida'")->fetchColumn();
        $totalPacientes = (int)$pdo->query("SELECT COUNT(*) FROM pacientes WHERE activo = TRUE")->fetchColumn();

        $metricas = [
            ['titulo' => 'Citas para Hoy', 'valor' => $totalCitasHoy, 'icono' => 'bi-calendar-check', 'color' => '#A81124', 'fondo' => '#FEE2E2'],
            ['titulo' => 'Programadas', 'valor' => $citasProgramadas, 'icono' => 'bi-clock-history', 'color' => '#0284C7', 'fondo' => '#E0F2FE'],
            ['titulo' => 'Atendidas', 'valor' => $citasAtendidas, 'icono' => 'bi-clipboard2-pulse', 'color' => '#16A34A', 'fondo' => '#D8F3DC'],
            ['titulo' => 'Pacientes Activos', 'valor' => $totalPacientes, 'icono' => 'bi-people', 'color' => '#2D6A4F', 'fondo' => '#F4F9F5'],
        ];

        // Próximas citas generales
        $sqlRec = "
            SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta,
                   p.id AS id_paciente, p.codigo AS codigo_paciente, (p.nombres || ' ' || p.apellidos) AS paciente_nombre, p.telefono AS paciente_telefono,
                   (m.nombres || ' ' || m.apellidos) AS medico_nombre, e.nombre AS especialidad_nombre
            FROM citas_medicas c
            JOIN pacientes p ON p.id = c.id_paciente
            JOIN medicos m ON m.id = c.id_medico
            JOIN especialidades e ON e.id = c.id_especialidad
            WHERE c.fecha >= :hoy
            ORDER BY c.fecha ASC, c.hora_inicio ASC
            LIMIT 10
        ";
        $stmtLista = $pdo->prepare($sqlRec);
        $stmtLista->execute([':hoy' => $hoy]);
        $citasProximas = $stmtLista->fetchAll();
    }

    // Listas para modal de agendar (utilizadas por Paciente y Recepcionista)
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

} catch (\Exception $e) {
    // Manejo de contingencia
}

$titulo_pagina = 'Panel de Control';
$titulo_seccion = 'Panel de Control';
$script_modulo = 'citas.js';
require_once dirname(__DIR__) . '/parciales/encabezado.php';
?>

<!-- ============================================================
     TARJETAS MÉTRICAS SUPERIORES ADAPTATIVAS
     ============================================================ -->
<div class="tarjetas-metricas">
    <?php foreach ($metricas as $m): ?>
        <div class="tarjeta-metrica">
            <div class="metrica-info">
                <span class="metrica-titulo"><?= htmlspecialchars($m['titulo']) ?></span>
                <span class="metrica-valor"><?= htmlspecialchars((string)$m['valor']) ?></span>
            </div>
            <div class="metrica-icono" style="color: <?= $m['color'] ?>; background-color: <?= $m['fondo'] ?>;">
                <i class="bi <?= $m['icono'] ?>"></i>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($rolUsuario === 'Administrador'): ?>
    <!-- Módulos de Administración y Supervisión del Sistema -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-top: 0.5rem;">
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <h3 class="tarjeta-titulo">
                    <i class="bi bi-shield-lock"></i>
                    <span>Gestión de Usuarios y Roles</span>
                </h3>
            </div>
            <div class="tarjeta-cuerpo">
                <p style="color: var(--color-texto-muted); font-size: 0.875rem; margin-bottom: 1.25rem;">
                    Crear y administrar cuentas de operadores, recepcionistas, médicos y pacientes con credenciales de correo y contraseña institucional.
                </p>
                <a href="<?= URL_BASE ?>/vistas/usuarios/index.php" class="btn btn-primario" style="width: 100%;">
                    <i class="bi bi-arrow-right"></i>
                    <span>Administrar Usuarios</span>
                </a>
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <h3 class="tarjeta-titulo">
                    <i class="bi bi-person-badge"></i>
                    <span>Médicos y Especialidades</span>
                </h3>
            </div>
            <div class="tarjeta-cuerpo">
                <p style="color: var(--color-texto-muted); font-size: 0.875rem; margin-bottom: 1.25rem;">
                    Consultar plantilla médica, registrar especialidades médicas normalizadas y verificar jornadas de atención de los profesionales.
                </p>
                <a href="<?= URL_BASE ?>/vistas/medicos/index.php" class="btn btn-primario" style="width: 100%;">
                    <i class="bi bi-arrow-right"></i>
                    <span>Médicos y Especialidades</span>
                </a>
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <h3 class="tarjeta-titulo">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Reportes y Estadísticas</span>
                </h3>
            </div>
            <div class="tarjeta-cuerpo">
                <p style="color: var(--color-texto-muted); font-size: 0.875rem; margin-bottom: 1.25rem;">
                    Consultar métricas globales del centro de salud, estadísticas de rendimiento y exportar el padrón general en formato PDF o CSV.
                </p>
                <a href="<?= URL_BASE ?>/vistas/reportes/index.php" class="btn btn-secundario" style="width: 100%;">
                    <i class="bi bi-arrow-right"></i>
                    <span>Consultar Reportes</span>
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- ============================================================
         TABLA DE PRÓXIMAS CITAS MÉDICAS ADAPTADA POR ROL
         ============================================================ -->
    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2 class="tarjeta-titulo">
                <i class="bi bi-calendar3"></i>
                <?php if ($rolUsuario === 'Paciente'): ?>
                    <span>Mis Próximas Citas Médicas</span>
                <?php elseif ($rolUsuario === 'Medico'): ?>
                    <span>Agenda de Pacientes Próximos</span>
                <?php else: ?>
                    <span>Próximas Citas Médicas Agendadas</span>
                <?php endif; ?>
            </h2>

            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <?php if ($rolUsuario === 'Paciente'): ?>
                    <!-- Botón Crear / Agendar Cita exclusivo para el Paciente -->
                    <button type="button" class="btn btn-sm btn-primario" onclick="abrirModalAgendarCita()" title="Agendar una nueva cita médica">
                        <i class="bi bi-calendar-plus"></i>
                        <span>Agendar Cita</span>
                    </button>
                    <a href="<?= URL_BASE ?>/vistas/citas/index.php" class="btn btn-sm btn-secundario" title="Ver mis citas agendadas">
                        <i class="bi bi-calendar3"></i>
                        <span>Mis Citas</span>
                    </a>
                    <a href="<?= URL_BASE ?>/vistas/perfil/index.php" class="btn btn-sm btn-secundario" title="Ver mi ficha médica">
                        <i class="bi bi-person-vcard"></i>
                        <span>Mi Ficha</span>
                    </a>

                <?php elseif ($rolUsuario === 'Medico'): ?>
                    <a href="<?= URL_BASE ?>/vistas/citas/hoy.php" class="btn btn-sm btn-acento" title="Ver mi agenda de hoy en tablero interactivo">
                        <i class="bi bi-calendar2-day"></i>
                        <span>Mi Agenda de Hoy</span>
                    </a>
                    <a href="<?= URL_BASE ?>/vistas/citas/index.php" class="btn btn-sm btn-secundario" title="Ver listado completo de mis citas">
                        <i class="bi bi-calendar-check"></i>
                        <span>Mis Citas</span>
                    </a>
                    <a href="<?= URL_BASE ?>/vistas/pacientes/index.php" class="btn btn-sm btn-secundario" title="Buscar pacientes y fichas">
                        <i class="bi bi-people"></i>
                        <span>Buscar Pacientes</span>
                    </a>

                <?php elseif ($rolUsuario === 'Recepcionista'): ?>
                    <a href="<?= URL_BASE ?>/vistas/citas/hoy.php" class="btn btn-sm btn-acento" title="Ver tablero de citas de hoy">
                        <i class="bi bi-calendar2-day"></i>
                        <span>Tablero Hoy</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-primario" onclick="abrirModalAgendarCita()" title="Programar nueva cita">
                        <i class="bi bi-plus-lg"></i>
                        <span>Programar Cita</span>
                    </button>
                    <a href="<?= URL_BASE ?>/vistas/citas/index.php" class="btn btn-sm btn-secundario" title="Gestionar todas las citas">
                        <i class="bi bi-calendar3"></i>
                        <span>Gestionar Citas</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="tarjeta-cuerpo" style="padding: 0;">
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
                        <?php if (empty($citasProximas)): ?>
                            <tr>
                                <td colspan="8" class="texto-centro texto-muted" style="padding: 2.5rem;">
                                    <i class="bi bi-info-circle"></i> 
                                    <?= $rolUsuario === 'Paciente' ? 'No tiene citas próximas agendadas. ¡Haga clic en "Agendar Cita" para programar su atención médica!' : 'No hay citas próximas registradas.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($citasProximas as $cita): 
                                $claseBadge = 'estado-programada';
                                if ($cita['estado'] === 'Confirmada') $claseBadge = 'estado-confirmada';
                                if ($cita['estado'] === 'Atendida') $claseBadge = 'estado-atendida';
                                if ($cita['estado'] === 'Cancelada') $claseBadge = 'estado-cancelada';
                                if ($cita['estado'] === 'Inasistencia') $claseBadge = 'estado-inasistencia';
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($cita['codigo_cita']) ?></strong></td>
                                    <td><?= Ayudantes::formatearFechaBolivia($cita['fecha']) ?></td>
                                    <td><?= substr($cita['hora_inicio'], 0, 5) ?> - <?= substr($cita['hora_fin'], 0, 5) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($cita['paciente_nombre']) ?></div>
                                        <?php if (!empty($cita['paciente_telefono'])): ?>
                                            <div class="texto-muted" style="font-size: 0.75rem;"><i class="bi bi-telephone"></i> <?= htmlspecialchars($cita['paciente_telefono']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($cita['medico_nombre']) ?></td>
                                    <td><?= htmlspecialchars($cita['especialidad_nombre']) ?></td>
                                    <td>
                                        <span class="insignia-estado <?= $claseBadge ?>">
                                            <?= htmlspecialchars($cita['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="texto-centro">
                                        <div class="acciones-fila" style="justify-content: center;">
                                            <!-- Ver Detalle en Modal -->
                                            <button type="button" class="btn-accion" title="Ver Detalle de Cita" onclick="verDetalleCita(<?= $cita['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            <?php if ($rolUsuario === 'Medico'): ?>
                                                <button type="button" class="btn-accion" style="color: #0284C7;" title="Ver Ficha Médica" onclick="abrirFichaMedica(<?= $cita['id_paciente'] ?>)">
                                                    <i class="bi bi-file-earmark-medical"></i>
                                                </button>
                                                <button type="button" class="btn-accion btn-accion-historial" title="Ver Historial Clínico" onclick="abrirHistorialPaciente(<?= $cita['id_paciente'] ?>, '<?= htmlspecialchars(addslashes($cita['paciente_nombre'])) ?>')">
                                                    <i class="bi bi-clock-history"></i>
                                                </button>
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
    </div>
<?php endif; ?>

<!-- Inclusión de Modales Requeridos -->
<?php 
require_once dirname(__DIR__) . '/citas/modal_agendar.php';
require_once dirname(__DIR__) . '/citas/modal_detalle.php';
require_once dirname(__DIR__) . '/pacientes/modal_ficha_clinica.php';
require_once dirname(__DIR__) . '/pacientes/modal_historial.php';
require_once dirname(__DIR__) . '/parciales/pie.php';
?>

<script>
/**
 * Consulta y despliega el detalle completo en modal
 */
async function verDetalleCita(idCita) {
    const contenedor = document.getElementById('cuerpoDetalleCita');
    if (contenedor) {
        contenedor.innerHTML = '<div class="texto-centro texto-muted p-4"><i class="bi bi-hourglass-split"></i> Cargando información...</div>';
    }
    abrirModal('modalDetalleCita');

    const respuesta = await peticionAjax('<?= URL_BASE ?>/vistas/citas/index.php?accion=detalle&id_cita=' + idCita, 'GET');

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
