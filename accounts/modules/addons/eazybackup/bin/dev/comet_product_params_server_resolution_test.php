<?php
declare(strict_types=1);

require_once dirname(__DIR__, 5) . '/init.php';
require_once dirname(__DIR__, 5) . '/modules/servers/comet/functions.php';

use WHMCS\Database\Capsule;

$fixture = Capsule::table('tblproducts as p')
    ->join('tblservergroups as sg', 'sg.id', '=', 'p.servergroup')
    ->join('tblservergroupsrel as sgr', 'sgr.groupid', '=', 'sg.id')
    ->join('tblservers as s', 's.id', '=', 'sgr.serverid')
    ->where('p.servertype', 'comet')
    ->whereColumn('sg.name', '<>', 's.name')
    ->where('s.hostname', '<>', '')
    ->select(['p.id as product_id', 's.id as server_id', 's.hostname', 's.username'])
    ->first();

if (!$fixture) {
    fwrite(STDERR, "FAIL: no relational server-resolution fixture found\n");
    exit(1);
}

$params = comet_ProductParams((int) $fixture->product_id, (int) $fixture->server_id);
$passed = hash_equals((string) $fixture->hostname, (string) ($params['serverhostname'] ?? ''))
    && hash_equals((string) $fixture->username, (string) ($params['serverusername'] ?? ''));

if (!$passed) {
    fwrite(STDERR, "FAIL: assigned related server was not resolved\n");
    exit(1);
}

fwrite(STDOUT, "comet-product-params-server-resolution-ok\n");
