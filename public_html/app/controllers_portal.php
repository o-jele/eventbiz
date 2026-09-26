<?php
// Customer dashboard: My Glamorous (both brands, company badge per row).
declare(strict_types=1);

function pg_portal(): void
{
    $u = require_login();
    if (!$u['customer_id']) {
        layout('My Glamorous', '<p>No customer record linked. Contact us.</p>');
        return;
    }
    $cid = (int) $u['customer_id'];
    $orders = db_all(
        'SELECT o.*, c.name AS company FROM sales_orders o JOIN companies c ON c.id=o.company_id
         WHERE o.customer_id = ? ORDER BY o.id DESC LIMIT 20', [$cid]
    );
    $invoices = db_all(
        'SELECT i.*, c.name AS company FROM invoices i JOIN companies c ON c.id=i.company_id
         WHERE i.customer_id = ? ORDER BY i.id DESC LIMIT 20', [$cid]
    );
    $rentals = db_all(
        'SELECT b.*, c.name AS company FROM rental_bookings b JOIN companies c ON c.id=b.company_id
         WHERE b.customer_id = ? ORDER BY b.id DESC LIMIT 20', [$cid]
    );
    $cakes = db_all(
        'SELECT k.*, c.name AS company, i.name AS product FROM cake_orders k
         JOIN companies c ON c.id=k.company_id JOIN items i ON i.id=k.product_item_id
         WHERE k.customer_id = ? ORDER BY k.id DESC LIMIT 20', [$cid]
    );
    $events = db_all(
        'SELECT v.*, c.name AS company FROM events v JOIN companies c ON c.id=v.company_id
         WHERE v.customer_id = ? ORDER BY v.id DESC LIMIT 20', [$cid]
    );
    $decls = db_all(
        'SELECT d.*, c.name AS company FROM payment_declarations d JOIN companies c ON c.id=d.company_id
         WHERE d.customer_id = ? ORDER BY d.id DESC LIMIT 20', [$cid]
    );

    $or = [];
    foreach ($orders as $o) {
        $or[] = [brand_badge($o['company']), '#'.$o['id'], money((float) $o['grand_total']), e($o['status'])];
    }
    $ir = [];
    foreach ($invoices as $i) {
        $bal = (float) $i['total'] - (float) $i['paid'];
        $pay = $bal > 0 ? ' <a href="/declare?invoice_id=' . (int) $i['id'] . '">Declare payment</a>' : '';
        $ir[] = [brand_badge($i['company']), e($i['label']) . ' #' . $i['id'],
                 money((float) $i['total']), money((float) $i['paid']), money($bal) . $pay, e($i['status'])];
    }
    $rr = [];
    foreach ($rentals as $b) {
        $due = (float) $b['deposit_required'] - (float) $b['deposit_received'];
        $dl = ($due > 0 && !in_array($b['status'], ['Returned', 'Completed', 'Cancelled'], true))
            ? ' <a href="/declare?against=rental:' . (int) $b['id'] . '">Pay deposit</a>' : '';
        $rr[] = [brand_badge($b['company']), '#' . $b['id'], e($b['event_date']), money((float) $b['grand_total']),
                 e($b['status']) . '<br><span class="mut">deposit due ' . money(max(0, $due)) . '</span>' . $dl];
    }
    $kr = [];
    foreach ($cakes as $k) {
        $due = (float) $k['deposit_required'] - (float) $k['deposit_received'];
        $dl = ($due > 0 && !in_array($k['status'], ['Completed', 'Cancelled'], true))
            ? ' <a href="/declare?against=cake:' . (int) $k['id'] . '">Pay deposit</a>' : '';
        $kr[] = [brand_badge($k['company']), '#' . $k['id'] . ' ' . e($k['product']), e($k['required_date']),
                 e($k['status']) . '<br><span class="mut">deposit due ' . money(max(0, $due)) . '</span>' . $dl];
    }
    $er = [];
    foreach ($events as $v) {
        $er[] = [brand_badge($v['company']), e($v['name']), e($v['event_date']), e($v['status'])];
    }
    $dr = [];
    foreach ($decls as $d) {
        $dr[] = [brand_badge($d['company']), '#' . $d['id'], money((float) $d['amount']), e($d['method']), e($d['status'])];
    }
    layout('My Glamorous', '<h1>My Glamorous</h1>
      <h2>Orders</h2>' . ($or ? table(['Brand', 'Order', 'Total', 'Status'], $or) : '<p class="mut">None yet.</p>') . '
      <h2>Invoices &amp; payments</h2>' . ($ir ? table(['Brand', 'Invoice', 'Total', 'Paid', 'Balance', 'Status'], $ir) : '<p class="mut">None yet.</p>') . '
      <h2>Rental bookings</h2>' . ($rr ? table(['Brand', 'Booking', 'Event date', 'Total', 'Status'], $rr) : '<p class="mut">None yet.</p>') . '
      <h2>Cake orders</h2>' . ($kr ? table(['Brand', 'Order', 'Required', 'Status'], $kr) : '<p class="mut">None yet.</p>') . '
      <h2>Events</h2>' . ($er ? table(['Brand', 'Event', 'Date', 'Status'], $er) : '<p class="mut">None yet.</p>') . '
      <h2>Payment declarations</h2>' . ($dr ? table(['Brand', 'Declaration', 'Amount', 'Method', 'Status'], $dr) : '<p class="mut">None yet.</p>'));
}

/** Customer declares a manual payment (SPEC §8). Creates NO payment row until staff approve. */
function pg_declare(): void
{
    $u = require_login();
    $against = get_param('against', '');
    if ($against === '' && ($_GET['invoice_id'] ?? '') !== '') {
        $against = 'invoice:' . (int) $_GET['invoice_id']; // backwards compat
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $parts = explode(':', post('against', '')) + [null, null];
        $kind = $parts[0];
        $rid = (int) ($parts[1] ?? 0);
        $companyId = 0;
        $refType = '';
        $refId = 0;
        $invoiceId = null;
        if ($kind === 'invoice') {
            $inv = db_one('SELECT * FROM invoices WHERE id = ?', [$rid]);
            if (!$inv || (int) $inv['customer_id'] !== (int) $u['customer_id']) {
                exit('Unknown invoice.');
            }
            $companyId = (int) $inv['company_id'];
            $order = $inv['order_id'] ? db_one('SELECT * FROM sales_orders WHERE id = ?', [$inv['order_id']]) : null;
            $refType = $order ? 'sales_order' : 'invoice';
            $refId = $order ? (int) $order['id'] : (int) $inv['id'];
            $invoiceId = (int) $inv['id'];
        } elseif ($kind === 'rental') {
            $b = db_one('SELECT * FROM rental_bookings WHERE id = ?', [$rid]);
            if (!$b || (int) $b['customer_id'] !== (int) $u['customer_id']) {
                exit('Unknown booking.');
            }
            $companyId = (int) $b['company_id'];
            $refType = 'rental_booking';
            $refId = (int) $b['id'];
            $link = db_one('SELECT id FROM invoices WHERE rental_booking_id = ?', [$rid]);
            $invoiceId = $link ? (int) $link['id'] : null;
        } elseif ($kind === 'cake') {
            $k = db_one('SELECT * FROM cake_orders WHERE id = ?', [$rid]);
            if (!$k || (int) $k['customer_id'] !== (int) $u['customer_id']) {
                exit('Unknown cake order.');
            }
            $companyId = (int) $k['company_id'];
            $refType = 'cake_order';
            $refId = (int) $k['id'];
            $link = db_one('SELECT id FROM invoices WHERE cake_order_id = ?', [$rid]);
            $invoiceId = $link ? (int) $link['id'] : null;
        } else {
            exit('Pick what this payment is for.');
        }
        db_exec(
            "INSERT INTO payment_declarations (company_id, customer_id, ref_type, ref_id, invoice_id, amount,
             method, reference, payment_date, proof_path, sender_detail, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?, 'Pending Verification', ?)",
            [$companyId, (int) $u['customer_id'], $refType, $refId, $invoiceId,
             (float) post('amount'), post('method'), post('reference'),
             post('payment_date') ?: date('Y-m-d'), save_upload('proof'), post('sender_detail'),
             (int) $u['id']]
        );
        flash('Payment declaration submitted. Staff will verify it shortly.');
        redirect('/my-glamorous');
    }
    $cid = (int) $u['customer_id'];
    $opts = '';
    $invites = db_all(
        'SELECT * FROM invoices WHERE customer_id = ? AND (total - paid) > 0 ORDER BY id DESC', [$cid]
    );
    foreach ($invites as $i) {
        $val = 'invoice:' . (int) $i['id'];
        $sel = $against === $val ? ' selected' : '';
        $opts .= '<option value="' . $val . '"' . $sel . '>Invoice #' . (int) $i['id'] . ' · '
            . e($i['label']) . ' · balance MWK ' . money((float) $i['total'] - (float) $i['paid']) . '</option>';
    }
    $deps = db_all(
        "SELECT id, grand_total, deposit_required, deposit_received FROM rental_bookings
         WHERE customer_id = ? AND (deposit_required - deposit_received) > 0
         AND status NOT IN ('Returned','Completed','Cancelled') ORDER BY id DESC", [$cid]
    );
    foreach ($deps as $d) {
        $val = 'rental:' . (int) $d['id'];
        $sel = $against === $val ? ' selected' : '';
        $opts .= '<option value="' . $val . '"' . $sel . '>Rental booking #' . (int) $d['id']
            . ' · deposit due MWK ' . money((float) $d['deposit_required'] - (float) $d['deposit_received']) . '</option>';
    }
    $cdeps = db_all(
        "SELECT id, price, deposit_required, deposit_received FROM cake_orders
         WHERE customer_id = ? AND (deposit_required - deposit_received) > 0
         AND status NOT IN ('Completed','Cancelled') ORDER BY id DESC", [$cid]
    );
    foreach ($cdeps as $d) {
        $val = 'cake:' . (int) $d['id'];
        $sel = $against === $val ? ' selected' : '';
        $opts .= '<option value="' . $val . '"' . $sel . '>Cake order #' . (int) $d['id']
            . ' · deposit due MWK ' . money((float) $d['deposit_required'] - (float) $d['deposit_received']) . '</option>';
    }
    layout('Declare payment', '<h1>Declare a payment</h1><div class="card"><form method="post" enctype="multipart/form-data">' . csrf_field() . '
      ' . field('Paying for', '<select name="against">' . ($opts ?: '<option value="">— nothing due —</option>') . '</select>') . '
      ' . field('Amount (MWK)', '<input name="amount" required inputmode="decimal">') . '
      ' . field('Method', '<select name="method"><option>Cash</option><option>Bank Transfer</option><option>Mobile Money</option><option>Other Manual</option></select>') . '
      ' . field('Payment reference (bank ref / mobile TxID)', '<input name="reference" required>') . '
      ' . field('Payment date', '<input type="date" name="payment_date" value="' . date('Y-m-d') . '">') . '
      ' . field('Sender account / number (last digits)', '<input name="sender_detail">') . '
      ' . field('Proof (photo / screenshot)', '<input type="file" name="proof" accept="image/*,.pdf">') . '
      <button class="btn">Submit for verification</button></form></div>');
}
