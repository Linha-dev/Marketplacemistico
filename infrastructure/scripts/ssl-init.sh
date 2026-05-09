#!/usr/bin/env bash
# ────────────────────────────────────────────────────────────
# ssl-init.sh — Initial SSL certificate setup with Let's Encrypt
#
# Usage:
#   ./infrastructure/scripts/ssl-init.sh api.quintalmistico.com.br
# ────────────────────────────────────────────────────────────
set -euo pipefail

DOMAIN="${1:?Usage: $0 <domain>}"
EMAIL="${CERTBOT_EMAIL:-miwoadm@gmail.com}"

PROJECT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$PROJECT_DIR"

echo "==> Requesting SSL certificate for: $DOMAIN"

# 1. Generate a temporary self-signed cert so Nginx can start
echo "==> Creating temporary self-signed certificate..."
mkdir -p infrastructure/nginx/ssl

openssl req -x509 -nodes -days 1 \
  -newkey rsa:2048 \
  -keyout infrastructure/nginx/ssl/privkey.pem \
  -out infrastructure/nginx/ssl/fullchain.pem \
  -subj "/CN=$DOMAIN"

# Copy temporary certs to the Docker volume
docker compose up -d nginx
sleep 2

# 2. Request real certificate via Certbot
echo "==> Requesting real certificate from Let's Encrypt..."
docker compose run --rm certbot certonly \
  --webroot \
  --webroot-path=/var/www/certbot \
  --email "$EMAIL" \
  --agree-tos \
  --no-eff-email \
  --force-renewal \
  -d "$DOMAIN"

# 3. Copy certificate to Nginx volume
echo "==> Linking certificates..."
docker compose exec -T nginx sh -c "\
  ln -sf /etc/letsencrypt/live/$DOMAIN/fullchain.pem /etc/nginx/ssl/fullchain.pem && \
  ln -sf /etc/letsencrypt/live/$DOMAIN/privkey.pem   /etc/nginx/ssl/privkey.pem"

# 4. Reload Nginx
echo "==> Reloading Nginx..."
docker compose exec -T nginx nginx -s reload

# Clean up temporary certs
rm -f infrastructure/nginx/ssl/privkey.pem infrastructure/nginx/ssl/fullchain.pem

echo "==> SSL setup complete for $DOMAIN!"
echo "    Certificate will auto-renew via the certbot container."
