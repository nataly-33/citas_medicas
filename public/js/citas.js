/**
 * JavaScript Módulo Citas Médicas - Clínica Vida Salud
 * Sistemas de Información 1 - UAGRM
 * Casos de Uso: CU07 (Disponibilidad), CU08 (Agendar), CU09 (Reprogramar), CU10 (Cancelar), CU11 (Atender)
 */

document.addEventListener('DOMContentLoaded', function () {
    // Inicializar listeners de agendamiento
    const formAgendar = document.getElementById('formAgendarCita');
    if (formAgendar) {
        formAgendar.addEventListener('submit', agendarCitaSubmit);

        const selectMedico = document.getElementById('cita_id_medico');
        const inputFecha = document.getElementById('cita_fecha');

        if (selectMedico) {
            selectMedico.addEventListener('change', function () {
                cargarEspecialidadesPorMedico(this.value);
                consultarTurnosDisponibles();
            });
        }

        if (inputFecha) {
            inputFecha.addEventListener('change', consultarTurnosDisponibles);
            // Restricción visual de fecha mínima (hoy)
            const hoy = new Date().toISOString().split('T')[0];
            inputFecha.setAttribute('min', hoy);
        }
    }

    // Inicializar listeners de reprogramación
    const formReprogramar = document.getElementById('formReprogramarCita');
    if (formReprogramar) {
        formReprogramar.addEventListener('submit', reprogramarCitaSubmit);

        const inputFechaReprog = document.getElementById('reprogramar_fecha');
        if (inputFechaReprog) {
            inputFechaReprog.addEventListener('change', consultarTurnosReprogramacion);
            const hoy = new Date().toISOString().split('T')[0];
            inputFechaReprog.setAttribute('min', hoy);
        }
    }

    // Inicializar listeners de cancelación
    const formCancelar = document.getElementById('formCancelarCita');
    if (formCancelar) {
        formCancelar.addEventListener('submit', cancelarCitaSubmit);
    }

    // Inicializar listeners de atención médica
    const formAtender = document.getElementById('formAtenderCita');
    if (formAtender) {
        formAtender.addEventListener('submit', atenderCitaSubmit);
    }
});

/**
 * Abre modal de Agendamiento
 */
function abrirModalAgendarCita() {
    const form = document.getElementById('formAgendarCita');
    if (form) {
        form.reset();
        limpiarErroresFormulario(form);
    }
    const hoy = new Date().toISOString().split('T')[0];
    const inputFecha = document.getElementById('cita_fecha');
    if (inputFecha) {
        inputFecha.value = hoy;
    }
    ocultarAlertaModal('modalAgendarCita');
    abrirModal('modalAgendarCita');
    consultarTurnosDisponibles();
}

/**
 * CU07: Consulta turnos libres en tiempo real según Médico y Fecha
 */
async function consultarTurnosDisponibles() {
    const selectMedico = document.getElementById('cita_id_medico');
    const inputFecha = document.getElementById('cita_fecha');
    const selectTurno = document.getElementById('cita_turno');

    if (!selectMedico || !inputFecha || !selectTurno) return;

    const idMedico = selectMedico.value;
    const fecha = inputFecha.value;

    limpiarErrorCampo(inputFecha);
    selectTurno.innerHTML = '<option value="">-- Seleccione un turno libre --</option>';

    if (!idMedico || !fecha) return;

    // Validación de fecha no pasada
    const fechaActual = new Date().toISOString().split('T')[0];
    if (fecha < fechaActual) {
        mostrarErrorCampo('cita_fecha', 'No es posible agendar en fechas pasadas.');
        return;
    }

    selectTurno.innerHTML = '<option value="">Consultando disponibilidad...</option>';

    const url = window.location.pathname + '?accion=disponibilidad&id_medico=' + idMedico + '&fecha=' + fecha;
    const respuesta = await peticionAjax(url, 'GET');

    selectTurno.innerHTML = '';

    if (respuesta && respuesta.exito && respuesta.turnos && respuesta.turnos.length > 0) {
        const opcionDefecto = document.createElement('option');
        opcionDefecto.value = '';
        opcionDefecto.textContent = '-- Seleccione un turno disponible (' + respuesta.turnos.length + ' libres) --';
        selectTurno.appendChild(opcionDefecto);

        respuesta.turnos.forEach(function (turno) {
            const opt = document.createElement('option');
            const hInicio = turno.hora_inicio.substring(0, 5);
            const hFin = turno.hora_fin.substring(0, 5);
            opt.value = turno.hora_inicio + '|' + turno.hora_fin;
            opt.textContent = hInicio + ' - ' + hFin;
            selectTurno.appendChild(opt);
        });
    } else {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No hay turnos disponibles para la fecha seleccionada';
        selectTurno.appendChild(opt);
    }
}

/**
 * Filtra dinámicamente las especialidades asociadas al médico seleccionado
 */
function cargarEspecialidadesPorMedico(idMedico) {
    const selectEspecialidad = document.getElementById('cita_id_especialidad');
    if (!selectEspecialidad) return;

    const selectMedico = document.getElementById('cita_id_medico');
    const optionSeleccionada = selectMedico.options[selectMedico.selectedIndex];
    const especialidadesData = optionSeleccionada ? optionSeleccionada.getAttribute('data-especialidades') : '';

    if (especialidadesData) {
        const ids = especialidadesData.split(',');
        for (let i = 0; i < selectEspecialidad.options.length; i++) {
            const opt = selectEspecialidad.options[i];
            if (opt.value === '') {
                opt.style.display = 'block';
            } else if (ids.includes(opt.value)) {
                opt.style.display = 'block';
            } else {
                opt.style.display = 'none';
            }
        }
    } else {
        for (let i = 0; i < selectEspecialidad.options.length; i++) {
            selectEspecialidad.options[i].style.display = 'block';
        }
    }
}

/**
 * CU08: Programar Cita Médica
 */
async function agendarCitaSubmit(e) {
    e.preventDefault();
    const form = e.target;
    limpiarErroresFormulario(form);
    ocultarAlertaModal('modalAgendarCita');

    const idPaciente = document.getElementById('cita_id_paciente').value;
    const idMedico = document.getElementById('cita_id_medico').value;
    const idEspecialidad = document.getElementById('cita_id_especialidad').value;
    const fecha = document.getElementById('cita_fecha').value;
    const turno = document.getElementById('cita_turno').value;
    const motivo = document.getElementById('cita_motivo').value.trim();

    let tieneErrores = false;

    if (!idPaciente) {
        mostrarErrorCampo('cita_id_paciente', 'Debe seleccionar un paciente.');
        tieneErrores = true;
    }
    if (!idMedico) {
        mostrarErrorCampo('cita_id_medico', 'Debe seleccionar un médico.');
        tieneErrores = true;
    }
    if (!idEspecialidad) {
        mostrarErrorCampo('cita_id_especialidad', 'Debe seleccionar una especialidad.');
        tieneErrores = true;
    }
    if (!fecha) {
        mostrarErrorCampo('cita_fecha', 'Debe seleccionar una fecha.');
        tieneErrores = true;
    } else {
        const hoy = new Date().toISOString().split('T')[0];
        if (fecha < hoy) {
            mostrarErrorCampo('cita_fecha', 'La fecha de la cita no puede ser anterior a hoy.');
            tieneErrores = true;
        }
    }
    if (!turno) {
        mostrarErrorCampo('cita_turno', 'Debe seleccionar un turno horario disponible.');
        tieneErrores = true;
    }

    if (tieneErrores) return;

    const [horaInicio, horaFin] = turno.split('|');

    const datos = {
        accion: 'agendar',
        id_paciente: idPaciente,
        id_medico: idMedico,
        id_especialidad: idEspecialidad,
        fecha: fecha,
        hora_inicio: horaInicio,
        hora_fin: horaFin,
        motivo_consulta: motivo
    };

    const respuesta = await peticionAjax(window.location.pathname, 'POST', datos);

    if (respuesta && respuesta.exito) {
        cerrarModal('modalAgendarCita');
        window.location.href = window.location.pathname + '?mensaje=cita_creada';
    } else {
        // Regla Inviolable: Si el servidor rechaza (solapamiento horario, etc.), el modal NO debe cerrarse
        const errorMsg = respuesta.mensaje || 'Error al programar la cita.';
        mostrarAlertaModal('modalAgendarCita', errorMsg);
        consultarTurnosDisponibles(); // Refrescar turnos libres
    }
}

/**
 * CU09: Abre modal de Reprogramación
 */
function abrirModalReprogramar(idCita, codigoCita, idMedico, nombreMedico, fechaActual, horaActual) {
    const form = document.getElementById('formReprogramarCita');
    if (form) {
        form.reset();
        limpiarErroresFormulario(form);
    }
    ocultarAlertaModal('modalReprogramarCita');

    document.getElementById('reprogramar_id_cita').value = idCita;
    document.getElementById('reprogramar_id_medico').value = idMedico;
    document.getElementById('reprogramar_info_cita').textContent = codigoCita + ' - Dr(a). ' + nombreMedico;
    document.getElementById('reprogramar_info_actual').textContent = 'Horario actual: ' + fechaActual + ' (' + horaActual + ')';

    const inputFecha = document.getElementById('reprogramar_fecha');
    inputFecha.value = fechaActual;

    abrirModal('modalReprogramarCita');
    consultarTurnosReprogramacion();
}

/**
 * Consulta turnos para la reprogramación
 */
async function consultarTurnosReprogramacion() {
    const idMedico = document.getElementById('reprogramar_id_medico').value;
    const fecha = document.getElementById('reprogramar_fecha').value;
    const selectTurno = document.getElementById('reprogramar_turno');

    if (!idMedico || !fecha || !selectTurno) return;

    limpiarErrorCampo('reprogramar_fecha');
    selectTurno.innerHTML = '<option value="">Consultando disponibilidad...</option>';

    const idCita = document.getElementById('reprogramar_id_cita')?.value || '';
    const url = window.location.pathname + '?accion=disponibilidad&id_medico=' + idMedico + '&fecha=' + fecha + (idCita ? '&excluir_id_cita=' + idCita : '');
    const respuesta = await peticionAjax(url, 'GET');

    selectTurno.innerHTML = '';

    if (respuesta && respuesta.exito && respuesta.turnos && respuesta.turnos.length > 0) {
        const def = document.createElement('option');
        def.value = '';
        def.textContent = '-- Seleccione nuevo turno (' + respuesta.turnos.length + ' libres) --';
        selectTurno.appendChild(def);

        respuesta.turnos.forEach(function (turno) {
            const opt = document.createElement('option');
            opt.value = turno.hora_inicio + '|' + turno.hora_fin;
            opt.textContent = turno.hora_inicio.substring(0, 5) + ' - ' + turno.hora_fin.substring(0, 5);
            selectTurno.appendChild(opt);
        });
    } else {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No hay turnos disponibles para esta fecha';
        selectTurno.appendChild(opt);
    }
}

/**
 * Envío de reprogramación
 */
async function reprogramarCitaSubmit(e) {
    e.preventDefault();
    const form = e.target;
    limpiarErroresFormulario(form);
    ocultarAlertaModal('modalReprogramarCita');

    const idCita = document.getElementById('reprogramar_id_cita').value;
    const fecha = document.getElementById('reprogramar_fecha').value;
    const turno = document.getElementById('reprogramar_turno').value;

    let tieneErrores = false;
    if (!fecha) {
        mostrarErrorCampo('reprogramar_fecha', 'Seleccione la nueva fecha.');
        tieneErrores = true;
    }
    if (!turno) {
        mostrarErrorCampo('reprogramar_turno', 'Seleccione el nuevo horario disponible.');
        tieneErrores = true;
    }

    if (tieneErrores) return;

    const [horaInicio, horaFin] = turno.split('|');

    const respuesta = await peticionAjax(window.location.pathname, 'POST', {
        accion: 'reprogramar',
        id_cita: idCita,
        fecha: fecha,
        hora_inicio: horaInicio,
        hora_fin: horaFin
    });

    if (respuesta && respuesta.exito) {
        cerrarModal('modalReprogramarCita');
        window.location.href = window.location.pathname + '?mensaje=cita_reprogramada';
    } else {
        mostrarAlertaModal('modalReprogramarCita', respuesta.mensaje || 'Error al reprogramar la cita.');
        consultarTurnosReprogramacion();
    }
}

/**
 * CU10: Abre modal de Cancelación de Cita con motivo obligatorio
 */
function abrirModalCancelar(idCita, codigoCita, paciente, medico, fechaHora) {
    const form = document.getElementById('formCancelarCita');
    if (form) {
        form.reset();
        limpiarErroresFormulario(form);
    }
    ocultarAlertaModal('modalCancelarCita');

    document.getElementById('cancelar_id_cita').value = idCita;
    document.getElementById('cancelar_info_cita').textContent = codigoCita + ' - ' + paciente;
    document.getElementById('cancelar_info_detalle').textContent = 'Médico: ' + medico + ' | ' + fechaHora;

    abrirModal('modalCancelarCita');
}

/**
 * Envío de cancelación de cita (Liberación inmediata del cupo)
 */
async function cancelarCitaSubmit(e) {
    e.preventDefault();
    const form = e.target;
    limpiarErroresFormulario(form);
    ocultarAlertaModal('modalCancelarCita');

    const idCita = document.getElementById('cancelar_id_cita').value;
    const motivo = document.getElementById('cancelar_motivo').value.trim();

    if (!motivo) {
        mostrarErrorCampo('cancelar_motivo', 'El motivo de cancelación es obligatorio.');
        return;
    }

    const respuesta = await peticionAjax(window.location.pathname, 'POST', {
        accion: 'cancelar',
        id_cita: idCita,
        motivo_cancelacion: motivo
    });

    if (respuesta && respuesta.exito) {
        cerrarModal('modalCancelarCita');
        window.location.href = window.location.pathname + '?mensaje=cita_cancelada';
    } else {
        mostrarAlertaModal('modalCancelarCita', respuesta.mensaje || 'Error al cancelar la cita.');
    }
}

/**
 * CU11: Abre modal de Atención Médica para ingresar notas clínicas
 */
function abrirModalAtender(idCita, codigoCita, paciente) {
    const form = document.getElementById('formAtenderCita');
    if (form) {
        form.reset();
        limpiarErroresFormulario(form);
    }
    ocultarAlertaModal('modalAtenderCita');

    document.getElementById('atender_id_cita').value = idCita;
    document.getElementById('atender_info_cita').textContent = codigoCita + ' - Paciente: ' + paciente;

    abrirModal('modalAtenderCita');
}

/**
 * Envío de atención médica completada
 */
async function atenderCitaSubmit(e) {
    e.preventDefault();
    const form = e.target;
    limpiarErroresFormulario(form);
    ocultarAlertaModal('modalAtenderCita');

    const idCita = document.getElementById('atender_id_cita').value;
    const observaciones = document.getElementById('atender_observaciones').value.trim();

    if (!observaciones) {
        mostrarErrorCampo('atender_observaciones', 'Las notas de atención y diagnóstico son obligatorias.');
        return;
    }

    const respuesta = await peticionAjax(window.location.pathname, 'POST', {
        accion: 'atender',
        id_cita: idCita,
        observaciones_atencion: observaciones
    });

    if (respuesta && respuesta.exito) {
        cerrarModal('modalAtenderCita');
        window.location.href = window.location.pathname + '?mensaje=cita_atendida';
    } else {
        mostrarAlertaModal('modalAtenderCita', respuesta.mensaje || 'Error al registrar atención.');
    }
}

/**
 * CU11: Confirmar asistencia presencial del paciente
 */
async function confirmarAsistencia(idCita, codigoCita) {
    abrirModalConfirmacion({
        titulo: 'Confirmar Asistencia Presencial',
        mensaje: '¿Confirma que el paciente de la cita ' + codigoCita + ' se encuentra presente en la clínica?',
        textoBoton: 'Confirmar Asistencia',
        accionConfirmar: async function () {
            const respuesta = await peticionAjax(window.location.pathname, 'POST', {
                accion: 'confirmar_asistencia',
                id_cita: idCita
            });
            if (respuesta && respuesta.exito) {
                window.location.href = window.location.pathname + '?mensaje=asistencia_confirmada';
            } else {
                mostrarNotificacionToast(respuesta.mensaje || 'Error al confirmar asistencia', 'peligro');
            }
        }
    });
}

/**
 * Marcar inasistencia del paciente
 */
async function marcarInasistencia(idCita, codigoCita) {
    abrirModalConfirmacion({
        titulo: 'Registrar Inasistencia',
        mensaje: '¿Desea marcar como Inasistencia la cita ' + codigoCita + '?',
        textoBoton: 'Marcar Inasistencia',
        accionConfirmar: async function () {
            const respuesta = await peticionAjax(window.location.pathname, 'POST', {
                accion: 'inasistencia',
                id_cita: idCita
            });
            if (respuesta && respuesta.exito) {
                window.location.href = window.location.pathname + '?mensaje=inasistencia_registrada';
            } else {
                mostrarNotificacionToast(respuesta.mensaje || 'Error al registrar inasistencia', 'peligro');
            }
        }
    });
}
