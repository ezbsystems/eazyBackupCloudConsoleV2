#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 4) . '/init.php';

use WHMCS\Database\Capsule;

$options = getopt('', ['repo-id:', 'status:', 'op-id:', 'limit:']);
$repoId = isset($options['repo-id']) ? (int) $options['repo-id'] : 0;
$status = trim((string) ($options['status'] ?? ''));
$opId = isset($options['op-id']) ? (int) $options['op-id'] : 0;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 20;

if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')) {
    fwrite(STDERR, "s3_kopia_repo_operations table missing\n");
    exit(1);
}

$query = Capsule::table('s3_kopia_repo_operations as op')
    ->join('s3_kopia_repos as r', 'r.id', '=', 'op.repo_id')
    ->select('op.*', 'r.repository_id')
    ->orderBy('op.id', 'desc')
    ->limit($limit);

if ($repoId > 0) {
    $query->where('op.repo_id', $repoId);
}
if ($status !== '') {
    $query->where('op.status', $status);
}
if ($opId > 0) {
    $query->where('op.id', $opId);
}

$rows = $query->get();
if ($rows->isEmpty()) {
    echo "No repo operations matched filters\n";
    exit(0);
}

$now = time();
foreach ($rows as $row) {
    $updatedTs = !empty($row->updated_at) ? strtotime((string) $row->updated_at) : 0;
    $ageSeconds = $updatedTs > 0 ? ($now - $updatedTs) : null;
    $lock = Capsule::schema()->hasTable('s3_kopia_repo_locks')
        ? Capsule::table('s3_kopia_repo_locks')->where('repo_id', (int) $row->repo_id)->first()
        : null;
    $lockExpiry = null;
    $lockTokenMatch = false;
    if ($lock !== null) {
        $lockExpiry = (string) ($lock->expires_at ?? '');
        $lockTokenMatch = (string) ($lock->lock_token ?? '') === (string) ($row->operation_token ?? '');
    }

    echo str_repeat('-', 72) . PHP_EOL;
    echo "op_id: {$row->id}" . PHP_EOL;
    echo "repo_id: {$row->repo_id} ({$row->repository_id})" . PHP_EOL;
    echo "type: {$row->op_type} status: {$row->status} attempts: {$row->attempt_count}" . PHP_EOL;
    if (isset($row->claimed_by_node_id)) {
        echo "claimed_by_node_id: " . ($row->claimed_by_node_id ?: '-') . PHP_EOL;
    }
    echo "created_at: {$row->created_at} updated_at: {$row->updated_at}" . PHP_EOL;
    if ($ageSeconds !== null) {
        echo "age_since_updated_s: {$ageSeconds}" . PHP_EOL;
    }
    if ($lock !== null) {
        echo "lock_token_match: " . ($lockTokenMatch ? 'yes' : 'no') . " expires_at: " . ($lockExpiry ?: '-') . PHP_EOL;
    } else {
        echo "lock: none" . PHP_EOL;
    }
    if (!empty($row->payload_json)) {
        echo "payload_json: {$row->payload_json}" . PHP_EOL;
    }
    if (!empty($row->result_json)) {
        echo "result_json: {$row->result_json}" . PHP_EOL;
    }
}

echo str_repeat('-', 72) . PHP_EOL;
echo "shown: " . count($rows) . PHP_EOL;
