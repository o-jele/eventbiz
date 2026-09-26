<?php
// Shared HTML layout — Glamorous boutique chrome.
declare(strict_types=1);

// WhatsApp chat button number in international format WITHOUT '+'.
// TODO(go-live): replace with the real business number, e.g. '265991234567'.
const GLAM_WHATSAPP = '';

function layout(string $title, string $body, string $active = ''): void
{
    $u = current_user();
    $flash = take_flash();
    $isStaff = $u && in_array($u['role'], STAFF_ROLES, true);
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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="/">Glamorous<em>.</em></a>
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
</header>
<main class="wrap">
  <?php if ($flash): ?>
    <p class="flash <?= e($flash['kind']) ?>"><?= e($flash['msg']) ?></p>
  <?php endif; ?>
  <?= $body ?>
</main>
<?php if (GLAM_WHATSAPP !== ''): ?>
<a class="wa-float" href="https://wa.me/<?= e(GLAM_WHATSAPP) ?>?text=Hello%20Glamorous!" target="_blank" rel="noopener">Chat to order</a>
<?php endif; ?>
<footer class="foot">
  <div class="foot-inner">
    <div>
      <h4>Glamorous<em style="color:var(--gold)">.</em></h4>
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
