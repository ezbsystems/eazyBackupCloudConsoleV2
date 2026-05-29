<?php

require_once __DIR__ . '/../../lib/Admin/AgentBuild/bootstrap.php';

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

function cloudstorage_admin_agent_builds($vars)
{
    $tab = $_GET['tab'] ?? 'dashboard';
    $jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
    $baseUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=agent_builds';

    // Settings save (POST)
    if ($tab === 'settings' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && ($_POST['cs_action'] ?? '') === 'save_settings') {
        cloudstorage_agent_builds_save_settings();
        header('Location: ' . $baseUrl . '&tab=settings&saved=1');
        exit;
    }

    $settings = Settings::all();
    $latestLinux   = cloudstorage_agent_builds_latest('linux');
    $latestWindows = cloudstorage_agent_builds_latest('windows');
    $jobs          = JobStore::listJobs(50);
    $releases      = JobStore::listReleases(50);

    $job = null;
    $steps = [];
    if ($jobId > 0) {
        $job = JobStore::getJob($jobId);
        if ($job) {
            $steps = JobStore::steps($jobId);
        }
    }

    $templateVars = [
        'baseUrl'        => $baseUrl,
        'tab'            => $tab,
        'settings'       => $settings,
        'latestLinux'    => $latestLinux,
        'latestWindows'  => $latestWindows,
        'jobs'           => $jobs,
        'releases'       => $releases,
        'job'            => $job,
        'steps'          => $steps,
        'jobId'          => $jobId,
        'savedFlag'      => !empty($_GET['saved']),
        'defaultGitRef'  => $settings['default_git_ref'] ?? 'main',
        'nextVersion'    => JobStore::nextSuggestedVersion(),
        'token'          => function_exists('generate_token') ? generate_token('plain') : '',
    ];

    $template = new \Smarty();
    $template->setTemplateDir(__DIR__ . '/../../templates/admin/');
    $template->setCompileDir($GLOBALS['templates_compiledir']);
    $template->assign($templateVars);
    echo $template->fetch('agent_builds.tpl');
}

function cloudstorage_agent_builds_latest(string $platform): ?array
{
    try {
        $r = \WHMCS\Database\Capsule::table('s3_agent_releases')
            ->where('platform', $platform)
            ->where('is_latest', 1)
            ->orderBy('id', 'desc')
            ->first();
        return $r ? (array) $r : null;
    } catch (\Throwable $e) {
        return null;
    }
}

function cloudstorage_agent_builds_save_settings(): void
{
    $fields = [
        'agent_build_repo_path'              => $_POST['repo_path'] ?? '',
        'agent_build_git_root'               => $_POST['git_root'] ?? '',
        'agent_build_default_git_ref'        => $_POST['default_git_ref'] ?? 'main',
        'agent_build_publish_dir'            => $_POST['publish_dir'] ?? '',
        'agent_build_windows_host'           => $_POST['win_host'] ?? '',
        'agent_build_windows_user'           => $_POST['win_user'] ?? '',
        'agent_build_windows_ssh_key'        => $_POST['win_ssh_key'] ?? '',
        'agent_build_windows_work_dir'       => $_POST['win_work_dir'] ?? '',
        'agent_build_iscc_path'              => $_POST['iscc_path'] ?? '',
        'agent_build_signing_enabled'        => !empty($_POST['signing_enabled']) ? 'on' : '',
        'agent_build_azure_tenant_id'        => $_POST['azure_tenant_id'] ?? '',
        'agent_build_azure_client_id'        => $_POST['azure_client_id'] ?? '',
        'agent_build_azure_kv_url'           => $_POST['azure_kv_url'] ?? '',
        'agent_build_azure_kv_cert_name'     => $_POST['azure_kv_cert_name'] ?? '',
        'agent_build_signing_timestamp_url'  => $_POST['azure_ts_url'] ?? '',
        'agent_build_azuresigntool_path'     => $_POST['azuresigntool_path'] ?? '',
    ];

    foreach ($fields as $k => $v) {
        // tbladdonmodules has no unique key on (module, setting), so
        // updateOrInsert can produce duplicates if a prior save inserted
        // before any row existed. Delete-then-insert keeps it idempotent.
        \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $k)
            ->delete();
        \WHMCS\Database\Capsule::table('tbladdonmodules')->insert([
            'module'  => 'cloudstorage',
            'setting' => $k,
            'value'   => (string) $v,
        ]);
    }

    // Encrypted client secret: only update when a non-empty value is supplied.
    $secret = (string) ($_POST['azure_client_secret'] ?? '');
    if ($secret !== '') {
        $encrypted = $secret;
        try {
            if (function_exists('localAPI')) {
                $r = localAPI('EncryptPassword', ['password2' => $secret]);
                if (is_array($r) && !empty($r['password'])) {
                    $encrypted = (string) $r['password'];
                }
            }
        } catch (\Throwable $e) {}
        \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'agent_build_azure_client_secret')
            ->delete();
        \WHMCS\Database\Capsule::table('tbladdonmodules')->insert([
            'module'  => 'cloudstorage',
            'setting' => 'agent_build_azure_client_secret',
            'value'   => $encrypted,
        ]);
    }
}
