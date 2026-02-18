<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

class RepoSecretService
{
    public static function generateSecret(int $bytes = 32): string
    {
        if ($bytes < 32) {
            $bytes = 32;
        }

        return bin2hex(random_bytes($bytes));
    }
}
