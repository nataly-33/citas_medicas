<?php
/**
 * Modal para Visualizar la Ficha Médica Completa del Paciente
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalFichaClinica">
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
            <div class="texto-centro texto-muted p-4">
                <i class="bi bi-hourglass-split"></i> Cargando ficha médica...
            </div>
        </div>

        <div class="modal-pie">
            <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalFichaClinica')">
                <i class="bi bi-x"></i>
                <span>Cerrar</span>
            </button>
        </div>
    </div>
</div>
