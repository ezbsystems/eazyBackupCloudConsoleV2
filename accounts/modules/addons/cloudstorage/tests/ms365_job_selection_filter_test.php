<?php

declare(strict_types=1);

/**
 * Parity tests for ms365_job_selection.js inventory filter helpers.
 */

function normalizeSearchTokens(string $searchQuery): array
{
    $q = strtolower(trim($searchQuery));
    if ($q === '') {
        return [];
    }
    return array_values(array_filter(preg_split('/\s+/', $q) ?: [], static fn ($t) => $t !== ''));
}

function nodeSearchableText(array $node): string
{
    $parts = [$node['label'] ?? '', $node['subtitle'] ?? ''];
    if (!empty($node['resourceType'])) {
        $parts[] = $node['resourceType'];
    }
    $parts = array_filter($parts, static fn ($p) => $p !== '');
    return strtolower(implode(' ', $parts));
}

function textMatchesTokens(string $text, array $tokens): bool
{
    if ($tokens === []) {
        return true;
    }
    foreach ($tokens as $token) {
        if (!str_contains($text, $token)) {
            return false;
        }
    }
    return true;
}

function getDescendants(array $sectionNodes, string $parentKey): array
{
    return array_values(array_filter($sectionNodes, static fn ($n) => ($n['parentKey'] ?? '') === $parentKey));
}

function branchSearchableText(array $node, array $descendants): string
{
    $parts = [nodeSearchableText($node)];
    foreach ($descendants as $child) {
        $parts[] = nodeSearchableText($child);
    }
    return implode(' ', $parts);
}

function nodeMatchesQuery(array $node, array $descendants, array $tokens): bool
{
    return textMatchesTokens(branchSearchableText($node, $descendants), $tokens);
}

function isFlatSection(array $sectionNodes): bool
{
    if ($sectionNodes === []) {
        return false;
    }
    foreach ($sectionNodes as $node) {
        if (($node['depth'] ?? 0) !== 0 || !empty($node['hasChildren'])) {
            return false;
        }
    }
    return true;
}

function visibleNodes(array $sectionNodes, string $searchQuery, array $expandedKeys): array
{
    $tokens = normalizeSearchTokens($searchQuery);

    if ($tokens === []) {
        return array_values(array_filter($sectionNodes, static function ($node) use ($sectionNodes, $expandedKeys) {
            if (($node['depth'] ?? 0) === 0) {
                return true;
            }
            $parentKey = $node['parentKey'] ?? '';
            foreach ($sectionNodes as $parent) {
                if (($parent['key'] ?? '') === $parentKey) {
                    return !empty($expandedKeys[$parentKey]);
                }
            }
            return false;
        }));
    }

    if (isFlatSection($sectionNodes)) {
        return array_values(array_filter(
            $sectionNodes,
            static fn ($node) => textMatchesTokens(nodeSearchableText($node), $tokens)
        ));
    }

    $visible = [];
    foreach ($sectionNodes as $node) {
        if (($node['depth'] ?? 0) !== 0) {
            continue;
        }
        $children = getDescendants($sectionNodes, $node['key']);
        if (!nodeMatchesQuery($node, $children, $tokens)) {
            continue;
        }
        $visible[] = $node;
        if (empty($node['hasChildren'])) {
            continue;
        }
        $parentMatches = textMatchesTokens(nodeSearchableText($node), $tokens);
        $branchMatches = textMatchesTokens(branchSearchableText($node, $children), $tokens);
        foreach ($children as $child) {
            $childText = nodeSearchableText($child);
            $childMatchesAll = textMatchesTokens($childText, $tokens);
            $childMatchesAny = false;
            foreach ($tokens as $token) {
                if (str_contains($childText, $token)) {
                    $childMatchesAny = true;
                    break;
                }
            }
            if ($parentMatches || $childMatchesAll || ($branchMatches && $childMatchesAny)) {
                $visible[] = $child;
            }
        }
    }

    return $visible;
}

function assertCount(int $expected, array $actual, string $label): void
{
    $count = count($actual);
    if ($count !== $expected) {
        fwrite(STDERR, "FAIL {$label}: expected {$expected} nodes, got {$count}\n");
        exit(1);
    }
}

function assertContainsKey(array $nodes, string $key, string $label): void
{
    foreach ($nodes as $node) {
        if (($node['key'] ?? '') === $key) {
            return;
        }
    }
    fwrite(STDERR, "FAIL {$label}: missing node key {$key}\n");
    exit(1);
}

function assertNotContainsKey(array $nodes, string $key, string $label): void
{
    foreach ($nodes as $node) {
        if (($node['key'] ?? '') === $key) {
            fwrite(STDERR, "FAIL {$label}: unexpected node key {$key}\n");
            exit(1);
        }
    }
}

$userSection = [
    ['key' => 'parent:u1', 'label' => 'Jane Doe', 'subtitle' => 'jane@contoso.com', 'depth' => 0, 'hasChildren' => true, 'parentKey' => ''],
    ['key' => 'cap:u1:mail', 'label' => 'Mail', 'subtitle' => '', 'depth' => 1, 'hasChildren' => false, 'parentKey' => 'parent:u1'],
    ['key' => 'cap:u1:calendar', 'label' => 'Calendar', 'subtitle' => '', 'depth' => 1, 'hasChildren' => false, 'parentKey' => 'parent:u1'],
];

$teamSection = [
    ['key' => 'parent:t1', 'label' => 'Engineering', 'subtitle' => '', 'depth' => 0, 'hasChildren' => true, 'parentKey' => ''],
    ['key' => 'res:ch1', 'label' => 'General', 'subtitle' => '', 'resourceType' => 'team_channel', 'depth' => 1, 'hasChildren' => false, 'parentKey' => 'parent:t1'],
    ['key' => 'res:ch2', 'label' => 'Random', 'subtitle' => '', 'resourceType' => 'team_channel', 'depth' => 1, 'hasChildren' => false, 'parentKey' => 'parent:t1'],
];

$siteSection = [
    ['key' => 'parent:s1', 'label' => 'Marketing Site', 'subtitle' => '', 'depth' => 0, 'hasChildren' => true, 'parentKey' => ''],
    ['key' => 'cap:s1:files', 'label' => 'Files', 'subtitle' => '', 'depth' => 1, 'hasChildren' => false, 'parentKey' => 'parent:s1'],
];

$flatSection = [
    ['key' => 'leaf:p1', 'label' => 'Q1 Roadmap', 'subtitle' => 'planner_plan', 'depth' => 0, 'hasChildren' => false, 'parentKey' => ''],
    ['key' => 'leaf:p2', 'label' => 'Q2 Roadmap', 'subtitle' => 'planner_plan', 'depth' => 0, 'hasChildren' => false, 'parentKey' => ''],
];

// Empty query respects expand state.
$collapsed = visibleNodes($userSection, '', []);
assertCount(1, $collapsed, 'collapsed tree shows parent only');
$expanded = visibleNodes($userSection, '', ['parent:u1' => true]);
assertCount(3, $expanded, 'expanded tree shows parent and children');

// User display name match shows workloads.
$jane = visibleNodes($userSection, 'jane', []);
assertCount(3, $jane, 'user name match expands workloads');
assertContainsKey($jane, 'cap:u1:mail', 'user mail workload visible');

// Site match.
$site = visibleNodes($siteSection, 'marketing', []);
assertCount(2, $site, 'site match shows parent and files');

// Team channel match shows parent and only matching channel.
$channel = visibleNodes($teamSection, 'general', []);
assertCount(2, $channel, 'channel match shows parent and channel');
assertContainsKey($channel, 'res:ch1', 'matching channel visible');
assertNotContainsKey($channel, 'res:ch2', 'non-matching channel hidden');

// Multi-token query.
$multi = visibleNodes($userSection, 'jane mail', []);
assertCount(2, $multi, 'multi-token narrows to matching child');
assertContainsKey($multi, 'cap:u1:mail', 'mail child matches multi-token');
assertNotContainsKey($multi, 'cap:u1:calendar', 'calendar excluded by multi-token');

// Flat section filters leaves individually.
$planner = visibleNodes($flatSection, 'q2', []);
assertCount(1, $planner, 'flat section filters one leaf');
assertContainsKey($planner, 'leaf:p2', 'q2 planner visible');

echo "ms365_job_selection_filter_test: OK\n";
