-- Migrate 005: product images for the website catalogue.

ALTER TABLE items
  ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL;
