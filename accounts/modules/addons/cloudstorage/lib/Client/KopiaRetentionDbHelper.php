<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Illuminate\Database\QueryException;

/**
 * Shared DB helpers for Kopia retention services.
 */
class KopiaRetentionDbHelper
{
    /**
     * Detect MySQL duplicate key / unique constraint violations.
     *
     * @param QueryException $e
     * @return bool
     */
    public static function isDuplicateKeyException(QueryException $e): bool
    {
        $code = $e->getCode();
        if ($code === '23000' || $code === 23000 || $code === 1062) {
            return true;
        }
        $msg = $e->getMessage();
        return str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062');
    }
}
