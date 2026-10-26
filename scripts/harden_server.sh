#!/usr/bin/env bash
set -euo pipefail

# Server network & firewall hardening script for Teak Email VPS
echo "=== Running Server Hardening Audit & Configuration ==="

# 1. Firewall (UFW) configuration
if command -v ufw >/dev/null 2>&1; then
    echo "[+] Configuring UFW rules..."
    ufw default deny incoming
    ufw default allow outgoing
    
    # Allow necessary public services
    ufw allow 22/tcp comment 'SSH'
    ufw allow 80/tcp comment 'HTTP'
    ufw allow 443/tcp comment 'HTTPS'
    ufw allow 25/tcp comment 'SMTP'
    ufw allow 587/tcp comment 'Submission'
    ufw allow 993/tcp comment 'IMAPS'
    ufw allow 143/tcp comment 'IMAP'
    
    # Deny external access to internal ports
    ufw deny 3456 comment 'MCP Server Internal'
    ufw deny 6379 comment 'Redis Internal'
    ufw deny 5432 comment 'Postgres Internal'
    ufw deny 8090 comment 'App Internal'
    ufw deny 8091 comment 'App Internal'
    ufw deny 8092 comment 'App Internal'
    
    # Enable UFW without prompting
    echo "y" | ufw enable || true
    ufw status verbose
fi

# 2. Redis loopback binding check
if [ -f /etc/redis/redis.conf ]; then
    echo "[+] Verifying Redis loopback binding..."
    sed -i "s/^bind .*/bind 127.0.0.1 ::1/" /etc/redis/redis.conf || true
    systemctl restart redis-server 2>/dev/null || systemctl restart redis 2>/dev/null || true
fi

# 3. File permissions hardening
echo "[+] Hardening sensitive configuration files..."
if [ -f /var/www/inboxapp/config.php ]; then
    chmod 600 /var/www/inboxapp/config.php
    chown www-data:www-data /var/www/inboxapp/config.php
fi

echo "=== Server Hardening Complete ==="
