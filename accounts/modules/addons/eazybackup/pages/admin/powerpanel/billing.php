<?php

use WHMCS\Database\Capsule as DB;

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

/**
 * Controller: Admin Power Panel → Billing (Storage, Devices, Protected Items)
 *
 * Accepts optional query params:
 * - username: contains filter (case-sensitive)
 * - product: exact match on product id (int)
 * - sort: product|username|bytes|units_storage|devices|units_devices|hv|hv_units|di|di_units|m365|m365_units|vmw|vmw_units
 * - dir: asc|desc
 * - page: integer >= 1
 * - perPage: integer (default 25)
 */
return (function () {
	$req = $_GET + [];

	$filterUsername = isset($req['username']) ? trim((string)$req['username']) : '';
	$filterProduct  = isset($req['product']) ? (int)$req['product'] : 0;
	$startParam     = isset($req['start']) ? (string)$req['start'] : '';
	$endParam       = isset($req['end'])   ? (string)$req['end']   : '';
	$sort           = isset($req['sort']) ? (string)$req['sort'] : 'username';
	$dir            = strtolower((string)($req['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
	$page           = max(1, (int)($req['page'] ?? 1));
	$perPage        = max(1, min(2000, (int)($req['perPage'] ?? 25)));

	$sortMap = [
		'product'        => 'p.name',
		'username'       => 'u.username',
		'bytes'          => 'v.total_bytes',
		'units_storage'  => 'b.storage_units',
		'devices'        => 'd.device_count',
		'units_devices'  => 'b.device_units',
		'hv'             => 'hv.hv_count',
		'hv_units'       => 'b.hv_units',
		'di'             => 'i.di_count',
		'di_units'       => 'b.di_units',
		'm365'           => 'i.m365_count',
		'm365_units'     => 'b.m365_units',
		'vmw'            => 'vmw.vmw_count',
		'vmw_units'      => 'b.vmw_units',
		'pmx'            => 'pmx.pmx_count',
		'pmx_units'      => 'b.pmx_units',
	];
	$orderByExpr = $sortMap[$sort] ?? $sortMap['username'];

	// Billing window (defaults: current calendar month)
	$now = time();
	$firstOfMonth = strtotime(date('Y-m-01 00:00:00'));
	$firstOfNext  = strtotime(date('Y-m-01 00:00:00', strtotime('+1 month', $firstOfMonth)));
	$startTs = ctype_digit($startParam) ? (int)$startParam : ($startParam !== '' ? (int)strtotime($startParam) : $firstOfMonth);
	$endTs   = ctype_digit($endParam)   ? (int)$endParam   : ($endParam   !== '' ? (int)strtotime($endParam)   : $firstOfNext);
	$startSql = date('Y-m-d H:i:s', $startTs);
	$endSql   = date('Y-m-d H:i:s', $endTs);

	// UNION usernames that have any data in storage/devices/items; then join to hosting/product
	$sqlBase = "
		FROM (
			SELECT username FROM (
				SELECT BINARY username AS username FROM comet_vaults WHERE type IN (1000,1003,1005,1007,1008) AND is_active = 1 AND username IS NOT NULL AND username<>'' GROUP BY BINARY username
				UNION
				SELECT BINARY username AS username FROM comet_devices WHERE revoked_at IS NULL AND username IS NOT NULL AND username<>'' GROUP BY BINARY username
				UNION
				SELECT BINARY username AS username FROM comet_items   WHERE username IS NOT NULL AND username<>'' GROUP BY BINARY username
			) uu
		) u
		JOIN tblhosting h ON BINARY h.username = u.username AND h.domainstatus = 'Active'
		JOIN tblproducts p ON p.id = h.packageid
		LEFT JOIN (
			SELECT username, SUM(total_bytes) AS total_bytes
			FROM comet_vaults
			WHERE type IN (1000,1003,1005,1007,1008) AND is_active = 1 AND username IS NOT NULL AND username<>''
			GROUP BY BINARY username
		) v ON BINARY v.username = u.username
		LEFT JOIN (
			SELECT username, COUNT(*) AS device_count
			FROM comet_devices
			WHERE revoked_at IS NULL AND username IS NOT NULL AND username<>''
			GROUP BY BINARY username
		) d ON BINARY d.username = u.username
		LEFT JOIN (
			SELECT username,
			       -- Disk Image is billed per device (not per protected item). Count distinct devices that have >=1 windisk item.
			       COUNT(DISTINCT CASE
			           WHEN type='engine1/windisk'
			             THEN COALESCE(NULLIF(comet_device_id,''), NULLIF(owner_device,''))
			           ELSE NULL
			       END) AS di_count,
			       SUM(CASE WHEN type='engine1/winmsofficemail' 
			                THEN COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(content,'$.Statistics.LastBackupJob.TotalAccountsCount')) AS UNSIGNED),0)
			                ELSE 0 END) AS m365_count
			FROM comet_items
			WHERE username IS NOT NULL AND username<>''
			GROUP BY BINARY username
		) i ON BINARY i.username = u.username
		LEFT JOIN (
			-- Per protected item (Hyper-V): prefer in-window counts; if none in window, fall back to latest known item counts
			SELECT i.username,
			       SUM(GREATEST(COALESCE(j.jobs_vmcount,0), COALESCE(i.items_vmcount,0))) AS hv_count
			FROM (
				SELECT ci.username, ci.id AS comet_item_id,
				       COALESCE(
				         GREATEST(
				           CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastSuccessfulBackupJob.EndTime')) AS UNSIGNED)
				                      BETWEEN UNIX_TIMESTAMP(:hvStartItems1) AND UNIX_TIMESTAMP(:hvEndItems1)-1
				                 THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastSuccessfulBackupJob.TotalVmCount')) AS UNSIGNED)
				                 ELSE NULL END,
				           CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastBackupJob.EndTime')) AS UNSIGNED)
				                      BETWEEN UNIX_TIMESTAMP(:hvStartItems2) AND UNIX_TIMESTAMP(:hvEndItems2)-1
				                 THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastBackupJob.TotalVmCount')) AS UNSIGNED)
				                 ELSE NULL END
				         ),
				         GREATEST(
				           COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastSuccessfulBackupJob.TotalVmCount')) AS UNSIGNED),0),
				           COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastBackupJob.TotalVmCount')) AS UNSIGNED),0)
				         ),
				         0
				       ) AS items_vmcount
				FROM comet_items ci
				WHERE ci.type='engine1/hyperv' AND ci.username IS NOT NULL AND ci.username<>''
			) i
			LEFT JOIN (
				SELECT j2.username, j2.comet_item_id,
				       COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(j2.content,'$.TotalVmCount')) AS UNSIGNED),0) AS jobs_vmcount
				FROM (
					SELECT username, comet_item_id, MAX(ended_at) AS ended_at
					FROM comet_jobs
					WHERE status = 5000 AND ended_at >= :hvStartJobs AND ended_at < :hvEndJobs
					GROUP BY username, comet_item_id
				) latest
				JOIN comet_jobs j2
				  ON j2.username = latest.username AND j2.comet_item_id = latest.comet_item_id AND j2.ended_at = latest.ended_at
				JOIN comet_items ci ON ci.id = j2.comet_item_id AND ci.type='engine1/hyperv'
			) j ON BINARY j.username = i.username AND j.comet_item_id = i.comet_item_id
			GROUP BY BINARY i.username
		) hv ON BINARY hv.username = u.username
		LEFT JOIN (
			SELECT i.username,
			       SUM(GREATEST(COALESCE(j.jobs_vmcount,0), COALESCE(i.items_vmcount,0))) AS vmw_count
			FROM (
				SELECT ci.username, ci.id AS comet_item_id,
				       COALESCE(
				         GREATEST(
				           CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastSuccessfulBackupJob.EndTime')) AS UNSIGNED)
								      BETWEEN UNIX_TIMESTAMP(:vmwStartItems1) AND UNIX_TIMESTAMP(:vmwEndItems1)-1
						        THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastSuccessfulBackupJob.TotalVmCount')) AS UNSIGNED)
						        ELSE NULL END,
				           CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastBackupJob.EndTime')) AS UNSIGNED)
								      BETWEEN UNIX_TIMESTAMP(:vmwStartItems2) AND UNIX_TIMESTAMP(:vmwEndItems2)-1
						        THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastBackupJob.TotalVmCount')) AS UNSIGNED)
						        ELSE NULL END
				         ),
				         GREATEST(
				           COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastSuccessfulBackupJob.TotalVmCount')) AS UNSIGNED),0),
				           COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(ci.content,'$.Statistics.LastBackupJob.TotalVmCount')) AS UNSIGNED),0)
				         ),
				         0
				       ) AS items_vmcount
				FROM comet_items ci
				WHERE ci.type='engine1/vmware' AND ci.username IS NOT NULL AND ci.username<>''
			) i
			LEFT JOIN (
				SELECT j2.username, j2.comet_item_id,
				       COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(j2.content,'$.TotalVmCount')) AS UNSIGNED),0) AS jobs_vmcount
				FROM (
					SELECT username, comet_item_id, MAX(ended_at) AS ended_at
					FROM comet_jobs
					WHERE status = 5000 AND ended_at >= :vmwStartJobs AND ended_at < :vmwEndJobs
					GROUP BY username, comet_item_id
				) latest
				JOIN comet_jobs j2
				  ON j2.username = latest.username AND j2.comet_item_id = latest.comet_item_id AND j2.ended_at = latest.ended_at
				JOIN comet_items ci ON ci.id = j2.comet_item_id AND ci.type='engine1/vmware'
			) j ON BINARY j.username = i.username AND j.comet_item_id = i.comet_item_id
			GROUP BY BINARY i.username
		) vmw ON BINARY vmw.username = u.username
		LEFT JOIN (
			SELECT username, COUNT(*) AS pmx_count
			FROM comet_items
			WHERE type='engine1/proxmox' AND username IS NOT NULL AND username<>''
			GROUP BY BINARY username
		) pmx ON BINARY pmx.username = u.username
		LEFT JOIN (
			SELECT username,
			       SUM(CASE WHEN category='device' AND grace_expires_at>=NOW() THEN 1 ELSE 0 END) AS device_grace_active,
			       MIN(CASE WHEN category='device' AND grace_expires_at>=NOW() THEN grace_expires_at END) AS device_grace_earliest,
			       SUM(CASE WHEN category='addon' AND entity_key LIKE 'disk_image%'  AND grace_expires_at>=NOW() THEN 1 ELSE 0 END) AS di_grace_active,
			       MIN(CASE WHEN category='addon' AND entity_key LIKE 'disk_image%'  AND grace_expires_at>=NOW() THEN grace_expires_at END) AS di_grace_earliest,
			       SUM(CASE WHEN category='addon' AND entity_key LIKE 'hyperv_vm%'   AND grace_expires_at>=NOW() THEN 1 ELSE 0 END) AS hv_grace_active,
			       MIN(CASE WHEN category='addon' AND entity_key LIKE 'hyperv_vm%'   AND grace_expires_at>=NOW() THEN grace_expires_at END) AS hv_grace_earliest,
			       SUM(CASE WHEN category='addon' AND entity_key LIKE 'vmware_vm%'   AND grace_expires_at>=NOW() THEN 1 ELSE 0 END) AS vmw_grace_active,
			       MIN(CASE WHEN category='addon' AND entity_key LIKE 'vmware_vm%'   AND grace_expires_at>=NOW() THEN grace_expires_at END) AS vmw_grace_earliest,
			       SUM(CASE WHEN category='addon' AND entity_key LIKE 'proxmox_vm%'  AND grace_expires_at>=NOW() THEN 1 ELSE 0 END) AS pmx_grace_active,
			       MIN(CASE WHEN category='addon' AND entity_key LIKE 'proxmox_vm%'  AND grace_expires_at>=NOW() THEN grace_expires_at END) AS pmx_grace_earliest,
			       SUM(CASE WHEN category='addon' AND entity_key LIKE 'm365_accounts%' AND grace_expires_at>=NOW() THEN 1 ELSE 0 END) AS m365_grace_active,
			       MIN(CASE WHEN category='addon' AND entity_key LIKE 'm365_accounts%' AND grace_expires_at>=NOW() THEN grace_expires_at END) AS m365_grace_earliest
			FROM eb_billing_grace
			GROUP BY BINARY username
		) gr ON BINARY gr.username = u.username
		LEFT JOIN (
			SELECT hco.relid AS service_id,
			       SUM(CASE WHEN hco.configid IN (61,67)  THEN hco.qty ELSE 0 END) AS storage_units,
                           SUM(CASE WHEN hco.configid IN (88,89,93)  THEN hco.qty ELSE 0 END) AS device_units,
		       SUM(CASE WHEN hco.configid IN (97,82)  THEN hco.qty ELSE 0 END) AS hv_units,
		       SUM(CASE WHEN hco.configid IN (91,94)  THEN hco.qty ELSE 0 END) AS di_units,
		       SUM(CASE WHEN hco.configid IN (60,59)  THEN hco.qty ELSE 0 END) AS m365_units,
		       SUM(CASE WHEN hco.configid IN (99,101) THEN hco.qty ELSE 0 END) AS vmw_units,
		       SUM(CASE WHEN hco.configid IN (102)    THEN hco.qty ELSE 0 END) AS pmx_units
			FROM tblhostingconfigoptions hco
			GROUP BY hco.relid
		) b ON b.service_id = h.id
	";

$where = [];
$params = [
    // split params for items JSON paths and job subqueries to avoid duplicate named placeholders collision in drivers
    'hvStartItems1' => $startSql,
    'hvEndItems1' => $endSql,
    'hvStartItems2' => $startSql,
    'hvEndItems2' => $endSql,
    'hvStartJobs' => $startSql,
    'hvEndJobs' => $endSql,
    'vmwStartItems1' => $startSql,
    'vmwEndItems1' => $endSql,
    'vmwStartItems2' => $startSql,
    'vmwEndItems2' => $endSql,
    'vmwStartJobs' => $startSql,
    'vmwEndJobs' => $endSql,
];
// Base params used by queries that only reference $sqlBase (and not $whereSql)
$baseParams = [
    'hvStartItems1' => $startSql,
    'hvEndItems1' => $endSql,
    'hvStartItems2' => $startSql,
    'hvEndItems2' => $endSql,
    'hvStartJobs' => $startSql,
    'hvEndJobs' => $endSql,
    'vmwStartItems1' => $startSql,
    'vmwEndItems1' => $endSql,
    'vmwStartItems2' => $startSql,
    'vmwEndItems2' => $endSql,
    'vmwStartJobs' => $startSql,
    'vmwEndJobs' => $endSql,
];
// Only active services
$where[] = "h.domainstatus = 'Active'";
// Exclude specific product permanently (pid=48)
$where[] = 'p.id <> 48';
	if ($filterUsername !== '') {
		$where[] = 'BINARY u.username LIKE :usernameLike';
		$params['usernameLike'] = '%' . $filterUsername . '%';
	}
	if ($filterProduct > 0) {
		$where[] = 'p.id = :productExact';
		$params['productExact'] = $filterProduct;
	}
	$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

	// Count per service for pagination
	$countSql = 'SELECT COUNT(*) AS c FROM (SELECT h.id ' . $sqlBase . ' ' . $whereSql . ' GROUP BY h.id) t';
	$totalRows = (int) (DB::selectOne($countSql, $params)->c ?? 0);

	$offset = ($page - 1) * $perPage;

	$selectSql = 'SELECT '
		. 'p.id AS product_id, p.name AS product_name, u.username, '
		. 'COALESCE(v.total_bytes,0) AS total_bytes, '
		. 'COALESCE(d.device_count,0) AS device_count, '
		. 'COALESCE(hv.hv_count,0) AS hv_count, COALESCE(i.di_count,0) AS di_count, COALESCE(i.m365_count,0) AS m365_count, COALESCE(vmw.vmw_count,0) AS vmw_count, COALESCE(pmx.pmx_count,0) AS pmx_count, '
		. 'COALESCE(b.storage_units,0) AS storage_units, COALESCE(b.device_units,0) AS device_units, '
		. 'COALESCE(b.hv_units,0) AS hv_units, COALESCE(b.di_units,0) AS di_units, COALESCE(b.m365_units,0) AS m365_units, COALESCE(b.vmw_units,0) AS vmw_units, COALESCE(b.pmx_units,0) AS pmx_units, '
		. 'COALESCE(gr.device_grace_active,0) AS device_grace_active, gr.device_grace_earliest, '
		. 'COALESCE(gr.di_grace_active,0) AS di_grace_active, gr.di_grace_earliest, '
		. 'COALESCE(gr.hv_grace_active,0) AS hv_grace_active, gr.hv_grace_earliest, '
		. 'COALESCE(gr.vmw_grace_active,0) AS vmw_grace_active, gr.vmw_grace_earliest, '
		. 'COALESCE(gr.pmx_grace_active,0) AS pmx_grace_active, gr.pmx_grace_earliest, '
		. 'COALESCE(gr.m365_grace_active,0) AS m365_grace_active, gr.m365_grace_earliest, '
		. 'h.id AS service_id, h.userid AS user_id '
		. $sqlBase . ' '
		. $whereSql . ' '
		. 'ORDER BY ' . $orderByExpr . ' ' . $dir . ' '
		. 'LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
	$rows = DB::select($selectSql, $params);

// Distinct product list (uses only $sqlBase placeholders)
$productsSql = 'SELECT DISTINCT p.id AS product_id, p.name AS product_name ' . $sqlBase . ' ORDER BY p.name ASC';
$productRows = DB::select($productsSql, $baseParams);
	$products = array_map(function ($r) { return ['id' => (int)$r->product_id, 'name' => (string)$r->product_name]; }, $productRows);

	$humanBytes = function ($bytes) {
		$units = ['bytes','KB','MB','GB','TB','PB'];
		$i = 0;
		$bytes = (float)$bytes;
		while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
		return sprintf('%0.2f %s', $bytes, $units[$i]);
	};

	$rowsOut = [];
	foreach ($rows as $r) {
		$nowTs = time();
		$daysLeft = function ($earliest) use ($nowTs) {
			if (!$earliest) return 0;
			$ts = strtotime((string)$earliest);
			if ($ts === false) return 0;
			$delta = $ts - $nowTs;
			return $delta > 0 ? (int)ceil($delta / 86400) : 0;
		};

		$devUsed = (int)$r->device_count;
		$devBilled = (int)$r->device_units;
		$productId = (int)$r->product_id;
		// Product-specific device rules:
		// - M365 products (52,57): billed devices are always 0 and correct; never flag upgrades for device count
		// - Virtual Server products (53,54): unlimited devices; treat billed as 0 and do not flag
		if ($productId === 52 || $productId === 57 || $productId === 53 || $productId === 54) {
			$devBilled = 0; // considered correct for these products
		}
		$devDelta = max(0, $devUsed - $devBilled);
		$devGraceActive = (int)($r->device_grace_active ?? 0);
		$devGraceCovered = min($devDelta, $devGraceActive);
		$devDueNow = max(0, $devDelta - $devGraceCovered);
		$devGraceDaysLeft = $devGraceCovered > 0 ? $daysLeft($r->device_grace_earliest ?? null) : 0;
		// Never flag device upgrades for these product IDs
		if ($productId === 52 || $productId === 57 || $productId === 53 || $productId === 54) {
			$devDelta = 0;
			$devGraceCovered = 0;
			$devDueNow = 0;
			$devGraceDaysLeft = 0;
		}

		// Disk Image add-on is billed per device, and should never exceed the number of active devices.
		// (Some accounts can have multiple windisk protected items on a single device.)
		$diUsed = min((int)$r->di_count, $devUsed);
		$diBilled = (int)$r->di_units;
		$diDelta = max(0, $diUsed - $diBilled);
		$diGraceActive = (int)($r->di_grace_active ?? 0);
		$diGraceCovered = min($diDelta, $diGraceActive);
		$diDueNow = max(0, $diDelta - $diGraceCovered);
		$diGraceDaysLeft = $diGraceCovered > 0 ? $daysLeft($r->di_grace_earliest ?? null) : 0;

		$hvUsed = (int)$r->hv_count;
		$hvBilled = (int)$r->hv_units;
		$hvDelta = max(0, $hvUsed - $hvBilled);
		$hvGraceActive = (int)($r->hv_grace_active ?? 0);
		$hvGraceCovered = min($hvDelta, $hvGraceActive);
		$hvDueNow = max(0, $hvDelta - $hvGraceCovered);
		$hvGraceDaysLeft = $hvGraceCovered > 0 ? $daysLeft($r->hv_grace_earliest ?? null) : 0;

		$vmwUsed = (int)$r->vmw_count;
		$vmwBilled = (int)$r->vmw_units;
		$vmwDelta = max(0, $vmwUsed - $vmwBilled);
		$vmwGraceActive = (int)($r->vmw_grace_active ?? 0);
		$vmwGraceCovered = min($vmwDelta, $vmwGraceActive);
		$vmwDueNow = max(0, $vmwDelta - $vmwGraceCovered);
		$vmwGraceDaysLeft = $vmwGraceCovered > 0 ? $daysLeft($r->vmw_grace_earliest ?? null) : 0;

		$pmxUsed = (int)($r->pmx_count ?? 0);
		$pmxBilled = (int)($r->pmx_units ?? 0);
		$pmxDelta = max(0, $pmxUsed - $pmxBilled);
		$pmxGraceActive = (int)($r->pmx_grace_active ?? 0);
		$pmxGraceCovered = min($pmxDelta, $pmxGraceActive);
		$pmxDueNow = max(0, $pmxDelta - $pmxGraceCovered);
		$pmxGraceDaysLeft = $pmxGraceCovered > 0 ? $daysLeft($r->pmx_grace_earliest ?? null) : 0;

		$m365Used = (int)$r->m365_count;
		$m365Billed = (int)$r->m365_units;
		$m365Delta = max(0, $m365Used - $m365Billed);
		$m365GraceActive = (int)($r->m365_grace_active ?? 0);
		$m365GraceCovered = min($m365Delta, $m365GraceActive);
		$m365DueNow = max(0, $m365Delta - $m365GraceCovered);
		$m365GraceDaysLeft = $m365GraceCovered > 0 ? $daysLeft($r->m365_grace_earliest ?? null) : 0;
		$rowsOut[] = [
			'product_id'     => (int)$r->product_id,
			'product_name'   => (string)$r->product_name,
			'username'       => (string)$r->username,
			'total_bytes'    => (int)$r->total_bytes,
			'total_bytes_hr' => $humanBytes($r->total_bytes),
			'device_count'   => (int)$r->device_count,
			'hv_count'       => (int)$r->hv_count,
			// Expose the billing-relevant Disk Image usage (capped by device count) so UI badges don't mislead.
			'di_count'       => (int)$diUsed,
			'm365_count'     => (int)$r->m365_count,
			'vmw_count'      => (int)$r->vmw_count,
			'pmx_count'      => (int)($r->pmx_count ?? 0),
			'storage_units'  => (int)$r->storage_units,
			'device_units'   => (int)$r->device_units,
			'hv_units'       => (int)$r->hv_units,
			'di_units'       => (int)$r->di_units,
			'm365_units'     => (int)$r->m365_units,
			'vmw_units'      => (int)$r->vmw_units,
			'pmx_units'      => (int)($r->pmx_units ?? 0),
			'service_id'     => (int)$r->service_id,
			'user_id'        => (int)$r->user_id,
			'devices_delta'        => $devDelta,
			'devices_grace'        => $devGraceCovered,
			'devices_due_now'      => $devDueNow,
			'devices_grace_days'   => $devGraceDaysLeft,
			'di_delta'             => $diDelta,
			'di_grace'             => $diGraceCovered,
			'di_due_now'           => $diDueNow,
			'di_grace_days'        => $diGraceDaysLeft,
			'hv_delta'             => $hvDelta,
			'hv_grace'             => $hvGraceCovered,
			'hv_due_now'           => $hvDueNow,
			'hv_grace_days'        => $hvGraceDaysLeft,
			'vmw_delta'            => $vmwDelta,
			'vmw_grace'            => $vmwGraceCovered,
			'vmw_due_now'          => $vmwDueNow,
			'vmw_grace_days'       => $vmwGraceDaysLeft,
			'pmx_delta'            => $pmxDelta,
			'pmx_grace'            => $pmxGraceCovered,
			'pmx_due_now'          => $pmxDueNow,
			'pmx_grace_days'       => $pmxGraceDaysLeft,
			'm365_delta'           => $m365Delta,
			'm365_grace'           => $m365GraceCovered,
			'm365_due_now'         => $m365DueNow,
			'm365_grace_days'      => $m365GraceDaysLeft,
		];
	}

	$buildUrl = function (array $overrides = []) use ($req) {
		$qs = array_merge([
			'action'  => 'powerpanel',
			'view'    => 'billing',
			'username'=> $req['username'] ?? '',
			'product' => $req['product'] ?? '',
			'page'    => $req['page'] ?? 1,
			'perPage' => $req['perPage'] ?? 25,
			'sort'    => $req['sort'] ?? 'username',
			'dir'     => $req['dir'] ?? 'asc',
		], $overrides);
		return 'addonmodules.php?module=eazybackup&' . http_build_query($qs);
	};

	$currentDir = $dir === 'DESC' ? 'desc' : 'asc';
	$toggleDir  = $currentDir === 'asc' ? 'desc' : 'asc';
	$sortLinks = [
		'product'       => $buildUrl(['sort' => 'product',       'dir' => ($sort === 'product'       ? $toggleDir : 'asc')]),
		'username'      => $buildUrl(['sort' => 'username',      'dir' => ($sort === 'username'      ? $toggleDir : 'asc')]),
		'bytes'         => $buildUrl(['sort' => 'bytes',         'dir' => ($sort === 'bytes'         ? $toggleDir : 'asc')]),
		'units_storage' => $buildUrl(['sort' => 'units_storage', 'dir' => ($sort === 'units_storage' ? $toggleDir : 'asc')]),
		'devices'       => $buildUrl(['sort' => 'devices',       'dir' => ($sort === 'devices'       ? $toggleDir : 'asc')]),
		'units_devices' => $buildUrl(['sort' => 'units_devices', 'dir' => ($sort === 'units_devices' ? $toggleDir : 'asc')]),
		'hv'            => $buildUrl(['sort' => 'hv',            'dir' => ($sort === 'hv'            ? $toggleDir : 'asc')]),
		'hv_units'      => $buildUrl(['sort' => 'hv_units',      'dir' => ($sort === 'hv_units'      ? $toggleDir : 'asc')]),
		'di'            => $buildUrl(['sort' => 'di',            'dir' => ($sort === 'di'            ? $toggleDir : 'asc')]),
		'di_units'      => $buildUrl(['sort' => 'di_units',      'dir' => ($sort === 'di_units'      ? $toggleDir : 'asc')]),
		'm365'          => $buildUrl(['sort' => 'm365',          'dir' => ($sort === 'm365'          ? $toggleDir : 'asc')]),
		'm365_units'    => $buildUrl(['sort' => 'm365_units',    'dir' => ($sort === 'm365_units'    ? $toggleDir : 'asc')]),
		'vmw'           => $buildUrl(['sort' => 'vmw',           'dir' => ($sort === 'vmw'           ? $toggleDir : 'asc')]),
		'vmw_units'     => $buildUrl(['sort' => 'vmw_units',     'dir' => ($sort === 'vmw_units'     ? $toggleDir : 'asc')]),
		'pmx'           => $buildUrl(['sort' => 'pmx',           'dir' => ($sort === 'pmx'           ? $toggleDir : 'asc')]),
		'pmx_units'     => $buildUrl(['sort' => 'pmx_units',     'dir' => ($sort === 'pmx_units'     ? $toggleDir : 'asc')]),
	];

	$totalPages = max(1, (int)ceil($totalRows / $perPage));
	$paginationHtml = '';
	if ($totalPages > 1) {
		$paginationHtml .= '<nav aria-label="Page navigation"><ul class="pagination">';
		$prevUrl = $buildUrl(['page' => max(1, $page - 1)]);
		$nextUrl = $buildUrl(['page' => min($totalPages, $page + 1)]);
		$paginationHtml .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars($prevUrl) . '">Previous</a></li>';
		$start = max(1, $page - 3);
		$end   = min($totalPages, $page + 3);
		for ($i = $start; $i <= $end; $i++) {
			$url = $buildUrl(['page' => $i]);
			$paginationHtml .= '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . htmlspecialchars($url) . '">' . $i . '</a></li>';
		}
		$paginationHtml .= '<li class="page-item' . ($page >= $totalPages ? ' disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars($nextUrl) . '">Next</a></li>';
		$paginationHtml .= '</ul></nav>';
	}

	return [
		'rows'        => $rowsOut,
		'products'    => $products,
		'filters'     => [
			'username' => $filterUsername,
			'product'  => $filterProduct,
		],
		'sort'        => $sort,
		'dir'         => $currentDir,
		'page'        => $page,
		'perPage'     => $perPage,
		'totalRows'   => $totalRows,
		'totalPages'  => $totalPages,
		'sortLinks'   => $sortLinks,
		'pagination'  => $paginationHtml,
	];
})();


