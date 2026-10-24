<?php
namespace App\Modelos;

use PDO;
use PDOException;
use App\Nucleo\Conexion;
use App\Nucleo\Ayudantes;

/**
 * Modelo de Acceso a Datos para Especialidades Médicas
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class EspecialidadModelo {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
    }

    /**
     * Obtiene únicamente las especialidades activas para selección en citas
     */
    public function obtenerActivas(): array {
        try {
            $sql = "SELECT id, nombre, descripcion, duracion_minutos 
                    FROM especialidades 
                    WHERE activa = TRUE 
                    ORDER BY nombre ASC";
            $sentencia = $this->pdo->query($sql);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('EspecialidadModelo::obtenerActivas', 'Error al obtener especialidades activas', $e);
            return [];
        }
    }

    /**
     * Obtiene el catálogo completo de especialidades con métricas de médicos asociados
     */
    public function obtenerTodas(): array {
        try {
            $sql = "SELECT e.id, e.nombre, e.descripcion, e.duracion_minutos, e.activa, e.fecha_creacion,
                           COUNT(me.id_medico) AS total_medicos
                    FROM especialidades e
                    LEFT JOIN medico_especialidad me ON me.id_especialidad = e.id
                    GROUP BY e.id
                    ORDER BY e.nombre ASC";
            $sentencia = $this->pdo->query($sql);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('EspecialidadModelo::obtenerTodas', 'Error al obtener todas las especialidades', $e);
            return [];
        }
    }

    /**
     * Busca una especialidad por su ID
     */
    public function buscarPorId(int $id): ?array {
        try {
            $sql = "SELECT id, nombre, descripcion, duracion_minutos, activa, fecha_creacion
                    FROM especialidades
                    WHERE id = :id
                    LIMIT 1";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
            $resultado = $sentencia->fetch();
            return $resultado ?: null;
        } catch (PDOException $e) {
            Ayudantes::registrarError('EspecialidadModelo::buscarPorId', 'Error al buscar especialidad por ID', $e);
            return null;
        }
    }

    /**
     * Busca una especialidad por su nombre normalizado (ignorando mayúsculas y espacios extremos)
     */
    public function buscarPorNombre(string $nombre, ?int $excluir_id = null): ?array {
        return $this->buscarPorNombreNormalizado($nombre, $excluir_id);
    }

    /**
     * Busca por nombre normalizado LOWER(TRIM(nombre))
     */
    public function buscarPorNombreNormalizado(string $nombre, ?int $excluir_id = null): ?array {
        try {
            $sql = "SELECT id, nombre, descripcion, duracion_minutos, activa
                    FROM especialidades
                    WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre))";
            $params = [':nombre' => trim($nombre)];

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
            Ayudantes::registrarError('EspecialidadModelo::buscarPorNombreNormalizado', 'Error al buscar por nombre normalizado', $e);
            return null;
        }
    }

    /**
     * Registra una nueva especialidad médica
     */
    public function insertar(array $datos): int {
        try {
            $sql = "INSERT INTO especialidades (nombre, descripcion, duracion_minutos, activa)
                    VALUES (:nombre, :descripcion, :duracion_minutos, :activa)
                    RETURNING id";
            $sentencia = $this->pdo->prepare($sql);

            $duracion = !empty($datos['duracion_minutos']) ? (int)$datos['duracion_minutos'] : 30;

            $sentencia->execute([
                ':nombre'           => trim($datos['nombre']),
                ':descripcion'      => !empty($datos['descripcion']) ? trim($datos['descripcion']) : null,
                ':duracion_minutos' => $duracion,
                ':activa'           => isset($datos['activa']) ? (bool)$datos['activa'] : true,
            ]);

            return (int)$sentencia->fetchColumn();
        } catch (PDOException $e) {
            Ayudantes::registrarError('EspecialidadModelo::insertar', 'Error al insertar especialidad', $e);
            throw $e;
        }
    }

    /**
     * Modifica los datos de una especialidad existente
     */
    public function actualizar(int $id, array $datos): bool {
        try {
            $sql = "UPDATE especialidades SET 
                        nombre = :nombre,
                        descripcion = :descripcion,
                        duracion_minutos = :duracion_minutos
                    WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);

            $duracion = !empty($datos['duracion_minutos']) ? (int)$datos['duracion_minutos'] : 30;

            return $sentencia->execute([
                ':id'               => $id,
                ':nombre'           => trim($datos['nombre']),
                ':descripcion'      => !empty($datos['descripcion']) ? trim($datos['descripcion']) : null,
                ':duracion_minutos' => $duracion,
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('EspecialidadModelo::actualizar', 'Error al actualizar especialidad', $e);
            throw $e;
        }
    }

    /**
     * Cambia el estado de activación de una especialidad médica
     */
    public function cambiarEstado(int $id, bool $activa): bool {
        try {
            $sql = "UPDATE especialidades SET activa = :activa WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([
                ':id'     => $id,
                ':activa' => $activa ? 'true' : 'false',
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('EspecialidadModelo::cambiarEstado', 'Error al cambiar estado de especialidad', $e);
            return false;
        }
    }

    /**
     * Verifica si existen médicos asignados a la especialidad
     */
    public function tieneMedicosAsignados(int $id): bool {
        try {
            $sql = "SELECT COUNT(*) FROM medico_especialidad WHERE id_especialidad = :id";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
            return (int)$sentencia->fetchColumn() > 0;
        } catch (PDOException $e) {
            Ayudantes::registrarError('EspecialidadModelo::tieneMedicosAsignados', 'Error al verificar médicos asignados', $e);
            return false;
        }
    }
}
