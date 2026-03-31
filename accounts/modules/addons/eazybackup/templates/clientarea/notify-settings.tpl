{assign var="activeTab" value="notifications"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebNotifyBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Notifications</span>
    </div>
{/capture}

{capture name=ebNotifyContent}
    {include
        file="templates/eazyBackup/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebNotifyBreadcrumb
        ebPageTitle="Notifications"
        ebPageDescription="Manage billing notification categories, recipient routing, and dashboard visibility settings."
    }
    <div class="eb-subpanel">
        {if $successful}
            <div class="eb-alert eb-alert--success mb-6">
                <div class="eb-alert-title">{lang key='changessavedsuccessfully'}</div>
            </div>
        {/if}
        {if $errormessage}
            <div class="eb-alert eb-alert--danger mb-6">{$errormessage}</div>
        {/if}

        <form method="post" action="{$modulelink}&a=notify-settings" class="space-y-8">
            <section>
                <div class="eb-section-intro">
                    <h3 class="eb-section-title">Email Categories</h3>
                    <p class="eb-section-description">Choose which billing notifications you receive for cloud backup services.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="flex items-center gap-3">
                        <button type="button" class="eb-toggle" data-toggle data-target="notify_storage">
                            <span class="eb-toggle-track{if $prefs.notify_storage} is-on{/if}">
                                <span class="eb-toggle-thumb"></span>
                            </span>
                        </button>
                        <span class="eb-toggle-label">Storage</span>
                        <input type="hidden" name="notify_storage" id="notify_storage" value="{if $prefs.notify_storage}1{else}0{/if}">
                    </label>
                    <label class="flex items-center gap-3">
                        <button type="button" class="eb-toggle" data-toggle data-target="notify_devices">
                            <span class="eb-toggle-track{if $prefs.notify_devices} is-on{/if}">
                                <span class="eb-toggle-thumb"></span>
                            </span>
                        </button>
                        <span class="eb-toggle-label">Devices</span>
                        <input type="hidden" name="notify_devices" id="notify_devices" value="{if $prefs.notify_devices}1{else}0{/if}">
                    </label>
                    <label class="flex items-center gap-3">
                        <button type="button" class="eb-toggle" data-toggle data-target="notify_addons">
                            <span class="eb-toggle-track{if $prefs.notify_addons} is-on{/if}">
                                <span class="eb-toggle-thumb"></span>
                            </span>
                        </button>
                        <span class="eb-toggle-label">Add-ons</span>
                        <input type="hidden" name="notify_addons" id="notify_addons" value="{if $prefs.notify_addons}1{else}0{/if}">
                    </label>
                </div>
            </section>

            <section>
                <div class="eb-section-intro">
                    <h3 class="eb-section-title">Recipient Routing</h3>
                    <p class="eb-section-description">Choose where notification emails should be sent.</p>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="routing_policy" class="eb-field-label">Default Recipient</label>
                        <div class="relative" x-data="ebSelectMenu({ selectId: 'routing_policy', placeholder: 'Default Recipient' })" x-init="init()" @click.outside="close()" @keydown.escape.prevent="close()">
                            <select id="routing_policy" name="routing_policy" class="sr-only" tabindex="-1" aria-hidden="true">
                                <option value="primary" {if $prefs.routing_policy=='primary'}selected{/if}>Primary</option>
                                <option value="billing" {if $prefs.routing_policy=='billing'}selected{/if}>Billing</option>
                                <option value="technical" {if $prefs.routing_policy=='technical'}selected{/if}>Technical</option>
                                <option value="custom" {if $prefs.routing_policy=='custom'}selected{/if}>Custom</option>
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
                                <template x-for="option in options" :key="'routing-' + option.value">
                                    <button type="button" class="eb-menu-item w-full" :class="selectedValue === option.value ? 'is-active' : ''" @click="select(option.value)">
                                        <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="custom_recipients" class="eb-field-label">Custom Recipient(s)</label>
                        <input
                            type="text"
                            name="custom_recipients"
                            id="custom_recipients"
                            value="{$prefs.custom_recipients|escape}"
                            placeholder="user@example.com, billing@example.com"
                            class="eb-input"
                        />
                        <p class="eb-field-help">Only used when Default Recipient is set to Custom. Separate with commas or semicolons.</p>
                    </div>
                </div>
            </section>

            <section>
                <div class="eb-section-intro">
                    <h3 class="eb-section-title">Dashboard</h3>
                    <p class="eb-section-description">Control whether the upcoming charges panel is visible on your dashboard.</p>
                </div>
                <label class="flex items-center gap-3">
                    <button type="button" class="eb-toggle" data-toggle data-target="show_upcoming_charges">
                        <span class="eb-toggle-track{if $prefs.show_upcoming_charges} is-on{/if}">
                            <span class="eb-toggle-thumb"></span>
                        </span>
                    </button>
                    <span class="eb-toggle-label">Show Upcoming Charges</span>
                    <input type="hidden" name="show_upcoming_charges" id="show_upcoming_charges" value="{if $prefs.show_upcoming_charges}1{else}0{/if}">
                </label>
            </section>

            <div class="flex items-center justify-end gap-3">
                <button type="reset" class="eb-btn eb-btn-ghost">{lang key='cancel'}</button>
                <button type="submit" name="save" value="save" class="eb-btn eb-btn-primary">{lang key='clientareasavechanges'}</button>
            </div>
        </form>
    </div>
{/capture}

{include
    file="templates/eazyBackup/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebNotifyContent
}

{literal}
<script>
function ebSelectMenu(config) {
  return {
    open: false,
    selectId: config.selectId,
    placeholder: config.placeholder || 'Select an option',
    selectedValue: '',
    selectedLabel: config.placeholder || 'Select an option',
    options: [],
    disabled: false,
    init() {
      var select = document.getElementById(this.selectId);
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

      var selectedOption = select.options[select.selectedIndex] || this.options[0] || null;
      this.selectedValue = selectedOption ? selectedOption.value : '';
      this.selectedLabel = selectedOption ? selectedOption.text.trim() : this.placeholder;
    },
    toggle() {
      if (this.disabled) {
        return;
      }

      this.open = !this.open;
    },
    close() {
      this.open = false;
    },
    select(value) {
      var option = this.options.find(function(item) {
        return item.value === value;
      });
      var select = document.getElementById(this.selectId);

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

document.addEventListener('DOMContentLoaded', function () {
  var toggles = document.querySelectorAll('[data-toggle]');
  toggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-target');
      if (!targetId) {
        return;
      }
      var input = document.getElementById(targetId);
      if (!input) {
        return;
      }
      var next = String(parseInt(input.value || '0', 10) === 1 ? 0 : 1);
      input.value = next;
      var track = btn.querySelector('.eb-toggle-track');
      if (track) {
        track.classList.toggle('is-on', next === '1');
      }
    });
  });
});
</script>
{/literal}
