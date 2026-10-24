<?php
/**
 * Modal para Cancelación Justificada de Cita Médica
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalCancelarCita">
    <div class="modal-dialogo">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalCancelarTitulo">
                <i class="bi bi-calendar-x" style="color: var(--color-peligro);"></i>
                <span>Cancelar Cita Médica</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalCancelarCita')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="formCancelarCita" method="POST" action="index.php">
            <input type="hidden" name="accion" value="cancelar">
            <input type="hidden" name="id_cita" id="cancelar_id_cita" value="">

            <div class="modal-cuerpo">
                <div class="alerta-modal-peligro">
                    <span class="mensaje-alerta-modal"></span>
                </div>

                <div style="background-color: var(--color-peligro-fondo); border: 1px solid var(--color-peligro-borde); padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <div id="cancelar_info_cita" style="font-weight: 600; color: var(--color-peligro);"></div>
                    <div id="cancelar_info_detalle" style="font-size: 0.8125rem; color: #7F1D1D; margin-top: 0.25rem;"></div>
                    <div style="font-size: 0.75rem; color: #991B1B; margin-top: 0.5rem;">
                        <i class="bi bi-info-circle"></i> Al confirmar la cancelación, el horario quedará inmediatamente liberado para nuevos pacientes.
                    </div>
                </div>

                <div class="grupo-campo">
                    <label for="cancelar_motivo" class="etiqueta-campo">
                        Motivo Obligatorio de Cancelación<span class="etiqueta-requerido">*</span>
                    </label>
                    <textarea name="motivo_cancelacion" id="cancelar_motivo" class="control-campo" rows="3" 
                              placeholder="Indique el motivo por el cual se cancela la cita..." required></textarea>
                </div>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalCancelarCita')">
                    <i class="bi bi-x"></i>
                    <span>Volver</span>
                </button>
                <button type="submit" class="btn btn-peligro" id="btnConfirmarCancelar">
                    <i class="bi bi-calendar-x"></i>
                    <span>Confirmar Cancelación</span>
                </button>
            </div>
        </form>
    </div>
</div>
