# QualityDoc-Polyglot

> **Sistema Integral de Gestión de Documentos de Calidad**  
> Proyecto integrador — Taller de BD · Desarrollo Web · Administración de Contenedores  
> Instituto Tecnológico Superior de Monclova · 6to Semestre

---

## Tabla de Contenidos

1. [Descripcion del Proyecto](#descripcion-del-proyecto)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Tecnologias Utilizadas](#tecnologias-utilizadas)
4. [Requisitos Previos](#requisitos-previos)
5. [Instalacion Rapida](#instalacion-rapida)
6. [Instalacion Manual](#instalacion-manual)
7. [Acceso y Credenciales](#acceso-y-credenciales)
8. [Modulos del Sistema](#modulos-del-sistema)
9. [Flujo de Versiones de Documentos](#flujo-de-versiones-de-documentos)
10. [Roles de Usuario](#roles-de-usuario)
11. [Estructura del Repositorio](#estructura-del-repositorio)
12. [Base de Datos](#base-de-datos)
13. [API Endpoints](#api-endpoints)
14. [Comandos Utiles](#comandos-utiles)

---

## Descripcion del Proyecto

**QualityDoc-Polyglot** es una plataforma multi-empresa para el control del **ciclo de vida de documentos de calidad** bajo normativas como **ISO 9001** e **IATF 16949**.

El sistema permite que las empresas gestionen sus documentos internos (procedimientos, manuales, registros) a través de un flujo de trabajo con tres roles: **Autor → Revisor → Autorizador**, con control de versiones automático.

Es un proyecto **polyglot** (multilenguaje y multi-base de datos) que integra tres stacks tecnológicos distintos en un solo entorno Docker.

---

## Arquitectura del Sistema

```
+----------------------------------------------------------+
|                    USUARIO / NAVEGADOR                    |
+----------+----------------+----------------+-------------+
           |                |                |
     Puerto 5000      Puerto 8080      Puerto 3000
           |                |                |
  +--------+--------+ +-----+------+ +-------+----------+
  |  Portal Admin   | | Portal PHP | | SmartSearch      |
  |  .NET Core 10   | |   PHP 8    | | Node.js + TS     |
  |   (C# MVC)      | |            | | (Express)        |
  +--------+--------+ +-----+------+ +-------+----------+
           |                |                |
    +------+------+  +------+------+  +------+------+
    |  SQL Server |  | PostgreSQL  |  |   MongoDB   |
    |  (Puerto    |  |  (Puerto    |  |  (Puerto    |
    |   1433)     |  |   5432)     |  |   27017)    |
    +-------------+  +-------------+  +-------------+
```

### Red interna Docker: `qualitydoc-net`

Todos los servicios se comunican entre si dentro de la red privada de Docker. Solo los puertos necesarios estan expuestos al host.

---

## Tecnologias Utilizadas

| Capa | Tecnologia | Version | Proposito |
|------|-----------|---------|-----------|
| **Backend Admin** | .NET Core MVC (C#) | 10.0 | Gestion de flujo documental |
| **Backend Publico** | PHP | 8.x | Portal de consulta publica |
| **Backend Busqueda** | Node.js + TypeScript | 20 LTS | Indexacion y busqueda full-text |
| **BD Principal** | SQL Server | 2022 | Documentos, usuarios, flujo de trabajo |
| **BD Publica** | PostgreSQL | 16 | Documentos vigentes, logs de consulta |
| **BD Busqueda** | MongoDB | 7 | Indice de texto completo (full-text search) |
| **Contenedores** | Docker + Docker Compose | Latest | Orquestacion de servicios |
| **Frontend** | Bootstrap 5 + Bootstrap Icons | 5.3 | UI responsiva |

---

## Requisitos Previos

Solo necesitas **una** cosa instalada:

- **[Docker Desktop](https://www.docker.com/products/docker-desktop/)** — incluye Docker Engine y Docker Compose.

> Asegurate de que Docker Desktop este **abierto y corriendo** antes de instalar.

---

## Instalacion Rapida

### En Windows:
```
1. Clona o descarga el repositorio
2. Abre la carpeta del proyecto
3. Haz doble clic en:  install.bat
4. Espera a que termine (~3-5 minutos)
5. Abre tu navegador en:  http://localhost:5000
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

## Instalacion Manual

Si prefieres hacerlo paso a paso desde la terminal:

```bash
# 1. Clona el repositorio
git clone https://github.com/Rositapro/qualitydoc-polyglot.git
cd qualitydoc-polyglot

# 2. Entra a la carpeta de Docker
cd docker

# 3. Construye y levanta todos los contenedores
docker-compose up -d --build

# 4. Verifica que todos los contenedores esten corriendo
docker-compose ps
```

---

## Acceso y Credenciales

### Portal Principal — .NET (Administracion)
| URL | http://localhost:5000 |
|-----|----------------------|

**Usuarios de prueba:**

| Rol | Correo | Contrasena |
|-----|--------|------------|
| Administrador | `admin@empresa.com` | `Admin123!` |
| Autor | `autor1@empresa.com` | `Autor123!` |
| Revisor | `revisor1@empresa.com` | `Revisor123!` |
| Autorizador | `autorizador1@empresa.com` | `Auth123!` |

---

### Portal Publico — PHP (Consulta de Documentos)
| URL | http://localhost:8080 |
|-----|----------------------|

| Usuario | Contrasena |
|---------|------------|
| `usuarioConsulta` | `Rosa123` |

---

### Servicio de Busqueda — Node.js
| URL | http://localhost:3000 |
|-----|----------------------|

> La busqueda inteligente tambien esta integrada en el portal .NET en `/SmartSearch`.

---

### Bases de Datos (conexion directa)

| Base de Datos | Host | Puerto | Usuario | Contrasena | BD |
|--------------|------|--------|---------|------------|-----|
| SQL Server | localhost | 1433 | `sa` | `YourStrong@Passw0rd` | `QualityDocDB` |
| PostgreSQL | localhost | 5432 | `Rosalinda` | `Rosa123` | `gestionconsulta` |
| MongoDB | localhost | 27017 | (sin autenticacion) | — | `qualitydoc` |

---

## Modulos del Sistema

### 1. Portal de Administracion (.NET Core)

El modulo central del sistema. Gestiona el flujo completo de documentos.

**Funcionalidades:**
- Registro e inicio de sesion con roles
- Creacion de documentos con carga de archivos PDF
- Flujo de trabajo: Autor → Revisor → Autorizador
- Sistema de rechazo con notas/comentarios
- Control de versiones automatico
- Historial de versiones obsoletas (desplegable por documento)
- Busqueda inteligente en MongoDB (SmartSearch)
- Panel de administracion (gestion de usuarios, empresas, normas ISO)
- Descarga de documentos PDF
- Gestion multi-empresa (cada empresa solo ve sus documentos)

**Controladores principales:**

| Controlador | Responsabilidad |
|------------|----------------|
| `AuthController` | Login / Logout |
| `AuthorController` | Crear y editar documentos, enviar a revision |
| `ReviewerController` | Revisar documentos, aprobar o rechazar |
| `ApproverController` | Autorizar documentos, activar version vigente |
| `AdminController` | Gestion de usuarios, empresas, catalogos ISO |
| `SmartSearchController` | Busqueda full-text en MongoDB |

---

### 2. Portal Publico (PHP + PostgreSQL)

Portal de solo lectura para operarios y personal de planta.

**Funcionalidades:**
- Inicio de sesion con credenciales de consulta
- Listado de documentos vigentes agrupados por codigo
- Historial de versiones obsoletas (pestana desplegable)
- Descarga de documentos PDF vigentes
- Registro automatico de logs de consulta (quien consulto que y cuando)
- Reportes de auditoria

---

### 3. Busqueda Inteligente (Node.js + MongoDB)

Motor de busqueda full-text integrado en el portal .NET.

**Funcionalidades:**
- Indexacion automatica del contenido completo de los PDFs
- Busqueda por titulo, contenido, autor, norma ISO
- Fallback con busqueda por expresiones regulares (Regex)
- Historial de versiones obsoletas por documento
- Separacion por empresa (cada empresa solo busca en sus documentos)
- Vista previa del texto extraido del PDF

---

## Flujo de Versiones de Documentos

El sistema aplica versionado automatico siguiendo esta logica:

```
AUTOR crea documento
        |
        v
   Version: 0.1  ---- Envia a revision
        |
   REVISOR revisa
        +-- Rechaza --> AUTOR corrige --> Version: 0.2 --> Revisor de nuevo --> 0.3...
        |
        +-- Aprueba --> AUTORIZADOR revisa
                             +-- Rechaza --> vuelve al autor (sigue siendo 0.x)
                             |
                             +-- Autoriza --> Version: 1.0  (VIGENTE)
                                                |
                                           AUTOR edita
                                                |
                                           Version: 1.1 --> Revisor --> Autorizador
                                                                              |
                                                                         Autoriza --> 2.0
```

**Regla matematica de versiones:**
- Durante revision: `X.1`, `X.2`, `X.3`... (incremento decimal)
- Al autorizar: `Math.Floor(version_actual) + 1.0` → numero entero nuevo

---

## Roles de Usuario

| Rol | Permisos |
|-----|---------|
| **Administrador** | Gestiona usuarios, empresas, normas ISO. Ve todos los documentos. |
| **Autor** | Crea documentos, los edita y los envia a revision. |
| **Revisor** | Revisa documentos del Autor. Puede aprobar (enviar al autorizador) o rechazar (devolver al autor). |
| **Autorizador** | Aprueba o rechaza documentos revisados. Al aprobar, activa la nueva version vigente. |
| **Consulta (PHP)** | Solo puede ver y descargar documentos vigentes. No puede modificar nada. |

---

## Estructura del Repositorio

```
QualityDoc-Polyglot/
|
+-- install.bat          <- Script de instalacion (Windows)
+-- install.sh           <- Script de instalacion (Linux/macOS)
+-- stop.bat             <- Detener servicios (Windows)
+-- stop.sh              <- Detener servicios (Linux/macOS)
+-- README.md            <- Este archivo
|
+-- docker/
|   +-- docker-compose.yml  <- Orquestacion de todos los servicios
|   +-- node.Dockerfile     <- Imagen del servicio Node.js
|   +-- php.Dockerfile      <- Imagen del servicio PHP
|
+-- db/
|   +-- postgres/
|   |   +-- init.sql        <- Esquema y datos iniciales de PostgreSQL
|   +-- sqlserver/
|   |   +-- init.sql        <- Esquema y datos iniciales de SQL Server
|   +-- mongodb/
|       +-- init-mongo.js   <- Indices y datos semilla de MongoDB
|
+-- src/
    +-- dotnet-core/        <- Portal de Administracion (.NET Core 10)
    |   +-- src/modulo-central/
    |       +-- QualityDocc.Domain/        <- Entidades y modelos
    |       +-- QualityDocc.Application/   <- Interfaces y logica de negocio
    |       +-- QualityDocc.Infrastructure/<- Repositorios y servicios externos
    |       +-- QualityDocc.MVC/           <- Controladores, vistas, configuracion
    |       +-- QualityDocc.Tests/         <- Pruebas unitarias (13 tests)
    |
    +-- php-app/            <- Portal Publico (PHP 8)
    |   +-- index.php
    |   +-- login.php
    |   +-- documentos.php  <- Lista documentos vigentes + historial
    |   +-- reportes.php    <- Logs de auditoria
    |
    +-- node-service/       <- Servicio de Busqueda (Node.js + TypeScript)
        +-- src/
            +-- controllers/
            |   +-- document.controller.ts
            +-- app.ts
```

---

## Base de Datos

### SQL Server — Tablas principales

| Tabla | Descripcion |
|-------|------------|
| `User` | Usuarios del sistema con roles y empresa |
| `Role` | Roles: Admin, Author, Reviewer, Approver |
| `Company` | Empresas registradas (multi-tenant) |
| `Document` | Documentos con estado de flujo de trabajo |
| `DocumentVersion` | Versiones de cada documento (historial completo) |
| `Iso` | Normas ISO disponibles (ISO 9001, IATF 16949, etc.) |

### PostgreSQL — Tablas principales

| Tabla | Descripcion |
|-------|------------|
| `DocumentosVigentes` | Copia de documentos en estado Vigente para consulta |
| `LogsConsultas` | Registro de quien descargo que documento y cuando |

### MongoDB — Coleccion `documents`

```json
{
  "title": "Manual de Calidad",
  "fileExtension": "pdf",
  "body": "...texto completo extraido del PDF...",
  "empresaid": 1001,
  "status": "Vigente",
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

| Metodo | Ruta | Descripcion |
|--------|------|-------------|
| `POST` | `/api/login` | Autenticacion de usuario |
| `GET` | `/api/search?q=texto&empresaId=1` | Busqueda full-text |

### .NET (Puerto 5000) — Rutas principales

| Ruta | Descripcion |
|------|------------|
| `/Auth/Login` | Inicio de sesion |
| `/Author/Index` | Panel del Autor |
| `/Author/Create` | Crear nuevo documento |
| `/Author/Search` | Buscar documentos propios |
| `/Reviewer/Index` | Panel del Revisor |
| `/Approver/Index` | Panel del Autorizador |
| `/Admin/Index` | Panel de Administracion |
| `/SmartSearch` | Busqueda inteligente en MongoDB |

---

## Pruebas

El proyecto incluye **13 pruebas unitarias** que se ejecutan automaticamente al construir el contenedor Docker:

```
Passed!  - Failed: 0, Passed: 13, Skipped: 0, Total: 13
```

Para ejecutar las pruebas manualmente:

```bash
docker exec admin-dotnet dotnet test
```

---

## Comandos Utiles

```bash
# Ver estado de todos los contenedores
docker-compose ps

# Ver logs de un servicio especifico
docker logs admin-dotnet
docker logs web-php
docker logs search-service
docker logs db-sqlserver
docker logs db-postgres
docker logs db-mongodb

# Reconstruir un solo servicio
docker-compose up -d --build admin-dotnet

# Detener sin borrar datos
docker-compose stop

# Detener y eliminar contenedores (mantiene volumenes/datos)
docker-compose down

# Eliminar TODO incluyendo datos (reset completo)
docker-compose down -v
```

---

## Autora

**Rosalinda** — Instituto Tecnologico Superior de Monclova  
6to Semestre · Ingenieria en Sistemas Computacionales  
Proyecto Integrador — 2026

---

> **Nota:** Este proyecto fue desarrollado como proyecto integrador academico. El sistema implementa conceptos de arquitectura polyglot, microservicios, contenedores Docker, control de versiones y gestion documental basada en normativas ISO.
