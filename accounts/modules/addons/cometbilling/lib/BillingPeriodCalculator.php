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
    public static function dateOnly(string $dateTimeOrDate): string
    {
        return substr($dateTimeOrDate, 0, 10);
    }

    /**
     * Expected billing end for a revoked device (Y-m-d), or null if unknown.
     */
    public static function deviceExpectedEnd(
        ?string $registrationDate,
        string $revokedDate,
        int $cycleDays,
        ?string $nextDueDate
    ): ?string {
        $cycleDays = $cycleDays > 0 ? $cycleDays : 30;
        $revoked = self::dateOnly($revokedDate);

        if ($registrationDate !== null && $registrationDate !== '') {
            $reg = self::dateOnly($registrationDate);
            $end = self::periodEndContaining($reg, $revoked, $cycleDays);
            if ($end !== null) {
                return $end;
            }
        }

        if ($nextDueDate !== null && $nextDueDate !== '') {
            return self::walkBackFromNextDue(self::dateOnly($nextDueDate), $revoked, $cycleDays);
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
