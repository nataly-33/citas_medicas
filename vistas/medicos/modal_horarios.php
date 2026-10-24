<?php
/**
 * Modal Configuración de Horarios de Atención Semanal (CU06)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */
?>
<div class="modal-fondo" id="modalHorarios">
    <div class="modal-dialogo modal-dialogo-grande" style="max-width: 680px;">
        <div class="modal-cabecera">
            <div class="modal-titulo" id="modalHorariosTitulo">
                <i class="bi bi-clock"></i>
                <span>Configurar Horarios de Atención</span>
            </div>
            <button type="button" class="modal-cerrar" onclick="cerrarModal('modalHorarios')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="formHorarios" method="POST" action="index.php">
            <input type="hidden" name="accion" value="guardar_horarios">
            <input type="hidden" name="id_medico" id="horarios_id_medico" value="">

            <div class="modal-cuerpo">
                <div class="alerta-modal-peligro">
                    <span class="mensaje-alerta-modal"></span>
                </div>

                <p class="texto-muted" style="margin-bottom: 1rem; font-size: 0.8125rem;">
                    Active los días de consulta e ingrese el rango horario de atención. Cada consulta tiene una duración estándar de 30 minutos.
                </p>

                <?php 
                $diasNombres = [
                    1 => 'Lunes',
                    2 => 'Martes',
                    3 => 'Miércoles',
                    4 => 'Jueves',
                    5 => 'Viernes',
                    6 => 'Sábado',
                    7 => 'Domingo'
                ];
                ?>

                <div class="tabla-responsiva">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th style="width: 40px;">Activo</th>
                                <th>Día de Semana</th>
                                <th>Hora Inicio</th>
                                <th>Hora Fin</th>
                                <th>Duración</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diasNombres as $diaNum => $diaNombre): ?>
                                <tr>
                                    <td class="texto-centro">
                                        <input type="checkbox" name="dias[<?= $diaNum ?>][activo]" value="1" 
                                               id="dia_activo_<?= $diaNum ?>" 
                                               onchange="toggleFilaHorario(<?= $diaNum ?>)">
                                    </td>
                                    <td>
                                        <label for="dia_activo_<?= $diaNum ?>" style="font-weight: 600; cursor: pointer;">
                                            <?= $diaNombre ?>
                                        </label>
                                    </td>
                                    <td>
                                        <input type="time" name="dias[<?= $diaNum ?>][hora_inicio]" 
                                               id="hora_inicio_<?= $diaNum ?>" 
                                               value="08:00" class="control-campo" style="padding: 0.3rem 0.5rem;" disabled>
                                    </td>
                                    <td>
                                        <input type="time" name="dias[<?= $diaNum ?>][hora_fin]" 
                                               id="hora_fin_<?= $diaNum ?>" 
                                               value="12:00" class="control-campo" style="padding: 0.3rem 0.5rem;" disabled>
                                    </td>
                                    <td>
                                        <span class="texto-muted" style="font-size: 0.8125rem;">30 min</span>
                                        <input type="hidden" name="dias[<?= $diaNum ?>][duracion]" value="30">
                                        <input type="hidden" name="dias[<?= $diaNum ?>][dia_semana]" value="<?= $diaNum ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalHorarios')">
                    <i class="bi bi-x"></i>
                    <span>Cancelar</span>
                </button>
                <button type="submit" class="btn btn-primario" id="btnGuardarHorarios">
                    <i class="bi bi-check2"></i>
                    <span>Guardar Jornada Semanal</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleFilaHorario(diaNum) {
    const check = document.getElementById('dia_activo_' + diaNum);
    const inicio = document.getElementById('hora_inicio_' + diaNum);
    const fin = document.getElementById('hora_fin_' + diaNum);
    if (check && inicio && fin) {
        inicio.disabled = !check.checked;
        fin.disabled = !check.checked;
    }
}
</script>
