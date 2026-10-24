<?php
/**
 * Portal del Paciente / Perfil y Ficha Médica
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

require_once dirname(__DIR__, 2) . '/configuracion/aplicacion.php';
require_once dirname(__DIR__, 2) . '/src/core/GuardiaSesion.php';
require_once dirname(__DIR__, 2) . '/src/core/Ayudantes.php';
require_once dirname(__DIR__, 2) . '/src/core/Conexion.php';
require_once dirname(__DIR__, 2) . '/src/controladores/UsuarioControlador.php';
require_once dirname(__DIR__, 2) . '/src/controladores/CitaControlador.php';

use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;
use App\Nucleo\Conexion;
use App\Controladores\UsuarioControlador;
use App\Controladores\CitaControlador;

GuardiaSesion::verificarAutenticacion();

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
$rolUsuario = $_SESSION['nombre_rol'] ?? '';
$correoSesion = $_SESSION['correo'] ?? '';

$pdo = Conexion::obtenerConexion();
$usuarioControlador = new UsuarioControlador();
$citaControlador = new CitaControlador();

// Buscar paciente vinculado al usuario
$stmtP = $pdo->prepare("SELECT * FROM pacientes WHERE id_usuario = :id_u OR LOWER(correo) = LOWER(:correo) LIMIT 1");
$stmtP->execute([':id_u' => $idUsuario, ':correo' => $correoSesion]);
$paciente = $stmtP->fetch();

// ============================================================
// PROCESAMIENTO DE PETICIONES AJAX Y POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $accion = $datos['accion'] ?? '';

    // Actualizar datos de contacto y emergencia del paciente
    if ($accion === 'actualizar_contacto' && $paciente) {
        $tel = trim($datos['telefono'] ?? '');
        $correo = trim($datos['correo'] ?? '');
        $dir = trim($datos['direccion'] ?? '');
        $contNom = trim($datos['contacto_emergencia_nombre'] ?? '');
        $contPar = trim($datos['contacto_emergencia_parentesco'] ?? '');
        $contTel = trim($datos['contacto_emergencia_telefono'] ?? '');

        if (empty($tel) || !preg_match('/^[0-9]{7,10}$/', $tel)) {
            Ayudantes::responderJson(['exito' => false, 'campo' => 'telefono', 'mensaje' => 'El teléfono debe contener entre 7 y 10 dígitos numéricos.'], 400);
        }

        try {
            $stmtUp = $pdo->prepare("
                UPDATE pacientes SET
                    telefono = :tel, correo = :correo, direccion = :dir,
                    contacto_emergencia_nombre = :cnom, contacto_emergencia_parentesco = :cpar, contacto_emergencia_telefono = :ctel
                WHERE id = :id
            ");
            $stmtUp->execute([
                ':tel'    => $tel,
                ':correo' => $correo ?: null,
                ':dir'    => $dir ?: null,
                ':cnom'   => $contNom ?: null,
                ':cpar'   => $contPar ?: 'Familiar directo',
                ':ctel'   => $contTel ?: null,
                ':id'     => $paciente['id']
            ]);

            Ayudantes::responderJson(['exito' => true, 'mensaje' => 'Datos de contacto actualizados correctamente.']);
        } catch (\Exception $e) {
            Ayudantes::responderJson(['exito' => false, 'mensaje' => 'Error al actualizar información.'], 500);
        }
    }

    // Cancelar cita propia (solo si está en estado Programada)
    if ($accion === 'cancelar_cita_propia' && $paciente) {
        $idCita = (int)($datos['id_cita'] ?? 0);
        $motivo = trim($datos['motivo_cancelacion'] ?? '');

        if (!$idCita || empty($motivo)) {
            Ayudantes::responderJson(['exito' => false, 'mensaje' => 'El motivo de cancelación es obligatorio.'], 400);
        }

        // Verificar que la cita pertenezca a este paciente
        $stmtC = $pdo->prepare("SELECT id, estado FROM citas_medicas WHERE id = :id AND id_paciente = :id_p");
        $stmtC->execute([':id' => $idCita, ':id_p' => $paciente['id']]);
        $citaPropia = $stmtC->fetch();

        if (!$citaPropia) {
            Ayudantes::responderJson(['exito' => false, 'mensaje' => 'No tiene autorización para cancelar esta cita.'], 403);
        }

        if ($citaPropia['estado'] !== 'Programada') {
            Ayudantes::responderJson(['exito' => false, 'mensaje' => 'Solo es posible cancelar citas en estado Programada.'], 400);
        }

        $res = $citaControlador->cancelarCita($idCita, $motivo);
        Ayudantes::responderJson($res, $res['exito'] ? 200 : 400);
    }

    // Cambio de contraseña
    if ($accion === 'cambiar_clave') {
        $nueva = $datos['contrasena_nueva'] ?? '';
        $conf = $datos['contrasena_confirmar'] ?? '';
        $res = $usuarioControlador->cambiarContrasena($idUsuario, $nueva, $conf);
        Ayudantes::responderJson($res, $res['exito'] ? 200 : 400);
    }
}

// Obtener citas del paciente si existe
$citasPaciente = [];
if ($paciente) {
    $stmtCitas = $pdo->prepare("
        SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta,
               c.motivo_cancelacion, c.observaciones_atencion,
               (m.nombres || ' ' || m.apellidos) AS nombre_medico,
               e.nombre AS nombre_especialidad
        FROM citas_medicas c
        JOIN medicos m ON m.id = c.id_medico
        JOIN especialidades e ON e.id = c.id_especialidad
        WHERE c.id_paciente = :id_p
        ORDER BY c.fecha DESC, c.hora_inicio DESC
    ");
    $stmtCitas->execute([':id_p' => $paciente['id']]);
    $citasPaciente = $stmtCitas->fetchAll();
}

$titulo_pagina = 'Mi Perfil y Ficha Médica';
$titulo_seccion = 'Mi Perfil y Ficha Médica';
require_once dirname(__DIR__) . '/parciales/encabezado.php';
?>

<div class="perfil-contenedor">
    <!-- Columna Izquierda: Tarjeta de Identidad y Cambio de Clave -->
    <div>
        <div class="perfil-lateral-tarjeta">
            <div class="perfil-avatar">
                <i class="bi bi-person"></i>
            </div>
            <div class="perfil-nombre-titular">
                <?= htmlspecialchars($paciente ? ($paciente['nombres'] . ' ' . $paciente['apellidos']) : $nombreUsuario) ?>
            </div>
            <div class="perfil-codigo-paciente">
                <?= $paciente ? htmlspecialchars($paciente['codigo']) : htmlspecialchars($rolUsuario) ?>
            </div>
            <div style="margin-top: 0.75rem;">
                <span class="insignia-estado estado-activo"><?= htmlspecialchars($rolUsuario) ?></span>
            </div>
        </div>

        <!-- Tarjeta de Cambio de Contraseña -->
        <div class="tarjeta" style="margin-top: 1.5rem;">
            <div class="tarjeta-cabecera">
                <h3 class="tarjeta-titulo" style="font-size: 0.875rem;">
                    <i class="bi bi-key"></i>
                    <span>Seguridad y Contraseña</span>
                </h3>
            </div>
            <div class="tarjeta-cuerpo">
                <form id="formCambiarClavePerfil">
                    <div class="alerta-modal-peligro" id="alertaClavePerfil">
                        <span class="mensaje-alerta-modal"></span>
                    </div>

                    <div class="grupo-campo">
                        <label for="perfil_clave_nueva" class="etiqueta-campo">
                            Nueva Contraseña<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="password" id="perfil_clave_nueva" class="control-campo" placeholder="Mínimo 6 caracteres" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="perfil_clave_conf" class="etiqueta-campo">
                            Confirmar Contraseña<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="password" id="perfil_clave_conf" class="control-campo" placeholder="Repita la contraseña" required>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primario" style="width: 100%;">
                        <i class="bi bi-check2"></i> Actualizar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Ficha Médica Completa y Gestión de Citas -->
    <div>
        <?php if ($paciente): ?>
            <!-- 1. Datos Personales y 2. Contacto -->
            <div class="tarjeta">
                <div class="tarjeta-cabecera">
                    <h3 class="tarjeta-titulo">
                        <i class="bi bi-person-lines-fill"></i>
                        <span>Información Personal y de Contacto</span>
                    </h3>
                </div>
                <div class="tarjeta-cuerpo">
                    <div class="ficha-clinica-grid" style="margin-bottom: 1.25rem;">
                        <div class="ficha-seccion">
                            <div class="ficha-seccion-titulo"><i class="bi bi-person"></i> Datos Personales</div>
                            <div class="ficha-campo"><span class="ficha-etiqueta">Cédula de Identidad (CI):</span><span class="ficha-valor"><?= htmlspecialchars($paciente['ci']) ?></span></div>
                            <div class="ficha-campo"><span class="ficha-etiqueta">Nombres y Apellidos:</span><span class="ficha-valor"><?= htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></span></div>
                            <div class="ficha-campo"><span class="ficha-etiqueta">Fecha de Nacimiento:</span><span class="ficha-valor"><?= Ayudantes::formatearFechaBolivia($paciente['fecha_nacimiento']) ?></span></div>
                            <div class="ficha-campo"><span class="ficha-etiqueta">Sexo Biológico:</span><span class="ficha-valor"><?= $paciente['sexo'] === 'M' ? 'Masculino' : ($paciente['sexo'] === 'F' ? 'Femenino' : 'Otro') ?></span></div>
                        </div>

                        <div class="ficha-seccion">
                            <div class="ficha-seccion-titulo"><i class="bi bi-telephone"></i> Datos de Contacto</div>
                            <form id="formActualizarContacto">
                                <div class="grupo-campo" style="margin-bottom: 0.5rem;">
                                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Teléfono / Celular:</label>
                                    <input type="text" id="contacto_tel" class="control-campo" value="<?= htmlspecialchars($paciente['telefono']) ?>" required>
                                </div>
                                <div class="grupo-campo" style="margin-bottom: 0.5rem;">
                                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Correo Electrónico:</label>
                                    <input type="email" id="contacto_correo" class="control-campo" value="<?= htmlspecialchars($paciente['correo'] ?: '') ?>">
                                </div>
                                <div class="grupo-campo" style="margin-bottom: 0.75rem;">
                                    <label class="etiqueta-campo" style="font-size: 0.75rem;">Dirección Domiciliaria:</label>
                                    <input type="text" id="contacto_dir" class="control-campo" value="<?= htmlspecialchars($paciente['direccion'] ?: '') ?>">
                                </div>
                                <button type="submit" class="btn btn-sm btn-secundario">
                                    <i class="bi bi-check2"></i> Guardar Cambios de Contacto
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- 3. Información Médica y 4. Contacto de Emergencia -->
                    <div class="ficha-clinica-grid">
                        <div class="ficha-seccion">
                            <div class="ficha-seccion-titulo"><i class="bi bi-heart-pulse"></i> Ficha Médica y Antecedentes</div>
                            <div class="ficha-campo">
                                <span class="ficha-etiqueta">Grupo Sanguíneo:</span>
                                <div><span class="badge-tipo-sangre"><?= htmlspecialchars($paciente['tipo_sangre'] ?: 'ORH+') ?></span></div>
                            </div>
                            <div class="ficha-campo">
                                <span class="ficha-etiqueta">Alergias Conocidas:</span>
                                <span class="ficha-valor" style="color: #B91C1C;"><?= htmlspecialchars($paciente['alergias'] ?: 'Ninguna conocida') ?></span>
                            </div>
                            <div class="ficha-campo">
                                <span class="ficha-etiqueta">Enfermedades Crónicas:</span>
                                <span class="ficha-valor"><?= htmlspecialchars($paciente['enfermedades_cronicas'] ?: 'Ninguna') ?></span>
                            </div>
                            <div class="ficha-campo">
                                <span class="ficha-etiqueta">Medicamentos Actuales:</span>
                                <span class="ficha-valor"><?= htmlspecialchars($paciente['medicamentos_actuales'] ?: 'Ninguno') ?></span>
                            </div>
                            <div class="ficha-campo">
                                <span class="ficha-etiqueta">Antecedentes Relevantes:</span>
                                <span class="ficha-valor"><?= htmlspecialchars($paciente['antecedentes_relevantes'] ?: 'Sin antecedentes quirúrgicos') ?></span>
                            </div>
                        </div>

                        <div class="ficha-seccion">
                            <div class="ficha-seccion-titulo"><i class="bi bi-shield-exclamation"></i> Contacto de Emergencia</div>
                            <div class="ficha-campo">
                                <span class="ficha-etiqueta">Nombre del Contacto:</span>
                                <span class="ficha-valor"><?= htmlspecialchars($paciente['contacto_emergencia_nombre'] ?: 'No registrado') ?></span>
                            </div>
                            <div class="ficha-campo">
                                <span class="ficha-etiqueta">Parentesco:</span>
                                <span class="ficha-valor"><?= htmlspecialchars($paciente['contacto_emergencia_parentesco'] ?: 'Familiar directo') ?></span>
                            </div>
                            <div class="ficha-campo">
                                <span class="ficha-etiqueta">Teléfono de Emergencia:</span>
                                <span class="ficha-valor"><?= htmlspecialchars($paciente['contacto_emergencia_telefono'] ?: 'No registrado') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Gestión de Citas del Paciente -->
            <div class="tarjeta">
                <div class="tarjeta-cabecera">
                    <h3 class="tarjeta-titulo">
                        <i class="bi bi-calendar-check"></i>
                        <span>Historial y Gestión de Mis Citas Médicas</span>
                    </h3>
                </div>
                <div class="tarjeta-cuerpo" style="padding: 0;">
                    <div class="tabla-responsiva">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Fecha</th>
                                    <th>Horario</th>
                                    <th>Médico</th>
                                    <th>Especialidad</th>
                                    <th>Estado</th>
                                    <th class="texto-centro">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($citasPaciente)): ?>
                                    <tr>
                                        <td colspan="7" class="texto-centro texto-muted" style="padding: 2rem;">
                                            <i class="bi bi-info-circle"></i> No registra citas médicas agendadas actualmente.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($citasPaciente as $cp): 
                                        $badgeClase = 'estado-programada';
                                        if ($cp['estado'] === 'Confirmada') $badgeClase = 'estado-confirmada';
                                        if ($cp['estado'] === 'Atendida') $badgeClase = 'estado-atendida';
                                        if ($cp['estado'] === 'Cancelada') $badgeClase = 'estado-cancelada';
                                        if ($cp['estado'] === 'Inasistencia') $badgeClase = 'estado-inasistencia';
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($cp['codigo_cita']) ?></strong></td>
                                            <td><?= Ayudantes::formatearFechaBolivia($cp['fecha']) ?></td>
                                            <td><?= substr($cp['hora_inicio'], 0, 5) ?> - <?= substr($cp['hora_fin'], 0, 5) ?></td>
                                            <td>Dr(a). <?= htmlspecialchars($cp['nombre_medico']) ?></td>
                                            <td><?= htmlspecialchars($cp['nombre_especialidad']) ?></td>
                                            <td>
                                                <span class="insignia-estado <?= $badgeClase ?>">
                                                    <?= htmlspecialchars($cp['estado']) ?>
                                                </span>
                                            </td>
                                            <td class="texto-centro">
                                                <?php if ($cp['estado'] === 'Programada'): ?>
                                                    <button type="button" class="btn btn-sm btn-secundario" style="color: var(--color-peligro);" 
                                                            title="Cancelar Cita"
                                                            onclick="cancelarCitaPaciente(<?= $cp['id'] ?>, '<?= $cp['codigo_cita'] ?>')">
                                                        <i class="bi bi-x-circle"></i> Cancelar
                                                    </button>
                                                <?php else: ?>
                                                    <span class="texto-muted" style="font-size: 0.75rem;">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alerta alerta-info">
                <i class="bi bi-info-circle"></i>
                <span>Su cuenta tiene rol <strong><?= htmlspecialchars($rolUsuario) ?></strong>. Esta vista está configurada para mostrar la ficha médica de usuarios con rol Paciente.</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Cancelar Cita Propia -->
<div class="modal-fondo" id="modalCancelarPropia">
    <div class="modal-dialogo" style="max-width: 440px;">
        <div class="modal-cabecera">
            <div class="modal-titulo">
                <i class="bi bi-calendar-x" style="color: var(--color-peligro);"></i>
                <span id="tituloCancelarPropia">Cancelar Cita Médica</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalCancelarPropia')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="alerta-modal-peligro" id="alertaModal_modalCancelarPropia" style="display: none;"></div>
        <form id="formCancelarPropia">
            <input type="hidden" id="cancelar_propia_id">
            <div class="modal-cuerpo">
                <p style="font-size: 0.875rem; color: var(--color-texto); margin-bottom: 1rem;">
                    ¿Está seguro de que desea cancelar su cita médica? El cupo horario quedará inmediatamente disponible.
                </p>
                <div class="grupo-campo">
                    <label for="cancelar_propia_motivo" class="etiqueta-campo">
                        Motivo de Cancelación<span class="etiqueta-requerido">*</span>
                    </label>
                    <textarea id="cancelar_propia_motivo" class="control-campo" rows="3" placeholder="Indique el motivo por el cual cancela su cita..." required></textarea>
                </div>
            </div>
            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalCancelarPropia')">
                    <i class="bi bi-x"></i> Volver
                </button>
                <button type="submit" class="btn btn-peligro">
                    <i class="bi bi-check2"></i> Confirmar Cancelación
                </button>
            </div>
        </form>
    </div>
</div>

<?php 
require_once dirname(__DIR__) . '/parciales/pie.php'; 
?>

<script>
document.getElementById('formCambiarClavePerfil')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const cNueva = document.getElementById('perfil_clave_nueva').value;
    const cConf = document.getElementById('perfil_clave_conf').value;
    const alerta = document.getElementById('alertaClavePerfil');

    if (cNueva.length < 6) {
        mostrarErrorCampo('perfil_clave_nueva', 'La contraseña debe contener al menos 6 caracteres.');
        return;
    }
    if (cNueva !== cConf) {
        mostrarErrorCampo('perfil_clave_conf', 'Las contraseñas no coinciden.');
        return;
    }

    const res = await peticionAjax('index.php', 'POST', {
        accion: 'cambiar_clave',
        contrasena_nueva: cNueva,
        contrasena_confirmar: cConf
    });

    if (res && res.exito) {
        mostrarNotificacionToast(res.mensaje || 'Contraseña actualizada correctamente.', 'exito');
        document.getElementById('formCambiarClavePerfil').reset();
        limpiarErroresFormulario(document.getElementById('formCambiarClavePerfil'));
    } else {
        mostrarNotificacionToast(res.mensaje || 'Error al actualizar contraseña.', 'peligro');
    }
});

document.getElementById('formActualizarContacto')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const tel = document.getElementById('contacto_tel').value.trim();
    const correo = document.getElementById('contacto_correo').value.trim();
    const dir = document.getElementById('contacto_dir').value.trim();

    if (!/^[0-9]{7,10}$/.test(tel)) {
        mostrarErrorCampo('contacto_tel', 'El teléfono debe contener entre 7 y 10 dígitos numéricos.');
        return;
    }

    const res = await peticionAjax('index.php', 'POST', {
        accion: 'actualizar_contacto',
        telefono: tel,
        correo: correo,
        direccion: dir
    });

    if (res && res.exito) {
        mostrarNotificacionToast(res.mensaje || 'Datos de contacto actualizados correctamente.', 'exito');
        setTimeout(() => window.location.reload(), 800);
    } else {
        mostrarNotificacionToast(res.mensaje || 'Error al actualizar contacto.', 'peligro');
    }
});

function cancelarCitaPaciente(idCita, codigoCita) {
    document.getElementById('cancelar_propia_id').value = idCita;
    document.getElementById('tituloCancelarPropia').textContent = 'Cancelar Cita: ' + codigoCita;
    document.getElementById('cancelar_propia_motivo').value = '';
    abrirModal('modalCancelarPropia');
}

document.getElementById('formCancelarPropia')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const id = document.getElementById('cancelar_propia_id').value;
    const motivo = document.getElementById('cancelar_propia_motivo').value.trim();

    if (!motivo) {
        mostrarErrorCampo('cancelar_propia_motivo', 'El motivo de cancelación es obligatorio.');
        return;
    }

    const res = await peticionAjax('index.php', 'POST', {
        accion: 'cancelar_cita_propia',
        id_cita: id,
        motivo_cancelacion: motivo
    });

    if (res && res.exito) {
        cerrarModal('modalCancelarPropia');
        mostrarNotificacionToast(res.mensaje || 'Cita médica cancelada correctamente.', 'exito');
        setTimeout(() => window.location.reload(), 800);
    } else {
        mostrarAlertaModal('modalCancelarPropia', res.mensaje || 'Error al cancelar la cita.');
    }
});
</script>
