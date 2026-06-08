CREATE DATABASE DocumentManagement;
GO
USE DocumentManagement;
GO

-- 0. Tabla de Empresas
CREATE TABLE Company (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL,
    IsDeleted BIT DEFAULT 0,
    DateCreate DATETIME NULL DEFAULT GETDATE(),
    DateUpdate DATETIME NULL,
    DateDelete DATETIME NULL,
    IdUserCreate INT NULL,
    IdUserUpdate INT NULL,
    IdUserDelete INT NULL,
    Status BIT NOT NULL DEFAULT 1
);

-- 1. Tablas de Catálogo
CREATE TABLE Role (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL,
    DateCreate DATETIME NULL DEFAULT GETDATE(),
    DateUpdate DATETIME NULL,
    DateDelete DATETIME NULL,
    IdUserCreate INT NULL,
    IdUserUpdate INT NULL,
    IdUserDelete INT NULL,
    Status BIT NOT NULL DEFAULT 1
);

CREATE TABLE Iso (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Name VARCHAR(100) NOT NULL,
    CompanyId INT FOREIGN KEY REFERENCES Company(Id),
    DateCreate DATETIME NULL DEFAULT GETDATE(),
    DateUpdate DATETIME NULL,
    DateDelete DATETIME NULL,
    IdUserCreate INT NULL,
    IdUserUpdate INT NULL,
    IdUserDelete INT NULL,
    Status BIT NOT NULL DEFAULT 1
);

-- 2. Tabla de Usuarios
CREATE TABLE [User] (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Username VARCHAR(50) NOT NULL,
    PasswordHash VARCHAR(MAX) NOT NULL,
    RoleId INT FOREIGN KEY REFERENCES Role(Id),
    CompanyId INT NULL FOREIGN KEY REFERENCES Company(Id),
    IsDeleted BIT DEFAULT 0,
    Email VARCHAR(100) NOT NULL,
    DateCreate DATETIME NULL DEFAULT GETDATE(),
    DateUpdate DATETIME NULL,
    DateDelete DATETIME NULL,
    IdUserCreate INT NULL,
    IdUserUpdate INT NULL,
    IdUserDelete INT NULL,
    Status BIT NOT NULL DEFAULT 1
);

-- 3. Documentos
CREATE TABLE Document (
    Id INT PRIMARY KEY IDENTITY(1,1),
    Title VARCHAR(200) NOT NULL,
    Description VARCHAR(MAX) NULL,
    AuthorId INT NOT NULL,
    CompanyId INT FOREIGN KEY REFERENCES Company(Id),
    IsoId INT FOREIGN KEY REFERENCES Iso(Id),
    RejectionNotes VARCHAR(MAX) NULL,
    WorkflowState INT NOT NULL DEFAULT 1, -- Mapea al Enum DocumentStatus (Revision)
    CreatedAt DATETIME DEFAULT GETDATE(),
    DateCreate DATETIME NULL DEFAULT GETDATE(),
    DateUpdate DATETIME NULL,
    DateDelete DATETIME NULL,
    IdUserCreate INT NULL,
    IdUserUpdate INT NULL,
    IdUserDelete INT NULL,
    Status BIT NOT NULL DEFAULT 1 -- Heredado de BaseEntity para el soft-delete o activo
);

CREATE TABLE DocumentVersion (
    Id INT PRIMARY KEY IDENTITY(1,1),
    DocumentId INT FOREIGN KEY REFERENCES Document(Id),
    VersionNumber FLOAT NOT NULL,
    Status VARCHAR(20) NOT NULL DEFAULT 'Vigente', -- Cambiado a VARCHAR para el ciclo de vida
    FileUrl VARCHAR(500) NULL,
    Extension VARCHAR(20) NULL,
    ChangeLog VARCHAR(MAX) NULL,
    CreatedAt DATETIME DEFAULT GETDATE(),
    DateCreate DATETIME NULL DEFAULT GETDATE(),
    DateUpdate DATETIME NULL,
    DateDelete DATETIME NULL,
    IdUserCreate INT NULL,
    IdUserUpdate INT NULL,
    IdUserDelete INT NULL
);

-- 4. Flujo de Aprobación
CREATE TABLE ApprovalFlow (
    Id INT PRIMARY KEY IDENTITY(1,1),
    VersionId INT FOREIGN KEY REFERENCES DocumentVersion(Id),
    ApproverId INT FOREIGN KEY REFERENCES [User](Id),
    Comments TEXT,
    Decision VARCHAR(20),
    DateCreate DATETIME NULL DEFAULT GETDATE(),
    DateUpdate DATETIME NULL,
    DateDelete DATETIME NULL,
    IdUserCreate INT NULL,
    IdUserUpdate INT NULL,
    IdUserDelete INT NULL,
    Status BIT NOT NULL DEFAULT 1
);

CREATE TABLE Suggestion (
    Id INT PRIMARY KEY IDENTITY(1,1),
    DocumentId INT FOREIGN KEY REFERENCES Document(Id),
    AuthorName VARCHAR(100) NOT NULL,
    Comment VARCHAR(MAX) NOT NULL,
    CompanyId INT NOT NULL,
    DateCreate DATETIME NULL DEFAULT GETDATE(),
    DateUpdate DATETIME NULL,
    DateDelete DATETIME NULL,
    IdUserCreate INT NULL,
    IdUserUpdate INT NULL,
    IdUserDelete INT NULL,
    Status BIT NOT NULL DEFAULT 1
);

-- 5. Datos Iniciales
-- Seed Roles (Matching the Authorize attributes in .NET: SuperAdmin, Administrator, Reviewer, Author, Reader)
INSERT INTO Role (Name) VALUES ('SuperAdmin'), ('Administrator'), ('Reviewer'), ('Author'), ('Reader'), ('Approver');

-- Seed Companies
INSERT INTO Company (Name) VALUES ('Empresa Maestra'); -- ID 1

-- Seed Users
-- SuperAdmin & Admin Maestra
INSERT INTO [User] (Username, PasswordHash, RoleId, CompanyId, Email) 
VALUES ('superadmin', 'Document2026!', 1, NULL, 'superadmin@qualitydoc.com');

INSERT INTO [User] (Username, PasswordHash, RoleId, CompanyId, Email) 
VALUES ('admin_empresa', 'Document2026!', 2, 1, 'admin_empresa@qualitydoc.com');
GO
