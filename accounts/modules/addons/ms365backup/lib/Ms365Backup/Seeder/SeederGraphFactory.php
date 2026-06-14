<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;

final class SeederGraphFactory
{
    public static function appClient(): GraphClient
    {
        $creds = SeederConfigRepository::credentials();
        $tokens = SeederTokenProvider::fromConfig();

        return new GraphClient($tokens, $creds['region']);
    }

    public static function delegatedClient(): GraphClient
    {
        $creds = SeederConfigRepository::credentials();
        $tokens = SeederDelegatedTokenProvider::fromConfig();

        return new GraphClient($tokens, $creds['region']);
    }
}
