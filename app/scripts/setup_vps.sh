#!/bin/bash
# setup_vps.sh — satu kali: install Node, MCP server, nginx vhost, cron.
# Usage: jalankan di VPS sebagai root. (scp file ini dulu)
set -euo pipefail

echo "==> 1. Install Node.js 20 (jika belum)"
if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi
node --version

echo "==> 2. Setup MCP server di /opt/codeinbox"
mkdir -p /opt/codeinbox
if [ ! -d /opt/codeinbox/mcp-server ]; then
  mkdir -p /opt/codeinbox/mcp-server
fi
cd /opt/codeinbox/mcp-server
# Salin dari hasil deploy (app/mcp-server) jika belum ada
if [ ! -f package.json ]; then
  echo "ERROR: /opt/codeinbox/mcp-server/package.json belum ada."
  echo "       Deploy app dulu: bash scripts/deploy.sh lalu salin mcp-server/"
  exit 1
fi
npm install --omit=dev

echo "==> 3. Systemd service"
cat > /etc/systemd/system/codeinbox-mcp.service <<'UNIT'
[Unit]
Description=CodeInbox MCP Server
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/codeinbox/mcp-server
EnvironmentFile=/etc/codeinbox-mcp.env
ExecStart=/usr/bin/node /opt/codeinbox/mcp-server/index.js
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable codeinbox-mcp
systemctl restart codeinbox-mcp
systemctl status codeinbox-mcp --no-pager | head -8 || true

echo "==> 4. Nginx vhost"
cp /var/www/inboxapp/nginx/inbox.pesat.ai.conf /etc/nginx/sites-available/inbox.pesat.ai
ln -sf /etc/nginx/sites-available/inbox.pesat.ai /etc/nginx/sites-enabled/inbox.pesat.ai
nginx -t && nginx -s reload

echo "==> 5. Cron"
CRON_TMP=$(mktemp)
crontab -l > "$CRON_TMP" 2>/dev/null || true
grep -q "honeypot_watch" "$CRON_TMP" || echo "*/5 * * * * php /var/www/inboxapp/scripts/honeypot_watch.php >> /var/log/codeinbox-cron.log 2>&1" >> "$CRON_TMP"
grep -q "daily_rent" "$CRON_TMP" || echo "0 3 * * * php /var/www/inboxapp/scripts/daily_rent.php >> /var/log/codeinbox-cron.log 2>&1" >> "$CRON_TMP"
crontab "$CRON_TMP"
rm -f "$CRON_TMP"
echo "Cron OK:"
crontab -l | grep codeinbox || true

echo "==> 6. Env MCP (isi CODEINBOX_API_KEY)"
if [ ! -f /etc/codeinbox-mcp.env ]; then
  cat > /etc/codeinbox-mcp.env <<'ENV'
CODEINBOX_API_URL=https://inbox.pesat.ai/api
CODEINBOX_API_KEY=REPLACE_WITH_API_KEY
ENV
  echo "⚠️  Isi CODEINBOX_API_KEY di /etc/codeinbox-mcp.env lalu: systemctl restart codeinbox-mcp"
fi

echo "==> Selesai. Jangan lupa:"
echo "   1. Set DNS inbox.pesat.ai di Cloudflare (A → 94.100.26.189 atau tunnel)"
echo "   2. Isi /etc/codeinbox-mcp.env"
echo "   3. Test: curl http://127.0.0.1:3456/healthz"
