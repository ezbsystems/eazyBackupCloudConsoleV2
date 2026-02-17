<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\User\User;
    use WHMCS\Authentication\Auth;
    use WHMCS\Database\Capsule;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Session timeout.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    if (!isset($_POST['password']) || empty($_POST['password'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Please enter the password.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $password = (string) $_POST["password"];

    // Resolve tblusers_clients column names across WHMCS schema variants.
    $linkUserCol = 'userid';
    $linkClientCol = 'clientid';
    try {
        if (Capsule::schema()->hasColumn('tblusers_clients', 'auth_user_id')) {
            $linkUserCol = 'auth_user_id';
        }
        if (Capsule::schema()->hasColumn('tblusers_clients', 'client_id')) {
            $linkClientCol = 'client_id';
        }
    } catch (\Throwable $__) {
        // keep legacy defaults when schema introspection is unavailable
    }

    // Resolve active client account first, then resolve owner for that client.
    $clientId = 0;
    $loggedInUserId = 0;
    $resolvedClientFrom = 'unknown';
    $caId = (int) $ca->getUserID();
    try {
        if (class_exists(Auth::class) && method_exists(Auth::class, 'user')) {
            $authUser = Auth::user();
            if ($authUser && isset($authUser->id)) {
                $loggedInUserId = (int) $authUser->id;
            }
        }
    } catch (\Throwable $__) {
        // ignore
    }
    try {
        if (class_exists(Auth::class) && method_exists(Auth::class, 'client')) {
            $authClient = Auth::client();
            if ($authClient && isset($authClient->id)) {
                $clientId = (int) $authClient->id;
                $resolvedClientFrom = 'authClient';
            }
        }
    } catch (\Throwable $__) {
        // ignore
    }

    // In WHMCS client area, session uid is the most reliable active account id.
    if ($clientId <= 0 && isset($_SESSION['uid']) && (int) $_SESSION['uid'] > 0) {
        $clientId = (int) $_SESSION['uid'];
        $resolvedClientFrom = 'sessionUid';
    }

    // Some installs expose the active client account via ClientArea::getUserID().
    if ($clientId <= 0 && $caId > 0) {
        try {
            $caIsClient = Capsule::table('tblclients')->where('id', $caId)->exists();
            if ($caIsClient) {
                if ($loggedInUserId > 0) {
                    $isLinked = Capsule::table('tblusers_clients')
                        ->where($linkClientCol, $caId)
                        ->where($linkUserCol, $loggedInUserId)
                        ->exists();
                    if ($isLinked) {
                        $clientId = $caId;
                        $resolvedClientFrom = 'caUserIdAsClientLinked';
                    }
                } else {
                    $clientId = $caId;
                    $resolvedClientFrom = 'caUserIdAsClient';
                }
            }
        } catch (\Throwable $__) {
            // ignore
        }
    }

    // Fallback: derive client account from authenticated WHMCS user link.
    if ($clientId <= 0 && $loggedInUserId > 0) {
        try {
            $link = Capsule::table('tblusers_clients')
                ->where($linkUserCol, $loggedInUserId)
                ->orderBy('owner', 'desc')
                ->first();
            if ($link && isset($link->{$linkClientCol})) {
                $clientId = (int) $link->{$linkClientCol};
                $resolvedClientFrom = 'authUserLink';
            }
        } catch (\Throwable $__) {
            // ignore
        }
    }

    // Last-resort legacy fallback: if getUserID is not a client id, treat it as auth user id.
    if ($clientId <= 0 && $caId > 0) {
        try {
            $caIsClient = Capsule::table('tblclients')->where('id', $caId)->exists();
            if (!$caIsClient) {
                $link = Capsule::table('tblusers_clients')
                    ->where($linkUserCol, $caId)
                    ->orderBy('owner', 'desc')
                    ->first();
                if ($link && isset($link->{$linkClientCol})) {
                    $clientId = (int) $link->{$linkClientCol};
                    $resolvedClientFrom = 'caUserIdAsAuthUser';
                }
            }
        } catch (\Throwable $__) {
            // ignore
        }
    }

    if ($clientId <= 0) {
        logModuleCall(
            'cloudstorage',
            'ValidatePasswordClientResolveFail',
            [
                'caUserId' => $caId,
                'loggedInUserId' => $loggedInUserId,
            ],
            'Unable to resolve active client account for password verification'
        );
        $jsonData = [
            'status' => 'fail',
            'message' => 'Unable to verify password at this time. Please try again or contact support.'
        ];
        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $ownerUserId = 0;
    $ownerEmail = '';
    try {
        $ownerLink = Capsule::table('tblusers_clients')
            ->where($linkClientCol, $clientId)
            ->where('owner', 1)
            ->first();
        if ($ownerLink && isset($ownerLink->{$linkUserCol})) {
            $ownerUserId = (int) $ownerLink->{$linkUserCol};
            $ownerUser = User::find($ownerUserId);
            if ($ownerUser && !empty($ownerUser->email)) {
                $ownerEmail = (string) $ownerUser->email;
            }
        }
    } catch (\Throwable $__) {
        // ignore and fail below
    }

    logModuleCall(
        'cloudstorage',
        'ValidatePasswordOwnerContext',
        [
            'clientId' => $clientId,
            'loggedInUserId' => $loggedInUserId,
            'resolvedClientFrom' => $resolvedClientFrom,
            'linkUserCol' => $linkUserCol,
            'linkClientCol' => $linkClientCol,
            'ownerUserId' => $ownerUserId,
            'ownerEmailResolved' => ($ownerEmail !== ''),
        ],
        null
    );

    if ($ownerUserId <= 0 || $ownerEmail === '') {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Unable to verify password at this time. Please contact support.'
        ];
        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $results = localAPI('ValidateLogin', [
        'email' => $ownerEmail,
        'password2' => $password,
    ]);

    $resultStatus = (string) ($results['result'] ?? '');
    $resultUserId = (int) ($results['userid'] ?? 0);
    $userIdMatchesOwner = ($resultUserId > 0 && $resultUserId === $ownerUserId);
    $isSuccess = ($resultStatus === 'success' && $userIdMatchesOwner);

    logModuleCall(
        'cloudstorage',
        'ValidatePasswordOwnerResult',
        [
            'clientId' => $clientId,
            'ownerUserId' => $ownerUserId,
            'apiResult' => $resultStatus,
            'apiUserId' => $resultUserId,
            'userIdMatchesOwner' => $userIdMatchesOwner,
            'twoFactorEnabled' => $results['twoFactorEnabled'] ?? null,
        ],
        null
    );

    if ($isSuccess) {
        $_SESSION['cloudstorage_pw_verified_at'] = time();
        $jsonData = [
            'status' => 'success',
            'message' => 'Password is correct.'
        ];
    } else {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Password is incorrect.'
        ];
    }

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();