<?php
/* * ********************************************************************
*  Hide Domain Permissions from client area by WHMCS Services
*
* Created By WHMCSServices http://www.whmcsservices.com
* Contact Paul: dev@whmcsservices.com
*
* This software is furnished under a license and may be used and copied
* only in accordance with the terms of such license and with the
* inclusion of the above copyright notice. This software or any other
* copies thereof may not be provided or otherwise made available to any
* other person. No title to and ownership of the software is hereby
* transferred.
* ******************************************************************** */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    if (defined('CLIENTAREA') && CLIENTAREA == 1) {
        // Limit to contacts/sub-accounts page only
        $tpl = isset($vars['templatefile']) ? (string)$vars['templatefile'] : '';
        $isContacts = in_array($tpl, ['account-contacts-manage', 'clientareacontacts', 'contacts'], true);
        if (!$isContacts) {
            $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
            if (strpos($uri, 'clientarea.php') !== false && strpos($uri, 'action=contacts') !== false) {
                $isContacts = true;
            }
        }
        if (!$isContacts) {
            return '';
        }
        // Vanilla JS (no jQuery) to remove domain-related permission inputs
        return '<script>
document.addEventListener("DOMContentLoaded", function () {
  var selectors = [
    "input[name=\"perms[managedomains]\"]",
    "input[name=\"perms[domains]\"]",
    "input[name=\"email_preferences[domain]\"]"
  ];
  selectors.forEach(function (sel) {
    var input = document.querySelector(sel);
    if (!input) return;
    var parent = input.parentElement;
    if (parent && parent.previousElementSibling && parent.previousElementSibling.tagName === "BR") {
      parent.previousElementSibling.remove();
    }
    if (parent) parent.remove();
  });
});
</script>';
    }
});
