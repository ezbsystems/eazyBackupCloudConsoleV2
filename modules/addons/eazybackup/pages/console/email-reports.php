<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

header('Content-Type: application/json');

try {
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
        exit;
    }

    $action    = (string)($post['action'] ?? '');
    $serviceId = (int)($post['serviceId'] ?? 0);
    $username  = (string)($post['username'] ?? '');
    if (!$action || $serviceId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        exit;
    }

    // Ensure ownership
    $account = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', Auth::client()->id)
        ->select('id', 'packageid', 'username')
        ->first();
    if (!$account) {
        echo json_encode(['status' => 'error', 'message' => 'Service not found or access denied']);
        exit;
    }
    // If username not supplied, or mismatched, prefer canonical from service record
    if ($username === '') {
        $username = $account->username;
    } elseif ($account->username !== $username) {
        echo json_encode(['status' => 'error', 'message' => 'Service not found or access denied']);
        exit;
    }

    $params = comet_ServiceParams($serviceId);
    $params['username'] = $username;
    $server = comet_Server($params);

    switch ($action) {
        case 'piProfileGet': {
            $ph = $server->AdminGetUserProfileAndHash($username);
            echo json_encode([
                'status'  => 'success',
                'hash'    => $ph->ProfileHash,
                'profile' => $ph->Profile ? $ph->Profile->toArray(true) : null,
            ]);
            break;
        }

        case 'updateEmailReports': {
            $enabled   = !!($post['enabled'] ?? false);
            $emails    = (array)($post['emails'] ?? []);
            $mode      = (string)($post['mode'] ?? 'default'); // 'default' | 'custom'
            $preset    = (string)($post['preset'] ?? 'warn_error');
            $hash      = (string)($post['hash'] ?? '');

            $ph = $server->AdminGetUserProfileAndHash($username);
            $prof = $ph->Profile; // \Comet\UserProfileConfig
            if (!$prof) {
                echo json_encode(['status' => 'error', 'message' => 'Profile not found']);
                break;
            }

            // Emails and enable flag
            $prof->SendEmailReports = (bool)$enabled;
            $prof->Emails = array_values(array_filter(array_map('strval', $emails), function($e){ return filter_var($e, FILTER_VALIDATE_EMAIL); }));

            // Configure per-email overrides for custom mode, otherwise reset to default behavior
            if ($mode === 'custom') {
                // Build SearchClause tree using SDK types
                $statusMap = [
                    'errors'            => [7002],
                    'warn_error'        => [7001, 7002],
                    'warn_error_missed' => [7001, 7002, 7004],
                    'success'           => [5000],
                ];
                $statuses = $statusMap[$preset] ?? [7001, 7002];

                $children = [];
                foreach ($statuses as $st) {
                    $children[] = \Comet\SearchClause::createFromArray([
                        'ClauseType'   => '',
                        'RuleField'    => 'BackupJobDetail.Status',
                        'RuleOperator' => \Comet\Def::SEARCHOPERATOR_INT_EQ,
                        'RuleValue'    => (string)$st,
                    ]);
                }
                $filter = \Comet\SearchClause::createFromArray([
                    'ClauseType'     => (count($children) > 1 ? 'or' : 'and'),
                    'ClauseChildren' => array_map(function($c){ return $c->toArray(); }, $children),
                ]);

                $report = \Comet\EmailReportConfig::createFromArray([
                    'ReportType'       => 0,
                    'SummaryFrequency' => [],
                    'Filter'           => $filter->toArray(),
                ]);

                // Build OverrideEmailSettings map per recipient
                $overrides = [];
                foreach ($prof->Emails as $em) {
                    $overrides[$em] = \Comet\UserCustomEmailSettings::createFromArray([
                        'Reports' => [ $report->toArray() ],
                    ]);
                }
                $prof->OverrideEmailSettings = $overrides;

                // Ensure policy object exists and disable DefaultEmailReports overrides to avoid collision
                if (!isset($prof->Policy) || !($prof->Policy instanceof \Comet\UserPolicy)) {
                    $prof->Policy = \Comet\UserPolicy::createFromArray([]);
                }
                if ($prof->Policy->DefaultEmailReports instanceof \Comet\DefaultEmailReportPolicy) {
                    $prof->Policy->DefaultEmailReports->ShouldOverrideDefaultReports = false;
                    $prof->Policy->DefaultEmailReports->Reports = [];
                } else {
                    $prof->Policy->DefaultEmailReports = \Comet\DefaultEmailReportPolicy::createFromArray([
                        'ShouldOverrideDefaultReports' => false,
                        'Reports' => [],
                    ]);
                }
            } else {
                // default mode: clear overrides and leave defaults
                $prof->OverrideEmailSettings = [];
                if (isset($prof->Policy) && $prof->Policy instanceof \Comet\UserPolicy && $prof->Policy->DefaultEmailReports instanceof \Comet\DefaultEmailReportPolicy) {
                    $prof->Policy->DefaultEmailReports->ShouldOverrideDefaultReports = false;
                    $prof->Policy->DefaultEmailReports->Reports = [];
                }
            }

            $resp = $server->AdminSetUserProfileHash($username, $prof, ($hash ?: $ph->ProfileHash));
            if ($resp->Status >= 400) {
                echo json_encode(['status' => 'error', 'message' => $resp->Message, 'code' => $resp->Status]);
            } else {
                echo json_encode(['status' => 'success']);
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


