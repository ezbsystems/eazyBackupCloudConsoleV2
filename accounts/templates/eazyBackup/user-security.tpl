{assign var="activeTab" value="security"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebAccountBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Security</span>
    </div>
{/capture}

{capture name=ebAccountContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebAccountBreadcrumb
        ebPageTitle="Security"
        ebPageDescription="Manage linked accounts and two-factor authentication for your user."
    }

    {if $linkableProviders}
        <div class="eb-subpanel">
            <h3 class="eb-section-title">{lang key='remoteAuthn.titleLinkedAccounts'}</h3>
            <p class="eb-section-description">Review the identities linked to this account and add new providers when needed.</p>
            <div class="mt-5 space-y-6">
                {include file="$template/includes/linkedaccounts.tpl" linkContext="clientsecurity"}
                {include file="$template/includes/linkedaccounts.tpl" linkContext="linktable"}
            </div>
        </div>
    {/if}

    {if $twoFactorAuthAvailable}
        <div class="mt-4 eb-subpanel">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="eb-section-title">{lang key='twofactorauth'}</h3>
                    <p class="eb-section-description">Add an extra verification step to protect account access.</p>
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                        <span>{lang key='twofacurrently'}</span>
                        {if $twoFactorAuthEnabled}
                            <span class="eb-badge eb-badge--success">{lang key='enabled'|strtolower}</span>
                        {else}
                            <span class="eb-badge eb-badge--warning">{lang key='disabled'|strtolower}</span>
                        {/if}
                    </div>
                </div>

                <div class="shrink-0">
                    {if $twoFactorAuthEnabled}
                        <a href="{routePath('account-security-two-factor-disable')}" class="eb-btn eb-btn-danger open-modal twofa-config-link" data-modal-title="{lang key='twofadisable'}" data-modal-class="twofa-setup" data-btn-submit-label="{lang key='twofadisable'}" data-btn-submit-color="danger" data-btn-submit-id="btnDisable2FA">
                            {lang key='twofadisableclickhere'}
                        </a>
                    {else}
                        <a href="{routePath('account-security-two-factor-enable')}" class="eb-btn eb-btn-primary open-modal twofa-config-link" data-modal-title="{lang key='twofaenable'}" data-modal-class="twofa-setup" data-btn-submit-id="btnEnable2FA">
                            {lang key='twofaenableclickhere'}
                        </a>
                    {/if}
                </div>
            </div>

            {if $twoFactorAuthRequired}
                {include file="$template/includes/ui/eb-alert.tpl"
                    ebAlertType="warning"
                    ebAlertMessage={lang key="clientAreaSecurityTwoFactorAuthRequired"}
                    ebAlertClass="mt-6"
                }
            {/if}
        </div>
    {/if}

    {include file="$template/clientareasecurity.tpl"}

    <div id="modalAjax" class="modal system-modal whmcs-modal fade fixed inset-0 z-50" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;">
        <div class="eb-modal-backdrop absolute inset-0"></div>

        <div class="modal-dialog relative mx-auto mt-10 w-full max-w-lg px-4">
            <div class="modal-content panel-primary eb-modal">
                <div class="modal-header panel-heading eb-modal-header">
                    <h5 class="modal-title eb-modal-title"></h5>
                    <button type="button" class="close eb-modal-close" id="modalAjaxClose" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                        <span class="sr-only">{lang key='close'}</span>
                    </button>
                </div>

                <div class="modal-body panel-body eb-modal-body">
                    {lang key='loading'}
                </div>

                <div class="modal-footer panel-footer eb-modal-footer">
                    <div class="loader float-left text-sm">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        {lang key='loading'}
                    </div>
                    <button type="button" class="btn btn-default btn-close eb-btn eb-btn-ghost" data-dismiss="modal">{lang key='close'}</button>
                    <button type="button" class="btn btn-primary btn-submit eb-btn eb-btn-primary modal-submit">{lang key='submit'}</button>
                </div>
            </div>
        </div>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebAccountContent
}

<script>
jQuery(function($) {
    var modalShownForTwoFA = false;

    $('#modalAjax').on('shown.bs.modal', function() {
        modalShownForTwoFA = true;
    });

    $('#modalAjax').on('hidden.bs.modal', function() {
        if (modalShownForTwoFA) {
            window.location.reload();
        }
    });
});
</script>
