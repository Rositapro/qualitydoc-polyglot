@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul

echo.
echo  ╔══════════════════════════════════════════════════════════╗
echo  ║         QualityDoc-Polyglot - Script de Instalación      ║
echo  ║      Sistema de Gestión Documental (ISO 9001 / IATF)     ║
echo  ╚══════════════════════════════════════════════════════════╝
echo.

:: ─────────────────────────────────────────────
:: 1. Verificar que Docker esté instalado
:: ─────────────────────────────────────────────
echo [1/5] Verificando Docker...
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo  ERROR: Docker no esta instalado o no esta en el PATH.
    echo  Descarga Docker Desktop desde: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)
echo  OK - Docker encontrado.

:: ─────────────────────────────────────────────
:: 2. Verificar que Docker Compose esté instalado
:: ─────────────────────────────────────────────
echo.
echo [2/5] Verificando Docker Compose...
docker compose version >nul 2>&1 || docker-compose --version >nul 2>&1
if !errorlevel! neq 0 (
    echo  ERROR: Docker Compose no esta instalado.
    echo  Instala Docker Desktop (incluye Docker Compose).
    pause
    exit /b 1
)
echo  OK - Docker Compose encontrado.

:: ─────────────────────────────────────────────
:: 3. Verificar que Docker esté corriendo
:: ─────────────────────────────────────────────
echo.
echo [3/5] Verificando que Docker este en ejecucion...
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo  ERROR: Docker no esta corriendo. Por favor abre Docker Desktop primero.
    pause
    exit /b 1
)
echo  OK - Docker esta activo.

:: ─────────────────────────────────────────────
:: 4. Construir las imágenes de Docker
:: ─────────────────────────────────────────────
echo.
echo [4/5] Construyendo imagenes Docker (esto puede tardar varios minutos)...
echo       Por favor espere...
echo.
cd /d "%~dp0docker"
echo  Microservicio 1: .NET + SQL Server...
docker-compose -f docker-compose.dotnet.yml build --no-cache
echo  Microservicio 2: PHP + PostgreSQL...
docker-compose -f docker-compose.php.yml build --no-cache
echo  Microservicio 3: Node.js + MongoDB...
docker-compose -f docker-compose.node.yml build --no-cache
if %errorlevel% neq 0 (
    echo.
    echo  ERROR: Fallo la construccion de las imagenes.
    echo  Revisa los logs anteriores para mas detalles.
    pause
    exit /b 1
)
echo.
echo  OK - Imagenes construidas correctamente.

:: ─────────────────────────────────────────────
:: 5. Levantar todos los servicios
:: ─────────────────────────────────────────────
echo.
echo [5/5] Levantando todos los servicios...
docker-compose -f docker-compose.dotnet.yml up -d
docker-compose -f docker-compose.php.yml up -d
docker-compose -f docker-compose.node.yml up -d
if %errorlevel% neq 0 (
    echo.
    echo  ERROR: No se pudieron levantar los servicios.
    pause
    exit /b 1
)

:: ─────────────────────────────────────────────
:: Esperar a que los servicios estén listos
:: ─────────────────────────────────────────────
echo.
echo  Esperando 15 segundos para que los servicios inicien...
timeout /t 15 /nobreak >nul

:: ─────────────────────────────────────────────
:: Mostrar estado final
:: ─────────────────────────────────────────────
echo.
echo  ╔══════════════════════════════════════════════════════════╗
echo  ║               Instalacion completada! ✓                  ║
echo  ╚══════════════════════════════════════════════════════════╝
echo.
echo  Servicios disponibles:
echo.
echo    Portal Principal (.NET)  →  http://localhost:5000
echo    Portal PHP               →  http://localhost:8080
echo    MongoDB                  →  mongodb://localhost:27017
echo    SQL Server               →  localhost:1433
echo    Servicio Node.js         →  http://localhost:3000
echo.
echo  Para detener todos los servicios, ejecuta: stop.bat
echo.

:: Preguntar si abrir el navegador
set /p abrir="Deseas abrir el portal en el navegador? (s/n): "
if /i "%abrir%"=="s" (
    start http://localhost:5000
)

pause
