<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Helper for cloud backup UUID-to-binary DB boundary conversions.
 * Validates UUID strings and produces MySQL 8 UUID_TO_BIN/BIN_TO_UUID expressions.
 */
class UuidBinary
{
    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Check if value is a valid UUID string.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isUuid($value): bool
    {
        return is_string($value) && preg_match(self::UUID_REGEX, $value);
    }

    /**
     * Validate and normalize UUID to lowercase.
     *
     * @param string $uuid
     * @return string Lowercase canonical UUID
     * @throws \InvalidArgumentException if not a valid UUID
     */
    public static function normalize(string $uuid): string
    {
        $trimmed = trim($uuid);
        if (!preg_match(self::UUID_REGEX, $trimmed)) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }
        return strtolower($trimmed);
    }

    /**
     * Return UUID_TO_BIN SQL expression for use with a trusted normalized UUID.
     *
     * @param string $uuid Normalized UUID string (from normalize())
     * @return string e.g. "UUID_TO_BIN('018f1234-5678-7abc-def0-123456789abc')"
     */
    public static function toDbExpr(string $uuid): string
    {
        $norm = self::normalize($uuid);
        return "UUID_TO_BIN('" . addslashes($norm) . "')";
    }

    /**
     * Return BIN_TO_UUID SQL select expression.
     *
     * @param string $column Column name (e.g. run_id, job_id)
     * @param string $alias Result alias, default 'id'
     * @return string e.g. "BIN_TO_UUID(run_id) AS id"
     */
    public static function fromDbExpr(string $column, string $alias = 'id'): string
    {
        $col = preg_replace('/[^a-zA-Z0-9_.]/', '', $column);
        $al = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        return "BIN_TO_UUID({$col}) AS {$al}";
    }
}
