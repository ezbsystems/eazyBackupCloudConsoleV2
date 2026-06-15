<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
<style>{literal}
.eb-pill { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; color:#fff; }
.eb-pill.queued{background:#777} .eb-pill.running{background:#3498db} .eb-pill.succeeded{background:#27ae60}
.eb-pill.failed{background:#c0392b} .eb-pill.cancelled{background:#95a5a6} .eb-pill.skipped{background:#bdc3c7}
.eb-pill.pending{background:#7f8c8d}
.eb-pill.active{background:#27ae60} .eb-pill.superseded{background:#95a5a6} .eb-pill.draft{background:#777}
.eb-step-row { padding:8px 12px; border-left:3px solid #ccc; margin-bottom:4px; background:#fafafa; }
.eb-step-row.running { border-left-color:#3498db; }
.eb-step-row.succeeded { border-left-color:#27ae60; }
.eb-step-row.failed { border-left-color:#c0392b; }
.eb-step-row.skipped { border-left-color:#bdc3c7; opacity:0.7; }
.eb-log { background:#1e1e1e; color:#dcdcdc; font-family:Consolas,Menlo,monospace; font-size:12px;
          padding:10px; border-radius:4px; max-height:480px; overflow:auto; white-space:pre-wrap; }
.eb-cards .panel { margin-bottom:10px; }
.eb-tabs { margin-bottom:15px; }
.eb-monospace { font-family:Consolas,Menlo,monospace; font-size:12px; }
{/literal}</style>

<div class="content-padded">
<h2 class="page-title"><i class="fa fa-cogs"></i> e3 Agent Builds</h2>
<p class="text-muted">Build, sign, and publish the e3 local backup agent for Linux and Windows.</p>

<ul class="nav nav-tabs eb-tabs">
  <li class="{if $tab eq 'dashboard'}active{/if}"><a href="{$baseUrl}&tab=dashboard">Dashboard</a></li>
  <li class="{if $tab eq 'new'}active{/if}"><a href="{$baseUrl}&tab=new">New Build</a></li>
  <li class="{if $tab eq 'history'}active{/if}"><a href="{$baseUrl}&tab=history">Build History</a></li>
  <li class="{if $tab eq 'detail'}active{/if}"><a href="{$baseUrl}&tab=detail{if $jobId}&job_id={$jobId}{/if}">Build Detail</a></li>
  <li class="{if $tab eq 'releases'}active{/if}"><a href="{$baseUrl}&tab=releases">Releases</a></li>
  <li class="{if $tab eq 'deployment'}active{/if}"><a href="{$baseUrl}&tab=deployment">Deployment</a></li>
  <li class="{if $tab eq 'settings'}active{/if}"><a href="{$baseUrl}&tab=settings">Settings</a></li>
</ul>

{if $tab eq 'dashboard'}
  <div class="row eb-cards">
    <div class="col-md-6">
      <div class="panel panel-default">
        <div class="panel-heading"><i class="fa fa-linux"></i> Latest Linux Release</div>
        <div class="panel-body">
          {if $latestLinux}
            <p><strong>Version:</strong> {$latestLinux.version_label|escape}<br>
            <strong>SHA-256:</strong> <span class="eb-monospace">{$latestLinux.sha256|escape}</span><br>
            <strong>Size:</strong> {$latestLinux.size_bytes} bytes<br>
            <strong>Published:</strong> {$latestLinux.published_at}<br>
            <a class="btn btn-default btn-sm" href="{$latestLinux.download_url|escape}">Download</a></p>
          {else}<p class="text-muted">No Linux release yet.</p>{/if}
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="panel panel-default">
        <div class="panel-heading"><i class="fa fa-windows"></i> Latest Windows Release</div>
        <div class="panel-body">
          {if $latestWindows}
            <p><strong>Version:</strong> {$latestWindows.version_label|escape}<br>
            <strong>SHA-256:</strong> <span class="eb-monospace">{$latestWindows.sha256|escape}</span><br>
            <strong>Signed:</strong> {if $latestWindows.signed_at}yes ({$latestWindows.signed_subject|escape}){else}no{/if}<br>
            <strong>Size:</strong> {$latestWindows.size_bytes} bytes<br>
            <strong>Published:</strong> {$latestWindows.published_at}<br>
            <a class="btn btn-default btn-sm" href="{$latestWindows.download_url|escape}">Download</a></p>
          {else}<p class="text-muted">No Windows release yet.</p>{/if}
        </div>
      </div>
    </div>
  </div>
  <p><a class="btn btn-primary" href="{$baseUrl}&tab=new"><i class="fa fa-play"></i> Start a new build</a></p>
{/if}

{if $tab eq 'new'}
  <div class="panel panel-default">
    <div class="panel-heading">New Build</div>
    <div class="panel-body">
      <form id="ebNewBuildForm">
        <div class="form-group">
          <label>Platform</label>
          <select name="platform" class="form-control" style="max-width:300px;">
            <option value="both">Linux + Windows</option>
            <option value="linux">Linux only</option>
            <option value="windows">Windows only</option>
            <option value="recovery_iso">Recovery agent only</option>
          </select>
        </div>
        <div class="form-group">
          <label>Git Ref</label>
          <input type="text" name="git_ref" value="{$defaultGitRef|escape}" class="form-control" style="max-width:300px;">
        </div>
        <div class="form-group">
          <label>Version (semantic)</label>
          <input type="text" name="version_label" value="{$nextVersion|escape}" placeholder="e.g. 1.2.1" pattern="v?\d+\.\d+\.\d+" class="form-control" style="max-width:300px;">
          <p class="help-block">Use a semantic version <code>MAJOR.MINOR.PATCH</code> (e.g. <code>1.2.1</code>). Pre-filled with the next patch bump; edit to bump minor/major. This is the version embedded in the agent and shown everywhere.</p>
        </div>
        <div class="checkbox"><label><input type="checkbox" name="run_tests" checked> Run go test ./...</label></div>
        <div class="checkbox"><label><input type="checkbox" name="sign" {if $settings.signing_enabled}checked{/if}> Code-sign Windows binaries (Azure KV)</label></div>
        <div class="checkbox"><label><input type="checkbox" name="publish" checked> Publish to /client_installer/</label></div>
        <div class="checkbox"><label><input type="checkbox" name="deploy_after_publish"> Deploy to production after publish</label></div>
        <div class="checkbox"><label><input type="checkbox" name="include_recovery"> Also build recovery agent</label></div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-play"></i> Start Build</button>
        <span id="ebNewBuildMsg" class="text-muted" style="margin-left:10px;"></span>
      </form>
    </div>
  </div>
{/if}

{if $tab eq 'history'}
  <table class="table table-striped table-condensed">
    <thead><tr><th>ID</th><th>Created</th><th>Platform</th><th>Git Ref</th><th>Version</th><th>Status</th><th>Step</th><th></th></tr></thead>
    <tbody>
    {foreach $jobs as $j}
      <tr>
        <td>{$j.id}</td>
        <td>{$j.created_at}</td>
        <td>{$j.platform}</td>
        <td>{$j.git_ref|escape}{if $j.git_commit} ({$j.git_commit|escape}){/if}</td>
        <td>{$j.version_label|escape}</td>
        <td><span class="eb-pill {$j.status}">{$j.status}</span></td>
        <td>{$j.current_step|escape}</td>
        <td><a class="btn btn-default btn-xs" href="{$baseUrl}&tab=detail&job_id={$j.id}">Detail</a></td>
      </tr>
    {foreachelse}
      <tr><td colspan="8" class="text-muted">No builds yet.</td></tr>
    {/foreach}
    </tbody>
  </table>
{/if}

{if $tab eq 'detail'}
  {if $job}
    <div class="panel panel-default">
      <div class="panel-heading">
        Build #{$job.id}
        <span class="eb-pill {$job.status}" id="ebJobStatus">{$job.status}</span>
        <span class="pull-right">
          <button type="button" class="btn btn-success btn-xs" id="ebDeployJobBtn" {if $job.status neq 'succeeded'}disabled{/if}>Deploy to Production</button>
          <button type="button" class="btn btn-warning btn-xs" id="ebCancelBtn" {if $job.status neq 'queued' and $job.status neq 'running'}disabled{/if}>Cancel</button>
        </span>
      </div>
      <div class="panel-body">
        <p><strong>Platform:</strong> {$job.platform} &nbsp;
           <strong>Git ref:</strong> {$job.git_ref|escape}{if $job.git_commit} ({$job.git_commit|escape}){/if} &nbsp;
           <strong>Version:</strong> {$job.version_label|escape} &nbsp;
           <strong>Started:</strong> {$job.started_at|default:'-'} &nbsp;
           <strong>Ended:</strong> {$job.ended_at|default:'-'}</p>
        {if $job.error_message}<div class="alert alert-danger">{$job.error_message|escape}</div>{/if}

        <div id="ebSteps">
        {foreach $steps as $s}
          <div class="eb-step-row {$s.status}" data-step="{$s.step_key}">
            <strong>{$s.step_key}</strong>
            <span class="eb-pill {$s.status} pull-right" data-role="status">{$s.status}</span>
            <div class="eb-monospace text-muted" data-role="meta">
              exit={$s.exit_code|default:'-'} &middot; bytes={$s.bytes_logged}
            </div>
            <a href="javascript:void(0)" class="ebStepToggle" data-step="{$s.step_key}">show log</a>
            <pre class="eb-log" data-step-log="{$s.step_key}" style="display:none"></pre>
          </div>
        {/foreach}
        </div>
      </div>
    </div>

    <script>
    (function(){
      var jobId = {$job.id|intval};
      var apiBase = '/modules/addons/cloudstorage/api/';
      var offsets = {};
      var initialStatus = {literal}"{/literal}{$job.status|escape:'javascript'}{literal}"{/literal};
      var pollDelay = (initialStatus === 'running' || initialStatus === 'queued') ? 2000 : 30000;

      function fmt(s){ return (s||'').toString(); }

      function refreshStatus(){
        fetch(apiBase + 'admin_agent_build_status.php?job_id=' + jobId, {literal}{credentials:'same-origin'}{/literal})
          .then(function(r){ return r.json(); })
          .then(function(d){
            if (d.status !== 'success') return;
            var jobStatus = d.job.status;
            document.getElementById('ebJobStatus').textContent = jobStatus;
            document.getElementById('ebJobStatus').className = 'eb-pill ' + jobStatus;
            d.steps.forEach(function(s){
              var row = document.querySelector('.eb-step-row[data-step="'+s.step_key+'"]');
              if (!row) return;
              row.className = 'eb-step-row ' + s.status;
              row.querySelector('[data-role=status]').textContent = s.status;
              row.querySelector('[data-role=status]').className = 'eb-pill ' + s.status + ' pull-right';
              row.querySelector('[data-role=meta]').textContent = 'exit=' + fmt(s.exit_code) + ' \u00b7 bytes=' + fmt(s.bytes_logged);
              if (s.status === 'running' || s.status === 'succeeded' || s.status === 'failed') {
                tailLog(s.step_key);
              }
            });
            pollDelay = (jobStatus === 'running' || jobStatus === 'queued') ? 2000 : 30000;
          })
          .catch(function(){})
          .finally(function(){ setTimeout(refreshStatus, pollDelay); });
      }

      function tailLog(step){
        var pre = document.querySelector('[data-step-log="'+step+'"]');
        if (!pre || pre.dataset.paused === '1') return;
        var off = offsets[step] || 0;
        fetch(apiBase + 'admin_agent_build_log_tail.php?job_id=' + jobId + '&step=' + step + '&offset=' + off,
              {literal}{credentials:'same-origin'}{/literal})
          .then(function(r){ return r.json(); })
          .then(function(d){
            if (d.status !== 'success') return;
            if (d.chunk) {
              pre.textContent += d.chunk;
              pre.scrollTop = pre.scrollHeight;
            }
            offsets[step] = d.next || 0;
          });
      }

      document.querySelectorAll('.ebStepToggle').forEach(function(a){
        a.addEventListener('click', function(){
          var step = this.getAttribute('data-step');
          var pre = document.querySelector('[data-step-log="'+step+'"]');
          if (pre.style.display === 'none') {
            pre.style.display = 'block';
            this.textContent = 'hide log';
            tailLog(step);
          } else {
            pre.style.display = 'none';
            this.textContent = 'show log';
          }
        });
      });

      var cancelBtn = document.getElementById('ebCancelBtn');
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function(){
          if (!confirm('Cancel build ' + jobId + '?')) return;
          var fd = new FormData(); fd.append('job_id', jobId);
          fetch(apiBase + 'admin_agent_build_cancel.php', {literal}{method:'POST', body: fd, credentials:'same-origin'}{/literal})
            .then(function(){ refreshStatus(); });
        });
      }

      var deployBtn = document.getElementById('ebDeployJobBtn');
      if (deployBtn) {
        deployBtn.addEventListener('click', function(){
          if (!confirm('Deploy build ' + jobId + ' to production?')) return;
          var fd = new FormData();
          fd.append('mode', 'job');
          fd.append('job_id', jobId);
          fetch(apiBase + 'admin_agent_deploy_publish.php', {literal}{method:'POST', body: fd, credentials:'same-origin'}{/literal})
            .then(function(r){ return r.json(); })
            .then(function(d){
              if (d.status === 'success') {
                alert('Deployment ' + d.deployment_id + ' published. Production will sync within ~5 minutes.');
              } else {
                alert('Deploy failed: ' + (d.message || 'unknown error'));
              }
            });
        });
      }

      refreshStatus();
    })();
    </script>

  {else}
    <p class="text-muted">No job selected. Pick one from <a href="{$baseUrl}&tab=history">Build History</a>.</p>
  {/if}
{/if}

{if $tab eq 'releases'}
  <table class="table table-striped table-condensed">
    <thead><tr><th>Platform</th><th>Filename</th><th>Version</th><th>Commit</th><th>SHA-256</th><th>Size</th><th>Latest</th><th>Published</th><th></th></tr></thead>
    <tbody>
    {foreach $releases as $r}
      <tr>
        <td>{$r.platform}</td>
        <td>{$r.artifact_filename|escape}</td>
        <td>{$r.version_label|escape}</td>
        <td>{$r.git_commit|escape}</td>
        <td class="eb-monospace">{$r.sha256|truncate:16:'...'|escape}</td>
        <td>{$r.size_bytes}</td>
        <td>{if $r.is_latest}<span class="label label-success">latest</span>{/if}</td>
        <td>{$r.published_at}</td>
        <td>
          <a class="btn btn-default btn-xs" href="{$r.download_url|escape}">Download</a>
          {if !$r.is_latest}
            <button class="btn btn-primary btn-xs ebPromote" data-id="{$r.id}">Set Latest</button>
          {/if}
        </td>
      </tr>
    {foreachelse}
      <tr><td colspan="9" class="text-muted">No releases yet.</td></tr>
    {/foreach}
    </tbody>
  </table>
  <script>
    document.querySelectorAll('.ebPromote').forEach(function(b){
      b.addEventListener('click', function(){
        var fd = new FormData(); fd.append('release_id', this.getAttribute('data-id'));
        fetch('/modules/addons/cloudstorage/api/admin_agent_build_release_publish.php',
              {literal}{method:'POST', body: fd, credentials:'same-origin'}{/literal})
          .then(function(){ location.reload(); });
      });
    });
  </script>
{/if}

{if $tab eq 'deployment'}
  <div class="row">
    <div class="col-md-8">
      <div class="panel panel-default">
        <div class="panel-heading">Current Production Target</div>
        <div class="panel-body">
          {if $activeDeploy}
            <p><strong>Deployment ID:</strong> {$activeDeploy.deployment_id}<br>
            <strong>Version:</strong> {$activeDeploy.version_label|escape}<br>
            <strong>Git commit:</strong> {$activeDeploy.git_commit|escape}<br>
            <strong>Activated:</strong> {$activeDeploy.activated_at}<br>
            <strong>Artifacts:</strong> {$activeDeploy.artifacts|@count}</p>
            <ul>
            {foreach $activeDeploy.artifacts as $a}
              <li><span class="eb-monospace">{$a.latest_filename|escape}</span> ({$a.platform|escape}, sha256={$a.sha256|truncate:16:'...'|escape})</li>
            {/foreach}
            </ul>
            <p class="text-muted">Production polls the manifest URL every few minutes and installs when this deployment ID changes.</p>
          {else}
            <p class="text-muted">No production deployment target is active yet.</p>
          {/if}
          <button type="button" class="btn btn-primary" id="ebDeployLatestBtn"><i class="fa fa-cloud-upload"></i> Deploy latest releases to production</button>
          <span id="ebDeployMsg" class="text-muted" style="margin-left:10px;"></span>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="panel panel-default">
        <div class="panel-heading">Manifest URL</div>
        <div class="panel-body">
          <p class="eb-monospace" style="word-break:break-all;">{$settings.deploy_manifest_api_url|escape}</p>
          <p class="help-block">Configure this URL and the shared secret on the production server (Settings → Production Deployment).</p>
        </div>
      </div>
      {if $settings.deploy_sync_enabled}
      <div class="panel panel-default">
        <div class="panel-heading">Local Sync Status (consumer)</div>
        <div class="panel-body">
          <p><strong>Last synced deployment:</strong> {$settings.deploy_last_sync_id}<br>
          <strong>Sync enabled:</strong> yes</p>
        </div>
      </div>
      {/if}
    </div>
  </div>

  <h4>Deployment History</h4>
  <table class="table table-striped table-condensed">
    <thead><tr><th>ID</th><th>Version</th><th>Commit</th><th>Job</th><th>Status</th><th>Activated</th></tr></thead>
    <tbody>
    {foreach $deployments as $d}
      <tr>
        <td>{$d.id}</td>
        <td>{$d.version_label|escape}</td>
        <td>{$d.git_commit|escape}</td>
        <td>{if $d.job_id}<a href="{$baseUrl}&tab=detail&job_id={$d.job_id}">#{$d.job_id}</a>{else}-{/if}</td>
        <td><span class="eb-pill {$d.status}">{$d.status}</span></td>
        <td>{$d.activated_at}</td>
      </tr>
    {foreachelse}
      <tr><td colspan="6" class="text-muted">No deployments yet.</td></tr>
    {/foreach}
    </tbody>
  </table>

  {if $syncRuns|@count > 0}
  <h4>Sync Runs (this server)</h4>
  <table class="table table-striped table-condensed">
    <thead><tr><th>ID</th><th>Deployment</th><th>Status</th><th>Detail</th><th>Started</th><th>Ended</th></tr></thead>
    <tbody>
    {foreach $syncRuns as $s}
      <tr>
        <td>{$s.id}</td>
        <td>{$s.deployment_id}</td>
        <td><span class="eb-pill {$s.status}">{$s.status}</span></td>
        <td>{$s.detail|escape}</td>
        <td>{$s.started_at}</td>
        <td>{$s.ended_at|default:'-'}</td>
      </tr>
    {/foreach}
    </tbody>
  </table>
  {/if}

  <script>
  (function(){
    var btn = document.getElementById('ebDeployLatestBtn');
    if (!btn) return;
    btn.addEventListener('click', function(){
      if (!confirm('Deploy the current latest releases to production?')) return;
      var msg = document.getElementById('ebDeployMsg');
      msg.textContent = 'Publishing...';
      var fd = new FormData();
      fd.append('mode', 'latest');
      fetch('/modules/addons/cloudstorage/api/admin_agent_deploy_publish.php',
            {literal}{method:'POST', body: fd, credentials:'same-origin'}{/literal})
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d.status === 'success') {
            msg.textContent = 'Deployment ' + d.deployment_id + ' published (' + d.artifact_count + ' artifacts).';
            setTimeout(function(){ location.reload(); }, 1200);
          } else {
            msg.textContent = 'Error: ' + (d.message || 'unknown');
          }
        })
        .catch(function(err){
          msg.textContent = 'Request failed: ' + (err && err.message ? err.message : err);
        });
    });
  })();
  </script>
{/if}

{if $tab eq 'settings'}
  {if $savedFlag}<div class="alert alert-success">Settings saved.</div>{/if}
  <form method="post" action="{$baseUrl}&tab=settings">
    <input type="hidden" name="cs_action" value="save_settings">
    <fieldset>
      <legend>Build Hosts &amp; Paths</legend>
      <div class="form-group"><label>Local agent repo path</label>
        <input type="text" class="form-control" name="repo_path" value="{$settings.repo_path|escape}">
        <p class="help-block">Filesystem path containing the agent <code>go.mod</code>. All Go builds and Inno staging run from here.</p></div>
      <div class="form-group"><label>Git working tree root (optional)</label>
        <input type="text" class="form-control" name="git_root" value="{if isset($settings.git_root) && $settings.git_root != $settings.repo_path}{$settings.git_root|escape}{/if}" placeholder="leave blank if the agent repo IS its own git working tree">
        <p class="help-block">Set this when the agent source lives inside a larger monorepo (e.g. <code>/var/www/eazybackup.ca</code>). When blank, <code>git fetch/checkout/pull</code> runs in the path above.</p></div>
      <div class="form-group"><label>Default git ref</label>
        <input type="text" class="form-control" name="default_git_ref" value="{$settings.default_git_ref|escape}"></div>
      <div class="form-group"><label>Publish directory (client_installer)</label>
        <input type="text" class="form-control" name="publish_dir" value="{$settings.publish_dir|escape}"></div>
      <div class="form-group"><label>Windows host</label>
        <input type="text" class="form-control" name="win_host" value="{$settings.win_host|escape}"></div>
      <div class="form-group"><label>Windows SSH user</label>
        <input type="text" class="form-control" name="win_user" value="{$settings.win_user|escape}"></div>
      <div class="form-group"><label>Windows SSH key path</label>
        <input type="text" class="form-control" name="win_ssh_key" value="{$settings.win_ssh_key|escape}"></div>
      <div class="form-group"><label>Windows work directory</label>
        <input type="text" class="form-control" name="win_work_dir" value="{$settings.win_work_dir|escape}"></div>
      <div class="form-group"><label>ISCC.exe path (Windows)</label>
        <input type="text" class="form-control" name="iscc_path" value="{$settings.iscc_path|escape}"></div>
    </fieldset>

    <fieldset>
      <legend>Code Signing (Azure Key Vault)</legend>
      <div class="checkbox"><label><input type="checkbox" name="signing_enabled" {if $settings.signing_enabled}checked{/if}> Enable code signing</label></div>
      <div class="form-group"><label>Azure Tenant ID</label>
        <input type="text" class="form-control" name="azure_tenant_id" value="{$settings.azure_tenant_id|escape}"></div>
      <div class="form-group"><label>Azure Client ID</label>
        <input type="text" class="form-control" name="azure_client_id" value="{$settings.azure_client_id|escape}"></div>
      <div class="form-group"><label>Azure Client Secret <small class="text-muted">(leave blank to keep existing)</small></label>
        <input type="password" class="form-control" name="azure_client_secret" value=""></div>
      <div class="form-group"><label>Azure Key Vault URL</label>
        <input type="text" class="form-control" name="azure_kv_url" value="{$settings.azure_kv_url|escape}"></div>
      <div class="form-group"><label>Key Vault certificate name</label>
        <input type="text" class="form-control" name="azure_kv_cert_name" value="{$settings.azure_kv_cert|escape}"></div>
      <div class="form-group"><label>Timestamp URL (RFC 3161)</label>
        <input type="text" class="form-control" name="azure_ts_url" value="{$settings.azure_ts_url|escape}"></div>
      <div class="form-group"><label>AzureSignTool path (Windows)</label>
        <input type="text" class="form-control" name="azuresigntool_path" value="{$settings.azuresigntool|escape}"></div>
    </fieldset>

    <fieldset>
      <legend>Production Deployment</legend>
      <div class="form-group"><label>Server role</label>
        <select name="deploy_role" class="form-control" style="max-width:300px;">
          <option value="publisher" {if $settings.deploy_role eq 'publisher'}selected{/if}>Publisher (dev — builds and serves manifest)</option>
          <option value="consumer" {if $settings.deploy_role eq 'consumer'}selected{/if}>Consumer (production — pulls from dev)</option>
        </select></div>
      <div class="form-group"><label>Shared deploy secret <small class="text-muted">(leave blank to keep existing)</small></label>
        <input type="password" class="form-control" name="deploy_shared_secret" value="" placeholder="same secret on dev and prod">
        <p class="help-block">Used to authenticate manifest and artifact downloads between servers. Store the same value on both dev and production.</p></div>
      <div class="form-group"><label>Manifest URL (consumer only)</label>
        <input type="text" class="form-control" name="deploy_manifest_url" value="{$settings.deploy_manifest_url|escape}" placeholder="https://dev.eazybackup.ca/modules/addons/cloudstorage/api/agent_deploy_manifest.php">
        <p class="help-block">On production, set this to the dev server's manifest endpoint.</p></div>
      <div class="form-group"><label>Production publish directory (consumer only)</label>
        <input type="text" class="form-control" name="deploy_publish_dir" value="{$settings.deploy_publish_dir|escape}" placeholder="/var/www/eazybackup.ca/accounts/client_installer">
        <p class="help-block">Where synced artifacts are installed on production. Defaults to the build publish directory when blank.</p></div>
      <div class="checkbox"><label><input type="checkbox" name="deploy_sync_enabled" {if $settings.deploy_sync_enabled}checked{/if}> Enable deployment sync cron (consumer)</label></div>
      {if $settings.deploy_role eq 'consumer'}
      <button type="button" class="btn btn-default" id="ebDeploySyncTestBtn">Test deployment sync</button>
      <div id="ebDeploySyncTestResults" style="margin-top:10px;"></div>
      {/if}
      {if $settings.deploy_role eq 'publisher'}
      <p class="help-block">Publisher manifest URL: <span class="eb-monospace">{$settings.deploy_manifest_api_url|escape}</span></p>
      {/if}
    </fieldset>

    <button type="submit" class="btn btn-primary">Save Settings</button>
    <button type="button" class="btn btn-default" id="ebTestBtn">Test Connection</button>
    <div id="ebTestResults" style="margin-top:15px;"></div>
  </form>
  <script>
    document.getElementById('ebTestBtn').addEventListener('click', function(){
      var box = document.getElementById('ebTestResults');
      box.innerHTML = '<em>Running checks...</em>';
      fetch('/modules/addons/cloudstorage/api/admin_agent_build_settings_test.php',
            {literal}{method:'POST', credentials:'same-origin'}{/literal})
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d.status !== 'success'){ box.innerHTML = '<div class="alert alert-danger">'+d.message+'</div>'; return; }
          var rows = '';
          Object.keys(d.checks).forEach(function(k){
            var c = d.checks[k];
            rows += '<tr><td>' + c.name + '</td><td>' +
                    (c.ok ? '<span class="label label-success">OK</span>' : '<span class="label label-danger">FAIL</span>') +
                    '</td><td class="eb-monospace">' + (c.detail || '') + '</td></tr>';
          });
          box.innerHTML = '<table class="table table-condensed"><thead><tr><th>Check</th><th>Result</th><th>Detail</th></tr></thead><tbody>'+rows+'</tbody></table>';
        });
    });
  </script>
{/if}

{if $tab eq 'settings' && $settings.deploy_role eq 'consumer'}
<script>
(function(){
  var btn = document.getElementById('ebDeploySyncTestBtn');
  if (!btn) return;
  btn.addEventListener('click', function(){
    var box = document.getElementById('ebDeploySyncTestResults');
    box.innerHTML = '<em>Running sync test...</em>';
    fetch('/modules/addons/cloudstorage/api/admin_agent_deploy_sync_test.php',
          {literal}{method:'POST', credentials:'same-origin'}{/literal})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.status !== 'success') { box.innerHTML = '<div class="alert alert-danger">' + (d.message||'failed') + '</div>'; return; }
        var rows = '';
        Object.keys(d.checks).forEach(function(k){
          var c = d.checks[k];
          rows += '<tr><td>' + c.name + '</td><td>' +
            (c.ok ? '<span class="label label-success">OK</span>' : '<span class="label label-danger">FAIL</span>') +
            '</td><td class="eb-monospace">' + (c.detail || '') + '</td></tr>';
        });
        box.innerHTML = '<table class="table table-condensed"><thead><tr><th>Check</th><th>Result</th><th>Detail</th></tr></thead><tbody>'+rows+'</tbody></table>';
      });
  });
})();
</script>
{/if}

{if $tab eq 'new'}
<script>
(function(){
  var form = document.getElementById('ebNewBuildForm');
  if (!form) { return; }
  var baseUrl = {literal}"{/literal}{$baseUrl|escape:'javascript'}{literal}"{/literal};
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(form);
    var msg = document.getElementById('ebNewBuildMsg');
    msg.textContent = 'Submitting...';
    fetch('/modules/addons/cloudstorage/api/admin_agent_build_create.php',
          {literal}{method:'POST', body: fd, credentials:'same-origin', headers:{'Accept':'application/json'}}{/literal})
      .then(function(r){
        var ct = (r.headers.get('content-type') || '').toLowerCase();
        if (!r.ok || ct.indexOf('application/json') === -1) {
          return r.text().then(function(t){
            throw new Error('HTTP ' + r.status + ' (' + (ct || 'no content-type') + '): ' + t.substring(0, 400));
          });
        }
        return r.json();
      })
      .then(function(d){
        if (d.status === 'success'){
          window.location = baseUrl + '&tab=detail&job_id=' + d.job_id;
        } else {
          msg.textContent = 'Error: ' + (d.message || 'unknown error');
        }
      })
      .catch(function(err){
        console.error('Agent build create failed:', err);
        msg.innerHTML = '<span class="text-danger">Request failed: ' + (err && err.message ? err.message.replace(/</g,'&lt;') : err) + '</span>';
      });
  });
})();
</script>
{/if}

</div>
