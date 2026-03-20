{assign var=ebPageClass value=$ebPageClass|default:''}
{assign var=ebInnerClass value=$ebInnerClass|default:''}
{assign var=ebPanelClass value=$ebPanelClass|default:''}
{assign var=ebPageNavClass value=$ebPageNavClass|default:''}

<div class="eb-page {$ebPageClass}">
    <div class="eb-page-inner {$ebInnerClass}">
        <div class="eb-panel {$ebPanelClass}">
            {if isset($ebPageNav) && $ebPageNav|trim neq ''}
                <div class="eb-panel-nav {$ebPageNavClass}">
                    {$ebPageNav nofilter}
                </div>
            {/if}
            {$ebPageContent nofilter}
        </div>
    </div>
</div>
