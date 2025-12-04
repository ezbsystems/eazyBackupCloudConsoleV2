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
        // Limit to contacts/sub-accounts & user/user-permissions pages only
        $tpl = isset($vars['templatefile']) ? (string)$vars['templatefile'] : '';

        // Target templates:
        // - Contacts/sub-accounts management
        // - User management (invite form)
        // - User permissions pages
        $isTargetPage = in_array($tpl, [
            'account-contacts-manage',
            'clientareacontacts',
            'contacts',
            'account-user-management',    // Users page (invite permissions)
            'account-user-permissions',   // Older/singular naming
            'account-users-permissions',  // Newer/plural naming used by routePath('account-users-permissions', ...)
        ], true);

        if (!$isTargetPage) {
            $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
            if (strpos($uri, 'clientarea.php') !== false && strpos($uri, 'action=contacts') !== false) {
                $isTargetPage = true;
            }
        }

        if (!$isTargetPage) {
            return '';
        }

        // Vanilla JS (no jQuery) to remove domain-related permission inputs from both
        // Contacts and Users templates
        return '<script>
document.addEventListener("DOMContentLoaded", function () {
  var selectors = [
    "input[name=\"perms[managedomains]\"]",
    "input[name=\"perms[domains]\"]",
    "input[name=\"email_preferences[domain]\"]"
  ];
  selectors.forEach(function (sel) {
    var inputs = document.querySelectorAll(sel);
    if (!inputs.length) return;
    inputs.forEach(function (input) {
      var parent = input.parentElement;
      if (parent && parent.previousElementSibling && parent.previousElementSibling.tagName === "BR") {
        parent.previousElementSibling.remove();
      }
      if (parent) parent.remove();
    });
  });
});
</script>';
    }
});
