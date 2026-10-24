<?php
namespace App\Modelos;

use PDO;
use PDOException;
use App\Nucleo\Conexion;
use App\Nucleo\Ayudantes;

/**
 * Modelo de Acceso a Datos para Pacientes y Ficha Médica
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class PacienteModelo {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
    }

    /**
     * Obtiene pacientes paginados a 15 registros con filtro por CI, nombres o apellidos
     */
    public function obtenerPaginados(int $limite = 15, int $desplazamiento = 0, string $buscar = ''): array {
        try {
            $sql = "SELECT id, codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, 
                           telefono, correo, direccion, 
                           tipo_sangre, alergias, enfermedades_cronicas, medicamentos_actuales, antecedentes_relevantes,
                           contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono, 
                           id_usuario, activo, fecha_creacion,
                           (nombres || ' ' || apellidos) AS nombre_completo
                    FROM pacientes";
            $params = [];

            if (!empty($buscar)) {
                $sql .= " WHERE (ci ILIKE :buscar 
                             OR nombres ILIKE :buscar 
                             OR apellidos ILIKE :buscar 
                             OR (nombres || ' ' || apellidos) ILIKE :buscar 
                             OR codigo ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            $sql .= " ORDER BY id DESC LIMIT :limite OFFSET :desplazamiento";
            $sentencia = $this->pdo->prepare($sql);

            foreach ($params as $clave => $valor) {
                $sentencia->bindValue($clave, $valor, PDO::PARAM_STR);
            }
            $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
            $sentencia->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);

            $sentencia->execute();
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::obtenerPaginados', 'Error al obtener pacientes paginados', $e);
            return [];
        }
    }

    /**
     * Cuenta el total de pacientes que coinciden con el filtro de búsqueda
     */
    public function contarTotal(string $buscar = ''): int {
        try {
            $sql = "SELECT COUNT(*) FROM pacientes";
            $params = [];

            if (!empty($buscar)) {
                $sql .= " WHERE (ci ILIKE :buscar 
                             OR nombres ILIKE :buscar 
                             OR apellidos ILIKE :buscar 
                             OR (nombres || ' ' || apellidos) ILIKE :buscar 
                             OR codigo ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return (int)$sentencia->fetchColumn();
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::contarTotal', 'Error al contar pacientes', $e);
            return 0;
        }
    }

    /**
     * Busca un paciente por su identificador primario (ID)
     */
    public function buscarPorId(int $id): ?array {
        try {
            $sql = "SELECT id, codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, 
                           telefono, correo, direccion, 
                           tipo_sangre, alergias, enfermedades_cronicas, medicamentos_actuales, antecedentes_relevantes,
                           contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono, 
                           id_usuario, activo, fecha_creacion,
                           (nombres || ' ' || apellidos) AS nombre_completo
                    FROM pacientes
                    WHERE id = :id
                    LIMIT 1";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
            $resultado = $sentencia->fetch();
            return $resultado ?: null;
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::buscarPorId', 'Error al buscar paciente por ID', $e);
            return null;
        }
    }

    /**
     * Busca un paciente por su Cédula de Identidad (CI)
     */
    public function buscarPorCi(string $ci, ?int $excluir_id = null): ?array {
        try {
            $sql = "SELECT id, codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, 
                           telefono, correo, direccion, 
                           tipo_sangre, alergias, enfermedades_cronicas, medicamentos_actuales, antecedentes_relevantes,
                           contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono, 
                           id_usuario, activo, fecha_creacion,
                           (nombres || ' ' || apellidos) AS nombre_completo
                    FROM pacientes
                    WHERE LOWER(ci) = LOWER(:ci)";
            $params = [':ci' => trim($ci)];

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
            Ayudantes::registrarError('PacienteModelo::buscarPorCi', 'Error al buscar paciente por CI', $e);
            return null;
        }
    }

    /**
     * Genera el siguiente código correlativo de paciente con formato PAC-XXXX
     */
    public function generarSiguienteCodigo(): string {
        try {
            $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo FROM 5) AS INTEGER)), 0) + 1 AS siguiente
                    FROM pacientes
                    WHERE codigo ~ '^PAC-[0-9]+$'";
            $sentencia = $this->pdo->query($sql);
            $siguiente = (int)$sentencia->fetchColumn();
            return sprintf("PAC-%04d", $siguiente ?: 1);
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::generarSiguienteCodigo', 'Error al generar código de paciente', $e);
            return 'PAC-' . date('YmdHis');
        }
    }

    /**
     * Registra un nuevo paciente en la base de datos
     */
    public function insertar(array $datos): int {
        try {
            $sql = "INSERT INTO pacientes (
                        codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, 
                        telefono, correo, direccion, 
                        tipo_sangre, alergias, enfermedades_cronicas, medicamentos_actuales, antecedentes_relevantes,
                        contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono, 
                        id_usuario, activo
                    ) VALUES (
                        :codigo, :ci, :nombres, :apellidos, :fecha_nacimiento, :sexo, 
                        :telefono, :correo, :direccion, 
                        :tipo_sangre, :alergias, :enfermedades_cronicas, :medicamentos_actuales, :antecedentes_relevantes,
                        :contacto_emergencia_nombre, :contacto_emergencia_parentesco, :contacto_emergencia_telefono, 
                        :id_usuario, :activo
                    ) RETURNING id";
            $sentencia = $this->pdo->prepare($sql);

            $codigo = !empty($datos['codigo']) ? trim($datos['codigo']) : $this->generarSiguienteCodigo();

            $sentencia->execute([
                ':codigo'                       => $codigo,
                ':ci'                           => trim($datos['ci']),
                ':nombres'                      => trim($datos['nombres']),
                ':apellidos'                    => trim($datos['apellidos']),
                ':fecha_nacimiento'             => $datos['fecha_nacimiento'],
                ':sexo'                         => strtoupper(trim($datos['sexo'])),
                ':telefono'                     => trim($datos['telefono']),
                ':correo'                       => !empty($datos['correo']) ? strtolower(trim($datos['correo'])) : null,
                ':direccion'                    => !empty($datos['direccion']) ? trim($datos['direccion']) : null,
                ':tipo_sangre'                  => !empty($datos['tipo_sangre']) ? trim($datos['tipo_sangre']) : 'ORH+',
                ':alergias'                     => !empty($datos['alergias']) ? trim($datos['alergias']) : 'Ninguna conocida',
                ':enfermedades_cronicas'        => !empty($datos['enfermedades_cronicas']) ? trim($datos['enfermedades_cronicas']) : 'Ninguna',
                ':medicamentos_actuales'        => !empty($datos['medicamentos_actuales']) ? trim($datos['medicamentos_actuales']) : 'Ninguno',
                ':antecedentes_relevantes'      => !empty($datos['antecedentes_relevantes']) ? trim($datos['antecedentes_relevantes']) : 'Sin antecedentes quirúrgicos',
                ':contacto_emergencia_nombre'   => !empty($datos['contacto_emergencia_nombre']) ? trim($datos['contacto_emergencia_nombre']) : null,
                ':contacto_emergencia_parentesco' => !empty($datos['contacto_emergencia_parentesco']) ? trim($datos['contacto_emergencia_parentesco']) : 'Familiar directo',
                ':contacto_emergencia_telefono' => !empty($datos['contacto_emergencia_telefono']) ? trim($datos['contacto_emergencia_telefono']) : null,
                ':id_usuario'                   => !empty($datos['id_usuario']) ? (int)$datos['id_usuario'] : null,
                ':activo'                       => isset($datos['activo']) ? (bool)$datos['activo'] : true,
            ]);

            return (int)$sentencia->fetchColumn();
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::insertar', 'Error al insertar paciente', $e);
            throw $e;
        }
    }

    /**
     * Actualiza la información personal, clínica y de emergencia del paciente
     */
    public function actualizar(int $id, array $datos): bool {
        try {
            $sql = "UPDATE pacientes SET 
                        ci = :ci,
                        nombres = :nombres,
                        apellidos = :apellidos,
                        fecha_nacimiento = :fecha_nacimiento,
                        sexo = :sexo,
                        telefono = :telefono,
                        correo = :correo,
                        direccion = :direccion,
                        tipo_sangre = :tipo_sangre,
                        alergias = :alergias,
                        enfermedades_cronicas = :enfermedades_cronicas,
                        medicamentos_actuales = :medicamentos_actuales,
                        antecedentes_relevantes = :antecedentes_relevantes,
                        contacto_emergencia_nombre = :contacto_emergencia_nombre,
                        contacto_emergencia_parentesco = :contacto_emergencia_parentesco,
                        contacto_emergencia_telefono = :contacto_emergencia_telefono
                    WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);

            return $sentencia->execute([
                ':id'                           => $id,
                ':ci'                           => trim($datos['ci']),
                ':nombres'                      => trim($datos['nombres']),
                ':apellidos'                    => trim($datos['apellidos']),
                ':fecha_nacimiento'             => $datos['fecha_nacimiento'],
                ':sexo'                         => strtoupper(trim($datos['sexo'])),
                ':telefono'                     => trim($datos['telefono']),
                ':correo'                       => !empty($datos['correo']) ? strtolower(trim($datos['correo'])) : null,
                ':direccion'                    => !empty($datos['direccion']) ? trim($datos['direccion']) : null,
                ':tipo_sangre'                  => !empty($datos['tipo_sangre']) ? trim($datos['tipo_sangre']) : 'ORH+',
                ':alergias'                     => !empty($datos['alergias']) ? trim($datos['alergias']) : 'Ninguna conocida',
                ':enfermedades_cronicas'        => !empty($datos['enfermedades_cronicas']) ? trim($datos['enfermedades_cronicas']) : 'Ninguna',
                ':medicamentos_actuales'        => !empty($datos['medicamentos_actuales']) ? trim($datos['medicamentos_actuales']) : 'Ninguno',
                ':antecedentes_relevantes'      => !empty($datos['antecedentes_relevantes']) ? trim($datos['antecedentes_relevantes']) : 'Sin antecedentes quirúrgicos',
                ':contacto_emergencia_nombre'   => !empty($datos['contacto_emergencia_nombre']) ? trim($datos['contacto_emergencia_nombre']) : null,
                ':contacto_emergencia_parentesco' => !empty($datos['contacto_emergencia_parentesco']) ? trim($datos['contacto_emergencia_parentesco']) : 'Familiar directo',
                ':contacto_emergencia_telefono' => !empty($datos['contacto_emergencia_telefono']) ? trim($datos['contacto_emergencia_telefono']) : null,
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::actualizar', 'Error al actualizar paciente', $e);
            throw $e;
        }
    }

    /**
     * Obtiene la ficha clínica completa y resumen estadístico de atenciones de un paciente
     */
    public function obtenerFichaCompleta(int $id): ?array {
        try {
            $paciente = $this->buscarPorId($id);
            if (!$paciente) {
                return null;
            }

            // Estadísticas de citas
            $sqlMetricas = "SELECT 
                                COUNT(*) AS total_citas,
                                COUNT(CASE WHEN estado = 'Atendida' THEN 1 END) AS atendidas,
                                COUNT(CASE WHEN estado = 'Programada' THEN 1 END) AS programadas,
                                COUNT(CASE WHEN estado = 'Confirmada' THEN 1 END) AS confirmadas,
                                COUNT(CASE WHEN estado = 'Cancelada' THEN 1 END) AS canceladas,
                                COUNT(CASE WHEN estado = 'Inasistencia' THEN 1 END) AS inasistencias
                            FROM citas_medicas
                            WHERE id_paciente = :id";
            $sentencia = $this->pdo->prepare($sqlMetricas);
            $sentencia->execute([':id' => $id]);
            $metricas = $sentencia->fetch() ?: [];

            // Datos de usuario vinculado si existe
            $usuario = null;
            if (!empty($paciente['id_usuario'])) {
                $sqlUsuario = "SELECT id, correo, activo, ultimo_acceso FROM usuarios WHERE id = :id_u";
                $sentenciaU = $this->pdo->prepare($sqlUsuario);
                $sentenciaU->execute([':id_u' => $paciente['id_usuario']]);
                $usuario = $sentenciaU->fetch() ?: null;
            }

            $paciente['metricas'] = [
                'total_citas'   => (int)($metricas['total_citas'] ?? 0),
                'atendidas'     => (int)($metricas['atendidas'] ?? 0),
                'programadas'   => (int)($metricas['programadas'] ?? 0),
                'confirmadas'   => (int)($metricas['confirmadas'] ?? 0),
                'canceladas'    => (int)($metricas['canceladas'] ?? 0),
                'inasistencias' => (int)($metricas['inasistencias'] ?? 0),
            ];
            $paciente['usuario_acceso'] = $usuario;

            return $paciente;
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::obtenerFichaCompleta', 'Error al obtener ficha completa', $e);
            return null;
        }
    }

    /**
     * Asocia una cuenta de usuario al paciente
     */
    public function vincularUsuario(int $id_paciente, int $id_usuario): bool {
        try {
            $sql = "UPDATE pacientes SET id_usuario = :id_u WHERE id = :id_p";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([
                ':id_u' => $id_usuario,
                ':id_p' => $id_paciente,
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::vincularUsuario', 'Error al vincular usuario a paciente', $e);
            return false;
        }
    }

    /**
     * Cambia el estado de activación de un paciente (Baja Lógica)
     */
    public function cambiarEstado(int $id, bool $activo): bool {
        try {
            $sql = "UPDATE pacientes SET activo = :activo WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([
                ':id'     => $id,
                ':activo' => $activo ? 'true' : 'false',
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::cambiarEstado', 'Error al cambiar estado de paciente', $e);
            return false;
        }
    }

    /**
     * Verifica si el paciente cuenta con citas históricas registradas
     */
    public function tieneCitasHistoricas(int $id): bool {
        try {
            $sql = "SELECT COUNT(*) FROM citas_medicas WHERE id_paciente = :id";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
            return (int)$sentencia->fetchColumn() > 0;
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::tieneCitasHistoricas', 'Error al verificar citas de paciente', $e);
            return false;
        }
    }

    /**
     * Búsqueda rápida para autocompletar en selección de citas
     */
    public function buscarParaSeleccion(string $termino, int $limite = 10): array {
        try {
            $sql = "SELECT id, codigo, ci, nombres, apellidos, telefono, tipo_sangre
                    FROM pacientes
                    WHERE activo = TRUE 
                      AND (ci ILIKE :termino 
                           OR nombres ILIKE :termino 
                           OR apellidos ILIKE :termino 
                           OR (nombres || ' ' || apellidos) ILIKE :termino)
                    ORDER BY apellidos ASC, nombres ASC
                    LIMIT :limite";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->bindValue(':termino', '%' . trim($termino) . '%', PDO::PARAM_STR);
            $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
            $sentencia->execute();
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('PacienteModelo::buscarParaSeleccion', 'Error al buscar para selección', $e);
            return [];
        }
    }
}
