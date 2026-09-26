-- Migrate 007: register POS (OpenSourcePOS-inspired counter sale).
-- Per-line discounts, tendered/change on orders, suspended (parked) sales.

ALTER TABLE sales_orders
  ADD COLUMN IF NOT EXISTS discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS tendered DECIMAL(14,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS change_due DECIMAL(14,2) NOT NULL DEFAULT 0;

ALTER TABLE sales_order_items
  ADD COLUMN IF NOT EXISTS discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS suspended_sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id INT UNSIGNED NULL,
  customer_name VARCHAR(150) NULL,
  customer_phone VARCHAR(40) NULL,
  payload TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
