<?php
/**
 * Modal para Registrar la Atención Médica y Notas Clínicas (CU11)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalAtenderCita">
    <div class="modal-dialogo modal-dialogo-grande">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalAtenderTitulo">
                <i class="bi bi-clipboard2-check"></i>
                <span>Registrar Atención Médica</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalAtenderCita')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="formAtenderCita" method="POST" action="index.php">
            <input type="hidden" name="accion" value="atender">
            <input type="hidden" name="id_cita" id="atender_id_cita" value="">

            <div class="modal-cuerpo">
                <div class="alerta-modal-peligro">
                    <span class="mensaje-alerta-modal"></span>
                </div>

                <div style="background-color: var(--color-exito-fondo); border: 1px solid var(--color-exito-borde); padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <div id="atender_info_cita" style="font-weight: 600; color: var(--color-primario);"></div>
                    <div style="font-size: 0.8125rem; color: #14532D; margin-top: 0.25rem;">
                        Al guardar la atención, el estado de la cita pasará a <strong>Atendida</strong> y no podrá cancelarse.
                    </div>
                </div>

                <!-- Ficha rápida de alertas clínicas del paciente (Sangre y Alergias) -->
                <div id="atender_datos_clinicos_paciente" style="display: none; background-color: #FEF3C7; border: 1px solid #FDE68A; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.8125rem;">
                    <div style="font-weight: 700; color: #92400E; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="bi bi-heart-pulse"></i> Alertas Clínicas del Paciente
                    </div>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                        <div><strong>Grupo Sanguíneo:</strong> <span id="atender_tipo_sangre" class="badge-tipo-sangre">-</span></div>
                        <div><strong>Alergias Conocidas:</strong> <span id="atender_alergias" style="color: #B91C1C; font-weight: 600;">-</span></div>
                    </div>
                </div>

                <div class="grupo-campo">
                    <label for="atender_observaciones" class="etiqueta-campo">
                        Diagnóstico y Observaciones Clínicas<span class="etiqueta-requerido">*</span>
                    </label>
                    <textarea name="observaciones_atencion" id="atender_observaciones" class="control-campo" rows="5" 
                              placeholder="Describa el diagnóstico médico, tratamiento recetado o recomendaciones para el paciente..." required></textarea>
                </div>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalAtenderCita')">
                    <i class="bi bi-x"></i>
                    <span>Cancelar</span>
                </button>
                <button type="submit" class="btn btn-primario" id="btnConfirmarAtender">
                    <i class="bi bi-check2-circle"></i>
                    <span>Completar Atención</span>
                </button>
            </div>
        </form>
    </div>
</div>
