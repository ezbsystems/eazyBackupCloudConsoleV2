<div 
    class="
        rounded-md 
        p-2 
        {if $type eq "error"}bg-red-700 border border-red-700 text-white mb-6
        {elseif $type eq "success"}bg-green-700 border border-green-700 text-white mb-6
        {elseif $type eq "info"}bg-blue-700 border border-blue-700 text-white mb-6
        {else}bg-gray-700 border border-gray-700 text-white mb-6{/if}
        {if $textcenter} text-center{/if}
        {if $additionalClasses} {$additionalClasses}{/if}
        {if $hide} hidden{/if}
    "
    {if $idname} id="{$idname}"{/if}
>
    {if $errorshtml}
        <strong class="font-semibold">{lang key='clientareaerrors'}</strong>
        <ul class="mt-2 list-disc list-inside">
            {$errorshtml}
        </ul>
    {else}
        {if $title}
            <h2 class="text-lg font-semibold mb-2">{$title}</h2>
        {/if}
        <p>{$msg}</p>
    {/if}
</div>
