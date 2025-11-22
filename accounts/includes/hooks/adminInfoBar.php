<?php

use WHMCS\Database\Capsule;
use WHMCS\Billing\Invoice;

function admin_v8_infobar_hook($vars) {
	
	$pendingstatuslist = Capsule::table('tblorderstatuses')->where('showpending','1')->pluck('title');
	$pendingorders = Capsule::table('tblorders')->whereIn('status',$pendingstatuslist)->count();
	$overdueinvoices = Invoice::overdue()->count();
	$awaitingreplylist = Capsule::table('tblticketstatuses')->where('showawaiting','1')->pluck('title');
	$ticketsawaiting = Capsule::table('tbltickets')->whereIn('status',$awaitingreplylist)->count();
	$headerreturn = '<div style="margin: 0; padding: 5px; background-color: #00082D; display: block; width: 100%; max-height: 20px;">
	<div style="text-align: center; color: #fff; font-size: .8em; margin: 0;">
		<a href="orders.php?status=Pending" style="color: #fff;"><span style="font-weight: 700; color: #fc0;">'.$pendingorders.'</span> '.AdminLang::trans('stats.pendingorders').'</a> |
		<a href="invoices.php?status=Overdue" style="color: #fff;"><span style="font-weight: 700; color: #fc0;">'.$overdueinvoices.'</span> '.AdminLang::trans('stats.overdueinvoices').'</a> |
		<a href="supporttickets.php" style="color: #fff;"><span style="font-weight: 700; color: #fc0;">'.$ticketsawaiting.'</span> '.AdminLang::trans('stats.ticketsawaitingreply').'</a>
	</div>
</div>';
	return $headerreturn;
}
add_hook("AdminAreaHeaderOutput",1,"admin_v8_infobar_hook");