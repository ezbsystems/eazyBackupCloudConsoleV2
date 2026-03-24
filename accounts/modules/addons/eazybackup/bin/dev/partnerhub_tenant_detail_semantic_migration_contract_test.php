<?php

declare(strict_types=1);

/**
 * Contract test: semantic (eb-*) UI coverage for tenant-detail.tpl by functional region.
 *
 * Ensures billing-tab-only eb-btn / eb-input usage cannot satisfy the contract: each major
 * region (profile editor, canonical status, members, storage users, active plans, white label)
 * must carry semantic card/table/field/badge/alert/button markers and must not retain a small
 * set of legacy Tailwind skinning stacks.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_semantic_migration_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$tenantTemplateFile = $moduleRoot . '/templates/whitelabel/tenant-detail.tpl';

$source = @file_get_contents($tenantTemplateFile);
if ($source === false) {
    echo 'FAIL: unable to read tenant detail template file' . PHP_EOL;
    exit(1);
}

$sectionTitleAnchors = [
    'Edit Customer Tenant',
    'Canonical Tenant Status',
    'Tenant Members',
    'Storage Users',
    'Active Plans',
    'White Label Mapping',
];
foreach ($sectionTitleAnchors as $title) {
    if (strpos($source, $title) === false) {
        echo 'FAIL: tenant-detail.tpl missing section title anchor: ' . $title . PHP_EOL;
        exit(1);
    }
}

/**
 * @return array{slice: string, error: string|null}
 */
function eb_ph_find_matching_section_end(string $source, int $sectionStart): ?int
{
    $offset = $sectionStart;
    $depth = 0;

    while (true) {
        $nextOpen = strpos($source, '<section', $offset);
        $nextClose = strpos($source, '</section>', $offset);

        if ($nextClose === false) {
            return null;
        }

        if ($nextOpen !== false && $nextOpen < $nextClose) {
            ++$depth;
            $offset = $nextOpen + strlen('<section');
            continue;
        }

        --$depth;
        if ($depth === 0) {
            return $nextClose;
        }

        $offset = $nextClose + strlen('</section>');
    }
}

/**
 * @return array{slice: string, error: string|null}
 */
function eb_ph_slice_enclosing_section(string $source, string $anchorNeedle, string $guardNeedle): array
{
    $anchor = strpos($source, $anchorNeedle);
    if ($anchor === false) {
        return [
            'slice' => '',
            'error' => "missing anchor marker: {$anchorNeedle}",
        ];
    }

    $sectionStart = strrpos(substr($source, 0, $anchor), '<section');
    if ($sectionStart === false) {
        return [
            'slice' => '',
            'error' => "unable to locate enclosing <section> for anchor: {$anchorNeedle}",
        ];
    }

    $guard = strpos($source, $guardNeedle, $anchor + strlen($anchorNeedle));
    if ($guard === false) {
        return [
            'slice' => '',
            'error' => "missing guard marker after anchor {$anchorNeedle}: {$guardNeedle}",
        ];
    }

    $sectionEnd = eb_ph_find_matching_section_end($source, $sectionStart);
    if ($sectionEnd === null) {
        return [
            'slice' => '',
            'error' => "missing closing </section> for anchor: {$anchorNeedle}",
        ];
    }
    if ($sectionEnd >= $guard) {
        return [
            'slice' => '',
            'error' => "region escaped guard boundary for anchor {$anchorNeedle}: {$guardNeedle}",
        ];
    }

    return [
        'slice' => substr($source, $sectionStart, ($sectionEnd + strlen('</section>')) - $sectionStart),
        'error' => null,
    ];
}

/**
 * @param list<string> $needles
 */
function eb_ph_region_contains_one_of(string $region, array $needles): bool
{
    foreach ($needles as $needle) {
        if (strpos($region, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $needles
 */
function eb_ph_region_contains_all_of(string $region, array $needles): bool
{
    foreach ($needles as $needle) {
        if (strpos($region, $needle) === false) {
            return false;
        }
    }

    return true;
}

/**
 * Build a simple class-attribute matcher using lookaheads so token order does not matter.
 *
 * @param list<string> $classTokens
 */
function eb_ph_class_stack_pattern(array $classTokens): string
{
    $lookaheads = '';
    foreach ($classTokens as $token) {
        $quotedToken = preg_quote($token, '/');
        $lookaheads .= '(?=[^"]*(?:(?<=class=")|(?<=\\s))' . $quotedToken . '(?=\\s|"))';
    }

    return '/class="' . $lookaheads . '[^"]*"/';
}

/** @var list<array{key: string, anchor: string, guard: string}> */
$regions = [
    [
        'key' => 'edit_customer_tenant',
        'anchor' => '>Edit Customer Tenant</h2>',
        'guard' => '>Canonical Tenant Status</h2>',
    ],
    [
        'key' => 'canonical_tenant_status',
        'anchor' => '>Canonical Tenant Status</h2>',
        'guard' => '>Danger Zone</h2>',
    ],
    [
        'key' => 'tenant_members',
        'anchor' => '>Tenant Members</h2>',
        'guard' => "{elseif \$activeTab eq 'storage_users'",
    ],
    [
        'key' => 'storage_users',
        'anchor' => '>Storage Users</h2>',
        'guard' => "{elseif \$activeTab eq 'billing'",
    ],
    [
        'key' => 'active_plans',
        'anchor' => '>Active Plans</h2>',
        'guard' => '>Saved Payment Methods</h2>',
    ],
    [
        'key' => 'white_label_mapping',
        'anchor' => '>White Label Mapping</h2>',
        'guard' => '{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"',
    ],
];

/** @var array<string, array{any_of: list<list<string>>, all_of: list<string>}> */
$requiredSemantics = [
    'edit_customer_tenant' => [
        'any_of' => [
            ['eb-card-raised', 'eb-card'],
            ['eb-field-label'],
            ['eb-input'],
            ['eb-btn'],
        ],
        'all_of' => [
            'name="name"',
            'type="submit"',
        ],
    ],
    'canonical_tenant_status' => [
        'any_of' => [
            ['eb-card-raised', 'eb-card'],
            ['eb-badge'],
        ],
        'all_of' => [],
    ],
    'tenant_members' => [
        'any_of' => [
            ['eb-card-raised', 'eb-card'],
            ['eb-table-shell', 'eb-table'],
            ['eb-badge'],
            ['eb-alert'],
        ],
        'all_of' => [
            '<table',
        ],
    ],
    'storage_users' => [
        'any_of' => [
            ['eb-card-raised', 'eb-card'],
            ['eb-table-shell', 'eb-table'],
            ['eb-badge'],
            ['eb-alert'],
        ],
        'all_of' => [
            '<table',
        ],
    ],
    'active_plans' => [
        'any_of' => [
            ['eb-card-raised', 'eb-card'],
            ['eb-table-shell', 'eb-table'],
            ['eb-badge'],
            ['eb-btn'],
            ['eb-alert'],
        ],
        'all_of' => [
            '<table',
        ],
    ],
    'white_label_mapping' => [
        'any_of' => [
            ['eb-card-raised', 'eb-card'],
            ['eb-btn'],
            ['eb-alert'],
        ],
        'all_of' => [
            'eb-field-label',
            'eb-input',
        ],
    ],
];

/**
 * Legacy skinning stacks to eliminate per region (not generic layout utilities).
 *
 * @var array<string, string>
 */
$legacySkinningForbidden = [
    'raw_outer_section_stack' => eb_ph_class_stack_pattern(['rounded-2xl', 'border', 'border-slate-800/80', 'bg-slate-900/70']),
    'raw_inner_card_stack' => eb_ph_class_stack_pattern(['rounded-2xl', 'border', 'border-slate-800', 'bg-slate-950/50']),
    'raw_field_focus_stack' => eb_ph_class_stack_pattern(['outline-white/10', 'focus-within:outline-sky-700']),
    'raw_rose_danger_shell' => eb_ph_class_stack_pattern(['border-rose-500/30', 'bg-rose-500/5']),
    'raw_sky_primary_button_stack' => eb_ph_class_stack_pattern(['bg-sky-600', 'hover:bg-sky-500']),
];

$failures = [];

foreach ($regions as $region) {
    $key = $region['key'];
    $regionMatch = eb_ph_slice_enclosing_section($source, $region['anchor'], $region['guard']);
    $slice = $regionMatch['slice'];
    if ($regionMatch['error'] !== null) {
        $failures[] = "FAIL: unable to isolate {$key}: {$regionMatch['error']}";
        continue;
    }

    foreach ($legacySkinningForbidden as $patternName => $pattern) {
        if (preg_match($pattern, $slice) === 1) {
            $failures[] = "FAIL: legacy skinning pattern still present in {$key}: {$patternName}";
        }
    }

    if (!isset($requiredSemantics[$key])) {
        $failures[] = "FAIL: internal error — no semantic requirements for {$key}";
        continue;
    }

    $req = $requiredSemantics[$key];
    foreach ($req['any_of'] as $group) {
        if (!eb_ph_region_contains_one_of($slice, $group)) {
            $groupLabel = implode(' | ', $group);
            $failures[] = "FAIL: missing semantic marker in {$key} (need one of: {$groupLabel})";
        }
    }
    foreach ($req['all_of'] as $needle) {
        if (!eb_ph_region_contains_all_of($slice, [$needle])) {
            $failures[] = "FAIL: missing required content in {$key}: {$needle}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-tenant-detail-semantic-migration-contract-ok\n";
exit(0);
