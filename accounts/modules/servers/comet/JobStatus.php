<?php

namespace Comet;

class JobStatus
{
    public const UNKNOWN = 9999;
    public const SUCCESS = 5000;
    public const ACTIVE = 6001;
    public const REVIVED = 6002;
    public const TIMEOUT = 7000;
    public const WARNING = 7001;
    public const ERROR = 7002;
    public const FAILED_QUOTA = 7003;
    public const MISSED = 7004;
    public const CANCELLED = 7005;
    public const ALREADY_RUNNING = 7006;
    public const ABANDONED = 7007;

    /**
     * Get the string representation of a job status.
     *
     * @param int $value
     * @return string
     */
    public static function toString($value)
    {
        $values = [
            static::UNKNOWN => 'unknown',
            static::SUCCESS => 'completed',
            static::ACTIVE => 'started',
            static::REVIVED => 'revived',
            static::TIMEOUT => 'timed out',
            static::WARNING => 'had warnings',
            static::ERROR => 'failed',
            static::FAILED_QUOTA => 'failed',
            static::MISSED => 'missed',
            static::CANCELLED => 'cancelled',
            static::ALREADY_RUNNING => 'skipped',
            static::ABANDONED => 'abandoned',
        ];

        return $values[$value] ?? $values[static::UNKNOWN];
    }
}
