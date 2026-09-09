# Domain Sync Report — 2026-08-28 (Updated)

## Summary
- **Target User**: n311311@gmail.com (ID=3)
- **Total Spaceship Domains Fetched**: 45
- **Total User Domains After Sync**: 46 (4 pre-existing + 42 new)
- **DNS Changes Made**: NONE (all existing records preserved)
- **Inboxes Created**: 0 (confirmed)
- **Credentials Used**: Environment variables (SPACESHIP_API_KEY, SPACESHIP_API_SECRET) — never CLI args or API body

## Sync Classification

| Category | Count | Details |
|----------|-------|---------|
| Safe (no MX) | 33 | No external email provider detected. Ready for inbox creation. |
| Safe (Teak MX) | 3 | jasa-seo.id, jdp.industries, toohumid.com — MX already points to Teak server |
| Conflict (SpaceMail) | 5 | jdp.academy, penghasilantambahan.com, pesat.ai, slasi.id, straight.ltd |
| Conflict (Zoho) | 2 | jetdigitalpro.com, sf-en.com |
| Unknown | 2 | gcrindex.org (Spaceship forward MX), thesitesale.com (DC verification) |
| **Total** | **45** | |

## Safe Domains (36 total)

### Pre-existing (manual source, verified)
| Domain | MX Records | Notes |
|--------|------------|-------|
| toohumid.com | mail.pesat.ai | Clean Teak MX |
| jasa-seo.id | mail.pesat.ai | Clean Teak MX |
| jdp.industries | mail.pesat.ai | Clean Teak MX |

### From Spaceship (verified, no MX records)
aerisresearch.com, aideveloperid.com, allthingsgardener.com, ashburtonresearch.com, bowlakechinese.com, brillies.co, byehumidity.com, calderstoneresearch.com, crestmoreresearch.com, dunstanresearch.com, flumbericoco.com, flumberico.shop, flumberico.site, freeonlinemyersbriggs.com, gcrindex.com, hargroveresearch.com, kingsworthresearch.com, knowngarden.com, mans.boats, milkwoodrestaurant.com, norwellresearch.com, pemberresearch.com, pesat.app, pesatrouter.com, pickleball-outfits.com, presswires.net, sanibelislandgo.com, seo-contentwritingservices.com, seotool.im, suttonhillsresearch.com, teak.email, waverlyresearch.com, whitfieldresearch.com, wordmarks.net

## Conflict Domains (7 total) — DO NOT MODIFY DNS

| Domain | Provider | MX Records | Notes |
|--------|----------|------------|-------|
| jetdigitalpro.com | Zoho | mx2.zoho.com, mail.pesat.ai, mx3.zoho.com, mx.zoho.com | Has Teak MX but also Zoho. DNS cleanup needed. |
| sf-en.com | Zoho | mx2.zoho.com, mx.zoho.com, mx3.zoho.com | Full Zoho MX. Do not modify. |
| jdp.academy | SpaceMail | mx1.spacemail.com, mx2.spacemail.com | SpaceMail MX. Do not modify. |
| penghasilantambahan.com | SpaceMail | mx2.spacemail.com, mx1.spacemail.com | SpaceMail MX. Do not modify. |
| pesat.ai | SpaceMail | mx2.spacemail.com, mx1.spacemail.com | Main domain uses SpaceMail intentionally. Do not modify. |
| slasi.id | SpaceMail | mx1.spacemail.com, mx2.spacemail.com | SpaceMail MX. Do not modify. |
| straight.ltd | SpaceMail | mx2.spacemail.com, mx1.spacemail.com | SpaceMail MX. Do not modify. |

## Unknown Domains (2 total) — Manual Review Required

| Domain | MX Records | Notes |
|--------|------------|-------|
| gcrindex.org | mx2.efwd.spaceship.net, mx1.efwd.spaceship.net | Spaceship email forwarding. May be safe to configure. |
| thesitesale.com | _dc-mx.18e43817716f.thesitesale.com | Domain verification record. Likely unused for email. |

## Classification Legend
- **safe**: Domain points to Teak MX (mail.pesat.ai / 94.100.26.189) or has no MX records. Ready for inbox creation.
- **conflict_***: Domain has existing external email provider (Google, Microsoft, Zoho, SpaceMail, etc.). DO NOT modify DNS.
- **unknown**: Domain has MX records not matching known providers. Manual review required.
- **pending**: Domain added but DNS not yet inspected.

## Conflict Detection (12 Providers)
The system detects conflicts with: Google, Microsoft, Zoho, SpaceMail, ProtonMail, Yandex, FastMail, Rackspace, Mimecast, Proofpoint, Barracuda, MailChannels.

## Security Fix Applied
- **api.php POST /api.php/domains** — No longer accepts `auth_key`/`auth_secret` in request body
- Credentials read from server-side env vars only (SPACESHIP_API_KEY, SPACESHIP_API_SECRET)
- Body credentials are completely ignored; returns 503 if env vars not set
- **spaceship_sync.php** — Reads credentials from env vars, not CLI args (invisible in process listings)

## AI Agent API Endpoints

### GET /api.php/domains
Lists user's custom domains, safe domains, pool domains, and classification summary.
Requires: `Authorization: Bearer cib_xxx`

Response (redacted):
```json
{
  "ok": true,
  "domains": [{"id": 5, "domain": "aerisresearch.com", "source": "spaceship", "status": "verified", "classification": "safe", ...}],
  "safe_domains": ["aerisresearch.com", ...],
  "pool_domains": ["jetdigitalpro.com", "toohumid.com", "jasa-seo.id", "jdp.industries"],
  "summary": {"total": 46, "safe": 37, "conflict": 7, "unknown": 0, "pending": 2}
}
```

### POST /api.php/domains/sync
Syncs domains from Spaceship registrar. Server-side credentials only (env vars).
Requires: `Authorization: Bearer cib_xxx` + `{"registrar": "spaceship"}`

Note: Credentials are NOT accepted in the request body. The server reads SPACESHIP_API_KEY and SPACESHIP_API_SECRET from environment variables.

## Remaining Actions
1. **Review unknown domains** — gcrindex.org and thesitesale.com need manual review
2. **Remove stray Zoho MX** from jetdigitalpro.com (mx.zoho.com, mx2.zoho.com, mx3.zoho.com)
3. **Add DKIM record** for jasa-seo.id
4. **Add DMARC** (p=none) for toohumid.com, jasa-seo.id, jdp.industries
5. **Rotate Spaceship credentials** — Used ephemerally for this sync; recommend rotating in Spaceship account settings
6. **DNS cleanup for conflict domains** — SpaceMail/Zoho conflicts on 7 domains need review
