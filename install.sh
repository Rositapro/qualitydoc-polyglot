#!/bin/bash
# ─────────────────────────────────────────────────────────────
#  QualityDoc-Polyglot - Script de Instalación (Linux / macOS)
# ─────────────────────────────────────────────────────────────

set -e  # Detener si ocurre cualquier error

# Colores para la terminal
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # Sin color

echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         QualityDoc-Polyglot - Script de Instalación      ║${NC}"
echo -e "${BLUE}║      Sistema de Gestión Documental (ISO 9001 / IATF)     ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""

# ─────────────────────────────────────────────
# 1. Verificar que Docker esté instalado
# ─────────────────────────────────────────────
echo -e "${YELLOW}[1/5]${NC} Verificando Docker..."
if ! command -v docker &> /dev/null; then
    echo -e "${RED} ERROR: Docker no está instalado.${NC}"
    echo "  Descarga Docker desde: https://www.docker.com/products/docker-desktop"
    exit 1
fi
echo -e "${GREEN} ✓ Docker encontrado: $(docker --version)${NC}"

# ─────────────────────────────────────────────
# 2. Verificar Docker Compose
# ─────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[2/5]${NC} Verificando Docker Compose..."
if ! (docker compose version &> /dev/null || docker-compose --version &> /dev/null); then
    echo -e "${RED} ERROR: Docker Compose no está instalado.${NC}"
    exit 1
fi
echo -e "${GREEN} ✓ Docker Compose encontrado.${NC}"

# ─────────────────────────────────────────────
# 3. Verificar que Docker esté corriendo
# ─────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[3/5]${NC} Verificando que Docker esté en ejecución..."
if ! docker info &> /dev/null; then
    echo -e "${RED} ERROR: Docker no está corriendo. Por favor inicia Docker Desktop primero.${NC}"
    exit 1
fi
echo -e "${GREEN} ✓ Docker está activo.${NC}"

# ─────────────────────────────────────────────
# 4. Construir las imágenes
# ─────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[4/5]${NC} Construyendo imágenes Docker (esto puede tardar varios minutos)..."
echo "      Por favor espere..."
echo ""

# Navegar a la carpeta docker
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/docker"

docker-compose build --no-cache
echo -e "${GREEN} ✓ Imágenes construidas correctamente.${NC}"

# ─────────────────────────────────────────────
# 5. Levantar todos los servicios
# ─────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[5/5]${NC} Levantando todos los servicios..."
docker-compose up -d
echo -e "${GREEN} ✓ Servicios levantados.${NC}"

# Esperar que los servicios inicien
echo ""
echo "  Esperando 15 segundos para que los servicios inicien..."
sleep 15

# ─────────────────────────────────────────────
# Resultado final
# ─────────────────────────────────────────────
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║               Instalación completada! ✓                  ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo "  Servicios disponibles:"
echo ""
echo -e "    Portal Principal (.NET)  →  ${BLUE}http://localhost:5000${NC}"
echo -e "    Portal PHP               →  ${BLUE}http://localhost:8080${NC}"
echo -e "    MongoDB                  →  ${BLUE}mongodb://localhost:27017${NC}"
echo -e "    SQL Server               →  ${BLUE}localhost:1433${NC}"
echo -e "    Servicio Node.js         →  ${BLUE}http://localhost:3000${NC}"
echo ""
echo "  Para detener todos los servicios, ejecuta: ./stop.sh"
echo ""

# Preguntar si abrir el navegador (solo en macOS o si xdg-open está disponible)
read -p "  ¿Deseas abrir el portal en el navegador? (s/n): " abrir
if [[ "$abrir" =~ ^[Ss]$ ]]; then
    if command -v xdg-open &> /dev/null; then
        xdg-open http://localhost:5000
    elif command -v open &> /dev/null; then
        open http://localhost:5000
    fi
fi
