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
        $pname = db_one('SELECT name FROM items WHERE id = ?', [(int) $k['product_item_id']]);
        db_exec('INSERT INTO sales_order_items (order_id, item_id, description, qty, rate, amount) VALUES (?,?,?,?,?,?)',
            [$oid, (int) $k['product_item_id'], $pname ? $pname['name'] : 'Cake', (float) $k['quantity'], $rate, (float) $k['price']]);
        $inv = make_invoice((int) $k['company_id'], (int) $k['customer_id'], $oid, 'Cake order #' . $k['id'], (float) $k['price']);
        db_exec('UPDATE invoices SET cake_order_id=? WHERE id=?', [(int) $k['id'], $inv]);
        db_exec("UPDATE cake_orders SET status='Confirmed' WHERE id=?", [(int) $k['id']]);
        db()->commit();
        flash('Confirmed: order #' . $oid . ', invoice #' . $inv . '.');
        redirect('/admin/bakery');
    }
    // Advance production one step.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prod'], $_POST['to'])) {
        check_csrf();
        $k = db_one('SELECT * FROM cake_orders WHERE id = ?', [(int) $_POST['prod']]);
        $to = $_POST['to'];
        $i = array_search($k['production_status'] ?? '', $flow, true);
        if ($k && $i !== false && ($flow[$i + 1] ?? null) === $to) {
            guard_company(company_name((int) $k['company_id']));
            db_exec('UPDATE cake_orders SET production_status=?, status=? WHERE id=?',
                [$to, $to === 'Delivered' ? 'Delivered' : $k['status'], (int) $k['id']]);
            flash('Cake #' . $k['id'] . ' → ' . $to . '.');
        }
        redirect('/admin/bakery');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_cake'])) {
        check_csrf();
        db_exec("UPDATE cake_orders SET status='Completed' WHERE id=?", [(int) $_POST['complete_cake']]);
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
            $acts .= ' <form method="post" style="display:inline">' . csrf_field() . '
              <input type="hidden" name="prod" value="' . (int) $r['id'] . '">
              <input type="hidden" name="to" value="' . $flow[$i + 1] . '">
              <button class="btn sec">→ ' . $flow[$i + 1] . '</button></form>';
        }
        if ($r['status'] === 'Delivered') {
            $acts .= ' <form method="post" style="display:inline">' . csrf_field() . '
              <input type="hidden" name="complete_cake" value="' . (int) $r['id'] . '">
              <button class="btn sec">Complete</button></form>';
        }
        $tr[] = [brand_badge($r['company']), '#' . $r['id'] . ' ' . e($r['order_type']) . ': ' . e($r['product']),
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['catering_set'], $_POST['catering_to'])) {
        check_csrf();
        $c = db_one('SELECT * FROM catering_orders WHERE id = ?', [(int) $_POST['catering_set']]);
        if ($c && ($flow[$c['status']] ?? null) === $_POST['catering_to']) {
            guard_company(company_name((int) $c['company_id']));
            db_exec('UPDATE catering_orders SET status=? WHERE id=?', [$_POST['catering_to'], (int) $c['id']]);
            flash('Catering #' . $c['id'] . ' → ' . $_POST['catering_to'] . '.');
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
            ? ' <form method="post" style="display:inline">' . csrf_field() . '
               <input type="hidden" name="catering_set" value="' . (int) $r['id'] . '">
               <input type="hidden" name="catering_to" value="' . $flow[$r['status']] . '">
               <button class="btn sec">→ ' . $flow[$r['status']] . '</button></form>' : '';
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

// ---------- Counter-sale register (Creations walk-in) ----------
// Layout & flow borrow heavily from OpenSourcePOS 3.4 (MIT licence):
// find/scan bar with suggestions, editable cart lines with line discounts,
// customer attach, tendered/change, suspend-resume, keyboard shortcuts.
// Rebuilt here dependency-free (no jQuery/Bootstrap) in the Glamorous theme.
const POS_ROLES = ['admin', 'accounts', 'creations_staff'];
const POS_ROLES_BAKERY = ['admin', 'accounts', 'delights_sales', 'delights_ops'];

/** Register till config: Creations shop vs Delights bakery counter. */
function pos_cfg(string $ctx): array
{
    if ($ctx === 'gd') {
        return [
            'company' => GD_NAME,
            'warehouse' => 'DELIGHTS - BAKERY - GD',
            'roles' => POS_ROLES_BAKERY,
            'item_where' => "published = 1 AND item_type IN ('stock','service') AND business_unit IN ('Delights','Shared')",
            'base' => '/admin/bakery-pos',
            'title' => 'Bakery counter',
            'invoice_label' => 'Bakery sale #',
        ];
    }
    return [
        'company' => GC_NAME,
        'warehouse' => 'CREATIONS - SHOP - GC',
        'roles' => POS_ROLES,
        'item_where' => "published = 1 AND item_type = 'stock' AND business_unit IN ('Creations','Shared')",
        'base' => '/admin/pos',
        'title' => 'Counter sale',
        'invoice_label' => 'Counter sale #',
    ];
}

/** Which till is this request for? Bakery POS routes carry the gd context. */
function pos_ctx(): string
{
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    return str_starts_with($p, '/admin/bakery-pos') ? 'gd' : 'gc';
}

/** POS request guard: returns [ctx, cfg, user]. */
function pos_guard(): array
{
    $ctx = pos_ctx();
    $cfg = pos_cfg($ctx);
    $u = require_role($cfg['roles']);
    guard_company($cfg['company']);
    return [$ctx, $cfg, $u];
}

function pos_base(): string
{
    return pos_cfg(pos_ctx())['base'];
}

function pos_cart(?string $ctx = null): array
{
    $ctx ??= pos_ctx();
    $k = 'pos_cart_' . $ctx;
    if (!isset($_SESSION[$k]) && $ctx === 'gc' && isset($_SESSION['pos_cart'])) {
        $_SESSION[$k] = $_SESSION['pos_cart'];
        unset($_SESSION['pos_cart']);
    }
    return $_SESSION[$k] ?? [];
}

function pos_save_cart(array $c, ?string $ctx = null): void
{
    $ctx ??= pos_ctx();
    $_SESSION['pos_cart_' . $ctx] = $c;
}

function pos_customer(?string $ctx = null): array
{
    $ctx ??= pos_ctx();
    $k = 'pos_customer_' . $ctx;
    if (!isset($_SESSION[$k]) && $ctx === 'gc' && isset($_SESSION['pos_customer'])) {
        $_SESSION[$k] = $_SESSION['pos_customer'];
        unset($_SESSION['pos_customer']);
    }
    return $_SESSION[$k] ?? ['name' => 'Walk-in', 'phone' => ''];
}

function pos_clear(?string $ctx = null): void
{
    $ctx ??= pos_ctx();
    unset($_SESSION['pos_cart_' . $ctx], $_SESSION['pos_customer_' . $ctx]);
    if ($ctx === 'gc') {
    pos_clear($ctx);
    }
}

function pos_totals(array $cart): array
{
    $lines = [];
    $sub = 0;
    $disc = 0;
    $units = 0;
    foreach ($cart as $id => $l) {
        $it = db_one('SELECT id, sku, name, price, stock_qty FROM items WHERE id = ?', [(int) $id]);
        if (!$it) {
            continue;
        }
        $qty = max(0, (float) ($l['qty'] ?? 0));
        if ($qty <= 0) {
            continue;
        }
        $dpct = min(100, max(0, (float) ($l['discount'] ?? 0)));
        $gross = $qty * (float) $it['price'];
        $d = $gross * $dpct / 100;
        $sub += $gross;
        $disc += $d;
        $units += $qty;
        $lines[] = ['id' => (int) $id, 'sku' => $it['sku'], 'name' => $it['name'],
                    'price' => (float) $it['price'], 'stock' => (float) $it['stock_qty'],
                    'qty' => $qty, 'discount' => $dpct, 'amount' => $gross - $d];
    }
    return ['lines' => $lines, 'units' => $units, 'subtotal' => $sub, 'discount' => $disc, 'total' => $sub - $disc];
}

function pg_admin_pos(): void
{
    [$ctx, $cfg] = pos_guard();
    $cart = pos_cart($ctx);
    $cust = pos_customer($ctx);
    $B = $cfg['base'];
    $t = pos_totals($cart);
    $suspended = db_all(
        'SELECT s.*, u.name AS staff FROM suspended_sales s LEFT JOIN users u ON u.id = s.staff_id WHERE s.context = ? ORDER BY s.id DESC LIMIT 10',
        [$ctx]
    );
    $susHtml = '';
    foreach ($suspended as $s) {
        $susHtml .= '<li>#' . (int) $s['id'] . ' ' . e((string) ($s['customer_name'] ?: 'Walk-in'))
            . ' <span class="t">' . e((string) ($s['staff'] ?? '')) . ' · ' . e($s['created_at']) . '</span>'
            . ' <form method="post" action="' . $B . '/resume" style="display:inline">' . csrf_field() . '
               <input type="hidden" name="id" value="' . (int) $s['id'] . '"><button class="btn sec">Resume</button></form>'
            . ' <form method="post" action="' . $B . '/suspend-delete" style="display:inline" onsubmit="return confirm(\'Delete this parked sale?\')">' . csrf_field() . '
               <input type="hidden" name="id" value="' . (int) $s['id'] . '"><button class="btn sec">Delete</button></form></li>';
    }
    $rows = '';
    foreach ($t['lines'] as $l) {
        $rows .= '<tr><td><form method="post" action="' . $B . '/remove" style="display:inline">' . csrf_field() . '
          <input type="hidden" name="item_id" value="' . (int) $l['id'] . '"><button class="btn sec">×</button></form></td>
          <td>' . e($l['sku']) . '<br><span class="t">stock ' . e((string) $l['stock']) . '</span></td>
          <td>' . e($l['name']) . '</td><td>' . money($l['price']) . '</td>
          <td><form method="post" action="' . $B . '/update">' . csrf_field() . '
          <input type="hidden" name="item_id" value="' . (int) $l['id'] . '">
          <input name="qty" value="' . e((string) $l['qty']) . '" size="4" inputmode="decimal"></td>
          <td><input name="discount" value="' . e((string) $l['discount']) . '" size="4" inputmode="decimal" title="% off"></td>
          <td>' . money($l['amount']) . '</td>
          <td><button class="btn sec">Update</button></form></td></tr>';
    }
    $today = db_all(
        'SELECT o.id, o.grand_total, k.name AS customer FROM sales_orders o JOIN customers k ON k.id=o.customer_id
         WHERE o.company_id = ? AND DATE(o.created_at) = CURDATE() ORDER BY o.id DESC LIMIT 12',
        [company_id($cfg['company'])]
    );
    $tr = [];
    foreach ($today as $x) {
        $tr[] = ['#' . $x['id'], e($x['customer']), money((float) $x['grand_total'])];
    }
    layout('Counter sale', admin_nav() . section_tabs([['/admin/pos', 'Creations'], ['/admin/bakery-pos', 'Bakery']]) . '<h1>' . e($cfg['title']) . ' ' . brand_badge($cfg['company']) . '</h1>
    <div class="pos-grid"><div>
      <div class="card"><h3>Find or scan item <span class="mut small">(Alt+1)</span></h3>
        <input id="pos-search" placeholder="Type SKU or name…" autocomplete="off">
        <div id="pos-suggest"></div>
        <form id="pos-add" method="post" action="/admin/pos/add">' . csrf_field() . '<input type="hidden" name="item_id" id="pos-add-id"></form>
      </div>
      <div class="card"><h3>Cart (' . count($t['lines']) . ' lines)</h3>'
      . ($rows
          ? '<div class="tbl-wrap"><table class="tbl"><thead><tr><th></th><th>SKU</th><th>Item</th><th>Price</th><th>Qty</th><th>% off</th><th>Total</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>
            <p><form method="post" action="' . $B . '/suspend" style="display:inline">' . csrf_field() . '<button class="btn sec">Park sale</button></form>
            <form method="post" action="' . $B . '/cancel" style="display:inline" onsubmit="return confirm(\'Clear this sale?\')">' . csrf_field() . '<button class="btn sec">Cancel sale</button></form></p>'
          : '<p class="mut">No items yet — search above or scan.</p>')
      . '</div>'
      . ($susHtml ? '<div class="card"><h3>Parked sales</h3><ul class="feed">' . $susHtml . '</ul></div>' : '') . '
    </div><div>
      <div class="card"><h3>Customer</h3>
        <form method="post" action="' . $B . '/customer">' . csrf_field() . '
        <div class="row2">' . field('Name', '<input name="name" value="' . e($cust['name']) . '">') . field('Phone', '<input name="phone" value="' . e($cust['phone']) . '" inputmode="tel">') . '</div>
        <button class="btn sec">Attach</button></form></div>
      <div class="card pos-totals"><h3>Totals</h3>
        <p>Items: <strong>' . e((string) $t['units']) . '</strong><br>Subtotal: MWK ' . money($t['subtotal'])
        . '<br>Discount: MWK ' . money($t['discount']) . '</p>
        <p class="pos-grand">MWK ' . money($t['total']) . '</p></div>
      <div class="card"><h3>Take payment</h3>
        <form method="post" action="' . $B . '/complete">' . csrf_field() . '
        ' . field('Method', '<select name="method"><option>Cash</option><option>Bank Transfer</option><option>Mobile Money</option><option>Other Manual</option></select>') . '
        ' . field('Amount tendered (Alt+5)', '<input id="pos-tendered" name="tendered" inputmode="decimal" data-total="' . $t['total'] . '" value="' . $t['total'] . '">') . '
        <p>Change due: <strong id="pos-change">MWK 0.00</strong></p>
        ' . field('Reference (bank/mobile)', '<input name="reference">') . '
        <button class="btn">Complete sale</button></form></div>
    </div></div>
    <h2>Today</h2>' . ($tr ? table(['#', 'Customer', 'Total'], $tr) : '<p class="mut">No sales today.</p>') . '
    <script>
    (function () {
      function esc(s) { s = String(s); var q = String.fromCharCode(34), sq = String.fromCharCode(39); return s.split("&").join("&amp;").split("<").join("&lt;").split(">").join("&gt;").split(q).join("&quot;").split(sq).join("&#39;"); }
      var si = document.getElementById("pos-search"), box = document.getElementById("pos-suggest"), items = [];
      si.addEventListener("input", function () {
        var q = si.value.trim();
        if (q.length < 2) { box.innerHTML = ""; return; }
        fetch("' . $B . '/suggest?q=" + encodeURIComponent(q)).then(function (r) { return r.json(); }).then(function (d) {
          items = d;
          box.innerHTML = d.map(function (it, i) {
            return "<button type=\'button\' data-i=\'" + i + "\'>" + esc(it.sku) + " — " + esc(it.name) + " <b>MWK " + it.price + "</b> (" + it.stock + " in stock)</button>";
          }).join("");
          box.querySelectorAll("button").forEach(function (b) {
            b.onclick = function () {
              document.getElementById("pos-add-id").value = items[+b.dataset.i].id;
              document.getElementById("pos-add").submit();
            };
          });
        });
      });
      si.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && items.length) {
          e.preventDefault();
          document.getElementById("pos-add-id").value = items[0].id;
          document.getElementById("pos-add").submit();
        }
      });
      var ten = document.getElementById("pos-tendered"), chg = document.getElementById("pos-change");
      function upd() {
        var v = parseFloat((ten.value || "0").replace(/,/g, "")) || 0;
        var c = v - parseFloat(ten.dataset.total || "0");
        chg.textContent = "MWK " + (c < 0 ? "0.00" : c.toLocaleString("en-US", {minimumFractionDigits: 2, maximumFractionDigits: 2}));
      }
      if (ten) { ten.addEventListener("input", upd); upd(); }
      document.addEventListener("keydown", function (e) {
        if (e.altKey && e.key === "1") { e.preventDefault(); si.focus(); si.select(); }
        if (e.altKey && e.key === "5") { e.preventDefault(); if (ten) { ten.focus(); ten.select(); } }
      });
    })();
    </script>');
}

function pg_pos_suggest(): void
{
    [$ctx, $cfg] = pos_guard();
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    header('Content-Type: application/json');
    if (strlen(trim($_GET['q'] ?? '')) < 2) {
        echo '[]';
        exit;
    }
    $rows = db_all(
        "SELECT id, sku, name, price, stock_qty AS stock FROM items
         WHERE " . $cfg['item_where'] . "
           AND (sku LIKE ? OR name LIKE ?) ORDER BY name LIMIT 8", [$q, $q]
    );
    echo json_encode($rows);
    exit;
}

function pg_pos_add(): void
{
    [$ctx, $cfg] = pos_guard();
    check_csrf();
    $id = 0;
    if (!empty($_POST['item_id'])) {
        $id = (int) $_POST['item_id'];
    } elseif (!empty($_POST['sku'])) {
        $row = db_one('SELECT id FROM items WHERE sku = ? AND published = 1', [trim($_POST['sku'])]);
        $id = $row ? (int) $row['id'] : 0;
    }
    if ($id) {
        $it = db_one("SELECT id FROM items WHERE id = ? AND " . $cfg['item_where'], [$id]);
        if ($it) {
            $cart = pos_cart($ctx);
            $cart[$id] = ['qty' => (float) ($cart[$id]['qty'] ?? 0) + 1, 'discount' => (float) ($cart[$id]['discount'] ?? 0)];
            pos_save_cart($cart, $ctx);
        } else {
            flash('Item is not sellable here.', 'err');
        }
    }
    redirect(pos_base());
}

function pg_pos_update(): void
{
    [$ctx] = pos_guard();
    check_csrf();
    $id = (int) ($_POST['item_id'] ?? 0);
    $cart = pos_cart($ctx);
    if ($id && isset($cart[$id])) {
        $qty = max(0, (float) ($_POST['qty'] ?? 0));
        if ($qty <= 0) {
            unset($cart[$id]);
        } else {
            $cart[$id] = ['qty' => $qty, 'discount' => min(100, max(0, (float) ($_POST['discount'] ?? 0)))];
        }
        pos_save_cart($cart, $ctx);
    }
    redirect(pos_base());
}

function pg_pos_remove(): void
{
    [$ctx] = pos_guard();
    check_csrf();
    $cart = pos_cart($ctx);
    unset($cart[(int) ($_POST['item_id'] ?? 0)]);
    pos_save_cart($cart, $ctx);
    redirect(pos_base());
}

function pg_pos_customer(): void
{
    [$ctx] = pos_guard();
    check_csrf();
    $_SESSION['pos_customer_' . $ctx] = ['name' => post('name', 'Walk-in') ?: 'Walk-in', 'phone' => post('phone')];
    redirect(pos_base());
}

function pg_pos_suspend(): void
{
    [$ctx, $cfg, $u] = pos_guard();
    check_csrf();
    $cart = pos_cart($ctx);
    if (!$cart) {
        flash('Nothing to park.', 'err');
        redirect(pos_base());
    }
    $cust = pos_customer($ctx);
    db_exec('INSERT INTO suspended_sales (staff_id, customer_name, customer_phone, context, payload) VALUES (?,?,?,?,?)',
        [(int) $u['id'], $cust['name'], $cust['phone'], $ctx, json_encode($cart)]);
    pos_clear($ctx);
    flash('Sale parked (#' . db_last_id() . ').');
    redirect(pos_base());
}

function pg_pos_resume(): void
{
    [$ctx] = pos_guard();
    check_csrf();
    $s = db_one('SELECT * FROM suspended_sales WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if (!$s) {
        redirect(pos_base());
    }
    if (($s['context'] ?? 'gc') !== $ctx) {
        flash('That parked sale belongs to the other till.', 'err');
        redirect(pos_base());
    }
    $_SESSION['pos_cart_' . $ctx] = json_decode($s['payload'], true) ?: [];
    $_SESSION['pos_customer_' . $ctx] = ['name' => (string) ($s['customer_name'] ?: 'Walk-in'), 'phone' => (string) ($s['customer_phone'] ?? '')];
    db_exec('DELETE FROM suspended_sales WHERE id = ?', [(int) $s['id']]);
    flash('Parked sale resumed.');
    redirect(pos_base());
}

function pg_pos_suspend_delete(): void
{
    pos_guard();
    check_csrf();
    db_exec('DELETE FROM suspended_sales WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    redirect(pos_base());
}

function pg_pos_cancel(): void
{
    [$ctx] = pos_guard();
    check_csrf();
    pos_clear($ctx);
    flash('Sale cleared.');
    redirect(pos_base());
}

function pg_pos_complete(): void
{
    [$ctx, $cfg, $u] = pos_guard();
    check_csrf();
    $companyId = company_id($cfg['company']);
    $cart = pos_cart($ctx);
    $t = pos_totals($cart);
    if (!$t['lines']) {
        flash('Cart is empty.', 'err');
        redirect(pos_base());
    }
    $tendered = (float) post('tendered', '0');
    if ($tendered < $t['total']) {
        flash('Tendered MWK ' . money($tendered) . ' is less than the MWK ' . money($t['total']) . ' total.', 'err');
        redirect(pos_base());
    }
    $change = $tendered - $t['total'];
    $cust = pos_customer($ctx);
    $cid = $cust['phone'] !== ''
        ? find_or_create_customer($cust['name'], $cust['phone'])
        : walkin_customer_id();
    $wh = db_one('SELECT * FROM warehouses WHERE name = ?', [$cfg['warehouse']]);
    db()->beginTransaction();
    try {
        db_exec(
            "INSERT INTO sales_orders (company_id, customer_id, status, fulfilment_method, delivery_status, subtotal, discount_total, grand_total, tendered, change_due, created_by)
             VALUES (?,?, 'Confirmed','Customer Pickup','Not Required', ?,?,?,?,?,?)",
            [$companyId, $cid, $t['subtotal'], $t['discount'], $t['total'], $tendered, $change, (int) $u['id']]
        );
        $oid = db_last_id();
        foreach ($t['lines'] as $l) {
            $it = db_one('SELECT * FROM items WHERE id = ? FOR UPDATE', [$l['id']]);
            if (!$it) {
                throw new RuntimeException('Unknown item #' . $l['id'] . '.');
            }
            // Services (fresh bakery etc.) are produced to order — no stock check.
            if ($it['item_type'] === 'stock' && (float) $it['stock_qty'] < $l['qty']) {
                throw new RuntimeException('Insufficient stock for ' . $it['name'] . '.');
            }
            db_exec('INSERT INTO sales_order_items (order_id, item_id, description, qty, rate, discount_pct, amount) VALUES (?,?,?,?,?,?,?)',
                [$oid, $l['id'], $l['name'], $l['qty'], $l['price'], $l['discount'], $l['amount']]);
            if ($it['item_type'] === 'stock') {
                post_stock($l['id'], (int) $wh['id'], -$l['qty'], 'sales_order', $oid, 'Counter sale');
            }
        }
            $inv = make_invoice($companyId, $cid, $oid, $cfg['invoice_label'] . $oid, $t['total']);
        db_exec(
            "INSERT INTO payments (company_id, customer_id, invoice_id, kind, amount, method, reference, payment_date, received_by)
             VALUES (?,?,?, 'invoice', ?,?,?,?,?)",
            [$companyId, $cid, $inv, $t['total'], post('method', 'Cash'), post('reference') ?: null, date('Y-m-d'), (int) $u['id']]
        );
        refresh_invoice($inv);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        flash($ex->getMessage(), 'err');
        redirect(pos_base());
    }
    unset($_SESSION['pos_cart'], $_SESSION['pos_customer']);
    flash('Sale #' . $oid . ' complete — invoice #' . $inv . '. Change due: MWK ' . money($change) . '.');
    redirect('/invoice/' . $inv);
}

function walkin_customer_id(): int
{
    $row = db_one("SELECT id FROM customers WHERE name = 'Walk-in' ORDER BY id LIMIT 1");
    if ($row) {
        return (int) $row['id'];
    }
    db_exec("INSERT INTO customers (name, phone) VALUES ('Walk-in','')");
    return db_last_id();
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
    layout('Purchasing', admin_nav() . section_tabs([['/admin/warehouse', 'Warehouse'], ['/admin/transfers', 'Transfers'], ['/admin/purchases', 'Purchasing']]) . '<h1>Purchasing</h1>
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
    layout('Transfers', admin_nav() . section_tabs([['/admin/warehouse', 'Warehouse'], ['/admin/transfers', 'Transfers'], ['/admin/purchases', 'Purchasing']]) . '<h1>Intercompany transfers</h1>
      <p class="mut">Explicit GC↔GD moves with a paper trail. Same-company moves belong in Items → Adjust.</p>
      <div class="card"><form method="post">' . csrf_field() . '
      <div class="row2">' . field('From warehouse', '<select name="from_warehouse">' . $opts . '</select>') . field('To warehouse', '<select name="to_warehouse">' . $opts . '</select>') . '</div>
      <div class="row2">' . field('Item', '<select name="item_id">' . $iopts . '</select>') . field('Qty', '<input name="qty" required>') . '</div>
      <div class="row2">' . field('Transfer price (per unit)', '<input name="unit_price" value="0">') . field('Reference', '<input name="reference">') . '</div>
      ' . field('Notes', '<input name="notes">') . '
      <button class="btn">Record transfer</button></form></div>
      <h2>History</h2>' . ($tr ? table(['#', 'Item', 'From → To', 'Amount', 'Ref'], $tr) : '<p class="mut">No transfers yet.</p>'));
}

// ---------- Invoice list (who owes what) ----------
function pg_admin_invoices(): void
{
    $u = require_staff();
    $conds = [];
    $params = [];
    // Single-company roles see only their company's invoices.
    if (($u['role'] === 'creations_staff')) {
        $conds[] = 'c.abbr = ?';
        $params[] = 'GC';
    } elseif (in_array($u['role'], ['delights_sales', 'delights_ops'], true)) {
        $conds[] = 'c.abbr = ?';
        $params[] = 'GD';
    }
    $f = get_param('f');
    if ($f === 'unpaid') {
        $conds[] = '(i.total - i.paid) > 0';
    }
    $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
    $rows = db_all(
        "SELECT i.*, c.name AS company, k.name AS customer FROM invoices i
         JOIN companies c ON c.id=i.company_id JOIN customers k ON k.id=i.customer_id
         $where ORDER BY i.id DESC LIMIT 100", $params
    );
    $tr = [];
    foreach ($rows as $r) {
        $bal = (float) $r['total'] - (float) $r['paid'];
        $tr[] = [brand_badge($r['company']),
                 '<a href="/invoice/' . (int) $r['id'] . '" target="_blank">#' . (int) $r['id'] . '</a> ' . e($r['label']),
                 e($r['customer']), money((float) $r['total']), money((float) $r['paid']),
                 money($bal), e($r['status'])];
    }
    layout('Invoices', admin_nav() . section_tabs([['/admin/orders', 'Orders'], ['/admin/quotations', 'Quotations'], ['/admin/invoices', 'Invoices']]) . '<h1>Invoices</h1>
      <p class="mut">Filter: <a href="/admin/invoices">all</a> · <a href="/admin/invoices?f=unpaid">unpaid only</a></p>' .
        ($tr ? table(['Brand', '#', 'Customer', 'Total', 'Paid', 'Balance', 'Status'], $tr) : '<p class="mut">No invoices.</p>'));
}

// ---------- Settings (admin only) ----------
function pg_admin_settings(): void
{
    require_role(['admin']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_user'])) {
        check_csrf();
        if (post('name') === '' || post('email') === '' || ($_POST['password'] ?? '') === '') {
            flash('Name, email and password are required.', 'err');
            redirect('/admin/settings');
        }
        if (db_one('SELECT id FROM users WHERE email = ?', [post('email')])) {
            flash('Email already exists.', 'err');
            redirect('/admin/settings');
        }
        db_exec(
            'INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,?)',
            [post('name'), post('email'), post('phone'), password_hash($_POST['password'], PASSWORD_DEFAULT), post('role')]
        );
        flash('User created — ask them to change the password on first login.');
        redirect('/admin/settings');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        setting_set('site_name', post('site_name', 'Glamorous'));
        setting_set('whatsapp', preg_replace('/\D+/', '', post('whatsapp')));
        flash('Settings saved.');
        redirect('/admin/settings');
    }
    $users = db_all('SELECT id, name, email, role, active FROM users ORDER BY id');
    $tr = [];
    foreach ($users as $r) {
        $tr[] = ['#' . $r['id'], e($r['name']), e($r['email']), e($r['role']), $r['active'] ? 'yes' : 'no'];
    }
    layout('Settings', admin_nav() . '<h1>Settings</h1><div class="card"><form method="post">' . csrf_field() . '
      ' . field('Site name', '<input name="site_name" value="' . e(setting_get('site_name', 'Glamorous')) . '">') . '
      ' . field('WhatsApp number (international digits, e.g. 265991234567 — enables the chat button)', '<input name="whatsapp" value="' . e(setting_get('whatsapp', '')) . '" inputmode="numeric">') . '
      <button class="btn">Save</button></form></div>
      <p class="mut">Currency is MWK and fixed at install — changing it later needs accountant review.</p>
      <h2>User accounts</h2>' . table(['#', 'Name', 'Email', 'Role', 'Active'], $tr) . '
      <h3>New staff login</h3><div class="card"><form method="post">' . csrf_field() . '<input type="hidden" name="new_user" value="1">
      ' . field('Name', '<input name="name" required>') . field('Email', '<input type="email" name="email" required>') . '
      ' . field('Phone', '<input name="phone">') . field('Temp password', '<input name="password" required>') . '
      ' . field('Role', '<select name="role"><option value="creations_staff">Creations Staff</option><option value="delights_sales">Delights Sales</option><option value="delights_ops">Delights Ops</option><option value="accounts">Accounts Manager</option><option value="admin">Admin</option></select>') . '
      <button class="btn">Create login</button></form></div>');
}

function pg_admin_users(): void
{
    // User accounts live under Settings now; keep the old route working.
    redirect('/admin/settings');
}

// ---------- All quotations ----------
function pg_admin_quotations(): void
{
    require_role(['admin', 'accounts', 'delights_sales']);
    $f = get_param('f');
    $sql = 'SELECT q.*, v.name AS event, k.name AS customer, c.name AS company FROM quotations q
            JOIN events v ON v.id = q.event_id JOIN customers k ON k.id = q.customer_id
            JOIN companies c ON c.id = q.company_id'
        . ($f !== '' ? ' WHERE q.status = ?' : '') . ' ORDER BY q.id DESC LIMIT 100';
    $rows = $f !== '' ? db_all($sql, [$f]) : db_all($sql);
    $tr = [];
    foreach ($rows as $r) {
        $tr[] = [brand_badge($r['company']),
                 '<a href="/admin/events?view=' . (int) $r['event_id'] . '">#' . (int) $r['id'] . ' ' . e($r['event']) . '</a>',
                 e($r['customer']), money((float) $r['grand_total']), money((float) $r['deposit_required']), e($r['status'])];
    }
    layout('Quotations', admin_nav() . section_tabs([['/admin/orders', 'Orders'], ['/admin/quotations', 'Quotations'], ['/admin/invoices', 'Invoices']]) . '<h1>Quotations</h1>
      <p class="mut">Filter: <a href="/admin/quotations">all</a> · <a href="/admin/quotations?f=Draft">draft</a> · <a href="/admin/quotations?f=Sent">sent</a> · <a href="/admin/quotations?f=Approved">approved</a> · <a href="/admin/quotations?f=Converted">converted</a></p>' .
        ($tr ? table(['Brand', 'Quotation', 'Customer', 'Total', 'Deposit', 'Status'], $tr) : '<p class="mut">No quotations. Create one from an event.</p>'));
}

// ---------- Warehouse balances + internal moves ----------
function pg_admin_warehouse(): void
{
    $u = require_role(['admin', 'accounts', 'creations_staff', 'delights_ops']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $from = db_one('SELECT w.*, c.name AS company FROM warehouses w JOIN companies c ON c.id = w.company_id WHERE w.id = ? AND w.is_group = 0', [(int) post('from_warehouse')]);
        $to = db_one('SELECT w.*, c.name AS company FROM warehouses w JOIN companies c ON c.id = w.company_id WHERE w.id = ? AND w.is_group = 0', [(int) post('to_warehouse')]);
        $it = db_one('SELECT * FROM items WHERE id = ?', [(int) post('item_id')]);
        $qty = (float) post('qty', '0');
        if (!$from || !$to || !$it) {
            exit('Pick source, destination and item.');
        }
        guard_company(company_name((int) $from['company_id']));
        guard_company(company_name((int) $to['company_id']));
        if ((int) $from['company_id'] !== (int) $to['company_id']) {
            flash('Cross-company move — use Transfers instead.', 'err');
            redirect('/admin/warehouse');
        }
        if ((int) $from['id'] === (int) $to['id']) {
            flash('Source and destination are the same.', 'err');
            redirect('/admin/warehouse');
        }
        if ($qty <= 0) {
            flash('Quantity must be above zero.', 'err');
            redirect('/admin/warehouse');
        }
        $have = warehouse_balance((int) $it['id'], (int) $from['id']);
        if ($have < $qty) {
            flash('Only ' . $have . ' × ' . $it['name'] . ' in ' . $from['name'] . '.', 'err');
            redirect('/admin/warehouse');
        }
        db()->beginTransaction();
        db_exec('INSERT INTO stock_moves (item_id, warehouse_id, qty_change, ref_type, notes, created_by) VALUES (?,?,?,?,?,?)',
            [(int) $it['id'], (int) $from['id'], -$qty, 'move', 'Internal move out', (int) $u['id']]);
        db_exec('INSERT INTO stock_moves (item_id, warehouse_id, qty_change, ref_type, notes, created_by) VALUES (?,?,?,?,?,?)',
            [(int) $it['id'], (int) $to['id'], $qty, 'move', 'Internal move in', (int) $u['id']]);
        db()->commit();
        flash('Moved ' . $qty . ' × ' . $it['name'] . ' → ' . $to['name'] . '.');
        redirect('/admin/warehouse?w=' . (int) $to['id']);
    }
    $whs = db_all('SELECT w.id, w.name, c.name AS company FROM warehouses w JOIN companies c ON c.id = w.company_id WHERE w.is_group = 0 ORDER BY w.name');
    $w = (int) get_param('w') ?: (int) ($whs[0]['id'] ?? 0);
    $whopts = '';
    foreach ($whs as $x) {
        $whopts .= '<option value="' . (int) $x['id'] . '"' . ($w === (int) $x['id'] ? ' selected' : '') . '>' . e($x['name']) . '</option>';
    }
    $bal = [];
    if ($w) {
        foreach (db_all('SELECT item_id, COALESCE(SUM(qty_change),0) AS b FROM stock_moves WHERE warehouse_id = ? GROUP BY item_id', [$w]) as $r) {
            $bal[(int) $r['item_id']] = (float) $r['b'];
        }
    }
    $items = db_all("SELECT id, sku, name, stock_qty FROM items WHERE item_type IN ('stock','rental') ORDER BY name");
    $tr = [];
    $iopts = '';
    foreach ($items as $i) {
        $b = $bal[(int) $i['id']] ?? 0;
        if ($w && $b == 0 && (float) $i['stock_qty'] == 0) {
            continue;
        }
        $tr[] = [e($i['sku']), e($i['name']), e((string) $b), e((string) $i['stock_qty'])];
        $iopts .= '<option value="' . (int) $i['id'] . '">' . e($i['name']) . '</option>';
    }
    $moves = db_all(
        "SELECT m.*, i.sku, f.name AS fw FROM stock_moves m
         JOIN items i ON i.id = m.item_id JOIN warehouses f ON f.id = m.warehouse_id
         WHERE m.ref_type IN ('move','adjustment') ORDER BY m.id DESC LIMIT 20"
    );
    $mr = [];
    foreach ($moves as $m) {
        $mr[] = [e($m['created_at']), e($m['sku']), e($m['qty_change']), e($m['fw']), e($m['ref_type']) . ' ' . e((string) ($m['notes'] ?? ''))];
    }
    layout('Warehouse', admin_nav() . section_tabs([['/admin/warehouse', 'Warehouse'], ['/admin/transfers', 'Transfers'], ['/admin/purchases', 'Purchasing']]) . '<h1>Warehouse</h1>
      <div class="card"><form method="get" action="/admin/warehouse"><label class="fld"><span>Warehouse</span>
      <select name="w" onchange="this.form.submit()">' . $whopts . '</select></label></form>
      ' . ($tr ? table(['SKU', 'Item', 'Here', 'Global'], $tr) : '<p class="mut">Nothing stocked here.</p>') . '</div>
      <h2>Move stock (same company)</h2><div class="card"><form method="post">' . csrf_field() . '
      <div class="row2">' . field('From', '<select name="from_warehouse">' . $whopts . '</select>') . field('To', '<select name="to_warehouse">' . $whopts . '</select>') . '</div>
      <div class="row2">' . field('Item', '<select name="item_id">' . $iopts . '</select>') . field('Qty', '<input name="qty">') . '</div>
      <button class="btn">Move</button></form></div>
      <h2>Recent moves</h2>' . ($mr ? table(['When', 'SKU', 'Qty', 'Warehouse', 'Ref'], $mr) : '<p class="mut">None.</p>'));
}
