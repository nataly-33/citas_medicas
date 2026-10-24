<?php
/**
 * Modal para Visualizar el Historial Clínico de Citas de un Paciente (CU12)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalHistorialPaciente">
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
            <div class="texto-centro texto-muted p-4">
                <i class="bi bi-hourglass-split"></i> Cargando citas históricas...
            </div>
        </div>

        <div class="modal-pie">
            <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalHistorialPaciente')">
                <i class="bi bi-x"></i>
                <span>Cerrar</span>
            </button>
        </div>
    </div>
</div>
