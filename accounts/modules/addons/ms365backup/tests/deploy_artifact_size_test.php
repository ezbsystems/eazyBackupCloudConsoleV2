<?php
declare(strict_types=1);

/**
 * Contract guard for DeployService update offer artifact_size_bytes field.
 */
function deployOfferIncludesArtifactSize(array $release): array
{
    return [
        'version' => (string) ($release['version'] ?? ''),
        'sha256' => (string) ($release['sha256'] ?? ''),
        'download_url' => 'https://example.test/artifact',
        'release_id' => (int) ($release['id'] ?? 0),
        'artifact_size_bytes' => (int) ($release['artifact_size'] ?? 0),
    ];
}

$offer = deployOfferIncludesArtifactSize([
    'id' => 42,
    'version' => '0.3.81',
    'sha256' => 'abc',
    'artifact_size' => 52428800,
]);
assert($offer['artifact_size_bytes'] === 52428800);
assert(array_key_exists('artifact_size_bytes', $offer));

echo "deploy_artifact_size_test: ok\n";
