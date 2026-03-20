{* Minimal shell for Stripe Connect embedded account management *}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhActions}
    <a href="{$modulelink}&a=ph-stripe-connect" class="eb-btn eb-btn-secondary eb-btn-sm">Back to Status</a>
{/capture}

{capture assign=ebPhContent}
    <section class="eb-subpanel">
        <div class="mb-4">
            <h2 class="eb-app-card-title">Embedded Account Management</h2>
            <p class="eb-field-help">Open Stripe's embedded account tools without leaving Partner Hub.</p>
        </div>
        <div id="stripe-embedded-account"
             data-endpoint="{$modulelink|escape}&a=ph-stripe-account-session"
             data-connect-link="{$modulelink|escape}&a=ph-stripe-connect"
             data-manage-link="{$modulelink|escape}&a=ph-stripe-manage-redirect"
             class="eb-card-raised"></div>
    </section>
    <script src="modules/addons/eazybackup/assets/js/stripe-account-manage.js"></script>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
    ebPhSidebarPage='stripe-manage'
    ebPhTitle='Manage Stripe Account'
    ebPhDescription='Use the embedded management to update bank details, business profile, and ownership.'
    ebPhActions=$ebPhActions
    ebPhContent=$ebPhContent
}
