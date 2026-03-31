<script>
var stateNotRequired = true;
jQuery(document).ready(function() {
    WHMCS.form.register();
});
</script>
<script src="{$BASE_PATH_JS}/StatesDropdown.js"></script>

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
        <div class="eb-subpanel overflow-visible">
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
                    <div class="relative" x-data="ebSelectMenu({ selectId: 'country', placeholder: '{lang key='clientareacountry'|escape:'javascript'}' })" x-init="init()" @click.outside="close()" @keydown.escape.prevent="close()">
                        <div class="sr-only">
                            {$clientcountriesdropdown|replace:'<select':'<select id="country" class="sr-only" tabindex="-1" aria-hidden="true"'}
                        </div>
                        <button type="button"
                            class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left"
                            @click="toggle()"
                            :aria-expanded="open"
                            :disabled="disabled">
                            <span class="min-w-0 flex-1 truncate" x-text="selectedLabel"></span>
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="eb-menu absolute left-0 right-0 z-50 mt-2 overflow-hidden p-2"
                            style="display: none;">
                            <div class="border-b border-[var(--eb-border-subtle)] pb-2">
                                <input type="search" x-model="search" placeholder="Search countries..." class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm" @click.stop>
                            </div>
                            <div class="mt-2 max-h-64 overflow-y-auto">
                                <template x-for="option in filteredOptions" :key="'country-' + option.value">
                                    <button type="button" class="eb-menu-item w-full" :class="selectedValue === option.value ? 'is-active' : ''" @click="select(option.value)">
                                        <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                    </button>
                                </template>
                                <div x-show="filteredOptions.length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No countries match your search.</div>
                            </div>
                        </div>
                    </div>
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
                    <div id="inputStateWrapper"
                        class="relative"
                        x-data="ebStateSelectMenu({ rootId: 'inputStateWrapper', countryId: 'country', placeholder: '{lang key='clientareastate'|escape:'javascript'}' })"
                        x-init="init()"
                        @click.outside="close()"
                        @keydown.escape.prevent="close()">
                        <input type="text" name="state" id="inputState" value="{$clientstate}" class="eb-input" {if in_array('state', $uneditablefields)}disabled{/if}>
                        <template x-if="hasSelect">
                            <div>
                                <button type="button"
                                    class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left"
                                    @click="toggle()"
                                    :aria-expanded="open"
                                    :disabled="disabled">
                                    <span class="min-w-0 flex-1 truncate" x-text="selectedLabel"></span>
                                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div x-show="open"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="eb-menu absolute left-0 right-0 z-50 mt-2 overflow-hidden p-2"
                                    style="display: none;">
                                    <div class="max-h-64 overflow-y-auto">
                                        <template x-for="option in options" :key="'state-' + option.value">
                                            <button type="button" class="eb-menu-item w-full" :class="selectedValue === option.value ? 'is-active' : ''" @click="select(option.value)">
                                                <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
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
                <div class="relative" x-data="ebSelectMenu({ selectId: 'inputBillingContact', placeholder: '{lang key='defaultbillingcontact'|escape:'javascript'}' })" x-init="init()" @click.outside="close()" @keydown.escape.prevent="close()">
                    <select id="inputBillingContact" name="billingcid" class="sr-only" tabindex="-1" aria-hidden="true">
                        <option value="0">{lang key='usedefaultcontact'}</option>
                        {foreach $contacts as $contact}
                            <option value="{$contact.id}" {if $contact.id eq $billingcid}selected="selected"{/if}>{$contact.name}</option>
                        {/foreach}
                    </select>
                    <button type="button"
                        class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left"
                        @click="toggle()"
                        :aria-expanded="open"
                        :disabled="disabled">
                        <span class="min-w-0 flex-1 truncate" x-text="selectedLabel"></span>
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="open"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="eb-menu absolute left-0 right-0 z-50 mt-2 overflow-hidden p-1"
                        style="display: none;">
                        <template x-for="option in options" :key="'billing-contact-' + option.value">
                            <button type="button" class="eb-menu-item w-full" :class="selectedValue === option.value ? 'is-active' : ''" @click="select(option.value)">
                                <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-4">
                <button type="reset" class="eb-btn eb-btn-ghost">{lang key='cancel'}</button>
                <button type="submit" name="save" value="save" class="eb-btn eb-btn-primary">{lang key='clientareasavechanges'}</button>
            </div>
        </div>
    </form>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPanelClass="!overflow-visible"
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

function ebSelectMenu(config) {
    return {
        open: false,
        search: '',
        selectId: config.selectId,
        placeholder: config.placeholder || 'Select an option',
        selectedValue: '',
        selectedLabel: config.placeholder || 'Select an option',
        options: [],
        disabled: false,
        init() {
            const select = document.getElementById(this.selectId);
            if (!select) {
                return;
            }

            this.disabled = select.disabled;
            this.options = Array.from(select.options).map(function(option) {
                return {
                    value: option.value,
                    label: option.text.trim(),
                    disabled: option.disabled
                };
            }).filter(function(option) {
                return !option.disabled;
            });

            const selectedOption = select.options[select.selectedIndex] || this.options[0] || null;
            this.selectedValue = selectedOption ? selectedOption.value : '';
            this.selectedLabel = selectedOption ? selectedOption.text.trim() : this.placeholder;
        },
        get filteredOptions() {
            if (!this.search) {
                return this.options;
            }

            const query = this.search.toLowerCase();
            return this.options.filter(function(option) {
                return option.label.toLowerCase().includes(query);
            });
        },
        toggle() {
            if (this.disabled) {
                return;
            }

            this.open = !this.open;
            if (!this.open) {
                this.search = '';
            }
        },
        close() {
            this.open = false;
            this.search = '';
        },
        select(value) {
            const option = this.options.find(function(item) {
                return item.value === value;
            });
            const select = document.getElementById(this.selectId);

            if (!option || !select) {
                return;
            }

            this.selectedValue = option.value;
            this.selectedLabel = option.label;
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            select.dispatchEvent(new Event('input', { bubbles: true }));
            this.close();
        }
    };
}

function ebStateSelectMenu(config) {
    return {
        open: false,
        rootId: config.rootId,
        countryId: config.countryId,
        placeholder: config.placeholder || 'Select an option',
        selectedValue: '',
        selectedLabel: config.placeholder || 'Select an option',
        options: [],
        disabled: false,
        hasSelect: false,
        init() {
            const country = document.getElementById(this.countryId);
            if (country) {
                country.addEventListener('change', () => {
                    window.setTimeout(() => this.sync(), 50);
                });
            }

            this.sync();
            window.setTimeout(() => this.sync(), 120);
        },
        sync() {
            const root = document.getElementById(this.rootId);
            const select = root ? root.querySelector('#stateselect') : null;

            if (!select) {
                this.hasSelect = false;
                this.options = [];
                this.selectedValue = '';
                this.selectedLabel = this.placeholder;
                this.disabled = false;
                this.close();
                return;
            }

            select.classList.add('sr-only');
            select.setAttribute('tabindex', '-1');
            select.setAttribute('aria-hidden', 'true');

            this.hasSelect = true;
            this.disabled = select.disabled;
            this.options = Array.from(select.options).map(function(option) {
                return {
                    value: option.value,
                    label: option.text.trim(),
                    disabled: option.disabled
                };
            }).filter(function(option) {
                return !option.disabled;
            });

            const selectedOption = select.options[select.selectedIndex] || this.options[0] || null;
            this.selectedValue = selectedOption ? selectedOption.value : '';
            this.selectedLabel = selectedOption ? selectedOption.text.trim() : this.placeholder;
        },
        toggle() {
            if (this.disabled || !this.hasSelect) {
                return;
            }

            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
        select(value) {
            const root = document.getElementById(this.rootId);
            const select = root ? root.querySelector('#stateselect') : null;
            const option = this.options.find(function(item) {
                return item.value === value;
            });

            if (!select || !option) {
                return;
            }

            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            select.dispatchEvent(new Event('input', { bubbles: true }));
            this.selectedValue = option.value;
            this.selectedLabel = option.label;
            this.close();
        }
    };
}
</script>
