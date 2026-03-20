{assign var=ebFieldId value=$ebFieldId|default:''}
{assign var=ebFieldWrapperClass value=$ebFieldWrapperClass|default:''}

<div class="{$ebFieldWrapperClass}">
    {if $ebFieldLabel|default:''}
        <label{if $ebFieldId} for="{$ebFieldId}"{/if} class="eb-field-label">{$ebFieldLabel}</label>
    {/if}
    {$ebFieldControl nofilter}
    {if $ebFieldHelp|default:''}
        <p class="eb-field-help">{$ebFieldHelp}</p>
    {/if}
</div>
