{\WHMCS\View\Asset::fontCssInclude('open-sans-family.css')}
{* <link href="{assetPath file='all.min.css'}?v={$versionHash}" rel="stylesheet"> *}
{* <link href="{assetPath file='theme.min.css'}?v={$versionHash}" rel="stylesheet"> *}
{* <link href="{$WEB_ROOT}/assets/css/fontawesome-all.min.css" rel="stylesheet"> *}

<script src="https://cdn.tailwindcss.com"></script>

<script src="modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js" defer></script>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
<script defer src="/modules/addons/eazybackup/assets/js/email-reports.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>window.jQuery||document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"><\\/script>')</script>
<script>window.jQuery||document.write('<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/jquery.min.js"><\\/script>')</script>

{assetExists file="custom.css"}
<link href="{$__assetPath__}" rel="stylesheet">
{/assetExists}

<link href="{$WEB_ROOT}/templates/{$template}/css/custom.css" rel="stylesheet">

<script>
    var csrfToken = '{$token}',
        markdownGuide = '{lang|addslashes key="markdown.title"}',
        locale = '{if !empty($mdeLocale)}{$mdeLocale}{else}en{/if}',
        saved = '{lang|addslashes key="markdown.saved"}',
        saving = '{lang|addslashes key="markdown.saving"}',
        whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
    {if $captcha}{$captcha->getPageJs()}{/if}
</script>
<script src="{assetPath file='scripts.min.js'}?v={$versionHash}"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/services.js?v={$versionHash}"></script>

{if $templatefile == "viewticket" && !$loggedin}
  <meta name="robots" content="noindex" />
{/if}
