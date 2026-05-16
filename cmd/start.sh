#!/bin/sh
#
# cmd/start.sh — avvia MySQL via Docker e il server Symfony
#
# Uso: ./cmd/start.sh
#       ./cmd/start.sh --stop   (ferma tutto)

set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
COMPOSE="docker compose -f $PROJECT_ROOT/docker/docker-compose.yml"

# ── stop ──────────────────────────────────────────────────────────────────────
if [ "$1" = "--stop" ]; then
  echo "→ Fermo Symfony..."
  symfony server:stop --dir="$PROJECT_ROOT" 2>/dev/null || true
  echo "→ Fermo Docker..."
  $COMPOSE down
  echo "✓ Tutto fermo."
  exit 0
fi

# ── start ─────────────────────────────────────────────────────────────────────
echo "→ Avvio MySQL..."
$COMPOSE up -d

echo "→ Attendo che MySQL sia pronto..."
ATTEMPTS=0
MAX=30
until $COMPOSE exec -T mysql mysqladmin ping -h localhost -u root -proot --silent 2>/dev/null; do
  ATTEMPTS=$((ATTEMPTS + 1))
  if [ "$ATTEMPTS" -ge "$MAX" ]; then
    echo "✗ MySQL non risponde dopo ${MAX} tentativi. Controlla i log:"
    echo "  docker compose -f docker/docker-compose.yml logs mysql"
    exit 1
  fi
  printf "."
  sleep 1
done
echo ""
echo "✓ MySQL pronto."

echo "→ Avvio Symfony (https://localhost:8000)..."
symfony server:start --dir="$PROJECT_ROOT" -d

echo ""
echo "✓ Tutto avviato."
echo "  App:   https://localhost:8000"
echo "  MySQL: 127.0.0.1:3306  (user: app / pass: app)"
echo ""
echo "  Per fermare tutto: ./cmd/start.sh --stop"
