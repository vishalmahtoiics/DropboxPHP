-- Visual page builder: drag-and-drop layouts for pages, products and
-- reusable templates, with revisions.

CREATE TABLE builder_templates (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(160) NOT NULL,
  kind           ENUM('page','section','product') NOT NULL DEFAULT 'section',
  layout         JSON NOT NULL,
  draft          JSON NULL,
  is_default     TINYINT(1) NOT NULL DEFAULT 0,
  created_by_id  BIGINT UNSIGNED NULL,
  updated_by_id  BIGINT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_builder_templates_kind (kind, is_default),
  CONSTRAINT fk_builder_templates_creator FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_builder_templates_updater FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE builder_revisions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_type  ENUM('page','product','template') NOT NULL,
  owner_id    BIGINT UNSIGNED NOT NULL,
  layout      JSON NOT NULL,
  user_id     BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_builder_revisions_owner (owner_type, owner_id, id),
  CONSTRAINT fk_builder_revisions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pages
  ADD COLUMN editor ENUM('sections','builder') NOT NULL DEFAULT 'sections' AFTER show_footer,
  ADD COLUMN builder_layout JSON NULL AFTER editor,
  ADD COLUMN builder_draft JSON NULL AFTER builder_layout;

-- page_layout: classic = the built-in product page, template = a product
-- template (the default one unless template_id picks another; classic when
-- none exists), builder = this product's own layout.
ALTER TABLE products
  ADD COLUMN page_layout ENUM('classic','template','builder') NOT NULL DEFAULT 'template' AFTER cta_form_id,
  ADD COLUMN template_id BIGINT UNSIGNED NULL AFTER page_layout,
  ADD COLUMN builder_layout JSON NULL AFTER template_id,
  ADD COLUMN builder_draft JSON NULL AFTER builder_layout,
  ADD CONSTRAINT fk_products_template FOREIGN KEY (template_id) REFERENCES builder_templates (id) ON DELETE SET NULL;
