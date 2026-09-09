#!/bin/bash
# cleanup_qa_data.sh — Remove leftover QA/test data from Gate 2 testing.
#
# Safety: NEVER deletes n311311@gmail.com (user_id=3) or service accounts.
# Only removes users with test-pattern emails or pending/banned status from QA.
#
# Usage: bash scripts/cleanup_qa_data.sh [--dry-run]
set -euo pipefail

DB_USER="mailcow"
DB_PASS="TeakMail2026!"
DB_NAME="mailcow"
DRY_RUN=false
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=true

mysql_exec() {
    mysql -u "$DB_USER" -p"$DB_PASS" -N -e "$1" "$DB_NAME" 2>/dev/null
}

echo "=== QA Data Cleanup ==="
echo "Mode: $([ "$DRY_RUN" = true ] && echo 'DRY RUN' || echo 'LIVE')"

# ─── Find QA/test users (exclude n311311@gmail.com ID=3 and service accounts) ───
echo ""
echo "--- QA Users to remove ---"
QA_USERS=$(mysql_exec "
    SELECT id, email, status FROM ia_users
    WHERE email != 'n311311@gmail.com'
    AND (
        email LIKE 'qa-%'
        OR email LIKE 'test-%'
        OR email LIKE '%@testmail.com'
        OR email LIKE '%@guerrillamail%'
        OR email LIKE '%@mailinator%'
        OR email LIKE 'tempuser%'
        OR (status = 'pending' AND created_at > '2026-08-25')
        OR (status = 'banned' AND email LIKE 'qa-%')
    )
    ORDER BY id
")

if [ -z "$QA_USERS" ]; then
    echo "No QA users found. Database is clean."
else
    echo "$QA_USERS" | while IFS=$'\t' read -r id email status; do
        echo "  ID=$id | $email | status=$status"
    done

    if [ "$DRY_RUN" = false ]; then
        # Get user IDs for cleanup
        QA_IDS=$(mysql_exec "
            SELECT id FROM ia_users
            WHERE email != 'n311311@gmail.com'
            AND (
                email LIKE 'qa-%'
                OR email LIKE 'test-%'
                OR email LIKE '%@testmail.com'
                OR email LIKE '%@guerrillamail%'
                OR email LIKE '%@mailinator%'
                OR email LIKE 'tempuser%'
                OR (status = 'pending' AND created_at > '2026-08-25')
                OR (status = 'banned' AND email LIKE 'qa-%')
            )
        ")

        for uid in $QA_IDS; do
            echo "  Cleaning user_id=$uid..."
            mysql_exec "DELETE FROM ia_api_keys WHERE user_id = $uid"
            mysql_exec "DELETE FROM ia_sent_emails WHERE user_id = $uid"
            mysql_exec "DELETE FROM ia_user_domains WHERE user_id = $uid"
            mysql_exec "DELETE FROM ia_registrar_creds WHERE user_id = $uid"
            mysql_exec "DELETE FROM ia_rate_limits WHERE bucket LIKE 'inbox:$uid%' OR bucket LIKE 'fail:%'"
            mysql_exec "DELETE FROM ia_inboxes WHERE user_id = $uid"
            mysql_exec "DELETE FROM ia_audit WHERE user_id = $uid"
            mysql_exec "DELETE FROM ia_credit_ledger WHERE user_id = $uid"
            mysql_exec "DELETE FROM ia_users WHERE id = $uid"
        done
        echo "  QA users deleted."
    fi
fi

# ─── Clean orphaned ia_codes (used by deleted QA users) ───
echo ""
echo "--- Orphaned AppSumo codes ---"
ORPHAN_CODES=$(mysql_exec "
    SELECT id, code, status, redeemed_by FROM ia_codes
    WHERE redeemed_by IS NOT NULL
    AND redeemed_by NOT IN (SELECT id FROM ia_users)
")
if [ -z "$ORPHAN_CODES" ]; then
    echo "No orphaned codes found."
else
    echo "$ORPHAN_CODES" | while IFS=$'\t' read -r id code status redeemed_by; do
        echo "  ID=$id | $code | redeemed_by=$redeemed_by (user deleted)"
    done
    if [ "$DRY_RUN" = false ]; then
        mysql_exec "
            UPDATE ia_codes SET status='unused', redeemed_by=NULL, redeemed_at=NULL
            WHERE redeemed_by IS NOT NULL
            AND redeemed_by NOT IN (SELECT id FROM ia_users)
        "
        echo "  Orphaned codes reset to unused."
    fi
fi

# ─── Clean orphaned Mailcow mailboxes ───
echo ""
echo "--- Orphaned Mailcow mailboxes ---"
ORPHAN_MB=$(mysql_exec "
    SELECT username FROM mailbox
    WHERE username NOT IN (
        SELECT CONCAT(local_part, '@', domain) FROM ia_inboxes WHERE status='active'
    )
    AND username != ''
    AND local_part != 'postmaster'
    AND local_part != 'admin'
")
if [ -z "$ORPHAN_MB" ]; then
    echo "No orphaned mailboxes found."
else
    echo "$ORPHAN_MB" | while IFS=$'\t' read -r username; do
        echo "  Mailbox: $username"
    done
    if [ "$DRY_RUN" = false ]; then
        mysql_exec "
            DELETE FROM mailbox WHERE username IN (
                SELECT username FROM (
                    SELECT m.username FROM mailbox m
                    LEFT JOIN ia_inboxes i ON m.username = i.email_address AND i.status = 'active'
                    WHERE i.id IS NULL
                    AND m.username != ''
                    AND m.local_part NOT IN ('postmaster', 'admin')
                ) AS orphaned
            )
        "
        mysql_exec "
            DELETE FROM sender_acl WHERE logged_in_as NOT IN (SELECT username FROM mailbox)
            AND logged_in_as != ''
        "
        echo "  Orphaned mailboxes cleaned."
    fi
fi

# ─── Verify n311311@gmail.com is safe ───
echo ""
echo "--- Safety check ---"
N311=$(mysql_exec "SELECT id, email, status FROM ia_users WHERE id = 3")
if [ -n "$N311" ]; then
    echo "  n311311@gmail.com (ID=3): SAFE — untouched"
else
    echo "  WARNING: n311311@gmail.com not found in database!"
fi

# ─── Summary ───
echo ""
echo "--- Final state ---"
TOTAL_USERS=$(mysql_exec "SELECT COUNT(*) FROM ia_users")
ACTIVE_INBOXES=$(mysql_exec "SELECT COUNT(*) FROM ia_inboxes WHERE status='active'")
echo "  Users: $TOTAL_USERS"
echo "  Active inboxes: $ACTIVE_INBOXES"
echo ""
echo "=== Cleanup complete ==="
