<?php

namespace WHMCS\Module\Addon\CloudStorage\Request;

use Aws\S3\S3Client;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

class S3ClientFactory
{
    /**
     * Build an S3Client using a user's access keys stored in DB (encrypted).
     *
     * @param string $endpoint
     * @param string $region
     * @param int $userId
     * @param string $encryptionKey
     * @return array ['status' => 'success', 'client' => S3Client] or ['status' => 'fail','message'=>...]
     */
    public static function forUser(string $endpoint, string $region, int $userId, string $encryptionKey): array
    {
        $row = Capsule::table('s3_user_access_keys')->where('user_id', $userId)->first();
        if (!$row) {
            return ['status' => 'fail', 'message' => 'Access keys missing.'];
        }

        $accessKey = HelperController::decryptKey($row->access_key, $encryptionKey);
        $secretKey = HelperController::decryptKey($row->secret_key, $encryptionKey);
        if (empty($accessKey) || empty($secretKey)) {
            return ['status' => 'fail', 'message' => 'Access keys invalid.'];
        }

        $config = [
            'version' => 'latest',
            'region' => $region ?: 'us-east-1',
            'endpoint' => $endpoint,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
            'use_path_style_endpoint' => true,
            'signature_version' => 'v4',
        ];

        try {
            $client = new S3Client($config);
            return ['status' => 'success', 'client' => $client];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => 'Storage connection failed.'];
        }
    }
}


