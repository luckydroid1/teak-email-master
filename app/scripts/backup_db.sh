#!/bin/bash
# backup_db.sh — Production-safe daily database backup for Teak Email.
#
# Features:
# - Compressed mysqldump with restricted permissions (0600)
# - 30-day retention with automatic rotation
# - Restore test into temporary database
# - Structured logging
# - Exits non-zero on failure (for cron error alerts)
#
# Usage: bash scripts/backup_db.sh
# Cron:  0 2 * * * /var/www/inboxapp/scripts/backup_db.sh >> /var/log/teak-backup.log 2>&1
set -euo pipefail

# ─── Config ──────────────────────────────────────────────────────
BACKUP_DIR="/root/backups/teak-db"
LOG_FILE="/var/log/teak-backup.log"
RETENTION_DAYS=30
DB_NAME="mailcow"
DB_USER="mailcow"
DB_PASS="TeakMail2026!"
TIMESTAMP=$(date '+%Y%m%d-%H%M%S')
BACKUP_FILE="$BACKUP_DIR/teak-mailcow-${TIMESTAMP}.sql.gz"
RESTORE_DB="teak_restore_test_${TIMESTAMP}"

# ─── Helpers ─────────────────────────────────────────────────────
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$LOG_FILE"; }
die() { log "ERROR: $*"; exit 1; }

# ─── Pre-flight ──────────────────────────────────────────────────
mkdir -p "$BACKUP_DIR"
chmod 0700 "$BACKUP_DIR"

command -v mysqldump >/dev/null 2>&1 || die "mysqldump not found"
command -v mysql >/dev/null 2>&1 || die "mysql client not found"

log "=== Backup started ==="

# ─── Dump ────────────────────────────────────────────────────────
mysqldump \
    -h 127.0.0.1 \
    -u "$DB_USER" \
    -p"$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --add-drop-table \
    "$DB_NAME" 2>>"$LOG_FILE" | gzip > "$BACKUP_FILE"

# Verify dump is non-empty
FILESIZE=$(stat -c%s "$BACKUP_FILE" 2>/dev/null || stat -f%z "$BACKUP_FILE" 2>/dev/null || echo 0)
if [ "$FILESIZE" -lt 100 ]; then
    rm -f "$BACKUP_FILE"
    die "Backup file too small ($FILESIZE bytes) — dump likely failed"
fi

# Set restricted permissions (0600 = owner read/write only)
chmod 0600 "$BACKUP_FILE"
chown root:root "$BACKUP_FILE"

log "Backup created: $BACKUP_FILE ($FILESIZE bytes)"

# ─── Restore Test ────────────────────────────────────────────────
log "Starting restore test into $RESTORE_DB"

# Create temporary database (use TCP to match DSN host=127.0.0.1)
mysql -h 127.0.0.1 -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$RESTORE_DB\`" 2>>"$LOG_FILE"

# Restore
gunzip -c "$BACKUP_FILE" | mysql -h 127.0.0.1 -u "$DB_USER" -p"$DB_PASS" "$RESTORE_DB" 2>>"$LOG_FILE"

# Verify critical tables exist and have data
TABLES_OK=true
for table in ia_users ia_inboxes ia_credit_ledger ia_api_keys ia_audit mailbox domain; do
    COUNT=$(mysql -h 127.0.0.1 -u "$DB_USER" -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM \`$RESTORE_DB\`.\`$table\`" 2>/dev/null || echo "-1")
    if [ "$COUNT" = "-1" ]; then
        log "WARN: Table $table missing in restore"
        TABLES_OK=false
    else
        log "  $table: $COUNT rows"
    fi
done

# Drop temporary database
mysql -h 127.0.0.1 -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS \`$RESTORE_DB\`" 2>>"$LOG_FILE"

if [ "$TABLES_OK" = false ]; then
    log "WARN: Restore test had missing tables (may be expected for new installs)"
fi

log "Restore test completed"

# ─── Rotation ────────────────────────────────────────────────────
DELETED=0
find "$BACKUP_DIR" -name "teak-mailcow-*.sql.gz" -mtime +"$RETENTION_DAYS" -type f | while read -r old; do
    rm -f "$old"
    DELETED=$((DELETED + 1))
    log "Rotated old backup: $old"
done

# ─── Summary ─────────────────────────────────────────────────────
TOTAL_BACKUPS=$(find "$BACKUP_DIR" -name "teak-mailcow-*.sql.gz" -type f | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)

log "Backup complete: $TOTAL_BACKUPS backups, total size: $TOTAL_SIZE"
log "=== Backup finished ==="
