{assign var=ebE3SidebarPage value=$ebE3SidebarPage|default:$activeNav|default:''}
{assign var=ebE3PanelClass value=$ebE3PanelClass|default:''}
{assign var=ebE3HeaderClass value=$ebE3HeaderClass|default:''}
{assign var=ebE3BodyClass value=$ebE3BodyClass|default:''}
{assign var=ebE3Title value=$ebE3Title|default:''}
{assign var=ebE3TitleHtml value=$ebE3TitleHtml|default:''}
{assign var=ebE3Description value=$ebE3Description|default:''}
{assign var=ebE3Icon value=$ebE3Icon|default:''}
{assign var=ebE3Actions value=$ebE3Actions|default:''}
{assign var=ebE3Content value=$ebE3Content|default:''}

{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="eb-page">
    <div class="eb-page-inner">
        <div x-data="{
            sidebarCollapsed: localStorage.getItem('eb_e3_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
            toggleCollapse() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('eb_e3_sidebar_collapsed', this.sidebarCollapsed);
            },
            handleResize() {
                if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true;
            }
        }"
        x-init="window.addEventListener('resize', () => handleResize())"
        class="eb-panel !p-0 {$ebE3PanelClass}">
            <div class="eb-app-shell">
                {include file="modules/addons/cloudstorage/templates/partials/e3backup_sidebar.tpl" activeNav=$ebE3SidebarPage isMspClient=$isMspClient|default:false}
                <main class="eb-app-main">
                    {if $ebE3TitleHtml|trim neq '' || $ebE3Title|trim neq '' || $ebE3Icon|trim neq '' || $ebE3Actions|trim neq ''}
                        <div class="eb-app-header {$ebE3HeaderClass}">
                            {if $ebE3TitleHtml|trim neq ''}
                            <div class="eb-app-header-copy min-w-0 flex-1 !items-start">
                                {$ebE3TitleHtml nofilter}
                            </div>
                            {else}
                            <div class="eb-app-header-copy">
                                {if $ebE3Icon|trim neq ''}
                                    {$ebE3Icon nofilter}
                                {/if}
                                <div class="min-w-0">
                                    <h1 class="eb-app-header-title">{$ebE3Title}</h1>
                                    {if $ebE3Description|trim neq ''}
                                        <p class="eb-page-description !mt-1">{$ebE3Description}</p>
                                    {/if}
                                </div>
                            </div>
                            {/if}
                            {if $ebE3Actions|trim neq ''}
                                <div class="w-full lg:w-auto lg:shrink-0">
                                    {$ebE3Actions nofilter}
                                </div>
                            {/if}
                        </div>
                    {/if}
                    <div class="eb-app-body {$ebE3BodyClass}">
                        {$ebE3Content nofilter}
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>

{* Download Agent flyout — moved to document.body via JS to escape eb-theme-main stacking context *}
<div id="e3-download-flyout" class="eb-sidebar-flyout" aria-hidden="true">
    <div class="flex flex-col h-full">
        <div class="eb-sidebar-flyout-header flex h-16 items-center justify-between px-4 py-3">
            <h2 class="text-lg font-semibold" style="color:var(--eb-text-primary);">Download Agent</h2>
            <button id="e3-download-flyout-close" class="eb-btn eb-btn-secondary eb-btn-xs" aria-label="Close download drawer">
                Close
            </button>
        </div>
        <div class="eb-sidebar-flyout-body flex-1 overflow-y-auto p-4">
            <div class="space-y-4">
                <p class="text-sm" style="color:var(--eb-text-muted);">Download the e3 Backup Agent for your operating system.</p>
                <a href="/client_installer/e3-backup-agent-setup.exe" target="_blank" rel="noopener" class="eb-btn eb-btn-primary eb-btn-md w-full justify-center">
                    Windows Agent
                </a>
                <a href="/client_installer/e3-backup-agent-linux" target="_blank" rel="noopener" class="eb-btn eb-btn-secondary eb-btn-md w-full justify-center">
                    Linux Agent
                </a>
                <div class="eb-card !p-4">
                    <p class="text-sm" style="color:var(--eb-text-secondary);">
                        Need an enrollment token after download?
                    </p>
                    <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="mt-3 inline-flex text-sm font-medium" style="color:var(--eb-info-text);">
                        Open enrollment tokens
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var flyout = document.getElementById('e3-download-flyout');
    var closeBtn = document.getElementById('e3-download-flyout-close');
    if (!flyout) return;

    document.body.appendChild(flyout);

    function openFlyout() {
        flyout.classList.add('is-open');
        flyout.setAttribute('aria-hidden', 'false');
    }

    function closeFlyout() {
        flyout.classList.remove('is-open');
        flyout.setAttribute('aria-hidden', 'true');
    }

    window.addEventListener('open-e3-download-flyout', openFlyout);
    if (closeBtn) closeBtn.addEventListener('click', closeFlyout);

    flyout.querySelectorAll('a[href]').forEach(function(link) {
        link.addEventListener('click', closeFlyout);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && flyout.classList.contains('is-open')) closeFlyout();
    });
});
</script>
