{if $message = get_flash_message()}
    <div class="mb-4 p-4 rounded-md
        {if $message.type == "error"} bg-red-700 border-red-700 text-gray-100
        {elseif $message.type == "success"} bg-green-700 border-green-700 text-gray-100
        {elseif $message.type == "warning"} bg-yellow-700 border-yellow-700 text-gray-100
        {else} bg-blue-700 border-blue-700 text-gray-100 {/if}">
        <p>{$message.text}</p>
    </div>
{/if}

