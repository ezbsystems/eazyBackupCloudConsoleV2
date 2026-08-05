<?php
namespace CometBilling;

/**
 * Pure Comet billing period helpers (no DB).
 *
 * Devices: inclusive periods [start, start+cycleDays], next period starts end+1 day.
 * Boosters: remove date is last billable day.
 */
class BillingPeriodCalculator
{
    private static ?string $dbTimeZone = null;
    private static bool $dbTimeZoneResolved = false;

    public static function dateOnly(string $dateTimeOrDate): string
    {
        return substr($dateTimeOrDate, 0, 10);
    }

    /**
     * Calendar date in UTC for a DB-local revoked_at timestamp.
     *
     * comet_devices.revoked_at is written with MySQL NOW() in session/system TZ
     * (America/Toronto on our WHMCS hosts). Comet portal usage_date values are UTC
     * calendar dates. Date-only inputs pass through unchanged.
     */
    public static function utcDateOnly(string $dateTimeOrDate): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTimeOrDate)) {
            return $dateTimeOrDate;
        }

        $normalized = strlen($dateTimeOrDate) >= 19
            ? substr($dateTimeOrDate, 0, 19)
            : self::dateOnly($dateTimeOrDate);

        $localTz = self::resolveDbTimeZone();
        try {
            $local = new \DateTimeImmutable($normalized, new \DateTimeZone($localTz));

            return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        } catch (\Throwable $e) {
            return self::dateOnly($dateTimeOrDate);
        }
    }

    public static function clearTimezoneCache(): void
    {
        self::$dbTimeZone = null;
        self::$dbTimeZoneResolved = false;
    }

    private static function resolveDbTimeZone(): string
    {
        if (self::$dbTimeZoneResolved) {
            return self::$dbTimeZone ?? 'America/Toronto';
        }

        self::$dbTimeZoneResolved = true;
        self::$dbTimeZone = 'America/Toronto';

        if (!class_exists(\WHMCS\Database\Capsule::class)) {
            return self::$dbTimeZone;
        }

        try {
            $row = \WHMCS\Database\Capsule::select(
                'SELECT @@session.time_zone AS session_tz, @@system_time_zone AS system_tz LIMIT 1'
            );
            if ($row !== []) {
                $sessionTz = trim((string) ($row[0]->session_tz ?? ''));
                $systemTz = trim((string) ($row[0]->system_tz ?? ''));
                $candidate = strtoupper($sessionTz) === 'SYSTEM' ? $systemTz : $sessionTz;
                self::$dbTimeZone = self::normalizeTimeZone($candidate);
            }
        } catch (\Throwable $e) {
            // Keep America/Toronto fallback.
        }

        return self::$dbTimeZone;
    }

    private static function normalizeTimeZone(string $timeZone): string
    {
        $timeZone = trim($timeZone);
        if ($timeZone === '') {
            return 'America/Toronto';
        }

        static $abbreviationMap = [
            'EDT' => 'America/Toronto',
            'EST' => 'America/Toronto',
            'CDT' => 'America/Winnipeg',
            'CST' => 'America/Winnipeg',
            'MDT' => 'America/Edmonton',
            'MST' => 'America/Edmonton',
            'PDT' => 'America/Vancouver',
            'PST' => 'America/Vancouver',
            'UTC' => 'UTC',
        ];

        $upper = strtoupper($timeZone);
        if (isset($abbreviationMap[$upper])) {
            return $abbreviationMap[$upper];
        }

        try {
            new \DateTimeZone($timeZone);

            return $timeZone;
        } catch (\Throwable $e) {
            return 'America/Toronto';
        }
    }

    /**
     * Expected billing end for a revoked device (Y-m-d), or null if unknown.
     *
     * Prefer portal next_due walk-back (Comet's actual billing calendar). Fall
     * back to RegistrationTime-aligned periods only when next_due is missing.
     */
    public static function deviceExpectedEnd(
        ?string $registrationDate,
        string $revokedDate,
        int $cycleDays,
        ?string $nextDueDate
    ): ?string {
        $cycleDays = $cycleDays > 0 ? $cycleDays : 30;
        $revoked = self::dateOnly($revokedDate);

        if ($nextDueDate !== null && $nextDueDate !== '') {
            $fromNextDue = self::walkBackFromNextDue(self::dateOnly($nextDueDate), $revoked, $cycleDays);
            if ($fromNextDue !== null) {
                return $fromNextDue;
            }
        }

        if ($registrationDate !== null && $registrationDate !== '') {
            $reg = self::dateOnly($registrationDate);
            $end = self::periodEndContaining($reg, $revoked, $cycleDays);
            if ($end !== null) {
                return $end;
            }
        }

        return null;
    }

    public static function deviceBillingStatus(?string $expectedEnd, ?string $nextDueDate): string
    {
        if ($expectedEnd === null || $expectedEnd === '') {
            return 'unknown';
        }
        if ($nextDueDate === null || $nextDueDate === '') {
            return 'unknown';
        }
        $nextDue = self::dateOnly($nextDueDate);
        $end = self::dateOnly($expectedEnd);

        return $nextDue > $end ? 'overbilled_past_grace' : 'expected_grace';
    }

    public static function boosterBillingStatus(?string $removeDate, string $snapshotDate): string
    {
        if ($removeDate === null || $removeDate === '') {
            return 'unknown';
        }
        $remove = self::dateOnly($removeDate);
        $snap = self::dateOnly($snapshotDate);

        return $snap > $remove ? 'overbilled_past_grace' : 'expected_grace';
    }

    /**
     * From registration anchor, find inclusive period end containing $revoked.
     */
    private static function periodEndContaining(string $regDate, string $revoked, int $cycleDays): ?string
    {
        if ($revoked < $regDate) {
            return null;
        }

        $start = $regDate;
        // Cap iterations (e.g. 40 years of monthly-ish cycles)
        for ($i = 0; $i < 500; $i++) {
            $end = date('Y-m-d', strtotime($start . ' +' . $cycleDays . ' days'));
            if ($revoked >= $start && $revoked <= $end) {
                return $end;
            }
            $start = date('Y-m-d', strtotime($end . ' +1 day'));
        }

        return null;
    }

    /**
     * Periods ending at nextDue, nextDue-cycle, ... until one contains revoked.
     * Period ending on $periodEnd has start = periodEnd - cycleDays.
     */
    private static function walkBackFromNextDue(string $nextDue, string $revoked, int $cycleDays): ?string
    {
        $periodEnd = $nextDue;
        for ($i = 0; $i < 500; $i++) {
            $start = date('Y-m-d', strtotime($periodEnd . ' -' . $cycleDays . ' days'));
            if ($revoked >= $start && $revoked <= $periodEnd) {
                return $periodEnd;
            }
            if ($revoked > $periodEnd) {
                // revoke after known next_due horizon — treat next_due as too early; unknown
                return null;
            }
            $periodEnd = date('Y-m-d', strtotime($start . ' -1 day'));
        }

        return null;
    }
}
