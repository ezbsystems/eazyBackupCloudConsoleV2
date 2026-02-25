<?php
namespace WHMCS\Module\Addon\CloudStorage\Client;

final class AgentIdentity
{
    public static function isValidUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim($value));
    }
}
