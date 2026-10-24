/**
 * JavaScript Módulo Pacientes - Clínica Vida Salud
 * Sistemas de Información 1 - UAGRM
 * Gestión de Pacientes, Ficha Médica e Historial
 */

document.addEventListener('DOMContentLoaded', function () {
    const formPaciente = document.getElementById('formPaciente');
    if (formPaciente) {
        formPaciente.addEventListener('submit', guardarPaciente);
    }
});

/**
 * Prepara el modal para registrar un nuevo paciente
 */
function abrirModalNuevoPaciente() {
    const form = document.getElementById('formPaciente');
    if (form) {
        form.reset();
        document.getElementById('paciente_id').value = '';
        limpiarErroresFormulario(form);
    }
    const titulo = document.getElementById('modalPacienteTitulo');
    if (titulo) {
        titulo.innerHTML = '<i class="bi bi-person-plus"></i> Nuevo Paciente';
    }
    abrirModal('modalPaciente');
}

/**
 * Precarga el modal con los datos del paciente para edición
 * @param {Object} paciente 
 */
function abrirModalEditarPaciente(paciente) {
    const form = document.getElementById('formPaciente');
    if (form) {
        limpiarErroresFormulario(form);
        document.getElementById('paciente_id').value = paciente.id || '';
        document.getElementById('paciente_ci').value = paciente.ci || '';
        document.getElementById('paciente_nombres').value = paciente.nombres || '';
        document.getElementById('paciente_apellidos').value = paciente.apellidos || '';
        document.getElementById('paciente_fecha_nacimiento').value = paciente.fecha_nacimiento || '';
        document.getElementById('paciente_sexo').value = paciente.sexo || 'M';
        document.getElementById('paciente_telefono').value = paciente.telefono || '';
        document.getElementById('paciente_correo').value = paciente.correo || '';
        document.getElementById('paciente_direccion').value = paciente.direccion || '';

        // Campos clínicos y de emergencia
        if (document.getElementById('paciente_tipo_sangre')) {
            document.getElementById('paciente_tipo_sangre').value = paciente.tipo_sangre || 'ORH+';
        }
        if (document.getElementById('paciente_alergias')) {
            document.getElementById('paciente_alergias').value = paciente.alergias || '';
        }
        if (document.getElementById('paciente_enfermedades_cronicas')) {
            document.getElementById('paciente_enfermedades_cronicas').value = paciente.enfermedades_cronicas || '';
        }
        if (document.getElementById('paciente_medicamentos_actuales')) {
            document.getElementById('paciente_medicamentos_actuales').value = paciente.medicamentos_actuales || '';
        }
        if (document.getElementById('paciente_antecedentes_relevantes')) {
            document.getElementById('paciente_antecedentes_relevantes').value = paciente.antecedentes_relevantes || '';
        }
        if (document.getElementById('paciente_contacto_nombre')) {
            document.getElementById('paciente_contacto_nombre').value = paciente.contacto_emergencia_nombre || '';
        }
        if (document.getElementById('paciente_contacto_parentesco')) {
            document.getElementById('paciente_contacto_parentesco').value = paciente.contacto_emergencia_parentesco || '';
        }
        if (document.getElementById('paciente_contacto_telefono')) {
            document.getElementById('paciente_contacto_telefono').value = paciente.contacto_emergencia_telefono || '';
        }
    }
    const titulo = document.getElementById('modalPacienteTitulo');
    if (titulo) {
        titulo.innerHTML = '<i class="bi bi-pencil-square"></i> Modificar Paciente';
    }
    abrirModal('modalPaciente');
}

/**
 * Envío asíncrono y validación dinámica de paciente
 * @param {Event} e 
 */
async function guardarPaciente(e) {
    e.preventDefault();
    const form = e.target;
    limpiarErroresFormulario(form);
    ocultarAlertaModal('modalPaciente');

    const ci = document.getElementById('paciente_ci').value.trim();
    const nombres = document.getElementById('paciente_nombres').value.trim();
    const apellidos = document.getElementById('paciente_apellidos').value.trim();
    const fechaNac = document.getElementById('paciente_fecha_nacimiento').value.trim();
    const telefono = document.getElementById('paciente_telefono').value.trim();
    const correo = document.getElementById('paciente_correo').value.trim();

    let tieneErrores = false;

    if (!ci) {
        mostrarErrorCampo('paciente_ci', 'La cédula de identidad es obligatoria.');
        tieneErrores = true;
    }

    if (nombres.length < 2) {
        mostrarErrorCampo('paciente_nombres', 'Los nombres deben contener al menos 2 letras.');
        tieneErrores = true;
    }

    if (apellidos.length < 2) {
        mostrarErrorCampo('paciente_apellidos', 'Los apellidos deben contener al menos 2 letras.');
        tieneErrores = true;
    }

    if (!fechaNac) {
        mostrarErrorCampo('paciente_fecha_nacimiento', 'La fecha de nacimiento es requerida.');
        tieneErrores = true;
    } else {
        const fechaIngresada = new Date(fechaNac);
        const hoy = new Date();
        if (fechaIngresada > hoy) {
            mostrarErrorCampo('paciente_fecha_nacimiento', 'La fecha no puede ser futura.');
            tieneErrores = true;
        }
    }

    if (!/^[0-9]{7,10}$/.test(telefono)) {
        mostrarErrorCampo('paciente_telefono', 'El teléfono debe contener entre 7 y 10 dígitos numéricos.');
        tieneErrores = true;
    }

    if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        mostrarErrorCampo('paciente_correo', 'El formato de correo electrónico no es válido.');
        tieneErrores = true;
    }

    if (tieneErrores) {
        return;
    }

    const formData = new FormData(form);
    const url = form.getAttribute('action') || window.location.pathname;

    const respuesta = await peticionAjax(url, 'POST', formData);

    if (respuesta && respuesta.exito) {
        cerrarModal('modalPaciente');
        window.location.href = window.location.pathname + '?mensaje=guardado_exitoso';
    } else {
        // Regla Inviolable: El modal NO se cierra
        const mensajeError = respuesta.mensaje || 'Error al procesar la solicitud.';
        mostrarAlertaModal('modalPaciente', mensajeError);

        if (respuesta.campo === 'ci' || mensajeError.toLowerCase().includes('ci') || mensajeError.toLowerCase().includes('identidad')) {
            mostrarErrorCampo('paciente_ci', 'El documento de identidad ya se encuentra registrado.');
        } else if (respuesta.campo) {
            mostrarErrorCampo('paciente_' + respuesta.campo, mensajeError);
        }
    }
}

/**
 * Consulta el historial de atenciones de un paciente
 * @param {number} idPaciente 
 * @param {string} nombreCompleto 
 */
async function abrirHistorialPaciente(idPaciente, nombreCompleto) {
    const titulo = document.getElementById('modalHistorialTitulo');
    if (titulo) {
        titulo.innerHTML = '<i class="bi bi-clock-history"></i> Historial de Citas: ' + nombreCompleto;
    }

    const contenedor = document.getElementById('contenedorHistorialCitas');
    if (contenedor) {
        contenedor.innerHTML = '<div class="texto-centro p-4"><i class="bi bi-hourglass-split"></i> Cargando historial...</div>';
    }

    abrirModal('modalHistorialPaciente');

    const rutaBase = (typeof obtenerRutaBase === 'function') ? obtenerRutaBase() : '';
    const respuesta = await peticionAjax(rutaBase + '/vistas/pacientes/index.php?accion=historial&id_paciente=' + idPaciente, 'GET');

    if (contenedor) {
        if (respuesta && respuesta.exito && respuesta.citas && respuesta.citas.length > 0) {
            let html = '<div class="tabla-responsiva"><table class="tabla">';
            html += '<thead><tr><th>Código</th><th>Fecha</th><th>Horario</th><th>Médico</th><th>Especialidad</th><th>Estado</th><th>Observaciones / Diagnóstico</th></tr></thead><tbody>';

            respuesta.citas.forEach(function (c) {
                let badgeClase = 'estado-programada';
                if (c.estado === 'Confirmada') badgeClase = 'estado-confirmada';
                if (c.estado === 'Atendida') badgeClase = 'estado-atendida';
                if (c.estado === 'Cancelada') badgeClase = 'estado-cancelada';
                if (c.estado === 'Inasistencia') badgeClase = 'estado-inasistencia';

                let detalle = c.observaciones_atencion || c.motivo_cancelacion || c.motivo_consulta || '-';
                if (c.estado === 'Cancelada' && c.motivo_cancelacion) {
                    detalle = '<strong>Motivo:</strong> ' + c.motivo_cancelacion;
                }

                html += '<tr>' +
                    '<td><strong>' + c.codigo_cita + '</strong></td>' +
                    '<td>' + c.fecha + '</td>' +
                    '<td>' + c.hora_inicio.substring(0, 5) + ' - ' + c.hora_fin.substring(0, 5) + '</td>' +
                    '<td>' + (c.nombre_completo_medico || c.nombre_medico || c.medico_apellidos || '-') + '</td>' +
                    '<td>' + (c.nombre_especialidad || '-') + '</td>' +
                    '<td><span class="insignia-estado ' + badgeClase + '">' + c.estado + '</span></td>' +
                    '<td>' + detalle + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
            contenedor.innerHTML = html;
        } else {
            contenedor.innerHTML = '<div class="texto-centro texto-muted p-4"><i class="bi bi-info-circle"></i> No se registran citas previas para este paciente.</div>';
        }
    }
}

/**
 * Confirmación de cambio de estado / baja lógica
 * @param {number} idPaciente 
 * @param {string} nombreCompleto 
 * @param {boolean} estadoActual 
 */
function confirmarBajaPaciente(idPaciente, nombreCompleto, estadoActual) {
    const accionTexto = estadoActual ? 'desactivar' : 'activar';
    abrirModalConfirmacion({
        titulo: 'Confirmar Cambio de Estado',
        mensaje: '¿Está seguro de ' + accionTexto + ' al paciente ' + nombreCompleto + '? Sus citas previas y registros históricos se mantendrán en el sistema.',
        textoBoton: estadoActual ? 'Desactivar' : 'Activar',
        accionConfirmar: async function () {
            const respuesta = await peticionAjax(window.location.pathname, 'POST', {
                accion: 'cambiar_estado',
                id_paciente: idPaciente
            });
            if (respuesta && respuesta.exito) {
                window.location.href = window.location.pathname + '?mensaje=estado_actualizado';
            } else {
                mostrarNotificacionToast(respuesta.mensaje || 'Error al cambiar estado', 'peligro');
            }
        }
    });
}
