<?php

use WHMCS\Database\Capsule as DB;

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

/**
 * Controller: Admin Power Panel → Protected Items
 *
 * Accepts optional query params:
 * - username: contains filter (case-sensitive)
 * - product: exact match on product id (int)
 * - sort: product|username|hv|hv_units|di|di_units|m365|m365_units|vmw|vmw_units
 * - dir: asc|desc
 * - page: integer >= 1
 * - perPage: integer (default 25)
 */
$req = $_GET + [];

$filterUsername = isset($req['username']) ? trim((string)$req['username']) : '';
$filterProduct  = isset($req['product']) ? (int)$req['product'] : 0;
$sort           = isset($req['sort']) ? (string)$req['sort'] : 'username';
$dir            = strtolower((string)($req['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$page           = max(1, (int)($req['page'] ?? 1));
$perPage        = max(1, min(250, (int)($req['perPage'] ?? 25)));

// Map sort keys to SQL expressions
$sortMap = [
	'product'   => 'p.name',
	'username'  => 'i.username',
	'hv'        => 'i.hv_count',
	'hv_units'  => 'b.hv_units',
	'di'        => 'i.di_count',
	'di_units'  => 'b.di_units',
	'm365'      => 'i.m365_count',
	'm365_units'=> 'b.m365_units',
	'vmw'       => 'i.vmw_count',
	'vmw_units' => 'b.vmw_units',
];
$orderByExpr = $sortMap[$sort] ?? $sortMap['username'];

// Base SQL: pivot protected item counts by engine per username; join to service and product; left join billing per service
$sqlBase = "
	FROM (
		SELECT BINARY username AS username,
		       SUM(CASE WHEN type = 'engine1/hyperv'          THEN 1 ELSE 0 END) AS hv_count,
		       SUM(CASE WHEN type = 'engine1/windisk'         THEN 1 ELSE 0 END) AS di_count,
		       SUM(CASE WHEN type = 'engine1/winmsofficemail' THEN 1 ELSE 0 END) AS m365_count,
		       SUM(CASE WHEN type = 'engine1/vmware'          THEN 1 ELSE 0 END) AS vmw_count
		FROM comet_items
		WHERE username IS NOT NULL AND username <> ''
		GROUP BY BINARY username
	) i
		JOIN tblhosting h
		  ON BINARY h.username = i.username AND h.domainstatus = 'Active'
	JOIN tblproducts p
	  ON p.id = h.packageid
	LEFT JOIN (
		SELECT hco.relid AS service_id,
		       SUM(CASE WHEN hco.configid IN (97,82)   THEN hco.qty ELSE 0 END) AS hv_units,
		       SUM(CASE WHEN hco.configid IN (91,94)   THEN hco.qty ELSE 0 END) AS di_units,
		       SUM(CASE WHEN hco.configid IN (60,59)   THEN hco.qty ELSE 0 END) AS m365_units,
		       SUM(CASE WHEN hco.configid IN (99,101)  THEN hco.qty ELSE 0 END) AS vmw_units
		FROM tblhostingconfigoptions hco
		GROUP BY hco.relid
	) b
	  ON b.service_id = h.id
";

$where = [];
$params = [];
// Only active services
$where[] = "h.domainstatus = 'Active'";
// Exclude specific product permanently (pid=48)
$where[] = 'p.id <> 48';
if ($filterUsername !== '') {
	$where[] = 'BINARY i.username LIKE :usernameLike';
	$params['usernameLike'] = '%' . $filterUsername . '%';
}
if ($filterProduct > 0) {
	$where[] = 'p.id = :productExact';
	$params['productExact'] = $filterProduct;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total rows for pagination (per service)
$countSql = 'SELECT COUNT(*) AS c FROM (SELECT h.id ' . $sqlBase . ' ' . $whereSql . ' GROUP BY h.id) t';
$totalRows = (int) (DB::selectOne($countSql, $params)->c ?? 0);

$offset = ($page - 1) * $perPage;

// Main data query
$selectSql = 'SELECT p.id AS product_id, p.name AS product_name, i.username, '
	. 'i.hv_count, i.di_count, i.m365_count, i.vmw_count, '
	. 'COALESCE(b.hv_units,0) AS hv_units, COALESCE(b.di_units,0) AS di_units, COALESCE(b.m365_units,0) AS m365_units, COALESCE(b.vmw_units,0) AS vmw_units, '
	. 'h.id AS service_id, h.userid AS user_id '
	. $sqlBase . ' '
	. $whereSql . ' '
	. 'ORDER BY ' . $orderByExpr . ' ' . $dir . ' '
	. 'LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
$rows = DB::select($selectSql, $params);

// Distinct product list (for filter dropdown) — restrict to active services
$productsSql = 'SELECT DISTINCT p.id AS product_id, p.name AS product_name ' . $sqlBase . " WHERE h.domainstatus = 'Active' ORDER BY p.name ASC";
$productRows = DB::select($productsSql);
$products = array_map(function ($r) { return ['id' => (int)$r->product_id, 'name' => (string)$r->product_name]; }, $productRows);

// Enrich rows
$rowsOut = [];
foreach ($rows as $r) {
	$rowsOut[] = [
		'product_id'    => (int)$r->product_id,
		'product_name'  => (string)$r->product_name,
		'username'      => (string)$r->username,
		'hv_count'      => (int)$r->hv_count,
		'hv_units'      => (int)$r->hv_units,
		'di_count'      => (int)$r->di_count,
		'di_units'      => (int)$r->di_units,
		'm365_count'    => (int)$r->m365_count,
		'm365_units'    => (int)$r->m365_units,
		'vmw_count'     => (int)$r->vmw_count,
		'vmw_units'     => (int)$r->vmw_units,
		'service_id'    => (int)$r->service_id,
		'user_id'       => (int)$r->user_id,
	];
}

// Build sort links helper
$buildUrl = function (array $overrides = []) use ($req) {
	$qs = array_merge([
		'action'  => 'powerpanel',
		'view'    => 'items',
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
	'product'    => $buildUrl(['sort' => 'product',    'dir' => ($sort === 'product'    ? $toggleDir : 'asc')]),
	'username'   => $buildUrl(['sort' => 'username',   'dir' => ($sort === 'username'   ? $toggleDir : 'asc')]),
	'hv'         => $buildUrl(['sort' => 'hv',         'dir' => ($sort === 'hv'         ? $toggleDir : 'asc')]),
	'hv_units'   => $buildUrl(['sort' => 'hv_units',   'dir' => ($sort === 'hv_units'   ? $toggleDir : 'asc')]),
	'di'         => $buildUrl(['sort' => 'di',         'dir' => ($sort === 'di'         ? $toggleDir : 'asc')]),
	'di_units'   => $buildUrl(['sort' => 'di_units',   'dir' => ($sort === 'di_units'   ? $toggleDir : 'asc')]),
	'm365'       => $buildUrl(['sort' => 'm365',       'dir' => ($sort === 'm365'       ? $toggleDir : 'asc')]),
	'm365_units' => $buildUrl(['sort' => 'm365_units', 'dir' => ($sort === 'm365_units' ? $toggleDir : 'asc')]),
	'vmw'        => $buildUrl(['sort' => 'vmw',        'dir' => ($sort === 'vmw'        ? $toggleDir : 'asc')]),
	'vmw_units'  => $buildUrl(['sort' => 'vmw_units',  'dir' => ($sort === 'vmw_units'  ? $toggleDir : 'asc')]),
];

// Pagination HTML (Bootstrap)
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



