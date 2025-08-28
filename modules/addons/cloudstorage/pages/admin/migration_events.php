<?php

use WHMCS\Database\Capsule;

function cloudstorage_admin_migration_events($vars)
{
    $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
    $limit = isset($_GET['limit']) ? max(10, (int) $_GET['limit']) : 50;

    $query = Capsule::table('s3_migration_events as e')
        ->leftJoin('tblclients as c', 'e.client_id', '=', 'c.id')
        ->select(
            'e.id','e.client_id','e.actor_admin_id','e.action','e.from_alias','e.to_alias','e.notes','e.created_at',
            'c.firstname','c.lastname','c.companyname'
        )
        ->orderBy('e.created_at', 'desc')
        ->limit($limit);

    if ($clientId) {
        $query->where('e.client_id', $clientId);
    }

    $events = $query->get();

    $html = '<div class="p-4">';
    $html .= '<h2>Migration Events</h2>';

    // Filter form
    $html .= '<form method="get" class="mb-3">'
        . '<input type="hidden" name="module" value="cloudstorage">'
        . '<input type="hidden" name="action" value="migration_events">'
        . '<div class="row g-2">'
        .   '<div class="col-auto">'
        .       '<input type="number" class="form-control" name="client_id" placeholder="Client ID" value="' . htmlspecialchars((string)$clientId) . '">'
        .   '</div>'
        .   '<div class="col-auto">'
        .       '<select name="limit" class="form-select">'
        .           '<option value="50"' . ($limit==50?' selected':'') . '>Last 50</option>'
        .           '<option value="100"' . ($limit==100?' selected':'') . '>Last 100</option>'
        .           '<option value="200"' . ($limit==200?' selected':'') . '>Last 200</option>'
        .       '</select>'
        .   '</div>'
        .   '<div class="col-auto">'
        .       '<button class="btn btn-primary" type="submit">Filter</button>'
        .   '</div>'
        . '</div>'
        . '</form>';

    $html .= '<table class="table table-bordered table-striped align-middle">'
        . '<thead class="table-dark"><tr>'
        . '<th>ID</th><th>Client</th><th>Action</th><th>From</th><th>To</th><th>Notes</th><th>When</th>'
        . '</tr></thead><tbody>';

    if ($events->isEmpty()) {
        $html .= '<tr><td colspan="7" class="text-center">No events found.</td></tr>';
    } else {
        foreach ($events as $e) {
            $clientName = trim(($e->firstname ?? '') . ' ' . ($e->lastname ?? '') . ' ' . ($e->companyname ?? ''));
            $clientDisplay = htmlspecialchars($clientName ?: '');
            $eventsUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=migration_events&client_id=' . (int)$e->client_id;
            $managerUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=migration_manager&client_id=' . (int)$e->client_id;
            $clientCell = '<div class="d-flex align-items-center gap-2">'
                . '<a href="' . htmlspecialchars($eventsUrl) . '" class="text-decoration-none">'
                .    (int)$e->client_id . ' - ' . $clientDisplay
                . '</a>'
                . '<a href="' . htmlspecialchars($managerUrl) . '" class="btn btn-sm btn-outline-secondary">Manage</a>'
                . '</div>';
            $notes = $e->notes ? htmlspecialchars($e->notes) : '';
            $badgeClass = 'bg-secondary';
            switch ($e->action) {
                case 'freeze': $badgeClass = 'bg-warning text-dark'; break;
                case 'unfreeze': $badgeClass = 'bg-success'; break;
                case 'flip': $badgeClass = 'bg-info'; break;
                case 'rollback': $badgeClass = 'bg-danger'; break;
                case 'sync': $badgeClass = 'bg-primary'; break;
                case 'verify': $badgeClass = 'bg-dark'; break;
            }
            $html .= '<tr>'
                . '<td>' . (int)$e->id . '</td>'
                . '<td>' . $clientCell . '</td>'
                . '<td><span class="badge ' . $badgeClass . '">' . htmlspecialchars($e->action) . '</span></td>'
                . '<td>' . htmlspecialchars((string)$e->from_alias) . '</td>'
                . '<td>' . htmlspecialchars((string)$e->to_alias) . '</td>'
                . '<td><code>' . $notes . '</code></td>'
                . '<td>' . htmlspecialchars($e->created_at) . '</td>'
                . '</tr>';
        }
    }

    $html .= '</tbody></table>';
    $html .= '</div>';

    return $html;
}


