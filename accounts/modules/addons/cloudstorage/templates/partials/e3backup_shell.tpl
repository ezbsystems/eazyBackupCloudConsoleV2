{assign var=ebE3SidebarPage value=$ebE3SidebarPage|default:$activeNav|default:''}
{assign var=ebE3PanelClass value=$ebE3PanelClass|default:''}
{assign var=ebE3HeaderClass value=$ebE3HeaderClass|default:''}
{assign var=ebE3BodyClass value=$ebE3BodyClass|default:''}
{assign var=ebE3Title value=$ebE3Title|default:''}
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
                    {if $ebE3Title|trim neq '' || $ebE3Actions|trim neq ''}
                        <div class="eb-app-header {$ebE3HeaderClass}">
                            <div class="eb-app-header-copy">
                                {if $ebE3Icon|trim neq ''}
                                    {$ebE3Icon nofilter}
                                {/if}
                                <div>
                                    <h1 class="eb-app-header-title">{$ebE3Title}</h1>
                                    {if $ebE3Description|trim neq ''}
                                        <p class="eb-page-description !mt-1">{$ebE3Description}</p>
                                    {/if}
                                </div>
                            </div>
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
