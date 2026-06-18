CREATE DATABASE DocumentManagement;
GO
USE DocumentManagement;
GO

-- 0. Tabla de Empresas
CREATE TABLE Company (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL,
    Status BIT NOT NULL DEFAULT 1  -- 1 = Activa/Vigente | 0 = Eliminada/Inactiva
);

-- 1. Tablas de Catálogo
CREATE TABLE Role (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL,
    Status BIT NOT NULL DEFAULT 1  -- 1 = Activo/Vigente | 0 = Eliminado/Inactivo
);

CREATE TABLE Category (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL,
    CompanyId INT FOREIGN KEY REFERENCES Company(Id),
    Status BIT NOT NULL DEFAULT 1  -- 1 = Activa/Vigente | 0 = Eliminada/Inactiva
);

-- 2. Tabla de Usuarios
CREATE TABLE [User] (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Username VARCHAR(50) NOT NULL,
    PasswordHash VARCHAR(MAX) NOT NULL,
    RoleId INT FOREIGN KEY REFERENCES Role(Id),
    CompanyId INT NULL FOREIGN KEY REFERENCES Company(Id),
    Email VARCHAR(100) NOT NULL,
    Status BIT NOT NULL DEFAULT 1  -- 1 = Activo/Vigente | 0 = Eliminado/Inactivo
);

-- 3. Documentos
CREATE TABLE Document (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Title VARCHAR(200) NOT NULL,
    CompanyId INT FOREIGN KEY REFERENCES Company(Id),
    CategoryId INT FOREIGN KEY REFERENCES Category(Id),
    CreatedAt DATETIME DEFAULT GETDATE(),
    Status BIT NOT NULL DEFAULT 1  -- 1 = Activo/Vigente | 0 = Eliminado/Inactivo
);

CREATE TABLE DocumentVersion (
    Id INT PRIMARY KEY IDENTITY(1,1),
    DocumentId INT FOREIGN KEY REFERENCES Document(Id),
    VersionNumber FLOAT NOT NULL,
    Status VARCHAR(20),  -- 'Vigente', 'Obsoleto', 'En Revision'
    CreatedAt DATETIME DEFAULT GETDATE()
);

-- 4. Flujo de Aprobacion
CREATE TABLE ApprovalFlow (
    Id INT PRIMARY KEY IDENTITY(1,1),
    VersionId INT FOREIGN KEY REFERENCES DocumentVersion(Id),
    ApproverId INT FOREIGN KEY REFERENCES [User](Id),
    Comments TEXT,
    Decision VARCHAR(20),
    Status BIT NOT NULL DEFAULT 1  -- 1 = Activo | 0 = Cancelado/Inactivo
);

-- 5. Datos Iniciales
INSERT INTO Role (Name) VALUES ('SuperAdmin'), ('Administrador'), ('Revisor'), ('Autor'), ('Lector'), ('Aprobador');
INSERT INTO Company (Name) VALUES ('Empresa Maestra');
INSERT INTO [User] (Username, PasswordHash, RoleId, CompanyId, Email) VALUES ('superadmin', 'Ho+T3Gd14Ck1nr+a0C8svscVBLaeNtnp9NThBijBOuBDRmwZ+vvr9KFRGgFP0Wy9', 1, NULL, 'superadmin@qualitydoc.com');
INSERT INTO [User] (Username, PasswordHash, RoleId, CompanyId, Email) VALUES ('admin_empresa', 'Ho+T3Gd14Ck1nr+a0C8svscVBLaeNtnp9NThBijBOuBDRmwZ+vvr9KFRGgFP0Wy9', 2, 1, 'admin_empresa@qualitydoc.com');
GO
