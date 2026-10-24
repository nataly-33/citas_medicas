<?php
namespace App\Modelos;

use PDO;
use PDOException;
use App\Nucleo\Conexion;
use App\Nucleo\Ayudantes;

/**
 * Modelo de Acceso a Datos para Horarios de Atención Semanales
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class HorarioModelo {
    private PDO $pdo;

    public const NOMBRES_DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
    }

    /**
     * Obtiene la jornada configurada de un médico para un día específico de la semana (1 a 7)
     */
    public function obtenerPorMedicoYDia(int $id_medico, int $dia_semana): ?array {
        try {
            $sql = "SELECT id, id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo
                    FROM horarios_atencion
                    WHERE id_medico = :id_medico 
                      AND dia_semana = :dia_semana 
                      AND activo = TRUE
                    LIMIT 1";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([
                ':id_medico'   => $id_medico,
                ':dia_semana'  => $dia_semana,
            ]);
            $resultado = $sentencia->fetch();
            return $resultado ?: null;
        } catch (PDOException $e) {
            Ayudantes::registrarError('HorarioModelo::obtenerPorMedicoYDia', 'Error al obtener horario por día', $e);
            return null;
        }
    }

    /**
     * Obtiene todos los turnos configurados para un médico ordenados cronológicamente por día
     */
    public function obtenerTodosPorMedico(int $id_medico): array {
        try {
            $sql = "SELECT id, id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo
                    FROM horarios_atencion
                    WHERE id_medico = :id_medico
                    ORDER BY dia_semana ASC, hora_inicio ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id_medico' => $id_medico]);
            $registros = $sentencia->fetchAll();

            foreach ($registros as &$reg) {
                $reg['nombre_dia'] = self::NOMBRES_DIAS[$reg['dia_semana']] ?? 'Desconocido';
            }

            return $registros;
        } catch (PDOException $e) {
            Ayudantes::registrarError('HorarioModelo::obtenerTodosPorMedico', 'Error al obtener horarios del médico', $e);
            return [];
        }
    }

    /**
     * Guarda o actualiza un turno de atención específico
     */
    public function guardarTurno(int $id_medico, int $dia_semana, string $hora_inicio, string $hora_fin, int $duracion = 30): bool {
        try {
            $sql = "INSERT INTO horarios_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo)
                    VALUES (:id_medico, :dia_semana, :hora_inicio, :hora_fin, :duracion, TRUE)
                    ON CONFLICT (id_medico, dia_semana, hora_inicio) 
                    DO UPDATE SET hora_fin = EXCLUDED.hora_fin, duracion_minutos = EXCLUDED.duracion_minutos, activo = TRUE";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([
                ':id_medico'   => $id_medico,
                ':dia_semana'  => $dia_semana,
                ':hora_inicio' => $hora_inicio,
                ':hora_fin'    => $hora_fin,
                ':duracion'    => $duracion,
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('HorarioModelo::guardarTurno', 'Error al guardar turno', $e);
            return false;
        }
    }

    /**
     * Elimina todos los turnos de atención asignados a un médico
     */
    public function eliminarTurnosPorMedico(int $id_medico): bool {
        try {
            $sql = "DELETE FROM horarios_atencion WHERE id_medico = :id_medico";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([':id_medico' => $id_medico]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('HorarioModelo::eliminarTurnosPorMedico', 'Error al eliminar turnos', $e);
            return false;
        }
    }

    /**
     * Sincroniza en una única transacción la grilla completa de horarios semanales de un médico
     */
    public function sincronizarHorarios(int $id_medico, array $turnos): bool {
        try {
            $this->pdo->beginTransaction();

            $this->eliminarTurnosPorMedico($id_medico);

            $sql = "INSERT INTO horarios_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo)
                    VALUES (:id_medico, :dia_semana, :hora_inicio, :hora_fin, :duracion, TRUE)";
            $sentencia = $this->pdo->prepare($sql);

            foreach ($turnos as $indice => $turno) {
                // El día de semana puede venir como clave asociativa o como campo 'dia_semana'
                $diaSemana = !empty($turno['dia_semana']) ? (int)$turno['dia_semana'] : (int)$indice;

                if ($diaSemana >= 1 && $diaSemana <= 7 && !empty($turno['activo']) && !empty($turno['hora_inicio']) && !empty($turno['hora_fin'])) {
                    $duracion = !empty($turno['duracion_minutos']) ? (int)$turno['duracion_minutos'] : (!empty($turno['duracion']) ? (int)$turno['duracion'] : 30);
                    $hIni = trim($turno['hora_inicio']);
                    $hFin = trim($turno['hora_fin']);
                    if (strlen($hIni) === 5) $hIni .= ':00';
                    if (strlen($hFin) === 5) $hFin .= ':00';

                    $sentencia->execute([
                        ':id_medico'   => $id_medico,
                        ':dia_semana'  => $diaSemana,
                        ':hora_inicio' => $hIni,
                        ':hora_fin'    => $hFin,
                        ':duracion'    => $duracion,
                    ]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Ayudantes::registrarError('HorarioModelo::sincronizarHorarios', 'Error al sincronizar horarios semanales', $e);
            return false;
        }
    }
}
