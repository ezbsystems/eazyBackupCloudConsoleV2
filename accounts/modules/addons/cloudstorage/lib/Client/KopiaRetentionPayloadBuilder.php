<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Builds agent-ready payload for repo operations (retention, maintenance).
 * Output includes repo_id, operation_id, operation_token, and effective_policy.
 */
class KopiaRetentionPayloadBuilder
{
    /**
     * Build payload for agent execution.
     *
     * @param int $repoId s3_kopia_repos.id
     * @param int $operationId s3_kopia_repo_operations.id
     * @param string $operationToken operation_token from operation row
     * @param array $effectivePolicy Comet-tier retention map (hourly, daily, weekly, monthly, yearly)
     * @return array Payload for agent
     */
    public static function build(
        int $repoId,
        int $operationId,
        string $operationToken,
        array $effectivePolicy
    ): array {
        return [
            'repo_id' => $repoId,
            'operation_id' => $operationId,
            'operation_token' => $operationToken,
            'effective_policy' => $effectivePolicy,
        ];
    }
}
