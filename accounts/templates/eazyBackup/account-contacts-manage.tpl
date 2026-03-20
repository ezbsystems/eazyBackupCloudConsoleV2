<script src="{$BASE_PATH_JS}/StatesDropdown.js"></script>

{assign var="activeTab" value="contactsmanage"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebAccountBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Contacts</span>
    </div>
{/capture}

{capture name=ebAccountContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebAccountBreadcrumb
        ebPageTitle="Contacts"
        ebPageDescription="Manage saved contacts, notification preferences, and billing details."
    }

    <div class="eb-subpanel">
        <form role="form" method="post" action="{routePath('account-contacts')}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="inputContactId" class="eb-field-label">{lang key='clientareachoosecontact'}</label>
                <select name="contactid" id="inputContactId" onchange="submit()" class="eb-select">
                    {foreach $contacts as $contact}
                        <option value="{$contact.id}"{if $contact.id eq $contactid} selected="selected"{/if}>{$contact.name} - {$contact.email}</option>
                    {/foreach}
                    <option value="new">{lang key='clientareanavaddcontact'}</option>
                </select>
            </div>
            <button type="submit" class="eb-btn eb-btn-secondary">{lang key='go'}</button>
        </form>
    </div>

    {include file="$template/includes/flashmessage-darkmode.tpl"}
    {if $errorMessageHtml}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="danger" errorshtml=$errorMessageHtml}
    {/if}

    <div class="mt-4 eb-subpanel">
        <div class="eb-section-intro">
            <h3 class="eb-section-title">{lang key="contactDetails"}</h3>
            <p class="eb-section-description">Update the selected contact record and email preferences.</p>
        </div>

        <form role="form" method="post" action="{routePath('account-contacts-save')}" class="space-y-6">
            <input type="hidden" name="contactid" value="{$contactid}">

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="inputFirstName" class="eb-field-label">{lang key='clientareafirstname'}</label>
                    <input type="text" name="firstname" id="inputFirstName" value="{$formdata.firstname}" class="eb-input">
                </div>
                <div>
                    <label for="inputLastName" class="eb-field-label">{lang key='clientarealastname'}</label>
                    <input type="text" name="lastname" id="inputLastName" value="{$formdata.lastname}" class="eb-input">
                </div>
                <div>
                    <label for="inputCompanyName" class="eb-field-label">{lang key='clientareacompanyname'}</label>
                    <input type="text" name="companyname" id="inputCompanyName" value="{$formdata.companyname}" class="eb-input">
                </div>
                <div>
                    <label for="inputEmail" class="eb-field-label">{lang key='clientareaemail'}</label>
                    <input type="email" name="email" id="inputEmail" value="{$formdata.email}" class="eb-input">
                </div>
                <div>
                    <label for="country" class="eb-field-label">{lang key='clientareacountry'}</label>
                    {$countriesdropdown|replace:'<select':'<select id="country" class="eb-select"'}
                </div>
                <div>
                    <label for="inputAddress1" class="eb-field-label">{lang key='clientareaaddress1'}</label>
                    <input type="text" name="address1" id="inputAddress1" value="{$formdata.address1}" class="eb-input">
                </div>
                <div>
                    <label for="inputCity" class="eb-field-label">{lang key='clientareacity'}</label>
                    <input type="text" name="city" id="inputCity" value="{$formdata.city}" class="eb-input">
                </div>
                <div>
                    <label for="inputState" class="eb-field-label">{lang key='clientareastate'}</label>
                    <input type="text" name="state" id="inputState" value="{$formdata.state}" class="eb-input">
                </div>
                <div>
                    <label for="inputPostcode" class="eb-field-label">{lang key='clientareapostcode'}</label>
                    <input type="text" name="postcode" id="inputPostcode" value="{$formdata.postcode}" class="eb-input">
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="inputPhone" class="eb-field-label">{lang key='clientareaphonenumber'}</label>
                    <input type="tel" name="phonenumber" id="inputPhone" value="{$formdata.phonenumber}" class="eb-input">
                </div>
            </div>

            <div>
                <h4 class="eb-section-title">{lang key='clientareacontactsemails'}</h4>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    {foreach $formdata.emailPreferences as $emailType => $value}
                        <label class="eb-choice-card{if $value} is-selected{/if}">
                            <input type="hidden" name="email_preferences[{$emailType}]" value="0">
                            <input type="checkbox" class="eb-check-input eb-choice-card-control" name="email_preferences[{$emailType}]" id="{$emailType}emails" value="1"{if $value} checked="checked"{/if}>
                            <span class="eb-choice-card-title">{lang key="clientareacontactsemails"|cat:$emailType}</span>
                        </label>
                    {/foreach}
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <button type="button" class="eb-btn eb-btn-danger" id="deleteContactButton">{lang key='clientareadeletecontact'}</button>
                <button type="reset" class="eb-btn eb-btn-ghost">{lang key='cancel'}</button>
                <button type="submit" class="eb-btn eb-btn-primary">{lang key='clientareasavechanges'}</button>
            </div>
        </form>
    </div>

    <form method="post" action="{routePath('account-contacts-delete')}">
        <input type="hidden" name="contactid" value="{$contactid}">
        <div class="fixed inset-0 z-50 hidden" id="modalDeleteContact">
            <div class="eb-modal-backdrop absolute inset-0" data-contact-delete-overlay="true"></div>
            <div class="relative flex min-h-full items-center justify-center px-4">
                <div class="eb-modal">
                    <div class="eb-modal-header">
                        <div>
                            <h4 class="eb-modal-title">{lang key="clientareadeletecontact"}: {$contact.name}</h4>
                            <p class="eb-modal-subtitle">{lang key="clientareadeletecontactareyousure"}</p>
                        </div>
                    </div>
                    <div class="eb-modal-footer">
                        <button type="button" class="eb-btn eb-btn-ghost" data-contact-delete-close="true">{lang key="cancel"}</button>
                        <button type="submit" class="eb-btn eb-btn-danger">{lang key="confirm"}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebAccountContent
}

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButton = document.getElementById('deleteContactButton');
    const deleteModal = document.getElementById('modalDeleteContact');

    function toggleDeleteModal(show) {
        deleteModal.classList.toggle('hidden', !show);
        document.body.classList.toggle('overflow-hidden', show);
    }

    if (deleteButton) {
        deleteButton.addEventListener('click', function() {
            toggleDeleteModal(true);
        });
    }

    document.querySelectorAll('[data-contact-delete-close="true"], [data-contact-delete-overlay="true"]').forEach(function(node) {
        node.addEventListener('click', function() {
            toggleDeleteModal(false);
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && deleteModal && !deleteModal.classList.contains('hidden')) {
            toggleDeleteModal(false);
        }
    });
});
</script>
