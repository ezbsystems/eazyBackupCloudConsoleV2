<?php

use WHMCS\Database\Capsule as DB;

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

/**
 * Controller: Admin Power Panel → Storage Vaults
 *
 * Accepts optional query params:
 * - username: contains filter (case-sensitive)
 * - server: exact match on normalized server URL
 * - sort: username|server|bytes|units
 * - dir: asc|desc
 * - page: integer >= 1
 * - perPage: integer (default 25)
 */
return (function (): array {
	$req = $_GET + [];

	$filterUsername = isset($req['username']) ? trim((string)$req['username']) : '';
	$filterServer   = isset($req['server']) ? trim((string)$req['server']) : '';
	$sort           = isset($req['sort']) ? (string)$req['sort'] : 'username';
	$dir            = strtolower((string)($req['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
	$page           = max(1, (int)($req['page'] ?? 1));
	$perPage        = max(1, min(250, (int)($req['perPage'] ?? 25)));

	// Map sort keys to SQL expressions
	$sortMap = [
		'username' => 'v.username',
		'server'   => 'su.comet_server_url',
		'bytes'    => 'v.total_bytes',
		'units'    => 'b.billed_units',
	];
	$orderByExpr = $sortMap[$sort] ?? $sortMap['username'];

	// Base SQL (MySQL 5.7 compatible, no CTEs)
	// Roll up by username only; map to server strictly via tblhosting.server (source of truth)
	$sqlBase = "
		FROM (
			SELECT username,
			       SUM(total_bytes) AS total_bytes
			FROM comet_vaults
			WHERE type IN (1000,1003,1005,1007,1008)
			  AND username IS NOT NULL AND username <> ''
			GROUP BY username
		) v
		JOIN tblhosting h
		  ON BINARY h.username = v.username
		JOIN (
			SELECT
			  s.id AS server_id,
			  (CONCAT(
				CASE WHEN s.secure IN ('on','1',1) THEN 'https' ELSE 'http' END,
				'://',
				LOWER(TRIM(TRAILING '/' FROM s.hostname)),
				CASE WHEN s.port IS NULL OR s.port IN (80,443) THEN '' ELSE CONCAT(':', s.port) END
			  ) COLLATE utf8mb3_unicode_ci) AS comet_server_url
			FROM tblservers s
		) su
		  ON su.server_id = h.server
		LEFT JOIN (
			SELECT hco.relid AS service_id, SUM(hco.qty) AS billed_units
			FROM tblhostingconfigoptions hco
			WHERE hco.configid IN (61,67)
			GROUP BY hco.relid
		) b
		  ON b.service_id = h.id
	";

	$where = [];
	$params = [];
	if ($filterUsername !== '') {
		$where[] = 'BINARY v.username LIKE :usernameLike';
		$params['usernameLike'] = '%' . $filterUsername . '%';
	}
	if ($filterServer !== '') {
		$where[] = 'su.comet_server_url = :serverExact';
		$params['serverExact'] = $filterServer;
	}
	$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

	// Count total rows for pagination
	$countSql = 'SELECT COUNT(*) AS c FROM (SELECT v.username, su.comet_server_url ' . $sqlBase . ' ' . $whereSql . ' GROUP BY v.username, su.comet_server_url) t';
	$totalRows = (int) (DB::selectOne($countSql, $params)->c ?? 0);

	$offset = ($page - 1) * $perPage;

	// Main data query
	$selectSql = 'SELECT v.username, su.comet_server_url, v.total_bytes, COALESCE(b.billed_units,0) AS billed_units, h.id AS service_id, h.userid AS user_id '
		. $sqlBase . ' ' . $whereSql . ' '
		. 'ORDER BY ' . $orderByExpr . ' ' . $dir . ' '
		. 'LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
	$rows = DB::select($selectSql, $params);

	// Distinct server list (for filter dropdown)
	$serversSql = 'SELECT DISTINCT su.comet_server_url ' . $sqlBase . ' ORDER BY su.comet_server_url ASC';
	$serverRows = DB::select($serversSql);
	$servers = array_map(function ($r) { return $r->comet_server_url; }, $serverRows);

	// Helper to format bytes
	$humanBytes = function ($bytes) {
		$units = ['bytes','KB','MB','GB','TB','PB'];
		$i = 0;
		$bytes = (float)$bytes;
		while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
		return sprintf('%0.2f %s', $bytes, $units[$i]);
	};

	// Enrich rows with human-readable size
	$rowsOut = [];
	foreach ($rows as $r) {
		$rowsOut[] = [
			'username'         => $r->username,
			'comet_server_url' => $r->comet_server_url,
			'total_bytes'      => (int)$r->total_bytes,
			'total_bytes_hr'   => $humanBytes($r->total_bytes),
			'billed_units'     => (int)$r->billed_units,
			'service_id'       => (int)$r->service_id,
			'user_id'          => (int)$r->user_id,
		];
	}

	// Build sort links helper
	$buildUrl = function (array $overrides = []) use ($req) {
		$qs = array_merge([
			'action'  => 'powerpanel',
			'view'    => 'storage',
			'username'=> $req['username'] ?? '',
			'server'  => $req['server'] ?? '',
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
		'username' => $buildUrl(['sort' => 'username', 'dir' => ($sort === 'username' ? $toggleDir : 'asc')]),
		'server'   => $buildUrl(['sort' => 'server',   'dir' => ($sort === 'server'   ? $toggleDir : 'asc')]),
		'bytes'    => $buildUrl(['sort' => 'bytes',    'dir' => ($sort === 'bytes'    ? $toggleDir : 'asc')]),
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
		'servers'     => $servers,
		'filters'     => [
			'username' => $filterUsername,
			'server'   => $filterServer,
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


