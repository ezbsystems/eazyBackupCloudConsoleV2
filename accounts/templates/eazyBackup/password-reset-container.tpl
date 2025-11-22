<div class="flex justify-center items-center min-h-screen bg-gray-700">
  
        <div class="bg-gray-800 rounded-lg shadow p-8 max-w-md w-full">
            {if $loggedin && $innerTemplate}
                {include file="$template/includes/alert-darkmode.tpl" type="error" msg="{lang key='noPasswordResetWhenLoggedIn'}" textcenter=true}
            {else}
                {if $successMessage}
                    {include file="$template/includes/alert-darkmode.tpl" type="success" msg=$successTitle textcenter=true}
                    <p class="text-gray-300 text-sm text-center">{$successMessage}</p>
                {else}
                    {if $innerTemplate}
                        {include file="$template/password-reset-$innerTemplate.tpl"}
                    {/if}
                {/if}
            {/if}
        </div>
    
</div>
