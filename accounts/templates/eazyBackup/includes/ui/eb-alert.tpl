{assign var=ebAlertType value=$ebAlertType|default:'info'}
{assign var=ebAlertClass value=$ebAlertClass|default:''}

<div class="eb-alert eb-alert--{$ebAlertType} {$ebAlertClass}{if $ebAlertCentered|default:false} text-center{/if}{if $hide|default:false} hidden{/if}"{if $idname|default:''} id="{$idname}"{/if}>
    <div class="min-w-0 flex-1">
        {if $ebAlertTitle|default:''}
            <p class="eb-alert-title">{$ebAlertTitle}</p>
        {/if}
        {if $errorshtml|default:''}
            <strong class="font-semibold">{lang key='clientareaerrors'}</strong>
            <ul class="mt-2 list-disc list-inside">
                {$errorshtml nofilter}
            </ul>
        {else}
            {$ebAlertMessage nofilter}
        {/if}
    </div>
</div>
