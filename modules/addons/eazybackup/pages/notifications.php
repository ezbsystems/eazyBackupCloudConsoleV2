<?php
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('Access denied'); }

$serviceId = isset($_GET['serviceid']) ? (int)$_GET['serviceid'] : 0;
$clientId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;

// Basic list of recent notifications scoped to this service (client safety check)
$q = Capsule::table('eb_notifications_sent as n')
    ->join('tblhosting as h', 'h.id', '=', 'n.service_id')
    ->where('n.service_id', $serviceId)
    ->where('h.userid', $clientId)
    ->orderBy('n.created_at', 'desc')
    ->limit(100)
    ->get(['n.created_at','n.category','n.subject','n.template','n.recipients']);

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Recent Notifications</h2>';
echo '<table class="table table-striped">';
echo '<thead><tr><th>Date</th><th>Category</th><th>Subject</th><th>Template</th></tr></thead><tbody>';
foreach ($q as $row) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row->created_at) . '</td>';
    echo '<td>' . htmlspecialchars($row->category) . '</td>';
    echo '<td>' . htmlspecialchars($row->subject) . '</td>';
    echo '<td>' . htmlspecialchars($row->template) . '</td>';
    echo '</tr>';
}
echo '</tbody></table>';


