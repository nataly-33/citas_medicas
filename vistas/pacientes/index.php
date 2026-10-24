<?php
/**
 * Vista Principal de Pacientes (CU03, CU12)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

require_once dirname(__DIR__, 2) . '/configuracion/aplicacion.php';
require_once dirname(__DIR__, 2) . '/src/core/GuardiaSesion.php';
require_once dirname(__DIR__, 2) . '/src/core/Ayudantes.php';
require_once dirname(__DIR__, 2) . '/src/core/Conexion.php';
require_once dirname(__DIR__, 2) . '/src/controladores/PacienteControlador.php';

use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;
use App\Nucleo\Conexion;
use App\Controladores\PacienteControlador;

GuardiaSesion::verificarAutenticacion();
GuardiaSesion::verificarRol(['Administrador', 'Recepcionista', 'Medico']);

$rolUsuario = $_SESSION['nombre_rol'] ?? '';
$pdo = Conexion::obtenerConexion();
$pacienteControlador = new PacienteControlador();

// ============================================================
// PROCESAMIENTO DE PETICIONES AJAX Y ACCIONES
// ============================================================

// Petición AJAX: Consultar Ficha Médica Completa
if (isset($_GET['accion']) && $_GET['accion'] === 'ficha_clinica') {
    $idPaciente = (int)($_GET['id_paciente'] ?? 0);
    $pacienteDetalle = $pacienteControlador->buscarPorId($idPaciente);
    if ($pacienteDetalle) {
        Ayudantes::responderJson(['exito' => true, 'paciente' => $pacienteDetalle]);
    } else {
        Ayudantes::responderJson(['exito' => false, 'mensaje' => 'Paciente no encontrado.'], 404);
    }
}

// Petición AJAX: Consultar Historial de Citas (CU12)
if (isset($_GET['accion']) && $_GET['accion'] === 'historial') {
    $idPaciente = (int)($_GET['id_paciente'] ?? 0);
    try {
        $citas = $pacienteControlador->obtenerHistorialCitas($idPaciente);
        Ayudantes::responderJson(['exito' => true, 'citas' => $citas]);
    } catch (\Exception $e) {
        Ayudantes::responderJson(['exito' => false, 'mensaje' => 'Error al obtener historial.'], 500);
    }
}

// Petición AJAX / POST: Guardar Paciente (Crear o Modificar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $accion = $datos['accion'] ?? '';

    if ($accion === 'guardar' || isset($datos['ci'])) {
        $id = !empty($datos['id']) ? (int)$datos['id'] : null;
        if ($id) {
            $resultado = $pacienteControlador->modificarPaciente($id, $datos);
        } else {
            $resultado = $pacienteControlador->registrarPaciente($datos);
        }
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }

    if ($accion === 'cambiar_estado') {
        $idPaciente = (int)($datos['id_paciente'] ?? 0);
        $exito = $pacienteControlador->cambiarEstadoPaciente($idPaciente);
        Ayudantes::responderJson([
            'exito' => $exito,
            'mensaje' => $exito ? 'Estado del paciente actualizado correctamente.' : 'Error al modificar estado.'
        ], $exito ? 200 : 400);
    }
}

// ============================================================
// CONSULTA PRINCIPAL CON FILTROS Y PAGINACIÓN DE 15 FILAS
// ============================================================
$buscar = trim($_GET['buscar'] ?? '');
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

$datosPacientes = $pacienteControlador->listarPacientes($pagina_actual, $buscar);
$pacientes = $datosPacientes['registros'];
$total_registros = $datosPacientes['total'];
$limite_registros = $datosPacientes['limite'];

$titulo_pagina = 'Gestión de Pacientes';
$titulo_seccion = 'Gestión de Pacientes';
$script_modulo = 'pacientes.js';
require_once dirname(__DIR__) . '/parciales/encabezado.php';
?>

<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <h2 class="tarjeta-titulo">
            <i class="bi bi-people"></i>
            <span>Listado de Pacientes</span>
        </h2>
        <?php if ($rolUsuario === 'Administrador' || $rolUsuario === 'Recepcionista'): ?>
            <button type="button" class="btn btn-primario" onclick="abrirModalNuevoPaciente()">
                <i class="bi bi-person-plus"></i>
                <span>Nuevo Paciente</span>
            </button>
        <?php endif; ?>
    </div>

    <div class="tarjeta-cuerpo">
        <!-- Barra de Filtros con Buscar y Limpiar -->
        <form method="GET" action="index.php" class="barra-filtros">
            <div style="flex: 1; min-width: 260px;">
                <input type="text" name="buscar" class="control-campo" 
                       placeholder="Buscar por CI, Código, Nombres o Apellidos..." 
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>

            <button type="submit" class="btn btn-secundario">
                <i class="bi bi-search"></i>
                <span>Buscar</span>
            </button>

            <?php if (!empty($buscar)): ?>
                <a href="index.php" class="btn btn-secundario" title="Limpiar Filtros">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Limpiar</span>
                </a>
            <?php endif; ?>
        </form>

        <!-- Tabla de Pacientes (15 filas estándar) -->
        <div class="tabla-responsiva">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cédula (CI)</th>
                        <th>Nombre Completo</th>
                        <th>Teléfono</th>
                        <th>Grupo Sang.</th>
                        <th>Estado</th>
                        <th class="texto-centro">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pacientes)): ?>
                        <tr>
                            <td colspan="7" class="texto-centro texto-muted" style="padding: 2.5rem;">
                                <i class="bi bi-info-circle"></i> No se encontraron pacientes registrados con el criterio de búsqueda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pacientes as $pac): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($pac['codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($pac['ci']) ?></td>
                                <td><?= htmlspecialchars($pac['nombres'] . ' ' . $pac['apellidos']) ?></td>
                                <td><?= htmlspecialchars($pac['telefono']) ?></td>
                                <td>
                                    <span class="badge-tipo-sangre" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($pac['tipo_sangre'] ?: 'ORH+') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="insignia-estado <?= $pac['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                        <?= $pac['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td class="texto-centro">
                                    <div class="acciones-fila" style="justify-content: center;">
                                        <!-- Botón Ficha Médica Completa -->
                                        <button type="button" class="btn-accion" style="color: #0284C7;" 
                                                title="Ver Ficha Médica y Antecedentes" 
                                                onclick="abrirFichaMedica(<?= $pac['id'] ?>)">
                                            <i class="bi bi-file-earmark-medical"></i>
                                        </button>

                                        <!-- Botón Historial de Citas -->
                                        <button type="button" class="btn-accion btn-accion-historial" 
                                                title="Ver Historial de Citas" 
                                                onclick="abrirHistorialPaciente(<?= $pac['id'] ?>, '<?= htmlspecialchars(addslashes($pac['nombres'] . ' ' . $pac['apellidos'])) ?>')">
                                            <i class="bi bi-clock-history"></i>
                                        </button>

                                        <?php if ($rolUsuario === 'Administrador' || $rolUsuario === 'Recepcionista'): ?>
                                            <!-- Botón Editar Paciente -->
                                            <button type="button" class="btn-accion btn-accion-editar" 
                                                    title="Modificar Paciente" 
                                                    onclick='abrirModalEditarPaciente(<?= json_encode($pac, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <!-- Botón Desactivar / Baja Lógica -->
                                            <button type="button" class="btn-accion btn-accion-eliminar" 
                                                    title="<?= $pac['activo'] ? 'Desactivar Paciente' : 'Reactivar Paciente' ?>" 
                                                    onclick="confirmarBajaPaciente(<?= $pac['id'] ?>, '<?= htmlspecialchars(addslashes($pac['nombres'] . ' ' . $pac['apellidos'])) ?>', <?= $pac['activo'] ? 'true' : 'false' ?>)">
                                                <i class="bi bi-<?= $pac['activo'] ? 'trash3' : 'arrow-clockwise' ?>"></i>
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

    <!-- Paginación Compartida de 15 Filas a la Derecha -->
    <?php require dirname(__DIR__) . '/parciales/paginacion.php'; ?>
</div>

<!-- Inclusión de Modales del Módulo -->
<?php 
require_once __DIR__ . '/modal_formulario.php';
require_once __DIR__ . '/modal_historial.php';
require_once __DIR__ . '/modal_ficha_clinica.php';
require_once dirname(__DIR__) . '/parciales/pie.php';
?>

<script>
/**
 * Abre modal de Ficha Médica Completa
 */
async function abrirFichaMedica(idPaciente) {
    const contenedor = document.getElementById('cuerpoFichaClinica');
    if (contenedor) {
        contenedor.innerHTML = '<div class="texto-centro texto-muted p-4"><i class="bi bi-hourglass-split"></i> Cargando ficha médica...</div>';
    }
    abrirModal('modalFichaClinica');

    const res = await peticionAjax('index.php?accion=ficha_clinica&id_paciente=' + idPaciente, 'GET');

    if (contenedor && res && res.exito && res.paciente) {
        const p = res.paciente;
        document.getElementById('ficha_titulo').textContent = 'Ficha Médica: ' + p.codigo + ' - ' + p.nombres + ' ' + p.apellidos;

        let sexoDesc = p.sexo === 'M' ? 'Masculino' : (p.sexo === 'F' ? 'Femenino' : 'Otro');

        contenedor.innerHTML = `
            <div class="ficha-clinica-grid" style="margin-bottom: 1.25rem;">
                <div class="ficha-seccion">
                    <div class="ficha-seccion-titulo"><i class="bi bi-person"></i> Datos Personales</div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Cédula de Identidad (CI):</span><span class="ficha-valor">${p.ci}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Nombres y Apellidos:</span><span class="ficha-valor">${p.nombres} ${p.apellidos}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Fecha de Nacimiento:</span><span class="ficha-valor">${p.fecha_nacimiento}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Sexo Biológico:</span><span class="ficha-valor">${sexoDesc}</span></div>
                </div>

                <div class="ficha-seccion">
                    <div class="ficha-seccion-titulo"><i class="bi bi-telephone"></i> Contacto</div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Teléfono:</span><span class="ficha-valor">${p.telefono}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Correo Electrónico:</span><span class="ficha-valor">${p.correo || '-'}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Dirección Domiciliaria:</span><span class="ficha-valor">${p.direccion || '-'}</span></div>
                </div>
            </div>

            <div class="ficha-clinica-grid">
                <div class="ficha-seccion">
                    <div class="ficha-seccion-titulo"><i class="bi bi-heart-pulse"></i> Información Clínica</div>
                    <div class="ficha-campo">
                        <span class="ficha-etiqueta">Grupo Sanguíneo:</span>
                        <div><span class="badge-tipo-sangre">${p.tipo_sangre || 'ORH+'}</span></div>
                    </div>
                    <div class="ficha-campo">
                        <span class="ficha-etiqueta">Alergias Conocidas:</span>
                        <span class="ficha-valor" style="color: #B91C1C;">${p.alergias || 'Ninguna conocida'}</span>
                    </div>
                    <div class="ficha-campo">
                        <span class="ficha-etiqueta">Enfermedades Crónicas:</span>
                        <span class="ficha-valor">${p.enfermedades_cronicas || 'Ninguna'}</span>
                    </div>
                    <div class="ficha-campo">
                        <span class="ficha-etiqueta">Medicamentos Actuales:</span>
                        <span class="ficha-valor">${p.medicamentos_actuales || 'Ninguno'}</span>
                    </div>
                    <div class="ficha-campo">
                        <span class="ficha-etiqueta">Antecedentes Relevantes:</span>
                        <span class="ficha-valor">${p.antecedentes_relevantes || 'Sin antecedentes quirúrgicos'}</span>
                    </div>
                </div>

                <div class="ficha-seccion">
                    <div class="ficha-seccion-titulo"><i class="bi bi-shield-exclamation"></i> Contacto de Emergencia</div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Nombre:</span><span class="ficha-valor">${p.contacto_emergencia_nombre || 'No registrado'}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Parentesco:</span><span class="ficha-valor">${p.contacto_emergencia_parentesco || 'Familiar directo'}</span></div>
                    <div class="ficha-campo"><span class="ficha-etiqueta">Teléfono:</span><span class="ficha-valor">${p.contacto_emergencia_telefono || 'No registrado'}</span></div>
                </div>
            </div>
        `;
    } else {
        if (contenedor) {
            contenedor.innerHTML = '<div class="alerta alerta-peligro"><i class="bi bi-exclamation-triangle-fill"></i> No se pudo cargar la información del paciente.</div>';
        }
    }
}
</script>
