-- Script de creación de esquemas y datos semilla para PostgreSQL
-- Módulo de Consulta Pública
-- Integrante: Rosalinda Cedillo Osornio

CREATE TABLE IF NOT EXISTS DocumentosVigentes ( 
    idOriginal INT PRIMARY KEY,
    tituloDocumento VARCHAR(200) NOT NULL,
    rutaArchivo VARCHAR(500) NOT NULL,
    empresaId INT NOT NULL,
    fechaAprobacion TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS LogsConsultas ( 
    idLog SERIAL PRIMARY KEY,
    idDocumento INT NOT NULL,
    nombreUsuario VARCHAR(100) NOT NULL,
    fechaConsulta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documento FOREIGN KEY (idDocumento) REFERENCES DocumentosVigentes(idOriginal)
);

-- Insertar datos semilla de prueba si no existen
INSERT INTO DocumentosVigentes (idOriginal, tituloDocumento, rutaArchivo, empresaId, fechaAprobacion) 
VALUES 
(101, 'Manual de Ensamble de Motor', 'docs/empresa1/manual_ensamble.pdf', 1, '2026-05-01 10:00:00'), 
(102, 'Protocolo de Higiene de Planta', 'docs/empresa2/protocolo_higiene.pdf', 2, '2026-05-02 11:30:00')
ON CONFLICT (idOriginal) DO NOTHING;
