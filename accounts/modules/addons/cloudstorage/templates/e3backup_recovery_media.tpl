{capture assign=ebE3RecoveryMediaActions}
    <a href="{$portableToolUrl|escape:'html'}"
       target="_blank"
       rel="noopener"
       class="eb-btn eb-btn-info eb-btn-sm">
        Download Recovery Media Creator
    </a>
{/capture}

{capture assign=ebE3RecoveryMediaBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Recovery Media</span>
    </div>
{/capture}

{capture assign=ebE3Content}
<div x-data="recoveryMediaPage()" x-init="init()" class="space-y-6">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3RecoveryMediaBreadcrumb
        ebPageTitle='Create Recovery Media for Device'
        ebPageDescription='Build media on any Windows PC and target a specific source device for same-hardware recovery drivers.'
        ebPageActions=$ebE3RecoveryMediaActions
    }

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-4">
            <div class="eb-card">
                <div class="eb-card-header">
                    <div>
                        <div class="eb-card-title">1) Select Source Device</div>
                        <p class="eb-card-subtitle">Choose the source machine and the recovery profile to embed in the token.</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="eb-field-label">Source Agent</label>
                        <div class="relative" @click.away="sourceAgentMenuOpen = false">
                            <button type="button"
                                    @click="sourceAgentMenuOpen = !sourceAgentMenuOpen"
                                    class="eb-btn eb-btn-secondary eb-btn-sm flex w-full items-center justify-between gap-2">
                                <span class="min-w-0 truncate text-left" x-text="sourceAgentLabel()"></span>
                                <svg class="h-4 w-4 shrink-0 transition-transform" :class="sourceAgentMenuOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="sourceAgentMenuOpen"
                                 x-transition
                                 class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden"
                                 style="display: none;">
                                <div class="eb-menu-label">Select source agent</div>
                                <div class="max-h-72 overflow-auto p-1">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="isSourceAgentSelected('') ? 'is-active' : ''"
                                            @click="setSourceAgent(''); sourceAgentMenuOpen = false;">
                                        Select a source agent
                                    </button>
                                    <template x-for="agent in agents" :key="agent.agent_uuid || agent.id">
                                        <button type="button"
                                                class="eb-menu-option"
                                                :class="isSourceAgentSelected(agent.agent_uuid || '') ? 'is-active' : ''"
                                                @click="setSourceAgent(agent.agent_uuid || ''); sourceAgentMenuOpen = false;">
                                            <span x-text="agent.hostname || agent.device_name || agent.agent_uuid || 'Unknown agent'"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="eb-field-label">Build Mode</label>
                        <div class="relative" @click.away="modeMenuOpen = false">
                            <button type="button"
                                    @click="modeMenuOpen = !modeMenuOpen"
                                    class="eb-btn eb-btn-secondary eb-btn-sm flex w-full items-center justify-between gap-2">
                                <span class="min-w-0 truncate text-left" x-text="modeLabel()"></span>
                                <svg class="h-4 w-4 shrink-0 transition-transform" :class="modeMenuOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="modeMenuOpen"
                                 x-transition
                                 class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden"
                                 style="display: none;">
                                <div class="eb-menu-label">Build mode</div>
                                <div class="p-1">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="isModeSelected('fast') ? 'is-active' : ''"
                                            @click="setMode('fast'); modeMenuOpen = false;">
                                        Fast / Same Hardware
                                    </button>
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="isModeSelected('dissimilar') ? 'is-active' : ''"
                                            @click="setMode('dissimilar'); modeMenuOpen = false;">
                                        Dissimilar Hardware
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="eb-alert eb-alert--info">
                        <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25 12 11.25v5.25m0 0h.75m-.75 0h-.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <div>Fast mode prefers the latest essential source bundle, then the full source bundle, then broad extras.</div>
                    </div>
                    <div>
                        <button type="button"
                                class="eb-btn eb-btn-success eb-btn-sm"
                                @click="createToken()"
                                :disabled="busy || !sourceAgentUuid">
                            <span x-text="busy ? 'Generating...' : 'Generate Media Build Token'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="eb-card">
                <div class="eb-card-header">
                    <div>
                        <div class="eb-card-title">2) Use Token in Portable Tool</div>
                        <p class="eb-card-subtitle">Open the Recovery Media Creator on any Windows PC and paste this token.</p>
                    </div>
                </div>
                <div class="space-y-3">
                    <textarea class="eb-textarea h-36 eb-type-mono"
                              readonly
                              x-model="token"></textarea>
                    <div class="eb-type-caption" x-text="expiresText"></div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                class="eb-btn eb-btn-copy eb-btn-sm"
                                @click="copyToken()"
                                :disabled="!token">
                            Copy Token
                        </button>
                        <span class="eb-type-caption" x-text="copyStatus"></span>
                    </div>
                </div>
            </div>

            <div class="eb-alert eb-alert--warning">
                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.29 3.86l-7.5 13A1 1 0 0 0 3.66 18.5h16.68a1 1 0 0 0 .87-1.64l-7.5-13a1 1 0 0 0-1.74 0Z" />
                </svg>
                <div>If no source bundle exists yet for the selected device, the media builder falls back to the broad extras pack if one is configured.</div>
            </div>
        </div>
    </div>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='recovery_media'
    ebE3Title='Create Recovery Media for Device'
    ebE3Description='Build media on any Windows PC and target a specific source device for same-hardware recovery drivers.'
    ebE3Actions=$ebE3RecoveryMediaActions
    ebE3Content=$ebE3Content
}

{literal}
<script>
function recoveryMediaPage() {
  return {
    agents: {/literal}{if $agents}{$agents|json_encode nofilter}{else}[]{/if}{literal},
    sourceAgentUuid: '',
    sourceAgentMenuOpen: false,
    mode: 'fast',
    modeMenuOpen: false,
    token: '',
    expiresText: '',
    copyStatus: '',
    busy: false,
    init() {},
    setSourceAgent(agentUuid) {
      this.sourceAgentUuid = String(agentUuid || '');
    },
    isSourceAgentSelected(agentUuid) {
      return String(this.sourceAgentUuid || '') === String(agentUuid || '');
    },
    sourceAgentLabel() {
      if (!this.sourceAgentUuid) return 'Select a source agent';
      const match = (this.agents || []).find((agent) => String(agent.agent_uuid || '') === String(this.sourceAgentUuid));
      if (!match) return this.sourceAgentUuid;
      return match.hostname || match.device_name || match.agent_uuid || 'Unknown agent';
    },
    setMode(mode) {
      this.mode = String(mode || 'fast');
    },
    isModeSelected(mode) {
      return String(this.mode || 'fast') === String(mode || '');
    },
    modeLabel() {
      return this.mode === 'dissimilar' ? 'Dissimilar Hardware' : 'Fast / Same Hardware';
    },
    async createToken() {
      if (!this.sourceAgentUuid) return;
      this.busy = true;
      this.token = '';
      this.expiresText = '';
      this.copyStatus = '';
      try {
        const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_media_build_token_create.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            source_agent_uuid: String(this.sourceAgentUuid || '').trim(),
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

