#!/bin/bash
echo ""
echo "  Deteniendo todos los servicios de QualityDoc-Polyglot..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/docker"
docker-compose down
echo ""
echo "  ✓ Todos los servicios han sido detenidos."
