<?php
namespace App\Controladores;

use App\Modelos\ReporteModelo;
use App\Nucleo\Ayudantes;

/**
 * Controlador de Reportes Administrativos, Estadísticas y Exportación
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class ReporteControlador {
    private ReporteModelo $reporteModelo;

    public function __construct(?ReporteModelo $reporteModelo = null) {
        $this->reporteModelo = $reporteModelo ?: new ReporteModelo();
    }

    /**
     * Obtiene los totales consolidados por estado en el período seleccionado
     */
    public function obtenerResumenGeneral(string $fecha_inicio, string $fecha_fin): array {
        $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : date('Y-m-01');
        $fecha_fin = !empty($fecha_fin) ? $fecha_fin : date('Y-m-t');

        return $this->reporteModelo->contarTotalesPorEstado($fecha_inicio, $fecha_fin);
    }

    /**
     * Obtiene los totales globales e históricos de todo el sistema
     */
    public function obtenerTotalesGenerales(): array {
        return $this->reporteModelo->obtenerTotalesGenerales();
    }

    /**
     * Obtiene el desglose de citas atendidas y canceladas por cada médico
     */
    public function obtenerCitasPorMedico(string $fecha_inicio, string $fecha_fin): array {
        $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : date('Y-m-01');
        $fecha_fin = !empty($fecha_fin) ? $fecha_fin : date('Y-m-t');

        return $this->reporteModelo->obtenerCitasPorMedico($fecha_inicio, $fecha_fin);
    }

    /**
     * Obtiene el desglose de volumen de citas por especialidad médica
     */
    public function obtenerCitasPorEspecialidad(string $fecha_inicio, string $fecha_fin): array {
        $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : date('Y-m-01');
        $fecha_fin = !empty($fecha_fin) ? $fecha_fin : date('Y-m-t');

        return $this->reporteModelo->obtenerCitasPorEspecialidad($fecha_inicio, $fecha_fin);
    }

    /**
     * Obtiene el detalle de citas filtradas para visualización tabular
     */
    public function obtenerDetalleReporte(string $fecha_inicio, string $fecha_fin, int $id_medico = 0, int $id_especialidad = 0, string $estado = ''): array {
        $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : date('Y-m-01');
        $fecha_fin = !empty($fecha_fin) ? $fecha_fin : date('Y-m-t');

        return $this->reporteModelo->obtenerDetalleReporte($fecha_inicio, $fecha_fin, $id_medico, $id_especialidad, $estado);
    }

    /**
     * Exporta el reporte de citas a formato CSV descargable con BOM UTF-8 para Excel
     */
    public function exportarCsv(string $fecha_inicio, string $fecha_fin, int $id_medico = 0, int $id_especialidad = 0, string $estado = ''): void {
        $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : date('Y-m-01');
        $fecha_fin = !empty($fecha_fin) ? $fecha_fin : date('Y-m-t');

        $citas = $this->reporteModelo->obtenerDetalleReporte($fecha_inicio, $fecha_fin, $id_medico, $id_especialidad, $estado);

        $nombreArchivo = 'reporte_citas_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $salida = fopen('php://output', 'w');

        // BOM UTF-8 para apertura correcta en Microsoft Excel (Bolivia / español)
        fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($salida, [
            'Código Cita',
            'Fecha',
            'Hora Inicio',
            'Hora Fin',
            'Estado',
            'CI Paciente',
            'Paciente',
            'Teléfono Paciente',
            'Tipo de Sangre',
            'Médico',
            'Matrícula Médica',
            'Especialidad',
            'Motivo Consulta',
            'Motivo Cancelación',
            'Notas de Atención',
            'Operador Registro',
        ], ';');

        foreach ($citas as $cita) {
            fputcsv($salida, [
                $cita['codigo_cita'],
                $cita['fecha'],
                substr($cita['hora_inicio'], 0, 5),
                substr($cita['hora_fin'], 0, 5),
                $cita['estado'],
                $cita['ci_paciente'],
                $cita['nombre_paciente'],
                $cita['telefono_paciente'],
                $cita['tipo_sangre'] ?? '',
                $cita['nombre_medico'],
                $cita['matricula_medico'],
                $cita['nombre_especialidad'],
                $cita['motivo_consulta'] ?? '',
                $cita['motivo_cancelacion'] ?? '',
                $cita['observaciones_atencion'] ?? '',
                $cita['registrado_por'],
            ], ';');
        }

        fclose($salida);
        exit;
    }

    /**
     * Obtiene el padrón de pacientes registrados con sus métricas y filtros
     */
    public function obtenerReportePacientes(string $buscar = '', string $sexo = '', string $estado = '', string $tipoSangre = ''): array {
        return $this->reporteModelo->obtenerReportePacientes($buscar, $sexo, $estado, $tipoSangre);
    }

    /**
     * Exporta el padrón de pacientes registrados a formato CSV
     */
    public function exportarCsvPacientes(string $buscar = '', string $sexo = '', string $estado = '', string $tipoSangre = ''): void {
        $pacientes = $this->reporteModelo->obtenerReportePacientes($buscar, $sexo, $estado, $tipoSangre);

        $nombreArchivo = 'padron_pacientes_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $salida = fopen('php://output', 'w');

        // BOM UTF-8
        fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($salida, [
            'Código',
            'Cédula de Identidad (CI)',
            'Nombres',
            'Apellidos',
            'Sexo',
            'Fecha Nacimiento',
            'Teléfono',
            'Correo',
            'Dirección',
            'Tipo de Sangre',
            'Fecha de Registro',
            'Total Citas',
            'Citas Atendidas',
            'Citas Canceladas',
            'Inasistencias',
            'Estado',
        ], ';');

        foreach ($pacientes as $pac) {
            fputcsv($salida, [
                $pac['codigo'],
                $pac['ci'],
                $pac['nombres'],
                $pac['apellidos'],
                $pac['sexo'],
                $pac['fecha_nacimiento'],
                $pac['telefono'],
                $pac['correo'] ?? '',
                $pac['direccion'] ?? '',
                $pac['tipo_sangre'] ?? '',
                substr($pac['fecha_creacion'], 0, 10),
                $pac['total_citas'],
                $pac['citas_atendidas'],
                $pac['citas_canceladas'],
                $pac['inasistencias'],
                !empty($pac['activo']) ? 'Activo' : 'Inactivo',
            ], ';');
        }

        fclose($salida);
        exit;
    }
}
