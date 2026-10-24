<?php
namespace App\Controladores;

use Exception;
use App\Modelos\MedicoModelo;
use App\Modelos\HorarioModelo;
use App\Modelos\EspecialidadModelo;
use App\Modelos\UsuarioModelo;
use App\Nucleo\Ayudantes;

/**
 * Controlador de Gestión de Médicos y Turnos Semanales
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class MedicoControlador {
    private MedicoModelo $medicoModelo;
    private HorarioModelo $horarioModelo;
    private EspecialidadModelo $especialidadModelo;
    private UsuarioModelo $usuarioModelo;

    public function __construct(
        ?MedicoModelo $medicoModelo = null,
        ?HorarioModelo $horarioModelo = null,
        ?EspecialidadModelo $especialidadModelo = null,
        ?UsuarioModelo $usuarioModelo = null
    ) {
        $this->medicoModelo = $medicoModelo ?: new MedicoModelo();
        $this->horarioModelo = $horarioModelo ?: new HorarioModelo();
        $this->especialidadModelo = $especialidadModelo ?: new EspecialidadModelo();
        $this->usuarioModelo = $usuarioModelo ?: new UsuarioModelo();
    }

    /**
     * Lista los médicos con paginación de 15 filas y filtros por especialidad y búsqueda
     */
    public function listarMedicos(int $pagina = 1, int $id_especialidad = 0, string $buscar = ''): array {
        $limite = defined('REGISTROS_POR_PAGINA') ? REGISTROS_POR_PAGINA : 15;
        $pagina = max(1, $pagina);
        $desplazamiento = ($pagina - 1) * $limite;

        $total = $this->medicoModelo->contarTotal($id_especialidad, $buscar);
        $registros = $this->medicoModelo->obtenerPaginados($limite, $desplazamiento, $id_especialidad, $buscar);
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
     * Registra un nuevo médico y genera automáticamente su cuenta de usuario
     */
    public function registrarMedico(array $datos): array {
        return $this->crear($datos);
    }

    /**
     * Crea un médico en la base de datos
     */
    public function crear(array $datos): array {
        try {
            $registroProfesional = trim($datos['registro_profesional'] ?? '');
            $nombres = trim($datos['nombres'] ?? '');
            $apellidos = trim($datos['apellidos'] ?? '');
            $telefono = trim($datos['telefono'] ?? '');
            $correo = trim($datos['correo'] ?? '');
            $especialidades = $datos['especialidades'] ?? [];

            // 1. Validaciones con mensajes limpios
            if (empty($registroProfesional)) {
                return ['exito' => false, 'campo' => 'registro_profesional', 'mensaje' => 'La matrícula médica o registro profesional es obligatorio.'];
            }
            if (mb_strlen($nombres) < 2 || mb_strlen($apellidos) < 2) {
                return ['exito' => false, 'campo' => 'nombres', 'mensaje' => 'Los nombres y apellidos deben contener al menos 2 letras.'];
            }
            if (empty($telefono) || !preg_match('/^[0-9]{7,10}$/', $telefono)) {
                return ['exito' => false, 'campo' => 'telefono', 'mensaje' => 'El número de teléfono debe contener entre 7 y 10 dígitos numéricos.'];
            }
            if (empty($especialidades) || !is_array($especialidades)) {
                return ['exito' => false, 'campo' => 'especialidades', 'mensaje' => 'Debe asignar al menos una especialidad al médico.'];
            }

            // 2. Unicidad de registro profesional
            $existente = $this->medicoModelo->buscarPorRegistroProfesional($registroProfesional);
            if ($existente) {
                return [
                    'exito'   => false,
                    'campo'   => 'registro_profesional',
                    'mensaje' => 'La matrícula médica o registro profesional ya se encuentra registrado con otro médico.',
                ];
            }

            // 3. Crear cuenta de usuario con rol Medico (id_rol = 3)
            $correoUsuario = !empty($correo) ? strtolower(trim($correo)) : ('medico_' . preg_replace('/[^0-9]/', '', $registroProfesional) . '@vidasalud.bo');

            if (!filter_var($correoUsuario, FILTER_VALIDATE_EMAIL)) {
                return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico no tiene un formato válido.'];
            }

            // Validación estricta de unicidad de correo en usuarios y médicos
            if ($this->usuarioModelo->existeCorreo($correoUsuario)) {
                return [
                    'exito'   => false,
                    'campo'   => 'correo',
                    'mensaje' => 'El correo electrónico ya se encuentra registrado con otro usuario en el sistema.',
                ];
            }

            $pdo = \App\Nucleo\Conexion::obtenerConexion();
            $stmtM = $pdo->prepare("SELECT COUNT(*) FROM medicos WHERE LOWER(correo) = LOWER(:correo)");
            $stmtM->execute([':correo' => $correoUsuario]);
            if ((int)$stmtM->fetchColumn() > 0) {
                return [
                    'exito'   => false,
                    'campo'   => 'correo',
                    'mensaje' => 'El correo electrónico ya se encuentra registrado con otro médico.',
                ];
            }

            $contrasenaPlana = !empty($datos['contrasena']) ? trim($datos['contrasena']) : 'admin123';

            $idUsuario = $this->usuarioModelo->insertar([
                'correo'     => $correoUsuario,
                'contrasena' => $contrasenaPlana,
                'id_rol'     => 3, // Rol Medico
                'nombres'    => $nombres,
                'apellidos'  => $apellidos,
                'telefono'   => $telefono,
                'activo'     => true,
            ]);

            // 4. Código y registro del médico
            $codigo = $this->medicoModelo->generarSiguienteCodigo();
            $datos['codigo'] = $codigo;
            $datos['correo'] = $correoUsuario;
            $datos['id_usuario'] = $idUsuario;
            $datos['activo'] = true;

            $idGenerado = $this->medicoModelo->insertar($datos);
            $nuevoMedico = $this->medicoModelo->buscarPorId($idGenerado);

            return [
                'exito'   => true,
                'mensaje' => "Médico registrado exitosamente con código {$codigo}. Su cuenta de acceso es '{$correoUsuario}' con contraseña {$contrasenaPlana}.",
                'medico'  => $nuevoMedico,
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('MedicoControlador::crear', 'Error al registrar médico', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al procesar el registro del médico.',
            ];
        }
    }

    /**
     * Modifica los datos personales y especialidades de un médico
     */
    public function modificarMedico(int $id, array $datos): array {
        try {
            $medico = $this->medicoModelo->buscarPorId($id);
            if (!$medico) {
                return ['exito' => false, 'mensaje' => 'El médico solicitado no existe.'];
            }

            $registroProfesional = trim($datos['registro_profesional'] ?? '');
            $nombres = trim($datos['nombres'] ?? '');
            $apellidos = trim($datos['apellidos'] ?? '');
            $telefono = trim($datos['telefono'] ?? '');
            $correo = trim($datos['correo'] ?? '');
            $especialidades = $datos['especialidades'] ?? [];

            if (empty($registroProfesional)) {
                return ['exito' => false, 'campo' => 'registro_profesional', 'mensaje' => 'La matrícula médica o registro profesional es obligatorio.'];
            }
            if (mb_strlen($nombres) < 2 || mb_strlen($apellidos) < 2) {
                return ['exito' => false, 'campo' => 'nombres', 'mensaje' => 'Los nombres y apellidos deben contener al menos 2 letras.'];
            }
            if (empty($telefono) || !preg_match('/^[0-9]{7,10}$/', $telefono)) {
                return ['exito' => false, 'campo' => 'telefono', 'mensaje' => 'El número de teléfono debe contener entre 7 y 10 dígitos numéricos.'];
            }
            if (empty($especialidades) || !is_array($especialidades)) {
                return ['exito' => false, 'campo' => 'especialidades', 'mensaje' => 'El médico debe contar con al menos una especialidad asignada.'];
            }

            // Unicidad de registro profesional
            $duplicado = $this->medicoModelo->buscarPorRegistroProfesional($registroProfesional, $id);
            if ($duplicado) {
                return [
                    'exito'   => false,
                    'campo'   => 'registro_profesional',
                    'mensaje' => 'La matrícula médica o registro profesional ya se encuentra registrado con otro médico.',
                ];
            }

            // Validación de correo único
            if (!empty($correo)) {
                $correoLimpio = strtolower(trim($correo));
                if (!filter_var($correoLimpio, FILTER_VALIDATE_EMAIL)) {
                    return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico no tiene un formato válido.'];
                }

                $pdo = \App\Nucleo\Conexion::obtenerConexion();
                $stmtM = $pdo->prepare("SELECT COUNT(*) FROM medicos WHERE LOWER(correo) = :correo AND id <> :id");
                $stmtM->execute([':correo' => $correoLimpio, ':id' => $id]);
                if ((int)$stmtM->fetchColumn() > 0) {
                    return [
                        'exito'   => false,
                        'campo'   => 'correo',
                        'mensaje' => 'El correo electrónico ya se encuentra registrado con otro médico.',
                    ];
                }

                $idUsuario = (int)($medico['id_usuario'] ?? 0);
                if ($this->usuarioModelo->existeCorreo($correoLimpio, $idUsuario ?: null)) {
                    return [
                        'exito'   => false,
                        'campo'   => 'correo',
                        'mensaje' => 'El correo electrónico ya se encuentra registrado con otro usuario en el sistema.',
                    ];
                }
                $datos['correo'] = $correoLimpio;
            }

            $this->medicoModelo->actualizar($id, $datos);

            // Sincronizar o crear la cuenta de usuario vinculada
            $correoFinal = !empty($correo) ? strtolower(trim($correo)) : $medico['correo'];
            if (!empty($medico['id_usuario'])) {
                $datosUsuario = [
                    'correo'    => $correoFinal,
                    'nombres'   => $nombres,
                    'apellidos' => $apellidos,
                    'telefono'  => $telefono,
                    'id_rol'    => 3,
                ];
                if (!empty($datos['contrasena'])) {
                    $datosUsuario['contrasena'] = trim($datos['contrasena']);
                }
                $this->usuarioModelo->actualizar((int)$medico['id_usuario'], $datosUsuario);
            } else {
                // Si el médico no tenía cuenta vinculada, crearla automáticamente
                $pdo = \App\Nucleo\Conexion::obtenerConexion();
                $idNuevoUsuario = $this->usuarioModelo->insertar([
                    'correo'     => $correoFinal,
                    'contrasena' => !empty($datos['contrasena']) ? trim($datos['contrasena']) : 'admin123',
                    'id_rol'     => 3,
                    'nombres'    => $nombres,
                    'apellidos'  => $apellidos,
                    'telefono'   => $telefono,
                    'activo'     => true,
                ]);
                $stmtUpd = $pdo->prepare("UPDATE medicos SET id_usuario = :id_u WHERE id = :id_m");
                $stmtUpd->execute([':id_u' => $idNuevoUsuario, ':id_m' => $id]);
            }

            return [
                'exito'   => true,
                'mensaje' => 'Datos del médico actualizados correctamente.',
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('MedicoControlador::modificarMedico', 'Error al modificar médico', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al actualizar los datos del médico.',
            ];
        }
    }

    /**
     * Cambia el estado de activación de un médico (Baja lógica)
     */
    public function cambiarEstadoMedico(int $id): bool {
        $medico = $this->medicoModelo->buscarPorId($id);
        if (!$medico) {
            return false;
        }

        $nuevoEstado = empty($medico['activo']);
        return $this->medicoModelo->cambiarEstado($id, $nuevoEstado);
    }

    /**
     * Obtiene los horarios semanales de atención de un médico
     */
    public function obtenerHorariosMedico(int $id_medico): array {
        return $this->horarioModelo->obtenerTodosPorMedico($id_medico);
    }

    /**
     * Guarda la grilla completa de horarios de un médico
     */
    public function guardarHorariosMedico(int $id_medico, array $horarios): bool {
        return $this->horarioModelo->sincronizarHorarios($id_medico, $horarios);
    }

    /**
     * Obtiene un médico por ID
     */
    public function buscarPorId(int $id): ?array {
        return $this->medicoModelo->buscarPorId($id);
    }

    /**
     * Obtiene la lista de especialidades asociadas a un médico
     */
    public function obtenerEspecialidadesPorMedico(int $id_medico): array {
        return $this->medicoModelo->obtenerEspecialidadesPorMedico($id_medico);
    }

    /**
     * Obtiene médicos activos para listas desplegables
     */
    public function obtenerActivos(): array {
        return $this->medicoModelo->obtenerActivos();
    }
}
