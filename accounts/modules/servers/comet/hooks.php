<?php

// Require the autoloader first, before any use statements
require_once __DIR__ . '/vendor/autoload.php';

use \WHMCS\Database\Capsule;
use Comet\BackupJobDetail;
use Comet\Server;
use Comet\UserProfileConfig;

/**
 * Comet Provisioning Module Hooks
 *
 * @copyright (c) 2019 eazyBackup Systems Ltd.
 */

// Require any libraries needed for the module to function.
require_once __DIR__ . "/functions.php";
require_once __DIR__ . '/summary_functions.php';

// Also, perform any initialization required by the service's library.

/**
 * Register hooks with WHMCS.
 */

/**
 * Validates backup account username is available on the Comet server.
 *
 * Validation for username/password length and character set is done
 * using a regular expression in the custom field configuration.
 */
$username = '';
    
// Attempt to get the username from $vars, assuming a nested array first
// if (isset($vars["customfield"]) && is_array($vars["customfield"])) {
//     $username = isset($vars["customfield"][$id]) ? trim($vars["customfield"][$id]) : '';
//     error_log("[DEBUG] Found username in \$vars['customfield']: " . var_export($username, true));
// }

// Try the direct key approach if still empty
// if ($username === '') {
//     $directKey = "customfield[" . $id . "]";
//     if (isset($vars[$directKey])) {
//         $username = trim($vars[$directKey]);
//         error_log("[DEBUG] Found username using direct key in \$vars: " . var_export($username, true));
//     } else {
//         error_log("[DEBUG] Direct key '$directKey' not found in \$vars.");
//     }
// }

// Fall back to $_REQUEST if still empty
// if ($username === '' && isset($_REQUEST['customfield'])) {
//     if (is_array($_REQUEST['customfield'])) {
//         $username = isset($_REQUEST['customfield'][$id]) ? trim($_REQUEST['customfield'][$id]) : '';
//         error_log("[DEBUG] Found username in \$_REQUEST['customfield']: " . var_export($username, true));
//     } else {
//         error_log("[DEBUG] \$_REQUEST['customfield'] is not an array: " . var_export($_REQUEST['customfield'], true));
//     }
// }

// Final check: if still empty, temporarily set a fallback for testing purposes.
// if ($username === '') {
//     error_log("[DEBUG] Username still empty; setting fallback username for debugging.");
//     // Uncomment the next line for a temporary fallback (remove once debugging is complete)
//     // $username = 'DEBUG_USERNAME';
// }

// error_log("[DEBUG] Final username value: " . var_export($username, true));


/**
 * Validates quantities when upgrading/downgrading config options.
 */
add_hook('ClientAreaPageUpgrade', 1, function ($vars) {
    $error = false;
    $params = comet_ServiceParams($vars["id"]);

    // Quantity page
    if ($vars["type"] == "configoptions" && $vars["templatefile"] == "upgrade") {
        $_SESSION["configoptions"] = [];

        foreach ($vars["configoptions"] as $option) {
            $type = strtolower(explode(" ", $option["optionname"])[1]);

            // Store config options details in the session
            $_SESSION["configoptions"][$type] = $option;
        }

        $vars["configoptionstable"] = $_SESSION["configoptions"];

        foreach ($vars["configoptionstable"] as $name => $option) {
            $vars["configoptionstable"][$name]["price"] = (float) filter_var(explode("$", $option["selectedoption"])[1], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }

        $vars["devices"] = max(1, comet_DeviceCount(comet_User($params)));
        $vars["maxdevices"] = comet_User($params)->MaximumDevices;
        $vars["minstorageplan"] = ceil((comet_StorageUsedBytes(comet_User($params)) + 1) / 2 ** 40);
        $vars["humanminstorageplan"] = comet_HumanFileSize($vars["minstorageplan"] * 2 ** 40);
    }

    // Checkout page
    if ($vars["type"] == "configoptions" && $vars["templatefile"] == "upgradesummary") {
        $requestedStorageQuota = -1;
        $requestedDeviceQuota = -1;

        // Get requested storage/device config option quantities
        foreach ($_SESSION["configoptions"] as $configoption) {
            if ($configoption["optionname"] == "Additional Storage") {
                $requestedStorageQuota = $vars["configoptions"][$configoption["id"]];
            }
            if ($configoption["optionname"] == "Additional Devices") {
                $requestedDeviceQuota = $vars["configoptions"][$configoption["id"]];
            }
        }

        // Get minimum/maximum quotas
        $minimumStorageQuota = (comet_StorageUsedBytes($params) / 2 ** 40) - 1;
        $minimumDeviceQuota = comet_DeviceCount($params) - 1;

        if (!is_numeric($requestedStorageQuota) || !is_numeric($requestedDeviceQuota)) {
            $_SESSION["errormessage"] = "You must enter a number";
            $error = true;
        } else if ((int)$requestedStorageQuota < $minimumStorageQuota) {
            $_SESSION["errormessage"] = "You are currently using more than $requestedStorageQuota TB of storage";
            $error = true;
        } else if ((int)$requestedDeviceQuota < $minimumDeviceQuota) {
            $_SESSION["errormessage"] = "You currently have more than $requestedDeviceQuota devices registered";
            $error = true;
        }

        if ($error == true) {
            header("Location: " . html_entity_decode($_SERVER["HTTP_REFERER"]));
            exit;
        }
    }

    if (!empty($_SESSION["errormessage"])) {
        $vars["errormessage"] = $_SESSION["errormessage"];
        unset($_SESSION["errormessage"]);
    }

    return $vars;
});

/**
 * Modify Client Area homepage panels
 */
// add_hook('ClientAreaHomepagePanels', 1, function ($homePagePanels) {
//     $products = $homePagePanels->getChild("Active Products/Services");

//     foreach ($products->getChildren() as $product) {
//         $sid = explode("&id=", $product->getUri())[1];
//         $params = comet_ServiceParams($sid);

//         if (empty($params["domain"])) {
//             $product->setLabel($product->getLabel() . "<span class=\"text-domain\">" . $params["username"] . "</span>");
//         }

//         $product->setLabel(join(explode("<br />", $product->getLabel())));
//     }

//     return $products;
// });

/**
 * Add back link to Client Area product details sidebar.
 */
// add_hook('ClientAreaPrimarySidebar', 1, function ($primarySidebar) {
//     if (!empty($_REQUEST["action"]) && $_REQUEST["action"] == "productdetails") {
//         $overview = $primarySidebar->getChild("Service Details Overview");
//         if ($overview !== null) {
//             $overview->addChild("Show All Services")->setOrder(20)->setLabel("My Services")->setUri("/clientarea.php?action=services");
//             $overview->removeChild("Information");
//         }
//     }
// });

add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    if ($vars["filename"] == "clientsservices") {
        // Ensure 'userid' is present in the request
        $clientId = isset($_REQUEST["userid"]) ? intval($_REQUEST["userid"]) : 0;
        if ($clientId === 0) {
            return ''; // Invalid client ID, exit early
        }

        // Fetch client products using the local API
        $result = localAPI("GetClientsProducts", ['clientid' => $clientId]);

        // Check if the API call was successful and products are returned
        if ($result['result'] !== 'success' || !isset($result['products']['product'])) {
            return ''; // No products or API error, exit early
        }

        $products = $result['products']['product'];

        // Normalize $products to always be an array
        if (isset($products['id'])) {
            // Only one product returned, wrap it in an array
            $products = [$products];
        } elseif (!is_array($products)) {
            // Unexpected format, treat as no products
            $products = [];
        }

        // Initialize usernames array
        $usernames = [];

        // Iterate over each product to build the usernames array
        foreach ($products as $product) {
            // Ensure 'id' and 'name' exist for each product
            if (!isset($product["id"]) || !isset($product["name"])) {
                continue; // Skip invalid product entries
            }

            $usernames[$product["id"]] = $product["name"];

            if (!empty($product["username"])) {
                $usernames[$product["id"]] .= " - " . $product["username"];
            } elseif (!empty($product["domain"])) {
                $usernames[$product["id"]] .= " - " . $product["domain"];
            }
        }

        // If no valid usernames were collected, exit early
        if (empty($usernames)) {
            return '';
        }

        // Encode the usernames array to JSON for use in JavaScript
        $usernames_json = json_encode($usernames);

        // Return the JavaScript to update the product select dropdown
        return <<<HTML
<script type="text/javascript">
    $(document).ready(function() {
        var usernames = $usernames_json;
        var selectize = $("[name=productselect]")[0].selectize;

        if (selectize) {
            Object.keys(usernames).forEach(function(id) {
                selectize.updateOption(id, {value: id, name: usernames[id]});
            });
        }
    });
</script>
HTML;
    }
});

/**
 * Only OBC users can see OBC products.
 */
add_hook('ClientAreaPageCart', 1, function ($vars) {
    if (!empty($_REQUEST["a"]) && $_REQUEST["a"] == "complete") {
        $order = localAPI("GetOrders", ["id" => $vars["orderid"]])["orders"]["order"][0];
        $product = explode(" - ", $order["lineitems"]["lineitem"][0]["product"])[0];
        header("Location: index.php?m=eazybackup&a=complete&product=" . strtolower($product));
        exit();
    }


    $obcgroupid = Capsule::table("tblclientgroups")->where("groupname", "OBC")->first()->id;
    if ($obcgroupid !== $vars["clientsdetails"]["groupid"]) {
        $groupname = Capsule::table("tblproductgroups")->find($_GET["gid"])->name;

        if ($groupname == "OBC") {
            header("Location: " . $vars["systemurl"] . "cart.php");
            exit();
        }

        if ($vars["secondarySidebar"]->getChild('Categories')) $vars["secondarySidebar"]->getChild('Categories')->removeChild('OBC');
    }
});

/**
 * Add alerts to the Client Area.
 */
add_hook('ClientAlert', 1, function ($client) {
    $type = $_REQUEST["msg"] ?? "";

    if ($type == "ok") {
        return new \WHMCS\User\Alert(
            "The action was completed successfully",
            'success'
        );
    }
});

/**
 * Client Area Details page.
 */
add_hook('ClientAreaPageProductDetails', 1, function ($vars) {
    $type = $_REQUEST["msg"] ?? "";

    if ($type == "ok") {
        $vars["modulecustombuttonresult"] = "success";
    }

    return $vars;
});
/**
 * Hook into the “My Services” page (clientareaproducts.tpl) and
 * enrich each $service with:
 *   • TotalSizeBytes (raw bytes for sorting)
 *   • TotalSize      (human-readable string)
 *   • …plus your existing TotalStorage, devicecounting, etc.
 */
add_hook('ClientAreaPageProductsServices', 1, function (array $vars) {
    try {
        foreach ($vars['services'] as $key => $serviceData) {
            // === NEW: Total Size of Protected Items ===
            $username = $serviceData['username'] ?? '';
            if ($username) {
                $tot = comet_getUserTotalSize($username);
                $vars['services'][$key]['TotalSizeBytes'] = $tot['bytes'];
                $vars['services'][$key]['TotalSize']      = $tot['human'];
            }

            // === EXISTING LOGIC BELOW ===

            // 1) Get packageid for this service
            $serviceId = $serviceData['id'];
            $pid = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->value('packageid');

            // 2) Load Comet server connection details
            $serverDetail = comet_ProductParams($pid);
            $params = [
                'serverhttpprefix' => $serverDetail['serverhttpprefix'],
                'serverhostname'   => $serverDetail['serverhostname'],
                'serverusername'   => $serverDetail['serverusername'],
                'serverpassword'   => $serverDetail['serverpassword'],
                'username'         => $username,
            ];
            if (empty($params['username'])) {
                continue;
            }

            // 3) Fetch the Comet user object
            $user = comet_User($params);

            // 4) Microsoft 365 user count
            $vars['services'][$key]['MicrosoftAccountCount'] =
                MicrosoftAccountCount($user);

            // 5) TotalStorage (S3 & eazyBackup vaults)
            $allowedTypes = [1000, 1003];
            $combinedBytes = 0;
            foreach ((array)$user->Destinations as $destination) {
                if (in_array((int)$destination->DestinationType, $allowedTypes, true)) {
                    $combinedBytes +=
                        $destination->Statistics->ClientProvidedSize->Size
                        ?? 0;
                }
            }
            $vars['services'][$key]['TotalStorage'] =
                comet_HumanFileSize($combinedBytes, 2);

            // 6) Suspension flag
            $vars['services'][$key]['IsSuspended'] = $user->IsSuspended;

            // 7) (Example) Active device list fetch
            $activeDevices = [];
            foreach (comet_Server($params)->AdminDispatcherListActive() as $conn) {
                $activeDevices[$conn->DeviceID] = true;
            }

            // 8) Device count
            $vars['services'][$key]['devicecounting'] =
                count((array)$user->Devices);

            // 9) Quota total size
            $vars['services'][$key]['quota_totalsize'] =
                comet_HumanFileSize($user->AllProtectedItemsQuotaBytes ?? 0, 2);
        }

        return $vars;
    } catch (\Exception $e) {
        // WHMCS will display the exception message if an error occurs
        return $e->getMessage();
    }
});

/**
 * Service Cancellation requests page.
 */
add_hook('ClientAreaPageCancellation', 1, function ($vars) {
    // Perform hook code here...

    $serviceId = $vars['id'];
    $username = Capsule::table("tblhosting")->where(["id" => $serviceId])->value('username');
    if (!empty($username)) {

        $vars['username'] = $username;

        return $vars;
    }
    return $vars;
});
