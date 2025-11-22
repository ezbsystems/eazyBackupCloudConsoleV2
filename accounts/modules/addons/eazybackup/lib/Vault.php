<?php

use Comet\RetentionPolicy;
use WHMCS\Database\Capsule;

class Vault
{
    private $cometServer;
    private $username;
    private $params;

    public function __construct($params)
    {
        $this->params = $params;
        $this->username = $params['username'];
        $this->cometServer = comet_Server($params);
    }

    public function updateVault($vaultId, $vaultName, $vaultQuota, $retentionRules)
    {
        try {
            $userProfile = $this->cometServer->AdminGetUserProfile($this->username);

            if (isset($userProfile->Destinations[$vaultId])) {
                // Update Vault Name
                if ($vaultName !== null) {
                    $userProfile->Destinations[$vaultId]->Description = $vaultName;
                }

                // Update Vault Quota
                if ($vaultQuota !== null) {
                    if (isset($vaultQuota['unlimited']) && $vaultQuota['unlimited']) {
                        $userProfile->Destinations[$vaultId]->StorageLimitEnabled = false;
                        $userProfile->Destinations[$vaultId]->StorageLimitBytes = 0;
                    } else {
                        $userProfile->Destinations[$vaultId]->StorageLimitEnabled = true;
                        $userProfile->Destinations[$vaultId]->StorageLimitBytes = $this->convertToBytes($vaultQuota['size'], $vaultQuota['unit']);
                    }
                }

                // Apply Retention Rules
                if (is_array($retentionRules)) {
                    $ret = new \stdClass();
                    $ret->Mode = 802; // default retention mode
                    $ranges = [];
                    foreach ($retentionRules as $r) {
                        if (!is_array($r)) { continue; }
                        $type = isset($r['Type']) ? (int)$r['Type'] : (int)($r['type'] ?? 0);
                        if ($type <= 0) { continue; }
                        $o = new \stdClass();
                        $o->Type = $type;
                        $o->Timestamp = 0;
                        $o->Jobs = isset($r['Jobs']) ? (int)$r['Jobs'] : (int)($r['jobs'] ?? 0);
                        $o->Days = isset($r['Days']) ? (int)$r['Days'] : (int)($r['days'] ?? 0);
                        $o->Weeks = isset($r['Weeks']) ? (int)$r['Weeks'] : (int)($r['weeks'] ?? 0);
                        $o->Months = isset($r['Months']) ? (int)$r['Months'] : (int)($r['months'] ?? 0);
                        $o->Years = isset($r['Years']) ? (int)$r['Years'] : (int)($r['years'] ?? 0);
                        $o->WeekOffset = isset($r['WeekOffset']) ? (int)$r['WeekOffset'] : (int)($r['weekOffset'] ?? 0);
                        $o->MonthOffset = isset($r['MonthOffset']) ? (int)$r['MonthOffset'] : (int)($r['monthOffset'] ?? 1);
                        $o->YearOffset = isset($r['YearOffset']) ? (int)$r['YearOffset'] : (int)($r['yearOffset'] ?? 1);

                        // Normalize per known types
                        switch ($type) {
                            case 902: // keep all in last X days
                                $o->Jobs = 0; $o->Weeks = 0; $o->Months = 0; $o->Years = 0; break;
                            case 903: // daily one for last X days
                                $o->Jobs = 0; $o->Weeks = 0; $o->Months = 0; $o->Years = 0; break;
                            case 906: // weekly on weekOffset for last X weeks
                                $o->Jobs = 0; $o->Days = 0; $o->Months = 0; $o->Years = 0; break;
                            case 905: // monthly on monthOffset for last X months
                                $o->Jobs = 0; $o->Days = 0; $o->Weeks = 0; $o->Years = 0; if ($o->MonthOffset <= 0) $o->MonthOffset = 1; break;
                            case 911: // yearly, last X years
                                $o->Jobs = 0; $o->Days = 0; $o->Weeks = 0; $o->Months = 0; if ($o->YearOffset <= 0) $o->YearOffset = 1; break;
                            default:
                                // treat as generic; leave fields as provided
                                break;
                        }
                        $ranges[] = $o;
                    }
                    $ret->Ranges = $ranges;
                    $userProfile->Destinations[$vaultId]->DefaultRetention = $ret;
                }

                $this->cometServer->AdminSetUserProfile($this->username, $userProfile);
                comet_ClearUserCache();
                return ['status' => 'success', 'message' => 'Vault updated successfully.'];
            }

            return ['status' => 'error', 'message' => 'Vault not found.'];
        } catch (\Exception $e) {
            logModuleCall(
                "comet",
                __FUNCTION__,
                $this->params,
                $e->getMessage(),
                $e->getTraceAsString()
            );

            return ['status' => 'error', 'message' => 'Error updating vault: ' . $e->getMessage()];
        }
    }

    public function deleteVault($vaultId)
    {
        try {
            $userProfile = $this->cometServer->AdminGetUserProfile($this->username);

            if (isset($userProfile->Destinations[$vaultId])) {
                unset($userProfile->Destinations[$vaultId]);
                $this->cometServer->AdminSetUserProfile($this->username, $userProfile);
                comet_ClearUserCache();
                return ['status' => 'success'];
            }

            return ['status' => 'error', 'message' => 'Vault not found.'];
        } catch (\Exception $e) {
            logModuleCall(
                "comet",
                __FUNCTION__,
                $this->params,
                $e->getMessage(),
                $e->getTraceAsString()
            );

            return ['status' => 'error', 'message' => 'Error deleting vault: ' . $e->getMessage()];
        }
    }

    public function applyRetention($vaultId, $retentionRules)
    {
        try {
            // The target ID for dispatcher actions is the user's ID in Comet
            $targetId = $this->cometServer->AdminGetUserProfile($this->username)->Username;

            $this->cometServer->AdminDispatcherApplyRetentionRules($targetId, $vaultId);
            comet_ClearUserCache();
            return ['status' => 'success', 'message' => 'Retention policy applied successfully.'];
        } catch (\Exception $e) {
            logModuleCall(
                "comet",
                __FUNCTION__,
                $this->params,
                $e->getMessage(),
                $e->getTraceAsString()
            );

            return ['status' => 'error', 'message' => 'Error applying retention policy: ' . $e->getMessage()];
        }
    }

    private function convertToBytes($size, $unit)
    {
        $unit = strtoupper($unit);
        $size = (int)$size;
        switch ($unit) {
            case 'GB':
                return $size * pow(1024, 3);
            case 'TB':
                return $size * pow(1024, 4);
            default:
                return 0;
        }
    }
}