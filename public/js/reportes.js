/**
 * JavaScript Módulo Reportes Administrativos - Clínica Vida Salud
 * Sistemas de Información 1 - UAGRM
 * Caso de Uso: CU13 (Consultar Agenda y Reportes Administrativos)
 */

document.addEventListener('DOMContentLoaded', function () {
    const btnExportar = document.getElementById('btnExportarCsv');
    if (btnExportar) {
        btnExportar.addEventListener('click', exportarReporteCsv);
    }
});

/**
 * Limpia los campos de filtro del reporte y recarga
 */
function limpiarFiltrosReporte() {
    const url = new URL(window.location.href);
    url.search = '';
    window.location.href = url.pathname;
}

/**
 * Descarga el reporte de citas filtrado en formato CSV
 */
function exportarReporteCsv() {
    const fechaInicio = document.getElementById('filtro_fecha_inicio')?.value || '';
    const fechaFin = document.getElementById('filtro_fecha_fin')?.value || '';
    const idMedico = document.getElementById('filtro_id_medico')?.value || '';
    const idEspecialidad = document.getElementById('filtro_id_especialidad')?.value || '';
    const estado = document.getElementById('filtro_estado')?.value || '';

    const params = new URLSearchParams({
        accion: 'exportar_csv',
        fecha_inicio: fechaInicio,
        fecha_fin: fechaFin,
        id_medico: idMedico,
        id_especialidad: idEspecialidad,
        estado: estado
    });

    window.location.href = window.location.pathname + '?' + params.toString();
}
