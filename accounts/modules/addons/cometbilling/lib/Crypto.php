<?php
namespace CometBilling;

class Crypto
{
    public static function enc(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        return encrypt($plain);
    }

    public static function dec(?string $cipher): string
    {
        if (!$cipher) {
            return '';
        }
        return decrypt($cipher);
    }
}


