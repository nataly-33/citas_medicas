<?php
namespace App\Servicios;

use InvalidArgumentException;
use App\Modelos\PacienteModelo;
use App\Modelos\MedicoModelo;
use App\Modelos\HorarioModelo;
use App\Modelos\CitaModelo;
use App\Nucleo\Ayudantes;

/**
 * Servicio de Validación Exhaustiva de Reglas de Negocio para Citas Médicas
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class CitaValidacionServicio {
    private PacienteModelo $pacienteModelo;
    private MedicoModelo $medicoModelo;
    private HorarioModelo $horarioModelo;
    private CitaModelo $citaModelo;

    public function __construct(
        ?PacienteModelo $pacienteModelo = null,
        ?MedicoModelo $medicoModelo = null,
        ?HorarioModelo $horarioModelo = null,
        ?CitaModelo $citaModelo = null
    ) {
        $this->pacienteModelo = $pacienteModelo ?: new PacienteModelo();
        $this->medicoModelo = $medicoModelo ?: new MedicoModelo();
        $this->horarioModelo = $horarioModelo ?: new HorarioModelo();
        $this->citaModelo = $citaModelo ?: new CitaModelo();
    }

    /**
     * Valida integralmente las reglas de negocio para el agendamiento de una cita
     *
     * @param array $datos Datos de la cita solicitada
     * @throws InvalidArgumentException
     */
    public function validarReglasAgendamiento(array $datos): void {
        // 1. Validación de campos obligatorios
        if (empty($datos['id_paciente'])) {
            throw new InvalidArgumentException("Debe seleccionar un paciente válido.");
        }
        if (empty($datos['id_medico'])) {
            throw new InvalidArgumentException("Debe seleccionar un médico.");
        }
        if (empty($datos['id_especialidad'])) {
            throw new InvalidArgumentException("Debe seleccionar la especialidad médica de la consulta.");
        }
        if (empty($datos['fecha'])) {
            throw new InvalidArgumentException("Debe especificar la fecha de la cita.");
        }
        if (empty($datos['hora_inicio']) || empty($datos['hora_fin'])) {
            throw new InvalidArgumentException("Debe seleccionar el horario de la consulta.");
        }

        $idPaciente = (int)$datos['id_paciente'];
        $idMedico = (int)$datos['id_medico'];
        $fecha = trim($datos['fecha']);
        $horaInicio = trim($datos['hora_inicio']);
        $horaFin = trim($datos['hora_fin']);

        // Normalizar formato de horas (HH:MM:SS)
        if (strlen($horaInicio) === 5) {
            $horaInicio .= ':00';
        }
        if (strlen($horaFin) === 5) {
            $horaFin .= ':00';
        }

        // 2. Paciente existente y activo
        $paciente = $this->pacienteModelo->buscarPorId($idPaciente);
        if (!$paciente) {
            throw new InvalidArgumentException("El paciente seleccionado no existe en el sistema.");
        }
        if (empty($paciente['activo'])) {
            throw new InvalidArgumentException("El paciente se encuentra inactivo. Debe reactivarlo antes de agendar.");
        }

        // 3. Médico existente y activo
        $medico = $this->medicoModelo->buscarPorId($idMedico);
        if (!$medico) {
            throw new InvalidArgumentException("El médico seleccionado no existe en el sistema.");
        }
        if (empty($medico['activo'])) {
            throw new InvalidArgumentException("El médico seleccionado se encuentra inactivo y no puede recibir citas.");
        }

        // 4. No se pueden registrar citas en fechas pasadas
        $hoy = date('Y-m-d');
        if ($fecha < $hoy) {
            throw new InvalidArgumentException("No se pueden registrar citas en fechas pasadas.");
        }

        $horaActual = date('H:i:s');
        if ($fecha === $hoy && $horaInicio <= $horaActual) {
            throw new InvalidArgumentException("No se puede programar una cita en un horario que ya ha transcurrido hoy.");
        }

        // 5. Duración de la cita y congruencia de horas (30, 45, 60 min)
        if ($horaFin <= $horaInicio) {
            throw new InvalidArgumentException("La hora de fin debe ser posterior a la hora de inicio.");
        }

        $minutos = (strtotime("2000-01-01 $horaFin") - strtotime("2000-01-01 $horaInicio")) / 60;
        if (!in_array((int)$minutos, [30, 45, 60], true)) {
            throw new InvalidArgumentException("La duración de la consulta médica debe ser de 30, 45 o 60 minutos.");
        }

        // 6. La cita debe pertenecer al horario de atención del médico
        $diaSemana = Ayudantes::obtenerDiaSemana($fecha);
        $jornada = $this->horarioModelo->obtenerPorMedicoYDia($idMedico, $diaSemana);
        if (!$jornada || empty($jornada['activo'])) {
            $nombreDia = HorarioModelo::NOMBRES_DIAS[$diaSemana] ?? 'el día indicado';
            throw new InvalidArgumentException("El médico no atiende los días {$nombreDia}.");
        }

        if ($horaInicio < $jornada['hora_inicio'] || $horaFin > $jornada['hora_fin']) {
            throw new InvalidArgumentException(sprintf(
                "El horario seleccionado está fuera de la jornada del médico (%s a %s).",
                substr($jornada['hora_inicio'], 0, 5),
                substr($jornada['hora_fin'], 0, 5)
            ));
        }

        // 7. Un médico no puede tener citas superpuestas
        $hayConflicto = $this->citaModelo->verificarConflictoHorario($idMedico, $fecha, $horaInicio, $horaFin);
        if ($hayConflicto) {
            throw new InvalidArgumentException("El médico ya tiene una cita reservada en el horario seleccionado. Por favor elija otro turno.");
        }
    }

    /**
     * Valida integralmente las reglas de negocio para la reprogramación de una cita
     *
     * @param int $id_cita ID de la cita a reprogramar
     * @param string $nueva_fecha Nueva fecha solicitada
     * @param string $nueva_hora_inicio Nueva hora de inicio
     * @param string $nueva_hora_fin Nueva hora de fin
     * @throws InvalidArgumentException
     */
    public function validarReglasReprogramacion(int $id_cita, string $nueva_fecha, string $nueva_hora_inicio, string $nueva_hora_fin): void {
        $cita = $this->citaModelo->buscarPorId($id_cita);
        if (!$cita) {
            throw new InvalidArgumentException("La cita solicitada no existe.");
        }

        // Restricción sobre estado: No se pueden reprogramar citas confirmadas, atendidas, canceladas o con inasistencia
        if ($cita['estado'] === 'Confirmada') {
            throw new InvalidArgumentException("Una cita médica con estado 'Confirmada' no puede ser reprogramada. El paciente ya se encuentra en sala de espera.");
        }
        if ($cita['estado'] === 'Atendida') {
            throw new InvalidArgumentException("Una cita médica con estado 'Atendida' no puede ser reprogramada.");
        }
        if ($cita['estado'] === 'Cancelada') {
            throw new InvalidArgumentException("Una cita médica cancelada no puede ser reprogramada. Debe agendar una nueva cita.");
        }
        if ($cita['estado'] === 'Inasistencia') {
            throw new InvalidArgumentException("Una cita marcada como 'Inasistencia' no puede ser reprogramada. Debe agendar una nueva cita.");
        }

        $idMedico = (int)$cita['id_medico'];
        $fecha = trim($nueva_fecha);
        $horaInicio = trim($nueva_hora_inicio);
        $horaFin = trim($nueva_hora_fin);

        if (strlen($horaInicio) === 5) {
            $horaInicio .= ':00';
        }
        if (strlen($horaFin) === 5) {
            $horaFin .= ':00';
        }

        // No fechas pasadas
        $hoy = date('Y-m-d');
        if ($fecha < $hoy) {
            throw new InvalidArgumentException("No se puede reprogramar una cita a una fecha pasada.");
        }

        $horaActual = date('H:i:s');
        if ($fecha === $hoy && $horaInicio <= $horaActual) {
            throw new InvalidArgumentException("No se puede reprogramar a un horario que ya ha transcurrido hoy.");
        }

        // Duración de 30, 45 o 60 minutos
        if ($horaFin <= $horaInicio) {
            throw new InvalidArgumentException("La hora de fin debe ser posterior a la hora de inicio.");
        }

        $minutos = (strtotime("2000-01-01 $horaFin") - strtotime("2000-01-01 $horaInicio")) / 60;
        if (!in_array((int)$minutos, [30, 45, 60], true)) {
            throw new InvalidArgumentException("La duración de la consulta debe ser de 30, 45 o 60 minutos.");
        }

        // Horario dentro de jornada del médico
        $diaSemana = Ayudantes::obtenerDiaSemana($fecha);
        $jornada = $this->horarioModelo->obtenerPorMedicoYDia($idMedico, $diaSemana);
        if (!$jornada || empty($jornada['activo'])) {
            $nombreDia = HorarioModelo::NOMBRES_DIAS[$diaSemana] ?? 'el día indicado';
            throw new InvalidArgumentException("El médico no tiene jornada de atención registrada para los días {$nombreDia}.");
        }

        if ($horaInicio < $jornada['hora_inicio'] || $horaFin > $jornada['hora_fin']) {
            throw new InvalidArgumentException(sprintf(
                "El nuevo horario queda fuera de la jornada del médico (%s a %s).",
                substr($jornada['hora_inicio'], 0, 5),
                substr($jornada['hora_fin'], 0, 5)
            ));
        }

        // Verificar que no colisione con otra cita activa del médico (excluyendo la misma cita)
        $hayConflicto = $this->citaModelo->verificarConflictoHorario($idMedico, $fecha, $horaInicio, $horaFin, $id_cita);
        if ($hayConflicto) {
            throw new InvalidArgumentException("El nuevo horario solicitado ya está ocupado por otra cita médica.");
        }
    }
}
