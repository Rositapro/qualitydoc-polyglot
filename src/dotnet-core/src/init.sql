CREATE DATABASE DocumentManagement;
GO
USE DocumentManagement;
GO

-- 0. Tabla de Empresas
CREATE TABLE Company (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL,
    IsDeleted BIT DEFAULT 0
);

-- 1. Tablas de Catálogo
CREATE TABLE Role (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL
);

CREATE TABLE Category (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL,
    CompanyId INT FOREIGN KEY REFERENCES Company(Id)
);

-- 2. Tabla de Usuarios
CREATE TABLE [User] (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Username VARCHAR(50) NOT NULL,
    PasswordHash VARCHAR(MAX) NOT NULL,
    RoleId INT FOREIGN KEY REFERENCES Role(Id),
    CompanyId INT NULL FOREIGN KEY REFERENCES Company(Id),
    IsDeleted BIT DEFAULT 0,
    Email VARCHAR(100) NOT NULL
);

-- 3. Documentos
CREATE TABLE Document (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Title VARCHAR(200) NOT NULL,
    CompanyId INT FOREIGN KEY REFERENCES Company(Id),
    CategoryId INT FOREIGN KEY REFERENCES Category(Id),
    CreatedAt DATETIME DEFAULT GETDATE()
);

CREATE TABLE DocumentVersion (
    Id INT PRIMARY KEY IDENTITY(1,1),
    DocumentId INT FOREIGN KEY REFERENCES Document(Id),
    VersionNumber FLOAT NOT NULL,
    Status VARCHAR(20),
    CreatedAt DATETIME DEFAULT GETDATE()
);

-- 4. Flujo
CREATE TABLE ApprovalFlow (
    Id INT PRIMARY KEY IDENTITY(1,1),
    VersionId INT FOREIGN KEY REFERENCES DocumentVersion(Id),
    ApproverId INT FOREIGN KEY REFERENCES [User](Id),
    Comments TEXT,
    Decision VARCHAR(20)
);

-- 5. Datos Iniciales
INSERT INTO Role (Name) VALUES ('SuperAdmin'), ('Administrador'), ('Revisor'), ('Autor'), ('Lector'), ('Aprobador');
INSERT INTO Company (Name) VALUES ('Empresa Maestra');
INSERT INTO [User] (Username, PasswordHash, RoleId, CompanyId) VALUES ('superadmin', 'Document2026!', 1, NULL);
INSERT INTO [User] (Username, PasswordHash, RoleId, CompanyId) VALUES ('admin_empresa', 'Document2026!', 2, 1);
GO
