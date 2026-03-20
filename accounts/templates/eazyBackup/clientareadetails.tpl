{assign var="activeTab" value="details"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebAccountBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Profile</span>
    </div>
{/capture}

{capture name=ebAccountContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebAccountBreadcrumb
        ebPageTitle="My Account"
        ebPageDescription="Manage your billing profile and default billing contact."
    }

    {if $successful}
        {include file="$template/includes/ui/eb-alert.tpl"
            ebAlertType="success"
            ebAlertMessage={lang key='changessavedsuccessfully'}
        }
    {/if}

    {if $errormessage}
        {include file="$template/includes/ui/eb-alert.tpl"
            ebAlertType="danger"
            ebAlertMessage=$errormessage
        }
    {/if}

    <form method="post" action="?action=details" class="space-y-4">
        <div class="eb-subpanel">
            <div class="eb-section-intro">
                <h3 class="eb-section-title">Profile Details</h3>
                <p class="eb-section-description">Update the contact and billing information attached to your account.</p>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="inputFirstName" class="eb-field-label">{lang key='clientareafirstname'}</label>
                    <input type="text" name="firstname" id="inputFirstName" value="{$clientfirstname}" class="eb-input" {if in_array('firstname', $uneditablefields)}disabled{/if}>
                </div>
                <div>
                    <label for="inputLastName" class="eb-field-label">{lang key='clientarealastname'}</label>
                    <input type="text" name="lastname" id="inputLastName" value="{$clientlastname}" class="eb-input" {if in_array('lastname', $uneditablefields)}disabled{/if}>
                </div>
                <div>
                    <label for="inputCompanyName" class="eb-field-label">{lang key='clientareacompanyname'}</label>
                    <input type="text" name="companyname" id="inputCompanyName" value="{$clientcompanyname}" class="eb-input" {if in_array('companyname', $uneditablefields)}disabled{/if}>
                </div>
                <div>
                    <label for="inputEmail" class="eb-field-label">{lang key='clientareaemail'}</label>
                    <input type="email" name="email" id="inputEmail" value="{$clientemail}" class="eb-input" {if in_array('email', $uneditablefields)}disabled{/if}>
                </div>
                <div>
                    <label for="country" class="eb-field-label">{lang key='clientareacountry'}</label>
                    {$clientcountriesdropdown|replace:'<select':'<select id="country" class="eb-select"'}
                </div>
                <div>
                    <label for="inputAddress1" class="eb-field-label">{lang key='clientareaaddress1'}</label>
                    <input type="text" name="address1" id="inputAddress1" value="{$clientaddress1}" class="eb-input" {if in_array('address1', $uneditablefields)}disabled{/if}>
                </div>
                <div>
                    <label for="inputCity" class="eb-field-label">{lang key='clientareacity'}</label>
                    <input type="text" name="city" id="inputCity" value="{$clientcity}" class="eb-input" {if in_array('city', $uneditablefields)}disabled{/if}>
                </div>
                <div>
                    <label for="inputState" class="eb-field-label">{lang key='clientareastate'}</label>
                    <input type="text" name="state" id="inputState" value="{$clientstate}" class="eb-input" {if in_array('state', $uneditablefields)}disabled{/if}>
                </div>
                <div>
                    <label for="inputPostcode" class="eb-field-label">{lang key='clientareapostcode'}</label>
                    <input type="text" name="postcode" id="inputPostcode" value="{$clientpostcode}" class="eb-input" {if in_array('postcode', $uneditablefields)}disabled="disabled"{/if}>
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="inputPhone" class="eb-field-label">{lang key='clientareaphonenumber'}</label>
                    <input type="tel" name="phonenumber" id="inputPhone" value="{$clientphonenumber}" class="eb-input" {if in_array('phonenumber', $uneditablefields)}disabled{/if}>
                </div>
            </div>
        </div>

        <div class="eb-subpanel">
            <div class="eb-section-intro">
                <h3 class="eb-section-title">Default Billing Contact</h3>
                <p class="eb-section-description">Choose the contact who should receive invoices and payment notifications.</p>
            </div>

            <div>
                <label for="inputBillingContact" class="eb-field-label">{lang key='defaultbillingcontact'}</label>
                <select id="inputBillingContact" name="billingcid" class="eb-select">
                    <option value="0">{lang key='usedefaultcontact'}</option>
                    {foreach $contacts as $contact}
                        <option value="{$contact.id}" {if $contact.id eq $billingcid}selected="selected"{/if}>{$contact.name}</option>
                    {/foreach}
                </select>
            </div>

            <div class="mt-6 flex items-center justify-end gap-4">
                <button type="reset" class="eb-btn eb-btn-ghost">{lang key='cancel'}</button>
                <button type="submit" name="save" value="save" class="eb-btn eb-btn-primary">{lang key='clientareasavechanges'}</button>
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
    const phoneInput = document.querySelector('#inputPhone');
    if (phoneInput && window.intlTelInput) {
        window.intlTelInput(phoneInput, {
            allowDropdown: false,
            separateDialCode: false
        });
    }
});
</script>
