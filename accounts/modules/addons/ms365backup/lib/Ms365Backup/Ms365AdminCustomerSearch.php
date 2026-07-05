<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Admin customer typeahead for MS365 provision tooling.
 */
final class Ms365AdminCustomerSearch
{
    /**
     * @return list<int>
     */
    public static function relevantProductIds(): array
    {
        $pids = Ms365BillingConfig::getBillablePids();
        try {
            $storagePid = (int) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'pid_cloud_storage')
                ->value('value');
            if ($storagePid > 0 && !in_array($storagePid, $pids, true)) {
                $pids[] = $storagePid;
            }
        } catch (\Throwable $_) {
        }

        return array_values(array_unique(array_filter(array_map('intval', $pids))));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '' || strlen($query) < 2) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $numericId = ctype_digit($query) ? (int) $query : null;

        try {
            $base = Capsule::table('tblclients')
                ->select('id', 'firstname', 'lastname', 'companyname', 'email', 'status', 'datecreated')
                ->orderBy('id', 'desc')
                ->limit($limit);

            $base->where(function ($q) use ($like, $numericId) {
                $q->where('email', 'like', $like)
                    ->orWhere('firstname', 'like', $like)
                    ->orWhere('lastname', 'like', $like)
                    ->orWhere('companyname', 'like', $like)
                    ->orWhereRaw('CONCAT(firstname, " ", lastname) LIKE ?', [$like]);
                if ($numericId !== null) {
                    $q->orWhere('id', '=', $numericId);
                }
            });

            $clients = $base->get();
        } catch (\Throwable $_) {
            return [];
        }

        if ($clients->isEmpty()) {
            return [];
        }

        $clientIds = $clients->pluck('id')->all();
        $pids = self::relevantProductIds();

        $productNames = [];
        if ($pids !== []) {
            try {
                $rows = Capsule::table('tblproducts')->whereIn('id', $pids)->select('id', 'name')->get();
                foreach ($rows as $r) {
                    $productNames[(int) $r->id] = (string) $r->name;
                }
            } catch (\Throwable $_) {
            }
        }

        $servicesByClient = [];
        try {
            $svcQuery = Capsule::table('tblhosting')
                ->whereIn('userid', $clientIds)
                ->select('id', 'userid', 'username', 'packageid', 'domainstatus', 'regdate');
            if ($pids !== []) {
                $svcQuery->whereIn('packageid', $pids);
            }
            $services = $svcQuery->orderBy('id', 'desc')->get();
            foreach ($services as $svc) {
                $cid = (int) $svc->userid;
                if (!isset($servicesByClient[$cid])) {
                    $servicesByClient[$cid] = [];
                }
                $servicesByClient[$cid][] = [
                    'id' => (int) $svc->id,
                    'username' => (string) ($svc->username ?? ''),
                    'packageid' => (int) $svc->packageid,
                    'product' => $productNames[(int) $svc->packageid] ?? ('Product #' . (int) $svc->packageid),
                    'domainstatus' => (string) ($svc->domainstatus ?? ''),
                    'regdate' => (string) ($svc->regdate ?? ''),
                ];
            }
        } catch (\Throwable $_) {
        }

        $results = [];
        foreach ($clients as $c) {
            $full = trim(((string) ($c->firstname ?? '')) . ' ' . ((string) ($c->lastname ?? '')));
            $results[] = [
                'id' => (int) $c->id,
                'name' => $full !== '' ? $full : null,
                'companyname' => (string) ($c->companyname ?? ''),
                'email' => (string) ($c->email ?? ''),
                'status' => (string) ($c->status ?? ''),
                'datecreated' => (string) ($c->datecreated ?? ''),
                'services' => $servicesByClient[(int) $c->id] ?? [],
            ];
        }

        return $results;
    }
}
