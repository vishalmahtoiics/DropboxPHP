-- URL health (404 log) and the backup system.

CREATE TABLE not_found_log (
  path_hash     CHAR(64) NOT NULL PRIMARY KEY,
  path          VARCHAR(500) NOT NULL,
  hits          INT UNSIGNED NOT NULL DEFAULT 1,
  last_referrer VARCHAR(500) NULL,
  status        ENUM('OPEN','IGNORED','RESOLVED') NOT NULL DEFAULT 'OPEN',
  first_seen_at DATETIME NOT NULL,
  last_seen_at  DATETIME NOT NULL,
  KEY idx_nf_status (status, hits)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backups (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  filename      VARCHAR(255) NOT NULL,
  type          ENUM('FULL','DATABASE','MEDIA') NOT NULL DEFAULT 'FULL',
  status        ENUM('PENDING','RUNNING','COMPLETED','FAILED','RESTORING') NOT NULL DEFAULT 'PENDING',
  origin        ENUM('MANUAL','SCHEDULED','SAFETY','IMPORTED') NOT NULL DEFAULT 'MANUAL',
  size          BIGINT UNSIGNED NULL,
  checksum      CHAR(64) NULL,
  tables_count  INT NULL,
  rows_count    BIGINT NULL,
  media_count   INT NULL,
  error         TEXT NULL,
  note          VARCHAR(255) NULL,
  created_by_id BIGINT UNSIGNED NULL,
  started_at    DATETIME NULL,
  completed_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_backups_created (created_at),
  CONSTRAINT fk_backups_user FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backup_restores (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  backup_id      BIGINT UNSIGNED NULL,
  safety_backup_id BIGINT UNSIGNED NULL,
  status         ENUM('RUNNING','COMPLETED','FAILED') NOT NULL DEFAULT 'RUNNING',
  error          TEXT NULL,
  restored_by_id BIGINT UNSIGNED NULL,
  started_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at   DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row: the global backup/restore lock (MySQL GET_LOCK is per-connection,
-- so a row lock with an expiry survives the request that took it).
CREATE TABLE system_locks (
  name       VARCHAR(40) NOT NULL PRIMARY KEY,
  owner      VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
