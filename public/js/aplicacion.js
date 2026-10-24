/**
 * JavaScript Principal - Clínica Vida Salud
 * Sistemas de Información 1 - UAGRM
 * Cero Emojis - Control de Modales, Validaciones y Peticiones Asíncronas
 */

document.addEventListener('DOMContentLoaded', function () {
    // Escuchar teclas de escape para cerrar modales abiertos
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const modalAbierto = document.querySelector('.modal-fondo.abierto');
            if (modalAbierto) {
                cerrarModal(modalAbierto.id);
            }
        }
    });

    // Cerrar modal al hacer clic en el fondo gris exterior
    document.querySelectorAll('.modal-fondo').forEach(function (fondo) {
        fondo.addEventListener('click', function (e) {
            if (e.target === fondo) {
                cerrarModal(fondo.id);
            }
        });
    });

    // Auto-ocultar alertas temporales después de 6 segundos
    document.querySelectorAll('.alerta-autodesvanecer').forEach(function (alerta) {
        setTimeout(function () {
            alerta.style.transition = 'opacity 0.4s ease';
            alerta.style.opacity = '0';
            setTimeout(function () { alerta.remove(); }, 400);
        }, 6000);
    });
});

/**
 * Abre un modal por su ID
 * @param {string} idModal 
 */
function abrirModal(idModal) {
    const modal = document.getElementById(idModal);
    if (!modal) return;
    ocultarAlertaModal(idModal);
    const form = modal.querySelector('form');
    if (form) {
        limpiarErroresFormulario(form);
    }
    modal.classList.add('abierto');
    document.body.style.overflow = 'hidden';
}

/**
 * Cierra un modal por su ID
 * @param {string} idModal 
 */
function cerrarModal(idModal) {
    const modal = document.getElementById(idModal);
    if (!modal) return;
    modal.classList.remove('abierto');
    ocultarAlertaModal(idModal);
    document.body.style.overflow = '';
}

/**
 * Muestra el mensaje en rojo institucional debajo del input y resalta el borde
 * @param {HTMLElement|string} elementoInput 
 * @param {string} mensaje 
 */
function mostrarErrorCampo(elementoInput, mensaje) {
    const input = typeof elementoInput === 'string' ? document.getElementById(elementoInput) : elementoInput;
    if (!input) return;

    input.classList.add('campo-error');
    
    // Buscar o crear el contenedor .texto-error-campo
    let errorSpan = input.parentNode.querySelector('.texto-error-campo');
    if (!errorSpan) {
        errorSpan = document.createElement('span');
        errorSpan.className = 'texto-error-campo';
        input.parentNode.appendChild(errorSpan);
    }
    errorSpan.textContent = mensaje;
    errorSpan.style.display = 'block';
}

/**
 * Limpia el estado de error de un input específico
 * @param {HTMLElement|string} elementoInput 
 */
function limpiarErrorCampo(elementoInput) {
    const input = typeof elementoInput === 'string' ? document.getElementById(elementoInput) : elementoInput;
    if (!input) return;

    input.classList.remove('campo-error');
    const errorSpan = input.parentNode.querySelector('.texto-error-campo');
    if (errorSpan) {
        errorSpan.textContent = '';
        errorSpan.style.display = 'none';
    }
}

/**
 * Limpia todos los errores y bordes rojos de un formulario
 * @param {HTMLFormElement} formulario 
 */
function limpiarErroresFormulario(formulario) {
    if (!formulario) return;
    formulario.querySelectorAll('.campo-error').forEach(function (el) {
        el.classList.remove('campo-error');
    });
    formulario.querySelectorAll('.texto-error-campo').forEach(function (el) {
        el.textContent = '';
        el.style.display = 'none';
    });
}

/**
 * Muestra banner rojo en la cabecera del modal sin cerrarlo (Regla Inviolable)
 * @param {string} idModal 
 * @param {string} mensaje 
 */
function mostrarAlertaModal(idModal, mensaje) {
    const modal = document.getElementById(idModal);
    if (!modal) return;
    const alerta = modal.querySelector('.alerta-modal-peligro');
    if (alerta) {
        const textoSpan = alerta.querySelector('.mensaje-alerta-modal') || alerta;
        textoSpan.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + mensaje;
        alerta.classList.add('visible');
    }
}

/**
 * Oculta banner rojo del modal
 * @param {string} idModal 
 */
function ocultarAlertaModal(idModal) {
    const modal = document.getElementById(idModal);
    if (!modal) return;
    const alerta = modal.querySelector('.alerta-modal-peligro');
    if (alerta) {
        alerta.classList.remove('visible');
    }
}

/**
 * Modal genérico de confirmación para bajas lógicas y acciones críticas
 * @param {Object} opciones { titulo, mensaje, textoBoton, accionConfirmar }
 */
let accionConfirmarPendiente = null;
function abrirModalConfirmacion(opciones) {
    const modal = document.getElementById('modalConfirmacionGenerico');
    if (!modal) return;

    const elTitulo = document.getElementById('modalConfirmacionTitulo');
    const elMensaje = document.getElementById('modalConfirmacionMensaje');
    const btnConfirmar = document.getElementById('btnConfirmacionAceptar');

    if (elTitulo && opciones.titulo) elTitulo.textContent = opciones.titulo;
    if (elMensaje && opciones.mensaje) elMensaje.textContent = opciones.mensaje;
    if (btnConfirmar && opciones.textoBoton) btnConfirmar.textContent = opciones.textoBoton;

    accionConfirmarPendiente = opciones.accionConfirmar || null;
    abrirModal('modalConfirmacionGenerico');
}

function ejecutarConfirmacion() {
    if (typeof accionConfirmarPendiente === 'function') {
        accionConfirmarPendiente();
    }
    cerrarModal('modalConfirmacionGenerico');
}

/**
 * Helper AJAX robusto para Fetch API
 */
async function peticionAjax(url, metodo = 'POST', datos = {}) {
    const opciones = {
        method: metodo,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    if (metodo.toUpperCase() === 'POST') {
        if (datos instanceof FormData) {
            opciones.body = datos;
        } else {
            opciones.headers['Content-Type'] = 'application/json';
            opciones.body = JSON.stringify(datos);
        }
    }

    try {
        const respuesta = await fetch(url, opciones);
        const json = await respuesta.json();
        return json;
    } catch (error) {
        console.error('Error en peticionAjax:', error);
        return {
            exito: false,
            mensaje: 'Error de comunicación con el servidor. Intente nuevamente.'
        };
    }
}

/**
 * Muestra una notificación institucional temporal en pantalla (reemplazo sin alert nativo)
 * @param {string} mensaje 
 * @param {string} tipo ('exito' | 'peligro' | 'advertencia' | 'info')
 */
function mostrarNotificacionToast(mensaje, tipo = 'exito') {
    let contenedor = document.getElementById('contenedorToastsVidaSalud');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'contenedorToastsVidaSalud';
        contenedor.style.position = 'fixed';
        contenedor.style.bottom = '20px';
        contenedor.style.right = '20px';
        contenedor.style.zIndex = '9999';
        contenedor.style.display = 'flex';
        contenedor.style.flexDirection = 'column';
        contenedor.style.gap = '0.5rem';
        contenedor.style.maxWidth = '380px';
        document.body.appendChild(contenedor);
    }

    const toast = document.createElement('div');
    toast.className = 'alerta alerta-' + (tipo === 'error' ? 'peligro' : tipo);
    toast.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1)';
    toast.style.margin = '0';
    toast.style.animation = 'modalAparecer 0.2s ease-out';

    let icono = 'bi-check-circle-fill';
    if (tipo === 'peligro' || tipo === 'error') icono = 'bi-exclamation-triangle-fill';
    if (tipo === 'advertencia') icono = 'bi-exclamation-circle-fill';
    if (tipo === 'info') icono = 'bi-info-circle-fill';

    toast.innerHTML = '<i class="bi ' + icono + '"></i><span>' + mensaje + '</span>';
    contenedor.appendChild(toast);

    setTimeout(function () {
        toast.style.transition = 'opacity 0.4s ease';
        toast.style.opacity = '0';
        setTimeout(function () { toast.remove(); }, 400);
    }, 4500);
}

/**
 * Determina la ruta base para peticiones AJAX dinámicas entre módulos
 */
function obtenerRutaBase() {
    if (typeof window.URL_BASE === 'string') {
        return window.URL_BASE;
    }
    if (typeof URL_BASE !== 'undefined' && typeof URL_BASE === 'string') {
        return URL_BASE;
    }
    const path = window.location.pathname;
    if (path.startsWith('/citas_medicas')) {
        return '/citas_medicas';
    }
    return '';
}

/**
 * Abre modal de Ficha Médica Completa desde cualquier módulo
 * @param {number} idPaciente 
 */
async function abrirFichaMedica(idPaciente) {
    let modal = document.getElementById('modalFichaClinica');
    if (!modal) {
        const div = document.createElement('div');
        div.className = 'modal-fondo';
        div.id = 'modalFichaClinica';
        div.innerHTML = `
            <div class="modal-dialogo modal-dialogo-grande" style="max-width: 760px;">
                <div class="modal-cabecera">
                    <div class="modal-titulo">
                        <i class="bi bi-file-earmark-medical"></i>
                        <span id="ficha_titulo">Ficha Médica del Paciente</span>
                    </div>
                    <button type="button" class="modal-cerrar" onclick="cerrarModal('modalFichaClinica')" title="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-cuerpo" id="cuerpoFichaClinica">
                    <div class="texto-centro texto-muted p-4"><i class="bi bi-hourglass-split"></i> Cargando ficha médica...</div>
                </div>
                <div class="modal-pie">
                    <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalFichaClinica')">
                        <i class="bi bi-x"></i>
                        <span>Cerrar</span>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(div);
        modal = div;
    }

    const contenedor = document.getElementById('cuerpoFichaClinica');
    if (contenedor) {
        contenedor.innerHTML = '<div class="texto-centro texto-muted p-4"><i class="bi bi-hourglass-split"></i> Cargando ficha médica...</div>';
    }
    abrirModal('modalFichaClinica');

    const rutaBase = obtenerRutaBase();
    const res = await peticionAjax(rutaBase + '/vistas/pacientes/index.php?accion=ficha_clinica&id_paciente=' + idPaciente, 'GET');

    if (contenedor && res && res.exito && res.paciente) {
        const p = res.paciente;
        const tituloElem = document.getElementById('ficha_titulo');
        if (tituloElem) {
            tituloElem.textContent = 'Ficha Médica: ' + p.codigo + ' - ' + p.nombres + ' ' + p.apellidos;
        }

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
                    <div class="ficha-campo"><span class="ficha-etiqueta">Teléfono:</span><span class="ficha-valor">${p.telefono || '-'}</span></div>
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
            contenedor.innerHTML = '<div class="alerta alerta-peligro"><i class="bi bi-exclamation-triangle"></i> ' + (res?.mensaje || 'No se pudo cargar la información médica del paciente.') + '</div>';
        }
    }
}

/**
 * Consulta el historial de atenciones de un paciente desde cualquier módulo
 * @param {number} idPaciente 
 * @param {string} nombreCompleto 
 */
async function abrirHistorialPaciente(idPaciente, nombreCompleto = '') {
    let modal = document.getElementById('modalHistorialPaciente');
    if (!modal) {
        const div = document.createElement('div');
        div.className = 'modal-fondo';
        div.id = 'modalHistorialPaciente';
        div.innerHTML = `
            <div class="modal-dialogo modal-dialogo-grande" style="max-width: 820px;">
                <div class="modal-cabecera">
                    <div class="modal-titulo" id="modalHistorialTitulo">
                        <i class="bi bi-clock-history"></i>
                        <span>Historial de Citas Médicas</span>
                    </div>
                    <button type="button" class="modal-cerrar" onclick="cerrarModal('modalHistorialPaciente')" title="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-cuerpo" id="contenedorHistorialCitas" style="max-height: 60vh; overflow-y: auto;">
                    <div class="texto-centro texto-muted p-4"><i class="bi bi-hourglass-split"></i> Cargando historial...</div>
                </div>
                <div class="modal-pie">
                    <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalHistorialPaciente')">
                        <i class="bi bi-x"></i>
                        <span>Cerrar</span>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(div);
        modal = div;
    }

    const titulo = document.getElementById('modalHistorialTitulo');
    if (titulo) {
        titulo.innerHTML = '<i class="bi bi-clock-history"></i> Historial de Citas' + (nombreCompleto ? ': ' + nombreCompleto : '');
    }

    const contenedor = document.getElementById('contenedorHistorialCitas');
    if (contenedor) {
        contenedor.innerHTML = '<div class="texto-centro p-4"><i class="bi bi-hourglass-split"></i> Cargando historial...</div>';
    }

    abrirModal('modalHistorialPaciente');

    const rutaBase = obtenerRutaBase();
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
