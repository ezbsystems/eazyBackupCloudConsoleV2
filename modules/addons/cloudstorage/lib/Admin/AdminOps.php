<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AdminOps {

    private static $module = 'cloudstorage';

    /**
     * Remove Key
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param string $accessKey
     * @param string $username
     *
     * @return array
     */
    public static function removeKey($endpoint, $adminAccessKey, $adminSecretKey, $accessKey, $username = null)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'access-key' => $accessKey,
                'key' => ''
            ];

            // Add uid parameter if username is provided (required for Ceph RGW)
            if (!empty($username)) {
                $query['uid'] = $username;
            }

            $stringToSign = "DELETE\n\n\n{$date}\n/admin/user";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->delete($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            return [
                'status' => 'success',
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Remove key failed. Please try again or contact support.'];
            logModuleCall(self::$module, __FUNCTION__, ['access_key' => $accessKey, 'username' => $username], $e->getMessage());

            return $response;
        }
    }

    /**
     * Create Key
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param string $username
     *
     * @return array
     */
    public static function createKey($endpoint, $adminAccessKey, $adminSecretKey, $username)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $username,
                'key' => ''
            ];

            $stringToSign = "PUT\n\n\n{$date}\n/admin/user";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->put($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            $keys = json_decode($response->getBody()->getContents(), true);

            return [
                'status' => 'success',
                'data' => $keys,
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Create key failed. Please try again or contact support.'];
            logModuleCall(self::$module, __FUNCTION__, $username, $e->getMessage());

            return $response;
        }
    }

    /**
     * Get Usage
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param array $params
     *
     * @return array
     */
    public static function getUsage($endpoint, $adminAccessKey, $adminSecretKey, $params)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/usage";
            $query = [];

            if (isset($params['uid'])) {
                $query['uid'] = $params['uid'];
            }
            if (isset($params['start_date'])) {
                $query['start-date'] = $params['start_date'];
            }
            if (isset($params['end_date'])) {
                $query['end-date'] = $params['end_date'];
            }
            if (isset($params['categories'])) {
                $query['categories'] = $params['categories'];
            }
            if (isset($params['show_entries']) && $params['show_entries']) {
                $query['show-entries'] = true;
            }
            if (isset($params['show_summary']) && $params['show_summary']) {
                $query['show-summary'] = true;
            }

            $stringToSign = "GET\n\n\n{$date}\n/admin/usage";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            return [
                'status' => 'success',
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Get usage failed. Please try again or contact support.'];
            logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage());

            return $response;
        }
    }

    /**
     * Get Bucket Info
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param array $params
     *
     * @return array
     */
    public static function getBucketInfo($endpoint, $adminAccessKey, $adminSecretKey, $params)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/bucket";
            $query = [];

            if (isset($params['uid'])) {
                $query['uid'] = $params['uid'];
            }
            if (isset($params['stats'])) {
                $query['stats'] = $params['stats'];
            }
            if (isset($params['bucket'])) {
                $query['bucket'] = $params['bucket'];
            }

            $stringToSign = "GET\n\n\n{$date}\n/admin/bucket";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'status' => 'success',
                'data' => $data
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Get bucket info failed. Please try again or contact support.', 'error' => $e->getMessage()];
            logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage());

            return $response;
        }
    }

    /**
     * Get User Info
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param string $username
     *
     * @return array
     */
    public static function getUserInfo($endpoint, $adminAccessKey, $adminSecretKey, $username)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $username
            ];

            $stringToSign = "GET\n\n\n{$date}\n/admin/user";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            return [
                'status' => 'success',
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Get user info failed. Please try again or contact support.'];
            logModuleCall(self::$module, __FUNCTION__, $username, $e->getMessage());

            return $response;
        }
    }

    /**
     * Create User
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param array $params
     *
     * @return array
     */
    public static function createUser($endpoint, $adminAccessKey, $adminSecretKey, $params)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $params['uid'],
                'display-name' => $params['name']
            ];

            if (isset($params['email'])) {
                $query['email'] = $params['email'];
            }

            if (isset($params['tenant'])) {
                $query['tenant'] = $params['tenant'];
            }

            $stringToSign = "PUT\n\n\n{$date}\n/admin/user";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->put($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            return [
                'status' => 'success',
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Create user failed. Please try again or contact support.'];
            logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage());

            return $response;
        }
    }

    /**
     * Create User
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param array $username
     *
     * @return array
     */
    public static function removeUser($endpoint, $adminAccessKey, $adminSecretKey, $username)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $username,
                'purge-data' => false
            ];

            $stringToSign = "DELETE\n\n\n{$date}\n/admin/user";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->delete($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            return [
                'status' => 'success',
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Remove user failed. Please try again or contact support.'];
            
            // Check if this is a "NoSuchUser" error (404 response)
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                
                if ($statusCode === 404 && strpos($responseBody, 'NoSuchUser') !== false) {
                    $response['error_type'] = 'NoSuchUser';
                    // Keep generic message for customer, detailed info only in logs
                    logModuleCall(self::$module, __FUNCTION__, $username, 'NoSuchUser error - user does not exist on storage system: ' . $e->getMessage());
                } else {
                    logModuleCall(self::$module, __FUNCTION__, $username, $e->getMessage());
                }
            } else {
                logModuleCall(self::$module, __FUNCTION__, $username, $e->getMessage());
            }

            return $response;
        }
    }

    /**
     * Create Sub User
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param array $params
     *
     * @return array
     */
    public static function createSubUser($endpoint, $adminAccessKey, $adminSecretKey, $params)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $params['uid'],
                'subuser' => $params['subuser'],
                'access' => $params['access'],
                'key-type' => 's3',
                'generate-secret' => true
            ];

            $stringToSign = "PUT\n\n\n{$date}\n/admin/user";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->put($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            return [
                'status' => 'success',
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Create sub user failed. Please try again or contact support.'];
            logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage());

            return $response;
        }
    }

    /**
     * Update Sub User
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param array $params
     *
     * @return array
     */
    public static function updateSubUser($endpoint, $adminAccessKey, $adminSecretKey, $params)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $params['uid'],
                'subuser' => $params['subuser'],
                'purge-keys' => true
            ];

            $stringToSign = "DELETE\n\n\n{$date}\n/admin/user";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->delete($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            return [
                'status' => 'success',
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Remove sub user failed. Please try again or contact support.'];
            logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage());

            return $response;
        }
    }

    /**
     * Remove Sub User
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param array $params
     *
     * @return array
     */
    public static function removeSubUser($endpoint, $adminAccessKey, $adminSecretKey, $params)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $params['uid'],
                'subuser' => $params['subuser'],
                'purge-keys' => true
            ];

            $stringToSign = "DELETE\n\n\n{$date}\n/admin/user";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
            $authHeader = "AWS {$adminAccessKey}:{$signature}";

            $response = $client->delete($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Date' => $date,
                ],
                'query' => $query,
            ]);

            return [
                'status' => 'success',
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $response = ['status' => 'fail', 'message' => 'Remove sub user failed. Please try again or contact support.'];
            logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage());

            return $response;
        }
    }
}