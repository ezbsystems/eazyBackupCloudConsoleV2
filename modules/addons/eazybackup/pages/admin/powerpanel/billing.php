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
	$perPage        = max(1, min(250, (int)($req['perPage'] ?? 25)));

	$sortMap = [
		'product'        => 'p.name',
		'username'       => 'u.username',
		'bytes'          => 'v.total_bytes',
		'units_storage'  => 'b.storage_units',
		'devices'        => 'd.device_count',
		'units_devices'  => 'b.device_units',
		'hv'             => 'i.hv_count',
		'hv_units'       => 'b.hv_units',
		'di'             => 'i.di_count',
		'di_units'       => 'b.di_units',
		'm365'           => 'i.m365_count',
		'm365_units'     => 'b.m365_units',
		'vmw'            => 'i.vmw_count',
		'vmw_units'      => 'b.vmw_units',
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
				SELECT username FROM comet_vaults WHERE type IN (1000,1003,1005,1007,1008) AND username IS NOT NULL AND username<>'' GROUP BY username
				UNION
				SELECT username FROM comet_devices WHERE revoked_at IS NULL AND username IS NOT NULL AND username<>'' GROUP BY username
				UNION
				SELECT username FROM comet_items   WHERE username IS NOT NULL AND username<>'' GROUP BY username
			) uu
		) u
		JOIN tblhosting h ON BINARY h.username = u.username AND h.domainstatus = 'Active'
		JOIN tblproducts p ON p.id = h.packageid
		LEFT JOIN (
			SELECT username, SUM(total_bytes) AS total_bytes
			FROM comet_vaults
			WHERE type IN (1000,1003,1005,1007,1008) AND username IS NOT NULL AND username<>''
			GROUP BY username
		) v ON v.username = u.username
		LEFT JOIN (
			SELECT username, COUNT(*) AS device_count
			FROM comet_devices
			WHERE revoked_at IS NULL AND username IS NOT NULL AND username<>''
			GROUP BY username
		) d ON d.username = u.username
		LEFT JOIN (
			SELECT username,
			       SUM(CASE WHEN type='engine1/windisk'         THEN 1 ELSE 0 END) AS di_count,
			       SUM(CASE WHEN type='engine1/winmsofficemail' THEN 1 ELSE 0 END) AS m365_count
			FROM comet_items
			WHERE username IS NOT NULL AND username<>''
			GROUP BY username
		) i ON i.username = u.username
		LEFT JOIN (
			SELECT j2.username,
			       SUM(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(j2.content,'$.TotalVmCount')) AS UNSIGNED),0)) AS hv_count
			FROM (
				SELECT username, comet_item_id, MAX(ended_at) AS ended_at
				FROM comet_jobs
				WHERE status = 5000 AND ended_at >= :hvStart AND ended_at < :hvEnd
				GROUP BY username, comet_item_id
			) latest
			JOIN comet_jobs j2
			  ON j2.username = latest.username AND j2.comet_item_id = latest.comet_item_id AND j2.ended_at = latest.ended_at
			JOIN comet_items ci ON ci.id = j2.comet_item_id AND ci.type='engine1/hyperv'
			GROUP BY j2.username
		) hv ON hv.username = u.username
		LEFT JOIN (
			SELECT j2.username,
			       SUM(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(j2.content,'$.TotalVmCount')) AS UNSIGNED),0)) AS vmw_count
			FROM (
				SELECT username, comet_item_id, MAX(ended_at) AS ended_at
				FROM comet_jobs
				WHERE status = 5000 AND ended_at >= :vmwStart AND ended_at < :vmwEnd
				GROUP BY username, comet_item_id
			) latest
			JOIN comet_jobs j2
			  ON j2.username = latest.username AND j2.comet_item_id = latest.comet_item_id AND j2.ended_at = latest.ended_at
			JOIN comet_items ci ON ci.id = j2.comet_item_id AND ci.type='engine1/vmware'
			GROUP BY j2.username
		) vmw ON vmw.username = u.username
		LEFT JOIN (
			SELECT hco.relid AS service_id,
			       SUM(CASE WHEN hco.configid IN (61,67)  THEN hco.qty ELSE 0 END) AS storage_units,
                           SUM(CASE WHEN hco.configid IN (88,89,93)  THEN hco.qty ELSE 0 END) AS device_units,
			       SUM(CASE WHEN hco.configid IN (97,82)  THEN hco.qty ELSE 0 END) AS hv_units,
			       SUM(CASE WHEN hco.configid IN (91,94)  THEN hco.qty ELSE 0 END) AS di_units,
			       SUM(CASE WHEN hco.configid IN (60,59)  THEN hco.qty ELSE 0 END) AS m365_units,
			       SUM(CASE WHEN hco.configid IN (99,101) THEN hco.qty ELSE 0 END) AS vmw_units
			FROM tblhostingconfigoptions hco
			GROUP BY hco.relid
		) b ON b.service_id = h.id
	";

	$where = [];
$params = ['hvStart' => $startSql, 'hvEnd' => $endSql, 'vmwStart' => $startSql, 'vmwEnd' => $endSql];
// Only active services
$where[] = "h.domainstatus = 'Active'";
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
		. 'COALESCE(hv.hv_count,0) AS hv_count, COALESCE(i.di_count,0) AS di_count, COALESCE(i.m365_count,0) AS m365_count, COALESCE(vmw.vmw_count,0) AS vmw_count, '
		. 'COALESCE(b.storage_units,0) AS storage_units, COALESCE(b.device_units,0) AS device_units, '
		. 'COALESCE(b.hv_units,0) AS hv_units, COALESCE(b.di_units,0) AS di_units, COALESCE(b.m365_units,0) AS m365_units, COALESCE(b.vmw_units,0) AS vmw_units, '
		. 'h.id AS service_id, h.userid AS user_id '
		. $sqlBase . ' '
		. $whereSql . ' '
		. 'ORDER BY ' . $orderByExpr . ' ' . $dir . ' '
		. 'LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
	$rows = DB::select($selectSql, $params);

	// Distinct product list
	$productsSql = 'SELECT DISTINCT p.id AS product_id, p.name AS product_name ' . $sqlBase . ' ORDER BY p.name ASC';
	$productRows = DB::select($productsSql, $params);
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
		$rowsOut[] = [
			'product_id'     => (int)$r->product_id,
			'product_name'   => (string)$r->product_name,
			'username'       => (string)$r->username,
			'total_bytes'    => (int)$r->total_bytes,
			'total_bytes_hr' => $humanBytes($r->total_bytes),
			'device_count'   => (int)$r->device_count,
			'hv_count'       => (int)$r->hv_count,
			'di_count'       => (int)$r->di_count,
			'm365_count'     => (int)$r->m365_count,
			'vmw_count'      => (int)$r->vmw_count,
			'storage_units'  => (int)$r->storage_units,
			'device_units'   => (int)$r->device_units,
			'hv_units'       => (int)$r->hv_units,
			'di_units'       => (int)$r->di_units,
			'm365_units'     => (int)$r->m365_units,
			'vmw_units'      => (int)$r->vmw_units,
			'service_id'     => (int)$r->service_id,
			'user_id'        => (int)$r->user_id,
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


