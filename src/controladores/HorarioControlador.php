<?php
namespace App\Controladores;

use App\Servicios\DisponibilidadServicio;
use App\Modelos\HorarioModelo;
use App\Modelos\MedicoModelo;
use App\Nucleo\Ayudantes;

/**
 * Controlador de Horarios de Atención y Consulta de Disponibilidad de Agenda
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class HorarioControlador {
    private DisponibilidadServicio $disponibilidadServicio;
    private HorarioModelo $horarioModelo;
    private MedicoModelo $medicoModelo;

    public function __construct(
        ?DisponibilidadServicio $disponibilidadServicio = null,
        ?HorarioModelo $horarioModelo = null,
        ?MedicoModelo $medicoModelo = null
    ) {
        $this->disponibilidadServicio = $disponibilidadServicio ?: new DisponibilidadServicio();
        $this->horarioModelo = $horarioModelo ?: new HorarioModelo();
        $this->medicoModelo = $medicoModelo ?: new MedicoModelo();
    }

    /**
     * Consulta los turnos libres de 30 minutos para un médico y fecha específica (CU07)
     * Responde llamadas asíncronas desde modales de agendamiento y reprogramación
     */
    public function consultarDisponibilidad(int $id_medico, string $fecha, ?int $excluir_id_cita = null): array {
        if ($id_medico <= 0) {
            return [
                'exito'   => false,
                'mensaje' => 'Debe seleccionar un médico válido.',
                'turnos'  => [],
            ];
        }

        if (empty($fecha)) {
            return [
                'exito'   => false,
                'mensaje' => 'Debe seleccionar una fecha.',
                'turnos'  => [],
            ];
        }

        $hoy = date('Y-m-d');
        if ($fecha < $hoy) {
            return [
                'exito'   => false,
                'mensaje' => 'No se puede consultar disponibilidad en fechas pasadas.',
                'turnos'  => [],
            ];
        }

        $medico = $this->medicoModelo->buscarPorId($id_medico);
        if (!$medico || empty($medico['activo'])) {
            return [
                'exito'   => false,
                'mensaje' => 'El médico seleccionado no está activo.',
                'turnos'  => [],
            ];
        }

        $turnos = $this->disponibilidadServicio->calcularTurnosDisponibles($id_medico, $fecha, $excluir_id_cita);

        $diaSemana = Ayudantes::obtenerDiaSemana($fecha);
        $nombreDia = HorarioModelo::NOMBRES_DIAS[$diaSemana] ?? '';

        return [
            'exito'       => true,
            'id_medico'   => $id_medico,
            'fecha'       => $fecha,
            'nombre_dia'  => $nombreDia,
            'turnos'      => $turnos,
            'total'       => count($turnos),
            'mensaje'     => count($turnos) > 0 
                ? 'Turnos disponibles encontrados.' 
                : "El médico no dispone de turnos libres para el día {$nombreDia} en la fecha indicada.",
        ];
    }

    /**
     * Guarda la configuración de horarios semanales de un médico (CU06)
     */
    public function configurarHorarios(int $id_medico, array $turnos): bool {
        if ($id_medico <= 0) {
            return false;
        }
        return $this->horarioModelo->sincronizarHorarios($id_medico, $turnos);
    }

    /**
     * Obtiene los horarios configurados para un médico
     */
    public function obtenerHorariosPorMedico(int $id_medico): array {
        return $this->horarioModelo->obtenerTodosPorMedico($id_medico);
    }
}
