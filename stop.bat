@echo off
echo.
echo  Deteniendo todos los microservicios de QualityDoc-Polyglot...
cd /d "%~dp0docker"
docker-compose -f docker-compose.dotnet.yml down
docker-compose -f docker-compose.php.yml down
docker-compose -f docker-compose.node.yml down
echo.
echo  Todos los servicios han sido detenidos.
pause
