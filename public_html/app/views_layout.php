<?php
// Shared HTML layout — Glamorous boutique chrome.
declare(strict_types=1);

function layout(string $title, string $body, string $active = ''): void
{
    $u = current_user();
    $flash = take_flash();
    $isStaff = $u && in_array($u['role'], STAFF_ROLES, true);
    // Brand mark follows the business context: each subsidiary shows its own logo.
    $brandPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $brandCtx = function_exists('context_key_for_path') ? context_key_for_path($brandPath) : 'neutral';
    // Staff chrome (sidebar + staff topbar) only inside /admin — the public
    // site, including View-site homepage, always renders full-screen.
    $staffChrome = $isStaff && str_starts_with($brandPath, '/admin');
    $brandHtml = '<a class="brand" href="/">Glamorous<em>.</em></a>';
    if ($brandCtx === 'creations') {
        $brandHtml = '<a class="brand" href="/creations"><img class="brand-logo" src="/assets/img/logo-creations.jpg" alt="Glamorous Creations"></a>';
    } elseif ($brandCtx === 'delights') {
        $brandHtml = '<a class="brand" href="/delights"><img class="brand-logo" src="/assets/img/logo-delights.jpg" alt="Glamorous Delights"></a>';
    }
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Glamorous Creations (baking supplies) and Glamorous Delights (cakes, catering, rentals, events) — Lilongwe, Malawi.">
<title><?= e($title) ?> · Glamorous</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<script>try{var _t=localStorage.getItem("glam-theme");if(_t==="dark"||(!_t&&matchMedia("(prefers-color-scheme: dark)").matches)){document.documentElement.dataset.theme="dark";}document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("theme-toggle");function icons(){var s=document.getElementById("theme-icon-sun"),m=document.getElementById("theme-icon-moon");if(s&&m){var dark=document.documentElement.dataset.theme==="dark";s.style.display=dark?"none":"";m.style.display=dark?"":"none";}}if(b){b.onclick=function(){var d=document.documentElement.dataset.theme==="dark";document.documentElement.dataset.theme=d?"light":"dark";try{localStorage.setItem("glam-theme",d?"light":"dark");}catch(e){}icons();};}icons();});}catch(e){}</script>
</head>
<body<?= $staffChrome ? ' class="has-side"' : '' ?>>
<header class="topbar">
  <?php if ($staffChrome): ?><button id="side-burger" aria-label="Open menu">&#9776;</button><?php endif; ?>
  <?= $brandHtml ?>
  <?php if ($staffChrome): ?>
  <nav class="staff-top">
    <button id="theme-toggle" class="theme-btn icon-btn" title="Toggle dark mode" aria-label="Toggle dark mode">
      <span id="theme-icon-sun"><?= icon('sun') ?></span><span id="theme-icon-moon" style="display:none"><?= icon('moon') ?></span>
    </button>
    <a class="btn sec" href="/"><?= icon('home') ?> View site</a>
  </nav>
  <?php else: ?>
  <nav>
    <a href="/creations/shop">Shop</a>
    <a href="/delights/cakes">Cakes</a>
    <a href="/delights/catering">Catering</a>
    <a href="/delights/rentals">Rentals</a>
    <?php if ($u): ?>
      <a href="/my-glamorous">My Glamorous</a>
      <a href="/password">Account</a>
      <?php if ($isStaff): ?><a href="/admin"><strong>Staff</strong></a><?php endif; ?>
      <a href="/logout" class="nav-cta">Logout</a>
    <?php else: ?>
      <a href="/login">Login</a>
      <a href="/register" class="nav-cta">Join</a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>
</header>
<?php if ($staffChrome && function_exists('staff_sidebar')): ?>
<div class="staff-shell">
  <div class="side-veil" id="side-close"></div>
  <aside class="sidebar" aria-label="Staff navigation"><?= staff_sidebar() ?></aside>
  <div class="staff-main">
<?php endif; ?>
<main class="wrap">
  <?php if ($flash): ?>
    <p class="flash <?= e($flash['kind']) ?>"><?= e($flash['msg']) ?></p>
  <?php endif; ?>
  <?= $body ?>
</main>
<?php if ($staffChrome && function_exists('staff_sidebar')): ?>
  </div>
</div>
<?php endif; ?>
<?php
// WhatsApp chat button: set the number in Staff → Settings (digits, e.g. 265991234567).
$wa = preg_replace('/\D+/', '', setting_get('whatsapp', ''));
if ($wa !== ''): ?>
<a class="wa-float" href="https://wa.me/<?= e($wa) ?>?text=Hello%20Glamorous!" target="_blank" rel="noopener">Chat to order</a>
<?php endif; ?>
<?php if (!$staffChrome): ?>
<footer class="foot">
  <div class="foot-inner">
    <div>
      <div class="foot-logos"><span><img src="/assets/img/logo-creations.jpg" alt="Glamorous Creations"></span><span><img src="/assets/img/logo-delights.jpg" alt="Glamorous Delights"></span></div>
      <p>Baking supplies, custom cakes, catering, rentals &amp; full events — made with love in Lilongwe, Malawi.</p>
    </div>
    <div>
      <h4>Shop</h4>
      <p><a href="/creations/shop">Baking supplies</a><br>
      <a href="/cart">Your cart</a><br>
      <a href="/my-glamorous">Track orders</a></p>
    </div>
    <div>
      <h4>Celebrate</h4>
      <p><a href="/delights/cakes">Wedding &amp; custom cakes</a><br>
      <a href="/delights/catering">Catering</a><br>
      <a href="/delights/rentals">Rentals</a><br>
      <a href="/delights/request">Plan an event</a></p>
    </div>
  </div>
  <p class="base">www.glamorous.mw · Glamorous Creations &amp; Glamorous Delights · Cash, bank transfer &amp; mobile money accepted</p>
</footer>
<?php endif; ?>
</body>
</html>
<?php
}

function field(string $label, string $html): string
{
    return '<label class="fld"><span>' . e($label) . '</span>' . $html . '</label>';
}

function table(array $heads, array $rows): string
{
    $h = '<div class="tbl-wrap"><table class="tbl"><thead><tr>';
    foreach ($heads as $x) {
        $h .= '<th>' . e($x) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr>';
        foreach ($r as $c) {
            $h .= '<td>' . $c . '</td>';
        }
        $h .= '</tr>';
    }
    return $h . '</tbody></table></div>';
}
