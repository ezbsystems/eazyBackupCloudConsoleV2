<div class="min-h-screen bg-slate-950 text-gray-200" x-data="recoveryMediaPage()" x-init="init()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="recovery_media"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                        <span class="text-slate-600">/</span>
                        <span class="text-white text-sm font-medium">Recovery Media</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-white">Create Recovery Media for Device</h1>
                    <p class="text-xs text-slate-400 mt-1">Build media on any Windows PC and target a specific source device for same-hardware recovery drivers.</p>
                </div>
                <a href="{$portableToolUrl|escape:'html'}"
                   target="_blank"
                   rel="noopener"
                   class="mt-3 sm:mt-0 inline-flex items-center rounded-lg border border-sky-500/50 bg-sky-500/10 px-3 py-2 text-sm text-sky-200 hover:border-sky-400 hover:text-white transition">
                    Download Recovery Media Creator
                </a>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                        <h3 class="text-sm font-semibold text-slate-200 mb-3">1) Select Source Device</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs text-slate-400">Source Agent</label>
                                <select class="mt-1 w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100"
                                        x-model="sourceAgentId">
                                    <option value="">Select a source agent</option>
                                    {foreach $agents as $a}
                                        <option value="{$a->id}">{$a->hostname|default:"Agent #`$a->id`"}</option>
                                    {/foreach}
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-400">Build Mode</label>
                                <select class="mt-1 w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100"
                                        x-model="mode">
                                    <option value="fast">Fast / Same Hardware</option>
                                    <option value="dissimilar">Dissimilar Hardware</option>
                                </select>
                            </div>
                            <div class="text-xs text-slate-400 rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                                Fast mode prefers latest essential source bundle, then full source bundle, then broad extras.
                            </div>
                            <button type="button"
                                    class="inline-flex items-center rounded-lg border border-emerald-500/50 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200 hover:border-emerald-400 hover:text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    @click="createToken()"
                                    :disabled="busy || !sourceAgentId">
                                <span x-text="busy ? 'Generating...' : 'Generate Media Build Token'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                        <h3 class="text-sm font-semibold text-slate-200 mb-3">2) Use Token in Portable Tool</h3>
                        <p class="text-xs text-slate-400 mb-2">Open the Recovery Media Creator on any Windows PC and paste this token.</p>
                        <textarea class="w-full h-36 bg-slate-950 border border-slate-700 rounded-lg p-3 text-xs font-mono text-slate-200"
                                  readonly
                                  x-model="token"></textarea>
                        <div class="mt-2 text-xs text-slate-400" x-text="expiresText"></div>
                        <div class="mt-3 flex items-center gap-2">
                            <button type="button"
                                    class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs text-slate-200 hover:border-slate-500"
                                    @click="copyToken()"
                                    :disabled="!token">
                                Copy Token
                            </button>
                            <span class="text-xs text-slate-500" x-text="copyStatus"></span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-amber-600/40 bg-amber-700/10 p-4 text-xs text-amber-200">
                        If no source bundle exists yet for the selected device, the media builder falls back to the broad extras pack (if configured).
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
function recoveryMediaPage() {
  return {
    sourceAgentId: '',
    mode: 'fast',
    token: '',
    expiresText: '',
    copyStatus: '',
    busy: false,
    init() {},
    async createToken() {
      if (!this.sourceAgentId) return;
      this.busy = true;
      this.token = '';
      this.expiresText = '';
      this.copyStatus = '';
      try {
        const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_media_build_token_create.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            source_agent_id: Number(this.sourceAgentId),
            mode: this.mode,
            ttl_minutes: 30
          })
        });
        const data = await resp.json();
        if (data.status !== 'success') {
          throw new Error(data.message || 'Failed to generate token');
        }
        this.token = data.token || '';
        this.expiresText = data.expires_at ? ('Expires: ' + data.expires_at + ' UTC') : '';
      } catch (err) {
        alert(err && err.message ? err.message : 'Failed to generate token');
      } finally {
        this.busy = false;
      }
    },
    async copyToken() {
      if (!this.token) return;
      this.copyStatus = '';
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(this.token);
          this.copyStatus = 'Copied';
          return;
        }
        const el = document.createElement('textarea');
        el.value = this.token;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        this.copyStatus = 'Copied';
      } catch (e) {
        this.copyStatus = 'Copy failed';
      }
    }
  };
}
</script>
{/literal}

