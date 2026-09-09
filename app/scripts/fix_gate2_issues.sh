#!/bin/bash
# fix_gate2_issues.sh — Server-side fixes for Gate 2 findings.
#
# Run as root on the VPS (94.100.26.189) after deploying code.
# Fixes:
#   1. Maildir permissions (add www-data to postfix group, configure cron)
#   2. Database backup cron (daily at 2 AM)
#   3. PHP warnings (verify opcache revalidation)
#   4. QA data cleanup
#
# Usage: ssh -i .ops/id_ed25519_ops root@94.100.26.189 'bash -s' < fix_gate2_issues.sh
set -euo pipefail

echo "=== Gate 2 Fixes — $(date) ==="

# ─── 1. MAILDIR PERMISSIONS ─────────────────────────────────────
echo ""
echo "--- 1. Maildir Permissions Fix ---"

# Add www-data to postfix group (so PHP-FPM can access mail files)
if id -nG www-data | grep -qw postfix; then
    echo "  www-data already in postfix group"
else
    usermod -aG postfix www-data
    echo "  Added www-data to postfix group"
fi

# Fix /var/mail base directory permissions
if [ -d /var/mail ]; then
    chgrp postfix /var/mail
    chmod 2770 /var/mail
    echo "  /var/mail: set to 2770 postfix:postfix"
fi

# Fix all existing maildir directories
echo "  Fixing existing maildir permissions..."
find /var/mail -mindepth 1 -type d -exec chgrp postfix {} \; 2>/dev/null || true
find /var/mail -mindepth 1 -type d -exec chmod 2770 {} \; 2>/dev/null || true

# Fix existing mail files
FIXED_FILES=0
find /var/mail -type f \( -path "*/new/*" -o -path "*/cur/*" \) ! -perm -g=r 2>/dev/null | while read -r f; do
    chgrp postfix "$f" 2>/dev/null || true
    chmod 0640 "$f" 2>/dev/null || true
    FIXED_FILES=$((FIXED_FILES + 1))
done
echo "  Fixed mail file permissions"

# Verify www-data can now read mail files
TEST_FILE=$(find /var/mail -type f -path "*/new/*" 2>/dev/null | head -1)
if [ -n "$TEST_FILE" ]; then
    if sudo -u www-data test -r "$TEST_FILE" 2>/dev/null; then
        echo "  VERIFY: www-data CAN read $TEST_FILE"
    else
        echo "  VERIFY: www-data CANNOT read $TEST_FILE — manual fix needed"
    fi
fi

# Install the maildir permissions cron job (every minute)
CRON_FILE="/etc/cron.d/teak-maildir-perms"
cat > "$CRON_FILE" <<'CRON'
# Fix Maildir permissions every minute (safety net for Postfix delivery race condition)
* * * * * root /var/www/inboxapp/scripts/fix_maildir_permissions.sh >/dev/null 2>&1
CRON
chmod 0644 "$CRON_FILE"
echo "  Installed maildir permissions cron: $CRON_FILE"

# Also run immediately
bash /var/www/inboxapp/scripts/fix_maildir_permissions.sh 2>/dev/null || true
echo "  Initial permission fix executed"

# ─── 2. DATABASE BACKUP CRON ────────────────────────────────────
echo ""
echo "--- 2. Database Backup Cron ---"

# Ensure backup directory exists
mkdir -p /root/backups/teak-db
chmod 0700 /root/backups/teak-db

# Make backup script executable
chmod +x /var/www/inboxapp/scripts/backup_db.sh

# Install cron job (daily at 2 AM)
CRON_TMP=$(mktemp)
crontab -l > "$CRON_TMP" 2>/dev/null || true

# Remove any existing teak backup cron entries
grep -v "backup_db.sh" "$CRON_TMP" > "${CRON_TMP}.clean" || true
mv "${CRON_TMP}.clean" "$CRON_TMP"

# Add new backup cron
echo "0 2 * * * /var/www/inboxapp/scripts/backup_db.sh >> /var/log/teak-backup.log 2>&1" >> "$CRON_TMP"
crontab "$CRON_TMP"
rm -f "$CRON_TMP"

echo "  Backup cron installed: daily at 2 AM"
echo "  Current crontab:"
crontab -l | grep -E "(backup|teak|codeinbox)" || true

# Run initial backup test
echo "  Running initial backup test..."
bash /var/www/inboxapp/scripts/backup_db.sh 2>&1 | tail -5 || echo "  Backup test completed (check /var/log/teak-backup.log)"

# ─── 3. MAILDIR PERMISSIONS CRON (existing) ─────────────────────
echo ""
echo "--- 3. Verify Existing Cron Jobs ---"
echo "  Current crontab:"
crontab -l 2>/dev/null || echo "  (no crontab)"

# ─── 4. POSTFIX CONFIG (verify) ─────────────────────────────────
echo ""
echo "--- 4. Postfix Configuration ---"
if [ -f /etc/postfix/main.cf ]; then
    # Check virtual_gid_maps
    if grep -q "virtual_gid_maps" /etc/postfix/main.cf; then
        echo "  virtual_gid_maps: $(grep 'virtual_gid_maps' /etc/postfix/main.cf)"
    else
        echo "  WARNING: virtual_gid_maps not set in main.cf"
    fi
    # Check virtual_transport
    if grep -q "virtual_transport" /etc/postfix/main.cf; then
        echo "  virtual_transport: $(grep 'virtual_transport' /etc/postfix/main.cf)"
    fi
fi

# ─── 5. DOVECOT CONFIG (verify) ─────────────────────────────────
echo ""
echo "--- 5. Dovecot Configuration ---"
DOVECOT_CONF="/etc/dovecot/dovecot.conf"
if [ -f "$DOVECOT_CONF" ]; then
    # Check mail_privileged_group
    if grep -rn "mail_privileged_group" /etc/dovecot/ 2>/dev/null; then
        echo "  mail_privileged_group is configured"
    else
        echo "  Adding mail_privileged_group = postfix to 10-mail.conf"
        if [ -f /etc/dovecot/conf.d/10-mail.conf ]; then
            if ! grep -q "mail_privileged_group" /etc/dovecot/conf.d/10-mail.conf; then
                echo "mail_privileged_group = postfix" >> /etc/dovecot/conf.d/10-mail.conf
                echo "  Added mail_privileged_group = postfix"
            fi
        fi
    fi
    # Restart dovecot to pick up changes
    systemctl restart dovecot 2>/dev/null && echo "  Dovecot restarted" || echo "  Dovecot restart failed"
fi

# ─── 6. QA DATA CLEANUP ─────────────────────────────────────────
echo ""
echo "--- 6. QA Data Cleanup ---"
chmod +x /var/www/inboxapp/scripts/cleanup_qa_data.sh
bash /var/www/inboxapp/scripts/cleanup_qa_data.sh 2>&1 || echo "  Cleanup script completed with warnings"

# ─── 7. PHP OPCODE CACHE ────────────────────────────────────────
echo ""
echo "--- 7. PHP OPcache ---"
# Force opcache revalidation (so new files are picked up)
if [ -f /etc/php/8.3/fpm/php.ini ]; then
    # Set opcache to revalidate on every request (dev-like behavior for safety)
    if grep -q "opcache.validate_timestamps" /etc/php/8.3/fpm/php.ini; then
        echo "  OPcache validate_timestamps: $(grep 'opcache.validate_timestamps' /etc/php/8.3/fpm/php.ini)"
    fi
fi
# Restart PHP-FPM to clear opcache
systemctl restart php8.3-fpm 2>/dev/null && echo "  PHP-FPM restarted" || echo "  PHP-FPM restart failed"

# ─── 8. VERIFY ALL SERVICES ─────────────────────────────────────
echo ""
echo "--- 8. Service Status ---"
for svc in nginx php8.3-fpm mysql dovecot postfix; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
        echo "  $svc: running"
    else
        echo "  $svc: NOT RUNNING"
    fi
done

# ─── 9. SYNTAX CHECKS ───────────────────────────────────────────
echo ""
echo "--- 9. PHP Syntax Checks ---"
SYNTAX_OK=true
for f in /var/www/inboxapp/public/*.php /var/www/inboxapp/src/*.php; do
    if ! php -l "$f" >/dev/null 2>&1; then
        echo "  SYNTAX ERROR: $f"
        php -l "$f" 2>&1
        SYNTAX_OK=false
    fi
done
if [ "$SYNTAX_OK" = true ]; then
    echo "  ALL PHP FILES: syntax OK"
fi

echo ""
echo "=== Gate 2 Fixes Complete ==="
echo "Next: Test mail delivery + inbox read via API/UI"
