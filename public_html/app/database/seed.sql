-- Glamorous seed: companies, warehouses, item groups, payment modes, settings.
-- Admin user is created by install.php (never ship default passwords).

INSERT INTO companies (name, abbr, currency) VALUES
  ('Glamorous Creations', 'GC', 'MWK'),
  ('Glamorous Delights', 'GD', 'MWK');

-- Warehouses (parent groups first; parent_id fixed up below by name lookup).
INSERT INTO warehouses (company_id, name, is_group, parent_id) VALUES
  ((SELECT id FROM companies WHERE abbr='GC'), 'Creations Premises', 1, NULL),
  ((SELECT id FROM companies WHERE abbr='GD'), 'Delights Premises', 1, NULL);

INSERT INTO warehouses (company_id, name, is_group, parent_id) VALUES
  ((SELECT id FROM companies WHERE abbr='GC'), 'CREATIONS - SHOP - GC', 0,
    (SELECT id FROM warehouses WHERE name='Creations Premises')),
  ((SELECT id FROM companies WHERE abbr='GC'), 'CREATIONS - STORE - GC', 0,
    (SELECT id FROM warehouses WHERE name='Creations Premises')),
  ((SELECT id FROM companies WHERE abbr='GD'), 'DELIGHTS - BAKERY - GD', 0,
    (SELECT id FROM warehouses WHERE name='Delights Premises')),
  ((SELECT id FROM companies WHERE abbr='GD'), 'DELIGHTS - CATERING - GD', 0,
    (SELECT id FROM warehouses WHERE name='Delights Premises')),
  ((SELECT id FROM companies WHERE abbr='GD'), 'DELIGHTS - RENTAL - GD', 0,
    (SELECT id FROM warehouses WHERE name='Delights Premises'));

INSERT INTO item_groups (name, parent_id, business_unit) VALUES
  ('Creations', NULL, 'Creations'),
  ('Delights - Bakery', NULL, 'Delights'),
  ('Delights - Catering', NULL, 'Delights'),
  ('Delights - Rental', NULL, 'Delights'),
  ('Services', NULL, 'Shared');

INSERT INTO item_groups (name, parent_id, business_unit) VALUES
  ('Baking Ingredients', (SELECT id FROM item_groups WHERE name='Creations'), 'Creations'),
  ('Baking Tools & Equipment', (SELECT id FROM item_groups WHERE name='Creations'), 'Creations'),
  ('Packaging', (SELECT id FROM item_groups WHERE name='Creations'), 'Creations'),
  ('Cakes', (SELECT id FROM item_groups WHERE name='Delights - Bakery'), 'Delights'),
  ('Fritters & Snacks', (SELECT id FROM item_groups WHERE name='Delights - Bakery'), 'Delights'),
  ('Bakery Ingredients', (SELECT id FROM item_groups WHERE name='Delights - Bakery'), 'Delights'),
  ('Catering Menus', (SELECT id FROM item_groups WHERE name='Delights - Catering'), 'Delights'),
  ('Catering Services', (SELECT id FROM item_groups WHERE name='Delights - Catering'), 'Delights'),
  ('Furniture', (SELECT id FROM item_groups WHERE name='Delights - Rental'), 'Delights'),
  ('Cookware & Serving', (SELECT id FROM item_groups WHERE name='Delights - Rental'), 'Delights'),
  ('Tents & Decor', (SELECT id FROM item_groups WHERE name='Delights - Rental'), 'Delights'),
  ('Delivery & Setup', (SELECT id FROM item_groups WHERE name='Services'), 'Shared'),
  ('Event Staffing', (SELECT id FROM item_groups WHERE name='Services'), 'Shared');

INSERT INTO modes_of_payment (company_id, mode, account_name) VALUES
  ((SELECT id FROM companies WHERE abbr='GC'), 'Cash', 'Cash - GC'),
  ((SELECT id FROM companies WHERE abbr='GC'), 'Bank Transfer', 'Bank Account - GC'),
  ((SELECT id FROM companies WHERE abbr='GC'), 'Mobile Money', 'Mobile Money - GC'),
  ((SELECT id FROM companies WHERE abbr='GC'), 'Other Manual', 'Cash - GC'),
  ((SELECT id FROM companies WHERE abbr='GD'), 'Cash', 'Cash - GD'),
  ((SELECT id FROM companies WHERE abbr='GD'), 'Bank Transfer', 'Bank Account - GD'),
  ((SELECT id FROM companies WHERE abbr='GD'), 'Mobile Money', 'Mobile Money - GD'),
  ((SELECT id FROM companies WHERE abbr='GD'), 'Other Manual', 'Cash - GD');

-- Starter catalogue examples (edit in Admin → Items afterwards).
INSERT INTO items (sku, name, item_group_id, company_id, uom, item_type, business_unit, cost, price, stock_qty, default_warehouse_id, published, description) VALUES
  ('FLR-001', 'Flour 50kg', (SELECT id FROM item_groups WHERE name='Baking Ingredients'),
    (SELECT id FROM companies WHERE abbr='GC'), 'Bag', 'stock', 'Creations', 85000, 95000, 0,
    (SELECT id FROM warehouses WHERE name='CREATIONS - STORE - GC'), 1, 'All-purpose flour'),
  ('CHR-001', 'Plastic Chair', (SELECT id FROM item_groups WHERE name='Furniture'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Pc', 'rental', 'Delights', 15000, 2000, 300,
    (SELECT id FROM warehouses WHERE name='DELIGHTS - RENTAL - GD'), 1, 'White plastic chair, per-event rate'),
  ('MENU-A', 'Wedding Menu A', (SELECT id FROM item_groups WHERE name='Catering Menus'),
    (SELECT id FROM companies WHERE abbr='GD'), 'Plate', 'service', 'Delights', 8000, 12000, 0, NULL, 1, 'Wedding catering per head');

INSERT INTO settings (`k`, `v`) VALUES
  ('site_name', 'Glamorous'),
  ('currency', 'MWK');
