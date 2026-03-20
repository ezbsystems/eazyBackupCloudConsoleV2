{assign var=ebPageHeaderClass value=$ebPageHeaderClass|default:''}

<div class="eb-page-header {$ebPageHeaderClass}">
    <div>
        {if isset($ebBreadcrumb) && $ebBreadcrumb|trim neq ''}
            {$ebBreadcrumb nofilter}
        {/if}
        <h2 class="eb-page-title">{$ebPageTitle}</h2>
        {if isset($ebPageDescription) && $ebPageDescription|trim neq ''}
            <p class="eb-page-description">{$ebPageDescription}</p>
        {/if}
    </div>
    {if isset($ebPageActions) && $ebPageActions|trim neq ''}
        <div class="shrink-0">
            {$ebPageActions nofilter}
        </div>
    {/if}
</div>
