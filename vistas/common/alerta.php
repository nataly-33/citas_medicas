<?php
/**
 * Componente Compartido de Notificaciones y Banners de Alerta
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

$mensajeClave = $_GET['mensaje'] ?? null;
$errorClave = $_GET['error'] ?? null;

$mensajesExito = [
    'guardado_exitoso'     => 'Registro procesado y guardado correctamente.',
    'paciente_guardado'    => 'Datos del paciente actualizados con éxito.',
    'medico_guardado'      => 'Médico registrado correctamente en el sistema.',
    'cita_creada'          => 'Cita médica agendada exitosamente en estado Programada.',
    'cita_reprogramada'    => 'Cita reprogramada correctamente en nuevo horario.',
    'cita_cancelada'       => 'Cita cancelada con éxito. El cupo horario ha sido liberado.',
    'cita_atendida'        => 'Atención médica y notas clínicas registradas exitosamente.',
    'asistencia_confirmada'=> 'Asistencia presencial del paciente confirmada.',
    'inasistencia_registrada' => 'Inasistencia del paciente registrada.',
    'estado_actualizado'   => 'El estado del registro fue modificado satisfactoriamente.',
    'sesion_cerrada'       => 'La sesión se cerró de manera segura.'
];

$mensajesError = [
    'acceso_denegado'      => 'Acceso denegado: debe iniciar sesión para acceder a las funciones del sistema.',
    'sesion_expirada'      => 'Su sesión ha finalizado por límite de inactividad.',
    'permiso_insuficiente' => 'Su rol no tiene autorización para realizar la acción solicitada.',
    'operacion_fallida'    => 'No se pudo completar la operación. Verifique los datos ingresados.'
];
?>

<?php if ($mensajeClave && isset($mensajesExito[$mensajeClave])): ?>
    <div class="alerta alerta-exito alerta-autodesvanecer">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= htmlspecialchars($mensajesExito[$mensajeClave]) ?></span>
    </div>
<?php endif; ?>

<?php if ($errorClave && isset($mensajesError[$errorClave])): ?>
    <div class="alerta alerta-peligro">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span><?= htmlspecialchars($mensajesError[$errorClave]) ?></span>
    </div>
<?php endif; ?>
