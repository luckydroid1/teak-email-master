# AppSumo Listing — CodeInbox
> Produk live di https://inbox.pesat.ai · Tanggal: 2026-07-31
> Redemption: user signup → /redeem → masukkan kode AS-XXXX

---

## 1. Deal Title
**CodeInbox — Clean Email Inboxes for Builders & AI Agents**

## 2. Deal Subtitle
**Lifetime access to the platform. Included usage credits, no monthly fee.**

## 3. One-Line Description (AppSumo field)
Create fresh email inboxes in 2 clicks — use them yourself or with your AI agent via REST API & MCP. No CAPTCHA wall, no blocklists, no subscription.

## 4. Hero Copy
**Headline:** Clean email inboxes for builders & AI agents.
**Subheadline:** Your AI agent needs an email to verify accounts and grab OTPs. Gmail blocks automation. Temp mail is banned everywhere. CodeInbox gives you clean, dedicated inboxes on custom domains — readable by humans or by your agent through a simple API.
**CTA:** Get Lifetime Access Today · Scale usage only when you grow.

## 5. Key Value Props (3 bullets)
- ⚡ **2-Click Inbox** — Pick a domain, type a name, done. Receiving in seconds.
- 🤖 **API & MCP Ready** — Your agent lists inboxes and grabs OTPs automatically.
- 🌐 **Your Domain or Ours** — Use our clean shared domains (bring-your-own coming soon).

## 6. Pricing (5 tiers)
| Tier | Price | Included Credits | Inboxes | Domains | API | Retention |
|------|-------|-----------------|---------|---------|-----|-----------|
| Tier 1 | $37 | 1,000 | 1 | 1 | Basic | 7 days |
| Tier 2 | $67 | 2,500 | 3 | 1 | Basic | 14 days |
| Tier 3 | $97 | 6,000 | 8 | 2 | Full | 30 days |
| Tier 4 | $147 | 12,000 | 20 | 5 | Full | 45 days |
| Tier 5 | $197 | 25,000 | 50 | 10 | Full + Priority | 60 days |

**Badges:** Tier 3 = "Most Popular" · Tier 5 = "Best Value"
**All tiers include:** Lifetime platform access · Non-expiring credits · No monthly fee · Top-up optional.

## 7. How This Lifetime Deal Works
- This deal gives you **lifetime platform access** — no recurring subscription.
- Each tier includes **non-expiring credits** used for platform activity.
- If your usage grows, top up. If not, pay nothing more.
- This structure protects long-term stability — so we can support users for years.

## 8. What Credits Cost
| Activity | Cost |
|----------|------|
| 1 email received | 1 credit |
| 1 inbox active per day | 2 credits |
| 25 API calls | 1 credit |
| 1 custom domain active/month | 50 credits |

*Example: a light user (1 inbox, 10 emails/day, 20 API calls/day) uses ~20 credits/day → 1,000 credits ≈ 50 days.*

## 9. Best For
- Solopreneurs testing workflows
- Agencies managing many clients
- Builders connecting tools via API/MCP
- AI agent developers who hit the "OTP wall"
- Buyers avoiding monthly SaaS fatigue

## 10. Not Ideal For
- Bulk email senders (this is receive-only by design)
- Mass account farming (prohibited by Terms)

## 11. FAQ (anti-refund)
**Is this really a lifetime deal?**
Yes — for platform access. Your account access is lifetime. Included credits are a one-time allowance, not a monthly reset.

**Do credits expire?**
No. Credits are non-expiring. Use them whenever you need.

**Do I need a top-up?**
Only if your usage grows. Small users may never need one.

**Why not unlimited usage?**
Unlimited usage breaks long-term economics. Metered usage keeps the product sustainable — so we can support users for years.

**Will I lose access later?**
No. Your lifetime access stays active.

**Can my AI agent use this?**
Yes. Generate an API key in your dashboard, then connect via REST API or MCP server (setup guide included).

## 12. Redemption Flow (opsional — user instructions)
1. Buy on AppSumo → receive license code (AS-XXXXXXXXXXXX)
2. Go to https://inbox.pesat.ai → Sign up → verify email
3. Redeem code at /redeem → credits added instantly
4. Create your first inbox at /inboxes

## 13. Ops Notes (untuk founder — jangan tampilkan ke buyer)
- Generate codes: `php scripts/generate_codes.php 50 3` (50 kode tier 3)
- Ganti `app_url` di config.php ke domain brand jika sudah beli
- Abuse protection aktif: rate limit per jam, honeypot, risk scoring, trust tiers (akun baru limit kecil)
- Service account untuk MCP: agents@jetdigitalpro.com
- **CRITICAL: rotate credentials** — semuanya masih terekspos di repo public GitHub
