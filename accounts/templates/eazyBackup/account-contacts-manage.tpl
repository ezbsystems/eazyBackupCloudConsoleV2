<script>
var stateNotRequired = true;
jQuery(document).ready(function() {
    WHMCS.form.register();
});
</script>
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

    <div class="eb-subpanel overflow-visible">
        <form role="form" method="post" action="{routePath('account-contacts')}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="inputContactId" class="eb-field-label">{lang key='clientareachoosecontact'}</label>
                <div class="relative" x-data="ebSelectMenu({ selectId: 'inputContactId', placeholder: '{lang key='clientareachoosecontact'|escape:'javascript'}' })" x-init="init()" @click.outside="close()" @keydown.escape.prevent="close()">
                    <select name="contactid" id="inputContactId" onchange="this.form.submit()" class="sr-only" tabindex="-1" aria-hidden="true">
                        {foreach $contacts as $contact}
                            <option value="{$contact.id}"{if $contact.id eq $contactid} selected="selected"{/if}>{$contact.name} - {$contact.email}</option>
                        {/foreach}
                        <option value="new">{lang key='clientareanavaddcontact'}</option>
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
                        class="eb-menu absolute left-0 right-0 z-50 mt-2 overflow-hidden p-2"
                        style="display: none;">
                        <div class="max-h-64 overflow-y-auto">
                            <template x-for="option in options" :key="'contact-manage-selector-' + option.value">
                                <button type="button" class="eb-menu-item w-full" :class="selectedValue === option.value ? 'is-active' : ''" @click="select(option.value)">
                                    <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="eb-btn eb-btn-secondary">{lang key='go'}</button>
        </form>
    </div>

    {include file="$template/includes/flashmessage-darkmode.tpl"}
    {if $errorMessageHtml}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="danger" errorshtml=$errorMessageHtml}
    {/if}

    <div class="mt-4 eb-subpanel overflow-visible">
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
                    <div class="relative" x-data="ebSelectMenu({ selectId: 'country', placeholder: '{lang key='clientareacountry'|escape:'javascript'}' })" x-init="init()" @click.outside="close()" @keydown.escape.prevent="close()">
                        <div class="sr-only">
                            {$countriesdropdown|replace:'<select':'<select id="country" class="sr-only" tabindex="-1" aria-hidden="true"'}
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
                                <template x-for="option in filteredOptions" :key="'contact-manage-country-' + option.value">
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
                    <input type="text" name="address1" id="inputAddress1" value="{$formdata.address1}" class="eb-input">
                </div>
                <div>
                    <label for="inputCity" class="eb-field-label">{lang key='clientareacity'}</label>
                    <input type="text" name="city" id="inputCity" value="{$formdata.city}" class="eb-input">
                </div>
                <div>
                    <label for="inputState" class="eb-field-label">{lang key='clientareastate'}</label>
                    <div id="contactManageStateWrapper"
                        class="relative"
                        x-data="ebStateSelectMenu({ rootId: 'contactManageStateWrapper', countryId: 'country', placeholder: '{lang key='clientareastate'|escape:'javascript'}' })"
                        x-init="init()"
                        @click.outside="close()"
                        @keydown.escape.prevent="close()">
                        <input type="text" name="state" id="inputState" value="{$formdata.state}" class="eb-input">
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
                                        <template x-for="option in options" :key="'contact-manage-state-' + option.value">
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
    ebPanelClass="!overflow-visible"
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
