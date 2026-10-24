<?php
namespace App\Controladores;

use Exception;
use App\Modelos\UsuarioModelo;
use App\Nucleo\Ayudantes;

/**
 * Controlador de Administración de Usuarios y Operadores
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class UsuarioControlador {
    private UsuarioModelo $usuarioModelo;

    public function __construct(?UsuarioModelo $usuarioModelo = null) {
        $this->usuarioModelo = $usuarioModelo ?: new UsuarioModelo();
    }

    /**
     * Lista los usuarios con paginación estándar de 15 filas y filtro opcional por rol
     */
    public function listarUsuarios(int $pagina = 1, string $buscar = '', int $idRol = 0): array {
        $limite = defined('REGISTROS_POR_PAGINA') ? REGISTROS_POR_PAGINA : 15;
        $pagina = max(1, $pagina);
        $desplazamiento = ($pagina - 1) * $limite;

        $total = $this->usuarioModelo->contarTotal($buscar, $idRol);
        $registros = $this->usuarioModelo->obtenerPaginados($limite, $desplazamiento, $buscar, $idRol);
        $totalPaginas = max(1, (int)ceil($total / $limite));

        return [
            'registros'     => $registros,
            'total'         => $total,
            'pagina_actual' => $pagina,
            'total_paginas' => $totalPaginas,
            'limite'        => $limite,
        ];
    }

    /**
     * Registra un nuevo operador o usuario en el sistema
     */
    public function registrarUsuario(array $datos): array {
        try {
            $correo = strtolower(trim($datos['correo'] ?? ''));
            $nombres = trim($datos['nombres'] ?? '');
            $apellidos = trim($datos['apellidos'] ?? '');
            $telefono = trim($datos['telefono'] ?? '');
            $contrasena = trim($datos['contrasena'] ?? '');
            $idRol = (int)($datos['id_rol'] ?? 0);

            if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico no tiene un formato válido.'];
            }
            if (mb_strlen($nombres) < 2 || mb_strlen($apellidos) < 2) {
                return ['exito' => false, 'campo' => 'nombres', 'mensaje' => 'Los nombres y apellidos deben contener al menos 2 letras.'];
            }
            if (empty($telefono) || !preg_match('/^[0-9]{7,10}$/', $telefono)) {
                return ['exito' => false, 'campo' => 'telefono', 'mensaje' => 'El número de teléfono debe contener entre 7 y 10 dígitos numéricos.'];
            }
            if (empty($contrasena) || strlen($contrasena) < 6) {
                return ['exito' => false, 'campo' => 'contrasena', 'mensaje' => 'La contraseña debe contener al menos 6 caracteres.'];
            }

            // Un admin NO debe registrar pacientes desde aquí (eso es de Recepción)
            if ($idRol === 4) {
                return ['exito' => false, 'campo' => 'id_rol', 'mensaje' => 'Los pacientes solo pueden ser registrados por el personal de Recepción desde el módulo de Pacientes.'];
            }
            if ($idRol < 1 || $idRol > 3) {
                return ['exito' => false, 'campo' => 'id_rol', 'mensaje' => 'Debe seleccionar un rol válido (Administrador, Recepcionista o Médico).'];
            }

            if ($this->usuarioModelo->existeCorreo($correo)) {
                return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico ya se encuentra registrado con otro usuario.'];
            }

            $pdo = \App\Nucleo\Conexion::obtenerConexion();
            $stmtMed = $pdo->prepare("SELECT COUNT(*) FROM medicos WHERE LOWER(correo) = LOWER(:correo)");
            $stmtMed->execute([':correo' => $correo]);
            if ((int)$stmtMed->fetchColumn() > 0) {
                return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico ya se encuentra registrado con un médico.'];
            }

            // Validaciones específicas si es Médico
            $registroProfesional = '';
            $especialidades = [];
            if ($idRol === 3) {
                $registroProfesional = trim($datos['registro_profesional'] ?? '');
                $especialidades = $datos['especialidades'] ?? [];

                if (empty($registroProfesional)) {
                    return ['exito' => false, 'campo' => 'registro_profesional', 'mensaje' => 'La matrícula médica o registro profesional es obligatoria para el rol Médico.'];
                }
                if (empty($especialidades) || !is_array($especialidades)) {
                    return ['exito' => false, 'campo' => 'especialidades', 'mensaje' => 'Debe seleccionar al menos una especialidad médica para el doctor.'];
                }

                $medicoModelo = new \App\Modelos\MedicoModelo();
                if ($medicoModelo->buscarPorRegistroProfesional($registroProfesional)) {
                    return ['exito' => false, 'campo' => 'registro_profesional', 'mensaje' => 'La matrícula médica ya se encuentra registrada con otro médico.'];
                }
            }

            $idGenerado = $this->usuarioModelo->insertar([
                'correo'     => $correo,
                'nombres'    => $nombres,
                'apellidos'  => $apellidos,
                'telefono'   => $telefono,
                'contrasena' => $contrasena,
                'id_rol'     => $idRol,
                'activo'     => true,
            ]);

            // Si es Médico, registrarlo automáticamente en el plantel médico
            if ($idRol === 3 && $idGenerado > 0) {
                $medicoModelo = new \App\Modelos\MedicoModelo();
                $medicoModelo->insertar([
                    'registro_profesional' => $registroProfesional,
                    'nombres'              => $nombres,
                    'apellidos'            => $apellidos,
                    'telefono'             => $telefono,
                    'correo'               => $correo,
                    'id_usuario'           => $idGenerado,
                    'especialidades'       => array_map('intval', $especialidades),
                    'activo'               => true,
                ]);
            }

            return [
                'exito'   => true,
                'mensaje' => 'Usuario registrado exitosamente' . ($idRol === 3 ? ' y vinculado al plantel médico.' : '.'),
                'id'      => $idGenerado,
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('UsuarioControlador::registrarUsuario', 'Fallo al registrar usuario', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Ocurrió un error al procesar el registro del usuario.',
            ];
        }
    }

    /**
     * Modifica los datos de un usuario existente
     */
    public function modificarUsuario(int $id, array $datos): array {
        try {
            $usuario = $this->usuarioModelo->buscarPorId($id);
            if (!$usuario) {
                return ['exito' => false, 'mensaje' => 'El usuario no existe.'];
            }

            $correo = strtolower(trim($datos['correo'] ?? ''));
            $nombres = trim($datos['nombres'] ?? '');
            $apellidos = trim($datos['apellidos'] ?? '');
            $telefono = trim($datos['telefono'] ?? '');
            $idRol = (int)($datos['id_rol'] ?? 0);

            if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico es inválido.'];
            }
            if (mb_strlen($nombres) < 2 || mb_strlen($apellidos) < 2) {
                return ['exito' => false, 'campo' => 'nombres', 'mensaje' => 'Los nombres y apellidos deben contener al menos 2 letras.'];
            }
            if (empty($telefono) || !preg_match('/^[0-9]{7,10}$/', $telefono)) {
                return ['exito' => false, 'campo' => 'telefono', 'mensaje' => 'El número de teléfono debe contener entre 7 y 10 dígitos numéricos.'];
            }
            if ($idRol === 4) {
                return ['exito' => false, 'campo' => 'id_rol', 'mensaje' => 'No se puede asignar el rol Paciente desde este módulo.'];
            }
            if ($idRol < 1 || $idRol > 3) {
                return ['exito' => false, 'campo' => 'id_rol', 'mensaje' => 'Debe seleccionar un rol válido (Administrador, Recepcionista o Médico).'];
            }

            if ($this->usuarioModelo->existeCorreo($correo, $id)) {
                return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico ya está en uso por otra cuenta.'];
            }

            $pdo = \App\Nucleo\Conexion::obtenerConexion();
            $stmtMed = $pdo->prepare("SELECT COUNT(*) FROM medicos WHERE LOWER(correo) = LOWER(:correo) AND id_usuario <> :id_u");
            $stmtMed->execute([':correo' => $correo, ':id_u' => $id]);
            if ((int)$stmtMed->fetchColumn() > 0) {
                return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico ya se encuentra registrado con otro médico.'];
            }

            if (!empty($datos['contrasena']) && strlen(trim($datos['contrasena'])) < 6) {
                return ['exito' => false, 'campo' => 'contrasena', 'mensaje' => 'La nueva contraseña debe tener al menos 6 caracteres.'];
            }

            $this->usuarioModelo->actualizar($id, [
                'correo'     => $correo,
                'nombres'    => $nombres,
                'apellidos'  => $apellidos,
                'telefono'   => $telefono,
                'id_rol'     => $idRol,
                'contrasena' => !empty($datos['contrasena']) ? trim($datos['contrasena']) : null,
            ]);

            // Si es Médico o pasa a ser Médico, sincronizar datos con tabla medicos
            if ($idRol === 3) {
                $medicoModelo = new \App\Modelos\MedicoModelo();
                $registroProfesional = trim($datos['registro_profesional'] ?? '');
                $especialidades = $datos['especialidades'] ?? [];

                // Buscar si ya tiene ficha médica
                $pdo = \App\Nucleo\Conexion::obtenerConexion();
                $stmtMed = $pdo->prepare("SELECT id FROM medicos WHERE id_usuario = :id_usuario LIMIT 1");
                $stmtMed->execute([':id_usuario' => $id]);
                $idMedico = $stmtMed->fetchColumn();

                if ($idMedico) {
                    $medicoModelo->actualizar((int)$idMedico, [
                        'registro_profesional' => !empty($registroProfesional) ? $registroProfesional : $usuario['registro_profesional'] ?? 'MP-'.rand(1000,9999),
                        'nombres'              => $nombres,
                        'apellidos'            => $apellidos,
                        'telefono'             => $telefono,
                        'correo'               => $correo,
                        'especialidades'       => !empty($especialidades) ? array_map('intval', $especialidades) : null,
                    ]);
                } else if (!empty($registroProfesional)) {
                    $medicoModelo->insertar([
                        'registro_profesional' => $registroProfesional,
                        'nombres'              => $nombres,
                        'apellidos'            => $apellidos,
                        'telefono'             => $telefono,
                        'correo'               => $correo,
                        'id_usuario'           => $id,
                        'especialidades'       => !empty($especialidades) ? array_map('intval', $especialidades) : [],
                        'activo'               => true,
                    ]);
                }
            }

            return [
                'exito'   => true,
                'mensaje' => 'Usuario modificado correctamente.',
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('UsuarioControlador::modificarUsuario', 'Fallo al modificar usuario', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al actualizar los datos del usuario.',
            ];
        }
    }

    /**
     * Cambia la contraseña de un usuario
     */
    public function cambiarContrasena(int $id, string $nueva, string $confirmacion): array {
        try {
            $nueva = trim($nueva);
            $confirmacion = trim($confirmacion);

            if (empty($nueva) || strlen($nueva) < 6) {
                return ['exito' => false, 'mensaje' => 'La contraseña debe contener al menos 6 caracteres.'];
            }
            if ($nueva !== $confirmacion) {
                return ['exito' => false, 'mensaje' => 'Las contraseñas ingresadas no coinciden.'];
            }

            $resultado = $this->usuarioModelo->cambiarContrasena($id, $nueva);
            if ($resultado) {
                return ['exito' => true, 'mensaje' => 'Contraseña actualizada exitosamente.'];
            }
            return ['exito' => false, 'mensaje' => 'No se pudo actualizar la contraseña.'];
        } catch (Exception $e) {
            Ayudantes::registrarError('UsuarioControlador::cambiarContrasena', 'Error al cambiar contraseña', $e);
            return ['exito' => false, 'mensaje' => 'Error al cambiar la contraseña.'];
        }
    }

    /**
     * Invierte el estado de activación de un usuario
     */
    public function cambiarEstadoUsuario(int $id): bool {
        $usuario = $this->usuarioModelo->buscarPorId($id);
        if (!$usuario) {
            return false;
        }

        $nuevoEstado = empty($usuario['activo']);
        return $this->usuarioModelo->cambiarEstado($id, $nuevoEstado);
    }

    /**
     * Obtiene el catálogo de roles
     */
    public function obtenerRoles(): array {
        return $this->usuarioModelo->obtenerRoles();
    }

    /**
     * Busca los datos de un usuario por su ID
     */
    public function buscarPorId(int $id): ?array {
        return $this->usuarioModelo->buscarPorId($id);
    }
}
