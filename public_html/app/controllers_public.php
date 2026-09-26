<?php
// Public site: home, Creations shop + cart + checkout, Delights request forms, auth.
declare(strict_types=1);

function pg_home(): void
{
    $feat = db_one(
        "SELECT * FROM items WHERE published = 1 AND image_path IS NOT NULL AND image_path != ''
         ORDER BY featured DESC, id LIMIT 1"
    );
    $featHtml = '';
    if ($feat) {
        $featHtml = '<a class="hero-feature" href="/product/' . (int) $feat['id'] . '">'
            . item_img($feat['image_path'], $feat['name'])
            . '<span class="hero-feature-tag">Featured</span>'
            . '<strong>' . e($feat['name']) . '</strong>'
            . '<span class="price">MWK ' . money((float) $feat['price']) . '</span></a>';
    }
    layout('Welcome', '
    <section class="hero-band"><div class="hero-grid"><div>
      <span class="hero-kicker">Lilongwe · Malawi</span>
      <h1>Bakes, cakes &amp; celebrations, beautifully done.</h1>
      <p class="lead">Stock your kitchen at <strong>Glamorous Creations</strong> — or let <strong>Glamorous Delights</strong> handle your wedding, party or corporate event, from cake to catering to chairs.</p>
      <div class="hero-cta">
        <a class="btn" href="/creations/shop">Shop supplies</a>
        <a class="btn dark" href="/delights/request">Plan an event</a>
      </div>
    </div>' . $featHtml . '</div></section>
    <div class="hero">
      <a class="cat-card" href="/creations/shop"><img class="cat-logo" src="/assets/img/logo-creations.jpg" alt="Glamorous Creations"><h3>Glamorous Creations</h3>
        <p>Flour, flavours, tools &amp; packaging for home bakers and businesses. Order online — pickup or arranged delivery.</p>
        <p><strong>Browse the shop →</strong></p></a>
      <a class="cat-card" href="/delights"><img class="cat-logo" src="/assets/img/logo-delights.jpg" alt="Glamorous Delights"><h3>Glamorous Delights</h3>
        <p>Wedding &amp; custom cakes, catering, equipment rental and full event styling. Tell us your date — we send a quotation.</p>
        <p><strong>Explore celebrations →</strong></p></a>
    </div>
    <h2>How it works</h2>
    <div class="steps">
      <div class="step"><h3>Browse or request</h3><p>Shop supplies instantly, or send a cake, catering or rental request with your event date.</p></div>
      <div class="step"><h3>We confirm</h3><p>Pay cash, bank transfer or mobile money. Our team verifies every payment personally.</p></div>
      <div class="step"><h3>Celebrate</h3><p>Pickup or arranged delivery — with our crew on-site for full events.</p></div>
    </div>
    <div class="trust"><span>Pickup available</span><span>Delivery arranged</span><span>Mobile money accepted</span><span>Custom orders welcome</span></div>');
}

function delights_from(string $group): string
{
    $row = db_one(
        'SELECT MIN(i.price) AS m FROM items i JOIN item_groups g ON g.id = i.item_group_id
         WHERE g.name = ? AND i.published = 1 AND i.price > 0', [$group]
    );
    return ($row && (float) $row['m'] > 0) ? 'from MWK ' . money((float) $row['m']) : 'on quotation';
}

function pg_creations(): void
{
    $groups = db_all(
        "SELECT g.id, g.name, COUNT(i.id) AS n FROM item_groups g
         LEFT JOIN items i ON i.item_group_id = g.id AND i.published = 1 AND i.item_type = 'stock'
           AND i.business_unit IN ('Creations','Shared')
         WHERE g.name IN ('Baking Ingredients','Baking Tools & Equipment','Packaging')
         GROUP BY g.id ORDER BY g.name"
    );
    $g = (int) get_param('g');
    $sql = "SELECT i.* FROM items i WHERE i.published = 1 AND i.business_unit IN ('Creations','Shared') AND i.item_type = 'stock'";
    $params = [];
    if ($g) {
        $sql .= ' AND i.item_group_id = ?';
        $params[] = $g;
    }
    $sql .= ' ORDER BY i.featured DESC, i.name LIMIT 48';
    $items = db_all($sql, $params);
    $chips = '<a href="/creations/shop" class="' . ($g ? '' : 'on') . '">Everything</a>';
    foreach ($groups as $gg) {
        $chips .= '<a href="/creations/shop?g=' . (int) $gg['id'] . '" class="' . ($g === (int) $gg['id'] ? 'on' : '') . '">'
            . e($gg['name']) . ' (' . (int) $gg['n'] . ')</a>';
    }
    $cards = '';
    foreach ($items as $it) {
        $stock = (float) $it['stock_qty'];
        $pill = $stock <= 0
            ? '<span class="stock-pill stock-out">Out of stock</span>'
            : (((float) $it['reorder_level'] > 0 && $stock <= (float) $it['reorder_level'])
                ? '<span class="stock-pill stock-low">Low stock</span>'
                : '<span class="stock-pill stock-ok">In stock</span>');
        $cards .= '<div class="card">' . item_img($it['image_path'] ?? null, $it['name'])
            . '<h3>' . e($it['name']) . '</h3>
          <p class="mut">' . e($it['sku']) . '</p>
          <p class="price">MWK ' . money((float) $it['price']) . '</p>
          <p>' . $pill . '</p>
          <p><a class="btn" href="/product/' . (int) $it['id'] . '">View</a></p></div>';
    }
    layout('Creations', '<h1>Baking supplies shop</h1>
      <p class="mut">Everything for home bakers &amp; baking businesses — flour to cake boxes. Pickup or arranged delivery.</p>
      <div class="chips">' . $chips . '</div>
      <div class="grid">' . ($cards ?: '<p>No products in this category yet.</p>') . '</div>');
}

function pg_product(int $id): void
{
    $it = db_one('SELECT * FROM items WHERE id = ? AND published = 1', [$id]);
    if (!$it) {
        http_response_code(404);
        exit('Product not found.');
    }
    $stock = (float) $it['stock_qty'];
    $pill = $stock <= 0
        ? '<span class="stock-pill stock-out">Out of stock</span>'
        : (((float) $it['reorder_level'] > 0 && $stock <= (float) $it['reorder_level'])
            ? '<span class="stock-pill stock-low">Only ' . e((string) $it['stock_qty']) . ' left</span>'
            : '<span class="stock-pill stock-ok">In stock</span>');
    $buy = $stock > 0
        ? '<form method="post" action="/cart/add">' . csrf_field() . '
      <input type="hidden" name="item_id" value="' . (int) $it['id'] . '">
      ' . field('Quantity', '<input name="qty" type="number" min="1" max="' . (int) $stock . '" value="1">') . '
      <button class="btn">Add to cart</button></form>'
        : '<p><span class="stock-pill stock-out">Currently out of stock — check back soon</span></p>';
    layout($it['name'], '
    <div class="product"><div>' . item_img($it['image_path'] ?? null, $it['name']) . '</div><div>
    <h1>' . e($it['name']) . '</h1>
    <p class="mut">' . e($it['sku']) . ' · ' . e($it['uom']) . '</p>
    <p>' . $pill . '</p>
    <p>' . nl2br(e($it['description'] ?? '')) . '</p>
    <p class="price-big">MWK ' . money((float) $it['price']) . '</p>
    ' . $buy . '</div></div>');
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
        $rows[] = [e($it['name']), (int) $qty, money((float) $it['price']), money($amt),
                   '<form method="post" action="/cart/remove" style="display:inline">' . csrf_field() . '
                    <input type="hidden" name="item_id" value="' . (int) $id . '">
                    <button class="btn sec">Remove</button></form>'];
    }
    $body = '<h1>Cart</h1>' . ($rows ? table(['Item', 'Qty', 'Rate', 'Amount', ''], $rows)
        . '<p><strong>Total: MWK ' . money($total) . '</strong></p>
           <p><a class="btn" href="/checkout">Checkout</a>
           <form method="post" action="/cart/clear" style="display:inline">' . csrf_field() . '
           <button class="btn sec">Clear cart</button></form></p>'
        : '<p>Your cart is empty. <a href="/creations/shop">Keep shopping</a>.</p>');
    layout('Cart', $body);
}

function pg_cart_remove(): void
{
    check_csrf();
    unset($_SESSION['cart'][(int) ($_POST['item_id'] ?? 0)]);
    redirect('/cart');
}

function pg_cart_clear(): void
{
    check_csrf();
    unset($_SESSION['cart']);
    redirect('/cart');
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
                    'INSERT INTO sales_order_items (order_id, item_id, description, qty, rate, amount) VALUES (?,?,?,?,?,?)',
                    [$orderId, $id, $it['name'], $qty, $it['price'], $amt]
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
        $_SESSION['last_order'] = ['order' => $orderId, 'invoice' => $invId];
        flash('Order placed! Pay Cash / Bank / Mobile Money, then declare your payment below.');
        redirect('/checkout/success');
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

/** Guest-friendly confirmation: no login required, reads the session receipt. */
function pg_checkout_success(): void
{
    $ids = $_SESSION['last_order'] ?? null;
    if (!$ids) {
        redirect('/creations/shop');
    }
    $o = db_one(
        'SELECT o.*, c.name AS company, k.name AS customer FROM sales_orders o
         JOIN companies c ON c.id=o.company_id JOIN customers k ON k.id=o.customer_id WHERE o.id = ?',
        [(int) $ids['order']]
    );
    $inv = db_one('SELECT * FROM invoices WHERE id = ?', [(int) $ids['invoice']]);
    if (!$o || !$inv) {
        redirect('/creations/shop');
    }
    $u = current_user();
    layout('Order confirmed', '<h1>Order confirmed ' . brand_badge($o['company']) . '</h1>
      <div class="card"><p>Thank you, ' . e($o['customer']) . '!</p>
      <p>Order <strong>#' . (int) $o['id'] . '</strong> · Invoice <strong>#' . (int) $inv['id'] . '</strong><br>
      Total: <strong>MWK ' . money((float) $o['grand_total']) . '</strong> · ' . e($o['fulfilment_method']) . '</p>
      <p>Next: pay by Cash, Bank Transfer or Mobile Money, then
      <a class="btn" href="/declare?invoice_id=' . (int) $inv['id'] . '">declare your payment</a></p>'
      . ($u ? '<p><a href="/my-glamorous">Track it in My Glamorous</a></p>'
            : '<p><a href="/register">Create an account</a> to track orders, or <a href="/login">log in</a>.</p>')
      . '</div>');
}

function pg_delights(): void
{
    layout('Delights', '
    <section class="hero-band">
      <span class="hero-kicker">Glamorous Delights</span>
      <h1>Your celebration, handled with love.</h1>
      <p class="lead">Custom cakes, crowd-pleasing catering, beautiful equipment — or the whole event, end to end. Send your date and guest count; we reply with one clear quotation.</p>
      <div class="hero-cta"><a class="btn" href="/delights/request">Request a quotation</a></div>
    </section>
    <div class="grid">
      <a class="cat-card" href="/delights/cakes"><h3>Cakes &amp; Fritters</h3>
        <p>Wedding tiers, birthday cakes, cupcakes, mandazi &amp; more — designed around your theme.</p>
        <p class="from">' . e(delights_from('Cakes')) . '</p></a>
      <a class="cat-card" href="/delights/catering"><h3>Catering</h3>
        <p>Weddings, funerals, corporate &amp; parties. Menus per head, serving crew included.</p>
        <p class="from">' . e(delights_from('Catering Menus')) . ' per head</p></a>
      <a class="cat-card" href="/delights/rentals"><h3>Rentals</h3>
        <p>Chairs, tables, tents, plates, glasses, chafing dishes — clean, counted &amp; on time.</p>
        <p class="from">' . e(delights_from('Furniture')) . '</p></a>
      <a class="cat-card" href="/delights/request"><h3>Full events</h3>
        <p>One team for cake, food, equipment &amp; setup. Tell us the occasion — we plan it.</p>
        <p class="from">one quotation, everything included</p></a>
    </div>
    <div class="trust"><span>Custom designs</span><span>Tastings on request</span><span>Deposit secures your date</span></div>');
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
        if (in_array($etype, ['Cake', 'Fritters', 'Other Bakery'], true)) {
            $prodId = (int) post('product_id');
            db_exec(
                "INSERT INTO cake_orders (company_id, customer_id, order_type, product_item_id, quantity,
                 required_date, customization, reference_image, fulfilment_method, status)
                 VALUES (?,?,?,?,?,?,?,?,?, 'Awaiting Quotation')",
                [$companyId, $customerId, $etype, $prodId, (float) post('quantity', '1'),
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
      ' . field('Enquiry type', '<select name="enquiry_type"><option>Wedding</option><option>Catering</option><option>Rental</option><option>Cake</option><option>Fritters</option><option>Other Bakery</option><option>General</option></select>') . '
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
