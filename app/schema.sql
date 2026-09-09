-- ============================================================
-- Teak Email — Schema v1.1
-- Evolusi dari Mail Admin. Tabel prefix `ia_` di DB mailcow.
-- Includes Postfix/Dovecot/MySQL mail system tables.
-- ============================================================

-- ─── Mail System Tables (Postfix/Dovecot/MySQL) ────────────

CREATE TABLE IF NOT EXISTS domain (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  domain VARCHAR(255) NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT '',
  aliases INT UNSIGNED NOT NULL DEFAULT 400,
  mailboxes INT UNSIGNED NOT NULL DEFAULT 10,
  quota INT UNSIGNED NOT NULL DEFAULT 10240,
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mailbox (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(255) DEFAULT '',
  local_part VARCHAR(128) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  quota INT UNSIGNED NOT NULL DEFAULT 102400,
  active TINYINT NOT NULL DEFAULT 1,
  attributes JSON,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sender_acl (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  logged_in_as VARCHAR(255) NOT NULL,
  send_as VARCHAR(255) NOT NULL,
  active TINYINT NOT NULL DEFAULT 1,
  UNIQUE KEY idx_acl (logged_in_as, send_as)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  address VARCHAR(255) NOT NULL UNIQUE,
  goto TEXT NOT NULL,
  domain VARCHAR(255) NOT NULL,
  active TINYINT NOT NULL DEFAULT 1,
  KEY idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── App Tables ─────────────────────────────────────────────

-- Users
CREATE TABLE IF NOT EXISTS ia_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('pending','active','suspended','banned') NOT NULL DEFAULT 'pending',
  trust_tier TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- 1=new, 2=ramping, 3=trusted
  risk_score INT NOT NULL DEFAULT 0,
  verify_token VARCHAR(64) DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AppSumo license codes (di-generate oleh kita)
CREATE TABLE IF NOT EXISTS ia_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  tier TINYINT UNSIGNED NOT NULL,                  -- 1-5
  credits INT UNSIGNED NOT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'appsumo',
  status ENUM('unused','redeemed') NOT NULL DEFAULT 'unused',
  redeemed_by INT UNSIGNED DEFAULT NULL,
  redeemed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Credit ledger (append-only)
CREATE TABLE IF NOT EXISTS ia_credit_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  delta INT NOT NULL,                              -- +/-
  balance_after INT NOT NULL,
  type VARCHAR(32) NOT NULL,                       -- redeem, inbox_create, email_received, api_call, topup, refund, daily_rent
  ref VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inboxes (registrasi inbox di produk, referensi mailbox di Mailcow)
CREATE TABLE IF NOT EXISTS ia_inboxes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  email_address VARCHAR(255) NOT NULL UNIQUE,
  local_part VARCHAR(128) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  status ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  retention_days INT UNSIGNED NOT NULL DEFAULT 7,
  expires_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API keys
CREATE TABLE IF NOT EXISTS ia_api_keys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  key_hash CHAR(64) NOT NULL,                      -- sha256(key)
  key_prefix VARCHAR(12) NOT NULL,                 -- display: cib_ab12...
  scopes VARCHAR(255) NOT NULL DEFAULT 'read,write',
  rate_limit INT UNSIGNED NOT NULL DEFAULT 120,    -- per jam
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate limit counters per bucket per jam
CREATE TABLE IF NOT EXISTS ia_rate_limits (
  bucket VARCHAR(128) NOT NULL,
  period VARCHAR(16) NOT NULL,                     -- YYYYMMDDHH
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit log
CREATE TABLE IF NOT EXISTS ia_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(64) NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  detail VARCHAR(512) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── User-Scoped Custom Domains ──────────────────────────────
-- Each user has their own domain list. No global pool_domains fallback.
CREATE TABLE IF NOT EXISTS ia_user_domains (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  domain VARCHAR(255) NOT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'manual',        -- manual | spaceship | api
  status ENUM('pending','verified','conflict','rejected') NOT NULL DEFAULT 'pending',
  classification VARCHAR(32) DEFAULT NULL,               -- safe | conflict_spacemail | conflict_google | conflict_microsoft | conflict_zoho | conflict_other | unknown
  mx_summary VARCHAR(512) DEFAULT NULL,                 -- comma-separated MX hosts
  notes TEXT DEFAULT NULL,                               -- redacted classification notes
  synced_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_user_domain (user_id, domain),
  KEY idx_user (user_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Spaceship credential storage (server-side only, never exposed to client)
CREATE TABLE IF NOT EXISTS ia_registrar_creds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  registrar VARCHAR(32) NOT NULL,
  api_key_enc VARCHAR(512) NOT NULL,                    -- encrypted or hashed reference
  api_secret_enc VARCHAR(512) DEFAULT NULL,
  last_sync_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_user_registrar (user_id, registrar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sent emails tracking
CREATE TABLE IF NOT EXISTS ia_sent_emails (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  from_email VARCHAR(255) NOT NULL,
  to_email VARCHAR(255) NOT NULL,
  subject VARCHAR(500) NOT NULL,
  body TEXT,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_from (from_email),
  KEY idx_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password reset tokens (single-use, short-lived, stored hashed)
CREATE TABLE IF NOT EXISTS ia_password_reset_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,                     -- sha256 of the plain token
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_token (token_hash),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
