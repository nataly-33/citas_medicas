<?php
/**
 * Modal para Programar Cita Médica (CU08) con Consulta Dinámica de Disponibilidad (CU07)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalAgendarCita">
    <div class="modal-dialogo modal-dialogo-grande">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalAgendarTitulo">
                <i class="bi bi-calendar-plus"></i>
                <span>Programar Cita Médica</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalAgendarCita')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="formAgendarCita" method="POST" action="index.php">
            <input type="hidden" name="accion" value="agendar">

            <div class="modal-cuerpo">
                <!-- Regla Inviolable: Alerta en la cabecera si el servidor rechaza (solapamiento horario, etc.), el modal NO debe cerrarse -->
                <div class="alerta-modal-peligro">
                    <span class="mensaje-alerta-modal"></span>
                </div>

                <!-- Paciente: Selector para operadores o Tarjeta bloqueada para paciente en autoservicio -->
                <?php if (!empty($rolUsuario) && $rolUsuario === 'Paciente'): ?>
                    <input type="hidden" name="id_paciente" id="cita_id_paciente" value="<?= (int)($_SESSION['id_paciente'] ?? 0) ?>">
                    <div class="grupo-campo">
                        <label class="etiqueta-campo">Paciente Solicitante</label>
                        <div style="padding: 0.55rem 0.75rem; background-color: #F8FAFC; border: 1px solid var(--color-borde); border-radius: 6px; font-weight: 600; color: #002855; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="bi bi-person-circle" style="font-size: 1.1rem; color: #A81124;"></i>
                            <span><?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Mi Perfil') ?></span>
                            <?php if (!empty($_SESSION['ci_paciente'])): ?>
                                <span class="texto-muted" style="font-weight: normal; font-size: 0.8125rem;">(CI: <?= htmlspecialchars($_SESSION['ci_paciente']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grupo-campo">
                        <label for="cita_id_paciente" class="etiqueta-campo">
                            Paciente<span class="etiqueta-requerido">*</span>
                        </label>
                        <select name="id_paciente" id="cita_id_paciente" class="control-campo" required>
                            <option value="">-- Seleccione un paciente --</option>
                            <?php if (!empty($listaPacientes)): ?>
                                <?php foreach ($listaPacientes as $pac): ?>
                                    <option value="<?= $pac['id'] ?>">
                                        <?= htmlspecialchars($pac['ci'] . ' - ' . $pac['nombres'] . ' ' . $pac['apellidos']) ?> (<?= htmlspecialchars($pac['codigo']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="cita_id_medico" class="etiqueta-campo">
                            Médico<span class="etiqueta-requerido">*</span>
                        </label>
                        <select name="id_medico" id="cita_id_medico" class="control-campo" required>
                            <option value="">-- Seleccione médico --</option>
                            <?php if (!empty($listaMedicos)): ?>
                                <?php foreach ($listaMedicos as $med): ?>
                                    <option value="<?= $med['id'] ?>" data-especialidades="<?= htmlspecialchars($med['especialidades_ids']) ?>">
                                        Dr(a). <?= htmlspecialchars($med['nombres'] . ' ' . $med['apellidos']) ?> (<?= htmlspecialchars($med['registro_profesional']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="grupo-campo">
                        <label for="cita_id_especialidad" class="etiqueta-campo">
                            Especialidad Requerida<span class="etiqueta-requerido">*</span>
                        </label>
                        <select name="id_especialidad" id="cita_id_especialidad" class="control-campo" required>
                            <option value="">-- Seleccione especialidad --</option>
                            <?php if (!empty($listaEspecialidades)): ?>
                                <?php foreach ($listaEspecialidades as $esp): ?>
                                    <option value="<?= $esp['id'] ?>"><?= htmlspecialchars($esp['nombre']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="cita_fecha" class="etiqueta-campo">
                            Fecha de Consulta<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="date" name="fecha" id="cita_fecha" class="control-campo" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="cita_turno" class="etiqueta-campo">
                            Horario / Turno Libre (30 min)<span class="etiqueta-requerido">*</span>
                        </label>
                        <select name="turno" id="cita_turno" class="control-campo" required>
                            <option value="">-- Seleccione médico y fecha primero --</option>
                        </select>
                    </div>
                </div>

                <div class="grupo-campo">
                    <label for="cita_motivo" class="etiqueta-campo">
                        Motivo de Consulta (Opcional)
                    </label>
                    <input type="text" name="motivo_consulta" id="cita_motivo" class="control-campo" placeholder="ej. Dolor de cabeza frecuente, chequeo preventivo">
                </div>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalAgendarCita')">
                    <i class="bi bi-x"></i>
                    <span>Cancelar</span>
                </button>
                <button type="submit" class="btn btn-primario" id="btnConfirmarAgendar">
                    <i class="bi bi-calendar-check"></i>
                    <span>Confirmar Cita</span>
                </button>
            </div>
        </form>
    </div>
</div>
