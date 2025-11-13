<?php

namespace PartnerHub;

class WhmcsBridge
{
    /** Add a WHMCS Client via LocalAPI. Returns [result, clientid?, message?] */
    public static function addClient(array $payload, string $adminUser = 'API'): array
    {
        $res = localAPI('AddClient', $payload, $adminUser);
        return $res;
    }

    /** Update a WHMCS Client via LocalAPI. Returns [result, message?] */
    public static function updateClient(int $clientId, array $payload, string $adminUser = 'API'): array
    {
        $payload = array_merge(['clientid' => $clientId], $payload);
        $res = localAPI('UpdateClient', $payload, $adminUser);
        return $res;
    }

    /** Create a WHMCS User (admin-level user record). Returns [result, userid?, message?] */
    public static function addUser(array $payload, string $adminUser = 'API'): array
    {
        $res = localAPI('AddUser', $payload, $adminUser);
        return $res;
    }

    /** Associate an existing User to a Client. Returns [result, message?] */
    public static function addClientUser(int $userId, int $clientId, array $perms = [], string $adminUser = 'API'): array
    {
        $payload = [
            'client_id' => $clientId,
            'user_id' => $userId,
            'permissions' => $perms,
        ];
        $res = localAPI('AddClientUser', $payload, $adminUser);
        return $res;
    }
}


