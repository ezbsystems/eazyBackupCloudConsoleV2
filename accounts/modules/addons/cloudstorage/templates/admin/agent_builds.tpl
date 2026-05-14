<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
<style>{literal}
.eb-pill { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; color:#fff; }
.eb-pill.queued{background:#777} .eb-pill.running{background:#3498db} .eb-pill.succeeded{background:#27ae60}
.eb-pill.failed{background:#c0392b} .eb-pill.cancelled{background:#95a5a6} .eb-pill.skipped{background:#bdc3c7}
.eb-pill.pending{background:#7f8c8d}
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
          <label>Version Label</label>
          <input type="text" name="version_label" placeholder="auto: YYYY.MM.DD-HHMMSS" class="form-control" style="max-width:300px;">
        </div>
        <div class="checkbox"><label><input type="checkbox" name="run_tests" checked> Run go test ./...</label></div>
        <div class="checkbox"><label><input type="checkbox" name="sign" {if $settings.signing_enabled}checked{/if}> Code-sign Windows binaries (Azure KV)</label></div>
        <div class="checkbox"><label><input type="checkbox" name="publish" checked> Publish to /client_installer/</label></div>
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
      var jobId = {$job.id};
      var apiBase = '/modules/addons/cloudstorage/api/';
      var offsets = {};
      var pollDelay = ({$job.status} == 'running' || {$job.status} == 'queued') ? 2000 : 30000;

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

{if $tab eq 'settings'}
  {if $savedFlag}<div class="alert alert-success">Settings saved.</div>{/if}
  <form method="post" action="{$baseUrl}&tab=settings">
    <input type="hidden" name="cs_action" value="save_settings">
    <fieldset>
      <legend>Build Hosts &amp; Paths</legend>
      <div class="form-group"><label>Local agent repo path</label>
        <input type="text" class="form-control" name="repo_path" value="{$settings.repo_path|escape}"></div>
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

{if $tab eq 'new'}
<script>
document.getElementById('ebNewBuildForm').addEventListener('submit', function(e){
  e.preventDefault();
  var fd = new FormData(this);
  var msg = document.getElementById('ebNewBuildMsg');
  msg.textContent = 'Submitting...';
  fetch('/modules/addons/cloudstorage/api/admin_agent_build_create.php',
        {literal}{method:'POST', body: fd, credentials:'same-origin'}{/literal})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.status === 'success'){
        window.location = '{$baseUrl}&tab=detail&job_id=' + d.job_id;
      } else {
        msg.textContent = 'Error: ' + d.message;
      }
    });
});
</script>
{/if}

</div>
