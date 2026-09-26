<?php
// Shared HTML layout.
declare(strict_types=1);

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
<title><?= e($title) ?> · Glamorous</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="/">Glamorous</a>
  <nav>
    <a href="/creations">Creations</a>
    <a href="/creations/shop">Shop</a>
    <a href="/delights">Delights</a>
    <a href="/delights/rentals">Rentals</a>
    <a href="/delights/catering">Catering</a>
    <?php if ($u): ?>
      <a href="/my-glamorous">My Glamorous</a>
      <a href="/password">Account</a>
      <?php if ($isStaff): ?><a href="/admin"><strong>Staff</strong></a><?php endif; ?>
      <a href="/logout">Logout (<?= e($u['name']) ?>)</a>
    <?php else: ?>
      <a href="/login">Login</a>
      <a href="/register">Register</a>
    <?php endif; ?>
  </nav>
</header>
<main class="wrap">
  <?php if ($flash): ?>
    <p class="flash <?= e($flash['kind']) ?>"><?= e($flash['msg']) ?></p>
  <?php endif; ?>
  <?= $body ?>
</main>
<footer class="foot"><small>www.glamorous.mw · Creations (retail) + Delights (events)</small></footer>
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
    $h = '<table class="tbl"><thead><tr>';
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
    return $h . '</tbody></table>';
}
