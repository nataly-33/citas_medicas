<?php
namespace App\Nucleo;

use PDO;
use PDOException;

require_once dirname(__DIR__, 2) . '/configuration/base_datos.php';

/**
 * Conexión Singleton con PDO pdo_pgsql para PostgreSQL Localhost
 * Cero inyección SQL con consultas preparadas obligatorias.
 */
class Conexion {
    private static ?PDO $instancia = null;

    private function __construct() {}

    public static function obtenerConexion(): PDO {
        if (self::$instancia === null) {
            $dsn = "pgsql:host=" . BD_HOST . ";port=" . BD_PUERTO . ";dbname=" . BD_NOMBRE;
            try {
                self::$instancia = new PDO($dsn, BD_USUARIO, BD_CLAVE, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Registrar en log local sin exponer credenciales
                $archivoLog = dirname(__DIR__, 2) . '/almacenamiento/logs/errores.log';
                $mensaje = "[" . date('Y-m-d H:i:s') . "] Error de conexión a PostgreSQL: " . $e->getMessage() . PHP_EOL;
                @file_put_contents($archivoLog, $mensaje, FILE_APPEND);
                throw new PDOException("No se pudo conectar a la base de datos de la clínica. Verifique que PostgreSQL esté iniciado.");
            }
        }
        return self::$instancia;
    }
}
