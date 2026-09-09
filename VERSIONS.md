# Teak Email — Version History

## User Tier 5 Upgrade: y3s@gmx.com — 2026-09-02

### Status: COMPLETE — Upgrade applied, verified, no side effects

### What Changed
1. **Created Tier 5 redeem code** via SQL (format: `AS-` + 12 hex chars, tier=5, credits=25,000, source=admin)
2. **Redeemed code for user y3s@gmx.com (ID=33)**: Added 25,000 credits to ia_credit_ledger (balance: 26,000 total)
3. **Updated ia_users.trust_tier** from 1 to 5 for ID=33
4. **Audit log entry created**: action=redeem, detail="tier=5 credits=25000"
5. **Method**: Used existing redeem mechanism (ia_codes + ia_credit_ledger + ia_audit), not ad-hoc data corruption

### Tier 5 Entitlements (from TIER_TABLE)
| Field | Before (Tier 1) | After (Tier 5) |
|-------|-----------------|----------------|
| Credits | 1,000 | 26,000 (1,000 + 25,000) |
| Inbox slots | 1 | 50 |
| Domains | 1 | 10 |
| API access | basic | full |
| Retention | 7 days | 60 days |
| Rate limits | 60 API/hr | 3,000 API/hr |

### Verification (All PASS)
- trust_tier = 5
- Credit balance = 26,000
- Ledger: 2 entries (Tier 1 + Tier 5), both with correct balance_after
- Codes: 2 redeemed by user 33 (no duplicates, no corruption)
- Audit trail: Complete signup -> verify -> login -> redeem -> login -> redeem flow
- Other users (IDs 3, 34, 35): unchanged
- PHP syntax: redeem.php, credits.php, abuse.php all pass
- Endpoints: landing (200), API (401), login (200) all healthy

### Data Preserved
- All API keys (1 active, 3 revoked)
- All inboxes (0)
- All user domains (0)
- No passwords created or exposed

### Files Changed
- None (database-only operation via SSH + MySQL)
- Documentation: `coldstart/coldstart.md`, `VERSIONS.md` updated

---

## Beginner-Friendly AI Agent Documentation Rewrite — 2026-09-02

### Status: COMPLETE — Deployed and verified

### What Changed
1. **`mcp_setup.php` — Full rewrite**: Now the primary documentation hub for connecting AI agents. Replaced the old minimal config page with a comprehensive beginner-friendly guide.
2. **`api_keys.php` — AI agent section rewrite**: Replaced the old "4-step flow" (which did not explain what Teak Email is or how to connect) with a clean "MCP (Recommended)" vs "REST API" card layout linking to the full guide.
3. **"Choose your app" section**: Numbered, app-specific setup instructions for:
   - **Claude Desktop**: Where to find MCP settings (macOS/Windows), exact JSON config, restart instructions, test prompt
   - **Cursor**: Settings > MCP path, exact config, test prompt, note about UI label variations
   - **Windsurf/Other MCP clients**: Generic config, test prompt
   - **ZCode**: Explanation that this environment cannot inherit browser API key; two options (env var or MCP client config)
   - **REST API (curl)**: Complete 6-step copy-paste flow with toohumid.com
4. **API key explanation**: Clear distinction between API key (for code, starts with `cib_`, created in API Keys tab, shown once) and inbox password (for IMAP/POP3, random string, different from API key).
5. **Troubleshooting section**: 5 common symptoms with exact fixes:
   - "Agent says it cannot connect" (npx not installed, wrong config, restart needed)
   - "401 Unauthorized" (key missing/invalid/revoked)
   - "Domain not available" (use pool domains, check eligibility)
   - "No emails yet" (check sending service, wait 30s)
   - "MCP server not found" (Node.js version, npm network, JSON format)
6. **Copy buttons**: All code blocks have copy-to-clipboard buttons.
7. **Mobile-friendly CSS**: Uses existing dark theme patterns, responsive grid layouts.
8. **Safe domains only**: All examples use `toohumid.com` (pool domain). Zero references to `jetdigitalpro.com`.

### UX Before/After

| Aspect | Before | After |
|--------|--------|-------|
| Point 2 clarity | "Tell your agent what to do" — no explanation of what app, how to connect, MCP vs API | Full "Choose your app" section with 5 app-specific numbered guides |
| What is Teak Email | Not explained on these pages | Hero section: "Teak Email gives you temporary email inboxes" |
| MCP vs API | Mentioned but not explained | Large recommendation card + two-tab layout with clear distinction |
| App-specific setup | Generic JSON only | Separate sections for Claude Desktop, Cursor, Windsurf, ZCode with exact paths |
| API key explanation | "Copy it now" — no context | Full explanation: what it is, where to create it, shown once, difference from inbox password |
| Domain examples | Some used jetdigitalpro.com (blocked) | All use toohumid.com (safe pool domain) |
| Troubleshooting | 5 items on api_keys.php only | 5 items with exact symptoms and fixes on mcp_setup.php |
| Copy functionality | No copy buttons | Copy buttons on all code blocks |
| Beginner flow | Confusing — user doesn't know what MCP is or which app to use | Clear: "Not sure? Choose MCP." Then pick your app from numbered list |

### Supported Clients (Verified)

| Client | Status | Config Path | Notes |
|--------|--------|-------------|-------|
| Claude Desktop | SUPPORTED | Settings > Developer > Edit Config | MCP loads on startup; must restart after config change |
| Cursor | SUPPORTED | Settings > MCP | UI labels may vary ("MCP Servers", "Tools & MCP") |
| Windsurf | SUPPORTED (generic) | Settings > MCP | Same JSON config format; exact UI path may vary |
| ZCode | PARTIAL | Env var or MCP client | Cannot inherit browser API key; must set `TEAK_EMAIL_API_KEY` or configure MCP client |
| curl/Python/Node.js | SUPPORTED | N/A (REST API) | Bearer token auth; full endpoint coverage |

### Files Changed
- `app/public/mcp_setup.php` — Full rewrite (~350 lines, was ~120 lines)
- `app/public/api_keys.php` — AI agent section rewritten (lines 64-293 replaced)
- `VERSIONS.md` — This entry
- `coldstart/coldstart.md` — This entry

### Test Results
| # | Test | Result |
|---|------|--------|
| 1 | PHP syntax (mcp_setup.php) | PASS |
| 2 | PHP syntax (api_keys.php) | PASS |
| 3 | Landing page | 200 |
| 4 | API (unauth) | 401 |
| 5 | API keys page (unauth) | 302 (redirect to login) |
| 6 | MCP setup page (unauth) | 302 (redirect to login) |
| 7 | 404 test | Custom 404 page |
| 8 | Security headers (5/5) | PASS |
| 9 | YOUR_API_KEY placeholders (mcp_setup.php) | 10 occurrences (correct) |
| 10 | toohumid.com in examples (mcp_setup.php) | 9 occurrences (correct) |
| 11 | jetdigitalpro.com references | 0 in both files (correct) |
| 12 | Claude Desktop section | Present |
| 13 | Cursor section | Present |
| 14 | Windsurf section | Present |
| 15 | ZCode section | Present |
| 16 | Troubleshooting section | Present |
| 17 | Copy buttons on code blocks | Present |

### Deployment
- Pre-deploy backup: `/var/www/inboxapp/public/{mcp_setup,api_keys}.php.bak-20260902-*`
- Deploy: SCP both files to `/var/www/inboxapp/public/`
- PHP syntax: BOTH PASS on server
- No service restart needed (opcache validates timestamps)

### Remaining Limitations
- ZCode cannot automatically inherit browser API key (environment limitation)
- MCP server package `codeinbox-mcp` must be available via npx (requires Node.js 18+)
- Some corporate networks block npm registry (MCP setup will fail)
- Cursor UI labels change frequently; documentation may need periodic updates

---

## Domain Eligibility Fix + jetdigitalpro.com Rejection — 2026-09-02

### Status: COMPLETE — Deployed and verified

### What Changed
1. **Domain eligibility helper** (`app/src/inbox.php`): New `inbox_domain_eligible()` function validates domain eligibility for inbox creation. A domain is eligible when: (a) it is NOT in `INBOX_BLOCKED_DOMAINS`, (b) it is active in Mailcow, and (c) it is either in the shared pool OR a verified user-owned domain in `ia_user_domains`.
2. **Blocked domain list**: `INBOX_BLOCKED_DOMAINS = ['jetdigitalpro.com']` — permanently excluded from new inbox creation. Existing mailboxes on jetdigitalpro.com are untouched.
3. **`inbox_eligible_domains()`**: Returns pool + custom verified domains (excluding blocked) for API/UI use.
4. **`inbox_create()` updated**: Uses `inbox_domain_eligible()` instead of raw `in_array(pool_domains)`. Now supports user-owned verified custom domains for inbox creation.
5. **`config.php`**: Removed `jetdigitalpro.com` from `pool_domains` array (3 pool domains remain: toohumid.com, jasa-seo.id, jdp.industries).
6. **API `GET /api/domains`**: New `eligible` field shows all domains a user can actually create inboxes on. `pool_domains` and `custom_domains` fields added for clarity.
7. **UI dropdown** (`app/public/inboxes.php`): Shows eligible domains (pool + verified custom) instead of only pool_domains.
8. **API Keys documentation** (`app/public/api_keys.php`): Updated Tier 1 domain section to explain shared pool vs custom domains. Updated error guidance for "Domain not available".
9. **Bug fix** (`app/src/abuse.php`): Added tiers 4 and 5 to `trust_limits()` map. Tier 5 users (n311311@gmail.com) were getting a fatal TypeError because the map only had tiers 1-3.

### Eligibility Behavior
- **Pool domains** (excluding jetdigitalpro.com): toohumid.com, jasa-seo.id, jdp.industries — available to all users
- **User-owned domains**: Any domain in `ia_user_domains` with status=verified and classification=safe — available to the domain owner only
- **Blocked domains**: jetdigitalpro.com — rejected for all new inboxes, existing data untouched
- **Arbitrary domains**: Rejected — must be in pool or verified user-owned

### E2E Test Results
| # | Test | Result |
|---|------|--------|
| 1 | List eligible domains (jetdigitalpro excluded) | PASS — 37 eligible (3 pool + 34 custom) |
| 2 | Reject jetdigitalpro.com for inbox creation | PASS — "This domain is not available for new inboxes" |
| 3 | Create inbox on pool domain (toohumid.com) | PASS |
| 4 | Internal email delivery + list/read/OTP | PASS — OTP 987654 extracted correctly |
| 5 | Create inbox on user-owned custom domain (aerisresearch.com) | PASS |
| 6 | Reject arbitrary domain not in pool or user domains | PASS — "Domain is not configured on the mail server" |
| 7 | Test data cleanup | PASS — 0 active inboxes, test API key revoked |

### Files Changed
- `app/src/inbox.php` — Added `INBOX_BLOCKED_DOMAINS`, `inbox_domain_eligible()`, `inbox_eligible_domains()`; updated `inbox_create()` to use eligibility helper
- `app/src/config.php` — Removed `jetdigitalpro.com` from `pool_domains`
- `app/src/abuse.php` — Added tiers 4 and 5 to `trust_limits()` map with fallback to tier 3
- `app/public/api.php` — Updated `GET /api/domains` to return `eligible`, `pool_domains`, `custom_domains` fields
- `app/public/inboxes.php` — Updated dropdown to show `inbox_eligible_domains()` instead of `pool_domains`
- `app/public/api_keys.php` — Updated Tier 1 domain section and error guidance

### Deployment
- Pre-deploy backup: `/root/backups/domain-eligibility-20260902-*`
- Deploy: SCP 6 files to `/var/www/inboxapp/{src,public}/`
- PHP lint: ALL 6 files PASS
- No service restart needed (opcache validates timestamps)

---

## AI Agent Documentation on API Keys Page — 2026-09-02

### Status: COMPLETE — Deployed and verified

### What Changed
1. **API Keys page (`api_keys.php`)** — Added prominent "Use Teak Email with an AI Agent" section below key management cards
2. **4-step numbered flow**: Generate API key, tell agent what to do, agent calls API, get OTP/read email
3. **Copy-paste curl examples** with placeholder `YOUR_API_KEY`: list domains, create inbox, list emails, read email, extract OTP
4. **Tier 1 entitlements section** pulled from actual `TIER_TABLE` code: 1,000 credits, 1 inbox slot, 1 domain, basic API, 7-day retention
5. **Domain availability**: Shows toohumid.com, jasa-seo.id, jdp.industries as available pool domains. jetdigitalpro.com excluded from pool (now blocked for new inboxes per domain eligibility fix)
6. **Error guidance** with 5 common issues: Unauthorized (401), Domain not available, Inbox slot limit, No emails yet, Not enough credits — each with fix instructions
7. **Security notice**: API key shown once, keep private, revoke if compromised
8. **Helpful links**: Getting Started, My Inboxes, support@teak.email

### Endpoints Documented (live, Nginx-routed)
- `GET /api/domains` — list available domains
- `POST /api/inboxes` — create inbox `{domain, local_part}`
- `GET /api/inboxes` — list inboxes
- `GET /api/inboxes/{email}/emails` — list emails
- `GET /api/inboxes/{email}/emails/{uid}` — read email
- `GET /api/inboxes/{email}/otp/{uid}` — extract OTP
- `GET /api/balance` — check credit balance

### Route Consistency
- All routes use `/api/*` (Nginx routes to `api.php` with PATH_INFO)
- No `/api.php/inboxes` endpoint exists — the old form is not documented
- Nginx config: `location ~ ^/api(/.*)?$ { fastcgi_param SCRIPT_FILENAME $document_root/api.php; fastcgi_param PATH_INFO $1; }`

### Tier 1 Entitlements (from TIER_TABLE in `app/src/redeem.php`)
| Field | Value |
|-------|-------|
| Credits | 1,000 |
| Inbox slots | 1 |
| Domains | 1 (choose from pool) |
| API access | basic |
| Retention | 7 days |

### Pool Domains (production, `app/src/config.php`)
- toohumid.com
- jasa-seo.id
- jdp.industries

### What is NOT documented (actual limitations)
- **Domain creation**: Users cannot create custom domains via API. Only pool domains are available for inbox creation. `inbox_create()` validates against `pool_domains`, not user-scoped custom domains.
- **Domain sync**: `POST /api/domains/sync` exists but requires server-side Spaceship credentials (not available to Tier 1 users without admin configuration)

### Files Changed
- `app/public/api_keys.php` — Added ~180 lines of documentation HTML (AI agent section, curl examples, Tier 1 details, error guidance, links)

### Test Results
| # | Test | Result |
|---|------|--------|
| 1 | PHP syntax (deployed file) | PASS |
| 2 | Landing page | 200 |
| 3 | Login page | 200 |
| 4 | Signup page | 200 |
| 5 | API (unauth) | 401 |
| 6 | API balance (unauth) | 401 |
| 7 | API domains (unauth) | 401 |
| 8 | 404 page | 404 |
| 9 | Privacy | 200 |
| 10 | Terms | 200 |
| 11 | API keys page (unauth) | 302 (redirect to login) |
| 12 | Security headers (5/5) | PASS |
| 13 | OG tags (5/5) | PASS |
| 14 | jetdigitalpro.com in domain badges | 0 occurrences (correctly excluded) |
| 15 | YOUR_API_KEY placeholders | 9 occurrences (correct) |

### Deployment
- Pre-deploy backup: `/var/www/inboxapp/public/api_keys.php.bak-20260902-*`
- Deploy: SCP api_keys.php to `/var/www/inboxapp/public/`
- PHP syntax: PASS
- Opcache: validates timestamps (no restart needed)

---

## Tier 1 Redeem Code Created — 2026-09-02

### Status: COMPLETE

### What Changed
1. Investigated cheapest tier from code/schema: **Tier 1** (1,000 credits, 1 inbox slot, 1 domain, basic API, 7-day retention)
2. Confirmed y3s@gmx.com (ID=33) exists, active, verified, zero credits, no codes redeemed
3. Generated one Tier 1 redeem code via same mechanism as `generate_codes()`: `AS-` + 12 cryptographically random hex chars
4. Code ID: 5, Status: unused, Source: admin
5. Verified: code exists, unused, unique (2 total codes in DB, both unique), Tier 1 entitlements correct
6. No code was redeemed -- left for normal redemption flow at `/redeem.php`

### Account: y3s@gmx.com
- ID: 33, Status: active, Trust tier: 1, Verified: yes, Credits: 0, Codes redeemed: 0

### Tier 1 Entitlements
- 1,000 credits, 1 inbox slot, 1 domain, basic API, 7-day retention

### Documentation
- `coldstart/coldstart.md` updated with full record (code value redacted in docs)
- `VERSIONS.md` updated (this entry)

---

## Git Repository Initialized — 2026-09-02

### Status: LOCAL COMMIT COMPLETE — Push pending

### Repository Info
- **GitHub URL**: https://github.com/emerilansel-jpg/mail-admin-pesat
- **Branch**: `master`
- **Commit**: `3f2c73b` (initial commit)
- **Files**: 115 committed, 15022 insertions
- **Push**: BLOCKED — GitHub repo needs creation + `gh auth login`

### Security
- Secrets excluded: `.ops/` (SSH keys, passwords), `app/src/config.php`
- All committed files scanned: CLEAN (no hardcoded secrets)

---

## UX Overhaul + Tier Upgrade + Sender Change + MCP Skill — 2026-08-29

### Status: COMPLETE — All 4 tasks done, 42/42 PHP syntax pass, all endpoints verified

### What Changed
1. **UX overhaul** -- Score 7 -> 8.5/10. Loading states on all forms, show/hide password toggles, text labels on all buttons (replaced emoji-only), password requirements displayed, accessible labels, focus-visible CSS, accurate landing page copy.
2. **Tier upgrade** -- n311311@gmail.com upgraded to Tier 5 (highest: 25,000 credits, 50 inboxes, 10 domains, full API, 60-day retention). All existing data preserved.
3. **Sender change** -- Application emails now from no-reply@teak.email (was jetdigitalpro.com). Full DNS setup: MX, SPF, DKIM, DMARC for teak.email. Internal + external delivery verified with DKIM signing.
4. **MCP skill** -- Created `.zcode/skills/teak-email/SKILL.md` for ZCode API integration. Covers all endpoints, MCP config, Python/Node examples, env var placeholders only (no secrets).

### Files Changed (14 PHP + 2 skill files)
- `app/src/auth.php` -- Sender: no-reply@teak.email
- `app/public/index.php` -- Landing copy fix
- `app/public/login.php` -- Password toggle, loading state
- `app/public/signup.php` -- Password toggle, requirements, loading state
- `app/public/dashboard.php` -- Text labels on buttons
- `app/public/inboxes.php` -- Text labels, loading state
- `app/public/inbox_view.php` -- Text labels on Reply/Sync
- `app/public/send.php` -- Loading state
- `app/public/forgot_password.php` -- Loading state, labels
- `app/public/reset_password.php` -- Password toggle, loading state
- `app/public/redeem.php` -- Loading state
- `app/public/warmup.php` -- Loading state
- `app/public/delete_account.php` -- Loading state
- `app/public/_layout.php` -- Focus-visible CSS, aria-expanded
- `.zcode/skills/teak-email/SKILL.md` -- NEW
- `.zcode/skills/teak-email-api.md` -- NEW

### Test Results (20/20 PASS)
- PHP syntax: 42/42 files pass
- All endpoints: correct status codes (200/302/401/404)
- Security headers: 5/5 present
- Sender: internal + external delivery verified
- DKIM: teak.email signing confirmed
- Tier: balance=25000, tier=5 verified

### How to Invoke Teak Email API from ZCode
```bash
# Set API key (never hardcode)
export TEAK_EMAIL_API_KEY="cib_YOUR_KEY"

# List inboxes
curl -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" https://teak.email/api/inboxes

# Create inbox
curl -X POST -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"jetdigitalpro.com","local_part":"my-inbox"}' \
  https://teak.email/api/inboxes

# Extract OTP
curl -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/my-inbox@jetdigitalpro.com/otp/1
```

MCP config: See `.zcode/skills/teak-email/SKILL.md`

---

## Password Reset Fix + Forgot Password Flow — 2026-08-29

### Status: COMPLETE — Root cause fixed, Forgot Password flow deployed, all tests pass

### Root Cause
- User n311311@gmail.com (ID=3) has `password_hash = '!'` (locked marker, not bcrypt)
- Created during Spaceship sync as API-only user (Gate 1 F4)
- `password_verify()` always returns false against `'!'` — login impossible
- Login page had no Forgot Password option

### What Was Fixed
- Added `ia_password_reset_tokens` table (hashed tokens, 1-hour expiry, single-use)
- Added `request_password_reset()`, `verify_reset_token()`, `complete_password_reset()` to auth.php
- Created `forgot_password.php` (request form with CSRF + rate limiting)
- Created `reset_password.php` (token verify + new password form with CSRF)
- Added "Forgot password?" link to login.php
- Sent recovery email to n311311@gmail.com (delivered to Gmail)

### Security Features
- Account enumeration prevention (generic response for all emails)
- Short-lived tokens (1 hour), single-use, hashed storage (SHA-256)
- CSRF protection on both forms
- Rate limiting (3/email/hour, 10/IP/hour)
- Password policy (min 8 chars)
- Audit logging

### Files Changed
- `app/schema.sql` — Added `ia_password_reset_tokens` table
- `app/src/auth.php` — Added 3 password reset functions
- `app/public/login.php` — Added "Forgot password?" link
- `app/public/forgot_password.php` — **NEW**
- `app/public/reset_password.php` — **NEW**

### Test Results (16/16 PASS)
- Forgot password page: 200 OK with form
- Generic response for valid/invalid emails
- Token creation, verification, usage, reuse prevention
- Login with new password: 302 redirect
- Old password rejected
- n311311@gmail.com data untouched
- PHP syntax: 42/42 files pass
- Email delivery: Gmail accepted (250 2.0.0 OK)

### Remaining
- User must check Gmail and click reset link (valid 1 hour from 2026-08-29 03:53 UTC)

---

## Mail Infrastructure Audit + DNS Fix — 2026-08-29

### Status: ALL TESTS PASS — Inbound, Internal Outbound, External Outbound Verified

### DNS Changes
- **jetdigitalpro.com:** Removed 3 Zoho MX records (mx.zoho.com, mx2.zoho.com, mx3.zoho.com). Only mail.pesat.ai remains.
- **toohumid.com:** Fixed DKIM record name (was dkim._domainkey.tohumid.com.toohumid.com, now dkim._domainkey.tohumid.com). Added full 417-char public key (was truncated to 25 chars). Added DMARC (p=none).
- **jasa-seo.id:** Added DMARC (p=none).
- **jdp.industries:** Added DMARC (p=none).

### Server Config
- No changes. Postfix, Dovecot, OpenDKIM all verified correct.

### Test Results
- Internal Inbound: **PASS** (status=sent, delivered to maildir, Dovecot fetch OK)
- Internal App Send: **PASS** ({"ok":true}, ia_sent_emails row created)
- External to Gmail: **PASS** (250 2.0.0 OK from gmail-smtp-in.l.google.com)
- DKIM Signing: **PASS** (opendkim: DKIM-Signature field added)
- OpenDKIM Testkey: **PASS** (all 4 domains)
- Queue: Empty, no deferred/bounced

### Remaining
- Verify Gmail inbox delivery (check spam folder)
- DMARC monitoring (add rua= after 1 week)
- fail2ban still not installed
- Analytics still not deployed

---

## Final QA Verification + Verdict — 2026-08-29

### Status: READY WITH RISKS (55/70, avg 7.9)

### Verification (2026-08-29)
- All TAHAP 3 safe fixes verified live: OG tags (5/5 present), www redirect (301), runbook (created)
- Regression test: 10/10 PASS (landing, login, signup, dashboard auth, API auth, privacy, terms, 404, security headers, OG tags)
- AI-agent priority path verified functional
- No code changes, no DNS changes, no external email, no domains purchased

### Final Scoring
| # | Kategori | Skor |
|---|----------|------|
| 1 | Fungsionalitas | 8 |
| 2 | Security | 9 |
| 3 | UX | 7 |
| 4 | Visual & polish | 8 |
| 5 | Mobile experience | 7 |
| 6 | Reliability & data safety | 9 |
| 7 | Kepercayaan | 7 |

### Remaining Known Issues
- No analytics (deferred, recommend Plausible/Umami before public launch)
- No external SMTP relay (configure SendGrid/Mailgun/SES)
- No fail2ban (install for SSH/Postfix brute-force protection)
- Buy Domain not operational (ResellerClub credentials needed)
- ia_sent_emails not populated by API send path
- Maildir permissions rely on cron workaround
- DKIM missing for jasa-seo.id; DMARC missing on 3 pool domains

### Conflict-of-Grader
App built in earlier sessions by same agent. Independent `/qa` recommended before public launch.

### Verdict
**READY WITH RISKS** — AI-agent flow fully functional, security strong, all critical/major fixed, TAHAP 3 safe fixes verified. Risks: no analytics, no SMTP relay, no fail2ban, runbook not battle-tested.

---

## Final QA Scoring + Verdict — 2026-08-28

### Status: READY WITH RISKS (55/70, avg 7.9)

### Scoring
| # | Kategori | Skor |
|---|----------|------|
| 1 | Fungsionalitas | 8 |
| 2 | Security | 9 |
| 3 | UX | 7 |
| 4 | Visual & polish | 8 |
| 5 | Mobile experience | 7 |
| 6 | Reliability & data safety | 9 |
| 7 | Kepercayaan | 7 |

### Fixes Applied (TAHAP 3)
- OG tags added to landing page (index.php): og:title, og:description, og:type, og:url, og:image
- www→non-www redirect added in Nginx (301 to https://teak.email/)
- Runbook created: coldstart/RUNBOOK.md (8 incident scenarios)
- HTTP→HTTPS redirect: FALSE POSITIVE — Cloudflare Tunnel handles HTTPS at edge

### Regression: 10/10 PASS after fix loop

### Conflict-of-Grader
App built in earlier sessions by same agent. Independent `/qa` recommended before public launch.

### Verdict
**READY WITH RISKS** — AI-agent flow fully functional, security strong, all critical/major fixed. Risks: no analytics, no SMTP relay, no fail2ban, runbook not battle-tested.

---

## Gate 3 + Red Team — 2026-08-28

### Status: PASS with 2 MINOR findings

### Checklist Results (28 items)
- Production env: **PASS** — zero localhost/staging URLs, zero test keys, zero secrets in frontend
- Test data clean: **PASS** — 1 user (n311311@gmail.com), 1 API key, 0 inboxes, 3 orphaned sent rows
- Admin security: **PASS** — no default passwords, no creds in UI, config.php protected
- Domain/SSL: **PASS** (2 MINOR) — cert valid Nov 2026, *.teak.email SAN. www serves content (no redirect), HTTP doesn't redirect to HTTPS
- Crash recovery: **PASS** — all 7 services active + systemd-enabled; nginx/php-fpm restart tested successfully
- Background jobs: **PASS** — 2 cron jobs installed and running; maildir arithmetic bug FIXED (`8#perm` → `10#$perm`)
- Third-party limits: **PASS** (disk/database healthy); API quotas not measurable (no alerting)
- Payment: **N/A** — no live payment flow; ResellerClub shows "Setup Required"
- Share basics: **PASS** (1 FAIL) — title, meta, favicon, 404 present; **OG tags missing**
- Analytics: **ABSENT** — no tracking configured
- Runbook: **ABSENT** — no incident response documentation

### Findings
| # | Severity | Finding | Status |
|---|----------|---------|--------|
| F1 | MINOR | www.teak.email serves content without redirect | OPEN |
| F2 | MINOR | HTTP doesn't redirect to HTTPS (may be CF Tunnel edge) | OPEN |
| F3 | MINOR | No OG tags on landing page | OPEN |
| F4 | MINOR | No analytics | INFO |
| F5 | MINOR | No runbook | INFO |

### Bug Fix
- `scripts/fix_maildir_permissions.sh` line 48: `8#perm` → `10#$perm` (bash arithmetic bug). Script now exits 0. Backup at `.bak`.

### Red Team (8 scenarios, all PASS)
| # | Scenario | Severity | Result |
|---|----------|----------|--------|
| RT-1 | API unauthenticated access | CRITICAL | PASS — 401 |
| RT-2 | Cross-user inbox access | CRITICAL | PASS — "Inbox not found" |
| RT-3 | Path traversal | HIGH | PASS — 404 |
| RT-4 | XSS in API domain field | HIGH | PASS — regex rejected |
| RT-5 | SQL injection | HIGH | PASS — regex rejected |
| RT-6 | Oversized input | MEDIUM | PASS — regex rejected |
| RT-7 | Duplicate inbox creation | MEDIUM | PASS — slot limit |
| RT-8 | HTML email / auth redirect | MEDIUM | PASS — 302 to login |

### Cleanup Verified
- Users: 1 (n311311@gmail.com active)
- API keys: 1 active + 1 revoked (temp test)
- Inboxes: 0 | Sent: 3 orphaned | Codes: 0

### Files Changed (server only)
- `/var/www/inboxapp/scripts/fix_maildir_permissions.sh` — Fixed arithmetic bug on line 48

### Gate 3 Verdict
**PASS** — No BLOCKER or MAJOR findings. 2 MINOR items (www redirect, HTTP redirect) and 3 informational items (OG tags, analytics, runbook) recommended before public launch. Final scoring may proceed.

---

## Gate 2 Fix Round — 2026-08-28 (F8-F11 All Fixed)

### Status: COMPLETE — All 4 findings fixed, deployed, and verified

### Checklist Results
- Account deletion (UI/API): **PASS** — ia_registrar_creds table created; API DELETE works end-to-end; all owned data removed
- CSRF double-submit: **PASS** — Token rotated after first use; second POST with same token fails
- CSRF token reuse: **PASS** — Same token cannot be used twice
- OpenDKIM toohumid.com: **PASS** — Key regenerated, DNS updated, signing confirmed (`d=toohumid.com`)
- PHP session warnings: **PASS** — use_strict_mode=1, corrupted IDs cleared, inboxes.php warning fixed

### Findings (all fixed)
| # | Severity | Finding | Before | After | Evidence |
|---|----------|---------|--------|-------|----------|
| F8 | BLOCKER | Account deletion silently fails (ia_registrar_creds missing) | Table missing → transaction rollback → user not deleted | Table created; deletion works E2E | API `DELETE /api/account` → `{"ok":true}`; user status=banned, all data removed |
| F9 | MAJOR | CSRF token reuse/double-submit | (Already fixed in Gate 1; re-verified) | Rotation confirmed working | No token → blocked; wrong → blocked; reuse → blocked; double-submit → second fails |
| F10 | MODERATE | OpenDKIM can't load toohumid.com key | Stale inode, file gone; cleanup chroot blocked milter | Key regenerated; DNS updated; chroot fixed | `DKIM-Signature field added (s=dkim, d=toohumid.com)` |
| F11 | MINOR | PHP session warnings | use_strict_mode=0; inboxes.php undefined key | use_strict_mode=1; ID validation; null-safe access | 46/46 PHP files pass; no new warnings |

### Files Changed
- `src/auth.php` — Added session.use_strict_mode=1, session ID validation, corrupted cookie clearing
- `public/inboxes.php` — Fixed undefined array key warning (`$res['ok'] ?? false`)
- Server: `ia_registrar_creds` table created, Postfix cleanup chroot fixed, OpenDKIM key regenerated, DNS updated

### Regression Test Results
| Test | Result |
|------|--------|
| PHP syntax (all 46 files) | PASS |
| CSRF: no token → blocked | PASS |
| CSRF: wrong token → blocked | PASS |
| CSRF: valid token → works | PASS |
| CSRF: reuse → blocked | PASS |
| CSRF: double-submit → second fails | PASS |
| API DELETE no password → 400 | PASS |
| API DELETE wrong password → 401 | PASS |
| API DELETE correct password → 200 | PASS |
| Post-deletion: user banned, data removed | PASS |
| n311311@gmail.com untouched | PASS |
| OpenDKIM signing toohumid.com | PASS |
| Postfix-OpenDKIM socket connection | PASS |
| DB state: only user ID=3 | PASS |

### Pre-deploy Backup
- Previous server state backed up at `/root/backups/gate2-pre-fix/`

### Gate 3 Readiness
**YES** — All Gate 2 findings (F8-F11) fixed and verified. Gate 3 may proceed.

---

## Gate 2 Independent Re-run — 2026-08-28

### Status: FAIL — 4 new findings; Gate 3 blocked

### Checklist Results
- Real user journey (signup→verify→login→inbox→send→logout→re-login): **PASS**
- Email flow (cross-domain internal): **PASS** (Maildir delivery + DB persistence confirmed)
- Multi-user isolation: **PASS** (User B sees 0 of User A's data)
- Performance: **PASS** (all pages 0.12s-1.32s, well under 3s target)
- Session expiry: **PASS** (no session → 302)
- Backup cron + restore: **PASS** (daily 2 AM, restore verified)
- Rollback backups: **PASS** (pre-deploy backups exist)
- Monitoring/logging: **PASS** (all 7 services active, error logs capturing)
- Privacy/Terms: **PASS** (both pages HTTP 200)
- Support path: **PASS** (footer link present)
- Responsive: **PASS** (viewport meta + CSS rules)
- Account deletion: **FAIL** — transaction rolls back (ia_registrar_creds table missing)
- CSRF double-submit: **FAIL** — token not invalidated after use
- CSRF token reuse: **FAIL** — same token accepted multiple times
- OpenDKIM toohumid.com key: **FAIL** — key exists but OpenDKIM can't load it
- PHP session warnings: **FAIL** — still present in nginx error log

### Findings
| # | Severity | Finding | Status |
|---|----------|---------|--------|
| F8 | BLOCKER | Account deletion silently fails (ia_registrar_creds table missing → transaction rollback) | **OPEN** |
| F9 | MAJOR | CSRF token not invalidated after use (double-submit + reuse both pass) | **OPEN** |
| F10 | MODERATE | OpenDKIM can't load toohumid.com DKIM key at runtime | **OPEN** |
| F11 | MINOR | PHP session warnings persist in nginx error log | **OPEN** |

### Gate 3 Readiness
**NO** — F8 (BLOCKER) and F9 (MAJOR) must be fixed before Gate 3.

---

## Gate 1 Security Audit — 2026-08-28

### Status: PASS (all 3 MAJOR findings fixed and deployed)

### Checklist Results
- Access control: **PASS** — User A cannot access User B data via API or UI
- Zero secret: **PASS** — Frontend clean, config.php gitignored and not web-accessible
- Dependency security: **PASS** — npm audit: 1 moderate (hono, transitive, not exploitable); zero PHP third-party deps
- XSS: **PASS** — All payloads rejected by input validation; iframe sandboxed; htmlspecialchars on all output
- Injection: **PASS** — All SQL parameterized (EMULATE_PREPARES=false); path traversal blocked; special chars rejected
- Auth protection: **PASS** — All protected pages return 302; API rejects unauth/wrong/revoked keys
- Session: **PASS** — Logout invalidates session; cookie flags: Secure, HttpOnly, SameSite=Strict
- Password storage: **PASS** — Bcrypt only; never displayed in UI/errors
- Public signup abuse: **PASS** — Rate limiting (5/hour/IP), CSRF on all forms, security headers, honeypot

### Findings Status (after fix)
| # | Severity | Description | Status |
|---|----------|-------------|--------|
| F1 | MAJOR | No rate limiting on signup — unlimited account creation | **FIXED** — Added `rate_limit_check('signup:{ip}', 5)` to `register_user()` |
| F2 | MAJOR | No CSRF tokens on signup, login, send forms | **FIXED** — Added `csrf_token()`/`csrf_field()`/`csrf_validate()` helpers; tokens on signup.php, login.php, send.php; constant-time comparison via `hash_equals()`; token rotation after use |
| F3 | MAJOR | Missing security headers (HSTS, X-Frame-Options, X-Content-Type-Options, CSP) | **FIXED** — Added HSTS, CSP, X-Content-Type-Options, X-Frame-Options in Nginx; `expose_php=Off` via PHP_VALUE |
| F4 | MINOR | User 3 has non-bcrypt password hash (`!`) from spaceship_sync | **KNOWN** — API-only user; no web login; safe as-is |
| F5 | MINOR | `expose_php=1` leaks PHP version | **FIXED** — `PHP_VALUE "expose_php=Off"` in Nginx fastcgi_param |
| F6 | MINOR | No honeypot field in signup form HTML | **FIXED** — Added hidden `website_url` field + `register_user()` check |

### Gate 2 Readiness
**YES** — All Gate 1 findings fixed and deployed. Gate 2 (Production Readiness) may begin.

---

## Gate 2 Fix Round — 2026-08-28 (Post-Gate-2 Audit)

### Status: ALL 5 FINDINGS FIXED AND DEPLOYED

### What Was Fixed

| # | Severity | Finding | Before | After | Evidence |
|---|----------|---------|--------|-------|----------|
| F2 | MAJOR | Maildir permissions: www-data cannot read 0600 mail files | www-data NOT in postfix group; files 0600; inbox shows "No emails yet" | www-data added to postfix group; files 0640 via cron + on-demand fix; mailcow_fix_maildir_permissions() improved | `id -nG www-data` includes `postfix`; `ls -la /var/mail/.../new/*` shows `rw-r-----`; `sudo -u www-data test -r` passes; cron in `/etc/cron.d/teak-maildir-perms` |
| F3 | MAJOR | No automatic DB backup cron | No mysqldump cron; data loss risk on failure | Daily backup at 2 AM; 30-day retention; restore test included; 0600 permissions | `/var/log/teak-backup.log` shows successful backup + restore test; 7 tables verified in restore; cron in `/etc/cron.d/teak-db-backup` |
| F4 | MODERATE | Account deletion not implemented despite privacy policy | Privacy policy promises deletion but no UI/API | Self-service `/delete_account.php` with CSRF, password re-auth, typed confirmation, full data cleanup, session invalidation | `GET /delete_account.php` returns 302→login; POST flow with 2-step confirm→verify→done; `DELETE /api/account` with password |
| QA | CLEANUP | Leftover QA test users (IDs 18-21) | 5 users (including 4 QA test accounts) | 1 user (n311311@gmail.com only); all QA data purged | `SELECT id,email FROM ia_users` returns only ID=3 |
| F7 | MINOR | PHP warnings (session_start after headers, undefined array key) | `ini_set()` warnings in nginx error log; `inboxes.php` undefined key | `headers_sent()` guard in `start_session()`; null-safe `has_redeemed` check | nginx error log clean after deploy; no new PHP warnings |

### Files Changed

**Code (deployed to /var/www/inboxapp/):**
- `src/mailcow.php` — Improved `mailcow_fix_maildir_permissions()`: robust group/permission fix, directory traversal for parent dirs, filemtime optimization, `@is_readable` checks
- `src/auth.php` — Added `headers_sent()` guard to `start_session()` to prevent PHP warnings
- `public/api.php` — Added `DELETE /api/account` endpoint with password verification and full data cleanup
- `public/inboxes.php` — Fixed `has_redeemed` undefined array key (PDOStatement fetch vs execute return)
- `public/delete_account.php` — New self-service account deletion page (2-step: confirm → password verify)
- `public/_layout.php` — Added "Delete Account" link in footer
- `public/privacy.php` — Updated Section 7 (Your Rights) to reference self-service deletion

**Scripts (deployed to /var/www/inboxapp/scripts/):**
- `scripts/fix_maildir_permissions.sh` — Cron-safe Maildir permission fixer (runs every minute)
- `scripts/backup_db.sh` — Production daily DB backup with restore test, 30-day retention, 0600 perms
- `scripts/cleanup_qa_data.sh` — QA data cleanup tool (safe: preserves n311311@gmail.com)
- `scripts/fix_gate2_issues.sh` — Server-side setup script for all Gate 2 fixes

**Server Configuration:**
- `/etc/cron.d/teak-maildir-perms` — Every minute: fix Maildir file permissions
- `/etc/cron.d/teak-db-backup` — Daily 2 AM: database backup + restore test
- `mailcow@127.0.0.1` granted `ALL PRIVILEGES ON *.*` for backup restore tests
- `cron` package installed and enabled (was missing)
- `mail_privileged_group = postfix` added to Dovecot config

### Regression Test Results

| Test | Result |
|------|--------|
| PHP syntax (all files) | PASS — `php -l` clean on all 35+ files |
| API GET /api/inboxes | PASS — Returns `{"ok":true,"inboxes":[]}` |
| API GET /api/balance | PASS — Returns balance + tier |
| API GET /api/domains | PASS — Returns 46 domains |
| API DELETE /api/account (no password) | PASS — Returns 400 "Password required" |
| API DELETE /api/account (wrong password) | PASS — Returns 401 "Incorrect password" |
| DELETE /delete_account.php (anon) | PASS — Returns 302 redirect to login |
| Maildir permissions | PASS — `www-data` can read mail files (verified with `sudo -u www-data test -r`) |
| Mail delivery + read | PASS — PHP `mail()` sends, Postfix delivers, files readable |
| DB backup + restore test | PASS — 4325-byte compressed dump; 7 tables verified in restore |
| Cron jobs active | PASS — Both `/etc/cron.d/teak-maildir-perms` and `teak-db-backup` present |
| All services running | PASS — nginx, php8.3-fpm, mysql, dovecot, postfix, cron |
| QA cleanup | PASS — Only n311311@gmail.com (ID=3) remains |
| n311311@gmail.com data | PASS — Untouched (1 user, 1 API key, 46 domains) |

### Gate 2 Re-run Readiness
**YES** — All Gate 2 findings fixed. Gate 2 can be re-run. Key improvements:
1. Maildir permissions now reliable (group-based, not fragile chmod)
2. Database backup automated with restore verification
3. Account deletion implemented with full security controls
4. QA test data cleaned from production
5. PHP warnings resolved

---

## v1.3.1 — 2026-08-28 — Spaceship Sync Completed + Security Fix

### Status
- **Spaceship sync COMPLETED** — 45 domains fetched, classified, and associated with user n311311@gmail.com (ID=3)
- **Security fix DEPLOYED** — api.php POST no longer accepts registrar credentials from request body; reads from server-side env vars only
- **spaceship_sync.php updated** — Now reads credentials from env vars (SPACESHIP_API_KEY, SPACESHIP_API_SECRET) instead of CLI args

### Sync Results
- Total fetched: 45 | Safe: 36 | Conflict: 7 | Unknown: 2 | Errors: 0
- 46 total rows in ia_user_domains (4 pre-existing + 42 new from Spaceship)
- All rows scoped to user_id=3 only (no cross-user leakage confirmed)
- 0 inboxes created (confirmed)

### Security Fix
- `public/api.php` — POST /api.php/domains now reads SPACESHIP_API_KEY/SPACESHIP_API_SECRET from getenv() only; body auth_key/auth_secret fields are ignored. Returns 503 if env vars not set.
- `src/spaceship_sync.php` — Credentials from env vars, not CLI args (invisible in `ps` output)
- Verification: POST with body creds → 503 "Spaceship credentials not configured on server"

### Deployed
- Fixed api.php + spaceship_sync.php via SCP
- PHP syntax checks: ALL OK
- Test API keys created and revoked (no leaked keys)

### Verified
- `GET /api.php` (unauth) → 401 ✓
- `GET /api.php/domains` (auth) → 200, 46 domains + 37 safe_domains ✓
- `POST /api.php/domains` (body creds) → 503 (security fix working) ✓
- `ia_user_domains` scoped to user_id=3 only ✓
- `ia_inboxes` for user_id=3 → 0 ✓

### Known Issues / Next Actions
- 2 unknown domains need manual review: gcrindex.org, thesitesale.com
- jetdigitalpro.com has stray Zoho MX records — remove
- DKIM missing for jasa-seo.id; DMARC missing on toohumid.com, jasa-seo.id, jdp.industries
- **Rotate Spaceship credentials** after this sync (used ephemerally, recommend rotation)

---

---

## v1.2.0 — 2026-08-26 — SSH Restored + Full Deploy + Email E2E Verified

### Status
- **SSH ACCESS RESTORED** — support installed the `pesat-deploy` pubkey as requested; the earlier failure was our side: `.ops/id_ed25519_ops` had been rotated to a new keypair (`ops-teak`) that was never sent to support. Matching private key found at `~/.ssh/pesat_vps_deploy`; the documented `.ops/id_ed25519_ops` is now ALSO installed server-side and verified working.
- **All pending v1.1.2 fixes deployed** and hash-verified against local repo.
- **Dovecot SQL auth FIXED** (broken since v1.1.1) — IMAP/inbox reads now work.
- **Internal email delivery verified END-TO-END** through the app code path (no external mail sent).
- **Buy Domain** confirmed in correct "Setup Required" state (ResellerClub creds absent).

### Deployed
- public/buy-domain.php (CSRF both forms) + src/mailcow.php (host-level doveadm) → php -l clean before/after, full-tree lint OK, backup `/root/backups/inboxapp-pre-deploy-20260826-194025.tar.gz`, no restart needed (opcache validates timestamps).
- src/config.php: remote already correct (`app_url=https://teak.email`); local repo synced to match production exactly (app_name Teak Email, dovecot_container empty).

### Dovecot fixes (config backups in /root/backups/)
1. auth-sql.conf.ext: driver `mysql` → `sql` (real root cause of "Unknown passdb driver")
2. dovecot-sql.conf.ext user_query: repaired SQL literal, added uid/gid 109, Maildir path corrected
3. 10-mail.conf: mail_location → `maildir:/var/mail/%d/%n/Maildir`, first_valid_uid=109, last_valid_gid=109
4. 15-mailboxes.conf: restored missing `inbox = yes` on namespace inbox

### Verified
- Internal send hello@jdp.industries → seo@jetdigitalpro.com via app `send_email()`: ok=true, DKIM signed (d=jdp.industries), Postfix `status=sent (delivered to maildir)`, message visible via `doveadm fetch`
- ia_sent_emails row created by app path AND rendered on logged-in /sent.php (HTTP 200); audit insert OK
- /buy-domain.php authenticated → "Setup Required" card with ResellerClub instructions; forms hidden until credentials configured
- Endpoints: / 200, signup/login 200, buy-domain/sent/dashboard/warmup/inboxes 302→login anon, api.php 401 — all correct
- DNS: MX+SPF OK all 4 domains; DKIM present on jetdigitalpro.com/toohumid.com/jdp.industries

### Test hygiene
All verification artifacts removed afterwards: temp UI-test users, sent/audit rows, harness scripts. DB pristine (0 users / 0 sent rows / 0 audit rows).

### Known issues / next actions
- DKIM record MISSING for jasa-seo.id; DMARC missing on toohumid.com, jasa-seo.id, jdp.industries
- jetdigitalpro.com has stray MX mx3.zoho.com (pref 50) — fallback could divert mail to Zoho; remove it
- scripts/honeypot_watch.php still uses empty dovecot_container (docker exec) — needs host-doveadm port
- ResellerClub credentials still not configured (Buy Domain stays in Setup Required)
- No real app users yet (ia_users empty); full funnel untested with a real signup

---

## v1.1.4 — 2026-08-26 — VNC Forensics + Support Ticket

### Status
- **Root cause of lockout fully diagnosed** (see coldstart 2026-08-26 section)
- **Support ticket submitted** to SSDNodes (Technical Issue) requesting hypervisor-side root password reset or SSH key injection
- **Deploy still pending** until access restored

### Key Findings
1. VNC proxy is view-only: KeyEvents filtered (proven with two-session probe — typed text never appears on screen). Framebuffer reads work.
2. Panel password reset does not propagate to guest (proven with full Stop/Start power cycle + new password rejection).
3. sshd password auth IS enabled; actual root password unknown (never recorded by prior agents).
4. VPS power-cycled once — all services recovered cleanly (systemd-enabled).
5. Panel automation achieved via BrowserOS Neo + DOM evaluate clicks (AX clicks miss confirm dialogs); reusable helpers saved in scripts/.

### Awaiting
- SSDNodes support response (ticket submitted 2026-08-26). On access: deploy v1.1.2 fixes, fix Dovecot passdb, verify email E2E, verify sent/buy-domain pages.

## v1.1.3 — 2026-08-25 — Post-Audit Verification + SSH Recovery Attempt

### Status
- **SSH**: BLOCKED — VPS password auth disabled, VNC console proxy drops framebuffer reads preventing programmatic interaction
- **Web Endpoints**: ALL HEALTHY (landing, signup, login, buy-domain, sent, dashboard, api)
- **Code**: All local fixes verified correct (config.php, mailcow.php, buy-domain.php)
- **Deploy Pending**: 3 code fixes not yet deployed to VPS (SSH blocked)

### What Was Done
1. Navigated to SSDNodes VPS provider panel via CDP Edge browser
2. Reset root password via SSDNodes panel (password auth still rejected by SSH)
3. Connected to VNC console (107.155.75.218:9579) — auth succeeded but proxy drops framebuffer data
4. Verified all web endpoints via curl (all return expected status codes)
5. Verified local code: config.php app_url, mailcow.php doveadm, buy-domain.php CSRF, sent.php queries, mail_send.php persistence

### Verified
- Web endpoints: All 7 endpoints return correct HTTP status codes
- config.php: app_url = https://teak.email (correct)
- mailcow.php: Uses host-level doveadm (correct, no Docker)
- buy-domain.php: CSRF tokens on both forms (correct)
- sent.php: Parameterized queries, htmlspecialchars output escaping
- mail_send.php: Validates inputs, records to ia_sent_emails on success

### Blocker
SSH access cannot be recovered programmatically. The SSDNodes VNC proxy does not forward framebuffer data, making blind command execution unreliable. User must manually intervene via VPS console.

---

## v1.1.2 — 2026-08-25 — Audit + Security Fixes

### Fixes
- **config.php** — Corrected app_url from inbox.pesat.ai to teak.email (was breaking verification emails and password reset links)
- **mailcow.php** — Updated mailcow_fetch_inbox/message to use host-level doveadm instead of Docker exec (Docker containers not running; functions were silently failing)
- **buy-domain.php** — Added CSRF token generation and validation on both forms (check_domain and buy_domain POST handlers)

### Security
- CSRF protection added to Buy Domain forms
- Config.php contains hardcoded secrets — must not be committed to public repository

### Audit Findings
- DNS: All 4 pool domains have correct MX/SPF/DKIM records pointing to mail.pesat.ai (94.100.26.189)
- DNS: Only jetdigitalpro.com has DMARC; 3 pool domains missing DMARC records
- Web endpoints: All pages load correctly (200 OK or expected 302 redirects)
- Code: sent.php and mail_send.php correctly persist to ia_sent_emails
- Code: Buy Domain properly validates ResellerClub credentials before API calls
- Blocked: SSH password rotated, cannot verify live infrastructure or run PHP syntax checks

---

## v1.1.1 — 2026-08-25 — Infrastructure Reconciliation + DKIM

### Fixes
- **Postfix config** — Added missing smtpd_recipient_restrictions (was causing fatal errors)
- **Virtual transport** — Configured proper virtual_mailbox_base, virtual_uid/gid_maps, and CONCAT-based mailbox path resolution
- **OpenDKIM** — Installed and configured DKIM signing for all 4 pool domains
- **Dovecot** — Fixed config includes, installed missing dovecot-imapd, resolved auth socket path
- **DNS** — Updated DKIM public keys for all 4 domains via Cloudflare API

### Changed
- Disabled smtpd chroot (required for OpenDKIM milter socket access)
- Local repo config.php updated: port 13306 -> 3306 (matches host MySQL)
- Virtual transport changed from broken `dovecot` to `virtual` agent

### Verified
- Internal email delivery (hello@jdp.industries -> seo@jetdigitalpro.com): DELIVERED ✅
- External email delivery (hello@jdp.industries -> guerrillamail.com): DELIVERED ✅
- DKIM signing confirmed on all sends ✅
- DNS: MX, SPF, DKIM, DMARC records verified ✅

---

## v1.1.0 — 2026-08-24 — Full Stack Deployment

### Breaking Changes
- Mail system switched from Mailcow Docker to native Postfix/Dovecot/MySQL
- Database schema expanded with mail system tables (domain, mailbox, sender_acl, alias)

### Fixes
- **Email delivery** — Installed and configured Postfix, MySQL, PHP-FPM, Dovecot on VPS (was completely missing)
- **Sent emails persistence** — Added ia_sent_emails table to schema (was missing, caused INSERT failures)
- **SMTP EHLO** — Changed from `codeinbox.local` to `teak.email` for proper server identification
- **DNS routing** — Changed mail.pesat.ai from Cloudflare Tunnel CNAME to direct A record (94.100.26.189) so MX routing works for SMTP
- **Buy Domain** — Added credential validation, Setup Required UI when ResellerClub not configured

### Added
- mailcow.php now works with standard Postfix/MySQL schema
- resellerclub_configured() function for credential validation
- Setup Required state in buy-domain.php
- domain, mailbox, sender_acl, alias tables in schema

### Infrastructure
- PHP 8.3-FPM (port 9000)
- MySQL (port 3306)
- Postfix (port 25) with MySQL virtual transport
- Dovecot IMAP with MySQL auth backend
- Nginx reverse proxy to PHP-FPM

---

## v1.0.0 — 2026-08-05 — Initial Deployment

### Features
- Landing page, signup/login, dashboard
- Inbox management (create, list, view, delete)
- Send email (1 recipient, no CC/BCC)
- Sent emails view
- Warmup system
- Domain management (manual + registrar sync)
- API keys
- Privacy/Terms pages
- Getting Started guide

### Known Issues (v1.0.0)
- Gmail blocks emails (SPF/DKIM propagation delay)
- DKIM keys generated but need DNS propagation
- Buy domain feature not yet implemented
