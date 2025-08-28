<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

class DBController {

    private static $module = 'cloudstorage';

    /**
     * Get User.
     *
     * @param string $username
     *
     * @return object|null
     */
    public static function getUser($username)
    {
        $user = Capsule::table('s3_users')->where('username', $username)->first();

        return $user;
    }

    /**
     * Get Tenants.
     *
     * @param string $username
     *
     * @return object|null
     */
    public static function getTenants($userId, $columns = ['*'])
    {
        $tenants = Capsule::table('s3_users')
            ->select($columns)
            ->where('parent_id', $userId)
            ->get();

        return $tenants;
    }

    /**
     * Get Client.
     *
     * @param string $loggedInUserId
     *
     * @return object|null
     */
    public static function getClient($loggedInUserId)
    {
        return Capsule::table('tblclients')->where('id', $loggedInUserId)->first();
    }

    /**
     * Get User Buckets.
     *
     * @param $userIds
     *
     * @return object|null
     */
    public static function getUserBuckets($userIds, $columns = ['*'])
    {
        if (!is_array($userIds)) {
            $userIds = [$userIds];
        }

        $buckets = Capsule::table('s3_buckets')
            ->select($columns)
            ->where('is_active', '1')
            ->whereIn('user_id', $userIds)
            ->get();

        return $buckets;
    }

    /**
     * Get Product.
     *
     * @param string $userId
     * @param string $packageId
     *
     * @return object|null
     */
    public static function getProduct($userId, $packageId)
    {
        $product  = Capsule::table('tblhosting')->select('username')->where('userid', $userId)->where('packageid', $packageId)->first();

        return $product;
    }

    /**
     * Get Bucket.
     *
     * @param string $bucketId
     *
     * @return object|null
     */
    public static function getBucket($bucketId)
    {
        $bucket  = Capsule::table('s3_buckets')->select('id')->where('s3_id', $bucketId)->first();

        return $bucket;
    }

    /**
     * Get Highest Amount From S3 Prices.
     *
     * @param string $userId
     *
     * @return object|null
     */
    public static function getHighestAmount($userId, $startDate, $endDate)
    {
        $highestAmount = Capsule::table('s3_prices')
        ->where('user_id', '=', $userId)
        ->whereDate('created_at', '>=', $startDate)
        ->whereDate('created_at', '<=', $endDate)
        ->max('amount');

        return $highestAmount;
    }

    /**
     * Save User.
     *
     * @param array $params
     *
     * @return integer
     */
    public static function saveUser($params)
    {
        return Capsule::table('s3_users')->insertGetId($params);
    }

    /**
     * Save Bucket.
     *
     * @param array $values
     *
     * @return object|null
     */
    public static function saveBucket($values)
    {
        $bucketId = Capsule::table('s3_buckets')->insertGetId($values);

        return $bucketId;
    }

    /**
     * Save Bucket Stats.
     *
     * @param array $values
     *
     * @return
     */
    public static function saveBucketStats($values)
    {
        Capsule::table('s3_bucket_stats')->insert($values);
    }

    /**
     * Save Bucket Stats Summary.
     *
     * @param array $values
     *
     * @return
     */
    public static function saveBucketStatsSummary($values)
    {
        Capsule::table('s3_bucket_stats_summary')->insert($values);
    }

    /**
     * Save Price Updates.
     *
     * @param array $values
     *
     * @return
     */
    public static function savePrices($values)
    {
        Capsule::table('s3_prices')->insert($values);
    }

    /**
     * Save Transfer Stats.
     *
     * @param array $values
     *
     * @return
     */
    public static function saveTransferStats($values)
    {
        return Capsule::table('s3_transfer_stats')->insert($values);
    }

    /**
     * Save Transfer Stats Summary.
     *
     * @param array $values
     *
     * @return
     */
    public static function saveTransferStatsSummary($values)
    {
        return Capsule::table('s3_transfer_stats_summary')->insert($values);
    }

    /**
     * Insert record into a table
     *
     * @param string $table    - Table name.
     * @param array  $data     - Table data
     *
     * @return boolean
     */
    public static function insertRecord(string $table, array $data)
    {
        try {
           return Capsule::table($table)->insert($data);
        } catch (Exception $ex) {
            logModuleCall(self::$module, 'insertRecord', [
                $table,
                $data
            ], $ex->getMessage());
        }
    }

    /**
     * Insert record into a table
     *
     * @param string $table    - Table name.
     * @param array  $data     - Table data
     *
     * @return int
     */
    public static function insertGetId(string $table, array $data)
    {
        try {
           return Capsule::table($table)->insertGetId($data);
        } catch (Exception $ex) {
            logModuleCall(self::$module, 'insertGetId', [
                $table,
                $data
            ], $ex->getMessage());
        }
    }

    /**
     * Update the table records
     *
     * @param string $table   - Table name
     * @param array $data     - Columns, which updated
     * @param array $where    - Optional. Where clause
     *
     * @return int
     */
    public static function updateRecord(string $table, array $data = [], array $where = [])
    {
        try {
            if (count($where) == 0) {
                // return Capsule::table($table)->update($data);
                return 0;
            } else {
                return Capsule::table($table)->where($where)->update($data);
            }
        } catch (Exception $ex) {
            logModuleCall(self::$module, 'updateRecord', [
                $table,
                $data,
                $where
            ], $ex->getMessage());
        }
    }

    /**
     * Delete the table records
     *
     * @param string $table   - Table name
     * @param array $where    - Optional. Where clause
     *
     * @return int
     */
    public static function deleteRecord(string $table, array $where = [])
    {
        try {
            if (count($where) == 0) {
                return Capsule::table($table)->delete();
            } else {
                return Capsule::table($table)->where($where)->delete();
            }
        } catch (Exception $ex) {
            logModuleCall(self::$module, 'deleteRecord', [
                $table,
                $where
            ], $ex->getMessage());
        }
    }

     /**
     * Get the first record of a table based on a where clause and optional order.
     *
     * @param string $table    - Table name.
     * @param array  $where    - Optional. Where clause
     * @param array  $columns  - Optional. Specific columns to fetch (default all columns).
     * @param string|null $orderBy - Optional. Column to order by.
     * @param string $direction - Optional. Sort direction: 'ASC' or 'DESC' (default ASC).
     * @return object|null     - The first record or null if not found.
     */
    public static function getRow(string $table, array $where = [], array $columns = ['*'], string $orderBy = null, string $direction = 'ASC')
    {
        try {
            $query = Capsule::table($table)->select($columns);

            if (count($where)) {
                $query->where($where);
            }

            if ($orderBy) {
                $query->orderBy($orderBy, $direction);
            }

            return $query->first();
        } catch (Exception $ex) {
            logModuleCall(self::$module, 'getRow', [
                $table,
                $where,
                $columns,
                $orderBy,
                $direction
            ], $ex->getMessage());

            return null;
        }
    }

    /**
     * Get the matched records of a table based on a where clause and optional order.
     *
     * @param string $table    - Table name.
     * @param array  $where    - Optional. Where clause
     * @param array  $columns  - Optional. Specific columns to fetch (default all columns).
     * @param string|null $orderBy - Optional. Column to order by.
     * @param string $direction - Optional. Sort direction: 'ASC' or 'DESC' (default ASC).
     * @return object|null     - The first record or null if not found.
     */
    public static function getResult(string $table, array $where = [], array $columns = ['*'], string $orderBy = null, string $direction = 'ASC')
    {
        try {
            $query = Capsule::table($table)
                ->select($columns);

            if (count($where)) {
                $query->where($where);
            }

            // Add orderBy clause if $orderBy is provided
            if ($orderBy) {
                $query->orderBy($orderBy, $direction);
            }

            return $query->get();
        } catch (Exception $ex) {
            logModuleCall(self::$module, 'getResult', [
                $table,
                $where,
                $columns,
                $orderBy,
                $direction
            ], $ex->getMessage());

            return null;
        }
    }
}