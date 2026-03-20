{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebAuthContent}
  <div class="text-center">
    <h1 class="eb-auth-title">Signup Unavailable</h1>
    <p class="eb-auth-description">
      {if $reason=='invalid_host'}
        This signup URL is not enabled. Please contact your service provider for the correct link.
      {else}
        Public signup is currently disabled.
      {/if}
    </p>
  </div>
{/capture}

{include file="$template/includes/ui/auth-shell.tpl"
  ebAuthContent=$ebAuthContent
}


