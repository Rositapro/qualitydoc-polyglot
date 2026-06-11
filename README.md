# QualityDoc-Polyglot

> **Sistema Integral de Gestión de Documentos de Calidad**  
> Proyecto integrador — Taller de BD · Desarrollo Web · Administración de Contenedores  
> Instituto Tecnológico Superior de Monclova · 6to Semestre

---

## Tabla de Contenidos

1. [¿Qué es este proyecto?](#qué-es-este-proyecto)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Tecnologías Utilizadas](#tecnologías-utilizadas)
4. [Requisitos Previos](#requisitos-previos)
5. [Instalación Rápida](#instalación-rápida)
6. [Instalación Manual](#instalación-manual)
7. [Acceso y Credenciales](#acceso-y-credenciales)
8. [Módulos del Sistema](#módulos-del-sistema)
9. [Flujo de Versiones de Documentos](#flujo-de-versiones-de-documentos)
10. [Roles de Usuario](#roles-de-usuario)
11. [Estructura del Repositorio](#estructura-del-repositorio)
12. [Base de Datos](#base-de-datos)
13. [API Endpoints](#api-endpoints)
14. [Comandos Útiles](#comandos-útiles)

---

## ¿Qué es este proyecto?

**QualityDoc-Polyglot** es una plataforma multi-empresa para el control del **ciclo de vida de documentos de calidad** bajo normativas como **ISO 9001** e **IATF 16949**.

El sistema permite que las empresas gestionen sus documentos internos (procedimientos, manuales, registros) a través de un flujo de trabajo con tres roles: **Autor -> Revisor -> Autorizador**, con control de versiones automático.

Es un proyecto **polyglot** (multilenguaje y multi-base de datos) que integra tres stacks tecnológicos distintos en un solo entorno Docker.

---

## Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────┐
│                    USUARIO / NAVEGADOR                   │
└──────────┬────────────────┬────────────────┬────────────┘
           │                │                │
     Puerto 5000      Puerto 8080      Puerto 3000
           │                │                │
   ┌────────▼───────┐ ┌──────▼──────┐ ┌──────▼──────────┐
   │  Portal Admin  │ │ Portal PHP  │ │ SmartSearch     │
   │  .NET Core 10  │ │   PHP 8     │ │ Node.js + TS    │
   │   (C# MVC)     │ │             │ │ (Express)       │
   └────────┬───────┘ └──────┬──────┘ └──────┬──────────┘
            │                │                │
     ┌──────▼──────┐  ┌──────▼──────┐  ┌─────▼───────┐
     │  SQL Server │  │ PostgreSQL  │  │   MongoDB   │
     │  (Puerto    │  │  (Puerto    │  │  (Puerto    │
     │   1433)     │  │   5432)     │  │   27017)    │
     └─────────────┘  └─────────────┘  └─────────────┘
```

### Red interna Docker: qualitydoc-net

Todos los servicios se comunican entre sí dentro de la red privada de Docker. Solo los puertos necesarios están expuestos al host.

---

## Tecnologías Utilizadas

| Capa | Tecnología | Versión | Propósito |
|------|-----------|---------|-----------|
| **Backend Admin** | .NET Core MVC (C#) | 10.0 | Gestión de flujo documental |
| **Backend Público** | PHP | 8.x | Portal de consulta pública |
| **Backend Búsqueda** | Node.js + TypeScript | 20 LTS | Indexación y búsqueda full-text |
| **BD Principal** | SQL Server | 2022 | Documentos, usuarios, flujo de trabajo |
| **BD Pública** | PostgreSQL | 16 | Documentos vigentes, logs de consulta |
| **BD Búsqueda** | MongoDB | 7 | Índice de texto completo (full-text search) |
| **Contenedores** | Docker + Docker Compose | Latest | Orquestación de servicios |
| **Frontend** | Bootstrap 5 + Bootstrap Icons | 5.3 | UI responsiva |

---

## Requisitos Previos

Solo necesitas una cosa instalada:

- **Docker Desktop** — incluye Docker Engine y Docker Compose.

> Atención: Asegúrate de que Docker Desktop esté abierto y corriendo antes de instalar.

---

## Instalación Rápida

### En Windows:
```
1. Clona o descarga el repositorio
2. Abre la carpeta del proyecto
3. Haz doble clic en: install.bat
4. Espera a que termine (~3-5 minutos)
5. Abre tu navegador en: http://localhost:5000
```

### En Linux / macOS:
```bash
git clone https://github.com/Rositapro/qualitydoc-polyglot.git
cd qualitydoc-polyglot
chmod +x install.sh
./install.sh
```

### Para detener el sistema:
- **Windows:** doble clic en `stop.bat`
- **Linux/Mac:** `./stop.sh`

---

## Instalación Manual

Si prefieres hacerlo paso a paso desde la terminal:

```bash
# 1. Clona el repositorio
git clone https://github.com/Rositapro/qualitydoc-polyglot.git
cd qualitydoc-polyglot

# 2. Entra a la carpeta de Docker
cd docker

# 3. Construye y levanta todos los contenedores de los microservicios
docker-compose -f docker-compose.dotnet.yml up -d --build
docker-compose -f docker-compose.php.yml up -d --build
docker-compose -f docker-compose.node.yml up -d --build
```

---

## Acceso y Credenciales

### Portal Principal — .NET (Administración)
| URL | http://localhost:5000 |
|-----|----------------------|

**Usuarios de prueba (Base de Datos SQL Server):**

| Rol | Nombre de Usuario | Correo | Contraseña |
|-----|-------------------|--------|------------|
| SuperAdmin | superadmin | `superadmin@qualitydoc.com` | `Document2026!` |
| Administrador | admin_empresa | `admin_empresa@qualitydoc.com` | `Document2026!` |

---

### Portal Público — PHP (Consulta de Documentos)
| URL | http://localhost:8080 |
|-----|----------------------|

| Usuario | Contraseña |
|---------|------------|
| `usuarioConsulta` | `Rosa123` |

---

### Servicio de Búsqueda — Node.js
| URL | http://localhost:3000 |
|-----|----------------------|

La búsqueda inteligente también está integrada en el portal .NET en `/SmartSearch`.

---

### Bases de Datos (conexión directa)

| Base de Datos | Host | Puerto | Usuario | Contraseña | BD |
|--------------|------|--------|---------|------------|----|
| SQL Server | localhost | 1433 | `sa` | `Document2026!` | `DocumentManagement` |
| PostgreSQL | localhost | 5432 | `Rosalinda` | `Rosa123` | `gestionconsulta` |
| MongoDB | localhost | 27017 | _(sin auth)_ | — | `qualitydoc` |

---

## Módulos del Sistema

### 1. Portal de Administración (.NET Core)

El módulo central del sistema. Gestiona el flujo completo de documentos e implementa los controles visuales.

**Funcionalidades:**
- Registro e inicio de sesión con roles.
- Creación de documentos con carga de archivos PDF, DOCX y XLSX.
- Lector de texto completo de archivos de Microsoft Excel (.xlsx) integrado en el flujo de aprobación.
- Flujo de trabajo: Autor -> Revisor -> Autorizador.
- Sistema de rechazo con notas y comentarios.
- Control de versiones automático.
- Visualización de la bienvenida del sistema mostrando el Nombre de Usuario en lugar del correo electrónico.
- Badges de perfil y gestión de usuarios personalizados con colores únicos por rol (SuperAdmin = Rojo Oscuro, Admin = Azul, Revisor = Púrpura, Autor = Verde, Aprobador = Naranja, Lector = Gris).
- Búsqueda inteligente en MongoDB (SmartSearch).
- Panel de administración (gestión de usuarios, empresas, normas ISO).
- Descarga de documentos físicos.
- Gestión multi-empresa (cada empresa solo ve sus documentos correspondientes).

**Controladores principales:**

| Controlador | Responsabilidad |
|------------|----------------|
| `AuthController` | Login / Logout e inyección de Claims del usuario |
| `AuthorController` | Crear y editar documentos, enviar a revisión |
| `ReviewerController` | Revisar documentos, aprobar o rechazar |
| `ApproverController` | Autorizar documentos, activar versión vigente y ejecutar extracción de texto |
| `AdminController` | Gestión de usuarios, empresas, catálogos ISO |
| `SmartSearchController` | Búsqueda full-text en MongoDB |

---

### 2. Portal Público (PHP + PostgreSQL)

Portal de solo lectura para operarios y personal de planta.

**Funcionalidades:**
- Inicio de sesión con credenciales de consulta.
- Listado de documentos vigentes agrupados por código.
- Historial de versiones obsoletas integrado en un panel desplegable de historial.
- Soporte para visualizar (visor de PDFs, Word y Excel) y descargar versiones históricas/obsoletas que han sido reemplazadas, facilitando auditorías históricas.
- Descarga de documentos vigentes.
- Registro automático de logs de consulta (quién consultó qué y cuándo).
- Reportes de auditoría.

---

### 3. Búsqueda Inteligente (Node.js + MongoDB)

Motor de búsqueda full-text integrado en el portal .NET y accesible vía web.

**Funcionalidades:**
- Indexación automática del contenido completo de los archivos (PDF, DOCX y XLSX).
- Búsqueda por título, contenido, autor, norma ISO.
- Fallback con búsqueda por expresiones regulares (Regex).
- Consulta interactiva del historial de versiones obsoletas de cada documento.
- Soporte para visualizar el contenido de texto indexado y los metadatos JSON completos de cualquier versión histórica u obsoleta de forma directa en la interfaz de búsqueda.
- Separación por empresa (cada empresa solo busca en sus documentos).
- Vista previa del texto extraído del archivo.

---

## Flujo de Versiones de Documentos

El sistema aplica versionado automático siguiendo esta lógica:

```
AUTOR crea documento
        │
        ▼
   Versión: 0.1  ──── Envía a revisión
        │
   REVISOR revisa
        ├── Rechaza ──► AUTOR corrige ──► Versión: 0.2 ──► Revisor de nuevo ──► 0.3...
        │
        └── Aprueba ──► AUTORIZADOR revisa
                             ├── Rechaza ──► vuelve al autor (sigue siendo 0.x)
                             │
                             └── Autoriza ──► Versión: 1.0 (VIGENTE)
                                                │
                                           AUTOR edita
                                                │
                                           Versión: 1.1 ──► Revisor ──► Autorizador
                                                                              │
                                                                         Autoriza ──► 2.0
```

**Regla matemática de versiones:**
- Durante revisión: `X.1`, `X.2`, `X.3`... (incremento decimal)
- Al autorizar: `Math.Floor(versión_actual) + 1.0` -> número entero nuevo

---

## Roles de Usuario

| Rol | Permisos |
|-----|---------|
| **Administrador** | Gestiona usuarios, empresas, normas ISO. Ve todos los documentos. |
| **Autor** | Crea documentos, los edita y los envía a revisión. |
| **Revisor** | Revisa documentos del Autor. Puede aprobar (enviar al autorizador) o rechazar (devolver al autor). |
| **Autorizador / Aprobador** | Aprueba o rechaza documentos revisados. Al aprobar, activa la nueva versión vigente. |
| **Consulta (PHP)** | Solo puede ver y descargar documentos vigentes y consultar el historial de obsoletos. No puede modificar nada. |

---

## Estructura del Repositorio

```
QualityDoc-Polyglot/
│
├── install.bat          ← Script de instalación (Windows)
├── install.sh           ← Script de instalación (Linux/macOS)
├── stop.bat             ← Detener servicios (Windows)
├── stop.sh              ← Detener servicios (Linux/macOS)
├── README.md            ← Este archivo
│
├── docker/
│   ├── docker-compose.dotnet.yml  ← Microservicio 1: .NET + SQL Server
│   ├── docker-compose.php.yml     ← Microservicio 2: PHP + PostgreSQL
│   ├── docker-compose.node.yml    ← Microservicio 3: Node + MongoDB
│   ├── node.Dockerfile            ← Imagen del servicio Node.js
│   └── php.Dockerfile             ← Imagen del servicio PHP
│
├── db/
│   ├── postgres/
│   │   └── init.sql        ← Esquema y datos iniciales de PostgreSQL
│   ├── sqlserver/
│   │   └── init.sql        ← Esquema y datos iniciales de SQL Server
│   └── mongodb/
│       └── init-mongo.js   ← Índices y datos semilla de MongoDB
│
└── src/
    ├── dotnet-core/        ← Portal de Administración (.NET Core 10)
    │   └── src/modulo-central/
    │       ├── QualityDocc.Domain/        ← Entidades y modelos
    │       ├── QualityDocc.Application/   ← Interfaces y lógica de negocio
    │       ├── QualityDocc.Infrastructure/← Repositorios y servicios externos
    │       ├── QualityDocc.MVC/           ← Controladores, vistas, configuración
    │       └── QualityDocc.Tests/         ← Pruebas unitarias (13 tests completados)
    │
    ├── php-app/            ← Portal Público (PHP 8)
    │   ├── index.php
    │   ├── login.php
    │   ├── documentos.php  ← Lista documentos vigentes + historial
    │   └── reportes.php    ← Logs de auditoría
    │
    └── node-service/       ← Servicio de Búsqueda (Node.js + TypeScript)
        └── src/
            ├── controllers/
            │   └── document.controller.ts
            └── app.ts
```

---

## Base de Datos

### SQL Server — Tablas principales

| Tabla | Descripción |
|-------|------------|
| `User` | Usuarios del sistema con roles y empresa |
| `Role` | Roles: Admin, Author, Reviewer, Approver |
| `Company` | Empresas registradas (multi-tenant) |
| `Document` | Documentos con estado de flujo de trabajo |
| `DocumentVersion` | Versiones de cada documento (historial completo) |
| `Iso` | Normas ISO disponibles (ISO 9001, IATF 16949, etc.) |

### PostgreSQL — Tablas principales

| Tabla | Descripción |
|-------|------------|
| `DocumentosVigentes` | Copia de documentos en estado Vigente para consulta |
| `LogsConsultas` | Registro de quién descargó qué documento y cuándo |

### MongoDB — Colección documents

```json
{
  "title": "Manual de Calidad",
  "fileExtension": "pdf",
  "body": "...texto completo extraído del archivo...",
  "empresaid": 1001,
  "status": "Vigente",   // o "Obsoleto"
  "metadata": {
    "author": "autor1",
    "version": "2.0",
    "iso": "ISO 9001",
    "documentId": 5,
    "versionId": 12
  },
  "tags": ["document", "ISO 9001"]
}
```

---

## API Endpoints

### Node.js (Puerto 3000)

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/login` | Autenticación de usuario |
| `GET` | `/api/search?q=texto&empresaId=1` | Búsqueda full-text |

### .NET (Puerto 5000) — Rutas principales

| Ruta | Descripción |
|------|------------|
| `/Auth/Login` | Inicio de sesión |
| `/Author/Index` | Panel del Autor |
| `/Author/Create` | Crear nuevo documento |
| `/Author/Search` | Buscar documentos propios |
| `/Reviewer/Index` | Panel del Revisor |
| `/Approver/Index` | Panel del Autorizador |
| `/Admin/Index` | Panel de Administración |
| `/SmartSearch` | Búsqueda inteligente en MongoDB |

---

## Pruebas

El proyecto incluye 13 pruebas unitarias que se ejecutan automáticamente al construir el contenedor Docker:

```
Passed! - Failed: 0, Passed: 13, Skipped: 0, Total: 13
```

Para ejecutar las pruebas manualmente:

```bash
docker exec admin-dotnet dotnet test
```

---

## Comandos Útiles

```bash
# Ver estado de todos los contenedores
docker compose -f docker-compose.dotnet.yml ps
docker compose -f docker-compose.php.yml ps
docker compose -f docker-compose.node.yml ps

# Ver logs de un servicio específico
docker logs admin-dotnet
docker logs web-php
docker logs search-service
docker logs db-sqlserver
docker logs db-postgres
docker logs db-mongodb

# Reconstruir un solo servicio
docker compose -f docker-compose.dotnet.yml up -d --build admin-dotnet

# Detener sin borrar datos
docker compose -f docker-compose.dotnet.yml stop
docker compose -f docker-compose.php.yml stop
docker compose -f docker-compose.node.yml stop

# Detener y eliminar contenedores
docker compose -f docker-compose.dotnet.yml down
docker compose -f docker-compose.php.yml down
docker compose -f docker-compose.node.yml down
```

---

## Autoras

**Odeth Peña y Rosalinda Cedillo** — Instituto Tecnológico Superior de Monclova  
6to Semestre · Ingeniería informática  
Proyecto Integrador — 2026

---

Nota: Este proyecto fue desarrollado como proyecto integrador académico. El sistema implementa conceptos de arquitectura polyglot, microservicios, contenedores Docker, control de versiones y gestión documental basada en normativas ISO.
