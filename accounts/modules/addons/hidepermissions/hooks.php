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
        $tpl = isset($vars['templatefile']) ? (string)$vars['templatefile'] : '';

        $isTargetPage = in_array($tpl, [
            'account-contacts-manage',
            'account-contacts-new',
            'clientareacontacts',
            'contacts',
            'account-user-management',
            'account-user-permissions',
            'account-users-permissions',
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

        return '<script>
function hideDomainPermissionFields() {
  var selectors = [
    "input[name=\"perms[managedomains]\"]",
    "input[name=\"perms[domains]\"]",
    "input[name=\"perms[affiliate]\"]",
    "input[name=\"perms[affiliates]\"]",
    "input[name=\"email_preferences[domain]\"]"
  ];
  var titlePatterns = [
    /^view domains$/i,
    /^manage domain settings$/i,
    /^view\s*(?:&|and)\s*manage affiliate account$/i
  ];

  function removeNode(node) {
    if (!node || !node.parentNode) {
      return;
    }
    node.parentNode.removeChild(node);
  }

  function removePermissionContainer(input) {
    var container = input.closest(".eb-choice-card")
      || input.closest("label")
      || input.closest(".form-group")
      || input.closest("tr");

    if (container) {
      removeNode(container);
      return true;
    }

    if (input.parentElement) {
      removeNode(input.parentElement);
      return true;
    }

    return false;
  }

  selectors.forEach(function (selector) {
    document.querySelectorAll(selector).forEach(function (input) {
      removePermissionContainer(input);
    });
  });

  document.querySelectorAll(".eb-choice-card, label, tr, .form-group").forEach(function (node) {
    var titleNode = node.querySelector(".eb-choice-card-title");
    var text = titleNode ? titleNode.textContent.trim() : node.textContent.trim();

    if (!text) {
      return;
    }

    var shouldHide = titlePatterns.some(function (pattern) {
      return pattern.test(text);
    });

    if (shouldHide) {
      removeNode(node);
    }
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", hideDomainPermissionFields);
} else {
  hideDomainPermissionFields();
}
</script>';
    }
});
