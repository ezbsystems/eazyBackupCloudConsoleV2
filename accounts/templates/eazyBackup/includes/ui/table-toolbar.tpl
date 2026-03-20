{assign var=ebToolbarClass value=$ebToolbarClass|default:''}

<div class="eb-table-toolbar {$ebToolbarClass}">
    <div class="flex flex-wrap items-center gap-3">
        {$ebToolbarLeft nofilter}
    </div>
    <div class="flex-1"></div>
    {if isset($ebToolbarRight) && $ebToolbarRight|trim neq ''}
        {$ebToolbarRight nofilter}
    {/if}
</div>
