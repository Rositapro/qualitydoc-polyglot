-- Base de Datos: sgd_db (PostgreSQL)
-- Script de inicialización y estructura

-- Eliminar tablas si existen para garantizar una recreación limpia
DROP TABLE IF EXISTS sugerencias CASCADE;
DROP TABLE IF EXISTS logsconsultas CASCADE;
DROP TABLE IF EXISTS documento CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;

-- 1. Tabla: usuarios
CREATE TABLE usuarios (
    idusuario VARCHAR(50) PRIMARY KEY,
    nombreusuario VARCHAR(100) NOT NULL,
    rol VARCHAR(50) NOT NULL, -- e.g. admin, colaborador, auditor
    empresaid INT NOT NULL
);

-- 2. Tabla: documento
CREATE TABLE documento (
    iddocumento SERIAL PRIMARY KEY,
    titulodocumento VARCHAR(255) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    version VARCHAR(20) NOT NULL,
    idiso VARCHAR(50) NOT NULL, -- e.g. ISO 9001, ISO 27001
    estado VARCHAR(20) NOT NULL CHECK (estado IN ('vigente', 'obsoleto')),
    empresaid INT NOT NULL,
    rutaarchivo VARCHAR(255) NULL
);

-- 3. Tabla: logsconsultas
CREATE TABLE logsconsultas (
    idlog SERIAL PRIMARY KEY,
    idusuario VARCHAR(50) NOT NULL,
    iddocumento INT NOT NULL,
    accion VARCHAR(50) NOT NULL CHECK (accion IN ('visualizacion', 'descarga', 'sugerencia')),
    empresaid INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_usuario FOREIGN KEY (idusuario) REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    CONSTRAINT fk_logs_documento FOREIGN KEY (iddocumento) REFERENCES documento(iddocumento) ON DELETE CASCADE
);

-- 4. Tabla: sugerencias
CREATE TABLE sugerencias (
    idsugerencia SERIAL PRIMARY KEY,
    iddocumento INT NOT NULL,
    idusuario VARCHAR(50) NOT NULL,
    comentario TEXT NOT NULL,
    empresaid INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sugerencias_usuario FOREIGN KEY (idusuario) REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    CONSTRAINT fk_sugerencias_documento FOREIGN KEY (iddocumento) REFERENCES documento(iddocumento) ON DELETE CASCADE
);

-- Indices para optimizar el filtrado multi-tenencia
CREATE INDEX idx_usuarios_empresa ON usuarios(empresaid);
CREATE INDEX idx_documento_empresa ON documento(empresaid);
CREATE INDEX idx_logs_empresa ON logsconsultas(empresaid);
CREATE INDEX idx_sugerencias_empresa ON sugerencias(empresaid);

-- ==========================================
-- DATOS SEMILLA (Seed Data)
-- ==========================================
-- Insertar usuario de sistema para auditoría automática por trigger
INSERT INTO usuarios (idusuario, nombreusuario, rol, empresaid) 
VALUES ('sistema_dotnet', 'Sistema central .NET', 'sistema', 1);

-- ==========================================
-- TRIGGERS (Disparadores de Base de Datos)
-- ==========================================

-- 1. Crear función del disparador
CREATE OR REPLACE FUNCTION trigger_log_publicacion()
RETURNS TRIGGER AS $$
BEGIN
    -- Inserta de manera automática un log de visualización cuando se publica un nuevo documento
    INSERT INTO logsconsultas (idusuario, iddocumento, accion, empresaid, fecha)
    VALUES ('sistema_dotnet', NEW.iddocumento, 'visualizacion', NEW.empresaid, NOW());
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 2. Crear el trigger en la tabla documento
CREATE TRIGGER trg_despues_publicar
AFTER INSERT ON documento
FOR EACH ROW
EXECUTE FUNCTION trigger_log_publicacion();
