<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AdminOps {

    private static $module = 'cloudstorage';

    /**
     * Create a short-lived S3 key for an RGW user and return ONLY the newly created keypair.
     *
     * This is safety-critical: many RGW AdminOps responses include multiple keys (sometimes with secret_key fields),
     * so callers MUST NOT "pick the first key" and later revoke it, or they may revoke the customer's real key.
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     * @param string $username RGW uid (prefer full tenant-qualified uid e.g. tenant$uid)
     * @param string|null $tenant Optional tenant query param (some RGW builds don't support this; prefer null + full uid)
     * @return array
     *   - status: success|fail
     *   - access_key, secret_key, uid, tenant
     *   - message (on fail)
     *   - debug (optional)
     */
    public static function createTempKey(string $endpoint, string $adminAccessKey, string $adminSecretKey, string $username, ?string $tenant = null): array
    {
        $username = trim((string)$username);
        $tenant = $tenant !== null ? trim((string)$tenant) : null;
        if ($username === '') {
            return ['status' => 'fail', 'message' => 'Missing username for createTempKey.'];
        }

        // Capture existing access keys so we can reliably identify the newly-created key.
        $beforeKeys = [];
        try {
            $info = self::getUserInfo($endpoint, $adminAccessKey, $adminSecretKey, $username, $tenant);
            $data = is_array($info) ? ($info['data'] ?? null) : null;
            if (is_array($data) && isset($data['keys']) && is_array($data['keys'])) {
                foreach ($data['keys'] as $k) {
                    if (is_array($k) && !empty($k['access_key'])) {
                        $beforeKeys[(string)$k['access_key']] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // If we cannot get beforeKeys, we can still proceed, but selection becomes less reliable.
            // We'll still try to identify the new key from the createKey response.
        }

        $keys = self::createKey($endpoint, $adminAccessKey, $adminSecretKey, $username, $tenant);
        if (!is_array($keys) || ($keys['status'] ?? '') !== 'success') {
            logModuleCall(self::$module, __FUNCTION__ . '_CREATE_FAILED', ['uid' => $username, 'tenant' => $tenant], $keys);
            return ['status' => 'fail', 'message' => 'Unable to create temporary key.'];
        }

        $raw = $keys['data'] ?? [];
        // createKey can return either a list of key records or a user-info object with "keys"
        $records = [];
        if (is_array($raw) && isset($raw['keys']) && is_array($raw['keys'])) {
            $records = $raw['keys'];
        } elseif (is_array($raw)) {
            $records = $raw;
        }

        $tempAccessKey = '';
        $tempSecretKey = '';

        if (is_array($records) && count($records) > 0) {
            // Prefer the newly created key (not present in beforeKeys), and prefer one that includes secret_key.
            foreach ($records as $r) {
                if (!is_array($r)) { continue; }
                $ak = (string)($r['access_key'] ?? '');
                $sk = (string)($r['secret_key'] ?? '');
                if ($ak === '') { continue; }
                if (!isset($beforeKeys[$ak]) && $sk !== '') {
                    $tempAccessKey = $ak;
                    $tempSecretKey = $sk;
                    break;
                }
            }

            // If not found, pick any new access key then look up its secret_key in the response.
            if ($tempAccessKey === '') {
                $newAccessKey = '';
                foreach ($records as $r) {
                    if (!is_array($r)) { continue; }
                    $ak = (string)($r['access_key'] ?? '');
                    if ($ak !== '' && !isset($beforeKeys[$ak])) {
                        $newAccessKey = $ak;
                    }
                }
                if ($newAccessKey === '') {
                    // Fallback: assume last is newest (common RGW behavior)
                    $last = end($records);
                    if (is_array($last)) {
                        $newAccessKey = (string)($last['access_key'] ?? '');
                    }
                    reset($records);
                }
                if ($newAccessKey !== '') {
                    foreach ($records as $r) {
                        if (!is_array($r)) { continue; }
                        if ((string)($r['access_key'] ?? '') === $newAccessKey) {
                            $tempAccessKey = (string)($r['access_key'] ?? '');
                            $tempSecretKey = (string)($r['secret_key'] ?? '');
                            break;
                        }
                    }
                }
            }
        }

        if ($tempAccessKey === '' || $tempSecretKey === '') {
            // Safety: don't guess; failing here is better than revoking the wrong key later.
            logModuleCall(self::$module, __FUNCTION__ . '_PARSE_FAILED', [
                'uid' => $username,
                'tenant' => $tenant,
                'before_key_count' => count($beforeKeys),
            ], $keys);
            return ['status' => 'fail', 'message' => 'Unable to determine newly created temporary key.'];
        }

        return [
            'status' => 'success',
            'access_key' => $tempAccessKey,
            'secret_key' => $tempSecretKey,
            'uid' => $username,
            'tenant' => $tenant,
        ];
    }

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
    public static function removeKey($endpoint, $adminAccessKey, $adminSecretKey, $accessKey, $username = null, $tenant = null)
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
            // Tenant support (RGW multisite/multitenant)
            if (!empty($tenant)) {
                $query['tenant'] = $tenant;
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
            // Some RGW builds do NOT accept tenant as a separate query param for key ops.
            // Fallback: if tenant is provided and RGW returns NoSuchUser, retry using uid="tenant$uid" without tenant param.
            if (!empty($tenant) && !empty($username) && $e->hasResponse()) {
                try {
                    $status = $e->getResponse()->getStatusCode();
                    $body = (string)$e->getResponse()->getBody();
                    if ($status === 404 && (stripos($body, 'NoSuchUser') !== false || stripos($body, '"Code":"NoSuchUser"') !== false)) {
                        $altUid = (strpos((string)$username, '$') !== false) ? (string)$username : ((string)$tenant . '$' . (string)$username);
                        $retryQuery = [
                            'access-key' => $accessKey,
                            'key' => '',
                            'uid' => $altUid,
                        ];
                        $stringToSign = "DELETE\n\n\n{$date}\n/admin/user";
                        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
                        $authHeader = "AWS {$adminAccessKey}:{$signature}";
                        $retry = $client->delete($url, [
                            'headers' => [
                                'Authorization' => $authHeader,
                                'Date' => $date,
                            ],
                            'query' => $retryQuery,
                        ]);
                        return [
                            'status' => 'success',
                            'data' => json_decode($retry->getBody()->getContents(), true),
                        ];
                    }
                } catch (\Throwable $ignore) {
                    // fall through to generic fail
                }
            }
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
    public static function createKey($endpoint, $adminAccessKey, $adminSecretKey, $username, $tenant = null)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $username,
                'key' => ''
            ];
            // Optional tenant (RGW multitenant)
            if (!empty($tenant)) {
                $query['tenant'] = $tenant;
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

            $keys = json_decode($response->getBody()->getContents(), true);

            return [
                'status' => 'success',
                'data' => $keys,
            ];
        } catch (RequestException $e) {
            // Some RGW builds do NOT accept tenant as a separate query param for key ops.
            // Fallback: if tenant is provided and RGW returns NoSuchUser, retry using uid="tenant$uid" without tenant param.
            if (!empty($tenant) && !empty($username) && $e->hasResponse()) {
                try {
                    $status = $e->getResponse()->getStatusCode();
                    $body = (string)$e->getResponse()->getBody();
                    if ($status === 404 && (stripos($body, 'NoSuchUser') !== false || stripos($body, '"Code":"NoSuchUser"') !== false)) {
                        $altUid = (strpos((string)$username, '$') !== false) ? (string)$username : ((string)$tenant . '$' . (string)$username);
                        $retryQuery = [
                            'uid' => $altUid,
                            'key' => ''
                        ];
                        $stringToSign = "PUT\n\n\n{$date}\n/admin/user";
                        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
                        $authHeader = "AWS {$adminAccessKey}:{$signature}";
                        $retry = $client->put($url, [
                            'headers' => [
                                'Authorization' => $authHeader,
                                'Date' => $date,
                            ],
                            'query' => $retryQuery,
                        ]);
                        $keys = json_decode($retry->getBody()->getContents(), true);
                        return [
                            'status' => 'success',
                            'data' => $keys,
                        ];
                    }
                } catch (\Throwable $ignore) {
                    // fall through to generic fail
                }
            }
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
			if (isset($params['tenant'])) {
				$query['tenant'] = $params['tenant'];
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
	 * Create Bucket (Ceph AdminOps)
	 *
	 * @param string $endpoint
	 * @param string $adminAccessKey
	 * @param string $adminSecretKey
	 * @param array  $params ['bucket' => 'name' or 'tenant/name', 'uid' => 'owner', 'tenant' => 'optional']
	 *
	 * @return array
	 */
	public static function createBucket($endpoint, $adminAccessKey, $adminSecretKey, $params)
	{
		try {
			$client = new Client();
			$date = gmdate('D, d M Y H:i:s T');
			$url = "{$endpoint}/admin/bucket";

			$query = [];
			if (isset($params['uid'])) {
				$query['uid'] = $params['uid'];
			}
			// Pass tenant as its own param; bucket should be the bare bucket name
			if (isset($params['bucket'])) {
				$query['bucket'] = $params['bucket'];
			}
			if (isset($params['tenant'])) {
				$query['tenant'] = $params['tenant'];
			}
			// Optional placement target if provided by caller
			if (isset($params['placement'])) {
				$query['placement'] = $params['placement'];
			}
			// Prefer JSON response when supported
			$query['format'] = 'json';

			$stringToSign = "PUT\n\n\n{$date}\n/admin/bucket";
			$signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
			$authHeader = "AWS {$adminAccessKey}:{$signature}";

			$response = $client->put($url, [
				'headers' => [
					'Authorization' => $authHeader,
					'Date' => $date,
					'Accept' => 'application/json'
				],
				'query' => $query,
			]);

			$body = (string)$response->getBody();
			$data = strlen($body) ? json_decode($body, true) : null;

			return [
				'status' => 'success',
				'data' => $data
			];
		} catch (RequestException $e) {
			$response = ['status' => 'fail', 'message' => 'Create bucket failed. Please try again or contact support.'];
			logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage());

			return $response;
		}
	}

	/**
	 * Link Bucket to Owner (assign bucket ownership)
	 *
	 * @param string $endpoint
	 * @param string $adminAccessKey
	 * @param string $adminSecretKey
	 * @param array  $params ['bucket' => 'name', 'uid' => 'owner', 'tenant' => 'optional']
	 *
	 * @return array
	 */
	public static function linkBucket($endpoint, $adminAccessKey, $adminSecretKey, $params)
	{
		try {
			$client = new Client();
			$date = gmdate('D, d M Y H:i:s T');
			$url = "{$endpoint}/admin/bucket";

			$query = [
				'op' => 'link',
				'format' => 'json'
			];
			if (isset($params['uid'])) {
				$query['uid'] = $params['uid'];
			}
			if (isset($params['bucket'])) {
				$query['bucket'] = $params['bucket'];
			}
			if (isset($params['tenant'])) {
				$query['tenant'] = $params['tenant'];
			}

			$stringToSign = "POST\n\n\n{$date}\n/admin/bucket";
			$signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
			$authHeader = "AWS {$adminAccessKey}:{$signature}";

			$response = $client->post($url, [
				'headers' => [
					'Authorization' => $authHeader,
					'Date' => $date,
					'Accept' => 'application/json'
				],
				'query' => $query,
			]);

			$body = (string)$response->getBody();
			$data = strlen($body) ? json_decode($body, true) : null;

			return [
				'status' => 'success',
				'data' => $data
			];
		} catch (RequestException $e) {
			$response = ['status' => 'fail', 'message' => 'Link bucket failed. Please try again or contact support.'];
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
    public static function getUserInfo($endpoint, $adminAccessKey, $adminSecretKey, $username, $tenant = null)
    {
        try {
            $client = new Client();
            $date = gmdate('D, d M Y H:i:s T');
            $url = "{$endpoint}/admin/user";
            $query = [
                'uid' => $username
            ];
            if (!empty($tenant)) {
                $query['tenant'] = $tenant;
            }

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
            // Fallback for RGW builds that don't accept tenant param for user lookup.
            if (!empty($tenant) && !empty($username) && $e->hasResponse()) {
                try {
                    $status = $e->getResponse()->getStatusCode();
                    $body = (string)$e->getResponse()->getBody();
                    if ($status === 404 && (stripos($body, 'NoSuchUser') !== false || stripos($body, '"Code":"NoSuchUser"') !== false)) {
                        $altUid = (strpos((string)$username, '$') !== false) ? (string)$username : ((string)$tenant . '$' . (string)$username);
                        $retryQuery = [ 'uid' => $altUid ];
                        $stringToSign = "GET\n\n\n{$date}\n/admin/user";
                        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
                        $authHeader = "AWS {$adminAccessKey}:{$signature}";
                        $retry = $client->get($url, [
                            'headers' => [
                                'Authorization' => $authHeader,
                                'Date' => $date,
                            ],
                            'query' => $retryQuery,
                        ]);
                        return [
                            'status' => 'success',
                            'data' => json_decode($retry->getBody()->getContents(), true),
                        ];
                    }
                } catch (\Throwable $ignore) {
                    // fall through
                }
            }
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