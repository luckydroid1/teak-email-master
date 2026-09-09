# 📄 Product Requirements Document (PRD) — Teak Email v1.0

**Version:** 1.0  
**Date:** 2026-07-31  
**Author:** Nell VH  
**Status:** Ready for Launch  

---

## 1. Executive Summary

**Teak Email** adalah platform email infrastructure untuk **multi-site business owners** dan **AI agent builders**.

### Problem Statement
- Business owners dengan banyak domain (5-50 sites) kesulitan manage email per domain secara terpisah → inbox tercampur, password chaos, no unified view.
- AI agent developers (vibe coders) stuck di "OTP wall" — Gmail blocks automation, temp mail services banned everywhere.
- Developer butuh email testing infrastructure (sandbox, API, parsing) tapi solusi existing terlalu complex atau expensive.

### Solution
Platform single-panel email management:
- **Web Dashboard**: Create/manage inboxes via simple UI (2 clicks).
- **REST API**: CRUD + OTP extractor via HTTP.
- **MCP Server**: Connect AI agents (Claude, Cursor, GPT) to read emails & extract codes automatically.

---

## 2. Target Audience

| Persona | Pain Point | Willingness to Pay |
|---------|------------|-------------------|
| **Multi-Site Agency Owner** | Manage 10-50 client emails → inbox chaos, no unified dashboard | $9-49/mo or lifetime deal |
| **AI Agent Builder / Vibe Coder** | "My agent can't get OTPs from Gmail" — need clean inbox for verification | $10-25/mo or lifetime |
| **Developer Testing Infrastructure** | Need sandbox email for testing signup flows, webhooks, parsing | $5-20/mo or free tier |

---

## 3. Core Features (v1.0)

### 3.1 Web Dashboard
- ✅ **Signup/Login** — Email+password, bcrypt, verified_at timestamp
- ✅ **Redeem AppSumo Code** — Non-expiring credits based on tier
- ✅ **Create Inbox** — Pick domain, enter local part, instant provisioning (Mailcow)
- ✅ **Inbox Viewer** — List emails, read full content, extract OTP code
- ✅ **Delete Inbox** — Soft delete (retention period)
- ✅ **API Keys Management** — Generate revocable keys with rate limits
- ✅ **Credits Balance** — Real-time balance display
- ✅ **Empty State** — Clear message when no inboxes exist

### 3.2 REST API (`/api/`)
- ✅ `GET /inboxes` — List user's inboxes
- ✅ `POST /inboxes` — Create new inbox (domain, local_part required)
- ✅ `DELETE /inboxes/{email}` — Delete inbox
- ✅ `GET /inboxes/{email}/emails` — List emails in inbox
- ✅ `GET /inboxes/{email}/emails/{uid}` — Read full email content
- ✅ `GET /inboxes/{email}/otp/{uid}` — Extract OTP from email body
- ✅ `GET /balance` — Get credit balance + tier
- ✅ `GET /domains` — List available domains

**Auth**: Bearer token (`Authorization: Bearer <key>`)  
**Rate Limit**: Per-user, per-hour (configurable by tier)  
**Error Handling**: JSON `{error: "message"}` format

### 3.3 MCP Server (`/mcp`)
- ✅ Protocol: Streamable HTTP (Model Context Protocol)
- ✅ Tools:
  - `list_inboxes` — List all inboxes
  - `create_inbox(domain, local_part)` — Create new inbox
  - `list_emails(email)` — List emails in inbox
  - `read_email(email, uid)` — Read full email
  - `get_otp(email, uid)` — Extract OTP code
  - `get_balance()` — Check remaining credits
  - `list_domains()` — Available domains

**Integration**: Claude Desktop, Cursor, custom agents  
**Transport**: POST `/mcp` with JSON-RPC payload

### 3.4 Abuse Prevention
- ✅ **Rate Limiting** — Per-user, per-hour (inbox creation, API calls)
- ✅ **Honeypot Detection** — Block `admin@`, `postmaster@`, etc.
- ✅ **Risk Scoring** — TLD blacklist, anonymous email domains
- ✅ **Trust Tiers** — New users (low quota), ramped up after 14 days, trusted after 60 days
- ✅ **Ownership Isolation** — API denies cross-user access

---

## 4. Technical Architecture

### 4.1 Stack
| Component | Technology | Notes |
|-----------|-----------|-------|
| **Frontend** | PHP 8.3 server-rendered | No JS frameworks, vanilla CSS |
| **Backend API** | PHP 8.3 | RESTful endpoints, PDO |
| **Database** | MySQL (Mailcow) | Shared DB, prefix `ia_` for tables |
| **Email Delivery** | Dovecot + Postfix (Mailcow) | Receive-only, SMTP relay |
| **MCP Server** | Node.js + `@modelcontextprotocol/sdk` | Standalone service |
| **Infrastructure** | Nginx + Cloudflare Tunnel | Single VPS (SSDNodes) |
| **Payment** | Manual/AppSumo redemption | No recurring payment processing yet |

### 4.2 Data Model
```sql
ia_users        -- Users (email, password_hash, status, trust_tier, risk_score)
ia_codes        -- AppSumo license codes (code, tier, status, redeemed_by)
ia_credit_ledger -- Credit transactions (delta, balance_after, type, ref)
ia_inboxes      -- Inboxes (user_id, email_address, local_part, domain, expires_at)
ia_api_keys     -- API keys (key_hash, scopes, rate_limit)
ia_rate_limits  -- Rate limiting counters (bucket, period, hits)
ia_audit        -- Audit logs (action, ip, detail)
```

### 4.3 Security Controls
- ✅ **Password Hashing** — bcrypt (`PASSWORD_BCRYPT`)
- ✅ **Session Security** — `Secure; HttpOnly; SameSite=Strict` cookies
- ✅ **SQL Injection Prevention** — Prepared statements (PDO)
- ✅ **XSS Prevention** — `htmlspecialchars()` + input validation
- ✅ **HTTPS Enforcement** — Cloudflare SSL auto-renewal
- ✅ **CORS Headers** — Restricted origins
- ⚠️ **CSRF Protection** — Not implemented in v1.0 (plan for v1.1)
- ⚠️ **Two-Factor Auth** — Not implemented (plan for v2.0)

---

## 5. Pricing Model

### 5.1 Tier Structure
| Tier | Price (LTD) | Included Credits | Inboxes | Domains | API | Retention |
|------|-------------|------------------|---------|---------|-----|-----------|
| **Tier 1** | $37 | 1,000 | 1 | 1 | Basic | 7 days |
| **Tier 2** | $67 | 2,500 | 3 | 1 | Basic | 14 days |
| **Tier 3** | $97 | 6,000 | 8 | 2 | Full | 30 days |
| **Tier 4** | $147 | 12,000 | 20 | 5 | Full | 45 days |
| **Tier 5** | $197 | 25,000 | 50 | 10 | Full + Priority | 60 days |

**Credit Consumption Rules**:
- 1 email received = 1 credit
- 1 inbox active per day = 2 credits
- 25 API calls = 1 credit
- 1 custom domain/month = 50 credits

**Key Promise**: Lifetime access, non-expiring credits, no mandatory monthly fees.

---

## 6. Roadmap (Post-Launch)

### 6.1 v1.1 (Q4 2026)
- [ ] **BYOD (Bring Your Own Domain)** — Verify DNS TXT record, add custom domains
- [ ] **Webhooks** — POST notification when email received
- [ ] **Top-up Payment** — Stripe/LemonSqueezy integration for credit purchases
- [ ] **Referral Program** — 10% credit bonus for successful referrals
- [ ] **Team Seats** — Share inboxes among team members

### 6.2 v2.0 (Q1 2027)
- [ ] **Two-Factor Auth** — TOTP support for account security
- [ ] **Privacy Policy + Terms of Service** — Legal compliance
- [ ] **User Account Deletion** — Self-service data removal
- [ ] **Contact Support** — Integrated ticketing system
- [ ] **Analytics Dashboard** — Usage tracking, retention metrics

### 6.3 v2.5 (Q2 2027)
- [ ] **Bulk Import** — Upload CSV to create multiple inboxes
- [ ] **Email Templates** — Pre-built reply templates
- [ ] **Advanced Filtering** — Search/filter emails by metadata
- [ ] **Mobile App** — iOS/Android companion app

---

## 7. Success Metrics (KPIs)

| Metric | Target (Month 3) | Target (Year 1) |
|--------|-----------------|----------------|
| **Active Users** | 100 | 1,000 |
| **Monthly Revenue** | $2,000 | $15,000 |
| **Customer Churn** | <5% | <2% |
| **API Calls/User/Month** | 500 | 2,000 |
| **NPS Score** | 40 | 60 |
| **Support Ticket Response Time** | <4 hours | <2 hours |

---

## 8. Risks & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| **Credential Leak** | High | Critical | Rotate all secrets, private GitHub repo, audit quarterly |
| **Spam/Farming** | Medium | High | Abuse prevention (honeypots, rate limits, trust tiers), manual review for high-volume users |
| **DNS Propagation Failures** | Low | Medium | Use stable domains, avoid TLD changes, monitor propagation |
| **Cloudflare Rate Limits** | Low | Medium | Monitor usage, optimize caching, use dedicated IPs if needed |
| **Email Deliverability Issues** | Medium | High | Warm IPs gradually, avoid bulk sending, use proper headers/SPF/DKIM |

---

## 9. Deployment Checklist

- [ ] **Domain Setup** — `teak.email` registered, DNS records configured
- [ ] **SSL Certificate** — Let's Encrypt + auto-renewal
- [ ] **Database Migration** — Run `schema.sql` on production DB
- [ ] **Service Accounts** — Create `agents@teak.email` for MCP server
- [ ] **Cron Jobs** — Install `honeypot_watch.php` (5m) + `daily_rent.php` (3am)
- [ ] **API Keys** — Rotate all hardcoded credentials (MySQL, CF, SSH)
- [ ] **AppSumo Codes** — Generate initial batch (50-100 codes per tier)
- [ ] **Privacy Policy + ToS** — Publish pages on site
- [ ] **Monitoring** — Set up error logging (file or external service)
- [ ] **Backup** — Test database backup/restore procedure

---

## 10. Appendix: Conversation History References

See attached files for full context:
- `/docs/qa-final-report.md` — Full QA audit results
- `/docs/appsumo-listing.md` — AppSumo product listing copy
- `/docs/security-signoff.md` — Security test results & mitigations
- `/docs/conversation-history-2026-07-31.md` — Complete dialogue history

---

**Approval Signatures**  
Product Lead: ________________________ Date: _________  
Engineering Lead: ____________________ Date: _________  
Launch Approval: _____________________ Date: _________
