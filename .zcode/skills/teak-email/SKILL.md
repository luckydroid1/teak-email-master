---
name: teak-email
description: "Interact with Teak Email API: create inboxes, read emails, extract OTP codes, manage domains, check balance. Use when user says: teak email, create inbox, get OTP, check email, teak API."
---

# Teak Email API

Manage email inboxes and extract OTP codes via the Teak Email REST API.

## Credentials

- **Website**: `https://teak.email`
- **API Base URL**: `https://teak.email/api`
- **Auth Header**: `Authorization: Bearer $TEAK_EMAIL_API_KEY`
- **MCP Server**: `npx -y codeinbox-mcp` (set `CODEINBOX_API_KEY` env var)

**IMPORTANT**: Never hardcode API keys. Always use `$TEAK_EMAIL_API_KEY` environment variable.

## Environment Setup

```bash
export TEAK_EMAIL_API_KEY="cib_YOUR_KEY_HERE"
```

## API Endpoints

### List Inboxes
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes
```

### Create Inbox
```bash
curl -s -X POST \
  -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"toohumid.com","local_part":"my-inbox"}' \
  https://teak.email/api/inboxes
```

Eligible domains (pool + your verified custom domains): `toohumid.com`, `jasa-seo.id`, `jdp.industries`, plus any verified custom domains from your account.
Run `GET /api/domains` to see the `eligible` field with your full list.
Note: `jetdigitalpro.com` is blocked for new inboxes.

### List Emails
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/EMAIL_ADDRESS/emails
```

### Read Email
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/EMAIL_ADDRESS/emails/UID
```

### Extract OTP
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/EMAIL_ADDRESS/otp/UID
```

### Delete Inbox
```bash
curl -s -X DELETE \
  -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/inboxes/EMAIL_ADDRESS
```

### Check Balance
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/balance
```

### List Domains
```bash
curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  https://teak.email/api/domains
```

## MCP Server Config (for Claude Code / Cursor / Windsurf)

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

## Typical Agent Flow (OTP Extraction)

1. Create inbox: `POST /api/inboxes` with domain + local_part
2. Wait for email (poll `GET /api/inboxes/{email}/emails`)
3. Extract OTP: `GET /api/inboxes/{email}/otp/{uid}`
4. Clean up: `DELETE /api/inboxes/{email}`

## Python Example

```python
import os, requests
API_KEY = os.environ["TEAK_EMAIL_API_KEY"]
BASE = "https://teak.email/api"
H = {"Authorization": f"Bearer {API_KEY}"}

# Create inbox
r = requests.post(f"{BASE}/inboxes", headers=H,
                   json={"domain":"toohumid.com","local_part":"test-123"})
inbox = r.json()

# List emails
emails = requests.get(f"{BASE}/inboxes/{inbox['email']}/emails", headers=H).json()["emails"]

# Extract OTP
if emails:
    otp = requests.get(f"{BASE}/inboxes/{inbox['email']}/otp/{emails[0]['uid']}", headers=H).json()
    print(f"OTP: {otp['otp']}")
```

## Node.js Example

```javascript
const h = { Authorization: `Bearer ${process.env.TEAK_EMAIL_API_KEY}` };
const B = 'https://teak.email/api';

const { email } = await fetch(`${B}/inboxes`, {
  method:'POST', headers:{...h,'Content-Type':'application/json'},
  body: JSON.stringify({domain:'toohumid.com',local_part:'test-123'})
}).then(r=>r.json());

const {emails} = await fetch(`${B}/inboxes/${email}/emails`,{headers:h}).then(r=>r.json());
if(emails.length){
  const otp = await fetch(`${B}/inboxes/${email}/otp/${emails[0].uid}`,{headers:h}).then(r=>r.json());
  console.log(`OTP: ${otp.otp}`);
}
```

## Error Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Inbox created |
| 400 | Bad request |
| 401 | Unauthorized |
| 404 | Not found |
| 429 | Rate limit exceeded |

## Notes

- Credits consumed: -1 per list/read/OTP API call
- Inbox rent: 60 credits/month per active inbox
- Retention: 7-60 days based on tier
- Sender for app emails: no-reply@teak.email
