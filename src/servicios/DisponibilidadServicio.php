<?php
namespace App\Servicios;

use App\Modelos\HorarioModelo;
use App\Modelos\CitaModelo;
use App\Nucleo\Ayudantes;

/**
 * Servicio Especializado de Cálculo de Disponibilidad y Turnos Libres
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 * Soporta duraciones de 30, 45 y 60 minutos y validación estricta de horarios de hoy
 */
class DisponibilidadServicio {
    private HorarioModelo $horarioModelo;
    private CitaModelo $citaModelo;

    public function __construct(?HorarioModelo $horarioModelo = null, ?CitaModelo $citaModelo = null) {
        $this->horarioModelo = $horarioModelo ?: new HorarioModelo();
        $this->citaModelo = $citaModelo ?: new CitaModelo();
    }

    /**
     * Calcula los turnos de atención libres para un médico en una fecha específica
     * Cruza el horario configurado del día con las citas existentes y descarta citas canceladas
     *
     * @param int $id_medico ID del médico a consultar
     * @param string $fecha Fecha de consulta en formato 'YYYY-MM-DD'
     * @param int|null $excluir_id_cita ID de cita a excluir (para reprogramaciones)
     * @param int|null $duracion_especifica Duración en minutos (30, 45, 60) si se desea sobreescribir
     * @return array Lista de intervalos libres
     */
    public function calcularTurnosDisponibles(int $id_medico, string $fecha, ?int $excluir_id_cita = null, ?int $duracion_especifica = null): array {
        // Validar formato de fecha
        if (empty($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return [];
        }

        $hoy = date('Y-m-d');
        // No se permiten fechas pasadas
        if ($fecha < $hoy) {
            return [];
        }

        // Determinar día de la semana (1 = Lunes, ..., 7 = Domingo)
        $diaSemana = Ayudantes::obtenerDiaSemana($fecha);

        // Obtener la jornada de atención configurada para ese día
        $jornada = $this->horarioModelo->obtenerPorMedicoYDia($id_medico, $diaSemana);
        if (!$jornada || empty($jornada['activo'])) {
            return [];
        }

        $horaInicio = $jornada['hora_inicio'];
        $horaFin = $jornada['hora_fin'];
        
        $duracion = 30;
        if (!empty($duracion_especifica) && in_array((int)$duracion_especifica, [30, 45, 60], true)) {
            $duracion = (int)$duracion_especifica;
        } elseif (!empty($jornada['duracion_minutos'])) {
            $duracion = (int)$jornada['duracion_minutos'];
        }

        // Generar todos los bloques potenciales según la jornada y duración de consulta
        $bloquesTeoricos = $this->generarBloquesDeTiempo($horaInicio, $horaFin, $duracion);

        // Consultar citas ya reservadas, excluyendo las canceladas
        $citasOcupadas = $this->citaModelo->obtenerCitasOcupadas($id_medico, $fecha, $excluir_id_cita);

        $horaActual = date('H:i:s');
        $esHoy = ($fecha === $hoy);

        $turnosDisponibles = [];

        foreach ($bloquesTeoricos as $bloque) {
            $inicioBloque = $bloque['hora_inicio'];
            $finBloque = $bloque['hora_fin'];

            // Validación de horas de hoy: Si $fecha == date('Y-m-d'), descartar turnos cuya $hora_inicio <= date('H:i:s')
            if ($esHoy && $inicioBloque <= $horaActual) {
                continue;
            }

            // Verificar si el bloque se solapa con alguna cita ocupada
            $estaOcupado = false;
            foreach ($citasOcupadas as $ocupada) {
                $inicioOcupada = $ocupada['hora_inicio'];
                $finOcupada = $ocupada['hora_fin'];

                // Condición de solapamiento de intervalos temporales
                if ($inicioBloque < $finOcupada && $finBloque > $inicioOcupada) {
                    $estaOcupado = true;
                    break;
                }
            }

            if (!$estaOcupado) {
                $turnosDisponibles[] = [
                    'hora_inicio' => substr($inicioBloque, 0, 5),
                    'hora_fin'    => substr($finBloque, 0, 5),
                    'etiqueta'    => substr($inicioBloque, 0, 5) . ' - ' . substr($finBloque, 0, 5),
                    'duracion'    => $duracion,
                ];
            }
        }

        return $turnosDisponibles;
    }

    /**
     * Genera los bloques discretos de tiempo entre dos límites con una duración fija en minutos (30, 45, 60)
     */
    public function generarBloquesDeTiempo(string $hora_inicio, string $hora_fin, int $duracion_minutos = 30): array {
        $bloques = [];

        $tiempoInicio = strtotime("2000-01-01 " . $hora_inicio);
        $tiempoFin = strtotime("2000-01-01 " . $hora_fin);
        $pasoSegundos = $duracion_minutos * 60;

        if ($pasoSegundos <= 0) {
            $pasoSegundos = 1800; // 30 min por defecto
        }

        $actual = $tiempoInicio;
        while ($actual + $pasoSegundos <= $tiempoFin) {
            $siguiente = $actual + $pasoSegundos;
            $bloques[] = [
                'hora_inicio' => date('H:i:s', $actual),
                'hora_fin'    => date('H:i:s', $siguiente),
            ];
            $actual = $siguiente;
        }

        return $bloques;
    }
}
