<?php
/**
 * Gestión de Usuarios y Operadores del Sistema (CU02)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

require_once dirname(__DIR__, 2) . '/configuracion/aplicacion.php';
require_once dirname(__DIR__, 2) . '/src/core/GuardiaSesion.php';
require_once dirname(__DIR__, 2) . '/src/core/Ayudantes.php';
require_once dirname(__DIR__, 2) . '/src/controladores/UsuarioControlador.php';

use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;
use App\Controladores\UsuarioControlador;

GuardiaSesion::verificarAutenticacion();
GuardiaSesion::verificarRol(['Administrador']);

$usuarioControlador = new UsuarioControlador();

// ============================================================
// PROCESAMIENTO DE PETICIONES AJAX Y ACCIONES POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $accion = $datos['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = !empty($datos['id']) ? (int)$datos['id'] : null;
        if ($id) {
            $resultado = $usuarioControlador->modificarUsuario($id, $datos);
        } else {
            $resultado = $usuarioControlador->registrarUsuario($datos);
        }
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }

    if ($accion === 'cambiar_contrasena') {
        $id = (int)($datos['id'] ?? 0);
        $nueva = $datos['contrasena_nueva'] ?? '';
        $confirmacion = $datos['contrasena_confirmar'] ?? '';
        $resultado = $usuarioControlador->cambiarContrasena($id, $nueva, $confirmacion);
        Ayudantes::responderJson($resultado, $resultado['exito'] ? 200 : 400);
    }

    if ($accion === 'cambiar_estado') {
        $id = (int)($datos['id'] ?? 0);
        $exito = $usuarioControlador->cambiarEstadoUsuario($id);
        Ayudantes::responderJson([
            'exito' => $exito,
            'mensaje' => $exito ? 'Estado del usuario actualizado correctamente.' : 'No se pudo actualizar el estado.'
        ], $exito ? 200 : 400);
    }
}

// ============================================================
// CONSULTA CON BUSCADOR, FILTRO DE ROL Y PAGINACIÓN DE 15 FILAS
// ============================================================
$buscar = trim($_GET['buscar'] ?? '');
$filtroIdRol = (int)($_GET['id_rol'] ?? 0);
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

$datosUsuarios = $usuarioControlador->listarUsuarios($pagina_actual, $buscar, $filtroIdRol);
$usuarios = $datosUsuarios['registros'];
$total_registros = $datosUsuarios['total'];
$limite_registros = $datosUsuarios['limite'];

$pdo = \App\Nucleo\Conexion::obtenerConexion();
$stmtEsp = $pdo->query("SELECT id, nombre FROM especialidades WHERE activa = TRUE ORDER BY nombre ASC");
$especialidadesCatalogo = $stmtEsp->fetchAll();
$rolesCatalogo = array_values(array_filter($usuarioControlador->obtenerRoles(), function($r) {
    return (int)$r['id'] !== 4; // Excluir rol Paciente (solo lo registra Recepción)
}));

$titulo_pagina = 'Gestión de Usuarios';
$titulo_seccion = 'Gestión de Usuarios y Roles';
require_once dirname(__DIR__) . '/parciales/encabezado.php';
?>

<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <h2 class="tarjeta-titulo">
            <i class="bi bi-shield-lock"></i>
            <span>Operadores y Cuentas de Acceso</span>
        </h2>
        <div>
            <button type="button" class="btn btn-primario" onclick="abrirModalNuevoUsuario()">
                <i class="bi bi-person-plus"></i>
                <span>Nuevo Usuario</span>
            </button>
        </div>
    </div>

    <div class="tarjeta-cuerpo">
        <!-- Barra de Filtros por Rol y Texto -->
        <form method="GET" action="index.php" class="barra-filtros">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="buscar" class="control-campo" 
                       placeholder="Buscar por nombre, apellido, correo..." 
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>

            <div style="min-width: 200px;">
                <select name="id_rol" class="control-campo">
                    <option value="">-- Todos los Roles --</option>
                    <option value="1" <?= $filtroIdRol === 1 ? 'selected' : '' ?>>1. Administrador</option>
                    <option value="2" <?= $filtroIdRol === 2 ? 'selected' : '' ?>>2. Recepcionista</option>
                    <option value="3" <?= $filtroIdRol === 3 ? 'selected' : '' ?>>3. Médico</option>
                    <option value="4" <?= $filtroIdRol === 4 ? 'selected' : '' ?>>4. Paciente</option>
                </select>
            </div>

            <button type="submit" class="btn btn-secundario">
                <i class="bi bi-search"></i>
                <span>Buscar</span>
            </button>

            <?php if (!empty($buscar) || $filtroIdRol > 0): ?>
                <a href="index.php" class="btn btn-secundario" title="Limpiar Filtro">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Limpiar</span>
                </a>
            <?php endif; ?>
        </form>

        <!-- Tabla Estándar de 15 Filas -->
        <div class="tabla-responsiva">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Nombres y Apellidos</th>
                        <th>Correo Electrónico (Acceso)</th>
                        <th>Teléfono</th>
                        <th>Rol Asignado</th>
                        <th>Último Acceso</th>
                        <th>Estado</th>
                        <th class="texto-centro">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="7" class="texto-centro texto-muted" style="padding: 2.5rem;">
                                <i class="bi bi-info-circle"></i> No se encontraron usuarios registrados con el criterio especificado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u): 
                            $nombreRol = $u['nombre_rol'] ?? 'Operador';
                            $rolClase = 'estado-programada';
                            if ($nombreRol === 'Administrador') $rolClase = 'estado-cancelada';
                            if ($nombreRol === 'Recepcionista') $rolClase = 'estado-confirmada';
                            if ($nombreRol === 'Medico') $rolClase = 'estado-atendida';
                            if ($nombreRol === 'Paciente') $rolClase = 'estado-inasistencia';
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars(trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? '')) ?: '-') ?></strong>
                                </td>
                                <td><?= htmlspecialchars($u['correo']) ?></td>
                                <td><?= htmlspecialchars($u['telefono'] ?? '-') ?></td>
                                <td>
                                    <span class="insignia-estado <?= $rolClase ?>">
                                        <?= htmlspecialchars($nombreRol) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= !empty($u['ultimo_acceso']) ? Ayudantes::formatearFechaHoraBolivia($u['ultimo_acceso']) : '<span class="texto-muted">Nunca</span>' ?>
                                </td>
                                <td>
                                    <span class="insignia-estado <?= $u['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td class="texto-centro">
                                    <div class="acciones-fila" style="justify-content: center;">
                                        <!-- Modificar Usuario (incluye cambio de clave) -->
                                        <button type="button" class="btn-accion btn-accion-editar" 
                                                title="Editar Usuario" 
                                                onclick='abrirModalEditarUsuario(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Activar / Desactivar -->
                                        <button type="button" class="btn-accion btn-accion-eliminar" 
                                                title="<?= $u['activo'] ? 'Desactivar Usuario' : 'Activar Usuario' ?>" 
                                                onclick="confirmarEstadoUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['correo'])) ?>', <?= $u['activo'] ? 'true' : 'false' ?>)">
                                            <i class="bi bi-<?= $u['activo'] ? 'trash3' : 'arrow-clockwise' ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación a la Derecha -->
    <?php require dirname(__DIR__) . '/parciales/paginacion.php'; ?>
</div>

<!-- ============================================================
     MODAL DE CREACIÓN / EDICIÓN DE USUARIO
     ============================================================ -->
<div class="modal-fondo" id="modalUsuario">
    <div class="modal-dialogo modal-dialogo-grande">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalUsuarioTitulo">
                <i class="bi bi-person-plus"></i>
                <span>Nuevo Usuario</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalUsuario')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="formUsuario" method="POST" action="index.php">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" id="usuario_id" value="">

            <div class="modal-cuerpo">
                <div class="alerta-modal-peligro">
                    <span class="mensaje-alerta-modal"></span>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="usuario_nombres" class="etiqueta-campo">
                            Nombres<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="nombres" id="usuario_nombres" class="control-campo" placeholder="ej. Juan Carlos" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="usuario_apellidos" class="etiqueta-campo">
                            Apellidos<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="apellidos" id="usuario_apellidos" class="control-campo" placeholder="ej. Soliz Roca" required>
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="usuario_correo" class="etiqueta-campo">
                            Correo Electrónico (Acceso al Sistema)<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="email" name="correo" id="usuario_correo" class="control-campo" placeholder="ej. usuario@vidasalud.bo" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="usuario_telefono" class="etiqueta-campo">
                            Teléfono / Celular<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="telefono" id="usuario_telefono" class="control-campo" placeholder="ej. 70012345 (7 a 10 dígitos)" required>
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="usuario_id_rol" class="etiqueta-campo">
                            Rol en el Sistema<span class="etiqueta-requerido">*</span>
                        </label>
                        <select name="id_rol" id="usuario_id_rol" class="control-campo" onchange="alternarCamposMedico(this.value)" required>
                            <option value="">-- Seleccionar Rol --</option>
                            <?php foreach ($rolesCatalogo as $rol): ?>
                                <option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?> - <?= htmlspecialchars($rol['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grupo-campo" id="bloque_contrasena_usuario">
                        <label for="usuario_contrasena" class="etiqueta-campo" id="label_contrasena_usuario">
                            Contraseña Inicial<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="password" name="contrasena" id="usuario_contrasena" class="control-campo" placeholder="Ingrese cualquier contraseña para el usuario">
                        <span class="texto-muted" id="ayuda_contrasena_usuario" style="font-size: 0.75rem; display: none;">Dejar este campo vacío si no desea modificar la clave del usuario.</span>
                    </div>
                </div>

                <!-- Sección Dinámica para Médicos -->
                <div id="seccion_medico_campos" style="display: none; padding: 1rem; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; margin-top: 0.75rem;">
                    <div style="font-weight: 600; color: #1B4332; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="bi bi-person-badge"></i> Datos Profesionales del Médico
                    </div>
                    <div class="grupo-campo" style="margin-bottom: 0.75rem;">
                        <label for="usuario_registro_profesional" class="etiqueta-campo">
                            Matrícula Profesional / Registro Médico<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="registro_profesional" id="usuario_registro_profesional" class="control-campo" placeholder="ej. MP-10492">
                    </div>
                    <div class="grupo-campo" style="margin-bottom: 0;">
                        <label class="etiqueta-campo">
                            Especialidades Médicas Asignadas<span class="etiqueta-requerido">*</span>
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; max-height: 140px; overflow-y: auto; padding: 0.5rem; border: 1px solid #CBD5E1; border-radius: 4px; background-color: #FFFFFF;">
                            <?php foreach ($especialidadesCatalogo as $esp): ?>
                                <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8125rem; cursor: pointer;">
                                    <input type="checkbox" name="especialidades[]" class="check-especialidad-usr" value="<?= $esp['id'] ?>">
                                    <span><?= htmlspecialchars($esp['nombre']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalUsuario')">
                    <i class="bi bi-x"></i>
                    <span>Cancelar</span>
                </button>
                <button type="submit" class="btn btn-primario" id="btnGuardarUsuario">
                    <i class="bi bi-check2"></i>
                    <span>Guardar Usuario</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function alternarCamposMedico(rolId) {
    const seccion = document.getElementById('seccion_medico_campos');
    const inputReg = document.getElementById('usuario_registro_profesional');
    if (!seccion) return;

    if (parseInt(rolId, 10) === 3) {
        seccion.style.display = 'block';
        if (inputReg) inputReg.required = true;
    } else {
        seccion.style.display = 'none';
        if (inputReg) {
            inputReg.required = false;
            inputReg.value = '';
        }
        document.querySelectorAll('.check-especialidad-usr').forEach(chk => chk.checked = false);
    }
}

function abrirModalNuevoUsuario() {
    const form = document.getElementById('formUsuario');
    if (form) {
        form.reset();
        document.getElementById('usuario_id').value = '';
        limpiarErroresFormulario(form);
    }
    alternarCamposMedico(0);
    document.getElementById('modalUsuarioTitulo').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo Usuario';
    document.getElementById('usuario_contrasena').required = true;
    document.getElementById('label_contrasena_usuario').innerHTML = 'Contraseña Inicial<span class="etiqueta-requerido">*</span>';
    document.getElementById('ayuda_contrasena_usuario').style.display = 'none';
    abrirModal('modalUsuario');
}

function abrirModalEditarUsuario(u) {
    const form = document.getElementById('formUsuario');
    if (form) {
        limpiarErroresFormulario(form);
        document.getElementById('usuario_id').value = u.id || '';
        document.getElementById('usuario_correo').value = u.correo || '';
        document.getElementById('usuario_nombres').value = u.nombres || '';
        document.getElementById('usuario_apellidos').value = u.apellidos || '';
        document.getElementById('usuario_telefono').value = u.telefono || '';
        document.getElementById('usuario_id_rol').value = u.id_rol || '';
        document.getElementById('usuario_contrasena').value = '';
        document.getElementById('usuario_contrasena').required = false;
        document.getElementById('label_contrasena_usuario').innerHTML = 'Cambiar Contraseña (Opcional)';
        document.getElementById('ayuda_contrasena_usuario').style.display = 'block';

        alternarCamposMedico(u.id_rol);
        if (parseInt(u.id_rol, 10) === 3 && u.registro_profesional) {
            document.getElementById('usuario_registro_profesional').value = u.registro_profesional;
        }
    }
    document.getElementById('modalUsuarioTitulo').innerHTML = '<i class="bi bi-pencil-square"></i> Modificar Usuario';
    abrirModal('modalUsuario');
}

document.getElementById('formUsuario')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    limpiarErroresFormulario(form);
    ocultarAlertaModal('modalUsuario');

    const correo = document.getElementById('usuario_correo').value.trim();
    const nombres = document.getElementById('usuario_nombres').value.trim();
    const apellidos = document.getElementById('usuario_apellidos').value.trim();
    const telefono = document.getElementById('usuario_telefono').value.trim();
    const rol = parseInt(document.getElementById('usuario_id_rol').value, 10);
    const contrasena = document.getElementById('usuario_contrasena').value;
    const id = document.getElementById('usuario_id').value;

    let errores = false;
    if (!correo || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        mostrarErrorCampo('usuario_correo', 'Ingrese un correo electrónico válido.');
        errores = true;
    }
    if (nombres.length < 2) {
        mostrarErrorCampo('usuario_nombres', 'Los nombres deben contener al menos 2 letras.');
        errores = true;
    }
    if (apellidos.length < 2) {
        mostrarErrorCampo('usuario_apellidos', 'Los apellidos deben contener al menos 2 letras.');
        errores = true;
    }
    if (!telefono || !/^[0-9]{7,10}$/.test(telefono)) {
        mostrarErrorCampo('usuario_telefono', 'El teléfono debe contener entre 7 y 10 dígitos numéricos.');
        errores = true;
    }
    if (!rol || rol < 1 || rol > 3) {
        mostrarErrorCampo('usuario_id_rol', 'Debe seleccionar un rol válido (Administrador, Recepcionista o Médico).');
        errores = true;
    }
    if (!id && (!contrasena || contrasena.length < 6)) {
        mostrarErrorCampo('usuario_contrasena', 'La contraseña inicial debe contener al menos 6 caracteres.');
        errores = true;
    }

    let regProf = '';
    let especialidades = [];
    if (rol === 3) {
        regProf = document.getElementById('usuario_registro_profesional').value.trim();
        if (!regProf) {
            mostrarErrorCampo('usuario_registro_profesional', 'La matrícula médica o registro profesional es obligatoria.');
            errores = true;
        }
        document.querySelectorAll('.check-especialidad-usr:checked').forEach(chk => {
            especialidades.push(parseInt(chk.value, 10));
        });
        if (especialidades.length === 0) {
            mostrarAlertaModal('modalUsuario', 'Debe seleccionar al menos una especialidad médica para el doctor.');
            errores = true;
        }
    }

    if (errores) return;

    const datos = {
        accion: 'guardar',
        id: id,
        correo: correo,
        nombres: nombres,
        apellidos: apellidos,
        telefono: telefono,
        id_rol: rol,
        contrasena: contrasena,
        registro_profesional: regProf,
        especialidades: especialidades
    };

    const respuesta = await peticionAjax(window.location.pathname, 'POST', datos);

    if (respuesta && respuesta.exito) {
        cerrarModal('modalUsuario');
        window.location.href = window.location.pathname + '?mensaje=guardado_exitoso';
    } else {
        mostrarAlertaModal('modalUsuario', respuesta.mensaje || 'Error al guardar usuario.');
        if (respuesta.campo) {
            mostrarErrorCampo('usuario_' + respuesta.campo, respuesta.mensaje);
        }
    }
});

function confirmarEstadoUsuario(id, correo, estadoActual) {
    const accionTexto = estadoActual ? 'desactivar' : 'activar';
    abrirModalConfirmacion({
        titulo: 'Confirmar Estado de Usuario',
        mensaje: '¿Está seguro de ' + accionTexto + ' la cuenta del usuario ' + correo + '?',
        textoBoton: estadoActual ? 'Desactivar' : 'Activar',
        accionConfirmar: async function () {
            const respuesta = await peticionAjax(window.location.pathname, 'POST', {
                accion: 'cambiar_estado',
                id: id
            });
            if (respuesta && respuesta.exito) {
                window.location.href = window.location.pathname + '?mensaje=estado_actualizado';
            } else {
                mostrarNotificacionToast(respuesta.mensaje || 'Error al actualizar estado', 'peligro');
            }
        }
    });
}
</script>

<?php 
require_once dirname(__DIR__) . '/parciales/pie.php'; 
?>
