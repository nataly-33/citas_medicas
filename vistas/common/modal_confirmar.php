<?php
/**
 * Modal Genérico de Confirmación para Bajas Lógicas y Acciones Críticas
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalConfirmacionGenerico">
    <div class="modal-dialogo" style="max-width: 440px;">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalConfirmacionTitulo">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Confirmar Acción</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalConfirmacionGenerico')" title="Cerrar ventana">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="modal-cuerpo">
            <p id="modalConfirmacionMensaje" style="color: var(--color-texto); font-size: 0.875rem;">
                ¿Está seguro de proceder con esta acción?
            </p>
        </div>

        <div class="modal-pie">
            <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalConfirmacionGenerico')">
                <i class="bi bi-x"></i>
                <span>Cancelar</span>
            </button>
            <button type="button" class="btn btn-peligro" id="btnConfirmacionAceptar" onclick="ejecutarConfirmacion()">
                <i class="bi bi-check2"></i>
                <span>Confirmar</span>
            </button>
        </div>
    </div>
</div>
