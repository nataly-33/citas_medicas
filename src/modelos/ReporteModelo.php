<?php
namespace App\Modelos;

use PDO;
use PDOException;
use App\Nucleo\Conexion;
use App\Nucleo\Ayudantes;

/**
 * Modelo de Acceso a Datos para Reportes Administrativos y Estadísticos
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class ReporteModelo {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
    }

    /**
     * Obtiene el resumen de totales agrupados por estado en un rango de fechas
     */
    public function contarTotalesPorEstado(string $fecha_inicio, string $fecha_fin): array {
        try {
            $sql = "SELECT 
                        COUNT(*) AS total_general,
                        COUNT(CASE WHEN estado = 'Programada' THEN 1 END) AS programadas,
                        COUNT(CASE WHEN estado = 'Confirmada' THEN 1 END) AS confirmadas,
                        COUNT(CASE WHEN estado = 'Atendida' THEN 1 END) AS atendidas,
                        COUNT(CASE WHEN estado = 'Cancelada' THEN 1 END) AS canceladas,
                        COUNT(CASE WHEN estado = 'Inasistencia' THEN 1 END) AS inasistencias
                    FROM citas_medicas
                    WHERE fecha BETWEEN :inicio AND :fin";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([
                ':inicio' => $fecha_inicio,
                ':fin'    => $fecha_fin,
            ]);
            $resultado = $sentencia->fetch();

            return [
                'total_general' => (int)($resultado['total_general'] ?? 0),
                'programadas'   => (int)($resultado['programadas'] ?? 0),
                'confirmadas'   => (int)($resultado['confirmadas'] ?? 0),
                'atendidas'     => (int)($resultado['atendidas'] ?? 0),
                'canceladas'    => (int)($resultado['canceladas'] ?? 0),
                'inasistencias' => (int)($resultado['inasistencias'] ?? 0),
            ];
        } catch (PDOException $e) {
            Ayudantes::registrarError('ReporteModelo::contarTotalesPorEstado', 'Error al calcular totales de reporte', $e);
            return [
                'total_general' => 0,
                'programadas'   => 0,
                'confirmadas'   => 0,
                'atendidas'     => 0,
                'canceladas'    => 0,
                'inasistencias' => 0,
            ];
        }
    }

    /**
     * Obtiene los totales históricos globales de todo el sistema
     */
    public function obtenerTotalesGenerales(): array {
        try {
            $sqlCitas = "SELECT 
                            COUNT(*) AS total_citas,
                            COUNT(CASE WHEN estado = 'Programada' THEN 1 END) AS programadas,
                            COUNT(CASE WHEN estado = 'Confirmada' THEN 1 END) AS confirmadas,
                            COUNT(CASE WHEN estado = 'Atendida' THEN 1 END) AS atendidas,
                            COUNT(CASE WHEN estado = 'Cancelada' THEN 1 END) AS canceladas,
                            COUNT(CASE WHEN estado = 'Inasistencia' THEN 1 END) AS inasistencias
                        FROM citas_medicas";
            $resCitas = $this->pdo->query($sqlCitas)->fetch() ?: [];

            $sqlEntidades = "SELECT 
                                (SELECT COUNT(*) FROM pacientes WHERE activo = TRUE) AS total_pacientes,
                                (SELECT COUNT(*) FROM medicos WHERE activo = TRUE) AS total_medicos,
                                (SELECT COUNT(*) FROM especialidades WHERE activa = TRUE) AS total_especialidades,
                                (SELECT COUNT(*) FROM usuarios WHERE activo = TRUE) AS total_usuarios";
            $resEntidades = $this->pdo->query($sqlEntidades)->fetch() ?: [];

            return [
                'total_citas'          => (int)($resCitas['total_citas'] ?? 0),
                'programadas'          => (int)($resCitas['programadas'] ?? 0),
                'confirmadas'          => (int)($resCitas['confirmadas'] ?? 0),
                'atendidas'            => (int)($resCitas['atendidas'] ?? 0),
                'canceladas'           => (int)($resCitas['canceladas'] ?? 0),
                'inasistencias'        => (int)($resCitas['inasistencias'] ?? 0),
                'total_pacientes'      => (int)($resEntidades['total_pacientes'] ?? 0),
                'total_medicos'        => (int)($resEntidades['total_medicos'] ?? 0),
                'total_especialidades' => (int)($resEntidades['total_especialidades'] ?? 0),
                'total_usuarios'       => (int)($resEntidades['total_usuarios'] ?? 0),
            ];
        } catch (PDOException $e) {
            Ayudantes::registrarError('ReporteModelo::obtenerTotalesGenerales', 'Error al obtener totales globales', $e);
            return [];
        }
    }

    /**
     * Obtiene el reporte especializado de Pacientes Registrados con métricas de atención
     */
    public function obtenerReportePacientes(string $buscar = '', string $sexo = '', string $estado = '', string $tipoSangre = ''): array {
        try {
            $sql = "SELECT p.id, p.codigo, p.ci, p.nombres, p.apellidos, 
                           (p.nombres || ' ' || p.apellidos) AS nombre_completo,
                           p.telefono, p.correo, p.direccion, p.tipo_sangre, p.sexo, p.fecha_nacimiento,
                           p.fecha_creacion, p.activo,
                           COUNT(c.id) AS total_citas,
                           COUNT(CASE WHEN c.estado = 'Atendida' THEN 1 END) AS citas_atendidas,
                           COUNT(CASE WHEN c.estado = 'Programada' THEN 1 END) AS citas_programadas,
                           COUNT(CASE WHEN c.estado = 'Cancelada' THEN 1 END) AS citas_canceladas,
                           COUNT(CASE WHEN c.estado = 'Inasistencia' THEN 1 END) AS inasistencias
                    FROM pacientes p
                    LEFT JOIN citas_medicas c ON c.id_paciente = p.id";
            $condiciones = [];
            $params = [];

            if (!empty($buscar)) {
                $condiciones[] = "(p.ci ILIKE :buscar 
                             OR p.nombres ILIKE :buscar 
                             OR p.apellidos ILIKE :buscar 
                             OR (p.nombres || ' ' || p.apellidos) ILIKE :buscar 
                             OR p.codigo ILIKE :buscar
                             OR p.telefono ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            if (!empty($sexo)) {
                $condiciones[] = "p.sexo = :sexo";
                $params[':sexo'] = $sexo;
            }

            if ($estado === 'activo') {
                $condiciones[] = "p.activo = TRUE";
            } elseif ($estado === 'inactivo') {
                $condiciones[] = "p.activo = FALSE";
            }

            if (!empty($tipoSangre)) {
                $condiciones[] = "p.tipo_sangre = :tipoSangre";
                $params[':tipoSangre'] = $tipoSangre;
            }

            if (!empty($condiciones)) {
                $sql .= " WHERE " . implode(' AND ', $condiciones);
            }

            $sql .= " GROUP BY p.id ORDER BY p.apellidos ASC, p.nombres ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('ReporteModelo::obtenerReportePacientes', 'Error al obtener reporte de pacientes', $e);
            return [];
        }
    }

    /**
     * Obtiene el detalle exhaustivo de citas con filtros gerenciales para pantalla o CSV
     */
    public function obtenerDetalleReporte(string $fecha_inicio, string $fecha_fin, int $id_medico = 0, int $id_especialidad = 0, string $estado = ''): array {
        try {
            $sql = "SELECT c.id, c.codigo_cita, c.fecha, c.hora_inicio, c.hora_fin, c.estado, 
                           c.motivo_consulta, c.motivo_cancelacion, c.observaciones_atencion,
                           p.codigo AS codigo_paciente, p.ci AS ci_paciente, 
                           (p.nombres || ' ' || p.apellidos) AS nombre_paciente, p.telefono AS telefono_paciente,
                           p.tipo_sangre,
                           m.codigo AS codigo_medico, (m.nombres || ' ' || m.apellidos) AS nombre_medico,
                           m.registro_profesional AS matricula_medico,
                           e.nombre AS nombre_especialidad,
                           u.correo AS registrado_por
                    FROM citas_medicas c
                    INNER JOIN pacientes p ON p.id = c.id_paciente
                    INNER JOIN medicos m ON m.id = c.id_medico
                    INNER JOIN especialidades e ON e.id = c.id_especialidad
                    INNER JOIN usuarios u ON u.id = c.id_usuario_registro
                    WHERE c.fecha BETWEEN :inicio AND :fin";

            $params = [
                ':inicio' => $fecha_inicio,
                ':fin'    => $fecha_fin,
            ];

            if ($id_medico > 0) {
                $sql .= " AND c.id_medico = :id_medico";
                $params[':id_medico'] = $id_medico;
            }

            if ($id_especialidad > 0) {
                $sql .= " AND c.id_especialidad = :id_esp";
                $params[':id_esp'] = $id_especialidad;
            }

            if (!empty($estado)) {
                $sql .= " AND c.estado = :estado";
                $params[':estado'] = $estado;
            }

            $sql .= " ORDER BY c.fecha ASC, c.hora_inicio ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('ReporteModelo::obtenerDetalleReporte', 'Error al obtener detalle de reporte', $e);
            return [];
        }
    }

    /**
     * Agrupa y calcula el volumen de citas y atenciones por médico
     */
    public function obtenerCitasPorMedico(string $fecha_inicio, string $fecha_fin): array {
        try {
            $sql = "SELECT m.id, m.codigo, (m.nombres || ' ' || m.apellidos) AS nombre_medico, 
                           m.registro_profesional,
                           COUNT(c.id) AS total_citas,
                           COUNT(CASE WHEN c.estado = 'Programada' THEN 1 END) AS programadas,
                           COUNT(CASE WHEN c.estado = 'Confirmada' THEN 1 END) AS confirmadas,
                           COUNT(CASE WHEN c.estado = 'Atendida' THEN 1 END) AS atendidas,
                           COUNT(CASE WHEN c.estado = 'Cancelada' THEN 1 END) AS canceladas,
                           COUNT(CASE WHEN c.estado = 'Inasistencia' THEN 1 END) AS inasistencias
                    FROM medicos m
                    LEFT JOIN citas_medicas c ON c.id_medico = m.id AND c.fecha BETWEEN :inicio AND :fin
                    GROUP BY m.id
                    ORDER BY total_citas DESC, nombre_medico ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([
                ':inicio' => $fecha_inicio,
                ':fin'    => $fecha_fin,
            ]);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('ReporteModelo::obtenerCitasPorMedico', 'Error al agrupar citas por médico', $e);
            return [];
        }
    }

    /**
     * Agrupa y calcula el volumen de citas y demanda por especialidad médica
     */
    public function obtenerCitasPorEspecialidad(string $fecha_inicio, string $fecha_fin): array {
        try {
            $sql = "SELECT e.id, e.nombre AS nombre_especialidad,
                           COUNT(c.id) AS total_citas,
                           COUNT(CASE WHEN c.estado = 'Programada' THEN 1 END) AS programadas,
                           COUNT(CASE WHEN c.estado = 'Confirmada' THEN 1 END) AS confirmadas,
                           COUNT(CASE WHEN c.estado = 'Atendida' THEN 1 END) AS atendidas,
                           COUNT(CASE WHEN c.estado = 'Cancelada' THEN 1 END) AS canceladas,
                           COUNT(CASE WHEN c.estado = 'Inasistencia' THEN 1 END) AS inasistencias
                    FROM especialidades e
                    LEFT JOIN citas_medicas c ON c.id_especialidad = e.id AND c.fecha BETWEEN :inicio AND :fin
                    GROUP BY e.id
                    ORDER BY total_citas DESC, nombre_especialidad ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([
                ':inicio' => $fecha_inicio,
                ':fin'    => $fecha_fin,
            ]);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('ReporteModelo::obtenerCitasPorEspecialidad', 'Error al agrupar citas por especialidad', $e);
            return [];
        }
    }
}
