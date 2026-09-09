# Teak Email

**Clean email inboxes for builders & AI agents.** Create fresh inboxes in seconds, use them manually or via API/MCP. Lifetime access, non-expiring credits, no monthly fee.

## What is Teak Email?

Teak Email is a lightweight email infrastructure platform built on top of Mailcow. It provides:

- **Web Dashboard**: Create/manage inboxes via simple UI (2 clicks)
- **REST API**: CRUD + OTP extractor via HTTP
- **MCP Server**: Connect AI agents (Claude, Cursor, GPT) to read emails & extract codes automatically
- **Abuse Prevention**: Honeypots, rate limits, trust tiers, risk scoring

## Why Choose Teak Email?

- **2-Click Inbox Creation** — Pick a domain, type a name, done. Receiving in seconds.
- **API & MCP Ready** — Your agent lists inboxes and grabs OTPs automatically.
- **Your Domain or Ours** — Use our clean shared domains, or bring your own (v1.1).
- **Receive-Only, Clean Reputation** — No bulk sending. Clean infrastructure designed for receiving codes.
- **Lifetime Deal** — Pay once for platform access. Use included non-expiring credits anytime.

## Quick Start

### For Humans (Web UI)
1. Go to [https://teak.email](https://teak.email)
2. Sign up → verify email → redeem AppSumo code
3. Create inbox → receive emails → extract OTPs

### For Developers (API)
```bash
# List inboxes
curl -H "Authorization: Bearer YOUR_API_KEY" \
  https://teak.email/api/inboxes

# Create inbox
curl -X POST -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"jetdigitalpro.com","local_part":"agent.2026"}' \
  https://teak.email/api/inboxes

# Get OTP from latest email
curl -H "Authorization: Bearer YOUR_API_KEY" \
  https://teak.email/api/inboxes/agent.2026@jetdigitalpro.com/otp/1
```

### For AI Agents (MCP)
Add to your MCP config:
```json
{
  "mcpServers": {
    "codeinbox": {
      "command": "npx",
      "args": ["-y", "codeinbox-mcp"],
      "env": {
        "CODEINBOX_API_KEY": "YOUR_API_KEY",
        "CODEINBOX_API_URL": "https://teak.email/api"
      }
    }
  }
}
```

## Pricing (Lifetime Deal)

| Tier | Price | Credits | Inboxes | Domains | API | Retention |
|------|-------|---------|---------|---------|-----|-----------|
| Tier 1 | $37 | 1,000 | 1 | 1 | Basic | 7 days |
| Tier 2 | $67 | 2,500 | 3 | 1 | Basic | 14 days |
| Tier 3 | $97 | 6,000 | 8 | 2 | Full | 30 days |
| Tier 4 | $147 | 12,000 | 20 | 5 | Full | 45 days |
| Tier 5 | $197 | 25,000 | 50 | 10 | Full + Priority | 60 days |

**Credit Consumption**:
- 1 email received = 1 credit
- 1 inbox active per day = 2 credits
- 25 API calls = 1 credit
- 1 custom domain/month = 50 credits

## Documentation

- [PRD v1.0](./PRD-v1.0.md) — Product Requirements Document
- [coldstart.md](./coldstart.md) — Deployment & ops context
- [AppSumo Listing](./app/appsumo-listing.md) — Product listing copy

## Security

- Passwords hashed with bcrypt
- Session cookies: `Secure; HttpOnly; SameSite=Strict`
- SQL injection prevention via PDO prepared statements
- XSS prevention via `htmlspecialchars()`
- Rate limiting per user/hour
- Honeypot detection for spam prevention
- Trust tiers for gradual access escalation

## License

Proprietary software. All rights reserved.

---

**Built by**: Nell VH  
**Repository**: [github.com/emerilansel-jpg/mail-admin-pesat](https://github.com/emerilansel-jpg/mail-admin-pesat)  
**Website**: [https://teak.email](https://teak.email) (launching soon)
