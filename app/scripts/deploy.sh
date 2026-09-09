#!/bin/bash
# deploy.sh — deploy CodeInbox ke VPS.
# Usage: bash scripts/deploy.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="root@94.100.26.189"
REMOTE_PATH="/var/www/inboxapp"

echo "==> Packing $APP_DIR (tanpa config.php & node_modules)"
TARBALL=$(mktemp --suffix=.tar.gz)
tar -czf "$TARBALL" \
  -C "$APP_DIR" \
  --exclude='node_modules' \
  --exclude='config.php' \
  --exclude='*.tar.gz' \
  public src schema.sql scripts nginx mcp-server

echo "==> Uploading to $REMOTE"
scp -q "$TARBALL" "$REMOTE:/tmp/inboxapp.tar.gz"

echo "==> Extracting on server"
ssh "$REMOTE" bash -s <<'EOF'
set -euo pipefail
mkdir -p /var/www/inboxapp
tar -xzf /tmp/inboxapp.tar.gz -C /var/www/inboxapp
chown -R www-data:www-data /var/www/inboxapp/public 2>/dev/null || true
# Pastikan config.php ada (jangan overwrite)
if [ ! -f /var/www/inboxapp/src/config.php ]; then
  echo "ERROR: src/config.php belum ada di server! Salin manual dari config.example.php"
  exit 1
fi
# Migrasi schema
php /var/www/inboxapp/scripts/setup_db.php

# Sync MCP server ke /opt/codeinbox (kalau ada perubahan)
if [ -d /var/www/inboxapp/mcp-server ]; then
  cp -r /var/www/inboxapp/mcp-server/* /opt/codeinbox/mcp-server/ 2>/dev/null || true
  cd /opt/codeinbox/mcp-server && npm install --omit=dev >/dev/null 2>&1 || true
  systemctl restart codeinbox-mcp 2>/dev/null || true
fi
echo "Deploy OK"
EOF

rm -f "$TARBALL"
echo "==> Done"
