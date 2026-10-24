<?php
namespace App\Nucleo;

/**
 * Guardia de Sesión y Control de Acceso por Roles (RBAC)
 * Soporta Administrador, Recepcionista, Médico y Paciente
 */
class GuardiaSesion {
    public static function iniciarSesionSiEsNecesario(): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    public static function verificarAutenticacion(string $rutaRedireccion = '/citas_medicas/vistas/autenticacion/login.php'): void {
        self::iniciarSesionSiEsNecesario();
        
        if (empty($_SESSION['autenticado']) || empty($_SESSION['id_usuario'])) {
            header("Location: " . $rutaRedireccion . "?error=acceso_denegado");
            exit;
        }

        // Control de inactividad: 8 horas (jornada laboral)
        if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso'] > 28800)) {
            self::destruirSesion();
            header("Location: " . $rutaRedireccion . "?error=sesion_expirada");
            exit;
        }

        $_SESSION['ultimo_acceso'] = time();
    }

    public static function verificarRol(array $rolesPermitidos): void {
        self::verificarAutenticacion();
        $rolActual = $_SESSION['nombre_rol'] ?? '';

        // El Administrador tiene acceso total por principio
        if ($rolActual === 'Administrador') {
            return;
        }

        if (!in_array($rolActual, $rolesPermitidos, true)) {
            header("Location: /citas_medicas/vistas/panel/index.php?error=permiso_insuficiente");
            exit;
        }
    }

    public static function esAdministrador(): bool {
        self::iniciarSesionSiEsNecesario();
        return ($_SESSION['nombre_rol'] ?? '') === 'Administrador';
    }

    public static function esRecepcionista(): bool {
        self::iniciarSesionSiEsNecesario();
        return ($_SESSION['nombre_rol'] ?? '') === 'Recepcionista';
    }

    public static function esMedico(): bool {
        self::iniciarSesionSiEsNecesario();
        return ($_SESSION['nombre_rol'] ?? '') === 'Medico';
    }

    public static function esPaciente(): bool {
        self::iniciarSesionSiEsNecesario();
        return ($_SESSION['nombre_rol'] ?? '') === 'Paciente';
    }

    public static function obtenerIdUsuario(): ?int {
        self::iniciarSesionSiEsNecesario();
        return isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null;
    }

    public static function obtenerIdPaciente(): ?int {
        self::iniciarSesionSiEsNecesario();
        return isset($_SESSION['id_paciente']) ? (int)$_SESSION['id_paciente'] : null;
    }

    public static function obtenerIdMedico(): ?int {
        self::iniciarSesionSiEsNecesario();
        return isset($_SESSION['id_medico']) ? (int)$_SESSION['id_medico'] : null;
    }

    public static function destruirSesion(): void {
        self::iniciarSesionSiEsNecesario();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
