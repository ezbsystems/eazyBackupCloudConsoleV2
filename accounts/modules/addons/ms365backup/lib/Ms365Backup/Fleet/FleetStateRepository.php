<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use WHMCS\Database\Capsule;

final class FleetStateRepository
{
    public static function ensure(): void
    {
        if (!Capsule::schema()->hasTable('ms365_worker_fleet_state')) {
            return;
        }
        if (!Capsule::table('ms365_worker_fleet_state')->where('id', 1)->exists()) {
            Capsule::table('ms365_worker_fleet_state')->insert(['id' => 1, 'updated_at' => time()]);
        }
    }

    /** @return array<string, mixed> */
    public static function get(): array
    {
        self::ensure();
        $row = Capsule::table('ms365_worker_fleet_state')->where('id', 1)->first();

        return $row ? (array) $row : ['id' => 1];
    }

  public static function setTargetRelease(int $releaseId, string $strategy, bool $force, ?string $canaryNodeId, ?int $deployJobId): void
    {
        self::ensure();
        Capsule::table('ms365_worker_fleet_state')->where('id', 1)->update([
            'target_release_id' => $releaseId,
            'deploy_strategy' => $strategy,
            'deploy_force' => $force ? 1 : 0,
            'canary_node_id' => $canaryNodeId,
            'active_deploy_job_id' => $deployJobId,
            'updated_at' => time(),
        ]);
    }

    public static function clearDeploy(): void
    {
        self::ensure();
        Capsule::table('ms365_worker_fleet_state')->where('id', 1)->update([
            'target_release_id' => null,
            'active_deploy_job_id' => null,
            'canary_node_id' => null,
            'deploy_force' => 0,
            'updated_at' => time(),
        ]);
    }
}
