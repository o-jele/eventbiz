<?php
// Public site: home, Creations shop + cart + checkout, Delights request forms, auth.
declare(strict_types=1);

function pg_home(): void
{
    layout('Welcome', '
    <div class="hero">
      <div class="card"><h2>Glamorous Creations</h2>
        <p>Baking supplies, tools &amp; packaging. Buy online, pick up or get delivery.</p>
        <p><a class="btn" href="/creations/shop">Shop supplies</a></p></div>
      <div class="card"><h2>Glamorous Delights</h2>
        <p>Wedding cakes, catering, equipment rental &amp; full events.</p>
        <p><a class="btn" href="/delights">Plan an event</a></p></div>
    </div>');
}

function pg_creations(): void
{
    $items = db_all(
        "SELECT i.* FROM items i WHERE i.published = 1 AND i.business_unit IN ('Creations','Shared')
         AND i.item_type = 'stock' ORDER BY i.featured DESC, i.name LIMIT 24"
    );
    $cards = '';
    foreach ($items as $it) {
        $cards .= '<div class="card"><h3>' . e($it['name']) . '</h3>
          <p class="mut">' . e($it['sku']) . ' · Stock: ' . e((string) $it['stock_qty']) . '</p>
          <p><strong>MWK ' . money((float) $it['price']) . '</strong></p>
          <p><a class="btn" href="/product/' . (int) $it['id'] . '">View</a></p></div>';
    }
    layout('Creations', '<h1>Glamorous Creations</h1><div class="grid">' . ($cards ?: '<p>No products published yet.</p>') . '</div>');
}

function pg_product(int $id): void
{
    $it = db_one('SELECT * FROM items WHERE id = ? AND published = 1', [$id]);
    if (!$it) {
        http_response_code(404);
        exit('Product not found.');
    }
    layout($it['name'], '
    <div class="card"><h1>' . e($it['name']) . '</h1>
    <p class="mut">' . e($it['sku']) . ' · ' . e($it['uom']) . ' · Stock: ' . e((string) $it['stock_qty']) . '</p>
    <p>' . nl2br(e($it['description'] ?? '')) . '</p>
    <p><strong>MWK ' . money((float) $it['price']) . '</strong></p>
    <form method="post" action="/cart/add">' . csrf_field() . '
      <input type="hidden" name="item_id" value="' . (int) $it['id'] . '">
      ' . field('Quantity', '<input name="qty" type="number" min="1" value="1">') . '
      <button class="btn">Add to cart</button></form></div>');
}

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function pg_cart_add(): void
{
    check_csrf();
    $id = (int) ($_POST['item_id'] ?? 0);
    $qty = max(1, (int) ($_POST['qty'] ?? 1));
    $it = db_one('SELECT * FROM items WHERE id = ? AND published = 1', [$id]);
    if (!$it) {
        exit('Unknown product.');
    }
    $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
    flash('Added to cart.');
    redirect('/cart');
}

function pg_cart(): void
{
    $rows = [];
    $total = 0;
    foreach (cart() as $id => $qty) {
        $it = db_one('SELECT * FROM items WHERE id = ?', [$id]);
        if (!$it) {
            continue;
        }
        $amt = $qty * (float) $it['price'];
        $total += $amt;
        $rows[] = [e($it['name']), (int) $qty, money((float) $it['price']), money($amt)];
    }
    $body = '<h1>Cart</h1>' . ($rows ? table(['Item', 'Qty', 'Rate', 'Amount'], $rows)
        . '<p><strong>Total: MWK ' . money($total) . '</strong></p>
           <p><a class="btn" href="/checkout">Checkout</a></p>'
        : '<p>Your cart is empty. <a href="/creations/shop">Keep shopping</a>.</p>');
    layout('Cart', $body);
}

function pg_checkout(): void
{
    $items = cart();
    if (!$items) {
        redirect('/cart');
    }
    $u = current_user();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        // Company MUST come from the route context (SPEC §6) — checkout lives under /creations.
        $companyId = company_id_for_path('/checkout');
        guard_company(GC_NAME);
        $name = $u ? $u['name'] : post('name');
        $phone = $u ? ($u['phone'] ?? post('phone')) : post('phone');
        $email = $u ? $u['email'] : post('email');
        if ($name === '' || $phone === '') {
            flash('Name and phone are required.', 'err');
            redirect('/checkout');
        }
        $customerId = find_or_create_customer($name, $phone, $email);
        $method = post('fulfilment_method', 'Customer Pickup');
        $wh = db_one('SELECT id FROM warehouses WHERE name = ?', ['CREATIONS - SHOP - GC']);
        db()->beginTransaction();
        try {
            db_exec(
                'INSERT INTO sales_orders (company_id, customer_id, status, fulfilment_method, delivery_address,
                 contact_person, contact_phone, preferred_delivery, delivery_notes, delivery_status)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$companyId, $customerId, 'Confirmed', $method, post('delivery_address') ?: null,
                 post('contact_person') ?: null, post('contact_phone') ?: null,
                 post('preferred_delivery') ?: null, post('delivery_notes') ?: null,
                 $method === 'Delivery' ? 'Pending Arrangement' : 'Not Required']
            );
            $orderId = db_last_id();
            $sub = 0;
            foreach ($items as $id => $qty) {
                $it = db_one('SELECT * FROM items WHERE id = ? FOR UPDATE', [$id]);
                if (!$it || (float) $it['stock_qty'] < $qty) {
                    throw new RuntimeException('Insufficient stock for ' . ($it['name'] ?? "#$id") . '.');
                }
                $amt = $qty * (float) $it['price'];
                $sub += $amt;
                db_exec(
                    'INSERT INTO sales_order_items (order_id, item_id, qty, rate, amount) VALUES (?,?,?,?,?)',
                    [$orderId, $id, $qty, $it['price'], $amt]
                );
                post_stock($id, (int) $wh['id'], -$qty, 'sales_order', $orderId, 'Website checkout');
            }
            $dcharge = $method === 'Delivery' ? (float) post('delivery_charge', '0') : 0;
            $grand = $sub + $dcharge;
            db_exec('UPDATE sales_orders SET subtotal=?, delivery_charge=?, grand_total=? WHERE id=?', [$sub, $dcharge, $grand, $orderId]);
            $invId = make_invoice($companyId, $customerId, $orderId, 'Creations web order #' . $orderId, $grand);
            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            flash($ex->getMessage(), 'err');
            redirect('/checkout');
        }
        unset($_SESSION['cart']);
        flash('Order placed! Invoice #' . $invId . ' — pay Cash / Bank / Mobile Money, then declare your payment.');
        redirect($u ? '/my-glamorous' : '/login');
    }
    $name = e($u['name'] ?? '');
    $phone = e($u['phone'] ?? '');
    $email = e($u['email'] ?? '');
    layout('Checkout', '<h1>Checkout ' . brand_badge(GC_NAME) . '</h1>
      <div class="card"><form method="post">' . csrf_field() . '
      ' . field('Name', '<input name="name" required value="' . $name . '">') . '
      ' . field('Phone', '<input name="phone" required value="' . $phone . '">') . '
      ' . field('Email', '<input name="email" value="' . $email . '">') . '
      ' . field('Fulfilment', '<select name="fulfilment_method"><option>Customer Pickup</option><option>Delivery</option></select>') . '
      ' . field('Delivery address (if delivery)', '<textarea name="delivery_address" rows="2"></textarea>') . '
      ' . field('Contact phone (if delivery)', '<input name="contact_phone">') . '
      <button class="btn">Place order</button></form></div>');
}

function pg_delights(): void
{
    layout('Delights', '<h1>Glamorous Delights</h1><div class="grid">
      <div class="card"><h3>Cakes &amp; Fritters</h3><p>Custom wedding cakes, cupcakes, fritters.</p>
        <p><a class="btn" href="/delights/cakes">Request a cake</a></p></div>
      <div class="card"><h3>Catering</h3><p>Weddings, funerals, corporate, parties.</p>
        <p><a class="btn" href="/delights/catering">Request catering</a></p></div>
      <div class="card"><h3>Rentals</h3><p>Chairs, tables, tents, plates, glasses.</p>
        <p><a class="btn" href="/delights/rentals">Browse equipment</a></p></div>
      <div class="card"><h3>Full event</h3><p>One quotation for everything.</p>
        <p><a class="btn" href="/delights/request">Describe your event</a></p></div>
    </div>');
}

function pg_rentals(): void
{
    $items = db_all(
        "SELECT i.* FROM items i WHERE i.published = 1 AND i.item_type = 'rental' ORDER BY i.name"
    );
    $cards = '';
    foreach ($items as $it) {
        $cards .= '<div class="card"><h3>' . e($it['name']) . '</h3>
          <p class="mut">Owned: ' . e((string) $it['stock_qty']) . ' · MWK ' . money((float) $it['price']) . ' per event</p>
          <p><a class="btn" href="/delights/request?type=Rental">Request booking</a></p></div>';
    }
    layout('Rentals', '<h1>Equipment rental</h1><p class="mut">Availability is confirmed by staff; deposit depends on your quotation.</p>
      <div class="grid">' . ($cards ?: '<p>Catalogue coming soon.</p>') . '</div>');
}

/** Generic service request → Customer Enquiry (+ Cake Order for cake type). */
function pg_request(): void
{
    $type = get_param('type', 'General');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        // Company inferred from route (/delights/* → Delights). Never hard-coded per form.
        $companyId = company_id_for_path('/delights/request');
        guard_company(GD_NAME);
        $etype = post('enquiry_type', 'General');
        $name = post('name');
        $phone = post('phone');
        $email = post('email');
        if ($name === '' || ($phone === '' && $email === '')) {
            flash('Name plus phone or email is required.', 'err');
            redirect('/delights/request');
        }
        $u = current_user();
        $customerId = $u && $u['customer_id'] ? (int) $u['customer_id']
            : find_or_create_customer($name, $phone, $email);
        db_exec(
            'INSERT INTO enquiries (company_id, enquiry_type, customer_id, name, phone, email, subject, message, event_date, source)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$companyId, $etype, $customerId, $name, $phone, $email,
             post('subject') ?: ($etype . ' request from website'), post('message'),
             post('event_date') ?: null, 'Website']
        );
        $enqId = db_last_id();
        if ($etype === 'Cake') {
            $prodId = (int) post('product_id');
            db_exec(
                "INSERT INTO cake_orders (company_id, customer_id, order_type, product_item_id, quantity,
                 required_date, customization, reference_image, fulfilment_method, status)
                 VALUES (?,?,?,?,?,?,?,?,?, 'Awaiting Quotation')",
                [$companyId, $customerId, 'Cake', $prodId, (float) post('quantity', '1'),
                 post('event_date') ?: date('Y-m-d'), post('message'), save_upload('reference_image', 'cake-refs'),
                 post('fulfilment_method', 'Customer Pickup')]
            );
        }
        flash('Request received (enquiry #' . $enqId . '). Our team will respond with a quotation.');
        redirect('/delights');
    }
    $cakes = db_all("SELECT id, name FROM items WHERE published = 1 AND item_type IN ('stock','service') AND business_unit = 'Delights' ORDER BY name LIMIT 50");
    $opts = '';
    foreach ($cakes as $c) {
        $opts .= '<option value="' . (int) $c['id'] . '">' . e($c['name']) . '</option>';
    }
    layout('Request', '<h1>Request a service ' . brand_badge(GD_NAME) . '</h1>
      <div class="card"><form method="post" enctype="multipart/form-data">' . csrf_field() . '
      ' . field('Enquiry type', '<select name="enquiry_type"><option>Wedding</option><option>Catering</option><option>Rental</option><option>Cake</option><option>General</option></select>') . '
      ' . field('Your name', '<input name="name" required>') . '
      <div class="row2">' . field('Phone', '<input name="phone">') . field('Email', '<input name="email">') . '</div>
      ' . field('Event date', '<input type="date" name="event_date">') . '
      ' . field('Subject', '<input name="subject">') . '
      ' . field('Details (guests, venue, quantities)', '<textarea name="message" rows="4" required></textarea>') . '
      ' . field('Cake base (only for cake requests)', '<select name="product_id">' . $opts . '</select>') . '
      ' . field('Quantity (cake)', '<input name="quantity" value="1">') . '
      ' . field('Reference image (cake)', '<input type="file" name="reference_image" accept="image/*,.pdf">') . '
      <button class="btn">Send request</button></form></div>');
}

function pg_register(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $name = post('name');
        $email = post('email');
        $phone = post('phone');
        $pass = $_POST['password'] ?? '';
        if ($name === '' || $email === '' || strlen($pass) < 6) {
            flash('Name, email and a 6+ character password are required.', 'err');
            redirect('/register');
        }
        if (db_one('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('That email is already registered. Please log in.', 'err');
            redirect('/login');
        }
        $cid = find_or_create_customer($name, $phone, $email);
        db_exec(
            'INSERT INTO users (name, email, phone, password_hash, role, customer_id) VALUES (?,?,?,?,?,?)',
            [$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), 'customer', $cid]
        );
        login_user(db_one('SELECT * FROM users WHERE id = ?', [db_last_id()]));
        flash('Welcome, ' . $name . '!');
        redirect('/my-glamorous');
    }
    layout('Register', '<h1>Create account</h1><div class="card"><form method="post">' . csrf_field() . '
      ' . field('Name', '<input name="name" required>') . '
      ' . field('Email', '<input type="email" name="email" required>') . '
      ' . field('Phone / WhatsApp', '<input name="phone">') . '
      ' . field('Password (6+ chars)', '<input type="password" name="password" required>') . '
      <button class="btn">Register</button></form></div>');
}

function pg_login(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $u = db_one('SELECT * FROM users WHERE email = ? AND active = 1', [post('email')]);
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            login_user($u);
            flash('Welcome back!');
            redirect(get_param('next', '') !== '' ? post('next', '/my-glamorous') : '/my-glamorous');
        }
        flash('Invalid email or password.', 'err');
        redirect('/login');
    }
    layout('Login', '<h1>Login</h1><div class="card"><form method="post">' . csrf_field() . '
      <input type="hidden" name="next" value="' . e(get_param('next', '/my-glamorous')) . '">
      ' . field('Email', '<input type="email" name="email" required>') . '
      ' . field('Password', '<input type="password" name="password" required>') . '
      <button class="btn">Login</button></form></div>');
}
