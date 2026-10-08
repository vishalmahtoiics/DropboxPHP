-- Header and footer templates for the visual builder: the active one
-- (is_default = 1) replaces the site's built-in header or footer.
ALTER TABLE builder_templates
  MODIFY kind ENUM('page','section','product','header','footer') NOT NULL DEFAULT 'section';
