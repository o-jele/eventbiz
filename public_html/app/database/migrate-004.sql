-- Migrate 004: event-specific line descriptions on sales orders
-- (so invoices show "300x chairs", not just the generic item name).

ALTER TABLE sales_order_items
  ADD COLUMN IF NOT EXISTS description VARCHAR(255) NULL;
