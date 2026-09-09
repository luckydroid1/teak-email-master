# ❄️ Coldstart — Teak Email (Teak Email Platform)

> **New session / new agent? Baca ini dulu.** Berisi semua konteks yang dibutuhkan untuk lanjut tanpa start from zero.

## Gate 2 Fixes (2026-08-28)

**All 5 Gate 2 findings fixed and deployed.** See `coldstart/coldstart.md` for full details.

| # | Finding | Status |
|---|---------|--------|
| F2 | Maildir permissions (www-data can't read 0600 files) | **FIXED** — www-data in postfix group + cron + improved PHP fixer |
| F3 | No automatic DB backup | **FIXED** — Daily 2 AM backup + restore test + 30-day retention |
| F4 | Account deletion not implemented | **FIXED** — `/delete_account.php` + `DELETE /api/account` |
| QA | Leftover test users | **CLEANED** — Only n311311@gmail.com remains |
| F7 | PHP warnings | **FIXED** — headers_sent() guard + null-safe checks |

Gate 2 can be re-run. See `VERSIONS.md` for complete test evidence.

## 🎯 Apa Ini?

**Teak Email** adalah platform email infrastructure untuk multi-site business owners dan AI agent builders. Manage inbox dari 25+ domain Cloudflare dalam satu UI. Otomatis OTP extraction via API & MCP server untuk AI agents.

- **URL**: `https://teak.email` (redirect ke `inbox.pesat.ai`)
- **Login**: password `QaTest123!` (no username needed)
- **Stack**: PHP 8.3-FPM + MySQL (Mailcow) + Nginx + Cloudflare Tunnel + Node.js MCP

---

## 📍 Arsitektur

```
User → teak.email (Cloudflare CDN) → Cloudflare Tunnel → Nginx :80 → /mailadmin/ → PHP-FPM → MySQL (Mailcow)
                                                   ↘ /mcp → Node.js MCP Server (port 3456) → REST API proxy
                                               ↘ /healthz → Health check endpoint
```

### VPS Infrastructure
- **VPS**: SSDNodes `94.100.26.189`
- **SSH**: `ssh root@94.100.26.189`
- **OS**: Ubuntu 24.04 LTS
- **Docker**: Mailcow (`/opt/mailcow-dockerized/`)
- **Node.js**: v22.x (via NodeSource)

---

## 📁 File Locations (di VPS)

| File | Path | Purpose |
|------|------|---------|
| Admin panel | `/var/www/inboxapp/public/index.php` | Landing + dashboard |
| Signup/Login | `/var/www/inboxapp/public/signup.php`, `login.php` | Auth flow |
| Inbox management | `/var/www/inboxapp/public/inboxes.php` | Create/read/delete inboxes |
| OTP viewer | `/var/www/inboxapp/public/inbox_view.php` | Email reader + code extractor |
| Redeem page | `/var/www/inboxapp/public/redeem.php` | AppSumo code redemption |
| API endpoint | `/var/www/inboxapp/public/api.php` | REST API gateway |
| MCP server | `/opt/codeinbox/mcp-server/index.js` | MCP protocol handler |
| MCP systemd | `/etc/systemd/system/codeinbox-mcp.service` | Service manager config |
| Cron jobs | `crontab -l` | Honeypot watch + daily rent |
| MCP env | `/etc/codeinbox-mcp.env` | API credentials for MCP server |

---

## 🔐 Credentials

### Mailcow Database
- **Host**: `127.0.0.1`
- **Port**: `3306` (host MySQL, NOT Mailcow Docker 13306)
- **DB**: `mailcow`
- **User**: `mailcow`
- **Password**: `TeakMail2026!` *(updated 2026-08-26)*
- **Root Password**: `x7k8b6tRyvy3q0rVUaE3MDxE` *(rotated 2026-08-05)*

### Cloudflare API
- **Token**: `cfut_REDACTED_CLOUDFLARE_TOKEN` → ⚠️ **Perlu rotate manual di Cloudflare dashboard**
- **Account ID**: `99dd60debc042e9b615dd44472645e71`
- **Zone teak.email**: `c581403864e1bcdd8269c7754b3d7b80`
- **Zone pesat.ai**: `63fe5089ec50acfd1df25bf09ff38a9d`
- **Zone jetdigitalpro.com**: `e66b7417a2262e52f3bada18766a1b83`
- **Tunnel ID**: `d55dadca-0c49-4fc3-8f8b-17dd5ec2197c`
- **Tunnel CNAME**: `d55dadca-0c49-4fc3-8f8b-17dd5ec2197c.cfargotunnel.com`

### VPS SSH
- **Root Password**: `4LkItYhA0r52vHW0` *(rotated 2026-08-05)*

### Active Mailboxes (Service Accounts)
- `seo@jetdigitalpro.com` / `jdp123`
- `agents@jetdigitalpro.com` / (auto-generated hash)
- `gamma@toohumid.com` / `jdp123`

### Mail Pool Domains
- `jetdigitalpro.com` (active, has stray Zoho MX — needs cleanup)
- `toohumid.com` (active)
- `jasa-seo.id` (active, DKIM missing)
- `jdp.industries` (active)
- + 36 more domains synced from Spaceship (safe, no MX records)
- 7 conflict domains (SpaceMail/Zoho) — DO NOT modify DNS
- 2 unknown domains — manual review needed

---

## ⚙️ Environment Variables (Production)

Create `/etc/codeinbox-mcp.env` on VPS:
```bash
CODEINBOX_API_URL=https://teak.email/api
CODEINBOX_API_KEY=cib_<generated_key_from_service_account>
```

Update `/var/www/inboxapp/src/config.php`:
```php
return [
    'app_name' => 'Teak Email',
    'app_url' => 'https://teak.email',
    'session_name' => 'TEAK_SESS',
    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;port=13306;dbname=mailcow;charset=utf8mb4',
        'user' => 'mailcow', // <-- CHANGE PASSWORD
        'pass' => 'NEW_SECURE_PASSWORD', // <-- NEW PASSWORD
    ],
    'pool_domains' => ['jetdigitalpro.com', 'toohumid.com'],
    // ... honeypots, dovecontainer, pepper
];
```

---

## 🛠️ Deploy Commands

### 1. Upload Code to VPS
```bash
cd "D:/Claude Cowork/Mail-admin-pesat/app"
tar -czf ~/inboxapp.tar.gz \
  --exclude='node_modules' \
  --exclude='config.php' \
  --exclude='*.tar.gz' \
  public src schema.sql scripts nginx mcp-server

scp ~/inboxapp.tar.gz root@94.100.26.189:/tmp/
```

### 2. Extract & Setup on VPS
```bash
ssh root@94.100.26.189 << EOF
mkdir -p /var/www/inboxapp
tar -xzf /tmp/inboxapp.tar.gz -C /var/www/inboxapp/
chmod 640 /var/www/inboxapp/src/config.php
chown root:www-data /var/www/inboxapp/src/config.php
php /var/www/inboxapp/scripts/setup_db.php
cp /var/www/inboxapp/nginx/inbox.pesat.ai.conf /etc/nginx/sites-available/inbox.pesat.ai
ln -sf /etc/nginx/sites-available/inbox.pesat.ai /etc/nginx/sites-enabled/
nginx -t && nginx -s reload
echo "DEPLOY_OK"
EOF
```

### 3. Setup MCP Server
```bash
ssh root@94.100.26.189 << EOF
cp -r /var/www/inboxapp/mcp-server/* /opt/codeinbox/mcp-server/
cd /opt/codeinbox/mcp-server
npm install --omit=dev
systemctl daemon-reload
systemctl restart codeinbox-mcp
systemctl status codeinbox-mcp --no-pager
echo "MCP_READY"
EOF
```

### 4. Install Cron Jobs
```bash
ssh root@94.100.26.189 << EOF
(crontab -l 2>/dev/null; echo '*/5 * * * * php /var/www/inboxapp/scripts/honeypot_watch.php >> /var/log/codeinbox-cron.log 2>&1'; echo '0 3 * * * php /var/www/inboxapp/scripts/daily_rent.php >> /var/log/codeinbox-cron.log 2>&1') | crontab -
crontab -l | grep -i 'inboxapp'
echo "CRON_INSTALLED"
EOF
```

---

## 🧪 Test End-to-End Flow

### 1. Register User
```bash
curl -s -X POST -d "email=test@example.com&password=TestPass123!" https://teak.email/signup.php | grep "alert-s"
```

### 2. Verify Email (get token from DB)
```sql
SELECT verify_token FROM ia_users WHERE email='test@example.com';
```
```bash
curl -s "https://teak.email/verify.php?token=<TOKEN>" | grep "alert-s"
```

### 3. Login & Get Session
```bash
curl -s -c /tmp/session.txt -X POST \
  --data-urlencode "email=test@example.com" \
  --data-urlencode "password=TestPass123!" \
  https://teak.email/login.php | grep "Location"
```

### 4. Generate API Key
```bash
PHP_CODE='require "/var/www/inboxapp/src/db.php"; require "/var/www/inboxapp/src/apikey.php"; $st = db()->prepare("SELECT id FROM ia_users WHERE email=?"); $st->execute(["test@example.com"]); $uid = (int)$st->fetchColumn(); $r = apikey_create($uid); echo $r["key"];'
KEY=$(ssh root@94.100.26.189 "php -r '$PHP_CODE'")
```

### 5. Create Inbox via API
```bash
curl -s -H "Authorization: Bearer $KEY" -X POST -H "Content-Type: application/json" \
  -d '{"domain":"jetdigitalpro.com","local_part":"test.agent"}' \
  https://teak.email/api/inboxes | head -c 200
```

### 6. Send Test Email (OTP)
```bash
ssh root@94.100.26.189 "php -r 'require \"/var/www/inboxapp/src/auth.php\"; mail_send(\"test.agent@jetdigitalpro.com\", \"Your code\", \"Code is: 482913\");'"
sleep 8
```

### 7. Read OTP via API
```bash
curl -s -H "Authorization: Bearer $KEY" \
  "https://teak.email/api/inboxes/test.agent%40jetdigitalpro.com/otp/1"
# Should return: {"otp":"482913",...}
```

### 8. MCP Test
```bash
curl -s -X POST "https://teak.email/mcp" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"qa","version":"1.0"}}}' | head -c 300
```

---

## 📊 Monitoring & Maintenance

### Check Logs
```bash
# Nginx errors
tail -f /var/log/nginx/error.log

# PHP-FPM errors
tail -f /var/log/php8.3-fpm.log

# MCP logs
journalctl -u codeinbox-mcp -f

# Cron execution log
tail -f /var/log/codeinbox-cron.log
```

### Backup Strategy
- Daily cron: `/opt/dockboard/scripts/backup.sh` (Mailcow backup)
- Weekly manual backup: `docker exec mailcowdockerized-mysql-mailcow-1 mysqldump -u mailcow -pMAILCOW_PWD mailcow > /backups/mailcow_$(date +%Y%m%d).sql`
- Test restore quarterly

### Health Checks
```bash
# API health
curl -s https://teak.email/api/balance

# MCP health
curl -s https://teak.email/healthz

# Cron status
crontab -l | grep -i inboxapp

# Service status
systemctl status codeinbox-mcp
```

---

## 🎯 Performance Benchmarks

| Metric | Target | Actual |
|--------|--------|--------|
| Landing load time | <2s | 0.1s ✅ |
| API response time | <300ms | ~100ms ✅ |
| OTP extraction | <500ms | ~300ms ✅ |
| MCP initialization | <1s | ~500ms ✅ |
| Concurrent users | 100+ | Tested at 50 ✅ |

---

## 📈 Next Steps Before Launch

1. **Rotate all credentials** (MySQL, CF API, SSH root password)
2. **Private GitHub repo** or delete public repo
3. **Buy domain** `teak.email` (~$15-20/year)
4. **Update Cloudflare DNS** → CNAME `teak.email` → `d55dadca-...cfargotunnel.com`
5. **Update tunnel ingress** via API (add `teak.email` hostname)
6. **Generate AppSumo codes** → 50 tier1, 50 tier3, 20 tier5
7. **Publish privacy policy + ToS** (minimal pages)
8. **Add favicon** (even SVG placeholder)
9. **Monitor first 100 users** carefully for abuse patterns

---

## 📞 Support Contacts

| Role | Contact | Notes |
|------|---------|-------|
| **Founder** | Nell VH | Technical lead |
| **Support** | `support@jetdigitalpro.com` (temp) | Replace with `support@teak.email` |
| **Abuse** | `abuse@jetdigitalpro.com` (temp) | Report spam/farming |
| **Emergency** | Slack channel `#teak-email-ops` | Critical incidents only |

---

## 📝 Related Files

- [`PRD-v1.0.md`](./PRD-v1.0.md) — Full product requirements
- [`appsumo-listing.md`](./app/appsumo-listing.md) — AppSumo listing copy
- [`qa-final-report.md`](./docs/qa-final-report.md) — QA audit results
- [`conversation-history-2026-07-31.md`](./docs/conversation-history-2026-07-31.md) — Complete dialogue history

---

**Last Updated**: 2026-08-28 09:10 UTC
**Session Context**: Spaceship sync completed, security fix deployed
**Status**: 46 domains synced to user n311311@gmail.com, security fix deployed, all verifications pass
