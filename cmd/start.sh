#!/bin/sh
#
# cmd/start.sh — avvia MySQL + Ollama via Docker e il server Symfony
#
# Uso: ./cmd/start.sh
#       ./cmd/start.sh --stop          (ferma tutto)
#       ./cmd/start.sh --index         (re-indicizza il codebase dopo modifiche)

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

# ── re-index only ──────────────────────────────────────────────────────────────
if [ "$1" = "--index" ]; then
  echo "→ Re-indicizzazione codebase..."
  php "$PROJECT_ROOT/bin/console" app:index-codebase --truncate
  echo "✓ Indicizzazione completata."
  exit 0
fi

# ── start ─────────────────────────────────────────────────────────────────────
echo "→ Avvio MySQL e Ollama..."
$COMPOSE up -d

# ── MySQL healthcheck ──────────────────────────────────────────────────────────
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

# ── Ollama healthcheck ─────────────────────────────────────────────────────────
echo "→ Attendo che Ollama sia pronto..."
ATTEMPTS=0
MAX=40
until $COMPOSE exec -T ollama curl -sf http://localhost:11434/api/tags >/dev/null 2>&1; do
  ATTEMPTS=$((ATTEMPTS + 1))
  if [ "$ATTEMPTS" -ge "$MAX" ]; then
    echo "✗ Ollama non risponde dopo ${MAX} tentativi. Controlla i log:"
    echo "  docker compose -f docker/docker-compose.yml logs ollama"
    exit 1
  fi
  printf "."
  sleep 1
done
echo ""
echo "✓ Ollama pronto."

# ── pull nomic-embed-text (skip se già presente) ───────────────────────────────
if ! $COMPOSE exec -T ollama ollama list 2>/dev/null | grep -q "nomic-embed-text"; then
  echo "→ Download modello nomic-embed-text (~274MB, solo prima volta)..."
  $COMPOSE exec -T ollama ollama pull nomic-embed-text
  echo "✓ Modello scaricato."
else
  echo "✓ Modello nomic-embed-text già presente."
fi

# ── vector store setup (skip se tabella già esiste) ───────────────────────────
if [ ! -f "$PROJECT_ROOT/var/code_store.db" ]; then
  echo "→ Setup vector store SQLite..."
  php "$PROJECT_ROOT/bin/console" ai:store:setup sqlite code
  echo "→ Prima indicizzazione del codebase..."
  php "$PROJECT_ROOT/bin/console" app:index-codebase
  echo "✓ Codebase indicizzato."
else
  echo "✓ Vector store già presente (usa --index per re-indicizzare)."
fi

# ── Symfony ────────────────────────────────────────────────────────────────────
echo "→ Avvio Symfony (https://localhost:8000)..."
symfony server:start --dir="$PROJECT_ROOT" -d

echo ""
echo "✓ Tutto avviato."
echo "  App:    https://localhost:8000"
echo "  MySQL:  127.0.0.1:3306  (user: app / pass: app)"
echo "  Ollama: http://localhost:11434"
echo ""
echo "  Per fermare tutto:          ./cmd/start.sh --stop"
echo "  Per re-indicizzare:         ./cmd/start.sh --index"
