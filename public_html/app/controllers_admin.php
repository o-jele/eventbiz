<?php
// Staff backend: enquiries, events + quotations, rentals + returns,
// payment verification, orders, items/stock, users, reports.
declare(strict_types=1);

function staff_sidebar(): string
{
    $u = current_user();
    $groups = [
        'Desk' => [
            ['url' => '/admin', 'label' => 'Dashboards', 'icon' => 'dashboard'],
            ['url' => '/admin/enquiries', 'label' => 'Enquiries', 'icon' => 'enquiries'],
            ['url' => '/admin/pos', 'label' => 'POS', 'icon' => 'pos'],
            ['url' => '/admin/events', 'label' => 'Events', 'icon' => 'events'],
            ['url' => '/admin/catering', 'label' => 'Catering', 'icon' => 'catering'],
            ['url' => '/admin/rentals', 'label' => 'Rentals', 'icon' => 'rentals'],
        ],
        'Money' => [
            ['url' => '/admin/payments', 'label' => 'Payments', 'icon' => 'payments'],
            ['url' => '/admin/orders', 'label' => 'Orders', 'icon' => 'orders'],
        ],
        'Stock' => [
            ['url' => '/admin/items', 'label' => 'Items', 'icon' => 'items'],
            ['url' => '/admin/warehouse', 'label' => 'Warehouse', 'icon' => 'warehouse'],
        ],
        'Setup' => [
            ['url' => '/admin/reports', 'label' => 'Reports', 'icon' => 'reports'],
            ['url' => '/admin/settings', 'label' => 'Settings', 'icon' => 'settings'],
        ],
    ];
    $open = (int) (db_one("SELECT COUNT(*) AS c FROM enquiries WHERE status = 'Open'")['c'] ?? 0);
    $pend = (int) (db_one("SELECT COUNT(*) AS c FROM payment_declarations WHERE status IN ('Submitted','Pending Verification')")['c'] ?? 0);
    $hot = ['/admin/enquiries' => $open, '/admin/payments' => $pend];
    $here = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
    $h = '<a class="side-brand" href="/admin"><span class="full">Glamorous<em>.</em></span><span class="mini-mark">G.</span></a>';
    // Child pages light up their section parent (Creations = default POS till).
    $aliases = ['/admin/bakery-pos' => '/admin/pos', '/admin/quotations' => '/admin/orders',
                '/admin/invoices' => '/admin/orders', '/admin/transfers' => '/admin/warehouse',
                '/admin/purchases' => '/admin/warehouse'];
    $here = $aliases[$here] ?? $here;
    foreach ($groups as $g => $links) {
        $h .= '<div class="side-grp"><span>' . $g . '</span>';
        foreach ($links as $link) {
            [$url, $label, $ic] = [$link['url'], $link['label'], $link['icon']];
            $n = $hot[$url] ?? 0;
            $active = ($here === $url || ($url !== '/admin' && str_starts_with($here, $url . '/'))) ? ' on' : '';
            $h .= '<a href="' . $url . '" class="side-link' . ($n ? ' hot' : '') . $active . '">'
                . '<span class="ico">' . icon($ic) . '</span><span class="lbl">' . $label . ($n ? ' <b>(' . $n . ')</b>' : '') . '</span></a>';
        }
        $h .= '</div>';
    }
    $name = $u ? $u['name'] : 'Staff';
    $role = $u ? ucwords(str_replace('_', ' ', $u['role'])) : '';
    $words = array_slice(explode(' ', $name), 0, 2);
    $initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), $words)));
    $h .= '<div class="side-foot">'
        . '<div class="profile"><span class="avatar-lg sm">' . e($initials) . '</span>'
        . '<span class="who"><strong>' . e($name) . '</strong><small>' . e($role) . '</small></span>'
        . '<span class="dot-online" title="Signed in"></span></div>'
        . '<div class="profile-actions">'
        . '<a class="profile-btn" href="/logout">' . icon('logout') . '<span>Logout</span></a>'
        . '<button id="side-collapse" class="profile-btn" title="Collapse sidebar">⇤<span>Fold</span></button>'
        . '</div></div>';
    $h .= '<script>(function(){try{'
        . 'if(localStorage.getItem("glam-side")==="mini"){document.body.classList.add("side-mini");}'
        . 'document.getElementById("side-collapse").onclick=function(){document.body.classList.toggle("side-mini");localStorage.setItem("glam-side",document.body.classList.contains("side-mini")?"mini":"full");};'
        . 'var b=document.getElementById("side-burger");if(b){b.onclick=function(){document.body.classList.toggle("side-open");};}'
        . 'var sc=document.getElementById("side-close");if(sc){sc.onclick=function(){document.body.classList.remove("side-open");};}'
        . '}catch(e){}})();</script>';
    return $h;
}

/** Sibling tabs shown on top of grouped work pages (POS / Orders / Warehouse). */
function section_tabs(array $tabs): string
{
    $here = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $h = '<div class="tabs section-tabs">';
    foreach ($tabs as [$url, $label]) {
        $h .= '<a href="' . $url . '" class="' . ($here === $url ? 'on' : '') . '">' . $label . '</a>';
    }
    return $h . '</div>';
}

function admin_nav(): string
{
    // Nav now lives in the staff sidebar (rendered by layout()); kept so
    // existing pages calling admin_nav() need no changes.
    return '';
}

function pg_admin(): void
{
    $u = require_staff();
    $hour = (int) date('G');
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $scope = staff_company_id();
    $scp = $scope ? [$scope] : [];
    $co = fn($a) => $scope ? " AND $a.company_id = ?" : '';

    $open = (int) (db_one("SELECT COUNT(*) AS c FROM enquiries WHERE status = 'Open'")['c'] ?? 0);
    $pend = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(amount),0) AS t FROM payment_declarations WHERE status IN ('Submitted','Pending Verification')");
    $ret = (int) (db_one("SELECT COUNT(*) AS c FROM rental_bookings WHERE status IN ('At Customer','Return Due')")['c'] ?? 0);
    $unpaid = db_one(
        'SELECT COUNT(*) AS n, COALESCE(SUM(i.total - i.paid),0) AS t FROM invoices i WHERE (i.total - i.paid) > 0' . $co('i'), $scp
    );
    $today = db_one(
        'SELECT COUNT(*) AS n, COALESCE(SUM(grand_total),0) AS t FROM sales_orders WHERE DATE(created_at) = CURDATE()' . $co('sales_orders'), $scp
    );

    // Trend + sparkline data (this 7 days vs prior 7).
    $byDay = function (string $table, string $sumCol, string $dateCol, ?string $companyCol) use ($scope): array {
        $sql = "SELECT DATE($dateCol) AS d, COUNT(*) AS n, COALESCE(SUM($sumCol),0) AS t FROM $table WHERE $dateCol >= CURDATE() - INTERVAL 6 DAY";
        $p = [];
        if ($scope && $companyCol) {
            $sql .= " AND $companyCol = ?";
            $p[] = $scope;
        }
        $sql .= " GROUP BY DATE($dateCol)";
        $map = [];
        foreach (db_all($sql, $p) as $r) {
            $map[$r['d']] = $r;
        }
        return $map;
    };
    $range = [];
    for ($i = 6; $i >= 0; $i--) {
        $range[] = date('Y-m-d', strtotime("-$i days"));
    }
    $enqMap = $byDay('enquiries', '1', 'created_at', 'company_id');
    $payMap = $byDay('payments', 'amount', 'created_at', 'company_id');
    $prevQ = function (string $table, string $sumCol, string $dateCol, ?string $companyCol) use ($scope) {
        $sql = "SELECT COUNT(*) AS n, COALESCE(SUM($sumCol),0) AS t FROM $table WHERE $dateCol BETWEEN CURDATE() - INTERVAL 13 DAY AND CURDATE() - INTERVAL 7 DAY";
        $p = [];
        if ($scope && $companyCol) {
            $sql .= " AND $companyCol = ?";
            $p[] = $scope;
        }
        return db_one($sql, $p);
    };
    $sPrev = $prevQ('sales_orders', 'grand_total', 'created_at', 'company_id');
    $ePrev = $prevQ('enquiries', '1', 'created_at', 'company_id');
    $pPrev = $prevQ('payments', 'amount', 'created_at', 'company_id');
    $enq7 = 0;
    foreach ($range as $d) {
        $enq7 += (int) ($enqMap[$d]['n'] ?? 0);
    }
    $pay7 = 0;
    foreach ($range as $d) {
        $pay7 += (float) ($payMap[$d]['t'] ?? 0);
    }

    // Revenue 30d + prior 30d.
    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $days[$d] = ['label' => date('j M', strtotime($d)), 'value' => 0];
    }
    foreach (db_all(
        'SELECT DATE(created_at) AS d, COALESCE(SUM(grand_total),0) AS t FROM sales_orders
         WHERE created_at >= CURDATE() - INTERVAL 29 DAY' . $co('sales_orders') . ' GROUP BY DATE(created_at)', $scp
    ) as $r) {
        if (isset($days[$r['d']])) {
            $days[$r['d']]['value'] = (float) $r['t'];
        }
    }
    $revTotal = array_sum(array_column($days, 'value'));
    $revPrev = (float) (db_one(
        'SELECT COALESCE(SUM(grand_total),0) AS t FROM sales_orders
         WHERE created_at BETWEEN CURDATE() - INTERVAL 59 DAY AND CURDATE() - INTERVAL 30 DAY' . $co('sales_orders'), $scp
    )['t'] ?? 0);

    // Sales mix donut (30d).
    $mixRows = db_all(
        "SELECT CASE WHEN g.name IN ('Catering Menus','Catering Services') THEN 'Catering'
          WHEN g.name IN ('Furniture','Cookware & Serving','Tents & Decor') THEN 'Rentals'
          WHEN g.name IN ('Cakes','Fritters & Snacks') THEN 'Bakery' ELSE 'Shop' END AS bucket,
          COALESCE(SUM(si.amount),0) AS t
         FROM sales_order_items si JOIN sales_orders o ON o.id = si.order_id
         JOIN items i ON i.id = si.item_id LEFT JOIN item_groups g ON g.id = i.item_group_id
         WHERE o.created_at >= CURDATE() - INTERVAL 29 DAY" . $co('o') . ' GROUP BY bucket', $scp
    );
    $mixColors = ['Shop' => '#1A1A1A', 'Bakery' => '#DE7FB8', 'Catering' => '#C9A24B', 'Rentals' => '#7D9B76'];
    $mix = [];
    foreach ($mixRows as $m) {
        $mix[] = ['label' => $m['bucket'], 'value' => (float) $m['t'], 'color' => $mixColors[$m['bucket']] ?? '#999'];
    }

    // Tops.
    $topCust = db_all(
        'SELECT k.name, COALESCE(SUM(o.grand_total),0) AS t FROM sales_orders o JOIN customers k ON k.id = o.customer_id
         WHERE o.created_at >= CURDATE() - INTERVAL 29 DAY' . $co('o') . ' GROUP BY k.id ORDER BY t DESC LIMIT 5', $scp
    );
    $topItems = db_all(
        'SELECT i.name, COALESCE(SUM(si.qty),0) AS q, COALESCE(SUM(si.amount),0) AS t FROM sales_order_items si
         JOIN sales_orders o ON o.id = si.order_id JOIN items i ON i.id = si.item_id
         WHERE o.created_at >= CURDATE() - INTERVAL 6 DAY' . $co('o') . ' GROUP BY i.id ORDER BY t DESC LIMIT 5', $scp
    );

    // Monthly calendar (navigable; click a day to start booking it).
    $cal = get_param('cal', date('Y-m'));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $cal)) {
        $cal = date('Y-m');
    }
    $calFirst = $cal . '-01';
    $calPrev = date('Y-m', strtotime($calFirst . ' -1 month'));
    $calNext = date('Y-m', strtotime($calFirst . ' +1 month'));
    $monthEvents = db_all(
        "SELECT id, name, event_date FROM events
         WHERE event_date >= ? AND event_date < DATE_ADD(?, INTERVAL 1 MONTH)
           AND status NOT IN ('Completed','Cancelled') ORDER BY event_date",
        [$calFirst, $calFirst]
    );
    $dots = [];
    foreach ($monthEvents as $ce) {
        $dots[$ce['event_date']][] = $ce;
    }
    $calCells = str_repeat('<span class="cal-empty"></span>', (int) date('N', strtotime($calFirst)) - 1);
    $todayS = date('Y-m-d');
    $daysIn = (int) date('t', strtotime($calFirst));
    for ($dd = 1; $dd <= $daysIn; $dd++) {
        $d = sprintf('%s-%02d', $cal, $dd);
        $n = isset($dots[$d]) ? count($dots[$d]) : 0;
        $cls = 'cal-cell' . ($n ? ' has' : '') . ($d === $todayS ? ' today' : '');
        $titles = $n ? ' title="' . e(implode(', ', array_column($dots[$d], 'name'))) . '"' : '';
        $calCells .= '<a class="' . $cls . '" href="/admin/events?new=1&date=' . $d . '"' . $titles . '><b>' . $dd . '</b>' . ($n ? '<i>' . $n . '</i>' : '') . '</a>';
    }
    $calHtml = '<div class="cal-nav"><a class="btn sec" href="/admin?cal=' . $calPrev . '">Prev</a>'
        . '<strong>' . e(date('F Y', strtotime($calFirst))) . '</strong>'
        . '<a class="btn sec" href="/admin?cal=' . $calNext . '">Next</a></div>'
        . '<div class="cal-month"><span class="cal-dow">Mo</span><span class="cal-dow">Tu</span><span class="cal-dow">We</span>'
        . '<span class="cal-dow">Th</span><span class="cal-dow">Fr</span><span class="cal-dow">Sa</span><span class="cal-dow">Su</span>'
        . $calCells . '</div>';
    $upcoming = db_all(
        "SELECT v.id, v.name, v.event_date, v.status, k.name AS customer FROM events v
         JOIN customers k ON k.id = v.customer_id
         WHERE v.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
           AND v.status NOT IN ('Completed','Cancelled') ORDER BY v.event_date LIMIT 8"
    );
    $out = db_all(
        "SELECT b.id, b.return_expected, k.name AS customer FROM rental_bookings b
         JOIN customers k ON k.id = b.customer_id
         WHERE b.status IN ('Dispatched','At Customer') ORDER BY b.return_expected LIMIT 6"
    );
    $af = get_param('af', 'all');
    $parts = [];
    $fp = [];
    if ($af === 'all' || $af === 'orders') {
        $parts[] = "(SELECT 'order' AS t, CONCAT('Order #', o.id, ' · ', k.name) AS d, o.status AS s, o.created_at AS c
          FROM sales_orders o JOIN customers k ON k.id = o.customer_id WHERE 1=1" . $co('o') . ')';
        $fp = array_merge($fp, $scp);
    }
    if ($af === 'all' || $af === 'payments') {
        $parts[] = "(SELECT 'payment' AS t, CONCAT(p.method, ' · MK', FORMAT(p.amount, 0)) AS d, 'posted' AS s, p.created_at AS c
          FROM payments p WHERE 1=1" . $co('p') . ')';
        $fp = array_merge($fp, $scp);
    }
    if ($af === 'all' || $af === 'bookings') {
        $parts[] = "(SELECT 'rental' AS t, CONCAT('Booking #', b.id, ' · ', k.name) AS d, b.status AS s, b.created_at AS c
          FROM rental_bookings b JOIN customers k ON k.id = b.customer_id)";
        $parts[] = "(SELECT 'enquiry' AS t, CONCAT(eq.enquiry_type, ': ', eq.subject) AS d, eq.status AS s, eq.created_at AS c FROM enquiries eq)";
        $parts[] = "(SELECT 'transfer' AS t, CONCAT('Transfer #', t.id) AS d, 'done' AS s, t.created_at AS c FROM transfers t)";
    }
    $feed = $parts ? db_all(implode(' UNION ALL ', $parts) . ' ORDER BY c DESC LIMIT 12', $fp) : [];
    $badge = function (string $s): string {
        $ok = ['Completed', 'Paid', 'posted', 'done', 'Delivered', 'Confirmed', 'Approved', 'Converted', 'Returned', 'Settled'];
        $bad = ['Cancelled', 'Rejected'];
        $cls = in_array($s, $ok, true) ? 'ok' : (in_array($s, $bad, true) ? 'bad' : 'warn');
        return '<span class="status-badge ' . $cls . '">' . e($s) . '</span>';
    };
    $low = db_all('SELECT sku, name, stock_qty FROM items WHERE item_type = ? AND stock_qty <= reorder_level AND reorder_level > 0 LIMIT 8', ['stock']);
    $oos = db_all("SELECT name, stock_qty FROM items WHERE item_type = 'stock' AND stock_qty <= 0 AND published = 1 ORDER BY name LIMIT 6");

    $stat = function (string $num, string $label, string $link, bool $alert = false): string {
        return '<div class="stat' . ($alert ? ' alert' : '') . '"><div class="n">' . $num . '</div>'
            . '<div class="l">' . e($label) . '</div><p><a href="' . $link . '">Open →</a></p></div>';
    };
    $tile = function (string $num, string $label, string $link, string $badgeHtml, bool $alert = false, float $raw = 0, int $dec = 0, string $pre = ''): string {
        return '<a class="stat' . ($alert ? ' alert' : '') . '" href="' . $link . '"><div class="n" data-count="' . $raw . '" data-dec="' . $dec . '" data-pre="' . $pre . '">' . $num . '</div>'
            . '<div class="l">' . e($label) . ' ' . $badgeHtml . '</div></a>';
    };
    $opsOnly = $u['role'] !== 'creations_staff';

    $upHtml = '';
    foreach ($upcoming as $v) {
        $inDays = (int) ((strtotime($v['event_date']) - strtotime(date('Y-m-d'))) / 86400);
        $upHtml .= '<li><a href="/admin/events?view=' . (int) $v['id'] . '">' . e($v['name']) . '</a>'
            . '<br><span class="t">' . e($v['event_date']) . ' · in ' . $inDays . ' days · ' . e($v['customer']) . ' · ' . e($v['status']) . '</span></li>';
    }
    $outHtml = '';
    foreach ($out as $b) {
        $its = db_all(
            'SELECT i.qty, t.name FROM rental_booking_items i JOIN items t ON t.id = i.item_id WHERE i.booking_id = ?',
            [(int) $b['id']]
        );
        $desc = [];
        foreach ($its as $it) {
            $desc[] = (int) $it['qty'] . '× ' . $it['name'];
        }
        $outHtml .= '<li><a href="/admin/rentals?view=' . (int) $b['id'] . '">Booking #' . (int) $b['id'] . '</a> ' . e($b['customer'])
            . '<br><span class="t">' . e(implode(', ', $desc)) . ' · back ' . e($b['return_expected']) . '</span></li>';
    }
    $feedHtml = '';
    $lastDay = '';
    foreach ($feed as $f) {
        $day = substr((string) $f['c'], 0, 10);
        $dayLabel = $day === date('Y-m-d') ? 'Today' : ($day === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : $day);
        if ($dayLabel !== $lastDay) {
            if ($lastDay !== '') {
                $feedHtml .= '</ul>';
            }
            $feedHtml .= '<h4 style="margin:.8rem 0 .2rem">' . e($dayLabel) . '</h4><ul class="feed">';
            $lastDay = $dayLabel;
        }
        $feedHtml .= '<li><div class="feed-item"><span class="fava t-' . e($f['t']) . '">' . strtoupper(e(substr($f['t'], 0, 1))) . '</span>'
            . '<div>' . e($f['d']) . ' ' . $badge((string) $f['s']) . '<br><span class="t">' . e($f['c']) . '</span></div></div></li>';
    }
    if ($feedHtml !== '') {
        $feedHtml .= '</ul>';
    }
    $tabs = '';
    foreach (['all' => 'All', 'orders' => 'Orders', 'payments' => 'Payments', 'bookings' => 'Bookings'] as $k => $label) {
        $tabs .= '<a href="/admin?af=' . $k . '" class="' . ($af === $k ? 'on' : '') . '">' . $label . '</a>';
    }
    $invHtml = '';
    foreach ($oos as $l) {
        $invHtml .= '<li class="inv-alert out" style="padding-left:.6rem">' . e($l['name']) . ' <span class="t">OUT OF STOCK</span></li>';
    }
    foreach ($low as $l) {
        $invHtml .= '<li class="inv-alert" style="padding-left:.6rem">' . e($l['name']) . ' <span class="t">' . e((string) $l['stock_qty']) . ' left</span></li>';
    }
    $topCustHtml = '';
    foreach ($topCust as $c) {
        $topCustHtml .= '<li>' . e($c['name']) . ' <span class="t">MK' . number_format((float) $c['t']) . '</span></li>';
    }
    $topItemsHtml = '';
    foreach ($topItems as $it) {
        $topItemsHtml .= '<li>' . e($it['name']) . ' <span class="t">' . (int) $it['q'] . ' sold · MK' . number_format((float) $it['t']) . '</span></li>';
    }

    layout('Staff', admin_nav()
        . '<div class="greet-row"><div>'
        . '<h1>' . $greet . ', <span class="grad-text">' . e($u['name']) . '</span></h1>'
        . '<p class="mut" style="margin:0">' . date('l, j F Y') . ' · here is your business at a glance.</p></div></div>'
        . '<p class="section-label">Shortcuts</p>'
        . '<div class="quick-actions"><a class="btn sec" href="/admin/pos">Creations POS</a>'
        . '<a class="btn sec" href="/admin/bakery-pos">Bakery POS</a>'
        . '<a class="btn sec" href="/admin/catering">Catering Booking</a>'
        . '<a class="btn sec" href="/admin/rentals">Rental Booking</a></div>'
        . '<p class="section-label">At a glance</p>'
        . '<div class="stats">'
        . $tile((string) $open, 'open enquiries', '/admin/enquiries',
            trend_badge(trend_of((float) $enq7, (float) ($ePrev['n'] ?? 0))), $open > 0, (float) $open, 0, '')
        . $tile(number_format((float) ($pend['t'] ?? 0)), 'to verify (' . (int) ($pend['n'] ?? 0) . ')', '/admin/payments',
            trend_badge(trend_of((float) $pay7, (float) ($pPrev['t'] ?? 0))), ($pend['n'] ?? 0) > 0, (float) ($pend['t'] ?? 0), 0, 'MK')
        . $tile(number_format($revTotal), 'revenue 30 days', '/admin/reports',
            trend_badge(trend_of($revTotal, $revPrev)), true, $revTotal, 0, 'MK')
        . $tile(number_format((float) ($unpaid['t'] ?? 0)), 'owed (' . (int) ($unpaid['n'] ?? 0) . ' invoices)', '/admin/invoices?f=unpaid',
            '', ($unpaid['n'] ?? 0) > 0, (float) ($unpaid['t'] ?? 0), 0, 'MK')
        . '</div>'
        . '<div class="dash-grid rev-grid">'
        . '<div class="panel chart-card"><h3>Revenue · last 30 days</h3><div class="chart-meta"><span class="big" data-count="' . $revTotal . '" data-dec="0" data-pre="MK">MK' . number_format($revTotal) . '</span>'
        . trend_badge(trend_of($revTotal, $revPrev)) . '<span class="mut">vs prior 30d</span></div>'
        . svg_area_chart(array_values($days)) . '</div>'
        . '<div class="panel chart-card"><h3>Sales mix · last 30 days</h3>'
        . ($mix ? svg_donut($mix) : '<p class="mut">No sales yet.</p>') . '</div>'
        . '</div>'
        . '<div class="dash-grid"><div>'
        . '<div class="panel"><h3>Today</h3><p class="stat-line"><strong>' . (int) ($today['n'] ?? 0) . ' sales</strong> · MK'
        . number_format((float) ($today['t'] ?? 0)) . ' taken</p>'
        . '<p><a class="btn sec" href="/admin/pos">New counter sale</a> <a class="btn sec" href="/admin/orders">Orders</a></p></div>'
        . ($opsOnly
            ? '<div class="panel"><h3>Coming up</h3>' . $calHtml
              . ($upHtml ? '<ul class="feed">' . $upHtml . '</ul>' : '<p class="mut">No events on the calendar.</p>') . '</div>'
              . '<div class="panel"><h3>Equipment out</h3>' . ($outHtml ? '<ul class="feed">' . $outHtml . '</ul>' : '<p class="mut">Everything is home.</p>') . '</div>'
              . '<div class="panel"><h3>Top this week</h3><h4>Items</h4><ul class="feed">' . ($topItemsHtml ?: '<li class="mut">No sales yet.</li>') . '</ul>'
              . '<h4>Customers (30d)</h4><ul class="feed">' . ($topCustHtml ?: '<li class="mut">No sales yet.</li>') . '</ul></div>'
            : '')
        . '</div><div>'
        . '<div class="panel"><h3>Latest activity</h3><div class="tabs">' . $tabs . '</div>' . ($feedHtml ?: '<p class="mut">Nothing yet.</p>') . '</div>'
        . '<div class="panel"><h3>Inventory alerts</h3>' . ($invHtml ? '<ul class="feed">' . $invHtml . '</ul>' : '<p class="mut">Stock levels healthy.</p>') . '</div>'
        . '</div></div>'
        . '<script>(function(){function fmt(v,dec){return Number(v).toLocaleString("en-US",{minimumFractionDigits:dec,maximumFractionDigits:dec});}'
        . 'document.querySelectorAll("[data-count]").forEach(function(el){var target=parseFloat(el.dataset.count||"0"),dec=parseInt(el.dataset.dec||"0",10),pre=el.dataset.pre||"";'
        . 'if(!isFinite(target))return;var t0=null;function step(t){if(!t0)t0=t;var p=Math.min(1,(t-t0)/800),e=1-Math.pow(1-p,3);'
        . 'el.textContent=pre+fmt(target*e,dec);if(p<1)requestAnimationFrame(step);}requestAnimationFrame(step);});})();</script>');
}

// ---------- Enquiries ----------
function pg_admin_enquiries(): void
{
    $u = require_role(['admin', 'accounts', 'delights_sales', 'creations_staff']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_enquiry'])) {
        check_csrf();
        $enq = db_one('SELECT * FROM enquiries WHERE id = ?', [(int) $_POST['convert_enquiry']]);
        if ($enq) {
            guard_company(company_name((int) $enq['company_id']));
            $cid = $enq['customer_id'] ?: find_or_create_customer($enq['name'], (string) $enq['phone'], (string) $enq['email']);
            db_exec(
                "INSERT INTO events (company_id, customer_id, name, event_type, event_date, venue, status, notes)
                 VALUES (?,?,?,?,?,?, 'Enquiry', ?)",
                [(int) $enq['company_id'], $cid, $enq['subject'], 'Other',
                 $enq['event_date'] ?: date('Y-m-d'), 'TBD', 'From enquiry #' . $enq['id'] . ': ' . $enq['message']]
            );
            $evId = db_last_id();
            db_exec("UPDATE enquiries SET status='Converted', customer_id=? WHERE id=?", [$cid, $enq['id']]);
            flash('Enquiry converted to event #' . $evId . '.');
            redirect('/admin/events?view=' . $evId);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_enquiry'])) {
        check_csrf();
        db_exec("UPDATE enquiries SET status='Closed' WHERE id=?", [(int) $_POST['close_enquiry']]);
        redirect('/admin/enquiries');
    }
    $rows = db_all(
        'SELECT q.*, c.name AS company FROM enquiries q JOIN companies c ON c.id=q.company_id
         WHERE q.status NOT IN (?,?) ORDER BY q.id DESC LIMIT 100', ['Converted', 'Closed']
    );
    $tr = [];
    foreach ($rows as $r) {
        $tr[] = [brand_badge($r['company']), '#' . $r['id'] . ' ' . e($r['subject']),
                 e($r['name']) . '<br>' . e((string) $r['phone']), e($r['enquiry_type']) . '<br>' . e($r['status']),
                 '<form method="post" style="display:inline">' . csrf_field() . '
                  <input type="hidden" name="convert_enquiry" value="' . (int) $r['id'] . '">
                  <button class="btn sec">Convert to event</button></form> ·
                  <form method="post" style="display:inline">' . csrf_field() . '
                  <input type="hidden" name="close_enquiry" value="' . (int) $r['id'] . '">
                  <button class="btn sec">Close</button></form>'];
    }
    layout('Enquiries', admin_nav() . '<h1>Enquiry inbox</h1>' .
        ($tr ? table(['Brand', 'Subject', 'Contact', 'Type/Status', ''], $tr) : '<p class="mut">Inbox zero.</p>'));
}

// ---------- Events + quotations ----------
function pg_admin_events(): void
{
    $u = require_role(['admin', 'accounts', 'delights_sales', 'delights_ops']);
    if (($_GET['new'] ?? '') === '1') {
        $preDate = get_param('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preDate)) {
            $preDate = '';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_csrf();
            $companyId = company_id(GD_NAME);
            guard_company(GD_NAME);
            $cid = find_or_create_customer(post('customer_name'), post('customer_phone'));
            db_exec(
                'INSERT INTO events (company_id, customer_id, name, event_type, event_date, venue, venue_address, guests, contact_person, contact_phone, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$companyId, $cid, post('name'), post('event_type'), post('event_date'), post('venue'),
                 post('venue_address'), (int) post('guests') ?: null, post('customer_name'), post('customer_phone'), 'Enquiry']
            );
            redirect('/admin/events?view=' . db_last_id());
        }
        $c = brand_badge(GD_NAME);
        layout('New event', admin_nav() . "<h1>New event $c</h1><div class=\"card\"><form method=\"post\">" . csrf_field() . '
          ' . field('Event name', '<input name="name" required placeholder="Wedding - Banda Family">') . '
          ' . field('Type', '<select name="event_type"><option>Wedding</option><option>Funeral</option><option>Corporate</option><option>Party</option><option>Function</option><option>Other</option></select>') . '
          ' . field('Date', '<input type="date" name="event_date" required value="' . e($preDate) . '">') . '
          ' . field('Venue', '<input name="venue" required>') . '
          ' . field('Venue address', '<textarea name="venue_address" rows="2"></textarea>') . '
          ' . field('Guests', '<input name="guests" type="number">') . '
          ' . field('Customer name', '<input name="customer_name" required>') . '
          ' . field('Customer phone', '<input name="customer_phone" required>') . '
          <button class="btn">Create</button></form></div>');
        return;
    }
    if (($_GET['view'] ?? '') !== '') {
        pg_admin_event_view((int) $_GET['view']);
        return;
    }
    $rows = db_all(
        'SELECT v.*, c.name AS company, k.name AS customer FROM events v
         JOIN companies c ON c.id=v.company_id JOIN customers k ON k.id=v.customer_id
         ORDER BY v.event_date DESC LIMIT 100'
    );
    $tr = [];
    foreach ($rows as $r) {
        $tr[] = [brand_badge($r['company']), '<a href="/admin/events?view=' . (int) $r['id'] . '">#' . (int) $r['id'] . ' ' . e($r['name']) . '</a>',
                 e($r['event_date']), e($r['customer']), e($r['status'])];
    }
    layout('Events', admin_nav() . '<h1>Events</h1><p><a class="btn" href="/admin/events?new=1">New event</a></p>' .
        ($tr ? table(['Brand', 'Event', 'Date', 'Customer', 'Status'], $tr) : '<p class="mut">No events yet.</p>'));
}

function delights_items_options(): string
{
    $items = db_all("SELECT id, name, price FROM items WHERE business_unit IN ('Delights','Shared') AND published = 1 ORDER BY name");
    $o = '<option value="">— pick item —</option>';
    foreach ($items as $i) {
        $o .= '<option value="' . (int) $i['id'] . '">' . e($i['name']) . ' (' . money((float) $i['price']) . ')</option>';
    }
    return $o;
}

function pg_admin_event_view(int $id): void
{
    $u = require_role(['admin', 'accounts', 'delights_sales', 'delights_ops']);
    $ev = db_one('SELECT v.*, c.name AS company, k.name AS customer, k.phone FROM events v
                  JOIN companies c ON c.id=v.company_id JOIN customers k ON k.id=v.customer_id WHERE v.id = ?', [$id]);
    if (!$ev) {
        http_response_code(404);
        exit('Event not found.');
    }
    guard_company($ev['company']);
    // New quotation from form.
    if (($_GET['quote'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $types = $_POST['service_type'] ?? [];
        $itemIds = $_POST['item_id'] ?? [];
        $descs = $_POST['description'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $rates = $_POST['rate'] ?? [];
        $services = [];
        $sub = 0;
        foreach ($types as $n => $t) {
            if (trim((string) ($descs[$n] ?? '')) === '') {
                continue;
            }
            $q = (float) ($qtys[$n] ?? 0);
            $r = (float) ($rates[$n] ?? 0);
            $amt = $q * $r;
            $sub += $amt;
            $services[] = ['type' => $t, 'item' => (int) ($itemIds[$n] ?? 0) ?: null,
                           'desc' => trim((string) $descs[$n]), 'qty' => $q, 'rate' => $r, 'amount' => $amt];
        }
        if (!$services) {
            flash('Add at least one service row.', 'err');
            redirect('/admin/events?view=' . $id . '&quote=1');
        }
        $dep = (float) post('deposit_required');
        db()->beginTransaction();
        db_exec(
            'INSERT INTO quotations (company_id, customer_id, event_id, subtotal, delivery_setup, grand_total, deposit_required, valid_until, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [(int) $ev['company_id'], (int) $ev['customer_id'], $id, $sub, 0, $sub, $dep, post('valid_until') ?: null, 'Sent', (int) $u['id']]
        );
        $qid = db_last_id();
        foreach ($services as $s) {
            db_exec(
                'INSERT INTO quotation_services (quotation_id, service_type, item_id, description, qty, rate, amount)
                 VALUES (?,?,?,?,?,?,?)',
                [$qid, $s['type'], $s['item'], $s['desc'], $s['qty'], $s['rate'], $s['amount']]
            );
        }
        db_exec("UPDATE events SET status='Quoted' WHERE id=?", [$id]);
        db()->commit();
        flash('Quotation #' . $qid . ' created. Total MK' . money($sub) . '.');
        redirect('/admin/events?view=' . $id);
    }
    // Convert quotation → sales order + invoice + rental/catering/cake docs (POST only).
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_qid'])) {
        check_csrf();
        pg_admin_quotation_convert($ev, (int) $_POST['convert_qid'], $u);
        return;
    }
    if (($_GET['quote'] ?? '') === '1') {
        $rows = '';
        $opts = delights_items_options();
        for ($n = 0; $n < 6; $n++) {
            $rows .= '<tr><td><select name="service_type[]"><option>Rental</option><option>Catering</option><option>Bakery</option><option>Delivery</option><option>Setup</option><option>Staffing</option><option>Other</option></select></td>
              <td><select name="item_id[]">' . $opts . '</select></td>
              <td><input name="description[]" placeholder="300× chairs"></td>
              <td><input name="qty[]" value="1"></td><td><input name="rate[]" value="0"></td></tr>';
        }
        layout('New quotation', admin_nav() . '<h1>Quotation for ' . e($ev['name']) . '</h1>
          <div class="card"><form method="post">' . csrf_field() . '
          <table class="tbl"><thead><tr><th>Type</th><th>Item</th><th>Description</th><th>Qty</th><th>Rate</th></tr></thead>
          <tbody>' . $rows . '</tbody></table>
          ' . field('Deposit required (MK — no fixed %, per agreement)', '<input name="deposit_required" required>') . '
          ' . field('Valid until', '<input type="date" name="valid_until">') . '
          <button class="btn">Generate quotation</button></form></div>');
        return;
    }
    $quotes = db_all('SELECT * FROM quotations WHERE event_id = ? ORDER BY id DESC', [$id]);
    $qr = [];
    foreach ($quotes as $q) {
        $qr[] = ['#' . $q['id'], money((float) $q['grand_total']), money((float) $q['deposit_required']),
                 e($q['status']), in_array($q['status'], ['Sent', 'Approved'], true)
                    ? '<form method="post" style="display:inline">' . csrf_field() . '
                       <input type="hidden" name="convert_qid" value="' . (int) $q['id'] . '">
                       <button class="btn sec">Convert to order</button></form>' : ''];
    }
    $rentals = db_all('SELECT * FROM rental_bookings WHERE event_id = ? ORDER BY id', [$id]);
    $rr = [];
    foreach ($rentals as $b) {
        $rr[] = ['#' . $b['id'], e($b['event_date']), money((float) $b['grand_total']), e($b['status'])];
    }
    layout($ev['name'], admin_nav() . '<h1>' . e($ev['name']) . ' ' . brand_badge($ev['company']) . '</h1>
      <p class="mut">' . e($ev['customer']) . ' · ' . e($ev['event_date']) . ' · ' . e($ev['venue']) . ' · ' . e($ev['status']) . '</p>
      <p><a class="btn" href="/admin/events?view=' . $id . '&quote=1">New quotation</a></p>
      <h2>Quotations</h2>' . ($qr ? table(['#', 'Total', 'Deposit', 'Status', ''], $qr) : '<p class="mut">None.</p>') . '
      <h2>Rental bookings</h2>' . ($rr ? table(['#', 'Event date', 'Total', 'Status'], $rr) : '<p class="mut">None.</p>'));
}

function pg_admin_quotation_convert(array $ev, int $qid, array $u): void
{
    require_role(['admin', 'accounts', 'delights_sales']);
    $q = db_one('SELECT * FROM quotations WHERE id = ? AND event_id = ?', [$qid, (int) $ev['id']]);
    if (!$q || !in_array($q['status'], ['Sent', 'Approved'], true)) {
        exit('Quotation cannot be converted.');
    }
    $services = db_all('SELECT s.*, i.name AS item_name, i.item_type FROM quotation_services s
                        LEFT JOIN items i ON i.id = s.item_id WHERE s.quotation_id = ?', [$qid]);
    db()->beginTransaction();
    try {
        db_exec(
            "INSERT INTO sales_orders (company_id, customer_id, event_id, status, fulfilment_method, delivery_status, subtotal, grand_total, created_by)
             VALUES (?,?,?, 'Confirmed', 'Delivery', 'Pending Arrangement', ?, ?, ?)",
            [(int) $ev['company_id'], (int) $ev['customer_id'], (int) $ev['id'],
             (float) $q['grand_total'], (float) $q['grand_total'], (int) $u['id']]
        );
        $orderId = db_last_id();
        foreach ($services as $s) {
            if (!$s['item_id']) {
                throw new RuntimeException('Every service row needs an Item before converting.');
            }
            db_exec(
                'INSERT INTO sales_order_items (order_id, item_id, description, qty, rate, amount) VALUES (?,?,?,?,?,?)',
                [$orderId, $s['item_id'], $s['description'], $s['qty'], $s['rate'], $s['amount']]
            );
        }
        // Rental rows → one booking (availability enforced).
        $rentalRows = array_values(array_filter($services, fn($s) => $s['service_type'] === 'Rental'));
        if ($rentalRows) {
            db_exec(
                "INSERT INTO rental_bookings (company_id, customer_id, event_id, booking_date, event_date, return_expected,
                 fulfilment_method, rental_total, grand_total, deposit_required, status)
                 VALUES (?,?,?,?,?,?, 'Delivery', ?, ?, ?, 'Deposit Pending')",
                [(int) $ev['company_id'], (int) $ev['customer_id'], (int) $ev['id'], date('Y-m-d'),
                 $ev['event_date'], $ev['event_date'], (float) $q['grand_total'], (float) $q['grand_total'], (float) $q['deposit_required']]
            );
            $bid = db_last_id();
            foreach ($rentalRows as $s) {
                $avail = rental_available((int) $s['item_id'], $ev['event_date'], $ev['event_date']);
                if ($avail < (float) $s['qty']) {
                    throw new RuntimeException('Not enough ' . $s['item_name'] . ' available (' . $avail . ').');
                }
                db_exec(
                    'INSERT INTO rental_booking_items (booking_id, item_id, qty, rate, amount) VALUES (?,?,?,?,?)',
                    [$bid, $s['item_id'], (int) $s['qty'], $s['rate'], $s['amount']]
                );
            }
        }
        foreach ($services as $s) {
            if ($s['service_type'] === 'Catering') {
                db_exec(
                    "INSERT INTO catering_orders (company_id, customer_id, event_id, service_date, guests, menu_item_id, rate_per_head, amount, status)
                     VALUES (?,?,?,?,?,?,?,?,'Confirmed')",
                    [(int) $ev['company_id'], (int) $ev['customer_id'], (int) $ev['id'],
                     $ev['event_date'], (int) $ev['guests'] ?: (int) $s['qty'], $s['item_id'], $s['rate'], $s['amount']]
                );
            }
            if ($s['service_type'] === 'Bakery') {
                db_exec(
                    "INSERT INTO cake_orders (company_id, customer_id, event_id, order_type, product_item_id, quantity,
                     required_date, customization, price, deposit_required, status)
                     VALUES (?,?,?,?,?,?,?,? ,?,?, 'Confirmed')",
                    [(int) $ev['company_id'], (int) $ev['customer_id'], (int) $ev['id'], 'Cake',
                     $s['item_id'], $s['qty'], $ev['event_date'], $s['description'], $s['amount'], 0]
                );
            }
        }
        $invId = make_invoice((int) $ev['company_id'], (int) $ev['customer_id'], $orderId,
                              'Event order #' . $orderId . ' (' . $ev['name'] . ')', (float) $q['grand_total']);
        db_exec("UPDATE quotations SET status='Converted', order_id=? WHERE id=?", [$orderId, $qid]);
        db_exec("UPDATE events SET status='Deposit Pending' WHERE id=?", [(int) $ev['id']]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        flash($ex->getMessage(), 'err');
        redirect('/admin/events?view=' . (int) $ev['id']);
    }
    flash('Converted: order #' . $orderId . ', invoice #' . $invId . '.');
    redirect('/admin/events?view=' . (int) $ev['id']);
}

// ---------- Rentals ----------
function pg_admin_rentals(): void
{
    $u = require_role(['admin', 'accounts', 'delights_sales', 'delights_ops']);
    if (($_GET['view'] ?? '') !== '') {
        pg_admin_rental_view((int) $_GET['view'], $u);
        return;
    }
    if (($_GET['new'] ?? '') === '1') {
        pg_admin_rental_new();
        return;
    }
    $rows = db_all(
        'SELECT b.*, c.name AS company, k.name AS customer FROM rental_bookings b
         JOIN companies c ON c.id=b.company_id JOIN customers k ON k.id=b.customer_id
         ORDER BY b.event_date DESC LIMIT 100'
    );
    $tr = [];
    foreach ($rows as $r) {
        $tr[] = [brand_badge($r['company']),
                 '<a href="/admin/rentals?view=' . (int) $r['id'] . '">#' . (int) $r['id'] . '</a>',
                 e($r['customer']), e($r['event_date']) . ' → ' . e($r['return_expected']),
                 money((float) $r['grand_total']), e($r['status'])];
    }
    layout('Rentals', admin_nav() . '<h1>Rental bookings</h1><p><a class="btn" href="/admin/rentals?new=1">New booking</a></p>' .
        ($tr ? table(['Brand', '#', 'Customer', 'Use → return', 'Total', 'Status'], $tr) : '<p class="mut">No bookings.</p>'));
}

function pg_admin_rental_view(int $id, array $u): void
{
    $b = db_one('SELECT b.*, c.name AS company, k.name AS customer FROM rental_bookings b
                 JOIN companies c ON c.id=b.company_id JOIN customers k ON k.id=b.customer_id WHERE b.id = ?', [$id]);
    if (!$b) {
        http_response_code(404);
        exit('Booking not found.');
    }
    guard_company($b['company']);
    $items = db_all('SELECT i.*, t.name AS item_name FROM rental_booking_items i JOIN items t ON t.id=i.item_id WHERE i.booking_id = ?', [$id]);
    // Status moves (POST only).
    $flow = ['Confirmed' => ['Deposit Pending', 'Quoted'], 'Dispatched' => ['Confirmed'],
             'At Customer' => ['Dispatched'], 'Return Due' => ['At Customer'],
             'Cancelled' => ['Quoted', 'Deposit Pending']];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set'])) {
        check_csrf();
        $to = $_POST['set'];
        if (isset($flow[$to]) && in_array($b['status'], $flow[$to], true)) {
            if ($to === 'Confirmed') {
                foreach ($items as $it) {
                    $avail = rental_available((int) $it['item_id'], $b['event_date'], $b['return_expected'], $id);
                    if ($avail < (int) $it['qty']) {
                        flash('Not enough ' . $it['item_name'] . ' available (' . $avail . ').', 'err');
                        redirect('/admin/rentals?view=' . $id);
                    }
                }
            }
            db_exec('UPDATE rental_bookings SET status=? WHERE id=?', [$to, $id]);
            flash('Booking #' . $id . ' → ' . $to . '.');
        }
        redirect('/admin/rentals?view=' . $id);
    }
    // Standalone invoice for bookings not covered by an event invoice (POST only).
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_invoice'])) {
        check_csrf();
        $invId = rental_ensure_invoice($id, $u);
        flash('Invoice #' . $invId . ' ready.');
        redirect('/admin/rentals?view=' . $id);
    }
    // Return form.
    if (($_GET['return'] ?? '') === '1') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_csrf();
            require_role(['admin', 'accounts', 'delights_ops']);
            $goods = $_POST['good'] ?? [];
            $dmgs = $_POST['damaged'] ?? [];
            $miss = $_POST['missing'] ?? [];
            $dchg = $_POST['damage_charge'] ?? [];
            $mchg = $_POST['missing_charge'] ?? [];
            $dmgTotal = 0;
            $missTotal = 0;
            $lines = [];
            $wh = db_one('SELECT id FROM warehouses WHERE name = ?', ['DELIGHTS - RENTAL - GD']);
            db()->beginTransaction();
            try {
                foreach ($items as $it) {
                    $iid = (int) $it['item_id'];
                    $g = (int) ($goods[$iid] ?? 0);
                    $d = (int) ($dmgs[$iid] ?? 0);
                    $m = (int) ($miss[$iid] ?? 0);
                    if ($g + $d + $m !== (int) $it['qty']) {
                        throw new RuntimeException($it['item_name'] . ": good($g)+damaged($d)+missing($m) must equal dispatched(" . $it['qty'] . ').');
                    }
                    $dc = (float) ($dchg[$iid] ?? 0);
                    $mc = (float) ($mchg[$iid] ?? 0);
                    $dmgTotal += $dc;
                    $missTotal += $mc;
                    $lines[] = [$iid, $it['qty'], $g, $d, $m, $dc, $mc];
                    if ($m > 0) {
                        post_stock($iid, (int) $wh['id'], -$m, 'rental_missing', $id, 'Missing on return');
                    }
                }
                $clean = (float) post('cleaning', '0');
                $forf = (float) post('forfeited', '0');
                $set = settle_deposit((float) $b['deposit_received'], $dmgTotal, $missTotal, $clean, $forf);
                db_exec(
                    'INSERT INTO rental_returns (booking_id, company_id, customer_id, return_actual, damage_charges,
                     missing_charges, cleaning_charges, forfeited, refund_due, status)
                     VALUES (?,?,?,?,?,?,?,?,?, ?)',
                    [$id, (int) $b['company_id'], (int) $b['customer_id'], post('return_actual') ?: date('Y-m-d'),
                     $dmgTotal, $missTotal, $clean, $forf, $set['refund_due'], 'Settled']
                );
                $rid = db_last_id();
                foreach ($lines as [$iid, $disp, $g, $d, $m, $dc, $mc]) {
                    db_exec(
                        'INSERT INTO rental_return_items (return_id, item_id, qty_dispatched, qty_good, qty_damaged, qty_missing, damage_charge, missing_charge)
                         VALUES (?,?,?,?,?,?,?,?)',
                        [$rid, $iid, $disp, $g, $d, $m, $dc, $mc]
                    );
                }
                db_exec("UPDATE rental_bookings SET status='Returned' WHERE id=?", [$id]);
                db()->commit();
            } catch (Throwable $ex) {
                db()->rollBack();
                flash($ex->getMessage(), 'err');
                redirect('/admin/rentals?view=' . $id . '&return=1');
            }
            flash('Return settled. Refund due MK' . money($set['refund_due']) . '.');
            redirect('/admin/rentals?view=' . $id);
        }
        $fr = '';
        foreach ($items as $it) {
            $iid = (int) $it['item_id'];
            $fr .= '<tr><td>' . e($it['item_name']) . ' (dispatched ' . (int) $it['qty'] . ')</td>
              <td><input name="good[' . $iid . ']" value="' . (int) $it['qty'] . '"></td>
              <td><input name="damaged[' . $iid . ']" value="0"></td>
              <td><input name="missing[' . $iid . ']" value="0"></td>
              <td><input name="damage_charge[' . $iid . ']" value="0"></td>
              <td><input name="missing_charge[' . $iid . ']" value="0"></td></tr>';
        }
        layout('Return', admin_nav() . '<h1>Return for booking #' . $id . '</h1>
          <div class="card"><form method="post">' . csrf_field() . '
          <table class="tbl"><thead><tr><th>Item</th><th>Good</th><th>Damaged</th><th>Missing</th><th>Damage MK</th><th>Missing MK</th></tr></thead>
          <tbody>' . $fr . '</tbody></table>
          ' . field('Actual return date', '<input type="date" name="return_actual" value="' . date('Y-m-d') . '">') . '
          ' . field('Cleaning charges', '<input name="cleaning" value="0">') . '
          ' . field('Forfeited (per terms)', '<input name="forfeited" value="0">') . '
          <button class="btn">Settle return</button></form></div>');
        return;
    }
    $ir = [];
    foreach ($items as $it) {
        $ir[] = [e($it['item_name']), (int) $it['qty'], money((float) $it['rate']), money((float) $it['amount'])];
    }
    $rets = db_all('SELECT * FROM rental_returns WHERE booking_id = ? ORDER BY id', [$id]);
    $rr = [];
    foreach ($rets as $r) {
        $rr[] = ['#' . $r['id'], e($r['return_actual']), money((float) $r['refund_due']), e($r['status'])];
    }
    $actions = '';
    foreach (['Confirmed', 'Dispatched', 'At Customer', 'Return Due', 'Cancelled'] as $s) {
        $actions .= ' <form method="post" action="/admin/rentals?view=' . $id . '" style="display:inline">' . csrf_field() . '
          <input type="hidden" name="set" value="' . $s . '"><button class="btn sec">→ ' . $s . '</button></form>';
    }
    layout('Booking #' . $id, admin_nav() . '<h1>Booking #' . $id . ' ' . brand_badge($b['company']) . '</h1>
      <p class="mut">' . e($b['customer']) . ' · ' . e($b['event_date']) . ' → ' . e($b['return_expected']) . ' · '
      . e($b['status']) . ' · deposit ' . money((float) $b['deposit_received']) . '/' . money((float) $b['deposit_required']) . '</p>
      <p>' . $actions . ' <form method="post" action="/admin/rentals?view=' . $id . '" style="display:inline">' . csrf_field() . '
        <input type="hidden" name="make_invoice" value="1"><button class="btn sec">Create invoice</button></form>
        <a class="btn" href="/admin/rentals?view=' . $id . '&return=1">Record return</a></p>
      <h2>Items</h2>' . table(['Item', 'Qty', 'Rate', 'Amount'], $ir) . '
      <h2>Returns</h2>' . ($rr ? table(['#', 'Date', 'Refund', 'Status'], $rr) : '<p class="mut">None.</p>'));
}

// ---------- Payment verification ----------
function pg_admin_payments(): void
{
    $u = require_role(['admin', 'accounts']);
    if (($_GET['approve'] ?? '') !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $d = db_one('SELECT * FROM payment_declarations WHERE id = ?', [(int) $_POST['id']]);
        if (!$d || !in_array($d['status'], ['Submitted', 'Pending Verification'], true)) {
            exit('Declaration cannot be approved.');
        }
        guard_company(company_name((int) $d['company_id']));
        $kind = post('kind', 'invoice');
        db()->beginTransaction();
        db_exec(
            "INSERT INTO payments (company_id, customer_id, invoice_id, kind, amount, method, reference, payment_date, proof_path, sender_detail, received_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [(int) $d['company_id'], (int) $d['customer_id'], $d['invoice_id'] ?: null, $kind,
             (float) $d['amount'], $d['method'], $d['reference'], $d['payment_date'],
             $d['proof_path'], $d['sender_detail'], (int) $u['id']]
        );
        $pid = db_last_id();
        if ($d['invoice_id']) {
            refresh_invoice((int) $d['invoice_id']);
        }
        if ($kind === 'deposit') {
            if ($d['ref_type'] === 'rental_booking') {
                db_exec('UPDATE rental_bookings SET deposit_received = deposit_received + ? WHERE id = ?', [(float) $d['amount'], (int) $d['ref_id']]);
            }
            if ($d['ref_type'] === 'cake_order') {
                db_exec('UPDATE cake_orders SET deposit_received = deposit_received + ? WHERE id = ?', [(float) $d['amount'], (int) $d['ref_id']]);
            }
        }
        db_exec("UPDATE payment_declarations SET status='Approved', verified_by=?, payment_id=? WHERE id=?", [(int) $u['id'], $pid, (int) $d['id']]);
        db()->commit();
        flash('Approved. Payment #' . $pid . ' posted.');
        redirect('/admin/payments');
    }
    if (($_GET['reject'] ?? '') !== '') {
        db_exec("UPDATE payment_declarations SET status='Rejected', verified_by=? WHERE id=?", [(int) $u['id'], (int) $_GET['reject']]);
        flash('Declaration rejected (no ledger posting).');
        redirect('/admin/payments');
    }
    $rows = db_all(
        'SELECT d.*, c.name AS company, k.name AS customer FROM payment_declarations d
         JOIN companies c ON c.id=d.company_id JOIN customers k ON k.id=d.customer_id
         WHERE d.status IN (?,?) ORDER BY d.id DESC LIMIT 100', ['Submitted', 'Pending Verification']
    );
    $tr = [];
    foreach ($rows as $r) {
        $proof = $r['proof_path'] ? ' <a href="/' . e($r['proof_path']) . '" target="_blank">proof</a>' : '';
        $tr[] = [brand_badge($r['company']), '#' . $r['id'] . ' ' . e($r['customer']),
                 money((float) $r['amount']) . '<br>' . e($r['method']) . '<br>' . e($r['reference']) . $proof,
                 e($r['ref_type']) . ' #' . (int) $r['ref_id'],
                 '<form method="post" action="/admin/payments?approve=1">' . csrf_field() . '
                   <input type="hidden" name="id" value="' . (int) $r['id'] . '">
                   <select name="kind"><option value="invoice">Against invoice</option><option value="deposit">Deposit (liability)</option></select>
                   <button class="btn">Approve</button></form>
                  <p><a href="/admin/payments?reject=' . (int) $r['id'] . '">Reject</a></p>'];
    }
    layout('Payments', admin_nav() . '<h1>Payment verification</h1><p class="mut">Approve posts a payment; Reject posts nothing.</p>' .
        ($tr ? table(['Brand', 'Declaration', 'Amount', 'Against', ''], $tr) : '<p class="mut">Nothing pending.</p>'));
}

// ---------- Orders / Items / Users / Reports ----------
function pg_admin_orders(): void
{
    require_staff();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deliver'])) {
        check_csrf();
        $o = db_one('SELECT * FROM sales_orders WHERE id = ?', [(int) $_POST['deliver']]);
        $flow = ['Pending Arrangement' => 'Arranged', 'Arranged' => 'Out for Delivery', 'Out for Delivery' => 'Delivered'];
        if ($o && isset($flow[$o['delivery_status']])) {
            db_exec('UPDATE sales_orders SET delivery_status=? WHERE id=?', [$flow[$o['delivery_status']], $o['id']]);
        }
        redirect('/admin/orders');
    }
    $rows = db_all(
        'SELECT o.*, c.name AS company, k.name AS customer FROM sales_orders o
         JOIN companies c ON c.id=o.company_id JOIN customers k ON k.id=o.customer_id
         ORDER BY o.id DESC LIMIT 100'
    );
    $tr = [];
    foreach ($rows as $r) {
        $next = $r['delivery_status'] !== 'Delivered' && $r['delivery_status'] !== 'Not Required'
            ? ' <form method="post" style="display:inline">' . csrf_field() . '<input type="hidden" name="deliver" value="' . (int) $r['id'] . '"><button class="btn sec">advance</button></form>' : '';
        $tr[] = [brand_badge($r['company']), '#' . $r['id'], e($r['customer']), money((float) $r['grand_total']),
                 e($r['fulfilment_method']) . ' / ' . e($r['delivery_status']) . $next, e($r['status'])];
    }
    layout('Orders', admin_nav() . section_tabs([['/admin/orders', 'Orders'], ['/admin/quotations', 'Quotations'], ['/admin/invoices', 'Invoices']]) . '<h1>Sales orders</h1>' .
        ($tr ? table(['Brand', '#', 'Customer', 'Total', 'Delivery', 'Status'], $tr) : '<p class="mut">No orders.</p>'));
}

function pg_admin_items(): void
{
    $u = require_role(['admin', 'accounts', 'creations_staff', 'delights_ops']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['adjust'] ?? '') !== '') {
        check_csrf();
        $it = db_one('SELECT * FROM items WHERE id = ?', [(int) $_GET['adjust']]);
        $wh = db_one('SELECT * FROM warehouses WHERE id = ?', [(int) post('warehouse_id')]);
        if (!$it || !$wh) {
            exit('Unknown item/warehouse.');
        }
        guard_company(company_name((int) $wh['company_id']));
        try {
            post_stock((int) $it['id'], (int) $wh['id'], (float) post('qty'), 'adjustment', null, post('notes'));
            flash('Stock adjusted.');
        } catch (Throwable $ex) {
            flash($ex->getMessage(), 'err');
        }
        redirect('/admin/items');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['new'] ?? '') === '1') {
        check_csrf();
        $img = save_upload('image', 'products');
        db_exec(
            'INSERT INTO items (sku, name, item_group_id, company_id, uom, item_type, business_unit, cost, price, replacement_rate, stock_qty, default_warehouse_id, published, image_path, description)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [post('sku'), post('name'), (int) post('item_group_id') ?: null, (int) post('company_id') ?: null,
             post('uom', 'Pc'), post('item_type', 'stock'), post('business_unit', 'Shared'),
             (float) post('cost'), (float) post('price'), (float) post('replacement_rate'),
             (float) post('stock_qty'), (int) post('warehouse_id') ?: null, post('published') ? 1 : 0, $img, post('description')]
        );
        flash('Item created.');
        redirect('/admin/items');
    }
    $rows = db_all(
        'SELECT i.*, g.name AS grp, c.name AS company FROM items i
         LEFT JOIN item_groups g ON g.id=i.item_group_id LEFT JOIN companies c ON c.id=i.company_id
         ORDER BY i.name LIMIT 200'
    );
    $tr = [];
    $whs = db_all('SELECT w.*, c.name AS company FROM warehouses w JOIN companies c ON c.id=w.company_id WHERE w.is_group = 0');
    $whOpts = '';
    foreach ($whs as $w) {
        $whOpts .= '<option value="' . (int) $w['id'] . '">' . e($w['name']) . '</option>';
    }
    $wf = (int) get_param('w');
    $bal = [];
    if ($wf) {
        foreach (db_all('SELECT item_id, COALESCE(SUM(qty_change),0) AS b FROM stock_moves WHERE warehouse_id = ? GROUP BY item_id', [$wf]) as $br) {
            $bal[(int) $br['item_id']] = (float) $br['b'];
        }
    }
    $fchips = '<div class="chips"><a href="/admin/items" class="' . ($wf ? '' : 'on') . '">All warehouses</a>';
    foreach ($whs as $x) {
        $fchips .= '<a href="/admin/items?w=' . (int) $x['id'] . '" class="' . ($wf === (int) $x['id'] ? 'on' : '') . '">' . e($x['name']) . '</a>';
    }
    $fchips .= '</div>';
    foreach ($rows as $r) {
        $thumb = !empty($r['image_path']) ? item_img($r['image_path'], $r['name']) : '';
        $here = $wf ? e((string) ($bal[(int) $r['id']] ?? 0)) : '<span class="mut">—</span>';
        $tr[] = [e($r['sku']), $thumb . e($r['name']) . '<br><span class="mut">' . e((string) ($r['grp'] ?? '')) . ' · ' . e($r['item_type']) . '</span>',
                 $here, e((string) $r['stock_qty']), money((float) $r['price']),
                 '<form method="post" action="/admin/items?adjust=' . (int) $r['id'] . '">' . csrf_field() . '
                   <input name="qty" placeholder="+/- qty" size="8">
                   <select name="warehouse_id">' . $whOpts . '</select>
                   <button class="btn sec">Adjust</button></form>'];
    }
    $groups = db_all('SELECT * FROM item_groups ORDER BY name');
    $gopts = '';
    foreach ($groups as $g) {
        $gopts .= '<option value="' . (int) $g['id'] . '">' . e($g['name']) . '</option>';
    }
    $comps = db_all('SELECT * FROM companies');
    $copts = '<option value="">— shared —</option>';
    foreach ($comps as $c) {
        $copts .= '<option value="' . (int) $c['id'] . '">' . e($c['name']) . '</option>';
    }
    layout('Items', admin_nav() . '<h1>Items &amp; stock</h1>' . $fchips .
        table(['SKU', 'Item', 'Here', 'Stock', 'Price', 'Adjust'], $tr) . '
        <h2>New item</h2><div class="card"><form method="post" action="/admin/items?new=1" enctype="multipart/form-data">' . csrf_field() . '
        ' . field('SKU', '<input name="sku" required>') . field('Name', '<input name="name" required>') . '
        ' . field('Group', '<select name="item_group_id">' . $gopts . '</select>') . '
        ' . field('Company', '<select name="company_id">' . $copts . '</select>') . '
        ' . field('Type', '<select name="item_type"><option value="stock">Stock</option><option value="rental">Rental</option><option value="service">Service</option></select>') . '
        ' . field('Business unit', '<select name="business_unit"><option>Creations</option><option>Delights</option><option>Shared</option></select>') . '
        <div class="row2">' . field('Cost', '<input name="cost" value="0">') . field('Price', '<input name="price" value="0">') . '</div>
        ' . field('Opening stock', '<input name="stock_qty" value="0">') . '
        ' . field('Warehouse', '<select name="warehouse_id">' . $whOpts . '</select>') . '
        ' . field('Photo (website)', '<input type="file" name="image" accept="image/*">') . '
        ' . field('Published on website', '<select name="published"><option value="0">No</option><option value="1">Yes</option></select>') . '
        <button class="btn">Create item</button></form></div>');
}

function pg_admin_reports(): void
{
    require_role(['admin', 'accounts']);
    $sales = db_all(
        'SELECT c.name AS company, COUNT(*) n, COALESCE(SUM(o.grand_total),0) t
         FROM sales_orders o JOIN companies c ON c.id=o.company_id GROUP BY c.name'
    );
    $sr = [];
    foreach ($sales as $s) {
        $sr[] = [brand_badge($s['company']), (int) $s['n'], money((float) $s['t'])];
    }
    $util = db_all(
        "SELECT t.name, t.stock_qty AS owned, COUNT(b.id) AS bookings, COALESCE(SUM(i.qty),0) AS units_booked
         FROM items t LEFT JOIN rental_booking_items i ON i.item_id=t.id
         LEFT JOIN rental_bookings b ON b.id=i.booking_id AND b.status IN ('Confirmed','Dispatched','At Customer','Return Due')
         WHERE t.item_type='rental' GROUP BY t.id ORDER BY units_booked DESC"
    );
    $ur = [];
    foreach ($util as $x) {
        $ur[] = [e($x['name']), e((string) $x['owned']), (int) $x['bookings'], (int) $x['units_booked']];
    }
    $bal = db_all(
        'SELECT k.name AS customer, c.name AS company, COALESCE(SUM(i.total - i.paid),0) AS bal
         FROM invoices i JOIN customers k ON k.id=i.customer_id JOIN companies c ON c.id=i.company_id
         GROUP BY k.id, c.id HAVING bal > 0 ORDER BY bal DESC LIMIT 50'
    );
    $br = [];
    foreach ($bal as $x) {
        $br[] = [e($x['customer']), brand_badge($x['company']), money((float) $x['bal'])];
    }
    $decl = db_all(
        'SELECT status, COUNT(*) n, COALESCE(SUM(amount),0) t FROM payment_declarations GROUP BY status'
    );
    $dr = [];
    foreach ($decl as $d) {
        $dr[] = [e($d['status']), (int) $d['n'], money((float) $d['t'])];
    }
    $pm = db_all(
        'SELECT kind, method, COUNT(*) n, COALESCE(SUM(amount),0) t FROM payments GROUP BY kind, method ORDER BY kind, method'
    );
    $pr = [];
    foreach ($pm as $p) {
        $pr[] = [e($p['kind']), e($p['method']), (int) $p['n'], money((float) $p['t'])];
    }
    layout('Reports', admin_nav() . '<h1>Reports</h1>
      <h2>Sales by company</h2>' . ($sr ? table(['Company', 'Orders', 'Total MK'], $sr) : '<p class="mut">No sales.</p>') . '
      <h2>Rental utilization</h2>' . ($ur ? table(['Equipment', 'Owned', 'Active bookings', 'Units out'], $ur) : '<p class="mut">No rental items.</p>') . '
      <h2>Customer balances &gt; 0</h2>' . ($br ? table(['Customer', 'Company', 'Balance'], $br) : '<p class="mut">All settled.</p>') . '
      <h2>Payment declarations by status</h2>' . ($dr ? table(['Status', 'Count', 'Total MK'], $dr) : '<p class="mut">None.</p>') . '
      <h2>Posted payments</h2>' . ($pr ? table(['Kind', 'Method', 'Count', 'Total MK'], $pr) : '<p class="mut">None.</p>'));
}
