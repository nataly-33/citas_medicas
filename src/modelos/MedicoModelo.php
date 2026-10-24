<?php
namespace App\Modelos;

use PDO;
use PDOException;
use App\Nucleo\Conexion;
use App\Nucleo\Ayudantes;

/**
 * Modelo de Acceso a Datos para Médicos y Asignación de Especialidades
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class MedicoModelo {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
    }

    /**
     * Obtiene médicos paginados a 15 registros con filtros por especialidad y búsqueda
     */
    public function obtenerPaginados(int $limite = 15, int $desplazamiento = 0, int $id_especialidad = 0, string $buscar = ''): array {
        try {
            $sql = "SELECT m.id, m.codigo, m.registro_profesional, m.nombres, m.apellidos, 
                           m.telefono, m.correo, m.id_usuario, m.activo, m.fecha_creacion,
                           COALESCE(STRING_AGG(DISTINCT e.nombre, ', ' ORDER BY e.nombre), 'Sin especialidad') AS especialidades_nombres
                    FROM medicos m
                    LEFT JOIN medico_especialidad me ON me.id_medico = m.id
                    LEFT JOIN especialidades e ON e.id = me.id_especialidad";
            
            $condiciones = [];
            $params = [];

            if ($id_especialidad > 0) {
                $condiciones[] = "m.id IN (SELECT id_medico FROM medico_especialidad WHERE id_especialidad = :id_esp)";
                $params[':id_esp'] = $id_especialidad;
            }

            if (!empty($buscar)) {
                $condiciones[] = "(m.codigo ILIKE :buscar 
                               OR m.registro_profesional ILIKE :buscar 
                               OR m.nombres ILIKE :buscar 
                               OR m.apellidos ILIKE :buscar 
                               OR (m.nombres || ' ' || m.apellidos) ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            if (!empty($condiciones)) {
                $sql .= " WHERE " . implode(" AND ", $condiciones);
            }

            $sql .= " GROUP BY m.id ORDER BY m.id DESC LIMIT :limite OFFSET :desplazamiento";
            $sentencia = $this->pdo->prepare($sql);

            foreach ($params as $clave => $valor) {
                $sentencia->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
            $sentencia->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);

            $sentencia->execute();
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::obtenerPaginados', 'Error al obtener médicos paginados', $e);
            return [];
        }
    }

    /**
     * Cuenta el total de médicos que coinciden con los filtros aplicados
     */
    public function contarTotal(int $id_especialidad = 0, string $buscar = ''): int {
        try {
            $sql = "SELECT COUNT(DISTINCT m.id) FROM medicos m";
            $condiciones = [];
            $params = [];

            if ($id_especialidad > 0) {
                $condiciones[] = "m.id IN (SELECT id_medico FROM medico_especialidad WHERE id_especialidad = :id_esp)";
                $params[':id_esp'] = $id_especialidad;
            }

            if (!empty($buscar)) {
                $condiciones[] = "(m.codigo ILIKE :buscar 
                               OR m.registro_profesional ILIKE :buscar 
                               OR m.nombres ILIKE :buscar 
                               OR m.apellidos ILIKE :buscar 
                               OR (m.nombres || ' ' || m.apellidos) ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            if (!empty($condiciones)) {
                $sql .= " WHERE " . implode(" AND ", $condiciones);
            }

            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return (int)$sentencia->fetchColumn();
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::contarTotal', 'Error al contar médicos', $e);
            return 0;
        }
    }

    /**
     * Busca un médico por su identificador primario (ID)
     */
    public function buscarPorId(int $id): ?array {
        try {
            $sql = "SELECT m.id, m.codigo, m.registro_profesional, m.nombres, m.apellidos, 
                           m.telefono, m.correo, m.id_usuario, m.activo, m.fecha_creacion,
                           COALESCE(STRING_AGG(DISTINCT e.nombre, ', ' ORDER BY e.nombre), '') AS especialidades_nombres
                    FROM medicos m
                    LEFT JOIN medico_especialidad me ON me.id_medico = m.id
                    LEFT JOIN especialidades e ON e.id = me.id_especialidad
                    WHERE m.id = :id
                    GROUP BY m.id
                    LIMIT 1";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
            $resultado = $sentencia->fetch();
            return $resultado ?: null;
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::buscarPorId', 'Error al buscar médico por ID', $e);
            return null;
        }
    }

    /**
     * Busca un médico por su matrícula médica / registro profesional único (RN02)
     */
    public function buscarPorRegistroProfesional(string $registro_profesional, ?int $excluir_id = null): ?array {
        try {
            $sql = "SELECT id, codigo, registro_profesional, nombres, apellidos, telefono, correo, activo
                    FROM medicos
                    WHERE LOWER(registro_profesional) = LOWER(:reg)";
            $params = [':reg' => trim($registro_profesional)];

            if ($excluir_id !== null) {
                $sql .= " AND id <> :excluir_id";
                $params[':excluir_id'] = $excluir_id;
            }

            $sql .= " LIMIT 1";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            $resultado = $sentencia->fetch();
            return $resultado ?: null;
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::buscarPorRegistroProfesional', 'Error al buscar por registro', $e);
            return null;
        }
    }

    /**
     * Genera el siguiente código correlativo de médico con formato MED-XXX
     */
    public function generarSiguienteCodigo(): string {
        try {
            $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo FROM 5) AS INTEGER)), 0) + 1 AS siguiente
                    FROM medicos
                    WHERE codigo ~ '^MED-[0-9]+$'";
            $sentencia = $this->pdo->query($sql);
            $siguiente = (int)$sentencia->fetchColumn();
            return sprintf("MED-%03d", $siguiente ?: 1);
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::generarSiguienteCodigo', 'Error al generar código de médico', $e);
            return 'MED-' . date('YmdHis');
        }
    }

    /**
     * Registra un nuevo médico y sincroniza sus especialidades
     */
    public function insertar(array $datos): int {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO medicos (
                        codigo, registro_profesional, nombres, apellidos, telefono, correo, id_usuario, activo
                    ) VALUES (
                        :codigo, :registro_profesional, :nombres, :apellidos, :telefono, :correo, :id_usuario, :activo
                    ) RETURNING id";
            $sentencia = $this->pdo->prepare($sql);

            $codigo = !empty($datos['codigo']) ? trim($datos['codigo']) : $this->generarSiguienteCodigo();

            $sentencia->execute([
                ':codigo'               => $codigo,
                ':registro_profesional' => trim($datos['registro_profesional']),
                ':nombres'              => trim($datos['nombres']),
                ':apellidos'            => trim($datos['apellidos']),
                ':telefono'             => trim($datos['telefono']),
                ':correo'               => !empty($datos['correo']) ? strtolower(trim($datos['correo'])) : null,
                ':id_usuario'           => !empty($datos['id_usuario']) ? (int)$datos['id_usuario'] : null,
                ':activo'               => isset($datos['activo']) ? (bool)$datos['activo'] : true,
            ]);

            $idMedico = (int)$sentencia->fetchColumn();

            // Sincronizar especialidades asociadas
            if (!empty($datos['especialidades']) && is_array($datos['especialidades'])) {
                $this->sincronizarEspecialidadesInterno($idMedico, $datos['especialidades']);
            }

            $this->pdo->commit();
            return $idMedico;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Ayudantes::registrarError('MedicoModelo::insertar', 'Error al insertar médico', $e);
            throw $e;
        }
    }

    /**
     * Actualiza la información personal, profesional y especialidades del médico
     */
    public function actualizar(int $id, array $datos): bool {
        try {
            $this->pdo->beginTransaction();

            $sql = "UPDATE medicos SET 
                        registro_profesional = :registro_profesional,
                        nombres = :nombres,
                        apellidos = :apellidos,
                        telefono = :telefono,
                        correo = :correo
                    WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);

            $resultado = $sentencia->execute([
                ':id'                   => $id,
                ':registro_profesional' => trim($datos['registro_profesional']),
                ':nombres'              => trim($datos['nombres']),
                ':apellidos'            => trim($datos['apellidos']),
                ':telefono'             => trim($datos['telefono']),
                ':correo'               => !empty($datos['correo']) ? strtolower(trim($datos['correo'])) : null,
            ]);

            // Sincronizar especialidades
            if (isset($datos['especialidades']) && is_array($datos['especialidades'])) {
                $this->sincronizarEspecialidadesInterno($id, $datos['especialidades']);
            }

            $this->pdo->commit();
            return $resultado;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Ayudantes::registrarError('MedicoModelo::actualizar', 'Error al actualizar médico', $e);
            throw $e;
        }
    }

    /**
     * Sincroniza la tabla pivote medico_especialidad
     */
    public function sincronizarEspecialidades(int $id_medico, array $especialidades_ids): void {
        try {
            $this->pdo->beginTransaction();
            $this->sincronizarEspecialidadesInterno($id_medico, $especialidades_ids);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Ayudantes::registrarError('MedicoModelo::sincronizarEspecialidades', 'Error al sincronizar especialidades', $e);
            throw $e;
        }
    }

    private function sincronizarEspecialidadesInterno(int $id_medico, array $especialidades_ids): void {
        // Eliminar asignaciones previas
        $sqlEliminar = "DELETE FROM medico_especialidad WHERE id_medico = :id_medico";
        $sentenciaEliminar = $this->pdo->prepare($sqlEliminar);
        $sentenciaEliminar->execute([':id_medico' => $id_medico]);

        // Insertar las nuevas
        if (!empty($especialidades_ids)) {
            $sqlInsertar = "INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES (:id_medico, :id_especialidad) ON CONFLICT DO NOTHING";
            $sentenciaInsertar = $this->pdo->prepare($sqlInsertar);

            foreach ($especialidades_ids as $idEsp) {
                if ((int)$idEsp > 0) {
                    $sentenciaInsertar->execute([
                        ':id_medico'       => $id_medico,
                        ':id_especialidad' => (int)$idEsp,
                    ]);
                }
            }
        }
    }

    /**
     * Obtiene los IDs y nombres de especialidades asignadas a un médico
     */
    public function obtenerEspecialidadesPorMedico(int $id_medico): array {
        try {
            $sql = "SELECT e.id, e.nombre, e.duracion_minutos
                    FROM especialidades e
                    INNER JOIN medico_especialidad me ON me.id_especialidad = e.id
                    WHERE me.id_medico = :id_medico
                    ORDER BY e.nombre ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id_medico' => $id_medico]);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::obtenerEspecialidadesPorMedico', 'Error al consultar especialidades', $e);
            return [];
        }
    }

    /**
     * Cambia el estado de activación de un médico (Baja lógica - RN04, RN11)
     */
    public function cambiarEstado(int $id, bool $activo): bool {
        try {
            $sql = "UPDATE medicos SET activo = :activo WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([
                ':id'     => $id,
                ':activo' => $activo ? 'true' : 'false',
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::cambiarEstado', 'Error al cambiar estado de médico', $e);
            return false;
        }
    }

    /**
     * Verifica si el médico tiene citas registradas en el histórico (RN11)
     */
    public function tieneCitasHistoricas(int $id): bool {
        try {
            $sql = "SELECT COUNT(*) FROM citas_medicas WHERE id_medico = :id";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
            return (int)$sentencia->fetchColumn() > 0;
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::tieneCitasHistoricas', 'Error al verificar citas del médico', $e);
            return false;
        }
    }

    /**
     * Obtiene todos los médicos activos para selectores
     */
    public function obtenerActivos(): array {
        try {
            $sql = "SELECT id, codigo, (nombres || ' ' || apellidos) AS nombre_completo, registro_profesional
                    FROM medicos
                    WHERE activo = TRUE
                    ORDER BY apellidos ASC, nombres ASC";
            $sentencia = $this->pdo->query($sql);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::obtenerActivos', 'Error al obtener médicos activos', $e);
            return [];
        }
    }

    /**
     * Obtiene médicos activos filtrados por especialidad
     */
    public function obtenerActivosPorEspecialidad(int $id_especialidad): array {
        try {
            $sql = "SELECT m.id, m.codigo, (m.nombres || ' ' || m.apellidos) AS nombre_completo, m.registro_profesional
                    FROM medicos m
                    INNER JOIN medico_especialidad me ON me.id_medico = m.id
                    WHERE m.activo = TRUE AND me.id_especialidad = :id_especialidad
                    ORDER BY m.apellidos ASC, m.nombres ASC";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id_especialidad' => $id_especialidad]);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('MedicoModelo::obtenerActivosPorEspecialidad', 'Error al filtrar médicos por especialidad', $e);
            return [];
        }
    }
}
