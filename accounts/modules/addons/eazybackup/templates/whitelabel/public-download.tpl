{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebAuthContent}
  <div class="space-y-6">
    <div class="text-center">
      <h1 class="eb-auth-title">Download Client Software</h1>
      <p class="eb-auth-description">Choose your platform to download the MSP-branded backup client.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="eb-card space-y-3">
        <h2 class="eb-app-card-title">Windows</h2>
        <div class="flex flex-wrap gap-2">
          <a class="eb-btn eb-btn-primary eb-btn-sm" href="{$dl.win_any|escape}">Any CPU</a>
          <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{$dl.win_x64|escape}">x86_64 Only</a>
          <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{$dl.win_x86|escape}">x86_32 Only</a>
        </div>
      </div>
      <div class="eb-card space-y-3">
        <h2 class="eb-app-card-title">Linux</h2>
        <div class="flex flex-wrap gap-2">
          <a class="eb-btn eb-btn-primary eb-btn-sm" href="{$dl.linux_deb|escape}">.deb</a>
          <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{$dl.linux_tgz|escape}">.tar.gz</a>
        </div>
      </div>
      <div class="eb-card space-y-3">
        <h2 class="eb-app-card-title">macOS</h2>
        <div class="flex flex-wrap gap-2">
          <a class="eb-btn eb-btn-primary eb-btn-sm" href="{$dl.mac_x64|escape}">x86_64</a>
          <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{$dl.mac_arm|escape}">Apple Silicon</a>
        </div>
      </div>
      <div class="eb-card space-y-3">
        <h2 class="eb-app-card-title">Synology</h2>
        <div class="flex flex-wrap gap-2">
          <a class="eb-btn eb-btn-primary eb-btn-sm" href="{$dl.syn_dsm6|escape}">DSM 6</a>
          <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{$dl.syn_dsm7|escape}">DSM 7</a>
        </div>
      </div>
    </div>
  </div>
{/capture}

{include file="$template/includes/ui/auth-shell.tpl"
  ebAuthWrapClass='!max-w-4xl'
  ebAuthCardClass='!px-8 !py-8'
  ebAuthContent=$ebAuthContent
}


