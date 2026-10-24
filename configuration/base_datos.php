<?php
/**
 * Parámetros de Conexión a la Base de Datos PostgreSQL
 * Soporta configuración Localhost y Nube (Render, Railway, Docker)
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

if ($dbUrl = getenv('DATABASE_URL')) {
    // Si se despliega en Render u otro servicio cloud con DATABASE_URL
    $partes = parse_url($dbUrl);
    define('BD_HOST', $partes['host'] ?? 'localhost');
    define('BD_PUERTO', isset($partes['port']) ? (string)$partes['port'] : '5432');
    define('BD_NOMBRE', ltrim($partes['path'] ?? 'db_vidasalud', '/'));
    define('BD_USUARIO', $partes['user'] ?? 'postgres');
    define('BD_CLAVE', $partes['pass'] ?? 'postgres');
} else {
    // Configuración estándar local / manual por variables de entorno
    define('BD_HOST', getenv('BD_HOST') ?: 'localhost');
    define('BD_PUERTO', getenv('BD_PUERTO') ?: '5432');
    define('BD_NOMBRE', getenv('BD_NOMBRE') ?: 'db_vidasalud');
    define('BD_USUARIO', getenv('BD_USUARIO') ?: 'postgres');
    define('BD_CLAVE', getenv('BD_CLAVE') ?: (getenv('BD_PASSWORD') ?: 'postgres'));
}
