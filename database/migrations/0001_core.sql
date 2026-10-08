-- Core schema: markets, staff and access control, sessions, rate limiting,
-- settings and the audit log. MySQL 8 / MariaDB 10.6+, InnoDB, utf8mb4.

CREATE TABLE rate_limits (
  key_hash      CHAR(64)     NOT NULL PRIMARY KEY,
  window_start  INT UNSIGNED NOT NULL,
  hits          INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at    DATETIME     NOT NULL,
  KEY idx_rate_limits_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE countries (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(120) NOT NULL,
  code            CHAR(2)      NOT NULL,
  slug            VARCHAR(16)  NOT NULL DEFAULT '',
  locale          VARCHAR(16)  NOT NULL DEFAULT 'en',
  currency        CHAR(3)      NOT NULL DEFAULT 'INR',
  currency_symbol VARCHAR(8)   NOT NULL DEFAULT '₹',
  phone_code      VARCHAR(8)   NULL,
  timezone        VARCHAR(64)  NOT NULL DEFAULT 'Asia/Kolkata',
  is_default      TINYINT(1)   NOT NULL DEFAULT 0,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  is_published    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order      INT          NOT NULL DEFAULT 0,
  settings        JSON         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_countries_code (code),
  UNIQUE KEY uq_countries_slug (slug),
  KEY idx_countries_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL,
  description VARCHAR(255) NULL,
  is_system   TINYINT(1)   NOT NULL DEFAULT 0,
  `rank`      INT          NOT NULL DEFAULT 100,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roles_name (name),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key`       VARCHAR(80)  NOT NULL,
  `group`     VARCHAR(40)  NOT NULL,
  label       VARCHAR(160) NOT NULL,
  UNIQUE KEY uq_permissions_key (`key`),
  KEY idx_permissions_group (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id       BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_role_permissions_permission (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role_id                 BIGINT UNSIGNED NOT NULL,
  email                   VARCHAR(254) NOT NULL,
  name                    VARCHAR(160) NOT NULL,
  password_hash           VARCHAR(255) NULL,
  status                  ENUM('ACTIVE','INVITED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
  avatar_url              VARCHAR(500) NULL,
  job_title               VARCHAR(120) NULL,
  department              VARCHAR(120) NULL,
  phone                   VARCHAR(40)  NULL,
  alternate_phone         VARCHAR(40)  NULL,
  timezone                VARCHAR(64)  NULL,
  address_line1           VARCHAR(200) NULL,
  address_line2           VARCHAR(200) NULL,
  city                    VARCHAR(120) NULL,
  state                   VARCHAR(120) NULL,
  country                 VARCHAR(120) NULL,
  postal_code             VARCHAR(20)  NULL,
  bio                     TEXT NULL,
  linkedin_url            VARCHAR(500) NULL,
  twitter_url             VARCHAR(500) NULL,
  website_url             VARCHAR(500) NULL,
  all_countries           TINYINT(1)   NOT NULL DEFAULT 1,
  two_factor_required     TINYINT(1)   NOT NULL DEFAULT 1,
  two_factor_enabled      TINYINT(1)   NOT NULL DEFAULT 0,
  two_factor_secret       VARCHAR(255) NULL,
  two_factor_pending      VARCHAR(255) NULL,
  two_factor_verified_at  DATETIME NULL,
  two_factor_last_step    BIGINT NULL,
  password_changed_at     DATETIME NULL,
  last_login_at           DATETIME NULL,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at              DATETIME NULL,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role_id),
  KEY idx_users_status (status),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_countries (
  user_id    BIGINT UNSIGNED NOT NULL,
  country_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, country_id),
  CONSTRAINT fk_uc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_uc_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Server-side record of every signed-in session: revocable, and the only
-- place "has this session cleared two-factor authentication?" is answered.
CREATE TABLE auth_sessions (
  id              CHAR(64)        NOT NULL PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  mfa_verified_at DATETIME NULL,
  mfa_method      VARCHAR(20) NULL,
  ip_hash         CHAR(32) NULL,
  user_agent      VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME NOT NULL,
  revoked_at      DATETIME NULL,
  revoked_reason  VARCHAR(80) NULL,
  KEY idx_auth_sessions_user (user_id),
  KEY idx_auth_sessions_expires (expires_at),
  CONSTRAINT fk_auth_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recovery_codes (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  code_hash  CHAR(64) NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_recovery_codes_hash (code_hash),
  KEY idx_recovery_codes_user (user_id),
  CONSTRAINT fk_recovery_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Typed settings groups (website, seo, tracking, email, footer, blog, product …).
-- Defaults and validation live in App\Services\Settings.
CREATE TABLE settings (
  `group`     VARCHAR(40) NOT NULL PRIMARY KEY,
  data        JSON        NOT NULL,
  updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id    BIGINT UNSIGNED NULL,
  actor_email VARCHAR(254) NULL,
  action      VARCHAR(40)  NOT NULL,
  entity      VARCHAR(60)  NOT NULL,
  entity_id   VARCHAR(64)  NULL,
  summary     TEXT NULL,
  before_data JSON NULL,
  after_data  JSON NULL,
  ip_hash     CHAR(32) NULL,
  user_agent  VARCHAR(500) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_actor (actor_id),
  KEY idx_audit_entity (entity, entity_id),
  KEY idx_audit_created (created_at),
  KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_templates (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key`      VARCHAR(60)  NOT NULL,
  name       VARCHAR(120) NOT NULL,
  subject    VARCHAR(255) NOT NULL,
  body       MEDIUMTEXT   NOT NULL,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_templates_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tracking_scripts (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(120) NOT NULL,
  placement        ENUM('HEAD','BODY_START','BODY_END') NOT NULL DEFAULT 'HEAD',
  environment      ENUM('ALL','PRODUCTION','DEVELOPMENT') NOT NULL DEFAULT 'ALL',
  is_active        TINYINT(1) NOT NULL DEFAULT 0,
  requires_consent TINYINT(1) NOT NULL DEFAULT 1,
  code             MEDIUMTEXT NOT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
