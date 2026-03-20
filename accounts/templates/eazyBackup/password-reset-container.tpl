{capture name=ebPasswordResetContent}
    {if $loggedin && $innerTemplate}
        {include file="$template/includes/alert-darkmode.tpl" type="error" msg="{lang key='noPasswordResetWhenLoggedIn'}" textcenter=true}
    {else}
        {if $successMessage}
            {include file="$template/includes/alert-darkmode.tpl" type="success" msg=$successTitle textcenter=true}
            <p class="eb-type-body text-center">{$successMessage}</p>
        {else}
            {if $innerTemplate}
                {include file="$template/password-reset-$innerTemplate.tpl"}
            {/if}
        {/if}
    {/if}
{/capture}

{include file="$template/includes/ui/auth-shell.tpl"
    ebAuthWrapClass="max-w-md"
    ebAuthContent=$smarty.capture.ebPasswordResetContent
}
