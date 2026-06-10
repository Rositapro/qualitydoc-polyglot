@echo off
echo.
echo  Deteniendo todos los servicios de QualityDoc-Polyglot...
cd /d "%~dp0docker"
docker-compose down
echo.
echo  Todos los servicios han sido detenidos.
pause
