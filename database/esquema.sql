BEGIN;

-- Extensión requerida para validar no-solapamiento de citas médicas
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- 1. ROLES Y PERMISOS
CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(150),
    activo BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS permisos (
    id SERIAL PRIMARY KEY,
    codigo_permiso VARCHAR(60) NOT NULL UNIQUE,
    descripcion VARCHAR(120) NOT NULL
);

CREATE TABLE IF NOT EXISTS rol_permiso (
    id_rol INT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    id_permiso INT NOT NULL REFERENCES permisos(id) ON DELETE CASCADE,
    PRIMARY KEY (id_rol, id_permiso)
);

-- 2. USUARIOS
CREATE TABLE IF NOT EXISTS usuarios (
    id BIGSERIAL PRIMARY KEY,
    correo VARCHAR(120) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
    nombres VARCHAR(80),
    apellidos VARCHAR(80),
    telefono VARCHAR(20),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    ultimo_acceso TIMESTAMPTZ,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. ESPECIALIDADES MÉDICAS
CREATE TABLE IF NOT EXISTS especialidades (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    duracion_minutos INT NOT NULL DEFAULT 30 CHECK (duracion_minutos > 0),
    activa BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_especialidad_nombre_normalizado ON especialidades (LOWER(TRIM(nombre)));

-- 4. MÉDICOS
CREATE TABLE IF NOT EXISTS medicos (
    id BIGSERIAL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    registro_profesional VARCHAR(40) NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    correo VARCHAR(120),
    id_usuario BIGINT UNIQUE REFERENCES usuarios(id) ON DELETE SET NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_medicos_apellidos_nombres ON medicos(apellidos, nombres);

CREATE TABLE IF NOT EXISTS medico_especialidad (
    id_medico BIGINT NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    id_especialidad INT NOT NULL REFERENCES especialidades(id) ON DELETE RESTRICT,
    PRIMARY KEY (id_medico, id_especialidad)
);

-- 5. PACIENTES
CREATE TABLE IF NOT EXISTS pacientes (
    id BIGSERIAL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    ci VARCHAR(20) NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo CHAR(1) NOT NULL CHECK (sexo IN ('M', 'F', 'O')),
    telefono VARCHAR(20) NOT NULL,
    correo VARCHAR(120),
    direccion VARCHAR(200),
    tipo_sangre VARCHAR(10) DEFAULT 'ORH+',
    alergias TEXT DEFAULT 'Ninguna conocida',
    enfermedades_cronicas TEXT DEFAULT 'Ninguna',
    medicamentos_actuales TEXT DEFAULT 'Ninguno',
    antecedentes_relevantes TEXT DEFAULT 'Sin antecedentes quirúrgicos',
    contacto_emergencia_nombre VARCHAR(100),
    contacto_emergencia_parentesco VARCHAR(50) DEFAULT 'Familiar directo',
    contacto_emergencia_telefono VARCHAR(20),
    id_usuario BIGINT UNIQUE REFERENCES usuarios(id) ON DELETE SET NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_pacientes_ci ON pacientes(ci);
CREATE INDEX IF NOT EXISTS idx_pacientes_apellidos_nombres ON pacientes(apellidos, nombres);

-- 6. HORARIOS DE ATENCIÓN DE MÉDICOS
CREATE TABLE IF NOT EXISTS horarios_atencion (
    id BIGSERIAL PRIMARY KEY,
    id_medico BIGINT NOT NULL REFERENCES medicos(id) ON DELETE CASCADE,
    dia_semana SMALLINT NOT NULL CHECK (dia_semana BETWEEN 1 AND 7),
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    duracion_minutos INT NOT NULL DEFAULT 30 CHECK (duracion_minutos > 0),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT chk_rango_horario CHECK (hora_fin > hora_inicio),
    CONSTRAINT uq_medico_dia_hora UNIQUE (id_medico, dia_semana, hora_inicio)
);

-- 7. CITAS MÉDICAS (MÓDULO CENTRAL)
CREATE TABLE IF NOT EXISTS citas_medicas (
    id BIGSERIAL PRIMARY KEY,
    codigo_cita VARCHAR(20) NOT NULL UNIQUE,
    id_paciente BIGINT NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    id_medico BIGINT NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    id_especialidad INT NOT NULL REFERENCES especialidades(id) ON DELETE RESTRICT,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    motivo_consulta VARCHAR(250),
    estado VARCHAR(20) NOT NULL DEFAULT 'Programada' 
        CHECK (estado IN ('Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia')),
    motivo_cancelacion VARCHAR(250),
    observaciones_atencion TEXT,
    id_usuario_registro BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_horas_cita CHECK (hora_fin > hora_inicio)
);

-- REGLA RN07 FÍSICA: Evita físicamente solapamiento de citas activas del mismo médico
-- Las citas con estado 'Cancelada' quedan excluidas (RN09)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'ex_cita_medico_no_solapada'
    ) THEN
        ALTER TABLE citas_medicas
            ADD CONSTRAINT ex_cita_medico_no_solapada
            EXCLUDE USING gist (
                id_medico WITH =,
                tsrange(fecha + hora_inicio, fecha + hora_fin, '[)') WITH &&
            )
            WHERE (estado <> 'Cancelada');
    END IF;
END $$;

-- Índices de consulta de citas médicas
CREATE INDEX IF NOT EXISTS idx_citas_fecha_estado ON citas_medicas(fecha, estado);
CREATE INDEX IF NOT EXISTS idx_citas_paciente ON citas_medicas(id_paciente, fecha DESC);
CREATE INDEX IF NOT EXISTS idx_citas_medico_fecha ON citas_medicas(id_medico, fecha);

-- Vista de Reportes y Trazabilidad
CREATE OR REPLACE VIEW vista_citas_completas AS
SELECT 
    c.id,
    c.codigo_cita,
    c.fecha,
    c.hora_inicio,
    c.hora_fin,
    c.estado,
    c.motivo_consulta,
    c.motivo_cancelacion,
    c.observaciones_atencion,
    p.codigo AS codigo_paciente,
    p.ci AS ci_paciente,
    (p.nombres || ' ' || p.apellidos) AS nombre_completo_paciente,
    p.telefono AS telefono_paciente,
    m.codigo AS codigo_medico,
    (m.nombres || ' ' || m.apellidos) AS nombre_completo_medico,
    m.registro_profesional AS matricula_medico,
    e.nombre AS nombre_especialidad,
    u.correo AS registrado_por
FROM citas_medicas c
JOIN pacientes p ON p.id = c.id_paciente
JOIN medicos m ON m.id = c.id_medico
JOIN especialidades e ON e.id = c.id_especialidad
JOIN usuarios u ON u.id = c.id_usuario_registro;

COMMIT;
