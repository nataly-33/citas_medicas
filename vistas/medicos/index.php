<?php
/**
 * Vista Principal de Médicos y Especialidades (CU04, CU05, CU06)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

require_once dirname(__DIR__, 2) . '/configuracion/aplicacion.php';
require_once dirname(__DIR__, 2) . '/src/core/GuardiaSesion.php';
require_once dirname(__DIR__, 2) . '/src/core/Ayudantes.php';
require_once dirname(__DIR__, 2) . '/src/core/Conexion.php';
require_once dirname(__DIR__, 2) . '/src/controladores/MedicoControlador.php';
require_once dirname(__DIR__, 2) . '/src/controladores/HorarioControlador.php';

use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;
use App\Nucleo\Conexion;
use App\Controladores\MedicoControlador;
use App\Controladores\HorarioControlador;

GuardiaSesion::verificarAutenticacion();
GuardiaSesion::verificarRol(['Administrador']);

$rolUsuario = $_SESSION['nombre_rol'] ?? '';
$pdo = Conexion::obtenerConexion();
$medicoControlador = new MedicoControlador();
$horarioControlador = new HorarioControlador();

// Obtener todas las especialidades activas para filtros y modales
$stmtEsp = $pdo->query("SELECT id, nombre FROM especialidades WHERE activa = TRUE ORDER BY nombre ASC");
$todasEspecialidades = $stmtEsp->fetchAll();

// ============================================================
// PROCESAMIENTO DE PETICIONES AJAX Y ACCIONES
// ============================================================

// Petición AJAX: Obtener horarios configurados de un médico
if (isset($_GET['accion']) && $_GET['accion'] === 'obtener_horarios') {
    $idMedico = (int)($_GET['id_medico'] ?? 0);
    $horarios = $medicoControlador->obtenerHorariosMedico($idMedico);
    Ayudantes::responderJson(['exito' => true, 'horarios' => $horarios]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $accion = $datos['accion'] ?? '';

    // Guardar Médico (Crear o Modificar)
    if ($accion === 'guardar' || isset($datos['registro_profesional'])) {
        $id = !empty($datos['id']) ? (int)$datos['id'] : null;
        if ($id) {
            $resultado = $medicoControlador->modificarMedico($id, $datos);
        } else {
            $resultado = $medicoControlador->registrarMedico($datos);
        }
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }

    // Guardar Horarios de Atención
    if ($accion === 'guardar_horarios') {
        $idMedico = (int)($datos['id_medico'] ?? 0);
        $dias = $datos['dias'] ?? [];
        $exito = $horarioControlador->configurarHorarios($idMedico, $dias);
        Ayudantes::responderJson([
            'exito' => $exito,
            'mensaje' => $exito ? 'Horarios de atención actualizados exitosamente.' : 'Error al guardar los horarios de atención.'
        ], $exito ? 200 : 400);
    }

    // Cambio de Estado / Baja Lógica
    if ($accion === 'cambiar_estado') {
        $idMedico = (int)($datos['id_medico'] ?? 0);
        $exito = $medicoControlador->cambiarEstadoMedico($idMedico);
        Ayudantes::responderJson([
            'exito' => $exito,
            'mensaje' => $exito ? 'Estado del médico actualizado correctamente.' : 'Error al modificar estado.'
        ], $exito ? 200 : 400);
    }
}

// ============================================================
// CONSULTA PRINCIPAL CON FILTROS Y PAGINACIÓN DE 15 FILAS
// ============================================================

$buscar = trim($_GET['buscar'] ?? '');
$idEspecialidadFiltro = (int)($_GET['id_especialidad'] ?? 0);
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

$datosMedicos = $medicoControlador->listarMedicos($pagina_actual, $idEspecialidadFiltro, $buscar);
$medicos = $datosMedicos['registros'];
$total_registros = $datosMedicos['total'];
$limite_registros = $datosMedicos['limite'];

$titulo_pagina = 'Plantel Médico';
$titulo_seccion = 'Plantel Médico y Horarios';
require_once dirname(__DIR__) . '/parciales/encabezado.php';
?>

<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <h2 class="tarjeta-titulo">
            <i class="bi bi-person-badge"></i>
            <span>Listado de Médicos y Horarios</span>
        </h2>
        <?php if ($rolUsuario === 'Administrador'): ?>
            <a href="<?= URL_BASE ?>/vistas/usuarios/index.php" class="btn btn-primario" title="Registrar médicos desde la gestión de usuarios">
                <i class="bi bi-person-plus"></i>
                <span>Registrar Médico (en Usuarios)</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="tarjeta-cuerpo">
        <!-- Barra de Filtros con Buscar y Limpiar -->
        <form method="GET" action="index.php" class="barra-filtros">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="buscar" class="control-campo" 
                       placeholder="Buscar por Matrícula, Código, Nombre..." 
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>

            <div style="min-width: 200px;">
                <select name="id_especialidad" class="control-campo">
                    <option value="">-- Todas las Especialidades --</option>
                    <?php foreach ($todasEspecialidades as $esp): ?>
                        <option value="<?= $esp['id'] ?>" <?= $idEspecialidadFiltro === (int)$esp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($esp['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-secundario">
                <i class="bi bi-search"></i>
                <span>Buscar</span>
            </button>

            <?php if (!empty($buscar) || $idEspecialidadFiltro > 0): ?>
                <a href="index.php" class="btn btn-secundario" title="Limpiar Filtros">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Limpiar</span>
                </a>
            <?php endif; ?>
        </form>

        <!-- Tabla de Médicos (15 Filas) -->
        <div class="tabla-responsiva">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Matrícula</th>
                        <th>Médico</th>
                        <th>Especialidades</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th class="texto-centro">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($medicos)): ?>
                        <tr>
                            <td colspan="8" class="texto-centro texto-muted" style="padding: 2.5rem;">
                                <i class="bi bi-info-circle"></i> No se encontraron médicos registrados con los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($medicos as $med): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($med['codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($med['registro_profesional']) ?></td>
                                <td><?= htmlspecialchars($med['nombres'] . ' ' . $med['apellidos']) ?></td>
                                <td><?= htmlspecialchars($med['especialidades_nombres'] ?? 'Sin especialidad') ?></td>
                                <td><?= htmlspecialchars($med['telefono']) ?></td>
                                <td><?= htmlspecialchars($med['correo'] ?: '-') ?></td>
                                <td>
                                    <span class="insignia-estado <?= $med['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                        <?= $med['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td class="texto-centro">
                                    <div class="acciones-fila" style="justify-content: center;">
                                        <!-- Configurar Horarios Semanales -->
                                        <button type="button" class="btn-accion" 
                                                title="Configurar Horarios de Atención" 
                                                style="color: var(--color-acento);"
                                                onclick="abrirModalHorariosMedico(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['nombres'] . ' ' . $med['apellidos'])) ?>')">
                                            <i class="bi bi-clock"></i>
                                        </button>

                                        <?php if ($rolUsuario === 'Administrador'): ?>
                                            <!-- Modificar Médico -->
                                            <button type="button" class="btn-accion btn-accion-editar" 
                                                    title="Modificar Datos" 
                                                    onclick='abrirModalEditarMedico(<?= json_encode($med, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <!-- Baja Lógica -->
                                            <button type="button" class="btn-accion btn-accion-eliminar" 
                                                    title="<?= $med['activo'] ? 'Desactivar Médico' : 'Activar Médico' ?>" 
                                                    onclick="confirmarBajaMedico(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['nombres'] . ' ' . $med['apellidos'])) ?>', <?= $med['activo'] ? 'true' : 'false' ?>)">
                                                <i class="bi bi-<?= $med['activo'] ? 'trash3' : 'arrow-clockwise' ?>"></i>
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
require_once __DIR__ . '/modal_horarios.php';
require_once dirname(__DIR__) . '/parciales/pie.php';
?>

<script>
function abrirModalNuevoMedico() {
    const form = document.getElementById('formMedico');
    if (form) {
        form.reset();
        document.getElementById('medico_id').value = '';
        limpiarErroresFormulario(form);
    }
    const titulo = document.getElementById('modalMedicoTitulo');
    if (titulo) {
        titulo.innerHTML = '<i class="bi bi-person-badge"></i> Nuevo Médico';
    }
    document.getElementById('medico_contrasena').required = true;
    document.getElementById('label_medico_clave').innerHTML = 'Contraseña Inicial<span class="etiqueta-requerido">*</span>';
    document.getElementById('ayuda_medico_clave').style.display = 'none';
    abrirModal('modalMedico');
}

function abrirModalEditarMedico(medico) {
    const form = document.getElementById('formMedico');
    if (form) {
        limpiarErroresFormulario(form);
        document.getElementById('medico_id').value = medico.id || '';
        document.getElementById('medico_registro_profesional').value = medico.registro_profesional || '';
        document.getElementById('medico_nombres').value = medico.nombres || '';
        document.getElementById('medico_apellidos').value = medico.apellidos || '';
        document.getElementById('medico_telefono').value = medico.telefono || '';
        document.getElementById('medico_correo').value = medico.correo || '';
        document.getElementById('medico_contrasena').value = '';
        document.getElementById('medico_contrasena').required = false;
        document.getElementById('label_medico_clave').innerHTML = 'Cambiar Contraseña (Opcional)';
        document.getElementById('ayuda_medico_clave').style.display = 'block';

        const ids = (medico.especialidades_ids || '').split(',');
        document.querySelectorAll('.check-especialidad').forEach(function (chk) {
            chk.checked = ids.includes(chk.value);
        });
    }
    const titulo = document.getElementById('modalMedicoTitulo');
    if (titulo) {
        titulo.innerHTML = '<i class="bi bi-pencil-square"></i> Modificar Médico';
    }
    abrirModal('modalMedico');
}

document.getElementById('formMedico')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    limpiarErroresFormulario(form);
    ocultarAlertaModal('modalMedico');

    const reg = document.getElementById('medico_registro_profesional').value.trim();
    const nom = document.getElementById('medico_nombres').value.trim();
    const ape = document.getElementById('medico_apellidos').value.trim();
    const tel = document.getElementById('medico_telefono').value.trim();
    const correo = document.getElementById('medico_correo').value.trim();
    const pass = document.getElementById('medico_contrasena').value;
    const id = document.getElementById('medico_id').value;

    let tieneErrores = false;
    if (!reg) {
        mostrarErrorCampo('medico_registro_profesional', 'La matrícula médica o registro profesional es obligatorio.');
        tieneErrores = true;
    }
    if (nom.length < 2) {
        mostrarErrorCampo('medico_nombres', 'Los nombres deben contener al menos 2 letras.');
        tieneErrores = true;
    }
    if (ape.length < 2) {
        mostrarErrorCampo('medico_apellidos', 'Los apellidos deben contener al menos 2 letras.');
        tieneErrores = true;
    }
    if (!/^[0-9]{7,10}$/.test(tel)) {
        mostrarErrorCampo('medico_telefono', 'El teléfono debe contener entre 7 y 10 dígitos numéricos.');
        tieneErrores = true;
    }
    if (!correo || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        mostrarErrorCampo('medico_correo', 'Ingrese un correo electrónico válido.');
        tieneErrores = true;
    }
    if (!id && (!pass || pass.length < 6)) {
        mostrarErrorCampo('medico_contrasena', 'La contraseña inicial debe contener al menos 6 caracteres.');
        tieneErrores = true;
    }

    const checkeados = form.querySelectorAll('.check-especialidad:checked');
    if (checkeados.length === 0) {
        const errorEsp = document.getElementById('error_medico_especialidades');
        if (errorEsp) {
            errorEsp.textContent = 'Debe seleccionar al menos una especialidad.';
            errorEsp.style.display = 'block';
        }
        tieneErrores = true;
    }

    if (tieneErrores) return;

    const formData = new FormData(form);
    const respuesta = await peticionAjax(window.location.pathname, 'POST', formData);

    if (respuesta && respuesta.exito) {
        cerrarModal('modalMedico');
        window.location.href = window.location.pathname + '?mensaje=medico_guardado';
    } else {
        const msg = respuesta.mensaje || 'Error al guardar médico.';
        mostrarAlertaModal('modalMedico', msg);
        if (respuesta.campo === 'registro_profesional' || msg.toLowerCase().includes('registro profesional') || msg.toLowerCase().includes('matrícula')) {
            mostrarErrorCampo('medico_registro_profesional', 'La matrícula ya se encuentra asignada a otro profesional.');
        } else if (respuesta.campo) {
            mostrarErrorCampo('medico_' + respuesta.campo, msg);
        }
    }
});

async function abrirModalHorariosMedico(idMedico, nombreMedico) {
    const titulo = document.getElementById('modalHorariosTitulo');
    if (titulo) {
        titulo.innerHTML = '<i class="bi bi-clock"></i> Horarios: Dr(a). ' + nombreMedico;
    }
    document.getElementById('horarios_id_medico').value = idMedico;

    for (let i = 1; i <= 7; i++) {
        const chk = document.getElementById('dia_activo_' + i);
        const ini = document.getElementById('hora_inicio_' + i);
        const fin = document.getElementById('hora_fin_' + i);
        if (chk) chk.checked = false;
        if (ini) { ini.value = '08:00'; ini.disabled = true; }
        if (fin) { fin.value = '12:00'; fin.disabled = true; }
    }

    abrirModal('modalHorarios');

    const respuesta = await peticionAjax(window.location.pathname + '?accion=obtener_horarios&id_medico=' + idMedico, 'GET');
    if (respuesta && respuesta.exito && respuesta.horarios) {
        respuesta.horarios.forEach(function (h) {
            const chk = document.getElementById('dia_activo_' + h.dia_semana);
            const ini = document.getElementById('hora_inicio_' + h.dia_semana);
            const fin = document.getElementById('hora_fin_' + h.dia_semana);
            if (chk) chk.checked = true;
            if (ini) { ini.value = h.hora_inicio.substring(0, 5); ini.disabled = false; }
            if (fin) { fin.value = h.hora_fin.substring(0, 5); fin.disabled = false; }
        });
    }
}

document.getElementById('formHorarios')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    ocultarAlertaModal('modalHorarios');

    const formData = new FormData(form);
    const respuesta = await peticionAjax(window.location.pathname, 'POST', formData);

    if (respuesta && respuesta.exito) {
        cerrarModal('modalHorarios');
        window.location.href = window.location.pathname + '?mensaje=guardado_exitoso';
    } else {
        mostrarAlertaModal('modalHorarios', respuesta.mensaje || 'Error al guardar los horarios.');
    }
});

function confirmarBajaMedico(idMedico, nombreMedico, estadoActual) {
    const accionTexto = estadoActual ? 'desactivar' : 'activar';
    abrirModalConfirmacion({
        titulo: 'Confirmar Cambio de Estado de Médico',
        mensaje: '¿Está seguro de ' + accionTexto + ' al Dr(a). ' + nombreMedico + '? Las citas agendadas previamente se mantendrán en el sistema histórico.',
        textoBoton: estadoActual ? 'Desactivar' : 'Activar',
        accionConfirmar: async function () {
            const respuesta = await peticionAjax(window.location.pathname, 'POST', {
                accion: 'cambiar_estado',
                id_medico: idMedico
            });
            if (respuesta && respuesta.exito) {
                window.location.href = window.location.pathname + '?mensaje=estado_actualizado';
            } else {
                mostrarNotificacionToast(respuesta.mensaje || 'Error al cambiar estado', 'peligro');
            }
        }
    });
}
</script>
