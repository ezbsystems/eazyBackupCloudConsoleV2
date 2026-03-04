<?php

use WHMCS\Database\Capsule as DB;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

return (function () {
    $req = $_GET + [];

    $nowYear = (int)date('Y');
    $year = isset($req['year']) ? (int)$req['year'] : $nowYear;
    $month = isset($req['month']) ? (int)$req['month'] : (int)date('n');
    $page = max(1, (int)($req['page'] ?? 1));
    $perPage = max(1, min(2000, (int)($req['perPage'] ?? 50)));
    $sort = isset($req['sort']) ? (string)$req['sort'] : 'renewal_date';
    $dir = strtolower((string)($req['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

    if ($year < 2000 || $year > 2100) {
        $year = $nowYear;
    }
    if ($month < 1 || $month > 12) {
        $month = (int)date('n');
    }

    $start = sprintf('%04d-%02d-01', $year, $month);
    $targetStart = new DateTimeImmutable($start . ' 00:00:00');

    $cycleMonthsMap = [
        'Monthly' => 1,
        'Quarterly' => 3,
        'Semi-Annually' => 6,
        'Annually' => 12,
        'Biennially' => 24,
        'Triennially' => 36,
    ];
    $allowedSorts = ['username', 'billingcycle', 'amount', 'renewal_date'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'renewal_date';
    }

    $parseDate = function (?string $value): ?DateTimeImmutable {
        $v = trim((string)$value);
        if ($v === '' || $v === '0000-00-00') {
            return null;
        }
        try {
            return new DateTimeImmutable($v . ' 00:00:00');
        } catch (\Throwable $e) {
            return null;
        }
    };

    $monthDiff = function (DateTimeImmutable $from, DateTimeImmutable $to): int {
        $fromYm = ((int)$from->format('Y') * 12) + (int)$from->format('n');
        $toYm = ((int)$to->format('Y') * 12) + (int)$to->format('n');
        return $toYm - $fromYm;
    };

    $projectDateWithAnchorDay = function (DateTimeImmutable $anchor, DateTimeImmutable $targetMonthStart): DateTimeImmutable {
        $anchorDay = (int)$anchor->format('j');
        $targetYear = (int)$targetMonthStart->format('Y');
        $targetMonth = (int)$targetMonthStart->format('n');
        $daysInTargetMonth = cal_days_in_month(CAL_GREGORIAN, $targetMonth, $targetYear);
        $day = min($anchorDay, $daysInTargetMonth);
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $targetYear, $targetMonth, $day));
    };

    $forecastRows = [];
    $services = DB::table('tblhosting as h')
        ->join('tblclients as c', 'c.id', '=', 'h.userid')
        ->where('h.domainstatus', '=', 'Active')
        ->whereNotIn('h.billingcycle', ['Free Account', 'One Time'])
        ->select([
            'h.id as service_id',
            'h.userid as user_id',
            'h.username',
            'h.billingcycle',
            'h.amount',
            'h.nextduedate',
            'h.nextinvoicedate',
            'h.regdate',
            'c.taxexempt',
            'c.country',
            'c.state',
        ])
        ->get();

    foreach ($services as $svc) {
        $billingCycle = (string)($svc->billingcycle ?? '');
        $cycleMonths = (int)($cycleMonthsMap[$billingCycle] ?? 0);
        if ($cycleMonths <= 0) {
            continue;
        }

        $anchorDate =
            $parseDate((string)($svc->nextinvoicedate ?? '')) ?:
            $parseDate((string)($svc->nextduedate ?? '')) ?:
            $parseDate((string)($svc->regdate ?? ''));

        if ($anchorDate === null) {
            continue;
        }

        $monthsBetween = $monthDiff($anchorDate, $targetStart);
        if ($monthsBetween < 0) {
            continue;
        }
        if (($monthsBetween % $cycleMonths) !== 0) {
            continue;
        }

        $projectedRenewalDate = $projectDateWithAnchorDay($anchorDate, $targetStart);
        $forecastRows[] = (object)[
            'service_id' => (int)$svc->service_id,
            'user_id' => (int)$svc->user_id,
            'username' => (string)($svc->username ?? ''),
            'billingcycle' => $billingCycle,
            'amount' => (float)($svc->amount ?? 0),
            'renewal_date' => $projectedRenewalDate->format('Y-m-d'),
            'nextduedate' => (string)($svc->nextduedate ?? ''),
            'taxexempt' => (int)($svc->taxexempt ?? 0),
            'country' => (string)($svc->country ?? ''),
            'state' => (string)($svc->state ?? ''),
        ];
    }

    $cycleSortRank = [
        'monthly' => 1,
        'quarterly' => 2,
        'semi-annually' => 3,
        'annually' => 4,
        'biennially' => 5,
        'triennially' => 6,
    ];

    usort($forecastRows, function ($a, $b) use ($sort, $dir, $cycleSortRank) {
        $cmp = 0;
        if ($sort === 'username') {
            $cmp = strcasecmp((string)$a->username, (string)$b->username);
        } elseif ($sort === 'billingcycle') {
            $aRank = (int)($cycleSortRank[strtolower((string)$a->billingcycle)] ?? 999);
            $bRank = (int)($cycleSortRank[strtolower((string)$b->billingcycle)] ?? 999);
            $cmp = $aRank <=> $bRank;
            if ($cmp === 0) {
                $cmp = strcasecmp((string)$a->billingcycle, (string)$b->billingcycle);
            }
        } elseif ($sort === 'amount') {
            $cmp = ((float)$a->amount <=> (float)$b->amount);
        } else {
            $cmp = strcmp((string)$a->renewal_date, (string)$b->renewal_date);
        }

        if ($cmp === 0) {
            $cmp = strcasecmp((string)$a->username, (string)$b->username);
        }
        if ($cmp === 0) {
            $cmp = ((int)$a->service_id <=> (int)$b->service_id);
        }

        return $dir === 'desc' ? -$cmp : $cmp;
    });

    $totalRows = count($forecastRows);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $rows = array_slice($forecastRows, $offset, $perPage);

    $rowsOut = [];
    $totalAmount = 0.0;
    $totalTax = 0.0;
    $taxRateCache = [];

    $resolveTaxRates = function (string $country, string $state) use (&$taxRateCache): array {
        $cacheKey = strtoupper(trim($country)) . '|' . strtoupper(trim($state));
        if (isset($taxRateCache[$cacheKey])) {
            return $taxRateCache[$cacheKey];
        }

        $findRateForLevel = function (int $level) use ($country, $state): float {
            try {
                $row = DB::table('tbltax')
                    ->where('level', '=', $level)
                    ->where(function ($q) use ($country) {
                        $q->where('country', '=', '')
                          ->orWhere('country', '=', $country);
                    })
                    ->where(function ($q) use ($state) {
                        $q->where('state', '=', '')
                          ->orWhere('state', '=', $state);
                    })
                    // Prefer the most specific match: country+state, then country-only, then global.
                    ->orderByRaw('CASE WHEN country = ? THEN 1 ELSE 0 END DESC', [$country])
                    ->orderByRaw('CASE WHEN state = ? THEN 1 ELSE 0 END DESC', [$state])
                    ->orderBy('id', 'asc')
                    ->select(['taxrate'])
                    ->first();
            } catch (\Throwable $e) {
                $row = null;
            }

            return isset($row->taxrate) ? (float)$row->taxrate : 0.0;
        };

        $rates = [
            'taxrate1' => $findRateForLevel(1),
            'taxrate2' => $findRateForLevel(2),
        ];
        $taxRateCache[$cacheKey] = $rates;

        return $rates;
    };

    // Compute totals across ALL matching services for the selected month (not just current page).
    foreach ($forecastRows as $r) {
        $amount = (float)($r->amount ?? 0);
        $isTaxExempt = (int)($r->taxexempt ?? 0) === 1;
        $country = (string)($r->country ?? '');
        $state = (string)($r->state ?? '');
        $rates = $resolveTaxRates($country, $state);
        $taxRate1 = (float)($rates['taxrate1'] ?? 0);
        $taxRate2 = (float)($rates['taxrate2'] ?? 0);
        $combinedRate = $isTaxExempt ? 0.0 : ($taxRate1 + $taxRate2);
        $taxAmount = round($amount * ($combinedRate / 100), 2);

        $totalAmount += $amount;
        $totalTax += $taxAmount;
    }

    foreach ($rows as $r) {
        $amount = (float)($r->amount ?? 0);
        $isTaxExempt = (int)($r->taxexempt ?? 0) === 1;
        $country = (string)($r->country ?? '');
        $state = (string)($r->state ?? '');
        $rates = $resolveTaxRates($country, $state);
        $taxRate1 = (float)($rates['taxrate1'] ?? 0);
        $taxRate2 = (float)($rates['taxrate2'] ?? 0);
        $combinedRate = $isTaxExempt ? 0.0 : ($taxRate1 + $taxRate2);
        $taxAmount = round($amount * ($combinedRate / 100), 2);

        $rowsOut[] = [
            'service_id' => (int)$r->service_id,
            'user_id' => (int)$r->user_id,
            'username' => (string)($r->username ?? ''),
            'billingcycle' => (string)($r->billingcycle ?? ''),
            'amount' => round($amount, 2),
            'tax_amount' => $taxAmount,
            'nextduedate' => (string)($r->nextduedate ?? ''),
            'renewal_date' => (string)($r->renewal_date ?? ''),
        ];
    }

    $buildUrl = function (array $overrides = []) use ($year, $month, $perPage, $page, $sort, $dir) {
        $qs = array_merge([
            'action' => 'powerpanel',
            'view' => 'income-forecast',
            'year' => $year,
            'month' => $month,
            'perPage' => $perPage,
            'page' => $page,
            'sort' => $sort,
            'dir' => $dir,
        ], $overrides);
        return 'addonmodules.php?module=eazybackup&' . http_build_query($qs);
    };

    $toggleDir = $dir === 'asc' ? 'desc' : 'asc';
    $sortLinks = [
        'username' => $buildUrl(['sort' => 'username', 'dir' => ($sort === 'username' ? $toggleDir : 'asc'), 'page' => 1]),
        'billingcycle' => $buildUrl(['sort' => 'billingcycle', 'dir' => ($sort === 'billingcycle' ? $toggleDir : 'asc'), 'page' => 1]),
        'amount' => $buildUrl(['sort' => 'amount', 'dir' => ($sort === 'amount' ? $toggleDir : 'asc'), 'page' => 1]),
        'renewal_date' => $buildUrl(['sort' => 'renewal_date', 'dir' => ($sort === 'renewal_date' ? $toggleDir : 'asc'), 'page' => 1]),
    ];

    $paginationHtml = '';
    if ($totalPages > 1) {
        $paginationHtml .= '<nav aria-label="Income forecast pagination"><ul class="pagination">';
        $prevUrl = $buildUrl(['page' => max(1, $page - 1)]);
        $nextUrl = $buildUrl(['page' => min($totalPages, $page + 1)]);
        $paginationHtml .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8') . '">Previous</a></li>';
        $startPage = max(1, $page - 3);
        $endPage = min($totalPages, $page + 3);
        for ($i = $startPage; $i <= $endPage; $i++) {
            $url = $buildUrl(['page' => $i]);
            $paginationHtml .= '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $i . '</a></li>';
        }
        $paginationHtml .= '<li class="page-item' . ($page >= $totalPages ? ' disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') . '">Next</a></li>';
        $paginationHtml .= '</ul></nav>';
    }

    return [
        'year' => $year,
        'month' => $month,
        'page' => $page,
        'perPage' => $perPage,
        'totalRows' => $totalRows,
        'totalPages' => $totalPages,
        'pagination' => $paginationHtml,
        'sort' => $sort,
        'dir' => $dir,
        'sortLinks' => $sortLinks,
        'rows' => $rowsOut,
        'totals' => [
            'renewal_amount' => round($totalAmount, 2),
            'tax_amount' => round($totalTax, 2),
            'grand_total' => round($totalAmount + $totalTax, 2),
        ],
    ];
})();
