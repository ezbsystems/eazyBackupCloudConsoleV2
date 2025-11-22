<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

header('Content-Type: application/json');

try {
    $postData = json_decode(file_get_contents('php://input'), true);
    if (!is_array($postData)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
        exit;
    }

    $action    = $postData['action']    ?? '';
    $serviceId = isset($postData['serviceId']) ? (int)$postData['serviceId'] : 0;
    $username  = $postData['username']  ?? '';

    if ($serviceId <= 0 || $username === '' || $action === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        exit;
    }

    // Ensure the service belongs to the logged-in client
    $account = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', Auth::client()->id)
        ->select('id', 'packageid', 'username')
        ->first();
    if (!$account || $account->username !== $username) {
        echo json_encode(['status' => 'error', 'message' => 'Service not found or access denied']);
        exit;
    }

    $params = comet_ServiceParams($serviceId);
    $params['username'] = $username;
    $server = comet_Server($params);
    if (!($server instanceof \Comet\Server)) {
        throw new \Exception('Failed to initialize Comet server client');
    }

    // Helper: Build Comet base URL
    $buildBaseUrl = function(array $params): string {
        $scheme = $params['serverhttpprefix'] ?? 'https';
        $host   = $params['serverhostname'] ?? '';
        $port   = $params['serverport'] ?? '';
        return rtrim($scheme . '://' . $host . ($port ?? ''), '/') . '/';
    };

    // Helper: Send a Comet NetworkRequest (compat) using Guzzle directly
    $sendCompat = function(array $params, \Comet\NetworkRequest $nr) use ($buildBaseUrl) {
        $baseUrl = $buildBaseUrl($params);
        $client  = new \GuzzleHttp\Client(['base_uri' => $baseUrl, 'http_errors' => false]);
        $endpoint = ltrim($nr->Endpoint(), '/');
        $response = $client->request($nr->Method(), $endpoint, [
            'headers'     => [ 'Content-Type' => $nr->ContentType() ],
            'form_params' => $nr->Parameters(),
            'timeout'     => 15,
        ]);
        $status = $response->getStatusCode();
        $body   = (string)$response->getBody();
        $cls    = get_class($nr);
        if (!is_callable([$cls, 'ProcessResponse'])) {
            throw new \Exception('Incompatible compat request class');
        }
        return $cls::ProcessResponse($status, $body);
    };

    switch ($action) {
        case 'regenerate': {
            // Impersonate as user to obtain SessionKey (no user password required)
            $session = $server->AdminAccountSessionStartAsUser($username);
            $sessionKey = $session->SessionKey;

            // Get Profile + Hash first
            $getPH = new eazyBackup\CometCompat\UserWebGetUserProfileAndHashRequest($username, $sessionKey);
            $phRes = $sendCompat($params, $getPH);
            $profileHash = $phRes->ProfileHash;

            // Regenerate TOTP
            $regen = new eazyBackup\CometCompat\UserWebAccountRegenerateTotpRequest($username, $sessionKey, $profileHash);
            $regenRes = $sendCompat($params, $regen);

            echo json_encode([
                'status'      => 'success',
                'message'     => $regenRes->Message,
                'image'       => $regenRes->Image,
                'url'         => $regenRes->URL,
                'profileHash' => $regenRes->ProfileHash ?: $profileHash,
            ]);
            break;
        }

        case 'validate': {
            $code        = $postData['code']        ?? '';
            $profileHash = $postData['profileHash'] ?? '';
            if ($code === '' || $profileHash === '') {
                echo json_encode(['status' => 'error', 'message' => 'Missing code or profileHash']);
                break;
            }
            $session = $server->AdminAccountSessionStartAsUser($username);
            $sessionKey = $session->SessionKey;

            $validate = new eazyBackup\CometCompat\UserWebAccountValidateTotpRequest($username, $sessionKey, $profileHash, $code);
            $validateRes = $sendCompat($params, $validate);

            echo json_encode([
                'status'  => 'success',
                'message' => $validateRes->Message,
            ]);
            break;
        }

        case 'disable': {
            $resp = $server->AdminDisableUserTotp($username);
            if ($resp->Status >= 400) {
                echo json_encode(['status' => 'error', 'message' => $resp->Message, 'code' => $resp->Status]);
            } else {
                echo json_encode(['status' => 'success', 'message' => $resp->Message]);
            }
            break;
        }

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;


