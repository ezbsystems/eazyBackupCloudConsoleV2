<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

class CloudBackupSourceController
{
    /**
     * Create or update a Google Drive source connection
     */
    public static function createOrUpdateGoogleSource(int $clientId, string $displayName, ?string $email, string $scopes, string $refreshTokenEnc, array $meta = []): array
    {
        try {
            $existing = Capsule::table('s3_cloudbackup_sources')
                ->where('client_id', $clientId)
                ->where('provider', 'google_drive')
                ->when($email, function ($q) use ($email) {
                    return $q->where('account_email', $email);
                })
                ->first();

            $now = date('Y-m-d H:i:s');
            $metaJson = json_encode($meta);

            if ($existing) {
                $update = [
                    'display_name' => $displayName,
                    'account_email' => $email,
                    'scopes' => $scopes,
                    'status' => 'active',
                    'meta' => $metaJson,
                    'updated_at' => $now,
                ];
                if (!empty($refreshTokenEnc)) {
                    $update['refresh_token_enc'] = $refreshTokenEnc;
                }
                Capsule::table('s3_cloudbackup_sources')
                    ->where('id', $existing->id)
                    ->update($update);
                return ['status' => 'success', 'id' => (int)$existing->id];
            } else {
                $id = Capsule::table('s3_cloudbackup_sources')->insertGetId([
                    'client_id' => $clientId,
                    'provider' => 'google_drive',
                    'display_name' => $displayName,
                    'account_email' => $email,
                    'scopes' => $scopes,
                    'refresh_token_enc' => $refreshTokenEnc,
                    'status' => 'active',
                    'meta' => $metaJson,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                return ['status' => 'success', 'id' => (int)$id];
            }
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'createOrUpdateGoogleSource', ['client_id' => $clientId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to persist source'];
        }
    }

    public static function listSourcesForClient(int $clientId, string $provider): array
    {
        try {
            $rows = Capsule::table('s3_cloudbackup_sources')
                ->where('client_id', $clientId)
                ->where('provider', $provider)
                ->orderBy('updated_at', 'desc')
                ->get(['id', 'display_name', 'account_email', 'status']);
            return array_map(function ($r) {
                return [
                    'id' => (int)$r->id,
                    'display_name' => $r->display_name,
                    'account_email' => $r->account_email,
                    'status' => $r->status,
                ];
            }, $rows->toArray());
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'listSourcesForClient', ['client_id' => $clientId, 'provider' => $provider], $e->getMessage());
            return [];
        }
    }

    public static function getSource(int $id, int $clientId): ?array
    {
        try {
            $row = Capsule::table('s3_cloudbackup_sources')
                ->where('id', $id)
                ->where('client_id', $clientId)
                ->first();
            return $row ? (array)$row : null;
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'getSource', ['id' => $id, 'client_id' => $clientId], $e->getMessage());
            return null;
        }
    }

    public static function revokeSource(int $id, int $clientId): array
    {
        try {
            Capsule::table('s3_cloudbackup_sources')
                ->where('id', $id)
                ->where('client_id', $clientId)
                ->update([
                    'status' => 'revoked',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            return ['status' => 'success'];
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'revokeSource', ['id' => $id, 'client_id' => $clientId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to revoke source'];
        }
    }
}


