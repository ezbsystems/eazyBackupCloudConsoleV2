{assign var=ebPhSidebarPage value=$ebPhSidebarPage|default:''}
{assign var=ebPhPanelClass value=$ebPhPanelClass|default:''}
{assign var=ebPhHeaderClass value=$ebPhHeaderClass|default:''}
{assign var=ebPhBodyClass value=$ebPhBodyClass|default:''}
{assign var=ebPhTitle value=$ebPhTitle|default:''}
{assign var=ebPhDescription value=$ebPhDescription|default:''}
{assign var=ebPhIcon value=$ebPhIcon|default:''}

<div class="eb-page">
    <div class="eb-page-inner">
        <div x-data="{
            sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
            toggleCollapse() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed);
            },
            handleResize() {
                if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true;
            }
        }"
        x-init="window.addEventListener('resize', () => handleResize())"
        class="eb-panel !p-0 {$ebPhPanelClass}">
            <div class="eb-app-shell">
                {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage=$ebPhSidebarPage}
                <main class="eb-app-main">
                    {if $ebPhTitle|trim neq '' || ($ebPhActions|default:''|trim neq '')}
                        <div class="eb-app-header {$ebPhHeaderClass}">
                            <div class="eb-app-header-copy">
                                {if $ebPhIcon|trim neq ''}
                                    {$ebPhIcon nofilter}
                                {/if}
                                <div>
                                    <h1 class="eb-app-header-title">{$ebPhTitle}</h1>
                                    {if $ebPhDescription|trim neq ''}
                                        <p class="eb-page-description !mt-1">{$ebPhDescription}</p>
                                    {/if}
                                </div>
                            </div>
                            {if $ebPhActions|default:''|trim neq ''}
                                <div class="w-full lg:w-auto lg:shrink-0">
                                    {$ebPhActions nofilter}
                                </div>
                            {/if}
                        </div>
                    {/if}
                    <div class="eb-app-body {$ebPhBodyClass}">
                        {$ebPhContent nofilter}
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>
