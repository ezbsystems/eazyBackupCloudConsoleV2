{if $message = get_flash_message()}
    {if $message.type == "error"}
        {assign var=ebFlashType value="danger"}
    {elseif $message.type == "success"}
        {assign var=ebFlashType value="success"}
    {elseif $message.type == "warning"}
        {assign var=ebFlashType value="warning"}
    {else}
        {assign var=ebFlashType value="info"}
    {/if}

    {include file="$template/includes/ui/eb-alert.tpl"
        ebAlertType=$ebFlashType
        ebAlertMessage=$message.text
    }
{/if}
