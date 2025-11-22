<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use DateInterval;
use DatePeriod;
use DateTime;
use WHMCS\Database\Capsule;

class HelperController {

    /**
     * Format size units (bytes to human-readable string).
     *
     * @param int|float $bytes
     * @return string
     */
    public static function formatSizeUnits($bytes)
    {
        if ($bytes >= 1099511627776) {
            $bytes = number_format($bytes / 1099511627776, 2) . ' <span class="size-unit">TiB</span>';
        } elseif ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' <span class="size-unit">GiB</span>';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' <span class="size-unit">MiB</span>';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' <span class="size-unit">KiB</span>';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' <span class="size-unit">Bytes</span>';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' <span class="size-unit">Byte</span>';
        } else {
            $bytes = '0 <span class="size-unit">Bytes</span>';
        }

        return $bytes;
    }

    /**
     * Format size units to a plain text string (no HTML markup).
     *
     * @param int|float $bytes
     * @return string
     */
    public static function formatSizeUnitsPlain($bytes)
    {
        if ($bytes >= 1099511627776) {
            return number_format($bytes / 1099511627776, 2) . ' TiB';
        } elseif ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GiB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MiB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KiB';
        } elseif ($bytes > 1) {
            return $bytes . ' Bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' Byte';
        }
        return '0 Bytes';
    }

    /**
     * Prepare daily usage chart data
     *
     * @return array
     */
    public static function prepareDailyUsageChart($start, $bucketStats)
    {
        if (!is_array($bucketStats) || empty($bucketStats)) {
            return [];
        }

        $usageMap = [];
        foreach ($bucketStats as $row) {
            $usageMap[$row['period']] = $row['total_usage'];
        }

        $start = new DateTime($start);
        $end = new DateTime();

        $dailyUsageChart = [];
        $interval = new DateInterval('P1D');
        $range = new DatePeriod($start, $interval, $end);

        $lastValue = 0;
        foreach ($range as $date) {
            $period = $date->format('Y-m-d');
            if (array_key_exists($period, $usageMap)) {
                $lastValue = $usageMap[$period];
            }
            // Last-value carry-forward for stock-like metric (storage size)
            $dailyUsageChart[] = [
                'period' => $period,
                'total_usage' => $lastValue
            ];
        }

        return $dailyUsageChart;
    }

    /**
     * Sort Bucket
     *
     * @param array $buckets
     *
     * @return array
     */
    public static function sortBucket($buckets)
    {
        if (!is_array($buckets) || empty($buckets)) {
            return [];
        }

        usort($buckets, function($a, $b) {
            return $b['size'] - $a['size'];
        });

        $topBuckets = array_slice($buckets, 0, 10);

        return $topBuckets;
    }

    /**
     * Get Date Ranges
     *
     * @param $type
     *
     * @return array
     */
    public static function getDateRange($type)
    {
        $now = new DateTime();
        $endDate = $now->format('Y-m-d');
        $ago = new DateTime();

        if ($type == 'weekly') {
            $ago->modify('-1 week');
        } else {
            $ago->modify('-1 month');
        }
        $startDate = $ago->format('Y-m-d');

        return ['start' => $startDate, 'end' => $endDate];
    }

    /**
     * Encrypt Key
     *
     * @param string $key
     * @param string $encryptionKey
     * @return string Base64 encoded IV + ciphertext
     */
    public static function encryptKey($key, $encryptionKey)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encryptedKey = openssl_encrypt($key, 'aes-256-cbc', $encryptionKey, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encryptedKey);
    }

    /**
     * Decrypt Key
     *
     * @param string $encryptedKey
     * @param string $encryptionKey
     * @return string
     */
    public static function decryptKey($encryptedKey, $encryptionKey)
    {
        $decodedData = base64_decode($encryptedKey);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decodedData, 0, $ivLength);
        $encryptedKeyData = substr($decodedData, $ivLength);
        $decryptedKey = openssl_decrypt($encryptedKeyData, 'aes-256-cbc', $encryptionKey, OPENSSL_RAW_DATA, $iv);

        return $decryptedKey;
    }

    /**
     * Validate bucket name
     *
     * @param string $bucketName
     * @return bool
     */
    public static function isValidBucketName($bucketName)
    {
        /**
         * Should not start with a hyphen -
         * Should not contain double hyphens --
         * Should not contain double dots ..
         * Should not contain .-
         * Should not contain -.
         * Should contain only lowercase letters, numbers,
         * dots, and hyphens but must end with a letter or number
         */
        $pattern1 = '/^(?!-)(?!.*--)(?!.*\.\.)(?!.*\.-)(?!.*-\.)[a-z0-9-.]*[a-z0-9]$/';

        /**
         * should not start or end with a dot
         */
        $pattern2 = '/^\.|\\.$/';

        // Perform validation
        return preg_match($pattern1, $bucketName) && !preg_match($pattern2, $bucketName);
    }

    /**
     * Generate tenant id
     *
     * @param int $length
     * @return string
     */
    public static function generateTenantId($length = 12)
    {
        return str_pad(random_int(100000000000, 999999999999), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Verify generated tenant id uniqueness
     *
     * @return string
     */
    public static function getUniqueTenantId()
    {
        do {
            $tenantId = self::generateTenantId();
            $isTenantIdExist = Capsule::table('s3_users')
            ->where('tenant_id', $tenantId)
            ->count();
        } while ($isTenantIdExist);

        return $tenantId;
    }
}