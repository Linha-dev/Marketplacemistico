#!/usr/bin/env bash
# ────────────────────────────────────────────────────────────
# backup-db.sh — Automated PostgreSQL backup
#
# Usage:
#   ./infrastructure/scripts/backup-db.sh
#
# Recommended: add to crontab for daily backups
#   0 3 * * * /opt/marketplace/infrastructure/scripts/backup-db.sh >> /var/log/qm-backup.log 2>&1
# ────────────────────────────────────────────────────────────
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$PROJECT_DIR"

if [ -f .env ]; then
  set -a; source .env; set +a
fi

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/infrastructure/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DB_NAME="${POSTGRES_DB:-quintalmistico}"
DB_USER="${POSTGRES_USER:-qm_app}"
BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

echo "[$(date)] Starting backup of database '$DB_NAME'..."

docker compose exec -T postgres pg_dump \
  -U "$DB_USER" \
  -d "$DB_NAME" \
  --no-owner \
  --no-privileges \
  --format=plain \
  | gzip > "$BACKUP_FILE"

FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "[$(date)] Backup created: $BACKUP_FILE ($FILE_SIZE)"

# Clean up old backups
DELETED=$(find "$BACKUP_DIR" -name "*.sql.gz" -mtime +"$RETENTION_DAYS" -print -delete | wc -l)
echo "[$(date)] Deleted $DELETED backup(s) older than $RETENTION_DAYS days"

echo "[$(date)] Backup complete!"
