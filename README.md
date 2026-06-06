# QualityDoc-Polyglot - Sistema Integral de Gestión de Documentos de Calidad

Este proyecto representa la culminación integradora de las materias de **Taller de Base de Datos**, **Desarrollo de Aplicaciones Web** y **Administración de Tecnologías de Virtualización (Contenedores)**. Es una plataforma multi-stack y multi-empresa para la trazabilidad y control del ciclo de vida de documentos de calidad (bajo normativas ISO/ANSI).

---

## 1. Arquitectura Técnica (Polyglot Stack)

El sistema está diseñado bajo una arquitectura de microservicios y bases de datos políglotas (SQL y NoSQL) orquestadas mediante Docker:

1. **Módulo Central (Admin):** Desarrollado en **.NET Core 10 MVC (C#)** y persistido en **SQL Server**. Gestiona la lógica pesada de aprobaciones de documentos y flujos de revisión.
2. **Módulo de Consulta Pública:** Desarrollado en **PHP 8** y persistido en **PostgreSQL**. Permite a los operarios en planta consultar documentos vigentes y registra logs de auditoría.
3. **Módulo de Indexación y Búsqueda (Búsqueda Inteligente):** Desarrollado en **Node.js (TypeScript / Express)** y persistido en **MongoDB**. Permite búsquedas rápidas con índices de texto completo sobre metadatos dinámicos.

```mermaid
graph TD
    User([Operario / Admin]) -->|Puerto 8080| PHP[web-php: PHP 8]
    User -->|Puerto 3000| Node[search-service: Node.js/TS]
    
    subgraph Red Docker (qualitydoc-net)
        PHP -->|Logs de Consulta| Postgres[(db-postgres: PostgreSQL 16)]
        Node -->|Indexación y Búsqueda| Mongo[(db-mongodb: MongoDB)]
        
        %% Módulo de .NET y SQL Server comentado
        DotNet[admin-dotnet: .NET Core 10] -.->|Aprobaciones| SQLServer[(db-sqlserver: SQL Server)]
        DotNet -.->|Notifica indexación| Node
    end
```

---

## 2. Requisitos Previos

* [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y en ejecución en tu computadora.

---

## 3. Instrucciones de Instalación y Despliegue

Sigue estos pasos para levantar todo el entorno unificado (Bases de datos y aplicaciones):

1. Abre una terminal y colócate en la carpeta `docker` del repositorio:
   ```bash
   cd docker
   ```
2. Ejecuta el comando de Docker Compose para compilar e iniciar todos los contenedores en segundo plano:
   ```bash
   docker-compose up -d --build
   ```
3. Docker descargará las imágenes oficiales, compilará el código de Node y PHP local, inicializará las bases de datos con sus respectivos esquemas y cargará los datos semilla de prueba automáticamente.

---

## 4. Direcciones de Acceso y Credenciales por Defecto

### Portales Web:
* **Buscador de Calidad (Node.js/MongoDB):** [http://localhost:3000](http://localhost:3000)
  * *Credenciales (Simulación):* Puedes iniciar sesión con cualquier correo y contraseña no vacíos.
  * *Aislamiento por Empresa:*
    * Para entrar como **Empresa 1** y ver sus documentos exclusivos, inicia sesión con un correo que no contenga el número 2 (ej: `admin@empresa1.com`).
    * Para entrar como **Empresa 2**, usa un correo que contenga el número 2 (ej: `admin@empresa2.com`).
* **Consulta de Documentos (PHP/PostgreSQL):** [http://localhost:8080](http://localhost:8080)
  * *Credenciales:* `usuarioConsulta` / `Rosa123`

### Bases de Datos:
* **PostgreSQL:**
  * *Host:* `localhost` (dentro de Docker: `db-postgres`)
  * *Puerto:* `5432`
  * *Usuario:* `Rosalinda`
  * *Contraseña:* `Rosa123`
  * *Base de Datos:* `gestionconsulta`
* **MongoDB:**
  * *Host:* `localhost` (dentro de Docker: `db-mongodb`)
  * *Puerto:* `27017`
  * *Base de Datos:* `qualitydoc`

---

## 5. Inicialización y Datos Semilla

Al levantar los contenedores por primera vez, se ejecutan de manera automática los siguientes scripts de la carpeta `/db`:
* **`db/postgres/init.sql`:** Crea las tablas `DocumentosVigentes` y `LogsConsultas` y carga dos registros iniciales de ejemplo.
* **`db/mongodb/init-mongo.js`:** Genera un índice de texto compuesto (`DocumentTextIndex`) en los campos de título, etiquetas y contenido de texto de Mongoose con diferentes pesos, e inserta 4 documentos semilla segmentados entre la Empresa 1 y la Empresa 2.

---

## 6. Guía de Integración para Odeth (.NET Core & SQL Server)

Para integrar tu módulo en el repositorio unificado, sigue estos pasos:

1. Coloca los archivos de tu proyecto de **.NET Core 10** dentro de la carpeta:
   `src/dotnet-core/`
2. Guarda el script de creación de tu base de datos de **SQL Server** en:
   `db/sqlserver/init.sql`
3. Abre el archivo `/docker/docker-compose.yml` y descomenta las secciones correspondientes a los servicios `db-sqlserver` y `admin-dotnet`, así como el volumen `mssql_data` al final del archivo.
4. Asegúrate de configurar la cadena de conexión de tu aplicación de .NET apuntando al servidor `db-sqlserver` en el puerto `1433`.
5. Ejecuta `docker-compose up -d --build` para compilar y probar la integración completa.
