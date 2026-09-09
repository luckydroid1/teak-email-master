# Teak Email — Incident Response Runbook

**Last updated:** 2026-08-28
**Owner:** Teak Email operations
**Server:** 94.100.26.189 (SSDNodes VPS, Singapore)
**App URL:** https://teak.email
**SSH:** `ssh -i .ops/id_ed25519_ops root@94.100.26.189`

---

## 1. App Down (HTTP 5xx or unreachable)

**Symptoms:** Users report site unreachable; curl returns 5xx or timeout.

**Diagnosis:**
```bash
ssh root@94.100.26.189
systemctl status nginx php8.3-fpm mysql postfix dovecot opendkim cron
curl -s -o /dev/null -w '%{http_code}' http://teak.email/
```

**Steps:**
1. Check all 7 services: `systemctl status nginx php8.3-fpm mysql postfix dovecot opendkim cron`
2. Restart any stopped service: `systemctl restart <service>`
3. Check Nginx config: `nginx -t && systemctl reload nginx`
4. Check PHP-FPM: `systemctl restart php8.3-fpm`
5. Check Cloudflare Tunnel: `ps aux | grep cloudflared`
6. If tunnel down: `systemctl restart cloudflared-permanent`
7. Check disk space: `df -h`
8. Check logs: `tail -50 /var/log/nginx/error.log`

**Escalation:** If services restart but site still down, check Cloudflare Tunnel status in Cloudflare dashboard (apps-pesat-ai tunnel).

---

## 2. Email Not Delivering (Sent but not received)

**Symptoms:** Users report emails not arriving in inboxes.

**Diagnosis:**
```bash
ssh root@94.100.26.189
tail -50 /var/log/mail.log | grep "status="
```

**Steps:**
1. Check Postfix queue: `postqueue -p`
2. Check Postfix logs: `tail -100 /var/log/mail.log | grep "status=bounced\|status=deferred"`
3. Check Maildir permissions: `ls -la /var/mail/<domain>/<user>/Maildir/new/`
4. Run permission fix: `php /var/www/inboxapp/scripts/fix_maildir_perms.php`
5. Check OpenDKIM: `systemctl status opendkim`
6. Check disk space: `df -h` (full disk = maildir writes fail)

---

## 3. API Returning Errors

**Symptoms:** API calls return unexpected 4xx/5xx errors.

**Diagnosis:**
```bash
ssh root@94.100.26.189
tail -50 /var/log/nginx/error.log | grep "api.php"
```

**Steps:**
1. Check PHP-FPM: `systemctl status php8.3-fpm`
2. Check API endpoint: `curl -s http://teak.email/api.php`
3. Check database: `mysql -u mailcow -p mailcow -e "SELECT 1"`
4. Check API key: `mysql -u mailcow -p mailcow -e "SELECT key_prefix, is_active FROM ia_api_keys"`
5. Check rate limiting: look for 429 errors in logs

---

## 4. Database Issues

**Symptoms:** Data missing, query errors, slow responses.

**Diagnosis:**
```bash
ssh root@94.100.26.189
mysql -u mailcow -p mailcow -e "SHOW TABLES"
```

**Steps:**
1. Check MySQL status: `systemctl status mysql`
2. Check DB size: `mysql -u mailcow -p mailcow -e "SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='mailcow'"`
3. Check for corruption: `mysqlcheck -u mailcow -p mailcow --check`
4. Restore from backup: `mysql -u mailcow -p mailcow < /root/backups/db-YYYYMMDD.sql`

---

## 5. Backup & Restore

**Automated backups:** Daily at 2 AM via `/etc/cron.d/teak-db-backup`
**Backup location:** `/var/backups/teak-db-*.sql.gz` (30-day retention)
**Restore test:** Included in cron job (creates temp DB, restores, verifies)

**Manual backup:**
```bash
mysqldump -u mailcow -p mailcow | gzip > /root/backups/manual-$(date +%Y%m%d-%H%M%S).sql.gz
```

**Manual restore:**
```bash
zcat /root/backups/db-YYYYMMDD.sql.gz | mysql -u mailcow -p mailcow
```

---

## 6. Rollback (Revert Last Deploy)

**Pre-deploy backups:** `/root/backups/` contains timestamped backups.

**Steps:**
1. Identify backup: `ls -la /root/backups/`
2. Restore code: `tar -xzf /root/backups/inboxapp-pre-deploy-*.tar.gz -C /var/www/inboxapp/`
3. Restore config: `cp /root/backups/<config-backup> /etc/nginx/sites-enabled/teak.email`
4. Reload: `nginx -t && systemctl reload nginx`
5. Verify: `curl -s -o /dev/null -w '%{http_code}' http://teak.email/`

**Estimated time:** <2 minutes

---

## 7. Maildir Permission Issues

**Symptoms:** Inbox shows "No emails yet" despite Postfix delivering.

**Steps:**
1. Run permission fix: `php /var/www/inboxapp/scripts/fix_maildir_perms.php`
2. Check cron: `cat /etc/cron.d/teak-maildir-perms`
3. Restart cron: `systemctl restart cron`
4. Verify: `ls -la /var/mail/<domain>/<user>/Maildir/new/`

---

## 8. DKIM/Email Authentication Issues

**Symptoms:** Emails marked as spam; DKIM verification fails.

**Steps:**
1. Check OpenDKIM: `systemctl status opendkim`
2. Check DKIM keys: `ls -la /etc/opendkim/keys/`
3. Test key: `opendkim-testkey -d <domain> -s dkim`
4. Check DNS: `dig TXT dkim._domainkey.<domain>`
5. Regenerate key: `opendkim-genkey -b 2048 -d <domain> -s dkim -f /etc/opendkim/keys/dkim.<domain>.private`

---

## Key Contacts

- **Server provider:** SSDNodes (support via panel at ssdnodes.com/manage)
- **DNS:** Cloudflare (teak.email zone)
- **Domain registrar:** Spaceship (API credentials in server env vars)

## Key Files

| File | Location | Purpose |
|------|----------|---------|
| Nginx config | `/etc/nginx/sites-enabled/teak.email` | Web server |
| PHP app | `/var/www/inboxapp/` | Application code |
| Config | `/var/www/inboxapp/src/config.php` | Secrets (NOT in git) |
| Logs | `/var/log/nginx/error.log`, `/var/log/mail.log` | Error tracking |
| Backups | `/var/backups/`, `/root/backups/` | DB + code backups |
| Cron jobs | `/etc/cron.d/teak-*` | Maildir perms, DB backup |
