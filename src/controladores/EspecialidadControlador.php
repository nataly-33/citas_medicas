<?php
namespace App\Controladores;

use Exception;
use App\Modelos\EspecialidadModelo;
use App\Nucleo\Ayudantes;

/**
 * Controlador de Gestión de Especialidades Médicas
 * Sistema Clínica Vida Salud - Sistemas 1 (UAGRM)
 */
class EspecialidadControlador {
    private EspecialidadModelo $especialidadModelo;

    public function __construct(?EspecialidadModelo $especialidadModelo = null) {
        $this->especialidadModelo = $especialidadModelo ?: new EspecialidadModelo();
    }

    /**
     * Lista todas las especialidades registradas en el sistema
     */
    public function listarEspecialidades(): array {
        return $this->especialidadModelo->obtenerTodas();
    }

    /**
     * Registra una nueva especialidad médica validando unicidad normalizada
     */
    public function registrarEspecialidad(array $datos): array {
        try {
            $nombre = trim($datos['nombre'] ?? '');
            $descripcion = trim($datos['descripcion'] ?? '');
            $duracion = !empty($datos['duracion_minutos']) ? (int)$datos['duracion_minutos'] : 30;

            if (empty($nombre)) {
                return ['exito' => false, 'campo' => 'nombre', 'mensaje' => 'El nombre de la especialidad es obligatorio.'];
            }
            if (mb_strlen($nombre) < 3) {
                return ['exito' => false, 'campo' => 'nombre', 'mensaje' => 'El nombre de la especialidad debe contener al menos 3 caracteres.'];
            }

            // Validación de nombre normalizado único
            if ($this->especialidadModelo->buscarPorNombreNormalizado($nombre)) {
                return [
                    'exito'   => false, 
                    'campo'   => 'nombre', 
                    'mensaje' => 'Ya existe una especialidad registrada con este nombre.'
                ];
            }

            $idGenerado = $this->especialidadModelo->insertar([
                'nombre'           => $nombre,
                'descripcion'      => $descripcion,
                'duracion_minutos' => $duracion,
                'activa'           => true,
            ]);

            return [
                'exito'   => true,
                'mensaje' => 'Especialidad médica registrada exitosamente.',
                'id'      => $idGenerado,
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('EspecialidadControlador::registrarEspecialidad', 'Error al registrar especialidad', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al guardar la especialidad médica.',
            ];
        }
    }

    /**
     * Modifica los datos de una especialidad existente
     */
    public function modificarEspecialidad(int $id, array $datos): array {
        try {
            $especialidad = $this->especialidadModelo->buscarPorId($id);
            if (!$especialidad) {
                return ['exito' => false, 'mensaje' => 'La especialidad no existe.'];
            }

            $nombre = trim($datos['nombre'] ?? '');
            $descripcion = trim($datos['descripcion'] ?? '');
            $duracion = !empty($datos['duracion_minutos']) ? (int)$datos['duracion_minutos'] : 30;

            if (empty($nombre)) {
                return ['exito' => false, 'campo' => 'nombre', 'mensaje' => 'El nombre de la especialidad es obligatorio.'];
            }
            if (mb_strlen($nombre) < 3) {
                return ['exito' => false, 'campo' => 'nombre', 'mensaje' => 'El nombre de la especialidad debe contener al menos 3 caracteres.'];
            }

            $duplicado = $this->especialidadModelo->buscarPorNombreNormalizado($nombre, $id);
            if ($duplicado) {
                return [
                    'exito'   => false, 
                    'campo'   => 'nombre', 
                    'mensaje' => 'Ya existe una especialidad registrada con este nombre.'
                ];
            }

            $this->especialidadModelo->actualizar($id, [
                'nombre'           => $nombre,
                'descripcion'      => $descripcion,
                'duracion_minutos' => $duracion,
            ]);

            return [
                'exito'   => true,
                'mensaje' => 'Especialidad modificada correctamente.',
            ];
        } catch (Exception $e) {
            Ayudantes::registrarError('EspecialidadControlador::modificarEspecialidad', 'Error al modificar especialidad', $e);
            return [
                'exito'   => false,
                'mensaje' => 'Error al actualizar la especialidad.',
            ];
        }
    }

    /**
     * Cambia el estado de activación de una especialidad médica
     */
    public function cambiarEstadoEspecialidad(int $id): bool {
        $especialidad = $this->especialidadModelo->buscarPorId($id);
        if (!$especialidad) {
            return false;
        }

        $nuevoEstado = empty($especialidad['activa']);
        return $this->especialidadModelo->cambiarEstado($id, $nuevoEstado);
    }

    /**
     * Obtiene únicamente las especialidades activas para formularios
     */
    public function obtenerActivas(): array {
        return $this->especialidadModelo->obtenerActivas();
    }
}
