<?php

require_once __DIR__ . "/functions.php";

use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;

add_hook("EmailPreSend", 1, function ($vars) {
    $email_template_name = $vars['messagename'];
    $relid = $vars['relid'];
    $merge_fields = [];

    // Checking for certain template name, if so - this is our case
    if ($email_template_name == "Invoice Created" || $email_template_name == "Invoice Payment Confirmation") {
        // Getting total of the invoice
        $result = Capsule::table('tblinvoices')->where('id', $relid)->value('total');
        
        // If it is equal to '0.00' we disable email sending
        if ($result == '0.00') {
            $merge_fields['abortsend'] = true;
        }
    }
    return $merge_fields;
});

/**
 * Legacy: Hiding domain permissions on Contacts/Sub-Accounts page.
 * Disabled for custom client-area theme/nav; keep hook returning unchanged vars.
 */
add_hook('ClientAreaPageContacts', 1, function ($vars) {
    return $vars; // no-op
});

/**
 * Adds additional service information to submit ticket page.
 */
add_hook('ClientAreaPageSubmitTicket', 1, function ($vars) {
    $clientsproducts = localAPI('GetClientsProducts', ['clientid' => $vars['client']->id])['products']['product'];
    $relatedservices = [];

    foreach ($clientsproducts as $product) {
        $username = empty($product['domain']) ? $product['username'] : $product['domain'];
        $relatedservices[$product['groupname']][] = [
            'id' => 'S' . $product['id'],
            'name' => $product['name'],
            'username' => $username,
            'status' => $product['status'],
        ];
    }

    $vars['relatedservices'] = $relatedservices;
    return $vars;
});

/**
 * Helper: build a versioned <script> or <link> tag for a module asset.
 * $relPath should start with /modules/...
 */
function eazybackup_asset_tag(string $relPath, string $type = 'script'): string
{
    // Absolute base URL (handles http/https + path)
    $webRoot = rtrim(Setting::getValue('SystemURL'), '/');
    // Filesystem path so we can cache-bust with filemtime
    $fsPath  = rtrim(ROOTDIR, '/') . $relPath;
    $ver     = is_readable($fsPath) ? (int) @filemtime($fsPath) : time();

    if ($type === 'style') {
        return sprintf('<link rel="stylesheet" href="%s%s?v=%d">', $webRoot, $relPath, $ver);
    }
    // default: script
    return sprintf('<script defer src="%s%s?v=%d"></script>', $webRoot, $relPath, $ver);
}

/**
 * Asset injection for the eazybackup addon.
 * Loads the email reporting component script where needed.
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    // Only when we are viewing the eazybackup addon pages
    if (!isset($_GET['m']) || $_GET['m'] !== 'eazybackup') {
        return '';
    }    
    $action   = $_GET['a'] ?? '';
    $allowedA = ['user-profile']; // e.g. add 'protected-items', 'storage-vaults' as needed
    if ($action && !in_array($action, $allowedA, true)) {
        return '';
    }
    $tags = [];    
    $tags[] = eazybackup_asset_tag('/modules/addons/eazybackup/assets/js/email-reports.js', 'script');
    return implode("\n", $tags);
});

/**
 * Provide branding-aware download variables for the theme header (flyout + modals)
 * Exposes: {$eb_brand_download.base}, {$eb_brand_download.base_urlenc}, {$eb_brand_download.productName}, {$eb_brand_download.accent}, {$eb_brand_download.isBranded}
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    try {
        $clientId = (int)($_SESSION['uid'] ?? 0);
        $out = [
            'base' => 'https://panel.obcbackup.com/',
            'base_urlenc' => rawurlencode('https://panel.obcbackup.com/'),
            'productName' => 'OBC Branded Client',
            'accent' => '#4f46e5', // indigo-600
            'isBranded' => 0,
        ];
        if ($clientId > 0) {
            $row = Capsule::table('eb_whitelabel_tenants')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->orderBy('updated_at', 'desc')
                ->first();
            if ($row) {
                $brand = json_decode((string)($row->brand_json ?? '{}'), true) ?: [];
                $product = (string)($brand['ProductName'] ?? $brand['BrandName'] ?? '');
                $accent = (string)($brand['AccentColor'] ?? '#4f46e5');
                $host = '';
                $cd = (string)($row->custom_domain ?? '');
                $cdStatus = (string)($row->custom_domain_status ?? '');
                if ($cd !== '' && in_array($cdStatus, ['verified','org_updated','cert_ok','dns_ok'], true)) { $host = $cd; }
                if ($host === '') { $host = (string)$row->fqdn; }
                if ($host !== '') {
                    $base = 'https://' . $host . '/';
                    $out['base'] = $base;
                    $out['base_urlenc'] = rawurlencode($base);
                }
                if ($product !== '') { $out['productName'] = $product; }
                if ($accent !== '') { $out['accent'] = $accent; }
                $out['isBranded'] = 1;
            }
        }
        return ['eb_brand_download' => $out];
    } catch (\Throwable $_) { return []; }
});

// Partner Hub navigation visibility flags for the client-area sidebar
add_hook('ClientAreaPage', 1, function ($vars) {
    try {
        $get = function (string $setting, bool $default = true): bool {
            try {
                $val = Capsule::table('tbladdonmodules')
                    ->where('module', 'eazybackup')
                    ->where('setting', $setting)
                    ->value('value');
            } catch (\Throwable $e) { $val = null; }
            // If row is missing entirely, fall back to default; otherwise evaluate strictly
            if ($val === null) { return $default; }
            $s = strtolower(trim((string)$val));
            return in_array($s, ['1','on','yes','true'], true);
        };
        return [
            'eb_partner_hub_enabled' => $get('partnerhub_nav_enabled', true),
            'eb_ph_show_overview'    => $get('partnerhub_show_overview', true),
            'eb_ph_show_clients'     => $get('partnerhub_show_clients', true),
            'eb_ph_show_catalog'     => $get('partnerhub_show_catalog', true),
            'eb_ph_show_billing'     => $get('partnerhub_show_billing', true),
            'eb_ph_show_money'       => $get('partnerhub_show_money', true),
            'eb_ph_show_stripe'      => $get('partnerhub_show_stripe', true),
            'eb_ph_show_settings'    => $get('partnerhub_show_settings', true),
        ];
    } catch (\Throwable $e) {
        return [];
    }
});




/**
 * Client-area TOS gating (entire portal) when active version requires acceptance.
 */
add_hook('ClientAreaPage', 2, function ($vars) {
    try {
        // Only after login
        $clientId = (int)($_SESSION['uid'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        // Whitelist acceptance routes and login-related pages to avoid loops
        $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $qs = (string)($_SERVER['QUERY_STRING'] ?? '');
        $isModuleTos = (isset($_GET['m']) && $_GET['m'] === 'eazybackup' && isset($_GET['a']) && in_array($_GET['a'], ['tos-block','tos-accept','tos-view'], true));
        $isLoginOrLogout = (strpos($reqUri, 'logout.php') !== false) || (strpos($reqUri, 'pwreset') !== false);
        if ($isModuleTos || $isLoginOrLogout) {
            return;
        }

        // Read active TOS that requires acceptance
        $active = Capsule::table('eb_tos_versions')
            ->where('is_active', 1)
            ->orderBy('published_at', 'desc')
            ->first();
        if (!$active || (int)$active->require_acceptance !== 1) {
            return; // nothing enforced
        }
        $version = (string)$active->version;

        // Determine identity for per-user acceptance (contact if present)
        $contactId = (int)($_SESSION['cid'] ?? 0);

        // Check per-user acceptance
        $q = Capsule::table('eb_tos_user_acceptances')
            ->where('client_id', $clientId)
            ->where('tos_version', $version);
        if ($contactId > 0) {
            $q->where('contact_id', $contactId);
        } else {
            $q->whereNull('user_id')->whereNull('contact_id');
        }
        $userAccepted = (bool)$q->exists();

        if ($userAccepted) {
            return;
        }

        // Redirect to TOS block page with return_to
        $returnTo = $reqUri !== '' ? $reqUri : ('clientarea.php' . ($qs !== '' ? ('?' . $qs) : ''));
        $target = 'index.php?m=eazybackup&a=tos-block&return_to=' . rawurlencode($returnTo);
        header('Location: ' . $target);
        exit;
    } catch (\Throwable $e) {
        // On error, fail open to avoid lockouts
        return;
    }
});

// Password onboarding gate was originally implemented as a ClientAreaPage hook.
// We now rely on the tos-accept handler to redirect flagged clients directly
// to the password-onboarding route after TOS acceptance, to avoid redirect
// loops and over-gating the entire client area.

// Admin dashboard widget: Comet WebSocket Workers status (read-only)
add_hook('AdminHomeWidgets', 1, function () {
    return new class extends \WHMCS\Module\AbstractWidget {
        protected $title = 'Comet WebSocket Workers';
        protected $description = 'Read-only status of systemd-managed Comet WebSocket workers';
        protected $weight = 150;
        protected $columns = 1;
        protected $cache = false; // we handle caching server-side

        public function getData()
        {
            return [];
        }

        public function generateOutput($data)
        {
            $html = '';
            $html .= '<div class="panel panel-default">';
            $html .= '<div class="panel-heading">Comet WebSocket Workers';
            $html .= '<div class="pull-right">';
            $html .= '<span id="eb-summary" class="label label-default">-- / --</span>';
            $html .= '<label style="margin-left:8px"><input type="checkbox" id="eb-auto"/> Auto-refresh</label>';
            $html .= '<button id="eb-refresh" class="btn btn-default btn-xs" style="margin-left:8px">Refresh</button>';
            $html .= '</div></div>';
            $html .= '<div class="panel-body">';
            $html .= '<table class="table table-condensed" id="eb-workers">';
            $html .= '<thead><tr><th style="width:18px"></th><th>Worker</th><th>Unit</th><th>PID</th><th>Uptime</th><th>Started</th><th>Last Exit</th><th>Restarts</th></tr></thead>';
            $html .= '<tbody></tbody></table>';
            $html .= '<div class="text-muted" id="eb-checked"></div>';
            $html .= '<div class="text-muted" id="eb-server-time"></div>';
            $html .= '</div></div>';
            $html .= '<style>.eb-dot{display:inline-block;width:8px;height:8px;border-radius:50%}.eb-green{background:#22c55e}.eb-yellow{background:#eab308}.eb-red{background:#ef4444}.eb-gray{background:#9ca3af}</style>';

            $script = <<<'JS'
<script>(function(){
var api="addonmodules.php?module=eazybackup&action=admin-workers&op=list";
var tbody=document.querySelector("#eb-workers tbody");
var summary=document.getElementById("eb-summary");
var checked=document.getElementById("eb-checked");
var serverTimeEl=document.getElementById("eb-server-time");
var auto=document.getElementById("eb-auto");
var btn=document.getElementById("eb-refresh");
var timer=null;
function fmtRel(seconds){seconds=Math.max(0,parseInt(seconds||0,10));var h=Math.floor(seconds/3600);var m=Math.floor((seconds%3600)/60);var s=seconds%60;return (h>0?(h+"h "):"")+(m>0?(m+"m "):"")+(h===0? (s+"s"):"").trim();}
function dot(c){return "<span class=\"eb-dot eb-"+c+"\"></span>";}
function esc(s){return String(s==null?"":s).replace(/[&<>]/g,function(ch){return {"&":"&amp;","<":"&lt;",">":"&gt;"}[ch];});}
function load(){
 fetch(api,{credentials:"same-origin"}).then(function(r){return r.text().then(function(t){ try { return JSON.parse(t);} catch(e){ console.error('Workers API returned non-JSON. HTTP', r.status, 'Body:', t); throw e; } });}).then(function(j){
  var rows=j.workers||[]; var run=0; var total=rows.length;
  tbody.innerHTML="";
  rows.forEach(function(w){
    if(w.color==="green"){run++;}
    var sinceLocal = (w.sinceEpochMs && w.sinceEpochMs>0)? new Date(w.sinceEpochMs).toLocaleString():"";
    var lastExit = (w.lastExitCode!=null || w.lastExitStatus!=null)? ("code="+(w.lastExitCode==null?"":w.lastExitCode)+" status="+(w.lastExitStatus==null?"":w.lastExitStatus)):"";
    var tr="<tr>"
          +"<td>"+dot(w.color||"gray")+"</td>"
          +"<td>"+esc(w.label||"")+"</td>"
          +"<td><code>"+esc(w.unit||"")+"</code></td>"
          +"<td>"+esc(w.mainPid||0)+"</td>"
          +"<td>"+esc(fmtRel(w.uptimeSeconds||0))+"</td>"
          +"<td>"+esc(sinceLocal)+"</td>"
          +"<td>"+esc(lastExit)+"</td>"
          +"<td>"+esc(w.restartCount==null?"":w.restartCount)+"</td>"
        +"</tr>";
    tbody.insertAdjacentHTML("beforeend", tr);
  });
  summary.textContent=run+" / "+total+" running";
  var ck = (j.checkedAtIso||""); var cached = j.cached?" (cached)":"";
  checked.textContent = ck? ("Last checked: "+ck+cached):"";
  serverTimeEl.textContent = j.serverTimeIso? ("Server time: "+j.serverTimeIso):"";
  if((rows.length===0) && j.error){ tbody.innerHTML = "<tr><td colspan=8 class=\"text-muted\">"+esc(j.error)+"</td></tr>"; }
 }).catch(function(){ tbody.innerHTML = "<tr><td colspan=8 class=\"text-muted\">Cannot read status</td></tr>"; });
}
btn.addEventListener("click", function(e){ e.preventDefault(); load(); });
auto.addEventListener("change", function(){ if(timer){ clearInterval(timer); timer=null;} if(this.checked){ timer=setInterval(load,30000);} });
load();
})();</script>
JS;
            $html .= $script;

            return $html;
        }
    };
});


