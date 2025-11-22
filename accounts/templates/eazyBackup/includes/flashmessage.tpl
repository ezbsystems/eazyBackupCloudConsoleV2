{if $message = get_flash_message()}
    <div class="mb-4 p-4 rounded-md
        {if $message.type == "error"} bg-red-50 border border-red-200 text-sm text-red-800
        {elseif $message.type == "success"} bg-green-50 border border-green-200 text-sm text-green-800
        {elseif $message.type == "warning"} bg-yellow-50 border border-yellow-200 text-sm text-yellow-800
        {else} bg-blue-50 border border-blue-200 text-sm text-blue-800{/if}">
        <p>{$message.text}</p>
    </div>
{/if}

