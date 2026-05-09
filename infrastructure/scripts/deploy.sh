#!/usr/bin/env bash
# ────────────────────────────────────────────────────────────
# deploy.sh — Deploy / update services on the VPS
#
# Usage:
#   ./infrastructure/scripts/deploy.sh          # full deploy
#   ./infrastructure/scripts/deploy.sh backend   # rebuild only backend
#   ./infrastructure/scripts/deploy.sh migrate   # run migrations only
# ────────────────────────────────────────────────────────────
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$PROJECT_DIR"

# Load .env if present
if [ -f .env ]; then
  set -a; source .env; set +a
fi

SERVICE="${1:-all}"

echo "==> Marketplace Mistico — Deploy ($SERVICE)"
echo "    Project dir: $PROJECT_DIR"
echo ""

case "$SERVICE" in
  all)
    echo "==> Pulling latest images..."
    docker compose pull postgres certbot

    echo "==> Building application images..."
    docker compose build --no-cache backend nginx

    echo "==> Starting services..."
    docker compose up -d

    echo "==> Waiting for database health check..."
    sleep 5

    echo "==> Running migrations..."
    docker compose exec -T backend node scripts/migrate.js up

    echo "==> Checking service health..."
    sleep 3
    docker compose ps
    echo ""
    echo "==> Deploy complete!"
    ;;

  backend)
    echo "==> Rebuilding backend..."
    docker compose build --no-cache backend
    docker compose up -d backend

    echo "==> Running migrations..."
    sleep 3
    docker compose exec -T backend node scripts/migrate.js up

    echo "==> Backend redeployed!"
    docker compose ps backend
    ;;

  migrate)
    echo "==> Running migrations..."
    docker compose exec -T backend node scripts/migrate.js up
    echo "==> Migrations complete!"
    ;;

  rollback)
    echo "==> Rolling back last migration..."
    docker compose exec -T backend node scripts/migrate.js down
    echo "==> Rollback complete!"
    ;;

  restart)
    echo "==> Restarting all services..."
    docker compose restart
    docker compose ps
    ;;

  logs)
    docker compose logs -f --tail=100
    ;;

  status)
    docker compose ps
    echo ""
    echo "==> Disk usage:"
    docker system df
    echo ""
    echo "==> Container resource usage:"
    docker stats --no-stream
    ;;

  *)
    echo "Usage: $0 {all|backend|migrate|rollback|restart|logs|status}"
    exit 1
    ;;
esac
