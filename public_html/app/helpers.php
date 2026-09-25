<?php
// Small helpers: escaping, redirects, CSRF, money, uploads.
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid form token. Go back and try again.');
    }
}

function money(float $n): string
{
    return number_format($n, 2);
}

function post(string $k, string $default = ''): string
{
    return trim((string) ($_POST[$k] ?? $default));
}

function get_param(string $k, string $default = ''): string
{
    return trim((string) ($_GET[$k] ?? $default));
}

function flash(string $msg, string $kind = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'kind' => $kind];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Store an uploaded proof/reference image. Returns relative path or null. */
function save_upload(string $field, string $subdir = 'proofs'): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    global $config;
    $dir = rtrim($config['upload_dir'], '/') . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'], true)) {
        exit('Upload must be an image or PDF.');
    }
    if (($_FILES[$field]['size'] ?? 0) > 5 * 1024 * 1024) {
        exit('Upload must be under 5MB.');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name);
    return 'uploads/' . $subdir . '/' . $name;
}

function company_name(int $companyId): string
{
    $row = db_one('SELECT name FROM companies WHERE id = ?', [$companyId]);
    return $row['name'] ?? ('#' . $companyId);
}

function brand_badge(string $companyName): string
{
    $cls = $companyName === GC_NAME ? 'badge-gc' : 'badge-gd';
    $short = $companyName === GC_NAME ? 'Creations' : ($companyName === GD_NAME ? 'Delights' : $companyName);
    return '<span class="badge ' . $cls . '">' . e($short) . '</span>';
}
