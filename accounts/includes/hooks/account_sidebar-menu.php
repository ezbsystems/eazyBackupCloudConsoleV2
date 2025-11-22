<?php

use WHMCS\View\Menu\Item as MenuItem;
add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $primarySidebar)

{
	if (!is_null($primarySidebar->getChild('Account'))) {
		$primarySidebar->getChild('Account')
	->addChild('Your Profile')
		->setLabel('Your profile')
		->setUri('index.php?rp=/user/profile')
		->setOrder(1);
	}
});

add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $primarySidebar)

{
	if (!is_null($primarySidebar->getChild('Account'))) {
		$primarySidebar->getChild('Account')
	->addChild('Change Password')
		->setLabel('Change Password')
		->setUri('index.php?rp=/user/password')
		->setOrder(2);
	}
});

add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $primarySidebar)

{
	if (!is_null($primarySidebar->getChild('Account'))) {
		$primarySidebar->getChild('Account')
	->addChild('User Security')
    ->setLabel('Two-factor authentication')		
		->setUri('index.php?rp=/user/security')
		->setOrder(3);
	}
});

add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $primarySidebar)

{
	if (!is_null($primarySidebar->getChild('Account'))) {
		$primarySidebar->getChild('Account')
	->getChild('Payment Methods')
		->setOrder(4);
	}
});

// Hide Services menu for subaccounts without permission
add_hook('ClientAreaPrimarySidebar', 2, function (MenuItem $primarySidebar) {
    if (!isset($_SESSION['uid']) || isset($_SESSION['adminid'])) {
        return;
    }
    try {
        $currentClientId = (int) $_SESSION['uid'];
        $ownerId = $currentClientId;
        try {
            if (class_exists('WHMCS\\Authentication\\Auth') && method_exists('WHMCS\\Authentication\\Auth', 'client')) {
                $c = \WHMCS\Authentication\Auth::client();
                if ($c && isset($c->ownerId) && (int) $c->ownerId > 0) {
                    $ownerId = (int) $c->ownerId;
                }
            }
        } catch (\Throwable $ignored) {}
        if ($ownerId !== $currentClientId) {
            $perm = \WHMCS\Database\Capsule::table('eazybackup_user_permissions')
                ->where('userid', $ownerId)
                ->where('subaccountid', $currentClientId)
                ->first();
            if ($perm && isset($perm->can_access_services) && (int) $perm->can_access_services === 0) {
                if (!is_null($primarySidebar->getChild('Services'))) {
                    $primarySidebar->removeChild('Services');
                }
            }
        }
    } catch (\Throwable $ignored) {}
});