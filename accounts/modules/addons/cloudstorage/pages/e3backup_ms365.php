<?php

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$clientId = (int) $ca->getUserID();
$ms365Status = Ms365E3Controller::status($clientId);

return [
    'activeNav' => 'ms365',
    'ms365Status' => $ms365Status,
    'ms365Presets' => [
        [
            'id' => 'user_mail_calendar',
            'label' => 'All users — mail + calendar',
            'description' => 'Back up mailbox and calendar for every licensed user in your tenant.',
        ],
        [
            'id' => 'collaboration',
            'label' => 'Collaboration',
            'description' => 'SharePoint sites, Teams, and Microsoft 365 groups (files, lists, messages).',
        ],
        [
            'id' => 'full',
            'label' => 'Full tenant',
            'description' => 'Users, OneDrive, collaboration, Planner, OneNote, and directory baseline.',
        ],
    ],
];
