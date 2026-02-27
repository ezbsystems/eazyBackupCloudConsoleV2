<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Returns the WHMCS clientarea payload for the global Job Logs page.
 *
 * @param array $vars Incoming vars from the module (includes modulelink, etc.)
 * @return array WHMCS clientarea payload
 */
function eazybackup_job_logs_global(array $vars)
{
    return [
        'pagetitle'    => 'Job Logs',
        'templatefile' => 'templates/console/job-logs-global',
        'requirelogin' => true,
        'forcessl'     => true,
        'vars'         => array_merge($vars, [
            'modulelink' => $vars['modulelink'] ?? '',
        ]),
    ];
}
