<?php
namespace App\Modelos;

use PDO;
use PDOException;
use App\Nucleo\Conexion;
use App\Nucleo\Ayudantes;

/**
 * Modelo de Acceso a Datos para Usuarios y Roles
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class UsuarioModelo {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
    }

    /**
     * Busca un usuario por correo electrónico o CI de paciente vinculado
     */
    public function buscarPorIdentificador(string $identificador): ?array {
        try {
            $sql = "SELECT u.id, u.correo, u.contrasena_hash, u.id_rol, 
                           u.nombres, u.apellidos, u.activo, u.ultimo_acceso, u.fecha_creacion, 
                           r.nombre AS nombre_rol,
                           p.id AS id_paciente, p.ci AS ci_paciente,
                           m.id AS id_medico
                    FROM usuarios u
                    INNER JOIN roles r ON r.id = u.id_rol
                    LEFT JOIN pacientes p ON p.id_usuario = u.id
                    LEFT JOIN medicos m ON m.id_usuario = u.id
                    WHERE LOWER(u.correo) = LOWER(:identificador)
                       OR p.ci = :identificador
                    LIMIT 1";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':identificador' => trim($identificador)]);
            $resultado = $sentencia->fetch();
            return $resultado ?: null;
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::buscarPorIdentificador', 'Error al buscar por identificador', $e);
            return null;
        }
    }

    /**
     * Busca un usuario por su identificador primario (ID)
     */
    public function buscarPorId(int $id): ?array {
        try {
            $sql = "SELECT u.id, u.correo, u.id_rol, u.nombres, u.apellidos, u.telefono,
                           u.activo, u.ultimo_acceso, u.fecha_creacion, r.nombre AS nombre_rol,
                           p.id AS id_paciente, m.id AS id_medico, m.registro_profesional
                    FROM usuarios u
                    INNER JOIN roles r ON r.id = u.id_rol
                    LEFT JOIN pacientes p ON p.id_usuario = u.id
                    LEFT JOIN medicos m ON m.id_usuario = u.id
                    WHERE u.id = :id
                    LIMIT 1";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
            $resultado = $sentencia->fetch();
            return $resultado ?: null;
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::buscarPorId', 'Error al buscar usuario por ID', $e);
            return null;
        }
    }



    /**
     * Verifica si un correo electrónico ya está registrado
     */
    public function existeCorreo(string $correo, ?int $excluir_id = null): bool {
        try {
            $sql = "SELECT COUNT(*) FROM usuarios WHERE LOWER(correo) = LOWER(:correo)";
            $params = [':correo' => trim($correo)];
            if ($excluir_id !== null) {
                $sql .= " AND id <> :excluir_id";
                $params[':excluir_id'] = $excluir_id;
            }
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($params);
            return (int)$sentencia->fetchColumn() > 0;
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::existeCorreo', 'Error al verificar correo', $e);
            return false;
        }
    }

    /**
     * Registra un nuevo usuario en la base de datos
     */
    public function insertar(array $datos): int {
        try {
            $sql = "INSERT INTO usuarios (correo, contrasena_hash, id_rol, nombres, apellidos, telefono, activo)
                    VALUES (:correo, :contrasena_hash, :id_rol, :nombres, :apellidos, :telefono, :activo)
                    RETURNING id";
            $sentencia = $this->pdo->prepare($sql);

            $correo = strtolower(trim($datos['correo']));

            $contrasenaHash = isset($datos['contrasena']) 
                ? password_hash($datos['contrasena'], PASSWORD_BCRYPT)
                : ($datos['contrasena_hash'] ?? password_hash('admin123', PASSWORD_BCRYPT));

            $sentencia->execute([
                ':correo'          => $correo,
                ':contrasena_hash' => $contrasenaHash,
                ':id_rol'          => (int)$datos['id_rol'],
                ':nombres'         => !empty($datos['nombres']) ? trim($datos['nombres']) : null,
                ':apellidos'       => !empty($datos['apellidos']) ? trim($datos['apellidos']) : null,
                ':telefono'        => !empty($datos['telefono']) ? trim($datos['telefono']) : null,
                ':activo'          => isset($datos['activo']) ? (bool)$datos['activo'] : true,
            ]);

            return (int)$sentencia->fetchColumn();
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::insertar', 'Error al insertar usuario', $e);
            throw $e;
        }
    }

    /**
     * Modifica los datos de un usuario existente
     */
    public function actualizar(int $id, array $datos): bool {
        try {
            $correo = strtolower(trim($datos['correo']));

            $campos = [
                'correo = :correo',
                'id_rol = :id_rol',
                'nombres = :nombres',
                'apellidos = :apellidos',
                'telefono = :telefono',
            ];
            $params = [
                ':id'        => $id,
                ':correo'    => $correo,
                ':id_rol'    => (int)$datos['id_rol'],
                ':nombres'   => !empty($datos['nombres']) ? trim($datos['nombres']) : null,
                ':apellidos' => !empty($datos['apellidos']) ? trim($datos['apellidos']) : null,
                ':telefono'  => !empty($datos['telefono']) ? trim($datos['telefono']) : null,
            ];

            if (!empty($datos['contrasena'])) {
                $campos[] = 'contrasena_hash = :contrasena_hash';
                $params[':contrasena_hash'] = password_hash($datos['contrasena'], PASSWORD_BCRYPT);
            }

            if (isset($datos['activo'])) {
                $campos[] = 'activo = :activo';
                $params[':activo'] = (bool)$datos['activo'];
            }

            $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute($params);
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::actualizar', 'Error al actualizar usuario', $e);
            throw $e;
        }
    }

    /**
     * Cambia la contraseña de un usuario
     */
    public function cambiarContrasena(int $id, string $nuevaContrasena): bool {
        try {
            $sql = "UPDATE usuarios SET contrasena_hash = :hash WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([
                ':id'   => $id,
                ':hash' => password_hash($nuevaContrasena, PASSWORD_BCRYPT),
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::cambiarContrasena', 'Error al cambiar contraseña', $e);
            return false;
        }
    }

    /**
     * Actualiza la fecha y hora de último acceso
     */
    public function actualizarUltimoAcceso(int $id): void {
        try {
            $sql = "UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute([':id' => $id]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::actualizarUltimoAcceso', 'Error al actualizar último acceso', $e);
        }
    }

    /**
     * Cambia el estado de activación de un usuario (baja lógica o reactivación)
     */
    public function cambiarEstado(int $id, bool $activo): bool {
        try {
            $sql = "UPDATE usuarios SET activo = :activo WHERE id = :id";
            $sentencia = $this->pdo->prepare($sql);
            return $sentencia->execute([
                ':id'     => $id,
                ':activo' => $activo ? 'true' : 'false',
            ]);
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::cambiarEstado', 'Error al cambiar estado de usuario', $e);
            return false;
        }
    }

    /**
     * Obtiene usuarios con paginación estándar de 15 filas, filtro de búsqueda y filtro opcional de rol
     * Orden predeterminado: Administrador, Recepcionista, Médico y Paciente
     */
    public function obtenerPaginados(int $limite = 15, int $desplazamiento = 0, string $buscar = '', int $idRol = 0): array {
        try {
            $sql = "SELECT u.id, u.correo, u.id_rol, u.nombres, u.apellidos, u.telefono,
                           u.activo, u.ultimo_acceso, u.fecha_creacion, r.nombre AS nombre_rol,
                           TRIM(COALESCE(u.nombres, '') || ' ' || COALESCE(u.apellidos, '')) AS nombre_completo,
                           m.registro_profesional
                    FROM usuarios u
                    INNER JOIN roles r ON r.id = u.id_rol
                    LEFT JOIN medicos m ON m.id_usuario = u.id";
            $condiciones = [];
            $params = [];

            if (!empty($buscar)) {
                $condiciones[] = "(u.correo ILIKE :buscar 
                              OR u.nombres ILIKE :buscar 
                              OR u.apellidos ILIKE :buscar 
                              OR (u.nombres || ' ' || u.apellidos) ILIKE :buscar 
                              OR r.nombre ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            if ($idRol > 0) {
                $condiciones[] = "u.id_rol = :id_rol";
                $params[':id_rol'] = $idRol;
            }

            if (!empty($condiciones)) {
                $sql .= " WHERE " . implode(' AND ', $condiciones);
            }

            $sql .= " ORDER BY CASE r.nombre 
                               WHEN 'Administrador' THEN 1 
                               WHEN 'Recepcionista' THEN 2 
                               WHEN 'Medico' THEN 3 
                               WHEN 'Paciente' THEN 4 
                               ELSE 5 END, u.id ASC LIMIT :limite OFFSET :desplazamiento";
            $sentencia = $this->pdo->prepare($sql);

            foreach ($params as $clave => $valor) {
                if ($clave === ':id_rol') {
                    $sentencia->bindValue($clave, $valor, PDO::PARAM_INT);
                } else {
                    $sentencia->bindValue($clave, $valor, PDO::PARAM_STR);
                }
            }
            $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
            $sentencia->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);

            $sentencia->execute();
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::obtenerPaginados', 'Error al listar usuarios paginados', $e);
            return [];
        }
    }

    /**
     * Cuenta el total de usuarios según el criterio de búsqueda y rol
     */
    public function contarTotal(string $buscar = '', int $idRol = 0): int {
        try {
            $sql = "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.id_rol";
            $condiciones = [];
            $params = [];

            if (!empty($buscar)) {
                $condiciones[] = "(u.correo ILIKE :buscar 
                              OR u.nombres ILIKE :buscar 
                              OR u.apellidos ILIKE :buscar 
                              OR (u.nombres || ' ' || u.apellidos) ILIKE :buscar 
                              OR r.nombre ILIKE :buscar)";
                $params[':buscar'] = '%' . trim($buscar) . '%';
            }

            if ($idRol > 0) {
                $condiciones[] = "u.id_rol = :id_rol";
                $params[':id_rol'] = $idRol;
            }

            if (!empty($condiciones)) {
                $sql .= " WHERE " . implode(' AND ', $condiciones);
            }

            $sentencia = $this->pdo->prepare($sql);
            foreach ($params as $clave => $valor) {
                if ($clave === ':id_rol') {
                    $sentencia->bindValue($clave, $valor, PDO::PARAM_INT);
                } else {
                    $sentencia->bindValue($clave, $valor, PDO::PARAM_STR);
                }
            }
            $sentencia->execute();
            return (int)$sentencia->fetchColumn();
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::contarTotal', 'Error al contar total de usuarios', $e);
            return 0;
        }
    }

    /**
     * Obtiene el catálogo de roles activos en el sistema
     */
    public function obtenerRoles(): array {
        try {
            $sql = "SELECT id, nombre, descripcion, activo FROM roles WHERE activo = TRUE ORDER BY id ASC";
            $sentencia = $this->pdo->query($sql);
            return $sentencia->fetchAll();
        } catch (PDOException $e) {
            Ayudantes::registrarError('UsuarioModelo::obtenerRoles', 'Error al obtener roles', $e);
            return [];
        }
    }
}
