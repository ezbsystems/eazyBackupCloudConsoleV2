{if $showSsoSetting}
    <div class="eb-subpanel">
        <h3 class="text-lg font-semibold text-slate-50">{lang key='sso.title'}</h3>

        {include file="$template/includes/ui/eb-alert.tpl"
            ebAlertType="success"
            ebAlertMessage={lang key='sso.summary'}
            ebAlertClass="mt-4"
        }

        <form id="frmSingleSignOn" class="mt-5 space-y-4">
            <input type="hidden" name="token" value="{$token}">
            <input type="hidden" name="action" value="security">
            <input type="hidden" name="toggle_sso" value="1">
            <label for="inputAllowSso" class="flex items-center gap-3 text-sm text-slate-300">
                <input type="checkbox" name="allow_sso" class="h-4 w-4 accent-orange-500" id="inputAllowSso"{if $isSsoEnabled} checked{/if}>
                <span id="ssoStatusTextEnabled"{if !$isSsoEnabled} style="display:none;"{/if}>{lang key='sso.enabled'}</span>
                <span id="ssoStatusTextDisabled"{if $isSsoEnabled} style="display:none;"{/if}>{lang key='sso.disabled'}</span>
            </label>
        </form>

        <p class="mt-4 text-sm text-slate-400">{lang key='sso.disablenotice'}</p>
    </div>
{/if}
