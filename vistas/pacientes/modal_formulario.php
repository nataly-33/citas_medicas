<?php
/**
 * Modal Formulario para Registrar y Editar Paciente
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalPaciente">
    <div class="modal-dialogo modal-dialogo-grande" style="max-width: 800px;">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalPacienteTitulo">
                <i class="bi bi-person-plus"></i>
                <span>Nuevo Paciente</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalPaciente')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="formPaciente" method="POST" action="index.php">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" id="paciente_id" value="">

            <div class="modal-cuerpo" style="max-height: 75vh; overflow-y: auto;">
                <div class="alerta-modal-peligro">
                    <span class="mensaje-alerta-modal"></span>
                </div>

                <!-- SECCIÓN 1: DATOS PERSONALES -->
                <div style="font-weight: 700; color: var(--color-primario); font-size: 0.8125rem; text-transform: uppercase; margin-bottom: 0.75rem; border-bottom: 1px solid var(--color-borde); padding-bottom: 0.25rem;">
                    <i class="bi bi-person"></i> Datos Personales y de Identificación
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="paciente_ci" class="etiqueta-campo">
                            Cédula de Identidad (CI)<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="ci" id="paciente_ci" class="control-campo" placeholder="ej. 8934521" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="paciente_fecha_nacimiento" class="etiqueta-campo">
                            Fecha de Nacimiento<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="date" name="fecha_nacimiento" id="paciente_fecha_nacimiento" class="control-campo" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="paciente_sexo" class="etiqueta-campo">
                            Sexo Biológico<span class="etiqueta-requerido">*</span>
                        </label>
                        <select name="sexo" id="paciente_sexo" class="control-campo" required>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="O">Otro</option>
                        </select>
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="paciente_nombres" class="etiqueta-campo">
                            Nombres<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="nombres" id="paciente_nombres" class="control-campo" placeholder="ej. Juan Pablo" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="paciente_apellidos" class="etiqueta-campo">
                            Apellidos<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="apellidos" id="paciente_apellidos" class="control-campo" placeholder="ej. Mercado Rios" required>
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="paciente_telefono" class="etiqueta-campo">
                            Teléfono / Celular<span class="etiqueta-requerido">*</span>
                        </label>
                        <input type="text" name="telefono" id="paciente_telefono" class="control-campo" placeholder="ej. 70912345 (7 a 10 dígitos)" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="paciente_correo" class="etiqueta-campo">
                            Correo Electrónico
                        </label>
                        <input type="email" name="correo" id="paciente_correo" class="control-campo" placeholder="ej. paciente@gmail.com">
                    </div>
                </div>

                <div class="grupo-campo">
                    <label for="paciente_direccion" class="etiqueta-campo">
                        Dirección Domiciliaria
                    </label>
                    <input type="text" name="direccion" id="paciente_direccion" class="control-campo" placeholder="ej. Av. San Martín #420, Santa Cruz">
                </div>

                <!-- SECCIÓN 2: INFORMACIÓN MÉDICA / CLÍNICA -->
                <div style="font-weight: 700; color: var(--color-primario); font-size: 0.8125rem; text-transform: uppercase; margin: 1rem 0 0.75rem; border-bottom: 1px solid var(--color-borde); padding-bottom: 0.25rem;">
                    <i class="bi bi-heart-pulse"></i> Información Médica y Alergias
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="paciente_tipo_sangre" class="etiqueta-campo">
                            Grupo Sanguíneo
                        </label>
                        <select name="tipo_sangre" id="paciente_tipo_sangre" class="control-campo">
                            <option value="ORH+">O Rh+</option>
                            <option value="ORH-">O Rh-</option>
                            <option value="ARH+">A Rh+</option>
                            <option value="ARH-">A Rh-</option>
                            <option value="BRH+">B Rh+</option>
                            <option value="BRH-">B Rh-</option>
                            <option value="ABRH+">AB Rh+</option>
                            <option value="ABRH-">AB Rh-</option>
                        </select>
                    </div>

                    <div class="grupo-campo">
                        <label for="paciente_alergias" class="etiqueta-campo">
                            Alergias Conocidas
                        </label>
                        <input type="text" name="alergias" id="paciente_alergias" class="control-campo" placeholder="ej. Penicilina, polen, mariscos (o 'Ninguna')">
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="paciente_enfermedades_cronicas" class="etiqueta-campo">
                            Enfermedades Crónicas
                        </label>
                        <input type="text" name="enfermedades_cronicas" id="paciente_enfermedades_cronicas" class="control-campo" placeholder="ej. Hipertensión, Asma, Diabetes (o 'Ninguna')">
                    </div>

                    <div class="grupo-campo">
                        <label for="paciente_medicamentos_actuales" class="etiqueta-campo">
                            Medicamentos Actuales
                        </label>
                        <input type="text" name="medicamentos_actuales" id="paciente_medicamentos_actuales" class="control-campo" placeholder="ej. Losartán 50mg, Insulina (o 'Ninguno')">
                    </div>
                </div>

                <div class="grupo-campo">
                    <label for="paciente_antecedentes_relevantes" class="etiqueta-campo">
                        Antecedentes Relevantes / Quirúrgicos
                    </label>
                    <input type="text" name="antecedentes_relevantes" id="paciente_antecedentes_relevantes" class="control-campo" placeholder="ej. Cirugía de apéndice en 2021, fractura...">
                </div>

                <!-- SECCIÓN 3: CONTACTO DE EMERGENCIA -->
                <div style="font-weight: 700; color: var(--color-primario); font-size: 0.8125rem; text-transform: uppercase; margin: 1rem 0 0.75rem; border-bottom: 1px solid var(--color-borde); padding-bottom: 0.25rem;">
                    <i class="bi bi-shield-exclamation"></i> Contacto de Emergencia
                </div>

                <div class="fila-formulario">
                    <div class="grupo-campo">
                        <label for="paciente_contacto_nombre" class="etiqueta-campo">
                            Nombre del Familiar / Contacto
                        </label>
                        <input type="text" name="contacto_emergencia_nombre" id="paciente_contacto_nombre" class="control-campo" placeholder="Nombre completo">
                    </div>

                    <div class="grupo-campo">
                        <label for="paciente_contacto_parentesco" class="etiqueta-campo">
                            Parentesco
                        </label>
                        <input type="text" name="contacto_emergencia_parentesco" id="paciente_contacto_parentesco" class="control-campo" placeholder="ej. Cónyuge, Madre, Padre, Hermano">
                    </div>

                    <div class="grupo-campo">
                        <label for="paciente_contacto_telefono" class="etiqueta-campo">
                            Teléfono de Emergencia
                        </label>
                        <input type="text" name="contacto_emergencia_telefono" id="paciente_contacto_telefono" class="control-campo" placeholder="Teléfono de contacto">
                    </div>
                </div>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalPaciente')">
                    <i class="bi bi-x"></i>
                    <span>Cancelar</span>
                </button>
                <button type="submit" class="btn btn-primario" id="btnGuardarPaciente">
                    <i class="bi bi-check2"></i>
                    <span>Guardar Paciente</span>
                </button>
            </div>
        </form>
    </div>
</div>
