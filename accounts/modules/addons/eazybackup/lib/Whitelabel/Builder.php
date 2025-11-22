<?php

namespace EazyBackup\Whitelabel;

use WHMCS\Database\Capsule;

class Builder
{
    private array $cfg;

    public function __construct(array $vars)
    {
        $this->cfg = $vars;
    }

    /**
     * Run entire pipeline inline (no cron). Minimal placeholder: marks success immediately in DEV or stub.
     */
    public function runImmediate(int $tenantId): void
    {
        $dev = (int)($this->cfg['whitelabel_dev_mode'] ?? 0) === 1;
        $steps = ['dns','nginx','cert','org','admin','branding','email','storage','whmcs','verify'];
        foreach ($steps as $st) { $this->ensureStep($tenantId, $st, 'queued'); }

        // Load tenant row
        $t = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
        if (!$t) { throw new \RuntimeException('Tenant missing'); }
        $fqdn = (string)$t->fqdn;

        // 1) DNS
        $this->startStep($tenantId, 'dns');
        $dnsOk = true;
        if (!$dev || !(int)($this->cfg['whitelabel_dev_skip_dns'] ?? 0)) {
            $dns = new \EazyBackup\Whitelabel\AwsRoute53($this->cfg);
            $target = (string)($this->cfg['whitelabel_dns_target'] ?? '');
            if ($target === '') { $target = $fqdn; }
            $res = $dns->upsertCNAME($fqdn, $target);
            $dnsOk = (bool)($res['ok'] ?? true);
            $changeId = (string)($res['change_id'] ?? '');
            if ($dnsOk && $changeId !== '') { $dnsOk = $dns->waitForChange($changeId); }
        }
        $this->setStep($tenantId, 'dns', $dnsOk ? 'success' : 'failed');
        if (!$dnsOk) { Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update(['status'=>'failed','updated_at'=>date('Y-m-d H:i:s')]); return; }

        // 2) nginx (HTTP stub)
        $this->startStep($tenantId, 'nginx');
        if (!$dev || !(int)($this->cfg['whitelabel_dev_skip_nginx'] ?? 0)) {
            $ops = new \EazyBackup\Whitelabel\HostOps($this->cfg);
            $ops->writeHttpStub($fqdn);
        }
        $this->setStep($tenantId, 'nginx', 'success');

        // 3b) write HTTPS vhost after cert
        $this->startStep($tenantId, 'cert');
        if (!$dev || !(int)($this->cfg['whitelabel_dev_skip_cert'] ?? 0)) {
            $ops = new \EazyBackup\Whitelabel\HostOps($this->cfg);
            $ops->issueCert($fqdn);
        }
        $this->setStep($tenantId, 'cert', 'success');

        // 3c) HTTPS vhost
        $this->startStep($tenantId, 'nginx');
        if (!$dev || !(int)($this->cfg['whitelabel_dev_skip_nginx'] ?? 0)) {
            $ops = new \EazyBackup\Whitelabel\HostOps($this->cfg);
            $ops->writeHttps($fqdn);
        }
        $this->setStep($tenantId, 'nginx', 'success');

        // 4) Organization
        $this->startStep($tenantId, 'org');
        $ct = new \EazyBackup\Whitelabel\CometTenant($this->cfg);
        $org = $ct->createOrUpdateOrg($fqdn, $fqdn);
        $orgId = (string)($org['org_id'] ?? '');
        Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update(['org_id' => $orgId]);
        $this->setStep($tenantId, 'org', $orgId !== '' ? 'success' : 'failed');
        if ($orgId === '') { Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update(['status'=>'failed','updated_at'=>date('Y-m-d H:i:s')]); return; }

        // 5) Admin user
        $this->startStep($tenantId, 'admin');
        $adminUser = 'admin@' . $fqdn;
        $adminPass = bin2hex(random_bytes(8));
        $ct->createAdminUser($orgId, $adminUser, $adminPass);
        Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
            'comet_admin_user' => $adminUser,
            'comet_admin_pass_enc' => encrypt($adminPass),
        ]);
        // Clone and assign policy to admin; mark step failed if this fails
        $okPolicy = false;
        try { $okPolicy = (bool)$ct->ensurePolicyForAdmin($orgId, $adminUser, '68edd10b-1e17-4a25-826b-8e24b6c32d80'); } catch (\Throwable $__) { $okPolicy = false; }
        $this->setStep($tenantId, 'admin', $okPolicy ? 'success' : 'failed');

        // 6) Branding
        $this->startStep($tenantId, 'branding');
        $brand = json_decode((string)($t->brand_json ?? '{}'), true) ?: [];
        $okBrand = (bool)$ct->applyBranding($orgId, $brand);
        $this->setStep($tenantId, 'branding', $okBrand ? 'success' : 'failed');

        // 7) Email
        $this->startStep($tenantId, 'email');
        $email = json_decode((string)($t->email_json ?? '{}'), true) ?: [];
        $okEmail = (bool)$ct->applyEmailOptions($orgId, $email);
        $this->setStep($tenantId, 'email', $okEmail ? 'success' : 'failed');

        // 8) Storage
        $this->startStep($tenantId, 'storage');
        $ct->configureStorageTemplate($orgId, []);
        $this->setStep($tenantId, 'storage', 'success');

		// 9) WHMCS wiring
		$this->startStep($tenantId, 'whmcs');
		$ops = new \EazyBackup\Whitelabel\WhmcsOps();
		$srv = $ops->addServerAndGroup($fqdn, $fqdn, (string)($this->cfg['server_module_name'] ?? 'comet'));
		$tplPid = (int)($this->cfg['whitelabel_template_pid'] ?? 0);
		// Resolve target PRODUCT GROUP id per client (one group per customer)
		$targetGroupId = (int)$ops->ensureClientProductGroup((int)$t->client_id);
		// Determine product display name from intake branding (fallback to FQDN Plan)
		$brandForName = json_decode((string)($t->brand_json ?? '{}'), true) ?: [];
		$productName = trim((string)($brandForName['ProductName'] ?? ''));
		if ($productName === '') { $productName = trim((string)($brandForName['BrandName'] ?? '')); }
		if ($productName === '') { $productName = $fqdn . ' Plan'; }
		$newPid = ($tplPid > 0 && $targetGroupId > 0) ? $ops->cloneProduct($tplPid, $targetGroupId, $productName) : 0;
		Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update([
			'server_id' => (int)$srv['server_id'], 'servergroup_id' => (int)$srv['servergroup_id'], 'product_id' => (int)$newPid
		]);
		$this->setStep($tenantId, 'whmcs', 'success');

        // 10) verify
        $this->startStep($tenantId, 'verify');
        $this->setStep($tenantId, 'verify', 'success');

        Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function applyBranding(int $tenantId): bool
    {
        $t = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
        if (!$t) {
            try { logModuleCall('eazybackup','branding_apply_start', ['tenant'=>$tenantId], 'not_found'); } catch (\Throwable $__) {}
            return false;
        }
        $orgId = (string)($t->org_id ?? '');
        if ($orgId === '') {
            // Guard: cannot apply without an organization on Comet
            try { logModuleCall('eazybackup','branding_apply_start', ['tenant'=>$tenantId], 'missing_org'); } catch (\Throwable $__) {}
            return false;
        }
        $brand = json_decode((string)($t->brand_json ?? '{}'), true) ?: [];
        $email = json_decode((string)($t->email_json ?? '{}'), true) ?: [];

        // Step bookkeeping
        $this->ensureStep($tenantId, 'branding', 'queued');
        $this->startStep($tenantId, 'branding');

        $ct = new \EazyBackup\Whitelabel\CometTenant($this->cfg);
        $okBrand = false;
        try {
            $okBrand = (bool)$ct->applyBranding($orgId, $brand);
        } catch (\Throwable $e) {
            $okBrand = false;
            try { logModuleCall('eazybackup','branding_apply_exception', ['tenant'=>$tenantId], $e->getMessage()); } catch (\Throwable $__) {}
        }
        $this->setStep($tenantId, 'branding', $okBrand ? 'success' : 'failed');

        // Email is coupled on this page; apply and track as separate step
        $this->ensureStep($tenantId, 'email', 'queued');
        $this->startStep($tenantId, 'email');
        $okEmail = true; // treat inherit as true in CometTenant
        try {
            $okEmail = (bool)$ct->applyEmailOptions($orgId, $email);
        } catch (\Throwable $e) {
            $okEmail = false;
            try { logModuleCall('eazybackup','branding_apply_email_exception', ['tenant'=>$tenantId], $e->getMessage()); } catch (\Throwable $__) {}
        }
        $this->setStep($tenantId, 'email', $okEmail ? 'success' : 'failed');

        Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $ok = ($okBrand && $okEmail);
        try { logModuleCall('eazybackup','branding_apply_result', ['tenant'=>$tenantId], $ok ? 'ok' : 'failed'); } catch (\Throwable $__) {}
        return $ok;
    }

    private function ensureStep(int $tenantId, string $step, string $status): void
    {
        $exists = Capsule::table('eb_whitelabel_builds')
            ->where('tenant_id', $tenantId)
            ->where('step', $step)
            ->exists();
        if (!$exists) {
            Capsule::table('eb_whitelabel_builds')->insert([
                'tenant_id' => $tenantId,
                'step' => $step,
                'status' => $status,
                'log_json' => json_encode([]),
                'idempotency_key' => sha1($tenantId . ':' . $step),
                'started_at' => null,
                'finished_at' => null,
                'last_error' => null,
            ]);
        }
    }

    private function setStep(int $tenantId, string $step, string $status): void
    {
        Capsule::table('eb_whitelabel_builds')
            ->where('tenant_id', $tenantId)
            ->where('step', $step)
            ->update([
                'status' => $status,
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function startStep(int $tenantId, string $step): void
    {
        Capsule::table('eb_whitelabel_builds')
            ->where('tenant_id', $tenantId)
            ->where('step', $step)
            ->update([
                'status' => 'running',
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => null,
            ]);
    }

    // DEV runner: execute a single step in isolation
    public function runStep(int $tenantId, string $step): void
    {
        $valid = ['dns','nginx','cert','org','admin','branding','email','storage','whmcs','verify'];
        if (!in_array($step, $valid, true)) { return; }
        $t = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first(); if (!$t) return;
        $fqdn = (string)$t->fqdn; $orgId = (string)($t->org_id ?? '');
        switch ($step) {
            case 'dns':
                $this->ensureStep($tenantId, 'dns','queued'); $this->startStep($tenantId,'dns');
                $dns = new \EazyBackup\Whitelabel\AwsRoute53($this->cfg);
                $target = (string)($this->cfg['whitelabel_dns_target'] ?? ''); if ($target===''){ $target=$fqdn; }
                $res = $dns->upsertCNAME($fqdn, $target); $dns->waitForChange((string)($res['change_id']??'')); $this->setStep($tenantId,'dns','success'); break;
            case 'nginx':
                $this->ensureStep($tenantId,'nginx','queued'); $this->startStep($tenantId,'nginx');
                $ops = new \EazyBackup\Whitelabel\HostOps($this->cfg); $ops->writeHttpStub($fqdn); $this->setStep($tenantId,'nginx','success'); break;
            case 'cert':
                $this->ensureStep($tenantId,'cert','queued'); $this->startStep($tenantId,'cert');
                $ops2 = new \EazyBackup\Whitelabel\HostOps($this->cfg); $ops2->issueCert($fqdn); $this->setStep($tenantId,'cert','success'); break;
            case 'org':
                $this->ensureStep($tenantId,'org','queued'); $this->startStep($tenantId,'org');
                $ct = new \EazyBackup\Whitelabel\CometTenant($this->cfg); $o = $ct->createOrUpdateOrg($fqdn,$fqdn); $oid=(string)($o['org_id']??''); Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update(['org_id'=>$oid]); $this->setStep($tenantId,'org','success'); break;
            case 'admin':
                $this->ensureStep($tenantId,'admin','queued'); $this->startStep($tenantId,'admin');
                $ct2 = new \EazyBackup\Whitelabel\CometTenant($this->cfg); $user='admin@'.$fqdn; $pass=bin2hex(random_bytes(8)); $ct2->createAdminUser($orgId?:'',$user,$pass); Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update(['comet_admin_user'=>$user,'comet_admin_pass_enc'=>encrypt($pass)]); $this->setStep($tenantId,'admin','success'); break;
            case 'branding':
                $this->ensureStep($tenantId,'branding','queued'); $this->startStep($tenantId,'branding');
                $ct3 = new \EazyBackup\Whitelabel\CometTenant($this->cfg); $brand=json_decode((string)($t->brand_json??'{}'),true)?:[]; $ok=$ct3->applyBranding($orgId?:'',$brand); $this->setStep($tenantId,'branding',$ok?'success':'failed'); break;
            case 'email':
                $this->ensureStep($tenantId,'email','queued'); $this->startStep($tenantId,'email');
                $ct4 = new \EazyBackup\Whitelabel\CometTenant($this->cfg); $email=json_decode((string)($t->email_json??'{}'),true)?:[]; $ok=$ct4->applyEmailOptions($orgId?:'',$email); $this->setStep($tenantId,'email',$ok?'success':'failed'); break;
            case 'storage':
                $this->ensureStep($tenantId,'storage','queued'); $this->startStep($tenantId,'storage');
                $ct5 = new \EazyBackup\Whitelabel\CometTenant($this->cfg); $ct5->configureStorageTemplate($orgId?:'',[]); $this->setStep($tenantId,'storage','success'); break;
			case 'whmcs':
				$this->ensureStep($tenantId,'whmcs','queued'); $this->startStep($tenantId,'whmcs');
				$ops = new \EazyBackup\Whitelabel\WhmcsOps();
				$srv = $ops->addServerAndGroup($fqdn,$fqdn,(string)($this->cfg['server_module_name']??'comet'));
				$tplPid = (int)($this->cfg['whitelabel_template_pid']??0);
				$targetGroupId = (int)$ops->ensureClientProductGroup((int)$t->client_id);
				$brandForName = json_decode((string)($t->brand_json??'{}'), true) ?: [];
				$productName = trim((string)($brandForName['ProductName'] ?? ''));
				if ($productName === '') { $productName = trim((string)($brandForName['BrandName'] ?? '')); }
				if ($productName === '') { $productName = $fqdn.' Plan'; }
				$newPid = ($tplPid>0 && $targetGroupId>0) ? $ops->cloneProduct($tplPid,$targetGroupId,$productName) : 0;
				Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update(['server_id'=>(int)$srv['server_id'],'servergroup_id'=>(int)$srv['servergroup_id'],'product_id'=>(int)$newPid]);
				$this->setStep($tenantId,'whmcs','success');
				break;
            case 'verify':
                $this->ensureStep($tenantId,'verify','queued'); $this->startStep($tenantId,'verify'); $this->setStep($tenantId,'verify','success'); break;
        }
    }
}


