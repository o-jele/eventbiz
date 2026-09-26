-- Migrate 009: customer birthdays (birthday-week widget).

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS birth_date DATE NULL;
