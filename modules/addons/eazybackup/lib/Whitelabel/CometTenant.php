<?php

namespace EazyBackup\Whitelabel;

use WHMCS\Database\Capsule;

class CometTenant
{
    private array $cfg;

    public function __construct(array $vars)
    {
        $this->cfg = $vars;
    }

    public function createOrUpdateOrg(string $name, string $fqdn): array
    {
        try {
            $rootUrl  = '';
            $admin    = '';
            $password = '';
            try {
                $sid = (int)($this->cfg['comet_server_id'] ?? 0);
                if ($sid > 0 && class_exists('WHMCS\\Database\\Capsule')) {
                    $srv = Capsule::table('tblservers')->where('id', $sid)->first();
                    if ($srv) {
                        $host = (string)($srv->hostname ?? '');
                        $secureRaw = $srv->secure ?? 1;
                        $isSecure = is_numeric($secureRaw)
                            ? ((int)$secureRaw === 1)
                            : in_array(strtolower((string)$secureRaw), ['1','on','true','yes'], true);
                        $port = (string)($srv->port ?? '');
                        $scheme = $isSecure ? 'https' : 'http';
                        $rootUrl = $scheme . '://' . $host;
                        if ($port !== '' && !in_array((int)$port, [$isSecure ? 443 : 80], true)) { $rootUrl .= ':' . $port; }
                        $admin = (string)($srv->username ?? '');
                        if (function_exists('decrypt')) {
                            $password = (string)decrypt((string)($srv->password ?? ''));
                        } else {
                            $password = (string)($srv->password ?? '');
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->log('comet server resolve error: ' . $e->getMessage());
            }
            if ($rootUrl === '' || $admin === '' || $password === '') {
                $rootUrl  = rtrim((string)($this->cfg['comet_root_url'] ?? ''), '/');
                $admin    = (string)($this->cfg['comet_root_admin'] ?? '');
                $password = (string)($this->cfg['comet_root_password'] ?? '');
                $this->log('comet: using legacy root credentials (deprecated)');
            }
            if ($rootUrl === '' || $admin === '' || $password === '') {
                $this->log('comet: missing credentials');
            }

            $server = $this->getServerClient($rootUrl, $admin, $password);
            if ($server) {
                $this->log('comet client=Comet\\Server url=' . $this->maskUrl($rootUrl));
                $org = new \Comet\Organization();
                $org->Name = $name;
                $org->IsSuspended = false;
                $org->Hosts = [$fqdn];
                $org->Branding = \Comet\BrandingOptions::createFromArray([
                    'DefaultLoginServerURL' => 'https://' . $fqdn . '/',
                ]);
                $resp = $server->AdminOrganizationSet(null, $org);
                $oid = ($resp instanceof \Comet\OrganizationResponse) ? (string)$resp->ID : '';
                return ['ok' => ($oid !== ''), 'org_id' => $oid];
            }
            if ($rootUrl !== '' && class_exists('\\Comet\\API\\CometClient')) {
                $this->log('comet client=CometClient url=' . $this->maskUrl($rootUrl));
                try {
                    $api = new \Comet\API\CometClient($rootUrl, $admin, $password);
                    $org = \Comet\Organization::createFromArray([
                        'Name' => $name,
                        'IsSuspended' => false,
                        'Hosts' => [$fqdn],
                        'Branding' => ['DefaultLoginServerURL' => 'https://' . $fqdn . '/'],
                    ]);
                    $resp = $api->AdminOrganizationSet(null, $org);
                    $oid = (is_object($resp) && property_exists($resp, 'ID')) ? (string)$resp->ID : '';
                    if ($oid !== '') { return ['ok' => true, 'org_id' => $oid]; }
                } catch (\Throwable $__) {}
                return ['ok' => false, 'org_id' => ''];
            }
            if ($rootUrl !== '' && class_exists('CometAPI')) {
                $this->log('comet client=CometAPI url=' . $this->maskUrl($rootUrl));
                try {
                    $api = new \CometAPI($rootUrl, $admin, $password);
                    $payload = [
                        'Name' => $name,
                        'IsSuspended' => false,
                        'Hosts' => [$fqdn],
                        'Branding' => ['DefaultLoginServerURL' => 'https://' . $fqdn . '/'],
                    ];
                    $res = $api->AdminOrganizationSet($payload);
                    $oid = (is_array($res) && isset($res['OrganizationID'])) ? (string)$res['OrganizationID'] : '';
                    if ($oid !== '') { return ['ok' => true, 'org_id' => $oid]; }
                } catch (\Throwable $__) {}
                return ['ok' => false, 'org_id' => ''];
            }
        } catch (\Throwable $e) {
            $this->log('comet org error: ' . $e->getMessage());
            return ['ok' => false, 'org_id' => ''];
        }
        return ['ok' => false, 'org_id' => ''];
    }

    public function createAdminUser(string $orgId, string $username, string $password): array
    {
        try {
            $creds = $this->resolveCreds();
            if (!$creds) { throw new \RuntimeException('missing comet credentials'); }
            $server = $this->getServerClient($creds['url'], $creds['user'], $creds['pass']);
            if ($server) {
                $this->log('comet client=Comet\\Server url=' . $this->maskUrl($creds['url']));
                $server->AdminAdminUserNew($username, $password, $orgId);
                return ['ok' => true];
            } elseif (class_exists('\\Comet\\API\\CometClient')) {
                $this->log('comet client=CometClient url=' . $this->maskUrl($creds['url']));
                try {
                    $api = new \Comet\API\CometClient($creds['url'], $creds['user'], $creds['pass']);
                    $api->AdminAdminUserNew($username, $password, $orgId);
                    return ['ok' => true];
                } catch (\Throwable $__) {}
            } elseif (class_exists('CometAPI')) {
                $this->log('comet client=CometAPI url=' . $this->maskUrl($creds['url']));
                try {
                    $api = new \CometAPI($creds['url'], $creds['user'], $creds['pass']);
                    $api->AdminAdminUserNew($username, $password, $orgId);
                    return ['ok' => true];
                } catch (\Throwable $__) {}
            }
        } catch (\Throwable $e) {
            $this->log('comet admin error: ' . $e->getMessage());
        }
        return ['ok' => false];
    }

    /**
     * Clone a template policy for an organization, and assign to the admin via AllowedUserPolicies.
     * Idempotent: if a policy with the deterministic ID exists, it will be updated.
     */
    public function ensurePolicyForAdmin(string $orgId, string $adminUsername, string $templatePolicyId): bool
    {
        try {
            $creds = $this->resolveCreds(); if (!$creds) { return false; }
            $server = $this->getServerClient($creds['url'], $creds['user'], $creds['pass']);
            if (!$server) { return false; }
            $this->log('comet policy: cloning template=' . $templatePolicyId . ' for org=' . $orgId . ' user=' . $adminUsername);

            // If prior config write occurred, wait for Comet to be responsive
            $this->waitForCometReady($server, 60);

            // Load template policy
            $gp = null;
            for ($__i=0; $__i<3; $__i++) {
                try {
                    $tpl = $server->AdminPoliciesGet($templatePolicyId);
                    $gp = ($tpl && isset($tpl->Policy)) ? $tpl->Policy : null;
                    if ($gp instanceof \Comet\GroupPolicy) { break; }
                } catch (\Throwable $__e) {
                    $this->log('comet policy get retry ' . ($__i+1) . ' error=' . $__e->getMessage());
                }
                try { usleep(250000 * ($__i+1)); } catch (\Throwable $_) {}
            }
            if (!($gp instanceof \Comet\GroupPolicy)) {
                // Fallback: list all and locate ID
                try {
                    $all = $server->AdminPoliciesListFull();
                    if (is_array($all) && isset($all[$templatePolicyId]) && $all[$templatePolicyId] instanceof \Comet\GroupPolicy) {
                        $gp = $all[$templatePolicyId];
                    }
                } catch (\Throwable $__) {}
            }
            if (!($gp instanceof \Comet\GroupPolicy)) { return false; }

            // Create a deterministic new PolicyID based on orgId
            $h = sha1('wl-policy:' . $orgId);
            $newId = substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);

            // Prepare cloned policy for the new org
            $clone = \Comet\GroupPolicy::createFromArray($gp->toArray());
            $clone->OrganizationID = $orgId;
            if (property_exists($clone, 'Description')) { $clone->Description = 'WL cloned policy for ' . $orgId; }

            // Create/update the policy with specific ID
            // Retry AdminPoliciesSet in case of transient 502 (exponential backoff)
            $setOk = false; $delay = 0.5;
            for ($__i=0; $__i<10; $__i++) {
                try { $server->AdminPoliciesSet($newId, $clone, null, null); $setOk=true; break; } catch (\Throwable $__e) {
                    $this->log('comet policies/set try=' . ($__i+1) . ' err=' . $__e->getMessage());
                }
                try { usleep((int)($delay * 1000000)); } catch (\Throwable $_) {}
                $delay = min($delay * 1.6, 3.0);
            }
            if (!$setOk) { return false; }

            // Attach to admin via server config (idempotent)
            $okAttach = $this->attachPolicyToAdmin($server, $orgId, $adminUsername, $newId);
            if ($okAttach) { $this->log('comet policy: assigned new policy id=' . $newId . ' to ' . $adminUsername); }
            return $okAttach;
        } catch (\Throwable $e) {
            $this->log('comet policy error: ' . $e->getMessage());
            return false;
        }
    }

    private function waitForCometReady(\Comet\Server $server, int $maxSeconds = 60): bool
    {
        $deadline = microtime(true) + $maxSeconds; $delay = 0.25;
        do {
            try { $server->AdminMetaServerConfigGet(); return true; } catch (\Throwable $__) {}
            try { usleep((int)($delay * 1000000)); } catch (\Throwable $_) {}
            $delay = min($delay * 1.6, 2.0);
        } while (microtime(true) < $deadline);
        return false;
    }

    /**
     * Attach a cloned policy to a tenant admin (idempotent).
     * Only updates the Permissions block for the matching admin.
     */
    private function attachPolicyToAdmin(\Comet\Server $server, string $orgId, string $adminUsername, string $newPolicyId): bool
    {
        try {
            $cfg = $server->AdminMetaServerConfigGet();
            if (!is_object($cfg) || !isset($cfg->AdminUsers) || !is_array($cfg->AdminUsers)) { return false; }
            $found = false;
            foreach ($cfg->AdminUsers as $idx => $au) {
                $u = is_object($au) ? (string)($au->Username ?? '') : (string)($au['Username'] ?? '');
                $o = is_object($au) ? (string)($au->OrganizationID ?? '') : (string)($au['OrganizationID'] ?? '');
                if ($u === (string)$adminUsername && $o === (string)$orgId) {
                    $found = true;
                    // Preserve existing permissions
                    if (is_object($au)) {
                        if (!isset($au->Permissions) || !is_object($au->Permissions)) { $au->Permissions = new \Comet\AdminUserPermissions(); }
                        $au->Permissions->PreventEditServerSettings = true;
                        $au->Permissions->PreventServerShutdown = true;
                        $cur = is_array($au->Permissions->AllowedUserPolicies ?? null) ? $au->Permissions->AllowedUserPolicies : [];
                        if (!in_array($newPolicyId, $cur, true)) { $cur[] = $newPolicyId; }
                        $au->Permissions->AllowedUserPolicies = array_values(array_unique($cur));
                        $cfg->AdminUsers[$idx] = $au;
                    } else if (is_array($au)) {
                        if (!isset($au['Permissions']) || !is_array($au['Permissions'])) { $au['Permissions'] = []; }
                        $au['Permissions']['PreventEditServerSettings'] = true;
                        $au['Permissions']['PreventServerShutdown'] = true;
                        $cur = isset($au['Permissions']['AllowedUserPolicies']) && is_array($au['Permissions']['AllowedUserPolicies']) ? $au['Permissions']['AllowedUserPolicies'] : [];
                        if (!in_array($newPolicyId, $cur, true)) { $cur[] = $newPolicyId; }
                        $au['Permissions']['AllowedUserPolicies'] = array_values(array_unique($cur));
                        $cfg->AdminUsers[$idx] = $au;
                    }
                    break;
                }
            }
            if (!$found) { throw new \RuntimeException('Admin not found in server config for attach.'); }
            // Single config write
            $server->AdminMetaServerConfigSet($cfg);
            $this->waitForCometReady($server, 60);

            // Verify
            $after = $server->AdminMetaServerConfigGet();
            if (!is_object($after) || !isset($after->AdminUsers) || !is_array($after->AdminUsers)) { return false; }
            foreach ($after->AdminUsers as $au2) {
                $u2 = is_object($au2) ? (string)($au2->Username ?? '') : (string)($au2['Username'] ?? '');
                $o2 = is_object($au2) ? (string)($au2->OrganizationID ?? '') : (string)($au2['OrganizationID'] ?? '');
                if ($u2 === (string)$adminUsername && $o2 === (string)$orgId) {
                    if (is_object($au2)) {
                        $perms = $au2->Permissions ?? new \Comet\AdminUserPermissions();
                        return in_array($newPolicyId, $perms->AllowedUserPolicies ?? [], true);
                    } else if (is_array($au2)) {
                        $perms = isset($au2['Permissions']) && is_array($au2['Permissions']) ? $au2['Permissions'] : [];
                        $cur = isset($perms['AllowedUserPolicies']) && is_array($perms['AllowedUserPolicies']) ? $perms['AllowedUserPolicies'] : [];
                        return in_array($newPolicyId, $cur, true);
                    }
                }
            }
            return false;
        } catch (\Throwable $e) {
            $this->log('comet attachPolicyToAdmin error: ' . $e->getMessage());
            return false;
        }
    }

    public function applyBranding(string $orgId, array $branding): bool
    {
        try {
            $creds = $this->resolveCreds(); if (!$creds) { return false; }
            $server = $this->getServerClient($creds['url'], $creds['user'], $creds['pass']);
            if ($server) {
                $this->log('comet client=Comet\\Server url=' . $this->maskUrl($creds['url']));

                $cur = null;
                for ($__i=0; $__i<3; $__i++) {
                    try { $cur = $this->loadOrgOrThrow($server, $orgId); break; } catch (\Throwable $__e) {
                        $this->log('comet branding load org retry ' . ($__i+1) . ' error=' . $__e->getMessage());
                        try { usleep(250000 * ($__i+1)); } catch (\Throwable $_) {}
                    }
                }
                if (!$cur) {
                    // Fallback: construct minimal org without listing
                    $fqdn = $this->lookupFqdnByOrgId($orgId);
                    if ($fqdn === '') { return false; }
                    $cur = new \Comet\Organization();
                    $cur->Name = $fqdn;
                    $cur->IsSuspended = false;
                    $cur->Hosts = [$fqdn];
                }
                $branding = $this->rewriteBrandingAssetsToResources($server, $branding);
                $this->log('comet branding upload: posting keys=' . json_encode(array_keys($branding)));

                // Ensure CompanyName and CloudStorageName are set
                if (!isset($branding['CompanyName'])) { $branding['CompanyName'] = (string)($branding['ProductName'] ?? ''); }
                if (!isset($branding['CloudStorageName'])) { $branding['CloudStorageName'] = (string)($branding['ProductName'] ?? ''); }
                $cur->Branding = \Comet\BrandingOptions::createFromArray($branding);
                if ((string)($cur->Branding->DefaultLoginServerURL ?? '') === '' && is_array($cur->Hosts) && count($cur->Hosts) > 0) {
                    $cur->Branding->DefaultLoginServerURL = 'https://' . (string)$cur->Hosts[0] . '/';
                }
                // Enable SoftwareBuildRole minimally
                $sbro = new \Comet\SoftwareBuildRoleOptions();
                $sbro->RoleEnabled = true;
                $sbro->AllowUnauthenticatedDownloads = false;
                $sbro->MaxBuilders = 0;
                $cur->SoftwareBuildRole = $sbro;
                $server->AdminOrganizationSet($orgId, $cur);
                // Round-trip verify branding values
                try {
                    $ver = $this->loadOrgOrThrow($server, $orgId);
                    $posted = isset($cur->Branding) ? (array)$cur->Branding->toArray() : [];
                    $stored = isset($ver->Branding) ? (array)$ver->Branding->toArray() : [];
                    $this->log('comet branding verify: posted_keys=' . json_encode(array_keys($posted)) . ' stored_keys=' . json_encode(array_keys($stored)));
                    $checkKeys = ['LogoImage','Favicon','PathHeaderImage','PathAppIconImage','PathTilePng','PathIcoFile','PathIcnsFile','PathMenuBarIcnsFile','PathEulaRtf','TopColor','TileBackgroundColor','CompanyName','ProductName'];
                    $result = [];
                    foreach ($checkKeys as $ck) {
                        $v = isset($stored[$ck]) ? (string)$stored[$ck] : '';
                        $result[$ck] = ($v !== '' && strpos($v, 'resource://') === 0) ? 'resource' : ($v !== '' ? 'value' : 'empty');
                    }
                    $this->log('comet branding verify summary: ' . json_encode($result));
                } catch (\Throwable $__) {}
                return true;
            }

            // Fallback: Comet\API\CometClient
            if (class_exists('\\Comet\\API\\CometClient')) {
                $this->log('comet client=CometClient url=' . $this->maskUrl($creds['url']));
                try {
                    $api = new \Comet\API\CometClient($creds['url'], $creds['user'], $creds['pass']);
                    $branding2 = $this->rewriteBrandingAssetsToResourcesViaClient($api, $branding);
                    $this->log('comet branding upload(Client): posting keys=' . json_encode(array_keys($branding2)));

                    // Load existing org then merge branding
                    $cur = null;
                    for ($__i=0; $__i<3; $__i++) {
                        try { $cur = $this->loadOrgViaClient($api, $orgId); if ($cur) break; } catch (\Throwable $__e) {
                            $this->log('comet branding load org (Client) retry ' . ($__i+1) . ' error=' . $__e->getMessage());
                        }
                        try { usleep(250000 * ($__i+1)); } catch (\Throwable $_) {}
                    }
                    if (!$cur) {
                        $fqdn = $this->lookupFqdnByOrgId($orgId);
                        if ($fqdn === '') { throw new \RuntimeException('Organization not found: ' . $orgId); }
                        $cur = new \Comet\Organization();
                        $cur->Name = $fqdn; $cur->IsSuspended = false; $cur->Hosts = [$fqdn];
                    }
                    if (!($cur instanceof \Comet\Organization)) { $cur = \Comet\Organization::createFromArray((array)$cur); }
                    if (!isset($branding2['CompanyName'])) { $branding2['CompanyName'] = (string)($branding2['ProductName'] ?? ''); }
                    if (!isset($branding2['CloudStorageName'])) { $branding2['CloudStorageName'] = (string)($branding2['ProductName'] ?? ''); }
                    $cur->Branding = \Comet\BrandingOptions::createFromArray($branding2);
                    if ((string)($cur->Branding->DefaultLoginServerURL ?? '') === '' && is_array($cur->Hosts) && count($cur->Hosts) > 0) {
                        $cur->Branding->DefaultLoginServerURL = 'https://' . (string)$cur->Hosts[0] . '/';
                    }
                    $sbro = new \Comet\SoftwareBuildRoleOptions(); $sbro->RoleEnabled = true; $sbro->AllowUnauthenticatedDownloads = false; $sbro->MaxBuilders = 0; $cur->SoftwareBuildRole = $sbro;
                    $api->AdminOrganizationSet($orgId, $cur);
                    // Round-trip verify branding values
                    try {
                        $ver = $this->loadOrgViaClient($api, $orgId);
                        $stored = [];
                        if ($ver instanceof \Comet\Organization) { $stored = (array)$ver->Branding->toArray(); }
                        else if (is_array($ver) && isset($ver['Branding']) && is_array($ver['Branding'])) { $stored = $ver['Branding']; }
                        $checkKeys = ['LogoImage','Favicon','PathHeaderImage','PathAppIconImage','PathTilePng','PathIcoFile','PathIcnsFile','PathMenuBarIcnsFile','PathEulaRtf','TopColor','TileBackgroundColor','CompanyName','ProductName'];
                        $result = [];
                        foreach ($checkKeys as $ck) {
                            $v = isset($stored[$ck]) ? (string)$stored[$ck] : '';
                            $result[$ck] = ($v !== '' && strpos($v, 'resource://') === 0) ? 'resource' : ($v !== '' ? 'value' : 'empty');
                        }
                        $this->log('comet branding verify (Client): ' . json_encode($result));
                    } catch (\Throwable $__) {}
                    return true;
                } catch (\Throwable $__) {}
            }

            // Legacy CometAPI fallback
            if (class_exists('CometAPI')) {
                $this->log('comet client=CometAPI url=' . $this->maskUrl($creds['url']));
                try {
                    $api = new \CometAPI($creds['url'], $creds['user'], $creds['pass']);
                    $branding2 = $this->rewriteBrandingAssetsToResourcesViaClient($api, $branding);
                    $this->log('comet branding upload(CometAPI): posting keys=' . json_encode(array_keys($branding2)));

                    if (!isset($branding2['CompanyName'])) { $branding2['CompanyName'] = (string)($branding2['ProductName'] ?? ''); }
                    if (!isset($branding2['CloudStorageName'])) { $branding2['CloudStorageName'] = (string)($branding2['ProductName'] ?? ''); }
                    $sbro = ['RoleEnabled' => true, 'AllowUnauthenticatedDownloads' => false, 'MaxBuilders' => 0];
                    $payload = [
                        'OrganizationID' => $orgId,
                        'Branding' => $branding2,
                        'SoftwareBuildRole' => $sbro,
                    ];
                    $api->AdminOrganizationSet($payload);
                    // Round-trip verify branding values
                    try {
                        $ver = $this->loadOrgViaClient($api, $orgId);
                        $stored = is_array($ver) ? (isset($ver['Branding']) ? (array)$ver['Branding'] : []) : [];
                        $checkKeys = ['LogoImage','Favicon','PathHeaderImage','PathAppIconImage','PathTilePng','PathIcoFile','PathIcnsFile','PathMenuBarIcnsFile','PathEulaRtf','TopColor','TileBackgroundColor','CompanyName','ProductName'];
                        $result = [];
                        foreach ($checkKeys as $ck) {
                            $v = isset($stored[$ck]) ? (string)$stored[$ck] : '';
                            $result[$ck] = ($v !== '' && strpos($v, 'resource://') === 0) ? 'resource' : ($v !== '' ? 'value' : 'empty');
                        }
                        $this->log('comet branding verify (CometAPI): ' . json_encode($result));
                    } catch (\Throwable $__) {}
                    return true;
                } catch (\Throwable $__) {}
            }
            return false;
        } catch (\Throwable $e) {
            $this->log('comet branding error: ' . $e->getMessage());
            return false;
        }
    }

    public function applyEmailOptions(string $orgId, array $email): bool
    {
        try {
            if (!empty($email['inherit'])) { return true; }
            $creds = $this->resolveCreds(); if (!$creds) { return false; }
            $server = $this->getServerClient($creds['url'], $creds['user'], $creds['pass']);
            if ($server) {
                $this->log('comet client=Comet\\Server url=' . $this->maskUrl($creds['url']));
                $cur = null;
                for ($__i=0; $__i<3; $__i++) {
                    try { $cur = $this->loadOrgOrThrow($server, $orgId); break; } catch (\Throwable $__e) {
                        $this->log('comet email load org retry ' . ($__i+1) . ' error=' . $__e->getMessage());
                        try { usleep(250000 * ($__i+1)); } catch (\Throwable $_) {}
                    }
                }
                if (!$cur) {
                    $fqdn = $this->lookupFqdnByOrgId($orgId);
                    if ($fqdn === '') { return false; }
                    $cur = new \Comet\Organization(); $cur->Name = $fqdn; $cur->IsSuspended = false; $cur->Hosts = [$fqdn];
                }
                $cur->Email = \Comet\EmailOptions::createFromArray($email);
                $server->AdminOrganizationSet($orgId, $cur);
                return true;
            }
            if (class_exists('\\Comet\\API\\CometClient')) {
                $this->log('comet client=CometClient url=' . $this->maskUrl($creds['url']));
                try {
                    $api = new \Comet\API\CometClient($creds['url'], $creds['user'], $creds['pass']);
                    $cur = null;
                    for ($__i=0; $__i<3; $__i++) {
                        try { $cur = $this->loadOrgViaClient($api, $orgId); if ($cur) break; } catch (\Throwable $__e) {
                            $this->log('comet email load org (Client) retry ' . ($__i+1) . ' error=' . $__e->getMessage());
                        }
                        try { usleep(250000 * ($__i+1)); } catch (\Throwable $_) {}
                    }
                    if (!$cur) {
                        $fqdn = $this->lookupFqdnByOrgId($orgId);
                        if ($fqdn === '') { throw new \RuntimeException('Organization not found: ' . $orgId); }
                        $cur = new \Comet\Organization(); $cur->Name = $fqdn; $cur->IsSuspended = false; $cur->Hosts = [$fqdn];
                    }
                    if (!($cur instanceof \Comet\Organization)) { $cur = \Comet\Organization::createFromArray((array)$cur); }
                    $cur->Email = \Comet\EmailOptions::createFromArray($email);
                    $api->AdminOrganizationSet($orgId, $cur);
                    return true;
                } catch (\Throwable $__) {}
            }
            if (class_exists('CometAPI')) {
                $this->log('comet client=CometAPI url=' . $this->maskUrl($creds['url']));
                try {
                    $api = new \CometAPI($creds['url'], $creds['user'], $creds['pass']);
                    $payload = [
                        'OrganizationID' => $orgId,
                        'Email' => $email,
                    ];
                    $api->AdminOrganizationSet($payload);
                    return true;
                } catch (\Throwable $__) {}
            }
            return false;
        } catch (\Throwable $e) {
            $this->log('comet email error: ' . $e->getMessage());
            return false;
        }
    }

    public function configureStorageTemplate(string $orgId, array $tpl): bool
    {
        try {
            $creds = $this->resolveCreds(); if (!$creds) { return false; }
            $server = $this->getServerClient($creds['url'], $creds['user'], $creds['pass']);
            if (!$server) { return false; }
            $this->log('comet client=Comet\\Server url=' . $this->maskUrl($creds['url']));

            // Build deterministic template ID
            $hid = sha1('wl-storage:' . $orgId);
            $tplId = substr($hid, 0, 8) . '-' . substr($hid, 8, 4) . '-' . substr($hid, 12, 4) . '-' . substr($hid, 16, 4) . '-' . substr($hid, 20, 12);

            // Load current org (fallback to minimal org if list fails)
            $org = null; $fqdn = $this->lookupFqdnByOrgId($orgId);
            try { $org = $this->loadOrgOrThrow($server, $orgId); } catch (\Throwable $e) { $this->log('comet storage load org: ' . $e->getMessage()); }
            if (!$org) {
                if ($fqdn === '') { return false; }
                $org = new \Comet\Organization(); $org->Name = $fqdn; $org->IsSuspended = false; $org->Hosts = [$fqdn];
            }

            // Prepare RemoteStorageOption
            $defaultUrl = is_array($org->Hosts) && count($org->Hosts) > 0 ? ('https://' . (string)$org->Hosts[0] . '/') : '';
            $adminRow = $this->findAdminInConfig($server, $orgId);
            $storageUser = is_array($adminRow) ? (string)($adminRow['Username'] ?? '') : (string)($adminRow->Username ?? '');
            $storagePass = $this->getAdminPlainPasswordForOrg($orgId);
            $desc = ($org->Branding && isset($org->Branding->ProductName) && $org->Branding->ProductName !== '') ? (string)$org->Branding->ProductName . ' Cloud Storage' : ($fqdn . ' Cloud Storage');

            $opt = new \Comet\RemoteStorageOption();
            $opt->Type = 'comet';
            $opt->Description = $desc;
            $opt->RemoteAddress = $defaultUrl !== '' ? $defaultUrl : $fqdn;
            $opt->Username = $storageUser;
            $opt->Password = $storagePass;
            $opt->RebrandStorage = true;
            $opt->ID = $tplId;
            $opt->Default = true;

            // Upsert by ID
            $remote = is_array($org->RemoteStorage) ? $org->RemoteStorage : [];
            $found = false;
            foreach ($remote as $idx => $rs) {
                $rid = is_object($rs) ? (string)($rs->ID ?? '') : (string)($rs['ID'] ?? '');
                if ($rid === $tplId) { $remote[$idx] = $opt; $found = true; break; }
            }
            if (!$found) { $remote[] = $opt; }
            $org->RemoteStorage = $remote;

            // Write once
            $server->AdminOrganizationSet($orgId, $org);

            // Verify visibility (optional)
            try { $providers = $server->AdminRequestStorageVaultProviders($orgId); $this->log('comet storage providers count=' . count((array)$providers)); } catch (\Throwable $__) {}

            // Optional: connectivity test
            try { $server->AdminMetaRemoteStorageVaultTest($opt); } catch (\Throwable $e) { $this->log('comet storage test error: ' . $e->getMessage()); }

            return true;
        } catch (\Throwable $e) {
            $this->log('comet storage error: ' . $e->getMessage());
        }
        return false;
    }

    private function uploadResource(\Comet\Server $server, string $absPath): ?string
    {
        if ($absPath === '' || !@is_readable($absPath)) { return null; }
        try {
            $bytes = @file_get_contents($absPath);
            if ($bytes === false) { return null; }
            $resp = $server->AdminMetaResourceNew($bytes);
            if ($resp && isset($resp->ResourceHash) && $resp->ResourceHash !== '') { return (string)$resp->ResourceHash; }
        } catch (\Throwable $__) {}
        return null;
    }

    private function uploadResourceViaClient($client, string $absPath): ?string
    {
        if ($absPath === '' || !@is_readable($absPath)) { return null; }
        try {
            $bytes = @file_get_contents($absPath);
            if ($bytes === false) { return null; }
            // Try method call with raw bytes
            if (is_object($client) && method_exists($client, 'AdminMetaResourceNew')) {
                $resp = $client->AdminMetaResourceNew($bytes);
                if (is_object($resp)) {
                    if (property_exists($resp, 'ResourceHash') && (string)$resp->ResourceHash !== '') { return (string)$resp->ResourceHash; }
                    if (property_exists($resp, 'Hash') && (string)$resp->Hash !== '') { return (string)$resp->Hash; }
                } else if (is_array($resp)) {
                    if (isset($resp['ResourceHash']) && (string)$resp['ResourceHash'] !== '') { return (string)$resp['ResourceHash']; }
                    if (isset($resp['Hash']) && (string)$resp['Hash'] !== '') { return (string)$resp['Hash']; }
                }
            }
            // Legacy clients might expect associative payload
            if (is_object($client) && method_exists($client, 'AdminMetaResourceNew')) {
                $resp = $client->AdminMetaResourceNew(['Bytes' => $bytes]);
                if (is_object($resp) && property_exists($resp, 'ResourceHash') && (string)$resp->ResourceHash !== '') { return (string)$resp->ResourceHash; }
                if (is_array($resp) && isset($resp['ResourceHash']) && (string)$resp['ResourceHash'] !== '') { return (string)$resp['ResourceHash']; }
            }
        } catch (\Throwable $__) {}
        return null;
    }

    private function rewriteBrandingAssetsToResources(\Comet\Server $server, array $b): array
    {
        foreach (['LogoImage','Favicon','PathHeaderImage','PathAppIconImage','PathTilePng','PathIcoFile','PathIcnsFile','PathMenuBarIcnsFile','PathEulaRtf'] as $k) {
            if (!empty($b[$k]) && is_string($b[$k]) && strpos((string)$b[$k], 'resource://') !== 0) {
                $path = (string)$b[$k];
                $hash = $this->uploadResource($server, $path);
                if ($hash) {
                    $this->log('comet resource uploaded key=' . $k . ' hash=' . $hash);
                    $b[$k] = 'resource://' . $hash;
                } else {
                    $this->log('comet resource upload failed key=' . $k . ' path=' . $path);
                    // Avoid leaking server-local paths into Comet config
                    $b[$k] = '';
                }
            }
        }
        return $b;
    }

    private function rewriteBrandingAssetsToResourcesViaClient($client, array $b): array
    {
        foreach (['LogoImage','Favicon','PathHeaderImage','PathAppIconImage','PathTilePng','PathIcoFile','PathIcnsFile','PathMenuBarIcnsFile','PathEulaRtf'] as $k) {
            if (!empty($b[$k]) && is_string($b[$k]) && strpos((string)$b[$k], 'resource://') !== 0) {
                $path = (string)$b[$k];
                $hash = $this->uploadResourceViaClient($client, $path);
                if ($hash) {
                    $this->log('comet resource uploaded (client) key=' . $k . ' hash=' . $hash);
                    $b[$k] = 'resource://' . $hash;
                } else {
                    $this->log('comet resource upload failed (client) key=' . $k . ' path=' . $path);
                    // Avoid leaking server-local paths into Comet config
                    $b[$k] = '';
                }
            }
        }
        return $b;
    }

    private function loadOrgOrThrow(\Comet\Server $server, string $orgId): \Comet\Organization
    {
        $list = $server->AdminOrganizationList();
        if (is_array($list) && isset($list[$orgId]) && $list[$orgId] instanceof \Comet\Organization) {
            return $list[$orgId];
        }
        throw new \RuntimeException('Organization not found: ' . $orgId);
    }

    private function loadOrgViaClient($client, string $orgId)
    {
        try {
            if (!is_object($client) || !method_exists($client, 'AdminOrganizationList')) { return null; }
            $list = $client->AdminOrganizationList();
            // Try by associative index
            if (is_array($list)) {
                if (isset($list[$orgId])) { return $list[$orgId]; }
                // Try search through values
                foreach ($list as $k => $v) {
                    if (is_object($v) && property_exists($v, 'ID') && (string)$v->ID === (string)$orgId) { return $v; }
                    if (is_array($v) && isset($v['ID']) && (string)$v['ID'] === (string)$orgId) { return $v; }
                }
                // Nested under a key
                foreach (['Organizations','Orgs','Data'] as $top) {
                    if (isset($list[$top]) && is_array($list[$top])) {
                        foreach ($list[$top] as $v) {
                            if (is_object($v) && property_exists($v, 'ID') && (string)$v->ID === (string)$orgId) { return $v; }
                            if (is_array($v) && isset($v['ID']) && (string)$v['ID'] === (string)$orgId) { return $v; }
                        }
                    }
                }
            }
        } catch (\Throwable $__) {}
        return null;
    }

    private function lookupFqdnByOrgId(string $orgId): string
    {
        try {
            $row = Capsule::table('eb_whitelabel_tenants')->where('org_id', $orgId)->first();
            if ($row && isset($row->fqdn)) { return (string)$row->fqdn; }
        } catch (\Throwable $__) {}
        return '';
    }

    private function findAdminInConfig(\Comet\Server $server, string $orgId)
    {
        try {
            $cfg = $server->AdminMetaServerConfigGet();
            if (is_object($cfg) && isset($cfg->AdminUsers) && is_array($cfg->AdminUsers)) {
                foreach ($cfg->AdminUsers as $au) {
                    $o = is_object($au) ? (string)($au->OrganizationID ?? '') : (string)($au['OrganizationID'] ?? '');
                    if ($o === (string)$orgId) { return $au; }
                }
            }
        } catch (\Throwable $__) {}
        return null;
    }

    private function getAdminPlainPasswordForOrg(string $orgId): string
    {
        try {
            $row = Capsule::table('eb_whitelabel_tenants')->where('org_id', $orgId)->first();
            if ($row) {
                $enc = (string)($row->comet_admin_pass_enc ?? '');
                if ($enc !== '' && function_exists('decrypt')) { return (string)decrypt($enc); }
            }
        } catch (\Throwable $__) {}
        return '';
    }

    private function log(string $msg): void
    {
        try { logModuleCall('eazybackup','whitelabel_comet',[], $msg); } catch (\Throwable $_) {}
    }

    private function resolveCreds(): ?array
    {
        try {
            $sid = (int)($this->cfg['comet_server_id'] ?? 0);
            if ($sid > 0) {
                $srv = Capsule::table('tblservers')->where('id', $sid)->first();
                if ($srv) {
                    $host = (string)($srv->hostname ?? '');
                    $secureRaw = $srv->secure ?? 1;
                    $isSecure = is_numeric($secureRaw)
                        ? ((int)$secureRaw === 1)
                        : in_array(strtolower((string)$secureRaw), ['1','on','true','yes'], true);
                    $port = (string)($srv->port ?? '');
                    $scheme = $isSecure ? 'https' : 'http';
                    $url = $scheme . '://' . $host;
                    if ($port !== '' && !in_array((int)$port, [$isSecure ? 443 : 80], true)) { $url .= ':' . $port; }
                    $user = (string)($srv->username ?? '');
                    $pass = (string)($srv->password ?? '');
                    if (function_exists('decrypt')) { $pass = (string)decrypt($pass); }
                    if ($url !== '' && $user !== '' && $pass !== '') { return ['url' => $url, 'user' => $user, 'pass' => $pass]; }
                }
            }
        } catch (\Throwable $e) {
            $this->log('comet resolveCreds error: ' . $e->getMessage());
        }
        $url = rtrim((string)($this->cfg['comet_root_url'] ?? ''), '/');
        $user = (string)($this->cfg['comet_root_admin'] ?? '');
        $pass = (string)($this->cfg['comet_root_password'] ?? '');
        if ($url !== '' && $user !== '' && $pass !== '') { return ['url' => $url, 'user' => $user, 'pass' => $pass]; }
        return null;
    }

    private function getServerClient(string $url, string $user, string $pass)
    {
        try {
            if (!class_exists('Comet\\Server')) {
                @require_once __DIR__ . '/../../../../servers/comet/vendor/autoload.php';
                if (!class_exists('Comet\\Server')) {
                    @require_once __DIR__ . '/../../../../servers/comet/functions.php';
                }
            }
            if (!class_exists('Comet\\Server')) { return null; }
            $u = rtrim($url, '/') . '/';
            return new \Comet\Server($u, $user, $pass);
        } catch (\Throwable $e) {
            $this->log('comet getServerClient error: ' . $e->getMessage());
            return null;
        }
    }

    private function maskUrl(string $url): string
    {
        $p = @parse_url($url);
        if (!is_array($p)) { return '[invalid-url]'; }
        $scheme = isset($p['scheme']) ? $p['scheme'] : 'https';
        $host = isset($p['host']) ? $p['host'] : '';
        $port = isset($p['port']) ? (int)$p['port'] : null;
        $u = $scheme . '://' . $host;
        if ($port !== null) { $u .= ':' . $port; }
        return $u;
    }
}



