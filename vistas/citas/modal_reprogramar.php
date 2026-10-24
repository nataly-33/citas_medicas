<?php
/**
 * Modal para Reprogramar Cita Médica (CU09)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalReprogramarCita">
    <div class="modal-dialogo">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalReprogramarTitulo">
                <i class="bi bi-calendar2-range"></i>
                <span>Reprogramar Cita Médica</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalReprogramarCita')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="formReprogramarCita" method="POST" action="index.php">
            <input type="hidden" name="accion" value="reprogramar">
            <input type="hidden" name="id_cita" id="reprogramar_id_cita" value="">
            <input type="hidden" name="id_medico" id="reprogramar_id_medico" value="">

            <div class="modal-cuerpo">
                <div class="alerta-modal-peligro">
                    <span class="mensaje-alerta-modal"></span>
                </div>

                <div style="background-color: var(--color-fondo-secundario); padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid var(--color-borde);">
                    <div id="reprogramar_info_cita" style="font-weight: 600; color: var(--color-primario);"></div>
                    <div id="reprogramar_info_actual" style="font-size: 0.8125rem; color: var(--color-texto-muted); margin-top: 0.25rem;"></div>
                </div>

                <div class="grupo-campo">
                    <label for="reprogramar_fecha" class="etiqueta-campo">
                        Nueva Fecha de Consulta<span class="etiqueta-requerido">*</span>
                    </label>
                    <input type="date" name="fecha" id="reprogramar_fecha" class="control-campo" required>
                </div>

                <div class="grupo-campo">
                    <label for="reprogramar_turno" class="etiqueta-campo">
                        Nuevo Horario Libre<span class="etiqueta-requerido">*</span>
                    </label>
                    <select name="turno" id="reprogramar_turno" class="control-campo" required>
                        <option value="">-- Seleccione una fecha --</option>
                    </select>
                </div>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalReprogramarCita')">
                    <i class="bi bi-x"></i>
                    <span>Cancelar</span>
                </button>
                <button type="submit" class="btn btn-primario" id="btnConfirmarReprogramar">
                    <i class="bi bi-check2"></i>
                    <span>Confirmar Reprogramación</span>
                </button>
            </div>
        </form>
    </div>
</div>
