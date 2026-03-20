{assign var=completeBrandName value=$whitelabel_product_name|default:'White Label'}
{assign var=completeDownloadHost value=$serverHostname|default:'panel.eazybackup.ca'}
{assign var=completeSelfAddress value="https://`$serverHostname|default:'panel.eazybackup.ca'`/"}
{assign var=completeClientLabel value=$whitelabel_product_name|default:'branded'}
{assign var=completeHeading value="Your new {$completeBrandName} account is ready"}
{assign var=completeIntro value='Download your branded client installer using the options below. We are here if you need any assistance.'}
{assign var=completeAccentClass value='eb-btn-primary'}
{include file="modules/addons/eazybackup/templates/partials/complete-download-shell.tpl"}
