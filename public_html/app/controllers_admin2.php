<?php
// Staff backend part 2: bakery board, catering board, rental creation,
// counter-sale POS, purchasing, password change.
declare(strict_types=1);

// ---------- Bakery board ----------
function pg_admin_bakery(): void
{
    $u = require_role(['admin', 'accounts', 'delights_sales', 'delights_ops']);
    $flow = ['Pending', 'Scheduled', 'In Production', 'Ready', 'Delivered'];

    // Quote a cake request: set price + deposit.
    if (($_GET['quote'] ?? '') !== '') {
        $k = db_one('SELECT * FROM cake_orders WHERE id = ?', [(int) $_GET['quote']]);
        if (!$k) {
            exit('Unknown cake order.');
        }
        guard_company(company_name((int) $k['company_id']));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_csrf();
            $price = (float) post('price');
            if ($price <= 0) {
                flash('Price must be above zero.', 'err');
                redirect('/admin/bakery?quote=' . (int) $k['id']);
            }
            db_exec("UPDATE cake_orders SET price=?, deposit_required=?, status='Quoted' WHERE id=?",
                [$price, (float) post('deposit_required'), (int) $k['id']]);
            flash('Cake order #' . $k['id'] . ' quoted at MWK ' . money($price) . '.');
            redirect('/admin/bakery');
        }
        layout('Quote cake', admin_nav() . '<h1>Quote cake order #' . (int) $k['id'] . '</h1>
          <div class="card"><form method="post">' . csrf_field() . '
          ' . field('Agreed price (MWK)', '<input name="price" required>') . '
          ' . field('Deposit required (MWK)', '<input name="deposit_required" value="0">') . '
          <button class="btn">Send quotation</button></form></div>');
        return;
    }
    // Confirm a quoted cake: creates sales order + invoice.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cake'])) {
        check_csrf();
        $k = db_one('SELECT * FROM cake_orders WHERE id = ?', [(int) $_POST['confirm_cake']]);
        if (!$k || !in_array($k['status'], ['Quoted', 'Awaiting Quotation'], true) || (float) $k['price'] <= 0) {
            exit('Cake order cannot be confirmed (needs a quoted price).');
        }
        guard_company(company_name((int) $k['company_id']));
        $rate = (float) $k['price'] / max(1, (float) $k['quantity']);
        db()->beginTransaction();
        db_exec(
            "INSERT INTO sales_orders (company_id, customer_id, status, fulfilment_method, delivery_address,
             contact_phone, delivery_status, subtotal, grand_total, created_by)
             VALUES (?,?, 'Confirmed', ?,?,?,?,?,?,?)",
            [(int) $k['company_id'], (int) $k['customer_id'], $k['fulfilment_method'],
             $k['delivery_address'], $k['contact_phone'],
             $k['fulfilment_method'] === 'Delivery' ? 'Pending Arrangement' : 'Not Required',
             (float) $k['price'], (float) $k['price'], (int) $u['id']]
        );
        $oid = db_last_id();
        db_exec('INSERT INTO sales_order_items (order_id, item_id, qty, rate, amount) VALUES (?,?,?,?,?)',
            [$oid, (int) $k['product_item_id'], (float) $k['quantity'], $rate, (float) $k['price']]);
        $inv = make_invoice((int) $k['company_id'], (int) $k['customer_id'], $oid, 'Cake order #' . $k['id'], (float) $k['price']);
        db_exec('UPDATE invoices SET cake_order_id=? WHERE id=?', [(int) $k['id'], $inv]);
        db_exec("UPDATE cake_orders SET status='Confirmed' WHERE id=?", [(int) $k['id']]);
        db()->commit();
        flash('Confirmed: order #' . $oid . ', invoice #' . $inv . '.');
        redirect('/admin/bakery');
    }
    // Advance production one step.
    if (($_GET['prod'] ?? '') !== '' && ($_GET['to'] ?? '') !== '') {
        $k = db_one('SELECT * FROM cake_orders WHERE id = ?', [(int) $_GET['prod']]);
        $to = $_GET['to'];
        $i = array_search($k['production_status'] ?? '', $flow, true);
        if ($k && $i !== false && ($flow[$i + 1] ?? null) === $to) {
            guard_company(company_name((int) $k['company_id']));
            db_exec('UPDATE cake_orders SET production_status=?, status=? WHERE id=?',
                [$to, $to === 'Delivered' ? 'Delivered' : $k['status'], (int) $k['id']]);
            flash('Cake #' . $k['id'] . ' → ' . $to . '.');
        }
        redirect('/admin/bakery');
    }
    if (($_GET['done'] ?? '') !== '') {
        db_exec("UPDATE cake_orders SET status='Completed' WHERE id=?", [(int) $_GET['done']]);
        redirect('/admin/bakery');
    }
    $f = get_param('f');
    $sql = 'SELECT k.*, c.name AS company, t.name AS customer, i.name AS product FROM cake_orders k
            JOIN companies c ON c.id=k.company_id JOIN customers t ON t.id=k.customer_id
            JOIN items i ON i.id=k.product_item_id'
        . ($f !== '' ? ' WHERE k.status = ?' : '') . ' ORDER BY k.required_date LIMIT 100';
    $rows = $f !== '' ? db_all($sql, [$f]) : db_all($sql);
    $tr = [];
    foreach ($rows as $r) {
        $acts = '';
        if ($r['status'] === 'Awaiting Quotation') {
            $acts .= ' <a href="/admin/bakery?quote=' . (int) $r['id'] . '">Quote</a>';
        }
        if (in_array($r['status'], ['Quoted', 'Awaiting Quotation'], true) && (float) $r['price'] > 0) {
            $acts .= ' <form method="post" style="display:inline">' . csrf_field() . '
              <input type="hidden" name="confirm_cake" value="' . (int) $r['id'] . '">
              <button class="btn sec">Confirm</button></form>';
        }
        $i = array_search($r['production_status'], $flow, true);
        if ($i !== false && isset($flow[$i + 1])) {
            $acts .= ' <a href="/admin/bakery?prod=' . (int) $r['id'] . '&to=' . $flow[$i + 1] . '">→ ' . $flow[$i + 1] . '</a>';
        }
        if ($r['status'] === 'Delivered') {
            $acts .= ' <a href="/admin/bakery?done=' . (int) $r['id'] . '">Complete</a>';
        }
        $tr[] = [brand_badge($r['company']), '#' . $r['id'] . ' ' . e($r['product']),
                 e($r['customer']), e($r['required_date']),
                 money((float) $r['price']) . '<br><span class="mut">' . e($r['status']) . ' / ' . e($r['production_status']) . '</span>', $acts];
    }
    layout('Bakery', admin_nav() . '<h1>Bakery board</h1>
      <p class="mut">Filter: <a href="/admin/bakery">all</a> · <a href="/admin/bakery?f=Awaiting Quotation">awaiting quotation</a> ·
      <a href="/admin/bakery?f=Confirmed">confirmed</a> · <a href="/admin/bakery?f=Delivered">delivered</a></p>' .
        ($tr ? table(['Brand', 'Order', 'Customer', 'Required', 'Price / status', ''], $tr) : '<p class="mut">No cake orders.</p>'));
}

// ---------- Catering board ----------
function pg_admin_catering(): void
{
    require_role(['admin', 'accounts', 'delights_sales', 'delights_ops']);
    $flow = ['Draft' => 'Confirmed', 'Confirmed' => 'In Preparation', 'In Preparation' => 'Delivered', 'Delivered' => 'Completed'];
    if (($_GET['set'] ?? '') !== '' && ($_GET['to'] ?? '') !== '') {
        $c = db_one('SELECT * FROM catering_orders WHERE id = ?', [(int) $_GET['set']]);
        if ($c && ($flow[$c['status']] ?? null) === $_GET['to']) {
            guard_company(company_name((int) $c['company_id']));
            db_exec('UPDATE catering_orders SET status=? WHERE id=?', [$_GET['to'], (int) $c['id']]);
            flash('Catering #' . $c['id'] . ' → ' . $_GET['to'] . '.');
        }
        redirect('/admin/catering');
    }
    $rows = db_all(
        'SELECT o.*, c.name AS company, t.name AS customer, i.name AS menu, v.name AS event FROM catering_orders o
         JOIN companies c ON c.id=o.company_id JOIN customers t ON t.id=o.customer_id
         JOIN items i ON i.id=o.menu_item_id LEFT JOIN events v ON v.id=o.event_id
         ORDER BY o.service_date LIMIT 100'
    );
    $tr = [];
    foreach ($rows as $r) {
        $next = isset($flow[$r['status']])
            ? ' <a href="/admin/catering?set=' . (int) $r['id'] . '&to=' . $flow[$r['status']] . '">→ ' . $flow[$r['status']] . '</a>' : '';
        $tr[] = [brand_badge($r['company']), '#' . $r['id'] . ' ' . e($r['menu']),
                 e($r['event'] ?? '—'), e($r['service_date']) . ' · ' . (int) $r['guests'] . ' guests',
                 money((float) $r['amount']), e($r['status']) . $next];
    }
    layout('Catering', admin_nav() . '<h1>Catering board</h1>' .
        ($tr ? table(['Brand', 'Order', 'Event', 'Service', 'Amount', 'Status'], $tr) : '<p class="mut">No catering orders. They are created when an event quotation is converted.</p>'));
}

// ---------- Staff rental booking creation ----------
function rental_item_options(): string
{
    $items = db_all("SELECT id, name, price, stock_qty FROM items WHERE published = 1 AND item_type = 'rental' ORDER BY name");
    $o = '<option value="">— pick equipment —</option>';
    foreach ($items as $i) {
        $o .= '<option value="' . (int) $i['id'] . '">' . e($i['name']) . ' (own ' . e((string) $i['stock_qty']) . ', ' . money((float) $i['price']) . ')</option>';
    }
    return $o;
}

function pg_admin_rental_new(): void
{
    $u = require_role(['admin', 'accounts', 'delights_sales']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $companyId = company_id(GD_NAME);
        guard_company(GD_NAME);
        $from = post('event_date');
        $to = post('return_expected');
        if ($from === '' || $to === '' || $to < $from) {
            flash('Return date must be on/after event date.', 'err');
            redirect('/admin/rentals?new=1');
        }
        $cid = find_or_create_customer(post('customer_name'), post('customer_phone'));
        if (post('customer_name') === '' || post('customer_phone') === '') {
            flash('Customer name and phone are required.', 'err');
            redirect('/admin/rentals?new=1');
        }
        $itemIds = $_POST['item_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $lines = [];
        $total = 0;
        foreach ($itemIds as $n => $iid) {
            $iid = (int) $iid;
            $q = (int) ($qtys[$n] ?? 0);
            if (!$iid || $q <= 0) {
                continue;
            }
            $it = db_one("SELECT * FROM items WHERE id = ? AND item_type = 'rental'", [$iid]);
            if (!$it) {
                flash('Unknown rental item.', 'err');
                redirect('/admin/rentals?new=1');
            }
            $avail = rental_available($iid, $from, $to);
            if ($avail < $q) {
                flash('Only ' . $avail . ' × ' . $it['name'] . ' free for those dates.', 'err');
                redirect('/admin/rentals?new=1');
            }
            $amt = $q * (float) $it['price'];
            $total += $amt;
            $lines[] = ['id' => $iid, 'qty' => $q, 'rate' => (float) $it['price'], 'amount' => $amt];
        }
        if (!$lines) {
            flash('Add at least one equipment row.', 'err');
            redirect('/admin/rentals?new=1');
        }
        $dcharge = (float) post('delivery_charge', '0');
        $grand = $total + $dcharge;
        db()->beginTransaction();
        db_exec(
            "INSERT INTO rental_bookings (company_id, customer_id, booking_date, event_date, return_expected,
             fulfilment_method, delivery_address, contact_person, contact_phone, delivery_charge,
             rental_total, grand_total, deposit_required, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'Deposit Pending')",
            [$companyId, $cid, date('Y-m-d'), $from, $to, post('fulfilment_method', 'Customer Pickup'),
             post('delivery_address') ?: null, post('customer_name'), post('customer_phone'),
             $dcharge, $total, $grand, (float) post('deposit_required', '0')]
        );
        $bid = db_last_id();
        foreach ($lines as $l) {
            db_exec('INSERT INTO rental_booking_items (booking_id, item_id, qty, rate, amount) VALUES (?,?,?,?,?)',
                [$bid, $l['id'], $l['qty'], $l['rate'], $l['amount']]);
        }
        db()->commit();
        flash('Booking #' . $bid . ' created (MWK ' . money($grand) . '). Confirm it after deposit.');
        redirect('/admin/rentals?view=' . $bid);
    }
    $opts = rental_item_options();
    $rows = '';
    for ($n = 0; $n < 5; $n++) {
        $rows .= '<tr><td><select name="item_id[]">' . $opts . '</select></td><td><input name="qty[]" value="0"></td></tr>';
    }
    layout('New booking', admin_nav() . '<h1>New rental booking ' . brand_badge(GD_NAME) . '</h1>
      <div class="card"><form method="post">' . csrf_field() . '
      ' . field('Customer name', '<input name="customer_name" required>') . '
      ' . field('Customer phone', '<input name="customer_phone" required>') . '
      <div class="row2">' . field('Event date', '<input type="date" name="event_date" required>') . field('Expected return', '<input type="date" name="return_expected" required>') . '</div>
      ' . field('Fulfilment', '<select name="fulfilment_method"><option>Customer Pickup</option><option>Delivery</option></select>') . '
      ' . field('Delivery address', '<textarea name="delivery_address" rows="2"></textarea>') . '
      <table class="tbl"><thead><tr><th>Equipment</th><th>Qty</th></tr></thead><tbody>' . $rows . '</tbody></table>
      ' . field('Delivery charge', '<input name="delivery_charge" value="0">') . '
      ' . field('Deposit required (MWK — per agreement, no fixed %)', '<input name="deposit_required" value="0">') . '
      <button class="btn">Create booking</button></form></div>');
}

/** Create (or fetch) the invoice for a standalone rental booking. */
function rental_ensure_invoice(int $bookingId, array $u): int
{
    $b = db_one('SELECT * FROM rental_bookings WHERE id = ?', [$bookingId]);
    if (!$b) {
        exit('Unknown booking.');
    }
    guard_company(company_name((int) $b['company_id']));
    $ex = db_one('SELECT id FROM invoices WHERE rental_booking_id = ?', [$bookingId]);
    if ($ex) {
        return (int) $ex['id'];
    }
    $inv = make_invoice((int) $b['company_id'], (int) $b['customer_id'], null,
                        'Rental booking #' . $bookingId, (float) $b['grand_total']);
    db_exec('UPDATE invoices SET rental_booking_id=? WHERE id=?', [$bookingId, $inv]);
    return $inv;
}

// ---------- Counter-sale POS (Creations walk-in) ----------
function pos_item_options(): string
{
    $items = db_all(
        "SELECT id, name, price, stock_qty FROM items WHERE published = 1 AND item_type = 'stock'
         AND business_unit IN ('Creations','Shared') ORDER BY name"
    );
    $o = '<option value="">— pick product —</option>';
    foreach ($items as $i) {
        $o .= '<option value="' . (int) $i['id'] . '">' . e($i['name']) . ' (stock ' . e((string) $i['stock_qty']) . ', ' . money((float) $i['price']) . ')</option>';
    }
    return $o;
}

function pg_admin_pos(): void
{
    $u = require_role(['admin', 'accounts', 'creations_staff']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $companyId = company_id(GC_NAME);
        guard_company(GC_NAME);
        $name = post('customer_name', 'Walk-in') ?: 'Walk-in';
        $phone = post('phone');
        if ($phone !== '') {
            $cid = find_or_create_customer($name, $phone);
        } else {
            $row = db_one("SELECT id FROM customers WHERE name = 'Walk-in' ORDER BY id LIMIT 1");
            if ($row) {
                $cid = (int) $row['id'];
            } else {
                db_exec("INSERT INTO customers (name, phone) VALUES ('Walk-in','')");
                $cid = db_last_id();
            }
        }
        $wh = db_one('SELECT * FROM warehouses WHERE name = ?', ['CREATIONS - SHOP - GC']);
        $itemIds = $_POST['item_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        db()->beginTransaction();
        try {
            db_exec(
                "INSERT INTO sales_orders (company_id, customer_id, status, fulfilment_method, delivery_status, created_by)
                 VALUES (?,?,'Confirmed','Customer Pickup','Not Required',?)",
                [$companyId, $cid, (int) $u['id']]
            );
            $oid = db_last_id();
            $sub = 0;
            $n = 0;
            foreach ($itemIds as $k => $iid) {
                $iid = (int) $iid;
                $q = (float) ($qtys[$k] ?? 0);
                if (!$iid || $q <= 0) {
                    continue;
                }
                $it = db_one('SELECT * FROM items WHERE id = ? FOR UPDATE', [$iid]);
                if (!$it || (float) $it['stock_qty'] < $q) {
                    throw new RuntimeException('Insufficient stock for ' . ($it['name'] ?? "#$iid") . '.');
                }
                $amt = $q * (float) $it['price'];
                $sub += $amt;
                $n++;
                db_exec('INSERT INTO sales_order_items (order_id, item_id, qty, rate, amount) VALUES (?,?,?,?,?)',
                    [$oid, $iid, $q, $it['price'], $amt]);
                post_stock($iid, (int) $wh['id'], -$q, 'sales_order', $oid, 'Counter sale');
            }
            if ($n === 0) {
                throw new RuntimeException('Add at least one product row.');
            }
            db_exec('UPDATE sales_orders SET subtotal=?, grand_total=? WHERE id=?', [$sub, $sub, $oid]);
            $inv = make_invoice($companyId, $cid, $oid, 'Counter sale #' . $oid, $sub);
            db_exec(
                "INSERT INTO payments (company_id, customer_id, invoice_id, kind, amount, method, reference, payment_date, received_by)
                 VALUES (?,?,?, 'invoice', ?,?,?,?,?)",
                [$companyId, $cid, $inv, $sub, post('method', 'Cash'), post('reference') ?: null, date('Y-m-d'), (int) $u['id']]
            );
            refresh_invoice($inv);
            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            flash($ex->getMessage(), 'err');
            redirect('/admin/pos');
        }
        flash('Sale #' . $oid . ' recorded — MWK ' . money($sub) . ' paid.');
        redirect('/admin/pos');
    }
    $opts = pos_item_options();
    $rows = '';
    for ($n = 0; $n < 5; $n++) {
        $rows .= '<tr><td><select name="item_id[]">' . $opts . '</select></td><td><input name="qty[]" value="0"></td></tr>';
    }
    $today = db_all(
        'SELECT o.id, o.grand_total, k.name AS customer FROM sales_orders o JOIN customers k ON k.id=o.customer_id
         WHERE o.company_id = ? AND DATE(o.created_at) = CURDATE() ORDER BY o.id DESC LIMIT 20',
        [company_id(GC_NAME)]
    );
    $tr = [];
    foreach ($today as $t) {
        $tr[] = ['#' . $t['id'], e($t['customer']), money((float) $t['grand_total'])];
    }
    layout('Counter sale', admin_nav() . '<h1>Counter sale ' . brand_badge(GC_NAME) . '</h1>
      <div class="card"><form method="post">' . csrf_field() . '
      ' . field('Customer (leave Walk-in for anonymous)', '<input name="customer_name" value="Walk-in">') . '
      ' . field('Phone (optional)', '<input name="phone">') . '
      <table class="tbl"><thead><tr><th>Product</th><th>Qty</th></tr></thead><tbody>' . $rows . '</tbody></table>
      <div class="row2">' . field('Method', '<select name="method"><option>Cash</option><option>Bank Transfer</option><option>Mobile Money</option><option>Other Manual</option></select>') . field('Reference (bank/mobile)', '<input name="reference">') . '</div>
      <button class="btn">Record paid sale</button></form></div>
      <h2>Today</h2>' . ($tr ? table(['#', 'Customer', 'Total'], $tr) : '<p class="mut">No sales today.</p>'));
}

// ---------- Purchasing ----------
function pg_admin_purchases(): void
{
    $u = require_role(['admin', 'accounts', 'creations_staff', 'delights_ops']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_supplier'])) {
        check_csrf();
        if (post('name') === '') {
            flash('Supplier name required.', 'err');
            redirect('/admin/purchases');
        }
        db_exec('INSERT INTO suppliers (name, phone, email) VALUES (?,?,?)',
            [post('name'), post('phone'), post('email')]);
        flash('Supplier added.');
        redirect('/admin/purchases');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_purchase'])) {
        check_csrf();
        $wh = db_one('SELECT * FROM warehouses WHERE id = ? AND is_group = 0', [(int) post('warehouse_id')]);
        if (!$wh) {
            exit('Pick a real warehouse (not a group).');
        }
        guard_company(company_name((int) $wh['company_id']));
        $itemIds = $_POST['item_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $costs = $_POST['cost'] ?? [];
        db()->beginTransaction();
        try {
            db_exec(
                'INSERT INTO purchases (company_id, supplier_id, supplier_ref, created_by) VALUES (?,?,?,?)',
                [(int) $wh['company_id'], (int) post('supplier_id'), post('supplier_ref') ?: null, (int) $u['id']]
            );
            $pid = db_last_id();
            $total = 0;
            $n = 0;
            foreach ($itemIds as $k => $iid) {
                $iid = (int) $iid;
                $q = (float) ($qtys[$k] ?? 0);
                $c = (float) ($costs[$k] ?? 0);
                if (!$iid || $q <= 0) {
                    continue;
                }
                $n++;
                $total += $q * $c;
                db_exec('INSERT INTO purchase_items (purchase_id, item_id, warehouse_id, qty, cost, amount) VALUES (?,?,?,?,?,?)',
                    [$pid, $iid, (int) $wh['id'], $q, $c, $q * $c]);
                post_stock($iid, (int) $wh['id'], $q, 'purchase', $pid, 'Goods receipt');
            }
            if ($n === 0) {
                throw new RuntimeException('Add at least one item row.');
            }
            db_exec('UPDATE purchases SET total=? WHERE id=?', [$total, $pid]);
            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            flash($ex->getMessage(), 'err');
            redirect('/admin/purchases');
        }
        flash('Purchase #' . $pid . ' received — stock updated.');
        redirect('/admin/purchases');
    }
    $sups = db_all('SELECT * FROM suppliers ORDER BY name');
    $sopts = '';
    foreach ($sups as $s) {
        $sopts .= '<option value="' . (int) $s['id'] . '">' . e($s['name']) . '</option>';
    }
    $str = [];
    foreach ($sups as $s) {
        $str[] = [e($s['name']), e((string) $s['phone']), e((string) $s['email'])];
    }
    $whs = db_all('SELECT w.*, c.name AS company FROM warehouses w JOIN companies c ON c.id=w.company_id WHERE w.is_group = 0 ORDER BY w.name');
    $whopts = '';
    foreach ($whs as $w) {
        $whopts .= '<option value="' . (int) $w['id'] . '">' . e($w['name']) . '</option>';
    }
    $items = db_all("SELECT id, name FROM items WHERE item_type IN ('stock','rental') ORDER BY name");
    $iopts = '<option value="">— pick item —</option>';
    foreach ($items as $i) {
        $iopts .= '<option value="' . (int) $i['id'] . '">' . e($i['name']) . '</option>';
    }
    $prows = '';
    for ($n = 0; $n < 5; $n++) {
        $prows .= '<tr><td><select name="item_id[]">' . $iopts . '</select></td><td><input name="qty[]" value="0"></td><td><input name="cost[]" value="0"></td></tr>';
    }
    $plist = db_all(
        'SELECT p.*, s.name AS supplier, c.name AS company FROM purchases p
         JOIN suppliers s ON s.id=p.supplier_id JOIN companies c ON c.id=p.company_id
         ORDER BY p.id DESC LIMIT 30'
    );
    $ptr = [];
    foreach ($plist as $p) {
        $ptr[] = [brand_badge($p['company']), '#' . $p['id'], e($p['supplier']), money((float) $p['total']), e($p['created_at'])];
    }
    layout('Purchasing', admin_nav() . '<h1>Purchasing</h1>
      <h2>Receive goods</h2><div class="card"><form method="post">' . csrf_field() . '
      <input type="hidden" name="new_purchase" value="1">
      ' . field('Supplier', '<select name="supplier_id">' . ($sopts ?: '<option value="">— add one below —</option>') . '</select>') . '
      <div class="row2">' . field('Warehouse', '<select name="warehouse_id">' . $whopts . '</select>') . field('Supplier ref', '<input name="supplier_ref">') . '</div>
      <table class="tbl"><thead><tr><th>Item</th><th>Qty</th><th>Unit cost</th></tr></thead><tbody>' . $prows . '</tbody></table>
      <button class="btn">Receive into stock</button></form></div>
      <h2>Recent receipts</h2>' . ($ptr ? table(['Brand', '#', 'Supplier', 'Total', 'When'], $ptr) : '<p class="mut">None.</p>') . '
      <h2>Suppliers</h2>' . ($str ? table(['Name', 'Phone', 'Email'], $str) : '<p class="mut">None yet.</p>') . '
      <div class="card"><form method="post">' . csrf_field() . '<input type="hidden" name="new_supplier" value="1">
      ' . field('Name', '<input name="name" required>') . '
      <div class="row2">' . field('Phone', '<input name="phone">') . field('Email', '<input name="email">') . '</div>
      <button class="btn sec">Add supplier</button></form></div>');
}

// ---------- Password change ----------
function pg_password(): void
{
    $u = require_login();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $fresh = db_one('SELECT * FROM users WHERE id = ?', [(int) $u['id']]);
        if (!$fresh || !password_verify($_POST['current'] ?? '', $fresh['password_hash'])) {
            flash('Current password is wrong.', 'err');
            redirect('/password');
        }
        if (strlen($_POST['newpass'] ?? '') < 8) {
            flash('New password must be 8+ characters.', 'err');
            redirect('/password');
        }
        db_exec('UPDATE users SET password_hash=? WHERE id=?',
            [password_hash($_POST['newpass'], PASSWORD_DEFAULT), (int) $u['id']]);
        flash('Password changed.');
        redirect(in_array($u['role'], STAFF_ROLES, true) ? '/admin' : '/my-glamorous');
    }
    layout('Account', '<h1>Change password</h1><div class="card"><form method="post">' . csrf_field() . '
      ' . field('Current password', '<input type="password" name="current" required>') . '
      ' . field('New password (8+ chars)', '<input type="password" name="newpass" required>') . '
      <button class="btn">Change</button></form></div>');
}

// ---------- Intercompany transfers (SPEC §6) ----------
// Explicit, auditable GC↔GD moves. Silent cross-company Stock Entries stay
// blocked in post_stock(); this is the ONLY legal cross-company path.
function pg_admin_transfers(): void
{
    $u = require_role(['admin', 'accounts']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $from = db_one('SELECT w.*, c.name AS company FROM warehouses w JOIN companies c ON c.id=w.company_id WHERE w.id = ? AND w.is_group = 0', [(int) post('from_warehouse')]);
        $to = db_one('SELECT w.*, c.name AS company FROM warehouses w JOIN companies c ON c.id=w.company_id WHERE w.id = ? AND w.is_group = 0', [(int) post('to_warehouse')]);
        $it = db_one('SELECT * FROM items WHERE id = ?', [(int) post('item_id')]);
        $qty = (float) post('qty', '0');
        $price = (float) post('unit_price', '0');
        if (!$from || !$to || !$it) {
            exit('Pick source, destination and item.');
        }
        if ((int) $from['company_id'] === (int) $to['company_id']) {
            flash('Same-company move — use Items → Adjust instead. Transfers are GC↔GD only.', 'err');
            redirect('/admin/transfers');
        }
        if ($qty <= 0) {
            flash('Quantity must be above zero.', 'err');
            redirect('/admin/transfers');
        }
        $have = warehouse_balance((int) $it['id'], (int) $from['id']);
        if ($have < $qty) {
            flash('Only ' . $have . ' × ' . $it['name'] . ' in ' . $from['name'] . ' (ledger balance).', 'err');
            redirect('/admin/transfers');
        }
        db()->beginTransaction();
        db_exec(
            'INSERT INTO transfers (from_warehouse_id, to_warehouse_id, item_id, qty, unit_price, amount, reference, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [(int) $from['id'], (int) $to['id'], (int) $it['id'], $qty, $price, $qty * $price,
             post('reference') ?: null, post('notes') ?: null, (int) $u['id']]
        );
        $tid = db_last_id();
        // Paired ledger moves. items.stock_qty is intentionally untouched:
        // global on-hand is unchanged, only the owning company differs.
        db_exec(
            'INSERT INTO stock_moves (item_id, warehouse_id, qty_change, ref_type, ref_id, notes, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [(int) $it['id'], (int) $from['id'], -$qty, 'transfer', $tid, 'Intercompany out', (int) $u['id']]
        );
        db_exec(
            'INSERT INTO stock_moves (item_id, warehouse_id, qty_change, ref_type, ref_id, notes, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [(int) $it['id'], (int) $to['id'], $qty, 'transfer', $tid, 'Intercompany in', (int) $u['id']]
        );
        db()->commit();
        flash('Transfer #' . $tid . ' recorded: ' . $qty . ' × ' . $it['name'] . ' (' . $from['company'] . ' → ' . $to['company'] . ').');
        redirect('/admin/transfers');
    }
    $whs = db_all('SELECT w.id, w.name, c.name AS company FROM warehouses w JOIN companies c ON c.id=w.company_id WHERE w.is_group = 0 ORDER BY w.name');
    $opts = '';
    foreach ($whs as $w) {
        $opts .= '<option value="' . (int) $w['id'] . '">' . e($w['name']) . ' (' . e($w['company']) . ')</option>';
    }
    $items = db_all("SELECT id, name FROM items WHERE item_type IN ('stock','rental') ORDER BY name");
    $iopts = '';
    foreach ($items as $i) {
        $iopts .= '<option value="' . (int) $i['id'] . '">' . e($i['name']) . '</option>';
    }
    $rows = db_all(
        'SELECT t.*, f.name AS fw, c1.name AS fc, w.name AS tw, c2.name AS tc, i.name AS item
         FROM transfers t JOIN warehouses f ON f.id=t.from_warehouse_id JOIN companies c1 ON c1.id=f.company_id
         JOIN warehouses w ON w.id=t.to_warehouse_id JOIN companies c2 ON c2.id=w.company_id
         JOIN items i ON i.id=t.item_id ORDER BY t.id DESC LIMIT 50'
    );
    $tr = [];
    foreach ($rows as $r) {
        $tr[] = ['#' . $r['id'], e($r['item']) . ' × ' . e((string) $r['qty']),
                 brand_badge($r['fc']) . ' ' . e($r['fw']) . ' → ' . brand_badge($r['tc']) . ' ' . e($r['tw']),
                 money((float) $r['amount']), e((string) ($r['reference'] ?? ''))];
    }
    layout('Transfers', admin_nav() . '<h1>Intercompany transfers</h1>
      <p class="mut">Explicit GC↔GD moves with a paper trail. Same-company moves belong in Items → Adjust.</p>
      <div class="card"><form method="post">' . csrf_field() . '
      <div class="row2">' . field('From warehouse', '<select name="from_warehouse">' . $opts . '</select>') . field('To warehouse', '<select name="to_warehouse">' . $opts . '</select>') . '</div>
      <div class="row2">' . field('Item', '<select name="item_id">' . $iopts . '</select>') . field('Qty', '<input name="qty" required>') . '</div>
      <div class="row2">' . field('Transfer price (per unit)', '<input name="unit_price" value="0">') . field('Reference', '<input name="reference">') . '</div>
      ' . field('Notes', '<input name="notes">') . '
      <button class="btn">Record transfer</button></form></div>
      <h2>History</h2>' . ($tr ? table(['#', 'Item', 'From → To', 'Amount', 'Ref'], $tr) : '<p class="mut">No transfers yet.</p>'));
}
