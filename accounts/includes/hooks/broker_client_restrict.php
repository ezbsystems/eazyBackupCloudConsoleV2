<?php

/**
 * Broker client-group restrictions: hard block + Smarty flag for nav hiding.
 */

require_once dirname(__DIR__, 2) . '/modules/addons/eazybackup/lib/BrokerClientRestrict.php';

add_hook('ClientAreaPage', 1, function (array $vars) {
    $isBrokerClient = false;

    try {
        $clientId = 0;
        if (isset($_SESSION['uid']) && (int)$_SESSION['uid'] > 0) {
            $clientId = (int)$_SESSION['uid'];
        } elseif (!empty($vars['clientsdetails']['id'])) {
            $clientId = (int)$vars['clientsdetails']['id'];
        }

        if ($clientId > 0) {
            $isBrokerClient = eazybackup_client_is_broker($clientId);

            if ($isBrokerClient && eazybackup_broker_request_is_denied()) {
                eazybackup_broker_redirect_dashboard();
            }
        }
    } catch (\Throwable $__) {
        $isBrokerClient = false;
    }

    return ['isBrokerClient' => $isBrokerClient];
});
