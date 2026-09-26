-- Glamorous PHP app — schema (MariaDB/MySQL, utf8mb4)
-- Port of SPEC.md domain model. Two companies, shared customers.
-- Install via public_html/install.php or: mysql < schema.sql && mysql < seed.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS companies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  abbr VARCHAR(10) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'MWK'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  is_group TINYINT(1) NOT NULL DEFAULT 0,
  parent_id INT UNSIGNED NULL,
  UNIQUE KEY uq_wh (company_id, name),
  FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  whatsapp VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  preferred_contact VARCHAR(20) NOT NULL DEFAULT 'Phone',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cust_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'customer',
  customer_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  parent_id INT UNSIGNED NULL,
  business_unit VARCHAR(20) NOT NULL DEFAULT 'Shared',
  FOREIGN KEY (parent_id) REFERENCES item_groups(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  item_group_id INT UNSIGNED NULL,
  company_id INT UNSIGNED NULL,
  uom VARCHAR(20) NOT NULL DEFAULT 'Pc',
  item_type VARCHAR(20) NOT NULL DEFAULT 'stock',
  business_unit VARCHAR(20) NOT NULL DEFAULT 'Shared',
  cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  replacement_rate DECIMAL(14,2) NOT NULL DEFAULT 0,
  stock_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(14,3) NOT NULL DEFAULT 0,
  default_warehouse_id INT UNSIGNED NULL,
  published TINYINT(1) NOT NULL DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  description TEXT NULL,
  FOREIGN KEY (item_group_id) REFERENCES item_groups(id),
  FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_moves (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  qty_change DECIMAL(14,3) NOT NULL,
  ref_type VARCHAR(40) NULL,
  ref_id INT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS modes_of_payment (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  mode VARCHAR(40) NOT NULL,
  account_name VARCHAR(120) NOT NULL,
  UNIQUE KEY uq_mop (company_id, mode),
  FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Draft',
  fulfilment_method VARCHAR(20) NOT NULL DEFAULT 'Customer Pickup',
  delivery_address TEXT NULL,
  contact_person VARCHAR(150) NULL,
  contact_phone VARCHAR(40) NULL,
  preferred_delivery DATETIME NULL,
  delivery_notes TEXT NULL,
  delivery_status VARCHAR(30) NOT NULL DEFAULT 'Not Required',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  delivery_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales_order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  qty DECIMAL(14,3) NOT NULL,
  rate DECIMAL(14,2) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NULL,
  rental_booking_id INT UNSIGNED NULL,
  cake_order_id INT UNSIGNED NULL,
  label VARCHAR(150) NOT NULL DEFAULT 'Sales Invoice',
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Unpaid',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (order_id) REFERENCES sales_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  invoice_id INT UNSIGNED NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'invoice',
  amount DECIMAL(14,2) NOT NULL,
  method VARCHAR(40) NOT NULL,
  reference VARCHAR(120) NULL,
  payment_date DATE NOT NULL,
  proof_path VARCHAR(255) NULL,
  sender_detail VARCHAR(120) NULL,
  received_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_declarations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  ref_type VARCHAR(40) NOT NULL,
  ref_id INT UNSIGNED NOT NULL,
  invoice_id INT UNSIGNED NULL,
  amount DECIMAL(14,2) NOT NULL,
  method VARCHAR(40) NOT NULL,
  reference VARCHAR(120) NOT NULL,
  payment_date DATE NOT NULL,
  proof_path VARCHAR(255) NULL,
  sender_detail VARCHAR(120) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Submitted',
  verified_by INT UNSIGNED NULL,
  payment_id INT UNSIGNED NULL,
  remarks TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  enquiry_type VARCHAR(30) NOT NULL,
  customer_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  subject VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  event_date DATE NULL,
  source VARCHAR(30) NOT NULL DEFAULT 'Website',
  status VARCHAR(20) NOT NULL DEFAULT 'Open',
  assigned_to INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  event_type VARCHAR(30) NOT NULL,
  event_date DATE NOT NULL,
  venue VARCHAR(200) NOT NULL,
  venue_address TEXT NULL,
  guests INT NULL,
  contact_person VARCHAR(150) NULL,
  contact_phone VARCHAR(40) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Enquiry',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quotations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  delivery_setup DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  deposit_required DECIMAL(14,2) NOT NULL DEFAULT 0,
  valid_until DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Draft',
  order_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (event_id) REFERENCES events(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quotation_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  service_type VARCHAR(20) NOT NULL,
  item_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  qty DECIMAL(14,3) NOT NULL,
  rate DECIMAL(14,2) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cake_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NULL,
  order_type VARCHAR(20) NOT NULL,
  product_item_id INT UNSIGNED NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  required_date DATE NOT NULL,
  customization TEXT NULL,
  reference_image VARCHAR(255) NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  deposit_required DECIMAL(14,2) NOT NULL DEFAULT 0,
  deposit_received DECIMAL(14,2) NOT NULL DEFAULT 0,
  fulfilment_method VARCHAR(20) NOT NULL DEFAULT 'Customer Pickup',
  delivery_address TEXT NULL,
  contact_phone VARCHAR(40) NULL,
  production_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
  status VARCHAR(30) NOT NULL DEFAULT 'Awaiting Quotation',
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (event_id) REFERENCES events(id),
  FOREIGN KEY (product_item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS catering_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NULL,
  service_date DATE NOT NULL,
  guests INT NOT NULL,
  menu_item_id INT UNSIGNED NOT NULL,
  rate_per_head DECIMAL(14,2) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  delivery_setup DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Draft',
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (event_id) REFERENCES events(id),
  FOREIGN KEY (menu_item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rental_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NULL,
  booking_date DATE NOT NULL,
  event_date DATE NOT NULL,
  return_expected DATE NOT NULL,
  fulfilment_method VARCHAR(20) NOT NULL DEFAULT 'Customer Pickup',
  delivery_address TEXT NULL,
  contact_person VARCHAR(150) NULL,
  contact_phone VARCHAR(40) NULL,
  delivery_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  rental_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  deposit_required DECIMAL(14,2) NOT NULL DEFAULT 0,
  deposit_received DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Draft',
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (event_id) REFERENCES events(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rental_booking_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  rate DECIMAL(14,2) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (booking_id) REFERENCES rental_bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rental_returns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  company_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  return_actual DATE NOT NULL,
  damage_charges DECIMAL(14,2) NOT NULL DEFAULT 0,
  missing_charges DECIMAL(14,2) NOT NULL DEFAULT 0,
  cleaning_charges DECIMAL(14,2) NOT NULL DEFAULT 0,
  forfeited DECIMAL(14,2) NOT NULL DEFAULT 0,
  refund_due DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Inspected',
  FOREIGN KEY (booking_id) REFERENCES rental_bookings(id),
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rental_return_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  qty_dispatched INT NOT NULL,
  qty_good INT NOT NULL DEFAULT 0,
  qty_damaged INT NOT NULL DEFAULT 0,
  qty_missing INT NOT NULL DEFAULT 0,
  damage_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  missing_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  remarks VARCHAR(255) NULL,
  FOREIGN KEY (return_id) REFERENCES rental_returns(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(80) PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  supplier_ref VARCHAR(120) NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Received',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  qty DECIMAL(14,3) NOT NULL,
  cost DECIMAL(14,2) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
