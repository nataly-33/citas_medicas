BEGIN;

-- 1. ROLES
INSERT INTO roles (id, nombre, descripcion, activo) VALUES
(1, 'Administrador', 'Control total de configuración, usuarios, médicos y reportes gerenciales', TRUE),
(2, 'Recepcionista', 'Gestión de pacientes, consulta de agenda, reserva y reprogramación de citas', TRUE),
(3, 'Medico', 'Consulta de citas asignadas del día y registro de atención clínica', TRUE),
(4, 'Paciente', 'Consulta personal de citas médicas e historial personal', TRUE)
ON CONFLICT (id) DO UPDATE SET nombre = EXCLUDED.nombre, descripcion = EXCLUDED.descripcion;

-- 2. PERMISOS
INSERT INTO permisos (id, codigo_permiso, descripcion) VALUES
(1, 'usuarios.gestionar', 'Crear, modificar y desactivar operadores del sistema'),
(2, 'pacientes.gestionar', 'Registro, modificación y baja lógica de pacientes'),
(3, 'medicos.gestionar', 'Mantenimiento del plantel médico y especialidades'),
(4, 'horarios.configurar', 'Definición de jornadas semanales de atención'),
(5, 'citas.programar', 'Reserva de nuevas citas médicas'),
(6, 'citas.reprogramar', 'Modificación de fecha u hora de citas activas'),
(7, 'citas.cancelar', 'Cancelación justificada de citas con motivo obligatorio'),
(8, 'citas.atender', 'Marcar cita como atendida y registrar notas médicas'),
(9, 'reportes.consultar', 'Visualización, filtrado y exportación de reportes gerenciales')
ON CONFLICT (id) DO UPDATE SET codigo_permiso = EXCLUDED.codigo_permiso, descripcion = EXCLUDED.descripcion;

-- Asignación de Permisos a Roles
DELETE FROM rol_permiso WHERE id_rol IN (1, 2, 3, 4);
-- Administrador: Todos los permisos (1 al 9)
INSERT INTO rol_permiso (id_rol, id_permiso) VALUES 
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9);
-- Recepcionista: Pacientes, Médicos (consulta/horarios), Citas y Reportes
INSERT INTO rol_permiso (id_rol, id_permiso) VALUES 
(2,2),(2,3),(2,5),(2,6),(2,7),(2,9);
-- Médico: Atender citas y consultar reportes/agenda
INSERT INTO rol_permiso (id_rol, id_permiso) VALUES 
(3,8),(3,9);
-- Paciente: Consulta de citas
INSERT INTO rol_permiso (id_rol, id_permiso) VALUES 
(4,5);

-- 3. USUARIOS INICIALES (Contraseña de prueba para todos: admin123)
-- Hash Bcrypt para "admin123":
-- $2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6
INSERT INTO usuarios (id, correo, contrasena_hash, id_rol, nombres, apellidos, activo) VALUES
(1, 'admin@vidasalud.bo', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 1, 'Administrador', 'Principal', TRUE),
(2, 'recepcion@vidasalud.bo', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 2, 'Recepción', 'Turno Mañana', TRUE),
(3, 'claudia.recepcion@vidasalud.bo', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 2, 'Claudia', 'Vargas Recepción', TRUE),
(4, 'carlos.justiniano@vidasalud.bo', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 3, 'Carlos', 'Justiniano Vaca', TRUE),
(5, 'maria.pinto@vidasalud.bo', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 3, 'María Elena', 'Pinto Flores', TRUE),
(6, 'fernando.aguilera@vidasalud.bo', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 3, 'Fernando', 'Aguilera Cuéllar', TRUE),
(7, 'patricia.vaca@vidasalud.bo', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 3, 'Patricia', 'Vaca Suárez', TRUE),

-- Cuentas de Acceso para Pacientes (id_rol = 4, credencial = correo o CI, contraseña = admin123)
(8, 'juan.mercado@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Juan Pablo', 'Mercado Ríos', TRUE),
(9, 'claudia.vargas@hotmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Claudia', 'Vargas Suárez', TRUE),
(10, 'padre.mateo@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Mateo', 'Justiniano Rojas', TRUE),
(11, 'ramiro.suarez@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Ramiro', 'Suárez Saucedo', TRUE),
(12, 'mariana.cespedes@yahoo.es', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Mariana', 'Céspedes Ortiz', TRUE),
(13, 'jorge.banegas@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Jorge Luis', 'Banegas Peña', TRUE),
(14, 'valeria.antelo@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Valeria', 'Antelo Melgar', TRUE),
(15, 'carlos.roca@hotmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Carlos Eduardo', 'Roca Paz', TRUE),
(16, 'tutor.luciana@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Luciana', 'Morales Gutiérrez', TRUE),
(17, 'rodrigo.chavez@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Rodrigo', 'Chávez Hurtado', TRUE),
(18, 'andrea.siles@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Andrea', 'Siles Montaño', TRUE),
(19, 'madre.sebas@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Sebastián', 'Ribera Justiniano', TRUE),
(20, 'gaby.cuellar@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Gabriela', 'Cuéllar Vaca', TRUE),
(21, 'mauricio.taborga@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Mauricio', 'Taborga Salvatierra', TRUE),
(22, 'camila.dorado@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Camila', 'Dorado Soliz', TRUE),
(23, 'david.pinto@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'David', 'Pinto Aguilera', TRUE),
(24, 'sofia.barba@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Sofía', 'Barba Moreno', TRUE),
(25, 'alejandro.arteaga@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Alejandro', 'Arteaga Bravo', TRUE),
(26, 'nataly.torrico@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Nataly', 'Torrico Menacho', TRUE),
(27, 'fabricio.v@gmail.com', '$2y$12$4iDuYM6z9PUQ2hunlpRFdu/G87FnWLgzyKbAQcTTfpbU0Q5Al9Jj6', 4, 'Fabricio', 'Villarroel Zeballos', TRUE)
ON CONFLICT (id) DO UPDATE SET 
    correo = EXCLUDED.correo,
    contrasena_hash = EXCLUDED.contrasena_hash,
    nombres = EXCLUDED.nombres,
    apellidos = EXCLUDED.apellidos;

-- 4. ESPECIALIDADES MÉDICAS (7 Especialidades)
INSERT INTO especialidades (id, nombre, descripcion, duracion_minutos, activa) VALUES
(1, 'Medicina General', 'Atención integral primaria, diagnósticos iniciales y chequeos preventivos', 30, TRUE),
(2, 'Pediatría', 'Atención médica integral de recién nacidos, niños y adolescentes', 30, TRUE),
(3, 'Cardiología', 'Prevención, diagnóstico y tratamiento de afecciones cardíacas y circulatorias', 30, TRUE),
(4, 'Ginecología', 'Cuidado de la salud sexual, reproductiva y ginecológica femenina', 30, TRUE),
(5, 'Odontología', 'Salud bucal, limpieza, restauraciones, extracciones y prevención', 30, TRUE),
(6, 'Traumatología', 'Diagnóstico y tratamiento de lesiones óseas, articulares y musculares', 30, TRUE),
(7, 'Dermatología', 'Cuidado, prevención y tratamiento de enfermedades de la piel y mucosas', 30, TRUE)
ON CONFLICT (id) DO UPDATE SET nombre = EXCLUDED.nombre, descripcion = EXCLUDED.descripcion;

-- 5. MÉDICOS (4 Médicos Especialistas)
INSERT INTO medicos (id, codigo, registro_profesional, nombres, apellidos, telefono, correo, id_usuario, activo) VALUES
(1, 'MED-001', 'MP-30412', 'Carlos', 'Justiniano Vaca', '77012345', 'carlos.justiniano@vidasalud.bo', 4, TRUE),
(2, 'MED-002', 'MP-41209', 'María Elena', 'Pinto Flores', '78456123', 'maria.pinto@vidasalud.bo', 5, TRUE),
(3, 'MED-003', 'MP-52381', 'Fernando', 'Aguilera Cuéllar', '71629481', 'fernando.aguilera@vidasalud.bo', 6, TRUE),
(4, 'MED-004', 'MP-38914', 'Patricia', 'Vaca Suárez', '72198453', 'patricia.vaca@vidasalud.bo', 7, TRUE)
ON CONFLICT (id) DO UPDATE SET 
    codigo = EXCLUDED.codigo,
    registro_profesional = EXCLUDED.registro_profesional,
    nombres = EXCLUDED.nombres,
    apellidos = EXCLUDED.apellidos,
    id_usuario = EXCLUDED.id_usuario;

-- Médico - Especialidad
DELETE FROM medico_especialidad WHERE id_medico IN (1, 2, 3, 4);
INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES
(1, 1), -- Dr. Justiniano: Medicina General
(1, 3), -- Dr. Justiniano: Cardiología
(2, 2), -- Dra. Pinto: Pediatría
(3, 6), -- Dr. Aguilera: Traumatología
(4, 4), -- Dra. Vaca: Ginecología
(4, 1); -- Dra. Vaca: Medicina General

-- 6. HORARIOS DE ATENCIÓN DE MÉDICOS (Múltiples Jornadas Semanales)
DELETE FROM horarios_atencion WHERE id_medico IN (1, 2, 3, 4);
-- Dr. Justiniano (MED-001): Lunes (1), Miércoles (3), Viernes (5) Mañanas (08:00 a 12:00)
INSERT INTO horarios_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo) VALUES
(1, 1, '08:00', '12:00', 30, TRUE),
(1, 3, '08:00', '12:00', 30, TRUE),
(1, 5, '08:00', '12:00', 30, TRUE);

-- Dra. Pinto (MED-002): Martes (2), Jueves (4) Tardes (14:00 a 18:00)
INSERT INTO horarios_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo) VALUES
(2, 2, '14:00', '18:00', 30, TRUE),
(2, 4, '14:00', '18:00', 30, TRUE);

-- Dr. Aguilera (MED-003): Lunes (1), Miércoles (3) Tardes (14:00 a 18:00) y Sábado (6) Mañanas (08:00 a 12:00)
INSERT INTO horarios_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo) VALUES
(3, 1, '14:00', '18:00', 30, TRUE),
(3, 3, '14:00', '18:00', 30, TRUE),
(3, 6, '08:00', '12:00', 30, TRUE);

-- Dra. Vaca (MED-004): Martes (2), Jueves (4) Mañanas (08:00 a 12:00) y Viernes (5) Tardes (14:00 a 18:00)
INSERT INTO horarios_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo) VALUES
(4, 2, '08:00', '12:00', 30, TRUE),
(4, 4, '08:00', '12:00', 30, TRUE),
(4, 5, '14:00', '18:00', 30, TRUE);

-- 7. PACIENTES (20 Pacientes Bolivianos con Datos Clínicos y de Emergencia Completos)
INSERT INTO pacientes (
    id, codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion, 
    tipo_sangre, alergias, enfermedades_cronicas, medicamentos_actuales, antecedentes_relevantes,
    contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono, 
    id_usuario, activo
) VALUES
(1, 'PAC-0001', '8934521', 'Juan Pablo', 'Mercado Ríos', '1990-04-15', 'M', '70912345', 'juan.mercado@gmail.com', 'Av. San Martín #420, Equipetrol',
    'ORH+', 'Penicilina', 'Hipertensión arterial leve', 'Losartán 50mg', 'Apendicectomía en 2015',
    'María Elena Ríos', 'Madre', '70912346', 8, TRUE),
(2, 'PAC-0002', '5432198', 'Claudia', 'Vargas Suárez', '1995-11-20', 'F', '71089234', 'claudia.vargas@hotmail.com', 'Barrio Sirari Calle Los Claveles #45',
    'ARH+', 'Ninguna conocida', 'Ninguna', 'Ninguno', 'Sin antecedentes quirúrgicos',
    'Roberto Vargas', 'Padre', '71089235', 9, TRUE),
(3, 'PAC-0003', '7654321', 'Mateo', 'Justiniano Rojas', '2018-08-05', 'M', '73123456', 'padre.mateo@gmail.com', 'Av. Santos Dumont 3er Anillo',
    'ORH+', 'Polen y ácaros', 'Asma infantil controlada', 'Salbutamol si precisa', 'Sin antecedentes quirúrgicos',
    'Gonzalo Justiniano', 'Padre', '73123450', 10, TRUE),
(4, 'PAC-0004', '10928374', 'Ramiro', 'Suárez Saucedo', '1988-03-25', 'M', '71398234', 'ramiro.suarez@gmail.com', 'Av. Cristo Redentor 5to Anillo',
    'BRH+', 'Sulfas', 'Ninguna', 'Ninguno', 'Fractura clavícula derecha 2012',
    'Patricia Saucedo', 'Esposa', '71398230', 11, TRUE),
(5, 'PAC-0005', '6321987', 'Mariana', 'Céspedes Ortiz', '1992-09-14', 'F', '72154321', 'mariana.cespedes@yahoo.es', 'Plan 3000 Barrio El Carmen Calle 8',
    'ORH+', 'Ninguna conocida', 'Hipotiroidismo', 'Levotiroxina 75mcg', 'Cesárea previa en 2019',
    'Carlos Ortiz', 'Hermano', '72154320', 12, TRUE),
(6, 'PAC-0006', '4987654', 'Jorge Luis', 'Banegas Peña', '1975-12-01', 'M', '77312984', 'jorge.banegas@gmail.com', 'Villa 1ro de Mayo Calle 3 #112',
    'ARH+', 'AINES y aspirina', 'Diabetes mellitus tipo 2', 'Metformina 850mg', 'Colecistectomía laparoscópica 2021',
    'Silvia Peña', 'Esposa', '77312980', 13, TRUE),
(7, 'PAC-0007', '8123456', 'Valeria', 'Antelo Melgar', '2001-07-22', 'F', '78564123', 'valeria.antelo@gmail.com', 'Av. Busch 2do Anillo Edif. Jardines',
    'ORH+', 'Ninguna conocida', 'Ninguna', 'Ninguno', 'Sin antecedentes quirúrgicos',
    'Carmen Melgar', 'Madre', '78564120', 14, TRUE),
(8, 'PAC-0008', '3456789', 'Carlos Eduardo', 'Roca Paz', '1968-05-30', 'M', '70019283', 'carlos.roca@hotmail.com', 'Barrio Las Palmas Calle Palma Real',
    'ABRH+', 'Ibuprofeno', 'Hipertensión arterial', 'Enalapril 10mg', 'Cirugía de meniscos 2017',
    'Lucía Paz', 'Cónyuge', '70019280', 15, TRUE),
(9, 'PAC-0009', '9234567', 'Luciana', 'Morales Gutiérrez', '2015-02-18', 'F', '71625344', 'tutor.luciana@gmail.com', 'Av. Grigotá 4to Anillo',
    'ORH+', 'Lactosa leve', 'Ninguna', 'Ninguno', 'Sin antecedentes quirúrgicos',
    'Marcela Gutiérrez', 'Madre', '71625340', 16, TRUE),
(10, 'PAC-0010', '5678912', 'Rodrigo', 'Chávez Hurtado', '1983-10-10', 'M', '73291845', 'rodrigo.chavez@gmail.com', 'Barrio Hamacas Calle 5 #23',
    'ARH-', 'Ninguna conocida', 'Gastritis crónica', 'Omeprazol 20mg', 'Amigdalectomía en la infancia',
    'Daniela Hurtado', 'Hermana', '73291840', 17, TRUE),
(11, 'PAC-0011', '7891234', 'Andrea', 'Siles Montaño', '1997-04-03', 'F', '74581293', 'andrea.siles@gmail.com', 'Los Lotes Mz. 14 Lote 8',
    'ORH+', 'Ninguna conocida', 'Ninguna', 'Ninguno', 'Sin antecedentes quirúrgicos',
    'Pedro Siles', 'Padre', '74581290', 18, TRUE),
(12, 'PAC-0012', '11223344', 'Sebastián', 'Ribera Justiniano', '2020-11-12', 'M', '76091823', 'madre.sebas@gmail.com', 'Av. Piraí 3er Anillo Interno',
    'ORH+', 'Amoxicilina', 'Rinitis alérgica', 'Cetirizina jarabe', 'Sin antecedentes quirúrgicos',
    'Paola Justiniano', 'Madre', '76091820', 19, TRUE),
(13, 'PAC-0013', '6789012', 'Gabriela', 'Cuéllar Vaca', '1993-01-29', 'F', '78129456', 'gaby.cuellar@gmail.com', 'Av. Alemana 6to Anillo',
    'BRH+', 'Ninguna conocida', 'Migraña recurrente', 'Paracetamol 1g si dolor', 'Sin antecedentes quirúrgicos',
    'Jorge Cuéllar', 'Esposo', '78129450', 20, TRUE),
(14, 'PAC-0014', '4321098', 'Mauricio', 'Taborga Salvatierra', '1980-06-17', 'M', '77089123', 'mauricio.taborga@gmail.com', 'Av. Tres Pasos al Frente Calle 12',
    'ORH+', 'Ciprofloxacino', 'Hiperuricemia', 'Alopurinol 100mg', 'Sin antecedentes quirúrgicos',
    'Elena Salvatierra', 'Cónyuge', '77089120', 21, TRUE),
(15, 'PAC-0015', '8901234', 'Camila', 'Dorado Soliz', '2004-08-09', 'F', '71382945', 'camila.dorado@gmail.com', 'Barrio Equipetrol Norte Calle J',
    'ARH+', 'Ninguna conocida', 'Ninguna', 'Ninguno', 'Sin antecedentes quirúrgicos',
    'Sonia Soliz', 'Madre', '71382940', 22, TRUE),
(16, 'PAC-0016', '5544332', 'David', 'Pinto Aguilera', '1972-03-05', 'M', '72291834', 'david.pinto@gmail.com', 'Av. Virgen de Cotoca Km 4',
    'ORH+', 'Penicilina', 'Dislipidemia', 'Atorvastatina 20mg', 'Sin antecedentes quirúrgicos',
    'Beatriz Aguilera', 'Esposa', '72291830', 23, TRUE),
(17, 'PAC-0017', '9988776', 'Sofía', 'Barba Moreno', '2012-09-25', 'F', '75019283', 'sofia.barba@gmail.com', 'Barrio Petrolero Calle 8 #14',
    'ORH+', 'Picaduras de avispa', 'Ninguna', 'Ninguno', 'Sin antecedentes quirúrgicos',
    'Hugo Barba', 'Padre', '75019280', 24, TRUE),
(18, 'PAC-0018', '3322110', 'Alejandro', 'Arteaga Bravo', '1985-12-14', 'M', '70839201', 'alejandro.arteaga@gmail.com', 'Av. Beni 4to Anillo',
    'ORH-', 'Ninguna conocida', 'Ninguna', 'Ninguno', 'Apendicectomía 2010',
    'Carla Bravo', 'Hermana', '70839200', 25, TRUE),
(19, 'PAC-0019', '7766554', 'Nataly', 'Torrico Menacho', '1999-05-18', 'F', '73491820', 'nataly.torrico@gmail.com', 'Barrio Guaracachi Calle 4',
    'ARH+', 'Ninguna conocida', 'Ninguna', 'Ninguno', 'Sin antecedentes quirúrgicos',
    'Esther Menacho', 'Madre', '73491822', 26, TRUE),
(20, 'PAC-0020', '6655443', 'Fabricio', 'Villarroel Zeballos', '1991-08-30', 'M', '77123948', 'fabricio.v@gmail.com', 'Av. Mutualista 3er Anillo Externo',
    'ORH+', 'Ninguna conocida', 'Ninguna', 'Ninguno', 'Sin antecedentes quirúrgicos',
    'Luis Villarroel', 'Hermano', '77123940', 27, TRUE)
ON CONFLICT (id) DO UPDATE SET 
    codigo = EXCLUDED.codigo,
    ci = EXCLUDED.ci,
    nombres = EXCLUDED.nombres,
    apellidos = EXCLUDED.apellidos,
    tipo_sangre = EXCLUDED.tipo_sangre,
    alergias = EXCLUDED.alergias,
    enfermedades_cronicas = EXCLUDED.enfermedades_cronicas,
    medicamentos_actuales = EXCLUDED.medicamentos_actuales,
    antecedentes_relevantes = EXCLUDED.antecedentes_relevantes,
    contacto_emergencia_nombre = EXCLUDED.contacto_emergencia_nombre,
    contacto_emergencia_parentesco = EXCLUDED.contacto_emergencia_parentesco,
    contacto_emergencia_telefono = EXCLUDED.contacto_emergencia_telefono,
    id_usuario = EXCLUDED.id_usuario;

-- 8. CITAS MÉDICAS (25 Citas Médicas Variadas en Estados y Fechas)
DELETE FROM citas_medicas WHERE id BETWEEN 1 AND 25;
INSERT INTO citas_medicas (id, codigo_cita, id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, motivo_consulta, estado, motivo_cancelacion, observaciones_atencion, id_usuario_registro) VALUES
-- Citas de Dr. Justiniano (Lunes 2026-10-26)
(1, 'CM-2026-0001', 1, 1, 1, '2026-10-26', '08:00', '08:30', 'Chequeo general de presión arterial', 'Programada', NULL, NULL, 2),
(2, 'CM-2026-0002', 2, 1, 3, '2026-10-26', '08:30', '09:00', 'Evaluación por palpitaciones y fatiga', 'Programada', NULL, NULL, 2),
(3, 'CM-2026-0003', 4, 1, 1, '2026-10-26', '10:00', '10:30', 'Control de laboratorio y colesterol', 'Programada', NULL, NULL, 2),
(4, 'CM-2026-0004', 6, 1, 3, '2026-10-26', '11:00', '11:30', 'Control postoperatorio cardiovascular', 'Confirmada', NULL, NULL, 2),

-- Citas de Dra. Pinto (Martes 2026-10-27)
(5, 'CM-2026-0005', 3, 2, 2, '2026-10-27', '14:00', '14:30', 'Control de crecimiento, peso y vacunas', 'Programada', NULL, NULL, 2),
(6, 'CM-2026-0006', 9, 2, 2, '2026-10-27', '14:30', '15:00', 'Cuadro de fiebre recurrente y tos seca', 'Confirmada', NULL, NULL, 2),
(7, 'CM-2026-0007', 12, 2, 2, '2026-10-27', '15:30', '16:00', 'Revisión por alergia cutánea infantil', 'Programada', NULL, NULL, 2),

-- Citas de Dr. Aguilera - Traumatología (Lunes 2026-10-26)
(8, 'CM-2026-0008', 8, 3, 6, '2026-10-26', '14:00', '14:30', 'Dolor agudo en rodilla derecha tras caída', 'Programada', NULL, NULL, 2),
(9, 'CM-2026-0009', 10, 3, 6, '2026-10-26', '15:00', '15:30', 'Evaluación de fractura de muñeca', 'Confirmada', NULL, NULL, 2),
(10, 'CM-2026-0010', 14, 3, 6, '2026-10-26', '16:00', '16:30', 'Control de esguince de tobillo grado II', 'Programada', NULL, NULL, 2),

-- Citas de Dra. Vaca - Ginecología (Martes 2026-10-27)
(11, 'CM-2026-0011', 5, 4, 4, '2026-10-27', '08:30', '09:00', 'Control prenatal de rutina semana 24', 'Programada', NULL, NULL, 2),
(12, 'CM-2026-0012', 7, 4, 4, '2026-10-27', '09:30', '10:00', 'Papanicolau y chequeo ginecológico anual', 'Confirmada', NULL, NULL, 2),
(13, 'CM-2026-0013', 13, 4, 4, '2026-10-27', '10:30', '11:00', 'Consulta por dolor pélvico persistente', 'Programada', NULL, NULL, 2),

-- Citas Históricas Atendidas (Semana Anterior: 2026-10-19)
(14, 'CM-2026-0014', 1, 1, 1, '2026-10-19', '08:00', '08:30', 'Consulta por cuadro gripal severo', 'Atendida', NULL, 'Paciente presenta faringitis aguda. Se prescribe amoxicilina 500mg y reposo por 3 días.', 2),
(15, 'CM-2026-0015', 2, 1, 3, '2026-10-19', '09:00', '09:30', 'Electrocardiograma preventivo', 'Atendida', NULL, 'Ritmo sinusal normal. Presión 120/80. Se recomienda reducir ingesta de sodio.', 2),
(16, 'CM-2026-0016', 4, 1, 1, '2026-10-19', '10:00', '10:30', 'Certificado médico laboral', 'Atendida', NULL, 'Evaluación clínica general satisfactoria. Apto para tareas laborales.', 2),
(17, 'CM-2026-0017', 3, 2, 2, '2026-10-20', '14:00', '14:30', 'Control de vacunas de los 6 años', 'Atendida', NULL, 'Se aplican vacunas de refuerzo. Peso 21kg, talla adecuada.', 2),
(18, 'CM-2026-0018', 6, 3, 6, '2026-10-19', '14:30', '15:00', 'Dolor lumbar mecánico', 'Atendida', NULL, 'Lumbalgia por esfuerzo postural. Se indican antiinflamatorios y fisioterapia.', 2),
(19, 'CM-2026-0019', 15, 4, 4, '2026-10-20', '08:30', '09:00', 'Consulta ginecológica preventiva', 'Atendida', NULL, 'Ecografía pélvica dentro de parámetros normales.', 2),

-- Citas Canceladas con Motivo Justificado (Prueba de Liberación y Trazabilidad RN09, RN10)
(20, 'CM-2026-0020', 16, 1, 1, '2026-10-21', '08:30', '09:00', 'Control de glucosa en ayunas', 'Cancelada', 'Paciente tuvo un viaje urgente a Montero y solicitó reprogramar', NULL, 2),
(21, 'CM-2026-0021', 17, 2, 2, '2026-10-22', '14:30', '15:00', 'Fiebre y dolor de garganta', 'Cancelada', 'Madre avisó que paciente mejoró y no requirió consulta presencial', NULL, 2),
(22, 'CM-2026-0022', 18, 3, 6, '2026-10-21', '15:00', '15:30', 'Dolor de hombro derecho', 'Cancelada', 'Conflicto laboral del paciente, solicitó agendar para otra semana', NULL, 2),

-- Citas Marcadas como Inasistencia (Para Reportes Gerenciales CU13)
(23, 'CM-2026-0023', 19, 1, 1, '2026-10-21', '11:00', '11:30', 'Chequeo de presión', 'Inasistencia', NULL, 'Paciente no se presentó a su turno ni notificó cancelación', 2),
(24, 'CM-2026-0024', 20, 4, 4, '2026-10-22', '10:00', '10:30', 'Control ginecológico', 'Inasistencia', NULL, 'Paciente ausente en sala de espera a la hora programada', 2),
(25, 'CM-2026-0025', 11, 2, 2, '2026-10-22', '16:00', '16:30', 'Revisión pediátrica', 'Inasistencia', NULL, 'Sin respuesta telefónica al momento de llamar su turno', 2);

-- Sincronizar secuencias de PostgreSQL
SELECT setval('roles_id_seq', (SELECT COALESCE(MAX(id), 1) FROM roles));
SELECT setval('permisos_id_seq', (SELECT COALESCE(MAX(id), 1) FROM permisos));
SELECT setval('usuarios_id_seq', (SELECT COALESCE(MAX(id), 1) FROM usuarios));
SELECT setval('especialidades_id_seq', (SELECT COALESCE(MAX(id), 1) FROM especialidades));
SELECT setval('medicos_id_seq', (SELECT COALESCE(MAX(id), 1) FROM medicos));
SELECT setval('pacientes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM pacientes));
SELECT setval('horarios_atencion_id_seq', (SELECT COALESCE(MAX(id), 1) FROM horarios_atencion));
SELECT setval('citas_medicas_id_seq', (SELECT COALESCE(MAX(id), 1) FROM citas_medicas));

COMMIT;
