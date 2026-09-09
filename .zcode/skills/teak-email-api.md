# Teak Email API / MCP Skill

Use this skill when you need to interact with Teak Email (https://teak.email) from ZCode.
Covers: create/read/delete inboxes, list emails, extract OTP codes, manage domains, check balance.

## Quick Reference

**Base URL:** `https://teak.email/api`
**Auth:** `Authorization: Bearer $TEAK_EMAIL_API_KEY`
**MCP Server:** `npx -y codeinbox-mcp`

## Environment Variable

Set your API key as an environment variable. Never hardcode it.

```bash
export TEAK_EMAIL_API_KEY="cib_YOUR_ACTUAL_KEY_HERE"
```

Or in `.env` (gitignored):
```
TEAK_EMAIL_API_KEY=cib_YOUR_ACTUAL_KEY_HERE
```

## REST API Endpoints

### List Inboxes
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes
```
Response: `{"ok":true,"inboxes":[...]}`

### Create Inbox
```bash
curl -s -X POST \
  -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"toohumid.com","local_part":"my-inbox"}' \
  https://teak.email/api/inboxes
```
Response: `{"ok":true,"email":"my-inbox@toohumid.com","password":"abc123def456"}`
HTTP 201 on success.

Eligible domains (pool + your verified custom): `toohumid.com`, `jasa-seo.id`, `jdp.industries`, plus any verified custom domains.
Run `GET /api/domains` to see the `eligible` field. `jetdigitalpro.com` is blocked for new inboxes.

### List Emails in Inbox
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/my-inbox@toohumid.com/emails
```
Response: `{"ok":true,"inbox":{...},"emails":[{"uid":1,"from":"...","subject":"...","date":"..."}]}`

### Read Single Email
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/my-inbox@toohumid.com/emails/1
```
Response: `{"ok":true,"raw":"...full raw email content..."}`

### Extract OTP Code
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/my-inbox@toohumid.com/otp/1
```
Response: `{"ok":true,"otp":"123456","text":"Your verification code is: 123456..."}`

### Delete Inbox
```bash
curl -s -X DELETE \
  -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/my-inbox@toohumid.com
```
Response: `{"ok":true}`

### Check Balance & Tier
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/balance
```
Response: `{"ok":true,"balance":25000,"tier":5}`

### List Domains
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/domains
```
Response: `{"ok":true,"eligible":["toohumid.com",...],"pool_domains":["toohumid.com","jasa-seo.id","jdp.industries"],"custom_domains":["your-domain.com",...],"domains":[...],"safe_domains":[...],"summary":{...}}`

## Typical Agent Workflow (OTP Extraction)

1. Create inbox: `POST /api/inboxes` with domain + local_part
2. Wait for email to arrive (poll `GET /api/inboxes/{email}/emails`)
3. Extract OTP: `GET /api/inboxes/{email}/otp/{uid}`
4. Clean up: `DELETE /api/inboxes/{email}`

## MCP Server Configuration

For Claude Code, Cursor, Windsurf, or other MCP-compatible tools:

```json
{
  "mcpServers": {
    "teak-email": {
      "command": "npx",
      "args": ["-y", "codeinbox-mcp"],
      "env": {
        "CODEINBOX_API_KEY": "$TEAK_EMAIL_API_KEY",
        "CODEINBOX_API_URL": "https://teak.email/api"
      }
    }
  }
}
```

Replace `$TEAK_EMAIL_API_KEY` with your actual key or reference the env var.

## Python Example

```python
import os, requests

API_KEY = os.environ["TEAK_EMAIL_API_KEY"]
BASE = "https://teak.email/api"
HEADERS = {"Authorization": f"Bearer {API_KEY}"}

# Create inbox
r = requests.post(f"{BASE}/inboxes", headers=HEADERS,
                   json={"domain": "toohumid.com", "local_part": "test-123"})
inbox = r.json()
print(f"Created: {inbox['email']}")

# List emails
r = requests.get(f"{BASE}/inboxes/{inbox['email']}/emails", headers=HEADERS)
emails = r.json()["emails"]
print(f"Found {len(emails)} emails")

# Extract OTP from latest
if emails:
    r = requests.get(f"{BASE}/inboxes/{inbox['email']}/otp/{emails[0]['uid']}", headers=HEADERS)
    print(f"OTP: {r.json()['otp']}")
```

## Node.js Example

```javascript
const API_KEY = process.env.TEAK_EMAIL_API_KEY;
const BASE = 'https://teak.email/api';
const headers = { Authorization: `Bearer ${API_KEY}` };

// Create inbox
const res = await fetch(`${BASE}/inboxes`, {
  method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' },
  body: JSON.stringify({ domain: 'toohumid.com', local_part: 'test-123' })
});
const { email, password } = await res.json();
console.log(`Created: ${email}`);

// List emails
const list = await fetch(`${BASE}/inboxes/${email}/emails`, { headers });
const { emails } = await list.json();

// Extract OTP
if (emails.length) {
  const otp = await fetch(`${BASE}/inboxes/${email}/otp/${emails[0].uid}`, { headers });
  console.log(`OTP: ${(await otp.json()).otp}`);
}
```

## Error Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Inbox created |
| 400 | Bad request (invalid input) |
| 401 | Unauthorized (bad/missing API key) |
| 404 | Inbox or email not found |
| 405 | Method not allowed |
| 429 | Rate limit exceeded |
| 500 | Server error |

## Rate Limits

- API: 120 requests per hour per key (configurable per user tier)
- Inbox creation: Limited per hour based on trust tier
- Signup: 5 per IP per hour

## Notes

- Inboxes have retention periods based on tier (7-60 days)
- Credits are consumed for API calls (-1 per list/read/OTP call)
- Inbox rent: 60 credits/month per active inbox
- All emails are receive-only (no sending from inboxes via API)
- Application emails (password reset, verification) come from no-reply@teak.email
