<?php
// Shared domain logic: stock ledger, rental availability, deposits, invoicing.
declare(strict_types=1);

// Pending bookings hold stock too (released on Cancelled); otherwise two
// overlapping quotations could sell the same chairs twice (SPEC T5).
const RENTAL_ACTIVE = ['Quoted', 'Deposit Pending', 'Confirmed', 'Dispatched', 'At Customer', 'Return Due'];

/** Adjust cached stock + append ledger row. Throws on cross-company move. */
function post_stock(int $itemId, int $warehouseId, float $qtyChange, ?string $refType, ?int $refId, ?string $notes = null): void
{
    $item = db_one('SELECT * FROM items WHERE id = ?', [$itemId]);
    $wh = db_one('SELECT * FROM warehouses WHERE id = ?', [$warehouseId]);
    if (!$item || !$wh) {
        throw new RuntimeException('Unknown item or warehouse.');
    }
    if ($item['company_id'] && (int) $item['company_id'] !== (int) $wh['company_id']) {
        throw new RuntimeException('Cross-company stock move blocked. Use the intercompany SOP (sell GC → buy GD).');
    }
    db_exec(
        'INSERT INTO stock_moves (item_id, warehouse_id, qty_change, ref_type, ref_id, notes, created_by)
         VALUES (?,?,?,?,?,?,?)',
        [$itemId, $warehouseId, $qtyChange, $refType, $refId, $notes, current_user()['id'] ?? null]
    );
    db_exec('UPDATE items SET stock_qty = stock_qty + ? WHERE id = ?', [$qtyChange, $itemId]);
}

/**
 * Rental availability: on_hand - overlapping confirmed qty (same item).
 * Overlap = [event_date, return_expected] intersects another active booking.
 */
function rental_available(int $itemId, string $from, string $to, ?int $excludeBookingId = null): float
{
    $item = db_one('SELECT stock_qty FROM items WHERE id = ?', [$itemId]);
    if (!$item) {
        return 0;
    }
    $params = [$itemId, $to, $from];
    $exclude = '';
    if ($excludeBookingId) {
        $exclude = 'AND b.id != ?';
        $params[] = $excludeBookingId;
    }
    $in = "'" . implode("','", RENTAL_ACTIVE) . "'";
    $row = db_one(
        "SELECT COALESCE(SUM(i.qty),0) AS reserved
         FROM rental_bookings b JOIN rental_booking_items i ON i.booking_id = b.id
         WHERE i.item_id = ? AND b.status IN ($in)
           AND b.event_date <= ? AND b.return_expected >= ? $exclude",
        $params
    );
    return max(0, (float) $item['stock_qty'] - (float) ($row['reserved'] ?? 0));
}

/** Pure deposit settlement (SPEC T4). Never posts payments — caller does. */
function settle_deposit(float $received, float $damage = 0, float $missing = 0, float $cleaning = 0, float $forfeited = 0): array
{
    $applied = $damage + $missing + $cleaning + $forfeited;
    return ['applied' => $applied, 'refund_due' => max(0, $received - $applied)];
}

function refresh_invoice(int $invoiceId): void
{
    $inv = db_one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
    if (!$inv) {
        return;
    }
    $paid = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE invoice_id = ?', [$invoiceId])['s'] ?? 0);
    $status = $paid <= 0 ? 'Unpaid' : ($paid < (float) $inv['total'] ? 'Partial' : 'Paid');
    db_exec('UPDATE invoices SET paid = ?, status = ? WHERE id = ?', [$paid, $status, $invoiceId]);
}

/** Create invoice for an order/event total. Returns invoice id. */
function make_invoice(int $companyId, int $customerId, ?int $orderId, string $label, float $total): int
{
    db_exec(
        'INSERT INTO invoices (company_id, customer_id, order_id, label, total) VALUES (?,?,?,?,?)',
        [$companyId, $customerId, $orderId, $label, $total]
    );
    return db_last_id();
}

/** Find or create customer by phone (shared identity, SPEC §5). */
function find_or_create_customer(string $name, string $phone, string $email = '', string $whatsapp = ''): int
{
    $phone = trim($phone);
    if ($phone !== '') {
        $row = db_one('SELECT id FROM customers WHERE phone = ? ORDER BY id LIMIT 1', [$phone]);
        if ($row) {
            return (int) $row['id'];
        }
    }
    if ($email !== '') {
        $row = db_one('SELECT id FROM customers WHERE email = ? AND email != "" ORDER BY id LIMIT 1', [$email]);
        if ($row) {
            return (int) $row['id'];
        }
    }
    db_exec(
        'INSERT INTO customers (name, phone, whatsapp, email) VALUES (?,?,?,?)',
        [$name, $phone, $whatsapp, $email]
    );
    return db_last_id();
}
