{assign var="activeTab" value="password"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebAccountBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Change Password</span>
    </div>
{/capture}

{capture name=ebAccountContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebAccountBreadcrumb
        ebPageTitle="Change Password"
        ebPageDescription="Update your password and keep your account credentials current."
    }

    {include file="$template/includes/flashmessage-darkmode.tpl"}

    <div class="eb-subpanel password-form-container">
        <form class="space-y-6 using-password-strength" method="post" action="{routePath('user-password')}" role="form">
            <input type="hidden" name="submit" value="true">

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="inputExistingPassword" class="eb-field-label">{lang key='existingpassword'}</label>
                    <input type="password" name="existingpw" id="inputExistingPassword" autocomplete="off" class="eb-input">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div id="newPassword1">
                    <label for="inputNewPassword1" class="eb-field-label">{lang key='newpassword'}</label>
                    <input type="password" name="newpw" id="inputNewPassword1" autocomplete="off" class="eb-input">
                </div>

                <div id="newPassword2">
                    <label for="inputNewPassword2" class="eb-field-label">{lang key='confirmnewpassword'}</label>
                    <input type="password" name="confirmpw" id="inputNewPassword2" autocomplete="off" class="eb-input">
                    <div id="inputNewPassword2Msg" class="eb-choice-card-description mt-2"></div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <button type="reset" class="eb-btn eb-btn-ghost">{lang key='cancel'}</button>
                <button type="submit" class="eb-btn eb-btn-primary">{lang key='clientareasavechanges'}</button>
            </div>
        </form>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebAccountContent
}

<script>
function sendHeight() {
    var body = document.body,
        html = document.documentElement;

    var height = Math.max(
        body.scrollHeight, body.offsetHeight,
        html.clientHeight, html.scrollHeight, html.offsetHeight
    );

    window.parent.postMessage(height, '*');
}

document.addEventListener('DOMContentLoaded', function() {
    sendHeight();
});

var observer = new MutationObserver(function() {
    sendHeight();
});

var targetNode = document.querySelector('.password-form-container');
if (targetNode) {
    observer.observe(targetNode, { attributes: true, childList: true, subtree: true });
}
</script>
