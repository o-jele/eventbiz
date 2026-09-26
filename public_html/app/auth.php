<?php
// Auth + company-aware guards (SPEC §10, §14).
declare(strict_types=1);

const STAFF_ROLES = ['admin', 'accounts', 'creations_staff', 'delights_sales', 'delights_ops'];

const COMPANY_SCOPE = [
    'creations_staff' => ['Glamorous Creations'],
    'delights_sales' => ['Glamorous Delights'],
    'delights_ops' => ['Glamorous Delights'],
    'accounts' => ['Glamorous Creations', 'Glamorous Delights'],
    'admin' => ['Glamorous Creations', 'Glamorous Delights'],
];

function current_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $cache = null;
    if ($cache === null) {
        $cache = db_one('SELECT * FROM users WHERE id = ? AND active = 1', [(int) $_SESSION['uid']]) ?: null;
    }
    return $cache;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    return $u;
}

function require_role(array $roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden for your role.');
    }
    return $u;
}

function require_staff(): array
{
    return require_role(STAFF_ROLES);
}

/** Second-layer guard: staff role must cover the document's company. */
function guard_company(string $companyName): void
{
    $u = current_user();
    if (!$u) {
        return;
    }
    if ($u['role'] === 'admin') {
        return;
    }
    $scoped = array_intersect([$u['role']], array_keys(COMPANY_SCOPE));
    if ($scoped && !in_array($companyName, COMPANY_SCOPE[$u['role']] ?? [], true)) {
        http_response_code(403);
        exit('Not permitted for company ' . htmlspecialchars($companyName) . '.');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
}

function logout_user(): void
{
    unset($_SESSION['uid']);
    session_regenerate_id(true);
}

/** Company id this staff member is scoped to, or null for all-companies roles. */
function staff_company_id(): ?int
{
    $u = current_user();
    if (!$u || in_array($u['role'], ['admin', 'accounts'], true)) {
        return null;
    }
    $map = [
        'creations_staff' => 'Glamorous Creations',
        'delights_sales' => 'Glamorous Delights',
        'delights_ops' => 'Glamorous Delights',
    ];
    if (!isset($map[$u['role']])) {
        return null;
    }
    $row = db_one('SELECT id FROM companies WHERE name = ?', [$map[$u['role']]]);
    return $row ? (int) $row['id'] : null;
}
