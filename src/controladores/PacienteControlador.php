<?php
namespace App\Controladores;

use Exception;
use App\Modelos\PacienteModelo;
use App\Modelos\UsuarioModelo;
use App\Modelos\CitaModelo;
use App\Nucleo\Ayudantes;

/**
 * Controlador de Gestión de Pacientes y Ficha Médica
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class PacienteControlador {
    private PacienteModelo $pacienteModelo;
    private UsuarioModelo $usuarioModelo;
    private CitaModelo $citaModelo;

    public function __construct(
        ?PacienteModelo $pacienteModelo = null,
        ?UsuarioModelo $usuarioModelo = null,
        ?CitaModelo $citaModelo = null
    ) {
        $this->pacienteModelo = $pacienteModelo ?: new PacienteModelo();
        $this->usuarioModelo = $usuarioModelo ?: new UsuarioModelo();
        $this->citaModelo = $citaModelo ?: new CitaModelo();
    }

    /**
     * Lista los pacientes con paginación de 15 filas y filtro por CI/nombre
     */
    public function listarPacientes(int $pagina = 1, string $buscar = ''): array {
        $limite = defined('REGISTROS_POR_PAGINA') ? REGISTROS_POR_PAGINA : 15;
        $pagina = max(1, $pagina);
        $desplazamiento = ($pagina - 1) * $limite;

        $total = $this->pacienteModelo->contarTotal($buscar);
        $registros = $this->pacienteModelo->obtenerPaginados($limite, $desplazamiento, $buscar);
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
     * Registra un nuevo paciente con validaciones limpias y creación automática de usuario
     */
    public function registrarPaciente(array $datos): array {
        return $this->crear($datos);
    }

    /**
     * Crea un nuevo paciente y su usuario de acceso
     */
    public function crear(array $datos): array {
        try {
            $ci = trim($datos['ci'] ?? '');
            $nombres = trim($datos['nombres'] ?? '');
            $apellidos = trim($datos['apellidos'] ?? '');
            $fechaNacimiento = trim($datos['fecha_nacimiento'] ?? '');
            $sexo = strtoupper(trim($datos['sexo'] ?? ''));
            $telefono = trim($datos['telefono'] ?? '');
            $correo = trim($datos['correo'] ?? '');

            // 1. Validaciones de obligatoriedad y formato con mensajes limpios
            if (empty($ci)) {
                return ['exito' => false, 'campo' => 'ci', 'mensaje' => 'El documento de identidad (CI) es obligatorio.'];
            }
            if (mb_strlen($nombres) < 2 || mb_strlen($apellidos) < 2) {
                return ['exito' => false, 'campo' => 'nombres', 'mensaje' => 'Los nombres y apellidos deben contener al menos 2 letras.'];
            }
            if (empty($fechaNacimiento)) {
                return ['exito' => false, 'campo' => 'fecha_nacimiento', 'mensaje' => 'La fecha de nacimiento es obligatoria.'];
            }
            if (!in_array($sexo, ['M', 'F', 'O'], true)) {
                return ['exito' => false, 'campo' => 'sexo', 'mensaje' => 'Seleccione un sexo válido (M, F u O).'];
            }
            if (empty($telefono) || !preg_match('/^[0-9]{7,10}$/', $telefono)) {
                return ['exito' => false, 'campo' => 'telefono', 'mensaje' => 'El número de teléfono debe contener entre 7 y 10 dígitos numéricos.'];
            }

            // 2. Unicidad de Cédula de Identidad
            $pacienteExistente = $this->pacienteModelo->buscarPorCi($ci);
            if ($pacienteExistente) {
                return [
                    'exito'   => false,
                    'campo'   => 'ci',
                    'mensaje' => 'El documento de identidad (CI) ya se encuentra registrado en el sistema.',
                ];
            }

            // 3. Crear usuario de acceso con rol Paciente (id_rol = 4)
            if (!empty($correo)) {
                $correoUsuario = strtolower(trim($correo));
                if (!filter_var($correoUsuario, FILTER_VALIDATE_EMAIL)) {
                    return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico no tiene un formato válido.'];
                }
                if ($this->usuarioModelo->existeCorreo($correoUsuario)) {
                    return [
                        'exito'   => false,
                        'campo'   => 'correo',
                        'mensaje' => 'El correo electrónico ya se encuentra registrado con otro usuario en el sistema.',
                    ];
                }
            } else {
                $correoUsuario = "{$ci}@paciente.vidasalud.bo";
            }

            $contrasenaPlana = !empty($datos['contrasena']) ? trim($datos['contrasena']) : 'admin123';

            $idUsuario = null;
            // Verificar si ya existe usuario con ese nombre de usuario o correo
            $usuarioExistente = $this->usuarioModelo->buscarPorIdentificador($ci);
            if ($usuarioExistente) {
                $idUsuario = (int)$usuarioExistente['id'];
            } else {
                $idUsuario = $this->usuarioModelo->insertar([
                    'correo'     => $correoUsuario,
                    'contrasena' => $contrasenaPlana,
                    'id_rol'     => 4, // Rol Paciente
                    'nombres'    => $nombres,
                    'apellidos'  => $apellidos,
                    'activo'     => true,
                ]);
            }

            // 4. Generar código correlativo PAC-XXXX
            $codigo = $this->pacienteModelo->generarSiguienteCodigo();
            $datos['codigo'] = $codigo;
            $datos['id_usuario'] = $idUsuario;
            $datos['activo'] = true;

            $idGenerado = $this->pacienteModelo->insertar($datos);
            $nuevoPaciente = $this->pacienteModelo->buscarPorId($idGenerado);

            return [
                'exito'    => true,
                'mensaje'  => "Paciente registrado exitosamente con código {$codigo}. Su usuario de acceso es su CI con clave inicial {$contrasenaPlana}.",
                'paciente' => $nuevoPaciente,
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('PacienteControlador::crear', 'Error al registrar paciente', $e);
            return [
                'exito'   => false,
                'mensaje' => 'No se pudo completar el registro del paciente debido a un error interno.',
            ];
        }
    }

    /**
     * Modifica los datos personales, clínicos y de emergencia del paciente
     */
    public function modificarPaciente(int $id, array $datos): array {
        return $this->actualizar($id, $datos);
    }

    /**
     * Actualiza el paciente y sincroniza datos en su usuario vinculado
     */
    public function actualizar(int $id, array $datos): array {
        try {
            $paciente = $this->pacienteModelo->buscarPorId($id);
            if (!$paciente) {
                return ['exito' => false, 'mensaje' => 'El paciente solicitado no existe.'];
            }

            $ci = trim($datos['ci'] ?? '');
            $nombres = trim($datos['nombres'] ?? '');
            $apellidos = trim($datos['apellidos'] ?? '');
            $fechaNacimiento = trim($datos['fecha_nacimiento'] ?? '');
            $sexo = strtoupper(trim($datos['sexo'] ?? ''));
            $telefono = trim($datos['telefono'] ?? '');
            $correo = trim($datos['correo'] ?? '');

            if (empty($ci)) {
                return ['exito' => false, 'campo' => 'ci', 'mensaje' => 'El documento de identidad (CI) es obligatorio.'];
            }
            if (mb_strlen($nombres) < 2 || mb_strlen($apellidos) < 2) {
                return ['exito' => false, 'campo' => 'nombres', 'mensaje' => 'Los nombres y apellidos deben contener al menos 2 letras.'];
            }
            if (empty($fechaNacimiento)) {
                return ['exito' => false, 'campo' => 'fecha_nacimiento', 'mensaje' => 'La fecha de nacimiento es obligatoria.'];
            }
            if (empty($telefono) || !preg_match('/^[0-9]{7,10}$/', $telefono)) {
                return ['exito' => false, 'campo' => 'telefono', 'mensaje' => 'El número de teléfono debe contener entre 7 y 10 dígitos numéricos.'];
            }

            // Verificar CI duplicado excluyendo el registro actual
            $duplicado = $this->pacienteModelo->buscarPorCi($ci, $id);
            if ($duplicado) {
                return [
                    'exito'   => false,
                    'campo'   => 'ci',
                    'mensaje' => 'El documento de identidad (CI) ya está registrado con otro paciente.',
                ];
            }

            // Validar unicidad de correo si se proporciona
            if (!empty($correo)) {
                $correoLimpio = strtolower(trim($correo));
                if (!filter_var($correoLimpio, FILTER_VALIDATE_EMAIL)) {
                    return ['exito' => false, 'campo' => 'correo', 'mensaje' => 'El correo electrónico no tiene un formato válido.'];
                }
                $idUsuario = (int)($paciente['id_usuario'] ?? 0);
                if ($this->usuarioModelo->existeCorreo($correoLimpio, $idUsuario ?: null)) {
                    return [
                        'exito'   => false,
                        'campo'   => 'correo',
                        'mensaje' => 'El correo electrónico ya se encuentra registrado con otro usuario en el sistema.',
                    ];
                }
                $datos['correo'] = $correoLimpio;
            }

            $this->pacienteModelo->actualizar($id, $datos);

            // Sincronizar datos con la cuenta de usuario vinculada
            if (!empty($paciente['id_usuario'])) {
                $datosUsuario = [
                    'correo'    => !empty($correo) ? $correo : "{$ci}@paciente.vidasalud.bo",
                    'nombres'   => $nombres,
                    'apellidos' => $apellidos,
                    'id_rol'    => 4,
                ];
                if (!empty($datos['contrasena'])) {
                    $datosUsuario['contrasena'] = trim($datos['contrasena']);
                }
                $this->usuarioModelo->actualizar((int)$paciente['id_usuario'], $datosUsuario);
            }

            return [
                'exito'   => true,
                'mensaje' => 'Datos del paciente actualizados correctamente.',
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('PacienteControlador::actualizar', 'Error al modificar paciente', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al guardar los cambios del paciente.',
            ];
        }
    }

    /**
     * Obtiene la ficha clínica completa y resumen estadístico de atenciones
     */
    public function obtenerFichaCompleta(int $id): array {
        $ficha = $this->pacienteModelo->obtenerFichaCompleta($id);
        if (!$ficha) {
            return [
                'exito'   => false,
                'mensaje' => 'Ficha clínica no encontrada.',
                'ficha'   => null,
            ];
        }

        return [
            'exito' => true,
            'ficha' => $ficha,
        ];
    }

    /**
     * Obtiene los datos básicos de un paciente por su ID (alias buscarPorId)
     */
    public function obtenerPorId(int $id): ?array {
        return $this->pacienteModelo->buscarPorId($id);
    }

    public function buscarPorId(int $id): ?array {
        return $this->obtenerPorId($id);
    }

    public function obtenerHistorialCitas(int $id_paciente): array {
        $resultado = $this->obtenerHistorialPaciente($id_paciente);
        return $resultado['citas'] ?? [];
    }

    /**
     * Cambia el estado de activación del paciente (Baja Lógica)
     */
    public function cambiarEstadoPaciente(int $id): bool {
        $paciente = $this->pacienteModelo->buscarPorId($id);
        if (!$paciente) {
            return false;
        }

        $nuevoEstado = empty($paciente['activo']);
        return $this->pacienteModelo->cambiarEstado($id, $nuevoEstado);
    }

    /**
     * Obtiene la ficha y el historial completo de citas del paciente
     */
    public function obtenerHistorialPaciente(int $id_paciente): array {
        $paciente = $this->pacienteModelo->buscarPorId($id_paciente);
        if (!$paciente) {
            return [
                'exito'   => false,
                'mensaje' => 'Paciente no encontrado.',
                'citas'   => [],
            ];
        }

        $citas = $this->citaModelo->obtenerCitasPorPaciente($id_paciente);

        return [
            'exito'    => true,
            'paciente' => $paciente,
            'citas'    => $citas,
            'total'    => count($citas),
        ];
    }

    /**
     * Búsqueda ágil de pacientes para desplegable en el formulario de citas
     */
    public function buscarPaciente(string $termino): array {
        if (strlen(trim($termino)) < 2) {
            return [];
        }
        return $this->pacienteModelo->buscarParaSeleccion($termino);
    }
}
