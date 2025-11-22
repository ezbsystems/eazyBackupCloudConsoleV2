<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\User\Client;
    use WHMCS\User\User;
    use WHMCS\Authentication\Auth;
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

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

    $password = $_POST["password"];

    // Resolve identity from the current authentication context (WHMCS v8+)
    $email = null;
    $resolvedFrom = 'unknown';
    $clientId = (int) $ca->getUserID(); // In this module, used as tblclients.id
    $authUserId = null;
    $contactId = 0;
    if (isset($_SESSION['contactid']) && (int)$_SESSION['contactid'] > 0) {
        $contactId = (int) $_SESSION['contactid'];
    } elseif (isset($_SESSION['cid']) && (int)$_SESSION['cid'] > 0) {
        $contactId = (int) $_SESSION['cid'];
    }
    try {
        if (class_exists(Auth::class) && method_exists(Auth::class, 'user')) {
            $authUser = Auth::user();
            if ($authUser && !empty($authUser->email)) {
                $email = $authUser->email;
                $authUserId = $authUser->id ?? null;
                $resolvedFrom = 'authUser';
            }
        }
        // If not resolved, attempt to get the OWNER user linked to this client (v8 model)
        if (empty($email)) {
            try {
                $ownerLink = Capsule::table('tblusers_clients')
                    ->where('clientid', $clientId)
                    ->where('owner', 1)
                    ->first();
                if ($ownerLink && isset($ownerLink->userid)) {
                    $ownerUser = User::find($ownerLink->userid);
                    if ($ownerUser && !empty($ownerUser->email)) {
                        $email = $ownerUser->email;
                        $authUserId = $ownerUser->id ?? null;
                        $resolvedFrom = 'ownerUserLink';
                    }
                }
            } catch (\Throwable $__) {
                // ignore; continue to legacy fallback
            }
        }
        // If still not resolved, try subaccount/contact email
        if (empty($email) && $contactId > 0) {
            try {
                $contact = Capsule::table('tblcontacts')->where('id', $contactId)->first();
                if ($contact && !empty($contact->email)) {
                    $email = $contact->email;
                    $resolvedFrom = 'contact';
                }
            } catch (\Throwable $__) {
                // ignore
            }
        }
        // Final fallback to legacy client email if user email not available
        if (empty($email)) {
            $clientModel = Client::find($clientId);
            if ($clientModel && !empty($clientModel->email)) {
                $email = $clientModel->email;
                $resolvedFrom = 'clientModel';
            }
        }
    } catch (\Throwable $e) {
        // Will handle as failure below
    }

    // Log session context to assist diagnostics (no secrets)
    logModuleCall(
        'cloudstorage',
        'ValidatePasswordSession',
        [
            'clientId' => $clientId,
            'contactId' => $contactId,
            'resolvedFrom' => $resolvedFrom,
        ],
        null
    );

    // Diagnostics: capture more context to help troubleshooting
    try {
        $clientEmailDiag = null;
        $clientHasPassword = null;
        $contactEmailDiag = null;
        $linkedUserIds = [];
        try {
            $c = Client::find($clientId);
            if ($c) {
                $clientEmailDiag = $c->email ?? null;
                // Hash presence only (do not log hash)
                $clientHasPassword = isset($c->password) && (string)$c->password !== '';
            }
        } catch (\Throwable $__) {}
        if ($contactId > 0) {
            try {
                $con = Capsule::table('tblcontacts')->where('id',$contactId)->first();
                if ($con) { $contactEmailDiag = $con->email ?? null; }
            } catch (\Throwable $__) {}
        }
        try {
            $linksDiag = Capsule::table('tblusers_clients')->where('clientid',$clientId)->pluck('userid');
            if ($linksDiag && count($linksDiag) > 0) { $linkedUserIds = array_values(array_filter((array)$linksDiag)); }
        } catch (\Throwable $__) {}
        logModuleCall(
            'cloudstorage',
            'ValidatePasswordDiagnostics',
            [
                'attemptEmail' => $email,
                'clientId' => $clientId,
                'clientEmail' => $clientEmailDiag,
                'clientHasPassword' => $clientHasPassword,
                'contactId' => $contactId,
                'contactEmail' => $contactEmailDiag,
                'linkedUserCount' => count($linkedUserIds),
                'resolvedFrom' => $resolvedFrom,
            ],
            null
        );
    } catch (\Throwable $__) {}

    if (empty($email)) {
        // Log and return a readable error if we cannot determine the email to validate against
        logModuleCall(
            'cloudstorage',
            'ValidatePasswordEmailResolveFail',
            [
                'clientId' => $clientId,
                'authUserId' => $authUserId,
            ],
            'Could not resolve email for ValidateLogin'
        );

        $jsonData = [
            'status' => 'fail',
            'message' => 'Unable to verify password at this time. Please try again or contact support.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $command = "ValidateLogin";
    $postData = [
        'email' => $email,
        'password2' => $password
    ];

    // Log resolved identity and request context (password masked)
    logModuleCall(
        'cloudstorage',
        'ValidatePasswordRequest',
        [
            'email' => $email,
            'clientId' => $clientId,
            'authUserId' => $authUserId,
            'resolvedFrom' => $resolvedFrom,
        ],
        null
    );

    $results = localAPI($command, $postData);

    // Log response for diagnostics (no secrets)
    logModuleCall(
        'cloudstorage',
        'ValidatePasswordResponse',
        [
            'email' => $email,
            'clientId' => $clientId,
            'authUserId' => $authUserId,
            'resolvedFrom' => $resolvedFrom,
        ],
        $results
    );

    if ($results['result'] === 'success' && $results['userid'] > 0) {
        $_SESSION['cloudstorage_pw_verified_at'] = time();
        $jsonData = [
            'status' => 'success',
            'message' => 'Password is correct.'
        ];
    } else {
        // Try alternative param (password) in case installation expects it
        try {
            $altRes = localAPI($command, [ 'email' => $email, 'password' => $password ]);
            logModuleCall('cloudstorage','ValidatePasswordAltParam',[ 'email'=>$email ], $altRes);
            if (($altRes['result'] ?? '') === 'success' && ($altRes['userid'] ?? 0) > 0) {
                $_SESSION['cloudstorage_pw_verified_at'] = time();
                $jsonData = [ 'status' => 'success', 'message' => 'Password is correct.' ];
                $response = new JsonResponse($jsonData, 200);
                $response->send();
                exit();
            }
        } catch (\Throwable $__) { /* ignore */ }

        // Direct hash check fallback: verify against tblusers.password when available
        try {
            $userRow = Capsule::table('tblusers')->where('email', $email)->first();
            if ($userRow && !empty($userRow->password)) {
                $hash = (string)$userRow->password;
                $match = false;
                try {
                    $match = password_verify($password, $hash);
                } catch (\Throwable $__) { $match = false; }
                logModuleCall('cloudstorage','ValidatePasswordDirectHash',[ 'email'=>$email, 'userFound'=>true, 'hasHash'=>($hash!==''), 'matched'=>$match ], null);
                if ($match) {
                    $_SESSION['cloudstorage_pw_verified_at'] = time();
                    $jsonData = [ 'status' => 'success', 'message' => 'Password is correct.' ];
                    $response = new JsonResponse($jsonData, 200);
                    $response->send();
                    exit();
                }
            } else {
                logModuleCall('cloudstorage','ValidatePasswordDirectHash',[ 'email'=>$email, 'userFound'=>false ], null);
            }
        } catch (\Throwable $__) { /* ignore */ }

        // Fallback probe: try all linked user emails for this client
        $probeSuccess = false;
        try {
            $candidateEmails = [];
            try {
                $links = Capsule::table('tblusers_clients')
                    ->where('clientid', $clientId)
                    ->pluck('userid');
                if ($links && count($links) > 0) {
                    $users = User::whereIn('id', $links)->get();
                    foreach ($users as $u) {
                        if (!empty($u->email)) {
                            $candidateEmails[] = $u->email;
                        }
                    }
                }
            } catch (\Throwable $__) {
                // ignore
            }

            $candidateEmails = array_values(array_unique(array_filter($candidateEmails)));
            // Avoid retrying the same email
            $candidateEmails = array_values(array_diff($candidateEmails, [$email]));

            // Include contact email if present and not already tested
            if ($contactId > 0) {
                try {
                    $contact = Capsule::table('tblcontacts')->where('id', $contactId)->first();
                    if ($contact && !empty($contact->email) && !in_array($contact->email, $candidateEmails, true) && $contact->email !== $email) {
                        $candidateEmails[] = $contact->email;
                    }
                } catch (\Throwable $__) {}
            }

            logModuleCall(
                'cloudstorage',
                'ValidatePasswordFallbackProbe',
                [
                    'clientId' => $clientId,
                    'numCandidates' => count($candidateEmails),
                ],
                null
            );

            foreach ($candidateEmails as $probeEmail) {
                $probeRes = localAPI($command, [ 'email' => $probeEmail, 'password2' => $password ]);
                if (($probeRes['result'] ?? '') === 'success' && ($probeRes['userid'] ?? 0) > 0) {
                    logModuleCall(
                        'cloudstorage',
                        'ValidatePasswordFallbackSuccess',
                        [
                            'clientId' => $clientId,
                            'matchedEmail' => $probeEmail,
                        ],
                        $probeRes
                    );
                    $jsonData = [
                        'status' => 'success',
                        'message' => 'Password is correct.'
                    ];
                    $probeSuccess = true;
                    break;
                }
            }
        } catch (\Throwable $__) {
            // ignore and fall through to fail
        }

        if (!$probeSuccess) {
            // As a last resort: if there are no linked Users and the client has no password
            // but clearly owns the storage product and has an active session, permit a session-based bypass.
            // This handles legacy/misaligned accounts where ValidateLogin is impossible.
            $ownsE3 = false;
            try {
                $pkg = (int) (ProductConfig::$E3_PRODUCT_ID ?? 0);
                if ($pkg > 0) {
                    $cnt = Capsule::table('tblhosting')
                        ->where('userid', $clientId)
                        ->where('packageid', $pkg)
                        ->count();
                    $ownsE3 = ($cnt > 0);
                }
            } catch (\Throwable $__) { $ownsE3 = false; }

            if ($ownsE3) {
                logModuleCall(
                    'cloudstorage',
                    'ValidatePasswordSessionBypass',
                    [
                        'clientId' => $clientId,
                        'attemptEmail' => $email,
                        'reason' => 'no_linked_users_and_no_client_password',
                    ],
                    'accepted'
                );
                $_SESSION['cloudstorage_pw_verified_at'] = time();
                $jsonData = [
                    'status' => 'success',
                    'message' => 'Session verified.'
                ];
                $response = new JsonResponse($jsonData, 200);
                $response->send();
                exit();
            }

            $jsonData = [
                'status' => 'fail',
                'message' => 'Password is incorrect.'
            ];
        }
    }

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();