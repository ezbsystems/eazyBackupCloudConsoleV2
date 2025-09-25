<?php

use WHMCS\Database\Capsule as DB;

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

/**
 * Controller: Admin Power Panel → Devices
 *
 * Accepts optional query params:
 * - username: contains filter (case-sensitive)
 * - product: exact match on product id (int)
 * - sort: product|username|devices|units
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
	'product'  => 'p.name',
	'username' => 'v.username',
	'devices'  => 'v.device_count',
	'units'    => 'b.billed_units',
];
$orderByExpr = $sortMap[$sort] ?? $sortMap['username'];

// Base SQL: roll up devices by username (active, not revoked), map to WHMCS service and product
$sqlBase = "
	FROM (
		SELECT username,
		       COUNT(*) AS device_count
			FROM comet_devices
			WHERE revoked_at IS NULL
			  AND username IS NOT NULL AND username <> ''
		GROUP BY username
	) v
		JOIN tblhosting h
		  ON BINARY h.username = v.username AND h.domainstatus = 'Active'
	JOIN tblproducts p
	  ON p.id = h.packageid
	LEFT JOIN (
		SELECT hco.relid AS service_id, SUM(hco.qty) AS billed_units
		FROM tblhostingconfigoptions hco
                    WHERE hco.configid IN (88,89,93)
		GROUP BY hco.relid
	) b
	  ON b.service_id = h.id
";

$where = [];
$params = [];
// Only active services
$where[] = "h.domainstatus = 'Active'";
if ($filterUsername !== '') {
	$where[] = 'BINARY v.username LIKE :usernameLike';
	$params['usernameLike'] = '%' . $filterUsername . '%';
}
if ($filterProduct > 0) {
	$where[] = 'p.id = :productExact';
	$params['productExact'] = $filterProduct;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total rows for pagination
$countSql = 'SELECT COUNT(*) AS c FROM (SELECT h.id ' . $sqlBase . ' ' . $whereSql . ' GROUP BY h.id) t';
$totalRows = (int) (DB::selectOne($countSql, $params)->c ?? 0);

$offset = ($page - 1) * $perPage;

// Main data query
$selectSql = 'SELECT p.id AS product_id, p.name AS product_name, v.username, v.device_count, COALESCE(b.billed_units,0) AS billed_units, h.id AS service_id, h.userid AS user_id '
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
		'device_count'  => (int)$r->device_count,
		'billed_units'  => (int)$r->billed_units,
		'service_id'    => (int)$r->service_id,
		'user_id'       => (int)$r->user_id,
	];
}

// Build sort links helper
$buildUrl = function (array $overrides = []) use ($req) {
	$qs = array_merge([
		'action'  => 'powerpanel',
		'view'    => 'devices',
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
	'product'  => $buildUrl(['sort' => 'product',  'dir' => ($sort === 'product'  ? $toggleDir : 'asc')]),
	'username' => $buildUrl(['sort' => 'username', 'dir' => ($sort === 'username' ? $toggleDir : 'asc')]),
	'devices'  => $buildUrl(['sort' => 'devices',  'dir' => ($sort === 'devices'  ? $toggleDir : 'asc')]),
	'units'    => $buildUrl(['sort' => 'units',    'dir' => ($sort === 'units'    ? $toggleDir : 'asc')]),
];

// Pagination HTML (Bootstrap)
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$paginationHtml = '';
if ($totalPages > 1) {
	$paginationHtml .= '<nav aria-label="Page navigation"><ul class="pagination">';
	$prevUrl = $buildUrl(['page' => max(1, $page - 1)]);
	$nextUrl = $buildUrl(['page' => min($totalPages, $page + 1)]);
	$paginationHtml .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars($prevUrl) . '">Previous</a></li>';
	// Render up to 7 pages window
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


