-- Glamorous DEMO seed — dev/test ONLY, never run on production.
-- Run ONCE on a dev database AFTER install.php (it needs the base seed):
--   mysql glamorous < seed-demo.sql
-- Demo logins (all password: DemoPass123!):
--   accounts@glamorous.mw  Accounts Manager
--   sales@glamorous.mw     Delights Sales
--   ops@glamorous.mw       Delights Ops
--   jane@demo.mw           Customer (Jane Banda)
-- For production, do a FRESH install.php run instead of cleaning demo data.

SET NAMES utf8mb4;

-- Top up the base flour to a demo level.
UPDATE items SET stock_qty = 40 WHERE sku = 'FLR-001';
UPDATE items SET replacement_rate = 15000 WHERE sku = 'CHR-001';

INSERT IGNORE INTO items
  (sku, name, item_group_id, company_id, uom, item_type, business_unit, cost, price, replacement_rate, stock_qty, default_warehouse_id, published, description)
VALUES
  ('SUG-050', 'Sugar 50kg',
    (SELECT id FROM item_groups WHERE name='Baking Ingredients'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Bag', 'stock', 'Creations', 78000, 88000, 0, 25,
    (SELECT id FROM warehouses WHERE name='CREATIONS - STORE - GC'), 1, 'Brown sugar'),
  ('BKP-001', 'Baking Powder 1kg',
    (SELECT id FROM item_groups WHERE name='Baking Ingredients'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Pc', 'stock', 'Creations', 12000, 15000, 0, 60,
    (SELECT id FROM warehouses WHERE name='CREATIONS - STORE - GC'), 1, ''),
  ('OIL-005', 'Cooking Oil 5L',
    (SELECT id FROM item_groups WHERE name='Baking Ingredients'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Bottle', 'stock', 'Creations', 22000, 26000, 0, 30,
    (SELECT id FROM warehouses WHERE name='CREATIONS - STORE - GC'), 1, ''),
  ('BUT-500', 'Butter 500g',
    (SELECT id FROM item_groups WHERE name='Baking Ingredients'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Pc', 'stock', 'Creations', 9500, 12000, 0, 40,
    (SELECT id FROM warehouses WHERE name='CREATIONS - SHOP - GC'), 1, ''),
  ('EGG-TRY', 'Eggs Tray (30)',
    (SELECT id FROM item_groups WHERE name='Baking Ingredients'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Tray', 'stock', 'Creations', 8000, 10000, 0, 50,
    (SELECT id FROM warehouses WHERE name='CREATIONS - SHOP - GC'), 1, 'Farm eggs'),
  ('BWL-SET', 'Mixing Bowl Set',
    (SELECT id FROM item_groups WHERE name='Baking Tools & Equipment'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Set', 'stock', 'Creations', 25000, 32000, 0, 15,
    (SELECT id FROM warehouses WHERE name='CREATIONS - SHOP - GC'), 1, ''),
  ('CUP-MEA', 'Measuring Cup Set',
    (SELECT id FROM item_groups WHERE name='Baking Tools & Equipment'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Set', 'stock', 'Creations', 8000, 11000, 0, 20,
    (SELECT id FROM warehouses WHERE name='CREATIONS - SHOP - GC'), 1, ''),
  ('TRY-BAK', 'Baking Tray Large',
    (SELECT id FROM item_groups WHERE name='Baking Tools & Equipment'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Pc', 'stock', 'Creations', 15000, 19000, 0, 18,
    (SELECT id FROM warehouses WHERE name='CREATIONS - SHOP - GC'), 1, ''),
  ('WSK-001', 'Whisk Steel',
    (SELECT id FROM item_groups WHERE name='Baking Tools & Equipment'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Pc', 'stock', 'Creations', 5000, 7500, 0, 25,
    (SELECT id FROM warehouses WHERE name='CREATIONS - SHOP - GC'), 1, ''),
  ('BOX-LG', 'Cake Box Large',
    (SELECT id FROM item_groups WHERE name='Packaging'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Pc', 'stock', 'Creations', 1500, 2500, 0, 200,
    (SELECT id FROM warehouses WHERE name='CREATIONS - SHOP - GC'), 1, ''),
  ('BAG-100', 'Paper Bags pk100',
    (SELECT id FROM item_groups WHERE name='Packaging'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Pack', 'stock', 'Creations', 6000, 8500, 0, 60,
    (SELECT id FROM warehouses WHERE name='CREATIONS - SHOP - GC'), 1, ''),
  ('TBL-008', 'Round Table 8-seater',
    (SELECT id FROM item_groups WHERE name='Furniture'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Pc', 'rental', 'Delights', 45000, 8000, 45000, 40,
    (SELECT id FROM warehouses WHERE name='DELIGHTS - RENTAL - GD'), 1, 'Per-event rate'),
  ('TNT-050', 'Tent 50-seater',
    (SELECT id FROM item_groups WHERE name='Tents & Decor'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Pc', 'rental', 'Delights', 800000, 250000, 800000, 4,
    (SELECT id FROM warehouses WHERE name='DELIGHTS - RENTAL - GD'), 1, 'Includes setup'),
  ('PLT-DIN', 'Dinner Plate',
    (SELECT id FROM item_groups WHERE name='Cookware & Serving'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Pc', 'rental', 'Delights', 2500, 500, 2500, 500,
    (SELECT id FROM warehouses WHERE name='DELIGHTS - RENTAL - GD'), 1, ''),
  ('FRK-001', 'Fork',
    (SELECT id FROM item_groups WHERE name='Cookware & Serving'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Pc', 'rental', 'Delights', 800, 200, 800, 500,
    (SELECT id FROM warehouses WHERE name='DELIGHTS - RENTAL - GD'), 1, ''),
  ('GLS-001', 'Glass',
    (SELECT id FROM item_groups WHERE name='Cookware & Serving'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Pc', 'rental', 'Delights', 1200, 300, 1200, 300,
    (SELECT id FROM warehouses WHERE name='DELIGHTS - RENTAL - GD'), 1, ''),
  ('CHF-001', 'Chafing Dish',
    (SELECT id FROM item_groups WHERE name='Cookware & Serving'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Pc', 'rental', 'Delights', 60000, 15000, 60000, 20,
    (SELECT id FROM warehouses WHERE name='DELIGHTS - RENTAL - GD'), 1, ''),
  ('MENU-B', 'Funeral Menu B',
    (SELECT id FROM item_groups WHERE name='Catering Menus'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Plate', 'service', 'Delights', 6000, 9000, 0, 0, NULL, 1, ''),
  ('MENU-C', 'Corporate Menu C',
    (SELECT id FROM item_groups WHERE name='Catering Menus'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Plate', 'service', 'Delights', 10000, 14000, 0, 0, NULL, 1, ''),
  ('CKB-WED', 'Wedding Cake Base (per tier)',
    (SELECT id FROM item_groups WHERE name='Cakes'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Tier', 'service', 'Delights', 40000, 65000, 0, 0, NULL, 1, 'Quoted per design'),
  ('CKB-CUP', 'Cupcakes (per dozen)',
    (SELECT id FROM item_groups WHERE name='Cakes'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Dozen', 'service', 'Delights', 15000, 22000, 0, 0, NULL, 1, ''),
  ('FRT-MAN', 'Mandazi (per 50)',
    (SELECT id FROM item_groups WHERE name='Fritters & Snacks'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Batch', 'service', 'Delights', 12000, 18000, 0, 0, NULL, 1, ''),
  ('DLV-001', 'Delivery (per trip)',
    (SELECT id FROM item_groups WHERE name='Delivery & Setup'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Trip', 'service', 'Shared', 30000, 50000, 0, 0, NULL, 1, ''),
  ('SET-001', 'Setup Crew (per event)',
    (SELECT id FROM item_groups WHERE name='Delivery & Setup'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Event', 'service', 'Shared', 40000, 70000, 0, 0, NULL, 1, ''),
  ('WTR-001', 'Waiter (per staff-day)',
    (SELECT id FROM item_groups WHERE name='Event Staffing'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Day', 'service', 'Shared', 15000, 25000, 0, 0, NULL, 1, '');

INSERT INTO customers (name, phone, whatsapp, email) VALUES
  ('Jane Banda', '0999111000', '0999111000', 'jane@demo.mw'),
  ('Chikondi Phiri', '0999222000', '', '');

INSERT IGNORE INTO suppliers (name, phone) VALUES
  ('Lilongwe Millers', '0999333000');

-- Demo logins, all password DemoPass123! (bcrypt below is that password).
INSERT INTO users (name, email, password_hash, role, customer_id) VALUES
  ('Accounts', 'accounts@glamorous.mw',
   '$2y$12$EQZx6WEkaAQwGgoMvlQgHebK4gqt.GYEUG9rslME3OOnWQ3Y38xRC', 'accounts', NULL),
  ('Delights Sales', 'sales@glamorous.mw',
   '$2y$12$EQZx6WEkaAQwGgoMvlQgHebK4gqt.GYEUG9rslME3OOnWQ3Y38xRC', 'delights_sales', NULL),
  ('Delights Ops', 'ops@glamorous.mw',
   '$2y$12$EQZx6WEkaAQwGgoMvlQgHebK4gqt.GYEUG9rslME3OOnWQ3Y38xRC', 'delights_ops', NULL),
  ('Jane Banda', 'jane@demo.mw',
   '$2y$12$EQZx6WEkaAQwGgoMvlQgHebK4gqt.GYEUG9rslME3OOnWQ3Y38xRC', 'customer',
   (SELECT id FROM customers WHERE email = 'jane@demo.mw'));

-- Demo event + draft quotation + draft booking (safe: Draft holds no stock).
INSERT INTO events (company_id, customer_id, name, event_type, event_date, venue, guests, contact_person, contact_phone, status) VALUES
  ((SELECT id FROM companies WHERE abbr='GD'),
   (SELECT id FROM customers WHERE email='jane@demo.mw'),
   'Demo Wedding - Banda', 'Wedding', '2026-12-19', 'Bingu Hall', 200,
   'Jane Banda', '0999111000', 'Enquiry');
SET @ev = LAST_INSERT_ID();
SET @cu = (SELECT id FROM customers WHERE email='jane@demo.mw');
SET @gd = (SELECT id FROM companies WHERE abbr='GD');

INSERT INTO quotations (company_id, customer_id, event_id, subtotal, delivery_setup, grand_total, deposit_required, valid_until, status) VALUES
  (@gd, @cu, @ev, 2850000, 0, 2850000, 800000, '2026-11-30', 'Draft');
SET @q = LAST_INSERT_ID();

INSERT INTO quotation_services (quotation_id, service_type, item_id, description, qty, rate, amount) VALUES
  (@q, 'Rental', (SELECT id FROM items WHERE sku='CHR-001'), '200x plastic chairs', 200, 2000, 400000),
  (@q, 'Catering', (SELECT id FROM items WHERE sku='MENU-A'), '200x Wedding Menu A', 200, 12000, 2400000),
  (@q, 'Delivery', (SELECT id FROM items WHERE sku='DLV-001'), 'Delivery to Bingu Hall', 1, 50000, 50000);

INSERT INTO rental_bookings (company_id, customer_id, event_id, booking_date, event_date, return_expected,
  fulfilment_method, delivery_charge, rental_total, grand_total, deposit_required, status) VALUES
  (@gd, @cu, @ev, CURDATE(), '2026-12-19', '2026-12-21', 'Delivery', 50000, 600000, 650000, 200000, 'Draft');

SET @b = LAST_INSERT_ID();

INSERT INTO rental_booking_items (booking_id, item_id, qty, rate, amount) VALUES
  (@b, (SELECT id FROM items WHERE sku='CHR-001'), 200, 2000, 400000),
  (@b, (SELECT id FROM items WHERE sku='TBL-008'), 25, 8000, 200000);
