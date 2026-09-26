-- Migrate 008: suspend context (which POS parked the sale).

ALTER TABLE suspended_sales
  ADD COLUMN IF NOT EXISTS context VARCHAR(10) NOT NULL DEFAULT 'gc';
