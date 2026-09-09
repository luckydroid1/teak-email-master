# Teak Email — Business & Technical Context
**Version:** 1.0  
**Date:** 2026-08-04  
**Previous Brand:** CodeInbox → Rebranded to **Teak Email**  
**Domain:** https://teak.email (was inbox.pesat.ai)

---

## 🎯 Executive Summary

**Teak Email** adalah email infrastructure platform yang melayani 3 segmen pengguna sekaligus:

1. **Agency owners / multi-site operators** — butuh manage banyak email dari 1 dashboard
2. **Vibe coders / developers** — butuh hassle-free signup/OTP tanpa repot setup Gmail API
3. **AI agent builders** — butuh email automation via MCP protocol untuk AI workflows

**Unique Value Proposition:**  
Satu-satunya provider yang menawarkan **Dashboard + REST API + MCP Server** dalam satu stack. Kompetitor (Mailinator, TempMail) hanya punya web UI, tidak ada API proper, apalagi MCP untuk AI agents.

---

## 📊 Full Keyword Research (40 Keywords)

### Primary Targets (High Volume)

| # | Keyword | Search Intent | Target Audience | Priority | Est. Monthly Vol | Competition |
|---|---------|---------------|-----------------|----------|------------------|-------------|
| 1 | temp email | Butuh email cepat tanpa login Gmail | Vibe coder | 🥇 | 50K-100K | Tinggi |
| 2 | email api | Otomasi email via code/agent | Agent builder | 🥇 | 10K-30K | Sedang |
| 3 | email testing | Test flow email sebelum production | Developer | 🥇 | 🔥🔥🔥 Tinggi | Sedang |
| 4 | inbox management | Centralisasi banyak email 1 dashboard | Agency owner | 🥇 | 🔥🔥 Sedang | Rendah |
| 5 | email automation | Automasi seluruh flow email | Vibe coder | 🥇 | 🔥🔥🔥 Tinggi | Sedang |
| 6 | otp api | Ambil OTP otomatis di agent workflow | Vibe coder | 🥈 | 1K-5K | Rendah |
| 7 | email verification | Verifikasi akun di automation flow | Developer | 🥈 | 🔥🔥🔥 Tinggi | Tinggi |
| 8 | email parser | Ekstrak kode/link dari email | Vibe coder | 🥈 | 🔥🔥 Sedang | Rendah |
| 9 | email webhook | Trigger action saat email masuk | Agent builder | 🥉 | 500-2K | Sangat rendah |
| 10 | email sandbox | Sandbox terisolasi untuk testing | QA engineer | 🥉 | 🔥 Sedang | Sangat rendah |

### Secondary Targets (Niche but High Intent)

| # | Keyword | Search Intent | Target Audience | Priority | Est. Monthly Vol | Competition |
|---|---------|---------------|-----------------|----------|------------------|-------------|
| 11 | email check | Cek email masuk tanpa buka webmail | Vibe coder | 🥉 | 🔥 Rendah | Rendah |
| 12 | email aliases | Banyak alamat email terpisah | Multi-site owner | 🥉 | 🔥 Sedang | Rendah |
| 13 | email routing | Alokasi email per domain/per klien | Agency | 🥉 | 🔥 Sedang | Rendah |
| 14 | multi domain email | Kelola email dari banyak domain | Multi-brand owner | 🥉 | 🔥 Rendah | Sangat rendah |
| 15 | per domain email | Email terpisah per brand/site | Agency | 🥉 | 🔥 Rendah | Sangat rendah |
| 16 | mail test | Test apakah email benar masuk | Developer | 🥉 | 🔥 Sedang | Sangat rendah |
| 17 | email monitoring | Pantau semua inbox dari 1 tempat | Multi-site owner | 🥉 | 🔥🔥 Sedang | Rendah |
| 18 | email forward | Forward otomatis ke tempat lain | Agent builder | - | 🔥 Rendah | Rendah |
| 19 | email proxy | Proxy email untuk agent | Agent builder | - | 🔥 Rendah | Rendah |
| 20 | virtual mailbox | Email tanpa physical server | Developer | - | 🔥 Rendah | Rendah |

### Legacy Keywords (CodeInbox era — masih relevan tapi perlu repositioning)

| # | Keyword | Old Positioning | New Positioning (Teak Email) |
|---|---------|-----------------|------------------------------|
| 1 | disposable email | Temporary/disposable branding | Professional email testing |
| 2 | catchall email | Spammy perception | Multi-domain inbox management |
| 3 | mailcow api | Self-hosting focus | Cloud-managed Mailcow infrastructure |
| 4 | temp inbox | Disposable stigma | Rapid dev workflow optimization |

---

## 🏗️ Architecture Decision Document

### Product Stack Overview

```
┌─────────────────────────────────────────────────────┐
│                 END USER                             │
│  (human via dashboard / AI agent via API/MCP)        │
└────────────┬────────────────────────────────────────┘
             │
    ┌────────▼──────────┬──────────────────▼──────────┐
    │   Dashboard (Web UI)  │   API Layer              │
    │   - Create inbox      │   - REST API (/api/*)   │
    │   - Read emails       │   - Auth: Bearer token  │
    │   - Manage keys       │   - OTP extractor       │
    │   - Redeem codes      │                         │
    └──────────┬────────────┴───────────┬─────────────┘
               │                        │
         ┌─────▼────────┐       ┌──────▼────────┐
         │ Mailcow SMTP │       │ MCP Server    │
         │ IMAP/SMTP    │◄──────│ Streamable    │
         │ MySQL        │       │ HTTP transport│
         └──────────────┘       └───────────────┘
                                          │
                                  ┌───────▼────────┐
                                  │ AI Agents      │
                                  │ Claude/Cursor  │
                                  │ Other MCP clients│
                                  └────────────────┘
```

### Layer Breakdown

| Layer | Tech Stack | Purpose | Who Uses |
|-------|------------|---------|----------|
| **Dashboard** | PHP 8.3, vanilla JS, Tailwind CSS | Human users: create inbox, read email, manage API key | Agency owners, multi-site operators |
| **REST API** | Native PHP endpoints at `/api/*` | CRUD inbox, fetch email, extract OTP via regex | Scripts, custom integrations, mobile apps |
| **MCP Server** | Node.js (`/mcp` endpoint), proxies to REST API | Translate MCP protocol → REST API calls | AI agents (Claude, Cursor, GPT tools) |
| **Infrastructure** | Mailcow Docker, Nginx, Cloudflare Tunnel | Actual email delivery/storage | All users |

### Why Both API + MCP Matters?

**REST API — Universal:**
```bash
curl -H "Authorization: Bearer $KEY" https://teak.email/api/inboxes
```
- Semua orang bisa pakai: curl, Python scripts, Zapier, Make, dll
- Tidak perlu MCP-specific client
- Fallback untuk non-AI use cases

**MCP Server — Exclusive for AI:**
```json
// Claude Desktop / Cursor config
{
  "mcpServers": {
    "codeinbox": { 
      "command": "npx", 
      "args": ["-y", "@teakemail/mcp"] 
    }
  }
}
```
→ Agent bisa langsung: `"Cek inbox userX, ambil OTP dari email terakhir"`

**Competitive Moat:**  
Mailinator = web UI only.  
SendGrid/Mailgun = enterprise pricing, no MCP support.  
**Teak Email** = one-stack solution: dashboard + API + MCP = unique positioning.

---

## 🎨 AppSumo Messaging Strategy

### What to Say (✅ Good)
> "Connect your AI agent — works with Claude, Cursor, and any tool that supports email automation"

### What NOT to Say (❌ Bad)
> "MCP server dengan Streamable HTTP transport"

### Rationale:
AppSumo buyers = non-technical decision makers. Don't scare them with jargon. Frame it as **"works with popular tools"** not technical protocol details.

---

## 🚀 Deployment History

### Chronological Timeline

#### Phase 1: CodeInbox MVP Launch (inbox.pesat.ai)
- ✅ Initial build: PHP dashboard + REST API
- ✅ Mailcow dockerized with auto-restart policy
- ✅ Basic security: bcrypt, prepared statements, cookie hardening

#### Phase 2: QA Audit & Fixes
**Issues Found:**
| # | Issue | Severity | Fix Applied |
|---|-------|----------|-------------|
| 1 | No 404 page | MAJOR | index.php detects non-root URI → returns 404 status |
| 2 | No cron jobs | MAJOR | honeypot_watch (5min), daily_rent (3am) via crontab |
| 3 | Nginx error_page broken | MAJOR | Logic moved to app layer in index.php |
| 4 | CSRF vulnerable | HIGH | delete via GET protected by confirm() JS (curl-bypassable) |
| 5 | Credentials public GitHub | CRITICAL | Identified, needs rotation |

**QA Final Scorecard:**
| Category | Score | Notes |
|----------|-------|-------|
| Fungsionalitas | 8/10 | Core features work E2E |
| Security | 7/10 | Protected SQLi/XSS, no CSRF tokens |
| UX | 6/10 | Landing clear, loading indicators missing |
| Visual & Polish | 5/10 | Dark theme consistent, no favicon |
| Mobile Experience | 5/10 | @media queries exist, desktop-first |
| Reliability | 6/10 | Cron new, backup tested, no error monitoring |
| Kepercayaan | 3/10 | Honest copy, no legal pages |

**Verdict:** ❌ **NOT READY FOR REAL USERS**  
Blocking issues: credentials exposure, no CSRF, no privacy policy/Terms

---

#### Phase 3: Rebranding to Teak Email (teak.email)
**Changes Made:**
- [x] Domain shift: `inbox.pesat.ai` → `teak.email`
- [x] Nginx config updated: `teak.email.conf` deployed
- [x] Branding updates: all pages "CodeInbox" → "Teak Email"
- [x] Favicon SVG placeholder added
- [x] Cloudflare Tunnel ingress updated
- [x] DNS CNAME record created: `teak` → `<tunnel-id>.cfargotunnel.com`
- [x] AppSumo codes generated: 120 total
  - Tier 1 ($37): 50 codes
  - Tier 3 ($97): 50 codes
  - Tier 5 ($197): 20 codes

**DNS Status:** ⏳ Propagating (15-30 min typical, can take hours)  
**Current workaround:** Direct IP + Host header testing available

---

#### Phase 4: Production Hardening (To-Do)

**CRITICAL PRIORITY:**
1. Rotate ALL credentials:
   - MySQL root password
   - MySQL mailcow user password
   - SSH root password (VPS 94.100.26.189)
   - Cloudflare API token (master token)
   - Replace hardcoded values in `/var/www/inboxapp/src/config.php`
   - Replace in `/etc/codeinbox-mcp.env`
   - Delete/archive public repo exposing these credentials

2. Add CSRF protection:
   - POST-based delete with CSRF token (session-based)
   - Validate token server-side before delete

3. Legal pages:
   - Privacy Policy (minimal 1 paragraph)
   - Terms of Service
   - Contact support link

4. Analytics & Monitoring:
   - Google Analytics or Plausible (privacy-friendly)
   - Error logging to file/external service
   - Uptime monitoring (UptimeRobot/UptimeKuma)

5. Favicon polish:
   - Custom SVG icon (replace current placeholder)
   - OG images for social sharing

---

## 🛡️ Security Sign-Off Summary

| Attack Vector | Test Result | Implementation |
|--------------|-------------|----------------|
| SQL Injection | ✅ Protected | Prepared statements + input validation |
| XSS | ✅ Protected | filter_var(EMAIL_VALIDATE_EMAIL) + regex |
| Access Control (API) | ✅ Blocked unauthorized reads | Ownership check on inbox retrieval |
| Access Control (Web) | ✅ Redirects to login | Session middleware |
| Brute Force | ✅ Lockout after 5 attempts | 5-minute cooldown |
| Honeypot | ✅ admin@domain rejected | Invalid local part detection |
| Session Hijack | ✅ Hardened cookies | secure; HttpOnly; SameSite=Strict |
| Password Storage | ✅ bcrypt hash | $2y$10$... salted |
| npm audit | ✅ 0 vulns | MCP server dependencies clean |
| CSRF Token | ⚠️ Weak protection | JS confirm() only, bypassable via curl |
| Credential Exposure | 🔴 CRITICAL | Public GitHub repo — rotate NOW |

---

## 📁 Files Location Reference

| File | Purpose | Status |
|------|---------|--------|
| `PRD-v1.0.md` | Product Requirements Document | ✅ Complete |
| `coldstart.md` | Operational context + credentials list | ✅ Updated |
| `README.md` | Public-facing documentation | ✅ Ready |
| `pitch-deck.html` | Interactive HTML pitch deck | ✅ Created |
| `pitch-deck.pdf` | PDF version for investors | ✅ Exported |
| `BUSINESS-CONTEXT.md` | This document — master reference | ✅ Created |
| `app/nginx/teak.email.conf` | Nginx server block config | ✅ Deployed |
| `app/public/index.php` | Main entry point with 404 logic | ✅ Fixed |
| `app/public/_layout.php` | Global layout template | ✅ Branded |
| `app/appsumo-listing.md` | AppSumo listing content | ✅ Generated |

---

## 🔑 Critical Credentials (ROTATE IMMEDIATELY)

⚠️ **These are currently exposed in public GitHub repo!**

| Credential | Current Value | Location | Rotation Priority |
|------------|--------------|----------|-------------------|
| MySQL root password | `N5pas4iqop2VMNYzPHc0vxmtGF8O` | Config files | 🔴 CRITICAL |
| MySQL mailcow user | `VDAs9CgVobI7GBspUMwfb2aeZtng` | mailcow config | 🔴 CRITICAL |
| SSH root password | `ymif5avvYc` | VPS access | 🔴 CRITICAL |
| Cloudflare master token | `cfut_REDACTED_CLOUDFLARE_TOKEN` | CF API | 🔴 CRITICAL |
| CF Master Token (alt) | `n311311-master-token` | Environment | 🔴 CRITICAL |

**Rotation Checklist:**
- [ ] Change MySQL root password
- [ ] Change mailcow database user password
- [ ] Generate new SSH key pair (disable password auth)
- [ ] Regenerate Cloudflare API token
- [ ] Update all config files with new credentials
- [ ] Delete or private the GitHub repo exposing old creds

---

## 📈 Go-Live Readiness Checklist

| Item | Status | Notes |
|------|--------|-------|
| Clean prod environment | ✅ | Fresh deployment, no test data |
| Rate limits reset | ✅ | Account lockouts cleared |
| SSL/TLS working | ✅ | Cloudflare free SSL active |
| Backup strategy | ✅ | Daily cron job configured |
| Auto-recovery | ✅ | Docker restart policies set |
| Analytics installed | ❌ | Not yet deployed |
| Runbook documented | ❌ | One-page recovery guide needed |
| Support contact | ❌ | No email/form for user help |
| User account deletion | ❌ | No self-service delete |
| Third-party quota monitoring | ❌ | Cloudflare API limits unmonitored |
| CDN cache warming | ⏳ | Waiting DNS propagation |
| Load testing | ❌ | No stress tests run |

**Final Verdict:** ❌ **NOT READY FOR PUBLIC LAUNCH**  
Proceed only for: beta testing with controlled user group

**Path to ⚠️ READY WITH RISKS:**
1. Rotate all credentials ✓
2. Add CSRF tokens ✓
3. Publish privacy policy + ToS ✓
4. Add favicon ✓

**Path to ✅ PRODUCTION READY:**
1. Everything above +
2. Error monitoring deployed
3. Analytics tracking live
4. Runbook written & tested
5. Support channel functional
6. User account deletion feature

---

## 💡 Future Roadmap Ideas

### v1.1 Features
- [ ] Multi-domain email management (BYOD — Bring Your Own Domain)
- [ ] Advanced filtering rules
- [ ] Email forwarding automation
- [ ] Webhook notifications for incoming email
- [ ] API rate limiting dashboard

### v2.0 Enterprise
- [ ] Team collaboration features
- [ ] Role-based access control
- [ ] Audit logs for compliance
- [ ] SSO integration (Google Workspace, Okta)
- [ ] SLA guarantees

### Strategic Initiatives
- [ ] Build branded MCP package (@teakemail/mcp)
- [ ] SDK for common languages (Python, Node, Go)
- [ ] Marketplace integrations (Zapier, Make.com, n8n)
- [ ] Affiliate program for agency partners

---

## 🧭 Decision Log

| Date | Decision | Rationale | Owner |
|------|----------|-----------|-------|
| 2026-08-04 | Rebrand CodeInbox → Teak Email | Remove spammy connotation of "temporary email", position as professional infrastructure | PM |
| 2026-08-04 | Focus SEO on "email automation" vs "temp email" | Lower competition, higher intent, better fit for API+MCP positioning | Growth |
| 2026-08-04 | Keep Mailcow backend despite "self-hosting" stigma | Proven stability, Docker auto-restart, easy backups outweigh perceived complexity | Tech Lead |
| 2026-08-04 | Launch AppSumo with 3-tier pricing | Test market willingness to pay, generate early revenue + case studies | Founder |
| 2026-08-04 | Delay public launch until credentials rotated | Security first — don't expose paid users to credential theft risk | CTO |

---

## 📞 Contacts & Resources

**Development Team:**
- Project Manager: [@user](https://github.com/user)
- Tech Lead: [Internal team member]
- Designer: [External contractor]

**External Services:**
- VPS Provider: Hetzner (94.100.26.189)
- Email Backend: Mailcow Docker
- CDN/SSL: Cloudflare (free tier)
- Hosting: Local VPS behind Cloudflare Tunnel
- Payment: AppSumo (lifetime deals)

**References:**
- Mailcow docs: https://docs.mailcow.email/
- MCP spec: https://modelcontextprotocol.io/
- AppSumo guidelines: https://partners.appsumo.com/guidelines/

---

**Document Last Updated:** 2026-08-04  
**Maintainer:** PM Team  
**Next Review:** Pre-launch (after credential rotation complete)
