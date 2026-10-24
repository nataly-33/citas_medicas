<?php
namespace App\Controladores;

use App\Modelos\UsuarioModelo;
use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;

/**
 * Controlador de Autenticación y Control de Sesiones
 * Permite autenticación con usuario, correo o CI
 */
class AutenticacionControlador {
    private UsuarioModelo $usuarioModelo;

    public function __construct(?UsuarioModelo $usuarioModelo = null) {
        $this->usuarioModelo = $usuarioModelo ?: new UsuarioModelo();
    }

    /**
     * Valida credenciales e inicializa la sesión segura del usuario
     *
     * @param string $identificador Nombre de usuario, correo electrónico o CI
     * @param string $contrasena Contraseña en texto plano
     * @return array Resultado de la autenticación
     */
    public function iniciarSesion(string $identificador, string $contrasena): array {
        $identificador = trim($identificador);
        $contrasena = trim($contrasena);

        if (empty($identificador) || empty($contrasena)) {
            return [
                'exito'   => false,
                'mensaje' => 'Por favor ingrese su usuario, correo o CI y contraseña.',
            ];
        }

        $usuario = $this->usuarioModelo->buscarPorIdentificador($identificador);
        if (!$usuario) {
            return [
                'exito'   => false,
                'mensaje' => 'Credenciales incorrectas o usuario no registrado.',
            ];
        }

        if (empty($usuario['activo'])) {
            return [
                'exito'   => false,
                'mensaje' => 'Su cuenta se encuentra inactiva. Por favor comuníquese con el personal de la clínica.',
            ];
        }

        if (!password_verify($contrasena, $usuario['contrasena_hash'])) {
            return [
                'exito'   => false,
                'mensaje' => 'Contraseña incorrecta. Verifique sus datos.',
            ];
        }

        // Iniciar sesión y regenerar identificador para evitar fijación de sesión
        GuardiaSesion::iniciarSesionSiEsNecesario();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $nombreCompleto = trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
        if (empty($nombreCompleto)) {
            $nombreCompleto = $usuario['correo'];
        }

        $_SESSION['id_usuario']      = (int)$usuario['id'];
        $_SESSION['correo']          = $usuario['correo'];
        $_SESSION['id_rol']          = (int)$usuario['id_rol'];
        $_SESSION['nombre_rol']      = $usuario['nombre_rol'];
        $_SESSION['nombres']         = $usuario['nombres'] ?? '';
        $_SESSION['apellidos']       = $usuario['apellidos'] ?? '';
        $_SESSION['nombre_completo'] = $nombreCompleto;
        $_SESSION['id_paciente']     = !empty($usuario['id_paciente']) ? (int)$usuario['id_paciente'] : null;
        $_SESSION['ci_paciente']     = $usuario['ci_paciente'] ?? null;
        $_SESSION['id_medico']       = !empty($usuario['id_medico']) ? (int)$usuario['id_medico'] : null;
        $_SESSION['autenticado']     = true;
        $_SESSION['ultimo_acceso']   = time();

        // Actualizar último acceso en la base de datos
        $this->usuarioModelo->actualizarUltimoAcceso((int)$usuario['id']);

        // Todos los roles (Administrador, Recepcionista, Médico y Paciente) acceden a su Panel de Control personalizado
        $redireccion = '/citas_medicas/vistas/panel/index.php';

        return [
            'exito'       => true,
            'mensaje'     => 'Inicio de sesión exitoso.',
            'redireccion' => $redireccion,
            'usuario'     => [
                'id'              => $usuario['id'],
                'correo'          => $usuario['correo'],
                'nombre_completo' => $nombreCompleto,
                'nombre_rol'      => $usuario['nombre_rol'],
                'id_paciente'     => $_SESSION['id_paciente'],
                'id_medico'       => $_SESSION['id_medico'],
            ]
        ];
    }

    /**
     * Cierra la sesión activa de forma segura
     */
    public function cerrarSesion(): void {
        GuardiaSesion::destruirSesion();
    }

    /**
     * Obtiene los datos del usuario actualmente autenticado en sesión
     */
    public function obtenerUsuarioActual(): ?array {
        GuardiaSesion::iniciarSesionSiEsNecesario();
        if (empty($_SESSION['autenticado']) || empty($_SESSION['id_usuario'])) {
            return null;
        }

        return [
            'id_usuario'      => $_SESSION['id_usuario'],
            'correo'          => $_SESSION['correo'] ?? '',
            'nombre_completo' => $_SESSION['nombre_completo'] ?? $_SESSION['correo'],
            'id_rol'          => $_SESSION['id_rol'],
            'nombre_rol'      => $_SESSION['nombre_rol'],
            'id_paciente'     => $_SESSION['id_paciente'] ?? null,
            'id_medico'       => $_SESSION['id_medico'] ?? null,
        ];
    }
}
