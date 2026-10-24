<?php
/**
 * Modal para Ver Detalle Completo de una Cita Médica
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalDetalleCita">
    <div class="modal-dialogo modal-dialogo-grande" style="max-width: 680px;">
        <div class="modal-cabecera">
            <div class="modal-titulo">
                <i class="bi bi-file-medical"></i>
                <span id="detalle_titulo">Detalle de la Cita Médica</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalDetalleCita')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="modal-cuerpo" id="cuerpoDetalleCita">
            <div class="texto-centro texto-muted p-4">
                <i class="bi bi-hourglass-split"></i> Cargando información detallada...
            </div>
        </div>

        <div class="modal-pie">
            <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalDetalleCita')">
                <i class="bi bi-x"></i>
                <span>Cerrar</span>
            </button>
        </div>
    </div>
</div>
