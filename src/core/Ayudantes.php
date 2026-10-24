<?php
namespace App\Nucleo;

/**
 * Funciones de Utilidad y Sanitización
 */
class Ayudantes {
    public static function responderJson(array $datos, int $codigoHttp = 200): void {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function limpiarTexto(?string $texto): string {
        return htmlspecialchars(trim($texto ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public static function formatearFechaBolivia(string $fecha): string {
        $partes = explode('-', $fecha);
        if (count($partes) === 3) {
            return "{$partes[2]}/{$partes[1]}/{$partes[0]}";
        }
        return $fecha;
    }

    public static function formatearFechaHoraBolivia(string $fechaHora): string {
        $timestamp = strtotime($fechaHora);
        if ($timestamp !== false) {
            return date('d/m/Y H:i', $timestamp);
        }
        return $fechaHora;
    }

    public static function obtenerDiaSemana(string $fecha): int {
        // En PHP: date('N') devuelve 1 (Lunes) a 7 (Domingo)
        return (int)date('N', strtotime($fecha));
    }

    public static function registrarError(string $origen, string $mensaje, ?\Throwable $excepcion = null): void {
        $archivoLog = dirname(__DIR__, 2) . '/almacenamiento/logs/errores.log';
        $detalleExcepcion = $excepcion ? " | Detalle: " . $excepcion->getMessage() . " en " . $excepcion->getFile() . ":" . $excepcion->getLine() : "";
        $linea = "[" . date('Y-m-d H:i:s') . "] [{$origen}] {$mensaje}{$detalleExcepcion}" . PHP_EOL;
        @file_put_contents($archivoLog, $linea, FILE_APPEND);
    }
}
