<?php
/**
 * Modal Formulario para Registrar y Editar Médico (CU05)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalMedico">
    <div class="modal-dialogo modal-dialogo-grande">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalMedicoTitulo">
                <i class="bi bi-person-badge"></i>
                <span>Nuevo Médico</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalMedico')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="formMedico" method="POST" action="index.php">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" id="medico_id" value="">

            <div class="modal-cuerpo" style="max-height: 75vh; overflow-y: auto;">
                <div class="alerta-modal-peligro">
                    <span class="mensaje-alerta-modal"></span>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="medico_registro_profesional" class="etiqueta-campo">
                            Registro Profesional / Matrícula<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="registro_profesional" id="medico_registro_profesional" class="control-campo" placeholder="ej. MP-30412" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="medico_telefono" class="etiqueta-campo">
                            Teléfono / Celular<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="telefono" id="medico_telefono" class="control-campo" placeholder="ej. 77012345 (7 a 10 dígitos)" required>
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="medico_nombres" class="etiqueta-campo">
                            Nombres<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="nombres" id="medico_nombres" class="control-campo" placeholder="ej. Carlos" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="medico_apellidos" class="etiqueta-campo">
                            Apellidos<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="apellidos" id="medico_apellidos" class="control-campo" placeholder="ej. Justiniano Vaca" required>
                    </div>
                </div>

                <!-- Credenciales de Acceso para el Médico -->
                <div style="background-color: var(--color-fondo-secundario); border: 1px solid var(--color-borde); padding: 0.875rem; border-radius: 6px; margin-bottom: 1rem;">
                    <div style="font-weight: 700; color: var(--color-primario); font-size: 0.8125rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="bi bi-shield-lock"></i> Credenciales de Acceso al Sistema
                    </div>
                    <div class="fila-formulario" style="margin-bottom: 0;">
                        <div class="grupo-campo" style="margin-bottom: 0;">
                            <label for="medico_correo" class="etiqueta-campo">
                                Correo Electrónico (Usuario)<span class="etiqueta-requerido">*</span>
                            </label>
                            <input type="email" name="correo" id="medico_correo" class="control-campo" placeholder="ej. doctor@vidasalud.bo" required>
                        </div>

                        <div class="grupo-campo" id="bloque_medico_clave" style="margin-bottom: 0;">
                            <label for="medico_contrasena" class="etiqueta-campo" id="label_medico_clave">
                                Contraseña Inicial<span class="etiqueta-requerido">*</span>
                            </label>
                            <input type="password" name="contrasena" id="medico_contrasena" class="control-campo" placeholder="Ingrese cualquier contraseña para el médico">
                            <span class="texto-muted" id="ayuda_medico_clave" style="font-size: 0.75rem; display: none;">Dejar este campo vacío si no desea modificar la clave.</span>
                        </div>
                    </div>
                </div>

                <div class="grupo-campo">
                    <label class="etiqueta-campo">
                        Especialidades Médicas Asignadas<span class="etiqueta-requerido">*</span>
                    </label>
                    <div id="contenedorEspecialidadesCheckboxes" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--color-borde); border-radius: 6px; background-color: var(--color-fondo-secundario);">
                        <?php if (!empty($todasEspecialidades)): ?>
                            <?php foreach ($todasEspecialidades as $esp): ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; cursor: pointer;">
                                    <input type="checkbox" name="especialidades[]" value="<?= $esp['id'] ?>" class="check-especialidad">
                                    <span><?= htmlspecialchars($esp['nombre']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="texto-muted">No hay especialidades configuradas.</span>
                        <?php endif; ?>
                    </div>
                    <span class="texto-error-campo" id="error_medico_especialidades"></span>
                </div>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalMedico')">
                    <i class="bi bi-x"></i>
                    <span>Cancelar</span>
                </button>
                <button type="submit" class="btn btn-primario" id="btnGuardarMedico">
                    <i class="bi bi-check2"></i>
                    <span>Guardar Médico</span>
                </button>
            </div>
        </form>
    </div>
</div>
