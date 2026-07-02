<?php

namespace Comet;

use WHMCS\Database\Capsule;
use Carbon\Carbon;

class CometDevice
{
    public $id;
    public $hash;
    public $comet_user_id;
    public $content;
    public $name;
    public $platform;

    /**
     * Set the Comet device fields value
     */
    public static function setDevice($device)
    {
        $content = json_decode($device->content);
        $device->id = hash('sha256', $device->comet_user_id . $device->hash);
        $device->name = $content->FriendlyName;
        $device->platform = $content->PlatformVersion;

        return $device;
    }

    /**
     * Record the Comet devices history
     */
    public static function deviceHistory($device, $action)
    {
        try {
            $cometUser = Capsule::table('comet_users')->where('id', $device->comet_user_id)->first();
            if (!$cometUser) {
                throw new \Exception('Comet user not found');
            }
    
            $backup = Capsule::table('backup_plan_users')
                ->where('comet_username', $cometUser->username)
                ->where('comet_server_id', $cometUser->comet_server_id)
                ->first();
    
            if (!$backup) {
                throw new \Exception('Backup plan not found');
            }
    
            $deviceHistory = Capsule::table('comet_device_histories')
                ->where('backup_plan_id', $backup->id)
                ->where('expiry_date', $backup->expiry_date)
                ->where('comet_device_id', $device->id)
                ->where('comet_user_id', $device->comet_user_id)
                ->first();
    
            $now = Carbon::now();
    
            if ($action === 'ADD') {
                if ($deviceHistory) {
                    Capsule::table('comet_device_histories')
                        ->where('id', $deviceHistory->id)
                        ->update([
                            'added_date' => $now,
                            'device_removed_date' => null,
                        ]);
                } else {
                    Capsule::table('comet_device_histories')->insert([
                        'name' => $device->name,
                        'device_id' => $device->hash,
                        'comet_user_id' => $device->comet_user_id,
                        'comet_device_id' => $device->id,
                        'backup_plan_id' => $backup->id,
                        'added_date' => $now,
                        'expiry_date' => $backup->expiry_date,
                    ]);
                }
            } else { // action is 'REMOVE'
                if ($deviceHistory) {
                    Capsule::table('comet_device_histories')
                        ->where('id', $deviceHistory->id)
                        ->update([
                            'device_removed_date' => $now,
                        ]);
                } else {
                    Capsule::table('comet_device_histories')->insert([
                        'name' => $device->name,
                        'device_id' => $device->hash,
                        'comet_user_id' => $device->comet_user_id,
                        'comet_device_id' => $device->id,
                        'backup_plan_id' => $backup->id,
                        'added_date' => $now,
                        'expiry_date' => $backup->expiry_date,
                        'device_removed_date' => $now,
                    ]);
                }
            }
    
            Capsule::table('user_comet_histories')->insert([
                'content' => json_encode($device),
                'comet_user_id' => $device->comet_user_id,
                'user_id' => $cometUser->user_id,
                'parent_id' => $cometUser->parent_id,
                'backup_plan_id' => $backup->id,
                'type_id' => $device->hash,
                'type' => 'DEVICES',
                'action' => $action,
            ]);
        } catch (\Throwable $th) {
            error_log('CometDevice:', ['error' => $th->getMessage()]);
        }
    }
}    
