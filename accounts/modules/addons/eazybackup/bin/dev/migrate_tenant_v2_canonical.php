<?php

declare(strict_types=1);

/**
 * Backfill eb_whitelabel_tenants.canonical_tenant_id from s3_backup_tenants.
 *
 * Usage:
 *   php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php
 *   php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --dry-run
 *   php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --apply
 *   php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --manual-map=path/to/mapping.json
 */

require __DIR__ . '/../bootstrap.php';

function mtv2_stderr(string $msg): void
{
    fwrite(STDERR, $msg . PHP_EOL);
}

function mtv2_arg_value(array $argv, string $flag): ?string
{
    foreach ($argv as $arg) {
        if (strpos($arg, $flag . '=') === 0) {
            return substr($arg, strlen($flag) + 1);
        }
    }
    return null;
}

function mtv2_has_flag(array $argv, string $flag): bool
{
    return in_array($flag, $argv, true);
}

function mtv2_norm_slug(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/^https?:\/\//', '', $value);
    $value = trim((string) $value, "/ \t\n\r\0\x0B");
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string) $value, '-');
    $value = preg_replace('/-+/', '-', $value);
    return (string) $value;
}

function mtv2_hostname(?string $fqdn): string
{
    $host = strtolower(trim((string) $fqdn));
    if ($host === '') {
        return '';
    }
    $host = preg_replace('/^https?:\/\//', '', $host);
    $host = trim((string) $host, "/ \t\n\r\0\x0B");
    if ($host === '') {
        return '';
    }
    $parts = explode('/', $host, 2);
    return trim((string) ($parts[0] ?? ''));
}

function mtv2_load_manual_map(?string $path): array
{
    if ($path === null || trim($path) === '') {
        return [];
    }
    if (!is_file($path)) {
        throw new RuntimeException("Manual mapping file not found: {$path}");
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Unable to read manual mapping file: {$path}");
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Manual mapping file must contain JSON object/array: {$path}");
    }

    $map = [];
    $isList = array_keys($decoded) === range(0, count($decoded) - 1);
    if ($isList) {
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $wlId = (int) ($row['whitelabel_tenant_id'] ?? 0);
            $canonicalId = (int) ($row['canonical_tenant_id'] ?? 0);
            if ($wlId > 0 && $canonicalId > 0) {
                $map[$wlId] = $canonicalId;
            }
        }
        return $map;
    }

    foreach ($decoded as $wlKey => $target) {
        $wlId = (int) $wlKey;
        if ($wlId <= 0) {
            continue;
        }

        if (is_array($target)) {
            $canonicalId = (int) ($target['canonical_tenant_id'] ?? 0);
        } else {
            $canonicalId = (int) $target;
        }
        if ($canonicalId > 0) {
            $map[$wlId] = $canonicalId;
        }
    }

    return $map;
}

/**
 * @param array<string, list<array<string,mixed>>> $tenantIndex
 * @param list<string> $keys
 * @return array<int, array<string,mixed>>
 */
function mtv2_collect_matches(array $tenantIndex, array $keys): array
{
    $unique = [];
    foreach ($keys as $key) {
        if ($key === '' || !isset($tenantIndex[$key])) {
            continue;
        }
        foreach ($tenantIndex[$key] as $tenant) {
            $id = (int) ($tenant['id'] ?? 0);
            if ($id > 0) {
                $unique[$id] = $tenant;
            }
        }
    }
    return array_values($unique);
}

$hasApply = mtv2_has_flag($argv, '--apply');
$hasDryRun = mtv2_has_flag($argv, '--dry-run');
if ($hasApply && $hasDryRun) {
    mtv2_stderr('ERROR: --apply and --dry-run cannot be used together.');
    exit(2);
}

$dryRun = !$hasApply;
if ($hasDryRun) {
    $dryRun = true;
}
$manualMapPath = mtv2_arg_value($argv, '--manual-map');

try {
    $manualMap = mtv2_load_manual_map($manualMapPath);
} catch (Throwable $e) {
    mtv2_stderr("ERROR: " . $e->getMessage());
    exit(2);
}

$pdo = db();

try {
    $hasWhitelabel = (bool) $pdo->query("SHOW TABLES LIKE 'eb_whitelabel_tenants'")->fetchColumn();
    $hasStorageTenants = (bool) $pdo->query("SHOW TABLES LIKE 's3_backup_tenants'")->fetchColumn();
    if (!$hasWhitelabel || !$hasStorageTenants) {
        mtv2_stderr('ERROR: required tables missing (eb_whitelabel_tenants and/or s3_backup_tenants).');
        exit(1);
    }

    $whitelabelColumns = $pdo->query("SHOW COLUMNS FROM eb_whitelabel_tenants")->fetchAll(PDO::FETCH_ASSOC);
    $hasCanonicalColumn = false;
    foreach ($whitelabelColumns as $col) {
        if (((string) ($col['Field'] ?? '')) === 'canonical_tenant_id') {
            $hasCanonicalColumn = true;
            break;
        }
    }
    if (!$hasCanonicalColumn) {
        mtv2_stderr('ERROR: eb_whitelabel_tenants.canonical_tenant_id is missing. Run addon schema migration first.');
        exit(1);
    }
} catch (Throwable $e) {
    mtv2_stderr('ERROR: schema preflight failed: ' . $e->getMessage());
    exit(1);
}

$wlRows = $pdo->query(
    "SELECT id, client_id, org_id, subdomain, fqdn, canonical_tenant_id
     FROM eb_whitelabel_tenants
     ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$storageRows = $pdo->query(
    "SELECT id, client_id, name, slug, status
     FROM s3_backup_tenants
     WHERE status <> 'deleted'
     ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/** @var array<int, array<string, list<array<string,mixed>>>> $byClient */
$byClient = [];
/** @var array<int, array<int, array<string,mixed>>> $storageByClientId */
$storageByClientId = [];
foreach ($storageRows as $row) {
    $clientId = (int) ($row['client_id'] ?? 0);
    $tenantId = (int) ($row['id'] ?? 0);
    if ($clientId <= 0 || $tenantId <= 0) {
        continue;
    }

    $storageByClientId[$clientId][$tenantId] = $row;

    $slugKeys = [];
    $slugKeys[] = mtv2_norm_slug((string) ($row['slug'] ?? ''));
    $slugKeys[] = mtv2_norm_slug((string) ($row['name'] ?? ''));

    foreach ($slugKeys as $key) {
        if ($key === '') {
            continue;
        }
        if (!isset($byClient[$clientId][$key])) {
            $byClient[$clientId][$key] = [];
        }
        $byClient[$clientId][$key][] = $row;
    }
}

$report = [
    'mode' => $dryRun ? 'DRY-RUN' : 'APPLY',
    'total_whitelabel_tenants' => count($wlRows),
    'already_mapped' => 0,
    'mapped' => [],
    'ambiguous' => [],
    'unmapped' => [],
    'manual_invalid' => [],
];

foreach ($wlRows as $row) {
    $wlId = (int) ($row['id'] ?? 0);
    $clientId = (int) ($row['client_id'] ?? 0);
    $existingCanonical = isset($row['canonical_tenant_id']) ? (int) $row['canonical_tenant_id'] : 0;
    if ($wlId <= 0 || $clientId <= 0) {
        continue;
    }

    if ($existingCanonical > 0) {
        $report['already_mapped']++;
        continue;
    }

    if (isset($manualMap[$wlId])) {
        $manualTarget = (int) $manualMap[$wlId];
        $tenant = $storageByClientId[$clientId][$manualTarget] ?? null;
        if ($tenant === null) {
            $report['manual_invalid'][] = [
                'wl_id' => $wlId,
                'client_id' => $clientId,
                'manual_target' => $manualTarget,
                'reason' => 'target tenant missing or not owned by client',
            ];
            $report['unmapped'][] = [
                'wl_id' => $wlId,
                'client_id' => $clientId,
                'org_id' => (string) ($row['org_id'] ?? ''),
                'subdomain' => (string) ($row['subdomain'] ?? ''),
                'fqdn' => (string) ($row['fqdn'] ?? ''),
                'reason' => 'manual mapping invalid',
            ];
            continue;
        }

        $report['mapped'][] = [
            'wl_id' => $wlId,
            'client_id' => $clientId,
            'canonical_tenant_id' => $manualTarget,
            'source' => 'manual-map',
            'matched_slug' => (string) ($tenant['slug'] ?? ''),
        ];
        continue;
    }

    $clientIndex = $byClient[$clientId] ?? [];
    $orgKey = mtv2_norm_slug((string) ($row['org_id'] ?? ''));
    $phaseOneKeys = [];
    if ($orgKey !== '') {
        $phaseOneKeys[] = $orgKey;
    }
    $phaseOneKeys = array_values(array_unique($phaseOneKeys));
    $phaseOneMatches = mtv2_collect_matches($clientIndex, $phaseOneKeys);

    if (count($phaseOneMatches) === 1) {
        $match = $phaseOneMatches[0];
        $report['mapped'][] = [
            'wl_id' => $wlId,
            'client_id' => $clientId,
            'canonical_tenant_id' => (int) ($match['id'] ?? 0),
            'source' => 'deterministic-org-slug',
            'matched_slug' => (string) ($match['slug'] ?? ''),
        ];
        continue;
    }
    if (count($phaseOneMatches) > 1) {
        $report['ambiguous'][] = [
            'wl_id' => $wlId,
            'client_id' => $clientId,
            'reason' => 'deterministic-org-slug',
            'candidate_ids' => array_values(array_map(static function (array $m): int {
                return (int) ($m['id'] ?? 0);
            }, $phaseOneMatches)),
            'org_id' => (string) ($row['org_id'] ?? ''),
        ];
        continue;
    }

    $subdomainKey = mtv2_norm_slug((string) ($row['subdomain'] ?? ''));
    $hostname = mtv2_hostname((string) ($row['fqdn'] ?? ''));
    $fqdnKey = mtv2_norm_slug($hostname);

    $fqdnFirstLabel = '';
    if ($hostname !== '' && strpos($hostname, '.') !== false) {
        $fqdnFirstLabel = (string) explode('.', $hostname, 2)[0];
    }
    $fqdnFirstLabelKey = mtv2_norm_slug($fqdnFirstLabel);

    $phaseTwoKeys = [];
    foreach ([$subdomainKey, $fqdnKey, $fqdnFirstLabelKey] as $k) {
        if ($k !== '') {
            $phaseTwoKeys[] = $k;
        }
    }
    $phaseTwoKeys = array_values(array_unique($phaseTwoKeys));
    $phaseTwoMatches = mtv2_collect_matches($clientIndex, $phaseTwoKeys);

    if (count($phaseTwoMatches) === 1) {
        $match = $phaseTwoMatches[0];
        $report['mapped'][] = [
            'wl_id' => $wlId,
            'client_id' => $clientId,
            'canonical_tenant_id' => (int) ($match['id'] ?? 0),
            'source' => 'fqdn-subdomain-slug',
            'matched_slug' => (string) ($match['slug'] ?? ''),
        ];
        continue;
    }
    if (count($phaseTwoMatches) > 1) {
        $report['ambiguous'][] = [
            'wl_id' => $wlId,
            'client_id' => $clientId,
            'reason' => 'fqdn-subdomain-slug',
            'candidate_ids' => array_values(array_map(static function (array $m): int {
                return (int) ($m['id'] ?? 0);
            }, $phaseTwoMatches)),
            'subdomain' => (string) ($row['subdomain'] ?? ''),
            'fqdn' => (string) ($row['fqdn'] ?? ''),
        ];
        continue;
    }

    $report['unmapped'][] = [
        'wl_id' => $wlId,
        'client_id' => $clientId,
        'org_id' => (string) ($row['org_id'] ?? ''),
        'subdomain' => (string) ($row['subdomain'] ?? ''),
        'fqdn' => (string) ($row['fqdn'] ?? ''),
        'reason' => 'no slug match',
    ];
}

$applied = 0;
if (!$dryRun && !empty($report['mapped'])) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE eb_whitelabel_tenants
             SET canonical_tenant_id = :canonical_tenant_id
             WHERE id = :id AND client_id = :client_id
               AND (canonical_tenant_id IS NULL OR canonical_tenant_id = 0)"
        );
        foreach ($report['mapped'] as $mapped) {
            $stmt->execute([
                ':canonical_tenant_id' => (int) ($mapped['canonical_tenant_id'] ?? 0),
                ':id' => (int) ($mapped['wl_id'] ?? 0),
                ':client_id' => (int) ($mapped['client_id'] ?? 0),
            ]);
            $applied += $stmt->rowCount();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        mtv2_stderr('ERROR: apply failed: ' . $e->getMessage());
        exit(1);
    }
}

echo "TENANT_V2_CANONICAL_MIGRATION_REPORT\n";
echo "mode=" . $report['mode'] . PHP_EOL;
echo "manual_map=" . ($manualMapPath !== null ? $manualMapPath : '(none)') . PHP_EOL;
echo "total_whitelabel_tenants=" . (int) $report['total_whitelabel_tenants'] . PHP_EOL;
echo "already_mapped=" . (int) $report['already_mapped'] . PHP_EOL;
echo "mapped=" . count($report['mapped']) . PHP_EOL;
echo "ambiguous=" . count($report['ambiguous']) . PHP_EOL;
echo "unmapped=" . count($report['unmapped']) . PHP_EOL;
echo "manual_invalid=" . count($report['manual_invalid']) . PHP_EOL;
if (!$dryRun) {
    echo "applied_updates={$applied}" . PHP_EOL;
    if ($applied !== count($report['mapped'])) {
        mtv2_stderr(
            'ERROR: mapped tenant count does not match applied updates. ' .
            'Review concurrent updates or stale canonical_tenant_id values before retrying.'
        );
        exit(1);
    }
}

echo "\n[MAPPED]\n";
foreach ($report['mapped'] as $m) {
    echo "wl_id=" . (int) $m['wl_id']
        . " client_id=" . (int) $m['client_id']
        . " canonical_tenant_id=" . (int) $m['canonical_tenant_id']
        . " source=" . (string) $m['source']
        . " matched_slug=" . (string) $m['matched_slug']
        . PHP_EOL;
}
if (empty($report['mapped'])) {
    echo "(none)\n";
}

echo "\n[AMBIGUOUS]\n";
foreach ($report['ambiguous'] as $a) {
    $candidateIds = array_map('intval', (array) ($a['candidate_ids'] ?? []));
    echo "wl_id=" . (int) $a['wl_id']
        . " client_id=" . (int) $a['client_id']
        . " reason=" . (string) ($a['reason'] ?? '')
        . " candidate_ids=[" . implode(',', $candidateIds) . "]"
        . PHP_EOL;
}
if (empty($report['ambiguous'])) {
    echo "(none)\n";
}

echo "\n[UNMAPPED]\n";
foreach ($report['unmapped'] as $u) {
    echo "wl_id=" . (int) $u['wl_id']
        . " client_id=" . (int) $u['client_id']
        . " reason=" . (string) ($u['reason'] ?? '')
        . " org_id=" . (string) ($u['org_id'] ?? '')
        . " subdomain=" . (string) ($u['subdomain'] ?? '')
        . " fqdn=" . (string) ($u['fqdn'] ?? '')
        . PHP_EOL;
}
if (empty($report['unmapped'])) {
    echo "(none)\n";
}

if (!empty($report['manual_invalid'])) {
    echo "\n[MANUAL_INVALID]\n";
    foreach ($report['manual_invalid'] as $mi) {
        echo "wl_id=" . (int) $mi['wl_id']
            . " client_id=" . (int) $mi['client_id']
            . " manual_target=" . (int) $mi['manual_target']
            . " reason=" . (string) $mi['reason']
            . PHP_EOL;
    }
}

exit(0);
