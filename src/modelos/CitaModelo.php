<?php
namespace App\Modelos;

use PDO;
use PDOException;
use App\Nucleo\Conexion;
use App\Nucleo\Ayudantes;

/**
 * Modelo de Acceso a Datos para Citas Médicas
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class CitaModelo {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
    }

    /**
     * Obtiene citas paginadas a 15 filas con filtros por rango de fecha, médico, paciente y estado
     * Ordenamiento por defecto: fecha DESC, hora_inicio ASC, y jerarquía de estados
     */
    public function obtenerPaginadas(
        int $limite = 15, 
        int $desplazamiento = 0, 
        string $fecha_desde = '', 
        string $fecha_hasta = '', 
        int $id_medico = 0, 
        string $estado = '',
        int $id_paciente = 0,
        string $buscar = ''
    ): array {
        try {
            $sql = "SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, 
                           c.motivo_consulta, c.motivo_cancelacion, c.observaciones_atencion,
                           c.id_paciente, c.id_medico, c.id_especialidad,
                           p.codigo AS codigo_paciente, p.ci AS ci_paciente, 
                           (p.nombres || ' ' || p.apellidos) AS nombre_paciente, p.telefono AS telefono_paciente,
                           p.tipo_sangre, p.alergias,
                           m.codigo AS codigo_medico, (m.nombres || ' ' || m.apellidos) AS nombre_medico,
                           m.registro_profesional AS matricula_medico,
                           e.nombre AS nombre_especialidad, e.duracion_minutos,
                           u.correo AS registrado_por
                    FROM citas_medicas c
                    INNER JOIN pacientes p ON p.id = c.id_paciente
                    INNER JOIN medicos m ON m.id = c.id_medico
                    INNER JOIN especialidades e ON e.id = c.id_especialidad
                    INNER JOIN usuarios u ON u.id = c.id_usuario_registro";

            $condiciones = [];
            $params = [];

            // Filtro por rango de fechas
            if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                $condiciones[] = "c.fecha BETWEEN :fecha_desde AND :fecha_hasta";
                $params[':fecha_desde'] = $fecha_desde;
                $params[':fecha_hasta'] = $fecha_hasta;
            } elseif (!empty($fecha_desde)) {
                $condiciones[] = "c.fecha >= :fecha_desde";
                $params[':fecha_desde'] = $fecha_desde;
            } elseif (!empty($fecha_hasta)) {
                $condiciones[] = "c.fecha <= :fecha_hasta";
                $params[':fecha_hasta'] = $fecha_hasta;
            }

            if ($id_medico > 0) {
                $condiciones[] = "c.id_medico = :id_medico";
                $params[':id_medico'] = $id_medico;
            }

            if ($id_paciente > 0) {
                $condiciones[] = "c.id_paciente = :id_paciente";
                $params[':id_paciente'] = $id_paciente;
            }

            if (!empty($estado)) {
                $condiciones[] = "c.estado = :estado";
                $params[':estado'] = $estado;
            }

            if (!empty($buscar)) {
                $condiciones[] = "(c.codigo_cita ILIKE :buscar 
                               OR p.ci ILIKE :buscar 
                               OR p.nombres ILIKE :buscar 
                               OR p.apellidos ILIKE :buscar 
                               OR (p.nombres || ' ' || p.apellidos) ILIKE :buscar
                               OR m.nombres ILIKE :buscar 
                               OR m.apellidos ILIKE :buscar 
                               OR e.nombre ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            if (!empty($condiciones)) {
                $sql .= " WHERE " . implode(" AND ", $condiciones);
            }

            // Ordenamiento por defecto solicitado
            $sql .= " ORDER BY c.fecha DESC, c.hora_inicio ASC, 
                      CASE c.estado 
                          WHEN 'Programada' THEN 1 
                          WHEN 'Confirmada' THEN 2 
                          WHEN 'Atendida' THEN 3 
                          WHEN 'Cancelada' THEN 4 
                          WHEN 'Inasistencia' THEN 5 
                      END, c.id DESC 
                      LIMIT :limite OFFSET :desplazamiento";

            $sentencia = $this->pdo->prepare($sql);

            foreach ($params as $clave => $valor) {
                $sentencia->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
            $sentencia->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);

            $sentencia->execute();
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::obtenerPaginadas', 'Error al obtener citas paginadas', $e);
            return [];
        }
    }

    /**
     * Cuenta el total de citas según los filtros aplicados
     */
    public function contarTotal(
        string $fecha_desde = '', 
        string $fecha_hasta = '', 
        int $id_medico = 0, 
        string $estado = '',
        int $id_paciente = 0,
        string $buscar = ''
    ): int {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM citas_medicas c
                    INNER JOIN pacientes p ON p.id = c.id_paciente
                    INNER JOIN medicos m ON m.id = c.id_medico
                    INNER JOIN especialidades e ON e.id = c.id_especialidad";

            $condiciones = [];
            $params = [];

            if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                $condiciones[] = "c.fecha BETWEEN :fecha_desde AND :fecha_hasta";
                $params[':fecha_desde'] = $fecha_desde;
                $params[':fecha_hasta'] = $fecha_hasta;
            } elseif (!empty($fecha_desde)) {
                $condiciones[] = "c.fecha >= :fecha_desde";
                $params[':fecha_desde'] = $fecha_desde;
            } elseif (!empty($fecha_hasta)) {
                $condiciones[] = "c.fecha <= :fecha_hasta";
                $params[':fecha_hasta'] = $fecha_hasta;
            }

            if ($id_medico > 0) {
                $condiciones[] = "c.id_medico = :id_medico";
                $params[':id_medico'] = $id_medico;
            }

            if ($id_paciente > 0) {
                $condiciones[] = "c.id_paciente = :id_paciente";
                $params[':id_paciente'] = $id_paciente;
            }

            if (!empty($estado)) {
                $condiciones[] = "c.estado = :estado";
                $params[':estado'] = $estado;
            }

            if (!empty($buscar)) {
                $condiciones[] = "(c.codigo_cita ILIKE :buscar 
                               OR p.ci ILIKE :buscar 
                               OR p.nombres ILIKE :buscar 
                               OR p.apellidos ILIKE :buscar 
                               OR (p.nombres || ' ' || p.apellidos) ILIKE :buscar
                               OR m.nombres ILIKE :buscar 
                               OR m.apellidos ILIKE :buscar 
                               OR e.nombre ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            if (!empty($condiciones)) {
                $sql .= " WHERE " . implode(" AND ", $condiciones);
            }

            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return (int)$sentencia->fetchColumn();
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::contarTotal', 'Error al contar total de citas', $e);
            return 0;
        }
    }

    /**
     * Busca una cita por su ID incluyendo datos clínicos y de contacto completos
     */
    public function buscarPorId(int $id): ?array {
        return $this->obtenerDetalleCompleto($id);
    }

    /**
     * Obtiene el detalle exhaustivo de una cita médica para el modal de visualización
     */
    public function obtenerDetalleCompleto(int $id): ?array {
        try {
            $sql = "SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, 
                           c.motivo_consulta, c.motivo_cancelacion, c.observaciones_atencion,
                           c.id_paciente, c.id_medico, c.id_especialidad, c.id_usuario_registro,
                           c.fecha_creacion, c.fecha_actualizacion,
                           p.codigo AS codigo_paciente, p.ci AS ci_paciente, 
                           (p.nombres || ' ' || p.apellidos) AS nombre_paciente, 
                           p.nombres AS nombres_paciente, p.apellidos AS apellidos_paciente,
                           p.fecha_nacimiento AS fecha_nacimiento_paciente, p.sexo AS sexo_paciente,
                           p.telefono AS telefono_paciente, p.correo AS correo_paciente, p.direccion AS direccion_paciente,
                           p.tipo_sangre, p.alergias, p.enfermedades_cronicas, p.medicamentos_actuales, p.antecedentes_relevantes,
                           p.contacto_emergencia_nombre, p.contacto_emergencia_parentesco, p.contacto_emergencia_telefono,
                           m.codigo AS codigo_medico, (m.nombres || ' ' || m.apellidos) AS nombre_medico,
                           m.registro_profesional AS matricula_medico, m.telefono AS telefono_medico,
                           e.nombre AS nombre_especialidad, e.duracion_minutos,
                           u.correo AS registrado_por,
                           TRIM(COALESCE(u.nombres, '') || ' ' || COALESCE(u.apellidos, '')) AS registrado_por_nombre
                    FROM citas_medicas c
                    INNER JOIN pacientes p ON p.id = c.id_paciente
                    INNER JOIN medicos m ON m.id = c.id_medico
                    INNER JOIN especialidades e ON e.id = c.id_especialidad
                    INNER JOIN usuarios u ON u.id = c.id_usuario_registro
                    WHERE c.id = :id
                    LIMIT 1";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
            $resultado = $sentencia->fetch();
            return $resultado ?: null;
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::obtenerDetalleCompleto', 'Error al obtener detalle de cita', $e);
            return null;
        }
    }

    /**
     * Obtiene todas las citas de una fecha específica para la vista diaria o tablero Kanban
     */
    public function obtenerPorFecha(string $fecha, int $id_medico = 0): array {
        try {
            $sql = "SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, 
                           c.motivo_consulta, c.motivo_cancelacion, c.observaciones_atencion,
                           p.id AS id_paciente, p.codigo AS codigo_paciente, p.ci AS ci_paciente,
                           (p.nombres || ' ' || p.apellidos) AS nombre_paciente, p.telefono AS telefono_paciente,
                           p.tipo_sangre, p.alergias,
                           m.id AS id_medico, m.codigo AS codigo_medico, 
                           (m.nombres || ' ' || m.apellidos) AS nombre_medico,
                           e.nombre AS nombre_especialidad
                    FROM citas_medicas c
                    INNER JOIN pacientes p ON p.id = c.id_paciente
                    INNER JOIN medicos m ON m.id = c.id_medico
                    INNER JOIN especialidades e ON e.id = c.id_especialidad
                    WHERE c.fecha = :fecha";
            $params = [':fecha' => $fecha];

            if ($id_medico > 0) {
                $sql .= " AND c.id_medico = :id_medico";
                $params[':id_medico'] = $id_medico;
            }

            $sql .= " ORDER BY c.hora_inicio ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::obtenerPorFecha', 'Error al obtener citas por fecha', $e);
            return [];
        }
    }

    /**
     * Verifica si existe conflicto o solapamiento de horarios para un médico en una fecha dada
     */
    public function verificarConflictoHorario(int $id_medico, string $fecha, string $hora_inicio, string $hora_fin, ?int $excluir_id_cita = null): bool {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM citas_medicas 
                    WHERE id_medico = :id_medico 
                      AND fecha = :fecha 
                      AND estado <> 'Cancelada'
                      AND (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)";
            $params = [
                ':id_medico'   => $id_medico,
                ':fecha'       => $fecha,
                ':hora_inicio' => $hora_inicio,
                ':hora_fin'    => $hora_fin,
            ];

            if ($excluir_id_cita !== null) {
                $sql .= " AND id <> :excluir_id";
                $params[':excluir_id'] = $excluir_id_cita;
            }

            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return (int)$sentencia->fetchColumn() > 0;
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::verificarConflictoHorario', 'Error al verificar conflicto horario', $e);
            return true;
        }
    }

    /**
     * Genera el siguiente código correlativo de cita médica con formato CM-YYYY-XXXX
     */
    public function generarSiguienteCodigo(): string {
        try {
            $anioActual = date('Y');
            $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo_cita FROM 9) AS INTEGER)), 0) + 1 AS siguiente
                    FROM citas_medicas
                    WHERE codigo_cita ~ ('^CM-' || :anio || '-[0-9]+$')";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':anio' => $anioActual]);
            $siguiente = (int)$sentencia->fetchColumn();
            return sprintf("CM-%s-%04d", $anioActual, $siguiente ?: 1);
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::generarSiguienteCodigo', 'Error al generar código de cita', $e);
            return sprintf("CM-%s-%04d", date('Y'), rand(1000, 9999));
        }
    }

    /**
     * Inserta una cita médica de forma transaccional garantizando atomicidad
     */
    public function insertarTransaccional(array $datos): array {
        try {
            $this->pdo->beginTransaction();

            $codigoCita = !empty($datos['codigo_cita']) ? trim($datos['codigo_cita']) : $this->generarSiguienteCodigo();

            $sql = "INSERT INTO citas_medicas (
                        codigo_cita, id_paciente, id_medico, id_especialidad, 
                        fecha, hora_inicio, hora_fin, motivo_consulta, 
                        estado, id_usuario_registro
                    ) VALUES (
                        :codigo_cita, :id_paciente, :id_medico, :id_especialidad, 
                        :fecha, :hora_inicio, :hora_fin, :motivo_consulta, 
                        :estado, :id_usuario_registro
                    ) RETURNING id";
            $sentencia = $this->pdo->prepare($sql);

            $sentencia->execute([
                ':codigo_cita'         => $codigoCita,
                ':id_paciente'         => (int)$datos['id_paciente'],
                ':id_medico'           => (int)$datos['id_medico'],
                ':id_especialidad'     => (int)$datos['id_especialidad'],
                ':fecha'               => $datos['fecha'],
                ':hora_inicio'         => $datos['hora_inicio'],
                ':hora_fin'            => $datos['hora_fin'],
                ':motivo_consulta'     => !empty($datos['motivo_consulta']) ? trim($datos['motivo_consulta']) : null,
                ':estado'              => !empty($datos['estado']) ? $datos['estado'] : 'Programada',
                ':id_usuario_registro' => (int)$datos['id_usuario_registro'],
            ]);

            $idGenerado = (int)$sentencia->fetchColumn();

            $this->pdo->commit();

            return [
                'id'          => $idGenerado,
                'codigo_cita' => $codigoCita,
                'mensaje'     => 'Cita médica agendada correctamente',
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Ayudantes::registrarError('CitaModelo::insertarTransaccional', 'Error transaccional al agendar cita', $e);
            throw $e;
        }
    }

    /**
     * Actualiza la fecha y horario de una cita médica (Reprogramación)
     */
    public function actualizarHorario(int $id, string $nueva_fecha, string $nueva_hora_inicio, string $nueva_hora_fin): bool {
        try {
            $this->pdo->beginTransaction();

            $sql = "UPDATE citas_medicas SET 
                        fecha = :nueva_fecha,
                        hora_inicio = :nueva_hora_inicio,
                        hora_fin = :nueva_hora_fin,
                        estado = 'Programada',
                        fecha_actualizacion = CURRENT_TIMESTAMP
                    WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            $resultado = $sentencia->execute([
                ':id'                => $id,
                ':nueva_fecha'       => $nueva_fecha,
                ':nueva_hora_inicio' => $nueva_hora_inicio,
                ':nueva_hora_fin'    => $nueva_hora_fin,
            ]);

            $this->pdo->commit();
            return $resultado;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Ayudantes::registrarError('CitaModelo::actualizarHorario', 'Error transaccional al reprogramar cita', $e);
            throw $e;
        }
    }

    /**
     * Actualiza el estado de una cita médica (Atendida, Cancelada, Confirmada, Inasistencia)
     */
    public function actualizarEstado(int $id, string $estado, ?string $motivo_cancelacion = null, ?string $observaciones = null): bool {
        try {
            $sql = "UPDATE citas_medicas SET 
                        estado = :estado,
                        motivo_cancelacion = COALESCE(:motivo_cancelacion, motivo_cancelacion),
                        observaciones_atencion = COALESCE(:observaciones, observaciones_atencion),
                        fecha_actualizacion = CURRENT_TIMESTAMP
                    WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([
                ':id'                 => $id,
                ':estado'             => $estado,
                ':motivo_cancelacion' => $motivo_cancelacion !== null ? trim($motivo_cancelacion) : null,
                ':observaciones'      => $observaciones !== null ? trim($observaciones) : null,
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::actualizarEstado', 'Error al actualizar estado de cita', $e);
            return false;
        }
    }

    /**
     * Obtiene el historial clínico de citas de un paciente en orden cronológico
     */
    public function obtenerCitasPorPaciente(int $id_paciente): array {
        try {
            $sql = "SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, 
                           c.motivo_consulta, c.motivo_cancelacion, c.observaciones_atencion,
                           m.codigo AS codigo_medico, (m.nombres || ' ' || m.apellidos) AS nombre_medico,
                           m.registro_profesional AS matricula_medico,
                           e.nombre AS nombre_especialidad
                    FROM citas_medicas c
                    INNER JOIN medicos m ON m.id = c.id_medico
                    INNER JOIN especialidades e ON e.id = c.id_especialidad
                    WHERE c.id_paciente = :id_paciente
                    ORDER BY c.fecha DESC, c.hora_inicio DESC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id_paciente' => $id_paciente]);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::obtenerCitasPorPaciente', 'Error al obtener historial de paciente', $e);
            return [];
        }
    }

    /**
     * Obtiene la agenda del día para un médico específico
     */
    public function obtenerCitasPorMedicoYFecha(int $id_medico, string $fecha): array {
        try {
            $sql = "SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, 
                           c.motivo_consulta, c.motivo_cancelacion, c.observaciones_atencion,
                           p.id AS id_paciente, p.codigo AS codigo_paciente, p.ci AS ci_paciente,
                           (p.nombres || ' ' || p.apellidos) AS nombre_paciente, p.telefono AS telefono_paciente,
                           p.tipo_sangre, p.alergias,
                           e.nombre AS nombre_especialidad
                    FROM citas_medicas c
                    INNER JOIN pacientes p ON p.id = c.id_paciente
                    INNER JOIN especialidades e ON e.id = c.id_especialidad
                    WHERE c.id_medico = :id_medico 
                      AND c.fecha = :fecha
                    ORDER BY c.hora_inicio ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([
                ':id_medico' => $id_medico,
                ':fecha'     => $fecha,
            ]);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::obtenerCitasPorMedicoYFecha', 'Error al obtener agenda del día', $e);
            return [];
        }
    }

    /**
     * Obtiene los intervalos ocupados para un médico y fecha (excluyendo canceladas)
     */
    public function obtenerCitasOcupadas(int $id_medico, string $fecha, ?int $excluir_id = null): array {
        try {
            $sql = "SELECT hora_inicio, hora_fin 
                    FROM citas_medicas 
                    WHERE id_medico = :id_medico 
                      AND fecha = :fecha 
                      AND estado <> 'Cancelada'";
            $params = [
                ':id_medico' => $id_medico,
                ':fecha'     => $fecha,
            ];

            if ($excluir_id !== null) {
                $sql .= " AND id <> :excluir_id";
                $params[':excluir_id'] = $excluir_id;
            }

            $sql .= " ORDER BY hora_inicio ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('CitaModelo::obtenerCitasOcupadas', 'Error al obtener citas ocupadas', $e);
            return [];
        }
    }
}
