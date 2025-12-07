<div class="min-h-screen bg-slate-950 text-gray-200">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        <!-- Navigation Tabs -->
        <div class="mb-6">
            <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Cloud Backup Navigation">
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_jobs' || empty($smarty.get.view)}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Jobs
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_runs'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Run History
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_settings"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_settings'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Settings
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_agents"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_agents'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Agents
                </a>
            </nav>
        </div>
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Backup Agents</h1>
                <p class="text-xs text-slate-400 mt-1">Manage agent tokens and download the Windows agent.</p>
            </div>
            <a href="/client_installer/e3-backup-agent.exe" class="px-4 py-2 rounded-md bg-sky-600 text-white text-sm font-semibold hover:bg-sky-500" target="_blank" rel="noopener">
                Download Windows Agent
            </a>
        </div>
        <div class="mb-4 flex gap-2">
            <input id="agentHostname" type="text" placeholder="Hostname (optional)" class="w-64 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <button class="px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-500" onclick="createAgent()">Create Agent</button>
        </div>
        <div id="agentConfCard" class="hidden mb-4 rounded-lg border border-slate-700 bg-slate-900 p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-slate-100">Agent Config (agent.conf)</h3>
                <button class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-600" onclick="copyAgentConf()">Copy</button>
            </div>
            <pre id="agentConfText" class="text-xs whitespace-pre-wrap text-slate-100"></pre>
        </div>
        <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                    <tr>
                        <th class="px-4 py-2 text-left">ID</th>
                        <th class="px-4 py-2 text-left">Hostname</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Last Seen</th>
                        <th class="px-4 py-2 text-left">Created</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody id="agentsBody" class="divide-y divide-slate-800">
                </tbody>
            </table>
        </div>
    </div>
</div>

{literal}
<script>
async function loadAgents() {
    const res = await fetch('modules/addons/cloudstorage/api/agent_list.php');
    const data = await res.json();
    if (data.status !== 'success') {
        alert(data.message || 'Failed to load agents');
        return;
    }
    const body = document.getElementById('agentsBody');
    body.innerHTML = '';
    (data.agents || []).forEach(a => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="px-4 py-2 text-slate-200">${a.id}</td>
            <td class="px-4 py-2 text-slate-200">${a.hostname || ''}</td>
            <td class="px-4 py-2">
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${a.status==='active'?'bg-emerald-500/15 text-emerald-200':'bg-slate-700 text-slate-300'}">
                    ${a.status}
                </span>
            </td>
            <td class="px-4 py-2 text-slate-300">${a.last_seen_at || '—'}</td>
            <td class="px-4 py-2 text-slate-300">${a.created_at || ''}</td>
            <td class="px-4 py-2">
                <button class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 hover:border-slate-500" onclick="disableAgent(${a.id}, false)">Disable</button>
                <button class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 hover:border-slate-500 ml-1" onclick="disableAgent(${a.id}, true)">Revoke</button>
            </td>
        `;
        body.appendChild(tr);
    });
}

async function createAgent() {
    const hostname = document.getElementById('agentHostname').value || '';
    const res = await fetch('modules/addons/cloudstorage/api/agent_create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ hostname })
    });
    const data = await res.json();
    if (data.status !== 'success') {
        alert(data.message || 'Failed to create agent');
        return;
    }
    const confText = document.getElementById('agentConfText');
    confText.textContent = JSON.stringify(data.agent_conf || {}, null, 2);
    document.getElementById('agentConfCard').classList.remove('hidden');
    loadAgents();
}

async function disableAgent(agentId, revoke=false) {
    if (!confirm(revoke ? 'Disable and revoke token for this agent?' : 'Disable this agent?')) return;
    const res = await fetch('modules/addons/cloudstorage/api/agent_disable.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ agent_id: agentId, revoke: revoke ? '1':'0' })
    });
    const data = await res.json();
    if (data.status !== 'success') {
        alert(data.message || 'Failed to disable agent');
        return;
    }
    loadAgents();
}

function copyAgentConf() {
    const text = document.getElementById('agentConfText').textContent || '';
    navigator.clipboard.writeText(text).then(() => {
        alert('Config copied to clipboard');
    }).catch(() => alert('Copy failed'));
}

document.addEventListener('DOMContentLoaded', loadAgents);
</script>
{/literal}

