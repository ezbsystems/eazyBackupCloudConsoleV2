<?php

namespace Comet;

use WHMCS\Database\Capsule;

class CometUser
{
    // Properties
    public $id;
    public $content;
    public $user_id;
    public $comet_server_id;
    public $account_name;
    public $username;
    public $locale;
    public $timezone;
    public $is_suspended;
    public $has_items_limit;
    public $items_limit_bytes;
    public $device_quota;
    public $comet_policy_id;
    public $created_at;
    public $updated_at;

    public static function setUser($user)
    {
        $createdAt = date('Y-m-d H:i:s', $user->content['CreateTime']);
        $user->account_name = $user->content['AccountName'];
        $user->username = $user->content['Username'];
        $user->locale = $user->content['LanguageCode'];
        $user->timezone = $user->content['LocalTimezone'];
        $user->is_suspended = $user->content['IsSuspended'];
        $user->has_items_limit = $user->content['AllProtectedItemsQuotaEnabled'];
        $user->items_limit_bytes = $user->content['AllProtectedItemsQuotaBytes'];
        $user->device_quota = $user->content['MaximumDevices'];
        $user->comet_policy_id = $user->content['PolicyID'];
        $user->created_at = $createdAt;

        return $user;
    }

    public static function findOrCreate($cometUserId, $cometServerId)
    {
        $cometUser = Capsule::table('comet_users')->where('id', $cometUserId)->first();
        if (!$cometUser) {
            $cometUser = new CometUser();
            $cometUser->id = $cometUserId;
            $cometUser->comet_server_id = $cometServerId;
            Capsule::table('comet_users')->insert((array)$cometUser);
        }
        return $cometUser;
    }
}
