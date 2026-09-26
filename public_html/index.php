<?php
// Front controller. Apache (.htaccess) rewrites everything here;
// Hestia+Nginx: add the try_files rule from docs/hestia-deployment.md.
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/lib.php';
require __DIR__ . '/app/dashcharts.php';
require __DIR__ . '/app/views_layout.php';
require __DIR__ . '/app/controllers_public.php';
require __DIR__ . '/app/controllers_portal.php';
require __DIR__ . '/app/controllers_admin.php';
require __DIR__ . '/app/controllers_admin2.php';

if (!empty($config['__missing'])) {
    http_response_code(500);
    exit('Not installed. Open /install.php in your browser first, then delete it.');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

$routes = [
    '/' => 'pg_home',
    '/creations' => 'pg_creations',
    '/creations/shop' => 'pg_creations',
    '/products' => 'pg_creations',
    '/shop' => 'pg_creations',
    '/cart' => 'pg_cart',
    '/cart/add' => 'pg_cart_add',
    '/cart/remove' => 'pg_cart_remove',
    '/cart/clear' => 'pg_cart_clear',
    '/checkout' => 'pg_checkout',
    '/checkout/success' => 'pg_checkout_success',
    '/delights' => 'pg_delights',
    '/delights/cakes' => 'pg_request',
    '/delights/fritters' => 'pg_request',
    '/delights/catering' => 'pg_request',
    '/delights/rentals' => 'pg_rentals',
    '/delights/events' => 'pg_request',
    '/delights/request' => 'pg_request',
    '/cakes' => 'pg_request',
    '/catering' => 'pg_request',
    '/rentals' => 'pg_rentals',
    '/events' => 'pg_request',
    '/request' => 'pg_request',
    '/register' => 'pg_register',
    '/login' => 'pg_login',
    '/my-glamorous' => 'pg_portal',
    '/declare' => 'pg_declare',
    '/admin' => 'pg_admin',
    '/admin/bakery' => 'pg_admin_bakery',
    '/admin/catering' => 'pg_admin_catering',
    '/admin/pos' => 'pg_admin_pos',
    '/admin/pos/suggest' => 'pg_pos_suggest',
    '/admin/pos/add' => 'pg_pos_add',
    '/admin/pos/update' => 'pg_pos_update',
    '/admin/pos/remove' => 'pg_pos_remove',
    '/admin/pos/customer' => 'pg_pos_customer',
    '/admin/pos/suspend' => 'pg_pos_suspend',
    '/admin/pos/resume' => 'pg_pos_resume',
    '/admin/pos/suspend-delete' => 'pg_pos_suspend_delete',
    '/admin/pos/cancel' => 'pg_pos_cancel',
    '/admin/pos/complete' => 'pg_pos_complete',
    '/admin/purchases' => 'pg_admin_purchases',
    '/admin/transfers' => 'pg_admin_transfers',
    '/admin/invoices' => 'pg_admin_invoices',
    '/password' => 'pg_password',
    '/admin/enquiries' => 'pg_admin_enquiries',
    '/admin/events' => 'pg_admin_events',
    '/admin/rentals' => 'pg_admin_rentals',
    '/admin/payments' => 'pg_admin_payments',
    '/admin/orders' => 'pg_admin_orders',
    '/admin/items' => 'pg_admin_items',
    '/admin/users' => 'pg_admin_users',
    '/admin/reports' => 'pg_admin_reports',
];

if ($path === '/logout') {
    logout_user();
    redirect('/');
}
if (preg_match('#^/product/(\d+)$#', $path, $m)) {
    pg_product((int) $m[1]);
    exit;
}
if (preg_match('#^/invoice/(\d+)$#', $path, $m)) {
    pg_invoice((int) $m[1]);
    exit;
}
if (isset($routes[$path])) {
    $routes[$path]();
    exit;
}
http_response_code(404);
layout('Not found', '<h1>404</h1><p>That page does not exist. <a href="/">Home</a></p>');
