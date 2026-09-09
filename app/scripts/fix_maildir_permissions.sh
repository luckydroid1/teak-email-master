#!/bin/bash
# fix_maildir_permissions.sh — Fix Maildir file permissions so www-data can read emails.
#
# Root cause: Postfix creates Maildir files as 0600 (postfix:postfix).
# PHP-FPM (www-data) cannot read them, causing "No emails yet" despite delivery.
#
# Fix: Add www-data to postfix group (one-time), then this script ensures all
# mail files are group-readable (0640) for the postfix group.
#
# Run as root via cron (every minute) or manually after delivery issues.
# This is a SAFETY NET — the primary fix is proper group setup (see setup_vps.sh).
set -euo pipefail

LOG="/var/log/maildir-perms.log"
MAIL_BASE="/var/mail"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Count files fixed
FIXED=0

# Fix directory permissions (setgid so new subdirs inherit group)
if [ -d "$MAIL_BASE" ]; then
    chgrp postfix "$MAIL_BASE" 2>/dev/null || true
    chmod 2770 "$MAIL_BASE" 2>/dev/null || true
fi

# Find all Maildir new/ and cur/ directories
find "$MAIL_BASE" -type d \( -name "new" -o -name "cur" -o -name "tmp" \) 2>/dev/null | while read -r dir; do
    # Ensure directory is group-readable and setgid
    chgrp postfix "$dir" 2>/dev/null || true
    chmod 2770 "$dir" 2>/dev/null || true

    # Fix individual email files: ensure group-readable (0640)
    find "$dir" -maxdepth 1 -type f ! -perm -g=r 2>/dev/null | while read -r file; do
        chgrp postfix "$file" 2>/dev/null || true
        chmod 0640 "$file" 2>/dev/null || true
        FIXED=$((FIXED + 1))
    done
done

# Also fix parent domain/user directories
find "$MAIL_BASE" -mindepth 1 -maxdepth 3 -type d ! -perm -g=r 2>/dev/null | while read -r dir; do
    chgrp postfix "$dir" 2>/dev/null || true
    # Directories: rwx for owner, r-x for group (2770 with setgid)
    perm=$(stat -c '%a' "$dir" 2>/dev/null || echo "")
    if [ -n "$perm" ]; then
        # Ensure group can traverse (x) and read (r)
        new_perm=$((8#perm | 0270))
        # Keep setgid bit
        new_perm=$((new_perm | 02000))
        chmod "$new_perm" "$dir" 2>/dev/null || true
    fi
done

if [ "$FIXED" -gt 0 ]; then
    echo "[$TIMESTAMP] Fixed $FIXED maildir files" >> "$LOG"
fi
