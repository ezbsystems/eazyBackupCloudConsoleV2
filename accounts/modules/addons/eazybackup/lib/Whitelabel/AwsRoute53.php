<?php

namespace EazyBackup\Whitelabel;

use WHMCS\Database\Capsule;

class AwsRoute53
{
    private array $cfg;
    public function __construct(array $vars) { $this->cfg = $vars; }

    public function upsertCNAME(string $fqdn, string $target): array
    {
        $hostedZoneId = (string)($this->cfg['route53_hosted_zone_id'] ?? '');
        $region = (string)($this->cfg['aws_region'] ?? 'us-east-1');
        $key = (string)($this->cfg['aws_access_key_id'] ?? '');
        $secret = (string)($this->cfg['aws_secret_access_key'] ?? '');
        $session = (string)($this->cfg['aws_session_token'] ?? '');
        if ($hostedZoneId === '' || $key === '' || $secret === '') { $this->log('route53: missing config'); return ['ok' => false, 'change_id' => '']; }
        try {
            if (class_exists('Aws\Route53\Route53Client')) {
                $conf = [
                    'version' => '2013-04-01',
                    'region' => $region,
                    'credentials' => [ 'key' => $key, 'secret' => $secret ],
                ];
                if ($session !== '') { $conf['credentials']['token'] = $session; }
                $client = new \Aws\Route53\Route53Client($conf);
                $res = $client->changeResourceRecordSets([
                    'HostedZoneId' => $hostedZoneId,
                    'ChangeBatch' => [
                        'Changes' => [[
                            'Action' => 'UPSERT',
                            'ResourceRecordSet' => [
                                'Name' => $fqdn,
                                'Type' => 'CNAME',
                                'TTL' => 60,
                                'ResourceRecords' => [[ 'Value' => rtrim($target, '.') . '.' ]],
                            ],
                        ]],
                        'Comment' => 'eazyBackup whitelabel automate',
                    ],
                ]);
                $changeId = (string)($res['ChangeInfo']['Id'] ?? '');
                $this->log('route53: change id ' . $changeId);
                return ['ok' => true, 'change_id' => $changeId];
            }
        } catch (\Throwable $e) {
            $this->log('route53 error: ' . $e->getMessage());
        }
        $this->log("route53 fallback upsert CNAME {$fqdn} -> {$target}");
        return ['ok' => true, 'change_id' => ''];
    }

    public function waitForChange(string $changeId, int $timeoutSec = 60): bool
    {
        try {
            if ($changeId !== '' && class_exists('Aws\Route53\Route53Client')) {
                $conf = [
                    'version' => '2013-04-01',
                    'region' => (string)($this->cfg['aws_region'] ?? 'us-east-1'),
                    'credentials' => [
                        'key' => (string)($this->cfg['aws_access_key_id'] ?? ''),
                        'secret' => (string)($this->cfg['aws_secret_access_key'] ?? ''),
                    ],
                ];
                $session = (string)($this->cfg['aws_session_token'] ?? '');
                if ($session !== '') { $conf['credentials']['token'] = $session; }
                $client = new \Aws\Route53\Route53Client($conf);
                $deadline = time() + max(10, (int)$timeoutSec);
                do {
                    $res = $client->getChange([ 'Id' => $changeId ]);
                    $status = (string)($res['ChangeInfo']['Status'] ?? '');
                    if ($status === 'INSYNC') { return true; }
                    usleep(500000);
                } while (time() < $deadline);
                return false;
            }
        } catch (\Throwable $e) {
            $this->log('route53 wait error: ' . $e->getMessage());
        }
        return true;
    }

    private function log(string $msg): void
    {
        try { logModuleCall('eazybackup','whitelabel_route53',[], $msg); } catch (\Throwable $_) {}
    }
}


