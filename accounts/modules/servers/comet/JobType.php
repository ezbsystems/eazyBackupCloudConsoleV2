<?php

namespace Comet;

class JobType
{
    public const UNKNOWN = 4000;
    public const BACKUP = 4001;
    public const RESTORE = 4002;
    public const RETENTION = 4003;
    public const UNLOCK = 4004;
    public const DELETE_CUSTOM = 4005;
    public const REMEASURE = 4006;
    public const UPDATE = 4007;
    public const IMPORT = 4008;
    public const REINDEX = 4009;
    public const DEEP_VERIFY = 4010;
    public const UNINSTALL = 4011;

    /**
     * Get the string representation of a job type.
     *
     * @param int $value
     * @return string
     */
    public static function toString($value)
    {
        $values = [
            static::UNKNOWN => 'unknown',
            static::BACKUP => 'backup',
            static::RESTORE => 'restore',
            static::RETENTION => 'retention clean',
            static::UNLOCK => 'vault unlock',
            static::DELETE_CUSTOM => 'snapshot delete',
            static::REMEASURE => 'vault remeasure',
            static::UPDATE => 'client update',
            static::IMPORT => 'import',
            static::REINDEX => 'index repair',
            static::DEEP_VERIFY => 'deep verify',
            static::UNINSTALL => 'client uninstall',
        ];

        return $values[$value] ?? $values[static::UNKNOWN];
    }
}
