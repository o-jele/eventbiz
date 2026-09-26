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

/** Inline SVG icon (stroke, 18px). Keeps the app dependency-free. */
function icon(string $name): string
{
    $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'enquiries' => '<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3,7 12,13 21,7"/>',
        'pos' => '<circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/><path d="M3 3h2l2.4 12.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6"/>',
        'bakery' => '<path d="M4 13h16v8H4z"/><path d="M4 13c0-3 3.5-5 8-5s8 2 8 5"/><line x1="9" y1="8" x2="9" y2="4"/><line x1="15" y1="8" x2="15" y2="4"/>',
        'events' => '<rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
        'catering' => '<path d="M4 17h16"/><path d="M5 17a7 7 0 0 1 14 0"/><line x1="12" y1="10" x2="12" y2="7"/><circle cx="12" cy="5.5" r="1"/>',
        'rentals' => '<path d="M3 8l9-5 9 5v8l-9 5-9-5z"/><path d="M3 8l9 5 9-5"/><line x1="12" y1="13" x2="12" y2="18"/>',
        'payments' => '<rect x="3" y="6" width="18" height="13" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="7" y1="15" x2="11" y2="15"/>',
        'orders' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/>',
        'quotations' => '<path d="M6 3h9l4 4v14H6z"/><polyline points="14,3 14,8 19,8"/><line x1="9" y1="12" x2="16" y2="12"/><line x1="9" y1="16" x2="16" y2="16"/>',
        'invoices' => '<path d="M6 3h9l4 4v14H6z"/><polyline points="14,3 14,8 19,8"/><line x1="9" y1="13" x2="13" y2="13"/>',
        'items' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8.5" cy="8.5" r="1.5"/>',
        'purchasing' => '<path d="M4 9h16l-1.5 9h-13z"/><path d="M4 9l2-5h12l2 5"/><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/>',
        'transfers' => '<polyline points="4,8 8,4 12,8"/><line x1="8" y1="4" x2="8" y2="16"/><polyline points="12,16 16,20 20,16"/><line x1="16" y1="20" x2="16" y2="8"/>',
        'warehouse' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><line x1="10" y1="20" x2="10" y2="14"/><line x1="14" y1="20" x2="14" y2="14"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14.5c2.2.7 4 2.6 4 5.5"/>',
        'reports' => '<line x1="4" y1="20" x2="20" y2="20"/><line x1="7" y1="20" x2="7" y2="14"/><line x1="12" y1="20" x2="12" y2="9"/><line x1="17" y1="20" x2="17" y2="5"/>',
        'settings' => '<line x1="5" y1="7" x2="19" y2="7"/><circle cx="15" cy="7" r="2.5"/><line x1="5" y1="17" x2="19" y2="17"/><circle cx="9" cy="17" r="2.5"/>',
        'home' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'logout' => '<path d="M14 4h5v16h-5"/><path d="M3 12h11"/><polyline points="10,8 14,12 10,16"/>',
        'sun' => '<circle cx="12" cy="12" r="4.5"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.5" y1="4.5" x2="6.5" y2="6.5"/><line x1="17.5" y1="17.5" x2="19.5" y2="19.5"/><line x1="4.5" y1="19.5" x2="6.5" y2="17.5"/><line x1="17.5" y1="6.5" x2="19.5" y2="4.5"/>',
        'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>',
    ];
    $inner = $p[$name] ?? $p['dashboard'];
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/** Key-value site settings (settings table). */
function setting_get(string $k, string $default = ''): string
{
    static $cache = [];
    if (!array_key_exists($k, $cache)) {
        try {
            $row = db_one('SELECT v FROM settings WHERE k = ?', [$k]);
        } catch (Throwable $ex) {
            $row = null;
        }
        $cache[$k] = $row ? (string) $row['v'] : $default;
    }
    return $cache[$k];
}

function setting_set(string $k, string $v): void
{
    db_exec('INSERT INTO settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
}

function item_img(?string $path, string $alt = ''): string
{
    if (!$path) {
        return '';
    }
    return '<img src="/' . e($path) . '" alt="' . e($alt) . '" style="max-width:100%;border-radius:8px;margin-bottom:.5rem">';
}
