<?php
// Business Context — the ONLY route → Company mapping (SPEC §11).
// Business Context = Company + Location + Business Unit.
declare(strict_types=1);

const GC_NAME = 'Glamorous Creations';
const GD_NAME = 'Glamorous Delights';

function business_contexts(): array
{
    return [
        'creations' => [
            'key' => 'creations',
            'company' => GC_NAME,
            'default_warehouse' => 'CREATIONS - SHOP - GC',
            'business_unit' => 'Creations Retail',
        ],
        'delights' => [
            'key' => 'delights',
            'company' => GD_NAME,
            'default_warehouse' => 'DELIGHTS - BAKERY - GD',
            'business_unit' => 'Delights Events',
        ],
    ];
}

function context_key_for_path(string $path): string
{
    $p = strtolower(rtrim($path, '/'));
    if ($p === '') {
        return 'creations'; // homepage neutral; default keeps old links working
    }
    if (preg_match('#^/(creations|shop|products|product|cart|checkout)(/|$)#', $p)) {
        return 'creations';
    }
    if (preg_match('#^/(delights|cakes|fritters|catering|rentals|events|request)(/|$)#', $p)) {
        return 'delights';
    }
    // Admin / portal / auth routes are company-neutral.
    return 'neutral';
}

function business_context(string $path): array
{
    $key = context_key_for_path($path);
    if ($key === 'neutral') {
        return ['key' => 'neutral', 'company' => null];
    }
    return business_contexts()[$key];
}

function company_id(string $companyName): ?int
{
    $row = db_one('SELECT id FROM companies WHERE name = ?', [$companyName]);
    return $row ? (int) $row['id'] : null;
}

function company_id_for_path(string $path): ?int
{
    $ctx = business_context($path);
    if (empty($ctx['company'])) {
        return null;
    }
    return company_id($ctx['company']);
}

function warehouse_for_service(string $serviceType): string
{
    $map = [
        'Bakery' => 'DELIGHTS - BAKERY - GD',
        'Catering' => 'DELIGHTS - CATERING - GD',
        'Rental' => 'DELIGHTS - RENTAL - GD',
    ];
    return $map[$serviceType] ?? 'DELIGHTS - BAKERY - GD';
}
