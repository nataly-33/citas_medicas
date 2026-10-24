<?php
namespace App\Controladores;

use InvalidArgumentException;
use Exception;
use App\Modelos\CitaModelo;
use App\Servicios\CitaValidacionServicio;
use App\Nucleo\GuardiaSesion;
use App\Nucleo\Ayudantes;

/**
 * Controlador de Gestión de Citas Médicas
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class CitaControlador {
    private CitaModelo $citaModelo;
    private CitaValidacionServicio $validacionServicio;

    public function __construct(
        ?CitaModelo $citaModelo = null,
        ?CitaValidacionServicio $validacionServicio = null
    ) {
        $this->citaModelo = $citaModelo ?: new CitaModelo();
        $this->validacionServicio = $validacionServicio ?: new CitaValidacionServicio();
    }

    /**
     * Lista citas médicas con paginación de 15 filas, filtros de rango de fecha, médico, estado y búsqueda
     */
    public function listarCitas(
        int $pagina = 1, 
        string $fecha_desde = '', 
        string $fecha_hasta = '', 
        int $id_medico = 0, 
        string $estado = '',
        int $id_paciente = 0,
        string $buscar = ''
    ): array {
        GuardiaSesion::iniciarSesionSiEsNecesario();

        // Si es rol Paciente, restringir automáticamente a sus propias citas
        if (GuardiaSesion::esPaciente() && !empty($_SESSION['id_paciente'])) {
            $id_paciente = (int)$_SESSION['id_paciente'];
        }

        $limite = defined('REGISTROS_POR_PAGINA') ? REGISTROS_POR_PAGINA : 15;
        $pagina = max(1, $pagina);
        $desplazamiento = ($pagina - 1) * $limite;

        $total = $this->citaModelo->contarTotal($fecha_desde, $fecha_hasta, $id_medico, $estado, $id_paciente, $buscar);
        $registros = $this->citaModelo->obtenerPaginadas($limite, $desplazamiento, $fecha_desde, $fecha_hasta, $id_medico, $estado, $id_paciente, $buscar);
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
     * Agenda una nueva cita médica ejecutando validación estricta y transacción atómica
     */
    public function agendarCita(array $datos): array {
        try {
            GuardiaSesion::iniciarSesionSiEsNecesario();

            // Si es Paciente autenticado, forzar su propio id_paciente
            if (GuardiaSesion::esPaciente() && !empty($_SESSION['id_paciente'])) {
                $datos['id_paciente'] = (int)$_SESSION['id_paciente'];
            }

            if (empty($datos['id_usuario_registro']) && !empty($_SESSION['id_usuario'])) {
                $datos['id_usuario_registro'] = (int)$_SESSION['id_usuario'];
            }

            if (empty($datos['id_usuario_registro'])) {
                $datos['id_usuario_registro'] = 1;
            }

            // Normalización de horas (HH:MM)
            if (!empty($datos['hora_inicio']) && strlen(trim($datos['hora_inicio'])) === 5) {
                $datos['hora_inicio'] = trim($datos['hora_inicio']) . ':00';
            }
            if (!empty($datos['hora_fin']) && strlen(trim($datos['hora_fin'])) === 5) {
                $datos['hora_fin'] = trim($datos['hora_fin']) . ':00';
            }

            // Validación estricta de Reglas de Negocio
            $this->validacionServicio->validarReglasAgendamiento($datos);

            // Inserción transaccional atómica
            $resultado = $this->citaModelo->insertarTransaccional($datos);

            return [
                'exito'       => true,
                'mensaje'     => "Cita médica reservada exitosamente con código {$resultado['codigo_cita']}.",
                'id_cita'     => $resultado['id'],
                'codigo_cita' => $resultado['codigo_cita'],
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'exito'   => false,
                'mensaje' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('CitaControlador::agendarCita', 'Fallo al agendar cita', $e);
            return [
                'exito'   => false,
                'mensaje' => 'No se pudo agendar la cita. Verifique la disponibilidad del turno e intente nuevamente.',
            ];
        }
    }

    /**
     * Reprograma una cita existente a una nueva fecha y horario
     */
    public function reprogramarCita(int $id_cita, string $nueva_fecha, string $nueva_hora_inicio, string $nueva_hora_fin): array {
        try {
            $nueva_fecha = trim($nueva_fecha);
            $nueva_hora_inicio = trim($nueva_hora_inicio);
            $nueva_hora_fin = trim($nueva_hora_fin);

            if (strlen($nueva_hora_inicio) === 5) {
                $nueva_hora_inicio .= ':00';
            }
            if (strlen($nueva_hora_fin) === 5) {
                $nueva_hora_fin .= ':00';
            }

            // Validar reglas de reprogramación
            $this->validacionServicio->validarReglasReprogramacion($id_cita, $nueva_fecha, $nueva_hora_inicio, $nueva_hora_fin);

            // Actualización transaccional
            $this->citaModelo->actualizarHorario($id_cita, $nueva_fecha, $nueva_hora_inicio, $nueva_hora_fin);

            return [
                'exito'   => true,
                'mensaje' => 'La cita ha sido reprogramada exitosamente.',
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'exito'   => false,
                'mensaje' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('CitaControlador::reprogramarCita', 'Fallo al reprogramar cita', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Ocurrió un error al reprogramar la cita médica.',
            ];
        }
    }

    /**
     * Cancela formalmente una cita médica con registro obligatorio de motivo
     */
    public function cancelarCita(int $id_cita, string $motivo_cancelacion): array {
        try {
            $motivo = trim($motivo_cancelacion);
            if (empty($motivo)) {
                return [
                    'exito'   => false,
                    'mensaje' => 'Debe ingresar obligatoriamente el motivo de cancelación de la cita.',
                ];
            }

            $cita = $this->citaModelo->buscarPorId($id_cita);
            if (!$cita) {
                return ['exito' => false, 'mensaje' => 'La cita seleccionada no existe.'];
            }

            if ($cita['estado'] === 'Atendida') {
                return [
                    'exito'   => false,
                    'mensaje' => 'Una cita médica atendida no puede ser cancelada porque forma parte del historial clínico.',
                ];
            }

            if ($cita['estado'] === 'Cancelada') {
                return [
                    'exito'   => false,
                    'mensaje' => 'La cita médica ya se encuentra en estado cancelado.',
                ];
            }

            $this->citaModelo->actualizarEstado($id_cita, 'Cancelada', $motivo);

            return [
                'exito'   => true,
                'mensaje' => 'Cita cancelada correctamente. El cupo horario ha sido liberado para nuevos pacientes.',
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('CitaControlador::cancelarCita', 'Fallo al cancelar cita', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al cancelar la cita médica.',
            ];
        }
    }

    /**
     * Registra la cita como atendida junto a las observaciones clínicas del médico
     */
    public function marcarAtendida(int $id_cita, string $observaciones = ''): array {
        return $this->atender($id_cita, $observaciones);
    }

    /**
     * Atiende formalmente una cita médica
     */
    public function atender(int $id_cita, string $observaciones = ''): array {
        try {
            $cita = $this->citaModelo->buscarPorId($id_cita);
            if (!$cita) {
                return ['exito' => false, 'mensaje' => 'La cita médica no existe.'];
            }

            if ($cita['estado'] === 'Cancelada') {
                return ['exito' => false, 'mensaje' => 'No se puede marcar como atendida una cita cancelada.'];
            }
            if ($cita['estado'] === 'Atendida') {
                return ['exito' => false, 'mensaje' => 'La cita ya fue registrada como atendida anteriormente.'];
            }

            $this->citaModelo->actualizarEstado($id_cita, 'Atendida', null, trim($observaciones));

            return [
                'exito'   => true,
                'mensaje' => 'Atención médica registrada exitosamente en el historial del paciente.',
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('CitaControlador::atender', 'Error al marcar cita atendida', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al registrar la atención médica.',
            ];
        }
    }

    /**
     * Registra la inasistencia del paciente a la cita
     */
    public function marcarInasistencia(int $id_cita): array {
        try {
            $cita = $this->citaModelo->buscarPorId($id_cita);
            if (!$cita) {
                return ['exito' => false, 'mensaje' => 'La cita no existe.'];
            }

            if ($cita['estado'] === 'Atendida') {
                return ['exito' => false, 'mensaje' => 'No se puede marcar inasistencia en una cita ya atendida.'];
            }
            if ($cita['estado'] === 'Cancelada') {
                return ['exito' => false, 'mensaje' => 'La cita ya está cancelada.'];
            }

            $this->citaModelo->actualizarEstado($id_cita, 'Inasistencia');

            return [
                'exito'   => true,
                'mensaje' => 'Se registró la inasistencia del paciente a la cita médica.',
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('CitaControlador::marcarInasistencia', 'Error al marcar inasistencia', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al registrar inasistencia.',
            ];
        }
    }

    /**
     * Obtiene el detalle completo de una cita médica para el modal de visualización
     */
    public function obtenerDetalle(int $id_cita): array {
        $detalle = $this->citaModelo->obtenerDetalleCompleto($id_cita);
        if (!$detalle) {
            return [
                'exito'   => false,
                'mensaje' => 'No se encontró la cita médica solicitada.',
                'detalle' => null,
            ];
        }

        return [
            'exito'   => true,
            'detalle' => $detalle,
        ];
    }

    /**
     * Devuelve las citas de una fecha específica para la vista diaria o Kanban
     */
    public function citasPorFecha(string $fecha, int $id_medico = 0): array {
        if (empty($fecha)) {
            $fecha = date('Y-m-d');
        }

        $citas = $this->citaModelo->obtenerPorFecha($fecha, $id_medico);

        return [
            'exito' => true,
            'fecha' => $fecha,
            'citas' => $citas,
            'total' => count($citas),
        ];
    }

    /**
     * Obtiene la agenda cronológica del día para un médico específico
     */
    public function obtenerAgendaDelDia(int $id_medico, string $fecha): array {
        if ($id_medico <= 0 || empty($fecha)) {
            return [];
        }
        return $this->citaModelo->obtenerCitasPorMedicoYFecha($id_medico, $fecha);
    }

    /**
     * Busca los detalles completos de una cita por su ID
     */
    public function buscarPorId(int $id): ?array {
        return $this->citaModelo->buscarPorId($id);
    }
}
