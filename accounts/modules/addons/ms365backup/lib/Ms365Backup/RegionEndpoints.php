<?php
declare(strict_types=1);

namespace Ms365Backup;

final class RegionEndpoints
{
    /** @return array{login: string, graph: string} */
    public static function forRegion(string $region): array
    {
        return match ($region) {
            'USGovernment' => [
                'login' => 'https://login.microsoftonline.us',
                'graph' => 'https://graph.microsoft.us',
            ],
            'China' => [
                'login' => 'https://login.chinacloudapi.cn',
                'graph' => 'https://microsoftgraph.chinacloudapi.cn',
            ],
            'Germany' => [
                'login' => 'https://login.microsoftonline.de',
                'graph' => 'https://graph.microsoft.de',
            ],
            default => [
                'login' => 'https://login.microsoftonline.com',
                'graph' => 'https://graph.microsoft.com',
            ],
        };
    }

    /** @return list<string> */
    public static function allowedRegions(): array
    {
        return ['GlobalPublicCloud', 'USGovernment', 'China', 'Germany'];
    }
}
