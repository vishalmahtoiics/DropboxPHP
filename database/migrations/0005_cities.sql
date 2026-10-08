-- Cities: local address spaces inside a market (/delhi, /ae/dubai).

CREATE TABLE cities (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  country_id           BIGINT UNSIGNED NOT NULL,
  name                 VARCHAR(120) NOT NULL,
  slug                 VARCHAR(80)  NOT NULL,
  state                VARCHAR(120) NULL,
  is_active            TINYINT(1) NOT NULL DEFAULT 1,
  is_published         TINYINT(1) NOT NULL DEFAULT 1,
  no_index             TINYINT(1) NOT NULL DEFAULT 0,
  exclude_from_sitemap TINYINT(1) NOT NULL DEFAULT 0,
  settings             JSON NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cities_slug (country_id, slug),
  CONSTRAINT fk_cities_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pages
  ADD COLUMN generation_batch VARCHAR(32) NULL AFTER generated_from_id,
  ADD COLUMN generated_at DATETIME NULL AFTER generation_batch,
  ADD CONSTRAINT fk_pages_city FOREIGN KEY (city_id) REFERENCES cities (id) ON DELETE RESTRICT;
