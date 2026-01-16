<div class="min-h-screen bg-slate-950 text-gray-200" x-data="tokensApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="tokens"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        {* Glass panel container *}
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Enrollment Tokens</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Enrollment Tokens</h1>
                <p class="text-xs text-slate-400 mt-1">Generate tokens for agent enrollment. Use these for silent deployment via RMM tools.</p>
            </div>
            <button @click="showCreateModal = true" class="mt-4 sm:mt-0 px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-500">
                + Generate Token
            </button>
        </div>

        <!-- Tokens Table -->
        <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Token</th>
                        <th class="px-4 py-3 text-left font-medium">Description</th>
                        {if $isMspClient}
                        <th class="px-4 py-3 text-left font-medium">Tenant</th>
                        {/if}
                        <th class="px-4 py-3 text-left font-medium">Uses</th>
                        <th class="px-4 py-3 text-left font-medium">Expires</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <template x-if="loading">
                        <tr>
                            <td colspan="{if $isMspClient}7{else}6{/if}" class="px-4 py-8 text-center text-slate-400">
                                <svg class="animate-spin h-6 w-6 mx-auto text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && tokens.length === 0">
                        <tr>
                            <td colspan="{if $isMspClient}7{else}6{/if}" class="px-4 py-8 text-center text-slate-400">
                                No enrollment tokens yet. Click "Generate Token" to create one.
                            </td>
                        </tr>
                    </template>
                    <template x-for="token in tokens" :key="token.id">
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <code class="text-xs bg-slate-800 px-2 py-1 rounded font-mono text-emerald-300" x-text="token.token"></code>
                                    <button @click="copyToken(token.token)" class="text-slate-400 hover:text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-300" x-text="token.description || '—'"></td>
                            {if $isMspClient}
                            <td class="px-4 py-3 text-slate-300" x-text="token.tenant_name || 'All / Direct'"></td>
                            {/if}
                            <td class="px-4 py-3 text-slate-300">
                                <span x-text="token.use_count"></span>/<span x-text="token.max_uses || '∞'"></span>
                            </td>
                            <td class="px-4 py-3 text-slate-300" x-text="token.expires_at || 'Never'"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                      :class="token.is_valid ? 'bg-emerald-500/15 text-emerald-200' : 'bg-rose-500/15 text-rose-200'">
                                    <span x-text="token.is_valid ? 'Active' : (token.revoked_at ? 'Revoked' : 'Expired')"></span>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <button @click="showInstallCmd(token)" class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 hover:border-slate-500">Install Cmd</button>
                                    <button @click="revokeToken(token)" x-show="token.is_valid" class="text-xs px-2 py-1 rounded bg-rose-900/50 border border-rose-700 hover:border-rose-500 text-rose-200">Revoke</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Token Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="showCreateModal = false">
        <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Generate Enrollment Token</h3>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form @submit.prevent="createToken()" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Description (optional)</label>
                    <input type="text" x-model="newToken.description" placeholder="e.g., December rollout" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                
                {if $isMspClient}
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Scope to Tenant (optional)</label>
                    <select x-model="newToken.tenant_id" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">All / Direct</option>
                        {foreach from=$tenants item=tenant}
                        <option value="{$tenant->id}">{$tenant->name|escape}</option>
                        {/foreach}
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Agents enrolled with this token will be assigned to the selected tenant.</p>
                </div>
                {/if}

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Max Uses</label>
                    <div class="flex items-center gap-3">
                        <input type="number" x-model="newToken.max_uses" min="0" placeholder="0 = unlimited" class="w-32 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <span class="text-xs text-slate-500">0 = unlimited</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Expires After</label>
                    <select x-model="newToken.expires_in" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Never</option>
                        <option value="24h">24 hours</option>
                        <option value="7d">7 days</option>
                        <option value="30d">30 days</option>
                        <option value="90d">90 days</option>
                        <option value="1y">1 year</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-500" :disabled="creating">
                        <span x-show="!creating">Generate Token</span>
                        <span x-show="creating">Generating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Install Command Modal -->
    <div x-show="showInstallModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="showInstallModal = false">
        <div class="w-full max-w-2xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Silent Install Command</h3>
                <button @click="showInstallModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-slate-400">Use this command to silently install the backup agent with your enrollment token:</p>
                
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1 uppercase tracking-wide">Windows (CMD/PowerShell)</label>
                    <div class="relative">
                        <pre class="bg-slate-800 rounded-lg p-3 text-xs text-emerald-300 font-mono overflow-x-auto" x-text="installCmd"></pre>
                        <button @click="copyInstallCmd()" class="absolute top-2 right-2 text-xs px-2 py-1 rounded bg-slate-700 text-slate-300 hover:bg-slate-600">Copy</button>
                    </div>
                </div>

                <div class="text-xs text-slate-500 space-y-1">
                    <p><strong>Note:</strong> Download the agent installer from the Agents page before running this command.</p>
                    <p>The agent will automatically register with your account using the embedded token.</p>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
</div>

{literal}
<script>
function tokensApp() {
    return {
        tokens: [],
        loading: true,
        showCreateModal: false,
        showInstallModal: false,
        creating: false,
        installCmd: '',
        newToken: {
            description: '',
            tenant_id: '',
            max_uses: 0,
            expires_in: '7d'
        },
        
        init() {
            this.loadTokens();
        },
        
        async loadTokens() {
            this.loading = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_token_list.php');
                const data = await res.json();
                if (data.status === 'success') {
                    this.tokens = data.tokens || [];
                } else {
                    console.error(data.message);
                }
            } catch (e) {
                console.error('Failed to load tokens:', e);
            }
            this.loading = false;
        },
        
        async createToken() {
            this.creating = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_token_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        description: this.newToken.description,
                        tenant_id: this.newToken.tenant_id,
                        max_uses: this.newToken.max_uses,
                        expires_in: this.newToken.expires_in
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showCreateModal = false;
                    this.newToken = { description: '', tenant_id: '', max_uses: 0, expires_in: '7d' };
                    this.loadTokens();
                    // Show the install command for the new token
                    this.installCmd = `e3-backup-agent.exe /S /TOKEN=${data.token}`;
                    this.showInstallModal = true;
                } else {
                    alert(data.message || 'Failed to create token');
                }
            } catch (e) {
                alert('Failed to create token');
            }
            this.creating = false;
        },
        
        async revokeToken(token) {
            if (!confirm('Revoke this token? Agents already enrolled will continue to work.')) return;
            
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_token_revoke.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ token_id: token.id })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.loadTokens();
                } else {
                    alert(data.message || 'Failed to revoke token');
                }
            } catch (e) {
                alert('Failed to revoke token');
            }
        },
        
        showInstallCmd(token) {
            this.installCmd = `e3-backup-agent.exe /S /TOKEN=${token.token}`;
            this.showInstallModal = true;
        },
        
        copyToken(token) {
            navigator.clipboard.writeText(token).then(() => {
                // Could add a toast notification here
            });
        },
        
        copyInstallCmd() {
            navigator.clipboard.writeText(this.installCmd);
        }
    };
}
</script>
{/literal}

