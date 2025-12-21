<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

class Tenant {

    private static $module = 'cloudstorage';

    /**
     * Prevent instantiation
     */
    private function __construct()
    {
        // By this way we can stop the object creation of this class
    }

    /**
     * Decrypt tenant key
     *
     * @param string $request
     * @param string $parentUserId
     *
     * @return array
     */
    public static function decryptTenantKey($request, $parentUserId)
    {
        try {
            // Require recent password verification (defense-in-depth)
            $verifiedAt = isset($_SESSION['cloudstorage_pw_verified_at']) ? (int) $_SESSION['cloudstorage_pw_verified_at'] : 0;
            $freshWindow = 15 * 60; // 15 minutes
            if ($verifiedAt <= 0 || (time() - $verifiedAt) > $freshWindow) {
                return [
                    'status' => 'fail',
                    'message' => 'Please verify your password to view access keys.'
                ];
            }

            $id = $request['id'];
            $username = $request['username'];

            $tenant = DBController::getRow('s3_users', [
                ['username', '=', $username],
                ['parent_id', '=', $parentUserId],
            ]);

            if (is_null($tenant)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant ' . $username . ' not found.'
                ];
            }

            $tenantKey = DBController::getRow('s3_user_access_keys', [
                ['id', '=', $id],
                ['user_id', '=', $tenant->id],
            ]);

            if (is_null($tenantKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Key not found.'
                ];
            }

            $encryptionKey = DBController::getRow('tbladdonmodules', [
                ['module', '=', 'cloudstorage'],
                ['setting', '=', 'encryption_key']
            ]);

            if (is_null($encryptionKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Something went wrong. Please contact technical support for assistance.'
                ];
            }

            $decryptedAccessKey = HelperController::decryptKey($tenantKey->access_key, $encryptionKey->value);
            $decryptedSecretKey = HelperController::decryptKey($tenantKey->secret_key, $encryptionKey->value);
            $tenantKey->access_key = $decryptedAccessKey;
            $tenantKey->secret_key = $decryptedSecretKey;

            return [
                'status' => 'success',
                'message' => 'Successfully decrypted keys.',
                'keys' => $tenantKey
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $request, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Something went wrong. Please contact technical support for assistance.'
            ];
        }
    }

    /**
     * Decrypt subuser key
     *
     * @param string $request
     * @param string $parentUserId
     *
     * @return array
     */
    public static function decryptSubuserKey($request, $parentUserId)
    {
        try {
            // Require recent password verification (defense-in-depth)
            $verifiedAt = isset($_SESSION['cloudstorage_pw_verified_at']) ? (int) $_SESSION['cloudstorage_pw_verified_at'] : 0;
            $freshWindow = 15 * 60; // 15 minutes
            if ($verifiedAt <= 0 || (time() - $verifiedAt) > $freshWindow) {
                return [
                    'status' => 'fail',
                    'message' => 'Please verify your password to view access keys.'
                ];
            }

            $id = $request['id'];
            $username = $request['username'];

            $tenant = DBController::getRow('s3_users', [
                ['username', '=', $username],
                ['parent_id', '=', $parentUserId],
            ]);

            if (is_null($tenant)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant ' . $username . ' not found.'
                ];
            }

            // get the subuser key
            $subuserKey = DBController::getRow('s3_subusers_keys', [
                ['id', '=', $id],
            ]);

            if (is_null($subuserKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Key not found.'
                ];
            }

            // verify the subuser belongs to tenant
            $subuser = DBController::getRow('s3_subusers', [
                ['user_id', '=', $tenant->id],
                ['id', '=', $subuserKey->subuser_id],
            ]);

            if (is_null($subuser)) {
                return [
                    'status' => 'fail',
                    'message' => 'Key not found.'
                ];
            }

            $encryptionKey = DBController::getRow('tbladdonmodules', [
                ['module', '=', 'cloudstorage'],
                ['setting', '=', 'encryption_key']
            ]);

            if (is_null($encryptionKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Something went wrong. Please contact technical support for assistance.'
                ];
            }

            $decryptedAccessKey = HelperController::decryptKey($subuserKey->access_key, $encryptionKey->value);
            $decryptedSecretKey = HelperController::decryptKey($subuserKey->secret_key, $encryptionKey->value);
            $subuserKey->access_key = $decryptedAccessKey;
            $subuserKey->secret_key = $decryptedSecretKey;

            return [
                'status' => 'success',
                'message' => 'Successfully decrypted keys.',
                'keys' => $subuserKey
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $request, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Something went wrong. Please contact technical support for assistance.'
            ];
        }
    }

    /**
     * Add tenant
     *
     * @param $request
     * @param $parentUser
     *
     * @return array
     */
    public static function addTenant($request, $parentUser)
    {
        try {
            $name = $request['name'];
            $username = $request['username'];
            $tenant = DBController::getRow('s3_users', [
                ['username', '=', $username],
                ['parent_id', '=', $parentUser->id],
            ]);

            if (!is_null($tenant)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant ' . $username . ' already exist.'
                ];
            }

            $module = DBController::getResult('tbladdonmodules', [
                ['module', '=', 'cloudstorage']
            ]);

            if (count($module) == 0) {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage service error. Please contact technical support for assistance.'
                ];
            }

            $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
            $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
            $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
            $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
            $newTenantId = $parentUser->tenant_id;

            $params = [
                'uid'  => $username,
                'name' => $name,
                'tenant' => $newTenantId
            ];

            $tenant = AdminOps::createUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);

            if ($tenant['status'] != 'success') {
                return [
                    'message' => $tenant['message'],
                    'status' => 'fail',
                ];
            }

            $accessKey = HelperController::encryptKey($tenant['data']['keys'][0]['access_key'], $encryptionKey);
            $secretKey = HelperController::encryptKey($tenant['data']['keys'][0]['secret_key'], $encryptionKey);

            $tenantId = DBController::insertGetId('s3_users', [
                'name' => $name,
                'username' => $username,
                'tenant_id' => $newTenantId,
                'parent_id' => $parentUser->id
            ]);

            $keyId = DBController::insertGetId('s3_user_access_keys', [
                'user_id' => $tenantId,
                'access_key' => $accessKey,
                'secret_key' => $secretKey
            ]);

            return [
                'status' => 'success',
                'message' => 'User has been added successfully.',
                'data' => [
                    'username' => $username,
                    // For RGW tenants, all sub-users live under the parent's tenant namespace.
                    // Returning it allows the UI to show it immediately without requiring a refresh.
                    'tenant_id' => $newTenantId,
                    'key_id' => $keyId
                ]
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $request, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Something went wrong. Please contact technical support for assistance.'
            ];
        }
    }

    /**
     * Add tenant key
     *
     * @param $request
     * @param $parentUserId
     *
     * @return array
     */
    public static function addTenantKey($request, $parentUserId)
    {
        try {
            $username = $request['username'];

            $tenant = DBController::getRow('s3_users', [
                ['username', '=', $username],
                ['parent_id', '=', $parentUserId],
            ]);

            if (is_null($tenant)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant ' . $username . ' not found.'
                ];
            }

            $module = DBController::getResult('tbladdonmodules', [
                ['module', '=', 'cloudstorage']
            ]);

            if (count($module) == 0) {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage service error. Please contact technical support for assistance.'
                ];
            }
            $tenantUsername = !(empty($tenant->tenant_id)) ? $tenant->tenant_id . '$' . $username : $username;
            $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
            $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
            $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
            $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
            $keys = AdminOps::createKey($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $tenantUsername);
            if ($keys['status'] != 'success') {
                return [
                    'message' => $keys['message'],
                    'status' => 'fail',
                ];
            }

            $accessKey = $secretKey = '';

            // get the DB keys of the users
            $dbKeys = DBController::getResult('s3_user_access_keys', [
                ['user_id', '=', $tenant->id]
            ]);

            if (count($dbKeys)) {
                $filteredKeys = array_filter($keys['data'], function ($record) use ($tenantUsername) {
                    return $record['user'] === $tenantUsername;
                });

                $originalKeys = [];
                foreach ($dbKeys as $dbKey) {
                    $dbAccessKey = HelperController::decryptKey($dbKey->access_key, $encryptionKey);
                    $dbSecretKey = HelperController::decryptKey($dbKey->secret_key, $encryptionKey);
                    $originalKeys[] = [
                        'user' => $tenantUsername,
                        'access_key' => $dbAccessKey,
                        'secret_key' => $dbSecretKey
                    ];
                }

                $newKeys = array_udiff($filteredKeys, $originalKeys, function ($a, $b) {
                    return strcmp(json_encode($a), json_encode($b));
                });

                $newKeys = array_values($newKeys);

                if (count($newKeys)) {
                    $accessKey = $newKeys[0]['access_key'];
                    $secretKey = $newKeys[0]['secret_key'];
                }
            } else {
                $accessKey = $keys['data'][0]['access_key'];
                $secretKey = $keys['data'][0]['secret_key'];
            }

            if (empty($accessKey) || empty($secretKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Unable to get the keys. Please try again or contact support.'
                ];
            }

            $accessKey = HelperController::encryptKey($accessKey, $encryptionKey);
            $secretKey = HelperController::encryptKey($secretKey, $encryptionKey);
            $keyId = DBController::insertGetId('s3_user_access_keys', [
                'user_id' => $tenant->id,
                'access_key' => $accessKey,
                'secret_key' => $secretKey
            ]);

            return [
                'status' => 'success',
                'message' => 'Keys added successfully',
                'type' => 'primary',
                'data' => [
                    'username' => $username,
                    'key_id' => $keyId
                ]
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $request, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Something went wrong. Please contact technical support for assistance.'
            ];
        }
    }

    /**
     * Add tenant subuser key
     *
     * @param $request
     * @param $parentUser
     *
     * @return array
     */
    public static function addTenantSubuserKey($request, $parentUser)
    {
        try {
            $username = $request['username'];
            $parentUserId = $parentUser->id;
            $tenant = DBController::getRow('s3_users', [
                ['username', '=', $username],
                ['parent_id', '=', $parentUserId],
            ]);

            if (is_null($tenant)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant ' . $username . ' not found.'
                ];
            }
            $tenantUsername = !(empty($tenant->tenant_id)) ? $tenant->tenant_id . '$' . $username : $username;

            $module = DBController::getResult('tbladdonmodules', [
                ['module', '=', 'cloudstorage']
            ]);

            if (count($module) == 0) {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage service error. Please contact technical support for assistance.'
                ];
            }

            $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
            $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
            $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
            $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

            $subusername = $request['subusername'];
            $permission = $request['access'];
            $params = [
                'uid' => $tenantUsername,
                'subuser' => $subusername,
                'access' => $permission
            ];

            $subuser = AdminOps::createSubUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);

            if ($subuser['status'] != 'success') {
                return [
                    'status' => 'fail',
                    'message' => $subuser['message'],
                ];
            }

            $subuserId = DBController::insertGetId('s3_subusers', [
                'user_id' => $tenant->id,
                'subuser' => $subusername,
                'permission' => $permission
            ]);

            $userinfo = AdminOps::getUserInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $tenantUsername);
            $accessKey = $secretKey = '';
            if ($userinfo['status'] != 'success') {
                return [
                    'status' => 'fail',
                    'message' => $userinfo['message']
                ];
            }

            $keys = $userinfo['data']['keys'];
            $searchUser = $tenantUsername.':'.$subusername;

            foreach ($keys as $item) {
                if ($item['user'] === $searchUser) {
                    $accessKey = $item['access_key'];
                    $secretKey = $item['secret_key'];
                    break;
                }
            }

            if (empty($accessKey) || empty($secretKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Unable to get the keys. Please try again or contact support.'
                ];
            }

            $accessKey = HelperController::encryptKey($accessKey, $encryptionKey);
            $secretKey = HelperController::encryptKey($secretKey, $encryptionKey);
            $keyId = DBController::insertGetId('s3_subusers_keys', [
                'subuser_id' => $subuserId,
                'access_key' => $accessKey,
                'secret_key' => $secretKey
            ]);

            if ($permission == 'readwrite') {
                $permission = "Read Write";
            } else {
                $permission = ucwords($permission);
            }

            return [
                'status' => 'success',
                'message' => 'Keys added successfully',
                'type' => 'subuser',
                'data' => [
                    'key_id' => $keyId,
                    'permission' => $permission,
                    'subusername' => $subusername,
                    'username' => $username
                ]
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $request, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Something went wrong. Please contact technical support for assistance.'
            ];
        }
    }

    /**
     * Delete Key
     *
     * @param $request
     * @param $parentUserId
     *
     * @return array
     */
    public static function deleteKey($request, $parentUserId)
    {
        try {
            $keyId = $request['id'];
            $username = $request['username'];

            $tenant = DBController::getRow('s3_users', [
                ['username', '=', $username],
                ['parent_id', '=', $parentUserId],
            ], [
                'id', 'username', 'tenant_id'
            ]);

            if (is_null($tenant)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant ' . $username . ' not found.'
                ];
            }

            // get the key from db
            $dbKey = DBController::getRow('s3_user_access_keys', [
                ['user_id', '=', $tenant->id],
                ['id', '=', $keyId],
            ]);

            if (is_null($dbKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Key not found.'
                ];
            }

            $module = DBController::getResult('tbladdonmodules', [
                ['module', '=', 'cloudstorage']
            ]);

            if (count($module) == 0) {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage service error. Please contact technical support for assistance.'
                ];
            }

            $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
            $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
            $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
            $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
            $accessKey = HelperController::decryptKey($dbKey->access_key, $encryptionKey);
            
            // Construct the full Ceph username with tenant prefix
            $tenantUsername = !(empty($tenant->tenant_id)) ? $tenant->tenant_id . '$' . $username : $username;
            $deleteKey = AdminOps::removeKey($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $accessKey, $tenantUsername);
            if ($deleteKey['status'] != 'success') {
                return [
                    'status' => 'fail',
                    'message' => $deleteKey['message']
                ];
            }

            // delete the DB key
            DBController::deleteRecord('s3_user_access_keys', [
                ['id', '=', $keyId]
            ]);

            return [
                'status' => 'success',
                'message' => 'Key deleted successfully'
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $request, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Something went wrong. Please contact technical support for assistance.'
            ];
        }
    }

    /**
     * Delete subuser
     *
     * @param $request
     * @param $parentUserId
     *
     * @return array
     */
    public static function deleteSubuser($request, $parentUserId)
    {
        try {
            $keyId = $request['id'];
            $username = $request['username'];

            $tenant = DBController::getRow('s3_users', [
                ['username', '=', $username],
                ['parent_id', '=', $parentUserId],
            ], [
                'id', 'username'
            ]);

            if (is_null($tenant)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant ' . $username . ' not found.'
                ];
            }

            // get the subuser key
            $dbKey = DBController::getRow('s3_subusers_keys', [
                ['id', '=', $keyId],
            ], [
                'id', 'subuser_id', 'access_key'
            ]);

            if (is_null($dbKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Key not found.'
                ];
            }

            // verify the subuser belongs to tenant
            $subuser = DBController::getRow('s3_subusers', [
                ['user_id', '=', $tenant->id],
                ['id', '=', $dbKey->subuser_id],
            ], [
                'id', 'subuser'
            ]);

            if (is_null($subuser)) {
                return [
                    'status' => 'fail',
                    'message' => 'Key not found.'
                ];
            }

            $module = DBController::getResult('tbladdonmodules', [
                ['module', '=', 'cloudstorage']
            ]);

            if (count($module) == 0) {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage service error. Please contact technical support for assistance.'
                ];
            }

            $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
            $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
            $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
            $tenantUsername = !(empty($tenant->tenant_id)) ? $tenant->tenant_id . '$' . $username : $username;
            $params = [
                'uid' => $tenantUsername,
                'subuser' => $subuser->subuser
            ];

            $result = AdminOps::removeSubUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
            if ($result['status'] != 'success') {
                return [
                    'status' => 'fail',
                    'message' => $result['message']
                ];
            }

            // delete the DB key
            DBController::deleteRecord('s3_subusers_keys', [
                ['id', '=', $keyId]
            ]);

            // delete the Subuser
            DBController::deleteRecord('s3_subusers', [
                ['id', '=', $subuser->id]
            ]);

            return [
                'status' => 'success',
                'message' => 'Key deleted successfully'
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $request, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Something went wrong. Please contact technical support for assistance.'
            ];
        }
    }

    /**
     * Delete Key
     *
     * @param $request
     * @param $parentUserId
     *
     * @return array
     */
    public static function deleteTenant($request, $parentUserId)
    {
        try {
            $username = $request['username'];

            $tenant = DBController::getRow('s3_users', [
                ['username', '=', $username],
                ['parent_id', '=', $parentUserId],
            ]);

            if (is_null($tenant)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant ' . $username . ' not found.'
                ];
            }

            $module = DBController::getResult('tbladdonmodules', [
                ['module', '=', 'cloudstorage']
            ]);

            if (count($module) == 0) {
                return [
                    'status' => 'fail',
                    'message' => 'Cloud Storage service error. Please contact technical support for assistance.'
                ];
            }

            $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
            $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
            $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();

            // check bucket exist for this username
            $tenantBuckets = DBController::getResult('s3_buckets', [
                ['user_id', '=', $tenant->id],
                ['is_active', '=', '1']
            ]);

            if (count($tenantBuckets)) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant has buckets, please delete the bucket first.'
                ];
            }
            // check if the tenant has tenant group
            $apiUsername = !empty($tenant->tenant_id) ? $tenant->tenant_id . '$' . $username : $username;

            $adminOpsResponse = AdminOps::getBucketInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, ['uid' => $apiUsername]);

            if ($adminOpsResponse['status'] != 'success') {
                return [
                    'status' => 'fail',
                    'message' => $adminOpsResponse['message']
                ];
            } elseif (count($adminOpsResponse['data'])) {
                return [
                    'status' => 'fail',
                    'message' => 'Tenant has buckets, please delete the bucket first.'
                ];
            }

            $adminOpsResponse = AdminOps::removeUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $apiUsername);
            if ($adminOpsResponse['status'] != 'success') {
                // If user doesn't exist on RGW (NoSuchUser error), proceed with DB cleanup
                if (isset($adminOpsResponse['error_type']) && $adminOpsResponse['error_type'] === 'NoSuchUser') {
                    // Log that we're cleaning up orphaned database record
                    logModuleCall(self::$module, __FUNCTION__, $username, 'User not found on RGW, proceeding with database cleanup for orphaned record');
                } else {
                    // For other errors, return the error as before
                    return [
                        'status' => 'fail',
                        'message' => $adminOpsResponse['message']
                    ];
                }
            }

            // get all the subusers
            $subusers = DBController::getResult('s3_subusers', [
                ['user_id', '=', $tenant->id]
            ], [
                'id'
            ]);

            if (count($subusers)) {
                $subuserIds = $subusers->pluck('id')->toArray();
                // delete all the subusers keys
                Capsule::table('s3_subusers_keys')
                    ->whereIn('subuser_id', $subuserIds)
                    ->delete();

                Capsule::table('s3_subusers')
                    ->whereIn('id', $subuserIds)
                    ->delete();
            }

            // delete the all keys
            DBController::deleteRecord('s3_user_access_keys', [
                ['user_id', '=', $tenant->id]
            ]);

            // delete user
            DBController::deleteRecord('s3_users', [
                ['id', '=', $tenant->id]
            ]);

            // Log additional details for admin if this was an orphaned record cleanup
            if (isset($adminOpsResponse['error_type']) && $adminOpsResponse['error_type'] === 'NoSuchUser') {
                logModuleCall(self::$module, __FUNCTION__, $username, 'Successfully cleaned up orphaned database record - user did not exist on storage system');
            }
            
            return [
                'status' => 'success',
                'message' => 'Tenant ' . $username . ' deleted successfully.'
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $request, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Something went wrong. Please contact technical support for assistance.'
            ];
        }
    }
}